<?php

namespace App\Http\Controllers;

use App\Models\AcademicCalendarUpload;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GradeLevel;
use App\Models\SchoolActivity;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AcademicCalendarService;
use App\Services\DailyKbmSnapshotService;
use App\Services\DeviceDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CurriculumCalendarController extends Controller
{
    public function index(Request $request)
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        // Seed baseline Kaldik for 2026/2027 if none exists
        if (AcademicCalendarUpload::where('academic_year', '2026/2027')->count() === 0) {
            try {
                AcademicCalendarService::seedDefaultKaldik2026((int) auth()->id());
            } catch (Throwable $e) {
                // Ignore seeding errors in environments without filesystem access
            }
        }

        $activeUpload = AcademicCalendarUpload::where('academic_year', $academicYear)
            ->where('is_active', true)
            ->latest()
            ->first();

        $allUploads = AcademicCalendarUpload::where('academic_year', $academicYear)
            ->with('uploader')
            ->latest()
            ->get();

        $allGtk = User::operationalPersonnel()->orderBy('name')->get();
        $teachers = $allGtk->filter(fn ($u) => ! $u->isStaff())->values();
        $staffMembers = $allGtk->filter(fn ($u) => $u->isStaff())->values();
        $periodMap = CurriculumEvent::periodScheduleMap();

        $categories = CurriculumEvent::categoryDefinitions();

        // Optimized: Single query for all events of this academic year
        $yearEvents = CurriculumEvent::where('academic_year', $academicYear)->get();
        $hasSupervisions = Supervision::where('academic_year', $academicYear)->whereNotNull('schedule_date')->exists();

        // Statistics for current academic year (calculated from preloaded events)
        $totalEvents = $yearEvents->count();
        $totalHolidays = $yearEvents->where('is_holiday', true)->count();
        $totalTasks = $yearEvents->where('category', 'tugas_guru')->count();
        $totalCurriculumAgendas = $yearEvents->where('category', 'kegiatan_kurikulum')->count();

        // Master Activities Data (evaluated in memory without N+1 queries)
        $schoolActivities = SchoolActivity::where('is_active', true)->orderBy('order_index')->orderBy('name')->get();
        $unscheduledActivitiesCount = $schoolActivities->filter(function ($act) use ($yearEvents, $hasSupervisions) {
            if ($act->category === 'supervisi' || strtolower((string) $act->code) === 'spv') {
                if ($hasSupervisions) {
                    return false;
                }
            }

            if ($yearEvents->contains('school_activity_id', $act->id)) {
                return false;
            }

            $name = mb_strtolower(trim($act->name));
            $code = mb_strtolower(trim((string) $act->code));

            return ! $yearEvents->contains(function ($ev) use ($name, $code) {
                $evTitle = mb_strtolower($ev->title);

                return ($name !== '' && str_contains($evTitle, $name)) || ($code !== '' && str_contains($evTitle, $code));
            });
        })->count();

        // Master Tingkat & Rombel Kelas untuk Target Kegiatan
        $gradeLevels = GradeLevel::orderBy('level')->get();
        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();
        $learningImpacts = CurriculumEvent::learningImpactDefinitions();

        if (DeviceDetector::isMobile($request)) {
            $yearEvents->load(['users', 'schoolActivity']);

            $eventsJson = $yearEvents->map(function ($ev) {
                return [
                    'id' => $ev->id,
                    'title' => $ev->title,
                    'description' => $ev->description,
                    'category' => $ev->category,
                    'category_label' => $ev->category_label,
                    'category_badge' => $ev->category_badge,
                    'category_color' => $ev->category_color,
                    'start_date' => $ev->start_date ? $ev->start_date->format('Y-m-d') : null,
                    'end_date' => $ev->end_date ? $ev->end_date->format('Y-m-d') : ($ev->start_date ? $ev->start_date->format('Y-m-d') : null),
                    'is_all_day' => (bool) $ev->is_all_day,
                    'is_holiday' => (bool) $ev->is_holiday,
                    'start_time' => $ev->start_time ? substr($ev->start_time, 0, 5) : null,
                    'end_time' => $ev->end_time ? substr($ev->end_time, 0, 5) : null,
                    'location' => $ev->location,
                    'learning_impact' => $ev->learning_impact ?? 'normal',
                    'learning_impact_label' => $ev->learning_impact_short_label,
                    'learning_impact_badge' => $ev->learning_impact_badge,
                    'teacher_instructions' => $ev->teacher_instructions,
                    'assigned_teachers' => $ev->users->pluck('name')->values()->all(),
                ];
            })->values();

            return view('mobile.calendar.index', compact(
                'academicYear',
                'setting',
                'activeUpload',
                'allUploads',
                'yearEvents',
                'eventsJson',
                'categories',
                'learningImpacts',
                'totalEvents',
                'totalHolidays',
                'totalTasks',
                'totalCurriculumAgendas',
                'gradeLevels',
                'classrooms',
                'allGtk',
                'teachers',
                'staffMembers',
                'periodMap'
            ));
        }

        return view('kurikulum.calendar.index', compact(
            'academicYear',
            'setting',
            'activeUpload',
            'allUploads',
            'teachers',
            'categories',
            'totalEvents',
            'totalHolidays',
            'totalTasks',
            'totalCurriculumAgendas',
            'schoolActivities',
            'unscheduledActivitiesCount',
            'gradeLevels',
            'classrooms',
            'learningImpacts',
            'allGtk',
            'staffMembers',
            'periodMap'
        ));
    }

    /**
     * JSON Events feed for FullCalendar.
     */
    public function events(Request $request): JsonResponse
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        $query = CurriculumEvent::with(['users', 'creator'])
            ->where('academic_year', $academicYear);

        // Filter by category
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $categories = explode(',', $request->input('category'));
            $query->whereIn('category', $categories);
        }

        // Filter by teacher
        if ($request->filled('teacher_id') && $request->input('teacher_id') !== 'all') {
            $teacherId = $request->input('teacher_id');
            $query->whereHas('users', function ($q) use ($teacherId) {
                $q->where('users.id', $teacherId);
            });
        }

        // Filter my events
        if ($request->boolean('my_events') && auth()->check()) {
            $currentUserId = auth()->id();
            $query->where(function ($q) use ($currentUserId) {
                $q->whereHas('users', fn ($uq) => $uq->where('users.id', $currentUserId))
                    ->orWhere('created_by', $currentUserId);
            });
        }

        $events = $query->get();

        $formatted = [];

        foreach ($events as $event) {
            // FullCalendar end date for multi-day allDay events is exclusive (+1 day)
            $endDate = $event->end_date;
            if ($event->is_all_day && $endDate && $endDate->gt($event->start_date)) {
                $fcEnd = $endDate->copy()->addDay()->format('Y-m-d');
            } elseif ($endDate) {
                $fcEnd = $endDate->format('Y-m-d');
            } else {
                $fcEnd = null;
            }

            $start = $event->is_all_day
                ? $event->start_date->format('Y-m-d')
                : ($event->start_date->format('Y-m-d').($event->start_time ? 'T'.$event->start_time : ''));

            $end = $event->is_all_day
                ? $fcEnd
                : ($fcEnd ? ($fcEnd.($event->end_time ? 'T'.$event->end_time : '')) : null);

            $formatted[] = [
                'id' => 'evt_'.$event->id,
                'db_id' => $event->id,
                'title' => $event->title,
                'start' => $start,
                'end' => $end,
                'allDay' => $event->is_all_day,
                'backgroundColor' => $event->category_color,
                'borderColor' => $event->category_color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source_type' => 'curriculum_event',
                    'school_activity_id' => $event->school_activity_id,
                    'category' => $event->category,
                    'category_label' => $event->category_label,
                    'category_badge' => $event->category_badge,
                    'description' => $event->description,
                    'location' => $event->location,
                    'is_holiday' => $event->is_holiday,
                    'target_audience' => $event->target_audience ?? 'semua',
                    'target_grade_levels' => $event->target_grade_levels,
                    'target_classroom_ids' => $event->target_classroom_ids,
                    'learning_impact' => $event->learning_impact ?? 'normal',
                    'learning_impact_label' => $event->learning_impact_label,
                    'learning_impact_badge' => $event->learning_impact_badge,
                    'max_period' => $event->max_period,
                    'dismissal_time' => $event->dismissal_time,
                    'teacher_instructions' => $event->teacher_instructions,
                    'teacher_impact' => $event->teacher_impact ?? 'normal',
                    'teacher_impact_label' => $event->teacher_impact_label,
                    'teacher_impact_badge' => $event->teacher_impact_badge,
                    'gtk_checkin_time' => $event->gtk_checkin_time,
                    'gtk_checkout_time' => $event->gtk_checkout_time,
                    'student_start_time' => $event->student_start_time,
                    'student_end_time' => $event->student_end_time,
                    'disengaged_teacher_impact' => $event->disengaged_teacher_impact ?? 'masuk_normal',
                    'disengaged_teacher_impact_label' => $event->disengaged_teacher_impact_label,
                    'disengaged_teacher_max_period' => $event->disengaged_teacher_max_period,
                    'disengaged_teacher_dismissal_time' => $event->disengaged_teacher_dismissal_time,
                    'disengaged_staff_impact' => $event->disengaged_staff_impact ?? 'masuk_normal',
                    'disengaged_staff_impact_label' => $event->disengaged_staff_impact_label,
                    'disengaged_staff_dismissal_time' => $event->disengaged_staff_dismissal_time,
                    'disengaged_student_impact' => $event->disengaged_student_impact ?? 'kbm_normal',
                    'disengaged_student_impact_label' => $event->disengaged_student_impact_label,
                    'disengaged_student_max_period' => $event->disengaged_student_max_period,
                    'disengaged_student_dismissal_time' => $event->disengaged_student_dismissal_time,
                    'start_date_formatted' => $event->start_date->translatedFormat('d F Y'),
                    'end_date_formatted' => $event->end_date ? $event->end_date->translatedFormat('d F Y') : null,
                    'time_formatted' => ($event->start_time ? $event->start_time.($event->end_time ? ' - '.$event->end_time : '') : 'Sepanjang Hari'),
                    'users' => $event->users->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'short_name' => $u->formatted_short_name,
                        'role' => $u->pivot->role ?? 'assignee',
                    ]),
                    'can_edit' => auth()->user()?->hasRole(['super_admin', 'waka_kurikulum']) || $event->created_by === auth()->id(),
                ],
            ];
        }

        // Include Supervisions in the calendar if category allows
        $categoryParam = $request->input('category', 'all');
        if ($categoryParam === 'all' || in_array('supervisi', explode(',', $categoryParam))) {
            $supervisionsQuery = Supervision::with(['teacher', 'supervisor'])
                ->where('academic_year', $academicYear)
                ->whereNotNull('schedule_date');

            if ($request->filled('teacher_id') && $request->input('teacher_id') !== 'all') {
                $supervisionsQuery->where('teacher_id', $request->input('teacher_id'));
            }

            if ($request->boolean('my_events') && auth()->check()) {
                $curId = auth()->id();
                $supervisionsQuery->where(function ($sq) use ($curId) {
                    $sq->where('teacher_id', $curId)->orWhere('supervisor_id', $curId);
                });
            }

            foreach ($supervisionsQuery->get() as $spv) {
                $spvStart = $spv->schedule_date->format('Y-m-d');
                $timeStr = (string) $spv->schedule_time;
                $spvStartTime = '08:00:00';
                $spvEndTime = null;

                if (preg_match('/^(\d{1,2})[:.](\d{2})/', trim($timeStr), $sMatches)) {
                    $spvStartTime = sprintf('%02d:%02d:00', (int) $sMatches[1], (int) $sMatches[2]);
                }

                if (preg_match('/(?:[-–]|s\/d|\bhingga\b)\s*(\d{1,2})[:.](\d{2})/i', $timeStr, $eMatches)) {
                    $spvEndTime = sprintf('%02d:%02d:00', (int) $eMatches[1], (int) $eMatches[2]);
                }

                $formatted[] = [
                    'id' => 'spv_'.$spv->id,
                    'db_id' => $spv->id,
                    'title' => 'Supervisi: '.($spv->teacher?->name ?? 'Guru').' ('.$spv->subject.')',
                    'start' => $spvStart.'T'.$spvStartTime,
                    'end' => $spvEndTime ? ($spvStart.'T'.$spvEndTime) : null,
                    'allDay' => false,
                    'backgroundColor' => '#4f46e5',
                    'borderColor' => '#4338ca',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'source_type' => 'supervision',
                        'category' => 'supervisi',
                        'category_label' => 'Supervisi Guru',
                        'category_badge' => 'bg-indigo text-white',
                        'description' => 'Supervisi Akademik: Guru '.$spv->teacher?->name.' oleh Supervisor '.($spv->supervisor?->name ?? '-'),
                        'location' => 'Ruang Kelas / Laboratorium',
                        'is_holiday' => false,
                        'start_date_formatted' => $spv->schedule_date->translatedFormat('d F Y'),
                        'time_formatted' => $spv->schedule_time ?: '08:00 WIB',
                        'users' => [
                            [
                                'id' => $spv->teacher_id,
                                'name' => $spv->teacher?->name,
                                'short_name' => $spv->teacher?->formatted_short_name,
                                'role' => 'Guru Yang Disupervisi',
                            ],
                            [
                                'id' => $spv->supervisor_id,
                                'name' => $spv->supervisor?->name,
                                'short_name' => $spv->supervisor?->formatted_short_name,
                                'role' => 'Supervisor',
                            ],
                        ],
                        'can_edit' => false,
                        'supervision_url' => route('supervisions.show', $spv->id),
                    ],
                ];
            }
        }

        return response()->json($formatted);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_activity_id' => 'nullable|exists:school_activities,id',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'is_all_day' => 'nullable|boolean',
            'is_holiday' => 'nullable|boolean',
            'target_audience' => 'nullable|string|in:semua,tingkat,rombel,guru_staf',
            'target_grade_levels' => 'nullable|array',
            'target_classroom_ids' => 'nullable|array',
            'learning_impact' => 'nullable|string|in:normal,pulang_cepat,pembelajaran_daring,bebas_kbm_kegiatan,libur_khusus',
            'max_period' => 'nullable|integer|min:1|max:12',
            'dismissal_time' => 'nullable|string|max:10',
            'teacher_instructions' => 'nullable|string',
            'teacher_impact' => 'nullable|string|in:normal,bebas_mengajar_tugas,diliburkan_penuh',
            'gtk_checkin_time' => 'nullable|string|max:10',
            'gtk_checkout_time' => 'nullable|string|max:10',
            'student_start_time' => 'nullable|string|max:10',
            'student_end_time' => 'nullable|string|max:10',
            'disengaged_teacher_impact' => 'nullable|string|in:masuk_normal,pulang_cepat,libur',
            'disengaged_teacher_max_period' => 'nullable|integer|min:1|max:10',
            'disengaged_teacher_dismissal_time' => 'nullable|string|max:10',
            'disengaged_staff_impact' => 'nullable|string|in:masuk_normal,pulang_cepat,libur',
            'disengaged_staff_dismissal_time' => 'nullable|string|max:10',
            'disengaged_student_impact' => 'nullable|string|in:kbm_normal,pembelajaran_daring,pulang_cepat,bebas_kbm,libur_khusus',
            'disengaged_student_max_period' => 'nullable|integer|min:1|max:10',
            'disengaged_student_dismissal_time' => 'nullable|string|max:10',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'academic_year' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $validated['academic_year'] ?? session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027'));

        $isAllDay = $request->boolean('is_all_day', true);
        $isHoliday = $request->boolean('is_holiday', false);

        // If category is a known holiday category, enforce is_holiday
        if (in_array($validated['category'], ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'])) {
            $isHoliday = true;
        }

        $learningImpact = $validated['learning_impact'] ?? 'normal';
        if ($isHoliday && $learningImpact === 'normal') {
            $learningImpact = 'libur_khusus';
        }

        // Auto-fill disengaged dismissal time if period selected
        $periodMap = CurriculumEvent::periodScheduleMap();
        $teacherDismissalTime = $validated['disengaged_teacher_dismissal_time'] ?? null;
        if (! empty($validated['disengaged_teacher_max_period']) && isset($periodMap[$validated['disengaged_teacher_max_period']])) {
            $teacherDismissalTime = $teacherDismissalTime ?: $periodMap[$validated['disengaged_teacher_max_period']]['time'];
        }

        $studentDismissalTime = $validated['disengaged_student_dismissal_time'] ?? null;
        if (! empty($validated['disengaged_student_max_period']) && isset($periodMap[$validated['disengaged_student_max_period']])) {
            $studentDismissalTime = $studentDismissalTime ?: $periodMap[$validated['disengaged_student_max_period']]['time'];
        }

        $targetAudience = $validated['target_audience'] ?? 'guru_staf';
        $isNoStudents = ($targetAudience === 'guru_staf');

        $eventData = [
            'title' => $validated['title'],
            'category' => $validated['category'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?: $validated['start_date'],
            'start_time' => $isAllDay ? null : ($validated['start_time'] ?? null),
            'end_time' => $isAllDay ? null : ($validated['end_time'] ?? null),
            'is_all_day' => $isAllDay,
            'is_holiday' => $isHoliday,
            'target_audience' => $targetAudience,
            'target_grade_levels' => $isNoStudents ? null : ($validated['target_grade_levels'] ?? null),
            'target_classroom_ids' => $isNoStudents ? null : ($validated['target_classroom_ids'] ?? null),
            'learning_impact' => $isNoStudents ? 'normal' : $learningImpact,
            'max_period' => $validated['max_period'] ?? null,
            'dismissal_time' => $validated['dismissal_time'] ?? null,
            'teacher_instructions' => $validated['teacher_instructions'] ?? null,
            'teacher_impact' => $validated['teacher_impact'] ?? 'normal',
            'gtk_checkin_time' => $validated['gtk_checkin_time'] ?? null,
            'gtk_checkout_time' => $validated['gtk_checkout_time'] ?? null,
            'student_start_time' => $isNoStudents ? null : ($validated['student_start_time'] ?? null),
            'student_end_time' => $isNoStudents ? null : ($validated['student_end_time'] ?? null),
            'disengaged_teacher_impact' => $validated['disengaged_teacher_impact'] ?? 'masuk_normal',
            'disengaged_teacher_max_period' => $validated['disengaged_teacher_max_period'] ?? null,
            'disengaged_teacher_dismissal_time' => $teacherDismissalTime,
            'disengaged_staff_impact' => $validated['disengaged_staff_impact'] ?? 'masuk_normal',
            'disengaged_staff_dismissal_time' => $validated['disengaged_staff_dismissal_time'] ?? null,
            'disengaged_student_impact' => $validated['disengaged_student_impact'] ?? 'kbm_normal',
            'disengaged_student_max_period' => $validated['disengaged_student_max_period'] ?? null,
            'disengaged_student_dismissal_time' => $studentDismissalTime,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
        ];

        $event = null;
        if (! empty($validated['school_activity_id'])) {
            $event = CurriculumEvent::where('school_activity_id', $validated['school_activity_id'])
                ->where('academic_year', $academicYear)
                ->first();
        }

        if ($event) {
            $event->update($eventData);
        } else {
            $event = CurriculumEvent::create(array_merge($eventData, [
                'academic_year' => $academicYear,
                'school_activity_id' => $validated['school_activity_id'] ?? null,
                'created_by' => auth()->id(),
            ]));
        }

        if (! empty($validated['user_ids'])) {
            $event->users()->sync($validated['user_ids']);
        }

        // Invalidate cache snapshot KBM pada rentang tanggal kegiatan
        try {
            app(DailyKbmSnapshotService::class)->invalidateSnapshotRange($event->start_date, $event->end_date);
        } catch (Throwable $t) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan sekolah berhasil ditambahkan ke kalender.',
            'event' => $event->load('users'),
        ]);
    }

    public function update(Request $request, CurriculumEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'school_activity_id' => 'nullable|exists:school_activities,id',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'is_all_day' => 'nullable|boolean',
            'is_holiday' => 'nullable|boolean',
            'target_audience' => 'nullable|string|in:semua,tingkat,rombel,guru_staf',
            'target_grade_levels' => 'nullable|array',
            'target_classroom_ids' => 'nullable|array',
            'learning_impact' => 'nullable|string|in:normal,pulang_cepat,pembelajaran_daring,bebas_kbm_kegiatan,libur_khusus',
            'max_period' => 'nullable|integer|min:1|max:12',
            'dismissal_time' => 'nullable|string|max:10',
            'teacher_instructions' => 'nullable|string',
            'teacher_impact' => 'nullable|string|in:normal,bebas_mengajar_tugas,diliburkan_penuh',
            'gtk_checkin_time' => 'nullable|string|max:10',
            'gtk_checkout_time' => 'nullable|string|max:10',
            'student_start_time' => 'nullable|string|max:10',
            'student_end_time' => 'nullable|string|max:10',
            'disengaged_teacher_impact' => 'nullable|string|in:masuk_normal,pulang_cepat,libur',
            'disengaged_teacher_max_period' => 'nullable|integer|min:1|max:10',
            'disengaged_teacher_dismissal_time' => 'nullable|string|max:10',
            'disengaged_staff_impact' => 'nullable|string|in:masuk_normal,pulang_cepat,libur',
            'disengaged_staff_dismissal_time' => 'nullable|string|max:10',
            'disengaged_student_impact' => 'nullable|string|in:kbm_normal,pembelajaran_daring,pulang_cepat,bebas_kbm,libur_khusus',
            'disengaged_student_max_period' => 'nullable|integer|min:1|max:10',
            'disengaged_student_dismissal_time' => 'nullable|string|max:10',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $isAllDay = $request->boolean('is_all_day', true);
        $isHoliday = $request->boolean('is_holiday', false);

        if (in_array($validated['category'], ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'])) {
            $isHoliday = true;
        }

        $learningImpact = $validated['learning_impact'] ?? 'normal';
        if ($isHoliday && $learningImpact === 'normal') {
            $learningImpact = 'libur_khusus';
        }

        $periodMap = CurriculumEvent::periodScheduleMap();
        $teacherDismissalTime = $validated['disengaged_teacher_dismissal_time'] ?? null;
        if (! empty($validated['disengaged_teacher_max_period']) && isset($periodMap[$validated['disengaged_teacher_max_period']])) {
            $teacherDismissalTime = $teacherDismissalTime ?: $periodMap[$validated['disengaged_teacher_max_period']]['time'];
        }

        $studentDismissalTime = $validated['disengaged_student_dismissal_time'] ?? null;
        if (! empty($validated['disengaged_student_max_period']) && isset($periodMap[$validated['disengaged_student_max_period']])) {
            $studentDismissalTime = $studentDismissalTime ?: $periodMap[$validated['disengaged_student_max_period']]['time'];
        }

        $targetAudience = $validated['target_audience'] ?? 'guru_staf';
        $isNoStudents = ($targetAudience === 'guru_staf');

        $event->update([
            'school_activity_id' => $validated['school_activity_id'] ?? $event->school_activity_id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?: $validated['start_date'],
            'start_time' => $isAllDay ? null : ($validated['start_time'] ?? null),
            'end_time' => $isAllDay ? null : ($validated['end_time'] ?? null),
            'is_all_day' => $isAllDay,
            'is_holiday' => $isHoliday,
            'target_audience' => $targetAudience,
            'target_grade_levels' => $isNoStudents ? null : ($validated['target_grade_levels'] ?? null),
            'target_classroom_ids' => $isNoStudents ? null : ($validated['target_classroom_ids'] ?? null),
            'learning_impact' => $isNoStudents ? 'normal' : $learningImpact,
            'max_period' => $validated['max_period'] ?? null,
            'dismissal_time' => $validated['dismissal_time'] ?? null,
            'teacher_instructions' => $validated['teacher_instructions'] ?? null,
            'teacher_impact' => $validated['teacher_impact'] ?? 'normal',
            'gtk_checkin_time' => $validated['gtk_checkin_time'] ?? null,
            'gtk_checkout_time' => $validated['gtk_checkout_time'] ?? null,
            'student_start_time' => $isNoStudents ? null : ($validated['student_start_time'] ?? null),
            'student_end_time' => $isNoStudents ? null : ($validated['student_end_time'] ?? null),
            'disengaged_teacher_impact' => $validated['disengaged_teacher_impact'] ?? 'masuk_normal',
            'disengaged_teacher_max_period' => $validated['disengaged_teacher_max_period'] ?? null,
            'disengaged_teacher_dismissal_time' => $teacherDismissalTime,
            'disengaged_staff_impact' => $validated['disengaged_staff_impact'] ?? 'masuk_normal',
            'disengaged_staff_dismissal_time' => $validated['disengaged_staff_dismissal_time'] ?? null,
            'disengaged_student_impact' => $validated['disengaged_student_impact'] ?? 'kbm_normal',
            'disengaged_student_max_period' => $validated['disengaged_student_max_period'] ?? null,
            'disengaged_student_dismissal_time' => $studentDismissalTime,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        if (isset($validated['user_ids'])) {
            $event->users()->sync($validated['user_ids']);
        }

        // Invalidate cache snapshot KBM pada rentang tanggal kegiatan
        try {
            app(DailyKbmSnapshotService::class)->invalidateSnapshotRange($event->start_date, $event->end_date);
        } catch (Throwable $t) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan kurikulum berhasil diperbarui.',
            'event' => $event->load('users'),
        ]);
    }

    public function destroy(Request $request, CurriculumEvent $event)
    {
        $startDate = $event->start_date;
        $endDate = $event->end_date;

        $event->users()->detach();
        $event->delete();

        // Invalidate cache snapshot KBM pada rentang tanggal kegiatan yang dihapus
        try {
            app(DailyKbmSnapshotService::class)->invalidateSnapshotRange($startDate, $endDate);
        } catch (Throwable $t) {
        }

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', 'Jadwal kegiatan kurikulum berhasil dihapus.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan kurikulum berhasil dihapus.',
        ]);
    }

    /**
     * Upload & Sync Kalender Pendidikan (PDF / Excel).
     */
    public function upload(Request $request)
    {
        abort_unless(auth()->user()->isWakaKurikulum(), 403, 'Hanya Waka Kurikulum yang berhak mengunggah Kalender Pendidikan.');

        $request->validate([
            'kaldik_file' => 'required|mimes:pdf,xlsx,xls|max:15360',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'academic_year' => 'nullable|string',
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        $file = $request->file('kaldik_file');
        $originalFilename = $file->getClientOriginalName();
        $title = $request->input('title') ?: ('Kalender Pendidikan TP '.$academicYear.' ('.pathinfo($originalFilename, PATHINFO_FILENAME).')');

        $path = $file->store('academic_calendars', 'public');

        // Deactivate previous uploads for this academic year
        AcademicCalendarUpload::where('academic_year', $academicYear)->update(['is_active' => false]);

        $upload = AcademicCalendarUpload::create([
            'academic_year' => $academicYear,
            'title' => $title,
            'original_filename' => $originalFilename,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'notes' => $request->input('notes'),
            'total_events' => 0,
            'is_active' => true,
        ]);

        // Auto-seed baseline events from Kaldik if this is 2026/2027
        if ($academicYear === '2026/2027' && CurriculumEvent::where('academic_year', $academicYear)->count() === 0) {
            AcademicCalendarService::generateDefaultEventsFor2026($upload);
        }

        return back()->with('success', "Dokumen Kalender Pendidikan ({$originalFilename}) berhasil diunggah dan terpasang sebagai acuan resmi TP {$academicYear}.");
    }

    /**
     * Download original Kaldik file.
     */
    public function downloadKaldik(AcademicCalendarUpload $upload)
    {
        if (! Storage::disk('public')->exists($upload->file_path)) {
            abort(404, 'File Kalender Pendidikan tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('public')->download($upload->file_path, $upload->original_filename);
    }

    /**
     * Preview inline PDF Kaldik.
     */
    public function previewKaldik(AcademicCalendarUpload $upload)
    {
        if (! Storage::disk('public')->exists($upload->file_path)) {
            abort(404, 'File Kalender Pendidikan tidak ditemukan di server.');
        }

        return Storage::disk('public')->response($upload->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$upload->original_filename.'"',
        ]);
    }
}
