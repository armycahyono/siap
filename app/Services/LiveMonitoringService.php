<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketStudentPermit;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use Carbon\Carbon;

class LiveMonitoringService
{
    public function __construct(
        protected DailyKbmSnapshotService $snapshotService
    ) {}

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
        $isPrincipal = $user->isPrincipal() || $user->isSuperAdmin();
        $isKurikulum = $user->isWakaKurikulum();
        $isKesiswaan = $user->isWakaKesiswaan() || $user->canManageDiscipline($operationalAcademicYear);
        $isHomeroom = (bool) $myHomeroomClassroom;
        $isBk = $user->canOperateBk($operationalAcademicYear);

        if (! $isPrincipal && ! $isKurikulum && ! $isKesiswaan && ! $isHomeroom && ! $isBk) {
            return null;
        }

        $roleKey = match (true) {
            $isPrincipal => 'principal',
            $isKurikulum => 'kurikulum',
            $isKesiswaan => 'kesiswaan',
            $isBk => 'bk',
            default => 'homeroom',
        };

        $roleLabel = match ($roleKey) {
            'principal' => 'Kepala Sekolah',
            'kurikulum' => 'Waka Kurikulum',
            'kesiswaan' => 'Kedisiplinan & Kesiswaan',
            'bk' => 'Guru BK',
            default => 'Wali Kelas ('.($myHomeroomClassroom?->name ?? 'Perwalian').')',
        };

        $canViewStudentAll = $isPrincipal || $isKurikulum || $isKesiswaan || $isBk;
        $canViewGtk = $isPrincipal || $isKurikulum;
        $canViewBk = $isPrincipal || $isKurikulum || $isKesiswaan || $isBk;

        $targetDateStr = $date->toDateString();
        $isToday = $date->isToday();
        $currentTimeStr = now()->format('H:i');

        // 1. DATA SISWA (Presensi KBM, Perizinan Meja Piket & Gate Attendance)
        $permitsQuery = PiketStudentPermit::with(['student.classroom', 'classroom', 'givenBy'])
            ->whereDate('permit_date', $targetDateStr)
            ->orderBy('created_at', 'desc');

        $sessionsQuery = SubjectAttendanceSession::whereDate('attendance_date', $targetDateStr);

        $gateLogsQuery = AttendanceLog::with(['student.classroom'])
            ->where('user_type', 'student')
            ->whereDate('scanned_at', $targetDateStr)
            ->orderBy('scanned_at', 'asc');

        if (! $canViewStudentAll && $myHomeroomClassroom) {
            $permitsQuery->where(function ($pq) use ($myHomeroomClassroom) {
                $pq->where('classroom_id', $myHomeroomClassroom->id)
                    ->orWhereHas('student', fn ($sq) => $sq->where('classroom_id', $myHomeroomClassroom->id));
            });
            $sessionsQuery->where('classroom_id', $myHomeroomClassroom->id);
            $gateLogsQuery->whereHas('student', fn ($sq) => $sq->where('classroom_id', $myHomeroomClassroom->id));
        }

        $studentPermits = $permitsQuery->get();
        $todaySessionIds = $sessionsQuery->pluck('id');
        $studentGateLogs = $gateLogsQuery->get();
        $gateLogsByStudent = $studentGateLogs->groupBy('student_id');

        $studentRecords = SubjectAttendanceRecord::with(['student.classroom', 'session.classroom', 'session.subject'])
            ->whereIn('subject_attendance_session_id', $todaySessionIds)
            ->get();

        // 8 Status Siswa
        $latePermits = $studentPermits->where('permit_type', 'terlambat')->unique('student_id')->values();
        $earlyLeavePermits = $studentPermits->where('permit_type', 'izin_pulang_cepat')->unique('student_id')->values();
        $izinPermits = $studentPermits->where('permit_type', 'izin_tidak_masuk')->unique('student_id')->values();
        $dispenPermits = $studentPermits->filter(fn ($p) => strtolower($p->permit_category ?? '') === 'dispen' || str_contains(strtolower($p->reason ?? ''), 'dispen'))->unique('student_id')->values();

        $sakitPermits = $izinPermits->filter(fn ($p) => strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit'))->values();
        $murniIzinPermits = $izinPermits->reject(fn ($p) => strtolower($p->permit_category ?? '') === 'sakit' || str_contains(strtolower($p->reason ?? ''), 'sakit'))->values();

        // Rekap Siswa Terlambat dari Berbagai Sumber: Piket, KBM (status = 't'), dan Gate Scan (> 07:00 atau status = 'terlambat')
        $lateRecords = $studentRecords->where('status', 't')->values();
        $lateGateLogs = collect();
        foreach ($gateLogsByStudent as $sId => $logs) {
            $firstScan = $logs->first();
            if ($firstScan && ($firstScan->status === 'terlambat' || ($firstScan->scanned_at && $firstScan->scanned_at->format('H:i:s') > '07:00:00'))) {
                $lateGateLogs->put($sId, $firstScan);
            }
        }

        $allLateStudentIds = $latePermits->pluck('student_id')
            ->concat($lateRecords->pluck('student_id'))
            ->concat($lateGateLogs->keys())
            ->filter()
            ->unique()
            ->values();

        $sakitRecords = $studentRecords->where('status', 's')->values();
        $murniIzinRecords = $studentRecords->where('status', 'i')->values();
        $dispenRecords = $studentRecords->where('status', 'd')->values();
        $alfaRecords = $studentRecords->where('status', 'a')->values();

        $allSakitStudentIds = $sakitPermits->pluck('student_id')->concat($sakitRecords->pluck('student_id'))->filter()->unique()->values();
        $allMurniIzinStudentIds = $murniIzinPermits->pluck('student_id')->concat($murniIzinRecords->pluck('student_id'))->filter()->diff($allSakitStudentIds)->unique()->values();
        $allDispenStudentIds = $dispenPermits->pluck('student_id')->concat($dispenRecords->pluck('student_id'))->filter()->diff($allSakitStudentIds)->diff($allMurniIzinStudentIds)->unique()->values();

        // Indikasi Bolos KBM
        $fingerprintLogs = $studentGateLogs->pluck('student_id')->filter()->unique();
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
        $allAlfaStudentIds = $pureAlfaRecords->pluck('student_id')->unique()->values();

        // Hadir: Sesi KBM terakhir 'h' atau hadir di gerbang (tidak dalam kondisi izin/sakit/alfa)
        $latestStatusPerStudent = $studentRecords->sortByDesc('subject_attendance_session_id')->unique('student_id');
        $hadirRecords = $latestStatusPerStudent->where('status', 'h')->values();

        $gatePresentStudentIds = $studentGateLogs->pluck('student_id')->filter()->unique();
        $nonHadirStudentIds = $allSakitStudentIds->concat($allMurniIzinStudentIds)->concat($allDispenStudentIds)->concat($allAlfaStudentIds);
        $validGatePresentIds = $gatePresentStudentIds->diff($nonHadirStudentIds);

        $allHadirStudentIds = $hadirRecords->pluck('student_id')->concat($validGatePresentIds)->filter()->unique()->values();

        // 2. DATA GTK (Khusus Kurikulum & Kepala Sekolah)
        $gtkData = null;
        if ($canViewGtk) {
            $snapshot = $this->snapshotService->getOrGenerateSnapshot($date);
            $dailyRosters = $snapshot['rosters'] ?? collect();
            $dailySchedules = $snapshot['schedules'] ?? collect();

            $periodTimes = LessonSchedule::periodTimes($date->dayOfWeekIso === 5 ? 'jumat' : 'senin');

            // Agenda / Event Kurikulum yang aktif pada tanggal ini
            $todayEvents = CurriculumEvent::with('users')
                ->where(function ($q) use ($targetDateStr) {
                    $q->whereDate('start_date', '<=', $targetDateStr)
                        ->where(function ($sub) use ($targetDateStr) {
                            $sub->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $targetDateStr);
                        });
                })->get();

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

                // Cari agenda / event kurikulum yang menugaskan personil ini hari ini
                $assignedEvents = $todayEvents->filter(fn ($ev) => ! $ev->is_holiday && $ev->teacher_impact !== 'diliburkan_penuh' && $ev->users->contains('id', $u->id));

                // Tentukan jam kewajiban masuk (Datang)
                $expectedStartTimeStr = null;
                $dutyLabel = 'Tugas';

                if ($isPiket) {
                    $expectedStartTimeStr = '07:00';
                    $dutyLabel = 'Guru Piket Sekolah (07:00)';
                } elseif ($isStaffTu) {
                    $expectedStartTimeStr = '07:00';
                    $dutyLabel = ($u->position ?: 'Staf TU').' (07:00)';
                } elseif ($assignedEvents->isNotEmpty()) {
                    $ev = $assignedEvents->first();
                    $expectedStartTimeStr = $ev->gtk_checkin_time ?: '07:00';
                    $dutyLabel = $ev->title." ({$expectedStartTimeStr})";
                } elseif ($hasSchedule) {
                    $earliestSched = $teacherSchedules->first();
                    $periodNum = (int) $earliestSched->start_period;
                    $expectedStartTimeStr = $earliestSched->start_time ?: ($periodTimes[$periodNum]['start'] ?? '07:00');
                    $dutyLabel = "Jam ke-{$periodNum} ({$expectedStartTimeStr} WIB)";
                } else {
                    $expectedStartTimeStr = '07:00';
                    $dutyLabel = 'Tugas Sekolah (07:00)';
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
                'telat' => $gtkLateList->count(),
                'belum_masuk' => $gtkAwaitingList->count(),
                'izin' => $gtkPermits->whereIn('user_id', $obligatedGtkList->pluck('id'))->count(),
                'inval' => $todayInvals->count(),
                'unattended_kbm' => $unattendedSchedules->count(),
                'unattended' => $unattendedSchedules->count(),
                'detail' => [
                    'hadir' => $gtkPresentList->values(),
                    'terlambat' => $gtkLateList->values(),
                    'telat' => $gtkLateList->values(),
                    'belum_masuk' => $gtkAwaitingList->values(),
                    'izin' => $gtkPermits->whereIn('user_id', $obligatedGtkList->pluck('id'))->map(fn ($p) => [
                        'user_name' => $p->user?->name ?? 'GTK',
                        'duty_info' => ucfirst(str_replace('_', ' ', $p->permit_type)),
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
                    'unattended' => $unattendedSchedules->map(fn ($sch) => [
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

        // (b) Siswa Terlambat Masuk (Piket, KBM, & Scan Gerbang)
        foreach ($allLateStudentIds as $lateId) {
            $lp = $latePermits->firstWhere('student_id', $lateId);
            $lr = $lateRecords->firstWhere('student_id', $lateId);
            $lg = $lateGateLogs->get($lateId);

            $st = $lp?->student ?? $lr?->student ?? $lg?->student;
            $cls = $lp?->classroom?->name ?? $st?->classroom?->name ?? $lr?->session?->classroom?->name ?? '-';

            $time = match (true) {
                ! empty($lp?->entry_time) => substr($lp->entry_time, 0, 5),
                $lp?->created_at !== null => $lp->created_at->format('H:i'),
                $lg?->scanned_at !== null => $lg->scanned_at->format('H:i'),
                $lr?->created_at !== null => $lr->created_at->format('H:i'),
                default => '-',
            };

            $desc = match (true) {
                ! empty($lp?->reason) => $lp->reason,
                ! empty($lr?->notes) => $lr->notes,
                $lr !== null => 'Terlambat KBM ('.($lr->session?->subject?->name ?? 'Mapel').')',
                $lg !== null => 'Terlambat Masuk Gerbang',
                default => 'Terlambat Masuk',
            };

            $officer = match (true) {
                ! empty($lp?->givenBy?->name) => $lp->givenBy->name,
                ! empty($lp?->giver?->name) => $lp->giver->name,
                $lp !== null => 'Piket',
                $lr !== null => ($lr->session?->teacher?->name ?? 'Guru Mapel'),
                $lg !== null => 'Scan Gerbang',
                default => 'Sistem Presensi',
            };

            $timelineEvents->push([
                'type' => 'permit_late',
                'time' => $time,
                'title' => 'Siswa Terlambat Masuk',
                'badge' => $lp ? ('Jam ke-'.($lp->period_assigned ?? '1')) : 'Terlambat',
                'badge_color' => 'amber',
                'person_name' => $st?->name ?? 'Siswa',
                'classroom_name' => $cls,
                'description' => $desc,
                'action_taken' => $lp ? 'Diberikan surat izin piket' : 'Dicatat dalam presensi',
                'officer' => $officer,
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
                'hadir' => $allHadirStudentIds->count(),
                'telat' => $allLateStudentIds->count(),
                'sakit' => $allSakitStudentIds->count(),
                'izin' => $allMurniIzinStudentIds->count(),
                'dispen' => $allDispenStudentIds->count(),
                'pulang' => $earlyLeavePermits->count(),
                'alfa' => $allAlfaStudentIds->count(),
                'bolos' => $truancyStudents->count(),
                'detail' => [
                    'hadir' => $allHadirStudentIds->map(function ($id) use ($hadirRecords, $studentGateLogs) {
                        $r = $hadirRecords->firstWhere('student_id', $id);
                        $g = $studentGateLogs->firstWhere('student_id', $id);
                        $st = $r?->student ?? $g?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $r?->session?->classroom?->name ?? $st?->classroom?->name ?? '-',
                            'reason' => $r ? ('Hadir di '.($r->session?->subject?->name ?? 'KBM')) : 'Hadir Presensi Gerbang',
                            'time' => $r?->created_at?->format('H:i') ?? ($g?->scanned_at?->format('H:i') ?? '-'),
                        ];
                    })->values(),
                    'telat' => $allLateStudentIds->map(function ($id) use ($latePermits, $lateRecords, $lateGateLogs) {
                        $p = $latePermits->firstWhere('student_id', $id);
                        $r = $lateRecords->firstWhere('student_id', $id);
                        $g = $lateGateLogs->get($id);

                        $st = $p?->student ?? $r?->student ?? $g?->student;
                        $classroom = $p?->classroom?->name ?? $st?->classroom?->name ?? $r?->session?->classroom?->name ?? '-';

                        $reason = match (true) {
                            ! empty($p?->reason) => $p->reason,
                            ! empty($r?->notes) => $r->notes,
                            $r !== null => 'Terlambat KBM ('.($r->session?->subject?->name ?? 'Mapel').')',
                            $g !== null => 'Terlambat Masuk Gerbang',
                            default => 'Terlambat Masuk',
                        };

                        $time = match (true) {
                            ! empty($p?->entry_time) => substr($p->entry_time, 0, 5),
                            $p?->created_at !== null => $p->created_at->format('H:i'),
                            $g?->scanned_at !== null => $g->scanned_at->format('H:i'),
                            $r?->created_at !== null => $r->created_at->format('H:i'),
                            default => '-',
                        };

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $classroom,
                            'reason' => $reason,
                            'time' => $time,
                        ];
                    })->values(),
                    'sakit' => $allSakitStudentIds->map(function ($id) use ($studentRecords, $sakitPermits) {
                        $p = $sakitPermits->firstWhere('student_id', $id);
                        $r = $studentRecords->firstWhere('student_id', $id);
                        $st = $p?->student ?? $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $p?->classroom?->name ?? $st?->classroom?->name ?? $r?->session?->classroom?->name ?? '-',
                            'reason' => $p?->reason ?: ($r?->notes ?: 'Sakit'),
                            'time' => $p?->created_at?->format('H:i') ?? ($r?->created_at?->format('H:i') ?? '-'),
                        ];
                    })->values(),
                    'izin' => $allMurniIzinStudentIds->map(function ($id) use ($studentRecords, $murniIzinPermits) {
                        $p = $murniIzinPermits->firstWhere('student_id', $id);
                        $r = $studentRecords->firstWhere('student_id', $id);
                        $st = $p?->student ?? $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $p?->classroom?->name ?? $st?->classroom?->name ?? $r?->session?->classroom?->name ?? '-',
                            'reason' => $p?->reason ?: ($r?->notes ?: 'Izin'),
                            'time' => $p?->created_at?->format('H:i') ?? ($r?->created_at?->format('H:i') ?? '-'),
                        ];
                    })->values(),
                    'dispen' => $allDispenStudentIds->map(function ($id) use ($studentRecords, $dispenPermits) {
                        $p = $dispenPermits->firstWhere('student_id', $id);
                        $r = $studentRecords->firstWhere('student_id', $id);
                        $st = $p?->student ?? $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $p?->classroom?->name ?? $st?->classroom?->name ?? $r?->session?->classroom?->name ?? '-',
                            'reason' => $p?->reason ?: ($r?->notes ?: 'Dispensasi Kegiatan / Tugas'),
                            'time' => $p?->created_at?->format('H:i') ?? ($r?->created_at?->format('H:i') ?? '-'),
                        ];
                    })->values(),
                    'pulang' => $earlyLeavePermits->map(fn ($p) => [
                        'name' => $p->student?->name ?? 'Siswa',
                        'classroom' => $p->classroom?->name ?? $p->student?->classroom?->name ?? '-',
                        'reason' => $p->reason ?: 'Izin pulang awal',
                        'time' => $p->departure_time ? substr($p->departure_time, 0, 5) : ($p->created_at?->format('H:i') ?? '-'),
                    ])->values(),
                    'alfa' => $allAlfaStudentIds->map(function ($id) use ($pureAlfaRecords) {
                        $r = $pureAlfaRecords->firstWhere('student_id', $id);
                        $st = $r?->student;

                        return [
                            'name' => $st?->name ?? 'Siswa',
                            'classroom' => $r?->session?->classroom?->name ?? $st?->classroom?->name ?? '-',
                            'reason' => 'Tanpa Keterangan'.($r?->session?->subject?->name ? ' ('.$r->session->subject->name.')' : ''),
                            'time' => $r?->created_at?->format('H:i') ?? '-',
                        ];
                    })->values(),
                    'bolos' => $truancyStudents->map(fn ($t) => [
                        'name' => $t->student_name,
                        'classroom' => $t->classroom_name,
                        'reason' => "Alfa di {$t->alfa_subjects} (Hadir di {$t->present_subjects})",
                        'time' => '-',
                    ])->values(),
                ],
            ],
            'gtk' => $gtkData,
            'timeline' => $timelineEvents,
        ];
    }
}
