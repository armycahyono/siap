<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\LessonSchedule;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Collection;

class GtkDecreeCancellationPreviewService
{
    public const STATUS_NO_IMPACT = 'tidak_berdampak';

    public const STATUS_CANDIDATE_DEACTIVATE = 'kandidat_dinonaktifkan';

    public const STATUS_PROTECTED_BY_OTHER = 'dilindungi_sumber_lain';

    public const STATUS_WILL_UNLINK = 'akan_dilepas';

    public const STATUS_MANUAL_REVIEW = 'perlu_pemeriksaan_manual';

    public const STATUS_ALREADY_INACTIVE = 'sudah_tidak_aktif';

    public const STATUS_LABELS = [
        self::STATUS_NO_IMPACT => 'Tidak Berdampak Operasional',
        self::STATUS_CANDIDATE_DEACTIVATE => 'Kandidat Dinonaktifkan',
        self::STATUS_PROTECTED_BY_OTHER => 'Dilindungi SK Lain',
        self::STATUS_WILL_UNLINK => 'Akan Dilepas (Tautan Saja)',
        self::STATUS_MANUAL_REVIEW => 'Perlu Pemeriksaan Manual',
        self::STATUS_ALREADY_INACTIVE => 'Sudah Tidak Aktif',
    ];

    public const STATUS_BADGES = [
        self::STATUS_NO_IMPACT => 'bg-secondary text-white',
        self::STATUS_CANDIDATE_DEACTIVATE => 'bg-danger text-white',
        self::STATUS_PROTECTED_BY_OTHER => 'bg-success text-white',
        self::STATUS_WILL_UNLINK => 'bg-info text-white',
        self::STATUS_MANUAL_REVIEW => 'bg-warning text-dark',
        self::STATUS_ALREADY_INACTIVE => 'bg-dark text-white',
    ];

    /**
     * Menganalisis dampak simulasi pembatalan SK GTK secara murni read-only.
     * Tidak mengubah status SK, detail, penugasan operasional, wali kelas, atau jadwal.
     *
     * @return array<string, mixed>
     */
    public function preview(GtkDecree $decree): array
    {
        $decree->loadMissing([
            'academicYear',
            'recipients.user',
            'details.user',
            'details.subject',
            'details.classroom',
            'details.structuralPosition',
            'details.role',
            'details.teacherSubjectAssignment',
            'details.teacherStructuralAssignment',
            'details.userRoleAssignment',
        ]);

        $items = [];
        $counts = [
            self::STATUS_NO_IMPACT => 0,
            self::STATUS_CANDIDATE_DEACTIVATE => 0,
            self::STATUS_PROTECTED_BY_OTHER => 0,
            self::STATUS_WILL_UNLINK => 0,
            self::STATUS_MANUAL_REVIEW => 0,
            self::STATUS_ALREADY_INACTIVE => 0,
        ];

        foreach ($decree->details as $detail) {
            $analysis = $this->analyzeDetailCancellation($decree, $detail);
            $items[] = $analysis;
            $counts[$analysis['status']]++;
        }

        $total = count($items);

        return [
            'decree' => $decree,
            'decree_status' => $decree->status,
            'items' => $items,
            'summary' => [
                'total' => $total,
                'no_impact' => $counts[self::STATUS_NO_IMPACT],
                'candidate_deactivate' => $counts[self::STATUS_CANDIDATE_DEACTIVATE],
                'protected_by_other' => $counts[self::STATUS_PROTECTED_BY_OTHER],
                'will_unlink' => $counts[self::STATUS_WILL_UNLINK],
                'manual_review' => $counts[self::STATUS_MANUAL_REVIEW],
                'already_inactive' => $counts[self::STATUS_ALREADY_INACTIVE],
            ],
        ];
    }

    /**
     * Analisis pembatalan per butir rincian keputusan.
     *
     * @return array<string, mixed>
     */
    protected function analyzeDetailCancellation(GtkDecree $decree, GtkDecreeDetail $detail): array
    {
        // 1. SK Berstatus Draf: Belum pernah diterapkan ke operasional
        if ($decree->status === GtkDecree::STATUS_DRAFT) {
            return $this->formatResult(
                $detail,
                self::STATUS_NO_IMPACT,
                'Dokumen SK masih berupa draf dan penugasan belum pernah diterapkan ke sistem operasional.',
                'Pembatalan hanya membatalkan draf dokumen tanpa memengaruhi penugasan guru di aplikasi.'
            );
        }

        // 2. SK Sudah Pernah Dibatalkan Sebelumnya
        if ($decree->status === GtkDecree::STATUS_CANCELLED) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Dokumen SK ini telah dibatalkan sebelumnya pada '.($decree->cancelled_at?->format('d/m/Y H:i') ?? '-').'.',
                'Tidak ada tindakan pembatalan ulang.'
            );
        }

        // 3. SK Sudah Berakhir Masa Berlakunya
        if ($decree->status === GtkDecree::STATUS_EXPIRED) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Dokumen SK ini telah berstatus berakhir.',
                'Penugasan telah selesai sesuai masa berlakunya.'
            );
        }

        // 4. Kategori Arsip / Non-Operasional
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_ARSIP || $detail->link_type === GtkDecreeDetail::LINK_TYPE_ARCHIVED) {
            return $this->formatResult(
                $detail,
                self::STATUS_NO_IMPACT,
                'Butir keputusan bersifat arsip/dokumentasi dinas dan tidak memiliki baris penugasan operasional.',
                'Pembatalan SK tidak berdampak pada data operasional.'
            );
        }

        // 5. Belum Diterapkan / Belum Terhubung ke Data Operasional
        if (! $detail->operational_id && ! $detail->teacher_subject_assignment_id && ! $detail->teacher_structural_assignment_id && ! $detail->user_role_assignment_id) {
            return $this->formatResult(
                $detail,
                self::STATUS_NO_IMPACT,
                'Butir penugasan ini belum ditautkan ke record operasional mana pun.',
                'Tidak ada perubahan pada modul operasional.'
            );
        }

        // 6. Cari apakah ada dasar SK lain yang aktif yang menaungi penugasan yang sama
        $otherActiveDecrees = $this->findOtherActiveDecreesForDetail($decree, $detail);
        if ($otherActiveDecrees->isNotEmpty()) {
            $otherTitles = $otherActiveDecrees->map(fn ($d) => "SK No. {$d->number}")->implode(', ');

            return $this->formatResult(
                $detail,
                self::STATUS_PROTECTED_BY_OTHER,
                "Penugasan ini masih memiliki dasar hukum aktif lain yang sah ({$otherTitles}).",
                'Penugasan operasional tetap dipertahankan karena dilindungi oleh SK lain yang masih aktif.',
                $otherActiveDecrees
            );
        }

        // 7. Analisis Berdasarkan Kategori Penugasan Spesifik
        return match ($detail->assignment_category) {
            GtkDecreeDetail::CATEGORY_MENGAJAR => $this->analyzeTeachingCancellation($decree, $detail),
            GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL => $this->analyzeStructuralCancellation($decree, $detail),
            GtkDecreeDetail::CATEGORY_PERAN_SISTEM => $this->analyzeRoleCancellation($decree, $detail),
            default => $this->formatResult(
                $detail,
                self::STATUS_NO_IMPACT,
                'Kategori penugasan umum/lainnya.',
                'Tidak ada tindakan operasional otomatis yang terpengaruh.'
            ),
        };
    }

    /**
     * Analisis pembatalan penugasan Guru Mengajar (KBM).
     *
     * @return array<string, mixed>
     */
    protected function analyzeTeachingCancellation(GtkDecree $decree, GtkDecreeDetail $detail): array
    {
        $assignmentId = $detail->teacher_subject_assignment_id ?? $detail->operational_id;
        $assignment = TeacherSubjectAssignment::find($assignmentId);

        if (! $assignment) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Penugasan mengajar tidak ditemukan di database operasional (mungkin telah dihapus sebelumnya).',
                'Tidak berdampak pada penugasan saat ini.'
            );
        }

        // Periksa apakah penugasan ini memiliki jadwal KBM aktif (LessonSchedule)
        $activeSchedulesCount = LessonSchedule::where('teacher_id', $assignment->teacher_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('classroom_id', $assignment->classroom_id)
            ->where('academic_year', $assignment->academic_year)
            ->count();

        if ($activeSchedulesCount > 0) {
            return $this->formatResult(
                $detail,
                self::STATUS_MANUAL_REVIEW,
                "Terdapat {$activeSchedulesCount} jadwal pelajaran KBM aktif yang terikat dengan penugasan ini.",
                'Perlu pemeriksaan manual: Jangan menonaktifkan penugasan secara langsung agar jadwal KBM rombel tidak terputus. Siapkan guru pengganti terlebih dahulu.',
                $assignment
            );
        }

        // Jika hubungan hanya ditautkan (bukan produk SK ini)
        if ($detail->link_type === GtkDecreeDetail::LINK_TYPE_LINKED) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_UNLINK,
                'Penugasan mengajar ini sudah ada sebelum SK terbit dan hanya ditautkan ke SK.',
                'Jika SK dibatalkan, hanya tautan hukum SK yang dilepas. Penugasan mengajar operasional tetap dipertahankan.',
                $assignment
            );
        }

        // Jika dibuat oleh SK ini dan tidak ada jadwal aktif
        return $this->formatResult(
            $detail,
            self::STATUS_CANDIDATE_DEACTIVATE,
            'Penugasan mengajar dibuat langsung saat aktivasi SK ini dan tidak memiliki dasar SK aktif lain.',
            'Kandidat untuk dinonaktifkan dari sistem operasional KBM jika pembatalan disetujui.',
            $assignment
        );
    }

    /**
     * Analisis pembatalan penugasan Wali Kelas atau Jabatan Struktural.
     *
     * @return array<string, mixed>
     */
    protected function analyzeStructuralCancellation(GtkDecree $decree, GtkDecreeDetail $detail): array
    {
        // Kasus Khusus A: Penugasan Wali Kelas berbasis Rombel
        if ($detail->classroom_id && (! $detail->structural_position_id || $this->isHomeroomPosition($detail->structuralPosition))) {
            $classroom = Classroom::find($detail->classroom_id);
            if (! $classroom) {
                return $this->formatResult(
                    $detail,
                    self::STATUS_NO_IMPACT,
                    'Rombongan belajar tidak ditemukan atau telah dihapus.',
                    'Tidak ada tindakan operasional.'
                );
            }

            // Jika guru saat ini bukan lagi wali kelas rombel tersebut
            if ((int) $classroom->homeroom_teacher_id !== (int) $detail->user_id) {
                return $this->formatResult(
                    $detail,
                    self::STATUS_ALREADY_INACTIVE,
                    "Guru saat ini sudah tidak lagi menjabat sebagai wali kelas {$classroom->name} (telah digantikan).",
                    'Status penugasan wali kelas sudah tidak aktif untuk guru ini.',
                    $classroom
                );
            }

            // Jika masih aktif menjabat wali kelas: selalu tandai manual review
            return $this->formatResult(
                $detail,
                self::STATUS_MANUAL_REVIEW,
                "Guru saat ini tercatat aktif sebagai Wali Kelas pada rombel '{$classroom->name}'.",
                'Perlu pemeriksaan manual: Pembatalan SK tidak boleh langsung mengosongkan wali kelas rombel. Siapkan SK pengangkatan wali kelas pengganti.',
                $classroom
            );
        }

        // Kasus Khusus B: Penugasan Guru Piket
        if ($this->isPiketPosition($detail->structuralPosition)) {
            return $this->formatResult(
                $detail,
                self::STATUS_MANUAL_REVIEW,
                'Penugasan piket terhubung dengan jadwal harian operasional pada modul Jadwal Piket.',
                'Perlu pemeriksaan manual: Penyesuaian jadwal piket mingguan dikelola langsung melalui modul Jadwal Piket.',
                $detail->structuralPosition
            );
        }

        // Kasus C: Penugasan Struktural Biasa (TeacherStructuralAssignment)
        $assignmentId = $detail->teacher_structural_assignment_id ?? $detail->operational_id;
        $assignment = TeacherStructuralAssignment::find($assignmentId);

        if (! $assignment) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Data penugasan struktural tidak ditemukan di database operasional.',
                'Tidak berdampak pada data aktif saat ini.'
            );
        }

        // Cek apakah penugasan struktural sudah tidak aktif
        $today = now()->toDateString();
        $isExpired = $assignment->valid_until && $assignment->valid_until->toDateString() < $today;
        if (! $assignment->is_active || $isExpired) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Penugasan jabatan struktural sudah berstatus tidak aktif atau telah berakhir masa jabatannya.',
                'Tidak ada tindakan penonaktifan ulang yang diperlukan.',
                $assignment
            );
        }

        // Jika hanya ditautkan
        if ($detail->link_type === GtkDecreeDetail::LINK_TYPE_LINKED) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_UNLINK,
                'Penugasan jabatan struktural ini sudah ada sebelum SK terbit dan hanya ditautkan ke SK.',
                'Jika SK dibatalkan, hanya tautan hukum SK yang dilepas. Penugasan jabatan tetap aktif.',
                $assignment
            );
        }

        // Jika dibuat oleh SK ini
        return $this->formatResult(
            $detail,
            self::STATUS_CANDIDATE_DEACTIVATE,
            "Jabatan struktural '{$detail->structuralPosition?->name}' dibuat langsung saat aktivasi SK ini.",
            'Kandidat untuk dinonaktifkan dari sistem penugasan struktural jika pembatalan disetujui.',
            $assignment
        );
    }

    /**
     * Analisis pembatalan penugasan Peran Sistem (UserRoleAssignment).
     *
     * @return array<string, mixed>
     */
    protected function analyzeRoleCancellation(GtkDecree $decree, GtkDecreeDetail $detail): array
    {
        $assignmentId = $detail->user_role_assignment_id ?? $detail->operational_id;
        $roleAssignment = UserRoleAssignment::find($assignmentId);

        if (! $roleAssignment) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Penugasan peran hak akses tidak ditemukan di sistem.',
                'Tidak berdampak pada hak akses saat ini.'
            );
        }

        $today = now()->toDateString();
        $isExpired = $roleAssignment->valid_until && $roleAssignment->valid_until->toDateString() < $today;
        if (! $roleAssignment->status || $isExpired) {
            return $this->formatResult(
                $detail,
                self::STATUS_ALREADY_INACTIVE,
                'Peran hak akses sistem sudah berstatus nonaktif atau telah kedaluwarsa.',
                'Tidak ada perubahan hak akses yang terjadi.',
                $roleAssignment
            );
        }

        if ($detail->link_type === GtkDecreeDetail::LINK_TYPE_LINKED) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_UNLINK,
                'Hak akses sistem ini sudah dimiliki guru sebelum SK ini dan hanya ditautkan.',
                'Tautan SK dilepas, hak akses pengguna tetap dipertahankan.',
                $roleAssignment
            );
        }

        return $this->formatResult(
            $detail,
            self::STATUS_CANDIDATE_DEACTIVATE,
            "Hak akses peran '{$detail->role?->name}' diberikan langsung saat aktivasi SK ini.",
            'Kandidat untuk dinonaktifkan jika pembatalan disetujui.',
            $roleAssignment
        );
    }

    /**
     * Mencari apakah ada SK GTK lain yang berstatus aktif yang menaungi penugasan operasional yang sama.
     */
    protected function findOtherActiveDecreesForDetail(GtkDecree $decree, GtkDecreeDetail $detail): Collection
    {
        $hasCondition = ($detail->operational_type && $detail->operational_id)
            || $detail->teacher_subject_assignment_id
            || $detail->teacher_structural_assignment_id
            || $detail->user_role_assignment_id;

        if (! $hasCondition) {
            return collect();
        }

        $query = GtkDecreeDetail::query()
            ->where('gtk_decree_id', '!=', $decree->id)
            ->where(function ($q) use ($detail) {
                $subConditions = false;

                if ($detail->operational_type && $detail->operational_id) {
                    $q->where(function ($sub) use ($detail) {
                        $sub->where('operational_type', $detail->operational_type)
                            ->where('operational_id', $detail->operational_id);
                    });
                    $subConditions = true;
                }

                if ($detail->teacher_subject_assignment_id) {
                    $subConditions ? $q->orWhere('teacher_subject_assignment_id', $detail->teacher_subject_assignment_id) : $q->where('teacher_subject_assignment_id', $detail->teacher_subject_assignment_id);
                    $subConditions = true;
                }

                if ($detail->teacher_structural_assignment_id) {
                    $subConditions ? $q->orWhere('teacher_structural_assignment_id', $detail->teacher_structural_assignment_id) : $q->where('teacher_structural_assignment_id', $detail->teacher_structural_assignment_id);
                    $subConditions = true;
                }

                if ($detail->user_role_assignment_id) {
                    $subConditions ? $q->orWhere('user_role_assignment_id', $detail->user_role_assignment_id) : $q->where('user_role_assignment_id', $detail->user_role_assignment_id);
                }
            });

        $today = now()->startOfDay();

        return $query->with('decree')
            ->get()
            ->map(fn ($d) => $d->decree)
            ->filter(function ($d) use ($today) {
                if (! $d || $d->status !== GtkDecree::STATUS_ACTIVE) {
                    return false;
                }
                if ($d->valid_until && $d->valid_until->startOfDay()->lt($today)) {
                    return false;
                }

                return true;
            })
            ->unique('id')
            ->values();
    }

    protected function isHomeroomPosition(?object $position): bool
    {
        if (! $position) {
            return false;
        }

        $code = strtolower((string) ($position->code ?? ''));
        $name = strtolower((string) ($position->name ?? ''));

        return str_contains($code, 'wali') || str_contains($name, 'wali kelas');
    }

    protected function isPiketPosition(?object $position): bool
    {
        if (! $position) {
            return false;
        }

        $code = strtolower((string) ($position->code ?? ''));
        $name = strtolower((string) ($position->name ?? ''));

        return str_contains($code, 'piket') || str_contains($name, 'piket');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatResult(
        GtkDecreeDetail $detail,
        string $status,
        string $reason,
        string $estimatedImpact,
        mixed $relatedRecord = null
    ): array {
        return [
            'detail_id' => $detail->id,
            'detail' => $detail,
            'user' => $detail->user,
            'category' => $detail->assignment_category,
            'category_label' => GtkDecreeDetail::CATEGORIES[$detail->assignment_category] ?? $detail->assignment_category,
            'link_type' => $detail->link_type,
            'link_type_label' => GtkDecreeDetail::LINK_TYPES[$detail->link_type] ?? $detail->link_type,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'status_badge' => self::STATUS_BADGES[$status] ?? 'bg-secondary',
            'reason' => $reason,
            'estimated_impact' => $estimatedImpact,
            'related_record' => $relatedRecord,
            'needs_manual_review' => $status === self::STATUS_MANUAL_REVIEW,
        ];
    }
}
