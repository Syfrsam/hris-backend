<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payslip_number' => $this->payslip_number,
            'period' => [
                'id' => $this->payrollPeriod?->id,
                'name' => $this->payrollPeriod?->name,
                'start_date' => $this->payrollPeriod?->start_date?->toDateString(),
                'cutoff_date' => $this->payrollPeriod?->cutoff_date?->toDateString(),
                'payment_date' => $this->payrollPeriod?->payment_date?->toDateString(),
            ],
            'employee' => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->user?->name,
                'nik' => $this->employee?->nik,
                'department' => $this->employee?->department?->name,
                'job_position' => $this->employee?->jobPosition?->title,
                'bank_name' => $this->employee?->bank_name,
                'bank_account_number' => $this->employee?->bank_account_number,
            ],
            'summary' => [
                'basic_salary' => (float) $this->basic_salary,
                'total_allowances' => (float) $this->total_allowances,
                'total_overtime_pay' => (float) $this->total_overtime_pay,
                'gross_salary' => (float) ($this->basic_salary + $this->total_allowances + $this->total_overtime_pay),
                'late_deduction_amount' => (float) $this->late_deduction_amount,
                'bpjs_kesehatan' => (float) $this->bpjs_kesehatan,
                'bpjs_ketenagakerjaan' => (float) $this->bpjs_ketenagakerjaan,
                'tax_pph21' => (float) $this->tax_pph21,
                'total_deductions' => (float) $this->total_deductions,
                'net_salary' => (float) $this->net_salary,
            ],
            'status' => $this->status,
            'pdf_url' => route('api.payrolls.download', ['id' => $this->id]),
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'amount' => (float) $item->amount,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
