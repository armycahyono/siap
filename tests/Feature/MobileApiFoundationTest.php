<?php

namespace Tests\Feature;

use App\Models\PiketIncident;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\AcademicYearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                    'user' => ['id', 'name', 'username', 'email'],
                    'subjects',
                    'structural_positions',
                    'homeroom_classrooms',
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
        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/kbm');

        $response->assertOk()
            ->assertJsonPath('success', true)
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
}
