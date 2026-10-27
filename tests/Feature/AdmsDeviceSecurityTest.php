<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceBiometric;
use App\Models\AttendanceDeviceCommand;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmsDeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createDummyClassroom(): Classroom
    {
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => 'X', 'level' => 10]);
        $dept = Department::first() ?? Department::create(['name' => 'Teknik Komputer', 'code' => 'TKJ']);

        return Classroom::create([
            'name' => 'X TKJ 1',
            'academic_year' => '2026/2027',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
    }

    // ==========================================
    // A. Pengujian Keabsahan Perangkat & IP (1 - 3)
    // ==========================================

    /**
     * Skenario 1: Perangkat aktif dengan IP cocok dapat berkomunikasi.
     */
    public function test_01_active_device_with_matching_ip_can_communicate(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Solution X601 Lab',
            'device_sn' => 'SN-SOL-001',
            'ip_address' => '192.168.1.100',
            'is_active' => true,
            'status' => 'online',
        ]);

        $response = $this->call('GET', '/iclock/cdata?SN=SN-SOL-001&options=all', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $response->assertStatus(200);
        $response->assertSee('GET OPTION FROM: SN-SOL-001');
    }

    /**
     * Skenario 2: Perangkat aktif yang dihubungi dari IP berbeda ditolak/diabaikan dan IP terdaftar TIDAK berubah.
     */
    public function test_02_active_device_with_mismatched_ip_is_rejected_and_device_ip_not_overwritten(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Solution X601 Lab',
            'device_sn' => 'SN-SOL-001',
            'ip_address' => '192.168.1.100',
            'is_active' => true,
            'status' => 'online',
        ]);

        $spoofedIp = '192.168.1.222';

        $payload = "GUR-001\t2026-10-25 07:15:00\t1\t0\t0\n";
        $response = $this->call('POST', '/iclock/cdata?SN=SN-SOL-001&table=ATTLOG', [], [], [], [
            'REMOTE_ADDR' => $spoofedIp,
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 0');

        // Pastikan IP perangkat tidak tertimpa oleh IP penyerang/spoofed
        $device->refresh();
        $this->assertEquals('192.168.1.100', $device->ip_address);
    }

    /**
     * Skenario 3: Perangkat baru/tidak aktif ditolak atau diabaikan saat push data.
     */
    public function test_03_unregistered_or_inactive_device_push_is_ignored(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Perangkat Baru Belum Verifikasi',
            'device_sn' => 'SN-NEW-999',
            'ip_address' => '192.168.1.105',
            'is_active' => false,
            'status' => 'online',
        ]);

        $payload = "GUR-001\t2026-10-25 07:15:00\t1\t0\t0\n";
        $response = $this->call('POST', '/iclock/cdata?SN=SN-NEW-999&table=ATTLOG', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.105',
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 0');
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    // ==========================================
    // B. Pengujian Endpoint getrequest (4 - 7)
    // ==========================================

    /**
     * Skenario 4: Perangkat terdaftar dan aktif dari IP cocok menerima antrean perintah yang ditujukan kepadanya.
     */
    public function test_04_active_device_with_matching_ip_receives_queued_commands(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Utama',
            'device_sn' => 'SN-ACTIVE-01',
            'ip_address' => '192.168.1.50',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-ACTIVE-01',
            'command' => 'INFO',
            'status' => 'pending',
        ]);

        $response = $this->call('GET', '/iclock/getrequest?SN=SN-ACTIVE-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.50',
        ]);

        $response->assertStatus(200);
        $response->assertSee("C:{$command->id}:INFO");

        $command->refresh();
        $this->assertEquals('sent', $command->status);
    }

    /**
     * Skenario 5: Pemanggilan getrequest dari IP yang berbeda atau perangkat tidak aktif mengembalikan respons netral tanpa payload perintah.
     */
    public function test_05_getrequest_from_mismatched_ip_or_inactive_device_returns_neutral_ok(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Utama',
            'device_sn' => 'SN-ACTIVE-01',
            'ip_address' => '192.168.1.50',
            'is_active' => true,
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-ACTIVE-01',
            'command' => 'DATA USER PIN=123',
            'status' => 'pending',
        ]);

        // Request dari IP berbeda (spoofed)
        $response = $this->call('GET', '/iclock/getrequest?SN=SN-ACTIVE-01', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99',
        ]);

        $response->assertStatus(200);
        $response->assertSee("OK\n");
        $response->assertDontSee('DATA USER');
    }

    /**
     * Skenario 6: Pemanggilan getrequest yang tidak sah TIDAK mengubah status perintah pending menjadi sent.
     */
    public function test_06_unauthorized_getrequest_does_not_mark_pending_command_as_sent(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Rahasia',
            'device_sn' => 'SN-ACTIVE-01',
            'ip_address' => '192.168.1.50',
            'is_active' => true,
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-ACTIVE-01',
            'command' => 'DATA UPDATE FINGERTMP PIN=123',
            'status' => 'pending',
        ]);

        // Panggilan dari IP tidak sah
        $this->call('GET', '/iclock/getrequest?SN=SN-ACTIVE-01', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99',
        ]);

        $command->refresh();
        $this->assertEquals('pending', $command->status);
    }

    /**
     * Skenario 7: Perintah yang ditujukan untuk perangkat A tidak dikembalikan kepada perangkat B meskipun keduanya aktif.
     */
    public function test_07_command_for_device_a_is_not_dispatched_to_device_b(): void
    {
        $deviceA = AttendanceDevice::create([
            'name' => 'Mesin A',
            'device_sn' => 'SN-DEV-A',
            'ip_address' => '192.168.1.51',
            'is_active' => true,
            'status' => 'online',
        ]);

        $deviceB = AttendanceDevice::create([
            'name' => 'Mesin B',
            'device_sn' => 'SN-DEV-B',
            'ip_address' => '192.168.1.52',
            'is_active' => true,
            'status' => 'online',
        ]);

        $commandA = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-DEV-A',
            'command' => 'REBOOT',
            'status' => 'pending',
        ]);

        // Device B polling
        $responseB = $this->call('GET', '/iclock/getrequest?SN=SN-DEV-B', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.52',
        ]);

        $responseB->assertStatus(200);
        $responseB->assertDontSee("C:{$commandA->id}:REBOOT");

        $commandA->refresh();
        $this->assertEquals('pending', $commandA->status);
    }

    // ==========================================
    // C. Pengujian Endpoint devicecmd (8 - 11)
    // ==========================================

    /**
     * Skenario 8: Konfirmasi eksekusi dari perangkat sah dengan SN, IP, dan CommKey cocok berhasil memperbarui status perintah menjadi executed.
     */
    public function test_08_legitimate_devicecmd_updates_command_to_executed(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $response->assertSee('OK');

        $command->refresh();
        $this->assertEquals('executed', $command->status);
        $this->assertNotNull($command->executed_at);
        $this->assertStringContainsString('Return=0', (string) $command->response);
    }

    /**
     * Skenario 8b: Konfirmasi tanpa CommKey ditolak dan tidak mengubah status perintah.
     */
    public function test_08b_devicecmd_without_commkey_is_rejected(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 8c: Konfirmasi dengan CommKey salah ditolak dan tidak mengubah status perintah.
     */
    public function test_08c_devicecmd_with_wrong_commkey_is_rejected(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=wrongkey', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 8d: Konfirmasi dari perangkat tidak aktif ditolak.
     */
    public function test_08d_devicecmd_from_inactive_device_is_rejected(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Nonaktif',
            'device_sn' => 'SN-CMD-INACTIVE',
            'ip_address' => '192.168.1.60',
            'is_active' => false,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-INACTIVE',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-INACTIVE&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 8e: Konfirmasi tanpa parameter SN atau SN kosong ditolak.
     */
    public function test_08e_devicecmd_without_sn_parameter_is_rejected(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 8f: Konfirmasi dengan SN yang tidak cocok dengan target command ditolak (mencegah konfirmasi milik perangkat lain).
     */
    public function test_08f_devicecmd_with_mismatched_sn_for_command_is_rejected(): void
    {
        $deviceA = AttendanceDevice::create([
            'name' => 'Mesin A',
            'device_sn' => 'SN-DEV-A',
            'ip_address' => '192.168.1.61',
            'is_active' => true,
            'comm_key' => 'secretA',
            'status' => 'online',
        ]);

        $deviceB = AttendanceDevice::create([
            'name' => 'Mesin B',
            'device_sn' => 'SN-DEV-B',
            'ip_address' => '192.168.1.62',
            'is_active' => true,
            'comm_key' => 'secretB',
            'status' => 'online',
        ]);

        $commandA = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-DEV-A',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        // Device B mencoba mengonfirmasi perintah milik Device A
        $body = "ID={$commandA->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-DEV-B&PushCommKey=secretB', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.62',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $commandA->refresh();
        $this->assertEquals('sent', $commandA->status);
        $this->assertNull($commandA->executed_at);
    }

    /**
     * Skenario 8g: Konfirmasi untuk perangkat yang belum dikonfigurasi IP address ditolak.
     */
    public function test_08g_devicecmd_rejected_when_device_ip_unconfigured(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Tanpa IP',
            'device_sn' => 'SN-NO-IP',
            'ip_address' => '',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-NO-IP',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-NO-IP&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 8h: Perintah dengan status pending tidak dapat langsung dikonfirmasi sebelum dikirim (status 'sent').
     */
    public function test_08h_devicecmd_rejects_confirmation_of_pending_command(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'pending', // Belum di-fetch via getrequest
        ]);

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('pending', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 9: Konfirmasi dari IP tidak cocok atau SN tidak cocok ditolak/diabaikan.
     */
    public function test_09_devicecmd_from_mismatched_ip_or_sn_is_ignored(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'sent',
        ]);

        // Spoofed IP
        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $command->refresh();
        $this->assertEquals('sent', $command->status); // Status TIDAK berubah
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 10: Konfirmasi palsu tidak memicu penghapusan biometrik atau aksi mutasi lain.
     */
    public function test_10_forged_devicecmd_does_not_delete_biometric_data(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        AttendanceDeviceBiometric::create([
            'device_sn' => 'SN-CMD-01',
            'pin' => 'GUR-999',
            'bio_type' => 'fingerprint',
            'finger_id' => 0,
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'CLEAR DATA',
            'status' => 'sent',
        ]);

        // Penyerang dari IP asing mencoba mengonfirmasi eksekusi perintah penghapusan
        $body = "ID={$command->id}&Return=0&CMD=CLEAR DATA";
        $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=wrongkey', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.200',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        // Biometrik harus tetap aman dan tidak terhapus
        $this->assertDatabaseHas('attendance_device_biometrics', [
            'device_sn' => 'SN-CMD-01',
            'pin' => 'GUR-999',
        ]);
        $command->refresh();
        $this->assertEquals('sent', $command->status);
        $this->assertNull($command->executed_at);
    }

    /**
     * Skenario 11: Pengiriman konfirmasi ulang untuk perintah yang sudah executed bersifat idempoten.
     */
    public function test_11_duplicate_devicecmd_confirmation_is_idempotent(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Mesin Valid',
            'device_sn' => 'SN-CMD-01',
            'ip_address' => '192.168.1.60',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $command = AttendanceDeviceCommand::create([
            'device_sn' => 'SN-CMD-01',
            'command' => 'INFO',
            'status' => 'executed',
            'response' => 'ID=1&Return=0&CMD=INFO',
            'executed_at' => Carbon::now()->subMinutes(5),
        ]);

        $executedAtOriginal = $command->executed_at;

        $body = "ID={$command->id}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=SN-CMD-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.60',
            'CONTENT_TYPE' => 'text/plain',
        ], $body);

        $response->assertStatus(200);
        $response->assertSee('OK');

        $command->refresh();
        $this->assertEquals('executed', $command->status);
        $this->assertEquals($executedAtOriginal->toDateTimeString(), $command->executed_at->toDateTimeString());
    }

    // ==========================================
    // D. Pengujian Data Ingestion (cdata & rootPush) (12 - 14)
    // ==========================================

    /**
     * Skenario 12: Request push data presensi dari IP tidak cocok tidak membuat record pada attendance_logs.
     */
    public function test_12_attendance_push_from_mismatched_ip_does_not_create_attendance_logs(): void
    {
        AttendanceDevice::create([
            'name' => 'Mesin Presensi',
            'device_sn' => 'SN-ATT-01',
            'ip_address' => '192.168.1.70',
            'is_active' => true,
            'status' => 'online',
        ]);

        $payload = "GUR-001\t2026-10-25 07:15:00\t1\t0\t0\n";
        $response = $this->call('POST', '/iclock/cdata?SN=SN-ATT-01&table=ATTLOG', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99', // Mismatched IP
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 0');
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    /**
     * Skenario 13: Request push biometrik dari IP tidak cocok tidak membuat atau memodifikasi attendance_biometrics.
     */
    public function test_13_biometric_push_from_mismatched_ip_does_not_create_or_modify_biometrics(): void
    {
        AttendanceDevice::create([
            'name' => 'Mesin Biometrik',
            'device_sn' => 'SN-BIO-01',
            'ip_address' => '192.168.1.80',
            'is_active' => true,
            'status' => 'online',
        ]);

        $payload = "USER PIN=GUR-001\tName=Guru Test\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\n"
                  ."FP PIN=GUR-001\tFID=0\tSize=500\tValid=1\tTMP=AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8gISIjJCUmJygpKissLS4vMDEyMzQ1Njc4OTo7PD0+P0BB\n";

        $response = $this->call('POST', '/iclock/cdata?SN=SN-BIO-01&table=OPERLOG', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99', // Mismatched IP
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 0');
        $this->assertDatabaseCount('attendance_biometrics', 0);
    }

    /**
     * Skenario 14: Request push dari perangkat terdaftar dan aktif dengan IP cocok berhasil mencatat log presensi.
     */
    public function test_14_attendance_push_from_legitimate_active_device_records_log(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Guru Pengajar',
            'username' => 'guru.test',
            'email' => 'gurutest@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => 'GUR-777',
            'is_active' => true,
        ]);

        AttendanceDevice::create([
            'name' => 'Mesin Sah',
            'device_sn' => 'SN-LEGIT-01',
            'ip_address' => '192.168.1.88',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        $payload = "GUR-777\t2026-10-25 07:12:00\t1\t0\t0\n";
        $response = $this->call('POST', '/iclock/cdata?SN=SN-LEGIT-01&table=ATTLOG&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.88',
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 1');

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => 'GUR-777',
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
        ]);
    }

    // ==========================================
    // E. Pengujian Endpoint Root (POST /) (15 - 17)
    // ==========================================

    /**
     * Skenario 15: Request POST biasa (misal form web atau sembarang payload text) ke root (/) ditolak dengan HTTP 400.
     */
    public function test_15_root_post_with_arbitrary_web_payload_is_rejected_with_400(): void
    {
        $response = $this->call('POST', '/', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.10',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], 'username=admin&password=secret123&action=login');

        $response->assertStatus(400);
        $response->assertSee('Bad Request: Invalid push payload format');
    }

    /**
     * Skenario 16: Request POST dengan payload Revo/ADMS yang valid dari perangkat aktif dan IP cocok diproses dengan benar.
     */
    public function test_16_root_post_with_valid_revo_payload_from_active_matching_ip_is_processed(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::create([
            'name' => 'Guru Revo',
            'username' => 'guru.revo',
            'email' => 'gururevo@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => 'GUR-888',
            'is_active' => true,
        ]);

        AttendanceDevice::create([
            'name' => 'Fingerspot Revo Mesin',
            'device_sn' => 'REVO-DEV-01',
            'ip_address' => '192.168.1.25',
            'is_active' => true,
            'comm_key' => 'secret123',
            'status' => 'online',
        ]);

        // Simulasikan payload JSON khas Fingerspot Revo
        $revoJson = json_encode([
            'fk_info' => 'WDV204',
            'data' => [
                [
                    'pin' => 'GUR-888',
                    'time' => '2026-10-25 07:10:00',
                    'status' => '1',
                ],
            ],
        ]);

        $response = $this->call('POST', '/?SN=REVO-DEV-01&PushCommKey=secret123', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.25',
            'CONTENT_TYPE' => 'application/json',
        ], $revoJson);

        $response->assertStatus(200);
        $response->assertSee('result=OK');

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => 'GUR-888',
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
        ]);
    }

    /**
     * Skenario 17: Request POST ke root dari IP tidak cocok ditolak/diabaikan.
     */
    public function test_17_root_post_from_mismatched_ip_is_ignored(): void
    {
        AttendanceDevice::create([
            'name' => 'Fingerspot Revo Mesin',
            'device_sn' => 'REVO-DEV-01',
            'ip_address' => '192.168.1.25',
            'is_active' => true,
            'status' => 'online',
        ]);

        $revoJson = json_encode([
            'fk_info' => 'WDV204',
            'data' => [
                [
                    'pin' => 'GUR-888',
                    'time' => '2026-10-25 07:10:00',
                    'status' => '1',
                ],
            ],
        ]);

        $response = $this->call('POST', '/?SN=REVO-DEV-01', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.99', // Mismatched IP
            'CONTENT_TYPE' => 'application/json',
        ], $revoJson);

        $response->assertStatus(200);
        $response->assertSee('result=OK');

        // Pastikan TIDAK mencatat log presensi
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    // ==========================================
    // F. Pengujian Batas Pendaftaran Perangkat Baru (18 - 20)
    // ==========================================

    /**
     * Skenario 18: Pendaftaran otomatis perangkat baru hanya membuat entri dengan is_active = false.
     */
    public function test_18_auto_registered_device_is_created_with_inactive_status(): void
    {
        $newSn = 'SN-NEW-BRAND-1';
        $newIp = '192.168.1.210';

        $response = $this->call('GET', "/iclock/cdata?SN={$newSn}&options=all", [], [], [], [
            'REMOTE_ADDR' => $newIp,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_devices', [
            'device_sn' => $newSn,
            'ip_address' => $newIp,
            'is_active' => false,
        ]);
    }

    /**
     * Skenario 19: Flooding request pendaftaran melebihi ambang batas tidak membuat entri perangkat tanpa batas di database.
     */
    public function test_19_flooding_auto_registration_requests_is_throttled(): void
    {
        $initialCount = AttendanceDevice::count();

        // Buat 10 request dari perangkat baru yang berbeda
        for ($i = 1; $i <= 10; $i++) {
            $sn = "FLOOD-SN-{$i}";
            $ip = "192.168.10.{$i}";
            $this->call('GET', "/iclock/cdata?SN={$sn}&options=all", [], [], [], [
                'REMOTE_ADDR' => $ip,
            ]);
        }

        $this->assertEquals($initialCount + 10, AttendanceDevice::count());

        // Request ke-11 harus diblokir oleh throttling dan tidak membuat entri di database
        $floodSn = 'FLOOD-SN-11';
        $floodIp = '192.168.10.11';
        $response = $this->call('GET', "/iclock/cdata?SN={$floodSn}&options=all", [], [], [], [
            'REMOTE_ADDR' => $floodIp,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($initialCount + 10, AttendanceDevice::count());
        $this->assertDatabaseMissing('attendance_devices', [
            'device_sn' => $floodSn,
        ]);
    }

    /**
     * Skenario 20: Admin tetap dapat mengaktifkan perangkat baru secara eksplisit melalui antarmuka/model.
     */
    public function test_20_admin_can_explicitly_activate_new_device(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Perangkat Menunggu Persetujuan',
            'device_sn' => 'SN-PENDING-01',
            'ip_address' => '192.168.1.150',
            'is_active' => false,
            'status' => 'online',
        ]);

        $this->assertFalse($device->is_active);

        // Admin mengaktifkan perangkat
        $device->update(['is_active' => true]);

        $this->assertTrue($device->fresh()->is_active);
    }
}
