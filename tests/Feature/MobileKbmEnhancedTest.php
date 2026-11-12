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
use Carbon\Carbon;
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
            'semester' => 'Ganjil',
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

    public function test_vice_principals_can_access_live_monitoring_including_gtk(): void
    {
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_sarpras'], ['name' => 'Waka Sarpras']);
        $wakaSarpras = User::create([
            'name' => 'Waka Sarpras Test',
            'username' => 'wakasarprastest',
            'email' => 'wakasarpras@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $wakaRole->id,
            'position' => 'Waka Bidang Sarpras',
            'is_active' => true,
        ]);

        $response = $this->actingAs($wakaSarpras)
            ->getJson('/api/v1/mobile/kbm');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $liveMonitoring = $response->json('data.live_monitoring');
        $this->assertNotNull($liveMonitoring);
        $this->assertTrue($liveMonitoring['can_view_gtk']);
        $this->assertTrue($liveMonitoring['can_view_student_all']);
        $this->assertArrayHasKey('gtk', $liveMonitoring);
        $this->assertArrayHasKey('siswa', $liveMonitoring);
    }

    public function test_my_schedules_contains_last_topic_notes_and_ongoing_status(): void
    {
        // 1. Buat riwayat sesi sebelumnya untuk kelas dan mapel ini
        SubjectAttendanceSession::create([
            'lesson_schedule_id' => $this->schedule->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'attendance_date' => '2026-10-26', // Pertemuan sebelumnya
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
            'topic' => 'Konfigurasi Firewall MikroTik',
            'notes' => 'Latihan praktikum bab 3 selesai, tugas mandiri.',
        ]);

        // 2. Fetch KBM untuk hari Senin berikutnya
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm?date=2026-11-02');

        $response->assertStatus(200);
        $schedules = $response->json('data.my_schedules');

        $this->assertNotEmpty($schedules);
        $schedule = $schedules[0];

        $this->assertEquals('Konfigurasi Firewall MikroTik', $schedule['last_topic']);
        $this->assertEquals('Latihan praktikum bab 3 selesai, tugas mandiri.', $schedule['last_notes']);
        $this->assertArrayHasKey('last_session_date', $schedule);
        $this->assertArrayHasKey('is_ongoing', $schedule);
        $this->assertFalse($schedule['is_ongoing']); // Karena tanggal masa lalu
    }

    public function test_schedule_ongoing_status_is_true_during_class_hours(): void
    {
        // 2026-11-02 adalah hari Senin, pukul 07:30 (di dalam jam ke-1 s/d 2: 07:00 - 08:30)
        Carbon::setTestNow('2026-11-02 07:30:00');

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm?date=2026-11-02');

        $response->assertStatus(200);
        $schedules = $response->json('data.my_schedules');
        $this->assertNotEmpty($schedules);
        $this->assertTrue($schedules[0]['is_ongoing']);

        // Setelah jam pelajaran selesai (pukul 09:00)
        Carbon::setTestNow('2026-11-02 09:00:00');

        $response2 = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm?date=2026-11-02');

        $this->assertFalse($response2->json('data.my_schedules.0.is_ongoing'));

        Carbon::setTestNow(); // Reset
    }

    public function test_subsequent_kbm_period_inherits_attendance_status_from_earlier_session(): void
    {
        $ay = AcademicYear::where('name', '2026/2027')->first();

        // Buat siswa kedua dan ketiga
        $studentSakit = Student::create([
            'name' => 'Budi Sakit Test',
            'nis' => '12346',
            'gender' => 'L',
            'is_active' => true,
        ]);
        StudentClassroom::create([
            'student_id' => $studentSakit->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $studentAlfa = Student::create([
            'name' => 'Citra Alfa Test',
            'nis' => '12347',
            'gender' => 'P',
            'is_active' => true,
        ]);
        StudentClassroom::create([
            'student_id' => $studentAlfa->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Buat jadwal untuk jam berikutnya (Jam ke-3 s/d 4)
        $subject2 = Subject::firstOrCreate(
            ['code' => 'TKJ-02'],
            ['name' => 'Teknologi Layanan Jaringan', 'is_active' => true]
        );

        $scheduleJam3 = LessonSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'classroom_id' => $this->classroom->id,
            'subject_id' => $subject2->id,
            'teacher_id' => $this->teacher->id,
            'day' => 'Senin',
            'start_period' => 3,
            'end_period' => 4,
            'start_time' => '08:45:00',
            'end_time' => '10:15:00',
        ]);

        $dateStr = '2026-11-02';

        // 1. Simpan sesi presensi jam ke-1 s/d 2 oleh guru sebelumnya
        $sessionJam1 = SubjectAttendanceSession::create([
            'lesson_schedule_id' => $this->schedule->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'attendance_date' => $dateStr,
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
            'topic' => 'Pengenalan Jaringan Komputer',
        ]);

        // Siswa 1: Hadir ('h')
        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $sessionJam1->id,
            'student_id' => $this->student->id,
            'status' => 'h',
        ]);
        // Siswa 2: Sakit ('s')
        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $sessionJam1->id,
            'student_id' => $studentSakit->id,
            'status' => 's',
            'notes' => 'Demam tinggi',
        ]);
        // Siswa 3: Alpa ('a')
        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $sessionJam1->id,
            'student_id' => $studentAlfa->id,
            'status' => 'a',
        ]);

        // 2. Akses halaman kbm/take untuk Jam ke-3 s/d 4 yang belum pernah diabsen
        $response = $this->actingAs($this->teacher)
            ->getJson("/api/v1/mobile/kbm/take?schedule_id={$scheduleJam3->id}&date={$dateStr}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session' => [
                        'is_attended' => false,
                    ],
                    'earlier_session' => [
                        'id' => $sessionJam1->id,
                        'start_period' => 1,
                        'end_period' => 2,
                        'subject_name' => $this->subject->name,
                    ],
                    'summary' => [
                        'total' => 3,
                        'hadir' => 1,
                        'sakit' => 1,
                        'alfa' => 1,
                    ],
                ],
            ]);

        $students = collect($response->json('data.students'))->keyBy('id');

        // Verifikasi Siswa 1 mewarisi Hadir
        $this->assertEquals('h', $students[$this->student->id]['status']);
        $this->assertTrue($students[$this->student->id]['is_inherited']);

        // Verifikasi Siswa 2 mewarisi Sakit
        $this->assertEquals('s', $students[$studentSakit->id]['status']);
        $this->assertEquals('Demam tinggi', $students[$studentSakit->id]['notes']);
        $this->assertTrue($students[$studentSakit->id]['is_inherited']);
        $this->assertEquals('JP 1: Sakit', $students[$studentSakit->id]['inherited_notice']);

        // Verifikasi Siswa 3 mewarisi Alpa
        $this->assertEquals('a', $students[$studentAlfa->id]['status']);
        $this->assertTrue($students[$studentAlfa->id]['is_inherited']);
        $this->assertEquals('JP 1: Alpa', $students[$studentAlfa->id]['inherited_notice']);

        // 3. Verifikasi info pada index KBM untuk jam ke-3 menandai has_earlier_session
        $kbmIndexRes = $this->actingAs($this->teacher)
            ->getJson("/api/v1/mobile/kbm?date={$dateStr}");

        $kbmIndexRes->assertStatus(200);
        $myScheds = collect($kbmIndexRes->json('data.my_schedules'))->keyBy('id');
        $this->assertTrue($myScheds[$scheduleJam3->id]['has_earlier_session']);
        $this->assertEquals('Jam ke-1', $myScheds[$scheduleJam3->id]['earlier_period_text']);
    }
}
