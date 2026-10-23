<?php

namespace App\Models;

use App\Services\DeviceDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CurriculumEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_all_day' => 'boolean',
        'is_holiday' => 'boolean',
        'target_grade_levels' => 'array',
        'target_classroom_ids' => 'array',
        'max_period' => 'integer',
        'disengaged_teacher_max_period' => 'integer',
        'disengaged_student_max_period' => 'integer',
    ];

    public function schoolActivity(): BelongsTo
    {
        return $this->belongsTo(SchoolActivity::class, 'school_activity_id');
    }

    public function academicCalendarUpload(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendarUpload::class, 'academic_calendar_upload_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curriculum_event_users', 'curriculum_event_id', 'user_id')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function activityCategory(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'category', 'code');
    }

    public static function categoryDefinitions(bool $onlyActive = true): array
    {
        $dynamicCats = ActivityCategory::categoryDefinitions($onlyActive);

        $kaldikCodes = [
            'lhb' => ['label' => 'Libur Hari Besar (LHB)', 'color' => '#dc2626', 'badge' => 'bg-danger text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'lu' => ['label' => 'Libur Umum (LU)', 'color' => '#ea580c', 'badge' => 'bg-orange text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'ls1' => ['label' => 'Libur Semester 1 (LS1)', 'color' => '#8b5cf6', 'badge' => 'bg-purple text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'ls2' => ['label' => 'Libur Semester 2 (LS2)', 'color' => '#7c3aed', 'badge' => 'bg-purple text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'cb' => ['label' => 'Cuti Bersama (CB)', 'color' => '#9333ea', 'badge' => 'bg-pink text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'kts' => ['label' => 'Kegiatan Tengah Semester (KTS)', 'color' => '#16a34a', 'badge' => 'bg-success text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'kpp' => ['label' => 'Kegiatan Permulaan Puasa (KPP)', 'color' => '#d97706', 'badge' => 'bg-yellow text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'lhr' => ['label' => 'Libur Sekitar Hari Raya (LHR)', 'color' => '#ca8a04', 'badge' => 'bg-yellow text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'ke' => ['label' => 'Kegiatan Ekstrakurikuler (KE)', 'color' => '#0284c7', 'badge' => 'bg-azure text-white', 'is_kaldik' => true, 'is_kaldik_code' => true],
            'kegiatan_kurikulum' => ['label' => 'Agenda Kurikulum', 'color' => '#2563eb', 'badge' => 'bg-blue text-white', 'is_kaldik' => false, 'is_kaldik_code' => true],
            'tugas_guru' => ['label' => 'Tugas & Pengingat Guru', 'color' => '#f97316', 'badge' => 'bg-warning text-dark', 'is_kaldik' => false, 'is_kaldik_code' => true],
        ];

        return array_merge($dynamicCats, $kaldikCodes);
    }

    public function getCategoryLabelAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['label'] ?? ucfirst($this->category);
    }

    public function getCategoryColorAttribute(): string
    {
        if (! empty($this->color)) {
            return $this->color;
        }
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['color'] ?? '#2563eb';
    }

    public function getCategoryBadgeAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['badge'] ?? 'bg-primary text-white';
    }

    public static function learningImpactDefinitions(): array
    {
        return [
            'normal' => [
                'label' => 'KBM Normal (Sesuai Jadwal)',
                'short_label' => 'KBM Normal',
                'badge' => 'bg-secondary text-white',
                'description' => 'Kegiatan tidak mengubah jadwal pelajaran kelas.',
            ],
            'pulang_cepat' => [
                'label' => 'Pulang Cepat (KBM Terbatas)',
                'short_label' => 'Pulang Cepat',
                'badge' => 'bg-warning text-dark',
                'description' => 'Pembelajaran dipulangkan lebih awal (misal karena rapat dinas / agenda sekolah).',
            ],
            'pembelajaran_daring' => [
                'label' => 'Pembelajaran Daring (Dari Rumah)',
                'short_label' => 'KBM Daring',
                'badge' => 'bg-info text-white',
                'description' => 'Siswa belajar secara daring dari rumah (bebas tatap muka di kelas).',
            ],
            'bebas_kbm_kegiatan' => [
                'label' => 'Bebas KBM (Kegiatan Khusus: PKL / KI / Ujian / Gladi)',
                'short_label' => 'Bebas KBM',
                'badge' => 'bg-purple text-white',
                'description' => 'Siswa berpartisipasi dalam kegiatan luar kelas / PKL / Gladi dan gugur kewajiban tatap muka reguler.',
            ],
            'libur_khusus' => [
                'label' => 'Libur Khusus / Diliburkan',
                'short_label' => 'Libur Khusus',
                'badge' => 'bg-danger text-white',
                'description' => 'Siswa pada sasaran terkait diliburkan dari aktivitas pembelajaran.',
            ],
        ];
    }

    public function getLearningImpactLabelAttribute(): string
    {
        $defs = static::learningImpactDefinitions();
        $impact = $this->learning_impact ?? 'normal';

        if (DeviceDetector::isMobile()) {
            return $defs[$impact]['short_label'] ?? $defs[$impact]['label'] ?? 'KBM Normal';
        }

        return $defs[$impact]['label'] ?? 'KBM Normal';
    }

    public function getLearningImpactShortLabelAttribute(): string
    {
        $defs = static::learningImpactDefinitions();
        $impact = $this->learning_impact ?? 'normal';

        return $defs[$impact]['short_label'] ?? $defs[$impact]['label'] ?? 'KBM Normal';
    }

    public function getLearningImpactBadgeAttribute(): string
    {
        $defs = static::learningImpactDefinitions();

        return $defs[$this->learning_impact ?? 'normal']['badge'] ?? 'bg-secondary text-white';
    }

    public function getDateSummaryAttribute(): string
    {
        if (! $this->start_date) {
            return '';
        }

        if (! $this->end_date || $this->start_date->equalTo($this->end_date)) {
            return 'Tgl: '.$this->start_date->translatedFormat('d M y');
        }

        $sameYear = $this->start_date->year === $this->end_date->year;
        $sameMonth = $sameYear && ($this->start_date->month === $this->end_date->month);

        if ($sameMonth) {
            return 'Tgl: '.$this->start_date->translatedFormat('d').' - '.$this->end_date->translatedFormat('d M y');
        }

        if ($sameYear) {
            return 'Tgl: '.$this->start_date->translatedFormat('d M').' - '.$this->end_date->translatedFormat('d M y');
        }

        return 'Tgl: '.$this->start_date->translatedFormat('d M y').' - '.$this->end_date->translatedFormat('d M y');
    }

    public function getStudentAudienceLabelAttribute(): string
    {
        $audience = $this->target_audience ?? 'semua';

        if ($audience === 'guru_staf') {
            return 'Hanya GTK';
        }

        if ($audience === 'tingkat') {
            $levels = (array) ($this->target_grade_levels ?? []);
            if (empty($levels)) {
                return 'Semua Warga';
            }

            $romanMap = [
                '10' => 'X',
                '11' => 'XI',
                '12' => 'XII',
                '13' => 'XIII',
            ];

            $levelLabels = array_map(function ($lvl) use ($romanMap) {
                $lvlStr = trim((string) $lvl);

                return $romanMap[$lvlStr] ?? $lvlStr;
            }, $levels);

            return 'Kelas '.implode(', ', $levelLabels);
        }

        if ($audience === 'rombel') {
            $classroomIds = array_map('intval', (array) ($this->target_classroom_ids ?? []));
            if (empty($classroomIds)) {
                return 'Semua Warga';
            }

            $classrooms = Classroom::whereIn('id', $classroomIds)->pluck('name');
            if ($classrooms->isNotEmpty()) {
                if ($classrooms->count() <= 2) {
                    return $classrooms->implode(', ');
                }

                return $classrooms->count().' Rombel';
            }

            return count($classroomIds).' Rombel';
        }

        return 'Semua Warga';
    }

    public function getStudentImpactTextAttribute(): string
    {
        if ($this->is_holiday && (! $this->learning_impact || $this->learning_impact === 'normal')) {
            return 'Libur Sekolah';
        }

        return match ($this->learning_impact) {
            'bebas_kbm_kegiatan' => 'Bebas KBM',
            'pulang_cepat' => 'Pulang Cepat',
            'pembelajaran_daring' => 'KBM Daring',
            'libur_khusus' => 'Libur Sekolah',
            default => $this->learning_impact_short_label ?? 'KBM Normal',
        };
    }

    public function getMobileSubtitleAttribute(): string
    {
        $audience = $this->student_audience_label;
        $impact = $this->student_impact_text;

        return $this->date_summary.' | '.$audience.' '.$impact;
    }

    public static function teacherImpactDefinitions(): array
    {
        return [
            'normal' => [
                'label' => 'Mengajar Reguler Sesuai Jadwal',
                'badge' => 'bg-secondary text-white',
                'description' => 'Guru tetap mengajar di kelas sesuai jadwal pelajaran biasa.',
            ],
            'bebas_mengajar_tugas' => [
                'label' => 'Gugur Kewajiban Mengajar (Panitia/Pengawas/Tugas Khusus)',
                'badge' => 'bg-success text-white',
                'description' => 'Guru yang ditugaskan bebas dari mengajar kelas reguler; jam kerja dan kehadiran mengikuti jadwal kegiatan/ujian.',
            ],
            'diliburkan_penuh' => [
                'label' => 'Bebas Masuk / Diliburkan Penuh',
                'badge' => 'bg-danger text-white',
                'description' => 'Guru dan staf tidak diwajibkan hadir ke sekolah.',
            ],
        ];
    }

    public function getTeacherImpactLabelAttribute(): string
    {
        $defs = static::teacherImpactDefinitions();

        return $defs[$this->teacher_impact ?? 'normal']['label'] ?? 'Mengajar Reguler';
    }

    public function getTeacherImpactBadgeAttribute(): string
    {
        $defs = static::teacherImpactDefinitions();

        return $defs[$this->teacher_impact ?? 'normal']['badge'] ?? 'bg-secondary text-white';
    }

    public static function periodScheduleMap(): array
    {
        return [
            1 => ['label' => 'Jam ke-1', 'time' => '07:45'],
            2 => ['label' => 'Jam ke-2', 'time' => '08:30'],
            3 => ['label' => 'Jam ke-3', 'time' => '09:15'],
            4 => ['label' => 'Jam ke-4', 'time' => '10:30'],
            5 => ['label' => 'Jam ke-5', 'time' => '11:10'],
            6 => ['label' => 'Jam ke-6', 'time' => '11:50'],
            7 => ['label' => 'Jam ke-7', 'time' => '12:30'],
            8 => ['label' => 'Jam ke-8', 'time' => '13:40'],
            9 => ['label' => 'Jam ke-9', 'time' => '14:20'],
            10 => ['label' => 'Jam ke-10', 'time' => '15:00'],
        ];
    }

    public static function disengagedTeacherImpactDefinitions(): array
    {
        return [
            'masuk_normal' => [
                'label' => 'Masuk Normal (Sesuai Jadwal)',
                'badge' => 'bg-secondary text-white',
            ],
            'pulang_cepat' => [
                'label' => 'Pulang Cepat',
                'badge' => 'bg-warning text-dark',
            ],
            'libur' => [
                'label' => 'Libur',
                'badge' => 'bg-danger text-white',
            ],
        ];
    }

    public static function disengagedStaffImpactDefinitions(): array
    {
        return [
            'masuk_normal' => [
                'label' => 'Masuk Normal',
                'badge' => 'bg-secondary text-white',
            ],
            'pulang_cepat' => [
                'label' => 'Pulang Cepat',
                'badge' => 'bg-warning text-dark',
            ],
            'libur' => [
                'label' => 'Libur',
                'badge' => 'bg-danger text-white',
            ],
        ];
    }

    public static function disengagedStudentImpactDefinitions(): array
    {
        return [
            'kbm_normal' => [
                'label' => 'KBM Normal',
                'badge' => 'bg-secondary text-white',
            ],
            'pembelajaran_daring' => [
                'label' => 'Pembelajaran Daring',
                'badge' => 'bg-info text-white',
            ],
            'pulang_cepat' => [
                'label' => 'Pulang Cepat',
                'badge' => 'bg-warning text-dark',
            ],
            'bebas_kbm' => [
                'label' => 'Bebas KBM',
                'badge' => 'bg-purple text-white',
            ],
            'libur_khusus' => [
                'label' => 'Libur Khusus',
                'badge' => 'bg-danger text-white',
            ],
        ];
    }

    public function getDisengagedTeacherImpactLabelAttribute(): string
    {
        $defs = static::disengagedTeacherImpactDefinitions();

        return $defs[$this->disengaged_teacher_impact ?? 'masuk_normal']['label'] ?? 'Masuk Normal';
    }

    public function getDisengagedStaffImpactLabelAttribute(): string
    {
        $defs = static::disengagedStaffImpactDefinitions();

        return $defs[$this->disengaged_staff_impact ?? 'masuk_normal']['label'] ?? 'Masuk Normal';
    }

    public function getDisengagedStudentImpactLabelAttribute(): string
    {
        $defs = static::disengagedStudentImpactDefinitions();

        return $defs[$this->disengaged_student_impact ?? 'kbm_normal']['label'] ?? 'KBM Normal';
    }

    /**
     * Cek apakah kegiatan kalender ini berdampak pada rombel kelas tertentu.
     */
    public function appliesToClassroom(Classroom $classroom): bool
    {
        // 1. Jika sasaran semua, maka berlaku untuk semua rombel
        if (empty($this->target_audience) || $this->target_audience === 'semua') {
            return true;
        }

        // 2. Jika sasaran tingkat (grade_levels)
        if ($this->target_audience === 'tingkat') {
            $levels = (array) ($this->target_grade_levels ?? []);
            $rawLevel = trim((string) ($classroom->gradeLevel?->level ?? ''));
            $gradeId = (int) $classroom->grade_level_id;

            $romanMap = [
                '10' => 'X',
                '11' => 'XI',
                '12' => 'XII',
                '13' => 'XIII',
            ];
            $romanToNumber = array_flip($romanMap);

            foreach ($levels as $lvl) {
                $lvlStr = trim((string) $lvl);
                // Cek kesamaan langsung (misal "XII" === "XII" atau "1" === "1")
                if (strcasecmp($lvlStr, $rawLevel) === 0 || (int) $lvlStr === $gradeId) {
                    return true;
                }
                // Cek konversi angka ke Romawi atau sebaliknya
                if (isset($romanMap[$lvlStr]) && strcasecmp($romanMap[$lvlStr], $rawLevel) === 0) {
                    return true;
                }
                if (isset($romanToNumber[strtoupper($lvlStr)]) && (int) $romanToNumber[strtoupper($lvlStr)] === (int) $rawLevel) {
                    return true;
                }
            }

            return false;
        }

        // 3. Jika sasaran rombel tertentu
        if ($this->target_audience === 'rombel') {
            $classroomIds = array_map('intval', (array) ($this->target_classroom_ids ?? []));

            return in_array((int) $classroom->id, $classroomIds, true);
        }

        return false;
    }

    /**
     * Cek apakah kegiatan ini berdampak pada guru tertentu pada jadwal mengajar hari ini.
     *
     * @param  iterable<LessonSchedule>|null  $mySchedules
     */
    public function affectsTeacher(User $user, $mySchedules = null): bool
    {
        $hasSpecificAssignedUsers = $this->relationLoaded('users')
            ? $this->users->isNotEmpty()
            : $this->users()->exists();

        $isUserAssigned = $this->relationLoaded('users')
            ? $this->users->contains('id', $user->id)
            : $this->users()->where('users.id', $user->id)->exists();

        // 1. Jika guru secara langsung ditugaskan pada agenda ini
        if ($isUserAssigned) {
            return true;
        }

        // 2. Hari libur sekolah atau seluruh guru diliburkan
        if ($this->is_holiday || $this->teacher_impact === 'diliburkan_penuh') {
            return true;
        }

        // 3. Jika guru bebas mengajar karena tugas khusus (hanya berdampak bagi guru yang ditugaskan)
        if ($this->teacher_impact === 'bebas_mengajar_tugas') {
            return $isUserAssigned;
        }

        // 4. Jika sasaran kegiatan adalah Guru & Staf (guru_staf):
        // Jika ada penugasan guru tertentu, hanya berlaku bagi yang ditugaskan.
        // Jika tidak ada guru spesifik (artinya rapat dinas seluruh GTK), maka berlaku untuk semua.
        if ($this->target_audience === 'guru_staf') {
            return ! $hasSpecificAssignedUsers || $isUserAssigned;
        }

        // 5. Kegiatan menyasar tingkat / rombel tertentu:
        // Berdampak HANYA jika ada kelas yang diajar guru pada hari ini yang terdampak kegiatan
        if ($this->target_audience === 'tingkat' || $this->target_audience === 'rombel') {
            if ($mySchedules) {
                foreach ($mySchedules as $sched) {
                    if ($sched->classroom && $this->appliesToClassroom($sched->classroom)) {
                        return true;
                    }
                }
            }

            return false;
        }

        // 6. Kegiatan menyasar seluruh siswa (semua):
        // Berdampak jika ada perubahan KBM siswa atau kepulangan guru
        if (empty($this->target_audience) || $this->target_audience === 'semua') {
            return ($this->learning_impact && $this->learning_impact !== 'normal')
                || in_array($this->disengaged_teacher_impact, ['pulang_cepat', 'libur']);
        }

        return false;
    }
}
