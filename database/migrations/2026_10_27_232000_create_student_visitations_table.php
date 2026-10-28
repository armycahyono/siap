<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_visitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->string('visit_type', 50)->index();
            $table->string('source_type', 50)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('primary_officer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('secondary_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_date')->index();
            $table->date('executed_date')->nullable()->index();
            $table->string('status', 30)->default('dijadwalkan')->index();
            $table->text('result_summary')->nullable();
            $table->text('follow_up_plan')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visitations');
    }
};
