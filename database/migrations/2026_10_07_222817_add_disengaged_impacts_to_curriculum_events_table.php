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
            // Jam pelaksanaan spesifik bagi siswa yang terlibat
            $table->string('student_start_time', 10)->nullable()->after('gtk_checkout_time');
            $table->string('student_end_time', 10)->nullable()->after('student_start_time');

            // Dampak bagi Guru yang TIDAK terlibat: masuk_normal, pulang_cepat, libur_khusus
            $table->string('disengaged_teacher_impact', 50)->default('masuk_normal')->after('student_end_time');
            $table->integer('disengaged_teacher_max_period')->nullable()->after('disengaged_teacher_impact');
            $table->string('disengaged_teacher_dismissal_time', 10)->nullable()->after('disengaged_teacher_max_period');

            // Dampak bagi Staf TU yang TIDAK terlibat: masuk_normal, pulang_cepat, libur_khusus
            $table->string('disengaged_staff_impact', 50)->default('masuk_normal')->after('disengaged_teacher_dismissal_time');
            $table->string('disengaged_staff_dismissal_time', 10)->nullable()->after('disengaged_staff_impact');

            // Dampak bagi Siswa yang TIDAK terlibat: kbm_normal, pembelajaran_daring, pulang_cepat, bebas_kbm, libur_khusus
            $table->string('disengaged_student_impact', 50)->default('kbm_normal')->after('disengaged_staff_dismissal_time');
            $table->integer('disengaged_student_max_period')->nullable()->after('disengaged_student_impact');
            $table->string('disengaged_student_dismissal_time', 10)->nullable()->after('disengaged_student_max_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_events', function (Blueprint $table) {
            $table->dropColumn([
                'student_start_time',
                'student_end_time',
                'disengaged_teacher_impact',
                'disengaged_teacher_max_period',
                'disengaged_teacher_dismissal_time',
                'disengaged_staff_impact',
                'disengaged_staff_dismissal_time',
                'disengaged_student_impact',
                'disengaged_student_max_period',
                'disengaged_student_dismissal_time',
            ]);
        });
    }
};
