<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBiometric;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceBiometric;
use App\Models\AttendanceDeviceCommand;
use App\Models\AttendanceLog;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AdmsController extends Controller
{
    /**
     * Handshake and push handler (/iclock/cdata)
     */
    public function cdata(Request $request): Response
    {
        $sn = $request->query('SN', $request->input('SN', ''));
        $table = strtoupper((string) $request->query('table', $request->input('table', '')));

        // Track or register device
        $device = $this->resolveDevice($sn, $request->ip());

        // 1. Handshake request (GET)
        if ($request->isMethod('get')) {
            $options = "GET OPTION FROM: {$sn}\n"
                ."Stamp=0\n"
                ."OpStamp=0\n"
                ."ErrorDelay=60\n"
                ."Delay=5\n"
                ."TransTimes=00:00;14:05\n"
                ."TransInterval=1\n"
                ."TransFlag=TransData AttLog\tOpLog\tAttPhoto\tEnrollUser\tEnrollFP\tFPImag\tFACE\tUserPic\tBioPhoto\n"
                ."Realtime=1\n"
                ."TimeZone=7\n"
                ."ServerTZ=+0700\n"
                ."DaylightSavingTime=0\n"
                ."Encrypt=0\n";

            return response($options, 200, ['Content-Type' => 'text/plain']);
        }

        // 2. Data Push request (POST)
        $rawContent = $request->getContent();
        Log::info("ADMS Push [SN: {$sn}, Table: {$table}]: ".substr($rawContent, 0, 500));

        if (! $device->is_active) {
            Log::warning("ADMS Push [SN: {$sn}, IP: {$request->ip()}] diabaikan karena mesin belum diverifikasi admin.");

            return response("OK: 0\n", 200, ['Content-Type' => 'text/plain']);
        }

        $count = 0;
        if ($table === 'OPERLOG' || $table === 'FINGERTMP' || $table === 'BIODATA' || $table === 'BIOPHOTO' || (str_contains($rawContent, 'PIN=') && str_contains($rawContent, 'TMP='))) {
            $count = $this->processOperLogLines($rawContent, $device);
        } elseif ($table === 'ATTLOG' || empty($table)) {
            $count = $this->processRawLines($rawContent, $device);
        }

        return response("OK: {$count}\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Handle direct root POST from Revo WDV-204BNC (/)
     * Uses FKDataHS / FK Web Protocol
     */
    public function rootPush(Request $request): Response|JsonResponse
    {
        $raw = $request->getContent();
        $ip = $request->ip();

        Log::info("REVO ROOT PUSH from {$ip}:", [
            'method' => $request->method(),
            'query' => $request->query(),
            'post' => $request->all(),
            'headers' => $request->headers->all(),
            'raw_preview' => substr($raw, 0, 1000),
        ]);

        $sn = $request->input('SN', $request->query('SN', ''));
        $device = $this->resolveDevice($sn, $ip);

        if (! $device->is_active) {
            Log::warning("Revo Root Push [SN: {$sn}, IP: {$ip}] diabaikan karena mesin belum diverifikasi admin.");

            return response("result=OK\n", 200, [
                'Content-Type' => 'text/plain',
                'Connection' => 'close',
                'response_code' => 'OK',
            ]);
        }

        // Extract JSON string from raw payload (handles leading binary bytes \x??\x00\x00\x00)
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $json = null;

        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($raw, $start, $end - $start + 1);
            $json = json_decode($jsonStr, true);
        }

        if (is_array($json)) {
            $this->processJsonPayload($json, $device);
        } elseif (! empty($raw)) {
            $this->processRawLines($raw, $device);
        }

        // Check if there is a pending SET_TIME command for this device
        $pendingCmd = AttendanceDeviceCommand::where('status', 'pending')
            ->where(function ($q) use ($sn) {
                if (! empty($sn)) {
                    $q->where('device_sn', $sn);
                }
                $q->orWhere('device_sn', 'REVO_TEST');
            })
            ->latest()
            ->first();

        if ($pendingCmd) {
            $cmdName = $pendingCmd->command;

            if ($cmdName === 'SET_TIME') {
                $nowStr = now()->format('YmdHis');
                $payloadJson = json_encode(['time' => $nowStr]);
                $prefix = pack('V', strlen($payloadJson));
                $body = $prefix.$payloadJson."\0";

                $pendingCmd->update([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'response' => "Injected SET_TIME {$nowStr}",
                ]);

                Log::info("AdmsController: Injected SET_TIME ({$nowStr}) to device in response to heartbeat");

                return response($body, 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Connection' => 'close',
                    'response_code' => 'OK',
                    'cmd_code' => 'SET_TIME',
                ]);
            }

            if (in_array($cmdName, ['CLEAR_ENROLL_DATA', 'CLEAR_LOG_DATA', 'CLEAR_ALL_ADMIN'])) {
                $pendingCmd->update([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'response' => "Injected {$cmdName}",
                ]);

                Log::info("AdmsController: Injected {$cmdName} to device in response to heartbeat");

                return response('', 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Connection' => 'close',
                    'response_code' => 'OK',
                    'cmd_code' => $cmdName,
                ]);
            }
        }

        // FK Web Protocol expects:
        // response_code: OK header and result=OK in body
        return response("result=OK\n", 200, [
            'Content-Type' => 'text/plain',
            'Connection' => 'close',
            'response_code' => 'OK',
        ]);
    }

    /**
     * Heartbeat and pending command poll (/iclock/getrequest)
     */
    public function getrequest(Request $request): Response
    {
        $sn = $request->query('SN', '');

        if (! empty($sn)) {
            AttendanceDevice::where('device_sn', $sn)->update([
                'status' => 'online',
                'last_activity_at' => now(),
            ]);

            $device = AttendanceDevice::where('device_sn', $sn)->first();
            if ($device && ! $device->is_active) {
                return response("OK\n", 200, ['Content-Type' => 'text/plain']);
            }

            $commands = AttendanceDeviceCommand::where('device_sn', $sn)
                ->where('status', 'pending')
                ->oldest()
                ->take(30)
                ->get();

            if ($commands->isNotEmpty()) {
                $output = '';
                foreach ($commands as $command) {
                    $command->update(['status' => 'sent']);
                    $output .= "C:{$command->id}:{$command->command}\n";
                }

                return response($output, 200, ['Content-Type' => 'text/plain']);
            }
        }

        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Acknowledge command response from device (/iclock/devicecmd)
     */
    public function devicecmd(Request $request): Response
    {
        $raw = $request->getContent();
        $lines = explode("\n", trim($raw));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            parse_str($line, $parsed);
            $id = $parsed['ID'] ?? null;
            $return = $parsed['Return'] ?? null;

            if ($id) {
                $cmd = AttendanceDeviceCommand::find($id);
                if ($cmd) {
                    $isSuccess = ($return === '0' || $return === 0);
                    $cmd->update([
                        'status' => $isSuccess ? 'executed' : 'failed',
                        'response' => $line,
                        'executed_at' => now(),
                    ]);

                    if ($isSuccess && ! empty($cmd->device_sn)) {
                        // 1. Jika perintah kirim biometrik (FINGERTMP) berhasil dieksekusi di mesin
                        if (str_contains($cmd->command, 'DATA UPDATE FINGERTMP')) {
                            preg_match('/PIN=([^\t\s]+)/', $cmd->command, $mPin);
                            preg_match('/FID=([0-9]+)/', $cmd->command, $mFid);
                            if (! empty($mPin[1])) {
                                AttendanceDeviceBiometric::updateOrCreate(
                                    [
                                        'device_sn' => $cmd->device_sn,
                                        'pin' => $mPin[1],
                                        'bio_type' => 'fingerprint',
                                        'finger_id' => isset($mFid[1]) ? (int) $mFid[1] : 0,
                                    ],
                                    ['synced_at' => now()]
                                );
                            }
                        }

                        // 2. Jika perintah kirim biometrik wajah (BIOPHOTO/FACE) berhasil
                        if (str_contains($cmd->command, 'DATA UPDATE BIOPHOTO') || str_contains($cmd->command, 'DATA UPDATE FACE')) {
                            preg_match('/PIN=([^\t\s]+)/', $cmd->command, $mPin);
                            if (! empty($mPin[1])) {
                                AttendanceDeviceBiometric::updateOrCreate(
                                    [
                                        'device_sn' => $cmd->device_sn,
                                        'pin' => $mPin[1],
                                        'bio_type' => 'face',
                                        'finger_id' => 0,
                                    ],
                                    ['synced_at' => now()]
                                );
                            }
                        }

                        // 3. Jika perintah format/kosongkan user dijalankan di mesin
                        if ($cmd->command === 'CLEAR DATA' || $cmd->command === 'CLEAR_ENROLL_DATA') {
                            AttendanceDeviceBiometric::where('device_sn', $cmd->device_sn)->delete();
                        }
                    }
                }
            }
        }

        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Find or create device and update its activity status
     */
    protected function resolveDevice(?string $sn, string $ip): ?AttendanceDevice
    {
        $device = null;
        if (! empty($sn)) {
            $device = AttendanceDevice::where('device_sn', $sn)->first();
        }

        if (! $device) {
            $device = AttendanceDevice::where('ip_address', $ip)->first();

            if ($device) {
                $updates = ['status' => 'online', 'last_activity_at' => now()];
                if (! empty($sn) && empty($device->device_sn)) {
                    $updates['device_sn'] = $sn;
                }
                $device->update($updates);
            } else {
                $name = 'Perangkat Baru ('.$ip.')';
                if (! empty($sn) && str_starts_with($sn, 'AEYU')) {
                    $name = 'Solution X601 ('.$sn.')';
                } elseif (! empty($sn) && str_starts_with($sn, 'AEWD')) {
                    $name = 'Solution X606-S ('.$sn.')';
                } elseif ($ip === '192.168.1.25') {
                    $name = 'Fingerspot Revo WDV-204BNC';
                }

                $device = AttendanceDevice::create([
                    'name' => $name,
                    'device_sn' => $sn ?: null,
                    'ip_address' => $ip ?: '192.168.1.18',
                    'port' => 4370,
                    'server_port' => 8014,
                    'status' => 'online',
                    'is_active' => false,
                    'last_activity_at' => now(),
                ]);
            }
        } else {
            $updates = ['status' => 'online', 'last_activity_at' => now()];
            if ($ip && $device->ip_address !== $ip) {
                $updates['ip_address'] = $ip;
            }
            $device->update($updates);
        }

        return $device;
    }

    /**
     * Process raw lines (ATTLOG standard)
     */
    protected function processRawLines(string $rawContent, ?AttendanceDevice $device): int
    {
        $count = 0;
        $lines = explode("\n", trim($rawContent));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/[\t]+/', $line);
            if (count($parts) < 2) {
                $parts = preg_split('/\s{2,}/', $line);
            }

            if (count($parts) >= 2) {
                $pin = trim($parts[0]);
                $timeStr = trim($parts[1]);

                try {
                    $scannedAt = Carbon::parse($timeStr);
                } catch (\Exception) {
                    continue;
                }

                $statusRaw = $parts[3] ?? '0';
                $statusMap = [
                    '0' => 'check_in',
                    '1' => 'check_out',
                    '2' => 'break_out',
                    '3' => 'break_in',
                    '4' => 'overtime_in',
                    '5' => 'overtime_out',
                ];
                $status = $statusMap[$statusRaw] ?? 'check_in';

                $verifyRaw = $parts[2] ?? '';
                $verifyMap = [
                    '0' => 'fingerprint', // 0 = Password / Fingerprint / Auto (default biometrics on ZKTeco/Solution)
                    '1' => 'fingerprint',
                    '2' => 'password',
                    '3' => 'card',
                    '4' => 'card',
                    '15' => 'face',
                ];
                $verifyType = $verifyMap[$verifyRaw] ?? 'fingerprint';
                $workCode = $parts[4] ?? null;

                $this->saveRecord($pin, $scannedAt, $status, $verifyType, $workCode, $device?->device_sn, $line);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Process OPERLOG lines (Extract and store biometric templates: FP & FACE)
     */
    protected function processOperLogLines(string $rawContent, ?AttendanceDevice $device): int
    {
        $count = 0;
        $lines = explode("\n", trim($rawContent));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // 1. Sidik Jari: FP PIN=41301\tFID=7\tSize=1296\tValid=1\tTMP=SolTUzIxAAAD...
            if (str_starts_with($line, 'FP ') || (str_contains($line, 'PIN=') && str_contains($line, 'TMP=') && (str_contains($line, 'FID=') || ! str_contains($line, 'FACE')))) {
                $clean = str_replace('FP ', '', $line);
                $parts = preg_split('/[\t]+/', $clean);

                $data = [];
                foreach ($parts as $part) {
                    $pair = explode('=', trim($part), 2);
                    if (count($pair) === 2) {
                        $data[trim($pair[0])] = trim($pair[1]);
                    }
                }

                $pin = $data['PIN'] ?? null;
                $template = $data['TMP'] ?? null;
                $fingerId = (int) ($data['FID'] ?? 0);
                $size = (int) ($data['Size'] ?? strlen($template ?? ''));
                $valid = (int) ($data['Valid'] ?? 1);

                if (! empty($pin) && ! empty($template)) {
                    AttendanceBiometric::updateOrCreate(
                        [
                            'pin' => (string) $pin,
                            'bio_type' => 'fingerprint',
                            'finger_id' => $fingerId,
                        ],
                        [
                            'size' => $size,
                            'valid' => $valid,
                            'template' => $template,
                            'source_device_sn' => $device?->device_sn,
                        ]
                    );

                    if ($device?->device_sn) {
                        AttendanceDeviceBiometric::updateOrCreate(
                            [
                                'device_sn' => $device->device_sn,
                                'pin' => (string) $pin,
                                'bio_type' => 'fingerprint',
                                'finger_id' => $fingerId,
                            ],
                            ['synced_at' => now()]
                        );
                    }

                    Log::info("AdmsController: Stored biometric fingerprint template for PIN {$pin} (Finger ID: {$fingerId}) from {$device?->name}");
                    $count++;
                }
            }

            // 2. Wajah: FACE PIN=41301\tFID=0\tSize=... \tTMP=... atau BIODATA / BIOPHOTO
            if (str_starts_with($line, 'FACE ') || str_starts_with($line, 'BIOPHOTO ') || (str_contains($line, 'PIN=') && str_contains($line, 'TMP=') && str_contains($line, 'FACE'))) {
                $clean = preg_replace('/^(FACE|BIOPHOTO)\s+/', '', $line);
                $parts = preg_split('/[\t]+/', $clean);

                $data = [];
                foreach ($parts as $part) {
                    $pair = explode('=', trim($part), 2);
                    if (count($pair) === 2) {
                        $data[trim($pair[0])] = trim($pair[1]);
                    }
                }

                $pin = $data['PIN'] ?? null;
                $template = $data['TMP'] ?? null;
                $faceId = (int) ($data['FID'] ?? 0);
                $size = (int) ($data['Size'] ?? strlen($template ?? ''));
                $valid = (int) ($data['Valid'] ?? 1);

                if (! empty($pin) && ! empty($template)) {
                    AttendanceBiometric::updateOrCreate(
                        [
                            'pin' => (string) $pin,
                            'bio_type' => 'face',
                            'finger_id' => $faceId,
                        ],
                        [
                            'size' => $size,
                            'valid' => $valid,
                            'template' => $template,
                            'source_device_sn' => $device?->device_sn,
                        ]
                    );

                    if ($device?->device_sn) {
                        AttendanceDeviceBiometric::updateOrCreate(
                            [
                                'device_sn' => $device->device_sn,
                                'pin' => (string) $pin,
                                'bio_type' => 'face',
                                'finger_id' => $faceId,
                            ],
                            ['synced_at' => now()]
                        );
                    }

                    Log::info("AdmsController: Stored biometric FACE template for PIN {$pin} from {$device?->name}");
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Process JSON payload from Fingerspot (FKDataHS / Realand format)
     */
    protected function processJsonPayload(array $json, ?AttendanceDevice $device): int
    {
        $count = 0;

        if (isset($json['fk_time'])) {
            Log::info("AdmsController: Revo heartbeat detected. Internal device time (fk_time): {$json['fk_time']}");
        }

        // 1. Single scan log: { "user_id": "1", "io_time": "20251121081556", "io_mode": 16777216, "verify_mode": 268435456 }
        if (isset($json['user_id']) && isset($json['io_time'])) {
            $pin = (string) $json['user_id'];
            $timeStr = (string) $json['io_time'];
            $scannedAt = null;

            try {
                if (strlen($timeStr) === 14) {
                    $scannedAt = Carbon::createFromFormat('YmdHis', $timeStr);
                } else {
                    $scannedAt = Carbon::parse($timeStr);
                }
            } catch (\Exception) {
                $scannedAt = now();
            }

            $ioMode = (int) ($json['io_mode'] ?? 0);
            $status = ($ioMode === 1 || $ioMode === 16777217) ? 'check_out' : 'check_in';

            $verifyMode = (int) ($json['verify_mode'] ?? 0);
            $verifyType = 'fingerprint';
            if ($verifyMode >= 268435456) {
                $verifyType = 'face';
            } elseif ($verifyMode === 2) {
                $verifyType = 'password';
            } elseif ($verifyMode === 3 || $verifyMode === 4) {
                $verifyType = 'card';
            }

            $this->saveRecord($pin, $scannedAt, $status, $verifyType, null, $device?->device_sn, json_encode($json));

            return 1;
        }

        // 2. User info / enrollment data: { "user_id": "16", "user_name": "BAGUS USMAN ALI" }
        if (isset($json['user_id']) && isset($json['user_name'])) {
            $pin = (string) $json['user_id'];
            $userName = (string) $json['user_name'];
            Log::info("Enrolled user detected from Revo: PIN: {$pin}, Name: {$userName}");

            return 1;
        }

        // 3. Array of records in data / attlog / logs
        $records = $json['data'] ?? $json['attlog'] ?? $json['logs'] ?? (isset($json[0]) ? $json : null);
        if (is_array($records)) {
            foreach ($records as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $pin = (string) ($item['pin'] ?? $item['user_id'] ?? $item['PIN'] ?? $item['enroll_id'] ?? '');
                $timeStr = (string) ($item['scan_date'] ?? $item['date_time'] ?? $item['io_time'] ?? $item['time'] ?? '');

                if (empty($pin) || empty($timeStr)) {
                    continue;
                }

                try {
                    $scannedAt = strlen($timeStr) === 14 ? Carbon::createFromFormat('YmdHis', $timeStr) : Carbon::parse($timeStr);
                } catch (\Exception) {
                    $scannedAt = now();
                }

                $statusRaw = (string) ($item['status_scan'] ?? $item['status'] ?? $item['io_mode'] ?? '0');
                $status = in_array($statusRaw, ['1', '16777217']) ? 'check_out' : 'check_in';
                $verifyType = (string) ($item['verify'] ?? $item['verify_type'] ?? 'fingerprint');

                $this->saveRecord($pin, $scannedAt, $status, $verifyType, null, $device?->device_sn, json_encode($item));
                $count++;
            }
        }

        return $count;
    }

    /**
     * Match User (Teacher) by NIGS, Student by NISN, and save AttendanceLog
     */
    protected function saveRecord(string $pin, Carbon $scannedAt, string $status, string $verifyType, ?string $workCode, ?string $sn, string $raw, ?string $customName = null): void
    {
        $userType = 'unknown';
        $userId = null;
        $studentId = null;
        $userName = null;

        // 1. Match Guru/Karyawan by NIGS (or nigs_fix)
        $teacher = User::operationalPersonnel()
            ->where(function ($q) use ($pin) {
                $q->where('nigs', $pin)
                    ->orWhere('nigs_fix', $pin);
            })
            ->first();

        if ($teacher) {
            $userType = 'teacher';
            $userId = $teacher->id;
            $userName = $teacher->name;
        } else {
            // 2. Match Siswa by NISN or NIS
            $student = Student::where('nisn', $pin)
                ->orWhere('nis', $pin)
                ->first();

            if ($student) {
                $userType = 'student';
                $studentId = $student->id;
                $userName = $student->name;
            } else {
                // 3. Fallback: match by User ID or Student ID
                $userById = User::operationalPersonnel()->find($pin);
                if ($userById) {
                    $userType = 'teacher';
                    $userId = $userById->id;
                    $userName = $userById->name;
                } else {
                    $studentById = Student::find($pin);
                    if ($studentById) {
                        $userType = 'student';
                        $studentId = $studentById->id;
                        $userName = $studentById->name;
                    } else {
                        $userName = $customName ?: "Belum Terdaftar (PIN: {$pin})";
                    }
                }
            }
        }

        // Prevent rapid accidental duplicate scan within 60 seconds for the same PIN
        $recentDuplicate = AttendanceLog::where('pin', $pin)
            ->where('scanned_at', '>=', $scannedAt->copy()->subSeconds(60))
            ->where('scanned_at', '<=', $scannedAt->copy()->addSeconds(60))
            ->first();

        if ($recentDuplicate) {
            return;
        }

        // Koreksi status jika mesin mengirim default (misal status check_out padahal scan pagi pertama)
        if ($status === 'check_out' && $scannedAt->format('H:i') < '12:00') {
            $hasEarlierScan = AttendanceLog::where('pin', $pin)
                ->whereDate('scanned_at', $scannedAt->toDateString())
                ->exists();

            if (! $hasEarlierScan) {
                $status = 'check_in';
            }
        }

        AttendanceLog::updateOrCreate(
            [
                'device_sn' => $sn,
                'pin' => $pin,
                'scanned_at' => $scannedAt,
            ],
            [
                'user_type' => $userType,
                'user_id' => $userId,
                'student_id' => $studentId,
                'name' => $userName,
                'status' => $status,
                'verify_type' => $verifyType,
                'work_code' => $workCode,
                'raw_payload' => $raw,
            ]
        );
    }
}
