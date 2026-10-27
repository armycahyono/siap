<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\DailyClassroomKbmSnapshot;
use App\Models\DailyKbmRoster;
use App\Models\DailyScheduleTarget;
use App\Models\LessonSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyKbmSnapshotService
{
    /**
     * Pastikan snapshot harian tersedia untuk tanggal yang diminta.
     * Jika belum ada atau $forceSync bernilai true, sistem akan meng-generate ulang.
     *
     * @return array{rosters: Collection, schedules: Collection, classrooms: Collection}
     */
    public function getOrGenerateSnapshot(Carbon $date, bool $forceSync = false, ?string $targetAcademicYear = null): array
    {
        $dateStr = $date->toDateString();

        $rostersExist = DailyKbmRoster::whereDate('date', $dateStr)->exists();
        $schedulesExist = DailyScheduleTarget::whereDate('date', $dateStr)->exists();
        $classroomsExist = DailyClassroomKbmSnapshot::whereDate('date', $dateStr)->exists();

        if (! $rostersExist || ! $schedulesExist || ! $classroomsExist || $forceSync) {
            $this->generateSnapshotForDate($date, $targetAcademicYear);
        }

        // Sinkronisasi status kehadiran sesi presensi & kehadiran siswa yang sudah tersimpan
        $this->syncCompletedSessions($date);
        $this->syncStudentAttendanceForDate($date);

        return [
            'rosters' => DailyKbmRoster::with(['user', 'exemptionEvent'])
                ->whereDate('date', $dateStr)
                ->get(),
            'schedules' => DailyScheduleTarget::with(['lessonSchedule', 'teacher', 'classroom.gradeLevel', 'subject', 'exemptionEvent', 'attendanceSession'])
                ->whereDate('date', $dateStr)
                ->orderBy('start_period')
                ->get(),
            'classrooms' => DailyClassroomKbmSnapshot::with(['classroom.gradeLevel', 'classroom.homeroomTeacher', 'exemptionEvent'])
                ->whereDate('date', $dateStr)
                ->get(),
        ];
    }

    /**
     * Tentukan tahun ajaran dan semester yang sah untuk tanggal tertentu.
     *
     * @return array{academic_year: string, semester: string}
     */
    public function resolveAcademicYearForDate(Carbon $date, ?string $explicitYear = null): array
    {
        $dateStr = $date->toDateString();
        $month = (int) $date->format('n');
        $dateSemester = ($month >= 7 && $month <= 12) ? 'Ganjil' : 'Genap';

        // 1. Tahun target eksplisit jika diberikan secara sah
        if (! empty($explicitYear)) {
            $validatedYear = app(AcademicYearContext::class)->resolveExplicitYear($explicitYear);
            $ayModel = AcademicYear::where('name', $validatedYear)->first();

            return [
                'academic_year' => $validatedYear,
                'semester' => $ayModel?->semester ?? $dateSemester,
            ];
        }

        // 2. Pertahankan konsistensi data historis: gunakan tahun ajaran dari snapshot yang sudah ada
        $existingYear = DailyScheduleTarget::whereDate('date', $dateStr)->value('academic_year')
            ?? DailyKbmRoster::whereDate('date', $dateStr)->value('academic_year')
            ?? DailyClassroomKbmSnapshot::whereDate('date', $dateStr)->value('academic_year');

        if ($existingYear && AcademicYear::where('name', $existingYear)->exists()) {
            $ayModel = AcademicYear::where('name', $existingYear)->first();

            return [
                'academic_year' => $existingYear,
                'semester' => $ayModel?->semester ?? $dateSemester,
            ];
        }

        // 3. Untuk tanggal hari ini (tanggal berjalan), gunakan strictly tahun operasional aktif
        if ($date->isToday()) {
            try {
                $opYear = app(AcademicYearContext::class)->getOperationalYear();

                return [
                    'academic_year' => $opYear->name,
                    'semester' => $opYear->semester ?? $dateSemester,
                ];
            } catch (\Throwable) {
                return [
                    'academic_year' => SchoolSetting::getMasterYear(),
                    'semester' => $dateSemester,
                ];
            }
        }

        // 4. Untuk tanggal non-hari ini (historis/terjadwal): tentukan berdasarkan kalender akademik (start_date/end_date)
        $matchingYear = AcademicYear::whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->first();

        if ($matchingYear) {
            return [
                'academic_year' => $matchingYear->name,
                'semester' => $matchingYear->semester ?? $dateSemester,
            ];
        }

        // 5. Jika tidak ada rentang tanggal terdaftar, hitung tahun ajaran standar kalender Indonesia (Juli-Juni)
        $calYear = (int) $date->format('Y');
        $computedYearName = $month >= 7 ? "{$calYear}/".($calYear + 1) : ($calYear - 1)."/{$calYear}";
        $computedModel = AcademicYear::where('name', $computedYearName)->first();

        if ($computedModel) {
            return [
                'academic_year' => $computedModel->name,
                'semester' => $computedModel->semester ?? $dateSemester,
            ];
        }

        // 6. Fallback aman terakhir: tahun operasional aktif atau master
        try {
            $opYear = app(AcademicYearContext::class)->getOperationalYear();

            return [
                'academic_year' => $opYear->name,
                'semester' => $opYear->semester ?? $dateSemester,
            ];
        } catch (\Throwable) {
            return [
                'academic_year' => SchoolSetting::getMasterYear(),
                'semester' => $dateSemester,
            ];
        }
    }

    /**
     * Generate snapshot harian untuk tanggal tertentu berdasarkan Master Jadwal dan Kalender Kurikulum.
     */
    public function generateSnapshotForDate(Carbon $date, ?string $targetAcademicYear = null): void
    {
        $dateStr = $date->toDateString();
        $dayName = $date->isoFormat('dddd');
        $resolved = $this->resolveAcademicYearForDate($date, $targetAcademicYear);
        $academicYear = $resolved['academic_year'];
        $semester = $resolved['semester'];
        $isWeekend = $date->isWeekend();

        // 1. Ambil agenda kegiatan yang aktif pada tanggal ini
        $todayEvents = CurriculumEvent::with('users')
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('start_date', '<=', $dateStr)
                    ->where(function ($sub) use ($dateStr) {
                        $sub->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $dateStr);
                    });
            })
            ->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)
                    ->orWhereNull('academic_year');
            })
            ->get();

        $isHolidayToday = $todayEvents->contains(fn ($ev) => $ev->is_holiday || $ev->teacher_impact === 'diliburkan_penuh');
        $isStaffHolidayToday = $todayEvents->contains(fn ($ev) => $ev->is_holiday || $ev->disengaged_staff_impact === 'libur' || $ev->teacher_impact === 'diliburkan_penuh');

        // 2. Evaluasi Agenda Khusus Akhir Pekan (Sabtu/Minggu)
        $mandatoryWeekendEvents = $todayEvents->filter(function ($ev) {
            if ($ev->is_holiday || $ev->teacher_impact === 'diliburkan_penuh') {
                return false;
            }

            $start = $ev->start_date ? Carbon::parse($ev->start_date) : null;
            $end = $ev->end_date ? Carbon::parse($ev->end_date) : $start;
            $durationDays = ($start && $end) ? $start->diffInDays($end) + 1 : 1;

            if ($durationDays > 3) {
                return false;
            }

            return $ev->target_audience === 'guru_staf' || $ev->users->isNotEmpty();
        });

        $weekendAssignedUserIds = collect();
        $mandatoryAllGtkWeekend = false;

        if ($isWeekend) {
            foreach ($mandatoryWeekendEvents as $ev) {
                if ($ev->users->isNotEmpty()) {
                    $weekendAssignedUserIds = $weekendAssignedUserIds->concat($ev->users->pluck('id'));
                } elseif ($ev->target_audience === 'guru_staf') {
                    $mandatoryAllGtkWeekend = true;
                }
            }
            $weekendAssignedUserIds = $weekendAssignedUserIds->unique();
        }

        // Personel yang ditugaskan khusus (bebas mengajar reguler)
        $onDutyEvents = $todayEvents->filter(fn ($ev) => $ev->teacher_impact === 'bebas_mengajar_tugas');
        $onDutyTeacherIds = $onDutyEvents->flatMap(fn ($ev) => $ev->users->pluck('id'))->unique();

        // 3. Ambil seluruh jadwal master pada hari ini
        $baseSchedules = $isWeekend
            ? collect()
            : LessonSchedule::with(['classroom.gradeLevel', 'subject', 'teacher'])
                ->where('day', $dayName)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->when($semester, fn ($q) => $q->where('semester', $semester))
                ->orderBy('start_period')
                ->get();

        // 4. Generate DailyScheduleTarget
        $activeScheduleTeacherCounts = [];

        foreach ($baseSchedules as $sched) {
            $isExempted = false;
            $exemptionReason = null;
            $exemptionEventId = null;

            if ($isHolidayToday) {
                $isExempted = true;
                $exemptionReason = 'Libur Sekolah / Diliburkan Penuh';
            } else {
                // Cek apakah rombel kelas terdampak event
                foreach ($todayEvents as $ev) {
                    if ($ev->is_holiday || $ev->teacher_impact === 'diliburkan_penuh') {
                        $isExempted = true;
                        $exemptionReason = $ev->title;
                        $exemptionEventId = $ev->id;
                        break;
                    }

                    if ($sched->classroom && $ev->appliesToClassroom($sched->classroom)) {
                        if (in_array($ev->learning_impact, ['libur_khusus', 'bebas_kbm_kegiatan'])) {
                            $isExempted = true;
                            $exemptionReason = $ev->title;
                            $exemptionEventId = $ev->id;
                            break;
                        }
                    }

                    if (empty($ev->target_audience) || $ev->target_audience === 'semua') {
                        if (in_array($ev->learning_impact, ['libur_khusus', 'bebas_kbm_kegiatan']) || $ev->disengaged_teacher_impact === 'libur') {
                            $isExempted = true;
                            $exemptionReason = $ev->title;
                            $exemptionEventId = $ev->id;
                            break;
                        }
                    }
                }

                // Cek penugasan guru bebas mengajar
                if (! $isExempted && $onDutyTeacherIds->contains($sched->teacher_id)) {
                    $dutyEv = $onDutyEvents->first(fn ($e) => $e->users->contains('id', $sched->teacher_id));
                    $isExempted = true;
                    $exemptionReason = $dutyEv ? $dutyEv->title : 'Tugas Khusus Sekolah';
                    $exemptionEventId = $dutyEv?->id;
                }
            }

            if (! $isExempted) {
                $tId = $sched->teacher_id;
                $activeScheduleTeacherCounts[$tId] = ($activeScheduleTeacherCounts[$tId] ?? 0) + 1;
            }

            DailyScheduleTarget::updateOrCreate(
                [
                    'date' => $dateStr,
                    'lesson_schedule_id' => $sched->id,
                ],
                [
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'teacher_id' => $sched->teacher_id,
                    'classroom_id' => $sched->classroom_id,
                    'subject_id' => $sched->subject_id,
                    'start_period' => $sched->start_period,
                    'end_period' => $sched->end_period,
                    'start_time' => $sched->start_time,
                    'end_time' => $sched->end_time,
                    'is_exempted' => $isExempted,
                    'exemption_reason' => $exemptionReason,
                    'exemption_event_id' => $exemptionEventId,
                ]
            );
        }

        // 5. Generate DailyKbmRoster untuk seluruh GTK Operasional
        $allGtkUsers = User::operationalPersonnel()->orderBy('name')->get();

        foreach ($allGtkUsers as $u) {
            $isExpected = false;
            $exemptionReason = null;
            $exemptionEventId = null;

            if ($isHolidayToday) {
                $isExpected = false;
                $exemptionReason = 'Libur Sekolah';
            } elseif ($isWeekend) {
                if ($weekendAssignedUserIds->contains($u->id)) {
                    $isExpected = true;
                } elseif ($mandatoryAllGtkWeekend) {
                    $isExpected = $u->isStaff() ? ! $isStaffHolidayToday : true;
                } else {
                    $isExpected = false;
                    $exemptionReason = 'Akhir Pekan (Libur)';
                }
            } else {
                // Hari kerja reguler (Senin - Jumat)
                if ($u->isStaff()) {
                    $isExpected = ! $isStaffHolidayToday;
                    if (! $isExpected) {
                        $exemptionReason = 'Staf Diliburkan';
                    }
                } else {
                    // Guru: wajib hadir jika punya jadwal mengajar aktif hari ini atau ditugaskan di event
                    $activeCount = $activeScheduleTeacherCounts[$u->id] ?? 0;
                    $isAssignedEvent = $todayEvents->contains(fn ($ev) => ! $ev->is_holiday && $ev->teacher_impact !== 'diliburkan_penuh' && $ev->users->contains('id', $u->id));

                    $isExpected = ($activeCount > 0) || $isAssignedEvent;
                    if (! $isExpected) {
                        $exemptionReason = 'Tidak Ada Jam Mengajar Aktif';
                    }
                }
            }

            DailyKbmRoster::updateOrCreate(
                [
                    'date' => $dateStr,
                    'user_id' => $u->id,
                ],
                [
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'user_type' => $u->isStaff() ? 'staff' : 'teacher',
                    'is_expected_checkin' => $isExpected,
                    'is_weekend' => $isWeekend,
                    'is_holiday' => $isHolidayToday,
                    'active_schedules_count' => $activeScheduleTeacherCounts[$u->id] ?? 0,
                    'exemption_reason' => $exemptionReason,
                    'exemption_event_id' => $exemptionEventId,
                ]
            );
        }

        // 6. Generate DailyClassroomKbmSnapshot untuk seluruh Rombel Kelas Aktif
        $allClassrooms = Classroom::with('gradeLevel')
            ->where('academic_year', $academicYear)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($allClassrooms as $classroom) {
            $isClassKbmActive = true;
            $classExemptionReason = null;
            $classExemptionEventId = null;

            if ($isHolidayToday) {
                $isClassKbmActive = false;
                $classExemptionReason = 'Libur Sekolah / Nasional';
            } elseif ($isWeekend) {
                $isClassKbmActive = false;
                $classExemptionReason = 'Akhir Pekan (Libur KBM)';
            } else {
                // Cek apakah rombel ini terdampak agenda kegiatan yang meliburkan / membebaskan KBM
                foreach ($todayEvents as $ev) {
                    if ($ev->is_holiday || $ev->teacher_impact === 'diliburkan_penuh') {
                        $isClassKbmActive = false;
                        $classExemptionReason = $ev->title;
                        $classExemptionEventId = $ev->id;
                        break;
                    }

                    if ($ev->appliesToClassroom($classroom)) {
                        if (in_array($ev->learning_impact, ['libur_khusus', 'bebas_kbm_kegiatan'])) {
                            $isClassKbmActive = false;
                            $classExemptionReason = $ev->title;
                            $classExemptionEventId = $ev->id;
                            break;
                        }
                    }

                    if (empty($ev->target_audience) || $ev->target_audience === 'semua') {
                        if (in_array($ev->learning_impact, ['libur_khusus', 'bebas_kbm_kegiatan'])) {
                            $isClassKbmActive = false;
                            $classExemptionReason = $ev->title;
                            $classExemptionEventId = $ev->id;
                            break;
                        }
                    }
                }
            }

            // Hitung jadwal pelajaran untuk kelas ini pada hari ini
            $classroomSchedules = $baseSchedules->where('classroom_id', $classroom->id);
            $totalStudents = $classroom->students()->where('status', 'aktif')->count();

            DailyClassroomKbmSnapshot::updateOrCreate(
                [
                    'date' => $dateStr,
                    'classroom_id' => $classroom->id,
                ],
                [
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'is_kbm_active' => $isClassKbmActive,
                    'exemption_reason' => $classExemptionReason,
                    'exemption_event_id' => $classExemptionEventId,
                    'total_students' => $totalStudents,
                    'scheduled_sessions_count' => $classroomSchedules->count(),
                ]
            );
        }
    }

    /**
     * Hubungkan sesi presensi mapel yang sudah selesai dengan target jadwal harian.
     */
    public function syncCompletedSessions(Carbon $date): void
    {
        $dateStr = $date->toDateString();

        $completedSessions = SubjectAttendanceSession::whereDate('attendance_date', $dateStr)
            ->whereNotNull('lesson_schedule_id')
            ->get();

        foreach ($completedSessions as $session) {
            DailyScheduleTarget::where('date', $dateStr)
                ->where('lesson_schedule_id', $session->lesson_schedule_id)
                ->update([
                    'attendance_session_id' => $session->id,
                    'is_completed' => true,
                ]);
        }
    }

    /**
     * Hitung dan sinkronkan akumulasi kehadiran siswa per rombel pada tanggal tertentu.
     */
    public function syncStudentAttendanceForDate(Carbon $date): void
    {
        $dateStr = $date->toDateString();

        $snapshots = DailyClassroomKbmSnapshot::where('date', $dateStr)->get();
        if ($snapshots->isEmpty()) {
            return;
        }

        // Ambil semua sesi presensi pada tanggal ini
        $todaySessions = SubjectAttendanceSession::whereDate('attendance_date', $dateStr)->pluck('id');

        // Ambil rekap surat izin piket siswa pada tanggal ini
        $piketPermits = PiketStudentPermit::whereDate('permit_date', $dateStr)->get();

        // Ambil seluruh rekap kehadiran mapel siswa pada tanggal ini
        $records = SubjectAttendanceRecord::with('session')
            ->whereIn('subject_attendance_session_id', $todaySessions)
            ->get();

        foreach ($snapshots as $snapshot) {
            $classId = $snapshot->classroom_id;

            // Jumlah sesi yang sudah diabsen untuk kelas ini
            $completedSessionsCount = DailyScheduleTarget::where('date', $dateStr)
                ->where('classroom_id', $classId)
                ->where('is_completed', true)
                ->count();

            // Izin / Dispensasi dari Piket
            $classPermits = $piketPermits->where('classroom_id', $classId);
            $piketIzinStudentIds = $classPermits->where('permit_type', 'izin_tidak_masuk')->pluck('student_id')->unique();
            $piketLateStudentIds = $classPermits->where('permit_type', 'terlambat')->pluck('student_id')->unique();
            $piketEarlyLeaveStudentIds = $classPermits->where('permit_type', 'izin_pulang_cepat')->pluck('student_id')->unique();

            // Catatan presensi kelas dari guru mapel
            $classRecords = $records->filter(fn ($r) => $r->session?->classroom_id === $classId);
            $recordIzinStudentIds = $classRecords->whereIn('status', ['i', 's', 'd'])->pluck('student_id')->unique();
            $recordAlfaStudentIds = $classRecords->where('status', 'a')->pluck('student_id')->unique();
            $recordPresentStudentIds = $classRecords->where('status', 'h')->pluck('student_id')->unique();

            // Deteksi siswa yang hadir di satu sesi mapel tapi alfa di sesi mapel lain (Bolos KBM)
            // Siswa yang pernah hadir di sesi lain hari ini tidak dihitung sebagai Alfa Seharian Penuh
            $truantStudentIds = $recordAlfaStudentIds->intersect($recordPresentStudentIds);
            $pureAlfaStudentIds = $recordAlfaStudentIds->diff($truantStudentIds);

            $allIzinStudentIds = $piketIzinStudentIds->concat($recordIzinStudentIds)->unique();
            $permitCount = $allIzinStudentIds->count();
            $lateCount = $piketLateStudentIds->count();
            $earlyLeaveCount = $piketEarlyLeaveStudentIds->count();
            $alfaCount = $pureAlfaStudentIds->count(); // Alfa murni (tidak hadir sama sekali)

            $totalStudents = $snapshot->total_students;
            // Jika kelas sedang PKL/Bebas KBM, hadir dihitung penuh/normal
            if (! $snapshot->is_kbm_active) {
                $presentCount = $totalStudents;
                $attendancePercentage = 100.00;
            } else {
                $unattendedStudentCount = $permitCount + $alfaCount;
                $presentCount = max(0, $totalStudents - $unattendedStudentCount);
                $attendancePercentage = $totalStudents > 0
                    ? round(($presentCount / $totalStudents) * 100, 2)
                    : 100.00;
            }

            $snapshot->update([
                'present_students_count' => $presentCount,
                'permit_students_count' => $permitCount,
                'late_students_count' => $lateCount,
                'early_leave_students_count' => $earlyLeaveCount,
                'alfa_students_count' => $alfaCount,
                'attendance_percentage' => $attendancePercentage,
                'completed_sessions_count' => $completedSessionsCount,
            ]);
        }
    }

    /**
     * Hapus dan invalidate cache snapshot untuk rentang tanggal tertentu (misal saat agenda kalender diubah).
     */
    public function invalidateSnapshotRange(Carbon|string $startDate, Carbon|string|null $endDate = null): void
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : $start;

        DailyClassroomKbmSnapshot::whereBetween('date', [$start, $end])->delete();
        DailyScheduleTarget::whereBetween('date', [$start, $end])->delete();
        DailyKbmRoster::whereBetween('date', [$start, $end])->delete();
    }
}
