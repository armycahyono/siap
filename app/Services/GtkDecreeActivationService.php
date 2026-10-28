<?php

namespace App\Services;

use App\Exceptions\GtkDecreeActivationException;
use App\Models\Classroom;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

class GtkDecreeActivationService
{
    public function __construct(
        protected GtkDecreeImpactPreviewService $previewService,
        protected HomeroomAssignmentService $homeroomService
    ) {}

    /**
     * Mengaktifkan SK GTK secara transaksional, aman, dan idempoten.
     *
     * @return array<string, mixed>
     *
     * @throws GtkDecreeActivationException
     */
    public function activate(GtkDecree $decree, User $actor): array
    {
        return DB::transaction(function () use ($decree, $actor) {
            // 1. Kunci baris SK GTK untuk mencegah eksekusi ganda / race condition
            /** @var GtkDecree $lockedDecree */
            $lockedDecree = GtkDecree::where('id', $decree->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validasi status awal dokumen SK
            if ($lockedDecree->status === GtkDecree::STATUS_ACTIVE) {
                throw GtkDecreeActivationException::invalidStatus(
                    "SK dengan nomor '{$lockedDecree->number}' sudah berstatus aktif dan tidak dapat diaktifkan kembali."
                );
            }

            if ($lockedDecree->status === GtkDecree::STATUS_CANCELLED) {
                throw GtkDecreeActivationException::invalidStatus(
                    "SK dengan nomor '{$lockedDecree->number}' telah dibatalkan dan tidak dapat diaktifkan kembali."
                );
            }

            if ($lockedDecree->status !== GtkDecree::STATUS_DRAFT) {
                throw GtkDecreeActivationException::invalidStatus(
                    "Hanya SK berstatus draft yang dapat diaktifkan (status saat ini: '{$lockedDecree->status}')."
                );
            }

            // 3. Validasi kelengkapan dokumen SK
            if (! $lockedDecree->issued_date || ! $lockedDecree->tmt_date || ! $lockedDecree->valid_from) {
                throw GtkDecreeActivationException::invalidStatus(
                    'SK belum memiliki tanggal penetapan, TMT, atau tanggal mulai berlaku yang lengkap.'
                );
            }

            $detailCount = $lockedDecree->details()->count();
            if ($detailCount === 0) {
                throw GtkDecreeActivationException::invalidStatus(
                    "SK dengan nomor '{$lockedDecree->number}' belum memiliki butir keputusan/rincian penugasan untuk diterapkan."
                );
            }

            // 4. Validasi tanggal efektif / masa depan (pencegahan deferred activation)
            $today = now()->startOfDay();
            if ($lockedDecree->valid_from->startOfDay()->gt($today)) {
                throw GtkDecreeActivationException::futureEffectiveDate(
                    "Tanggal efektif SK ({$lockedDecree->valid_from->format('d/m/Y')}) masih di masa depan. Sistem penugasan belum mendukung penundaan operasional otomatis (deferred activation)."
                );
            }

            if ($lockedDecree->valid_until && $lockedDecree->valid_until->lt($lockedDecree->valid_from)) {
                throw GtkDecreeActivationException::invalidStatus(
                    "Tanggal akhir berlaku SK ({$lockedDecree->valid_until->format('d/m/Y')}) tidak boleh mendahului tanggal mulai ({$lockedDecree->valid_from->format('d/m/Y')})."
                );
            }

            // Validasi tanggal butir individual jika ada tanggal masa depan
            foreach ($lockedDecree->details as $d) {
                if ($d->valid_from && $d->valid_from->startOfDay()->gt($today)) {
                    throw GtkDecreeActivationException::futureEffectiveDate(
                        "Butir keputusan ID #{$d->id} memiliki tanggal mulai berlaku di masa depan ({$d->valid_from->format('d/m/Y')}). Penjadwalan masa depan belum didukung."
                    );
                }
                if ($d->valid_from && $d->valid_until && $d->valid_until->lt($d->valid_from)) {
                    throw GtkDecreeActivationException::invalidStatus(
                        "Butir keputusan ID #{$d->id} memiliki tanggal akhir yang mendahului tanggal mulai."
                    );
                }
            }

            // 5. Jalankan Pratinjau Dampak Terbaru
            $preview = $this->previewService->preview($lockedDecree);
            $summary = $preview['summary'];

            // 6. Tolak jika ditemukan konflik penugasan
            if ($summary['conflict'] > 0) {
                $conflicts = array_values(array_filter($preview['items'], fn ($i) => $i['status'] === GtkDecreeImpactPreviewService::STATUS_CONFLICT));
                $firstConflict = $conflicts[0]['notes'] ?? 'Terjadi konflik penugasan operasional.';

                throw GtkDecreeActivationException::conflict(
                    "Aktivasi dibatalkan: Ditemukan {$summary['conflict']} konflik penugasan operasional. {$firstConflict}",
                    $conflicts
                );
            }

            // 7. Tolak jika ada butir rincian yang belum lengkap
            if ($summary['incomplete'] > 0) {
                $incompletes = array_values(array_filter($preview['items'], fn ($i) => $i['status'] === GtkDecreeImpactPreviewService::STATUS_INCOMPLETE));
                $firstIncomplete = $incompletes[0]['notes'] ?? 'Data penugasan belum lengkap.';

                throw GtkDecreeActivationException::incomplete(
                    "Aktivasi dibatalkan: Ditemukan {$summary['incomplete']} butir keputusan yang belum lengkap atau memerlukan konfigurasi jadwal mingguan khusus. {$firstIncomplete}",
                    $incompletes
                );
            }

            // 8. Terapkan rincian secara atomik
            $targetAcademicYear = $preview['target_academic_year'];
            $appliedCount = 0;
            $createdCount = 0;
            $linkedCount = 0;
            $archivedCount = 0;

            foreach ($preview['items'] as $item) {
                /** @var GtkDecreeDetail $detail */
                $detail = GtkDecreeDetail::where('id', $item['detail_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Idempotensi: jika rincian sudah berstatus diterapkan, lewati tanpa duplikasi
                if ($detail->implementation_status === GtkDecreeDetail::STATUS_APPLIED && $detail->operational_id) {
                    $appliedCount++;

                    continue;
                }

                switch ($item['status']) {
                    case GtkDecreeImpactPreviewService::STATUS_WILL_CREATE:
                        $this->applyCreateAssignment($lockedDecree, $detail, $actor, $targetAcademicYear);
                        $createdCount++;
                        $appliedCount++;
                        break;

                    case GtkDecreeImpactPreviewService::STATUS_WILL_LINK:
                        $this->applyLinkAssignment($lockedDecree, $detail, $actor, $item['existing_record']);
                        $linkedCount++;
                        $appliedCount++;
                        break;

                    case GtkDecreeImpactPreviewService::STATUS_NOT_APPLICABLE:
                        $this->applyArchivedAssignment($lockedDecree, $detail, $actor);
                        $archivedCount++;
                        $appliedCount++;
                        break;

                    default:
                        throw GtkDecreeActivationException::invalidStatus(
                            "Status rincian penugasan '{$item['status']}' tidak valid untuk aktivasi."
                        );
                }
            }

            // 9. Perbarui status dokumen induk SK
            $lockedDecree->update([
                'status' => GtkDecree::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return [
                'success' => true,
                'decree' => $lockedDecree->fresh(['details']),
                'created_count' => $createdCount,
                'linked_count' => $linkedCount,
                'archived_count' => $archivedCount,
                'total_applied' => $appliedCount,
                'message' => "SK No. {$lockedDecree->number} berhasil diaktifkan. {$appliedCount} butir keputusan telah diterapkan ke sistem operasional.",
            ];
        });
    }

    /**
     * Terapkan pembuatan penugasan operasional baru (AKAN DIBUAT).
     */
    protected function applyCreateAssignment(
        GtkDecree $decree,
        GtkDecreeDetail $detail,
        User $actor,
        string $targetAcademicYear
    ): void {
        // A. Penugasan Guru Mengajar (KBM)
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_MENGAJAR) {
            $assignment = TeacherSubjectAssignment::firstOrCreate(
                [
                    'teacher_id' => $detail->user_id,
                    'subject_id' => $detail->subject_id,
                    'classroom_id' => $detail->classroom_id,
                    'academic_year' => $targetAcademicYear,
                ],
                [
                    'hours_per_week' => $detail->teaching_hours ?? 2,
                    'notes' => "Ditetapkan via SK No: {$decree->number}",
                ]
            );

            $detail->update([
                'operational_type' => TeacherSubjectAssignment::class,
                'operational_id' => $assignment->id,
                'teacher_subject_assignment_id' => $assignment->id,
                'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
                'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
                'notes' => "Penugasan mengajar mapel berhasil dibuat via SK No: {$decree->number}.",
                'conflict_notes' => null,
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // B. Penugasan Wali Kelas berbasis Rombel
        if ($detail->classroom_id && (! $detail->structural_position_id || $this->isHomeroomPosition($detail->structuralPosition))) {
            /** @var Classroom $classroom */
            $classroom = Classroom::where('id', $detail->classroom_id)->lockForUpdate()->firstOrFail();

            if ($classroom->homeroom_teacher_id && (int) $classroom->homeroom_teacher_id !== (int) $detail->user_id) {
                throw GtkDecreeActivationException::conflict(
                    "Konflik: Rombongan belajar '{$classroom->name}' telah diisi wali kelas lain saat aktivasi diproses."
                );
            }

            $this->homeroomService->assignHomeroom($classroom, $detail->user_id, $actor->id);

            $detail->update([
                'operational_type' => Classroom::class,
                'operational_id' => $classroom->id,
                'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
                'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
                'notes' => "Penetapan wali kelas untuk {$classroom->name} berhasil diterapkan.",
                'conflict_notes' => null,
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // C. Penugasan Struktural / Operasional
        if (in_array($detail->assignment_category, [GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL], true)) {
            $position = StructuralPosition::findOrFail($detail->structural_position_id);

            if (! $position->allows_multiple) {
                $detailStart = $detail->valid_from?->format('Y-m-d') ?? $decree->valid_from?->format('Y-m-d');
                $detailEnd = $detail->valid_until?->format('Y-m-d') ?? $decree->valid_until?->format('Y-m-d');

                $conflictActive = TeacherStructuralAssignment::where('structural_position_id', $position->id)
                    ->where('is_active', true)
                    ->where('teacher_id', '!=', $detail->user_id)
                    ->get()
                    ->first(function ($asgn) use ($detailStart, $detailEnd) {
                        $asgnStart = $asgn->valid_from?->format('Y-m-d') ?? $asgn->sk_date?->format('Y-m-d');
                        $asgnEnd = $asgn->valid_until?->format('Y-m-d');

                        return $this->previewService->datesOverlap($detailStart, $detailEnd, $asgnStart, $asgnEnd);
                    });

                if ($conflictActive) {
                    throw GtkDecreeActivationException::conflict(
                        "Konflik: Jabatan tunggal '{$position->name}' sedang aktif dipegang oleh pejabat lain."
                    );
                }
            }

            $assignment = TeacherStructuralAssignment::create([
                'teacher_id' => $detail->user_id,
                'structural_position_id' => $detail->structural_position_id,
                'academic_year' => $targetAcademicYear,
                'sk_number' => $decree->number,
                'sk_date' => $decree->issued_date,
                'valid_from' => $detail->valid_from ?? $decree->valid_from,
                'valid_until' => $detail->valid_until ?? $decree->valid_until,
                'notes' => $detail->notes ?? "Ditetapkan via SK No: {$decree->number}",
                'is_active' => true,
            ]);

            $detail->update([
                'operational_type' => TeacherStructuralAssignment::class,
                'operational_id' => $assignment->id,
                'teacher_structural_assignment_id' => $assignment->id,
                'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
                'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
                'notes' => "Penugasan struktural berhasil dibuat via SK No: {$decree->number}.",
                'conflict_notes' => null,
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // D. Penugasan Peran Hak Akses Sistem
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_PERAN_SISTEM) {
            $roleAssignment = UserRoleAssignment::create([
                'user_id' => $detail->user_id,
                'role_id' => $detail->role_id,
                'academic_year_id' => $decree->academic_year_id,
                'academic_year' => $targetAcademicYear,
                'valid_from' => $detail->valid_from ?? $decree->valid_from,
                'valid_until' => $detail->valid_until ?? $decree->valid_until,
                'status' => true,
                'sk_number' => $decree->number,
                'assigned_by' => $actor->id,
                'notes' => $detail->notes ?? "Ditetapkan via SK No: {$decree->number}",
            ]);

            $detail->update([
                'operational_type' => UserRoleAssignment::class,
                'operational_id' => $roleAssignment->id,
                'user_role_assignment_id' => $roleAssignment->id,
                'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
                'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
                'notes' => "Penetapan peran sistem berhasil diterapkan via SK No: {$decree->number}.",
                'conflict_notes' => null,
                'updated_by' => $actor->id,
            ]);

            return;
        }

        // Fallback untuk kategori lainnya: catat sebagai arsip
        $this->applyArchivedAssignment($decree, $detail, $actor);
    }

    /**
     * Tautkan butir rincian SK ke penugasan operasional yang sudah ada (AKAN DITAUTKAN).
     * TIDAK mengubah data operasional eksisting.
     */
    protected function applyLinkAssignment(
        GtkDecree $decree,
        GtkDecreeDetail $detail,
        User $actor,
        mixed $existingRecord
    ): void {
        $updateData = [
            'operational_type' => get_class($existingRecord),
            'operational_id' => $existingRecord->id,
            'link_type' => GtkDecreeDetail::LINK_TYPE_LINKED,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'notes' => "Ditautkan ke penugasan operasional eksisting (ID: #{$existingRecord->id}).",
            'conflict_notes' => null,
            'updated_by' => $actor->id,
        ];

        if ($existingRecord instanceof TeacherSubjectAssignment) {
            $updateData['teacher_subject_assignment_id'] = $existingRecord->id;
        } elseif ($existingRecord instanceof TeacherStructuralAssignment) {
            $updateData['teacher_structural_assignment_id'] = $existingRecord->id;
        } elseif ($existingRecord instanceof UserRoleAssignment) {
            $updateData['user_role_assignment_id'] = $existingRecord->id;
        }

        $detail->update($updateData);
    }

    /**
     * Terapkan butir rincian arsip tanpa membuat penugasan operasional.
     */
    protected function applyArchivedAssignment(
        GtkDecree $decree,
        GtkDecreeDetail $detail,
        User $actor
    ): void {
        $detail->update([
            'link_type' => GtkDecreeDetail::LINK_TYPE_ARCHIVED,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'notes' => 'Tercatat sebagai arsip / dokumentasi dinas tanpa baris penugasan operasional.',
            'conflict_notes' => null,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * Helper identifikasi posisi wali kelas.
     */
    protected function isHomeroomPosition(?StructuralPosition $position): bool
    {
        if (! $position) {
            return false;
        }

        $code = strtolower((string) $position->code);
        $name = strtolower((string) $position->name);

        return str_contains($code, 'wali') || str_contains($name, 'wali kelas');
    }
}
