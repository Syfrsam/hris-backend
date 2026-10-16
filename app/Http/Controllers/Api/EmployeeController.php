<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Services\EmployeeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * Display a paginated listing of employees with filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'branch_id', 'department_id', 'employment_status']);
        $perPage = (int) $request->input('per_page', 15);

        $employees = $this->employeeService->getEmployees($filters, $perPage);

        return EmployeeResource::collection($employees);
    }

    /**
     * Store a newly created employee profile and account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'job_position_id' => ['required', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'nik' => ['required', 'string', 'max:30', 'unique:employees,nik'],
            'ktp_number' => ['required', 'string', 'max:20', 'unique:employees,ktp_number'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'in:single,married,widowed'],
            'dependents_count' => ['nullable', 'integer', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['nullable', 'in:permanent,contract,probation,intern'],
            'join_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['nullable', 'in:pkwt,pkwtt,internship'],
            'contract_number' => ['nullable', 'string', 'max:50', 'unique:employment_contracts,contract_number'],
            'contract_end_date' => ['nullable', 'date'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        try {
            $employee = $this->employeeService->createEmployee(
                $validated,
                $request->file('contract_file')
            );

            return response()->json([
                'success' => true,
                'message' => 'Data karyawan berhasil ditambahkan.',
                'data' => new EmployeeResource($employee),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan karyawan: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployeeDetail($id);

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Update the specified employee profile.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_position_id' => ['nullable', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'in:single,married,widowed'],
            'dependents_count' => ['nullable', 'integer', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['nullable', 'in:permanent,contract,probation,intern'],
            'join_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        try {
            $employee = $this->employeeService->updateEmployee(
                $id,
                $validated,
                $request->file('contract_file')
            );

            return response()->json([
                'success' => true,
                'message' => 'Data karyawan berhasil diperbarui.',
                'data' => new EmployeeResource($employee),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui karyawan: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get team members reporting to current authenticated manager.
     */
    public function subordinates(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Profil karyawan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $subordinates = $this->employeeService->getSubordinates($employee->id);

        return EmployeeResource::collection($subordinates);
    }
}
