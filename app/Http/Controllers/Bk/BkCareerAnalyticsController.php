<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkStudentCareerProfile;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Student;
use App\Services\AcademicYearContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BkCareerAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear) && ! $user->isPrincipal() && ! $user->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk Analisis Bimbingan Karir & BKK.');
        }

        $availableYears = AcademicYear::orderByDesc('start_date')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $classrooms = Classroom::where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
        })->with('department')->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::where('is_active', true)->with('department')->orderBy('name')->get();
        }

        // 1. Agregasi Rencana Pasca-Kelulusan (Pola BMW)
        $profileQuery = BkStudentCareerProfile::where('academic_year', $academicYear)
            ->whereHas('student', fn ($s) => $s->where('status', 'aktif'));

        $totalProfiles = (clone $profileQuery)->count();
        $totalStudents = Student::where('status', 'aktif')->count();

        $planCounts = (clone $profileQuery)
            ->select('post_graduation_plan', DB::raw('count(*) as count'))
            ->groupBy('post_graduation_plan')
            ->pluck('count', 'post_graduation_plan')
            ->all();

        $bekerjaCount = $planCounts['bekerja'] ?? 0;
        $melanjutkanCount = $planCounts['melanjutkan'] ?? 0;
        $wirausahaCount = $planCounts['wirausaha'] ?? 0;
        $belumMemutuskanCount = $planCounts['belum_memutuskan'] ?? 0;

        // Persentase
        $bekerjaPercent = $totalProfiles > 0 ? round(($bekerjaCount / $totalProfiles) * 100, 1) : 0;
        $melanjutkanPercent = $totalProfiles > 0 ? round(($melanjutkanCount / $totalProfiles) * 100, 1) : 0;
        $wirausahaPercent = $totalProfiles > 0 ? round(($wirausahaCount / $totalProfiles) * 100, 1) : 0;
        $belumMemutuskanPercent = $totalProfiles > 0 ? round(($belumMemutuskanCount / $totalProfiles) * 100, 1) : 0;

        // 2. Top 5 Target Industri
        $topIndustries = (clone $profileQuery)
            ->whereNotNull('target_industry')
            ->where('target_industry', '!=', '')
            ->select('target_industry', DB::raw('count(*) as total'))
            ->groupBy('target_industry')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // 3. Top 5 Kampus / Perguruan Tinggi Target
        $topUniversities = (clone $profileQuery)
            ->whereNotNull('target_university')
            ->where('target_university', '!=', '')
            ->select('target_university', DB::raw('count(*) as total'))
            ->groupBy('target_university')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // 4. Filter & Pencarian Daftar Siswa Siap Salur BKK
        $planFilter = $request->query('plan');
        $departmentId = $request->query('department_id');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        $studentsQuery = Student::with(['classroom.department', 'careerProfiles' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->where('status', 'aktif')
            ->whereHas('careerProfiles', function ($q) use ($academicYear, $planFilter) {
                $q->where('academic_year', $academicYear);
                if ($planFilter) {
                    $q->where('post_graduation_plan', $planFilter);
                }
            });

        if ($classroomId) {
            $studentsQuery->where('classroom_id', $classroomId);
        }

        if ($departmentId) {
            $studentsQuery->whereHas('classroom', fn ($c) => $c->where('department_id', $departmentId));
        }

        if ($search) {
            $studentsQuery->where(function ($q) use ($search, $academicYear) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhereHas('careerProfiles', function ($cp) use ($search, $academicYear) {
                        $cp->where('academic_year', $academicYear)
                            ->where(function ($sub) use ($search) {
                                $sub->where('target_industry', 'like', "%{$search}%")
                                    ->orWhere('target_job_position', 'like', "%{$search}%")
                                    ->orWhere('target_university', 'like', "%{$search}%")
                                    ->orWhere('target_study_program', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $candidates = $studentsQuery->orderBy('name')->paginate(15)->withQueryString();

        return view('bk.career.analytics', compact(
            'academicYear',
            'availableYears',
            'departments',
            'classrooms',
            'totalProfiles',
            'totalStudents',
            'bekerjaCount',
            'melanjutkanCount',
            'wirausahaCount',
            'belumMemutuskanCount',
            'bekerjaPercent',
            'melanjutkanPercent',
            'wirausahaPercent',
            'belumMemutuskanPercent',
            'topIndustries',
            'topUniversities',
            'candidates',
            'planFilter',
            'departmentId',
            'classroomId',
            'search'
        ));
    }
}
