<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['slug' => 'waka_kurikulum'], [
            'name' => 'Waka Kurikulum',
            'description' => 'Waka Kurikulum',
            'is_system' => true,
        ]);

        $this->user = User::firstOrCreate(['username' => 'wakakur'], [
            'name' => 'Waka Kurikulum Test',
            'email' => 'waka@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        SchoolSetting::firstOrCreate(['id' => 1], [
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'grade_a_min' => 86,
            'grade_b_min' => 76,
            'grade_c_min' => 61,
        ]);
    }

    public function test_can_switch_academic_year_and_semester_in_session(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('academic-year.switch'), [
            'academic_year' => '2025/2026',
            'semester' => 'Genap',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('active_academic_year', '2025/2026');
        $response->assertSessionHas('active_semester', 'Genap');

        // Following request with that session sees the updated setting
        $dashboardResponse = $this->withSession([
            'active_academic_year' => '2025/2026',
            'active_semester' => 'Genap',
        ])->get(route('dashboard'));

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('2025/2026');
        $dashboardResponse->assertSee('Genap');
    }

    public function test_can_reset_academic_year_to_master(): void
    {
        $this->actingAs($this->user);

        $response = $this->withSession([
            'active_academic_year' => '2024/2025',
            'active_semester' => 'Genap',
        ])->get(route('academic-year.reset'));

        $response->assertRedirect();
        $response->assertSessionMissing('active_academic_year');
        $response->assertSessionMissing('active_semester');

        $setting = SchoolSetting::getSetting();
        $this->assertEquals('2026/2027', $setting->academic_year);
        $this->assertEquals('Ganjil', $setting->semester);
    }

    public function test_archive_banner_is_displayed_when_archive_year_is_active(): void
    {
        $this->actingAs($this->user);

        $response = $this->withSession([
            'active_academic_year' => '2025/2026',
            'active_semester' => 'Genap',
        ])->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Mode Arsip: Anda sedang melihat data Tahun Pelajaran 2025/2026');
        $response->assertSee(route('academic-year.reset'));
    }

    public function test_can_switch_academic_year_without_specifying_semester(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('academic-year.switch'), [
            'academic_year' => '2024/2025',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('active_academic_year', '2024/2025');
    }

    public function test_changing_only_semester_on_master_year_does_not_trigger_archive_mode(): void
    {
        $this->actingAs($this->user);

        $response = $this->withSession([
            'active_academic_year' => '2026/2027',
            'active_semester' => 'Genap',
        ])->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Mode Arsip: Anda sedang melihat data');
    }

    public function test_available_academic_years_automatically_includes_next_future_year(): void
    {
        // 1. In master year 2026/2027, 2027/2028 (+1) is automatically available
        $years = SchoolSetting::getAvailableAcademicYears();
        $this->assertContains('2027/2028', $years);
        $this->assertContains('2026/2027', $years);

        // 2. When master year advances to 2027/2028 next year, 2028/2029 (+1) is automatically available
        $setting = SchoolSetting::first();
        $setting->update(['academic_year' => '2027/2028']);

        $nextYears = SchoolSetting::getAvailableAcademicYears();
        $this->assertContains('2028/2029', $nextYears);
        $this->assertContains('2027/2028', $nextYears);
    }
}
