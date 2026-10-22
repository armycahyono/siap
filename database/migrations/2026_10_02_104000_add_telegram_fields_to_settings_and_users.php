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
        Schema::table('school_settings', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('kop_surat_path');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->string('telegram_discipline_chat_id')->nullable()->after('telegram_bot_username');
            $table->string('telegram_piket_chat_id')->nullable()->after('telegram_discipline_chat_id');
            $table->boolean('telegram_notifications_enabled')->default(false)->after('telegram_piket_chat_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('phone');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_discipline_chat_id',
                'telegram_piket_chat_id',
                'telegram_notifications_enabled',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
            ]);
        });
    }
};
