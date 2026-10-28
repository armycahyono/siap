<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkStudentDocument extends Model
{
    use HasFactory;

    protected $table = 'bk_student_documents';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'document_category',
        'title',
        'document_date',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'file_size' => 'integer',
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

    public function isSensitive(): bool
    {
        return in_array($this->document_category, [
            'hasil_psikotes',
            'surat_komitmen',
            'surat_perjanjian',
            'berkas_kesehatan',
        ], true);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->document_category) {
            'sertifikat_prestasi' => 'Sertifikat / Piagam Prestasi',
            'hasil_psikotes' => 'Hasil Psikotes / Tes Bakat & IQ',
            'surat_komitmen' => 'Surat Komitmen & Pernyataan Siswa/Ortu',
            'surat_perjanjian' => 'Surat Perjanjian Pembinaan',
            'berkas_kesehatan' => 'Catatan / Berkas Medis Khusus',
            'administrasi_bk' => 'Administrasi Layanan BK',
            default => 'Dokumen Lainnya',
        };
    }

    public function getCategoryBadgeClassAttribute(): string
    {
        return match ($this->document_category) {
            'sertifikat_prestasi' => 'bg-warning text-dark',
            'hasil_psikotes' => 'bg-purple text-white',
            'surat_komitmen' => 'bg-azure text-white',
            'surat_perjanjian' => 'bg-danger text-white',
            'berkas_kesehatan' => 'bg-pink text-white',
            'administrasi_bk' => 'bg-teal text-white',
            default => 'bg-secondary text-white',
        };
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if (! $bytes) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
