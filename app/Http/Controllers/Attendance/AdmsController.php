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
use Illuminate\Support\Facades\DB;
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

        if (! $device || ! $device->is_active) {
            Log::warning("ADMS Push [SN: {$sn}, IP: {$request->ip()}] diabaikan karena mesin belum diverifikasi admin atau IP tidak valid.");

            return response("OK: 0\n", 200, ['Content-Type' => 'text/plain']);
        }

        if (! $this->verifyDeviceCommKey($request, $device)) {
            Log::warning("ADMS Push [SN: {$sn}, IP: {$request->ip()}] diabaikan karena CommKey tidak cocok.");

            return response("OK: 0\n", 200, ['Content-Type' => 'text/plain']);
        }

        Log::info("ADMS Push [SN: {$sn}, Table: {$table}]: ".substr($rawContent, 0, 500));

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

        // Validasi karakteristik payload Revo/FKDataHS:
        // Revo mengirim payload biner berawalan \x10/\x11 atau string JSON dengan fk_info / user_id / data
        $isJsonPayload = (str_contains($raw, '{') && str_contains($raw, '}'));
        $isBinaryFk = str_starts_with($raw, "\x10") || str_starts_with($raw, "\x11");
        $isAttlogLines = (str_contains($raw, "\t") && preg_match('/^[0-9A-Za-z_-]+\t[0-9]{4}-[0-9]{2}-[0-9]{2}/m', $raw));

        if (! $isJsonPayload && ! $isBinaryFk && ! $isAttlogLines) {
            Log::warning("Revo Root Push from {$ip} rejected: payload tidak sesuai format mesin presensi.");

            return response('Bad Request: Invalid push payload format', 400);
        }

        Log::info("REVO ROOT PUSH from {$ip}:", [
            'method' => $request->method(),
            'query' => $request->query(),
            'post' => $request->all(),
            'headers' => $request->headers->all(),
            'raw_preview' => substr($raw, 0, 1000),
        ]);

        $sn = $request->input('SN', $request->query('SN', ''));
        $device = $this->resolveDevice($sn, $ip);

        if (! $device || ! $device->is_active) {
            Log::warning("Revo Root Push [SN: {$sn}, IP: {$ip}] diabaikan karena mesin belum diverifikasi admin atau IP tidak valid.");

            return response("result=OK\n", 200, [
                'Content-Type' => 'text/plain',
                'Connection' => 'close',
                'response_code' => 'OK',
            ]);
        }

        if (! $this->verifyDeviceCommKey($request, $device)) {
            Log::warning("Revo Root Push [SN: {$sn}, IP: {$ip}] diabaikan karena CommKey tidak cocok.");

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

        // Check if there is a pending command for this specific active device
        $targetSn = $device->device_sn;
        if (! empty($targetSn)) {
            $pendingCmd = AttendanceDeviceCommand::where('status', 'pending')
                ->where('device_sn', $targetSn)
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
        $sn = $request->query('SN', $request->input('SN', ''));
        $ip = $request->ip();

        if (empty($sn)) {
            return response("OK\n", 200, ['Content-Type' => 'text/plain']);
        }

        $device = AttendanceDevice::where('device_sn', $sn)->first();

        // Verifikasi 1: Perangkat harus terdaftar dan aktif
        if (! $device || ! $device->is_active) {
            return response("OK\n", 200, ['Content-Type' => 'text/plain']);
        }

        // Verifikasi 2: IP pengirim harus sesuai dengan IP perangkat terdaftar
        if ($device->ip_address && $device->ip_address !== $ip) {
            Log::warning("ADMS getrequest: Untrusted command request for [SN: {$sn}] from mismatched IP [{$ip}]. Expected [{$device->ip_address}]. Commands blocked.");

            return response("OK\n", 200, ['Content-Type' => 'text/plain']);
        }

        // Verifikasi 3: CommKey jika dikonfigurasi
        if (! $this->verifyDeviceCommKey($request, $device)) {
            Log::warning("ADMS getrequest: CommKey mismatch for [SN: {$sn}]. Commands blocked.");

            return response("OK\n", 200, ['Content-Type' => 'text/plain']);
        }

        // Update status online hanya setelah seluruh pemeriksaan identitas lolos
        $device->update([
            'status' => 'online',
            'last_activity_at' => now(),
        ]);

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

        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Acknowledge command response from device (/iclock/devicecmd)
     */
    public function devicecmd(Request $request): Response
    {
        $sn = trim((string) $request->query('SN', $request->input('SN', '')));
        $ip = $request->ip();

        // 1. Parameter SN wajib disertakan dan tidak boleh kosong
        if (empty($sn)) {
            Log::warning("ADMS devicecmd: Request ditolak dari IP [{$ip}] karena parameter SN kosong atau tidak dikirim.");

            return response("OK\n", 200, ['Content-Type' => 'text/plain']);
        }

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

            if (! $id) {
                continue;
            }

            $cmd = AttendanceDeviceCommand::find($id);
            if (! $cmd) {
                continue;
            }

            // 2. Wajibkan SN pada request cocok persis dengan device_sn milik command
            if ($cmd->device_sn !== $sn) {
                Log::warning("ADMS devicecmd: SN pengirim [{$sn}] tidak cocok dengan target perintah ID {$id} [{$cmd->device_sn}]. Konfirmasi ditolak.");

                continue;
            }

            // 3. Verifikasi perangkat target terdaftar dan aktif
            $targetDevice = AttendanceDevice::where('device_sn', $cmd->device_sn)->first();
            if (! $targetDevice || ! $targetDevice->is_active) {
                Log::warning("ADMS devicecmd: Konfirmasi perintah ID {$id} diabaikan karena perangkat target [{$cmd->device_sn}] tidak aktif/terdaftar.");

                continue;
            }

            // 4. Verifikasi IP pengirim terhadap target device (wajib terdaftar dan cocok persis)
            if (empty($targetDevice->ip_address) || $targetDevice->ip_address !== $ip) {
                Log::warning("ADMS devicecmd: IP tidak cocok untuk perintah ID {$id}. Terdaftar: [{$targetDevice->ip_address}], Pengirim: [{$ip}]. Konfirmasi ditolak.");

                continue;
            }

            // 5. Verifikasi CommKey wajib (tidak boleh null, kosong, '0', dan harus cocok)
            if (! $this->verifyDeviceCommKey($request, $targetDevice)) {
                Log::warning("ADMS devicecmd: CommKey tidak valid atau tidak cocok untuk [SN: {$targetDevice->device_sn}]. Konfirmasi perintah ID {$id} ditolak.");

                continue;
            }

            // 6. Cegah pemrosesan ulang perintah yang sudah berstatus final (idempotency)
            if ($cmd->status === 'executed' || $cmd->status === 'failed') {
                continue;
            }

            // 7. Validasi status: hanya perintah yang sudah dikirim ('sent') yang dapat dikonfirmasi
            if ($cmd->status !== 'sent') {
                Log::warning("ADMS devicecmd: Perintah ID {$id} berstatus [{$cmd->status}], tidak diizinkan untuk dikonfirmasi. Konfirmasi ditolak.");

                continue;
            }

            $isSuccess = ($return === '0' || $return === 0);

            // 8. Lindungi seluruh efek samping dalam transaksi database
            DB::transaction(function () use ($cmd, $isSuccess, $line) {
                $cmd->update([
                    'status' => $isSuccess ? 'executed' : 'failed',
                    'response' => $line,
                    'executed_at' => now(),
                ]);

                // Eksekusi efek samping hanya jika status berhasil dan verifikasi perangkat lolos
                if ($isSuccess && ! empty($cmd->device_sn)) {
                    // 8a. Jika perintah kirim biometrik (FINGERTMP) berhasil dieksekusi di mesin
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

                    // 8b. Jika perintah kirim biometrik wajah (BIOPHOTO/FACE) berhasil
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

                    // 8c. Jika perintah format/kosongkan user dijalankan di mesin
                    if ($cmd->command === 'CLEAR DATA' || $cmd->command === 'CLEAR_ENROLL_DATA') {
                        AttendanceDeviceBiometric::where('device_sn', $cmd->device_sn)->delete();
                    }
                }
            });
        }

        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Verifikasi CommKey perangkat jika dikonfigurasi (bukan default '0')
     */
    protected function verifyDeviceCommKey(Request $request, AttendanceDevice $device): bool
    {
        // Enforce strict CommKey validation: device must have a non-null, non-empty, non-'0' key, and the request must provide a matching key.
        $storedKey = $device->comm_key;
        // Reject if stored CommKey is missing, empty, whitespace-only, or the literal string "0".
        if (is_null($storedKey) || trim((string) $storedKey) === '' || $storedKey === '0') {
            return false;
        }
        // Retrieve provided CommKey from request (body or query) respecting existing priority order.
        $providedKey = (string) $request->input('PushCommKey', $request->query('PushCommKey',
            $request->input('commkey', $request->query('commkey',
                $request->input('comm_key', $request->query('comm_key', ''))))));
        // Reject if client did not supply a key or supplied an empty/placeholder value.
        if (trim($providedKey) === '' || $providedKey === '0') {
            return false;
        }
        // Finally, compare keys.
        if ($providedKey !== (string) $storedKey) {
            return false;
        }

        // All checks passed.
        return true;
    }

    /**
     * Find device and update its activity status, or trigger throttled auto-registration
     */
    protected function resolveDevice(?string $sn, string $ip): ?AttendanceDevice
    {
        $device = null;
        if (! empty($sn)) {
            $device = AttendanceDevice::where('device_sn', $sn)->first();
        }

        if (! $device && ! empty($ip)) {
            $device = AttendanceDevice::where('ip_address', $ip)->first();
        }

        if ($device) {
            // Jika perangkat sudah diverifikasi admin (is_active = true)
            if ($device->is_active) {
                // Aturan ID-03-03: IP perangkat aktif tidak boleh ditimpa otomatis oleh request masuk.
                // Jika request datang dari IP yang berbeda, tolak dan jangan dianggap tepercaya.
                if ($device->ip_address && $device->ip_address !== $ip) {
                    Log::warning("ADMS Security: IP mismatch untuk perangkat aktif [SN: {$sn}]. Terdaftar: {$device->ip_address}, Request: {$ip}. Request ditolak.");

                    return null;
                }

                $device->update([
                    'status' => 'online',
                    'last_activity_at' => now(),
                ]);
            } else {
                // Perangkat belum aktif: update waktu aktivitas
                $updates = ['status' => 'online', 'last_activity_at' => now()];
                if (empty($device->device_sn) && ! empty($sn)) {
                    $updates['device_sn'] = $sn;
                }
                $device->update($updates);
            }

            return $device;
        }

        // Pendaftaran otomatis perangkat baru (ID-03-06)
        return $this->registerNewDevice($sn, $ip);
    }

    /**
     * Daftarkan perangkat baru yang belum terverifikasi (is_active = false) dengan batasan frekuensi
     */
    protected function registerNewDevice(?string $sn, string $ip): ?AttendanceDevice
    {
        // Cegah flooding pendaftaran perangkat baru: batasi maksimal 10 perangkat baru per 10 menit
        $recentCount = AttendanceDevice::where('created_at', '>=', now()->subMinutes(10))->count();
        if ($recentCount >= 10) {
            Log::warning("ADMS Security: Batas auto-registration tercapai. Pendaftaran perangkat baru ditangguhkan untuk IP: {$ip}, SN: {$sn}");

            return null;
        }

        $name = 'Perangkat Baru ('.$ip.')';
        if (! empty($sn) && str_starts_with($sn, 'AEYU')) {
            $name = 'Solution X601 ('.$sn.')';
        } elseif (! empty($sn) && str_starts_with($sn, 'AEWD')) {
            $name = 'Solution X606-S ('.$sn.')';
        } elseif ($ip === '192.168.1.25') {
            $name = 'Fingerspot Revo WDV-204BNC';
        }

        return AttendanceDevice::create([
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

        // 1. Match Guru/Karyawan by NIGS
        $teacher = User::operationalPersonnel()
            ->where('nigs', $pin)
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
