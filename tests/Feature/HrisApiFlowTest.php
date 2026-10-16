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
}
