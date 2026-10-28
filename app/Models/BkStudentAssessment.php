<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkStudentAssessment extends Model
{
    use HasFactory;

    protected $table = 'bk_student_assessments';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'assessment_date',
        'assessment_name',
        'assessor_name',
        'result_summary',
        'recommended_career_paths',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'assessment_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYearRelation(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
