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
        Schema::create('curriculum_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_calendar_upload_id')->nullable()->constrained('academic_calendar_uploads')->nullOnDelete();
            $table->string('academic_year', 20)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->index();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->string('color', 20)->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_holiday')->default(false);
            $table->unsignedSmallInteger('effective_day_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_events');
    }
};
