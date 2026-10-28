<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkAssignmentConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;

    protected User $admin;

    protected Role $guruRole;

    protected Role $stafRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );

        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'description' => 'Super Admin']);
        $this->guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru', 'description' => 'Guru']);
        $this->stafRole = Role::firstOrCreate(['slug' => 'staf'], ['name' => 'Staf / Tendik', 'description' => 'Staf / Tendik']);
        Role::firstOrCreate(['slug' => 'laboran_toolman'], ['name' => 'Laboran / Toolman', 'description' => 'Laboran']);
        Role::firstOrCreate(['slug' => 'operator'], ['name' => 'Operator', 'description' => 'Operator']);
        Role::firstOrCreate(['slug' => 'bendahara'], ['name' => 'Bendahara', 'description' => 'Bendahara']);

        $this->admin = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_penugasan_gtk_is_single_door_and_menu_penugasan_pegawai_removed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('teacher-assignments.index', ['academic_year' => '2026/2027']));
        $response->assertOk();
        $response->assertSee('Penugasan GTK (Guru & Tenaga Kependidikan)');
        $response->assertSee('1. Mata Pelajaran');
        $response->assertSee('2. Wali Kelas &amp; BK', false);
        $response->assertSee('3. Struktural');
        $response->assertSee('4. Operasional');

        // Pastikan dropdown navigasi utama TIDAK lagi menampilkan menu terpisah 'Penugasan Pegawai'
        $response->assertDontSee('<i class="ti ti-id-badge-2 me-2"></i> Penugasan Pegawai', false);
        $response->assertDontSee('Penugasan Pegawai Lintas Modul', false);
    }

    public function test_alias_gtk_assignments_index_route_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('gtk-assignments.index', ['academic_year' => '2026/2027']));
        $response->assertOk();
        $response->assertSee('Penugasan GTK (Guru & Tenaga Kependidikan)');
    }

    public function test_tenaga_kependidikan_positions_accessible_in_operasional_tab(): void
    {
        $posToolman = StructuralPosition::firstOrCreate(
            ['code' => 'TOOLMAN_TKJ'],
            [
                'name' => 'Toolman TKJ dan AK',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Unit & Fasilitas',
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $posSatpam = StructuralPosition::firstOrCreate(
            ['code' => 'SATPAM'],
            [
                'name' => 'Satpam',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Ketertiban & Keamanan',
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->admin)->get(route('teacher-assignments.index', [
            'tab' => 'operasional',
            'academic_year' => '2026/2027',
        ]));

        $response->assertOk();
        $response->assertSee('Toolman TKJ dan AK');
        $response->assertSee('Satpam');
    }

    public function test_structural_leadership_positions_accessible_in_struktural_tab(): void
    {
        $posKs = StructuralPosition::firstOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'category' => 'Pimpinan',
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        $posWaka = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KUR'],
            [
                'name' => 'Waka Kurikulum',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'category' => 'Wakil Kepala Sekolah',
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->admin)->get(route('teacher-assignments.index', [
            'tab' => 'struktural',
            'academic_year' => '2026/2027',
        ]));

        $response->assertOk();
        $response->assertSee('Kepala Sekolah');
        $response->assertSee('Waka Kurikulum');
    }

    public function test_non_teacher_staff_can_be_assigned_to_operational_position(): void
    {
        $stafUser = User::factory()->create([
            'name' => 'Budi Staf Laboratorium',
            'role_id' => $this->stafRole->id,
            'position' => 'laboran',
            'is_active' => true,
        ]);

        $posLaboran = StructuralPosition::firstOrCreate(
            ['code' => 'LABORAN'],
            [
                'name' => 'Laboran Praktik / Komputer',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Unit & Fasilitas',
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $postData = [
            'teacher_id' => $stafUser->id,
            'structural_position_id' => $posLaboran->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-LAB-001/2026',
            'sk_date' => '2026-07-15',
            'notes' => 'Petugas penanggung jawab lab komputer',
        ];

        $response = $this->actingAs($this->admin)->post(route('teacher-assignments.store-operational'), $postData);
        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'operasional', 'academic_year' => '2026/2027']));

        $assignment = TeacherStructuralAssignment::where('teacher_id', $stafUser->id)
            ->where('structural_position_id', $posLaboran->id)
            ->where('academic_year', '2026/2027')
            ->first();

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->is_active);
        $this->assertEquals('SK-LAB-001/2026', $assignment->sk_number);

        // Verifikasi sinkronisasi role kontekstual ke user_role_assignments
        $roleAssignment = UserRoleAssignment::where('user_id', $stafUser->id)
            ->where('academic_year', '2026/2027')
            ->first();

        $this->assertNotNull($roleAssignment);
        $this->assertEquals('laboran_toolman', $roleAssignment->role->slug);
    }

    public function test_revocation_of_one_assignment_does_not_revoke_other_active_assignments(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        // Tugas 1: Operasional Operator
        $posOperator = StructuralPosition::firstOrCreate(
            ['code' => 'OPERATOR'],
            [
                'name' => 'Operator Sekolah / Dapodik',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Layanan & Sistem',
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $this->actingAs($this->admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $user->id,
            'structural_position_id' => $posOperator->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-OP-01',
        ]);

        // Tugas 2: Struktural Bendahara
        $posBendahara = StructuralPosition::firstOrCreate(
            ['code' => 'BENDAHARA'],
            [
                'name' => 'Bendahara Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'category' => 'Tata Kelola & Keuangan',
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        $this->actingAs($this->admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $user->id,
            'structural_position_id' => $posBendahara->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-BEND-01',
        ]);

        // Kedua role aktif
        $this->assertTrue($user->hasRole('operator', '2026/2027'));
        $this->assertTrue($user->hasRole('bendahara', '2026/2027'));

        // Cabut penugasan Operator
        $operatorAssignment = TeacherStructuralAssignment::where('teacher_id', $user->id)
            ->where('structural_position_id', $posOperator->id)
            ->first();

        $this->actingAs($this->admin)->delete(route('teacher-assignments.destroy-operational', $operatorAssignment));

        // Verifikasi: role operator dicabut, TETAPI role bendahara tetap aktif
        $user->unsetRelation('activeRoleAssignments');
        $this->assertFalse($user->hasRole('operator', '2026/2027'));
        $this->assertTrue($user->hasRole('bendahara', '2026/2027'));
    }
}
