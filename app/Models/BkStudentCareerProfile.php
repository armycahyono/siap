<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkStudentCareerProfile extends Model
{
    use HasFactory;

    protected $table = 'bk_student_career_profiles';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'academic_year',
        'hobbies',
        'talents',
        'skills_to_develop',
        'favorite_subjects',
        'career_aspirations',
        'post_graduation_plan',
        'target_industry',
        'target_job_position',
        'target_university',
        'target_study_program',
        'entrepreneurship_type',
        'business_plan_summary',
        'notes',
        'recorded_by',
        'updated_by',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getPostGraduationPlanLabelAttribute(): string
    {
        return match ($this->post_graduation_plan) {
            'bekerja' => 'Bekerja',
            'melanjutkan' => 'Melanjutkan Pendidikan',
            'wirausaha' => 'Berwirausaha',
            default => 'Belum Memutuskan',
        };
    }

    public function getPostGraduationPlanBadgeAttribute(): string
    {
        return match ($this->post_graduation_plan) {
            'bekerja' => 'bg-success text-white',
            'melanjutkan' => 'bg-azure text-white',
            'wirausaha' => 'bg-warning text-dark',
            default => 'bg-secondary text-white',
        };
    }

    public function getPostGraduationPlanBadgeLtAttribute(): string
    {
        return match ($this->post_graduation_plan) {
            'bekerja' => 'bg-success-lt text-success',
            'melanjutkan' => 'bg-azure-lt text-azure',
            'wirausaha' => 'bg-warning-lt text-warning',
            default => 'bg-secondary-lt text-secondary',
        };
    }
}
