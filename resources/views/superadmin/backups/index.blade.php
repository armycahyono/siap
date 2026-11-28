@extends('layouts.app')

@section('title', 'Backup Data Sistem')

@section('content')
@include('layouts._hero_header', [
    'title' => 'Backup Data Sistem',
    'subtitle' => 'Buat cadangan database MySQL (.sql.gz) dan arsip storage, serta unduh file backup kapan saja.',
    'icon' => 'ti ti-database-export',
    'category' => 'SUPER ADMIN & PEMELIHARAAN',
])

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="card-title mb-1">Cadangan Otomatis &amp; Manual</h4>
            <div class="text-muted small">
                Jadwal otomatis berjalan setiap hari pukul <strong>02:00 WIB</strong>. Retensi: 7 hari terakhir, 4 minggu (Minggu), 6 bulan (tanggal 1).
            </div>
        </div>
        <form method="POST" action="{{ route('backups.store') }}" class="d-flex flex-wrap align-items-center gap-3">
            @csrf
            <label class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" name="with_storage" value="1">
                <span class="form-check-label small">Sertakan file storage</span>
            </label>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-database-export me-1"></i> Backup Sekarang
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Daftar File Backup ({{ $backups->count() }})</h4>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Nama File</th>
                    <th>Jenis</th>
                    <th>Ukuran</th>
                    <th>Tanggal Dibuat</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                    <tr>
                        <td class="fw-medium"><code>{{ $backup['name'] }}</code></td>
                        <td>
                            <span class="badge {{ $backup['type'] === 'Database' ? 'bg-blue-lt text-blue' : 'bg-purple-lt text-purple' }}">
                                {{ $backup['type'] }}
                            </span>
                        </td>
                        <td>{{ $backup['size'] }}</td>
                        <td class="text-muted small">{{ $backup['created_at'] }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-download me-1"></i> Unduh
                                </a>
                                <form method="POST" action="{{ route('backups.destroy', $backup['name']) }}" onsubmit="return confirm('Hapus file backup ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            Belum ada file backup. Klik <strong>Backup Sekarang</strong> untuk membuat cadangan pertama.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
