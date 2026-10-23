<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupervisionDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        $academicYear = $request->get('academic_year', $setting->academic_year);
        $semester = $request->get('semester', $setting->semester);

        $teachersCount = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))
            ->where('is_active', true)
            ->count();

        $allSupervisions = Supervision::with(['teacher', 'supervisor', 'principal'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->get();

        $totalSchedules = $allSupervisions->count();
        $completedSupervisions = $allSupervisions->whereIn('status', ['disetujui', 'selesai'])->count();
        $pendingApprovalCount = $allSupervisions->where('status', 'menunggu_persetujuan')->count();
        $inProgressCount = $allSupervisions->whereIn('status', ['praobservasi', 'observasi', 'pascaobservasi'])->count();
        $scheduledCount = $allSupervisions->where('status', 'terjadwal')->count();

        $unsupervisedTeachersCount = max(0, $teachersCount - $completedSupervisions);
        $completionRate = $teachersCount > 0 ? round(($completedSupervisions / $teachersCount) * 100, 1) : 0;

        $averageScore = $completedSupervisions > 0
            ? round($allSupervisions->whereIn('status', ['disetujui', 'selesai'])->avg('percentage'), 1)
            : 0;

        $upcomingSchedules = Supervision::with(['teacher', 'supervisor'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date', 'asc')
            ->take(8)
            ->get();

        $recentSupervisions = Supervision::with(['teacher', 'supervisor'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get();

        $predicateCounts = [
            'Sangat Baik' => $allSupervisions->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $allSupervisions->where('predicate', 'Baik')->count(),
            'Cukup' => $allSupervisions->where('predicate', 'Cukup')->count(),
            'Perlu Bimbingan' => $allSupervisions->where('predicate', 'Perlu Bimbingan')->count(),
        ];

        return view('supervisions.dashboard', compact(
            'user',
            'setting',
            'academicYear',
            'semester',
            'teachersCount',
            'totalSchedules',
            'completedSupervisions',
            'pendingApprovalCount',
            'inProgressCount',
            'scheduledCount',
            'unsupervisedTeachersCount',
            'completionRate',
            'averageScore',
            'upcomingSchedules',
            'recentSupervisions',
            'predicateCounts'
        ));
    }
}
