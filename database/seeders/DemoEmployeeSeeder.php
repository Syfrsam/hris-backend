<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\JobPosition;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::where('code', 'JKT-HQ')->first();
        $hrDept = Department::where('code', 'HRD')->first();
        $itDept = Department::where('code', 'ITD')->first();
        $annualLeave = LeaveType::where('code', 'ANNUAL')->first();

        $hrManagerPos = JobPosition::where('title', 'HR Manager')->first();
        $hrStaffPos = JobPosition::where('title', 'HR Operations Specialist')->first();
        $engManagerPos = JobPosition::where('title', 'Engineering Manager')->first();
        $softwareEngPos = JobPosition::where('title', 'Software Engineer')->first();

        // 1. Akun HR Manager
        $hrUser = User::firstOrCreate(
            ['email' => 'hrmanager@hris.local'],
            [
                'name' => 'Dewi Lestari, S.Psi',
                'password' => bcrypt('password'),
                'phone' => '081234567891',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $hrUser->assignRole('HR Manager');

        $hrEmp = Employee::firstOrCreate(
            ['user_id' => $hrUser->id],
            [
                'nik' => 'EMP-2026-001',
                'ktp_number' => '3171012345670001',
                'branch_id' => $branch->id,
                'department_id' => $hrDept->id,
                'job_position_id' => $hrManagerPos->id,
                'gender' => 'female',
                'birth_date' => '1990-05-15',
                'birth_place' => 'Jakarta',
                'marital_status' => 'married',
                'dependents_count' => 1,
                'bank_name' => 'BCA',
                'bank_account_number' => '5420123456',
                'bank_account_holder' => 'Dewi Lestari',
                'employment_status' => 'permanent',
                'join_date' => '2023-01-01',
            ]
        );

        EmploymentContract::firstOrCreate(
            ['employee_id' => $hrEmp->id, 'contract_number' => 'CTR/HRD/2023/001'],
            [
                'contract_type' => 'pkwtt',
                'start_date' => '2023-01-01',
                'basic_salary' => 28000000,
                'status' => 'active',
            ]
        );

        // 2. Akun Line Manager (Engineering Manager)
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@hris.local'],
            [
                'name' => 'Budi Pratama, S.Kom',
                'password' => bcrypt('password'),
                'phone' => '081234567892',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $managerUser->assignRole('Manager');

        $managerEmp = Employee::firstOrCreate(
            ['user_id' => $managerUser->id],
            [
                'nik' => 'EMP-2026-002',
                'ktp_number' => '3171012345670002',
                'branch_id' => $branch->id,
                'department_id' => $itDept->id,
                'job_position_id' => $engManagerPos->id,
                'gender' => 'male',
                'birth_date' => '1988-08-20',
                'birth_place' => 'Bandung',
                'marital_status' => 'married',
                'dependents_count' => 2,
                'bank_name' => 'Mandiri',
                'bank_account_number' => '1370012345678',
                'bank_account_holder' => 'Budi Pratama',
                'employment_status' => 'permanent',
                'join_date' => '2022-06-01',
            ]
        );

        EmploymentContract::firstOrCreate(
            ['employee_id' => $managerEmp->id, 'contract_number' => 'CTR/ITD/2022/002'],
            [
                'contract_type' => 'pkwtt',
                'start_date' => '2022-06-01',
                'basic_salary' => 30000000,
                'status' => 'active',
            ]
        );

        // 3. Akun Employee (Bawahan langsung dari Budi Pratama)
        $employeeUser = User::firstOrCreate(
            ['email' => 'employee@hris.local'],
            [
                'name' => 'Siti Nurhaliza',
                'password' => bcrypt('password'),
                'phone' => '081234567893',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $employeeUser->assignRole('Employee');

        $staffEmp = Employee::firstOrCreate(
            ['user_id' => $employeeUser->id],
            [
                'nik' => 'EMP-2026-003',
                'ktp_number' => '3171012345670003',
                'manager_id' => $managerEmp->id, // Melapor ke Budi Pratama (Line Manager)
                'branch_id' => $branch->id,
                'department_id' => $itDept->id,
                'job_position_id' => $softwareEngPos->id,
                'gender' => 'female',
                'birth_date' => '1998-11-10',
                'birth_place' => 'Surabaya',
                'marital_status' => 'single',
                'dependents_count' => 0,
                'bank_name' => 'BCA',
                'bank_account_number' => '5420987654',
                'bank_account_holder' => 'Siti Nurhaliza',
                'employment_status' => 'permanent',
                'join_date' => '2024-03-01',
            ]
        );

        EmploymentContract::firstOrCreate(
            ['employee_id' => $staffEmp->id, 'contract_number' => 'CTR/ITD/2024/003'],
            [
                'contract_type' => 'pkwtt',
                'start_date' => '2024-03-01',
                'basic_salary' => 9500000,
                'status' => 'active',
            ]
        );

        // 4. Akun HR Staff (Operations & Payroll Draft generator)
        $hrStaffUser = User::firstOrCreate(
            ['email' => 'hrstaff@hris.local'],
            [
                'name' => 'Rian Hidayat',
                'password' => bcrypt('password'),
                'phone' => '081234567894',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $hrStaffUser->assignRole('HR Staff');

        $hrStaffEmp = Employee::firstOrCreate(
            ['user_id' => $hrStaffUser->id],
            [
                'nik' => 'EMP-2026-004',
                'ktp_number' => '3171012345670004',
                'manager_id' => $hrEmp->id, // Melapor ke Dewi Lestari (HR Manager)
                'branch_id' => $branch->id,
                'department_id' => $hrDept->id,
                'job_position_id' => $hrStaffPos->id,
                'gender' => 'male',
                'birth_date' => '1995-04-12',
                'birth_place' => 'Semarang',
                'marital_status' => 'single',
                'dependents_count' => 0,
                'bank_name' => 'BNI',
                'bank_account_number' => '0245678901',
                'bank_account_holder' => 'Rian Hidayat',
                'employment_status' => 'permanent',
                'join_date' => '2023-09-01',
            ]
        );

        EmploymentContract::firstOrCreate(
            ['employee_id' => $hrStaffEmp->id, 'contract_number' => 'CTR/HRD/2023/004'],
            [
                'contract_type' => 'pkwtt',
                'start_date' => '2023-09-01',
                'basic_salary' => 8500000,
                'status' => 'active',
            ]
        );

        // Alokasikan Kuota Cuti Tahunan 12 Hari untuk semua karyawan demo
        if ($annualLeave) {
            foreach ([$hrEmp, $managerEmp, $staffEmp, $hrStaffEmp] as $emp) {
                LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $emp->id,
                        'leave_type_id' => $annualLeave->id,
                        'year' => (int) date('Y'),
                    ],
                    [
                        'total_quota' => 12,
                        'used_quota' => 0,
                        'remaining_quota' => 12,
                        'valid_until' => date('Y').'-12-31',
                    ]
                );
            }
        }
    }
}
