@extends('layouts.app')

@section('title', 'Tahun Pelajaran — Data Pokok')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-calendar-stats me-2"></i> Tahun Pelajaran
        </h2>
        <div class="text-muted small">
            Kelola tahun pelajaran, rentang waktu operasional, status aktif sekolah, dan penguncian arsip data.
        </div>
    </div>
    @if(auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasRole(['super_admin', 'waka_kurikulum']))
    <div class="col-auto">
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahTahun">
            <i class="ti ti-plus me-1"></i> Tambah Tahun Pelajaran
        </button>
    </div>
    @endif
</div>

@include('master.nav')

<!-- Ringkasan Stats -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-primary-lt rounded-circle me-3">
                    <i class="ti ti-calendar-check fs-2 text-primary"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Tahun Pelajaran Aktif</div>
                    <h3 class="m-0 fw-bold text-dark">
                        {{ $activeYear->name ?? $activeYear->academic_year ?? '-' }}
                    </h3>
                    <small class="text-success fw-bold">
                        <i class="ti ti-check me-1"></i> Tahun Berjalan
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-info-lt rounded-circle me-3">
                    <i class="ti ti-folders fs-2 text-info"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Terdaftar</div>
                    <h3 class="m-0 fw-bold text-dark">{{ $academicYears->count() }} Periode</h3>
                    <small class="text-muted">Master & Perencanaan</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-lg bg-warning-lt rounded-circle me-3">
                    <i class="ti ti-lock fs-2 text-warning"></i>
                </span>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Arsip Terkunci</div>
                    <h3 class="m-0 fw-bold text-dark">{{ $academicYears->where('is_locked', true)->count() }} Periode</h3>
                    <small class="text-muted">Data Masa Lalu Diamankan</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Daftar Tahun Pelajaran -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tahun Pelajaran</th>
                    <th>Rentang Tanggal</th>
                    <th>Status Sekolah</th>
                    <th>Status Arsip</th>
                    <th>Keterangan</th>
                    <th class="text-end" style="min-width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($academicYears as $index => $year)
                <tr class="{{ $year->is_active ? 'table-primary bg-primary-lt' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <div class="fw-bold fs-3 text-dark">
                             TP. {{ $year->name }}
                        </div>
                    </td>
                    <td>
                        @if($year->start_date && $year->end_date)
                            <div class="small fw-semibold text-dark">
                                {{ $year->start_date->translatedFormat('d M Y') }} &mdash; {{ $year->end_date->translatedFormat('d M Y') }}
                            </div>
                        @else
                            <span class="text-muted small">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        @if($year->is_active)
                            <span class="badge bg-success text-white fw-bold shadow-sm px-2 py-1">
                                <i class="ti ti-circle-check me-1"></i> AKTIF BERJALAN
                            </span>
                        @else
                            <span class="badge bg-secondary-lt">Non-Aktif</span>
                        @endif
                    </td>
                    <td>
                        @if($year->is_locked)
                            <span class="badge bg-warning text-dark fw-bold">
                                <i class="ti ti-lock me-1"></i> Terkunci (Arsip)
                            </span>
                        @else
                            <span class="badge bg-success-lt">
                                <i class="ti ti-lock-open me-1"></i> Terbuka
                            </span>
                        @endif
                    </td>
                    <td>
                        <small class="text-muted">{{ $year->description ?: '-' }}</small>
                    </td>
                    <td class="text-end">
                        <div class="btn-group">
                            @if(!$year->is_active && (auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasRole(['super_admin', 'waka_kurikulum'])))
                            <form action="{{ route('academic-years.set-active', $year) }}" method="POST" class="d-inline" onsubmit="return confirm('Jadikan TP. {{ $year->name }} sebagai tahun ajaran aktif utama sekolah?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Set Sebagai Tahun Ajaran Aktif">
                                    <i class="ti ti-check me-1"></i> Aktifkan
                                </button>
                            </form>
                            @endif

                            @if(auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasRole(['super_admin', 'waka_kurikulum']))
                            <form action="{{ route('academic-years.toggle-lock', $year) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $year->is_locked ? 'btn-outline-warning' : 'btn-outline-secondary' }}" title="{{ $year->is_locked ? 'Buka Kunci Arsip' : 'Kunci Menjadi Arsip' }}">
                                    <i class="ti {{ $year->is_locked ? 'ti-lock-open' : 'ti-lock' }}"></i>
                                </button>
                            </form>

                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditTahun{{ $year->id }}" title="Edit Data">
                                <i class="ti ti-pencil"></i>
                            </button>

                            @if(!$year->is_active)
                            <form action="{{ route('academic-years.destroy', $year) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Tahun Pelajaran {{ $year->name }}? Tindakan ini tidak dapat dibatalkan.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>

                <!-- Modal Edit Tahun Pelajaran -->
                <div class="modal modal-blur fade" id="modalEditTahun{{ $year->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form action="{{ route('academic-years.update', $year) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Tahun Pelajaran {{ $year->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Format Tahun Pelajaran</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $year->name) }}" placeholder="Contoh: 2026/2027" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label fw-bold">Tanggal Mulai</label>
                                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $year->start_date?->format('Y-m-d')) }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-bold">Tanggal Selesai</label>
                                            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $year->end_date?->format('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan</label>
                                        <textarea name="description" class="form-control" rows="2" placeholder="Catatan operasional...">{{ old('description', $year->description) }}</textarea>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_locked" value="1" id="switchLock{{ $year->id }}" {{ old('is_locked', $year->is_locked) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="switchLock{{ $year->id }}">Kunci sebagai Arsip (Data Read-Only)</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-calendar-off fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada data tahun pelajaran yang tercatat.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Tahun Pelajaran -->
<div class="modal modal-blur fade" id="modalTambahTahun" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('academic-years.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Tahun Pelajaran Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Format Tahun Pelajaran</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: 2027/2028" required>
                        <div class="form-text small">Gunakan format 4 digit tahun / 4 digit tahun (contoh: 2027/2028).</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="switchNewActive">
                        <label class="form-check-label fw-semibold" for="switchNewActive">Langsung tetapkan sebagai Tahun Pelajaran Aktif Sekolah</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_locked" value="1" id="switchNewLock">
                        <label class="form-check-label fw-semibold" for="switchNewLock">Kunci data sebagai arsip (Read-Only)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">Simpan Tahun Pelajaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
