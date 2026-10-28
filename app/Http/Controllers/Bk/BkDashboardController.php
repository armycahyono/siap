<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\StudentVisitation;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BkDashboardController extends Controller
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
            $user->canOperateBk($academicYear) || $user->hasRole(['super_admin', 'guru_bk', 'koordinator_bk']),
            403,
            'Anda tidak memiliki hak akses untuk Dashboard Bimbingan & Konseling.'
        );

        $availableYears = AcademicYear::orderByDesc('start_date')->get();
        $today = Carbon::today('Asia/Jakarta');

        // 1. Kartu Metrik Utama BK
        $baseServiceQuery = BkService::forAcademicYear($academicYear);
        $baseReferralQuery = BkReferral::forAcademicYear($academicYear);

        $activeCasesCount = (clone $baseServiceQuery)
            ->where('follow_up_status', 'sedang_berjalan')
            ->count() + (clone $baseReferralQuery)
            ->where('status', 'diproses')
            ->count();

        $waitingFollowUpCount = (clone $baseReferralQuery)
            ->where('status', 'menunggu')
            ->count() + (clone $baseServiceQuery)
            ->where('follow_up_status', 'belum_dilaksanakan')
            ->count();

        $todaySchedulesCount = (clone $baseServiceQuery)
            ->whereDate('service_date', $today->toDateString())
            ->count();

        $needsEvaluationCount = (clone $baseServiceQuery)
            ->whereNotNull('follow_up_target_date')
            ->whereDate('follow_up_target_date', '<=', $today->toDateString())
            ->where('follow_up_status', '!=', 'selesai')
            ->count();

        // 2. Agenda Layanan BK (Hari ini & yang Akan Datang)
        $upcomingServices = (clone $baseServiceQuery)
            ->with(['student.classroom', 'counselor'])
            ->whereDate('service_date', '>=', $today->toDateString())
            ->orderBy('service_date')
            ->take(5)
            ->get();

        if ($upcomingServices->isEmpty()) {
            $upcomingServices = (clone $baseServiceQuery)
                ->with(['student.classroom', 'counselor'])
                ->latest('service_date')
                ->take(5)
                ->get();
        }

        // 3. Kasus dan Pembinaan yang Memerlukan Tindak Lanjut (Rujukan dari Piket)
        $pendingReferrals = (clone $baseReferralQuery)
            ->with(['incident.classroom', 'incident.student', 'handler'])
            ->whereIn('status', ['menunggu', 'diproses'])
            ->latest()
            ->take(6)
            ->get();

        // 4. Ringkasan Pembinaan (Distribusi Bidang Bimbingan)
        $serviceTypeCounts = (clone $baseServiceQuery)
            ->selectRaw('service_type, count(*) as count')
            ->groupBy('service_type')
            ->pluck('count', 'service_type')
            ->toArray();

        $serviceTypes = [
            'pribadi' => ['label' => 'Bimbingan Pribadi', 'icon' => 'ti ti-user', 'color' => 'primary'],
            'sosial' => ['label' => 'Bimbingan Sosial', 'icon' => 'ti ti-users', 'color' => 'azure'],
            'belajar' => ['label' => 'Bimbingan Belajar', 'icon' => 'ti ti-book', 'color' => 'success'],
            'karier' => ['label' => 'Bimbingan Karier', 'icon' => 'ti ti-briefcase', 'color' => 'teal'],
        ];

        $totalServices = array_sum($serviceTypeCounts);

        // 5. Visitasi & Pemantauan Terkini (Home Visit & Pemantauan Kasus)
        $recentVisitations = StudentVisitation::forAcademicYear($academicYear)
            ->with(['student.classroom', 'primaryOfficer'])
            ->latest('planned_date')
            ->take(5)
            ->get();

        // 6. Riwayat Aktivitas Layanan BK Sesuai Hak Akses (Tanpa Membocorkan Catatan Rahasia)
        $recentActivities = (clone $baseServiceQuery)
            ->with(['student.classroom', 'counselor'])
            ->latest('service_date')
            ->take(6)
            ->get();

        return view('bk.dashboard', compact(
            'academicYear',
            'availableYears',
            'today',
            'activeCasesCount',
            'waitingFollowUpCount',
            'todaySchedulesCount',
            'needsEvaluationCount',
            'upcomingServices',
            'pendingReferrals',
            'serviceTypes',
            'serviceTypeCounts',
            'totalServices',
            'recentVisitations',
            'recentActivities'
        ));
    }
}
