<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\BkReferral;
use App\Models\Classroom;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\StudentVisitation;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\DailyKbmSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileTasksController extends Controller
{
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
        $bkData = null;
        if ($user->canOperateBk($operationalAcademicYear)) {
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

            $bkData = [
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
     * Bangun payload Live Monitoring terpadu (1 kartu adaptif untuk semua peran).
     */
    protected function buildLiveMonitoringData(
        User $user,
        Carbon $date,
        string $operationalAcademicYear,
        ?Classroom $myHomeroomClassroom
    ): ?array {
        $isPrincipal = $user->isPrincipal() || $user->isSuperAdmin();
        $isKurikulum = $user->isWakaKurikulum();
        $isKesiswaan = $user->isWakaKesiswaan() || $user->canManageDiscipline($operationalAcademicYear);
        $isHomeroom = (bool) $myHomeroomClassroom;

        if (! $isPrincipal && ! $isKurikulum && ! $isKesiswaan && ! $isHomeroom) {
            return null;
        }

        $roleKey = match (true) {
            $isPrincipal => 'principal',
            $isKurikulum => 'kurikulum',
            $isKesiswaan => 'kesiswaan',
            default => 'homeroom',
        };

        $roleLabel = match ($roleKey) {
            'principal' => 'Kepala Sekolah',
            'kurikulum' => 'Waka Kurikulum',
            'kesiswaan' => 'Kedisiplinan & Kesiswaan',
            default => 'Wali Kelas ('.($myHomeroomClassroom?->name ?? 'Perwalian').')',
        };

        $canViewStudentAll = $isPrincipal || $isKurikulum || $isKesiswaan;
        $canViewGtk = $isPrincipal || $isKurikulum;
        $canViewBk = $isPrincipal || $isKurikulum || $isKesiswaan || $user->canOperateBk($operationalAcademicYear);

        $targetDateStr = $date->toDateString();
        $isToday = $date->isToday();
        $currentTimeStr = now()->format('H:i');

        // 1. DATA SISWA (Presensi KBM & Perizinan Meja Piket)
        $permitsQuery = PiketStudentPermit::with(['student.classroom', 'classroom'])
            ->whereDate('permit_date', $targetDateStr)
            ->orderBy('created_at', 'desc');

        $sessionsQuery = SubjectAttendanceSession::whereDate('attendance_date', $targetDateStr);

        if (! $canViewStudentAll && $myHomeroomClassroom) {
            $permitsQuery->where('classroom_id', $myHomeroomClassroom->id);
            $sessionsQuery->where('classroom_id', $myHomeroomClassroom->id);
        }

        $studentPermits = $permitsQuery->get();
        $todaySessionIds = $sessionsQuery->pluck('id');

        $studentRecords = SubjectAttendanceRecord::with(['student.classroom', 'session.classroom', 'session.subject'])
            ->whereIn('subject_attendance_session_id', $todaySessionIds)
            ->get();

        // 8 Status Siswa
        $latePermits = $studentPermits->where('permit_type', 'terlambat')->unique('student_id')->values();
        $earlyLeavePermits = $studentPermits->where('permit_type', 'izin_pulang_cepat')->unique('student_id')->values();
        $izinPermits = $studentPermits->where('permit_type', 'izin_tidak_masuk')->unique('student_id')->values();

        $sakitPermits = $izinPermits->filter(fn ($p) => strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit'))->values();
        $murniIzinPermits = $izinPermits->reject(fn ($p) => strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit'))->values();

        $sakitRecords = $studentRecords->where('status', 's')->values();
        $murniIzinRecords = $studentRecords->where('status', 'i')->values();
        $dispenRecords = $studentRecords->where('status', 'd')->values();
        $alfaRecords = $studentRecords->where('status', 'a')->values();

        $allSakitStudentIds = $sakitPermits->pluck('student_id')->concat($sakitRecords->pluck('student_id'))->filter()->unique();
        $allMurniIzinStudentIds = $murniIzinPermits->pluck('student_id')->concat($murniIzinRecords->pluck('student_id'))->filter()->diff($allSakitStudentIds)->unique();
        $allDispenStudentIds = $dispenRecords->pluck('student_id')->filter()->diff($allSakitStudentIds)->diff($allMurniIzinStudentIds)->unique();

        $latestStatusPerStudent = $studentRecords->sortByDesc('subject_attendance_session_id')->unique('student_id');
        $hadirStudents = $latestStatusPerStudent->where('status', 'h')->values();

        // Indikasi Bolos KBM
        $fingerprintLogs = AttendanceLog::where('user_type', 'student')
            ->whereDate('scanned_at', $targetDateStr)
            ->pluck('student_id')
            ->filter()
            ->unique();

        $earlyLeaveStudentIds = $earlyLeavePermits->pluck('student_id')->filter()->unique();
        $recordsByStudent = $studentRecords->groupBy('student_id');
        $truancyStudents = collect();

        foreach ($recordsByStudent as $sId => $recs) {
            if ($earlyLeaveStudentIds->contains($sId)) {
                continue;
            }

            $hasPresent = $recs->contains('status', 'h');
            $hasAlfa = $recs->contains('status', 'a');
            $hasFp = $fingerprintLogs->contains($sId);

            if (($hasPresent && $hasAlfa) || ($hasFp && $hasAlfa)) {
                $st = $recs->first()?->student;
                $alfaSubjects = $recs->where('status', 'a')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');
                $presentSubjects = $recs->where('status', 'h')->map(fn ($r) => $r->session?->subject?->name)->filter()->unique()->implode(', ');

                $truancyStudents->push((object) [
                    'student_id' => $sId,
                    'student_name' => $st?->name ?? 'Siswa',
                    'classroom_name' => $st?->classroom?->name ?? '-',
                    'alfa_count_jp' => $recs->where('status', 'a')->count(),
                    'alfa_subjects' => $alfaSubjects ?: 'Mapel Tertentu',
                    'present_subjects' => $presentSubjects ?: ($hasFp ? 'Scan Masuk Gerbang' : '-'),
                ]);
            }
        }

        $truancyStudentIds = $truancyStudents->pluck('student_id');
        $pureAlfaRecords = $alfaRecords->reject(fn ($r) => $truancyStudentIds->contains($r->student_id))->values();
        $pureAlfaCount = $pureAlfaRecords->pluck('student_id')->unique()->count();

        // 2. DATA GTK (Khusus Kurikulum & Kepala Sekolah)
        $gtkData = null;
        if ($canViewGtk) {
            $snapshotService = app(DailyKbmSnapshotService::class);
            $snapshot = $snapshotService->getOrGenerateSnapshot($date);
            $dailyRosters = $snapshot['rosters'] ?? collect();
            $dailySchedules = $snapshot['schedules'] ?? collect();

            $periodTimes = LessonSchedule::periodTimes($date->dayOfWeekIso === 5 ? 'jumat' : 'senin');

            // Presensi Log GTK pada tanggal target
            $gtkScans = AttendanceLog::with('user')
                ->where('user_type', 'teacher')
                ->whereDate('scanned_at', $targetDateStr)
                ->orderBy('scanned_at', 'asc')
                ->get();

            $scansByUserId = $gtkScans->groupBy('user_id');

            // GTK Izin / Cuti
            $gtkPermits = GtkPermit::approved()->with('user')
                ->whereDate('start_date', '<=', $targetDateStr)
                ->whereDate('end_date', '>=', $targetDateStr)
                ->get();
            $gtkPermitUserIds = $gtkPermits->pluck('user_id')->unique();

            // Evaluasi Setiap GTK Berdasarkan Kewajiban Mengajar / Tugasnya Hari Ini
            $expectedGtkUsers = $dailyRosters->where('is_expected_checkin', true)
                ->map(fn ($r) => $r->user)
                ->filter()
                ->unique('id')
                ->values();

            $obligatedGtkList = collect();
            $gtkPresentList = collect();
            $gtkLateList = collect();
            $gtkAwaitingList = collect();

            foreach ($expectedGtkUsers as $u) {
                // Cari jadwal mengajar aktif guru hari ini
                $teacherSchedules = $dailySchedules->where('is_exempted', false)
                    ->where('teacher_id', $u->id)
                    ->sortBy('start_period');

                $isStaffTu = $u->isStaff();
                $isPiket = $u->isPiketOfficerToday($targetDateStr);
                $hasSchedule = $teacherSchedules->isNotEmpty();

                // Tentukan jam kewajiban masuk (Datang)
                $expectedStartTimeStr = null;
                $dutyLabel = 'Tugas';

                if ($isPiket) {
                    $expectedStartTimeStr = '07:00';
                    $dutyLabel = 'Guru Piket Sekolah (07:00)';
                } elseif ($isStaffTu) {
                    $expectedStartTimeStr = '07:00';
                    $dutyLabel = ($u->position ?: 'Staf TU').' (07:00)';
                } elseif ($hasSchedule) {
                    $earliestSched = $teacherSchedules->first();
                    $periodNum = (int) $earliestSched->start_period;
                    $expectedStartTimeStr = $earliestSched->start_time ?: ($periodTimes[$periodNum]['start'] ?? '07:00');
                    $dutyLabel = "Jam ke-{$periodNum} ({$expectedStartTimeStr} WIB)";
                }

                // Jika bukan staf, bukan piket, dan tidak punya jam mengajar hari ini: TIDAK WAJIB MASUK
                if (! $expectedStartTimeStr) {
                    continue;
                }

                $obligatedGtkList->push($u);

                $userScans = $scansByUserId->get($u->id);
                $firstScan = $userScans ? $userScans->sortBy('scanned_at')->first() : null;
                $hasPermit = $gtkPermitUserIds->contains($u->id);

                if ($firstScan) {
                    $scanTimeStr = $firstScan->scanned_at ? $firstScan->scanned_at->format('H:i') : null;
                    $isLate = false;
                    if ($scanTimeStr && $scanTimeStr > $expectedStartTimeStr) {
                        $isLate = true;
                    } elseif ($firstScan->status === 'terlambat') {
                        $isLate = true;
                    }

                    $presentItem = [
                        'user_name' => $u->name,
                        'time' => $scanTimeStr ?? '-',
                        'duty_info' => $dutyLabel,
                    ];

                    $gtkPresentList->push($presentItem);

                    if ($isLate) {
                        $gtkLateList->push([
                            'user_name' => $u->name,
                            'time' => $scanTimeStr ?? '-',
                            'duty_info' => $dutyLabel." • Masuk {$scanTimeStr}",
                        ]);
                    }
                } elseif (! $hasPermit) {
                    // Belum ada scan dan belum ada izin:
                    // Cek apakah jam sekarang sudah melewati jam kewajiban masuk guru tersebut
                    $isPastDutyTime = ! $isToday || $currentTimeStr >= $expectedStartTimeStr;
                    if ($isPastDutyTime) {
                        $gtkAwaitingList->push([
                            'user_name' => $u->name,
                            'position' => $u->position ?? 'Guru',
                            'duty_info' => $dutyLabel,
                        ]);
                    }
                }
            }

            // Inval Mengajar Hari Ini
            $todayInvals = PiketInvalClass::with(['classroom', 'absentTeacher', 'subject', 'invalTeacher'])
                ->whereHas('journal', fn ($j) => $j->whereDate('date', $targetDateStr))
                ->get();

            // Belum Absen Mapel KBM
            $activeScheduleTargets = $dailySchedules->where('is_exempted', false)->values();
            $unattendedSchedules = collect();
            if ($isToday) {
                $unattendedSchedules = $activeScheduleTargets->filter(function ($t) use ($currentTimeStr, $periodTimes) {
                    if ($t->attendanceSession) {
                        return false;
                    }
                    $periodNum = (int) $t->start_period;
                    $startTime = $t->start_time ?: ($periodTimes[$periodNum]['start'] ?? '07:00');

                    return $startTime <= $currentTimeStr;
                })->values();
            }

            $gtkData = [
                'total_expected' => $obligatedGtkList->count(),
                'hadir' => $gtkPresentList->count(),
                'terlambat' => $gtkLateList->count(),
                'belum_masuk' => $gtkAwaitingList->count(),
                'izin' => $gtkPermits->whereIn('user_id', $obligatedGtkList->pluck('id'))->count(),
                'inval' => $todayInvals->count(),
                'unattended_kbm' => $unattendedSchedules->count(),
                'detail' => [
                    'hadir' => $gtkPresentList->values(),
                    'terlambat' => $gtkLateList->values(),
                    'belum_masuk' => $gtkAwaitingList->values(),
                    'izin' => $gtkPermits->whereIn('user_id', $obligatedGtkList->pluck('id'))->map(fn ($p) => [
                        'user_name' => $p->user?->name ?? 'GTK',
                        'permit_type' => $p->permit_type,
                        'reason' => $p->reason,
                    ])->values(),
                    'inval' => $todayInvals->map(fn ($inv) => [
                        'classroom' => $inv->classroom?->name ?? '-',
                        'subject' => $inv->subject?->name ?? '-',
                        'absent_teacher' => $inv->absentTeacher?->name ?? 'Guru',
                        'inval_teacher' => $inv->invalTeacher?->name ?? 'Guru Pengganti',
                        'period' => "Jam {$inv->period_start}-{$inv->period_end}",
                    ])->values(),
                    'unattended_kbm' => $unattendedSchedules->map(fn ($sch) => [
                        'teacher_name' => $sch->teacher?->name ?? 'Guru',
                        'classroom' => $sch->classroom?->name ?? '-',
                        'subject' => $sch->subject?->name ?? '-',
                        'period' => "Jam {$sch->start_period}-{$sch->end_period}",
                    ])->values(),
                ],
            ];
        }

        // 3. EVENT TIMELINE (Feed Peristiwa Hari Ini Secara Kronologis)
        $timelineEvents = collect();

        // (a) Kejadian / Pelanggaran Meja Piket
        $incidentsQuery = PiketIncident::with(['student.classroom', 'classroom', 'recordedBy'])
            ->whereDate('incident_date', $targetDateStr);

        if (! $canViewStudentAll && $myHomeroomClassroom) {
            $incidentsQuery->where('classroom_id', $myHomeroomClassroom->id);
        }

        foreach ($incidentsQuery->get() as $inc) {
            $timelineEvents->push([
                'type' => 'incident',
                'time' => substr($inc->incident_time, 0, 5) ?: '-',
                'title' => $inc->category_label ?? 'Pelanggaran Siswa',
                'badge' => ($inc->penalty_points ?? 0).' Poin',
                'badge_color' => 'rose',
                'person_name' => $inc->student?->name ?? $inc->student_name ?? 'Siswa',
                'classroom_name' => $inc->classroom?->name ?? $inc->student?->classroom?->name ?? '-',
                'description' => $inc->description,
                'action_taken' => $inc->action_taken,
                'officer' => $inc->recordedBy?->name ?? 'Piket',
            ]);
        }

        // (b) Izin Siswa Terlambat Masuk
        foreach ($latePermits as $lp) {
            $timelineEvents->push([
                'type' => 'permit_late',
                'time' => $lp->created_at ? $lp->created_at->format('H:i') : '-',
                'title' => 'Terlambat Masuk Gerbang',
                'badge' => 'Jam ke-'.($lp->period_assigned ?? '1'),
                'badge_color' => 'amber',
                'person_name' => $lp->student?->name ?? 'Siswa',
                'classroom_name' => $lp->classroom?->name ?? $lp->student?->classroom?->name ?? '-',
                'description' => $lp->reason ?? 'Izin masuk terlambat',
                'action_taken' => 'Diberikan surat izin piket',
                'officer' => $lp->giver?->name ?? 'Piket',
            ]);
        }

        // (c) Izin Siswa Pulang Cepat
        foreach ($earlyLeavePermits as $ep) {
            $timelineEvents->push([
                'type' => 'permit_early',
                'time' => $ep->created_at ? $ep->created_at->format('H:i') : '-',
                'title' => 'Izin Pulang Lebih Awal',
                'badge' => 'Pulang Cepat',
                'badge_color' => 'purple',
                'person_name' => $ep->student?->name ?? 'Siswa',
                'classroom_name' => $ep->classroom?->name ?? $ep->student?->classroom?->name ?? '-',
                'description' => $ep->reason ?? 'Izin pulang lebih awal',
                'action_taken' => 'Disetujui piket',
                'officer' => $ep->giver?->name ?? 'Piket',
            ]);
        }

        // (d) Siswa Bolos KBM
        foreach ($truancyStudents as $ts) {
            $timelineEvents->push([
                'type' => 'truancy',
                'time' => '-',
                'title' => 'Indikasi Bolos Pelajaran',
                'badge' => $ts->alfa_count_jp.' JP Alfa',
                'badge_color' => 'slate',
                'person_name' => $ts->student_name,
                'classroom_name' => $ts->classroom_name,
                'description' => 'Alfa di mapel: '.$ts->alfa_subjects,
                'action_taken' => 'Terdeteksi di: '.$ts->present_subjects,
                'officer' => 'Sistem KBM',
            ]);
        }

        // (e) Rujukan & Layanan BK (jika berwenang)
        if ($canViewBk) {
            $bkReferralsQuery = PiketIncident::where('follow_up_target', 'rujuk_bk')
                ->whereDate('incident_date', $targetDateStr)
                ->with(['student.classroom', 'bkReferral']);

            if (! $canViewStudentAll && $myHomeroomClassroom) {
                $bkReferralsQuery->where('classroom_id', $myHomeroomClassroom->id);
            }

            foreach ($bkReferralsQuery->get() as $bkr) {
                $timelineEvents->push([
                    'type' => 'bk_referral',
                    'time' => substr($bkr->incident_time, 0, 5) ?: '-',
                    'title' => 'Rujukan Kasus ke BK',
                    'badge' => $bkr->bkReferral?->status_label ?? 'Rujukan BK',
                    'badge_color' => 'indigo',
                    'person_name' => $bkr->student?->name ?? 'Siswa',
                    'classroom_name' => $bkr->classroom?->name ?? '-',
                    'description' => $bkr->description,
                    'action_taken' => 'Kasus dilimpahkan ke Guru BK',
                    'officer' => 'Meja Piket',
                ]);
            }
        }

        // (f) GTK Izin & Inval (jika berwenang)
        if ($canViewGtk) {
            foreach ($gtkPermits ?? [] as $gp) {
                $timelineEvents->push([
                    'type' => 'gtk_permit',
                    'time' => '-',
                    'title' => 'Izin / Cuti Guru GTK',
                    'badge' => ucfirst(str_replace('_', ' ', $gp->permit_type)),
                    'badge_color' => 'purple',
                    'person_name' => $gp->user?->name ?? 'GTK',
                    'classroom_name' => 'Guru/Staf',
                    'description' => $gp->reason ?? 'Izin GTK',
                    'action_taken' => 'Telah disetujui pimpinan',
                    'officer' => 'Kepala Sekolah',
                ]);
            }

            foreach ($todayInvals ?? [] as $inv) {
                $timelineEvents->push([
                    'type' => 'inval',
                    'time' => '-',
                    'title' => 'Penugasan Guru Inval',
                    'badge' => "Jam {$inv->period_start}-{$inv->period_end}",
                    'badge_color' => 'blue',
                    'person_name' => $inv->invalTeacher?->name ?? 'Guru Pengganti',
                    'classroom_name' => $inv->classroom?->name ?? '-',
                    'description' => 'Menggantikan '.($inv->absentTeacher?->name ?? 'Guru').' ('.($inv->subject?->name ?? 'Mapel').')',
                    'action_taken' => $inv->assignment_material ?: 'Pendampingan kelas',
                    'officer' => 'Piket Inval',
                ]);
            }
        }

        $timelineEvents = $timelineEvents->sortByDesc('time')->values();

        return [
            'role_key' => $roleKey,
            'role_label' => $roleLabel,
            'scope_label' => $canViewStudentAll ? 'Seluruh Siswa (Tingkat Sekolah)' : 'Kelas '.($myHomeroomClassroom?->name ?? '-'),
            'can_view_student_all' => $canViewStudentAll,
            'can_view_gtk' => $canViewGtk,
            'can_view_bk' => $canViewBk,
            'siswa' => [
                'hadir' => $hadirStudents->count(),
                'telat' => $latePermits->count(),
                'sakit' => $allSakitStudentIds->count(),
                'izin' => $allMurniIzinStudentIds->count(),
                'dispen' => $allDispenStudentIds->count(),
                'pulang' => $earlyLeavePermits->count(),
                'alfa' => $pureAlfaCount,
                'bolos' => $truancyStudents->count(),
                'detail' => [
                    'hadir' => $hadirStudents->map(fn ($r) => [
                        'name' => $r->student?->name ?? 'Siswa',
                        'classroom' => $r->session?->classroom?->name ?? $r->student?->classroom?->name ?? '-',
                    ])->take(50)->values(),
                    'telat' => $latePermits->map(fn ($p) => [
                        'name' => $p->student?->name ?? 'Siswa',
                        'classroom' => $p->classroom?->name ?? '-',
                        'reason' => $p->reason ?? 'Terlambat masuk',
                        'time' => $p->created_at?->format('H:i') ?? '-',
                    ])->values(),
                    'sakit' => $allSakitStudentIds->map(function ($id) use ($studentRecords, $sakitPermits) {
                        $p = $sakitPermits->firstWhere('student_id', $id);
                        $r = $studentRecords->firstWhere('student_id', $id);
                        $st = $p?->student ?? $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $p?->classroom?->name ?? $st?->classroom?->name ?? '-',
                            'reason' => $p?->reason ?? 'Sakit',
                        ];
                    })->values(),
                    'izin' => $allMurniIzinStudentIds->map(function ($id) use ($studentRecords, $murniIzinPermits) {
                        $p = $murniIzinPermits->firstWhere('student_id', $id);
                        $r = $studentRecords->firstWhere('student_id', $id);
                        $st = $p?->student ?? $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $p?->classroom?->name ?? $st?->classroom?->name ?? '-',
                            'reason' => $p?->reason ?? 'Izin',
                        ];
                    })->values(),
                    'dispen' => $allDispenStudentIds->map(function ($id) use ($studentRecords) {
                        $r = $studentRecords->firstWhere('student_id', $id);

                        return [
                            'name' => $r?->student?->name ?? 'Siswa',
                            'classroom' => $r?->session?->classroom?->name ?? '-',
                            'reason' => 'Dispensasi Kegiatan / Tugas',
                        ];
                    })->values(),
                    'pulang' => $earlyLeavePermits->map(fn ($p) => [
                        'name' => $p->student?->name ?? 'Siswa',
                        'classroom' => $p->classroom?->name ?? '-',
                        'reason' => $p->reason ?? 'Izin pulang awal',
                        'time' => $p->created_at?->format('H:i') ?? '-',
                    ])->values(),
                    'alfa' => $pureAlfaRecords->unique('student_id')->map(fn ($r) => [
                        'name' => $r->student?->name ?? 'Siswa',
                        'classroom' => $r->session?->classroom?->name ?? '-',
                        'reason' => 'Tanpa Keterangan',
                    ])->values(),
                    'bolos' => $truancyStudents->map(fn ($t) => [
                        'name' => $t->student_name,
                        'classroom' => $t->classroom_name,
                        'reason' => "Alfa di {$t->alfa_subjects} (Hadir di {$t->present_subjects})",
                    ])->values(),
                ],
            ],
            'gtk' => $gtkData,
            'timeline' => $timelineEvents,
        ];
    }

    /**
     * Update status rujukan BK secara cepat dari mobile.
     */
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
}
