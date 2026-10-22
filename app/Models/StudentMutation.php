<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StudentMutation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'student_id',
        'classroom_id',
        'type',
        'mutation_date',
        'reason_category',
        'reason_description',
        'target_school',
        'letter_number',
        'attachment_path',
        'attachment_name',
        'recorded_by',
    ];

    protected $casts = [
        'mutation_date' => 'date',
    ];

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

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'mutasi' => 'Mutasi Keluar (Pindah Sekolah)',
            'keluar' => 'Mengundurkan Diri',
            'dikeluarkan' => 'Dikeluarkan (Pelanggaran/Disiplin)',
            'drop_out' => 'Berhenti / Drop Out',
            default => ucfirst($this->type),
        };
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->type) {
            'mutasi' => 'bg-warning-lt text-dark',
            'keluar' => 'bg-secondary-lt text-dark',
            'dikeluarkan' => 'bg-danger-lt text-danger',
            'drop_out' => 'bg-danger text-white',
            default => 'bg-light text-dark',
        };
    }

    public function getReasonCategoryLabelAttribute(): string
    {
        return match ($this->reason_category) {
            'pindah_domisili' => 'Pindah Domisili / Ikut Orang Tua',
            'ekonomi_keluarga' => 'Faktor Ekonomi / Biaya',
            'pelanggaran_disiplin' => 'Pelanggaran Tata Tertib / Masalah Disiplin',
            'mengundurkan_diri' => 'Permintaan Orang Tua / Siswa',
            'bekerja' => 'Bekerja / Mencari Nafkah',
            'kesehatan' => 'Kesehatan / Sakit Menahun',
            'menikah' => 'Menikah',
            default => 'Lainnya / Alasan Khusus',
        };
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->attachment_path && Storage::disk('public')->exists($this->attachment_path)) {
            return Storage::disk('public')->url($this->attachment_path);
        }

        return null;
    }
}
