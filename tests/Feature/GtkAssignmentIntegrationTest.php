<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkAssignmentIntegrationTest extends TestCase
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

    private function createTeacher(string $name = 'Guru Test'): User
    {
        $role = $this->getOrCreateRole('guru', 'Guru', []);

        return User::factory()->create([
            'name' => $name,
            'role_id' => $role->id,
            'position' => 'guru',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_gtk_assignments_index_with_four_groups(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('gtk-assignments.index'));

        $response->assertStatus(200);
        $response->assertViewIs('master.assignments.index');
        $response->assertSee('Penugasan GTK');
        $response->assertSee('1. Mata Pelajaran');
        $response->assertSee('2. Wali Kelas &amp; BK', false);
        $response->assertSee('3. Struktural');
        $response->assertSee('4. Operasional');
    }

    public function test_structural_dropdown_only_contains_active_structural_positions(): void
    {
        $admin = $this->createAdmin();

        $activeStructural = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_TEST_STRUK'],
            [
                'name' => 'Waka Bidang Khusus',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'is_active' => true,
                'order' => 50,
            ]
        );

        $inactiveStructural = StructuralPosition::firstOrCreate(
            ['code' => 'STRUK_NONAKTIF'],
            [
                'name' => 'Struktural Nonaktif',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'is_active' => false,
                'order' => 51,
            ]
        );

        $activeOperational = StructuralPosition::firstOrCreate(
            ['code' => 'OP_ACTIVE_1'],
            [
                'name' => 'Operator Data Pokok',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
                'order' => 52,
            ]
        );

        $response = $this->actingAs($admin)->get(route('teacher-assignments.index', ['tab' => 'struktural']));

        $response->assertStatus(200);
        $choices = $response->viewData('structuralPositionChoices');

        $this->assertTrue($choices->contains('id', $activeStructural->id));
        $this->assertFalse($choices->contains('id', $inactiveStructural->id));
        $this->assertFalse($choices->contains('id', $activeOperational->id));
    }

    public function test_cannot_assign_operational_or_inactive_position_via_structural_endpoint(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $opPosition = StructuralPosition::firstOrCreate(
            ['code' => 'OP_DISC_TEAM'],
            [
                'name' => 'Tim Ketertiban',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $opPosition->id,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('teacher_structural_assignments', [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $opPosition->id,
        ]);
    }

    public function test_structural_turnover_deactivates_old_incumbent_and_syncs_contextual_role(): void
    {
        $admin = $this->createAdmin();
        $teacher1 = $this->createTeacher('Pejabat Lama');
        $teacher2 = $this->createTeacher('Pejabat Baru');

        $this->getOrCreateRole('kepala_sekolah', 'Kepala Sekolah');

        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        // Assign teacher 1
        $this->actingAs($admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacher1->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/001/2026',
        ]);

        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacher1->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacher1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Turnover: Assign teacher 2 to the same single-holder position
        $this->actingAs($admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacher2->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/002/2026',
        ]);

        // Teacher 1 assignment is deactivated, preserving history
        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacher1->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'is_active' => false,
        ]);

        // Teacher 1 contextual role revoked
        $ksRole = Role::where('slug', 'kepala_sekolah')->first();
        $this->assertDatabaseMissing('user_role_assignments', [
            'user_id' => $teacher1->id,
            'role_id' => $ksRole->id,
            'academic_year' => '2026/2027',
        ]);

        // Teacher 2 assignment is active with synced role
        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacher2->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacher2->id,
            'role_id' => $ksRole->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);
    }

    public function test_operational_dropdown_only_contains_active_operational_positions(): void
    {
        $admin = $this->createAdmin();

        $activeOp = StructuralPosition::firstOrCreate(
            ['code' => 'OP_ACTIVE_TEST'],
            [
                'name' => 'Tim Kebersihan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $inactiveOp = StructuralPosition::firstOrCreate(
            ['code' => 'OP_INACTIVE_TEST'],
            [
                'name' => 'Tim Nonaktif',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => false,
            ]
        );

        $response = $this->actingAs($admin)->get(route('teacher-assignments.index', ['tab' => 'operasional']));

        $response->assertStatus(200);
        $choices = $response->viewData('operationalPositionChoices');

        $this->assertTrue($choices->contains('id', $activeOp->id));
        $this->assertFalse($choices->contains('id', $inactiveOp->id));
    }

    public function test_cannot_assign_structural_position_via_operational_endpoint(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $strukPosition = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KUR'],
            [
                'name' => 'Waka Kurikulum',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $strukPosition->id,
            'academic_year' => '2026/2027',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_operational_multi_holder_and_role_sync(): void
    {
        $admin = $this->createAdmin();
        $teacherA = $this->createTeacher('Guru Disiplin A');
        $teacherB = $this->createTeacher('Guru Disiplin B');

        $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');
        $this->getOrCreateRole('guru_bk', 'Guru BK');

        $opPos = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            [
                'name' => 'Tim Kedisiplinan Sekolah',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        // Assign teacher A
        $this->actingAs($admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $teacherA->id,
            'structural_position_id' => $opPos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/DISC/01',
        ]);

        // Assign teacher B (jamak / multi-holder: both active)
        $this->actingAs($admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $teacherB->id,
            'structural_position_id' => $opPos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/DISC/02',
        ]);

        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacherA->id,
            'structural_position_id' => $opPos->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('teacher_structural_assignments', [
            'teacher_id' => $teacherB->id,
            'structural_position_id' => $opPos->id,
            'is_active' => true,
        ]);

        // Check contextual role synchronization
        $discRole = Role::where('slug', 'tim_kedisiplinan')->first();
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacherA->id,
            'role_id' => $discRole->id,
            'status' => true,
        ]);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacherB->id,
            'role_id' => $discRole->id,
            'status' => true,
        ]);

        // Verification of methods and role isolation
        $this->assertTrue($teacherA->isDisciplineTeam('2026/2027'));
        $this->assertTrue($teacherB->isDisciplineTeam('2026/2027'));
        $this->assertFalse($teacherA->isCounselingBK('2026/2027'));
        $this->assertFalse($teacherB->isCounselingBK('2026/2027'));
    }

    public function test_revoking_operational_duty_removes_contextual_role(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher('Petugas Perpus');

        $this->getOrCreateRole('petugas_perpustakaan', 'Petugas Perpustakaan');

        $opPos = StructuralPosition::firstOrCreate(
            ['code' => 'PETUGAS_PERPUS'],
            [
                'name' => 'Petugas Perpustakaan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        $this->actingAs($admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $opPos->id,
            'academic_year' => '2026/2027',
        ]);

        $assignment = TeacherStructuralAssignment::where('teacher_id', $teacher->id)
            ->where('structural_position_id', $opPos->id)
            ->first();

        $this->assertNotNull($assignment);

        // Revoke
        $this->actingAs($admin)->delete(route('teacher-assignments.destroy-operational', $assignment));

        $this->assertDatabaseMissing('teacher_structural_assignments', [
            'id' => $assignment->id,
        ]);

        $perpusRole = Role::where('slug', 'petugas_perpustakaan')->first();
        $this->assertDatabaseMissing('user_role_assignments', [
            'user_id' => $teacher->id,
            'role_id' => $perpusRole->id,
            'academic_year' => '2026/2027',
        ]);
    }

    public function test_bk_assignment_supports_multiple_classrooms_and_stays_isolated_from_discipline(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher('Guru BK Ibu Siti');

        $this->getOrCreateRole('guru_bk', 'Guru BK');
        $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['name' => 'X', 'level' => '10', 'order' => 1]);

        $cls1 = Classroom::create([
            'name' => 'X TKR 1',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $cls2 = Classroom::create([
            'name' => 'X TKR 2',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Assign to 2 classrooms simultaneously
        $response = $this->actingAs($admin)->post(route('teacher-assignments.store-bk'), [
            'teacher_id' => $teacher->id,
            'classroom_ids' => [$cls1->id, $cls2->id],
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/BK/2026',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => '2026/2027']));

        $bkRole = Role::where('slug', 'guru_bk')->first();

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacher->id,
            'role_id' => $bkRole->id,
            'context_type' => Classroom::class,
            'context_id' => $cls1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacher->id,
            'role_id' => $bkRole->id,
            'context_type' => Classroom::class,
            'context_id' => $cls2->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Complete isolation check
        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->isDisciplineTeam('2026/2027'));

        // Revoke one classroom
        $asgn1 = UserRoleAssignment::where('user_id', $teacher->id)
            ->where('context_id', $cls1->id)
            ->first();

        $this->actingAs($admin)->delete(route('teacher-assignments.destroy-bk', $asgn1));

        $this->assertDatabaseMissing('user_role_assignments', [
            'id' => $asgn1->id,
        ]);

        // Still active on classroom 2
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $teacher->id,
            'context_id' => $cls2->id,
        ]);
        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
    }

    public function test_unauthorized_user_cannot_manage_gtk_assignments(): void
    {
        $teacher = $this->createTeacher('Guru Tanpa Hak Akses');
        $targetTeacher = $this->createTeacher('Target');

        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'KS'],
            ['name' => 'Kepala Sekolah', 'type' => 'struktural', 'allows_multiple' => false, 'is_active' => true]
        );

        $response = $this->actingAs($teacher)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $targetTeacher->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
        ]);

        $response->assertStatus(403);
    }
}
