<?php

namespace App\Repositories\Contracts;

use App\Models\LeaveBalance;
use Illuminate\Database\Eloquent\Collection;

interface LeaveRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get leave requests for a specific employee.
     */
    public function getEmployeeLeaveRequests(int $employeeId): Collection;

    /**
     * Get leave requests waiting for Line Manager review.
     */
    public function getPendingForManager(int $managerEmployeeId): Collection;

    /**
     * Get leave requests waiting for HR Manager review.
     */
    public function getPendingForHr(): Collection;

    /**
     * Get annual leave balance for employee and year.
     */
    public function getBalance(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance;
}
