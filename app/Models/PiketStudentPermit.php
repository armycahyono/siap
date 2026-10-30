<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketStudentPermit extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'piket_journal_id',
        'permit_number',
        'permit_date',
        'end_date',
        'student_id',
        'student_name',
        'classroom_id',
        'permit_type',
        'permit_category',
        'reason',
        'entry_time',
        'departure_time',
        'return_time',
        'period_assigned',
        'action_taken',
        'reporter_type',
        'attachment_path',
        'given_by',
        'status',
        'notified_homeroom',
        'notified_subject_teacher',
    ];

    protected $casts = [
        'permit_date' => 'date',
        'end_date' => 'date',
        'notified_homeroom' => 'boolean',
        'notified_subject_teacher' => 'boolean',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PiketJournal::class, 'piket_journal_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function giver(): BelongsTo
    {
        return $this->givenBy();
    }

    public function getPermitTypeLabelAttribute(): string
    {
        if ($this->permit_type === 'izin_tidak_masuk') {
            return match ($this->permit_category) {
                'sakit' => 'Sakit (Tidak Masuk)',
                'dispen' => 'Dispensasi',
                default => 'Izin Tidak Masuk',
            };
        }

        return match ($this->permit_type) {
            'terlambat' => 'Terlambat Masuk',
            'izin_keluar_sementara' => 'Izin Keluar Sementara',
            'izin_pulang_cepat' => 'Izin Pulang Cepat',
            'izin_tidak_masuk' => 'Izin Tidak Masuk',
            default => ucfirst(str_replace('_', ' ', $this->permit_type)),
        };
    }

    public function getPermitTypeBadgeClassAttribute(): string
    {
        if ($this->permit_type === 'izin_tidak_masuk') {
            return match ($this->permit_category) {
                'sakit' => 'bg-warning text-dark',
                'dispen' => 'bg-purple text-white',
                default => 'bg-info text-white',
            };
        }

        return match ($this->permit_type) {
            'terlambat' => 'bg-warning text-dark',
            'izin_keluar_sementara' => 'bg-blue text-white',
            'izin_pulang_cepat' => 'bg-danger text-white',
            'izin_tidak_masuk' => 'bg-secondary text-white',
            default => 'bg-dark text-white',
        };
    }

    public function getActivityLogTitle(): string
    {
        return "Izin Siswa: {$this->student_name} ({$this->permit_type_label})";
    }

    /**
     * Dapatkan Jam Pelajaran (JP) saat izin mulai berlaku atau selesai
     */
    public function getAffectedPeriod(): ?int
    {
        if (! empty($this->period_assigned)) {
            // Jika disimpan sebagai angka atau "Jam ke-X"
            if (preg_match('/\d+/', (string) $this->period_assigned, $matches)) {
                return (int) $matches[0];
            }
        }

        // Fallback: estimasi dari entry_time atau departure_time
        $timeStr = null;
        if ($this->permit_type === 'terlambat' && $this->entry_time) {
            $timeStr = substr($this->entry_time, 0, 5);
        } elseif ($this->permit_type === 'izin_pulang_cepat' && $this->departure_time) {
            $timeStr = substr($this->departure_time, 0, 5);
        }

        if ($timeStr) {
            $periodTimes = LessonSchedule::periodTimes();
            foreach ($periodTimes as $p => $times) {
                if ($timeStr <= $times['end']) {
                    return $p;
                }
            }

            return count($periodTimes);
        }

        return null;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return route('piket.permits.attachment', $this);
    }
}
