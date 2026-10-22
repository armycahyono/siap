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
        Schema::table('piket_student_permits', function (Blueprint $table) {
            $table->string('permit_category', 30)->default('izin')->after('permit_type'); // 'sakit', 'izin', 'dispen', 'terlambat'
            $table->date('end_date')->nullable()->after('permit_date'); // untuk izin beberapa hari
            $table->string('reporter_type', 30)->default('piket')->after('action_taken'); // 'piket', 'wali_kelas', 'guru'
            $table->string('attachment_path')->nullable()->after('reporter_type'); // foto surat dokter / bukti WA
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piket_student_permits', function (Blueprint $table) {
            $table->dropColumn(['permit_category', 'end_date', 'reporter_type', 'attachment_path']);
        });
    }
};
