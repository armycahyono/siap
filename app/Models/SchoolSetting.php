<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'foundation_name',
        'school_name',
        'school_npsn',
        'school_address',
        'school_phone',
        'school_email',
        'school_website',
        'established_date',
        'establishment_decree',
        'operational_decree',
        'operational_decree_valid_until',
        'accreditation_grade',
        'accreditation_year',
        'accreditation_decree',
        'accreditation_date',
        'accreditation_valid_until',
        'accreditation_institution',
        'vision',
        'mission',
        'goals',
        'principal_id',
        'principal_name',
        'principal_nip',
        'curriculum_head_id',
        'curriculum_head_name',
        'curriculum_head_nip',
        'academic_year',
        'semester',
        'grade_a_min',
        'grade_b_min',
        'grade_c_min',
        'logo_path',
        'kop_surat_path',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_discipline_chat_id',
        'telegram_piket_chat_id',
        'telegram_supervision_chat_id',
        'telegram_activities_chat_id',
        'telegram_teachers_chat_id',
        'telegram_notifications_enabled',
        'school_latitude',
        'school_longitude',
        'school_radius_meters',
        'geotag_attendance_enabled',
        'geotag_require_selfie',
        'geotag_strict_radius',
    ];

    protected $casts = [
        'telegram_notifications_enabled' => 'boolean',
        'school_latitude' => 'float',
        'school_longitude' => 'float',
        'school_radius_meters' => 'integer',
        'geotag_attendance_enabled' => 'boolean',
        'geotag_require_selfie' => 'boolean',
        'geotag_strict_radius' => 'boolean',
        'established_date' => 'date',
        'operational_decree_valid_until' => 'date',
        'accreditation_date' => 'date',
        'accreditation_valid_until' => 'date',
    ];

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function curriculumHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curriculum_head_id');
    }

    public static function getSetting(bool $ignoreSession = false): self
    {
        $setting = self::firstOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'principal_name' => 'Endang Patmowati, S.Pd.',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'grade_a_min' => 86,
                'grade_b_min' => 76,
                'grade_c_min' => 61,
            ]
        );

        if (! $ignoreSession && function_exists('session')) {
            try {
                if (session()->has('active_academic_year')) {
                    $setting->academic_year = session('active_academic_year');
                }
                if (session()->has('active_semester')) {
                    $setting->semester = session('active_semester');
                }
            } catch (\Throwable) {
                // session not accessible
            }
        }

        return $setting;
    }

    public static function getMasterYear(): string
    {
        $master = self::find(1);

        return $master->academic_year ?? '2026/2027';
    }

    public static function getMasterSemester(): string
    {
        $master = self::find(1);

        return $master->semester ?? 'Ganjil';
    }

    public static function isViewingArchive(): bool
    {
        if (! function_exists('session')) {
            return false;
        }

        try {
            $masterYear = self::getMasterYear();
            $activeYear = session('active_academic_year', $masterYear);

            return $activeYear !== $masterYear;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function getAvailableAcademicYears(): array
    {
        $masterYear = self::getMasterYear();

        try {
            $fromTable = AcademicYear::orderBy('name', 'desc')->pluck('name');
            if ($fromTable->isNotEmpty()) {
                $years = collect([$masterYear, ...$fromTable])->filter()->unique()->sortDesc()->values()->toArray();
                if (! empty($years)) {
                    return $years;
                }
            }
        } catch (\Throwable) {
            // fallback if table does not exist
        }

        // Ambil tahun awal dari masterYear (misal "2026/2027" -> 2026)
        $masterStartYear = null;
        if (preg_match('/^(\d{4})/', $masterYear, $matches)) {
            $masterStartYear = (int) $matches[1];
        }

        // Ambil tahun awal jika sedang dalam konteks session
        $sessionStartYear = null;
        if (function_exists('session') && session()->has('active_academic_year')) {
            if (preg_match('/^(\d{4})/', session('active_academic_year'), $sessionMatches)) {
                $sessionStartYear = (int) $sessionMatches[1];
            }
        }

        // Hitung tahun ajaran kalender saat ini (bulan Juli / 7 ke atas sudah masuk tahun ajaran baru)
        $currentMonth = (int) now()->format('n');
        $currentCalYear = (int) now()->format('Y');
        $calStartYear = ($currentMonth >= 7) ? $currentCalYear : ($currentCalYear - 1);

        $baseYear = max($masterStartYear ?? $calStartYear, $sessionStartYear ?? 0, $calStartYear);

        // Secara otomatis generate:
        // +1 tahun ke depan (1 tingkat di atas tahun pelajaran saat ini/berjalan)
        // tahun berjalan saat ini
        // serta hingga 5 tahun ke belakang (arsip)
        $generatedYears = [];
        for ($offset = 1; $offset >= -5; $offset--) {
            $startY = $baseYear + $offset;
            $endY = $startY + 1;
            $generatedYears[] = "{$startY}/{$endY}";
        }

        $years = collect([$masterYear, ...$generatedYears]);

        try {
            $fromClassrooms = Classroom::distinct()->pluck('academic_year');
            $fromSchedules = LessonSchedule::distinct()->pluck('academic_year');
            $fromSupervisions = Supervision::distinct()->pluck('academic_year');
            $fromSubjectAssignments = TeacherSubjectAssignment::distinct()->pluck('academic_year');
            $fromStructuralAssignments = TeacherStructuralAssignment::distinct()->pluck('academic_year');

            $years = $years->merge($fromClassrooms)
                ->merge($fromSchedules)
                ->merge($fromSupervisions)
                ->merge($fromSubjectAssignments)
                ->merge($fromStructuralAssignments);
        } catch (\Throwable) {
            // fallback
        }

        return $years
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    public function getPredicate(float|int $percentage): string
    {
        if ($percentage >= $this->grade_a_min) {
            return 'Sangat Baik';
        }
        if ($percentage >= $this->grade_b_min) {
            return 'Baik';
        }
        if ($percentage >= $this->grade_c_min) {
            return 'Cukup';
        }

        return 'Perlu Bimbingan';
    }

    public function getPrincipalOfficial(): ?TeacherStructuralAssignment
    {
        return TeacherStructuralAssignment::with('teacher', 'structuralPosition')
            ->whereHas('structuralPosition', fn ($q) => $q->where('code', 'KS')->orWhere('name', 'Kepala Sekolah'))
            ->where('academic_year', $this->academic_year)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function getCurriculumHeadOfficial(): ?TeacherStructuralAssignment
    {
        return TeacherStructuralAssignment::with('teacher', 'structuralPosition')
            ->whereHas('structuralPosition', fn ($q) => $q->where('code', 'WAKA_KUR')->orWhere('name', 'like', '%Kurikulum%'))
            ->where('academic_year', $this->academic_year)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function getActivityLogTitle(): string
    {
        return "Pengaturan Sekolah & TP Master ({$this->academic_year})";
    }
}
