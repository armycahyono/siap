<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketInvalClass extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'piket_journal_id',
        'classroom_id',
        'absent_teacher_id',
        'subject_id',
        'inval_teacher_id',
        'period_start',
        'period_end',
        'reason',
        'assignment_material',
        'assignment_file_path',
        'assignment_file_name',
        'notes',
    ];

    protected $appends = [
        'assignment_file_url',
    ];

    public function getAssignmentFileUrlAttribute(): ?string
    {
        if (! $this->assignment_file_path) {
            return null;
        }

        return route('piket.invals.assignment', $this);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PiketJournal::class, 'piket_journal_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function invalTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inval_teacher_id');
    }

    public function getActivityLogTitle(): string
    {
        return 'Guru Inval: Kelas '.($this->classroom?->name ?? '-').' ('.($this->absentTeacher?->name ?? '-').' -> '.($this->invalTeacher?->name ?? 'Belum ada').')';
    }
}
