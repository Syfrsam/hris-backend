<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\JobPositionController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayrollController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - HRIS Enterprise Platform
|--------------------------------------------------------------------------
*/

// Public Authentication Route
Route::post('/auth/login', [AuthController::class, 'login']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {

    // User Session & Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Master Data: Branches, Departments & Job Positions (Read for all, Write for HR/Superadmin)
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{department}', [DepartmentController::class, 'show']);
    Route::get('job-positions/options', [JobPositionController::class, 'options']);
    Route::get('job-positions', [JobPositionController::class, 'index']);
    Route::get('job-positions/{job_position}', [JobPositionController::class, 'show']);

    Route::middleware('role:HR Staff|HR Manager|Superadmin')->group(function () {
        Route::post('branches', [BranchController::class, 'store']);
        Route::put('branches/{branch}', [BranchController::class, 'update']);
        Route::delete('branches/{branch}', [BranchController::class, 'destroy']);

        Route::post('departments', [DepartmentController::class, 'store']);
        Route::put('departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);

        Route::post('job-positions', [JobPositionController::class, 'store']);
        Route::put('job-positions/{job_position}', [JobPositionController::class, 'update']);
        Route::delete('job-positions/{job_position}', [JobPositionController::class, 'destroy']);

        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);
        Route::put('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
    });

    // Subordinates list for Line Managers & HR
    Route::middleware('role:Manager|HR Manager|Superadmin')->group(function () {
        Route::get('employees/subordinates', [EmployeeController::class, 'subordinates']);
    });

    // Attendance System (GPS Geolocation & Late Tolerance)
    Route::prefix('attendances')->group(function () {
        Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::get('/my-history', [AttendanceController::class, 'myHistory']);

        Route::middleware('role:HR Staff|HR Manager|Superadmin')->group(function () {
            Route::get('/anomalies', [AttendanceController::class, 'anomalies']);
        });

        Route::middleware('role:Manager|HR Manager|Superadmin')->group(function () {
            Route::get('/team', [AttendanceController::class, 'teamAttendance']);
        });
    });

    // Leave & Time-Off System (Employee Self-Service)
    Route::prefix('leaves')->group(function () {
        Route::get('/types', [LeaveController::class, 'leaveTypes']);
        Route::get('/balances', [LeaveController::class, 'balances']);
        Route::get('/', [LeaveController::class, 'index']);
        Route::post('/', [LeaveController::class, 'store']);
    });

    // Overtime Management (Employee Self-Service)
    Route::prefix('overtimes')->group(function () {
        Route::get('/', [OvertimeController::class, 'index']);
        Route::post('/', [OvertimeController::class, 'store']);
    });

    // Two-Step Approval Workflow (Line Manager ➔ HR Manager)
    Route::middleware('role:Manager|HR Manager|Superadmin')->prefix('approvals')->group(function () {
        Route::get('/leaves', [ApprovalController::class, 'pendingLeaves']);
        Route::post('/leaves/{id}/approve', [ApprovalController::class, 'approveLeave']);
        Route::post('/leaves/{id}/reject', [ApprovalController::class, 'rejectLeave']);

        Route::get('/overtimes', [ApprovalController::class, 'pendingOvertimes']);
        Route::post('/overtimes/{id}/approve', [ApprovalController::class, 'approveOvertime']);
        Route::post('/overtimes/{id}/reject', [ApprovalController::class, 'rejectOvertime']);
    });

    // Payroll Engine & Digital Payslips
    Route::prefix('payrolls')->group(function () {
        // HR Operations (Periods, Draft generation, listing)
        Route::middleware('role:HR Staff|HR Manager|Superadmin')->group(function () {
            Route::get('/periods', [PayrollController::class, 'periods']);
            Route::post('/periods', [PayrollController::class, 'storePeriod']);
            Route::post('/periods/{id}/generate-draft', [PayrollController::class, 'generateDraft'])->whereNumber('id');
            Route::get('/periods/{id}/list', [PayrollController::class, 'periodPayrolls'])->whereNumber('id');
        });

        // HR Manager Final Approval & Authorization
        Route::middleware('role:HR Manager|Superadmin')->group(function () {
            Route::post('/periods/{id}/authorize', [PayrollController::class, 'authorizePeriod'])->whereNumber('id');
        });

        // Employee self-service & payslip access
        Route::get('/my-payslips', [PayrollController::class, 'myPayslips']);
        Route::get('/{id}', [PayrollController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/download', [PayrollController::class, 'downloadPdf'])->whereNumber('id')->name('api.payrolls.download');
    });
});
