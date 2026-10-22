<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDeviceBiometric extends Model
{
    protected $fillable = [
        'device_sn',
        'pin',
        'bio_type',
        'finger_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
