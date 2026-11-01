<?php

namespace Tests\Feature;

use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramGroupReplyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        SchoolSetting::first()->update([
            'telegram_bot_token' => '123456:FAKE_TOKEN',
            'telegram_notifications_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);
    }

    public function test_group_reply_maintains_custom_web_configured_keyboard(): void
    {
        // Grup Warga GTK dengan custom buttons khusus
        $gtk = TelegramChannel::where('code', 'gtk')->first();
        $gtk->update([
            'chat_id' => '-1003981909476',
            'show_reply_keyboard' => true,
            'enabled_commands' => ['/piket', '/jadwal'],
            'custom_buttons' => [
                '/piket' => ['label' => 'TOMBOL PIKET KHUSUS', 'description' => null, 'show_reply_button' => true],
                '/jadwal' => ['label' => 'TOMBOL JADWAL KHUSUS', 'description' => null, 'show_reply_button' => true],
            ],
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => '8051871751',
        ]);

        // Kirim perintah atau klik tombol dari dalam grup
        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => [
                    'id' => -1003981909476,
                    'title' => 'Warga GTK SMK PGRI Kras',
                    'type' => 'supergroup',
                ],
                'from' => [
                    'id' => 8051871751,
                    'first_name' => 'Army',
                ],
                'text' => 'TOMBOL PIKET KHUSUS',
            ],
        ]);

        $response->assertOk();

        // Verifikasi bahwa pesan balasan bot membawa keyboard kustom saluran grup, BUKAN menu awal japri
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            if ($request['chat_id'] !== '-1003981909476') {
                return false;
            }

            $markup = json_decode($request['reply_markup'] ?? '{}', true);

            // Harus mengandung label kustom grup
            $hasCustomButton = isset($markup['keyboard'][0][0]['text'])
                && $markup['keyboard'][0][0]['text'] === 'TOMBOL PIKET KHUSUS';

            // Tidak boleh mengandung menu japri seperti 'Cek Presensi Saya'
            $replyStr = json_encode($markup);
            $hasNotPersonalMenu = ! str_contains($replyStr, 'Cek Presensi Saya');

            return $hasCustomButton && $hasNotPersonalMenu;
        });
    }
}
