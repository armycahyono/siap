<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearSubjectAttendanceAndHomeroomTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $homeroomTeacherA;

    protected User $homeroomTeacherB;

    protected Classroom $classA;

    protected Classroom $classB;

    protected Subject $subject;

    protected Student $student2026;

    protected Student $student2024;

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

        SchoolSetting::create([
            'school_name' => 'SMK Negeri 1 Test',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->homeroomTeacherA = User::create([
            'name' => 'Wali Kelas A',
            'username' => 'walikelas_a',
            'email' => 'wali_a@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $this->homeroomTeacherB = User::create([
            'name' => 'Wali Kelas B',
            'username' => 'walikelas_b',
            'email' => 'wali_b@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas X',
            'level' => 10,
            'order_index' => 1,
            'is_active' => true,
        ]);

        $this->classA = Classroom::create([
            'name' => 'X-RPL-1',
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->homeroomTeacherA->id,
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->year2026->id,
            'is_active' => true,
        ]);

        $this->classB = Classroom::create([
            'name' => 'X-TKJ-1',
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->homeroomTeacherB->id,
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->year2026->id,
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Pemrograman Web',
            'code' => 'PWEB-01',
            'category' => 'kejuruan',
            'order_index' => 1,
            'is_active' => true,
        ]);

        $this->student2026 = Student::create([
            'classroom_id' => $this->classA->id,
            'name' => 'Ahmad Siswa 2026',
            'nis' => '1001',
            'nisn' => '0012345001',
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $this->student2024 = Student::create([
            'classroom_id' => $this->classA->id,
            'name' => 'Bambang Siswa 2024',
            'nis' => '1002',
            'nisn' => '0012345002',
            'gender' => 'L',
            'status' => 'lulus',
            'is_active' => false,
        ]);
    }

    /**
     * Skenario 1: Sesi tampilan aktif di UI (misalnya 2024/2025) tidak mencemari
     * pembuatan sesi presensi mapel baru untuk hari ini (tersimpan dengan tahun operasional 2026/2027).
     */
    public function test_ui_session_does_not_contaminate_new_subject_attendance_session_creation(): void
    {
        $today = now()->toDateString();
        $dayName = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Senin',
        };

        // Buat jadwal KBM hari ini
        $schedule = LessonSchedule::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->homeroomTeacherA->id,
            'day' => $dayName,
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // Simulasikan waktu KBM sedang berlangsung
        Carbon::setTestNow(Carbon::parse($today.' 07:15:00'));

        // Simulasikan user sedang melihat tahun ajaran historis di session UI
        $response = $this->actingAs($this->homeroomTeacherA)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('attendance.subject.store'), [
                'date' => $today,
                'classroom_id' => $this->classA->id,
                'subject_id' => $this->subject->id,
                'start_period' => 1,
                'end_period' => 2,
                'lesson_schedule_id' => $schedule->id,
                'attendance' => [
                    $this->student2026->id => [
                        'status' => 'h',
                        'notes' => 'Hadir tepat waktu',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('attendance.subject.index', ['date' => $today]));

        // Sesi yang terbentuk harus menggunakan tahun operasional 2026/2027, BUKAN session view 2024/2025
        $session = SubjectAttendanceSession::where('classroom_id', $this->classA->id)
            ->where('subject_id', $this->subject->id)
            ->whereDate('attendance_date', $today)
            ->where('start_period', 1)
            ->first();

        $this->assertNotNull($session);
        $this->assertSame('2026/2027', $session->academic_year);

        $this->assertNull(SubjectAttendanceSession::where('classroom_id', $this->classA->id)
            ->where('subject_id', $this->subject->id)
            ->whereDate('attendance_date', $today)
            ->where('academic_year', '2024/2025')
            ->first());

        Carbon::setTestNow();
    }

    /**
     * Skenario 2: Target tahun ajaran eksplisit yang valid (misalnya 2027/2028) dihormati saat pembuatan sesi presensi.
     */
    public function test_explicit_valid_target_academic_year_is_honored(): void
    {
        $today = now()->toDateString();

        $response = $this->actingAs($this->admin)
            ->post(route('attendance.subject.store'), [
                'date' => $today,
                'academic_year' => '2027/2028',
                'semester' => 'ganjil',
                'classroom_id' => $this->classA->id,
                'subject_id' => $this->subject->id,
                'start_period' => 3,
                'end_period' => 4,
                'attendance' => [
                    $this->student2026->id => [
                        'status' => 'h',
                        'notes' => 'Uji coba tahun mendatang',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('attendance.subject.index', ['date' => $today]));

        $session = SubjectAttendanceSession::where('classroom_id', $this->classA->id)
            ->where('subject_id', $this->subject->id)
            ->whereDate('attendance_date', $today)
            ->where('start_period', 3)
            ->first();

        $this->assertNotNull($session);
        $this->assertSame('2027/2028', $session->academic_year);
    }

    /**
     * Skenario 3: Tahun ajaran tidak terdaftar (misalnya 1999/2000) ditolak melalui validasi.
     */
    public function test_unregistered_academic_year_is_rejected_by_validation(): void
    {
        $today = now()->toDateString();

        $response = $this->actingAs($this->admin)
            ->from(route('attendance.subject.take'))
            ->post(route('attendance.subject.store'), [
                'date' => $today,
                'academic_year' => '1999/2000',
                'classroom_id' => $this->classA->id,
                'subject_id' => $this->subject->id,
                'start_period' => 5,
                'end_period' => 6,
                'attendance' => [
                    $this->student2026->id => [
                        'status' => 'h',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['academic_year']);
        $this->assertDatabaseMissing('subject_attendance_sessions', [
            'classroom_id' => $this->classA->id,
            'academic_year' => '1999/2000',
        ]);
    }

    /**
     * Skenario 4: Rekaman presensi lama mempertahankan tahun ajaran aslinya saat diperbarui.
     */
    public function test_historical_attendance_session_preserves_original_academic_year_when_updated(): void
    {
        $historicalDate = '2024-09-10';

        // Buat rekaman presensi historis tahun 2024/2025
        $historicalSession = SubjectAttendanceSession::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->homeroomTeacherA->id,
            'attendance_date' => $historicalDate,
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2024/2025',
            'semester' => 'ganjil',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $historicalSession->id,
            'student_id' => $this->student2024->id,
            'status' => 'h',
            'notes' => 'Catatan awal',
        ]);

        // Perbarui sesi historis tersebut tanpa mengirimkan parameter academic_year eksplisit
        $response = $this->actingAs($this->admin)
            ->post(route('attendance.subject.store'), [
                'date' => $historicalDate,
                'classroom_id' => $this->classA->id,
                'subject_id' => $this->subject->id,
                'start_period' => 1,
                'end_period' => 2,
                'attendance' => [
                    $this->student2024->id => [
                        'status' => 'i',
                        'notes' => 'Diperbarui menjadi izin',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();

        // Pastikan academic_year tetap '2024/2025', tidak tertimpa menjadi '2026/2027'
        $historicalSession->refresh();
        $this->assertSame('2024/2025', $historicalSession->academic_year);

        $this->assertDatabaseHas('subject_attendance_records', [
            'subject_attendance_session_id' => $historicalSession->id,
            'student_id' => $this->student2024->id,
            'status' => 'i',
            'notes' => 'Diperbarui menjadi izin',
        ]);
    }

    /**
     * Skenario 5: Rekap wali kelas memfilter siswa sesuai tahun ajaran yang dipilih via student_classrooms.
     */
    public function test_homeroom_recap_filters_students_by_selected_academic_year_via_student_classrooms(): void
    {
        // Daftarkan siswa ke rombel melalui tabel riwayat student_classrooms
        StudentClassroom::create([
            'student_id' => $this->student2024->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2024->id,
            'academic_year_name' => '2024/2025',
            'status' => 'aktif',
        ]);

        StudentClassroom::create([
            'student_id' => $this->student2026->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year_name' => '2026/2027',
            'status' => 'aktif',
        ]);

        // 1. Tampilkan rekap untuk tahun ajaran historis 2024/2025
        $response2024 = $this->actingAs($this->homeroomTeacherA)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classA->id,
                'academic_year' => '2024/2025',
                'mode' => 'daily',
                'date' => '2024-09-10',
            ]));

        $response2024->assertStatus(200);
        $response2024->assertSee($this->student2024->nisn);
        $response2024->assertDontSee($this->student2026->nisn);

        // 2. Tampilkan rekap untuk tahun ajaran aktif 2026/2027
        $response2026 = $this->actingAs($this->homeroomTeacherA)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classA->id,
                'academic_year' => '2026/2027',
                'mode' => 'daily',
                'date' => '2026-09-10',
            ]));

        $response2026->assertStatus(200);
        $response2026->assertSee($this->student2026->nisn);
        $response2026->assertDontSee($this->student2024->nisn);
    }

    /**
     * Skenario 6: Data presensi dari tahun ajaran berbeda tidak bercampur dalam rekapitulasi.
     */
    public function test_attendance_data_from_different_academic_years_does_not_mix_in_recap(): void
    {
        // Daftarkan siswa ke riwayat kelas
        StudentClassroom::create([
            'student_id' => $this->student2024->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2024->id,
            'academic_year_name' => '2024/2025',
            'status' => 'aktif',
        ]);

        StudentClassroom::create([
            'student_id' => $this->student2026->id,
            'classroom_id' => $this->classA->id,
            'academic_year_id' => $this->year2026->id,
            'academic_year_name' => '2026/2027',
            'status' => 'aktif',
        ]);

        // Buat sesi presensi tahun 2024/2025 (10 September 2024)
        $session2024 = SubjectAttendanceSession::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->homeroomTeacherA->id,
            'attendance_date' => '2024-09-10',
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2024/2025',
            'semester' => 'ganjil',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session2024->id,
            'student_id' => $this->student2024->id,
            'status' => 'h',
        ]);

        // Buat sesi presensi tahun 2026/2027 (10 September 2026)
        $session2026 = SubjectAttendanceSession::create([
            'classroom_id' => $this->classA->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->homeroomTeacherA->id,
            'attendance_date' => '2026-09-10',
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session2026->id,
            'student_id' => $this->student2026->id,
            'status' => 'a',
        ]);

        // Akses rekap semester untuk 2024/2025
        $response = $this->actingAs($this->homeroomTeacherA)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classA->id,
                'mode' => 'semester',
                'semester' => 'ganjil',
                'academic_year' => '2024/2025',
            ]));

        $response->assertStatus(200);
        // Harus ada siswa 2024 dan tidak mencantumkan siswa 2026
        $response->assertSee($this->student2024->nisn);
        $response->assertDontSee($this->student2026->nisn);
    }

    /**
     * Skenario 7: Pembatasan hak akses wali kelas tetap ditegakkan.
     */
    public function test_homeroom_teacher_authority_and_access_restrictions_remain_enforced(): void
    {
        // 1. Wali Kelas A mencoba mengakses kelas B (yang bukan kelas perwaliannya) -> harus 403 Forbidden
        $responseUnauthorized = $this->actingAs($this->homeroomTeacherA)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classB->id,
                'academic_year' => '2026/2027',
            ]));

        $responseUnauthorized->assertStatus(403);

        // 2. Wali Kelas A mengakses kelas A (kelas perwaliannya) -> 200 OK
        $responseAuthorized = $this->actingAs($this->homeroomTeacherA)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classA->id,
                'academic_year' => '2026/2027',
            ]));

        $responseAuthorized->assertStatus(200);

        // 3. Admin memiliki hak akses penuh (canViewAll) untuk melihat kelas B -> 200 OK
        $responseAdmin = $this->actingAs($this->admin)
            ->get(route('attendance.homeroom-recap.index', [
                'classroom_id' => $this->classB->id,
                'academic_year' => '2026/2027',
            ]));

        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee($this->classB->name);
    }
}
