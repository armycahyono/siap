<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use App\Services\ActivityLogger;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use finfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GeotagAttendanceController extends Controller
{
    /**
     * Hitung jarak dua titik koordinat (Haversine Formula) dalam satuan meter.
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
    }

    /**
     * Halaman Utama Presensi Geotagging Mobile GTK.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();
        $todayStr = Carbon::today()->toDateString();

        // Riwayat presensi user hari ini
        $todayLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->orderBy('scanned_at')
            ->get();

        $checkInLog = $todayLogs->first();
        $checkOutLog = $todayLogs->count() > 1 ? $todayLogs->last() : null;

        // Riwayat 7 hari terakhir dengan penentuan Masuk vs Pulang akurat
        $allRecentLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', '>=', Carbon::today()->subDays(7)->toDateString())
            ->orderBy('scanned_at')
            ->get();

        $logsByDate = $allRecentLogs->groupBy(fn ($l) => $l->scanned_at->toDateString());
        foreach ($logsByDate as $dateStr => $dayLogs) {
            if ($dayLogs->count() === 1) {
                $log = $dayLogs->first();
                $log->determined_status = ($log->status === 'check_in' || $log->scanned_at->format('H:i') < '12:00') ? 'check_in' : 'check_out';
            } else {
                $firstLog = $dayLogs->first();
                foreach ($dayLogs as $log) {
                    if ($log->id === $firstLog->id) {
                        $log->determined_status = 'check_in';
                    } else {
                        $log->determined_status = 'check_out';
                    }
                }
            }
        }

        $recentLogs = $allRecentLogs->sortByDesc('scanned_at')->take(15);

        // Info jadwal & tugas hari ini
        $dayNames = [
            0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
            4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeek] ?? 'Senin';

        $isMobile = DeviceDetector::isMobile();

        $schedulesCount = $isMobile ? 0 : LessonSchedule::where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->count();

        $isPiketToday = $isMobile ? false : PiketSchedule::where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->exists();

        if ($isMobile) {
            return view('mobile.attendance.geotag', compact(
                'user',
                'setting',
                'todayLogs',
                'checkInLog',
                'checkOutLog',
                'recentLogs',
                'schedulesCount',
                'isPiketToday'
            ));
        }

        return view('web.attendance.geotag', compact(
            'user',
            'setting',
            'todayLogs',
            'checkInLog',
            'checkOutLog',
            'recentLogs',
            'schedulesCount',
            'isPiketToday'
        ));
    }

    /**
     * Simpan rekaman presensi geotagging GPS.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        if (! $setting->geotag_attendance_enabled) {
            $msg = 'Presensi mandiri Geotagging sedang dinonaktifkan oleh sekolah.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        try {
            $validated = $request->validate([
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy' => ['nullable', 'numeric', 'min:0', 'max:50000'],
                'type' => ['required', 'in:check_in,check_out'],
                'notes' => ['nullable', 'string', 'max:255'],
                'selfie' => ['nullable', 'file', 'max:5120'],
                'selfie_base64' => ['nullable', 'string'],
            ]);

            // Validasi & Simpan Foto Selfie (Base64 atau Uploaded File)
            $selfiePath = null;
            if ($request->hasFile('selfie')) {
                $selfiePath = $this->processUploadedSelfie($request->file('selfie'), $user->id);
            } elseif (! empty($validated['selfie_base64'])) {
                $selfiePath = $this->processBase64Selfie($validated['selfie_base64'], $user->id);
            }
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first(),
                    'errors' => $e->validator->errors(),
                ], 422);
            }

            throw $e;
        }

        $userLat = (float) $validated['latitude'];
        $userLon = (float) $validated['longitude'];
        $accuracy = isset($validated['accuracy']) ? (float) $validated['accuracy'] : null;
        $type = $validated['type'];

        // Validasi kualitas sinyal GPS (akurasi)
        if ($accuracy !== null && $accuracy > 1000) {
            $msg = "Akurasi sinyal GPS perangkat Anda terlalu rendah (±{$accuracy} meter). Pastikan GPS aktif dengan akurasi tinggi dan berada di area terbuka.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'accuracy' => $accuracy,
                ], 422);
            }

            return back()->with('error', $msg);
        }

        // Cek koordinat sekolah
        $schoolLat = $setting->school_latitude ?? -7.95590200;
        $schoolLon = $setting->school_longitude ?? 111.97259400;
        $radiusLimit = $setting->school_radius_meters ?: 100;

        $distance = self::calculateDistance($userLat, $userLon, (float) $schoolLat, (float) $schoolLon);
        $isWithinRadius = $distance <= $radiusLimit;

        // Validasi pembatasan radius ketat
        if ($setting->geotag_strict_radius && ! $isWithinRadius) {
            $msg = "Anda terdeteksi berada di luar area sekolah ({$distance} meter dari sekolah, batas maksimal {$radiusLimit} meter). Presensi ditolak.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'distance' => $distance,
                    'radius_limit' => $radiusLimit,
                ], 422);
            }

            return back()->with('error', $msg);
        }

        // Kebijakan kewajiban selfie:
        // Desktop mewajibkan selfie jika pengaturan aktif.
        // Mobile UI Blade tanpa widget kamera tidak diblokir, namun jika selfie dikirim dari PWA/mobile tetap divalidasi dan disimpan.
        if ($setting->geotag_require_selfie && ! DeviceDetector::isMobile($request) && empty($selfiePath)) {
            $msg = 'Foto selfie wajib diambil untuk melakukan presensi.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $now = Carbon::now();
        $todayStr = $now->toDateString();

        // Cek log hari ini
        $existingLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->get();

        if ($type === 'check_in' && $existingLogs->count() > 0) {
            $msg = 'Anda sudah melakukan presensi masuk hari ini pada pukul '.$existingLogs->first()->scanned_at->format('H:i').' WIB.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('warning', $msg);
        }

        $isMobileDevice = DeviceDetector::isMobile($request);

        $rawPayload = [
            'latitude' => $userLat,
            'longitude' => $userLon,
            'accuracy_meters' => $accuracy,
            'distance_to_school_meters' => $distance,
            'is_within_radius' => $isWithinRadius,
            'school_radius_meters' => $radiusLimit,
            'notes' => $validated['notes'] ?? null,
            'selfie_path' => $selfiePath,
            'selfie_url' => null,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'source' => $isMobileDevice ? 'PWA_MOBILE' : 'WEB_DESKTOP',
        ];

        $pin = $user->nigs ?: (string) $user->id;

        $log = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => $pin,
            'user_type' => 'teacher',
            'user_id' => $user->id,
            'name' => $user->name,
            'scanned_at' => $now,
            'status' => $type,
            'verify_type' => 'geotag',
            'work_code' => $isWithinRadius ? 'IN_RADIUS' : 'OUT_RADIUS',
            'raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES),
        ]);

        if ($selfiePath) {
            $rawPayload['selfie_url'] = route('attendance.geotag.selfie', $log);
            $log->update(['raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES)]);
        }

        $statusLabel = $type === 'check_in' ? 'Masuk' : 'Pulang';
        $successMsg = "Berhasil mencatat presensi {$statusLabel} pada pukul {$now->format('H:i')} WIB (Jarak: {$distance} m).";

        try {
            ActivityLogger::log(
                action: 'attendance',
                description: "Melakukan presensi {$statusLabel} pada pukul {$now->format('H:i')} WIB (Jarak: {$distance} m) via ".($isMobileDevice ? 'SIAP Mobile' : 'Web Desktop').'.',
                subject: $log,
                properties: [
                    'source' => $isMobileDevice ? 'Mobile' : 'Web',
                    'type' => $type,
                    'distance' => $distance,
                    'time' => $now->format('H:i:s'),
                ],
                user: $user
            );
        } catch (\Throwable) {
            // Non-blocking log failure
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'type' => $type,
                'time' => $now->format('H:i:s'),
                'distance' => $distance,
                'log' => $log,
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Decode, validate, and securely store base64 selfie image.
     *
     * @throws ValidationException
     */
    protected function processBase64Selfie(?string $base64Payload, int $userId): ?string
    {
        if (empty($base64Payload) || ! is_string($base64Payload)) {
            return null;
        }

        // 1. Batasi ukuran payload Base64 sebelum pemrosesan berat (~7.5MB string untuk max 5MB binary)
        if (strlen($base64Payload) > 7500000) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Ukuran data foto selfie melebihi batas maksimal yang diizinkan (5MB).',
            ]);
        }

        // 2. Validasi ketat struktur Data URL (hanya JPEG, PNG, dan WebP)
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=\s]+)$/i', $base64Payload, $matches)) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Format foto selfie tidak valid. Hanya format gambar Data URL Base64 (JPEG, PNG, WebP) yang didukung.',
            ]);
        }

        $rawEncoded = preg_replace('/\s+/', '', $matches[2]);
        if (empty($rawEncoded)) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Data Base64 foto selfie tidak boleh kosong.',
            ]);
        }

        // 3. Dekode Base64 dengan mode ketat (strict mode)
        $binaryData = base64_decode($rawEncoded, true);
        if ($binaryData === false || $binaryData === '') {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Data Base64 foto selfie rusak atau gagal didekode.',
            ]);
        }

        // 4. Validasi ukuran biner hasil dekode (maksimal 5MB = 5.242.880 bytes)
        if (strlen($binaryData) > 5242880) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Ukuran berkas gambar hasil dekode melebihi batas maksimal 5MB.',
            ]);
        }

        // 5. Verifikasi tipe MIME nyata berkas menggunakan Fileinfo buffer di sisi server
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($binaryData);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (! isset($allowedMimes[$detectedMime])) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Berkas yang diunggah bukan format gambar yang diizinkan (hanya JPEG, PNG, WebP).',
            ]);
        }

        // 6. Verifikasi integritas gambar menggunakan getimagesizefromstring
        $imageSizeInfo = @getimagesizefromstring($binaryData);
        if ($imageSizeInfo === false || empty($imageSizeInfo[0]) || empty($imageSizeInfo[1])) {
            throw ValidationException::withMessages([
                'selfie_base64' => 'Data berkas bukan merupakan gambar valid.',
            ]);
        }

        // 7. Nama dan ekstensi berkas sepenuhnya ditentukan oleh server berdasarkan MIME terverifikasi
        $extension = $allowedMimes[$detectedMime];
        $monthDir = 'attendance/geotag/'.date('Y-m');
        $filename = 'selfie_'.$userId.'_'.time().'_'.Str::random(12).'.'.$extension;
        $fullPath = $monthDir.'/'.$filename;

        // 8. Simpan berkas gambar yang telah lolos seluruh validasi ke disk privat (local)
        Storage::disk('local')->put($fullPath, $binaryData);

        return $fullPath;
    }

    /**
     * Validate and securely store uploaded file selfie.
     *
     * @param  UploadedFile  $file
     *
     * @throws ValidationException
     */
    protected function processUploadedSelfie($file, int $userId): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file->getRealPath());

        if (! isset($allowedMimes[$detectedMime])) {
            throw ValidationException::withMessages([
                'selfie' => 'Berkas unggahan bukan format gambar yang diizinkan (hanya JPEG, PNG, WebP).',
            ]);
        }

        $imageSizeInfo = @getimagesize($file->getRealPath());
        if ($imageSizeInfo === false || empty($imageSizeInfo[0]) || empty($imageSizeInfo[1])) {
            throw ValidationException::withMessages([
                'selfie' => 'Berkas unggahan tidak dapat diverifikasi sebagai gambar valid.',
            ]);
        }

        $extension = $allowedMimes[$detectedMime];
        $monthDir = 'attendance/geotag/'.date('Y-m');
        $filename = 'selfie_'.$userId.'_'.time().'_'.Str::random(12).'.'.$extension;

        return $file->storeAs($monthDir, $filename, 'local');
    }

    /**
     * View private attendance selfie photo with strict authentication & authorization.
     */
    public function viewSelfie(AttendanceLog $log)
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        // Otorisasi: Super Admin, Kepala Sekolah, atau Pemilik Data Presensi
        $isAuthorized = $currentUser->isSuperAdmin()
            || $currentUser->isPrincipal()
            || ($log->user_type === 'teacher' && $log->user_id === $currentUser->id);

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat foto selfie ini.');
        }

        $payload = is_array($log->raw_payload) ? $log->raw_payload : json_decode($log->raw_payload ?? '', true);
        if (! is_array($payload)) {
            abort(404, 'Data presensi tidak valid.');
        }

        $selfiePath = $payload['selfie_path'] ?? null;

        // Fallback untuk record historis yang hanya memiliki selfie_url
        if (empty($selfiePath) && ! empty($payload['selfie_url']) && is_string($payload['selfie_url'])) {
            $parsedPath = parse_url($payload['selfie_url'], PHP_URL_PATH);
            if ($parsedPath && str_contains($parsedPath, 'attendance/geotag/')) {
                $pos = strpos($parsedPath, 'attendance/geotag/');
                $candidate = substr($parsedPath, $pos);
                if (! empty($candidate)) {
                    $selfiePath = $candidate;
                }
            }
        }

        // Path Traversal Protection
        if (! $selfiePath || str_contains($selfiePath, '..')) {
            abort(404, 'Foto selfie tidak ditemukan.');
        }

        // Whitelist Directory: Pastikan path hanya berada di dalam direktori attendance/geotag/
        if (! str_starts_with($selfiePath, 'attendance/geotag/')) {
            abort(404, 'Path foto selfie berada di luar direktori yang diizinkan.');
        }

        // Dual-disk resolution: prioritas disk 'local' (privat), fallback disk 'public' untuk file lama
        $disk = null;
        if (Storage::disk('local')->exists($selfiePath)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($selfiePath)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File foto selfie tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($selfiePath);
    }
}
