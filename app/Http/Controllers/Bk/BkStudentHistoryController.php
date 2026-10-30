<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkService;
use App\Models\Classroom;
use App\Models\PiketIncident;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Services\AcademicYearContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BkStudentHistoryController extends Controller
{
    /**
     * Tampilkan direktori/pencarian siswa untuk membuka Buku Pribadi Siswa Digital.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka Buku Pribadi Siswa.');
        }

        $search = $request->query('search');
        $classroomId = $request->query('classroom_id');

        $query = Student::with(['classroom.department'])
            ->withCount(['bkServices' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->with(['careerProfiles' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->where('status', 'aktif');

        if ($classroomId) {
            $query->where(function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)
                    ->orWhereHas('studentClassrooms', fn ($sc) => $sc->where('classroom_id', $classroomId));
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('name')->paginate(20)->withQueryString();

        $classrooms = Classroom::where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
        })->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        }

        $availableYears = AcademicYear::orderByDesc('name')->pluck('name')->all();
        if (empty($availableYears)) {
            $availableYears = [$academicYear];
        }

        return view('bk.students.index', compact(
            'students',
            'classrooms',
            'classroomId',
            'search',
            'academicYear',
            'availableYears'
        ));
    }

    /**
     * Tampilkan Buku Pribadi Siswa Digital (Profil perkembangan, riwayat bimbingan & konseling, akademik, dan kedisiplinan).
     * Hanya dapat diakses oleh Guru BK yang sah pada tahun ajaran terkait atau Super Admin.
     */
    public function show(Student $student, Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka riwayat bimbingan konseling siswa.');
        }

        $canAccessConfidential = $user->canAccessCounselingNotes($academicYear);

        // 1. Data Siswa, Kelas Aktif, Riwayat Rombel Lintas Tahun Ajaran, dan Mutasi
        $student->load([
            'classroom.department',
            'studentClassrooms' => fn ($q) => $q->with(['classroom.department', 'academicYear'])->latest('id'),
            'mutations' => fn ($q) => $q->with('recordedBy')->latest('mutation_date'),
        ]);

        $currentClassroom = $student->classroomForAcademicYear($academicYear) ?? $student->classroom;

        // 2. Sesi Layanan Bimbingan & Konseling
        $servicesQuery = BkService::with(['counselor', 'incident', 'group', 'classroom'])
            ->where(function ($q) use ($student) {
                $q->where('student_id', $student->id)
                    ->orWhereHas('participants', fn ($pq) => $pq->where('student_id', $student->id));
            })
            ->when($request->filled('academic_year'), fn ($q) => $q->where('academic_year', $academicYear))
            ->latest('service_date');

        if ($canAccessConfidential) {
            $servicesQuery->with('confidentialNote');
        }

        $services = $servicesQuery->get();

        // 3. Catatan Kejadian / Pelanggaran Meja Piket & Kedisiplinan Siswa
        $allIncidents = PiketIncident::with(['recordedBy', 'verifier', 'bkReferral.handler', 'bkServices', 'classroom'])
            ->where('student_id', $student->id)
            ->latest('incident_date')
            ->get();

        $referredIncidents = $allIncidents->where('follow_up_target', 'rujuk_bk');

        // 4. Perizinan & Kedisiplinan Meja Piket Siswa
        $permits = $student->permits()
            ->with(['classroom', 'journal'])
            ->latest('permit_date')
            ->get();

        // 5. Profil Minat, Bakat & Rencana Karier Siswa (Tab 3)
        $careerProfile = $student->careerProfiles()
            ->with(['recorder', 'updater'])
            ->where('academic_year', $academicYear)
            ->first();

        // 6. Riwayat Asesmen Minat dan Bakat Siswa (Tab 3)
        $assessments = $student->bkAssessments()
            ->with('recorder')
            ->where('academic_year', $academicYear)
            ->latest('assessment_date')
            ->get();

        // 7. Tab 4: Prestasi, Penghargaan & Organisasi Siswa
        $achievements = $student->achievements()
            ->with('recorder')
            ->where('academic_year', $academicYear)
            ->latest('event_date')
            ->get();

        // 8. Tab 5: Catatan Perkembangan Siswa Non-Kasus
        $developmentNotes = $student->developmentNotes()
            ->with('recorder')
            ->where('academic_year', $academicYear)
            ->latest('note_date')
            ->get();

        // 9. Tab 5 / Lintas Modul: Visitasi & Pemantauan Siswa (Home Visit & Monitoring)
        $visitations = $student->visitations()
            ->with(['requester', 'primaryOfficer', 'secondaryOfficer'])
            ->where('academic_year', $academicYear)
            ->latest('planned_date')
            ->get();

        // 10. Tab 8: Dokumen Pendukung & Arsip Digital BK
        $documents = $student->bkDocuments()
            ->with('recorder')
            ->where('academic_year', $academicYear)
            ->latest('created_at')
            ->get();

        $availableYears = AcademicYear::orderByDesc('name')->pluck('name')->all();
        if (empty($availableYears)) {
            $availableYears = [$academicYear];
        }

        return view('bk.students.history', compact(
            'student',
            'currentClassroom',
            'services',
            'allIncidents',
            'referredIncidents',
            'permits',
            'careerProfile',
            'assessments',
            'achievements',
            'developmentNotes',
            'visitations',
            'documents',
            'academicYear',
            'availableYears',
            'canAccessConfidential'
        ));
    }

    /**
     * Ekspor Ringkasan Buku Pribadi Siswa Digital ke format PDF resmi.
     */
    public function exportPdf(Request $request, Student $student)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear) && ! $user->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak Buku Pribadi Siswa.');
        }

        $currentClassroom = $student->classroomForAcademicYear($academicYear) ?? $student->classroom;

        $careerProfile = $student->careerProfileForAcademicYear($academicYear) ?? $student->latestCareerProfile;
        $assessments = $student->bkAssessments()->where('academic_year', $academicYear)->latest('assessment_date')->get();
        if ($assessments->isEmpty()) {
            $assessments = $student->bkAssessments()->latest('assessment_date')->take(5)->get();
        }

        $achievements = $student->achievements()->where('academic_year', $academicYear)->latest('achievement_date')->get();
        if ($achievements->isEmpty()) {
            $achievements = $student->achievements()->latest('achievement_date')->take(5)->get();
        }

        $developmentNotes = $student->bkDevelopmentNotes()->where('academic_year', $academicYear)->latest('note_date')->get();

        $services = $student->bkServices()
            ->with(['counselor'])
            ->where('academic_year', $academicYear)
            ->latest('service_date')
            ->get();

        $visitations = StudentVisitation::with(['primaryOfficer', 'secondaryOfficer'])
            ->where('student_id', $student->id)
            ->where('academic_year', $academicYear)
            ->latest('actual_date')
            ->get();

        $incidents = $student->piketIncidents()
            ->forAcademicYear($academicYear)
            ->latest('incident_date')
            ->get();

        $setting = SchoolSetting::getSetting();

        $pdf = Pdf::loadView('bk.students.history_pdf', compact(
            'student',
            'currentClassroom',
            'academicYear',
            'careerProfile',
            'assessments',
            'achievements',
            'developmentNotes',
            'services',
            'visitations',
            'incidents',
            'setting'
        ))->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = 'Buku_Pribadi_BK_'.Str::slug($student->name).'_'.Str::slug($academicYear).'.pdf';

        return $pdf->download($filename);
    }
}
