@extends('layouts.app')

@section('title', 'Arsip SK GTK — Data Pokok')

@section('content')
@php
    $heroActions = '';
    if (auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']) || auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasPermission('manage_teachers') || auth()->user()->hasPermission('operate_tu')) {
        $heroActions = '<a href="' . route('gtk-decrees.create') . '" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs"><i class="ti ti-plus me-1"></i> Buat Draf SK Baru</a>';
    }
@endphp

@include('layouts._hero_header', [
    'title' => 'Arsip SK GTK',
    'subtitle' => 'Pencatatan, pengarsipan naskah resmi Surat Keputusan (SK) Guru dan Tenaga Kependidikan, serta perincian tugas sebelum diterapkan.',
    'icon' => 'ti ti-file-certificate',
    'category' => 'DATA POKOK & PENGATURAN',
    'academicYear' => $activeYear->name ?? null,
    'actions' => $heroActions
])

@include('master.nav')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-center">
            <i class="ti ti-circle-check fs-2 me-2"></i>
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

<!-- Ringkasan Stats -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-primary-lt rounded-circle me-3">
                    <i class="ti ti-file-certificate fs-2 text-primary"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Dokumen SK</div>
                    <h3 class="m-0 fw-bold text-dark">{{ $totalAll }} Dokumen</h3>
                    <small class="text-muted">Semua status arsip</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-warning-lt rounded-circle me-3">
                    <i class="ti ti-file-pencil fs-2 text-warning"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Draf Penyusunan</div>
                    <h3 class="m-0 fw-bold text-warning">{{ $totalDraft }} Draf</h3>
                    <small class="text-muted">Belum diaktifkan</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-success-lt rounded-circle me-3">
                    <i class="ti ti-circle-check fs-2 text-success"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">SK Aktif Berjalan</div>
                    <h3 class="m-0 fw-bold text-success">{{ $totalActive }} Dokumen</h3>
                    <small class="text-muted">Penugasan berlaku</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-info-lt rounded-circle me-3">
                    <i class="ti ti-users fs-2 text-info"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">GTK Terdaftar SK</div>
                    <h3 class="m-0 fw-bold text-info">{{ $totalPersonnelInvolved }} Personil</h3>
                    <small class="text-muted">Guru & Staf penerima</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card Filter & Tabel SK -->
<div class="card border-0 shadow-sm">
    <div class="card-body border-bottom p-3">
        <form action="{{ route('gtk-decrees.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nomor SK, judul, pejabat, atau keterangan..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="all">Semua Jenis SK</option>
                    @foreach(\App\Models\GtkDecree::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="all">Semua Status</option>
                    @foreach(\App\Models\GtkDecree::STATUSES as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="academic_year" class="form-select form-select-sm">
                    <option value="all">Semua TP</option>
                    @foreach($academicYears as $ay)
                        <option value="{{ $ay->name }}" {{ $academicYear === $ay->name ? 'selected' : '' }}>{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2 d-flex gap-1 justify-content-end">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                <a href="{{ route('gtk-decrees.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                    <i class="ti ti-rotate-2"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr class="bg-light text-muted small text-uppercase">
                    <th style="width: 50px;">No</th>
                    <th>Nomor SK &amp; Judul</th>
                    <th>Jenis SK</th>
                    <th>Tahun Ajaran</th>
                    <th>Tanggal Formal</th>
                    <th>Penerima</th>
                    <th>Rincian Tugas</th>
                    <th>Berkas PDF</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($decrees as $index => $decree)
                    <tr>
                        <td class="text-muted">{{ $decrees->firstItem() + $index }}</td>
                        <td>
                            <div class="fw-bold text-dark">
                                <a href="{{ route('gtk-decrees.show', $decree) }}" class="text-reset text-decoration-none hover-primary">
                                    {{ $decree->number }}
                                </a>
                            </div>
                            <div class="text-secondary small">{{ $decree->title }}</div>
                            @if($decree->signer_name)
                                <div class="text-muted text-xs mt-1">
                                    <i class="ti ti-signature me-1"></i> {{ $decree->signer_name }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt text-uppercase font-weight-medium" style="font-size: 0.72rem;">
                                {{ \App\Models\GtkDecree::TYPES[$decree->type] ?? $decree->type }}
                            </span>
                        </td>
                        <td>
                            <span class="fw-semibold text-secondary">
                                {{ $decree->academicYear?->name ?? $decree->academic_year ?? '-' }}
                            </span>
                        </td>
                        <td>
                            <div class="text-xs text-muted">Penetapan: <span class="fw-bold text-dark">{{ $decree->issued_date ? $decree->issued_date->format('d/m/Y') : '-' }}</span></div>
                            <div class="text-xs text-muted">TMT: <span class="fw-bold text-dark">{{ $decree->tmt_date ? $decree->tmt_date->format('d/m/Y') : '-' }}</span></div>
                            <div class="text-xs text-muted">
                                Berlaku: {{ $decree->valid_from ? $decree->valid_from->format('d/m/Y') : '-' }}
                                @if($decree->valid_until)
                                    s.d {{ $decree->valid_until->format('d/m/Y') }}
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt fw-bold">
                                <i class="ti ti-users me-1"></i> {{ $decree->recipients_count }} GTK
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-azure-lt fw-bold">
                                <i class="ti ti-list-check me-1"></i> {{ $decree->details_count }} Butir
                            </span>
                        </td>
                        <td>
                            @if($decree->file_path && $decree->hasPrivateDocument())
                                <a href="{{ route('gtk-decrees.document', ['gtkDecree' => $decree, 'preview' => 1]) }}" target="_blank" class="badge bg-danger-lt text-danger text-decoration-none hover-shadow" title="Buka Pratinjau PDF">
                                    <i class="ti ti-file-text me-1"></i> PDF ({{ round($decree->file_size / 1024, 1) }} KB)
                                </a>
                            @else
                                <span class="text-muted text-xs fst-italic">Belum ada file</span>
                            @endif
                        </td>
                        <td>
                            @if($decree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                                <span class="badge bg-warning text-dark fw-bold">
                                    <i class="ti ti-pencil me-1"></i> Draf
                                </span>
                            @elseif($decree->status === \App\Models\GtkDecree::STATUS_ACTIVE)
                                <span class="badge bg-success text-white fw-bold">
                                    <i class="ti ti-check me-1"></i> Aktif
                                </span>
                            @elseif($decree->status === \App\Models\GtkDecree::STATUS_EXPIRED)
                                <span class="badge bg-secondary text-white">
                                    <i class="ti ti-clock-off me-1"></i> Berakhir
                                </span>
                            @elseif($decree->status === \App\Models\GtkDecree::STATUS_CANCELLED)
                                <span class="badge bg-danger text-white">
                                    <i class="ti ti-ban me-1"></i> Dibatalkan
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('gtk-decrees.show', $decree) }}" class="btn btn-outline-primary" title="Lihat Detail & Kelola Rincian">
                                    <i class="ti ti-eye"></i>
                                </a>
                                @if($decree->status === \App\Models\GtkDecree::STATUS_DRAFT && (auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']) || auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasPermission('manage_teachers') || auth()->user()->hasPermission('operate_tu')))
                                    <a href="{{ route('gtk-decrees.edit', $decree) }}" class="btn btn-outline-secondary" title="Edit Metadata Draf">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <form action="{{ route('gtk-decrees.destroy', $decree) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus draf SK No. {{ $decree->number }}? Seluruh draf penerima dan butir penugasan terkait akan terhapus.');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus Draf">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="ti ti-file-off fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-bold">Tidak ada dokumen SK GTK yang ditemukan.</div>
                            <small>Silakan sesuaikan kriteria pencarian atau buat draf SK baru.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($decrees->hasPages())
        <div class="card-footer d-flex align-items-center justify-content-between p-3 border-top">
            <div class="text-muted small">
                Menampilkan {{ $decrees->firstItem() }} s.d {{ $decrees->lastItem() }} dari {{ $decrees->total() }} dokumen
            </div>
            <div>
                {{ $decrees->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
