<?php

namespace Tests\Feature;

use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Contracts\LeaveRepositoryInterface;
use App\Repositories\Contracts\OvertimeRepositoryInterface;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use App\Services\AttendanceService;
use App\Services\OvertimeService;
use Tests\TestCase;

class HrisBusinessLogicTest extends TestCase
{
    /**
     * Test that all repository interfaces resolve correctly from service container.
     */
    public function test_repository_interfaces_resolve_from_container(): void
    {
        $this->assertInstanceOf(EmployeeRepositoryInterface::class, app(EmployeeRepositoryInterface::class));
        $this->assertInstanceOf(AttendanceRepositoryInterface::class, app(AttendanceRepositoryInterface::class));
        $this->assertInstanceOf(LeaveRepositoryInterface::class, app(LeaveRepositoryInterface::class));
        $this->assertInstanceOf(OvertimeRepositoryInterface::class, app(OvertimeRepositoryInterface::class));
        $this->assertInstanceOf(PayrollRepositoryInterface::class, app(PayrollRepositoryInterface::class));
    }

    /**
     * Test Haversine GPS distance calculation.
     */
    public function test_haversine_gps_distance_calculation(): void
    {
        $attendanceService = app(AttendanceService::class);

        // Koordinat Monas ke Balai Kota Jakarta (~500 - 800 meter)
        $distance = $attendanceService->calculateDistance(
            -6.175392, 106.827153, // Monas
            -6.175392, 106.827153  // Titik yang sama
        );

        $this->assertEquals(0.0, $distance);

        // Titik berjarak ~50 meter
        $distance50m = $attendanceService->calculateDistance(
            -6.225014, 106.809657,
            -6.225400, 106.809657
        );

        $this->assertGreaterThan(30.0, $distance50m);
        $this->assertLessThan(60.0, $distance50m);
    }

    /**
     * Test Depnaker overtime pay calculation formula.
     * Hourly rate = 1 / 173 * basic salary.
     * 1st hour = 1.5 * hourly rate.
     * 2nd hour = 2.0 * hourly rate.
     */
    public function test_depnaker_overtime_pay_calculation(): void
    {
        $overtimeService = app(OvertimeService::class);
        $basicSalary = 17300000; // Hourly rate = 100,000 IDR

        // 1 jam lembur = 1.5 * 100,000 = 150,000
        $pay1Hour = $overtimeService->calculateDepnakerOvertimePay($basicSalary, 1.0);
        $this->assertEquals(150000.0, $pay1Hour);

        // 2 jam lembur = 150,000 + (1.0 * 2.0 * 100,000) = 350,000
        $pay2Hours = $overtimeService->calculateDepnakerOvertimePay($basicSalary, 2.0);
        $this->assertEquals(350000.0, $pay2Hours);

        // 4 jam lembur (maksimal) = 150,000 + (3.0 * 2.0 * 100,000) = 750,000
        $pay4Hours = $overtimeService->calculateDepnakerOvertimePay($basicSalary, 4.0);
        $this->assertEquals(750000.0, $pay4Hours);
    }
}
