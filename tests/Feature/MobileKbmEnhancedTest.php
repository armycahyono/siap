<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileKbmEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected Classroom $classroom;

    protected Subject $subject;

    protected LessonSchedule $schedule;

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

        $this->teacher = User::create([
            'name' => 'Guru Pengajar Test',
            'username' => 'gurupengajartest',
            'email' => 'gurupengajar@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Kelas XII']);

        $this->classroom = Classroom::firstOrCreate(
            ['name' => 'XII TKJ 1'],
            ['academic_year' => '2026/2027', 'grade_level_id' => $grade->id, 'is_active' => true]
        );

        $this->subject = Subject::firstOrCreate(
            ['code' => 'TKJ-01'],
            ['name' => 'Administrasi Server Jaringan', 'is_active' => true]
        );

        $this->schedule = LessonSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        $this->student = Student::create([
            'name' => 'Ahmad Siswa Test',
            'nis' => '12345',
            'nisn' => '0012345678',
            'gender' => 'L',
            'is_active' => true,
            'phone_parent' => '081234567890',
        ]);

        StudentClassroom::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    public function test_can_fetch_kbm_with_past_date_navigation(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm?date=2026-11-02');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'date_info' => [
                        'current' => '2026-11-02',
                    ],
                ],
            ]);
    }

    public function test_can_store_kbm_attendance_with_swap_type_and_notes(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/kbm/take/store', [
                'date' => '2026-11-02',
                'schedule_id' => $this->schedule->id,
                'classroom_id' => $this->classroom->id,
                'subject_id' => $this->subject->id,
                'start_period' => 1,
                'end_period' => 2,
                'topic' => 'Instalasi Linux Debian Server',
                'notes' => 'Praktikum lancar di laboratorium komputer',
                'is_swap' => true,
                'swap_type' => 'guru_pengganti',
                'swap_notes' => 'Menggantikan guru dinas luar',
                'academic_year' => '2026/2027',
                'attendance' => [
                    [
                        'student_id' => $this->student->id,
                        'status' => 'h',
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Presensi kelas dan jurnal KBM berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('subject_attendance_sessions', [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'is_swap' => 1,
            'swap_type' => 'guru_pengganti',
            'swap_notes' => 'Menggantikan guru dinas luar',
            'topic' => 'Instalasi Linux Debian Server',
        ]);

        $this->assertDatabaseHas('subject_attendance_records', [
            'student_id' => $this->student->id,
            'status' => 'h',
        ]);
    }

    public function test_can_fetch_kbm_history_and_detail(): void
    {
        $session = SubjectAttendanceSession::create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'attendance_date' => '2026-10-26',
            'start_period' => 1,
            'end_period' => 2,
            'topic' => 'Pengenalan Topologi Jaringan',
            'notes' => 'Materi teori bab 1',
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'h',
            'notes' => 'Hadir tepat waktu',
        ]);

        // Test history list
        $resList = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm/history?start_date=2026-10-01&end_date=2026-10-31');

        $resList->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sessions' => [
                        '*' => [
                            'id',
                            'date',
                            'classroom_name',
                            'subject_name',
                            'topic',
                            'status_counts',
                        ],
                    ],
                    'pagination',
                ],
            ]);

        // Test history detail
        $resDetail = $this->actingAs($this->teacher)
            ->getJson("/api/v1/mobile/kbm/history/{$session->id}");

        $resDetail->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session' => [
                        'id' => $session->id,
                        'topic' => 'Pengenalan Topologi Jaringan',
                        'classroom_name' => 'XII TKJ 1',
                    ],
                ],
            ]);

        $records = $resDetail->json('data.records');
        $this->assertCount(1, $records);
        $this->assertEquals('AHMAD SISWA TEST', $records[0]['student_name']);
        $this->assertEquals('081234567890', $records[0]['parent_phone']);
    }

    public function test_can_fetch_school_schedule_browser(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/school-schedule?day=Senin');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_day' => 'Senin',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'academic_year',
                    'days',
                    'selected_day',
                    'schedules' => [
                        '*' => [
                            'id',
                            'classroom_name',
                            'subject_name',
                            'teacher_name',
                            'start_period',
                            'end_period',
                        ],
                    ],
                    'filter_options' => [
                        'classrooms',
                        'teachers',
                    ],
                ],
            ]);
    }
}
