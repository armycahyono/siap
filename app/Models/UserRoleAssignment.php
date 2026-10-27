<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserRoleAssignment extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
        'department_id',
        'academic_year_id',
        'academic_year',
        'context_type',
        'context_id',
        'valid_from',
        'valid_until',
        'status',
        'sk_number',
        'notes',
        'assigned_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function context(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'context_type', 'context_id');
    }

    /**
     * Scope for active assignments.
     */
    public function scopeActive(Builder $query, ?string $academicYear = null): Builder
    {
        $today = now()->toDateString();

        $query->where('status', true)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            });

        if (! empty($academicYear)) {
            $query->where(function (Builder $q) use ($academicYear) {
                $q->where('academic_year', $academicYear)
                    ->orWhereNull('academic_year');
            });
        }

        return $query;
    }

    /**
     * Scope for a specific role slug or list of slugs.
     */
    public function scopeForRole(Builder $query, string|array $roleSlugs): Builder
    {
        $slugs = is_array($roleSlugs) ? $roleSlugs : [$roleSlugs];

        return $query->whereHas('role', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    /**
     * Human-readable label for context.
     */
    public function getContextLabelAttribute(): ?string
    {
        if (empty($this->context_type) || empty($this->context_id)) {
            return null;
        }

        if ($this->context_type === Classroom::class || str_ends_with($this->context_type, 'Classroom')) {
            return $this->context?->name ? "Kelas {$this->context->name}" : "Rombel #{$this->context_id}";
        }

        if ($this->context_type === Department::class || str_ends_with($this->context_type, 'Department')) {
            return $this->context?->name ? "Jurusan {$this->context->name}" : "Jurusan #{$this->context_id}";
        }

        return class_basename($this->context_type)." #{$this->context_id}";
    }
}
