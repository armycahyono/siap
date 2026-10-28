<?php

namespace App\Services;

use App\Exceptions\GtkDecreeCancellationException;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

class GtkDecreeCancellationService
{
    public function __construct(
        protected GtkDecreeCancellationPreviewService $previewService
    ) {}

    /**
     * Mengeksekusi pembatalan SK GTK secara aman, transaksional, dan idempoten.
     *
     * @return array<string, mixed>
     *
     * @throws GtkDecreeCancellationException
     */
    public function cancel(GtkDecree $decree, User $actor, string $reason): array
    {
        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            throw GtkDecreeCancellationException::invalidStatus('Alasan pembatalan SK wajib diisi.');
        }

        return DB::transaction(function () use ($decree, $actor, $cleanReason) {
            // 1. Kunci baris SK GTK untuk mencegah eksekusi ganda / race condition
            /** @var GtkDecree $lockedDecree */
            $lockedDecree = GtkDecree::where('id', $decree->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validasi status awal dokumen SK
            if ($lockedDecree->status === GtkDecree::STATUS_CANCELLED) {
                throw GtkDecreeCancellationException::invalidStatus(
                    "SK dengan nomor '{$lockedDecree->number}' sudah berstatus dibatalkan dan tidak dapat dibatalkan ulang."
                );
            }

            if (! in_array($lockedDecree->status, [GtkDecree::STATUS_ACTIVE, GtkDecree::STATUS_DRAFT, GtkDecree::STATUS_EXPIRED], true)) {
                throw GtkDecreeCancellationException::invalidStatus(
                    "SK dengan status '{$lockedDecree->status}' tidak dapat dibatalkan."
                );
            }

            // 3. Jalankan analisis pratinjau dampak terkini di dalam transaksi
            $preview = $this->previewService->preview($lockedDecree);

            $deactivatedCount = 0;
            $unlinkedCount = 0;
            $protectedCount = 0;
            $manualReviewCount = 0;
            $noImpactCount = 0;

            // 4. Proses setiap butir keputusan secara atomik
            foreach ($preview['items'] as $item) {
                /** @var GtkDecreeDetail $detail */
                $detail = GtkDecreeDetail::where('id', $item['detail_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                switch ($item['status']) {
                    case GtkDecreeCancellationPreviewService::STATUS_CANDIDATE_DEACTIVATE:
                        $this->deactivateOperationalAssignment($lockedDecree, $detail, $actor);
                        $deactivatedCount++;
                        break;

                    case GtkDecreeCancellationPreviewService::STATUS_WILL_UNLINK:
                        $detail->update([
                            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                            'notes' => "Tautan SK dibatalkan. Penugasan operasional tetap berjalan karena merupakan data sebelum SK (ID: #{$detail->operational_id}).",
                            'updated_by' => $actor->id,
                        ]);
                        $unlinkedCount++;
                        break;

                    case GtkDecreeCancellationPreviewService::STATUS_PROTECTED_BY_OTHER:
                        $detail->update([
                            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                            'notes' => 'Penugasan operasional dipertahankan karena dilindungi oleh SK lain yang masih aktif.',
                            'updated_by' => $actor->id,
                        ]);
                        $protectedCount++;
                        break;

                    case GtkDecreeCancellationPreviewService::STATUS_MANUAL_REVIEW:
                        // Kebijakan Konservatif: Penugasan berisiko (wali kelas, jadwal KBM) dipertahankan demi operasional sekolah
                        $detail->update([
                            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                            'conflict_notes' => 'Perlu Pemeriksaan Manual: Penugasan operasional dipertahankan demi kelancaran KBM/organisasi sekolah menunggu penyesuaian jadwal atau SK pengganti.',
                            'notes' => 'Penugasan operasional dipertahankan (memerlukan tindak lanjut manual).',
                            'updated_by' => $actor->id,
                        ]);
                        $manualReviewCount++;
                        break;

                    case GtkDecreeCancellationPreviewService::STATUS_ALREADY_INACTIVE:
                    case GtkDecreeCancellationPreviewService::STATUS_NO_IMPACT:
                    default:
                        $detail->update([
                            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                            'notes' => 'Dibatalkan secara administratif tanpa dampak perubahan pada penugasan operasional.',
                            'updated_by' => $actor->id,
                        ]);
                        $noImpactCount++;
                        break;
                }
            }

            // 5. Perbarui status dokumen induk SK dan rekam audit
            $lockedDecree->update([
                'status' => GtkDecree::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $cleanReason,
                'updated_by' => $actor->id,
            ]);

            return [
                'success' => true,
                'decree' => $lockedDecree->fresh(['details']),
                'deactivated_count' => $deactivatedCount,
                'unlinked_count' => $unlinkedCount,
                'protected_count' => $protectedCount,
                'manual_review_count' => $manualReviewCount,
                'no_impact_count' => $noImpactCount,
                'message' => "SK No. {$lockedDecree->number} berhasil dibatalkan.",
            ];
        });
    }

    /**
     * Menonaktifkan penugasan operasional secara aman tanpa menghapus riwayat permanen.
     */
    protected function deactivateOperationalAssignment(
        GtkDecree $decree,
        GtkDecreeDetail $detail,
        User $actor
    ): void {
        $today = now()->toDateString();

        // A. Penugasan Mengajar (TeacherSubjectAssignment)
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_MENGAJAR) {
            $assignmentId = $detail->teacher_subject_assignment_id ?? $detail->operational_id;
            $assignment = TeacherSubjectAssignment::find($assignmentId);
            if ($assignment) {
                $assignment->update([
                    'notes' => ($assignment->notes ? $assignment->notes.' | ' : '')."[NONAKTIF akibat pembatalan SK {$decree->number}]",
                ]);
            }

            $detail->update([
                'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                'notes' => "Penugasan mengajar dinonaktifkan akibat pembatalan SK {$decree->number}.",
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // B. Penugasan Struktural (TeacherStructuralAssignment)
        if (in_array($detail->assignment_category, [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true)) {
            $assignmentId = $detail->teacher_structural_assignment_id ?? $detail->operational_id;
            $assignment = TeacherStructuralAssignment::find($assignmentId);
            if ($assignment) {
                $assignment->update([
                    'is_active' => false,
                    'valid_until' => $today,
                    'notes' => ($assignment->notes ? $assignment->notes.' | ' : '')."[Dinonaktifkan via pembatalan SK {$decree->number}]",
                ]);
            }

            $detail->update([
                'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                'notes' => "Penugasan struktural dinonaktifkan akibat pembatalan SK {$decree->number}.",
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // C. Penugasan Peran Hak Akses Sistem (UserRoleAssignment)
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_PERAN_SISTEM) {
            $assignmentId = $detail->user_role_assignment_id ?? $detail->operational_id;
            $roleAssignment = UserRoleAssignment::find($assignmentId);
            if ($roleAssignment) {
                $roleAssignment->update([
                    'status' => false,
                    'valid_until' => $today,
                    'notes' => ($roleAssignment->notes ? $roleAssignment->notes.' | ' : '')."[Dinonaktifkan via pembatalan SK {$decree->number}]",
                ]);
            }

            $detail->update([
                'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
                'notes' => "Penugasan peran sistem dinonaktifkan akibat pembatalan SK {$decree->number}.",
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // Fallback
        $detail->update([
            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
            'notes' => "Dibatalkan akibat pembatalan SK {$decree->number}.",
            'updated_by' => $actor->id,
        ]);
    }
}
