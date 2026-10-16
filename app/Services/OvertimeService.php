<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\OvertimeRequest;
use App\Repositories\Contracts\OvertimeRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    public function __construct(
        protected OvertimeRepositoryInterface $overtimeRepository
    ) {}

    /**
     * Submit a new overtime request (Max 4 hours per workday).
     *
     * @param  array{date: string, start_time: string, end_time: string, duration_hours: float, task_description: string}  $data
     *
     * @throws Exception
     */
    public function applyOvertime(int $employeeId, array $data): OvertimeRequest
    {
        $duration = (float) $data['duration_hours'];

        if ($duration <= 0) {
            throw new Exception('Durasi lembur harus lebih dari 0 jam.');
        }

        // Validasi aturan: Maksimal 4 jam per hari kerja
        if ($duration > 4.0) {
            throw new Exception('Pengajuan lembur dibatasi maksimal 4 jam per hari kerja.');
        }

        // Cek total durasi jika sudah ada pengajuan di tanggal yang sama
        $existingHours = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->where('date', $data['date'])
            ->whereIn('status', ['pending', 'approved'])
            ->sum('duration_hours');

        if (($existingHours + $duration) > 4.0) {
            throw new Exception("Total pengajuan lembur pada tanggal {$data['date']} melebihi batas maksimal 4 jam (Sudah diajukan: {$existingHours} jam, baru: {$duration} jam).");
        }

        $requestNumber = 'OT-'.date('Ym').'-'.sprintf('%04d', rand(1, 9999));

        return $this->overtimeRepository->create([
            'request_number' => $requestNumber,
            'employee_id' => $employeeId,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration_hours' => $duration,
            'task_description' => $data['task_description'],
            'rate_multiplier' => 1.50,
            'status' => 'pending',
            'current_step' => 'manager',
        ]);
    }

    /**
     * Stage 1: Line Manager approves overtime request.
     *
     * @throws Exception
     */
    public function approveByManager(int $overtimeRequestId, int $reviewerUserId, ?string $notes = null): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequestId, $reviewerUserId, $notes) {
            $overtime = $this->overtimeRepository->findOrFail($overtimeRequestId);

            if ($overtime->status !== 'pending' || $overtime->current_step !== 'manager') {
                throw new Exception('Pengajuan lembur tidak dalam status menunggu persetujuan Line Manager.');
            }

            Approval::create([
                'approvable_type' => OvertimeRequest::class,
                'approvable_id' => $overtime->id,
                'stage' => 'manager',
                'reviewer_id' => $reviewerUserId,
                'status' => 'approved',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            $overtime->update([
                'current_step' => 'hr',
            ]);

            return $overtime->fresh(['approvals.reviewer']);
        });
    }

    /**
     * Stage 2: HR Manager final authorization.
     *
     * @throws Exception
     */
    public function approveByHr(int $overtimeRequestId, int $reviewerUserId, ?string $notes = null): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequestId, $reviewerUserId, $notes) {
            $overtime = $this->overtimeRepository->findOrFail($overtimeRequestId);

            if ($overtime->status !== 'pending' || $overtime->current_step !== 'hr') {
                throw new Exception('Pengajuan lembur tidak dalam status menunggu verifikasi HR Manager.');
            }

            Approval::create([
                'approvable_type' => OvertimeRequest::class,
                'approvable_id' => $overtime->id,
                'stage' => 'hr',
                'reviewer_id' => $reviewerUserId,
                'status' => 'approved',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            $overtime->update([
                'status' => 'approved',
                'current_step' => 'completed',
            ]);

            return $overtime->fresh(['approvals.reviewer']);
        });
    }

    /**
     * Reject overtime request.
     *
     * @throws Exception
     */
    public function rejectOvertime(int $overtimeRequestId, int $reviewerUserId, string $stage, string $notes): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequestId, $reviewerUserId, $stage, $notes) {
            $overtime = $this->overtimeRepository->findOrFail($overtimeRequestId);

            if ($overtime->status !== 'pending') {
                throw new Exception('Pengajuan lembur sudah tidak dalam status pending.');
            }

            Approval::create([
                'approvable_type' => OvertimeRequest::class,
                'approvable_id' => $overtime->id,
                'stage' => $stage,
                'reviewer_id' => $reviewerUserId,
                'status' => 'rejected',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            $overtime->update([
                'status' => 'rejected',
                'current_step' => 'completed',
            ]);

            return $overtime->fresh(['approvals.reviewer']);
        });
    }

    /**
     * Calculate overtime compensation according to standard Depnaker regulation:
     * - Hourly rate = 1 / 173 * Basic Salary
     * - 1st hour = 1.5 * Hourly rate
     * - Subsequent hours = 2.0 * Hourly rate
     */
    public function calculateDepnakerOvertimePay(float $basicSalary, float $durationHours): float
    {
        if ($durationHours <= 0 || $basicSalary <= 0) {
            return 0.0;
        }

        $hourlyRate = $basicSalary / 173;

        if ($durationHours <= 1.0) {
            return round($durationHours * 1.5 * $hourlyRate, 2);
        }

        $firstHourPay = 1.0 * 1.5 * $hourlyRate;
        $subsequentHours = $durationHours - 1.0;
        $subsequentPay = $subsequentHours * 2.0 * $hourlyRate;

        return round($firstHourPay + $subsequentPay, 2);
    }
}
