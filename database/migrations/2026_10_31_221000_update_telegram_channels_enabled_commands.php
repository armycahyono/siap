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
            'enabled_commands' => json_encode(['/poin', '/lapor', '/terlambat', '/bolos', '/top_kasus', '/rekap', '/bantuan', '/id']),
        ]);

        DB::table('telegram_channels')->where('code', 'piket')->update([
            'enabled_commands' => json_encode(['/piket', '/jadwal', '/inval', '/guru_izin', '/izin_keluar', '/tamu', '/serah_terima', '/rekap', '/bantuan', '/id']),
        ]);

        DB::table('telegram_channels')->where('code', 'activities')->update([
            'enabled_commands' => json_encode(['/agenda', '/kaldik', '/asesmen', '/supervisi', '/jadwal', '/bantuan', '/id']),
        ]);

        DB::table('telegram_channels')->where('code', 'gtk')->update([
            'enabled_commands' => json_encode(['/piket', '/jadwal', '/agenda', '/ultah', '/pengumuman', '/info_sekolah', '/rekap', '/bantuan', '/id']),
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
