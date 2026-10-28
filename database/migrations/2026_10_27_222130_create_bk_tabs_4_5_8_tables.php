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
        // 1. Tab 4: Prestasi, Penghargaan, dan Kegiatan Siswa
        Schema::create('bk_student_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->date('event_date')->index();
            $table->enum('type', ['prestasi', 'kegiatan'])->default('prestasi')->index();
            $table->enum('category', [
                'akademik',
                'non_akademik',
                'seni',
                'olahraga',
                'keagamaan',
                'organisasi',
                'ekskul',
                'lainnya',
            ])->default('akademik')->index();
            $table->enum('level', [
                'sekolah',
                'kecamatan',
                'kabupaten',
                'provinsi',
                'nasional',
                'internasional',
            ])->default('sekolah')->index();
            $table->string('title', 255);
            $table->string('achievement_result', 255)->nullable();
            $table->string('organizer', 255)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. Tab 5: Catatan Perkembangan Siswa Non-Kasus
        Schema::create('bk_student_development_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->date('note_date')->index();
            $table->enum('category', [
                'sikap_belajar',
                'kedisiplinan_positif',
                'kemandirian',
                'interaksi_sosial',
                'tanggung_jawab',
                'rekomendasi_pembinaan',
                'lainnya',
            ])->default('sikap_belajar')->index();
            $table->string('title', 255);
            $table->text('description');
            $table->text('follow_up_recommendation')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 3. Tab 8: Dokumen Pendukung & Arsip Digital BK (Private Storage)
        Schema::create('bk_student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->enum('document_category', [
                'sertifikat_prestasi',
                'hasil_psikotes',
                'surat_komitmen',
                'surat_perjanjian',
                'berkas_kesehatan',
                'administrasi_bk',
                'lainnya',
            ])->default('lainnya')->index();
            $table->string('title', 255);
            $table->date('document_date')->nullable()->index();
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bk_student_documents');
        Schema::dropIfExists('bk_student_development_notes');
        Schema::dropIfExists('bk_student_achievements');
    }
};
