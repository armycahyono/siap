<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan relasi academic_year_id pada tabel classrooms
        if (! Schema::hasColumn('classrooms', 'academic_year_id')) {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('academic_year')
                    ->constrained('academic_years')
                    ->nullOnDelete();
            });

            // Sinkronkan data academic_year_id dari tabel academic_years berdasarkan nama
            $academicYears = DB::table('academic_years')->select('id', 'name')->get();
            foreach ($academicYears as $ay) {
                DB::table('classrooms')
                    ->where('academic_year', $ay->name)
                    ->update(['academic_year_id' => $ay->id]);
            }
        }

        // 2. Buat tabel riwayat penempatan rombel siswa (student_classrooms)
        Schema::create('student_classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('status', 30)->default('aktif'); // aktif, naik_kelas, tinggal_kelas, lulus, mutasi, keluar
            $table->boolean('is_repeating')->default(false); // penanda siswa mengulang / tinggal kelas
            $table->string('academic_year_name', 20)->nullable(); // snapshot cache nama tahun (cth: 2025/2026)
            $table->string('classroom_name', 100)->nullable(); // snapshot cache nama rombel (cth: X TKR 1)
            $table->text('notes')->nullable();
            $table->timestamps();

            // Kunci unik: satu siswa hanya terdaftar di satu rombel per tahun pelajaran
            $table->unique(['student_id', 'academic_year_id'], 'uq_student_academic_year');

            // Indeks komposit untuk kueri cepat
            $table->index(['academic_year_id', 'classroom_id'], 'idx_ay_classroom');
            $table->index(['student_id', 'status'], 'idx_student_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_classrooms');

        if (Schema::hasColumn('classrooms', 'academic_year_id')) {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('academic_year_id');
            });
        }
    }
};
