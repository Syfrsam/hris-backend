<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrisWorkflowAndRoleTest extends TestCase
{
    /**
     * Test Role-Based Access Control (RBAC): Employee cannot perform HR admin actions.
     */
    public function test_employee_is_forbidden_from_hr_routes(): void
    {
        $employeeUser = User::where('email', 'employee@hris.local')->first();

        // 1. Employee trying to create department
        $response = $this->actingAs($employeeUser, 'sanctum')
            ->postJson('/api/departments', [
                'name' => 'Unauthorized Department',
                'code' => 'UNAUTH',
            ]);
        $response->assertStatus(403);

        // 2. Employee trying to access payroll periods
        $response = $this->actingAs($employeeUser, 'sanctum')
            ->getJson('/api/payrolls/periods');
        $response->assertStatus(403);
    }

    /**
     * Test HR Staff cannot authorize payroll (only HR Manager / Superadmin).
     */
    public function test_hr_staff_cannot_authorize_payroll(): void
    {
        $hrStaffUser = User::where('email', 'hrstaff@hris.local')->first();

        $response = $this->actingAs($hrStaffUser, 'sanctum')
            ->postJson('/api/payrolls/periods/999/authorize');

        $response->assertStatus(403);
    }

    /**
     * Test Clock-in GPS Geolocation radius validation.
     */
    public function test_attendance_gps_radius_validation(): void
    {
        $employeeUser = User::where('email', 'employee@hris.local')->first();
        $hq = Branch::where('code', 'JKT-HQ')->first();

        // Test Out of Radius clock-in (> 100m, e.g. Monas coordinates)
        $outResponse = $this->actingAs($employeeUser, 'sanctum')
            ->postJson('/api/attendances/clock-in', [
                'latitude' => -6.175392,
                'longitude' => 106.827153,
            ]);

        $outResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test Complete Leave Request & Two-Step Approval Workflow (Manager -> HR).
     */
    public function test_leave_submission_and_two_step_approval_flow(): void
    {
        $employeeUser = User::where('email', 'employee@hris.local')->first();
        $managerUser = User::where('email', 'manager@hris.local')->first();
        $hrManagerUser = User::where('email', 'hrmanager@hris.local')->first();
        $annualLeave = LeaveType::where('code', 'ANNUAL')->first();

        // Set start date H+5 to satisfy H-3 notice rule
        $startDate = Carbon::now()->addDays(5)->format('Y-m-d');
        $endDate = Carbon::now()->addDays(6)->format('Y-m-d');

        // 1. Employee submits leave request
        $submitResponse = $this->actingAs($employeeUser, 'sanctum')
            ->postJson('/api/leaves', [
                'leave_type_id' => $annualLeave->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Family vacation and personal matters',
            ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $leaveId = $submitResponse->json('data.id');

        // 2. Line Manager approves Stage 1
        $managerApproveResponse = $this->actingAs($managerUser, 'sanctum')
            ->postJson("/api/approvals/leaves/{$leaveId}/approve", [
                'notes' => 'Approved by line manager, good to go.',
            ]);

        $managerApproveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // 3. HR Manager approves Stage 2 (final approval)
        $hrApproveResponse = $this->actingAs($hrManagerUser, 'sanctum')
            ->postJson("/api/approvals/leaves/{$leaveId}/approve", [
                'notes' => 'Final approval granted by HR.',
            ]);

        $hrApproveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        // Verify leave request record status
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveId,
            'status' => 'approved',
        ]);
    }

    /**
     * Test Overtime Submission and Manager Approval.
     */
    public function test_overtime_submission_and_approval(): void
    {
        $employeeUser = User::where('email', 'employee@hris.local')->first();
        $managerUser = User::where('email', 'manager@hris.local')->first();

        $otDate = Carbon::now()->addDays(1)->format('Y-m-d');

        // 1. Employee applies for 2.5 hours overtime
        $response = $this->actingAs($employeeUser, 'sanctum')
            ->postJson('/api/overtimes', [
                'date' => $otDate,
                'start_time' => '17:00',
                'end_time' => '19:30',
                'duration_hours' => 2.5,
                'task_description' => 'Server migration and database backup maintenance',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duration_hours', 2.5);

        $otId = $response->json('data.id');

        // 2. Line Manager approves overtime (Stage 1)
        $managerApproveResponse = $this->actingAs($managerUser, 'sanctum')
            ->postJson("/api/approvals/overtimes/{$otId}/approve", [
                'notes' => 'Critical maintenance approved by manager.',
            ]);

        $managerApproveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // 3. HR Manager approves overtime (Stage 2)
        $hrManagerUser = User::where('email', 'hrmanager@hris.local')->first();
        $hrApproveResponse = $this->actingAs($hrManagerUser, 'sanctum')
            ->postJson("/api/approvals/overtimes/{$otId}/approve", [
                'notes' => 'Final overtime approved by HR.',
            ]);

        $hrApproveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }

    /**
     * Test Payslip PDF generation response.
     */
    public function test_payslip_pdf_download(): void
    {
        $employeeUser = User::where('email', 'employee@hris.local')->first();
        $employee = $employeeUser->employee;

        // Create dummy period & payslip
        $period = PayrollPeriod::create([
            'name' => 'Test Period For PDF',
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'cutoff_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            'payment_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            'status' => 'approved',
        ]);

        $payroll = Payroll::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'payslip_number' => 'SLIP-TEST-'.time(),
            'basic_salary' => 8000000,
            'total_allowances' => 1500000,
            'total_overtime_pay' => 250000,
            'total_deductions' => 250000,
            'late_deduction_amount' => 0,
            'tax_pph21' => 150000,
            'bpjs_kesehatan' => 50000,
            'bpjs_ketenagakerjaan' => 50000,
            'net_salary' => 9500000,
            'status' => 'published',
        ]);

        $response = $this->actingAs($employeeUser, 'sanctum')
            ->getJson("/api/payrolls/{$payroll->id}/download");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type', ''), 'application/pdf'));
    }

    /**
     * Test HR Staff can create an employee with legal contract document upload.
     */
    public function test_hr_can_create_employee_with_contract_document(): void
    {
        Storage::fake('public');

        $hrStaffUser = User::where('email', 'hrstaff@hris.local')->first();
        $branch = Branch::where('code', 'JKT-HQ')->first();
        $dept = Department::first();
        $pos = JobPosition::first();

        $contractFile = UploadedFile::fake()->create('contract_agreement.pdf', 250, 'application/pdf');

        $payload = [
            'name' => 'Ahmad Fauzi Test',
            'email' => 'ahmad.fauzi.'.time().'@hris.local',
            'password' => 'password123',
            'phone' => '081234567890',
            'role' => 'Employee',
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'job_position_id' => $pos->id,
            'nik' => 'EMP-TEST-'.time(),
            'ktp_number' => '317101'.rand(1000000000, 9999999999),
            'gender' => 'male',
            'birth_date' => '1995-05-12',
            'birth_place' => 'Jakarta',
            'marital_status' => 'single',
            'dependents_count' => 0,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Ahmad Fauzi',
            'employment_status' => 'contract',
            'join_date' => '2026-10-01',
            'basic_salary' => 8500000,
            'contract_type' => 'pkwt',
            'contract_number' => 'PKWT-'.time(),
            'contract_end_date' => '2027-09-30',
            'contract_file' => $contractFile,
        ];

        $response = $this->actingAs($hrStaffUser, 'sanctum')
            ->post('/api/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Ahmad Fauzi Test');

        $this->assertDatabaseHas('employees', [
            'nik' => $payload['nik'],
        ]);
    }
}
