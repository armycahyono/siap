<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolActivity extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'is_kaldik' => 'boolean',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'default_duration_days' => 'integer',
        'order_index' => 'integer',
    ];

    public function curriculumEvents(): HasMany
    {
        return $this->hasMany(CurriculumEvent::class, 'school_activity_id');
    }

    public static function categoryDefinitions(): array
    {
        return [
            'smk_khusus' => [
                'label' => 'Kegiatan Khusus SMK (Vokasi)',
                'badge' => 'bg-azure text-white',
                'color' => '#0284c7',
                'description' => 'PKL / Prakerin, Kunjungan Industri (KI), UKK, Guru Tamu DUDI, Penyelarasan Kurikulum',
            ],
            'akademik_ujian' => [
                'label' => 'Asesmen & Ujian Akademik',
                'badge' => 'bg-blue text-white',
                'color' => '#2563eb',
                'description' => 'ANBK, ASAS (Akhir Semester), ASAT (Kenaikan Kelas), ASAJ (Akhir Jenjang), KTS / STS',
            ],
            'supervisi' => [
                'label' => 'Supervisi & Penjaminan Mutu Guru',
                'badge' => 'bg-indigo text-white',
                'color' => '#4f46e5',
                'description' => 'Supervisi Akademik, Telaah RPP / Modul Ajar, Pra/Observasi/Pasca Observasi Kelas',
            ],
            'kaldik' => [
                'label' => 'Kalender Pendidikan Resmi (Kaldik)',
                'badge' => 'bg-danger text-white',
                'color' => '#dc2626',
                'description' => 'Hari Pertama Masuk Sekolah, MPLS, Libur Semester 1 & 2, Libur Hari Besar Nasional',
            ],
            'kesiswaan' => [
                'label' => 'Kesiswaan & Pengembangan Karakter',
                'badge' => 'bg-success text-white',
                'color' => '#16a34a',
                'description' => 'Pondok Ramadhan, Peringatan Hari Besar, Purnawiyata / Wisuda, Ekstrakurikuler',
            ],
            'manajemen' => [
                'label' => 'Rapat Kerja & Manajemen Sekolah',
                'badge' => 'bg-warning text-dark',
                'color' => '#f59e0b',
                'description' => 'Rapat Kerja Awal Tahun, Workshop IHT, Evaluasi Kinerja Guru & Tendik',
            ],
            'lainnya' => [
                'label' => 'Kegiatan Umum Lainnya',
                'badge' => 'bg-secondary text-white',
                'color' => '#64748b',
                'description' => 'Agenda penugasan atau kegiatan sekolah insidental lainnya',
            ],
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['label'] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function getCategoryBadgeAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['badge'] ?? 'bg-secondary text-white';
    }

    public function getCategoryColorAttribute(): string
    {
        if (! empty($this->color)) {
            return $this->color;
        }
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['color'] ?? '#2563eb';
    }

    /**
     * Get scheduled event in curriculum calendar for a specific academic year.
     */
    public function getScheduledEventForYear(string $academicYear): ?CurriculumEvent
    {
        // 1. Direct foreign key link
        $event = $this->curriculumEvents()->where('academic_year', $academicYear)->first();
        if ($event) {
            return $event;
        }

        // 2. Name or code matching fallback
        $name = trim($this->name);
        $code = trim((string) $this->code);

        $matched = CurriculumEvent::where('academic_year', $academicYear)
            ->where(function ($q) use ($name, $code) {
                $q->where('title', 'like', "%{$name}%");
                if ($code !== '') {
                    $q->orWhere('title', 'like', "%{$code}%");
                }
            })
            ->first();

        return $matched;
    }

    /**
     * Check if this activity is scheduled for a specific academic year.
     */
    public function isScheduledForYear(string $academicYear): bool
    {
        if ($this->getScheduledEventForYear($academicYear)) {
            return true;
        }

        // Special case for Supervisi: check if supervisions table has scheduled records
        if ($this->category === 'supervisi' || strtolower($this->code ?? '') === 'spv') {
            return Supervision::where('academic_year', $academicYear)
                ->whereNotNull('schedule_date')
                ->exists();
        }

        return false;
    }

    public function getActivityLogTitle(): string
    {
        return "Kegiatan Sekolah '{$this->name}'".($this->code ? " ({$this->code})" : '');
    }
}
