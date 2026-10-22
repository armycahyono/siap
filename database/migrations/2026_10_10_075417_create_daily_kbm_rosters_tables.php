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
        // 1. Roster Harian GTK (Target Presensi Gerbang & Beban Mengajar Harian)
        Schema::create('daily_kbm_rosters', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('academic_year', 20)->index();
            $table->string('semester', 10)->default('Ganjil');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_type', 20)->default('teacher'); // teacher / staff
            $table->boolean('is_expected_checkin')->default(false)->index();
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->integer('active_schedules_count')->default(0);
            $table->string('exemption_reason')->nullable();
            $table->foreignId('exemption_event_id')->nullable()->constrained('curriculum_events')->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'user_id']);
        });

        // 2. Target Jadwal Pelajaran Harian (Jadwal KBM Aktif & Status Presensi Mapel)
        Schema::create('daily_schedule_targets', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('academic_year', 20)->index();
            $table->string('semester', 10)->default('Ganjil');
            $table->foreignId('lesson_schedule_id')->constrained('lesson_schedules')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->integer('start_period');
            $table->integer('end_period');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_exempted')->default(false)->index();
            $table->string('exemption_reason')->nullable();
            $table->foreignId('exemption_event_id')->nullable()->constrained('curriculum_events')->nullOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained('subject_attendance_sessions')->nullOnDelete();
            $table->boolean('is_completed')->default(false)->index();
            $table->timestamps();

            $table->unique(['date', 'lesson_schedule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_schedule_targets');
        Schema::dropIfExists('daily_kbm_rosters');
    }
};
