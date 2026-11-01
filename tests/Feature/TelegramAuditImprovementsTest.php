<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use App\Models\TelegramMessageTemplate;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAuditImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::firstOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'telegram_bot_token' => '123456:FAKE_TOKEN_FOR_TEST',
                'telegram_notifications_enabled' => true,
            ]
        );
    }

    public function test_webhook_secret_token_verification(): void
    {
        $service = app(TelegramService::class);
        $expectedSecret = $service->getWebhookSecretToken();

        // 1. Request with invalid secret token should return 401
        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'invalid-token-xyz',
        ])->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 12345, 'type' => 'private'],
                'from' => ['id' => 12345, 'first_name' => 'John'],
                'text' => '/help',
            ],
        ]);

        $response->assertStatus(401);

        // 2. Request with valid secret token should return 200
        $responseValid = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => $expectedSecret,
        ])->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 12345, 'type' => 'private'],
                'from' => ['id' => 12345, 'first_name' => 'John'],
                'text' => '/help',
            ],
        ]);

        $responseValid->assertStatus(200);
    }

    public function test_stale_state_is_cancelled_by_batal_command(): void
    {
        Http::fake();

        $chatId = '888123';
        Cache::put("tg_piket_state_{$chatId}", [
            'flow' => 'student_permit',
            'step' => 'awaiting_student_name',
        ], now()->addMinutes(15));

        $this->assertTrue(Cache::has("tg_piket_state_{$chatId}"));

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $chatId, 'first_name' => 'Guru A'],
                'text' => '/batal',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertFalse(Cache::has("tg_piket_state_{$chatId}"));
    }

    public function test_stale_state_is_reset_when_user_types_start_or_menu(): void
    {
        Http::fake();

        $chatId = '888124';
        Cache::put("tg_piket_state_{$chatId}", [
            'flow' => 'incident',
            'step' => 'awaiting_student_name',
        ], now()->addMinutes(15));

        $this->assertTrue(Cache::has("tg_piket_state_{$chatId}"));

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $chatId, 'first_name' => 'Guru B'],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertFalse(Cache::has("tg_piket_state_{$chatId}"));
    }

    public function test_group_interactive_commands_are_redirected_to_private_chat(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['username' => 'siap_test_bot']]),
        ]);

        $groupChatId = '-10099887766';
        $user = User::factory()->create([
            'name' => 'Petugas Piket Hari Ini',
            'telegram_chat_id' => '12345678',
        ]);

        TelegramChannel::create([
            'name' => 'Grup Piket Test',
            'code' => 'piket_test',
            'chat_id' => $groupChatId,
            'enabled_commands' => ['/izin_siswa', '/lapor', '/bantuan'],
            'is_active' => true,
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => $groupChatId, 'type' => 'supergroup', 'title' => 'Grup Piket Test'],
                'from' => ['id' => '12345678', 'first_name' => 'Petugas'],
                'text' => '/izin_siswa',
            ],
        ]);

        $response->assertStatus(200);

        // State MUST NOT be stored on the group chatId
        $this->assertFalse(Cache::has("tg_piket_state_{$groupChatId}"));
    }

    public function test_deep_linking_initiates_wizard_in_private_chat(): void
    {
        Http::fake();

        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create([
            'name' => 'Guru Piket Valid',
            'role_id' => $role->id,
            'telegram_chat_id' => '777666',
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => '777666', 'type' => 'private'],
                'from' => ['id' => '777666', 'first_name' => 'Guru'],
                'text' => '/start lapor',
            ],
        ]);

        $response->assertStatus(200);
        // User with permission has state initialized
        $state = Cache::get('tg_piket_state_777666');
        $this->assertNotNull($state);
        $this->assertEquals('incident', $state['flow']);
    }

    public function test_expanded_message_templates_are_customizable_and_rendered(): void
    {
        TelegramMessageTemplate::syncDefaultTemplates();

        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'guru_izin']);
        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'izin_keluar']);
        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'terlambat']);
        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'serah_terima']);
        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'id']);
        $this->assertDatabaseHas('telegram_message_templates', ['code' => 'poin']);

        // Customise a template
        $tpl = TelegramMessageTemplate::where('code', 'terlambat')->first();
        $tpl->update([
            'template' => 'CUSTOM TELAT: {total_terlambat} siswa pada {tanggal}',
        ]);

        $rendered = TelegramMessageTemplate::render('terlambat', [
            'total_terlambat' => 5,
            'tanggal' => 'Senin, 02 Nov',
        ]);

        $this->assertEquals('CUSTOM TELAT: 5 siswa pada Senin, 02 Nov', $rendered);
    }
}
