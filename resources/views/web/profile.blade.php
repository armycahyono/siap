@extends('layouts.web')

@section('title', 'Profil Pengguna — SMK PGRI Kras')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Profil Akun Pengguna',
    'subtitle' => 'Kelola informasi data pribadi, identitas diri, dan keamanan kata sandi akun Anda.',
    'icon' => 'ti ti-user-circle',
    'category' => 'PENGGUNA SISTEM',
    'actions' => '<a href="' . route('dashboard') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-arrow-left me-1"></i> Kembali ke Beranda</a>',
])

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
                            <label class="form-label">Nomor WhatsApp / HP</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status GTK (Kepegawaian)</label>
                            <input type="text" class="form-control bg-light" value="{{ $user->gtk_status ?: 'GTY' }} (Aktif)" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">Alamat Lengkap / Tinggal</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Alamat domisili tempat tinggal saat ini...">{{ old('address', $user->address) }}</textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span><i class="ti ti-brand-telegram text-primary me-1"></i> Telegram Chat ID (Untuk Notifikasi &amp; Tembusan)</span>
                                @if($user->telegram_chat_id)
                                    <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Terhubung (ID: {{ $user->telegram_chat_id }})</span>
                                @else
                                    <span class="badge bg-warning-lt">Belum Terhubung</span>
                                @endif
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="ti ti-id"></i></span>
                                <input type="text" name="telegram_chat_id" class="form-control font-monospace" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" placeholder="Contoh: 123456789">
                                <a href="{{ $telegramBotUrl ?? 'https://t.me/smkpgrikras_bot' }}" target="_blank" class="btn btn-outline-info d-flex align-items-center gap-1" title="Buka bot di Telegram">
                                    <i class="ti ti-brand-telegram"></i> Buka Bot
                                </a>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                Dapatkan Chat ID dengan membuka bot lalu ketik <code>/id</code>, atau ketik <code>/hubungkan {{ $user->nip ?: ($user->username ?: $user->email) }}</code> langsung di bot.
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

        <!-- Kartu 2: Penugasan Aktif & Mata Pelajaran (Otomatis dari Sistem) -->
        @php
            $userSubjectsList = $userSubjects ?? collect();
            $activeDecreesList = $activeDecrees ?? collect();
            $activeStructuralList = $activeStructuralPositions ?? collect();
            $activeHomeroomList = $activeHomeroom ?? collect();
            $activitiesList = $recentActivities ?? collect();
            $primaryPosition = $user->position ?: ($user->role?->name ?? 'GTK');
        @endphp
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 32px; height: 32px; background: #195b9e;">
                        <i class="ti ti-briefcase fs-3"></i>
                    </span>
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0" style="font-size: 0.95rem;">Penugasan Aktif &amp; Mata Pelajaran</h4>
                        <div class="text-muted" style="font-size: 0.72rem;">Informasi resmi berdasarkan jadwal KBM &amp; SK penetapan</div>
                    </div>
                </div>
                <span class="badge bg-blue-lt fw-bold">{{ $primaryPosition }}</span>
            </div>
            <div class="card-body p-4">
                <!-- Mata Pelajaran yang Diampu -->
                <div class="mb-3.5 pb-3 border-bottom">
                    <div class="text-muted small fw-bold text-uppercase tracking-wider mb-2" style="font-size: 0.72rem;">
                        <i class="ti ti-book text-warning me-1"></i> Mata Pelajaran yang Diampu (Jadwal KBM)
                    </div>
                    @if($userSubjectsList->isNotEmpty())
                        <div class="d-flex flex-wrap gap-1.5">
                            @foreach($userSubjectsList as $sbj)
                                <span class="badge bg-primary-lt text-primary fw-semibold px-2.5 py-1.5" style="font-size: 0.78rem;">
                                    <i class="ti ti-book-2 me-1"></i> {{ $sbj }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small fst-italic">
                            <i class="ti ti-info-circle me-1"></i> Belum ada mata pelajaran pada jadwal KBM aktif.
                        </div>
                    @endif
                </div>

                <!-- Tugas Tambahan & Peran Struktural -->
                <div class="mb-2">
                    <div class="text-muted small fw-bold text-uppercase tracking-wider mb-2" style="font-size: 0.72rem;">
                        <i class="ti ti-award text-indigo me-1"></i> Tugas Tambahan &amp; Peran Aktif
                    </div>
                    @if($activeHomeroomList->isNotEmpty() || $activeStructuralList->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($activeHomeroomList as $homeroom)
                                <span class="badge bg-success-lt text-success fw-bold px-2.5 py-1.5" style="font-size: 0.78rem;">
                                    <i class="ti ti-school me-1"></i> Wali Kelas {{ $homeroom->name }}
                                </span>
                            @endforeach
                            @foreach($activeStructuralList as $stPos)
                                <span class="badge bg-indigo-lt text-indigo fw-bold px-2.5 py-1.5" style="font-size: 0.78rem;">
                                    <i class="ti ti-briefcase me-1"></i> {{ $stPos->structuralPosition?->name ?? 'Jabatan Struktural' }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small fst-italic">
                            <i class="ti ti-info-circle me-1"></i> Tidak ada tugas tambahan struktural saat ini.
                        </div>
                    @endif
                </div>

                <div class="alert alert-info py-2 px-3 mt-3 mb-0 small border-0 bg-info-subtle text-info-emphasis rounded-2 d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                    <i class="ti ti-info-circle fs-3 flex-shrink-0"></i>
                    <span>Data penugasan KBM dan mata pelajaran tersinkron otomatis dari master Kurikulum &amp; Jadwal.</span>
                </div>
            </div>
        </div>

        <!-- Kartu 3: Dokumen SK GTK Resmi -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 32px; height: 32px; background: #4f46e5;">
                        <i class="ti ti-certificate fs-3"></i>
                    </span>
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0" style="font-size: 0.95rem;">Dokumen Surat Keputusan (SK) GTK</h4>
                        <div class="text-muted" style="font-size: 0.72rem;">Arsip berkas SK penugasan resmi dari Yayasan / Sekolah</div>
                    </div>
                </div>
                <a href="{{ route('gtk-decrees.index') }}" class="btn btn-sm btn-outline-primary fw-semibold">
                    Semua SK <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                @if($activeDecreesList->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-hover mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Nomor &amp; Judul SK</th>
                                    <th>TMT / Berlaku</th>
                                    <th class="text-end">Dokumen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activeDecreesList as $decree)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $decree->number }}</div>
                                            <div class="text-muted small text-truncate" style="max-width: 300px;">{{ $decree->title }}</div>
                                        </td>
                                        <td class="small text-muted">
                                            {{ $decree->tmt_date ? $decree->tmt_date->translatedFormat('d M Y') : '-' }}
                                        </td>
                                        <td class="text-end">
                                            @if($decree->file_path && $decree->hasPrivateDocument())
                                                <a href="{{ route('gtk-decrees.document', $decree) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold">
                                                    <i class="ti ti-download me-1"></i> Unduh PDF
                                                </a>
                                            @else
                                                <span class="badge bg-light text-muted">Belum ada file</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted small">
                        <i class="ti ti-file-certificate fs-1 d-block mb-1 text-muted opacity-50"></i>
                        Belum ada dokumen SK GTK aktif yang tersinkronisasi dengan akun Anda.
                    </div>
                @endif
            </div>
        </div>

        <!-- Kartu 4: Riwayat Aktivitas Akun (Audit Trail) -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 32px; height: 32px; background: #0284c7;">
                        <i class="ti ti-history fs-3"></i>
                    </span>
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0" style="font-size: 0.95rem;">Riwayat Aktivitas Akun</h4>
                        <div class="text-muted" style="font-size: 0.72rem;">Catatan 10 aktivitas keamanan &amp; operasional terakhir</div>
                    </div>
                </div>
                <span class="badge bg-light text-muted fw-bold">Audit Trail</span>
            </div>
            <div class="card-body p-0">
                @if($activitiesList->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-hover mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Aktivitas</th>
                                    <th>Deskripsi Kejadian</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activitiesList as $act)
                                    <tr>
                                        <td style="width: 140px;">
                                            <span class="badge {{ $act->action_badge_class }}">
                                                {{ $act->action_label }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-dark small" style="line-height: 1.4; max-width: 380px; word-break: break-word;">
                                                {{ $act->description }}
                                            </div>
                                            @if($act->ip_address)
                                                <div class="text-muted small" style="font-size: 0.68rem;">IP: {{ $act->ip_address }}</div>
                                            @endif
                                        </td>
                                        <td class="small text-muted" style="width: 160px;">
                                            <div class="fw-semibold text-dark">{{ $act->created_at->translatedFormat('d M Y, H:i') }} WIB</div>
                                            <div style="font-size: 0.7rem;">{{ $act->created_at->diffForHumans() }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted small">
                        <i class="ti ti-activity fs-1 d-block mb-1 text-muted opacity-50"></i>
                        Belum ada riwayat aktivitas tercatat pada akun ini.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Telegram Bot & Password Change -->
    <div class="col-lg-5">
        <!-- Kartu Integrasi Bot Telegram SIAP -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-info-lt text-info rounded-circle flex-shrink-0" style="width: 34px; height: 34px;">
                        <i class="ti ti-brand-telegram fs-2"></i>
                    </span>
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0" style="font-size: 0.95rem;">Bot Telegram SIAP</h4>
                        <div class="text-muted" style="font-size: 0.72rem;">SMK PGRI KRAS &bull; &#64;{{ $telegramBotUsername ?? 'smkpgrikras_bot' }}</div>
                    </div>
                </div>
                @if($user->telegram_chat_id)
                    <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Terhubung</span>
                @else
                    <span class="badge bg-warning-lt">Belum Terhubung</span>
                @endif
            </div>
            <div class="card-body p-3 text-center">
                @php
                    $tgBotUrl = $telegramBotUrl ?? ('https://t.me/' . ($telegramBotUsername ?? 'smkpgrikras_bot'));
                    $tgQr = $telegramQrCode ?? null;
                    if (!$tgQr) {
                        try {
                            $tgQr = (new \chillerlan\QRCode\QRCode)->render($tgBotUrl);
                        } catch (\Throwable $e) {}
                    }
                @endphp

                <!-- QR Code Box -->
                @if(!empty($tgQr))
                    <div class="p-2.5 bg-white border rounded-3 d-inline-block shadow-xs mb-2">
                        <img src="{{ $tgQr }}" alt="QR Code Bot Telegram SIAP" style="width: 140px; height: 140px; display: block;" class="mx-auto">
                    </div>
                    <div class="text-muted small mb-3" style="font-size: 0.76rem; line-height: 1.35;">
                        <i class="ti ti-scan text-primary me-1"></i> Pindai QR Code menggunakan kamera HP untuk langsung membuka bot di Telegram
                    </div>
                @endif

                <a href="{{ $tgBotUrl }}" target="_blank" class="btn btn-info text-white w-100 fw-bold py-2 mb-3 shadow-xs d-flex align-items-center justify-content-center gap-2">
                    <i class="ti ti-brand-telegram fs-2"></i>
                    <span>Hubungkan ke Bot Telegram</span>
                </a>

                <div class="bg-light p-2.5 rounded-3 text-start border">
                    <div class="fw-bold text-dark mb-1 d-flex align-items-center gap-1 small">
                        <i class="ti ti-info-circle text-primary"></i> Langkah Menautkan Akun:
                    </div>
                    <ol class="ps-3 mb-0 text-muted" style="font-size: 0.75rem; line-height: 1.5;">
                        <li>Buka bot melalui tombol di atas atau scan QR Code.</li>
                        <li>Tekan tombol <b>START</b> pada bot.</li>
                        <li>Ketik perintah: <code class="user-select-all">/hubungkan {{ $user->nip ?: ($user->username ?: $user->email) }}</code></li>
                        <li>Atau ketik <code class="user-select-all">/id</code> lalu salin nomor Chat ID ke form sebelah kiri.</li>
                    </ol>
                </div>
            </div>
        </div>

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
