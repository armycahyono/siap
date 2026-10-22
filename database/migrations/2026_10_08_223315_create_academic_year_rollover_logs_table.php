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
        Schema::create('academic_year_rollover_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 50)->unique();
            $table->string('source_academic_year', 30);
            $table->string('target_academic_year', 30);
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->longText('student_snapshots'); // JSON array of student states before rollover
            $table->boolean('is_rolled_back')->default(false);
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_year_rollover_logs');
    }
};
