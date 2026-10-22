<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupervisionIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'phase_id',
        'code',
        'title',
        'description',
        'max_score',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(SupervisionPhase::class, 'phase_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SupervisionScore::class, 'indicator_id');
    }
}
