<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobGrade;
use App\Models\JobPosition;
use App\Models\LeaveType;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Default Branch (Jakarta HQ)
        Branch::firstOrCreate(
            ['code' => 'JKT-HQ'],
            [
                'name' => 'Jakarta Head Office',
                'address' => 'Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan',
                'latitude' => -6.225014,
                'longitude' => 106.809657,
                'radius_meters' => 100,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ]
        );

        // 2. Default Departments
        $departments = [
            ['code' => 'HRD', 'name' => 'Human Resources Department'],
            ['code' => 'ITD', 'name' => 'Information Technology Department'],
            ['code' => 'FIN', 'name' => 'Finance & Accounting'],
            ['code' => 'OPS', 'name' => 'Operations'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // 3. Default Job Grades
        $grades = [
            ['level' => 1, 'name' => 'Entry / Junior Level', 'min_salary' => 5000000, 'max_salary' => 8000000],
            ['level' => 2, 'name' => 'Mid / Senior Level', 'min_salary' => 8500000, 'max_salary' => 15000000],
            ['level' => 3, 'name' => 'Lead / Supervisor Level', 'min_salary' => 15000000, 'max_salary' => 25000000],
            ['level' => 4, 'name' => 'Managerial Level', 'min_salary' => 25000000, 'max_salary' => 45000000],
        ];

        foreach ($grades as $grade) {
            JobGrade::firstOrCreate(['level' => $grade['level']], $grade);
        }

        // Default Job Positions
        $hrDept = Department::where('code', 'HRD')->first();
        $itDept = Department::where('code', 'ITD')->first();
        $grade1 = JobGrade::where('level', 1)->first();
        $grade2 = JobGrade::where('level', 2)->first();
        $grade4 = JobGrade::where('level', 4)->first();

        if ($hrDept && $grade4) {
            JobPosition::firstOrCreate(
                ['department_id' => $hrDept->id, 'title' => 'HR Manager'],
                ['job_grade_id' => $grade4->id]
            );
        }

        if ($hrDept && $grade2) {
            JobPosition::firstOrCreate(
                ['department_id' => $hrDept->id, 'title' => 'HR Operations Specialist'],
                ['job_grade_id' => $grade2->id]
            );
        }

        if ($itDept && $grade4) {
            JobPosition::firstOrCreate(
                ['department_id' => $itDept->id, 'title' => 'Engineering Manager'],
                ['job_grade_id' => $grade4->id]
            );
        }

        if ($itDept && $grade2) {
            JobPosition::firstOrCreate(
                ['department_id' => $itDept->id, 'title' => 'Senior Software Engineer'],
                ['job_grade_id' => $grade2->id]
            );
        }

        if ($itDept && $grade1) {
            JobPosition::firstOrCreate(
                ['department_id' => $itDept->id, 'title' => 'Software Engineer'],
                ['job_grade_id' => $grade1->id]
            );
        }

        // 4. Default Shift (08:00 - 17:00 with 15 mins late tolerance)
        Shift::firstOrCreate(
            ['name' => 'Regular Office Hour'],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'late_tolerance_minutes' => 15,
                'is_active' => true,
            ]
        );

        // 5. Default Leave Types
        $leaveTypes = [
            [
                'code' => 'ANNUAL',
                'name' => 'Cuti Tahunan',
                'default_quota' => 12,
                'deduct_quota' => true,
                'min_notice_days' => 3, // Wajib H-3
                'requires_attachment' => false,
                'is_active' => true,
            ],
            [
                'code' => 'SICK',
                'name' => 'Cuti Sakit',
                'default_quota' => 0,
                'deduct_quota' => false,
                'min_notice_days' => 0,
                'requires_attachment' => true, // Wajib upload surat dokter
                'is_active' => true,
            ],
            [
                'code' => 'SPECIAL',
                'name' => 'Izin Khusus / Menikah / Berduka',
                'default_quota' => 3,
                'deduct_quota' => false,
                'min_notice_days' => 1,
                'requires_attachment' => false,
                'is_active' => true,
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Cuti di Luar Tanggungan',
                'default_quota' => 0,
                'deduct_quota' => false,
                'min_notice_days' => 7,
                'requires_attachment' => false,
                'is_active' => true,
            ],
        ];

        foreach ($leaveTypes as $leave) {
            LeaveType::firstOrCreate(['code' => $leave['code']], $leave);
        }
    }
}
