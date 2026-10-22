<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicYearRolloverLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'source_academic_year',
        'target_academic_year',
        'executed_by',
        'summary',
        'student_snapshots',
        'is_rolled_back',
        'rolled_back_at',
        'rolled_back_by',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'student_snapshots' => 'array',
            'is_rolled_back' => 'boolean',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function rollbackExecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
