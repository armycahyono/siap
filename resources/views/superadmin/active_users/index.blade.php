@extends('layouts.app')

@section('title', 'User Aktif & Monitoring Sesi - Super Admin')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Monitoring User Aktif & Sesi Login',
    'subtitle' => 'Pantau pengguna yang sedang online, sesi login aktif real-time di sistem, dan kendali keaktifan akun pengguna.',
    'icon' => 'ti ti-user-check',
    'category' => 'SUPER ADMIN & MONITORING SESI',
])

<!-- Stat Cards -->
<div class="row row-cards mb-4">
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
                        <span class="bg-success text-white avatar shadow-sm">
                            <i class="ti ti-wifi fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Sedang Online</div>
                        <div class="fw-bold fs-2 text-success d-flex align-items-center">
                            {{ $onlineUsersCount }}
                            <span class="badge bg-success-lt ms-2" style="font-size: 0.65rem;">&bull; Real-time</span>
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
                            <i class="ti ti-check fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Akun Aktif</div>
                        <div class="fw-bold fs-2 text-teal">{{ $activeAccountCount }}</div>
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
                            <i class="ti ti-user-x fs-2"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Akun Nonaktif</div>
                        <div class="fw-bold fs-2 text-danger">{{ $inactiveAccountCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white p-2">
        <ul class="nav nav-pills gap-2">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'sessions' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('active-users.index', ['tab' => 'sessions']) }}">
                    <i class="ti ti-device-laptop me-1 fs-3"></i>
                    <span>Sesi Online Real-Time ({{ $activeSessions->total() }})</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'users' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('active-users.index', ['tab' => 'users']) }}">
                    <i class="ti ti-user-check me-1 fs-3"></i>
                    <span>Semua Akun Pengguna ({{ $allUsers->total() }})</span>
                </a>
            </li>
        </ul>
    </div>
</div>

@if($tab === 'sessions')
    <!-- TAB 1: Sesi Online Real-Time -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form action="{{ route('active-users.index') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="sessions">
                <div class="col-md-5">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, username, email, IP address..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role_id" class="form-select">
                        <option value="">-- Semua Peran / Role --</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ $roleId == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                </div>
                @if($search || $roleId)
                <div class="col-md-2">
                    <a href="{{ route('active-users.index', ['tab' => 'sessions']) }}" class="btn btn-outline-secondary w-100">
                        <i class="ti ti-rotate-2 me-1"></i> Reset
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th>Pengguna</th>
                        <th>Peran (Role)</th>
                        <th>Alamat IP</th>
                        <th>Perangkat &amp; Browser</th>
                        <th>Aktivitas Terakhir</th>
                        <th>Status Sesi</th>
                        <th class="w-1 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeSessions as $s)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                    {{ strtoupper(substr($s->user_name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $s->user_name }}</div>
                                    <div class="small text-muted">{{ $s->email }} &bull; <code>{{ $s->username }}</code></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $s->role_name ?? 'User' }}</span>
                        </td>
                        <td>
                            <code class="text-dark fw-bold">{{ $s->ip_address ?: '-' }}</code>
                        </td>
                        <td>
                            <div class="text-dark small d-flex align-items-center">
                                <i class="ti ti-device-desktop me-1 text-secondary"></i>
                                {{ $s->device }}
                            </div>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">{{ $s->last_seen }}</div>
                        </td>
                        <td>
                            @if($s->is_current)
                                <span class="badge bg-primary text-white">Sesi Anda Saat Ini</span>
                            @elseif($s->is_recent_online)
                                <span class="badge bg-success text-white"><span class="badge-dot bg-white me-1"></span> Online</span>
                            @else
                                <span class="badge bg-secondary text-white">Aktif (Idle)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($s->is_current)
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Anda tidak dapat memutus sesi Anda sendiri">
                                    <i class="ti ti-lock"></i>
                                </button>
                            @else
                                <form action="{{ route('active-users.destroy-session', $s->session_id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memutus sesi login pengguna ini secara paksa?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="Putus Sesi (Force Logout)">
                                        <i class="ti ti-power me-1"></i> Putus Sesi
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="ti ti-users-minus fs-1 mb-2 d-block"></i>
                            Tidak ada sesi login pengguna yang ditemukan sesuai kriteria filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($activeSessions->hasPages())
        <div class="card-footer d-flex align-items-center py-2">
            {{ $activeSessions->links() }}
        </div>
        @endif
    </div>
@else
    <!-- TAB 2: Semua Akun Pengguna -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form action="{{ route('active-users.index') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="users">
                <div class="col-md-4">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, username, email, NIP..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role_id" class="form-select">
                        <option value="">-- Semua Peran / Role --</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ $roleId == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
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
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($search || $roleId || ($statusFilter !== null && $statusFilter !== ''))
                    <a href="{{ route('active-users.index', ['tab' => 'users']) }}" class="btn btn-outline-secondary" title="Reset">
                        <i class="ti ti-rotate-2"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th>Pengguna</th>
                        <th>Peran (Role)</th>
                        <th>NIP / Identitas</th>
                        <th>Status Akun</th>
                        <th>Sesi Login</th>
                        <th class="w-1 text-center">Kendali Akun</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allUsers as $u)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $u->name }}</div>
                                    <div class="small text-muted">{{ $u->email }} &bull; <code>{{ $u->username }}</code></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $u->role?->name ?? 'User' }}</span>
                        </td>
                        <td>
                            <small class="text-muted">{{ $u->nip ?: '-' }}</small>
                        </td>
                        <td>
                            @if($u->is_active)
                                <span class="badge bg-success-lt text-success"><i class="ti ti-check me-1"></i> Aktif</span>
                            @else
                                <span class="badge bg-danger-lt text-danger"><i class="ti ti-x me-1"></i> Dinonaktifkan</span>
                            @endif
                        </td>
                        <td>
                            @if(isset($onlineUserIds[$u->id]))
                                <span class="badge bg-success text-white"><span class="badge-dot bg-white me-1"></span> Online</span>
                            @else
                                <span class="badge bg-light text-muted border">Offline</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($u->id === auth()->id())
                                <button class="btn btn-sm btn-light text-muted" disabled title="Akun Anda Sendiri">
                                    <i class="ti ti-user"></i> Akun Anda
                                </button>
                            @else
                                <form action="{{ route('active-users.toggle-status', $u) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin {{ $u->is_active ? 'menonaktifkan' : 'mengaktifkan kembali' }} akun {{ $u->name }}?');">
                                    @csrf
                                    @if($u->is_active)
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm">
                                            <i class="ti ti-user-x me-1"></i> Nonaktifkan
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-sm btn-success shadow-sm">
                                            <i class="ti ti-user-check me-1"></i> Aktifkan
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="ti ti-users-minus fs-1 mb-2 d-block"></i>
                            Tidak ada akun pengguna yang ditemukan sesuai kriteria filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($allUsers->hasPages())
        <div class="card-footer d-flex align-items-center py-2">
            {{ $allUsers->links() }}
        </div>
        @endif
    </div>
@endif
@endsection
