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
            $table->string('target_audience', 50)->default('semua')->after('is_holiday'); // 'semua', 'tingkat', 'rombel', 'guru_staf'
            $table->json('target_grade_levels')->nullable()->after('target_audience'); // [10, 11, 12] atau grade_level_id
            $table->json('target_classroom_ids')->nullable()->after('target_grade_levels'); // [1, 2, ...] classroom ids
            $table->string('learning_impact', 50)->default('normal')->after('target_classroom_ids'); // 'normal', 'pulang_cepat', 'pembelajaran_daring', 'bebas_kbm_kegiatan', 'libur_khusus'
            $table->unsignedTinyInteger('max_period')->nullable()->after('learning_impact'); // batas jam ke- jika pulang cepat (misal jam ke-4)
            $table->string('dismissal_time', 10)->nullable()->after('max_period'); // batas jam pulang (misal '10:00')
            $table->text('teacher_instructions')->nullable()->after('dismissal_time'); // petunjuk guru / instruksi tugas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_events', function (Blueprint $table) {
            $table->dropColumn([
                'target_audience',
                'target_grade_levels',
                'target_classroom_ids',
                'learning_impact',
                'max_period',
                'dismissal_time',
                'teacher_instructions',
            ]);
        });
    }
};
