<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    public function __construct(Employee $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findByNik(string $nik): ?Employee
    {
        return $this->model
            ->with(['user', 'department', 'branch', 'jobPosition', 'manager', 'activeContract'])
            ->where('nik', $nik)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByUserId(int $userId): ?Employee
    {
        return $this->model
            ->with(['user', 'department', 'branch', 'jobPosition', 'manager', 'activeContract'])
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getSubordinates(int $managerId): Collection
    {
        return $this->model
            ->with(['user', 'department', 'jobPosition', 'activeContract'])
            ->where('manager_id', $managerId)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function filterEmployees(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['user', 'department', 'branch', 'jobPosition', 'manager', 'activeContract']);

        // Apply fast indexed equality filters first
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        // Apply search with indexed fields
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nik', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest('id')->paginate($perPage);
    }
}
