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

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class, 'user_id');
    }

    public function activeRoleAssignments(): HasMany
    {
        $today = now()->toDateString();

        return $this->hasMany(UserRoleAssignment::class, 'user_id')
            ->where('status', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            });
    }

    public function hasRole(string|array $roles, mixed $context = null): bool
    {
        $targetSlugs = is_array($roles) ? $roles : [$roles];

        // 1. Super Admin always matches
        if ($this->role?->slug === 'super_admin') {
            return true;
        }

        // 2. Check primary role if no specific context is requested
        if ($context === null && in_array($this->role?->slug, $targetSlugs, true)) {
            return true;
        }

        // 3. Check contextual role assignments
        if ($this->relationLoaded('activeRoleAssignments')) {
            return $this->activeRoleAssignments->contains(function ($assignment) use ($targetSlugs, $context) {
                if (! $assignment->role || ! in_array($assignment->role->slug, $targetSlugs, true)) {
                    return false;
                }
                if ($context !== null) {
                    if ($assignment->context_type === null && $assignment->context_id === null) {
                        return true; // Global assignment applies everywhere
                    }
                    $ctxType = is_object($context) ? get_class($context) : null;
                    $ctxId = is_object($context) ? ($context->id ?? null) : $context;

                    return $assignment->context_type === $ctxType && (string) $assignment->context_id === (string) $ctxId;
                }

                return true;
            });
        }

        return $this->activeRoleAssignments()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $targetSlugs))
            ->when($context !== null, function ($q) use ($context) {
                $ctxType = is_object($context) ? get_class($context) : null;
                $ctxId = is_object($context) ? ($context->id ?? null) : $context;
                $q->where(function ($sub) use ($ctxType, $ctxId) {
                    $sub->where(function ($c) use ($ctxType, $ctxId) {
                        $c->where('context_type', $ctxType)->where('context_id', $ctxId);
                    })->orWhere(function ($c) {
                        $c->whereNull('context_type')->whereNull('context_id');
                    });
                });
            })
            ->exists();
    }

    public function hasPermission(string $permissionSlug, mixed $context = null): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Check primary role if no context is requested
        if ($context === null && ($this->role?->hasPermission($permissionSlug) ?? false)) {
            return true;
        }

        // Check active contextual role assignments
        if ($this->relationLoaded('activeRoleAssignments')) {
            return $this->activeRoleAssignments->contains(function ($assignment) use ($permissionSlug, $context) {
                if (! $assignment->role?->hasPermission($permissionSlug)) {
                    return false;
                }
                if ($context !== null) {
                    if ($assignment->context_type === null && $assignment->context_id === null) {
                        return true;
                    }
                    $ctxType = is_object($context) ? get_class($context) : null;
                    $ctxId = is_object($context) ? ($context->id ?? null) : $context;

                    return $assignment->context_type === $ctxType && (string) $assignment->context_id === (string) $ctxId;
                }

                return true;
            });
        }

        return $this->activeRoleAssignments()
            ->whereHas('role.permissions', fn ($q) => $q->where('slug', $permissionSlug))
            ->when($context !== null, function ($q) use ($context) {
                $ctxType = is_object($context) ? get_class($context) : null;
                $ctxId = is_object($context) ? ($context->id ?? null) : $context;
                $q->where(function ($sub) use ($ctxType, $ctxId) {
                    $sub->where(function ($c) use ($ctxType, $ctxId) {
                        $c->where('context_type', $ctxType)->where('context_id', $ctxId);
                    })->orWhere(function ($c) {
                        $c->whereNull('context_type')->whereNull('context_id');
                    });
                });
            })
            ->exists();
    }

    /**
     * Get all active roles (primary role + active assigned roles).
     *
     * @return Collection<int, Role>
     */
    public function allRoles(): Collection
    {
        $roles = collect();
        if ($this->role) {
            $roles->push($this->role);
        }

        $assignedRoles = $this->relationLoaded('activeRoleAssignments')
            ? $this->activeRoleAssignments->pluck('role')->filter()
            : $this->activeRoleAssignments()->with('role')->get()->pluck('role')->filter();

        return $roles->concat($assignedRoles)->unique('id');
    }

    /**
     * Assign a role to this user.
     */
    public function assignRole(Role|string $role, array $attributes = []): UserRoleAssignment
    {
        $roleModel = is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;

        return $this->roleAssignments()->create(array_merge([
            'role_id' => $roleModel->id,
            'status' => true,
        ], $attributes));
    }

    /**
     * Get array representation of role assignments for UI modals.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFormattedRoleAssignmentsAttribute(): array
    {
        return $this->roleAssignments->map(function ($a) {
            return [
                'id' => $a->id,
                'role_name' => $a->role?->name,
                'role_slug' => $a->role?->slug,
                'context_label' => $a->context_label ?: 'Global',
                'academic_year' => $a->academic_year ?? '-',
                'status' => (bool) $a->status,
                'sk_number' => $a->sk_number ?? '-',
                'notes' => $a->notes ?? '-',
                'toggle_url' => route('users.assignments.toggle', $a->id),
                'delete_url' => route('users.assignments.destroy', $a->id),
            ];
        })->toArray();
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
        // 1. Super Admin dan Waka Kurikulum selalu memiliki wewenang supervisi
        if ($this->isSuperAdmin() || $this->isWakaKurikulum()) {
            return true;
        }

        // 2. Jika tahun ajaran spesifik diperiksa
        if ($academicYear !== null) {
            // Cek TeacherSupervisorAssignment terlebih dahulu
            if ($this->relationLoaded('supervisorAssignments')) {
                $assignment = $this->supervisorAssignments->firstWhere('academic_year', $academicYear);
                if ($assignment !== null) {
                    return (bool) $assignment->is_active;
                }
            } else {
                $assignment = $this->supervisorAssignments()
                    ->where('academic_year', $academicYear)
                    ->first();
                if ($assignment !== null) {
                    return (bool) $assignment->is_active;
                }
            }

            // Cek penugasan peran kontekstual UserRoleAssignment untuk tahun ajaran terkait
            if ($this->relationLoaded('activeRoleAssignments')) {
                $hasContextual = $this->activeRoleAssignments->contains(function ($ra) use ($academicYear) {
                    return $ra->role?->slug === 'supervisor'
                        && (bool) $ra->status
                        && ($ra->academic_year === null || $ra->academic_year === $academicYear);
                });
                if ($hasContextual) {
                    return true;
                }
            } else {
                $hasContextual = $this->activeRoleAssignments()
                    ->whereHas('role', fn ($q) => $q->where('slug', 'supervisor'))
                    ->where(function ($q) use ($academicYear) {
                        $q->whereNull('academic_year')->orWhere('academic_year', $academicYear);
                    })
                    ->exists();
                if ($hasContextual) {
                    return true;
                }
            }

            // Fallback warisan: jika akun memiliki role utama 'supervisor' tapi belum ada record penugasan
            if ($this->role?->slug === 'supervisor' && ! $this->supervisorAssignments()->exists()) {
                return true;
            }

            return false;
        }

        // 3. Jika tahun ajaran tidak ditentukan (pemeriksaan keaktifan global)
        if ($this->relationLoaded('supervisorAssignments')) {
            $hasActive = $this->supervisorAssignments->contains(fn ($a) => (bool) $a->is_active);
            if ($hasActive) {
                return true;
            }
        } else {
            if ($this->supervisorAssignments()->where('is_active', true)->exists()) {
                return true;
            }
        }

        // Jika ada record penugasan tapi semuanya non-aktif, tolak hak akses
        if ($this->supervisorAssignments()->exists()) {
            return false;
        }

        return $this->hasRole('supervisor');
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
        if ($this->isStaff() || $this->isSystemAdmin()) {
            return false;
        }

        return strtolower($this->position ?? '') === 'guru'
            || $this->hasRole(['guru', 'operator_kurikulum'])
            || $this->hasTeachingDuty();
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

    public function isWakaKesiswaan(?string $academicYear = null): bool
    {
        if ($this->hasRole(['waka_kesiswaan', 'kesiswaan'])) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) use ($academicYear) {
                if (isset($pos->pivot->is_active) && ! $pos->pivot->is_active) {
                    return false;
                }
                if ($academicYear && isset($pos->pivot->academic_year) && $pos->pivot->academic_year !== $academicYear) {
                    return false;
                }
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return $code === 'WAKA_KESISWAAN' || (str_contains($name, 'waka') && str_contains($name, 'kesiswaan'));
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $query = $this->structuralPositions()
                ->wherePivot('is_active', true)
                ->where(function ($q) {
                    $q->where('code', 'WAKA_KESISWAAN')
                        ->orWhere(function ($sq) {
                            $sq->where('name', 'like', '%waka%')
                                ->where('name', 'like', '%kesiswaan%');
                        });
                });

            if ($academicYear) {
                $query->wherePivot('academic_year', $academicYear);
            }

            if ($query->exists()) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'waka kesiswaan') || str_contains($pos, 'waka_kesiswaan');
    }

    public function isCounselingBK(?string $academicYear = null): bool
    {
        if ($this->hasRole(['bk', 'guru_bk', 'bimbingan_konseling'])) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) use ($academicYear) {
                if (isset($pos->pivot->is_active) && ! $pos->pivot->is_active) {
                    return false;
                }
                if ($academicYear && isset($pos->pivot->academic_year) && $pos->pivot->academic_year !== $academicYear) {
                    return false;
                }
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return $code === 'BK' || str_contains($name, 'bk') || str_contains($name, 'konseling') || str_contains($name, 'bimbingan');
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $query = $this->structuralPositions()
                ->wherePivot('is_active', true)
                ->where(function ($q) {
                    $q->where('code', 'BK')
                        ->orWhere('name', 'like', '%bk%')
                        ->orWhere('name', 'like', '%konseling%')
                        ->orWhere('name', 'like', '%bimbingan%');
                });

            if ($academicYear) {
                $query->wherePivot('academic_year', $academicYear);
            }

            if ($query->exists()) {
                return true;
            }
        }

        if ($this->relationLoaded('subjectAssignments')) {
            $hasBkSubject = $this->subjectAssignments->contains(function ($assign) use ($academicYear) {
                if ($academicYear && isset($assign->academic_year) && $assign->academic_year !== $academicYear) {
                    return false;
                }
                $code = strtoupper($assign->subject?->code ?? '');
                $name = strtolower($assign->subject?->name ?? '');

                return $code === 'BK' || str_contains($name, 'bimbingan') || str_contains($name, 'konseling');
            });
            if ($hasBkSubject) {
                return true;
            }
        } else {
            $query = $this->subjectAssignments()
                ->whereHas('subject', function ($q) {
                    $q->where('code', 'BK')
                        ->orWhere('name', 'like', '%bimbingan%')
                        ->orWhere('name', 'like', '%konseling%');
                });

            if ($academicYear) {
                $query->where('academic_year', $academicYear);
            }

            if ($query->exists()) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'bk') || str_contains($pos, 'konseling') || str_contains($pos, 'bimbingan');
    }

    public function isDisciplineTeam(?string $academicYear = null): bool
    {
        // 1. Explicit roles / contextual assignments (NEVER automatically includes BK or general kesiswaan)
        if ($this->hasRole(['tim_kedisiplinan', 'kedisiplinan', 'tatib'])) {
            return true;
        }

        // 2. Explicit active structural position for Discipline / Tatib (NEVER includes BK)
        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) use ($academicYear) {
                if (isset($pos->pivot->is_active) && ! $pos->pivot->is_active) {
                    return false;
                }
                if ($academicYear && isset($pos->pivot->academic_year) && $pos->pivot->academic_year !== $academicYear) {
                    return false;
                }
                $name = strtolower($pos->name ?? '');
                $code = strtoupper($pos->code ?? '');

                return in_array($code, ['TATIB', 'DISIPLIN', 'TIM_DISIPLIN', 'TIM_KEDISIPLINAN'], true)
                    || str_contains($name, 'kedisiplinan')
                    || str_contains($name, 'tatib')
                    || str_contains($name, 'tata tertib');
            });
            if ($hasPos) {
                return true;
            }
        } else {
            $query = $this->structuralPositions()
                ->wherePivot('is_active', true)
                ->where(function ($q) {
                    $q->whereIn('code', ['TATIB', 'DISIPLIN', 'TIM_DISIPLIN', 'TIM_KEDISIPLINAN'])
                        ->orWhere('name', 'like', '%kedisiplinan%')
                        ->orWhere('name', 'like', '%tatib%')
                        ->orWhere('name', 'like', '%tata tertib%');
                });

            if ($academicYear) {
                $query->wherePivot('academic_year', $academicYear);
            }

            if ($query->exists()) {
                return true;
            }
        }

        // 3. Explicit position string (ONLY discipline / tatib keywords, NEVER bk / konseling / bimbingan)
        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'kedisiplinan') || str_contains($pos, 'tatib') || str_contains($pos, 'tata tertib') || str_contains($pos, 'tim disiplin');
    }

    /**
     * Determine if user has authority to access counseling notes and sessions.
     * Strictly restricted to Counseling Teachers (Guru BK) and Super Admin.
     * Discipline team members who are NOT Guru BK must NOT have access.
     */
    public function canAccessCounselingNotes(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isCounselingBK($academicYear);
    }

    /**
     * Determine if user has authority to manage student discipline matters.
     * Waka Kesiswaan (coordination), explicit Discipline Team members, and student affairs operators.
     * Guru BK do NOT automatically have discipline management access unless explicitly assigned.
     */
    public function canManageDiscipline(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineTeam($academicYear)
            || $this->hasPermission('operate_student_affairs');
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

        return $pos === 'staf'
            || str_contains($pos, 'administrasi')
            || str_contains($pos, 'satpam')
            || str_contains($pos, 'penjaga')
            || str_contains($pos, 'toolman')
            || str_contains($pos, 'pesuruh')
            || str_contains($pos, 'kebun')
            || str_contains($pos, 'tata usaha');
    }

    public function isStaff(): bool
    {
        if ($this->isSystemAdmin()) {
            return false;
        }

        return strtolower($this->position ?? '') === 'staf';
    }

    /**
     * Determine if the user is a system administrator.
     */
    public function isSystemAdmin(): bool
    {
        return $this->isSuperAdmin()
            || strtolower($this->position ?? '') === 'administrator'
            || $this->username === 'superadmin'
            || str_contains(strtolower($this->name ?? ''), 'administrator sistem');
    }

    /**
     * Get the active structural position title, or fallback to user group position.
     */
    public function getStructuralTitleAttribute(): string
    {
        if ($this->relationLoaded('structuralPositions') && $this->structuralPositions->isNotEmpty()) {
            $active = $this->structuralPositions->first(fn ($p) => $p->pivot->is_active ?? true);
            if ($active) {
                return $active->name;
            }
        }

        $activeStructural = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->first();

        if ($activeStructural) {
            return $activeStructural->name;
        }

        return match (strtolower($this->position ?? '')) {
            'administrator' => 'Administrator',
            'staf' => 'Staf / Tendik',
            'guru' => 'Guru',
            default => $this->position ? ucwords($this->position) : '-',
        };
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
                    ->orWhere(function ($sub) {
                        $sub->where('position', '!=', 'administrator')
                            ->where('position', 'not like', '%Administrator%');
                    });
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
            || $this->isPiketOfficerToday()
            || $this->isWakaKesiswaan()
            || $this->isDisciplineTeam()
            || $this->isCounselingBK();
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

    public function isHomeroomTeacher(?Classroom $classroom = null): bool
    {
        if ($classroom !== null) {
            if (! (bool) $classroom->is_active) {
                return false;
            }

            return (int) $classroom->homeroom_teacher_id === (int) $this->id;
        }

        if ($this->relationLoaded('homeroomClassrooms')) {
            $hasActiveHomeroom = $this->homeroomClassrooms->contains(fn ($c) => (bool) $c->is_active);
        } else {
            $hasActiveHomeroom = $this->homeroomClassrooms()->where('is_active', true)->exists();
        }

        return $hasActiveHomeroom;
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
        return Supervision::sanitizeNip($this->nip) ?: ($this->nigs ?: '-');
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
