<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceSession;
use App\Services\AcademicYearContext;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileDashboardController extends Controller
{
    /**
     * Get aggregated dashboard payload for SIAP Mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $setting = SchoolSetting::getSetting();

        $academicYearContext = app(AcademicYearContext::class);
        $operationalYear = $academicYearContext->getOperationalYear();
        $operationalAcademicYear = $operationalYear->name;
        $operationalSemester = $operationalYear->semester ?? SchoolSetting::getSetting(true)->semester ?? 'Ganjil';
        $operationalAyModel = AcademicYear::where('name', $operationalAcademicYear)->first();

        $todayStr = now()->toDateString();
        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        // 1. Status Kehadiran GPS / Mesin hari ini
        $todayAttendanceLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->orderBy('scanned_at')
            ->get();

        $myCheckInToday = $todayAttendanceLogs->first();
        $myCheckOutToday = $todayAttendanceLogs->count() > 1 ? $todayAttendanceLogs->last() : null;

        // 2. Jadwal Mengajar Guru Khusus Hari Ini
        $todayTeachingSchedules = LessonSchedule::with(['classroom', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('academic_year', $operationalAcademicYear)
            ->where('semester', $operationalSemester)
            ->orderBy('start_period')
            ->get();

        // Status sesi KBM yang sudah tersimpan
        $mySavedScheduleIds = collect();
        if ($todayTeachingSchedules->isNotEmpty()) {
            $mySavedSessions = SubjectAttendanceSession::whereDate('attendance_date', $todayStr)
                ->where(function ($q) use ($user, $todayTeachingSchedules) {
                    $q->where('teacher_id', $user->id)
                        ->orWhere('scheduled_teacher_id', $user->id)
                        ->orWhereIn('lesson_schedule_id', $todayTeachingSchedules->pluck('id'));
                })
                ->get();

            $mySavedScheduleIds = $mySavedSessions->pluck('lesson_schedule_id')->filter()->unique();
            foreach ($todayTeachingSchedules as $sched) {
                if ($mySavedSessions->contains(fn ($s) => $s->classroom_id == $sched->classroom_id && $s->start_period == $sched->start_period)) {
                    $mySavedScheduleIds->push($sched->id);
                }
            }
            $mySavedScheduleIds = $mySavedScheduleIds->unique();
        }

        $formattedTeachingSchedules = $todayTeachingSchedules->map(function ($schedule) use ($mySavedScheduleIds) {
            $isSaved = $mySavedScheduleIds->contains($schedule->id);

            return [
                'id' => $schedule->id,
                'subject_name' => $schedule->subject?->name ?? 'Mata Pelajaran',
                'subject_code' => $schedule->subject?->code ?? '-',
                'classroom_name' => $schedule->classroom?->name ?? 'Kelas',
                'start_period' => $schedule->start_period,
                'end_period' => $schedule->end_period,
                'period_text' => "Jam ke-{$schedule->start_period} s/d {$schedule->end_period}",
                'time_range' => ($schedule->start_time && $schedule->end_time)
                    ? substr($schedule->start_time, 0, 5).' - '.substr($schedule->end_time, 0, 5)
                    : null,
                'is_completed' => $isSaved,
            ];
        });

        // 3. Agenda Sekolah Hari Ini
        $allTodayEvents = CurriculumEvent::with('users')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->where(function ($sub) use ($operationalAcademicYear) {
                $sub->where('academic_year', $operationalAcademicYear)
                    ->orWhereNull('academic_year');
            })
            ->orderBy('is_holiday', 'desc')
            ->orderBy('start_date')
            ->get();

        if ($user->isPrincipal() || $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->isPiketOfficerToday()) {
            $todaySchoolAgendas = $allTodayEvents;
        } else {
            $todaySchoolAgendas = $allTodayEvents->filter(function ($ev) use ($user, $todayTeachingSchedules) {
                if (empty($ev->target_audience) || $ev->target_audience === 'semua') {
                    return true;
                }

                return $ev->affectsTeacher($user, $todayTeachingSchedules);
            })->values();

            if ($todaySchoolAgendas->isEmpty() && $allTodayEvents->isNotEmpty()) {
                $todaySchoolAgendas = $allTodayEvents;
            }
        }

        $formattedAgendas = $todaySchoolAgendas->map(function ($ev) {
            return [
                'id' => $ev->id,
                'title' => $ev->title,
                'category' => $ev->category,
                'is_holiday' => (bool) $ev->is_holiday,
                'start_date' => $ev->start_date?->format('Y-m-d'),
                'end_date' => $ev->end_date?->format('Y-m-d'),
                'description' => $ev->description,
                'target_audience' => $ev->target_audience ?? 'semua',
            ];
        });

        // 4. Status Piket
        $canAccessPiket = $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum())
            || $user->canAccessPiket($operationalAcademicYear);
        $isManagement = $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum());

        $isPiketToday = $user->isPiketOfficerToday();
        $todayPiketSchedule = PiketSchedule::where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('academic_year', $operationalAcademicYear)
            ->where('is_active', true)
            ->first();

        $myWeeklyPiketSchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('academic_year', $operationalAcademicYear)
            ->where('is_active', true)
            ->get();

        // 5. Status Rombel Wali Kelas
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->where(function ($sub) use ($operationalAcademicYear, $operationalAyModel) {
                if ($operationalAyModel) {
                    $sub->where('classrooms.academic_year_id', $operationalAyModel->id);
                }
                $sub->orWhere('classrooms.academic_year', $operationalAcademicYear)
                    ->orWhereNull('classrooms.academic_year');
            })
            ->first();

        // 6. Izin / Cuti Hari Ini
        $myGtkPermitToday = GtkPermit::approved()
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->first();

        // 7. Notifikasi
        $userNotifications = NotificationService::getUserNotifications($user);

        return response()->json([
            'success' => true,
            'data' => [
                'date_info' => [
                    'date' => $todayStr,
                    'formatted_date' => Carbon::now()->translatedFormat('l, d F Y'),
                    'day_name' => $todayDay,
                ],
                'school_info' => [
                    'name' => $setting->school_name,
                    'academic_year' => $operationalAcademicYear,
                    'semester' => $operationalSemester,
                    'logo_url' => $setting->logo ? asset('storage/'.$setting->logo) : asset('assets/images/logo-default.png'),
                    'geotag_enabled' => (bool) $setting->geotag_attendance_enabled,
                ],
                'attendance_today' => [
                    'has_check_in' => (bool) $myCheckInToday,
                    'check_in_time' => $myCheckInToday ? $myCheckInToday->scanned_at->format('H:i') : null,
                    'has_check_out' => (bool) $myCheckOutToday,
                    'check_out_time' => $myCheckOutToday ? $myCheckOutToday->scanned_at->format('H:i') : null,
                    'status_label' => $myCheckInToday ? ($myCheckOutToday ? 'Lengkap (Masuk & Pulang)' : 'Sudah Masuk') : 'Belum Presensi',
                ],
                'teaching_today' => [
                    'total_sessions' => $formattedTeachingSchedules->count(),
                    'completed_sessions' => $formattedTeachingSchedules->where('is_completed', true)->count(),
                    'schedules' => $formattedTeachingSchedules->values(),
                ],
                'agendas_today' => $formattedAgendas->values(),
                'piket_info' => [
                    'can_access' => $canAccessPiket,
                    'is_management' => $isManagement,
                    'is_piket_today' => $isPiketToday,
                    'today_schedule' => $todayPiketSchedule ? [
                        'day' => $todayPiketSchedule->day,
                        'notes' => $todayPiketSchedule->notes,
                    ] : null,
                    'weekly_days' => $myWeeklyPiketSchedules->pluck('day')->unique()->values(),
                ],
                'homeroom_info' => $myHomeroomClassroom ? [
                    'id' => $myHomeroomClassroom->id,
                    'name' => $myHomeroomClassroom->name,
                    'grade_level' => $myHomeroomClassroom->grade_level ?? null,
                ] : null,
                'permit_today' => $myGtkPermitToday ? [
                    'type' => $myGtkPermitToday->permit_type,
                    'reason' => $myGtkPermitToday->reason,
                    'start_date' => $myGtkPermitToday->start_date?->format('Y-m-d'),
                    'end_date' => $myGtkPermitToday->end_date?->format('Y-m-d'),
                ] : null,
                'notifications_summary' => [
                    'total_unread' => count($userNotifications['items'] ?? []),
                    'items' => array_slice($userNotifications['items'] ?? [], 0, 5),
                ],
            ],
        ]);
    }
}
