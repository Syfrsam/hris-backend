<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

require_once __DIR__ . '/MonthlyAttendanceSeeder.php';

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            MasterDataSeeder::class,
            DemoEmployeeSeeder::class,
            MonthlyAttendanceSeeder::class,
        ]);
    }
}
