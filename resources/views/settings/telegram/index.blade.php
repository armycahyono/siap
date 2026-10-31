@extends('layouts.app')

@section('title', 'Pengaturan Bot Telegram & Notifikasi Sekolah')

@section('content')
<!-- Header Halaman -->
<div class="page-header d-print-none mb-4">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle text-uppercase tracking-wider text-muted">
                Pengaturan Sistem
            </div>
            <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                <i class="ti ti-brand-telegram text-info me-2 fs-1"></i>
                Pengaturan Bot Telegram & Notifikasi Sekolah
            </h2>
            <div class="text-secondary mt-1">
                Pusat integrasi bot Telegram sekolah untuk notifikasi otomatis tugas piket, supervisi pembelajaran, kegiatan kurikulum, dan siaran pengumuman.
            </div>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-broadcast-cepat">
                <i class="ti ti-broadcast me-1"></i> Siaran Pengumuman Cepat
            </button>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-test-telegram">
                <i class="ti ti-send me-1"></i> Uji Coba Bot
            </button>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-{{ $setting->telegram_notifications_enabled ? 'success' : 'secondary' }} border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">STATUS BOT TELEGRAM</div>
                <div class="h2 fw-bold text-dark mb-0">
                    @if($setting->telegram_notifications_enabled && $setting->telegram_bot_token)
                        <span class="text-success"><i class="ti ti-circle-check me-1"></i> Aktif</span>
                    @else
                        <span class="text-secondary"><i class="ti ti-circle-x me-1"></i> Non-Aktif</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-info border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">GURU TERHUBUNG TELEGRAM</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $totalTeachersWithId }} <span class="fs-4 text-muted font-weight-normal">/ {{ $teachers->count() }} guru</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-primary border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">NOTIFIKASI BERHASIL</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $totalSent }} <span class="fs-4 text-muted font-weight-normal">pesan</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-danger border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">GAGAL / ERROR</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $totalFailed }} <span class="fs-4 text-muted font-weight-normal">pesan</span></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Kolom Kiri: Form Konfigurasi Bot & Saluran Grup -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-settings text-primary me-2"></i> Konfigurasi Bot & Saluran Notifikasi Sekolah
                </h4>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('telegram.settings') }}" method="POST">
                    @csrf
                    <!-- Token & Username -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label required fw-bold text-dark">
                                <i class="ti ti-key me-1 text-warning"></i> Token Bot Telegram (@BotFather)
                            </label>
                            <input type="text" name="telegram_bot_token" class="form-control font-monospace" 
                                value="{{ old('telegram_bot_token', $setting->telegram_bot_token) }}" 
                                placeholder="Contoh: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                            <small class="text-muted">Dapatkan token bot dengan membuat bot di Telegram melalui akun resmi <b>@BotFather</b>.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username Bot</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" name="telegram_bot_username" class="form-control" 
                                    value="{{ old('telegram_bot_username', $setting->telegram_bot_username) }}" 
                                    placeholder="NamaBotSekolah_bot">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="telegram_notifications_enabled" value="1" id="notif_enabled"
                                    {{ $setting->telegram_notifications_enabled ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="notif_enabled">
                                    Aktifkan Layanan Notifikasi Bot
                                </label>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="ti ti-messages me-1 text-info"></i> Ringkasan ID Saluran Grup Sekolah
                    </h5>
                    <div class="text-secondary small mb-3">
                        ID Grup utama sekolah yang terhubung dengan bot. Untuk mengatur daftar kelompok peran, perintah grup, dan mengirim tautan undangan personal, kelola pada tabel <b>Saluran Grup Telegram</b> di bawah.
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- 1. Grup Piket & Disiplin -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-shield-check text-warning me-1"></i> Grup Kedisiplinan & BK
                            </label>
                            <input type="text" name="telegram_discipline_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_discipline_chat_id', $setting->telegram_discipline_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Notif keterlambatan, izin, & kasus pelanggaran.</small>
                        </div>

                        <!-- 2. Grup Petugas Piket -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-calendar-user text-primary me-1"></i> Grup Piket Harian
                            </label>
                            <input type="text" name="telegram_piket_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_piket_chat_id', $setting->telegram_piket_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Pengumuman tim piket hari H & kelas kosong/inval.</small>
                        </div>

                        <!-- 3. Grup Kegiatan Sekolah & Kurikulum -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-calendar-star text-success me-1"></i> Grup Kegiatan Sekolah & Kaldik
                            </label>
                            <input type="text" name="telegram_activities_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_activities_chat_id', $setting->telegram_activities_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Agenda kegiatan, ujian, & pengingat kaldik.</small>
                        </div>

                        <!-- 4. Grup Dewan Guru -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-users-group text-azure me-1"></i> Grup GTK SMK PGRI Kras
                            </label>
                            <input type="text" name="telegram_teachers_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_teachers_chat_id', $setting->telegram_teachers_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Pengumuman kedinasan resmi dewan guru & staf.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary shadow-sm px-4">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Pengaturan Bot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Uji Coba Cepat & Pengingat Manual -->
    <div class="col-lg-5">
        <!-- Card Panduan & Uji Coba -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-send text-info me-2"></i> Uji Coba Pengiriman Pesan
                </h4>
            </div>
            <div class="card-body p-3">
                <form action="{{ route('telegram.test') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small text-muted">Kirim Pesan Uji Coba ke Chat ID:</label>
                        <div class="input-group">
                            <input type="text" name="test_chat_id" class="form-control font-monospace" 
                                placeholder="ID akun pribadi atau grup" required>
                            <button type="submit" class="btn btn-info text-white">
                                <i class="ti ti-send me-1"></i> Kirim Tes
                            </button>
                        </div>
                        <small class="text-muted">Ketikkan Chat ID pribadi Anda untuk mengetes bot aktif dan bisa mengirim.</small>
                    </div>
                </form>

                <div class="hr-text text-muted my-3">Pengingat Manual Otomatis</div>

                <div class="d-flex flex-column gap-2">
                    <!-- Pengingat Piket -->
                    <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark small"><i class="ti ti-shield-check text-warning me-1"></i> Pengingat Guru Piket</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Kirim ke guru yang bertugas besok</div>
                        </div>
                        <form action="{{ route('telegram.reminder') }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="tomorrow">
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="ti ti-bell me-1"></i> Kirim (H-1)
                            </button>
                        </form>
                    </div>

                    <!-- Pengingat Supervisi -->
                    <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark small"><i class="ti ti-clipboard-check text-indigo me-1"></i> Pengingat Supervisi Guru</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Kirim jadwal observasi besok ke guru & supervisor</div>
                        </div>
                        <form action="{{ route('telegram.supervision-reminder') }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="tomorrow">
                            <button type="submit" class="btn btn-sm btn-outline-indigo">
                                <i class="ti ti-bell me-1"></i> Kirim (H-1)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Konfigurasi Webhook & Chat Bot Interaktif -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-webhook text-purple me-2"></i> Webhook & Bot Interaktif Piket
                    </h4>
                    @if(!empty($webhookInfo['url']))
                        <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Webhook Aktif</span>
                    @else
                        <span class="badge bg-secondary-lt">Webhook Belum Disetel</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-3">
                <div class="small text-secondary mb-3">
                    Webhook memungkinkan petugas piket berinteraksi 2 arah dengan Bot Telegram (cek piket hari ini, cek jadwal jam sekarang, cek kelas kosong/inval, dan <strong>melaporkan kejadian/kasus siswa langsung dari Telegram beserta foto bukti</strong>).
                </div>

                @if(!empty($webhookInfo['url']))
                    <div class="alert alert-success border-0 py-2 px-3 small mb-3">
                        <div class="fw-bold"><i class="ti ti-link me-1"></i> URL Webhook Terdaftar:</div>
                        <code class="text-break">{{ $webhookInfo['url'] }}</code>
                        @if(isset($webhookInfo['pending_update_count']))
                            <div class="text-muted mt-1" style="font-size: 0.75rem;">Pending Update: {{ $webhookInfo['pending_update_count'] }} pesan</div>
                        @endif
                    </div>
                @endif

                <form action="{{ route('telegram.set-webhook') }}" method="POST" class="mb-2">
                    @csrf
                    <label class="form-label small text-muted">URL Webhook Aplikasi (Harus HTTPS):</label>
                    <div class="input-group mb-2">
                        <input type="url" name="webhook_url" class="form-control font-monospace form-control-sm" 
                            value="{{ !empty($webhookInfo['url']) ? $webhookInfo['url'] : url('/telegram/webhook') }}" 
                            placeholder="https://domain-anda.com/telegram/webhook" required>
                        <button type="submit" class="btn btn-sm btn-purple text-white">
                            <i class="ti ti-check me-1"></i> Setel Webhook
                        </button>
                    </div>
                    <small class="text-muted" style="font-size: 0.75rem;">
                        Untuk localhost/development, gunakan tool tunneling (seperti Ngrok) dan masukkan URL HTTPS publiknya.
                    </small>
                </form>

                @if(!empty($webhookInfo['url']))
                    <form action="{{ route('telegram.delete-webhook') }}" method="POST" onsubmit="return confirm('Hapus konfigurasi webhook?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100 mt-2">
                            <i class="ti ti-trash me-1"></i> Hapus Webhook
                        </button>
                    </form>
                @endif

                <div class="hr-text text-muted my-3">Daftar Perintah Interaktif Bot SIAP</div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless text-secondary mb-0" style="font-size: 0.8rem;">
                        <tbody>
                            <tr>
                                <td><code>/presensi</code></td>
                                <td>Cek log presensi tap/kehadiran saya hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/jadwalsaya</code></td>
                                <td>Jadwal mengajar KBM pribadi hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/cek_absen_siswa</code></td>
                                <td>Rekap presensi KBM & kejadian siswa (Wali Kelas, BK, Piket)</td>
                            </tr>
                            <tr>
                                <td><code>/piket</code></td>
                                <td>Cek rekan & jadwal tim piket hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/jadwal</code></td>
                                <td>Jadwal KBM & guru mengajar jam aktif sekolah</td>
                            </tr>
                            <tr>
                                <td><code>/inval</code></td>
                                <td>Cek guru izin & kelas kosong / penugasan inval</td>
                            </tr>
                            <tr>
                                <td><code>/lapor</code></td>
                                <td>Lapor kasus/pelanggaran siswa + foto bukti</td>
                            </tr>
                            <tr>
                                <td><code>/ajukan_izin</code></td>
                                <td>Ajukan izin mandiri GTK & titip tugas KBM</td>
                            </tr>
                            <tr>
                                <td><code>/tukar</code></td>
                                <td>Tukar jadwal piket dengan rekan guru</td>
                            </tr>
                            <tr>
                                <td><code>/rekap</code></td>
                                <td>Rekap cepat perizinan, tamu & kasus hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/tamu</code></td>
                                <td>Daftar buku tamu kunjungan sekolah hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/agenda</code></td>
                                <td>Agenda kegiatan & kalender pendidikan sekolah</td>
                            </tr>
                            <tr>
                                <td><code>/poin [nis]</code></td>
                                <td>Cek catatan kasus & rekam jejak siswa</td>
                            </tr>
                            <tr>
                                <td><code>/hubungkan [nip]</code></td>
                                <td>Tautkan akun SIAP ke akun Telegram</td>
                            </tr>
                            <tr>
                                <td><code>/id</code></td>
                                <td>Cek nomor Chat ID Telegram unik pribadi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card Panduan Chat ID -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-help text-secondary me-2"></i> Panduan Mendapatkan Chat ID
                </h4>
            </div>
            <div class="card-body p-3 small text-secondary">
                <ol class="ps-3 mb-0">
                    <li class="mb-2">
                        <b>Untuk Guru Pribadi:</b> Buka Telegram dan cari bot <code>@userinfobot</code> atau <code>@RawDataBot</code>, klik <b>Start</b>. Nomor <b>Id</b> yang muncul adalah Chat ID guru.
                    </li>
                    <li class="mb-2">
                        <b>Untuk Grup Sekolah:</b> Masukkan Bot Anda ke dalam grup sebagai Admin. Tambahkan juga bot <code>@userinfobot</code> ke grup tersebut atau forward pesan grup ke bot untuk melihat ID Grup (biasanya bertanda minus, contoh: <code>-1002345678901</code>).
                    </li>
                    <li>
                        <b>Pastikan Bot Sudah di-Start:</b> Telegram mewajibkan pengguna perorangan untuk mengklik <b>Start</b> pada bot sebelum bot dapat mengirimkan pesan pribadi kepada mereka.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- PENGELOLAAN SALURAN GRUP TELEGRAM SEKOLAH (DINAMIS & MULTI-GRUP) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                <i class="ti ti-messages text-primary me-2 fs-2"></i>
                Saluran Grup Telegram Sekolah
            </h4>
            <div class="text-muted small mt-1">
                Kelola grup resmi sekolah, sesuaikan menu perintah interaktif khusus per grup, dan siarkan tautan undangan resmi langsung ke japri guru yang berwenang.
            </div>
        </div>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-tambah-saluran">
                <i class="ti ti-plus me-1"></i> Tambah Saluran Grup
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 200px;">Saluran Grup</th>
                    <th style="min-width: 170px;">ID Chat & Tautan</th>
                    <th style="min-width: 220px;">Kelompok / Peran GTK</th>
                    <th style="min-width: 200px;">Perintah Menu Aktif</th>
                    <th class="text-center" style="width: 190px;">Aksi Cepat</th>
                </tr>
            </thead>
            <tbody>
                @forelse($channels as $channel)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                @php
                                    $icon = match($channel->code) {
                                        'discipline' => 'ti-shield-check text-warning',
                                        'piket' => 'ti-calendar-user text-primary',
                                        'activities' => 'ti-calendar-star text-success',
                                        'gtk' => 'ti-users-group text-azure',
                                        default => 'ti-message-circle text-info',
                                    };
                                @endphp
                                <i class="ti {{ $icon }} fs-2 me-2"></i>
                                <div>
                                    <div class="fw-bold text-dark">{{ $channel->name }}</div>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 230px;" title="{{ $channel->description }}">
                                        {{ $channel->description ?? 'Tidak ada keterangan.' }}
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($channel->chat_id)
                                <div class="font-monospace fw-bold text-dark small"><code>{{ $channel->chat_id }}</code></div>
                                <span class="badge bg-success-lt small"><i class="ti ti-check me-1"></i> Terhubung</span>
                            @else
                                <span class="badge bg-secondary-lt small"><i class="ti ti-alert-circle me-1"></i> Belum Ada ID</span>
                            @endif

                            @if($channel->invite_link)
                                <div class="mt-1">
                                    <a href="{{ $channel->invite_link }}" target="_blank" class="small text-primary text-decoration-none" title="Buka tautan undangan grup">
                                        <i class="ti ti-link me-1"></i> Buka Tautan
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($channel->allowed_roles) && count($channel->allowed_roles) > 0)
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($channel->allowed_roles as $roleSlug)
                                        <span class="badge bg-blue-lt small text-uppercase" style="font-size: 0.7rem;">
                                            {{ str_replace('_', ' ', $roleSlug) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="badge bg-azure-lt small">Semua GTK / Terbuka</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($channel->enabled_commands) && count($channel->enabled_commands) > 0)
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($channel->enabled_commands as $cmd)
                                        <span class="badge bg-purple-lt font-monospace small" style="font-size: 0.72rem;">
                                            {{ $cmd }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted small">Hanya siaran / notifikasi</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                <!-- Tombol Edit -->
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-channel" 
                                    data-id="{{ $channel->id }}"
                                    data-name="{{ $channel->name }}"
                                    data-chat-id="{{ $channel->chat_id }}"
                                    data-description="{{ $channel->description }}"
                                    data-invite-link="{{ $channel->invite_link }}"
                                    data-roles='@json($channel->allowed_roles ?? [])'
                                    data-commands='@json($channel->enabled_commands ?? [])'
                                    data-active="{{ $channel->is_active ? 1 : 0 }}"
                                    title="Edit Pengaturan Grup">
                                    <i class="ti ti-edit"></i>
                                </button>

                                <!-- Tombol Kirim Undangan ke Japri GTK -->
                                <form action="{{ route('telegram.channel.invite', $channel->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Kirimkan pesan undangan bergabung ke grup ini via Japri Telegram ke seluruh GTK yang memenuhi peran?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Siarkan Tautan Undangan ke Japri GTK" {{ empty($channel->chat_id) ? 'disabled' : '' }}>
                                        <i class="ti ti-send"></i>
                                    </button>
                                </form>

                                <!-- Tombol Sync Menu Perintah ke Telegram -->
                                <form action="{{ route('telegram.channel.sync-commands', $channel->id) }}" method="POST" class="d-inline" title="Sinkronkan Menu Perintah Khusus ke Telegram">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info" {{ empty($channel->chat_id) ? 'disabled' : '' }}>
                                        <i class="ti ti-refresh"></i>
                                    </button>
                                </form>

                                @if(!in_array($channel->code, ['discipline', 'piket', 'activities', 'gtk']))
                                    <!-- Tombol Hapus (Hanya untuk grup kustom) -->
                                    <form action="{{ route('telegram.channel.destroy', $channel->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus saluran grup ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Saluran">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            Belum ada saluran grup telegram yang dikonfigurasi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TABEL PEMETAAN CHAT ID GURU -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-users-group text-primary me-2"></i> Pemetaan Chat ID Telegram Dewan Guru & Tenaga Pendidik
            </h4>
            <div class="text-muted small mt-1">Daftarkan Chat ID guru agar notifikasi jadwal piket, perizinan siswa bimbingan, dan jadwal supervisi masuk ke Telegram pribadi guru.</div>
        </div>
        <div class="card-actions">
            <span class="badge bg-primary-lt fs-4">{{ $totalTeachersWithId }} / {{ $teachers->count() }} Terhubung</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Guru</th>
                    <th>Peran / Jabatan</th>
                    <th>Chat ID Telegram</th>
                    <th>Username Telegram</th>
                    <th>Status Notifikasi</th>
                    <th class="text-end" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($teachers as $idx => $t)
                    <tr>
                        <td class="text-muted small">{{ $idx + 1 }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $t->name }}</div>
                            <div class="text-muted small font-monospace">NIP: {{ $t->nip ?: '-' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $t->role?->name ?? 'Guru' }}</span>
                        </td>
                        <td>
                            @if($t->telegram_chat_id)
                                <span class="font-monospace fw-bold text-primary">{{ $t->telegram_chat_id }}</span>
                            @else
                                <span class="text-muted small">Belum terhubung</span>
                            @endif
                        </td>
                        <td>
                            @if($t->telegram_username)
                                <span class="text-dark">{{ '@' . ltrim($t->telegram_username, '@') }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            @if($t->telegram_chat_id)
                                <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Siap Menerima</span>
                            @else
                                <span class="badge bg-secondary-lt">Menunggu ID</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-teacher-chat"
                                data-id="{{ $t->id }}"
                                data-name="{{ $t->name }}"
                                data-chat-id="{{ $t->telegram_chat_id }}"
                                data-username="{{ $t->telegram_username }}"
                                title="Edit Chat ID">
                                <i class="ti ti-edit me-1"></i> Atur ID
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- LOG RIWAYAT NOTIFIKASI TELEGRAM -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold text-dark m-0">
            <i class="ti ti-history text-secondary me-2"></i> Riwayat Pengiriman Notifikasi Bot Telegram
        </h4>
        <div class="card-actions d-flex gap-2 align-items-center">
            <form action="{{ route('telegram.index') }}" method="GET" class="d-flex gap-1">
                <select name="target_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <option value="piket_reminder" {{ $targetType === 'piket_reminder' ? 'selected' : '' }}>Pengingat Piket</option>
                    <option value="subject_teacher" {{ $targetType === 'subject_teacher' ? 'selected' : '' }}>Guru Mapel</option>
                    <option value="homeroom_teacher" {{ $targetType === 'homeroom_teacher' ? 'selected' : '' }}>Wali Kelas</option>
                    <option value="discipline_group" {{ $targetType === 'discipline_group' ? 'selected' : '' }}>Grup Disiplin</option>
                    <option value="supervision_reminder" {{ $targetType === 'supervision_reminder' ? 'selected' : '' }}>Supervisi</option>
                    <option value="school_activity" {{ $targetType === 'school_activity' ? 'selected' : '' }}>Kegiatan Sekolah</option>
                    <option value="broadcast" {{ $targetType === 'broadcast' ? 'selected' : '' }}>Broadcast</option>
                    <option value="test" {{ $targetType === 'test' ? 'selected' : '' }}>Uji Coba</option>
                </select>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 170px;">Waktu Kirim</th>
                    <th>Kategori / Tujuan</th>
                    <th>Penerima / Chat ID</th>
                    <th>Cuplikan Isi Pesan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-muted small">
                            {{ $log->created_at?->format('d/m/Y H:i:s') }}
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">{{ strtoupper(str_replace('_', ' ', $log->target_type)) }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark small">{{ $log->recipient_name }}</div>
                            <span class="font-monospace small text-muted">{{ $log->chat_id }}</span>
                        </td>
                        <td>
                            <span class="small text-secondary text-truncate d-inline-block" style="max-width: 450px;">
                                {{ strip_tags($log->message) }}
                            </span>
                        </td>
                        <td>
                            @if($log->status === 'sent')
                                <span class="badge bg-success-lt fw-bold"><i class="ti ti-check me-1"></i> Terkirim</span>
                            @else
                                <span class="badge bg-danger-lt fw-bold" title="{{ $log->response_payload }}"><i class="ti ti-x me-1"></i> Gagal</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            Belum ada riwayat notifikasi telegram yang tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white d-flex align-items-center justify-content-between py-2">
            <div class="text-muted small">
                Menampilkan {{ $logs->firstItem() }} s/d {{ $logs->lastItem() }} dari {{ $logs->total() }} log
            </div>
            <div>
                {{ $logs->links() }}
            </div>
        </div>
    @endif
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT CHAT ID GURU -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-edit-teacher-chat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="{{ route('telegram.teacher-chat-id') }}" method="POST">
                @csrf
                <input type="hidden" name="teacher_id" id="modal_teacher_id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-brand-telegram me-2 fs-2"></i>
                        Atur Chat ID Telegram Guru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Nama Guru:</label>
                        <div class="h4 fw-bold text-dark" id="modal_teacher_name">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Chat ID Telegram</label>
                        <input type="text" name="telegram_chat_id" id="modal_telegram_chat_id" class="form-control font-monospace" 
                            placeholder="Contoh: 123456789" required>
                        <small class="text-muted">ID angka yang didapat guru saat mengirim pesan ke @userinfobot.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username Telegram (Opsional)</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="telegram_username" id="modal_telegram_username" class="form-control" 
                                placeholder="username_guru">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-check me-1"></i> Simpan Chat ID
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: TAMBAH SALURAN GRUP -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-tambah-saluran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="{{ route('telegram.channel.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-messages me-2 fs-2"></i>
                        Tambah Saluran Grup Telegram Sekolah
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required">Nama Saluran Grup</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Grup Tim Panitia Ujian" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ID Chat Telegram (Awali -100)</label>
                            <input type="text" name="chat_id" class="form-control font-monospace" placeholder="-100xxxxxxxxxx">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Deskripsi / Fungsi Grup</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Jelaskan fungsi koordinasi dan informasi apa yang masuk ke grup ini..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tautan Undangan Grup (Invite Link Telegram)</label>
                            <input type="url" name="invite_link" class="form-control font-monospace" placeholder="https://t.me/+xxxxxx (Kosongkan jika ingin dibuat otomatis oleh bot)">
                        </div>

                        <!-- Pilihan Peran GTK yang Diizinkan -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark border-bottom pb-1">
                                <i class="ti ti-user-check me-1 text-primary"></i> Kelompok / Peran GTK yang Diizinkan Bergabung
                            </label>
                            <div class="row g-2 mt-1">
                                @foreach($availableRoles as $role)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="{{ $role->slug }}">
                                            <span class="form-check-label small">{{ $role->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Guru yang memiliki peran di atas akan diizinkan dan dikirimi link undangan personal via Japri Bot.</small>
                        </div>

                        <!-- Pilihan Perintah yang Aktif di Grup -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark border-bottom pb-1">
                                <i class="ti ti-command me-1 text-info"></i> Perintah Menu Interaktif yang Diaktifkan di Grup
                            </label>
                            <div class="row g-2 mt-1">
                                @php
                                    $defaultCommands = [
                                        '/piket' => 'Cek tim piket hari ini',
                                        '/jadwal' => 'Jadwal KBM jam sekarang',
                                        '/inval' => 'Cek kelas kosong & inval',
                                        '/lapor' => 'Lapor kasus siswa',
                                        '/rekap' => 'Rekap harian sekolah',
                                        '/tamu' => 'Daftar buku tamu',
                                        '/agenda' => 'Agenda kegiatan & kaldik',
                                        '/poin' => 'Cek kasus & poin siswa',
                                        '/bantuan' => 'Panduan bantuan bot',
                                        '/id' => 'Cek Chat ID unik',
                                    ];
                                @endphp
                                @foreach($defaultCommands as $cmdCode => $cmdDesc)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enabled_commands[]" value="{{ $cmdCode }}">
                                            <span class="form-check-label font-monospace small"><b>{{ $cmdCode }}</b> <span class="text-muted font-sans-serif" style="font-size: 0.75rem;">({{ $cmdDesc }})</span></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Hanya perintah yang dicentang yang akan direspons bot saat diketik di dalam grup.</small>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="channel_add_active" checked>
                                <label class="form-check-label fw-bold" for="channel_add_active">Aktifkan Saluran Grup Ini</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-check me-1"></i> Simpan Saluran Grup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT SALURAN GRUP -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-edit-saluran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="form-edit-channel" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-edit me-2 fs-2"></i>
                        Edit Saluran Grup: <span id="modal_edit_channel_title" class="ms-1">-</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required">Nama Saluran Grup</label>
                            <input type="text" name="name" id="edit_channel_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ID Chat Telegram (Awali -100)</label>
                            <input type="text" name="chat_id" id="edit_channel_chat_id" class="form-control font-monospace" placeholder="-100xxxxxxxxxx">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Deskripsi / Fungsi Grup</label>
                            <textarea name="description" id="edit_channel_description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tautan Undangan Grup (Invite Link Telegram)</label>
                            <input type="url" name="invite_link" id="edit_channel_invite_link" class="form-control font-monospace" placeholder="https://t.me/+xxxxxx">
                        </div>

                        <!-- Pilihan Peran GTK yang Diizinkan -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark border-bottom pb-1">
                                <i class="ti ti-user-check me-1 text-primary"></i> Kelompok / Peran GTK yang Diizinkan Bergabung
                            </label>
                            <div class="row g-2 mt-1" id="edit-roles-container">
                                @foreach($availableRoles as $role)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check">
                                            <input class="form-check-input edit-role-checkbox" type="checkbox" name="allowed_roles[]" value="{{ $role->slug }}" id="edit_role_{{ $role->slug }}">
                                            <span class="form-check-label small">{{ $role->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Pilihan Perintah yang Aktif di Grup -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark border-bottom pb-1">
                                <i class="ti ti-command me-1 text-info"></i> Perintah Menu Interaktif yang Diaktifkan di Grup
                            </label>
                            <div class="row g-2 mt-1" id="edit-commands-container">
                                @foreach($defaultCommands as $cmdCode => $cmdDesc)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check">
                                            <input class="form-check-input edit-command-checkbox" type="checkbox" name="enabled_commands[]" value="{{ $cmdCode }}" id="edit_cmd_{{ ltrim($cmdCode, '/') }}">
                                            <span class="form-check-label font-monospace small"><b>{{ $cmdCode }}</b> <span class="text-muted font-sans-serif" style="font-size: 0.75rem;">({{ $cmdDesc }})</span></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_channel_is_active">
                                <label class="form-check-label fw-bold" for="edit_channel_is_active">Aktifkan Saluran Grup Ini</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan Grup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: BROADCAST SIARAN CEPAT -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-broadcast-cepat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="{{ route('telegram.broadcast') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-broadcast me-2 fs-2"></i>
                        Kirim Siaran Pengumuman Cepat
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label required">Pilih Saluran / Grup Tujuan</label>
                        <select name="target_channel" class="form-select" required>
                            <option value="">-- Pilih Grup Sekolah --</option>
                            @foreach($channels as $ch)
                                @if($ch->is_active && $ch->chat_id)
                                    <option value="{{ $ch->code }}">{{ $ch->name }} ({{ $ch->chat_id }})</option>
                                @endif
                            @endforeach
                            @if($setting->telegram_teachers_chat_id && !$channels->where('code', 'gtk')->first()?->chat_id)
                                <option value="teachers">📢 Grup Dewan Guru & Karyawan</option>
                            @endif
                            @if($setting->telegram_discipline_chat_id && !$channels->where('code', 'discipline')->first()?->chat_id)
                                <option value="discipline">🛡️ Grup Kedisiplinan & BK</option>
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Judul Pengumuman</label>
                        <input type="text" name="broadcast_title" class="form-control" required placeholder="Contoh: Pengingat Upacara Hari Senin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Isi Pesan Pengumuman</label>
                        <textarea name="broadcast_message" class="form-control" rows="4" required placeholder="Tuliskan isi pengumuman atau instruksi resmi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-send me-1"></i> Kirim Siaran Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: UJI COBA BOT -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-test-telegram" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="{{ route('telegram.test') }}" method="POST">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-send me-2 fs-2"></i>
                        Uji Coba Pengiriman Pesan Bot
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label required">Chat ID Target Penerima</label>
                        <input type="text" name="test_chat_id" class="form-control font-monospace" required placeholder="Contoh: 123456789 atau -100xxxxxxxxxx">
                        <small class="text-muted">Masukkan Chat ID pengguna atau grup yang ingin dites.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white shadow-sm">
                        <i class="ti ti-send me-1"></i> Kirim Pesan Uji Coba
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Teacher Chat ID Modal
    document.querySelectorAll('.btn-edit-teacher-chat').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('modal_teacher_id').value = this.dataset.id;
            document.getElementById('modal_teacher_name').textContent = this.dataset.name;
            document.getElementById('modal_telegram_chat_id').value = this.dataset.chatId || '';
            document.getElementById('modal_telegram_username').value = this.dataset.username || '';

            const modal = new bootstrap.Modal(document.getElementById('modal-edit-teacher-chat'));
            modal.show();
        });
    });

    // Edit Telegram Channel Modal
    document.querySelectorAll('.btn-edit-channel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name || '';
            const chatId = this.dataset.chatId || '';
            const description = this.dataset.description || '';
            const inviteLink = this.dataset.inviteLink || '';
            const active = this.dataset.active === '1';

            let roles = [];
            try {
                roles = JSON.parse(this.dataset.roles || '[]');
            } catch (e) {
                roles = [];
            }

            let commands = [];
            try {
                commands = JSON.parse(this.dataset.commands || '[]');
            } catch (e) {
                commands = [];
            }

            // Set Form action & values
            const form = document.getElementById('form-edit-channel');
            form.action = '{{ url("pengaturan/telegram/saluran-grup") }}/' + id;

            document.getElementById('modal_edit_channel_title').textContent = name;
            document.getElementById('edit_channel_name').value = name;
            document.getElementById('edit_channel_chat_id').value = chatId;
            document.getElementById('edit_channel_description').value = description;
            document.getElementById('edit_channel_invite_link').value = inviteLink;
            document.getElementById('edit_channel_is_active').checked = active;

            // Check roles
            document.querySelectorAll('.edit-role-checkbox').forEach(function (cb) {
                cb.checked = roles.includes(cb.value);
            });

            // Check commands
            document.querySelectorAll('.edit-command-checkbox').forEach(function (cb) {
                cb.checked = commands.includes(cb.value);
            });

            const modal = new bootstrap.Modal(document.getElementById('modal-edit-saluran'));
            modal.show();
        });
    });
});
</script>
@endpush
