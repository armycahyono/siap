<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ActivityCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'badge_class',
        'description',
        'is_kaldik',
        'is_active',
        'order_index',
    ];

    protected $casts = [
        'is_kaldik' => 'boolean',
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];

    public function schoolActivities(): HasMany
    {
        return $this->hasMany(SchoolActivity::class, 'category', 'code');
    }

    public function curriculumEvents(): HasMany
    {
        return $this->hasMany(CurriculumEvent::class, 'category', 'code');
    }

    /**
     * Get computed badge class or fallback.
     */
    public function getComputedBadgeClassAttribute(): string
    {
        if (! empty($this->badge_class)) {
            return $this->badge_class;
        }

        return 'bg-primary text-white';
    }

    /**
     * Dynamic category definitions format matching SchoolActivity & CurriculumEvent.
     */
    public static function categoryDefinitions(bool $onlyActive = true): array
    {
        if (! Schema::hasTable('activity_categories')) {
            return static::fallbackDefinitions();
        }

        $query = static::query()->orderBy('order_index')->orderBy('name');
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $categories = $query->get();
        if ($categories->isEmpty()) {
            return static::fallbackDefinitions();
        }

        $defs = [];
        foreach ($categories as $cat) {
            $defs[$cat->code] = [
                'id' => $cat->id,
                'label' => $cat->name,
                'color' => $cat->color ?: '#2563eb',
                'badge' => $cat->computed_badge_class,
                'description' => $cat->description ?? '',
                'is_kaldik' => (bool) $cat->is_kaldik,
                'is_active' => (bool) $cat->is_active,
                'order_index' => $cat->order_index,
            ];
        }

        return $defs;
    }

    /**
     * Hardcoded fallback in case database table is unavailable.
     */
    public static function fallbackDefinitions(): array
    {
        return [
            'smk_khusus' => [
                'label' => 'Kegiatan Khusus SMK (Vokasi)',
                'badge' => 'bg-azure text-white',
                'color' => '#0284c7',
                'description' => 'PKL / Prakerin, Kunjungan Industri (KI), UKK, Guru Tamu DUDI, Penyelarasan Kurikulum',
                'is_kaldik' => false,
            ],
            'akademik_ujian' => [
                'label' => 'Asesmen & Ujian Akademik',
                'badge' => 'bg-blue text-white',
                'color' => '#2563eb',
                'description' => 'ANBK, ASAS (Akhir Semester), ASAT (Kenaikan Kelas), ASAJ (Akhir Jenjang), KTS / STS',
                'is_kaldik' => false,
            ],
            'supervisi' => [
                'label' => 'Supervisi & Penjaminan Mutu Guru',
                'badge' => 'bg-indigo text-white',
                'color' => '#4f46e5',
                'description' => 'Supervisi Akademik, Telaah RPP / Modul Ajar, Pra/Observasi/Pasca Observasi Kelas',
                'is_kaldik' => false,
            ],
            'kaldik' => [
                'label' => 'Kalender Pendidikan Resmi (Kaldik)',
                'badge' => 'bg-danger text-white',
                'color' => '#dc2626',
                'description' => 'Hari Pertama Masuk Sekolah, MPLS, Libur Semester 1 & 2, Libur Hari Besar Nasional',
                'is_kaldik' => true,
            ],
            'kesiswaan' => [
                'label' => 'Kesiswaan & Pengembangan Karakter',
                'badge' => 'bg-success text-white',
                'color' => '#16a34a',
                'description' => 'Pondok Ramadhan, Peringatan Hari Besar, Purnawiyata / Wisuda, Ekstrakurikuler',
                'is_kaldik' => false,
            ],
            'manajemen' => [
                'label' => 'Rapat Kerja & Manajemen Sekolah',
                'badge' => 'bg-warning text-dark',
                'color' => '#f59e0b',
                'description' => 'Rapat Kerja Awal Tahun, Workshop IHT, Evaluasi Kinerja Guru & Tendik',
                'is_kaldik' => false,
            ],
            'lainnya' => [
                'label' => 'Kegiatan Umum Lainnya',
                'badge' => 'bg-secondary text-white',
                'color' => '#64748b',
                'description' => 'Agenda penugasan atau kegiatan sekolah insidental lainnya',
                'is_kaldik' => false,
            ],
        ];
    }
}
