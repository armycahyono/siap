<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherStructuralAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'teacher_id',
        'structural_position_id',
        'academic_year',
        'sk_number',
        'sk_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'sk_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function structuralPosition(): BelongsTo
    {
        return $this->belongsTo(StructuralPosition::class, 'structural_position_id');
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $positionName = $this->structuralPosition?->name ?? "Jabatan #{$this->structural_position_id}";

        return "Penugasan Struktural {$teacherName} ({$positionName})";
    }
}
