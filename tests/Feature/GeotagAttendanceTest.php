<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    protected function createTeacher(string $username = 'guru1'): User
    {
        $role = Role::where('slug', 'guru')->first();

        return User::create([
            'name' => 'Guru Test '.$username,
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => '1990'.rand(10000000, 99999999),
            'is_active' => true,
        ]);
    }

    protected function generateSyntheticDataUrl(string $format = 'jpeg'): string
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        if ($format === 'jpeg' || $format === 'jpg') {
            imagejpeg($img);
        } elseif ($format === 'png') {
            imagepng($img);
        } elseif ($format === 'webp') {
            imagewebp($img);
        }
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/'.$format.';base64,'.base64_encode($data);
    }

    public function test_valid_jpeg_selfie_base64_is_accepted_and_stored(): void
    {
        Storage::fake('local');
        $teacher = $this->createTeacher('guru_jpeg');
        $selfieBase64 = $this->generateSyntheticDataUrl('jpeg');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => $selfieBase64,
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $log = AttendanceLog::where('user_id', $teacher->id)->first();
        $this->assertNotNull($log);
        $raw = json_decode($log->raw_payload, true);
        $this->assertNotNull($raw['selfie_url']);
        $this->assertStringContainsString('.jpg', $raw['selfie_path']);
        $this->assertTrue(Storage::disk('local')->exists($raw['selfie_path']));
    }

    public function test_valid_png_selfie_base64_is_accepted_and_stored(): void
    {
        Storage::fake('local');
        $teacher = $this->createTeacher('guru_png');
        $selfieBase64 = $this->generateSyntheticDataUrl('png');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => $selfieBase64,
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $log = AttendanceLog::where('user_id', $teacher->id)->first();
        $this->assertNotNull($log);
        $raw = json_decode($log->raw_payload, true);
        $this->assertNotNull($raw['selfie_url']);
        $this->assertStringContainsString('.png', $raw['selfie_path']);
        $this->assertTrue(Storage::disk('local')->exists($raw['selfie_path']));
    }

    public function test_valid_webp_selfie_base64_is_accepted_and_stored(): void
    {
        Storage::fake('local');
        $teacher = $this->createTeacher('guru_webp');
        $selfieBase64 = $this->generateSyntheticDataUrl('webp');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => $selfieBase64,
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $log = AttendanceLog::where('user_id', $teacher->id)->first();
        $this->assertNotNull($log);
        $raw = json_decode($log->raw_payload, true);
        $this->assertNotNull($raw['selfie_url']);
        $this->assertStringContainsString('.webp', $raw['selfie_path']);
        $this->assertTrue(Storage::disk('local')->exists($raw['selfie_path']));
    }

    public function test_corrupted_base64_selfie_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_corrupt');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => 'data:image/jpeg;base64,!!!NotAValidBase64String@@@###===',
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_disallowed');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => 'data:image/gif;base64,'.base64_encode('GIF89a Fake Gif Content'),
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_empty_selfie_base64_payload_is_handled_gracefully(): void
    {
        $teacher = $this->createTeacher('guru_empty_selfie');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => '',
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        // When selfie is not required, empty selfie string is accepted gracefully without error
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_payload_exceeding_max_size_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_oversized');

        // Payload with 8 million characters exceeds 7.5MB safety threshold
        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => 'data:image/jpeg;base64,'.str_repeat('A', 8000000),
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_non_image_content_claimed_as_image_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_fake_image');

        // Header claims image/jpeg, but body is PHP code
        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
            'selfie_base64' => 'data:image/jpeg;base64,'.base64_encode('<?php echo "Not an image"; ?>'),
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_latitude_or_longitude_out_of_range_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_out_of_bounds');

        // Latitude > 90
        $payload = [
            'latitude' => 95.000000,
            'longitude' => 111.97260000,
            'accuracy' => 10,
            'type' => 'check_in',
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);
        $response->assertStatus(422);

        // Longitude > 180
        $payload2 = [
            'latitude' => -7.95591000,
            'longitude' => 195.000000,
            'accuracy' => 10,
            'type' => 'check_in',
        ];

        $response2 = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload2);
        $response2->assertStatus(422);
    }

    public function test_invalid_accuracy_value_is_rejected(): void
    {
        $teacher = $this->createTeacher('guru_invalid_accuracy');

        // Negative accuracy
        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => -5,
            'type' => 'check_in',
        ];

        $response = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload);
        $response->assertStatus(422);

        // Excessively poor GPS accuracy (> 1000 meters)
        $payload2 = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 1500,
            'type' => 'check_in',
        ];

        $response2 = $this->actingAs($teacher)->postJson(route('attendance.geotag.store'), $payload2);
        $response2->assertStatus(422);
        $response2->assertJson(['success' => false]);
    }

    public function test_attendance_via_pwa_mobile_records_mobile_source(): void
    {
        $teacher = $this->createTeacher('guru_mobile');

        $payload = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 8,
            'type' => 'check_in',
        ];

        $response = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'])
            ->postJson(route('attendance.geotag.store'), $payload);

        $response->assertStatus(200);

        $log = AttendanceLog::where('user_id', $teacher->id)->first();
        $this->assertNotNull($log);
        $raw = json_decode($log->raw_payload, true);
        $this->assertEquals('PWA_MOBILE', $raw['source']);
    }

    public function test_selfie_required_policy_enforcement(): void
    {
        $setting = SchoolSetting::getSetting();
        $setting->update(['geotag_require_selfie' => true]);

        $teacher = $this->createTeacher('guru_require_selfie');

        // Desktop user agent without selfie -> rejected
        $payloadNoSelfie = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 8,
            'type' => 'check_in',
        ];

        $response = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->postJson(route('attendance.geotag.store'), $payloadNoSelfie);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        // Desktop with valid selfie -> accepted
        $payloadWithSelfie = [
            'latitude' => -7.95591000,
            'longitude' => 111.97260000,
            'accuracy' => 8,
            'type' => 'check_in',
            'selfie_base64' => $this->generateSyntheticDataUrl('jpeg'),
        ];

        $response2 = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->postJson(route('attendance.geotag.store'), $payloadWithSelfie);

        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);
    }
}
