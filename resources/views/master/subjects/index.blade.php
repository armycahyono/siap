@extends('layouts.app')

@section('title', 'Data Pokok Mata Pelajaran - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Data Pokok: Mata Pelajaran',
    'subtitle' => 'Kelola daftar kurikulum mata pelajaran umum, kejuruan, dan muatan lokal.',
    'icon' => 'ti ti-books',
    'category' => 'KURIKULUM & MAPEL',
    'stats' => [
        ['label' => 'Total Mapel', 'value' => $subjects->total(), 'class' => 'text-white'],
        ['label' => 'Kategori', 'value' => count($categories), 'class' => 'text-info'],
    ],
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateSubject"><i class="ti ti-plus me-1"></i> Tambah Mapel</button>'
])

@include('master.nav')

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('subjects.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari kode atau nama mapel..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-select">
                    <option value="">-- Semua Jurusan --</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" {{ $deptId == $d->id ? 'selected' : '' }}>{{ $d->code }} - {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-light w-100">Filter</button>
                @if($search || $category || $deptId)
                    <a href="{{ route('subjects.index') }}" class="btn btn-icon btn-outline-secondary" title="Reset Filter"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 110px;">Kode</th>
                    <th>Nama Mata Pelajaran</th>
                    <th>Kategori / Kelompok</th>
                    <th class="text-center" style="width: 75px;">KKM</th>
                    <th>Jurusan Terkait</th>
                    <th class="text-center">Jml Pengampu</th>
                    <th class="text-center">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subjects as $sub)
                <tr>
                    <td>
                        <span class="badge bg-purple text-white fw-bold px-2 py-1">{{ $sub->code }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $sub->name }}</div>
                    </td>
                    <td>
                        @php
                            $catBadge = match($sub->category) {
                                'Kejuruan' => 'bg-cyan-lt text-cyan fw-bold',
                                'Muatan Lokal' => 'bg-orange-lt text-orange fw-bold',
                                'Pilihan' => 'bg-pink-lt text-pink fw-bold',
                                default => 'bg-blue-lt text-blue fw-bold'
                            };
                        @endphp
                        <span class="badge {{ $catBadge }}">{{ $sub->category }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-green-lt text-green fw-bold px-2 py-1">{{ $sub->kkm ?? 75 }}</span>
                    </td>
                    <td>
                        @if($sub->department)
                            <span class="badge bg-secondary-lt fw-bold">{{ $sub->department->code }} - {{ $sub->department->name }}</span>
                        @else
                            <span class="text-muted small fst-italic">Semua Jurusan (Umum)</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-teal-lt fw-bold">{{ $sub->assignments_count }} Penugasan</span>
                    </td>
                    <td class="text-center">
                        @if($sub->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt">Non-Aktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditSub{{ $sub->id }}">
                                <i class="ti ti-edit"></i>
                            </button>
                            <form action="{{ route('subjects.destroy', $sub) }}" method="POST" onsubmit="return confirm('Hapus mata pelajaran {{ $sub->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Modal Edit Mapel -->
                        <div class="modal fade text-start" id="modalEditSub{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('subjects.update', $sub) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Mapel: {{ $sub->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Kode Mapel</label>
                                                <input type="text" name="code" class="form-control" value="{{ old('code', $sub->code) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Nama Mata Pelajaran</label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name', $sub->name) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Kategori</label>
                                                <select name="category" class="form-select" required>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat }}" {{ $sub->category == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Jurusan Terkait (Opsional)</label>
                                                <select name="department_id" class="form-select">
                                                    <option value="">-- Umum / Semua Jurusan --</option>
                                                    @foreach($departments as $d)
                                                        <option value="{{ $d->id }}" {{ $sub->department_id == $d->id ? 'selected' : '' }}>
                                                            {{ $d->code }} - {{ $d->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">KKM (Kriteria Ketuntasan Minimal)</label>
                                                <input type="number" name="kkm" class="form-control" value="{{ old('kkm', $sub->kkm ?? 75) }}" min="0" max="100" required>
                                                <div class="form-text">Standar KKM ini akan disinkronkan ke aplikasi CBT PGRI untuk paket ujian.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nomor Urut Tampil</label>
                                                <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $sub->order_index) }}" min="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $sub->is_active ? 'checked' : '' }}>
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
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 mb-2 d-block"></i>
                        Tidak ada data mata pelajaran yang cocok.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($subjects->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $subjects->links() }}
    </div>
    @endif
</div>

<!-- Modal Tambah Mapel -->
<div class="modal fade" id="modalCreateSubject" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('subjects.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Mata Pelajaran Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Kode Mapel (cth: MTK, PAI, TKR-PMKR)</label>
                        <input type="text" name="code" class="form-control" placeholder="MTK" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Nama Mata Pelajaran</label>
                        <input type="text" name="name" class="form-control" placeholder="Matematika Terapan" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Kategori</label>
                        <select name="category" class="form-select" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jurusan Terkait (Opsional)</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Umum / Semua Jurusan --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} - {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">KKM (Kriteria Ketuntasan Minimal)</label>
                        <input type="number" name="kkm" class="form-control" value="75" min="0" max="100" required>
                        <div class="form-text">Standar KKM ini akan disinkronkan ke aplikasi CBT PGRI untuk paket ujian.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Urut Tampil</label>
                        <input type="number" name="order_index" class="form-control" value="1" min="1">
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
                    <button type="submit" class="btn btn-primary">Tambah Mapel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
