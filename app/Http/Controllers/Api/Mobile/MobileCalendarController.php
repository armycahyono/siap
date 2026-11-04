<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarUpload;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\AcademicCalendarService;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class MobileCalendarController extends Controller
{
    /**
     * Get monthly calendar data, daily events, and statistics for SIAP Mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        try {
            $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
        } catch (Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        // Seed baseline if needed
        if ($academicYear === '2026/2027' && AcademicCalendarUpload::where('academic_year', '2026/2027')->count() === 0) {
            try {
                AcademicCalendarService::seedDefaultKaldik2026((int) $user->id);
            } catch (Throwable) {
                // Ignore seeding errors in restricted environments
            }
        }

        // Parse requested month (YYYY-MM)
        $monthInput = $request->input('month', now()->format('Y-m'));
        try {
            $monthCarbon = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (Throwable) {
            $monthCarbon = now()->startOfMonth();
        }

        $startOfMonth = $monthCarbon->copy()->startOfMonth();
        $endOfMonth = $monthCarbon->copy()->endOfMonth();

        // 7xN Calendar grid window (from Sunday to Saturday)
        $gridStart = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        // Fetch events covering the grid range
        $events = CurriculumEvent::with(['users'])
            ->where(function ($q) use ($gridStart, $gridEnd) {
                $q->whereBetween('start_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                    ->orWhereBetween('end_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
                    ->orWhere(function ($sub) use ($gridStart, $gridEnd) {
                        $sub->where('start_date', '<=', $gridStart->toDateString())
                            ->where('end_date', '>=', $gridEnd->toDateString());
                    });
            })
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $todayStr = now()->toDateString();
        $categoriesDefs = CurriculumEvent::categoryDefinitions();

        // Map events to date strings
        $eventsByDate = [];
        foreach ($events as $event) {
            $evStart = $event->start_date ? Carbon::parse($event->start_date) : null;
            $evEnd = $event->end_date ? Carbon::parse($event->end_date) : $evStart;

            if (! $evStart) {
                continue;
            }

            $spanPeriod = CarbonPeriod::create(
                $evStart->max($gridStart),
                $evEnd ? $evEnd->min($gridEnd) : $evStart
            );

            $formattedEvent = [
                'id' => $event->id,
                'title' => $event->title,
                'category' => $event->category,
                'category_label' => $event->category_label,
                'category_color' => $event->category_color,
                'category_badge' => $event->category_badge,
                'is_holiday' => (bool) $event->is_holiday,
                'is_all_day' => (bool) $event->is_all_day,
                'start_date' => $event->start_date ? $event->start_date->format('Y-m-d') : null,
                'end_date' => $event->end_date ? $event->end_date->format('Y-m-d') : null,
                'start_time' => $event->start_time ? substr((string) $event->start_time, 0, 5) : null,
                'end_time' => $event->end_time ? substr((string) $event->end_time, 0, 5) : null,
                'location' => $event->location,
                'learning_impact' => $event->learning_impact ?? 'normal',
                'learning_impact_label' => $event->learning_impact_short_label,
                'learning_impact_badge' => $event->learning_impact_badge,
                'teacher_instructions' => $event->teacher_instructions,
                'assigned_teachers' => $event->users->pluck('name')->values()->all(),
            ];

            foreach ($spanPeriod as $dt) {
                $dKey = $dt->toDateString();
                $eventsByDate[$dKey][] = $formattedEvent;
            }
        }

        // Build days matrix
        $gridPeriod = CarbonPeriod::create($gridStart, $gridEnd);
        $days = [];
        $effectiveDaysCount = 0;
        $holidayDaysCount = 0;
        $eventDaysCount = 0;

        $dayNamesIndo = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        foreach ($gridPeriod as $currentDay) {
            $dayStr = $currentDay->toDateString();
            $isCurrentMonth = $currentDay->month === $monthCarbon->month;
            $isSunday = $currentDay->dayOfWeek === Carbon::SUNDAY;
            $dayEvents = $eventsByDate[$dayStr] ?? [];

            $isHoliday = $isSunday;
            $holidayTitle = $isSunday ? 'Hari Libur Akhir Pekan' : null;

            foreach ($dayEvents as $ev) {
                if ($ev['is_holiday']) {
                    $isHoliday = true;
                    $holidayTitle = $ev['title'];
                    break;
                }
            }

            // Stats count for days strictly within the requested month
            if ($isCurrentMonth) {
                if ($isHoliday) {
                    $holidayDaysCount++;
                } else {
                    $effectiveDaysCount++;
                }

                if (! empty($dayEvents)) {
                    $eventDaysCount++;
                }
            }

            // Collect dot colors (up to 3 distinct colors)
            $dotColors = [];
            foreach ($dayEvents as $ev) {
                $color = $ev['category_color'] ?? '#2563eb';
                if (! in_array($color, $dotColors, true)) {
                    $dotColors[] = $color;
                }
                if (count($dotColors) >= 3) {
                    break;
                }
            }

            $days[] = [
                'date' => $dayStr,
                'day_num' => (int) $currentDay->format('j'),
                'day_name' => $dayNamesIndo[$currentDay->dayOfWeek] ?? '',
                'is_current_month' => $isCurrentMonth,
                'is_today' => $dayStr === $todayStr,
                'is_sunday' => $isSunday,
                'is_holiday' => $isHoliday,
                'holiday_title' => $holidayTitle,
                'events_count' => count($dayEvents),
                'events' => $dayEvents,
                'dots' => $dotColors,
            ];
        }

        // Fetch upcoming 15 events starting from today
        $upcomingEvents = CurriculumEvent::with(['users'])
            ->where(function ($q) use ($todayStr) {
                $q->where('start_date', '>=', $todayStr)
                    ->orWhere('end_date', '>=', $todayStr);
            })
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->limit(15)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'category' => $event->category,
                    'category_label' => $event->category_label,
                    'category_color' => $event->category_color,
                    'category_badge' => $event->category_badge,
                    'is_holiday' => (bool) $event->is_holiday,
                    'start_date' => $event->start_date ? $event->start_date->format('Y-m-d') : null,
                    'end_date' => $event->end_date ? $event->end_date->format('Y-m-d') : null,
                    'start_time' => $event->start_time ? substr((string) $event->start_time, 0, 5) : null,
                    'end_time' => $event->end_time ? substr((string) $event->end_time, 0, 5) : null,
                    'location' => $event->location,
                    'learning_impact_label' => $event->learning_impact_short_label,
                    'assigned_teachers' => $event->users->pluck('name')->values()->all(),
                ];
            });

        $monthNamesIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $gradeLevels = GradeLevel::orderBy('level')->get(['id', 'level', 'name']);
        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get(['id', 'name']);
        $learningImpacts = CurriculumEvent::learningImpactDefinitions();
        $periodMap = CurriculumEvent::periodScheduleMap();

        return response()->json([
            'success' => true,
            'academic_year' => $academicYear,
            'month' => $monthCarbon->format('Y-m'),
            'month_name' => ($monthNamesIndo[$monthCarbon->month] ?? '').' '.$monthCarbon->year,
            'prev_month' => $monthCarbon->copy()->subMonth()->format('Y-m'),
            'next_month' => $monthCarbon->copy()->addMonth()->format('Y-m'),
            'stats' => [
                'effective_days' => $effectiveDaysCount,
                'holiday_days' => $holidayDaysCount,
                'event_days' => $eventDaysCount,
                'total_events' => $events->count(),
            ],
            'days' => $days,
            'upcoming_events' => $upcomingEvents,
            'categories' => collect($categoriesDefs)->map(function ($cat, $key) {
                return [
                    'key' => $key,
                    'label' => $cat['label'] ?? ucfirst($key),
                    'color' => $cat['color'] ?? '#2563eb',
                    'is_kaldik' => ! empty($cat['is_kaldik']),
                ];
            })->values(),
            'grade_levels' => $gradeLevels,
            'classrooms' => $classrooms,
            'learning_impacts' => $learningImpacts,
            'period_schedule_map' => $periodMap,
            'can_create' => (bool) $this->canManageCalendar($user),
        ]);
    }

    /**
     * Cek apakah pengguna memiliki hak akses mengelola agenda kalender sekolah.
     * Mencakup: Super Admin, Kepala Sekolah (KS), Seluruh Waka, dan Operator Waka.
     */
    public function canManageCalendar(User $user): bool
    {
        if ($user->isSuperAdmin()
            || $user->isPrincipal()
            || $user->isVicePrincipal()
            || $user->isOperator()
            || $user->isWakaKurikulum()
            || $user->isWakaKesiswaan()
            || $user->isWakaSarpras()
            || $user->isWakaHumas()
            || $user->isOperatorKurikulum()
            || $user->isOperatorKesiswaan()
            || $user->isOperatorSarpras()
            || $user->isOperatorHumas()
            || $user->canViewAllSchedules()
        ) {
            return true;
        }

        $roleSlug = $user->role?->slug ?? '';
        if (in_array($roleSlug, ['super_admin', 'adm2', 'kepala_sekolah', 'waka_kurikulum', 'waka_kesiswaan', 'waka_sarpras', 'waka_humas', 'operator_kurikulum', 'operator_kesiswaan', 'operator_sarpras', 'operator_humas', 'operator'], true)) {
            return true;
        }

        $structCodes = $user->structuralPositions->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();
        foreach (['KS', 'WAKA_KUR', 'WAKA_SARPRAS', 'WAKA_KESISWAAN', 'WAKA_HUMAS', 'OPERATOR'] as $reqCode) {
            if (in_array($reqCode, $structCodes, true)) {
                return true;
            }
        }

        $pos = strtolower($user->position ?? '');
        if (str_contains($pos, 'waka') || str_contains($pos, 'kepala') || str_contains($pos, 'operator') || str_contains($pos, 'admin')) {
            return true;
        }

        return false;
    }

    /**
     * Store new calendar event from SIAP Mobile (for authorized managers).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $this->canManageCalendar($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan agenda kalender.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_all_day' => 'nullable|boolean',
            'is_holiday' => 'nullable|boolean',
            'start_time' => 'nullable|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'target_audience' => 'nullable|string|in:semua,guru_staf,tingkat,rombel',
            'target_grade_levels' => 'nullable|array',
            'target_classroom_ids' => 'nullable|array',
            'learning_impact' => 'nullable|string|in:normal,pulang_cepat,pembelajaran_daring,bebas_kbm_kegiatan,libur_khusus',
            'max_period' => 'nullable|integer|min:1|max:12',
            'dismissal_time' => 'nullable|string|max:10',
        ]);

        try {
            $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
        } catch (Throwable) {
            $academicYear = SchoolSetting::getMasterYear();
        }

        $isAllDay = $request->boolean('is_all_day', true);
        $isHoliday = $request->boolean('is_holiday', false);

        if (in_array($validated['category'], ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'], true)) {
            $isHoliday = true;
        }

        $learningImpact = $validated['learning_impact'] ?? ($isHoliday ? 'libur_khusus' : 'normal');
        if ($isHoliday && $learningImpact === 'normal') {
            $learningImpact = 'libur_khusus';
        }

        $targetAudience = $validated['target_audience'] ?? 'semua';
        $targetGradeLevels = ($targetAudience === 'tingkat') ? ($validated['target_grade_levels'] ?? null) : null;
        $targetClassroomIds = ($targetAudience === 'rombel') ? ($validated['target_classroom_ids'] ?? null) : null;

        $maxPeriod = ($learningImpact === 'pulang_cepat') ? ($validated['max_period'] ?? null) : null;
        $dismissalTime = $validated['dismissal_time'] ?? null;
        if ($maxPeriod && empty($dismissalTime)) {
            $periodMap = CurriculumEvent::periodScheduleMap();
            $dismissalTime = $periodMap[$maxPeriod]['time'] ?? null;
        }

        $event = CurriculumEvent::create([
            'academic_year' => $academicYear,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? $validated['start_date'],
            'is_all_day' => $isAllDay,
            'is_holiday' => $isHoliday,
            'start_time' => $isAllDay ? null : ($validated['start_time'] ?? null),
            'end_time' => $isAllDay ? null : ($validated['end_time'] ?? null),
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'target_audience' => $targetAudience,
            'target_grade_levels' => $targetGradeLevels,
            'target_classroom_ids' => $targetClassroomIds,
            'learning_impact' => $learningImpact,
            'max_period' => $maxPeriod,
            'dismissal_time' => $dismissalTime,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil ditambahkan ke kalender.',
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date ? $event->start_date->format('Y-m-d') : null,
                'category' => $event->category,
                'category_label' => $event->category_label,
            ],
        ], 201);
    }
}
