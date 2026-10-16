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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->string('nik', 30)->unique();
            $table->string('ktp_number', 20)->unique();
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date');
            $table->string('birth_place', 100)->nullable();
            $table->enum('marital_status', ['single', 'married', 'widowed'])->default('single');
            $table->integer('dependents_count')->default(0);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_holder', 100)->nullable();
            $table->enum('employment_status', ['permanent', 'contract', 'probation', 'intern'])->default('probation');
            $table->date('join_date');
            $table->date('resign_date')->nullable();
            $table->timestamps();

            // Indexes for fast lookup
            $table->index('manager_id');
            $table->index(['branch_id', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
