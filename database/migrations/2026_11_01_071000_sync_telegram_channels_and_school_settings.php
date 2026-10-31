<?php

use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pastikan saluran GTK terasosiasi dengan Chat ID Warga GTK (-1003981909476)
        TelegramChannel::where('code', 'gtk')->update([
            'name' => 'Warga GTK SMK PGRI Kras',
            'chat_id' => '-1003981909476',
            'is_active' => true,
        ]);

        // 2. Lepaskan Chat ID -1003981909476 dari grup Kedisiplinan jika masih menempel
        TelegramChannel::where('code', 'discipline')
            ->where('chat_id', '-1003981909476')
            ->update([
                'chat_id' => null,
            ]);

        // 3. Pastikan saluran Piket terisi dengan Chat ID Pak Piket Sekolah jika ada (-1004409003378)
        TelegramChannel::where('code', 'piket')
            ->whereNull('chat_id')
            ->update([
                'chat_id' => '-1004409003378',
            ]);

        // 4. Sinkronkan ke tabel school_settings
        $setting = SchoolSetting::first();
        if ($setting) {
            $updateData = [];

            // Jika telegram_discipline_chat_id masih memegang ID milik GTK, bersihkan
            if ($setting->telegram_discipline_chat_id === '-1003981909476') {
                $updateData['telegram_discipline_chat_id'] = null;
            }

            // Tetapkan telegram_teachers_chat_id ke grup Warga GTK
            $updateData['telegram_teachers_chat_id'] = '-1003981909476';

            // Tetapkan telegram_piket_chat_id jika belum terisi
            if (empty($setting->telegram_piket_chat_id)) {
                $updateData['telegram_piket_chat_id'] = '-1004409003378';
            }

            if (! empty($updateData)) {
                $setting->update($updateData);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback tidak perlu merusak chat_id produksi
    }
};
