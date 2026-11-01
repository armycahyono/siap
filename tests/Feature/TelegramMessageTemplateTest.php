<?php

namespace Tests\Feature;

use App\Models\PiketTelegramLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\TelegramMessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_telegram_index_displays_separated_columns_for_commands_and_keyboard(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('telegram.index'));

        $response->assertOk();
        $response->assertSee('Perintah Aktif (/cmd)');
        $response->assertSee('Tombol Keyboard Grup');
        $response->assertSee('Bersihkan Log');
    }

    public function test_admin_can_clear_logs_by_period(): void
    {
        // Buat log lama (40 hari lalu) dan baru (2 hari lalu)
        $oldLog = PiketTelegramLog::create([
            'target_type' => 'test',
            'recipient_name' => 'Guru Lama',
            'chat_id' => '111',
            'message' => 'Pesan lama',
            'status' => 'sent',
        ]);
        DB::table('piket_telegram_logs')
            ->where('id', $oldLog->id)
            ->update(['created_at' => now()->subDays(40)]);

        $newLog = PiketTelegramLog::create([
            'target_type' => 'test',
            'recipient_name' => 'Guru Baru',
            'chat_id' => '222',
            'message' => 'Pesan baru',
            'status' => 'sent',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('telegram.logs.clear'), [
                'period' => '30_days',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('piket_telegram_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('piket_telegram_logs', ['id' => $newLog->id]);
    }

    public function test_admin_can_clear_failed_logs_only(): void
    {
        $sentLog = PiketTelegramLog::create([
            'target_type' => 'test',
            'recipient_name' => 'Guru Sukses',
            'chat_id' => '111',
            'message' => 'Pesan terkirim',
            'status' => 'sent',
        ]);

        $failedLog = PiketTelegramLog::create([
            'target_type' => 'test',
            'recipient_name' => 'Guru Gagal',
            'chat_id' => '222',
            'message' => 'Pesan gagal',
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('telegram.logs.clear'), [
                'period' => 'failed_only',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('piket_telegram_logs', ['id' => $sentLog->id]);
        $this->assertDatabaseMissing('piket_telegram_logs', ['id' => $failedLog->id]);
    }

    public function test_admin_can_reset_all_logs(): void
    {
        PiketTelegramLog::create([
            'target_type' => 'test',
            'recipient_name' => 'Guru Test',
            'chat_id' => '999',
            'message' => 'Pesan tes',
            'status' => 'sent',
        ]);

        $this->assertGreaterThan(0, PiketTelegramLog::count());

        $response = $this->actingAs($this->adminUser)
            ->post(route('telegram.logs.clear'), [
                'period' => 'all',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(0, PiketTelegramLog::count());
    }
}
