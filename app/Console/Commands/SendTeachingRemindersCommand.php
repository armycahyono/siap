<?php

namespace App\Console\Commands;

use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketInvalClass;
use App\Models\SchoolSetting;
use App\Services\AcademicYearContext;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTeachingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:teaching-reminder {--force : Abaikan cache dan kirim paksa} {--test : Mode simulasi tanpa kirim telegram}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim notifikasi Telegram pengingat 10 menit sebelum jam KBM dimulai ke guru pengajar';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $dayIso = $now->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu

        // Hari Minggu tidak ada KBM
        if ($dayIso > 6) {
            $this->line('Hari Minggu tidak ada jadwal KBM.');

            return Command::SUCCESS;
        }

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        $todayDay = $dayNames[$dayIso] ?? 'Senin';
        $todayDate = $now->toDateString();

        try {
            $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
        } catch (\Throwable $e) {
            Log::warning('SendTeachingRemindersCommand operational year fallback: '.$e->getMessage());
            $academicYear = SchoolSetting::getMasterYear();
        }

        $periodTimes = LessonSchedule::periodTimes($todayDay);
        $targetPeriods = [];

        foreach ($periodTimes as $period => $times) {
            $periodStartTime = Carbon::createFromFormat('Y-m-d H:i', "{$todayDate} {$times['start']}", 'Asia/Jakarta');
            // Menit menuju jam mulai
            $diffInMinutes = $now->diffInMinutes($periodStartTime, false);

            // Jendela notifikasi: antara 5 sampai 12 menit sebelum jam mulai (ideal 10 menit)
            if ($diffInMinutes >= 5 && $diffInMinutes <= 12) {
                $targetPeriods[$period] = [
                    'start' => $times['start'],
                    'end' => $times['end'],
                ];
            }
        }

        if (empty($targetPeriods)) {
            $this->line("Tidak ada jam KBM yang dimulai dalam 10 menit ke depan (Waktu: {$now->format('H:i:s')} WIB).");

            return Command::SUCCESS;
        }

        $this->info('Mendeteksi jam pelajaran mendekati mulai: '.implode(', ', array_keys($targetPeriods)));

        $schedules = LessonSchedule::with(['teacher', 'classroom', 'subject'])
            ->where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->whereIn('start_period', array_keys($targetPeriods))
            ->get();

        $sentCount = 0;
        $isForce = (bool) $this->option('force');
        $isTest = (bool) $this->option('test');

        foreach ($schedules as $sched) {
            $teacher = $sched->teacher;
            if (! $teacher || empty($teacher->telegram_chat_id)) {
                continue;
            }

            $cacheKey = "tg_teach_remind_{$sched->id}_{$todayDate}";
            if (! $isForce && Cache::has($cacheKey)) {
                continue;
            }

            // Cek apakah guru sedang izin/cuti resmi hari ini
            $isOnPermit = GtkPermit::where('user_id', $teacher->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $todayDate)
                ->whereDate('end_date', '>=', $todayDate)
                ->exists();

            if ($isOnPermit) {
                continue;
            }

            // Cek apakah jam ini sudah digantikan oleh guru inval
            $isInvalidated = PiketInvalClass::where('absent_teacher_id', $teacher->id)
                ->whereDate('created_at', $todayDate)
                ->where('period_start', '<=', $sched->start_period)
                ->where('period_end', '>=', $sched->start_period)
                ->exists();

            if ($isInvalidated) {
                continue;
            }

            $times = LessonSchedule::getTimeForPeriod($sched->start_period, $sched->end_period, $todayDay);
            $startTime = $times['start_time'] ?? $targetPeriods[$sched->start_period]['start'];
            $endTime = $times['end_time'] ?? $targetPeriods[$sched->start_period]['end'];

            if ($isTest) {
                $this->line(" [TEST] Pengingat untuk: {$teacher->name} ({$sched->classroom?->name} - {$sched->subject?->name}) Jam {$startTime}-{$endTime}");
                $sentCount++;

                continue;
            }

            $success = $telegramService->notifyUpcomingTeaching(
                $teacher,
                $sched,
                $startTime,
                $endTime
            );

            if ($success) {
                Cache::put($cacheKey, true, now()->endOfDay());
                $sentCount++;
                $this->info(" [TERKIRIM] {$teacher->name} - {$sched->classroom?->name} (Jam {$startTime})");
            }
        }

        $this->info("Proses selesai. Total {$sentCount} pengingat KBM berhasil diproses.");

        return Command::SUCCESS;
    }
}
