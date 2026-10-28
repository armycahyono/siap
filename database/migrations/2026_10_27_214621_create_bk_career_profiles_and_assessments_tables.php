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
        // 1. Profil Minat, Bakat, dan Rencana Karier Siswa
        Schema::create('bk_student_career_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->text('hobbies')->nullable();
            $table->text('talents')->nullable();
            $table->text('skills_to_develop')->nullable();
            $table->text('favorite_subjects')->nullable();
            $table->string('career_aspirations', 255)->nullable();
            $table->enum('post_graduation_plan', ['bekerja', 'melanjutkan', 'wirausaha', 'belum_memutuskan'])
                ->default('belum_memutuskan')
                ->index();
            $table->string('target_industry', 255)->nullable();
            $table->string('target_job_position', 255)->nullable();
            $table->string('target_university', 255)->nullable();
            $table->string('target_study_program', 255)->nullable();
            $table->string('entrepreneurship_type', 255)->nullable();
            $table->text('business_plan_summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year']);
        });

        // 2. Riwayat Asesmen Minat dan Bakat Siswa
        Schema::create('bk_student_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->date('assessment_date')->index();
            $table->string('assessment_name', 255);
            $table->string('assessor_name', 255)->nullable();
            $table->text('result_summary');
            $table->text('recommended_career_paths')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bk_student_assessments');
        Schema::dropIfExists('bk_student_career_profiles');
    }
};
