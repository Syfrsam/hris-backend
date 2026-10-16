<?php

namespace App\Repositories\Eloquent;

use App\Models\Attendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    public function __construct(Attendance $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findTodayAttendance(int $employeeId, string $date): ?Attendance
    {
        return $this->model
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getMonthlyAttendance(int $employeeId, string $startDate, string $endDate): Collection
    {
        return $this->model
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttendanceAnomalies(string $date): Collection
    {
        return $this->model
            ->with(['employee.user', 'employee.department', 'shift'])
            ->where('date', $date)
            ->where(function ($q) {
                $q->where('status', 'late')
                    ->orWhere('status', 'early_leave')
                    ->orWhere('status', 'absent');
            })
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getTeamAttendance(array $subordinateEmployeeIds, string $date): Collection
    {
        return $this->model
            ->with(['employee.user', 'shift'])
            ->whereIn('employee_id', $subordinateEmployeeIds)
            ->where('date', $date)
            ->get();
    }
}
