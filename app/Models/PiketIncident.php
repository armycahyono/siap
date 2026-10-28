<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PiketIncident extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'piket_journal_id',
        'academic_year',
        'incident_date',
        'incident_time',
        'student_id',
        'student_name',
        'classroom_id',
        'location',
        'category',
        'description',
        'action_taken',
        'follow_up_target',
        'confiscated_items',
        'evidence_photo',
        'recorded_by',
        'status',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'disciplinary_sanction',
        'parental_involvement',
        'parental_involvement_notes',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'verified_at' => 'datetime',
        'parental_involvement' => 'boolean',
    ];

    protected $appends = [
        'evidence_photo_url',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PiketJournal::class, 'piket_journal_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function bkReferral(): HasOne
    {
        return $this->hasOne(BkReferral::class, 'piket_incident_id');
    }

    public function bkServices(): HasMany
    {
        return $this->hasMany(BkService::class, 'piket_incident_id');
    }

    /**
     * Scope to filter incidents by academic year (either directly or via journal).
     */
    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('journal', fn ($jq) => $jq->where('academic_year', $academicYear));
        });
    }

    public function getBkFollowUpStatusAttribute(): string
    {
        return $this->bkReferral?->status ?? 'menunggu';
    }

    public function getDisciplineActionAttribute(): ?string
    {
        return $this->disciplinary_sanction;
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'kedisiplinan' => 'Kedisiplinan',
            'seragam' => 'Pelanggaran Seragam / Kerapian',
            'gadget_terlarang' => 'HP / Gadget Terlarang',
            'perkelahian' => 'Pertikaian / Perkelahian',
            'rokok' => 'Rokok / Vape',
            'kesehatan_pingsan' => 'Kesehatan / Pingsan',
            'lainnya' => 'Lain-lain',
            default => ucfirst($this->category),
        };
    }

    public function getFollowUpLabelAttribute(): string
    {
        return match ($this->follow_up_target) {
            'selesai_di_piket' => 'Selesai di Piket',
            'rujuk_wali_kelas' => 'Rujuk ke Wali Kelas',
            'rujuk_bk' => 'Rujuk ke Guru BK',
            'rujuk_kesiswaan' => 'Rujuk ke Tim Kesiswaan',
            default => ucfirst(str_replace('_', ' ', $this->follow_up_target)),
        };
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'terverifikasi';
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->isVerified();
    }

    public function getVerificationStatusLabelAttribute(): string
    {
        return match ($this->verification_status) {
            'terverifikasi' => 'Pelanggaran Terverifikasi',
            'bukan_pelanggaran' => 'Bukan Pelanggaran / Selesai Tanpa Sanksi',
            'dibatalkan' => 'Dibatalkan',
            default => 'Menunggu Verifikasi Kedisiplinan',
        };
    }

    public function getVerificationBadgeClassAttribute(): string
    {
        return match ($this->verification_status) {
            'terverifikasi' => 'bg-danger text-white',
            'bukan_pelanggaran' => 'bg-success-lt text-success',
            'dibatalkan' => 'bg-secondary-lt text-secondary',
            default => 'bg-warning-lt text-warning',
        };
    }

    public function getEvidencePhotoUrlAttribute(): ?string
    {
        return $this->evidence_photo ? route('piket.incidents.photo', $this) : null;
    }

    public function getActivityLogTitle(): string
    {
        return "Kejadian/Kasus: {$this->student_name} ({$this->category_label})";
    }
}
