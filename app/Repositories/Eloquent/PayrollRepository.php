<?php

namespace App\Repositories\Eloquent;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PayrollRepository extends BaseRepository implements PayrollRepositoryInterface
{
    public function __construct(Payroll $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getPeriods(): Collection
    {
        return PayrollPeriod::with(['generator', 'approver'])
            ->latest('start_date')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPayrollsForPeriod(int $periodId): Collection
    {
        return $this->model
            ->with(['employee.user', 'employee.department', 'employee.jobPosition', 'items'])
            ->where('payroll_period_id', $periodId)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getEmployeePayslips(int $employeeId): Collection
    {
        return $this->model
            ->with(['payrollPeriod', 'items'])
            ->where('employee_id', $employeeId)
            ->where('status', 'published')
            ->latest()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPayslipDetail(int $payrollId): ?Payroll
    {
        return $this->model
            ->with([
                'payrollPeriod',
                'employee.user',
                'employee.department',
                'employee.jobPosition.jobGrade',
                'employee.branch',
                'items',
            ])
            ->find($payrollId);
    }
}
