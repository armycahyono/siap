<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StructuralPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'order',
        'description',
        'signature_image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class)->where('is_active', true);
    }

    public function currentAssignment(?string $academicYear = null): HasOne
    {
        $query = $this->hasOne(TeacherStructuralAssignment::class)->where('is_active', true);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->latest('id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_structural_assignments', 'structural_position_id', 'teacher_id')
            ->withPivot(['academic_year', 'sk_number', 'sk_date', 'notes', 'is_active'])
            ->withTimestamps();
    }
}
