<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\LessonSchedule;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceSession;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PiketDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $academicYearContext = app(AcademicYearContext::class);
        if ($request->filled('academic_year')) {
            try {
                $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));
            } catch (\InvalidArgumentException $e) {
                $academicYear = $academicYearContext->getOperationalYear()->name;
            }
        } else {
            $academicYear = $academicYearContext->getOperationalYear()->name;
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();
        $setting = SchoolSetting::getSetting();
        $semester = $request->query('semester', $ayModel?->semester ?? $setting->semester ?? 'Ganjil');

        $now = Carbon::now('Asia/Jakarta');
        $today = Carbon::today();
        $currentTime = $now->format('H:i');

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

        // 1. Petugas Piket Hari Ini
        $todaySchedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->with('teacher')
            ->orderBy('role', 'desc')
            ->get();

        // 2. Jurnal Hari Ini
        $todayJournal = PiketJournal::where('date', $today->toDateString())
            ->with(['attendances.teacher', 'coordinator'])
            ->first();

        // 3. Statistik Hari Ini
        $todayLateCount = PiketStudentPermit::whereDate('permit_date', $today)
            ->where('permit_type', 'terlambat')
            ->count();

        $todayPermitLeaveCount = PiketStudentPermit::whereDate('permit_date', $today)
            ->whereIn('permit_type', ['izin_keluar_sementara', 'izin_pulang_cepat'])
            ->count();

        $todayInvalCount = PiketInvalClass::whereHas('journal', function ($q) use ($today) {
            $q->whereDate('date', $today);
        })->count();

        $todayGuestCount = PiketGuest::whereDate('visit_date', $today)->count();
        $todayIncidentCount = PiketIncident::whereDate('incident_date', $today)->count();

        // 4. Riwayat Izin Terbaru Hari Ini
        $recentPermits = PiketStudentPermit::whereDate('permit_date', $today)
            ->with(['classroom', 'givenBy'])
            ->latest()
            ->take(10)
            ->get();

        // 5. Riwayat Tamu Hari Ini
        $recentGuests = PiketGuest::whereDate('visit_date', $today)
            ->with('recordedBy')
            ->latest()
            ->take(5)
            ->get();

        // 6. Inval Kelas Hari Ini
        $todayInvals = PiketInvalClass::whereHas('journal', function ($q) use ($today) {
            $q->whereDate('date', $today);
        })->with(['classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->get();

        // =========================================================================
        // 7. JADWAL PELAJARAN & GURU MAPEL DI MASING-MASING KELAS JAM SEKARANG
        // =========================================================================
        $periodTimes = LessonSchedule::periodTimes($todayDay);
        $maxPeriodDay = count($periodTimes); // 7 di hari Jumat, 10 di hari Senin-Kamis
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

        // Selected period: user can manually click or default to detected period
        $selectedPeriod = $request->query('period') ? (int) $request->query('period') : ($detectedPeriod ?: 1);
        if ($selectedPeriod < 1 || $selectedPeriod > $maxPeriodDay) {
            $selectedPeriod = $detectedPeriod ?: 1;
        }

        $activePeriodTimes = $periodTimes[$selectedPeriod] ?? ['start' => '07:00', 'end' => '07:45'];

        // Jadwal mulai ditampilkan pada hari yang sama sejak pukul 00.01 hingga 24.00
        $selectedDay = $todayDay;

        // Ambil semua kelas aktif
        $allClassrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })
            ->where('is_active', true)
            ->with(['homeroomTeacher', 'gradeLevel'])
            ->orderBy('name')
            ->get();

        if ($allClassrooms->isEmpty()) {
            $allClassrooms = Classroom::with(['homeroomTeacher', 'gradeLevel'])->orderBy('name')->get();
        }

        // Ambil jadwal pelajaran untuk jam sekarang & jam berikutnya
        $nextPeriod = min(10, $selectedPeriod + 1);

        $activeLessonSchedules = LessonSchedule::where('academic_year', $academicYear)
            ->where('day', $selectedDay)
            ->where('start_period', '<=', $selectedPeriod)
            ->where('end_period', '>=', $selectedPeriod)
            ->with(['teacher', 'subject', 'classroom'])
            ->get()
            ->keyBy('classroom_id');

        $nextLessonSchedules = LessonSchedule::where('academic_year', $academicYear)
            ->where('day', $selectedDay)
            ->where('start_period', '<=', $nextPeriod)
            ->where('end_period', '>=', $nextPeriod)
            ->with(['teacher', 'subject', 'classroom'])
            ->get()
            ->keyBy('classroom_id');

        // Ambil data inval yang relevan untuk jam ke-X ini
        $activeInvalsByClass = $todayInvals->filter(function ($inv) use ($selectedPeriod) {
            return $inv->period_start <= $selectedPeriod && $inv->period_end >= $selectedPeriod;
        })->keyBy('classroom_id');

        // Ambil event kalender kurikulum hari ini untuk mengecek dampak per kelas
        $todayEvents = CurriculumEvent::where(function ($q) use ($today) {
            $dateStr = $today->toDateString();
            $q->whereDate('start_date', '<=', $dateStr)
                ->where(function ($sub) use ($dateStr) {
                    $sub->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $dateStr);
                });
        })->get();

        // Ambil data kehadiran dan izin siswa hari ini per kelas
        $todayStudentPermits = PiketStudentPermit::whereDate('permit_date', $today)
            ->where('status', '!=', 'ditolak')
            ->get();

        $todaySessions = SubjectAttendanceSession::with(['records', 'teacher'])
            ->whereDate('attendance_date', $today)
            ->get();

        // Log scan kehadiran guru piket hari ini beserta jam masuk pertama
        $teacherLogsToday = AttendanceLog::where('user_type', 'teacher')
            ->whereDate('scanned_at', $today)
            ->orderBy('scanned_at', 'asc')
            ->get();

        $teacherScanUserIds = $teacherLogsToday->pluck('user_id')->unique();
        $teacherCheckInTimes = $teacherLogsToday->groupBy('user_id')->map(function ($logs) {
            return $logs->first()->scanned_at?->format('H:i');
        });

        // Rekapitulasi per kelas
        $classroomSchedules = [];
        $countTeaching = 0;
        $countInvalNeeded = 0;
        $countInvalHandled = 0;
        $countFree = 0;
        $countEvent = 0;

        foreach ($allClassrooms as $classroom) {
            $sched = $activeLessonSchedules->get($classroom->id);
            $nextSched = $nextLessonSchedules->get($classroom->id);
            $inval = $activeInvalsByClass->get($classroom->id);

            // Cek dampak event pada kelas ini (prioritaskan event yang membebaskan KBM atau memiliki dampak KBM khusus)
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
            if ($isEventImpacted) {
                // Kelas sedang PKL / Kegiatan Khusus (Bebas KBM Reguler)
                $status = 'event';
                $countEvent++;
            } elseif ($inval) {
                if ($inval->inval_teacher_id) {
                    $status = 'inval_handled';
                    $countInvalHandled++;
                } else {
                    $status = 'inval_needed';
                    $countInvalNeeded++;
                }
            } elseif ($sched) {
                $status = 'teaching';
                $countTeaching++;
            } else {
                $status = 'free';
                $countFree++;
            }

            // Hitung statistik absensi siswa di kelas ini hari ini
            $classSessions = $todaySessions->where('classroom_id', $classroom->id);
            $classRecords = $classSessions->flatMap->records;

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

            // Deteksi bolos: hadir di salah satu sesi mapel tapi alfa di sesi mapel lain
            $presentStudents = $classRecords->where('status', 'h')->pluck('student_id')->unique();
            $alfaStudents = $classRecords->where('status', 'a')->pluck('student_id')->unique();
            $countBolos = $alfaStudents->intersect($presentStudents)->count();
            $countAlfa = $alfaStudents->diff($presentStudents)->count();

            // Total siswa hadir aktif
            $countHadir = $classRecords->where('status', 'h')->pluck('student_id')->unique()->count();
            if ($countHadir === 0 && ! empty($classroom->total_students)) {
                // Estimasi default jika belum diabsen
                $unattended = $countSakit + $countIzin + $countAlfa;
                $countHadir = max(0, $classroom->total_students - $unattended);
            }

            // Evaluasi apakah sesi KBM jam aktif ini sudah diabsen
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

            $classroomSchedules[] = [
                'classroom' => $classroom,
                'schedule' => $sched,
                'next_schedule' => $nextSched,
                'inval' => $inval,
                'status' => $status,
                'event' => $classEvent,
                'teacher' => $sched?->teacher,
                'subject' => $sched?->subject,
                'inval_teacher' => $inval?->invalTeacher,
                'absent_teacher' => $inval?->absentTeacher,
                'is_attended' => $isAttended,
                'attended_session' => $activeSession,
                'stats' => [
                    'hadir' => $countHadir,
                    'bolos' => $countBolos,
                    'dispen' => $countDispen,
                    'telat' => $countTelat,
                    'pulang' => $countPulang,
                    'sakit' => $countSakit,
                    'izin' => $countIzin,
                    'alfa' => $countAlfa,
                ],
            ];
        }

        $data = compact(
            'academicYear',
            'semester',
            'today',
            'todayDay',
            'currentTime',
            'todaySchedules',
            'todayJournal',
            'todayLateCount',
            'todayPermitLeaveCount',
            'todayInvalCount',
            'todayGuestCount',
            'todayIncidentCount',
            'recentPermits',
            'recentGuests',
            'todayInvals',
            'periodTimes',
            'detectedPeriod',
            'detectedPeriodLabel',
            'selectedPeriod',
            'activePeriodTimes',
            'selectedDay',
            'isBreak',
            'isOutsideKbm',
            'isKbmFinished',
            'kbmEndTime',
            'isWeekend',
            'breakMessage',
            'classroomSchedules',
            'countTeaching',
            'countInvalNeeded',
            'countInvalHandled',
            'countFree',
            'countEvent',
            'nextPeriod',
            'teacherScanUserIds',
            'teacherCheckInTimes'
        );

        if (DeviceDetector::isMobile()) {
            return view('mobile.piket.dashboard', $data);
        }

        return view('web.piket.dashboard', $data);
    }
}
