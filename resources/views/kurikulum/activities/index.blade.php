@extends('layouts.app')

@section('title', 'Agenda Kegiatan Sekolah')

@section('content')
<!-- Breadcrumbs -->
<div class="mb-3">
    <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><span class="text-muted">Kurikulum</span></li>
        <li class="breadcrumb-item active" aria-current="page"><a href="#">Agenda Kegiatan</a></li>
    </ol>
</div>

<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $actActions = '<a href="' . route('kurikulum.calendar.index', ['academic_year' => $academicYear]) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-calendar-event me-1 text-info"></i> Buka Kalender Sekolah</a> ';
    if (auth()->user()->canViewAllSchedules()) {
        $actActions .= '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" onclick="openCreateActivityModal()"><i class="ti ti-plus me-1"></i> Tambah Agenda Baru</button>';
    }
@endphp
@include('layouts._hero_header', [
    'title' => 'Agenda Kegiatan Sekolah',
    'subtitle' => 'Daftar & monitoring acuan seluruh agenda kegiatan serta program kerja sekolah (Kurikulum, Kesiswaan, Humas, PKL, Asesmen & Ujian, Supervisi) TP ' . $academicYear . '.',
    'icon' => 'ti ti-calendar-star',
    'category' => 'KURIKULUM & AGENDA',
    'academicYear' => $academicYear,
    'actions' => $actActions
])

<!-- Scheduling Overview & Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-primary text-white avatar shadow-sm me-3">
                        <i class="ti ti-list-check fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Total Acuan Kegiatan</div>
                        <div class="fw-bold fs-2 text-dark">{{ $totalActivities }} <span class="fs-4 fw-normal text-muted">Agenda</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #16a34a !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-success text-white avatar shadow-sm me-3">
                        <i class="ti ti-calendar-check fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Sudah Terjadwal (TP {{ $academicYear }})</div>
                        <div class="fw-bold fs-2 text-success">{{ $scheduledCount }} <span class="fs-4 fw-normal text-muted">Kegiatan</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-warning text-white avatar shadow-sm me-3">
                        <i class="ti ti-calendar-x fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Belum Terjadwal</div>
                        <div class="fw-bold fs-2 text-warning">{{ $unscheduledCount }} <span class="fs-4 fw-normal text-muted">Kegiatan</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="text-muted small fw-medium mb-1">Capaian Penjadwalan TP {{ $academicYear }}</div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold fs-3 text-primary">{{ $percentageScheduled }}%</span>
                    <span class="small text-muted">{{ $scheduledCount }}/{{ $totalActivities }} Terpenuhi</span>
                </div>
                <div class="progress progress-sm">
                    <div class="progress-bar bg-primary" style="width: {{ $percentageScheduled }}%" role="progressbar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="{{ route('kurikulum.activities.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Cari nama kegiatan, kode, sasaran peserta..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">-- Semua Kategori Kegiatan --</option>
                    @foreach($categories as $key => $cat)
                        <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>
                            {{ $cat['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>-- Semua Status Penjadwalan --</option>
                    <option value="terjadwal" {{ $statusFilter === 'terjadwal' ? 'selected' : '' }}>✓ Sudah Terjadwal di Kalender</option>
                    <option value="belum_terjadwal" {{ $statusFilter === 'belum_terjadwal' ? 'selected' : '' }}>✕ Belum Terjadwal (Perlu Dijadwalkan)</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || ($category && $category !== 'all') || ($statusFilter && $statusFilter !== 'all'))
                    <a href="{{ route('kurikulum.activities.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                        <i class="ti ti-rotate-2"></i>
                    </a>
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
                    <th style="min-width: 320px;">Nama Kegiatan</th>
                    <th style="width: 170px;">Sasaran Peserta</th>
                    <th style="width: 260px;">Status Penjadwalan (TP {{ $academicYear }})</th>
                    <th style="width: 140px;">Sifat &amp; Durasi</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $act)
                <tr>
                    <td>
                        <div class="d-flex align-items-start">
                            <span class="badge-dot me-2 rounded-circle flex-shrink-0 mt-2" style="background-color: {{ $act->category_color }}; width: 9px; height: 9px;"></span>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.92rem !important;">
                                    <a href="javascript:void(0)" onclick='openPreviewModal(@json($act), @json($act->scheduled_event))' class="text-dark text-decoration-none" title="Klik untuk melihat preview">
                                        {{ $act->name }}@if($act->code) <span class="text-secondary fw-normal">({{ $act->code }})</span>@endif
                                    </a>
                                </div>
                                <div class="small text-muted d-flex align-items-center gap-1 flex-wrap mt-1" style="font-size: 0.76rem;">
                                    @if($act->code)
                                        <span class="fw-bold text-secondary">{{ $act->code }}</span>
                                        <span class="text-muted opacity-50">|</span>
                                    @endif
                                    <span class="badge {{ $act->category_badge }} fw-semibold px-2 py-0" style="font-size: 0.7rem;">
                                        {{ $act->category_label }}
                                    </span>
                                    @if($act->is_kaldik)
                                        <span class="badge bg-red-lt text-red fw-bold px-1 py-0" style="font-size: 0.68rem;">
                                            Kaldik
                                        </span>
                                    @endif
                                    @if($act->description)
                                        <span class="text-muted opacity-50">|</span>
                                        <span class="text-muted text-truncate d-inline-block" style="max-width: 320px;" title="{{ $act->description }}">
                                            {{ $act->description }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="small text-dark fw-medium" style="font-size: 0.8rem;">
                            {{ $act->target_participant ?: 'Seluruh Sekolah' }}
                        </div>
                    </td>
                    <td>
                        @if($act->is_scheduled)
                            <div>
                                <span class="badge bg-success-lt text-success fw-bold border border-success mb-1 px-2 py-0" style="font-size: 0.7rem;">
                                    <i class="ti ti-calendar-check me-1"></i> Sudah Terjadwal
                                </span>
                                @if($act->scheduled_event)
                                    <div class="fw-bold text-dark" style="font-size: 0.78rem;">
                                        <i class="ti ti-calendar me-1 text-primary"></i>
                                        {{ $act->scheduled_event->start_date->translatedFormat('d M Y') }}
                                        @if($act->scheduled_event->end_date && $act->scheduled_event->end_date->gt($act->scheduled_event->start_date))
                                            s/d {{ $act->scheduled_event->end_date->translatedFormat('d M Y') }}
                                        @endif
                                    </div>
                                    @if($act->scheduled_event->location)
                                        <div class="small text-muted text-truncate" style="max-width: 220px; font-size: 0.72rem;">
                                            <i class="ti ti-map-pin me-1 text-secondary"></i> {{ $act->scheduled_event->location }}
                                        </div>
                                    @endif
                                    <div class="mt-1 d-flex gap-1 flex-wrap align-items-center">
                                        @if(auth()->user()->canViewAllSchedules())
                                            <button type="button" class="btn btn-outline-primary py-0 px-2 fw-semibold"
                                                    style="font-size: 0.68rem; line-height: 1.5;"
                                                    onclick='openEditScheduleModal(@json($act), @json($act->scheduled_event))' 
                                                    title="Ubah Tanggal atau Detail Jadwal">
                                                <i class="ti ti-pencil me-1"></i> Ubah Jadwal
                                            </button>
                                        @endif
                                        <a href="{{ route('kurikulum.calendar.index', ['academic_year' => $academicYear, 'date' => $act->scheduled_event->start_date->format('Y-m-d')]) }}" 
                                           class="btn btn-ghost-secondary py-0 px-1"
                                           style="font-size: 0.68rem; line-height: 1.5;" 
                                           target="_blank" title="Lihat di Kalender Kegiatan">
                                            <i class="ti ti-calendar-event me-1"></i> Kalender
                                        </a>
                                    </div>
                                @elseif($act->supervision_count > 0)
                                    <div class="fw-bold text-dark" style="font-size: 0.78rem;">
                                        <i class="ti ti-clipboard-check me-1 text-indigo"></i>
                                        {{ $act->supervision_count }} Jadwal Supervisi
                                    </div>
                                    @if(auth()->user()->canViewAllSchedules())
                                    <div class="mt-1">
                                        <a href="{{ route('schedules.index') }}" 
                                           class="btn btn-outline-indigo py-0 px-2 fw-semibold"
                                           style="font-size: 0.68rem; line-height: 1.5;">
                                            <i class="ti ti-list-details me-1"></i> Buka Jadwal Supervisi
                                        </a>
                                    </div>
                                    @endif
                                @endif
                            </div>
                        @else
                            <div>
                                <span class="badge bg-warning-lt text-warning fw-bold border border-warning mb-1 px-2 py-0" style="font-size: 0.7rem;">
                                    <i class="ti ti-calendar-x me-1"></i> Belum Terjadwal
                                </span>
                                @if(auth()->user()->canViewAllSchedules())
                                <div class="mt-1">
                                    <button type="button" class="btn btn-primary py-0 px-2 fw-semibold shadow-none" 
                                            style="font-size: 0.68rem; line-height: 1.5;"
                                            onclick='openQuickScheduleModal(@json($act))'>
                                        <i class="ti ti-calendar-plus me-1"></i> Jadwalkan
                                    </button>
                                </div>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="small" style="font-size: 0.78rem;">
                            @if($act->is_mandatory)
                                <span class="badge bg-dark-lt text-dark fw-semibold px-2 py-0" style="font-size: 0.68rem;">Wajib</span>
                            @else
                                <span class="badge bg-secondary-lt text-muted px-2 py-0" style="font-size: 0.68rem;">Opsional</span>
                            @endif
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">&plusmn; {{ $act->default_duration_days }} Hari</div>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-icon btn-ghost-primary" 
                                    onclick='openPreviewModal(@json($act), @json($act->scheduled_event))' title="Preview Detail Kegiatan">
                                <i class="ti ti-eye fs-3"></i>
                            </button>
                            @if(auth()->user()->canViewAllSchedules())
                            <button type="button" class="btn btn-sm btn-icon btn-ghost-primary" 
                                    onclick='openEditActivityModal(@json($act))' title="Edit Kegiatan">
                                <i class="ti ti-edit fs-3"></i>
                            </button>
                            <form action="{{ route('kurikulum.activities.destroy', $act->id) }}" method="POST" 
                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus kegiatan \'{{ $act->name }}\'?')" 
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Hapus Kegiatan">
                                    <i class="ti ti-trash fs-3"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="empty">
                            <div class="empty-icon text-muted">
                                <i class="ti ti-calendar-off fs-1"></i>
                            </div>
                            <p class="empty-title">Tidak ada kegiatan sekolah ditemukan</p>
                            <p class="empty-subtitle text-muted">
                                Coba sesuaikan kata kunci pencarian atau filter status penjadwalan.
                            </p>
                            @if(auth()->user()->canViewAllSchedules())
                            <div class="empty-action">
                                <button type="button" class="btn btn-primary" onclick="openCreateActivityModal()">
                                    <i class="ti ti-plus me-1"></i> Tambah Kegiatan Baru
                                </button>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(auth()->user()->canViewAllSchedules())
<!-- ================= MODAL 1: FORM TAMBAH / EDIT KEGIATAN ================= -->
<div class="modal fade" id="modalActivityForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="formActivity" method="POST" action="{{ route('kurikulum.activities.store') }}" class="modal-content border-0 shadow">
            @csrf
            <div id="methodSpoofing"></div>
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalActivityTitle">
                    <i class="ti ti-calendar-plus me-1"></i> Tambah Kegiatan Sekolah
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. INFORMASI DASAR -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label required fw-bold">Nama / Judul Kegiatan</label>
                        <input type="text" name="name" id="input_name" class="form-control" 
                               placeholder="Contoh: Praktik Kerja Lapangan (PKL) / Kunjungan Industri (KI) / Ujian Sekolah" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kode / Singkatan</label>
                        <input type="text" name="code" id="input_code" class="form-control" 
                               placeholder="Contoh: PKL / KI / UKK / ANBK">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Kategori Kegiatan</label>
                        <select name="category" id="input_category" class="form-select" required onchange="onCategoryChange(this.value)">
                            <optgroup label="-- Kategori Kegiatan (Data Pokok) --">
                                @foreach($eventCategories as $key => $cat)
                                    @if(empty($cat['is_kaldik_code']))
                                        <option value="{{ $key }}" data-color="{{ $cat['color'] }}">{{ $cat['label'] }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                            <optgroup label="-- Kode Khusus Kaldik (Libur / Dinas) --">
                                @foreach($eventCategories as $key => $cat)
                                    @if(!empty($cat['is_kaldik_code']))
                                        <option value="{{ $key }}" data-color="{{ $cat['color'] }}">{{ $cat['label'] }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Lokasi / Tempat (Opsional)</label>
                        <input type="text" name="location" id="input_location" class="form-control" placeholder="Contoh: Bengkel TKRO / Lab RPL / Aula / DUDI">
                    </div>
                </div>

                <!-- 2. WAKTU PELAKSANAAN & STATUS LIBUR -->
                <div class="card border border-primary-subtle shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2 pb-1 border-bottom border-primary-subtle">
                            <i class="ti ti-calendar-time text-primary fs-3 me-2"></i>
                            <h6 class="mb-0 fw-bold text-dark">Waktu Pelaksanaan &amp; Status Libur</h6>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">Tanggal Mulai (Opsional untuk Penjadwalan)</label>
                                <input type="date" name="start_date" id="input_start_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">Tanggal Selesai (Jika Berkelanjutan)</label>
                                <input type="date" name="end_date" id="input_end_date" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="p-2 bg-white rounded border">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="input_is_holiday" name="is_holiday" value="1" onchange="onHolidayToggle(this.checked)">
                                        <label class="form-check-label fw-bold small text-danger" for="input_is_holiday">
                                            <i class="ti ti-calendar-off me-1"></i> Libur Sekolah Penuh (Bebas KBM &amp; Presensi)
                                        </label>
                                    </div>
                                    <div class="text-muted small ps-4 mt-1" style="font-size: 0.72rem; line-height: 1.2;">
                                        Seluruh warga sekolah bebas KBM dan bebas presensi. Blok peserta kegiatan otomatis disembunyikan.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTAINER PESERTA & PIHAK TIDAK TERLIBAT (Disembunyikan jika Libur Sekolah Penuh) -->
                <div id="section_participants_and_impacts">
                    <!-- 3. PESERTA KEGIATAN -->
                    <div class="card border border-info-subtle shadow-none bg-azure-lt mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3 pb-1 border-bottom border-info-subtle">
                                <i class="ti ti-users-group text-info fs-3 me-2"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Peserta Kegiatan (Yang Terlibat Langsung)</h6>
                                    <div class="text-muted small" style="font-size: 0.73rem;">GTK dan siswa yang ditugaskan/terlibat langsung dalam pelaksanaan kegiatan.</div>
                                </div>
                            </div>

                            <!-- A. GTK yang Ditugaskan -->
                            <div class="p-3 bg-white rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div class="fw-bold text-dark small d-flex align-items-center">
                                        <i class="ti ti-user-check text-success me-1 fs-3"></i> GTK yang Ditugaskan (Panitia / Pengawas / Pembimbing)
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="toggleSelectGtk('teachers')">
                                            <i class="ti ti-check me-1"></i> Semua Guru
                                        </button>
                                        <button type="button" class="btn btn-outline-info py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="toggleSelectGtk('staff')">
                                            <i class="ti ti-check me-1"></i> Semua Staf
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="toggleSelectGtk('none')">
                                            Kosongkan
                                        </button>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Status Mengajar GTK Terlibat:</label>
                                        <select name="teacher_impact" id="input_teacher_impact" class="form-select form-select-sm">
                                            <option value="bebas_mengajar_tugas">Gugur Kewajiban Mengajar (Tugas Khusus / Panitia / Pengawas)</option>
                                            <option value="normal">Tetap Mengajar Reguler Sesuai Jadwal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Jam Pelaksanaan Tugas GTK (WIB):</label>
                                        <div class="input-group input-group-sm">
                                            <input type="time" name="gtk_checkin_time" id="input_gtk_checkin_time" class="form-control form-control-sm" value="07:00">
                                            <span class="input-group-text">s/d</span>
                                            <input type="time" name="gtk_checkout_time" id="input_gtk_checkout_time" class="form-control form-control-sm" value="13:30">
                                        </div>
                                    </div>
                                </div>

                                <!-- Checklist GTK -->
                                <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
                                    <div class="row g-2">
                                        @foreach($allGtk as $gtk)
                                            <div class="col-md-6">
                                                <label class="form-check mb-1">
                                                    <input class="form-check-input user-assign-cb {{ $gtk->isStaff() ? 'user-staff-cb' : 'user-teacher-cb' }}" 
                                                           type="checkbox" name="user_ids[]" value="{{ $gtk->id }}" onchange="onGtkCheckboxChange()">
                                                    <span class="form-check-label small text-truncate d-block" title="{{ $gtk->name }} ({{ $gtk->position ?? ($gtk->isStaff() ? 'Staf' : 'Guru') }})">
                                                        {{ $gtk->name }}
                                                        <span class="badge {{ $gtk->isStaff() ? 'bg-secondary-lt' : 'bg-primary-lt' }} ms-1" style="font-size: 0.65rem;">
                                                            {{ $gtk->isStaff() ? 'Staf' : 'Guru' }}
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- B. Siswa yang Terlibat -->
                            <div class="p-3 bg-white rounded-3 border">
                                <div class="fw-bold text-dark small mb-2 d-flex align-items-center">
                                    <i class="ti ti-school text-primary me-1 fs-3"></i> Siswa yang Terlibat Langsung
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-12" id="col_target_audience">
                                        <label class="form-label small fw-bold text-muted mb-1">Pilih Kelompok Siswa Terlibat:</label>
                                        <select name="target_audience" id="input_target_audience" class="form-select form-select-sm" onchange="onTargetAudienceChange(this.value)">
                                            <option value="guru_staf" selected>Tidak Ada Siswa Terlibat (Hanya GTK)</option>
                                            <option value="semua">Semua Siswa (Seluruh Sekolah)</option>
                                            <option value="tingkat">Tingkat Tertentu (Contoh: Hanya Kelas XII)</option>
                                            <option value="rombel">Rombel Tertentu (Pilih Kelas Spesifik)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="box_student_time" style="display: none;">
                                        <label class="form-label small fw-bold text-muted mb-1">Jam Pelaksanaan Siswa (WIB):</label>
                                        <div class="input-group input-group-sm">
                                            <input type="time" name="student_start_time" id="input_student_start_time" class="form-control form-control-sm" value="07:00">
                                            <span class="input-group-text">s/d</span>
                                            <input type="time" name="student_end_time" id="input_student_end_time" class="form-control form-control-sm" value="12:00">
                                        </div>
                                    </div>
                                </div>

                                <!-- Opsi Tingkat Tertentu -->
                                <div class="mb-2" id="box_target_tingkat" style="display: none;">
                                    <label class="form-label small fw-bold text-dark mb-1">Pilih Tingkat yang Terlibat:</label>
                                    <div class="d-flex gap-3 flex-wrap bg-light p-2 rounded border">
                                        @foreach($gradeLevels as $gl)
                                            <label class="form-check form-check-inline mb-0">
                                                <input class="form-check-input grade-level-cb" type="checkbox" name="target_grade_levels[]" value="{{ $gl->level }}">
                                                <span class="form-check-label small fw-semibold">Tingkat {{ $gl->name }} (Kelas {{ $gl->level }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Opsi Rombel Tertentu -->
                                <div class="mb-2" id="box_target_rombel" style="display: none;">
                                    <label class="form-label small fw-bold text-dark mb-1">Pilih Rombel Kelas yang Terlibat:</label>
                                    <div class="bg-light p-2 rounded border" style="max-height: 120px; overflow-y: auto;">
                                        <div class="row g-1">
                                            @foreach($classrooms as $cls)
                                                <div class="col-md-4 col-sm-6">
                                                    <label class="form-check mb-1">
                                                        <input class="form-check-input classroom-target-cb" type="checkbox" name="target_classroom_ids[]" value="{{ $cls->id }}">
                                                        <span class="form-check-label small text-truncate d-block" title="{{ $cls->name }}">
                                                            {{ $cls->name }}
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2 mt-1" id="box_student_learning_impact" style="display: none;">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">Status KBM bagi Siswa Terlibat:</label>
                                        <select name="learning_impact" id="input_learning_impact" class="form-select form-select-sm" onchange="evaluateStaffAndDisengagedVisibility()">
                                            <option value="bebas_kbm_kegiatan">Bebas KBM (Ujian / PKL / KI / Gladi / Dispensasi Tugas Khusus)</option>
                                            <option value="pembelajaran_daring">Pembelajaran Daring</option>
                                            <option value="normal">KBM Normal (Sesuai Jadwal Penuh)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. GURU DAN SISWA YANG TIDAK TERLIBAT KEGIATAN -->
                    <div class="card border border-warning-subtle shadow-none bg-yellow-lt mb-3" id="section_disengaged_impacts">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3 pb-1 border-bottom border-warning-subtle">
                                <i class="ti ti-shield-alert text-warning fs-3 me-2"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Guru, Staf, &amp; Siswa yang TIDAK Terlibat Kegiatan</h6>
                                    <div class="text-muted small" style="font-size: 0.73rem;">Penetapan status bagi warga sekolah yang tidak bertugas/terlibat dalam agenda di atas.</div>
                                </div>
                            </div>

                            <!-- Baris 1: Guru yang Tidak Terlibat -->
                            <div class="p-2 bg-white rounded-3 border mb-2" id="row_disengaged_teachers">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <span class="fw-bold text-dark small"><i class="ti ti-user-x text-primary me-1"></i> Guru Lain:</span>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="disengaged_teacher_impact" id="input_disengaged_teacher_impact" class="form-select form-select-sm" onchange="onDisengagedTeacherImpactChange(this.value)">
                                            <option value="masuk_normal">Masuk Normal</option>
                                            <option value="pulang_cepat">Pulang Cepat</option>
                                            <option value="libur">Libur</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5" id="box_disengaged_teacher_period" style="display: none;">
                                        <select name="disengaged_teacher_max_period" id="input_disengaged_teacher_max_period" class="form-select form-select-sm">
                                            <option value="">-- Pulang Jam Pelajaran Ke- --</option>
                                            @foreach($periodMap as $pNum => $pData)
                                                <option value="{{ $pNum }}">{{ $pData['label'] }} ({{ $pData['time'] }} WIB)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Baris 2: Staf TU yang Tidak Terlibat -->
                            <div class="p-2 bg-white rounded-3 border mb-2" id="row_disengaged_staff">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <span class="fw-bold text-dark small"><i class="ti ti-briefcase text-secondary me-1"></i> Staf TU:</span>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="disengaged_staff_impact" id="input_disengaged_staff_impact" class="form-select form-select-sm" onchange="onDisengagedStaffImpactChange(this.value)">
                                            <option value="masuk_normal">Masuk Normal</option>
                                            <option value="pulang_cepat" id="opt_staff_pulang_cepat">Pulang Cepat</option>
                                            <option value="libur" id="opt_staff_libur">Libur</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5" id="box_disengaged_staff_time" style="display: none;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Pulang:</span>
                                            <input type="time" name="disengaged_staff_dismissal_time" id="input_disengaged_staff_dismissal_time" class="form-control form-control-sm" value="12:00">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Baris 3: Siswa Tingkat/Kelas Lain yang Tidak Terlibat -->
                            <div class="p-2 bg-white rounded-3 border" id="row_disengaged_students">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <span class="fw-bold text-dark small"><i class="ti ti-mood-empty text-info me-1"></i> Siswa Lain:</span>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="disengaged_student_impact" id="input_disengaged_student_impact" class="form-select form-select-sm" onchange="onDisengagedStudentImpactChange(this.value)">
                                            <option value="kbm_normal">KBM Normal</option>
                                            <option value="pembelajaran_daring">Pembelajaran Daring</option>
                                            <option value="pulang_cepat">Pulang Cepat</option>
                                            <option value="bebas_kbm">Bebas KBM</option>
                                            <option value="libur_khusus">Libur Khusus</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5" id="box_disengaged_student_period" style="display: none;">
                                        <select name="disengaged_student_max_period" id="input_disengaged_student_max_period" class="form-select form-select-sm">
                                            <option value="">-- KBM Selesai Jam Ke- --</option>
                                            @foreach($periodMap as $pNum => $pData)
                                                <option value="{{ $pNum }}">{{ $pData['label'] }} ({{ $pData['time'] }} WIB)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. CATATAN & DESKRIPSI KEGIATAN -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi / Petunjuk Pelaksanaan (Opsional)</label>
                    <textarea name="description" id="input_description" class="form-control" rows="2" placeholder="Informasi instruksi pelaksanaan, link materi, agenda pembahasan, atau kriteria tugas..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold" id="btnSubmitActivity">
                    <i class="ti ti-check me-1"></i> Simpan Kegiatan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL 2: QUICK SCHEDULE / EDIT SCHEDULE KE KALENDER ================= -->
<div class="modal fade" id="modalQuickSchedule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formQuickSchedule" method="POST" action="" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="event_id" id="quick_event_id" value="">

            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title fw-bold" id="modalQuickScheduleTitle">
                    <i class="ti ti-calendar-plus me-1"></i> Jadwalkan ke Kalender Kegiatan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 px-3 mb-3 small" id="quickScheduleInfo">
                    Menjadwalkan kegiatan untuk <strong>Tahun Pelajaran: {{ $academicYear }}</strong>.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kegiatan</label>
                    <input type="text" id="quick_name" class="form-control fw-bold bg-light" readonly>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Tanggal Mulai</label>
                        <input type="date" name="start_date" id="quick_start_date" class="form-control fw-semibold" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tanggal Selesai</label>
                        <input type="date" name="end_date" id="quick_end_date" class="form-control fw-semibold" 
                               placeholder="Opsional jika 1 hari">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="p-2 bg-light rounded border">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="quick_is_holiday" name="is_holiday" value="1">
                            <label class="form-check-label fw-semibold text-danger" for="quick_is_holiday">
                                <i class="ti ti-calendar-off me-1"></i> Libur Sekolah / Bebas KBM
                            </label>
                        </div>
                        <div class="text-muted small ps-4 mt-1" style="font-size: 0.73rem; line-height: 1.2;">
                            Menggugurkan kewajiban KBM tatap muka &amp; presensi wajib bagi guru dan siswa pada tanggal ini.
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Lokasi Pelaksanaan</label>
                    <input type="text" name="location" id="quick_location" class="form-control" value="SMK PGRI Kras" placeholder="Contoh: DUDI Mitra / Lab / Aula">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Catatan Penjadwalan</label>
                    <textarea name="notes" id="quick_notes" class="form-control" rows="2" placeholder="Catatan instruksi atau target pelaksanaan..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-teal text-white fw-bold" id="btnSubmitQuickSchedule">
                    <i class="ti ti-calendar-event me-1"></i> Masukkan ke Kalender
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function onCategoryChange(category) {
        if (['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(category)) {
            const holInput = document.getElementById('input_is_holiday');
            if (holInput) {
                holInput.checked = true;
                onHolidayToggle(true);
            }
        }
    }

    function onHolidayToggle(isHoliday) {
        const participantsSection = document.getElementById('section_participants_and_impacts');
        if (participantsSection) {
            participantsSection.style.display = isHoliday ? 'none' : 'block';
        }
        if (isHoliday) {
            const lImpact = document.getElementById('input_learning_impact');
            if (lImpact) {
                lImpact.value = 'libur_khusus';
            }
        }
        evaluateStaffAndDisengagedVisibility();
    }

    function toggleSelectGtk(type) {
        if (type === 'teachers') {
            document.querySelectorAll('.user-teacher-cb').forEach(cb => cb.checked = true);
        } else if (type === 'staff') {
            document.querySelectorAll('.user-staff-cb').forEach(cb => cb.checked = true);
        } else if (type === 'none') {
            document.querySelectorAll('.user-assign-cb').forEach(cb => cb.checked = false);
        }
        evaluateStaffAndDisengagedVisibility();
    }

    function onGtkCheckboxChange() {
        evaluateStaffAndDisengagedVisibility();
    }

    function onTargetAudienceChange(audience) {
        const boxTingkat = document.getElementById('box_target_tingkat');
        const boxRombel = document.getElementById('box_target_rombel');
        const boxStudentTime = document.getElementById('box_student_time');
        const colTargetAudience = document.getElementById('col_target_audience');
        const boxStudentLearningImpact = document.getElementById('box_student_learning_impact');

        const isNoStudents = (audience === 'guru_staf');

        if (boxTingkat) boxTingkat.style.display = (audience === 'tingkat') ? 'block' : 'none';
        if (boxRombel) boxRombel.style.display = (audience === 'rombel') ? 'block' : 'none';

        if (boxStudentTime) {
            boxStudentTime.style.display = isNoStudents ? 'none' : 'block';
        }
        if (colTargetAudience) {
            colTargetAudience.className = isNoStudents ? 'col-12' : 'col-md-6';
        }
        if (boxStudentLearningImpact) {
            boxStudentLearningImpact.style.display = isNoStudents ? 'none' : 'block';
        }

        evaluateStaffAndDisengagedVisibility();
    }

    function onDisengagedTeacherImpactChange(impact) {
        const box = document.getElementById('box_disengaged_teacher_period');
        if (box) {
            box.style.display = (impact === 'pulang_cepat') ? 'block' : 'none';
        }
    }

    function onDisengagedStaffImpactChange(impact) {
        const box = document.getElementById('box_disengaged_staff_time');
        if (box) {
            box.style.display = (impact === 'pulang_cepat') ? 'block' : 'none';
        }
    }

    function onDisengagedStudentImpactChange(impact) {
        const box = document.getElementById('box_disengaged_student_period');
        if (box) {
            box.style.display = (impact === 'pulang_cepat') ? 'block' : 'none';
        }
        evaluateStaffAndDisengagedVisibility();
    }

    function evaluateStaffAndDisengagedVisibility() {
        const audience = document.getElementById('input_target_audience') ? document.getElementById('input_target_audience').value : 'guru_staf';
        const rowDisengagedStudents = document.getElementById('row_disengaged_students');
        const rowDisengagedTeachers = document.getElementById('row_disengaged_teachers');
        const optStaffPulangCepat = document.getElementById('opt_staff_pulang_cepat');
        const optStaffLibur = document.getElementById('opt_staff_libur');
        const selectStaffImpact = document.getElementById('input_disengaged_staff_impact');
        const studentImpact = document.getElementById('input_learning_impact') ? document.getElementById('input_learning_impact').value : 'normal';
        const disengagedStudentImpact = document.getElementById('input_disengaged_student_impact') ? document.getElementById('input_disengaged_student_impact').value : 'kbm_normal';

        if (audience === 'semua') {
            if (rowDisengagedStudents) rowDisengagedStudents.style.display = 'none';
        } else {
            if (rowDisengagedStudents) rowDisengagedStudents.style.display = 'block';
        }

        const teacherCheckboxes = document.querySelectorAll('.user-teacher-cb');
        const totalTeachers = teacherCheckboxes.length;
        const checkedTeachers = document.querySelectorAll('.user-teacher-cb:checked').length;
        if (totalTeachers > 0 && checkedTeachers === totalTeachers) {
            if (rowDisengagedTeachers) rowDisengagedTeachers.style.display = 'none';
        } else {
            if (rowDisengagedTeachers) rowDisengagedTeachers.style.display = 'block';
        }

        let studentsStillInSchool = false;
        if (audience === 'semua') {
            if (studentImpact === 'normal') studentsStillInSchool = true;
        } else if (audience === 'guru_staf') {
            if (disengagedStudentImpact === 'kbm_normal') studentsStillInSchool = true;
        } else {
            if (studentImpact === 'normal' || disengagedStudentImpact === 'kbm_normal') studentsStillInSchool = true;
        }

        if (studentsStillInSchool) {
            if (optStaffPulangCepat) optStaffPulangCepat.disabled = true;
            if (optStaffLibur) optStaffLibur.disabled = true;
            if (selectStaffImpact && (selectStaffImpact.value === 'pulang_cepat' || selectStaffImpact.value === 'libur')) {
                selectStaffImpact.value = 'masuk_normal';
                onDisengagedStaffImpactChange('masuk_normal');
            }
        } else {
            if (optStaffPulangCepat) optStaffPulangCepat.disabled = false;
            if (optStaffLibur) optStaffLibur.disabled = false;
        }
    }

    function openCreateActivityModal() {
        const form = document.getElementById('formActivity');
        form.action = "{{ route('kurikulum.activities.store') }}";
        document.getElementById('methodSpoofing').innerHTML = '';
        document.getElementById('modalActivityTitle').innerHTML = '<i class="ti ti-calendar-plus me-1"></i> Tambah Kegiatan Sekolah';
        const btn = document.getElementById('btnSubmitActivity');
        btn.innerHTML = '<i class="ti ti-check me-1"></i> Simpan Kegiatan';
        btn.disabled = false;

        document.getElementById('input_name').value = '';
        document.getElementById('input_code').value = '';
        document.getElementById('input_category').value = 'kegiatan_kurikulum';
        document.getElementById('input_location').value = 'SMK PGRI Kras';
        document.getElementById('input_start_date').value = '';
        document.getElementById('input_end_date').value = '';

        const holInput = document.getElementById('input_is_holiday');
        if (holInput) {
            holInput.checked = false;
            onHolidayToggle(false);
        }

        document.getElementById('input_learning_impact').value = 'bebas_kbm_kegiatan';
        document.getElementById('input_target_audience').value = 'guru_staf';
        onTargetAudienceChange('guru_staf');

        document.querySelectorAll('.grade-level-cb').forEach(cb => cb.checked = false);
        document.querySelectorAll('.classroom-target-cb').forEach(cb => cb.checked = false);
        document.getElementById('input_student_start_time').value = '07:00';
        document.getElementById('input_student_end_time').value = '12:00';

        const teacherImpactEl = document.getElementById('input_teacher_impact');
        if (teacherImpactEl) {
            teacherImpactEl.value = 'bebas_mengajar_tugas';
        }
        document.getElementById('input_gtk_checkin_time').value = '07:00';
        document.getElementById('input_gtk_checkout_time').value = '13:30';
        document.querySelectorAll('.user-assign-cb').forEach(cb => cb.checked = false);

        document.getElementById('input_disengaged_teacher_impact').value = 'masuk_normal';
        onDisengagedTeacherImpactChange('masuk_normal');
        document.getElementById('input_disengaged_teacher_max_period').value = '';

        document.getElementById('input_disengaged_staff_impact').value = 'masuk_normal';
        onDisengagedStaffImpactChange('masuk_normal');
        document.getElementById('input_disengaged_staff_dismissal_time').value = '12:00';

        document.getElementById('input_disengaged_student_impact').value = 'kbm_normal';
        onDisengagedStudentImpactChange('kbm_normal');
        document.getElementById('input_disengaged_student_max_period').value = '';

        document.getElementById('input_description').value = '';

        evaluateStaffAndDisengagedVisibility();

        new bootstrap.Modal(document.getElementById('modalActivityForm')).show();
    }

    function openEditActivityModal(activity) {
        const form = document.getElementById('formActivity');
        form.action = `/kurikulum/kegiatan-sekolah/${activity.id}`;
        document.getElementById('methodSpoofing').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('modalActivityTitle').innerHTML = '<i class="ti ti-edit me-1"></i> Edit Kegiatan: ' + activity.name;
        const btn = document.getElementById('btnSubmitActivity');
        btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Simpan Perubahan';
        btn.disabled = false;

        document.getElementById('input_name').value = activity.name || '';
        document.getElementById('input_code').value = activity.code || '';
        document.getElementById('input_category').value = activity.category || 'kegiatan_kurikulum';
        document.getElementById('input_description').value = activity.description || '';

        const ev = activity.scheduled_event;
        if (ev) {
            document.getElementById('input_location').value = ev.location || '';
            document.getElementById('input_start_date').value = ev.start_date ? String(ev.start_date).substring(0, 10) : '';
            document.getElementById('input_end_date').value = (ev.end_date && ev.end_date !== ev.start_date) ? String(ev.end_date).substring(0, 10) : '';

            const isHoliday = Boolean(ev.is_holiday) || ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(ev.category || activity.category);
            const holInput = document.getElementById('input_is_holiday');
            if (holInput) {
                holInput.checked = isHoliday;
                onHolidayToggle(isHoliday);
            }

            const targetAudience = ev.target_audience || 'guru_staf';
            document.getElementById('input_target_audience').value = targetAudience;
            onTargetAudienceChange(targetAudience);

            document.getElementById('input_student_start_time').value = ev.student_start_time ? String(ev.student_start_time).substring(0, 5) : '07:00';
            document.getElementById('input_student_end_time').value = ev.student_end_time ? String(ev.student_end_time).substring(0, 5) : '12:00';
            document.getElementById('input_learning_impact').value = ev.learning_impact || 'bebas_kbm_kegiatan';

            const targetLevels = (ev.target_grade_levels || []).map(String);
            document.querySelectorAll('.grade-level-cb').forEach(cb => {
                cb.checked = targetLevels.includes(String(cb.value));
            });

            const targetClassrooms = (ev.target_classroom_ids || []).map(String);
            document.querySelectorAll('.classroom-target-cb').forEach(cb => {
                cb.checked = targetClassrooms.includes(String(cb.value));
            });

            const teacherImpactEl = document.getElementById('input_teacher_impact');
            if (teacherImpactEl) {
                teacherImpactEl.value = ev.teacher_impact || 'bebas_mengajar_tugas';
            }
            document.getElementById('input_gtk_checkin_time').value = ev.gtk_checkin_time ? String(ev.gtk_checkin_time).substring(0, 5) : '07:00';
            document.getElementById('input_gtk_checkout_time').value = ev.gtk_checkout_time ? String(ev.gtk_checkout_time).substring(0, 5) : '13:30';

            const assignedUserIds = (ev.users || []).map(u => u.id);
            document.querySelectorAll('.user-assign-cb').forEach(cb => {
                cb.checked = assignedUserIds.includes(parseInt(cb.value, 10));
            });

            const disTeacher = ev.disengaged_teacher_impact || 'masuk_normal';
            document.getElementById('input_disengaged_teacher_impact').value = disTeacher;
            onDisengagedTeacherImpactChange(disTeacher);
            document.getElementById('input_disengaged_teacher_max_period').value = ev.disengaged_teacher_max_period || '';

            const disStaff = ev.disengaged_staff_impact || 'masuk_normal';
            document.getElementById('input_disengaged_staff_impact').value = disStaff;
            onDisengagedStaffImpactChange(disStaff);
            document.getElementById('input_disengaged_staff_dismissal_time').value = ev.disengaged_staff_dismissal_time ? String(ev.disengaged_staff_dismissal_time).substring(0, 5) : '12:00';

            const disStudent = ev.disengaged_student_impact || 'kbm_normal';
            document.getElementById('input_disengaged_student_impact').value = disStudent;
            onDisengagedStudentImpactChange(disStudent);
            document.getElementById('input_disengaged_student_max_period').value = ev.disengaged_student_max_period || '';
        } else {
            document.getElementById('input_location').value = 'SMK PGRI Kras';
            document.getElementById('input_start_date').value = '';
            document.getElementById('input_end_date').value = '';

            const holInput = document.getElementById('input_is_holiday');
            if (holInput) {
                holInput.checked = false;
                onHolidayToggle(false);
            }

            document.getElementById('input_target_audience').value = 'guru_staf';
            onTargetAudienceChange('guru_staf');
            document.querySelectorAll('.grade-level-cb').forEach(cb => cb.checked = false);
            document.querySelectorAll('.classroom-target-cb').forEach(cb => cb.checked = false);
            document.querySelectorAll('.user-assign-cb').forEach(cb => cb.checked = false);

            document.getElementById('input_disengaged_teacher_impact').value = 'masuk_normal';
            onDisengagedTeacherImpactChange('masuk_normal');
            document.getElementById('input_disengaged_teacher_max_period').value = '';

            document.getElementById('input_disengaged_staff_impact').value = 'masuk_normal';
            onDisengagedStaffImpactChange('masuk_normal');
            document.getElementById('input_disengaged_staff_dismissal_time').value = '12:00';

            document.getElementById('input_disengaged_student_impact').value = 'kbm_normal';
            onDisengagedStudentImpactChange('kbm_normal');
            document.getElementById('input_disengaged_student_max_period').value = '';
        }

        evaluateStaffAndDisengagedVisibility();

        new bootstrap.Modal(document.getElementById('modalActivityForm')).show();
    }

    function openQuickScheduleModal(activity) {
        const form = document.getElementById('formQuickSchedule');
        form.action = `/kurikulum/kegiatan-sekolah/${activity.id}/jadwalkan`;
        document.getElementById('quick_event_id').value = '';

        document.getElementById('modalQuickScheduleTitle').innerHTML = '<i class="ti ti-calendar-plus me-1"></i> Jadwalkan ke Kalender Kegiatan';
        const btn = document.getElementById('btnSubmitQuickSchedule');
        btn.innerHTML = '<i class="ti ti-calendar-event me-1"></i> Masukkan ke Kalender';
        btn.disabled = false;
        document.getElementById('quickScheduleInfo').innerHTML = 'Menjadwalkan kegiatan untuk <strong>Tahun Pelajaran: {{ $academicYear }}</strong>.';

        document.getElementById('quick_name').value = activity.name + (activity.code ? ` (${activity.code})` : '');
        
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('quick_start_date').value = `${yyyy}-${mm}-${dd}`;
        document.getElementById('quick_end_date').value = '';
        document.getElementById('quick_location').value = 'SMK PGRI Kras';
        document.getElementById('quick_notes').value = '';

        const isHol = ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(activity.category);
        const quickHol = document.getElementById('quick_is_holiday');
        if (quickHol) quickHol.checked = isHol;

        new bootstrap.Modal(document.getElementById('modalQuickSchedule')).show();
    }

    function openEditScheduleModal(activity, event) {
        const form = document.getElementById('formQuickSchedule');
        form.action = `/kurikulum/kegiatan-sekolah/${activity.id}/jadwalkan`;
        document.getElementById('quick_event_id').value = event ? event.id : '';

        document.getElementById('modalQuickScheduleTitle').innerHTML = '<i class="ti ti-calendar-event me-1"></i> Ubah Jadwal: ' + activity.name;
        document.getElementById('btnSubmitQuickSchedule').innerHTML = '<i class="ti ti-device-floppy me-1"></i> Simpan Perubahan Jadwal';
        document.getElementById('quickScheduleInfo').innerHTML = 'Perbarui tanggal pelaksanaan atau detail agenda pada <strong>Tahun Pelajaran: {{ $academicYear }}</strong>.';

        document.getElementById('quick_name').value = activity.name + (activity.code ? ` (${activity.code})` : '');
        
        if (event && event.start_date) {
            document.getElementById('quick_start_date').value = String(event.start_date).substring(0, 10);
        }
        if (event && event.end_date && event.end_date !== event.start_date) {
            document.getElementById('quick_end_date').value = String(event.end_date).substring(0, 10);
        } else {
            document.getElementById('quick_end_date').value = '';
        }

        document.getElementById('quick_location').value = (event && event.location) ? event.location : 'SMK PGRI Kras';
        
        const isHol = event ? (Boolean(event.is_holiday) || ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(activity.category)) : false;
        const quickHol = document.getElementById('quick_is_holiday');
        if (quickHol) quickHol.checked = isHol;

        // Extract clean notes if has prefix Catatan:
        let desc = (event && event.description) ? event.description : '';
        if (desc.includes('Catatan: ')) {
            desc = desc.split('Catatan: ')[1];
        }
        document.getElementById('quick_notes').value = desc;

        const btnSubmit = document.getElementById('btnSubmitQuickSchedule');
        if (btnSubmit) btnSubmit.disabled = false;

        new bootstrap.Modal(document.getElementById('modalQuickSchedule')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const formQuick = document.getElementById('formQuickSchedule');
        if (formQuick) {
            formQuick.addEventListener('submit', function() {
                const btn = document.getElementById('btnSubmitQuickSchedule');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...';
                }
            });
        }

        const formAct = document.getElementById('formActivity');
        if (formAct) {
            formAct.addEventListener('submit', function() {
                const btn = document.getElementById('btnSubmitActivity');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...';
                }
            });
        }
    });
</script>
@endif

<!-- ================= MODAL 3: PREVIEW KEGIATAN SEKOLAH ================= -->
<div class="modal fade" id="modalPreviewActivity" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <span id="preview_color_dot" class="badge-dot rounded-circle" style="width: 12px; height: 12px; background-color: #206bc4;"></span>
                    <h5 class="modal-title fw-bold" id="preview_modal_title">
                        Preview Kegiatan Sekolah
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Header Card / Badge info -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h3 class="fw-bold text-dark mb-1" id="preview_name">-</h3>
                            <div class="d-flex align-items-center gap-2 flex-wrap" id="preview_badges">
                                <!-- badges injected via JS -->
                            </div>
                        </div>
                        <div id="preview_schedule_badge">
                            <!-- schedule status badge injected via JS -->
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card card-sm border shadow-none h-100">
                            <div class="card-body p-3">
                                <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.05rem;">
                                    <i class="ti ti-info-circle me-1 text-primary"></i> Data Acuan Kegiatan
                                </div>
                                <table class="table table-sm table-borderless m-0" style="font-size: 0.82rem;">
                                    <tr>
                                        <td class="text-muted py-1" style="width: 120px;">Kode Singkatan</td>
                                        <td class="fw-bold py-1" id="preview_code">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Kategori</td>
                                        <td class="fw-bold py-1" id="preview_category">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Sasaran Peserta</td>
                                        <td class="fw-bold py-1" id="preview_target">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Sifat &amp; Durasi</td>
                                        <td class="fw-bold py-1" id="preview_duration">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Kaldik Jatim</td>
                                        <td class="fw-bold py-1" id="preview_kaldik">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-sm border shadow-none h-100">
                            <div class="card-body p-3">
                                <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.05rem;">
                                    <i class="ti ti-calendar me-1 text-primary"></i> Status Penjadwalan (TP {{ $academicYear }})
                                </div>
                                <div id="preview_schedule_details">
                                    <!-- Populated via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-sm border shadow-none">
                    <div class="card-body p-3">
                        <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.05rem;">
                            <i class="ti ti-notes me-1 text-primary"></i> Deskripsi / Keterangan
                        </div>
                        <p class="text-secondary mb-0" id="preview_description" style="font-size: 0.84rem; white-space: pre-line;">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <div class="d-flex gap-2" id="preview_actions">
                    <!-- Action buttons injected via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentPreviewActivity = null;
    let currentPreviewEvent = null;

    function openPreviewModal(activity, event) {
        currentPreviewActivity = activity;
        currentPreviewEvent = event;

        document.getElementById('preview_modal_title').textContent = 'Preview Kegiatan: ' + activity.name;
        document.getElementById('preview_color_dot').style.backgroundColor = activity.color || activity.category_color || '#206bc4';
        
        let titleHtml = activity.name;
        if (activity.code) {
            titleHtml += ` <span class="text-secondary fw-normal">(${activity.code})</span>`;
        }
        document.getElementById('preview_name').innerHTML = titleHtml;

        let badgesHtml = `<span class="badge ${activity.category_badge || 'bg-blue-lt'} fw-semibold">${activity.category_label || activity.category}</span>`;
        if (activity.is_kaldik) {
            badgesHtml += ` <span class="badge bg-red-lt text-red fw-bold"><i class="ti ti-file-certificate me-1"></i> Kaldik Jatim</span>`;
        }
        if (activity.is_mandatory) {
            badgesHtml += ` <span class="badge bg-dark-lt text-dark fw-semibold">Wajib</span>`;
        } else {
            badgesHtml += ` <span class="badge bg-secondary-lt text-muted">Opsional</span>`;
        }
        document.getElementById('preview_badges').innerHTML = badgesHtml;

        document.getElementById('preview_code').innerHTML = activity.code ? `<code class="fw-bold text-primary">${activity.code}</code>` : '-';
        document.getElementById('preview_category').textContent = activity.category_label || activity.category;
        document.getElementById('preview_target').textContent = activity.target_participant || 'Seluruh Sekolah';
        document.getElementById('preview_duration').textContent = `± ${activity.default_duration_days || 1} Hari (${activity.is_mandatory ? 'Wajib' : 'Opsional'})`;
        document.getElementById('preview_kaldik').innerHTML = activity.is_kaldik 
            ? '<span class="badge bg-red-lt text-red fw-bold">Ya (Kaldik Jatim)</span>' 
            : '<span class="text-muted">Bukan Bagian Kaldik</span>';

        document.getElementById('preview_description').textContent = activity.description || 'Tidak ada catatan atau deskripsi tambahan untuk kegiatan ini.';

        let statusBadgeHtml = '';
        let schedDetailsHtml = '';
        let actionsHtml = '';
        const canManage = {{ auth()->user()->canViewAllSchedules() ? 'true' : 'false' }};

        if (activity.is_scheduled && event) {
            statusBadgeHtml = `<span class="badge bg-success-lt text-success fw-bold border border-success px-2 py-1"><i class="ti ti-calendar-check me-1"></i> Sudah Terjadwal</span>`;
            
            let dateStr = event.start_date ? String(event.start_date).substring(0, 10) : '-';
            if (event.end_date && event.end_date !== event.start_date) {
                dateStr += ' s/d ' + String(event.end_date).substring(0, 10);
            }

            schedDetailsHtml = `
                <div class="mb-2">
                    <div class="text-muted small">Waktu Pelaksanaan:</div>
                    <div class="fw-bold text-dark fs-3"><i class="ti ti-calendar text-primary me-1"></i> ${dateStr}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Lokasi:</div>
                    <div class="fw-semibold text-dark"><i class="ti ti-map-pin text-secondary me-1"></i> ${event.location || 'SMK PGRI Kras'}</div>
                </div>
            `;

            actionsHtml += `
                <a href="/kurikulum/kalender-kegiatan?academic_year={{ $academicYear }}&date=${event.start_date ? String(event.start_date).substring(0, 10) : ''}" 
                   target="_blank" class="btn btn-outline-secondary fw-semibold btn-sm">
                    <i class="ti ti-calendar-event me-1"></i> Buka di Kalender
                </a>
            `;

            if (canManage) {
                actionsHtml += `
                    <button type="button" class="btn btn-primary fw-bold btn-sm" onclick="handlePreviewEditSchedule()">
                        <i class="ti ti-pencil me-1"></i> Ubah Jadwal
                    </button>
                `;
            }
        } else if (activity.supervision_count > 0) {
            statusBadgeHtml = `<span class="badge bg-indigo-lt text-indigo fw-bold border border-indigo px-2 py-1"><i class="ti ti-clipboard-check me-1"></i> Terhubung Supervisi</span>`;
            schedDetailsHtml = `
                <div class="alert alert-info py-2 px-3 small mb-0">
                    <i class="ti ti-info-circle me-1"></i> Kegiatan ini terhubung dengan <strong>${activity.supervision_count} jadwal supervisi guru</strong> pada TP {{ $academicYear }}.
                </div>
            `;
            if (canManage) {
                actionsHtml += `
                    <a href="{{ route('schedules.index') }}" class="btn btn-indigo text-white fw-bold btn-sm">
                        <i class="ti ti-list-details me-1"></i> Buka Jadwal Supervisi
                    </a>
                `;
            }
        } else {
            statusBadgeHtml = `<span class="badge bg-warning-lt text-warning fw-bold border border-warning px-2 py-1"><i class="ti ti-calendar-x me-1"></i> Belum Terjadwal</span>`;
            schedDetailsHtml = `
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    <i class="ti ti-alert-circle me-1"></i> Belum ada jadwal di Kalender Kegiatan untuk Tahun Pelajaran {{ $academicYear }}.
                </div>
            `;
            if (canManage) {
                actionsHtml += `
                    <button type="button" class="btn btn-teal text-white fw-bold btn-sm" onclick="handlePreviewQuickSchedule()">
                        <i class="ti ti-calendar-plus me-1"></i> Jadwalkan Sekarang
                    </button>
                `;
            }
        }

        document.getElementById('preview_schedule_badge').innerHTML = statusBadgeHtml;
        document.getElementById('preview_schedule_details').innerHTML = schedDetailsHtml;
        document.getElementById('preview_actions').innerHTML = actionsHtml;

        new bootstrap.Modal(document.getElementById('modalPreviewActivity')).show();
    }

    function handlePreviewEditSchedule() {
        const modalEl = document.getElementById('modalPreviewActivity');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        if (currentPreviewActivity) {
            openEditScheduleModal(currentPreviewActivity, currentPreviewEvent);
        }
    }

    function handlePreviewQuickSchedule() {
        const modalEl = document.getElementById('modalPreviewActivity');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        if (currentPreviewActivity) {
            openQuickScheduleModal(currentPreviewActivity);
        }
    }
</script>
@endsection
