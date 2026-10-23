<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GradeLevel;
use App\Models\SchoolActivity;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SchoolActivityController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        $search = $request->input('search');
        $category = $request->input('category');
        $statusFilter = $request->input('status', 'all'); // 'all', 'terjadwal', 'belum_terjadwal'

        $categories = SchoolActivity::categoryDefinitions();

        $query = SchoolActivity::query()->orderBy('order_index')->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('target_participant', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $allActivities = $query->get();

        // Optimized: pre-load events with assigned users for active academic year
        $events = CurriculumEvent::with('users')->where('academic_year', $academicYear)->get();
        $totalSupervisions = Supervision::where('academic_year', $academicYear)
            ->whereNotNull('schedule_date')
            ->count();

        // Check scheduling status for active academic year
        $scheduledCount = 0;
        $unscheduledCount = 0;

        $items = $allActivities->map(function ($activity) use ($events, $totalSupervisions, &$scheduledCount, &$unscheduledCount) {
            // Find event linked directly or by title/code
            $event = $events->firstWhere('school_activity_id', $activity->id);
            if (! $event) {
                $name = mb_strtolower(trim($activity->name));
                $code = mb_strtolower(trim((string) $activity->code));
                $event = $events->first(function ($ev) use ($name, $code) {
                    $evTitle = mb_strtolower($ev->title);
                    if ($name !== '' && str_contains($evTitle, $name)) {
                        return true;
                    }

                    return $code !== '' && str_contains($evTitle, $code);
                });
            }

            $hasSupervisions = false;
            $supervisionCount = 0;

            if ($activity->category === 'supervisi' || strtolower((string) $activity->code) === 'spv') {
                $supervisionCount = $totalSupervisions;
                $hasSupervisions = $supervisionCount > 0;
            }

            $isScheduled = ($event !== null) || $hasSupervisions;

            if ($isScheduled) {
                $scheduledCount++;
            } else {
                $unscheduledCount++;
            }

            $activity->is_scheduled = $isScheduled;
            $activity->scheduled_event = $event;
            $activity->supervision_count = $supervisionCount;

            return $activity;
        });

        // Filter by status if requested
        if ($statusFilter === 'terjadwal') {
            $items = $items->filter(fn ($item) => $item->is_scheduled)->values();
        } elseif ($statusFilter === 'belum_terjadwal') {
            $items = $items->filter(fn ($item) => ! $item->is_scheduled)->values();
        }

        $totalActivities = $allActivities->count();
        $percentageScheduled = $totalActivities > 0 ? round(($scheduledCount / $totalActivities) * 100) : 0;

        // Master resources for the comprehensive activity/event form
        $gradeLevels = GradeLevel::orderBy('level')->get();
        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::orderBy('name')->get();
        }
        $allGtk = User::operationalPersonnel()->orderBy('name')->get();
        $teachers = $allGtk->filter(fn ($u) => ! $u->isStaff())->values();
        $staffMembers = $allGtk->filter(fn ($u) => $u->isStaff())->values();
        $periodMap = CurriculumEvent::periodScheduleMap();
        $eventCategories = CurriculumEvent::categoryDefinitions();
        $learningImpacts = CurriculumEvent::learningImpactDefinitions();

        return view('kurikulum.activities.index', compact(
            'items',
            'categories',
            'eventCategories',
            'academicYear',
            'search',
            'category',
            'statusFilter',
            'totalActivities',
            'scheduledCount',
            'unscheduledCount',
            'percentageScheduled',
            'setting',
            'gradeLevels',
            'classrooms',
            'allGtk',
            'teachers',
            'staffMembers',
            'periodMap',
            'learningImpacts'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat mengelola kegiatan sekolah.');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_holiday' => ['nullable', 'boolean'],
            'teacher_impact' => ['nullable', 'string', 'max:50'],
            'gtk_checkin_time' => ['nullable', 'string', 'max:10'],
            'gtk_checkout_time' => ['nullable', 'string', 'max:10'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'target_audience' => ['nullable', 'string', 'max:50'],
            'student_start_time' => ['nullable', 'string', 'max:10'],
            'student_end_time' => ['nullable', 'string', 'max:10'],
            'target_grade_levels' => ['nullable', 'array'],
            'target_classroom_ids' => ['nullable', 'array'],
            'learning_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_teacher_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_teacher_max_period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'disengaged_teacher_dismissal_time' => ['nullable', 'string', 'max:10'],
            'disengaged_staff_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_staff_dismissal_time' => ['nullable', 'string', 'max:10'],
            'disengaged_student_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_student_max_period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'disengaged_student_dismissal_time' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string'],
        ]);

        $name = trim((string) ($request->input('name') ?: $request->input('title')));
        if (empty($name)) {
            return back()->withErrors(['name' => 'Nama / Judul kegiatan wajib diisi.'])->withInput();
        }

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        $catDefs = CurriculumEvent::categoryDefinitions();
        $categoryKey = $validated['category'];
        $color = $catDefs[$categoryKey]['color'] ?? '#0284c7';

        $targetAudience = $request->input('target_audience', 'guru_staf');
        $targetParticipantText = match ($targetAudience) {
            'semua' => 'Seluruh Siswa',
            'tingkat' => 'Tingkat '.implode(', ', $request->input('target_grade_levels', [])),
            'rombel' => 'Rombel Pilihan',
            'guru_staf' => 'GTK (Guru & Staf)',
            default => 'GTK & Siswa'
        };

        $durationDays = 1;
        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $durationDays = max(1, Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1);
        }

        $activity = SchoolActivity::create([
            'name' => $name,
            'code' => $validated['code'] ?? null,
            'category' => $categoryKey,
            'target_participant' => $targetParticipantText,
            'description' => $validated['description'] ?? null,
            'color' => $color,
            'default_duration_days' => $durationDays,
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        $redirectParams = $request->filled('academic_year') ? ['academic_year' => $academicYear] : [];

        // Auto schedule to CurriculumEvent if start_date is provided
        if (! empty($validated['start_date'])) {
            $periodMap = CurriculumEvent::periodScheduleMap();
            $teacherDismissalTime = $validated['disengaged_teacher_dismissal_time'] ?? null;
            if (! empty($validated['disengaged_teacher_max_period']) && isset($periodMap[$validated['disengaged_teacher_max_period']])) {
                $teacherDismissalTime = $teacherDismissalTime ?: $periodMap[$validated['disengaged_teacher_max_period']]['time'];
            }

            $studentDismissalTime = $validated['disengaged_student_dismissal_time'] ?? null;
            if (! empty($validated['disengaged_student_max_period']) && isset($periodMap[$validated['disengaged_student_max_period']])) {
                $studentDismissalTime = $studentDismissalTime ?: $periodMap[$validated['disengaged_student_max_period']]['time'];
            }

            $isHoliday = $request->boolean('is_holiday', false);
            if (in_array($categoryKey, ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'])) {
                $isHoliday = true;
            }

            $isNoStudents = ($targetAudience === 'guru_staf');

            $event = CurriculumEvent::create([
                'title' => $name,
                'school_activity_id' => $activity->id,
                'academic_year' => $academicYear,
                'category' => $categoryKey,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?: $validated['start_date'],
                'is_all_day' => true,
                'is_holiday' => $isHoliday,
                'location' => $validated['location'] ?? null,
                'description' => $validated['description'] ?? null,
                'color' => $color,
                'target_audience' => $targetAudience,
                'target_grade_levels' => $isNoStudents ? null : ($validated['target_grade_levels'] ?? null),
                'target_classroom_ids' => $isNoStudents ? null : ($validated['target_classroom_ids'] ?? null),
                'learning_impact' => $isNoStudents ? 'normal' : ($validated['learning_impact'] ?? 'normal'),
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
                'created_by' => auth()->id(),
            ]);

            if (! empty($validated['user_ids'])) {
                $event->users()->sync($validated['user_ids']);
            }

            return redirect()->route('kurikulum.activities.index', $redirectParams)
                ->with('success', "Kegiatan '{$name}' berhasil ditambahkan dan langsung dijadwalkan ke Kalender Kegiatan.");
        }

        return redirect()->route('kurikulum.activities.index', $redirectParams)
            ->with('success', "Kegiatan '{$name}' berhasil ditambahkan ke Kegiatan Sekolah.");
    }

    public function update(Request $request, SchoolActivity $schoolActivity): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat mengelola kegiatan sekolah.');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_holiday' => ['nullable', 'boolean'],
            'teacher_impact' => ['nullable', 'string', 'max:50'],
            'gtk_checkin_time' => ['nullable', 'string', 'max:10'],
            'gtk_checkout_time' => ['nullable', 'string', 'max:10'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'target_audience' => ['nullable', 'string', 'max:50'],
            'student_start_time' => ['nullable', 'string', 'max:10'],
            'student_end_time' => ['nullable', 'string', 'max:10'],
            'target_grade_levels' => ['nullable', 'array'],
            'target_classroom_ids' => ['nullable', 'array'],
            'learning_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_teacher_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_teacher_max_period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'disengaged_teacher_dismissal_time' => ['nullable', 'string', 'max:10'],
            'disengaged_staff_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_staff_dismissal_time' => ['nullable', 'string', 'max:10'],
            'disengaged_student_impact' => ['nullable', 'string', 'max:50'],
            'disengaged_student_max_period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'disengaged_student_dismissal_time' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string'],
        ]);

        $name = trim((string) ($request->input('name') ?: $request->input('title')));
        if (empty($name)) {
            return back()->withErrors(['name' => 'Nama / Judul kegiatan wajib diisi.'])->withInput();
        }

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year', session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027')));

        $catDefs = CurriculumEvent::categoryDefinitions();
        $categoryKey = $validated['category'];
        $color = $catDefs[$categoryKey]['color'] ?? $schoolActivity->color ?? '#0284c7';

        $targetAudience = $request->input('target_audience', 'guru_staf');
        $targetParticipantText = match ($targetAudience) {
            'semua' => 'Seluruh Siswa',
            'tingkat' => 'Tingkat '.implode(', ', $request->input('target_grade_levels', [])),
            'rombel' => 'Rombel Pilihan',
            'guru_staf' => 'GTK (Guru & Staf)',
            default => 'GTK & Siswa'
        };

        $durationDays = $schoolActivity->default_duration_days ?: 1;
        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $durationDays = max(1, Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1);
        }

        $schoolActivity->update([
            'name' => $name,
            'code' => $validated['code'] ?? $schoolActivity->code,
            'category' => $categoryKey,
            'target_participant' => $targetParticipantText,
            'description' => $validated['description'] ?? null,
            'color' => $color,
            'default_duration_days' => $durationDays,
        ]);

        // If start_date is provided, update or create CurriculumEvent
        if (! empty($validated['start_date'])) {
            $periodMap = CurriculumEvent::periodScheduleMap();
            $teacherDismissalTime = $validated['disengaged_teacher_dismissal_time'] ?? null;
            if (! empty($validated['disengaged_teacher_max_period']) && isset($periodMap[$validated['disengaged_teacher_max_period']])) {
                $teacherDismissalTime = $teacherDismissalTime ?: $periodMap[$validated['disengaged_teacher_max_period']]['time'];
            }

            $studentDismissalTime = $validated['disengaged_student_dismissal_time'] ?? null;
            if (! empty($validated['disengaged_student_max_period']) && isset($periodMap[$validated['disengaged_student_max_period']])) {
                $studentDismissalTime = $studentDismissalTime ?: $periodMap[$validated['disengaged_student_max_period']]['time'];
            }

            $isHoliday = $request->boolean('is_holiday', false);
            if (in_array($categoryKey, ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'])) {
                $isHoliday = true;
            }

            $isNoStudents = ($targetAudience === 'guru_staf');

            $event = CurriculumEvent::where('school_activity_id', $schoolActivity->id)
                ->where('academic_year', $academicYear)
                ->first();

            $eventData = [
                'title' => $name,
                'category' => $categoryKey,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?: $validated['start_date'],
                'is_all_day' => true,
                'is_holiday' => $isHoliday,
                'location' => $validated['location'] ?? null,
                'description' => $validated['description'] ?? null,
                'color' => $color,
                'target_audience' => $targetAudience,
                'target_grade_levels' => $isNoStudents ? null : ($validated['target_grade_levels'] ?? null),
                'target_classroom_ids' => $isNoStudents ? null : ($validated['target_classroom_ids'] ?? null),
                'learning_impact' => $isNoStudents ? 'normal' : ($validated['learning_impact'] ?? 'normal'),
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
            ];

            if ($event) {
                $event->update($eventData);
            } else {
                $event = CurriculumEvent::create(array_merge($eventData, [
                    'school_activity_id' => $schoolActivity->id,
                    'academic_year' => $academicYear,
                    'created_by' => auth()->id(),
                ]));
            }

            $event->users()->sync($validated['user_ids'] ?? []);
        }

        $redirectParams = $request->filled('academic_year') ? ['academic_year' => $academicYear] : [];

        return redirect()->route('kurikulum.activities.index', $redirectParams)
            ->with('success', "Kegiatan '{$name}' berhasil diperbarui.");
    }

    public function destroy(SchoolActivity $schoolActivity): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat mengelola kegiatan sekolah.');

        $name = $schoolActivity->name;
        $schoolActivity->curriculumEvents()->update(['school_activity_id' => null]);
        $schoolActivity->delete();

        return redirect()->route('kurikulum.activities.index')
            ->with('success', "Kegiatan '{$name}' berhasil dihapus dari Kegiatan Sekolah.");
    }

    /**
     * Jadwalkan kegiatan ini langsung ke Kalender Kegiatan atau perbarui jadwal jika sudah ada.
     */
    public function quickSchedule(Request $request, SchoolActivity $schoolActivity): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat menjadwalkan kegiatan sekolah.');

        $validated = $request->validate([
            'event_id' => ['nullable', 'exists:curriculum_events,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string'],
            'is_holiday' => ['nullable', 'boolean'],
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $validated['academic_year'] ?? session('active_academic_year', session('academic_year', $setting->academic_year ?? '2026/2027'));

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'] ?: $startDate;

        $categoryMap = [
            'kaldik' => 'kegiatan_kurikulum',
            'smk_khusus' => 'kegiatan_kurikulum',
            'akademik_ujian' => 'penilaian',
            'supervisi' => 'supervisi',
            'kesiswaan' => 'ke',
            'manajemen' => 'kegiatan_kurikulum',
            'lainnya' => 'lainnya',
        ];

        $calCategory = $categoryMap[$schoolActivity->category] ?? 'kegiatan_kurikulum';

        $isHoliday = $request->boolean('is_holiday', false);
        if (in_array($calCategory, ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'])) {
            $isHoliday = true;
        }

        $description = $schoolActivity->description;
        if (! empty($validated['notes'])) {
            $description = ($description ? $description."\n\n" : '').'Catatan: '.$validated['notes'];
        }

        // Cari event yang sudah ada berdasarkan event_id atau kombinasi activity + tahun pelajaran
        $allExisting = CurriculumEvent::where('school_activity_id', $schoolActivity->id)
            ->where('academic_year', $academicYear)
            ->orderBy('id')
            ->get();

        $existingEvent = null;
        if (! empty($validated['event_id'])) {
            $existingEvent = $allExisting->firstWhere('id', (int) $validated['event_id']);
        }
        if (! $existingEvent) {
            $existingEvent = $allExisting->first();
        }

        // Bersihkan duplikat jika sebelumnya sudah sempat tersimpan lebih dari 1 kegiatan yang sama
        if ($allExisting->count() > 1) {
            $keepId = $existingEvent ? $existingEvent->id : $allExisting->first()->id;
            foreach ($allExisting as $item) {
                if ($item->id !== $keepId) {
                    $item->users()->detach();
                    $item->delete();
                }
            }
        }

        $wasExisting = $existingEvent !== null;

        DB::transaction(function () use ($schoolActivity, $academicYear, $validated, $startDate, $endDate, $description, $calCategory, $isHoliday, &$existingEvent) {
            $target = CurriculumEvent::where('school_activity_id', $schoolActivity->id)
                ->where('academic_year', $academicYear)
                ->lockForUpdate()
                ->first();

            if ($target) {
                $target->update([
                    'title' => $schoolActivity->name,
                    'category' => $calCategory,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_holiday' => $isHoliday,
                    'location' => $validated['location'] ?: ($target->location ?: 'SMK PGRI Kras'),
                    'description' => $description,
                    'color' => $schoolActivity->color,
                ]);
                $existingEvent = $target;
            } else {
                $existingEvent = CurriculumEvent::create([
                    'academic_year' => $academicYear,
                    'school_activity_id' => $schoolActivity->id,
                    'title' => $schoolActivity->name,
                    'category' => $calCategory,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_all_day' => true,
                    'is_holiday' => $isHoliday,
                    'location' => $validated['location'] ?: 'SMK PGRI Kras',
                    'description' => $description,
                    'color' => $schoolActivity->color,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $message = $wasExisting
            ? "Jadwal kegiatan '{$schoolActivity->name}' berhasil diperbarui."
            : "Kegiatan '{$schoolActivity->name}' berhasil dijadwalkan ke Kalender Kegiatan TP {$academicYear}.";

        return redirect()->route('kurikulum.activities.index', ['academic_year' => $academicYear])
            ->with('success', $message);
    }
}
