<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class StructuralPosition extends Model
{
    use HasFactory;

    public const TYPE_STRUKTURAL = 'struktural';

    public const TYPE_OPERASIONAL = 'operasional';

    public const TYPES = [
        self::TYPE_STRUKTURAL => 'Struktural',
        self::TYPE_OPERASIONAL => 'Operasional',
    ];

    public const SCOPE_SCHOOL = 'school';

    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPE_UNIT = 'unit';

    public const SCOPE_CLASSROOM = 'classroom';

    public const SCOPES = [
        self::SCOPE_SCHOOL => 'Sekolah (Global)',
        self::SCOPE_DEPARTMENT => 'Jurusan / Keahlian',
        self::SCOPE_UNIT => 'Unit Fasilitas / Lab / Bengkel',
        self::SCOPE_CLASSROOM => 'Rombel / Kelas',
    ];

    public const TENURE_OPTIONS = [1, 2, 3, 4, 5];

    protected $fillable = [
        'name',
        'code',
        'category',
        'type',
        'default_tenure_years',
        'scope_type',
        'allows_multiple',
        'order',
        'description',
        'signature_image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allows_multiple' => 'boolean',
        'order' => 'integer',
        'default_tenure_years' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TeacherStructuralAssignment::class)->where('is_active', true);
    }

    public function currentAssignment(?string $academicYear = null): HasOne
    {
        $query = $this->hasOne(TeacherStructuralAssignment::class)->where('is_active', true);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->latest('id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_structural_assignments', 'structural_position_id', 'teacher_id')
            ->withPivot(['academic_year', 'sk_number', 'sk_date', 'notes', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Dapatkan path fisik berkas tanda tangan untuk keperluan pemrosesan server / PDF.
     */
    public function getSignatureRealPath(): ?string
    {
        if (! $this->signature_image_path) {
            return null;
        }

        if (Storage::disk('local')->exists($this->signature_image_path)) {
            return Storage::disk('local')->path($this->signature_image_path);
        }

        if (Storage::disk('public')->exists($this->signature_image_path)) {
            return Storage::disk('public')->path($this->signature_image_path);
        }

        return null;
    }

    /**
     * Dapatkan representasi Base64 Data URI untuk disematkan langsung pada dokumen PDF tanpa akses URL.
     */
    public function getSignatureBase64(): ?string
    {
        $path = $this->getSignatureRealPath();
        if (! $path || ! file_exists($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $data = file_get_contents($path);

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }

    /**
     * Scope untuk penugasan struktural.
     */
    public function scopeStructural($query)
    {
        return $query->where('type', self::TYPE_STRUKTURAL);
    }

    /**
     * Scope untuk penugasan operasional.
     */
    public function scopeOperational($query)
    {
        return $query->where('type', self::TYPE_OPERASIONAL);
    }

    /**
     * Scope untuk tugas yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Label tipe penugasan.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type ?? 'Struktural');
    }

    /**
     * Label cakupan penugasan.
     */
    public function getScopeLabelAttribute(): string
    {
        return self::SCOPES[$this->scope_type] ?? ucfirst($this->scope_type ?? 'Sekolah');
    }

    /**
     * Cek apakah jabatan/tugas sudah memiliki riwayat penugasan personil.
     */
    public function hasHistoricalAssignments(): bool
    {
        return $this->assignments()->exists();
    }
}
