<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BkReferral;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketIncident;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisciplinePiketBkIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $ayCurrent;

    protected AcademicYear $ayOld;

    protected Role $guruRole;

    protected Role $bkRole;

    protected Role $disiplinRole;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-28 10:00:00'); // Rabu

        $this->ayCurrent = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );

        $this->ayOld = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['semester' => 'Genap', 'is_active' => false, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
        );

        $this->guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru', 'description' => 'Guru']);
        $this->bkRole = Role::firstOrCreate(['slug' => 'guru_bk'], ['name' => 'Guru BK', 'description' => 'Guru BK']);
        $this->disiplinRole = Role::firstOrCreate(['slug' => 'tim_kedisiplinan'], ['name' => 'Tim Kedisiplinan', 'description' => 'Tim Kedisiplinan']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createStudent(string $academicYear = '2026/2027', ?User $homeroom = null): Student
    {
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKJ 1', 'academic_year' => $academicYear],
            [
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'is_active' => true,
                'homeroom_teacher_id' => $homeroom?->id,
            ]
        );

        if ($homeroom && $classroom->homeroom_teacher_id !== $homeroom->id) {
            $classroom->update(['homeroom_teacher_id' => $homeroom->id]);
        }

        return Student::create([
            'nis' => 'NIS'.rand(10000, 99999),
            'nisn' => 'NISN'.rand(10000, 99999),
            'name' => 'Siswa Pengujian '.rand(100, 999),
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);
    }

    public function test_discipline_team_member_authorized_via_master_penugasan_can_access_discipline_dashboard(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        $posDisiplin = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            [
                'name' => 'Tim Kedisiplinan dan Tata Tertib',
                'type' => 'operasional',
                'category' => 'operasional',
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $posDisiplin->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isDisciplineTeam('2026/2027'));
        $this->assertTrue($teacher->canManageDiscipline('2026/2027'));
        // Kedisiplinan TIDAK otomatis memiliki akses catatan rahasia BK
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));

        $response = $this->actingAs($teacher)->get(route('discipline.dashboard', ['academic_year' => '2026/2027']));
        $response->assertOk();
    }

    public function test_guru_bk_authorized_via_bk_assignment_can_access_bk_dashboard_and_confidential_notes(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $this->bkRole->id,
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ayCurrent->id,
            'status' => true,
        ]);

        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertTrue($teacher->canOperateBk('2026/2027'));
        $this->assertTrue($teacher->canAccessCounselingNotes('2026/2027'));

        // Guru BK murni tanpa penugasan kedisiplinan tidak otomatis memverifikasi sanksi kedisiplinan
        $this->assertFalse($teacher->canVerifyDiscipline('2026/2027'));

        $response = $this->actingAs($teacher)->get(route('bk.dashboard', ['academic_year' => '2026/2027']));
        $response->assertOk();
    }

    public function test_piket_officer_on_duty_today_can_record_incident_and_auto_refer_to_bk(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        // Wednesday = Rabu
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Rabu',
            'teacher_id' => $teacher->id,
            'role' => 'anggota',
            'duty_location' => 'Pos Gerbang & Meja Piket',
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isPiketOfficerToday('2026/2027'));
        $this->assertTrue($teacher->canRecordPiketIncident('2026/2027'));

        $student = $this->createStudent('2026/2027');

        $response = $this->actingAs($teacher)->post(route('piket.incidents.store'), [
            'incident_date' => '2026-10-28',
            'incident_time' => '08:30',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'location' => 'Koridor Belakang',
            'category' => 'kedisiplinan',
            'description' => 'Siswa kedapatan membolos jam pelajaran ke-2',
            'action_taken' => 'Diberi peringatan di meja piket dan diarahkan konseling',
            'follow_up_target' => 'rujuk_bk',
        ]);

        $response->assertRedirect(route('piket.incidents.index'));

        $incident = PiketIncident::where('student_id', $student->id)->latest()->first();
        $this->assertNotNull($incident);
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);

        // Verifikasi rujukan BK otomatis terbentuk
        $referral = BkReferral::where('piket_incident_id', $incident->id)->first();
        $this->assertNotNull($referral);
        $this->assertEquals('menunggu', $referral->status);
    }

    public function test_multi_duty_user_retains_other_duties_when_one_duty_revoked(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        // Penugasan 1: Guru BK
        $bkAssignment = UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $this->bkRole->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Penugasan 2: Tim Kedisiplinan
        $posDisiplin = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            ['name' => 'Tim Kedisiplinan', 'type' => 'operasional', 'category' => 'operasional', 'is_active' => true]
        );

        $disiplinAssignment = TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $posDisiplin->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // User memiliki dua kewenangan sekaligus
        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertTrue($teacher->isDisciplineTeam('2026/2027'));

        // Cabut penugasan kedisiplinan
        $disiplinAssignment->update(['is_active' => false]);
        $teacher->unsetRelation('structuralPositions');

        // Kewenangan kedisiplinan hilang, tetapi kewenangan BK tetap utuh aktif
        $this->assertFalse($teacher->isDisciplineTeam('2026/2027'));
        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
    }

    public function test_academic_year_isolation_prevents_stale_entitlements_in_new_year(): void
    {
        $teacher = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        $posDisiplin = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            ['name' => 'Tim Kedisiplinan', 'type' => 'operasional', 'category' => 'operasional', 'is_active' => true]
        );

        // Penugasan HANYA untuk tahun lalu (2025/2026)
        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $posDisiplin->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        // Jadwal piket HANYA untuk tahun lalu
        PiketSchedule::create([
            'academic_year' => '2025/2026',
            'semester' => 'Genap',
            'day' => 'Rabu',
            'teacher_id' => $teacher->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        // Pada tahun 2025/2026 memang aktif
        $this->assertTrue($teacher->isDisciplineTeam('2025/2026'));
        $this->assertTrue($teacher->isPiketOfficerToday('2025/2026'));

        // Namun pada tahun ajaran baru 2026/2027, hak akses TIDAK bocor
        $this->assertFalse($teacher->isDisciplineTeam('2026/2027'));
        $this->assertFalse($teacher->isPiketOfficerToday('2026/2027'));
        $this->assertFalse($teacher->canManageDiscipline('2026/2027'));

        $response = $this->actingAs($teacher)->get(route('discipline.dashboard', ['academic_year' => '2026/2027']));
        $response->assertForbidden();
    }

    public function test_unified_visitation_accessible_cross_functionally_with_confidentiality_guarded(): void
    {
        $waliKelas = User::factory()->create(['role_id' => $this->guruRole->id, 'is_active' => true]);
        $guruBk = User::factory()->create(['role_id' => $this->bkRole->id, 'is_active' => true]);
        $timDisiplin = User::factory()->create(['role_id' => $this->disiplinRole->id, 'is_active' => true]);
        $guruLain = User::factory()->create(['role_id' => $this->guruRole->id, 'is_active' => true]);

        $student = $this->createStudent('2026/2027', $waliKelas);

        // Wali kelas mengajukan visitasi, petugas pelaksana utama adalah Guru BK
        $visitation = StudentVisitation::create([
            'student_id' => $student->id,
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ayCurrent->id,
            'visit_type' => 'kunjungan_rumah',
            'source_type' => 'inisiatif_wali_kelas',
            'reason' => 'Siswa tidak hadir 4 hari berturut-turut tanpa kabar',
            'requested_by' => $waliKelas->id,
            'primary_officer_id' => $guruBk->id,
            'planned_date' => '2026-10-30',
            'status' => 'dijadwalkan',
            'confidential_notes' => 'Catatan sensitif kondisi keluarga siswa hanya untuk pihak berwenang',
        ]);

        // 1. Wali kelas siswa terkait berhak melihat
        $this->assertTrue($visitation->canUserView($waliKelas, '2026/2027'));

        // 2. Guru BK sebagai petugas pelaksana berhak melihat dan mengakses catatan rahasia
        $this->assertTrue($visitation->canUserView($guruBk, '2026/2027'));
        $this->assertTrue($visitation->canUserAccessConfidential($guruBk, '2026/2027'));

        // 3. Tim Kedisiplinan berhak melihat visitasi dalam pemantauan siswa
        $this->assertTrue($visitation->canUserView($timDisiplin, '2026/2027'));
        // Tetapi Tim Kedisiplinan yang bukan officer tidak berhak mengakses catatan rahasia BK
        $this->assertFalse($visitation->canUserAccessConfidential($timDisiplin, '2026/2027'));

        // 4. Guru luar yang tidak terkait ditolak
        $this->assertFalse($visitation->canUserView($guruLain, '2026/2027'));

        // Akses route show oleh Wali Kelas
        $responseWali = $this->actingAs($waliKelas)->get(route('visitations.show', $visitation));
        $responseWali->assertOk();

        // Akses route show oleh Guru BK
        $responseBk = $this->actingAs($guruBk)->get(route('visitations.show', $visitation));
        $responseBk->assertOk();

        // Akses route show oleh Guru Lain ditolak
        $responseLain = $this->actingAs($guruLain)->get(route('visitations.show', $visitation));
        $responseLain->assertForbidden();
    }
}
