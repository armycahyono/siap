<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtkPermit extends Model
{
    protected $guarded = ['id'];

    protected $appends = [
        'document_url',
        'assignment_document_url',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'duration_days' => 'integer',
        'approved_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public static function statusDefinitions(): array
    {
        return [
            self::STATUS_PENDING => [
                'label' => 'Menunggu Persetujuan',
                'badge' => 'bg-warning-lt text-warning border border-warning',
                'color' => '#d97706',
                'icon' => 'ti ti-clock',
            ],
            self::STATUS_APPROVED => [
                'label' => 'Disetujui',
                'badge' => 'bg-success-lt text-success border border-success',
                'color' => '#16a34a',
                'icon' => 'ti ti-check',
            ],
            self::STATUS_REJECTED => [
                'label' => 'Ditolak',
                'badge' => 'bg-danger-lt text-danger border border-danger',
                'color' => '#dc2626',
                'icon' => 'ti ti-x',
            ],
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        $defs = static::statusDefinitions();

        return $defs[$this->status]['label'] ?? ucfirst($this->status ?? 'pending');
    }

    public function getStatusBadgeAttribute(): string
    {
        $defs = static::statusDefinitions();

        return $defs[$this->status]['badge'] ?? 'bg-secondary-lt text-secondary';
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public static function typeDefinitions(): array
    {
        return [
            'sakit' => [
                'label' => 'Sakit',
                'badge' => 'bg-warning-lt text-dark',
                'icon' => 'ti ti-first-aid-kit',
                'color' => '#d97706',
            ],
            'izin' => [
                'label' => 'Izin Pribadi',
                'badge' => 'bg-info-lt text-dark',
                'icon' => 'ti ti-file-text',
                'color' => '#0284c7',
            ],
            'cuti' => [
                'label' => 'Cuti',
                'badge' => 'bg-purple-lt text-dark',
                'icon' => 'ti ti-calendar-event',
                'color' => '#9333ea',
            ],
            'dinas_luar' => [
                'label' => 'Dinas Luar / Diklat',
                'badge' => 'bg-primary-lt text-dark',
                'icon' => 'ti ti-briefcase',
                'color' => '#2563eb',
            ],
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        $defs = static::typeDefinitions();

        return $defs[$this->permission_type]['label'] ?? ucfirst($this->permission_type);
    }

    public function getPermitTypeLabelAttribute(): string
    {
        return $this->type_label;
    }

    public function getLabelAttribute(): string
    {
        return $this->type_label;
    }

    public function getTypeBadgeAttribute(): string
    {
        $defs = static::typeDefinitions();

        return $defs[$this->permission_type]['badge'] ?? 'bg-secondary-lt text-dark';
    }

    public function getTypeIconAttribute(): string
    {
        $defs = static::typeDefinitions();

        return $defs[$this->permission_type]['icon'] ?? 'ti ti-file';
    }

    public function getFormattedPeriodAttribute(): string
    {
        if ($this->start_date && $this->end_date && $this->start_date->ne($this->end_date)) {
            return $this->start_date->translatedFormat('d M Y').' s/d '.$this->end_date->translatedFormat('d M Y');
        }

        return $this->start_date ? $this->start_date->translatedFormat('d F Y') : '-';
    }

    public function getDocumentUrlAttribute(): ?string
    {
        if (! $this->document_path) {
            return null;
        }

        return route('attendance.gtk-permits.document', $this);
    }

    public function getAssignmentDocumentUrlAttribute(): ?string
    {
        if (! $this->assignment_document_path) {
            return null;
        }

        return route('attendance.gtk-permits.assignment', $this);
    }

    /**
     * Synchronize teacher absences recorded in Piket Inval / Piket Journals into GtkPermit
     */
    public static function syncFromPiketInvals(): int
    {
        $invals = PiketInvalClass::with(['journal', 'classroom', 'subject'])
            ->whereNotNull('absent_teacher_id')
            ->get();

        $syncedCount = 0;

        $grouped = $invals->groupBy(function ($item) {
            $date = $item->journal?->date;
            $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;

            return $item->absent_teacher_id.'|'.$dateStr;
        });

        foreach ($grouped as $key => $items) {
            [$teacherId, $dateStr] = explode('|', $key);
            if (empty($teacherId) || empty($dateStr)) {
                continue;
            }

            $existing = static::where('user_id', (int) $teacherId)
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->first();

            if (! $existing) {
                $firstItem = $items->first();
                $reasonText = $firstItem->reason ?: 'Izin';
                $reasonLower = strtolower($reasonText);

                $permitType = match (true) {
                    str_contains($reasonLower, 'sakit') => 'sakit',
                    str_contains($reasonLower, 'cuti') => 'cuti',
                    str_contains($reasonLower, 'dinas') || str_contains($reasonLower, 'tugas') => 'dinas_luar',
                    default => 'izin',
                };

                $classInfo = $items->map(function ($it) {
                    $cls = $it->classroom?->name ?? 'Kelas';
                    $sub = $it->subject?->name ?? 'Mapel';

                    return "{$cls} ({$sub}, Jam {$it->period_start}-{$it->period_end})";
                })->implode(', ');

                $task = $items->pluck('assignment_material')->filter()->first();
                $finalReason = $reasonText.($task ? " (Tugas: {$task})" : '');

                static::create([
                    'user_id' => (int) $teacherId,
                    'permission_type' => $permitType,
                    'status' => self::STATUS_APPROVED,
                    'start_date' => $dateStr,
                    'end_date' => $dateStr,
                    'duration_days' => 1,
                    'reason' => $finalReason,
                    'notes' => 'Dicatat dari Jurnal Piket'.($classInfo ? " [{$classInfo}]" : ''),
                    'approved_by' => $firstItem->journal?->coordinator_id,
                    'approved_at' => now(),
                ]);

                $syncedCount++;
            }
        }

        return $syncedCount;
    }
}
