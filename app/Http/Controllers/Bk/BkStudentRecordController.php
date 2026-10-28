<?php

namespace App\Http\Controllers\Bk;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BkStudentAchievement;
use App\Models\BkStudentDevelopmentNote;
use App\Models\BkStudentDocument;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BkStudentRecordController extends Controller
{
    /**
     * Tab 4: Simpan data prestasi atau keikutsertaan kegiatan siswa.
     */
    public function storeAchievement(Student $student, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'event_date' => ['required', 'date'],
            'type' => ['required', 'in:prestasi,kegiatan'],
            'category' => ['required', 'in:akademik,non_akademik,seni,olahraga,keagamaan,organisasi,ekskul,lainnya'],
            'level' => ['required', 'in:sekolah,kecamatan,kabupaten,provinsi,nasional,internasional'],
            'title' => ['required', 'string', 'max:255'],
            'achievement_result' => ['nullable', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk menambahkan rekam jejak siswa.');
        }

        $academicYearId = AcademicYear::where('name', $validated['academic_year'])->value('id');

        BkStudentAchievement::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'academic_year' => $validated['academic_year'],
            'event_date' => $validated['event_date'],
            'type' => $validated['type'],
            'category' => $validated['category'],
            'level' => $validated['level'],
            'title' => $validated['title'],
            'achievement_result' => $validated['achievement_result'] ?? null,
            'organizer' => $validated['organizer'] ?? null,
            'certificate_number' => $validated['certificate_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $validated['academic_year']])
            ->with('success', 'Data prestasi / kegiatan siswa berhasil dicatat ke Buku Pribadi.');
    }

    /**
     * Tab 4: Hapus data prestasi siswa.
     */
    public function destroyAchievement(Student $student, BkStudentAchievement $achievement): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($achievement->student_id !== $student->id) {
            abort(404);
        }

        if (! $user->canOperateBk($achievement->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus data prestasi siswa.');
        }

        $achievementYear = $achievement->academic_year;
        $achievement->delete();

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $achievementYear])
            ->with('success', 'Data prestasi / kegiatan berhasil dihapus.');
    }

    /**
     * Tab 5: Simpan catatan observasi perkembangan siswa non-kasus.
     */
    public function storeDevelopmentNote(Student $student, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'note_date' => ['required', 'date'],
            'category' => ['required', 'in:sikap_belajar,kedisiplinan_positif,kemandirian,interaksi_sosial,tanggung_jawab,rekomendasi_pembinaan,lainnya'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'follow_up_recommendation' => ['nullable', 'string'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk menambahkan catatan perkembangan siswa.');
        }

        $academicYearId = AcademicYear::where('name', $validated['academic_year'])->value('id');

        BkStudentDevelopmentNote::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'academic_year' => $validated['academic_year'],
            'note_date' => $validated['note_date'],
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'follow_up_recommendation' => $validated['follow_up_recommendation'] ?? null,
            'recorded_by' => $user->id,
        ]);

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $validated['academic_year']])
            ->with('success', 'Catatan observasi perkembangan siswa berhasil disimpan ke Buku Pribadi.');
    }

    /**
     * Tab 5: Hapus catatan observasi perkembangan siswa.
     */
    public function destroyDevelopmentNote(Student $student, BkStudentDevelopmentNote $note): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($note->student_id !== $student->id) {
            abort(404);
        }

        if (! $user->canOperateBk($note->academic_year)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus catatan perkembangan siswa.');
        }

        $noteYear = $note->academic_year;
        $note->delete();

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $noteYear])
            ->with('success', 'Catatan perkembangan siswa berhasil dihapus.');
    }

    /**
     * Tab 8: Upload dan simpan dokumen arsip pendukung BK ke private storage.
     */
    public function storeDocument(Student $student, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'document_category' => ['required', 'in:sertifikat_prestasi,hasil_psikotes,surat_komitmen,surat_perjanjian,berkas_kesehatan,administrasi_bk,lainnya'],
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'document_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        if (! $user->canOperateBk($validated['academic_year'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunggah dokumen pendukung siswa.');
        }

        // Pengecekan otorisasi dokumen kategori sensitif / rahasia
        if (in_array($validated['document_category'], ['hasil_psikotes', 'surat_komitmen', 'surat_perjanjian', 'berkas_kesehatan'], true)) {
            if (! $user->canAccessCounselingNotes($validated['academic_year'])) {
                abort(403, 'Anda tidak memiliki kewenangan konseling rahasia untuk mengunggah dokumen kategori sensitif ini.');
            }
        }

        $file = $request->file('document_file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getClientMimeType();
        $extension = $file->getClientOriginalExtension();

        $safeFileName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'_'.time().'.'.$extension;
        $folderPath = "bk_documents/{$student->id}";
        $filePath = $file->storeAs($folderPath, $safeFileName, 'local');

        $academicYearId = AcademicYear::where('name', $validated['academic_year'])->value('id');

        BkStudentDocument::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'academic_year' => $validated['academic_year'],
            'document_category' => $validated['document_category'],
            'title' => $validated['title'],
            'document_date' => $validated['document_date'] ?? null,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'description' => $validated['description'] ?? null,
            'recorded_by' => $user->id,
        ]);

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $validated['academic_year']])
            ->with('success', 'Dokumen pendukung berhasil diunggah ke arsip privat siswa.');
    }

    /**
     * Tab 8: Unduh dokumen pendukung siswa secara aman dari storage privat.
     */
    public function downloadDocument(Student $student, BkStudentDocument $document): StreamedResponse|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($document->student_id !== $student->id) {
            abort(404, 'Dokumen tidak sesuai dengan data siswa.');
        }

        $academicYear = $document->academic_year;
        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh dokumen siswa ini.');
        }

        // Pengecekan otorisasi unduh dokumen kategori sensitif
        if ($document->isSensitive() && ! $user->canAccessCounselingNotes($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh dokumen kategori sensitif/rahasia ini.');
        }

        $filePath = $document->file_path;
        if (! $filePath || str_contains($filePath, '..')) {
            return back()->with('error', 'Path berkas dokumen tidak valid.');
        }

        if (! Storage::disk('local')->exists($filePath)) {
            return back()->with('error', 'Berkas dokumen fisik tidak ditemukan pada server.');
        }

        return Storage::disk('local')->download($filePath, $document->file_name);
    }

    /**
     * Tab 8: Hapus dokumen pendukung dan berkas fisik dari storage privat.
     */
    public function destroyDocument(Student $student, BkStudentDocument $document): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($document->student_id !== $student->id) {
            abort(404);
        }

        $academicYear = $document->academic_year;
        if (! $user->canOperateBk($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus dokumen siswa ini.');
        }

        // Pengecekan otorisasi hapus dokumen kategori sensitif
        if ($document->isSensitive() && ! $user->canAccessCounselingNotes($academicYear)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus dokumen kategori sensitif/rahasia ini.');
        }

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear])
            ->with('success', 'Dokumen arsip siswa berhasil dihapus.');
    }
}
