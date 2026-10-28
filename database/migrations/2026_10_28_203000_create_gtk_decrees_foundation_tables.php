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
        // 1. Tabel Dokumen Induk SK GTK
        Schema::create('gtk_decrees', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100)->unique();
            $table->string('title', 255);
            $table->string('type', 50)->index(); // pembagian_tugas, tugas_tambahan, kepanitiaan, wali_kelas, guru_piket, pengangkatan, lainnya
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->nullable()->index(); // Cache nama tahun ajaran (misal: 2026/2027)

            // Tanggal formal SK (dibedakan secara tegas sesuai ketentuan regulasi)
            $table->date('issued_date'); // Tanggal penetapan SK
            $table->date('tmt_date'); // TMT (Terhitung Mulai Tanggal)
            $table->date('valid_from'); // Tanggal mulai berlaku efektif
            $table->date('valid_until')->nullable(); // Tanggal akhir berlaku efektif

            // Data Pejabat Penetap
            $table->string('signer_name', 150)->nullable();
            $table->string('signer_title', 150)->nullable();

            // Dokumen Berkas Privat
            $table->string('file_path')->nullable(); // Path relatif pada disk privat 'local' (storage/app/private/sk_gtk/...)
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->text('description')->nullable();

            // Status Dokumen: draft, aktif, berakhir, dibatalkan
            $table->string('status', 30)->default('draft')->index();

            // Audit Aktivasi
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();

            // Audit Pembatalan
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            // Audit Pembuat & Pengubah
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indeks komposit untuk efisiensi kueri pencarian & pelaporan
            $table->index(['status', 'academic_year'], 'idx_decrees_status_year');
            $table->index(['valid_from', 'valid_until'], 'idx_decrees_validity');
        });

        // 2. Tabel Penerima SK GTK (Personil yang tercantum dalam SK)
        Schema::create('gtk_decree_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_decree_id')->constrained('gtk_decrees')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Kendala unik: 1 GTK hanya tercatat satu kali sebagai penerima pada SK yang sama
            $table->unique(['gtk_decree_id', 'user_id'], 'uq_gtk_decree_user');
            $table->index('user_id', 'idx_decree_recipient_user');
        });

        // 3. Tabel Butir Keputusan / Rincian Penugasan SK GTK
        Schema::create('gtk_decree_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_decree_id')->constrained('gtk_decrees')->cascadeOnDelete();
            $table->foreignId('gtk_decree_recipient_id')->nullable()->constrained('gtk_decree_recipients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Kategori Penugasan: mengajar, struktural, operasional, peran_sistem, arsip, lainnya
            $table->string('assignment_category', 50)->index();

            // Referensi Entitas Operasional SIAP (seluruhnya nullable, divalidasi berdasarkan kategori)
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('structural_position_id')->nullable()->constrained('structural_positions')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();

            $table->unsignedSmallInteger('teaching_hours')->nullable(); // Jam mengajar tatap muka per minggu jika mengajar

            // Masa berlaku butir keputusan
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // Status penerapan penugasan: menunggu, diterapkan, dilewati, konflik, dibatalkan, berakhir
            $table->string('implementation_status', 30)->default('menunggu')->index();

            // Jenis asal penerapan: dibuat_sk (generate baru), ditautkan (link ke existing), arsip (hanya dokumentasi)
            $table->string('link_type', 30)->default('dibuat_sk');

            // Referensi Model & ID Penugasan Operasional Riil (dengan FK langsung berkendala nullOnDelete untuk mencegah cascading delete data operasional)
            $table->string('operational_type')->nullable();
            $table->unsignedBigInteger('operational_id')->nullable();

            $table->foreignId('teacher_subject_assignment_id')->nullable()->constrained('teacher_subject_assignments')->nullOnDelete();
            $table->foreignId('teacher_structural_assignment_id')->nullable()->constrained('teacher_structural_assignments')->nullOnDelete();
            $table->foreignId('user_role_assignment_id')->nullable()->constrained('user_role_assignments')->nullOnDelete();

            // Catatan konflik atau alasan tidak diterapkan
            $table->text('conflict_notes')->nullable();
            $table->text('notes')->nullable();

            // Audit Pembuat & Pengubah
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indeks komposit untuk kueri cepat
            $table->index(['gtk_decree_id', 'assignment_category'], 'idx_decree_details_cat');
            $table->index(['user_id', 'implementation_status'], 'idx_decree_details_user_status');
            $table->index(['operational_type', 'operational_id'], 'idx_decree_details_operational');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gtk_decree_details');
        Schema::dropIfExists('gtk_decree_recipients');
        Schema::dropIfExists('gtk_decrees');
    }
};
