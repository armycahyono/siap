<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'nisn',
        'nis',
        'nik',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'address',
        'classroom_id',
        'status',
        'graduation_year',
        'last_classroom_name',
        'entry_year',
        'father_name',
        'father_job',
        'father_phone',
        'mother_name',
        'mother_job',
        'mother_phone',
        'guardian_name',
        'guardian_job',
        'guardian_phone',
        'phone_parent',
        'phone_student',
        'email',
        'postal_code',
        'residence_type',
        'transportation',
        'distance_to_school_km',
        'latitude',
        'longitude',
        'family_card_no',
        'birth_certificate_no',
        'child_order',
        'siblings_count',
        'weight_kg',
        'height_cm',
        'head_circumference_cm',
        'special_needs',
        'school_origin',
        'national_exam_no',
        'diploma_serial_no',
        'skhun_no',
        'is_pip_eligible',
        'pip_reason',
        'kip_recipient',
        'kip_number',
        'kip_name',
        'kks_number',
        'kps_recipient',
        'kps_number',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'father_birth_year',
        'father_education',
        'father_income',
        'father_nik',
        'mother_birth_year',
        'mother_education',
        'mother_income',
        'mother_nik',
        'guardian_birth_year',
        'guardian_education',
        'guardian_income',
        'guardian_nik',
        'notes',
        'dapodik_payload',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'is_pip_eligible' => 'boolean',
        'kip_recipient' => 'boolean',
        'kps_recipient' => 'boolean',
        'distance_to_school_km' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'child_order' => 'integer',
        'siblings_count' => 'integer',
        'weight_kg' => 'integer',
        'height_cm' => 'integer',
        'head_circumference_cm' => 'integer',
        'dapodik_payload' => 'array',
    ];

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null ? mb_strtoupper(trim($value), 'UTF-8') : '';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeAlumni($query)
    {
        return $query->where('status', 'lulus');
    }

    public function scopeMutated($query)
    {
        return $query->whereIn('status', ['mutasi', 'keluar']);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'aktif' => 'Aktif',
            'lulus' => 'Alumni / Lulus',
            'mutasi' => 'Mutasi Keluar',
            'keluar' => 'Keluar / DO',
            default => ucfirst($this->status ?? 'Aktif'),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'aktif' => 'bg-success-lt',
            'lulus' => 'bg-purple-lt text-purple',
            'mutasi' => 'bg-warning-lt text-warning',
            'keluar' => 'bg-danger-lt text-danger',
            default => 'bg-secondary-lt',
        };
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function studentClassrooms(): HasMany
    {
        return $this->hasMany(StudentClassroom::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'student_classrooms')
            ->withPivot(['status', 'is_repeating', 'notes', 'academic_year_name', 'classroom_name'])
            ->withTimestamps();
    }

    public function classroomForAcademicYear(int|string $academicYearIdOrName): ?Classroom
    {
        $relation = $this->studentClassrooms()
            ->with('classroom.homeroomTeacher')
            ->where(function ($q) use ($academicYearIdOrName) {
                if (is_numeric($academicYearIdOrName)) {
                    $q->where('academic_year_id', $academicYearIdOrName);
                } else {
                    $q->where('academic_year_name', $academicYearIdOrName)
                        ->orWhereHas('academicYear', fn ($sub) => $sub->where('name', $academicYearIdOrName));
                }
            })
            ->first();

        return $relation?->classroom;
    }

    public function permits(): HasMany
    {
        return $this->hasMany(PiketStudentPermit::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(PiketIncident::class);
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'P' ? 'Perempuan' : 'Laki-laki';
    }

    public function getGenderBadgeAttribute(): string
    {
        return $this->gender === 'P' ? 'bg-pink-lt' : 'bg-blue-lt';
    }

    public function getFormattedBirthAttribute(): string
    {
        $place = $this->birth_place;
        $date = $this->birth_date?->format('d/m/Y');

        if ($place && $date) {
            return "{$place}, {$date}";
        }

        return $place ?: ($date ?: '-');
    }

    public function getPrimaryParentNameAttribute(): string
    {
        return $this->father_name ?: ($this->mother_name ?: ($this->guardian_name ?: '-'));
    }

    public function getPrimaryParentPhoneAttribute(): string
    {
        return $this->phone_parent ?: ($this->father_phone ?: ($this->mother_phone ?: ($this->guardian_phone ?: '-')));
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(StudentMutation::class)->latest();
    }

    public function latestMutation()
    {
        return $this->hasOne(StudentMutation::class)->latestOfMany();
    }

    public function getActivityLogTitle(): string
    {
        return "Siswa: {$this->name} (".($this->classroom?->name ?? '-').')';
    }
}
