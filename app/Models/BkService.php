<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BkService extends Model
{
    use HasFactory;

    protected $fillable = [
        'piket_incident_id',
        'student_id',
        'academic_year',
        'service_date',
        'service_type',
        'summary',
        'result',
        'follow_up_plan',
        'follow_up_assignee_id',
        'follow_up_target_date',
        'follow_up_status',
        'follow_up_evaluation_notes',
        'follow_up_evaluated_at',
        'counselor_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'follow_up_target_date' => 'date',
        'follow_up_evaluated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function followUpAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_assignee_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PiketIncident::class, 'piket_incident_id');
    }

    public function confidentialNote(): HasOne
    {
        return $this->hasOne(BkConfidentialNote::class, 'bk_service_id');
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereNull('academic_year');
        });
    }

    public function getHasFollowUpAttribute(): bool
    {
        return ! empty(trim((string) $this->follow_up_plan));
    }

    public function getFollowUpStatusLabelAttribute(): string
    {
        return match ($this->follow_up_status) {
            'selesai' => 'Selesai / Tuntas',
            'sedang_berjalan' => 'Sedang Berjalan',
            'dibatalkan' => 'Dibatalkan',
            default => 'Belum Dilaksanakan',
        };
    }

    public function getFollowUpStatusBadgeClassAttribute(): string
    {
        return match ($this->follow_up_status) {
            'selesai' => 'bg-success-lt text-success',
            'sedang_berjalan' => 'bg-info-lt text-info',
            'dibatalkan' => 'bg-secondary-lt text-secondary',
            default => 'bg-warning-lt text-warning',
        };
    }

    public function getServiceTypeLabelAttribute(): string
    {
        return match ($this->service_type) {
            'pribadi' => 'Bimbingan Pribadi',
            'sosial' => 'Bimbingan Sosial',
            'belajar' => 'Bimbingan Belajar',
            'karier' => 'Bimbingan Karier',
            default => ucfirst($this->service_type),
        };
    }

    public function getServiceTypeBadgeClassAttribute(): string
    {
        return match ($this->service_type) {
            'pribadi' => 'bg-purple-lt text-purple',
            'sosial' => 'bg-azure-lt text-azure',
            'belajar' => 'bg-info-lt text-info',
            'karier' => 'bg-teal-lt text-teal',
            default => 'bg-secondary-lt text-secondary',
        };
    }
}
