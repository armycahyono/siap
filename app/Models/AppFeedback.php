<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppFeedback extends Model
{
    use HasFactory;

    protected $table = 'app_feedback';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'description',
        'priority',
        'attachment_path',
        'attachment_original_name',
        'status',
        'admin_notes',
        'responded_by_id',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'saran_fitur' => 'Saran Fitur Baru',
            'perbaikan_tampilan' => 'Perbaikan Tampilan / UI',
            'laporan_bug' => 'Laporan Kendala / Bug',
            'konten_data' => 'Perbaikan Data / Format',
            default => 'Masukan Lainnya',
        };
    }

    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) {
            'saran_fitur' => 'blue',
            'perbaikan_tampilan' => 'purple',
            'laporan_bug' => 'danger',
            'konten_data' => 'teal',
            default => 'secondary',
        };
    }

    public function getCategoryIconAttribute(): string
    {
        return match ($this->category) {
            'saran_fitur' => 'bulb',
            'perbaikan_tampilan' => 'brush',
            'laporan_bug' => 'bug',
            'konten_data' => 'database',
            default => 'message-dots',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'menunggu' => 'Menunggu Ditinjau',
            'diproses' => 'Sedang Diproses',
            'selesai' => 'Selesai Diterapkan',
            'ditolak' => 'Belum Dapat Diterapkan',
            default => 'Menunggu',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'menunggu' => 'warning',
            'diproses' => 'info',
            'selesai' => 'success',
            'ditolak' => 'danger',
            default => 'secondary',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'tinggi' => 'Prioritas Tinggi',
            'sedang' => 'Prioritas Sedang',
            'rendah' => 'Prioritas Rendah',
            default => 'Sedang',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'tinggi' => 'danger',
            'sedang' => 'warning',
            'rendah' => 'secondary',
            default => 'secondary',
        };
    }
}
