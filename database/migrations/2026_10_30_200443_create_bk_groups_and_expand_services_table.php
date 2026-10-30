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
        // 1. Master Kelompok Binaan BK
        Schema::create('bk_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['karir', 'akademik', 'sosial_pribadi', 'kedisiplinan'])->default('karir');
            $table->string('academic_year', 20)->index();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('target_goal')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Anggota Siswa dalam Kelompok Binaan
        Schema::create('bk_group_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bk_group_id')->constrained('bk_groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('joined_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['bk_group_id', 'student_id']);
        });

        // 3. Modifikasi tabel Layanan BK untuk mendukung Moda Kelompok & Klasikal
        Schema::table('bk_services', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->string('service_mode', 20)->default('individual')->after('academic_year');
            $table->foreignId('bk_group_id')->nullable()->after('service_mode')->constrained('bk_groups')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->after('bk_group_id')->constrained('classrooms')->nullOnDelete();
        });

        // 4. Tabel Peserta Layanan BK Multi-Siswa
        Schema::create('bk_service_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bk_service_id')->constrained('bk_services')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('attendance_status', ['hadir', 'izin', 'alfa'])->default('hadir');
            $table->text('personal_notes')->nullable();
            $table->timestamps();

            $table->unique(['bk_service_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bk_service_participants');

        Schema::table('bk_services', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['bk_group_id']);
            $table->dropColumn(['classroom_id', 'bk_group_id', 'service_mode']);
            $table->foreignId('student_id')->nullable(false)->change();
        });

        Schema::dropIfExists('bk_group_students');
        Schema::dropIfExists('bk_groups');
    }
};
