<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectAttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_attendance_session_id',
        'student_id',
        'status',
        'notes',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SubjectAttendanceSession::class, 'subject_attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'h' => 'Hadir',
            't' => 'Terlambat',
            's' => 'Sakit',
            'i' => 'Izin',
            'a' => 'Alpa',
            'd' => 'Dispen',
            default => 'Hadir',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'h' => 'bg-success text-white',
            't' => 'bg-warning text-dark border border-warning',
            's' => 'bg-warning text-dark',
            'i' => 'bg-info text-white',
            'a' => 'bg-danger text-white',
            'd' => 'bg-purple text-white',
            default => 'bg-secondary text-white',
        };
    }
}
