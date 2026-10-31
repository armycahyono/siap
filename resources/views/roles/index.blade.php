@extends('layouts.app')

@section('title', 'Hak Akses & Peran Pengguna')

@push('styles')
<style>
    /* Kontainer Matriks Responsif & Pinned Column */
    .table-matrix-wrapper {
        position: relative;
        overflow-x: auto;
        max-width: 100%;
        border-radius: 4px;
    }

    .table-matrix {
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    /* Kolom Pertama (Sticky / Menempel saat scroll horizontal) */
    .table-matrix .sticky-col {
        position: sticky;
        left: 0;
        z-index: 4;
        background-color: #ffffff;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        min-width: 270px;
        max-width: 320px;
        width: 280px;
    }

    .table-matrix thead th.sticky-col {
        z-index: 10;
        background-color: #f8fafc;
        border-right: 2px solid #e2e8f0;
    }

    .table-matrix tbody tr td.sticky-col {
        border-right: 2px solid #e2e8f0;
    }

    .table-matrix tr.group-header-row td.sticky-col {
        background-color: #eef2f6 !important;
        z-index: 5;
    }

    /* Header Kolom Peran Vertikal */
    .table-matrix th.th-role-header {
        height: 185px;
        width: 44px;
        min-width: 42px;
        max-width: 48px;
        vertical-align: bottom !important;
        text-align: center;
        padding: 8px 3px 6px 3px !important;
        background-color: #f8fafc;
        border-bottom: 2px solid #cbd5e1;
        transition: background-color 0.15s ease;
        position: relative;
    }

    .table-matrix th.th-role-header:hover {
        background-color: #e2e8f0 !important;
    }

    .role-vertical-container {
        height: 155px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        overflow: hidden;
    }

    .role-vertical-title {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        text-align: left;
        white-space: nowrap;
        font-size: 0.76rem;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: 0.3px;
        max-height: 150px;
        display: inline-block;
        line-height: 1.2;
    }

    /* Sel Centang Peran */
    .table-matrix td.td-role-check {
        width: 44px;
        min-width: 42px;
        max-width: 48px;
        text-align: center;
        vertical-align: middle;
        padding: 6px 2px !important;
    }

    .table-matrix tbody tr:hover td {
        background-color: #f8fafc;
    }

    .table-matrix tbody tr:hover td.sticky-col {
        background-color: #edf2f7;
    }

    .role-slug-badge {
        font-size: 0.62rem;
        padding: 1px 3px;
        border-radius: 3px;
        max-width: 40px;
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
@endpush

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Pengaturan Hak Akses & Peran Pengguna',
    'subtitle' => 'Konfigurasi matriks izin akses fitur untuk setiap peran (Super Admin, Waka Kurikulum, Supervisor, Guru, Kepala Sekolah).',
    'icon' => 'ti ti-shield-lock',
    'category' => 'KEAMANAN & HAK AKSES',
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddRole"><i class="ti ti-plus me-1"></i> Tambah Peran Baru</button>'
])

<form action="{{ route('roles.update') }}" method="POST">
    @csrf

    @foreach($roles as $role)
        @if($role->slug !== 'super_admin')
            <input type="hidden" name="roles_present[]" value="{{ $role->id }}">
        @endif
    @endforeach

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-matrix me-2 text-primary"></i> Matriks Hak Akses Modul Aplikasi
                </h3>
                <div class="text-muted small mt-1">Header peran dibuat vertikal agar matriks lebih ramping dan kolom nama modul tetap menempel saat digulir.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 240px;">
                    <span class="input-group-text bg-light"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" id="permissionSearchInput" class="form-control" placeholder="Cari izin fitur...">
                </div>
                <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Matriks
                </button>
            </div>
        </div>

        <div class="table-matrix-wrapper">
            <table class="table table-vcenter card-table table-hover table-bordered table-matrix" id="matrixTable">
                <thead>
                    <tr>
                        <th class="sticky-col">
                            <div class="d-flex flex-column justify-content-end h-100 pb-2">
                                <span class="fw-bold text-dark fs-3">Modul & Izin Fitur</span>
                                <small class="text-muted">Total: {{ $roles->count() }} Peran Terdaftar</small>
                            </div>
                        </th>
                        @foreach($roles as $role)
                        <th class="th-role-header" title="{{ $role->name }} ({{ $role->slug }})&#10;{{ $role->description ?? 'Tidak ada keterangan.' }}">
                            <div class="role-vertical-container">
                                <span class="role-vertical-title">{{ $role->name }}</span>
                            </div>
                            <div class="mt-1" title="Kode slug: {{ $role->slug }}">
                                <span class="badge bg-blue-lt role-slug-badge">{{ $role->slug }}</span>
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $groupName => $groupPerms)
                    <tr class="table-light group-header-row" data-group="{{ strtoupper($groupName) }}">
                        <td colspan="{{ 1 + $roles->count() }}" class="fw-bold text-primary py-2 sticky-col">
                            <i class="ti ti-folder me-1"></i> {{ strtoupper($groupName) }}
                            <span class="badge bg-primary-lt ms-2" style="font-size: 0.7rem;">{{ $groupPerms->count() }} izin</span>
                        </td>
                    </tr>
                    @foreach($groupPerms as $perm)
                    <tr class="permission-item-row" data-name="{{ strtolower($perm->name) }}" data-slug="{{ strtolower($perm->slug) }}" data-group="{{ strtolower($groupName) }}">
                        <td class="sticky-col">
                            <div class="fw-semibold text-dark small">{{ $perm->name }}</div>
                            <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ $perm->slug }}</small>
                        </td>
                        @foreach($roles as $role)
                        <td class="td-role-check" title="{{ $perm->name }} &#8594; {{ $role->name }} ({{ $role->slug }})">
                            @if($role->slug === 'super_admin')
                                <input type="checkbox" class="form-check-input" checked disabled title="Super Admin memiliki akses penuh secara otomatis">
                            @else
                                <input type="checkbox" 
                                    name="permissions[{{ $role->id }}][]" 
                                    value="{{ $perm->id }}" 
                                    class="form-check-input" 
                                    {{ $role->permissions->contains('id', $perm->id) ? 'checked' : '' }}
                                    title="{{ $role->name }} ({{ $perm->slug }})">
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="ti ti-info-circle me-1 text-primary"></i> 
                Arahkan kursor (*hover*) ke nama peran atau kotak centang untuk melihat detail lengkap nama izin dan peran.
            </div>
            <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                <i class="ti ti-device-floppy me-1"></i> Simpan Matriks Hak Akses
            </button>
        </div>
    </div>
</form>

<!-- Modal Add Role -->
<div class="modal fade" id="modalAddRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('roles.store-role') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Peran Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nama Peran</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Pengawas Sekolah" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Kode Slug Unik</label>
                        <input type="text" name="slug" class="form-control" placeholder="Contoh: pengawas_sekolah" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Tanggung Jawab</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan singkat peran..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Peran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fitur Live Filter Pencarian Izin Fitur
    const searchInput = document.getElementById('permissionSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.permission-item-row');
            const groupHeaders = document.querySelectorAll('.group-header-row');

            if (!query) {
                rows.forEach(r => r.style.display = '');
                groupHeaders.forEach(g => g.style.display = '');
                return;
            }

            rows.forEach(function (row) {
                const name = row.dataset.name || '';
                const slug = row.dataset.slug || '';
                const group = row.dataset.group || '';

                if (name.includes(query) || slug.includes(query) || group.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Sembunyikan header grup jika tidak ada baris yang cocok di bawahnya
            groupHeaders.forEach(function (header) {
                let next = header.nextElementSibling;
                let hasVisibleChild = false;
                while (next && next.classList.contains('permission-item-row')) {
                    if (next.style.display !== 'none') {
                        hasVisibleChild = true;
                        break;
                    }
                    next = next.nextElementSibling;
                }
                header.style.display = hasVisibleChild ? '' : 'none';
            });
        });
    }
});
</script>
@endpush
