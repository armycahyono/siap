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
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name')->default('SMK PGRI KRAS');
            $table->string('school_npsn')->nullable();
            $table->text('school_address')->nullable();
            $table->string('school_phone')->nullable();
            $table->string('school_email')->nullable();
            $table->string('school_website')->nullable();
            $table->string('principal_name')->default('Endang Patmowati, S.Pd.');
            $table->string('principal_nip')->nullable();
            $table->string('curriculum_head_name')->default('Waka Kurikulum');
            $table->string('curriculum_head_nip')->nullable();
            $table->string('academic_year')->default('2026/2027');
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->unsignedInteger('grade_a_min')->default(86);
            $table->unsignedInteger('grade_b_min')->default(76);
            $table->unsignedInteger('grade_c_min')->default(61);
            $table->string('logo_path')->nullable();
            $table->string('kop_surat_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
