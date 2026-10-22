<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceBiometric extends Model
{
    use HasFactory;

    protected $fillable = [
        'pin',
        'bio_type',
        'finger_id',
        'size',
        'valid',
        'template',
        'source_device_sn',
    ];

    /**
     * Get human-friendly label for finger index / biometric type
     */
    public function getFingerLabelAttribute(): string
    {
        if ($this->bio_type === 'face') {
            return 'Wajah (Face)';
        }

        return match ($this->finger_id) {
            0 => 'Jempol Kiri',
            1 => 'Telunjuk Kiri',
            2 => 'Jari Tengah Kiri',
            3 => 'Jari Manis Kiri',
            4 => 'Kelingking Kiri',
            5 => 'Jempol Kanan',
            6 => 'Telunjuk Kanan',
            7 => 'Jari Tengah Kanan',
            8 => 'Jari Manis Kanan',
            9 => 'Kelingking Kanan',
            default => 'Jari #'.$this->finger_id,
        };
    }
}
