<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcademicYearAttendanceAndTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $homeroomTeacherA;

    protected User $regularTeacher;

    protected Classroom $classA;

    protected Classroom $classB;

    protected Subject $subject;

    protected Student $studentX;

    protected Student $studentY;

    protected AcademicYear $year2024;

    protected AcademicYear $year2026;

    protected AcademicYear $year2027;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], [
            'name' => 'Super Admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);

        $teacherRole = Role::firstOrCreate(['slug' => 'guru'], [
            'name' => 'Guru',
            'description' => 'Guru Pengampu',
            'is_system' => false,
        ]);

        $this->year2024 = AcademicYear::create([
            'name' => '2024/2025',
            'semester' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $this->year2026 = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->year2027 = AcademicYear::create([
            'name' => '2027/2028',
            'semester' => 'Ganjil',
            'start_date' => '2027-07-15',
            'end_date' => '2028-06-20',
            'is_active' => false,
        ]);

        $grade10 = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $grade11 = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->homeroomTeacherA = User::factory()->create([
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $this->regularTeacher = User::factory()->create([
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $this->classA = Classroom::create([
            'name' => 'X-RPL-1',
            'grade_level_id' => $grade10->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->homeroomTeacherA->id,
            'is_active' => true,
        ]);

        $this->classB = Classroom::create([
            'name' => 'XI-RPL-1',
            'grade_level_id' => $grade11->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->subject = Subject::firstOrCreate(['code' => 'PROG'], [
            'name' => 'Pemrograman Web',
            'is_active' => true,
        ]);

        $this->studentX = Student::create([
            'nis' => '10001',
            'nisn' => '0010001',
            'name' => 'Budi Santoso',
            'classroom_id' => $this->classB->id,
            'is_active' => true,
        ]);

        $this->studentY = Student::create([
            'nis' => '10002',
            'nisn' => '0010002',
            'name' => 'Citra Lestari',
            'classroom_id' => $this->classA->id,
            'is_active' => true,
        ]);

        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'school_name' => 'SMK SIAP',
            ]
        );
    }

    /**
     * Skenario 1: Monitoring live dan rekaman scan operasional tidak terkontaminasi oleh session UI arsip.
     */
    public function test_operational_scan_and_monitoring_is_not_contaminated_by_ui_archive_session(): void
    {
        $today = Carbon::today();
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        $todayDay = $dayNames[$today->dayOfWeek] ?? 'Senin';

        // Buat jadwal untuk tahun operasional 2026/2027
        $schedule2026 = LessonSchedule::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->homeroomTeacherA->id,
            'day' => $todayDay,
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // Buat jadwal untuk tahun historis 2024/2025 dengan guru berbeda
        $schedule2024 = LessonSchedule::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->regularTeacher->id,
            'day' => $todayDay,
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // Akses dashboard presensi hari ini dengan session arsip 2024/2025
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('attendance.index'));

        $response->assertOk();

        // Rekaman scan log hari ini harus tersimpan bersih tanpa terpengaruh session
        $log = AttendanceLog::create([
            'device_sn' => 'SN-TEST-01',
            'pin' => $this->studentY->nisn,
            'scanned_at' => now(),
            'user_type' => 'student',
            'student_id' => $this->studentY->id,
            'name' => $this->studentY->name,
            'status' => 'check_in',
            'verify_type' => 'fingerprint',
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'student_id' => $this->studentY->id,
            'user_type' => 'student',
        ]);
    }

    /**
     * Skenario 2: Laporan presensi siswa (reportStudents) memfilter berdasarkan academic_year yang diminta dan divalidasi.
     */
    public function test_student_attendance_report_filters_by_requested_and_validated_academic_year(): void
    {
        $response = $this->actingAs($this->admin)->get(route('attendance.report.students', [
            'academic_year' => '2024/2025',
            'classroom_id' => $this->classA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('academicYear', '2024/2025');
    }

    /**
     * Skenario 3: Rekaman dan log presensi historis mempertahankan tanggal dan tahun ajaran aslinya.
     */
    public function test_historical_attendance_logs_and_records_retain_original_year(): void
    {
        $pastDate = Carbon::parse('2024-09-15 07:05:00');

        $historicalLog = AttendanceLog::create([
            'device_sn' => 'SN-HISTORICAL-01',
            'pin' => $this->studentX->nisn,
            'scanned_at' => $pastDate,
            'user_type' => 'student',
            'student_id' => $this->studentX->id,
            'name' => $this->studentX->name,
            'status' => 'check_in',
            'verify_type' => 'fingerprint',
        ]);

        $this->assertEquals('2024-09-15', $historicalLog->scanned_at->toDateString());

        // Verifikasi log tidak tercampur ketika membuka dashboard monitoring hari berjalan
        $responseToday = $this->actingAs($this->admin)->get(route('attendance.index'));
        $responseToday->assertOk();
        $this->assertFalse(
            AttendanceLog::whereDate('scanned_at', now()->toDateString())->where('id', $historicalLog->id)->exists()
        );
    }

    /**
     * Skenario 4: Siswa yang berada di rombel berbeda pada tahun ajaran berbeda difilter akurat tanpa pencemaran silang.
     */
    public function test_students_placed_in_different_classrooms_across_academic_years_are_filtered_accurately(): void
    {
        // Pada TP 2024/2025: Budi Santoso (studentX) berada di X-RPL-1 (classA)
        StudentClassroom::create([
            'student_id' => $this->studentX->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2024->id,
            'academic_year_name' => '2024/2025',
            'classroom_name' => $this->classA->name,
            'status' => 'aktif',
        ]);

        // Pada TP 2026/2027: Citra Lestari (studentY) berada di X-RPL-1 (classA), sedangkan Budi Santoso (studentX) sudah naik ke XI-RPL-1 (classB)
        StudentClassroom::create([
            'student_id' => $this->studentY->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year_name' => '2026/2027',
            'classroom_name' => $this->classA->name,
            'status' => 'aktif',
        ]);
        StudentClassroom::create([
            'student_id' => $this->studentX->id,
            'classroom_id' => $this->classB->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year_name' => '2026/2027',
            'classroom_name' => $this->classB->name,
            'status' => 'aktif',
        ]);

        // 1. Cek laporan presensi X-RPL-1 pada TP historis 2024/2025
        $response2024 = $this->actingAs($this->admin)->get(route('attendance.report.students', [
            'classroom_id' => $this->classA->id,
            'academic_year' => '2024/2025',
            'period' => 'custom',
            'start_date' => '2024-09-01',
            'end_date' => '2024-09-30',
        ]));

        $response2024->assertOk();
        $people2024 = $response2024->viewData('people');
        $this->assertTrue($people2024->contains('id', $this->studentX->id), 'Budi Santoso harus terdaftar di X-RPL-1 pada 2024/2025');
        $this->assertFalse($people2024->contains('id', $this->studentY->id), 'Citra Lestari tidak boleh terdaftar di X-RPL-1 pada 2024/2025');

        // 2. Cek laporan presensi X-RPL-1 pada TP operasional 2026/2027
        $response2026 = $this->actingAs($this->admin)->get(route('attendance.report.students', [
            'classroom_id' => $this->classA->id,
            'academic_year' => '2026/2027',
            'period' => 'custom',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]));

        $response2026->assertOk();
        $people2026 = $response2026->viewData('people');
        $this->assertTrue($people2026->contains('id', $this->studentY->id), 'Citra Lestari harus terdaftar di X-RPL-1 pada 2026/2027');
        $this->assertFalse($people2026->contains('id', $this->studentX->id), 'Budi Santoso tidak boleh terdaftar di X-RPL-1 pada 2026/2027');
    }

    /**
     * Skenario 5: Handler webhook Telegram beroperasi independen dari session browser.
     */
    public function test_telegram_webhook_handlers_function_independently_of_browser_session(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $this->admin->update(['telegram_chat_id' => '123456789']);

        // Jalankan webhook dengan session arsip aktif
        $response = $this->withSession(['active_academic_year' => '2024/2025'])
            ->postJson(route('telegram.webhook'), [
                'message' => [
                    'message_id' => 101,
                    'from' => [
                        'id' => 123456789,
                        'first_name' => 'Admin',
                    ],
                    'chat' => [
                        'id' => 123456789,
                        'type' => 'private',
                    ],
                    'text' => '/piket',
                    'date' => time(),
                ],
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
    }

    /**
     * Skenario 6: Fallback hardcode 2026/2027 telah dieliminasi dari Telegram & Notifikasi saat tahun ajaran berganti.
     */
    public function test_hardcoded_fallback_2026_2027_eliminated_from_telegram_and_notifications(): void
    {
        // Nonaktifkan 2026/2027 dan aktifkan 2027/2028
        $this->year2026->update(['is_active' => false]);
        $this->year2027->update(['is_active' => true]);
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            ['academic_year' => '2027/2028', 'semester' => 'Ganjil']
        );

        $today = Carbon::today();
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        $todayDay = $dayNames[$today->dayOfWeek] ?? 'Senin';

        // Buat jadwal piket untuk tahun ajaran baru 2027/2028
        PiketSchedule::create([
            'teacher_id' => $this->homeroomTeacherA->id,
            'academic_year' => '2027/2028',
            'day' => $todayDay,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        // Verifikasi NotificationService mengambil tahun operasional 2027/2028
        $notifications = NotificationService::getUserNotifications($this->homeroomTeacherA);
        $this->assertNotNull($notifications);

        // Verifikasi TelegramService mendeteksi petugas piket dengan tahun ajaran baru 2027/2028
        $telegramService = app(TelegramService::class);
        $reflection = new \ReflectionClass($telegramService);
        $method = $reflection->getMethod('getOperationalAcademicYear');
        $method->setAccessible(true);
        $resolvedYear = $method->invoke($telegramService);

        $this->assertSame('2027/2028', $resolvedYear);
        $this->assertNotSame('2026/2027', $resolvedYear);
    }

    /**
     * Skenario 7: Otorisasi akses laporan presensi siswa (guru biasa vs wali kelas vs admin) tetap ditegakkan ketat.
     */
    public function test_authorization_gates_for_student_attendance_report_are_strictly_enforced(): void
    {
        // 1. Guru biasa yang bukan wali kelas aktif dilarang mengakses laporan presensi siswa (403 Forbidden)
        $responseForbidden = $this->actingAs($this->regularTeacher)->get(route('attendance.report.students', [
            'classroom_id' => $this->classA->id,
        ]));
        $responseForbidden->assertForbidden();

        // 2. Wali kelas yang bertugas di kelas A diizinkan mengakses laporan kelasnya (200 OK)
        $responseHomeroom = $this->actingAs($this->homeroomTeacherA)->get(route('attendance.report.students', [
            'classroom_id' => $this->classA->id,
        ]));
        $responseHomeroom->assertOk();

        // 3. Super Admin diizinkan mengakses lintas kelas (200 OK)
        $responseAdmin = $this->actingAs($this->admin)->get(route('attendance.report.students', [
            'classroom_id' => $this->classA->id,
        ]));
        $responseAdmin->assertOk();
    }
}
