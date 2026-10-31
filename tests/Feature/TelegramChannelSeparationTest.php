<?php

namespace Tests\Feature;

use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramChannelSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_gtk_and_discipline_chat_ids_are_properly_separated_and_no_stolen_id(): void
    {
        $setting = SchoolSetting::first();
        $setting->update([
            'telegram_bot_token' => '123456:TEST_TOKEN',
            'telegram_notifications_enabled' => true,
            'telegram_discipline_chat_id' => null,
            'telegram_teachers_chat_id' => '-1003981909476',
        ]);

        $disciplineChannel = TelegramChannel::where('code', 'discipline')->first();
        if ($disciplineChannel) {
            $disciplineChannel->update(['chat_id' => null]);
        }

        $gtkChannel = TelegramChannel::where('code', 'gtk')->first();
        if ($gtkChannel) {
            $gtkChannel->update(['chat_id' => '-1003981909476']);
        }

        $service = app(TelegramService::class);

        $this->assertNull($service->getDisciplineChatId());
        $this->assertEquals('-1003981909476', $service->getTeachersChatId());
    }

    public function test_updating_channel_chat_id_clears_duplicates_and_syncs_school_settings(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        if (! $admin) {
            $admin = User::factory()->create();
        }

        $gtk = TelegramChannel::where('code', 'gtk')->first();
        $discipline = TelegramChannel::where('code', 'discipline')->first();

        // Misal discipline tadinya memegang chat_id
        $discipline->update(['chat_id' => '-1003981909476']);

        // Admin mengedit channel gtk dan memasukkan chat_id -1003981909476
        $response = $this->actingAs($admin)->put(route('telegram.channel.update', $gtk->id), [
            'name' => 'Warga GTK SMK PGRI Kras',
            'chat_id' => '-1003981909476',
            'is_active' => '1',
            'enabled_commands' => ['/piket', '/bantuan'],
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals('-1003981909476', $gtk->fresh()->chat_id);
        $this->assertNull($discipline->fresh()->chat_id, 'Channel discipline harus melepaskan chat_id -1003981909476');

        $setting = SchoolSetting::first();
        $this->assertEquals('-1003981909476', $setting->telegram_teachers_chat_id);
        $this->assertNull($setting->telegram_discipline_chat_id);
    }
}
