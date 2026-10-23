<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'name',
        'short_name',
        'username',
        'email',
        'password',
        'role_id',
        'nip',
        'nuptk',
        'nigs',
        'nigs_fix',
        'numgs',
        'nik',
        'birth_info',
        'birth_year',
        'gtk_status',
        'gender',
        'position',
        'tmt_year',
        'faculty',
        'education_level',
        'major',
        'graduation_year',
        'address',
        'city',
        'postal_code',
        'phone',
        'telegram_chat_id',
        'telegram_username',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function supervisionsAsTeacher(): HasMany
    {
        return $this->hasMany(Supervision::class, 'teacher_id');
    }

    public function supervisionsAsSupervisor(): HasMany
    {
        return $this->hasMany(Supervision::class, 'supervisor_id');
    }

    public function hasRole(string|array $roles): bool
    {
        if (! $this->role) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($this->role->slug, $roles);
        }

        return $this->role->slug === $roles;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->role?->hasPermission($permissionSlug) ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isWakaKurikulum(): bool
    {
        if ($this->hasRole(['super_admin', 'waka_kurikulum'])) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'kurikulum')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) {
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return str_contains($name, 'kurikulum') || str_contains($code, 'KURIKULUM');
            });
        }

        return $this->structuralPositions()
            ->where(function ($q) {
                $q->where('name', 'like', '%kurikulum%')
                    ->orWhere('code', 'like', '%KURIKULUM%');
            })->exists();
    }

    public function isSupervisor(?string $academicYear = null): bool
    {
        if ($this->hasRole(['supervisor', 'waka_kurikulum', 'super_admin'])) {
            return true;
        }

        if ($academicYear) {
            return $this->supervisorAssignments()
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->exists();
        }

        return $this->supervisorAssignments()->where('is_active', true)->exists();
    }

    public function isPrincipal(): bool
    {
        return $this->hasRole('kepala_sekolah');
    }

    public function isVicePrincipal(): bool
    {
        if ($this->hasRole('waka_kurikulum')) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'waka') || str_contains($pos, 'wakil')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) {
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return str_contains($name, 'waka') || str_contains($name, 'wakil') || str_starts_with($code, 'WAKA_');
            });
        }

        return $this->structuralPositions()
            ->where(function ($q) {
                $q->where('name', 'like', '%waka%')
                    ->orWhere('name', 'like', '%wakil%')
                    ->orWhere('code', 'like', 'WAKA_%');
            })->exists();
    }

    public function canSubmitPermitForOthers(): bool
    {
        return $this->isPrincipal() || $this->isVicePrincipal() || $this->isSuperAdmin();
    }

    public function canManageManualTeacherAttendance(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRole(['adm2', 'admin'])
            || $this->isVicePrincipal()
            || $this->isPrincipal();
    }

    /**
     * Get slugs of roles that are eligible for teaching duties and scheduling.
     *
     * @return array<int, string>
     */
    public static function getTeachingRoleSlugs(): array
    {
        return ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah', 'operator_kurikulum'];
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(['guru', 'operator_kurikulum']) || $this->hasTeachingDuty();
    }

    public function isOperator(): bool
    {
        return $this->hasRole([
            'operator_kurikulum',
            'operator_kesiswaan',
            'operator_sarpras',
            'operator_humas',
            'operator',
        ]);
    }

    public function isOperatorKurikulum(): bool
    {
        return $this->hasRole('operator_kurikulum');
    }

    public function isOperatorKesiswaan(): bool
    {
        return $this->hasRole('operator_kesiswaan');
    }

    public function isOperatorSarpras(): bool
    {
        return $this->hasRole('operator_sarpras');
    }

    public function isOperatorHumas(): bool
    {
        return $this->hasRole('operator_humas');
    }

    public function canManageCurriculum(): bool
    {
        return $this->isSuperAdmin()
            || $this->isWakaKurikulum()
            || $this->hasRole('operator_kurikulum')
            || $this->hasPermission('operate_curriculum');
    }

    public function hasTeachingDuty(): bool
    {
        if ($this->isStaff()) {
            return false;
        }

        return $this->lessonSchedules()->exists() || $this->subjectAssignments()->exists();
    }

    public function isWakaKesiswaan(): bool
    {
        if ($this->hasRole(['waka_kesiswaan', 'kesiswaan'])) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) {
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return str_contains($name, 'kesiswaan') || $code === 'WAKA_KESISWAAN';
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $hasPos = $this->structuralPositions()
                ->where(function ($q) {
                    $q->where('name', 'like', '%kesiswaan%')
                        ->orWhere('code', 'WAKA_KESISWAAN');
                })->exists();
            if ($hasPos) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'kesiswaan');
    }

    public function isCounselingBK(): bool
    {
        if ($this->hasRole('bk')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) {
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return str_contains($name, 'bk') || str_contains($name, 'konseling') || str_contains($name, 'bimbingan') || $code === 'BK';
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $hasPos = $this->structuralPositions()
                ->where(function ($q) {
                    $q->where('name', 'like', '%bk%')
                        ->orWhere('name', 'like', '%konseling%')
                        ->orWhere('name', 'like', '%bimbingan%')
                        ->orWhere('code', 'BK');
                })->exists();
            if ($hasPos) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'bk') || str_contains($pos, 'konseling') || str_contains($pos, 'bimbingan');
    }

    public function isDisciplineTeam(): bool
    {
        if ($this->hasRole(['tim_kedisiplinan', 'kesiswaan', 'bk'])) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) {
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return str_contains($name, 'kesiswaan') || str_contains($name, 'kedisiplinan') || str_contains($name, 'tatib') || str_contains($name, 'bk') || str_contains($name, 'konseling') || in_array($code, ['WAKA_KESISWAAN', 'BK', 'TATIB', 'DISIPLIN']);
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $hasPos = $this->structuralPositions()
                ->where(function ($q) {
                    $q->where('name', 'like', '%kesiswaan%')
                        ->orWhere('name', 'like', '%kedisiplinan%')
                        ->orWhere('name', 'like', '%tatib%')
                        ->orWhere('name', 'like', '%bk%')
                        ->orWhere('name', 'like', '%konseling%')
                        ->orWhereIn('code', ['WAKA_KESISWAAN', 'BK', 'TATIB', 'DISIPLIN']);
                })->exists();
            if ($hasPos) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'kesiswaan') || str_contains($pos, 'kedisiplinan') || str_contains($pos, 'tatib') || str_contains($pos, 'bk') || str_contains($pos, 'konseling') || str_contains($pos, 'bimbingan');
    }

    public function canViewAllSchedules(): bool
    {
        return $this->isSuperAdmin()
            || $this->isWakaKurikulum()
            || $this->isPrincipal()
            || $this->hasPermission('manage_master_data')
            || $this->hasPermission('manage_schedules');
    }

    public static function isStaffPosition(?string $position): bool
    {
        if (empty($position)) {
            return false;
        }

        $pos = strtolower($position);

        if (str_contains($pos, 'administrator sistem') || str_contains($pos, 'super admin')) {
            return false;
        }

        return str_contains($pos, 'administrasi')
            || str_contains($pos, 'satpam')
            || str_contains($pos, 'penjaga')
            || str_contains($pos, 'toolman')
            || str_contains($pos, 'pesuruh')
            || str_contains($pos, 'kebun')
            || str_contains($pos, 'tata usaha')
            || str_contains($pos, 'staf');
    }

    public function isStaff(): bool
    {
        if ($this->isSystemAdmin()) {
            return false;
        }

        return self::isStaffPosition($this->position);
    }

    /**
     * Determine if the user is a system administrator.
     */
    public function isSystemAdmin(): bool
    {
        return $this->isSuperAdmin()
            || $this->username === 'superadmin'
            || str_contains(strtolower($this->name ?? ''), 'administrator sistem')
            || str_contains(strtolower($this->position ?? ''), 'administrator sistem');
    }

    /**
     * Scope query to only include operational school personnel (teachers, staff, executives),
     * strictly excluding system administrators ("Administrator Sistem" / super_admin).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeOperationalPersonnel($query)
    {
        return $query->whereDoesntHave('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->where('username', '!=', 'superadmin')
            ->where('name', 'not like', '%Administrator Sistem%')
            ->where(function ($q) {
                $q->whereNull('position')
                    ->orWhere('position', 'not like', '%Administrator Sistem%');
            });
    }

    /**
     * Alias of scopeOperationalPersonnel to exclude system administrator(s).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeExcludeAdmin($query)
    {
        return $this->scopeOperationalPersonnel($query);
    }

    public function canManageAttendanceDevices(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessTeacherAttendanceReport(): bool
    {
        return $this->isSuperAdmin()
            || $this->isWakaKurikulum()
            || $this->isPrincipal()
            || str_contains(strtolower($this->position ?? ''), 'administrasi')
            || str_contains(strtolower($this->position ?? ''), 'tata usaha');
    }

    public function canAccessStudentAttendanceReport(): bool
    {
        return $this->isSuperAdmin()
            || $this->isWakaKurikulum()
            || $this->isPrincipal()
            || str_contains(strtolower($this->position ?? ''), 'administrasi')
            || str_contains(strtolower($this->position ?? ''), 'tata usaha')
            || $this->isHomeroomTeacher();
    }

    public function isPiketOfficerToday(): bool
    {
        $today = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => null,
        };

        if (! $today) {
            return false;
        }

        return PiketSchedule::where('teacher_id', $this->id)
            ->where('day', $today)
            ->where('is_active', true)
            ->exists();
    }

    public function canAccessPiket(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('access_piket');
    }

    public function canOperatePiket(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('operate_piket')
            || $this->isPiketOfficerToday();
    }

    public function canManagePiketCases(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('manage_piket_cases')
            || $this->isPiketOfficerToday();
    }

    public function isPiketCoordinator(): bool
    {
        return $this->piketSchedules()
            ->where('role', 'koordinator')
            ->where('is_active', true)
            ->exists();
    }

    public function canManagePiketSchedules(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('manage_piket_schedules')
            || $this->isPiketOfficerToday()
            || $this->isPiketCoordinator()
            || $this->piketSchedules()->where('is_active', true)->exists();
    }

    public function canViewPiketReports(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('view_piket_reports')
            || $this->isPiketOfficerToday()
            || $this->canOperatePiket()
            || $this->isPiketCoordinator()
            || $this->piketSchedules()->where('is_active', true)->exists();
    }

    public function canManagePiketSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('manage_piket_settings');
    }

    public function getWhatsappNumberAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', $this->phone);
        if (str_starts_with($clean, '0')) {
            return '62'.substr($clean, 1);
        }

        return $clean;
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        $wa = $this->whatsapp_number;
        if (! $wa) {
            return null;
        }

        return "https://wa.me/{$wa}";
    }

    /**
     * Dapatkan ID seluruh rombel kelas yang relevan dengan guru ini pada tahun ajaran tertentu.
     * Meliputi kelas yang diajar (jadwal pelajaran & SK penugasan) dan kelas perwalian (wali kelas).
     *
     * @return Collection<int, int>
     */
    public function getRelevantClassroomIds(?string $academicYear = null): Collection
    {
        $teachingClassroomIds = LessonSchedule::where('teacher_id', $this->id)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->pluck('classroom_id');

        $assignedClassroomIds = TeacherSubjectAssignment::where('teacher_id', $this->id)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->pluck('classroom_id');

        $homeroomClassroomIds = Classroom::where('homeroom_teacher_id', $this->id)
            ->when($academicYear, function ($q) use ($academicYear) {
                $q->where(function ($sub) use ($academicYear) {
                    $sub->where('academic_year', $academicYear)
                        ->orWhereNull('academic_year');
                });
            })
            ->pluck('id');

        return $teachingClassroomIds
            ->merge($assignedClassroomIds)
            ->merge($homeroomClassroomIds)
            ->unique()
            ->values();
    }

    public function canConductSupervision(Supervision $supervision): bool
    {
        if ($this->hasRole('super_admin') || $this->isWakaKurikulum()) {
            return true;
        }

        if ($supervision->supervisor_id === $this->id) {
            return true;
        }

        // Guru yang disupervisi dapat melakukan penilaian mandiri / koreksi jika memiliki hak akses conduct_supervision
        if ($supervision->teacher_id === $this->id && $this->hasPermission('conduct_supervision')) {
            return true;
        }

        if ($this->hasPermission('conduct_supervision') && $this->hasPermission('view_all_supervision')) {
            return true;
        }

        return false;
    }

    public function canEditSupervision(Supervision $supervision): bool
    {
        if ($supervision->status === 'disetujui' || $supervision->status === 'selesai') {
            return false;
        }

        return $this->canConductSupervision($supervision);
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'teacher_id');
    }

    public function teachingSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject_assignments', 'teacher_id', 'subject_id')
            ->withPivot(['classroom_id', 'academic_year', 'semester', 'hours_per_week', 'notes'])
            ->distinct();
    }

    public function homeroomClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'homeroom_teacher_id');
    }

    public function isHomeroomTeacher(): bool
    {
        return $this->homeroomClassrooms()->exists();
    }

    public function lessonSchedules(): HasMany
    {
        return $this->hasMany(LessonSchedule::class, 'teacher_id');
    }

    public function structuralAssignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class, 'teacher_id');
    }

    public function structuralPositions(): BelongsToMany
    {
        return $this->belongsToMany(StructuralPosition::class, 'teacher_structural_assignments', 'teacher_id', 'structural_position_id')
            ->withPivot(['academic_year', 'sk_number', 'sk_date', 'notes', 'is_active'])
            ->withTimestamps();
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(TeacherSupervisorAssignment::class, 'teacher_id');
    }

    public function piketSchedules(): HasMany
    {
        return $this->hasMany(PiketSchedule::class, 'teacher_id');
    }

    public function getFormattedShortNameAttribute(): string
    {
        if (! empty($this->short_name)) {
            return $this->short_name;
        }

        return static::stripTitlesAndAbbreviate($this->name);
    }

    public static function stripTitlesAndAbbreviate(?string $fullName): string
    {
        if (! $fullName) {
            return '';
        }

        // 1. Remove academic prefix titles (Drs., Dra., Dr., Prof., H., Hj., Ir., etc.)
        $clean = preg_replace('/^(Drs\.|Dra\.|Dr\.|Prof\.|H\.|Hj\.|Ir\.)\s+/i', '', trim($fullName));

        // 2. Remove academic suffix titles (e.g. , S.Pd., M.Pd, ST, etc.)
        $parts = explode(',', $clean);
        $nameWithoutTitles = trim($parts[0]);

        // Convert to Title Case
        $nameWithoutTitles = ucwords(strtolower($nameWithoutTitles));

        // 3. Smart abbreviation if more than 2 words
        $words = explode(' ', $nameWithoutTitles);
        $words = array_values(array_filter($words));

        if (count($words) <= 2) {
            return implode(' ', $words);
        }

        // If starts with common prefix like Moh, Moch, Mochammad, Muhammad -> abbreviate to M.
        if (in_array(strtolower($words[0]), ['moh.', 'moh', 'moch.', 'moch', 'mochammad', 'muhammad', 'muchammad'])) {
            $words[0] = 'M.';
        }

        // If 3 words, keep first 2 and initial the 3rd, e.g. "Diah Ayu Kumalasari" -> "Diah Ayu K."
        if (count($words) === 3) {
            $lastInitial = strtoupper(substr($words[2], 0, 1)).'.';

            return "{$words[0]} {$words[1]} {$lastInitial}";
        }

        // If 4 words or more, keep first 2 and initial the subsequent words, e.g. "Yoga Anggorina Kukuh Kurniawan" -> "Yoga Anggorina K. K."
        if (count($words) >= 4) {
            $result = "{$words[0]} {$words[1]}";
            for ($i = 2; $i < count($words); $i++) {
                $result .= ' '.strtoupper(substr($words[$i], 0, 1)).'.';
            }

            return $result;
        }

        return implode(' ', $words);
    }

    public function setNipAttribute(?string $value): void
    {
        $this->attributes['nip'] = Supervision::sanitizeNip($value);
    }

    public function setNuptkAttribute(?string $value): void
    {
        $this->attributes['nuptk'] = Supervision::sanitizeNuptk($value);
    }

    public function getIdLabelAttribute(): string
    {
        return ! empty(Supervision::sanitizeNip($this->nip)) ? 'NIP' : 'NIGS';
    }

    public function getIdNumberAttribute(): string
    {
        return Supervision::sanitizeNip($this->nip) ?: ($this->nigs_fix ?: $this->nigs ?: '-');
    }

    public function getFormattedIdAttribute(): string
    {
        $label = $this->id_label;
        $num = $this->id_number;

        return $num !== '-' ? "{$label}. {$num}" : '-';
    }

    public function getActivityLogTitle(): string
    {
        return "Pengguna/Guru '{$this->name}' ({$this->username})";
    }
}
