@extends('layouts.mobile')

@section('title', 'Profil Akun GTK — APK SIAP')
@section('page_title', 'Profil Akun GTK')
@section('page_subtitle', $user->name)
@section('back_url', 'back')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<style>
    @keyframes slideUpCropModal {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    /* Styling Khusus Kanvas Cropper agar rapi & tidak bocor ke luar */
    .cropper-container {
        overflow: hidden !important;
        border-radius: 12px !important;
        max-width: 100% !important;
    }
    .cropper-view-box {
        border-radius: 16px !important;
        outline: 2.5px solid #38bdf8 !important;
    }
    .cropper-face {
        border-radius: 16px !important;
        background-color: transparent !important;
    }
    .cropper-modal {
        background-color: rgba(15, 23, 42, 0.7) !important;
    }
    .cropper-dashed,
    .cropper-point.point-se,
    .cropper-line {
        display: none !important;
    }
</style>
@endpush

@section('content')
@php
    $nigsVal = $user->nigs ?: ($user->id_number !== '-' ? $user->id_number : null);
    $nigsDisplay = $nigsVal ? "NIGS.{$nigsVal}" : ($user->formatted_id !== '-' ? $user->formatted_id : 'GTK');
    $primaryPosition = $user->structuralPositions->first()?->name ?: ($user->position ?: ($user->role?->name ?? 'Tenaga Pendidik'));
    $userSubjectsList = $userSubjects ?? collect();
    $nameLen = mb_strlen($user->name);
    $initialFontSize = match (true) {
        $nameLen > 36 => '0.82rem',
        $nameLen > 28 => '0.92rem',
        $nameLen > 22 => '1.02rem',
        default => '1.18rem',
    };
@endphp

<!-- =========================================================================
     1. KARTU IDENTITAS PROFIL UTAMA (WARNA BIRU KHAS SIAP SEPERTI BERANDA)
     ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-2.5 text-white" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important;">
    <div class="card-body p-3">
        <!-- Nama Lengkap Adaptif Berada di Tengah (Otomatis Menyesuaikan Ukuran Agar Tetap 1 Baris) -->
        <div id="profileNameContainer" class="text-center fw-bold text-white mb-3 pb-2" style="border-bottom: 1px solid rgba(255, 255, 255, 0.2); letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <span id="profileNameText" style="display: inline-block; white-space: nowrap; font-size: {{ $initialFontSize }}; line-height: 1.3;">
                {{ $user->name }}
            </span>
        </div>

        <!-- Dua Kolom: Kiri Foto, Kanan Detail Identitas (Sejajar dengan Waka Kurikulum) -->
        <div class="d-flex align-items-start gap-3">
            <!-- Kolom Kiri: Foto Bentuk Kotak Round (Sejajar Puncak Kolom Kanan) -->
            <div class="flex-shrink-0" style="width: 88px;">
                @if($user->avatar)
                    <img id="avatarProfileDisplay" src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" 
                         class="border border-2 border-white shadow-sm d-block" 
                         style="width: 88px; height: 88px; border-radius: 14px; object-fit: cover;">
                @else
                    <div id="avatarProfileDisplayPlaceholder" 
                         class="border border-2 border-white shadow-sm d-flex align-items-center justify-content-center fw-bold fs-1" 
                         style="width: 88px; height: 88px; border-radius: 14px; background-color: var(--brand-gold) !important; color: #fff !important;">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                @endif
            </div>

            <!-- Kolom Kanan: Jabatan, NIGS, No. Telp, Status GTK -->
            <div class="overflow-hidden flex-fill">
                <!-- Jabatan (Waka Kurikulum / Kepala Sekolah / dll) -->
                <div class="text-truncate mb-1.5" style="line-height: 1.2;">
                    <span class="badge text-white fw-bold text-truncate" style="background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.35); font-size: 0.74rem; padding: 3px 8px; max-width: 100%;">
                        {{ $primaryPosition }}
                    </span>
                </div>

                <!-- NIGS -->
                <div class="fw-semibold text-white small text-truncate" style="font-size: 0.78rem;">
                    <i class="ti ti-id me-1 text-white-50"></i>{{ $nigsDisplay }}
                </div>

                <!-- No Telp -->
                <div class="fw-semibold text-white small mt-1 text-truncate" style="font-size: 0.78rem;">
                    <i class="ti ti-phone me-1 text-white-50"></i>{{ $user->phone ?: '-' }}
                </div>

                <!-- Status GTK -->
                <div class="mt-1.5 d-flex align-items-center gap-1 flex-wrap">
                    <span class="badge text-white fw-bold" style="background: rgba(168, 85, 247, 0.3); border: 1px solid rgba(168, 85, 247, 0.5); font-size: 0.65rem; padding: 2px 7px;">
                        {{ $user->gtk_status ?: 'GTY' }}
                    </span>
                    <span class="badge bg-success text-white fw-bold" style="font-size: 0.65rem; padding: 2px 7px;">
                        Aktif
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     2. KARTU DETAIL INFORMASI GTK
     ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-2.5 bg-white" style="border: 1.5px solid #cbd5e1 !important;">
    <div class="card-body p-3">
        <!-- Email -->
        <div class="d-flex align-items-start justify-content-between py-1.5 border-bottom gap-2">
            <span class="text-muted small fw-semibold flex-shrink-0" style="font-size: 0.76rem; width: 105px;">
                <i class="ti ti-mail me-1 text-primary"></i> Email
            </span>
            <span class="fw-semibold text-dark small text-end text-break" style="font-size: 0.76rem;">
                {{ $user->email }}
            </span>
        </div>

        <!-- Telegram ID -->
        <div class="d-flex align-items-center justify-content-between py-1.5 border-bottom gap-2">
            <span class="text-muted small fw-semibold flex-shrink-0" style="font-size: 0.76rem; width: 105px;">
                <i class="ti ti-brand-telegram me-1 text-info"></i> Telegram ID
            </span>
            <div class="text-end">
                <span class="fw-semibold text-dark small" style="font-size: 0.76rem;">{{ $user->telegram_chat_id ?: '-' }}</span>
                @if($user->telegram_chat_id)
                    <span class="badge bg-success-lt text-success ms-1" style="font-size: 0.62rem; padding: 1px 5px;">
                        <i class="ti ti-check me-0.5"></i> Terhubung
                    </span>
                @else
                    <span class="badge bg-warning-lt text-warning ms-1" style="font-size: 0.62rem; padding: 1px 5px;">
                        Belum Terhubung
                    </span>
                @endif
            </div>
        </div>

        <!-- NUPTK - NIP -->
        <div class="d-flex align-items-center justify-content-between py-1.5 border-bottom gap-2">
            <span class="text-muted small fw-semibold flex-shrink-0" style="font-size: 0.76rem; width: 105px;">
                <i class="ti ti-numbers me-1 text-muted"></i> NUPTK - NIP
            </span>
            <span class="fw-semibold text-dark small text-end" style="font-size: 0.76rem;">
                {{ $user->nuptk ?: '-' }} - {{ $user->nip ?: '-' }}
            </span>
        </div>

        <!-- Mata Pelajaran -->
        <div class="d-flex align-items-start justify-content-between pt-1.5 gap-2">
            <span class="text-muted small fw-semibold flex-shrink-0" style="font-size: 0.76rem; width: 105px;">
                <i class="ti ti-book me-1 text-warning"></i> Mata Pelajaran
            </span>
            <div class="text-end text-dark small fw-semibold" style="font-size: 0.76rem;">
                @if($userSubjectsList->isNotEmpty())
                    {{ $userSubjectsList->join(' | ') }}
                @elseif($user->subject)
                    {{ $user->subject }}
                @else
                    <span class="text-muted">-</span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     2B. KARTU PENUGASAN & SK GTK RESMI
     ========================================================================= -->
@php
    $activeDecreesList = $activeDecrees ?? collect();
    $activeStructuralList = $activeStructuralPositions ?? collect();
    $activeHomeroomList = $activeHomeroom ?? collect();
@endphp
<div class="card border-0 shadow-sm rounded-3 mb-2.5 bg-white" style="border: 1.5px solid #cbd5e1 !important;">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1.5 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 28px; height: 28px; background: #4f46e5;">
                    <i class="ti ti-certificate fs-3"></i>
                </span>
                <span class="fw-bold text-dark small" style="font-size: 0.82rem;">Penugasan &amp; Dokumen SK</span>
            </div>
            <a href="{{ route('gtk-decrees.index') }}" class="small text-decoration-none fw-semibold external" style="font-size: 0.7rem; color: #4f46e5;">
                Semua SK <i class="ti ti-chevron-right"></i>
            </a>
        </div>

        {{-- Tugas Tambahan / Struktural & Wali Kelas --}}
        @if($activeHomeroomList->isNotEmpty() || $activeStructuralList->isNotEmpty())
            <div class="mb-2 p-2 rounded-2" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="text-muted small fw-semibold mb-1" style="font-size: 0.68rem;">Tugas Tambahan / Peran:</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($activeHomeroomList as $homeroom)
                        <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.65rem; padding: 2px 6px;">
                            <i class="ti ti-school me-0.5"></i> Wali Kelas {{ $homeroom->name }}
                        </span>
                    @endforeach
                    @foreach($activeStructuralList as $stPos)
                        <span class="badge bg-indigo-lt text-indigo fw-bold" style="font-size: 0.65rem; padding: 2px 6px;">
                            <i class="ti ti-briefcase me-0.5"></i> {{ $stPos->structuralPosition?->name ?? 'Jabatan' }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Daftar Berkas SK Aktif --}}
        @if($activeDecreesList->isNotEmpty())
            <div class="d-flex flex-column gap-1.5">
                @foreach($activeDecreesList as $decree)
                    <div class="p-2 rounded-2 border d-flex align-items-center justify-content-between gap-2" style="background: #ffffff; border-color: #f1f5f9 !important;">
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.74rem;">
                                {{ $decree->number }}
                            </div>
                            <div class="text-muted text-truncate" style="font-size: 0.66rem;">
                                {{ $decree->title }} &bull; {{ $decree->tmt_date ? $decree->tmt_date->format('d/m/Y') : '' }}
                            </div>
                        </div>
                        @if($decree->file_path && $decree->hasPrivateDocument())
                            <a href="{{ route('gtk-decrees.document', $decree) }}" class="btn btn-sm btn-outline-primary rounded-pill py-1 px-2.5 fw-bold text-decoration-none external flex-shrink-0 d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;">
                                <i class="ti ti-download fs-4"></i> PDF
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-2 text-muted" style="font-size: 0.72rem;">
                <i class="ti ti-info-circle me-1"></i> Belum ada dokumen SK tersinkronisasi.
            </div>
        @endif
    </div>
</div>

<!-- =========================================================================
     3. TIGA TOMBOL AKSI SEJAJAR SATU BARIS: [Edit Data] [Ganti Sandi] [Keluar Akun]
     ========================================================================= -->
<div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 8px !important; width: 100% !important; margin-bottom: 1rem !important;">
    <!-- Tombol 1: Edit Data -->
    <a href="{{ route('profile.edit') }}" 
       class="btn btn-primary rounded-pill py-2 px-1 fw-bold shadow-sm external text-decoration-none d-flex align-items-center justify-content-center gap-1" 
       style="width: 100% !important; min-width: 0 !important; font-size: 0.75rem; min-height: 40px; box-sizing: border-box !important;">
        <i class="ti ti-edit fs-3 flex-shrink-0"></i>
        <span class="text-truncate">Edit Data</span>
    </a>

    <!-- Tombol 2: Ganti Sandi -->
    <button type="button" 
            onclick="openPasswordSheet()" 
            class="btn btn-outline-warning text-dark rounded-pill py-2 px-1 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1" 
            style="width: 100% !important; min-width: 0 !important; font-size: 0.75rem; min-height: 40px; background: #fffdf5; border-color: #f59e0b !important; box-sizing: border-box !important;">
        <i class="ti ti-lock fs-3 text-warning flex-shrink-0"></i>
        <span class="text-truncate">Ganti Sandi</span>
    </button>

    <!-- Tombol 3: Keluar dari Akun -->
    <button type="button" 
            onclick="confirmLogoutApp()" 
            class="btn btn-outline-danger rounded-pill py-2 px-1 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1" 
            style="width: 100% !important; min-width: 0 !important; font-size: 0.75rem; min-height: 40px; background: #fef2f2; border-color: #ef4444 !important; box-sizing: border-box !important;">
        <i class="ti ti-logout fs-3 text-danger flex-shrink-0"></i>
        <span class="text-truncate">Keluar</span>
    </button>
</div>

<!-- Form Tersembunyi untuk Logout -->
<form id="formLogoutApp" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>
@endsection

<!-- =========================================================================
     MODAL BOTTOM SHEETS
     ========================================================================= -->
@push('modals')

<!-- 2. MODAL BOTTOM SHEET: UBAH KATA SANDI -->
<div class="sheet-modal sheet-password" id="sheet-password" style="height: auto; max-height: 85vh; border-radius: 20px 20px 0 0; background: #ffffff; overflow: hidden !important;">
    <div class="sheet-modal-inner d-flex flex-column" style="height: 100% !important; max-height: 85vh !important; overflow: hidden !important;">
        <!-- Header Standar Seragam Sesuai Pengesahan Supervisi -->
        <div class="d-flex align-items-center justify-content-between px-3.5 py-3 border-bottom flex-shrink-0" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.95) 0%, rgba(25, 91, 158, 0.9) 100%); border-radius: 20px 20px 0 0; z-index: 10; min-height: 60px;">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-warning text-dark rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="ti ti-lock fs-3"></i>
                </span>
                <div>
                    <div class="fw-bold text-white fs-3 lh-1">Ubah Kata Sandi</div>
                    <div class="text-white-50 small mt-0.5" style="font-size: 0.72rem;">Keamanan Akun GTK</div>
                </div>
            </div>
            <a href="javascript:void(0)" class="sheet-close text-white opacity-75 d-inline-flex align-items-center justify-content-center text-decoration-none me-2" style="font-size: 1.6rem; width: 34px; height: 34px; line-height: 1;" onclick="closePasswordSheet(); return false;">&times;</a>
        </div>

        <form action="{{ route('profile.password') }}" method="POST" class="d-flex flex-column flex-fill overflow-hidden m-0">
            @csrf
            <div class="flex-fill overflow-auto p-3" style="-webkit-overflow-scrolling: touch; overscroll-behavior-y: contain;">
                <div class="mb-2">
                    <label class="form-label required small fw-bold">Kata Sandi Saat Ini</label>
                    <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Masukkan password lama" required>
                </div>
                <div class="mb-2">
                    <label class="form-label required small fw-bold">Kata Sandi Baru</label>
                    <input type="password" name="password" class="form-control form-control-sm" minlength="6" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="mb-2">
                    <label class="form-label required small fw-bold">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="password_confirmation" class="form-control form-control-sm" minlength="6" placeholder="Ulangi password baru" required>
                </div>
            </div>

            <!-- Sticky Action Footer Bar -->
            <div class="p-2.5 px-3 border-top bg-white flex-shrink-0 d-flex gap-2 shadow-xs" style="z-index: 20; padding-bottom: calc(0.85rem + env(safe-area-inset-bottom, 12px)) !important;">
                <button type="button" class="btn btn-secondary flex-fill rounded-pill py-2 fw-semibold" onclick="closePasswordSheet()">
                    Batal
                </button>
                <button type="submit" class="btn btn-warning text-dark flex-fill rounded-pill py-2 fw-bold shadow-sm" style="background-color: var(--brand-gold) !important; border-color: var(--brand-gold) !important; color: #fff !important;">
                    <i class="ti ti-key me-1"></i> Perbarui Sandi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL BOTTOM SHEET: KONFIRMASI KELUAR -->
<div class="sheet-modal sheet-inapp-confirm" id="sheet-logout-confirm" style="height: auto; border-radius: 20px 20px 0 0; background: #ffffff;">
    <div class="sheet-modal-inner p-3 text-center" style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 16px)) !important;">
        <div class="avatar avatar-md bg-danger-lt text-danger rounded-circle mb-2 mx-auto" style="width: 48px; height: 48px;">
            <i class="ti ti-logout fs-1"></i>
        </div>
        <div class="fw-bold text-dark fs-3 mb-1">Konfirmasi Keluar</div>
        <div class="small text-muted mb-3" style="line-height: 1.35;">
            Apakah Anda yakin ingin keluar dari akun SIAP GTK? Anda harus login kembali untuk mengakses aplikasi.
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill rounded-pill py-2 fw-semibold" onclick="closeLogoutConfirm()">
                Batal
            </button>
            <button type="button" class="btn btn-danger flex-fill rounded-pill py-2 fw-bold shadow-sm" onclick="document.getElementById('formLogoutApp').submit()">
                Ya, Keluar Akun
            </button>
        </div>
    </div>
</div>

<!-- 4. STANDALONE OVERLAY MODAL: ATUR & SESUAIKAN POSISI FOTO (CROP & ZOOM)
     Didesain independen tanpa terpengaruh backdrop luar, tombol tajam dan 100% responsif -->
<div id="modal-crop-avatar" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.88); z-index: 9999999 !important; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); align-items: flex-end; justify-content: center; padding: 0; margin: 0;">
    <div class="bg-white w-100 d-flex flex-column" style="max-height: 94vh; border-radius: 24px 24px 0 0; overflow: hidden; box-shadow: 0 -12px 36px rgba(0,0,0,0.4); animation: slideUpCropModal 0.22s ease-out;">
        <!-- Header Standar Seragam Sesuai Pengesahan Supervisi -->
        <div class="d-flex align-items-center justify-content-between px-3 py-2.5 flex-shrink-0" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.98) 0%, rgba(25, 91, 158, 0.95) 100%);">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-white text-primary rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="ti ti-crop fs-3"></i>
                </span>
                <div>
                    <div class="fw-bold text-white fs-3 lh-1">Atur Posisi Foto</div>
                </div>
            </div>
            <a href="javascript:void(0)" onclick="cancelCropAvatar();" class="text-white opacity-75 d-inline-flex align-items-center justify-content-center text-decoration-none p-0" style="font-size: 1.7rem; width: 32px; height: 32px; line-height: 1; cursor: pointer;" title="Tutup">&times;</a>
        </div>

        <div class="p-3 overflow-auto" style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 16px)) !important;">
            <!-- Panduan Singkat -->
            <div class="text-center text-muted small mb-2" style="font-size: 0.75rem;">
                <i class="ti ti-hand-finger me-1 text-primary"></i> Geser foto atau cubit (zoom) agar wajah berada di dalam bingkai.
            </div>

            <!-- Area Kanvas Cropper dengan Tinggi Pasti & Terkunci -->
            <div class="position-relative overflow-hidden rounded-3 mb-2.5 bg-dark d-flex align-items-center justify-content-center" style="width: 100%; height: 270px; max-height: 42vh;">
                <img id="cropperSourceImg" src="" alt="Foto Sumber" style="display: block; max-width: 100%; max-height: 100%;">
            </div>

            <!-- Tombol Kontrol Cepat: Zoom In, Zoom Out, Putar, Reset (Ikon Saja Tanpa Teks) -->
            <div class="d-flex align-items-center justify-content-center gap-2.5 mb-3">
                <button type="button" class="btn btn-sm btn-light border rounded-circle text-dark shadow-sm d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" onclick="cropperZoom(0.1)" title="Zoom In (Perbesar)">
                    <i class="ti ti-zoom-in fs-2 text-primary"></i>
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-circle text-dark shadow-sm d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" onclick="cropperZoom(-0.1)" title="Zoom Out (Perkecil)">
                    <i class="ti ti-zoom-out fs-2 text-primary"></i>
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-circle text-dark shadow-sm d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" onclick="cropperRotate(90)" title="Putar 90 Derajat">
                    <i class="ti ti-rotate fs-2 text-dark"></i>
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-circle text-dark shadow-sm d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" onclick="cropperReset()" title="Reset Posisi">
                    <i class="ti ti-refresh fs-2 text-dark"></i>
                </button>
            </div>

            <!-- Tombol Aksi Bawah: Batal & Gunakan Foto -->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary flex-fill rounded-pill py-2.5 fw-bold" onclick="cancelCropAvatar()">
                    Batal
                </button>
                <button type="button" class="btn btn-primary flex-fill rounded-pill py-2.5 fw-bold shadow d-flex align-items-center justify-content-center gap-1.5" onclick="applyCroppedAvatar()">
                    <i class="ti ti-check fs-3"></i>
                    <span>Gunakan Foto Ini</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
    // 0. Auto-Fit Ukuran Font Nama Pengguna (Selalu 1 Baris Adaptif)
    function fitProfileName() {
        const container = document.getElementById('profileNameContainer');
        const text = document.getElementById('profileNameText');
        if (!container || !text) return;

        const maxW = container.clientWidth - 4;
        if (maxW <= 0) return;

        let currentSize = parseFloat(window.getComputedStyle(text).fontSize);
        while (text.scrollWidth > maxW && currentSize > 9.5) {
            currentSize -= 0.5;
            text.style.fontSize = currentSize + 'px';
        }
    }

    fitProfileName();
    document.addEventListener('DOMContentLoaded', fitProfileName);
    window.addEventListener('resize', fitProfileName);

    // 1. Edit Profile Sheet Handlers
    function openEditProfileSheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-edit-profile');
        } else if (window.f7) {
            window.f7.sheet.open('#sheet-edit-profile');
        } else {
            const el = document.getElementById('sheet-edit-profile');
            if (el) el.classList.add('modal-in');
        }
    }
    function closeEditProfileSheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-edit-profile');
        } else if (window.f7) {
            window.f7.sheet.close('#sheet-edit-profile');
        } else {
            const el = document.getElementById('sheet-edit-profile');
            if (el) el.classList.remove('modal-in');
        }
    }

    // 2. Change Password Sheet Handlers
    function openPasswordSheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-password');
        } else if (window.f7) {
            window.f7.sheet.open('#sheet-password');
        } else {
            const el = document.getElementById('sheet-password');
            if (el) el.classList.add('modal-in');
        }
    }
    function closePasswordSheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-password');
        } else if (window.f7) {
            window.f7.sheet.close('#sheet-password');
        } else {
            const el = document.getElementById('sheet-password');
            if (el) el.classList.remove('modal-in');
        }
    }

    // 3. Standalone Cropper & Photo Adjustment Handlers
    let cropperInstance = null;

    // Pindahkan modal crop langsung ke document.body agar mutlak di layer paling atas
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-crop-avatar');
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
    });

    function startAvatarCropper(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const modal = document.getElementById('modal-crop-avatar');
                const img = document.getElementById('cropperSourceImg');

                // Pastikan modal menempel di document.body
                if (modal && modal.parentNode !== document.body) {
                    document.body.appendChild(modal);
                }

                // Sembunyikan sheet edit profil & backdrop F7 sementara agar tidak menutupi
                const editSheet = document.getElementById('sheet-edit-profile');
                if (editSheet) {
                    editSheet.style.visibility = 'hidden';
                }
                document.querySelectorAll('.sheet-backdrop').forEach(function(b) {
                    b.style.visibility = 'hidden';
                });

                img.src = e.target.result;
                modal.style.display = 'flex';

                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }

                setTimeout(function() {
                    cropperInstance = new Cropper(img, {
                        aspectRatio: 1, // 1:1 persegi pas dengan kartu GTK
                        viewMode: 1, // tidak keluar batas gambar
                        autoCropArea: 0.88,
                        responsive: true,
                        restore: false,
                        checkOrientation: true,
                        guides: false,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        dragMode: 'move', // Default touch geser foto
                    });
                }, 120);
            };

            reader.readAsDataURL(file);
        }
    }

    function cropperZoom(ratio) {
        if (cropperInstance) cropperInstance.zoom(ratio);
    }
    function cropperRotate(deg) {
        if (cropperInstance) cropperInstance.rotate(deg);
    }
    function cropperReset() {
        if (cropperInstance) cropperInstance.reset();
    }

    function cancelCropAvatar() {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        const modal = document.getElementById('modal-crop-avatar');
        if (modal) modal.style.display = 'none';

        // Tampilkan kembali formulir edit data & backdropnya
        const editSheet = document.getElementById('sheet-edit-profile');
        if (editSheet) {
            editSheet.style.visibility = 'visible';
        }
        document.querySelectorAll('.sheet-backdrop').forEach(function(b) {
            b.style.visibility = 'visible';
        });
    }

    function applyCroppedAvatar() {
        if (!cropperInstance) return;

        // Ambil potongan tajam 500x500 px
        const canvas = cropperInstance.getCroppedCanvas({
            width: 500,
            height: 500,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) return;

        const base64Data = canvas.toDataURL('image/jpeg', 0.92);
        const base64Input = document.getElementById('avatarCroppedBase64');
        if (base64Input) {
            base64Input.value = base64Data;
        }

        canvas.toBlob(function(blob) {
            if (blob) {
                try {
                    const croppedFile = new File([blob], 'avatar_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(croppedFile);
                    const fileInput = document.getElementById('editAvatarFileInput');
                    if (fileInput) {
                        fileInput.files = dt.files;
                    }
                } catch (err) {
                    console.warn('DataTransfer File API fallback ke base64:', err);
                }
            }

            // Perbarui preview foto di modal Edit Data
            let imgEl = document.getElementById('avatarEditPreviewImg');
            const placeholderEl = document.getElementById('avatarEditPreviewPlaceholder');

            if (!imgEl) {
                imgEl = document.createElement('img');
                imgEl.id = 'avatarEditPreviewImg';
                imgEl.className = 'border shadow-sm d-block';
                imgEl.style.width = '68px';
                imgEl.style.height = '68px';
                imgEl.style.borderRadius = '12px';
                imgEl.style.objectFit = 'cover';
                imgEl.style.borderColor = '#cbd5e1';
                if (placeholderEl && placeholderEl.parentNode) {
                    placeholderEl.parentNode.replaceChild(imgEl, placeholderEl);
                }
            }
            imgEl.src = base64Data;

            const label = document.getElementById('labelPilihFoto');
            if (label) {
                label.textContent = 'Atur Ulang Posisi Foto';
            }

            cancelCropAvatar();
        }, 'image/jpeg', 0.92);
    }

    // 4. Logout In-App Confirm Handlers
    function confirmLogoutApp() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-logout-confirm');
        } else if (window.f7) {
            window.f7.sheet.open('#sheet-logout-confirm');
        } else {
            const el = document.getElementById('sheet-logout-confirm');
            if (el) el.classList.add('modal-in');
        }
    }
    function closeLogoutConfirm() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-logout-confirm');
        } else if (window.f7) {
            window.f7.sheet.close('#sheet-logout-confirm');
        } else {
            const el = document.getElementById('sheet-logout-confirm');
            if (el) el.classList.remove('modal-in');
        }
    }
</script>
@endpush
