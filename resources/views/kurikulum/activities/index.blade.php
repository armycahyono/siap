@extends('layouts.app')

@section('title', 'Kegiatan Sekolah')

@section('content')
<!-- Breadcrumbs -->
<div class="mb-3">
    <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><span class="text-muted">Kurikulum</span></li>
        <li class="breadcrumb-item active" aria-current="page"><a href="#">Kegiatan Sekolah</a></li>
    </ol>
</div>

<!-- Page Header -->
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-calendar-star me-2"></i> Kegiatan Sekolah
        </h2>
        <div class="text-muted small">
            Daftar acuan seluruh kegiatan resmi sekolah (Kaldik, Kegiatan Khusus SMK/PKL/KI, Asesmen &amp; Ujian, Supervisi). 
            Pantau status keterjadwalan kegiatan pada <strong>Tahun Pelajaran: {{ $academicYear }}</strong>.
        </div>
    </div>
    <div class="col-auto d-flex gap-2">
        <a href="{{ route('kurikulum.calendar.index', ['academic_year' => $academicYear]) }}" class="btn btn-outline-primary shadow-sm fw-semibold">
            <i class="ti ti-calendar-event me-1"></i> Buka Kalender Kegiatan
        </a>
        @if(auth()->user()->canViewAllSchedules())
        <button type="button" class="btn btn-primary shadow-sm fw-bold" onclick="openCreateActivityModal()">
            <i class="ti ti-plus me-1"></i> Tambah Kegiatan Baru
        </button>
        @endif
    </div>
</div>

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

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalActivityTitle">
                    <i class="ti ti-calendar-plus me-1"></i> Tambah Kegiatan Sekolah
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label required fw-bold">Nama Kegiatan</label>
                        <input type="text" name="name" id="input_name" class="form-control" 
                               placeholder="Contoh: Praktik Kerja Lapangan (PKL) / Kunjungan Industri (KI)" required>
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
                        <select name="category" id="input_category" class="form-select" required>
                            @foreach($categories as $key => $cat)
                                <option value="{{ $key }}" data-color="{{ $cat['color'] }}">{{ $cat['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Sasaran Peserta</label>
                        <input type="text" name="target_participant" id="input_target_participant" class="form-control" 
                               placeholder="Contoh: Kelas XI & XII / Seluruh Siswa / Dewan Guru">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Estimasi Durasi (Hari)</label>
                        <input type="number" name="default_duration_days" id="input_duration" class="form-control" 
                               value="1" min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Warna Label / Kalender</label>
                        <input type="color" name="color" id="input_color" class="form-control form-control-color w-100" 
                               value="#0284c7" title="Pilih warna untuk kalender">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_mandatory" id="input_is_mandatory" value="1" checked>
                            <label class="form-check-label fw-bold" for="input_is_mandatory">Kegiatan Wajib</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi / Ruang Lingkup Kegiatan</label>
                    <textarea name="description" id="input_description" class="form-control" rows="3" 
                              placeholder="Keterangan tujuan, pelaksana, atau teknis kegiatan..."></textarea>
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
        document.getElementById('input_category').value = 'smk_khusus';
        document.getElementById('input_target_participant').value = '';
        document.getElementById('input_duration').value = '1';
        document.getElementById('input_color').value = '#0284c7';
        document.getElementById('input_is_mandatory').checked = true;
        document.getElementById('input_description').value = '';

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
        document.getElementById('input_category').value = activity.category || 'smk_khusus';
        document.getElementById('input_target_participant').value = activity.target_participant || '';
        document.getElementById('input_duration').value = activity.default_duration_days || 1;
        document.getElementById('input_color').value = activity.color || '#0284c7';
        document.getElementById('input_is_mandatory').checked = Boolean(activity.is_mandatory);
        document.getElementById('input_description').value = activity.description || '';

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
