<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'device_sn',
        'ip_address',
        'port',
        'server_port',
        'comm_key',
        'status',
        'last_activity_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'is_active' => 'boolean',
            'port' => 'integer',
            'server_port' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_sn', 'device_sn');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(AttendanceDeviceCommand::class, 'device_sn', 'device_sn');
    }
}
