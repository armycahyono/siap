<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\SupervisionPhase;
use App\Models\SupervisionPhoto;
use App\Models\SupervisionScore;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileSupervisionController extends Controller
{
    /**
     * Authorize user access to this supervision record.
     */
    protected function authorizeAccess(Supervision $supervision, bool $conductOnly = false): void
    {
        $user = Auth::user();

        if ($conductOnly) {
            if (! $user->canEditSupervision($supervision)) {
                abort(403, 'Anda tidak memiliki hak untuk mengubah penilaian supervisi ini atau berkas sudah disahkan.');
            }

            return;
        }

        if ($user->hasRole('super_admin') || $user->isWakaKurikulum() || $user->isPrincipal() || $user->hasPermission('view_all_supervision')) {
            return;
        }

        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka berkas supervisi ini.');
        }
    }

    /**
     * Get supervision dashboard payload for mobile app.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        $canApprove = $user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision');

        // 1. As Teacher (Saya sebagai Guru yang Disupervisi)
        $asTeacher = Supervision::with(['supervisor'])
            ->where('teacher_id', $user->id)
            ->orderBy('schedule_date', 'desc')
            ->get()
            ->map(fn ($s) => $this->formatSupervisionSummary($s, 'teacher'));

        // 2. As Supervisor (Saya sebagai Penilai/Supervisor)
        $asSupervisor = Supervision::with(['teacher'])
            ->where('supervisor_id', $user->id)
            ->orderBy('schedule_date', 'desc')
            ->get()
            ->map(fn ($s) => $this->formatSupervisionSummary($s, 'supervisor'));

        // 3. For Principal / Approver (Persetujuan Kepala Sekolah)
        $pendingApprovals = collect();
        $recentApproved = collect();

        if ($canApprove) {
            $pendingApprovals = Supervision::with(['teacher', 'supervisor'])
                ->where('status', 'menunggu_persetujuan')
                ->orderBy('obs_date', 'desc')
                ->get()
                ->map(fn ($s) => $this->formatSupervisionSummary($s, 'principal'));

            $recentApproved = Supervision::with(['teacher', 'supervisor'])
                ->whereIn('status', ['disetujui', 'selesai'])
                ->orderBy('principal_approved_at', 'desc')
                ->take(15)
                ->get()
                ->map(fn ($s) => $this->formatSupervisionSummary($s, 'principal'));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'current_user_id' => $user->id,
                'current_user_name' => $user->name,
                'academic_year' => $setting->academic_year,
                'semester' => $setting->semester,
                'can_approve' => $canApprove,
                'counts' => [
                    'as_teacher_total' => $asTeacher->count(),
                    'as_teacher_active' => $asTeacher->whereIn('status', ['terjadwal', 'praobservasi', 'observasi'])->count(),
                    'as_supervisor_total' => $asSupervisor->count(),
                    'as_supervisor_active' => $asSupervisor->whereIn('status', ['terjadwal', 'praobservasi', 'observasi', 'pascaobservasi'])->count(),
                    'pending_approval' => $pendingApprovals->count(),
                ],
                'as_teacher' => $asTeacher->values(),
                'as_supervisor' => $asSupervisor->values(),
                'pending_approvals' => $pendingApprovals->values(),
                'recent_approved' => $recentApproved->values(),
            ],
        ]);
    }

    /**
     * Get comprehensive supervision details, rubric phases, scores, photos, and permissions.
     */
    public function show(int $id): JsonResponse
    {
        $supervision = Supervision::with(['teacher', 'supervisor', 'principal', 'photos', 'scores.indicator'])
            ->findOrFail($id);

        $this->authorizeAccess($supervision);

        $user = Auth::user();
        $setting = SchoolSetting::getSetting();

        // Rubric Phases & Indicators
        $phases = SupervisionPhase::with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $scoresMap = $supervision->scores->keyBy('indicator_id');

        $rubricData = $phases->map(function ($phase) use ($scoresMap) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'code' => $phase->code,
                'description' => $phase->description,
                'indicators' => $phase->indicators->map(function ($ind) use ($scoresMap) {
                    $scoreEntry = $scoresMap->get($ind->id);

                    return [
                        'id' => $ind->id,
                        'code' => $ind->code,
                        'title' => $ind->title,
                        'description' => $ind->description,
                        'max_score' => $ind->max_score,
                        'score' => $scoreEntry ? (int) $scoreEntry->score : null,
                        'notes' => $scoreEntry?->notes ?? '',
                    ];
                })->values(),
            ];
        });

        // Photos
        $photosData = $supervision->photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'file_path' => $photo->file_path,
                'url' => route('api.mobile.tasks.supervisions.photo', $photo->id),
                'caption' => $photo->caption,
                'category' => $photo->category,
                'taken_at' => $photo->taken_at?->format('d/m/Y') ?? null,
            ];
        });

        // Permissions flags
        $isLocked = in_array($supervision->status, ['disetujui', 'selesai']);
        $canEdit = $user->canEditSupervision($supervision);
        $canApprove = ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision')) && $supervision->status === 'menunggu_persetujuan';
        $canUploadRpp = ! $isLocked && ($supervision->teacher_id === $user->id || $supervision->supervisor_id === $user->id || $user->hasRole('super_admin') || $user->isWakaKurikulum());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $supervision->id,
                'report_number' => $supervision->report_number ?: 'Draft / Belum Diterbitkan',
                'academic_year' => $supervision->academic_year,
                'semester' => $supervision->semester,
                'subject' => $supervision->subject,
                'class_name' => $supervision->class_name,
                'expertise_program' => $supervision->expertise_program,
                'schedule_date' => $supervision->schedule_date?->format('Y-m-d'),
                'schedule_date_formatted' => $supervision->schedule_date?->isoFormat('dddd, D MMMM Y') ?? '-',
                'schedule_time' => $supervision->schedule_time,
                'schedule_notes' => $supervision->schedule_notes,
                'status' => $supervision->status,
                'status_label' => $supervision->status_label,
                'status_color' => $supervision->status_color,

                'teacher' => [
                    'id' => $supervision->teacher_id,
                    'name' => $supervision->teacher?->name ?? 'Guru',
                    'formatted_id' => $supervision->teacher?->formatted_id ?? '-',
                ],
                'supervisor' => [
                    'id' => $supervision->supervisor_id,
                    'name' => $supervision->supervisor?->name ?? 'Supervisor',
                    'formatted_id' => $supervision->supervisor?->formatted_id ?? '-',
                ],
                'principal' => [
                    'id' => $supervision->principal_id,
                    'name' => $supervision->principal?->name ?? '-',
                ],

                // RPP / Modul Ajar
                'rpp' => [
                    'has_file' => ! empty($supervision->rpp_file_path),
                    'original_name' => $supervision->rpp_original_name,
                    'uploaded_at' => $supervision->rpp_uploaded_at?->isoFormat('D MMMM Y HH:mm'),
                    'download_url' => $supervision->rpp_file_path ? route('api.mobile.tasks.supervisions.rpp', $supervision->id) : null,
                ],

                // Tahap 1: Praobservasi
                'praobservasi' => [
                    'material' => $supervision->pre_material ?? '',
                    'objectives' => $supervision->pre_objectives ?? '',
                    'method' => $supervision->pre_method ?? '',
                    'media' => $supervision->pre_media ?? '',
                    'strategy' => $supervision->pre_strategy ?? '',
                    'focus' => $supervision->pre_focus ?? '',
                    'readiness' => $supervision->pre_readiness ?? '',
                    'supervisor_notes' => $supervision->pre_supervisor_notes ?? '',
                    'completed_at' => $supervision->pre_completed_at?->isoFormat('D MMMM Y HH:mm'),
                    'is_completed' => ! empty($supervision->pre_completed_at),
                ],

                // Tahap 2: Observasi
                'observasi' => [
                    'obs_date' => $supervision->obs_date?->format('Y-m-d') ?? now()->toDateString(),
                    'obs_time' => $supervision->obs_time ?? now()->format('H:i'),
                    'total_score' => (int) $supervision->total_score,
                    'max_score' => (int) $supervision->max_score,
                    'percentage' => (float) $supervision->percentage,
                    'predicate' => $supervision->predicate ?? 'Belum Dinilai',
                    'completed_at' => $supervision->obs_completed_at?->isoFormat('D MMMM Y HH:mm'),
                    'is_completed' => ! empty($supervision->obs_completed_at),
                ],

                // Tahap 3: Pascaobservasi
                'pascaobservasi' => [
                    'positive' => $supervision->post_positive ?? '',
                    'improvement' => $supervision->post_improvement ?? '',
                    'recommendation' => $supervision->post_recommendation ?? '',
                    'followup' => $supervision->post_followup ?? '',
                    'discussion' => $supervision->post_discussion ?? '',
                    'completed_at' => $supervision->post_completed_at?->isoFormat('D MMMM Y HH:mm'),
                    'is_completed' => ! empty($supervision->post_completed_at),
                ],

                // Pengesahan / Finalisasi
                'finalisasi' => [
                    'supervisor_signed_at' => $supervision->supervisor_signed_at?->isoFormat('D MMMM Y HH:mm'),
                    'approved_by_principal' => (bool) $supervision->approved_by_principal,
                    'principal_approved_at' => $supervision->principal_approved_at?->isoFormat('D MMMM Y HH:mm'),
                    'principal_notes' => $supervision->principal_notes ?? '',
                    'qr_token' => $supervision->qr_token,
                ],

                'rubrics' => $rubricData,
                'photos' => $photosData,

                'permissions' => [
                    'can_edit' => $canEdit,
                    'can_upload_rpp' => $canUploadRpp,
                    'can_edit_preobs' => $canUploadRpp,
                    'can_conduct_obs' => $canEdit,
                    'can_edit_postobs' => $canEdit,
                    'can_upload_photo' => $canEdit,
                    'can_finalize' => $canEdit && in_array($supervision->status, ['observasi', 'pascaobservasi', 'praobservasi']),
                    'can_approve' => $canApprove,
                    'is_teacher' => $supervision->teacher_id === $user->id,
                    'is_supervisor' => $supervision->supervisor_id === $user->id,
                ],
            ],
        ]);
    }

    /**
     * Upload Modul Ajar / RPP file via Mobile.
     */
    public function uploadRpp(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision);

        if (in_array($supervision->status, ['disetujui', 'selesai'])) {
            return response()->json([
                'success' => false,
                'message' => 'Supervisi ini sudah disahkan, modul ajar tidak dapat diubah lagi.',
            ], 422);
        }

        $user = Auth::user();
        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id && ! $user->hasRole('super_admin') && ! $user->isWakaKurikulum()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengunggah modul ajar.',
            ], 403);
        }

        $request->validate([
            'rpp_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
        ]);

        if ($supervision->rpp_file_path) {
            if (Storage::disk('local')->exists($supervision->rpp_file_path)) {
                Storage::disk('local')->delete($supervision->rpp_file_path);
            } elseif (Storage::disk('public')->exists($supervision->rpp_file_path)) {
                Storage::disk('public')->delete($supervision->rpp_file_path);
            }
        }

        $file = $request->file('rpp_file');
        $fileName = 'rpp_spv_'.$supervision->id.'_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('rpp_modul_ajar', $fileName, 'local');

        $supervision->update([
            'rpp_file_path' => $path,
            'rpp_original_name' => $file->getClientOriginalName(),
            'rpp_uploaded_at' => now(),
        ]);

        ActivityLogger::log(
            'upload',
            "Mengunggah Modul Ajar / RPP untuk supervisi guru {$supervision->teacher?->name} ({$file->getClientOriginalName()}).",
            $supervision,
            [
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Berkas Modul Ajar / RPP berhasil diunggah.',
            'data' => [
                'has_file' => true,
                'original_name' => $supervision->rpp_original_name,
                'uploaded_at' => $supervision->rpp_uploaded_at?->isoFormat('D MMMM Y HH:mm'),
                'download_url' => route('api.mobile.tasks.supervisions.rpp', $supervision->id),
            ],
        ]);
    }

    /**
     * Save Pre-Observation reflection and notes.
     */
    public function savePreObservation(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision);

        $user = Auth::user();
        if ($supervision->teacher_id !== $user->id && $supervision->supervisor_id !== $user->id && ! $user->hasRole('super_admin') && ! $user->isWakaKurikulum()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses praobservasi.'], 403);
        }

        $data = $request->validate([
            'pre_material' => ['required', 'string'],
            'pre_objectives' => ['required', 'string'],
            'pre_method' => ['required', 'string'],
            'pre_media' => ['nullable', 'string'],
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
            "Menyimpan catatan praobservasi supervisi guru {$supervision->teacher?->name} via Mobile.",
            $supervision,
            [
                'tahap' => 'praobservasi',
                'materi' => $data['pre_material'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Catatan praobservasi berhasil disimpan.',
            'data' => [
                'status' => $supervision->status,
                'status_label' => $supervision->status_label,
            ],
        ]);
    }

    /**
     * Save observation scoring rubric items and notes.
     */
    public function saveObservation(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision, true);

        $scores = $request->input('scores', []);
        $notes = $request->input('notes', []);

        if (empty($scores)) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan pilih skor pada butir indikator observasi.',
            ], 422);
        }

        foreach ($scores as $indicatorId => $scoreVal) {
            if ($scoreVal === null || $scoreVal === '') {
                continue;
            }

            SupervisionScore::updateOrCreate(
                [
                    'supervision_id' => $supervision->id,
                    'indicator_id' => (int) $indicatorId,
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
            "Menyimpan penilaian observasi supervisi guru {$supervision->teacher?->name} via Mobile ({$supervision->percentage}% - {$supervision->predicate}).",
            $supervision,
            [
                'total_score' => $supervision->total_score,
                'percentage' => $supervision->percentage,
                'predicate' => $supervision->predicate,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Penilaian berhasil disimpan! Nilai: {$supervision->percentage}% ({$supervision->predicate}).",
            'data' => [
                'total_score' => (int) $supervision->total_score,
                'max_score' => (int) $supervision->max_score,
                'percentage' => (float) $supervision->percentage,
                'predicate' => $supervision->predicate,
                'status' => $supervision->status,
                'status_label' => $supervision->status_label,
            ],
        ]);
    }

    /**
     * Save post-observation dialogue & feedback.
     */
    public function savePostObservation(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
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
            "Menyimpan catatan pascaobservasi supervisi guru {$supervision->teacher?->name} via Mobile.",
            $supervision,
            [
                'tahap' => 'pascaobservasi',
                'rekomendasi' => $data['post_recommendation'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Catatan umpan balik pascaobservasi berhasil disimpan.',
            'data' => [
                'status' => $supervision->status,
                'status_label' => $supervision->status_label,
            ],
        ]);
    }

    /**
     * Upload classroom documentation photo from mobile camera/gallery.
     */
    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision, true);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('photo');
        $fileName = 'spv_'.$supervision->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('dokumentasi_supervisi', $fileName, 'local');

        $photo = SupervisionPhoto::create([
            'supervision_id' => $supervision->id,
            'file_path' => $path,
            'caption' => $request->input('caption', 'Foto Kegiatan Pembelajaran di Kelas'),
            'category' => $request->input('category', 'Kegiatan Pembelajaran'),
            'taken_at' => now()->toDateString(),
        ]);

        ActivityLogger::log(
            'upload',
            "Mengunggah foto dokumentasi kegiatan supervisi guru {$supervision->teacher?->name} via Mobile.",
            $supervision,
            [
                'file_name' => $fileName,
                'caption' => $photo->caption,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto dokumentasi berhasil ditambahkan.',
            'data' => [
                'id' => $photo->id,
                'file_path' => $photo->file_path,
                'url' => route('api.mobile.tasks.supervisions.photo', $photo->id),
                'caption' => $photo->caption,
                'category' => $photo->category,
                'taken_at' => $photo->taken_at?->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * Delete classroom documentation photo.
     */
    public function deletePhoto(int $photoId): JsonResponse
    {
        $photo = SupervisionPhoto::findOrFail($photoId);
        $supervision = $photo->supervision;
        $this->authorizeAccess($supervision, true);

        if ($photo->file_path) {
            if (Storage::disk('local')->exists($photo->file_path)) {
                Storage::disk('local')->delete($photo->file_path);
            } elseif (Storage::disk('public')->exists($photo->file_path)) {
                Storage::disk('public')->delete($photo->file_path);
            }
        }

        $caption = $photo->caption;
        $photo->delete();

        ActivityLogger::log(
            'delete',
            "Menghapus foto dokumentasi supervisi guru {$supervision->teacher?->name} via Mobile.",
            $supervision,
            ['caption' => $caption]
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto dokumentasi berhasil dihapus.',
        ]);
    }

    /**
     * Finalize and sign supervision report by supervisor (submit for Principal approval).
     */
    public function finalize(Request $request, int $id): JsonResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision, true);

        $updateData = [
            'supervisor_signed_at' => now(),
            'status' => 'menunggu_persetujuan',
        ];

        if (empty($supervision->report_number)) {
            $updateData['report_number'] = Supervision::generateReportNumber();
            $supervision->report_number = $updateData['report_number'];
        }

        $supervision->ensureQrToken();

        ActivityLogger::withoutLogging(function () use ($supervision, $updateData) {
            $supervision->update($updateData);
        });

        ActivityLogger::log(
            'submit',
            "Memfinalisasi berkas supervisi guru {$supervision->teacher?->name} via Mobile dan mengajukan pengesahan ke Kepala Sekolah.",
            $supervision,
            [
                'status' => 'menunggu_persetujuan',
                'report_number' => $supervision->report_number,
                'percentage' => $supervision->percentage,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Berkas supervisi berhasil difinalisasi dan diajukan ke Kepala Sekolah.',
            'data' => [
                'status' => 'menunggu_persetujuan',
                'status_label' => 'Persetujuan KS',
                'report_number' => $supervision->report_number,
            ],
        ]);
    }

    /**
     * Principal approve or reject/revision action.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if (! ($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin() || $user->hasPermission('approve_supervision'))) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengesahkan laporan supervisi.',
            ], 403);
        }

        $supervision = Supervision::findOrFail($id);
        $action = $request->input('action', 'approve');

        if ($action === 'reject') {
            $request->validate([
                'revision_notes' => ['required', 'string'],
            ]);

            ActivityLogger::withoutLogging(function () use ($supervision, $request) {
                $supervision->update([
                    'status' => 'pascaobservasi',
                    'principal_notes' => 'Catatan revisi: '.$request->input('revision_notes'),
                ]);
            });

            ActivityLogger::log(
                'reject',
                "Mengembalikan berkas supervisi guru {$supervision->teacher?->name} untuk perbaikan via Mobile.",
                $supervision,
                [
                    'revision_notes' => $request->input('revision_notes'),
                    'status' => 'pascaobservasi',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Berkas supervisi dikembalikan untuk perbaikan/revisi.',
                'data' => [
                    'status' => 'pascaobservasi',
                    'status_label' => 'Pascaobservasi Selesai',
                ],
            ]);
        }

        // Approve
        $supervision->ensureQrToken();

        $principalUser = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        $principalId = $user->hasRole('kepala_sekolah') ? $user->id : ($principalUser?->id ?? $user->id);

        $updateData = [
            'principal_id' => $principalId,
            'approved_by_principal' => true,
            'principal_approved_at' => now(),
            'principal_notes' => $request->input('principal_notes', 'Telah diperiksa dan disetujui.'),
            'status' => 'disetujui',
            'finalized_at' => now(),
        ];

        if (empty($supervision->report_number)) {
            $updateData['report_number'] = Supervision::generateReportNumber();
            $supervision->report_number = $updateData['report_number'];
        }

        ActivityLogger::withoutLogging(function () use ($supervision, $updateData) {
            $supervision->update($updateData);
        });

        // Capture snapshot
        $supervision->captureSnapshot(true);

        ActivityLogger::log(
            'approve',
            "Mengesahkan laporan supervisi guru {$supervision->teacher?->name} (No: {$supervision->report_number}) via Mobile.",
            $supervision,
            [
                'report_number' => $supervision->report_number,
                'percentage' => $supervision->percentage,
                'predicate' => $supervision->predicate,
                'principal_notes' => $updateData['principal_notes'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Laporan supervisi {$supervision->teacher?->name} resmi disahkan!",
            'data' => [
                'status' => 'disetujui',
                'status_label' => 'Disetujui Kepsek',
                'report_number' => $supervision->report_number,
            ],
        ]);
    }

    /**
     * View photo for mobile client.
     */
    public function viewPhoto(int $photoId): BinaryFileResponse
    {
        $photo = SupervisionPhoto::findOrFail($photoId);
        $this->authorizeAccess($photo->supervision);

        $path = $photo->file_path;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Foto dokumentasi supervisi tidak ditemukan.');
        }

        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path));
        }

        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path));
        }

        abort(404, 'Berkas foto tidak ditemukan di penyimpanan server.');
    }

    /**
     * Download RPP for mobile client.
     */
    public function downloadRpp(int $id): BinaryFileResponse
    {
        $supervision = Supervision::findOrFail($id);
        $this->authorizeAccess($supervision);

        $path = $supervision->rpp_file_path;
        if (! $path || str_contains($path, '..')) {
            abort(404, 'Berkas Modul Ajar / RPP tidak ditemukan.');
        }

        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
        }

        if (! $fullPath) {
            abort(404, 'Berkas Modul Ajar / RPP fisik tidak ditemukan.');
        }

        $downloadName = $supervision->rpp_original_name ?: 'Modul_Ajar_'.$supervision->subject.'_'.$supervision->class_name.'.pdf';

        return response()->download($fullPath, $downloadName);
    }

    /**
     * Helper to format supervision item for mobile listing.
     */
    protected function formatSupervisionSummary(Supervision $s, string $rolePerspective): array
    {
        return [
            'id' => $s->id,
            'report_number' => $s->report_number ?: '-',
            'teacher_name' => $s->teacher?->name ?? 'Guru',
            'teacher_id_formatted' => $s->teacher?->formatted_id ?? '-',
            'supervisor_name' => $s->supervisor?->name ?? 'Supervisor',
            'subject' => $s->subject,
            'class_name' => $s->class_name,
            'schedule_date' => $s->schedule_date?->format('Y-m-d'),
            'schedule_date_formatted' => $s->schedule_date?->isoFormat('D MMMM Y') ?? '-',
            'schedule_time' => $s->schedule_time,
            'obs_date' => $s->obs_date?->format('d/m/Y'),
            'status' => $s->status,
            'status_label' => $s->status_label,
            'status_color' => $s->status_color,
            'percentage' => (float) $s->percentage,
            'predicate' => $s->predicate ?? '-',
            'has_rpp' => ! empty($s->rpp_file_path),
            'has_photos' => $s->photos()->exists(),
            'perspective' => $rolePerspective,
        ];
    }
}
