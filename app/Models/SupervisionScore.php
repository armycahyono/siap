<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervision_id',
        'indicator_id',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function supervision(): BelongsTo
    {
        return $this->belongsTo(Supervision::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(SupervisionIndicator::class, 'indicator_id');
    }
}
