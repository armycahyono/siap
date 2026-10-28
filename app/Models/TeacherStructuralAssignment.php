<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherStructuralAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'teacher_id',
        'structural_position_id',
        'academic_year',
        'sk_number',
        'sk_date',
        'valid_from',
        'valid_until',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'sk_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Scope penugasan yang aktif untuk tahun ajaran tertentu.
     * Menggunakan tanggal awal dan akhir tahun ajaran (academic_years) untuk memeriksa
     * apakah masa berlaku penugasan beririsan dengan periode yang dipilih secara murni berbasis tanggal,
     * tanpa perbandingan string academic_year.
     */
    public function scopeActiveForAcademicYear($query, string $academicYear)
    {
        $ayModel = AcademicYear::where('name', $academicYear)->first();

        // Tentukan batas tanggal awal dan akhir tahun ajaran yang diperiksa
        $targetStartDate = $ayModel?->start_date
            ? ($ayModel->start_date instanceof \DateTimeInterface ? $ayModel->start_date->format('Y-m-d') : substr((string) $ayModel->start_date, 0, 10))
            : (str_contains($academicYear, '/') ? explode('/', $academicYear)[0].'-07-01' : null);

        $targetEndDate = $ayModel?->end_date
            ? ($ayModel->end_date instanceof \DateTimeInterface ? $ayModel->end_date->format('Y-m-d') : substr((string) $ayModel->end_date, 0, 10))
            : (str_contains($academicYear, '/') ? (explode('/', $academicYear)[0] + 1).'-06-30' : null);

        return $query->where('is_active', true)
            ->where(function ($q) use ($academicYear, $targetStartDate, $targetEndDate) {
                // Kasus 1: Penugasan terdaftar pada tahun ajaran yang sama
                $q->where(function ($sameYear) use ($academicYear, $targetStartDate) {
                    $sameYear->where('academic_year', $academicYear);
                    // Jika memiliki tanggal akhir definitif, pastikan belum kedaluwarsa sebelum tahun ajaran mulai
                    if ($targetStartDate) {
                        $sameYear->where(function ($sub) use ($targetStartDate) {
                            $sub->whereNull('valid_until')
                                ->orWhere('valid_until', '>=', $targetStartDate);
                        });
                    }
                });

                // Kasus 2: Penugasan lintas tahun ajaran yang memiliki valid_until definitif dan beririsan dengan rentang tanggal
                // (Catatan: Penugasan tanpa valid_until TIDAK otomatis aktif lintas tahun ajaran)
                if ($targetStartDate && $targetEndDate) {
                    $q->orWhere(function ($multiYear) use ($academicYear, $targetStartDate, $targetEndDate) {
                        $multiYear->where('academic_year', '!=', $academicYear)
                            ->whereNotNull('valid_until')
                            ->where('valid_until', '>=', $targetStartDate)
                            ->where(function ($subFrom) use ($targetEndDate) {
                                $subFrom->whereNotNull('valid_from')->where('valid_from', '<=', $targetEndDate)
                                    ->orWhere(function ($fallbackDate) use ($targetEndDate) {
                                        $fallbackDate->whereNull('valid_from')
                                            ->whereNotNull('sk_date')
                                            ->where('sk_date', '<=', $targetEndDate);
                                    });
                            });
                    });
                }
            });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function structuralPosition(): BelongsTo
    {
        return $this->belongsTo(StructuralPosition::class, 'structural_position_id');
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $positionName = $this->structuralPosition?->name ?? "Jabatan #{$this->structural_position_id}";

        return "Penugasan Struktural {$teacherName} ({$positionName})";
    }
}
