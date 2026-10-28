<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AcademicYearContext;
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

        // Status Kehadiran GPS / Mesin hari ini (Masuk & Pulang)
        $todayAttendanceLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $todayStr)
            ->orderBy('scanned_at')
            ->get();
        $myCheckInToday = $todayAttendanceLogs->first();
        $myCheckOutToday = $todayAttendanceLogs->count() > 1 ? $todayAttendanceLogs->last() : null;

        // Jadwal Mengajar Guru Khusus Hari Ini (Operasional Aktif)
        $todayTeachingSchedules = LessonSchedule::with(['classroom', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('academic_year', $operationalAcademicYear)
            ->where('semester', $operationalSemester)
            ->orderBy('start_period')
            ->get();

        // Agenda Sekolah / Kegiatan Hari Ini dari Kalender Kurikulum (Operasional Aktif)
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

        // Status Petugas Piket Hari Ini & Jadwal Piket Mingguan (Operasional Aktif)
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

        // Status Wali Kelas & Rombel (Operasional Aktif)
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->where(function ($sub) use ($operationalAcademicYear, $operationalAyModel) {
                if ($operationalAyModel) {
                    $sub->where('classrooms.academic_year_id', $operationalAyModel->id);
                }
                $sub->orWhere('classrooms.academic_year', $operationalAcademicYear)
                    ->orWhereNull('classrooms.academic_year');
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

        // Admin / Waka Kurikulum / Kepala Sekolah (Executive School-Wide Command Center)
        $todayDateStr = now()->toDateString();

        // 1. GTK Presensi & Kehadiran Hari Ini
        $operationalGtk = User::operationalPersonnel()->where('is_active', true)->get();
        $totalGtkCount = $operationalGtk->count();
        $todayGtkPresentUserIds = AttendanceLog::where('user_type', 'teacher')
            ->whereDate('scanned_at', $todayDateStr)
            ->pluck('user_id')
            ->unique();
        $gtkPresentCount = $todayGtkPresentUserIds->count();

        $gtkPermitsToday = GtkPermit::approved()
            ->with('user')
            ->whereDate('start_date', '<=', $todayDateStr)
            ->whereDate('end_date', '>=', $todayDateStr)
            ->get();
        $gtkPermitsTodayCount = $gtkPermitsToday->count();
        $gtkUnscannedCount = max(0, $totalGtkCount - $gtkPresentCount - $gtkPermitsTodayCount);

        // 2. KBM Rombel & Jadwal Mengajar Hari Ini
        $activeClassrooms = Classroom::where(function ($query) use ($operationalAcademicYear, $operationalAyModel) {
            if ($operationalAyModel) {
                $query->where('academic_year_id', $operationalAyModel->id);
            }
            $query->orWhere('academic_year', $operationalAcademicYear);
        })
            ->orderBy('name')
            ->get();
        $totalClassroomsCount = $activeClassrooms->count();

        $periodTimes = LessonSchedule::periodTimes($todayDay);
        $nowTime = now()->format('H:i');
        $detectedPeriod = null;
        $detectedPeriodLabel = 'Di Luar Jam KBM';
        foreach ($periodTimes as $p => $times) {
            if ($nowTime >= $times['start'] && $nowTime <= $times['end']) {
                $detectedPeriod = $p;
                $detectedPeriodLabel = "Jam ke-{$p} ({$times['start']} - {$times['end']} WIB)";
                break;
            }
        }
        if (! $detectedPeriod) {
            if ($nowTime < ($periodTimes[1]['start'] ?? '07:00')) {
                $detectedPeriodLabel = 'Belum Dimulai (Sebelum Jam ke-1)';
            } elseif ($nowTime > (end($periodTimes)['end'] ?? '15:15')) {
                $detectedPeriodLabel = 'KBM Telah Berakhir Hari Ini';
            } else {
                $detectedPeriodLabel = 'Waktu Istirahat / Antar Jam';
            }
        }

        $todayAllSchedules = LessonSchedule::with(['classroom', 'subject', 'teacher'])
            ->where('day', $todayDay)
            ->where('academic_year', $operationalAcademicYear)
            ->where('semester', $operationalSemester)
            ->orderBy('start_period')
            ->get();
        $todayTotalSchedulesCount = $todayAllSchedules->count();

        if ($detectedPeriod) {
            $currentPeriodSchedules = $todayAllSchedules->filter(fn ($s) => $s->start_period <= $detectedPeriod && $s->end_period >= $detectedPeriod)->values();
        } else {
            $currentPeriodSchedules = $todayAllSchedules->take(8);
        }

        // 3. Kesiswaan & Demografi Siswa
        $totalStudentsCount = Student::count();
        $activeStudentsCount = Student::where('status', 'aktif')->count();
        $alumniStudentsCount = Student::where('status', 'lulus')->count();
        $mutatedStudentsCount = Student::whereIn('status', ['mutasi', 'keluar'])->count();

        $studentsByGrade = Student::where('students.status', 'aktif')
            ->join('classrooms', 'students.classroom_id', '=', 'classrooms.id')
            ->join('grade_levels', 'classrooms.grade_level_id', '=', 'grade_levels.id')
            ->selectRaw('grade_levels.level, count(*) as count')
            ->groupBy('grade_levels.level')
            ->pluck('count', 'grade_levels.level');

        $studentsByDept = Student::where('students.status', 'aktif')
            ->join('classrooms', 'students.classroom_id', '=', 'classrooms.id')
            ->join('departments', 'classrooms.department_id', '=', 'departments.id')
            ->selectRaw('departments.code, count(*) as count')
            ->groupBy('departments.code')
            ->pluck('count', 'departments.code');

        // 4. Piket & Ketertiban Hari Ini
        $todayPiketOfficers = PiketSchedule::with('teacher')
            ->where('day', $todayDay)
            ->where('academic_year', $operationalAcademicYear)
            ->where('is_active', true)
            ->get();
        $todayStudentPermits = PiketStudentPermit::with(['student.classroom'])
            ->whereDate('permit_date', $todayDateStr)
            ->latest()
            ->get();
        $todayStudentPermitsCount = $todayStudentPermits->count();
        $todayIncidents = PiketIncident::with(['student.classroom'])
            ->whereDate('incident_date', $todayDateStr)
            ->latest()
            ->get();
        $todayIncidentsCount = $todayIncidents->count();

        // 5. Agenda & Kalender Kegiatan Sekolah Terdekat
        $upcomingSchoolEvents = CurriculumEvent::whereDate('end_date', '>=', $todayDateStr)
            ->where(function ($sub) use ($operationalAcademicYear) {
                $sub->where('academic_year', $operationalAcademicYear)
                    ->orWhereNull('academic_year');
            })
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get();

        // 6. Pusat Tindakan & Persetujuan (Pending Approvals)
        $pendingGtkPermits = GtkPermit::where('status', 'pending')
            ->with('user')
            ->latest()
            ->get();
        $totalPendingActionsCount = $pendingGtkPermits->count();

        // Safe fallback metrics
        $teachersCount = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))
            ->where('is_active', true)
            ->count();
        $allSupervisions = collect();
        $totalSchedules = 0;
        $completedSupervisions = 0;
        $pendingApprovalCount = 0;
        $inProgressCount = 0;
        $scheduledCount = 0;
        $unsupervisedTeachersCount = $teachersCount;
        $completionRate = 0;
        $averageScore = 0;
        $upcomingSchedules = collect();
        $recentSupervisions = collect();
        $predicateCounts = ['Sangat Baik' => 0, 'Baik' => 0, 'Cukup' => 0, 'Perlu Bimbingan' => 0];

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
            'myGtkPermitToday',
            'totalGtkCount',
            'gtkPresentCount',
            'gtkPermitsTodayCount',
            'gtkUnscannedCount',
            'totalClassroomsCount',
            'activeClassrooms',
            'periodTimes',
            'detectedPeriod',
            'detectedPeriodLabel',
            'todayTotalSchedulesCount',
            'todayAllSchedules',
            'currentPeriodSchedules',
            'totalStudentsCount',
            'activeStudentsCount',
            'alumniStudentsCount',
            'mutatedStudentsCount',
            'studentsByGrade',
            'studentsByDept',
            'todayPiketOfficers',
            'todayStudentPermits',
            'todayStudentPermitsCount',
            'todayIncidents',
            'todayIncidentsCount',
            'pendingGtkPermits',
            'totalPendingActionsCount',
            'upcomingSchoolEvents'
        ));
    }

    public function tasks(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        $academicYearContext = app(AcademicYearContext::class);
        $operationalYear = $academicYearContext->getOperationalYear();
        $operationalAcademicYear = $operationalYear->name;
        $operationalAyModel = AcademicYear::where('name', $operationalAcademicYear)->first();

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

        // 1. Data Piket Sekolah (Operasional Aktif)
        $isPiketToday = $user->isPiketOfficerToday();
        $todayPiketSchedule = PiketSchedule::where('teacher_id', $user->id)
            ->where('day', $dayName)
            ->where('academic_year', $operationalAcademicYear)
            ->where('is_active', true)
            ->first();

        $myWeeklyPiketSchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('academic_year', $operationalAcademicYear)
            ->where('is_active', true)
            ->get();

        // 2. Data Tugas Perwalian / Wali Kelas (Operasional Aktif)
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->where(function ($sub) use ($operationalAcademicYear, $operationalAyModel) {
                if ($operationalAyModel) {
                    $sub->where('classrooms.academic_year_id', $operationalAyModel->id);
                }
                $sub->orWhere('classrooms.academic_year', $operationalAcademicYear)
                    ->orWhereNull('classrooms.academic_year');
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

        // 6. Data Visitasi Siswa & Pemantauan Khusus (BK & Wali Kelas)
        $canAccessVisitations = $user->canAccessVisitations($operationalAcademicYear);
        $myPendingVisitationsCount = 0;
        $myRecentVisitations = collect();
        if ($canAccessVisitations) {
            $visitationQuery = StudentVisitation::with(['student.classroom', 'primaryOfficer'])
                ->where(function ($q) use ($user) {
                    $q->where('primary_officer_id', $user->id)
                        ->orWhere('secondary_officer_id', $user->id)
                        ->orWhere('requested_by', $user->id);
                });

            $myPendingVisitationsCount = (clone $visitationQuery)
                ->whereIn('status', ['diajukan', 'dijadwalkan'])
                ->count();

            $myRecentVisitations = (clone $visitationQuery)
                ->latest('planned_date')
                ->take(3)
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
                'pendingSupervisions',
                'canAccessVisitations',
                'myPendingVisitationsCount',
                'myRecentVisitations'
            ));
        }

        return redirect()->route('dashboard');
    }
}
