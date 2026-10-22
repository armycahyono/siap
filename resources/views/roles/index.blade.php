@extends('layouts.app')

@section('title', 'Hak Akses & Peran Pengguna')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-shield-lock me-2"></i> Pengaturan Hak Akses & Peran Pengguna
        </h2>
        <div class="text-muted small">
            Konfigurasi matriks izin akses fitur untuk setiap peran (Super Admin, Waka Kurikulum, Supervisor, Guru, Kepala Sekolah).
        </div>
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddRole">
            <i class="ti ti-plus me-1"></i> Tambah Peran Baru
        </button>
    </div>
</div>

<form action="{{ route('roles.update') }}" method="POST">
    @csrf

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h3 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-matrix me-2 text-primary"></i> Matriks Hak Akses Modul Aplikasi
            </h3>
            <button type="submit" class="btn btn-primary fw-bold">
                <i class="ti ti-device-floppy me-1"></i> Simpan Matriks Hak Akses
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover table-bordered">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 30%;">Modul & Izin Fitur</th>
                        @foreach($roles as $role)
                        <th class="text-center" style="width: {{ 70 / $roles->count() }}%;">
                            <div class="fw-bold text-dark">{{ $role->name }}</div>
                            <small class="text-muted font-monospace">{{ $role->slug }}</small>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $groupName => $groupPerms)
                    <tr class="table-light">
                        <td colspan="{{ 1 + $roles->count() }}" class="fw-bold text-primary py-2">
                            <i class="ti ti-folder me-1"></i> {{ strtoupper($groupName) }}
                        </td>
                    </tr>
                    @foreach($groupPerms as $perm)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $perm->name }}</div>
                            <small class="text-muted font-monospace">{{ $perm->slug }}</small>
                        </td>
                        @foreach($roles as $role)
                        <td class="text-center">
                            @if($role->slug === 'super_admin')
                                <input type="checkbox" class="form-check-input" checked disabled title="Super Admin memiliki akses total">
                            @else
                                <input type="checkbox" 
                                    name="permissions[{{ $role->id }}][]" 
                                    value="{{ $perm->id }}" 
                                    class="form-check-input" 
                                    {{ $role->permissions->contains('id', $perm->id) ? 'checked' : '' }}>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <small class="text-muted"><i class="ti ti-info-circle me-1"></i> Centang kotak izin untuk mengaktifkan akses fitur pada peran terkait.</small>
            <button type="submit" class="btn btn-primary fw-bold">
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
