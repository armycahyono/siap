<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketDutySwap extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'original_schedule_id',
        'requester_id',
        'replacement_teacher_id',
        'duty_date',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'duty_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function originalSchedule(): BelongsTo
    {
        return $this->belongsTo(PiketSchedule::class, 'original_schedule_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function replacementTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_teacher_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivityLogTitle(): string
    {
        return 'Tukar Piket: '.($this->requester?->name ?? 'Pemohon').' -> '.($this->replacementTeacher?->name ?? 'Pengganti');
    }
}
