<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearPiketAndHomeroomPermitTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $operationalYear;

    protected AcademicYear $archiveYear;

    protected User $superAdmin;

    protected User $homeroomTeacher;

    protected User $otherTeacher;

    protected Classroom $operationalClassroom;

    protected Classroom $archiveClassroom;

    protected Student $operationalStudent;

    protected Student $otherClassStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->operationalYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'semester' => 'Ganjil',
                'is_active' => true,
                'start_date' => '2026-07-15',
                'end_date' => '2027-06-30',
            ]
        );

        $this->archiveYear = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            [
                'semester' => 'Genap',
                'is_active' => false,
                'start_date' => '2024-07-15',
                'end_date' => '2025-06-30',
            ]
        );

        $setting = SchoolSetting::first() ?? new SchoolSetting;
        $setting->academic_year = '2026/2027';
        $setting->semester = 'Ganjil';
        $setting->save();

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $this->superAdmin = User::factory()->create([
            'role_id' => $superAdminRole?->id,
            'is_active' => true,
        ]);

        $guruRole = Role::where('slug', 'guru')->first();
        $this->homeroomTeacher = User::factory()->create([
            'name' => 'Wali Kelas 1',
            'role_id' => $guruRole?->id,
            'is_active' => true,
        ]);

        $this->otherTeacher = User::factory()->create([
            'name' => 'Guru Lain',
            'role_id' => $guruRole?->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $this->operationalClassroom = Classroom::create([
            'name' => 'XII TKJ 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->operationalYear->id,
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $this->homeroomTeacher->id,
            'is_active' => true,
        ]);

        $this->archiveClassroom = Classroom::create([
            'name' => 'XII TKJ 1 (24/25)',
            'academic_year' => '2024/2025',
            'academic_year_id' => $this->archiveYear->id,
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $this->homeroomTeacher->id,
            'is_active' => false,
        ]);

        $this->operationalStudent = Student::create([
            'name' => 'Budi Santoso',
            'gender' => 'L',
            'classroom_id' => $this->operationalClassroom->id,
            'is_active' => true,
        ]);

        $otherClass = Classroom::create([
            'name' => 'XII TKJ 2',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->operationalYear->id,
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $this->otherTeacher->id,
            'is_active' => true,
        ]);

        $this->otherClassStudent = Student::create([
            'name' => 'Siti Aminah',
            'gender' => 'P',
            'classroom_id' => $otherClass->id,
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 1: Sesi arsip di UI tidak mencemari form pembuatan izin wali kelas (create).
     */
    public function test_archive_session_does_not_contaminate_homeroom_permit_create_form(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('attendance.homeroom-permits.create'));

        $response->assertStatus(200);
        $response->assertSee('XII TKJ 1');
        $response->assertDontSee('XII TKJ 1 (24/25)');
    }

    /**
     * Skenario 2: Wali kelas dilarang mencatat izin siswa di luar rombel perwaliannya (store abort 403).
     */
    public function test_homeroom_teacher_cannot_record_permit_for_students_outside_their_classroom(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $this->operationalClassroom->id,
            'student_id' => $this->otherClassStudent->id, // Siswa dari kelas lain
            'permit_category' => 'izin',
            'permit_date' => now()->toDateString(),
            'reason' => 'Ada keperluan keluarga',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Skenario 3: Form izin siswa perwalian berhasil dicatat untuk siswa rombel dan menggunakan tahun operasional.
     */
    public function test_homeroom_teacher_can_record_permit_and_journal_uses_operational_year(): void
    {
        $todayStr = now()->toDateString();

        $response = $this->actingAs($this->homeroomTeacher)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('attendance.homeroom-permits.store'), [
                'classroom_id' => $this->operationalClassroom->id,
                'student_id' => $this->operationalStudent->id,
                'permit_category' => 'sakit',
                'permit_date' => $todayStr,
                'reason' => 'Demam tinggi',
            ]);

        $response->assertRedirect(route('attendance.homeroom-permits.index'));

        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $this->operationalStudent->id,
            'classroom_id' => $this->operationalClassroom->id,
            'permit_category' => 'sakit',
        ]);

        $permit = PiketStudentPermit::where('student_id', $this->operationalStudent->id)->first();
        $this->assertNotNull($permit);

        // Jurnal piket yang dibuat harus tetap menggunakan tahun ajaran operasional 2026/2027
        $journal = $permit->journal;
        $this->assertNotNull($journal);
        $this->assertEquals('2026/2027', $journal->academic_year);
    }

    /**
     * Skenario 4: Form pembuatan catatan insiden dan surat izin piket memuat kelas operasional.
     */
    public function test_piket_incident_and_permit_create_load_operational_classrooms(): void
    {
        // 1. Incidents create
        $incidentCreate = $this->actingAs($this->superAdmin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('piket.incidents.create'));

        $incidentCreate->assertStatus(200);
        $incidentCreate->assertSee('XII TKJ 1');
        $incidentCreate->assertDontSee('XII TKJ 1 (24/25)');

        // 2. Permits create
        $permitCreate = $this->actingAs($this->superAdmin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('piket.permits.create'));

        $permitCreate->assertStatus(200);
        $permitCreate->assertSee('XII TKJ 1');
        $permitCreate->assertDontSee('XII TKJ 1 (24/25)');
    }

    /**
     * Skenario 5: Laporan piket (index dan print) menghormati tahun arsip tervalidasi.
     */
    public function test_piket_reports_honor_explicit_archive_year(): void
    {
        // Index dengan parameter query tahun arsip
        $responseIndex = $this->actingAs($this->superAdmin)->get(route('piket.reports.index', [
            'academic_year' => '2024/2025',
        ]));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('XII TKJ 1 (24/25)');

        // Print dengan parameter query tahun arsip
        $responsePrint = $this->actingAs($this->superAdmin)->get(route('piket.reports.print', [
            'academic_year' => '2024/2025',
        ]));
        $responsePrint->assertStatus(200);
        $responsePrint->assertViewHas('academicYear', '2024/2025');
    }

    /**
     * Skenario 6: Dashboard meja piket mengutamakan tahun ajaran operasional saat tidak ada query tahun eksplisit.
     */
    public function test_piket_dashboard_defaults_to_operational_year_despite_archive_session(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('piket.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('academicYear', '2026/2027');
    }
}
