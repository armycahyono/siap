<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'piket_incident_id',
        'status',
        'notes',
        'handled_by',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PiketIncident::class, 'piket_incident_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->whereHas('incident', function ($q) use ($academicYear) {
            $q->where(function ($sub) use ($academicYear) {
                $sub->where('academic_year', $academicYear)
                    ->orWhereNull('academic_year');
            });
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'diproses' => 'Sedang Diproses',
            'selesai' => 'Selesai Ditindaklanjuti',
            default => 'Menunggu Tindak Lanjut',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'diproses' => 'bg-primary-lt text-primary',
            'selesai' => 'bg-success-lt text-success',
            default => 'bg-warning-lt text-warning',
        };
    }
}
