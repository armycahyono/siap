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
        Schema::create('attendance_biometrics', function (Blueprint $table) {
            $table->id();
            $table->string('pin')->index(); // NIGS guru atau NISN siswa
            $table->string('bio_type')->default('fingerprint'); // fingerprint, face
            $table->integer('finger_id')->default(0); // 0-9
            $table->integer('size')->default(0);
            $table->integer('valid')->default(1);
            $table->text('template'); // Biometric base64 / template string
            $table->string('source_device_sn')->nullable()->index(); // SN mesin sumber
            $table->timestamps();

            $table->unique(['pin', 'bio_type', 'finger_id'], 'unique_user_biometric');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_biometrics');
    }
};
