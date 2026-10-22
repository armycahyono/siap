<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketDutySwap;
use App\Models\PiketSchedule;
use App\Models\User;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PiketDutySwapController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    public function index(Request $request): View
    {
        $swaps = PiketDutySwap::with(['originalSchedule', 'requester', 'replacementTeacher', 'approver'])
            ->latest('duty_date')
            ->paginate(15);

        // List jadwal piket user atau semua jadwal
        $user = Auth::user();
        $mySchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('is_active', true)
            ->get();

        $allTeachers = User::operationalPersonnel()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.swaps_index', compact('swaps', 'mySchedules', 'allTeachers'));
        }

        return view('piket.swaps.index', compact('swaps', 'mySchedules', 'allTeachers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'original_schedule_id' => 'required|exists:piket_schedules,id',
            'replacement_teacher_id' => 'required|exists:users,id|different:requester_id',
            'duty_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|max:500',
        ]);

        $schedule = PiketSchedule::findOrFail($validated['original_schedule_id']);

        $swap = PiketDutySwap::create([
            'original_schedule_id' => $schedule->id,
            'requester_id' => Auth::id(),
            'replacement_teacher_id' => $validated['replacement_teacher_id'],
            'duty_date' => $validated['duty_date'],
            'reason' => $validated['reason'],
            'status' => 'menunggu',
        ]);

        $this->telegramService->notifyDutySwapRequested($swap);

        return back()->with('success', 'Permohonan tukar jadwal piket berhasil diajukan dan notifikasi telah dikirim ke guru pengganti.');
    }

    public function approve(PiketDutySwap $swap): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $swap->replacement_teacher_id && ! $user->canManagePiketSchedules()) {
            return back()->with('error', 'Hanya guru pengganti yang bersangkutan atau koordinator yang dapat menyetujui pertukaran ini.');
        }

        $swap->update([
            'status' => 'disetujui',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->telegramService->notifyDutySwapApproved($swap);

        return back()->with('success', 'Permohonan tukar jadwal piket telah disetujui.');
    }

    public function reject(PiketDutySwap $swap): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $swap->replacement_teacher_id && ! $user->canManagePiketSchedules()) {
            return back()->with('error', 'Hanya guru pengganti yang bersangkutan atau koordinator yang dapat menolak pertukaran ini.');
        }

        $swap->update([
            'status' => 'ditolak',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->telegramService->notifyDutySwapRejected($swap);

        return back()->with('success', 'Permohonan tukar jadwal piket telah ditolak.');
    }
}
