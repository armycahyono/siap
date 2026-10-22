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
        Schema::create('timetable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_upload_id')->constrained('timetable_uploads')->cascadeOnDelete();
            $table->string('class_name', 50);
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('day', 20);
            $table->unsignedTinyInteger('start_period');
            $table->unsignedTinyInteger('end_period');
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('subject_code', 50);
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('teacher_nickname', 50);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_valid')->default(true);
            $table->string('status_message')->nullable();
            $table->timestamps();

            $table->index(['timetable_upload_id', 'class_name']);
            $table->index(['timetable_upload_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_items');
    }
};
