<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeroomAttendanceRecapController;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketDutySwap;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $canAccessPiket = $user->canAccessMejaPiket($operationalAcademicYear);
        $isManagement = $user->isSuperAdmin()
            || $user->isPrincipal()
            || $user->isVicePrincipal();

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

        if (! $myHomeroomClassroom) {
            $myHomeroomClassroom = $user->homeroomClassrooms()->where('is_active', true)->first();
        }

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

            $homeroomStudents = $myHomeroomClassroom->students()->get()->keyBy('id');

            // 1. Hadir
            $hadirDetails = collect();
            foreach ($latestStatusPerStudent->where('status', 'h') as $rec) {
                $st = $rec->student ?? $homeroomStudents->get($rec->student_id);
                if ($st) {
                    $hadirDetails->put($st->id, [
                        'student_id' => $st->id,
                        'name' => $st->name,
                        'nis' => $st->nis ?? '',
                        'time' => $rec->created_at ? $rec->created_at->format('H:i') : '-',
                        'reason' => 'Hadir di '.($rec->session?->subject?->name ?? 'KBM'),
                    ]);
                }
            }
            foreach ($studentFingerprintLogs as $fId) {
                if (! $hadirDetails->has($fId) && $homeroomStudents->has($fId)) {
                    if (! $allSakitStudentIds->contains($fId) && ! $allMurniIzinStudentIds->contains($fId) && ! $allDispenStudentIds->contains($fId) && ! $pureAlfaRecords->pluck('student_id')->contains($fId)) {
                        $st = $homeroomStudents->get($fId);
                        $hadirDetails->put($fId, [
                            'student_id' => $fId,
                            'name' => $st->name,
                            'nis' => $st->nis ?? '',
                            'time' => '-',
                            'reason' => 'Presensi Masuk Gerbang',
                        ]);
                    }
                }
            }

            // 2. Late / Telat
            $lateDetails = $homeroomLatePermits->map(function ($lp) use ($homeroomStudents) {
                $st = $lp->student ?? $homeroomStudents->get($lp->student_id);

                return [
                    'student_id' => $lp->student_id,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => ! empty($lp->entry_time) ? substr($lp->entry_time, 0, 5) : ($lp->created_at ? $lp->created_at->format('H:i') : '-'),
                    'reason' => $lp->reason ?: ('Terlambat masuk (Jam ke-'.($lp->period_assigned ?? '1').')'),
                ];
            })->values();

            // 3. Sakit
            $sakitDetails = collect();
            foreach ($allSakitStudentIds as $sId) {
                $p = $homeroomSakitPermits->firstWhere('student_id', $sId);
                $r = $homeroomSakitRecords->firstWhere('student_id', $sId);
                $st = $p?->student ?? $r?->student ?? $homeroomStudents->get($sId);
                $sakitDetails->push([
                    'student_id' => $sId,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => $p?->created_at ? $p->created_at->format('H:i') : ($r?->created_at ? $r->created_at->format('H:i') : '-'),
                    'reason' => $p?->reason ?: ($r?->notes ?: 'Keterangan Sakit'),
                ]);
            }

            // 4. Izin
            $izinDetails = collect();
            foreach ($allMurniIzinStudentIds as $sId) {
                $p = $homeroomMurniIzinPermits->firstWhere('student_id', $sId);
                $r = $homeroomMurniIzinRecords->firstWhere('student_id', $sId);
                $st = $p?->student ?? $r?->student ?? $homeroomStudents->get($sId);
                $izinDetails->push([
                    'student_id' => $sId,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => $p?->created_at ? $p->created_at->format('H:i') : ($r?->created_at ? $r->created_at->format('H:i') : '-'),
                    'reason' => $p?->reason ?: ($r?->notes ?: 'Izin Berhalangan'),
                ]);
            }

            // 5. Dispen
            $dispenDetails = collect();
            foreach ($allDispenStudentIds as $sId) {
                $r = $homeroomDispenRecords->firstWhere('student_id', $sId);
                $st = $r?->student ?? $homeroomStudents->get($sId);
                $dispenDetails->push([
                    'student_id' => $sId,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => $r?->created_at ? $r->created_at->format('H:i') : '-',
                    'reason' => $r?->notes ?: 'Dispensasi Tugas/Lomba',
                ]);
            }

            // 6. Pulang
            $pulangDetails = $homeroomEarlyLeavePermits->map(function ($ep) use ($homeroomStudents) {
                $st = $ep->student ?? $homeroomStudents->get($ep->student_id);

                return [
                    'student_id' => $ep->student_id,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => ! empty($ep->entry_time) ? substr($ep->entry_time, 0, 5) : ($ep->created_at ? $ep->created_at->format('H:i') : '-'),
                    'reason' => $ep->reason ?: 'Izin Pulang Lebih Awal',
                ];
            })->values();

            // 7. Alfa
            $alfaDetails = collect();
            $pureAlfaStudentIds = $pureAlfaRecords->pluck('student_id')->unique();
            foreach ($pureAlfaStudentIds as $sId) {
                $r = $pureAlfaRecords->firstWhere('student_id', $sId);
                $st = $r?->student ?? $homeroomStudents->get($sId);
                $alfaDetails->push([
                    'student_id' => $sId,
                    'name' => $st?->name ?? 'Siswa',
                    'nis' => $st?->nis ?? '',
                    'time' => $r?->created_at ? $r->created_at->format('H:i') : '-',
                    'reason' => $r?->notes ?: 'Tanpa Keterangan (Alfa)',
                ]);
            }

            // 8. Truancy / Bolos
            $truancyDetails = $truancyStudents->map(function ($ts) {
                return [
                    'student_id' => $ts['student_id'],
                    'name' => $ts['student_name'],
                    'nis' => '',
                    'time' => '-',
                    'reason' => 'Alfa di '.$ts['alfa_subjects'].' (Hadir di '.$ts['present_subjects'].')',
                ];
            })->values();

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
                'details' => [
                    'hadir' => $hadirDetails->values(),
                    'late' => $lateDetails->values(),
                    'telat' => $lateDetails->values(),
                    'sakit' => $sakitDetails->values(),
                    'izin' => $izinDetails->values(),
                    'dispen' => $dispenDetails->values(),
                    'pulang' => $pulangDetails->values(),
                    'alfa' => $alfaDetails->values(),
                    'truancy' => $truancyDetails->values(),
                    'bolos' => $truancyDetails->values(),
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
        $canApproveSupervision = $user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision');
        $pendingSupervisions = collect();
        if ($canApproveSupervision) {
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
        $myTeacherActiveCount = Supervision::where('teacher_id', $user->id)
            ->whereIn('status', ['terjadwal', 'praobservasi', 'observasi'])
            ->count();
        $mySupervisorActiveCount = Supervision::where('supervisor_id', $user->id)
            ->whereIn('status', ['terjadwal', 'praobservasi', 'observasi', 'pascaobservasi'])
            ->count();
        $hasSupervisionAgenda = $myTeacherActiveCount > 0 || $mySupervisorActiveCount > 0 || $pendingSupervisions->isNotEmpty();
        $hasAnySupervision = $hasSupervisionAgenda;

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
                    'can_approve' => $canApproveSupervision,
                    'my_teacher_active_count' => $myTeacherActiveCount,
                    'my_supervisor_active_count' => $mySupervisorActiveCount,
                    'has_any_supervision' => $hasAnySupervision,
                    'has_agenda' => $hasSupervisionAgenda,
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
                    'is_vice_principal' => $user->isVicePrincipal(),
                    'is_waka_sarpras' => $user->isWakaSarpras(),
                    'is_waka_humas' => $user->isWakaHumas(),
                    'can_operate_bk' => $user->canOperateBk(),
                    'can_access_teacher_report' => $user->canAccessTeacherAttendanceReport(),
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

        if (! $user->canAccessMejaPiket($academicYear)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Meja piket hanya diperuntukkan guru piket, kepala sekolah, semua waka, dan tim kedisiplinan.',
            ], 403);
        }

        $isOfficerToday = $user->isPiketOfficerToday($academicYear);
        $canOperate = $user->canOperatePiket($academicYear) || $isOfficerToday;
        $isCoordinator = $user->isPiketCoordinator();
        $isManagement = $user->isSuperAdmin() || $user->isPrincipal() || $user->isVicePrincipal();
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

        $periodTimes = LessonSchedule::periodTimes($todayDay);
        $lastPeriod = ! empty($periodTimes) ? end($periodTimes) : null;
        $schoolEndTime = $lastPeriod['end'] ?? '15:00';
        $now = Carbon::now();
        $endCarbon = Carbon::createFromTimeString($schoolEndTime);
        $isNearOrPastClosing = $now->greaterThanOrEqualTo($endCarbon->copy()->subMinutes(45));
        $coordSchedule = $schedulesToday->firstWhere('role', 'koordinator');
        $effectiveCoordinatorId = $coordSchedule?->teacher_id;
        if ($coordSchedule) {
            $swap = $approvedSwapsToday->firstWhere('original_schedule_id', $coordSchedule->id)
                ?? $approvedSwapsToday->firstWhere('requester_id', $coordSchedule->teacher_id);
            if ($swap && $swap->replacement_teacher_id) {
                $effectiveCoordinatorId = $swap->replacement_teacher_id;
            }
        }
        if (! $effectiveCoordinatorId && $journal->coordinator_id) {
            $effectiveCoordinatorId = $journal->coordinator_id;
        }

        $isCoordinatorToday = $effectiveCoordinatorId && ((int) $user->id === (int) $effectiveCoordinatorId);
        $shouldRemindCloseJournal = $isCoordinatorToday && $journal->status !== 'selesai' && $isNearOrPastClosing;

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
                'school_end_time' => $schoolEndTime,
                'is_near_closing_time' => $isNearOrPastClosing,
                'should_remind_close_journal' => $shouldRemindCloseJournal,
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
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
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

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($todayStr, $user, $operationalYear->name, $operationalYear->semester);

        $studentIds = collect($validated['student_ids'] ?? [])->filter()->unique()->values();
        if ($studentIds->isEmpty() && ! empty($validated['student_id'])) {
            $studentIds = collect([(int) $validated['student_id']]);
        }

        // Mode 1: Multi Siswa / Siswa dari Database
        if ($studentIds->isNotEmpty()) {
            $students = Student::with('classroom')->whereIn('id', $studentIds)->get();
            if ($students->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa yang dipilih tidak ditemukan.',
                ], 422);
            }

            $batchId = $students->count() > 1
                ? 'GRP-'.Carbon::parse($permitDate)->format('Ymd').'-'.Str::upper(Str::random(5))
                : null;

            $createdPermits = collect();
            $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count();

            DB::transaction(function () use (
                $students, $journal, $todayStr, $permitDate, $validated, $entryTime,
                $departureTime, $periodAssigned, $user, $batchId, &$countToday, &$createdPermits
            ) {
                foreach ($students as $st) {
                    $existing = PiketStudentPermit::where('student_id', $st->id)
                        ->where('permit_type', $validated['permit_type'])
                        ->whereDate('permit_date', $todayStr)
                        ->first();

                    if ($existing) {
                        continue;
                    }

                    $countToday++;
                    $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

                    $permit = PiketStudentPermit::create([
                        'piket_journal_id' => $journal->id,
                        'permit_number' => $permitNumber,
                        'batch_id' => $batchId,
                        'permit_date' => $permitDate,
                        'student_id' => $st->id,
                        'student_name' => $st->name,
                        'classroom_id' => $st->classroom_id,
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
                        'batch_id' => $batchId,
                    ]);

                    $createdPermits->push($permit);
                }
            });

            if ($createdPermits->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seluruh siswa yang dipilih sudah memiliki surat izin jenis ini untuk hari ini.',
                ], 422);
            }

            $groupedByClass = $createdPermits->groupBy('classroom_id');

            // Send Telegram notifications per classroom
            try {
                $dayNames = [
                    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
                ];
                $dayName = $dayNames[Carbon::parse($permitDate)->dayOfWeekIso] ?? 'Senin';

                foreach ($groupedByClass as $clsId => $classPermits) {
                    $classroom = Classroom::with('homeroomTeacher')->find($clsId);
                    $homeroomTeacher = $classroom?->homeroomTeacher;

                    $scheduleQuery = LessonSchedule::where('classroom_id', $clsId)
                        ->where('day', $dayName)
                        ->with('teacher');

                    if (! empty($periodAssigned) && is_numeric($periodAssigned)) {
                        $p = (int) $periodAssigned;
                        $scheduleQuery->where('start_period', '<=', $p)
                            ->where('end_period', '>=', $p);
                    }
                    $subjectTeacher = $scheduleQuery->first()?->teacher;

                    foreach ($classPermits as $singlePermit) {
                        if ($validated['permit_type'] === 'terlambat') {
                            $this->telegramService->notifyStudentLate($singlePermit, $subjectTeacher, $homeroomTeacher);
                        } else {
                            $this->telegramService->notifyStudentPermit($singlePermit, $homeroomTeacher, $subjectTeacher);
                        }
                    }
                }
            } catch (\Throwable $e) {
            }

            $printUrl = $batchId
                ? route('piket.permits.batch-slip', ['batchId' => $batchId, 'autoprint' => 1])
                : route('piket.permits.slip', ['permit' => $createdPermits->first()->id, 'autoprint' => 1]);

            $classCount = $groupedByClass->count();
            $studentCount = $createdPermits->count();
            $msg = $batchId
                ? "Surat izin rombongan berhasil diterbitkan untuk {$studentCount} siswa ({$classCount} rombel)."
                : "Surat izin {$createdPermits->first()->permit_number} berhasil diterbitkan.";

            return response()->json([
                'success' => true,
                'is_batch' => (bool) $batchId,
                'batch_id' => $batchId,
                'total_students' => $studentCount,
                'total_classes' => $classCount,
                'message' => $msg,
                'print_url' => $printUrl,
                'data' => array_merge($createdPermits->first()->toArray(), [
                    'print_url' => $printUrl,
                ]),
                'permits' => $createdPermits->values(),
            ]);
        }

        // Mode 2: Manual entry tanpa memilih student_id dari daftar
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

        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $permitDate,
            'student_id' => null,
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

        $isManagement = $user->isSuperAdmin() || $user->isPrincipal() || $user->isVicePrincipal();

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
            $dayNames = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ];
            $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
            $dayName = $dayNames[$dayOfWeek] ?? 'Senin';

            $coordSchedule = PiketSchedule::where('day', $dayName)
                ->where('role', 'koordinator')
                ->where('is_active', true)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->first();

            $coordinatorId = $coordSchedule?->teacher_id;
            if ($coordSchedule) {
                $swap = PiketDutySwap::where('status', 'disetujui')
                    ->whereDate('duty_date', $date)
                    ->where(function ($q) use ($coordSchedule) {
                        $q->where('original_schedule_id', $coordSchedule->id)
                            ->orWhere('requester_id', $coordSchedule->teacher_id);
                    })
                    ->first();
                if ($swap && $swap->replacement_teacher_id) {
                    $coordinatorId = $swap->replacement_teacher_id;
                }
            }

            $journal = PiketJournal::create([
                'date' => $date,
                'academic_year' => $academicYear,
                'semester' => $semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $coordinatorId ?? $user->id,
            ]);
        } elseif (! $journal->coordinator_id) {
            $dayNames = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ];
            $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
            $dayName = $dayNames[$dayOfWeek] ?? 'Senin';

            $coordSchedule = PiketSchedule::where('day', $dayName)
                ->where('role', 'koordinator')
                ->where('is_active', true)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->first();

            if ($coordSchedule?->teacher_id) {
                $journal->update(['coordinator_id' => $coordSchedule->teacher_id]);
            }
        }

        return $journal;
    }

    /**
     * Get Homeroom Attendance Recap for Mobile.
     */
    public function getHomeroomRecap(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        try {
            $controller = app(HomeroomAttendanceRecapController::class);
            $data = $controller->resolveRecapData($request);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat rekap: '.$e->getMessage()], 500);
        }

        $myClassrooms = collect($data['myClassrooms'] ?? [])->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
        ])->values();

        $selectedClassroom = ! empty($data['selectedClassroom']) ? [
            'id' => $data['selectedClassroom']->id,
            'name' => $data['selectedClassroom']->name,
            'homeroom_teacher' => $data['selectedClassroom']->homeroomTeacher?->name,
        ] : null;

        $mode = $data['mode'] ?? 'daily';
        $exportQueryParams = http_build_query($request->query());

        $formatted = [
            'my_classrooms' => $myClassrooms,
            'selected_classroom' => $selectedClassroom,
            'mode' => $mode,
            'date' => $data['date'] ?? now()->toDateString(),
            'current_date' => $data['date'] ?? now()->toDateString(),
            'day_name' => $data['dayName'] ?? '',
            'prev_date' => $data['prevDate'] ?? null,
            'next_date' => $data['nextDate'] ?? null,
            'active_semester' => $data['activeSemester'] ?? null,
            'academic_year' => $data['academicYear'] ?? null,
            'start_date' => $data['startDateStr'] ?? null,
            'end_date' => $data['endDateStr'] ?? null,
            'export_urls' => [
                'excel' => route('attendance.homeroom-recap.export-excel').($exportQueryParams ? '?'.$exportQueryParams : ''),
                'pdf' => route('attendance.homeroom-recap.download-pdf').($exportQueryParams ? '?'.$exportQueryParams : ''),
                'print' => route('attendance.homeroom-recap.download-pdf').'?'.($exportQueryParams ? $exportQueryParams.'&action=print' : 'action=print'),
            ],
            'summary_stats' => $data['summaryStats'] ?? [],
        ];

        if ($mode === 'daily') {
            $periods = $data['periods'] ?? [];
            $sessionsByPeriod = [];
            foreach (($data['sessionsByPeriod'] ?? []) as $p => $item) {
                $sessionsByPeriod[$p] = is_array($item) ? [
                    'id' => $item['session']?->id ?? null,
                    'subject_name' => $item['subject_name'] ?? '-',
                    'teacher_name' => $item['teacher_name'] ?? '-',
                    'is_attended' => (bool) ($item['is_attended'] ?? false),
                ] : null;
            }

            $grid = collect($data['studentGrid'] ?? [])->map(function ($row) {
                $stu = $row['student'] ?? null;

                return [
                    'student_id' => $stu?->id,
                    'student_name' => $stu?->name,
                    'nis' => $stu?->nis ?? '-',
                    'gender' => $stu?->gender ?? 'L',
                    'periods' => $row['periods'] ?? [],
                    'final_status' => $row['finalStatus'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'parent_phone' => ($stu?->primary_parent_phone && $stu?->primary_parent_phone !== '-') ? $stu?->primary_parent_phone : ($stu?->phone_parent ?: ($stu?->phone_student ?: null)),
                ];
            })->values();

            $formatted['daily'] = [
                'periods' => $periods,
                'sessions_by_period' => $sessionsByPeriod,
                'student_grid' => $grid,
            ];
        } elseif ($mode === 'weekly') {
            $grid = collect($data['studentWeeklyGrid'] ?? [])->map(function ($row) {
                $stu = $row['student'] ?? null;

                return [
                    'student_id' => $stu?->id,
                    'student_name' => $stu?->name,
                    'nis' => $stu?->nis ?? '-',
                    'gender' => $stu?->gender ?? 'L',
                    'parent_phone' => ($stu?->primary_parent_phone && $stu?->primary_parent_phone !== '-') ? $stu?->primary_parent_phone : ($stu?->phone_parent ?: ($stu?->phone_student ?: null)),
                    'days' => $row['days'] ?? [],
                    'total_h' => $row['total_h'] ?? 0,
                    'total_s' => $row['total_s'] ?? 0,
                    'total_i' => $row['total_i'] ?? 0,
                    'total_d' => $row['total_d'] ?? 0,
                    'total_t' => $row['total_t'] ?? 0,
                    'total_p' => $row['total_p'] ?? 0,
                    'total_a' => $row['total_a'] ?? 0,
                    'total_truancy' => $row['total_truancy'] ?? 0,
                ];
            })->values();

            $formatted['weekly'] = [
                'days' => $data['days'] ?? [],
                'prev_week' => $data['prevWeek'] ?? null,
                'next_week' => $data['nextWeek'] ?? null,
                'student_grid' => $grid,
            ];
        } else {
            // Periodic: monthly, semester, yearly, custom
            $gridData = $data['studentRangeGrid'] ?? ($data['studentMonthlyGrid'] ?? []);
            $recap = collect($gridData)->map(function ($row) {
                $stu = $row['student'] ?? null;
                $daysAlfa = (int) ($row['days_alfa'] ?? 0);
                $daysTruant = (int) ($row['days_truant'] ?? 0);
                $attRate = (float) ($row['attendance_rate'] ?? 0);
                $isHighRisk = ($daysAlfa >= 3) || ($daysTruant >= 2) || ($attRate < 80);

                return [
                    'student_id' => $stu?->id,
                    'student_name' => $stu?->name,
                    'nis' => $stu?->nis ?? '-',
                    'gender' => $stu?->gender ?? 'L',
                    'parent_phone' => ($stu?->primary_parent_phone && $stu?->primary_parent_phone !== '-') ? $stu?->primary_parent_phone : ($stu?->phone_parent ?: ($stu?->phone_student ?: null)),
                    'days_present' => $row['days_present'] ?? 0,
                    'days_sick' => $row['days_sick'] ?? 0,
                    'days_permit' => $row['days_permit'] ?? 0,
                    'days_dispen' => $row['days_dispen'] ?? 0,
                    'days_late' => $row['days_late'] ?? 0,
                    'days_early_leave' => $row['days_early_leave'] ?? 0,
                    'days_alfa' => $daysAlfa,
                    'days_truant' => $daysTruant,
                    'attendance_rate' => $attRate,
                    'dapodik_present' => $row['dapodik_present'] ?? 0,
                    'dapodik_sick' => $row['dapodik_sick'] ?? 0,
                    'dapodik_permit' => $row['dapodik_permit'] ?? 0,
                    'dapodik_alfa' => $row['dapodik_alfa'] ?? 0,
                    'is_high_risk' => $isHighRisk,
                ];
            })->values();

            $formatted['periodic'] = [
                'recap' => $recap,
                'high_risk_count' => collect($recap)->where('is_high_risk', true)->count(),
                'period_title' => $data['periodTitle'] ?? '',
                'total_active_days' => $data['totalActiveDays'] ?? 0,
                'prev_month' => $data['prevMonth'] ?? null,
                'next_month' => $data['nextMonth'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * Get Live KBM schedules and attendance status per classroom for Piket Officers.
     */
    public function getPiketLiveKbm(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        if (! $user->canAccessMejaPiket($academicYear)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Meja piket hanya diperuntukkan guru piket, kepala sekolah, semua waka, dan tim kedisiplinan.',
            ], 403);
        }

        $now = Carbon::now();
        $today = Carbon::today();
        $dateStr = $today->toDateString();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[$today->dayOfWeekIso] ?? 'Senin';
        $currentTime = $now->format('H:i');

        $periodTimes = LessonSchedule::periodTimes($todayDay);
        $maxPeriodDay = count($periodTimes);
        $kbmEndTime = ($todayDay === 'Jumat') ? '12:30' : '15:00';

        $detectedPeriod = null;
        $detectedPeriodLabel = null;
        $isBreak = false;
        $isOutsideKbm = false;
        $isKbmFinished = false;
        $breakMessage = null;
        $isWeekend = in_array($todayDay, ['Sabtu', 'Minggu']);

        foreach ($periodTimes as $p => $times) {
            if ($currentTime >= $times['start'] && $currentTime < $times['end']) {
                $detectedPeriod = $p;
                $detectedPeriodLabel = "Jam ke-{$p} ({$times['start']} - {$times['end']} WIB)";
                break;
            }
        }

        if ($isWeekend) {
            $isOutsideKbm = true;
            $breakMessage = "Libur Akhir Pekan ({$todayDay}) — Tidak Ada KBM Reguler";
            $detectedPeriod = 1;
        } elseif (! $detectedPeriod) {
            if ($currentTime >= '09:15' && $currentTime < '09:50') {
                $isBreak = true;
                $breakMessage = 'Saat ini Jam Istirahat 1 (09:15 - 09:50 WIB)';
                $detectedPeriod = 4;
            } elseif ($todayDay !== 'Jumat' && $currentTime >= '12:30' && $currentTime < '13:00') {
                $isBreak = true;
                $breakMessage = 'Saat ini Jam Istirahat 2 & Sholat (12:30 - 13:00 WIB)';
                $detectedPeriod = 8;
            } elseif ($currentTime < '07:00') {
                $isOutsideKbm = true;
                $breakMessage = 'Di Luar Jam KBM Efektif (KBM Belum Dimulai)';
                $detectedPeriod = 1;
            } else {
                $isOutsideKbm = true;
                $isKbmFinished = true;
                $breakMessage = "KBM Hari {$todayDay} Telah Selesai (Pukul {$kbmEndTime} WIB)";
                $detectedPeriod = $maxPeriodDay;
            }
        }

        $selectedPeriod = $request->query('period') ? (int) $request->query('period') : ($detectedPeriod ?: 1);
        if ($selectedPeriod < 1 || $selectedPeriod > $maxPeriodDay) {
            $selectedPeriod = $detectedPeriod ?: 1;
        }

        $activePeriodTimes = $periodTimes[$selectedPeriod] ?? ['start' => '07:00', 'end' => '07:45'];

        // Classrooms
        $allClassrooms = Classroom::where('academic_year', $academicYear)
            ->where('is_active', true)
            ->with(['homeroomTeacher', 'gradeLevel'])
            ->orderBy('name')
            ->get();

        if ($allClassrooms->isEmpty()) {
            $allClassrooms = Classroom::with(['homeroomTeacher', 'gradeLevel'])->where('is_active', true)->orderBy('name')->get();
        }

        // Active schedules for this period and day
        $activeLessonSchedules = LessonSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('start_period', '<=', $selectedPeriod)
            ->where('end_period', '>=', $selectedPeriod)
            ->with(['teacher', 'subject', 'classroom'])
            ->get()
            ->keyBy('classroom_id');

        // Today inval classes for this period
        $todayInvals = PiketInvalClass::whereHas('journal', function ($q) use ($dateStr) {
            $q->whereDate('date', $dateStr);
        })->with(['classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->get()
            ->filter(fn ($inv) => $inv->period_start <= $selectedPeriod && $inv->period_end >= $selectedPeriod)
            ->keyBy('classroom_id');

        // Today events
        $todayEvents = CurriculumEvent::where(function ($q) use ($dateStr) {
            $q->whereDate('start_date', '<=', $dateStr)
                ->where(function ($sub) use ($dateStr) {
                    $sub->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $dateStr);
                });
        })->get();

        // Student permits & attendance sessions
        $todayStudentPermits = PiketStudentPermit::whereDate('permit_date', $dateStr)
            ->where('status', '!=', 'ditolak')
            ->get();

        $todaySessions = SubjectAttendanceSession::with(['records', 'teacher', 'subject'])
            ->whereDate('attendance_date', $dateStr)
            ->get();

        $classroomItems = [];
        $countTeaching = 0;
        $countInvalNeeded = 0;
        $countInvalHandled = 0;
        $countFree = 0;
        $countEvent = 0;
        $countAttended = 0;
        $countUnattended = 0;

        foreach ($allClassrooms as $classroom) {
            $sched = $activeLessonSchedules->get($classroom->id);
            $inval = $todayInvals->get($classroom->id);

            // Check event impact
            $classroomEvents = $todayEvents->filter(fn ($ev) => $ev->appliesToClassroom($classroom));
            $classEvent = $classroomEvents->first(fn ($ev) => in_array($ev->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus', 'pembelajaran_daring']) || $ev->is_holiday)
                ?? $classroomEvents->first(fn ($ev) => $ev->learning_impact && $ev->learning_impact !== 'normal')
                ?? $classroomEvents->sortByDesc(fn ($ev) => match ($ev->target_audience) {
                    'rombel' => 3,
                    'tingkat' => 2,
                    default => 1,
                })->first();

            $isEventImpacted = $classEvent && (
                in_array($classEvent->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus', 'pembelajaran_daring'])
                || $classEvent->is_holiday
            );

            $status = 'free';
            $statusLabel = 'Jam Kosong';
            if ($isEventImpacted) {
                $status = 'event';
                $statusLabel = $classEvent->student_impact_text ?: ($classEvent->title ?? 'Bebas KBM');
                $countEvent++;
            } elseif ($inval) {
                if ($inval->inval_teacher_id) {
                    $status = 'inval_handled';
                    $statusLabel = 'Inval Terisi';
                    $countInvalHandled++;
                } else {
                    $status = 'inval_needed';
                    $statusLabel = 'Butuh Inval';
                    $countInvalNeeded++;
                }
            } elseif ($sched) {
                $status = 'teaching';
                $statusLabel = 'KBM Normal';
                $countTeaching++;
            } else {
                $status = 'free';
                $statusLabel = 'Jam Kosong';
                $countFree++;
            }

            // Attendance check
            $classSessions = $todaySessions->where('classroom_id', $classroom->id);
            $classRecords = $classSessions->flatMap->records;

            $activeSession = null;
            if ($sched || $inval) {
                $activeSession = $classSessions->first(function ($sess) use ($sched, $selectedPeriod) {
                    if ($sched && $sess->lesson_schedule_id && $sess->lesson_schedule_id === $sched->id) {
                        return true;
                    }
                    if ($sched && $sess->start_period === $sched->start_period) {
                        return true;
                    }

                    return $sess->start_period <= $selectedPeriod && $sess->end_period >= $selectedPeriod;
                });
            }

            $isAttended = ($activeSession !== null);
            if ($status === 'teaching' || $status === 'inval_handled') {
                if ($isAttended) {
                    $countAttended++;
                } else {
                    $countUnattended++;
                }
            }

            // Stats
            $classPermits = $todayStudentPermits->where('classroom_id', $classroom->id);
            $countTelat = $classPermits->where('permit_type', 'terlambat')->count()
                + $classRecords->where('status', 't')->pluck('student_id')->unique()->count();
            $countPulang = $classPermits->where('permit_type', 'izin_pulang_cepat')->count();
            $countDispen = $classPermits->where('permit_category', 'dispen')->count()
                + $classRecords->where('status', 'd')->pluck('student_id')->unique()->count();
            $countSakit = $classPermits->where('permit_category', 'sakit')->count()
                + $classRecords->where('status', 's')->pluck('student_id')->unique()->count();
            $countIzin = $classPermits->where('permit_type', 'izin_tidak_masuk')->where('permit_category', '!=', 'sakit')->count()
                + $classRecords->where('status', 'i')->pluck('student_id')->unique()->count();

            $presentStudents = $classRecords->where('status', 'h')->pluck('student_id')->unique();
            $alfaStudents = $classRecords->where('status', 'a')->pluck('student_id')->unique();
            $countBolos = $alfaStudents->intersect($presentStudents)->count();
            $countAlfa = $alfaStudents->diff($presentStudents)->count();
            $countHadir = $presentStudents->count();

            $classroomItems[] = [
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'homeroom_teacher_name' => $classroom->homeroomTeacher?->name ?? 'Belum Ditentukan',
                'status' => $status,
                'status_label' => $statusLabel,
                'is_attended' => $isAttended,
                'schedule_id' => $sched?->id,
                'teacher_id' => $sched?->teacher_id ?? $inval?->absent_teacher_id,
                'subject_id' => $sched?->subject_id ?? $inval?->subject_id,
                'period' => $selectedPeriod,
                'subject_name' => $sched?->subject?->name ?? ($inval?->subject?->name ?? '-'),
                'teacher_name' => $sched?->teacher?->name ?? ($inval?->absentTeacher?->name ?? '-'),
                'inval_teacher_name' => $inval?->invalTeacher?->name,
                'attended_session' => $activeSession ? [
                    'id' => $activeSession->id,
                    'topic' => $activeSession->topic,
                    'notes' => $activeSession->notes,
                    'teacher_name' => $activeSession->teacher?->name,
                    'created_at_time' => $activeSession->created_at?->format('H:i'),
                ] : null,
                'attendance_stats' => [
                    'hadir' => $countHadir,
                    'sakit' => $countSakit,
                    'izin' => $countIzin,
                    'dispen' => $countDispen,
                    'alfa' => $countAlfa,
                    'bolos' => $countBolos,
                    'telat' => $countTelat,
                    'pulang' => $countPulang,
                ],
            ];
        }

        $formattedPeriodTimes = [];
        foreach ($periodTimes as $p => $times) {
            $formattedPeriodTimes[] = [
                'period' => $p,
                'start' => $times['start'],
                'end' => $times['end'],
                'label' => "Jam {$p}",
                'full_label' => "Jam ke-{$p} ({$times['start']} - {$times['end']})",
                'is_current' => ($p === $detectedPeriod),
            ];
        }

        $teachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'academic_year' => $academicYear,
                'today' => $dateStr,
                'today_day' => $todayDay,
                'current_time' => $currentTime,
                'detected_period' => $detectedPeriod,
                'detected_period_label' => $detectedPeriodLabel,
                'selected_period' => $selectedPeriod,
                'active_period_times' => $activePeriodTimes,
                'max_periods' => $maxPeriodDay,
                'period_times' => $formattedPeriodTimes,
                'is_break' => $isBreak,
                'is_outside_kbm' => $isOutsideKbm,
                'is_kbm_finished' => $isKbmFinished,
                'break_message' => $breakMessage,
                'teachers' => $teachers,
                'summary' => [
                    'total_classrooms' => count($allClassrooms),
                    'teaching_count' => $countTeaching,
                    'attended_count' => $countAttended,
                    'unattended_count' => $countUnattended,
                    'inval_needed_count' => $countInvalNeeded,
                    'inval_handled_count' => $countInvalHandled,
                    'event_count' => $countEvent,
                    'free_count' => $countFree,
                ],
                'classrooms' => $classroomItems,
            ],
        ]);
    }

    /**
     * Get Weekly Schedules for Piket Officers.
     */
    public function getPiketSchedules(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        if (! $user->canAccessMejaPiket($academicYear)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Meja piket hanya diperuntukkan guru piket, kepala sekolah, semua waka, dan tim kedisiplinan.',
            ], 403);
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();
        $setting = SchoolSetting::getSetting();
        $semester = $request->query('semester', $ayModel?->semester ?? $setting->semester ?? 'Ganjil');

        $dayOrder = "CASE day WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END";

        $schedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->with('teacher')
            ->orderByRaw($dayOrder)
            ->orderBy('role', 'desc')
            ->get();

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $grouped = [];
        foreach ($days as $day) {
            $grouped[$day] = $schedules->where('day', $day)->map(function ($s) {
                return [
                    'id' => $s->id,
                    'teacher_id' => $s->teacher_id,
                    'teacher_name' => $s->teacher?->name ?? 'Guru Piket',
                    'role' => $s->role,
                    'role_label' => ucfirst((string) $s->role),
                    'is_coordinator' => $s->role === 'koordinator',
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'academic_year' => $academicYear,
                'semester' => $semester,
                'days' => $days,
                'schedules' => $grouped,
            ],
        ]);
    }

    /**
     * Get Piket Journals list / archive.
     */
    public function getPiketJournals(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        if (! $user->canAccessMejaPiket($academicYear)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Meja piket hanya diperuntukkan guru piket, kepala sekolah, semua waka, dan tim kedisiplinan.',
            ], 403);
        }

        $month = $request->query('month');

        $query = PiketJournal::with(['coordinator', 'attendances.teacher'])
            ->withCount(['studentPermits', 'incidents', 'invalClasses', 'guests'])
            ->where('academic_year', $academicYear)
            ->latest('date');

        if ($month) {
            $query->whereMonth('date', Carbon::parse($month)->month)
                ->whereYear('date', Carbon::parse($month)->year);
        }

        $paginator = $query->paginate(15);

        $journals = collect($paginator->items())->map(function ($j) {
            return [
                'id' => $j->id,
                'date' => $j->date->format('Y-m-d'),
                'formatted_date' => $j->date->translatedFormat('l, d F Y'),
                'academic_year' => $j->academic_year,
                'semester' => $j->semester,
                'status' => $j->status,
                'coordinator_name' => $j->coordinator?->name ?? '-',
                'permits_count' => $j->student_permits_count,
                'incidents_count' => $j->incidents_count,
                'invals_count' => $j->inval_classes_count,
                'guests_count' => $j->guests_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'journals' => $journals,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * Get Piket Journal detail.
     */
    public function getPiketJournalDetail(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $journal = PiketJournal::with([
            'coordinator',
            'completer',
            'attendances.teacher',
            'invalClasses.classroom',
            'invalClasses.absentTeacher',
            'invalClasses.invalTeacher',
            'invalClasses.subject',
            'studentPermits.classroom',
            'studentPermits.givenBy',
            'incidents.classroom',
            'incidents.recordedBy',
            'guests.recordedBy',
        ])->find($id);

        if (! $journal) {
            return response()->json(['success' => false, 'message' => 'Jurnal piket tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'journal' => [
                    'id' => $journal->id,
                    'date' => $journal->date->format('Y-m-d'),
                    'formatted_date' => $journal->date->translatedFormat('l, d F Y'),
                    'academic_year' => $journal->academic_year,
                    'semester' => $journal->semester,
                    'status' => $journal->status,
                    'coordinator_name' => $journal->coordinator?->name ?? '-',
                    'completer_name' => $journal->completer?->name,
                    'notes' => $journal->notes,
                ],
                'officers' => $journal->attendances->map(fn ($a) => [
                    'teacher_name' => $a->teacher?->name ?? 'Petugas',
                    'status' => $a->status,
                    'check_in' => $a->check_in_time,
                    'check_out' => $a->check_out_time,
                ])->values(),
                'permits' => $journal->studentPermits->map(fn ($p) => [
                    'id' => $p->id,
                    'permit_number' => $p->permit_number,
                    'student_name' => $p->student_name,
                    'classroom_name' => $p->classroom?->name,
                    'permit_type' => $p->permit_type,
                    'permit_type_label' => $p->permit_type_label,
                    'reason' => $p->reason,
                    'return_time' => $p->return_time,
                    'given_by' => $p->givenBy?->name,
                    'slip_url' => route('piket.permits.slip', $p),
                ])->values(),
                'incidents' => $journal->incidents->map(fn ($inc) => [
                    'id' => $inc->id,
                    'student_name' => $inc->student_name,
                    'classroom_name' => $inc->classroom?->name,
                    'category' => $inc->category,
                    'description' => $inc->description,
                    'status' => $inc->status,
                    'recorded_by' => $inc->recordedBy?->name,
                ])->values(),
                'invals' => $journal->invalClasses->map(fn ($inv) => [
                    'id' => $inv->id,
                    'classroom_name' => $inv->classroom?->name,
                    'subject_name' => $inv->subject?->name,
                    'absent_teacher_name' => $inv->absentTeacher?->name,
                    'inval_teacher_name' => $inv->invalTeacher?->name,
                    'period' => "JP {$inv->period_start} - {$inv->period_end}",
                    'reason' => $inv->reason,
                    'assignment_material' => $inv->assignment_material,
                ])->values(),
                'guests' => $journal->guests->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->guest_name,
                    'institution' => $g->institution,
                    'purpose' => $g->purpose,
                    'meet_with' => $g->meet_with,
                    'badge_number' => $g->badge_number,
                    'check_in' => $g->check_in_time,
                    'check_out' => $g->check_out_time,
                ])->values(),
            ],
        ]);
    }

    /**
     * Confirm return time for student gate permit.
     */
    public function returnPiketStudentPermit(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $permit = PiketStudentPermit::findOrFail($id);
        $returnTime = now()->format('H:i:s');
        $permit->update([
            'return_time' => $returnTime,
        ]);

        ActivityLogger::log('update', "Mengonfirmasi kembalinya siswa {$permit->student_name} ke sekolah pada {$returnTime}", $permit);

        return response()->json([
            'success' => true,
            'message' => "Siswa {$permit->student_name} telah dikonfirmasi kembali ke sekolah ({$returnTime}).",
            'data' => [
                'id' => $permit->id,
                'return_time' => $returnTime,
            ],
        ]);
    }

    /**
     * Get Piket Reports & Statistics.
     */
    public function getPiketReports(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->query('classroom_id');

        $permitsQuery = PiketStudentPermit::with(['classroom', 'givenBy'])
            ->whereBetween('permit_date', [$startDate, $endDate]);
        if ($classroomId) {
            $permitsQuery->where('classroom_id', $classroomId);
        }
        $permits = $permitsQuery->latest('permit_date')->get();

        $invalQuery = PiketInvalClass::with(['journal', 'classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        if ($classroomId) {
            $invalQuery->where('classroom_id', $classroomId);
        }
        $invals = $invalQuery->latest()->get();

        $incidentQuery = PiketIncident::with(['classroom', 'recordedBy'])
            ->whereBetween('incident_date', [$startDate, $endDate]);
        if ($classroomId) {
            $incidentQuery->where('classroom_id', $classroomId);
        }
        $incidents = $incidentQuery->latest('incident_date')->get();

        $guests = PiketGuest::whereBetween('visit_date', [$startDate, $endDate])->get();

        $stats = [
            'total_late' => $permits->where('permit_type', 'terlambat')->count(),
            'total_leave' => $permits->whereIn('permit_type', ['izin_keluar_sementara', 'izin_pulang_cepat'])->count(),
            'total_inval' => $invals->count(),
            'total_incidents' => $incidents->count(),
            'total_guests' => $guests->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'stats' => $stats,
                'permits' => $permits->take(20)->map(fn ($p) => [
                    'id' => $p->id,
                    'permit_number' => $p->permit_number,
                    'student_name' => $p->student_name,
                    'classroom_name' => $p->classroom?->name,
                    'permit_type' => $p->permit_type_label,
                    'date' => $p->permit_date?->format('d M Y'),
                    'reason' => $p->reason,
                ]),
                'incidents' => $incidents->take(20)->map(fn ($inc) => [
                    'id' => $inc->id,
                    'student_name' => $inc->student_name,
                    'classroom_name' => $inc->classroom?->name,
                    'category' => $inc->category,
                    'date' => $inc->incident_date?->format('d M Y'),
                    'description' => $inc->description,
                ]),
            ],
        ]);
    }

    /**
     * Get Homeroom Student Permits for Mobile.
     */
    public function getHomeroomPermits(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $canViewAll = $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->isPrincipal();

        $ayModel = AcademicYear::where('name', $academicYear)->first();
        $classroomQuery = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        });

        if (! $canViewAll) {
            $classroomQuery->where('homeroom_teacher_id', $user->id);
        }

        $myClassrooms = $classroomQuery->orderBy('name')->get();
        if (! $canViewAll && $myClassrooms->isEmpty()) {
            $myClassrooms = Classroom::where('homeroom_teacher_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $selectedClassroomId = $request->query('classroom_id', $myClassrooms->first()?->id);
        $selectedClassroom = $myClassrooms->firstWhere('id', $selectedClassroomId) ?: $myClassrooms->first();

        $date = $request->query('date');
        $search = $request->query('search');

        $classroomIds = $myClassrooms->pluck('id');
        $query = PiketStudentPermit::with(['student', 'classroom', 'givenBy'])
            ->whereIn('classroom_id', $classroomIds)
            ->latest('permit_date');

        if ($selectedClassroom) {
            $query->where('classroom_id', $selectedClassroom->id);
        }

        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('permit_date', $date)
                    ->orWhere(function ($sub) use ($date) {
                        $sub->whereDate('permit_date', '<=', $date)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $date);
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $permits = $query->take(50)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'permit_number' => $p->permit_number,
                'student_id' => $p->student_id,
                'student_name' => $p->student_name,
                'classroom_id' => $p->classroom_id,
                'classroom_name' => $p->classroom?->name,
                'permit_type' => $p->permit_type,
                'permit_category' => $p->permit_category,
                'permit_category_label' => ucfirst((string) ($p->permit_category ?? 'Izin')),
                'reason' => $p->reason,
                'start_date' => $p->permit_date?->format('Y-m-d'),
                'end_date' => $p->end_date?->format('Y-m-d'),
                'formatted_dates' => $p->end_date && $p->end_date->gt($p->permit_date)
                    ? $p->permit_date?->format('d/m/Y').' - '.$p->end_date->format('d/m/Y')
                    : ($p->permit_date?->format('d/m/Y') ?? '-'),
                'attachment_url' => $p->attachment_url,
                'given_by_name' => $p->givenBy?->name ?? 'Wali Kelas',
                'status' => $p->status,
            ];
        });

        // Students in selected classroom for easy dropdown selection
        $students = collect();
        if ($selectedClassroom) {
            $enrolledStudentIds = StudentClassroom::where('classroom_id', $selectedClassroom->id)->pluck('student_id');
            $students = Student::where(function ($q) use ($selectedClassroom, $enrolledStudentIds) {
                $q->where('classroom_id', $selectedClassroom->id);
                if ($enrolledStudentIds->isNotEmpty()) {
                    $q->orWhereIn('id', $enrolledStudentIds);
                }
            })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'nis', 'nisn', 'gender']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'can_manage' => $myClassrooms->isNotEmpty() || $canViewAll,
                'my_classrooms' => $myClassrooms->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
                'selected_classroom' => $selectedClassroom ? ['id' => $selectedClassroom->id, 'name' => $selectedClassroom->name] : null,
                'students' => $students,
                'permits' => $permits,
                'total_permits' => $permits->count(),
            ],
        ]);
    }

    /**
     * Store student permit reported to homeroom teacher.
     */
    public function storeHomeroomPermit(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'student_id' => 'nullable|exists:students,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'permit_category' => 'required|in:sakit,izin,dispen',
            'permit_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:permit_date',
            'reason' => 'required|string|max:500',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
        ]);

        $studentIds = ! empty($validated['student_ids'])
            ? $validated['student_ids']
            : (! empty($validated['student_id']) ? [$validated['student_id']] : []);

        if (empty($studentIds)) {
            return response()->json(['success' => false, 'message' => 'Pilih setidaknya satu siswa.'], 422);
        }

        $classroom = Classroom::findOrFail($validated['classroom_id']);

        $canManage = $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->isPrincipal() || $user->isHomeroomTeacher($classroom);
        if (! $canManage) {
            return response()->json(['success' => false, 'message' => 'Anda bukan wali kelas untuk rombel ini.'], 403);
        }

        $todayStr = Carbon::parse($validated['permit_date'])->toDateString();
        $endDate = ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->toDateString() : null;

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('permits/evidence', 'local');
        }

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $journal = $this->getOrCreatePiketJournal($todayStr, $user, $operationalYear->name, $operationalYear->semester);

        $batchId = count($studentIds) > 1 ? sprintf('WALI-%s-%04d', Carbon::parse($todayStr)->format('Ymd'), random_int(1000, 9999)) : null;

        $createdPermits = collect();
        foreach ($studentIds as $sId) {
            $student = Student::findOrFail($sId);
            $isStudentInClass = ($student->classroom_id === $classroom->id)
                || StudentClassroom::where('classroom_id', $classroom->id)->where('student_id', $student->id)->exists();
            if (! $isStudentInClass) {
                continue;
            }

            $existingPermit = PiketStudentPermit::where('student_id', $student->id)
                ->where('permit_type', 'izin_tidak_masuk')
                ->whereDate('permit_date', $todayStr)
                ->first();

            if ($existingPermit) {
                continue;
            }

            $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
            $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

            $permit = PiketStudentPermit::create([
                'batch_id' => $batchId,
                'piket_journal_id' => $journal->id,
                'permit_number' => $permitNumber,
                'permit_date' => $validated['permit_date'],
                'end_date' => $endDate,
                'student_id' => $student->id,
                'student_name' => $student->name,
                'classroom_id' => $classroom->id,
                'permit_type' => 'izin_tidak_masuk',
                'permit_category' => $validated['permit_category'],
                'reason' => $validated['reason'],
                'action_taken' => 'Pemberitahuan dari Orang Tua/Wali Murid ke Wali Kelas',
                'reporter_type' => 'wali_kelas',
                'attachment_path' => $attachmentPath,
                'given_by' => $user->id,
                'status' => 'diberikan',
                'notified_homeroom' => true,
            ]);

            try {
                $this->telegramService->notifyStudentPermit($permit, $user, null);
            } catch (\Throwable) {
            }

            ActivityLogger::log('create', "Mencatat laporan izin siswa {$student->name} ({$validated['permit_category']})", $permit);
            $createdPermits->push($permit);
        }

        if ($createdPermits->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data izin baru yang tersimpan (kemungkinan siswa tidak terdaftar di kelas ini atau izin pada tanggal tersebut sudah pernah dicatat).',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($createdPermits) === 1
                ? "Laporan izin untuk {$createdPermits->first()->student_name} berhasil dicatat & disinkronkan ke piket!"
                : 'Laporan izin untuk '.count($createdPermits).' siswa berhasil dicatat & disinkronkan ke piket!',
            'data' => [
                'total_saved' => count($createdPermits),
                'batch_id' => $batchId,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Laporan izin untuk {$student->name} berhasil dicatat & disinkronkan ke Meja Piket.",
            'data' => $permit,
        ]);
    }

    /**
     * Delete student permit by homeroom teacher or admin.
     */
    public function destroyHomeroomPermit(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Belum terautentikasi.'], 401);
        }

        $permit = PiketStudentPermit::findOrFail($id);

        $canManage = $user->isSuperAdmin() || $user->isWakaKurikulum() || ($permit->classroom?->homeroom_teacher_id === $user->id) || ($permit->given_by === $user->id);
        if (! $canManage) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak menghapus data izin ini.'], 403);
        }

        if ($permit->attachment_path) {
            if (Storage::disk('local')->exists($permit->attachment_path)) {
                Storage::disk('local')->delete($permit->attachment_path);
            } elseif (Storage::disk('public')->exists($permit->attachment_path)) {
                Storage::disk('public')->delete($permit->attachment_path);
            }
        }

        $permit->delete();

        ActivityLogger::log('delete', "Menghapus data izin siswa {$permit->student_name}", $permit);

        return response()->json([
            'success' => true,
            'message' => 'Data izin siswa berhasil dihapus.',
        ]);
    }
}
