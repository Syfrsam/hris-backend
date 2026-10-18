<?php

namespace App\Repositories\Eloquent;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Repositories\Contracts\LeaveRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LeaveRepository extends BaseRepository implements LeaveRepositoryInterface
{
    public function __construct(LeaveRequest $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmployeeLeaveRequests(int $employeeId): Collection
    {
        return $this->model
            ->with(['leaveType', 'approvals.reviewer', 'employee.user', 'employee.department'])
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
            ->with(['employee.user', 'leaveType'])
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
            ->with(['employee.user', 'employee.department', 'leaveType', 'approvals.reviewer'])
            ->where('status', 'pending')
            ->where('current_step', 'hr')
            ->latest()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }
}
