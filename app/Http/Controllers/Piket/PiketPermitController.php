<?php

namespace App\Http\Controllers\Piket;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\PiketJournal;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PiketPermitController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', session('active_academic_year', $setting->academic_year ?? '2026/2027'));

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

        $classrooms = Classroom::where('academic_year', $academicYear)
            ->orderBy('name')
            ->get();

        if (DeviceDetector::isMobile($request)) {
            return view('mobile.piket.permits_index', compact('permits', 'classrooms', 'date', 'startDate', 'endDate', 'period', 'type', 'classroomId', 'search', 'academicYear', 'stats'));
        }

        return view('piket.permits.index', compact('permits', 'classrooms', 'date', 'startDate', 'endDate', 'period', 'type', 'classroomId', 'search', 'academicYear', 'stats'));
    }

    public function create(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = session('active_academic_year', $setting->academic_year ?? '2026/2027');

        $classrooms = Classroom::where('academic_year', $academicYear)
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
            'student_id' => 'nullable|exists:students,id',
            'student_name' => 'required|string|max:255',
            'classroom_id' => 'required|exists:classrooms,id',
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

        // Pencegahan Dobel Input / Double Click untuk siswa yang sama pada hari yang sama dengan tipe izin sama
        if (! empty($validated['student_id'])) {
            $existingPermit = PiketStudentPermit::where('student_id', $validated['student_id'])
                ->where('permit_type', $validated['permit_type'])
                ->whereDate('permit_date', $todayStr)
                ->first();

            if ($existingPermit) {
                return redirect()->route('piket.permits.show', $existingPermit)
                    ->with('warning', "Surat izin untuk siswa {$validated['student_name']} dengan jenis ini sudah pernah dibuat hari ini ({$existingPermit->permit_number}).");
            }
        }

        // Simpan file bukti / lampiran surat jika diupload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('piket/permits', 'public');
        }

        // Cari atau buat jurnal harian untuk tanggal ini
        $journal = PiketJournal::firstOrCreate(
            ['date' => $todayStr],
            [
                'academic_year' => session('active_academic_year', '2026/2027'),
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => Auth::id(),
            ]
        );

        // Generate nomor izin unik: IZN-YYYYMMDD-001
        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        // Tentukan jam keluar / pulang berdasarkan jenis izin
        $finalDepartureTime = null;
        if ($validated['permit_type'] === 'izin_pulang_cepat') {
            $finalDepartureTime = $validated['home_departure_time'] ?: Carbon::now()->format('H:i');
        } elseif ($validated['permit_type'] === 'izin_keluar_sementara') {
            $finalDepartureTime = $validated['departure_time'] ?: Carbon::now()->format('H:i');
        }

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $validated['permit_date'],
            'end_date' => $validated['end_date'] ?? null,
            'student_id' => $validated['student_id'] ?? null,
            'student_name' => $validated['student_name'],
            'classroom_id' => $validated['classroom_id'],
            'permit_type' => $validated['permit_type'],
            'permit_category' => $validated['permit_category'] ?? ($validated['permit_type'] === 'terlambat' ? 'terlambat' : 'izin'),
            'reason' => $validated['reason'],
            'entry_time' => ($validated['permit_type'] === 'terlambat') ? (($validated['entry_time'] ?? null) ?: Carbon::now()->format('H:i')) : null,
            'departure_time' => $finalDepartureTime,
            'return_time' => ($validated['permit_type'] === 'izin_keluar_sementara') ? ($validated['return_time'] ?? null) : null,
            'period_assigned' => ($validated['permit_type'] === 'terlambat') ? ($validated['period_assigned'] ?? null) : null,
            'action_taken' => $validated['action_taken'] ?? null,
            'attachment_path' => $attachmentPath,
            'given_by' => Auth::id(),
            'status' => 'diberikan',
        ]);

        // Cek pengiriman notifikasi Telegram
        $classroom = Classroom::with('homeroomTeacher')->find($validated['classroom_id']);
        $homeroomTeacher = $classroom?->homeroomTeacher;

        // Cari guru mapel yang sedang mengajar saat ini
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
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

        $currentSchedule = $scheduleQuery->first();
        $subjectTeacher = $currentSchedule?->teacher;

        if ($validated['permit_type'] === 'terlambat') {
            $this->telegramService->notifyStudentLate($permit, $subjectTeacher, $homeroomTeacher);
        } else {
            $this->telegramService->notifyStudentPermit($permit, $homeroomTeacher, $subjectTeacher);
        }

        return redirect()->route('piket.permits.show', $permit)
            ->with('success', "Surat izin {$permit->permit_number} berhasil diterbitkan dan notifikasi telah diproses.");
    }

    public function show(PiketStudentPermit $permit): View
    {
        $permit->load(['classroom.homeroomTeacher', 'student', 'givenBy']);

        if (DeviceDetector::isMobile(request())) {
            return view('mobile.piket.permits_show', compact('permit'));
        }

        return view('piket.permits.show', compact('permit'));
    }

    public function printSlip(PiketStudentPermit $permit): View
    {
        $permit->load(['classroom.homeroomTeacher', 'student', 'givenBy']);
        $setting = SchoolSetting::getSetting();

        return view('piket.permits.slip', compact('permit', 'setting'));
    }

    public function destroy(PiketStudentPermit $permit): RedirectResponse
    {
        $permit->delete();

        return back()->with('success', 'Data izin siswa berhasil dihapus.');
    }
}
