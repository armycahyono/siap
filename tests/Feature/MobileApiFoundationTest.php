<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
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
            'school_name' => 'SMK PGRI 1 NGAWI',
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
}
