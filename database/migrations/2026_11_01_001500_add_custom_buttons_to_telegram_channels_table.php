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
        Schema::table('telegram_channels', function (Blueprint $table) {
            $table->boolean('show_reply_keyboard')->default(true)->after('enabled_commands');
            $table->json('custom_buttons')->nullable()->after('show_reply_keyboard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_channels', function (Blueprint $table) {
            $table->dropColumn(['show_reply_keyboard', 'custom_buttons']);
        });
    }
};
