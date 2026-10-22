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
                        <i class="ti ti-messages me-1 text-info"></i> Saluran / Grup Telegram Terpusat Sekolah
                    </h5>
                    <div class="text-secondary small mb-3">
                        Masukkan ID Grup Telegram (biasanya diawali dengan tanda minus <code>-100xxxxxxxxxx</code>). Pastikan Bot sudah dimasukkan ke dalam grup sebagai Administrator.
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- 1. Grup Piket & Disiplin -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-shield-check text-warning me-1"></i> Grup Kedisiplinan & BK (Piket)
                            </label>
                            <input type="text" name="telegram_discipline_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_discipline_chat_id', $setting->telegram_discipline_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Notif siswa terlambat, izin keluar/pulang, dan kasus pelanggaran.</small>
                        </div>

                        <!-- 2. Grup Petugas Piket -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-calendar-user text-primary me-1"></i> Grup Tim Piket Harian
                            </label>
                            <input type="text" name="telegram_piket_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_piket_chat_id', $setting->telegram_piket_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Pengingat jadwal piket dan instruksi tugas harian.</small>
                        </div>

                        <!-- 3. Grup Supervisi -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-clipboard-check text-indigo me-1"></i> Grup Supervisi Guru & Akademik
                            </label>
                            <input type="text" name="telegram_supervision_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_supervision_chat_id', $setting->telegram_supervision_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Jadwal observasi, pengajuan pengesahan, dan hasil supervisi.</small>
                        </div>

                        <!-- 4. Grup Kegiatan Sekolah & Kurikulum -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-calendar-star text-success me-1"></i> Grup Kegiatan Sekolah & Kaldik
                            </label>
                            <input type="text" name="telegram_activities_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_activities_chat_id', $setting->telegram_activities_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Pengingat agenda kurikulum, kalender pendidikan, dan ujian.</small>
                        </div>

                        <!-- 5. Grup Dewan Guru -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">
                                <i class="ti ti-users-group text-azure me-1"></i> Grup Umum Dewan Guru & Karyawan
                            </label>
                            <input type="text" name="telegram_teachers_chat_id" class="form-control font-monospace" 
                                value="{{ old('telegram_teachers_chat_id', $setting->telegram_teachers_chat_id) }}" 
                                placeholder="-100xxxxxxxxxx">
                            <small class="text-muted">Pengumuman umum resmi dinas dan pesan siaran massal sekolah.</small>
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

                <div class="hr-text text-muted my-3">Perintah Interaktif Petugas Piket</div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless text-secondary mb-0" style="font-size: 0.8rem;">
                        <tbody>
                            <tr>
                                <td><code>/piket</code></td>
                                <td>Cek rekan & jadwal tim piket hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/jadwal</code></td>
                                <td>Jadwal KBM & guru mengajar jam aktif</td>
                            </tr>
                            <tr>
                                <td><code>/inval</code></td>
                                <td>Cek guru tidak hadir & kelas kosong</td>
                            </tr>
                            <tr>
                                <td><code>/lapor</code></td>
                                <td>Lapor kejadian/kasus siswa + foto bukti</td>
                            </tr>
                            <tr>
                                <td><code>/rekap</code></td>
                                <td>Rekap izin, kasus, & tamu hari ini</td>
                            </tr>
                            <tr>
                                <td><code>/id</code></td>
                                <td>Cek Chat ID Telegram pribadi</td>
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
                            @if($setting->telegram_teachers_chat_id)
                                <option value="teachers">📢 Grup Dewan Guru & Karyawan</option>
                            @endif
                            @if($setting->telegram_discipline_chat_id)
                                <option value="discipline">🛡️ Grup Kedisiplinan & BK</option>
                            @endif
                            @if($setting->telegram_piket_chat_id)
                                <option value="piket">👮 Grup Tim Piket Harian</option>
                            @endif
                            @if($setting->telegram_supervision_chat_id)
                                <option value="supervision">📋 Grup Supervisi & Akademik</option>
                            @endif
                            @if($setting->telegram_activities_chat_id)
                                <option value="activities">🗓️ Grup Kegiatan Sekolah & Kurikulum</option>
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
});
</script>
@endpush
