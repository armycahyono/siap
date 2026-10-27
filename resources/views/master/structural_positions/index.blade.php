@extends('layouts.app')

@section('title', 'Data Pokok Jabatan Struktural - Sistem Supervisi')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <h2 class="page-title fw-bold text-dark">Data Pokok: Jabatan Struktural</h2>
        <div class="text-muted small">Kelola master jabatan pimpinan, waka, kaprogli, dan kepala unit sekolah</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreatePosition">
            <i class="ti ti-plus me-1"></i> Tambah Jabatan
        </button>
    </div>
</div>

@include('master.nav')

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('structural-positions.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama jabatan, kode, kategori..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100">Cari</button>
            </div>
            @if($search || $category)
            <div class="col-md-1">
                <a href="{{ route('structural-positions.index') }}" class="btn btn-link text-muted p-0">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 60px;" class="text-center">No</th>
                    <th style="width: 130px;">Kode</th>
                    <th>Nama Jabatan Struktural</th>
                    <th>Kategori</th>
                    <th>Pejabat Saat Ini (TP. {{ $setting->academic_year }})</th>
                    <th style="width: 110px;">Tanda Tangan</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $index => $pos)
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $positions->firstItem() + $index }}</td>
                    <td>
                        <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                        @if($pos->allows_multiple)
                            <span class="badge bg-teal-lt ms-1" title="Kapasitas Jamak: bisa lebih 1 orang">Jamak</span>
                        @else
                            <span class="badge bg-secondary-lt ms-1" title="Kapasitas Tunggal: hanya 1 orang">Tunggal</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                        @if($pos->description)
                            <div class="text-muted small">{{ $pos->description }}</div>
                        @endif
                    </td>
                    <td>
                        @php
                            $catClass = match($pos->category) {
                                'Pimpinan' => 'bg-danger-lt',
                                'Wakil Kepala Sekolah' => 'bg-indigo-lt',
                                'Kepala Program Keahlian' => 'bg-cyan-lt',
                                'Kepala Unit & Fasilitas' => 'bg-teal-lt',
                                'Tata Kelola & Keuangan' => 'bg-purple-lt',
                                default => 'bg-secondary-lt'
                            };
                        @endphp
                        <span class="badge {{ $catClass }}">{{ $pos->category ?? 'Umum' }}</span>
                    </td>
                    <td>
                        @php
                            $currentAssignments = $pos->activeAssignments;
                        @endphp
                        @if($currentAssignments->isNotEmpty())
                            @foreach($currentAssignments as $asgn)
                                <div class="d-flex align-items-center mb-1">
                                    <span class="avatar avatar-xs bg-primary-lt rounded-circle me-2 fw-bold">
                                        {{ strtoupper(substr($asgn->teacher?->name ?? 'G', 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $asgn->teacher?->name ?? 'Guru Tidak Ditemukan' }}</div>
                                        @if($asgn->sk_number)
                                            <small class="text-muted" style="font-size: 0.72rem;">SK: {{ $asgn->sk_number }}</small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <span class="text-muted fst-italic small">Belum Ditugaskan</span>
                            <a href="{{ route('teacher-assignments.index', ['tab' => 'struktural', 'position_id' => $pos->id]) }}" class="badge bg-light text-primary text-decoration-none ms-1">
                                <i class="ti ti-plus me-1"></i>Tugaskan
                            </a>
                        @endif
                    </td>
                    <td>
                        @if($pos->signature_image_path)
                            <a href="{{ route('structural-positions.signature', $pos) }}" target="_blank" title="Lihat Tanda Tangan">
                                <img src="{{ route('structural-positions.signature', $pos) }}" alt="TTD {{ $pos->name }}" style="height: 38px; max-width: 80px; object-fit: contain;" class="border p-1 rounded bg-white">
                            </a>
                        @else
                            <span class="text-muted small fst-italic">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pos->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#modalEditPosition{{ $pos->id }}">
                            <i class="ti ti-edit me-1"></i> Edit
                        </button>
                        
                        <form action="{{ route('structural-positions.destroy', $pos) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jabatan struktural {{ $pos->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="ti ti-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>

                <!-- Modal Edit -->
                <div class="modal fade" id="modalEditPosition{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('structural-positions.update', $pos) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Jabatan Struktural</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Nama Jabatan</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $pos->name) }}" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Kode Singkat</label>
                                            <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code', $pos->code) }}" placeholder="Contoh: KS, WAKA_KUR">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Urutan Tampilan</label>
                                            <input type="number" name="order" class="form-control" value="{{ old('order', $pos->order) }}" min="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" {{ empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Tunggal (hanya 1 orang)</span>
                                                            <span class="text-muted small">Input baru menggantikan pejabat</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" {{ !empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Jamak (bisa lebih 1 orang)</span>
                                                            <span class="text-muted small">Bisa tambah personil baris baru</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori Jabatan</label>
                                        <input type="text" name="category" class="form-control" list="categoryOptions" value="{{ old('category', $pos->category) }}" placeholder="Pilih atau ketik kategori...">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Keterangan / Uraian Tugas</label>
                                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi jabatan...">{{ old('description', $pos->description) }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Scan Tanda Tangan (Upload Tanda Tangan)</label>
                                        <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                        <small class="text-muted">Maksimal 2MB. Disarankan PNG berlatar transparan.</small>
                                        @if($pos->signature_image_path)
                                            <div class="mt-2 p-2 bg-light rounded border d-flex align-items-center justify-content-between">
                                                <img src="{{ route('structural-positions.signature', $pos) }}" alt="TTD" style="height: 35px; max-width: 80px; object-fit: contain;">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="del_sig_pos_{{ $pos->id }}">
                                                    <label class="form-check-label text-danger small" for="del_sig_pos_{{ $pos->id }}">Hapus TTD</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $pos->is_active ? 'checked' : '' }}>
                                            <span class="form-check-label fw-bold">Status Jabatan Aktif</span>
                                        </label>
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
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="ti ti-briefcase-off fs-1 d-block mb-2 text-secondary"></i>
                        Tidak ada data jabatan struktural yang ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($positions->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between">
        <div class="text-muted small">
            Menampilkan {{ $positions->firstItem() }} s/d {{ $positions->lastItem() }} dari {{ $positions->total() }} data
        </div>
        <div>
            {{ $positions->links() }}
        </div>
    </div>
    @endif
</div>

<!-- Modal Tambah Jabatan -->
<div class="modal fade" id="modalCreatePosition" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('structural-positions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Jabatan Struktural</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nama Jabatan</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Koordinator Lab Multimedia" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Kode Singkat</label>
                            <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: KOOR_LAB">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="order" class="form-control" value="{{ \App\Models\StructuralPosition::max('order') + 1 }}" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Tunggal (hanya 1 orang)</span>
                                            <span class="text-muted small">Input baru menggantikan pejabat</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Jamak (bisa lebih 1 orang)</span>
                                            <span class="text-muted small">Bisa tambah personil baris baru</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori Jabatan</label>
                        <input type="text" name="category" class="form-control" list="categoryOptions" placeholder="Pilih atau ketik kategori...">
                        <datalist id="categoryOptions">
                            <option value="Pimpinan">
                            <option value="Wakil Kepala Sekolah">
                            <option value="Kepala Program Keahlian">
                            <option value="Kepala Unit & Fasilitas">
                            <option value="Tata Kelola & Keuangan">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan / Uraian Tugas</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi jabatan..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Scan Tanda Tangan (Upload Tanda Tangan)</label>
                        <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <small class="text-muted">Maksimal 2MB. Disarankan PNG berlatar transparan.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label fw-bold">Status Jabatan Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">Simpan Jabatan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
