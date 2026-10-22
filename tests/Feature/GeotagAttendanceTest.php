<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeotagAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Configure School Setting with exact known coordinates
        $setting = SchoolSetting::getSetting();
        $setting->update([
            'school_latitude' => -7.95590200,
            'school_longitude' => 111.97259400,
            'school_radius_meters' => 100,
            'geotag_attendance_enabled' => true,
            'geotag_strict_radius' => true,
            'geotag_require_selfie' => false,
        ]);
    }

    public function test_guest_is_redirected_from_geotag_page(): void
    {
        $response = $this->get(route('attendance.geotag.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_teacher_can_view_geotag_page(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Budi Santoso, S.Pd.',
            'username' => 'budis',
            'email' => 'budis@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => '198501012010011001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->get(route('attendance.geotag.index'));
        $response->assertStatus(200);
        $response->assertSee('Presensi Geotagging GPS GTK');
        $response->assertSee('Budi Santoso, S.Pd.');
    }

    public function test_teacher_can_check_in_within_school_radius(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Siti Aminah, S.Kom.',
            'username' => 'sitia',
            'email' => 'sitia@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => '199002022015022002',
            'is_active' => true,
        ]);

        // Coordinates very close to school (~10 meters away)
        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 5,
            'type' => 'check_in',
            'notes' => 'Hadir tepat waktu',
        ];

        $response = $this->actingAs($teacher)->post(route('attendance.geotag.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $teacher->id,
            'user_type' => 'teacher',
            'device_sn' => 'MOBILE-PWA',
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'work_code' => 'IN_RADIUS',
        ]);

        $log = AttendanceLog::where('user_id', $teacher->id)->first();
        $this->assertNotNull($log);
        $raw = json_decode($log->raw_payload, true);
        $this->assertTrue($raw['is_within_radius']);
        $this->assertLessThanOrEqual(100, $raw['distance_to_school_meters']);
    }

    public function test_teacher_outside_radius_is_rejected_when_strict_radius_enabled(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Ahmad Fauzi, M.Pd.',
            'username' => 'ahmadf',
            'email' => 'ahmadf@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => '198703032012031003',
            'is_active' => true,
        ]);

        // Far away coordinates (e.g. ~10 km away)
        $payload = [
            'latitude' => -8.05000000,
            'longitude' => 112.05000000,
            'accuracy' => 10,
            'type' => 'check_in',
            'notes' => 'Di luar jangkauan',
        ];

        $response = $this->actingAs($teacher)->post(route('attendance.geotag.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendance_logs', [
            'user_id' => $teacher->id,
            'device_sn' => 'MOBILE-PWA',
        ]);
    }

    public function test_teacher_can_check_out_after_check_in(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Dewi Lestari, S.Pd.',
            'username' => 'dewil',
            'email' => 'dewil@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => '199204042018042004',
            'is_active' => true,
        ]);

        // Check in first
        AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => $teacher->nigs,
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => $teacher->name,
            'scanned_at' => Carbon::now()->subHours(8),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'work_code' => 'IN_RADIUS',
        ]);

        // Now check out
        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 8,
            'type' => 'check_out',
            'notes' => 'Selesai KBM & pulang',
        ];

        $response = $this->actingAs($teacher)->post(route('attendance.geotag.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $teacher->id,
            'user_type' => 'teacher',
            'status' => 'check_out',
            'verify_type' => 'geotag',
        ]);
    }
}
