<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeroomAttendanceRecapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $homeroomTeacher;

    protected Classroom $classroom;

    protected Student $student;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->academicYear = AcademicYear::firstOrCreate(
            ['is_active' => true],
            [
                'name' => '2026/2027',
                'semester' => 'ganjil',
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
            ]
        );

        $teacherRole = Role::where('slug', 'guru')->first() ?? Role::factory()->create(['slug' => 'guru', 'name' => 'Guru']);

        $this->homeroomTeacher = User::factory()->create([
            'username' => 'walikelas_test_1',
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::firstOrCreate(
            ['level' => 10],
            ['name' => 'Kelas 10', 'order_index' => 1, 'is_active' => true]
        );

        $this->classroom = Classroom::create([
            'name' => 'X-A',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->homeroomTeacher->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'classroom_id' => $this->classroom->id,
            'nis' => '12345',
            'nisn' => '0012345678',
            'name' => 'Budi Santoso',
            'gender' => 'L',
            'status' => 'aktif',
        ]);
    }

    public function test_homeroom_recap_daily_renders_with_piket_permits(): void
    {
        // 1. Buat surat izin piket untuk siswa (Dispen, Pulang Cepat, Terlambat)
        PiketStudentPermit::create([
            'permit_number' => 'PIKET/TEST/001',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_pulang_cepat',
            'permit_category' => 'pulang',
            'permit_date' => '2026-10-12',
            'departure_time' => '10:00',
            'reason' => 'Sakit perut',
            'status' => 'diberikan',
            'given_by' => $this->homeroomTeacher->id,
            'approved_by' => $this->homeroomTeacher->id,
        ]);

        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'daily',
                'date' => '2026-10-12',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Pulang Cepat');
    }

    public function test_homeroom_recap_weekly_renders_properly(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'weekly',
                'date' => '2026-10-12',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Rekap Mingguan');
        $response->assertSee('Budi Santoso');
    }

    public function test_homeroom_recap_monthly_renders_with_analysis_columns(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'monthly',
                'date' => '2026-10-12',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Rekap Bulanan');
        $response->assertSee('Dispen');
        $response->assertSee('Telat');
        $response->assertSee('Pulang');
        $response->assertSee('% Kehadiran');
    }

    public function test_homeroom_recap_monthly_renders_dapodik_format_view(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'monthly',
                'date' => '2026-10-12',
                'view_type' => 'dapodik',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Format Raport / Dapodik');
        $response->assertSee('Hadir (H)');
        $response->assertSee('Sakit (S)');
        $response->assertSee('Izin (I)');
        $response->assertSee('Alfa (A)');
    }

    public function test_teacher_can_take_attendance_with_terlambat_status(): void
    {
        $subject = Subject::firstOrCreate(
            ['code' => 'MTK-01'],
            ['name' => 'Matematika', 'category' => 'umum', 'order_index' => 1, 'is_active' => true]
        );

        $response = $this->actingAs($this->homeroomTeacher)
            ->post(route('attendance.subject.store'), [
                'date' => '2026-10-12',
                'classroom_id' => $this->classroom->id,
                'subject_id' => $subject->id,
                'start_period' => 1,
                'end_period' => 2,
                'attendance' => [
                    $this->student->id => [
                        'status' => 't',
                        'notes' => 'Datang jam 07.20',
                    ],
                ],
            ]);

        if (session('error')) {
            $this->fail('Store error: '.session('error'));
        }

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('subject_attendance_records', [
            'student_id' => $this->student->id,
            'status' => 't',
            'notes' => 'Datang jam 07.20',
        ]);
    }

    public function test_homeroom_recap_semester_mode_renders_successfully(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'semester',
                'semester' => 'ganjil',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Rekap Semester');
        $response->assertSee('Budi Santoso');
        $response->assertSee('% Kehadiran');
    }

    public function test_homeroom_recap_yearly_mode_renders_successfully(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'yearly',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Rekap Tahunan');
        $response->assertSee('Budi Santoso');
    }

    public function test_homeroom_recap_custom_range_mode_renders_successfully(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'custom',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-14',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Rekap Rentang Tanggal');
        $response->assertSee('Budi Santoso');
    }

    public function test_homeroom_recap_export_excel_with_semester_mode(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.export-excel', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'semester',
                'semester' => 'ganjil',
            ]));

        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') === 'text/csv; charset=UTF-8');
    }

    public function test_homeroom_recap_download_pdf_with_custom_mode(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->get(route('attendance.homeroom-recap.download-pdf', [
                'classroom_id' => $this->classroom->id,
                'mode' => 'custom',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-14',
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
