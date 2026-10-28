<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GtkDecreeRecipient extends Model
{
    use HasFactory, LogsActivity;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'gtk_decree_id',
        'user_id',
        'notes',
    ];

    /**
     * Relasi ke Dokumen Induk SK GTK.
     */
    public function decree(): BelongsTo
    {
        return $this->belongsTo(GtkDecree::class, 'gtk_decree_id');
    }

    /**
     * Relasi ke Personil GTK (User).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke butir rincian penugasan yang ditugaskan kepada penerima ini dalam SK.
     */
    public function details(): HasMany
    {
        return $this->hasMany(GtkDecreeDetail::class, 'gtk_decree_recipient_id');
    }

    public function getActivityLogTitle(): string
    {
        $userName = $this->user?->name ?? "User #{$this->user_id}";
        $skNumber = $this->decree?->number ?? "SK #{$this->gtk_decree_id}";

        return "Penerima SK {$skNumber}: {$userName}";
    }
}
