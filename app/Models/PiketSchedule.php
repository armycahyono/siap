<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiketSchedule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'academic_year',
        'semester',
        'day',
        'teacher_id',
        'role',
        'duty_location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function dutySwaps(): HasMany
    {
        return $this->hasMany(PiketDutySwap::class, 'original_schedule_id');
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'koordinator';
    }

    public function getActivityLogTitle(): string
    {
        return "Jadwal Piket: {$this->day} - ".($this->teacher?->name ?? 'Guru')." ({$this->role})";
    }
}
