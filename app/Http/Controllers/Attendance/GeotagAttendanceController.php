<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'type' => ['required', 'in:check_in,check_out'],
            'notes' => ['nullable', 'string', 'max:255'],
            'selfie' => ['nullable', 'image', 'max:5120'],
            'selfie_base64' => ['nullable', 'string'],
        ]);

        $userLat = (float) $validated['latitude'];
        $userLon = (float) $validated['longitude'];
        $accuracy = isset($validated['accuracy']) ? (float) $validated['accuracy'] : null;
        $type = $validated['type'];

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

        // Upload selfie jika ada
        $selfiePath = null;
        if ($request->hasFile('selfie')) {
            $selfiePath = $request->file('selfie')->store('attendance/geotag/'.date('Y-m'), 'public');
        } elseif (! empty($validated['selfie_base64']) && str_starts_with($validated['selfie_base64'], 'data:image')) {
            $imageParts = explode(';base64,', $validated['selfie_base64']);
            if (count($imageParts) === 2) {
                $imageTypeAux = explode('image/', $imageParts[0]);
                $imageType = $imageTypeAux[1] ?? 'jpeg';
                $imageBase64 = base64_decode($imageParts[1]);
                $fileName = 'attendance/geotag/'.date('Y-m').'/selfie_'.$user->id.'_'.time().'.'.$imageType;
                Storage::disk('public')->put($fileName, $imageBase64);
                $selfiePath = $fileName;
            }
        }

        if ($setting->geotag_require_selfie && ! DeviceDetector::isMobile() && empty($selfiePath)) {
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

        $rawPayload = [
            'latitude' => $userLat,
            'longitude' => $userLon,
            'accuracy_meters' => $accuracy,
            'distance_to_school_meters' => $distance,
            'is_within_radius' => $isWithinRadius,
            'school_radius_meters' => $radiusLimit,
            'notes' => $validated['notes'] ?? null,
            'selfie_url' => $selfiePath ? Storage::url($selfiePath) : null,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'source' => 'PWA_MOBILE',
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

        $statusLabel = $type === 'check_in' ? 'Masuk' : 'Pulang';
        $successMsg = "Berhasil mencatat presensi {$statusLabel} pada pukul {$now->format('H:i')} WIB (Jarak: {$distance} m).";

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
}
