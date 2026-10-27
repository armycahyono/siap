<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcademicYearSettingAndTelegramReminderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher2026;

    protected User $teacher2024;

    protected AcademicYear $year2024;

    protected AcademicYear $year2026;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], [
            'name' => 'Super Admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);

        $teacherRole = Role::firstOrCreate(['slug' => 'guru'], [
            'name' => 'Guru',
            'description' => 'Guru Pengampu',
            'is_system' => false,
        ]);

        $this->year2024 = AcademicYear::create([
            'name' => '2024/2025',
            'semester' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $this->year2026 = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->teacher2026 = User::factory()->create([
            'role_id' => $teacherRole->id,
            'telegram_chat_id' => '111222333',
            'is_active' => true,
        ]);

        $this->teacher2024 = User::factory()->create([
            'role_id' => $teacherRole->id,
            'telegram_chat_id' => '444555666',
            'is_active' => true,
        ]);

        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'school_name' => 'SMK SIAP ASLI',
                'school_address' => 'Jl. Pendidikan No. 1',
                'telegram_bot_token' => 'dummy-bot-token',
                'telegram_notifications_enabled' => true,
            ]
        );
    }

    /**
     * Skenario 1: Menyimpan profil dan pengaturan sekolah saat session arsip aktif tidak mengubah master tahun ajaran.
     */
    public function test_saving_school_settings_while_archive_session_is_active_does_not_alter_master_academic_year(): void
    {
        // Simulasikan admin sedang membuka arsip 2024/2025 di tab lain
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('settings.update'), [
                'school_name' => 'SMK SIAP DIPERBARUI',
                'school_address' => 'Jl. Merdeka No. 45',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verifikasi bahwa master academic_year pada school_settings tetap 2026/2027 (tidak tertimpa 2024/2025)
        $masterSetting = SchoolSetting::find(1);
        $this->assertSame('SMK SIAP DIPERBARUI', $masterSetting->school_name);
        $this->assertSame('2026/2027', $masterSetting->academic_year);
        $this->assertNotSame('2024/2025', $masterSetting->academic_year);

        // Verifikasi pada halaman index pengaturan juga menampilkan master setting yang murni
        $indexResponse = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('settings.index'));

        $indexResponse->assertOk();
        $viewSetting = $indexResponse->viewData('setting');
        $this->assertSame('2026/2027', $viewSetting->academic_year);
    }

    /**
     * Skenario 2: Pengingat jadwal piket Telegram menggunakan tahun operasional dan mengabaikan session arsip.
     */
    public function test_telegram_piket_reminder_uses_operational_academic_year_and_ignores_archive_session(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $today = Carbon::today();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDayName = $dayNames[$today->dayOfWeekIso] ?? 'Senin';

        // Jadwal piket 2026/2027 untuk Guru 2026
        PiketSchedule::create([
            'teacher_id' => $this->teacher2026->id,
            'academic_year' => '2026/2027',
            'day' => $todayDayName,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        // Jadwal piket 2024/2025 untuk Guru 2024
        PiketSchedule::create([
            'teacher_id' => $this->teacher2024->id,
            'academic_year' => '2024/2025',
            'day' => $todayDayName,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        // Kirim pengingat piket hari ini dengan session arsip 2024/2025 aktif
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('telegram.reminder'), [
                'type' => 'today',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan pesan telegram dikirim ke guru operasional (teacher2026) dan BUKAN ke guru arsip (teacher2024)
        Http::assertSent(function ($request) {
            return str_contains($request['chat_id'], '111222333');
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request['chat_id'], '444555666');
        });
    }
}
