<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'semester',
        'start_date',
        'end_date',
        'is_active',
        'is_locked',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Get the active academic year instance.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Set this instance as active and deactivate all others.
     */
    public function setAsActive(): void
    {
        self::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);

        // Sync with SchoolSetting default
        $setting = SchoolSetting::first();
        if ($setting) {
            $setting->update([
                'academic_year' => $this->name,
                'semester' => $this->semester ?? 'Ganjil',
            ]);
        }
    }
}
