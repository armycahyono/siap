<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\TelegramMessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        SchoolSetting::first()->update([
            'school_name' => 'SMK PGRI KRAS TESTING',
            'telegram_bot_token' => '123456:FAKE_TOKEN',
            'telegram_notifications_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::first();
        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_telegram_message_templates_in_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('telegram.index'));

        $response->assertOk();
        $response->assertSee('Template Balasan Bot');
        $response->assertSee('Laporan Tim Piket Hari Ini');
    }

    public function test_admin_can_update_message_template(): void
    {
        $template = TelegramMessageTemplate::where('code', 'rekap')->firstOrFail();

        $customText = "🔥 <b>REKAP KHUSUS CUSTOM</b> 🔥\nTanggal: {tanggal}\nTerlambat: {total_terlambat} orang\nTamu: {total_tamu} orang\nSalam hangat {nama_sekolah}!";

        $response = $this->actingAs($this->adminUser)
            ->put(route('telegram.template.update', $template->id), [
                'template' => $customText,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('telegram.index'));
        $response->assertSessionHas('success');

        $template->refresh();
        $this->assertEquals($customText, $template->template);
        $this->assertTrue($template->is_active);
    }

    public function test_admin_can_reset_single_template_to_default(): void
    {
        $template = TelegramMessageTemplate::where('code', 'piket')->firstOrFail();
        $template->update([
            'template' => 'Teks acak sebelum direset',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('telegram.template.reset', $template->id));

        $response->assertRedirect(route('telegram.index'));
        $response->assertSessionHas('success');

        $template->refresh();
        $this->assertEquals($template->default_template, $template->template);
    }

    public function test_admin_can_reset_all_templates_to_default(): void
    {
        TelegramMessageTemplate::query()->update(['template' => 'Teks terubah']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('telegram.template.reset-all'));

        $response->assertRedirect(route('telegram.index'));
        $response->assertSessionHas('success');

        $first = TelegramMessageTemplate::first();
        $this->assertEquals($first->default_template, $first->template);
    }

    public function test_bot_command_renders_customized_template(): void
    {
        $rekapTemplate = TelegramMessageTemplate::where('code', 'rekap')->firstOrFail();
        $rekapTemplate->update([
            'template' => "📢 <b>REKAP EKSEKUTIF HARI INI</b>\nTanggal: {tanggal}\nTotal Terlambat: {total_terlambat} siswa\nTotal Tamu: {total_tamu} pengunjung\nSekolah: {nama_sekolah}",
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => '123456789',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Budi',
                ],
                'text' => '/rekap',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = $request['text'] ?? '';

            return str_contains($text, 'REKAP EKSEKUTIF HARI INI')
                && str_contains($text, 'Total Terlambat:')
                && str_contains($text, 'Total Tamu:')
                && str_contains($text, 'SMK PGRI KRAS TESTING');
        });
    }
}
