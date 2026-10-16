<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OvertimeRequestResource;
use App\Repositories\Contracts\OvertimeRepositoryInterface;
use App\Services\OvertimeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    public function __construct(
        protected OvertimeService $overtimeService,
        protected OvertimeRepositoryInterface $overtimeRepository
    ) {}

    /**
     * Display listing of employee's own overtime requests.
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

        $requests = $this->overtimeRepository->getEmployeeOvertimeRequests($employee->id);

        return response()->json([
            'success' => true,
            'data' => OvertimeRequestResource::collection($requests),
        ]);
    }

    /**
     * Submit a new overtime request (Max 4 hours per day).
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
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:4.0'],
            'task_description' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $overtime = $this->overtimeService->applyOvertime($employee->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan lembur berhasil diajukan dan sedang menunggu persetujuan Line Manager.',
                'data' => new OvertimeRequestResource($overtime->load('approvals')),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
