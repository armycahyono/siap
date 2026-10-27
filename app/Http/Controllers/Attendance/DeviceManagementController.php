<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBiometric;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceBiometric;
use App\Models\AttendanceDeviceCommand;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceDeviceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceManagementController extends Controller
{
    public function __construct(
        protected AttendanceDeviceService $deviceService
    ) {}

    /**
     * Display all attendance devices and hardware configuration
     */
    public function index(): View
    {
        // Auto-mark devices offline if inactive for more than 10 minutes
        AttendanceDevice::where('status', 'online')
            ->where('last_activity_at', '<', now()->subMinutes(10))
            ->update(['status' => 'offline']);

        $devices = AttendanceDevice::where('is_active', true)->withCount('commands')->get();

        // Biometrics stats per device (berdasarkan data riil yang tersinkron di tabel attendance_device_biometrics)
        $deviceBiometrics = AttendanceDeviceBiometric::selectRaw('device_sn, count(distinct pin) as user_count, count(*) as template_count')
            ->groupBy('device_sn')
            ->get()
            ->keyBy('device_sn');

        $biometricUsersPerDevice = collect();
        foreach ($devices as $dev) {
            if (! $dev->device_sn) {
                continue;
            }
            if ($deviceBiometrics->has($dev->device_sn)) {
                $biometricUsersPerDevice->put($dev->device_sn, $deviceBiometrics->get($dev->device_sn));
            }
        }

        $pendingDevices = AttendanceDevice::where('is_active', false)->get();
        $classrooms = Classroom::withCount('students')->orderBy('name')->get();
        $teachersCount = User::operationalPersonnel()->where('is_active', true)->count();
        $totalCommands = AttendanceDeviceCommand::count();
        $pendingCommands = AttendanceDeviceCommand::where('status', 'pending')->count();
        $savedBiometricsCount = AttendanceBiometric::count();
        $recentCommands = AttendanceDeviceCommand::with('device')->latest('id')->take(6)->get();

        $serverNetwork = $this->deviceService->getActiveServerNetwork();
        $serverIp = $serverNetwork['ip'];
        $serverPort = $serverNetwork['port'];

        return view('attendance.devices.index', compact(
            'devices',
            'pendingDevices',
            'classrooms',
            'teachersCount',
            'totalCommands',
            'pendingCommands',
            'savedBiometricsCount',
            'biometricUsersPerDevice',
            'recentCommands',
            'serverNetwork',
            'serverIp',
            'serverPort'
        ));
    }

    /**
     * Store a new attendance device
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer',
            'server_port' => 'required|integer',
            'device_sn' => 'nullable|string|max:100|unique:attendance_devices,device_sn',
            'comm_key' => 'nullable|string|max:20',
        ]);

        $validated['status'] = 'offline';
        $validated['is_active'] = true;
        $validated['comm_key'] = $validated['comm_key'] ?? '0';

        AttendanceDevice::create($validated);

        return redirect()->route('attendance.devices.index')->with('success', "Mesin absensi '{$validated['name']}' berhasil didaftarkan ke sistem.");
    }

    /**
     * Update device settings
     */
    public function update(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer',
            'server_port' => 'required|integer',
            'device_sn' => 'nullable|string|max:100|unique:attendance_devices,device_sn,'.$device->id,
            'comm_key' => 'nullable|string|max:20',
        ]);

        $device->update($validated);

        return redirect()->route('attendance.devices.index')->with('success', "Pengaturan mesin '{$device->name}' berhasil diperbarui.");
    }

    /**
     * Approve and activate an unverified attendance device
     */
    public function approve(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer',
            'server_port' => 'nullable|integer',
            'comm_key' => 'nullable|string|max:20',
        ]);

        $validated['is_active'] = true;
        if (empty($validated['server_port'])) {
            $validated['server_port'] = 8014;
        }

        $device->update($validated);

        return redirect()->route('attendance.devices.index')->with('success', "Mesin absensi '{$device->name}' berhasil diverifikasi dan diaktifkan.");
    }

    /**
     * Delete an attendance device
     */
    public function destroy(AttendanceDevice $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()->route('attendance.devices.index')->with('success', "Mesin absensi '{$name}' berhasil dihapus dari sistem.");
    }

    /**
     * Request sync / pull data from device
     */
    public function sync(Request $request, AttendanceDevice $device): RedirectResponse
    {
        if (! empty($device->device_sn)) {
            AttendanceDeviceCommand::create([
                'device_sn' => $device->device_sn,
                'command' => 'DATA QUERY ATTLOG',
                'status' => 'pending',
            ]);
            AttendanceDeviceCommand::create([
                'device_sn' => $device->device_sn,
                'command' => 'CHECK',
                'status' => 'pending',
            ]);
        }

        $connected = false;
        $start = microtime(true);
        $fp = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 1);
        if ($fp) {
            $connected = true;
            fclose($fp);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $device->update(['status' => 'online', 'last_activity_at' => now()]);
        }

        $msg = "Perintah sinkronisasi berhasil dikirim ke antrian mesin '{$device->name}'.";
        if ($connected) {
            $msg .= " Port {$device->port} mesin aktif dan merespons ({$latency} ms). Mesin akan segera mengunggah log absensi saat siklus komunikasi berikutnya.";
        } else {
            $msg .= " Catatan: Port {$device->port} mesin belum merespons langsung, pastikan mesin terhubung ke jaringan.";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Test connection to device
     */
    public function testConnection(AttendanceDevice $device): RedirectResponse
    {
        $start = microtime(true);
        $fp = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 2);
        if ($fp) {
            fclose($fp);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $device->update(['status' => 'online', 'last_activity_at' => now()]);

            return redirect()->back()->with('success', "Koneksi ke mesin {$device->name} ({$device->ip_address}:{$device->port}) BERHASIL! (Waktu respon: {$latency} ms)");
        }

        if ($device->last_activity_at && $device->last_activity_at->diffInMinutes(now()) <= 5) {
            $device->update(['status' => 'online']);

            return redirect()->back()->with('warning', "Port direct ({$device->port}) tidak merespons, namun mesin {$device->name} tercatat aktif mengirim data ADMS ({$device->last_activity_at->diffForHumans()}). Status mesin tetap ONLINE.");
        }

        $device->update(['status' => 'offline']);

        return redirect()->back()->with('error', "Gagal terhubung ke mesin {$device->name} ({$device->ip_address}:{$device->port}): {$errstr}");
    }

    /**
     * Upload / Synchronize User Data (GTK & Students) from server to machine(s)
     */
    public function syncUsers(Request $request): RedirectResponse
    {
        $request->validate([
            'target_type' => 'required|in:gtk,students,both',
            'device_id' => 'required|string',
            'student_filter' => 'nullable|string',
        ]);

        $targetType = $request->input('target_type');
        $deviceId = $request->input('device_id');
        $studentFilter = $request->input('student_filter', 'all');

        $devices = collect();
        if ($deviceId === 'all') {
            $devices = AttendanceDevice::where('is_active', true)->whereNotNull('device_sn')->get();
        } else {
            $single = AttendanceDevice::find($deviceId);
            if ($single && ! empty($single->device_sn)) {
                $devices->push($single);
            }
        }

        if ($devices->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada mesin aktif dengan Serial Number (SN) terdaftar untuk sinkronisasi ADMS.');
        }

        $queuedCommands = 0;

        foreach ($devices as $dev) {
            $sn = $dev->device_sn;

            if ($targetType === 'gtk' || $targetType === 'both') {
                $teachers = User::operationalPersonnel()->where('is_active', true)->get();
                foreach ($teachers as $t) {
                    $pin = $t->nigs ?: $t->id;
                    $name = addslashes(mb_substr($t->name, 0, 24));
                    $cmd = "DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tCard=\tGrp=1";
                    AttendanceDeviceCommand::create([
                        'device_sn' => $sn,
                        'command' => $cmd,
                        'status' => 'pending',
                    ]);
                    $queuedCommands++;
                }
            }

            if ($targetType === 'students' || $targetType === 'both') {
                $studentQuery = Student::where('is_active', true);
                if ($studentFilter !== 'all' && str_starts_with($studentFilter, 'class_')) {
                    $classId = (int) str_replace('class_', '', $studentFilter);
                    $studentQuery->where('classroom_id', $classId);
                }
                $students = $studentQuery->get();
                foreach ($students as $s) {
                    $pin = $s->nisn ?: ($s->nis ?: $s->id);
                    $name = addslashes(mb_substr($s->name, 0, 24));
                    $cmd = "DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tCard=\tGrp=1";
                    AttendanceDeviceCommand::create([
                        'device_sn' => $sn,
                        'command' => $cmd,
                        'status' => 'pending',
                    ]);
                    $queuedCommands++;
                }
            }

            AttendanceDeviceCommand::create([
                'device_sn' => $sn,
                'command' => 'CHECK',
                'status' => 'pending',
            ]);
        }

        return redirect()->back()->with('success', "Berhasil menambahkan {$queuedCommands} perintah unggah data ke antrean mesin ADMS.");
    }

    /**
     * Display remote control cockpit / dashboard for a specific attendance device
     */
    public function show(AttendanceDevice $device): View
    {
        $deviceSn = $device->device_sn;

        // Recent commands sent to this device
        $commands = AttendanceDeviceCommand::where('device_sn', $deviceSn)
            ->latest('id')
            ->take(30)
            ->get();

        // Recent scans through this machine
        $recentLogs = AttendanceLog::with(['user', 'student'])
            ->where(function ($q) use ($deviceSn) {
                if ($deviceSn) {
                    $q->where('device_sn', $deviceSn);
                } else {
                    $q->whereNull('device_sn');
                }
            })
            ->latest('scanned_at')
            ->take(15)
            ->get();

        // Statistics for this machine
        $totalScans = AttendanceLog::where('device_sn', $deviceSn)->count();
        $todayScans = AttendanceLog::where('device_sn', $deviceSn)->whereDate('scanned_at', Carbon::today())->count();
        $pendingCommands = AttendanceDeviceCommand::where('device_sn', $deviceSn)->where('status', 'pending')->count();

        // Teachers & Classrooms for targeted sync
        $teachersCount = User::operationalPersonnel()->where('is_active', true)->count();
        $classrooms = Classroom::withCount(['students' => function ($q) {
            $q->where('is_active', true);
        }])->orderBy('name')->get();

        // Count of inactive/graduated students in DB
        $graduatedStudentsCount = Student::where('is_active', false)->count();

        // Other devices for biometric cloning
        $otherDevices = AttendanceDevice::where('id', '!=', $device->id)
            ->whereNotNull('device_sn')
            ->orderBy('name')
            ->get();

        // Count of biometrics currently backed up in server
        $savedBiometricsCount = AttendanceBiometric::count();

        $serverNetwork = $this->deviceService->getActiveServerNetwork();
        $serverIp = $serverNetwork['ip'];
        $serverPort = $serverNetwork['port'];

        return view('attendance.devices.show', compact(
            'device',
            'commands',
            'recentLogs',
            'totalScans',
            'todayScans',
            'pendingCommands',
            'teachersCount',
            'classrooms',
            'graduatedStudentsCount',
            'otherDevices',
            'savedBiometricsCount',
            'serverNetwork',
            'serverIp',
            'serverPort'
        ));
    }

    /**
     * 1-Click Action: Sync device clock to server RTC
     */
    public function syncTime(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->syncTime($device);

        return redirect()->back()->with('success', "Perintah sinkronisasi jam dikirim ke {$device->name}. Jam mesin disesuaikan ke ".now()->format('d M Y H:i:s').' WIB.');
    }

    /**
     * 1-Click Action: Force pull all logs from device
     */
    public function queryLogs(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->queryAttLog($device);

        return redirect()->back()->with('success', "Perintah penarikan seluruh log (DATA QUERY ATTLOG) telah dikirim ke {$device->name}. Mesin akan mengunggah log presensi ke server.");
    }

    /**
     * 1-Click Action: Unlock / Reset Admin on device
     */
    public function unlockAdmin(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->clearAdminLock($device);

        return redirect()->back()->with('success', "Perintah buka kunci admin (CLEAR ADMIN) berhasil dikirim ke {$device->name}. Hak admin di keypad mesin telah direset.");
    }

    /**
     * 1-Click Action: Reboot machine
     */
    public function reboot(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->rebootDevice($device);

        return redirect()->back()->with('success', "Perintah restart mesin (REBOOT) telah dikirim ke {$device->name}. Mesin akan me-restart sistem dalam beberapa saat.");
    }

    /**
     * 1-Click Action: Clear all logs stored inside device memory
     */
    public function clearDeviceLogs(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->clearDeviceLogs($device);

        return redirect()->back()->with('warning', "Perintah pengosongan memori log mesin (CLEAR LOG) telah dikirim ke {$device->name}. Log di memori mesin akan dibersihkan.");
    }

    /**
     * Academic Year Transition: Delete Graduated / Inactive Students from machine
     */
    public function purgeGraduated(AttendanceDevice $device): RedirectResponse
    {
        $sn = $device->device_sn;
        if (empty($sn)) {
            return redirect()->back()->with('error', 'Mesin belum memiliki Serial Number (SN) yang valid.');
        }

        // Fetch students who are graduated / inactive
        $graduatedStudents = Student::where('is_active', false)->get();

        if ($graduatedStudents->isEmpty()) {
            return redirect()->back()->with('info', 'Tidak ditemukan data siswa berstatus tidak aktif / lulus di sistem.');
        }

        $queued = 0;
        foreach ($graduatedStudents as $st) {
            $pin = $st->nisn ?: ($st->nis ?: $st->id);
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
            $queued += 2;
        }

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', "Berhasil menambahkan antrean penghapusan {$graduatedStudents->count()} siswa lulus/nonaktif (total {$queued} perintah) dari mesin {$device->name}.");
    }

    /**
     * Academic Year Transition: Wipe all users on device (Clean slate)
     */
    public function clearAllUsers(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->clearAllUsers($device);

        return redirect()->back()->with('danger', "Perintah pengosongan seluruh user (CLEAR DATA) telah dikirim ke {$device->name}. Semua user di memori mesin akan dikosongkan.");
    }

    /**
     * Target Sync: Push specific group (GTK or Classroom) to this specific machine
     */
    public function syncTargetUsers(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $request->validate([
            'target_group' => 'required|string',
        ]);

        $sn = $device->device_sn;
        if (empty($sn)) {
            return redirect()->back()->with('error', 'Mesin belum memiliki Serial Number (SN) yang valid.');
        }

        $target = $request->input('target_group');
        $queued = 0;
        $label = '';

        if ($target === 'gtk') {
            $teachers = User::operationalPersonnel()->where('is_active', true)->get();
            foreach ($teachers as $t) {
                $pin = $t->nigs ?: $t->id;
                $cleanName = substr(trim(preg_replace('/[^\w\s\.\,\-]/', '', $t->name)), 0, 24);

                AttendanceDeviceCommand::create([
                    'device_sn' => $sn,
                    'command' => "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=0\tGrp=1",
                    'status' => 'pending',
                ]);
                $queued++;
            }
            $label = "{$teachers->count()} GTK (Guru & Tenaga Kependidikan)";
        } elseif ($target === 'all_students') {
            $students = Student::where('is_active', true)->get();
            foreach ($students as $st) {
                $pin = $st->nisn ?: ($st->nis ?: $st->id);
                $cleanName = substr(trim(preg_replace('/[^\w\s\.\,\-]/', '', $st->name)), 0, 24);

                AttendanceDeviceCommand::create([
                    'device_sn' => $sn,
                    'command' => "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=0\tGrp=1",
                    'status' => 'pending',
                ]);
                $queued++;
            }
            $label = "{$students->count()} Seluruh Siswa Aktif";
        } elseif (str_starts_with($target, 'class_')) {
            $classId = (int) str_replace('class_', '', $target);
            $classroom = Classroom::find($classId);
            $students = Student::where('classroom_id', $classId)->where('is_active', true)->get();

            foreach ($students as $st) {
                $pin = $st->nisn ?: ($st->nis ?: $st->id);
                $cleanName = substr(trim(preg_replace('/[^\w\s\.\,\-]/', '', $st->name)), 0, 24);

                AttendanceDeviceCommand::create([
                    'device_sn' => $sn,
                    'command' => "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=0\tGrp=1",
                    'status' => 'pending',
                ]);
                $queued++;
            }
            $label = "{$students->count()} Siswa Kelas ".($classroom?->name ?? $classId);
        }

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', "Berhasil menjadwalkan upload {$label} (total {$queued} perintah) ke mesin {$device->name}.");
    }

    /**
     * Send Custom Raw Command to machine
     */
    public function sendCustomCommand(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $request->validate([
            'raw_command' => 'required|string|max:255',
        ]);

        $sn = $device->device_sn;
        if (empty($sn)) {
            return redirect()->back()->with('error', 'Mesin belum memiliki Serial Number (SN) yang valid.');
        }

        $cmd = trim($request->input('raw_command'));

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => $cmd,
            'status' => 'pending',
        ]);

        AttendanceDeviceCommand::create([
            'device_sn' => $sn,
            'command' => 'CHECK',
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', "Perintah khusus '{$cmd}' berhasil dimasukkan ke antrean mesin {$device->name}.");
    }

    /**
     * Clear / Cancel pending commands for this device
     */
    public function cancelPendingCommands(AttendanceDevice $device): RedirectResponse
    {
        $sn = $device->device_sn;
        $deleted = AttendanceDeviceCommand::where('device_sn', $sn)->where('status', 'pending')->delete();

        return redirect()->back()->with('info', "Berhasil membatalkan {$deleted} antrean perintah tertunda pada mesin {$device->name}.");
    }

    /**
     * Request device to upload all stored biometrics (fingerprints & face) to server
     */
    public function backupBiometrics(AttendanceDevice $device): RedirectResponse
    {
        $this->deviceService->queryBiometrics($device);

        return redirect()->back()->with('success', "Permintaan penarikan seluruh data biometrik (sidik jari & wajah) dikirim ke {$device->name}. Mesin akan mengunggah data saat terhubung.");
    }

    /**
     * Push biometrics saved in server to another target device
     */
    public function cloneBiometrics(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $request->validate([
            'target_device_id' => 'required|exists:attendance_devices,id',
        ]);

        $targetDevice = AttendanceDevice::findOrFail($request->target_device_id);

        if (empty($targetDevice->device_sn)) {
            return redirect()->back()->with('error', "Mesin target ({$targetDevice->name}) belum memiliki Serial Number terdaftar.");
        }

        $queued = $this->deviceService->pushBiometricsToDevice($targetDevice);

        if ($queued === 0) {
            return redirect()->back()->with('warning', 'Belum ada data biometrik tersimpan di server. Silakan klik "Tarik Biometrik dari Mesin" terlebih dahulu pada mesin sumber.');
        }

        return redirect()->back()->with('success', "Berhasil memasukkan {$queued} data template biometrik ke antrean mesin {$targetDevice->name}. Mesin akan mendownload template secara otomatis!");
    }

    /**
     * Live Polling API for the Cockpit page
     */
    public function apiStatus(AttendanceDevice $device): JsonResponse
    {
        $sn = $device->device_sn;

        $device->refresh();
        $pendingCount = AttendanceDeviceCommand::where('device_sn', $sn)->where('status', 'pending')->count();
        $recentCommands = AttendanceDeviceCommand::where('device_sn', $sn)->latest('id')->take(10)->get();

        return response()->json([
            'status' => $device->status,
            'last_activity' => $device->last_activity_at ? $device->last_activity_at->diffForHumans() : '-',
            'pending_commands' => $pendingCount,
            'recent_commands' => $recentCommands,
        ]);
    }

    /**
     * Live Polling API for All Devices Overview (Sync & Wipe Progress Tracker)
     */
    public function apiAllStatus(): JsonResponse
    {
        $devices = AttendanceDevice::where('is_active', true)->get();

        $deviceBiometrics = AttendanceDeviceBiometric::selectRaw('device_sn, count(distinct pin) as user_count, count(*) as template_count')
            ->groupBy('device_sn')
            ->get()
            ->keyBy('device_sn');

        $deviceList = [];
        $totalPendingAll = 0;

        foreach ($devices as $dev) {
            $sn = $dev->device_sn;
            $pendingCount = $sn ? AttendanceDeviceCommand::where('device_sn', $sn)->where('status', 'pending')->count() : 0;
            $sentCount = $sn ? AttendanceDeviceCommand::where('device_sn', $sn)->where('status', 'sent')->count() : 0;
            $totalPendingAll += $pendingCount + $sentCount;

            $latestCmd = $sn ? AttendanceDeviceCommand::where('device_sn', $sn)->latest('id')->first() : null;

            $bioStat = ($sn && $deviceBiometrics->has($sn)) ? $deviceBiometrics->get($sn) : null;
            $bioUsers = $bioStat ? (int) $bioStat->user_count : 0;
            $bioTemplates = $bioStat ? (int) $bioStat->template_count : 0;

            // Tentukan status operasi terakhir
            $lastOperation = 'Siap (Idle)';
            $lastOperationBadge = 'bg-secondary-lt text-secondary';
            $lastOperationTime = '-';

            if ($latestCmd) {
                $lastOperationTime = $latestCmd->updated_at ? $latestCmd->updated_at->diffForHumans() : '-';
                $cmdText = strtoupper($latestCmd->command);

                if (str_contains($cmdText, 'CLEAR DATA') || str_contains($cmdText, 'CLEAR_ENROLL_DATA')) {
                    $lastOperation = ($latestCmd->status === 'executed')
                        ? 'Pengosongan Data Mesin Selesai'
                        : 'Sedang Mengosongkan Data Mesin...';
                    $lastOperationBadge = ($latestCmd->status === 'executed')
                        ? 'bg-danger-lt text-danger'
                        : 'bg-warning-lt text-warning';
                } elseif (str_contains($cmdText, 'DATA UPDATE FINGERTMP')) {
                    $lastOperation = ($latestCmd->status === 'executed')
                        ? 'Sinkron Biometrik Selesai'
                        : 'Sedang Mengirim Biometrik...';
                    $lastOperationBadge = ($latestCmd->status === 'executed')
                        ? 'bg-purple-lt text-purple'
                        : 'bg-indigo-lt text-indigo';
                } elseif (str_contains($cmdText, 'DATA UPDATE USERINFO')) {
                    $lastOperation = ($latestCmd->status === 'executed')
                        ? 'Sinkron Data Pengguna Selesai'
                        : 'Sedang Mengirim Pengguna...';
                    $lastOperationBadge = ($latestCmd->status === 'executed')
                        ? 'bg-azure-lt text-azure'
                        : 'bg-info-lt text-info';
                } elseif (str_contains($cmdText, 'CLEAR LOG') || str_contains($cmdText, 'CLEAR_LOG_DATA')) {
                    $lastOperation = ($latestCmd->status === 'executed')
                        ? 'Pembersihan Log Mesin Selesai'
                        : 'Sedang Membersihkan Log...';
                    $lastOperationBadge = 'bg-warning-lt text-warning';
                } elseif (str_contains($cmdText, 'QUERY ATTLOG')) {
                    $lastOperation = ($latestCmd->status === 'executed')
                        ? 'Tarik Log Selesai'
                        : 'Sedang Mengambil Log...';
                    $lastOperationBadge = 'bg-teal-lt text-teal';
                } else {
                    $lastOperation = $latestCmd->command;
                    $lastOperationBadge = ($latestCmd->status === 'executed')
                        ? 'bg-success-lt text-success'
                        : 'bg-warning-lt text-warning';
                }
            }

            $deviceList[] = [
                'id' => $dev->id,
                'name' => $dev->name,
                'ip_address' => $dev->ip_address,
                'port' => $dev->port,
                'device_sn' => $sn ?: '-',
                'status' => $dev->status,
                'last_activity' => $dev->last_activity_at ? $dev->last_activity_at->diffForHumans() : '-',
                'pending_commands' => $pendingCount,
                'sent_commands' => $sentCount,
                'in_progress' => ($pendingCount > 0 || $sentCount > 0),
                'bio_users' => $bioUsers,
                'bio_templates' => $bioTemplates,
                'last_operation' => $lastOperation,
                'last_operation_badge' => $lastOperationBadge,
                'last_operation_time' => $lastOperationTime,
                'latest_command' => $latestCmd ? [
                    'id' => $latestCmd->id,
                    'command' => mb_substr($latestCmd->command, 0, 50),
                    'status' => $latestCmd->status,
                    'response' => $latestCmd->response,
                ] : null,
            ];
        }

        // Ambil 8 riwayat aksi perintah terbaru di seluruh mesin untuk timeline laporan
        $recentReports = AttendanceDeviceCommand::with('device')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'device_name' => $c->device?->name ?? ($c->device_sn ?: 'Mesin'),
                    'command' => mb_substr($c->command, 0, 45),
                    'full_command' => $c->command,
                    'status' => $c->status,
                    'response' => $c->response,
                    'time' => $c->created_at ? $c->created_at->format('H:i:s') : '-',
                    'diff' => $c->created_at ? $c->created_at->diffForHumans() : '-',
                ];
            });

        return response()->json([
            'success' => true,
            'timestamp' => now()->format('H:i:s'),
            'total_pending_all' => $totalPendingAll,
            'devices' => $deviceList,
            'recent_reports' => $recentReports,
        ]);
    }

    /**
     * Display Biometric Master Vault
     */
    public function biometrics(Request $request): View
    {
        $devices = AttendanceDevice::where('is_active', true)->orderBy('name')->get();

        $totalTemplates = AttendanceBiometric::count();
        $totalFingerprints = AttendanceBiometric::where('bio_type', 'fingerprint')->count();
        $totalFaces = AttendanceBiometric::where('bio_type', 'face')->count();
        $totalSizeBytes = AttendanceBiometric::sum('size');

        $enrolledPins = AttendanceBiometric::distinct()->pluck('pin')->toArray();
        $totalEnrolledUsers = count($enrolledPins);

        $biometrics = AttendanceBiometric::orderBy('pin')->get();

        $teachers = User::operationalPersonnel()
            ->where(function ($q) use ($enrolledPins) {
                $q->whereIn('nigs', $enrolledPins)
                    ->orWhereIn('id', $enrolledPins);
            })
            ->get();

        $teachersByPin = collect();
        foreach ($teachers as $t) {
            if ($t->nigs) {
                $teachersByPin->put((string) $t->nigs, $t);
            }
            $teachersByPin->put((string) $t->id, $t);
        }

        $studentsByPin = Student::with('classroom')
            ->where(function ($q) use ($enrolledPins) {
                $q->whereIn('nisn', $enrolledPins)->orWhereIn('nis', $enrolledPins)->orWhereIn('id', $enrolledPins);
            })
            ->get()
            ->keyBy(fn ($s) => (string) ($s->nisn ?: ($s->nis ?: $s->id)));

        $devicesBySn = AttendanceDevice::all()->keyBy('device_sn');

        $groupedBiometrics = $biometrics->groupBy('pin')->map(function ($items, $pin) use ($teachersByPin, $studentsByPin, $devicesBySn) {
            $teacher = $teachersByPin->get((string) $pin);
            $student = $studentsByPin->get((string) $pin);

            $userName = 'Tidak Dikenal / Belum Ada Akun';
            $userType = 'unknown';
            $info = '-';

            if ($teacher) {
                $userName = $teacher->name;
                $userType = 'teacher';
                $nigsDisplay = $teacher->nigs ?: '-';
                $info = 'GTK / Guru (NIGS: '.$nigsDisplay.')';
            } elseif ($student) {
                $userName = $student->name;
                $userType = 'student';
                $info = 'Siswa Kelas '.($student->classroom?->name ?? '-').' (NISN: '.$student->nisn.')';
            }

            $sourceDevice = $devicesBySn->get($items->first()->source_device_sn);

            return [
                'pin' => $pin,
                'name' => $userName,
                'type' => $userType,
                'info' => $info,
                'items' => $items,
                'finger_count' => $items->where('bio_type', 'fingerprint')->count(),
                'face_count' => $items->where('bio_type', 'face')->count(),
                'source_device_name' => $sourceDevice?->name ?? ($items->first()->source_device_sn ?: 'Mesin Terdaftar'),
                'last_updated' => $items->max('updated_at'),
            ];
        });

        $search = $request->query('search');
        if ($search) {
            $groupedBiometrics = $groupedBiometrics->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['name']), strtolower($search))
                    || str_contains(strtolower($item['pin']), strtolower($search))
                    || str_contains(strtolower($item['info']), strtolower($search));
            });
        }

        $missingTeachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->whereNotIn('nigs', $enrolledPins)
            ->whereNotIn('id', $enrolledPins)
            ->orderBy('name')
            ->take(50)
            ->get();

        $missingStudents = Student::with('classroom')
            ->where('is_active', true)
            ->whereNotIn('nisn', $enrolledPins)
            ->whereNotIn('nis', $enrolledPins)
            ->whereNotIn('id', $enrolledPins)
            ->orderBy('classroom_id')
            ->orderBy('name')
            ->take(50)
            ->get();

        $totalMissingTeachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->whereNotIn('nigs', $enrolledPins)
            ->whereNotIn('id', $enrolledPins)
            ->count();

        $totalMissingStudents = Student::where('is_active', true)
            ->whereNotIn('nisn', $enrolledPins)
            ->whereNotIn('nis', $enrolledPins)
            ->whereNotIn('id', $enrolledPins)
            ->count();

        return view('attendance.devices.biometrics', compact(
            'devices',
            'totalTemplates',
            'totalFingerprints',
            'totalFaces',
            'totalSizeBytes',
            'totalEnrolledUsers',
            'groupedBiometrics',
            'missingTeachers',
            'missingStudents',
            'totalMissingTeachers',
            'totalMissingStudents',
            'search'
        ));
    }

    /**
     * Broadcast / Sync Biometrics from Master Server to target devices
     */
    public function broadcastBiometrics(Request $request): RedirectResponse|JsonResponse
    {
        $targetId = $request->input('target_device_id');

        if ($targetId === 'all' || empty($targetId)) {
            $targets = AttendanceDevice::where('is_active', true)->whereNotNull('device_sn')->get();
        } else {
            $targets = AttendanceDevice::where('is_active', true)->where('id', $targetId)->get();
        }

        if ($targets->isEmpty()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Tidak ada mesin tujuan aktif yang ditemukan.'], 404);
            }

            return redirect()->back()->with('error', 'Tidak ada mesin tujuan aktif yang ditemukan.');
        }

        $totalQueued = 0;
        $names = [];
        $results = [];

        foreach ($targets as $target) {
            $queued = $this->deviceService->pushBiometricsToDevice($target);
            $totalQueued += $queued;
            $names[] = $target->name;
            $results[] = [
                'id' => $target->id,
                'name' => $target->name,
                'ip' => $target->ip_address,
                'port' => $target->port,
                'queued' => $queued,
                'method' => ($target->port == 4370 && ! empty($target->ip_address)) ? 'Direct Socket (Instan)' : 'ADMS Queue (Otomatis)',
            ];
        }

        if ($totalQueued === 0) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Belum ada data biometrik yang tersimpan di Master Server untuk disinkronkan.'], 422);
            }

            return redirect()->back()->with('warning', 'Belum ada data biometrik yang tersimpan di Master Server untuk disinkronkan.');
        }

        $targetNames = implode(', ', $names);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'total_queued' => $totalQueued,
                'targets' => $results,
                'message' => "Berhasil memproses {$totalQueued} template biometrik untuk {$targetNames}.",
            ]);
        }

        return redirect()->back()->with('success', "Berhasil memasukkan {$totalQueued} template biometrik ke antrean sinkronisasi untuk mesin: {$targetNames}. Mesin akan otomatis mengunduh template!");
    }

    /**
     * Delete biometric template from master vault
     */
    public function destroyBiometric(AttendanceBiometric $biometric): RedirectResponse
    {
        $pin = $biometric->pin;
        $biometric->delete();

        return redirect()->back()->with('success', "Template biometrik PIN {$pin} berhasil dihapus dari Master Server.");
    }

    /**
     * Download saved backup files for Geisa (04) securely from private disk.
     */
    public function downloadGeisaBackup(string $file = 'zip')
    {
        $user = auth()->user();
        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Akses unduhan cadangan biometrik hanya diizinkan untuk Super Administrator.');
        }

        $fileMap = [
            'zip' => 'backup_geisa_04_lengkap.zip',
            'users_csv' => 'users.csv',
            'users_json' => 'users.json',
            'biometrics_csv' => 'biometrics.csv',
            'biometrics_json' => 'biometrics.json',
            'logs_csv' => 'attendance_records.csv',
            'logs_json' => 'attendance_records.json',
            'info' => 'device_info.json',
        ];

        if (! isset($fileMap[$file])) {
            abort(404, 'Tipe berkas cadangan biometrik tidak valid.');
        }

        $filename = $fileMap[$file];
        $baseDir = storage_path('app/private/backups/geisa_04');
        $filePath = $baseDir.DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($filePath)) {
            abort(404, 'Berkas cadangan tidak ditemukan.');
        }

        return response()->download($filePath, $filename, [
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
