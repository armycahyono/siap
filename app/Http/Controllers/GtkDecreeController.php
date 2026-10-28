<?php

namespace App\Http\Controllers;

use App\Exceptions\GtkDecreeActivationException;
use App\Exceptions\GtkDecreeCancellationException;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\GtkDecreeRecipient;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\User;
use App\Services\GtkDecreeActivationService;
use App\Services\GtkDecreeCancellationPreviewService;
use App\Services\GtkDecreeCancellationService;
use App\Services\GtkDecreeImpactPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GtkDecreeController extends Controller
{
    /**
     * Memeriksa otorisasi pengelola SK GTK (Super Admin, Waka Kurikulum, Kepala Sekolah, atau pemegang izin terkait).
     */
    protected function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah'])
            || $user->hasPermission('manage_master_data')
            || $user->hasPermission('manage_teachers')
            || $user->hasPermission('operate_tu');
    }

    /**
     * Memeriksa otorisasi akses baca dokumen SK.
     */
    protected function canView(User $user, ?GtkDecree $decree = null): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        if ($decree && $decree->status !== GtkDecree::STATUS_DRAFT) {
            return $decree->recipients()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Memastikan SK berstatus draft sebelum melakukan operasi perubahan data.
     */
    protected function ensureIsDraft(GtkDecree $decree): void
    {
        if ($decree->status !== GtkDecree::STATUS_DRAFT) {
            abort(403, "SK dengan status '{$decree->status}' bersifat arsip terkunci dan tidak dapat diubah.");
        }
    }

    /**
     * Halaman Arsip Daftar SK GTK.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $this->canManage($user)) {
            // Jika pengguna biasa (guru), hanya tampilkan SK non-draft di mana mereka terdaftar
            if ($user) {
                $query = GtkDecree::query()
                    ->where('status', '!=', GtkDecree::STATUS_DRAFT)
                    ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id));
            } else {
                abort(401);
            }
        } else {
            $query = GtkDecree::query();
        }

        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $academicYear = $request->query('academic_year');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('signer_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($type) && $type !== 'all') {
            $query->where('type', $type);
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($academicYear) && $academicYear !== 'all') {
            $query->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)
                    ->orWhere('academic_year_id', $academicYear);
            });
        }

        // Metrik Ringkasan Aktual
        $totalAll = GtkDecree::count();
        $totalDraft = GtkDecree::where('status', GtkDecree::STATUS_DRAFT)->count();
        $totalActive = GtkDecree::where('status', GtkDecree::STATUS_ACTIVE)->count();
        $totalArchived = GtkDecree::whereIn('status', [GtkDecree::STATUS_EXPIRED, GtkDecree::STATUS_CANCELLED])->count();
        $totalPersonnelInvolved = DB::table('gtk_decree_recipients')->distinct('user_id')->count('user_id');

        $decrees = $query->with(['academicYear'])
            ->withCount(['recipients', 'details'])
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $academicYears = AcademicYear::orderByDesc('name')->get();
        $activeYear = AcademicYear::getActive();

        return view('master.gtk_decrees.index', compact(
            'decrees',
            'search',
            'type',
            'status',
            'academicYear',
            'academicYears',
            'activeYear',
            'totalAll',
            'totalDraft',
            'totalActive',
            'totalArchived',
            'totalPersonnelInvolved'
        ));
    }

    /**
     * Form Buat Draf SK GTK Baru.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuat SK GTK.');
        }

        $academicYears = AcademicYear::orderByDesc('name')->get();
        $activeYear = AcademicYear::getActive();
        $types = GtkDecree::TYPES;

        return view('master.gtk_decrees.create', compact('academicYears', 'activeYear', 'types'));
    }

    /**
     * Simpan Draf SK GTK Baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuat SK GTK.');
        }

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:100', 'unique:gtk_decrees,number'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(GtkDecree::TYPES))],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'issued_date' => ['required', 'date'],
            'tmt_date' => ['required', 'date'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'signer_name' => ['nullable', 'string', 'max:150'],
            'signer_title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'pdf_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
        ], [
            'number.required' => 'Nomor SK wajib diisi.',
            'number.unique' => 'Nomor SK ini sudah terdaftar dalam sistem.',
            'title.required' => 'Judul SK wajib diisi.',
            'type.required' => 'Pilih jenis SK yang sesuai.',
            'issued_date.required' => 'Tanggal penetapan SK wajib diisi.',
            'tmt_date.required' => 'TMT (Terhitung Mulai Tanggal) wajib diisi.',
            'valid_from.required' => 'Tanggal mulai berlaku efektif wajib diisi.',
            'valid_until.after_or_equal' => 'Tanggal akhir berlaku tidak boleh mendahului tanggal mulai berlaku.',
            'pdf_document.mimes' => 'Berkas lampiran harus berupa dokumen PDF (.pdf).',
            'pdf_document.max' => 'Ukuran berkas PDF maksimal 10 MB.',
        ]);

        $filePath = null;
        $originalFilename = null;
        $fileSize = null;

        if ($request->hasFile('pdf_document')) {
            $uploadedFile = $request->file('pdf_document');
            $filePath = $uploadedFile->store('sk_gtk', 'local');
            $originalFilename = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();
        }

        $academicYearName = null;
        if (! empty($validated['academic_year_id'])) {
            $ay = AcademicYear::find($validated['academic_year_id']);
            $academicYearName = $ay?->name;
        }

        $decree = GtkDecree::create([
            'number' => trim($validated['number']),
            'title' => trim($validated['title']),
            'type' => $validated['type'],
            'academic_year_id' => $validated['academic_year_id'] ?? null,
            'academic_year' => $academicYearName,
            'issued_date' => $validated['issued_date'],
            'tmt_date' => $validated['tmt_date'],
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'] ?? null,
            'signer_name' => $validated['signer_name'] ?? null,
            'signer_title' => $validated['signer_title'] ?? null,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'file_size' => $fileSize,
            'description' => $validated['description'] ?? null,
            'status' => GtkDecree::STATUS_DRAFT,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('gtk-decrees.show', $decree)
            ->with('success', "Draf SK GTK No. {$decree->number} berhasil dibuat. Silakan kelola daftar penerima dan rincian penugasan.");
    }

    /**
     * Halaman Detail SK GTK (Ringkasan, Dokumen, Penerima, Rincian Keputusan, Audit).
     */
    public function show(Request $request, GtkDecree $gtkDecree): View
    {
        $user = $request->user();
        if (! $user || ! $this->canView($user, $gtkDecree)) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat SK ini.');
        }

        $gtkDecree->load([
            'academicYear',
            'recipients.user.role',
            'details.recipient.user',
            'details.user.role',
            'details.subject',
            'details.classroom',
            'details.structuralPosition',
            'details.role',
            'createdBy',
            'updatedBy',
            'activatedBy',
            'cancelledBy',
        ]);

        $canManage = $this->canManage($user);

        // Data Master untuk Modal/Form Input Rincian
        $operationalPersonnel = User::operationalPersonnel()
            ->where('is_active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();
        $structuralPositions = StructuralPosition::where('is_active', true)->orderBy('order')->get();
        $roles = Role::orderBy('name')->get();

        // Hitung analisis pratinjau dampak secara dinamis (read-only)
        $impactPreview = app(GtkDecreeImpactPreviewService::class)->preview($gtkDecree);

        return view('master.gtk_decrees.show', compact(
            'gtkDecree',
            'canManage',
            'operationalPersonnel',
            'subjects',
            'classrooms',
            'structuralPositions',
            'roles',
            'impactPreview'
        ));
    }

    /**
     * Halaman Khusus Pratinjau Dampak Penerapan SK GTK (Read-Only).
     */
    public function previewImpact(Request $request, GtkDecree $gtkDecree, GtkDecreeImpactPreviewService $previewService): View
    {
        $user = $request->user();
        if (! $user || ! $this->canView($user, $gtkDecree)) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat pratinjau SK ini.');
        }

        $impact = $previewService->preview($gtkDecree);
        $canManage = $this->canManage($user);

        return view('master.gtk_decrees.preview', compact('gtkDecree', 'impact', 'canManage'));
    }

    /**
     * Halaman Khusus Pratinjau Dampak Pembatalan SK GTK (Read-Only).
     */
    public function previewCancellation(Request $request, GtkDecree $gtkDecree, GtkDecreeCancellationPreviewService $cancellationService): View
    {
        $user = $request->user();
        if (! $user || ! $this->canView($user, $gtkDecree)) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat pratinjau pembatalan SK ini.');
        }

        $cancellation = $cancellationService->preview($gtkDecree);
        $canManage = $this->canManage($user);

        return view('master.gtk_decrees.cancel_preview', compact('gtkDecree', 'cancellation', 'canManage'));
    }

    /**
     * Form Edit Metadata Draf SK GTK.
     */
    public function edit(Request $request, GtkDecree $gtkDecree): View
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit SK GTK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $academicYears = AcademicYear::orderByDesc('name')->get();
        $types = GtkDecree::TYPES;

        return view('master.gtk_decrees.edit', compact('gtkDecree', 'academicYears', 'types'));
    }

    /**
     * Perbarui Metadata Draf SK GTK & Penanganan Berkas PDF Aman.
     */
    public function update(Request $request, GtkDecree $gtkDecree): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit SK GTK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:100', Rule::unique('gtk_decrees', 'number')->ignore($gtkDecree->id)],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(GtkDecree::TYPES))],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'issued_date' => ['required', 'date'],
            'tmt_date' => ['required', 'date'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'signer_name' => ['nullable', 'string', 'max:150'],
            'signer_title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'pdf_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'number.required' => 'Nomor SK wajib diisi.',
            'number.unique' => 'Nomor SK ini sudah digunakan oleh SK lain.',
            'title.required' => 'Judul SK wajib diisi.',
            'issued_date.required' => 'Tanggal penetapan SK wajib diisi.',
            'tmt_date.required' => 'TMT wajib diisi.',
            'valid_from.required' => 'Tanggal mulai berlaku efektif wajib diisi.',
            'valid_until.after_or_equal' => 'Tanggal akhir berlaku tidak boleh mendahului tanggal mulai berlaku.',
            'pdf_document.mimes' => 'Berkas lampiran harus berupa dokumen PDF (.pdf).',
            'pdf_document.max' => 'Ukuran berkas PDF maksimal 10 MB.',
        ]);

        $academicYearName = $gtkDecree->academic_year;
        if (! empty($validated['academic_year_id'])) {
            $ay = AcademicYear::find($validated['academic_year_id']);
            $academicYearName = $ay?->name;
        }

        $oldFilePath = $gtkDecree->file_path;
        $newFilePath = null;
        $newOriginalFilename = null;
        $newFileSize = null;

        if ($request->hasFile('pdf_document')) {
            $uploadedFile = $request->file('pdf_document');
            $newFilePath = $uploadedFile->store('sk_gtk', 'local');
            $newOriginalFilename = $uploadedFile->getClientOriginalName();
            $newFileSize = $uploadedFile->getSize();
        }

        try {
            $updateData = [
                'number' => trim($validated['number']),
                'title' => trim($validated['title']),
                'type' => $validated['type'],
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'academic_year' => $academicYearName,
                'issued_date' => $validated['issued_date'],
                'tmt_date' => $validated['tmt_date'],
                'valid_from' => $validated['valid_from'],
                'valid_until' => $validated['valid_until'] ?? null,
                'signer_name' => $validated['signer_name'] ?? null,
                'signer_title' => $validated['signer_title'] ?? null,
                'description' => $validated['description'] ?? null,
                'updated_by' => $user->id,
            ];

            if ($newFilePath) {
                $updateData['file_path'] = $newFilePath;
                $updateData['original_filename'] = $newOriginalFilename;
                $updateData['file_size'] = $newFileSize;
            }

            $gtkDecree->update($updateData);

            // Bersihkan berkas lama jika berhasil diperbarui dengan berkas baru
            if ($newFilePath && $oldFilePath && $oldFilePath !== $newFilePath && Storage::disk('local')->exists($oldFilePath)) {
                Storage::disk('local')->delete($oldFilePath);
            }
        } catch (\Throwable $e) {
            // Jika update database gagal, batalkan berkas baru yang telah diupload
            if ($newFilePath && Storage::disk('local')->exists($newFilePath)) {
                Storage::disk('local')->delete($newFilePath);
            }
            throw $e;
        }

        return redirect()->route('gtk-decrees.show', $gtkDecree)
            ->with('success', 'Metadata draf SK GTK berhasil diperbarui.');
    }

    /**
     * Hapus Draf SK GTK Beserta Dokumen Privat (Hanya jika berstatus draft).
     */
    public function destroy(Request $request, GtkDecree $gtkDecree): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus SK GTK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $number = $gtkDecree->number;
        $filePath = $gtkDecree->file_path;

        // Hapus draf (cascade delete penerima dan detail, tanpa mempengaruhi tabel penugasan operasional)
        $gtkDecree->delete();

        // Hapus berkas fisik di disk privat
        if ($filePath && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()->route('gtk-decrees.index')
            ->with('success', "Draf SK GTK No. {$number} berhasil dihapus.");
    }

    /**
     * Unduh / Pratinjau Dokumen PDF Privat dengan Proteksi Akses Ketat.
     */
    public function downloadDocument(Request $request, GtkDecree $gtkDecree): Response
    {
        $user = $request->user();
        if (! $user || ! $this->canView($user, $gtkDecree)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh dokumen SK ini.');
        }

        $path = $gtkDecree->file_path;

        if (empty($path) || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Berkas dokumen PDF SK tidak ditemukan di penyimpanan server.');
        }

        $filename = $gtkDecree->original_filename ?: (Str::slug($gtkDecree->number ?: 'sk_gtk', '_').'.pdf');

        if ($request->query('preview') === '1') {
            return Storage::disk('local')->response($path, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return Storage::disk('local')->download($path, $filename);
    }

    /**
     * Tambah Penerima GTK ke Draf SK (Mendukung pemilihan banyak GTK sekaligus).
     */
    public function addRecipients(Request $request, GtkDecree $gtkDecree): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola penerima SK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'user_ids.required' => 'Pilih minimal satu personil GTK untuk ditambahkan sebagai penerima SK.',
            'user_ids.min' => 'Pilih minimal satu personil GTK.',
        ]);

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($validated['user_ids'] as $userId) {
            $exists = $gtkDecree->recipients()->where('user_id', $userId)->exists();
            if ($exists) {
                $skippedCount++;

                continue;
            }

            GtkDecreeRecipient::create([
                'gtk_decree_id' => $gtkDecree->id,
                'user_id' => $userId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $addedCount++;
        }

        $message = "Berhasil menambahkan {$addedCount} penerima GTK ke dalam draf SK.";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} personil dilewati karena sudah terdaftar sebelumnya).";
        }

        return redirect()->route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'recipients'])
            ->with('success', $message);
    }

    /**
     * Hapus Penerima GTK dari Draf SK.
     */
    public function removeRecipient(Request $request, GtkDecree $gtkDecree, GtkDecreeRecipient $recipient): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola penerima SK.');
        }

        $this->ensureIsDraft($gtkDecree);

        if ((int) $recipient->gtk_decree_id !== (int) $gtkDecree->id) {
            abort(404, 'Penerima tidak terdaftar pada SK ini.');
        }

        $userName = $recipient->user?->name ?? 'GTK';
        $recipient->delete();

        return redirect()->route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'recipients'])
            ->with('success', "Penerima '{$userName}' berhasil dihapus dari draf SK.");
    }

    /**
     * Mode A: Tambah Butir Rincian Keputusan Tunggal per GTK.
     */
    public function storeDetail(Request $request, GtkDecree $gtkDecree): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola rincian SK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'assignment_category' => ['required', 'string', Rule::in(array_keys(GtkDecreeDetail::CATEGORIES))],
            'subject_id' => [
                'nullable',
                Rule::requiredIf($request->assignment_category === GtkDecreeDetail::CATEGORY_MENGAJAR),
                'exists:subjects,id',
            ],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'structural_position_id' => [
                'nullable',
                Rule::requiredIf(in_array($request->assignment_category, [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true)),
                'exists:structural_positions,id',
            ],
            'role_id' => [
                'nullable',
                Rule::requiredIf($request->assignment_category === GtkDecreeDetail::CATEGORY_PERAN_SISTEM),
                'exists:roles,id',
            ],
            'teaching_hours' => ['nullable', 'integer', 'min:1', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'Pilih personil GTK penerima tugas.',
            'assignment_category.required' => 'Pilih kategori penugasan.',
            'subject_id.required_if' => 'Mata pelajaran wajib dipilih untuk kategori penugasan mengajar.',
            'structural_position_id.required_if' => 'Jabatan wajib dipilih untuk kategori penugasan struktural / operasional.',
            'role_id.required_if' => 'Role hak akses wajib dipilih untuk kategori peran sistem.',
            'teaching_hours.min' => 'Jam mengajar minimal 1 jam.',
            'valid_until.after_or_equal' => 'Tanggal akhir penugasan tidak boleh mendahului tanggal mulai.',
        ]);

        // Pastikan GTK terdaftar dalam tabel penerima SK
        $recipient = GtkDecreeRecipient::firstOrCreate(
            ['gtk_decree_id' => $gtkDecree->id, 'user_id' => $validated['user_id']],
            ['notes' => 'Ditambahkan otomatis saat penugasan dibuat']
        );

        $isArchive = $validated['assignment_category'] === GtkDecreeDetail::CATEGORY_ARSIP;

        GtkDecreeDetail::create([
            'gtk_decree_id' => $gtkDecree->id,
            'gtk_decree_recipient_id' => $recipient->id,
            'user_id' => $validated['user_id'],
            'assignment_category' => $validated['assignment_category'],
            'subject_id' => $validated['assignment_category'] === GtkDecreeDetail::CATEGORY_MENGAJAR ? ($validated['subject_id'] ?? null) : null,
            'classroom_id' => $validated['classroom_id'] ?? null,
            'structural_position_id' => in_array($validated['assignment_category'], [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true) ? ($validated['structural_position_id'] ?? null) : null,
            'role_id' => $validated['assignment_category'] === GtkDecreeDetail::CATEGORY_PERAN_SISTEM ? ($validated['role_id'] ?? null) : null,
            'teaching_hours' => $validated['assignment_category'] === GtkDecreeDetail::CATEGORY_MENGAJAR ? ($validated['teaching_hours'] ?? null) : null,
            'valid_from' => ! empty($validated['valid_from']) ? $validated['valid_from'] : $gtkDecree->valid_from,
            'valid_until' => ! empty($validated['valid_until']) ? $validated['valid_until'] : $gtkDecree->valid_until,
            'implementation_status' => $isArchive ? GtkDecreeDetail::STATUS_SKIPPED : GtkDecreeDetail::STATUS_PENDING,
            'link_type' => $isArchive ? GtkDecreeDetail::LINK_TYPE_ARCHIVED : GtkDecreeDetail::LINK_TYPE_CREATED,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details'])
            ->with('success', 'Butir rincian keputusan berhasil ditambahkan ke draf SK.');
    }

    /**
     * Mode B: Input Massal Butir Rincian Keputusan (Table Mass-Entry).
     */
    public function storeBulkDetails(Request $request, GtkDecree $gtkDecree): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola rincian SK.');
        }

        $this->ensureIsDraft($gtkDecree);

        $validated = $request->validate([
            'bulk_details' => ['required', 'array', 'min:1'],
            'bulk_details.*.user_id' => ['required', 'exists:users,id'],
            'bulk_details.*.assignment_category' => ['required', 'string', Rule::in(array_keys(GtkDecreeDetail::CATEGORIES))],
            'bulk_details.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'bulk_details.*.classroom_id' => ['nullable', 'exists:classrooms,id'],
            'bulk_details.*.structural_position_id' => ['nullable', 'exists:structural_positions,id'],
            'bulk_details.*.role_id' => ['nullable', 'exists:roles,id'],
            'bulk_details.*.teaching_hours' => ['nullable', 'integer', 'min:1', 'max:60'],
            'bulk_details.*.valid_from' => ['nullable', 'date'],
            'bulk_details.*.valid_until' => ['nullable', 'date'],
            'bulk_details.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'bulk_details.required' => 'Data input massal tidak boleh kosong.',
            'bulk_details.*.user_id.required' => 'Setiap baris rincian wajib memiliki GTK penerima tugas.',
            'bulk_details.*.assignment_category.required' => 'Setiap baris rincian wajib memiliki kategori penugasan.',
        ]);

        $createdCount = 0;

        DB::transaction(function () use ($validated, $gtkDecree, $user, &$createdCount) {
            foreach ($validated['bulk_details'] as $row) {
                // Lewati baris tidak lengkap jika tidak ada kategori atau user
                if (empty($row['user_id']) || empty($row['assignment_category'])) {
                    continue;
                }

                // Validasi bersyarat per kategori
                $cat = $row['assignment_category'];
                if ($cat === GtkDecreeDetail::CATEGORY_MENGAJAR && empty($row['subject_id'])) {
                    continue; // Lewati baris mengajar tanpa mapel
                }
                if (in_array($cat, [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true) && empty($row['structural_position_id'])) {
                    continue; // Lewati baris struktural tanpa jabatan
                }

                $recipient = GtkDecreeRecipient::firstOrCreate(
                    ['gtk_decree_id' => $gtkDecree->id, 'user_id' => $row['user_id']],
                    ['notes' => 'Ditambahkan otomatis dari input massal']
                );

                $isArchive = $cat === GtkDecreeDetail::CATEGORY_ARSIP;

                GtkDecreeDetail::create([
                    'gtk_decree_id' => $gtkDecree->id,
                    'gtk_decree_recipient_id' => $recipient->id,
                    'user_id' => $row['user_id'],
                    'assignment_category' => $cat,
                    'subject_id' => $cat === GtkDecreeDetail::CATEGORY_MENGAJAR ? ($row['subject_id'] ?? null) : null,
                    'classroom_id' => $row['classroom_id'] ?? null,
                    'structural_position_id' => in_array($cat, [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true) ? ($row['structural_position_id'] ?? null) : null,
                    'role_id' => $cat === GtkDecreeDetail::CATEGORY_PERAN_SISTEM ? ($row['role_id'] ?? null) : null,
                    'teaching_hours' => $cat === GtkDecreeDetail::CATEGORY_MENGAJAR ? ($row['teaching_hours'] ?? null) : null,
                    'valid_from' => ! empty($row['valid_from']) ? $row['valid_from'] : $gtkDecree->valid_from,
                    'valid_until' => ! empty($row['valid_until']) ? $row['valid_until'] : $gtkDecree->valid_until,
                    'implementation_status' => $isArchive ? GtkDecreeDetail::STATUS_SKIPPED : GtkDecreeDetail::STATUS_PENDING,
                    'link_type' => $isArchive ? GtkDecreeDetail::LINK_TYPE_ARCHIVED : GtkDecreeDetail::LINK_TYPE_CREATED,
                    'notes' => $row['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $createdCount++;
            }
        });

        return redirect()->route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details'])
            ->with('success', "Berhasil menambahkan {$createdCount} butir rincian penugasan secara massal ke dalam draf SK.");
    }

    /**
     * Hapus Butir Rincian Keputusan dari Draf SK.
     */
    public function destroyDetail(Request $request, GtkDecree $gtkDecree, GtkDecreeDetail $detail): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola rincian SK.');
        }

        $this->ensureIsDraft($gtkDecree);

        if ((int) $detail->gtk_decree_id !== (int) $gtkDecree->id) {
            abort(404, 'Butir rincian tidak terdaftar pada SK ini.');
        }

        $detail->delete();

        return redirect()->route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details'])
            ->with('success', 'Butir rincian keputusan berhasil dihapus dari draf SK.');
    }

    /**
     * Aktivasi SK GTK dan Penerapan Penugasan Operasional ke Sistem.
     */
    public function activate(
        Request $request,
        GtkDecree $gtkDecree,
        GtkDecreeActivationService $activationService
    ): RedirectResponse {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengaktifkan SK GTK.');
        }

        try {
            $result = $activationService->activate($gtkDecree, $user);

            $detailedMessage = "SK No. {$gtkDecree->number} berhasil diaktifkan! "
                ."Rincian penerapan: {$result['created_count']} penugasan baru dibuat, "
                ."{$result['linked_count']} ditautkan ke data eksisting, dan "
                ."{$result['archived_count']} dicatat sebagai arsip dinas.";

            return redirect()->route('gtk-decrees.show', $gtkDecree)
                ->with('success', $detailedMessage);
        } catch (GtkDecreeActivationException $e) {
            return redirect()->route('gtk-decrees.preview', $gtkDecree)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Kesalahan sistem saat aktivasi SK GTK ID #'.$gtkDecree->id.': '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            return redirect()->route('gtk-decrees.preview', $gtkDecree)
                ->with('error', 'Terjadi kesalahan teknis pada sistem saat memproses aktivasi SK. Perubahan data telah dibatalkan.');
        }
    }

    /**
     * Eksekusi Pembatalan SK GTK secara Transaksional.
     */
    public function cancel(
        Request $request,
        GtkDecree $gtkDecree,
        GtkDecreeCancellationService $cancellationService
    ): RedirectResponse {
        $user = $request->user();
        if (! $this->canManage($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan SK GTK.');
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Alasan pembatalan SK wajib diisi.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        try {
            $result = $cancellationService->cancel($gtkDecree, $user, $validated['cancellation_reason']);

            $detailedMessage = "SK No. {$gtkDecree->number} berhasil dibatalkan! "
                ."Hasil operasional: {$result['deactivated_count']} penugasan dinonaktifkan, "
                ."{$result['unlinked_count']} tautan dilepas, "
                ."{$result['protected_count']} dipertahankan (dilindungi SK lain), dan "
                ."{$result['manual_review_count']} memerlukan tindak lanjut manual.";

            return redirect()->route('gtk-decrees.show', $gtkDecree)
                ->with('success', $detailedMessage);
        } catch (GtkDecreeCancellationException $e) {
            return redirect()->route('gtk-decrees.cancel-preview', $gtkDecree)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Kesalahan sistem saat pembatalan SK GTK ID #'.$gtkDecree->id.': '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            return redirect()->route('gtk-decrees.cancel-preview', $gtkDecree)
                ->with('error', 'Terjadi kesalahan teknis pada sistem saat memproses pembatalan SK. Perubahan data telah dibatalkan.');
        }
    }
}
