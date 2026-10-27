<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervision_id',
        'file_path',
        'caption',
        'category',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'date',
        ];
    }

    public function supervision(): BelongsTo
    {
        return $this->belongsTo(Supervision::class);
    }

    public function getUrlAttribute(): string
    {
        return route('supervisions.view-photo', $this);
    }
}
