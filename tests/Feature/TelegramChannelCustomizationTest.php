<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramChannelCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00', 'Asia/Jakarta'));

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'telegram_bot_token' => '123456789:AAFakeTokenTest',
            'telegram_bot_username' => 'smkpgrikras_bot',
            'telegram_notifications_enabled' => true,
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator']);
        $this->admin = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin_test@test.local',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_default_command_definitions_are_available(): void
    {
        $defs = TelegramChannel::getDefaultCommandDefinitions();

        $this->assertArrayHasKey('/piket', $defs);
        $this->assertArrayHasKey('/jadwal', $defs);
        $this->assertArrayHasKey('/bantuan', $defs);
        $this->assertArrayHasKey('/id', $defs);
        $this->assertEquals('📋 Tim Piket Hari Ini', $defs['/piket']['label']);
    }

    public function test_channel_custom_buttons_and_labels(): void
    {
        $channel = TelegramChannel::create([
            'name' => 'Grup Piket Kustom',
            'code' => 'grup_piket_kustom',
            'chat_id' => '-100999888777',
            'show_reply_keyboard' => true,
            'enabled_commands' => ['/piket', '/jadwal', '/agenda'],
            'custom_buttons' => [
                '/piket' => [
                    'label' => 'Jadwal Piket Hari Ini',
                    'description' => 'Melihat daftar petugas piket',
                    'show_reply_button' => true,
                ],
                '/jadwal' => [
                    'label' => 'Jadwal Kelas Jam Ini',
                    'description' => 'Jadwal KBM saat ini',
                    'show_reply_button' => false,
                ],
            ],
            'is_active' => true,
        ]);

        // Custom label override
        $this->assertEquals('Jadwal Piket Hari Ini', $channel->getButtonLabel('/piket'));
        $this->assertEquals('Melihat daftar petugas piket', $channel->getCommandDescription('/piket'));

        // Fallback to default label
        $this->assertEquals('🗓️ Agenda Sekolah', $channel->getButtonLabel('/agenda'));

        // Enabled/disabled reply button
        $this->assertTrue($channel->isReplyButtonEnabled('/piket'));
        $this->assertFalse($channel->isReplyButtonEnabled('/jadwal'));
        $this->assertTrue($channel->isReplyButtonEnabled('/agenda'));

        // Built reply keyboard only contains /piket and /agenda, NOT /jadwal
        $keyboard = $channel->buildReplyKeyboard();
        $this->assertArrayHasKey('keyboard', $keyboard);
        $flattened = collect($keyboard['keyboard'])->flatten(1)->pluck('text')->all();

        $this->assertContains('Jadwal Piket Hari Ini', $flattened);
        $this->assertContains('🗓️ Agenda Sekolah', $flattened);
        $this->assertNotContains('Jadwal Kelas Jam Ini', $flattened);
    }

    public function test_channel_with_reply_keyboard_disabled_returns_remove_keyboard(): void
    {
        $channel = TelegramChannel::create([
            'name' => 'Grup Sunyi',
            'code' => 'grup_sunyi',
            'chat_id' => '-100111222333',
            'show_reply_keyboard' => false,
            'enabled_commands' => ['/piket'],
            'is_active' => true,
        ]);

        $keyboard = $channel->buildReplyKeyboard();
        $this->assertEquals(['remove_keyboard' => true], $keyboard);
    }

    public function test_resolve_command_from_text(): void
    {
        $channel = TelegramChannel::create([
            'name' => 'Grup Guru',
            'code' => 'grup_guru',
            'chat_id' => '-100555666777',
            'custom_buttons' => [
                '/piket' => ['label' => 'Siapa Piket?'],
            ],
            'is_active' => true,
        ]);

        // Custom button text resolution
        $this->assertEquals('/piket', TelegramChannel::resolveCommandFromText('Siapa Piket?', $channel));

        // Default button text resolution
        $this->assertEquals('/jadwal', TelegramChannel::resolveCommandFromText('🏫 Jadwal Jam Sekarang', $channel));

        // Direct command text should return null (keeps argument intact)
        $this->assertNull(TelegramChannel::resolveCommandFromText('/poin 12345', $channel));

        // Casual chat text returns null
        $this->assertNull(TelegramChannel::resolveCommandFromText('Selamat pagi semuanya', $channel));
    }

    public function test_admin_can_update_channel_custom_buttons_via_controller(): void
    {
        $channel = TelegramChannel::create([
            'name' => 'Grup Awal',
            'code' => 'grup_awal',
            'chat_id' => '-100123123123',
            'show_reply_keyboard' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('telegram.channel.update', $channel->id), [
            'name' => 'Grup Diperbarui',
            'chat_id' => '-100123123123',
            'show_reply_keyboard' => '1',
            'enabled_commands' => ['/piket', '/inval'],
            'custom_buttons' => [
                '/piket' => [
                    'label' => 'Petugas Piket Hari Ini',
                    'description' => 'Lihat guru piket yang jaga',
                    'show_reply_button' => '1',
                ],
                '/inval' => [
                    'label' => 'Kelas Kosong & Inval',
                    'description' => 'Lihat kelas kosong',
                    // show_reply_button not checked
                ],
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $channel->refresh();

        $this->assertEquals('Grup Diperbarui', $channel->name);
        $this->assertTrue($channel->show_reply_keyboard);
        $this->assertEquals('Petugas Piket Hari Ini', $channel->getButtonLabel('/piket'));
        $this->assertTrue($channel->isReplyButtonEnabled('/piket'));
        $this->assertFalse($channel->isReplyButtonEnabled('/inval'));
    }

    public function test_webhook_handles_custom_button_press_in_group(): void
    {
        $channel = TelegramChannel::create([
            'name' => 'Warga TK SMK PGRI Kras',
            'code' => 'warga_gtk',
            'chat_id' => '-100777888999',
            'show_reply_keyboard' => true,
            'enabled_commands' => ['/piket'],
            'custom_buttons' => [
                '/piket' => [
                    'label' => 'Cek Petugas Piket',
                    'show_reply_button' => true,
                ],
            ],
            'is_active' => true,
        ]);

        // User taps the custom reply button: "Cek Petugas Piket"
        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => [
                    'id' => -100777888999,
                    'title' => 'Warga TK SMK PGRI Kras',
                    'type' => 'supergroup',
                ],
                'from' => [
                    'id' => 8051871751,
                    'first_name' => 'Army',
                ],
                'text' => 'Cek Petugas Piket',
            ],
        ]);

        $response->assertOk();

        // Bot should have responded to /piket
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'TIM PIKET HARI INI')
                && $request['chat_id'] === '-100777888999';
        });
    }
}
