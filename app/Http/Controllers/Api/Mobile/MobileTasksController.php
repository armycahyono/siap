<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\GtkPermit;
use App\Models\PiketIncident;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\StudentVisitation;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Services\AcademicYearContext;
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
                'roles_operational' => [
                    'can_manage_discipline' => $user->canManageDiscipline(),
                    'is_waka_kesiswaan' => $user->isWakaKesiswaan(),
                    'can_operate_bk' => $user->canOperateBk(),
                ],
            ],
        ]);
    }
}
