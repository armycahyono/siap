<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketDutySwap;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PiketCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));
        $semester = $request->query('semester', $setting->semester ?? 'Ganjil');

        $year = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);

        $currentMonth = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $currentMonth->daysInMonth;
        $startDayOfWeek = $currentMonth->copy()->startOfMonth()->dayOfWeekIso; // 1 (Mon) to 7 (Sun)

        // Ambil jadwal master piket
        $schedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->with('teacher')
            ->get();

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        // Ambil jurnal & perizinan bulan ini
        $startDate = $currentMonth->copy()->startOfMonth()->toDateString();
        $endDate = $currentMonth->copy()->endOfMonth()->toDateString();

        $journals = PiketJournal::whereBetween('date', [$startDate, $endDate])
            ->with(['coordinator', 'attendances.teacher'])
            ->withCount(['studentPermits', 'incidents', 'invalClasses'])
            ->get()
            ->keyBy(fn ($item) => $item->date->format('Y-m-d'));

        // Ambil tukar piket yang disetujui bulan ini
        $approvedSwaps = PiketDutySwap::where('status', 'disetujui')
            ->whereBetween('duty_date', [$startDate, $endDate])
            ->with(['requester', 'replacementTeacher'])
            ->get()
            ->groupBy(fn ($item) => $item->duty_date->format('Y-m-d'));

        // Bangun data kalender per tanggal
        $calendarData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($year, $month, $day);
            $dateStr = $date->format('Y-m-d');
            $dayName = $dayNames[$date->dayOfWeekIso] ?? '';

            // Guru piket terjadwal untuk hari ini
            $daySchedules = $schedules->where('day', $dayName)->values();
            $journal = $journals->get($dateStr);
            $swaps = $approvedSwaps->get($dateStr, collect());

            $calendarData[$day] = [
                'date' => $date,
                'dateStr' => $dateStr,
                'dayName' => $dayName,
                'isToday' => $date->isToday(),
                'isSunday' => $date->dayOfWeekIso === 7,
                'schedules' => $daySchedules,
                'journal' => $journal,
                'swaps' => $swaps,
            ];
        }

        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        return view('piket.calendar.index', compact(
            'academicYear',
            'semester',
            'currentMonth',
            'startDayOfWeek',
            'daysInMonth',
            'calendarData',
            'prevMonth',
            'nextMonth'
        ));
    }
}
