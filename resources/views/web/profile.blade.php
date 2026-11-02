@extends('layouts.web')

@section('title', 'Profil Pengguna — SMK PGRI Kras')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left"></i>
            </a>
            <h2 class="page-title text-navy fw-bold mb-0" style="color: #102d52;">
                <i class="ti ti-user-circle me-1"></i> Profil Akun Pengguna
            </h2>
        </div>
        <div class="text-muted small">Kelola informasi data pribadi dan kata sandi akun Anda.</div>
    </div>
</div>

<div class="row g-4">
    <!-- Biodata Form -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">Informasi Pribadi</h3>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <!-- Foto Profil Pengguna -->
                    <div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3 bg-light border border-dashed">
                        @php
                            $hasProfileAvatar = !empty($user->avatar) && file_exists(public_path('storage/' . $user->avatar));
                            $userInitial = strtoupper(substr($user->name, 0, 1));
                        @endphp
                        <div class="position-relative flex-shrink-0">
                            @if($hasProfileAvatar)
                                <img id="webAvatarPreview" src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="avatar avatar-xl rounded-3 border shadow-sm" style="width: 72px; height: 72px; object-fit: cover;">
                            @else
                                <div id="webAvatarPreview" class="avatar avatar-xl rounded-3 text-white fw-bold shadow-sm d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); font-size: 1.8rem;">
                                    {{ $userInitial }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label fw-bold mb-1 d-flex align-items-center justify-content-between">
                                <span><i class="ti ti-photo me-1 text-primary"></i> Foto Profil Pengguna</span>
                                @if($hasProfileAvatar)
                                    <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Foto Terpasang</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inisial Huruf</span>
                                @endif
                            </label>
                            <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/jpg,image/webp">
                            <small class="text-muted d-block mt-1">Format: JPG, PNG, atau WebP (Maks. 2MB). Foto akan otomatis tersinkronkan di Web dan HP.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Nama Lengkap &amp; Gelar</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">NIP</label>
                            <input type="text" name="nip" class="form-control" value="{{ old('nip', $user->nip) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NUPTK</label>
                            <input type="text" name="nuptk" class="form-control" value="{{ old('nuptk', $user->nuptk) }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mata Pelajaran</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject', $user->subject) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor WhatsApp / HP</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span><i class="ti ti-brand-telegram text-primary me-1"></i> Telegram Chat ID (Untuk Notifikasi &amp; Tembusan)</span>
                                @if($user->telegram_chat_id)
                                    <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Terhubung</span>
                                @else
                                    <span class="badge bg-warning-lt">Belum Terhubung</span>
                                @endif
                            </label>
                            <input type="text" name="telegram_chat_id" class="form-control font-monospace" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" placeholder="Contoh: 123456789">
                            <small class="text-muted">
                                Dapatkan Chat ID dengan membuka bot Telegram sekolah lalu ketik <code>/id</code> atau klik menu <b>🆔 Cek Chat ID</b>.
                            </small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">Ubah Kata Sandi</h3>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label required">Kata Sandi Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Kata Sandi Baru</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label required">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning text-dark w-100 fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                        <i class="ti ti-key me-1"></i> Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>

        <!-- Info Aplikasi Mobile (PWA) & Logout -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle">
                            <i class="ti ti-device-mobile fs-3"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark small">Aplikasi SIAP Mobile (PWA)</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Versi 2.0 &bull; SMK PGRI KRAS</div>
                        </div>
                    </div>
                    <span class="badge bg-success-lt">Aktif</span>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100 fw-bold" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun ini?')">
                        <i class="ti ti-logout me-1"></i> Keluar dari Aplikasi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('avatarInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                const preview = document.getElementById('webAvatarPreview');
                if (preview) {
                    if (preview.tagName === 'IMG') {
                        preview.src = evt.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = 'webAvatarPreview';
                        img.src = evt.target.result;
                        img.className = 'avatar avatar-xl rounded-3 border shadow-sm';
                        img.style.cssText = 'width: 72px; height: 72px; object-fit: cover;';
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
            };
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
