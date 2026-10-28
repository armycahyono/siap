<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Attendance\GeotagAttendanceController;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\SchoolSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileAttendanceController extends Controller
{
    /**
     * Get attendance status, school location config, and recent logs for SIAP Mobile.
     */
    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $setting = SchoolSetting::getSetting();
        $todayStr = Carbon::today()->toDateString();

        $todayLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->orderBy('scanned_at')
            ->get();

        $checkInLog = $todayLogs->first();
        $checkOutLog = $todayLogs->count() > 1 ? $todayLogs->last() : null;

        $recentLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', '>=', Carbon::today()->subDays(7)->toDateString())
            ->orderBy('scanned_at', 'desc')
            ->take(15)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'scanned_at' => $log->scanned_at->format('Y-m-d H:i:s'),
                    'time' => $log->scanned_at->format('H:i'),
                    'date' => $log->scanned_at->format('d M Y'),
                    'type' => $log->status,
                    'status_label' => $log->status === 'check_in' ? 'Masuk' : 'Pulang',
                    'method' => $log->method ?? 'geotag',
                    'verified' => (bool) $log->verified,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'geotag_enabled' => (bool) $setting->geotag_attendance_enabled,
                'strict_radius' => (bool) $setting->geotag_strict_radius,
                'require_selfie' => (bool) $setting->geotag_require_selfie,
                'school_location' => [
                    'latitude' => (float) ($setting->school_latitude ?? -7.95590200),
                    'longitude' => (float) ($setting->school_longitude ?? 111.97259400),
                    'radius_meters' => (int) ($setting->school_radius_meters ?: 100),
                ],
                'today' => [
                    'has_check_in' => (bool) $checkInLog,
                    'check_in_time' => $checkInLog ? $checkInLog->scanned_at->format('H:i') : null,
                    'has_check_out' => (bool) $checkOutLog,
                    'check_out_time' => $checkOutLog ? $checkOutLog->scanned_at->format('H:i') : null,
                    'can_check_in' => ! $checkInLog,
                    'can_check_out' => (bool) $checkInLog,
                ],
                'recent_logs' => $recentLogs->values(),
            ],
        ]);
    }

    /**
     * Store mobile geotagging attendance record.
     */
    public function store(Request $request): JsonResponse
    {
        $controller = app(GeotagAttendanceController::class);

        /** @var JsonResponse $response */
        $response = $controller->store($request);

        return $response;
    }
}
