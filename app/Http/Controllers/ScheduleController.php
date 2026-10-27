<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AcademicYearContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();
        $context = app(AcademicYearContext::class);

        if ($request->has('academic_year')) {
            $rawYear = $request->query('academic_year');
            if ($rawYear === 'all') {
                $academicYear = 'all';
            } else {
                try {
                    $academicYear = $context->getViewingYear($rawYear);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'academic_year' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            $academicYear = $context->getViewingYear();
        }

        $ayModel = $academicYear !== 'all' ? AcademicYear::where('name', $academicYear)->first() : null;
        $semester = $request->query('semester', $ayModel?->semester ?? $setting->semester ?? 'Ganjil');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Supervision::with(['teacher', 'supervisor']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        // If user is supervisor only, constrain to assigned
        if ($user->hasRole('supervisor') && ! $user->isWakaKurikulum()) {
            $query->where('supervisor_id', $user->id);
        }

        // If user is teacher only, constrain to own
        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('supervisor', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        $schedules = $query->orderBy('schedule_date', 'asc')->paginate(15)->withQueryString();

        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('schedules.index', compact('schedules', 'status', 'search', 'academicYear', 'semester', 'existingYears', 'setting'));
    }

    protected function authorizeManage(): void
    {
        if (! Auth::user()->hasPermission('manage_schedules')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola jadwal supervisi.');
        }
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();

        $setting = SchoolSetting::getSetting();
        $context = app(AcademicYearContext::class);

        // Jika ada target tahun eksplisit pada query string, validasi keberadaannya
        if ($request->filled('academic_year')) {
            $targetYear = $context->resolveExplicitYear($request->query('academic_year'));
            if (! $targetYear) {
                throw ValidationException::withMessages([
                    'academic_year' => 'Tahun ajaran target tidak valid.',
                ]);
            }
            $academicYear = $targetYear->name;
            $semester = $request->query('semester', $targetYear->semester ?? 'Ganjil');
        } else {
            // Default pembuatan jadwal baru selalu menggunakan tahun operasional, BUKAN session arsip
            $operationalYear = $context->getOperationalYear();
            $academicYear = $operationalYear->name;
            $semester = $request->query('semester', $operationalYear->semester ?? 'Ganjil');
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor']))
            ->where('is_active', true)
            ->with([
                'teachingSubjects',
                'subjectAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with(['subject', 'classroom']),
                'lessonSchedules' => fn ($q) => $q->where('academic_year', $academicYear)->with(['classroom.department', 'subject']),
            ])
            ->orderBy('name')
            ->get();

        $dayOrder = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $teachers->each(function ($teacher) use ($dayOrder) {
            $sorted = $teacher->lessonSchedules->sortBy([
                fn ($a, $b) => ($dayOrder[$a->day] ?? 99) <=> ($dayOrder[$b->day] ?? 99),
                ['start_period', 'asc'],
            ])->values();
            $teacher->setRelation('lessonSchedules', $sorted);
        });

        $teachersSchedulesMap = $this->buildTeacherSchedulesMap($teachers);

        $supervisors = User::whereHas('supervisorAssignments', fn ($q) => $q->where('academic_year', $academicYear)->where('is_active', true))
            ->operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($supervisors->isEmpty()) {
            $supervisors = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['supervisor', 'waka_kurikulum']))
                ->operationalPersonnel()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $classrooms = Classroom::where(function ($q) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $q->where('academic_year_id', $ayModel->id);
            }
            $q->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::orderBy('name')->get();
        }

        return view('schedules.create', compact('setting', 'teachers', 'supervisors', 'subjects', 'classrooms', 'academicYear', 'semester', 'teachersSchedulesMap'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $context = app(AcademicYearContext::class);

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'schedule_date' => ['required', 'date'],
            'schedule_time' => ['required', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'expertise_program' => ['nullable', 'string', 'max:255'],
            'schedule_notes' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
        ]);

        // Resolusi tahun ajaran: jika diisi eksplisit, validasi target. Jika kosong, gunakan tahun operasional.
        if (! empty($data['academic_year'])) {
            try {
                $targetYear = $context->resolveExplicitYear($data['academic_year']);
                $data['academic_year'] = $targetYear;
                $data['semester'] = ($data['semester'] ?? null) ?: 'Ganjil';
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'academic_year' => $e->getMessage(),
                ]);
            }
        } else {
            $operationalYear = $context->getOperationalYear();
            $data['academic_year'] = $operationalYear->name;
            $data['semester'] = ($data['semester'] ?? null) ?: ($operationalYear->semester ?? 'Ganjil');
        }

        $teacher = User::findOrFail($data['teacher_id']);

        $data['subject'] = $data['subject'] ?: ($teacher->subjectAssignments()->where('academic_year', $data['academic_year'])->first()?->subject?->name ?? '-');
        $data['class_name'] = $data['class_name'] ?: ($teacher->homeroomClassrooms()->where('academic_year', $data['academic_year'])->first()?->name ?? '-');
        $data['expertise_program'] = $data['expertise_program'] ?: ($teacher->homeroomClassrooms()->where('academic_year', $data['academic_year'])->first()?->department?->name ?? '-');
        $data['status'] = 'terjadwal';

        Supervision::create($data);

        return redirect()->route('schedules.index', [
            'academic_year' => $data['academic_year'],
            'semester' => $data['semester'],
        ])->with('success', "Jadwal supervisi untuk {$teacher->name} (TP {$data['academic_year']} {$data['semester']}) berhasil dibuat.");
    }

    public function edit(Supervision $schedule): View
    {
        $this->authorizeManage();

        $setting = SchoolSetting::getSetting();
        $context = app(AcademicYearContext::class);
        $academicYear = $schedule->academic_year ?: $context->getOperationalYear()->name;
        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor']))
            ->where('is_active', true)
            ->with([
                'teachingSubjects',
                'subjectAssignments' => fn ($q) => $q->where('academic_year', $academicYear)->with(['subject', 'classroom']),
                'lessonSchedules' => fn ($q) => $q->where('academic_year', $academicYear)->with(['classroom.department', 'subject']),
            ])
            ->orderBy('name')
            ->get();

        $dayOrder = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $teachers->each(function ($teacher) use ($dayOrder) {
            $sorted = $teacher->lessonSchedules->sortBy([
                fn ($a, $b) => ($dayOrder[$a->day] ?? 99) <=> ($dayOrder[$b->day] ?? 99),
                ['start_period', 'asc'],
            ])->values();
            $teacher->setRelation('lessonSchedules', $sorted);
        });

        $teachersSchedulesMap = $this->buildTeacherSchedulesMap($teachers);

        $supervisors = User::whereHas('supervisorAssignments', fn ($q) => $q->where('academic_year', $academicYear)->where('is_active', true))
            ->operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($supervisors->isEmpty()) {
            $supervisors = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['supervisor', 'waka_kurikulum']))
                ->operationalPersonnel()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $classrooms = Classroom::where(function ($q) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $q->where('academic_year_id', $ayModel->id);
            }
            $q->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::orderBy('name')->get();
        }

        return view('schedules.edit', compact('schedule', 'teachers', 'supervisors', 'subjects', 'classrooms', 'setting', 'academicYear', 'teachersSchedulesMap'));
    }

    public function update(Request $request, Supervision $schedule): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'schedule_date' => ['required', 'date'],
            'schedule_time' => ['required', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:50'],
            'expertise_program' => ['nullable', 'string', 'max:255'],
            'schedule_notes' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:terjadwal,praobservasi,observasi,pascaobservasi,menunggu_persetujuan,disetujui,selesai'],
        ]);

        // Selalu pertahankan tahun ajaran dan semester asli dokumen jadwal yang tersimpan (immutable)
        $data['academic_year'] = $schedule->academic_year;
        $data['semester'] = $schedule->semester;

        $schedule->update($data);

        return redirect()->route('schedules.index', [
            'academic_year' => $schedule->academic_year,
            'semester' => $schedule->semester,
        ])->with('success', 'Jadwal supervisi berhasil diperbarui.');
    }

    public function destroy(Supervision $schedule): RedirectResponse
    {
        $this->authorizeManage();

        // Jadwal yang sudah berjalan, selesai, disetujui, memiliki penilaian, atau disahkan tidak boleh dihapus
        if (
            $schedule->status !== 'terjadwal'
            || $schedule->approved_by_principal
            || $schedule->principal_approved_at !== null
            || $schedule->supervisor_signed_at !== null
            || $schedule->finalized_at !== null
            || $schedule->scores()->exists()
            || ($schedule->total_score ?? 0) > 0
            || $schedule->photos()->exists()
            || $schedule->pre_completed_at !== null
            || $schedule->obs_completed_at !== null
            || $schedule->post_completed_at !== null
        ) {
            return redirect()->route('schedules.index', [
                'academic_year' => $schedule->academic_year,
                'semester' => $schedule->semester,
            ])->with('error', 'Jadwal supervisi tidak dapat dihapus karena sudah berjalan, memiliki data penilaian, atau telah disahkan.');
        }

        $academicYear = $schedule->academic_year;
        $semester = $schedule->semester;

        $schedule->delete();

        return redirect()->route('schedules.index', [
            'academic_year' => $academicYear,
            'semester' => $semester,
        ])->with('success', 'Jadwal supervisi berhasil dihapus.');
    }

    /**
     * Map lesson schedules for each teacher to structured JS-ready array.
     */
    protected function buildTeacherSchedulesMap($teachers): array
    {
        return $teachers->mapWithKeys(function ($teacher) {
            return [
                $teacher->id => $teacher->lessonSchedules->map(function ($ls) {
                    $startTime = $ls->start_time ?: '07:00';
                    $endTime = $ls->end_time ?: '08:30';
                    $periodText = "Jam ke {$ls->start_period} - {$ls->end_period}";
                    $timeFormatted = "{$periodText} ({$startTime} - {$endTime} WIB)";

                    return [
                        'id' => $ls->id,
                        'day' => $ls->day,
                        'start_period' => $ls->start_period,
                        'end_period' => $ls->end_period,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'period_text' => $periodText,
                        'time_formatted' => $timeFormatted,
                        'class_name' => $ls->classroom?->name ?? '-',
                        'classroom_id' => $ls->classroom_id,
                        'department_name' => $ls->classroom?->department?->name ?? '',
                        'subject_name' => $ls->subject?->name ?? '-',
                        'subject_code' => $ls->subject?->code ?? '',
                    ];
                })->values(),
            ];
        })->toArray();
    }
}
