<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\PiketIncident;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CounselingAndDisciplineAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function getOrCreateRole(string $slug, string $name, array $permissions = ['access_piket']): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name]);
        foreach ($permissions as $pSlug) {
            $perm = Permission::firstOrCreate(['slug' => $pSlug], ['name' => $pSlug, 'group' => 'Piket']);
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        return $role;
    }

    public function test_guru_bk_without_discipline_assignment_has_no_discipline_authority(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $bkSubject = Subject::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Bimbingan dan Konseling', 'category' => 'Umum', 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $bkSubject->id,
            'classroom_id' => null,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
        ]);

        $this->assertTrue($teacher->isCounselingBK());
        $this->assertTrue($teacher->canAccessCounselingNotes());

        // Must NOT have discipline authority
        $this->assertFalse($teacher->isDisciplineTeam());
        $this->assertFalse($teacher->canManageDiscipline());
    }

    public function test_guru_bk_with_discipline_assignment_has_both_authorities(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $bkSubject = Subject::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Bimbingan dan Konseling', 'category' => 'Umum', 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        // BK assignment
        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $bkSubject->id,
            'classroom_id' => null,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
        ]);

        // Discipline assignment via UserRoleAssignment
        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => '2026/2027',
        ]);

        $this->assertTrue($teacher->isCounselingBK());
        $this->assertTrue($teacher->canAccessCounselingNotes());

        $this->assertTrue($teacher->isDisciplineTeam());
        $this->assertTrue($teacher->canManageDiscipline());
    }

    public function test_discipline_team_member_cannot_access_counseling_notes(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => '2026/2027',
        ]);

        $this->assertTrue($teacher->isDisciplineTeam());
        $this->assertTrue($teacher->canManageDiscipline());

        // Must NOT have BK counseling authority
        $this->assertFalse($teacher->isCounselingBK());
        $this->assertFalse($teacher->canAccessCounselingNotes());
    }

    public function test_waka_kesiswaan_has_discipline_coordination_authority_without_automatic_counseling(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $wakaPos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KESISWAAN'],
            ['name' => 'Waka Kesiswaan', 'category' => 'Wakil Kepala Sekolah', 'order' => 4, 'is_active' => true]
        );

        $wakaUser = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $wakaUser->id,
            'structural_position_id' => $wakaPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertTrue($wakaUser->isWakaKesiswaan());
        // Jabatan struktural Waka Kesiswaan dipisahkan dari keanggotaan tim operasional kedisiplinan
        $this->assertFalse($wakaUser->isDisciplineTeam());
        // Namun tetap memiliki wewenang koordinasi kedisiplinan siswa
        $this->assertTrue($wakaUser->canManageDiscipline());

        // Waka Kesiswaan does NOT automatically become BK counselor
        $this->assertFalse($wakaUser->isCounselingBK());
        $this->assertFalse($wakaUser->canAccessCounselingNotes());
    }

    public function test_incident_reporting_and_referral_to_bk_intact(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $classroom = Classroom::firstOrCreate(
            ['name' => 'XII TKJ 1'],
            [
                'academic_year' => $ay->name,
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'is_active' => true,
            ]
        );

        $student = Student::firstOrCreate(
            ['nisn' => '1234567890'],
            ['name' => 'Budi Santoso', 'classroom_id' => $classroom->id, 'academic_year' => $ay->name, 'status' => 'aktif']
        );

        // 1. Report incident with follow_up_target = 'rujuk_bk'
        $response = $this->actingAs($admin)->post(route('piket.incidents.store'), [
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat dan perlu pembinaan motivasi',
            'action_taken' => 'Diberikan peringatan piket dan dirujuk',
            'follow_up_target' => 'rujuk_bk',
            'evidence_photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertRedirect(route('piket.incidents.index'));

        $incident = PiketIncident::where('student_id', $student->id)->latest()->first();
        $this->assertNotNull($incident);
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);
        $this->assertEquals('tercatat', $incident->status);
        $this->assertNotNull($incident->evidence_photo);

        // 2. Setup Guru BK (without discipline team assignment)
        $bkSubject = Subject::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Bimbingan dan Konseling', 'category' => 'Umum', 'is_active' => true]
        );
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        TeacherSubjectAssignment::create([
            'teacher_id' => $guruBk->id,
            'subject_id' => $bkSubject->id,
            'academic_year' => $ay->name,
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
        ]);

        $this->assertTrue($guruBk->isCounselingBK());
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);

        // Guru BK can view photo for incident referred to BK
        $photoResp = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incident));
        $photoResp->assertOk();

        // 3. For another incident referred to kesiswaan (not to BK)
        $incidentKesiswaan = PiketIncident::create([
            'piket_journal_id' => $incident->piket_journal_id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '09:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'perkelahian',
            'description' => 'Insiden perkelahian',
            'action_taken' => 'Diamankan',
            'follow_up_target' => 'rujuk_kesiswaan',
            'evidence_photo' => $incident->evidence_photo,
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Guru BK cannot view photo of incident that is NOT referred to BK
        $photoRespForbidden = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incidentKesiswaan));
        $photoRespForbidden->assertForbidden();

        // But Tim Kedisiplinan can view it
        $tatibUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => $ay->name,
        ]);

        $photoRespTatib = $this->actingAs($tatibUser)->get(route('piket.incidents.photo', $incidentKesiswaan));
        $photoRespTatib->assertOk();
    }

    public function test_guru_bk_via_structural_position_does_not_become_discipline_team_member(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $bkPos = StructuralPosition::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Koordinator Bimbingan dan Konseling', 'category' => 'Unit Kerja', 'order' => 8, 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $bkPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isCounselingBK());
        $this->assertTrue($teacher->canAccessCounselingNotes());

        // Jabatan struktural BK TIDAK memberi keanggotaan Tim Kedisiplinan
        $this->assertFalse($teacher->isDisciplineTeam());
        $this->assertFalse($teacher->canManageDiscipline());
    }

    public function test_guru_bk_via_position_field_does_not_become_discipline_team_member(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Guru Bimbingan Konseling (BK)',
        ]);

        $this->assertTrue($teacher->isCounselingBK());
        $this->assertTrue($teacher->canAccessCounselingNotes());

        // Field position bertema BK tidak otomatis menjadikan anggota Tim Kedisiplinan
        $this->assertFalse($teacher->isDisciplineTeam());
        $this->assertFalse($teacher->canManageDiscipline());
    }

    public function test_guru_bk_via_role_bk_does_not_become_discipline_team_member(): void
    {
        $bkRole = $this->getOrCreateRole('bk', 'Guru BK');

        $teacher = User::factory()->create([
            'role_id' => $bkRole->id,
            'position' => 'guru',
        ]);

        $this->assertTrue($teacher->isCounselingBK());
        $this->assertTrue($teacher->canAccessCounselingNotes());

        // Role BK tidak otomatis menjadikan anggota Tim Kedisiplinan
        $this->assertFalse($teacher->isDisciplineTeam());
        $this->assertFalse($teacher->canManageDiscipline());
    }

    public function test_discipline_team_member_via_structural_position(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $tatibPos = StructuralPosition::firstOrCreate(
            ['code' => 'TATIB'],
            ['name' => 'Koordinator Tata Tertib & Kedisiplinan Siswa', 'category' => 'Unit Kerja', 'order' => 9, 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $tatibPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isDisciplineTeam());
        $this->assertTrue($teacher->canManageDiscipline());

        // Anggota Tim Kedisiplinan tidak otomatis menjadi Guru BK
        $this->assertFalse($teacher->isCounselingBK());
        $this->assertFalse($teacher->canAccessCounselingNotes());
    }

    public function test_other_assignments_remain_unaffected(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        $classroom = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => $ay->name,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        // Penugasan wali kelas tetap valid
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->isHomeroomTeacher());

        // Bukan BK dan bukan Tim Kedisiplinan
        $this->assertFalse($teacher->isCounselingBK());
        $this->assertFalse($teacher->isDisciplineTeam());
    }
}
