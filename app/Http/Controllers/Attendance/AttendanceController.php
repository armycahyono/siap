<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceCommand;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketAttendance;
use App\Models\PiketInvalClass;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\AttendanceDeviceService;
use App\Services\DailyKbmSnapshotService;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Display unified attendance dashboard
     */
    public function index(Request $request): View
    {
        $todayStr = Carbon::today()->toDateString();
        $latestLog = AttendanceLog::latest('scanned_at')->first();

        if ($request->has('date') && $request->query('date') !== '') {
            $date = $request->query('date');
        } else {
            $date = $todayStr;
        }

        $targetDateStr = ($date === 'all' || empty($date)) ? $todayStr : $date;
        $targetDate = Carbon::parse($targetDateStr);

        if ($targetDateStr === $todayStr) {
            try {
                $targetAcademicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable $e) {
                Log::warning('AttendanceController index operational year fallback: '.$e->getMessage());
                $targetAcademicYear = SchoolSetting::getMasterYear();
            }
        } else {
            try {
                $targetAcademicYear = app(DailyKbmSnapshotService::class)->resolveAcademicYearForDate($targetDate)['academic_year'];
            } catch (\Throwable $e) {
                Log::warning('AttendanceController index date resolved year fallback: '.$e->getMessage());
                $targetAcademicYear = SchoolSetting::getMasterYear();
            }
        }

        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        $targetDay = $dayNames[$targetDate->dayOfWeek] ?? 'Senin';
        $isWeekend = in_array($targetDate->dayOfWeek, [0, 6]);
        $holidayEvent = $this->getDayHolidayStatus($targetDate);
        $isHoliday = $isWeekend || ($holidayEvent !== null);
        $holidayReason = $holidayEvent ? $holidayEvent->title : ($isWeekend ? "Hari Libur ({$targetDay})" : null);

        // -------------------------------------------------------------
        // Batas Jam Pelajaran Sekolah Hari Ini (Standar Siswa, TU, Piket)
        // -------------------------------------------------------------
        $schoolScheduleToday = $isHoliday ? null : LessonSchedule::where('academic_year', $targetAcademicYear)
            ->where('day', $targetDay)
            ->selectRaw('MIN(start_time) as min_start, MAX(end_time) as max_end')
            ->first();

        $schoolStartTimeStr = $isHoliday ? null : ($schoolScheduleToday?->min_start ?: '07:00');
        $schoolEndTimeStr = $isHoliday ? null : ($schoolScheduleToday?->max_end ?: ($targetDay === 'Jumat' ? '12:30' : '15:00'));

        $schoolStart = $schoolStartTimeStr ? Carbon::parse($targetDateStr.' '.$schoolStartTimeStr.':00') : null;
        $schoolEnd = $schoolEndTimeStr ? Carbon::parse($targetDateStr.' '.$schoolEndTimeStr.':00') : null;

        // -------------------------------------------------------------
        // 1. TAB GURU & STAF: Guru Jadwal + Guru Piket + Staf TU + Hadir
        // -------------------------------------------------------------
        $schedulesToday = $isHoliday ? collect() : LessonSchedule::with(['subject', 'classroom'])
            ->where('academic_year', $targetAcademicYear)
            ->where('day', $targetDay)
            ->get()
            ->groupBy('teacher_id');

        $piketSchedulesToday = $isHoliday ? collect() : PiketSchedule::where('academic_year', $targetAcademicYear)
            ->where('day', $targetDay)
            ->where('is_active', true)
            ->get()
            ->keyBy('teacher_id');

        $teacherLogsToday = AttendanceLog::where('user_type', 'teacher')
            ->whereDate('scanned_at', $targetDateStr)
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('user_id');

        $gtkPermitsToday = GtkPermit::approved()
            ->whereDate('start_date', '<=', $targetDateStr)
            ->whereDate('end_date', '>=', $targetDateStr)
            ->get()
            ->keyBy('user_id');

        $scheduledTeacherIds = $isHoliday ? [] : $schedulesToday->keys()->toArray();
        $piketTeacherIds = $isHoliday ? [] : $piketSchedulesToday->keys()->toArray();
        $permitTeacherIds = $gtkPermitsToday->keys()->toArray();
        $staffTuIds = $isHoliday ? [] : User::operationalPersonnel()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('position', 'like', '%administrasi%')
                    ->orWhere('position', 'like', '%satpam%')
                    ->orWhere('position', 'like', '%penjaga%')
                    ->orWhere('position', 'like', '%toolman%')
                    ->orWhere('position', 'like', '%pesuruh%')
                    ->orWhere('position', 'like', '%kebun%');
            })->pluck('id')->toArray();
        $scannedTeacherIds = $teacherLogsToday->keys()->toArray();

        $activeTeacherIds = array_unique(array_merge(
            $scheduledTeacherIds,
            $piketTeacherIds,
            $permitTeacherIds,
            $staffTuIds,
            $scannedTeacherIds
        ));

        $teachers = User::operationalPersonnel()
            ->whereIn('id', $activeTeacherIds)
            ->where('is_active', true)
            ->get();

        $teacherAttendanceList = collect();
        $now = Carbon::now();

        foreach ($teachers as $teacher) {
            $schedules = $schedulesToday->get($teacher->id, collect());
            $logs = $teacherLogsToday->get($teacher->id, collect());
            $isPiket = $piketSchedulesToday->has($teacher->id);
            $piketInfo = $isPiket ? $piketSchedulesToday->get($teacher->id) : null;

            $firstScan = $logs->first();
            $lastScan = $logs->count() > 1 ? $logs->last() : null;

            $hasSchedule = ! $isHoliday && $schedules->isNotEmpty();
            $isStaffTu = ! $isHoliday && in_array($teacher->id, $staffTuIds);

            // Determine role type and duty label
            $roleLabel = 'Guru Mapel';
            if ($isHoliday) {
                $roleLabel = 'Guru & Staf (Hari Libur)';
            } elseif ($isPiket && $hasSchedule) {
                $roleLabel = 'Guru Mapel & Piket';
            } elseif ($isPiket) {
                $roleLabel = 'Guru Piket';
            } elseif ($isStaffTu) {
                $roleLabel = $teacher->position ?: 'Staf TU';
            } elseif (! $hasSchedule) {
                $roleLabel = 'Guru (Bebas Jam)';
            }

            // Determine expected hours (Datang & Pulang)
            $expectedStartTimeStr = null;
            $expectedEndTimeStr = null;
            $expectedStart = null;
            $expectedEnd = null;
            $lessonsSummary = [];

            if ($isHoliday) {
                $lessonsSummary[] = $holidayReason ?: 'Hari Libur (Tidak Ada Jam Wajib)';
            } elseif ($isPiket || $isStaffTu) {
                // Guru piket dan Staf TU mengikuti jam operasional sekolah penuh
                $expectedStartTimeStr = $schoolStartTimeStr;
                $expectedEndTimeStr = $schoolEndTimeStr;
                $expectedStart = $schoolStart;
                $expectedEnd = $schoolEnd;

                if ($hasSchedule) {
                    foreach ($schedules as $s) {
                        $crName = $s->classroom ? $s->classroom->name : '-';
                        $subName = $s->subject ? ($s->subject->code ?: $s->subject->name) : 'Mapel';
                        $lessonsSummary[] = "{$crName} ({$subName}, Jam {$s->start_period}-{$s->end_period})";
                    }
                }
                if ($isPiket && $piketInfo) {
                    $dutyLoc = $piketInfo->duty_location ? " ({$piketInfo->duty_location})" : '';
                    $lessonsSummary[] = 'Tugas Piket: '.ucfirst($piketInfo->role).$dutyLoc;
                }
            } elseif ($hasSchedule) {
                // Guru Mapel mengikuti jam mengajar kelasnya sendiri
                $expectedStartTimeStr = $schedules->min('start_time');
                $expectedEndTimeStr = $schedules->max('end_time');
                if ($expectedStartTimeStr) {
                    $expectedStart = Carbon::parse($targetDateStr.' '.$expectedStartTimeStr.':00');
                }
                if ($expectedEndTimeStr) {
                    $expectedEnd = Carbon::parse($targetDateStr.' '.$expectedEndTimeStr.':00');
                }
                foreach ($schedules as $s) {
                    $crName = $s->classroom ? $s->classroom->name : '-';
                    $subName = $s->subject ? ($s->subject->code ?: $s->subject->name) : 'Mapel';
                    $lessonsSummary[] = "{$crName} ({$subName}, Jam {$s->start_period}-{$s->end_period})";
                }
            }

            // Status Masuk / Check-in
            $status = 'waiting';
            $statusText = 'Menunggu Jam Masuk';
            $statusBadge = 'secondary';
            $delayMinutes = 0;

            if ($firstScan) {
                if ($expectedStart) {
                    if ($firstScan->scanned_at->lte($expectedStart)) {
                        $status = 'ontime';
                        $diff = $firstScan->scanned_at->diffInMinutes($expectedStart);
                        $statusText = $diff > 0 ? 'Tepat Waktu (-'.self::formatDuration($diff).')' : 'Tepat Waktu';
                        $statusBadge = 'success';
                    } else {
                        $status = 'late';
                        $delayMinutes = $firstScan->scanned_at->diffInMinutes($expectedStart);
                        $statusText = 'Terlambat '.self::formatDuration($delayMinutes);
                        $statusBadge = 'danger';
                    }
                } else {
                    $status = 'extra';
                    $statusText = $isHoliday ? 'Hadir (Hari Libur)' : 'Hadir (Bebas Jam)';
                    $statusBadge = 'azure';
                }
            } else {
                if ($gtkPermitsToday->has($teacher->id)) {
                    $permit = $gtkPermitsToday->get($teacher->id);
                    $status = 'permit';
                    $statusText = $permit->type_label;
                    $statusBadge = match ($permit->permission_type) {
                        'sakit' => 'warning',
                        'dinas_luar' => 'primary',
                        'cuti' => 'purple',
                        default => 'info',
                    };
                    $lessonsSummary[] = 'Izin: '.$permit->reason;
                } elseif ($expectedStart) {
                    if ($targetDateStr === $todayStr && $now->gt($expectedStart)) {
                        $status = 'absent';
                        $diff = $now->diffInMinutes($expectedStart);
                        $statusText = 'Belum Hadir (+'.self::formatDuration($diff).')';
                        $statusBadge = 'danger';
                    } elseif ($targetDateStr < $todayStr) {
                        $status = 'absent';
                        $statusText = 'Tidak Hadir';
                        $statusBadge = 'danger';
                    }
                }
            }

            // Status Pulang / Check-out
            $checkoutStatus = 'none';
            $checkoutText = '-';
            $checkoutBadge = 'secondary';
            $earlyMinutes = 0;

            if ($firstScan) {
                if ($lastScan) {
                    if ($expectedEnd) {
                        if ($lastScan->scanned_at->gte($expectedEnd)) {
                            $checkoutStatus = 'ontime';
                            $diff = $lastScan->scanned_at->diffInMinutes($expectedEnd);
                            $checkoutText = $diff > 0 ? 'Sesuai Jadwal (+'.self::formatDuration($diff).')' : 'Sesuai Jadwal';
                            $checkoutBadge = 'success';
                        } else {
                            $checkoutStatus = 'early';
                            $earlyMinutes = $lastScan->scanned_at->diffInMinutes($expectedEnd);
                            $checkoutText = 'Pulang Awal (-'.self::formatDuration($earlyMinutes).')';
                            $checkoutBadge = 'warning';
                        }
                    } else {
                        $checkoutStatus = 'completed';
                        $checkoutText = 'Selesai Kegiatan';
                        $checkoutBadge = 'azure';
                    }
                } else {
                    // Hanya scan 1x (firstScan)
                    if ($expectedEnd) {
                        if ($targetDateStr === $todayStr) {
                            if ($now->lte($expectedEnd)) {
                                $checkoutStatus = 'active';
                                $checkoutText = 'Sedang Bertugas';
                                $checkoutBadge = 'info';
                            } else {
                                $diffAfter = $now->diffInMinutes($expectedEnd);
                                if ($diffAfter > 30) {
                                    $checkoutStatus = 'missing';
                                    $checkoutText = 'Belum Scan Pulang';
                                    $checkoutBadge = 'orange';
                                } else {
                                    $checkoutStatus = 'ready';
                                    $checkoutText = 'Waktu Pulang Tiba';
                                    $checkoutBadge = 'info';
                                }
                            }
                        } else {
                            // Tanggal lampau dan hanya scan 1x
                            $checkoutStatus = 'missing';
                            $checkoutText = 'Tidak Scan Pulang';
                            $checkoutBadge = 'orange';
                        }
                    }
                }
            }

            $teacherAttendanceList->push((object) [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'position' => $teacher->position ?: 'Guru',
                'role_label' => $roleLabel,
                'nigs' => $teacher->nigs,
                'has_schedule' => $hasSchedule,
                'is_piket' => $isPiket,
                'is_staff_tu' => $isStaffTu,
                'earliest_start_time' => $expectedStartTimeStr,
                'expected_end_time' => $expectedEndTimeStr,
                'lessons_summary' => implode('; ', $lessonsSummary) ?: ($isStaffTu ? 'Tugas Administrasi' : 'Tanpa Jam Pelajaran'),
                'first_scan_at' => $firstScan?->scanned_at,
                'first_scan_channel' => $firstScan?->channel_badge,
                'last_scan_at' => $lastScan?->scanned_at,
                'last_scan_channel' => $lastScan?->channel_badge,
                'verify_type' => $firstScan?->verify_type,
                'status' => $status,
                'status_text' => $statusText,
                'status_badge' => $statusBadge,
                'delay_minutes' => $delayMinutes,
                'checkout_status' => $checkoutStatus,
                'checkout_text' => $checkoutText,
                'checkout_badge' => $checkoutBadge,
                'early_minutes' => $earlyMinutes,
            ]);
        }

        $teacherAttendanceList = $teacherAttendanceList->sortBy(function ($item) {
            $priority = ['late' => 1, 'absent' => 2, 'ontime' => 3, 'extra' => 4, 'waiting' => 5];

            return ($priority[$item->status] ?? 9).($item->earliest_start_time ?: '99:99').$item->name;
        })->values();

        // -------------------------------------------------------------
        // 2. TAB SISWA: Terlambat, Pulang Awal, dan Belum Hadir
        // -------------------------------------------------------------
        $studentLimitTime = Carbon::parse($targetDateStr.' 07:00:00');

        $studentLogsToday = AttendanceLog::where('user_type', 'student')
            ->whereDate('scanned_at', $targetDateStr)
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('student_id');

        $allStudents = Student::with('classroom')->where('is_active', true)->orderBy('name')->get();
        $lateStudents = collect();
        $earlyStudents = collect();
        $absentStudents = collect();
        $piketStudentPermitsToday = PiketStudentPermit::whereDate('permit_date', $targetDateStr)
            ->get()
            ->keyBy('student_id');

        // Rekap KBM mapel siswa hari ini untuk mendeteksi bolos jam pelajaran
        $todaySubjectSessions = SubjectAttendanceSession::whereDate('attendance_date', $targetDateStr)->pluck('id');
        $todaySubjectRecords = SubjectAttendanceRecord::whereIn('subject_attendance_session_id', $todaySubjectSessions)
            ->get()
            ->groupBy('student_id');

        $truancyStudents = collect();

        if (! $isHoliday) {
            foreach ($allStudents as $student) {
                $logs = $studentLogsToday->get($student->id);
                $permit = $piketStudentPermitsToday->get($student->id);
                $subRecords = $todaySubjectRecords->get($student->id) ?? collect();

                if ($logs && $logs->isNotEmpty()) {
                    $firstScan = $logs->first();
                    $lastScan = $logs->count() > 1 ? $logs->last() : null;
                    $totalPresentStudents++;

                    // Datang Terlambat (> 07:00 atau ada surat izin terlambat piket)
                    if ($firstScan->scanned_at->gt($studentLimitTime) || ($permit && $permit->permit_type === 'terlambat')) {
                        $delay = $firstScan->scanned_at->gt($studentLimitTime)
                            ? $firstScan->scanned_at->diffInMinutes($studentLimitTime)
                            : 0;
                        $lateStudents->push((object) [
                            'id' => $student->id,
                            'name' => $student->name,
                            'nisn' => $student->nisn ?: $student->nis,
                            'classroom' => $student->classroom?->name ?: '-',
                            'scanned_at' => $firstScan->scanned_at,
                            'delay_minutes' => $delay,
                            'verify_type' => $firstScan->verify_type,
                            'status_text' => $delay > 0 ? 'Terlambat '.self::formatDuration($delay) : 'Terlambat (Surat Piket)',
                        ]);
                    }

                    // Deteksi Bolos KBM: Siswa scan fingerprint masuk tapi alfa di mapel tanpa izin pulang
                    if ($subRecords->contains('status', 'a') && (! $permit || $permit->permit_type !== 'izin_pulang_cepat')) {
                        $alfaCount = $subRecords->where('status', 'a')->count();
                        $truancyStudents->push((object) [
                            'id' => $student->id,
                            'name' => $student->name,
                            'nisn' => $student->nisn ?: $student->nis,
                            'classroom' => $student->classroom?->name ?: '-',
                            'scanned_in_at' => $firstScan->scanned_at,
                            'alfa_jp' => $alfaCount,
                            'status_text' => "Bolos KBM ({$alfaCount} JP Alfa)",
                        ]);
                    }

                    // Pulang Lebih Awal (Scan Pulang sebelum jam bubar sekolah)
                    if ($schoolEnd && $lastScan && $lastScan->scanned_at->lt($schoolEnd)) {
                        $early = $lastScan->scanned_at->diffInMinutes($schoolEnd);
                        $earlyStudents->push((object) [
                            'id' => $student->id,
                            'name' => $student->name,
                            'nisn' => $student->nisn ?: $student->nis,
                            'classroom' => $student->classroom?->name ?: '-',
                            'scanned_in_at' => $firstScan->scanned_at,
                            'scanned_out_at' => $lastScan->scanned_at,
                            'early_minutes' => $early,
                            'verify_type' => $lastScan->verify_type,
                            'status_text' => 'Pulang '.self::formatDuration($early).' Lebih Awal',
                        ]);
                    }
                } else {
                    // Cek apakah ada surat izin piket / walikelas
                    if ($permit && in_array($permit->permit_type, ['izin_tidak_masuk', 'izin_pulang_cepat'])) {
                        $statusText = match ($permit->permit_category) {
                            'sakit' => 'Sakit (Izin Terverifikasi)',
                            'dispen' => 'Dispensasi Sekolah',
                            default => 'Izin Tidak Masuk',
                        };
                        $statusBadge = match ($permit->permit_category) {
                            'sakit' => 'warning',
                            'dispen' => 'purple',
                            default => 'info',
                        };
                    } else {
                        $statusText = $targetDateStr === $todayStr ? 'Belum Hadir / Potensi Alfa' : 'Alfa (Tanpa Keterangan)';
                        $statusBadge = 'danger';
                    }

                    $absentStudents->push((object) [
                        'id' => $student->id,
                        'name' => $student->name,
                        'nisn' => $student->nisn ?: $student->nis,
                        'classroom' => $student->classroom?->name ?: '-',
                        'status_text' => $statusText,
                        'status_badge' => $statusBadge,
                    ]);
                }
            }
        } else {
            // Weekend: Tidak ada kewajiban siswa hadir
            foreach ($allStudents as $student) {
                $logs = $studentLogsToday->get($student->id);
                if ($logs && $logs->isNotEmpty()) {
                    $totalPresentStudents++;
                }
            }
        }

        // -------------------------------------------------------------
        // 3. TAB LOG MENTAH: Seluruh scan real-time
        // -------------------------------------------------------------
        $type = $request->query('type');
        $search = $request->query('search');
        $statusFilter = $request->query('status');
        $channel = $request->query('channel');

        $query = AttendanceLog::with(['user', 'student', 'device'])
            ->latest('scanned_at');

        if (! empty($date) && $date !== 'all') {
            $query->whereDate('scanned_at', $date);
        }

        if (! empty($type)) {
            $query->where('user_type', $type);
        }

        if (! empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (! empty($channel)) {
            if ($channel === 'gps') {
                $query->where(function ($q) {
                    $q->where('verify_type', 'geotag')
                        ->orWhere('device_sn', 'MOBILE-PWA');
                });
            } elseif ($channel === 'telegram') {
                $query->where(function ($q) {
                    $q->where('verify_type', 'telegram')
                        ->orWhere('device_sn', 'TELEGRAM');
                });
            } elseif ($channel === 'machine') {
                $query->where(function ($q) {
                    $q->whereNotIn('verify_type', ['geotag', 'telegram'])
                        ->whereNotIn('device_sn', ['MOBILE-PWA', 'TELEGRAM']);
                });
            }
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        // Stats
        $statsQuery = AttendanceLog::query();
        if (! empty($date) && $date !== 'all') {
            $statsQuery->whereDate('scanned_at', $date);
        }

        $stats = [
            'total_scans' => (clone $statsQuery)->count(),
            'machine_scans' => (clone $statsQuery)->whereNotIn('verify_type', ['geotag', 'telegram'])->whereNotIn('device_sn', ['MOBILE-PWA', 'TELEGRAM'])->count(),
            'gps_scans' => (clone $statsQuery)->where(function ($q) {
                $q->where('verify_type', 'geotag')->orWhere('device_sn', 'MOBILE-PWA');
            })->count(),
            'telegram_scans' => (clone $statsQuery)->where(function ($q) {
                $q->where('verify_type', 'telegram')->orWhere('device_sn', 'TELEGRAM');
            })->count(),
            'total_teachers' => (clone $statsQuery)->where('user_type', 'teacher')->distinct('pin')->count('pin'),
            'total_students' => (clone $statsQuery)->where('user_type', 'student')->distinct('pin')->count('pin'),
            'total_unknown' => (clone $statsQuery)->where('user_type', 'unknown')->count(),
            'scheduled_teachers_count' => count($activeTeacherIds),
            'late_teachers_count' => $teacherAttendanceList->where('status', 'late')->count(),
            'early_teachers_count' => $teacherAttendanceList->where('checkout_status', 'early')->count(),
            'late_students_count' => $lateStudents->count(),
            'early_students_count' => $earlyStudents->count(),
            'absent_students_count' => $absentStudents->count(),
            'truancy_students_count' => $truancyStudents->count(),
        ];

        $devices = AttendanceDevice::where('is_active', true)->get();
        $classrooms = Classroom::withCount('students')->orderBy('name')->get();

        // -------------------------------------------------------------
        // 4. DAFTAR ID MASTER GURU (Untuk modal registrasi mesin)
        // -------------------------------------------------------------
        $allTeachersMaster = User::operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function ($t) {
                $hasLog = AttendanceLog::where('user_id', $t->id)->exists();
                $t->is_enrolled = $hasLog;

                return $t;
            });

        $latestLogId = AttendanceLog::max('id') ?? 0;

        return view('attendance.index', compact(
            'logs',
            'date',
            'type',
            'channel',
            'search',
            'statusFilter',
            'stats',
            'devices',
            'classrooms',
            'latestLog',
            'targetDay',
            'isHoliday',
            'holidayReason',
            'schoolStartTimeStr',
            'schoolEndTimeStr',
            'teacherAttendanceList',
            'lateStudents',
            'earlyStudents',
            'absentStudents',
            'truancyStudents',
            'allTeachersMaster',
            'latestLogId'
        ));
    }

    /**
     * Real-time polling API endpoint (called every 3-5 seconds by frontend)
     */
    public function getLatestLogs(Request $request): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);
        $date = $request->query('date', Carbon::today()->toDateString());

        $query = AttendanceLog::with(['user', 'student', 'device'])
            ->where('id', '>', $afterId);

        if (! empty($date) && $date !== 'all') {
            $query->whereDate('scanned_at', $date);
        }

        $newLogs = $query->orderBy('scanned_at', 'desc')->get()->map(function ($log) {
            $channelBadge = $log->channel_badge;

            return [
                'id' => $log->id,
                'pin' => $log->pin,
                'name' => $log->name ?: ($log->user?->name ?: ($log->student?->name ?: "PIN: {$log->pin}")),
                'user_type' => $log->user_type,
                'user_type_label' => match ($log->user_type) {
                    'teacher' => 'Guru & Staf',
                    'student' => 'Siswa',
                    default => 'Tidak Dikenal',
                },
                'status' => $log->status,
                'status_label' => $log->status === 'check_out' ? 'Pulang' : 'Masuk',
                'verify_type' => $log->verify_type,
                'verify_type_label' => $log->verify_type_label,
                'verify_icon' => $log->verify_icon,
                'channel_label' => $channelBadge['label'],
                'channel_class' => $channelBadge['class'],
                'channel_icon' => $channelBadge['icon'],
                'scanned_time' => $log->scanned_at ? $log->scanned_at->format('H:i:s') : '-',
                'scanned_date' => $log->scanned_at ? $log->scanned_at->translatedFormat('d F Y') : '-',
                'device_name' => match (true) {
                    $log->verify_type === 'geotag' || $log->device_sn === 'MOBILE-PWA' => 'GPS Mobile (PWA)',
                    $log->verify_type === 'telegram' || $log->device_sn === 'TELEGRAM' => 'Bot Telegram',
                    default => $log->device?->name ?: ($log->device_sn ?: 'Mesin Biometrik'),
                },
            ];
        });

        // Statistics
        $statsQuery = AttendanceLog::query();
        if (! empty($date) && $date !== 'all') {
            $statsQuery->whereDate('scanned_at', $date);
        }

        $stats = [
            'total_scans' => (clone $statsQuery)->count(),
            'machine_scans' => (clone $statsQuery)->whereNotIn('verify_type', ['geotag', 'telegram'])->whereNotIn('device_sn', ['MOBILE-PWA', 'TELEGRAM'])->count(),
            'gps_scans' => (clone $statsQuery)->where(function ($q) {
                $q->where('verify_type', 'geotag')->orWhere('device_sn', 'MOBILE-PWA');
            })->count(),
            'telegram_scans' => (clone $statsQuery)->where(function ($q) {
                $q->where('verify_type', 'telegram')->orWhere('device_sn', 'TELEGRAM');
            })->count(),
            'total_teachers' => (clone $statsQuery)->where('user_type', 'teacher')->distinct('pin')->count('pin'),
            'total_students' => (clone $statsQuery)->where('user_type', 'student')->distinct('pin')->count('pin'),
            'total_unknown' => (clone $statsQuery)->where('user_type', 'unknown')->count(),
        ];

        $device = AttendanceDevice::first();

        return response()->json([
            'new_logs' => $newLogs,
            'latest_id' => AttendanceLog::max('id') ?? $afterId,
            'stats' => $stats,
            'device_status' => $device?->status ?? 'offline',
            'device_last_activity' => $device?->last_activity_at ? $device->last_activity_at->diffForHumans() : '-',
        ]);
    }

    /**
     * Resolve date ranges, period type, and period labels for reports
     */
    protected function resolveReportDates(Request $request): array
    {
        $now = Carbon::now();
        $periodType = $request->query('period_type');

        // Auto-detect if not explicitly set
        if (empty($periodType)) {
            if ($request->filled('start_date') || $request->filled('end_date')) {
                $periodType = 'custom';
            } elseif ($request->filled('semester')) {
                $periodType = 'semester';
            } elseif ($request->filled('week_date')) {
                $periodType = 'weekly';
            } elseif ($request->filled('year') && ! $request->filled('month')) {
                $periodType = 'yearly';
            } else {
                $periodType = 'monthly';
            }
        }

        $year = (int) $request->query('year', $now->year);
        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        $month = (int) $request->query('month', $now->month);
        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        $semester = (string) $request->query('semester', '1');
        $weekDate = $request->query('week_date', $now->toDateString());

        switch ($periodType) {
            case 'weekly':
                $refDate = Carbon::parse($weekDate);
                $startDate = $refDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $endDate = $refDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                $periodLabel = Carbon::parse($startDate)->translatedFormat('d F Y').' s/d '.Carbon::parse($endDate)->translatedFormat('d F Y');
                break;

            case 'semester':
                if ($semester === '2') {
                    // Semester Genap: 1 Jan - 30 Jun
                    $startDate = Carbon::create($year, 1, 1)->toDateString();
                    $endDate = Carbon::create($year, 6, 30)->toDateString();
                    $periodLabel = 'Semester Genap (1 Januari '.$year.' s/d 30 Juni '.$year.')';
                } else {
                    // Semester Ganjil: 1 Jul - 31 Des
                    $startDate = Carbon::create($year, 7, 1)->toDateString();
                    $endDate = Carbon::create($year, 12, 31)->toDateString();
                    $periodLabel = 'Semester Ganjil (1 Juli '.$year.' s/d 31 Desember '.$year.')';
                }
                break;

            case 'yearly':
                $startDate = Carbon::create($year, 1, 1)->toDateString();
                $endDate = Carbon::create($year, 12, 31)->toDateString();
                $periodLabel = 'Tahun '.$year.' (1 Januari '.$year.' s/d 31 Desember '.$year.')';
                break;

            case 'custom':
                $startDate = $request->query('start_date');
                $endDate = $request->query('end_date');
                if (empty($startDate) && empty($endDate)) {
                    $startDate = $now->copy()->startOfMonth()->toDateString();
                    $endDate = $now->toDateString();
                } elseif (empty($startDate)) {
                    $startDate = $endDate;
                } elseif (empty($endDate)) {
                    $endDate = $startDate;
                }
                $periodLabel = $startDate === $endDate
                    ? Carbon::parse($startDate)->translatedFormat('d F Y')
                    : Carbon::parse($startDate)->translatedFormat('d F Y').' s/d '.Carbon::parse($endDate)->translatedFormat('d F Y');
                break;

            case 'monthly':
            default:
                $periodType = 'monthly';
                $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                $periodLabel = 'Bulan '.Carbon::create($year, $month, 1)->translatedFormat('F Y');
                break;
        }

        return [
            'periodType' => $periodType,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $periodLabel,
            'month' => $month,
            'year' => $year,
            'semester' => $semester,
            'weekDate' => $weekDate,
        ];
    }

    /**
     * Get holiday event from academic calendar for a given date
     */
    protected function getDayHolidayStatus(Carbon $date): ?CurriculumEvent
    {
        $dateStr = $date->toDateString();

        return CurriculumEvent::where(function ($query) {
            $query->where('is_holiday', true)
                ->orWhereIn('category', ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr']);
        })
            ->whereDate('start_date', '<=', $dateStr)
            ->where(function ($query) use ($dateStr) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $dateStr);
            })
            ->first();
    }

    /**
     * Build unified daily recap collection (1 row per person per date with Jam Masuk & Jam Pulang)
     */
    protected function buildAttendanceRecap(
        string $startDate,
        string $endDate,
        string $userType = 'all',
        ?int $userId = null,
        ?int $studentId = null,
        ?string $search = null
    ): array {
        $query = AttendanceLog::with(['user', 'student.classroom', 'device'])
            ->whereBetween('scanned_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);

        if ($userType !== 'all') {
            $query->where('user_type', $userType);
        }

        if (! empty($userId)) {
            $query->where('user_type', 'teacher')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhere('pin', (string) $userId);
                });
        } elseif (! empty($studentId)) {
            $query->where('user_type', 'student')
                ->where(function ($q) use ($studentId) {
                    $q->where('student_id', $studentId)
                        ->orWhere('pin', (string) $studentId);
                });
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $rawLogs = $query->orderBy('scanned_at', 'asc')->get();

        // Group by Date and Person
        $grouped = $rawLogs->groupBy(function ($item) {
            $dateStr = $item->scanned_at->toDateString();
            $personKey = $item->user_type.'_'.($item->user_id ?? ($item->student_id ?? $item->pin));

            return $dateStr.'|'.$personKey;
        });

        $recapItems = collect();

        foreach ($grouped as $key => $personLogs) {
            $firstScan = $personLogs->first();
            $lastScan = $personLogs->count() > 1 ? $personLogs->last() : null;

            $scannedDate = $firstScan->scanned_at;
            $isWeekend = in_array($scannedDate->dayOfWeek, [0, 6]);
            $holidayEvent = $this->getDayHolidayStatus($scannedDate);
            $isHoliday = $isWeekend || ($holidayEvent !== null);

            $name = $firstScan->name ?: ($firstScan->user?->name ?: ($firstScan->student?->name ?: "PIN: {$firstScan->pin}"));
            $pin = $firstScan->pin;
            $type = $firstScan->user_type;

            $category = match ($type) {
                'teacher' => 'Guru & Staf',
                'student' => 'Siswa',
                default => 'Lainnya',
            };

            $jamMasuk = $firstScan->scanned_at->format('H:i:s');
            $jamPulang = $lastScan ? $lastScan->scanned_at->format('H:i:s') : '-';

            // Status Kehadiran
            $status = 'Tepat Waktu';
            if ($isHoliday) {
                $status = 'Hadir (Hari Libur)';
            } elseif ($type === 'student') {
                $studentLimit = Carbon::parse($scannedDate->toDateString().' 07:00:00');
                if ($firstScan->scanned_at->gt($studentLimit)) {
                    $diff = $firstScan->scanned_at->diffInMinutes($studentLimit);
                    $status = 'Terlambat '.self::formatDuration($diff);
                }
            } elseif ($type === 'teacher') {
                if ($firstScan->scanned_at->format('H:i') > '07:15') {
                    $status = 'Hadir';
                }
            }

            $recapItems->push((object) [
                'date' => $scannedDate,
                'date_str' => $scannedDate->toDateString(),
                'date_formatted' => $scannedDate->translatedFormat('d/m/Y'),
                'day_name' => $scannedDate->translatedFormat('l'),
                'pin' => $pin,
                'name' => $name,
                'user_type' => $type,
                'category' => $category,
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'first_scan_at' => $firstScan->scanned_at,
                'last_scan_at' => $lastScan?->scanned_at,
                'total_scans' => $personLogs->count(),
                'status' => $status,
                'verify_type' => ucfirst($firstScan->verify_type ?: 'Sidik Jari'),
                'user' => $firstScan->user,
                'student' => $firstScan->student,
            ]);
        }

        $summary = [
            'total_recap_rows' => $recapItems->count(),
            'unique_people' => $recapItems->pluck('pin')->unique()->count(),
            'total_scans' => $rawLogs->count(),
            'total_with_checkout' => $recapItems->where('jam_pulang', '!=', '-')->count(),
            'total_late' => $recapItems->filter(fn ($r) => str_contains($r->status, 'Terlambat'))->count(),
        ];

        return [
            'items' => $recapItems,
            'summary' => $summary,
        ];
    }

    /**
     * Build individual recap collection for Teachers and Staff
     */
    protected function buildTeacherStaffIndividualRecap(
        string $startDate,
        string $endDate,
        string $subType = 'all',
        ?int $userId = null,
        ?string $search = null
    ): array {
        $teachersQuery = User::operationalPersonnel()->where('is_active', true);

        if ($subType === 'guru') {
            $teachersQuery->where(function ($q) {
                $q->whereNull('position')
                    ->orWhere(function ($sq) {
                        $sq->where('position', 'not like', '%administrasi%')
                            ->where('position', 'not like', '%satpam%')
                            ->where('position', 'not like', '%penjaga%')
                            ->where('position', 'not like', '%toolman%')
                            ->where('position', 'not like', '%pesuruh%')
                            ->where('position', 'not like', '%kebun%')
                            ->where('position', 'not like', '%tata usaha%')
                            ->where('position', 'not like', '%staf%');
                    });
            });
        } elseif ($subType === 'staf') {
            $teachersQuery->where(function ($q) {
                $q->where('position', 'like', '%administrasi%')
                    ->orWhere('position', 'like', '%satpam%')
                    ->orWhere('position', 'like', '%penjaga%')
                    ->orWhere('position', 'like', '%toolman%')
                    ->orWhere('position', 'like', '%pesuruh%')
                    ->orWhere('position', 'like', '%kebun%')
                    ->orWhere('position', 'like', '%tata usaha%')
                    ->orWhere('position', 'like', '%staf%');
            });
        }

        if (! empty($userId)) {
            $teachersQuery->where('id', $userId);
        }

        if (! empty($search)) {
            $teachersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nigs', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $teachers = $teachersQuery->orderBy('name')->get();
        $teacherIds = $teachers->pluck('id')->toArray();

        $allLogs = AttendanceLog::where('user_type', 'teacher')
            ->where(function ($q) use ($teacherIds) {
                $q->whereIn('user_id', $teacherIds)
                    ->orWhereIn('pin', array_map('strval', $teacherIds));
            })
            ->whereBetween('scanned_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('scanned_at', 'asc')
            ->get();

        $logsByUser = $allLogs->groupBy(function ($item) {
            return $item->user_id ?? $item->pin;
        });

        // Preload any inval excuses and piket attendances in the date range
        $invalRecords = PiketInvalClass::with('journal')
            ->whereIn('absent_teacher_id', $teacherIds)
            ->whereHas('journal', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy('absent_teacher_id');

        $piketAttendanceRecords = PiketAttendance::with('journal')
            ->whereIn('teacher_id', $teacherIds)
            ->whereHas('journal', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy('teacher_id');

        $gtkPermitRecords = GtkPermit::approved()
            ->whereIn('user_id', $teacherIds)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sq) use ($startDate, $endDate) {
                        $sq->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get()
            ->groupBy('user_id');

        $teacherLessonDays = LessonSchedule::whereIn('teacher_id', $teacherIds)
            ->select('teacher_id', 'day')
            ->distinct()
            ->get()
            ->groupBy('teacher_id')
            ->map(fn ($items) => $items->pluck('day')->toArray());

        $teacherPiketDays = PiketSchedule::whereIn('teacher_id', $teacherIds)
            ->where('is_active', true)
            ->select('teacher_id', 'day')
            ->distinct()
            ->get()
            ->groupBy('teacher_id')
            ->map(fn ($items) => $items->pluck('day')->toArray());

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $now = Carbon::now();

        $effectiveDates = collect();
        for ($curr = $start->copy(); $curr->lte($end); $curr->addDay()) {
            if (in_array($curr->dayOfWeek, [0, 6])) {
                continue;
            }
            if ($this->getDayHolidayStatus($curr) !== null) {
                continue;
            }
            $effectiveDates->push($curr->copy());
        }

        $people = collect();

        foreach ($teachers as $t) {
            $userLogs = $logsByUser->get($t->id, collect())
                ->merge($logsByUser->get((string) $t->id, collect()))
                ->unique('id')
                ->sortBy('scanned_at');

            $dailyGroups = $userLogs->groupBy(fn ($item) => $item->scanned_at->toDateString());
            $records = collect();

            $hadirCount = 0;
            $sakitCount = 0;
            $izinCount = 0;
            $cutiCount = 0;
            $dinasLuarCount = 0;
            $alfaCount = 0;
            $terlambatCount = 0;

            $userInvals = $invalRecords->get($t->id, collect());
            $userPiketAtts = $piketAttendanceRecords->get($t->id, collect());
            $userGtkPermits = $gtkPermitRecords->get($t->id, collect());
            $tLessonDays = $teacherLessonDays->get($t->id, []);
            $tPiketDays = $teacherPiketDays->get($t->id, []);

            foreach ($dailyGroups as $dateStr => $dayItems) {
                $first = $dayItems->first();
                $last = $dayItems->count() > 1 ? $dayItems->last() : null;

                $scannedDate = $first->scanned_at;
                $isWeekend = in_array($scannedDate->dayOfWeek, [0, 6]);
                $holidayEvent = $this->getDayHolidayStatus($scannedDate);
                $isHoliday = $isWeekend || ($holidayEvent !== null);

                $status = 'Tepat Waktu';
                $keteranganParts = [];

                if ($isHoliday) {
                    $status = 'Hadir (Hari Libur)';
                    $keteranganParts[] = $holidayEvent ? $holidayEvent->title : 'Libur Akhir Pekan';
                } else {
                    $teacherLimit = Carbon::parse($scannedDate->toDateString().' 07:15:00');
                    if ($first->scanned_at->gt($teacherLimit)) {
                        $diff = $first->scanned_at->diffInMinutes($teacherLimit);
                        $status = 'Terlambat '.self::formatDuration($diff);
                        $terlambatCount++;
                        $keteranganParts[] = 'Terlambat '.self::formatDuration($diff)." (Masuk {$first->scanned_at->format('H:i')})";
                    }
                }

                $hadirCount++;

                // Cek izin/cuti resmi GTK pada hari scan
                $gtkPermitToday = $userGtkPermits->first(function ($item) use ($dateStr) {
                    $s = $item->start_date instanceof Carbon ? $item->start_date->toDateString() : (string) $item->start_date;
                    $e = $item->end_date instanceof Carbon ? $item->end_date->toDateString() : (string) $item->end_date;

                    return $dateStr >= $s && $dateStr <= $e;
                });
                if ($gtkPermitToday) {
                    $keteranganParts[] = "{$gtkPermitToday->type_label}: {$gtkPermitToday->reason}";
                }

                $invalToday = $userInvals->first(function ($item) use ($dateStr) {
                    $jDate = $item->journal?->date;

                    return ($jDate instanceof Carbon ? $jDate->toDateString() : (string) $jDate) === $dateStr;
                });
                if ($invalToday) {
                    $keteranganParts[] = "Inval: {$invalToday->reason}";
                }

                if (! $last && ! $isHoliday) {
                    $keteranganParts[] = 'Hanya scan masuk (tidak scan pulang)';
                }

                $keterangan = ! empty($keteranganParts) ? implode('; ', $keteranganParts) : '-';

                $records->push((object) [
                    'date_raw' => $scannedDate->toDateString(),
                    'day_name' => $scannedDate->translatedFormat('l'),
                    'date_formatted' => $scannedDate->translatedFormat('d/m/Y'),
                    'pin' => $t->nigs ?: (string) $t->id,
                    'name' => $t->name,
                    'jam_masuk' => $first->scanned_at->format('H:i:s'),
                    'jam_pulang' => $last ? $last->scanned_at->format('H:i:s') : '-',
                    'status' => $status,
                    'keterangan' => $keterangan,
                ]);
            }

            foreach ($effectiveDates as $effDate) {
                $dateStr = $effDate->toDateString();
                if ($dailyGroups->has($dateStr)) {
                    continue;
                }

                // Cek apakah ada izin/cuti resmi GTK yang aktif pada tanggal ini
                $gtkPermit = $userGtkPermits->first(function ($item) use ($dateStr) {
                    $s = $item->start_date instanceof Carbon ? $item->start_date->toDateString() : (string) $item->start_date;
                    $e = $item->end_date instanceof Carbon ? $item->end_date->toDateString() : (string) $item->end_date;

                    return $dateStr >= $s && $dateStr <= $e;
                });

                if ($gtkPermit) {
                    $pType = $gtkPermit->permission_type;
                    $status = 'Izin';
                    if ($pType === 'sakit') {
                        $sakitCount++;
                        $status = 'Sakit';
                    } elseif ($pType === 'cuti') {
                        $cutiCount++;
                        $status = 'Cuti';
                    } elseif ($pType === 'dinas_luar') {
                        $dinasLuarCount++;
                        $status = 'Dinas Luar';
                    } else {
                        $izinCount++;
                        $status = 'Izin';
                    }

                    $ket = $gtkPermit->reason ?: $gtkPermit->type_label;
                    if ($gtkPermit->notes) {
                        $ket .= " ({$gtkPermit->notes})";
                    }
                    if ($gtkPermit->document_path) {
                        $ket .= ' [Ada Lampiran Surat]';
                    }

                    $records->push((object) [
                        'date_raw' => $dateStr,
                        'day_name' => $effDate->translatedFormat('l'),
                        'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                        'pin' => $t->nigs ?: (string) $t->id,
                        'name' => $t->name,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => $status,
                        'keterangan' => $ket,
                    ]);

                    continue;
                }

                $inval = $userInvals->first(function ($item) use ($dateStr) {
                    $jDate = $item->journal?->date;

                    return ($jDate instanceof Carbon ? $jDate->toDateString() : (string) $jDate) === $dateStr;
                });

                if ($inval) {
                    $reason = strtolower($inval->reason ?? '');
                    $status = 'Izin';
                    if (str_contains($reason, 'sakit')) {
                        $sakitCount++;
                        $status = 'Sakit';
                    } elseif (str_contains($reason, 'cuti')) {
                        $cutiCount++;
                        $status = 'Cuti';
                    } elseif (str_contains($reason, 'dinas') || str_contains($reason, 'diklat') || str_contains($reason, 'pelatihan')) {
                        $dinasLuarCount++;
                        $status = 'Dinas Luar';
                    } else {
                        $izinCount++;
                    }

                    $records->push((object) [
                        'date_raw' => $dateStr,
                        'day_name' => $effDate->translatedFormat('l'),
                        'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                        'pin' => $t->nigs ?: (string) $t->id,
                        'name' => $t->name,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => $status,
                        'keterangan' => $inval->reason ?: 'Izin piket/inval',
                    ]);

                    continue;
                }

                $piketAtt = $userPiketAtts->first(function ($item) use ($dateStr) {
                    $jDate = $item->journal?->date;

                    return ($jDate instanceof Carbon ? $jDate->toDateString() : (string) $jDate) === $dateStr;
                });

                if ($piketAtt) {
                    $status = 'Izin';
                    if ($piketAtt->status === 'sakit') {
                        $sakitCount++;
                        $status = 'Sakit';
                    } elseif ($piketAtt->status === 'izin') {
                        $izinCount++;
                        $status = 'Izin';
                    } elseif ($piketAtt->status === 'alpa') {
                        $alfaCount++;
                        $status = 'Alfa';
                    }

                    $records->push((object) [
                        'date_raw' => $dateStr,
                        'day_name' => $effDate->translatedFormat('l'),
                        'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                        'pin' => $t->nigs ?: (string) $t->id,
                        'name' => $t->name,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => $status,
                        'keterangan' => $piketAtt->notes ?: ($status === 'Alfa' ? 'Tanpa Keterangan' : 'Tercatat pada jurnal piket'),
                    ]);

                    continue;
                }

                $isObligated = false;
                if ($t->isStaff()) {
                    $isObligated = true;
                } else {
                    $dayName = match ($effDate->dayOfWeekIso) {
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        default => null,
                    };
                    if ($dayName && (in_array($dayName, $tLessonDays) || in_array($dayName, $tPiketDays))) {
                        $isObligated = true;
                    }
                }

                if ($isObligated) {
                    if ($effDate->lt($now->toDateString()) || ($effDate->isToday() && $now->hour >= 14)) {
                        $alfaCount++;
                        $records->push((object) [
                            'date_raw' => $dateStr,
                            'day_name' => $effDate->translatedFormat('l'),
                            'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                            'pin' => $t->nigs ?: (string) $t->id,
                            'name' => $t->name,
                            'jam_masuk' => '-',
                            'jam_pulang' => '-',
                            'status' => 'Alfa',
                            'keterangan' => 'Tanpa Keterangan',
                        ]);
                    }
                }
            }

            $records = $records->sortBy('date_raw')->values();

            $people->push((object) [
                'id' => $t->id,
                'name' => $t->name,
                'pin' => $t->nigs ?: (string) $t->id,
                'position' => $t->position ?: ($t->isStaff() ? 'Staf Administrasi' : 'Guru'),
                'category' => $t->isStaff() ? 'Staf' : 'Guru',
                'is_staff' => $t->isStaff(),
                'records' => $records,
                'total_days' => $records->count(),
                'hadir' => $hadirCount,
                'sakit' => $sakitCount,
                'izin' => $izinCount,
                'cuti' => $cutiCount,
                'dinas_luar' => $dinasLuarCount,
                'alfa' => $alfaCount,
                'terlambat' => $terlambatCount,
            ]);
        }

        return [
            'people' => $people,
            'summary' => [
                'total_people' => $people->count(),
                'active_attendees' => $people->where('hadir', '>', 0)->count(),
                'total_days_attended' => $people->sum('hadir'),
                'total_late' => $people->sum('terlambat'),
                'total_hadir' => $people->sum('hadir'),
                'total_sakit' => $people->sum('sakit'),
                'total_izin' => $people->sum('izin'),
                'total_cuti' => $people->sum('cuti'),
                'total_dinas_luar' => $people->sum('dinas_luar'),
                'total_alfa' => $people->sum('alfa'),
                'total_terlambat' => $people->sum('terlambat'),
            ],
        ];
    }

    /**
     * Build individual recap collection for Students by Classroom
     */
    protected function buildStudentIndividualRecap(
        string $startDate,
        string $endDate,
        ?int $classroomId = null,
        ?int $studentId = null,
        ?string $search = null,
        ?string $academicYear = null
    ): array {
        $studentsQuery = Student::with('classroom');

        if (! empty($classroomId)) {
            $enrolledStudentIds = [];
            if (! empty($academicYear)) {
                $ayRecord = AcademicYear::where('name', $academicYear)->first();
                $scQuery = StudentClassroom::where('classroom_id', $classroomId);
                if ($ayRecord) {
                    $scQuery->where(function ($q) use ($ayRecord, $academicYear) {
                        $q->where('academic_year_id', $ayRecord->id)
                            ->orWhere('academic_year_name', $academicYear);
                    });
                } else {
                    $scQuery->where('academic_year_name', $academicYear);
                }
                $enrolledStudentIds = $scQuery->pluck('student_id')->filter()->unique()->toArray();
            }

            if (! empty($enrolledStudentIds)) {
                $studentsQuery->whereIn('id', $enrolledStudentIds);
            } else {
                $studentsQuery->where('classroom_id', $classroomId)->where('is_active', true);
            }
        } else {
            $studentsQuery->where('is_active', true);
        }

        if (! empty($studentId)) {
            $studentsQuery->where('id', $studentId);
        }

        if (! empty($search)) {
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $students = $studentsQuery->orderBy('name')->get();
        $studentIds = $students->pluck('id')->toArray();

        $allLogs = AttendanceLog::where('user_type', 'student')
            ->where(function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds)
                    ->orWhereIn('pin', array_map('strval', $studentIds));
            })
            ->whereBetween('scanned_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('scanned_at', 'asc')
            ->get();

        $logsByStudent = $allLogs->groupBy(function ($item) {
            return $item->student_id ?? $item->pin;
        });

        $studentPermits = PiketStudentPermit::whereIn('student_id', $studentIds)
            ->whereBetween('permit_date', [$startDate, $endDate])
            ->get()
            ->groupBy('student_id');

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $now = Carbon::now();

        $effectiveDates = collect();
        for ($curr = $start->copy(); $curr->lte($end); $curr->addDay()) {
            if (in_array($curr->dayOfWeek, [0, 6])) {
                continue;
            }
            if ($this->getDayHolidayStatus($curr) !== null) {
                continue;
            }
            $effectiveDates->push($curr->copy());
        }

        $people = collect();

        foreach ($students as $s) {
            $userLogs = $logsByStudent->get($s->id, collect())
                ->merge($logsByStudent->get((string) $s->id, collect()))
                ->unique('id')
                ->sortBy('scanned_at');

            $dailyGroups = $userLogs->groupBy(fn ($item) => $item->scanned_at->toDateString());
            $records = collect();

            $hadirCount = 0;
            $sakitCount = 0;
            $izinCount = 0;
            $alfaCount = 0;
            $pulangCount = 0;
            $terlambatCount = 0;

            $sPermits = $studentPermits->get($s->id, collect());

            foreach ($dailyGroups as $dateStr => $dayItems) {
                $first = $dayItems->first();
                $last = $dayItems->count() > 1 ? $dayItems->last() : null;

                $scannedDate = $first->scanned_at;
                $isWeekend = in_array($scannedDate->dayOfWeek, [0, 6]);
                $holidayEvent = $this->getDayHolidayStatus($scannedDate);
                $isHoliday = $isWeekend || ($holidayEvent !== null);

                $status = 'Tepat Waktu';
                $keteranganParts = [];

                if ($isHoliday) {
                    $status = 'Hadir (Hari Libur)';
                    $keteranganParts[] = $holidayEvent ? $holidayEvent->title : 'Libur Akhir Pekan';
                } else {
                    $studentLimit = Carbon::parse($scannedDate->toDateString().' 07:00:00');
                    if ($first->scanned_at->gt($studentLimit)) {
                        $diff = $first->scanned_at->diffInMinutes($studentLimit);
                        $status = 'Terlambat '.self::formatDuration($diff);
                        $terlambatCount++;

                        $latePermit = $sPermits->first(fn ($item) => (is_string($item->permit_date) ? $item->permit_date : $item->permit_date->toDateString()) === $dateStr && $item->permit_type === 'terlambat');
                        $lateKet = 'Terlambat '.self::formatDuration($diff)." (Masuk {$first->scanned_at->format('H:i')})";
                        if ($latePermit && $latePermit->reason) {
                            $lateKet .= " - Alasan: {$latePermit->reason}";
                        }
                        $keteranganParts[] = $lateKet;
                    }
                }

                $hadirCount++;

                // Izin Keluar Sementara
                $exitPermit = $sPermits->first(fn ($item) => (is_string($item->permit_date) ? $item->permit_date : $item->permit_date->toDateString()) === $dateStr && $item->permit_type === 'izin_keluar_sementara');
                if ($exitPermit) {
                    $dep = $exitPermit->departure_time ? substr($exitPermit->departure_time, 0, 5) : '';
                    $ret = $exitPermit->return_time ? substr($exitPermit->return_time, 0, 5) : '';
                    $timeSpan = ($dep && $ret) ? " ({$dep}-{$ret})" : ($dep ? " ({$dep})" : '');
                    $keteranganParts[] = "Izin Keluar{$timeSpan}: {$exitPermit->reason}";
                }

                // Izin Pulang Cepat
                $earlyPermit = $sPermits->first(fn ($item) => (is_string($item->permit_date) ? $item->permit_date : $item->permit_date->toDateString()) === $dateStr && $item->permit_type === 'izin_pulang_cepat');
                if ($earlyPermit) {
                    $pulangCount++;
                    $dep = $earlyPermit->departure_time ? substr($earlyPermit->departure_time, 0, 5) : '';
                    $timeSpan = $dep ? " ({$dep})" : '';
                    $keteranganParts[] = "Izin Pulang Cepat{$timeSpan}: {$earlyPermit->reason}";
                } elseif ($last && $last->scanned_at->format('H:i:s') < '12:30:00' && ! $isHoliday) {
                    $pulangCount++;
                    $keteranganParts[] = "Pulang mendahului jam normal ({$last->scanned_at->format('H:i')})";
                }

                if (! $last && ! $isHoliday && empty($earlyPermit)) {
                    $keteranganParts[] = 'Hanya scan masuk (tidak scan pulang)';
                }

                $keterangan = ! empty($keteranganParts) ? implode('; ', $keteranganParts) : '-';

                $records->push((object) [
                    'date_raw' => $scannedDate->toDateString(),
                    'day_name' => $scannedDate->translatedFormat('l'),
                    'date_formatted' => $scannedDate->translatedFormat('d/m/Y'),
                    'pin' => $s->nisn ?: ($s->nis ?: (string) $s->id),
                    'name' => $s->name,
                    'jam_masuk' => $first->scanned_at->format('H:i:s'),
                    'jam_pulang' => $last ? $last->scanned_at->format('H:i:s') : '-',
                    'status' => $status,
                    'keterangan' => $keterangan,
                ]);
            }

            foreach ($effectiveDates as $effDate) {
                $dateStr = $effDate->toDateString();
                if ($dailyGroups->has($dateStr)) {
                    continue;
                }

                $permit = $sPermits->first(fn ($item) => (is_string($item->permit_date) ? $item->permit_date : $item->permit_date->toDateString()) === $dateStr);
                if ($permit) {
                    $status = 'Izin';
                    $ket = $permit->reason ?: $permit->permit_type_label;
                    if ($permit->permit_type === 'izin_pulang_cepat') {
                        $pulangCount++;
                        $status = 'Pulang';
                        $ket = 'Izin Pulang Cepat: '.($permit->reason ?: 'Pulang cepat');
                    } elseif ($permit->permit_type === 'izin_tidak_masuk') {
                        $reason = strtolower($permit->reason ?? '');
                        if (str_contains($reason, 'sakit')) {
                            $sakitCount++;
                            $status = 'Sakit';
                            $ket = $permit->reason ?: 'Sakit';
                        } else {
                            $izinCount++;
                            $status = 'Izin';
                            $ket = $permit->reason ?: 'Izin tidak masuk';
                        }
                    } else {
                        $izinCount++;
                    }

                    $records->push((object) [
                        'date_raw' => $dateStr,
                        'day_name' => $effDate->translatedFormat('l'),
                        'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                        'pin' => $s->nisn ?: ($s->nis ?: (string) $s->id),
                        'name' => $s->name,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => $status,
                        'keterangan' => $ket,
                    ]);

                    continue;
                }

                if ($effDate->lt($now->toDateString()) || ($effDate->isToday() && $now->hour >= 14)) {
                    $alfaCount++;
                    $records->push((object) [
                        'date_raw' => $dateStr,
                        'day_name' => $effDate->translatedFormat('l'),
                        'date_formatted' => $effDate->translatedFormat('d/m/Y'),
                        'pin' => $s->nisn ?: ($s->nis ?: (string) $s->id),
                        'name' => $s->name,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => 'Alfa',
                        'keterangan' => 'Tanpa Keterangan',
                    ]);
                }
            }

            $records = $records->sortBy('date_raw')->values();

            $people->push((object) [
                'id' => $s->id,
                'name' => $s->name,
                'pin' => $s->nisn ?: ($s->nis ?: (string) $s->id),
                'classroom_name' => $s->classroom?->name ?: '-',
                'records' => $records,
                'total_days' => $records->count(),
                'hadir' => $hadirCount,
                'sakit' => $sakitCount,
                'izin' => $izinCount,
                'alfa' => $alfaCount,
                'pulang' => $pulangCount,
                'terlambat' => $terlambatCount,
            ]);
        }

        return [
            'people' => $people,
            'summary' => [
                'total_students' => $people->count(),
                'active_attendees' => $people->where('hadir', '>', 0)->count(),
                'total_days_attended' => $people->sum('hadir'),
                'total_late' => $people->sum('terlambat'),
                'total_hadir' => $people->sum('hadir'),
                'total_sakit' => $people->sum('sakit'),
                'total_izin' => $people->sum('izin'),
                'total_alfa' => $people->sum('alfa'),
                'total_pulang' => $people->sum('pulang'),
                'total_terlambat' => $people->sum('terlambat'),
            ],
        ];
    }

    /**
     * Report: Presensi Guru & Staf (Akses: Kurikulum & TU)
     */
    public function reportTeachers(Request $request): View
    {
        if (! $request->user()->canAccessTeacherAttendanceReport()) {
            abort(403, 'Akses laporan presensi Guru & Staf hanya diperuntukkan bagi Kurikulum dan Tata Usaha.');
        }

        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $dateInfo = $this->resolveReportDates($request);

        $startDate = $dateInfo['startDate'];
        $endDate = $dateInfo['endDate'];
        $periodLabel = $dateInfo['periodLabel'];
        $periodType = $dateInfo['periodType'];

        $subType = $request->query('sub_type', 'all');
        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $search = $request->query('search');

        // Always build group recap for the Rekap Status table
        $recap = $this->buildTeacherStaffIndividualRecap($startDate, $endDate, $subType, null, $search);
        $people = $recap['people'];
        $summary = $recap['summary'];

        // Selected individual person for Rincian Presensi Individu
        $selectedPerson = null;
        if (! empty($userId)) {
            $selectedPerson = $people->firstWhere('id', $userId);
            if (! $selectedPerson) {
                $singleRecap = $this->buildTeacherStaffIndividualRecap($startDate, $endDate, 'all', $userId);
                $selectedPerson = $singleRecap['people']->first();
            }
        }

        $teachersList = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();

        $viewName = DeviceDetector::isMobile() ? 'mobile.attendance.report_teachers' : 'attendance.report_teachers';

        return view($viewName, compact(
            'people',
            'selectedPerson',
            'summary',
            'startDate',
            'endDate',
            'periodLabel',
            'periodType',
            'dateInfo',
            'subType',
            'userId',
            'search',
            'teachersList',
            'setting',
            'academicYear'
        ));
    }

    /**
     * Printable Official Attendance Report for Teachers and Staff
     */
    public function printReportTeachers(Request $request): View
    {
        if (! $request->user()->canAccessTeacherAttendanceReport()) {
            abort(403, 'Akses laporan presensi Guru & Staf hanya diperuntukkan bagi Kurikulum dan Tata Usaha.');
        }

        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $dateInfo = $this->resolveReportDates($request);

        $startDate = $dateInfo['startDate'];
        $endDate = $dateInfo['endDate'];
        $periodLabel = $dateInfo['periodLabel'];

        $subType = $request->query('sub_type', 'all');
        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $search = $request->query('search');
        $printType = $request->query('print_type');

        $recap = $this->buildTeacherStaffIndividualRecap($startDate, $endDate, $subType, $userId, $search);
        $people = $recap['people'];
        $summary = $recap['summary'];

        return view('attendance.print_teachers', compact(
            'people',
            'summary',
            'startDate',
            'endDate',
            'periodLabel',
            'subType',
            'userId',
            'printType',
            'setting',
            'academicYear'
        ));
    }

    /**
     * Report: Presensi Siswa (Akses: Wali Kelas, Kurikulum, & TU)
     */
    public function reportStudents(Request $request): View
    {
        if (! $request->user()->canAccessStudentAttendanceReport()) {
            abort(403, 'Akses laporan presensi siswa hanya diperuntukkan bagi Wali Kelas, Kurikulum, dan Tata Usaha.');
        }

        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $dateInfo = $this->resolveReportDates($request);

        $startDate = $dateInfo['startDate'];
        $endDate = $dateInfo['endDate'];
        $periodLabel = $dateInfo['periodLabel'];
        $periodType = $dateInfo['periodType'];

        $isHomeroomOnly = ! ($request->user()->isSuperAdmin() || $request->user()->isWakaKurikulum() || $request->user()->isPrincipal() || str_contains(strtolower($request->user()->position ?? ''), 'administrasi') || str_contains(strtolower($request->user()->position ?? ''), 'tata usaha'));

        if ($isHomeroomOnly) {
            $classrooms = $request->user()->homeroomClassrooms()->where('is_active', true)->orderBy('name')->get();
            if ($classrooms->isEmpty()) {
                abort(403, 'Anda belum ditugaskan sebagai wali kelas aktif.');
            }
        } else {
            $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        }

        $classroomId = $request->filled('classroom_id') ? (int) $request->query('classroom_id') : null;
        if ($isHomeroomOnly) {
            if (! in_array($classroomId, $classrooms->pluck('id')->toArray())) {
                $classroomId = $classrooms->first()->id;
            }
        } elseif (empty($classroomId) && $classrooms->isNotEmpty()) {
            $classroomId = $classrooms->first()->id;
        }

        $selectedClassroom = $classroomId ? Classroom::with('homeroomTeacher')->find($classroomId) : null;

        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;
        $search = $request->query('search');

        // Always build group recap for the Rekap Status table
        $recap = $this->buildStudentIndividualRecap($startDate, $endDate, $classroomId, null, $search, $academicYear);
        $people = $recap['people'];
        $summary = $recap['summary'];

        // Selected individual student for Rincian Presensi Individu
        $selectedPerson = null;
        if (! empty($studentId)) {
            $selectedPerson = $people->firstWhere('id', $studentId);
            if (! $selectedPerson) {
                $singleRecap = $this->buildStudentIndividualRecap($startDate, $endDate, null, $studentId, null, $academicYear);
                $selectedPerson = $singleRecap['people']->first();
            }
        }

        $studentsList = collect();
        if ($classroomId) {
            $enrolledStudentIds = [];
            if (! empty($academicYear)) {
                $ayRecord = AcademicYear::where('name', $academicYear)->first();
                $scQuery = StudentClassroom::where('classroom_id', $classroomId);
                if ($ayRecord) {
                    $scQuery->where(function ($q) use ($ayRecord, $academicYear) {
                        $q->where('academic_year_id', $ayRecord->id)
                            ->orWhere('academic_year_name', $academicYear);
                    });
                } else {
                    $scQuery->where('academic_year_name', $academicYear);
                }
                $enrolledStudentIds = $scQuery->pluck('student_id')->filter()->unique()->toArray();
            }

            if (! empty($enrolledStudentIds)) {
                $studentsList = Student::whereIn('id', $enrolledStudentIds)->orderBy('name')->get();
            } else {
                $studentsList = Student::where('classroom_id', $classroomId)->where('is_active', true)->orderBy('name')->get();
            }
        }

        return view('attendance.report_students', compact(
            'people',
            'selectedPerson',
            'summary',
            'startDate',
            'endDate',
            'periodLabel',
            'periodType',
            'dateInfo',
            'classrooms',
            'classroomId',
            'selectedClassroom',
            'studentId',
            'studentsList',
            'search',
            'isHomeroomOnly',
            'setting',
            'academicYear'
        ));
    }

    /**
     * Printable Official Attendance Report for Students by Classroom
     */
    public function printReportStudents(Request $request): View
    {
        if (! $request->user()->canAccessStudentAttendanceReport()) {
            abort(403, 'Akses laporan presensi siswa hanya diperuntukkan bagi Wali Kelas, Kurikulum, dan Tata Usaha.');
        }

        $setting = SchoolSetting::getSetting();
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $dateInfo = $this->resolveReportDates($request);

        $startDate = $dateInfo['startDate'];
        $endDate = $dateInfo['endDate'];
        $periodLabel = $dateInfo['periodLabel'];

        $isHomeroomOnly = ! ($request->user()->isSuperAdmin() || $request->user()->isWakaKurikulum() || $request->user()->isPrincipal() || str_contains(strtolower($request->user()->position ?? ''), 'administrasi') || str_contains(strtolower($request->user()->position ?? ''), 'tata usaha'));

        if ($isHomeroomOnly) {
            $classrooms = $request->user()->homeroomClassrooms()->where('is_active', true)->orderBy('name')->get();
            if ($classrooms->isEmpty()) {
                abort(403, 'Anda belum ditugaskan sebagai wali kelas aktif.');
            }
        } else {
            $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        }

        $classroomId = $request->filled('classroom_id') ? (int) $request->query('classroom_id') : null;
        if ($isHomeroomOnly) {
            if (! in_array($classroomId, $classrooms->pluck('id')->toArray())) {
                $classroomId = $classrooms->first()->id;
            }
        } elseif (empty($classroomId) && $classrooms->isNotEmpty()) {
            $classroomId = $classrooms->first()->id;
        }

        $selectedClassroom = $classroomId ? Classroom::with('homeroomTeacher')->find($classroomId) : null;
        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;
        $search = $request->query('search');
        $printType = $request->query('print_type');

        $recap = $this->buildStudentIndividualRecap($startDate, $endDate, $classroomId, $studentId, $search, $academicYear);
        $people = $recap['people'];
        $summary = $recap['summary'];

        return view('attendance.print_students', compact(
            'people',
            'summary',
            'startDate',
            'endDate',
            'periodLabel',
            'selectedClassroom',
            'printType',
            'setting',
            'academicYear'
        ));
    }

    /**
     * Backward-compatible entrypoint for attendance reports
     */
    public function report(Request $request): RedirectResponse
    {
        if ($request->query('user_type') === 'student' && $request->user()->canAccessStudentAttendanceReport()) {
            return redirect()->route('attendance.report.students', $request->query());
        }

        if ($request->user()->canAccessTeacherAttendanceReport()) {
            return redirect()->route('attendance.report.teachers', $request->query());
        }

        if ($request->user()->canAccessStudentAttendanceReport()) {
            return redirect()->route('attendance.report.students', $request->query());
        }

        abort(403, 'Akses laporan presensi tidak diizinkan.');
    }

    /**
     * Backward-compatible entrypoint for printing attendance reports
     */
    public function printReport(Request $request): RedirectResponse
    {
        if ($request->query('user_type') === 'student' && $request->user()->canAccessStudentAttendanceReport()) {
            return redirect()->route('attendance.report.students.print', $request->query());
        }

        if ($request->user()->canAccessTeacherAttendanceReport()) {
            return redirect()->route('attendance.report.teachers.print', $request->query());
        }

        if ($request->user()->canAccessStudentAttendanceReport()) {
            return redirect()->route('attendance.report.students.print', $request->query());
        }

        abort(403, 'Akses cetak laporan presensi tidak diizinkan.');
    }

    /**
     * Request sync / pull data from device
     */
    public function sync(Request $request, AttendanceDevice $device): RedirectResponse
    {
        if (! empty($device->device_sn)) {
            AttendanceDeviceCommand::create([
                'device_sn' => $device->device_sn,
                'command' => 'DATA QUERY ATTLOG',
                'status' => 'pending',
            ]);
            AttendanceDeviceCommand::create([
                'device_sn' => $device->device_sn,
                'command' => 'CHECK',
                'status' => 'pending',
            ]);
        }

        $connected = false;
        $start = microtime(true);
        $fp = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 1);
        if ($fp) {
            $connected = true;
            fclose($fp);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $device->update(['status' => 'online', 'last_activity_at' => now()]);
        }

        $msg = "Perintah sinkronisasi berhasil dikirim ke antrian mesin '{$device->name}'.";
        if ($connected) {
            $msg .= " Port {$device->port} mesin aktif dan merespons ({$latency} ms). Mesin akan segera mengunggah log absensi saat siklus komunikasi berikutnya.";
        } else {
            $msg .= " Catatan: Port {$device->port} mesin belum merespons langsung, pastikan mesin terhubung ke jaringan.";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Test connection to device
     */
    public function testConnection(AttendanceDevice $device): RedirectResponse
    {
        $start = microtime(true);
        $fp = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 2);
        if ($fp) {
            fclose($fp);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $device->update(['status' => 'online', 'last_activity_at' => now()]);

            return redirect()->back()->with('success', "Koneksi ke mesin {$device->name} ({$device->ip_address}:{$device->port}) BERHASIL! (Waktu respon: {$latency} ms)");
        }

        // Jika socket direct tertutup tapi mesin aktif berkomunikasi ADMS baru-baru ini
        if ($device->last_activity_at && $device->last_activity_at->diffInMinutes(now()) <= 5) {
            $device->update(['status' => 'online']);

            return redirect()->back()->with('warning', "Port direct ({$device->port}) tidak merespons, namun mesin {$device->name} tercatat aktif mengirim data ADMS ({$device->last_activity_at->diffForHumans()}). Status mesin tetap ONLINE.");
        }

        $device->update(['status' => 'offline']);

        return redirect()->back()->with('error', "Gagal terhubung ke mesin {$device->name} ({$device->ip_address}:{$device->port}): {$errstr}");
    }

    /**
     * Synchronize time on the device to match server time
     */
    public function syncTime(AttendanceDevice $device): RedirectResponse
    {
        $currentTime = now()->format('Y-m-d H:i:s');
        $directSuccess = app(AttendanceDeviceService::class)->syncTime($device);

        if ($directSuccess) {
            return redirect()->back()->with('success', "Jam pada mesin {$device->name} ({$device->ip_address}) BERHASIL disinkronkan secara langsung ke waktu server: {$currentTime} WIB.");
        }

        return redirect()->back()->with('success', "Perintah sinkronisasi waktu ({$currentTime} WIB) telah dikirim ke mesin {$device->name}. Mesin akan menyelaraskan waktu saat siklus komunikasi berikutnya.");
    }

    /**
     * Store a new attendance device
     */
    public function storeDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer',
            'server_port' => 'required|integer',
            'device_sn' => 'nullable|string|max:100|unique:attendance_devices,device_sn',
            'comm_key' => 'nullable|string|max:20',
        ]);

        $validated['status'] = 'offline';
        $validated['is_active'] = true;
        $validated['comm_key'] = $validated['comm_key'] ?? '0';

        AttendanceDevice::create($validated);

        return redirect()->back()->with('success', "Mesin absensi '{$validated['name']}' berhasil didaftarkan ke sistem.");
    }

    /**
     * Update device settings
     */
    public function updateDevice(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer',
            'server_port' => 'required|integer',
            'device_sn' => 'nullable|string|max:100|unique:attendance_devices,device_sn,'.$device->id,
            'comm_key' => 'nullable|string|max:20',
        ]);

        $device->update($validated);

        return redirect()->back()->with('success', "Pengaturan mesin '{$device->name}' berhasil diperbarui.");
    }

    /**
     * Delete an attendance device
     */
    public function destroyDevice(AttendanceDevice $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()->back()->with('success', "Mesin absensi '{$name}' berhasil dihapus dari sistem.");
    }

    /**
     * Upload / Synchronize User Data (GTK & Students) from server to machine(s)
     */
    public function syncUsers(Request $request): RedirectResponse
    {
        $request->validate([
            'target_type' => 'required|in:gtk,students,both',
            'device_id' => 'required|string',
            'student_filter' => 'nullable|string',
        ]);

        $targetType = $request->input('target_type');
        $deviceId = $request->input('device_id');
        $studentFilter = $request->input('student_filter', 'all');

        $devices = collect();
        if ($deviceId === 'all') {
            $devices = AttendanceDevice::where('is_active', true)->whereNotNull('device_sn')->get();
        } else {
            $dev = AttendanceDevice::find($deviceId);
            if ($dev && ! empty($dev->device_sn)) {
                $devices->push($dev);
            }
        }

        if ($devices->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada perangkat aktif dengan Serial Number (SN) yang valid untuk disinkronkan.');
        }

        $gtkCount = 0;
        $studentCount = 0;
        $queuedCommands = 0;

        foreach ($devices as $device) {
            $sn = $device->device_sn;

            // 1. Sync Data GTK (Guru & Tenaga Kependidikan)
            if ($targetType === 'gtk' || $targetType === 'both') {
                $teachers = User::operationalPersonnel()
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();

                $gtkCount = $teachers->count();

                foreach ($teachers as $teacher) {
                    $pin = $teacher->nigs ?: $teacher->id;
                    $cleanName = substr(trim(preg_replace('/[^\w\s\.\,\-]/', '', $teacher->name)), 0, 24);

                    AttendanceDeviceCommand::create([
                        'device_sn' => $sn,
                        'command' => "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=0\tGrp=1",
                        'status' => 'pending',
                    ]);
                    $queuedCommands++;
                }
            }

            // 2. Sync Data Siswa
            if ($targetType === 'students' || $targetType === 'both') {
                $studentQuery = Student::where('is_active', true);
                if (! empty($studentFilter) && $studentFilter !== 'all') {
                    $studentQuery->where('classroom_id', $studentFilter);
                }

                $students = $studentQuery->orderBy('name')->get();
                $studentCount = $students->count();

                foreach ($students as $student) {
                    $pin = $student->nisn ?: ($student->nis ?: $student->id);
                    $cleanName = substr(trim(preg_replace('/[^\w\s\.\,\-]/', '', $student->name)), 0, 24);

                    AttendanceDeviceCommand::create([
                        'device_sn' => $sn,
                        'command' => "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=0\tGrp=1",
                        'status' => 'pending',
                    ]);
                    $queuedCommands++;
                }
            }

            // Tambahkan instruksi CHECK agar mesin langsung mengecek antrean
            AttendanceDeviceCommand::create([
                'device_sn' => $sn,
                'command' => 'CHECK',
                'status' => 'pending',
            ]);
            $queuedCommands++;
        }

        $parts = [];
        if ($gtkCount > 0) {
            $parts[] = "{$gtkCount} Data GTK";
        }
        if ($studentCount > 0) {
            $parts[] = "{$studentCount} Data Siswa";
        }
        $summary = implode(' dan ', $parts);

        return redirect()->back()->with('success', "Berhasil menambahkan antrean sinkronisasi {$summary} (total {$queuedCommands} perintah) untuk {$devices->count()} mesin absensi. Mesin akan otomatis mengunduh nama & PIN pengguna secara bertahap saat siklus komunikasi berikutnya.");
    }

    /**
     * Clear test attendance logs from the web system
     */
    public function clearLogs(): RedirectResponse
    {
        AttendanceLog::query()->delete();

        return redirect()->back()->with('success', 'Semua riwayat log absensi di sistem web berhasil dikosongkan. Sistem siap menerima presensi baru.');
    }

    /**
     * Format duration in minutes into a concise human-readable string (e.g. "15 m", "50 m", "1 h", "1 h 30 m").
     */
    public static function formatDuration(int $minutes): string
    {
        $minutes = abs($minutes);
        if ($minutes < 60) {
            return "{$minutes} m";
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        if ($remMinutes === 0) {
            return "{$hours} h";
        }

        return "{$hours} h {$remMinutes} m";
    }
}
