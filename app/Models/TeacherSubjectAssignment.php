<?php

namespace App\Models;

use App\Services\BkAssignmentSyncService;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubjectAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'classroom_id',
        'academic_year',
        'semester',
        'hours_per_week',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hours_per_week' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (TeacherSubjectAssignment $assignment) {
            app(BkAssignmentSyncService::class)->syncOnSubjectAssignmentSaved($assignment);
        });

        static::deleted(function (TeacherSubjectAssignment $assignment) {
            app(BkAssignmentSyncService::class)->syncOnSubjectAssignmentDeleted($assignment);
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $subjectName = $this->subject?->name ?? "Mapel #{$this->subject_id}";
        $className = $this->classroom?->name ?? ($this->classroom_id ? "Kelas #{$this->classroom_id}" : 'Semua Kelas');

        return "Penugasan Mapel {$teacherName} ({$subjectName} - {$className})";
    }
}
