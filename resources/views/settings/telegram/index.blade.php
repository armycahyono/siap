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

<!-- Stat Cards Ringkasan -->
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
                <div class="text-secondary small fw-bold">GURU TERHUBUNG</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $totalTeachersWithId }} <span class="fs-4 text-muted font-weight-normal">/ {{ $teachers->count() }} GTK</span></div>
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

<!-- ========================================================================= -->
<!-- NAVIGASI TAB PENGATURAN BOT TELEGRAM (4 TAB UTAMA) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-bottom-0 p-0 bg-transparent">
        <ul class="nav nav-tabs card-header-tabs px-3 pt-2" id="telegramTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="#tab-config" class="nav-link active fw-bold py-2" id="nav-config-tab" data-bs-toggle="tab" role="tab" aria-controls="tab-config" aria-selected="true">
                    <i class="ti ti-settings me-1 text-primary fs-3"></i> Konfigurasi & Grup
                    <span class="badge bg-primary-lt ms-1">{{ $channels->count() }} Saluran</span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-teachers" class="nav-link fw-bold py-2" id="nav-teachers-tab" data-bs-toggle="tab" role="tab" aria-controls="tab-teachers" aria-selected="false">
                    <i class="ti ti-users me-1 text-azure fs-3"></i> Akun Telegram GTK
                    <span class="badge bg-info-lt ms-1">{{ $totalTeachersWithId }}/{{ $teachers->count() }}</span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-testing" class="nav-link fw-bold py-2" id="nav-testing-tab" data-bs-toggle="tab" role="tab" aria-controls="tab-testing" aria-selected="false">
                    <i class="ti ti-send me-1 text-warning fs-3"></i> Uji Coba & Pengingat
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-logs" class="nav-link fw-bold py-2" id="nav-logs-tab" data-bs-toggle="tab" role="tab" aria-controls="tab-logs" aria-selected="false">
                    <i class="ti ti-history me-1 text-secondary fs-3"></i> Riwayat Pengiriman
                    <span class="badge bg-secondary-lt ms-1">{{ $logs->total() }}</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- ========================================================================= -->
<!-- KONTEN TAB -->
<!-- ========================================================================= -->
<div class="tab-content">
    <!-- ===================================================================== -->
    <!-- TAB 1: KONFIGURASI & GRUP (DIGABUNGKAN) -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade show active" id="tab-config" role="tabpanel" aria-labelledby="nav-config-tab">
        <!-- Baris Atas: Kunci Bot & Webhook -->
        <div class="row g-4 mb-4">
            <!-- Kolom Kiri: Form Kunci Bot Telegram -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-key text-primary me-2"></i> Kunci & Identitas Bot Telegram
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('telegram.settings') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">
                                    <i class="ti ti-key me-1 text-warning"></i> Token Bot Telegram (@BotFather)
                                </label>
                                <input type="text" name="telegram_bot_token" class="form-control font-monospace" 
                                    value="{{ old('telegram_bot_token', $setting->telegram_bot_token) }}" 
                                    placeholder="Contoh: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                                <small class="text-muted">Token rahasia dari bot resmi <b>@BotFather</b> di Telegram.</small>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-bold text-dark">Username Bot</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" name="telegram_bot_username" class="form-control" 
                                            value="{{ old('telegram_bot_username', $setting->telegram_bot_username) }}" 
                                            placeholder="NamaBotSekolah_bot">
                                    </div>
                                </div>
                                <div class="col-sm-6 d-flex align-items-center">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" name="telegram_notifications_enabled" value="1" id="notif_enabled"
                                            {{ $setting->telegram_notifications_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark" for="notif_enabled">
                                            Aktifkan Notifikasi Bot
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end pt-2">
                                <button type="submit" class="btn btn-primary shadow-sm px-4">
                                    <i class="ti ti-device-floppy me-1"></i> Simpan Pengaturan Bot
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Webhook & Panduan Singkat -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-webhook text-purple me-2"></i> Webhook & Bot Interaktif
                        </h4>
                        @if(!empty($webhookInfo['url']))
                            <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Webhook Aktif</span>
                        @else
                            <span class="badge bg-secondary-lt">Belum Disetel</span>
                        @endif
                    </div>
                    <div class="card-body p-4">
                        <div class="small text-secondary mb-3">
                            Webhook memungkinkan Bot menerima perintah interaktif 2 arah secara real-time dari guru maupun obrolan di saluran grup sekolah.
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

                        <form action="{{ route('telegram.set-webhook') }}" method="POST" class="mb-3">
                            @csrf
                            <label class="form-label small text-muted">URL Webhook Aplikasi (Harus HTTPS):</label>
                            <div class="input-group">
                                <input type="url" name="webhook_url" class="form-control font-monospace form-control-sm" 
                                    value="{{ !empty($webhookInfo['url']) ? $webhookInfo['url'] : url('/telegram/webhook') }}" 
                                    placeholder="https://siap.smkpgrikras.my.id/telegram/webhook" required>
                                <button type="submit" class="btn btn-sm btn-purple text-white">
                                    <i class="ti ti-check me-1"></i> Setel Webhook
                                </button>
                            </div>
                        </form>

                        @if(!empty($webhookInfo['url']))
                            <form action="{{ route('telegram.delete-webhook') }}" method="POST" onsubmit="return confirm('Hapus konfigurasi webhook?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="ti ti-trash me-1"></i> Hapus Webhook
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Baris Bawah: Pengelolaan Saluran Grup Telegram Sekolah -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                        <i class="ti ti-messages text-primary me-2 fs-2"></i>
                        Daftar Saluran Grup Telegram Sekolah
                    </h4>
                    <div class="text-muted small mt-1">
                        Kelola grup resmi sekolah, tentukan kelompok/peran GTK yang berhak bergabung, atur perintah menu yang aktif, dan kirimkan tautan undangan langsung ke japri guru.
                    </div>
                </div>
                <div class="card-actions d-flex gap-2">
                    <form action="{{ route('telegram.channel.sync-all') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-info shadow-sm" title="Sinkronkan perintah bawaan pribadi & seluruh grup ke Telegram">
                            <i class="ti ti-refresh me-1"></i> Sinkronkan Menu ke Telegram
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-tambah-saluran">
                        <i class="ti ti-plus me-1"></i> Tambah Saluran Grup
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 220px;">Saluran Grup</th>
                            <th style="min-width: 170px;">ID Chat & Tautan Undangan</th>
                            <th style="min-width: 240px;">Kelompok / Peran GTK</th>
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
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 260px;" title="{{ $channel->description }}">
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
                                                @php
                                                    $roleModel = $availableRoles->firstWhere('slug', $roleSlug);
                                                    $roleName = $roleModel ? $roleModel->name : ucwords(str_replace('_', ' ', $roleSlug));
                                                @endphp
                                                <span class="badge bg-blue-lt small" style="font-size: 0.72rem;">
                                                    {{ $roleName }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="badge bg-azure-lt small">Semua GTK / Terbuka</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted small">Perintah Aktif:</span>
                                        @if($channel->show_reply_keyboard)
                                            <span class="badge bg-teal-lt small" title="Tombol Reply Keyboard di bawah chat diaktifkan">
                                                <i class="ti ti-keyboard me-1"></i> Keyboard Aktif
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-lt small" title="Tombol Reply Keyboard di bawah chat dinonaktifkan">
                                                <i class="ti ti-keyboard-off me-1"></i> Keyboard Off
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($channel->enabled_commands) && count($channel->enabled_commands) > 0)
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($channel->enabled_commands as $cmd)
                                                @php
                                                    $isBtn = $channel->isReplyButtonEnabled($cmd);
                                                @endphp
                                                <span class="badge {{ $isBtn && $channel->show_reply_keyboard ? 'bg-purple-lt' : 'bg-gray-lt' }} font-monospace small" style="font-size: 0.72rem;" title="{{ $isBtn && $channel->show_reply_keyboard ? 'Aktif sebagai tombol keyboard' : 'Hanya perintah menu teks' }}">
                                                    {{ $cmd }}
                                                    @if($isBtn && $channel->show_reply_keyboard)
                                                        <i class="ti ti-square-check ms-1 text-purple"></i>
                                                    @endif
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
                                            data-show-reply-keyboard="{{ $channel->show_reply_keyboard ? 1 : 0 }}"
                                            data-custom-buttons='@json($channel->custom_buttons ?? (object)[])'
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
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 2: AKUN TELEGRAM GTK -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-teachers" role="tabpanel" aria-labelledby="nav-teachers-tab">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                        <i class="ti ti-users text-primary me-2 fs-2"></i>
                        Daftar Chat ID Telegram Guru & Tenaga Kependidikan
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
                            <th>Nama Guru / GTK</th>
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
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 3: UJI COBA & PENGINGAT -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-testing" role="tabpanel" aria-labelledby="nav-testing-tab">
        <div class="row g-4 mb-4">
            <!-- Kolom Kiri: Uji Coba Pengiriman & Broadcast Cepat -->
            <div class="col-lg-6">
                <!-- Uji Coba Pengiriman -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-send text-info me-2"></i> Uji Coba Pengiriman Pesan Langsung
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('telegram.test') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">Chat ID Target Penerima</label>
                                <div class="input-group">
                                    <input type="text" name="test_chat_id" class="form-control font-monospace" 
                                        placeholder="Contoh: 123456789 atau -100xxxxxxxxxx" required>
                                    <button type="submit" class="btn btn-info text-white">
                                        <i class="ti ti-send me-1"></i> Kirim Uji Coba
                                    </button>
                                </div>
                                <small class="text-muted">Masukkan Chat ID pribadi Anda atau Chat ID grup untuk memastikan bot merespons.</small>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card Siaran Pengumuman Cepat -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-broadcast text-primary me-2"></i> Siaran Pengumuman Cepat ke Saluran Grup
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('telegram.broadcast') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">Pilih Saluran / Grup Tujuan</label>
                                <select name="target_channel" class="form-select" required>
                                    <option value="">-- Pilih Grup Sekolah --</option>
                                    @foreach($channels as $ch)
                                        @if($ch->is_active && $ch->chat_id)
                                            <option value="{{ $ch->code }}">{{ $ch->name }} ({{ $ch->chat_id }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">Judul Pengumuman</label>
                                <input type="text" name="broadcast_title" class="form-control" required placeholder="Contoh: Pengingat Upacara Hari Senin">
                            </div>
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">Isi Pesan Pengumuman</label>
                                <textarea name="broadcast_message" class="form-control" rows="4" required placeholder="Tuliskan isi pengumuman atau instruksi resmi..."></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary shadow-sm">
                                    <i class="ti ti-send me-1"></i> Kirim Siaran Pengumuman
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Pemicu Pengingat Manual -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-bell-ringing text-warning me-2"></i> Pemicu Pengingat Manual Otomatis
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-secondary small mb-3">
                            Selain berjalan sesuai jadwal cron otomatis, Anda dapat memicu pengingat notifikasi ke guru yang bertugas besok secara manual kapan saja:
                        </div>

                        <div class="d-flex flex-column gap-3">
                            <!-- Pengingat Piket -->
                            <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark"><i class="ti ti-shield-check text-warning me-1"></i> Pengingat Guru Piket</div>
                                    <div class="text-muted small">Kirim pemberitahuan ke guru yang terjadwal piket besok pagi</div>
                                </div>
                                <form action="{{ route('telegram.reminder') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="type" value="tomorrow">
                                    <button type="submit" class="btn btn-sm btn-outline-warning shadow-sm">
                                        <i class="ti ti-bell me-1"></i> Kirim (H-1)
                                    </button>
                                </form>
                            </div>

                            <!-- Pengingat Supervisi -->
                            <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark"><i class="ti ti-clipboard-check text-indigo me-1"></i> Pengingat Supervisi Guru</div>
                                    <div class="text-muted small">Kirim pengingat jadwal observasi kelas besok ke guru & supervisor</div>
                                </div>
                                <form action="{{ route('telegram.supervision-reminder') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="type" value="tomorrow">
                                    <button type="submit" class="btn btn-sm btn-outline-indigo shadow-sm">
                                        <i class="ti ti-bell me-1"></i> Kirim (H-1)
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Panduan Chat ID -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-help text-secondary me-2"></i> Panduan Chat ID Telegram
                        </h4>
                    </div>
                    <div class="card-body p-4 small text-secondary">
                        <ol class="ps-3 mb-0">
                            <li class="mb-2">
                                <b>Chat ID Guru Pribadi:</b> Guru membuka bot <code>@userinfobot</code> atau mengirim perintah <code>/id</code> ke bot sekolah untuk melihat nomor ID uniknya.
                            </li>
                            <li class="mb-2">
                                <b>Chat ID Grup:</b> Masukkan Bot ke dalam grup sebagai Admin. Masukkan bot <code>@userinfobot</code> ke grup untuk melihat ID Grup (diawali minus, contoh: <code>-1002345678901</code>).
                            </li>
                            <li>
                                <b>Wajib Klik Start:</b> Telegram mewajibkan setiap guru mengklik <b>Start</b> pada bot sekolah sebelum bot diizinkan mengirimkan pesan pribadi.
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 4: RIWAYAT NOTIFIKASI -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-logs" role="tabpanel" aria-labelledby="nav-logs-tab">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-history text-secondary me-2"></i> Riwayat Pengiriman Notifikasi Bot Telegram
                </h4>
                <div class="card-actions d-flex gap-2 align-items-center">
                    <form action="{{ route('telegram.index') }}" method="GET" class="d-flex gap-1">
                        <input type="hidden" name="tab" value="logs">
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
    </div>
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

@php
    $defs = $commandDefinitions ?? \App\Models\TelegramChannel::getDefaultCommandDefinitions();
    $categories = $commandCategories ?? \App\Models\TelegramChannel::getCommandCategories();
    $presets = $commandPresets ?? \App\Models\TelegramChannel::getCommandPresets();
@endphp

<!-- ========================================================================= -->
<!-- MODAL: TAMBAH SALURAN GRUP -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-tambah-saluran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form action="{{ route('telegram.channel.store') }}" method="POST" class="modal-content shadow">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="ti ti-messages me-2 fs-2"></i>
                    Tambah Saluran Grup Telegram Sekolah
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 modal-body-scrollable">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label required">Nama Saluran Grup</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Warga TK SMK PGRI Kras / Tim Piket" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ID Chat Telegram (Awali tanda minus misal -100...)</label>
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

                    <!-- Pengaturan Reply Keyboard & Kustomisasi Menu Perintah -->
                    <div class="col-12 mt-3">
                        <div class="card border shadow-none">
                            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
                                <div>
                                    <h4 class="card-title mb-0 d-flex align-items-center">
                                        <i class="ti ti-keyboard me-2 text-primary"></i>
                                        Konfigurasi Menu Perintah & Tombol Keyboard Grup
                                    </h4>
                                    <small class="text-muted">Pilih paket kelompok perintah siap pakai di bawah, atau atur perintah & label tombol secara manual.</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="show_reply_keyboard" value="1" id="add_show_reply_keyboard" checked>
                                    <label class="form-check-label fw-bold small" for="add_show_reply_keyboard">Tampilkan Reply Keyboard di Grup</label>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Preset Kelompok Perintah Siap Pakai -->
                                <div class="p-3 bg-light border-bottom">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <span class="fw-bold small text-dark d-flex align-items-center">
                                            <i class="ti ti-bookmarks me-1 text-primary fs-3"></i> 
                                            Pilih Cepat Kelompok Perintah (Preset Paket Grup):
                                        </span>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-xs btn-outline-dark btn-preset-all" data-target="add">
                                                <i class="ti ti-check-all me-1"></i> Pilih Semua
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-danger btn-preset-clear" data-target="add">
                                                <i class="ti ti-trash me-1"></i> Kosongkan
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($presets as $pKey => $p)
                                            <button type="button" class="btn btn-sm {{ $p['color'] }} shadow-none btn-apply-preset" 
                                                data-target="add" 
                                                data-commands='@json($p['commands'])'
                                                title="Pilih kelompok perintah {{ $p['name'] }}">
                                                <i class="ti {{ $p['icon'] }} me-1"></i> {{ $p['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm table-striped mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th style="width: 150px;">Perintah Bot</th>
                                                <th style="width: 110px;" class="text-center" title="Perintah ini aktif dan bisa digunakan di grup">Aktif di Grup</th>
                                                <th style="width: 120px;" class="text-center" title="Tampilkan sebagai tombol di keyboard bawah Telegram">Tombol Reply</th>
                                                <th style="min-width: 220px;">Teks Label Tombol (Kustom)</th>
                                                <th style="min-width: 240px;">Deskripsi Menu Telegram</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($categories as $catKey => $cat)
                                                @php
                                                    $catDefs = collect($defs)->filter(fn($d) => ($d['category'] ?? 'utilitas') === $catKey);
                                                @endphp
                                                @if($catDefs->isNotEmpty())
                                                    <tr class="table-light border-top border-bottom border-2">
                                                        <td colspan="5" class="py-2 px-3 bg-light">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div class="fw-bold text-dark d-flex align-items-center">
                                                                    <span class="badge {{ $cat['badge'] }} p-2 me-2">
                                                                        <i class="ti {{ $cat['icon'] }}"></i>
                                                                    </span>
                                                                    <span class="fs-4">{{ $cat['name'] }}</span>
                                                                    <span class="badge bg-secondary-lt ms-2">{{ $catDefs->count() }} Perintah</span>
                                                                </div>
                                                                <div class="d-flex gap-1">
                                                                    <button type="button" class="btn btn-xs btn-outline-primary btn-group-toggle" data-action="check" data-cat="{{ $catKey }}" data-target="add">
                                                                        <i class="ti ti-check me-1"></i> Pilih Kelompok Ini
                                                                    </button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-group-toggle" data-action="uncheck" data-cat="{{ $catKey }}" data-target="add">
                                                                        <i class="ti ti-x me-1"></i> Batal
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @foreach($catDefs as $cmd => $def)
                                                        <tr class="add-cmd-row cat-{{ $catKey }}" data-cmd="{{ $cmd }}" data-cat="{{ $catKey }}">
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="ti {{ $def['icon'] ?? 'ti-terminal-2' }} text-primary me-2"></i>
                                                                    <span class="font-monospace fw-bold text-dark">{{ $cmd }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input add-cmd-cb" type="checkbox" name="enabled_commands[]" value="{{ $cmd }}" checked>
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input add-btn-cb" type="checkbox" name="custom_buttons[{{ $cmd }}][show_reply_button]" value="1" checked>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="custom_buttons[{{ $cmd }}][label]" class="form-control form-control-sm" placeholder="{{ $def['label'] }}">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="custom_buttons[{{ $cmd }}][description]" class="form-control form-control-sm" placeholder="{{ $def['description'] }}">
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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

<!-- ========================================================================= -->
<!-- MODAL: EDIT SALURAN GRUP -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-edit-saluran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="form-edit-channel" method="POST" class="modal-content shadow">
            @csrf
            @method('PUT')
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="ti ti-edit me-2 fs-2"></i>
                    Edit Saluran Grup: <span id="modal_edit_channel_title" class="ms-1">-</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 modal-body-scrollable">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label required">Nama Saluran Grup</label>
                        <input type="text" name="name" id="edit_channel_name" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ID Chat Telegram (Awali tanda minus misal -100...)</label>
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

                    <!-- Pengaturan Reply Keyboard & Kustomisasi Menu Perintah -->
                    <div class="col-12 mt-3">
                        <div class="card border shadow-none">
                            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
                                <div>
                                    <h4 class="card-title mb-0 d-flex align-items-center">
                                        <i class="ti ti-keyboard me-2 text-primary"></i>
                                        Konfigurasi Menu Perintah & Tombol Keyboard Grup
                                    </h4>
                                    <small class="text-muted">Pilih paket kelompok perintah siap pakai di bawah, atau atur perintah & label tombol secara manual.</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="show_reply_keyboard" value="1" id="edit_show_reply_keyboard" checked>
                                    <label class="form-check-label fw-bold small" for="edit_show_reply_keyboard">Tampilkan Reply Keyboard di Grup</label>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Preset Kelompok Perintah Siap Pakai -->
                                <div class="p-3 bg-light border-bottom">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <span class="fw-bold small text-dark d-flex align-items-center">
                                            <i class="ti ti-bookmarks me-1 text-primary fs-3"></i> 
                                            Pilih Cepat Kelompok Perintah (Preset Paket Grup):
                                        </span>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-xs btn-outline-dark btn-preset-all" data-target="edit">
                                                <i class="ti ti-check-all me-1"></i> Pilih Semua
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-danger btn-preset-clear" data-target="edit">
                                                <i class="ti ti-trash me-1"></i> Kosongkan
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($presets as $pKey => $p)
                                            <button type="button" class="btn btn-sm {{ $p['color'] }} shadow-none btn-apply-preset" 
                                                data-target="edit" 
                                                data-commands='@json($p['commands'])'
                                                title="Pilih kelompok perintah {{ $p['name'] }}">
                                                <i class="ti {{ $p['icon'] }} me-1"></i> {{ $p['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm table-striped mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th style="width: 150px;">Perintah Bot</th>
                                                <th style="width: 110px;" class="text-center" title="Perintah ini aktif dan bisa digunakan di grup">Aktif di Grup</th>
                                                <th style="width: 120px;" class="text-center" title="Tampilkan sebagai tombol di keyboard bawah Telegram">Tombol Reply</th>
                                                <th style="min-width: 220px;">Teks Label Tombol (Kustom)</th>
                                                <th style="min-width: 240px;">Deskripsi Menu Telegram</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($categories as $catKey => $cat)
                                                @php
                                                    $catDefs = collect($defs)->filter(fn($d) => ($d['category'] ?? 'utilitas') === $catKey);
                                                @endphp
                                                @if($catDefs->isNotEmpty())
                                                    <tr class="table-light border-top border-bottom border-2">
                                                        <td colspan="5" class="py-2 px-3 bg-light">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div class="fw-bold text-dark d-flex align-items-center">
                                                                    <span class="badge {{ $cat['badge'] }} p-2 me-2">
                                                                        <i class="ti {{ $cat['icon'] }}"></i>
                                                                    </span>
                                                                    <span class="fs-4">{{ $cat['name'] }}</span>
                                                                    <span class="badge bg-secondary-lt ms-2">{{ $catDefs->count() }} Perintah</span>
                                                                </div>
                                                                <div class="d-flex gap-1">
                                                                    <button type="button" class="btn btn-xs btn-outline-primary btn-group-toggle" data-action="check" data-cat="{{ $catKey }}" data-target="edit">
                                                                        <i class="ti ti-check me-1"></i> Pilih Kelompok Ini
                                                                    </button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-group-toggle" data-action="uncheck" data-cat="{{ $catKey }}" data-target="edit">
                                                                        <i class="ti ti-x me-1"></i> Batal
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @foreach($catDefs as $cmd => $def)
                                                        <tr class="edit-cmd-row cat-{{ $catKey }}" data-cmd="{{ $cmd }}" data-cat="{{ $catKey }}">
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="ti {{ $def['icon'] ?? 'ti-terminal-2' }} text-primary me-2"></i>
                                                                    <span class="font-monospace fw-bold text-dark">{{ $cmd }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input edit-command-checkbox" type="checkbox" name="enabled_commands[]" value="{{ $cmd }}" id="edit_cmd_{{ ltrim(str_replace('/', '', $cmd), '_') }}">
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input edit-show-btn-checkbox" type="checkbox" name="custom_buttons[{{ $cmd }}][show_reply_button]" value="1" checked>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="custom_buttons[{{ $cmd }}][label]" class="form-control form-control-sm edit-label-input" placeholder="{{ $def['label'] }}">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="custom_buttons[{{ $cmd }}][description]" class="form-control form-control-sm edit-desc-input" placeholder="{{ $def['description'] }}">
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
                                <option value="{{ $ch->code }}">{{ $ch->name }} {{ $ch->chat_id ? '('.$ch->chat_id.')' : '' }}</option>
                            @endforeach
                            <option value="supervision" class="d-none">Grup Supervisi Guru</option>
                            <option value="activities" class="d-none">Grup Kegiatan Sekolah</option>
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

@push('styles')
<style>
.modal-body-scrollable {
    max-height: calc(85vh - 120px);
    overflow-y: auto !important;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.modal-body-scrollable::-webkit-scrollbar {
    width: 8px;
}
.modal-body-scrollable::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.modal-body-scrollable::-webkit-scrollbar-thumb {
    background-color: #94a3b8;
    border-radius: 4px;
    border: 2px solid #f1f5f9;
}
.modal-body-scrollable::-webkit-scrollbar-thumb:hover {
    background-color: #64748b;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // URL Hash & Tab Activation Handler
    // -------------------------------------------------------------
    const urlParams = new URLSearchParams(window.location.search);
    let activeTabId = null;

    if (urlParams.get('tab') === 'logs' || urlParams.get('target_type') || urlParams.get('page')) {
        activeTabId = 'tab-logs';
    } else if (window.location.hash) {
        activeTabId = window.location.hash.replace('#', '');
    }

    if (activeTabId) {
        const triggerEl = document.querySelector(`a[href="#${activeTabId}"]`);
        if (triggerEl) {
            const tabInstance = new bootstrap.Tab(triggerEl);
            tabInstance.show();
        }
    }

    // Update URL hash when tab is switched
    document.querySelectorAll('#telegramTabs a[data-bs-toggle="tab"]').forEach(function (tabEl) {
        tabEl.addEventListener('shown.bs.tab', function (e) {
            const target = e.target.getAttribute('href');
            if (target && target.startsWith('#')) {
                history.replaceState(null, null, target);
            }
        });
    });

    // -------------------------------------------------------------
    // Edit Teacher Chat ID Modal
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // Preset & Category Batch Selection Handlers
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-apply-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.dataset.target; // 'add' or 'edit'
            let selectedCmds = [];
            try {
                selectedCmds = JSON.parse(this.dataset.commands || '[]');
            } catch (e) {
                selectedCmds = [];
            }

            const prefix = target === 'add' ? '.add-' : '.edit-';
            document.querySelectorAll(prefix + 'cmd-row').forEach(function (row) {
                const cmd = row.dataset.cmd;
                const isSelected = selectedCmds.includes(cmd);

                const cmdCb = row.querySelector(target === 'add' ? '.add-cmd-cb' : '.edit-command-checkbox');
                if (cmdCb) {
                    cmdCb.checked = isSelected;
                }

                const btnCb = row.querySelector(target === 'add' ? '.add-btn-cb' : '.edit-show-btn-checkbox');
                if (btnCb) {
                    btnCb.checked = isSelected;
                }
            });
        });
    });

    document.querySelectorAll('.btn-preset-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.dataset.target;
            const prefix = target === 'add' ? '.add-' : '.edit-';
            document.querySelectorAll(prefix + 'cmd-row').forEach(function (row) {
                const cmdCb = row.querySelector(target === 'add' ? '.add-cmd-cb' : '.edit-command-checkbox');
                if (cmdCb) cmdCb.checked = true;
                const btnCb = row.querySelector(target === 'add' ? '.add-btn-cb' : '.edit-show-btn-checkbox');
                if (btnCb) btnCb.checked = true;
            });
        });
    });

    document.querySelectorAll('.btn-preset-clear').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.dataset.target;
            const prefix = target === 'add' ? '.add-' : '.edit-';
            document.querySelectorAll(prefix + 'cmd-row').forEach(function (row) {
                const cmdCb = row.querySelector(target === 'add' ? '.add-cmd-cb' : '.edit-command-checkbox');
                if (cmdCb) cmdCb.checked = false;
                const btnCb = row.querySelector(target === 'add' ? '.add-btn-cb' : '.edit-show-btn-checkbox');
                if (btnCb) btnCb.checked = false;
            });
        });
    });

    document.querySelectorAll('.btn-group-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const cat = this.dataset.cat;
            const action = this.dataset.action;
            const target = this.dataset.target;
            const prefix = target === 'add' ? '.add-' : '.edit-';
            const shouldCheck = (action === 'check');

            document.querySelectorAll(prefix + 'cmd-row.cat-' + cat).forEach(function (row) {
                const cmdCb = row.querySelector(target === 'add' ? '.add-cmd-cb' : '.edit-command-checkbox');
                if (cmdCb) cmdCb.checked = shouldCheck;
                const btnCb = row.querySelector(target === 'add' ? '.add-btn-cb' : '.edit-show-btn-checkbox');
                if (btnCb) btnCb.checked = shouldCheck;
            });
        });
    });

    // -------------------------------------------------------------
    // Edit Telegram Channel Modal
    // -------------------------------------------------------------
    const btnCheckAllCmd = document.getElementById('btn-check-all-edit-cmd');
    if (btnCheckAllCmd) {
        btnCheckAllCmd.addEventListener('click', function () {
            document.querySelectorAll('.edit-command-checkbox').forEach(function (cb) {
                cb.checked = true;
            });
        });
    }

    const btnUncheckAllBtn = document.getElementById('btn-uncheck-all-edit-btn');
    if (btnUncheckAllBtn) {
        btnUncheckAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.edit-show-btn-checkbox').forEach(function (cb) {
                cb.checked = false;
            });
        });
    }

    document.querySelectorAll('.btn-edit-channel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name || '';
            const chatId = this.dataset.chatId || '';
            const description = this.dataset.description || '';
            const inviteLink = this.dataset.inviteLink || '';
            const active = this.dataset.active === '1';
            const showReplyKeyboard = this.dataset.showReplyKeyboard !== '0';

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

            let customButtons = {};
            try {
                customButtons = JSON.parse(this.dataset.customButtons || '{}');
            } catch (e) {
                customButtons = {};
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
            document.getElementById('edit_show_reply_keyboard').checked = showReplyKeyboard;

            // Check roles
            document.querySelectorAll('.edit-role-checkbox').forEach(function (cb) {
                cb.checked = roles.includes(cb.value);
            });

            // Check commands
            document.querySelectorAll('.edit-command-checkbox').forEach(function (cb) {
                cb.checked = commands.includes(cb.value);
            });

            // Populate custom buttons configuration
            document.querySelectorAll('.edit-cmd-row').forEach(function (row) {
                const cmd = row.dataset.cmd;
                const conf = customButtons[cmd] || {};

                const btnCb = row.querySelector('.edit-show-btn-checkbox');
                if (btnCb) {
                    btnCb.checked = conf.show_reply_button !== undefined 
                        ? (conf.show_reply_button === true || conf.show_reply_button === 1 || conf.show_reply_button === '1') 
                        : true;
                }

                const labelInput = row.querySelector('.edit-label-input');
                if (labelInput) {
                    labelInput.value = conf.label || '';
                }

                const descInput = row.querySelector('.edit-desc-input');
                if (descInput) {
                    descInput.value = conf.description || '';
                }
            });

            const modal = new bootstrap.Modal(document.getElementById('modal-edit-saluran'));
            modal.show();
        });
    });
});
</script>
@endpush
