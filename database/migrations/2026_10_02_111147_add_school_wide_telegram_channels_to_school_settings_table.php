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
            $table->string('telegram_supervision_chat_id', 100)->nullable()->after('telegram_piket_chat_id');
            $table->string('telegram_activities_chat_id', 100)->nullable()->after('telegram_supervision_chat_id');
            $table->string('telegram_teachers_chat_id', 100)->nullable()->after('telegram_activities_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_supervision_chat_id',
                'telegram_activities_chat_id',
                'telegram_teachers_chat_id',
            ]);
        });
    }
};
