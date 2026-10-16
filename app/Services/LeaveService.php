<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Repositories\Contracts\LeaveRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(
        protected LeaveRepositoryInterface $leaveRepository
    ) {}

    /**
     * Submit a new leave or permit request.
     *
     * @param  array{leave_type_id: int, start_date: string, end_date: string, reason: string}  $data
     *
     * @throws Exception
     */
    public function applyLeave(int $employeeId, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $today = Carbon::today();

        if ($endDate->lessThan($startDate)) {
            throw new Exception('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        // 1. Validasi batas waktu pengajuan (H-3 untuk Cuti Tahunan)
        $noticeDays = (int) $today->diffInDays($startDate, false);
        if ($leaveType->min_notice_days > 0 && $noticeDays < $leaveType->min_notice_days) {
            throw new Exception("Pengajuan {$leaveType->name} wajib diajukan minimal H-{$leaveType->min_notice_days} sebelum tanggal mulai.");
        }

        // 2. Hitung total hari kerja cuti
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // 3. Validasi sisa kuota jika jenis cuti memotong kuota tahunan
        $currentYear = (int) $startDate->year;
        if ($leaveType->deduct_quota) {
            $balance = $this->leaveRepository->getBalance($employeeId, $leaveType->id, $currentYear);
            $remaining = $balance ? $balance->remaining_quota : 0;

            if ($remaining < $totalDays) {
                throw new Exception("Sisa kuota {$leaveType->name} Anda tidak mencukupi (Sisa: {$remaining} hari, diajukan: {$totalDays} hari).");
            }
        }

        // 4. Validasi lampiran surat dokter (untuk Cuti Sakit, maks 1MB)
        $docPath = null;
        if ($leaveType->requires_attachment) {
            if (! $attachment) {
                throw new Exception("Pengajuan {$leaveType->name} wajib mengunggah file bukti surat dokter.");
            }

            if ($attachment->getSize() > 1024 * 1024) {
                throw new Exception('Ukuran file bukti surat dokter maksimal 1MB.');
            }

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            if (! in_array(strtolower($attachment->getClientOriginalExtension()), $allowedExtensions, true)) {
                throw new Exception('Format file bukti harus PDF, JPG, atau PNG.');
            }

            $docPath = $attachment->store('leave_attachments', 'public');
        } elseif ($attachment) {
            $docPath = $attachment->store('leave_attachments', 'public');
        }

        $requestNumber = 'LR-'.date('Ym').'-'.sprintf('%04d', rand(1, 9999));

        return $this->leaveRepository->create([
            'request_number' => $requestNumber,
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveType->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'attachment_path' => $docPath,
            'status' => 'pending',
            'current_step' => 'manager',
        ]);
    }

    /**
     * Stage 1: Line Manager approves leave request.
     *
     * @throws Exception
     */
    public function approveByManager(int $leaveRequestId, int $reviewerUserId, ?string $notes = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $reviewerUserId, $notes) {
            $leave = $this->leaveRepository->findOrFail($leaveRequestId);

            if ($leave->status !== 'pending' || $leave->current_step !== 'manager') {
                throw new Exception('Pengajuan cuti ini tidak dalam status menunggu persetujuan Line Manager.');
            }

            // Record Line Manager approval
            Approval::create([
                'approvable_type' => LeaveRequest::class,
                'approvable_id' => $leave->id,
                'stage' => 'manager',
                'reviewer_id' => $reviewerUserId,
                'status' => 'approved',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            // Advance step to HR Manager
            $leave->update([
                'current_step' => 'hr',
            ]);

            return $leave->fresh(['approvals.reviewer', 'leaveType']);
        });
    }

    /**
     * Stage 2: HR Manager final authorization and safe quota deduction.
     *
     * @throws Exception
     */
    public function approveByHr(int $leaveRequestId, int $reviewerUserId, ?string $notes = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $reviewerUserId, $notes) {
            $leave = $this->leaveRepository->findOrFail($leaveRequestId);

            if ($leave->status !== 'pending' || $leave->current_step !== 'hr') {
                throw new Exception('Pengajuan cuti ini tidak dalam status menunggu verifikasi HR Manager.');
            }

            $leaveType = $leave->leaveType;

            // Safe Atomic Quota Deduction with Row Locking
            if ($leaveType->deduct_quota) {
                $year = Carbon::parse($leave->start_date)->year;

                $balance = LeaveBalance::where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (! $balance || $balance->remaining_quota < $leave->total_days) {
                    throw new Exception('Persetujuan gagal: Sisa kuota cuti karyawan tidak mencukupi untuk dipotong.');
                }

                $balance->decrement('remaining_quota', $leave->total_days);
                $balance->increment('used_quota', $leave->total_days);
            }

            // Record HR approval
            Approval::create([
                'approvable_type' => LeaveRequest::class,
                'approvable_id' => $leave->id,
                'stage' => 'hr',
                'reviewer_id' => $reviewerUserId,
                'status' => 'approved',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            // Complete approval cycle
            $leave->update([
                'status' => 'approved',
                'current_step' => 'completed',
            ]);

            return $leave->fresh(['approvals.reviewer', 'leaveType']);
        });
    }

    /**
     * Reject leave request at either Line Manager or HR stage.
     *
     * @throws Exception
     */
    public function rejectLeave(int $leaveRequestId, int $reviewerUserId, string $stage, string $notes): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $reviewerUserId, $stage, $notes) {
            $leave = $this->leaveRepository->findOrFail($leaveRequestId);

            if ($leave->status !== 'pending') {
                throw new Exception('Pengajuan cuti ini sudah tidak dalam status pending.');
            }

            Approval::create([
                'approvable_type' => LeaveRequest::class,
                'approvable_id' => $leave->id,
                'stage' => $stage,
                'reviewer_id' => $reviewerUserId,
                'status' => 'rejected',
                'notes' => $notes,
                'action_at' => now(),
            ]);

            $leave->update([
                'status' => 'rejected',
                'current_step' => 'completed',
            ]);

            return $leave->fresh(['approvals.reviewer', 'leaveType']);
        });
    }

    /**
     * Get pending leave requests for a line manager.
     */
    public function getPendingRequestsForManager(int $managerEmployeeId): Collection
    {
        return $this->leaveRepository->getPendingForManager($managerEmployeeId);
    }

    /**
     * Get pending leave requests escalated to HR.
     */
    public function getPendingRequestsForHr(): Collection
    {
        return $this->leaveRepository->getPendingForHr();
    }
}
