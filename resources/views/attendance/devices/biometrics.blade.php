@extends('layouts.app')

@section('title', 'Bank Master Biometrik (Sidik Jari & Wajah)')

@section('content')
<div class="page-header d-print-none mb-3">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Presensi</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('attendance.devices.index') }}">Mesin Absensi</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Bank Master Biometrik</li>
                    </ol>
                </nav>
                <div class="page-pretitle d-flex align-items-center gap-2">
                    <span>Brankas Data Terpusat</span>
                    <span class="badge bg-indigo-lt text-indigo font-monospace">Centralized Biometric Master</span>
                </div>
                <h2 class="page-title d-flex align-items-center">
                    <i class="ti ti-fingerprint text-indigo me-2"></i> Bank Master Biometrik Sekolah
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('attendance.devices.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Mesin
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-pull-biometrics">
                        <i class="ti ti-download me-1"></i> Tarik & Perbarui Master dari Mesin
                    </button>
                    <button type="button" class="btn btn-sm btn-indigo shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-broadcast-biometrics" {{ $totalTemplates == 0 ? 'disabled' : '' }}>
                        <i class="ti ti-send me-1"></i> Distribusikan ke Mesin Cabang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                <div class="d-flex">
                    <div><i class="ti ti-circle-check fs-2 me-2"></i></div>
                    <div>
                        <h4 class="alert-title mb-1">Berhasil!</h4>
                        <div class="text-secondary">{{ session('success') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                <div class="d-flex">
                    <div><i class="ti ti-alert-triangle fs-2 me-2"></i></div>
                    <div>
                        <h4 class="alert-title mb-1">Perhatian</h4>
                        <div class="text-secondary">{{ session('warning') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                <div class="d-flex">
                    <div><i class="ti ti-alert-circle fs-2 me-2"></i></div>
                    <div>
                        <h4 class="alert-title mb-1">Terjadi Kesalahan</h4>
                        <div class="text-secondary">{{ session('error') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- KARTU STATISTIK & SPESIFIKASI TEKNIS (Kompak & Hemat Vertikal) -->
        <div class="row row-cards mb-3 g-2">
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-indigo h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-indigo text-white avatar avatar-sm rounded">
                                    <i class="ti ti-fingerprint fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-indigo lh-1">
                                    {{ $totalTemplates }}
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">
                                    @if($totalFaces > 0)
                                        {{ $totalFingerprints }} Jari &bull; {{ $totalFaces }} Wajah
                                    @else
                                        {{ $totalFingerprints }} Sidik Jari
                                    @endif
                                </div>
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
                                    <i class="ti ti-users fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-success lh-1">
                                    {{ $totalEnrolledUsers }} Orang
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Pengguna Terdaftar</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-primary h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar avatar-sm rounded">
                                    <i class="ti ti-cpu fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">
                                    ZKFinger 10.0
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Format Standar Mesin</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-azure h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-azure text-white avatar avatar-sm rounded">
                                    <i class="ti ti-database fs-3"></i>
                                </span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">
                                    {{ number_format($totalSizeBytes / 1024, 2) }} KB
                                </div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Ukuran di Server</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANDUAN KERJA SINGKAT -->
        <div class="card shadow-sm border-0 bg-light-subtle mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-indigo text-white rounded"><i class="ti ti-info-circle"></i></span>
                        <div class="small">
                            <strong>Alur Replikasi:</strong> 
                            1. Rekam sidik jari di <strong>Mesin Utama</strong> &bull; 
                            2. Klik <strong>Tarik & Perbarui Master</strong> &bull; 
                            3. Klik <strong>Distribusikan ke Mesin Cabang</strong> agar mesin tujuan otomatis mengunduh template.
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-pull-biometrics">
                            <i class="ti ti-download me-1"></i> Tarik dari Mesin
                        </button>
                        <button type="button" class="btn btn-sm btn-indigo" data-bs-toggle="modal" data-bs-target="#modal-broadcast-biometrics" {{ $totalTemplates == 0 ? 'disabled' : '' }}>
                            <i class="ti ti-send me-1"></i> Sebar ke Cabang
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS KONTEN -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header border-bottom-0 pb-0 bg-body-tertiary">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="#tab-enrolled" class="nav-link active fw-bold" data-bs-toggle="tab" aria-selected="true" role="tab">
                            <i class="ti ti-fingerprint me-1"></i> Sudah Memiliki Biometrik 
                            <span class="badge bg-indigo-lt text-indigo ms-1">{{ $groupedBiometrics->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-missing" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-user-x me-1"></i> Belum Rekam Biometrik 
                            <span class="badge bg-warning-lt text-warning ms-1">{{ $totalMissingTeachers + $totalMissingStudents }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                <!-- TAB 1: SUDAH MEMILIKI BIOMETRIK -->
                <div class="tab-pane active show" id="tab-enrolled" role="tabpanel">
                    <div class="card-body border-bottom py-2 px-3">
                        <div class="d-flex align-items-center justify-content-end">
                            <form method="GET" action="{{ route('attendance.devices.biometrics') }}" class="d-flex align-items-center gap-1 mb-0">
                                <div class="input-icon">
                                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari nama, PIN, kelas..." style="width: 220px;">
                                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                </div>
                                @if($search)
                                    <a href="{{ route('attendance.devices.biometrics') }}" class="btn btn-sm btn-outline-secondary" title="Reset Pencarian">
                                        <i class="ti ti-x"></i>
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th style="width: 50px;">#</th>
                                    <th>PIN Mesin</th>
                                    <th>Nama Pengguna & Identitas</th>
                                    <th>Peran</th>
                                    <th>Biometrik Terdaftar</th>
                                    <th>Mesin Asal</th>
                                    <th>Terakhir Diperbarui</th>
                                    <th class="text-end" style="min-width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groupedBiometrics as $bio)
                                    <tr>
                                        <td class="text-secondary small">{{ $loop->iteration }}</td>
                                        <td>
                                            <span class="badge bg-blue-lt font-monospace px-2 py-1 fs-4">{{ $bio['pin'] }}</span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block">{{ $bio['name'] }}</strong>
                                            <div class="text-secondary small">{{ $bio['info'] }}</div>
                                        </td>
                                        <td>
                                            @if($bio['type'] === 'teacher')
                                                <span class="badge bg-purple-lt text-purple">GTK / Guru</span>
                                            @elseif($bio['type'] === 'student')
                                                <span class="badge bg-cyan-lt text-cyan">Siswa</span>
                                            @else
                                                <span class="badge bg-secondary-lt text-secondary">Lainnya</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($bio['items'] as $item)
                                                    <span class="badge bg-indigo-lt text-indigo" title="Valid: {{ $item->valid }}">
                                                        <i class="ti ti-fingerprint me-1"></i>{{ $item->finger_label }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            <div class="text-secondary small mt-1">
                                                Total: {{ $bio['finger_count'] }} Jari Terdaftar
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="ti ti-device-floppy text-secondary me-1"></i>
                                                <span class="small text-dark">{{ $bio['source_device_name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="text-secondary small">
                                            @if($bio['last_updated'])
                                                <span title="{{ $bio['last_updated']->format('d M Y H:i:s') }}">
                                                    {{ $bio['last_updated']->diffForHumans() }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($bio['items']->isNotEmpty())
                                                <form action="{{ route('attendance.devices.biometrics.destroy', $bio['items']->first()->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus seluruh template biometrik untuk PIN {{ $bio['pin'] }} ({{ $bio['name'] }}) dari Master Server?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus dari Master Server">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="ti ti-fingerprint-off fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                            @if($search)
                                                Tidak ditemukan data biometrik yang cocok dengan pencarian "<strong>{{ $search }}</strong>".
                                            @else
                                                Belum ada data biometrik yang ditarik ke server web.<br>
                                                Klik tombol <strong>"Tarik & Perbarui Master dari Mesin"</strong> di atas untuk mengambil data dari mesin utama.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: AUDIT BELUM REKAM BIOMETRIK -->
                <div class="tab-pane" id="tab-missing" role="tabpanel">
                    <div class="card-body">
                        <div class="alert alert-warning border-0 shadow-none mb-3 py-2 small">
                            <i class="ti ti-alert-triangle me-1"></i>
                            Daftar di bawah ini adalah GTK dan Siswa aktif yang <strong>belum pernah merekam sidik jari di mesin absensi</strong>. Petugas dapat memanggil mereka untuk melakukan registrasi jari di mesin utama.
                        </div>

                        <div class="row g-4">
                            <!-- GTK Belum Rekam -->
                            <div class="col-lg-6">
                                <div class="card border">
                                    <div class="card-header bg-purple-lt py-2 d-flex justify-content-between align-items-center">
                                        <h4 class="card-title text-purple mb-0 fs-4 fw-bold">
                                            <i class="ti ti-user-check me-1"></i> GTK Belum Rekam ({{ $totalMissingTeachers }} Orang)
                                        </h4>
                                    </div>
                                    <div class="table-responsive" style="max-height: 400px;">
                                        <table class="table table-vcenter table-sm mb-0">
                                            <thead>
                                                <tr class="table-light">
                                                    <th>Nama GTK</th>
                                                    <th>PIN / NIGS</th>
                                                    <th>Jabatan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($missingTeachers as $mt)
                                                    <tr>
                                                        <td><strong>{{ $mt->name }}</strong></td>
                                                        <td><code>{{ $mt->nigs_fix ?: (preg_replace('/\D/', '', (string) $mt->nigs) ?: $mt->id) }}</code></td>
                                                        <td class="text-secondary small">{{ $mt->position ?: 'Guru' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-success py-3">
                                                            <i class="ti ti-circle-check me-1"></i> Semua GTK telah merekam sidik jari!
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Siswa Belum Rekam -->
                            <div class="col-lg-6">
                                <div class="card border">
                                    <div class="card-header bg-cyan-lt py-2 d-flex justify-content-between align-items-center">
                                        <h4 class="card-title text-cyan mb-0 fs-4 fw-bold">
                                            <i class="ti ti-school me-1"></i> Siswa Belum Rekam ({{ $totalMissingStudents }} Siswa)
                                        </h4>
                                    </div>
                                    <div class="table-responsive" style="max-height: 400px;">
                                        <table class="table table-vcenter table-sm mb-0">
                                            <thead>
                                                <tr class="table-light">
                                                    <th>Nama Siswa</th>
                                                    <th>PIN / NISN</th>
                                                    <th>Kelas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($missingStudents as $ms)
                                                    <tr>
                                                        <td><strong>{{ $ms->name }}</strong></td>
                                                        <td><code>{{ $ms->nisn ?: ($ms->nis ?: $ms->id) }}</code></td>
                                                        <td class="text-secondary small">{{ $ms->classroom?->name ?? '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-success py-3">
                                                            <i class="ti ti-circle-check me-1"></i> Semua siswa telah merekam sidik jari!
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TARIK & PERBARUI MASTER DARI MESIN -->
<div class="modal modal-blur fade" id="modal-pull-biometrics" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary-lt">
                <h5 class="modal-title fw-bold text-primary">
                    <i class="ti ti-download me-1"></i> Tarik & Perbarui Master Biometrik
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    Sistem akan mengirim perintah <code>DATA QUERY FINGERTMP</code> ke mesin sumber. Mesin akan mengunggah seluruh template sidik jari ke server web dan otomatis memperbarui master tanpa menduplikasi data yang sudah ada.
                </div>
                <div class="mb-3">
                    <label class="form-label required">Pilih Mesin Utama / Sumber Perekaman:</label>
                    <select id="selectPullDevice" class="form-select">
                        @foreach($devices as $dev)
                            <option value="{{ $dev->id }}" {{ str_contains(strtolower($dev->name), '601') ? 'selected' : '' }}>
                                {{ $dev->name }} (IP: {{ $dev->ip_address }} &bull; SN: {{ $dev->device_sn }})
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint">Pilih mesin tempat siswa/guru merekam sidik jarinya.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary ms-auto" id="btnExecutePull">
                    <i class="ti ti-download me-1"></i> Mulai Penarikan Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DISTRIBUSIKAN / SINKRONKAN KE MESIN CABANG -->
<div class="modal modal-blur fade" id="modal-broadcast-biometrics" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="formBroadcastBiometrics" action="{{ route('attendance.devices.biometrics.broadcast') }}" method="POST">
            @csrf
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-indigo-lt">
                    <h5 class="modal-title fw-bold text-indigo">
                        <i class="ti ti-send me-1"></i> Distribusikan Master Biometrik ke Mesin Cabang
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Sebanyak <strong>{{ $totalTemplates }} template biometrik</strong> dari server master akan dikirimkan ke mesin tujuan terpilih.
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Pilih Mesin Tujuan:</label>
                        <select name="target_device_id" id="targetDeviceIdSelect" class="form-select" required>
                            <option value="all">-- Salin ke SEMUA Mesin Aktif Sekaligus --</option>
                            @foreach($devices as $dev)
                                <option value="{{ $dev->id }}">
                                    {{ $dev->name }} (IP: {{ $dev->ip_address }} &bull; SN: {{ $dev->device_sn }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-hint">Mesin dengan port direct 4370 akan disinkronkan secara instan, sedangkan ADMS antre otomatis.</small>
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <strong>Catatan:</strong> Data nama dan PIN pengguna akan otomatis dipastikan terdaftar terlebih dahulu sebelum template sidik jari disuntikkan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSubmitBroadcast" class="btn btn-indigo ms-auto">
                        <i class="ti ti-copy me-1"></i> Mulai Distribusi Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PROGRESS SINKRONISASI REAL-TIME -->
<div class="modal modal-blur fade" id="modal-sync-progress" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-indigo text-white">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                    <span class="spinner-border spinner-border-sm me-2" id="syncSpinner" role="status"></span>
                    <i class="ti ti-circle-check fs-2 text-white me-2 d-none" id="syncDoneIcon"></i>
                    <span id="syncModalTitle">Menyinkronkan Biometrik ke Mesin...</span>
                </h5>
            </div>
            <div class="modal-body py-4">
                <div class="text-center mb-3">
                    <div class="fs-1 fw-bold text-indigo mb-1" id="syncPercentageText">0%</div>
                    <div class="text-secondary small fw-medium" id="syncStatusSubtitle">Menyiapkan data template dan koneksi mesin...</div>
                </div>

                <div class="progress progress-lg mb-3 shadow-none bg-indigo-lt">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-indigo" id="syncProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <!-- DAFTAR PROGRES PER MESIN -->
                <div class="card bg-body-tertiary border-0 mb-0">
                    <div class="card-body p-3">
                        <div class="fw-bold small text-secondary mb-2 text-uppercase" style="letter-spacing: 0.5px;">Log Eksekusi Mesin:</div>
                        <ul class="list-unstyled mb-0 small" id="syncDeviceLogs">
                            <li class="text-muted d-flex align-items-center">
                                <i class="ti ti-loader ti-spin me-2 text-indigo"></i> Menghubungi mesin target...
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary">
                <button type="button" class="btn btn-secondary d-none ms-auto" id="btnSyncClose" data-bs-dismiss="modal">
                    Tutup & Perbarui Halaman
                </button>
            </div>
        </div>
    </div>
</div>

<form id="formExecutePull" method="POST" style="display: none;">
    @csrf
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. PULL HANDLER
    const btnExecutePull = document.getElementById('btnExecutePull');
    const selectPullDevice = document.getElementById('selectPullDevice');
    const formExecutePull = document.getElementById('formExecutePull');

    if (btnExecutePull && selectPullDevice && formExecutePull) {
        btnExecutePull.addEventListener('click', function() {
            const deviceId = selectPullDevice.value;
            formExecutePull.action = `/presensi/mesin/${deviceId}/cadangkan-biometrik`;
            btnExecutePull.disabled = true;
            btnExecutePull.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim Perintah...';
            formExecutePull.submit();
        });
    }

    // 2. BROADCAST LIVE PROGRESS HANDLER
    const formBroadcast = document.getElementById('formBroadcastBiometrics');
    const modalBroadcastEl = document.getElementById('modal-broadcast-biometrics');
    const modalProgressEl = document.getElementById('modal-sync-progress');

    if (formBroadcast && modalBroadcastEl && modalProgressEl) {
        const modalBroadcast = new bootstrap.Modal(modalBroadcastEl);
        const modalProgress = new bootstrap.Modal(modalProgressEl);

        const syncModalTitle = document.getElementById('syncModalTitle');
        const syncPercentageText = document.getElementById('syncPercentageText');
        const syncStatusSubtitle = document.getElementById('syncStatusSubtitle');
        const syncProgressBar = document.getElementById('syncProgressBar');
        const syncDeviceLogs = document.getElementById('syncDeviceLogs');
        const syncSpinner = document.getElementById('syncSpinner');
        const syncDoneIcon = document.getElementById('syncDoneIcon');
        const btnSyncClose = document.getElementById('btnSyncClose');

        formBroadcast.addEventListener('submit', function(e) {
            e.preventDefault();

            // Sembunyikan dialog form dan munculkan dialog progress
            bootstrap.Modal.getInstance(modalBroadcastEl).hide();
            modalProgress.show();

            // Reset state
            syncModalTitle.innerText = 'Menyinkronkan Biometrik ke Mesin...';
            syncPercentageText.innerText = '25%';
            syncStatusSubtitle.innerText = 'Menyusun paket template biometrik...';
            syncProgressBar.style.width = '25%';
            syncProgressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-indigo';
            syncSpinner.classList.remove('d-none');
            syncDoneIcon.classList.add('d-none');
            btnSyncClose.classList.add('d-none');
            syncDeviceLogs.innerHTML = '<li class="text-indigo d-flex align-items-center mb-1"><i class="ti ti-loader ti-spin me-2"></i> Mengirim paket template ke mesin tujuan...</li>';

            const formData = new FormData(formBroadcast);

            // Simulasi animasi bertahap saat request berjalan di background
            setTimeout(() => {
                if (syncProgressBar.style.width === '25%') {
                    syncProgressBar.style.width = '60%';
                    syncPercentageText.innerText = '60%';
                    syncStatusSubtitle.innerText = 'Menyuntikkan template ke memori perangkat (Port 4370 & ADMS)...';
                }
            }, 800);

            fetch(formBroadcast.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                syncProgressBar.style.width = '100%';
                syncPercentageText.innerText = '100%';
                syncProgressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');

                if (data.success) {
                    syncModalTitle.innerText = 'Sinkronisasi Selesai!';
                    syncProgressBar.className = 'progress-bar bg-success';
                    syncStatusSubtitle.innerText = data.message || 'Semua template biometrik berhasil diproses!';
                    syncSpinner.classList.add('d-none');
                    syncDoneIcon.classList.remove('d-none');

                    let logsHtml = '';
                    if (data.targets && data.targets.length > 0) {
                        data.targets.forEach(t => {
                            logsHtml += `
                                <li class="text-success d-flex align-items-center mb-1">
                                    <i class="ti ti-check text-success me-2"></i>
                                    <strong>${t.name}</strong> (${t.ip}): Berhasil ${t.queued} item &bull; <span class="badge bg-success-lt ms-1">${t.method}</span>
                                </li>
                            `;
                        });
                    } else {
                        logsHtml = `<li class="text-success"><i class="ti ti-check me-1"></i> ${data.message}</li>`;
                    }
                    syncDeviceLogs.innerHTML = logsHtml;
                } else {
                    syncModalTitle.innerText = 'Gagal Sinkronisasi';
                    syncProgressBar.className = 'progress-bar bg-danger';
                    syncStatusSubtitle.innerText = data.message || 'Terjadi kesalahan.';
                    syncSpinner.classList.add('d-none');
                    syncDeviceLogs.innerHTML = `<li class="text-danger"><i class="ti ti-x me-1"></i> ${data.message}</li>`;
                }

                btnSyncClose.classList.remove('d-none');
            })
            .catch(err => {
                syncProgressBar.style.width = '100%';
                syncPercentageText.innerText = 'Error';
                syncProgressBar.className = 'progress-bar bg-danger';
                syncModalTitle.innerText = 'Koneksi Terputus';
                syncStatusSubtitle.innerText = 'Gagal menghubungi server.';
                syncSpinner.classList.add('d-none');
                syncDeviceLogs.innerHTML = `<li class="text-danger"><i class="ti ti-alert-triangle me-1"></i> ${err.message || 'Error jaringan'}</li>`;
                btnSyncClose.classList.remove('d-none');
            });
        });

        btnSyncClose.addEventListener('click', function() {
            window.location.reload();
        });
    }
});
</script>
@endsection
