<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class AttendanceService
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository
    ) {}

    /**
     * Calculate geodesic distance between two GPS coordinates using Haversine Formula (in meters).
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Record employee clock-in with GPS radius validation and late tolerance calculation.
     *
     * @throws Exception
     */
    public function clockIn(int $employeeId, float $latitude, float $longitude, ?UploadedFile $photo = null): Attendance
    {
        $employee = Employee::with('branch')->findOrFail($employeeId);
        $branch = $employee->branch;

        if (! $branch || ! $branch->is_active) {
            throw new Exception('Data cabang kantor tidak ditemukan atau non-aktif.');
        }

        // 1. Geolocation validation (Max 100 meters by default)
        $distance = $this->calculateDistance($latitude, $longitude, (float) $branch->latitude, (float) $branch->longitude);
        $maxRadius = (float) $branch->radius_meters;

        if ($distance > $maxRadius) {
            throw new Exception("Clock-in gagal. Anda berada di luar radius kantor ({$distance} meter dari kantor, batas maksimal: {$maxRadius} meter).");
        }

        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // 2. Prevent duplicate clock-in
        $existing = $this->attendanceRepository->findTodayAttendance($employeeId, $today);
        if ($existing && $existing->clock_in_time) {
            throw new Exception('Anda sudah melakukan clock-in untuk hari ini.');
        }

        // 3. Resolve active shift (Default or branch regular shift)
        $shift = Shift::where('branch_id', $branch->id)->where('is_active', true)->first()
            ?? Shift::whereNull('branch_id')->where('is_active', true)->first()
            ?? Shift::first();

        if (! $shift) {
            throw new Exception('Tidak ada jadwal shift aktif yang terdaftar.');
        }

        // 4. Calculate late status based on 15 minutes tolerance
        $shiftStartTime = Carbon::parse($today.' '.$shift->start_time);
        $toleranceEndTime = $shiftStartTime->copy()->addMinutes($shift->late_tolerance_minutes);

        $status = 'on_time';
        $lateMinutes = 0;

        if ($now->greaterThan($toleranceEndTime)) {
            $status = 'late';
            // Late minutes calculated from official start time
            $lateMinutes = (int) $shiftStartTime->diffInMinutes($now);
        }

        // 5. Store selfie photo
        $photoPath = null;
        if ($photo) {
            $photoPath = $photo->store('attendances/clock_in', 'public');
        }

        return $this->attendanceRepository->create([
            'employee_id' => $employeeId,
            'shift_id' => $shift->id,
            'date' => $today,
            'clock_in_time' => $now,
            'clock_in_latitude' => $latitude,
            'clock_in_longitude' => $longitude,
            'clock_in_distance_meters' => $distance,
            'clock_in_photo_path' => $photoPath,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);
    }

    /**
     * Record employee clock-out with duration calculation.
     *
     * @throws Exception
     */
    public function clockOut(int $employeeId, float $latitude, float $longitude, ?UploadedFile $photo = null): Attendance
    {
        $employee = Employee::with('branch')->findOrFail($employeeId);
        $branch = $employee->branch;

        $distance = $this->calculateDistance($latitude, $longitude, (float) $branch->latitude, (float) $branch->longitude);
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $attendance = $this->attendanceRepository->findTodayAttendance($employeeId, $today);

        if (! $attendance || ! $attendance->clock_in_time) {
            throw new Exception('Anda belum melakukan clock-in hari ini.');
        }

        if ($attendance->clock_out_time) {
            throw new Exception('Anda sudah melakukan clock-out untuk hari ini.');
        }

        $clockInTime = Carbon::parse($attendance->clock_in_time);
        $workDurationMinutes = (int) $clockInTime->diffInMinutes($now);

        // Early leave check
        $shift = $attendance->shift ?? Shift::find($attendance->shift_id);
        $earlyLeaveMinutes = 0;
        if ($shift) {
            $shiftEndTime = Carbon::parse($today.' '.$shift->end_time);
            if ($now->lessThan($shiftEndTime)) {
                $earlyLeaveMinutes = (int) $now->diffInMinutes($shiftEndTime);
            }
        }

        $photoPath = null;
        if ($photo) {
            $photoPath = $photo->store('attendances/clock_out', 'public');
        }

        $attendance->update([
            'clock_out_time' => $now,
            'clock_out_latitude' => $latitude,
            'clock_out_longitude' => $longitude,
            'clock_out_distance_meters' => $distance,
            'clock_out_photo_path' => $photoPath,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'work_duration_minutes' => $workDurationMinutes,
        ]);

        return $attendance->fresh();
    }

    /**
     * Get monthly attendance recapitulation for payroll calculations.
     *
     * @return array{total_days: int, on_time_days: int, late_days: int, total_late_minutes: int, absent_days: int}
     */
    public function getMonthlyRecap(int $employeeId, string $startDate, string $endDate): array
    {
        $attendances = $this->attendanceRepository->getMonthlyAttendance($employeeId, $startDate, $endDate);

        $lateDays = $attendances->where('status', 'late')->count();
        $totalLateMinutes = (int) $attendances->sum('late_minutes');
        $onTimeDays = $attendances->where('status', 'on_time')->count();
        $absentDays = $attendances->where('status', 'absent')->count();

        return [
            'total_days' => $attendances->count(),
            'on_time_days' => $onTimeDays,
            'late_days' => $lateDays,
            'total_late_minutes' => $totalLateMinutes,
            'absent_days' => $absentDays,
        ];
    }

    /**
     * Get attendance anomalies for daily HR Staff audit.
     */
    public function getDailyAnomalies(string $date): Collection
    {
        return $this->attendanceRepository->getAttendanceAnomalies($date);
    }
}
