<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TeacherSupervisorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorAssignmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $wakaKurikulum;

    private Role $guruRole;

    private Role $wakaRole;

    private Role $supervisorRole;

    private AcademicYear $ay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru', 'description' => 'Guru']);
        $this->wakaRole = Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum', 'description' => 'Waka Kurikulum']);
        $this->supervisorRole = Role::firstOrCreate(['slug' => 'supervisor'], ['name' => 'Supervisor', 'description' => 'Supervisor']);

        $permManageMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master', 'group' => 'Master']);
        if (! $this->wakaRole->permissions()->where('permissions.id', $permManageMaster->id)->exists()) {
            $this->wakaRole->permissions()->attach($permManageMaster->id);
        }

        $this->ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $this->wakaKurikulum = User::factory()->create([
            'role_id' => $this->wakaRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);
    }

    public function test_guru_assigned_as_supervisor_preserves_role_id_and_position(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaKurikulum)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Senior Pembelajaran',
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => '2026/2027']));

        $teacher->refresh();

        // 1. Role ID must remain 'guru' (NO alteration to primary role)
        $this->assertEquals($this->guruRole->id, $teacher->role_id);
        $this->assertEquals('guru', $teacher->role->slug);

        // 2. Position must remain unchanged
        $this->assertEquals('guru', $teacher->position);

        // 3. Supervisor assignment record exists
        $this->assertDatabaseHas('teacher_supervisor_assignments', [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Senior Pembelajaran',
            'is_active' => true,
        ]);

        // 4. Authority check passes for 2026/2027
        $this->assertTrue($teacher->isSupervisor('2026/2027'));
    }

    public function test_revoking_supervisor_preserves_role_id_and_position(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        // Assign first
        $this->actingAs($this->wakaKurikulum)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Senior Pembelajaran',
        ]);

        // Revoke
        $response = $this->actingAs($this->wakaKurikulum)->delete(route('teacher-assignments.remove-supervisor', [
            'user' => $teacher->id,
            'academic_year' => '2026/2027',
        ]));

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => '2026/2027']));

        $teacher->refresh();

        // Role ID and position still unchanged
        $this->assertEquals($this->guruRole->id, $teacher->role_id);
        $this->assertEquals('guru', $teacher->role->slug);
        $this->assertEquals('guru', $teacher->position);

        // Assignment removed
        $this->assertDatabaseMissing('teacher_supervisor_assignments', [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
        ]);

        // Authority check returns false
        $this->assertFalse($teacher->isSupervisor('2026/2027'));
    }

    public function test_guru_with_multiple_duties_preserves_all_duties_when_assigned_and_revoked(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        // 1. Homeroom duty
        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);
        $classroom = Classroom::create([
            'name' => 'XII TKJ 1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // 2. Subject teaching duty
        $subject = Subject::firstOrCreate(['code' => 'PROG'], ['name' => 'Pemrograman Dasar', 'category' => 'Kejuruan', 'is_active' => true]);
        $subjectAssignment = TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 4,
        ]);

        // 3. Structural duty (e.g. Kaprogli)
        $structuralPos = StructuralPosition::firstOrCreate(
            ['code' => 'KAPRO_TKJ'],
            ['name' => 'Kaprogli TKJ', 'category' => 'Kepala Program Keahlian', 'order' => 8, 'is_active' => true]
        );
        $structuralAssignment = TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $structuralPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Pre-assertion: Verify initial duties
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->subjectAssignments()->where('id', $subjectAssignment->id)->exists());
        $this->assertTrue($teacher->structuralPositions()->where('structural_positions.id', $structuralPos->id)->exists());

        // Assign as supervisor
        $this->actingAs($this->wakaKurikulum)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor TKJ',
        ]);

        $teacher->refresh();

        // Verify primary role and position unchanged
        $this->assertEquals($this->guruRole->id, $teacher->role_id);
        $this->assertEquals('guru', $teacher->position);

        // Verify all other duties remain 100% intact
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->subjectAssignments()->where('id', $subjectAssignment->id)->exists());
        $this->assertTrue($teacher->structuralPositions()->where('structural_positions.id', $structuralPos->id)->exists());
        $this->assertTrue($teacher->isSupervisor('2026/2027'));

        // Revoke supervisor assignment
        $this->actingAs($this->wakaKurikulum)->delete(route('teacher-assignments.remove-supervisor', [
            'user' => $teacher->id,
            'academic_year' => '2026/2027',
        ]));

        $teacher->refresh();

        // Verify primary role and position STILL unchanged
        $this->assertEquals($this->guruRole->id, $teacher->role_id);
        $this->assertEquals('guru', $teacher->position);

        // Verify all other duties STILL intact after revoking supervisor
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->subjectAssignments()->where('id', $subjectAssignment->id)->exists());
        $this->assertTrue($teacher->structuralPositions()->where('structural_positions.id', $structuralPos->id)->exists());
        $this->assertFalse($teacher->isSupervisor('2026/2027'));
    }

    public function test_inactive_supervisor_assignment_does_not_grant_supervisor_authority(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        // Inactive assignment for 2026/2027
        TeacherSupervisorAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Nonaktif',
            'is_active' => false,
        ]);

        // Must return false because assignment is inactive
        $this->assertFalse($teacher->isSupervisor('2026/2027'));
        $this->assertFalse($teacher->isSupervisor());

        // Now activate the assignment
        TeacherSupervisorAssignment::where('teacher_id', $teacher->id)
            ->where('academic_year', '2026/2027')
            ->update(['is_active' => true]);

        $teacher->refresh();
        $this->assertTrue($teacher->isSupervisor('2026/2027'));
        $this->assertTrue($teacher->isSupervisor());

        // But false for another year
        $this->assertFalse($teacher->isSupervisor('2025/2026'));
    }

    public function test_existing_supervisor_listing_and_scope_by_academic_year(): void
    {
        AcademicYear::firstOrCreate(['name' => '2025/2026'], ['semester' => 'Genap', 'is_active' => false]);

        $teacherA = User::factory()->create([
            'name' => 'Guru A',
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $teacherB = User::factory()->create([
            'name' => 'Guru B',
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        // Assign Teacher A to 2025/2026
        $this->actingAs($this->wakaKurikulum)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacherA->id,
            'academic_year' => '2025/2026',
            'position_title' => 'Supervisor Tim 25/26',
        ]);

        // Assign Teacher B to 2026/2027
        $this->actingAs($this->wakaKurikulum)->post(route('teacher-assignments.assign-supervisor'), [
            'teacher_id' => $teacherB->id,
            'academic_year' => '2026/2027',
            'position_title' => 'Supervisor Tim 26/27',
        ]);

        // Check 2025/2026 index
        $resp25 = $this->actingAs($this->wakaKurikulum)->get(route('teacher-assignments.index', [
            'tab' => 'supervisor',
            'academic_year' => '2025/2026',
        ]));
        $resp25->assertOk();
        $supervisors25 = $resp25->viewData('supervisors');
        $this->assertTrue($supervisors25->contains('id', $teacherA->id));
        $this->assertFalse($supervisors25->contains('id', $teacherB->id));

        // Check 2026/2027 index
        $resp26 = $this->actingAs($this->wakaKurikulum)->get(route('teacher-assignments.index', [
            'tab' => 'supervisor',
            'academic_year' => '2026/2027',
        ]));
        $resp26->assertOk();
        $supervisors26 = $resp26->viewData('supervisors');
        $this->assertTrue($supervisors26->contains('id', $teacherB->id));
        $this->assertFalse($supervisors26->contains('id', $teacherA->id));
    }
}
