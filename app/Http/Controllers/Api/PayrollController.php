<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\Payroll;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService,
        protected PayrollRepositoryInterface $payrollRepository
    ) {}

    /**
     * Get list of monthly payroll periods.
     */
    public function periods(): JsonResponse
    {
        $periods = $this->payrollRepository->getPeriods();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    /**
     * Create a new monthly payroll period.
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'cutoff_date' => ['required', 'date', 'after:start_date'],
            'payment_date' => ['required', 'date', 'after_or_equal:cutoff_date'],
        ]);

        try {
            $period = $this->payrollService->createPeriod(
                $validated['name'],
                $validated['start_date'],
                $validated['cutoff_date'],
                $validated['payment_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Periode payroll berhasil dibuat.',
                'data' => $period,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate draft monthly payroll calculations (HR Staff action after cutoff date).
     */
    public function generateDraft(Request $request, int $periodId): JsonResponse
    {
        $user = $request->user();

        try {
            $period = $this->payrollService->generateDraftPayroll($periodId, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Draf kalkulasi payroll berhasil diproses untuk seluruh karyawan aktif.',
                'data' => $period,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Final authorization and digital payslip publication (HR Manager action).
     */
    public function authorizePeriod(Request $request, int $periodId): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['HR Manager', 'Superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya HR Manager yang memiliki wewenang memberikan pengesahan final payroll.',
            ], 403);
        }

        try {
            $period = $this->payrollService->authorizeAndPublishPayroll($periodId, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Payroll berhasil disahkan! Slip gaji digital telah diterbitkan ke portal karyawan.',
                'data' => $period,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get all payroll calculations in a period (for HR management).
     */
    public function periodPayrolls(int $periodId): JsonResponse
    {
        $payrolls = $this->payrollRepository->getPayrollsForPeriod($periodId);

        return response()->json([
            'success' => true,
            'data' => PayrollResource::collection($payrolls),
        ]);
    }

    /**
     * Get published payslips for the logged in employee.
     */
    public function myPayslips(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $payslips = $this->payrollService->getEmployeePayslips($employee->id);

        return response()->json([
            'success' => true,
            'data' => PayrollResource::collection($payslips),
        ]);
    }

    /**
     * Display single payslip detail with breakdown items.
     */
    public function show(int $id): JsonResponse
    {
        $payroll = $this->payrollService->getPayslipDetail($id);

        if (! $payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Slip gaji tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PayrollResource($payroll),
        ]);
    }

    /**
     * Download digital payslip as PDF (generated via DomPDF).
     */
    public function downloadPdf(int $id): Response|JsonResponse
    {
        $payroll = $this->payrollService->getPayslipDetail($id);

        if (! $payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Slip gaji tidak ditemukan.',
            ], 404);
        }

        $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $payroll]);

        return $pdf->download('Slip_Gaji_'.$payroll->payslip_number.'.pdf');
    }
}
