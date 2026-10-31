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
        Schema::create('penalty_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('late_penalty_type', ['hourly_rate', 'salary_proportional', 'fixed_per_incident', 'none'])
                ->default('hourly_rate');
            $table->decimal('hourly_rate', 15, 2)->default(25000.00);
            $table->decimal('incident_rate', 15, 2)->default(50000.00);
            $table->integer('grace_period_minutes')->default(15);
            $table->enum('absent_penalty_type', ['salary_proportional', 'fixed_daily'])
                ->default('salary_proportional');
            $table->decimal('absent_daily_amount', 15, 2)->default(150000.00);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalty_settings');
    }
};
