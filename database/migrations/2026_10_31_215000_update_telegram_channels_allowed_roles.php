<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('telegram_channels')->where('code', 'discipline')->update([
            'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kesiswaan', 'guru_bk', 'tim_kedisiplinan', 'koordinator_kedisiplinan', 'wali_kelas', 'guru_piket']),
        ]);

        DB::table('telegram_channels')->where('code', 'piket')->update([
            'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'guru_piket', 'guru']),
        ]);

        DB::table('telegram_channels')->where('code', 'activities')->update([
            'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'waka_sarpras', 'waka_humas', 'guru', 'staf']),
        ]);

        DB::table('telegram_channels')->where('code', 'gtk')->update([
            'allowed_roles' => json_encode(['super_admin', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'waka_sarpras', 'waka_humas', 'guru', 'staf', 'kepala_tu', 'operator']),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
