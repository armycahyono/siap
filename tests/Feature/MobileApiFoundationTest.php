<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $setting = SchoolSetting::getSetting();
        $setting->update([
            'school_name' => 'SMK PGRI KRAS',
            'school_latitude' => -7.95590200,
            'school_longitude' => 111.97259400,
            'school_radius_meters' => 100,
            'geotag_attendance_enabled' => true,
        ]);

        $role = Role::where('slug', 'guru')->first() ?? Role::create(['name' => 'Guru', 'slug' => 'guru']);

        $this->teacher = User::create([
            'name' => 'Guru Penguji Mobile',
            'username' => 'gurutes',
            'email' => 'gurutes@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id,
            'nigs' => '1234567890',
            'nip' => '198001012005011001',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/mobile/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/mobile/tasks');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/mobile/profile');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/mobile/attendance/status');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/mobile/kbm');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/mobile/notifications');
        $response->assertStatus(401);
    }

    public function test_user_can_login_via_mobile_api(): void
    {
        // 1. Login with username
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'login' => 'gurutes',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'gurutes')
            ->assertJsonPath('data.user.name', 'Guru Penguji Mobile');

        // 2. Login with NIP
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'login' => '198001012005011001',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'gurutes');

        // 3. Login with wrong password
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'login' => 'gurutes',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_fetch_mobile_dashboard(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date_info' => ['date', 'day_name'],
                    'school_info' => ['name', 'academic_year', 'semester'],
                    'attendance_today' => ['has_check_in', 'has_check_out'],
                    'teaching_today' => ['total_sessions', 'schedules'],
                    'agendas_today',
                    'piket_info',
                ],
            ]);
    }

    public function test_authenticated_user_can_fetch_mobile_tasks(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'target_date' => ['current', 'day_name'],
                    'piket' => ['is_piket_today'],
                    'gtk_permits' => ['my_recent'],
                    'roles_operational' => ['can_manage_discipline', 'is_waka_kesiswaan', 'can_operate_bk'],
                ],
            ]);
    }

    public function test_authenticated_user_can_fetch_mobile_profile(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'nigs',
                        'nuptk',
                        'nip',
                        'birth_info',
                        'telegram_chat_id',
                        'is_telegram_connected',
                    ],
                    'subjects',
                    'structural_positions',
                    'homeroom_classrooms',
                    'piket_schedules',
                    'special_assignments',
                    'active_decrees',
                ],
            ]);
    }

    public function test_authenticated_user_can_fetch_mobile_attendance_status(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/attendance/status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school_location.latitude', -7.95590200)
            ->assertJsonPath('data.school_location.longitude', 111.97259400)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'geotag_enabled',
                    'school_location' => ['latitude', 'longitude', 'radius_meters'],
                    'today' => ['has_check_in', 'has_check_out', 'can_check_in'],
                    'recent_logs',
                ],
            ]);
    }

    public function test_user_can_submit_geotag_attendance(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/attendance/store', [
                'latitude' => -7.95590200,
                'longitude' => 111.97259400,
                'accuracy' => 10,
                'type' => 'check_in',
                'notes' => 'Catatan presensi masuk pengujian',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->teacher->id,
            'user_type' => 'teacher',
            'status' => 'check_in',
        ]);
    }

    public function test_user_can_update_profile_and_password(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/profile/update', [
                'name' => 'Guru Nama Baru, S.Pd.',
                'phone' => '081234567890',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Guru Nama Baru, S.Pd.');

        $this->assertDatabaseHas('users', [
            'id' => $this->teacher->id,
            'name' => 'Guru Nama Baru, S.Pd.',
            'phone' => '081234567890',
        ]);

        $pwdResponse = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/profile/password', [
                'current_password' => 'password123',
                'password' => 'newsecretpass',
                'password_confirmation' => 'newsecretpass',
            ]);

        $pwdResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_check_auth_me_and_logout(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('data.user.id', $this->teacher->id);

        $response = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_fetch_mobile_kbm_schedules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 08:00:00')); // Jumat

        $activeYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYearStr = $activeYear->name;
        $semesterStr = $activeYear->semester ?? 'ganjil';

        $dept = Department::first() ?? Department::create(['name' => 'Teknik', 'code' => 'TK']);
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => 'X', 'numeric_level' => 10]);
        $classroom = Classroom::create([
            'name' => 'X TK 1',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);
        $subject = Subject::first() ?? Subject::create(['name' => 'Matematika', 'code' => 'MTK']);

        LessonSchedule::create([
            'academic_year' => $academicYearStr,
            'semester' => $semesterStr,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'day' => 'Jumat',
            'start_period' => 1,
            'end_period' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_sessions', 1)
            ->assertJsonCount(1, 'data.my_schedules')
            ->assertJsonPath('data.my_schedules.0.classroom_name', 'X TK 1')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date_info' => [
                        'current',
                        'formatted_date',
                        'day_name',
                        'is_today',
                        'is_kbm_day',
                        'is_weekend',
                        'is_holiday',
                    ],
                    'my_schedules',
                    'swap_sessions',
                    'total_sessions',
                    'attended_sessions_count',
                ],
            ]);

        Carbon::setTestNow();
    }

    public function test_authenticated_user_can_fetch_mobile_notifications(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications',
                    'unread_count',
                ],
            ]);
    }

    public function test_bk_officer_can_fetch_and_update_referral_status(): void
    {
        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $admin = User::create([
            'name' => 'Admin BK',
            'username' => 'adminbk',
            'email' => 'adminbk@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $operationalAy = app(AcademicYearContext::class)->getOperationalYear()->name;

        $incident = PiketIncident::create([
            'academic_year' => $operationalAy,
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30:00',
            'student_name' => 'Siswa Bermasalah',
            'category' => 'kedisiplinan',
            'description' => 'Terlambat berulang dan perlu bimbingan',
            'action_taken' => 'Pencatatan dan rujukan piket',
            'follow_up_target' => 'rujuk_bk',
            'status' => 'tercatat',
            'recorded_by' => $this->teacher->id,
        ]);

        // Regular teacher does not have BK widget
        $resTeacher = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/tasks');
        $resTeacher->assertOk()
            ->assertJsonPath('data.bk', null);

        // BK Officer / Super Admin has BK widget with summary
        $resAdmin = $this->actingAs($admin)->getJson('/api/v1/mobile/tasks');
        $resAdmin->assertOk()
            ->assertJsonPath('data.bk.can_access', true)
            ->assertJsonPath('data.bk.summary.total_rujukan', 1)
            ->assertJsonPath('data.bk.summary.menunggu', 1);

        // Regular teacher forbidden to update BK status
        $resForbidden = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/bk/status', [
            'incident_id' => $incident->id,
            'status' => 'diproses',
        ]);
        $resForbidden->assertStatus(403);

        // Authorized officer updates status
        $resUpdate = $this->actingAs($admin)->postJson('/api/v1/mobile/tasks/bk/status', [
            'incident_id' => $incident->id,
            'status' => 'diproses',
            'notes' => 'Dijadwalkan konseling individu besok pagi.',
        ]);
        $resUpdate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'diproses');
    }

    public function test_discipline_officer_can_fetch_and_verify_incident(): void
    {
        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $admin = User::create([
            'name' => 'Admin Kedisiplinan',
            'username' => 'admindisiplin',
            'email' => 'admindisiplin@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $operationalAy = app(AcademicYearContext::class)->getOperationalYear()->name;

        $incident = PiketIncident::create([
            'academic_year' => $operationalAy,
            'incident_date' => now()->toDateString(),
            'incident_time' => '09:00:00',
            'student_name' => 'Siswa Pelanggar',
            'category' => 'kerapian',
            'penalty_points' => 10,
            'description' => 'Mengenakan seragam tidak sesuai ketentuan',
            'action_taken' => 'Peringatan lisan dan pembinaan',
            'verification_status' => 'menunggu_verifikasi',
            'status' => 'tercatat',
            'recorded_by' => $this->teacher->id,
        ]);

        // Regular teacher does not have Discipline widget
        $resTeacher = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/tasks');
        $resTeacher->assertOk()
            ->assertJsonPath('data.discipline', null);

        // Discipline Officer has widget
        $resAdmin = $this->actingAs($admin)->getJson('/api/v1/mobile/tasks');
        $resAdmin->assertOk()
            ->assertJsonPath('data.discipline.can_access', true)
            ->assertJsonPath('data.discipline.summary.waiting_verification_count', 1);

        // Regular teacher cannot verify
        $resForbidden = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/discipline/verify', [
            'incident_id' => $incident->id,
            'verification_status' => 'terverifikasi',
        ]);
        $resForbidden->assertStatus(403);

        // Discipline officer can verify
        $resVerify = $this->actingAs($admin)->postJson('/api/v1/mobile/tasks/discipline/verify', [
            'incident_id' => $incident->id,
            'verification_status' => 'terverifikasi',
            'verification_notes' => 'Sudah dikonfirmasi dan poin dicatat.',
        ]);
        $resVerify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verification_status', 'terverifikasi');
    }

    public function test_unified_live_monitoring_for_principal_and_homeroom(): void
    {
        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $admin = User::create([
            'name' => 'Kepala Sekolah Penguji',
            'username' => 'ks_penguji',
            'email' => 'ks_penguji@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $resAdmin = $this->actingAs($admin)->getJson('/api/v1/mobile/tasks');
        $resAdmin->assertOk()
            ->assertJsonPath('data.live_monitoring.role_key', 'principal')
            ->assertJsonPath('data.live_monitoring.can_view_student_all', true)
            ->assertJsonPath('data.live_monitoring.can_view_gtk', true)
            ->assertJsonStructure([
                'data' => [
                    'live_monitoring' => [
                        'role_key',
                        'role_label',
                        'scope_label',
                        'can_view_student_all',
                        'can_view_gtk',
                        'can_view_bk',
                        'siswa' => [
                            'hadir',
                            'telat',
                            'sakit',
                            'izin',
                            'dispen',
                            'pulang',
                            'alfa',
                            'bolos',
                            'detail' => ['hadir', 'telat', 'sakit', 'izin', 'dispen', 'pulang', 'alfa', 'bolos'],
                        ],
                        'gtk' => [
                            'total_expected',
                            'hadir',
                            'terlambat',
                            'belum_masuk',
                            'izin',
                            'inval',
                            'unattended_kbm',
                            'detail' => ['hadir', 'terlambat', 'telat', 'belum_masuk', 'izin', 'inval', 'unattended_kbm', 'unattended'],
                        ],
                        'timeline',
                    ],
                ],
            ]);

        $resAdminKbm = $this->actingAs($admin)->getJson('/api/v1/mobile/kbm');
        $resAdminKbm->assertOk()
            ->assertJsonPath('data.live_monitoring.role_key', 'principal')
            ->assertJsonPath('data.live_monitoring.can_view_student_all', true)
            ->assertJsonPath('data.live_monitoring.can_view_gtk', true)
            ->assertJsonStructure([
                'data' => [
                    'live_monitoring' => [
                        'role_key',
                        'role_label',
                        'scope_label',
                    ],
                    'bk',
                ],
            ]);
    }

    public function test_live_monitoring_aggregates_kbm_late_records_and_details(): void
    {
        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $admin = User::create([
            'name' => 'Kepala Sekolah Monitoring',
            'username' => 'ks_monitoring',
            'email' => 'ks_monitoring@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2024/2025'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        $studentLate = Student::create([
            'nis' => '11111',
            'name' => 'Budi Terlambat KBM',
            'classroom_id' => $classroom->id,
            'status' => 'active',
            'academic_year' => '2024/2025',
        ]);

        $subject = Subject::firstOrCreate(['code' => 'MTK01'], ['name' => 'Matematika Terapan']);

        $session = SubjectAttendanceSession::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'attendance_date' => now()->toDateString(),
            'start_period' => 1,
            'end_period' => 2,
            'status' => 'completed',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session->id,
            'student_id' => $studentLate->id,
            'status' => 't',
            'notes' => 'Terlambat 15 menit',
        ]);

        $res = $this->actingAs($admin)->getJson('/api/v1/mobile/kbm');
        $res->assertOk();

        $siswa = $res->json('data.live_monitoring.siswa');
        $this->assertGreaterThanOrEqual(1, $siswa['telat']);
        $telatNames = collect($siswa['detail']['telat'])->pluck('name')->map(fn ($n) => strtoupper($n));
        $this->assertTrue($telatNames->contains('BUDI TERLAMBAT KBM'));
    }

    public function test_mobile_students_smart_search_behavior(): void
    {
        $unauth = $this->getJson('/api/v1/mobile/students/search?q=Ahmad');
        $unauth->assertStatus(401);

        // Query kurang dari 3 karakter
        $shortQuery = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/students/search?q=Ah');
        $shortQuery->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'Ketik minimal 3 karakter untuk mencari siswa.');

        // Buat kelas & siswa untuk pengujian
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $classroom = Classroom::first() ?? Classroom::create([
            'name' => 'X TKJ 1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'name' => 'Ahmad Rizky Pratama',
            'nisn' => '0098765432',
            'nis' => '10291',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);

        $student2 = Student::create([
            'name' => 'Budi Santoso Utomo',
            'nisn' => '0011223344',
            'nis' => '10292',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);

        // Cari berdasarkan nama (>= 3 karakter)
        $searchName = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/students/search?q=Ahm');
        $searchName->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student1->id)
            ->assertJsonPath('data.0.name', 'AHMAD RIZKY PRATAMA')
            ->assertJsonPath('data.0.classroom_name', $classroom->name);

        // Cari berdasarkan NISN (>= 3 karakter)
        $searchNisn = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/students/search?q=9876');
        $searchNisn->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student1->id);
    }

    public function test_mobile_profile_activity_logs_feed(): void
    {
        $unauth = $this->getJson('/api/v1/mobile/profile/activity-logs');
        $unauth->assertStatus(401);

        // Buat log aktivitas untuk guru penguji
        UserActivityLog::create([
            'user_id' => $this->teacher->id,
            'user_name' => $this->teacher->name,
            'user_username' => $this->teacher->username,
            'role_name' => 'Guru',
            'action' => 'update_profile',
            'description' => 'Memperbarui data profil akun via SIAP Mobile.',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) Mobile',
            'properties' => ['source' => 'Mobile'],
        ]);

        AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => $this->teacher->nigs ?: (string) $this->teacher->id,
            'user_type' => 'teacher',
            'user_id' => $this->teacher->id,
            'name' => $this->teacher->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'work_code' => 'IN_RADIUS',
            'raw_payload' => json_encode(['source' => 'PWA_MOBILE', 'distance_to_school_meters' => 15]),
        ]);

        // 1. Cek endpoint profile utama memuat activity_logs
        $profileRes = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/profile');
        $profileRes->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'activity_logs',
                ],
            ]);

        // 2. Cek endpoint dedicated profile/activity-logs
        $actRes = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/profile/activity-logs');
        $actRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source_type',
                        'action',
                        'category',
                        'badge_color',
                        'title',
                        'description',
                        'channel',
                        'timestamp',
                        'date_ymd',
                        'date_formatted',
                        'time_human',
                    ],
                ],
            ]);

        // 3. Cek filter tanggal hari ini vs tanggal lain
        $todayStr = now()->toDateString();
        $todayRes = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/profile/activity-logs?date='.$todayStr);
        $todayRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $pastRes = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/profile/activity-logs?date=2020-01-01');
        $pastRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_mobile_piket_endpoints_and_operations(): void
    {
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => '10', 'level' => 10]);
        $dept = Department::first() ?? Department::create(['name' => 'TKJ', 'code' => 'TKJ']);
        $classroom = Classroom::first() ?? Classroom::create([
            'name' => 'X-TKJ-1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $student = Student::first() ?? Student::create([
            'name' => 'Siswa Tes Piket',
            'nisn' => '1122334455',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $todayDay,
            'teacher_id' => $this->teacher->id,
            'role' => 'koordinator',
            'duty_location' => 'Meja Piket Utama',
            'is_active' => true,
        ]);

        // 1. Get Piket Workspace Data
        $res = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/tasks/piket');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scheduled_officers.0.teacher_name', $this->teacher->name)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'today',
                    'today_day',
                    'is_on_duty_today',
                    'can_operate',
                    'stats',
                    'permits',
                    'incidents',
                    'invals',
                    'guests',
                    'helpers',
                ],
            ]);

        // 2. Terbitkan Surat Izin Siswa (classroom_id dan period_assigned otomatis dihitung backend)
        $permitPayload = [
            'permit_type' => 'terlambat',
            'student_id' => $student->id,
            'reason' => 'Rantai sepeda putus',
            'entry_time' => '07:35',
            'action_taken' => 'Dinasihati dan diberikan surat izin masuk',
        ];
        $permitRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/piket/permits/store', $permitPayload);
        $permitRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classroom_id', $classroom->id)
            ->assertJsonPath('data.period_assigned', '1')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'permit_number',
                    'classroom_id',
                    'period_assigned',
                    'print_url',
                ],
                'print_url',
            ]);

        $this->assertStringContainsString('autoprint=1', $permitRes->json('print_url'));

        // 3. Catat Kejadian Siswa (Bisa Lebih Dari 1 Siswa & kelas otomatis terisi)
        $student2 = Student::create([
            'name' => 'Siswa Tes Piket Kedua',
            'nisn' => '9988776655',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);

        $incPayload = [
            'student_ids' => [$student->id, $student2->id],
            'category' => 'seragam',
            'incident_time' => '08:00',
            'description' => 'Tidak mengenakan dasi dan ikat pinggang standar',
            'action_taken' => 'Diberikan pembinaan dan teguran',
            'follow_up_target' => 'selesai_di_piket',
        ];
        $incRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/piket/incidents/store', $incPayload);
        $incRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'incidents');

        $this->assertDatabaseHas('piket_incidents', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'category' => 'seragam',
        ]);

        $this->assertDatabaseHas('piket_incidents', [
            'student_id' => $student2->id,
            'classroom_id' => $classroom->id,
            'category' => 'seragam',
        ]);

        // 4. Catat Guru Inval
        $invalPayload = [
            'classroom_id' => $classroom->id,
            'absent_teacher_id' => $this->teacher->id,
            'period_start' => 3,
            'period_end' => 4,
            'reason' => 'Tugas dinas luar',
            'assignment_material' => 'LKS hal 20',
        ];
        $invalRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/piket/invals/store', $invalPayload);
        $invalRes->assertOk()
            ->assertJsonPath('success', true);

        // 5. Catat Buku Tamu & Checkout
        $guestPayload = [
            'guest_name' => 'Bpk. Hendro',
            'institution' => 'Wali Murid',
            'phone' => '081234567890',
            'purpose' => 'Konsultasi nilai siswa',
            'meet_with' => 'Wali Kelas X-TKJ-1',
        ];
        $guestRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/piket/guests/store', $guestPayload);
        $guestRes->assertOk()
            ->assertJsonPath('success', true);
        $guestId = $guestRes->json('data.id');

        $checkoutRes = $this->actingAs($this->teacher)->postJson("/api/v1/mobile/tasks/piket/guests/{$guestId}/checkout");
        $checkoutRes->assertOk()
            ->assertJsonPath('success', true);

        // 6. Tutup Jurnal Piket
        $closeRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/tasks/piket/journal/close', [
            'general_notes' => 'Situasi KBM dan piket hari ini tertib dan aman.',
        ]);
        $closeRes->assertOk()
            ->assertJsonPath('success', true);

        // 7. Verifikasi Hak Akses Piket untuk Superadmin / Manajemen
        $adminRole = Role::where('slug', 'super_admin')->first() ?? Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $adminUser = User::create([
            'name' => 'Super Administrator',
            'username' => 'superadmintes',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $tasksAdminRes = $this->actingAs($adminUser)->getJson('/api/v1/mobile/tasks');
        $tasksAdminRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.piket.can_access', true)
            ->assertJsonPath('data.piket.is_management', true);

        $dashAdminRes = $this->actingAs($adminUser)->getJson('/api/v1/mobile/dashboard');
        $dashAdminRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.piket_info.can_access', true)
            ->assertJsonPath('data.piket_info.is_management', true);

        $piketAdminRes = $this->actingAs($adminUser)->getJson('/api/v1/mobile/tasks/piket');
        $piketAdminRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_management', true)
            ->assertJsonPath('data.can_operate', true);
    }

    public function test_mobile_gtk_permit_with_assignment_attachment_and_inval_schedules(): void
    {
        Storage::fake('local');

        $activeYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYearStr = $activeYear ? $activeYear->name : '2026/2027';
        $semesterStr = $activeYear ? $activeYear->semester : 'Ganjil';

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';

        // Set teacher as piket officer today
        PiketSchedule::create([
            'academic_year' => $academicYearStr,
            'semester' => $semesterStr,
            'day' => $todayDay,
            'teacher_id' => $this->teacher->id,
            'role' => 'koordinator',
            'duty_location' => 'Meja Piket Utama',
            'is_active' => true,
        ]);

        $dept = Department::first() ?? Department::create(['code' => 'TKJ', 'name' => 'Teknik Komputer']);
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => 'X', 'level' => 10]);
        $classroom = Classroom::create([
            'name' => 'X-TKJ-1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'academic_year' => $academicYearStr,
            'is_active' => true,
        ]);
        $subject = Subject::first() ?? Subject::create([
            'name' => 'Pemrograman Web',
            'code' => 'WEB01',
        ]);

        // Buat jadwal mengajar untuk guru pada hari ini
        LessonSchedule::create([
            'academic_year' => $academicYearStr,
            'semester' => $semesterStr,
            'day' => $todayDay,
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'start_period' => 1,
            'end_period' => 2,
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'academic_year' => $academicYearStr,
            'semester' => $semesterStr,
            'day' => $todayDay,
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'start_period' => 3,
            'end_period' => 4,
            'is_active' => true,
        ]);

        // 1. Submit Permohonan Izin GTK dengan lampiran dokumen tugas
        $assignmentFile = UploadedFile::fake()->create('tugas_kbm_mandiri.pdf', 300, 'application/pdf');
        $permitRes = $this->actingAs($this->teacher)->post('/api/v1/mobile/tasks/permits/store', [
            'permission_type' => 'izin',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
            'reason' => 'Menghadiri undangan dinas luar',
            'assignment_document' => $assignmentFile,
        ], ['Accept' => 'application/json']);

        $permitRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('gtk_permits', [
            'user_id' => $this->teacher->id,
            'assignment_document_name' => 'tugas_kbm_mandiri.pdf',
        ]);

        $gtkPermit = GtkPermit::where('user_id', $this->teacher->id)->latest('id')->first();
        $this->assertNotNull($gtkPermit->assignment_document_path);
        Storage::disk('local')->assertExists($gtkPermit->assignment_document_path);

        // Download attachment via download route
        $downloadRes = $this->actingAs($this->teacher)->get(route('attendance.gtk-permits.assignment', $gtkPermit));
        $downloadRes->assertOk();

        // 2. Cek endpoint jadwal mengajar guru untuk inval (/tasks/piket/invals/teacher-schedules)
        $schedRes = $this->actingAs($this->teacher)->getJson("/api/v1/mobile/tasks/piket/invals/teacher-schedules?teacher_id={$this->teacher->id}");
        $schedRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_schedules', 2)
            ->assertJsonPath('data.active_permit.assignment_document_name', 'tugas_kbm_mandiri.pdf')
            ->assertJsonCount(2, 'data.schedules');

        // 3. Simpan penugasan inval untuk 1 hari penuh (schedule_id = 'all') dengan file tugas
        $invalFile = UploadedFile::fake()->create('panduan_inval.docx', 150);
        $invalBatchRes = $this->actingAs($this->teacher)->post('/api/v1/mobile/tasks/piket/invals/store', [
            'absent_teacher_id' => $this->teacher->id,
            'schedule_id' => 'all',
            'reason' => 'Izin dinas luar',
            'assignment_material' => 'Mengerjakan modul di e-learning',
            'assignment_file' => $invalFile,
        ], ['Accept' => 'application/json']);

        $invalBatchRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('piket_inval_classes', [
            'classroom_id' => $classroom->id,
            'absent_teacher_id' => $this->teacher->id,
            'period_start' => 1,
            'period_end' => 2,
            'assignment_file_name' => 'panduan_inval.docx',
        ]);

        $this->assertDatabaseHas('piket_inval_classes', [
            'classroom_id' => $classroom->id,
            'absent_teacher_id' => $this->teacher->id,
            'period_start' => 3,
            'period_end' => 4,
            'assignment_file_name' => 'panduan_inval.docx',
        ]);

        // Cek data piket memuat url file tugas inval dan gtk_permits_today
        $piketRes = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/tasks/piket');
        $piketRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($piketRes->json('data.gtk_permits_today'));
        $this->assertNotEmpty($piketRes->json('data.invals.0.assignment_file_url'));
    }

    public function test_can_get_and_store_kbm_take_attendance_via_mobile_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 08:00:00'));

        $activeYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYearStr = $activeYear->name;
        $semesterStr = $activeYear->semester ?? 'ganjil';

        $dept = Department::first() ?? Department::create(['name' => 'Teknik', 'code' => 'TK']);
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => 'X', 'numeric_level' => 10]);
        $classroom = Classroom::create([
            'name' => 'X TK 2',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        $subject = Subject::first() ?? Subject::create([
            'name' => 'Matematika Teknik',
            'code' => 'MTK-TK',
        ]);

        $student1 = Student::create([
            'name' => 'Budi Santoso',
            'nis' => '1001',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'name' => 'Siti Aisyah',
            'nis' => '1002',
            'gender' => 'P',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $schedule = LessonSchedule::create([
            'academic_year' => $academicYearStr,
            'semester' => $semesterStr,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'day' => 'Jumat',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'is_active' => true,
        ]);

        // 1. GET take attendance data
        $getRes = $this->actingAs($this->teacher)->getJson("/api/v1/mobile/kbm/take?schedule_id={$schedule->id}");
        $getRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schedule.id', $schedule->id)
            ->assertJsonPath('data.schedule.classroom_name', 'X TK 2')
            ->assertJsonPath('data.session.is_attended', false)
            ->assertJsonCount(2, 'data.students');

        // 2. POST store attendance data
        $storeRes = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/kbm/take/store', [
            'schedule_id' => $schedule->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'start_period' => 1,
            'end_period' => 2,
            'date' => '2026-10-30',
            'topic' => 'Persamaan Linear Satu Variabel',
            'notes' => 'Siswa aktif bertanya dan mengerjakan latihan bab 1',
            'attendance' => [
                [
                    'student_id' => $student1->id,
                    'status' => 'h',
                    'notes' => null,
                ],
                [
                    'student_id' => $student2->id,
                    'status' => 's',
                    'notes' => 'Surat dokter',
                ],
            ],
        ]);

        $storeRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_attended', true);

        $this->assertDatabaseHas('subject_attendance_sessions', [
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'topic' => 'Persamaan Linear Satu Variabel',
        ]);

        $this->assertDatabaseHas('subject_attendance_records', [
            'student_id' => $student1->id,
            'status' => 'h',
        ]);

        $this->assertDatabaseHas('subject_attendance_records', [
            'student_id' => $student2->id,
            'status' => 's',
            'notes' => 'Surat dokter',
        ]);

        // 3. GET take data lagi untuk memastikan is_attended = true
        $recheckRes = $this->actingAs($this->teacher)->getJson("/api/v1/mobile/kbm/take?schedule_id={$schedule->id}");
        $recheckRes->assertOk()
            ->assertJsonPath('data.session.is_attended', true)
            ->assertJsonPath('data.session.topic', 'Persamaan Linear Satu Variabel')
            ->assertJsonPath('data.summary.hadir', 1)
            ->assertJsonPath('data.summary.sakit', 1);

        Carbon::setTestNow();
    }
}
