<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\UserRoleAssignment;

class GtkDecreeImpactPreviewService
{
    public const STATUS_WILL_LINK = 'akan_ditautkan';

    public const STATUS_WILL_CREATE = 'akan_dibuat';

    public const STATUS_CONFLICT = 'konflik';

    public const STATUS_NOT_APPLICABLE = 'tidak_memerlukan';

    public const STATUS_INCOMPLETE = 'belum_lengkap';

    public const STATUS_LABELS = [
        self::STATUS_WILL_LINK => 'Akan Ditautkan',
        self::STATUS_WILL_CREATE => 'Akan Dibuat',
        self::STATUS_CONFLICT => 'Konflik',
        self::STATUS_NOT_APPLICABLE => 'Tanpa Penugasan Operasional',
        self::STATUS_INCOMPLETE => 'Belum Lengkap',
    ];

    public const STATUS_BADGES = [
        self::STATUS_WILL_LINK => 'bg-info text-white',
        self::STATUS_WILL_CREATE => 'bg-success text-white',
        self::STATUS_CONFLICT => 'bg-danger text-white',
        self::STATUS_NOT_APPLICABLE => 'bg-secondary text-white',
        self::STATUS_INCOMPLETE => 'bg-warning text-dark',
    ];

    /**
     * Menganalisis dampak penerapan SK secara murni read-only.
     * Tidak mengubah data operasional, hak akses, jadwal, atau status butir keputusan.
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
        ]);

        $targetAcademicYear = $decree->academicYear?->name
            ?? $decree->academic_year
            ?? AcademicYear::getActive()?->name
            ?? '2026/2027';

        $items = [];
        $counts = [
            self::STATUS_WILL_LINK => 0,
            self::STATUS_WILL_CREATE => 0,
            self::STATUS_CONFLICT => 0,
            self::STATUS_NOT_APPLICABLE => 0,
            self::STATUS_INCOMPLETE => 0,
        ];

        foreach ($decree->details as $detail) {
            $analysis = $this->analyzeDetail($decree, $detail, $targetAcademicYear);
            $items[] = $analysis;
            $counts[$analysis['status']]++;
        }

        $total = count($items);
        $isReady = $total > 0 && $counts[self::STATUS_CONFLICT] === 0 && $counts[self::STATUS_INCOMPLETE] === 0;

        return [
            'decree' => $decree,
            'target_academic_year' => $targetAcademicYear,
            'items' => $items,
            'summary' => [
                'total' => $total,
                'will_link' => $counts[self::STATUS_WILL_LINK],
                'will_create' => $counts[self::STATUS_WILL_CREATE],
                'conflict' => $counts[self::STATUS_CONFLICT],
                'not_applicable' => $counts[self::STATUS_NOT_APPLICABLE],
                'incomplete' => $counts[self::STATUS_INCOMPLETE],
                'is_ready_for_activation' => $isReady,
            ],
        ];
    }

    /**
     * Menganalisis butir rincian keputusan individual.
     *
     * @return array<string, mixed>
     */
    protected function analyzeDetail(GtkDecree $decree, GtkDecreeDetail $detail, string $targetAcademicYear): array
    {
        // 1. Validasi Keberadaan GTK
        if (! $detail->user_id || ! $detail->user) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                'Personil GTK tidak ditemukan atau akun pengguna sudah dihapus.',
                'Perbaiki data GTK penerima tugas pada draf SK ini.'
            );
        }

        // 2. Validasi Konsistensi Tanggal
        if ($detail->valid_from && $detail->valid_until && $detail->valid_until->lt($detail->valid_from)) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                "Tanggal akhir penugasan ({$detail->valid_until->format('d/m/Y')}) mendahului tanggal mulai ({$detail->valid_from->format('d/m/Y')}).",
                'Sesuaikan tanggal berlaku agar tanggal akhir sama atau setelah tanggal mulai.'
            );
        }

        // 3. Kategori Arsip / Dokumentasi (Tanpa Penugasan Operasional)
        if ($detail->assignment_category === GtkDecreeDetail::CATEGORY_ARSIP) {
            return $this->formatResult(
                $detail,
                self::STATUS_NOT_APPLICABLE,
                'Butir keputusan berstatus arsip/dokumentasi dinas dan tidak memerlukan baris penugasan operasional.',
                'Tidak ada tindakan operasional yang akan dieksekusi.'
            );
        }

        // 4. Analisis Spesifik per Kategori
        return match ($detail->assignment_category) {
            GtkDecreeDetail::CATEGORY_MENGAJAR => $this->analyzeTeachingAssignment($decree, $detail, $targetAcademicYear),
            GtkDecreeDetail::CATEGORY_STRUKTURAL, GtkDecreeDetail::CATEGORY_OPERASIONAL => $this->analyzeStructuralOrOperationalAssignment($decree, $detail, $targetAcademicYear),
            GtkDecreeDetail::CATEGORY_PERAN_SISTEM => $this->analyzeSystemRoleAssignment($decree, $detail, $targetAcademicYear),
            default => $this->formatResult(
                $detail,
                self::STATUS_NOT_APPLICABLE,
                'Kategori penugasan lainnya/arsip.',
                'Hanya dicatat sebagai arsip riwayat penugasan.'
            ),
        };
    }

    /**
     * Analisis Penugasan Mengajar (KBM).
     *
     * @return array<string, mixed>
     */
    protected function analyzeTeachingAssignment(GtkDecree $decree, GtkDecreeDetail $detail, string $targetAcademicYear): array
    {
        // Kelengkapan Mata Pelajaran
        if (! $detail->subject_id || ! $detail->subject) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                'Mata pelajaran wajib dipilih untuk kategori penugasan mengajar.',
                'Lengkapi mata pelajaran yang diampu pada rincian butir ini.'
            );
        }

        $subjectName = $detail->subject->name;
        $className = $detail->classroom?->name ?? 'Semua Kelas / Tanpa Rombel Tertentu';

        // Cari penugasan mapel eksisting di database operasional (read-only)
        $query = TeacherSubjectAssignment::query()
            ->where('subject_id', $detail->subject_id)
            ->where('academic_year', $targetAcademicYear);

        if ($detail->classroom_id) {
            $query->where('classroom_id', $detail->classroom_id);
        } else {
            $query->whereNull('classroom_id');
        }

        $existingAssignments = $query->with('teacher')->get();

        // 1. Periksa apakah sudah ada guru lain yang mengajar mapel & rombel yang sama (KONFLIK)
        $conflicting = $existingAssignments->first(fn ($a) => (int) $a->teacher_id !== (int) $detail->user_id);
        if ($conflicting) {
            $currentTeacher = $conflicting->teacher?->name ?? "Guru #{$conflicting->teacher_id}";

            return $this->formatResult(
                $detail,
                self::STATUS_CONFLICT,
                "Mapel '{$subjectName}' pada rombel '{$className}' untuk TP {$targetAcademicYear} saat ini sudah diampu oleh {$currentTeacher}.",
                'Periksa kembali penugasan. Sistem tidak akan menimpa penugasan guru lama secara otomatis.',
                $conflicting,
                [
                    'rencana_sk' => [
                        'guru' => $detail->user->name,
                        'mapel' => $subjectName,
                        'kelas' => $className,
                        'jam_mengajar' => ($detail->teaching_hours ?? 2).' jam/mg',
                    ],
                    'data_eksisting' => [
                        'guru' => $currentTeacher,
                        'mapel' => $subjectName,
                        'kelas' => $className,
                        'jam_mengajar' => $conflicting->hours_per_week.' jam/mg',
                        'tahun_ajaran' => $conflicting->academic_year,
                    ],
                ]
            );
        }

        // 2. Periksa apakah penugasan identik untuk guru ini sudah ada (AKAN DITAUTKAN)
        $matching = $existingAssignments->first(fn ($a) => (int) $a->teacher_id === (int) $detail->user_id);
        if ($matching) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_LINK,
                "Penugasan mengajar mapel '{$subjectName}' di kelas '{$className}' untuk {$detail->user->name} sudah tercatat di sistem ({$matching->hours_per_week} jam/minggu).",
                'Akan ditautkan ke baris penugasan operasional yang sudah ada tanpa membuat baris baru.',
                $matching
            );
        }

        // 3. Belum ada penugasan dan tidak ada konflik (AKAN DIBUAT)
        $hours = $detail->teaching_hours ?? 2;

        return $this->formatResult(
            $detail,
            self::STATUS_WILL_CREATE,
            "Penugasan mengajar '{$subjectName}' di kelas '{$className}' belum ada di sistem operasional.",
            "Rencana pembuatan penugasan baru: {$hours} jam/minggu untuk {$detail->user->name} pada TP {$targetAcademicYear}."
        );
    }

    /**
     * Analisis Penugasan Struktural, Operasional, atau Wali Kelas.
     *
     * @return array<string, mixed>
     */
    protected function analyzeStructuralOrOperationalAssignment(GtkDecree $decree, GtkDecreeDetail $detail, string $targetAcademicYear): array
    {
        // Kasus Khusus A: Penugasan Wali Kelas berbasis Rombel
        if ($detail->classroom_id && (! $detail->structural_position_id || $this->isHomeroomPosition($detail->structuralPosition))) {
            return $this->analyzeHomeroomClassroomAssignment($decree, $detail, $targetAcademicYear);
        }

        // Validasi Keberadaan Master Jabatan
        if (! $detail->structural_position_id || ! $detail->structuralPosition) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                'Jabatan struktural/operasional belum dipilih atau referensi master tidak valid.',
                'Pilih jabatan struktural/operasional pada rincian butir ini.'
            );
        }

        $position = $detail->structuralPosition;
        $posName = $position->name;

        // Kasus Khusus B: Penugasan Piket (piket_schedules memerlukan hari tugas spesifik Senin-Sabtu)
        if ($this->isPiketPosition($position)) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                "Penugasan piket '{$posName}' memerlukan informasi hari tugas mingguan (Senin-Sabtu) yang spesifik pada modul Jadwal Piket.",
                'Rincian SK belum memuat kolom hari piket. Penugasan piket dikelola melalui jadwal piket operasional.'
            );
        }

        $detailStart = $detail->valid_from?->format('Y-m-d') ?? $decree->valid_from?->format('Y-m-d');
        $detailEnd = $detail->valid_until?->format('Y-m-d') ?? $decree->valid_until?->format('Y-m-d');

        // Cari penugasan jabatan aktif di database operasional
        $existingAssignments = TeacherStructuralAssignment::query()
            ->where('structural_position_id', $position->id)
            ->where('is_active', true)
            ->with('teacher')
            ->get();

        // 1. Periksa apakah penugasan untuk guru ini sudah aktif (AKAN DITAUTKAN)
        $matching = $existingAssignments->first(function ($asgn) use ($detail, $detailStart, $detailEnd) {
            if ((int) $asgn->teacher_id !== (int) $detail->user_id) {
                return false;
            }

            // Cek irisan masa berlaku
            $asgnStart = $asgn->valid_from?->format('Y-m-d') ?? $asgn->sk_date?->format('Y-m-d');
            $asgnEnd = $asgn->valid_until?->format('Y-m-d');

            return $this->datesOverlap($detailStart, $detailEnd, $asgnStart, $asgnEnd);
        });

        if ($matching) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_LINK,
                "Penugasan jabatan '{$posName}' untuk {$detail->user->name} sudah tercatat aktif di sistem operasional.",
                'Akan ditautkan ke penugasan jabatan struktural eksisting.',
                $matching
            );
        }

        // 2. Periksa konflik untuk jabatan tunggal (allows_multiple == false)
        if (! $position->allows_multiple) {
            $conflicting = $existingAssignments->first(function ($asgn) use ($detail, $detailStart, $detailEnd) {
                if ((int) $asgn->teacher_id === (int) $detail->user_id) {
                    return false;
                }

                $asgnStart = $asgn->valid_from?->format('Y-m-d') ?? $asgn->sk_date?->format('Y-m-d');
                $asgnEnd = $asgn->valid_until?->format('Y-m-d');

                return $this->datesOverlap($detailStart, $detailEnd, $asgnStart, $asgnEnd);
            });

            if ($conflicting) {
                $incumbent = $conflicting->teacher?->name ?? "Personil #{$conflicting->teacher_id}";
                $incumbentPeriod = ($conflicting->valid_from?->format('d/m/Y') ?? 'Awal')
                    .' s.d '
                    .($conflicting->valid_until?->format('d/m/Y') ?? 'Sekarang');

                return $this->formatResult(
                    $detail,
                    self::STATUS_CONFLICT,
                    "Jabatan '{$posName}' bersifat tunggal dan saat ini aktif dipegang oleh {$incumbent} (Periode: {$incumbentPeriod}).",
                    'Konflik jabatan tunggal. Diperlukan penonaktifan pejabat lama atau penyesuaian tanggal efektif sebelum aktivasi.',
                    $conflicting,
                    [
                        'rencana_sk' => [
                            'personil' => $detail->user->name,
                            'jabatan' => $posName,
                            'periode' => ($detailStart ?? 'Awal').' s.d '.($detailEnd ?? 'Selesai'),
                        ],
                        'pejabat_aktif' => [
                            'personil' => $incumbent,
                            'jabatan' => $posName,
                            'periode' => $incumbentPeriod,
                            'no_sk_lama' => $conflicting->sk_number ?? '-',
                        ],
                    ]
                );
            }
        }

        // 3. Belum ada dan tidak ada konflik (AKAN DIBUAT)
        $multipleNote = $position->allows_multiple ? ' (Jabatan memperbolehkan banyak personil)' : '';

        return $this->formatResult(
            $detail,
            self::STATUS_WILL_CREATE,
            "Jabatan '{$posName}' belum ditugaskan kepada {$detail->user->name} pada periode yang direncanakan{$multipleNote}.",
            "Rencana pembuatan penugasan struktural baru: {$posName} untuk {$detail->user->name}."
        );
    }

    /**
     * Analisis Penugasan Wali Kelas berbasis Rombel (Classrooms).
     *
     * @return array<string, mixed>
     */
    protected function analyzeHomeroomClassroomAssignment(GtkDecree $decree, GtkDecreeDetail $detail, string $targetAcademicYear): array
    {
        $classroom = Classroom::find($detail->classroom_id);
        if (! $classroom) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                'Rombongan belajar tidak ditemukan atau telah dihapus.',
                'Pilih rombongan belajar yang valid pada rincian butir ini.'
            );
        }

        $className = $classroom->name;
        $currentTeacherId = $classroom->homeroom_teacher_id;

        // 1. Sudah tercatat sebagai wali kelas rombel ini (AKAN DITAUTKAN)
        if ($currentTeacherId && (int) $currentTeacherId === (int) $detail->user_id) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_LINK,
                "{$detail->user->name} sudah tercatat sebagai Wali Kelas untuk rombel '{$className}'.",
                'Akan ditautkan ke data wali kelas rombel eksisting.',
                $classroom
            );
        }

        // 2. Rombel sudah memiliki wali kelas lain (KONFLIK)
        if ($currentTeacherId && (int) $currentTeacherId !== (int) $detail->user_id) {
            $incumbent = $classroom->homeroomTeacher?->name ?? "Guru #{$currentTeacherId}";

            return $this->formatResult(
                $detail,
                self::STATUS_CONFLICT,
                "Rombongan belajar '{$className}' saat ini sudah memiliki wali kelas: {$incumbent}.",
                'Rombel sudah terisi wali kelas lain. Diperlukan penyesuaian atau persetujuan mutasi wali kelas sebelum aktivasi.',
                $classroom,
                [
                    'rencana_sk' => [
                        'wali_kelas_baru' => $detail->user->name,
                        'rombel' => $className,
                    ],
                    'wali_kelas_saat_ini' => [
                        'wali_kelas_aktif' => $incumbent,
                        'rombel' => $className,
                    ],
                ]
            );
        }

        // 3. Rombel belum memiliki wali kelas (AKAN DIBUAT / DITETAPKAN)
        return $this->formatResult(
            $detail,
            self::STATUS_WILL_CREATE,
            "Rombel '{$className}' belum memiliki wali kelas.",
            "Rencana penetapan: Menetapkan {$detail->user->name} sebagai Wali Kelas '{$className}'."
        );
    }

    /**
     * Analisis Penugasan Peran Hak Akses Sistem (UserRoleAssignment).
     *
     * @return array<string, mixed>
     */
    protected function analyzeSystemRoleAssignment(GtkDecree $decree, GtkDecreeDetail $detail, string $targetAcademicYear): array
    {
        if (! $detail->role_id || ! $detail->role) {
            return $this->formatResult(
                $detail,
                self::STATUS_INCOMPLETE,
                'Role hak akses sistem belum dipilih atau tidak valid.',
                'Pilih role hak akses pada rincian butir ini.'
            );
        }

        $role = $detail->role;
        $roleName = $role->name;

        // Cek penugasan role aktif di user_role_assignments
        $existing = UserRoleAssignment::query()
            ->where('user_id', $detail->user_id)
            ->where('role_id', $role->id)
            ->where('status', true)
            ->first();

        if ($existing) {
            return $this->formatResult(
                $detail,
                self::STATUS_WILL_LINK,
                "Hak akses peran '{$roleName}' sudah aktif dimiliki oleh {$detail->user->name}.",
                'Akan ditautkan ke riwayat penetapan peran hak akses yang sudah ada.',
                $existing
            );
        }

        return $this->formatResult(
            $detail,
            self::STATUS_WILL_CREATE,
            "Peran hak akses '{$roleName}' belum aktif pada akun {$detail->user->name}.",
            "Rencana penetapan: Memberikan hak akses peran '{$roleName}' kepada {$detail->user->name}."
        );
    }

    /**
     * Memeriksa apakah dua rentang tanggal saling beririsan.
     * Menggunakan penanganan null yang aman untuk masa jabatan terbuka (open-ended).
     */
    public function datesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if ($startA && $endB && $startA > $endB) {
            return false;
        }

        if ($startB && $endA && $startB > $endA) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah jabatan struktural merupakan jabatan Wali Kelas.
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

    /**
     * Cek apakah jabatan struktural merupakan tugas Guru Piket.
     */
    protected function isPiketPosition(?StructuralPosition $position): bool
    {
        if (! $position) {
            return false;
        }

        $code = strtolower((string) $position->code);
        $name = strtolower((string) $position->name);

        return str_contains($code, 'piket') || str_contains($name, 'piket');
    }

    /**
     * Format struktur hasil analisis per butir rincian.
     *
     * @param  array<string, mixed>|null  $conflictComparison
     * @return array<string, mixed>
     */
    protected function formatResult(
        GtkDecreeDetail $detail,
        string $status,
        string $notes,
        string $recommendation,
        mixed $existingRecord = null,
        ?array $conflictComparison = null
    ): array {
        return [
            'detail_id' => $detail->id,
            'detail' => $detail,
            'user' => $detail->user,
            'category' => $detail->assignment_category,
            'category_label' => GtkDecreeDetail::CATEGORIES[$detail->assignment_category] ?? $detail->assignment_category,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'status_badge' => self::STATUS_BADGES[$status] ?? 'bg-secondary',
            'notes' => $notes,
            'recommendation' => $recommendation,
            'existing_record' => $existingRecord,
            'conflict_comparison' => $conflictComparison,
        ];
    }
}
