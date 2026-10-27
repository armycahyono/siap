<?php

namespace App\Services;

use App\Models\AttendanceBiometric;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceBiometric;
use App\Models\AttendanceDeviceCommand;
use App\Models\Student;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

class AttendanceDeviceService
{
    /**
     * Synchronize time on the device to match server local time (WIB / Asia/Jakarta)
     */
    public function syncTime(AttendanceDevice $device, ?DateTimeInterface $dateTime = null): bool
    {
        $dt = $dateTime ?: now();
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;
        $ip = $device->ip_address;
        $port = $device->port;
        $timestamp = $dt->getTimestamp();

        $directSuccess = false;

        // 1. Direct Socket Sync via Port 4370 (ZKTeco / Solution Standalone Protocol)
        if ($port === 4370 || $port === 5005) {
            $directSuccess = $this->sendDirectZkTime($ip, $port, $dt);
        }

        // 2. Queue ADMS / PUSH Commands
        // ZKTeco ADMS requires unix seconds for DateTime, TimeZone=7 for WIB
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => "SET OPTIONS DateTime={$timestamp},TimeZone=7,DaylightSavingTime=0",
            'status' => 'pending',
        ]);

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => "SET OPTIONS DateTime={$timestamp}",
            'status' => 'pending',
        ]);

        // Fingerspot Revo (FK Protocol)
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'SET_TIME',
            'status' => 'pending',
        ]);

        // Signal CHECK to immediately process command queue
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        if ($directSuccess) {
            $device->update(['status' => 'online', 'last_activity_at' => now()]);
            Log::info("AttendanceDeviceService: Time synchronized directly on {$device->name} ({$ip}:{$port}) to ".$dt->format('Y-m-d H:i:s'));
        }

        return $directSuccess;
    }

    /**
     * Send remote command to restart / reboot the device
     */
    public function rebootDevice(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;
        $directSuccess = false;

        if ($device->port === 4370 || $device->port === 5005) {
            $directSuccess = $this->sendDirectZkCommand($device->ip_address, $device->port, 1003); // CMD_RESTART = 1003
        }

        // Also queue via ADMS
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'REBOOT',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return $directSuccess;
    }

    /**
     * Unlock admin privilege / clear admin lock on device
     */
    public function clearAdminLock(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR ADMIN',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR_ALL_ADMIN',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return true;
    }

    /**
     * Force machine to upload all attendance logs
     */
    public function queryAttLog(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'DATA QUERY ATTLOG',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return true;
    }

    /**
     * Clear all user enrollment & biometric data from device
     */
    public function clearAllUsers(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR DATA',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR_ENROLL_DATA',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        // Attempt immediate wipe via direct ZK socket if port is reachable
        if (! empty($device->ip_address) && ! empty($device->port)) {
            $this->sendDirectZkCommand($device->ip_address, (int) $device->port, 14, ''); // CMD_CLEAR_DATA = 14
            $this->sendDirectZkCommand($device->ip_address, (int) $device->port, 1013, ''); // CMD_REFRESHDATA = 1013
        }

        // Clean up device biometrics record cache & detach source_device_sn from master vault
        if ($device->device_sn) {
            AttendanceDeviceBiometric::where('device_sn', $device->device_sn)->delete();
            AttendanceBiometric::where('source_device_sn', $device->device_sn)->update(['source_device_sn' => null]);
        }

        return true;
    }

    /**
     * Clear all attendance log memory on device
     */
    public function clearDeviceLogs(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR LOG',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CLEAR_LOG_DATA',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        // Attempt immediate log clearing via direct ZK socket if reachable
        if (! empty($device->ip_address) && ! empty($device->port)) {
            $this->sendDirectZkCommand($device->ip_address, (int) $device->port, 15, ''); // CMD_CLEAR_ATTLOG = 15
            $this->sendDirectZkCommand($device->ip_address, (int) $device->port, 1013, ''); // CMD_REFRESHDATA = 1013
        }

        return true;
    }

    /**
     * Queue deletion of a specific user PIN from machine
     */
    public function deleteUserPin(AttendanceDevice $device, string $pin): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => "DATA DELETE USERINFO PIN={$pin}",
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => "DATA DELETE FINGERTMP PIN={$pin}",
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return true;
    }

    /**
     * Request all fingerprint/biometric templates stored in the device to be uploaded to server
     */
    public function queryBiometrics(AttendanceDevice $device): bool
    {
        $sn = $device->device_sn ?: 'DEVICE_'.$device->id;

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'DATA QUERY FINGERTMP',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'DATA QUERY BIOPHOTO',
            'status' => 'pending',
        ]);
        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return true;
    }

    /**
     * Push biometric templates from server database to a target device
     */
    public function pushBiometricsToDevice(AttendanceDevice $targetDevice, ?array $pins = null): int
    {
        $sn = $targetDevice->device_sn;
        if (empty($sn)) {
            return 0;
        }

        $query = AttendanceBiometric::query();
        if (! empty($pins)) {
            $query->whereIn('pin', $pins);
        }

        $biometrics = $query->get();
        if ($biometrics->isEmpty()) {
            return 0;
        }

        $queued = 0;
        $uniquePins = $biometrics->pluck('pin')->unique();

        // 1. Pastikan USERINFO didaftarkan terlebih dahulu di mesin sebelum menyuntikkan template sidik jari
        // Mesin ZKTeco/Solution menolak template (Return=-10) jika PIN belum terdaftar di memori USERINFO mesin
        $teachers = User::operationalPersonnel()
            ->where(function ($q) use ($uniquePins) {
                $q->whereIn('nigs', $uniquePins)
                    ->orWhereIn('id', $uniquePins);
            })
            ->get();

        $students = Student::where(function ($q) use ($uniquePins) {
            $q->whereIn('nisn', $uniquePins)
                ->orWhereIn('nis', $uniquePins)
                ->orWhereIn('id', $uniquePins);
        })->get();

        foreach ($uniquePins as $pin) {
            $matchedTeacher = $teachers->first(fn ($t) => (string) ($t->nigs ?: $t->id) === (string) $pin || (string) $t->id === (string) $pin);
            $matchedStudent = $students->first(fn ($s) => (string) ($s->nisn ?: ($s->nis ?: $s->id)) === (string) $pin || (string) $s->id === (string) $pin);

            $userName = $matchedTeacher?->name ?? ($matchedStudent?->name ?? 'User '.$pin);
            $cleanName = addslashes(mb_substr($userName, 0, 24));

            $userCmd = "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=\tGrp=1";
            AttendanceDeviceCommand::create([
                'device_sn' => $sn,
                'command' => $userCmd,
                'status' => 'pending',
            ]);
            $queued++;
        }

        // 2. Suntikkan template biometrik (FINGERTMP / BIOPHOTO)
        foreach ($biometrics as $bio) {
            if ($bio->bio_type === 'fingerprint') {
                $cmd = "DATA UPDATE FINGERTMP PIN={$bio->pin}\tFID={$bio->finger_id}\tSize={$bio->size}\tValid={$bio->valid}\tTMP={$bio->template}";
            } else {
                $cmd = "DATA UPDATE BIOPHOTO PIN={$bio->pin}\tSize={$bio->size}\tTMP={$bio->template}";
            }

            AttendanceDeviceCommand::create([
                'device_sn' => $sn,
                'command' => $cmd,
                'status' => 'pending',
            ]);
            $queued++;
        }

        if ($queued > 0) {
            AttendanceDeviceCommand::create([
                'device_sn' => $sn,
                'command' => 'CHECK',
                'status' => 'pending',
            ]);
        }

        // 3. Direct Socket Push (Jika port direct 4370 terbuka di mesin target)
        // Mesin standalone seperti X606 / X107 dapat menerima template secara instan via direct socket
        if (! empty($targetDevice->ip_address) && (int) $targetDevice->port === 4370) {
            $this->pushBiometricsDirectSocket($targetDevice, $biometrics);
        }

        return $queued;
    }

    /**
     * Push biometric templates directly to machine via socket port 4370 (ZKTeco Protocol)
     */
    public function pushBiometricsDirectSocket(AttendanceDevice $targetDevice, $biometrics): int
    {
        $ip = $targetDevice->ip_address;
        $port = (int) $targetDevice->port;
        if (empty($ip) || $port <= 0) {
            return 0;
        }

        // Test connectivity first
        $fp = @fsockopen($ip, $port, $errno, $errstr, 1);
        if (! $fp) {
            return 0;
        }
        fclose($fp);

        $payload = [];
        foreach ($biometrics as $b) {
            if ($b->bio_type === 'fingerprint') {
                $payload[] = [
                    'pin' => (string) $b->pin,
                    'finger_id' => (int) $b->finger_id,
                    'valid' => (int) $b->valid,
                    'template' => $b->template,
                ];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $jsonPayload = json_encode($payload);
        $tempFile = storage_path('app/direct_push_'.uniqid().'.json');
        file_put_contents($tempFile, $jsonPayload);

        $pyCode = '
import sys, json, base64
from zk import ZK
from zk.finger import Finger

ip = sys.argv[1]
port = int(sys.argv[2])
file_path = sys.argv[3]

with open(file_path) as f:
    bios = json.load(f)

zk = ZK(ip, port=port, timeout=5)
conn = zk.connect()
users = conn.get_users()
user_map = {u.user_id: u for u in users}

bios_by_pin = {}
for b in bios:
    bios_by_pin.setdefault(str(b["pin"]), []).append(b)

success = 0
for pin, b_list in bios_by_pin.items():
    u = user_map.get(pin)
    if not u:
        continue
    fingers = []
    for b in b_list:
        raw = base64.b64decode(b["template"])
        fingers.append(Finger(uid=u.uid, fid=int(b["finger_id"]), valid=int(b.get("valid", 1)), template=raw))
    try:
        conn.save_user_template(u, fingers)
        success += len(fingers)
    except Exception:
        pass

conn.refresh_data()
conn.disconnect()
print(success)
';

        $scriptFile = storage_path('app/direct_push_worker.py');
        if (! file_exists($scriptFile)) {
            file_put_contents($scriptFile, trim($pyCode));
        }

        $cmd = "python \"{$scriptFile}\" \"{$ip}\" {$port} \"{$tempFile}\"";
        $output = @shell_exec($cmd);
        @unlink($tempFile);

        $syncedCount = (int) trim($output ?? '0');
        if ($syncedCount > 0 && ! empty($targetDevice->device_sn)) {
            // Update AttendanceDeviceBiometric cache in database
            foreach ($payload as $item) {
                AttendanceDeviceBiometric::updateOrCreate(
                    [
                        'device_sn' => $targetDevice->device_sn,
                        'pin' => $item['pin'],
                        'bio_type' => 'fingerprint',
                        'finger_id' => $item['finger_id'],
                    ],
                    ['synced_at' => now()]
                );
            }
            Log::info("AttendanceDeviceService: Direct socket pushed {$syncedCount} templates to {$targetDevice->name}");
        }

        return $syncedCount;
    }

    /**
     * Send CMD_SET_TIME (202) and CMD_REFRESHDATA (1013) over TCP/UDP socket
     */
    protected function sendDirectZkTime(string $ip, int $port, DateTimeInterface $dt): bool
    {
        $errno = 0;
        $errstr = '';

        foreach (['tcp', 'udp'] as $proto) {
            $socket = @stream_socket_client("{$proto}://{$ip}:{$port}", $errno, $errstr, 2);
            if (! $socket) {
                continue;
            }
            stream_set_timeout($socket, 2);

            $sessionId = 0;
            $replyId = 65534; // 0xFFFE

            // 1. CMD_CONNECT = 1000
            $resp = $this->sendZkPacket($socket, $proto, 1000, '', $sessionId, $replyId);
            if (! $resp || ! isset($resp['command'])) {
                fclose($socket);

                continue;
            }

            $sessionId = $resp['session_id'];
            $replyId = $resp['reply_id'];

            // 2. CMD_SET_TIME = 202
            $y = (int) $dt->format('y');
            $m = (int) $dt->format('n');
            $d = (int) $dt->format('j');
            $h = (int) $dt->format('G');
            $i = (int) $dt->format('i');
            $s = (int) $dt->format('s');

            $encodedTime = (($y * 12 * 31 + ($m - 1) * 31 + $d - 1) * 86400) + ($h * 3600 + $i * 60 + $s);
            $timePayload = pack('V', $encodedTime);

            $setTimeResp = $this->sendZkPacket($socket, $proto, 202, $timePayload, $sessionId, $replyId);

            // 3. CMD_REFRESHDATA = 1013
            $this->sendZkPacket($socket, $proto, 1013, '', $sessionId, $replyId);

            // 4. CMD_EXIT = 1001
            $this->sendZkPacket($socket, $proto, 1001, '', $sessionId, $replyId);
            fclose($socket);

            if ($setTimeResp && ($setTimeResp['command'] === 2000 || $setTimeResp['command'] === 0x07D0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send generic command via direct ZK socket (e.g. CMD_RESTART = 1003)
     */
    protected function sendDirectZkCommand(string $ip, int $port, int $commandCode, string $payload = ''): bool
    {
        $errno = 0;
        $errstr = '';

        foreach (['tcp', 'udp'] as $proto) {
            $socket = @stream_socket_client("{$proto}://{$ip}:{$port}", $errno, $errstr, 2);
            if (! $socket) {
                continue;
            }
            stream_set_timeout($socket, 2);

            $sessionId = 0;
            $replyId = 65534;

            // Connect
            $resp = $this->sendZkPacket($socket, $proto, 1000, '', $sessionId, $replyId);
            if (! $resp || ! isset($resp['command'])) {
                fclose($socket);

                continue;
            }

            $sessionId = $resp['session_id'];
            $replyId = $resp['reply_id'];

            // Send command
            $cmdResp = $this->sendZkPacket($socket, $proto, $commandCode, $payload, $sessionId, $replyId);

            // Exit
            $this->sendZkPacket($socket, $proto, 1001, '', $sessionId, $replyId);
            fclose($socket);

            if ($cmdResp && ($cmdResp['command'] === 2000 || $cmdResp['command'] === 0x07D0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build and send binary packet for ZK protocol
     */
    protected function sendZkPacket($socket, string $proto, int $cmd, string $data, int $sessionId, int &$replyId): ?array
    {
        $replyId++;
        if ($replyId >= 65535) {
            $replyId -= 65535;
        }

        $header = pack('vvvv', $cmd, 0, $sessionId, $replyId);
        $buf = $header.$data;
        $chk = $this->inChksum($buf);
        $packet = pack('vvvv', $cmd, $chk, $sessionId, $replyId).$data;

        if ($proto === 'tcp') {
            $tcpHeader = pack('vvV', 0x5050, 0x827D, strlen($packet));
            @fwrite($socket, $tcpHeader.$packet);
            $raw = @fread($socket, 1024);
            if (! $raw || strlen($raw) < 16) {
                return null;
            }

            return unpack('vcommand/vchecksum/vsession_id/vreply_id', substr($raw, 8, 8));
        } else {
            @fwrite($socket, $packet);
            $raw = @fread($socket, 1024);
            if (! $raw || strlen($raw) < 8) {
                return null;
            }

            return unpack('vcommand/vchecksum/vsession_id/vreply_id', substr($raw, 0, 8));
        }
    }

    /**
     * Compute 16-bit internet checksum
     */
    protected function inChksum(string $buf): int
    {
        $n = strlen($buf);
        $sum = 0;
        $i = 0;
        while ($n > 1) {
            $w = unpack('v', substr($buf, $i, 2))[1];
            $sum += $w;
            $i += 2;
            $n -= 2;
        }
        if ($n > 0) {
            $sum += ord($buf[$i]);
        }
        while ($sum >> 16) {
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        return (~$sum) & 0xFFFF;
    }

    /**
     * Resolve the active server network IP, Port, and domain for ADMS communication.
     *
     * @return array{ip: string, port: int, domain: ?string, domain_port: int}
     */
    public function getActiveServerNetwork(): array
    {
        // 1. Port resolution
        $configuredPort = config('services.adms.server_port');
        $defaultPort = (int) ($configuredPort
            ?: request()->server('SERVER_PORT')
            ?: request()->getPort()
            ?: 80);

        // Physical devices on LAN connect via plain HTTP (port 80) even if accessed via HTTPS (443) proxy
        $lanPort = in_array($defaultPort, [443, 8443], true) ? 80 : $defaultPort;

        // 2. IP resolution
        $serverIp = config('services.adms.server_ip');

        if (! $serverIp) {
            $host = request()->getHost();
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $host !== '127.0.0.1') {
                $serverIp = $host;
            }
        }

        if (! $serverIp) {
            $serverAddr = request()->server('SERVER_ADDR');
            if (filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $serverAddr !== '127.0.0.1') {
                $serverIp = $serverAddr;
            }
        }

        if (! $serverIp && PHP_OS_FAMILY === 'Linux') {
            $detected = trim((string) @shell_exec("hostname -I | awk '{print $1}'"));
            if (filter_var($detected, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $serverIp = $detected;
            }
        }

        if (! $serverIp) {
            $resolved = @gethostbyname(gethostname());
            if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $resolved !== '127.0.0.1') {
                $serverIp = $resolved;
            }
        }

        if (! $serverIp || $serverIp === '127.0.0.1') {
            $serverIp = '192.168.1.50';
        }

        $domain = request()->getHost();
        $isDomain = ! filter_var($domain, FILTER_VALIDATE_IP) && ! str_ends_with($domain, '.test') && $domain !== 'localhost';

        return [
            'ip' => $serverIp,
            'port' => $lanPort,
            'domain' => $isDomain ? $domain : null,
            'domain_port' => $defaultPort,
        ];
    }
}
