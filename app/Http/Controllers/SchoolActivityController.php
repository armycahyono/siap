<?php

namespace App\Http\Controllers;

use App\Models\CurriculumEvent;
use App\Models\SchoolActivity;
use App\Models\SchoolSetting;
use App\Models\Supervision;
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

        // Optimized: pre-load events for active academic year to prevent N+1 queries
        $events = CurriculumEvent::where('academic_year', $academicYear)->get();
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

        return view('kurikulum.activities.index', compact(
            'items',
            'categories',
            'academicYear',
            'search',
            'category',
            'statusFilter',
            'totalActivities',
            'scheduledCount',
            'unscheduledCount',
            'percentageScheduled',
            'setting'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat mengelola kegiatan sekolah.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'target_participant' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'default_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_mandatory'] = $request->boolean('is_mandatory');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['default_duration_days'] = $validated['default_duration_days'] ?? 1;

        SchoolActivity::create($validated);

        return redirect()->route('kurikulum.activities.index')
            ->with('success', "Kegiatan '{$validated['name']}' berhasil ditambahkan ke Kegiatan Sekolah.");
    }

    public function update(Request $request, SchoolActivity $schoolActivity): RedirectResponse
    {
        abort_unless(auth()->user()->canViewAllSchedules(), 403, 'Hanya kurikulum / administrator yang dapat mengelola kegiatan sekolah.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'target_participant' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'default_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_mandatory'] = $request->boolean('is_mandatory');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['default_duration_days'] = $validated['default_duration_days'] ?? 1;

        $schoolActivity->update($validated);

        return redirect()->route('kurikulum.activities.index')
            ->with('success', "Kegiatan '{$schoolActivity->name}' berhasil diperbarui.");
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
