<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyKbmRoster extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_expected_checkin' => 'boolean',
            'is_weekend' => 'boolean',
            'is_holiday' => 'boolean',
            'active_schedules_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exemptionEvent(): BelongsTo
    {
        return $this->belongsTo(CurriculumEvent::class, 'exemption_event_id');
    }
}
