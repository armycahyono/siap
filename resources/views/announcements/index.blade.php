@extends('layouts.web')

@section('title', 'Pengumuman Sekolah — SMK PGRI Kras')

@section('content')
@php
    $annActions = '';
    if ($unreadCount > 0) {
        $annActions .= '
            <form action="' . route('announcements.mark-all-read') . '" method="POST" class="d-inline">
                ' . csrf_field() . '
                <button type="submit" class="btn btn-outline-light shadow-sm" title="Tandai semua pengumuman sebagai sudah dibaca">
                    <i class="ti ti-checks me-1 text-info"></i> Tandai Semua Dibaca
                </button>
            </form>
        ';
    }
    if ($canPublish) {
        $annActions .= '
            <button type="button" class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-create-announcement">
                <i class="ti ti-plus me-1"></i> Buat Pengumuman Baru
            </button>
        ';
    }
@endphp

@include('layouts._hero_header', [
    'title' => 'Pengumuman & Edaran Resmi Sekolah',
    'subtitle' => 'Pusat informasi dan edaran resmi dari Kepala Sekolah, Wakil Kepala Sekolah, dan Ketua Jurusan (Kaprogli).',
    'icon' => 'ti ti-speakerphone',
    'category' => 'PUSAT INFORMASI SEKOLAH',
    'actions' => $annActions
])

<div class="container-xl">
    <!-- Navigasi Tab & Pencarian -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center justify-content-between">
                <div class="col-md-auto">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a href="{{ route('announcements.index', ['tab' => 'all', 'search' => $search]) }}" class="nav-link {{ $tab === 'all' ? 'active' : '' }}">
                                <i class="ti ti-list me-1"></i> Semua Pengumuman
                                <span class="badge bg-secondary-lt ms-1">{{ $totalActiveCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('announcements.index', ['tab' => 'unread', 'search' => $search]) }}" class="nav-link {{ $tab === 'unread' ? 'active' : '' }}">
                                <i class="ti ti-bell-ringing me-1"></i> Belum Dibaca
                                @if($unreadCount > 0)
                                    <span class="badge bg-danger text-white ms-1 rounded-pill">{{ $unreadCount }}</span>
                                @else
                                    <span class="badge bg-secondary-lt ms-1">0</span>
                                @endif
                            </a>
                        </li>
                        @if($canPublish)
                            <li class="nav-item">
                                <a href="{{ route('announcements.index', ['tab' => 'my', 'search' => $search]) }}" class="nav-link {{ $tab === 'my' ? 'active' : '' }}">
                                    <i class="ti ti-user me-1"></i> Pengumuman Saya
                                    <span class="badge bg-primary-lt ms-1">{{ $myAnnouncementsCount }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('announcements.index') }}" method="GET" class="input-group">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Cari judul atau isi pengumuman...">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="ti ti-search"></i>
                        </button>
                        @if($search)
                            <a href="{{ route('announcements.index', ['tab' => $tab]) }}" class="btn btn-outline-danger" title="Hapus pencarian">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifikasi / Pesan Sukses -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible shadow-sm fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-circle-check fs-2 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible shadow-sm fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-2 me-2"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Daftar Pengumuman -->
    @if($announcements->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <div class="avatar avatar-xl bg-light rounded-circle mx-auto mb-3">
                    <i class="ti ti-speakerphone-off fs-1 text-muted"></i>
                </div>
                <h3 class="fw-bold text-dark mb-1">Belum Ada Pengumuman</h3>
                <p class="text-muted max-w-sm mx-auto">
                    @if($search)
                        Tidak ditemukan pengumuman dengan kata kunci "<strong>{{ $search }}</strong>".
                    @elseif($tab === 'unread')
                        Hebat! Semua pengumuman telah Anda baca dan up to date.
                    @else
                        Saat ini belum ada pengumuman resmi yang dipublikasikan.
                    @endif
                </p>
                @if($canPublish)
                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modal-create-announcement">
                        <i class="ti ti-plus me-1"></i> Terbitkan Pengumuman Pertama
                    </button>
                @endif
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($announcements as $ann)
                @php
                    $isRead = $ann->isReadBy(auth()->user());
                    $authorName = $ann->author ? $ann->author->name : 'Pimpinan Sekolah';
                    $authorRole = $ann->author ? ($ann->author->role?->name ?? 'Pimpinan') : 'Pimpinan';
                    $isMine = $ann->author_id === auth()->id();
                @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 position-relative announcement-card {{ !$isRead ? 'border-start border-4 border-primary' : '' }}">
                        @if($ann->is_pinned)
                            <div class="position-absolute top-0 end-0 p-2" title="Pengumuman Tersemat">
                                <span class="badge bg-yellow text-yellow-fg shadow-sm"><i class="ti ti-pin"></i></span>
                            </div>
                        @endif

                        <div class="card-body p-4 d-flex flex-column">
                            <!-- Badge Status & Target -->
                            <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                                @if(!$isRead)
                                    <span class="badge bg-danger-lt text-danger fw-bold"><i class="ti ti-bell-ringing me-1"></i> Baru</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-check me-1"></i> Dibaca</span>
                                @endif

                                @if($canPublish && ($isMine || auth()->user()->isSuperAdmin() || auth()->user()->isPrincipal() || auth()->user()->isVicePrincipal()))
                                    <a href="javascript:void(0)" class="badge bg-blue-lt text-blue text-decoration-none btn-view-readers" data-id="{{ $ann->id }}" title="Klik untuk melihat siapa saja yang sudah membaca">
                                        <i class="ti ti-users-group me-1"></i> {{ $ann->reads_count }} Dibaca
                                    </a>
                                @endif

                                @if($ann->target_type === 'all')
                                    <span class="badge bg-success-lt text-success" title="Ditujukan untuk semua guru & staf"><i class="ti ti-users me-1"></i> Semua GTK</span>
                                @elseif($ann->target_type === 'role')
                                    <span class="badge bg-azure-lt text-azure" title="Sasaran Peran: {{ $ann->target_label }}"><i class="ti ti-user-check me-1"></i> {{ \Illuminate\Support\Str::limit($ann->target_label, 20) }}</span>
                                @elseif($ann->target_type === 'department')
                                    <span class="badge bg-indigo-lt text-indigo" title="Sasaran Jurusan: {{ $ann->target_label }}"><i class="ti ti-building me-1"></i> {{ \Illuminate\Support\Str::limit($ann->target_label, 20) }}</span>
                                @endif

                                @if($ann->send_telegram && $ann->telegram_sent_at)
                                    <span class="badge bg-teal-lt text-teal" title="Telah disiarkan ke Telegram ({{ $ann->telegram_sent_count }} penerima)"><i class="ti ti-brand-telegram me-1"></i> Telegram</span>
                                @endif
                            </div>

                            <!-- Judul Pengumuman -->
                            <h3 class="card-title fw-bold text-dark mb-2">
                                <a href="javascript:void(0)" class="text-reset btn-view-announcement" data-id="{{ $ann->id }}">
                                    {{ $ann->title }}
                                </a>
                            </h3>

                            <!-- Cuplikan Isi -->
                            <div class="text-muted small mb-3 flex-grow-1 lh-sm" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ strip_tags($ann->content) }}
                            </div>

                            <!-- Info Pengirim & Waktu -->
                            <div class="pt-2 border-top mt-auto">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-xs rounded-circle bg-primary-lt me-2">
                                            {{ strtoupper(substr($authorName, 0, 1)) }}
                                        </span>
                                        <div class="small">
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 140px;" title="{{ $authorName }}">{{ $authorName }}</div>
                                            <div class="text-muted" style="font-size: 10px;">{{ $authorRole }}</div>
                                        </div>
                                    </div>
                                    <div class="text-muted small text-end" style="font-size: 11px;">
                                        <i class="ti ti-clock me-1"></i>
                                        {{ $ann->published_at ? $ann->published_at->diffForHumans() : '-' }}
                                    </div>
                                </div>

                                <!-- Action Buttons: Baca, WhatsApp, Salin, Edit -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-1">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-view-announcement" data-id="{{ $ann->id }}">
                                            <i class="ti ti-eye me-1"></i> Baca
                                        </button>

                                        @if($canPublish && ($isMine || auth()->user()->isSuperAdmin() || auth()->user()->isPrincipal() || auth()->user()->isVicePrincipal()))
                                            <button type="button" class="btn btn-sm btn-outline-info btn-view-readers" data-id="{{ $ann->id }}" title="Lihat status pembaca">
                                                <i class="ti ti-users-group me-1"></i> <span class="d-none d-sm-inline">Pembaca (</span>{{ $ann->reads_count }}<span class="d-none d-sm-inline">)</span>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="d-inline-flex gap-1">
                                        <!-- Tombol Bagikan ke WhatsApp -->
                                        <a href="{{ $ann->whatsapp_share_url }}" target="_blank" class="btn btn-sm btn-outline-success" title="Bagikan pengumuman ini ke WhatsApp">
                                            <i class="ti ti-brand-whatsapp"></i>
                                        </a>

                                        <!-- Tombol Salin Teks -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-copy-announcement" 
                                            data-text="{{ $ann->getWhatsAppShareText() }}" 
                                            title="Salin Teks Pengumuman">
                                            <i class="ti ti-copy"></i>
                                        </button>

                                        @if($canPublish && ($isMine || auth()->user()->isSuperAdmin() || auth()->user()->isPrincipal() || auth()->user()->isVicePrincipal()))
                                            <!-- Tombol Menu Aksi Khusus Pimpinan -->
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                                    <li>
                                                        <a class="dropdown-item btn-view-readers" href="javascript:void(0)" data-id="{{ $ann->id }}">
                                                            <i class="ti ti-users-group me-2 text-info"></i> Status Pembaca ({{ $ann->reads_count }})
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item btn-edit-announcement" href="javascript:void(0)" data-id="{{ $ann->id }}">
                                                            <i class="ti ti-edit me-2 text-warning"></i> Edit Pengumuman
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form action="{{ route('announcements.resend-telegram', $ann->id) }}" method="POST" onsubmit="return confirm('Siarkan ulang pengumuman ini ke Telegram sekarang?')">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="ti ti-brand-telegram me-2 text-teal"></i> Siar Ulang Telegram
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('announcements.destroy', $ann->id) }}" method="POST" onsubmit="return confirm('Hapus pengumuman ini secara permanen?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="ti ti-trash me-2"></i> Hapus Pengumuman
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $announcements->links() }}
        </div>
    @endif
</div>

<!-- ========================================================================= -->
<!-- MODAL: BACA LENGKAP PENGUMUMAN -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-view-announcement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-speakerphone fs-2"></i>
                    <h5 class="modal-title fw-bold" id="view_ann_header_title">Detail Pengumuman Resmi</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <span class="badge bg-primary-lt fs-5" id="view_ann_target_badge">Semua Warga GTK</span>
                        <span class="badge bg-yellow-lt fs-5 d-none" id="view_ann_pinned_badge"><i class="ti ti-pin me-1"></i> Tersemat</span>
                    </div>
                    <div class="small text-muted" id="view_ann_date">
                        <i class="ti ti-clock me-1"></i> -
                    </div>
                </div>

                <h2 class="fw-bold text-dark mb-3" id="view_ann_title">-</h2>

                <div class="d-flex align-items-center gap-2 mb-4 p-2 bg-light rounded border">
                    <span class="avatar avatar-sm rounded-circle bg-primary-lt">
                        <i class="ti ti-user fs-3"></i>
                    </span>
                    <div>
                        <div class="fw-bold text-dark" id="view_ann_author">-</div>
                        <div class="small text-muted" id="view_ann_author_role">-</div>
                    </div>
                </div>

                <div class="bg-white p-3 rounded border text-dark lh-base fs-3 mb-4" id="view_ann_content" style="white-space: pre-wrap;">
                    -
                </div>

                <div class="p-3 bg-success-lt rounded border border-success border-opacity-50 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-brand-whatsapp text-success fs-1"></i>
                        <div class="small">
                            <strong class="text-success">Bagikan Informasi Ini:</strong>
                            <div class="text-muted">Kirimkan edaran ini langsung ke grup WhatsApp atau salin teksnya.</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-white border shadow-none" id="btn_modal_copy_ann">
                            <i class="ti ti-copy me-1"></i> Salin Teks
                        </button>
                        <a href="#" target="_blank" class="btn btn-sm btn-success shadow-none" id="btn_modal_wa_ann">
                            <i class="ti ti-brand-whatsapp me-1"></i> Kirim ke WhatsApp
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                @if($canPublish)
                    <button type="button" class="btn btn-outline-info btn-sm btn-view-readers" id="btn_modal_view_readers" data-id="">
                        <i class="ti ti-users-group me-1"></i> Status Pembaca
                    </button>
                @else
                    <div></div>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@if($canPublish)
<!-- ========================================================================= -->
<!-- MODAL: BUAT PENGUMUMAN BARU -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-create-announcement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form action="{{ route('announcements.store') }}" method="POST" class="modal-content shadow">
            @csrf
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="ti ti-speakerphone me-2 fs-2"></i>
                    Terbitkan Pengumuman Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required fw-bold">Judul Pengumuman</label>
                        <input type="text" name="title" class="form-control" placeholder="Contoh: Rapat Koordinasi Persiapan Penilaian Akhir Semester" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Sasaran / Penerima Pengumuman</label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="all" class="form-selectgroup-input target-type-radio" data-target="create" checked>
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-users fs-2 me-2 text-success"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Semua Warga GTK</strong>
                                            <span class="text-muted small">Seluruh Guru &amp; Tenaga Kependidikan</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="role" class="form-selectgroup-input target-type-radio" data-target="create">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-user-check fs-2 me-2 text-azure"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Kelompok / Peran</strong>
                                            <span class="text-muted small">Wali Kelas, Guru Piket, Tim BK, dll.</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="department" class="form-selectgroup-input target-type-radio" data-target="create">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-building fs-2 me-2 text-indigo"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Jurusan / Kaprogli</strong>
                                            <span class="text-muted small">Kelompok Program Keahlian Tertentu</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Peran Spesifik -->
                    <div class="col-12 d-none" id="create-roles-wrapper">
                        <label class="form-label fw-bold text-azure"><i class="ti ti-check me-1"></i> Pilih Peran yang Menerima Pengumuman Ini:</label>
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                @foreach($availableRoles as $role)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check m-0">
                                            <input class="form-check-input" type="checkbox" name="target_roles[]" value="{{ $role->slug }}">
                                            <span class="form-check-label small">{{ $role->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Jurusan Spesifik -->
                    <div class="col-12 d-none" id="create-departments-wrapper">
                        <label class="form-label fw-bold text-indigo"><i class="ti ti-check me-1"></i> Pilih Jurusan yang Menerima Pengumuman Ini:</label>
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                @foreach($availableDepartments as $dept)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check m-0">
                                            <input class="form-check-input" type="checkbox" name="target_departments[]" value="{{ $dept->id }}">
                                            <span class="form-check-label small fw-bold">{{ $dept->code }} - {{ $dept->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Isi Pesan / Edaran Pengumuman</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Tuliskan isi pengumuman lengkap secara jelas..." required></textarea>
                    </div>

                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="send_telegram" value="1" checked id="create_send_telegram">
                                        <span class="form-check-label fw-bold">
                                            <i class="ti ti-brand-telegram text-teal me-1"></i> Kirim Notifikasi ke Telegram
                                        </span>
                                        <span class="d-block small text-muted">Siarkan edaran ini ke saluran grup / Japri bot Telegram.</span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="create_is_pinned">
                                        <span class="form-check-label fw-bold">
                                            <i class="ti ti-pin text-warning me-1"></i> Sematkan di Bagian Teratas
                                        </span>
                                        <span class="d-block small text-muted">Pengumuman akan selalu muncul paling atas di web &amp; mobile.</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary shadow-sm">
                    <i class="ti ti-send me-1"></i> Terbitkan Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT PENGUMUMAN -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-edit-announcement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" id="form-edit-announcement" class="modal-content shadow">
            @csrf
            @method('PUT')
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="ti ti-edit me-2 fs-2"></i>
                    Edit Pengumuman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required fw-bold">Judul Pengumuman</label>
                        <input type="text" name="title" id="edit_ann_title" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Sasaran / Penerima Pengumuman</label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="all" id="edit_target_all" class="form-selectgroup-input target-type-radio" data-target="edit">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-users fs-2 me-2 text-success"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Semua Warga GTK</strong>
                                            <span class="text-muted small">Seluruh Guru &amp; Staf</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="role" id="edit_target_role" class="form-selectgroup-input target-type-radio" data-target="edit">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-user-check fs-2 me-2 text-azure"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Kelompok / Peran</strong>
                                            <span class="text-muted small">Peran Tertentu</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="target_type" value="department" id="edit_target_department" class="form-selectgroup-input target-type-radio" data-target="edit">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <i class="ti ti-building fs-2 me-2 text-indigo"></i>
                                        <span class="text-start">
                                            <strong class="d-block">Jurusan Tertentu</strong>
                                            <span class="text-muted small">Program Keahlian</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Peran Spesifik (Edit) -->
                    <div class="col-12 d-none" id="edit-roles-wrapper">
                        <label class="form-label fw-bold text-azure"><i class="ti ti-check me-1"></i> Pilih Peran yang Menerima:</label>
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                @foreach($availableRoles as $role)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check m-0">
                                            <input class="form-check-input edit-role-cb" type="checkbox" name="target_roles[]" value="{{ $role->slug }}">
                                            <span class="form-check-label small">{{ $role->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Jurusan Spesifik (Edit) -->
                    <div class="col-12 d-none" id="edit-departments-wrapper">
                        <label class="form-label fw-bold text-indigo"><i class="ti ti-check me-1"></i> Pilih Jurusan yang Menerima:</label>
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                @foreach($availableDepartments as $dept)
                                    <div class="col-md-4 col-sm-6">
                                        <label class="form-check m-0">
                                            <input class="form-check-input edit-dept-cb" type="checkbox" name="target_departments[]" value="{{ $dept->id }}">
                                            <span class="form-check-label small fw-bold">{{ $dept->code }} - {{ $dept->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label required fw-bold">Isi Pesan / Edaran Pengumuman</label>
                        <textarea name="content" id="edit_ann_content" class="form-control" rows="6" required></textarea>
                    </div>

                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="edit_ann_pinned">
                                        <span class="form-check-label fw-bold"><i class="ti ti-pin text-warning me-1"></i> Tersemat (Pin)</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_ann_active">
                                        <span class="form-check-label fw-bold"><i class="ti ti-toggle-right text-success me-1"></i> Status Aktif</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="resend_telegram" value="1" id="edit_ann_resend_tg">
                                        <span class="form-check-label fw-bold"><i class="ti ti-brand-telegram text-teal me-1"></i> Kirim Ulang Telegram</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary shadow-sm">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: RINCIAN STATUS PEMBACA PENGUMUMAN -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-readers-announcement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-users-group fs-2 text-info"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Status Pembaca Pengumuman</h5>
                        <div class="text-white-50 small text-truncate" style="max-width: 480px;" id="readers_ann_title_preview">Memuat judul...</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Loading State -->
                <div id="readers-loading-spinner" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Memuat rincian pembaca...</div>
                </div>

                <!-- Content Area -->
                <div id="readers-content-area" class="d-none">
                    <!-- Ringkasan Statistik -->
                    <div class="card border-0 bg-light shadow-none mb-4">
                        <div class="card-body p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-md-5">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fs-1 fw-bold text-primary" id="readers_stat_percent">0%</span>
                                        <div class="lh-sm">
                                            <div class="fw-bold text-dark small">Tingkat Keterbacaan</div>
                                            <div class="text-muted" style="font-size: 11px;">Sasaran: <span id="readers_stat_target_label" class="fw-semibold">-</span></div>
                                        </div>
                                    </div>
                                    <div class="progress progress-sm" style="height: 6px;">
                                        <div class="progress-bar bg-primary" id="readers_stat_progress_bar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-7">
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <div class="p-2 bg-white rounded border">
                                                <div class="text-muted" style="font-size: 11px;">Target</div>
                                                <div class="fs-3 fw-bold text-dark" id="readers_stat_target">0</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 bg-white rounded border border-success-subtle">
                                                <div class="text-success" style="font-size: 11px;">Sudah Baca</div>
                                                <div class="fs-3 fw-bold text-success" id="readers_stat_read">0</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 bg-white rounded border border-danger-subtle">
                                                <div class="text-danger" style="font-size: 11px;">Belum Baca</div>
                                                <div class="fs-3 fw-bold text-danger" id="readers_stat_unread">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Pencarian Live & Tombol Nudge Telegram -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="input-icon flex-grow-1" style="max-width: 320px;">
                            <span class="input-icon-addon">
                                <i class="ti ti-search text-muted"></i>
                            </span>
                            <input type="text" id="readers-search-input" class="form-control form-control-sm" placeholder="Cari nama atau jabatan GTK...">
                        </div>

                        <div id="readers-telegram-action-wrap" class="d-none">
                            <button type="button" class="btn btn-sm btn-outline-teal" id="btn_remind_unread_telegram" title="Kirim notifikasi pengingat Telegram khusus GTK yang belum membaca">
                                <i class="ti ti-brand-telegram me-1"></i> Ingatkan Belum Baca (<span id="readers_telegram_count">0</span>)
                            </button>
                        </div>
                    </div>

                    <!-- Nav Tabs: Sudah Baca vs Belum Baca -->
                    <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold d-flex align-items-center justify-content-center gap-1" id="tab-read-users" data-bs-toggle="tab" data-bs-target="#pane-read-users" type="button" role="tab">
                                <i class="ti ti-check text-success"></i> Sudah Membaca
                                <span class="badge bg-success-lt text-success ms-1" id="badge_count_read">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold d-flex align-items-center justify-content-center gap-1" id="tab-unread-users" data-bs-toggle="tab" data-bs-target="#pane-unread-users" type="button" role="tab">
                                <i class="ti ti-alert-circle text-danger"></i> Belum Membaca
                                <span class="badge bg-danger-lt text-danger ms-1" id="badge_count_unread">0</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Pane: Sudah Membaca -->
                        <div class="tab-pane fade show active" id="pane-read-users" role="tabpanel">
                            <div class="list-group list-group-flush border rounded overflow-hidden" id="list_read_users" style="max-height: 380px; overflow-y: auto;">
                                <!-- Item list will be injected here -->
                            </div>
                            <div id="empty_read_users" class="text-center py-4 text-muted d-none">
                                <i class="ti ti-users fs-1 mb-2 d-block opacity-50"></i>
                                Belum ada GTK yang membaca pengumuman ini.
                            </div>
                        </div>

                        <!-- Pane: Belum Membaca -->
                        <div class="tab-pane fade" id="pane-unread-users" role="tabpanel">
                            <div class="list-group list-group-flush border rounded overflow-hidden" id="list_unread_users" style="max-height: 380px; overflow-y: auto;">
                                <!-- Item list will be injected here -->
                            </div>
                            <div id="empty_unread_users" class="text-center py-4 text-success d-none">
                                <i class="ti ti-circle-check fs-1 mb-2 d-block"></i>
                                Luar biasa! Seluruh sasaran GTK telah membaca pengumuman ini.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 justify-content-between">
                <span class="text-muted small" id="readers_footer_info"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // Toggle Target Type Selection (All, Role, Department)
    // -------------------------------------------------------------
    function updateTargetTypeView(target, type) {
        const rolesWrap = document.getElementById(target + '-roles-wrapper');
        const deptsWrap = document.getElementById(target + '-departments-wrapper');

        if (rolesWrap) rolesWrap.classList.toggle('d-none', type !== 'role');
        if (deptsWrap) deptsWrap.classList.toggle('d-none', type !== 'department');
    }

    document.querySelectorAll('.target-type-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTargetTypeView(this.dataset.target, this.value);
        });
    });

    // -------------------------------------------------------------
    // Salin Teks Pengumuman ke Clipboard
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-copy-announcement').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = this.dataset.text || '';
            if (!text) return;

            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="ti ti-check text-success"></i>';
                this.classList.add('btn-success');
                this.classList.remove('btn-outline-secondary');

                setTimeout(() => {
                    this.innerHTML = originalHtml;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-secondary');
                }, 1500);
            }).catch(err => {
                alert('Gagal menyalin teks: ' + err);
            });
        });
    });

    // -------------------------------------------------------------
    // Lihat Detail Pengumuman (Modal Show)
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-view-announcement').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const url = '{{ url("pengumuman") }}/' + id;

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('view_ann_title').textContent = data.title;
                document.getElementById('view_ann_content').textContent = data.content;
                document.getElementById('view_ann_author').textContent = data.author_name;
                document.getElementById('view_ann_author_role').textContent = data.author_role;
                document.getElementById('view_ann_target_badge').textContent = data.target_label;
                document.getElementById('view_ann_date').innerHTML = '<i class="ti ti-clock me-1"></i> ' + data.published_at;

                const pinBadge = document.getElementById('view_ann_pinned_badge');
                if (pinBadge) {
                    pinBadge.classList.toggle('d-none', !data.is_pinned);
                }

                // WhatsApp Button & Copy
                const waBtn = document.getElementById('btn_modal_wa_ann');
                if (waBtn) waBtn.href = data.whatsapp_share_url;

                const copyBtn = document.getElementById('btn_modal_copy_ann');
                if (copyBtn) {
                    copyBtn.onclick = function () {
                        navigator.clipboard.writeText(data.whatsapp_share_text).then(() => {
                            copyBtn.textContent = 'Tersalin!';
                            setTimeout(() => { copyBtn.innerHTML = '<i class="ti ti-copy me-1"></i> Salin Teks'; }, 1500);
                        });
                    };
                }

                const readersBtn = document.getElementById('btn_modal_view_readers');
                if (readersBtn) {
                    readersBtn.dataset.id = data.id;
                }

                const modal = new bootstrap.Modal(document.getElementById('modal-view-announcement'));
                modal.show();
            });
        });
    });

    // -------------------------------------------------------------
    // Edit Pengumuman (Modal Edit)
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-edit-announcement').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const url = '{{ url("pengumuman") }}/' + id;

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                const form = document.getElementById('form-edit-announcement');
                form.action = url;

                document.getElementById('edit_ann_title').value = data.title;
                document.getElementById('edit_ann_content').value = data.content;
                document.getElementById('edit_ann_pinned').checked = data.is_pinned;
                document.getElementById('edit_ann_active').checked = data.is_active;

                if (data.target_type === 'all') {
                    document.getElementById('edit_target_all').checked = true;
                } else if (data.target_type === 'role') {
                    document.getElementById('edit_target_role').checked = true;
                } else if (data.target_type === 'department') {
                    document.getElementById('edit_target_department').checked = true;
                }
                updateTargetTypeView('edit', data.target_type);

                // Set roles
                const roles = data.target_roles || [];
                document.querySelectorAll('.edit-role-cb').forEach(cb => {
                    cb.checked = roles.includes(cb.value);
                });

                // Set departments
                const depts = (data.target_departments || []).map(d => String(d));
                document.querySelectorAll('.edit-dept-cb').forEach(cb => {
                    cb.checked = depts.includes(String(cb.value));
                });

                const modal = new bootstrap.Modal(document.getElementById('modal-edit-announcement'));
                modal.show();
            });
        });
    });

    // -------------------------------------------------------------
    // Status Pembaca Pengumuman (Modal Readers)
    // -------------------------------------------------------------
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    let currentReadersData = null;
    let activeAnnouncementIdForReaders = null;

    function renderReadersList(readUsers, unreadUsers, query = '') {
        const q = query.toLowerCase().trim();

        // 1. Render Read Users
        const readListEl = document.getElementById('list_read_users');
        const emptyReadEl = document.getElementById('empty_read_users');
        if (readListEl && emptyReadEl) {
            readListEl.innerHTML = '';

            const filteredRead = readUsers.filter(u => {
                if (!q) return true;
                return (u.name && u.name.toLowerCase().includes(q)) ||
                       (u.position_or_role && u.position_or_role.toLowerCase().includes(q));
            });

            if (filteredRead.length === 0) {
                emptyReadEl.classList.remove('d-none');
            } else {
                emptyReadEl.classList.add('d-none');
                filteredRead.forEach(u => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex align-items-center justify-content-between p-3';

                    const avatarHtml = u.avatar_url 
                        ? `<span class="avatar avatar-sm rounded-circle me-3" style="background-image: url('${escapeHtml(u.avatar_url)}')"></span>`
                        : `<span class="avatar avatar-sm rounded-circle bg-success-lt text-success fw-bold me-3">${escapeHtml(u.avatar_initial)}</span>`;

                    const badgeExtra = !u.is_target ? `<span class="badge bg-secondary-lt text-secondary ms-1" style="font-size: 10px;">Di Luar Sasaran</span>` : '';

                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            ${avatarHtml}
                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                    ${escapeHtml(u.name)}
                                    ${badgeExtra}
                                </div>
                                <div class="text-muted small" style="font-size: 11px;">${escapeHtml(u.position_or_role)}</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-lt text-success" title="${escapeHtml(u.read_at)}">
                                <i class="ti ti-check me-1"></i> Dibaca
                            </span>
                            <div class="text-muted small mt-1" style="font-size: 10px;">${escapeHtml(u.read_at_human)}</div>
                        </div>
                    `;
                    readListEl.appendChild(item);
                });
            }
        }

        // 2. Render Unread Users
        const unreadListEl = document.getElementById('list_unread_users');
        const emptyUnreadEl = document.getElementById('empty_unread_users');
        if (unreadListEl && emptyUnreadEl) {
            unreadListEl.innerHTML = '';

            const filteredUnread = unreadUsers.filter(u => {
                if (!q) return true;
                return (u.name && u.name.toLowerCase().includes(q)) ||
                       (u.position_or_role && u.position_or_role.toLowerCase().includes(q));
            });

            if (filteredUnread.length === 0) {
                emptyUnreadEl.classList.remove('d-none');
            } else {
                emptyUnreadEl.classList.add('d-none');
                filteredUnread.forEach(u => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex align-items-center justify-content-between p-3';

                    const avatarHtml = u.avatar_url 
                        ? `<span class="avatar avatar-sm rounded-circle me-3" style="background-image: url('${escapeHtml(u.avatar_url)}')"></span>`
                        : `<span class="avatar avatar-sm rounded-circle bg-danger-lt text-danger fw-bold me-3">${escapeHtml(u.avatar_initial)}</span>`;

                    const tgBadge = u.has_telegram 
                        ? `<span class="badge bg-teal-lt text-teal" style="font-size: 10px;" title="Terhubung ke Bot Telegram"><i class="ti ti-brand-telegram me-1"></i> Telegram Terhubung</span>`
                        : `<span class="badge bg-secondary-lt text-secondary" style="font-size: 10px;">Belum Sambung Telegram</span>`;

                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            ${avatarHtml}
                            <div>
                                <div class="fw-bold text-dark">${escapeHtml(u.name)}</div>
                                <div class="text-muted small" style="font-size: 11px;">${escapeHtml(u.position_or_role)}</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger-lt text-danger mb-1">
                                <i class="ti ti-clock me-1"></i> Belum Baca
                            </span>
                            <div>${tgBadge}</div>
                        </div>
                    `;
                    unreadListEl.appendChild(item);
                });
            }
        }
    }

    document.querySelectorAll('.btn-view-readers').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (!id) return;
            activeAnnouncementIdForReaders = id;

            // Close view modal if opened
            const viewModalEl = document.getElementById('modal-view-announcement');
            if (viewModalEl) {
                const viewModalInstance = bootstrap.Modal.getInstance(viewModalEl);
                if (viewModalInstance) {
                    viewModalInstance.hide();
                }
            }

            const readersModalEl = document.getElementById('modal-readers-announcement');
            if (!readersModalEl) return;
            const readersModal = new bootstrap.Modal(readersModalEl);
            readersModal.show();

            // Set loading state
            document.getElementById('readers-loading-spinner').classList.remove('d-none');
            document.getElementById('readers-content-area').classList.add('d-none');
            const searchInput = document.getElementById('readers-search-input');
            if (searchInput) searchInput.value = '';

            const url = '{{ url("pengumuman") }}/' + id + '/status-pembaca';

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Gagal memuat status pembaca');
                return res.json();
            })
            .then(data => {
                currentReadersData = data;

                document.getElementById('readers_ann_title_preview').textContent = data.announcement.title;
                document.getElementById('readers_stat_percent').textContent = data.summary.read_percentage + '%';
                document.getElementById('readers_stat_target_label').textContent = data.announcement.target_label;
                document.getElementById('readers_stat_progress_bar').style.width = data.summary.read_percentage + '%';
                document.getElementById('readers_stat_target').textContent = data.summary.total_target;
                document.getElementById('readers_stat_read').textContent = data.summary.total_read;
                document.getElementById('readers_stat_unread').textContent = data.summary.unread_count;

                document.getElementById('badge_count_read').textContent = data.read_users.length;
                document.getElementById('badge_count_unread').textContent = data.unread_users.length;

                // Telegram Action Wrap
                const tgWrap = document.getElementById('readers-telegram-action-wrap');
                const unreadWithTg = data.unread_users.filter(u => u.has_telegram).length;
                const tgCountEl = document.getElementById('readers_telegram_count');
                if (tgCountEl) tgCountEl.textContent = unreadWithTg;

                if (tgWrap) {
                    if (unreadWithTg > 0) {
                        tgWrap.classList.remove('d-none');
                    } else {
                        tgWrap.classList.add('d-none');
                    }
                }

                document.getElementById('readers_footer_info').textContent = 'Diterbitkan: ' + data.announcement.published_at;

                renderReadersList(data.read_users, data.unread_users, '');

                document.getElementById('readers-loading-spinner').classList.add('d-none');
                document.getElementById('readers-content-area').classList.remove('d-none');
            })
            .catch(err => {
                document.getElementById('readers-loading-spinner').innerHTML = `
                    <div class="text-danger py-4">
                        <i class="ti ti-alert-triangle fs-1 d-block mb-2"></i>
                        ${escapeHtml(err.message || 'Terjadi kesalahan saat memuat status pembaca.')}
                    </div>
                `;
            });
        });
    });

    // Live search in readers modal
    const searchInput = document.getElementById('readers-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (currentReadersData) {
                renderReadersList(currentReadersData.read_users, currentReadersData.unread_users, this.value);
            }
        });
    }

    // Remind unread button via Telegram
    const btnRemind = document.getElementById('btn_remind_unread_telegram');
    if (btnRemind) {
        btnRemind.addEventListener('click', function () {
            if (!activeAnnouncementIdForReaders) return;

            const count = document.getElementById('readers_telegram_count').textContent;
            if (!confirm(`Kirim notifikasi pengingat via Telegram ke ${count} GTK yang belum membaca pengumuman ini?`)) {
                return;
            }

            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';

            const csrfToken = '{{ csrf_token() }}';
            const url = '{{ url("pengumuman") }}/' + activeAnnouncementIdForReaders + '/ingatkan-belum-baca';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(result => {
                alert(result.message);
            })
            .catch(err => {
                alert('Gagal mengirim pengingat: ' + err.message);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalHtml;
            });
        });
    }
});
</script>
@endpush
@endsection
