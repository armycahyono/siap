<?php

namespace App\Console\Commands;

use App\Models\SchoolSetting;
use App\Services\BkAssignmentSyncService;
use Illuminate\Console\Command;

class SyncBkAssignmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bk:sync-assignments {academic_year? : Tahun pelajaran yang akan disinkronkan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronkan kelas binaan Guru BK secara otomatis dari penugasan jam mata pelajaran BK';

    /**
     * Execute the console command.
     */
    public function handle(BkAssignmentSyncService $syncService): int
    {
        $academicYear = $this->argument('academic_year') ?: SchoolSetting::getMasterYear();

        $this->info("Menyinkronkan penugasan kelas Guru BK untuk Tahun Pelajaran: {$academicYear}...");

        $count = $syncService->syncFromSubjectAssignments($academicYear);

        $this->info("Berhasil menyinkronkan {$count} kelas binaan Guru BK dari penugasan mata pelajaran BK.");

        return Command::SUCCESS;
    }
}
