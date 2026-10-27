<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\SubjectAttendanceSession;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeroomAttendanceRecapController extends Controller
{
    /**
     * Tampilkan Rekap Presensi Siswa Kelas Perwalian (Harian, Mingguan, Bulanan, Semesteran, Tahunan, Periode Tertentu)
     */
    public function index(Request $request): View
    {
        $viewData = $this->resolveRecapData($request);

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.homeroom_recap', $viewData);
        }

        return view('web.attendance.homeroom_recap', $viewData);
    }

    /**
     * Bangun Rekap Presensi Harian Berdasarkan Jam Pelajaran (JP 1 s.d. JP N)
     * Mengakomodasi: Hadir (H), Sakit (S), Izin (I), Dispen (D), Terlambat (T), Pulang Cepat (P), Alfa (A), Bolos (B)
     */
    protected function buildDailyRecap(?Classroom $classroom, $students, Carbon $date, ?string $academicYear = null): array
    {
        if (! $classroom) {
            return [
                'dayName' => 'Hari',
                'periods' => [],
                'sessionsByPeriod' => [],
                'studentGrid' => [],
                'piketPermits' => collect(),
                'summaryStats' => [
                    'total' => 0, 'present_full' => 0, 'partial_truant' => 0,
                    'permit' => 0, 'sick' => 0, 'dispen' => 0, 'early_leave' => 0, 'late' => 0, 'alfa' => 0,
                ],
            ];
        }

        $dateStr = $date->toDateString();
        $dayName = match ($date->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        // 1. Ambil semua jadwal pelajaran terjadwal pada hari ini untuk kelas ini
        $schedules = LessonSchedule::with(['subject', 'teacher'])
            ->where('classroom_id', $classroom->id)
            ->where('day', $dayName)
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->orderBy('start_period')
            ->get();

        // 2. Ambil seluruh sesi KBM yang sudah diisi guru mapel pada tanggal ini
        $sessions = SubjectAttendanceSession::with(['subject', 'teacher', 'records'])
            ->where('classroom_id', $classroom->id)
            ->whereDate('attendance_date', $dateStr)
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->orderBy('start_period')
            ->get();

        // Tentukan rentang Jam Pelajaran (JP) yang ada hari ini
        $maxPeriod = 10;
        if ($schedules->isNotEmpty()) {
            $maxPeriod = max($maxPeriod, (int) $schedules->max('end_period'));
        }
        if ($sessions->isNotEmpty()) {
            $maxPeriod = max($maxPeriod, (int) $sessions->max('end_period'));
        }

        $periods = range(1, $maxPeriod);

        // Petakan sesi KBM per JP
        $sessionsByPeriod = [];
        foreach ($periods as $p) {
            $sess = $sessions->first(fn ($s) => $s->start_period <= $p && $s->end_period >= $p);
            $sched = $schedules->first(fn ($sc) => $sc->start_period <= $p && $sc->end_period >= $p);

            $sessionsByPeriod[$p] = [
                'period' => $p,
                'session' => $sess,
                'schedule' => $sched,
                'subject_name' => $sess?->subject?->name ?? $sched?->subject?->name ?? '-',
                'teacher_name' => $sess?->teacher?->name ?? $sched?->teacher?->name ?? '-',
                'is_attended' => ! is_null($sess),
            ];
        }

        // 3. Ambil seluruh surat izin piket untuk siswa kelas ini pada tanggal ini (bisa multiple izin per siswa)
        $piketPermits = PiketStudentPermit::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('permit_date', $dateStr)
                    ->orWhere(function ($sub) use ($dateStr) {
                        $sub->whereDate('permit_date', '<=', $dateStr)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $dateStr);
                    });
            })
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->where('status', '!=', 'ditolak')
            ->get()
            ->groupBy('student_id');

        // 4. Bangun Matriks Status Siswa per JP
        $studentGrid = [];
        $summaryStats = [
            'total' => $students->count(),
            'present_full' => 0,
            'partial_truant' => 0,
            'sick' => 0,
            'permit' => 0,
            'dispen' => 0,
            'early_leave' => 0,
            'late' => 0,
            'alfa' => 0,
        ];

        foreach ($students as $stu) {
            $studentPermitsList = $piketPermits->get($stu->id, collect());
            $primaryPermit = $studentPermitsList->first();

            $latePermit = $studentPermitsList->firstWhere('permit_type', 'terlambat');
            $pulangPermit = $studentPermitsList->firstWhere('permit_type', 'izin_pulang_cepat');
            $absentPermit = $studentPermitsList->firstWhere('permit_type', 'izin_tidak_masuk');
            $keluarPermit = $studentPermitsList->firstWhere('permit_type', 'izin_keluar_sementara');

            $latePeriod = $latePermit?->getAffectedPeriod();
            $pulangPeriod = $pulangPermit?->getAffectedPeriod();

            $periodStatuses = [];
            $hasPresent = false;
            $hasAlfa = false;
            $hasSick = false;
            $hasPermit = false;
            $hasDispen = false;
            $hasEarlyLeave = ! is_null($pulangPermit);
            $hasLate = ! is_null($latePermit);
            $alfaPeriods = [];

            foreach ($periods as $p) {
                $sessInfo = $sessionsByPeriod[$p];
                $sess = $sessInfo['session'];

                $status = null;
                $label = '-';
                $notes = null;

                // A. Surat Pulang Cepat: Jika jam >= jam pulang, otomatis berstatus Pulang (P)
                if ($pulangPermit && $pulangPeriod && $p >= $pulangPeriod) {
                    $status = 'p';
                    $label = 'P';
                    $notes = 'Izin Pulang Cepat: '.$pulangPermit->reason;
                }
                // B. Surat Terlambat: Jika jam < jam masuk izin terlambat, berstatus Terlambat (T)
                elseif ($latePermit && $latePeriod && $p < $latePeriod) {
                    $status = 't';
                    $label = 'T';
                    $notes = 'Terlambat Masuk Jam ke-'.$latePeriod.': '.$latePermit->reason;
                }
                // C. Jam Pelajaran Terabsen oleh Guru Mapel
                elseif ($sess) {
                    $rec = $sess->records->firstWhere('student_id', $stu->id);
                    $rawStatus = $rec?->status ?? 'h';

                    // Jika guru mapel menandai 'a' (alfa) padahal siswa punya izin resmi pulang cepat di jam ini
                    if ($rawStatus === 'a' && $pulangPermit && $pulangPeriod && $p >= $pulangPeriod) {
                        $status = 'p';
                        $label = 'P';
                    } else {
                        $status = $rawStatus;
                        $label = strtoupper($status);
                    }
                    $notes = $rec?->notes;
                }
                // D. Jam KBM Belum Diabsen Guru Mapel, namun ada izin seharian penuh
                elseif ($absentPermit) {
                    if ($absentPermit->permit_category === 'sakit') {
                        $status = 's';
                        $label = 'S';
                    } elseif ($absentPermit->permit_category === 'dispen') {
                        $status = 'd';
                        $label = 'D';
                    } else {
                        $status = 'i';
                        $label = 'I';
                    }
                    $notes = $absentPermit->reason;
                }
                // E. Izin Keluar Sementara di jam ini
                elseif ($keluarPermit && $keluarPermit->getAffectedPeriod() == $p) {
                    $status = 'd';
                    $label = 'D';
                    $notes = 'Izin Keluar: '.$keluarPermit->reason;
                }

                // Klasifikasi Kehadiran
                if ($status === 'h') {
                    $hasPresent = true;
                } elseif ($status === 't') {
                    $hasPresent = true; // Terlambat tetap hadir di sekolah
                    $hasLate = true;
                    if (! $latePeriod) {
                        $latePeriod = $p;
                    }
                } elseif ($status === 'a') {
                    $hasAlfa = true;
                    $alfaPeriods[] = $p;
                } elseif ($status === 's') {
                    $hasSick = true;
                } elseif ($status === 'i') {
                    $hasPermit = true;
                } elseif ($status === 'd') {
                    $hasDispen = true;
                }

                $periodStatuses[$p] = [
                    'status' => $status,
                    'label' => $label,
                    'subject' => $sessInfo['subject_name'],
                    'notes' => $notes,
                ];
            }

            // Analisis status keseluruhan hari ini
            $finalStatus = 'Hadir Penuh';
            $finalBadge = 'bg-success text-white';
            $isTruant = false;

            if ($hasPresent && $hasAlfa) {
                // Bolos (Hadir di beberapa jam, tapi Alfa tanpa izin di jam lain)
                $finalStatus = 'Bolos (JP '.implode(',', $alfaPeriods).')';
                $finalBadge = 'bg-danger text-white border border-danger';
                $isTruant = true;
                $summaryStats['partial_truant']++;
            } elseif ($hasEarlyLeave) {
                $finalStatus = 'Pulang (JP '.($pulangPeriod ?? '-').')';
                $finalBadge = 'bg-purple text-white';
                $summaryStats['early_leave']++;
            } elseif ($hasLate && $hasPresent && ! $hasAlfa) {
                $finalStatus = 'Terlambat (JP '.($latePeriod ?? '-').')';
                $finalBadge = 'bg-warning text-dark border border-warning';
                $summaryStats['late']++;
            } elseif ($hasAlfa && ! $hasPresent) {
                $finalStatus = 'Alfa';
                $finalBadge = 'bg-danger-lt text-danger border border-danger';
                $summaryStats['alfa']++;
            } elseif ($hasSick && ! $hasPresent) {
                $finalStatus = 'Sakit';
                $finalBadge = 'bg-warning-lt text-dark border border-warning';
                $summaryStats['sick']++;
            } elseif ($hasDispen) {
                $finalStatus = 'Dispensasi';
                $finalBadge = 'bg-purple-lt text-purple border border-purple';
                $summaryStats['dispen']++;
            } elseif ($hasPermit && ! $hasPresent) {
                $finalStatus = 'Izin';
                $finalBadge = 'bg-info-lt text-info border border-info';
                $summaryStats['permit']++;
            } elseif ($hasPresent) {
                $finalStatus = 'Hadir Penuh';
                $finalBadge = 'bg-success-lt text-success border border-success';
                $summaryStats['present_full']++;
            } else {
                $finalStatus = 'Belum Ada Data';
                $finalBadge = 'bg-light text-muted border';
            }

            $studentGrid[$stu->id] = [
                'student' => $stu,
                'permit' => $primaryPermit,
                'permits_list' => $studentPermitsList,
                'periods' => $periodStatuses,
                'final_status' => $finalStatus,
                'final_badge' => $finalBadge,
                'is_truant' => $isTruant,
                'has_late' => $hasLate,
                'has_early_leave' => $hasEarlyLeave,
            ];
        }

        return [
            'dayName' => $dayName,
            'periods' => $periods,
            'sessionsByPeriod' => $sessionsByPeriod,
            'studentGrid' => $studentGrid,
            'piketPermits' => $piketPermits,
            'summaryStats' => $summaryStats,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
        ];
    }

    /**
     * Bangun Rekap Presensi Mingguan (Senin s.d. Jumat - KBM 5 Hari Kerja)
     * Mengakomodasi: H, S, I, D, T, P, A, Bolos
     */
    protected function buildWeeklyRecap(?Classroom $classroom, $students, Carbon $date, ?string $academicYear = null): array
    {
        if (! $classroom) {
            return ['weekDays' => [], 'studentWeeklyGrid' => []];
        }

        $startOfWeek = $date->copy()->startOfWeek(); // Senin
        $endOfWeek = $date->copy()->startOfWeek()->addDays(4); // Jumat (KBM 5 Hari Kerja)

        $days = [];
        $period = CarbonPeriod::create($startOfWeek, $endOfWeek);
        foreach ($period as $d) {
            $days[] = [
                'date' => $d->toDateString(),
                'dayName' => match ($d->dayOfWeekIso) {
                    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
                    4 => 'Kamis', 5 => 'Jumat',
                    default => 'Lainnya',
                },
                'shortName' => match ($d->dayOfWeekIso) {
                    1 => 'Sen', 2 => 'Sel', 3 => 'Rab',
                    4 => 'Kam', 5 => 'Jum',
                    default => '-',
                },
                'formatted' => $d->translatedFormat('d M'),
            ];
        }

        // Ambil semua sesi di pekan ini
        $sessions = SubjectAttendanceSession::with(['records'])
            ->where('classroom_id', $classroom->id)
            ->whereBetween('attendance_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->get();

        // Ambil perizinan piket di pekan ini
        $weekPermits = PiketStudentPermit::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('permit_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                    ->orWhere(function ($sub) use ($startOfWeek, $endOfWeek) {
                        $sub->whereDate('permit_date', '<=', $endOfWeek->toDateString())
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $startOfWeek->toDateString());
                    });
            })
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->where('status', '!=', 'ditolak')
            ->get()
            ->groupBy('student_id');

        $studentWeeklyGrid = [];
        foreach ($students as $stu) {
            $daySummaries = [];
            $totalH = 0;
            $totalS = 0;
            $totalI = 0;
            $totalD = 0;
            $totalT = 0;
            $totalP = 0;
            $totalA = 0;
            $totalTruancy = 0;

            $stuPermits = $weekPermits->get($stu->id, collect());

            foreach ($days as $dayInfo) {
                $dayDate = $dayInfo['date'];
                $daySessions = $sessions->filter(fn ($s) => $s->attendance_date->toDateString() === $dayDate);

                $dayPermit = $stuPermits->first(function ($p) use ($dayDate) {
                    return $p->permit_date->toDateString() === $dayDate ||
                        ($p->end_date && $p->permit_date->toDateString() <= $dayDate && $p->end_date->toDateString() >= $dayDate);
                });

                if ($daySessions->isEmpty() && ! $dayPermit) {
                    $daySummaries[$dayDate] = ['status' => '-', 'label' => '-', 'badge' => 'text-muted'];

                    continue;
                }

                $statusesInDay = [];
                foreach ($daySessions as $s) {
                    $rec = $s->records->firstWhere('student_id', $stu->id);
                    $statusesInDay[] = $rec?->status ?? 'h';
                }

                $hasH = in_array('h', $statusesInDay) || in_array('t', $statusesInDay);
                $hasA = in_array('a', $statusesInDay);
                $hasS = in_array('s', $statusesInDay) || ($dayPermit && $dayPermit->permit_category === 'sakit');
                $hasI = in_array('i', $statusesInDay) || ($dayPermit && $dayPermit->permit_type === 'izin_tidak_masuk' && $dayPermit->permit_category === 'izin');
                $hasD = in_array('d', $statusesInDay) || ($dayPermit && $dayPermit->permit_category === 'dispen');
                $hasP = ($dayPermit && $dayPermit->permit_type === 'izin_pulang_cepat');
                $hasT = in_array('t', $statusesInDay) || ($dayPermit && $dayPermit->permit_type === 'terlambat');

                if ($hasH && $hasA && ! $hasP) {
                    $daySummaries[$dayDate] = ['status' => 'bolos', 'label' => 'Bolos', 'badge' => 'bg-danger text-white'];
                    $totalTruancy++;
                } elseif ($hasP) {
                    $daySummaries[$dayDate] = ['status' => 'p', 'label' => 'P', 'badge' => 'bg-purple text-white'];
                    $totalP++;
                    if ($hasH) {
                        $totalH++;
                    }
                } elseif ($hasT && $hasH) {
                    $daySummaries[$dayDate] = ['status' => 't', 'label' => 'T', 'badge' => 'bg-warning text-dark border border-warning'];
                    $totalT++;
                    $totalH++;
                } elseif ($hasD) {
                    $daySummaries[$dayDate] = ['status' => 'd', 'label' => 'D', 'badge' => 'bg-purple-lt text-purple'];
                    $totalD++;
                } elseif ($hasA && ! $hasH) {
                    $daySummaries[$dayDate] = ['status' => 'a', 'label' => 'A', 'badge' => 'bg-danger-lt text-danger'];
                    $totalA++;
                } elseif ($hasS && ! $hasH) {
                    $daySummaries[$dayDate] = ['status' => 's', 'label' => 'S', 'badge' => 'bg-warning-lt text-warning'];
                    $totalS++;
                } elseif ($hasI && ! $hasH) {
                    $daySummaries[$dayDate] = ['status' => 'i', 'label' => 'I', 'badge' => 'bg-info-lt text-info'];
                    $totalI++;
                } elseif ($hasH) {
                    $daySummaries[$dayDate] = ['status' => 'h', 'label' => 'H', 'badge' => 'bg-success-lt text-success'];
                    $totalH++;
                } else {
                    $daySummaries[$dayDate] = ['status' => '-', 'label' => '-', 'badge' => 'text-muted'];
                }
            }

            $studentWeeklyGrid[$stu->id] = [
                'student' => $stu,
                'days' => $daySummaries,
                'total_h' => $totalH,
                'total_s' => $totalS,
                'total_i' => $totalI,
                'total_d' => $totalD,
                'total_t' => $totalT,
                'total_p' => $totalP,
                'total_a' => $totalA,
                'total_truant' => $totalTruancy,
            ];
        }

        return [
            'weekDays' => $days,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'studentWeeklyGrid' => $studentWeeklyGrid,
            'prevWeek' => $startOfWeek->copy()->subWeek()->toDateString(),
            'nextWeek' => $startOfWeek->copy()->addWeek()->toDateString(),
        ];
    }

    /**
     * Bangun Rekap Presensi Rentang Tanggal (Digunakan oleh Bulanan, Semesteran, Tahunan, dan Rentang Kustom)
     */
    protected function buildRangeRecap(?Classroom $classroom, $students, Carbon $startDate, Carbon $endDate, string $periodTitle, array $extra = [], ?string $academicYear = null): array
    {
        if (! $classroom) {
            return array_merge([
                'periodTitle' => $periodTitle,
                'totalActiveDays' => 0,
                'studentMonthlyGrid' => [],
                'studentRangeGrid' => [],
                'startDate' => $startDate,
                'endDate' => $endDate,
            ], $extra);
        }

        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        // Ambil semua records sesi di rentang ini
        $sessions = SubjectAttendanceSession::with('records')
            ->where('classroom_id', $classroom->id)
            ->whereBetween('attendance_date', [$startDateStr, $endDateStr])
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->get();

        // Ambil perizinan piket di rentang ini
        $rangePermits = PiketStudentPermit::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->whereBetween('permit_date', [$startDateStr, $endDateStr])
                    ->orWhere(function ($sub) use ($startDateStr, $endDateStr) {
                        $sub->whereDate('permit_date', '<=', $endDateStr)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $startDateStr);
                    });
            })
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->where('academic_year', $academicYear)->orWhereNull('academic_year')))
            ->where('status', '!=', 'ditolak')
            ->get()
            ->groupBy('student_id');

        // Kelompokkan sesi per tanggal untuk menghitung kehadiran per hari (Khusus hari Senin s.d. Jumat - KBM 5 Hari Kerja)
        $datesWithSession = $sessions->pluck('attendance_date')
            ->filter(fn ($d) => $d->dayOfWeekIso <= 5)
            ->map->toDateString()
            ->unique()
            ->values();
        $totalActiveDays = $datesWithSession->count();

        $studentRangeGrid = [];
        foreach ($students as $stu) {
            $daysPresent = 0;
            $daysSick = 0;
            $daysPermit = 0;
            $daysDispen = 0;
            $daysLate = 0;
            $daysEarlyLeave = 0;
            $daysAlfa = 0;
            $truancyDays = 0;

            $stuPermits = $rangePermits->get($stu->id, collect());

            foreach ($datesWithSession as $dStr) {
                $daySessions = $sessions->filter(fn ($s) => $s->attendance_date->toDateString() === $dStr);
                $statusesInDay = [];
                foreach ($daySessions as $s) {
                    $rec = $s->records->firstWhere('student_id', $stu->id);
                    $statusesInDay[] = $rec?->status ?? 'h';
                }

                $dayPermit = $stuPermits->first(function ($p) use ($dStr) {
                    return $p->permit_date->toDateString() === $dStr ||
                        ($p->end_date && $p->permit_date->toDateString() <= $dStr && $p->end_date->toDateString() >= $dStr);
                });

                $hasH = in_array('h', $statusesInDay) || in_array('t', $statusesInDay);
                $hasA = in_array('a', $statusesInDay);
                $hasS = in_array('s', $statusesInDay) || ($dayPermit && $dayPermit->permit_category === 'sakit');
                $hasI = in_array('i', $statusesInDay) || ($dayPermit && $dayPermit->permit_type === 'izin_tidak_masuk' && $dayPermit->permit_category === 'izin');
                $hasD = in_array('d', $statusesInDay) || ($dayPermit && $dayPermit->permit_category === 'dispen');
                $hasP = ($dayPermit && $dayPermit->permit_type === 'izin_pulang_cepat');
                $hasT = in_array('t', $statusesInDay) || ($dayPermit && $dayPermit->permit_type === 'terlambat');

                if ($hasT) {
                    $daysLate++;
                }
                if ($hasP) {
                    $daysEarlyLeave++;
                }

                if ($hasH && $hasA && ! $hasP) {
                    $truancyDays++;
                    $daysPresent++;
                } elseif ($hasA && ! $hasH) {
                    $daysAlfa++;
                } elseif ($hasS && ! $hasH) {
                    $daysSick++;
                } elseif ($hasD) {
                    $daysDispen++;
                    $daysPresent++; // Dispen resmi dihitung hadir secara kedinasan
                } elseif ($hasI && ! $hasH) {
                    $daysPermit++;
                } elseif ($hasH) {
                    $daysPresent++;
                }
            }

            $attendanceRate = $totalActiveDays > 0 ? round(($daysPresent / $totalActiveDays) * 100, 1) : 0;

            // Perhitungan Agregasi Standar Dapodik & Raport (H, S, I, A):
            $dapodikSick = $daysSick;
            $dapodikPermit = $daysPermit;
            $dapodikAlfa = $daysAlfa;
            $dapodikPresent = max(0, $totalActiveDays - ($dapodikSick + $dapodikPermit + $dapodikAlfa));

            $studentRangeGrid[$stu->id] = [
                'student' => $stu,
                'days_present' => $daysPresent,
                'days_sick' => $daysSick,
                'days_permit' => $daysPermit,
                'days_dispen' => $daysDispen,
                'days_late' => $daysLate,
                'days_early_leave' => $daysEarlyLeave,
                'days_alfa' => $daysAlfa,
                'days_truant' => $truancyDays,
                'attendance_rate' => $attendanceRate,
                // Format Standar Dapodik & Raport
                'dapodik_present' => $dapodikPresent,
                'dapodik_sick' => $dapodikSick,
                'dapodik_permit' => $dapodikPermit,
                'dapodik_alfa' => $dapodikAlfa,
            ];
        }

        return array_merge([
            'periodTitle' => $periodTitle,
            'totalActiveDays' => $totalActiveDays,
            'studentMonthlyGrid' => $studentRangeGrid,
            'studentRangeGrid' => $studentRangeGrid,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], $extra);
    }

    /**
     * Bangun Rekap Presensi Bulanan (Akumulasi Angka & Persentase Kehadiran untuk Analisis Keaktifan Siswa)
     */
    protected function buildMonthlyRecap(?Classroom $classroom, $students, Carbon $date, ?string $academicYear = null): array
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        return $this->buildRangeRecap($classroom, $students, $startOfMonth, $endOfMonth, 'Rekap Bulanan: Bulan '.$date->translatedFormat('F Y'), [
            'monthName' => $date->translatedFormat('F Y'),
            'prevMonth' => $startOfMonth->copy()->subMonth()->toDateString(),
            'nextMonth' => $startOfMonth->copy()->addMonth()->toDateString(),
        ], $academicYear);
    }

    /**
     * Bangun Rekap Presensi Semesteran (Ganjil / Genap)
     */
    protected function buildSemesterRecap(?Classroom $classroom, $students, string $academicYear, string $semester): array
    {
        $years = explode('/', $academicYear);
        $y1 = (int) ($years[0] ?? now()->year);
        $y2 = (int) ($years[1] ?? ($y1 + 1));

        if ($semester === 'genap') {
            $startDate = Carbon::create($y2, 1, 1)->startOfDay();
            $endDate = Carbon::create($y2, 6, 30)->endOfDay();
            $title = 'Semester Genap T.A. '.$academicYear.' (Januari - Juni '.$y2.')';
        } else {
            $startDate = Carbon::create($y1, 7, 1)->startOfDay();
            $endDate = Carbon::create($y1, 12, 31)->endOfDay();
            $title = 'Semester Ganjil T.A. '.$academicYear.' (Juli - Desember '.$y1.')';
        }

        return $this->buildRangeRecap($classroom, $students, $startDate, $endDate, 'Rekap Semester: '.$title, [
            'activeSemester' => $semester,
            'academicYear' => $academicYear,
            'semesterTitle' => $title,
        ], $academicYear);
    }

    /**
     * Bangun Rekap Presensi Tahunan (1 Tahun Ajaran Penuh: Juli s.d. Juni)
     */
    protected function buildYearlyRecap(?Classroom $classroom, $students, string $academicYear): array
    {
        $years = explode('/', $academicYear);
        $y1 = (int) ($years[0] ?? now()->year);
        $y2 = (int) ($years[1] ?? ($y1 + 1));

        $startDate = Carbon::create($y1, 7, 1)->startOfDay();
        $endDate = Carbon::create($y2, 6, 30)->endOfDay();
        $title = 'Tahun Ajaran '.$academicYear.' (Juli '.$y1.' - Juni '.$y2.')';

        return $this->buildRangeRecap($classroom, $students, $startDate, $endDate, 'Rekap Tahunan: '.$title, [
            'academicYear' => $academicYear,
            'yearlyTitle' => $title,
        ], $academicYear);
    }

    /**
     * Bangun Rekap Presensi Rentang Tanggal Tertentu (Kustom)
     */
    protected function buildCustomRecap(?Classroom $classroom, $students, Carbon $startDate, Carbon $endDate, ?string $academicYear = null): array
    {
        $title = 'Periode '.$startDate->translatedFormat('d M Y').' s.d. '.$endDate->translatedFormat('d M Y');

        return $this->buildRangeRecap($classroom, $students, $startDate, $endDate, 'Rekap Rentang Tanggal: '.$title, [
            'customTitle' => $title,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], $academicYear);
    }

    /**
     * Resolve data kelas dan siswa yang diotorisasi untuk ekspor
     */
    protected function resolveRecapData(Request $request): array
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $years = explode('/', (string) $academicYear);
        $y1 = (int) ($years[0] ?? now()->year);
        $y2 = (int) ($years[1] ?? ($y1 + 1));
        $academicYearClean = "{$y1}_{$y2}";

        $canViewAll = $user && ($user->isSuperAdmin() || $user->isWakaKurikulum() || $user->hasRole('kepala_sekolah'));

        $ayRecord = AcademicYear::where('name', $academicYear)->first();

        if ($canViewAll) {
            $classroomQuery = Classroom::query();
            if ($ayRecord) {
                $classroomQuery->where(function ($q) use ($academicYear, $ayRecord) {
                    $q->where('academic_year_id', $ayRecord->id)
                        ->orWhere(function ($sub) use ($academicYear) {
                            $sub->whereNull('academic_year_id')
                                ->where('academic_year', $academicYear);
                        });
                });
            } else {
                $classroomQuery->where('academic_year', $academicYear);
            }
            $myClassrooms = $classroomQuery->orderBy('name')->get();
            if ($myClassrooms->isEmpty()) {
                $myClassrooms = Classroom::orderBy('name')->get();
            }
        } else {
            $classroomQuery = Classroom::where('homeroom_teacher_id', $user?->id ?? 0)
                ->where('is_active', true);

            if ($ayRecord) {
                $classroomQuery->where(function ($q) use ($academicYear, $ayRecord) {
                    $q->where('academic_year_id', $ayRecord->id)
                        ->orWhere(function ($sub) use ($academicYear) {
                            $sub->whereNull('academic_year_id')
                                ->where('academic_year', $academicYear);
                        });
                });
            } else {
                $classroomQuery->where('academic_year', $academicYear);
            }

            $myClassrooms = $classroomQuery->orderBy('name')->get();
        }

        if (! $canViewAll && $request->filled('classroom_id')) {
            $requestedId = (int) $request->query('classroom_id');
            if (! $myClassrooms->contains('id', $requestedId)) {
                abort(403, 'Anda tidak memiliki hak akses untuk melihat rekapitulasi kelas ini.');
            }
        }

        $selectedClassroomId = $request->query('classroom_id', $myClassrooms->first()?->id);
        $selectedClassroom = $myClassrooms->firstWhere('id', $selectedClassroomId)
            ?? ($canViewAll ? Classroom::find($selectedClassroomId) : null);

        $mode = $request->query('mode', 'daily');
        if (! in_array($mode, ['daily', 'weekly', 'monthly', 'semester', 'yearly', 'custom'])) {
            $mode = 'daily';
        }

        $dateParam = $request->query('date', now()->toDateString());
        $currentDate = Carbon::parse($dateParam);
        $viewType = $request->query('view_type', 'internal'); // 'internal' atau 'dapodik'

        // Semester: ganjil / genap (default auto dari bulan atau setting)
        $semesterParam = strtolower((string) $request->query('semester', ''));
        if (! in_array($semesterParam, ['ganjil', 'genap'])) {
            $settingSem = strtolower((string) ($setting->semester ?? ''));
            if (in_array($settingSem, ['ganjil', 'genap'])) {
                $semesterParam = $settingSem;
            } else {
                $semesterParam = $currentDate->month >= 7 ? 'ganjil' : 'genap';
            }
        }

        // Custom range dates
        $startDateParam = $request->query('start_date', $currentDate->copy()->startOfMonth()->toDateString());
        $endDateParam = $request->query('end_date', $currentDate->toDateString());
        $startDate = Carbon::parse($startDateParam)->startOfDay();
        $endDate = Carbon::parse($endDateParam)->endOfDay();
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        $students = collect();
        if ($selectedClassroom) {
            $studentIdsQuery = StudentClassroom::where('classroom_id', $selectedClassroom->id);
            if ($ayRecord) {
                $studentIdsQuery->where(function ($q) use ($ayRecord, $academicYear) {
                    $q->where('academic_year_id', $ayRecord->id)
                        ->orWhere('academic_year_name', $academicYear);
                });
            } else {
                $studentIdsQuery->where('academic_year_name', $academicYear);
            }

            $enrolledStudentIds = $studentIdsQuery->pluck('student_id')->filter()->unique()->toArray();

            if (! empty($enrolledStudentIds)) {
                $students = Student::whereIn('id', $enrolledStudentIds)
                    ->orderBy('name')
                    ->get();
            } else {
                $students = Student::where('classroom_id', $selectedClassroom->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }

        $recapData = match ($mode) {
            'weekly' => $this->buildWeeklyRecap($selectedClassroom, $students, $currentDate, $academicYear),
            'monthly' => $this->buildMonthlyRecap($selectedClassroom, $students, $currentDate, $academicYear),
            'semester' => $this->buildSemesterRecap($selectedClassroom, $students, $academicYear, $semesterParam),
            'yearly' => $this->buildYearlyRecap($selectedClassroom, $students, $academicYear),
            'custom' => $this->buildCustomRecap($selectedClassroom, $students, $startDate, $endDate, $academicYear),
            default => $this->buildDailyRecap($selectedClassroom, $students, $currentDate, $academicYear),
        };

        return array_merge([
            'setting' => $setting,
            'user' => $user,
            'academicYear' => $academicYear,
            'academicYearClean' => $academicYearClean,
            'myClassrooms' => $myClassrooms,
            'selectedClassroomId' => $selectedClassroomId,
            'selectedClassroom' => $selectedClassroom,
            'canViewAll' => $canViewAll,
            'mode' => $mode,
            'date' => $currentDate->toDateString(),
            'currentDate' => $currentDate,
            'activeSemester' => $semesterParam,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startDateStr' => $startDate->toDateString(),
            'endDateStr' => $endDate->toDateString(),
            'viewType' => $viewType,
            'students' => $students,
        ], $recapData);
    }

    /**
     * Ekspor Rekap Presensi Siswa Kelas ke Format Excel (.csv dengan BOM UTF-8)
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->resolveRecapData($request);
        $classroom = $data['selectedClassroom'];

        if (! $classroom) {
            abort(404, 'Kelas tidak ditemukan atau Anda tidak memiliki akses ke kelas ini.');
        }
        $mode = $data['mode'];
        $currentDate = $data['currentDate'];
        $setting = $data['setting'];

        $className = $classroom ? str_replace([' ', '/', '\\'], '_', $classroom->name) : 'Kelas';
        $filename = match ($mode) {
            'weekly' => "Rekap_Presensi_{$className}_Mingguan_{$data['startOfWeek']->format('Ymd')}.csv",
            'monthly' => "Rekap_Presensi_{$className}_Bulanan_{$currentDate->format('Y_m')}.csv",
            'semester' => "Rekap_Presensi_{$className}_Semester_{$data['activeSemester']}_{$data['academicYearClean']}.csv",
            'yearly' => "Rekap_Presensi_{$className}_Tahunan_{$data['academicYearClean']}.csv",
            'custom' => "Rekap_Presensi_{$className}_Periode_{$data['startDate']->format('Ymd')}_{$data['endDate']->format('Ymd')}.csv",
            default => "Rekap_Presensi_{$className}_Harian_{$currentDate->format('Ymd')}.csv",
        };

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($data, $mode, $classroom, $currentDate, $setting) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM untuk Excel

            // Header Instansi / Sekolah
            fputcsv($handle, ['REKAPITULASI PRESENSI SISWA KELAS']);
            fputcsv($handle, [$setting->school_name ?? 'SMK Negeri']);
            fputcsv($handle, ['Kelas: '.($classroom?->name ?? '-').' | Wali Kelas: '.($classroom?->homeroomTeacher?->name ?? '-')]);

            if ($mode === 'daily') {
                fputcsv($handle, ['Tanggal: '.$data['dayName'].', '.$currentDate->translatedFormat('d F Y')]);
                fputcsv($handle, []);

                // Header Kolom Mode Harian
                $rowHead = ['No', 'NISN', 'Nama Siswa', 'L/P'];
                foreach ($data['periods'] as $p) {
                    $pInfo = $data['sessionsByPeriod'][$p] ?? null;
                    $subj = $pInfo['subject_name'] ?? "JP {$p}";
                    $rowHead[] = "JP {$p} ({$subj})";
                }
                $rowHead[] = 'Status Akhir';
                fputcsv($handle, $rowHead);

                // Baris Siswa
                $no = 1;
                foreach ($data['studentGrid'] as $row) {
                    $stu = $row['student'];
                    $line = [
                        $no++,
                        $stu->nisn ?: $stu->nis ?: '-',
                        $stu->name,
                        $stu->gender ?? '-',
                    ];
                    foreach ($data['periods'] as $p) {
                        $periodItem = $row['periods'][$p] ?? null;
                        $line[] = $periodItem['label'] ?? '-';
                    }
                    $line[] = $row['final_status'];
                    fputcsv($handle, $line);
                }

                // Ringkasan
                fputcsv($handle, []);
                fputcsv($handle, ['Ringkasan Kehadiran:']);
                fputcsv($handle, ['Hadir Penuh', $data['summaryStats']['present_full']]);
                fputcsv($handle, ['Bolos Sebagian JP', $data['summaryStats']['partial_truant']]);
                fputcsv($handle, ['Dispen', $data['summaryStats']['dispen']]);
                fputcsv($handle, ['Sakit', $data['summaryStats']['sick']]);
                fputcsv($handle, ['Izin', $data['summaryStats']['permit']]);
                fputcsv($handle, ['Pulang Cepat', $data['summaryStats']['early_leave']]);
                fputcsv($handle, ['Terlambat', $data['summaryStats']['late']]);
                fputcsv($handle, ['Alfa', $data['summaryStats']['alfa']]);
            } elseif ($mode === 'weekly') {
                fputcsv($handle, ['Periode Mingguan: '.$data['startOfWeek']->translatedFormat('d M Y').' s.d. '.$data['endOfWeek']->translatedFormat('d M Y')]);
                fputcsv($handle, []);

                // Header Kolom Mode Mingguan
                $rowHead = ['No', 'NISN', 'Nama Siswa', 'L/P'];
                foreach ($data['weekDays'] as $d) {
                    $rowHead[] = $d['dayName'].' ('.$d['formatted'].')';
                }
                $rowHead = array_merge($rowHead, ['Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Dispen (D)', 'Terlambat (T)', 'Pulang (P)', 'Alfa (A)', 'Bolos']);
                fputcsv($handle, $rowHead);

                $no = 1;
                foreach ($data['studentWeeklyGrid'] as $row) {
                    $stu = $row['student'];
                    $line = [
                        $no++,
                        $stu->nisn ?: $stu->nis ?: '-',
                        $stu->name,
                        $stu->gender ?? '-',
                    ];
                    foreach ($data['weekDays'] as $d) {
                        $line[] = $row['days'][$d['date']]['label'] ?? '-';
                    }
                    $line[] = $row['total_h'];
                    $line[] = $row['total_s'];
                    $line[] = $row['total_i'];
                    $line[] = $row['total_d'];
                    $line[] = $row['total_t'];
                    $line[] = $row['total_p'];
                    $line[] = $row['total_a'];
                    $line[] = $row['total_truant'];
                    fputcsv($handle, $line);
                }
            } else {
                // Mode Bulanan, Semesteran, Tahunan, Custom
                fputcsv($handle, ['Periode: '.($data['periodTitle'] ?? 'Rekapitulasi').' (Total Hari Efektif: '.$data['totalActiveDays'].' Hari)']);
                fputcsv($handle, []);

                // Header Kolom Mode Bulanan / Rentang
                fputcsv($handle, [
                    'No', 'NISN', 'Nama Siswa', 'L/P',
                    'Hadir (Hari)', 'Sakit (S)', 'Izin (I)', 'Dispen (D)',
                    'Terlambat (T)', 'Pulang Cepat (P)', 'Alfa (A)', 'Bolos Jam',
                    '% Kehadiran', 'Dapodik H', 'Dapodik S', 'Dapodik I', 'Dapodik A',
                ]);

                $no = 1;
                foreach ($data['studentMonthlyGrid'] as $row) {
                    $stu = $row['student'];
                    fputcsv($handle, [
                        $no++,
                        $stu->nisn ?: $stu->nis ?: '-',
                        $stu->name,
                        $stu->gender ?? '-',
                        $row['days_present'],
                        $row['days_sick'],
                        $row['days_permit'],
                        $row['days_dispen'],
                        $row['days_late'],
                        $row['days_early_leave'],
                        $row['days_alfa'],
                        $row['days_truant'],
                        $row['attendance_rate'].'%',
                        $row['dapodik_present'],
                        $row['dapodik_sick'],
                        $row['dapodik_permit'],
                        $row['dapodik_alfa'],
                    ]);
                }
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Unduh atau Cetak Langsung Rekap Presensi Siswa Kelas dalam Format PDF Siap Cetak
     */
    public function downloadPdf(Request $request): Response
    {
        $data = $this->resolveRecapData($request);
        $classroom = $data['selectedClassroom'];

        if (! $classroom) {
            abort(404, 'Kelas tidak ditemukan atau Anda tidak memiliki akses ke kelas ini.');
        }
        $mode = $data['mode'];
        $currentDate = $data['currentDate'];

        $className = $classroom ? str_replace([' ', '/', '\\'], '_', $classroom->name) : 'Kelas';
        $filename = match ($mode) {
            'weekly' => "Rekap_Presensi_{$className}_Mingguan_{$data['startOfWeek']->format('Ymd')}.pdf",
            'monthly' => "Rekap_Presensi_{$className}_Bulanan_{$currentDate->format('Y_m')}.pdf",
            'semester' => "Rekap_Presensi_{$className}_Semester_{$data['activeSemester']}_{$data['academicYearClean']}.pdf",
            'yearly' => "Rekap_Presensi_{$className}_Tahunan_{$data['academicYearClean']}.pdf",
            'custom' => "Rekap_Presensi_{$className}_Periode_{$data['startDate']->format('Ymd')}_{$data['endDate']->format('Ymd')}.pdf",
            default => "Rekap_Presensi_{$className}_Harian_{$currentDate->format('Ymd')}.pdf",
        };

        // Layout kertas: Harian (Portrait), Mingguan (Landscape), Bulanan & Mode Rentang Lainnya (Portrait)
        $orientation = match ($mode) {
            'weekly' => 'landscape',
            default => 'portrait',
        };

        $pdf = Pdf::loadView('attendance.homeroom_recap_pdf', $data)
            ->setPaper('a4', $orientation)
            ->setOption('isRemoteEnabled', true);

        if ($request->query('action') === 'print' || $request->query('stream')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
