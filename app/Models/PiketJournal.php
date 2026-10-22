<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiketJournal extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'academic_year',
        'semester',
        'coordinator_id',
        'general_notes',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(PiketAttendance::class);
    }

    public function invalClasses(): HasMany
    {
        return $this->hasMany(PiketInvalClass::class);
    }

    public function studentPermits(): HasMany
    {
        return $this->hasMany(PiketStudentPermit::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(PiketIncident::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(PiketGuest::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'selesai';
    }

    public function getActivityLogTitle(): string
    {
        return 'Jurnal Piket Tanggal: '.$this->date?->format('d/m/Y');
    }
}
