<?php

namespace App\Repositories\Contracts;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find today's attendance record for an employee.
     */
    public function findTodayAttendance(int $employeeId, string $date): ?Attendance;

    /**
     * Get attendance records within a date range (for cut-off period).
     */
    public function getMonthlyAttendance(int $employeeId, string $startDate, string $endDate): Collection;

    /**
     * Get attendance anomalies for HR Staff audit (late, unattended).
     */
    public function getAttendanceAnomalies(string $date): Collection;

    /**
     * Get team attendance summary for a manager on a specific date.
     *
     * @param  array<int, int>  $subordinateEmployeeIds
     */
    public function getTeamAttendance(array $subordinateEmployeeIds, string $date): Collection;
}
