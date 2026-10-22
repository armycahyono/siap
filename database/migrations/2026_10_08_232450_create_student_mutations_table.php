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
        Schema::create('student_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('type', 30); // 'mutasi', 'keluar', 'dikeluarkan', 'drop_out'
            $table->date('mutation_date');
            $table->string('reason_category', 50); // 'pindah_domisili', 'ekonomi_keluarga', 'pelanggaran_disiplin', 'mengundurkan_diri', 'bekerja', 'kesehatan', 'menikah', 'lainnya'
            $table->text('reason_description')->nullable();
            $table->string('target_school')->nullable();
            $table->string('letter_number')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_mutations');
    }
};
