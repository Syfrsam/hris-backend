<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define the 5 core roles
        $roles = [
            'Superadmin',
            'HR Manager',
            'HR Staff',
            'Manager',
            'Employee',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create default Superadmin account
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@hris.local'],
            [
                'name' => 'Super Administrator',
                'password' => bcrypt('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $superadmin->assignRole('Superadmin');
    }
}
