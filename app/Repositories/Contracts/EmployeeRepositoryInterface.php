<?php

namespace App\Repositories\Contracts;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find employee by NIK.
     */
    public function findByNik(string $nik): ?Employee;

    /**
     * Find employee by associated user ID.
     */
    public function findByUserId(int $userId): ?Employee;

    /**
     * Get direct subordinates for a manager.
     */
    public function getSubordinates(int $managerId): Collection;

    /**
     * Filter employees with search, department, branch, and status.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filterEmployees(array $filters, int $perPage = 15): LengthAwarePaginator;
}
