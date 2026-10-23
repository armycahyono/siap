@extends('layouts.app')

@section('title', 'Profil Sekolah — ' . ($setting->school_name ?? 'SMK PGRI KRAS'))

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $settingActions = '';
    if (auth()->user()->hasPermission('manage_settings') || auth()->user()->hasRole(['super_admin', 'waka_kurikulum'])) {
        $settingActions = '<ul class="nav nav-pills gap-2" id="profileTab" role="tablist">' .
            '<li class="nav-item" role="presentation"><button class="nav-link active fw-bold btn btn-sm btn-outline-light text-white shadow-xs" id="view-tab" data-bs-toggle="tab" data-bs-target="#tab-view" type="button" role="tab"><i class="ti ti-eye me-1"></i> Tinjau Profil</button></li>' .
            '<li class="nav-item" role="presentation"><button class="nav-link fw-bold btn btn-sm btn-warning text-dark shadow-xs" id="edit-tab" data-bs-toggle="tab" data-bs-target="#tab-edit" type="button" role="tab"><i class="ti ti-pencil me-1"></i> Edit Data Profil</button></li>' .
            '</ul>';
    }
@endphp
@include('layouts._hero_header', [
    'title' => 'Profil Sekolah',
    'subtitle' => 'Identitas resmi, legalitas kelembagaan, status akreditasi, serta visi, misi, dan tujuan sekolah.',
    'icon' => 'ti ti-building',
    'category' => 'INFORMASI KELEMBAGAAN',
    'actions' => $settingActions
])

<div class="tab-content" id="profileTabContent">
    <!-- TAB 1: TAMPILAN PROFIL SEKOLAH (DAPAT DIAKSES SEMUA USER) -->
    <div class="tab-pane fade show active" id="tab-view" role="tabpanel">
        <!-- Hero Card Profil Sekolah -->
        <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="background: linear-gradient(135deg, #102d52 0%, #1e4976 100%);">
            <div class="card-body p-4 p-md-5 text-white">
                <div class="row align-items-center g-4">
                    <div class="col-auto">
                        @if($setting->logo_path)
                            <img src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo {{ $setting->school_name }}" style="max-height: 85px; max-width: 120px; object-fit: contain; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.25));">
                        @else
                            <div class="avatar avatar-xl bg-yellow text-dark rounded-3 fw-bold shadow-lg fs-1" style="width: 80px; height: 80px; background-color: var(--brand-gold) !important; color: #fff !important;">
                                SIAP
                            </div>
                        @endif
                    </div>
                    <div class="col">
                        @if($setting->foundation_name)
                            <div class="text-uppercase tracking-wider small text-blue-200" style="color: #93c5fd; letter-spacing: 0.08rem;">
                                {{ $setting->foundation_name }}
                            </div>
                        @endif
                        <h1 class="display-6 fw-bold mb-1 text-white">{{ $setting->school_name }}</h1>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                            <span class="badge bg-white text-dark fw-bold px-2 py-1 shadow-sm">
                                <i class="ti ti-id me-1 text-primary"></i> NPSN: {{ $setting->school_npsn ?: 'Belum diisi' }}
                            </span>
                            <span class="badge bg-warning text-dark fw-bold px-2 py-1 shadow-sm">
                                <i class="ti ti-award me-1"></i> Akreditasi: {{ $setting->accreditation_grade ?: 'Terakreditasi' }}
                            </span>
                            <span class="badge bg-azure text-white fw-bold px-2 py-1 shadow-sm">
                                <i class="ti ti-calendar me-1"></i> TP. {{ $setting->academic_year }} (Semester {{ $setting->semester }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- 1. Identitas Sekolah -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                            <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle me-2">
                                <i class="ti ti-school fs-3"></i>
                            </span>
                            1. Identitas Sekolah
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nama Yayasan / Penyelenggara</div>
                            <div class="fw-bold text-dark fs-3">{!! nl2br(e($setting->foundation_name ?: '-')) !!}</div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nama Resmi Sekolah</div>
                            <div class="fw-bold text-dark fs-3">{{ $setting->school_name ?: '-' }}</div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nomor Pokok Sekolah Nasional (NPSN)</div>
                            <div class="fw-bold text-dark fs-3 font-monospace">{{ $setting->school_npsn ?: '-' }}</div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Alamat Lengkap</div>
                            <div class="fw-bold text-dark">{{ $setting->school_address ?: '-' }}</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small">Nomor Telepon</div>
                                <div class="fw-bold text-dark">
                                    <i class="ti ti-phone me-1 text-primary"></i>
                                    {{ $setting->school_phone ?: '-' }}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Email Resmi</div>
                                <div class="fw-bold text-dark">
                                    <i class="ti ti-mail me-1 text-primary"></i>
                                    {{ $setting->school_email ?: '-' }}
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Website Resmi</div>
                                <div class="fw-bold text-dark">
                                    <i class="ti ti-world me-1 text-primary"></i>
                                    @if($setting->school_website)
                                        <a href="{{ str_starts_with($setting->school_website, 'http') ? $setting->school_website : 'https://' . $setting->school_website }}" target="_blank" class="text-primary text-decoration-none">
                                            {{ $setting->school_website }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Legalitas dan Status Sekolah -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                            <span class="avatar avatar-sm bg-success-lt text-success rounded-circle me-2">
                                <i class="ti ti-certificate fs-3"></i>
                            </span>
                            2. Legalitas dan Status Sekolah
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Tanggal Berdiri</div>
                            <div class="fw-bold text-dark fs-3">
                                {{ $setting->established_date ? $setting->established_date->translatedFormat('d F Y') : '-' }}
                            </div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nomor SK Pendirian</div>
                            <div class="fw-bold text-dark fs-3">{{ $setting->establishment_decree ?: '-' }}</div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nomor SK Izin Operasional</div>
                            <div class="fw-bold text-dark fs-3">{{ $setting->operational_decree ?: '-' }}</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-muted small">Masa Berlaku SK Operasional</div>
                            <div class="fw-bold text-dark fs-3">
                                @if($setting->operational_decree_valid_until)
                                    {{ $setting->operational_decree_valid_until->translatedFormat('d F Y') }}
                                    @if($setting->operational_decree_valid_until->isPast())
                                        <span class="badge bg-danger ms-2">Kedaluwarsa</span>
                                    @else
                                        <span class="badge bg-success-lt ms-2">Masih Berlaku</span>
                                    @endif
                                @else
                                    <span class="text-muted">Tetap / Belum Diatur</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Akreditasi Sekolah -->
            <div class="col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                            <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle me-2">
                                <i class="ti ti-award fs-3"></i>
                            </span>
                            3. Akreditasi Sekolah
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3 pb-2 border-bottom">
                            <div class="col-sm-6">
                                <div class="text-muted small">Status / Nilai Akreditasi</div>
                                <div class="fw-bold text-dark fs-2 text-warning">
                                    {{ $setting->accreditation_grade ?: '-' }}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Tahun Akreditasi</div>
                                <div class="fw-bold text-dark fs-2">
                                    {{ $setting->accreditation_year ?: '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted small">Nomor SK Akreditasi</div>
                            <div class="fw-bold text-dark fs-3">{{ $setting->accreditation_decree ?: '-' }}</div>
                        </div>
                        <div class="row g-3 mb-3 pb-2 border-bottom">
                            <div class="col-sm-6">
                                <div class="text-muted small">Tanggal SK Akreditasi</div>
                                <div class="fw-bold text-dark">
                                    {{ $setting->accreditation_date ? $setting->accreditation_date->translatedFormat('d F Y') : '-' }}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Masa Berlaku Akreditasi</div>
                                <div class="fw-bold text-dark">
                                    {{ $setting->accreditation_valid_until ? $setting->accreditation_valid_until->translatedFormat('d F Y') : '-' }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small">Lembaga Akreditasi</div>
                            <div class="fw-bold text-dark">
                                <i class="ti ti-shield-check me-1 text-success"></i>
                                {{ $setting->accreditation_institution ?: 'Badan Akreditasi Nasional (BAN-PDM / BAN-S/M)' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Visi, Misi dan Tujuan -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                            <span class="avatar avatar-sm bg-purple-lt text-purple rounded-circle me-2">
                                <i class="ti ti-target-arrow fs-3"></i>
                            </span>
                            4. Visi, Misi dan Tujuan Sekolah
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border-start border-primary border-4">
                                    <h5 class="fw-bold text-primary mb-2 d-flex align-items-center">
                                        <i class="ti ti-eye me-2"></i> VISI SEKOLAH
                                    </h5>
                                    <div class="text-dark fs-3 lh-base">
                                        @if($setting->vision)
                                            {!! nl2br(e($setting->vision)) !!}
                                        @else
                                            <span class="text-muted fst-italic">Visi sekolah belum diisi. Hubungi Administrator untuk memperbarui data.</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light border-start border-teal border-4 h-100">
                                    <h5 class="fw-bold text-teal mb-2 d-flex align-items-center">
                                        <i class="ti ti-list-check me-2"></i> MISI SEKOLAH
                                    </h5>
                                    <div class="text-dark lh-base">
                                        @if($setting->mission)
                                            {!! nl2br(e($setting->mission)) !!}
                                        @else
                                            <span class="text-muted fst-italic">Misi sekolah belum diisi.</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light border-start border-warning border-4 h-100">
                                    <h5 class="fw-bold text-warning mb-2 d-flex align-items-center">
                                        <i class="ti ti-flag me-2"></i> TUJUAN SEKOLAH
                                    </h5>
                                    <div class="text-dark lh-base">
                                        @if($setting->goals)
                                            {!! nl2br(e($setting->goals)) !!}
                                        @else
                                            <span class="text-muted fst-italic">Tujuan sekolah belum diisi.</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->hasPermission('manage_settings') || auth()->user()->hasRole(['super_admin', 'waka_kurikulum']))
    <!-- TAB 2: FORM EDIT PROFIL & PENGATURAN (KHUSUS ADMIN / WAKA) -->
    <div class="tab-pane fade" id="tab-edit" role="tabpanel">
        <div class="card border-0 shadow-sm" style="max-width: 960px;">
            <div class="card-body p-4">
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- 1. IDENTITAS SEKOLAH -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle me-2 fw-bold">1</span>
                        <h4 class="fw-bold text-dark m-0">Identitas Sekolah</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nama Yayasan / Penyelenggara (Untuk Kop Surat)</label>
                            <textarea name="foundation_name" class="form-control" rows="2" placeholder="Nama Yayasan baris demi baris...">{{ old('foundation_name', $setting->foundation_name) }}</textarea>
                            <div class="form-text small">Tuliskan nama yayasan lengkap dengan enter/baris baru sesuai susunan pada kop surat resmi.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required fw-bold">Nama Sekolah</label>
                            <input type="text" name="school_name" class="form-control" value="{{ old('school_name', $setting->school_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">NPSN</label>
                            <input type="text" name="school_npsn" class="form-control" value="{{ old('school_npsn', $setting->school_npsn) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Alamat Lengkap</label>
                            <textarea name="school_address" class="form-control" rows="2">{{ old('school_address', $setting->school_address) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nomor Telepon</label>
                            <input type="text" name="school_phone" class="form-control" value="{{ old('school_phone', $setting->school_phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Email Sekolah</label>
                            <input type="email" name="school_email" class="form-control" value="{{ old('school_email', $setting->school_email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Website Resmi</label>
                            <input type="text" name="school_website" class="form-control" value="{{ old('school_website', $setting->school_website) }}">
                        </div>
                    </div>

                    <!-- 2. LEGALITAS DAN STATUS SEKOLAH -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-success-lt text-success rounded-circle me-2 fw-bold">2</span>
                        <h4 class="fw-bold text-dark m-0">Legalitas dan Status Sekolah</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Berdiri</label>
                            <input type="date" name="established_date" class="form-control" value="{{ old('established_date', $setting->established_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SK Pendirian</label>
                            <input type="text" name="establishment_decree" class="form-control" placeholder="Nomor SK Pendirian..." value="{{ old('establishment_decree', $setting->establishment_decree) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SK Operasional</label>
                            <input type="text" name="operational_decree" class="form-control" placeholder="Nomor SK Izin Operasional..." value="{{ old('operational_decree', $setting->operational_decree) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Masa Berlaku SK Operasional</label>
                            <input type="date" name="operational_decree_valid_until" class="form-control" value="{{ old('operational_decree_valid_until', $setting->operational_decree_valid_until?->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <!-- 3. AKREDITASI -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle me-2 fw-bold">3</span>
                        <h4 class="fw-bold text-dark m-0">Akreditasi</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status / Nilai Akreditasi</label>
                            <input type="text" name="accreditation_grade" class="form-control" placeholder="Contoh: A (Unggul) / 92" value="{{ old('accreditation_grade', $setting->accreditation_grade) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tahun Akreditasi</label>
                            <input type="text" name="accreditation_year" class="form-control" placeholder="Contoh: 2023" value="{{ old('accreditation_year', $setting->accreditation_year) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nomor SK Akreditasi</label>
                            <input type="text" name="accreditation_decree" class="form-control" placeholder="Nomor SK Akreditasi..." value="{{ old('accreditation_decree', $setting->accreditation_decree) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal SK Akreditasi</label>
                            <input type="date" name="accreditation_date" class="form-control" value="{{ old('accreditation_date', $setting->accreditation_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Masa Berlaku Akreditasi</label>
                            <input type="date" name="accreditation_valid_until" class="form-control" value="{{ old('accreditation_valid_until', $setting->accreditation_valid_until?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Lembaga Akreditasi</label>
                            <input type="text" name="accreditation_institution" class="form-control" placeholder="BAN-PDM / BAN-S/M" value="{{ old('accreditation_institution', $setting->accreditation_institution ?: 'BAN-PDM') }}">
                        </div>
                    </div>

                    <!-- 4. VISI, MISI DAN TUJUAN -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-purple-lt text-purple rounded-circle me-2 fw-bold">4</span>
                        <h4 class="fw-bold text-dark m-0">Visi, Misi dan Tujuan Sekolah</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12">
                            <label class="form-label fw-bold">Visi Sekolah</label>
                            <textarea name="vision" class="form-control" rows="3" placeholder="Tuliskan rumusan Visi Sekolah...">{{ old('vision', $setting->vision) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Misi Sekolah</label>
                            <textarea name="mission" class="form-control" rows="5" placeholder="Tuliskan butir-butir Misi Sekolah...">{{ old('mission', $setting->mission) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tujuan Sekolah</label>
                            <textarea name="goals" class="form-control" rows="5" placeholder="Tuliskan butir-butir Tujuan Sekolah...">{{ old('goals', $setting->goals) }}</textarea>
                        </div>
                    </div>

                    <!-- 5. LOGO RESMI SEKOLAH -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-indigo-lt text-indigo rounded-circle me-2 fw-bold">5</span>
                        <h4 class="fw-bold text-dark m-0">Logo Resmi Sekolah</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Unggah Logo Sekolah</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-muted">Format berkas gambar (JPG, PNG, WebP) maksimal 2MB. Disarankan PNG berlatar transparan.</small>
                            @if($setting->logo_path)
                            <div class="mt-2 d-flex align-items-center">
                                <img src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo Saat Ini" class="me-2 rounded-1 border bg-white p-1" style="max-height: 48px; max-width: 65px; object-fit: contain;">
                                <small class="text-muted">Logo saat ini terpasang</small>
                            </div>
                            @endif
                        </div>
                    <!-- 6. KOORDINAT LOKASI & GEOTAGGING PRESENSI GTK -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-sm bg-teal-lt text-teal rounded-circle me-2 fw-bold">6</span>
                        <h4 class="fw-bold text-dark m-0">Koordinat Lokasi &amp; Presensi Geotagging GPS (Mobile / PWA)</h4>
                    </div>
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12">
                            <div class="alert alert-info py-2 px-3 small mb-2 d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="ti ti-info-circle me-1"></i> Titik koordinat ini digunakan sebagai pusat penghitungan radius jarak presensi mandiri GTK di aplikasi HP/PWA.
                                </div>
                                <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="getCurrentAdminLocation()">
                                    <i class="ti ti-crosshair me-1"></i> Ambil GPS Perangkat Ini
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Latitude (Garis Lintang)</label>
                            <input type="number" step="any" id="inputSchoolLat" name="school_latitude" class="form-control font-monospace" placeholder="-7.95590200" value="{{ old('school_latitude', $setting->school_latitude) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Longitude (Garis Bujur)</label>
                            <input type="number" step="any" id="inputSchoolLon" name="school_longitude" class="form-control font-monospace" placeholder="111.97259400" value="{{ old('school_longitude', $setting->school_longitude) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Radius Toleransi (Meter)</label>
                            <div class="input-group">
                                <input type="number" name="school_radius_meters" class="form-control" min="10" max="5000" placeholder="100" value="{{ old('school_radius_meters', $setting->school_radius_meters ?? 100) }}">
                                <span class="input-group-text">meter</span>
                            </div>
                            <small class="text-muted">Batas maksimal jarak dari sekolah (disarankan 100 - 250m).</small>
                        </div>
                        <div class="col-12">
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="geotag_attendance_enabled" value="1" {{ old('geotag_attendance_enabled', $setting->geotag_attendance_enabled) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-bold">Aktifkan Presensi Geotagging</span>
                                    </label>
                                    <small class="text-muted d-block ps-4">Izinkan GTK melakukan presensi mandiri dari HP.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="geotag_strict_radius" value="1" {{ old('geotag_strict_radius', $setting->geotag_strict_radius) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-bold">Batas Radius Ketat</span>
                                    </label>
                                    <small class="text-muted d-block ps-4">Tolak presensi jika guru berada di luar radius meter yang ditentukan.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="geotag_require_selfie" value="1" {{ old('geotag_require_selfie', $setting->geotag_require_selfie) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-bold">Wajib Foto Selfie</span>
                                    </label>
                                    <small class="text-muted d-block ps-4">GTK wajib mengambil foto kamera depan saat presensi.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Seluruh Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function getCurrentAdminLocation() {
        if (!navigator.geolocation) {
            alert('Perangkat/browser Anda tidak mendukung GPS Geolocation.');
            return;
        }

        const latInput = document.getElementById('inputSchoolLat');
        const lonInput = document.getElementById('inputSchoolLon');

        navigator.geolocation.getCurrentPosition(
            function(position) {
                latInput.value = position.coords.latitude.toFixed(8);
                lonInput.value = position.coords.longitude.toFixed(8);
                alert(`Berhasil mengambil titik koordinat GPS: \nLatitude: ${latInput.value}\nLongitude: ${lonInput.value}\nAkurasi: ±${Math.round(position.coords.accuracy)} meter`);
            },
            function(err) {
                alert('Gagal mengambil koordinat lokasi: ' + err.message + '\nPastikan izin lokasi telah diberikan di browser Anda.');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000
            }
        );
    }
</script>
@endpush
