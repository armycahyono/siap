@extends('layouts.app')

@section('title', 'Kustomisasi Indikator & Rubrik')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $rubricActions = '<button type="button" class="btn btn-sm btn-outline-light shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddPhase"><i class="ti ti-folder-plus me-1 text-info"></i> Tambah Tahapan/Fase</button> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddIndicator"><i class="ti ti-plus me-1"></i> Tambah Butir Indikator</button>';
@endphp
@include('layouts._hero_header', [
    'title' => 'Kustomisasi Indikator & Rubrik Supervisi',
    'subtitle' => 'Atur tahapan dan butir-butir indikator penilaian pembelajaran sesuai perkembangan kurikulum sekolah Anda.',
    'icon' => 'ti ti-list-check',
    'category' => 'SUPERVISI & PENILAIAN',
    'actions' => $rubricActions
])

<!-- Rubric Overview Cards -->
<div class="row row-cards mb-4">
    <div class="col-sm-4">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Tahapan / Fase KBM</div>
                <div class="h2 mb-0 fw-bold text-dark">{{ $phases->count() }} Fase</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Butir Indikator Aktif</div>
                <div class="h2 mb-0 fw-bold text-primary">{{ $activeIndicators }} Butir</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Skor Maksimal Ideal Akumulatif</div>
                <div class="h2 mb-0 fw-bold text-success">{{ $totalMaxScore }} Poin</div>
            </div>
        </div>
    </div>
<!-- Ambang Batas Predikat Penilaian Supervisi Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                <span class="avatar avatar-sm bg-teal-lt text-teal rounded-circle me-2">
                    <i class="ti ti-calculator fs-3"></i>
                </span>
                Ambang Batas Predikat Supervisi
            </h4>
            <div class="text-muted small mt-1">Standar persentase pencapaian skor penilaian untuk menentukan predikat guru dalam laporan supervisi.</div>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-outline-teal" data-bs-toggle="collapse" data-bs-target="#collapseAmbangPredikat" aria-expanded="false">
                <i class="ti ti-adjustments me-1"></i> Atur Ambang Batas
            </button>
        </div>
    </div>
    <div class="card-body p-3">
        <!-- Visual Badges Row -->
        <div class="row g-3 text-center mb-2">
            <div class="col-sm-3 col-6">
                <div class="p-3 rounded-3 bg-success-lt border border-success border-opacity-25 h-100">
                    <span class="badge bg-success text-white mb-2 fs-4 px-3 py-1">Amat Baik (A)</span>
                    <div class="h2 mb-0 fw-bold text-success">&ge; {{ $setting->grade_a_min }}%</div>
                    <small class="text-muted">Skor {{ $setting->grade_a_min }}% s/d 100%</small>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-3 rounded-3 bg-blue-lt border border-blue border-opacity-25 h-100">
                    <span class="badge bg-blue text-white mb-2 fs-4 px-3 py-1">Baik (B)</span>
                    <div class="h2 mb-0 fw-bold text-primary">&ge; {{ $setting->grade_b_min }}%</div>
                    <small class="text-muted">Skor {{ $setting->grade_b_min }}% s/d {{ $setting->grade_a_min - 1 }}%</small>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-3 rounded-3 bg-warning-lt border border-warning border-opacity-25 h-100">
                    <span class="badge bg-warning text-dark mb-2 fs-4 px-3 py-1">Cukup (C)</span>
                    <div class="h2 mb-0 fw-bold text-warning">&ge; {{ $setting->grade_c_min }}%</div>
                    <small class="text-muted">Skor {{ $setting->grade_c_min }}% s/d {{ $setting->grade_b_min - 1 }}%</small>
                </div>
            </div>
            <div class="col-sm-3 col-6">
                <div class="p-3 rounded-3 bg-danger-lt border border-danger border-opacity-25 h-100">
                    <span class="badge bg-danger text-white mb-2 fs-4 px-3 py-1">Perlu Bimbingan (D)</span>
                    <div class="h2 mb-0 fw-bold text-danger">&lt; {{ $setting->grade_c_min }}%</div>
                    <small class="text-muted">Skor di bawah {{ $setting->grade_c_min }}%</small>
                </div>
            </div>
        </div>

        <!-- Form Collapse to Edit -->
        <div class="collapse mt-3" id="collapseAmbangPredikat">
            <div class="p-3 bg-light rounded-3 border">
                <form action="{{ route('rubrics.update-predicates') }}" method="POST">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label required fw-bold small">Batas Bawah Nilai A (%)</label>
                            <input type="number" name="grade_a_min" class="form-control" value="{{ old('grade_a_min', $setting->grade_a_min) }}" min="1" max="100" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label required fw-bold small">Batas Bawah Nilai B (%)</label>
                            <input type="number" name="grade_b_min" class="form-control" value="{{ old('grade_b_min', $setting->grade_b_min) }}" min="1" max="100" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label required fw-bold small">Batas Bawah Nilai C (%)</label>
                            <input type="number" name="grade_c_min" class="form-control" value="{{ old('grade_c_min', $setting->grade_c_min) }}" min="1" max="100" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <button type="submit" class="btn btn-teal w-100 fw-bold">
                                <i class="ti ti-device-floppy me-1"></i> Simpan Ambang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Phases & Indicators List -->
@foreach($phases as $phase)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="badge bg-primary text-white me-2 fw-bold">{{ $phase->code ?: $phase->order }}</span>
            <strong class="h3 mb-0 text-dark">{{ $phase->name }}</strong>
            <span class="badge bg-secondary-lt ms-2">{{ $phase->indicators->count() }} Butir</span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditPhase{{ $phase->id }}" title="Edit Fase">
                <i class="ti ti-pencil"></i> Edit
            </button>
            <form action="{{ route('rubrics.delete-phase', $phase) }}" method="POST" onsubmit="return confirm('Hapus fase ini dan semua indikator di dalamnya?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Fase">
                    <i class="ti ti-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 5%;">Kode</th>
                    <th>Pernyataan Indikator Pembelajaran</th>
                    <th style="width: 12%;" class="text-center">Skor Maksimal</th>
                    <th style="width: 10%;" class="text-center">Status</th>
                    <th style="width: 10%;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($phase->indicators as $ind)
                <tr>
                    <td class="font-monospace fw-bold text-muted">{{ $ind->code ?: '-' }}</td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $ind->title }}</div>
                        @if($ind->description)
                            <div class="text-muted small">{{ $ind->description }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-blue-lt fw-bold">Skala 1 - {{ $ind->max_score }}</span>
                    </td>
                    <td class="text-center">
                        @if($ind->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-secondary-lt">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditIndicator{{ $ind->id }}" title="Edit Indikator">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <form action="{{ route('rubrics.delete-indicator', $ind) }}" method="POST" onsubmit="return confirm('Hapus butir indikator ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Indikator">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                <!-- Modal Edit Indicator -->
                <div class="modal fade" id="modalEditIndicator{{ $ind->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('rubrics.update-indicator', $ind) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Butir Indikator</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Fase / Tahapan</label>
                                        <select name="phase_id" class="form-select" required>
                                            @foreach($phases as $p)
                                                <option value="{{ $p->id }}" {{ $ind->phase_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Kode Indikator</label>
                                            <input type="text" name="code" class="form-control" value="{{ $ind->code }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label required">Skor Maksimal</label>
                                            <input type="number" name="max_score" class="form-control" value="{{ $ind->max_score }}" min="1" max="10" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required">Pernyataan Indikator</label>
                                        <textarea name="title" class="form-control" rows="2" required>{{ $ind->title }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi / Panduan Rubrik (Opsional)</label>
                                        <textarea name="description" class="form-control" rows="2">{{ $ind->description }}</textarea>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label required">Nomor Urut</label>
                                            <input type="number" name="order" class="form-control" value="{{ $ind->order }}" required>
                                        </div>
                                        <div class="col-6 d-flex align-items-center">
                                            <label class="form-check mt-3">
                                                <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $ind->is_active ? 'checked' : '' }}>
                                                <span class="form-check-label fw-bold">Indikator Aktif</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">Belum ada indikator pada tahapan ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit Phase -->
<div class="modal fade" id="modalEditPhase{{ $phase->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('rubrics.update-phase', $phase) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Tahapan / Fase Supervisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">Kode (A/B/C)</label>
                            <input type="text" name="code" class="form-control" value="{{ $phase->code }}">
                        </div>
                        <div class="col-8">
                            <label class="form-label required">Nama Tahapan</label>
                            <input type="text" name="name" class="form-control" value="{{ $phase->name }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2">{{ $phase->description }}</textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required">Urutan Tampil</label>
                            <input type="number" name="order" class="form-control" value="{{ $phase->order }}" required>
                        </div>
                        <div class="col-6 d-flex align-items-center">
                            <label class="form-check mt-3">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $phase->is_active ? 'checked' : '' }}>
                                <span class="form-check-label fw-bold">Fase Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Modal Add Phase -->
<div class="modal fade" id="modalAddPhase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('rubrics.store-phase') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Tahapan/Fase Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">Kode Fase</label>
                            <input type="text" name="code" class="form-control" placeholder="D">
                        </div>
                        <div class="col-8">
                            <label class="form-label required">Nama Tahapan</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Asesmen Pasca KBM" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan singkat..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Fase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Indicator -->
<div class="modal fade" id="modalAddIndicator" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('rubrics.store-indicator') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Butir Indikator Penilaian Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Pilih Tahapan/Fase</label>
                        <select name="phase_id" class="form-select" required>
                            @foreach($phases as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Kode (Opsional)</label>
                            <input type="text" name="code" class="form-control" placeholder="B.14">
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Skor Maksimal</label>
                            <input type="number" name="max_score" class="form-control" value="4" min="1" max="10" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Pernyataan Indikator</label>
                        <textarea name="title" class="form-control" rows="2" placeholder="Tuliskan indikator perilaku/proses KBM yang diamati..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Rubrik / Rubrik Tambahan (Opsional)</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Panduan kriteria skor 1-4..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Indikator</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
