@extends('layouts.app')

@section('title', 'Catatan & Masukan Perbaikan Aplikasi - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Catatan & Masukan Perbaikan Aplikasi',
    'subtitle' => 'Ruang terbuka bagi guru, supervisor, dan staf untuk menyampaikan usulan fitur baru, perbaikan tampilan, atau laporan kendala sistem.',
    'icon' => 'ti ti-bulb',
    'category' => 'MASUKAN & PERBAIKAN SISTEM',
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateFeedback"><i class="ti ti-plus me-1"></i> Tambah Catatan Perbaikan</button>'
])

<!-- Statistik Ringkasan -->
<div class="row row-cards mb-4">
    <div class="col-6 col-md-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar bg-blue-lt text-blue rounded-3 me-3">
                        <i class="ti ti-message-report fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Total Catatan Masuk</div>
                        <div class="h2 mb-0 fw-bold text-dark">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar bg-warning-lt text-warning rounded-3 me-3">
                        <i class="ti ti-clock-pause fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Menunggu Ditinjau</div>
                        <div class="h2 mb-0 fw-bold text-warning">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar bg-azure-lt text-azure rounded-3 me-3">
                        <i class="ti ti-tool fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Sedang Dikerjakan</div>
                        <div class="h2 mb-0 fw-bold text-azure">{{ $stats['in_progress'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar bg-success-lt text-success rounded-3 me-3">
                        <i class="ti ti-circle-check fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Selesai Diterapkan</div>
                        <div class="h2 mb-0 fw-bold text-success">{{ $stats['completed'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tab & Search -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white p-2">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'all' && !$category && !$status ? 'active fw-bold' : '' }}" href="{{ route('feedbacks.index') }}">
                    <i class="ti ti-list me-1"></i> Semua Catatan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'mine' ? 'active fw-bold' : '' }}" href="{{ route('feedbacks.index', ['tab' => 'mine']) }}">
                    <i class="ti ti-user me-1"></i> Catatan Saya
                    @if($stats['mine'] > 0)
                        <span class="badge bg-primary-lt ms-1">{{ $stats['mine'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $category === 'saran_fitur' ? 'active fw-bold' : '' }}" href="{{ route('feedbacks.index', ['category' => 'saran_fitur']) }}">
                    <i class="ti ti-bulb me-1"></i> Usulan Fitur
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $category === 'laporan_bug' ? 'active fw-bold' : '' }}" href="{{ route('feedbacks.index', ['category' => 'laporan_bug']) }}">
                    <i class="ti ti-bug me-1"></i> Kendala / Bug
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'selesai' ? 'active fw-bold' : '' }}" href="{{ route('feedbacks.index', ['status' => 'selesai']) }}">
                    <i class="ti ti-check me-1"></i> Sudah Diterapkan
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-3">
        <form action="{{ route('feedbacks.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
            @if($status) <input type="hidden" name="status" value="{{ $status }}"> @endif

            <div class="col-md-7 col-lg-8">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari judul masukan, penjelasan, atau nama pengirim..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3 col-lg-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Cari
                </button>
            </div>
            @if($search || $category || $status || $tab !== 'all')
            <div class="col-md-2">
                <a href="{{ route('feedbacks.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

<!-- List of Feedback Items -->
<div class="space-y-3">
    @forelse($feedbacks as $item)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $item->category_color }}-lt px-2 py-1 fw-bold">
                        <i class="ti ti-{{ $item->category_icon }} me-1"></i> {{ $item->category_label }}
                    </span>
                    <span class="badge bg-{{ $item->priority_color }}-lt px-2 py-1">
                        {{ $item->priority_label }}
                    </span>
                    <span class="badge bg-{{ $item->status_color }} text-white px-2 py-1">
                        {{ $item->status_label }}
                    </span>
                </div>
                <div class="text-muted small d-flex align-items-center">
                    <i class="ti ti-calendar me-1"></i> {{ $item->created_at->translatedFormat('d M Y, H:i') }} WIB
                </div>
            </div>

            <!-- Title & Description -->
            <h3 class="card-title fw-bold text-dark mb-2 fs-2">
                {{ $item->title }}
            </h3>
            <p class="text-muted mb-3" style="white-space: pre-line; line-height: 1.6;">{{ $item->description }}</p>

            <!-- Attachment (if any) -->
            @if($item->attachment_path)
            <div class="mb-3">
                <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-paperclip me-1"></i> Lampiran: {{ $item->attachment_original_name ?: 'Lihat Berkas / Tangkapan Layar' }}
                </a>
            </div>
            @endif

            <!-- Admin Response Callout (if available) -->
            @if($item->admin_notes)
            <div class="alert alert-success border-0 bg-success-lt p-3 rounded-3 mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="fw-bold text-success">
                        <i class="ti ti-message-check me-1"></i> Tanggapan Pengembang / Admin:
                    </div>
                    @if($item->responded_at)
                    <div class="small text-muted">
                        {{ $item->responder?->name }} &bull; {{ $item->responded_at->translatedFormat('d M Y, H:i') }}
                    </div>
                    @endif
                </div>
                <div class="text-dark" style="white-space: pre-line; line-height: 1.5;">{{ $item->admin_notes }}</div>
            </div>
            @endif

            <!-- Submitter info & Action buttons footer -->
            <div class="d-flex flex-wrap align-items-center justify-content-between pt-3 border-top gap-2">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-primary-lt rounded-circle me-2 fw-bold text-primary">
                        {{ strtoupper(substr($item->user->name, 0, 1)) }}
                    </span>
                    <div>
                        <div class="fw-bold text-dark small">{{ $item->user->name }}</div>
                        <div class="text-muted small" style="font-size: 0.76rem;">{{ $item->user->role?->name ?? 'Pengguna' }}</div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    @if(auth()->user()->hasRole('super_admin') || auth()->user()->isWakaKurikulum())
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalRespond{{ $item->id }}">
                        <i class="ti ti-edit me-1"></i> Tanggapi / Update Status
                    </button>
                    @endif

                    @php
                        $canDelete = ($item->user_id === auth()->id() && $item->status === 'menunggu')
                            || auth()->user()->hasRole('super_admin')
                            || auth()->user()->isWakaKurikulum();
                    @endphp

                    @if($canDelete)
                    <form action="{{ route('feedbacks.destroy', $item) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan perbaikan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Catatan">
                            <i class="ti ti-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Response (Admin/Waka only) -->
    @if(auth()->user()->hasRole('super_admin') || auth()->user()->isWakaKurikulum())
    <div class="modal fade" id="modalRespond{{ $item->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('feedbacks.response', $item) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tanggapan & Pembaruan Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Judul Masukan:</label>
                            <div class="fw-bold text-dark">{{ $item->title }}</div>
                            <div class="small text-muted">Diajukan oleh: {{ $item->user->name }} ({{ $item->user->role?->name }})</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted required">Status Tindak Lanjut:</label>
                            <select name="status" class="form-select" required>
                                <option value="menunggu" {{ $item->status === 'menunggu' ? 'selected' : '' }}>Menunggu Ditinjau</option>
                                <option value="diproses" {{ $item->status === 'diproses' ? 'selected' : '' }}>Sedang Diproses / Dikerjakan</option>
                                <option value="selesai" {{ $item->status === 'selesai' ? 'selected' : '' }}>Selesai Diterapkan</option>
                                <option value="ditolak" {{ $item->status === 'ditolak' ? 'selected' : '' }}>Belum Dapat Diterapkan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Catatan / Respon Pengembang:</label>
                            <textarea name="admin_notes" rows="4" class="form-control" placeholder="Tuliskan catatan tindak lanjut, tanggal pembaruan rilis, atau alasan jika belum dapat diterapkan...">{{ $item->admin_notes }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Tanggapan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @empty
    <div class="card border-0 shadow-sm py-5 text-center text-muted">
        <div class="card-body">
            <i class="ti ti-bulb-off fs-1 d-block mb-3 text-secondary"></i>
            <h3 class="fw-bold text-dark mb-1">Belum Ada Catatan Perbaikan</h3>
            <p class="text-muted mb-3">Jadilah yang pertama menyampaikan saran fitur, masukan tampilan, atau kendala sistem.</p>
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateFeedback">
                <i class="ti ti-plus me-1"></i> Tulis Catatan Baru
            </button>
        </div>
    </div>
    @endforelse
</div>

@if($feedbacks->hasPages())
<div class="d-flex justify-content-end mt-4">
    {{ $feedbacks->links() }}
</div>
@endif

<!-- Modal Tambah Catatan Perbaikan -->
<div class="modal fade" id="modalCreateFeedback" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('feedbacks.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-bulb text-warning me-1"></i> Tambah Catatan & Masukan Perbaikan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-muted required">Kategori Masukan:</label>
                            <select name="category" class="form-select" required>
                                <option value="saran_fitur">💡 Usulan / Saran Fitur Baru</option>
                                <option value="perbaikan_tampilan">🎨 Perbaikan Tampilan / Tata Letak (UI/UX)</option>
                                <option value="laporan_bug">⚠️ Laporan Kendala / Kesalahan Sistem (Bug)</option>
                                <option value="konten_data">📋 Koreksi Data Pokok / Format Laporan</option>
                                <option value="lainnya">💬 Masukan / Catatan Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-muted required">Tingkat Prioritas:</label>
                            <select name="priority" class="form-select" required>
                                <option value="rendah">Rendah (Penyempurnaan)</option>
                                <option value="sedang" selected>Sedang (Dibutuhkan)</option>
                                <option value="tinggi">Tinggi (Mendesak / Mengganggu Operasional)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted required">Judul Catatan / Pokok Masukan:</label>
                            <input type="text" name="title" class="form-control" placeholder="Contoh: Tambahkan tombol cetak langsung pada halaman ringkasan supervisi" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted required">Penjelasan Detail:</label>
                            <textarea name="description" rows="5" class="form-control" placeholder="Jelaskan secara rinci apa yang ingin ditingkatkan, langkah-langkah terjadinya kendala, atau bagian halaman yang memerlukan penyesuaian..." required></textarea>
                            <div class="form-text small text-muted">Semakin detail penjelasan Anda, semakin mudah dan cepat perbaikan dapat diimplementasikan.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Lampiran Gambar / Berkas Pendukung (Opsional):</label>
                            <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx">
                            <div class="form-text small text-muted">Bisa berupa screenshot tampilan, dokumen pendukung, atau berkas acuan (Maksimal 10MB).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-send me-1"></i> Kirim Masukan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
