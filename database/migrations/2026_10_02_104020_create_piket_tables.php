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
        // 1. Jadwal Piket
        Schema::create('piket_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20)->index();
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'])->index();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['koordinator', 'anggota'])->default('anggota');
            $table->string('duty_location', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Tukar Jadwal Piket
        Schema::create('piket_duty_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_schedule_id')->constrained('piket_schedules')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('replacement_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('duty_date')->index();
            $table->text('reason');
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 3. Jurnal Piket Harian
        Schema::create('piket_journals', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('academic_year', 20)->index();
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('general_notes')->nullable();
            $table->enum('status', ['berlangsung', 'selesai'])->default('berlangsung')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Presensi Guru Piket
        Schema::create('piket_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_journal_id')->constrained('piket_journals')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Kelas Inval / Pengganti Guru Kosong
        Schema::create('piket_inval_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_journal_id')->constrained('piket_journals')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('absent_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('inval_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('period_start');
            $table->unsignedTinyInteger('period_end');
            $table->string('reason', 255);
            $table->text('assignment_material')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Perizinan Siswa
        Schema::create('piket_student_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_journal_id')->nullable()->constrained('piket_journals')->nullOnDelete();
            $table->string('permit_number', 50)->unique();
            $table->date('permit_date')->index();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name', 255);
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->enum('permit_type', ['terlambat', 'izin_keluar_sementara', 'izin_pulang_cepat', 'izin_tidak_masuk'])->index();
            $table->text('reason');
            $table->time('entry_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->string('period_assigned', 50)->nullable();
            $table->string('action_taken', 255)->nullable();
            $table->foreignId('given_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['diberikan', 'ditolak', 'selesai'])->default('diberikan')->index();
            $table->boolean('notified_homeroom')->default(false);
            $table->boolean('notified_subject_teacher')->default(false);
            $table->timestamps();
        });

        // 7. Catatan Kejadian / Buku Kasus
        Schema::create('piket_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_journal_id')->nullable()->constrained('piket_journals')->nullOnDelete();
            $table->date('incident_date')->index();
            $table->time('incident_time');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name', 255);
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->enum('category', ['kedisiplinan', 'seragam', 'gadget_terlarang', 'perkelahian', 'rokok', 'kesehatan_pingsan', 'lainnya'])->index();
            $table->text('description');
            $table->text('action_taken');
            $table->enum('follow_up_target', ['selesai_di_piket', 'rujuk_wali_kelas', 'rujuk_bk', 'rujuk_kesiswaan'])->default('selesai_di_piket')->index();
            $table->text('confiscated_items')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['tercatat', 'ditindaklanjuti', 'selesai'])->default('tercatat')->index();
            $table->timestamps();
        });

        // 8. Buku Tamu Piket
        Schema::create('piket_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piket_journal_id')->nullable()->constrained('piket_journals')->nullOnDelete();
            $table->date('visit_date')->index();
            $table->time('check_in_time');
            $table->time('check_out_time')->nullable();
            $table->string('guest_name', 255);
            $table->string('institution', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('purpose', 255);
            $table->string('meet_with', 255);
            $table->string('badge_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 9. Log Telegram Bot
        Schema::create('piket_telegram_logs', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 50)->index();
            $table->string('recipient_name', 255);
            $table->string('chat_id', 100)->index();
            $table->text('message');
            $table->enum('status', ['sent', 'failed'])->index();
            $table->text('response_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piket_telegram_logs');
        Schema::dropIfExists('piket_guests');
        Schema::dropIfExists('piket_incidents');
        Schema::dropIfExists('piket_student_permits');
        Schema::dropIfExists('piket_inval_classes');
        Schema::dropIfExists('piket_attendances');
        Schema::dropIfExists('piket_journals');
        Schema::dropIfExists('piket_duty_swaps');
        Schema::dropIfExists('piket_schedules');
    }
};
