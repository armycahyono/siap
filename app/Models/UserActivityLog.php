<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_username',
        'role_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActionBadgeClassAttribute(): string
    {
        return match (strtolower($this->action)) {
            'login' => 'bg-success text-white',
            'logout' => 'bg-secondary text-white',
            'switch_year', 'reset_year' => 'bg-cyan text-white',
            'create', 'store' => 'bg-primary text-white',
            'import' => 'bg-teal text-white',
            'update', 'edit' => 'bg-info text-white',
            'delete', 'destroy', 'force_logout' => 'bg-danger text-white',
            'approve' => 'bg-teal text-white',
            'reject' => 'bg-warning text-dark',
            'submit' => 'bg-purple text-white',
            'upload' => 'bg-azure text-white',
            'toggle_status' => 'bg-indigo text-white',
            default => 'bg-dark text-white',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match (strtolower($this->action)) {
            'login' => 'Masuk (Login)',
            'logout' => 'Keluar (Logout)',
            'switch_year' => 'Ganti Tahun',
            'reset_year' => 'Reset Tahun',
            'create', 'store' => 'Tambah Data',
            'import' => 'Impor Data',
            'update', 'edit' => 'Ubah Data',
            'delete', 'destroy' => 'Hapus Data',
            'force_logout' => 'Putus Sesi',
            'approve' => 'Pengesahan',
            'reject' => 'Penolakan / Revisi',
            'submit' => 'Pengajuan Supervisi',
            'upload' => 'Unggah Foto/Berkas',
            'toggle_status' => 'Ubah Status',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
