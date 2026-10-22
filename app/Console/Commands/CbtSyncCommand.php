<?php

namespace App\Console\Commands;

use App\Services\CbtSyncService;
use Illuminate\Console\Command;

class CbtSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cbt:sync 
                            {--type=all : Jenis sinkronisasi (all, tp, semester, jurusan, mapel, guru, kelas, siswa)}
                            {--academic-year= : ID Tahun Pelajaran di SIAP (opsional, default: aktif)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data pokok SIAP ke database GarudaCBT';

    /**
     * Execute the console command.
     */
    public function handle(CbtSyncService $syncService): int
    {
        $this->info('Memeriksa koneksi database GarudaCBT...');

        if (! $syncService->testConnection()) {
            $this->error('Gagal terhubung ke database CBT. Pastikan database db_cbt aktif di MySQL.');

            return self::FAILURE;
        }

        $type = $this->option('type') ?? 'all';
        $academicYearId = $this->option('academic-year') ? (int) $this->option('academic-year') : null;

        $this->info("Memulai sinkronisasi tipe: [{$type}]...");

        $startTime = microtime(true);

        switch ($type) {
            case 'tp':
                $result = $syncService->syncTahunPelajaran();
                $this->table(['Created', 'Updated', 'Total'], [[$result['created'], $result['updated'], $result['total']]]);
                break;

            case 'semester':
                $result = $syncService->syncSemester();
                $this->info("Semester aktif CBT diubah ke: {$result['active_semester']} (id_smt: {$result['active_id_smt']})");
                break;

            case 'jurusan':
                $result = $syncService->syncJurusan();
                $this->table(['Created', 'Updated', 'Total'], [[$result['created'], $result['updated'], $result['total']]]);
                break;

            case 'mapel':
                $result = $syncService->syncMapel();
                $this->table(['Created', 'Updated', 'Total'], [[$result['created'], $result['updated'], $result['total']]]);
                break;

            case 'guru':
                $result = $syncService->syncGuru();
                $this->table(['Created', 'Updated', 'Total'], [[$result['created'], $result['updated'], $result['total']]]);
                break;

            case 'kelas':
                $result = $syncService->syncKelas($academicYearId);
                $this->table(['Created', 'Updated', 'Total', 'TP'], [[$result['created'], $result['updated'], $result['total'], $result['tp']]]);
                break;

            case 'siswa':
                $result = $syncService->syncSiswa($academicYearId);
                $this->table(['Created', 'Updated', 'Assigned Kelas', 'Total Siswa Aktif'], [[$result['created'], $result['updated'], $result['assigned_kelas'], $result['total_active']]]);
                break;

            case 'all':
            default:
                $result = $syncService->syncAll($academicYearId);
                $this->info('Sinkronisasi selesai dengan sukses!');
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                break;
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Selesai dalam {$duration} detik.");

        return self::SUCCESS;
    }
}
