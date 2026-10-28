<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkStudentDevelopmentNote extends Model
{
    use HasFactory;

    protected $table = 'bk_student_development_notes';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'note_date',
        'category',
        'title',
        'description',
        'follow_up_recommendation',
        'recorded_by',
    ];

    protected $casts = [
        'note_date' => 'date',
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

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'sikap_belajar' => 'Sikap & Kebiasaan Belajar',
            'kedisiplinan_positif' => 'Kedisiplinan & Ketertiban',
            'kemandirian' => 'Kemandirian & Tanggung Jawab',
            'interaksi_sosial' => 'Interaksi Sosial & Pertemanan',
            'tanggung_jawab' => 'Partisipasi & Kepedulian',
            'rekomendasi_pembinaan' => 'Rekomendasi Tindak Lanjut',
            default => 'Lainnya',
        };
    }

    public function getCategoryBadgeClassAttribute(): string
    {
        return match ($this->category) {
            'sikap_belajar' => 'bg-blue text-white',
            'kedisiplinan_positif' => 'bg-green text-white',
            'kemandirian' => 'bg-teal text-white',
            'interaksi_sosial' => 'bg-info text-white',
            'tanggung_jawab' => 'bg-indigo text-white',
            'rekomendasi_pembinaan' => 'bg-warning text-dark',
            default => 'bg-secondary text-white',
        };
    }
}
