<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'payslip_number',
        'basic_salary',
        'total_allowances',
        'total_overtime_pay',
        'total_deductions',
        'late_deduction_amount',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'tax_pph21',
        'net_salary',
        'pdf_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_overtime_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'late_deduction_amount' => 'decimal:2',
            'bpjs_kesehatan' => 'decimal:2',
            'bpjs_ketenagakerjaan' => 'decimal:2',
            'tax_pph21' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
