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
        Schema::create('supervisions', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique()->nullable(); // e.g. SPV/001/SMK-PK/2026
            $table->string('academic_year'); // 2026/2027
            $table->string('semester'); // Ganjil / Genap

            // Participants
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('principal_id')->nullable()->constrained('users')->nullOnDelete();

            // Teaching Context
            $table->string('subject');
            $table->string('class_name');
            $table->string('expertise_program')->nullable();

            // Schedule
            $table->date('schedule_date');
            $table->string('schedule_time')->nullable(); // e.g. "07:30 - 09:00"
            $table->text('schedule_notes')->nullable();

            // Status workflow
            $table->enum('status', [
                'terjadwal',
                'praobservasi',
                'observasi',
                'pascaobservasi',
                'menunggu_persetujuan',
                'disetujui',
                'selesai',
            ])->default('terjadwal');

            // Guru Upload: RPP / Modul Ajar
            $table->string('rpp_file_path')->nullable();
            $table->string('rpp_original_name')->nullable();
            $table->timestamp('rpp_uploaded_at')->nullable();

            // Tahap 1: Praobservasi
            $table->text('pre_material')->nullable(); // Materi yang akan diajarkan
            $table->text('pre_objectives')->nullable(); // Tujuan Pembelajaran
            $table->text('pre_method')->nullable(); // Model / Metode
            $table->text('pre_media')->nullable(); // Media Pembelajaran
            $table->text('pre_strategy')->nullable(); // Strategi Pembelajaran
            $table->text('pre_focus')->nullable(); // Hal yang ingin diamati
            $table->text('pre_readiness')->nullable(); // Kesiapan pembelajaran
            $table->text('pre_supervisor_notes')->nullable(); // Catatan Supervisor
            $table->timestamp('pre_completed_at')->nullable();

            // Tahap 2: Observasi Nilai
            $table->date('obs_date')->nullable();
            $table->string('obs_time')->nullable();
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('max_score')->default(92);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('predicate')->nullable(); // Sangat Baik, Baik, Cukup, Perlu Bimbingan
            $table->timestamp('obs_completed_at')->nullable();

            // Tahap 3: Pascaobservasi / Feedback Supervisor
            $table->text('post_positive')->nullable(); // Catatan Positif
            $table->text('post_improvement')->nullable(); // Hal yang perlu ditingkatkan
            $table->text('post_recommendation')->nullable(); // Rekomendasi
            $table->text('post_followup')->nullable(); // Rencana Tindak Lanjut
            $table->text('post_discussion')->nullable(); // Catatan hasil diskusi dengan guru
            $table->timestamp('post_completed_at')->nullable();

            // Approval & Digital Signature / QR Code
            $table->timestamp('supervisor_signed_at')->nullable();
            $table->boolean('approved_by_principal')->default(false);
            $table->timestamp('principal_approved_at')->nullable();
            $table->text('principal_notes')->nullable();
            $table->string('qr_token', 64)->unique()->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();
        });

        // Skor per indikator
        Schema::create('supervision_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_id')->constrained('supervisions')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('supervision_indicators')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0); // 1, 2, 3, 4
            $table->text('notes')->nullable();
            $table->unique(['supervision_id', 'indicator_id']);
            $table->timestamps();
        });

        // Dokumentasi foto
        Schema::create('supervision_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_id')->constrained('supervisions')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->string('category')->default('Kegiatan Pembelajaran');
            $table->date('taken_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervision_photos');
        Schema::dropIfExists('supervision_scores');
        Schema::dropIfExists('supervisions');
    }
};
