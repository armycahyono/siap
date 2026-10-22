<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\DeviceDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PiketScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));
        $semester = $request->query('semester', $setting->semester ?? 'Ganjil');

        $dayOrder = "CASE day WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END";

        $schedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->with('teacher')
            ->orderByRaw($dayOrder)
            ->orderBy('role', 'desc')
            ->get();

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $groupedSchedules = [];
        foreach ($days as $day) {
            $groupedSchedules[$day] = $schedules->where('day', $day);
        }

        // List guru aktif untuk modal tambah/edit
        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah']))
            ->operationalPersonnel()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.schedules_index', compact(
                'academicYear',
                'semester',
                'days',
                'groupedSchedules',
                'teachers'
            ));
        }

        return view('piket.schedules.index', compact(
            'academicYear',
            'semester',
            'days',
            'groupedSchedules',
            'teachers'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year' => 'required|string|max:20',
            'semester' => 'required|in:Ganjil,Genap',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'teacher_id' => 'required|exists:users,id',
            'role' => 'required|in:koordinator,anggota',
            'duty_location' => 'nullable|string|max:100',
        ]);

        // Cek duplikasi di hari yang sama
        $exists = PiketSchedule::where('academic_year', $validated['academic_year'])
            ->where('semester', $validated['semester'])
            ->where('day', $validated['day'])
            ->where('teacher_id', $validated['teacher_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Guru tersebut sudah terjadwal piket pada hari '.$validated['day'].'.');
        }

        PiketSchedule::create($validated);

        return back()->with('success', 'Petugas piket berhasil ditambahkan ke jadwal.');
    }

    public function update(Request $request, PiketSchedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'teacher_id' => 'required|exists:users,id',
            'role' => 'required|in:koordinator,anggota',
            'duty_location' => 'nullable|string|max:100',
            'is_active' => 'required|boolean',
        ]);

        $schedule->update($validated);

        return back()->with('success', 'Jadwal petugas piket berhasil diperbarui.');
    }

    public function destroy(PiketSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'Petugas piket berhasil dihapus dari jadwal.');
    }
}
