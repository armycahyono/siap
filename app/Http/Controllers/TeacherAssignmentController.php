<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TeacherSupervisorAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AcademicYearContext;
use App\Services\HomeroomAssignmentService;
use App\Services\HomeroomImportService;
use App\Services\StructuralImportService;
use App\Services\SupervisorImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $setting = SchoolSetting::getSetting();
        $tab = $request->query('tab', 'mapel'); // 'mapel', 'walikelas', 'supervisor'
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        if ($tab === 'piket') {
            return redirect()->route('piket.schedules.index', ['academic_year' => $academicYear]);
        }
        $teacherId = $request->query('teacher_id');
        $subjectId = $request->query('subject_id');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        // Data Guru (Role guru, supervisor, kurikulum & operator kurikulum, aktif)
        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'operator_kurikulum']))
            ->where('is_active', true)
            ->with(['teachingSubjects', 'homeroomClassrooms'])
            ->orderBy('name')
            ->get();

        // Data Mapel, Kelas, Jurusan
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $classrooms = Classroom::where('academic_year', $academicYear)
            ->with(['gradeLevel', 'department', 'homeroomTeacher'])
            ->orderBy('name')
            ->get();
        $departments = Department::where('is_active', true)->orderBy('code')->get();

        // 1. Data Penugasan Mapel
        $assignmentQuery = TeacherSubjectAssignment::with(['teacher', 'subject', 'classroom.department', 'classroom.gradeLevel'])
            ->where('academic_year', $academicYear);

        if ($teacherId) {
            $assignmentQuery->where('teacher_id', $teacherId);
        }

        if ($subjectId) {
            $assignmentQuery->where('subject_id', $subjectId);
        }

        if ($classroomId) {
            $assignmentQuery->where('classroom_id', $classroomId);
        }

        if ($search) {
            $assignmentQuery->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('classroom', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $assignments = $assignmentQuery->orderBy('teacher_id')->paginate(15)->withQueryString();

        // Rekap per guru yang mengajar mapel: guru_id => list of subjects & classes
        $teacherRecap = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'operator_kurikulum']))
            ->where('is_active', true)
            ->with([
                'subjectAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with(['subject', 'classroom']),
                'homeroomClassrooms' => fn ($q) => $q->where('academic_year', $academicYear),
            ])
            ->get()
            ->filter(fn ($u) => $u->subjectAssignments->isNotEmpty())
            ->sortBy('name')
            ->values();

        // 3. Data Tim Supervisor Sekolah (Berdasarkan Tahun Pelajaran)
        $supervisors = User::whereHas('supervisorAssignments', function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)->where('is_active', true);
        })
            ->where('is_active', true)
            ->withCount(['supervisionsAsSupervisor' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->with([
                'role',
                'supervisorAssignments' => fn ($q) => $q->where('academic_year', $academicYear),
            ])
            ->orderByRaw("CASE WHEN role_id = (SELECT id FROM roles WHERE slug = 'waka_kurikulum' LIMIT 1) THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $candidateTeachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->whereDoesntHave('supervisorAssignments', function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->where('is_active', true);
            })
            ->with(['subjectAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with('subject')])
            ->orderBy('name')
            ->get();

        $positionId = $request->query('position_id');
        $category = $request->query('category');

        // 4. Data Penugasan Struktural
        $structuralPositions = StructuralPosition::with([
            'activeAssignments' => function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->with('teacher');
            },
        ])->orderBy('order')->orderBy('id')->get();
        $structuralCategories = StructuralPosition::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $structuralPositionQuery = StructuralPosition::with([
            'activeAssignments' => function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->with('teacher');
            },
        ]);

        if ($positionId) {
            $structuralPositionQuery->where('id', $positionId);
        }

        if ($category) {
            $structuralPositionQuery->where('category', $category);
        }

        if ($teacherId) {
            $structuralPositionQuery->whereHas('activeAssignments', function ($q) use ($teacherId, $academicYear) {
                $q->where('teacher_id', $teacherId)->where('academic_year', $academicYear);
            });
        }

        if ($search && $tab === 'struktural') {
            $structuralPositionQuery->where(function ($q) use ($search, $academicYear) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('activeAssignments', function ($aq) use ($search, $academicYear) {
                        $aq->where('academic_year', $academicYear)
                            ->where(function ($sq) use ($search) {
                                $sq->where('sk_number', 'like', "%{$search}%")
                                    ->orWhereHas('teacher', function ($t) use ($search) {
                                        $t->where('name', 'like', "%{$search}%")
                                            ->orWhere('nigs', 'like', "%{$search}%")
                                            ->orWhere('nip', 'like', "%{$search}%");
                                    });
                            });
                    });
            });
        }

        $structuralAssignments = $structuralPositionQuery->orderBy('order')->orderBy('id')->paginate(15)->withQueryString();
        $allTeachers = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();

        return view('master.assignments.index', compact(
            'tab',
            'academicYear',
            'teachers',
            'subjects',
            'classrooms',
            'departments',
            'assignments',
            'teacherRecap',
            'supervisors',
            'candidateTeachers',
            'structuralPositions',
            'structuralCategories',
            'category',
            'structuralAssignments',
            'allTeachers',
            'positionId',
            'teacherId',
            'subjectId',
            'classroomId',
            'search'
        ));
    }

    public function storeSubjectAssignment(Request $request): RedirectResponse
    {
        $setting = SchoolSetting::getSetting();

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'academic_year' => ['nullable', 'string', 'max:50', 'exists:academic_years,name'],
            'semester' => ['required', 'string', 'max:20'],
            'hours_per_week' => ['required', 'integer', 'min:1', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['academic_year'])) {
            try {
                $data['academic_year'] = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $data['academic_year'] = SchoolSetting::getMasterYear();
            }
        } else {
            $data['academic_year'] = app(AcademicYearContext::class)->resolveExplicitYear($data['academic_year']);
        }

        // Cek duplikasi penugasan untuk guru, mapel, kelas, dan tahun yang sama
        $exists = TeacherSubjectAssignment::where('teacher_id', $data['teacher_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('classroom_id', $data['classroom_id'])
            ->where('academic_year', $data['academic_year'])
            ->exists();

        if ($exists) {
            return redirect()->route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $data['academic_year']])
                ->with('error', 'Penugasan guru pada mata pelajaran dan kelas tersebut sudah ada.');
        }

        TeacherSubjectAssignment::create($data);

        $teacher = User::find($data['teacher_id']);
        $subject = Subject::find($data['subject_id']);

        return redirect()->route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $data['academic_year']])
            ->with('success', "Penugasan {$teacher->name} untuk mata pelajaran {$subject->name} berhasil ditambahkan.");
    }

    public function destroySubjectAssignment(TeacherSubjectAssignment $assignment): RedirectResponse
    {
        $teacherName = $assignment->teacher?->name;
        $subjectName = $assignment->subject?->name;
        $academicYear = $assignment->academic_year;

        $assignment->delete();

        return redirect()->route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $academicYear])
            ->with('success', "Penugasan mapel {$subjectName} untuk {$teacherName} berhasil dihapus.");
    }

    public function downloadHomeroomTemplate(Request $request): StreamedResponse
    {
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }
        $classrooms = Classroom::where('academic_year', $academicYear)
            ->with('homeroomTeacher')
            ->orderBy('name')
            ->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::with('homeroomTeacher')->orderBy('name')->get();
        }

        $filename = 'template_wali_kelas_'.str_replace(['/', '\\'], '-', $academicYear).'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($classrooms) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens it with proper encoding
            fwrite($handle, "\xEF\xBB\xBF");
            // Header
            fputcsv($handle, ['Nama Kelas', 'Nama Wali Kelas', 'NIGS / NIP (Opsional)']);
            foreach ($classrooms as $cls) {
                fputcsv($handle, [
                    $cls->name,
                    $cls->homeroomTeacher?->name ?? '',
                    $cls->homeroomTeacher?->nigs ?? ($cls->homeroomTeacher?->nip ?? ''),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function importHomeroom(Request $request, HomeroomImportService $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20', 'exists:academic_years,name'],
        ]);

        if (! empty($validated['academic_year'])) {
            $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($validated['academic_year']);
        } else {
            try {
                $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
            }
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $result = $importer->import($file->getRealPath(), $extension, $academicYear);

        if (! $result['success'] && empty($result['updated'])) {
            $errMsg = ! empty($result['errors']) ? implode('<br>', $result['errors']) : 'Gagal mengimpor data wali kelas. Periksa format file Anda.';

            return redirect()->route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $academicYear])
                ->with('error', $errMsg);
        }

        $msg = "Berhasil memperbarui {$result['imported_count']} penetapan wali kelas.";
        if (! empty($result['errors'])) {
            $msg .= ' Beberapa baris dilewati: '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $academicYear])
            ->with('success', $msg);
    }

    public function updateHomeroom(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate([
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        $newTeacherId = ! empty($data['homeroom_teacher_id']) ? (int) $data['homeroom_teacher_id'] : null;

        app(HomeroomAssignmentService::class)->assignHomeroom($classroom, $newTeacherId);

        $teacherName = $classroom->fresh()->homeroomTeacher?->name ?? 'Belum Ditentukan';

        return redirect()->route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $classroom->academic_year])
            ->with('success', "Wali kelas untuk {$classroom->name} berhasil diperbarui: {$teacherName}.");
    }

    public function downloadSupervisorTemplate(Request $request): StreamedResponse
    {
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $supervisors = User::whereHas('supervisorAssignments', function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)->where('is_active', true);
        })
            ->with(['supervisorAssignments' => fn ($q) => $q->where('academic_year', $academicYear)])
            ->orderBy('name')
            ->get();

        if ($supervisors->isEmpty()) {
            $supervisors = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['supervisor', 'waka_kurikulum']))
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $filename = 'template_tim_supervisi_'.str_replace(['/', '\\'], '-', $academicYear).'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($supervisors) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nama Guru', 'NIGS / NIP (Opsional)', 'Posisi / Keterangan (Opsional)']);
            foreach ($supervisors as $s) {
                $assignment = $s->supervisorAssignments?->first();
                $pos = $assignment?->position_title ?: ($s->position ?: 'Guru / Supervisor');
                fputcsv($handle, [
                    $s->name,
                    $s->nigs ?? ($s->nip ?? ''),
                    $pos,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function importSupervisor(Request $request, SupervisorImportService $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20', 'exists:academic_years,name'],
        ]);

        if (! empty($validated['academic_year'])) {
            $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($validated['academic_year']);
        } else {
            try {
                $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
            }
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $result = $importer->import($file->getRealPath(), $extension, $academicYear);

        if (! $result['success'] && empty($result['updated'])) {
            $errMsg = ! empty($result['errors']) ? implode('<br>', $result['errors']) : 'Gagal mengimpor data supervisor. Periksa format file Anda.';

            return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
                ->with('error', $errMsg);
        }

        $msg = "Berhasil memperbarui {$result['imported_count']} guru ke dalam Tim Supervisor untuk TP {$academicYear}.";
        if (! empty($result['errors'])) {
            $msg .= ' Beberapa baris dilewati: '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
            ->with('success', $msg);
    }

    public function assignSupervisor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'position_title' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'string', 'max:50', 'exists:academic_years,name'],
        ]);

        if (! empty($data['academic_year'])) {
            $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($data['academic_year']);
        } else {
            try {
                $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
            }
        }

        $teacher = User::findOrFail($data['teacher_id']);
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        $academicYearId = AcademicYear::where('name', $academicYear)->value('id');

        // Simpan / update ke teacher_supervisor_assignments
        TeacherSupervisorAssignment::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'academic_year' => $academicYear,
            ],
            [
                'academic_year_id' => $academicYearId,
                'position_title' => $data['position_title'] ?? 'Supervisor',
                'is_active' => true,
            ]
        );

        // Sinkronkan penugasan peran kontekstual pada user_role_assignments
        if ($supervisorRole) {
            UserRoleAssignment::updateOrCreate(
                [
                    'user_id' => $teacher->id,
                    'role_id' => $supervisorRole->id,
                    'academic_year' => $academicYear,
                ],
                [
                    'academic_year_id' => $academicYearId,
                    'status' => true,
                    'notes' => 'Tim Penilai Supervisi Pembelajaran',
                ]
            );
        }

        // CATATAN ARSITEKTUR:
        // Penugasan supervisor TIDAK mengubah users.role_id maupun users.position.
        // Role utama pengguna tetap terjaga (misal: guru), dan jabatan profil tetap tidak berubah.

        return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
            ->with('success', "Guru {$teacher->name} berhasil ditugaskan ke dalam Tim Supervisor untuk Tahun Pelajaran {$academicYear}.");
    }

    public function removeSupervisor(User $user): RedirectResponse
    {
        $academicYearInput = request('academic_year');
        if (! empty($academicYearInput)) {
            $academicYear = $academicYearInput;
        } else {
            try {
                $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
            }
        }

        if ($user->hasRole('waka_kurikulum') || $user->hasRole('super_admin')) {
            return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
                ->with('error', 'Peran Waka Kurikulum / Administrator tidak dapat dicabut dari penugasan ini.');
        }

        // Hapus penugasan supervisor untuk tahun pelajaran terkait
        TeacherSupervisorAssignment::where('teacher_id', $user->id)
            ->where('academic_year', $academicYear)
            ->delete();

        // Hapus juga penugasan peran kontekstual supervisor untuk tahun pelajaran terkait
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        if ($supervisorRole) {
            UserRoleAssignment::where('user_id', $user->id)
                ->where('role_id', $supervisorRole->id)
                ->where('academic_year', $academicYear)
                ->delete();
        }

        // CATATAN ARSITEKTUR:
        // Pencabutan tugas supervisor TIDAK mengubah users.role_id maupun users.position.

        return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
            ->with('success', "Penugasan supervisor untuk {$user->name} pada TP {$academicYear} berhasil dicabut.");
    }

    public function downloadStructuralTemplate(Request $request): StreamedResponse
    {
        try {
            $academicYear = app(AcademicYearContext::class)->getViewingYear($request->query('academic_year'));
        } catch (\Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $positions = StructuralPosition::where('is_active', true)
            ->with(['activeAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with('teacher')])
            ->orderBy('order')
            ->get();

        $filename = 'template_penugasan_struktural_'.str_replace(['/', '\\'], '-', $academicYear).'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($positions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nama Jabatan', 'Nama Pejabat (Guru)', 'NIGS / NIP (Opsional)', 'Nomor SK (Opsional)', 'Tanggal SK (Opsional)', 'Catatan (Opsional)']);
            foreach ($positions as $pos) {
                $assignment = $pos->activeAssignments->first();
                fputcsv($handle, [
                    $pos->name,
                    $assignment?->teacher?->name ?? '',
                    $assignment?->teacher?->nigs ?? ($assignment?->teacher?->nip ?? ''),
                    $assignment?->sk_number ?? '',
                    $assignment?->sk_date ? (is_string($assignment->sk_date) ? $assignment->sk_date : $assignment->sk_date->format('Y-m-d')) : '',
                    $assignment?->notes ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function importStructural(Request $request, StructuralImportService $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20', 'exists:academic_years,name'],
        ]);

        if (! empty($validated['academic_year'])) {
            $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($validated['academic_year']);
        } else {
            try {
                $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
            } catch (\Throwable) {
                $academicYear = SchoolSetting::getMasterYear();
            }
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $result = $importer->import($file->getRealPath(), $extension, $academicYear);

        if (! $result['success'] && empty($result['updated'])) {
            $errMsg = ! empty($result['errors']) ? implode('<br>', $result['errors']) : 'Gagal mengimpor data penugasan struktural. Periksa format file Anda.';

            return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear])
                ->with('error', $errMsg);
        }

        $msg = "Berhasil memperbarui {$result['imported_count']} penugasan struktural.";
        if (! empty($result['errors'])) {
            $msg .= ' Beberapa baris dilewati: '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear])
            ->with('success', $msg);
    }

    public function storeStructuralAssignment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assignment_id' => ['nullable', 'exists:teacher_structural_assignments,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'structural_position_id' => ['required', 'exists:structural_positions,id'],
            'academic_year' => ['nullable', 'string', 'max:50', 'exists:academic_years,name'],
            'sk_number' => ['nullable', 'string', 'max:100'],
            'sk_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['assignment_id'])) {
            $assignment = TeacherStructuralAssignment::findOrFail($data['assignment_id']);
            $data['academic_year'] = ! empty($data['academic_year'])
                ? app(AcademicYearContext::class)->resolveExplicitYear($data['academic_year'])
                : $assignment->academic_year;
        } else {
            if (empty($data['academic_year'])) {
                try {
                    $data['academic_year'] = app(AcademicYearContext::class)->getOperationalYear()->name;
                } catch (\Throwable) {
                    $data['academic_year'] = SchoolSetting::getMasterYear();
                }
            } else {
                $data['academic_year'] = app(AcademicYearContext::class)->resolveExplicitYear($data['academic_year']);
            }
        }
        $data['is_active'] = true;

        $teacher = User::findOrFail($data['teacher_id']);
        $position = StructuralPosition::findOrFail($data['structural_position_id']);

        DB::transaction(function () use ($data, $position, $teacher) {
            $roleMap = [
                'KS' => 'kepala_sekolah',
                'WAKA_KUR' => 'waka_kurikulum',
                'WAKA_KESISWAAN' => 'waka_kesiswaan',
                'WAKA_SARPRAS' => 'waka_sarpras',
                'WAKA_HUMAS' => 'waka_humas',
            ];
            $code = strtoupper($position->code ?? '');

            if (! $position->allows_multiple) {
                // Penugasan Tunggal (allows_multiple = false):
                // Ambil seluruh penugasan yang sedang aktif pada tahun ajaran terkait
                $activeIncumbents = TeacherStructuralAssignment::where('structural_position_id', $position->id)
                    ->where('academic_year', $data['academic_year'])
                    ->where('is_active', true)
                    ->get();

                $targetAssignment = ! empty($data['assignment_id'])
                    ? TeacherStructuralAssignment::find($data['assignment_id'])
                    : null;

                if ($targetAssignment && (int) $targetAssignment->teacher_id === (int) $teacher->id) {
                    // Update data pejabat yang sama dari modal edit penugasan
                    $targetAssignment->update([
                        'sk_number' => $data['sk_number'] ?? $targetAssignment->sk_number,
                        'sk_date' => $data['sk_date'] ?? $targetAssignment->sk_date,
                        'notes' => $data['notes'] ?? $targetAssignment->notes,
                        'is_active' => true,
                    ]);
                } else {
                    $sameTeacherActive = $activeIncumbents->firstWhere('teacher_id', $teacher->id);

                    if ($sameTeacherActive && empty($data['assignment_id'])) {
                        // Pejabat aktif yang sama disimpan kembali tanpa assignment_id: perbarui tanpa duplikasi
                        $sameTeacherActive->update([
                            'sk_number' => $data['sk_number'] ?? $sameTeacherActive->sk_number,
                            'sk_date' => $data['sk_date'] ?? $sameTeacherActive->sk_date,
                            'notes' => $data['notes'] ?? $sameTeacherActive->notes,
                            'is_active' => true,
                        ]);
                    } else {
                        // Pejabat berbeda ditugaskan di tahun ajaran yang sama:
                        // 1. Nonaktifkan pejabat lama (is_active = false) untuk mempertahankan riwayat dan SK
                        foreach ($activeIncumbents as $oldAsgn) {
                            if ((int) $oldAsgn->teacher_id !== (int) $teacher->id) {
                                $oldAsgn->update(['is_active' => false]);

                                // 2. Cabut contextual role dari pejabat lama jika ada role sistem terkait
                                if (isset($roleMap[$code])) {
                                    $targetRole = Role::where('slug', $roleMap[$code])->first();
                                    if ($targetRole) {
                                        $hasOtherActive = TeacherStructuralAssignment::where('teacher_id', $oldAsgn->teacher_id)
                                            ->where('structural_position_id', $position->id)
                                            ->where('academic_year', $data['academic_year'])
                                            ->where('is_active', true)
                                            ->exists();

                                        if (! $hasOtherActive) {
                                            DB::table('user_role_assignments')
                                                ->where('user_id', $oldAsgn->teacher_id)
                                                ->where('role_id', $targetRole->id)
                                                ->where('academic_year', $data['academic_year'])
                                                ->delete();
                                        }
                                    }
                                }
                            }
                        }

                        // Jika form modal edit mengubah personil pada record tertentu, pastikan record lama dinonaktifkan
                        if ($targetAssignment && (int) $targetAssignment->teacher_id !== (int) $teacher->id && $targetAssignment->is_active) {
                            $targetAssignment->update(['is_active' => false]);
                        }

                        // 3. Buat record penugasan baru yang aktif untuk pejabat pengganti
                        TeacherStructuralAssignment::create([
                            'teacher_id' => $teacher->id,
                            'structural_position_id' => $position->id,
                            'academic_year' => $data['academic_year'],
                            'sk_number' => $data['sk_number'] ?? null,
                            'sk_date' => $data['sk_date'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'is_active' => true,
                        ]);
                    }
                }
            } else {
                // Penugasan Jamak (allows_multiple = true)
                if (! empty($data['assignment_id'])) {
                    $assignment = TeacherStructuralAssignment::findOrFail($data['assignment_id']);
                    $assignment->update([
                        'teacher_id' => $teacher->id,
                        'structural_position_id' => $position->id,
                        'academic_year' => $data['academic_year'],
                        'sk_number' => $data['sk_number'] ?? $assignment->sk_number,
                        'sk_date' => $data['sk_date'] ?? $assignment->sk_date,
                        'notes' => $data['notes'] ?? $assignment->notes,
                        'is_active' => true,
                    ]);
                } else {
                    $existing = TeacherStructuralAssignment::where('structural_position_id', $position->id)
                        ->where('teacher_id', $teacher->id)
                        ->where('academic_year', $data['academic_year'])
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'sk_number' => $data['sk_number'] ?? $existing->sk_number,
                            'sk_date' => $data['sk_date'] ?? $existing->sk_date,
                            'notes' => $data['notes'] ?? $existing->notes,
                            'is_active' => true,
                        ]);
                    } else {
                        TeacherStructuralAssignment::create([
                            'teacher_id' => $teacher->id,
                            'structural_position_id' => $position->id,
                            'academic_year' => $data['academic_year'],
                            'sk_number' => $data['sk_number'] ?? null,
                            'sk_date' => $data['sk_date'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            // Sinkronkan penugasan peran kontekstual pada user_role_assignments (Fase 2)
            if (isset($roleMap[$code])) {
                $targetRole = Role::where('slug', $roleMap[$code])->first();
                if ($targetRole) {
                    DB::table('user_role_assignments')->updateOrInsert(
                        [
                            'user_id' => $teacher->id,
                            'role_id' => $targetRole->id,
                            'academic_year' => $data['academic_year'],
                        ],
                        [
                            'sk_number' => $data['sk_number'] ?? null,
                            'notes' => (! empty($data['notes'])) ? $data['notes'] : "Penugasan Struktural {$position->name}",
                            'status' => true,
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $data['academic_year']])
            ->with('success', "Penugasan struktural {$position->name} untuk {$teacher->name} berhasil disimpan.");
    }

    public function destroyStructuralAssignment(TeacherStructuralAssignment $assignment): RedirectResponse
    {
        $teacher = $assignment->teacher;
        $pos = $assignment->structuralPosition;
        $academicYear = $assignment->academic_year;

        DB::transaction(function () use ($assignment, $teacher, $pos, $academicYear) {
            // Synchronize removal with user_role_assignments if applicable
            if ($pos && $teacher) {
                $roleMap = [
                    'KS' => 'kepala_sekolah',
                    'WAKA_KUR' => 'waka_kurikulum',
                    'WAKA_KESISWAAN' => 'waka_kesiswaan',
                    'WAKA_SARPRAS' => 'waka_sarpras',
                    'WAKA_HUMAS' => 'waka_humas',
                ];
                $code = strtoupper($pos->code ?? '');
                if (isset($roleMap[$code])) {
                    $targetRole = Role::where('slug', $roleMap[$code])->first();
                    if ($targetRole) {
                        $hasOtherActive = TeacherStructuralAssignment::where('teacher_id', $teacher->id)
                            ->where('structural_position_id', $pos->id)
                            ->where('academic_year', $academicYear)
                            ->where('id', '!=', $assignment->id)
                            ->where('is_active', true)
                            ->exists();

                        if (! $hasOtherActive) {
                            DB::table('user_role_assignments')
                                ->where('user_id', $teacher->id)
                                ->where('role_id', $targetRole->id)
                                ->where('academic_year', $academicYear)
                                ->delete();
                        }
                    }
                }
            }

            $assignment->delete();
        });

        $teacherName = $teacher?->name ?? 'Guru';
        $posName = $pos?->name ?? 'Jabatan';

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear])
            ->with('success', "Penugasan struktural {$posName} untuk {$teacherName} berhasil dicabut.");
    }
}
