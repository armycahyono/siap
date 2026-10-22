@extends('layouts.web')

@section('title', 'Presensi Geotagging GPS GTK')

@section('content')
<div class="container-xl py-3" style="max-width: 680px;">
    <!-- Alert Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible shadow-sm border-0 fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-circle-check fs-2 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible shadow-sm border-0 fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-2 me-2"></i>
                <div>{{ session('warning') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible shadow-sm border-0 fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-circle-x fs-2 me-2"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Profile & Header Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
        <div class="card-body text-white p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-1 gap-md-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-white-lt text-white px-2 py-1 me-1 text-decoration-none" title="Kembali ke Beranda">
                        <i class="ti ti-arrow-left me-1"></i> Beranda
                    </a>
                    <span class="badge bg-white-lt text-white px-2 py-1">
                        <i class="ti ti-device-mobile me-1"></i> Presensi Mandiri GTK
                    </span>
                    <span class="badge bg-azure-lt text-white px-2 py-1">
                        {{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
                <div class="text-end ms-auto">
                    <div id="liveClock" class="fw-bold font-monospace fs-2 lh-1 text-warning">--:--:--</div>
                    <small class="text-white-50" style="font-size: 0.72rem;">WIB</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="avatar avatar-lg rounded-circle border border-2 border-white shadow-sm flex-shrink-0" style="object-fit: cover;">
                @else
                    <span class="avatar avatar-lg rounded-circle bg-warning text-dark fw-bold border border-2 border-white shadow-sm fs-2 flex-shrink-0">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </span>
                @endif
                <div class="flex-fill text-truncate">
                    <h3 class="mb-0 text-white fw-bold text-truncate">{{ $user->name }}</h3>
                    <div class="text-white-50 small mt-1 d-flex flex-wrap gap-2 align-items-center">
                        @if($user->nigs)
                            <span><i class="ti ti-id me-1"></i>NIGS: {{ $user->nigs }}</span>
                        @endif
                        <span><i class="ti ti-briefcase me-1"></i>{{ $user->position ?: 'Guru / Staf' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GPS Geolocation Radar Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div id="gpsPulseIcon" class="avatar avatar-sm bg-primary-lt text-primary rounded-circle flex-shrink-0">
                        <i class="ti ti-map-pin fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">Deteksi Posisi GPS</h4>
                        <small class="text-muted" id="gpsStatusText">Mencari koordinat satelit...</small>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 ms-auto" onclick="requestUserLocation(true)">
                    <i class="ti ti-refresh me-1"></i> Segarkan GPS
                </button>
            </div>

            <!-- Radar / Distance Box -->
            <div id="locationCard" class="p-3 rounded-3 border bg-light mb-3">
                <div class="row align-items-center g-2">
                    <div class="col-7 col-sm-8">
                        <div class="text-muted small">Jarak dari Sekolah:</div>
                        <div id="distanceDisplay" class="fw-bold fs-2 text-dark lh-1">
                            <span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span> Menghitung...
                        </div>
                        <div id="accuracyDisplay" class="text-muted small mt-1" style="font-size: 0.75rem;">
                            Akurasi sinyal: -
                        </div>
                    </div>
                    <div class="col-5 col-sm-4 text-end">
                        <span id="radiusBadge" class="badge bg-secondary text-white px-2 py-1 text-nowrap">
                            Mengecek Area
                        </span>
                        <div class="mt-2">
                            <a id="btnViewMap" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-link text-decoration-none p-0 d-none" style="font-size: 0.72rem;">
                                <i class="ti ti-map-2 me-1"></i>Lihat Peta
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="small text-muted mb-0 d-flex align-items-center gap-2">
                <i class="ti ti-building text-primary flex-shrink-0"></i>
                <div class="text-truncate">
                    <strong>Pusat Lokasi:</strong> {{ $setting->school_name }} (Radius: {{ $setting->school_radius_meters }} m)
                </div>
            </div>
        </div>
    </div>

    <!-- Presensi Action Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="card-title fw-bold m-0 d-flex align-items-center gap-2">
                <i class="ti ti-clock-check text-success fs-3"></i>
                Status &amp; Tombol Presensi Hari Ini
            </h4>
        </div>
        <div class="card-body p-4">
            <!-- 2-Column Status Info: Masuk & Pulang -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="p-3 rounded-3 border {{ $checkInLog ? 'bg-success-lt border-success' : 'bg-light' }}">
                        <div class="text-muted small mb-1">
                            <i class="ti ti-login text-success me-1"></i> Presensi Masuk
                        </div>
                        @if($checkInLog)
                            <div class="fw-bold text-success fs-2 lh-1">
                                {{ $checkInLog->scanned_at->format('H:i') }} <span class="small fs-5">WIB</span>
                            </div>
                            <div class="text-muted mt-1 small" style="font-size: 0.72rem;">
                                {{ $checkInLog->verify_type_label }} &bull; {{ $checkInLog->scanned_at->format('d/m/Y') }}
                            </div>
                        @else
                            <div class="text-muted fw-bold fs-3 lh-1">Belum Ada</div>
                            <div class="text-muted mt-1 small" style="font-size: 0.72rem;">Silakan lakukan presensi masuk</div>
                        @endif
                    </div>
                </div>

                <div class="col-6">
                    <div class="p-3 rounded-3 border {{ $checkOutLog ? 'bg-blue-lt border-primary' : 'bg-light' }}">
                        <div class="text-muted small mb-1">
                            <i class="ti ti-logout text-primary me-1"></i> Presensi Pulang
                        </div>
                        @if($checkOutLog)
                            <div class="fw-bold text-primary fs-2 lh-1">
                                {{ $checkOutLog->scanned_at->format('H:i') }} <span class="small fs-5">WIB</span>
                            </div>
                            <div class="text-muted mt-1 small" style="font-size: 0.72rem;">
                                {{ $checkOutLog->verify_type_label }} &bull; {{ $checkOutLog->scanned_at->format('d/m/Y') }}
                            </div>
                        @else
                            <div class="text-muted fw-bold fs-3 lh-1">Belum Ada</div>
                            <div class="text-muted mt-1 small" style="font-size: 0.72rem;">Presensi di akhir jam kerja</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Attendance Form -->
            <form id="attendanceForm" action="{{ route('attendance.geotag.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="latitude" id="formLat">
                <input type="hidden" name="longitude" id="formLon">
                <input type="hidden" name="accuracy" id="formAcc">
                <input type="hidden" name="type" id="formType" value="{{ $checkInLog ? 'check_out' : 'check_in' }}">
                <input type="hidden" name="selfie_base64" id="formSelfieBase64">

                <!-- Selfie Camera & Note Section (Accordion/Toggle) -->
                <div class="mb-3">
                    <label class="form-label fw-bold d-flex align-items-center justify-content-between">
                        <span>
                            <i class="ti ti-camera me-1 text-primary"></i> Foto Verifikasi Diri (Kamera HP)
                            @if($setting->geotag_require_selfie)
                                <span class="badge bg-danger ms-1">Wajib</span>
                            @else
                                <span class="badge bg-secondary-lt ms-1">Opsional</span>
                            @endif
                        </span>
                    </label>

                    <div class="d-flex align-items-center gap-3">
                        <div id="selfiePreviewWrapper" class="position-relative d-none">
                            <img id="selfiePreview" src="" alt="Selfie" class="rounded-3 border shadow-sm" style="width: 72px; height: 72px; object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 rounded-circle p-0" style="width: 20px; height: 20px; transform: translate(30%, -30%);" onclick="clearSelfie()">
                                &times;
                            </button>
                        </div>
                        <div class="flex-fill">
                            <input type="file" id="selfieFileInput" name="selfie" accept="image/*" capture="user" class="form-control" onchange="previewCapturedSelfie(this)">
                            <small class="text-muted" style="font-size: 0.75rem;">Ketuk untuk mengambil foto langsung dari kamera depan HP Anda.</small>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Contoh: Bertugas mengajar KBM atau Dinas luar...">
                </div>

                <!-- Submit Action Buttons -->
                <div class="d-grid gap-2">
                    @if(!$checkInLog)
                        <button type="button" id="btnCheckIn" class="btn btn-success btn-lg fw-bold shadow-sm py-3" onclick="submitAttendance('check_in')">
                            <i class="ti ti-map-pin me-2 fs-2"></i> Rekam Presensi Masuk Sekarang
                        </button>
                    @else
                        <button type="button" id="btnCheckOut" class="btn btn-primary btn-lg fw-bold shadow-sm py-3" onclick="submitAttendance('check_out')">
                            <i class="ti ti-logout me-2 fs-2"></i> Rekam Presensi Pulang Sekarang
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Quick GTK Routine Shortcuts -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="card-title fw-bold m-0 d-flex align-items-center gap-2">
                <i class="ti ti-apps text-primary fs-3"></i>
                Menu Rutinitas Cepat GTK Hari Ini
            </h4>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                <!-- 1. Jurnal KBM -->
                <div class="col-6">
                    <a href="{{ route('attendance.subject.index') }}" class="card card-link card-stacked border text-decoration-none h-100 p-3 hover-shadow transition">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle">
                                <i class="ti ti-book fs-3"></i>
                            </span>
                            <span class="fw-bold text-dark">Jurnal KBM</span>
                        </div>
                        <div class="small text-muted">
                            {{ $schedulesCount }} Jadwal mengajar hari ini
                        </div>
                    </a>
                </div>

                <!-- 2. Izin Siswa Perwalian -->
                <div class="col-6">
                    <a href="{{ route('attendance.homeroom-permits.index') }}" class="card card-link card-stacked border text-decoration-none h-100 p-3 hover-shadow transition">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle">
                                <i class="ti ti-clipboard-list fs-3"></i>
                            </span>
                            <span class="fw-bold text-dark">Izin Perwalian</span>
                        </div>
                        <div class="small text-muted">
                            Catat izin siswa rombel
                        </div>
                    </a>
                </div>

                <!-- 3. Piket Sekolah -->
                <div class="col-6">
                    <a href="{{ route('piket.dashboard') }}" class="card card-link card-stacked border text-decoration-none h-100 p-3 hover-shadow transition">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-sm bg-teal-lt text-teal rounded-circle">
                                <i class="ti ti-shield-check fs-3"></i>
                            </span>
                            <span class="fw-bold text-dark">Piket Sekolah</span>
                        </div>
                        <div class="small text-muted">
                            {{ $isPiketToday ? 'Tugas Piket Hari Ini' : 'Buku tamu & perizinan' }}
                        </div>
                    </a>
                </div>

                <!-- 4. Izin & Cuti GTK -->
                <div class="col-6">
                    <a href="{{ route('attendance.gtk-permits.index') }}" class="card card-link card-stacked border text-decoration-none h-100 p-3 hover-shadow transition">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-sm bg-purple-lt text-purple rounded-circle">
                                <i class="ti ti-calendar-time fs-3"></i>
                            </span>
                            <span class="fw-bold text-dark">Cuti &amp; Izin GTK</span>
                        </div>
                        <div class="small text-muted">
                            Ajukan izin dinas/sakit
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat 7 Hari Terakhir -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="card-title fw-bold m-0 d-flex align-items-center gap-2">
                <i class="ti ti-history text-muted fs-3"></i>
                Riwayat Presensi Mandiri Terakhir
            </h4>
        </div>
        <div class="card-body p-0">
            @if($recentLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-vcenter table-striped card-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Tipe</th>
                                <th>Metode</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLogs as $log)
                                @php
                                    $payload = json_decode($log->raw_payload, true) ?? [];
                                    $dist = $payload['distance_to_school_meters'] ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $log->scanned_at->format('H:i') }} WIB</div>
                                        <small class="text-muted">{{ $log->scanned_at->format('d/m/Y') }}</small>
                                    </td>
                                    <td>
                                        @if($log->is_check_in)
                                            <span class="badge bg-success-lt text-success fw-bold">Masuk</span>
                                        @else
                                            <span class="badge bg-blue-lt text-primary fw-bold">Pulang</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <i class="{{ $log->verify_icon }} me-1"></i> {{ $log->verify_type_label }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        @if($dist !== null)
                                            <div>Jarak: {{ $dist }} m</div>
                                        @endif
                                        @if(!empty($payload['notes']))
                                            <div class="text-truncate" style="max-width: 150px;">{{ $payload['notes'] }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    <i class="ti ti-notes-off fs-1 text-muted mb-2 d-block"></i>
                    Belum ada riwayat presensi geotagging tercatat.
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Konfigurasi Geofencing Sekolah dari Server
    const SCHOOL_CONFIG = {
        lat: {{ (float) ($setting->school_latitude ?? -7.95590200) }},
        lon: {{ (float) ($setting->school_longitude ?? 111.97259400) }},
        radius: {{ (int) ($setting->school_radius_meters ?? 100) }},
        strict: {{ $setting->geotag_strict_radius ? 'true' : 'false' }},
        requireSelfie: {{ $setting->geotag_require_selfie ? 'true' : 'false' }}
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

    // Geolocation Request Handler
    function requestUserLocation(manual = false) {
        const statusText = document.getElementById('gpsStatusText');
        const distanceDisplay = document.getElementById('distanceDisplay');
        const accuracyDisplay = document.getElementById('accuracyDisplay');
        const radiusBadge = document.getElementById('radiusBadge');
        const pulseIcon = document.getElementById('gpsPulseIcon');

        if (!navigator.geolocation) {
            statusText.textContent = 'Perangkat tidak mendukung GPS Geolocation.';
            distanceDisplay.innerHTML = '<span class="text-danger fs-4">GPS Tidak Didukung</span>';
            return;
        }

        statusText.textContent = 'Sedang mengunci sinyal GPS...';
        if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-primary text-white rounded-circle';

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

                statusText.textContent = `GPS Terkunci (${userCurrentLocation.lat.toFixed(5)}, ${userCurrentLocation.lon.toFixed(5)})`;
                distanceDisplay.innerHTML = `${distance} <span class="fs-4 text-muted">meter</span>`;
                accuracyDisplay.textContent = `Akurasi sinyal satelit: ±${userCurrentLocation.accuracy} meter`;

                if (isInside) {
                    radiusBadge.className = 'badge bg-success text-white px-2 py-1';
                    radiusBadge.innerHTML = '<i class="ti ti-check me-1"></i> Di Dalam Area';
                    if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-success text-white rounded-circle';
                } else {
                    radiusBadge.className = 'badge bg-danger text-white px-2 py-1';
                    radiusBadge.innerHTML = '<i class="ti ti-x me-1"></i> Di Luar Radius';
                    if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-danger text-white rounded-circle';
                }

                const btnMap = document.getElementById('btnViewMap');
                if (btnMap) {
                    btnMap.href = `https://www.google.com/maps?q=${userCurrentLocation.lat},${userCurrentLocation.lon}`;
                    btnMap.classList.remove('d-none');
                }

                if (manual) {
                    // Feedback visual
                    distanceDisplay.classList.add('text-primary');
                    setTimeout(() => distanceDisplay.classList.remove('text-primary'), 1000);
                }
            },
            function(error) {
                let errText = 'Gagal mendeteksi lokasi.';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errText = 'Izin lokasi (GPS) ditolak oleh pengguna/browser. Mohon aktifkan izin lokasi di pengaturan browser.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errText = 'Sinyal GPS tidak tersedia saat ini.';
                        break;
                    case error.TIMEOUT:
                        errText = 'Waktu permintaan GPS habis (Timeout).';
                        break;
                }
                statusText.textContent = errText;
                distanceDisplay.innerHTML = '<span class="text-danger fs-5">Lokasi Belum Terkunci</span>';
                if (pulseIcon) pulseIcon.className = 'avatar avatar-sm bg-secondary text-white rounded-circle';
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 5000
            }
        );
    }

    // Submit Attendance Action
    function submitAttendance(type) {
        if (!userCurrentLocation) {
            alert('Lokasi GPS belum terkunci. Mohon tunggu beberapa detik atau tekan tombol "Segarkan GPS", lalu izinkan akses lokasi di HP Anda.');
            requestUserLocation(true);
            return;
        }

        const distance = calculateDistance(
            userCurrentLocation.lat,
            userCurrentLocation.lon,
            SCHOOL_CONFIG.lat,
            SCHOOL_CONFIG.lon
        );

        if (SCHOOL_CONFIG.strict && distance > SCHOOL_CONFIG.radius) {
            alert(`Peringatan: Anda berada ${distance} meter dari sekolah, di luar batas toleransi ${SCHOOL_CONFIG.radius} meter. Presensi hanya dapat dilakukan di lingkungan sekolah.`);
            return;
        }

        const selfieInput = document.getElementById('selfieFileInput');
        if (SCHOOL_CONFIG.requireSelfie && (!selfieInput.files || selfieInput.files.length === 0)) {
            alert('Foto selfie verifikasi wajib diambil sebelum menyimpan presensi.');
            selfieInput.focus();
            return;
        }

        document.getElementById('formType').value = type;

        const actionText = type === 'check_in' ? 'Presensi Masuk' : 'Presensi Pulang';
        if (confirm(`Apakah Anda yakin ingin merekam ${actionText} sekarang?`)) {
            const form = document.getElementById('attendanceForm');
            const submitBtn = type === 'check_in' ? document.getElementById('btnCheckIn') : document.getElementById('btnCheckOut');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan presensi...';
            }
            form.submit();
        }
    }

    // Preview Selfie
    function previewCapturedSelfie(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('selfiePreview').src = e.target.result;
                document.getElementById('selfiePreviewWrapper').classList.remove('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearSelfie() {
        document.getElementById('selfieFileInput').value = '';
        document.getElementById('selfiePreview').src = '';
        document.getElementById('selfiePreviewWrapper').classList.add('d-none');
    }

    // Auto-request location on load
    document.addEventListener('DOMContentLoaded', function() {
        requestUserLocation();
    });
</script>
@endpush
@endsection
