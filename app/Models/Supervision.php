<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Supervision extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'report_number',
        'academic_year',
        'semester',
        'teacher_id',
        'supervisor_id',
        'principal_id',
        'subject',
        'class_name',
        'expertise_program',
        'schedule_date',
        'schedule_time',
        'schedule_notes',
        'status',
        'rpp_file_path',
        'rpp_original_name',
        'rpp_uploaded_at',
        'pre_material',
        'pre_objectives',
        'pre_method',
        'pre_media',
        'pre_strategy',
        'pre_focus',
        'pre_readiness',
        'pre_supervisor_notes',
        'pre_completed_at',
        'obs_date',
        'obs_time',
        'total_score',
        'max_score',
        'percentage',
        'predicate',
        'obs_completed_at',
        'post_positive',
        'post_improvement',
        'post_recommendation',
        'post_followup',
        'post_discussion',
        'post_completed_at',
        'supervisor_signed_at',
        'approved_by_principal',
        'principal_approved_at',
        'principal_notes',
        'qr_token',
        'finalized_at',
        'snapshot_data',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'obs_date' => 'date',
            'rpp_uploaded_at' => 'datetime',
            'pre_completed_at' => 'datetime',
            'obs_completed_at' => 'datetime',
            'post_completed_at' => 'datetime',
            'supervisor_signed_at' => 'datetime',
            'principal_approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'approved_by_principal' => 'boolean',
            'total_score' => 'integer',
            'max_score' => 'integer',
            'percentage' => 'float',
            'snapshot_data' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SupervisionScore::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SupervisionPhoto::class);
    }

    /**
     * Recalculate total score, max score, percentage, and predicate.
     */
    public function recalculateScore(): void
    {
        $setting = SchoolSetting::getSetting();
        $scores = $this->scores()->with('indicator')->get();

        $total = 0;
        $max = 0;

        foreach ($scores as $s) {
            $total += (int) $s->score;
            $max += (int) ($s->indicator?->max_score ?? 4);
        }

        // If no scores yet, fallback to active indicators count
        if ($max === 0) {
            $activeIndicators = SupervisionIndicator::where('is_active', true)->get();
            $max = $activeIndicators->sum('max_score') ?: 92;
        }

        $percentage = $max > 0 ? round(($total / $max) * 100, 2) : 0;
        $predicate = $setting->getPredicate($percentage);

        $this->update([
            'total_score' => $total,
            'max_score' => $max,
            'percentage' => $percentage,
            'predicate' => $predicate,
        ]);
    }

    public static function generateReportNumber(): string
    {
        $year = date('Y');
        $existingNumbers = self::whereNotNull('report_number')
            ->where('report_number', 'like', "%{$year}")
            ->pluck('report_number');

        $max = 0;
        foreach ($existingNumbers as $num) {
            if (preg_match('#^SPV/(\d+)/#', $num, $matches)) {
                $val = (int) $matches[1];
                if ($val > $max) {
                    $max = $val;
                }
            }
        }

        do {
            $max++;
            $padded = str_pad((string) $max, 3, '0', STR_PAD_LEFT);
            $candidate = "SPV/{$padded}/SMK PGRI KRAS/{$year}";
        } while (self::where('report_number', $candidate)->exists());

        return $candidate;
    }

    public static function sanitizeNip(?string $nip): ?string
    {
        if (! $nip) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim($nip));
        // NIK has 16 digits (e.g., 350...). Real PNS NIP has 18 digits.
        if (strlen($digits) === 16 || str_starts_with($digits, '350')) {
            return null;
        }

        return trim($nip);
    }

    public static function sanitizeNuptk(?string $nuptk): ?string
    {
        if (! $nuptk) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim($nuptk));
        // Real NUPTK is exactly 16 numeric digits.
        // Short numbers (<= 8 characters) are school NIGS codes, not NUPTK.
        if (strlen($digits) !== 16) {
            return null;
        }

        return trim($nuptk);
    }

    public function ensureQrToken(): string
    {
        if (empty($this->qr_token)) {
            $this->qr_token = Str::random(40);
            $this->save();
        }

        return $this->qr_token;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'terjadwal' => 'Terjadwal',
            'praobservasi' => 'Praobservasi Selesai',
            'observasi' => 'Sedang Observasi',
            'pascaobservasi' => 'Pascaobservasi Selesai',
            'menunggu_persetujuan' => 'Persetujuan KS',
            'disetujui' => 'Disetujui Kepsek',
            'selesai' => 'Selesai',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'terjadwal' => 'azure',
            'praobservasi' => 'blue',
            'observasi' => 'warning',
            'pascaobservasi' => 'indigo',
            'menunggu_persetujuan' => 'orange',
            'disetujui', 'selesai' => 'success',
            default => 'secondary',
        };
    }

    /**
     * Check if this supervision has frozen snapshot data.
     */
    public function hasSnapshot(): bool
    {
        return ! empty($this->snapshot_data) && is_array($this->snapshot_data);
    }

    /**
     * Freeze and capture an immutable snapshot of all evaluation details,
     * rubric indicators, teacher/supervisor/principal identities, and school kop.
     */
    public function captureSnapshot(bool $save = true): array
    {
        $setting = SchoolSetting::getSetting();
        $this->loadMissing(['teacher', 'supervisor', 'principal', 'scores']);

        $scoresMap = $this->scores->keyBy('indicator_id');

        $phasesData = [];
        $phases = SupervisionPhase::with(['indicators' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        foreach ($phases as $phase) {
            $indicatorsData = [];
            foreach ($phase->indicators as $ind) {
                $sc = $scoresMap->get($ind->id);
                $indicatorsData[] = [
                    'id' => $ind->id,
                    'code' => $ind->code,
                    'title' => $ind->title,
                    'description' => $ind->description,
                    'max_score' => $ind->max_score,
                    'score' => $sc?->score !== null ? (int) $sc->score : null,
                    'notes' => $sc?->notes ?? null,
                ];
            }

            $phasesData[] = [
                'id' => $phase->id,
                'name' => $phase->name,
                'code' => $phase->code,
                'indicators' => $indicatorsData,
            ];
        }

        $snapshot = [
            'frozen_at' => now()->toIso8601String(),
            'school' => [
                'name' => $setting->school_name,
                'address' => $setting->school_address,
                'phone' => $setting->school_phone,
                'email' => $setting->school_email,
                'logo' => $setting->school_logo,
                'principal_name' => $setting->principal_name,
                'principal_nip' => self::sanitizeNip($setting->principal_nip),
            ],
            'teacher' => [
                'id' => $this->teacher_id,
                'name' => $this->teacher?->name,
                'nip' => self::sanitizeNip($this->teacher?->nip),
                'nuptk' => self::sanitizeNuptk($this->teacher?->nuptk),
                'nigs' => $this->teacher?->nigs,
            ],
            'supervisor' => [
                'id' => $this->supervisor_id,
                'name' => $this->supervisor?->name,
                'nip' => self::sanitizeNip($this->supervisor?->nip),
                'nuptk' => self::sanitizeNuptk($this->supervisor?->nuptk),
                'nigs' => $this->supervisor?->nigs,
            ],
            'principal' => [
                'id' => $this->principal_id,
                'name' => $this->principal?->name ?? $setting->principal_name,
                'nip' => self::sanitizeNip($this->principal?->nip ?? $setting->principal_nip),
                'approved_at' => $this->principal_approved_at?->toIso8601String() ?? now()->toIso8601String(),
                'notes' => $this->principal_notes,
            ],
            'context' => [
                'report_number' => $this->report_number,
                'academic_year' => $this->academic_year,
                'semester' => $this->semester,
                'subject' => $this->subject,
                'class_name' => $this->class_name,
                'expertise_program' => $this->expertise_program,
                'schedule_date' => $this->schedule_date?->format('Y-m-d'),
                'schedule_time' => $this->schedule_time,
                'obs_date' => $this->obs_date?->format('Y-m-d'),
                'obs_time' => $this->obs_time,
            ],
            'phases' => $phasesData,
            'summary' => [
                'total_score' => $this->total_score,
                'max_score' => $this->max_score,
                'percentage' => $this->percentage,
                'predicate' => $this->predicate,
            ],
        ];

        if ($save) {
            $this->update(['snapshot_data' => $snapshot]);
        }

        return $snapshot;
    }

    /**
     * Get rubric phases and indicators.
     * If snapshot exists, returns frozen indicators. Otherwise falls back to live master indicators.
     */
    public function getRubricPhases(): array
    {
        if ($this->hasSnapshot() && ! empty($this->snapshot_data['phases'])) {
            return $this->snapshot_data['phases'];
        }

        // Live fallback
        $this->loadMissing('scores');
        $scoresMap = $this->scores->keyBy('indicator_id');

        $phases = SupervisionPhase::with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $phasesData = [];
        foreach ($phases as $phase) {
            $indicatorsData = [];
            foreach ($phase->indicators as $ind) {
                $sc = $scoresMap->get($ind->id);
                $indicatorsData[] = [
                    'id' => $ind->id,
                    'code' => $ind->code,
                    'title' => $ind->title,
                    'description' => $ind->description,
                    'max_score' => $ind->max_score,
                    'score' => $sc?->score,
                    'notes' => $sc?->notes,
                ];
            }

            $phasesData[] = [
                'id' => $phase->id,
                'name' => $phase->name,
                'code' => $phase->code,
                'indicators' => $indicatorsData,
            ];
        }

        return $phasesData;
    }

    /**
     * Get frozen or live School Info for reporting.
     */
    public function getSnapshotSchool(): array
    {
        $setting = SchoolSetting::getSetting();

        return [
            'name' => $this->snapshot_data['school']['name'] ?? $setting->school_name,
            'address' => $this->snapshot_data['school']['address'] ?? $setting->school_address,
            'phone' => $this->snapshot_data['school']['phone'] ?? $setting->school_phone,
            'email' => $this->snapshot_data['school']['email'] ?? $setting->school_email,
            'logo' => $this->snapshot_data['school']['logo'] ?? $setting->school_logo,
            'principal_name' => $this->snapshot_data['principal']['name'] ?? $setting->principal_name,
            'principal_nip' => $this->snapshot_data['principal']['nip'] ?? $setting->principal_nip,
        ];
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $subject = $this->subject ?: 'Supervisi';
        $class = $this->class_name ?: '';

        return "Supervisi {$teacherName} ({$subject}".($class ? " - {$class}" : '').')';
    }
}
