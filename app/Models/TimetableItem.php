<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableItem extends Model
{
    protected $fillable = [
        'timetable_upload_id',
        'class_name',
        'classroom_id',
        'day',
        'start_period',
        'end_period',
        'start_time',
        'end_time',
        'subject_code',
        'subject_id',
        'teacher_nickname',
        'teacher_id',
        'is_valid',
        'status_message',
    ];

    protected function casts(): array
    {
        return [
            'start_period' => 'integer',
            'end_period' => 'integer',
            'is_valid' => 'boolean',
        ];
    }

    public function timetableUpload()
    {
        return $this->belongsTo(TimetableUpload::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
