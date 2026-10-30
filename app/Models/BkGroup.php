<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'academic_year',
        'counselor_id',
        'description',
        'target_goal',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'bk_group_students')
            ->withPivot(['joined_at', 'notes'])
            ->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(BkService::class, 'bk_group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'karir' => 'Bimbingan Karir & Peminatan',
            'akademik' => 'Bimbingan Belajar & Akademik',
            'sosial_pribadi' => 'Sosial & Kepribadian',
            'kedisiplinan' => 'Kedisiplinan & Sikap',
            default => ucfirst(str_replace('_', ' ', (string) $this->category)),
        };
    }

    public function getCategoryBadgeColorAttribute(): string
    {
        return match ($this->category) {
            'karir' => 'teal',
            'akademik' => 'primary',
            'sosial_pribadi' => 'purple',
            'kedisiplinan' => 'orange',
            default => 'secondary',
        };
    }
}
