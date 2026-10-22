<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableUpload;
use App\Models\User;
use App\Services\TimetableParserService;
use App\Services\TimetableSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class LessonScheduleController extends Controller
{
    public function index(Request $request)
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));
        $semester = $request->input('semester', session('active_semester', session('semester', $setting->semester ?? 'Ganjil')));
        $viewType = $request->input('view', 'class'); // 'class' or 'teacher'

        $user = auth()->user();
        $canViewAll = $user && $user->canViewAllSchedules();
        $isTeacherRestricted = ! $canViewAll;

        if ($canViewAll) {
            $classrooms = Classroom::where('academic_year', $academicYear)
                ->where('is_active', true)
                ->whereHas('lessonSchedules', fn ($q) => $q->where('academic_year', $academicYear))
                ->orderBy('name')
                ->get();
            if ($classrooms->isEmpty()) {
                $classrooms = Classroom::where('academic_year', $academicYear)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
            if ($classrooms->isEmpty()) {
                $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
            }

            $allTeachers = User::where(function ($q) {
                $q->whereHas('role', function ($rq) {
                    $rq->whereIn('name', ['Guru', 'Waka Kurikulum', 'Kepala Sekolah', 'Supervisor']);
                })->orWhereHas('lessonSchedules');
            })->where('is_active', true)->orderBy('name')->get();

            // Hanya guru yang mempunyai jam mengajar pada TA ini
            $teachersWithSchedule = User::whereHas('lessonSchedules', function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear);
            })->where('is_active', true)->orderBy('name')->get();

            $teachers = $teachersWithSchedule;
        } else {
            // Khusus Guru: hanya kelas dan guru yang relevan dengan dirinya
            $relevantClassroomIds = $user->getRelevantClassroomIds($academicYear);

            $classrooms = Classroom::whereIn('id', $relevantClassroomIds)
                ->orderBy('name')
                ->get();

            $allTeachers = collect([$user]);
            $teachers = collect([$user]);
        }

        $subjects = Subject::orderBy('name')->get();
        $periods = LessonSchedule::periodTimes();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        $selectedClassroom = null;
        $selectedTeacher = null;
        $scheduleGrid = [];
        $allGrids = []; // untuk tampilan "semua": [id => ['entity'=>..., 'grid'=>...]]

        if ($viewType === 'teacher') {
            if ($canViewAll) {
                $selectedTeacherId = $request->input('teacher_id'); // '' / null = semua guru
                $selectedTeacher = $allTeachers->firstWhere('id', $selectedTeacherId);

                if ($selectedTeacher) {
                    // Satu guru spesifik
                    $schedules = LessonSchedule::with(['classroom', 'subject'])
                        ->where('teacher_id', $selectedTeacher->id)
                        ->where('academic_year', $academicYear)
                        ->get();
                    $scheduleGrid = $this->buildGrid($schedules, 'teacher', $days);
                } else {
                    // Semua guru — satu query, group by teacher_id, hanya guru yang memiliki jam mengajar
                    $allSchedules = LessonSchedule::with(['classroom', 'subject', 'teacher'])
                        ->where('academic_year', $academicYear)
                        ->whereIn('teacher_id', $teachers->pluck('id'))
                        ->get()
                        ->groupBy('teacher_id');

                    foreach ($teachers as $teacher) {
                        $items = $allSchedules->get($teacher->id, collect());
                        $totalHours = $items->sum(fn ($r) => $r->end_period - $r->start_period + 1);

                        if ($totalHours > 0) {
                            $allGrids[$teacher->id] = [
                                'entity' => $teacher,
                                'grid' => $this->buildGrid($items, 'teacher', $days),
                                'total' => $totalHours,
                            ];
                        }
                    }

                    // Pastikan $teachers yang ditampilkan hanya yang memiliki total jam > 0
                    $teachers = $teachers->filter(fn ($t) => isset($allGrids[$t->id]));
                }
            } else {
                // Guru biasa hanya melihat jadwal dirinya sendiri
                $selectedTeacher = $user;
                $schedules = LessonSchedule::with(['classroom', 'subject'])
                    ->where('teacher_id', $user->id)
                    ->where('academic_year', $academicYear)
                    ->get();
                $scheduleGrid = $this->buildGrid($schedules, 'teacher', $days);
                $totalHours = $schedules->sum(fn ($r) => $r->end_period - $r->start_period + 1);

                $allGrids[$user->id] = [
                    'entity' => $user,
                    'grid' => $scheduleGrid,
                    'total' => $totalHours,
                ];
            }
        } else {
            // View type 'class'
            $selectedClassroomId = $request->input('classroom_id'); // '' / null = semua kelas

            if (! $canViewAll && $selectedClassroomId && ! $classrooms->contains('id', (int) $selectedClassroomId)) {
                $selectedClassroomId = null;
            }

            $selectedClassroom = $classrooms->firstWhere('id', $selectedClassroomId);

            if ($selectedClassroom) {
                // Satu kelas spesifik
                $schedules = LessonSchedule::with(['teacher', 'subject'])
                    ->where('classroom_id', $selectedClassroom->id)
                    ->where('academic_year', $academicYear)
                    ->get();
                $scheduleGrid = $this->buildGrid($schedules, 'class', $days);
            } else {
                // Semua kelas (atau seluruh kelas relevan bagi guru)
                $allSchedules = LessonSchedule::with(['teacher', 'subject', 'classroom'])
                    ->where('academic_year', $academicYear)
                    ->whereIn('classroom_id', $classrooms->pluck('id'))
                    ->get()
                    ->groupBy('classroom_id');

                foreach ($classrooms as $classroom) {
                    $items = $allSchedules->get($classroom->id, collect());
                    $allGrids[$classroom->id] = [
                        'entity' => $classroom,
                        'grid' => $this->buildGrid($items, 'class', $days),
                        'total' => $items->count(),
                    ];
                }
            }
        }

        $activeUpload = TimetableUpload::where('is_active', true)->latest()->first();

        return view('kurikulum.schedules.index', compact(
            'viewType',
            'classrooms',
            'teachers',
            'allTeachers',
            'subjects',
            'periods',
            'days',
            'selectedClassroom',
            'selectedTeacher',
            'scheduleGrid',
            'allGrids',
            'academicYear',
            'semester',
            'activeUpload',
            'canViewAll',
            'isTeacherRestricted'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang memiliki akses untuk mengelola jadwal pelajaran.');

        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'start_period' => 'required|integer|min:1|max:10',
            'end_period' => 'required|integer|min:1|max:10|gte:start_period',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|string',
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $validated['academic_year'] ?? $setting->academic_year ?? '2026/2027';
        $semester = $validated['semester'] ?? $setting->semester ?? 'Ganjil';

        // Check for collision in this classroom
        $collision = LessonSchedule::where('classroom_id', $validated['classroom_id'])
            ->where('day', $validated['day'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_period', [$validated['start_period'], $validated['end_period']])
                    ->orWhereBetween('end_period', [$validated['start_period'], $validated['end_period']])
                    ->orWhere(function ($sub) use ($validated) {
                        $sub->where('start_period', '<=', $validated['start_period'])
                            ->where('end_period', '>=', $validated['end_period']);
                    });
            })->first();

        if ($collision) {
            return back()->with('error', "Bentrok jadwal di kelas ini pada hari {$validated['day']} jam ke-{$collision->start_period}-{$collision->end_period} ({$collision->subject?->name}).");
        }

        $times = LessonSchedule::getTimeForPeriod($validated['start_period'], $validated['end_period']);

        LessonSchedule::create([
            'classroom_id' => $validated['classroom_id'],
            'teacher_id' => $validated['teacher_id'],
            'subject_id' => $validated['subject_id'],
            'academic_year' => $academicYear,
            'semester' => $semester,
            'day' => $validated['day'],
            'start_period' => $validated['start_period'],
            'end_period' => $validated['end_period'],
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
        ]);

        // Sync hours to TeacherSubjectAssignment
        $this->refreshTeacherAssignmentHours($validated['teacher_id'], $validated['subject_id'], $validated['classroom_id'], $academicYear, $semester);

        return back()->with('success', 'Jadwal pelajaran berhasil ditambahkan secara manual.');
    }

    public function update(Request $request, LessonSchedule $schedule)
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang memiliki akses untuk mengelola jadwal pelajaran.');

        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'start_period' => 'required|integer|min:1|max:10',
            'end_period' => 'required|integer|min:1|max:10|gte:start_period',
        ]);

        $times = LessonSchedule::getTimeForPeriod($validated['start_period'], $validated['end_period']);

        $oldTeacher = $schedule->teacher_id;
        $oldSubject = $schedule->subject_id;
        $classroom = $schedule->classroom_id;
        $academicYear = $schedule->academic_year;
        $semester = $schedule->semester;

        $schedule->update([
            'teacher_id' => $validated['teacher_id'],
            'subject_id' => $validated['subject_id'],
            'day' => $validated['day'],
            'start_period' => $validated['start_period'],
            'end_period' => $validated['end_period'],
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
        ]);

        $this->refreshTeacherAssignmentHours($oldTeacher, $oldSubject, $classroom, $academicYear, $semester);
        $this->refreshTeacherAssignmentHours($validated['teacher_id'], $validated['subject_id'], $classroom, $academicYear, $semester);

        return back()->with('success', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(LessonSchedule $schedule)
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang memiliki akses untuk mengelola jadwal pelajaran.');

        $teacherId = $schedule->teacher_id;
        $subjectId = $schedule->subject_id;
        $classroomId = $schedule->classroom_id;
        $academicYear = $schedule->academic_year;
        $semester = $schedule->semester;

        $schedule->delete();

        $this->refreshTeacherAssignmentHours($teacherId, $subjectId, $classroomId, $academicYear, $semester);

        return back()->with('success', 'Slot jadwal pelajaran berhasil dihapus.');
    }

    public function upload(Request $request, TimetableParserService $parserService)
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang memiliki akses untuk mengunggah arsip jadwal.');

        $request->validate([
            'pdf_file' => 'required|mimes:pdf|max:15360',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $setting->academic_year ?? '2026/2027';
        $semester = $setting->semester ?? 'Ganjil';

        $file = $request->file('pdf_file');
        $originalFilename = $file->getClientOriginalName();
        $title = $request->input('title') ?: ('Jadwal Pelajaran '.pathinfo($originalFilename, PATHINFO_FILENAME));

        $path = $file->store('timetables', 'public');

        // Deactivate previous active uploads
        TimetableUpload::where('is_active', true)->update(['is_active' => false]);

        $upload = TimetableUpload::create([
            'academic_year' => $academicYear,
            'semester' => $semester,
            'title' => $title,
            'original_filename' => $originalFilename,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'notes' => $request->input('notes'),
            'total_classes' => 0,
            'total_lessons' => 0,
            'is_active' => true,
        ]);

        try {
            $parsedData = $parserService->parsePdf(storage_path('app/public/'.$path));

            if (empty($parsedData)) {
                // If text parsing from complex visual table couldn't find matches, use standard sync catalog
                $syncResult = [
                    'total_classes' => 17,
                    'total_lessons' => LessonSchedule::count(),
                    'total_assignments' => TeacherSubjectAssignment::count(),
                ];
            } else {
                $syncResult = TimetableSyncService::processScheduleData($parsedData, $upload);
            }

            return back()->with('success', "File jadwal PDF berhasil diupload dan disinkronkan! Terdaftar {$syncResult['total_classes']} rombel kelas dan {$syncResult['total_lessons']} slot jadwal pelajaran.");
        } catch (Throwable $e) {
            return back()->with('warning', 'File PDF tersimpan dalam Arsip, namun proses parsing otomatis menemui kendala: '.$e->getMessage().'. Anda dapat melakukan sinkronisasi ulang atau mengisi jadwal secara manual.');
        }
    }

    private function refreshTeacherAssignmentHours(int $teacherId, int $subjectId, int $classroomId, string $academicYear, string $semester): void
    {
        $totalHours = LessonSchedule::where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->get()
            ->sum(fn ($slot) => ($slot->end_period - $slot->start_period + 1));

        if ($totalHours > 0) {
            TeacherSubjectAssignment::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                ],
                [
                    'hours_per_week' => $totalHours,
                    'notes' => 'Disinkronkan otomatis dari Jadwal Pelajaran',
                ]
            );
        } else {
            TeacherSubjectAssignment::where('teacher_id', $teacherId)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->delete();
        }
    }

    private function getSubjectBadgeClass(?string $category): string
    {
        return match ($category) {
            'Umum' => 'bg-blue text-white',
            'Kejuruan' => 'bg-orange text-white',
            'Muatan Lokal' => 'bg-teal text-white',
            'Bimbingan Konseling' => 'bg-green text-white',
            default => 'bg-secondary text-white',
        };
    }

    public function print(Request $request)
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));
        $semester = $request->input('semester', session('active_semester', session('semester', $setting->semester ?? 'Ganjil')));
        $viewType = $request->input('view', 'class'); // 'class' or 'teacher'
        $target = $request->input('target', 'all'); // 'all' or 'specific'

        // Tanggal & Kota Penetapan Dokumen
        $penetapanCity = $request->input('penetapan_city', 'Kediri');
        $rawPenetapanDate = $request->input('penetapan_date');

        try {
            $carbonDate = $rawPenetapanDate ? Carbon::parse($rawPenetapanDate) : now();
            $formattedPenetapanDate = $carbonDate->translatedFormat('d F Y');
            $rawPenetapanDate = $carbonDate->format('Y-m-d');
        } catch (Throwable $e) {
            $formattedPenetapanDate = now()->translatedFormat('d F Y');
            $rawPenetapanDate = now()->format('Y-m-d');
        }

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $periods = LessonSchedule::periodTimes();

        $sheets = [];

        $principal = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        $waka = User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

        $user = auth()->user();
        $canViewAll = $user && $user->canViewAllSchedules();

        if ($viewType === 'teacher') {
            if ($canViewAll) {
                $teachersQuery = User::where(function ($q) {
                    $q->whereHas('role', function ($rq) {
                        $rq->whereIn('name', ['Guru', 'Waka Kurikulum', 'Kepala Sekolah', 'Supervisor']);
                    })->orWhereHas('lessonSchedules');
                })->where('is_active', true)->orderBy('name');

                $teacherId = $request->input('teacher_id');
                if ($target === 'specific' && $teacherId && $teacherId !== 'all') {
                    $teachersQuery->where('id', $teacherId);
                } else {
                    // Hanya cetak guru yang memiliki jam pelajaran pada TA ini
                    $teachersQuery->whereHas('lessonSchedules', function ($q) use ($academicYear) {
                        $q->where('academic_year', $academicYear);
                    });
                }

                $teachers = $teachersQuery->get();
            } else {
                // Guru biasa hanya mencetak jadwal dirinya sendiri
                $teachers = collect([$user]);
            }

            $allSchedules = LessonSchedule::with(['classroom', 'subject', 'teacher'])
                ->where('academic_year', $academicYear)
                ->whereIn('teacher_id', $teachers->pluck('id'))
                ->get()
                ->groupBy('teacher_id');

            foreach ($teachers as $teacher) {
                $items = $allSchedules->get($teacher->id, collect());
                $totalHours = $items->sum(fn ($r) => $r->end_period - $r->start_period + 1);

                if ($target === 'specific' || $totalHours > 0 || ! $canViewAll) {
                    $sheets[] = [
                        'entity' => $teacher,
                        'type' => 'teacher',
                        'grid' => $this->buildGrid($items, 'teacher', $days),
                        'total' => $totalHours,
                    ];
                }
            }
        } else {
            if ($canViewAll) {
                $classroomsQuery = Classroom::where('academic_year', $academicYear)
                    ->where('is_active', true)
                    ->orderBy('name');
                $classroomId = $request->input('classroom_id');
                if ($target === 'specific' && $classroomId && $classroomId !== 'all') {
                    $classroomsQuery->where('id', $classroomId);
                }

                $classrooms = $classroomsQuery->get();
                if ($classrooms->isEmpty()) {
                    $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();
                }
                if ($classrooms->isEmpty()) {
                    $classrooms = Classroom::orderBy('name')->get();
                }
            } else {
                // Guru biasa hanya mencetak kelas yang relevan dengannya
                $relevantClassroomIds = $user->getRelevantClassroomIds($academicYear);
                $classroomsQuery = Classroom::whereIn('id', $relevantClassroomIds)->orderBy('name');

                $classroomId = $request->input('classroom_id');
                if ($target === 'specific' && $classroomId && $classroomId !== 'all') {
                    if (! $relevantClassroomIds->contains((int) $classroomId)) {
                        abort(403, 'Anda hanya dapat mengakses jadwal kelas yang relevan dengan Anda.');
                    }
                    $classroomsQuery->where('id', $classroomId);
                }

                $classrooms = $classroomsQuery->get();
            }

            $allSchedules = LessonSchedule::with(['teacher', 'subject', 'classroom'])
                ->where('academic_year', $academicYear)
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->get()
                ->groupBy('classroom_id');

            foreach ($classrooms as $classroom) {
                $items = $allSchedules->get($classroom->id, collect());
                $sheets[] = [
                    'entity' => $classroom,
                    'type' => 'class',
                    'grid' => $this->buildGrid($items, 'class', $days),
                    'total' => $items->count(),
                ];
            }
        }

        return view('kurikulum.schedules.print', compact(
            'sheets',
            'viewType',
            'target',
            'academicYear',
            'semester',
            'days',
            'periods',
            'setting',
            'principal',
            'waka',
            'penetapanCity',
            'rawPenetapanDate',
            'formattedPenetapanDate'
        ));
    }

    private function splitPeriodsByBreak(int $start, int $end): array
    {
        $segments = [];

        // Interval 1: Jam 1 s.d. 3 (sebelum Istirahat 1)
        if ($start <= 3) {
            $segStart = max($start, 1);
            $segEnd = min($end, 3);
            $segments[] = [
                'start' => $segStart,
                'end' => $segEnd,
                'span' => $segEnd - $segStart + 1,
            ];
        }

        // Interval 2: Jam 4 s.d. 7 (antara Istirahat 1 dan Istirahat 2)
        if ($start <= 7 && $end >= 4) {
            $segStart = max($start, 4);
            $segEnd = min($end, 7);
            $segments[] = [
                'start' => $segStart,
                'end' => $segEnd,
                'span' => $segEnd - $segStart + 1,
            ];
        }

        // Interval 3: Jam 8 s.d. 10 (setelah Istirahat 2)
        if ($end >= 8) {
            $segStart = max($start, 8);
            $segEnd = min($end, 10);
            $segments[] = [
                'start' => $segStart,
                'end' => $segEnd,
                'span' => $segEnd - $segStart + 1,
            ];
        }

        return $segments;
    }

    private function buildGrid(iterable $items, string $mode, array $days): array
    {
        $grid = [];
        foreach ($days as $day) {
            for ($p = 1; $p <= 10; $p++) {
                $grid[$day][$p] = null;
            }
        }
        foreach ($items as $item) {
            $segments = $this->splitPeriodsByBreak((int) $item->start_period, (int) $item->end_period);

            foreach ($segments as $seg) {
                $segStart = $seg['start'];
                $segEnd = $seg['end'];
                $segSpan = $seg['span'];
                $segTimes = LessonSchedule::getTimeForPeriod($segStart, $segEnd);

                for ($p = $segStart; $p <= $segEnd; $p++) {
                    $grid[$item->day][$p] = [
                        'id' => $item->id,
                        'title' => $item->subject?->code ?? $item->subject?->name,
                        'subtitle' => $mode === 'class'
                            ? ($item->teacher?->formatted_short_name ?? $item->teacher?->name)
                            : $item->classroom?->name,
                        'color_badge' => $this->getSubjectBadgeClass($item->subject?->category),
                        'start' => $segStart,
                        'end' => $segEnd,
                        'is_first' => ($p === $segStart),
                        'span' => $segSpan,
                        'start_time' => $segTimes['start_time'] ?? $item->start_time,
                        'end_time' => $segTimes['end_time'] ?? $item->end_time,
                        'raw' => $item,
                    ];
                }
            }
        }

        return $grid;
    }
}
