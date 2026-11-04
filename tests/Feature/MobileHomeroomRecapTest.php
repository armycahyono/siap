<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileHomeroomRecapTest extends TestCase
{
    use RefreshDatabase;

    protected User $homeroomTeacher;

    protected User $otherTeacher;

    protected Classroom $classroom;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        AcademicYear::where('id', '!=', $ay->id)->update(['is_active' => false]);

        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->homeroomTeacher = User::create([
            'name' => 'Wali Kelas Test',
            'username' => 'walikelastest',
            'email' => 'walikelas@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $this->otherTeacher = User::create([
            'name' => 'Guru Lain Test',
            'username' => 'gurulaintest',
            'email' => 'gurulain@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Kelas XII']);

        $this->classroom = Classroom::create([
            'name' => 'XII RPL 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $ay->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->homeroomTeacher->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Budi Siswa Test',
            'nis' => '54321',
            'nisn' => '0098765432',
            'gender' => 'L',
            'is_active' => true,
            'phone_parent' => '081299887766',
        ]);

        StudentClassroom::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    public function test_homeroom_teacher_can_fetch_daily_recap(): void
    {
        $todayStr = Carbon::today()->toDateString();
        $subject = Subject::firstOrCreate(['name' => 'Pemrograman Web'], ['code' => 'PW01']);

        $session = SubjectAttendanceSession::create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->homeroomTeacher->id,
            'attendance_date' => $todayStr,
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2026/2027',
            'topic' => 'Pengantar Laravel REST API',
            'notes' => 'Lancar',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'h',
            'notes' => 'Hadir tepat waktu',
        ]);

        $response = $this->actingAs($this->homeroomTeacher)
            ->getJson("/api/v1/mobile/tasks/homeroom/recap?classroom_id={$this->classroom->id}&mode=daily&date={$todayStr}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mode' => 'daily',
                    'selected_classroom' => [
                        'id' => $this->classroom->id,
                        'name' => 'XII RPL 1',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'my_classrooms',
                    'selected_classroom',
                    'mode',
                    'summary_stats',
                    'daily' => [
                        'periods',
                        'student_grid' => [
                            '*' => [
                                'student_id',
                                'student_name',
                                'parent_phone',
                                'periods',
                            ],
                        ],
                    ],
                    'export_urls' => [
                        'excel',
                        'pdf',
                        'print',
                    ],
                ],
            ]);

        // Also test loading recap without explicit classroom_id (initial page load)
        $defaultResponse = $this->actingAs($this->homeroomTeacher)
            ->getJson('/api/v1/mobile/tasks/homeroom/recap');

        $defaultResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_classroom' => [
                        'id' => $this->classroom->id,
                        'name' => 'XII RPL 1',
                    ],
                ],
            ]);
    }

    public function test_homeroom_teacher_can_fetch_periodic_recap(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->getJson("/api/v1/mobile/tasks/homeroom/recap?classroom_id={$this->classroom->id}&mode=monthly");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mode' => 'monthly',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'periodic' => [
                        'recap',
                        'high_risk_count',
                        'total_active_days',
                    ],
                ],
            ]);
    }

    public function test_non_homeroom_teacher_cannot_access_other_classroom(): void
    {
        $response = $this->actingAs($this->otherTeacher)
            ->getJson("/api/v1/mobile/tasks/homeroom/recap?classroom_id={$this->classroom->id}&mode=daily");

        $response->assertStatus(403);
    }

    public function test_homeroom_teacher_can_download_attendance_recap_pdf_and_xlsx(): void
    {
        $todayStr = Carbon::today()->toDateString();

        // Test PDF download
        $pdfRes = $this->actingAs($this->homeroomTeacher)
            ->get("/presensi/rekap-kelas/unduh-pdf?classroom_id={$this->classroom->id}&mode=custom&start_date={$todayStr}&end_date={$todayStr}");
        $pdfRes->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // Test Excel (.xlsx) download
        $xlsxRes = $this->actingAs($this->homeroomTeacher)
            ->get("/presensi/rekap-kelas/ekspor-excel?classroom_id={$this->classroom->id}&mode=custom&start_date={$todayStr}&end_date={$todayStr}&format=xlsx");
        $xlsxRes->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_homeroom_teacher_can_download_incidents_recap_pdf_and_xlsx(): void
    {
        $todayStr = Carbon::today()->toDateString();

        // Test Incidents PDF download
        $pdfRes = $this->actingAs($this->homeroomTeacher)
            ->get("/piket/laporan/unduh-pdf?tab=incidents&classroom_id={$this->classroom->id}&start_date={$todayStr}&end_date={$todayStr}");
        $pdfRes->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // Test Incidents Excel (.xlsx) download
        $xlsxRes = $this->actingAs($this->homeroomTeacher)
            ->get("/piket/laporan/ekspor?tab=incidents&classroom_id={$this->classroom->id}&start_date={$todayStr}&end_date={$todayStr}&format=xlsx");
        $xlsxRes->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_bk_teacher_can_access_and_export_homeroom_recap(): void
    {
        $bkRole = Role::firstOrCreate(['slug' => 'guru_bk'], ['name' => 'Guru BK']);
        $bkTeacher = User::create([
            'name' => 'Guru BK Test',
            'username' => 'gurubktest',
            'email' => 'bk@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $bkRole->id,
            'is_active' => true,
        ]);

        $todayStr = Carbon::today()->toDateString();

        $pdfRes = $this->actingAs($bkTeacher)
            ->get("/presensi/rekap-kelas/unduh-pdf?classroom_id={$this->classroom->id}&mode=custom&start_date={$todayStr}&end_date={$todayStr}");
        $pdfRes->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }
}
