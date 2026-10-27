<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\GtkPermit;
use App\Models\PiketAttendance;
use App\Models\PiketDutySwap;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\SchoolSetting;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PiketJournalController extends Controller
{
    public function index(Request $request): View
    {
        $academicYearContext = app(AcademicYearContext::class);
        try {
            $academicYear = $academicYearContext->getViewingYear($request->query('academic_year'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'academic_year' => $e->getMessage(),
            ]);
        }

        $month = $request->query('month');

        $query = PiketJournal::with(['coordinator', 'attendances.teacher'])
            ->withCount(['studentPermits', 'incidents', 'invalClasses', 'guests'])
            ->where('academic_year', $academicYear)
            ->latest('date');

        if ($month) {
            $query->whereMonth('date', Carbon::parse($month)->month)
                ->whereYear('date', Carbon::parse($month)->year);
        }

        $journals = $query->paginate(15);

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.journals_index', compact('journals', 'academicYear', 'month'));
        }

        return view('piket.journals.index', compact('journals', 'academicYear', 'month'));
    }

    public function today(): RedirectResponse
    {
        $todayStr = Carbon::today()->toDateString();
        $context = app(AcademicYearContext::class);
        $operationalYear = $context->getOperationalYear();
        $academicYear = $operationalYear->name;
        $setting = SchoolSetting::getSetting();
        $semester = $operationalYear->semester ?? $setting->semester ?? 'Ganjil';

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';

        // Cari atau buat jurnal hari ini
        $journal = PiketJournal::firstOrCreate(
            ['date' => $todayStr],
            [
                'academic_year' => $academicYear,
                'semester' => $semester,
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        if (! $journal->coordinator_id) {
            $coordinatorSched = PiketSchedule::where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->where('day', $todayDay)
                ->where('role', 'koordinator')
                ->where('is_active', true)
                ->first();

            if ($coordinatorSched) {
                $journal->update(['coordinator_id' => $coordinatorSched->teacher_id]);
            }
        }

        $this->ensureAttendancesPopulated($journal);

        return redirect()->route('piket.journals.show', $journal);
    }

    public function show(PiketJournal $journal): View
    {
        $this->ensureAttendancesPopulated($journal);

        $journal->load([
            'coordinator',
            'completer',
            'attendances.teacher',
            'invalClasses.classroom',
            'invalClasses.absentTeacher',
            'invalClasses.invalTeacher',
            'invalClasses.subject',
            'studentPermits.classroom',
            'studentPermits.givenBy',
            'incidents.classroom',
            'incidents.recordedBy',
            'guests.recordedBy',
        ]);

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $journalDate = Carbon::parse($journal->date);
        $dayName = $dayNames[$journalDate->dayOfWeekIso] ?? 'Senin';

        $scheduledTeachers = PiketSchedule::with('teacher')
            ->where('academic_year', $journal->academic_year)
            ->where('semester', $journal->semester)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->get();

        if (DeviceDetector::isMobile(request())) {
            return view('mobile.piket.journal_show', compact('journal', 'scheduledTeachers'));
        }

        return view('piket.journals.show', compact('journal', 'scheduledTeachers'));
    }

    public function syncMachineAttendance(PiketJournal $journal): RedirectResponse
    {
        $this->ensureAttendancesPopulated($journal);
        $syncedCount = $this->performMachineSync($journal);

        return back()->with('success', "Presensi petugas piket berhasil disinkronkan dari mesin presensi dan izin GTK ({$syncedCount} petugas disinkronkan).");
    }

    private function ensureAttendancesPopulated(PiketJournal $journal): void
    {
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $journalDate = Carbon::parse($journal->date);
        $journalDateStr = $journalDate->toDateString();
        $dayName = $dayNames[$journalDate->dayOfWeekIso] ?? 'Senin';

        $schedules = PiketSchedule::with('teacher')
            ->where('academic_year', $journal->academic_year)
            ->where('semester', $journal->semester)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return;
        }

        if (! $journal->coordinator_id) {
            $coordinatorSched = $schedules->firstWhere('role', 'koordinator');
            if ($coordinatorSched) {
                $journal->update(['coordinator_id' => $coordinatorSched->teacher_id]);
            }
        }

        $approvedSwaps = PiketDutySwap::where('status', 'disetujui')
            ->whereDate('duty_date', $journalDateStr)
            ->with(['requester', 'replacementTeacher'])
            ->get();

        $newlyCreated = false;

        foreach ($schedules as $sched) {
            $swap = $approvedSwaps->firstWhere('original_schedule_id', $sched->id)
                ?? $approvedSwaps->firstWhere('requester_id', $sched->teacher_id);

            $effectiveTeacherId = $swap ? $swap->replacement_teacher_id : $sched->teacher_id;

            $notes = $sched->duty_location;
            if ($swap) {
                $requesterName = $sched->teacher?->formatted_short_name ?? $sched->teacher?->name ?? 'Guru Lain';
                $notes = ($notes ? $notes.' | ' : '')."[Tukar Piket] Menggantikan {$requesterName}";
            }

            $att = PiketAttendance::firstOrCreate(
                [
                    'piket_journal_id' => $journal->id,
                    'teacher_id' => $effectiveTeacherId,
                ],
                [
                    'status' => 'belum_hadir',
                    'notes' => $notes,
                ]
            );
        }

        // Sinkronkan selalu dengan data presensi GTK terkini (mesin presensi / geotag & izin GTK)
        $this->performMachineSync($journal);
    }

    private function performMachineSync(PiketJournal $journal): int
    {
        $journal->load('attendances');
        $teacherIds = $journal->attendances->pluck('teacher_id')->filter()->unique()->toArray();

        if (empty($teacherIds)) {
            return 0;
        }

        $journalDateStr = Carbon::parse($journal->date)->toDateString();

        $attendanceLogs = AttendanceLog::where('user_type', 'teacher')
            ->whereIn('user_id', $teacherIds)
            ->whereDate('scanned_at', $journalDateStr)
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('user_id');

        $gtkPermits = GtkPermit::whereIn('user_id', $teacherIds)
            ->whereDate('start_date', '<=', $journalDateStr)
            ->whereDate('end_date', '>=', $journalDateStr)
            ->get()
            ->keyBy('user_id');

        $syncedCount = 0;
        foreach ($journal->attendances as $att) {
            $logs = $attendanceLogs->get($att->teacher_id, collect());
            $permit = $gtkPermits->get($att->teacher_id);

            $updates = [];
            if ($logs->isNotEmpty()) {
                $updates['status'] = 'hadir';
                $updates['check_in_time'] = $logs->first()->scanned_at->format('H:i:s');
                if ($logs->count() > 1) {
                    $updates['check_out_time'] = $logs->last()->scanned_at->format('H:i:s');
                }
                $syncedCount++;
            } elseif ($permit) {
                $updates['status'] = in_array($permit->permission_type, ['sakit', 'izin']) ? $permit->permission_type : 'izin';
                $updates['check_in_time'] = null;
                $permitNote = ($att->notes && ! str_contains($att->notes, '[Izin GTK]') ? $att->notes.' | ' : '').'[Izin GTK] '.$permit->label.($permit->reason ? ': '.$permit->reason : '');
                $updates['notes'] = $permitNote;
                $syncedCount++;
            } else {
                // Belum ada log presensi dan tidak ada izin GTK -> Belum Hadir
                if (empty($att->check_in_time) && ! in_array($att->status, ['izin', 'sakit', 'alpa'])) {
                    if ($att->status !== 'belum_hadir') {
                        $updates['status'] = 'belum_hadir';
                    }
                }
            }

            if (! empty($updates)) {
                PiketAttendance::where('id', $att->id)->update($updates);
            }
        }

        return $syncedCount;
    }

    public function updateAttendance(Request $request, PiketJournal $journal): RedirectResponse
    {
        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.id' => 'required|exists:piket_attendances,id',
            'attendances.*.status' => 'required|in:hadir,izin,sakit,alpa,belum_hadir',
            'attendances.*.check_in_time' => 'nullable|string',
            'attendances.*.check_out_time' => 'nullable|string',
            'attendances.*.notes' => 'nullable|string|max:255',
        ]);

        foreach ($validated['attendances'] as $item) {
            PiketAttendance::where('id', $item['id'])
                ->where('piket_journal_id', $journal->id)
                ->update([
                    'status' => $item['status'],
                    'check_in_time' => $item['check_in_time'] ?: null,
                    'check_out_time' => $item['check_out_time'] ?: null,
                    'notes' => $item['notes'] ?? null,
                ]);
        }

        return back()->with('success', 'Presensi petugas piket berhasil diperbarui.');
    }

    public function updateNotes(Request $request, PiketJournal $journal): RedirectResponse
    {
        $validated = $request->validate([
            'general_notes' => 'nullable|string',
            'coordinator_id' => 'nullable|exists:users,id',
        ]);

        $journal->update($validated);

        return back()->with('success', 'Catatan situasi umum jurnal piket berhasil disimpan.');
    }

    public function complete(PiketJournal $journal): RedirectResponse
    {
        $journal->update([
            'status' => 'selesai',
            'completed_at' => now(),
            'completed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Jurnal piket harian telah selesai dan berhasil ditutup/divalidasi.');
    }

    public function print(PiketJournal $journal): View
    {
        $journal->load([
            'coordinator',
            'completer',
            'attendances.teacher',
            'invalClasses.classroom',
            'invalClasses.absentTeacher',
            'invalClasses.invalTeacher',
            'invalClasses.subject',
            'studentPermits.classroom',
            'incidents.classroom',
            'guests',
        ]);

        $setting = SchoolSetting::getSetting();

        return view('piket.journals.print', compact('journal', 'setting'));
    }
}
