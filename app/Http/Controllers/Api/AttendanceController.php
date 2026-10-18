<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Services\AttendanceService;
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
            'data' => $attendance ? new AttendanceResource($attendance->load(['shift', 'employee.user'])) : null,
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

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

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

        return response()->json([
            'success' => true,
            'date' => $date,
            'data' => AttendanceResource::collection($anomalies),
        ]);
    }

    /**
     * Get real-time attendance of team members for Manager dashboard.
     */
    public function teamAttendance(Request $request): JsonResponse
    {
        $manager = $request->user()->employee;
        if (! $manager) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya akun manajer yang dapat melihat absensi tim.',
            ], 403);
        }

        $subordinateIds = $manager->subordinates()->pluck('id')->toArray();
        $date = $request->input('date', Carbon::today()->toDateString());

        $attendances = $this->attendanceRepository->getTeamAttendance($subordinateIds, $date);

        return response()->json([
            'success' => true,
            'date' => $date,
            'data' => AttendanceResource::collection($attendances),
        ]);
    }
}
