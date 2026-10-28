<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtkDecreeDetail extends Model
{
    use HasFactory, LogsActivity;

    public const CATEGORY_MENGAJAR = 'mengajar';

    public const CATEGORY_STRUKTURAL = 'struktural';

    public const CATEGORY_OPERASIONAL = 'operasional';

    public const CATEGORY_PERAN_SISTEM = 'peran_sistem';

    public const CATEGORY_ARSIP = 'arsip';

    public const CATEGORY_LAINNYA = 'lainnya';

    public const CATEGORIES = [
        self::CATEGORY_MENGAJAR => 'Guru Pengampu (KBM)',
        self::CATEGORY_STRUKTURAL => 'Jabatan Struktural',
        self::CATEGORY_OPERASIONAL => 'Jabatan / Peran Operasional',
        self::CATEGORY_PERAN_SISTEM => 'Peran Hak Akses Sistem',
        self::CATEGORY_ARSIP => 'Dokumentasi / Arsip Saja',
        self::CATEGORY_LAINNYA => 'Penugasan Lainnya',
    ];

    public const STATUS_PENDING = 'menunggu';

    public const STATUS_APPLIED = 'diterapkan';

    public const STATUS_SKIPPED = 'dilewati';

    public const STATUS_CONFLICT = 'konflik';

    public const STATUS_CANCELLED = 'dibatalkan';

    public const STATUS_EXPIRED = 'berakhir';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu Diterapkan',
        self::STATUS_APPLIED => 'Diterapkan',
        self::STATUS_SKIPPED => 'Dilewati',
        self::STATUS_CONFLICT => 'Konflik / Kendala',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_EXPIRED => 'Berakhir',
    ];

    public const LINK_TYPE_CREATED = 'dibuat_sk';

    public const LINK_TYPE_LINKED = 'ditautkan';

    public const LINK_TYPE_ARCHIVED = 'arsip';

    public const LINK_TYPES = [
        self::LINK_TYPE_CREATED => 'Dibuat oleh SK',
        self::LINK_TYPE_LINKED => 'Ditautkan ke Penugasan Eksisting',
        self::LINK_TYPE_ARCHIVED => 'Arsip (Tanpa Penugasan Operasional)',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'gtk_decree_id',
        'gtk_decree_recipient_id',
        'user_id',
        'assignment_category',
        'subject_id',
        'classroom_id',
        'structural_position_id',
        'role_id',
        'teaching_hours',
        'valid_from',
        'valid_until',
        'implementation_status',
        'link_type',
        'operational_type',
        'operational_id',
        'teacher_subject_assignment_id',
        'teacher_structural_assignment_id',
        'user_role_assignment_id',
        'conflict_notes',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'teaching_hours' => 'integer',
        ];
    }

    /**
     * Relasi ke Dokumen Induk SK GTK.
     */
    public function decree(): BelongsTo
    {
        return $this->belongsTo(GtkDecree::class, 'gtk_decree_id');
    }

    public function gtkDecree(): BelongsTo
    {
        return $this->decree();
    }

    /**
     * Relasi ke Baris Penerima SK.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(GtkDecreeRecipient::class, 'gtk_decree_recipient_id');
    }

    /**
     * Relasi ke Personil GTK (User).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Mata Pelajaran (jika kategori mengajar).
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Relasi ke Kelas / Rombel (jika kategori mengajar / wali kelas).
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Relasi ke Jabatan Struktural / Operasional (jika kategori struktural/operasional).
     */
    public function structuralPosition(): BelongsTo
    {
        return $this->belongsTo(StructuralPosition::class);
    }

    /**
     * Relasi ke Role Sistem (jika kategori peran_sistem).
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relasi referensial langsung ke Penugasan Mapel (KBM).
     */
    public function teacherSubjectAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherSubjectAssignment::class);
    }

    /**
     * Relasi referensial langsung ke Penugasan Struktural / Operasional.
     */
    public function teacherStructuralAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherStructuralAssignment::class);
    }

    /**
     * Relasi referensial langsung ke Penugasan Peran User (Hak Akses).
     */
    public function userRoleAssignment(): BelongsTo
    {
        return $this->belongsTo(UserRoleAssignment::class);
    }

    /**
     * Pembuat butir penugasan.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pengubah terakhir butir penugasan.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Mengambil model penugasan operasional riil secara aman dan terkontrol.
     * Tidak mengasumsikan relasi polimorfik selalu valid, dengan validasi allowlist ketat.
     */
    public function getOperationalAssignmentAttribute(): ?Model
    {
        if ($this->teacher_subject_assignment_id && $this->teacherSubjectAssignment) {
            return $this->teacherSubjectAssignment;
        }

        if ($this->teacher_structural_assignment_id && $this->teacherStructuralAssignment) {
            return $this->teacherStructuralAssignment;
        }

        if ($this->user_role_assignment_id && $this->userRoleAssignment) {
            return $this->userRoleAssignment;
        }

        $allowedModels = [
            TeacherSubjectAssignment::class,
            TeacherStructuralAssignment::class,
            UserRoleAssignment::class,
        ];

        if ($this->operational_type && $this->operational_id && in_array($this->operational_type, $allowedModels, true)) {
            return $this->operational_type::find($this->operational_id);
        }

        return null;
    }

    /**
     * Scope rincian yang masih menunggu penerapan.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('implementation_status', self::STATUS_PENDING);
    }

    /**
     * Scope rincian yang telah diterapkan ke sistem operasional.
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('implementation_status', self::STATUS_APPLIED);
    }

    /**
     * Scope rincian yang mengalami konflik atau kendala.
     */
    public function scopeConflict(Builder $query): Builder
    {
        return $query->where('implementation_status', self::STATUS_CONFLICT);
    }

    /**
     * Scope rincian berdasarkan kategori penugasan.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('assignment_category', $category);
    }

    public function getActivityLogTitle(): string
    {
        $categoryLabel = self::CATEGORIES[$this->assignment_category] ?? $this->assignment_category;
        $userName = $this->user?->name ?? "User #{$this->user_id}";

        return "Butir SK [{$categoryLabel}] untuk {$userName}";
    }
}
