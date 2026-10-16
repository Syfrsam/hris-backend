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
        Schema::create('job_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->integer('level');
            $table->decimal('min_salary', 15, 2)->default(0);
            $table->decimal('max_salary', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_grades');
    }
};
