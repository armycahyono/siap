<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Attendance\AttendanceController;
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
                    'status_label' => $log->status_label,
                    'method' => $log->method_label,
                    'verify_type' => $log->verify_type,
                    'verify_type_label' => $log->verify_type_label,
                    'device_sn' => $log->device_sn,
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

    /**
     * Get Teacher & Staff Attendance Monitoring Report for Mobile.
     */
    public function getTeacherAttendanceReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        if (! $user->canAccessTeacherAttendanceReport()) {
            return response()->json(['success' => false, 'message' => 'Akses laporan presensi GTK hanya diperuntukkan bagi Kepala Sekolah, Kurikulum, dan Manajemen.'], 403);
        }

        $attController = app(AttendanceController::class);
        $dateInfo = $attController->resolveReportDates($request);

        $startDate = $dateInfo['startDate'];
        $endDate = $dateInfo['endDate'];
        $subType = $request->query('sub_type', 'all');
        $search = $request->query('search');

        $recap = $attController->buildTeacherStaffIndividualRecap($startDate, $endDate, $subType, null, $search);
        $people = $recap['people'];
        $summary = $recap['summary'];

        $formattedPeople = $people->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'nip' => $p->nip ?? '-',
                'position' => $p->position ?? 'Guru/Staf',
                'hadir' => $p->hadir ?? 0,
                'terlambat' => $p->terlambat ?? 0,
                'pulang_cepat' => $p->pulang_cepat ?? 0,
                'sakit' => $p->sakit ?? 0,
                'izin' => $p->izin ?? 0,
                'dinas_luar' => $p->dinas_luar ?? 0,
                'cuti' => $p->cuti ?? 0,
                'alfa' => $p->alfa ?? 0,
                'persentase_kehadiran' => $p->persentase_kehadiran ?? 0,
                'status_badge' => ($p->alfa ?? 0) > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700',
            ];
        })->values();

        $queryParams = http_build_query($request->query());

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_label' => $dateInfo['periodLabel'] ?? '',
                'period_type' => $dateInfo['periodType'] ?? 'custom',
                'sub_type' => $subType,
                'search' => $search,
                'summary' => $summary,
                'people' => $formattedPeople,
                'total_gtk' => $formattedPeople->count(),
                'export_urls' => [
                    'print' => route('attendance.report.teachers.print').($queryParams ? '?'.$queryParams : ''),
                    'pdf' => route('attendance.report.teachers.pdf').($queryParams ? '?'.$queryParams : ''),
                    'excel' => route('attendance.report.teachers.excel').($queryParams ? '?'.$queryParams : ''),
                ],
            ],
        ]);
    }
}
