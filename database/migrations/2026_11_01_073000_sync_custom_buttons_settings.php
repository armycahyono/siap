<?php

use App\Models\TelegramChannel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Saluran Pak Piket Sekolah (code: piket)
        $piketButtons = [
            '/piket' => ['label' => 'siapa saja yang piket hari ini', 'description' => null, 'show_reply_button' => true],
            '/jadwal' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/izin_keluar' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/tamu' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/id' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/poin' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/bolos' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/inval' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/lapor' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/rekap' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/ultah' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/agenda' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/kaldik' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/asesmen' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/bantuan' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/guru_izin' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/supervisi' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/terlambat' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/top_kasus' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/pengumuman' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/info_sekolah' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/serah_terima' => ['label' => null, 'description' => null, 'show_reply_button' => false],
        ];

        TelegramChannel::where('code', 'piket')->update([
            'custom_buttons' => $piketButtons,
        ]);

        // 2. Saluran Warga GTK SMK PGRI Kras (code: gtk)
        $gtkButtons = [
            '/piket' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/jadwal' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/tamu' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/agenda' => ['label' => null, 'description' => null, 'show_reply_button' => true],
            '/id' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/poin' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/bolos' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/inval' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/lapor' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/rekap' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/ultah' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/kaldik' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/asesmen' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/bantuan' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/guru_izin' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/supervisi' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/terlambat' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/top_kasus' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/pengumuman' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/izin_keluar' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/info_sekolah' => ['label' => null, 'description' => null, 'show_reply_button' => false],
            '/serah_terima' => ['label' => null, 'description' => null, 'show_reply_button' => false],
        ];

        TelegramChannel::where('code', 'gtk')->update([
            'custom_buttons' => $gtkButtons,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
