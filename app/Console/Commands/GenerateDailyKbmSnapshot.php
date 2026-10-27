<?php

namespace App\Console\Commands;

use App\Services\AcademicYearContext;
use App\Services\DailyKbmSnapshotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyKbmSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kbm:generate-snapshot {--date= : Tanggal spesifik format YYYY-MM-DD} {--days=1 : Jumlah hari ke depan untuk di-generate} {--force : Paksa generate ulang} {--academic_year= : Tahun ajaran target spesifik}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate atau perbarui snapshot KBM harian (Daily KBM Roster & Schedule Targets)';

    /**
     * Execute the console command.
     */
    public function handle(DailyKbmSnapshotService $service): int
    {
        $startDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $academicYear = $this->option('academic_year');

        if (! empty($academicYear)) {
            $academicYear = app(AcademicYearContext::class)->resolveExplicitYear($academicYear);
        }

        $this->info("Memulai generate snapshot KBM untuk {$days} hari mulai {$startDate->toDateString()}...");

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $service->getOrGenerateSnapshot($currentDate, $force, $academicYear);
            $this->line(" - [{$currentDate->toDateString()}] {$currentDate->isoFormat('dddd')} OK.");
        }

        $this->info('Snapshot KBM harian berhasil dibuat.');

        return Command::SUCCESS;
    }
}
