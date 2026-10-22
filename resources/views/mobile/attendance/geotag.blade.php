@extends('layouts.mobile')

@section('title', 'Absen GPS GTK — APK SIAP')
@section('page_title', 'Absen GPS')
@section('page_subtitle', 'Presensi Geotagging Mandiri')
@section('back_url', route('dashboard'))

@push('styles')
<style>
    /* Hero Presensi Action Button - Fokus Utama Halaman Mobile */
    .btn-hero-action {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 16px;
        border-radius: 16px;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.15s ease, filter 0.15s ease, background 0.3s ease, border-color 0.3s ease;
        position: relative;
        overflow: hidden;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
        text-align: left;
        border: none;
        will-change: transform;
        transform: translateZ(0);
    }
    .btn-hero-action:active {
        transform: scale(0.97);
        filter: brightness(1.08);
    }
    .btn-hero-action:disabled {
        opacity: 0.75;
        cursor: not-allowed;
        animation: none !important;
        transform: none !important;
    }

    /* Varian Masuk: Hijau Zamrud Segar & Berenergi */
    .btn-hero-masuk {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 55%, #22c55e 100%);
        border: 2px solid rgba(255, 255, 255, 0.45);
        color: #ffffff;
        box-shadow: 0 8px 22px -3px rgba(22, 163, 74, 0.45);
        animation: pulse-hero-action 2.6s infinite ease-in-out;
        will-change: transform;
        transform: translateZ(0);
    }

    /* Varian Pulang: Biru Safir Tegas & Elegan */
    .btn-hero-pulang {
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 55%, #38bdf8 100%);
        border: 2px solid rgba(255, 255, 255, 0.45);
        color: #ffffff;
        box-shadow: 0 8px 22px -3px rgba(37, 99, 235, 0.45);
        animation: pulse-hero-action 2.6s infinite ease-in-out;
        will-change: transform;
        transform: translateZ(0);
    }

    /* Varian Di Luar Radius: Berwarna Abu-Abu & Tanpa Animasi */
    .btn-hero-out-radius {
        background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%) !important;
        border: 2px solid #cbd5e1 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25) !important;
        animation: none !important;
        cursor: pointer !important;
    }
    .btn-hero-out-radius .btn-hero-icon {
        background: rgba(255, 255, 255, 0.2) !important;
    }
    .btn-hero-out-radius .btn-hero-arrow {
        background: rgba(255, 255, 255, 0.15) !important;
    }

    .btn-hero-icon {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.35);
    }

    .btn-hero-icon i {
        font-size: 1.6rem;
        color: #ffffff;
        line-height: 1;
    }

    .btn-hero-content {
        flex: 1 1 auto;
        padding: 0 12px;
        min-width: 0;
    }

    .btn-hero-sup {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
        letter-spacing: 0.02em;
        line-height: 1.1;
        margin-bottom: 2px;
    }

    .btn-hero-title {
        display: block;
        font-size: 1.05rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.01em;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    }

    .btn-hero-subtitle {
        display: block;
        font-size: 0.70rem;
        color: rgba(255, 255, 255, 0.85);
        margin-top: 2px;
        line-height: 1.2;
    }

    .btn-hero-arrow {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.1rem;
    }

    /* GPU-Composited Hardware Pulse (Zero CPU Repaint) */
    @keyframes pulse-hero-action {
        0%, 100% {
            transform: scale(1) translateZ(0);
        }
        50% {
            transform: scale(1.018) translateZ(0);
        }
    }
</style>
@endpush

@section('content')
@php
    $nameLen = mb_strlen($user->name);
    $initialGeotagFontSize = match (true) {
        $nameLen > 36 => '0.80rem',
        $nameLen > 28 => '0.88rem',
        $nameLen > 22 => '0.96rem',
        default => '1.05rem',
    };
@endphp
<!-- 1. Header Card: Identitas & Jam Digital Realtime -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge bg-white-lt text-white" style="font-size: 0.68rem;">
                <i class="ti ti-calendar me-1"></i>{{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
            </span>
            <div class="d-flex align-items-center gap-1">
                <span id="liveClock" class="fw-bold font-monospace text-warning fs-3 lh-1">--:--:--</span>
                <span class="text-white-50" style="font-size: 0.65rem;">WIB</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2.5">
            @if($user->avatar)
                <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="avatar avatar-md rounded-circle border border-2 border-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; object-fit: cover;">
            @else
                <span class="avatar avatar-md rounded-circle bg-warning text-dark fw-bold border border-2 border-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; font-size: 0.95rem; background-color: var(--brand-gold) !important; color: #fff !important;">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </span>
            @endif
            <div class="overflow-hidden flex-fill">
                <div id="geotagUserNameContainer" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <span id="geotagUserNameText" class="fw-bold text-white lh-sm" style="display: inline-block; white-space: nowrap; font-size: {{ $initialGeotagFontSize }};">
                        {{ $user->name }}
                    </span>
                </div>
                <div class="text-white-50 small mt-0.5 text-truncate" style="font-size: 0.72rem;">
                    {{ $user->nigs ? 'NIGS.' . $user->nigs : ($user->formatted_id !== '-' ? $user->formatted_id : 'GTK') }}
                    &bull; {{ $user->position ?: ($user->role?->name ?? 'Tenaga Pendidik') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Status Hadir Hari Ini: Masuk & Pulang (Grid 2 Kolom Rapi) -->
<div class="mobile-grid-2 mb-2">
    <!-- Presensi Masuk -->
    <div class="card border-0 shadow-sm rounded-3 {{ $checkInLog ? 'border-start border-success border-3' : '' }}">
        <div class="card-body p-2 d-flex flex-column justify-content-between h-100">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted fw-semibold small" style="font-size: 0.68rem;">
                    <i class="ti ti-login text-success me-0.5"></i> Presensi Masuk
                </span>
                @if($checkInLog)
                    <span class="badge bg-success-lt" style="font-size: 0.6rem; padding: 1px 5px;">
                        {{ $checkInLog->status === 'terlambat' ? 'Terlambat' : 'Tepat Waktu' }}
                    </span>
                @endif
            </div>

            <div class="my-1">
                @if($checkInLog)
                    <div class="fw-bold text-success fs-2 lh-1">
                        {{ $checkInLog->scanned_at->format('H:i') }} <span style="font-size: 0.65rem;">WIB</span>
                    </div>
                    <div class="text-muted small mt-1 text-truncate" style="font-size: 0.68rem;">
                        {{ $checkInLog->verify_type_label }}
                    </div>
                @else
                    <div class="fw-bold text-danger fs-3 lh-1">Belum Absen</div>
                    <div class="text-muted small mt-1" style="font-size: 0.68rem;">Pagi sebelum KBM</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Presensi Pulang -->
    <div class="card border-0 shadow-sm rounded-3 {{ $checkOutLog ? 'border-start border-primary border-3' : '' }}">
        <div class="card-body p-2 d-flex flex-column justify-content-between h-100">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted fw-semibold small" style="font-size: 0.68rem;">
                    <i class="ti ti-logout text-primary me-0.5"></i> Presensi Pulang
                </span>
                @if($checkOutLog)
                    <span class="badge bg-blue-lt" style="font-size: 0.6rem; padding: 1px 5px;">Selesai</span>
                @endif
            </div>

            <div class="my-1">
                @if($checkOutLog)
                    <div class="fw-bold text-primary fs-2 lh-1">
                        {{ $checkOutLog->scanned_at->format('H:i') }} <span style="font-size: 0.65rem;">WIB</span>
                    </div>
                    <div class="text-muted small mt-1 text-truncate" style="font-size: 0.68rem;">
                        {{ $checkOutLog->verify_type_label }}
                    </div>
                @else
                    <div class="fw-bold text-secondary fs-3 lh-1">Belum Pulang</div>
                    <div class="text-muted small mt-1" style="font-size: 0.68rem;">Akhir jam kerja</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 3. Radar Lokasi GPS (Struktur Rapi, Tulisan Teratur) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <!-- Header Radar -->
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <div class="d-flex align-items-center gap-1.5">
                <div id="gpsPulseIcon" class="avatar avatar-sm bg-primary-lt text-primary rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                    <i class="ti ti-map-pin fs-3"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-4 lh-sm">Deteksi Radius GPS</div>
                    <div class="text-muted small" id="gpsStatusText" style="font-size: 0.68rem;">Mencari koordinat satelit...</div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary btn-icon rounded-2 flex-shrink-0 shadow-none" onclick="requestUserLocation(true)" title="Segarkan GPS" aria-label="Segarkan GPS" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                <i class="ti ti-refresh fs-3"></i>
            </button>
        </div>

        <!-- Box Hasil Perhitungan Jarak & Status Radius -->
        <div class="p-2 bg-light rounded-2 mb-1">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small" style="font-size: 0.68rem;">Jarak Anda dari Sekolah:</div>
                    <div id="distanceDisplay" class="fw-bold fs-2 text-dark lh-sm mt-0.5">
                        <span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span> Menghitung...
                    </div>
                </div>
                <div class="text-end">
                    <span id="radiusBadge" class="badge bg-secondary text-white px-2 py-1" style="font-size: 0.7rem;">
                        Mengecek Area
                    </span>
                    <div class="mt-1">
                        <a id="btnViewMap" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-link text-decoration-none p-0 d-none" style="font-size: 0.68rem;">
                            <i class="ti ti-map-2 me-0.5"></i> Lihat di Peta
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-1 pt-1 border-top d-flex align-items-center justify-content-between text-muted" style="font-size: 0.68rem;">
                <span id="accuracyDisplay">Sinyal GPS: -</span>
                <span>Radius Izin: <strong>{{ $setting->school_radius_meters }} meter</strong></span>
            </div>
        </div>

        <div class="text-muted small d-flex align-items-center gap-1 mt-0.5" style="font-size: 0.68rem; line-height: 1.2;">
            <i class="ti ti-building text-primary flex-shrink-0"></i>
            <span class="text-truncate">Titik Pusat: <strong>{{ $setting->school_name }}</strong></span>
        </div>
    </div>
</div>

<!-- 4. Tombol Utama Aksi Presensi (Fokus Utama Halaman) -->
<div class="card shadow-sm rounded-4 mb-2 overflow-hidden" style="background: #ffffff; border: 1.5px solid #16a34a !important;">
    <div class="card-body p-2.5 px-3">
        <form id="attendanceForm" action="{{ route('attendance.geotag.store') }}" method="POST">
            @csrf
            <input type="hidden" name="latitude" id="formLat">
            <input type="hidden" name="longitude" id="formLon">
            <input type="hidden" name="accuracy" id="formAcc">
            <input type="hidden" name="type" id="formType" value="{{ $checkInLog ? 'check_out' : 'check_in' }}">

            <!-- Status Indicator Header -->
            <div class="d-flex align-items-center justify-content-between mb-2">
                @if(!$checkInLog)
                    <span class="badge bg-success-lt text-success fw-bold px-2 py-0.5" style="font-size: 0.68rem;">
                        <i class="ti ti-login me-1"></i>SIAP PRESENSI MASUK
                    </span>
                    <span class="text-muted small" style="font-size: 0.68rem;">Jam Kerja Guru</span>
                @else
                    <span class="badge {{ $checkOutLog ? 'bg-teal-lt text-teal' : 'bg-blue-lt text-primary' }} fw-bold px-2 py-0.5" style="font-size: 0.68rem;">
                        <i class="ti {{ $checkOutLog ? 'ti-check' : 'ti-logout' }} me-1"></i>{{ $checkOutLog ? 'PRESENSI HARI INI LENGKAP' : 'SIAP PRESENSI PULANG' }}
                    </span>
                    <span class="text-muted small" style="font-size: 0.68rem;">
                        {{ $checkOutLog ? 'Pulang: ' . $checkOutLog->scanned_at->format('H:i') . ' WIB' : 'Masuk: ' . $checkInLog->scanned_at->format('H:i') . ' WIB' }}
                    </span>
                @endif
            </div>

            <!-- Tombol Utama Presensi (Besar, Berwarna & Fokus Utama) -->
            <div>
                @if(!$checkInLog)
                    <button type="button" id="btnCheckIn" class="btn-hero-action btn-hero-masuk" onclick="submitAttendance('check_in')">
                        <div class="btn-hero-icon">
                            <i class="ti ti-map-pin-check"></i>
                        </div>
                        <div class="btn-hero-content">
                            <span class="btn-hero-sup">Rekam Sekarang</span>
                            <span class="btn-hero-title">Presensi Masuk</span>
                            <span class="btn-hero-subtitle">Ketuk untuk mencatat jam kehadiran masuk</span>
                        </div>
                        <div class="btn-hero-arrow">
                            <i class="ti ti-arrow-right"></i>
                        </div>
                    </button>
                @else
                    <button type="button" id="btnCheckOut" class="btn-hero-action btn-hero-pulang" onclick="submitAttendance('check_out')">
                        <div class="btn-hero-icon">
                            <i class="ti ti-door-exit"></i>
                        </div>
                        <div class="btn-hero-content">
                            <span class="btn-hero-sup">{{ $checkOutLog ? 'Perbarui Sekarang' : 'Rekam Sekarang' }}</span>
                            <span class="btn-hero-title">Presensi Pulang</span>
                            <span class="btn-hero-subtitle">{{ $checkOutLog ? 'Sudah absen pulang pukul ' . $checkOutLog->scanned_at->format('H:i') . ' WIB' : 'Ketuk untuk mencatat jam kepulangan' }}</span>
                        </div>
                        <div class="btn-hero-arrow">
                            <i class="ti ti-arrow-right"></i>
                        </div>
                    </button>
                @endif
            </div>

            <!-- Catatan Tambahan (Opsional, Rapih & Minimalis) -->
            <details class="mt-2 text-center" style="font-size: 0.74rem;">
                <summary class="text-muted small d-inline-flex align-items-center justify-content-center gap-1 py-1" style="list-style: none; cursor: pointer; user-select: none;">
                    <i class="ti ti-notes text-secondary"></i>
                    <span>Tambah catatan khusus presensi (opsional)</span>
                    <i class="ti ti-chevron-down text-muted" style="font-size: 0.65rem;"></i>
                </summary>
                <div class="mt-1.5 pt-1 border-top text-start">
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Mengajar jam ke-1 / Petugas piket..." style="font-size: 0.78rem;">
                </div>
            </details>
        </form>
    </div>
</div>

<!-- 5. Riwayat Presensi Mandiri (Feed List Card Mobile Bersih) -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-2 d-flex align-items-center justify-content-between">
            <span class="d-flex align-items-center gap-1.5">
                <i class="ti ti-history text-primary"></i> Riwayat Presensi Terakhir
            </span>
            <span class="badge bg-light text-muted" style="font-size: 0.65rem;">7 Hari</span>
        </div>

        @if($recentLogs->count() > 0)
            <div class="d-flex flex-column gap-1.5">
                @foreach($recentLogs as $log)
                    @php
                        $payload = json_decode($log->raw_payload, true) ?? [];
                        $dist = $payload['distance_to_school_meters'] ?? null;
                        $isCheckIn = $log->is_check_in;
                    @endphp
                    <div class="p-2 rounded-2 border bg-white d-flex align-items-center justify-content-between">
                        <div class="overflow-hidden pe-2">
                            <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                <span class="badge {{ $isCheckIn ? 'bg-success-lt text-success' : 'bg-blue-lt text-primary' }}" style="font-size: 0.62rem; padding: 2px 6px;">
                                    {{ $isCheckIn ? 'Masuk' : 'Pulang' }}
                                </span>
                                <span class="fw-bold text-dark fs-4 lh-sm">
                                    {{ $log->scanned_at->format('H:i') }} WIB
                                </span>
                            </div>
                            <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                                {{ $log->scanned_at->format('d/m/Y') }}
                                &bull; {{ $log->verify_type_label }}
                                @if($dist !== null)
                                    &bull; Jarak: <strong>{{ $dist }}m</strong>
                                @endif
                                @if(!empty($payload['notes']))
                                    &bull; <em>{{ $payload['notes'] }}</em>
                                @endif
                            </div>
                        </div>

                        <span class="avatar avatar-sm {{ $isCheckIn ? 'bg-success-lt text-success' : 'bg-blue-lt text-primary' }} rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                            <i class="{{ $isCheckIn ? 'ti ti-login' : 'ti ti-logout' }} fs-4"></i>
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-3 text-muted small">
                <i class="ti ti-notes-off fs-1 opacity-50 d-block mb-1"></i>
                Belum ada riwayat presensi mandiri tercatat.
            </div>
        @endif
    </div>
</div>

<!-- Sheet Modal Konfirmasi Presensi In-App (Tanpa browser alert popup) -->
<div class="sheet-modal" id="sheet-geotag-confirm" style="height: auto; border-radius: 20px 20px 0 0; background: #ffffff;">
    <div class="sheet-modal-inner p-3 pb-4">
        <div class="d-flex justify-content-center mb-2">
            <div style="width: 40px; height: 4px; background: #cbd5e1; border-radius: 999px;"></div>
        </div>
        <div class="text-center py-2">
            <div class="avatar avatar-lg rounded-circle mx-auto mb-2" id="geotagModalIcon" style="width: 56px; height: 56px; background: #e0f2fe; color: #0284c7;">
                <i class="ti ti-map-pin-check fs-1" id="geotagModalIconI"></i>
            </div>
            <h3 class="fw-bold mb-1" id="geotagModalTitle" style="color: #0f172a; font-size: 1.15rem;">Konfirmasi Presensi</h3>
            <p class="text-muted small mb-3 px-2" id="geotagModalDesc">Apakah Anda yakin ingin merekam presensi sekarang?</p>
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold border" onclick="closeGeotagModal()">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="btnGeotagConfirmSubmit" onclick="executeAttendanceSubmit()">
                    Ya, Rekam Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sheet Modal Peringatan/Info In-App -->
<div class="sheet-modal" id="sheet-geotag-alert" style="height: auto; border-radius: 20px 20px 0 0; background: #ffffff;">
    <div class="sheet-modal-inner p-3 pb-4">
        <div class="d-flex justify-content-center mb-2">
            <div style="width: 40px; height: 4px; background: #cbd5e1; border-radius: 999px;"></div>
        </div>
        <div class="text-center py-2">
            <div class="avatar avatar-lg rounded-circle mx-auto mb-2" id="geotagAlertIcon" style="width: 56px; height: 56px; background: #fee2e2; color: #dc2626;">
                <i class="ti ti-alert-triangle fs-1" id="geotagAlertIconI"></i>
            </div>
            <h3 class="fw-bold mb-1" id="geotagAlertTitle" style="color: #0f172a; font-size: 1.15rem;">Pemberitahuan</h3>
            <p class="text-muted small mb-3 px-3" id="geotagAlertDesc" style="white-space: pre-line; line-height: 1.4;"></p>
            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="closeGeotagAlertModal()">
                    Mengerti
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Konfigurasi Geofencing Sekolah dari Server
    const SCHOOL_CONFIG = {
        lat: {{ (float) ($setting->school_latitude ?? -7.95590200) }},
        lon: {{ (float) ($setting->school_longitude ?? 111.97259400) }},
        radius: {{ (int) ($setting->school_radius_meters ?? 100) }},
        strict: {{ $setting->geotag_strict_radius ? 'true' : 'false' }},
        requireSelfie: false
    };

    let userCurrentLocation = null;

    // Jam Digital Realtime
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const clockEl = document.getElementById('liveClock');
        if (clockEl) {
            clockEl.textContent = `${hours}:${minutes}:${seconds}`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Haversine Distance Formula
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // meter
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c);
    }

    // Update Status Tombol Presensi Berdasarkan Radius GPS
    function updateButtonRadiusState(isInside, distance) {
        const activeBtn = document.getElementById('btnCheckIn') || document.getElementById('btnCheckOut');
        if (!activeBtn) return;

        const subtitleEl = activeBtn.querySelector('.btn-hero-subtitle');

        if (isInside) {
            activeBtn.classList.remove('btn-hero-out-radius');
            if (subtitleEl && activeBtn.dataset.originalSubtitle) {
                subtitleEl.textContent = activeBtn.dataset.originalSubtitle;
            }
        } else {
            activeBtn.classList.add('btn-hero-out-radius');
            if (subtitleEl) {
                if (!activeBtn.dataset.originalSubtitle) {
                    activeBtn.dataset.originalSubtitle = subtitleEl.textContent;
                }
                subtitleEl.textContent = `Di luar radius (${distance} m dari batas ${SCHOOL_CONFIG.radius} m)`;
            }
        }
    }

    // Geolocation Request Handler
    function requestUserLocation(manual = false) {
        const statusText = document.getElementById('gpsStatusText');
        const distanceDisplay = document.getElementById('distanceDisplay');
        const accuracyDisplay = document.getElementById('accuracyDisplay');
        const radiusBadge = document.getElementById('radiusBadge');
        const pulseIcon = document.getElementById('gpsPulseIcon');

        if (!navigator.geolocation) {
            statusText.textContent = 'GPS tidak didukung perangkat.';
            distanceDisplay.innerHTML = '<span class="text-danger fs-4">Tidak Didukung</span>';
            updateButtonRadiusState(false, '?');
            return;
        }

        statusText.textContent = 'Mengunci sinyal satelit...';
        if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-primary-lt text-primary rounded-circle flex-shrink-0';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                userCurrentLocation = {
                    lat: position.coords.latitude,
                    lon: position.coords.longitude,
                    accuracy: Math.round(position.coords.accuracy)
                };

                // Populate Form Fields
                document.getElementById('formLat').value = userCurrentLocation.lat;
                document.getElementById('formLon').value = userCurrentLocation.lon;
                document.getElementById('formAcc').value = userCurrentLocation.accuracy;

                const distance = calculateDistance(
                    userCurrentLocation.lat,
                    userCurrentLocation.lon,
                    SCHOOL_CONFIG.lat,
                    SCHOOL_CONFIG.lon
                );

                const isInside = distance <= SCHOOL_CONFIG.radius;

                statusText.textContent = `Terkunci (${userCurrentLocation.lat.toFixed(4)}, ${userCurrentLocation.lon.toFixed(4)})`;
                distanceDisplay.innerHTML = `${distance} <span class="fs-4 text-muted fw-normal">meter</span>`;
                accuracyDisplay.textContent = `Sinyal: ±${userCurrentLocation.accuracy} m`;

                if (isInside) {
                    radiusBadge.className = 'badge bg-success text-white px-2 py-1';
                    radiusBadge.innerHTML = '<i class="ti ti-check me-0.5"></i> Di Dalam Area';
                    if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-success-lt text-success rounded-circle flex-shrink-0';
                } else {
                    radiusBadge.className = 'badge bg-danger text-white px-2 py-1';
                    radiusBadge.innerHTML = '<i class="ti ti-x me-0.5"></i> Di Luar Radius';
                    if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-danger-lt text-danger rounded-circle flex-shrink-0';
                }

                // Perbarui tampilan tombol: warna gray dan tanpa animasi jika di luar radius
                updateButtonRadiusState(isInside, distance);

                const btnMap = document.getElementById('btnViewMap');
                if (btnMap) {
                    btnMap.href = `https://www.google.com/maps?q=${userCurrentLocation.lat},${userCurrentLocation.lon}`;
                    btnMap.classList.remove('d-none');
                }

                if (manual) {
                    distanceDisplay.classList.add('text-primary');
                    setTimeout(() => distanceDisplay.classList.remove('text-primary'), 1000);
                }
            },
            function(error) {
                let errText = 'Gagal mendeteksi lokasi.';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errText = 'Izin lokasi (GPS) ditolak browser.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errText = 'Sinyal GPS tidak tersedia.';
                        break;
                    case error.TIMEOUT:
                        errText = 'Waktu permintaan GPS habis.';
                        break;
                }
                statusText.textContent = errText;
                distanceDisplay.innerHTML = '<span class="text-danger fs-4">Belum Terkunci</span>';
                if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-secondary-lt text-secondary rounded-circle flex-shrink-0';
                updateButtonRadiusState(false, '?');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 5000
            }
        );
    }

    let pendingAttendanceType = null;

    function showGeotagAlert(title, message, isError = true) {
        const titleEl = document.getElementById('geotagAlertTitle');
        const descEl = document.getElementById('geotagAlertDesc');
        const iconDiv = document.getElementById('geotagAlertIcon');
        const iconI = document.getElementById('geotagAlertIconI');

        if (titleEl) titleEl.textContent = title;
        if (descEl) descEl.textContent = message;

        if (iconDiv && iconI) {
            if (isError) {
                iconDiv.style.background = '#fee2e2';
                iconDiv.style.color = '#dc2626';
                iconI.className = 'ti ti-alert-triangle fs-1';
            } else {
                iconDiv.style.background = '#e0f2fe';
                iconDiv.style.color = '#0284c7';
                iconI.className = 'ti ti-info-circle fs-1';
            }
        }

        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-geotag-alert');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.open('#sheet-geotag-alert');
        } else {
            const el = document.getElementById('sheet-geotag-alert');
            if (el) el.classList.add('modal-in');
        }
    }

    function closeGeotagAlertModal() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-geotag-alert');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.close('#sheet-geotag-alert');
        } else {
            const el = document.getElementById('sheet-geotag-alert');
            if (el) el.classList.remove('modal-in');
        }
    }

    function closeGeotagModal() {
        pendingAttendanceType = null;
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-geotag-confirm');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.close('#sheet-geotag-confirm');
        } else {
            const el = document.getElementById('sheet-geotag-confirm');
            if (el) el.classList.remove('modal-in');
        }
    }

    function executeAttendanceSubmit() {
        const type = pendingAttendanceType;
        if (!type) return;

        closeGeotagModal();

        const form = document.getElementById('attendanceForm');
        const submitBtn = type === 'check_in' ? document.getElementById('btnCheckIn') : document.getElementById('btnCheckOut');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <div class="d-flex align-items-center justify-content-center w-100 py-1 gap-2 text-white">
                    <span class="spinner-border spinner-border-sm text-white" role="status"></span>
                    <span class="fw-bold fs-3">Menyimpan Presensi...</span>
                </div>
            `;
        }
        form.submit();
    }

    // Submit Attendance Action
    function submitAttendance(type) {
        if (!userCurrentLocation) {
            showGeotagAlert(
                'GPS Belum Terkunci',
                'Lokasi GPS belum terkunci. Mohon tunggu beberapa detik atau tekan tombol "Segarkan GPS", lalu pastikan akses lokasi diaktifkan.',
                true
            );
            requestUserLocation(true);
            return;
        }

        const distance = calculateDistance(
            userCurrentLocation.lat,
            userCurrentLocation.lon,
            SCHOOL_CONFIG.lat,
            SCHOOL_CONFIG.lon
        );

        // Validasi pembatasan radius: jika di luar radius, tolak dan beri notifikasi
        if (distance > SCHOOL_CONFIG.radius) {
            showGeotagAlert(
                'Di Luar Radius Sekolah',
                `Presensi tidak dapat dilakukan karena Anda berada di luar radius ketentuan sekolah.\n\nJarak Anda: ${distance} meter (Batas maksimal: ${SCHOOL_CONFIG.radius} meter).`,
                true
            );
            return;
        }

        document.getElementById('formType').value = type;
        pendingAttendanceType = type;

        const actionText = type === 'check_in' ? 'Presensi Masuk' : 'Presensi Pulang';
        const titleEl = document.getElementById('geotagModalTitle');
        const descEl = document.getElementById('geotagModalDesc');
        const submitBtn = document.getElementById('btnGeotagConfirmSubmit');
        const iconDiv = document.getElementById('geotagModalIcon');
        const iconI = document.getElementById('geotagModalIconI');

        if (titleEl) titleEl.textContent = `Konfirmasi ${actionText}`;
        if (descEl) descEl.textContent = `Apakah Anda yakin ingin merekam ${actionText} sekarang?`;
        if (submitBtn) {
            submitBtn.className = type === 'check_in' 
                ? 'btn btn-success rounded-pill px-4 fw-bold' 
                : 'btn btn-primary rounded-pill px-4 fw-bold';
            submitBtn.textContent = `Ya, ${actionText}`;
        }
        if (iconDiv && iconI) {
            if (type === 'check_in') {
                iconDiv.style.background = '#dcfce7';
                iconDiv.style.color = '#15803d';
                iconI.className = 'ti ti-door-enter fs-1';
            } else {
                iconDiv.style.background = '#e0f2fe';
                iconDiv.style.color = '#0284c7';
                iconI.className = 'ti ti-door-exit fs-1';
            }
        }

        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-geotag-confirm');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.open('#sheet-geotag-confirm');
        } else {
            const el = document.getElementById('sheet-geotag-confirm');
            if (el) el.classList.add('modal-in');
        }
    }

    // Auto-request location on load
    document.addEventListener('DOMContentLoaded', function() {
        requestUserLocation();
        fitGeotagUserName();
    });

    function fitGeotagUserName() {
        const container = document.getElementById('geotagUserNameContainer');
        const text = document.getElementById('geotagUserNameText');
        if (!container || !text) return;
        const maxW = container.clientWidth - 4;
        if (maxW <= 0) return;
        let size = parseFloat(window.getComputedStyle(text).fontSize);
        while (text.scrollWidth > maxW && size > 9.5) {
            size -= 0.5;
            text.style.fontSize = size + 'px';
        }
    }
    fitGeotagUserName();
    window.addEventListener('resize', fitGeotagUserName);
</script>
@endpush