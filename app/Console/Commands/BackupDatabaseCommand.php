<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup
        {--keep-days=7 : Simpan backup harian selama N hari}
        {--keep-weekly=4 : Simpan backup mingguan (hari Minggu) selama N minggu}
        {--keep-monthly=6 : Simpan backup bulanan (tanggal 1) selama N bulan}
        {--with-storage : Sertakan arsip folder storage/app (kecuali backups)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup database (dan opsional storage) ke storage/app/backups dengan retensi otomatis';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $appSlug = Str::slug((string) config('app.name', 'app'), '_');
        if ($appSlug === '') {
            $appSlug = 'app';
        }

        $timestamp = now()->format('Y-m-d_His');
        $dbResult = $this->backupDatabase($backupDir, $appSlug, $timestamp);

        if ($dbResult !== self::SUCCESS) {
            return $dbResult;
        }

        if ($this->option('with-storage')) {
            $this->backupStorage($backupDir, $appSlug, $timestamp);
        }

        $deletedCount = $this->applyRetentionPolicy($backupDir);
        if ($deletedCount > 0) {
            $this->info("Retensi otomatis: {$deletedCount} file backup lama dihapus.");
        }

        return self::SUCCESS;
    }

    protected function backupDatabase(string $backupDir, string $appSlug, string $timestamp): int
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        $driver = (string) ($connection['driver'] ?? '');

        if ($driver === 'sqlite') {
            $dbPath = (string) ($connection['database'] ?? '');
            $targetPath = "{$backupDir}/{$appSlug}_{$timestamp}.sqlite.gz";
            $content = ($dbPath !== '' && $dbPath !== ':memory:' && File::exists($dbPath))
                ? (string) File::get($dbPath)
                : "-- SQLite in-memory snapshot {$timestamp}\n";

            if (! $this->writeGzipFile($targetPath, $content)) {
                $this->error('Gagal menulis file backup SQLite.');

                return self::FAILURE;
            }

            $this->info('Backup database selesai: '.basename($targetPath));

            return self::SUCCESS;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error("Driver database '{$driver}' belum didukung untuk backup otomatis.");

            return self::FAILURE;
        }

        $mysqldump = $this->resolveMysqldumpBinary();
        if (! $mysqldump) {
            $this->error('Binary mysqldump tidak ditemukan di PATH maupun Laragon.');

            return self::FAILURE;
        }

        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');

        $rawSqlPath = "{$backupDir}/{$appSlug}_{$timestamp}.sql";
        $gzPath = "{$rawSqlPath}.gz";

        $command = [
            $mysqldump,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--result-file='.$rawSqlPath,
            $database,
        ];

        $process = new Process($command, base_path(), ['MYSQL_PWD' => $password], null, 300);
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($rawSqlPath) || File::size($rawSqlPath) === 0) {
            File::delete($rawSqlPath);
            $this->error('Gagal menjalankan mysqldump: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        if (! $this->compressFileToGzip($rawSqlPath, $gzPath)) {
            File::delete($rawSqlPath);
            $this->error('Gagal mengompresi hasil dump ke .sql.gz.');

            return self::FAILURE;
        }

        File::delete($rawSqlPath);
        $this->info('Backup database selesai: '.basename($gzPath));

        return self::SUCCESS;
    }

    protected function backupStorage(string $backupDir, string $appSlug, string $timestamp): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->warn('Ekstensi ZipArchive tidak tersedia; backup storage dilewati.');

            return;
        }

        $storageApp = storage_path('app');
        $zipPath = "{$backupDir}/{$appSlug}_storage_{$timestamp}.zip";
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->warn('Gagal membuat file arsip storage.');

            return;
        }

        $excludedPrefix = rtrim(str_replace('\\', '/', $backupDir), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storageApp, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $file->getRealPath());
            if (str_starts_with($fullPath, $excludedPrefix)) {
                continue;
            }

            $relativePath = ltrim(substr($fullPath, strlen(str_replace('\\', '/', $storageApp))), '/');
            $zip->addFile($fullPath, $relativePath);
        }

        $zip->close();
        $this->info('Backup storage selesai: '.basename($zipPath));
    }

    protected function applyRetentionPolicy(string $backupDir): int
    {
        $keepDays = max(1, (int) $this->option('keep-days'));
        $keepWeekly = max(0, (int) $this->option('keep-weekly'));
        $keepMonthly = max(0, (int) $this->option('keep-monthly'));

        $now = now();
        $dailyCutoff = $now->copy()->subDays($keepDays);
        $weeklyCutoff = $now->copy()->subWeeks($keepWeekly);
        $monthlyCutoff = $now->copy()->subMonths($keepMonthly);

        $deleted = 0;

        foreach (File::files($backupDir) as $file) {
            $name = $file->getFilename();
            if (! str_ends_with($name, '.sql.gz') && ! str_ends_with($name, '.sqlite.gz') && ! str_ends_with($name, '.zip')) {
                continue;
            }

            $fileDate = $this->extractDateFromFilename($name) ?? Carbon::createFromTimestamp($file->getMTime());

            $keep = $fileDate->greaterThanOrEqualTo($dailyCutoff)
                || ($keepWeekly > 0 && $fileDate->isSunday() && $fileDate->greaterThanOrEqualTo($weeklyCutoff))
                || ($keepMonthly > 0 && $fileDate->day === 1 && $fileDate->greaterThanOrEqualTo($monthlyCutoff));

            if (! $keep) {
                File::delete($file->getRealPath());
                $deleted++;
            }
        }

        return $deleted;
    }

    protected function extractDateFromFilename(string $filename): ?Carbon
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{6})/', $filename, $matches)) {
            return Carbon::createFromFormat('Y-m-d_His', "{$matches[1]}_{$matches[2]}") ?: null;
        }

        return null;
    }

    protected function resolveMysqldumpBinary(): ?string
    {
        $candidates = ['mysqldump'];

        if (PHP_OS_FAMILY === 'Windows') {
            $laragonMatches = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [];
            rsort($laragonMatches);
            $candidates = array_merge($laragonMatches, $candidates);
        } else {
            $candidates = array_merge(['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'], $candidates);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== 'mysqldump' && File::exists($candidate)) {
                return $candidate;
            }

            if ($candidate === 'mysqldump') {
                $check = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'mysqldump']);
                $check->run();
                if ($check->isSuccessful()) {
                    $resolved = trim(strtok($check->getOutput(), "\r\n"));
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            }
        }

        return null;
    }

    protected function compressFileToGzip(string $sourcePath, string $targetPath): bool
    {
        $in = fopen($sourcePath, 'rb');
        $out = gzopen($targetPath, 'wb9');

        if (! $in || ! $out) {
            if ($in) {
                fclose($in);
            }
            if ($out) {
                gzclose($out);
            }

            return false;
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 512);
            if ($chunk !== false) {
                gzwrite($out, $chunk);
            }
        }

        fclose($in);
        gzclose($out);

        return File::exists($targetPath);
    }

    protected function writeGzipFile(string $targetPath, string $content): bool
    {
        $out = gzopen($targetPath, 'wb9');
        if (! $out) {
            return false;
        }

        gzwrite($out, $content);
        gzclose($out);

        return File::exists($targetPath);
    }
}
