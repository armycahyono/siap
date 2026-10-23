<?php

namespace App\Http\Controllers;

use App\Models\ActivityCategory;
use App\Models\CurriculumEvent;
use App\Models\SchoolActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $type = $request->query('type'); // all, kaldik, internal

        $query = ActivityCategory::withCount(['schoolActivities', 'curriculumEvents']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type === 'kaldik') {
            $query->where('is_kaldik', true);
        } elseif ($type === 'internal') {
            $query->where('is_kaldik', false);
        }

        $categories = $query->orderBy('order_index')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $totalCategories = ActivityCategory::count();
        $totalActive = ActivityCategory::where('is_active', true)->count();
        $totalKaldik = ActivityCategory::where('is_kaldik', true)->count();
        $totalInternal = ActivityCategory::where('is_kaldik', false)->count();

        return view('master.activity_categories.index', compact(
            'categories',
            'search',
            'type',
            'totalCategories',
            'totalActive',
            'totalKaldik',
            'totalInternal'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:activity_categories,code'],
            'color' => ['nullable', 'string', 'max:30'],
            'badge_class' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_kaldik' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        if (empty($data['code'])) {
            $data['code'] = Str::slug($data['name'], '_');
            // Ensure unique if slug already exists
            $baseSlug = $data['code'];
            $counter = 1;
            while (ActivityCategory::where('code', $data['code'])->exists()) {
                $data['code'] = "{$baseSlug}_{$counter}";
                $counter++;
            }
        }

        $data['color'] = $data['color'] ?: '#2563eb';
        $data['is_kaldik'] = $request->boolean('is_kaldik', false);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['order_index'] = $data['order_index'] ?? ((int) ActivityCategory::max('order_index') + 1);

        if (empty($data['badge_class'])) {
            $data['badge_class'] = 'bg-primary text-white';
        }

        ActivityCategory::create($data);

        return redirect()->route('activity-categories.index')
            ->with('success', "Kategori kegiatan '{$data['name']}' berhasil ditambahkan.");
    }

    public function update(Request $request, ActivityCategory $activityCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('activity_categories', 'code')->ignore($activityCategory->id)],
            'color' => ['nullable', 'string', 'max:30'],
            'badge_class' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_kaldik' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $oldCode = $activityCategory->code;
        $newCode = $data['code'];

        $data['color'] = $data['color'] ?: '#2563eb';
        $data['is_kaldik'] = $request->boolean('is_kaldik', false);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['order_index'] = $data['order_index'] ?? $activityCategory->order_index;

        if (empty($data['badge_class'])) {
            $data['badge_class'] = $activityCategory->badge_class ?: 'bg-primary text-white';
        }

        $activityCategory->update($data);

        // Sync old category code if code changed
        if ($oldCode !== $newCode) {
            SchoolActivity::where('category', $oldCode)->update(['category' => $newCode]);
            CurriculumEvent::where('category', $oldCode)->update(['category' => $newCode]);
        }

        return redirect()->route('activity-categories.index')
            ->with('success', "Kategori kegiatan '{$data['name']}' berhasil diperbarui.");
    }

    public function destroy(ActivityCategory $activityCategory): RedirectResponse
    {
        $activityCount = $activityCategory->schoolActivities()->count();
        $eventCount = $activityCategory->curriculumEvents()->count();

        if ($activityCount > 0 || $eventCount > 0) {
            return redirect()->route('activity-categories.index')
                ->with('error', "Kategori '{$activityCategory->name}' tidak dapat dihapus karena masih digunakan oleh {$activityCount} agenda kegiatan dan {$eventCount} jadwal kalender. Anda dapat menonaktifkan statusnya agar tidak muncul di pilihan baru.");
        }

        $name = $activityCategory->name;
        $activityCategory->delete();

        return redirect()->route('activity-categories.index')
            ->with('success', "Kategori kegiatan '{$name}' berhasil dihapus.");
    }
}
