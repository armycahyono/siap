<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Models\User;
use App\Services\DeviceDetector;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

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

        // Status Kehadiran GPS / Mesin hari ini (Masuk & Pulang)
        $todayAttendanceLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->orderBy('scanned_at')
            ->get();
        $myCheckInToday = $todayAttendanceLogs->first();
        $myCheckOutToday = $todayAttendanceLogs->count() > 1 ? $todayAttendanceLogs->last() : null;

        // Jadwal Mengajar Guru Khusus Hari Ini
        $todayTeachingSchedules = LessonSchedule::with(['classroom', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->when($setting->academic_year, fn ($q) => $q->where('academic_year', $setting->academic_year))
            ->when($setting->semester, fn ($q) => $q->where('semester', $setting->semester))
            ->orderBy('start_period')
            ->get();

        // Agenda Sekolah / Kegiatan Hari Ini dari Kalender Kurikulum
        $allTodayEvents = CurriculumEvent::with('users')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->when($setting->academic_year, function ($q) use ($setting) {
                $q->where(function ($sub) use ($setting) {
                    $sub->where('academic_year', $setting->academic_year)
                        ->orWhereNull('academic_year');
                });
            })
            ->orderBy('is_holiday', 'desc')
            ->orderBy('start_date')
            ->get();

        // Kepala sekolah, Waka Kurikulum, & Petugas Piket wajib tahu SELURUH kegiatan yang dilaksanakan di sekolah
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

        $todaySchoolAgenda = $todaySchoolAgendas->first();

        // Status Petugas Piket Hari Ini & Jadwal Piket Mingguan
        $isPiketToday = $user->isPiketOfficerToday();
        $todayPiketSchedule = PiketSchedule::where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->first();

        $myWeeklyPiketSchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->get();

        // Status Wali Kelas & Rombel
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->when($setting->academic_year, function ($q) use ($setting) {
                $q->where(function ($sub) use ($setting) {
                    $sub->where('academic_year', $setting->academic_year)
                        ->orWhereNull('academic_year');
                });
            })
            ->first();

        // Status Izin / Cuti GTK Hari Ini & Riwayat Izin Masih Berlaku (Hanya yang telah disetujui / approved)
        $myGtkPermitToday = GtkPermit::approved()
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->first();

        $myActivePermits = GtkPermit::approved()
            ->where('user_id', $user->id)
            ->whereDate('end_date', '>=', $todayStr)
            ->orderBy('start_date', 'desc')
            ->get();

        // Notifikasi & Tugas Peran Terhubung dengan User (Sesuai Notifikasi Lonceng)
        $userNotifications = NotificationService::getUserNotifications($user);

        // Status sesi jadwal KBM hari ini milik guru yang sudah tersimpan
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

        // Physical Separation: Tampilan Mobile Khusus APK SIAP
        if (DeviceDetector::isMobile()) {
            return view('mobile.dashboard', compact(
                'user',
                'setting',
                'myCheckInToday',
                'myCheckOutToday',
                'todayDay',
                'todaySchoolAgenda',
                'todaySchoolAgendas',
                'allTodayEvents',
                'todayTeachingSchedules',
                'isPiketToday',
                'todayPiketSchedule',
                'myWeeklyPiketSchedules',
                'myHomeroomClassroom',
                'myGtkPermitToday',
                'myActivePermits',
                'userNotifications',
                'mySavedScheduleIds'
            ));
        }

        // Base query for counts & charts based on role (Desktop Web)
        if ($user->isTeacher()) {
            $supervisions = Supervision::with(['teacher', 'supervisor', 'principal'])
                ->where('teacher_id', $user->id)
                ->where('academic_year', $setting->academic_year)
                ->where('semester', $setting->semester)
                ->orderBy('schedule_date', 'desc')
                ->get();

            $totalTeachers = 1;
            $completedCount = $supervisions->whereIn('status', ['disetujui', 'selesai'])->count();
            $pendingActionCount = $supervisions->where('status', 'terjadwal')->count();
            $upcomingSchedules = $supervisions->where('schedule_date', '>=', now()->toDateString())->take(5);
            $recentCompleted = $supervisions->whereIn('status', ['disetujui', 'selesai'])->take(5);

            return view('web.dashboard.guru', compact(
                'user',
                'setting',
                'supervisions',
                'completedCount',
                'pendingActionCount',
                'upcomingSchedules',
                'recentCompleted',
                'myCheckInToday',
                'todayDay',
                'todaySchoolAgenda',
                'todaySchoolAgendas',
                'todayTeachingSchedules',
                'isPiketToday',
                'todayPiketSchedule',
                'myHomeroomClassroom',
                'myGtkPermitToday'
            ));
        }

        // Supervisor specific view
        if ($user->hasRole('supervisor') && ! $user->isWakaKurikulum()) {
            $query = Supervision::with(['teacher', 'supervisor', 'principal'])
                ->where('supervisor_id', $user->id)
                ->where('academic_year', $setting->academic_year)
                ->where('semester', $setting->semester);

            $assignedSupervisions = (clone $query)->orderBy('schedule_date', 'desc')->get();
            $totalAssigned = $assignedSupervisions->count();
            $completedCount = $assignedSupervisions->whereIn('status', ['menunggu_persetujuan', 'disetujui', 'selesai'])->count();
            $inProgressCount = $assignedSupervisions->whereIn('status', ['praobservasi', 'observasi', 'pascaobservasi'])->count();
            $scheduledCount = $assignedSupervisions->where('status', 'terjadwal')->count();

            $upcomingSchedules = (clone $query)
                ->where('schedule_date', '>=', now()->toDateString())
                ->orderBy('schedule_date', 'asc')
                ->take(5)
                ->get();

            return view('web.dashboard.supervisor', compact(
                'user',
                'setting',
                'assignedSupervisions',
                'totalAssigned',
                'completedCount',
                'inProgressCount',
                'scheduledCount',
                'upcomingSchedules',
                'myCheckInToday',
                'todayDay',
                'todaySchoolAgenda',
                'todaySchoolAgendas',
                'todayTeachingSchedules',
                'isPiketToday',
                'todayPiketSchedule',
                'myHomeroomClassroom',
                'myGtkPermitToday'
            ));
        }

        // Admin / Waka Kurikulum / Kepala Sekolah (School-wide view)
        $teachersCount = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))
            ->where('is_active', true)
            ->count();
        $allSupervisions = Supervision::with(['teacher', 'supervisor'])
            ->where('academic_year', $setting->academic_year)
            ->where('semester', $setting->semester)
            ->get();

        $totalSchedules = $allSupervisions->count();
        $completedSupervisions = $allSupervisions->whereIn('status', ['disetujui', 'selesai'])->count();
        $pendingApprovalCount = $allSupervisions->where('status', 'menunggu_persetujuan')->count();
        $inProgressCount = $allSupervisions->whereIn('status', ['praobservasi', 'observasi', 'pascaobservasi'])->count();
        $scheduledCount = $allSupervisions->where('status', 'terjadwal')->count();

        $unsupervisedTeachersCount = max(0, $teachersCount - $completedSupervisions);
        $completionRate = $teachersCount > 0 ? round(($completedSupervisions / $teachersCount) * 100, 1) : 0;

        $averageScore = $completedSupervisions > 0
            ? round($allSupervisions->whereIn('status', ['disetujui', 'selesai'])->avg('percentage'), 1)
            : 0;

        $upcomingSchedules = Supervision::with(['teacher', 'supervisor'])
            ->where('academic_year', $setting->academic_year)
            ->where('semester', $setting->semester)
            ->where('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date', 'asc')
            ->take(5)
            ->get();

        $recentSupervisions = Supervision::with(['teacher', 'supervisor'])
            ->where('academic_year', $setting->academic_year)
            ->where('semester', $setting->semester)
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get();

        // Predicate breakdown
        $predicateCounts = [
            'Sangat Baik' => $allSupervisions->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $allSupervisions->where('predicate', 'Baik')->count(),
            'Cukup' => $allSupervisions->where('predicate', 'Cukup')->count(),
            'Perlu Bimbingan' => $allSupervisions->where('predicate', 'Perlu Bimbingan')->count(),
        ];

        $todayDateStr = now()->toDateString();
        $gtkPermitsToday = GtkPermit::approved()
            ->with('user')
            ->whereDate('start_date', '<=', $todayDateStr)
            ->whereDate('end_date', '>=', $todayDateStr)
            ->get();

        return view('web.dashboard.index', compact(
            'user',
            'setting',
            'teachersCount',
            'totalSchedules',
            'completedSupervisions',
            'pendingApprovalCount',
            'inProgressCount',
            'scheduledCount',
            'unsupervisedTeachersCount',
            'completionRate',
            'averageScore',
            'upcomingSchedules',
            'recentSupervisions',
            'predicateCounts',
            'gtkPermitsToday',
            'myCheckInToday',
            'todayDay',
            'todaySchoolAgenda',
            'todaySchoolAgendas',
            'todayTeachingSchedules',
            'isPiketToday',
            'todayPiketSchedule',
            'myHomeroomClassroom',
            'myGtkPermitToday'
        ));
    }

    public function tasks(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam) : Carbon::today();
        $targetDateStr = $date->toDateString();
        $todayStr = now()->toDateString();
        $dayName = match ($date->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        $prevDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();

        // 1. Data Piket Sekolah
        $isPiketToday = $user->isPiketOfficerToday();
        $todayPiketSchedule = PiketSchedule::where('teacher_id', $user->id)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->first();

        $myWeeklyPiketSchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->get();

        // 2. Data Tugas Perwalian / Wali Kelas
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->when($setting->academic_year, function ($q) use ($setting) {
                $q->where(function ($sub) use ($setting) {
                    $sub->where('academic_year', $setting->academic_year)
                        ->orWhereNull('academic_year');
                });
            })
            ->first();

        $homeroomStudentPermitsTodayCount = 0;
        $homeroomLiveMonitoring = null;
        if ($myHomeroomClassroom) {
            // Semua izin piket untuk rombel ini pada tanggal terpilih
            $homeroomPermits = PiketStudentPermit::with(['student', 'classroom'])
                ->where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('permit_date', $targetDateStr)
                ->orderBy('created_at', 'desc')
                ->get();

            $homeroomLatePermits = $homeroomPermits->where('permit_type', 'terlambat')->unique('student_id')->values();
            $homeroomEarlyLeavePermits = $homeroomPermits->where('permit_type', 'izin_pulang_cepat')->unique('student_id')->values();
            $homeroomIzinPermits = $homeroomPermits->where('permit_type', 'izin_tidak_masuk')->unique('student_id')->values();

            $homeroomStudentPermitsTodayCount = $homeroomPermits->count();

            // Sesi presensi KBM rombel ini pada tanggal terpilih
            $homeroomTodaySessions = SubjectAttendanceSession::where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('attendance_date', $targetDateStr)
                ->pluck('id');

            $homeroomRecordsToday = SubjectAttendanceRecord::with(['student', 'session.classroom', 'session.subject'])
                ->whereIn('subject_attendance_session_id', $homeroomTodaySessions)
                ->get();

            $homeroomTotalStudents = $myHomeroomClassroom->students()->count();
            $homeroomSessionCount = $homeroomTodaySessions->count();

            // Pisahkan izin murni dan sakit
            // 1. Dari Meja Piket: izin tidak masuk yang terindikasi sakit vs izin murni
            $homeroomSakitPermits = $homeroomIzinPermits->filter(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();
            $homeroomMurniIzinPermits = $homeroomIzinPermits->reject(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();

            // 2. Dari Rekap Presensi KBM Mapel: status 's' (sakit), status 'i' (izin), status 'd' (dispensasi)
            $homeroomSakitRecords = $homeroomRecordsToday->where('status', 's')->values();
            $homeroomMurniIzinRecords = $homeroomRecordsToday->where('status', 'i')->values();
            $homeroomDispenRecords = $homeroomRecordsToday->where('status', 'd')->values();
            $homeroomPresentRecords = $homeroomRecordsToday->where('status', 'h')->values();
            $homeroomAlfaRecords = $homeroomRecordsToday->where('status', 'a')->values();

            // Gabungan unik siswa sakit
            $allSakitStudentIds = $homeroomSakitPermits->pluck('student_id')
                ->concat($homeroomSakitRecords->pluck('student_id'))
                ->filter()
                ->unique();
            $totalStudentSakitCount = $allSakitStudentIds->count();

            // Gabungan unik siswa izin murni (tanpa yang sudah masuk sakit)
            $allMurniIzinStudentIds = $homeroomMurniIzinPermits->pluck('student_id')
                ->concat($homeroomMurniIzinRecords->pluck('student_id'))
                ->filter()
                ->diff($allSakitStudentIds)
                ->unique();
            $totalStudentMurniIzinCount = $allMurniIzinStudentIds->count();

            // Gabungan unik siswa dispensasi
            $allDispenStudentIds = $homeroomDispenRecords->pluck('student_id')
                ->filter()
                ->diff($allSakitStudentIds)
                ->diff($allMurniIzinStudentIds)
                ->unique();
            $totalStudentDispenCount = $allDispenStudentIds->count();

            // Deteksi Bolos KBM rombel ini
            $studentFingerprintLogs = AttendanceLog::where('user_type', 'student')
                ->whereDate('scanned_at', $targetDateStr)
                ->pluck('student_id')
                ->filter()
                ->unique();

            $earlyLeaveStudentIds = $homeroomEarlyLeavePermits->pluck('student_id')->filter()->unique();
            $recordsByStudent = $homeroomRecordsToday->groupBy('student_id');
            $truancyStudents = collect();

            foreach ($recordsByStudent as $sId => $records) {
                if ($earlyLeaveStudentIds->contains($sId)) {
                    continue;
                }

                $hasPresentSession = $records->contains('status', 'h');
                $hasAlfaSession = $records->contains('status', 'a');
                $hasFingerprint = $studentFingerprintLogs->contains($sId);

                if (($hasPresentSession && $hasAlfaSession) || ($hasFingerprint && $hasAlfaSession)) {
                    $firstRecord = $records->first();
                    $studentModel = $firstRecord->student;
                    $alfaSubjects = $records->where('status', 'a')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');
                    $presentSubjects = $records->where('status', 'h')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');

                    $truancyStudents->push((object) [
                        'student_id' => $sId,
                        'student_name' => $studentModel?->name ?? 'Siswa',
                        'classroom_name' => $myHomeroomClassroom->name,
                        'alfa_count_jp' => $records->where('status', 'a')->count(),
                        'alfa_subjects' => $alfaSubjects ?: 'Mapel Tertentu',
                        'present_subjects' => $presentSubjects ?: ($hasFingerprint ? 'Scan Masuk Gerbang' : '-'),
                        'has_fingerprint' => $hasFingerprint,
                    ]);
                }
            }

            $truancyStudentIds = $truancyStudents->pluck('student_id');
            $pureAlfaRecords = $homeroomAlfaRecords->reject(fn ($r) => $truancyStudentIds->contains($r->student_id))->values();
            $pureAlfaCount = $pureAlfaRecords->pluck('student_id')->unique()->count();

            // Kehadiran terkini per siswa (status sesi terakhir yang diabsen)
            $latestStatusPerStudent = $homeroomRecordsToday
                ->sortByDesc('subject_attendance_session_id')
                ->unique('student_id');

            $homeroomHadirCount = $latestStatusPerStudent->where('status', 'h')->count();

            $attendanceRate = $homeroomTotalStudents > 0 && $homeroomSessionCount > 0
                ? round(($homeroomHadirCount / $homeroomTotalStudents) * 100, 1)
                : null;

            // Kejadian / Pelanggaran Siswa Rombel Ini dari Piket & BK pada Tanggal Terpilih
            $homeroomIncidents = PiketIncident::with(['student', 'classroom', 'recordedBy'])
                ->where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('incident_date', $targetDateStr)
                ->orderBy('incident_time', 'desc')
                ->get();

            // Kompilasi Log Kejadian Harian Siswa Terpadu (Kronologis Waktu)
            $homeroomStudentEvents = collect();

            // 1. Kejadian Kasus & Catatan Piket/BK
            foreach ($homeroomIncidents as $inc) {
                $homeroomStudentEvents->push([
                    'type' => 'incident',
                    'time' => substr($inc->incident_time, 0, 5),
                    'student_name' => $inc->student?->name ?? $inc->student_name,
                    'title' => $inc->category_label ?? 'Kejadian / Pelanggaran Siswa',
                    'badge' => $inc->follow_up_label ?? 'Kasus Piket',
                    'badge_class' => in_array($inc->follow_up_target, ['rujuk_bk', 'rujuk_kesiswaan']) ? 'bg-danger text-white' : 'bg-warning-lt text-warning',
                    'description' => $inc->description,
                    'action' => $inc->action_taken,
                    'note' => $inc->confiscated_items ? 'Sitaan: '.$inc->confiscated_items : null,
                    'officer' => $inc->recordedBy?->name ?? 'Petugas Piket',
                    'photo' => $inc->evidence_photo_url,
                ]);
            }

            // 2. Kejadian Siswa Terlambat
            foreach ($homeroomLatePermits as $lp) {
                $homeroomStudentEvents->push([
                    'type' => 'late',
                    'time' => $lp->created_at ? $lp->created_at->format('H:i') : '-',
                    'student_name' => $lp->student?->name ?? 'Siswa',
                    'title' => 'Terlambat Masuk Sekolah',
                    'badge' => 'Jam ke-'.($lp->period_assigned ?? '-'),
                    'badge_class' => 'bg-warning text-dark',
                    'description' => $lp->reason ?? 'Terlambat masuk kelas',
                    'action' => 'Diberikan surat izin masuk oleh piket',
                    'note' => null,
                    'officer' => $lp->giver?->name ?? 'Petugas Piket',
                    'photo' => null,
                ]);
            }

            // 3. Kejadian Siswa Izin Pulang Cepat
            foreach ($homeroomEarlyLeavePermits as $ep) {
                $homeroomStudentEvents->push([
                    'type' => 'pulang',
                    'time' => $ep->created_at ? $ep->created_at->format('H:i') : '-',
                    'student_name' => $ep->student?->name ?? 'Siswa',
                    'title' => 'Izin Pulang Lebih Awal',
                    'badge' => 'Pulang Cepat',
                    'badge_class' => 'bg-purple text-white',
                    'description' => $ep->reason ?? 'Izin pulang lebih awal',
                    'action' => 'Diberikan surat izin pulang oleh piket',
                    'note' => null,
                    'officer' => $ep->giver?->name ?? 'Petugas Piket',
                    'photo' => null,
                ]);
            }

            // 4. Kejadian Siswa Bolos KBM
            foreach ($truancyStudents as $ts) {
                $homeroomStudentEvents->push([
                    'type' => 'truancy',
                    'time' => '-',
                    'student_name' => $ts->student_name,
                    'title' => 'Indikasi Bolos Pelajaran',
                    'badge' => $ts->alfa_count_jp.' JP Alfa',
                    'badge_class' => 'bg-dark text-white',
                    'description' => 'Alfa di: '.$ts->alfa_subjects,
                    'action' => 'Terdeteksi di: '.$ts->present_subjects,
                    'note' => null,
                    'officer' => 'Sistem KBM',
                    'photo' => null,
                ]);
            }

            // Urutkan kronologis
            $homeroomStudentEvents = $homeroomStudentEvents->sortByDesc('time')->values();

            $homeroomLiveMonitoring = [
                'total_students' => $homeroomTotalStudents,
                'session_count' => $homeroomSessionCount,
                'hadir_count' => $homeroomHadirCount,
                'late_count' => $homeroomLatePermits->count(),
                'sakit_count' => $totalStudentSakitCount,
                'izin_count' => $totalStudentMurniIzinCount,
                'dispen_count' => $totalStudentDispenCount,
                'pulang_count' => $homeroomEarlyLeavePermits->count(),
                'alfa_count' => $pureAlfaCount,
                'truancy_count' => $truancyStudents->count(),
                'truancy_list' => $truancyStudents,
                'attendance_rate' => $attendanceRate,
                'present_records' => $homeroomPresentRecords,
                'sakit_permits' => $homeroomSakitPermits,
                'sakit_records' => $homeroomSakitRecords,
                'izin_permits' => $homeroomMurniIzinPermits,
                'izin_records' => $homeroomMurniIzinRecords,
                'dispen_records' => $homeroomDispenRecords,
                'late_permits' => $homeroomLatePermits,
                'pulang_permits' => $homeroomEarlyLeavePermits,
                'alfa_records' => $pureAlfaRecords,
                'incidents' => $homeroomIncidents,
                'student_events' => $homeroomStudentEvents,
            ];
        }

        // 3. Data Administrasi Izin / Cuti GTK Mandiri (Untuk guru/staf biasa)
        $myRecentPermits = GtkPermit::where('user_id', $user->id)
            ->latest('start_date')
            ->take(5)
            ->get();

        $myGtkPermitToday = GtkPermit::approved()
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->first();

        // 4. Data Persetujuan & Monitoring Izin GTK (Kepala Sekolah & Waka Kurikulum)
        $pendingGtkPermits = collect();
        $todayApprovedGtkPermits = collect();
        if ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin()) {
            $pendingGtkPermits = GtkPermit::with('user')
                ->pending()
                ->latest('created_at')
                ->take(10)
                ->get();

            $todayApprovedGtkPermits = GtkPermit::with('user')
                ->approved()
                ->whereDate('start_date', '<=', $todayStr)
                ->whereDate('end_date', '>=', $todayStr)
                ->orderBy('start_date')
                ->take(10)
                ->get();
        }

        // 5. Data Pengesahan Supervisi Akademik (Khusus Kepala Sekolah, Waka Kurikulum & Super Admin)
        $pendingSupervisions = collect();
        if ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision')) {
            $pendingSupervisions = Supervision::with(['teacher', 'supervisor'])
                ->where('status', 'menunggu_persetujuan')
                ->orderBy('obs_date', 'desc')
                ->take(10)
                ->get();
        }

        if (DeviceDetector::isMobile()) {
            return view('mobile.tasks', compact(
                'user',
                'setting',
                'date',
                'dayName',
                'prevDate',
                'nextDate',
                'isPiketToday',
                'todayPiketSchedule',
                'myWeeklyPiketSchedules',
                'myHomeroomClassroom',
                'homeroomStudentPermitsTodayCount',
                'homeroomLiveMonitoring',
                'myRecentPermits',
                'myGtkPermitToday',
                'pendingGtkPermits',
                'todayApprovedGtkPermits',
                'pendingSupervisions'
            ));
        }

        return redirect()->route('dashboard');
    }
}
