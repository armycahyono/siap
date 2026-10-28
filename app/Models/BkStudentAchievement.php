<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkStudentAchievement extends Model
{
    use HasFactory;

    protected $table = 'bk_student_achievements';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'event_date',
        'type',
        'category',
        'level',
        'title',
        'achievement_result',
        'organizer',
        'certificate_number',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYearRelation(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'prestasi' => 'Prestasi / Lomba',
            'kegiatan' => 'Kegiatan / Organisasi',
            default => ucfirst((string) $this->type),
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'akademik' => 'Akademik',
            'non_akademik' => 'Non-Akademik',
            'seni' => 'Seni & Budaya',
            'olahraga' => 'Olahraga',
            'keagamaan' => 'Keagamaan',
            'organisasi' => 'Organisasi (OSIS/MPK)',
            'ekskul' => 'Ekstrakurikuler',
            default => 'Lainnya',
        };
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'sekolah' => 'Tingkat Sekolah',
            'kecamatan' => 'Tingkat Kecamatan',
            'kabupaten' => 'Tingkat Kota / Kabupaten',
            'provinsi' => 'Tingkat Provinsi',
            'nasional' => 'Tingkat Nasional',
            'internasional' => 'Tingkat Internasional',
            default => ucfirst((string) $this->level),
        };
    }

    public function getLevelBadgeClassAttribute(): string
    {
        return match ($this->level) {
            'internasional' => 'bg-purple text-white',
            'nasional' => 'bg-danger text-white',
            'provinsi' => 'bg-warning text-dark',
            'kabupaten' => 'bg-primary text-white',
            'kecamatan' => 'bg-teal text-white',
            default => 'bg-secondary text-white',
        };
    }
}
