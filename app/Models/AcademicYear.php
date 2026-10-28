<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
     * Set this instance as active and deactivate all others atomically.
     */
    public function setAsActive(): void
    {
        DB::transaction(function () {
            // Lock shared coordination row (SchoolSetting) first to serialize concurrent activations
            $setting = SchoolSetting::lockForUpdate()->first();

            // Lock this record to prevent concurrent modifications
            $target = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($target->is_locked) {
                throw new \RuntimeException("Tahun Pelajaran {$target->name} sedang dikunci dan tidak dapat diaktifkan.");
            }

            // Deactivate all other academic years
            self::where('id', '!=', $target->id)->update(['is_active' => false]);

            // Activate target record
            $target->update(['is_active' => true]);
            $this->is_active = true;

            // Sync with SchoolSetting
            if ($setting) {
                $setting->update([
                    'academic_year' => $target->name,
                    'semester' => $target->semester ?? 'Ganjil',
                ]);
            }
        });
    }

    public function gtkDecrees(): HasMany
    {
        return $this->hasMany(GtkDecree::class);
    }
}
