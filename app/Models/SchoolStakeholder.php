<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolStakeholder extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'category',
        'name',
        'position',
        'institution',
        'nip',
        'nik',
        'rank_group',
        'phone',
        'email',
        'is_signatory',
        'endorsement_title',
        'signature_image_path',
        'order_index',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_signatory' => 'boolean',
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];

    /**
     * Category definitions with display labels, icons, and badge classes.
     */
    public static function categoryDefinitions(): array
    {
        return [
            'yayasan' => [
                'label' => 'Pengurus Yayasan',
                'badge' => 'bg-purple-lt text-purple',
                'icon' => 'ti ti-building-community',
                'color' => '#6f42c1',
            ],
            'komite' => [
                'label' => 'Pengurus Komite Sekolah',
                'badge' => 'bg-teal-lt text-teal',
                'icon' => 'ti ti-users-group',
                'color' => '#0ca678',
            ],
            'pengawas' => [
                'label' => 'Pengawas Sekolah / Pembina',
                'badge' => 'bg-azure-lt text-azure',
                'icon' => 'ti ti-user-check',
                'color' => '#4299e1',
            ],
            'dinas' => [
                'label' => 'Pejabat Dinas Pendidikan',
                'badge' => 'bg-orange-lt text-orange',
                'icon' => 'ti ti-building',
                'color' => '#f76707',
            ],
        ];
    }

    /**
     * Standard endorsement titles used on approval sheets.
     */
    public static function endorsementTitleOptions(): array
    {
        return [
            'Mengetahui,' => 'Mengetahui,',
            'Menyetujui,' => 'Menyetujui,',
            'Mengesahkan,' => 'Mengesahkan,',
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['label'] ?? ucfirst($this->category);
    }

    public function getCategoryBadgeAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['badge'] ?? 'bg-secondary-lt text-secondary';
    }

    public function getCategoryIconAttribute(): string
    {
        $defs = static::categoryDefinitions();

        return $defs[$this->category]['icon'] ?? 'ti ti-user';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSignatories(Builder $query): Builder
    {
        return $query->where('is_signatory', true)->where('is_active', true);
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        if ($category && $category !== 'all') {
            return $query->where('category', $category);
        }

        return $query;
    }

    public function getActivityLogTitle(): string
    {
        return "Stakeholder '{$this->name}' ({$this->category_label})";
    }
}
