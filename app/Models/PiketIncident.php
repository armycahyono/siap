<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketIncident extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'piket_journal_id',
        'incident_date',
        'incident_time',
        'student_id',
        'student_name',
        'classroom_id',
        'category',
        'description',
        'action_taken',
        'follow_up_target',
        'confiscated_items',
        'evidence_photo',
        'recorded_by',
        'status',
    ];

    protected $casts = [
        'incident_date' => 'date',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'kedisiplinan' => 'Kedisiplinan',
            'seragam' => 'Pelanggaran Seragam / Kerapian',
            'gadget_terlarang' => 'HP / Gadget Terlarang',
            'perkelahian' => 'Pertikaian / Perkelahian',
            'rokok' => 'Rokok / Vape',
            'kesehatan_pingsan' => 'Kesehatan / Pingsan',
            'lainnya' => 'Lain-lain',
            default => ucfirst($this->category),
        };
    }

    public function getFollowUpLabelAttribute(): string
    {
        return match ($this->follow_up_target) {
            'selesai_di_piket' => 'Selesai di Piket',
            'rujuk_wali_kelas' => 'Rujuk ke Wali Kelas',
            'rujuk_bk' => 'Rujuk ke Guru BK',
            'rujuk_kesiswaan' => 'Rujuk ke Tim Kesiswaan',
            default => ucfirst(str_replace('_', ' ', $this->follow_up_target)),
        };
    }

    public function getEvidencePhotoUrlAttribute(): ?string
    {
        return $this->evidence_photo ? asset('storage/'.$this->evidence_photo) : null;
    }

    public function getActivityLogTitle(): string
    {
        return "Kejadian/Kasus: {$this->student_name} ({$this->category_label})";
    }
}
