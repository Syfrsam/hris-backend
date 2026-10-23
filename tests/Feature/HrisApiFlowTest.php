<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HrisApiFlowTest extends TestCase
{
    /**
     * Test login API and fetch authenticated profile.
     */
    public function test_auth_login_and_me_endpoints(): void
    {
        // 1. Login with demo employee account
        $response = $this->postJson('/api/auth/login', [
            'email' => 'employee@hris.local',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                    ],
                ],
            ]);

        $token = $response->json('data.token');

        // 2. Fetch /api/auth/me using token
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'employee@hris.local');
    }

    /**
     * Test master data endpoints (departments, positions).
     */
    public function test_master_data_endpoints(): void
    {
        $user = User::where('email', 'superadmin@hris.local')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/departments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $posResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/job-positions');

        $posResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test leave balances and types endpoints.
     */
    public function test_leave_types_and_balances_endpoints(): void
    {
        $user = User::where('email', 'employee@hris.local')->first();

        $typesResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/leaves/types');

        $typesResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $balanceResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/leaves/balances');

        $balanceResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test attendance today endpoint.
     */
    public function test_attendance_today_endpoint(): void
    {
        $user = User::where('email', 'employee@hris.local')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/attendances/today');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test payroll periods endpoint.
     */
    public function test_payroll_periods_endpoint(): void
    {
        $user = User::where('email', 'hrmanager@hris.local')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/payrolls/periods');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test Superadmin exclusive user & role management endpoints.
     */
    public function test_superadmin_user_role_management_and_rbac(): void
    {
        $superadmin = User::where('email', 'superadmin@hris.local')->first();
        $hrManager = User::where('email', 'hrmanager@hris.local')->first();
        $employee = User::where('email', 'employee@hris.local')->first();

        // Non-Superadmin must be forbidden (403)
        $this->actingAs($hrManager, 'sanctum')
            ->getJson('/api/users')
            ->assertStatus(403);

        // Superadmin can list all users and roles
        $listResponse = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/users');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'users',
                    'available_roles',
                ],
            ]);

        // Superadmin cannot deactivate or strip Superadmin role from own account
        $this->actingAs($superadmin, 'sanctum')
            ->putJson('/api/users/'.$superadmin->id, [
                'role' => 'Employee',
            ])
            ->assertStatus(422);

        // Superadmin can update another user's role and restore it
        $updateResponse = $this->actingAs($superadmin, 'sanctum')
            ->putJson('/api/users/'.$employee->id, [
                'role' => 'HR Staff',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.primary_role', 'HR Staff');

        // Restore original role so database state stays clean
        $this->actingAs($superadmin, 'sanctum')
            ->putJson('/api/users/'.$employee->id, [
                'role' => 'Employee',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.primary_role', 'Employee');
    }
}
