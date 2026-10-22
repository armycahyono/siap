<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonSchedule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'timetable_upload_id',
        'classroom_id',
        'teacher_id',
        'subject_id',
        'academic_year',
        'semester',
        'day',
        'start_period',
        'end_period',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_period' => 'integer',
            'end_period' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function timetableUpload(): BelongsTo
    {
        return $this->belongsTo(TimetableUpload::class);
    }

    /**
     * Standard Bell Schedule Mapping for SMK PGRI Kras
     * Senin - Kamis: 10 Jam Pelajaran (07:00 - 15:00 WIB)
     * Jumat: 7 Jam Pelajaran (07:00 - 12:30 WIB)
     */
    public static function periodTimes(?string $day = null): array
    {
        if (strtolower((string) $day) === 'jumat') {
            return [
                1 => ['start' => '07:00', 'end' => '07:45'],
                2 => ['start' => '07:45', 'end' => '08:30'],
                3 => ['start' => '08:30', 'end' => '09:15'],
                // Istirahat 1 Jumat: 09:15 - 09:50
                4 => ['start' => '09:50', 'end' => '10:30'],
                5 => ['start' => '10:30', 'end' => '11:10'],
                6 => ['start' => '11:10', 'end' => '11:50'],
                7 => ['start' => '11:50', 'end' => '12:30'],
            ];
        }

        return [
            1 => ['start' => '07:00', 'end' => '07:45'],
            2 => ['start' => '07:45', 'end' => '08:30'],
            3 => ['start' => '08:30', 'end' => '09:15'],
            // Istirahat 1: 09:15 - 09:50
            4 => ['start' => '09:50', 'end' => '10:30'],
            5 => ['start' => '10:30', 'end' => '11:10'],
            6 => ['start' => '11:10', 'end' => '11:50'],
            7 => ['start' => '11:50', 'end' => '12:30'],
            // Istirahat 2: 12:30 - 13:00
            8 => ['start' => '13:00', 'end' => '13:40'],
            9 => ['start' => '13:40', 'end' => '14:20'],
            10 => ['start' => '14:20', 'end' => '15:00'],
        ];
    }

    public static function getTimeForPeriod(int $startPeriod, int $endPeriod, ?string $day = null): array
    {
        $times = self::periodTimes($day);
        $start = $times[$startPeriod]['start'] ?? null;
        $end = $times[$endPeriod]['end'] ?? null;

        return [
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    /**
     * Evaluasi apakah jadwal ini sudah masuk jendela waktu pengisian presensi
     * (Misal: 15 menit sebelum jam mulai KBM pada hari ini)
     *
     * @return array{is_open: bool, open_time: string|null, start_time: string|null, message: string|null}
     */
    public static function checkAttendanceWindow(int $startPeriod, ?string $startTime = null, ?string $day = null, ?Carbon $date = null, int $toleranceMinutes = 15): array
    {
        $date = $date ? $date->copy() : now();

        // Tanggal masa lalu selalu boleh (rekapitulasi / perbaikan catatan kehadiran)
        if ($date->isPast() && ! $date->isToday()) {
            return [
                'is_open' => true,
                'open_time' => null,
                'start_time' => $startTime,
                'message' => null,
            ];
        }

        // Tanggal masa depan belum boleh
        if ($date->isFuture() && ! $date->isToday()) {
            return [
                'is_open' => false,
                'open_time' => null,
                'start_time' => $startTime,
                'message' => 'Presensi belum dapat diisi untuk tanggal masa depan.',
            ];
        }

        $periodTimes = self::periodTimes($day);
        $effectiveStart = $startTime ?: ($periodTimes[$startPeriod]['start'] ?? '07:00');

        try {
            $parsedStart = Carbon::createFromFormat('H:i', substr($effectiveStart, 0, 5));
            $openTime = $parsedStart->copy()->subMinutes($toleranceMinutes)->format('H:i');
        } catch (\Throwable $e) {
            $openTime = $effectiveStart;
        }

        $currentTime = now()->format('H:i');

        if ($currentTime < $openTime) {
            return [
                'is_open' => false,
                'open_time' => $openTime,
                'start_time' => $effectiveStart,
                'message' => "Presensi dibuka pukul {$openTime} ({$toleranceMinutes} menit sebelum jam KBM dimulai).",
            ];
        }

        return [
            'is_open' => true,
            'open_time' => $openTime,
            'start_time' => $effectiveStart,
            'message' => null,
        ];
    }

    /**
     * Evaluasi status jendela presensi untuk instance jadwal ini
     *
     * @return array{is_open: bool, open_time: string|null, start_time: string|null, message: string|null}
     */
    public function getAttendanceWindowStatus(?Carbon $date = null, int $toleranceMinutes = 15): array
    {
        return self::checkAttendanceWindow($this->start_period, $this->start_time, $this->day, $date, $toleranceMinutes);
    }

    public function getActivityLogTitle(): string
    {
        $teacherName = $this->teacher?->name ?? "Guru #{$this->teacher_id}";
        $subjectName = $this->subject?->name ?? "Mapel #{$this->subject_id}";
        $className = $this->classroom?->name ?? "Kelas #{$this->classroom_id}";

        return "Jadwal Pelajaran {$teacherName} ({$subjectName} - {$className} {$this->day} Jam {$this->start_period}-{$this->end_period})";
    }

    /**
     * Dapatkan status kegiatan kalender yang berdampak pada rombel kelas jadwal ini di tanggal tertentu
     *
     * @param  iterable<CurriculumEvent>|null  $events
     * @return array{type: string, badge_text: string, badge_class: string, event: CurriculumEvent, title: string, note: string}|null
     */
    public function getEventImpact(?iterable $events = null): ?array
    {
        if (! $events || ! $this->classroom) {
            return null;
        }

        foreach ($events as $ev) {
            if ($ev->is_holiday || $ev->teacher_impact === 'diliburkan_penuh') {
                return [
                    'type' => 'libur',
                    'badge_text' => 'Libur',
                    'badge_class' => 'bg-danger text-white',
                    'event' => $ev,
                    'title' => $ev->title,
                    'note' => 'Hari libur sekolah / seluruh kegiatan diliburkan.',
                ];
            }

            if ($ev->appliesToClassroom($this->classroom)) {
                $learningImpact = $ev->learning_impact ?? 'normal';
                $studentImpact = $ev->disengaged_student_impact ?? 'kbm_normal';

                if (in_array($learningImpact, ['bebas_kbm_kegiatan', 'libur_khusus']) || $studentImpact === 'bebas_kbm') {
                    $isHoliday = ($learningImpact === 'libur_khusus' || $ev->is_holiday);
                    $isPkl = (stripos($ev->title, 'pkl') !== false || stripos($ev->title, 'prakerin') !== false);
                    $badgeText = $isHoliday ? 'Libur' : ($isPkl ? 'Bebas KBM (PKL)' : 'Bebas KBM');

                    return [
                        'type' => $isHoliday ? 'libur' : 'bebas_kbm',
                        'badge_text' => $badgeText,
                        'badge_class' => $isHoliday ? 'bg-danger text-white' : 'bg-purple text-white',
                        'event' => $ev,
                        'title' => $ev->title,
                        'note' => $isHoliday
                            ? 'Diliburkan karena agenda kegiatan.'
                            : ($isPkl ? 'Kelas sedang melaksanakan Praktik Kerja Lapangan (PKL).' : 'Kelas bebas tatap muka reguler karena kegiatan '.$ev->title.'.'),
                    ];
                }

                if ($learningImpact === 'pembelajaran_daring' || $studentImpact === 'pembelajaran_daring') {
                    return [
                        'type' => 'daring',
                        'badge_text' => 'KBM Daring',
                        'badge_class' => 'bg-info text-white',
                        'event' => $ev,
                        'title' => $ev->title,
                        'note' => 'Pembelajaran dilaksanakan secara daring (online dari rumah).',
                    ];
                }

                if ($learningImpact === 'pulang_cepat' || $studentImpact === 'pulang_cepat') {
                    return [
                        'type' => 'pulang_cepat',
                        'badge_text' => 'Pulang Cepat',
                        'badge_class' => 'bg-warning text-dark',
                        'event' => $ev,
                        'title' => $ev->title,
                        'note' => 'Pembelajaran terbatas / pulang lebih awal.',
                    ];
                }
            }

            if (empty($ev->target_audience) || $ev->target_audience === 'semua') {
                if (in_array($ev->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus'])) {
                    $isHoliday = ($ev->learning_impact === 'libur_khusus' || $ev->is_holiday);

                    return [
                        'type' => $isHoliday ? 'libur' : 'bebas_kbm',
                        'badge_text' => $isHoliday ? 'Libur' : 'Bebas KBM',
                        'badge_class' => $isHoliday ? 'bg-danger text-white' : 'bg-purple text-white',
                        'event' => $ev,
                        'title' => $ev->title,
                        'note' => $isHoliday
                            ? 'Diliburkan karena agenda kegiatan.'
                            : 'Kelas bebas tatap muka reguler karena kegiatan '.$ev->title.'.',
                    ];
                }

                if ($ev->learning_impact === 'pembelajaran_daring') {
                    return [
                        'type' => 'daring',
                        'badge_text' => 'KBM Daring',
                        'badge_class' => 'bg-info text-white',
                        'event' => $ev,
                        'title' => $ev->title,
                        'note' => 'Pembelajaran dilaksanakan secara daring (online dari rumah).',
                    ];
                }
            }
        }

        return null;
    }
}
