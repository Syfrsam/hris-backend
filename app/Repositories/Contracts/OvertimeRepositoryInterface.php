<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface OvertimeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get overtime requests for a specific employee.
     */
    public function getEmployeeOvertimeRequests(int $employeeId): Collection;

    /**
     * Get overtime requests waiting for Line Manager review.
     */
    public function getPendingForManager(int $managerEmployeeId): Collection;

    /**
     * Get overtime requests waiting for HR Manager review.
     */
    public function getPendingForHr(): Collection;

    /**
     * Get approved overtime requests within a cut-off date range.
     */
    public function getApprovedOvertimeBetween(int $employeeId, string $startDate, string $endDate): Collection;
}
