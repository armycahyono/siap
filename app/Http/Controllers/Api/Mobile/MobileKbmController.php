<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CurriculumEvent;
use App\Models\LessonSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
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
        $liveMonitoring = $this->liveMonitoringService->buildLiveMonitoringData($user, $date, $academicYear, $myHomeroomClassroom);
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
        $isTeacher = ($schedule->teacher_id === $user->id);
        $isManagement = $user->isSuperAdmin() || $user->hasRole('admin') || $user->isPrincipal() || $user->isWakaKurikulum();
        $isPiket = $user->canOperatePiket();

        if (! $isTeacher && ! $isManagement && ! $isPiket) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengabsen jadwal ini.',
            ], 403);
        }

        // Cek jendela waktu
        $windowStatus = $schedule->getAttendanceWindowStatus($date, 15);
        $canSubmit = $windowStatus['is_open'] || $isManagement;

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
        $isManagement = $user->isSuperAdmin() || $user->hasRole('admin') || $user->isPrincipal() || $user->isWakaKurikulum();
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

            $scheduledTeacherId = $schedule?->teacher_id ?? $existingSession?->scheduled_teacher_id ?? $user->id;

            if ($existingSession) {
                $existingSession->update([
                    'lesson_schedule_id' => $lessonScheduleId,
                    'teacher_id' => $user->id,
                    'scheduled_teacher_id' => $scheduledTeacherId,
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
}
