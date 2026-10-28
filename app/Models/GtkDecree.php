<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class GtkDecree extends Model
{
    use HasFactory, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'aktif';

    public const STATUS_EXPIRED = 'berakhir';

    public const STATUS_CANCELLED = 'dibatalkan';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_EXPIRED => 'Berakhir',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const TYPE_PEMBAGIAN_TUGAS = 'pembagian_tugas';

    public const TYPE_TUGAS_TAMBAHAN = 'tugas_tambahan';

    public const TYPE_KEPANITIAAN = 'kepanitiaan';

    public const TYPE_WALI_KELAS = 'wali_kelas';

    public const TYPE_GURU_PIKET = 'guru_piket';

    public const TYPE_PENGANGKATAN = 'pengangkatan';

    public const TYPE_LAINNYA = 'lainnya';

    public const TYPES = [
        self::TYPE_PEMBAGIAN_TUGAS => 'Pembagian Tugas KBM',
        self::TYPE_TUGAS_TAMBAHAN => 'Tugas Tambahan & Struktural',
        self::TYPE_KEPANITIAAN => 'Kepanitiaan & Tim Operasional',
        self::TYPE_WALI_KELAS => 'Wali Kelas & Pendamping',
        self::TYPE_GURU_PIKET => 'Guru Piket',
        self::TYPE_PENGANGKATAN => 'Pengangkatan / Penetapan GTK',
        self::TYPE_LAINNYA => 'Lainnya / Arsip',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'title',
        'type',
        'academic_year_id',
        'academic_year',
        'issued_date',
        'tmt_date',
        'valid_from',
        'valid_until',
        'signer_name',
        'signer_title',
        'file_path',
        'original_filename',
        'file_size',
        'description',
        'status',
        'activated_at',
        'activated_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'tmt_date' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * Relasi ke Tahun Ajaran (FK referensial).
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Relasi ke Daftar Penerima SK (tabel perantara penerima).
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(GtkDecreeRecipient::class);
    }

    /**
     * Relasi ke Personil GTK (User) yang tercantum sebagai penerima SK.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gtk_decree_recipients', 'gtk_decree_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Relasi ke seluruh butir rincian keputusan/penugasan dalam SK.
     */
    public function details(): HasMany
    {
        return $this->hasMany(GtkDecreeDetail::class);
    }

    /**
     * User yang mengaktifkan SK.
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * User yang membatalkan SK.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Pembuat draf SK.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pengubah terakhir SK.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope SK berstatus Draft.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope SK berstatus Aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope SK berstatus Berakhir.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope SK berstatus Dibatalkan.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope SK berdasarkan tahun ajaran (string atau ID).
     */
    public function scopeForAcademicYear(Builder $query, string|int $academicYear): Builder
    {
        if (is_numeric($academicYear)) {
            return $query->where('academic_year_id', $academicYear);
        }

        return $query->where(function ($q) use ($academicYear) {
            $q->where('academic_year', $academicYear)
                ->orWhereHas('academicYear', fn ($ay) => $ay->where('name', $academicYear));
        });
    }

    /**
     * Memeriksa apakah file PDF tersimpan di disk privat 'local'.
     */
    public function hasPrivateDocument(): bool
    {
        if (empty($this->file_path)) {
            return false;
        }

        return Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Mengambil path absolut dokumen privat di storage server.
     */
    public function getPrivateDocumentAbsolutePath(): ?string
    {
        if (! $this->hasPrivateDocument()) {
            return null;
        }

        return Storage::disk('local')->path($this->file_path);
    }

    public function getActivityLogTitle(): string
    {
        return "SK GTK No. {$this->number} ({$this->title})";
    }
}
