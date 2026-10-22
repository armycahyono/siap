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
        Schema::create('subject_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_attendance_session_id')->constrained('subject_attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['h', 's', 'i', 'a', 'd'])->default('h'); // h: hadir, s: sakit, i: izin, a: alpa/tanpa keterangan, d: dispen
            $table->string('notes')->nullable(); // catatan khusus untuk siswa ini
            $table->timestamps();

            $table->unique(['subject_attendance_session_id', 'student_id'], 'session_student_unique');
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_attendance_records');
    }
};
