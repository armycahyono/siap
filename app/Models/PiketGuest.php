<?php

namespace App\Models;

use App\Support\PhoneNumberHelper;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketGuest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'piket_journal_id',
        'visit_date',
        'check_in_time',
        'check_out_time',
        'guest_name',
        'institution',
        'phone',
        'purpose',
        'meet_with',
        'badge_number',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    protected $appends = [
        'whatsapp_url',
    ];

    public function getWhatsappNumberAttribute(): ?string
    {
        return PhoneNumberHelper::formatForWhatsapp($this->phone);
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return PhoneNumberHelper::toWhatsappUrl($this->phone);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PiketJournal::class, 'piket_journal_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivityLogTitle(): string
    {
        return "Buku Tamu: {$this->guest_name} (".($this->institution ?: 'Tamu Umum').')';
    }
}
