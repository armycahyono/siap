<?php

use App\Models\TelegramMessageTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        TelegramMessageTemplate::syncDefaultTemplates();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to drop, preserving user customizations
    }
};
