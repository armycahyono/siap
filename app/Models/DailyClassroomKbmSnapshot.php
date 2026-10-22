<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClassroomKbmSnapshot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_kbm_active' => 'boolean',
            'total_students' => 'integer',
            'present_students_count' => 'integer',
            'permit_students_count' => 'integer',
            'late_students_count' => 'integer',
            'early_leave_students_count' => 'integer',
            'alfa_students_count' => 'integer',
            'attendance_percentage' => 'float',
            'scheduled_sessions_count' => 'integer',
            'completed_sessions_count' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function exemptionEvent(): BelongsTo
    {
        return $this->belongsTo(CurriculumEvent::class, 'exemption_event_id');
    }
}
