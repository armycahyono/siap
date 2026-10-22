<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\SupervisionPhase;
use App\Models\SupervisionPhoto;
use App\Models\SupervisionScore;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SupervisionConductController extends Controller
{
    /**
     * Ensure current user is authorized to conduct or view this supervision.
     */
    protected function authorizeAccess(Supervision $supervision, bool $conductOnly = false): void
    {
        $user = Auth::user();

        if ($conductOnly) {
            if (! $user->canEditSupervision($supervision)) {
                abort(403, 'Anda tidak memiliki wewenang untuk mengisi/mengubah penilaian supervisi ini, atau dokumen telah resmi disahkan.');
            }

            return;
        }

        if ($user->hasRole('super_admin') || $user->isWakaKurikulum() || $user->isPrincipal()) {
            return;
        }

        // View access: teacher or supervisor
        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses ke berkas supervisi ini.');
        }
    }

    public function show(Supervision $supervision): View
    {
        $this->authorizeAccess($supervision);

        $supervision->load(['teacher', 'supervisor', 'principal', 'photos', 'scores.indicator']);
        $setting = SchoolSetting::getSetting();

        return view('supervisions.show', compact('supervision', 'setting'));
    }

    /**
     * Upload RPP / Modul Ajar (by Guru or Supervisor)
     */
    public function uploadRpp(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision);

        if (in_array($supervision->status, ['disetujui', 'selesai'])) {
            abort(403, 'Supervisi ini sudah disahkan, modul ajar tidak dapat diubah lagi.');
        }

        $user = Auth::user();
        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id && ! $user->hasRole('super_admin') && ! $user->isWakaKurikulum()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunggah modul ajar pada supervisi ini.');
        }

        $request->validate([
            'rpp_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360'], // max 15MB
        ]);

        if ($supervision->rpp_file_path && Storage::disk('public')->exists($supervision->rpp_file_path)) {
            Storage::disk('public')->delete($supervision->rpp_file_path);
        }

        $file = $request->file('rpp_file');
        $fileName = 'rpp_'.$supervision->id.'_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('modul_ajar', $fileName, 'public');

        $supervision->update([
            'rpp_file_path' => $path,
            'rpp_original_name' => $file->getClientOriginalName(),
            'rpp_uploaded_at' => now(),
        ]);

        return back()->with('success', 'Modul Ajar / RPP berhasil diunggah.');
    }

    /**
     * Tahap 1: Praobservasi
     */
    public function preObservation(Supervision $supervision): View
    {
        $this->authorizeAccess($supervision);

        return view('supervisions.pre_observation', compact('supervision'));
    }

    public function savePreObservation(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision, true);

        $data = $request->validate([
            'pre_material' => ['required', 'string'],
            'pre_objectives' => ['required', 'string'],
            'pre_method' => ['required', 'string'],
            'pre_media' => ['required', 'string'],
            'pre_strategy' => ['nullable', 'string'],
            'pre_focus' => ['nullable', 'string'],
            'pre_readiness' => ['nullable', 'string'],
            'pre_supervisor_notes' => ['nullable', 'string'],
        ]);

        $data['pre_completed_at'] = now();
        if ($supervision->status === 'terjadwal') {
            $data['status'] = 'praobservasi';
        }

        ActivityLogger::withoutLogging(function () use ($supervision, $data) {
            $supervision->update($data);
        });

        ActivityLogger::log(
            'update',
            "Menyimpan catatan praobservasi supervisi guru {$supervision->teacher?->name} ({$supervision->subject} - {$supervision->class_name}).",
            $supervision,
            [
                'tahap' => 'praobservasi',
                'materi' => $data['pre_material'] ?? null,
            ]
        );

        return redirect()->route('supervisions.observation', $supervision)
            ->with('success', 'Catatan Praobservasi berhasil disimpan. Silakan lanjutkan ke observasi pembelajaran.');
    }

    /**
     * Tahap 2: Observasi Pembelajaran (Instrumen 23 Butir)
     */
    public function observation(Supervision $supervision): View
    {
        $this->authorizeAccess($supervision);

        $phases = SupervisionPhase::with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $existingScores = $supervision->scores->pluck('score', 'indicator_id')->toArray();
        $existingNotes = $supervision->scores->pluck('notes', 'indicator_id')->toArray();
        $setting = SchoolSetting::getSetting();

        return view('supervisions.observation', compact('supervision', 'phases', 'existingScores', 'existingNotes', 'setting'));
    }

    public function saveObservation(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision, true);

        $scores = $request->input('scores', []);
        $notes = $request->input('notes', []);

        foreach ($scores as $indicatorId => $scoreVal) {
            SupervisionScore::updateOrCreate(
                [
                    'supervision_id' => $supervision->id,
                    'indicator_id' => $indicatorId,
                ],
                [
                    'score' => (int) $scoreVal,
                    'notes' => $notes[$indicatorId] ?? null,
                ]
            );
        }

        ActivityLogger::withoutLogging(function () use ($supervision, $request) {
            $supervision->recalculateScore();

            $supervision->update([
                'obs_date' => $request->input('obs_date', now()->toDateString()),
                'obs_time' => $request->input('obs_time', now()->format('H:i')),
                'obs_completed_at' => now(),
                'status' => in_array($supervision->status, ['terjadwal', 'praobservasi']) ? 'observasi' : $supervision->status,
            ]);
        });

        ActivityLogger::log(
            'update',
            "Menyimpan penilaian observasi pembelajaran supervisi guru {$supervision->teacher?->name} (Skor: {$supervision->percentage}%, {$supervision->predicate}).",
            $supervision,
            [
                'tahap' => 'observasi',
                'total_skor' => $supervision->total_score,
                'persentase' => $supervision->percentage,
                'predikat' => $supervision->predicate,
            ]
        );

        if ($request->has('continue')) {
            return redirect()->route('supervisions.post-observation', $supervision)
                ->with('success', 'Penilaian observasi berhasil disimpan! Nilai: '.$supervision->percentage.'% ('.$supervision->predicate.'). Lanjut ke Catatan Supervisor.');
        }

        return back()->with('success', 'Penilaian observasi berhasil disimpan (Total Skor: '.$supervision->total_score.' / '.$supervision->percentage.'%).');
    }

    /**
     * Tahap 3: Pascaobservasi / Feedback
     */
    public function postObservation(Supervision $supervision): View
    {
        $this->authorizeAccess($supervision);
        $setting = SchoolSetting::getSetting();

        return view('supervisions.post_observation', compact('supervision', 'setting'));
    }

    public function savePostObservation(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision, true);

        $data = $request->validate([
            'post_positive' => ['required', 'string'],
            'post_improvement' => ['required', 'string'],
            'post_recommendation' => ['required', 'string'],
            'post_followup' => ['required', 'string'],
            'post_discussion' => ['nullable', 'string'],
        ]);

        $data['post_completed_at'] = now();
        if (in_array($supervision->status, ['terjadwal', 'praobservasi', 'observasi'])) {
            $data['status'] = 'pascaobservasi';
        }

        ActivityLogger::withoutLogging(function () use ($supervision, $data) {
            $supervision->update($data);
        });

        ActivityLogger::log(
            'update',
            "Menyimpan catatan dan rekomendasi pascaobservasi supervisi guru {$supervision->teacher?->name}.",
            $supervision,
            [
                'tahap' => 'pascaobservasi',
                'rekomendasi' => $data['post_recommendation'] ?? null,
            ]
        );

        return redirect()->route('supervisions.documentation', $supervision)
            ->with('success', 'Catatan dan rekomendasi pascaobservasi berhasil disimpan. Silakan lengkapi dokumentasi foto.');
    }

    /**
     * Tahap 4: Dokumentasi Foto
     */
    public function documentation(Supervision $supervision): View
    {
        $this->authorizeAccess($supervision);
        $photos = $supervision->photos()->latest()->get();

        return view('supervisions.documentation', compact('supervision', 'photos'));
    }

    public function uploadPhoto(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision, true);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
        ]);

        $file = $request->file('photo');
        $fileName = 'spv_'.$supervision->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('dokumentasi_supervisi', $fileName, 'public');

        $photo = SupervisionPhoto::create([
            'supervision_id' => $supervision->id,
            'file_path' => $path,
            'caption' => $request->input('caption', 'Foto Kegiatan Supervisi'),
            'category' => $request->input('category', 'Kegiatan Pembelajaran'),
            'taken_at' => now()->toDateString(),
        ]);

        ActivityLogger::log(
            'upload',
            "Mengunggah foto dokumentasi kegiatan supervisi guru {$supervision->teacher?->name} (Kategori: {$request->input('category')}).",
            $supervision,
            [
                'file_name' => $fileName,
                'kategori' => $request->input('category'),
                'caption' => $request->input('caption'),
            ]
        );

        return back()->with('success', 'Foto dokumentasi berhasil ditambahkan.');
    }

    public function deletePhoto(SupervisionPhoto $photo): RedirectResponse
    {
        $supervision = $photo->supervision;
        $this->authorizeAccess($supervision, true);

        if (Storage::disk('public')->exists($photo->file_path)) {
            Storage::disk('public')->delete($photo->file_path);
        }

        $caption = $photo->caption;
        $category = $photo->category;
        $photo->delete();

        ActivityLogger::log(
            'delete',
            "Menghapus foto dokumentasi kegiatan supervisi guru {$supervision->teacher?->name}.",
            $supervision,
            [
                'caption' => $caption,
                'kategori' => $category,
            ]
        );

        return back()->with('success', 'Foto dokumentasi berhasil dihapus.');
    }

    /**
     * Finalisasi oleh Supervisor & Ajukan ke Kepala Sekolah
     */
    public function finalize(Request $request, Supervision $supervision): RedirectResponse
    {
        $this->authorizeAccess($supervision, true);

        $updateData = [
            'supervisor_signed_at' => now(),
            'status' => 'menunggu_persetujuan',
        ];

        // Pastikan nomor laporan terisi
        if (empty($supervision->report_number)) {
            $updateData['report_number'] = Supervision::generateReportNumber();
        }

        $supervision->ensureQrToken();

        ActivityLogger::withoutLogging(function () use ($supervision, $updateData) {
            $supervision->update($updateData);
        });

        ActivityLogger::log(
            'submit',
            "Memfinalisasi supervisi guru {$supervision->teacher?->name} dan mengajukan pengesahan ke Kepala Sekolah (No: {$supervision->report_number}).",
            $supervision,
            [
                'report_number' => $supervision->report_number,
                'status' => 'menunggu_persetujuan',
            ]
        );

        return redirect()->route('supervisions.show', $supervision)
            ->with('success', 'Supervisi berhasil difinalisasi dan diajukan ke Kepala Sekolah untuk persetujuan / pengesahan.');
    }

    /**
     * Tampilan Menu Tahapan Supervisi (Praobservasi, Observasi, Dokumentasi, Hasil Supervisi)
     */
    public function stageIndex(Request $request, string $stage): View
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();
        $academicYear = $request->query('academic_year', $setting->academic_year);
        $semester = $request->query('semester', 'all');
        $search = $request->query('search');

        $query = Supervision::with(['teacher', 'supervisor', 'photos']);

        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($semester && $semester !== 'all') {
            $query->where('semester', $semester);
        }

        if ($user->hasRole('supervisor') && ! $user->isWakaKurikulum()) {
            $query->where('supervisor_id', $user->id);
        }

        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")->orWhere('nigs_fix', 'like', "%{$search}%"))
                    ->orWhereHas('supervisor', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        $supervisions = $query->orderBy('schedule_date', 'asc')->paginate(10)->withQueryString();
        $existingYears = SchoolSetting::getAvailableAcademicYears();

        return view('supervisions.stage_index', compact('supervisions', 'stage', 'search', 'academicYear', 'semester', 'setting', 'existingYears'));
    }

    public function indexPreobs(Request $request): View
    {
        return $this->stageIndex($request, 'preobs');
    }

    public function indexObs(Request $request): View
    {
        return $this->stageIndex($request, 'obs');
    }

    public function indexDocs(Request $request): View
    {
        return $this->stageIndex($request, 'docs');
    }

    public function indexResults(Request $request): View
    {
        return $this->stageIndex($request, 'results');
    }
}
