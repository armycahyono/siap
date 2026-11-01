<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'target_type',
        'target_roles',
        'target_departments',
        'send_telegram',
        'telegram_sent_at',
        'telegram_sent_count',
        'is_pinned',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'target_departments' => 'array',
        'send_telegram' => 'boolean',
        'telegram_sent_count' => 'integer',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'telegram_sent_at' => 'datetime',
    ];

    /**
     * Relasi ke pembuat pengumuman (Author / Pimpinan).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Riwayat pembacaan pengumuman.
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Scope pengumuman yang aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pengumuman yang relevan dan boleh diakses oleh user tertentu.
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->where('target_type', 'all');
        }

        // Pimpinan / Superadmin dapat melihat seluruh pengumuman di sistem
        if ($user->canPublishAnnouncement()) {
            return $query;
        }

        $userRoles = $user->allRoles()->pluck('slug')->toArray();
        if ($user->role && ! in_array($user->role->slug, $userRoles)) {
            $userRoles[] = $user->role->slug;
        }

        $departmentIds = [];
        if (Schema::hasTable('departments')) {
            $departmentIds = Department::where('head_of_department_id', $user->id)->pluck('id')->toArray();
        }

        return $query->where(function (Builder $q) use ($user, $userRoles, $departmentIds) {
            $q->where('target_type', 'all')
                ->orWhere('author_id', $user->id)
                ->orWhere(function (Builder $sub) use ($userRoles) {
                    $sub->where('target_type', 'role')
                        ->where(function (Builder $roleSub) use ($userRoles) {
                            foreach ($userRoles as $roleSlug) {
                                $roleSub->orWhereJsonContains('target_roles', $roleSlug);
                            }
                        });
                })
                ->when(! empty($departmentIds), function (Builder $sub) use ($departmentIds) {
                    $sub->orWhere(function (Builder $dSub) use ($departmentIds) {
                        $dSub->where('target_type', 'department')
                            ->where(function (Builder $deptSub) use ($departmentIds) {
                                foreach ($departmentIds as $depId) {
                                    $deptSub->orWhereJsonContains('target_departments', (string) $depId)
                                        ->orWhereJsonContains('target_departments', (int) $depId);
                                }
                            });
                    });
                });
        });
    }

    /**
     * Cek apakah pengumuman telah dibaca oleh user tertentu.
     */
    public function isReadBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('reads')) {
            return $this->reads->contains('user_id', $user->id);
        }

        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Tandai pengumuman sudah dibaca oleh user tertentu.
     */
    public function markAsRead(?User $user): void
    {
        if (! $user) {
            return;
        }

        AnnouncementRead::firstOrCreate(
            [
                'announcement_id' => $this->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );
    }

    /**
     * Teks deskriptif sasaran / target pengumuman.
     */
    public function getTargetLabelAttribute(): string
    {
        if ($this->target_type === 'all') {
            return 'Semua Warga GTK';
        }

        if ($this->target_type === 'role') {
            $roles = $this->target_roles ?? [];
            if (empty($roles)) {
                return 'Semua Warga GTK';
            }

            $names = Role::whereIn('slug', $roles)->pluck('name')->toArray();

            return ! empty($names) ? implode(', ', $names) : implode(', ', $roles);
        }

        if ($this->target_type === 'department') {
            $deptIds = $this->target_departments ?? [];
            if (empty($deptIds)) {
                return 'Semua Jurusan';
            }

            $names = Department::whereIn('id', $deptIds)->pluck('name')->toArray();

            return ! empty($names) ? implode(', ', $names) : 'Jurusan Tertentu';
        }

        return 'Umum';
    }

    /**
     * Dapatkan teks pengumuman yang diformat rapi untuk disalin atau dikirim ke WhatsApp.
     */
    public function getWhatsAppShareText(): string
    {
        $schoolSetting = SchoolSetting::getSetting();
        $schoolName = strtoupper($schoolSetting->school_name ?? 'SMK PGRI KRAS');
        $authorName = $this->author ? $this->author->name : 'Pimpinan Sekolah';
        $authorRole = $this->author ? ($this->author->role?->name ?? 'Pimpinan') : 'Pimpinan';
        $waktu = $this->published_at ? $this->published_at->translatedFormat('l, d F Y H:i') : now()->translatedFormat('l, d F Y H:i');
        $target = $this->target_label;
        $content = strip_tags($this->content);

        return "📢 *PENGUMUMAN RESMI SEKOLAH*\n"
            ."*{$schoolName}*\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📌 *Judul:* {$this->title}\n"
            ."👤 *Pengirim:* {$authorName} ({$authorRole})\n"
            ."🎯 *Sasaran:* {$target}\n"
            ."⏰ *Waktu:* {$waktu} WIB\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."{$content}\n\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'_Sistem Informasi Administrasi dan Pendidikan (SIAP)_';
    }

    /**
     * Link WhatsApp Web / Mobile siap pakai.
     */
    public function getWhatsAppShareUrlAttribute(): string
    {
        return 'https://api.whatsapp.com/send?text='.rawurlencode($this->getWhatsAppShareText());
    }

    /**
     * Teks ringkasan untuk log aktivitas.
     */
    public function getActivityLogTitle(): string
    {
        return "Pengumuman: {$this->title}";
    }
}
