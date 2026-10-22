<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\PiketJournal;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\DeviceDetector;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeroomStudentPermitController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Tampilkan daftar izin siswa di bawah perwalian wali kelas ini.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();
        $academicYear = session('active_academic_year', $setting->academic_year ?? '2026/2027');

        // Ambil rombel yang diampu oleh user sebagai wali kelas
        // Super admin atau waka kurikulum juga bisa memantau semua rombel
        $canViewAll = $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->hasRole('kepala_sekolah');

        if ($canViewAll) {
            $myClassrooms = Classroom::where('academic_year', $academicYear)
                ->orderBy('name')
                ->get();
        } else {
            $myClassrooms = Classroom::where('homeroom_teacher_id', $user->id)
                ->where('academic_year', $academicYear)
                ->orderBy('name')
                ->get();
        }

        $selectedClassroomId = $request->query('classroom_id', $myClassrooms->first()?->id);
        // Default ke hari ini jika parameter 'date' tidak ada sama sekali di query string
        // Tetapi jika user mengosongkan/mereset tanggal (date=""), tampilkan semua tanggal pada tahun ajaran ini
        $date = $request->has('date') ? $request->query('date') : now()->toDateString();
        $search = $request->query('search');

        $classroomIds = $myClassrooms->pluck('id');

        $query = PiketStudentPermit::with(['student', 'classroom', 'givenBy'])
            ->whereIn('classroom_id', $classroomIds)
            ->latest('permit_date');

        if ($selectedClassroomId) {
            $query->where('classroom_id', $selectedClassroomId);
        }

        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('permit_date', $date)
                    ->orWhere(function ($sub) use ($date) {
                        $sub->whereDate('permit_date', '<=', $date)
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $date);
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('permit_number', 'like', "%{$search}%");
            });
        }

        $permits = $query->paginate(20)->withQueryString();

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.homeroom_permits', compact(
                'myClassrooms', 'selectedClassroomId', 'permits', 'date', 'search', 'canViewAll'
            ));
        }

        return view('web.attendance.homeroom_permits', compact(
            'myClassrooms',
            'selectedClassroomId',
            'permits',
            'date',
            'search',
            'canViewAll'
        ));
    }

    /**
     * Tampilkan form catat izin siswa perwalian.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();
        $academicYear = session('active_academic_year', $setting->academic_year ?? '2026/2027');

        $canViewAll = $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->hasRole('kepala_sekolah');

        if ($canViewAll) {
            $myClassrooms = Classroom::where('academic_year', $academicYear)
                ->with('students')
                ->orderBy('name')
                ->get();
        } else {
            $myClassrooms = Classroom::where('homeroom_teacher_id', $user->id)
                ->where('academic_year', $academicYear)
                ->with('students')
                ->orderBy('name')
                ->get();
        }

        abort_if($myClassrooms->isEmpty(), 403, 'Anda belum ditugaskan sebagai Wali Kelas pada tahun ajaran aktif.');

        $selectedClassroomId = $request->query('classroom_id', $myClassrooms->first()->id);
        $selectedClassroom = $myClassrooms->firstWhere('id', $selectedClassroomId) ?? $myClassrooms->first();

        $students = Student::where('classroom_id', $selectedClassroom->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if (DeviceDetector::isMobile()) {
            return view('mobile.attendance.homeroom_permits_create', compact(
                'myClassrooms',
                'selectedClassroom',
                'students'
            ));
        }

        return view('attendance.homeroom_permits.create', compact(
            'myClassrooms',
            'selectedClassroom',
            'students'
        ));
    }

    /**
     * Simpan laporan izin siswa perwalian ke pusat (PiketStudentPermit).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'student_id' => 'required|exists:students,id',
            'permit_category' => 'required|in:sakit,izin,dispen',
            'permit_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:permit_date',
            'reason' => 'required|string|max:500',
            'attachment' => 'nullable|image|max:3072', // Max 3MB foto surat / screenshot WA
        ]);

        $classroom = Classroom::findOrFail($validated['classroom_id']);

        // Verifikasi kepemilikan wali kelas (kecuali admin/waka)
        $canManage = $user->isSuperAdmin() || $user->isWakaKurikulum() || $user->hasRole('kepala_sekolah') || ($classroom->homeroom_teacher_id === $user->id);
        abort_unless($canManage, 403, 'Anda bukan wali kelas untuk rombel ini.');

        $student = Student::findOrFail($validated['student_id']);
        $todayStr = Carbon::parse($validated['permit_date'])->toDateString();

        // Pencegahan Dobel Input untuk siswa yang sama pada tanggal yang sama
        $existingPermit = PiketStudentPermit::where('student_id', $student->id)
            ->where('permit_type', 'izin_tidak_masuk')
            ->whereDate('permit_date', $todayStr)
            ->first();

        if ($existingPermit) {
            return redirect()->route('attendance.homeroom-permits.index')
                ->with('warning', "Izin untuk siswa {$student->name} pada tanggal tersebut sudah pernah dicatat sebelumnya ({$existingPermit->permit_number}).");
        }

        // Cari atau buat jurnal piket untuk tanggal mulai
        $journal = PiketJournal::firstOrCreate(
            ['date' => $todayStr],
            [
                'academic_year' => session('active_academic_year', '2026/2027'),
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $user->id,
            ]
        );

        // Upload attachment jika ada
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('permits/evidence', 'public');
        }

        // Generate nomor izin
        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $validated['permit_date'],
            'end_date' => $validated['end_date'] ?? null,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => $validated['permit_category'],
            'reason' => $validated['reason'],
            'action_taken' => 'Pemberitahuan dari Orang Tua/Wali Murid ke Wali Kelas',
            'reporter_type' => 'wali_kelas',
            'attachment_path' => $attachmentPath,
            'given_by' => $user->id,
            'status' => 'diberikan',
            'notified_homeroom' => true,
        ]);

        // Kirim notifikasi bot Telegram piket / pengurus jika service aktif
        try {
            $this->telegramService->notifyStudentPermit($permit, $user, null);
        } catch (\Throwable $e) {
            // Log telegram silent
        }

        return redirect()->route('attendance.homeroom-permits.index')
            ->with('success', "Laporan izin untuk {$student->name} berhasil dicatat. Status izin otomatis terhubung ke Meja Piket dan Absen Guru Mapel.");
    }

    /**
     * Hapus laporan izin siswa perwalian.
     */
    public function destroy(PiketStudentPermit $permit): RedirectResponse
    {
        $user = Auth::user();

        $canManage = $user->isSuperAdmin() || $user->isWakaKurikulum() || ($permit->classroom?->homeroom_teacher_id === $user->id) || ($permit->given_by === $user->id);
        abort_unless($canManage, 403, 'Anda tidak berhak menghapus data ini.');

        if ($permit->attachment_path) {
            Storage::disk('public')->delete($permit->attachment_path);
        }

        $permit->delete();

        return back()->with('success', 'Data izin siswa berhasil dihapus.');
    }
}
