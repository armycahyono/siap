<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\LessonSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\ActivityLogger;
use App\Services\DailyKbmSnapshotService;
use App\Services\LiveMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MobileKbmController extends Controller
{
    public function __construct(
        protected LiveMonitoringService $liveMonitoringService
    ) {}

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
        $formattedMySchedules = $mySchedules->map(function ($schedule) use ($savedSessions, $savedSessionsBySlot, $dateStr) {
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
                'fill_url' => route('attendance.subject.take', ['schedule_id' => $schedule->id, 'date' => $dateStr]),
            ];
        });

        // 5. Sesi Tukar Jam / Guru Pengganti / Inval
        $swapSessions = SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher', 'teacher'])
            ->where('teacher_id', $user->id)
            ->whereDate('attendance_date', $dateStr)
            ->where('is_swap', true)
            ->get()
            ->map(function ($session) use ($dateStr) {
                $recordsCount = $session->records->count();
                $presentCount = $session->records->where('status', 'h')->count();

                return [
                    'session_id' => $session->id,
                    'lesson_schedule_id' => $session->lesson_schedule_id,
                    'classroom_id' => $session->classroom_id,
                    'subject_id' => $session->subject_id,
                    'subject_name' => $session->subject?->name ?? 'Mapel Pengganti',
                    'classroom_name' => $session->classroom?->name ?? 'Kelas',
                    'original_teacher_name' => $session->scheduledTeacher?->name ?? 'Guru Asli',
                    'start_period' => $session->start_period,
                    'end_period' => $session->end_period,
                    'period_text' => "Jam ke-{$session->start_period} s/d {$session->end_period}",
                    'swap_type' => $session->swap_type ?? 'inval',
                    'topic' => $session->topic,
                    'notes' => $session->notes,
                    'is_attended' => true,
                    'attendance_summary' => [
                        'total' => $recordsCount,
                        'present' => $presentCount,
                        'sakit' => $session->records->where('status', 's')->count(),
                        'izin' => $session->records->where('status', 'i')->count(),
                        'alfa' => $session->records->where('status', 'a')->count(),
                    ],
                    'fill_url' => $session->lesson_schedule_id
                        ? route('attendance.subject.take', ['schedule_id' => $session->lesson_schedule_id, 'date' => $dateStr])
                        : null,
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
        $isManagementKbm = $user->isPrincipal() || $user->isSuperAdmin() || $user->isVicePrincipal() || $user->isWakaKurikulum() || $user->isWakaKesiswaan() || $user->isWakaSarpras() || $user->isWakaHumas() || $user->canManageDiscipline($academicYear) || $user->canOperateBk($academicYear);
        $liveMonitoring = $isManagementKbm
            ? $this->liveMonitoringService->buildLiveMonitoringData($user, $date, $academicYear, null)
            : null;
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

    /**
     * Get students and current attendance/journal state for a subject schedule session.
     */
    public function getTakeData(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $scheduleId = $request->query('schedule_id');
        if (! $scheduleId) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter schedule_id diperlukan.',
            ], 400);
        }

        $schedule = LessonSchedule::with(['classroom.gradeLevel', 'classroom.homeroomTeacher', 'subject', 'teacher'])->find($scheduleId);
        if (! $schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal pelajaran tidak ditemukan.',
            ], 404);
        }

        $classroom = $schedule->classroom;
        $subject = $schedule->subject;

        if (! $classroom || ! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'Data kelas atau mata pelajaran tidak valid.',
            ], 422);
        }

        $dateParam = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateParam);
        $dateStr = $date->toDateString();

        // Cek wewenang
        $isOwnerTeacher = ($schedule->teacher_id === $user->id);
        $isSubstituteTeacher = $user->hasRole('guru') || $user->hasTeachingDuty() || $user->isTeacher();
        $isManagement = $user->isSuperAdmin() || $user->hasRole('admin') || $user->isPrincipal() || $user->isWakaKurikulum() || $user->isVicePrincipal();
        $isPiket = $user->canOperatePiket();

        if (! $isOwnerTeacher && ! $isSubstituteTeacher && ! $isManagement && ! $isPiket) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengabsen jadwal ini.',
            ], 403);
        }

        // Cek jendela waktu
        $windowStatus = $schedule->getAttendanceWindowStatus($date, 15);
        $canSubmit = $windowStatus['is_open'] || $isManagement || $isPiket;

        // Tentukan tahun ajaran target
        if (! empty($schedule->academic_year)) {
            $targetAcademicYear = $schedule->academic_year;
        } elseif ($request->filled('academic_year')) {
            $targetAcademicYear = $request->query('academic_year');
        } elseif ($date->isToday()) {
            try {
                $targetAcademicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $targetAcademicYear = SchoolSetting::getMasterYear();
            }
        } else {
            $targetAcademicYear = app(DailyKbmSnapshotService::class)->resolveAcademicYearForDate($date)['academic_year'];
        }

        // Ambil siswa terdaftar di kelas
        $ayRecord = AcademicYear::where('name', $targetAcademicYear)->first();
        $studentIdsQuery = StudentClassroom::where('classroom_id', $classroom->id);
        if ($ayRecord) {
            $studentIdsQuery->where(function ($q) use ($ayRecord, $targetAcademicYear) {
                $q->where('academic_year_id', $ayRecord->id)
                    ->orWhere('academic_year_name', $targetAcademicYear);
            });
        } else {
            $studentIdsQuery->where('academic_year_name', $targetAcademicYear);
        }
        $enrolledStudentIds = $studentIdsQuery->pluck('student_id')->filter()->unique()->toArray();

        if (! empty($enrolledStudentIds)) {
            $students = Student::whereIn('id', $enrolledStudentIds)
                ->orderBy('name')
                ->get();
        } else {
            $students = Student::where('classroom_id', $classroom->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        // Ambil sesi yang sudah ada
        $session = SubjectAttendanceSession::with('records')
            ->where('classroom_id', $classroom->id)
            ->where('subject_id', $subject->id)
            ->whereDate('attendance_date', $dateStr)
            ->where('start_period', $schedule->start_period)
            ->first();

        $recordsMap = $session ? $session->records->keyBy('student_id') : collect();

        // Ambil perizinan piket hari ini
        $permits = PiketStudentPermit::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('permit_date', $dateStr)
                    ->orWhere(function ($sub) use ($dateStr) {
                        $sub->whereDate('permit_date', '<=', $dateStr)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $dateStr);
                    });
            })
            ->get()
            ->keyBy('student_id');

        $studentsData = $students->map(function ($st) use ($recordsMap, $permits) {
            $rec = $recordsMap->get($st->id);
            $permit = $permits->get($st->id);

            $status = 'h';
            $notes = null;
            $permitNotice = null;

            if ($rec) {
                $status = $rec->status;
                $notes = $rec->notes;
            } elseif ($permit) {
                $pType = strtolower($permit->permit_type ?? '');
                if (str_contains($pType, 'sakit')) {
                    $status = 's';
                    $permitNotice = 'Piket: Sakit';
                } elseif (str_contains($pType, 'izin')) {
                    $status = 'i';
                    $permitNotice = 'Piket: Izin';
                } elseif (str_contains($pType, 'dispen')) {
                    $status = 'd';
                    $permitNotice = 'Piket: Dispen';
                } elseif (str_contains($pType, 'terlambat')) {
                    $status = 't';
                    $permitNotice = 'Piket: Telat';
                } else {
                    $permitNotice = 'Piket: '.($permit->reason ?? 'Izin');
                }
                $notes = $permit->reason ?? null;
            }

            return [
                'id' => $st->id,
                'name' => $st->name,
                'nis' => $st->nis ?? '-',
                'gender' => $st->gender ?? 'L',
                'status' => $status,
                'notes' => $notes,
                'permit_notice' => $permitNotice,
                'parent_phone' => ($st->primary_parent_phone && $st->primary_parent_phone !== '-') ? $st->primary_parent_phone : ($st->phone_parent ?: ($st->father_phone ?: ($st->mother_phone ?: null))),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => [
                    'id' => $schedule->id,
                    'classroom_id' => $classroom->id,
                    'classroom_name' => $classroom->name,
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'teacher_id' => $schedule->teacher_id,
                    'teacher_name' => $schedule->teacher?->name ?? 'Guru Pengampu',
                    'start_period' => $schedule->start_period,
                    'end_period' => $schedule->end_period,
                    'period_text' => "Jam ke-{$schedule->start_period} s/d {$schedule->end_period}",
                    'time_range' => ($schedule->start_time && $schedule->end_time)
                        ? substr($schedule->start_time, 0, 5).' - '.substr($schedule->end_time, 0, 5)
                        : null,
                ],
                'session' => [
                    'id' => $session?->id,
                    'is_attended' => (bool) $session,
                    'topic' => $session?->topic ?? '',
                    'notes' => $session?->notes ?? '',
                    'swap_type' => $session?->swap_type ?? (! $isOwnerTeacher ? 'inval' : null),
                    'swap_notes' => $session?->swap_notes ?? null,
                    'is_swap' => (bool) ($session?->is_swap || (! $isOwnerTeacher)),
                ],
                'window' => [
                    'can_submit' => $canSubmit,
                    'is_open' => $windowStatus['is_open'],
                    'open_time' => $windowStatus['open_time'],
                    'message' => $windowStatus['message'],
                ],
                'date' => $dateStr,
                'formatted_date' => $date->translatedFormat('l, d F Y'),
                'academic_year' => $targetAcademicYear,
                'students' => $studentsData->values(),
                'summary' => [
                    'total' => $studentsData->count(),
                    'hadir' => $studentsData->where('status', 'h')->count(),
                    'sakit' => $studentsData->where('status', 's')->count(),
                    'izin' => $studentsData->where('status', 'i')->count(),
                    'alfa' => $studentsData->where('status', 'a')->count(),
                    'dispen' => $studentsData->where('status', 'd')->count(),
                    'telat' => $studentsData->where('status', 't')->count(),
                ],
            ],
        ]);
    }

    /**
     * Store or update subject attendance records and teaching journal.
     */
    public function storeAttendance(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'schedule_id' => ['nullable', 'exists:lesson_schedules,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'start_period' => ['required', 'integer', 'min:1', 'max:15'],
            'end_period' => ['required', 'integer', 'min:1', 'max:15'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_swap' => ['nullable', 'boolean'],
            'swap_type' => ['nullable', 'string', 'in:tukar_jam,guru_pengganti,inval,mandiri'],
            'swap_notes' => ['nullable', 'string', 'max:500'],
            'scheduled_teacher_id' => ['nullable', 'exists:users,id'],
            'academic_year' => ['nullable', 'string', 'exists:academic_years,name'],
            'semester' => ['nullable', 'string', 'in:ganjil,genap,Ganjil,Genap'],
            'attendance' => ['required', 'array'],
            'attendance.*.student_id' => ['required', 'exists:students,id'],
            'attendance.*.status' => ['required', 'in:h,s,i,a,d,t'],
            'attendance.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = SchoolSetting::getSetting();
        $date = Carbon::parse($validated['date']);

        $lessonScheduleId = $validated['schedule_id'] ?? null;
        $schedule = $lessonScheduleId ? LessonSchedule::find($lessonScheduleId) : null;

        if (! $schedule) {
            $dayName = match ($date->dayOfWeekIso) {
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                default => 'Minggu',
            };
            $schedule = LessonSchedule::where('classroom_id', $validated['classroom_id'])
                ->where('day', $dayName)
                ->where('start_period', $validated['start_period'])
                ->where('subject_id', $validated['subject_id'])
                ->first();

            if ($schedule) {
                $lessonScheduleId = $schedule->id;
            }
        }

        $existingSession = SubjectAttendanceSession::where('classroom_id', $validated['classroom_id'])
            ->where('subject_id', $validated['subject_id'])
            ->whereDate('attendance_date', $date->toDateString())
            ->where('start_period', $validated['start_period'])
            ->first();

        // Validasi Jendela Waktu
        $isManagement = $user->isSuperAdmin() || $user->hasRole('admin') || $user->isPrincipal() || $user->isWakaKurikulum() || $user->isVicePrincipal() || $user->canOperatePiket();
        if (! $existingSession && ! $isManagement) {
            $windowStatus = $schedule
                ? $schedule->getAttendanceWindowStatus($date, 15)
                : LessonSchedule::checkAttendanceWindow(
                    $validated['start_period'],
                    null,
                    match ($date->dayOfWeekIso) {
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        6 => 'Sabtu',
                        default => 'Minggu',
                    },
                    $date,
                    15
                );

            if (! $windowStatus['is_open']) {
                return response()->json([
                    'success' => false,
                    'message' => $windowStatus['message'] ?? 'Presensi kelas belum dapat dibuka.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            if (! $lessonScheduleId && $existingSession?->lesson_schedule_id) {
                $lessonScheduleId = $existingSession->lesson_schedule_id;
            }

            if (! empty($validated['academic_year'])) {
                $targetAcademicYear = $validated['academic_year'];
                $targetSemester = ! empty($validated['semester']) ? strtolower($validated['semester']) : null;
            } elseif ($existingSession && ! empty($existingSession->academic_year)) {
                $targetAcademicYear = $existingSession->academic_year;
                $targetSemester = $existingSession->semester;
            } elseif ($date->isToday()) {
                try {
                    $opYear = app(AcademicYearContext::class)->getOperationalYear();
                    $targetAcademicYear = $opYear->name;
                    $targetSemester = ! empty($opYear->semester) ? strtolower($opYear->semester) : null;
                } catch (\Throwable) {
                    $targetAcademicYear = SchoolSetting::getMasterYear();
                }
            } elseif ($schedule && ! empty($schedule->academic_year)) {
                $targetAcademicYear = $schedule->academic_year;
                $targetSemester = ! empty($schedule->semester) ? strtolower($schedule->semester) : null;
            } else {
                $resolved = app(DailyKbmSnapshotService::class)->resolveAcademicYearForDate($date);
                $targetAcademicYear = $resolved['academic_year'];
                $targetSemester = ! empty($resolved['semester']) ? strtolower($resolved['semester']) : null;
            }

            if (! $targetSemester) {
                $targetSemester = strtolower((string) ($setting->semester ?? 'ganjil'));
            }

            $scheduledTeacherId = $validated['scheduled_teacher_id'] ?? $schedule?->teacher_id ?? $existingSession?->scheduled_teacher_id ?? $user->id;
            $isSwap = ! empty($validated['is_swap']) || ! empty($validated['swap_type']) || ($scheduledTeacherId !== $user->id);
            $swapType = $validated['swap_type'] ?? ($isSwap ? 'guru_pengganti' : null);
            $swapNotes = $validated['swap_notes'] ?? null;

            if ($existingSession) {
                $existingSession->update([
                    'lesson_schedule_id' => $lessonScheduleId,
                    'teacher_id' => $user->id,
                    'scheduled_teacher_id' => $scheduledTeacherId,
                    'is_swap' => $isSwap,
                    'swap_type' => $swapType,
                    'swap_notes' => $swapNotes,
                    'end_period' => $validated['end_period'],
                    'academic_year' => $targetAcademicYear,
                    'semester' => $targetSemester,
                    'topic' => $validated['topic'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
                $session = $existingSession;
            } else {
                $session = SubjectAttendanceSession::create([
                    'classroom_id' => $validated['classroom_id'],
                    'subject_id' => $validated['subject_id'],
                    'attendance_date' => $date->toDateString(),
                    'start_period' => $validated['start_period'],
                    'end_period' => $validated['end_period'],
                    'lesson_schedule_id' => $lessonScheduleId,
                    'teacher_id' => $user->id,
                    'scheduled_teacher_id' => $scheduledTeacherId,
                    'is_swap' => $isSwap,
                    'swap_type' => $swapType,
                    'swap_notes' => $swapNotes,
                    'academic_year' => $targetAcademicYear,
                    'semester' => $targetSemester,
                    'topic' => $validated['topic'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            foreach ($validated['attendance'] as $item) {
                SubjectAttendanceRecord::updateOrCreate(
                    [
                        'subject_attendance_session_id' => $session->id,
                        'student_id' => $item['student_id'],
                    ],
                    [
                        'status' => $item['status'],
                        'notes' => $item['notes'] ?? null,
                    ]
                );
            }

            DB::commit();

            try {
                $clsName = $session->classroom?->name ?? 'Kelas';
                $sbjName = $session->subject?->name ?? 'Mapel';
                ActivityLogger::log(
                    action: 'kbm_attendance',
                    description: "Melakukan presensi KBM {$clsName} mata pelajaran {$sbjName} (Jam ke-{$validated['start_period']}-{$validated['end_period']}) via SIAP Mobile.",
                    subject: $session,
                    properties: [
                        'classroom' => $clsName,
                        'subject' => $sbjName,
                        'start_period' => $validated['start_period'],
                        'end_period' => $validated['end_period'],
                        'date' => $date->toDateString(),
                        'client' => 'mobile_app',
                    ],
                    user: $user
                );
            } catch (\Throwable) {
            }

            try {
                app(DailyKbmSnapshotService::class)->syncCompletedSessions($date);
            } catch (\Throwable) {
            }

            return response()->json([
                'success' => true,
                'message' => 'Presensi kelas dan jurnal KBM berhasil disimpan.',
                'data' => [
                    'session_id' => $session->id,
                    'is_attended' => true,
                    'classroom_name' => $session->classroom?->name,
                    'subject_name' => $session->subject?->name,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan presensi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get historical attendance sessions for mobile teacher.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $classroomId = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');
        $startDate = $request->query('start_date', now()->subMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $query = SubjectAttendanceSession::with(['classroom', 'subject', 'teacher', 'scheduledTeacher', 'records'])
            ->where(function ($q) use ($user) {
                $q->where('teacher_id', $user->id)
                    ->orWhere('scheduled_teacher_id', $user->id);
            })
            ->whereBetween('attendance_date', [$startDate, $endDate]);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $sessions = $query->latest('attendance_date')
            ->latest('start_period')
            ->paginate(15);

        $classrooms = Classroom::orderBy('name')->get(['id', 'name']);
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $formattedSessions = $sessions->map(function ($sess) {
            $statusCounts = [
                'h' => $sess->records->where('status', 'h')->count(),
                't' => $sess->records->where('status', 't')->count(),
                's' => $sess->records->where('status', 's')->count(),
                'i' => $sess->records->where('status', 'i')->count(),
                'a' => $sess->records->where('status', 'a')->count(),
                'd' => $sess->records->where('status', 'd')->count(),
            ];

            return [
                'id' => $sess->id,
                'date' => $sess->attendance_date ? $sess->attendance_date->format('Y-m-d') : null,
                'formatted_date' => $sess->attendance_date ? $sess->attendance_date->translatedFormat('d M Y') : null,
                'start_period' => $sess->start_period,
                'end_period' => $sess->end_period,
                'classroom_name' => $sess->classroom?->name,
                'subject_name' => $sess->subject?->name,
                'teacher_name' => $sess->teacher?->name,
                'scheduled_teacher_name' => $sess->scheduledTeacher?->name,
                'is_swap' => (bool) $sess->is_swap,
                'swap_type' => $sess->swap_type,
                'topic' => $sess->topic,
                'notes' => $sess->notes,
                'total_students' => $sess->records->count(),
                'status_counts' => $statusCounts,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $formattedSessions,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'total' => $sessions->total(),
                ],
                'filter_options' => [
                    'classrooms' => $classrooms,
                    'subjects' => $subjects,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Get detail of a specific past attendance session.
     */
    public function getHistoryDetail(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $session = SubjectAttendanceSession::with([
            'classroom',
            'subject',
            'teacher',
            'scheduledTeacher',
            'records.student',
        ])->find($id);

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Sesi KBM tidak ditemukan.'], 404);
        }

        $records = $session->records->map(function ($rec) {
            return [
                'id' => $rec->id,
                'student_id' => $rec->student_id,
                'student_name' => $rec->student?->name,
                'student_nisn' => $rec->student?->nisn,
                'student_nis' => $rec->student?->nis,
                'gender' => $rec->student?->gender,
                'status' => strtolower((string) $rec->status),
                'notes' => $rec->notes,
                'parent_phone' => ($rec->student?->primary_parent_phone && $rec->student?->primary_parent_phone !== '-') ? $rec->student?->primary_parent_phone : ($rec->student?->phone_parent ?: ($rec->student?->father_phone ?: ($rec->student?->mother_phone ?: null))),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'session' => [
                    'id' => $session->id,
                    'date' => $session->attendance_date ? $session->attendance_date->format('Y-m-d') : null,
                    'formatted_date' => $session->attendance_date ? $session->attendance_date->translatedFormat('l, d F Y') : null,
                    'start_period' => $session->start_period,
                    'end_period' => $session->end_period,
                    'start_time' => $session->start_time,
                    'end_time' => $session->end_time,
                    'classroom_name' => $session->classroom?->name,
                    'subject_name' => $session->subject?->name,
                    'teacher_name' => $session->teacher?->name,
                    'scheduled_teacher_name' => $session->scheduledTeacher?->name,
                    'is_swap' => (bool) $session->is_swap,
                    'swap_type' => $session->swap_type,
                    'swap_notes' => $session->swap_notes,
                    'topic' => $session->topic,
                    'notes' => $session->notes,
                    'total_students' => $records->count(),
                ],
                'records' => $records,
            ],
        ]);
    }

    /**
     * Get school lesson schedule browser for mobile.
     */
    public function getSchoolSchedule(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            default => 'Senin',
        };

        $selectedDay = $request->query('day', $todayDay);
        if (! in_array($selectedDay, $days, true)) {
            $selectedDay = 'Senin';
        }

        $classroomId = $request->query('classroom_id');
        $teacherId = $request->query('teacher_id');
        $search = trim((string) $request->query('search', ''));

        $query = LessonSchedule::with(['classroom', 'subject', 'teacher'])
            ->where('academic_year', $academicYear)
            ->where('day', $selectedDay);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('classroom', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $schedules = $query->orderBy('start_period')
            ->orderBy('classroom_id')
            ->get();

        $periodTimes = LessonSchedule::periodTimes($selectedDay);
        $currentTime = now()->format('H:i');
        $activePeriod = null;

        $todayStr = now()->toDateString();
        $isToday = ($todayDay === $selectedDay);

        if ($isToday) {
            foreach ($periodTimes as $p => $times) {
                if ($currentTime >= $times['start'] && $currentTime <= $times['end']) {
                    $activePeriod = (int) $p;
                    break;
                }
            }
        }

        $scheduleIds = $schedules->pluck('id')->filter()->all();
        $todaySessions = collect();
        $todaySessionsBySlot = collect();

        if ($isToday && ! empty($scheduleIds)) {
            $sessions = SubjectAttendanceSession::with(['teacher'])
                ->whereDate('attendance_date', $todayStr)
                ->where(function ($q) use ($scheduleIds) {
                    $q->whereIn('lesson_schedule_id', $scheduleIds);
                })
                ->get();

            $todaySessions = $sessions->keyBy('lesson_schedule_id');
            $todaySessionsBySlot = $sessions->keyBy(fn ($session) => "{$session->classroom_id}_{$session->start_period}");
        }

        $formattedSchedules = $schedules->map(function ($s) use ($activePeriod, $isToday, $todaySessions, $todaySessionsBySlot) {
            $isActive = $activePeriod && ($activePeriod >= $s->start_period && $activePeriod <= $s->end_period);

            $session = null;
            if ($isToday) {
                $session = $todaySessions->get($s->id)
                    ?? $todaySessionsBySlot->get("{$s->classroom_id}_{$s->start_period}");
            }

            $isAttended = (bool) $session;
            $attendedTeacherName = $session?->teacher?->formatted_short_name ?? $session?->teacher?->name;

            return [
                'id' => $s->id,
                'classroom_id' => $s->classroom_id,
                'classroom_name' => $s->classroom?->name,
                'subject_id' => $s->subject_id,
                'subject_name' => $s->subject?->name,
                'teacher_id' => $s->teacher_id,
                'teacher_name' => $s->teacher?->formatted_short_name ?? $s->teacher?->name,
                'start_period' => $s->start_period,
                'end_period' => $s->end_period,
                'start_time' => $s->start_time ? substr((string) $s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr((string) $s->end_time, 0, 5) : null,
                'is_active_now' => (bool) $isActive,
                'is_today' => (bool) $isToday,
                'is_attended' => $isAttended,
                'attended_by' => $attendedTeacherName,
                'attended_by_id' => $session?->teacher_id,
                'is_swap' => (bool) $session?->is_swap,
                'session_id' => $session?->id,
            ];
        });

        $classrooms = Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $teachers = User::whereHas('lessonSchedules', fn ($q) => $q->where('academic_year', $academicYear))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'academic_year' => $academicYear,
                'days' => $days,
                'selected_day' => $selectedDay,
                'today_day' => $todayDay,
                'active_period' => $activePeriod,
                'schedules' => $formattedSchedules,
                'filter_options' => [
                    'classrooms' => $classrooms,
                    'teachers' => $teachers,
                ],
            ],
        ]);
    }
}
