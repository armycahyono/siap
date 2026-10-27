<?php

namespace Tests\Feature;

use App\Http\Controllers\Attendance\AdmsController;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceBiometric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdmsCommKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Helper to create an active device with given CommKey value */
    private function createDevice(string $sn, $commKey): AttendanceDevice
    {
        return AttendanceDevice::create([
            'name' => 'Test Device',
            'device_sn' => $sn,
            'ip_address' => '192.168.1.100',
            'is_active' => true,
            'comm_key' => $commKey,
            'status' => 'online',
        ]);
    }

    /** Helper to perform a POST /iclock/cdata request */
    private function postCdata(string $sn, array $query = [], array $headers = [], $payload = "GUR-001\t2026-10-25 07:15:00\t1\t0\t0\n")
    {
        $url = '/iclock/cdata?SN='.$sn;
        if (! empty($query)) {
            $url .= '&'.http_build_query($query);
        }

        return $this->call('POST', $url, [], [], [], $headers, $payload);
    }

    // 1. CommKey database NULL - reject (cannot store NULL, use placeholder)
    public function test_null_comm_key_is_rejected(): void
    {
        $device = $this->createDevice('SN-NULL', 'invalid');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'anykey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 2. CommKey empty string - reject
    public function test_empty_comm_key_is_rejected(): void
    {
        $device = $this->createDevice('SN-EMPTY', '');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'anykey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 3. CommKey literal '0' - reject
    public function test_zero_string_comm_key_is_rejected(): void
    {
        $device = $this->createDevice('SN-ZERO', '0');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'anykey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 4. CommKey whitespace‑only stored - reject
    public function test_whitespace_comm_key_is_rejected(): void
    {
        $device = $this->createDevice('SN-SPACE', '   ');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'anykey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 5. CommKey request whitespace‑only parameter - reject
    public function test_whitespace_comm_key_parameter_is_rejected(): void
    {
        $device = $this->createDevice('SN-WHITEREQ', 'validKey');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => '   ']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 6. Valid CommKey with matching parameter (PushCommKey) – body
    public function test_valid_comm_key_with_pushcommkey_is_accepted(): void
    {
        $device = $this->createDevice('SN-VALID-1', 'secret123');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'secret123'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 7. Valid CommKey with matching parameter (commkey) – body
    public function test_valid_comm_key_with_commkey_is_accepted(): void
    {
        $device = $this->createDevice('SN-VALID-2', 'abcDEF');
        $response = $this->postCdata($device->device_sn, ['commkey' => 'abcDEF'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 8. Valid CommKey with matching parameter (comm_key) – body
    public function test_valid_comm_key_with_comm_key_is_accepted(): void
    {
        $device = $this->createDevice('SN-VALID-3', 'myKey');
        $response = $this->postCdata($device->device_sn, ['comm_key' => 'myKey'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 9. Missing CommKey parameter - reject
    public function test_missing_comm_key_parameter_is_rejected(): void
    {
        $device = $this->createDevice('SN-MISS', 'present');
        $response = $this->postCdata($device->device_sn);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 10. Empty CommKey parameter - reject
    public function test_empty_comm_key_parameter_is_rejected(): void
    {
        $device = $this->createDevice('SN-EMPTY-PARAM', 'present');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => '']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 11. Wrong CommKey value - reject
    public function test_wrong_comm_key_parameter_is_rejected(): void
    {
        $device = $this->createDevice('SN-WRONG', 'correctKey');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'incorrectKey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 12. Inactive device should be rejected even with correct CommKey
    public function test_inactive_device_is_rejected_even_with_correct_comm_key(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Inactive Device',
            'device_sn' => 'SN-INACTIVE',
            'ip_address' => '192.168.1.101',
            'is_active' => false,
            'comm_key' => 'validKey',
            'status' => 'online',
        ]);
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'validKey']);
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
    }

    // 13. Query‑only CommKey acceptance (PushCommKey)
    public function test_query_only_pushcommkey_is_accepted(): void
    {
        $device = $this->createDevice('SN-QUERY-1', 'queryKey1');
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'queryKey1'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 14. Query‑only CommKey acceptance (commkey)
    public function test_query_only_commkey_is_accepted(): void
    {
        $device = $this->createDevice('SN-QUERY-2', 'queryKey2');
        $response = $this->postCdata($device->device_sn, ['commkey' => 'queryKey2'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 15. Query‑only CommKey acceptance (comm_key)
    public function test_query_only_comm_key_is_accepted(): void
    {
        $device = $this->createDevice('SN-QUERY-3', 'queryKey3');
        $response = $this->postCdata($device->device_sn, ['comm_key' => 'queryKey3'], ['REMOTE_ADDR' => $device->ip_address]);
        $response->assertStatus(200);
        $response->assertDontSee('OK: 0');
    }

    // 16. Null stored CommKey should be rejected via protected validation
    public function test_null_stored_comm_key_is_rejected_via_protected_method(): void
    {
        $device = new AttendanceDevice([
            'name' => 'NullKeyDevice',
            'device_sn' => 'SN-NULL-DEV',
            'ip_address' => '10.0.0.1',
            'is_active' => true,
            'comm_key' => null,
            'status' => 'online',
        ]);
        $request = Request::create('/iclock/cdata', 'POST', ['PushCommKey' => 'any']);
        $controller = new class extends AdmsController
        {
            public function exposeVerifyDeviceCommKey(Request $request, AttendanceDevice $device): bool
            {
                return $this->verifyDeviceCommKey($request, $device);
            }
        };
        $result = $controller->exposeVerifyDeviceCommKey($request, $device);
        $this->assertFalse($result);
    }

    // 17. No side‑effects when CommKey validation fails
    public function test_no_side_effects_when_comm_key_validation_fails(): void
    {
        $device = $this->createDevice('SN-SIDE-EFF', 'validKey');
        AttendanceDeviceBiometric::truncate();
        $initialCount = AttendanceDeviceBiometric::count();
        $response = $this->postCdata($device->device_sn, ['PushCommKey' => 'badKey'], [], "GUR-001\t2026-10-25 07:15:00\t1\t0\t0\n");
        $response->assertStatus(200);
        $response->assertSee('OK: 0');
        $this->assertEquals($initialCount, AttendanceDeviceBiometric::count(), 'Biometric records should not be created on failed CommKey validation');
    }
}
