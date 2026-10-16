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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('clock_in_time')->nullable();
            $table->decimal('clock_in_latitude', 10, 8)->nullable();
            $table->decimal('clock_in_longitude', 11, 8)->nullable();
            $table->decimal('clock_in_distance_meters', 8, 2)->nullable();
            $table->string('clock_in_photo_path')->nullable();
            $table->timestamp('clock_out_time')->nullable();
            $table->decimal('clock_out_latitude', 10, 8)->nullable();
            $table->decimal('clock_out_longitude', 11, 8)->nullable();
            $table->decimal('clock_out_distance_meters', 8, 2)->nullable();
            $table->string('clock_out_photo_path')->nullable();
            $table->enum('status', ['on_time', 'late', 'early_leave', 'absent', 'on_leave', 'holiday'])->default('on_time');
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('work_duration_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique attendance per employee per day
            $table->unique(['employee_id', 'date']);

            // Composite indexes for fast reporting and cut-off recapitulation
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
