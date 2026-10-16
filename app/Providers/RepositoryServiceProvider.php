<?php

namespace App\Providers;

use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Contracts\LeaveRepositoryInterface;
use App\Repositories\Contracts\OvertimeRepositoryInterface;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use App\Repositories\Eloquent\AttendanceRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Eloquent\LeaveRepository;
use App\Repositories\Eloquent\OvertimeRepository;
use App\Repositories\Eloquent\PayrollRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(LeaveRepositoryInterface::class, LeaveRepository::class);
        $this->app->bind(OvertimeRepositoryInterface::class, OvertimeRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
