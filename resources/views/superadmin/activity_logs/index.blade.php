@extends('layouts.app')

@section('title', 'Log Aktivitas Pengguna - Super Admin')

@section('content')
<div class="row g-2 align-items-center mb-4">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-activity me-2 text-info"></i> Log Aktivitas Pengguna &amp; Audit Trail
        </h2>
        <div class="text-muted small">
            Rekam jejak lengkap seluruh aktivitas autentikasi, perubahan data pokok, pergantian konteks tahun pelajaran, dan tindakan operasional sistem.
        </div>
    </div>
    <div class="col-auto ms-auto d-print-none">
        <button type="button" class="btn btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#modalClearLogs">
            <i class="ti ti-trash me-1"></i> Bersihkan Log Lama
        </button>
    </div>
</div>

<!-- Stat Cards -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar shadow-sm">
                            <i class="ti ti-list-details fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Total Log Tercatat</div>
                        <div class="fw-bold fs-2 text-dark">{{ number_format($totalLogsCount, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar shadow-sm">
                            <i class="ti ti-calendar-event fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Aktivitas Hari Ini</div>
                        <div class="fw-bold fs-2 text-info">{{ number_format($todayLogsCount, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar shadow-sm">
                            <i class="ti ti-login fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Total Sesi Login</div>
                        <div class="fw-bold fs-2 text-success">{{ number_format($loginLogsCount, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar shadow-sm">
                            <i class="ti ti-edit fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Perubahan Data</div>
                        <div class="fw-bold fs-2 text-warning">{{ number_format($changesCount, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('activity-logs.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari deskripsi, nama, IP..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="action" class="form-select">
                    <option value="">-- Semua Aksi --</option>
                    @foreach($distinctActions as $act)
                        <option value="{{ $act }}" {{ $action === $act ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $act)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="user_id" class="form-select">
                    <option value="">-- Semua Pengguna --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ $userId == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->role?->name ?? 'User' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="date_preset" class="form-select">
                    <option value="all" {{ $datePreset === 'all' ? 'selected' : '' }}>Semua Waktu</option>
                    <option value="today" {{ $datePreset === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="7_days" {{ $datePreset === '7_days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                    <option value="30_days" {{ $datePreset === '30_days' ? 'selected' : '' }}>30 Hari Terakhir</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $action || $userId || ($datePreset && $datePreset !== 'all'))
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary" title="Reset">
                        <i class="ti ti-rotate-2"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 170px;">Waktu &amp; Tanggal</th>
                    <th>Pengguna (Aktor)</th>
                    <th>Kategori Aksi</th>
                    <th>Deskripsi Aktivitas</th>
                    <th>Alamat IP</th>
                    <th class="w-1 text-center">Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        <div class="fw-bold text-dark">{{ $log->created_at->translatedFormat('d M Y') }}</div>
                        <small class="text-muted">{{ $log->created_at->format('H:i:s') }} WIB &bull; {{ $log->created_at->diffForHumans() }}</small>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($log->user_name ?: 'U', 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $log->user_name ?: 'Anonim' }}</div>
                                <div class="small text-muted">
                                    <span class="badge bg-secondary-lt" style="font-size: 0.65rem;">{{ $log->role_name ?: 'Sistem' }}</span>
                                    @if($log->user_username) &bull; <code>{{ $log->user_username }}</code> @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $log->action_badge_class }}">
                            {{ $log->action_label }}
                        </span>
                    </td>
                    <td>
                        <div class="text-dark fw-medium" style="max-width: 480px; word-break: break-word;">
                            {{ $log->description }}
                        </div>
                    </td>
                    <td>
                        <div class="small">
                            <code class="text-dark fw-bold">{{ $log->ip_address ?: '-' }}</code>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetailLog{{ $log->id }}" title="Lihat Metadata Lengkap">
                            <i class="ti ti-eye"></i>
                        </button>

                        <!-- Modal Detail Log -->
                        <div class="modal fade" id="modalDetailLog{{ $log->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-start">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">
                                            <i class="ti ti-file-text me-1 text-primary"></i> Detail Log Aktivitas #{{ $log->id }}
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="text-muted small">Aksi / Tindakan</div>
                                            <div class="d-flex align-items-center mt-1">
                                                <span class="badge {{ $log->action_badge_class }} me-2">{{ $log->action_label }}</span>
                                                <span class="text-dark fw-bold">{{ $log->action }}</span>
                                            </div>
                                        </div>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="text-muted small">Deskripsi</div>
                                            <div class="text-dark fw-semibold mt-1">{{ $log->description }}</div>
                                        </div>
                                        <div class="row g-2 mb-3 pb-3 border-bottom">
                                            <div class="col-6">
                                                <div class="text-muted small">Pengguna (Aktor)</div>
                                                <div class="text-dark fw-bold">{{ $log->user_name }} ({{ $log->role_name }})</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">Waktu Kejadian</div>
                                                <div class="text-dark fw-semibold">{{ $log->created_at->translatedFormat('l, d F Y, H:i:s') }} WIB</div>
                                            </div>
                                        </div>
                                        <div class="row g-2 mb-3 pb-3 border-bottom">
                                            <div class="col-6">
                                                <div class="text-muted small">Alamat IP</div>
                                                <code>{{ $log->ip_address ?: '-' }}</code>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">Perangkat / User Agent</div>
                                                <small class="text-muted text-truncate d-block" title="{{ $log->user_agent }}">{{ $log->user_agent ?: '-' }}</small>
                                            </div>
                                        </div>
                                        @if($log->properties && count($log->properties) > 0)
                                        <div>
                                            @if(isset($log->properties['perubahan']) && is_array($log->properties['perubahan']))
                                                <div class="text-muted small mb-2 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                                    <i class="ti ti-history me-1 text-primary"></i> Rincian Perubahan Kolom (Sebelum vs Sesudah):
                                                </div>
                                                <div class="table-responsive rounded border mb-2" style="max-height: 220px; overflow-y: auto;">
                                                    <table class="table table-sm table-vcenter card-table small mb-0">
                                                        <thead class="bg-light text-muted">
                                                            <tr>
                                                                <th style="width: 30%;">Kolom Data</th>
                                                                <th style="width: 35%;" class="text-danger">Nilai Lama (Sebelum)</th>
                                                                <th style="width: 35%;" class="text-success">Nilai Baru (Sesudah)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($log->properties['perubahan'] as $field => $diff)
                                                            <tr>
                                                                <td><code class="text-dark fw-bold">{{ $field }}</code></td>
                                                                <td class="text-danger bg-danger-lt py-1">
                                                                    {{ is_null($diff['lama'] ?? null) ? '(kosong)' : (is_bool($diff['lama']) ? ($diff['lama'] ? 'true' : 'false') : (is_array($diff['lama']) ? json_encode($diff['lama']) : $diff['lama'])) }}
                                                                </td>
                                                                <td class="text-success bg-success-lt py-1 fw-semibold">
                                                                    {{ is_null($diff['baru'] ?? null) ? '(kosong)' : (is_bool($diff['baru']) ? ($diff['baru'] ? 'true' : 'false') : (is_array($diff['baru']) ? json_encode($diff['baru']) : $diff['baru'])) }}
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @elseif(isset($log->properties['attributes']) && is_array($log->properties['attributes']))
                                                <div class="text-muted small mb-2 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                                    <i class="ti ti-list-check me-1 text-info"></i> Snapshot Data Terkait:
                                                </div>
                                                <div class="table-responsive rounded border mb-2" style="max-height: 200px; overflow-y: auto;">
                                                    <table class="table table-sm table-vcenter card-table small mb-0">
                                                        <thead class="bg-light text-muted">
                                                            <tr>
                                                                <th style="width: 35%;">Kolom</th>
                                                                <th>Nilai</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($log->properties['attributes'] as $attrKey => $attrVal)
                                                            <tr>
                                                                <td><code class="text-dark">{{ $attrKey }}</code></td>
                                                                <td class="text-dark fw-medium">{{ is_null($attrVal) ? '(kosong)' : (is_bool($attrVal) ? ($attrVal ? 'true' : 'false') : (is_array($attrVal) ? json_encode($attrVal) : $attrVal)) }}</td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="text-muted small mb-1 fw-bold"><i class="ti ti-code me-1 text-secondary"></i> Parameter / Nilai Tambahan:</div>
                                                <pre class="bg-light p-2 rounded small text-dark border mb-0" style="max-height: 180px; overflow-y: auto;">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="ti ti-file-off fs-1 mb-2 d-block"></i>
                        Belum ada catatan log aktivitas yang sesuai dengan filter pencarian.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $logs->links() }}
    </div>
    @endif
</div>

<!-- Modal Bersihkan Log -->
<div class="modal fade" id="modalClearLogs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('activity-logs.clear') }}" method="POST" class="modal-content text-start">
            @csrf
            @method('DELETE')
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold text-white">
                    <i class="ti ti-alert-triangle me-1"></i> Bersihkan Log Aktivitas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-dark mb-3">
                    Pilih batas waktu catatan log yang ingin dibersihkan dari basis data untuk menghemat ruang penyimpanan:
                </p>
                <div class="mb-3">
                    <label class="form-label required">Rentang Pembersihan</label>
                    <select name="range" class="form-select" required>
                        <option value="30">Hapus catatan yang lebih lama dari 30 hari</option>
                        <option value="90">Hapus catatan yang lebih lama dari 90 hari</option>
                        <option value="all">Hapus SELURUH catatan log aktivitas (Reset Total)</option>
                    </select>
                </div>
                <div class="alert alert-warning py-2 mb-0 small">
                    <i class="ti ti-info-circle me-1"></i> Tindakan pembersihan ini tidak dapat dibatalkan (permanen).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm fw-bold">
                    <i class="ti ti-trash me-1"></i> Ya, Bersihkan Log
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
