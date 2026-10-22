<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyScheduleTarget extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_period' => 'integer',
            'end_period' => 'integer',
            'is_exempted' => 'boolean',
            'is_completed' => 'boolean',
        ];
    }

    public function lessonSchedule(): BelongsTo
    {
        return $this->belongsTo(LessonSchedule::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function exemptionEvent(): BelongsTo
    {
        return $this->belongsTo(CurriculumEvent::class, 'exemption_event_id');
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(SubjectAttendanceSession::class, 'attendance_session_id');
    }
}
