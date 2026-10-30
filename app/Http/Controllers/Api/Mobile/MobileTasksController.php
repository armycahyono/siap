<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\Classroom;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketDutySwap;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\ActivityLogger;
use App\Services\LiveMonitoringService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MobileTasksController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService,
        protected LiveMonitoringService $liveMonitoringService
    ) {}

    /**
     * Get operational tasks and live monitoring payload for SIAP Mobile.
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

        // 1. Data Piket Sekolah
        $canAccessPiket = $user->canAccessPiket($operationalAcademicYear)
            || $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum());
        $isManagement = $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum());

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

        // 2. Data Tugas Perwalian / Wali Kelas
        $myHomeroomClassroom = $user->homeroomClassrooms()
            ->where(function ($sub) use ($operationalAcademicYear, $operationalAyModel) {
                if ($operationalAyModel) {
                    $sub->where('classrooms.academic_year_id', $operationalAyModel->id);
                }
                $sub->orWhere('classrooms.academic_year', $operationalAcademicYear)
                    ->orWhereNull('classrooms.academic_year');
            })
            ->first();

        $homeroomLiveMonitoring = null;
        if ($myHomeroomClassroom) {
            $homeroomPermits = PiketStudentPermit::with(['student', 'classroom'])
                ->where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('permit_date', $targetDateStr)
                ->orderBy('created_at', 'desc')
                ->get();

            $homeroomLatePermits = $homeroomPermits->where('permit_type', 'terlambat')->unique('student_id')->values();
            $homeroomEarlyLeavePermits = $homeroomPermits->where('permit_type', 'izin_pulang_cepat')->unique('student_id')->values();
            $homeroomIzinPermits = $homeroomPermits->where('permit_type', 'izin_tidak_masuk')->unique('student_id')->values();

            $homeroomTodaySessions = SubjectAttendanceSession::where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('attendance_date', $targetDateStr)
                ->pluck('id');

            $homeroomRecordsToday = SubjectAttendanceRecord::with(['student', 'session.classroom', 'session.subject'])
                ->whereIn('subject_attendance_session_id', $homeroomTodaySessions)
                ->get();

            $homeroomTotalStudents = $myHomeroomClassroom->students()->count();
            $homeroomSessionCount = $homeroomTodaySessions->count();

            $homeroomSakitPermits = $homeroomIzinPermits->filter(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();

            $homeroomMurniIzinPermits = $homeroomIzinPermits->reject(function ($p) {
                return strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit');
            })->values();

            $homeroomSakitRecords = $homeroomRecordsToday->where('status', 's')->values();
            $homeroomMurniIzinRecords = $homeroomRecordsToday->where('status', 'i')->values();
            $homeroomDispenRecords = $homeroomRecordsToday->where('status', 'd')->values();
            $homeroomAlfaRecords = $homeroomRecordsToday->where('status', 'a')->values();

            $allSakitStudentIds = $homeroomSakitPermits->pluck('student_id')
                ->concat($homeroomSakitRecords->pluck('student_id'))
                ->filter()
                ->unique();
            $totalStudentSakitCount = $allSakitStudentIds->count();

            $allMurniIzinStudentIds = $homeroomMurniIzinPermits->pluck('student_id')
                ->concat($homeroomMurniIzinRecords->pluck('student_id'))
                ->filter()
                ->diff($allSakitStudentIds)
                ->unique();
            $totalStudentMurniIzinCount = $allMurniIzinStudentIds->count();

            $allDispenStudentIds = $homeroomDispenRecords->pluck('student_id')
                ->filter()
                ->diff($allSakitStudentIds)
                ->diff($allMurniIzinStudentIds)
                ->unique();
            $totalStudentDispenCount = $allDispenStudentIds->count();

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

                    $truancyStudents->push([
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

            $latestStatusPerStudent = $homeroomRecordsToday
                ->sortByDesc('subject_attendance_session_id')
                ->unique('student_id');

            $homeroomHadirCount = $latestStatusPerStudent->where('status', 'h')->count();

            $attendanceRate = $homeroomTotalStudents > 0 && $homeroomSessionCount > 0
                ? round(($homeroomHadirCount / $homeroomTotalStudents) * 100, 1)
                : null;

            $homeroomIncidents = PiketIncident::with(['student', 'classroom', 'recordedBy'])
                ->where('classroom_id', $myHomeroomClassroom->id)
                ->whereDate('incident_date', $targetDateStr)
                ->orderBy('incident_time', 'desc')
                ->get();

            $homeroomStudentEvents = collect();

            foreach ($homeroomIncidents as $inc) {
                $homeroomStudentEvents->push([
                    'type' => 'incident',
                    'time' => substr($inc->incident_time, 0, 5),
                    'student_name' => $inc->student?->name ?? $inc->student_name,
                    'title' => $inc->category_label ?? 'Kejadian / Pelanggaran Siswa',
                    'badge' => $inc->follow_up_label ?? 'Kasus Piket',
                    'badge_class' => in_array($inc->follow_up_target, ['rujuk_bk', 'rujuk_kesiswaan']) ? 'danger' : 'warning',
                    'description' => $inc->description,
                    'action' => $inc->action_taken,
                    'note' => $inc->confiscated_items ? 'Sitaan: '.$inc->confiscated_items : null,
                    'officer' => $inc->recordedBy?->name ?? 'Petugas Piket',
                    'photo' => $inc->evidence_photo_url,
                ]);
            }

            foreach ($homeroomLatePermits as $lp) {
                $homeroomStudentEvents->push([
                    'type' => 'late',
                    'time' => $lp->created_at ? $lp->created_at->format('H:i') : '-',
                    'student_name' => $lp->student?->name ?? 'Siswa',
                    'title' => 'Terlambat Masuk Sekolah',
                    'badge' => 'Jam ke-'.($lp->period_assigned ?? '-'),
                    'badge_class' => 'warning',
                    'description' => $lp->reason ?? 'Terlambat masuk kelas',
                    'action' => 'Diberikan surat izin masuk oleh piket',
                    'note' => null,
                    'officer' => $lp->giver?->name ?? 'Petugas Piket',
                    'photo' => null,
                ]);
            }

            foreach ($homeroomEarlyLeavePermits as $ep) {
                $homeroomStudentEvents->push([
                    'type' => 'pulang',
                    'time' => $ep->created_at ? $ep->created_at->format('H:i') : '-',
                    'student_name' => $ep->student?->name ?? 'Siswa',
                    'title' => 'Izin Pulang Lebih Awal',
                    'badge' => 'Pulang Cepat',
                    'badge_class' => 'purple',
                    'description' => $ep->reason ?? 'Izin pulang lebih awal',
                    'action' => 'Diberikan surat izin pulang oleh piket',
                    'note' => null,
                    'officer' => $ep->giver?->name ?? 'Petugas Piket',
                    'photo' => null,
                ]);
            }

            foreach ($truancyStudents as $ts) {
                $homeroomStudentEvents->push([
                    'type' => 'truancy',
                    'time' => '-',
                    'student_name' => $ts['student_name'],
                    'title' => 'Indikasi Bolos Pelajaran',
                    'badge' => $ts['alfa_count_jp'].' JP Alfa',
                    'badge_class' => 'dark',
                    'description' => 'Alfa di: '.$ts['alfa_subjects'],
                    'action' => 'Terdeteksi di: '.$ts['present_subjects'],
                    'note' => null,
                    'officer' => 'Sistem KBM',
                    'photo' => null,
                ]);
            }

            $homeroomStudentEvents = $homeroomStudentEvents->sortByDesc('time')->values();

            $homeroomLiveMonitoring = [
                'classroom' => [
                    'id' => $myHomeroomClassroom->id,
                    'name' => $myHomeroomClassroom->name,
                ],
                'summary' => [
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
                    'attendance_rate' => $attendanceRate,
                ],
                'truancy_list' => $truancyStudents->values(),
                'student_events' => $homeroomStudentEvents->values(),
            ];
        }

        // 3. Izin Mandiri GTK
        $myRecentPermits = GtkPermit::where('user_id', $user->id)
            ->latest('start_date')
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'permit_type' => $p->permit_type,
                'status' => $p->status,
                'reason' => $p->reason,
                'start_date' => $p->start_date?->format('Y-m-d'),
                'end_date' => $p->end_date?->format('Y-m-d'),
                'created_at' => $p->created_at?->format('d M Y H:i'),
            ]);

        $myGtkPermitToday = GtkPermit::approved()
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->first();

        // 4. Persetujuan Izin (Kepsek & Waka)
        $pendingGtkPermits = collect();
        $todayApprovedGtkPermits = collect();
        if ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin()) {
            $pendingGtkPermits = GtkPermit::with('user')
                ->pending()
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'user_name' => $p->user?->name ?? 'GTK',
                    'permit_type' => $p->permit_type,
                    'reason' => $p->reason,
                    'start_date' => $p->start_date?->format('Y-m-d'),
                    'end_date' => $p->end_date?->format('Y-m-d'),
                    'days_count' => $p->start_date && $p->end_date ? $p->start_date->diffInDays($p->end_date) + 1 : 1,
                ]);

            $todayApprovedGtkPermits = GtkPermit::with('user')
                ->approved()
                ->whereDate('start_date', '<=', $todayStr)
                ->whereDate('end_date', '>=', $todayStr)
                ->orderBy('start_date')
                ->take(10)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'user_name' => $p->user?->name ?? 'GTK',
                    'permit_type' => $p->permit_type,
                    'reason' => $p->reason,
                    'start_date' => $p->start_date?->format('Y-m-d'),
                    'end_date' => $p->end_date?->format('Y-m-d'),
                ]);
        }

        // 5. Supervisi Akademik
        $pendingSupervisions = collect();
        if ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision')) {
            $pendingSupervisions = Supervision::with(['teacher', 'supervisor'])
                ->where('status', 'menunggu_persetujuan')
                ->orderBy('obs_date', 'desc')
                ->take(10)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'teacher_name' => $s->teacher?->name ?? 'Guru',
                    'supervisor_name' => $s->supervisor?->name ?? 'Supervisor',
                    'obs_date' => $s->obs_date?->format('d M Y'),
                    'status' => $s->status,
                ]);
        }

        // 6. Visitasi Siswa
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
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'student_name' => $v->student?->name ?? 'Siswa',
                    'classroom_name' => $v->student?->classroom?->name ?? '-',
                    'purpose' => $v->purpose,
                    'planned_date' => $v->planned_date?->format('d M Y'),
                    'status' => $v->status,
                ]);
        }

        // 7. Layanan Bimbingan Konseling (BK)
        $bkData = $this->buildBkSummaryData($user, $operationalAcademicYear);

        // 8. Tim Kedisiplinan Siswa
        $disciplineData = null;
        if ($user->canManageDiscipline($operationalAcademicYear)) {
            $baseIncidentQuery = PiketIncident::forAcademicYear($operationalAcademicYear);

            $todayIncidentsCount = (clone $baseIncidentQuery)
                ->whereDate('incident_date', $todayStr)
                ->count();

            $waitingVerificationCount = (clone $baseIncidentQuery)
                ->where(function ($q) {
                    $q->where('verification_status', 'menunggu_verifikasi')
                        ->orWhere('status', 'tercatat');
                })
                ->count();

            $followUpCount = (clone $baseIncidentQuery)
                ->whereIn('follow_up_target', ['rujuk_bk', 'rujuk_kesiswaan', 'rujuk_wali_kelas'])
                ->whereIn('status', ['tercatat', 'proses'])
                ->count();

            $activeMonitoringCount = (clone $baseIncidentQuery)
                ->where('status', 'proses')
                ->count();

            $actionRequiredIncidents = (clone $baseIncidentQuery)
                ->with(['student.classroom', 'classroom', 'recordedBy'])
                ->where(function ($q) {
                    $q->where('verification_status', 'menunggu_verifikasi')
                        ->orWhere('status', 'tercatat')
                        ->orWhereNotNull('confiscated_items');
                })
                ->latest('incident_date')
                ->latest('incident_time')
                ->take(6)
                ->get()
                ->map(function ($inc) {
                    return [
                        'id' => $inc->id,
                        'student_name' => $inc->student?->name ?? $inc->student_name ?? 'Siswa',
                        'classroom_name' => $inc->classroom?->name ?? $inc->student?->classroom?->name ?? '-',
                        'incident_date' => $inc->incident_date?->format('d M Y') ?? '-',
                        'incident_time' => $inc->incident_time ? substr($inc->incident_time, 0, 5) : '-',
                        'category' => $inc->category,
                        'category_label' => $inc->category_label ?? ucfirst($inc->category),
                        'penalty_points' => $inc->penalty_points ?? 0,
                        'description' => $inc->description,
                        'action_taken' => $inc->action_taken,
                        'confiscated_items' => $inc->confiscated_items,
                        'verification_status' => $inc->verification_status ?? 'menunggu_verifikasi',
                        'verification_status_label' => $inc->verification_status_label ?? 'Menunggu',
                        'recorded_by_name' => $inc->recordedBy?->name ?? 'Piket',
                        'evidence_photo_url' => $inc->evidence_photo_url,
                    ];
                });

            $disciplineData = [
                'can_access' => true,
                'summary' => [
                    'today_count' => $todayIncidentsCount,
                    'waiting_verification_count' => $waitingVerificationCount,
                    'follow_up_count' => $followUpCount,
                    'active_monitoring_count' => $activeMonitoringCount,
                ],
                'action_required_incidents' => $actionRequiredIncidents->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'target_date' => [
                    'current' => $targetDateStr,
                    'day_name' => $dayName,
                    'prev' => $prevDate,
                    'next' => $nextDate,
                    'is_today' => $targetDateStr === $todayStr,
                ],
                'piket' => [
                    'can_access' => $canAccessPiket,
                    'is_management' => $isManagement,
                    'is_piket_today' => $isPiketToday,
                    'today_schedule' => $todayPiketSchedule ? [
                        'day' => $todayPiketSchedule->day,
                        'notes' => $todayPiketSchedule->notes,
                    ] : null,
                    'weekly_days' => $myWeeklyPiketSchedules->pluck('day')->unique()->values(),
                ],
                'homeroom' => $homeroomLiveMonitoring,
                'gtk_permits' => [
                    'my_permit_today' => $myGtkPermitToday ? [
                        'type' => $myGtkPermitToday->permit_type,
                        'reason' => $myGtkPermitToday->reason,
                        'start_date' => $myGtkPermitToday->start_date?->format('Y-m-d'),
                        'end_date' => $myGtkPermitToday->end_date?->format('Y-m-d'),
                    ] : null,
                    'my_recent' => $myRecentPermits->values(),
                    'pending_approval' => $pendingGtkPermits->values(),
                    'today_approved' => $todayApprovedGtkPermits->values(),
                ],
                'supervisions' => [
                    'pending' => $pendingSupervisions->values(),
                ],
                'visitations' => [
                    'can_access' => $canAccessVisitations,
                    'pending_count' => $myPendingVisitationsCount,
                    'recent' => $myRecentVisitations->values(),
                ],
                'bk' => $bkData,
                'discipline' => $disciplineData,
                'roles_operational' => [
                    'can_manage_discipline' => $user->canManageDiscipline(),
                    'is_waka_kesiswaan' => $user->isWakaKesiswaan(),
                    'can_operate_bk' => $user->canOperateBk(),
                ],
                'live_monitoring' => $this->buildLiveMonitoringData($user, $date, $operationalAcademicYear, $myHomeroomClassroom),
            ],
        ]);
    }

    /**
     * Bangun data ringkasan dan rujukan Layanan BK untuk kartu mobile.
     *
     * @return array<string, mixed>|null
     */
    public function buildBkSummaryData(User $user, string $operationalAcademicYear): ?array
    {
        if (! $user->canOperateBk($operationalAcademicYear)) {
            return null;
        }

        $baseBkQuery = PiketIncident::where('follow_up_target', 'rujuk_bk')
            ->where(function ($q) use ($operationalAcademicYear) {
                $q->where('academic_year', $operationalAcademicYear)
                    ->orWhereHas('journal', fn ($j) => $j->where('academic_year', $operationalAcademicYear))
                    ->orWhereHas('classroom', fn ($c) => $c->where('academic_year', $operationalAcademicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $operationalAcademicYear)))
                    ->orWhereHas('student.studentClassrooms', fn ($sc) => $sc->where('academic_year_name', $operationalAcademicYear)->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $operationalAcademicYear)));
            });

        $totalRujukan = (clone $baseBkQuery)->count();
        $totalMenunggu = (clone $baseBkQuery)->where(function ($q) {
            $q->doesntHave('bkReferral')
                ->orWhereHas('bkReferral', fn ($r) => $r->where('status', 'menunggu'));
        })->count();
        $totalDiproses = (clone $baseBkQuery)->whereHas('bkReferral', fn ($r) => $r->where('status', 'diproses'))->count();
        $totalSelesai = (clone $baseBkQuery)->whereHas('bkReferral', fn ($r) => $r->where('status', 'selesai'))->count();

        $recentReferrals = (clone $baseBkQuery)
            ->with(['student.classroom', 'classroom', 'recordedBy', 'bkReferral.handler'])
            ->latest('incident_date')
            ->latest('incident_time')
            ->take(6)
            ->get()
            ->map(function ($inc) {
                $refStatus = $inc->bkReferral?->status ?? 'menunggu';
                $statusLabel = match ($refStatus) {
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                    default => 'Menunggu',
                };

                return [
                    'id' => $inc->id,
                    'student_name' => $inc->student?->name ?? $inc->student_name ?? 'Siswa',
                    'classroom_name' => $inc->classroom?->name ?? $inc->student?->classroom?->name ?? '-',
                    'incident_date' => $inc->incident_date?->format('d M Y') ?? '-',
                    'incident_time' => $inc->incident_time ? substr($inc->incident_time, 0, 5) : '-',
                    'category' => $inc->category,
                    'category_label' => $inc->category_label ?? ucfirst($inc->category),
                    'description' => $inc->description,
                    'action_taken' => $inc->action_taken,
                    'referral_status' => $refStatus,
                    'referral_status_label' => $statusLabel,
                    'referral_notes' => $inc->bkReferral?->notes,
                    'recorded_by_name' => $inc->recordedBy?->name ?? 'Piket',
                    'evidence_photo_url' => $inc->evidence_photo_url,
                ];
            });

        return [
            'can_access' => true,
            'summary' => [
                'total_rujukan' => $totalRujukan,
                'menunggu' => $totalMenunggu,
                'diproses' => $totalDiproses,
                'selesai' => $totalSelesai,
            ],
            'recent_referrals' => $recentReferrals->values(),
        ];
    }

    /**
     * Bangun payload Live Monitoring terpadu (1 kartu adaptif untuk semua peran).
     *
     * @return array<string, mixed>|null
     */
    public function buildLiveMonitoringData(
        User $user,
        Carbon $date,
        string $operationalAcademicYear,
        ?Classroom $myHomeroomClassroom
    ): ?array {
        return $this->liveMonitoringService->buildLiveMonitoringData(
            $user,
            $date,
            $operationalAcademicYear,
            $myHomeroomClassroom
        );
    }

    public function updateBkReferralStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canOperateBk($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki kewenangan sebagai Guru BK.'], 403);
        }

        $validated = $request->validate([
            'incident_id' => 'required|exists:piket_incidents,id',
            'status' => 'required|in:menunggu,diproses,selesai',
            'notes' => 'nullable|string|max:1000',
        ]);

        $incident = PiketIncident::findOrFail($validated['incident_id']);
        if ($incident->follow_up_target !== 'rujuk_bk') {
            return response()->json(['success' => false, 'message' => 'Kasus kejadian ini tidak dirujuk kepada Guru BK.'], 422);
        }

        $referral = BkReferral::firstOrNew(['piket_incident_id' => $incident->id]);
        $referral->status = $validated['status'];
        if (isset($validated['notes'])) {
            $referral->notes = $validated['notes'];
        }
        $referral->handled_by = $user->id;

        if ($validated['status'] === 'selesai') {
            $referral->completed_at = now();
        } else {
            $referral->completed_at = null;
        }

        $referral->save();

        return response()->json([
            'success' => true,
            'message' => "Status rujukan BK berhasil diperbarui menjadi '{$referral->status_label}'.",
            'data' => [
                'incident_id' => $incident->id,
                'status' => $referral->status,
                'status_label' => $referral->status_label,
            ],
        ]);
    }

    /**
     * Verifikasi insiden kedisiplinan secara cepat dari mobile.
     */
    public function verifyDisciplineIncident(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canVerifyDiscipline($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses verifikasi kedisiplinan.'], 403);
        }

        $validated = $request->validate([
            'incident_id' => 'required|exists:piket_incidents,id',
            'verification_status' => 'required|in:terverifikasi,bukan_pelanggaran,dibatalkan,menunggu_verifikasi',
            'verification_notes' => 'nullable|string|max:1000',
            'follow_up_target' => 'nullable|in:selesai_di_piket,rujuk_wali_kelas,rujuk_bk,rujuk_kesiswaan',
        ]);

        $incident = PiketIncident::findOrFail($validated['incident_id']);

        $updateData = [
            'verification_status' => $validated['verification_status'],
            'verified_by' => $user->id,
            'verified_at' => now(),
            'verification_notes' => $validated['verification_notes'] ?? null,
        ];

        if (! empty($validated['follow_up_target'])) {
            $updateData['follow_up_target'] = $validated['follow_up_target'];
        }

        if ($validated['verification_status'] === 'terverifikasi') {
            $updateData['status'] = 'ditindaklanjuti';
        } elseif (in_array($validated['verification_status'], ['bukan_pelanggaran', 'dibatalkan'])) {
            $updateData['status'] = 'selesai';
        }

        $incident->update($updateData);

        if ($incident->follow_up_target === 'rujuk_bk' && $incident->student_id) {
            BkReferral::firstOrCreate(
                ['piket_incident_id' => $incident->id],
                ['status' => 'menunggu']
            );
        }

        try {
            ActivityLogger::log(
                action: 'verify_discipline',
                description: 'Memverifikasi insiden ketertiban siswa: '.($incident->student?->name ?? 'Siswa').' ('.strtoupper($validated['verification_status']).') via SIAP Mobile.',
                subject: $incident,
                properties: ['source' => 'Mobile', 'status' => $validated['verification_status'], 'notes' => $validated['verification_notes'] ?? null],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => "Insiden berhasil diperbarui menjadi '{$incident->verification_status_label}'.",
            'data' => [
                'incident_id' => $incident->id,
                'verification_status' => $incident->verification_status,
                'verification_status_label' => $incident->verification_status_label,
            ],
        ]);
    }

    /**
     * Ambil data lengkap untuk Sub-halaman Izin & Cuti GTK Mobile.
     */
    public function getPermitsData(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $myPermits = GtkPermit::with('approver')
            ->where('user_id', $user->id)
            ->latest('start_date')
            ->take(30)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'permit_type' => $p->permission_type,
                'status' => $p->status,
                'start_date' => $p->start_date?->format('d M Y'),
                'end_date' => $p->end_date?->format('d M Y'),
                'duration_days' => $p->duration_days,
                'reason' => $p->reason,
                'notes' => $p->notes,
                'approval_note' => $p->approval_note,
                'approver_name' => $p->approver?->name,
                'document_url' => $p->document_url,
                'assignment_document_url' => $p->assignment_document_url,
                'assignment_document_name' => $p->assignment_document_name,
                'created_at' => $p->created_at?->format('d M Y H:i'),
            ]);

        $canManage = $user->isPrincipal() || $user->isSuperAdmin();
        $pendingApprovals = [];
        if ($canManage) {
            $pendingApprovals = GtkPermit::pending()->with('user')
                ->latest('created_at')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'user_name' => $p->user?->name ?? 'GTK',
                    'user_position' => $p->user?->position ?? 'Guru/Staf',
                    'permit_type' => $p->permission_type,
                    'start_date' => $p->start_date?->format('d M Y'),
                    'end_date' => $p->end_date?->format('d M Y'),
                    'duration_days' => $p->duration_days,
                    'reason' => $p->reason,
                    'notes' => $p->notes,
                    'document_url' => $p->document_url,
                    'assignment_document_url' => $p->assignment_document_url,
                    'assignment_document_name' => $p->assignment_document_name,
                    'created_at' => $p->created_at?->format('d M Y H:i'),
                ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'can_manage' => $canManage,
                'my_permits' => $myPermits,
                'pending_approvals' => $pendingApprovals,
                'types' => [
                    ['key' => 'sakit', 'label' => 'Sakit (Surat Dokter)'],
                    ['key' => 'izin', 'label' => 'Izin Kepentingan Pribadi'],
                    ['key' => 'cuti', 'label' => 'Cuti Tahunan / Alasan Penting'],
                    ['key' => 'dinas_luar', 'label' => 'Tugas Dinas / Luar Sekolah'],
                ],
            ],
        ]);
    }

    /**
     * Simpan pengajuan Izin/Cuti GTK dari Mobile.
     */
    public function storeGtkPermit(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'permission_type' => ['required', 'in:sakit,izin,cuti,dinas_luar'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'assignment_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = ! empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $startDate->copy();
        $durationDays = $startDate->diffInDays($endDate) + 1;

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('gtk_permits', 'local');
        }

        $assignmentDocPath = null;
        $assignmentDocName = null;
        if ($request->hasFile('assignment_document')) {
            $file = $request->file('assignment_document');
            $assignmentDocName = $file->getClientOriginalName();
            $assignmentDocPath = $file->store('gtk_permits/assignments', 'local');
        }

        $isPrincipalOrAdmin = $user->isPrincipal() || $user->isSuperAdmin();
        $status = $isPrincipalOrAdmin ? GtkPermit::STATUS_APPROVED : GtkPermit::STATUS_PENDING;

        $permit = GtkPermit::create([
            'user_id' => $user->id,
            'permission_type' => $validated['permission_type'],
            'status' => $status,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'duration_days' => $durationDays,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'document_path' => $documentPath,
            'assignment_document_path' => $assignmentDocPath,
            'assignment_document_name' => $assignmentDocName,
            'approved_by' => $isPrincipalOrAdmin ? $user->id : null,
            'approved_at' => $isPrincipalOrAdmin ? now() : null,
        ]);

        try {
            app(TelegramService::class)->notifyGtkPermit($permit, $user);
        } catch (\Throwable) {
            // Ignore notification failure
        }

        try {
            ActivityLogger::log(
                action: 'create_permit',
                description: 'Mengajukan izin/cuti: '.ucfirst($permit->permission_type)." ({$permit->start_date?->format('d/m/Y')}) via SIAP Mobile.",
                subject: $permit,
                properties: ['source' => 'Mobile', 'duration_days' => $permit->duration_days],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => $isPrincipalOrAdmin ? 'Pengajuan izin berhasil dicatat & disetujui.' : 'Pengajuan izin berhasil dikirimkan ke Kepala Sekolah.',
            'data' => $permit,
        ]);
    }

    /**
     * Keputusan Persetujuan / Penolakan Izin GTK oleh KS.
     */
    public function actionGtkPermit(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        if (! $user->isPrincipal() && ! $user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya Kepala Sekolah yang berhak menyetujui/menolak perizinan.'], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $permit = GtkPermit::findOrFail($id);
        $newStatus = $validated['action'] === 'approve' ? GtkPermit::STATUS_APPROVED : GtkPermit::STATUS_REJECTED;

        $permit->update([
            'status' => $newStatus,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approval_note' => $validated['note'] ?? null,
        ]);

        try {
            app(TelegramService::class)->notifyGtkPermitDecision($permit, $user);
        } catch (\Throwable) {
            // Ignore notification failure
        }

        try {
            ActivityLogger::log(
                action: 'action_permit',
                description: 'Menindaklanjuti permohonan izin GTK: '.strtoupper($validated['action'])." ({$permit->user?->name}) via SIAP Mobile.",
                subject: $permit,
                properties: ['source' => 'Mobile', 'action' => $validated['action'], 'note' => $validated['note'] ?? null],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => $newStatus === GtkPermit::STATUS_APPROVED ? 'Izin berhasil disetujui.' : 'Izin telah ditolak.',
            'data' => [
                'id' => $permit->id,
                'status' => $permit->status,
            ],
        ]);
    }

    /**
     * Ambil data lengkap untuk Sub-halaman Visitasi Siswa Mobile.
     */
    public function getVisitationsData(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canAccessVisitations($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses modul visitasi.'], 403);
        }

        $visitations = StudentVisitation::with(['student.classroom', 'primaryOfficer', 'secondaryOfficer', 'requester'])
            ->where('academic_year', $operationalAcademicYear)
            ->latest('planned_date')
            ->take(30)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'student_name' => $v->student?->name ?? 'Siswa',
                'classroom_name' => $v->student?->classroom?->name ?? '-',
                'planned_date' => $v->planned_date?->format('d M Y'),
                'visit_type' => $v->visit_type,
                'status' => $v->status,
                'reason' => $v->reason,
                'result_summary' => $v->result_summary,
                'primary_officer' => $v->primaryOfficer?->name ?? '-',
                'secondary_officer' => $v->secondaryOfficer?->name,
            ]);

        // Daftar siswa aktif untuk form
        $studentsQuery = Student::where('status', 'aktif');
        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && ! $user->canOperateBk($operationalAcademicYear) && ! $user->canManageDiscipline($operationalAcademicYear)) {
            $myClassroomIds = $user->homeroomClassrooms()->pluck('id');
            $studentsQuery->whereIn('classroom_id', $myClassroomIds);
        }

        $students = $studentsQuery->with('classroom')
            ->orderBy('name')
            ->take(150)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'classroom' => $s->classroom?->name ?? '-',
            ]);

        $officers = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        return response()->json([
            'success' => true,
            'data' => [
                'visitations' => $visitations,
                'students' => $students,
                'officers' => $officers,
                'my_user_id' => $user->id,
                'stats' => [
                    'total' => $visitations->count(),
                    'dijadwalkan' => $visitations->whereIn('status', ['diajukan', 'dijadwalkan'])->count(),
                    'terlaksana' => $visitations->where('status', 'terlaksana')->count(),
                ],
            ],
        ]);
    }

    /**
     * Simpan jadwal visitasi siswa baru dari Mobile.
     */
    public function storeVisitation(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canAccessVisitations($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses modul visitasi.'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'planned_date' => 'required|date',
            'visit_type' => 'required|in:kunjungan_rumah,pemantauan_khusus,tindak_lanjut_absensi,komunikasi_ortu,tindak_lanjut_kasus,lainnya',
            'reason' => 'required|string|max:1000',
            'primary_officer_id' => 'required|exists:users,id',
            'secondary_officer_id' => 'nullable|exists:users,id',
        ]);

        $visitation = StudentVisitation::create([
            'student_id' => $validated['student_id'],
            'academic_year' => $operationalAcademicYear,
            'visit_type' => $validated['visit_type'],
            'source_type' => 'mandiri',
            'reason' => $validated['reason'],
            'primary_officer_id' => $validated['primary_officer_id'],
            'secondary_officer_id' => $validated['secondary_officer_id'] ?? null,
            'requested_by' => $user->id,
            'planned_date' => $validated['planned_date'],
            'status' => 'dijadwalkan',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal visitasi siswa berhasil dibuat.',
            'data' => $visitation,
        ]);
    }

    /**
     * Ambil data lengkap untuk Sub-halaman Tim Kedisiplinan Mobile.
     */
    public function getDisciplineData(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        $incidents = PiketIncident::with(['student.classroom', 'classroom', 'recordedBy', 'verifier', 'bkReferral'])
            ->where(function ($q) use ($operationalAcademicYear) {
                $q->where('academic_year', $operationalAcademicYear)
                    ->orWhereHas('journal', fn ($j) => $j->where('academic_year', $operationalAcademicYear));
            })
            ->latest('incident_date')
            ->latest('incident_time')
            ->take(50)
            ->get()
            ->map(fn ($inc) => [
                'id' => $inc->id,
                'student_name' => $inc->student?->name ?? $inc->student_name ?? 'Siswa',
                'classroom_name' => $inc->classroom?->name ?? $inc->student?->classroom?->name ?? '-',
                'incident_date' => $inc->incident_date?->format('d M Y') ?? '-',
                'incident_time' => $inc->incident_time ? substr($inc->incident_time, 0, 5) : '-',
                'category' => $inc->category,
                'category_label' => $inc->category_label ?? ucfirst($inc->category),
                'penalty_points' => $inc->penalty_points ?? 0,
                'description' => $inc->description,
                'action_taken' => $inc->action_taken,
                'verification_status' => $inc->verification_status ?? 'menunggu_verifikasi',
                'verification_status_label' => $inc->verification_status_label ?? 'Menunggu',
                'recorded_by_name' => $inc->recordedBy?->name ?? 'Piket',
                'evidence_photo_url' => $inc->evidence_photo_url,
                'follow_up_target' => $inc->follow_up_target,
            ]);

        $summary = [
            'total' => $incidents->count(),
            'menunggu' => $incidents->where('verification_status', 'menunggu_verifikasi')->count(),
            'terverifikasi' => $incidents->where('verification_status', 'terverifikasi')->count(),
            'rujukan_bk' => $incidents->where('follow_up_target', 'rujuk_bk')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'can_verify' => $user->canVerifyDiscipline($operationalAcademicYear),
                'summary' => $summary,
                'incidents' => $incidents,
            ],
        ]);
    }

    /**
     * Ambil data lengkap untuk Sub-halaman Layanan BK Mobile.
     */
    public function getBkData(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canOperateBk($operationalAcademicYear) && ! $user->canManageDiscipline($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki wewenang mengakses modul BK.'], 403);
        }

        $referrals = PiketIncident::with(['student.classroom', 'classroom', 'recordedBy', 'bkReferral.handler'])
            ->where('follow_up_target', 'rujuk_bk')
            ->latest('incident_date')
            ->take(30)
            ->get()
            ->map(function ($inc) {
                $refStatus = $inc->bkReferral?->status ?? 'menunggu';

                return [
                    'id' => $inc->id,
                    'student_id' => $inc->student_id,
                    'student_nisn' => $inc->student?->nisn,
                    'student_name' => $inc->student?->name ?? $inc->student_name ?? 'Siswa',
                    'classroom_name' => $inc->classroom?->name ?? $inc->student?->classroom?->name ?? '-',
                    'incident_date' => $inc->incident_date?->format('d M Y') ?? '-',
                    'category_label' => $inc->category_label ?? ucfirst($inc->category),
                    'description' => $inc->description,
                    'action_taken' => $inc->action_taken,
                    'referral_status' => $refStatus,
                    'referral_status_label' => ucfirst($refStatus),
                    'referral_notes' => $inc->bkReferral?->notes,
                ];
            });

        $services = BkService::with(['student.classroom', 'counselor'])
            ->where('academic_year', $operationalAcademicYear)
            ->latest('service_date')
            ->take(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'student_name' => $s->student?->name ?? 'Siswa',
                'classroom_name' => $s->student?->classroom?->name ?? '-',
                'service_date' => $s->service_date?->format('d M Y'),
                'service_type' => ucfirst($s->service_type),
                'summary' => $s->summary,
                'result' => $s->result,
                'follow_up_plan' => $s->follow_up_plan,
                'counselor_name' => $s->counselor?->name ?? 'Guru BK',
            ]);

        $students = Student::where('status', 'aktif')
            ->with('classroom')
            ->orderBy('name')
            ->take(150)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'classroom' => $s->classroom?->name ?? '-',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'can_operate_bk' => $user->canOperateBk($operationalAcademicYear),
                'referrals' => $referrals,
                'services' => $services,
                'students' => $students,
                'academic_year' => $operationalAcademicYear,
                'stats' => [
                    'total_rujukan' => $referrals->count(),
                    'menunggu' => $referrals->where('referral_status', 'menunggu')->count(),
                    'diproses' => $referrals->where('referral_status', 'diproses')->count(),
                    'selesai' => $referrals->where('referral_status', 'selesai')->count(),
                ],
            ],
        ]);
    }

    /**
     * Simpan sesi layanan konseling BK baru dari Mobile.
     */
    public function storeBkService(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        if (! $user->canOperateBk($operationalAcademicYear)) {
            return response()->json(['success' => false, 'message' => 'Hanya Guru BK yang dapat mencatat sesi layanan.'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'service_date' => 'required|date',
            'service_type' => 'required|in:pribadi,sosial,belajar,karier',
            'summary' => 'required|string|max:2000',
            'result' => 'required|string|max:2000',
            'follow_up_plan' => 'nullable|string|max:1000',
            'piket_incident_id' => 'nullable|exists:piket_incidents,id',
        ]);

        $service = BkService::create([
            'student_id' => $validated['student_id'],
            'piket_incident_id' => $validated['piket_incident_id'] ?? null,
            'academic_year' => $operationalAcademicYear,
            'service_date' => $validated['service_date'],
            'service_type' => $validated['service_type'],
            'summary' => $validated['summary'],
            'result' => $validated['result'],
            'follow_up_plan' => $validated['follow_up_plan'] ?? null,
            'counselor_id' => $user->id,
        ]);

        if (! empty($validated['piket_incident_id'])) {
            $ref = BkReferral::where('piket_incident_id', $validated['piket_incident_id'])->first();
            if ($ref) {
                $ref->update([
                    'status' => 'selesai',
                    'handled_by' => $user->id,
                    'handled_at' => now(),
                ]);
            }
        }

        try {
            $studentName = $service->student?->name ?? 'Siswa';
            ActivityLogger::log(
                action: 'create_bk_service',
                description: "Mencatat sesi konseling BK siswa: {$studentName} (Bidang: {$service->service_type}) via SIAP Mobile.",
                subject: $service,
                properties: ['source' => 'Mobile', 'service_type' => $service->service_type, 'service_date' => $service->service_date],
                user: $user
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi konseling siswa berhasil dicatat.',
            'data' => $service,
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Ketik minimal 3 karakter untuk mencari siswa.',
            ]);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalAcademicYear = $academicYearContext->getOperationalYear()->name;

        $query = Student::where('status', 'aktif')
            ->with('classroom')
            ->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                    ->orWhere('nisn', 'like', "%{$q}%")
                    ->orWhere('nis', 'like', "%{$q}%");
            });

        // Jika hanya wali kelas / guru biasa dan bukan pimpinan/BK/kedisiplinan/piket, batasi ke kelas binaan jika ada
        if (! $user->isSuperAdmin() && ! $user->isPrincipal() && ! $user->canOperateBk($operationalAcademicYear) && ! $user->canManageDiscipline($operationalAcademicYear) && ! $user->canOperatePiket($operationalAcademicYear) && ! $user->isPiketOfficerToday($operationalAcademicYear)) {
            $myClassroomIds = $user->homeroomClassrooms()->pluck('id');
            if ($myClassroomIds->isNotEmpty()) {
                $query->whereIn('classroom_id', $myClassroomIds);
            }
        }

        $students = $query->orderBy('name')
            ->take(25)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'nis' => $s->nis,
                'classroom_id' => $s->classroom_id,
                'classroom_name' => $s->classroom?->name ?? '-',
                'label' => "{$s->name} (".($s->classroom?->name ?? '-').($s->nisn ? " - {$s->nisn}" : '').')',
            ]);

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    /**
     * Get Piket operational workspace payload for mobile.
     */
    public function getPiketData(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $operationalYear = $academicYearContext->getOperationalYear();
        $academicYear = $operationalYear->name;

        $isOfficerToday = $user->isPiketOfficerToday($academicYear);
        $canOperate = $user->canOperatePiket($academicYear) || $isOfficerToday;
        $isCoordinator = $user->isPiketCoordinator();
        $isManagement = $user->isSuperAdmin() || $user->isPrincipal() || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum());
        $hasAnyPiketSchedule = PiketSchedule::where('teacher_id', $user->id)->where('is_active', true)->exists();

        $today = Carbon::today()->toDateString();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';

        // Approved swaps affecting today
        $approvedSwapsToday = PiketDutySwap::where('status', 'disetujui')
            ->whereDate('duty_date', $today)
            ->with(['requester:id,name', 'replacementTeacher:id,name'])
            ->get();

        $isSwappedInToday = $approvedSwapsToday->where('replacement_teacher_id', $user->id)->isNotEmpty();
        $isSwappedOutToday = $approvedSwapsToday->where('requester_id', $user->id)->isNotEmpty();
        $isOnDutyToday = ($isOfficerToday || $isSwappedInToday) && ! $isSwappedOutToday;

        // Ensure today's journal exists
        $journal = $this->getOrCreatePiketJournal($today, $user, $academicYear, $operationalYear->semester);

        // Scheduled officers today
        $schedulesToday = PiketSchedule::with('teacher:id,name')
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN role = 'koordinator' THEN 0 ELSE 1 END")
            ->get();

        $scheduledOfficers = $schedulesToday->map(function ($sch) use ($approvedSwapsToday) {
            $swap = $approvedSwapsToday->firstWhere('original_schedule_id', $sch->id)
                ?? $approvedSwapsToday->firstWhere('requester_id', $sch->teacher_id);

            return [
                'id' => $sch->id,
                'teacher_id' => $sch->teacher_id,
                'teacher_name' => $sch->teacher?->name ?? 'Guru',
                'role' => $sch->role,
                'duty_location' => $sch->duty_location,
                'is_swapped' => (bool) $swap,
                'replacement_teacher_name' => $swap?->replacementTeacher?->name,
            ];
        });

        // 1. Stats
        $permitsToday = PiketStudentPermit::whereDate('permit_date', $today)->get();
        $incidentsToday = PiketIncident::whereDate('incident_date', $today)->get();
        $invalsToday = PiketInvalClass::where('piket_journal_id', $journal->id)->get();
        $guestsToday = PiketGuest::whereDate('visit_date', $today)->get();

        $stats = [
            'total_permits' => $permitsToday->count(),
            'permits_terlambat' => $permitsToday->where('permit_type', 'terlambat')->count(),
            'permits_keluar' => $permitsToday->where('permit_type', 'izin_keluar_sementara')->count(),
            'permits_pulang' => $permitsToday->where('permit_type', 'izin_pulang_cepat')->count(),
            'permits_tidak_masuk' => $permitsToday->where('permit_type', 'izin_tidak_masuk')->count(),

            'total_incidents' => $incidentsToday->count(),
            'incidents_tercatat' => $incidentsToday->where('status', 'tercatat')->count(),
            'incidents_selesai' => $incidentsToday->where('status', 'selesai')->count(),
            'incidents_rujuk_bk' => $incidentsToday->where('follow_up_target', 'rujuk_bk')->count(),

            'total_invals' => $invalsToday->count(),
            'invals_handled' => $invalsToday->whereNotNull('inval_teacher_id')->count(),
            'invals_unhandled' => $invalsToday->whereNull('inval_teacher_id')->count(),

            'total_guests' => $guestsToday->count(),
            'guests_visiting' => $guestsToday->whereNull('check_out_time')->count(),
            'guests_checked_out' => $guestsToday->whereNotNull('check_out_time')->count(),
        ];

        // 2. Recent lists
        $permitsList = PiketStudentPermit::whereDate('permit_date', $today)
            ->with(['student:id,name,nisn', 'classroom:id,name'])
            ->latest()
            ->take(30)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'permit_number' => $p->permit_number,
                'permit_type' => $p->permit_type,
                'permit_category' => $p->permit_category,
                'student_name' => $p->student_name,
                'classroom_name' => $p->classroom?->name ?? '-',
                'reason' => $p->reason,
                'entry_time' => $p->entry_time ? substr($p->entry_time, 0, 5) : null,
                'departure_time' => $p->departure_time ? substr($p->departure_time, 0, 5) : null,
                'return_time' => $p->return_time ? substr($p->return_time, 0, 5) : null,
                'period_assigned' => $p->period_assigned,
                'status' => $p->status,
                'action_taken' => $p->action_taken,
                'created_at_human' => $p->created_at?->diffForHumans(),
            ]);

        $incidentsList = PiketIncident::whereDate('incident_date', $today)
            ->with(['student:id,name,nisn', 'classroom:id,name', 'recordedBy:id,name'])
            ->latest('id')
            ->take(30)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'student_name' => $i->student_name,
                'classroom_name' => $i->classroom?->name ?? '-',
                'incident_time' => substr($i->incident_time, 0, 5),
                'category' => $i->category,
                'description' => $i->description,
                'action_taken' => $i->action_taken,
                'follow_up_target' => $i->follow_up_target,
                'evidence_photo_url' => $i->evidence_photo_url,
                'status' => $i->status,
                'recorder_name' => $i->recordedBy?->name ?? 'Petugas Piket',
                'created_at_human' => $i->created_at?->diffForHumans(),
            ]);

        $invalsList = PiketInvalClass::where('piket_journal_id', $journal->id)
            ->with(['classroom:id,name', 'absentTeacher:id,name', 'invalTeacher:id,name', 'subject:id,name'])
            ->latest('id')
            ->take(30)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'classroom_name' => $inv->classroom?->name ?? '-',
                'absent_teacher_name' => $inv->absentTeacher?->name ?? '-',
                'inval_teacher_name' => $inv->invalTeacher?->name,
                'subject_name' => $inv->subject?->name ?? '-',
                'period_start' => $inv->period_start,
                'period_end' => $inv->period_end,
                'period_label' => "Jam ke {$inv->period_start}".($inv->period_end > $inv->period_start ? "-{$inv->period_end}" : ''),
                'reason' => $inv->reason,
                'assignment_material' => $inv->assignment_material,
                'assignment_file_url' => $inv->assignment_file_url,
                'assignment_file_name' => $inv->assignment_file_name,
                'notes' => $inv->notes,
                'is_handled' => (bool) $inv->inval_teacher_id,
            ]);

        $guestsList = PiketGuest::whereDate('visit_date', $today)
            ->latest('id')
            ->take(30)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'guest_name' => $g->guest_name,
                'institution' => $g->institution ?: 'Tamu Pribadi / Orang Tua',
                'phone' => $g->phone,
                'purpose' => $g->purpose,
                'meet_with' => $g->meet_with,
                'badge_number' => $g->badge_number,
                'check_in_time' => substr($g->check_in_time, 0, 5),
                'check_out_time' => $g->check_out_time ? substr($g->check_out_time, 0, 5) : null,
                'is_checked_out' => (bool) $g->check_out_time,
                'notes' => $g->notes,
            ]);

        // Helpers
        $classrooms = Classroom::orderBy('name')->get(['id', 'name']);
        $teachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $mySchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->pluck('day')
            ->toArray();

        $gtkPermitsToday = GtkPermit::with('user:id,name,position')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', '!=', GtkPermit::STATUS_REJECTED)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'user_name' => $p->user?->name ?? 'Guru',
                'user_position' => $p->user?->position ?? 'Guru',
                'permission_type' => $p->permission_type,
                'type_label' => $p->type_label,
                'reason' => $p->reason,
                'status' => $p->status,
                'status_label' => $p->status_label,
                'document_url' => $p->document_url,
                'assignment_document_url' => $p->assignment_document_url,
                'assignment_document_name' => $p->assignment_document_name,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $today,
                'today_day' => $todayDay,
                'is_on_duty_today' => $isOnDutyToday,
                'can_operate' => $canOperate || $isOnDutyToday || $isManagement,
                'is_coordinator' => $isCoordinator || $journal->coordinator_id === $user->id || $isManagement,
                'is_management' => $isManagement,
                'has_piket_schedule' => $hasAnyPiketSchedule,
                'my_schedules' => $mySchedules,
                'journal' => [
                    'id' => $journal->id,
                    'status' => $journal->status,
                    'coordinator_name' => $journal->coordinator?->name ?? $user->name,
                    'completed_at' => $journal->completed_at?->format('H:i d/m/Y'),
                    'general_notes' => $journal->general_notes,
                ],
                'scheduled_officers' => $scheduledOfficers,
                'stats' => $stats,
                'permits' => $permitsList,
                'incidents' => $incidentsList,
                'invals' => $invalsList,
                'guests' => $guestsList,
                'gtk_permits_today' => $gtkPermitsToday,
                'helpers' => [
                    'classrooms' => $classrooms,
                    'teachers' => $teachers,
                    'subjects' => $subjects,
                ],
            ],
        ]);
    }

    /**
     * Store new student permit issued by piket officer.
     */
    public function storePiketStudentPermit(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'permit_type' => 'required|in:terlambat,izin_keluar_sementara,izin_pulang_cepat,izin_tidak_masuk',
            'permit_date' => 'nullable|date',
            'student_id' => 'nullable|exists:students,id',
            'student_name' => 'nullable|string|max:255',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'reason' => 'required|string|max:500',
            'permit_category' => 'nullable|in:sakit,izin,dispen,terlambat',
            'entry_time' => 'nullable|string',
            'departure_time' => 'nullable|string',
            'return_time' => 'nullable|string',
            'period_assigned' => 'nullable|string|max:50',
            'action_taken' => 'nullable|string|max:255',
        ]);

        $permitDate = (! empty($validated['permit_date'])) ? $validated['permit_date'] : Carbon::today()->toDateString();
        $todayStr = Carbon::parse($permitDate)->toDateString();

        // Otomatis ambil data siswa & kelas jika classroom_id atau student_name tidak diisi
        $student = null;
        if (! empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                if (empty($validated['classroom_id'])) {
                    $validated['classroom_id'] = $student->classroom_id;
                }
                if (empty($validated['student_name'])) {
                    $validated['student_name'] = $student->name;
                }
            }
        }

        if (empty($validated['student_name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Nama siswa harus diisi atau dipilih dari data siswa.',
            ], 422);
        }

        if (empty($validated['classroom_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas siswa tidak ditemukan. Pastikan siswa telah terdaftar pada suatu rombel kelas.',
            ], 422);
        }

        // Prevent duplicate input for same student, type, and day
        if (! empty($validated['student_id'])) {
            $existingPermit = PiketStudentPermit::where('student_id', $validated['student_id'])
                ->where('permit_type', $validated['permit_type'])
                ->whereDate('permit_date', $todayStr)
                ->first();

            if ($existingPermit) {
                return response()->json([
                    'success' => false,
                    'message' => "Surat izin untuk {$validated['student_name']} dengan jenis ini sudah pernah dibuat hari ini ({$existingPermit->permit_number}).",
                ], 422);
            }
        }

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($todayStr, $user, $operationalYear->name, $operationalYear->semester);

        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        $departureTime = null;
        if (in_array($validated['permit_type'], ['izin_pulang_cepat', 'izin_keluar_sementara'])) {
            $departureTime = $validated['departure_time'] ?: Carbon::now()->format('H:i');
        }

        // Otomatis hitung waktu masuk & jam pelajaran ke- bila izin terlambat
        $entryTime = null;
        $periodAssigned = null;
        if ($validated['permit_type'] === 'terlambat') {
            $entryTime = ! empty($validated['entry_time']) ? $validated['entry_time'] : Carbon::now()->format('H:i');
            if (! empty($validated['period_assigned'])) {
                $periodAssigned = $validated['period_assigned'];
            } else {
                $dayOfWeekIso = Carbon::parse($permitDate)->dayOfWeekIso;
                $dayKey = $dayOfWeekIso === 5 ? 'jumat' : 'senin';
                $periodTimes = LessonSchedule::periodTimes($dayKey);
                $timeStr = substr($entryTime, 0, 5);
                foreach ($periodTimes as $p => $times) {
                    if ($timeStr <= $times['end']) {
                        $periodAssigned = (string) $p;
                        break;
                    }
                }
                if (! $periodAssigned && ! empty($periodTimes)) {
                    $periodAssigned = (string) array_key_last($periodTimes);
                }
            }
        }

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $permitDate,
            'student_id' => $validated['student_id'] ?? null,
            'student_name' => $validated['student_name'],
            'classroom_id' => $validated['classroom_id'],
            'permit_type' => $validated['permit_type'],
            'permit_category' => $validated['permit_category'] ?? ($validated['permit_type'] === 'terlambat' ? 'terlambat' : 'izin'),
            'reason' => $validated['reason'],
            'entry_time' => $entryTime,
            'departure_time' => $departureTime,
            'return_time' => ($validated['permit_type'] === 'izin_keluar_sementara') ? ($validated['return_time'] ?? null) : null,
            'period_assigned' => $periodAssigned,
            'action_taken' => $validated['action_taken'] ?? null,
            'given_by' => $user->id,
            'status' => 'diberikan',
        ]);

        ActivityLogger::log('create', "Menerbitkan surat izin piket: {$permit->student_name} ({$permit->permit_number})", $permit, [
            'type' => $permit->permit_type,
            'classroom_id' => $permit->classroom_id,
        ]);

        // Send Telegram notification
        try {
            $classroom = Classroom::with('homeroomTeacher')->find($validated['classroom_id']);
            $homeroomTeacher = $classroom?->homeroomTeacher;

            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $dayName = $dayNames[Carbon::parse($permitDate)->dayOfWeekIso] ?? 'Senin';

            $scheduleQuery = LessonSchedule::where('classroom_id', $validated['classroom_id'])
                ->where('day', $dayName)
                ->with('teacher');

            if (! empty($validated['period_assigned']) && is_numeric($validated['period_assigned'])) {
                $p = (int) $validated['period_assigned'];
                $scheduleQuery->where('start_period', '<=', $p)
                    ->where('end_period', '>=', $p);
            }

            $subjectTeacher = $scheduleQuery->first()?->teacher;

            if ($validated['permit_type'] === 'terlambat') {
                $this->telegramService->notifyStudentLate($permit, $subjectTeacher, $homeroomTeacher);
            } else {
                $this->telegramService->notifyStudentPermit($permit, $homeroomTeacher, $subjectTeacher);
            }
        } catch (\Throwable $e) {
            // Silently continue if Telegram notification fails
        }

        $printUrl = route('piket.permits.slip', ['permit' => $permit->id, 'autoprint' => 1]);

        return response()->json([
            'success' => true,
            'message' => "Surat izin {$permit->permit_number} berhasil diterbitkan.",
            'data' => array_merge($permit->toArray(), [
                'print_url' => $printUrl,
            ]),
            'print_url' => $printUrl,
        ]);
    }

    /**
     * Store new student incident recorded by piket officer.
     */
    public function storePiketIncident(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'incident_date' => 'nullable|date',
            'incident_time' => 'nullable|string',
            'student_id' => 'nullable|exists:students,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'student_name' => 'nullable|string|max:255',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'location' => 'nullable|string|max:255',
            'category' => 'required|in:kedisiplinan,seragam,gadget_terlarang,perkelahian,rokok,kesehatan_pingsan,lainnya',
            'description' => 'required|string',
            'action_taken' => 'required|string',
            'follow_up_target' => 'required|in:selesai_di_piket,rujuk_wali_kelas,rujuk_bk,rujuk_kesiswaan',
            'confiscated_items' => 'nullable|string|max:255',
            'evidence_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'evidence_photo_base64' => 'nullable|string',
        ]);

        $studentIds = ! empty($validated['student_ids']) ? array_filter((array) $validated['student_ids']) : [];
        if (! empty($validated['student_id']) && ! in_array($validated['student_id'], $studentIds)) {
            $studentIds[] = $validated['student_id'];
        }

        if (empty($studentIds) && empty($validated['student_name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu siswa yang terlibat.',
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('evidence_photo')) {
            $photoPath = $request->file('evidence_photo')->store('piket/incidents', 'local');
        } elseif (! empty($validated['evidence_photo_base64'])) {
            $b64 = $validated['evidence_photo_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $b64, $type)) {
                $b64 = substr($b64, strpos($b64, ',') + 1);
                $type = strtolower($type[1]);
                if (in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                    $decoded = base64_decode($b64);
                    if ($decoded !== false) {
                        $filename = 'piket/incidents/'.uniqid('piket_inc_').'.'.$type;
                        Storage::disk('local')->put($filename, $decoded);
                        $photoPath = $filename;
                    }
                }
            }
        }

        $incidentDate = (! empty($validated['incident_date'])) ? $validated['incident_date'] : Carbon::today()->toDateString();
        $incidentTime = (! empty($validated['incident_time'])) ? $validated['incident_time'] : Carbon::now()->format('H:i');
        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();

        $journal = $this->getOrCreatePiketJournal($incidentDate, $user, $operationalYear->name, $operationalYear->semester);

        $createdIncidents = [];

        if (! empty($studentIds)) {
            $students = Student::with('classroom.homeroomTeacher')->whereIn('id', $studentIds)->get();
            foreach ($students as $student) {
                $incident = PiketIncident::create([
                    'piket_journal_id' => $journal->id,
                    'academic_year' => $operationalYear->name ?? $journal->academic_year,
                    'incident_date' => $incidentDate,
                    'incident_time' => $incidentTime,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'classroom_id' => $student->classroom_id ?: ($validated['classroom_id'] ?? null),
                    'location' => $validated['location'] ?? null,
                    'category' => $validated['category'],
                    'description' => $validated['description'],
                    'action_taken' => $validated['action_taken'],
                    'follow_up_target' => $validated['follow_up_target'],
                    'confiscated_items' => $validated['confiscated_items'] ?? null,
                    'evidence_photo' => $photoPath,
                    'recorded_by' => $user->id,
                    'status' => $validated['follow_up_target'] === 'selesai_di_piket' ? 'selesai' : 'tercatat',
                ]);

                if ($incident->follow_up_target === 'rujuk_bk' && $incident->student_id) {
                    BkReferral::firstOrCreate(
                        ['piket_incident_id' => $incident->id],
                        ['status' => 'menunggu']
                    );
                }

                ActivityLogger::log('create', "Mencatat kejadian piket: {$incident->student_name} - {$incident->category}", $incident, [
                    'category' => $incident->category,
                    'follow_up' => $incident->follow_up_target,
                ]);

                try {
                    $homeroomTeacher = $student->classroom?->homeroomTeacher;
                    $this->telegramService->notifyIncident($incident, $homeroomTeacher);
                } catch (\Throwable $e) {
                    // Silently continue
                }

                $createdIncidents[] = $incident;
            }
        } else {
            $incident = PiketIncident::create([
                'piket_journal_id' => $journal->id,
                'academic_year' => $operationalYear->name ?? $journal->academic_year,
                'incident_date' => $incidentDate,
                'incident_time' => $incidentTime,
                'student_id' => null,
                'student_name' => $validated['student_name'] ?? 'Siswa',
                'classroom_id' => $validated['classroom_id'] ?? null,
                'location' => $validated['location'] ?? null,
                'category' => $validated['category'],
                'description' => $validated['description'],
                'action_taken' => $validated['action_taken'],
                'follow_up_target' => $validated['follow_up_target'],
                'confiscated_items' => $validated['confiscated_items'] ?? null,
                'evidence_photo' => $photoPath,
                'recorded_by' => $user->id,
                'status' => $validated['follow_up_target'] === 'selesai_di_piket' ? 'selesai' : 'tercatat',
            ]);

            ActivityLogger::log('create', "Mencatat kejadian piket: {$incident->student_name} - {$incident->category}", $incident, [
                'category' => $incident->category,
                'follow_up' => $incident->follow_up_target,
            ]);

            try {
                $classroom = $incident->classroom_id ? Classroom::with('homeroomTeacher')->find($incident->classroom_id) : null;
                $homeroomTeacher = $classroom?->homeroomTeacher;
                $this->telegramService->notifyIncident($incident, $homeroomTeacher);
            } catch (\Throwable $e) {
                // Silently continue
            }

            $createdIncidents[] = $incident;
        }

        $count = count($createdIncidents);
        $message = $count > 1
            ? "Catatan kejadian untuk {$count} siswa berhasil disimpan."
            : "Catatan kejadian {$createdIncidents[0]->student_name} berhasil disimpan.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $createdIncidents[0] ?? null,
            'incidents' => $createdIncidents,
            'count' => $count,
        ]);
    }

    /**
     * Dapatkan jadwal KBM guru hari ini dan status izin aktif untuk penugasan inval.
     */
    public function getTeacherSchedulesForInval(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $teacherId = $request->query('teacher_id');
        if (! $teacherId) {
            return response()->json(['success' => false, 'message' => 'ID Guru harus disertakan.'], 422);
        }

        $today = Carbon::today()->toDateString();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';

        $schedules = LessonSchedule::with(['classroom:id,name', 'subject:id,name'])
            ->where('teacher_id', $teacherId)
            ->where('day', $todayDay)
            ->orderBy('start_period')
            ->get();

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($today, $user, $operationalYear->name, $operationalYear->semester);

        $existingInvals = PiketInvalClass::where('piket_journal_id', $journal->id)
            ->where('absent_teacher_id', $teacherId)
            ->get();

        $periodTimes = LessonSchedule::periodTimes($todayDay === 'Jumat' ? 'jumat' : 'senin');

        $formattedSchedules = $schedules->map(function ($sch) use ($existingInvals, $periodTimes) {
            $startStr = $periodTimes[$sch->start_period]['start'] ?? null;
            $endStr = $periodTimes[$sch->end_period]['end'] ?? null;
            $timeStr = ($startStr && $endStr) ? " ({$startStr} - {$endStr})" : '';

            $alreadyInval = $existingInvals->first(function ($inv) use ($sch) {
                return $inv->classroom_id == $sch->classroom_id &&
                    $inv->period_start == $sch->start_period &&
                    $inv->period_end == $sch->end_period;
            });

            return [
                'id' => $sch->id,
                'classroom_id' => $sch->classroom_id,
                'classroom_name' => $sch->classroom?->name ?? 'Kelas',
                'subject_id' => $sch->subject_id,
                'subject_name' => $sch->subject?->name ?? 'Mapel',
                'start_period' => $sch->start_period,
                'end_period' => $sch->end_period,
                'period_label' => "Jam ke-{$sch->start_period}".($sch->end_period > $sch->start_period ? "-{$sch->end_period}" : '').$timeStr,
                'label' => ($sch->classroom?->name ?? 'Kelas').' • '.($sch->subject?->name ?? 'Mapel')." • Jam {$sch->start_period}-{$sch->end_period}".$timeStr,
                'is_already_assigned' => (bool) $alreadyInval,
                'inval_teacher_name' => $alreadyInval?->invalTeacher?->name,
            ];
        });

        // Cek pengajuan Izin/Cuti GTK aktif
        $activePermit = GtkPermit::where('user_id', $teacherId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', '!=', GtkPermit::STATUS_REJECTED)
            ->latest('id')
            ->first();

        $permitData = null;
        if ($activePermit) {
            $permitData = [
                'id' => $activePermit->id,
                'permission_type' => $activePermit->permission_type,
                'type_label' => $activePermit->type_label,
                'reason' => $activePermit->reason,
                'status' => $activePermit->status,
                'status_label' => $activePermit->status_label,
                'document_url' => $activePermit->document_url,
                'assignment_document_url' => $activePermit->assignment_document_url,
                'assignment_document_name' => $activePermit->assignment_document_name,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'teacher_id' => (int) $teacherId,
                'schedules' => $formattedSchedules,
                'total_schedules' => $formattedSchedules->count(),
                'active_permit' => $permitData,
            ],
        ]);
    }

    /**
     * Store new inval class assignment.
     */
    public function storePiketInval(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'absent_teacher_id' => 'required|exists:users,id',
            'schedule_id' => 'nullable|string',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'inval_teacher_id' => 'nullable|exists:users,id',
            'period_start' => 'nullable|integer|min:1|max:15',
            'period_end' => 'nullable|integer|min:1|max:15',
            'reason' => 'required|string|max:255',
            'assignment_material' => 'nullable|string',
            'assignment_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:10240',
            'use_permit_attachment' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $today = Carbon::today()->toDateString();
        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($today, $user, $operationalYear->name, $operationalYear->semester);

        $filePath = null;
        $fileName = null;
        if ($request->hasFile('assignment_file')) {
            $file = $request->file('assignment_file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('piket/inval_tasks', 'local');
        } elseif (! empty($validated['use_permit_attachment'])) {
            $gtkPermit = GtkPermit::where('user_id', $validated['absent_teacher_id'])
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->whereNotNull('assignment_document_path')
                ->latest('id')
                ->first();
            if ($gtkPermit) {
                $filePath = $gtkPermit->assignment_document_path;
                $fileName = $gtkPermit->assignment_document_name;
            }
        }

        // Jika opsi 'all' (1 hari penuh) dipilih
        if (($validated['schedule_id'] ?? null) === 'all') {
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';

            $schedules = LessonSchedule::where('teacher_id', $validated['absent_teacher_id'])
                ->where('day', $todayDay)
                ->orderBy('start_period')
                ->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru ini tidak memiliki jadwal KBM hari ini untuk ditugaskan secara 1 hari penuh.',
                ], 422);
            }

            $createdInvals = [];
            foreach ($schedules as $sch) {
                $inval = PiketInvalClass::create([
                    'piket_journal_id' => $journal->id,
                    'classroom_id' => $sch->classroom_id,
                    'absent_teacher_id' => $validated['absent_teacher_id'],
                    'subject_id' => $sch->subject_id,
                    'inval_teacher_id' => $validated['inval_teacher_id'] ?? null,
                    'period_start' => $sch->start_period,
                    'period_end' => $sch->end_period,
                    'reason' => $validated['reason'],
                    'assignment_material' => $validated['assignment_material'] ?? null,
                    'assignment_file_path' => $filePath,
                    'assignment_file_name' => $fileName,
                    'notes' => $validated['notes'] ?? null,
                ]);

                ActivityLogger::log('create', "Menugaskan guru inval (1 Hari Penuh): {$inval->classroom?->name}", $inval, [
                    'absent' => $inval->absent_teacher_id,
                    'inval' => $inval->inval_teacher_id,
                ]);

                try {
                    $this->telegramService->notifyInvalClassToWakaAndPiket($inval);
                } catch (\Throwable $e) {
                }

                $createdInvals[] = $inval;
            }

            GtkPermit::syncFromPiketInvals();

            $count = count($createdInvals);

            return response()->json([
                'success' => true,
                'message' => "Penugasan inval untuk seluruh ({$count}) jadwal kelas kosong 1 hari penuh berhasil disimpan.",
                'data' => $createdInvals[0] ?? null,
                'invals' => $createdInvals,
                'count' => $count,
            ]);
        }

        // Jika schedule spesifik dipilih
        if (! empty($validated['schedule_id']) && is_numeric($validated['schedule_id'])) {
            $sch = LessonSchedule::find($validated['schedule_id']);
            if ($sch) {
                $validated['classroom_id'] = $sch->classroom_id;
                $validated['subject_id'] = $sch->subject_id;
                $validated['period_start'] = $sch->start_period;
                $validated['period_end'] = $sch->end_period;
            }
        }

        if (empty($validated['classroom_id']) || empty($validated['period_start'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas dan jam pelajaran harus dipilih dari jadwal atau ditentukan secara manual.',
            ], 422);
        }

        $periodEnd = $validated['period_end'] ?? $validated['period_start'];

        $inval = PiketInvalClass::create([
            'piket_journal_id' => $journal->id,
            'classroom_id' => $validated['classroom_id'],
            'absent_teacher_id' => $validated['absent_teacher_id'],
            'subject_id' => $validated['subject_id'] ?? null,
            'inval_teacher_id' => $validated['inval_teacher_id'] ?? null,
            'period_start' => $validated['period_start'],
            'period_end' => $periodEnd,
            'reason' => $validated['reason'],
            'assignment_material' => $validated['assignment_material'] ?? null,
            'assignment_file_path' => $filePath,
            'assignment_file_name' => $fileName,
            'notes' => $validated['notes'] ?? null,
        ]);

        GtkPermit::syncFromPiketInvals();

        ActivityLogger::log('create', "Menugaskan guru inval: {$inval->classroom?->name}", $inval, [
            'absent' => $inval->absent_teacher_id,
            'inval' => $inval->inval_teacher_id,
        ]);

        try {
            $this->telegramService->notifyInvalClassToWakaAndPiket($inval);
        } catch (\Throwable $e) {
            // Silently continue
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan guru inval berhasil disimpan.',
            'data' => $inval,
        ]);
    }

    /**
     * Store new guest in piket guestbook.
     */
    public function storePiketGuest(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'guest_name' => 'required|string|max:255',
            'institution' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'purpose' => 'required|string|max:255',
            'meet_with' => 'required|string|max:255',
            'badge_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'check_in_time' => 'nullable|string',
        ]);

        $today = Carbon::today()->toDateString();
        $checkInTime = (! empty($validated['check_in_time'])) ? $validated['check_in_time'] : Carbon::now()->format('H:i');
        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($today, $user, $operationalYear->name, $operationalYear->semester);

        $guest = PiketGuest::create([
            'piket_journal_id' => $journal->id,
            'visit_date' => $today,
            'check_in_time' => $checkInTime,
            'guest_name' => $validated['guest_name'],
            'institution' => $validated['institution'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'purpose' => $validated['purpose'],
            'meet_with' => $validated['meet_with'],
            'badge_number' => $validated['badge_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);

        ActivityLogger::log('create', "Mencatat buku tamu piket: {$guest->guest_name} ({$guest->purpose})", $guest, [
            'guest' => $guest->guest_name,
            'meet_with' => $guest->meet_with,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Buku tamu berhasil dicatat.',
            'data' => $guest,
        ]);
    }

    /**
     * Mark checkout for a guest.
     */
    public function checkoutPiketGuest(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $guest = PiketGuest::findOrFail($id);
        $checkOutTime = Carbon::now()->format('H:i:s');
        $guest->update(['check_out_time' => $checkOutTime]);

        ActivityLogger::log('update', "Checkout tamu: {$guest->guest_name}", $guest, [
            'check_out' => $checkOutTime,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Tamu {$guest->guest_name} telah check-out pada {$checkOutTime}.",
            'data' => $guest,
        ]);
    }

    /**
     * Close/finalize today's piket journal.
     */
    public function closePiketJournal(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $today = Carbon::today()->toDateString();
        $journal = PiketJournal::whereDate('date', $today)->first();

        if (! $journal) {
            return response()->json(['success' => false, 'message' => 'Jurnal piket hari ini tidak ditemukan.'], 404);
        }

        $journal->update([
            'status' => 'selesai',
            'completed_at' => Carbon::now(),
            'completed_by' => $user->id,
            'general_notes' => $request->input('general_notes', $journal->general_notes),
        ]);

        ActivityLogger::log('update', "Menutup jurnal piket harian {$today}", $journal, [
            'completed_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jurnal piket harian berhasil divalidasi dan ditutup.',
            'data' => $journal,
        ]);
    }

    /**
     * Get Duty Swaps and available schedules for duty swapping.
     */
    public function getPiketDutySwaps(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $isManagement = $user->isSuperAdmin() || $user->isPrincipal() || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum());

        // My schedules that can be swapped
        $mySchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->get(['id', 'day', 'role', 'duty_location']);

        // Swaps query
        $query = PiketDutySwap::with(['originalSchedule', 'requester:id,name', 'replacementTeacher:id,name', 'approver:id,name'])
            ->latest('duty_date')
            ->latest('id');

        if (! $isManagement) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                    ->orWhere('replacement_teacher_id', $user->id);
            });
        }

        $swaps = $query->take(50)->get()->map(fn ($s) => [
            'id' => $s->id,
            'original_schedule_id' => $s->original_schedule_id,
            'schedule_day' => $s->originalSchedule?->day ?? '-',
            'requester_id' => $s->requester_id,
            'requester_name' => $s->requester?->name ?? 'Pemohon',
            'replacement_teacher_id' => $s->replacement_teacher_id,
            'replacement_teacher_name' => $s->replacementTeacher?->name ?? 'Pengganti',
            'duty_date' => $s->duty_date?->format('Y-m-d'),
            'duty_date_formatted' => $s->duty_date?->translatedFormat('d M Y') ?? '-',
            'reason' => $s->reason,
            'status' => $s->status,
            'can_action' => ($user->id === $s->replacement_teacher_id && $s->status === 'menunggu') || ($isManagement && $s->status === 'menunggu'),
            'created_at_human' => $s->created_at?->diffForHumans(),
        ]);

        $teachers = User::operationalPersonnel()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'my_schedules' => $mySchedules,
                'swaps' => $swaps,
                'teachers' => $teachers,
            ],
        ]);
    }

    /**
     * Store new duty swap proposal.
     */
    public function storePiketDutySwap(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'original_schedule_id' => 'required|exists:piket_schedules,id',
            'replacement_teacher_id' => 'required|exists:users,id',
            'duty_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|max:500',
        ]);

        if ($validated['replacement_teacher_id'] == $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat memilih diri sendiri sebagai pengganti.'], 422);
        }

        $schedule = PiketSchedule::findOrFail($validated['original_schedule_id']);

        $swap = PiketDutySwap::create([
            'original_schedule_id' => $schedule->id,
            'requester_id' => $user->id,
            'replacement_teacher_id' => $validated['replacement_teacher_id'],
            'duty_date' => $validated['duty_date'],
            'reason' => $validated['reason'],
            'status' => 'menunggu',
        ]);

        ActivityLogger::log('create', "Mengajukan tukar piket untuk tanggal {$swap->duty_date->format('d/m/Y')}", $swap, [
            'replacement_id' => $swap->replacement_teacher_id,
        ]);

        try {
            $this->telegramService->notifyDutySwapRequested($swap);
        } catch (\Throwable $e) {
            // Silently continue
        }

        return response()->json([
            'success' => true,
            'message' => 'Permohonan tukar jadwal piket berhasil diajukan.',
            'data' => $swap,
        ]);
    }

    /**
     * Approve or reject a duty swap request.
     */
    public function actionPiketDutySwap(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $swap = PiketDutySwap::with(['requester', 'replacementTeacher'])->findOrFail($id);

        $isAuthorized = ($user->id === $swap->replacement_teacher_id) || $user->canManagePiketSchedules();
        if (! $isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya guru pengganti atau manajemen yang dapat memproses permohonan ini.',
            ], 403);
        }

        if ($validated['action'] === 'approve') {
            $swap->update([
                'status' => 'disetujui',
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
            ]);

            ActivityLogger::log('update', "Menyetujui permohonan tukar jadwal piket dari {$swap->requester?->name}", $swap, [
                'status' => 'disetujui',
            ]);

            try {
                $this->telegramService->notifyDutySwapApproved($swap);
            } catch (\Throwable $e) {
                // Silently continue
            }

            return response()->json([
                'success' => true,
                'message' => 'Permohonan tukar jadwal piket telah disetujui.',
                'data' => $swap,
            ]);
        }

        $swap->update([
            'status' => 'ditolak',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        ActivityLogger::log('update', "Menolak permohonan tukar jadwal piket dari {$swap->requester?->name}", $swap, [
            'status' => 'ditolak',
        ]);

        try {
            $this->telegramService->notifyDutySwapRejected($swap);
        } catch (\Throwable $e) {
            // Silently continue
        }

        return response()->json([
            'success' => true,
            'message' => 'Permohonan tukar jadwal piket telah ditolak.',
            'data' => $swap,
        ]);
    }

    /**
     * Find or create PiketJournal safely across database drivers.
     */
    protected function getOrCreatePiketJournal(string $date, User $user, string $academicYear, ?string $semester = null): PiketJournal
    {
        $journal = PiketJournal::whereDate('date', $date)->first();
        if (! $journal) {
            $journal = PiketJournal::create([
                'date' => $date,
                'academic_year' => $academicYear,
                'semester' => $semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $user->id,
            ]);
        }

        return $journal;
    }
}
