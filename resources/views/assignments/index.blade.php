@extends('layouts.app')

@section('title', 'Penugasan Pegawai & Kewenangan Lintas Modul')

@section('content')
<!-- Page Header -->
@include('layouts._hero_header', [
    'title' => 'Penugasan Pegawai & Kewenangan Lintas Modul',
    'subtitle' => 'Standardisasi penugasan operasional pegawai lintas modul (Core, Kurikulum, Kesiswaan, BK, Piket, Sarpras, Humas, Perpustakaan, TU) dengan dukungan multi-penugasan per satu akun identitas.',
    'icon' => 'ti ti-id-badge-2',
    'category' => 'MANAJEMEN GTK & AKSES',
    'academicYear' => $filterYear !== 'all' ? $filterYear : $currentYear,
])

<div class="container-xl">
    <!-- Banner Konsolidasi Penugasan GTK -->
    <div class="alert alert-primary border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 mb-3">
        <div class="d-flex align-items-center me-3">
            <span class="avatar bg-primary-lt text-primary rounded me-3">
                <i class="ti ti-user-check fs-2"></i>
            </span>
            <div>
                <h5 class="alert-title fw-bold mb-1">Pengelolaan Terpadu: Penugasan GTK</h5>
                <div class="text-muted small">Seluruh penugasan guru dan tenaga kependidikan (Mata Pelajaran, Wali Kelas &amp; BK, Jabatan Struktural, serta Tugas Operasional) kini dikelola melalui satu pintu di menu <strong>Penugasan GTK</strong>. Halaman ini dipertahankan untuk kebutuhan audit RBAC teknis.</div>
            </div>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('teacher-assignments.index') }}" class="btn btn-primary text-nowrap">
                <i class="ti ti-arrow-right me-1"></i> Buka Penugasan GTK
            </a>
        </div>
    </div>
    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="alert alert-important alert-success alert-dismissible shadow-sm fade show mb-3" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-circle-check fs-2 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-important alert-danger alert-dismissible shadow-sm fade show mb-3" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-2 me-2"></i>
                <div>
                    <strong>Terjadi kesalahan input:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics Summary Cards -->
    <div class="row row-deck row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="ti ti-briefcase fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Total Penugasan Aktif</div>
                            <div class="h2 mb-0 fw-bold">{{ number_format($totalActive) }}</div>
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
                            <span class="bg-success text-white avatar">
                                <i class="ti ti-users fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Pegawai Ditugaskan</div>
                            <div class="h2 mb-0 fw-bold">{{ number_format($totalUsersAssigned) }}</div>
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
                            <span class="bg-azure text-white avatar">
                                <i class="ti ti-layers-intersect fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Multi-Penugasan</div>
                            <div class="d-flex align-items-baseline">
                                <div class="h2 mb-0 fw-bold me-2">{{ number_format($multiAssignedUsers) }}</div>
                                <span class="badge bg-azure-lt">1 Orang Ragam Tugas</span>
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
                            <span class="bg-warning text-white avatar">
                                <i class="ti ti-clock-pause fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Segera Berakhir</div>
                            <div class="d-flex align-items-baseline">
                                <div class="h2 mb-0 fw-bold me-2">{{ number_format($expiringCount) }}</div>
                                <span class="badge bg-warning-lt">&le; 30 Hari</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Action Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-filter me-1 text-primary"></i> Filter &amp; Pencarian Penugasan
                </h4>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalBulkCloseAssignment">
                    <i class="ti ti-clock-pause me-1"></i> Tutup Massal Periode
                </button>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalRolloverAssignment">
                    <i class="ti ti-arrows-right me-1"></i> Rollover ke TA Baru
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddAssignment">
                    <i class="ti ti-plus me-1"></i> Tambah Penugasan Pegawai
                </button>
            </div>
        </div>
        <div class="card-body bg-light-subtle pb-2">
            <!-- Quick Role Pills -->
            <div class="d-flex flex-wrap gap-1 mb-3">
                <span class="text-muted small align-self-center me-1">Pintasan Penugasan:</span>
                <a href="{{ route('employee-assignments.index', array_merge(request()->except('role_id'), ['academic_year' => $filterYear])) }}" class="badge {{ empty($filterRoleId) ? 'bg-primary text-white' : 'bg-light text-muted border' }} text-decoration-none py-1.5 px-2">
                    Semua
                </a>
                @php
                    $tatibRole = $roles->firstWhere('slug', 'tim_kedisiplinan');
                    $coordTatibRole = $roles->firstWhere('slug', 'koordinator_kedisiplinan');
                    $bkRole = $roles->firstWhere('slug', 'guru_bk');
                    $piketRole = $roles->firstWhere('slug', 'guru_piket');
                @endphp
                @if($tatibRole)
                    <a href="{{ route('employee-assignments.index', array_merge(request()->except('role_id'), ['role_id' => $tatibRole->id, 'academic_year' => $filterYear])) }}" class="badge {{ (string)$filterRoleId === (string)$tatibRole->id ? 'bg-danger text-white' : 'bg-danger-lt border border-danger' }} text-decoration-none py-1.5 px-2">
                        <i class="ti ti-shield-alert me-1"></i> Tim Kedisiplinan
                    </a>
                @endif
                @if($coordTatibRole)
                    <a href="{{ route('employee-assignments.index', array_merge(request()->except('role_id'), ['role_id' => $coordTatibRole->id, 'academic_year' => $filterYear])) }}" class="badge {{ (string)$filterRoleId === (string)$coordTatibRole->id ? 'bg-danger text-white' : 'bg-danger-lt border border-danger' }} text-decoration-none py-1.5 px-2">
                        <i class="ti ti-shield me-1"></i> Koordinator Kedisiplinan
                    </a>
                @endif
                @if($bkRole)
                    <a href="{{ route('employee-assignments.index', array_merge(request()->except('role_id'), ['role_id' => $bkRole->id, 'academic_year' => $filterYear])) }}" class="badge {{ (string)$filterRoleId === (string)$bkRole->id ? 'bg-primary text-white' : 'bg-primary-lt border border-primary' }} text-decoration-none py-1.5 px-2">
                        <i class="ti ti-heart-handshake me-1"></i> Guru BK
                    </a>
                @endif
                @if($piketRole)
                    <a href="{{ route('employee-assignments.index', array_merge(request()->except('role_id'), ['role_id' => $piketRole->id, 'academic_year' => $filterYear])) }}" class="badge {{ (string)$filterRoleId === (string)$piketRole->id ? 'bg-warning text-white' : 'bg-warning-lt border border-warning' }} text-decoration-none py-1.5 px-2">
                        <i class="ti ti-clock me-1"></i> Petugas Piket
                    </a>
                @endif
            </div>

            <form action="{{ route('employee-assignments.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Tahun Ajaran</label>
                    <select name="academic_year" class="form-select">
                        <option value="all" {{ $filterYear === 'all' ? 'selected' : '' }}>Semua Tahun Ajaran</option>
                        @foreach($academicYears as $ay)
                            <option value="{{ $ay->name }}" {{ $filterYear === $ay->name ? 'selected' : '' }}>
                                {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Peran / Penugasan</label>
                    <select name="role_id" class="form-select">
                        <option value="">Semua Peran / Tugas</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ (string)$filterRoleId === (string)$role->id ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted">Status Keaktifan</label>
                    <select name="status" class="form-select">
                        <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>Semua Status</option>
                        <option value="active" {{ $filterStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ $filterStatus === 'inactive' ? 'selected' : '' }}>Nonaktif / Selesai</option>
                        <option value="expired" {{ $filterStatus === 'expired' ? 'selected' : '' }}>Kedaluwarsa</option>
                        <option value="upcoming" {{ $filterStatus === 'upcoming' ? 'selected' : '' }}>Belum Berlaku</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Cari Nama / NIP / SK</label>
                    <input type="text" name="search" class="form-control" placeholder="Ketik kata kunci pencarian..." value="{{ $search }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100" title="Terapkan Filter">
                        <i class="ti ti-search"></i>
                    </button>
                    <a href="{{ route('employee-assignments.index') }}" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="ti ti-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table of Assignments -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
            <h4 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-list me-1 text-primary"></i> Daftar Penugasan Pegawai Lintas Modul
            </h4>
            <span class="text-muted small">Menampilkan {{ $assignments->firstItem() ?? 0 }} - {{ $assignments->lastItem() ?? 0 }} dari {{ $assignments->total() }} penugasan</span>
        </div>

        <div class="table-responsive mb-0">
            <table class="table table-vcenter table-hover card-table">
                <thead class="bg-light">
                    <tr>
                        <th class="w-1 text-center">No</th>
                        <th>Pegawai (Identitas Akun)</th>
                        <th>Peran Penugasan</th>
                        <th>Unit / Konteks</th>
                        <th>Periode &amp; Masa Berlaku</th>
                        <th>No. SK &amp; Keterangan</th>
                        <th class="text-center">Status</th>
                        <th class="w-1 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $idx => $assignment)
                        @php
                            $user = $assignment->user;
                            $effectiveStatus = $assignment->effective_status;
                            $badgeClass = $assignment->status_badge_class;
                            $statusLabel = $assignment->status_label;
                        @endphp
                        <tr>
                            <td class="text-center text-muted small">
                                {{ $assignments->firstItem() + $idx }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-primary-lt text-primary fw-bold">
                                        {{ strtoupper(substr($user->name ?? '?', 0, 2)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $user->name ?? '-' }}</div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <span>NIP: {{ $user->nip ?: '-' }}</span>
                                            <span>&bull;</span>
                                            <span class="badge bg-secondary-lt text-secondary" style="font-size: 0.7rem;">
                                                {{ $user->position ? ucwords($user->position) : ($user->role?->name ?? 'Pegawai') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php
                                    $roleBadgeColor = match($assignment->role?->slug) {
                                        'tim_kedisiplinan', 'koordinator_kedisiplinan' => 'bg-danger-lt text-danger border border-danger',
                                        'guru_bk' => 'bg-primary-lt text-primary border border-primary',
                                        'guru_piket' => 'bg-warning-lt text-warning border border-warning',
                                        default => 'bg-indigo-lt text-indigo',
                                    };
                                @endphp
                                <span class="badge {{ $roleBadgeColor }} fw-bold mb-1">
                                    {{ $assignment->role?->name ?? '-' }}
                                </span>
                                @if($assignment->role?->description)
                                    <div class="small text-muted text-truncate" style="max-width: 200px;" title="{{ $assignment->role->description }}">
                                        {{ $assignment->role->description }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($assignment->context_label)
                                    <span class="badge bg-azure-lt">
                                        <i class="ti ti-building me-1"></i> {{ $assignment->context_label }}
                                    </span>
                                @else
                                    <span class="text-muted small">
                                        <i class="ti ti-world me-1"></i> Global Sekolah
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-medium text-dark">
                                    <i class="ti ti-calendar-stats text-muted me-1"></i>
                                    {{ $assignment->academic_year ?: 'Semua Tahun' }}
                                </div>
                                <div class="small text-muted">
                                    @if($assignment->valid_from || $assignment->valid_until)
                                        {{ $assignment->valid_from ? $assignment->valid_from->format('d/m/Y') : '-' }}
                                        s.d.
                                        {{ $assignment->valid_until ? $assignment->valid_until->format('d/m/Y') : 'Seterusnya' }}
                                    @else
                                        <span class="text-secondary">Permanen / Tidak Dibatasi</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">
                                    {{ $assignment->sk_number ?: '-' }}
                                </div>
                                @if($assignment->notes)
                                    <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ $assignment->notes }}">
                                        {{ $assignment->notes }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $badgeClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button type="button" 
                                            class="btn btn-sm btn-icon btn-outline-secondary"
                                            title="Edit Penugasan"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditAssignment"
                                            data-assignment-id="{{ $assignment->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-role-id="{{ $assignment->role_id }}"
                                            data-academic-year="{{ $assignment->academic_year }}"
                                            data-context-type="{{ $assignment->context_type }}"
                                            data-context-id="{{ $assignment->context_id }}"
                                            data-valid-from="{{ $assignment->valid_from ? $assignment->valid_from->format('Y-m-d') : '' }}"
                                            data-valid-until="{{ $assignment->valid_until ? $assignment->valid_until->format('Y-m-d') : '' }}"
                                            data-sk-number="{{ $assignment->sk_number }}"
                                            data-notes="{{ $assignment->notes }}"
                                            data-status="{{ $assignment->status ? 1 : 0 }}"
                                            data-update-url="{{ route('employee-assignments.update', $assignment->id) }}">
                                        <i class="ti ti-edit"></i>
                                    </button>

                                    @if($assignment->status)
                                        <button type="button" 
                                                class="btn btn-sm btn-icon btn-outline-warning"
                                                title="Tutup / Cabut Penugasan Resmi"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalCloseAssignment"
                                                data-assignment-id="{{ $assignment->id }}"
                                                data-user-name="{{ $user->name }}"
                                                data-role-name="{{ $assignment->role?->name }}"
                                                data-close-url="{{ route('employee-assignments.close', $assignment->id) }}">
                                            <i class="ti ti-clock-pause"></i>
                                        </button>
                                    @endif

                                    <form action="{{ route('employee-assignments.toggle', $assignment->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" 
                                                class="btn btn-sm btn-icon {{ $assignment->status ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $assignment->status ? 'Nonaktifkan Sementara' : 'Aktifkan Kembali' }}">
                                            <i class="ti {{ $assignment->status ? 'ti-player-pause' : 'ti-player-play' }}"></i>
                                        </button>
                                    </form>

                                    <form action="{{ route('employee-assignments.destroy', $assignment->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data penugasan ini? Tindakan ini tidak dapat dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="Hapus Data (Kesalahan Input)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-clipboard-x fs-1 text-secondary mb-2 d-block"></i>
                                    <p class="mb-2 fw-medium">Tidak ada data penugasan yang sesuai dengan filter.</p>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddAssignment">
                                        <i class="ti ti-plus me-1"></i> Tambah Penugasan Baru
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assignments->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between py-2 border-top bg-transparent">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal: Tambah Penugasan Pegawai -->
<div class="modal fade" id="modalAddAssignment" tabindex="-1" aria-labelledby="modalAddAssignmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('employee-assignments.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalAddAssignmentLabel">
                        <i class="ti ti-id-badge-2 me-1"></i> Tambah Penugasan Pegawai Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required fw-bold">Pilih Pegawai / GTK</label>
                            <select name="user_id" class="form-select select2-user" required>
                                <option value="">-- Pilih Pegawai --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">
                                        {{ $u->name }} {{ $u->nip ? "({$u->nip})" : '' }} &bull; {{ $u->position ? ucwords($u->position) : ($u->role?->name ?? 'Pegawai') }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">Satu pegawai dapat memiliki beberapa penugasan aktif tanpa duplikasi akun.</div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label required fw-bold">Jenis Peran / Penugasan</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- Pilih Penugasan --</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tahun Ajaran Berlaku</label>
                            <select name="academic_year" class="form-select">
                                <option value="">-- Berlaku Global / Permanen --</option>
                                @foreach($academicYears as $ay)
                                    <option value="{{ $ay->name }}" {{ ($filterYear !== 'all' ? $filterYear : $currentYear) === $ay->name ? 'selected' : '' }}>
                                        {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">Kosongkan jika penugasan bersifat umum dan tidak dibatasi tahun ajaran.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Konteks Unit / Rombel (Opsional)</label>
                            <div class="row g-2">
                                <div class="col-5">
                                    <select name="context_type" id="addContextType" class="form-select">
                                        <option value="">Global</option>
                                        <option value="classroom">Rombel / Kelas</option>
                                        <option value="department">Jurusan</option>
                                    </select>
                                </div>
                                <div class="col-7">
                                    <select name="context_id" id="addContextId" class="form-select" disabled>
                                        <option value="">-- Pilih Unit --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-text small">Gunakan jika penugasan melekat pada kelas binaan (misal Wali Kelas) atau jurusan.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Mulai Berlaku</label>
                            <input type="date" name="valid_from" class="form-control" value="{{ now()->toDateString() }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Berakhir Berlaku</label>
                            <input type="date" name="valid_until" class="form-control">
                            <div class="form-text small">Kosongkan jika masa berlaku penugasan tidak dibatasi secara spesifik.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK / Surat Tugas</label>
                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 800/124/SMK/2026">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Catatan Penugasan</label>
                            <input type="text" name="notes" class="form-control" placeholder="Keterangan tambahan penugasan...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Penugasan Pegawai -->
<div class="modal fade" id="modalEditAssignment" tabindex="-1" aria-labelledby="modalEditAssignmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formEditAssignment" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalEditAssignmentLabel">
                        <i class="ti ti-edit me-1"></i> Perbarui Penugasan Pegawai
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Pegawai</label>
                        <input type="text" id="editUserName" class="form-control bg-light" readonly>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Peran / Penugasan</label>
                            <select name="role_id" id="editRoleId" class="form-select" required>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tahun Ajaran</label>
                            <select name="academic_year" id="editAcademicYear" class="form-select">
                                <option value="">-- Berlaku Global / Permanen --</option>
                                @foreach($academicYears as $ay)
                                    <option value="{{ $ay->name }}">{{ $ay->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Konteks Unit / Rombel</label>
                            <div class="row g-2">
                                <div class="col-5">
                                    <select name="context_type" id="editContextType" class="form-select">
                                        <option value="">Global</option>
                                        <option value="classroom">Rombel / Kelas</option>
                                        <option value="department">Jurusan</option>
                                    </select>
                                </div>
                                <div class="col-7">
                                    <select name="context_id" id="editContextId" class="form-select">
                                        <option value="">-- Pilih Unit --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal Mulai</label>
                            <input type="date" name="valid_from" id="editValidFrom" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal Berakhir</label>
                            <input type="date" name="valid_until" id="editValidUntil" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK</label>
                            <input type="text" name="sk_number" id="editSkNumber" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Catatan Penugasan</label>
                            <input type="text" name="notes" id="editNotes" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark ms-auto">
                        <i class="ti ti-device-floppy me-1"></i> Perbarui Data Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tutup Penugasan Resmi -->
<div class="modal fade" id="modalCloseAssignment" tabindex="-1" aria-labelledby="modalCloseAssignmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formCloseAssignment" method="POST">
                @csrf
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title fw-bold" id="modalCloseAssignmentLabel">
                        <i class="ti ti-clock-pause me-1"></i> Tutup / Cabut Penugasan Resmi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted">
                        Penutupan resmi akan menonaktifkan penugasan ini dan mencatat tanggal berakhir tanpa menghapus data riwayat dari sistem SIAP.
                    </p>

                    <div class="card bg-light-subtle p-3 mb-3 border-0">
                        <div class="d-flex align-items-center mb-1">
                            <span class="text-muted small me-2">Pegawai:</span>
                            <span class="fw-bold text-dark" id="closeUserName">-</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-muted small me-2">Penugasan:</span>
                            <span class="badge bg-indigo-lt text-indigo" id="closeRoleName">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Tanggal Resmi Berakhir / Dicabut</label>
                        <input type="date" name="closing_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Keterangan / Alasan Penutupan</label>
                        <textarea name="closing_notes" class="form-control" rows="3" placeholder="Contoh: Selesai masa bakti semester genap / Mutasi tugas / Rotasi kepanitiaan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning ms-auto">
                        <i class="ti ti-check me-1"></i> Tutup Penugasan Resmi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tutup Massal Penugasan Tahun Ajaran Berakhir -->
<div class="modal fade" id="modalBulkCloseAssignment" tabindex="-1" aria-labelledby="modalBulkCloseAssignmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('employee-assignments.bulk-close') }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title fw-bold" id="modalBulkCloseAssignmentLabel">
                        <i class="ti ti-clock-pause me-1"></i> Tutup Massal Penugasan Periode
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning d-flex align-items-center mb-3">
                        <i class="ti ti-alert-triangle fs-2 me-2"></i>
                        <div class="small">
                            Aksi ini akan menonaktifkan seluruh penugasan aktif pada tahun ajaran yang dipilih dan mencatat tanggal berakhir tanpa menghapus riwayat dari sistem SIAP.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Tahun Ajaran yang Ditutup</label>
                        <select name="academic_year" class="form-select" required>
                            @foreach($academicYears as $ay)
                                <option value="{{ $ay->name }}" {{ ($filterYear !== 'all' ? $filterYear : $currentYear) === $ay->name ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Tanggal Resmi Berakhir / Selesai</label>
                        <input type="date" name="closing_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Catatan Penutupan Massal</label>
                        <textarea name="closing_notes" class="form-control" rows="2" placeholder="Contoh: Selesai masa bakti tahun ajaran / Demisioner kepengurusan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning ms-auto">
                        <i class="ti ti-check me-1"></i> Eksekusi Penutupan Massal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Rollover Penugasan ke Tahun Ajaran Baru -->
<div class="modal fade" id="modalRolloverAssignment" tabindex="-1" aria-labelledby="modalRolloverAssignmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('employee-assignments.rollover') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalRolloverAssignmentLabel">
                        <i class="ti ti-arrows-right me-1"></i> Rollover Penugasan ke Tahun Ajaran Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info d-flex align-items-center mb-3">
                        <i class="ti ti-info-circle fs-2 me-2"></i>
                        <div class="small">
                            Fitur ini menyalin penugasan operasional aktif dari tahun ajaran asal ke tahun ajaran target secara aman (1 orang tetap 1 akun tanpa duplikasi).
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Tahun Ajaran Asal</label>
                            <select name="source_academic_year" class="form-select" required>
                                @foreach($academicYears as $ay)
                                    <option value="{{ $ay->name }}" {{ ($filterYear !== 'all' ? $filterYear : $currentYear) === $ay->name ? 'selected' : '' }}>
                                        {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-bold">Tahun Ajaran Target (Baru)</label>
                            @php
                                $nextTargetYear = '2027/2028';
                                if (preg_match('/^(\d{4})\/(\d{4})$/', ($filterYear !== 'all' ? $filterYear : $currentYear), $m)) {
                                    $nextTargetYear = ((int)$m[1] + 1) . '/' . ((int)$m[2] + 1);
                                }
                            @endphp
                            <input type="text" name="target_academic_year" class="form-control" value="{{ $nextTargetYear }}" placeholder="Contoh: 2027/2028" required>
                            <div class="form-text small">Masukkan nama tahun ajaran baru berikutnya.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK Baru (Opsional)</label>
                            <input type="text" name="new_sk_number" class="form-control" placeholder="Nomor SK tahun ajaran baru...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Mulai Berlaku (Opsional)</label>
                            <input type="date" name="valid_from" class="form-control">
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="close_previous" value="1" id="checkClosePrevious" checked>
                                <label class="form-check-label fw-bold text-dark" for="checkClosePrevious">
                                    Tutup resmi penugasan di Tahun Ajaran Asal
                                </label>
                                <div class="form-text small">
                                    Jika dicentang, penugasan di tahun ajaran asal akan otomatis dinonaktifkan dengan tanggal berakhir sebelum tahun baru dimulai.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success ms-auto">
                        <i class="ti ti-check me-1"></i> Jalankan Rollover Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const classroomsData = @json($classrooms->map(fn($c) => ['id' => $c->id, 'name' => $c->name]));
        const departmentsData = @json($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name]));

        function updateContextOptions(typeSelect, idSelect, selectedId = null) {
            const val = typeSelect.value;
            idSelect.innerHTML = '<option value="">-- Pilih Unit --</option>';

            if (!val) {
                idSelect.disabled = true;
                return;
            }

            idSelect.disabled = false;
            let list = [];
            if (val === 'classroom' || val.includes('Classroom')) {
                list = classroomsData;
            } else if (val === 'department' || val.includes('Department')) {
                list = departmentsData;
            }

            list.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                if (selectedId && String(selectedId) === String(item.id)) {
                    opt.selected = true;
                }
                idSelect.appendChild(opt);
            });
        }

        // Add Modal Context binding
        const addContextType = document.getElementById('addContextType');
        const addContextId = document.getElementById('addContextId');
        if (addContextType && addContextId) {
            addContextType.addEventListener('change', () => updateContextOptions(addContextType, addContextId));
        }

        // Edit Modal binding
        const modalEdit = document.getElementById('modalEditAssignment');
        if (modalEdit) {
            modalEdit.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const form = document.getElementById('formEditAssignment');
                form.action = button.getAttribute('data-update-url');

                document.getElementById('editUserName').value = button.getAttribute('data-user-name');
                document.getElementById('editRoleId').value = button.getAttribute('data-role-id');
                document.getElementById('editAcademicYear').value = button.getAttribute('data-academic-year') || '';
                document.getElementById('editValidFrom').value = button.getAttribute('data-valid-from') || '';
                document.getElementById('editValidUntil').value = button.getAttribute('data-valid-until') || '';
                document.getElementById('editSkNumber').value = button.getAttribute('data-sk-number') || '';
                document.getElementById('editNotes').value = button.getAttribute('data-notes') || '';

                const ctxType = button.getAttribute('data-context-type') || '';
                const ctxId = button.getAttribute('data-context-id') || '';
                const editCtxType = document.getElementById('editContextType');
                const editCtxId = document.getElementById('editContextId');

                if (ctxType.includes('Classroom')) {
                    editCtxType.value = 'classroom';
                } else if (ctxType.includes('Department')) {
                    editCtxType.value = 'department';
                } else {
                    editCtxType.value = '';
                }

                updateContextOptions(editCtxType, editCtxId, ctxId);
            });
        }

        // Close Modal binding
        const modalClose = document.getElementById('modalCloseAssignment');
        if (modalClose) {
            modalClose.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const form = document.getElementById('formCloseAssignment');
                form.action = button.getAttribute('data-close-url');

                document.getElementById('closeUserName').textContent = button.getAttribute('data-user-name');
                document.getElementById('closeRoleName').textContent = button.getAttribute('data-role-name');
            });
        }
    });
</script>
@endpush
@endsection
