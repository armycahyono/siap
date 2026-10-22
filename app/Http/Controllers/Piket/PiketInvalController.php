<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\GtkPermit;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PiketInvalController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));
        $classroomId = $request->query('classroom_id');
        $status = $request->query('status'); // 'all', 'handled' (terisi), 'unhandled' (kosong)
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

        $query = PiketInvalClass::with(['journal', 'classroom', 'absentTeacher', 'invalTeacher', 'subject'])
            ->latest();

        if ($startDate === $endDate) {
            $query->whereHas('journal', fn ($q) => $q->whereDate('date', $startDate));
        } else {
            $query->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        }

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($status === 'handled') {
            $query->whereNotNull('inval_teacher_id');
        } elseif ($status === 'unhandled') {
            $query->whereNull('inval_teacher_id');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('absentTeacher', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('invalTeacher', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('assignment_material', 'like', "%{$search}%");
            });
        }

        $invals = $query->paginate(15)->withQueryString();

        // Rekap angka statistik kelas kosong & inval
        $statsQuery = PiketInvalClass::query();
        if ($startDate === $endDate) {
            $statsQuery->whereHas('journal', fn ($q) => $q->whereDate('date', $startDate));
        } else {
            $statsQuery->whereHas('journal', fn ($q) => $q->whereBetween('date', [$startDate, $endDate]));
        }
        if ($classroomId) {
            $statsQuery->where('classroom_id', $classroomId);
        }
        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->whereHas('absentTeacher', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('invalTeacher', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'unhandled' => (clone $statsQuery)->whereNull('inval_teacher_id')->count(),
            'handled' => (clone $statsQuery)->whereNotNull('inval_teacher_id')->count(),
        ];

        $classrooms = Classroom::where('academic_year', $academicYear)->orderBy('name')->get();
        $teachers = User::operationalPersonnel()->where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.inval', compact(
                'invals', 'classrooms', 'teachers', 'subjects', 'classroomId', 'status',
                'date', 'startDate', 'endDate', 'period', 'search', 'stats', 'academicYear'
            ));
        }

        return view('piket.inval.index', compact('invals', 'classrooms', 'teachers', 'subjects', 'date', 'academicYear'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'classroom_id' => 'required|exists:classrooms,id',
            'absent_teacher_id' => 'required|exists:users,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'inval_teacher_id' => 'nullable|exists:users,id',
            'period_start' => 'required|integer|min:1|max:15',
            'period_end' => 'required|integer|min:1|max:15|gte:period_start',
            'reason' => 'required|string|max:255',
            'assignment_material' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $journal = PiketJournal::firstOrCreate(
            ['date' => $validated['date']],
            [
                'academic_year' => session('active_academic_year', '2026/2027'),
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        $inval = PiketInvalClass::create([
            'piket_journal_id' => $journal->id,
            'classroom_id' => $validated['classroom_id'],
            'absent_teacher_id' => $validated['absent_teacher_id'],
            'subject_id' => $validated['subject_id'] ?? null,
            'inval_teacher_id' => $validated['inval_teacher_id'] ?? null,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'reason' => $validated['reason'],
            'assignment_material' => $validated['assignment_material'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        GtkPermit::syncFromPiketInvals();

        // Kirim notifikasi Telegram ke Waka Kurikulum dan Petugas Piket Hari Ini
        $this->telegramService->notifyInvalClassToWakaAndPiket($inval);

        return back()->with('success', 'Data kelas kosong & guru inval berhasil disimpan.');
    }

    public function update(Request $request, PiketInvalClass $inval): RedirectResponse
    {
        $validated = $request->validate([
            'inval_teacher_id' => 'nullable|exists:users,id',
            'period_start' => 'required|integer|min:1|max:15',
            'period_end' => 'required|integer|min:1|max:15|gte:period_start',
            'reason' => 'required|string|max:255',
            'assignment_material' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $inval->update($validated);

        GtkPermit::syncFromPiketInvals();

        return back()->with('success', 'Data guru inval berhasil diperbarui.');
    }

    public function destroy(PiketInvalClass $inval): RedirectResponse
    {
        $teacherId = $inval->absent_teacher_id;
        $journalDate = $inval->journal?->date ? Carbon::parse($inval->journal->date)->toDateString() : null;

        $inval->delete();

        if ($teacherId && $journalDate) {
            $hasOtherInvals = PiketInvalClass::where('absent_teacher_id', $teacherId)
                ->whereHas('journal', fn ($q) => $q->whereDate('date', $journalDate))
                ->exists();

            if (! $hasOtherInvals) {
                GtkPermit::where('user_id', $teacherId)
                    ->whereDate('start_date', $journalDate)
                    ->whereDate('end_date', $journalDate)
                    ->where('notes', 'like', '%Jurnal Piket%')
                    ->delete();
            }
        }

        return back()->with('success', 'Data kelas inval berhasil dihapus.');
    }
}
