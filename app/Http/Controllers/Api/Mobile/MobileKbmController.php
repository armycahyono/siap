<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CurriculumEvent;
use App\Models\LessonSchedule;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceSession;
use App\Services\AcademicYearContext;
use App\Services\DailyKbmSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileKbmController extends Controller
{
    /**
     * Get KBM teaching schedules, attendance sessions, and journal data for SIAP Mobile.
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

        $dateParam = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateParam);
        $dateStr = $date->toDateString();

        $dayName = match ($date->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        $setting = SchoolSetting::getSetting();

        if ($request->filled('academic_year')) {
            try {
                $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($request->query('academic_year'));
                $semester = $request->query('semester', $setting->semester ?? 'Ganjil');
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
                $semester = $setting->semester ?? 'Ganjil';
            }
        } elseif ($date->isToday()) {
            try {
                $academicYear = app(AcademicYearContext::class)->getViewingYear();
                $semester = session('active_semester', $setting->semester ?? 'Ganjil');
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
                $semester = $setting->semester ?? 'Ganjil';
            }
        } else {
            $resolved = app(DailyKbmSnapshotService::class)->resolveAcademicYearForDate($date);
            $academicYear = $resolved['academic_year'];
            $semester = $request->query('semester', $resolved['semester'] ?? $setting->semester ?? 'Ganjil');
        }

        // 1. Evaluasi Hari KBM & Kalender Kurikulum
        $todayEvents = CurriculumEvent::with('users')
            ->where('academic_year', $academicYear)
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('start_date', '<=', $dateStr)
                    ->where(function ($sub) use ($dateStr) {
                        $sub->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $dateStr);
                    });
            })
            ->get();

        $isWeekend = $date->isWeekend() || $date->dayOfWeekIso > 5;
        $holidayEvent = $todayEvents->first(function ($ev) {
            return $ev->is_holiday
                || in_array($ev->event_type, ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'libur_khusus', 'libur'])
                || $ev->teacher_impact === 'diliburkan_penuh'
                || $ev->learning_impact === 'libur_khusus';
        });

        $isHoliday = ! is_null($holidayEvent);
        $isKbmDay = ! $isWeekend && ! $isHoliday;

        // 2. Jadwal Mengajar Guru Login pada Hari Ini
        $mySchedules = LessonSchedule::with(['classroom.gradeLevel', 'subject', 'teacher'])
            ->where('teacher_id', $user->id)
            ->where('day', $dayName)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->when($semester, fn ($q) => $q->where('semester', $semester))
            ->orderBy('start_period')
            ->get();

        // 3. Sesi Presensi KBM yang Sudah Tersimpan pada Tanggal Ini
        $myScheduleIds = $mySchedules->pluck('id')->filter()->all();

        $savedSessionsCollection = SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher', 'teacher'])
            ->whereDate('attendance_date', $dateStr)
            ->where(function ($q) use ($user, $myScheduleIds) {
                $q->where('teacher_id', $user->id)
                    ->orWhere('scheduled_teacher_id', $user->id);
                if (! empty($myScheduleIds)) {
                    $q->orWhereIn('lesson_schedule_id', $myScheduleIds);
                }
            })
            ->get();

        $savedSessions = $savedSessionsCollection->keyBy(function ($session) {
            return $session->lesson_schedule_id ?? "manual_{$session->id}";
        });

        $savedSessionsBySlot = $savedSessionsCollection->keyBy(function ($session) {
            return "{$session->classroom_id}_{$session->start_period}";
        });

        // 4. Format Jadwal Mengajar Guru
        $formattedMySchedules = $mySchedules->map(function ($schedule) use ($savedSessions, $savedSessionsBySlot) {
            $session = $savedSessions->get($schedule->id)
                ?? $savedSessionsBySlot->get("{$schedule->classroom_id}_{$schedule->start_period}");

            $isAttended = (bool) $session;
            $recordsCount = $session ? $session->records->count() : 0;
            $presentCount = $session ? $session->records->where('status', 'h')->count() : 0;

            return [
                'id' => $schedule->id,
                'subject_name' => $schedule->subject?->name ?? 'Mata Pelajaran',
                'subject_code' => $schedule->subject?->code ?? '-',
                'classroom_id' => $schedule->classroom_id,
                'classroom_name' => $schedule->classroom?->name ?? 'Kelas',
                'start_period' => $schedule->start_period,
                'end_period' => $schedule->end_period,
                'period_text' => "Jam ke-{$schedule->start_period} s/d {$schedule->end_period}",
                'time_range' => ($schedule->start_time && $schedule->end_time)
                    ? substr($schedule->start_time, 0, 5).' - '.substr($schedule->end_time, 0, 5)
                    : null,
                'is_attended' => $isAttended,
                'session_id' => $session?->id,
                'material_taught' => $session?->notes ?? $session?->material_taught ?? null,
                'attendance_summary' => $isAttended ? [
                    'total' => $recordsCount,
                    'present' => $presentCount,
                    'sakit' => $session->records->where('status', 's')->count(),
                    'izin' => $session->records->where('status', 'i')->count(),
                    'alfa' => $session->records->where('status', 'a')->count(),
                ] : null,
                'fill_url' => route('attendance.subject.take', ['schedule' => $schedule->id]),
            ];
        });

        // 5. Sesi Tukar Jam / Guru Pengganti
        $swapSessions = SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher', 'teacher'])
            ->where('teacher_id', $user->id)
            ->whereDate('attendance_date', $dateStr)
            ->where('is_swap', true)
            ->get()
            ->map(function ($session) {
                return [
                    'session_id' => $session->id,
                    'subject_name' => $session->subject?->name ?? 'Mapel Pengganti',
                    'classroom_name' => $session->classroom?->name ?? 'Kelas',
                    'original_teacher_name' => $session->scheduledTeacher?->name ?? 'Guru Asli',
                    'start_period' => $session->start_period,
                    'end_period' => $session->end_period,
                    'period_text' => "Jam ke-{$session->start_period} s/d {$session->end_period}",
                ];
            });

        // 6. Live Monitoring Terpadu & Layanan BK untuk Halaman Menu KBM
        $operationalAyModel = AcademicYear::where('name', $academicYear)->first();
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->where(function ($sub) use ($academicYear, $operationalAyModel) {
                if ($operationalAyModel) {
                    $sub->where('classrooms.academic_year_id', $operationalAyModel->id);
                }
                $sub->orWhere('classrooms.academic_year', $academicYear)
                    ->orWhereNull('classrooms.academic_year');
            })
            ->first();

        $tasksController = app(MobileTasksController::class);
        $liveMonitoring = $tasksController->buildLiveMonitoringData($user, $date, $academicYear, $myHomeroomClassroom);
        $bkData = $tasksController->buildBkSummaryData($user, $academicYear);

        return response()->json([
            'success' => true,
            'data' => [
                'date_info' => [
                    'current' => $dateStr,
                    'formatted_date' => $date->translatedFormat('l, d F Y'),
                    'day_name' => $dayName,
                    'is_today' => $date->isToday(),
                    'is_kbm_day' => $isKbmDay,
                    'is_weekend' => $isWeekend,
                    'is_holiday' => $isHoliday,
                    'holiday_title' => $holidayEvent?->title ?? null,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                ],
                'my_schedules' => $formattedMySchedules->values(),
                'swap_sessions' => $swapSessions->values(),
                'total_sessions' => $mySchedules->count(),
                'attended_sessions_count' => $formattedMySchedules->where('is_attended', true)->count(),
                'live_monitoring' => $liveMonitoring,
                'bk' => $bkData,
            ],
        ]);
    }
}
