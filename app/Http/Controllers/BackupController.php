<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => $this->isBackupFile($file->getFilename()))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'type' => str_ends_with($file->getFilename(), '.zip') ? 'Storage' : 'Database',
                'created_at' => date('d M Y H:i:s', $file->getMTime()),
                'mtime' => $file->getMTime(),
            ])
            ->sortByDesc('mtime')
            ->values();

        return view('superadmin.backups.index', compact('backups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $options = [
            '--keep-days' => 7,
            '--keep-weekly' => 4,
            '--keep-monthly' => 6,
        ];

        if ($request->boolean('with_storage')) {
            $options['--with-storage'] = true;
        }

        $exitCode = Artisan::call('app:backup', $options);

        if ($exitCode !== 0) {
            return back()->with('error', 'Backup gagal dijalankan: '.trim(Artisan::output()));
        }

        return back()->with('success', 'Backup data berhasil dibuat.');
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->resolveSafeBackupPath($filename);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = $this->resolveSafeBackupPath($filename);
        File::delete($path);

        return back()->with('success', "File backup {$filename} berhasil dihapus.");
    }

    protected function resolveSafeBackupPath(string $filename): string
    {
        $safeName = basename($filename);
        abort_unless($safeName === $filename && $this->isBackupFile($safeName), 404);

        $path = storage_path("app/backups/{$safeName}");
        abort_unless(File::exists($path), 404);

        return $path;
    }

    protected function isBackupFile(string $filename): bool
    {
        return str_ends_with($filename, '.sql.gz')
            || str_ends_with($filename, '.sqlite.gz')
            || str_ends_with($filename, '.zip');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
