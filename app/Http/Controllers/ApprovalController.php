<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', $setting->semester);

        $pendingQuery = Supervision::with(['teacher', 'supervisor'])
            ->where('status', 'menunggu_persetujuan');

        $historyQuery = Supervision::with(['teacher', 'supervisor'])
            ->whereIn('status', ['disetujui', 'selesai']);

        if ($academicYear && $academicYear !== 'all') {
            $pendingQuery->where('academic_year', $academicYear);
            $historyQuery->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $pendingQuery->where('semester', $semester);
            $historyQuery->where('semester', $semester);
        }

        $pendingSupervisions = $pendingQuery->orderBy('updated_at', 'desc')->get();
        $historySupervisions = $historyQuery->orderBy('principal_approved_at', 'desc')->take(20)->get();

        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('approvals.index', compact('pendingSupervisions', 'historySupervisions', 'setting', 'academicYear', 'semester', 'existingYears'));
    }

    public function show(Supervision $supervision): View
    {
        $supervision->load(['teacher', 'supervisor', 'photos', 'scores.indicator.phase']);
        $setting = SchoolSetting::getSetting();

        return view('approvals.show', compact('supervision', 'setting'));
    }

    public function approve(Request $request, Supervision $supervision): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'principal_notes' => ['nullable', 'string'],
        ]);

        $supervision->ensureQrToken();

        $principalUser = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        $principalId = $user->hasRole('kepala_sekolah') ? $user->id : ($principalUser?->id ?? $user->id);

        $updateData = [
            'principal_id' => $principalId,
            'approved_by_principal' => true,
            'principal_approved_at' => now(),
            'principal_notes' => $request->input('principal_notes', 'Telah diperiksa dan disetujui.'),
            'status' => 'disetujui',
            'finalized_at' => now(),
        ];

        if (empty($supervision->report_number)) {
            $updateData['report_number'] = Supervision::generateReportNumber();
            $supervision->report_number = $updateData['report_number'];
        }

        ActivityLogger::withoutLogging(function () use ($supervision, $updateData) {
            $supervision->update($updateData);
        });

        // Capture immutable snapshot upon official principal approval
        $supervision->captureSnapshot(true);

        ActivityLogger::log(
            'approve',
            "Mengesahkan laporan supervisi guru {$supervision->teacher?->name} (No: {$supervision->report_number}, Nilai: {$supervision->percentage}% - {$supervision->predicate}).",
            $supervision,
            [
                'report_number' => $supervision->report_number,
                'percentage' => $supervision->percentage,
                'predicate' => $supervision->predicate,
                'principal_notes' => $updateData['principal_notes'],
            ]
        );

        $redirect = $request->has('redirect_to') && $request->input('redirect_to') === 'tasks'
            ? redirect()->route('tasks')
            : redirect()->route('approvals.index');

        return $redirect->with('success', "Laporan supervisi {$supervision->teacher->name} (No: {$supervision->report_number}) telah resmi disetujui & disahkan!");
    }

    public function reject(Request $request, Supervision $supervision): RedirectResponse
    {
        $request->validate([
            'revision_notes' => ['required', 'string'],
        ]);

        ActivityLogger::withoutLogging(function () use ($supervision, $request) {
            $supervision->update([
                'status' => 'pascaobservasi',
                'principal_notes' => 'Catatan revisi: '.$request->input('revision_notes'),
            ]);
        });

        ActivityLogger::log(
            'reject',
            "Mengembalikan berkas supervisi guru {$supervision->teacher?->name} untuk perbaikan/revisi.",
            $supervision,
            [
                'revision_notes' => $request->input('revision_notes'),
                'status' => 'pascaobservasi',
            ]
        );

        $redirect = $request->has('redirect_to') && $request->input('redirect_to') === 'tasks'
            ? redirect()->route('tasks')
            : redirect()->route('approvals.index');

        return $redirect->with('info', 'Supervisi telah dikembalikan ke supervisor untuk revisi.');
    }
}
