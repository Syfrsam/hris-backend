<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('payslip_number', 50)->unique();
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_allowances', 15, 2)->default(0);
            $table->decimal('total_overtime_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('late_deduction_amount', 15, 2)->default(0);
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
            $table->decimal('bpjs_ketenagakerjaan', 15, 2)->default(0);
            $table->decimal('tax_pph21', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2);
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['draft', 'authorized', 'published'])->default('draft');
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
