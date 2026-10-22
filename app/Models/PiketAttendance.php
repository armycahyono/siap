<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'piket_journal_id',
        'teacher_id',
        'status',
        'check_in_time',
        'check_out_time',
        'notes',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PiketJournal::class, 'piket_journal_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
