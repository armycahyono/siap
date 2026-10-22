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
        Schema::create('attendance_device_biometrics', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->index();
            $table->string('pin')->index();
            $table->string('bio_type')->default('fingerprint'); // fingerprint, face
            $table->integer('finger_id')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['device_sn', 'pin', 'bio_type', 'finger_id'], 'unique_device_user_bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_device_biometrics');
    }
};
