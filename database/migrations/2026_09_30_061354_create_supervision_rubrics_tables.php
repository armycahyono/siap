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
        Schema::create('supervision_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Kegiatan Pendahuluan", "Kegiatan Inti", "Kegiatan Penutup"
            $table->string('code')->nullable(); // e.g. "A", "B", "C"
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supervision_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('supervision_phases')->cascadeOnDelete();
            $table->string('code')->nullable(); // e.g. "A.1", "B.3"
            $table->string('title'); // e.g. "Membuka pembelajaran", "Penguasaan materi"
            $table->text('description')->nullable();
            $table->unsignedInteger('max_score')->default(4); // 1-4 standard
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervision_indicators');
        Schema::dropIfExists('supervision_phases');
    }
};
