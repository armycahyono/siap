<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketGuest;
use App\Models\PiketJournal;
use App\Models\SchoolSetting;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PiketGuestController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $status = $request->query('status'); // 'all', 'visiting', 'checked_out'
        $search = $request->query('search');

        // Mode Periode: 'today', 'week', 'month', 'range'
        $period = $request->query('period', 'today');
        $today = Carbon::today()->toDateString();

        if ($period === 'week') {
            $startDate = Carbon::now()->startOfWeek()->toDateString();
            $endDate = Carbon::now()->endOfWeek()->toDateString();
        } elseif ($period === 'month') {
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->endOfMonth()->toDateString();
        } elseif ($period === 'range') {
            $startDate = $request->query('start_date', $today);
            $endDate = $request->query('end_date', $today);
        } else {
            $period = 'today';
            $specificDate = $request->query('date', $today);
            $startDate = $specificDate;
            $endDate = $specificDate;
        }

        $date = $startDate;

        $query = PiketGuest::with('recordedBy')->latest('visit_date')->latest('check_in_time');

        if ($startDate === $endDate) {
            $query->whereDate('visit_date', $startDate);
        } else {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        }

        if ($status === 'visiting') {
            $query->whereNull('check_out_time');
        } elseif ($status === 'checked_out') {
            $query->whereNotNull('check_out_time');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('meet_with', 'like', "%{$search}%");
            });
        }

        $guests = $query->paginate(20)->withQueryString();

        // Rekap angka statistik tamu
        $statsQuery = PiketGuest::query();
        if ($startDate === $endDate) {
            $statsQuery->whereDate('visit_date', $startDate);
        } else {
            $statsQuery->whereBetween('visit_date', [$startDate, $endDate]);
        }
        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('meet_with', 'like', "%{$search}%");
            });
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'visiting' => (clone $statsQuery)->whereNull('check_out_time')->count(),
            'checked_out' => (clone $statsQuery)->whereNotNull('check_out_time')->count(),
        ];

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.guests', compact(
                'guests', 'status', 'date', 'startDate', 'endDate', 'period', 'search', 'stats'
            ));
        }

        return view('piket.guests.index', compact('guests', 'date', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visit_date' => 'required|date',
            'check_in_time' => 'required|string',
            'guest_name' => 'required|string|max:255',
            'institution' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'purpose' => 'required|string|max:255',
            'meet_with' => 'required|string|max:255',
            'badge_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $journal = PiketJournal::firstOrCreate(
            ['date' => $validated['visit_date']],
            [
                'academic_year' => app(AcademicYearContext::class)->getOperationalYear()->name,
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        PiketGuest::create([
            'piket_journal_id' => $journal->id,
            'visit_date' => $validated['visit_date'],
            'check_in_time' => $validated['check_in_time'],
            'guest_name' => $validated['guest_name'],
            'institution' => $validated['institution'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'purpose' => $validated['purpose'],
            'meet_with' => $validated['meet_with'],
            'badge_number' => $validated['badge_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Buku tamu berhasil dicatat.');
    }

    public function checkOut(PiketGuest $guest): RedirectResponse
    {
        $guest->update([
            'check_out_time' => Carbon::now()->format('H:i:s'),
        ]);

        return back()->with('success', "Tamu {$guest->guest_name} telah selesai berkunjung (Check Out: ".Carbon::now()->format('H:i').' WIB).');
    }

    public function destroy(PiketGuest $guest): RedirectResponse
    {
        $guest->delete();

        return back()->with('success', 'Data tamu berhasil dihapus.');
    }
}
