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
        Schema::create('school_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('category')->default('lainnya'); // kaldik, smk_khusus, akademik_ujian, supervisi, kesiswaan, manajemen, lainnya
            $table->string('target_participant')->nullable(); // Semua Siswa, Kelas X, Kelas XI, Kelas XII, Dewan Guru, dll.
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable(); // Hex color or badge color
            $table->integer('default_duration_days')->default(1);
            $table->boolean('is_kaldik')->default(false);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_activities');
    }
};
