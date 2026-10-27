<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_sn',
        'pin',
        'user_type',
        'user_id',
        'student_id',
        'name',
        'scanned_at',
        'status',
        'verify_type',
        'work_code',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_sn', 'device_sn');
    }

    public function getVerifyTypeLabelAttribute(): string
    {
        return match ($this->verify_type) {
            'face' => 'Wajah',
            'card' => 'Kartu RFID',
            'password' => 'PIN / Password',
            'geotag' => 'Geotagging GPS',
            'telegram' => 'Bot Telegram',
            default => 'Sidik Jari',
        };
    }

    public function getVerifyIconAttribute(): string
    {
        return match ($this->verify_type) {
            'face' => 'ti ti-mood-smile',
            'card' => 'ti ti-id',
            'password' => 'ti ti-key',
            'geotag' => 'ti ti-map-pin',
            'telegram' => 'ti ti-brand-telegram',
            default => 'ti ti-fingerprint',
        };
    }

    /**
     * @return array{label: string, class: string, icon: string}
     */
    public function getChannelBadgeAttribute(): array
    {
        if ($this->verify_type === 'geotag' || $this->device_sn === 'MOBILE-PWA') {
            return [
                'label' => 'GPS Mobile',
                'class' => 'bg-primary-lt text-primary',
                'icon' => 'ti ti-map-pin',
            ];
        }

        if ($this->verify_type === 'telegram' || $this->device_sn === 'TELEGRAM') {
            return [
                'label' => 'Bot Telegram',
                'class' => 'bg-info-lt text-info',
                'icon' => 'ti ti-brand-telegram',
            ];
        }

        return [
            'label' => 'Mesin Absensi',
            'class' => 'bg-purple-lt text-purple',
            'icon' => 'ti ti-device-watch',
        ];
    }

    public function getIsCheckInAttribute(): bool
    {
        if (isset($this->determined_status)) {
            return $this->determined_status === 'check_in';
        }

        if ($this->status === 'check_in') {
            return true;
        }

        if ($this->scanned_at && $this->scanned_at->format('H:i') < '12:00') {
            return true;
        }

        return false;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_check_in ? 'Masuk' : 'Pulang';
    }
}
