<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkConfidentialNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'bk_service_id',
        'notes',
        'created_by',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(BkService::class, 'bk_service_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
