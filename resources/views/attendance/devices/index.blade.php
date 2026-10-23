@extends('layouts.app')

@section('title', 'Manajemen & Konfigurasi Mesin Absensi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $devActions = '<a href="' . route('attendance.devices.biometrics') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-fingerprint me-1 text-cyan"></i> Bank Master Biometrik</a> ' .
        '<button type="button" class="btn btn-sm btn-outline-light shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-sync-users"><i class="ti ti-cloud-upload me-1 text-info"></i> Sinkron Data ke Mesin</button> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-device"><i class="ti ti-plus me-1"></i> Tambah Mesin Baru</button>';
@endphp
<div class="container-xl px-0 mb-3">
    @include('layouts._hero_header', [
        'title' => 'Manajemen Mesin Absensi',
        'subtitle' => 'Konfigurasi perangkat keras presensi biometrik (Fingerprint & Face Recognition) dan server push ADMS.',
        'icon' => 'ti ti-devices',
        'category' => 'INFRASTRUKTUR & PERANGKAT KERAS',
        'actions' => $devActions
    ])
</div>

<div class="page-body">
    <div class="container-xl">
        <!-- Kartu Statistik Cepat Perangkat (Kompak & Hemat Vertikal) -->
        <div class="row row-cards mb-3 g-2">
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-primary h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar avatar-sm rounded">
                                    <i class="ti ti-devices fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">
                                    {{ $devices->count() }}
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Total Mesin Absensi</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-success h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-success text-white avatar avatar-sm rounded">
                                    <i class="ti ti-wifi fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-success lh-1">
                                    {{ $devices->where('status', 'online')->count() }}
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Mesin Status Online</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <a href="{{ route('attendance.devices.biometrics') }}" class="card card-sm card-link shadow-sm border-0 border-start border-3 border-indigo text-decoration-none h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-indigo text-white avatar avatar-sm rounded">
                                    <i class="ti ti-fingerprint fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-indigo lh-1">
                                    {{ $savedBiometricsCount }}
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Master Biometrik di Server</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 {{ $pendingCommands > 0 ? 'border-warning' : 'border-secondary' }} h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="{{ $pendingCommands > 0 ? 'bg-warning' : 'bg-secondary' }} text-white avatar avatar-sm rounded">
                                    <i class="ti ti-clock fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">
                                    {{ $pendingCommands }}
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Antrean Perintah Tertunda</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(isset($pendingDevices) && $pendingDevices->isNotEmpty())
            <!-- PERANGKAT BARU MENUNGGU VERIFIKASI ADMIN -->
            <div class="card shadow-sm border-0 border-start border-4 border-warning mb-4">
                <div class="card-header bg-warning-lt d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-warning text-white rounded"><i class="ti ti-shield-alert"></i></span>
                        <div>
                            <h3 class="card-title text-warning-emphasis mb-0 fs-4 fw-bold">
                                Perangkat Baru Ditemukan ({{ $pendingDevices->count() }} Menunggu Verifikasi Admin)
                            </h3>
                            <div class="text-secondary small">
                                Perangkat berikut terdeteksi menghubungi server ADMS tetapi belum diverifikasi. Log presensi tidak akan diproses sebelum disetujui admin.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th>Identitas Terdeteksi</th>
                                    <th>Alamat IP & Port</th>
                                    <th>Serial Number (SN)</th>
                                    <th>Terdeteksi Sejak</th>
                                    <th class="text-end" style="min-width: 220px;">Tindakan Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingDevices as $pDev)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="avatar avatar-sm rounded-circle me-2 bg-warning-lt text-warning">
                                                    <i class="ti ti-device-watch fs-3"></i>
                                                </span>
                                                <div>
                                                    <strong class="text-dark">{{ $pDev->name }}</strong>
                                                    <span class="badge bg-warning-lt text-warning ms-1">Menunggu Verifikasi</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="fw-bold">{{ $pDev->ip_address }}:{{ $pDev->port }}</code>
                                        </td>
                                        <td>
                                            @if($pDev->device_sn)
                                                <span class="badge bg-blue-lt font-monospace px-2 py-1">{{ $pDev->device_sn }}</span>
                                            @else
                                                <span class="badge bg-secondary-lt text-muted font-monospace">Belum Ada SN</span>
                                            @endif
                                        </td>
                                        <td class="text-secondary small">
                                            @if($pDev->created_at)
                                                <span title="{{ $pDev->created_at->format('d M Y H:i:s') }}">
                                                    <i class="ti ti-clock me-1"></i>{{ $pDev->created_at->diffForHumans() }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-list justify-content-end">
                                                <!-- Tombol Modal Verifikasi -->
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modal-approve-device-{{ $pDev->id }}">
                                                    <i class="ti ti-check me-1"></i> Verifikasi & Aktifkan
                                                </button>
                                                <!-- Tombol Tolak / Hapus -->
                                                <form action="{{ route('attendance.devices.destroy', $pDev->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tolak dan hapus perangkat ini dari antrean sistem?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Tolak & Hapus Perangkat">
                                                        <i class="ti ti-trash me-1"></i> Tolak
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- DAFTAR SEMUA MESIN ABSENSI TERHUBUNG -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-xs bg-primary text-white rounded"><i class="ti ti-list"></i></span>
                    <h3 class="card-title mb-0 fs-4 fw-bold">
                        Daftar Perangkat Mesin Absensi ({{ $devices->count() }} Mesin)
                    </h3>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-add-device">
                        <i class="ti ti-plus me-1"></i> Tambah Mesin Baru
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>Mesin Absensi</th>
                                <th>Alamat IP & Port</th>
                                <th>Serial Number (SN)</th>
                                <th>Biometrik Terekam</th>
                                <th>Aktivitas Terakhir</th>
                                <th class="text-end" style="min-width: 250px;">Aksi Perangkat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($devices as $dev)
                                @php
                                    $bioStat = $dev->device_sn ? ($biometricUsersPerDevice->get($dev->device_sn) ?? null) : null;
                                    $bioUserCount = $bioStat ? $bioStat->user_count : 0;
                                    $bioTemplateCount = $bioStat ? $bioStat->template_count : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($dev->status === 'online')
                                                <span class="status-dot status-dot-animated bg-success me-2 fs-3" title="Online (Terhubung)"></span>
                                            @else
                                                <span class="status-dot bg-secondary me-2 fs-3" title="Offline (Terputus)"></span>
                                            @endif
                                            <div>
                                                <strong class="text-dark">{{ $dev->name }}</strong>
                                                <div class="text-secondary small">
                                                    @if(str_contains(strtolower($dev->name), 'revo') || $dev->port == 5005)
                                                        Fingerspot FK-Web
                                                    @else
                                                        Solution / ZKTeco (ADMS & Port 4370)
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="fw-bold">{{ $dev->ip_address }}:{{ $dev->port }}</code>
                                    </td>
                                    <td>
                                        @if($dev->device_sn)
                                            <span class="badge bg-blue-lt font-monospace px-2 py-1">{{ $dev->device_sn }}</span>
                                        @else
                                            <span class="badge bg-secondary-lt text-muted font-monospace">Belum Terdeteksi</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($bioUserCount > 0)
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-indigo-lt text-indigo px-2 py-1 fw-bold fs-5 me-1">
                                                    <i class="ti ti-users me-1"></i>{{ $bioUserCount }} User
                                                </span>
                                            </div>
                                            <div class="text-secondary small mt-1">
                                                {{ $bioTemplateCount }} Template Sidik Jari
                                            </div>
                                        @else
                                            <span class="text-muted small">
                                                <i class="ti ti-fingerprint-off me-1"></i>Belum ada data
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-secondary small">
                                        @if($dev->last_activity_at)
                                            <span title="{{ $dev->last_activity_at->format('d M Y H:i:s') }}">
                                                <i class="ti ti-history me-1"></i>{{ $dev->last_activity_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-list justify-content-end">
                                            <!-- Tombol Cockpit / Kelola Remote Mesin -->
                                            <a href="{{ route('attendance.devices.manage.show', $dev->id) }}" class="btn btn-sm btn-primary" title="Buka Panel Kendali Remote, Kloning Biometrik & Pemeliharaan">
                                                <i class="ti ti-dashboard me-1"></i> Remote Cockpit
                                            </a>
                                            <!-- Tes Koneksi -->
                                            <form action="{{ route('attendance.devices.test', $dev->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Uji konektivitas langsung ke port mesin">
                                                    <i class="ti ti-network"></i>
                                                </button>
                                            </form>
                                            <!-- Tarik Data Log -->
                                            <form action="{{ route('attendance.devices.sync', $dev->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Kirim perintah tarik log absensi ke mesin {{ $dev->name }}?')" title="Tarik Log Absensi">
                                                    <i class="ti ti-refresh me-1"></i> Tarik Log
                                                </button>
                                            </form>
                                            <!-- Sinkron Jam -->
                                            <form action="{{ route('attendance.devices.sync-time', $dev->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Sinkronkan jam mesin dengan jam server sekarang">
                                                    <i class="ti ti-clock-check"></i>
                                                </button>
                                            </form>
                                            <!-- Edit Device Modal Trigger -->
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-edit-device-{{ $dev->id }}" title="Edit Pengaturan Mesin">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                            <!-- Hapus Mesin -->
                                            <form action="{{ route('attendance.devices.destroy', $dev->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mesin {{ $dev->name }} dari sistem?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Mesin">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="ti ti-plug-connected-x fs-1 d-block mb-1"></i>
                                        Belum ada mesin absensi yang terdaftar di sistem.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CARD / TABEL STATUS REALTIME & PROGRESS AKTIVITAS MESIN -->
        <div class="card shadow-sm border-0 border-top border-3 border-teal mb-3" id="liveMachineStatusCard">
            <div class="card-header bg-teal-lt d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-xs bg-teal text-white rounded"><i class="ti ti-activity-heartbeat"></i></span>
                    <div>
                        <h3 class="card-title mb-0 fs-4 fw-bold text-teal">
                            Status Realtime & Monitor Aktivitas Perangkat
                        </h3>
                        <div class="text-secondary small">
                            Memantau status komunikasi, proses sinkronisasi, dan pengosongan mesin secara live.
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-teal border border-teal-subtle px-2 py-1 shadow-sm font-monospace" id="liveRefreshTimeBadge">
                        <i class="ti ti-clock me-1"></i> Data: {{ now()->format('H:i:s') }}
                    </span>
                    <button type="button" class="btn btn-sm btn-teal" id="btnRefreshStatus" onclick="fetchLiveStatusOverview()" title="Perbarui Status Manual (Ringan & Cepat)">
                        <i class="ti ti-rotate-clockwise me-1" id="iconRefreshStatus"></i> Perbarui Status
                    </button>
                </div>
            </div>

            <!-- TABEL STATUS REALTIME PER MESIN -->
            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 22%;">Mesin & IP</th>
                            <th style="width: 14%;" class="text-center">Status Jaringan</th>
                            <th style="width: 16%;" class="text-center">Biometrik di Mesin</th>
                            <th style="width: 16%;" class="text-center">Antrean Proses</th>
                            <th style="width: 32%;">Laporan Operasi Terakhir</th>
                        </tr>
                    </thead>
                    <tbody id="liveStatusTableBody">
                        @forelse($devices as $dev)
                            @php
                                $bioStat = $dev->device_sn ? ($biometricUsersPerDevice->get($dev->device_sn) ?? null) : null;
                                $bioUserCount = $bioStat ? $bioStat->user_count : 0;
                                $bioTemplateCount = $bioStat ? $bioStat->template_count : 0;
                            @endphp
                            <tr id="device-row-{{ $dev->id }}">
                                <td>
                                    <div class="fw-bold text-dark d-flex align-items-center">
                                        <i class="ti ti-device-cctv text-teal me-1"></i> {{ $dev->name }}
                                    </div>
                                    <div class="text-secondary small font-monospace">
                                        {{ $dev->ip_address }}:{{ $dev->port }} &bull; SN: {{ $dev->device_sn ?: '-' }}
                                    </div>
                                </td>
                                <td class="text-center" id="device-net-{{ $dev->id }}">
                                    @if($dev->status === 'online')
                                        <span class="badge bg-success-lt text-success px-2 py-1">
                                            <span class="status-dot status-dot-animated bg-success me-1"></span> ONLINE
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-lt text-muted px-2 py-1">
                                            <span class="status-dot bg-secondary me-1"></span> OFFLINE
                                        </span>
                                    @endif
                                    <div class="text-secondary small mt-1 font-monospace" style="font-size: 11px;">
                                        {{ $dev->last_activity_at ? $dev->last_activity_at->diffForHumans() : '-' }}
                                    </div>
                                </td>
                                <td class="text-center" id="device-bio-{{ $dev->id }}">
                                    @if($bioUserCount > 0)
                                        <span class="badge bg-indigo-lt text-indigo px-2 py-1 fw-bold">
                                            <i class="ti ti-fingerprint me-1"></i> {{ $bioUserCount }} User
                                        </span>
                                        <div class="text-secondary small mt-1 font-monospace" style="font-size: 11px;">
                                            {{ $bioTemplateCount }} Template
                                        </div>
                                    @else
                                        <span class="badge bg-warning-lt text-warning px-2 py-1">
                                            <i class="ti ti-eraser me-1"></i> 0 User (Kosong)
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center" id="device-queue-{{ $dev->id }}">
                                    <span class="badge bg-secondary-lt text-secondary px-2 py-1">
                                        <i class="ti ti-circle-check me-1"></i> Siap (0 Antrean)
                                    </span>
                                </td>
                                <td id="device-op-{{ $dev->id }}">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-teal-lt text-teal px-2 py-1">
                                            <i class="ti ti-check me-1"></i> Normal
                                        </span>
                                        <span class="text-secondary small">Siap menerima instruksi</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada mesin absensi yang aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- LAPORAN AKTIVITAS & PERINTAH TERAKHIR (WEB & MESIN) -->
            <div class="card-footer bg-light py-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-bold small text-dark d-flex align-items-center">
                        <i class="ti ti-history text-secondary me-1"></i> Laporan Aktivitas & Eksekusi Mesin Terkini:
                    </div>
                    <span class="text-secondary small" style="font-size: 11px;">Klik "Perbarui Status" untuk memuat laporan terbaru</span>
                </div>
                <div class="row g-2" id="liveRecentCommandList">
                    @forelse($recentCommands as $cmd)
                        <div class="col-md-6 col-lg-4">
                            <div class="p-2 bg-white rounded border border-light-subtle d-flex align-items-center justify-content-between shadow-2xs">
                                <div class="text-truncate me-2">
                                    <div class="fw-bold small text-dark text-truncate">
                                        <i class="ti ti-device-cctv text-primary me-1"></i> {{ $cmd->device?->name ?? ($cmd->device_sn ?: 'Mesin') }}
                                    </div>
                                    <div class="font-monospace text-secondary" style="font-size: 11px;" title="{{ $cmd->command }}">
                                        {{ mb_substr($cmd->command, 0, 32) }}...
                                    </div>
                                </div>
                                <div class="text-end text-nowrap">
                                    @if($cmd->status === 'executed')
                                        <span class="badge bg-success-lt text-success" style="font-size: 10px;">SELESAI</span>
                                    @elseif($cmd->status === 'pending')
                                        <span class="badge bg-warning-lt text-warning" style="font-size: 10px;">ANTREAN</span>
                                    @elseif($cmd->status === 'sent')
                                        <span class="badge bg-blue-lt text-primary" style="font-size: 10px;">PROSES</span>
                                    @else
                                        <span class="badge bg-danger-lt text-danger" style="font-size: 10px;">GAGAL</span>
                                    @endif
                                    <div class="text-secondary" style="font-size: 10px;">{{ $cmd->created_at ? $cmd->created_at->format('H:i:s') : '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted small py-2">Belum ada riwayat aktivitas mesin tercatat.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- PANDUAN PENGATURAN MESIN & ADMS -->
        <div class="card shadow-sm border-0 border-top border-3 border-indigo mb-3">
            <div class="card-header bg-indigo-lt">
                <h3 class="card-title fw-bold text-indigo mb-0">
                    <i class="ti ti-help me-2"></i> Panduan Koneksi ADMS Solution & Fingerspot
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <strong class="text-dark d-block mb-2">1. Pengaturan di Layar Mesin (Solution X601 / X606-S):</strong>
                            <ol class="small text-secondary ps-3 mb-0">
                                <li>Masuk ke <strong>Menu &rarr; Komunikasi &rarr; Pengaturan Server Cloud / Web (ADMS)</strong>.</li>
                                <li>Aktifkan <strong>Server Cloud Domain: Nonaktif (Gunakan IP)</strong>.</li>
                                <li>Isi <strong>Alamat Server IP: <code>192.168.1.10</code></strong>.</li>
                                <li>Isi <strong>Port Server: <code>8014</code></strong>.</li>
                                <li>Pastikan mesin dan server terhubung di jaringan lokal (LAN/WiFi) yang sama.</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <strong class="text-dark d-block mb-2">2. Keuntungan Pemisahan Menu & Remote Cockpit:</strong>
                            <ul class="small text-secondary ps-3 mb-0">
                                <li><strong>Halaman Absensi:</strong> Bersih & fokus pada rekap kehadiran guru/siswa tanpa beban kontrol hardware.</li>
                                <li><strong>Halaman Mesin Absensi:</strong> Khusus Administrator untuk kendali perangkat, uji ping, dan konfigurasi IP.</li>
                                <li><strong>Remote Cockpit:</strong> Memungkinkan kloning sidik jari antar-mesin, pembersihan siswa lulus, atau restart mesin dari jauh.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: SINKRON DATA KE MESIN -->
<div class="modal fade" id="modal-sync-users" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('attendance.devices.sync-users') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="ti ti-cloud-upload me-2"></i> Sinkron / Update Data ke Mesin Absensi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-none mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="ti ti-info-circle fs-2 mt-1 text-primary"></i>
                            <div>
                                <strong>Upload Nama & ID Pengguna Otomatis</strong><br>
                                Fitur ini akan mengirimkan data Nama dan PIN/ID dari database web sekolah langsung ke memori mesin absensi. Anda <strong>tidak perlu input nama manual</strong> di layar mesin satu per satu.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">1. Pilih Data yang Ingin Di-upload ke Mesin:</label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="target_type" value="gtk" class="form-selectgroup-input" checked onchange="toggleStudentFilter(this.value)">
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <div class="form-selectgroup-label-content d-flex align-items-center">
                                        <span class="avatar avatar-md bg-teal-lt me-3"><i class="ti ti-school fs-2"></i></span>
                                        <div>
                                            <div class="font-weight-medium fs-3">Data GTK (Guru & Tenaga Kependidikan)</div>
                                            <div class="text-secondary small">Kirim seluruh <strong>{{ $teachersCount }} Guru & Staf</strong> aktif (PIN = NIGS)</div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="target_type" value="students" class="form-selectgroup-input" onchange="toggleStudentFilter(this.value)">
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <div class="form-selectgroup-label-content d-flex align-items-center">
                                        <span class="avatar avatar-md bg-azure-lt me-3"><i class="ti ti-users fs-2"></i></span>
                                        <div>
                                            <div class="font-weight-medium fs-3">Data Siswa</div>
                                            <div class="text-secondary small">Kirim data Siswa aktif (PIN = NISN / NIS). Cocok untuk siswa baru setiap tahun ajaran.</div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="target_type" value="both" class="form-selectgroup-input" onchange="toggleStudentFilter(this.value)">
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <div class="form-selectgroup-label-content d-flex align-items-center">
                                        <span class="avatar avatar-md bg-purple-lt me-3"><i class="ti ti-database-export fs-2"></i></span>
                                        <div>
                                            <div class="font-weight-medium fs-3">Keduanya (GTK & Siswa Sekaligus)</div>
                                            <div class="text-secondary small">Kirim seluruh Guru, Staf, dan Siswa sekolah ke mesin.</div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Filter Siswa (hanya muncul jika Siswa atau Both dipilih) -->
                    <div class="mb-3" id="studentFilterWrapper" style="display: none;">
                        <label class="form-label fw-bold">Pilih Kelompok Siswa:</label>
                        <select name="student_filter" class="form-select">
                            <option value="all">Semua Siswa Aktif Seluruh Sekolah</option>
                            <optgroup label="Pilih Berdasarkan Kelas / Rombel (Misal Siswa Baru):">
                                @foreach($classrooms as $cls)
                                    <option value="class_{{ $cls->id }}">{{ $cls->name }} ({{ $cls->students_count }} Siswa)</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <div class="form-hint">Tip: Untuk siswa baru setiap tahun ajaran, Anda bisa memilih hanya kelas X agar proses sinkronisasi efisien.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">2. Pilih Mesin Tujuan:</label>
                        <select name="device_id" class="form-select" required>
                            <option value="all">Kirim ke SEMUA Mesin Aktif ({{ $devices->count() }} Mesin)</option>
                            @foreach($devices as $dev)
                                <option value="{{ $dev->id }}">Hanya Mesin: {{ $dev->name }} ({{ $dev->ip_address }}) [SN: {{ $dev->device_sn ?: '-' }}]</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Mulai proses pengiriman data ke mesin absensi?')">
                        <i class="ti ti-upload me-1"></i> Mulai Sinkronisasi ke Mesin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: TAMBAH MESIN ABSENSI -->
<div class="modal fade" id="modal-add-device" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('attendance.devices.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="ti ti-plus me-2"></i> Tambah Mesin Absensi Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label required">Nama Mesin</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Solution X601 (Gerbang Depan)" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label required">IP Address Mesin</label>
                            <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.18" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label required">Port Mesin</label>
                            <input type="number" name="port" class="form-control" value="4370" required>
                            <div class="form-hint">4370 (ZKTeco/Solution)</div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required">Port Server Web</label>
                            <input type="number" name="server_port" class="form-control" value="8014" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Comm Key / Password</label>
                            <input type="text" name="comm_key" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Serial Number (SN)</label>
                        <input type="text" name="device_sn" class="form-control" placeholder="Contoh: AEYU230960177 (Opsional)">
                        <div class="form-hint">Bisa dikosongkan, akan otomatis terdeteksi saat mesin pertama kali terhubung ke server.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Simpan Mesin Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT MESIN ABSENSI (PER MESIN) -->
@foreach($devices as $dev)
    <div class="modal fade" id="modal-edit-device-{{ $dev->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('attendance.devices.update', $dev->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="ti ti-edit me-2"></i> Edit Pengaturan: {{ $dev->name }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label required">Nama Mesin</label>
                            <input type="text" name="name" class="form-control" value="{{ $dev->name }}" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-8">
                                <label class="form-label required">IP Address Mesin</label>
                                <input type="text" name="ip_address" class="form-control" value="{{ $dev->ip_address }}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label required">Port Mesin</label>
                                <input type="number" name="port" class="form-control" value="{{ $dev->port }}" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label required">Port Server Web</label>
                                <input type="number" name="server_port" class="form-control" value="{{ $dev->server_port }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Comm Key / Password</label>
                                <input type="text" name="comm_key" class="form-control" value="{{ $dev->comm_key ?? '0' }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Serial Number (SN)</label>
                            <input type="text" name="device_sn" class="form-control" value="{{ $dev->device_sn }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check me-1"></i> Perbarui Mesin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@if(isset($pendingDevices))
    @foreach($pendingDevices as $pDev)
        <div class="modal modal-blur fade" id="modal-approve-device-{{ $pDev->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form action="{{ route('attendance.devices.approve', $pDev->id) }}" method="POST">
                    @csrf
                    <div class="modal-content shadow-lg border-0">
                        <div class="modal-header bg-success-lt">
                            <h5 class="modal-title fw-bold text-success">
                                <i class="ti ti-circle-check me-1"></i> Verifikasi & Daftarkan Mesin Absensi
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="ti ti-info-circle me-1"></i> Perangkat dengan Serial Number <strong>{{ $pDev->device_sn ?: 'Belum Terdeteksi' }}</strong> dari IP <strong>{{ $pDev->ip_address }}</strong> akan diaktifkan untuk mencatat absensi.
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Nama Resmi Mesin</label>
                                <input type="text" name="name" class="form-control" value="{{ $pDev->name }}" placeholder="Contoh: Solution X601 Ruang Guru" required>
                                <small class="form-hint">Beri nama lokasi atau deskripsi yang mudah dikenali oleh pengelola.</small>
                            </div>
                            <div class="row">
                                <div class="col-md-7 mb-3">
                                    <label class="form-label required">Alamat IP Mesin</label>
                                    <input type="text" name="ip_address" class="form-control font-monospace" value="{{ $pDev->ip_address }}" required>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label required">Port Mesin</label>
                                    <input type="number" name="port" class="form-control font-monospace" value="{{ $pDev->port }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success ms-auto">
                                <i class="ti ti-check me-1"></i> Setujui & Aktifkan Mesin
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif

<script>
function toggleStudentFilter(val) {
    let wrap = document.getElementById('studentFilterWrapper');
    if (wrap) {
        wrap.style.display = (val === 'students' || val === 'both') ? 'block' : 'none';
    }
}

// LIVE REALTIME MONITORING & REPORT POLLING
const liveApiUrl = "{{ route('attendance.devices.api-all-status') }}";

function fetchLiveStatusOverview() {
    let btn = document.getElementById('btnRefreshStatus');
    let icon = document.getElementById('iconRefreshStatus');
    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('rotate-infinite');

    fetch(liveApiUrl)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // Update timestamp
            let timeBadge = document.getElementById('liveRefreshTimeBadge');
            if (timeBadge && data.timestamp) {
                timeBadge.innerHTML = `<i class="ti ti-check text-success me-1"></i> Data: ${data.timestamp}`;
            }

            // Update each device row
            if (data.devices && data.devices.length > 0) {
                data.devices.forEach(dev => {
                    // 1. Status Jaringan
                    let netCol = document.getElementById(`device-net-${dev.id}`);
                    if (netCol) {
                        let isOnline = dev.status === 'online';
                        netCol.innerHTML = `
                            <span class="badge ${isOnline ? 'bg-success-lt text-success' : 'bg-secondary-lt text-muted'} px-2 py-1">
                                <span class="status-dot ${isOnline ? 'status-dot-animated bg-success' : 'bg-secondary'} me-1"></span>
                                ${dev.status.toUpperCase()}
                            </span>
                            <div class="text-secondary small mt-1 font-monospace" style="font-size: 11px;">
                                ${dev.last_activity}
                            </div>
                        `;
                    }

                    // 2. Biometrik di Mesin
                    let bioCol = document.getElementById(`device-bio-${dev.id}`);
                    if (bioCol) {
                        if (dev.bio_users > 0) {
                            bioCol.innerHTML = `
                                <span class="badge bg-indigo-lt text-indigo px-2 py-1 fw-bold">
                                    <i class="ti ti-fingerprint me-1"></i> ${dev.bio_users} User
                                </span>
                                <div class="text-secondary small mt-1 font-monospace" style="font-size: 11px;">
                                    ${dev.bio_templates} Template
                                </div>
                            `;
                        } else {
                            bioCol.innerHTML = `
                                <span class="badge bg-warning-lt text-warning px-2 py-1">
                                    <i class="ti ti-eraser me-1"></i> 0 User (Kosong)
                                </span>
                            `;
                        }
                    }

                    // 3. Antrean Proses
                    let queueCol = document.getElementById(`device-queue-${dev.id}`);
                    if (queueCol) {
                        let pending = (dev.pending_commands || 0) + (dev.sent_commands || 0);
                        if (pending > 0) {
                            queueCol.innerHTML = `
                                <span class="badge bg-warning text-white px-2 py-1 shadow-2xs">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    ${pending} Antrean Berjalan
                                </span>
                            `;
                        } else {
                            queueCol.innerHTML = `
                                <span class="badge bg-secondary-lt text-secondary px-2 py-1">
                                    <i class="ti ti-circle-check me-1"></i> Siap (0 Antrean)
                                </span>
                            `;
                        }
                    }

                    // 4. Laporan Operasi Terakhir
                    let opCol = document.getElementById(`device-op-${dev.id}`);
                    if (opCol) {
                        let isProcessing = dev.in_progress;
                        opCol.innerHTML = `
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge ${dev.last_operation_badge} px-2 py-1">
                                    ${isProcessing ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' : '<i class="ti ti-check me-1"></i>'}
                                    ${dev.last_operation}
                                </span>
                                <span class="text-secondary small">${dev.last_operation_time}</span>
                            </div>
                        `;
                    }
                });
            }

            // Update Recent Activity List
            if (data.recent_reports && data.recent_reports.length > 0) {
                let reportWrap = document.getElementById('liveRecentCommandList');
                if (reportWrap) {
                    let html = '';
                    data.recent_reports.forEach(r => {
                        let badgeClass = 'bg-secondary-lt text-secondary';
                        let label = r.status.toUpperCase();
                        if (r.status === 'executed') {
                            badgeClass = 'bg-success-lt text-success';
                            label = 'SELESAI';
                        } else if (r.status === 'pending') {
                            badgeClass = 'bg-warning-lt text-warning';
                            label = 'ANTREAN';
                        } else if (r.status === 'sent') {
                            badgeClass = 'bg-blue-lt text-primary';
                            label = 'PROSES';
                        } else if (r.status === 'failed') {
                            badgeClass = 'bg-danger-lt text-danger';
                            label = 'GAGAL';
                        }

                        html += `
                            <div class="col-md-6 col-lg-4">
                                <div class="p-2 bg-white rounded border border-light-subtle d-flex align-items-center justify-content-between shadow-2xs">
                                    <div class="text-truncate me-2">
                                        <div class="fw-bold small text-dark text-truncate">
                                            <i class="ti ti-device-cctv text-primary me-1"></i> ${r.device_name}
                                        </div>
                                        <div class="font-monospace text-secondary" style="font-size: 11px;" title="${r.full_command}">
                                            ${r.command}...
                                        </div>
                                    </div>
                                    <div class="text-end text-nowrap">
                                        <span class="badge ${badgeClass}" style="font-size: 10px;">${label}</span>
                                        <div class="text-secondary" style="font-size: 10px;">${r.time}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    reportWrap.innerHTML = html;
                }
            }
        })
        .catch(err => console.debug('Live overview poll error:', err))
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('rotate-infinite');
        });
}
</script>
@endsection
