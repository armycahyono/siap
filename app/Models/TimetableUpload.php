<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year',
        'semester',
        'title',
        'original_filename',
        'file_path',
        'file_size',
        'uploaded_by',
        'notes',
        'total_classes',
        'total_lessons',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'file_size' => 'integer',
            'total_classes' => 'integer',
            'total_lessons' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function lessonSchedules(): HasMany
    {
        return $this->hasMany(LessonSchedule::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TimetableItem::class);
    }
}
