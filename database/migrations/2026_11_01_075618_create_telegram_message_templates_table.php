<?php

use App\Models\TelegramMessageTemplate;
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
        Schema::create('telegram_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->default('umum');
            $table->string('command', 50)->nullable();
            $table->text('template');
            $table->text('default_template');
            $table->json('available_placeholders')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed data template default awal
        $seedData = TelegramMessageTemplate::getDefaultSeedData();
        $now = now();
        foreach ($seedData as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $row['available_placeholders'] = json_encode($row['available_placeholders']);
        }
        unset($row);

        DB::table('telegram_message_templates')->insert($seedData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_message_templates');
    }
};
