<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\User;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepositoryInterface $employeeRepository,
        protected CloudStorageService $cloudStorage
    ) {}

    /**
     * Get paginated employees list with search/filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->employeeRepository->filterEmployees($filters, $perPage);
    }

    /**
     * Get full employee profile detail.
     */
    public function getEmployeeDetail(int $id): Employee
    {
        return $this->employeeRepository->findOrFail($id, ['*'], [
            'user',
            'department',
            'branch',
            'jobPosition.jobGrade',
            'manager.user',
            'subordinates.user',
            'contracts',
            'leaveBalances.leaveType',
        ]);
    }

    /**
     * Create an employee profile along with user account, role, and employment contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEmployee(array $data, ?UploadedFile $contractFile = null): Employee
    {
        return DB::transaction(function () use ($data, $contractFile) {
            // 1. Create User account
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password'] ?? 'password123'),
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // Assign role
            $role = $data['role'] ?? 'Employee';
            $user->assignRole($role);

            // 2. Create Employee profile
            $employeeData = [
                'user_id' => $user->id,
                'manager_id' => $data['manager_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'job_position_id' => $data['job_position_id'],
                'nik' => $data['nik'],
                'ktp_number' => $data['ktp_number'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'],
                'birth_place' => $data['birth_place'] ?? null,
                'marital_status' => $data['marital_status'] ?? 'single',
                'dependents_count' => $data['dependents_count'] ?? 0,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_holder' => $data['bank_account_holder'] ?? null,
                'employment_status' => $data['employment_status'] ?? 'probation',
                'join_date' => $data['join_date'] ?? now()->toDateString(),
            ];

            $employee = $this->employeeRepository->create($employeeData);

            // 3. Create Contract if basic salary is provided
            if (! empty($data['basic_salary'])) {
                $docPath = null;
                if ($contractFile) {
                    $docPath = $this->cloudStorage->upload($contractFile, 'contracts');
                }

                EmploymentContract::create([
                    'employee_id' => $employee->id,
                    'contract_number' => $data['contract_number'] ?? 'CTR/'.$employee->id.'/'.date('Y'),
                    'contract_type' => $data['contract_type'] ?? 'pkwtt',
                    'start_date' => $data['join_date'] ?? now()->toDateString(),
                    'end_date' => $data['contract_end_date'] ?? null,
                    'basic_salary' => $data['basic_salary'],
                    'document_path' => $docPath,
                    'status' => 'active',
                ]);
            }

            return $employee->load(['user', 'department', 'branch', 'jobPosition', 'activeContract']);
        });
    }

    /**
     * Update employee profile and optionally active contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEmployee(int $id, array $data, ?UploadedFile $contractFile = null): Employee
    {
        return DB::transaction(function () use ($id, $data, $contractFile) {
            $employee = $this->employeeRepository->findOrFail($id);

            // Update associated user data
            if (isset($data['name']) || isset($data['email']) || isset($data['phone'])) {
                $userData = array_filter([
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                ]);

                if (! empty($userData)) {
                    $employee->user->update($userData);
                }
            }

            if (! empty($data['role'])) {
                $employee->user->syncRoles([$data['role']]);
            }

            // Update employee fields
            $this->employeeRepository->update($id, $data);

            // Update contract salary or file if provided
            if (! empty($data['basic_salary'])) {
                $activeContract = $employee->activeContract;
                $docPath = $activeContract?->document_path;

                if ($contractFile) {
                    if ($docPath && ! str_starts_with($docPath, 'http') && Storage::disk('public')->exists($docPath)) {
                        Storage::disk('public')->delete($docPath);
                    }
                    $docPath = $this->cloudStorage->upload($contractFile, 'contracts');
                }

                if ($activeContract) {
                    $activeContract->update([
                        'basic_salary' => $data['basic_salary'],
                        'document_path' => $docPath,
                    ]);
                }
            }

            return $employee->fresh(['user', 'department', 'branch', 'jobPosition', 'activeContract']);
        });
    }

    /**
     * Get team members reporting to a line manager.
     */
    public function getSubordinates(int $managerEmployeeId): Collection
    {
        return $this->employeeRepository->getSubordinates($managerEmployeeId);
    }
}
