<?php

namespace App\Console\Commands;

use App\Models\AppFeedback;
use App\Models\AttendanceLog;
use App\Models\GtkPermit;
use App\Models\PiketIncident;
use App\Models\PiketStudentPermit;
use App\Models\SchoolStakeholder;
use App\Models\StructuralPosition;
use App\Models\StudentMutation;
use App\Models\Supervision;
use App\Models\SupervisionPhoto;
use App\Models\TimetableUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateSensitiveFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:migrate-sensitive
                            {--dry-run : Jalankan simulasi pemeriksaan tanpa menyalin atau menghapus berkas}
                            {--delete-source : Hapus berkas sumber dari disk publik setelah salinan ke privat terverifikasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrasikan berkas sensitif dari disk public ke disk local (private) secara aman dan terverifikasi.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');

        $this->info('=== PEMERIKSAAN & MIGRASI BERKAS SENSITIF KE DISK PRIVAT ===');
        if ($isDryRun) {
            $this->warn('[MODE SIMULASI (DRY-RUN)] Tidak ada berkas yang akan disalin atau dihapus.');
        } elseif ($deleteSource) {
            $this->alert('[PERINGATAN] Opsi --delete-source aktif: Berkas publik akan dihapus setelah diverifikasi di disk privat.');
        } else {
            $this->info('[MODE SALIN AMAN] Berkas akan disalin ke privat, salinan publik tetap dipertahankan sampai diverifikasi manual.');
        }

        $results = [
            'total_inspected' => 0,
            'found_in_public' => 0,
            'already_in_private' => 0,
            'migrated' => 0,
            'source_deleted' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'failed' => 0,
        ];

        // 1. Dokumen Izin/Cuti GTK
        $this->line('');
        $this->info('1. Memeriksa Dokumen Izin GTK (GtkPermit)...');
        $gtkPermits = GtkPermit::whereNotNull('document_path')->get(['id', 'document_path']);
        foreach ($gtkPermits as $permit) {
            $this->processFileMigration($permit->document_path, "GtkPermit #{$permit->id}", $results, $isDryRun, $deleteSource);
        }

        // 2. Bukti Foto Insiden Piket
        $this->line('');
        $this->info('2. Memeriksa Bukti Foto Insiden Piket (PiketIncident)...');
        $incidents = PiketIncident::whereNotNull('evidence_photo')->get(['id', 'evidence_photo']);
        foreach ($incidents as $inc) {
            $this->processFileMigration($inc->evidence_photo, "PiketIncident #{$inc->id}", $results, $isDryRun, $deleteSource);
        }

        // 3. Lampiran Izin Siswa
        $this->line('');
        $this->info('3. Memeriksa Lampiran Izin Siswa (PiketStudentPermit)...');
        $studentPermits = PiketStudentPermit::whereNotNull('attachment_path')->get(['id', 'attachment_path']);
        foreach ($studentPermits as $sp) {
            $this->processFileMigration($sp->attachment_path, "PiketStudentPermit #{$sp->id}", $results, $isDryRun, $deleteSource);
        }

        // 4. Berkas Mutasi Siswa
        $this->line('');
        $this->info('4. Memeriksa Berkas Mutasi Siswa (StudentMutation)...');
        $mutations = StudentMutation::whereNotNull('attachment_path')->get(['id', 'attachment_path']);
        foreach ($mutations as $mut) {
            $this->processFileMigration($mut->attachment_path, "StudentMutation #{$mut->id}", $results, $isDryRun, $deleteSource);
        }

        // 5. Modul Ajar / RPP Supervisi
        $this->line('');
        $this->info('5. Memeriksa Modul Ajar / RPP Supervisi (Supervision)...');
        $supervisions = Supervision::whereNotNull('rpp_file_path')->get(['id', 'rpp_file_path']);
        foreach ($supervisions as $sup) {
            $this->processFileMigration($sup->rpp_file_path, "Supervision RPP #{$sup->id}", $results, $isDryRun, $deleteSource);
        }

        // 6. Foto Dokumentasi Supervisi
        $this->line('');
        $this->info('6. Memeriksa Foto Dokumentasi Supervisi (SupervisionPhoto)...');
        $supervisionPhotos = SupervisionPhoto::whereNotNull('file_path')->get(['id', 'file_path']);
        foreach ($supervisionPhotos as $sph) {
            $this->processFileMigration($sph->file_path, "SupervisionPhoto #{$sph->id}", $results, $isDryRun, $deleteSource);
        }

        // 7. Lampiran Catatan Perbaikan (AppFeedback)
        $this->line('');
        $this->info('7. Memeriksa Lampiran Masukan Pengguna (AppFeedback)...');
        $feedbacks = AppFeedback::whereNotNull('attachment_path')->get(['id', 'attachment_path']);
        foreach ($feedbacks as $fb) {
            $this->processFileMigration($fb->attachment_path, "AppFeedback #{$fb->id}", $results, $isDryRun, $deleteSource);
        }

        // 8. Foto Selfie Geotag Presensi (AttendanceLog)
        $this->line('');
        $this->info('8. Memeriksa Foto Selfie Presensi Geotag (AttendanceLog)...');
        $logs = AttendanceLog::where('verify_type', 'geotag')->get(['id', 'raw_payload']);
        foreach ($logs as $log) {
            $raw = is_array($log->raw_payload) ? $log->raw_payload : json_decode($log->raw_payload ?? '[]', true);
            $selfiePath = $raw['selfie_path'] ?? null;
            if (! $selfiePath && ! empty($raw['selfie_url']) && is_string($raw['selfie_url'])) {
                $parsed = parse_url($raw['selfie_url'], PHP_URL_PATH);
                if ($parsed && str_contains($parsed, 'attendance/geotag/')) {
                    $selfiePath = substr($parsed, strpos($parsed, 'attendance/geotag/'));
                }
            }
            if ($selfiePath) {
                $this->processFileMigration(
                    $selfiePath,
                    "AttendanceLog Selfie #{$log->id}",
                    $results,
                    $isDryRun,
                    $deleteSource,
                    function () use ($log, $selfiePath) {
                        $currentRaw = is_array($log->raw_payload) ? $log->raw_payload : json_decode($log->raw_payload ?? '[]', true);
                        if (empty($currentRaw['selfie_path'])) {
                            $currentRaw['selfie_path'] = $selfiePath;
                            $currentRaw['selfie_url'] = route('attendance.geotag.selfie', $log);
                            $log->update(['raw_payload' => json_encode($currentRaw, JSON_UNESCAPED_SLASHES)]);
                        }
                    }
                );
            }
        }

        // 9. Berkas Jadwal Pelajaran Kurikulum (TimetableUpload)
        $this->line('');
        $this->info('9. Memeriksa Berkas Jadwal Pelajaran (TimetableUpload)...');
        $timetables = TimetableUpload::whereNotNull('file_path')->get(['id', 'file_path']);
        foreach ($timetables as $tt) {
            $this->processFileMigration($tt->file_path, "TimetableUpload #{$tt->id}", $results, $isDryRun, $deleteSource);
        }

        // 10. Tanda Tangan Jabatan Struktural (StructuralPosition)
        $this->line('');
        $this->info('10. Memeriksa Tanda Tangan Jabatan Struktural (StructuralPosition)...');
        $structuralPositions = StructuralPosition::whereNotNull('signature_image_path')->get(['id', 'name', 'signature_image_path']);
        foreach ($structuralPositions as $sp) {
            $this->processFileMigration($sp->signature_image_path, "StructuralPosition #{$sp->id} ({$sp->name})", $results, $isDryRun, $deleteSource);
        }

        // 11. Tanda Tangan Pejabat Sekolah (SchoolStakeholder)
        $this->line('');
        $this->info('11. Memeriksa Tanda Tangan Pejabat Sekolah (SchoolStakeholder)...');
        $stakeholders = SchoolStakeholder::whereNotNull('signature_image_path')->get(['id', 'name', 'signature_image_path']);
        foreach ($stakeholders as $sh) {
            $this->processFileMigration($sh->signature_image_path, "SchoolStakeholder #{$sh->id} ({$sh->name})", $results, $isDryRun, $deleteSource);
        }

        // 12. Pengecekan Berkas Sisa pada Direktori signatures/ Disk Publik
        $this->line('');
        $this->info('12. Memeriksa Sisa Berkas signatures/ pada Disk Publik...');
        if (Storage::disk('public')->exists('signatures')) {
            $publicSigFiles = Storage::disk('public')->files('signatures');
            foreach ($publicSigFiles as $sigFile) {
                $this->processFileMigration($sigFile, "Public Signature File {$sigFile}", $results, $isDryRun, $deleteSource);
            }
        }

        // Ringkasan Tabel
        $this->line('');
        $this->info('=== RINGKASAN HASIL MIGRASI ===');
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Berkas Diperiksa', $results['total_inspected']],
                ['Ditemukan di Disk Public', $results['found_in_public']],
                ['Sudah Ada di Disk Private (Identik)', $results['already_in_private']],
                ['Berhasil Dimigrasikan ke Private', $results['migrated']],
                ['Berkas Publik Dihapus (--delete-source)', $results['source_deleted']],
                ['Berkas Tidak Ditemukan di Server', $results['missing']],
                ['Konflik Checksum (Hash Berbeda)', $results['conflicts']],
                ['Gagal / Error', $results['failed']],
            ]
        );

        if ($results['conflicts'] > 0 || $results['failed'] > 0) {
            $this->error('Terdapat konflik integritas atau kegagalan saat migrasi. Periksa rincian di atas.');

            return self::FAILURE;
        }

        $this->info('Pemeriksaan dan migrasi berkas sensitif selesai dengan sukses.');

        return self::SUCCESS;
    }

    /**
     * Proses pemindahan satu berkas dengan stream dan verifikasi integritas SHA-256.
     */
    protected function processFileMigration(
        string $relativePath,
        string $context,
        array &$results,
        bool $isDryRun,
        bool $deleteSource,
        ?callable $onSuccessCallback = null
    ): void {
        $results['total_inspected']++;
        $relativePath = ltrim(str_replace(['../', '..\\'], '', $relativePath), '/');

        $existsInPrivate = Storage::disk('local')->exists($relativePath);
        $existsInPublic = Storage::disk('public')->exists($relativePath);

        if ($existsInPrivate && ! $existsInPublic) {
            $results['already_in_private']++;
            if ($onSuccessCallback && ! $isDryRun) {
                $onSuccessCallback();
            }

            return;
        }

        if (! $existsInPublic && ! $existsInPrivate) {
            $results['missing']++;
            $this->line("  - [TIDAK DITEMUKAN] {$context}: {$relativePath}");

            return;
        }

        if ($existsInPublic) {
            $results['found_in_public']++;

            if ($existsInPrivate) {
                // Verifikasi apakah berkas privat yang sudah ada benar-benar identik secara SHA-256
                $srcPath = Storage::disk('public')->path($relativePath);
                $dstPath = Storage::disk('local')->path($relativePath);

                $srcHash = file_exists($srcPath) ? hash_file('sha256', $srcPath) : null;
                $dstHash = file_exists($dstPath) ? hash_file('sha256', $dstPath) : null;

                if ($srcHash !== null && $dstHash !== null && $srcHash === $dstHash) {
                    $results['already_in_private']++;
                    if ($onSuccessCallback && ! $isDryRun) {
                        $onSuccessCallback();
                    }

                    if (! $isDryRun && $deleteSource) {
                        Storage::disk('public')->delete($relativePath);
                        $results['source_deleted']++;
                        $this->line("  - [BERSIHKAN SUMBER] Salinan publik {$context} dihapus (SHA-256 cocok).");
                    }
                } else {
                    $results['conflicts']++;
                    $this->warn("  - [KONFLIK INTEGRITAS] {$context}: Berkas ada di kedua disk tetapi SHA-256 berbeda! Sumber publik tidak dihapus.");
                }

                return;
            }

            if ($isDryRun) {
                $this->line("  - [SIMULASI PINDAH] {$context}: {$relativePath}");
                $results['migrated']++;

                return;
            }

            try {
                // 1. Validasi keterbacaan sumber via Stream
                $readStream = Storage::disk('public')->readStream($relativePath);
                if (! is_resource($readStream)) {
                    $results['failed']++;
                    $this->error("  - [ERROR STREAM BACA] {$context}: Tidak dapat membuka stream baca dari disk public.");

                    return;
                }

                // 2. Tulis ke disk privat via Stream (hemat memori PHP)
                $writeSuccess = Storage::disk('local')->writeStream($relativePath, $readStream);
                if (is_resource($readStream)) {
                    fclose($readStream);
                }

                if (! $writeSuccess || ! Storage::disk('local')->exists($relativePath)) {
                    $results['failed']++;
                    $this->error("  - [ERROR TULIS] {$context}: Gagal menulis stream ke disk privat.");
                    Storage::disk('local')->delete($relativePath);

                    return;
                }

                // 3. Verifikasi Ukuran & Checksum SHA-256
                $srcPath = Storage::disk('public')->path($relativePath);
                $dstPath = Storage::disk('local')->path($relativePath);

                $sourceSize = Storage::disk('public')->size($relativePath);
                $destSize = Storage::disk('local')->size($relativePath);

                $sourceHash = hash_file('sha256', $srcPath);
                $destHash = hash_file('sha256', $dstPath);

                if ($sourceSize !== $destSize || $sourceHash !== $destHash) {
                    $results['failed']++;
                    $this->error("  - [VERIFIKASI GAGAL] {$context}: Ukuran ({$sourceSize} vs {$destSize}) atau SHA-256 tidak cocok.");
                    Storage::disk('local')->delete($relativePath);

                    return;
                }

                $results['migrated']++;
                $this->line("  - [SUKSES MIGRASI] {$context} -> disk privat (SHA-256: ".substr($destHash, 0, 12).'...).');

                if ($onSuccessCallback) {
                    $onSuccessCallback();
                }

                if ($deleteSource) {
                    Storage::disk('public')->delete($relativePath);
                    $results['source_deleted']++;
                    $this->line("  - [HAPUS SUMBER] Salinan publik {$context} berhasil dihapus.");
                }
            } catch (\Throwable $e) {
                $results['failed']++;
                $this->error("  - [EXCEPTION] {$context}: {$e->getMessage()}");
            }
        }
    }
}
