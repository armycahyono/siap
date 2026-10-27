<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\HomeroomAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        // Resolve the viewing academic year using the dedicated service.
        // The service applies the priority: explicit query param → session → master year.
        // It throws InvalidArgumentException for an unknown explicit year.
        $academicYearContext = app(AcademicYearContext::class);
        try {
            $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'academic_year' => $e->getMessage(),
            ]);
        }
        $gradeId = $request->query('grade_level_id');
        $deptId = $request->query('department_id');
        $search = $request->query('search');

        $query = Classroom::with(['gradeLevel', 'department', 'homeroomTeacher'])
            ->withCount(['subjectAssignments']);

        if ($academicYear) {
            $ayRecord = AcademicYear::where('name', $academicYear)->first();
            if ($ayRecord) {
                $query->where(function ($q) use ($academicYear, $ayRecord) {
                    $q->where('academic_year_id', $ayRecord->id)
                        ->orWhere(function ($sub) use ($academicYear) {
                            $sub->whereNull('academic_year_id')
                                ->where('academic_year', $academicYear);
                        });
                });
            } else {
                $query->where('academic_year', $academicYear);
            }
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

        $academicYearContext = app(AcademicYearContext::class);
        $operationalYear = null;
        try {
            $operationalYear = $academicYearContext->getOperationalYear()->name;
        } catch (\Throwable) {
            $operationalYear = SchoolSetting::getMasterYear();
        }

        $data['academic_year'] = ! empty($data['academic_year'])
            ? $data['academic_year']
            : $operationalYear;
        $data['is_active'] = $request->boolean('is_active', true);

        // Cari atau hubungkan academic_year_id
        $ayRecord = AcademicYear::where('name', $data['academic_year'])->first();
        $data['academic_year_id'] = $ayRecord?->id;

        DB::transaction(function () use ($data) {
            $created = Classroom::create($data);
            if (! empty($data['homeroom_teacher_id'])) {
                app(HomeroomAssignmentService::class)->syncHomeroomRole($created, (int) $data['homeroom_teacher_id']);
            }
        });

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

        DB::transaction(function () use ($classroom, $data) {
            $classroom->update($data);
            $newTeacherId = ! empty($data['homeroom_teacher_id']) ? (int) $data['homeroom_teacher_id'] : null;
            app(HomeroomAssignmentService::class)->syncHomeroomRole($classroom, $newTeacherId);
        });

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

        DB::transaction(function () use ($classroom) {
            app(HomeroomAssignmentService::class)->cleanupClassroomRoles($classroom);
            $classroom->delete();
        });

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
