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
        TelegramChannel::where('code', 'gtk')->update([
            'name' => 'Warga GTK SMK PGRI Kras',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TelegramChannel::where('code', 'gtk')->update([
            'name' => 'Grup GTK SMK PGRI Kras',
        ]);
    }
};
