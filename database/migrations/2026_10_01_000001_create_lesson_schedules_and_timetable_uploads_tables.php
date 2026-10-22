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
        Schema::create('timetable_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_classes')->default(0);
            $table->unsignedInteger('total_lessons')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lesson_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_upload_id')->nullable()->constrained('timetable_uploads')->nullOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']);
            $table->unsignedTinyInteger('start_period'); // 1 - 10
            $table->unsignedTinyInteger('end_period');   // 1 - 10
            $table->string('start_time', 10)->nullable(); // e.g. 07:00
            $table->string('end_time', 10)->nullable();   // e.g. 08:30
            $table->timestamps();

            $table->index(['classroom_id', 'day', 'academic_year', 'semester']);
            $table->index(['teacher_id', 'day', 'academic_year', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_schedules');
        Schema::dropIfExists('timetable_uploads');
    }
};
