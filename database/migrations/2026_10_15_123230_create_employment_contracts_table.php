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
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_number', 50)->unique();
            $table->enum('contract_type', ['pkwt', 'pkwtt', 'internship']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('basic_salary', 15, 2);
            $table->string('document_path')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
