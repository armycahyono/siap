<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\StudentVisitation;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisciplineDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
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

        abort_unless(
            $user->canManageDiscipline($academicYear) || $user->hasRole(['super_admin', 'tim_kedisiplinan', 'koordinator_kedisiplinan']),
            403,
            'Anda tidak memiliki hak akses untuk Dashboard Kedisiplinan.'
        );

        $availableYears = AcademicYear::orderByDesc('start_date')->get();
        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $today = Carbon::today('Asia/Jakarta');
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

        // 1. Kartu Metrik Utama Kedisiplinan
        $baseIncidentQuery = PiketIncident::forAcademicYear($academicYear);

        $todayIncidentsCount = (clone $baseIncidentQuery)
            ->whereDate('incident_date', $today->toDateString())
            ->count();

        $unhandledCount = (clone $baseIncidentQuery)
            ->where(function ($q) {
                $q->where('status', 'tercatat')
                    ->orWhere('verification_status', 'menunggu_verifikasi');
            })
            ->count();

        $followUpCount = (clone $baseIncidentQuery)
            ->whereIn('follow_up_target', ['rujuk_bk', 'rujuk_kesiswaan', 'rujuk_wali_kelas'])
            ->whereIn('status', ['tercatat', 'proses'])
            ->count();

        $activeMonitoringCount = (clone $baseIncidentQuery)
            ->where('status', 'proses')
            ->count() + StudentVisitation::forAcademicYear($academicYear)
            ->whereIn('status', ['diajukan', 'dijadwalkan', 'terlaksana'])
            ->count();

        // 2. Data Grafik: Tren Pelanggaran (7 Hari Terakhir)
        $trendDates = [];
        $trendCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today('Asia/Jakarta')->subDays($i);
            $dString = $d->toDateString();
            $trendDates[] = $d->translatedFormat('d M');
            $trendCounts[] = (clone $baseIncidentQuery)
                ->whereDate('incident_date', $dString)
                ->count();
        }

        // 3. Data Grafik: Jenis Pelanggaran
        $categoryCounts = (clone $baseIncidentQuery)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $categoryLabels = [
            'kedisiplinan' => 'Kedisiplinan',
            'kerapian' => 'Kerapian',
            'kehadiran' => 'Kehadiran',
            'akademik' => 'Akademik / KBM',
            'berat' => 'Pelanggaran Berat',
            'lainnya' => 'Lainnya',
        ];

        $chartCategoryLabels = [];
        $chartCategoryData = [];
        foreach ($categoryLabels as $key => $lbl) {
            $count = $categoryCounts[$key] ?? 0;
            if ($count > 0 || empty($categoryCounts)) {
                $chartCategoryLabels[] = $lbl;
                $chartCategoryData[] = $count;
            }
        }

        // 4. Tugas dan Kasus yang Membutuhkan Tindakan
        $actionRequiredCases = (clone $baseIncidentQuery)
            ->with(['student.classroom', 'recordedBy'])
            ->where(function ($q) {
                $q->where('verification_status', 'menunggu_verifikasi')
                    ->orWhere('status', 'tercatat')
                    ->orWhereNotNull('confiscated_items');
            })
            ->latest('incident_date')
            ->latest('incident_time')
            ->take(6)
            ->get();

        // 5. Jadwal Piket Hari Ini dan Laporan Kejadian
        $todayPiketSchedules = PiketSchedule::where('academic_year', $academicYear)
            ->where(function ($q) use ($todayDay) {
                $q->where('day', $todayDay)
                    ->orWhere('day', strtolower($todayDay));
            })
            ->with('teacher')
            ->get();

        $todayJournal = PiketJournal::where('academic_year', $academicYear)
            ->whereDate('date', $today->toDateString())
            ->first();

        $recentPiketIncidents = (clone $baseIncidentQuery)
            ->with(['student.classroom', 'recordedBy'])
            ->latest('incident_date')
            ->latest('incident_time')
            ->take(5)
            ->get();

        // 6. Visitasi dan Pemantauan Siswa
        $recentVisitations = StudentVisitation::forAcademicYear($academicYear)
            ->with(['student.classroom', 'primaryOfficer'])
            ->latest('planned_date')
            ->take(5)
            ->get();

        return view('piket.discipline_dashboard', compact(
            'academicYear',
            'availableYears',
            'today',
            'todayDay',
            'todayIncidentsCount',
            'unhandledCount',
            'followUpCount',
            'activeMonitoringCount',
            'trendDates',
            'trendCounts',
            'chartCategoryLabels',
            'chartCategoryData',
            'categoryCounts',
            'actionRequiredCases',
            'todayPiketSchedules',
            'todayJournal',
            'recentPiketIncidents',
            'recentVisitations'
        ));
    }
}
