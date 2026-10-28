<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentVisitationController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    /**
     * Tampilkan daftar kegiatan visitasi dan pemantauan siswa.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        if (! $user->canAccessVisitations($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka modul Visitasi dan Pemantauan Siswa.');
        }

        $status = $request->query('status');
        $visitType = $request->query('visit_type');
        $search = $request->query('search');

        $query = StudentVisitation::with([
            'student.classroom',
            'requester',
            'primaryOfficer',
            'secondaryOfficer',
        ])->where('academic_year', $academicYear)
            ->latest('planned_date')
            ->latest('id');

        $isPrivileged = $user->isSuperAdmin()
            || $user->isPrincipal()
            || $user->canOperateBk($academicYear)
            || $user->canManageDiscipline($academicYear);

        if (! $isPrivileged) {
            $query->where(function ($q) use ($user, $academicYear) {
                $q->where('requested_by', $user->id)
                    ->orWhere('primary_officer_id', $user->id)
                    ->orWhere('secondary_officer_id', $user->id)
                    ->orWhereHas('student.studentClassrooms', function ($sc) use ($user, $academicYear) {
                        $sc->where('academic_year_name', $academicYear)
                            ->whereHas('classroom', fn ($c) => $c->where('homeroom_teacher_id', $user->id));
                    })
                    ->orWhereHas('student.classroom', function ($c) use ($user) {
                        $c->where('homeroom_teacher_id', $user->id);
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($visitType) {
            $query->where('visit_type', $visitType);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%"))
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('result_summary', 'like', "%{$search}%");
            });
        }

        $visitations = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => StudentVisitation::where('academic_year', $academicYear)->count(),
            'dijadwalkan' => StudentVisitation::where('academic_year', $academicYear)->whereIn('status', ['diajukan', 'dijadwalkan'])->count(),
            'terlaksana' => StudentVisitation::where('academic_year', $academicYear)->where('status', 'terlaksana')->count(),
            'selesai' => StudentVisitation::where('academic_year', $academicYear)->where('status', 'selesai')->count(),
        ];

        $availableYears = AcademicYear::orderByDesc('name')->pluck('name')->all();
        if (empty($availableYears)) {
            $availableYears = [$academicYear];
        }

        return view('visitations.index', compact(
            'visitations',
            'stats',
            'status',
            'visitType',
            'search',
            'academicYear',
            'availableYears',
            'isPrivileged'
        ));
    }

    /**
     * Form pengajuan / penjadwalan visitasi dan pemantauan siswa baru.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $academicYearContext = app(AcademicYearContext::class);
        $academicYear = $request->query('academic_year') ?? $academicYearContext->getViewingYear();

        $selectedStudentId = $request->query('student_id');
        $selectedStudent = $selectedStudentId ? Student::with('classroom')->find($selectedStudentId) : null;

        if (! $user->canCreateVisitation($selectedStudent, $academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengajukan visitasi siswa ini.');
        }

        $sourceType = $request->query('source_type');
        $sourceId = $request->query('source_id');
        $visitType = $request->query('visit_type', 'kunjungan_rumah');

        // Daftar siswa aktif
        $students = Student::where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name', 'nisn', 'classroom_id']);

        // Daftar calon petugas (Guru BK, Wali Kelas, Tim Kedisiplinan, Guru/Staf)
        $officers = User::where('is_active', true)
            ->where('role_id', '!=', 1) // bukan super_admin internal
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'position']);

        if ($officers->isEmpty()) {
            $officers = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'position']);
        }

        return view('visitations.create', compact(
            'students',
            'selectedStudent',
            'officers',
            'sourceType',
            'sourceId',
            'visitType',
            'academicYear'
        ));
    }

    /**
     * Simpan pengajuan visitasi siswa ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'visit_type' => 'required|in:kunjungan_rumah,pemantauan_khusus,tindak_lanjut_absensi,komunikasi_ortu,tindak_lanjut_kasus,lainnya',
            'source_type' => 'nullable|in:inisiatif_wali_kelas,rujukan_bk,kasus_kedisiplinan,masalah_kehadiran,mandiri,lainnya',
            'source_id' => 'nullable|integer',
            'reason' => 'required|string',
            'primary_officer_id' => 'required|exists:users,id',
            'secondary_officer_id' => 'nullable|exists:users,id|different:primary_officer_id',
            'planned_date' => 'required|date',
            'status' => 'nullable|in:diajukan,dijadwalkan,terlaksana,selesai',
            'confidential_notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYearContext = app(AcademicYearContext::class);
        $operationalYear = $academicYearContext->getOperationalYear();
        $academicYear = $operationalYear->name;

        if (! $user->canCreateVisitation($student, $academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuat penugasan visitasi siswa ini.');
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('visitations/attachments', 'local');
        }

        $visitation = StudentVisitation::create([
            'student_id' => $student->id,
            'academic_year_id' => $operationalYear->id,
            'academic_year' => $academicYear,
            'visit_type' => $validated['visit_type'],
            'source_type' => $validated['source_type'] ?? 'mandiri',
            'source_id' => $validated['source_id'] ?? null,
            'reason' => $validated['reason'],
            'requested_by' => $user->id,
            'primary_officer_id' => $validated['primary_officer_id'],
            'secondary_officer_id' => $validated['secondary_officer_id'] ?? null,
            'planned_date' => $validated['planned_date'],
            'status' => $validated['status'] ?? 'dijadwalkan',
            'confidential_notes' => $validated['confidential_notes'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        // Kirim notifikasi Telegram penugasan visitasi
        $this->telegramService->notifyVisitation($visitation->fresh(['student.classroom.homeroomTeacher', 'primaryOfficer', 'secondaryOfficer', 'requester']), 'created');

        return redirect()->route('visitations.show', $visitation)
            ->with('success', 'Rencana visitasi dan pemantauan siswa berhasil dijadwalkan.');
    }

    /**
     * Tampilkan detail hasil visitasi dan pemantauan siswa.
     */
    public function show(StudentVisitation $visitation): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $visitation->canUserView($user, $visitation->academic_year)) {
            abort(403, 'Anda tidak memiliki wewenang untuk melihat catatan visitasi siswa ini.');
        }

        $visitation->load([
            'student.classroom',
            'requester',
            'primaryOfficer',
            'secondaryOfficer',
            'academicYearRelation',
        ]);

        $canAccessConfidential = $visitation->canUserAccessConfidential($user, $visitation->academic_year);
        $canEdit = $visitation->canUserEdit($user, $visitation->academic_year);

        return view('visitations.show', compact('visitation', 'canAccessConfidential', 'canEdit'));
    }

    /**
     * Form edit pelaksanaan / laporan hasil visitasi.
     */
    public function edit(StudentVisitation $visitation): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $visitation->canUserEdit($user, $visitation->academic_year)) {
            abort(403, 'Anda tidak memiliki hak untuk memperbarui laporan visitasi ini.');
        }

        $visitation->load(['student.classroom', 'primaryOfficer', 'secondaryOfficer']);

        $officers = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']);
        $canAccessConfidential = $visitation->canUserAccessConfidential($user, $visitation->academic_year);

        return view('visitations.edit', compact('visitation', 'officers', 'canAccessConfidential'));
    }

    /**
     * Update hasil pelaksanaan visitasi, catatan tindak lanjut, dan status.
     */
    public function update(Request $request, StudentVisitation $visitation): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $visitation->canUserEdit($user, $visitation->academic_year)) {
            abort(403, 'Anda tidak memiliki hak untuk memperbarui laporan visitasi ini.');
        }

        $validated = $request->validate([
            'executed_date' => 'nullable|date',
            'status' => 'required|in:diajukan,dijadwalkan,terlaksana,selesai,dibatalkan',
            'result_summary' => 'nullable|string',
            'follow_up_plan' => 'nullable|string',
            'confidential_notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        $updateData = [
            'executed_date' => $validated['executed_date'] ?? null,
            'status' => $validated['status'],
            'result_summary' => $validated['result_summary'] ?? null,
            'follow_up_plan' => $validated['follow_up_plan'] ?? null,
        ];

        // Hanya perbarui catatan rahasia bila user berhak
        if ($visitation->canUserAccessConfidential($user, $visitation->academic_year)) {
            $updateData['confidential_notes'] = $validated['confidential_notes'] ?? null;
        }

        if ($request->hasFile('attachment')) {
            if ($visitation->attachment_path && Storage::disk('local')->exists($visitation->attachment_path)) {
                Storage::disk('local')->delete($visitation->attachment_path);
            }
            $updateData['attachment_path'] = $request->file('attachment')->store('visitations/attachments', 'local');
        }

        $visitation->update($updateData);

        // Kirim notifikasi Telegram pembaruan hasil visitasi
        $this->telegramService->notifyVisitation($visitation->fresh(['student.classroom.homeroomTeacher', 'primaryOfficer', 'secondaryOfficer', 'requester']), 'updated');

        return redirect()->route('visitations.show', $visitation)
            ->with('success', 'Laporan pelaksanaan dan hasil visitasi siswa berhasil diperbarui.');
    }

    /**
     * Unduh lampiran / dokumen berita acara visitasi secara privat & aman.
     */
    public function downloadAttachment(StudentVisitation $visitation)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $visitation->canUserView($user, $visitation->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh dokumen visitasi ini.');
        }

        $path = $visitation->attachment_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File lampiran visitasi tidak ditemukan di penyimpanan privat.');
        }

        $filename = 'Dokumen_Visitasi_'.str_replace(' ', '_', $visitation->student->name).'_'.$visitation->planned_date->format('Ymd');
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($path, "{$filename}.{$ext}");
    }

    /**
     * Ekspor Berita Acara Kunjungan Rumah / Lembar Visitasi dalam format PDF.
     */
    public function exportPdf(StudentVisitation $visitation)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $visitation->canUserView($user, $visitation->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencetak berita acara visitasi ini.');
        }

        $visitation->load([
            'student.classroom.homeroomTeacher',
            'requestedBy',
            'primaryOfficer',
            'secondaryOfficer',
            'bkService.counselor',
        ]);

        $setting = SchoolSetting::getSetting();
        $canAccessConfidential = $visitation->canUserAccessConfidential($user);

        $pdf = Pdf::loadView('visitations.pdf', compact('visitation', 'setting', 'canAccessConfidential'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = 'Berita_Acara_Visitasi_'.Str::slug($visitation->student->name).'_'.($visitation->actual_date ? $visitation->actual_date->format('Ymd') : date('Ymd')).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Hapus data visitasi.
     */
    public function destroy(StudentVisitation $visitation): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $canDelete = $user->isSuperAdmin()
            || ($user->id === $visitation->requested_by && $visitation->status !== 'selesai')
            || $user->canManageDiscipline($visitation->academic_year);

        if (! $canDelete) {
            abort(403, 'Anda tidak berwenang untuk menghapus catatan visitasi ini.');
        }

        if ($visitation->attachment_path && Storage::disk('local')->exists($visitation->attachment_path)) {
            Storage::disk('local')->delete($visitation->attachment_path);
        }

        $visitation->delete();

        return redirect()->route('visitations.index')
            ->with('success', 'Catatan visitasi berhasil dihapus.');
    }
}
