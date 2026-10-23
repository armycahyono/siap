<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));
        $gradeId = $request->query('grade_level_id');
        $deptId = $request->query('department_id');
        $search = $request->query('search');

        $query = Classroom::with(['gradeLevel', 'department', 'homeroomTeacher'])
            ->withCount(['subjectAssignments']);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        if ($gradeId) {
            $query->where('grade_level_id', $gradeId);
        }

        if ($deptId) {
            $query->where('department_id', $deptId);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $classrooms = $query->orderBy('name')->paginate(15)->withQueryString();

        $gradeLevels = GradeLevel::orderBy('order_index')->get();
        $departments = Department::where('is_active', true)->orderBy('code')->get();
        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'operator_kurikulum']))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('master.classrooms.index', compact(
            'classrooms',
            'gradeLevels',
            'departments',
            'teachers',
            'academicYear',
            'gradeId',
            'deptId',
            'search'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $setting = SchoolSetting::getSetting();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['academic_year'] = $data['academic_year'] ?: session('active_academic_year', $setting->academic_year ?? '2026/2027');
        $data['is_active'] = $request->boolean('is_active', true);

        // Cari atau hubungkan academic_year_id
        $ayRecord = AcademicYear::where('name', $data['academic_year'])->first();
        $data['academic_year_id'] = $ayRecord?->id;

        Classroom::create($data);

        return redirect()->route('classrooms.index', ['academic_year' => $data['academic_year']])
            ->with('success', "Rombel / Kelas {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
            'academic_year' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Cari atau hubungkan academic_year_id
        $ayRecord = AcademicYear::where('name', $data['academic_year'])->first();
        $data['academic_year_id'] = $ayRecord?->id;

        $classroom->update($data);

        return redirect()->route('classrooms.index', ['academic_year' => $data['academic_year']])
            ->with('success', "Rombel / Kelas {$classroom->name} berhasil diperbarui.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $academicYear = $classroom->academic_year;

        if ($classroom->subjectAssignments()->exists()) {
            return redirect()->route('classrooms.index', ['academic_year' => $academicYear])
                ->with('error', "Kelas {$classroom->name} tidak dapat dihapus karena memiliki penugasan mata pelajaran guru.");
        }

        $classroom->delete();

        return redirect()->route('classrooms.index', ['academic_year' => $academicYear])
            ->with('success', "Rombel / Kelas {$classroom->name} berhasil dihapus.");
    }

    // Kelola Tingkat
    public function storeGradeLevel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', 'max:20', 'unique:grade_levels,level'],
            'name' => ['required', 'string', 'max:100'],
            'order_index' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['order_index'] = $data['order_index'] ?? 1;
        $data['is_active'] = true;

        GradeLevel::create($data);

        return redirect()->route('classrooms.index')
            ->with('success', "Tingkat {$data['name']} ({$data['level']}) berhasil ditambahkan.");
    }
}
