<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
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
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubjectAttendanceController extends Controller
{
    /**
     * Map English day name to Indonesian
     */
    private function getIndonesianDayName(string $englishDay): string
    {
        return match ($englishDay) {
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
            default => 'Senin',
        };
    }

    /**
     * Halaman Utama Presensi Mengajar Mapel (Jadwal Hari Ini & Opsi Pengganti/Tukar Jam)
     */
    public function index(Request $request): Response|View
    {
        $user = Auth::user();
        $dateParam = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateParam);
        $dayName = $this->getIndonesianDayName($date->format('l'));

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

        // 1. Agenda / Kegiatan Sekolah pada tanggal ini dari Kalender Kurikulum
        $dateStr = $date->toDateString();
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

        // Evaluasi Hari KBM (KBM 5 hari kerja: Senin-Jumat; Sabtu & Minggu bukan hari KBM; Hari Libur ditiadakan KBM)
        $isWeekend = $date->isWeekend() || $date->dayOfWeekIso > 5;
        $holidayEvent = $todayEvents->first(function ($ev) {
            return $ev->is_holiday
                || in_array($ev->event_type, ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'libur_khusus', 'libur'])
                || $ev->teacher_impact === 'diliburkan_penuh'
                || $ev->learning_impact === 'libur_khusus';
        });
        $isHoliday = ! is_null($holidayEvent);
        $isKbmDay = ! $isWeekend && ! $isHoliday;

        // 2. Jadwal rutin mengajar milik guru yang login pada hari ini
        $mySchedules = LessonSchedule::with(['classroom.gradeLevel', 'subject', 'teacher'])
            ->where('teacher_id', $user->id)
            ->where('day', $dayName)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->when($semester, fn ($q) => $q->where('semester', $semester))
            ->orderBy('start_period')
            ->get();

        // 3. Sesi presensi yang sudah tersimpan untuk tanggal ini:
        // Ambil sesi di mana guru login adalah guru pengajar (teacher_id),
        // ATAU guru asli terjadwal (scheduled_teacher_id),
        // ATAU sesi yang terikat dengan jadwal mengajar guru ini
        $myScheduleIds = $mySchedules->pluck('id')->filter()->all();

        $savedSessionsCollection = SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher', 'teacher'])
            ->whereDate('attendance_date', $date->toDateString())
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

        // Mapping fallback berdasarkan rombel dan jam mulai (classroom_id + start_period)
        $savedSessionsBySlot = $savedSessionsCollection->keyBy(function ($session) {
            return "{$session->classroom_id}_{$session->start_period}";
        });

        // 4. Sesi mengajar orang lain yang digantikan oleh guru ini (tukar jam / guru pengganti)
        $swapSessions = SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher', 'teacher'])
            ->where('teacher_id', $user->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('is_swap', true)
            ->get();

        $isMobile = DeviceDetector::isMobile();

        // 5. Semua jadwal sekolah di hari ini (hanya pada hari ada KBM; Sabtu, Minggu, dan Libur tidak ada KBM)
        $allDaySchedules = $isKbmDay
            ? LessonSchedule::with(['classroom.gradeLevel', 'subject', 'teacher'])
                ->where('day', $dayName)
                ->where('teacher_id', '!=', $user->id)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->when($semester, fn ($q) => $q->where('semester', $semester))
                ->orderBy('classroom_id')
                ->orderBy('start_period')
                ->get()
            : collect();

        // Deteksi Jam Pelajaran yang sedang aktif saat ini (hanya dihitung pada hari ada KBM)
        $activeCurrentPeriod = null;
        $activeCurrentPeriodLabel = null;

        if ($isKbmDay) {
            $periodTimes = LessonSchedule::periodTimes();
            $currentTime = now()->format('H:i');

            foreach ($periodTimes as $p => $times) {
                if ($currentTime >= $times['start'] && $currentTime <= $times['end']) {
                    $activeCurrentPeriod = (int) $p;
                    $activeCurrentPeriodLabel = "Jam ke-{$p} ({$times['start']} - {$times['end']})";
                    break;
                }
            }

            // Jika pas jam istirahat atau sebelum/sesudah KBM
            if (! $activeCurrentPeriod) {
                if ($currentTime >= '09:15' && $currentTime < '09:50') {
                    $activeCurrentPeriod = 4; // Menjelang jam 4 setelah istirahat 1
                    $activeCurrentPeriodLabel = 'Istirahat 1 (Menjelang Jam 4)';
                } elseif ($currentTime >= '12:30' && $currentTime < '13:00') {
                    $activeCurrentPeriod = 8; // Menjelang jam 8 setelah istirahat 2
                    $activeCurrentPeriodLabel = 'Istirahat 2 (Menjelang Jam 8)';
                } elseif ($currentTime < '07:00') {
                    $activeCurrentPeriod = 1; // Pagi hari sebelum jam 1
                    $activeCurrentPeriodLabel = 'Sebelum KBM (Jam ke-1)';
                }
            }
        }

        // Ambil semua sesi presensi pada tanggal ini oleh siapa pun
        $allDateAttendanceSessions = SubjectAttendanceSession::with('teacher')
            ->whereDate('attendance_date', $date->toDateString())
            ->get();

        // Auto-heal sesi yang belum memiliki lesson_schedule_id jika ada jadwal yang cocok
        if ($isKbmDay) {
            foreach ($allDateAttendanceSessions->whereNull('lesson_schedule_id') as $unlinkedSession) {
                $matchedSchedule = LessonSchedule::where('classroom_id', $unlinkedSession->classroom_id)
                    ->where('day', $dayName)
                    ->where('start_period', $unlinkedSession->start_period)
                    ->first();
                if ($matchedSchedule) {
                    $unlinkedSession->update([
                        'lesson_schedule_id' => $matchedSchedule->id,
                        'scheduled_teacher_id' => $unlinkedSession->scheduled_teacher_id ?? $matchedSchedule->teacher_id,
                    ]);
                    $unlinkedSession->lesson_schedule_id = $matchedSchedule->id;
                }
            }
        }

        // Kumpulkan ID jadwal & slot (classroom_id + start_period) yang sudah diabsen
        $todayAttendedScheduleIds = $allDateAttendanceSessions
            ->pluck('lesson_schedule_id')
            ->filter()
            ->unique()
            ->toArray();

        $todayAttendedSlots = $allDateAttendanceSessions
            ->map(fn ($s) => "{$s->classroom_id}_{$s->start_period}")
            ->unique()
            ->toArray();

        // Mapping guru yang mengabsen per jadwal / slot
        $attendedTeacherByScheduleId = [];
        $attendedTeacherBySlot = [];
        foreach ($allDateAttendanceSessions as $sess) {
            $tName = $sess->teacher?->formatted_short_name ?? ($sess->teacher?->name ?? 'Guru');
            if ($sess->lesson_schedule_id) {
                $attendedTeacherByScheduleId[$sess->lesson_schedule_id] = $tName;
            }
            $attendedTeacherBySlot["{$sess->classroom_id}_{$sess->start_period}"] = $tName;
        }

        // Pastikan seluruh jadwal KBM hari ini yang slotnya sudah ada sesi otomatis diakui sudah diabsen
        if ($isKbmDay && $allDaySchedules->isNotEmpty()) {
            foreach ($allDaySchedules as $s) {
                $slotKey = "{$s->classroom_id}_{$s->start_period}";
                if (in_array($slotKey, $todayAttendedSlots)) {
                    $todayAttendedScheduleIds[] = $s->id;
                    if (! isset($attendedTeacherByScheduleId[$s->id]) && isset($attendedTeacherBySlot[$slotKey])) {
                        $attendedTeacherByScheduleId[$s->id] = $attendedTeacherBySlot[$slotKey];
                    }
                }
            }
        }
        $todayAttendedScheduleIds = array_values(array_unique($todayAttendedScheduleIds));

        // 6. Riwayat sesi terakhir yang telah diajar oleh guru (hanya web desktop)
        $recentSessions = $isMobile ? collect() : SubjectAttendanceSession::with(['classroom', 'subject', 'records', 'scheduledTeacher'])
            ->where('teacher_id', $user->id)
            ->latest('attendance_date')
            ->latest('start_period')
            ->take(5)
            ->get();

        // Cek apakah guru yang login ditugaskan pada agenda yang menggugurkan kewajiban mengajar
        $teacherOnDutyEvents = $todayEvents->filter(function ($ev) use ($user) {
            return $ev->teacher_impact === 'bebas_mengajar_tugas' && $ev->users->contains('id', $user->id);
        });

        // Filter kegiatan yang berdampak khusus pada guru ini (jadwal mengajar, penugasan dinas, atau libur)
        $teacherImpactingEvents = $todayEvents->filter(function ($ev) use ($user, $mySchedules) {
            return $ev->affectsTeacher($user, $mySchedules);
        });

        // Peran dan Hak Akses Monitoring KBM
        $isPrincipal = $user->isPrincipal();
        $isKurikulum = $user->isWakaKurikulum();
        $isDiscipline = $user->isDisciplineTeam();
        $hasTeachingDuty = $user->hasTeachingDuty();

        // Data Monitoring KBM Hari Ini (KS, Kurikulum, Tim Kedisiplinan)
        $kbmMonitoring = null;
        if ($isPrincipal || $isKurikulum || $isDiscipline) {
            // Ambil Roster GTK, Target Jadwal, dan Snapshot Rombel dari DailyKbmSnapshotService
            $snapshotService = app(DailyKbmSnapshotService::class);
            $snapshot = $snapshotService->getOrGenerateSnapshot($date);

            $gtkMonitoring = null;
            if ($isPrincipal || $isKurikulum) {
                $dailyRosters = $snapshot['rosters'];
                $dailyScheduleTargets = $snapshot['schedules'];

                // Indikator Libur & Weekend dari Roster
                $isHolidayToday = $dailyRosters->first()?->is_holiday ?? false;
                $isWeekend = $date->isWeekend();

                // Jadwal KBM aktif (tidak dibebaskan)
                $activeScheduleTargets = $dailyScheduleTargets->where('is_exempted', false)->values();
                $allTodaySchedules = $activeScheduleTargets->map(function ($target) {
                    $sched = $target->lessonSchedule;
                    if ($sched) {
                        $sched->setRelation('classroom', $target->classroom);
                        $sched->setRelation('subject', $target->subject);
                        $sched->setRelation('teacher', $target->teacher);
                    }

                    return $sched;
                })->filter()->values();

                // GTK yang diharapkan hadir hari ini (is_expected_checkin == true)
                $expectedGtkUsers = $dailyRosters->where('is_expected_checkin', true)
                    ->map(fn ($r) => $r->user)
                    ->filter()
                    ->values();

                $expectedGtkCount = $expectedGtkUsers->count();
                $expectedGtkUserIds = $expectedGtkUsers->pluck('id');

                // 3. Rekap Izin GTK yang wajib hadir
                $gtkPermits = GtkPermit::approved()->with('user')
                    ->whereDate('start_date', '<=', $dateStr)
                    ->whereDate('end_date', '>=', $dateStr)
                    ->get()
                    ->filter(fn ($p) => $expectedGtkUserIds->contains($p->user_id))
                    ->values();
                $gtkPermitUserIds = $gtkPermits->pluck('user_id')->unique();
                $gtkPermitCount = $gtkPermitUserIds->count();

                // 4. Presensi Datang GTK yang wajib hadir (atau yang hadir)
                $gtkScansToday = AttendanceLog::with('user')
                    ->where('user_type', 'teacher')
                    ->whereDate('scanned_at', $dateStr)
                    ->orderBy('scanned_at', 'asc')
                    ->get();

                $gtkAttendedUserIds = $gtkScansToday->pluck('user_id')->filter()->unique();
                $gtkPresentScans = $gtkScansToday->unique('user_id')->values();
                $gtkPresentCount = $gtkPresentScans->count();

                $gtkLateScans = $gtkScansToday->where('status', 'terlambat')->unique('user_id')->values();
                $gtkLateCount = $gtkLateScans->count();

                // 5. Belum Absen Masuk: GTK yang wajib hadir hari ini tetapi belum ada scan dan belum ada surat izin
                $gtkAwaitingList = $expectedGtkUsers->filter(function ($u) use ($gtkAttendedUserIds, $gtkPermitUserIds) {
                    return ! $gtkAttendedUserIds->contains($u->id) && ! $gtkPermitUserIds->contains($u->id);
                })->values();
                $gtkAwaitingCount = $gtkAwaitingList->count();

                // 6. Belum Absen Mapel (Jadwal KBM yang jamnya sudah mulai/lewat jika hari ini)
                $takenSessionScheduleIds = $todayAttendedScheduleIds;
                $takenSessionSlotKeys = $todayAttendedSlots;

                $periodTimes = LessonSchedule::periodTimes();
                $isTodayDate = $date->isToday();
                $currentTimeStr = now()->format('H:i');

                $unattendedSchedules = $allTodaySchedules->filter(function ($s) use ($takenSessionScheduleIds, $takenSessionSlotKeys, $isTodayDate, $currentTimeStr, $periodTimes, $date) {
                    // Jika sudah diabsen, tidak termasuk
                    if (in_array($s->id, $takenSessionScheduleIds) || in_array("{$s->classroom_id}_{$s->start_period}", $takenSessionSlotKeys)) {
                        return false;
                    }

                    // Jika melihat tanggal masa depan, belum ada yang lewat
                    if ($date->isFuture() && ! $isTodayDate) {
                        return false;
                    }

                    // Jika hari ini: hanya hitung jadwal yang jam mulainya sudah lewat atau sedang berlangsung
                    if ($isTodayDate) {
                        $startTime = $s->start_time ?? ($periodTimes[$s->start_period]['start'] ?? null);
                        if ($startTime && $startTime > $currentTimeStr) {
                            return false; // Jam pelajaran belum mulai
                        }
                    }

                    return true;
                })->values();

                $gtkMonitoring = [
                    'all_count' => $expectedGtkCount,
                    'present_count' => $gtkPresentCount,
                    'late_count' => $gtkLateCount,
                    'permit_count' => $gtkPermitCount,
                    'awaiting_count' => $gtkAwaitingCount,
                    'unattended_count' => $unattendedSchedules->count(),
                    'is_holiday' => $isHolidayToday,
                    'is_weekend' => $isWeekend,
                    'permits_list' => $gtkPermits,
                    'present_list' => $gtkPresentScans,
                    'late_list' => $gtkLateScans,
                    'awaiting_list' => $gtkAwaitingList,
                    'unattended_schedules' => $unattendedSchedules,
                ];
            }

            // Monitoring Siswa Hari Ini (KS, Kurikulum, dan Tim Kedisiplinan)
            $studentPermitsToday = PiketStudentPermit::with(['student', 'classroom'])
                ->whereDate('permit_date', $dateStr)
                ->orderBy('created_at', 'desc')
                ->get();

            // Deduplikasi izin piket berdasarkan student_id (agar siswa yang terinput ganda tidak dihitung berkali-kali)
            $studentLatePermits = $studentPermitsToday->where('permit_type', 'terlambat')->unique('student_id')->values();
            $studentLateCount = $studentLatePermits->count();

            $studentEarlyLeavePermits = $studentPermitsToday->where('permit_type', 'izin_pulang_cepat')->unique('student_id')->values();
            $studentEarlyLeaveCount = $studentEarlyLeavePermits->count();

            $studentIzinPermits = $studentPermitsToday->where('permit_type', 'izin_tidak_masuk')->unique('student_id')->values();
            $studentPermitCount = $studentIzinPermits->count();

            $todaySessions = SubjectAttendanceSession::whereDate('attendance_date', $dateStr)->pluck('id');
            $studentRecordsToday = SubjectAttendanceRecord::with(['student', 'session.classroom', 'session.subject'])
                ->whereIn('subject_attendance_session_id', $todaySessions)
                ->get();

            $studentAlfaRecords = $studentRecordsToday->where('status', 'a')->values();
            $studentAlfaCount = $studentAlfaRecords->pluck('student_id')->unique()->count();

            // Pisahkan izin dan sakit
            // 1. Dari Piket: periksa kolom permit_category ('sakit') atau kata 'sakit' di dalam kolom reason
            $studentSakitPermits = $studentIzinPermits->filter(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();
            $studentMurniIzinPermits = $studentIzinPermits->reject(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();

            // 2. Dari Rekap Presensi KBM Mapel: status 's' (sakit), status 'i' (izin) & 'd' (dispensasi)
            $studentSakitRecords = $studentRecordsToday->where('status', 's')->values();
            $studentMurniIzinRecords = $studentRecordsToday->whereIn('status', ['i', 'd'])->values();
            $studentPresentRecords = $studentRecordsToday->where('status', 'h')->values();

            // Gabungan unik siswa sakit
            $allSakitStudentIds = $studentSakitPermits->pluck('student_id')
                ->concat($studentSakitRecords->pluck('student_id'))
                ->filter()
                ->unique();
            $totalStudentSakitCount = $allSakitStudentIds->count();

            // Gabungan unik siswa izin (tanpa yang sudah masuk sakit)
            $allMurniIzinStudentIds = $studentMurniIzinPermits->pluck('student_id')
                ->concat($studentMurniIzinRecords->pluck('student_id'))
                ->filter()
                ->diff($allSakitStudentIds)
                ->unique();
            $totalStudentMurniIzinCount = $allMurniIzinStudentIds->count();

            $todayIncidents = PiketIncident::with(['student', 'classroom'])
                ->whereDate('incident_date', $dateStr)
                ->orderBy('incident_time', 'desc')
                ->get();

            // -------------------------------------------------------------
            // DETEKSI KHUSUS: Siswa Bolos KBM (Hadir di pagi/sesi awal tapi Alfa di sesi berikutnya)
            // Serta Deteksi Siswa Scan Masuk Fingerprint tapi Alfa di Mapel
            // -------------------------------------------------------------
            $studentFingerprintLogs = AttendanceLog::where('user_type', 'student')
                ->whereDate('scanned_at', $dateStr)
                ->pluck('student_id')
                ->filter()
                ->unique();

            $studentEarlyLeaveStudentIds = $studentEarlyLeavePermits->pluck('student_id')->filter()->unique();

            // Kelompokkan catatan presensi mapel per siswa hari ini
            $recordsByStudent = $studentRecordsToday->groupBy('student_id');
            $truancyStudents = collect(); // Siswa terindikasi bolos di tengah KBM

            foreach ($recordsByStudent as $sId => $records) {
                // Lewati jika siswa mengantongi izin pulang cepat dari piket
                if ($studentEarlyLeaveStudentIds->contains($sId)) {
                    continue;
                }

                $hasPresentSession = $records->contains('status', 'h');
                $hasAlfaSession = $records->contains('status', 'a');
                $hasFingerprint = $studentFingerprintLogs->contains($sId);

                // Pola 1: Pernah hadir di satu mapel hari ini tapi alfa di mapel lainnya (bolos jam pelajaran)
                // Pola 2: Scan fingerprint masuk di gerbang tapi alfa di kelas tanpa izin piket
                if (($hasPresentSession && $hasAlfaSession) || ($hasFingerprint && $hasAlfaSession)) {
                    $firstRecord = $records->first();
                    $studentModel = $firstRecord->student;
                    $classroomModel = $firstRecord->session?->classroom;
                    $alfaSubjects = $records->where('status', 'a')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');
                    $presentSubjects = $records->where('status', 'h')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');

                    $truancyStudents->push((object) [
                        'student_id' => $sId,
                        'student_name' => $studentModel?->name ?? 'Siswa',
                        'classroom_name' => $classroomModel?->name ?? '-',
                        'alfa_count_jp' => $records->where('status', 'a')->count(),
                        'alfa_subjects' => $alfaSubjects ?: 'Mapel Tertentu',
                        'present_subjects' => $presentSubjects ?: ($hasFingerprint ? 'Scan Masuk Gerbang' : '-'),
                        'has_fingerprint' => $hasFingerprint,
                    ]);
                }
            }

            // Alfa murni (Alfa di mapel dan TIDAK pernah hadir di mapel lain atau scan gerbang)
            $truancyStudentIds = $truancyStudents->pluck('student_id');
            $pureAlfaRecords = $studentAlfaRecords->reject(fn ($r) => $truancyStudentIds->contains($r->student_id))->values();
            $pureAlfaCount = $pureAlfaRecords->pluck('student_id')->unique()->count();

            $classroomSnapshots = $snapshot['classrooms'] ?? collect();
            $totalActiveStudents = $classroomSnapshots->where('is_kbm_active', true)->sum('total_students');
            $totalPresentStudents = $classroomSnapshots->where('is_kbm_active', true)->sum('present_students_count');
            $totalCompletedSessions = $classroomSnapshots->where('is_kbm_active', true)->sum('completed_sessions_count');
            $overallAttendanceRate = $totalActiveStudents > 0
                ? round(($totalPresentStudents / $totalActiveStudents) * 100, 1)
                : 100.0;

            $studentMonitoring = [
                'present_count' => $totalPresentStudents,
                'izin_count' => $totalStudentMurniIzinCount,
                'sakit_count' => $totalStudentSakitCount,
                'late_count' => $studentLateCount,
                'pulang_count' => $studentEarlyLeaveCount,
                'alfa_count' => $pureAlfaCount,
                'truancy_count' => $truancyStudents->count(),
                'truancy_list' => $truancyStudents,
                'total_expected_students' => $totalActiveStudents,
                'total_present_students' => $totalPresentStudents,
                'total_completed_sessions' => $totalCompletedSessions,
                'overall_attendance_rate' => $overallAttendanceRate,
                'classroom_snapshots' => $classroomSnapshots,
                'permits_list' => $studentPermitsToday,
                'izin_permits' => $studentMurniIzinPermits,
                'sakit_permits' => $studentSakitPermits,
                'izin_records' => $studentMurniIzinRecords,
                'sakit_records' => $studentSakitRecords,
                'present_records' => $studentPresentRecords,
                'late_permits' => $studentLatePermits,
                'pulang_permits' => $studentEarlyLeavePermits,
                'alfa_records' => $pureAlfaRecords,
                'all_alfa_records' => $studentAlfaRecords,
                'incidents_list' => $todayIncidents,
                'incidents_count' => $todayIncidents->count(),
            ];

            $kbmMonitoring = [
                'gtk' => $gtkMonitoring,
                'siswa' => $studentMonitoring,
                'can_view_gtk' => ($isPrincipal || $isKurikulum),
                'can_view_student' => ($isPrincipal || $isKurikulum || $isDiscipline),
            ];
        }

        // Data master untuk modal presensi mandiri tanpa jadwal (hanya web desktop)
        $classrooms = $isMobile ? collect() : Classroom::with('gradeLevel')->orderBy('name')->get();
        $subjects = $isMobile ? collect() : Subject::orderBy('name')->get();
        $teachers = $isMobile ? collect() : User::operationalPersonnel()->orderBy('name')->get();

        $periodTimes = LessonSchedule::periodTimes();

        $viewData = compact(
            'date',
            'dayName',
            'mySchedules',
            'savedSessions',
            'savedSessionsBySlot',
            'swapSessions',
            'allDaySchedules',
            'todayAttendedScheduleIds',
            'todayAttendedSlots',
            'attendedTeacherByScheduleId',
            'attendedTeacherBySlot',
            'recentSessions',
            'todayEvents',
            'teacherOnDutyEvents',
            'teacherImpactingEvents',
            'classrooms',
            'subjects',
            'teachers',
            'kbmMonitoring',
            'hasTeachingDuty',
            'isPrincipal',
            'isKurikulum',
            'isDiscipline',
            'activeCurrentPeriod',
            'activeCurrentPeriodLabel',
            'periodTimes',
            'isKbmDay',
            'isWeekend',
            'isHoliday',
            'holidayEvent'
        );

        $viewName = $isMobile ? 'mobile.attendance.subject' : 'web.attendance.subject';

        return response()
            ->view($viewName, $viewData)
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT');
    }

    /**
     * Tampilkan formulir lembar absen per-siswa (Berdasarkan Jadwal atau Tukar Jam)
     */
    public function take(Request $request): View
    {
        $user = Auth::user();
        $dateParam = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateParam);
        $scheduleId = $request->query('schedule_id');
        $isSwap = $request->boolean('is_swap');
        $swapType = $request->query('swap_type', $isSwap ? 'tukar_jam' : null);

        $schedule = null;
        $classroom = null;
        $subject = null;
        $scheduledTeacher = null;
        $startPeriod = (int) $request->query('start_period', 1);
        $endPeriod = (int) $request->query('end_period', 2);

        if ($scheduleId) {
            $schedule = LessonSchedule::with(['classroom.homeroomTeacher', 'classroom.gradeLevel', 'subject', 'teacher'])->findOrFail($scheduleId);
            $classroom = $schedule->classroom;
            $subject = $schedule->subject;
            $scheduledTeacher = $schedule->teacher;
            $startPeriod = $schedule->start_period;
            $endPeriod = $schedule->end_period;

            // Jika bukan jadwal guru sendiri, otomatis tandai sebagai tukar jam
            if ($schedule->teacher_id !== $user->id) {
                $isSwap = true;
                $swapType = $swapType ?? 'tukar_jam';
            }
        } else {
            // Mode mandiri / ad-hoc (pilih kelas & mapel langsung)
            $classroomId = $request->query('classroom_id');
            $subjectId = $request->query('subject_id');
            $scheduledTeacherId = $request->query('scheduled_teacher_id');

            $classroom = Classroom::with('homeroomTeacher')->findOrFail($classroomId);
            $subject = Subject::findOrFail($subjectId);
            if ($scheduledTeacherId) {
                $scheduledTeacher = User::find($scheduledTeacherId);
            }
        }

        // Cek apakah sesi ini sudah pernah diabsen sebelumnya pada tanggal yang sama
        $session = SubjectAttendanceSession::with('records')
            ->where('classroom_id', $classroom->id)
            ->where('subject_id', $subject->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('start_period', $startPeriod)
            ->first();

        // Jika belum ada $schedule, coba cocokkan secara cerdas dengan jadwal KBM hari ini
        if (! $schedule && $classroom && $subject) {
            $dayName = $this->getIndonesianDayName($date->format('l'));
            $matched = LessonSchedule::with(['classroom.homeroomTeacher', 'subject', 'teacher'])
                ->where('classroom_id', $classroom->id)
                ->where('subject_id', $subject->id)
                ->where('day', $dayName)
                ->where('start_period', $startPeriod)
                ->first();

            if (! $matched) {
                $matched = LessonSchedule::with(['classroom.homeroomTeacher', 'subject', 'teacher'])
                    ->where('classroom_id', $classroom->id)
                    ->where('day', $dayName)
                    ->where('start_period', $startPeriod)
                    ->first();
            }

            if ($matched) {
                $schedule = $matched;
                if (! $scheduledTeacher) {
                    $scheduledTeacher = $matched->teacher;
                }
                if ($schedule->teacher_id !== $user->id) {
                    $isSwap = true;
                    $swapType = $swapType ?? 'tukar_jam';
                }
            }
        }

        if (! $schedule && $session && $session->lesson_schedule_id) {
            $schedule = LessonSchedule::with(['classroom.homeroomTeacher', 'classroom.gradeLevel', 'subject', 'teacher'])->find($session->lesson_schedule_id);
            if ($schedule && ! $scheduledTeacher) {
                $scheduledTeacher = $schedule->teacher;
            }
        }

        // Validasi Jendela Waktu Presensi: Paling cepat 15 menit sebelum jam KBM dimulai
        if (! $session && ! ($user->hasRole('super_admin') || $user->isWakaKurikulum() || $user->isPrincipal())) {
            $windowStatus = $schedule
                ? $schedule->getAttendanceWindowStatus($date, 15)
                : LessonSchedule::checkAttendanceWindow($startPeriod, null, $this->getIndonesianDayName($date->format('l')), $date, 15);

            if (! $windowStatus['is_open']) {
                return redirect()->route('attendance.subject.index', ['date' => $date->toDateString()])
                    ->with('error', $windowStatus['message'] ?? 'Presensi kelas belum dapat dibuka.');
            }
        }

        // Tentukan tahun ajaran target untuk pengambilan data siswa di kelas
        if ($session && ! empty($session->academic_year)) {
            $targetAcademicYear = $session->academic_year;
        } elseif ($schedule && ! empty($schedule->academic_year)) {
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

        // Ambil daftar siswa yang terdaftar di kelas pada tahun ajaran target
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
            // Fallback ke siswa kelas aktif jika belum ada riwayat student_classrooms
            $students = Student::where('classroom_id', $classroom->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $studentIds = $students->pluck('id');
        $dateStr = $date->toDateString();

        // Ambil data perizinan piket / laporan wali kelas untuk tanggal ini
        $permits = PiketStudentPermit::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('permit_date', $dateStr)
                    ->orWhere(function ($sub) use ($dateStr) {
                        $sub->whereDate('permit_date', '<=', $dateStr)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $dateStr);
                    });
            })
            ->where('status', '!=', 'ditolak')
            ->get()
            ->keyBy('student_id');

        // Ambil log scan mesin absensi pertama pada tanggal ini untuk siswa di kelas ini
        $machineLogs = AttendanceLog::whereIn('student_id', $studentIds)
            ->whereDate('scanned_at', $dateStr)
            ->orderBy('scanned_at', 'asc')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($logs) => $logs->first());

        // Ambil event kalender kurikulum yang berdampak pada rombel kelas ini pada tanggal ini
        $allDateEvents = CurriculumEvent::where(function ($q) use ($dateStr) {
            $q->whereDate('start_date', '<=', $dateStr)
                ->where(function ($sub) use ($dateStr) {
                    $sub->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $dateStr);
                });
        })->get();

        $applicableClassEvents = $allDateEvents->filter(fn ($ev) => $ev->appliesToClassroom($classroom));
        $classroomEvent = $applicableClassEvents->first(fn ($ev) => in_array($ev->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus', 'pembelajaran_daring']) || $ev->is_holiday)
            ?? $applicableClassEvents->first(fn ($ev) => $ev->learning_impact && $ev->learning_impact !== 'normal')
            ?? $applicableClassEvents->sortByDesc(fn ($ev) => match ($ev->target_audience) {
                'rombel' => 3,
                'tingkat' => 2,
                default => 1,
            })->first();

        $disengagedEvent = null;
        if (! $classroomEvent) {
            $disengagedEvent = $allDateEvents->first(fn ($ev) => ! $ev->is_holiday && $ev->disengaged_student_impact !== 'kbm_normal');
        }

        // Mapping status presensi jika sudah ada sesi yang tersimpan
        $existingRecords = $session ? $session->records->keyBy('student_id') : collect();

        // Cari sesi presensi kelas terdahulu pada hari yang sama (misal jam ke-1 sebelum jam ke-3)
        // untuk mewarisi status ketidakhadiran (S, I, A, D) agar guru berikutnya tidak mulai dari nol
        $earlierSession = null;
        $previousRecords = collect();
        if (! $session) {
            $earlierSession = SubjectAttendanceSession::with('records')
                ->where('classroom_id', $classroom->id)
                ->whereDate('attendance_date', $dateStr)
                ->where('start_period', '<', $startPeriod)
                ->orderBy('start_period', 'desc')
                ->first();

            if ($earlierSession) {
                $previousRecords = $earlierSession->records->keyBy('student_id');
            }
        }

        $allTeachers = User::operationalPersonnel()->orderBy('name')->get();

        // Cari data Inval Meja Piket untuk kelas dan jam ini jika ada
        $activeInval = PiketInvalClass::where('classroom_id', $classroom->id)
            ->whereHas('journal', function ($q) use ($dateStr) {
                $q->whereDate('date', $dateStr);
            })
            ->where('period_start', '<=', $endPeriod)
            ->where('period_end', '>=', $startPeriod)
            ->with(['absentTeacher', 'invalTeacher'])
            ->first();

        $viewData = compact(
            'date',
            'schedule',
            'classroom',
            'subject',
            'scheduledTeacher',
            'startPeriod',
            'endPeriod',
            'isSwap',
            'swapType',
            'session',
            'students',
            'permits',
            'machineLogs',
            'classroomEvent',
            'disengagedEvent',
            'existingRecords',
            'earlierSession',
            'previousRecords',
            'allTeachers',
            'activeInval'
        );

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.take', $viewData);
        }

        return view('attendance.subject.take', $viewData);
    }

    /**
     * Simpan / Perbarui Presensi Siswa pada Pelajaran
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'academic_year' => ['nullable', 'string', 'exists:academic_years,name'],
            'semester' => ['nullable', 'string', 'in:ganjil,genap,Ganjil,Genap'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'start_period' => ['required', 'integer', 'min:1', 'max:15'],
            'end_period' => ['required', 'integer', 'min:1', 'max:15'],
            'lesson_schedule_id' => ['nullable', 'exists:lesson_schedules,id'],
            'scheduled_teacher_id' => ['nullable', 'exists:users,id'],
            'is_swap' => ['nullable', 'boolean'],
            'swap_type' => ['nullable', 'string', 'in:tukar_jam,guru_pengganti,inval,mandiri'],
            'swap_notes' => ['nullable', 'string', 'max:500'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attendance' => ['required', 'array'],
            'attendance.*.status' => ['required', 'in:h,s,i,a,d,t'],
            'attendance.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = SchoolSetting::getSetting();
        $date = Carbon::parse($validated['date']);

        $lessonScheduleId = $validated['lesson_schedule_id'] ?? null;
        $schedule = $lessonScheduleId ? LessonSchedule::find($lessonScheduleId) : null;

        // Jika schedule belum ada, cocokkan otomatis dengan Master Jadwal KBM hari tersebut
        if (! $schedule) {
            $dayName = $this->getIndonesianDayName($date->format('l'));
            $schedule = LessonSchedule::where('classroom_id', $validated['classroom_id'])
                ->where('day', $dayName)
                ->where('start_period', $validated['start_period'])
                ->where('subject_id', $validated['subject_id'])
                ->first();

            if (! $schedule) {
                $schedule = LessonSchedule::where('classroom_id', $validated['classroom_id'])
                    ->where('day', $dayName)
                    ->where('start_period', $validated['start_period'])
                    ->first();
            }

            if ($schedule) {
                $lessonScheduleId = $schedule->id;
                if (empty($validated['scheduled_teacher_id'])) {
                    $validated['scheduled_teacher_id'] = $schedule->teacher_id;
                }
            }
        } elseif (empty($validated['scheduled_teacher_id'])) {
            $validated['scheduled_teacher_id'] = $schedule->teacher_id;
        }

        $existingSession = SubjectAttendanceSession::where('classroom_id', $validated['classroom_id'])
            ->where('subject_id', $validated['subject_id'])
            ->whereDate('attendance_date', $date->toDateString())
            ->where('start_period', $validated['start_period'])
            ->first();

        // Validasi Jendela Waktu Presensi: Paling cepat 15 menit sebelum jam KBM dimulai
        if (! $existingSession && ! ($user->hasRole('super_admin') || $user->isWakaKurikulum() || $user->isPrincipal())) {
            $windowStatus = $schedule
                ? $schedule->getAttendanceWindowStatus($date, 15)
                : LessonSchedule::checkAttendanceWindow(
                    $validated['start_period'],
                    null,
                    $this->getIndonesianDayName($date->format('l')),
                    $date,
                    15
                );

            if (! $windowStatus['is_open']) {
                return back()->with('error', $windowStatus['message'] ?? 'Presensi kelas belum dapat dibuka.');
            }
        }

        DB::beginTransaction();
        try {
            if (! $lessonScheduleId && $existingSession?->lesson_schedule_id) {
                $lessonScheduleId = $existingSession->lesson_schedule_id;
            }

            // Tentukan tahun ajaran dan semester untuk sesi presensi
            if (! empty($validated['academic_year'])) {
                $targetAcademicYear = $validated['academic_year'];
                $targetSemester = ! empty($validated['semester']) ? strtolower($validated['semester']) : null;
            } elseif ($existingSession && ! empty($existingSession->academic_year)) {
                // Pertahankan tahun ajaran asli dari rekaman presensi lama yang diperbarui
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

            // Temukan atau buat sesi presensi
            if ($existingSession) {
                $existingSession->update([
                    'lesson_schedule_id' => $lessonScheduleId,
                    'teacher_id' => $user->id,
                    'scheduled_teacher_id' => $validated['scheduled_teacher_id'] ?? $existingSession->scheduled_teacher_id,
                    'end_period' => $validated['end_period'],
                    'academic_year' => $targetAcademicYear,
                    'semester' => $targetSemester,
                    'is_swap' => $request->boolean('is_swap'),
                    'swap_type' => $validated['swap_type'] ?? null,
                    'swap_notes' => $validated['swap_notes'] ?? null,
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
                    'lesson_schedule_id' => $lessonScheduleId,
                    'teacher_id' => $user->id,
                    'scheduled_teacher_id' => $validated['scheduled_teacher_id'] ?? null,
                    'end_period' => $validated['end_period'],
                    'academic_year' => $targetAcademicYear,
                    'semester' => $targetSemester,
                    'is_swap' => $request->boolean('is_swap'),
                    'swap_type' => $validated['swap_type'] ?? null,
                    'swap_notes' => $validated['swap_notes'] ?? null,
                    'topic' => $validated['topic'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            // Simpan / update status setiap siswa
            foreach ($validated['attendance'] as $studentId => $data) {
                SubjectAttendanceRecord::updateOrCreate(
                    [
                        'subject_attendance_session_id' => $session->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'status' => $data['status'],
                        'notes' => $data['notes'] ?? null,
                    ]
                );
            }

            DB::commit();

            try {
                $clsName = $session->classroom?->name ?? 'Kelas';
                $sbjName = $session->subject?->name ?? 'Mapel';
                ActivityLogger::log(
                    action: 'kbm_attendance',
                    description: "Melakukan presensi KBM {$clsName} mata pelajaran {$sbjName} (Jam ke-{$validated['start_period']}-{$validated['end_period']}).",
                    subject: $session,
                    properties: [
                        'classroom' => $clsName,
                        'subject' => $sbjName,
                        'start_period' => $validated['start_period'],
                        'end_period' => $validated['end_period'],
                        'date' => $date->toDateString(),
                    ],
                    user: $user
                );
            } catch (\Throwable) {
                // Non-blocking log failure
            }

            // Sinkronisasi status selesai pada tabel snapshot harian
            try {
                app(DailyKbmSnapshotService::class)->syncCompletedSessions($date);
            } catch (\Throwable $t) {
                // Jangan gagalkan alur utama jika sync snapshot terkendala
            }

            return redirect()->route('attendance.subject.index', ['date' => $date->toDateString()])
                ->with('success', 'Presensi siswa berhasil disimpan.')
                ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan presensi: '.$e->getMessage());
        }
    }

    /**
     * Lihat Detail & Rekap Sesi Presensi
     */
    public function show(SubjectAttendanceSession $session): View
    {
        $session->load(['classroom', 'subject', 'teacher', 'scheduledTeacher', 'records.student']);

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.subject_show', compact('session'));
        }

        return view('attendance.subject.show', compact('session'));
    }

    /**
     * Riwayat Seluruh Presensi Mengajar Guru (Filter Tanggal, Kelas, Mapel)
     */
    public function history(Request $request): View
    {
        $user = Auth::user();
        $classroomId = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $query = SubjectAttendanceSession::with(['classroom', 'subject', 'teacher', 'scheduledTeacher', 'records'])
            ->where('teacher_id', $user->id)
            ->whereBetween('attendance_date', [$startDate, $endDate]);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->query('academic_year'));
        }

        $sessions = $query->latest('attendance_date')
            ->latest('start_period')
            ->paginate(15)
            ->withQueryString();

        $classrooms = Classroom::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.subject_history', compact('sessions', 'classrooms', 'subjects', 'startDate', 'endDate', 'classroomId', 'subjectId'));
        }

        return view('web.attendance.subject_history', compact('sessions', 'classrooms', 'subjects', 'startDate', 'endDate', 'classroomId', 'subjectId'));
    }

    /**
     * Halaman Jadwal Pelajaran Sekolah (Dapat diakses oleh semua user)
     */
    public function schoolSchedule(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->input('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }
        $semester = $request->input('semester', session('active_semester', session('semester', $setting->semester ?? 'Ganjil')));

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            default => 'Senin',
        };

        $selectedDay = $request->input('day', $todayDay);
        if (! in_array($selectedDay, $days, true)) {
            $selectedDay = 'Senin';
        }

        $classroomId = $request->input('classroom_id');
        $teacherId = $request->input('teacher_id');
        $search = trim((string) $request->input('search', ''));

        $ayRecord = AcademicYear::where('name', $academicYear)->first();

        // Ambil semua kelas aktif
        $classrooms = Classroom::with('homeroomTeacher')
            ->when($ayRecord, function ($q) use ($academicYear, $ayRecord) {
                $q->where(function ($sub) use ($academicYear, $ayRecord) {
                    $sub->where('academic_year_id', $ayRecord->id)
                        ->orWhere(function ($s2) use ($academicYear) {
                            $s2->whereNull('academic_year_id')
                                ->where('academic_year', $academicYear);
                        });
                });
            }, function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::with('homeroomTeacher')->where('is_active', true)->orderBy('name')->get();
        }

        // Ambil seluruh guru yang memiliki jadwal mengajar pada tahun ajaran ini
        $teachers = User::whereHas('lessonSchedules', function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear);
        })->where('is_active', true)->orderBy('name')->get();

        // Hitung total jadwal per hari
        $dayCounts = LessonSchedule::where('academic_year', $academicYear)
            ->select('day', DB::raw('count(*) as count'))
            ->groupBy('day')
            ->pluck('count', 'day');

        // Deteksi jam pelajaran aktif saat ini
        $periodTimes = LessonSchedule::periodTimes($selectedDay);
        $currentTime = now()->format('H:i');
        $activePeriod = null;
        if ($selectedDay === $todayDay && now()->dayOfWeekIso <= 5) {
            foreach ($periodTimes as $p => $times) {
                if ($currentTime >= $times['start'] && $currentTime < $times['end']) {
                    $activePeriod = $p;
                    break;
                }
            }
        }

        // Query seluruh jadwal sekolah tanpa restriksi role
        $query = LessonSchedule::with(['classroom.homeroomTeacher', 'subject', 'teacher'])
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
                $q->whereHas('classroom', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $schedules = $query->orderBy('start_period')
            ->orderBy('classroom_id')
            ->get();

        $groupedByClassroom = $schedules->groupBy('classroom_id');
        $groupedByTeacher = $schedules->groupBy('teacher_id');

        $selectedClassroom = $classrooms->firstWhere('id', (int) $classroomId);
        $selectedTeacher = $teachers->firstWhere('id', (int) $teacherId);

        return view('mobile.attendance.school_schedule', compact(
            'academicYear',
            'semester',
            'days',
            'todayDay',
            'selectedDay',
            'classrooms',
            'teachers',
            'dayCounts',
            'schedules',
            'groupedByClassroom',
            'groupedByTeacher',
            'periodTimes',
            'activePeriod',
            'classroomId',
            'teacherId',
            'selectedClassroom',
            'selectedTeacher',
            'search'
        ));
    }
}
