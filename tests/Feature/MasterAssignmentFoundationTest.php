<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAssignmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );
    }

    private function getOrCreateRole(string $slug, string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name]);
        foreach ($permissions as $pSlug) {
            $perm = Permission::firstOrCreate(['slug' => $pSlug], ['name' => $pSlug, 'group' => 'Test']);
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        return $role;
    }

    private function createAdmin(): User
    {
        $role = $this->getOrCreateRole('super_admin', 'Super Admin', ['manage_master_data']);

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => 'administrator',
        ]);
    }

    private function createTeacher(): User
    {
        $role = $this->getOrCreateRole('guru', 'Guru', []);

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => 'guru',
        ]);
    }

    public function test_user_with_manage_master_data_permission_can_access_master_penugasan(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('structural-positions.index'));

        $response->assertStatus(200);
        $response->assertViewIs('master.structural_positions.index');
        $response->assertSee('Master Penugasan GTK');
    }

    public function test_user_without_permission_cannot_access_master_penugasan(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAs($teacher)->get(route('structural-positions.index'));

        $response->assertStatus(403);
    }

    public function test_can_create_new_structural_and_operational_duty_positions(): void
    {
        $admin = $this->createAdmin();

        // 1. Create Struktural Position
        $payloadStruktural = [
            'name' => 'Wakil Manajemen Mutu',
            'code' => 'WMM',
            'type' => 'struktural',
            'default_tenure_years' => 2,
            'scope_type' => 'school',
            'category' => 'Manajemen',
            'allows_multiple' => '0',
            'order' => 10,
            'description' => 'Mengkoordinir sistem penjaminan mutu sekolah',
            'is_active' => '1',
        ];

        $res1 = $this->actingAs($admin)->post(route('structural-positions.store'), $payloadStruktural);
        $res1->assertSessionHas('success');

        $this->assertDatabaseHas('structural_positions', [
            'name' => 'Wakil Manajemen Mutu',
            'code' => 'WMM',
            'type' => 'struktural',
            'default_tenure_years' => 2,
            'scope_type' => 'school',
            'allows_multiple' => 0,
            'is_active' => 1,
        ]);

        // 2. Create Operasional Duty Position
        $payloadOperasional = [
            'name' => 'Tim Adiwiyata & Lingkungan Hidup',
            'code' => 'TIM_ADIWIYATA',
            'type' => 'operasional',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
            'category' => 'Program Khusus',
            'allows_multiple' => '1',
            'order' => 20,
            'description' => 'Mengelola program sekolah adiwiyata',
            'is_active' => '1',
        ];

        $res2 = $this->actingAs($admin)->post(route('structural-positions.store'), $payloadOperasional);
        $res2->assertSessionHas('success');

        $this->assertDatabaseHas('structural_positions', [
            'name' => 'Tim Adiwiyata & Lingkungan Hidup',
            'code' => 'TIM_ADIWIYATA',
            'type' => 'operasional',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
            'allows_multiple' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_validates_required_fields_and_duplicate_code(): void
    {
        $admin = $this->createAdmin();

        StructuralPosition::create([
            'name' => 'Kepala Laboratorium',
            'code' => 'KA_LAB',
            'type' => 'struktural',
            'default_tenure_years' => 2,
            'scope_type' => 'unit',
            'is_active' => true,
        ]);

        // Missing required fields
        $resMissing = $this->actingAs($admin)->post(route('structural-positions.store'), []);
        $resMissing->assertSessionHasErrors(['name', 'code', 'type', 'default_tenure_years', 'scope_type']);

        // Duplicate code
        $resDup = $this->actingAs($admin)->post(route('structural-positions.store'), [
            'name' => 'Kepala Lab Komputer 2',
            'code' => 'KA_LAB',
            'type' => 'struktural',
            'default_tenure_years' => 2,
            'scope_type' => 'unit',
        ]);
        $resDup->assertSessionHasErrors(['code']);
    }

    public function test_validates_default_tenure_range_and_valid_type(): void
    {
        $admin = $this->createAdmin();

        // Invalid tenure (6 years > max 5)
        $resTenure = $this->actingAs($admin)->post(route('structural-positions.store'), [
            'name' => 'Tugas Uji',
            'code' => 'TUGAS_UJI',
            'type' => 'operasional',
            'default_tenure_years' => 6,
            'scope_type' => 'school',
        ]);
        $resTenure->assertSessionHasErrors(['default_tenure_years']);

        // Invalid type
        $resType = $this->actingAs($admin)->post(route('structural-positions.store'), [
            'name' => 'Tugas Uji 2',
            'code' => 'TUGAS_UJI_2',
            'type' => 'invalid_type',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
        ]);
        $resType->assertSessionHasErrors(['type']);
    }

    public function test_can_update_duty_position(): void
    {
        $admin = $this->createAdmin();

        $position = StructuralPosition::create([
            'name' => 'Staf Sarpras',
            'code' => 'STAF_SARPRAS',
            'type' => 'operasional',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
            'allows_multiple' => true,
            'is_active' => true,
        ]);

        $updatePayload = [
            'name' => 'Staf Sarana dan Prasarana',
            'code' => 'STAF_SARPRAS',
            'type' => 'operasional',
            'default_tenure_years' => 2,
            'scope_type' => 'unit',
            'category' => 'Sarpras',
            'allows_multiple' => '1',
            'order' => 5,
            'description' => 'Deskripsi diperbarui',
            'is_active' => '1',
        ];

        $res = $this->actingAs($admin)->put(route('structural-positions.update', $position), $updatePayload);
        $res->assertSessionHas('success');

        $this->assertDatabaseHas('structural_positions', [
            'id' => $position->id,
            'name' => 'Staf Sarana dan Prasarana',
            'default_tenure_years' => 2,
            'scope_type' => 'unit',
            'description' => 'Deskripsi diperbarui',
        ]);
    }

    public function test_cannot_change_code_if_position_has_historical_assignments(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $position = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_TEST'],
            [
                'name' => 'Waka Pengujian',
                'type' => 'struktural',
                'default_tenure_years' => 2,
                'scope_type' => 'school',
                'is_active' => true,
            ]
        );

        // Attach an assignment to simulate historical duty
        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-001/2026',
            'is_active' => true,
        ]);

        // Try changing the code to WAKA_HUBIN
        $res = $this->actingAs($admin)->put(route('structural-positions.update', $position), [
            'name' => 'Waka Pengujian Update',
            'code' => 'WAKA_HUBIN',
            'type' => 'struktural',
            'default_tenure_years' => 2,
            'scope_type' => 'school',
        ]);

        $res->assertSessionHas('error');
        $this->assertDatabaseHas('structural_positions', [
            'id' => $position->id,
            'code' => 'WAKA_TEST', // Code remains unchanged
        ]);
    }

    public function test_cannot_delete_duty_position_with_assignment_history(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $position = StructuralPosition::create([
            'name' => 'Ketua Jurusan TKR',
            'code' => 'KAJUR_TKR',
            'type' => 'struktural',
            'default_tenure_years' => 3,
            'scope_type' => 'department',
            'is_active' => true,
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $res = $this->actingAs($admin)->delete(route('structural-positions.destroy', $position));
        $res->assertSessionHas('error');

        $this->assertDatabaseHas('structural_positions', [
            'id' => $position->id,
        ]);
    }

    public function test_can_delete_unused_duty_position(): void
    {
        $admin = $this->createAdmin();

        $position = StructuralPosition::create([
            'name' => 'Tugas Sementara',
            'code' => 'TEMP_DUTY',
            'type' => 'operasional',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
            'is_active' => true,
        ]);

        $res = $this->actingAs($admin)->delete(route('structural-positions.destroy', $position));
        $res->assertSessionHas('success');

        $this->assertDatabaseMissing('structural_positions', [
            'id' => $position->id,
        ]);
    }

    public function test_can_toggle_active_status_of_duty_position(): void
    {
        $admin = $this->createAdmin();

        $position = StructuralPosition::create([
            'name' => 'Tim Pengembang Kurikulum',
            'code' => 'TPK',
            'type' => 'operasional',
            'default_tenure_years' => 1,
            'scope_type' => 'school',
            'is_active' => true,
        ]);

        // Toggle to inactive
        $res1 = $this->actingAs($admin)->post(route('structural-positions.toggle', $position));
        $res1->assertSessionHas('success');
        $this->assertFalse((bool) $position->fresh()->is_active);

        // Toggle back to active
        $res2 = $this->actingAs($admin)->post(route('structural-positions.toggle', $position));
        $res2->assertSessionHas('success');
        $this->assertTrue((bool) $position->fresh()->is_active);
    }

    public function test_legacy_structural_positions_compatibility(): void
    {
        // Posisi Kepala Sekolah (KS) sudah di-seed oleh migrasi awal
        $legacy = StructuralPosition::where('code', 'KS')->first();
        $this->assertNotNull($legacy);

        $this->assertEquals('struktural', $legacy->type);
        $this->assertEquals(1, $legacy->default_tenure_years);
        $this->assertEquals('school', $legacy->scope_type);
        $this->assertEquals('Struktural', $legacy->type_label);
        $this->assertEquals('Sekolah (Global)', $legacy->scope_label);
    }
}
