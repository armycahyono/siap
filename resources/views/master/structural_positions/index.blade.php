@extends('layouts.app')

@section('title', 'Master Penugasan GTK - Data Pokok')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Master Penugasan GTK',
    'subtitle' => 'Kelola definisi tugas Struktural dan Operasional, kapasitas personil, masa jabatan default, dan cakupan penugasan.',
    'icon' => 'ti ti-briefcase',
    'category' => 'DATA POKOK & SDM',
])

@include('master.nav')

<!-- Flash Messages -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
    <div class="d-flex align-items-center">
        <i class="ti ti-check fs-2 me-2"></i>
        <div>{{ session('success') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
    <div class="d-flex align-items-center">
        <i class="ti ti-alert-triangle fs-2 me-2"></i>
        <div>{{ session('error') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
    <div class="d-flex align-items-start">
        <i class="ti ti-alert-circle fs-2 me-2 mt-1"></i>
        <div>
            <div class="fw-bold mb-1">Terjadi kesalahan validasi data:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Metric Summary Cards -->
<div class="row g-2 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar">
                            <i class="ti ti-briefcase fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted">Total Definisi Tugas</div>
                        <div class="h2 mb-0 fw-bold">{{ number_format($totalPositions) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-indigo text-white avatar">
                            <i class="ti ti-building-bank fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted">Tugas Struktural</div>
                        <div class="d-flex align-items-baseline">
                            <div class="h2 mb-0 fw-bold me-2">{{ number_format($totalStructural) }}</div>
                            <span class="badge bg-indigo-lt">Pimpinan & Unit</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-teal text-white avatar">
                            <i class="ti ti-tools fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted">Tugas Operasional</div>
                        <div class="d-flex align-items-baseline">
                            <div class="h2 mb-0 fw-bold me-2">{{ number_format($totalOperational) }}</div>
                            <span class="badge bg-teal-lt">Tim & Layanan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar">
                            <i class="ti ti-circle-check fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted">Status Aktif</div>
                        <div class="d-flex align-items-baseline">
                            <div class="h2 mb-0 fw-bold me-2">{{ number_format($totalActive) }}</div>
                            @if($totalInactive > 0)
                                <span class="badge bg-danger-lt">{{ $totalInactive }} Nonaktif</span>
                            @else
                                <span class="badge bg-success-lt">Semua Aktif</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Action Card -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-filter me-1 text-primary"></i> Filter &amp; Pencarian Master Penugasan
            </h4>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreatePosition">
                <i class="ti ti-plus me-1"></i> Tambah Jenis Tugas
            </button>
        </div>
    </div>
    <div class="card-body bg-light-subtle pb-3">
        <form action="{{ route('structural-positions.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Cari Nama / Kode / Kategori</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Ketik kata kunci pencarian..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Kategori Tingkat Atas</label>
                <select name="type" class="form-select">
                    <option value="all" {{ ($type ?? 'all') === 'all' ? 'selected' : '' }}>Semua Kategori</option>
                    <option value="struktural" {{ ($type ?? '') === 'struktural' ? 'selected' : '' }}>Struktural</option>
                    <option value="operasional" {{ ($type ?? '') === 'operasional' ? 'selected' : '' }}>Operasional</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Cakupan Penugasan</label>
                <select name="scope_type" class="form-select">
                    <option value="all" {{ ($scopeType ?? 'all') === 'all' ? 'selected' : '' }}>Semua Cakupan</option>
                    @foreach(\App\Models\StructuralPosition::SCOPES as $sKey => $sVal)
                        <option value="{{ $sKey }}" {{ ($scopeType ?? '') === $sKey ? 'selected' : '' }}>{{ $sVal }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="all" {{ ($status ?? 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ ($status ?? '') === 'active' ? 'selected' : '' }}>Hanya Aktif</option>
                    <option value="0" {{ ($status ?? '') === '0' ? 'selected' : '' }}>Hanya Nonaktif</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Terapkan Filter">
                    <i class="ti ti-search me-1"></i> Filter
                </button>
                @if($search || ($type && $type !== 'all') || ($status && $status !== 'all') || ($scopeType && $scopeType !== 'all'))
                    <a href="{{ route('structural-positions.index') }}" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="ti ti-rotate-2"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
        <h4 class="card-title fw-bold text-dark mb-0">
            <i class="ti ti-list me-1 text-primary"></i> Daftar Definisi Tugas (Struktural &amp; Operasional)
        </h4>
        <span class="text-muted small">
            Menampilkan {{ $positions->firstItem() ?? 0 }} - {{ $positions->lastItem() ?? 0 }} dari {{ $positions->total() }} jenis tugas
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead class="bg-light">
                <tr class="text-muted small">
                    <th style="width: 50px;" class="text-center">No</th>
                    <th style="width: 140px;">Kode &amp; Kuota</th>
                    <th>Nama Tugas &amp; Uraian</th>
                    <th>Kategori Tingkat Atas</th>
                    <th>Masa Jabatan Default</th>
                    <th>Cakupan</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $index => $pos)
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $positions->firstItem() + $index }}</td>
                    <td>
                        <span class="badge bg-blue-lt fw-bold font-monospace d-inline-block mb-1">{{ $pos->code ?? '-' }}</span>
                        <div>
                            @if($pos->allows_multiple)
                                <span class="badge bg-teal-lt" title="Kapasitas Jamak: bisa ditugaskan ke lebih dari 1 orang">
                                    <i class="ti ti-users me-1"></i> Jamak
                                </span>
                            @else
                                <span class="badge bg-secondary-lt" title="Kapasitas Tunggal: hanya 1 orang pejabat">
                                    <i class="ti ti-user me-1"></i> Tunggal
                                </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                        @if($pos->description)
                            <div class="text-muted small mt-1">{{ Str::limit($pos->description, 90) }}</div>
                        @endif
                        @if($pos->category)
                            <span class="badge bg-light text-muted border mt-1" style="font-size: 0.70rem;">{{ $pos->category }}</span>
                        @endif
                    </td>
                    <td>
                        @if($pos->type === \App\Models\StructuralPosition::TYPE_OPERASIONAL)
                            <span class="badge bg-teal text-white">
                                <i class="ti ti-tools me-1"></i> Operasional
                            </span>
                        @else
                            <span class="badge bg-indigo text-white">
                                <i class="ti ti-building-bank me-1"></i> Struktural
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-cyan-lt fw-bold">
                            <i class="ti ti-calendar-time me-1"></i> {{ $pos->default_tenure_years ?? 1 }} Tahun
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="ti ti-map-pin me-1 text-muted"></i> {{ $pos->scope_label }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($pos->is_active)
                            <span class="badge bg-success-lt fw-bold">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt fw-bold">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <!-- Toggle Status Button -->
                            <form action="{{ route('structural-positions.toggle', $pos) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $pos->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $pos->is_active ? 'Nonaktifkan Tugas' : 'Aktifkan Tugas' }}">
                                    <i class="ti {{ $pos->is_active ? 'ti-power text-warning' : 'ti-check text-success' }}"></i>
                                </button>
                            </form>

                            <!-- Edit Button -->
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditPosition{{ $pos->id }}" title="Edit Definisi Tugas">
                                <i class="ti ti-edit"></i>
                            </button>

                            <!-- Delete Button -->
                            @if($pos->hasHistoricalAssignments())
                                <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Tidak dapat dihapus karena sudah memiliki riwayat penugasan" disabled>
                                    <i class="ti ti-lock"></i>
                                </button>
                            @else
                                <form action="{{ route('structural-positions.destroy', $pos) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jenis tugas {{ $pos->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Permanen">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>

                <!-- Modal Edit -->
                <div class="modal fade" id="modalEditPosition{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <form action="{{ route('structural-positions.update', $pos) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-edit me-1"></i> Edit Jenis Tugas: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <label class="form-label required fw-bold">Nama Tugas / Jabatan</label>
                                            <input type="text" name="name" class="form-control" value="{{ old('name', $pos->name) }}" required>
                                        </div>

                                        <div class="col-md-5">
                                            <label class="form-label required fw-bold">Kode Tugas</label>
                                            <input type="text" name="code" class="form-control text-uppercase font-monospace" value="{{ old('code', $pos->code) }}" {{ $pos->hasHistoricalAssignments() ? 'readonly' : '' }} required>
                                            @if($pos->hasHistoricalAssignments())
                                                <div class="form-text text-warning small">
                                                    <i class="ti ti-lock me-1"></i> Kode dikunci karena sudah memiliki riwayat penugasan personil.
                                                </div>
                                            @else
                                                <div class="form-text small">Huruf kapital, angka, strip, atau underscore.</div>
                                            @endif
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label required fw-bold">Kategori Tingkat Atas</label>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-selectgroup-item w-100">
                                                        <input type="radio" name="type" value="struktural" class="form-selectgroup-input" {{ old('type', $pos->type) === 'struktural' ? 'checked' : '' }} required>
                                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                            <span class="me-2"><i class="ti ti-building-bank text-indigo fs-2"></i></span>
                                                            <span>
                                                                <span class="fw-bold d-block text-dark">Struktural</span>
                                                                <span class="text-muted small">Pimpinan &amp; Unit</span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-selectgroup-item w-100">
                                                        <input type="radio" name="type" value="operasional" class="form-selectgroup-input" {{ old('type', $pos->type) === 'operasional' ? 'checked' : '' }} required>
                                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                            <span class="me-2"><i class="ti ti-tools text-teal fs-2"></i></span>
                                                            <span>
                                                                <span class="fw-bold d-block text-dark">Operasional</span>
                                                                <span class="text-muted small">Tim &amp; Layanan</span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label required fw-bold">Masa Jabatan Default</label>
                                            <select name="default_tenure_years" class="form-select" required>
                                                @foreach(\App\Models\StructuralPosition::TENURE_OPTIONS as $years)
                                                    <option value="{{ $years }}" {{ (int) old('default_tenure_years', $pos->default_tenure_years) === $years ? 'selected' : '' }}>
                                                        {{ $years }} Tahun (Default Periode SK)
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">Digunakan sebagai pengisi otomatis tanggal selesai saat penugasan dibuat.</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label required fw-bold">Kapasitas Petugas</label>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-selectgroup-item w-100">
                                                        <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" {{ empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                            <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                                            <span>
                                                                <span class="fw-bold d-block text-dark">Tunggal</span>
                                                                <span class="text-muted small">1 Orang Pejabat</span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-selectgroup-item w-100">
                                                        <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" {{ !empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                            <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                                            <span>
                                                                <span class="fw-bold d-block text-dark">Jamak</span>
                                                                <span class="text-muted small">&gt; 1 Personil / Tim</span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label required fw-bold">Cakupan Penugasan (Scope)</label>
                                            <select name="scope_type" class="form-select" required>
                                                @foreach(\App\Models\StructuralPosition::SCOPES as $sKey => $sVal)
                                                    <option value="{{ $sKey }}" {{ old('scope_type', $pos->scope_type) === $sKey ? 'selected' : '' }}>
                                                        {{ $sVal }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Kelompok Bidang / Sub-Kategori (Opsional)</label>
                                            <input type="text" name="category" class="form-control" list="categoryOptions" value="{{ old('category', $pos->category) }}" placeholder="Misal: Pimpinan, Kesiswaan, Sarpras...">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Urutan Tampilan</label>
                                            <input type="number" name="order" class="form-control" value="{{ old('order', $pos->order) }}" min="0">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold">Uraian Tugas / Tupoksi (Deskripsi)</label>
                                            <textarea name="description" class="form-control" rows="2" placeholder="Uraian ringkas tugas dan tanggung jawab...">{{ old('description', $pos->description) }}</textarea>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Scan Tanda Tangan Digital (Opsional)</label>
                                            <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                            <div class="form-text small">Format PNG transparan / JPEG maksimal 2MB.</div>
                                            @if($pos->signature_image_path)
                                                <div class="mt-2 d-flex align-items-center gap-2">
                                                    <span class="badge bg-light text-success border">
                                                        <i class="ti ti-check me-1"></i> Tanda tangan tersimpan
                                                    </span>
                                                    <label class="form-check form-check-inline m-0">
                                                        <input class="form-check-input" type="checkbox" name="remove_signature" value="1">
                                                        <span class="form-check-label text-danger small">Hapus tanda tangan</span>
                                                    </label>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-md-4 d-flex align-items-center">
                                            <label class="form-check form-switch mt-3">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $pos->is_active) ? 'checked' : '' }}>
                                                <span class="form-check-label fw-bold">Status Tugas Aktif</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted mb-2">
                            <i class="ti ti-search-off fs-1 text-secondary"></i>
                        </div>
                        <div class="fw-bold text-dark fs-3">Tidak Ada Data Master Tugas</div>
                        <div class="text-muted small">Coba ubah filter pencarian Anda atau tambahkan jenis tugas baru.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($positions->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between py-2 border-top bg-transparent">
        {{ $positions->links() }}
    </div>
    @endif
</div>

<!-- Modal: Tambah Jenis Tugas Baru -->
<div class="modal fade" id="modalCreatePosition" tabindex="-1" aria-labelledby="modalCreatePositionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('structural-positions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalCreatePositionLabel">
                        <i class="ti ti-plus me-1"></i> Tambah Definisi Tugas Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required fw-bold">Nama Tugas / Jabatan</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Koordinator Tim Kedisiplinan" value="{{ old('name') }}" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label required fw-bold">Kode Tugas</label>
                            <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="Contoh: KOORD_DISIPLIN" value="{{ old('code') }}" required>
                            <div class="form-text small">Huruf kapital, angka, strip, atau underscore.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Kategori Tingkat Atas</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="type" value="struktural" class="form-selectgroup-input" {{ old('type', 'struktural') === 'struktural' ? 'checked' : '' }} required>
                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                            <span class="me-2"><i class="ti ti-building-bank text-indigo fs-2"></i></span>
                                            <span>
                                                <span class="fw-bold d-block text-dark">Struktural</span>
                                                <span class="text-muted small">Pimpinan &amp; Unit</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="type" value="operasional" class="form-selectgroup-input" {{ old('type') === 'operasional' ? 'checked' : '' }} required>
                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                            <span class="me-2"><i class="ti ti-tools text-teal fs-2"></i></span>
                                            <span>
                                                <span class="fw-bold d-block text-dark">Operasional</span>
                                                <span class="text-muted small">Tim &amp; Layanan</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Masa Jabatan Default</label>
                            <select name="default_tenure_years" class="form-select" required>
                                @foreach(\App\Models\StructuralPosition::TENURE_OPTIONS as $years)
                                    <option value="{{ $years }}" {{ (int) old('default_tenure_years', 1) === $years ? 'selected' : '' }}>
                                        {{ $years }} Tahun (Default Periode SK)
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">Digunakan sebagai pengisi otomatis tanggal selesai saat penugasan dibuat.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Kapasitas Petugas</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" {{ old('allows_multiple', '0') === '0' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                            <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                            <span>
                                                <span class="fw-bold d-block text-dark">Tunggal</span>
                                                <span class="text-muted small">1 Orang Pejabat</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" {{ old('allows_multiple') === '1' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                            <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                            <span>
                                                <span class="fw-bold d-block text-dark">Jamak</span>
                                                <span class="text-muted small">&gt; 1 Personil / Tim</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Cakupan Penugasan (Scope)</label>
                            <select name="scope_type" class="form-select" required>
                                @foreach(\App\Models\StructuralPosition::SCOPES as $sKey => $sVal)
                                    <option value="{{ $sKey }}" {{ old('scope_type', 'school') === $sKey ? 'selected' : '' }}>
                                        {{ $sVal }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kelompok Bidang / Sub-Kategori (Opsional)</label>
                            <input type="text" name="category" class="form-control" list="categoryOptions" value="{{ old('category') }}" placeholder="Misal: Pimpinan, Kesiswaan, Sarpras...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Urutan Tampilan (Order)</label>
                            <input type="number" name="order" class="form-control" value="{{ old('order') }}" placeholder="Otomatis di akhir jika kosong" min="0">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Uraian Tugas / Tupoksi (Deskripsi)</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Uraian ringkas tugas dan tanggung jawab...">{{ old('description') }}</textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Scan Tanda Tangan Digital (Opsional)</label>
                            <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <div class="form-text small">Format PNG transparan / JPEG maksimal 2MB.</div>
                        </div>

                        <div class="col-md-4 d-flex align-items-center">
                            <label class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <span class="form-check-label fw-bold">Status Tugas Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i> Simpan Definisi Tugas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<datalist id="categoryOptions">
    @foreach($categories as $cat)
        <option value="{{ $cat }}"></option>
    @endforeach
    <option value="Pimpinan"></option>
    <option value="Wakil Kepala Sekolah"></option>
    <option value="Kepala Program Keahlian"></option>
    <option value="Kepala Unit & Fasilitas"></option>
    <option value="Tata Kelola & Keuangan"></option>
    <option value="Kesiswaan & Kedisiplinan"></option>
    <option value="Tenaga Kependidikan & Layanan"></option>
</datalist>
@endsection
