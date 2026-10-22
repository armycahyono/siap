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
        Schema::table('curriculum_events', function (Blueprint $table) {
            $table->string('teacher_impact', 50)->default('normal')->after('teacher_instructions');
            // 'normal' (mengajar biasa), 'bebas_mengajar_tugas' (gugur kewajiban mengajar reguler karena panitia/pengawas), 'diliburkan_penuh'
            $table->string('gtk_checkin_time', 10)->nullable()->after('teacher_impact'); // jam wajib hadir GTK bertugas (misal 07:00)
            $table->string('gtk_checkout_time', 10)->nullable()->after('gtk_checkin_time'); // jam boleh pulang GTK bertugas (misal 13:00)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_events', function (Blueprint $table) {
            $table->dropColumn(['teacher_impact', 'gtk_checkin_time', 'gtk_checkout_time']);
        });
    }
};
