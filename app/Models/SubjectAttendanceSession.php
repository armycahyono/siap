<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectAttendanceSession extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'lesson_schedule_id',
        'classroom_id',
        'subject_id',
        'teacher_id',
        'scheduled_teacher_id',
        'attendance_date',
        'start_period',
        'end_period',
        'start_time',
        'end_time',
        'academic_year',
        'semester',
        'is_swap',
        'swap_type',
        'swap_notes',
        'topic',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'start_period' => 'integer',
            'end_period' => 'integer',
            'is_swap' => 'boolean',
        ];
    }

    public function lessonSchedule(): BelongsTo
    {
        return $this->belongsTo(LessonSchedule::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scheduledTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_teacher_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(SubjectAttendanceRecord::class);
    }

    public function getHadirCountAttribute(): int
    {
        return $this->records->where('status', 'h')->count();
    }

    public function getSakitCountAttribute(): int
    {
        return $this->records->where('status', 's')->count();
    }

    public function getIzinCountAttribute(): int
    {
        return $this->records->where('status', 'i')->count();
    }

    public function getAlpaCountAttribute(): int
    {
        return $this->records->where('status', 'a')->count();
    }

    public function getTerlambatCountAttribute(): int
    {
        return $this->records->where('status', 't')->count();
    }

    public function getDispenCountAttribute(): int
    {
        return $this->records->where('status', 'd')->count();
    }
}
