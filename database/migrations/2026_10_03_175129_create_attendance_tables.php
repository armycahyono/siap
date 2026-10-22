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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Revo WDV-204BNC');
            $table->string('device_sn')->nullable()->unique();
            $table->string('ip_address')->default('192.168.1.25');
            $table->integer('port')->default(5005);
            $table->integer('server_port')->default(8014);
            $table->string('comm_key')->default('0');
            $table->string('status')->default('offline'); // online, offline
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->nullable()->index();
            $table->string('pin')->index(); // NIGS guru atau NISN siswa
            $table->string('user_type')->default('unknown')->index(); // teacher, student, unknown
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('name')->nullable();
            $table->dateTime('scanned_at')->index();
            $table->string('status')->default('check_in'); // check_in, check_out, break_out, break_in, etc.
            $table->string('verify_type')->nullable(); // fingerprint, face, password, card
            $table->string('work_code')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['device_sn', 'pin', 'scanned_at'], 'unique_device_pin_scan');
        });

        Schema::create('attendance_device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn')->index();
            $table->text('command');
            $table->string('status')->default('pending'); // pending, sent, executed, failed
            $table->text('response')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_device_commands');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_devices');
    }
};
