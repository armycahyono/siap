@extends('layouts.app')

@section('title', 'Manajemen Pengguna - Super Admin')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Manajemen Akun Pengguna',
    'subtitle' => 'Kelola seluruh akun pengguna sistem (Super Admin, Pimpinan, Guru, dan Staf/Tendik), perbarui peran, reset password, dan pantau hak akses.',
    'icon' => 'ti ti-users-group',
    'category' => 'SUPER ADMIN & KONTROL HAK AKSES',
    'actions' => '
        <button type="button" class="btn btn-primary d-inline-flex align-items-center shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
            <i class="ti ti-user-plus me-1.5 fs-2"></i>
            <span>Tambah Pengguna Baru</span>
        </button>
    '
])

<!-- Stat Cards -->
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar shadow-sm">
                            <i class="ti ti-users fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Total Pengguna</div>
                        <div class="fw-bold fs-2 text-dark">{{ $totalUsers }}</div>
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
                        <span class="bg-danger text-white avatar shadow-sm">
                            <i class="ti ti-shield-lock fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Super Admin</div>
                        <div class="fw-bold fs-2 text-danger d-flex align-items-center">
                            {{ $superAdminCount }}
                            @if($superAdminCount > 1)
                                <span class="badge bg-success-lt ms-2" style="font-size: 0.65rem;">Multi-Admin</span>
                            @else
                                <span class="badge bg-warning-lt ms-2" style="font-size: 0.65rem;">Admin Tunggal</span>
                            @endif
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
                        <span class="bg-teal text-white avatar shadow-sm">
                            <i class="ti ti-user-check fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Akun Aktif</div>
                        <div class="fw-bold fs-2 text-teal">{{ $activeCount }}</div>
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
                        <span class="bg-secondary text-white avatar shadow-sm">
                            <i class="ti ti-user-off fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Akun Nonaktif</div>
                        <div class="fw-bold fs-2 text-muted">{{ $inactiveCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Type Filter Navigation & Search Form -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom p-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <ul class="nav nav-pills gap-1">
            <li class="nav-item">
                <a class="nav-link {{ empty($typeFilter) ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" 
                   href="{{ route('users.index', array_merge(request()->except('type', 'page'))) }}">
                    <i class="ti ti-users me-1"></i> Semua Pengguna
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $typeFilter === 'admin' ? 'active bg-danger text-white fw-bold shadow-sm' : 'text-dark' }}" 
                   href="{{ route('users.index', array_merge(request()->except('page'), ['type' => 'admin'])) }}">
                    <i class="ti ti-shield me-1"></i> Super Admin ({{ $superAdminCount }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $typeFilter === 'guru' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" 
                   href="{{ route('users.index', array_merge(request()->except('page'), ['type' => 'guru'])) }}">
                    <i class="ti ti-school me-1"></i> Dewan Guru & Pimpinan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $typeFilter === 'staf' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" 
                   href="{{ route('users.index', array_merge(request()->except('page'), ['type' => 'staf'])) }}">
                    <i class="ti ti-briefcase me-1"></i> Staf & Tendik
                </a>
            </li>
        </ul>
        <div class="text-muted small px-2">
            Menampilkan <strong class="text-dark">{{ $users->total() }}</strong> akun
        </div>
    </div>
    <div class="card-body p-3">
        <form action="{{ route('users.index') }}" method="GET" class="row g-2 align-items-center">
            @if($typeFilter)
                <input type="hidden" name="type" value="{{ $typeFilter }}">
            @endif
            <div class="col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, username, email, NIP, jabatan..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role_id" class="form-select">
                    <option value="">-- Semua Peran (Role) --</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->id }}" {{ (string)$roleId === (string)$r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">-- Status Akun --</option>
                    <option value="1" {{ $statusFilter === '1' ? 'selected' : '' }}>Akun Aktif</option>
                    <option value="0" {{ $statusFilter === '0' ? 'selected' : '' }}>Akun Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $roleId || ($statusFilter !== null && $statusFilter !== '') || $typeFilter)
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" title="Reset Filter">
                    <i class="ti ti-rotate-2"></i>
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Flash Alerts -->
@if(session('success'))
<div class="alert alert-success alert-dismissible shadow-sm mb-3" role="alert">
    <div class="d-flex align-items-center">
        <i class="ti ti-circle-check fs-2 me-2"></i>
        <div>{{ session('success') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible shadow-sm mb-3" role="alert">
    <div class="d-flex align-items-center">
        <i class="ti ti-alert-triangle fs-2 me-2"></i>
        <div>{{ session('error') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible shadow-sm mb-3" role="alert">
    <div class="fw-bold mb-1"><i class="ti ti-alert-circle me-1"></i> Terdapat kesalahan pada input:</div>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Users Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover table-striped">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 40px;" class="text-center">No</th>
                    <th>Pengguna</th>
                    <th>Peran (Role)</th>
                    <th>Jabatan &amp; NIP</th>
                    <th>Kontak</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $index => $u)
                @php
                    $isOnline = isset($onlineUserIds[$u->id]);
                    $isCurrent = $u->id === auth()->id();
                @endphp
                <tr>
                    <td class="text-center text-muted small">
                        {{ $users->firstItem() + $index }}
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-md rounded-circle me-3 {{ $u->isSuperAdmin() ? 'bg-danger-lt text-danger fw-bold border border-danger-subtle' : 'bg-primary-lt text-primary fw-bold' }}">
                                {{ strtoupper(substr($u->name, 0, 2)) }}
                            </span>
                            <div>
                                <div class="font-weight-medium text-dark d-flex align-items-center gap-1.5 flex-wrap">
                                    <span>{{ $u->name }}</span>
                                    @if($isCurrent)
                                        <span class="badge bg-azure-lt border border-azure-subtle" style="font-size: 0.65rem;">Akun Anda</span>
                                    @endif
                                    @if($isOnline)
                                        <span class="badge bg-success-lt border border-success-subtle" style="font-size: 0.62rem;" title="Aktif dalam 15 menit terakhir">
                                            <span class="status-dot status-dot-animated status-green me-1"></span> Online
                                        </span>
                                    @endif
                                </div>
                                <div class="text-muted small d-flex align-items-center gap-2 mt-0.5">
                                    <span><i class="ti ti-user me-0.5"></i>{{ $u->username }}</span>
                                    <span>&bull;</span>
                                    <span><i class="ti ti-mail me-0.5"></i>{{ $u->email }}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($u->role)
                            @php
                                $roleSlug = $u->role->slug;
                                $roleBadge = match($roleSlug) {
                                    'super_admin' => 'bg-danger text-white shadow-xs',
                                    'waka_kurikulum' => 'bg-purple text-white shadow-xs',
                                    'supervisor' => 'bg-indigo text-white shadow-xs',
                                    'kepala_sekolah' => 'bg-dark text-white shadow-xs',
                                    'guru' => 'bg-blue-lt border border-blue-subtle text-blue fw-bold',
                                    default => 'bg-secondary-lt border text-secondary fw-bold'
                                };
                            @endphp
                            <span class="badge {{ $roleBadge }}">
                                @if($u->isSuperAdmin())
                                    <i class="ti ti-shield-lock me-1"></i>
                                @endif
                                {{ $u->role->name }}
                            </span>
                        @else
                            <span class="badge bg-warning-lt">Tanpa Peran</span>
                        @endif
                    </td>
                    <td>
                        <div class="text-dark small fw-medium">{{ $u->position ?: '-' }}</div>
                        @if($u->nip)
                            <div class="text-muted" style="font-size: 0.75rem;"><i class="ti ti-id me-0.5"></i>NIP: {{ $u->nip }}</div>
                        @endif
                    </td>
                    <td>
                        @if($u->phone)
                            <div class="small text-dark"><i class="ti ti-phone me-0.5 text-muted"></i>{{ $u->phone }}</div>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                        @if($u->short_name)
                            <div class="text-muted" style="font-size: 0.72rem;">Panggilan: {{ $u->short_name }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($u->is_active)
                            <span class="badge bg-success-lt border border-success-subtle">
                                <i class="ti ti-check me-0.5"></i> Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary-lt border">
                                <i class="ti ti-x me-0.5"></i> Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-settings me-1"></i> Aksi
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                <!-- Edit Modal Trigger -->
                                <button type="button" class="dropdown-item btn-edit-user"
                                    data-id="{{ $u->id }}"
                                    data-name="{{ $u->name }}"
                                    data-short-name="{{ $u->short_name }}"
                                    data-username="{{ $u->username }}"
                                    data-email="{{ $u->email }}"
                                    data-role-id="{{ $u->role_id }}"
                                    data-position="{{ $u->position }}"
                                    data-nip="{{ $u->nip }}"
                                    data-phone="{{ $u->phone }}"
                                    data-is-active="{{ $u->is_active ? '1' : '0' }}"
                                    data-update-url="{{ route('users.update', $u->id) }}">
                                    <i class="ti ti-edit me-2 text-primary"></i> Edit Data &amp; Peran
                                </button>

                                <!-- Reset Password Modal Trigger -->
                                <button type="button" class="dropdown-item btn-reset-password"
                                    data-id="{{ $u->id }}"
                                    data-name="{{ $u->name }}"
                                    data-username="{{ $u->username }}"
                                    data-reset-url="{{ route('users.reset-password', $u->id) }}">
                                    <i class="ti ti-key me-2 text-warning"></i> Reset Password
                                </button>

                                <div class="dropdown-divider"></div>

                                <!-- Toggle Active Status -->
                                @if(!$isCurrent)
                                    <form action="{{ route('users.toggle-status', $u->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Apakah Anda yakin ingin {{ $u->is_active ? 'menonaktifkan' : 'mengaktifkan kembali' }} akun {{ $u->name }}?');">
                                        @csrf
                                        <button type="submit" class="dropdown-item {{ $u->is_active ? 'text-secondary' : 'text-success' }}">
                                            @if($u->is_active)
                                                <i class="ti ti-user-off me-2"></i> Nonaktifkan Akun
                                            @else
                                                <i class="ti ti-user-check me-2"></i> Aktifkan Akun
                                            @endif
                                        </button>
                                    </form>
                                @endif

                                <!-- Delete Account -->
                                @if(!$isCurrent)
                                    <form action="{{ route('users.destroy', $u->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus akun {{ $u->name }} ({{ $u->username }}) secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="ti ti-trash me-2"></i> Hapus Akun
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-user-x fs-1 mb-2 d-block text-secondary opacity-50"></i>
                        Tidak ada data pengguna yang sesuai dengan filter pencarian.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between py-2 border-top">
        <div class="text-muted small">
            Menampilkan {{ $users->firstItem() }} s/d {{ $users->lastItem() }} dari {{ $users->total() }} entri
        </div>
        <div>
            {{ $users->links() }}
        </div>
    </div>
    @endif
</div>

<!-- ========================================== -->
<!-- MODAL 1: Tambah Pengguna Baru               -->
<!-- ========================================== -->
<div class="modal modal-blur fade" id="modalCreateUser" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="ti ti-user-plus me-2 fs-2"></i> Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Anda dapat membuat akun dengan peran <strong>Super Admin</strong> (sehingga ada lebih dari 1 Super Admin), <strong>Guru</strong>, atau <strong>Staf/Tendik</strong>.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required">Nama Lengkap &amp; Gelar</label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Santoso, S.Kom." required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nama Panggilan / Singkat</label>
                            <input type="text" name="short_name" class="form-control" placeholder="Contoh: Pak Budi" value="{{ old('short_name') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Username (Login)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-user"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Contoh: budi.santoso" required value="{{ old('username') }}">
                            </div>
                            <small class="form-hint">Digunakan saat login ke aplikasi SIAP.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-mail"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Contoh: budi@sekolah.sch.id" required value="{{ old('email') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Peran Utama (Role)</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- Pilih Peran Pengguna --</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}" {{ old('role_id') == $r->id ? 'selected' : '' }}>
                                        {{ $r->name }} {{ $r->slug === 'super_admin' ? '(Akses Penuh)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan Struktural / Posisi</label>
                            <input type="text" name="position" class="form-control" placeholder="Contoh: Super Admin, Guru Mapel, Staf TU..." value="{{ old('position') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" name="nip" class="form-control" placeholder="Nomor Induk Pegawai jika ada" value="{{ old('nip') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor WhatsApp / Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="Contoh: 081234567890" value="{{ old('phone') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-lock"></i></span>
                                <input type="password" name="password" id="create_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('create_password')">
                                    <i class="ti ti-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Konfirmasi Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-lock-check"></i></span>
                                <input type="password" name="password_confirmation" id="create_password_confirm" class="form-control" placeholder="Ulangi password di atas" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('create_password_confirm')">
                                    <i class="ti ti-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <span class="form-check-label fw-medium">Aktifkan akun ini segera (pengguna dapat langsung login)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-check me-1"></i> Simpan Pengguna Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 2: Edit Data & Peran Pengguna         -->
<!-- ========================================== -->
<div class="modal modal-blur fade" id="modalEditUser" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form id="formEditUser" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="ti ti-edit me-2 fs-2"></i> Edit Data &amp; Peran Pengguna
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required">Nama Lengkap &amp; Gelar</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nama Panggilan / Singkat</label>
                            <input type="text" name="short_name" id="edit_short_name" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-user"></i></span>
                                <input type="text" name="username" id="edit_username" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-mail"></i></span>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Peran Utama (Role)</label>
                            <select name="role_id" id="edit_role_id" class="form-select" required>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }} {{ $r->slug === 'super_admin' ? '(Akses Penuh)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan Struktural / Posisi</label>
                            <input type="text" name="position" id="edit_position" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">NIP</label>
                            <input type="text" name="nip" id="edit_nip" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor WhatsApp / Telepon</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                                <span class="form-check-label fw-medium">Status Akun Aktif (Dapat Login)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3: Reset Password Pengguna            -->
<!-- ========================================== -->
<div class="modal modal-blur fade" id="modalResetPassword" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form id="formResetPassword" action="" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title d-flex align-items-center fw-bold">
                        <i class="ti ti-key me-2 fs-2"></i> Reset Password Pengguna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="card bg-light-subtle border-0 mb-3 p-3">
                        <div class="small text-muted mb-1">Mereset password untuk akun:</div>
                        <div class="fw-bold text-dark fs-3" id="reset_user_name">-</div>
                        <div class="small text-muted"><i class="ti ti-user me-1"></i>Username: <code id="reset_user_username">-</code></div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label required mb-0">Password Baru</label>
                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.72rem;" onclick="generateRandomPassword()">
                                <i class="ti ti-wand me-1"></i> Buat Acak
                            </button>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                            <input type="password" name="password" id="reset_password_input" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_password_input')">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-lock-check"></i></span>
                            <input type="password" name="password_confirmation" id="reset_password_confirm_input" class="form-control" placeholder="Ulangi password baru" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_password_confirm_input')">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="small text-muted">
                        <i class="ti ti-info-circle me-1 text-primary"></i>
                        Setelah diubah, pengguna dapat langsung menggunakan password baru ini untuk login.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning ms-auto fw-bold text-dark">
                        <i class="ti ti-check me-1"></i> Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit User Modal Population
        const editButtons = document.querySelectorAll('.btn-edit-user');
        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditUser'));
        const formEdit = document.getElementById('formEditUser');

        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const updateUrl = this.getAttribute('data-update-url');
                formEdit.action = updateUrl;

                document.getElementById('edit_name').value = this.getAttribute('data-name') || '';
                document.getElementById('edit_short_name').value = this.getAttribute('data-short-name') || '';
                document.getElementById('edit_username').value = this.getAttribute('data-username') || '';
                document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                document.getElementById('edit_role_id').value = this.getAttribute('data-role-id') || '';
                document.getElementById('edit_position').value = this.getAttribute('data-position') || '';
                document.getElementById('edit_nip').value = this.getAttribute('data-nip') || '';
                document.getElementById('edit_phone').value = this.getAttribute('data-phone') || '';
                document.getElementById('edit_is_active').checked = this.getAttribute('data-is-active') === '1';

                modalEdit.show();
            });
        });

        // Reset Password Modal Population
        const resetButtons = document.querySelectorAll('.btn-reset-password');
        const modalReset = new bootstrap.Modal(document.getElementById('modalResetPassword'));
        const formReset = document.getElementById('formResetPassword');

        resetButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const resetUrl = this.getAttribute('data-reset-url');
                formReset.action = resetUrl;

                document.getElementById('reset_user_name').innerText = this.getAttribute('data-name') || '-';
                document.getElementById('reset_user_username').innerText = this.getAttribute('data-username') || '-';

                document.getElementById('reset_password_input').value = '';
                document.getElementById('reset_password_confirm_input').value = '';

                modalReset.show();
            });
        });
    });

    // Helper: Toggle Password Visibility
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    // Helper: Generate Random Password
    function generateRandomPassword() {
        const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
        let pass = '';
        for (let i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const inputPass = document.getElementById('reset_password_input');
        const inputConfirm = document.getElementById('reset_password_confirm_input');
        inputPass.type = 'text';
        inputConfirm.type = 'text';
        inputPass.value = pass;
        inputConfirm.value = pass;
    }
</script>
@endpush
