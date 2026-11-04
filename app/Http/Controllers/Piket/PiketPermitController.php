<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\PiketJournal;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\AcademicYearContext;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PiketPermitController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

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

        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $type = $request->query('type');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('search');

        // Mode Periode: 'today' (default hari ini), 'week' (minggu ini), 'month' (bulan ini), 'range' (rentang kustom)
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
            // Default: 'today' (Hari ini atau tanggal tertentu yang dipilih)
            $period = 'today';
            $specificDate = $request->query('date', $today);
            $startDate = $specificDate;
            $endDate = $specificDate;
        }

        $date = $startDate; // Untuk kompatibilitas backward view

        $query = PiketStudentPermit::with(['classroom.homeroomTeacher', 'student', 'givenBy'])
            ->latest('permit_date')
            ->latest('created_at');

        if ($type) {
            $query->where('permit_type', $type);
        }

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($startDate === $endDate) {
            $query->whereDate('permit_date', $startDate);
        } else {
            $query->whereBetween('permit_date', [$startDate, $endDate]);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('permit_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $permits = $query->paginate(20)->withQueryString();

        // Ringkasan Rekap Sesuai Periode & Filter Aktif
        $statsQuery = PiketStudentPermit::query();
        if ($startDate === $endDate) {
            $statsQuery->whereDate('permit_date', $startDate);
        } else {
            $statsQuery->whereBetween('permit_date', [$startDate, $endDate]);
        }
        if ($classroomId) {
            $statsQuery->where('classroom_id', $classroomId);
        }
        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('permit_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $allPermitsInScope = $statsQuery->get(['permit_type']);
        $stats = [
            'total' => $allPermitsInScope->count(),
            'terlambat' => $allPermitsInScope->where('permit_type', 'terlambat')->count(),
            'keluar' => $allPermitsInScope->where('permit_type', 'izin_keluar_sementara')->count(),
            'pulang' => $allPermitsInScope->where('permit_type', 'izin_pulang_cepat')->count(),
            'tidak_masuk' => $allPermitsInScope->where('permit_type', 'izin_tidak_masuk')->count(),
        ];

        $classrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })->orderBy('name')->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.permits_index', compact('permits', 'classrooms', 'date', 'startDate', 'endDate', 'period', 'type', 'classroomId', 'search', 'academicYear', 'stats'));
        }

        return view('piket.permits.index', compact('permits', 'classrooms', 'date', 'startDate', 'endDate', 'period', 'type', 'classroomId', 'search', 'academicYear', 'stats'));
    }

    public function create(Request $request): View
    {
        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();
        $academicYear = $operationalYear->name;
        $ayModel = AcademicYear::where('name', $academicYear)->first();

        $classrooms = Classroom::where(function ($query) use ($academicYear, $ayModel) {
            if ($ayModel) {
                $query->where('academic_year_id', $ayModel->id);
            }
            $query->orWhere('academic_year', $academicYear);
        })
            ->with('homeroomTeacher')
            ->orderBy('name')
            ->get();

        $defaultType = $request->query('type', 'terlambat');

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.permits_create', compact('classrooms', 'defaultType', 'academicYear'));
        }

        return view('piket.permits.create', compact('classrooms', 'defaultType', 'academicYear'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permit_type' => 'required|in:terlambat,izin_keluar_sementara,izin_pulang_cepat,izin_tidak_masuk',
            'permit_date' => 'required|date',
            'end_date' => 'nullable|date',
            'permit_category' => 'nullable|in:sakit,izin,dispen,terlambat',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'student_id' => 'nullable|exists:students,id',
            'student_name' => 'nullable|string|max:255',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'reason' => 'required|string|max:500',
            'entry_time' => 'nullable|string',
            'departure_time' => 'nullable|string',
            'home_departure_time' => 'nullable|string',
            'return_time' => 'nullable|string',
            'period_assigned' => 'nullable|string|max:50',
            'action_taken' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notify_telegram' => 'nullable|boolean',
        ]);

        $todayStr = Carbon::parse($validated['permit_date'])->toDateString();

        // Simpan file bukti / lampiran surat jika diupload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('piket/permits', 'local');
        }

        $operationalYear = app(AcademicYearContext::class)->getOperationalYear();

        // Cari atau buat jurnal harian untuk tanggal ini
        $journal = PiketJournal::firstOrCreate(
            ['date' => $todayStr],
            [
                'academic_year' => $operationalYear->name,
                'semester' => $operationalYear->semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        // Tentukan jam keluar / pulang berdasarkan jenis izin
        $finalDepartureTime = null;
        if ($validated['permit_type'] === 'izin_pulang_cepat') {
            $finalDepartureTime = $validated['home_departure_time'] ?: Carbon::now()->format('H:i');
        } elseif ($validated['permit_type'] === 'izin_keluar_sementara') {
            $finalDepartureTime = $validated['departure_time'] ?: Carbon::now()->format('H:i');
        }

        $finalEntryTime = ($validated['permit_type'] === 'terlambat')
            ? (($validated['entry_time'] ?? null) ?: Carbon::now()->format('H:i'))
            : null;

        $studentIds = collect($validated['student_ids'] ?? [])->filter()->unique()->values();
        if ($studentIds->isEmpty() && ! empty($validated['student_id'])) {
            $studentIds = collect([(int) $validated['student_id']]);
        }

        // MODE 1: Siswa Terpilih (Single atau Multi / Rombongan)
        if ($studentIds->isNotEmpty()) {
            $students = Student::with(['classroom.homeroomTeacher'])->whereIn('id', $studentIds)->get();
            if ($students->isEmpty()) {
                return back()->withInput()->with('error', 'Data siswa yang dipilih tidak ditemukan.');
            }

            $batchId = $students->count() > 1
                ? 'GRP-'.Carbon::parse($todayStr)->format('Ymd').'-'.Str::upper(Str::random(5))
                : null;

            $createdPermits = collect();
            $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count();

            DB::transaction(function () use (
                $students, $journal, $todayStr, $validated, $finalEntryTime,
                $finalDepartureTime, $attachmentPath, $batchId, &$countToday, &$createdPermits
            ) {
                foreach ($students as $st) {
                    $existing = PiketStudentPermit::where('student_id', $st->id)
                        ->where('permit_type', $validated['permit_type'])
                        ->whereDate('permit_date', $todayStr)
                        ->first();

                    if ($existing) {
                        continue;
                    }

                    $countToday++;
                    $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

                    $permit = PiketStudentPermit::create([
                        'piket_journal_id' => $journal->id,
                        'permit_number' => $permitNumber,
                        'batch_id' => $batchId,
                        'permit_date' => $validated['permit_date'],
                        'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->toDateString() : null,
                        'student_id' => $st->id,
                        'student_name' => $st->name,
                        'classroom_id' => $st->classroom_id,
                        'permit_type' => $validated['permit_type'],
                        'permit_category' => $validated['permit_category'] ?? ($validated['permit_type'] === 'terlambat' ? 'terlambat' : 'izin'),
                        'reason' => $validated['reason'],
                        'entry_time' => $finalEntryTime,
                        'departure_time' => $finalDepartureTime,
                        'return_time' => ($validated['permit_type'] === 'izin_keluar_sementara') ? ($validated['return_time'] ?? null) : null,
                        'period_assigned' => ($validated['permit_type'] === 'terlambat') ? ($validated['period_assigned'] ?? null) : null,
                        'action_taken' => $validated['action_taken'] ?? null,
                        'attachment_path' => $attachmentPath,
                        'given_by' => Auth::id(),
                        'status' => 'diberikan',
                    ]);

                    $createdPermits->push($permit);
                }
            });

            if ($createdPermits->isEmpty()) {
                return back()->withInput()->with('warning', 'Seluruh siswa yang dipilih sudah memiliki surat izin jenis ini untuk hari ini.');
            }

            // Notifikasi Telegram per Rombel/Kelas
            $dayNames = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ];
            $dayName = $dayNames[Carbon::parse($validated['permit_date'])->dayOfWeekIso] ?? 'Senin';

            $groupedByClass = $createdPermits->groupBy('classroom_id');
            foreach ($groupedByClass as $clsId => $classPermits) {
                $classroom = Classroom::with('homeroomTeacher')->find($clsId);
                $homeroomTeacher = $classroom?->homeroomTeacher;

                $scheduleQuery = LessonSchedule::where('classroom_id', $clsId)
                    ->where('day', $dayName)
                    ->with('teacher');

                if (! empty($validated['period_assigned']) && is_numeric($validated['period_assigned'])) {
                    $p = (int) $validated['period_assigned'];
                    $scheduleQuery->where('start_period', '<=', $p)
                        ->where('end_period', '>=', $p);
                }

                $subjectTeacher = $scheduleQuery->first()?->teacher;

                foreach ($classPermits as $permitItem) {
                    try {
                        if ($validated['permit_type'] === 'terlambat') {
                            $this->telegramService->notifyStudentLate($permitItem, $subjectTeacher, $homeroomTeacher);
                        } else {
                            $this->telegramService->notifyStudentPermit($permitItem, $homeroomTeacher, $subjectTeacher);
                        }
                    } catch (\Throwable) {
                    }
                }
            }

            $firstPermit = $createdPermits->first();
            $count = $createdPermits->count();

            if ($count > 1) {
                return redirect()->route('piket.permits.show', $firstPermit)
                    ->with('success', "Surat izin rombongan untuk {$count} siswa berhasil diterbitkan (Kode Batch: {$batchId}). Anda dapat mencetak slip terkelompok per rombel.");
            }

            return redirect()->route('piket.permits.show', $firstPermit)
                ->with('success', "Surat izin {$firstPermit->permit_number} berhasil diterbitkan.");
        }

        // MODE 2: Input Manual (Siswa Tanpa Data Master)
        if (empty($validated['student_name']) || empty($validated['classroom_id'])) {
            return back()->withInput()->with('error', 'Nama siswa dan kelas harus diisi.');
        }

        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $validated['permit_date'],
            'end_date' => ! empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->toDateString() : null,
            'student_id' => null,
            'student_name' => $validated['student_name'],
            'classroom_id' => $validated['classroom_id'],
            'permit_type' => $validated['permit_type'],
            'permit_category' => $validated['permit_category'] ?? ($validated['permit_type'] === 'terlambat' ? 'terlambat' : 'izin'),
            'reason' => $validated['reason'],
            'entry_time' => $finalEntryTime,
            'departure_time' => $finalDepartureTime,
            'return_time' => ($validated['permit_type'] === 'izin_keluar_sementara') ? ($validated['return_time'] ?? null) : null,
            'period_assigned' => ($validated['permit_type'] === 'terlambat') ? ($validated['period_assigned'] ?? null) : null,
            'action_taken' => $validated['action_taken'] ?? null,
            'attachment_path' => $attachmentPath,
            'given_by' => Auth::id(),
            'status' => 'diberikan',
        ]);

        $classroom = Classroom::with('homeroomTeacher')->find($validated['classroom_id']);
        $homeroomTeacher = $classroom?->homeroomTeacher;

        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $dayName = $dayNames[Carbon::parse($validated['permit_date'])->dayOfWeekIso] ?? 'Senin';

        $scheduleQuery = LessonSchedule::where('classroom_id', $validated['classroom_id'])
            ->where('day', $dayName)
            ->with('teacher');

        if (! empty($validated['period_assigned']) && is_numeric($validated['period_assigned'])) {
            $p = (int) $validated['period_assigned'];
            $scheduleQuery->where('start_period', '<=', $p)
                ->where('end_period', '>=', $p);
        }

        $subjectTeacher = $scheduleQuery->first()?->teacher;

        try {
            if ($validated['permit_type'] === 'terlambat') {
                $this->telegramService->notifyStudentLate($permit, $subjectTeacher, $homeroomTeacher);
            } else {
                $this->telegramService->notifyStudentPermit($permit, $homeroomTeacher, $subjectTeacher);
            }
        } catch (\Throwable) {
        }

        return redirect()->route('piket.permits.show', $permit)
            ->with('success', "Surat izin {$permit->permit_number} berhasil diterbitkan.");
    }

    public function show(PiketStudentPermit $permit): View
    {
        $permit->load(['classroom.homeroomTeacher', 'student', 'givenBy']);

        if (DeviceDetector::isMobile(request())) {
            return view('mobile.piket.permits_show', compact('permit'));
        }

        return view('piket.permits.show', compact('permit'));
    }

    public function printSlip(PiketStudentPermit $permit, Request $request): View
    {
        if ($permit->batch_id && $request->boolean('batch')) {
            return $this->printBatchSlip($permit->batch_id, $request);
        }

        $permit->load(['classroom.homeroomTeacher', 'student', 'givenBy']);
        $setting = SchoolSetting::getSetting();

        return view('piket.permits.slip', compact('permit', 'setting'));
    }

    public function printBatchSlip(string $batchId, Request $request): View
    {
        $permits = PiketStudentPermit::where('batch_id', $batchId)
            ->with(['classroom.homeroomTeacher', 'student', 'givenBy'])
            ->orderBy('classroom_id')
            ->orderBy('student_name')
            ->get();

        if ($permits->isEmpty()) {
            abort(404, 'Data izin rombongan tidak ditemukan.');
        }

        $groupedPermits = $permits->groupBy('classroom_id');
        $setting = SchoolSetting::getSetting();

        return view('piket.permits.slip', [
            'isBatch' => true,
            'permits' => $permits,
            'groupedPermits' => $groupedPermits,
            'setting' => $setting,
            'permit' => $permits->first(),
            'batchId' => $batchId,
        ]);
    }

    public function destroy(PiketStudentPermit $permit): RedirectResponse
    {
        if ($permit->attachment_path) {
            if (Storage::disk('local')->exists($permit->attachment_path)) {
                Storage::disk('local')->delete($permit->attachment_path);
            } elseif (Storage::disk('public')->exists($permit->attachment_path)) {
                Storage::disk('public')->delete($permit->attachment_path);
            }
        }

        $permit->delete();

        return back()->with('success', 'Data izin siswa berhasil dihapus.');
    }

    /**
     * Download or view private student permit attachment with strict authorization.
     */
    public function downloadAttachment(PiketStudentPermit $permit)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $isAuthorized = $user->isSuperAdmin()
            || $user->isPrincipal()
            || (method_exists($user, 'isWakaKesiswaan') && $user->isWakaKesiswaan())
            || (method_exists($user, 'isWakaKurikulum') && $user->isWakaKurikulum())
            || $user->can('operate_piket')
            || $user->can('manage_piket_cases')
            || ($permit->given_by === $user->id)
            || ($permit->classroom_id && $permit->classroom && $permit->classroom->homeroom_teacher_id === $user->id);

        if (! $isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat lampiran izin siswa ini.');
        }

        $path = $permit->attachment_path;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Lampiran surat izin tidak ditemukan.');
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (! $disk) {
            abort(404, 'File lampiran tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->response($path);
    }
}
