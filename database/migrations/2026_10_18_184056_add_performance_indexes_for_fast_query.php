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
        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'idx_users_name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index('employment_status', 'idx_employees_status');
            $table->index(['branch_id', 'department_id', 'employment_status'], 'idx_employees_branch_dept_status');
            $table->index(['department_id', 'employment_status'], 'idx_employees_dept_status');
            $table->index('created_at', 'idx_employees_created_at');
            $table->index(['employment_status', 'created_at'], 'idx_employees_status_created');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index('status', 'idx_attendances_status');
            $table->index(['employee_id', 'status', 'date'], 'idx_attendances_emp_status_date');
            $table->index(['date', 'employee_id'], 'idx_attendances_date_employee');
            $table->index(['date', 'status', 'employee_id'], 'idx_attendances_date_status_emp');
            $table->index('work_duration_minutes', 'idx_attendances_work_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_status');
            $table->dropIndex('idx_attendances_emp_status_date');
            $table->dropIndex('idx_attendances_date_employee');
            $table->dropIndex('idx_attendances_date_status_emp');
            $table->dropIndex('idx_attendances_work_duration');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_status');
            $table->dropIndex('idx_employees_branch_dept_status');
            $table->dropIndex('idx_employees_dept_status');
            $table->dropIndex('idx_employees_created_at');
            $table->dropIndex('idx_employees_status_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_name');
        });
    }
};
