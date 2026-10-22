<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClassroom extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'student_classrooms';

    protected $fillable = [
        'student_id',
        'classroom_id',
        'academic_year_id',
        'status',
        'is_repeating',
        'academic_year_name',
        'classroom_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_repeating' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function getActivityLogTitle(): string
    {
        return 'Riwayat Rombel Siswa: '.($this->student?->name ?? 'Siswa').' di '.($this->classroom?->name ?? '-').' (TP. '.($this->academicYear?->name ?? '-').')';
    }
}
