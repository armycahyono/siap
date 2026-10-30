<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkGroup;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\AcademicYearContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BkGroupController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki kewenangan untuk mengakses Kelompok Binaan BK.');
        }

        $category = $request->query('category');
        $search = $request->query('search');

        $query = BkGroup::with(['counselor', 'students.classroom'])
            ->withCount('students')
            ->forAcademicYear($academicYear);

        if (! $user->isSuperAdmin() && ! $user->isPrincipal()) {
            $query->where('counselor_id', $user->id);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('target_goal', 'like', "%{$search}%");
            });
        }

        $groups = $query->latest('id')->paginate(12)->withQueryString();

        $availableYears = AcademicYear::orderByDesc('start_date')->get();

        return view('bk.groups.index', compact('groups', 'academicYear', 'availableYears', 'category', 'search'));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki kewenangan sebagai Guru BK.');
        }

        $classrooms = Classroom::where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
        })->with('department')->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::where('is_active', true)->with('department')->orderBy('name')->get();
        }

        // Siswa aktif beserta profil karirnya
        $students = Student::with(['classroom', 'careerProfiles' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        return view('bk.groups.create', compact('classrooms', 'students', 'academicYear'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:karir,akademik,sosial_pribadi,kedisiplinan'],
            'academic_year' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'target_goal' => ['nullable', 'string', 'max:255'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['exists:students,id'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki wewenang pada tahun ajaran ini.');
        }

        DB::transaction(function () use ($validated, $user) {
            $group = BkGroup::create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'academic_year' => $validated['academic_year'],
                'counselor_id' => $user->id,
                'description' => $validated['description'] ?? null,
                'target_goal' => $validated['target_goal'] ?? null,
                'is_active' => true,
            ]);

            if (! empty($validated['student_ids'])) {
                $attachData = [];
                $today = now()->toDateString();
                foreach ($validated['student_ids'] as $stId) {
                    $attachData[$stId] = ['joined_at' => $today];
                }
                $group->students()->attach($attachData);
            }
        });

        return redirect()
            ->route('bk.groups.index', ['academic_year' => $validated['academic_year']])
            ->with('success', "Kelompok binaan '{$validated['name']}' berhasil dibuat.");
    }

    public function show(BkGroup $group, Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->canOperateBk($group->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka kelompok ini.');
        }

        $group->load([
            'counselor',
            'students.classroom.department',
            'students.careerProfiles' => fn ($q) => $q->where('academic_year', $group->academic_year),
            'services.counselor',
        ]);

        $existingStudentIds = $group->students->pluck('id')->all();

        // Siswa kandidat yang belum masuk kelompok
        $availableStudents = Student::with('classroom')
            ->where('status', 'aktif')
            ->whereNotIn('id', $existingStudentIds)
            ->orderBy('name')
            ->get();

        return view('bk.groups.show', compact('group', 'availableStudents'));
    }

    public function addStudents(BkGroup $group, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->canOperateBk($group->academic_year)) {
            abort(403);
        }

        $validated = $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['exists:students,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $attachData = [];
        $today = now()->toDateString();
        foreach ($validated['student_ids'] as $stId) {
            $attachData[$stId] = [
                'joined_at' => $today,
                'notes' => $validated['notes'] ?? null,
            ];
        }

        $group->students()->syncWithoutDetaching($attachData);

        return back()->with('success', count($validated['student_ids']).' siswa berhasil ditambahkan ke kelompok.');
    }

    public function removeStudent(BkGroup $group, Student $student): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->canOperateBk($group->academic_year)) {
            abort(403);
        }

        $group->students()->detach($student->id);

        return back()->with('success', "Siswa {$student->name} berhasil dikeluarkan dari kelompok.");
    }

    public function destroy(BkGroup $group): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->canOperateBk($group->academic_year)) {
            abort(403);
        }

        $name = $group->name;
        $ay = $group->academic_year;
        $group->delete();

        return redirect()->route('bk.groups.index', ['academic_year' => $ay])
            ->with('success', "Kelompok binaan '{$name}' berhasil dihapus.");
    }
}
