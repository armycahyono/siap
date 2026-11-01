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
        // Ubah saluran grup 'gtk' (Warga GTK) agar menggunakan allowed_roles 'semua_gtk' ringkas
        TelegramChannel::where('code', 'gtk')->update([
            'allowed_roles' => ['semua_gtk'],
        ]);

        // Dan jika ada channel lain yang memiliki allowed_roles lebih dari 15 peran (artinya semua peran dicentang), sederhanakan ke ['semua_gtk']
        $channels = TelegramChannel::all();
        foreach ($channels as $ch) {
            $roles = $ch->allowed_roles ?? [];
            if (count($roles) >= 15) {
                $ch->update(['allowed_roles' => ['semua_gtk']]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
