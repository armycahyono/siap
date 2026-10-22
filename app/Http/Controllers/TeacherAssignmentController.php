<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TeacherSupervisorAssignment;
use App\Models\User;
use App\Services\HomeroomImportService;
use App\Services\StructuralImportService;
use App\Services\SupervisorImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $tab = $request->query('tab', 'mapel'); // 'mapel', 'walikelas', 'supervisor'
        $academicYear = $request->query('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));
        $teacherId = $request->query('teacher_id');
        $subjectId = $request->query('subject_id');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        // Data Guru (Role guru & supervisor, aktif)
        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum']))
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
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs_fix', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('classroom', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $assignments = $assignmentQuery->orderBy('teacher_id')->paginate(15)->withQueryString();

        // Rekap per guru yang mengajar mapel: guru_id => list of subjects & classes
        $teacherRecap = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum']))
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

        // 4. Data Penugasan Struktural
        $structuralPositions = StructuralPosition::where('is_active', true)->orderBy('order')->orderBy('id')->get();
        $structuralAssignmentQuery = TeacherStructuralAssignment::with(['teacher', 'structuralPosition'])
            ->where('academic_year', $academicYear);

        if ($positionId) {
            $structuralAssignmentQuery->where('structural_position_id', $positionId);
        }

        if ($teacherId) {
            $structuralAssignmentQuery->where('teacher_id', $teacherId);
        }

        if ($search && $tab === 'struktural') {
            $structuralAssignmentQuery->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs_fix', 'like', "%{$search}%"))
                    ->orWhereHas('structuralPosition', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%"));
            });
        }

        $structuralAssignments = $structuralAssignmentQuery->orderBy('structural_position_id')->paginate(15)->withQueryString();
        $allTeachers = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();

        // 5. Data Penugasan Piket
        $dayOrder = "CASE day WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END";
        $piketSchedules = PiketSchedule::with('teacher')
            ->where('academic_year', $academicYear)
            ->orderByRaw($dayOrder)
            ->orderBy('role', 'desc')
            ->get();

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
            'structuralAssignments',
            'allTeachers',
            'piketSchedules',
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
            'academic_year' => ['nullable', 'string', 'max:50'],
            'semester' => ['required', 'string', 'max:20'],
            'hours_per_week' => ['required', 'integer', 'min:1', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['academic_year'] = $data['academic_year'] ?: $setting->academic_year;

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
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
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
                    $cls->homeroomTeacher?->nigs_fix ?? ($cls->homeroomTeacher?->nip ?? ''),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function importHomeroom(Request $request, HomeroomImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', $setting->academic_year);

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

        $classroom->update($data);

        $teacherName = $classroom->homeroomTeacher?->name ?? 'Belum Ditentukan';

        return redirect()->route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $classroom->academic_year])
            ->with('success', "Wali kelas untuk {$classroom->name} berhasil diperbarui: {$teacherName}.");
    }

    public function downloadSupervisorTemplate(Request $request): StreamedResponse
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);

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
                    $s->nigs_fix ?? ($s->nip ?? ''),
                    $pos,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function importSupervisor(Request $request, SupervisorImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', $setting->academic_year);

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
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', $setting->academic_year);

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'position_title' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'string', 'max:50'],
        ]);

        $academicYear = $data['academic_year'] ?? $academicYear;
        $teacher = User::findOrFail($data['teacher_id']);
        $supervisorRole = Role::where('slug', 'supervisor')->firstOrFail();
        $academicYearId = AcademicYear::where('name', $academicYear)->value('id');

        // Simpan / update ke teacher_supervisor_assignments
        TeacherSupervisorAssignment::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'academic_year' => $academicYear,
            ],
            [
                'academic_year_id' => $academicYearId,
                'position_title' => $data['position_title'] ?? ($teacher->position ?: 'Supervisor'),
                'is_active' => true,
            ]
        );

        // Jika user memiliki role guru, berikan hak role supervisor agar bisa mengakses fitur/menu supervisi
        if ($teacher->hasRole('guru')) {
            $updateData = ['role_id' => $supervisorRole->id];
            if (! empty($data['position_title'])) {
                $updateData['position'] = $data['position_title'];
            } elseif (empty($teacher->position)) {
                $updateData['position'] = 'Guru / Supervisor';
            }
            $teacher->update($updateData);
        } elseif (! empty($data['position_title']) && ! $teacher->hasRole(['super_admin', 'kepala_sekolah', 'waka_kurikulum'])) {
            $teacher->update(['position' => $data['position_title']]);
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
            ->with('success', "Guru {$teacher->name} berhasil ditugaskan ke dalam Tim Supervisor untuk Tahun Pelajaran {$academicYear}.");
    }

    public function removeSupervisor(User $user): RedirectResponse
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = request('academic_year', $setting->academic_year);

        if ($user->hasRole('waka_kurikulum') || $user->hasRole('super_admin')) {
            return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
                ->with('error', 'Peran Waka Kurikulum / Administrator tidak dapat dicabut dari penugasan ini.');
        }

        // Hapus penugasan supervisor untuk tahun pelajaran terkait
        TeacherSupervisorAssignment::where('teacher_id', $user->id)
            ->where('academic_year', $academicYear)
            ->delete();

        // Jika guru ini tidak lagi memiliki penugasan supervisor di tahun pelajaran mana pun, kembalikan role ke guru
        $hasOtherSupervisorAssignments = TeacherSupervisorAssignment::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasOtherSupervisorAssignments && $user->hasRole('supervisor')) {
            $guruRole = Role::where('slug', 'guru')->firstOrFail();
            $structuralPosition = $user->structuralPositions()->first();
            $defaultPosition = $structuralPosition ? $structuralPosition->name : ($user->position ?: 'Guru');

            $user->update([
                'role_id' => $guruRole->id,
                'position' => $defaultPosition,
            ]);
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear])
            ->with('success', "Penugasan supervisor untuk {$user->name} pada TP {$academicYear} berhasil dicabut.");
    }

    public function downloadStructuralTemplate(Request $request): StreamedResponse
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);

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
                    $assignment?->teacher?->nigs_fix ?? ($assignment?->teacher?->nip ?? ''),
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
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt,xls'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', $setting->academic_year);

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
        $setting = SchoolSetting::getSetting();

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'structural_position_id' => ['required', 'exists:structural_positions,id'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'sk_number' => ['nullable', 'string', 'max:100'],
            'sk_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['academic_year'] = $data['academic_year'] ?: $setting->academic_year;
        $data['is_active'] = true;

        $teacher = User::findOrFail($data['teacher_id']);
        $position = StructuralPosition::findOrFail($data['structural_position_id']);

        $assignment = TeacherStructuralAssignment::updateOrCreate(
            [
                'teacher_id' => $data['teacher_id'],
                'structural_position_id' => $data['structural_position_id'],
                'academic_year' => $data['academic_year'],
            ],
            [
                'sk_number' => $data['sk_number'] ?? null,
                'sk_date' => $data['sk_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]
        );

        if ($position->name && ! $teacher->hasRole(['super_admin', 'kepala_sekolah'])) {
            $teacher->update(['position' => $position->name]);
        }

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $data['academic_year']])
            ->with('success', "Penugasan struktural {$position->name} untuk {$teacher->name} berhasil disimpan.");
    }

    public function destroyStructuralAssignment(TeacherStructuralAssignment $assignment): RedirectResponse
    {
        $teacherName = $assignment->teacher?->name ?? 'Guru';
        $posName = $assignment->structuralPosition?->name ?? 'Jabatan';
        $academicYear = $assignment->academic_year;

        $assignment->delete();

        return redirect()->route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear])
            ->with('success', "Penugasan struktural {$posName} untuk {$teacherName} berhasil dihapus.");
    }
}
