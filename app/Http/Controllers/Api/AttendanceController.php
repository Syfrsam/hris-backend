<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Services\AttendanceService;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PenaltySetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceRepositoryInterface $attendanceRepository
    ) {}

    /**
     * Daily clock-in with GPS radius validation and 15-min tolerance.
     */
    public function clockIn(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        try {
            $attendance = $this->attendanceService->clockIn(
                $employee->id,
                (float) $request->latitude,
                (float) $request->longitude,
                $request->file('photo')
            );

            return response()->json([
                'success' => true,
                'message' => $attendance->status === 'late'
                    ? "Clock-in berhasil (Status: Terlambat {$attendance->late_minutes} menit)."
                    : 'Clock-in berhasil tepat waktu.',
                'data' => new AttendanceResource($attendance->load(['shift', 'employee.user'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Daily clock-out with work duration calculation.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        try {
            $attendance = $this->attendanceService->clockOut(
                $employee->id,
                (float) $request->latitude,
                (float) $request->longitude,
                $request->file('photo')
            );

            return response()->json([
                'success' => true,
                'message' => 'Clock-out berhasil.',
                'data' => new AttendanceResource($attendance->load(['shift', 'employee.user'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get today's attendance status for logged in employee.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $today = Carbon::today()->toDateString();
        $attendance = $this->attendanceRepository->findTodayAttendance($employee->id, $today);

        return response()->json([
            'success' => true,
            'data' => $attendance ? new AttendanceResource($attendance) : null,
        ]);
    }

    /**
     * Get employee's personal monthly attendance history and summary stats.
     */
    public function myHistory(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan.',
            ], 404);
        }

        $month = $request->input('month');
        if ($month) {
            try {
                $parsed = Carbon::parse($month.'-01');
                $startDate = $parsed->copy()->startOfMonth()->toDateString();
                $endDate = $parsed->copy()->endOfMonth()->toDateString();
            } catch (Exception) {
                $startDate = Carbon::now()->startOfMonth()->toDateString();
                $endDate = Carbon::now()->endOfMonth()->toDateString();
            }
        } else {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        }

        $attendances = $this->attendanceRepository->getMonthlyAttendance($employee->id, $startDate, $endDate);
        $recap = $this->attendanceService->getMonthlyRecapFromCollection($attendances);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $recap,
                'records' => AttendanceResource::collection($attendances),
            ],
        ]);
    }

    /**
     * Get daily attendance anomalies for HR Staff monitoring.
     */
    public function anomalies(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $anomalies = $this->attendanceService->getDailyAnomalies($date);
        $penaltySetting = PenaltySetting::getActive();

        return response()->json([
            'success' => true,
            'date' => $date,
            'penalty_setting' => $penaltySetting,
            'data' => AttendanceResource::collection($anomalies),
        ]);
    }

    /**
     * Get real-time or historical attendance of team members for Manager / HR / Superadmin.
     */
    public function teamAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $manager = $user->employee;

        $isGlobal = $user->hasRole('Superadmin') || $user->hasRole('HR Manager') || $user->hasRole('HR Staff');

        if (! $isGlobal && ! $manager && ! $user->hasRole('Manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya akun manajer atau HR yang dapat melihat presensi tim.',
            ], 403);
        }

        if ($isGlobal) {
            $subordinateIds = Employee::pluck('id')->toArray();
        } elseif ($manager) {
            $subordinateIds = $manager->subordinates()->pluck('id')->toArray();
            if (empty($subordinateIds)) {
                if ($manager->department_id) {
                    $subordinateIds = Employee::where('department_id', $manager->department_id)->pluck('id')->toArray();
                }
                if (empty($subordinateIds)) {
                    $subordinateIds = Employee::pluck('id')->toArray();
                }
            }
        } else {
            $subordinateIds = Employee::pluck('id')->toArray();
        }

        $query = Attendance::with(['employee.user', 'employee.department', 'employee.jobPosition', 'shift'])
            ->whereIn('employee_id', $subordinateIds);

        // Filter by month (e.g. 2026-10), start_date & end_date, or single date
        if ($request->filled('month')) {
            try {
                $monthDate = Carbon::parse($request->input('month').'-01');
                $start = $monthDate->copy()->startOfMonth()->toDateString();
                $end = $monthDate->copy()->endOfMonth()->toDateString();
                $query->whereBetween('date', [$start, $end]);
            } catch (Exception) {
                // ignore
            }
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->input('start_date'), $request->input('end_date')]);
        } elseif ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $attendances = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => AttendanceResource::collection($attendances),
        ]);
    }
}
