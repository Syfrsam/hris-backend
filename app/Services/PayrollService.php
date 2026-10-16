<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected PayrollRepositoryInterface $payrollRepository,
        protected AttendanceService $attendanceService,
        protected OvertimeService $overtimeService
    ) {}

    /**
     * Create a new monthly payroll period.
     *
     * @throws Exception
     */
    public function createPeriod(string $name, string $startDate, string $cutoffDate, string $paymentDate): PayrollPeriod
    {
        if (Carbon::parse($cutoffDate)->lessThan(Carbon::parse($startDate))) {
            throw new Exception('Tanggal cut-off tidak boleh lebih awal dari tanggal mulai periode.');
        }

        return PayrollPeriod::create([
            'name' => $name,
            'start_date' => $startDate,
            'cutoff_date' => $cutoffDate,
            'payment_date' => $paymentDate,
            'status' => 'draft',
        ]);
    }

    /**
     * Generate draft monthly payroll calculations (Processed by HR Staff after cut-off date).
     *
     * @throws Exception
     */
    public function generateDraftPayroll(int $periodId, int $hrStaffUserId): PayrollPeriod
    {
        $period = PayrollPeriod::findOrFail($periodId);

        // Aturan Bisnis: Hanya dapat diproses setelah tanggal cut-off absensi
        $today = Carbon::today();
        $cutoff = Carbon::parse($period->cutoff_date);

        if ($today->lessThan($cutoff)) {
            throw new Exception("Generate draft payroll hanya dapat diproses setelah tanggal cut-off absensi ({$period->cutoff_date}). Hari ini masih tanggal {$today->toDateString()}.");
        }

        if ($period->status === 'approved' || $period->status === 'disbursed') {
            throw new Exception('Periode payroll ini sudah disahkan atau didistribusikan, tidak dapat di-generate ulang.');
        }

        return DB::transaction(function () use ($period, $hrStaffUserId) {
            // Hapus draf payroll lama pada periode ini jika ada (regenerate)
            Payroll::where('payroll_period_id', $period->id)->delete();

            $activeEmployees = Employee::with(['activeContract', 'jobPosition.jobGrade'])
                ->where('employment_status', '!=', 'intern')
                ->whereNull('resign_date')
                ->get();

            foreach ($activeEmployees as $employee) {
                $this->calculateEmployeePayroll($employee, $period);
            }

            $period->update([
                'status' => 'calculated',
                'generated_by' => $hrStaffUserId,
            ]);

            return $period->fresh(['payrolls.items', 'generator']);
        });
    }

    /**
     * Calculate individual employee net salary, deductions, overtime, and itemized payslip.
     */
    public function calculateEmployeePayroll(Employee $employee, PayrollPeriod $period): Payroll
    {
        $contract = $employee->activeContract;
        $basicSalary = (float) ($contract ? $contract->basic_salary : 0);

        if ($basicSalary <= 0 && $employee->jobPosition?->jobGrade) {
            $basicSalary = (float) $employee->jobPosition->jobGrade->min_salary;
        }

        // 1. Rekapitulasi absensi & keterlambatan dalam periode cut-off
        $attendanceRecap = $this->attendanceService->getMonthlyRecap(
            $employee->id,
            $period->start_date,
            $period->cutoff_date
        );

        $totalLateMinutes = $attendanceRecap['total_late_minutes'];

        // Potongan Keterlambatan: 1 menit = Gaji Pokok / (21 hari kerja * 8 jam * 60 menit)
        $standardWorkMinutesPerMonth = 21 * 8 * 60;
        $ratePerMinute = $standardWorkMinutesPerMonth > 0 ? ($basicSalary / $standardWorkMinutesPerMonth) : 0;
        $lateDeductionAmount = round($totalLateMinutes * $ratePerMinute, 2);

        // 2. Akumulasi jam lembur yang telah disetujui (Approved Overtime)
        $approvedOvertimes = $this->overtimeService->calculateDepnakerOvertimePay(
            $basicSalary,
            (float) DB::table('overtime_requests')
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$period->start_date, $period->cutoff_date])
                ->sum('duration_hours')
        );
        $totalOvertimePay = $approvedOvertimes;

        // 3. Tunjangan Tetap (Transportasi & Uang Makan)
        $allowanceAmount = round($basicSalary * 0.10, 2); // Contoh tunjangan 10%

        // 4. Potongan BPJS Karyawan
        $bpjsKesehatan = round($basicSalary * 0.01, 2); // 1% pekerja
        $bpjsKetenagakerjaan = round($basicSalary * 0.03, 2); // 2% JHT + 1% JP

        // 5. Potongan Pajak PPh 21 Estimasi
        $grossIncome = $basicSalary + $allowanceAmount + $totalOvertimePay;
        $taxPph21 = round($grossIncome > 4500000 ? ($grossIncome * 0.05) : 0, 2);

        $totalDeductions = $lateDeductionAmount + $bpjsKesehatan + $bpjsKetenagakerjaan + $taxPph21;
        $netSalary = max(0, round($grossIncome - $totalDeductions, 2));

        $payslipNumber = 'SLIP-'.date('Ym', strtotime($period->start_date)).'-'.sprintf('%04d', $employee->id);

        $payroll = Payroll::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'payslip_number' => $payslipNumber,
            'basic_salary' => $basicSalary,
            'total_allowances' => $allowanceAmount,
            'total_overtime_pay' => $totalOvertimePay,
            'total_deductions' => $totalDeductions,
            'late_deduction_amount' => $lateDeductionAmount,
            'bpjs_kesehatan' => $bpjsKesehatan,
            'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
            'tax_pph21' => $taxPph21,
            'net_salary' => $netSalary,
            'status' => 'draft',
        ]);

        // 6. Buat rincian komponen pendapatan & potongan (Itemized Breakdown)
        $items = [
            ['name' => 'Gaji Pokok', 'type' => 'earning', 'amount' => $basicSalary],
            ['name' => 'Tunjangan Operasional / Transport', 'type' => 'earning', 'amount' => $allowanceAmount],
        ];

        if ($totalOvertimePay > 0) {
            $items[] = ['name' => 'Upah Lembur Resmi', 'type' => 'earning', 'amount' => $totalOvertimePay];
        }

        if ($lateDeductionAmount > 0) {
            $items[] = [
                'name' => "Denda Keterlambatan ({$totalLateMinutes} menit)",
                'type' => 'deduction',
                'amount' => $lateDeductionAmount,
            ];
        }

        $items[] = ['name' => 'BPJS Kesehatan (1%)', 'type' => 'deduction', 'amount' => $bpjsKesehatan];
        $items[] = ['name' => 'BPJS Ketenagakerjaan (3%)', 'type' => 'deduction', 'amount' => $bpjsKetenagakerjaan];

        if ($taxPph21 > 0) {
            $items[] = ['name' => 'Pajak Penghasilan (PPh 21)', 'type' => 'deduction', 'amount' => $taxPph21];
        }

        foreach ($items as $item) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $item['name'],
                'type' => $item['type'],
                'amount' => $item['amount'],
            ]);
        }

        return $payroll;
    }

    /**
     * Stage 2: HR Manager gives final authorization and publishes payslips to employee portals.
     *
     * @throws Exception
     */
    public function authorizeAndPublishPayroll(int $periodId, int $hrManagerUserId): PayrollPeriod
    {
        return DB::transaction(function () use ($periodId, $hrManagerUserId) {
            $period = PayrollPeriod::findOrFail($periodId);

            if ($period->status !== 'calculated') {
                throw new Exception('Hanya draf payroll berstatus "calculated" yang dapat disahkan oleh HR Manager.');
            }

            // Otorisasi final HR Manager
            $period->update([
                'status' => 'approved',
                'approved_by' => $hrManagerUserId,
                'approved_at' => now(),
            ]);

            // Terbitkan seluruh slip gaji ke status published
            Payroll::where('payroll_period_id', $period->id)->update([
                'status' => 'published',
            ]);

            return $period->fresh(['approver']);
        });
    }

    /**
     * Get published payslips history for Employee Self-Service (ESS).
     */
    public function getEmployeePayslips(int $employeeId): Collection
    {
        return $this->payrollRepository->getEmployeePayslips($employeeId);
    }

    /**
     * Get full payslip detail.
     */
    public function getPayslipDetail(int $payrollId): ?Payroll
    {
        return $this->payrollRepository->getPayslipDetail($payrollId);
    }
}
