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
use Illuminate\Support\Facades\Cache;

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
        $types = Cache::remember('master.leave_types', 300, function () {
            return LeaveType::where('is_active', true)->orderBy('id')->get()->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get all leave types for Superadmin / HR management.
     */
    public function allLeaveTypes(): JsonResponse
    {
        $types = LeaveType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Store new leave type.
     */
    public function storeLeaveType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:leave_types,code'],
            'name' => ['required', 'string', 'max:50'],
            'default_quota' => ['required', 'integer', 'min:0'],
            'deduct_quota' => ['boolean'],
            'min_notice_days' => ['required', 'integer', 'min:0'],
            'requires_attachment' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $type = LeaveType::create($validated);
        Cache::forget('master.leave_types');

        return response()->json([
            'success' => true,
            'message' => 'Jenis cuti baru berhasil ditambahkan.',
            'data' => $type,
        ], 201);
    }

    /**
     * Update leave type.
     */
    public function updateLeaveType(Request $request, LeaveType $leaveType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'default_quota' => ['required', 'integer', 'min:0'],
            'deduct_quota' => ['boolean'],
            'min_notice_days' => ['required', 'integer', 'min:0'],
            'requires_attachment' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $leaveType->update($validated);
        Cache::forget('master.leave_types');

        return response()->json([
            'success' => true,
            'message' => 'Jenis cuti berhasil diperbarui.',
            'data' => $leaveType,
        ]);
    }

    /**
     * Toggle or delete leave type.
     */
    public function deleteLeaveType(LeaveType $leaveType): JsonResponse
    {
        $leaveType->is_active = ! $leaveType->is_active;
        $leaveType->save();
        Cache::forget('master.leave_types');

        $statusText = $leaveType->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'success' => true,
            'message' => "Jenis cuti {$leaveType->name} berhasil {$statusText}.",
            'data' => $leaveType,
        ]);
    }

    /**
     * Get current leave balances for logged in employee.
     */
    public function balances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $year = (int) $request->input('year', date('Y'));

        if (! $employee) {
            return response()->json([
                'success' => true,
                'year' => $year,
                'data' => [],
                'message' => 'Akun belum terhubung dengan data karyawan.',
            ]);
        }

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
                'success' => true,
                'data' => [],
                'message' => 'Akun belum terhubung dengan data karyawan.',
            ]);
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
