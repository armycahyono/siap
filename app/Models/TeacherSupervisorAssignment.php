<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSupervisorAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'teacher_id',
        'academic_year',
        'academic_year_id',
        'position_title',
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $title = $this->position_title ?: 'Supervisor';

        return "Penugasan Tim Supervisor {$teacherName} ({$title}) - TP {$this->academic_year}";
    }
}
