<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVisitation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'visit_type',
        'source_type',
        'source_id',
        'reason',
        'requested_by',
        'primary_officer_id',
        'secondary_officer_id',
        'planned_date',
        'executed_date',
        'status',
        'result_summary',
        'follow_up_plan',
        'confidential_notes',
        'attachment_path',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'executed_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYearRelation(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function primaryOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_officer_id');
    }

    public function secondaryOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_officer_id');
    }

    public function bkService(): BelongsTo
    {
        return $this->belongsTo(BkService::class, 'bk_service_id');
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereNull('academic_year');
        });
    }

    public function getVisitTypeLabelAttribute(): string
    {
        return match ($this->visit_type) {
            'kunjungan_rumah' => 'Kunjungan Rumah (Home Visit)',
            'pemantauan_khusus' => 'Pemantauan Kondisi / Khusus',
            'tindak_lanjut_absensi' => 'Tindak Lanjut Kehadiran / Presensi',
            'komunikasi_ortu' => 'Komunikasi Orang Tua / Wali',
            'tindak_lanjut_kasus' => 'Tindak Lanjut Kasus / Kejadian',
            'lainnya' => 'Lainnya',
            default => ucfirst(str_replace('_', ' ', $this->visit_type)),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'diajukan' => 'Diajukan',
            'dijadwalkan' => 'Dijadwalkan',
            'terlaksana' => 'Terlaksana',
            'selesai' => 'Selesai & Ditutup',
            'dibatalkan' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'terlaksana' => 'bg-info-lt text-info',
            'selesai' => 'bg-success-lt text-success',
            'dibatalkan' => 'bg-secondary-lt text-secondary',
            'dijadwalkan' => 'bg-primary-lt text-primary',
            default => 'bg-warning-lt text-warning',
        };
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return match ($this->source_type) {
            'inisiatif_wali_kelas' => 'Inisiatif Wali Kelas',
            'rujukan_bk' => 'Rujukan / Layanan BK',
            'kasus_kedisiplinan' => 'Kasus / Kejadian Kedisiplinan',
            'masalah_kehadiran' => 'Presensi / Masalah Kehadiran',
            'mandiri' => 'Pengajuan Mandiri',
            default => ucfirst(str_replace('_', ' ', $this->source_type ?? '-')),
        };
    }

    public function canUserView(?User $user, ?string $academicYear = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isPrincipal()) {
            return true;
        }

        if ($user->id === $this->requested_by || $user->id === $this->primary_officer_id || $user->id === $this->secondary_officer_id) {
            return true;
        }

        $ay = $academicYear ?? $this->academic_year;

        if ($user->canOperateBk($ay)) {
            return true;
        }

        if ($user->canManageDiscipline($ay)) {
            return true;
        }

        $classroom = $this->student?->classroomForAcademicYear($ay) ?? $this->student?->classroom;
        if ($classroom && $user->isHomeroomTeacher($classroom)) {
            return true;
        }

        return false;
    }

    public function canUserAccessConfidential(?User $user, ?string $academicYear = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Assigned officers and requester have direct operational need
        if ($user->id === $this->requested_by || $user->id === $this->primary_officer_id || $user->id === $this->secondary_officer_id) {
            return true;
        }

        // Counseling teachers have professional counseling confidentiality access
        $ay = $academicYear ?? $this->academic_year;
        if ($user->canAccessCounselingNotes($ay)) {
            return true;
        }

        return false;
    }

    public function canUserEdit(?User $user, ?string $academicYear = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $this->requested_by || $user->id === $this->primary_officer_id || $user->id === $this->secondary_officer_id) {
            return true;
        }

        $ay = $academicYear ?? $this->academic_year;

        return $user->canManageDiscipline($ay) || $user->canOperateBk($ay);
    }

    public function getActivityLogTitle(): string
    {
        return "Visitasi Siswa: {$this->student?->name} ({$this->visit_type_label})";
    }
}
