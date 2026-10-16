<?php

namespace App\Repositories\Contracts;

use App\Models\Payroll;
use Illuminate\Database\Eloquent\Collection;

interface PayrollRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get all payroll periods.
     */
    public function getPeriods(): Collection;

    /**
     * Get all payroll records for a period.
     */
    public function getPayrollsForPeriod(int $periodId): Collection;

    /**
     * Get published payslips for an employee.
     */
    public function getEmployeePayslips(int $employeeId): Collection;

    /**
     * Get single payslip detail with all breakdown items.
     */
    public function getPayslipDetail(int $payrollId): ?Payroll;
}
