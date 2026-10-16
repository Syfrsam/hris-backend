<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Repositories\Contracts\LeaveRepositoryInterface;
use App\Services\LeaveService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveService $leaveService,
        protected LeaveRepositoryInterface $leaveRepository
    ) {}

    /**
     * Get active leave types for application form dropdown.
     */
    public function leaveTypes(): JsonResponse
    {
        $types = LeaveType::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get current leave balances for logged in employee.
     */
    public function balances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $year = (int) $request->input('year', date('Y'));
        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get();

        return response()->json([
            'success' => true,
            'year' => $year,
            'data' => $balances,
        ]);
    }

    /**
     * Display listing of employee's own leave requests.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $requests = $this->leaveRepository->getEmployeeLeaveRequests($employee->id);

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($requests),
        ]);
    }

    /**
     * Submit a new leave, sick permit, or time-off request.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
        ]);

        try {
            $leave = $this->leaveService->applyLeave(
                $employee->id,
                $validated,
                $request->file('attachment')
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil diajukan dan sedang menunggu persetujuan Line Manager.',
                'data' => new LeaveRequestResource($leave->load(['leaveType', 'approvals'])),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
