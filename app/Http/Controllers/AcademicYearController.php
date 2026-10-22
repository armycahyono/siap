<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of the academic years.
     */
    public function index(): View
    {
        $academicYears = AcademicYear::orderBy('name', 'desc')->get();
        $activeYear = AcademicYear::getActive() ?? SchoolSetting::getSetting();

        return view('master.academic_years.index', compact('academicYears', 'activeYear'));
    }

    /**
     * Store a newly created academic year in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'is_locked' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $shouldBeActive = ! empty($validated['is_active']);
        unset($validated['is_active']);

        $academicYear = AcademicYear::create([
            ...$validated,
            'semester' => $validated['semester'] ?? 'Ganjil',
            'is_active' => false,
            'is_locked' => (bool) ($validated['is_locked'] ?? false),
        ]);

        if ($shouldBeActive) {
            $academicYear->setAsActive();
        }

        ActivityLogger::log('create_academic_year', "Menambahkan Tahun Pelajaran baru: {$academicYear->name}", null, $academicYear->toArray());

        return redirect()->route('academic-years.index')->with('success', "Tahun Pelajaran {$academicYear->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified academic year in storage.
     */
    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name,'.$academicYear->id],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_locked' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $academicYear->update([
            ...$validated,
            'semester' => $validated['semester'] ?? $academicYear->semester ?? 'Ganjil',
            'is_locked' => (bool) ($validated['is_locked'] ?? false),
        ]);

        if ($academicYear->is_active) {
            $setting = SchoolSetting::first();
            if ($setting) {
                $setting->update([
                    'academic_year' => $academicYear->name,
                    'semester' => $academicYear->semester,
                ]);
            }
        }

        ActivityLogger::log('update_academic_year', "Memperbarui data Tahun Pelajaran: {$academicYear->name}", null, $academicYear->toArray());

        return redirect()->route('academic-years.index')->with('success', "Tahun Pelajaran {$academicYear->name} berhasil diperbarui.");
    }

    /**
     * Set the selected academic year as officially active for the school.
     */
    public function setActive(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->setAsActive();

        ActivityLogger::log('set_active_academic_year', "Menetapkan Tahun Pelajaran {$academicYear->name} (Semester {$academicYear->semester}) sebagai tahun ajaran aktif sekolah.");

        return redirect()->route('academic-years.index')->with('success', "Tahun Pelajaran {$academicYear->name} (Semester {$academicYear->semester}) berhasil ditetapkan sebagai tahun aktif sekolah.");
    }

    /**
     * Toggle the locked (archived) status of the academic year.
     */
    public function toggleLock(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active && ! $academicYear->is_locked) {
            return back()->with('error', 'Tahun Pelajaran yang sedang aktif tidak dapat dikunci/diarsipkan.');
        }

        $academicYear->update(['is_locked' => ! $academicYear->is_locked]);

        $status = $academicYear->is_locked ? 'dikunci (arsip permanen)' : 'dibuka kuncinya';
        ActivityLogger::log('toggle_lock_academic_year', "Mengubah status kunci Tahun Pelajaran {$academicYear->name} menjadi {$status}.");

        return redirect()->route('academic-years.index')->with('success', "Tahun Pelajaran {$academicYear->name} berhasil {$status}.");
    }

    /**
     * Remove the specified academic year from storage.
     */
    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->with('error', 'Tidak dapat menghapus Tahun Pelajaran yang sedang aktif!');
        }

        $name = $academicYear->name;
        $academicYear->delete();

        ActivityLogger::log('delete_academic_year', "Menghapus Tahun Pelajaran: {$name}");

        return redirect()->route('academic-years.index')->with('success', "Tahun Pelajaran {$name} berhasil dihapus.");
    }

    /**
     * Switch the active academic year and semester in session context.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['nullable', 'in:Ganjil,Genap'],
        ]);

        session([
            'active_academic_year' => $validated['academic_year'],
            'academic_year' => $validated['academic_year'],
        ]);

        if (! empty($validated['semester'])) {
            session([
                'active_semester' => $validated['semester'],
                'semester' => $validated['semester'],
            ]);
        }

        $semesterLabel = ! empty($validated['semester']) ? " (Semester {$validated['semester']})" : '';
        $message = "Konteks sistem dialihkan ke Tahun Pelajaran {$validated['academic_year']}{$semesterLabel}.";

        ActivityLogger::log('switch_year', $message, null, [
            'academic_year' => $validated['academic_year'],
            'semester' => $validated['semester'] ?? null,
        ]);

        $referer = $request->header('referer');
        if ($referer) {
            $parsedUrl = parse_url($referer);
            $query = [];
            if (! empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $query);
            }
            if (isset($query['academic_year'])) {
                $query['academic_year'] = $validated['academic_year'];
            }
            if (isset($query['semester']) && ! empty($validated['semester'])) {
                $query['semester'] = $validated['semester'];
            }
            $newQuery = http_build_query($query);
            $targetUrl = ($parsedUrl['path'] ?? '/').($newQuery ? '?'.$newQuery : '');

            return redirect($targetUrl)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Reset the active academic year and semester back to school's master default.
     */
    public function reset(Request $request): RedirectResponse
    {
        session()->forget(['active_academic_year', 'active_semester', 'academic_year', 'semester']);

        ActivityLogger::log('reset_year', 'Mengembalikan konteks Tahun Pelajaran ke master default sekolah.');

        $referer = $request->header('referer');
        if ($referer) {
            $parsedUrl = parse_url($referer);
            $query = [];
            if (! empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $query);
            }
            unset($query['academic_year'], $query['semester']);
            $newQuery = http_build_query($query);
            $targetUrl = ($parsedUrl['path'] ?? '/').($newQuery ? '?'.$newQuery : '');

            return redirect($targetUrl)->with('success', 'Konteks sistem dikembalikan ke Tahun Pelajaran master aktif sekolah.');
        }

        return back()->with('success', 'Konteks sistem dikembalikan ke Tahun Pelajaran master aktif sekolah.');
    }
}
