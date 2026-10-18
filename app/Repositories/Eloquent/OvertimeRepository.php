<?php

namespace App\Repositories\Eloquent;

use App\Models\OvertimeRequest;
use App\Repositories\Contracts\OvertimeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OvertimeRepository extends BaseRepository implements OvertimeRepositoryInterface
{
    public function __construct(OvertimeRequest $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmployeeOvertimeRequests(int $employeeId): Collection
    {
        return $this->model
            ->with(['approvals.reviewer', 'employee.user', 'employee.department'])
            ->where('employee_id', $employeeId)
            ->latest()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingForManager(int $managerEmployeeId): Collection
    {
        return $this->model
            ->with(['employee.user'])
            ->where('status', 'pending')
            ->where('current_step', 'manager')
            ->whereHas('employee', function ($q) use ($managerEmployeeId) {
                $q->where('manager_id', $managerEmployeeId);
            })
            ->latest()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingForHr(): Collection
    {
        return $this->model
            ->with(['employee.user', 'employee.department', 'approvals.reviewer'])
            ->where('status', 'pending')
            ->where('current_step', 'hr')
            ->latest()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getApprovedOvertimeBetween(int $employeeId, string $startDate, string $endDate): Collection
    {
        return $this->model
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }
}
