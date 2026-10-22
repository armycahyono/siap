<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom bidang mengajar dari tabel users.
 *
 * Kolom subject, expertise_program, dan target_class bersifat periodik
 * dan dapat berubah, sehingga tidak cocok sebagai data pokok guru.
 * Data mata pelajaran kini bersumber dari jadwal pelajaran (lesson_schedules),
 * dan kelas binaan dari relasi homeroom_teacher_id di tabel classrooms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subject', 'expertise_program', 'target_class']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('avatar');
            $table->string('expertise_program')->nullable()->after('subject');
            $table->string('target_class', 50)->nullable()->after('expertise_program');
        });
    }
};
