<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $teacher;

    protected Role $superAdminRole;

    protected Role $guruRole;

    protected Role $ksRole;

    protected Role $wakaRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();
        $this->guruRole = Role::where('slug', 'guru')->firstOrFail();
        $this->ksRole = Role::where('slug', 'kepala_sekolah')->firstOrFail();
        $this->wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $this->teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);
    }

    public function test_updating_school_setting_with_principal_id_does_not_mutate_user_role(): void
    {
        $guruRoleIdBefore = $this->teacher->role_id;
        $guruPositionBefore = $this->teacher->position;

        $response = $this->actingAs($this->superAdmin)->post(route('settings.update'), [
            'school_name' => 'SMK PGRI KRAS',
            'principal_id' => $this->teacher->id,
            'principal_name' => 'Drs. Guru Kepala',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan school_settings mencatat referensi nama & ID pejabat
        $setting = SchoolSetting::getSetting(true);
        $this->assertEquals($this->teacher->id, $setting->principal_id);
        $this->assertEquals('Drs. Guru Kepala', $setting->principal_name);

        // Role dasar dan posisi akun guru TIDAK BERUBAH
        $this->teacher->refresh();
        $this->assertEquals($guruRoleIdBefore, $this->teacher->role_id);
        $this->assertEquals($guruPositionBefore, $this->teacher->position);
        $this->assertNotEquals($this->ksRole->id, $this->teacher->role_id);
    }

    public function test_updating_school_setting_with_curriculum_head_id_does_not_mutate_user_role(): void
    {
        $guruRoleIdBefore = $this->teacher->role_id;
        $guruPositionBefore = $this->teacher->position;

        $response = $this->actingAs($this->superAdmin)->post(route('settings.update'), [
            'school_name' => 'SMK PGRI KRAS',
            'curriculum_head_id' => $this->teacher->id,
            'curriculum_head_name' => 'Guru Kurikulum, S.Pd.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = SchoolSetting::getSetting(true);
        $this->assertEquals($this->teacher->id, $setting->curriculum_head_id);
        $this->assertEquals('Guru Kurikulum, S.Pd.', $setting->curriculum_head_name);

        // Role dasar dan posisi akun guru TIDAK BERUBAH
        $this->teacher->refresh();
        $this->assertEquals($guruRoleIdBefore, $this->teacher->role_id);
        $this->assertEquals($guruPositionBefore, $this->teacher->position);
        $this->assertNotEquals($this->wakaRole->id, $this->teacher->role_id);
    }

    public function test_super_admin_assigned_in_setting_payload_does_not_lose_super_admin_role(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('settings.update'), [
            'school_name' => 'SMK PGRI KRAS',
            'principal_id' => $this->superAdmin->id,
            'principal_name' => $this->superAdmin->name,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->superAdmin->refresh();
        $this->assertEquals($this->superAdminRole->id, $this->superAdmin->role_id);
        $this->assertEquals('administrator', $this->superAdmin->position);
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }

    public function test_updating_general_school_profile_settings_succeeds(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('settings.update'), [
            'school_name' => 'SMK PUSAT KEUNGGULAN',
            'school_npsn' => '20539123',
            'vision' => 'Visi Berkelanjutan 2030',
            'school_latitude' => -7.9559,
            'school_longitude' => 111.9725,
            'school_radius_meters' => 150,
            'geotag_attendance_enabled' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = SchoolSetting::getSetting(true);
        $this->assertEquals('SMK PUSAT KEUNGGULAN', $setting->school_name);
        $this->assertEquals('20539123', $setting->school_npsn);
        $this->assertEquals('Visi Berkelanjutan 2030', $setting->vision);
        $this->assertEquals(-7.9559, (float) $setting->school_latitude);
        $this->assertEquals(150, $setting->school_radius_meters);
        $this->assertTrue((bool) $setting->geotag_attendance_enabled);
    }
}
