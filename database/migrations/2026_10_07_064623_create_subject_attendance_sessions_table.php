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
        Schema::create('subject_attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_schedule_id')->nullable()->constrained('lesson_schedules')->nullOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete(); // Guru riil yang mengajar/mengabsen
            $table->foreignId('scheduled_teacher_id')->nullable()->constrained('users')->nullOnDelete(); // Guru asli sesuai jadwal

            $table->date('attendance_date');
            $table->tinyInteger('start_period')->default(1);
            $table->tinyInteger('end_period')->default(2);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('academic_year', 20)->nullable();
            $table->enum('semester', ['ganjil', 'genap'])->nullable();

            $table->boolean('is_swap')->default(false); // Flag tukar jam / guru pengganti
            $table->string('swap_type', 30)->nullable(); // 'tukar_jam', 'guru_pengganti', 'inval', 'mandiri'
            $table->text('swap_notes')->nullable();

            $table->string('topic', 255)->nullable(); // Pokok bahasan materi / jurnal kelas
            $table->text('notes')->nullable(); // Catatan kegiatan kelas

            $table->timestamps();

            $table->index(['classroom_id', 'attendance_date']);
            $table->index(['teacher_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_attendance_sessions');
    }
};
