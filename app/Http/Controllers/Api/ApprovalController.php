<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\OvertimeRequest;
use App\Services\LeaveService;
use App\Services\OvertimeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        protected LeaveService $leaveService,
        protected OvertimeService $overtimeService
    ) {}

    /**
     * Get pending leave requests waiting for current reviewer's stage.
     */
    public function pendingLeaves(Request $request): JsonResponse
    {
        $user = $request->user();
        $isHr = $user->hasRole(['HR Manager', 'Superadmin']);
        $managerEmployee = $user->employee;

        if ($isHr) {
            $requests = $this->leaveService->getPendingRequestsForHr();
        } elseif ($managerEmployee) {
            $requests = $this->leaveService->getPendingRequestsForManager($managerEmployee->id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses persetujuan.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'stage' => $isHr ? 'hr' : 'manager',
            'data' => LeaveRequestResource::collection($requests),
        ]);
    }

    /**
     * Approve leave request (Stage 1 Manager or Stage 2 HR).
     */
    public function approveLeave(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $isHr = $user->hasRole(['HR Manager', 'Superadmin']);

        try {
            if ($isHr) {
                $leave = $this->leaveService->approveByHr($id, $user->id, $request->notes);
                $message = 'Pengajuan cuti telah disahkan dan kuota tahunan telah dipotong.';
            } else {
                $leave = $this->leaveService->approveByManager($id, $user->id, $request->notes);
                $message = 'Pengajuan cuti telah disetujui Line Manager dan diteruskan ke HR Manager.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new LeaveRequestResource($leave),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject leave request with justification notes.
     */
    public function rejectLeave(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $stage = $user->hasRole(['HR Manager', 'Superadmin']) ? 'hr' : 'manager';

        try {
            $leave = $this->leaveService->rejectLeave($id, $user->id, $stage, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti telah ditolak.',
                'data' => new LeaveRequestResource($leave),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get pending overtime requests for current reviewer.
     */
    public function pendingOvertimes(Request $request): JsonResponse
    {
        $user = $request->user();
        $isHr = $user->hasRole(['HR Manager', 'Superadmin']);
        $managerEmployee = $user->employee;

        if ($isHr) {
            $requests = OvertimeRequest::with(['employee.user', 'employee.department', 'approvals.reviewer'])
                ->where('status', 'pending')
                ->where('current_step', 'hr')
                ->latest()
                ->get();
        } elseif ($managerEmployee) {
            $requests = OvertimeRequest::with(['employee.user', 'employee.department'])
                ->where('status', 'pending')
                ->where('current_step', 'manager')
                ->whereHas('employee', function ($q) use ($managerEmployee) {
                    $q->where('manager_id', $managerEmployee->id);
                })
                ->latest()
                ->get();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses persetujuan.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'stage' => $isHr ? 'hr' : 'manager',
            'data' => OvertimeRequestResource::collection($requests),
        ]);
    }

    /**
     * Approve overtime request.
     */
    public function approveOvertime(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $isHr = $user->hasRole(['HR Manager', 'Superadmin']);

        try {
            if ($isHr) {
                $overtime = $this->overtimeService->approveByHr($id, $user->id, $request->notes);
                $message = 'Pengajuan lembur telah disahkan oleh HR Manager.';
            } else {
                $overtime = $this->overtimeService->approveByManager($id, $user->id, $request->notes);
                $message = 'Pengajuan lembur telah disetujui Line Manager dan diteruskan ke HR Manager.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new OvertimeRequestResource($overtime),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject overtime request with justification notes.
     */
    public function rejectOvertime(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $stage = $user->hasRole(['HR Manager', 'Superadmin']) ? 'hr' : 'manager';

        try {
            $overtime = $this->overtimeService->rejectOvertime($id, $user->id, $stage, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan lembur telah ditolak.',
                'data' => new OvertimeRequestResource($overtime),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
