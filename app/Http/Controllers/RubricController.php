<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionPhase;
use App\Models\SupervisionScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RubricController extends Controller
{
    public function index(): View
    {
        $phases = SupervisionPhase::with(['indicators' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        $totalIndicators = SupervisionIndicator::count();
        $activeIndicators = SupervisionIndicator::where('is_active', true)->count();
        $totalMaxScore = SupervisionIndicator::where('is_active', true)->sum('max_score');
        $setting = SchoolSetting::getSetting();

        return view('rubrics.index', compact('phases', 'totalIndicators', 'activeIndicators', 'totalMaxScore', 'setting'));
    }

    public function updatePredicates(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'grade_a_min' => ['required', 'integer', 'min:1', 'max:100'],
            'grade_b_min' => ['required', 'integer', 'min:1', 'max:100', 'lt:grade_a_min'],
            'grade_c_min' => ['required', 'integer', 'min:1', 'max:100', 'lt:grade_b_min'],
        ], [
            'grade_b_min.lt' => 'Batas bawah Nilai B harus lebih kecil dari Nilai A.',
            'grade_c_min.lt' => 'Batas bawah Nilai C harus lebih kecil dari Nilai B.',
        ]);

        $setting = SchoolSetting::getSetting();
        $setting->update($data);

        return back()->with('success', 'Ambang batas predikat penilaian supervisi berhasil diperbarui.');
    }

    public function storePhase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer'],
        ]);

        $data['order'] = $data['order'] ?? (SupervisionPhase::max('order') + 1);
        $data['is_active'] = true;

        SupervisionPhase::create($data);

        return back()->with('success', 'Fase/Tahapan supervisi baru berhasil ditambahkan.');
    }

    public function updatePhase(Request $request, SupervisionPhase $phase): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'order' => ['required', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');
        $phase->update($data);

        return back()->with('success', 'Fase supervisi berhasil diperbarui.');
    }

    public function deletePhase(SupervisionPhase $phase): RedirectResponse
    {
        $hasScores = SupervisionScore::whereIn('indicator_id', $phase->indicators()->pluck('id'))->exists();
        if ($hasScores) {
            return back()->with('error', 'Fase ini tidak dapat dihapus karena indikatornya sudah memiliki riwayat penilaian supervisi. Anda dapat menonaktifkannya melalui menu edit.');
        }

        $phase->delete();

        return back()->with('success', 'Fase dan seluruh indikator di dalamnya berhasil dihapus.');
    }

    public function storeIndicator(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phase_id' => ['required', 'exists:supervision_phases,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_score' => ['required', 'integer', 'min:1', 'max:10'],
            'order' => ['nullable', 'integer'],
        ]);

        $data['order'] = $data['order'] ?? (SupervisionIndicator::where('phase_id', $data['phase_id'])->max('order') + 1);
        $data['is_active'] = true;

        SupervisionIndicator::create($data);

        return back()->with('success', 'Indikator penilaian supervisi berhasil ditambahkan.');
    }

    public function updateIndicator(Request $request, SupervisionIndicator $indicator): RedirectResponse
    {
        $data = $request->validate([
            'phase_id' => ['required', 'exists:supervision_phases,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_score' => ['required', 'integer', 'min:1', 'max:10'],
            'order' => ['required', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');
        $indicator->update($data);

        return back()->with('success', 'Indikator berhasil diperbarui.');
    }

    public function deleteIndicator(SupervisionIndicator $indicator): RedirectResponse
    {
        if ($indicator->scores()->exists()) {
            return back()->with('error', 'Indikator ini tidak dapat dihapus karena sudah memiliki riwayat penilaian supervisi. Anda dapat menonaktifkannya melalui menu edit.');
        }

        $indicator->delete();

        return back()->with('success', 'Indikator berhasil dihapus.');
    }
}
