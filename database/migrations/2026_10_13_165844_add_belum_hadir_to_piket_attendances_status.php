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
        Schema::table('piket_attendances', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa', 'belum_hadir'])->default('belum_hadir')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piket_attendances', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir')->change();
        });
    }
};
