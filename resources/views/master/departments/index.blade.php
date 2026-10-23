@extends('layouts.app')

@section('title', 'Data Pokok Jurusan - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Data Pokok: Jurusan / Program Keahlian',
    'subtitle' => 'Kelola data program keahlian dan kompetensi kejuruan di SMK PGRI KRAS.',
    'icon' => 'ti ti-school',
    'category' => 'DATA POKOK & STRUKTUR',
    'stats' => [
        ['label' => 'Total Jurusan', 'value' => $departments->total(), 'class' => 'text-white'],
    ],
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateDept"><i class="ti ti-plus me-1"></i> Tambah Jurusan</button>'
])

@include('master.nav')

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('departments.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari kode atau nama jurusan..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100">Cari</button>
            </div>
            @if($search)
            <div class="col-md-2">
                <a href="{{ route('departments.index') }}" class="btn btn-link text-muted">Reset</a>
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
                    <th style="width: 120px;">Kode</th>
                    <th>Nama Program Keahlian</th>
                    <th>Keterangan</th>
                    <th class="text-center" style="width: 120px;">Status</th>
                    <th class="w-1 text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                <tr>
                    <td>
                        <span class="badge bg-blue text-white fw-bold px-2 py-1 fs-4">{{ $dept->code }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $dept->name }}</div>
                    </td>
                    <td>
                        <div class="text-muted small">
                            {{ $dept->description ?: '-' }}
                        </div>
                    </td>
                    <td class="text-center">
                        @if($dept->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt">Non-Aktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditDept{{ $dept->id }}">
                                <i class="ti ti-edit"></i>
                            </button>
                            <form action="{{ route('departments.destroy', $dept) }}" method="POST" onsubmit="return confirm('Hapus jurusan {{ $dept->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Modal Edit -->
                        <div class="modal fade text-start" id="modalEditDept{{ $dept->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('departments.update', $dept) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Jurusan: {{ $dept->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Kode Jurusan</label>
                                                <input type="text" name="code" class="form-control" value="{{ old('code', $dept->code) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Nama Jurusan / Program Keahlian</label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name', $dept->name) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Deskripsi / Keterangan</label>
                                                <textarea name="description" class="form-control" rows="3">{{ old('description', $dept->description) }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $dept->is_active ? 'checked' : '' }}>
                                                    <span class="form-check-label">Status Aktif</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 mb-2 d-block"></i>
                        Belum ada data jurusan yang terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($departments->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $departments->links() }}
    </div>
    @endif
</div>

<!-- Modal Tambah Jurusan -->
<div class="modal fade" id="modalCreateDept" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('departments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Jurusan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Kode Singkat (cth: TKR, TKJ, AKL)</label>
                        <input type="text" name="code" class="form-control" placeholder="TKR" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Nama Lengkap Jurusan</label>
                        <input type="text" name="name" class="form-control" placeholder="Teknik Kendaraan Ringan Otomotif" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi / Keterangan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Profil kompetensi keahlian..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label">Status Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Jurusan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
