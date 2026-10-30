<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'category',
        'kkm',
        'department_id',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kkm' => 'integer',
            'is_active' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_subject_assignments', 'subject_id', 'teacher_id')
            ->withPivot(['classroom_id', 'academic_year', 'semester', 'hours_per_week', 'notes'])
            ->distinct();
    }

    /**
     * Cek apakah mata pelajaran ini merupakan Bimbingan dan Konseling (BK).
     */
    public function isBk(): bool
    {
        return strcasecmp((string) $this->code, 'BK') === 0
            || str_contains(strtolower((string) $this->category), 'bimbingan')
            || str_contains(strtolower((string) $this->name), 'bimbingan')
            || str_contains(strtolower((string) $this->name), 'konseling');
    }

    /**
     * Scope query untuk mata pelajaran BK.
     */
    public function scopeBk($query)
    {
        return $query->where(function ($q) {
            $q->where('code', 'BK')
                ->orWhere('category', 'like', '%bimbingan%')
                ->orWhere('name', 'like', '%bimbingan%')
                ->orWhere('name', 'like', '%konseling%');
        });
    }

    public function getActivityLogTitle(): string
    {
        return "Mata Pelajaran '{$this->name}' ({$this->code})";
    }
}
