<?php

namespace App\Models;

use App\Support\PhoneNumberHelper;
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

        // 2. Check primary role if no specific context is requested or if context is an academic year
        $isAcademicYearContext = is_string($context) && (str_contains($context, '/') || preg_match('/^\d{4}$/', $context));
        if (($context === null || $isAcademicYearContext) && in_array($this->role?->slug, $targetSlugs, true)) {
            return true;
        }

        // 3. Check contextual role assignments
        if ($this->relationLoaded('activeRoleAssignments')) {
            return $this->activeRoleAssignments->contains(function ($assignment) use ($targetSlugs, $context, $isAcademicYearContext) {
                if (! $assignment->role || ! in_array($assignment->role->slug, $targetSlugs, true)) {
                    return false;
                }
                if ($context !== null) {
                    if ($isAcademicYearContext) {
                        return $assignment->academic_year === null || $assignment->academic_year === $context;
                    }
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
            ->when($context !== null, function ($q) use ($context, $isAcademicYearContext) {
                if ($isAcademicYearContext) {
                    $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $context));
                } else {
                    $ctxType = is_object($context) ? get_class($context) : null;
                    $ctxId = is_object($context) ? ($context->id ?? null) : $context;
                    $q->where(function ($sub) use ($ctxType, $ctxId) {
                        $sub->where(function ($c) use ($ctxType, $ctxId) {
                            $c->where('context_type', $ctxType)->where('context_id', $ctxId);
                        })->orWhere(function ($c) {
                            $c->whereNull('context_type')->whereNull('context_id');
                        });
                    });
                }
            })
            ->exists();
    }

    public function hasPermission(string $permissionSlug, mixed $context = null): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $isAcademicYearContext = is_string($context) && (str_contains($context, '/') || preg_match('/^\d{4}$/', $context));

        // Check primary role if no context is requested or context is academic year
        if (($context === null || $isAcademicYearContext) && ($this->role?->hasPermission($permissionSlug) ?? false)) {
            return true;
        }

        // Check active contextual role assignments
        if ($this->relationLoaded('activeRoleAssignments')) {
            return $this->activeRoleAssignments->contains(function ($assignment) use ($permissionSlug, $context, $isAcademicYearContext) {
                if (! $assignment->role?->hasPermission($permissionSlug)) {
                    return false;
                }
                if ($context !== null) {
                    if ($isAcademicYearContext) {
                        return $assignment->academic_year === null || $assignment->academic_year === $context;
                    }
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
            ->when($context !== null, function ($q) use ($context, $isAcademicYearContext) {
                if ($isAcademicYearContext) {
                    $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $context));
                } else {
                    $ctxType = is_object($context) ? get_class($context) : null;
                    $ctxId = is_object($context) ? ($context->id ?? null) : $context;
                    $q->where(function ($sub) use ($ctxType, $ctxId) {
                        $sub->where(function ($c) use ($ctxType, $ctxId) {
                            $c->where('context_type', $ctxType)->where('context_id', $ctxId);
                        })->orWhere(function ($c) {
                            $c->whereNull('context_type')->whereNull('context_id');
                        });
                    });
                }
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

    public function isWakaKurikulum(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'waka_kurikulum'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'kurikulum')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($p->pivot ?? $p, $academicYear)) {
                    return false;
                }
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return str_contains($name, 'kurikulum') || str_contains($code, 'KURIKULUM');
            });
        }

        $query = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%kurikulum%')
                    ->orWhere('code', 'like', '%KURIKULUM%');
            });

        return $this->applyStructuralYearScope($query, $academicYear)->exists();
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

    public function isPrincipal(?string $academicYear = null): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        if ($this->hasRole('kepala_sekolah', $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'kepala sekolah') || str_contains($pos, 'principal')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($p->pivot ?? $p, $academicYear)) {
                    return false;
                }
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return in_array($code, ['KS', 'KEPSEK']) || str_contains($name, 'kepala sekolah');
            });
        }

        $query = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['KS', 'KEPSEK'])->orWhere('name', 'like', '%kepala sekolah%');
            });

        return $this->applyStructuralYearScope($query, $academicYear)->exists();
    }

    public function isHeadmaster(?string $academicYear = null): bool
    {
        return $this->isPrincipal($academicYear);
    }

    public function isWakaSarpras(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'waka_sarpras'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'waka') && str_contains($pos, 'sarpras')) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($p->pivot ?? $p, $academicYear)) {
                    return false;
                }
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return $code === 'WAKA_SARPRAS' || (str_contains($name, 'waka') && str_contains($name, 'sarpras'));
            });
        }

        $query = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'WAKA_SARPRAS')
                    ->orWhere(function ($sq) {
                        $sq->where('name', 'like', '%waka%')->where('name', 'like', '%sarpras%');
                    });
            });

        return $this->applyStructuralYearScope($query, $academicYear)->exists();
    }

    public function isWakaHumas(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'waka_humas'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'waka') && (str_contains($pos, 'humas') || str_contains($pos, 'hubin'))) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($p->pivot ?? $p, $academicYear)) {
                    return false;
                }
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return $code === 'WAKA_HUMAS' || (str_contains($name, 'waka') && (str_contains($name, 'humas') || str_contains($name, 'hubin')));
            });
        }

        $query = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'WAKA_HUMAS')
                    ->orWhere(function ($sq) {
                        $sq->where('name', 'like', '%waka%')
                            ->where(fn ($s) => $s->where('name', 'like', '%humas%')->orWhere('name', 'like', '%hubin%'));
                    });
            });

        return $this->applyStructuralYearScope($query, $academicYear)->exists();
    }

    public function isLibrarian(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'petugas_perpustakaan', 'pustakawan', 'perpustakaan'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'perpustakaan') || str_contains($pos, 'pustakawan');
    }

    public function isLaboranOrToolman(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'laboran_toolman', 'toolman', 'laboran'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'toolman') || str_contains($pos, 'laboran') || str_contains($pos, 'teknisi bengkel');
    }

    public function isTreasurer(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'bendahara', 'bendahara_sekolah'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'bendahara');
    }

    public function isHeadOfTu(?string $academicYear = null): bool
    {
        if ($this->hasRole(['super_admin', 'kepala_tu', 'ka_tu'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'kepala tata usaha') || str_contains($pos, 'kepala tu') || str_contains($pos, 'ka tu') || str_contains($pos, 'ka. tu');
    }

    public function hasMultipleAssignments(?string $academicYear = null): bool
    {
        $assignmentsCount = $this->activeRoleAssignments()
            ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
            ->count();

        if ($assignmentsCount > 1) {
            return true;
        }

        if ($assignmentsCount === 1) {
            if ($this->isHomeroomTeacher(academicYear: $academicYear) || $this->hasTeachingDuty()) {
                return true;
            }
        }

        return false;
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
        if ($this->hasRole(['waka_kesiswaan', 'kesiswaan'], $academicYear)) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($pos->pivot ?? $pos, $academicYear)) {
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

            if ($this->applyStructuralYearScope($query, $academicYear)->exists()) {
                return true;
            }
        }

        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'waka kesiswaan') || str_contains($pos, 'waka_kesiswaan');
    }

    public function isCounselingBK(?string $academicYear = null): bool
    {
        if ($academicYear === null) {
            try {
                $academicYear = app(AcademicYearContext::class)->getViewingYear();
            } catch (\Throwable) {
                $academicYear = AcademicYear::getActive()?->name ?? SchoolSetting::getMasterYear();
            }
        }

        if ($this->relationLoaded('activeRoleAssignments')) {
            $today = now()->toDateString();

            $hasAssignment = $this->activeRoleAssignments->contains(function ($ra) use ($academicYear, $today) {
                if (! in_array($ra->role?->slug, ['guru_bk', 'bk'], true)) {
                    return false;
                }
                if (! (bool) $ra->status) {
                    return false;
                }
                $validFrom = $ra->valid_from instanceof \DateTimeInterface ? $ra->valid_from->format('Y-m-d') : $ra->valid_from;
                if ($validFrom && $validFrom > $today) {
                    return false;
                }
                $validUntil = $ra->valid_until instanceof \DateTimeInterface ? $ra->valid_until->format('Y-m-d') : $ra->valid_until;
                if ($validUntil && $validUntil < $today) {
                    return false;
                }
                if ($academicYear !== null) {
                    if ($ra->academic_year === $academicYear) {
                        return true;
                    }
                    if ($ra->academicYear?->name === $academicYear) {
                        return true;
                    }

                    return false;
                }

                return true;
            });

            return $hasAssignment;
        }

        return $this->activeRoleAssignments()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['guru_bk', 'bk']))
            ->when($academicYear !== null, function ($q) use ($academicYear) {
                $q->where(function ($sub) use ($academicYear) {
                    $sub->where('academic_year', $academicYear)
                        ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
                });
            })
            ->exists();
    }

    public function isDisciplineTeam(?string $academicYear = null): bool
    {
        if ($academicYear === null) {
            try {
                $academicYear = app(AcademicYearContext::class)->getViewingYear();
            } catch (\Throwable) {
                $academicYear = AcademicYear::getActive()?->name ?? SchoolSetting::getMasterYear();
            }
        }

        // 1. Contextual role assignments scoped to academic year and active validity dates
        if ($this->relationLoaded('activeRoleAssignments')) {
            $today = now()->toDateString();

            $hasRoleAssignment = $this->activeRoleAssignments->contains(function ($ra) use ($academicYear, $today) {
                if (! in_array($ra->role?->slug, ['tim_kedisiplinan', 'kedisiplinan', 'tatib'], true)) {
                    return false;
                }
                if (! (bool) $ra->status) {
                    return false;
                }
                $validFrom = $ra->valid_from instanceof \DateTimeInterface ? $ra->valid_from->format('Y-m-d') : $ra->valid_from;
                if ($validFrom && $validFrom > $today) {
                    return false;
                }
                $validUntil = $ra->valid_until instanceof \DateTimeInterface ? $ra->valid_until->format('Y-m-d') : $ra->valid_until;
                if ($validUntil && $validUntil < $today) {
                    return false;
                }
                if ($academicYear !== null) {
                    if ($ra->academic_year === $academicYear) {
                        return true;
                    }
                    if ($ra->academicYear?->name === $academicYear) {
                        return true;
                    }

                    return false;
                }

                return true;
            });

            if ($hasRoleAssignment) {
                return true;
            }
        } else {
            $hasActiveAssignment = $this->activeRoleAssignments()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['tim_kedisiplinan', 'kedisiplinan', 'tatib']))
                ->when($academicYear !== null, function ($q) use ($academicYear) {
                    $q->where(function ($sub) use ($academicYear) {
                        $sub->where('academic_year', $academicYear)
                            ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
                    });
                })
                ->exists();

            if ($hasActiveAssignment) {
                return true;
            }
        }

        // 2. Direct primary role (NEVER automatically includes BK or general kesiswaan)
        if (in_array($this->role?->slug, ['tim_kedisiplinan', 'kedisiplinan', 'tatib'], true)) {
            return true;
        }

        // 3. Explicit active structural position for Discipline / Tatib (NEVER includes BK)
        if ($this->relationLoaded('structuralPositions')) {
            $hasPos = $this->structuralPositions->contains(function ($pos) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($pos->pivot ?? $pos, $academicYear)) {
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

            if ($this->applyStructuralYearScope($query, $academicYear)->exists()) {
                return true;
            }
        }

        // 4. Explicit position string (ONLY discipline / tatib keywords, NEVER bk / konseling / bimbingan)
        $pos = strtolower($this->position ?? '');

        return str_contains($pos, 'kedisiplinan') || str_contains($pos, 'tatib') || str_contains($pos, 'tata tertib') || str_contains($pos, 'tim disiplin');
    }

    /**
     * Determine if user is a Discipline Coordinator (or Waka Kesiswaan).
     */
    public function isDisciplineCoordinator(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isWakaKesiswaan($academicYear)) {
            return true;
        }

        if ($this->hasRole(['koordinator_kedisiplinan', 'koordinator_tatib'], $academicYear)) {
            return true;
        }

        $pos = strtolower($this->position ?? '');
        if (str_contains($pos, 'koordinator') && (str_contains($pos, 'disiplin') || str_contains($pos, 'kedisiplinan') || str_contains($pos, 'tatib'))) {
            return true;
        }

        if ($this->relationLoaded('structuralPositions')) {
            return $this->structuralPositions->contains(function ($p) use ($academicYear) {
                if (! $this->isStructuralPivotActiveForYear($p->pivot ?? $p, $academicYear)) {
                    return false;
                }
                $name = strtolower($p->name ?? '');
                $code = strtoupper($p->code ?? '');

                return in_array($code, ['KOORD_TATIB', 'KOORD_DISIPLIN'], true)
                    || (str_contains($name, 'koordinator') && (str_contains($name, 'kedisiplinan') || str_contains($name, 'tatib')));
            });
        }

        $query = $this->structuralPositions()
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['KOORD_TATIB', 'KOORD_DISIPLIN'])
                    ->orWhere(function ($sub) {
                        $sub->where('name', 'like', '%koordinator%')
                            ->where(function ($s) {
                                $s->where('name', 'like', '%kedisiplinan%')
                                    ->orWhere('name', 'like', '%tatib%');
                            });
                    });
            });

        return $this->applyStructuralYearScope($query, $academicYear)->exists();
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
     * Determine if user can operate BK module (dashboard, referrals, services).
     */
    public function canOperateBk(?string $academicYear = null): bool
    {
        return $this->isSuperAdmin() || $this->isPrincipal() || $this->isCounselingBK($academicYear);
    }

    /**
     * Determine if user has authority to manage student discipline matters.
     * Waka Kesiswaan (coordination), explicit Discipline Team members, and student affairs operators.
     * Guru BK do NOT automatically have discipline management access unless explicitly assigned.
     */
    public function canManageDiscipline(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin() || $this->isPrincipal()) {
            return true;
        }

        return $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineCoordinator($academicYear)
            || $this->isDisciplineTeam($academicYear)
            || $this->hasPermission('manage_discipline', $academicYear)
            || $this->hasPermission('operate_student_affairs', $academicYear);
    }

    /**
     * Determine if user has authority to access the visitation and student monitoring module.
     * Accessible by SuperAdmin, Principal, BK, Homeroom Teachers, Discipline Team / Waka Kesiswaan,
     * or staff with direct visitation assignments.
     */
    public function canAccessVisitations(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin() || $this->isPrincipal()) {
            return true;
        }

        return $this->canOperateBk($academicYear)
            || $this->canManageDiscipline($academicYear)
            || $this->isHomeroomTeacher(academicYear: $academicYear)
            || StudentVisitation::where(function ($q) {
                $q->where('requested_by', $this->id)
                    ->orWhere('primary_officer_id', $this->id)
                    ->orWhere('secondary_officer_id', $this->id);
            })
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->exists();
    }

    /**
     * Determine if user can propose or create a visitation for a given student.
     */
    public function canCreateVisitation(?Student $student = null, ?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin() || $this->isPrincipal()) {
            return true;
        }

        if ($this->canOperateBk($academicYear) || $this->canManageDiscipline($academicYear)) {
            return true;
        }

        if ($student) {
            $classroom = $student->classroomForAcademicYear($academicYear) ?? $student->classroom;

            return $this->isHomeroomTeacher($classroom);
        }

        return $this->isHomeroomTeacher();
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

        return $this->role?->slug === 'staf'
            || strtolower($this->position ?? '') === 'staf'
            || self::isStaffPosition($this->position);
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

    public function isPiketOfficerToday(?string $academicYear = null): bool
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

        if ($academicYear === null) {
            try {
                $academicYear = app(AcademicYearContext::class)->getViewingYear();
            } catch (\Throwable) {
                $academicYear = AcademicYear::getActive()?->name ?? SchoolSetting::getMasterYear();
            }
        }

        return PiketSchedule::where('teacher_id', $this->id)
            ->where('day', $today)
            ->where('is_active', true)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->exists();
    }

    public function canAccessPiket(?string $academicYear = null): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('access_piket', $academicYear)
            || $this->canManagePiketCases($academicYear)
            || $this->canOperatePiket($academicYear)
            || $this->isDisciplineTeam($academicYear);
    }

    public function canOperatePiket(?string $academicYear = null): bool
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('operate_piket', $academicYear)
            || $this->isPiketOfficerToday($academicYear);
    }

    public function canManagePiketCases(?string $academicYear = null): bool
    {
        return $this->isSuperAdmin()
            || $this->isPrincipal()
            || $this->hasPermission('manage_piket_cases', $academicYear)
            || $this->isPiketOfficerToday($academicYear)
            || $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineCoordinator($academicYear)
            || $this->isDisciplineTeam($academicYear);
    }

    public function canViewPiketCases(?string $academicYear = null): bool
    {
        return $this->canManagePiketCases($academicYear)
            || $this->isCounselingBK($academicYear)
            || $this->isHomeroomTeacher(academicYear: $academicYear);
    }

    public function canRecordPiketIncident(?string $academicYear = null): bool
    {
        return $this->isSuperAdmin()
            || $this->isPrincipal()
            || $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineCoordinator($academicYear)
            || $this->isDisciplineTeam($academicYear)
            || $this->isPiketOfficerToday($academicYear)
            || $this->hasPermission('operate_piket', $academicYear)
            || $this->hasPermission('manage_piket_cases', $academicYear);
    }

    public function canVerifyDiscipline(?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin() || $this->isPrincipal()) {
            return true;
        }

        return $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineCoordinator($academicYear)
            || $this->isDisciplineTeam($academicYear)
            || $this->hasPermission('manage_discipline', $academicYear);
    }

    public function canDeletePiketIncident(PiketIncident $incident, ?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // If the incident has already been verified as a discipline violation, only Waka Kesiswaan or Principal can delete it
        if ($incident->isVerified()) {
            return $this->isPrincipal() || $this->isWakaKesiswaan($academicYear);
        }

        // If it was referred to BK and has counseling sessions underway, prevent unauthorized deletion
        if ($incident->bkServices()->exists()) {
            return $this->isPrincipal() || $this->isWakaKesiswaan($academicYear);
        }

        // General piket deletion: Waka Kesiswaan, Principal, Discipline Coordinator, Piket Coordinator, or recorder while status is still 'tercatat'
        return $this->isPrincipal()
            || $this->isWakaKesiswaan($academicYear)
            || $this->isDisciplineCoordinator($academicYear)
            || $this->isPiketCoordinator()
            || ($incident->recorded_by === $this->id && $incident->status === 'tercatat');
    }

    public function canTransitionIncidentStatus(PiketIncident $incident, string $newStatus, ?string $academicYear = null): bool
    {
        if ($this->isSuperAdmin() || $this->isPrincipal()) {
            return true;
        }

        $validStatuses = ['tercatat', 'ditindaklanjuti', 'selesai'];
        if (! in_array($newStatus, $validStatuses, true)) {
            return false;
        }

        // Once completed ('selesai'), reopening to 'ditindaklanjuti' requires Discipline Team / Coordinator / Waka Kesiswaan
        if ($incident->status === 'selesai' && $newStatus !== 'selesai') {
            return $this->canManageDiscipline($academicYear);
        }

        return $this->canManagePiketCases($academicYear);
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
        return PhoneNumberHelper::formatForWhatsapp($this->phone);
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return PhoneNumberHelper::toWhatsappUrl($this->phone);
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

    public function isHomeroomTeacher(?Classroom $classroom = null, ?string $academicYear = null): bool
    {
        if ($classroom !== null) {
            if (! (bool) $classroom->is_active) {
                return false;
            }

            if ($academicYear !== null && ! empty($classroom->academic_year) && $classroom->academic_year !== $academicYear) {
                return false;
            }

            if ((int) $classroom->homeroom_teacher_id === (int) $this->id) {
                return true;
            }

            return $this->hasRole('wali_kelas', $classroom);
        }

        if ($this->relationLoaded('homeroomClassrooms')) {
            $hasActiveHomeroom = $this->homeroomClassrooms->contains(function ($c) use ($academicYear) {
                if (! (bool) $c->is_active) {
                    return false;
                }
                if ($academicYear !== null && ! empty($c->academic_year) && $c->academic_year !== $academicYear) {
                    return false;
                }

                return true;
            });
        } else {
            $hasActiveHomeroom = $this->homeroomClassrooms()
                ->where('is_active', true)
                ->when($academicYear !== null, function ($q) use ($academicYear) {
                    $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $academicYear));
                })
                ->exists();
        }

        if ($hasActiveHomeroom) {
            return true;
        }

        return $this->hasRole('wali_kelas', $academicYear);
    }

    public function lessonSchedules(): HasMany
    {
        return $this->hasMany(LessonSchedule::class, 'teacher_id');
    }

    public function structuralAssignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class, 'teacher_id');
    }

    /**
     * Memeriksa apakah pivot penugasan struktural aktif untuk tahun ajaran tertentu,
     * mendukung penugasan multi-tahun berdasarkan rentang tanggal definitif yang beririsan.
     */
    protected function isStructuralPivotActiveForYear(mixed $pivot, ?string $academicYear): bool
    {
        if (isset($pivot->is_active) && ! $pivot->is_active) {
            return false;
        }

        if ($academicYear && isset($pivot->academic_year)) {
            $ayModel = AcademicYear::where('name', $academicYear)->first();
            $targetStartDate = $ayModel?->start_date
                ? ($ayModel->start_date instanceof \DateTimeInterface ? $ayModel->start_date->format('Y-m-d') : substr((string) $ayModel->start_date, 0, 10))
                : (str_contains($academicYear, '/') ? explode('/', $academicYear)[0].'-07-01' : null);

            $targetEndDate = $ayModel?->end_date
                ? ($ayModel->end_date instanceof \DateTimeInterface ? $ayModel->end_date->format('Y-m-d') : substr((string) $ayModel->end_date, 0, 10))
                : (str_contains($academicYear, '/') ? (explode('/', $academicYear)[0] + 1).'-06-30' : null);

            $validUntil = ! empty($pivot->valid_until)
                ? ($pivot->valid_until instanceof \DateTimeInterface ? $pivot->valid_until->format('Y-m-d') : substr((string) $pivot->valid_until, 0, 10))
                : null;

            $validFrom = ! empty($pivot->valid_from)
                ? ($pivot->valid_from instanceof \DateTimeInterface ? $pivot->valid_from->format('Y-m-d') : substr((string) $pivot->valid_from, 0, 10))
                : (! empty($pivot->sk_date)
                    ? ($pivot->sk_date instanceof \DateTimeInterface ? $pivot->sk_date->format('Y-m-d') : substr((string) $pivot->sk_date, 0, 10))
                    : null);

            if ($pivot->academic_year === $academicYear) {
                if ($targetStartDate && $validUntil && $validUntil < $targetStartDate) {
                    return false;
                }

                return true;
            }

            // Lintas tahun ajaran: harus memiliki valid_until definitif dan beririsan dengan rentang tanggal tahun ajaran
            if (! $validUntil || ! $targetStartDate || ! $targetEndDate) {
                return false;
            }

            if ($validUntil < $targetStartDate) {
                return false;
            }

            if ($validFrom && $validFrom > $targetEndDate) {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Menambahkan scope tahun ajaran pada relasi structuralPositions query builder
     * dengan pemeriksaan tanggal awal dan akhir tahun ajaran (beririsan murni berbasis tanggal).
     */
    protected function applyStructuralYearScope($query, ?string $academicYear)
    {
        if (! $academicYear) {
            return $query;
        }

        $ayModel = AcademicYear::where('name', $academicYear)->first();
        $targetStartDate = $ayModel?->start_date
            ? ($ayModel->start_date instanceof \DateTimeInterface ? $ayModel->start_date->format('Y-m-d') : substr((string) $ayModel->start_date, 0, 10))
            : (str_contains($academicYear, '/') ? explode('/', $academicYear)[0].'-07-01' : null);

        $targetEndDate = $ayModel?->end_date
            ? ($ayModel->end_date instanceof \DateTimeInterface ? $ayModel->end_date->format('Y-m-d') : substr((string) $ayModel->end_date, 0, 10))
            : (str_contains($academicYear, '/') ? (explode('/', $academicYear)[0] + 1).'-06-30' : null);

        return $query->where(function ($sq) use ($academicYear, $targetStartDate, $targetEndDate) {
            $sq->where(function ($sameYear) use ($academicYear, $targetStartDate) {
                $sameYear->where('teacher_structural_assignments.academic_year', $academicYear);
                if ($targetStartDate) {
                    $sameYear->where(function ($sub) use ($targetStartDate) {
                        $sub->whereNull('teacher_structural_assignments.valid_until')
                            ->orWhere('teacher_structural_assignments.valid_until', '>=', $targetStartDate);
                    });
                }
            });

            if ($targetStartDate && $targetEndDate) {
                $sq->orWhere(function ($multiYear) use ($academicYear, $targetStartDate, $targetEndDate) {
                    $multiYear->where('teacher_structural_assignments.academic_year', '!=', $academicYear)
                        ->whereNotNull('teacher_structural_assignments.valid_until')
                        ->where('teacher_structural_assignments.valid_until', '>=', $targetStartDate)
                        ->where(function ($subFrom) use ($targetEndDate) {
                            $subFrom->whereNotNull('teacher_structural_assignments.valid_from')->where('teacher_structural_assignments.valid_from', '<=', $targetEndDate)
                                ->orWhere(function ($fallbackDate) use ($targetEndDate) {
                                    $fallbackDate->whereNull('teacher_structural_assignments.valid_from')
                                        ->whereNotNull('teacher_structural_assignments.sk_date')
                                        ->where('teacher_structural_assignments.sk_date', '<=', $targetEndDate);
                                });
                        });
                });
            }
        });
    }

    public function structuralPositions(): BelongsToMany
    {
        return $this->belongsToMany(StructuralPosition::class, 'teacher_structural_assignments', 'teacher_id', 'structural_position_id')
            ->withPivot(['academic_year', 'sk_number', 'sk_date', 'valid_from', 'valid_until', 'notes', 'is_active'])
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

    public function gtkDecreeRecipients(): HasMany
    {
        return $this->hasMany(GtkDecreeRecipient::class, 'user_id');
    }

    public function gtkDecrees(): BelongsToMany
    {
        return $this->belongsToMany(GtkDecree::class, 'gtk_decree_recipients', 'user_id', 'gtk_decree_id')
            ->withTimestamps();
    }

    public function gtkDecreeDetails(): HasMany
    {
        return $this->hasMany(GtkDecreeDetail::class, 'user_id');
    }

    public function getActivityLogTitle(): string
    {
        return "Pengguna/Guru '{$this->name}' ({$this->username})";
    }
}
