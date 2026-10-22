@extends('layouts.app')

@section('title', 'Kendali Mesin: ' . $device->name)

@section('content')
<div class="page-header d-print-none mb-3">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('attendance.devices.index') }}">Mesin Absensi</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $device->name }}</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="page-title fs-2 fw-bold mb-0">
                        <i class="ti ti-device-cctv text-primary me-2"></i> {{ $device->name }}
                    </h2>
                    <span id="liveDeviceBadge" class="badge {{ $device->status === 'online' ? 'bg-success text-white' : 'bg-secondary text-white' }} px-2 py-1 fs-4">
                        <span class="status-dot {{ $device->status === 'online' ? 'status-dot-animated bg-white' : '' }} me-1"></span>
                        {{ strtoupper($device->status) }}
                    </span>
                    <span class="badge bg-blue-lt font-monospace px-2 py-1">SN: {{ $device->device_sn ?: 'Belum Terdeteksi' }}</span>
                </div>
                <div class="text-secondary small mt-1">
                    IP: <strong>{{ $device->ip_address }}:{{ $device->port }}</strong> &bull; Server ADMS: <strong>192.168.1.10:{{ $device->server_port }}</strong> &bull; Terakhir Aktif: <span id="liveDeviceActivity">{{ $device->last_activity_at ? $device->last_activity_at->diffForHumans() : '-' }}</span>
                </div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('attendance.devices.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Mesin
                    </a>
                    <!-- Quick Sync Clock -->
                    <form action="{{ route('attendance.devices.manage.sync-time', $device->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">
                            <i class="ti ti-clock-check me-1"></i> Sinkronkan Jam RTC
                        </button>
                    </form>
                    <!-- Quick Pull Log -->
                    <form action="{{ route('attendance.devices.manage.query-logs', $device->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-info text-white">
                            <i class="ti ti-refresh me-1"></i> Tarik Log Scan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <!-- BARIS KARTU TELEMETRI & STATISTIK (Kompak & Hemat Vertikal) -->
        <div class="row row-cards mb-3 g-2">
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-primary h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar avatar-sm rounded"><i class="ti ti-fingerprint fs-3"></i></span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">{{ number_format($totalScans) }}</div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Total Log Scan Tersimpan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-teal h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-teal text-white avatar avatar-sm rounded"><i class="ti ti-calendar-event fs-3"></i></span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">{{ number_format($todayScans) }}</div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Presensi Hari Ini (WIB)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-purple h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-purple text-white avatar avatar-sm rounded"><i class="ti ti-users fs-3"></i></span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1">{{ $teachersCount }} GTK &bull; {{ $classrooms->sum('students_count') }} Siswa</div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Potensi Pengguna Aktif</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-orange h-100">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="bg-orange text-white avatar avatar-sm rounded"><i class="ti ti-clock-pause fs-3"></i></span>
                            </div>
                            <div class="col text-truncate">
                                <div class="fs-3 fw-bold text-dark lh-1" id="livePendingCount">{{ $pendingCommands }}</div>
                                <div class="text-secondary small text-truncate" style="font-size: 11.5px;">Antrean Perintah Belum Dieksekusi</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARIS 1: KENDALI OPERASIONAL (KIRI) & SINKRONISASI PENGGUNA (KANAN) - TINGGI SEJAJAR -->
        <div class="row row-cards mb-3 align-items-stretch">
            <!-- PANEL 1: KENDALI OPERASIONAL CEPAT -->
            <div class="col-lg-6 d-flex">
                <div class="card shadow-sm border-0 w-100 d-flex flex-column">
                    <div class="card-header bg-body-tertiary">
                        <h3 class="card-title fw-bold">
                            <i class="ti ti-adjustments-bolt text-primary me-2"></i> Aksi Kendali Jarak Jauh (Remote Action)
                        </h3>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <p class="text-secondary small mb-3">
                            Semua perintah di bawah ini akan dikirimkan langsung ke mesin via koneksi socket port 4370 dan antrean protokol ADMS tanpa perlu menekan tombol fisik mesin.
                        </p>
                        <div class="row g-2">
                            <!-- Sinkron Jam -->
                            <div class="col-sm-6">
                                <form action="{{ route('attendance.devices.manage.sync-time', $device->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning w-100 justify-content-start text-start p-2">
                                        <i class="ti ti-clock-check fs-2 text-warning me-2"></i>
                                        <div>
                                            <div class="fw-bold">Sinkronkan Jam RTC</div>
                                            <div class="text-secondary small">Kalibrasi jam mesin ke WIB</div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <!-- Tarik Log -->
                            <div class="col-sm-6">
                                <form action="{{ route('attendance.devices.manage.query-logs', $device->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info w-100 justify-content-start text-start p-2">
                                        <i class="ti ti-cloud-download fs-2 text-info me-2"></i>
                                        <div>
                                            <div class="fw-bold">Tarik Paksa Seluruh Log</div>
                                            <div class="text-secondary small">Perintahkan upload ATTLOG</div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <!-- Buka Kunci Admin -->
                            <div class="col-sm-6">
                                <form action="{{ route('attendance.devices.manage.unlock-admin', $device->id) }}" method="POST" onsubmit="return confirm('Kirim perintah CLEAR ADMIN ke mesin {{ $device->name }}? Hak admin di keypad mesin akan direset agar menu mesin bisa diakses.')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success w-100 justify-content-start text-start p-2">
                                        <i class="ti ti-lock-open fs-2 text-success me-2"></i>
                                        <div>
                                            <div class="fw-bold">Buka Kunci Admin Mesin</div>
                                            <div class="text-secondary small">Reset hak akses admin jika terkunci</div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <!-- Reboot Mesin -->
                            <div class="col-sm-6">
                                <form action="{{ route('attendance.devices.manage.reboot', $device->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin me-restart mesin {{ $device->name }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger w-100 justify-content-start text-start p-2">
                                        <i class="ti ti-power fs-2 text-danger me-2"></i>
                                        <div>
                                            <div class="fw-bold">Restart / Reboot Mesin</div>
                                            <div class="text-secondary small">Restart sistem mesin jarak jauh</div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <!-- Kosongkan Memori Log Mesin -->
                            <div class="col-12 mt-2">
                                <form action="{{ route('attendance.devices.manage.clear-logs', $device->id) }}" method="POST" onsubmit="return confirm('PERINGATAN: Perintah ini akan MENGOSONGKAN SELURUH LOG yang tersimpan di dalam memori mesin {{ $device->name }}. Pastikan log sudah terunduh di server web. Lanjutkan?')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger w-100 justify-content-start text-start p-2">
                                        <i class="ti ti-eraser fs-2 me-2"></i>
                                        <div>
                                            <div class="fw-bold">Kosongkan Riwayat Log di Mesin</div>
                                            <div class="small opacity-75">Membersihkan memori log mesin (data log di web tetap aman)</div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL 4: SINKRONISASI PENGGUNA BERTARGET KE MESIN INI -->
            <div class="col-lg-6 d-flex">
                <div class="card shadow-sm border-0 w-100 d-flex flex-column">
                    <div class="card-header bg-body-tertiary">
                        <h3 class="card-title fw-bold">
                            <i class="ti ti-cloud-upload text-primary me-2"></i> Kirim Data Pengguna ke Mesin Ini Saja
                        </h3>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <p class="text-secondary small mb-3">
                                Unggah data nama dan nomor PIN (NIGS untuk guru, NISN untuk siswa) dari database sekolah ke mesin <strong>{{ $device->name }}</strong>:
                            </p>
                            <form action="{{ route('attendance.devices.manage.sync-target-users', $device->id) }}" method="POST" id="formSyncTargetUsers">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label required">Kelompok Pengguna:</label>
                                    <select name="target_group" class="form-select" required>
                                        <option value="" disabled selected>-- Pilih Pengguna yang Akan Dikirim --</option>
                                        <optgroup label="Pegawai & Pendidik">
                                            <option value="gtk">GTK: Seluruh Guru & Staf ({{ $teachersCount }} orang &bull; PIN: NIGS)</option>
                                        </optgroup>
                                        <optgroup label="Semua Siswa">
                                            <option value="all_students">Seluruh Siswa Aktif ({{ $classrooms->sum('students_count') }} siswa &bull; PIN: NISN)</option>
                                        </optgroup>
                                        <optgroup label="Siswa per Rombel / Kelas">
                                            @foreach($classrooms as $c)
                                                <option value="class_{{ $c->id }}">Kelas {{ $c->name }} ({{ $c->students_count }} Siswa)</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    <div class="form-hint">Tip: Pilih rombel tertentu (misal Kelas X) saat awal tahun ajaran baru.</div>
                                </div>
                            </form>
                        </div>
                        <div class="pt-3 border-top mt-auto">
                            <button type="submit" form="formSyncTargetUsers" class="btn btn-primary w-100">
                                <i class="ti ti-upload me-1"></i> Unggah Data Terpilih ke Mesin Ini
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARIS 2: PEMELIHARAAN TAHUN AJARAN, PERINTAH CUSTOM, ANTREAN & LOG TERAKHIR -->
        <div class="row row-cards">
            <!-- KOLOM KIRI -->
            <div class="col-lg-6">
                <!-- PANEL BIOMETRIK: CADANGKAN & KLONING SIDIK JARI/WAJAH KE MESIN LAIN -->
                <div class="card shadow-sm border-0 border-top border-3 border-indigo mb-3">
                    <div class="card-header bg-indigo-lt d-flex justify-content-between align-items-center">
                        <h3 class="card-title fw-bold text-indigo mb-0">
                            <i class="ti ti-fingerprint me-2"></i> Sinkronisasi & Kloning Biometrik (Sidik Jari / Wajah)
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('attendance.devices.biometrics') }}" class="btn btn-sm btn-outline-indigo">
                                <i class="ti ti-database me-1"></i> Buka Bank Master
                            </a>
                            <span class="badge bg-indigo text-white">{{ $savedBiometricsCount }} Template di Server</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info border-0 shadow-none mb-3 py-2 small">
                            <i class="ti ti-info-circle me-1"></i>
                            Rekam sidik jari siswa/guru cukup <strong>1 kali di satu mesin</strong>. Tarik biometriknya ke server web, lalu salin/kloning ke mesin absensi lainnya secara otomatis tanpa perlu rekam ulang manual di tiap mesin!
                        </div>

                        <!-- 1. Tarik Biometrik dari Mesin Ini -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="mb-2">
                                <strong class="text-dark fs-3 d-block">Langkah 1: Tarik & Cadangkan dari Mesin Ini</strong>
                                <span class="text-secondary small">
                                    Memerintahkan mesin <strong>{{ $device->name }}</strong> untuk mengunggah seluruh template sidik jari dan foto wajah yang telah terdaftar ke server web.
                                </span>
                            </div>
                            <form action="{{ route('attendance.devices.manage.backup-biometrics', $device->id) }}" method="POST" onsubmit="return confirm('Kirim perintah ke mesin {{ $device->name }} untuk mengunggah seluruh sidik jari/wajah ke server web?')">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="ti ti-download me-1"></i> Tarik Seluruh Biometrik Mesin Ini ke Server Web
                                </button>
                            </form>
                        </div>

                        <!-- 2. Kloning Biometrik ke Mesin Lain -->
                        <div class="p-3 bg-indigo-lt rounded border border-indigo-subtle">
                            <div class="mb-2">
                                <strong class="text-indigo fs-3 d-block">Langkah 2: Kloning / Salin ke Mesin Lain</strong>
                                <span class="text-secondary small">
                                    Mengirimkan {{ $savedBiometricsCount }} template biometrik yang tersimpan di server ke mesin absensi tujuan.
                                </span>
                            </div>
                            @if($otherDevices->isEmpty())
                                <div class="text-muted small italic">Tidak ada mesin absensi ADMS lain yang terdaftar.</div>
                            @else
                                <form action="{{ route('attendance.devices.manage.clone-biometrics', $device->id) }}" method="POST" onsubmit="return confirm('Kloning seluruh template biometrik yang ada di server ke mesin tujuan terpilih?')">
                                    @csrf
                                    <div class="input-group">
                                        <select name="target_device_id" class="form-select" required {{ $savedBiometricsCount == 0 ? 'disabled' : '' }}>
                                            <option value="" disabled selected>-- Pilih Mesin Tujuan --</option>
                                            @foreach($otherDevices as $od)
                                                <option value="{{ $od->id }}">{{ $od->name }} (SN: {{ $od->device_sn }})</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-indigo" {{ $savedBiometricsCount == 0 ? 'disabled' : '' }}>
                                            <i class="ti ti-copy me-1"></i> Kloning Sekarang
                                        </button>
                                    </div>
                                    @if($savedBiometricsCount == 0)
                                        <div class="form-hint text-danger mt-1">Belum ada template biometrik di server. Lakukan "Langkah 1" terlebih dahulu.</div>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- PANEL 2: MANAJEMEN PERGANTIAN TAHUN AJARAN & PENGHAPUSAN USER -->
                <div class="card shadow-sm border-0 border-top border-3 border-teal mb-3">
                    <div class="card-header bg-teal-lt d-flex justify-content-between align-items-center">
                        <h3 class="card-title fw-bold text-teal mb-0">
                            <i class="ti ti-school me-2"></i> Pemeliharaan Pergantian Tahun Ajaran
                        </h3>
                        <span class="badge bg-teal text-white">{{ $graduatedStudentsCount }} Siswa Nonaktif/Lulus</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info border-0 shadow-none mb-3 py-2">
                            <i class="ti ti-info-circle me-1"></i>
                            Gunakan fitur ini saat <strong>kenaikan kelas / tahun ajaran baru</strong> untuk mengosongkan memori mesin dari data siswa yang sudah lulus, atau memasukkan kelompok siswa baru tanpa mendatangi mesin.
                        </div>

                        <!-- 1. Hapus Siswa Lulus Bertarget -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong class="text-dark fs-3 d-block">Opsi 1: Hapus Siswa Lulus / Nonaktif Saja</strong>
                                    <span class="text-secondary small">
                                        Menghapus nama dan template sidik jari siswa yang berstatus tidak aktif di web. <strong>Data guru dan siswa kelas lain tetap aman tersimpan di mesin</strong>.
                                    </span>
                                </div>
                            </div>
                            <form action="{{ route('attendance.devices.manage.purge-graduated', $device->id) }}" method="POST" onsubmit="return confirm('Hapus seluruh siswa berstatus lulus/nonaktif ({{ $graduatedStudentsCount }} siswa) dari mesin {{ $device->name }}?')">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100" {{ $graduatedStudentsCount == 0 ? 'disabled' : '' }}>
                                    <i class="ti ti-user-x me-1"></i> Hapus {{ $graduatedStudentsCount }} Siswa Lulus dari Mesin Ini
                                </button>
                            </form>
                        </div>

                        <!-- 2. Format / Kosongkan Seluruh User di Mesin -->
                        <div class="p-3 bg-danger-lt rounded border border-danger-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong class="text-danger fs-3 d-block">Opsi 2: Kosongkan Seluruh User Mesin (Fresh Slate)</strong>
                                    <span class="text-secondary small">
                                        Menghapus seluruh pengguna dan biometrik di mesin ini hingga bersih seperti mesin baru. Setelah dikosongkan, Anda dapat mengunggah kembali GTK dan siswa aktif dengan 1 klik di samping.
                                    </span>
                                </div>
                            </div>
                            <form action="{{ route('attendance.devices.manage.clear-all-users', $device->id) }}" method="POST" onsubmit="return confirm('PERINGATAN FATAL: Apakah Anda yakin ingin MENGHAPUS SEMUA PENGGUNA & BIOMETRIK dari mesin {{ $device->name }}? Seluruh sidik jari/wajah di mesin akan terhapus!')">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="ti ti-trash-x me-1"></i> Format & Kosongkan Seluruh User di Mesin Ini
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- PANEL 3: KIRIM PERINTAH CUSTOM / TERMINAL ADMS -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-body-tertiary">
                        <h3 class="card-title fw-bold">
                            <i class="ti ti-terminal-2 text-dark me-2"></i> Kirim Perintah Custom ke Mesin
                        </h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('attendance.devices.manage.custom-command', $device->id) }}" method="POST">
                            @csrf
                            <div class="input-group">
                                <input type="text" name="raw_command" class="form-control font-monospace" placeholder="Contoh: CHECK, REBOOT, DATA QUERY ATTLOG" required>
                                <button class="btn btn-dark" type="submit">
                                    <i class="ti ti-send me-1"></i> Kirim Perintah
                                </button>
                            </div>
                            <div class="form-hint mt-2">
                                Sintaks ZK ADMS yang umum didukung: <code>CHECK</code>, <code>REBOOT</code>, <code>CLEAR ADMIN</code>, <code>DATA QUERY ATTLOG</code>, <code>DATA UPDATE USERINFO PIN=...</code>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN -->
            <div class="col-lg-6">

                <!-- PANEL 5: ANTREAN PERINTAH & LOG KOMUNIKASI MESIN -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                        <h3 class="card-title fw-bold mb-0">
                            <i class="ti ti-list-check text-success me-2"></i> Antrean & Riwayat Perintah Mesin
                        </h3>
                        @if($pendingCommands > 0)
                            <form action="{{ route('attendance.devices.manage.cancel-queue', $device->id) }}" method="POST" onsubmit="return confirm('Batalkan seluruh {{ $pendingCommands }} antrean perintah tertunda untuk mesin ini?')">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-danger">
                                    <i class="ti ti-x me-1"></i> Batalkan Antrean
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-vcenter table-hover card-table mb-0">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th class="w-1 text-center">ID</th>
                                    <th>Perintah</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Waktu</th>
                                </tr>
                            </thead>
                            <tbody id="liveCommandTableBody">
                                @forelse($commands as $cmd)
                                    <tr>
                                        <td class="font-monospace text-secondary small text-center">#{{ $cmd->id }}</td>
                                        <td>
                                            <div class="font-monospace text-truncate text-dark small" style="max-width: 260px;" title="{{ $cmd->command }}">
                                                {{ $cmd->command }}
                                            </div>
                                            @if($cmd->response)
                                                <div class="text-muted small font-monospace" style="font-size: 11px;">{{ $cmd->response }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($cmd->status === 'executed')
                                                <span class="badge bg-success-lt text-success">SUKSES</span>
                                            @elseif($cmd->status === 'sent')
                                                <span class="badge bg-blue-lt text-primary">TERKIRIM</span>
                                            @elseif($cmd->status === 'pending')
                                                <span class="badge bg-warning-lt text-warning">ANTREAN</span>
                                            @else
                                                <span class="badge bg-danger-lt text-danger">{{ strtoupper($cmd->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-secondary small font-monospace text-end pe-3">
                                            {{ $cmd->created_at->format('H:i:s') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat perintah untuk mesin ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PANEL 6: SCAN TERAKHIR DARI MESIN INI -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-body-tertiary">
                        <h3 class="card-title fw-bold mb-0">
                            <i class="ti ti-clock-record text-indigo me-2"></i> Presensi Terbaru dari Mesin Ini
                        </h3>
                    </div>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-vcenter table-hover card-table mb-0">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Nama / PIN</th>
                                    <th class="text-center">Tipe</th>
                                    <th class="text-center pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLogs as $log)
                                    <tr>
                                        <td class="font-monospace text-primary fw-bold small">
                                            {{ $log->scanned_at ? $log->scanned_at->format('d/m H:i:s') : '-' }}
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 200px;">{{ $log->name }}</div>
                                            <div class="text-secondary small font-monospace">PIN: {{ $log->pin }}</div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-azure-lt">{{ $log->user_type === 'teacher' ? 'Guru' : ($log->user_type === 'student' ? 'Siswa' : 'Unknown') }}</span>
                                        </td>
                                        <td class="text-center pe-3">
                                            <span class="badge bg-blue-lt text-primary fw-bold">
                                                <i class="ti ti-scan me-1"></i>Scan Terverifikasi
                                            </span>
                                            <div class="text-secondary small font-monospace" style="font-size: 10px;">
                                                Mode: {{ $log->status === 'check_out' ? 'Pulang' : 'Masuk' }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Belum ada catatan presensi dari mesin ini.</td>
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

<!-- AUTO-REFRESH STATUS & COMMAND QUEUE SETIAP 4 DETIK -->
<script>
let devicePollUrl = "{{ route('attendance.devices.manage.api-status', $device->id) }}";

function pollDeviceStatus() {
    fetch(devicePollUrl)
        .then(res => res.json())
        .then(data => {
            if (data.status) {
                let badge = document.getElementById('liveDeviceBadge');
                if (badge) {
                    badge.className = 'badge ' + (data.status === 'online' ? 'bg-success text-white' : 'bg-secondary text-white') + ' px-2 py-1 fs-4';
                    badge.innerHTML = `<span class="status-dot ${data.status === 'online' ? 'status-dot-animated bg-white' : ''} me-1"></span> ${data.status.toUpperCase()}`;
                }
            }
            if (data.last_activity) {
                let act = document.getElementById('liveDeviceActivity');
                if (act) act.innerText = data.last_activity;
            }
            if (data.pending_commands !== undefined) {
                let pCount = document.getElementById('livePendingCount');
                if (pCount) pCount.innerText = data.pending_commands;
            }
        })
        .catch(err => console.log('Cockpit poll error:', err));
}

setInterval(pollDeviceStatus, 4000);
</script>
@endsection
