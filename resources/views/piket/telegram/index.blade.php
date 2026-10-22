@extends('layouts.app')

@section('title', 'Pengaturan & Bot Telegram')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Integrasi Notifikasi Real-Time
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-brand-telegram text-primary me-2 fs-1"></i>
                    Pengaturan & Bot Telegram Piket
                </h2>
                <div class="text-secondary mt-1">
                    Konfigurasi bot Telegram untuk notifikasi siswa terlambat, izin keluar, laporan kasus BK, dan pengingat jadwal piket guru.
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Konfigurasi Bot & Uji Coba -->
        <div class="col-lg-6">
            <!-- Form Konfigurasi Bot -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-settings text-primary me-2"></i>
                        Konfigurasi Bot Telegram
                    </h3>
                </div>
                <form action="{{ route('piket.telegram.settings') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Bot Token Telegram</label>
                            <input type="text" name="telegram_bot_token" class="form-control font-monospace" value="{{ $setting->telegram_bot_token }}" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                            <div class="form-text small">Dapatkan token dari <a href="https://t.me/BotFather" target="_blank" class="fw-bold">@BotFather</a> di aplikasi Telegram.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Username Bot</label>
                            <input type="text" name="telegram_bot_username" class="form-control" value="{{ $setting->telegram_bot_username }}" placeholder="@NamaSekolahBot">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Chat ID Grup Tim Kedisiplinan & BK</label>
                            <input type="text" name="telegram_discipline_chat_id" class="form-control font-monospace" value="{{ $setting->telegram_discipline_chat_id }}" placeholder="Contoh: -1001234567890">
                            <div class="form-text small">ID grup Telegram untuk notifikasi kasus & laporan keterlambatan massal.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Chat ID Grup Guru Piket</label>
                            <input type="text" name="telegram_piket_chat_id" class="form-control font-monospace" value="{{ $setting->telegram_piket_chat_id }}" placeholder="Contoh: -1009876543210">
                        </div>

                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="telegram_notifications_enabled" value="1" {{ $setting->telegram_notifications_enabled ? 'checked' : '' }}>
                                <span class="form-check-label fw-bold">Aktifkan Pengiriman Notifikasi Otomatis</span>
                            </label>
                            <div class="form-text small">Jika diaktifkan, notifikasi akan langsung terkirim saat perizinan atau kasus dicatat.</div>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Uji Coba Pengiriman & Broadcast Pengingat -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-send text-info me-2"></i>
                        Uji Coba Koneksi & Pengingat Piket
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Test Send -->
                    <form action="{{ route('piket.telegram.test') }}" method="POST" class="mb-4">
                        @csrf
                        <label class="form-label fw-bold">Kirim Pesan Uji Coba (Test Connection)</label>
                        <div class="input-group">
                            <input type="text" name="test_chat_id" class="form-control font-monospace" placeholder="Masukkan Chat ID Anda atau Grup..." required>
                            <button type="submit" class="btn btn-info">
                                <i class="ti ti-send me-1"></i> Test Kirim
                            </button>
                        </div>
                        <div class="form-text small">Ketik <code>/start</code> ke bot Anda terlebih dahulu atau gunakan bot <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> untuk melihat Chat ID Anda.</div>
                    </form>

                    <div class="hr-text my-3">Pengingat Jadwal Guru Piket</div>

                    <!-- Trigger Broadcast -->
                    <div class="d-flex flex-wrap gap-2">
                        <form action="{{ route('piket.telegram.reminder') }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="tomorrow">
                            <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Kirim notifikasi pengingat piket untuk besok ke semua guru piket terjadwal?')">
                                <i class="ti ti-moon me-1"></i> Kirim Pengingat Besok (H-1)
                            </button>
                        </form>

                        <form action="{{ route('piket.telegram.reminder') }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="today">
                            <button type="submit" class="btn btn-outline-success" onclick="return confirm('Kirim notifikasi pengingat piket hari ini ke semua guru piket bertugas?')">
                                <i class="ti ti-sun me-1"></i> Kirim Pengingat Hari Ini
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Telegram Chat ID Guru & Log Pengiriman -->
        <div class="col-lg-6">
            <!-- Card Chat ID Guru -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-users text-primary me-2"></i>
                        Chat ID Telegram Guru (Wali Kelas / Guru Mapel)
                    </h3>
                    <span class="badge bg-blue-lt">{{ $teachers->whereNotNull('telegram_chat_id')->count() }} Terhubung</span>
                </div>
                <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @foreach($teachers as $t)
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <div class="fw-bold text-dark">{{ $t->name }}</div>
                                    <div class="text-muted small">
                                        @if($t->telegram_chat_id)
                                            <span class="text-success"><i class="ti ti-brand-telegram me-1"></i>Chat ID: <code>{{ $t->telegram_chat_id }}</code></span>
                                        @else
                                            <span class="text-warning"><i class="ti ti-alert-circle me-1"></i>Belum terhubung Chat ID</span>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost-primary" data-bs-toggle="modal" data-bs-target="#modal-edit-tg-{{ $t->id }}">
                                    <i class="ti ti-edit"></i> Edit ID
                                </button>
                            </div>

                            <!-- Modal Edit Chat ID Guru -->
                            <div class="modal fade" id="modal-edit-tg-{{ $t->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('piket.telegram.teacher-chat-id') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="teacher_id" value="{{ $t->id }}">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Atur Chat ID Telegram: {{ $t->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label required">Telegram Chat ID</label>
                                                    <input type="text" name="telegram_chat_id" class="form-control font-monospace" value="{{ $t->telegram_chat_id }}" placeholder="Contoh: 123456789">
                                                    <div class="form-text small">Guru dapat mengetahui ID mereka dengan mengirim pesan ke bot <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Username Telegram (Opsional)</label>
                                                    <input type="text" name="telegram_username" class="form-control" value="{{ $t->telegram_username }}" placeholder="@username_guru">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary">Simpan Chat ID</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Card Log Notifikasi Telegram -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-history text-secondary me-2"></i>
                        Log Pengiriman Pesan Telegram Terakhir
                    </h3>
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-vcenter card-table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Penerima & Tipe</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>
                                        <span class="small text-muted">{{ $log->created_at?->format('d/m H:i:s') }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-bold small text-dark">{{ $log->recipient_name }}</div>
                                        <span class="badge bg-light text-muted" style="font-size: 0.68rem;">{{ $log->target_type }}</span>
                                    </td>
                                    <td>
                                        @if($log->status === 'sent')
                                            <span class="badge bg-success text-white" style="font-size: 0.68rem;"><i class="ti ti-check"></i> Terkirim</span>
                                        @else
                                            <span class="badge bg-danger text-white" style="font-size: 0.68rem;"><i class="ti ti-x"></i> Gagal</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted small">
                                        Belum ada riwayat pengiriman pesan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
