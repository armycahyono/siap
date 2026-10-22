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
        Schema::create('daily_classroom_kbm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('academic_year', 20)->index();
            $table->string('semester', 10)->default('Ganjil');
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->boolean('is_kbm_active')->default(true)->index(); // false jika libur / PKL / Bebas KBM
            $table->string('exemption_reason')->nullable();
            $table->foreignId('exemption_event_id')->nullable()->constrained('curriculum_events')->nullOnDelete();
            $table->integer('total_students')->default(0);
            $table->integer('present_students_count')->default(0);
            $table->integer('permit_students_count')->default(0); // Izin/Sakit
            $table->integer('late_students_count')->default(0);
            $table->integer('early_leave_students_count')->default(0);
            $table->integer('alfa_students_count')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0.00); // 0.00 - 100.00%
            $table->integer('scheduled_sessions_count')->default(0);
            $table->integer('completed_sessions_count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'classroom_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_classroom_kbm_snapshots');
    }
};
