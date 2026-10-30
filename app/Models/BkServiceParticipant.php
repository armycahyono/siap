<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkServiceParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'bk_service_id',
        'student_id',
        'attendance_status',
        'personal_notes',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(BkService::class, 'bk_service_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
