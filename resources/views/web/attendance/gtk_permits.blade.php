@extends('layouts.web')

@section('title', ($isPersonalScope ? 'Izin & Cuti Saya' : 'Daftar Izin & Cuti GTK') . ' — SIAP')

@section('content')
<div class="container-xl">
    <!-- Breadcrumbs -->
    <div class="mb-3">
        <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Presensi</a></li>
            <li class="breadcrumb-item active" aria-current="page"><a href="#">{{ $isPersonalScope ? 'Izin & Cuti Saya' : 'Daftar Izin & Cuti GTK' }}</a></li>
        </ol>
    </div>

    <!-- Mode Switcher Tab (Khusus Kepala Sekolah / Waka Kurikulum / Super Admin) -->
    @if($canManageGtkPermits)
    <div class="card p-2 mb-3 border-0 shadow-sm bg-white">
        <div class="nav nav-pills nav-fill gap-2" role="tablist">
            <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage']) }}" 
               class="nav-link py-2 px-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2 {{ !$isPersonalScope ? 'active bg-navy text-white shadow-sm' : 'text-secondary bg-light' }}"
               style="{{ !$isPersonalScope ? 'background-color: #102d52 !important;' : '' }}">
                <i class="ti ti-building-community fs-2"></i>
                <div class="text-start">
                    <div style="font-size: 0.88rem; line-height: 1.2;">🏢 Daftar Izin &amp; Cuti GTK</div>
                    <div class="small fw-normal {{ !$isPersonalScope ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.73rem;">Persetujuan &amp; Monitoring Seluruh Guru &amp; Staf (Pimpinan)</div>
                </div>
                @if(($pendingPermitsCount ?? 0) > 0 && $isPersonalScope)
                    <span class="badge bg-warning text-dark ms-auto">{{ $pendingPermitsCount }} Pending</span>
                @endif
            </a>
            <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'personal']) }}" 
               class="nav-link py-2 px-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2 {{ $isPersonalScope ? 'active text-white shadow-sm' : 'text-secondary bg-light' }}"
               style="{{ $isPersonalScope ? 'background-color: #4f46e5 !important;' : '' }}">
                <i class="ti ti-user-check fs-2"></i>
                <div class="text-start">
                    <div style="font-size: 0.88rem; line-height: 1.2;">👤 Izin &amp; Cuti Saya</div>
                    <div class="small fw-normal {{ $isPersonalScope ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.73rem;">Pengajuan Pribadi &amp; Riwayat Cuti Diri Sendiri</div>
                </div>
            </a>
        </div>
    </div>
    @endif

    <!-- Page Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    @if($isPersonalScope)
                        <span class="badge bg-indigo text-white px-2 py-1"><i class="ti ti-user me-1"></i> Data Mandiri GTK</span>
                    @else
                        <span class="badge text-white px-2 py-1" style="background-color: #102d52;"><i class="ti ti-building-community me-1"></i> Panel Manajerial &amp; Persetujuan</span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Kembali ke Beranda">
                        <i class="ti ti-arrow-left"></i>
                    </a>
                    <h2 class="page-title fw-bold mb-0" style="color: {{ $isPersonalScope ? '#4f46e5' : '#102d52' }};">
                        @if($isPersonalScope)
                            <i class="ti ti-user-check me-1 text-indigo"></i> Izin &amp; Cuti Saya
                        @else
                            <i class="ti ti-file-certificate me-1" style="color: #102d52;"></i> Daftar Izin &amp; Cuti GTK
                        @endif
                    </h2>
                </div>
                <div class="text-secondary mt-1">
                    @if($isPersonalScope)
                        Riwayat pengajuan izin, sakit, cuti, atau dinas luar atas nama Anda (<strong>{{ auth()->user()->name }}</strong>).
                    @else
                        Monitoring dan persetujuan resmi Perizinan &amp; Cuti Guru serta Tenaga Kependidikan (Sakit, Izin, Cuti, dan Dinas Luar).
                    @endif
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
                @if(!$isPersonalScope)
                    <form action="{{ route('attendance.gtk-permits.sync-piket') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-info" title="Sinkronkan perizinan yang dicatat oleh petugas piket">
                            <i class="ti ti-refresh me-1"></i> Sinkron Jurnal Piket
                        </button>
                    </form>
                    <a href="{{ route('attendance.report.teachers') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-report me-1"></i> Rekap Presensi GTK
                    </a>
                @endif
                
                <button type="button" class="btn btn-sm fw-bold {{ $isPersonalScope ? 'btn-indigo text-white' : 'btn-primary' }}" 
                        style="{{ $isPersonalScope ? 'background-color: #4f46e5; border-color: #4f46e5;' : '' }}"
                        onclick="openCreatePermitModal()">
                    <i class="ti ti-plus me-1"></i> {{ $isPersonalScope ? '+ Ajukan Izin / Cuti Saya' : '+ Catat Izin / Cuti GTK' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-circle-check fs-2 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Statistics Cards (1 Baris: Menunggu, Izin Hari Ini, Total Periode) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-warning-lt text-warning avatar shadow-sm me-3">
                            <i class="ti ti-clock fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small fw-medium">Menunggu Persetujuan</div>
                            <div class="fw-bold fs-2 text-warning">{{ $pendingPermitsCount ?? 0 }} <span class="fs-4 fw-normal text-muted">Pengajuan</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #2563eb !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-primary text-white avatar shadow-sm me-3">
                            <i class="ti ti-calendar-check fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small fw-medium">Izin Aktif Hari Ini</div>
                            <div class="fw-bold fs-2 text-primary">{{ $todayPermitsCount ?? 0 }} <span class="fs-4 fw-normal text-muted">GTK</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #16a34a !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <span class="bg-success text-white avatar shadow-sm me-3">
                            <i class="ti ti-file-certificate fs-2"></i>
                        </span>
                        <div>
                            <div class="text-muted small fw-medium">Total Izin Periode Ini</div>
                            <div class="fw-bold fs-2 text-success">{{ $permits->total() }} <span class="fs-4 fw-normal text-muted">Surat</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card & Quick Tabs -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-secondary small fw-bold text-uppercase">Mode Tampilan:</span>
                    <a href="{{ route('attendance.gtk-permits.index', ['scope' => $scope, 'date' => now()->toDateString()]) }}" 
                       class="btn btn-sm {{ $date === now()->toDateString() ? 'btn-teal text-white fw-bold' : 'btn-outline-teal' }}">
                        <i class="ti ti-calendar-check me-1"></i> Izin Hari Ini ({{ \Carbon\Carbon::today()->translatedFormat('d M Y') }})
                        @if($todayPermitsCount > 0)
                            <span class="badge {{ $date === now()->toDateString() ? 'bg-white text-teal' : 'bg-teal text-white' }} ms-1">{{ $todayPermitsCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('attendance.gtk-permits.index', ['scope' => $scope, 'month' => now()->format('Y-m')]) }}" 
                       class="btn btn-sm {{ (!$date && $month === now()->format('Y-m')) ? 'btn-primary text-white fw-bold' : 'btn-outline-primary' }}">
                        <i class="ti ti-calendar me-1"></i> Bulan Ini ({{ \Carbon\Carbon::now()->translatedFormat('F Y') }})
                    </a>
                    <a href="{{ route('attendance.gtk-permits.index', ['scope' => $scope, 'month' => 'all']) }}" 
                       class="btn btn-sm {{ (!$date && $month === 'all') ? 'btn-secondary text-white fw-bold' : 'btn-outline-secondary' }}">
                        <i class="ti ti-list me-1"></i> Semua Data
                    </a>
                </div>

                @if($date)
                    <div class="text-teal small fw-semibold">
                        <i class="ti ti-filter me-1"></i> Memfilter izin aktif pada tanggal: <strong>{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</strong>
                    </div>
                @elseif($month && $month !== 'all')
                    <div class="text-secondary small">
                        <i class="ti ti-calendar-stats me-1"></i> Periode bulan: <strong>{{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</strong>
                    </div>
                @endif
            </div>

            <form action="{{ route('attendance.gtk-permits.index') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1">Pilih Tanggal</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" title="Filter izin aktif pada tanggal spesifik">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1">Atau Bulan</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1">Kategori</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="all">Semua Kategori</option>
                        @foreach($typeDefinitions as $tKey => $tDef)
                            <option value="{{ $tKey }}" {{ $type === $tKey ? 'selected' : '' }}>{{ $tDef['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @if(!$isPersonalScope)
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">Nama GTK</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Semua Guru &amp; Staf</option>
                        @foreach($personnel as $p)
                            <option value="{{ $p->id }}" {{ $userId == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->isStaff() ? 'Staf' : 'Guru' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="{{ $isPersonalScope ? 'col-md-3' : 'col-md-2' }}">
                    <label class="form-label small fw-bold mb-1">Status Persetujuan</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">Semua Status</option>
                        @foreach($statusDefinitions as $sKey => $sDef)
                            <option value="{{ $sKey }}" {{ $status === $sKey ? 'selected' : '' }}>{{ $sDef['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="{{ $isPersonalScope ? 'col-md-2' : 'col-md-2' }}">
                    <label class="form-label small fw-bold mb-1">Pencarian</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Alasan / catatan..." value="{{ $search }}">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1 mt-auto">
                    <button type="submit" class="btn btn-sm btn-primary w-100" title="Terapkan Filter">
                        <i class="ti ti-filter"></i>
                    </button>
                    @if($date || ($month && $month !== now()->format('Y-m')) || $type !== 'all' || $status !== 'all' || (!$isPersonalScope && $userId) || $search)
                        <a href="{{ route('attendance.gtk-permits.index', ['scope' => $scope]) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                            <i class="ti ti-refresh"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">No</th>
                        <th>Nama GTK</th>
                        <th>Jenis Izin</th>
                        <th>Rentang Tanggal</th>
                        <th>Durasi</th>
                        <th>Alasan &amp; Keterangan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Lampiran</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $canApprove = auth()->user()->isPrincipal() || auth()->user()->isSuperAdmin();
                    @endphp
                    @forelse($permits as $idx => $permit)
                        <tr>
                            <td class="text-center text-muted">{{ $permits->firstItem() + $idx }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $permit->user?->name ?? '-' }}</div>
                                <div class="text-muted small">
                                    <span class="badge {{ $permit->user?->isStaff() ? 'bg-secondary-lt' : 'bg-blue-lt' }} py-0 px-1" style="font-size: 0.7rem;">
                                        {{ $permit->user?->isStaff() ? 'Staf TU' : 'Guru' }}
                                    </span>
                                    {{ $permit->user?->formatted_id ? '• '.$permit->user->formatted_id : '' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $permit->type_badge }} fw-semibold">
                                    <i class="{{ $permit->type_icon }} me-1"></i> {{ $permit->type_label }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $permit->formatted_period }}</div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    {{ $permit->start_date->translatedFormat('l') }} s/d {{ $permit->end_date->translatedFormat('l') }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-bold px-2 py-1">
                                    {{ $permit->duration_days }} Hari
                                </span>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium">{{ $permit->reason }}</div>
                                @if($permit->notes)
                                    <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                        <i class="ti ti-notes me-1"></i>{{ $permit->notes }}
                                    </div>
                                @endif
                                @if($permit->approval_note)
                                    <div class="text-danger small mt-1" style="font-size: 0.75rem;">
                                        <i class="ti ti-message-circle me-1"></i>Catatan KS: {{ $permit->approval_note }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $permit->status_badge }} px-2 py-1">
                                    {{ $permit->status_label }}
                                </span>
                                @if($permit->approver)
                                    <div class="text-muted small mt-0.5" style="font-size: 0.68rem;" title="Disetujui/Ditolak oleh">
                                        {{ $permit->approver->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($permit->document_path)
                                    <a href="{{ $permit->document_url }}" target="_blank" class="btn btn-sm btn-ghost-primary px-2" title="Lihat Dokumen / Surat">
                                        <i class="ti ti-paperclip me-1"></i> Berkas
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @if($canApprove && $permit->isPending())
                                        <!-- Tombol Setujui -->
                                        <form action="{{ route('attendance.gtk-permits.approve', $permit) }}" method="POST" class="d-inline" onsubmit="return confirm('Setujui pengajuan izin ini?')">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" title="Setujui Izin">
                                                <i class="ti ti-check me-0.5"></i> Setujui
                                            </button>
                                        </form>
                                        <!-- Tombol Tolak Modal -->
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal({{ $permit->id }}, '{{ addslashes($permit->user?->name ?? 'GTK') }}')" title="Tolak Izin">
                                            <i class="ti ti-x me-0.5"></i> Tolak
                                        </button>
                                    @endif

                                    @if($canApprove || (auth()->id() === $permit->user_id && $permit->isPending()))
                                        <button type="button" class="btn btn-outline-secondary" onclick='openEditPermitModal(@json($permit))' title="Edit Izin">
                                            <i class="ti ti-pencil"></i>
                                        </button>
                                        <form action="{{ route('attendance.gtk-permits.destroy', $permit) }}" method="POST" onsubmit="return confirm('Hapus / batalkan pencatatan izin ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus / Batalkan">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="ti ti-file-off fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada data perizinan atau cuti GTK pada filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($permits->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between py-2">
                {{ $permits->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ================= MODAL TAMBAH / EDIT IZIN GTK ================= -->
<div class="modal fade" id="modalGtkPermit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="formGtkPermit" method="POST" action="" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <div id="permitMethodSpoof"></div>

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalGtkPermitTitle">
                    <i class="ti ti-file-plus me-1"></i> Catat Izin / Cuti GTK
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 px-3 mb-3 small">
                    <i class="ti ti-info-circle me-1"></i> Pencatatan ini berlaku untuk <strong>Guru maupun Staf TU/Karyawan</strong>. Pada tanggal yang dipilih, sistem otomatis menandai status kehadiran dan menggugurkan kewajiban scan (tidak akan dihitung Alfa).
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        @if(auth()->user()->canSubmitPermitForOthers() && !$isPersonalScope)
                            <label class="form-label required fw-bold">Pilih Guru / Staf (GTK)</label>
                            <select name="user_id" id="input_user_id" class="form-select" required>
                                <option value="">-- Pilih Guru atau Staf --</option>
                                @foreach($personnel as $p)
                                    <option value="{{ $p->id }}">
                                        [{{ $p->isStaff() ? 'Staf' : 'Guru' }}] {{ $p->name }} {{ $p->formatted_id ? '('.$p->formatted_id.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <label class="form-label fw-bold">Pemohon (GTK Pribadi)</label>
                            <input type="hidden" name="user_id" id="input_user_id" value="{{ auth()->id() }}">
                            <input type="text" class="form-control bg-light text-dark fw-bold" value="{{ auth()->user()->name }} ({{ auth()->user()->isStaff() ? 'Staf TU' : 'Guru' }})" readonly>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Kategori Perizinan</label>
                        <select name="permission_type" id="input_permission_type" class="form-select" required>
                            @foreach($typeDefinitions as $tKey => $tDef)
                                <option value="{{ $tKey }}">{{ $tDef['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Tanggal Mulai</label>
                        <input type="date" name="start_date" id="input_start_date" class="form-control" required onchange="calculateDuration()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold d-flex justify-content-between">
                            <span>Tanggal Selesai</span>
                            <span class="badge bg-light text-primary border" id="badgeDuration">1 Hari</span>
                        </label>
                        <input type="date" name="end_date" id="input_end_date" class="form-control" placeholder="Kosongkan jika hanya 1 hari" onchange="calculateDuration()">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Alasan / Keperluan Izin</label>
                    <textarea name="reason" id="input_reason" class="form-control" rows="2" placeholder="Contoh: Sakit Thypus (Dirawat di RS) / Rapat Koordinasi MGMP Luar Kota / Cuti Tahunan / Acara Keluarga" required></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Unggah Dokumen Lampiran (Opsional)</label>
                        <input type="file" name="document" id="input_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text small text-muted">Format: PDF, JPG, PNG (Maksimal 5MB). Contoh: Surat Dokter, Surat Tugas, dll.</div>
                        <div id="currentDocumentPreview" class="mt-1 small" style="display: none;"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="input_notes" class="form-control" rows="2" placeholder="Catatan instruksi penggantian tugas / koordinasi"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold" id="btnSubmitPermit">
                    <i class="ti ti-check me-1"></i> Simpan Data Izin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function calculateDuration() {
        const start = document.getElementById('input_start_date').value;
        const end = document.getElementById('input_end_date').value || start;
        const badge = document.getElementById('badgeDuration');

        if (start && end) {
            const d1 = new Date(start);
            const d2 = new Date(end);
            const diffTime = d2 - d1;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            if (diffDays > 0) {
                badge.textContent = `${diffDays} Hari`;
            } else {
                badge.textContent = `Tanggal Tidak Valid`;
            }
        }
    }

    function openCreatePermitModal() {
        const form = document.getElementById('formGtkPermit');
        form.action = "{{ route('attendance.gtk-permits.store') }}";
        document.getElementById('permitMethodSpoof').innerHTML = '';
        document.getElementById('modalGtkPermitTitle').innerHTML = '<i class="ti ti-file-plus me-1"></i> Catat Izin / Cuti GTK';
        document.getElementById('btnSubmitPermit').innerHTML = '<i class="ti ti-check me-1"></i> Simpan Data Izin';

        document.getElementById('input_user_id').value = '';
        document.getElementById('input_permission_type').value = 'sakit';
        const todayStr = new Date().toISOString().split('T')[0];
        document.getElementById('input_start_date').value = todayStr;
        document.getElementById('input_end_date').value = todayStr;
        document.getElementById('input_reason').value = '';
        document.getElementById('input_notes').value = '';
        document.getElementById('currentDocumentPreview').style.display = 'none';

        calculateDuration();
        new bootstrap.Modal(document.getElementById('modalGtkPermit')).show();
    }

    function openEditPermitModal(permit) {
        const form = document.getElementById('formGtkPermit');
        form.action = `/presensi/izin-cuti-gtk/${permit.id}`;
        document.getElementById('permitMethodSpoof').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('modalGtkPermitTitle').innerHTML = '<i class="ti ti-pencil me-1"></i> Edit Izin / Cuti GTK';
        document.getElementById('btnSubmitPermit').innerHTML = '<i class="ti ti-device-floppy me-1"></i> Simpan Perubahan';

        document.getElementById('input_user_id').value = permit.user_id;
        document.getElementById('input_permission_type').value = permit.permission_type;
        document.getElementById('input_start_date').value = String(permit.start_date).substring(0, 10);
        document.getElementById('input_end_date').value = String(permit.end_date).substring(0, 10);
        document.getElementById('input_reason').value = permit.reason || '';
        document.getElementById('input_notes').value = permit.notes || '';

        const docPreview = document.getElementById('currentDocumentPreview');
        if (permit.document_path) {
            docPreview.style.display = 'block';
            docPreview.innerHTML = `<span class="text-success"><i class="ti ti-paperclip me-1"></i> Berkas terlampir saat ini. Unggah berkas baru hanya jika ingin menggantinya.</span>`;
        } else {
            docPreview.style.display = 'none';
        }

        calculateDuration();
        new bootstrap.Modal(document.getElementById('modalGtkPermit')).show();
    }

    function openRejectModal(permitId, userName) {
        document.getElementById('rejectPermitName').textContent = userName;
        document.getElementById('formRejectPermit').action = `/presensi/izin-cuti-gtk/${permitId}/tolak`;
        document.getElementById('reject_approval_note').value = '';
        new bootstrap.Modal(document.getElementById('modalRejectPermit')).show();
    }
</script>

<!-- Modal Tolak Izin GTK -->
<div class="modal fade" id="modalRejectPermit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formRejectPermit" method="POST" action="" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-x me-1"></i> Tolak Pengajuan Izin GTK
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-2">
                    Anda akan menolak pengajuan izin untuk <strong class="text-dark" id="rejectPermitName">-</strong>.
                </p>
                <div class="mb-2">
                    <label class="form-label required small fw-bold">Alasan Penolakan</label>
                    <textarea name="approval_note" id="reject_approval_note" class="form-control" rows="3" placeholder="Tuliskan alasan penolakan izin di sini..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm fw-bold">
                    <i class="ti ti-x me-1"></i> Konfirmasi Tolak Izin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
