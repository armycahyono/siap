@extends('layouts.app')

@php
    $yearParts = explode('/', $academicYear);
    $acStartYear = isset($yearParts[0]) && is_numeric($yearParts[0]) ? (int) $yearParts[0] : (now()->month >= 7 ? now()->year : now()->year - 1);
    $acEndYear = isset($yearParts[1]) && is_numeric($yearParts[1]) ? (int) $yearParts[1] : ($acStartYear + 1);

    $now = now();
    $currentAcademicStartYear = $now->month >= 7 ? $now->year : $now->year - 1;
    if (request('date')) {
        $initialCalDate = request('date');
    } elseif ($acStartYear === $currentAcademicStartYear) {
        $initialCalDate = $now->format('Y-m-d');
    } else {
        $initialCalDate = "{$acStartYear}-07-01";
    }
@endphp

@section('title', 'Kalender Pendidikan & Kegiatan')

@push('styles')
<!-- FullCalendar 6 CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
<style>
    /* Google Calendar Styled FullCalendar */
    .fc {
        font-family: inherit;
    }
    .fc .fc-toolbar.fc-header-toolbar {
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .fc .fc-toolbar-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .fc .fc-button {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        transition: all 0.15s ease;
        text-transform: capitalize;
    }
    .fc .fc-button:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active, 
    .fc .fc-button-primary:not(:disabled):active {
        background-color: #2563eb !important;
        border-color: #2563eb !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .fc .fc-today-button {
        font-weight: 700;
        background-color: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }
    .fc .fc-today-button:hover {
        background-color: #dbeafe;
        color: #1d4ed8;
    }
    .fc .fc-col-header-cell {
        background-color: #f8fafc;
        padding: 8px 0;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        border-color: #e2e8f0;
    }
    .fc .fc-daygrid-day-number {
        font-size: 0.85rem;
        font-weight: 700;
        color: #334155;
        padding: 6px 8px;
    }
    .fc .fc-day-today {
        background-color: #f0f7ff !important;
    }
    .fc .fc-day-today .fc-daygrid-day-number {
        background-color: #2563eb;
        color: #ffffff;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 3px;
        box-shadow: 0 2px 4px rgba(37,99,235,0.3);
    }
    /* Event pill modern */
    .fc-event {
        border-radius: 5px !important;
        padding: 2px 6px !important;
        font-size: 0.78rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        margin-bottom: 2px !important;
        border: none !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .fc-event:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.15) !important;
    }
    .fc-event-title {
        font-weight: 600 !important;
    }
    .fc-theme-standard td, .fc-theme-standard th {
        border-color: #e2e8f0;
    }
    .fc .fc-daygrid-day-frame {
        min-height: 95px;
    }
    /* Holiday Day Highlight */
    .holiday-col-sun {
        background-color: #fff1f2 !important;
    }
    .holiday-col-sun .fc-col-header-cell-cushion {
        color: #e11d48 !important;
    }
    /* Left filter card sticky */
    .sticky-filter-card {
        position: sticky;
        top: 80px;
    }
    .category-filter-item {
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease;
    }
    .category-filter-item:hover {
        background-color: #f1f5f9;
    }
    /* MultiMonth (Kalender Pendidikan 12 Bulan Berjajar ke Bawah) */
    .fc .fc-multimonth {
        border: none !important;
        background: transparent !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 1.75rem !important; /* Jarak lega antar bulan */
    }
    .fc .fc-multimonth-month {
        background: #ffffff;
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        padding: 0 !important;
        overflow: hidden;
        width: 100% !important;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .fc .fc-multimonth-month:hover {
        border-color: #94a3b8 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    }
    /* Highlight kartu bulan yang memuat hari ini */
    .fc .fc-multimonth-month:has(.fc-day-today) {
        border-color: #3b82f6 !important;
        box-shadow: 0 4px 14px rgba(37,99,235,0.12) !important;
    }
    .fc .fc-multimonth-title {
        font-size: 1.15rem !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        padding: 0.85rem 1.25rem !important;
        text-align: left !important;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
    }
    .fc .fc-multimonth-month:has(.fc-day-today) .fc-multimonth-title {
        background: #eff6ff;
        color: #1d4ed8 !important;
        border-bottom-color: #bfdbfe;
    }
    .fc .fc-multimonth-title::before {
        content: "";
        display: inline-block;
        width: 4px;
        height: 1.25rem;
        background-color: #2563eb;
        border-radius: 2px;
        margin-right: 0.65rem;
    }
    .fc .fc-multimonth-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .fc .fc-multimonth-header-table th {
        font-size: 0.8rem !important;
        font-weight: 700 !important;
        color: #475569 !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 8px 0 !important;
        border: 1px solid #e2e8f0 !important;
        border-top: none !important;
        background: #f8fafc;
    }
    .fc .fc-multimonth-daygrid-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .fc .fc-multimonth-daygrid-table td {
        border: 1px solid #e2e8f0 !important;
        vertical-align: top !important;
    }
    /* Cell hari lega seperti tampilan bulan */
    .fc .fc-multimonth .fc-daygrid-day-frame {
        min-height: 90px !important;
        padding: 4px 6px !important;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
        border-radius: 0;
        transition: background-color 0.15s ease;
    }
    .fc .fc-multimonth .fc-daygrid-day:hover .fc-daygrid-day-frame {
        background-color: #f8fafc;
        cursor: pointer;
    }
    .fc .fc-multimonth .fc-daygrid-day-top {
        display: flex;
        justify-content: flex-end;
    }
    .fc .fc-multimonth .fc-daygrid-day-number {
        font-size: 0.85rem !important;
        font-weight: 700 !important;
        color: #334155;
        padding: 2px 6px !important;
    }
    .fc .fc-multimonth .fc-day-today {
        background-color: #f0f7ff !important;
    }
    .fc .fc-multimonth .fc-day-today .fc-daygrid-day-number {
        background-color: #2563eb;
        color: #ffffff;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 2px;
        box-shadow: 0 2px 4px rgba(37,99,235,0.3);
    }
    .fc .fc-multimonth .fc-daygrid-day-events {
        margin-top: 4px !important;
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-height: 0 !important;
    }
    .fc .fc-multimonth .fc-daygrid-event {
        border-radius: 5px !important;
        padding: 3px 6px !important;
        font-size: 0.76rem !important;
        font-weight: 600 !important;
        border: none !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
        margin-bottom: 2px !important;
        white-space: normal !important;
        line-height: 1.25 !important;
        cursor: pointer !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .fc .fc-multimonth .fc-daygrid-event:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.15) !important;
    }
    .fc .fc-multimonth .holiday-col-sun {
        background-color: #fff1f2 !important;
    }
    .fc .fc-multimonth .holiday-col-sun .fc-daygrid-day-number {
        color: #e11d48 !important;
    }
    .fc .fc-multimonth .holiday-col-sun th {
        color: #e11d48 !important;
    }
</style>
@endpush

@section('content')
<!-- Page Header -->
<div class="row g-3 mb-4 align-items-center justify-content-between">
    <div class="col-md-6">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-calendar-event me-2"></i> Kalender Pendidikan &amp; Kegiatan
        </h2>
        <div class="text-muted small">
            Acuan terpadu pelaksanaan program akademik, hari efektif, libur resmi Kaldik, supervisi, dan penugasan guru TP {{ $academicYear }}.
        </div>
    </div>
    <div class="col-md-6 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
        @if($activeUpload)
            <a href="{{ route('kurikulum.calendar.kaldik.preview', $activeUpload) }}" target="_blank" class="btn btn-outline-danger shadow-sm">
                <i class="ti ti-file-type-pdf me-1"></i> Buka Kaldik Asli (PDF)
            </a>
        @endif
        @if(auth()->user()->isWakaKurikulum())
            <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalUploadKaldik">
                <i class="ti ti-cloud-upload me-1"></i> Upload Kaldik (PDF/Excel)
            </button>
        @endif
        @if(auth()->user()->canViewAllSchedules())
            <button type="button" class="btn btn-primary shadow-sm fw-bold" onclick="openCreateModal()">
                <i class="ti ti-plus me-1"></i> Tambah Kegiatan / Agenda
            </button>
        @endif
    </div>
</div>

<!-- Main Calendar Layout -->
<div class="row g-4">
    <!-- Sidebar Kiri: Filters & Navigation (30%) -->
    <div class="col-lg-3">
        <div class="sticky-filter-card">
            <!-- Card Agenda List (Menggantikan Card Filter Agenda) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-list-details me-1 text-primary"></i> Agenda List
                        </h4>
                        <span class="badge bg-primary-lt text-primary fw-bold" id="agendaListCount">0 Agenda</span>
                    </div>

                    <!-- Filter Scope Pills: Bulan | Minggu | Tahun -->
                    <div class="btn-group w-100 mb-3" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary active py-1 fw-bold" id="btnFilterMonth" onclick="setAgendaListScope('month')">
                            Bulan
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary py-1 fw-bold" id="btnFilterWeek" onclick="setAgendaListScope('week')">
                            Minggu
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary py-1 fw-bold" id="btnFilterYear" onclick="setAgendaListScope('year')">
                            Tahun
                        </button>
                    </div>

                    <!-- Scrollable Agenda List Container -->
                    <div id="sidebarAgendaList" class="d-flex flex-column gap-2" style="max-height: 380px; overflow-y: auto;">
                        <div class="text-center py-4 text-muted small">
                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                            <div>Memuat agenda sekolah...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Kaldik Card -->
            <div class="card border-0 shadow-sm bg-primary-lt">
                <div class="card-body p-3">
                    <h5 class="fw-bold text-primary mb-2">
                        <i class="ti ti-chart-pie me-1"></i> Rekap Hari Efektif
                    </h5>
                    <div class="small text-muted mb-2">Berdasarkan Kaldik Jatim TP {{ $academicYear }}:</div>
                    <ul class="list-unstyled mb-0 small">
                        <li class="d-flex justify-content-between py-1 border-bottom border-primary-subtle">
                            <span>Hari Efektif Semester Ganjil</span>
                            <strong class="text-dark">115 Hari</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom border-primary-subtle">
                            <span>Hari Efektif Semester Genap</span>
                            <strong class="text-dark">102 Hari</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom border-primary-subtle">
                            <span>Kegiatan Tengah Sem. (KTS)</span>
                            <strong class="text-dark">3 Hari</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span>Hari Libur Resmi Nasional</span>
                            <strong class="text-danger">{{ $totalHolidays }} Hari</strong>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Acuan Kegiatan Sekolah Card -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="ti ti-calendar-star me-1 text-primary"></i> Kegiatan Sekolah
                        </h5>
                        @if(($unscheduledActivitiesCount ?? 0) > 0)
                            <span class="badge bg-warning-lt text-warning fw-bold">{{ $unscheduledActivitiesCount }} Belum Terjadwal</span>
                        @else
                            <span class="badge bg-success-lt text-success fw-bold">Semua Terjadwal</span>
                        @endif
                    </div>
                    <div class="small text-muted mb-2">
                        Acuan kegiatan sekolah (PKL, KI, UKK, ANBK, Supervisi, dll).
                    </div>
                    <a href="{{ route('kurikulum.activities.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-primary w-100 fw-bold">
                        <i class="ti ti-list-check me-1"></i> Buka Kegiatan Sekolah
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Calendar Area (70%) -->
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Modal 1: Form Tambah / Edit Kegiatan -->
<div class="modal fade" id="modalEventForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="formEvent" onsubmit="submitEventForm(event)" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" id="event_method" value="POST">
            <input type="hidden" id="event_id" value="">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalEventTitle">
                    <i class="ti ti-calendar-plus me-1"></i> Tambah Kegiatan Sekolah
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. INFORMASI DASAR & ACUAN -->
                <div class="mb-3 p-3 bg-light rounded-3 border border-light-subtle" id="sectionSelectMasterActivity">
                    <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
                        <span><i class="ti ti-calendar-star text-primary me-1"></i> Pilih dari Acuan Kegiatan Sekolah</span>
                        <span class="text-muted small fw-normal">Opsional</span>
                    </label>
                    <select id="select_school_activity" class="form-select form-select-sm fw-semibold" onchange="onSelectSchoolActivity(this)">
                        <option value="">-- Pilih kegiatan resmi sekolah --</option>
                        @foreach($schoolActivities as $sa)
                            <option value="{{ $sa->id }}" 
                                data-name="{{ $sa->name }}"
                                data-category="{{ $sa->category }}"
                                data-color="{{ $sa->category_color }}"
                                data-description="{{ $sa->description }}"
                                data-target-participant="{{ $sa->target_participant }}">
                                {{ $sa->name }}{{ $sa->target_participant ? ' — Sasaran: ' . $sa->target_participant : '' }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="school_activity_id" id="input_school_activity_id" value="">
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Nama / Judul Kegiatan</label>
                    <input type="text" name="title" id="input_title" class="form-control" 
                           placeholder="Contoh: Ujian Praktik Kejuruan / PKL DUDI / Rapat Pleno Kelulusan" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required fw-bold">Kategori Kegiatan</label>
                        <select name="category" id="input_category" class="form-select" required onchange="onCategoryChange(this.value)">
                            @foreach($categories as $key => $cat)
                                <option value="{{ $key }}" data-color="{{ $cat['color'] }}">{{ $cat['label'] }}</option>
                            @endforeach
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
                                <label class="form-label required fw-bold small text-dark mb-1">Tanggal Mulai</label>
                                <input type="date" name="start_date" id="input_start_date" class="form-control form-control-sm" required>
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
                <button type="submit" class="btn btn-primary fw-bold" id="btnSubmitEvent">
                    <i class="ti ti-check me-1"></i> Simpan Kegiatan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Detail Agenda Pop-up -->
<div class="modal fade" id="modalEventDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" id="detailModalHeader" style="background-color: #2563eb; color: #ffffff;">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-white text-dark fw-bold" id="detailCategoryBadge">Kategori</span>
                    <span class="badge bg-danger text-white fw-bold shadow-sm" id="detailHolidayBadge" style="display: none;">
                        <i class="ti ti-calendar-off me-1"></i> Libur Sekolah (Bebas KBM)
                    </span>
                    <span class="badge bg-warning text-dark fw-bold shadow-sm" id="detailLearningImpactBadge" style="display: none;">
                        Dampak KBM
                    </span>
                    <span class="badge bg-cyan text-white fw-bold shadow-sm" id="detailTeacherImpactBadge" style="display: none;">
                        Dampak GTK
                    </span>
                    <h5 class="modal-title fw-bold text-white mb-0" id="detailTitle">Judul Kegiatan</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <i class="ti ti-calendar-event fs-2 text-primary flex-shrink-0 mt-1"></i>
                    <div>
                        <div class="fw-bold text-dark" id="detailDates">Tanggal</div>
                        <div class="text-muted small" id="detailTime">Waktu</div>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-3" id="detailLocationRow">
                    <i class="ti ti-map-pin fs-2 text-danger flex-shrink-0 mt-1"></i>
                    <div>
                        <div class="fw-bold text-dark">Lokasi / Tempat</div>
                        <div class="text-muted small" id="detailLocation">-</div>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-3" id="detailUsersRow">
                    <i class="ti ti-users fs-2 text-info flex-shrink-0 mt-1"></i>
                    <div>
                        <div class="fw-bold text-dark">Guru Terkait / Penanggung Jawab</div>
                        <div class="d-flex flex-wrap gap-1 mt-1" id="detailUsersList">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-3" id="detailDisengagedRow" style="display: none;">
                    <i class="ti ti-shield-alert fs-2 text-warning flex-shrink-0 mt-1"></i>
                    <div>
                        <div class="fw-bold text-dark">Dampak Pihak Yang Tidak Terlibat</div>
                        <div class="small text-muted mt-1" id="detailDisengagedContent">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-0">
                    <i class="ti ti-notes fs-2 text-secondary flex-shrink-0 mt-1"></i>
                    <div>
                        <div class="fw-bold text-dark">Keterangan / Catatan</div>
                        <p class="text-muted small mb-0 mt-1" id="detailDescription" style="white-space: pre-line;">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div id="detailActionLeft">
                    <!-- Action like supervision link -->
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-outline-danger" id="btnDeleteEvent" style="display: none;" onclick="deleteCurrentEvent()">
                        <i class="ti ti-trash me-1"></i> Hapus
                    </button>
                    <button type="button" class="btn btn-primary" id="btnEditEvent" style="display: none;" onclick="editCurrentEvent()">
                        <i class="ti ti-edit me-1"></i> Edit Kegiatan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->isWakaKurikulum())
<!-- Modal 3: Upload Kaldik (PDF / Excel) -->
<div class="modal fade" id="modalUploadKaldik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.calendar.upload') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="ti ti-cloud-upload me-1"></i> Upload Kalender Pendidikan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info small mb-3">
                    <i class="ti ti-info-circle me-1"></i> File Kalender Pendidikan (Kaldik) resmi Provinsi Jawa Timur / SMK PGRI Kras dalam format <strong>PDF</strong> atau <strong>Excel (.xlsx)</strong>. File asli akan disimpan dalam arsip dan dapat dibuka kapan saja.
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih Dokumen Kaldik</label>
                    <input type="file" name="kaldik_file" class="form-control" accept=".pdf,.xlsx,.xls" required>
                    <div class="form-text small">Format: <code>.pdf</code>, <code>.xlsx</code>, <code>.xls</code> (Maks. 15 MB).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Judul Arsip (Opsional)</label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Kalender Pendidikan Resmi SMK PGRI Kras TP {{ $academicYear }}">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tahun Pelajaran</label>
                    <input type="text" class="form-control bg-light" value="{{ $academicYear }}" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Catatan (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Surat Edaran Kadisdik Jatim No. ..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-upload me-1"></i> Unggah & Sinkronkan
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Modal 4: Preview Dokumen PDF Asli Kaldik -->
@if($activeUpload)
<div class="modal fade" id="modalPreviewKaldik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-file-certificate fs-2 text-teal"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">{{ $activeUpload->title }}</h5>
                        <div class="text-white-50 small">Dokumen Kalender Pendidikan Asli &bull; {{ $activeUpload->original_filename }}</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('kurikulum.calendar.kaldik.download', $activeUpload) }}" class="btn btn-sm btn-outline-light">
                        <i class="ti ti-download me-1"></i> Unduh PDF
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="background-color: #525659;">
                <iframe src="{{ route('kurikulum.calendar.kaldik.preview', $activeUpload) }}" 
                        style="width: 100%; height: 100%; border: none;" 
                        title="Pratinjau Kalender Pendidikan"></iframe>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<!-- FullCalendar 6 Global JS & Locales -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/id.global.min.js"></script>

<script>
    let calendar;
    let currentEventData = null;
    let currentAgendaScope = 'month'; // 'month' | 'week' | 'year'
    let allEventsData = [];

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');

        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'id',
            initialView: 'dayGridMonth',
            initialDate: '{{ $initialCalDate }}',
            customButtons: {
                academicYearBtn: {
                    text: 'Tahun',
                    click: function() {
                        setAgendaListScope('year');
                    }
                }
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,academicYearBtn'
            },
            buttonText: {
                today: 'Hari Ini',
                month: 'Bulan',
                week: 'Minggu'
            },
            views: {
                academicYear: {
                    type: 'multiMonth',
                    multiMonthMaxColumns: 1, // Berjajar ke bawah 1 kolom (12 bulan)
                    dateIncrement: { years: 1 },
                    fixedWeekCount: true,
                    showNonCurrentDates: false,
                    multiMonthTitleFormat: { month: 'long', year: 'numeric' },
                    titleFormat: function(info) {
                        const startYear = info.start.year;
                        return `Tahun Pelajaran ${startYear}/${startYear + 1}`;
                    },
                    visibleRange: function(currentDate) {
                        // Kalender Pendidikan: selalu dimulai Juli (awal tahun ajaran) sampai Juni tahun berikutnya
                        const y = currentDate.getFullYear();
                        return {
                            start: `${y}-07-01`,
                            end: `${y + 1}-07-01`
                        };
                    }
                }
            },
            navLinks: true,
            navLinkDayClick: function(date, jsEvent) {
                // Saat klik tanggal di kalender tahunan, zoom langsung ke tampilan bulan tanggal tersebut
                calendar.changeView('dayGridMonth', date);
            },
            editable: false,
            dayMaxEvents: 4,
            height: 'auto',
            events: function(info, successCallback, failureCallback) {
                const params = new URLSearchParams();
                params.append('academic_year', '{{ $academicYear }}');

                fetch(`{{ route('kurikulum.calendar.events') }}?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        allEventsData = data;
                        successCallback(data);
                        renderSidebarAgendaList();
                    })
                    .catch(error => {
                        console.error('Error fetching calendar events:', error);
                        failureCallback(error);
                    });
            },
            datesSet: function(info) {
                if (info.view.type === 'dayGridMonth') {
                    currentAgendaScope = 'month';
                } else if (info.view.type === 'timeGridWeek') {
                    currentAgendaScope = 'week';
                } else if (info.view.type === 'academicYear') {
                    currentAgendaScope = 'year';
                }
                const btnM = document.getElementById('btnFilterMonth');
                const btnW = document.getElementById('btnFilterWeek');
                const btnY = document.getElementById('btnFilterYear');
                if (btnM) btnM.classList.toggle('active', currentAgendaScope === 'month');
                if (btnW) btnW.classList.toggle('active', currentAgendaScope === 'week');
                if (btnY) btnY.classList.toggle('active', currentAgendaScope === 'year');

                // Sinkronkan highlight tombol Tahun di toolbar atas
                const btnYearToolbar = document.querySelector('.fc-academicYearBtn-button');
                if (btnYearToolbar) {
                    btnYearToolbar.classList.toggle('fc-button-active', info.view.type === 'academicYear');
                }

                renderSidebarAgendaList();
            },
            dateClick: function(info) {
                openCreateModal(info.dateStr);
            },
            eventClick: function(info) {
                showEventDetail(info.event);
            },
            dayCellClassNames: function(arg) {
                // Highlight Sunday as holiday column
                if (arg.date.getDay() === 0) {
                    return ['holiday-col-sun'];
                }
                return [];
            }
        });

        calendar.render();
    });

    function setAgendaListScope(scope) {
        currentAgendaScope = scope;
        document.getElementById('btnFilterMonth')?.classList.toggle('active', scope === 'month');
        document.getElementById('btnFilterWeek')?.classList.toggle('active', scope === 'week');
        document.getElementById('btnFilterYear')?.classList.toggle('active', scope === 'year');

        if (calendar) {
            if (scope === 'month' && calendar.view.type !== 'dayGridMonth') {
                calendar.changeView('dayGridMonth');
            } else if (scope === 'week' && calendar.view.type !== 'timeGridWeek') {
                calendar.changeView('timeGridWeek');
            } else if (scope === 'year' && calendar.view.type !== 'academicYear') {
                const cur = calendar.getDate();
                const m = cur.getMonth();
                const y = cur.getFullYear();
                const startYear = m >= 6 ? y : y - 1;
                calendar.changeView('academicYear', `${startYear}-07-01`);
            }
        }
        renderSidebarAgendaList();
    }

    function renderSidebarAgendaList() {
        const listEl = document.getElementById('sidebarAgendaList');
        const countEl = document.getElementById('agendaListCount');
        if (!listEl || !allEventsData) return;

        let filtered = [];
        if (!calendar) {
            filtered = allEventsData;
        } else {
            const view = calendar.view;
            const currentStart = view.activeStart;
            const currentEnd = view.activeEnd;

            filtered = allEventsData.filter(ev => {
                if (!ev.start) return false;
                const evStart = new Date(ev.start);
                const evEnd = ev.end ? new Date(ev.end) : evStart;

                if (currentAgendaScope === 'year') {
                    return true;
                }
                return evEnd >= currentStart && evStart < currentEnd;
            });
        }

        // Sort by start date ascending
        filtered.sort((a, b) => new Date(a.start) - new Date(b.start));

        if (countEl) {
            countEl.textContent = `${filtered.length} Agenda`;
        }

        if (filtered.length === 0) {
            const scopeLabel = currentAgendaScope === 'month' ? 'bulan ini' : (currentAgendaScope === 'week' ? 'minggu ini' : 'tahun ini');
            listEl.innerHTML = `
                <div class="text-center py-4 text-muted small">
                    <i class="ti ti-calendar-off fs-2 mb-1 d-block opacity-50"></i>
                    Tidak ada agenda pada ${scopeLabel}.
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(ev => {
            const props = ev.extendedProps || {};
            const color = ev.backgroundColor || '#206bc4';
            const dateFormatted = props.start_date_formatted || (ev.start ? ev.start.substring(0, 10) : '-');

            html += `
                <div class="p-2 rounded-2 border bg-white shadow-xs" 
                     onclick="focusCalendarEvent('${ev.id}')"
                     style="border-left: 3px solid ${color} !important; cursor: pointer; transition: all 0.15s ease;">
                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.8rem;" title="${ev.title}">
                        ${ev.title}
                    </div>
                    <div class="small text-muted d-flex align-items-center justify-content-between mt-1" style="font-size: 0.7rem;">
                        <span><i class="ti ti-calendar me-1 text-primary"></i> ${dateFormatted}</span>
                        <span class="badge ${props.category_badge || 'bg-light text-muted'}" style="font-size: 0.65rem;">
                            ${props.category_label || 'Agenda'}
                        </span>
                    </div>
                    ${props.location ? `
                    <div class="small text-muted text-truncate mt-1" style="font-size: 0.68rem;">
                        <i class="ti ti-map-pin me-1 text-secondary"></i> ${props.location}
                    </div>` : ''}
                </div>
            `;
        });

        listEl.innerHTML = html;
    }

    function focusCalendarEvent(eventId) {
        if (!calendar) return;
        let event = calendar.getEventById(eventId);
        if (event) {
            calendar.gotoDate(event.start);
            showEventDetail(event);
        } else {
            const raw = allEventsData.find(e => e.id === eventId);
            if (raw) {
                calendar.gotoDate(raw.start);
                showEventDetail({
                    id: raw.id,
                    title: raw.title,
                    start: new Date(raw.start),
                    startStr: raw.start,
                    backgroundColor: raw.backgroundColor,
                    extendedProps: raw.extendedProps || {}
                });
            }
        }
    }

    function refetchCalendar() {
        if (calendar) {
            calendar.refetchEvents();
        }
    }

    function onCategoryChange(category) {
        // Otomatis tandai sebagai libur jika merupakan kategori libur resmi Kaldik
        if (['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(category)) {
            const holInput = document.getElementById('input_is_holiday');
            if (holInput) {
                holInput.checked = true;
                onHolidayToggle(true);
            }
        }
    }

    function onSelectSchoolActivity(selectEl) {
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        if (selectedOpt && selectedOpt.value) {
            document.getElementById('input_title').value = selectedOpt.getAttribute('data-name') || '';
            document.getElementById('input_description').value = selectedOpt.getAttribute('data-description') || '';
            document.getElementById('input_school_activity_id').value = selectedOpt.value;

            const cat = selectedOpt.getAttribute('data-category');
            const catMap = {
                'kaldik': 'kegiatan_kurikulum',
                'smk_khusus': 'kegiatan_kurikulum',
                'akademik_ujian': 'kegiatan_kurikulum',
                'supervisi': 'supervisi',
                'kesiswaan': 'ke',
                'manajemen': 'kegiatan_kurikulum',
                'lainnya': 'lainnya'
            };
            const mappedCat = catMap[cat] || 'kegiatan_kurikulum';
            document.getElementById('input_category').value = mappedCat;
            onCategoryChange(mappedCat);
        } else {
            document.getElementById('input_school_activity_id').value = '';
        }
    }

    function onLearningImpactChange(impact) {
        const boxPulangCepat = document.getElementById('box_pulang_cepat');
        if (boxPulangCepat) {
            boxPulangCepat.style.display = (impact === 'pulang_cepat') ? 'flex' : 'none';
        }
    }

    function evaluateStaffAndDisengagedVisibility() {
        const audience = document.getElementById('input_target_audience').value;
        const rowDisengagedStudents = document.getElementById('row_disengaged_students');
        const rowDisengagedTeachers = document.getElementById('row_disengaged_teachers');
        const optStaffPulangCepat = document.getElementById('opt_staff_pulang_cepat');
        const optStaffLibur = document.getElementById('opt_staff_libur');
        const selectStaffImpact = document.getElementById('input_disengaged_staff_impact');
        const studentImpact = document.getElementById('input_learning_impact').value;
        const disengagedStudentImpact = document.getElementById('input_disengaged_student_impact').value;

        // 1. Tampilkan / Sembunyikan Siswa Lain yang Tidak Terlibat
        // Jika target audience adalah "semua" (seluruh sekolah), maka tidak ada siswa lain
        if (audience === 'semua') {
            if (rowDisengagedStudents) rowDisengagedStudents.style.display = 'none';
        } else {
            if (rowDisengagedStudents) rowDisengagedStudents.style.display = 'block';
        }

        // 2. Tampilkan / Sembunyikan Guru Lain yang Tidak Terlibat
        // Jika semua guru telah dipilih dalam checkbox GTK, sembunyikan baris "Guru Lain"
        const teacherCheckboxes = document.querySelectorAll('.user-teacher-cb');
        const totalTeachers = teacherCheckboxes.length;
        const checkedTeachers = document.querySelectorAll('.user-teacher-cb:checked').length;
        if (totalTeachers > 0 && checkedTeachers === totalTeachers) {
            if (rowDisengagedTeachers) rowDisengagedTeachers.style.display = 'none';
        } else {
            if (rowDisengagedTeachers) rowDisengagedTeachers.style.display = 'block';
        }

        // 3. ATURAN BISNIS STAF TU:
        // "Staf TU tidak bisa pulang cepat selama masih ada murid di sekolah."
        // Cek apakah masih ada murid yang KBM Normal di sekolah:
        let studentsStillInSchool = false;
        if (audience === 'semua') {
            // Semua siswa terlibat: jika KBM Normal, berarti siswa tetap di sekolah penuh
            if (studentImpact === 'normal') {
                studentsStillInSchool = true;
            }
        } else if (audience === 'guru_staf') {
            // Agenda khusus guru/staf, siswa lain tetap KBM normal jika disengaged_student_impact === 'kbm_normal'
            if (disengagedStudentImpact === 'kbm_normal') {
                studentsStillInSchool = true;
            }
        } else {
            // Agenda untuk tingkat / rombel tertentu:
            // Cek apakah siswa terlibat masih normal KBM ATAU siswa lain yang tidak terlibat masih KBM normal
            if (studentImpact === 'normal' || disengagedStudentImpact === 'kbm_normal') {
                studentsStillInSchool = true;
            }
        }

        if (studentsStillInSchool) {
            // Kunci staf TU agar tidak bisa pulang cepat / libur
            if (optStaffPulangCepat) optStaffPulangCepat.disabled = true;
            if (optStaffLibur) optStaffLibur.disabled = true;
            if (selectStaffImpact && (selectStaffImpact.value === 'pulang_cepat' || selectStaffImpact.value === 'libur')) {
                selectStaffImpact.value = 'masuk_normal';
                onDisengagedStaffImpactChange('masuk_normal');
            }
        } else {
            if (optStaffPulangCepat) optStaffPulangCepat.disabled = false;
            if (optStaffLibur) optStaffLibur.disabled = false;

            // Jika staf pulang cepat dan student_end_time terisi, default waktu pulang staf bisa mengikuti
            if (selectStaffImpact && selectStaffImpact.value === 'pulang_cepat') {
                const studentEndTime = document.getElementById('input_student_end_time');
                const staffDismissal = document.getElementById('input_disengaged_staff_dismissal_time');
                if (studentEndTime && staffDismissal && !staffDismissal.value) {
                    staffDismissal.value = studentEndTime.value || '12:00';
                }
            }
        }
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

    function openCreateModal(dateStr = '') {
        const form = document.getElementById('formEvent');
        form.reset();
        document.getElementById('event_method').value = 'POST';
        document.getElementById('event_id').value = '';
        document.getElementById('select_school_activity').value = '';
        document.getElementById('input_school_activity_id').value = '';
        document.getElementById('modalEventTitle').innerHTML = '<i class="ti ti-calendar-plus me-1"></i> Tambah Kegiatan Sekolah';
        document.getElementById('btnSubmitEvent').innerHTML = '<i class="ti ti-check me-1"></i> Simpan Kegiatan';

        const todayStr = dateStr || new Date().toISOString().split('T')[0];
        document.getElementById('input_start_date').value = todayStr;
        document.getElementById('input_end_date').value = todayStr;

        const holInput = document.getElementById('input_is_holiday');
        if (holInput) {
            holInput.checked = false;
            onHolidayToggle(false);
        }

        // Reset Peserta & Siswa Terlibat
        document.getElementById('input_learning_impact').value = 'bebas_kbm_kegiatan';
        document.getElementById('input_target_audience').value = 'guru_staf';
        onTargetAudienceChange('guru_staf');
        document.querySelectorAll('.grade-level-cb').forEach(cb => cb.checked = false);
        document.querySelectorAll('.classroom-target-cb').forEach(cb => cb.checked = false);
        document.getElementById('input_student_start_time').value = '07:00';
        document.getElementById('input_student_end_time').value = '12:00';

        // Reset Dampak GTK
        const teacherImpactEl = document.getElementById('input_teacher_impact');
        if (teacherImpactEl) {
            teacherImpactEl.value = 'bebas_mengajar_tugas';
        }
        document.getElementById('input_gtk_checkin_time').value = '07:00';
        document.getElementById('input_gtk_checkout_time').value = '13:30';
        document.querySelectorAll('.user-assign-cb').forEach(cb => cb.checked = false);

        // Reset Pihak Yang Tidak Terlibat
        document.getElementById('input_disengaged_teacher_impact').value = 'masuk_normal';
        onDisengagedTeacherImpactChange('masuk_normal');
        document.getElementById('input_disengaged_teacher_max_period').value = '';

        document.getElementById('input_disengaged_staff_impact').value = 'masuk_normal';
        onDisengagedStaffImpactChange('masuk_normal');
        document.getElementById('input_disengaged_staff_dismissal_time').value = '12:00';

        document.getElementById('input_disengaged_student_impact').value = 'kbm_normal';
        onDisengagedStudentImpactChange('kbm_normal');
        document.getElementById('input_disengaged_student_max_period').value = '';

        evaluateStaffAndDisengagedVisibility();

        new bootstrap.Modal(document.getElementById('modalEventForm')).show();
    }

    function showEventDetail(event) {
        currentEventData = event;
        const props = event.extendedProps;

        document.getElementById('detailTitle').innerText = event.title;
        document.getElementById('detailCategoryBadge').innerText = props.category_label || 'Kegiatan';
        document.getElementById('detailModalHeader').style.backgroundColor = event.backgroundColor || '#2563eb';

        const isHoliday = Boolean(props.is_holiday) || ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(props.category);
        const holBadge = document.getElementById('detailHolidayBadge');
        if (holBadge) {
            holBadge.style.display = isHoliday ? 'inline-block' : 'none';
        }

        const impactBadge = document.getElementById('detailLearningImpactBadge');
        if (impactBadge) {
            if (props.learning_impact && props.learning_impact !== 'normal') {
                impactBadge.style.display = 'inline-block';
                impactBadge.className = `badge ${props.learning_impact_badge || 'bg-warning text-dark'} fw-bold shadow-sm`;
                let impactText = props.learning_impact_label || 'Dampak KBM';
                impactBadge.innerText = impactText;
            } else {
                impactBadge.style.display = 'none';
            }
        }

        const teacherImpactBadge = document.getElementById('detailTeacherImpactBadge');
        if (teacherImpactBadge) {
            if (props.teacher_impact && props.teacher_impact !== 'normal') {
                teacherImpactBadge.style.display = 'inline-block';
                teacherImpactBadge.className = `badge ${props.teacher_impact_badge || 'bg-cyan text-white'} fw-bold shadow-sm`;
                let tText = props.teacher_impact_label || 'Dampak GTK';
                if (props.gtk_checkin_time || props.gtk_checkout_time) {
                    tText += ` (${props.gtk_checkin_time || '07:00'} - ${props.gtk_checkout_time || 'Selesai'})`;
                }
                teacherImpactBadge.innerText = tText;
            } else {
                teacherImpactBadge.style.display = 'none';
            }
        }

        // Dates formatting
        if (props.end_date_formatted && props.end_date_formatted !== props.start_date_formatted) {
            document.getElementById('detailDates').innerText = `${props.start_date_formatted} s.d. ${props.end_date_formatted}`;
        } else {
            document.getElementById('detailDates').innerText = props.start_date_formatted || event.startStr;
        }

        document.getElementById('detailTime').innerText = props.time_formatted || 'Sepanjang Hari';
        document.getElementById('detailLocation').innerText = props.location || 'SMK PGRI Kras';
        
        let descText = props.description || '';
        if (props.teacher_instructions) {
            descText += (descText ? '\n\n' : '') + '📌 Petunjuk: ' + props.teacher_instructions;
        }
        document.getElementById('detailDescription').innerText = descText || 'Tidak ada deskripsi tambahan.';

        // Users / Teachers list
        const usersList = document.getElementById('detailUsersList');
        usersList.innerHTML = '';
        if (props.users && props.users.length > 0) {
            document.getElementById('detailUsersRow').style.display = 'flex';
            props.users.forEach(u => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-blue-lt border border-primary text-dark fw-bold px-2 py-1';
                badge.innerHTML = `<i class="ti ti-user me-1 text-primary"></i> ${u.name}`;
                usersList.appendChild(badge);
            });
        } else {
            document.getElementById('detailUsersRow').style.display = 'none';
        }

        // Dampak Pihak Yang Tidak Terlibat
        const disengagedRow = document.getElementById('detailDisengagedRow');
        const disengagedContent = document.getElementById('detailDisengagedContent');
        if (disengagedRow && disengagedContent) {
            if (isHoliday) {
                disengagedRow.style.display = 'none';
            } else {
                let dHtml = '<ul class="list-unstyled mb-0">';
                
                // Guru
                let tStatus = props.disengaged_teacher_impact_label || 'Masuk Normal';
                if (props.disengaged_teacher_impact === 'pulang_cepat') {
                    tStatus += ` (Jam ke-${props.disengaged_teacher_max_period || '-'} / ${props.disengaged_teacher_dismissal_time || '-'} WIB)`;
                }
                dHtml += `<li><strong>Guru Lain:</strong> <span class="badge bg-light text-dark border ms-1">${tStatus}</span></li>`;

                // Staf
                let sStatus = props.disengaged_staff_impact_label || 'Masuk Normal';
                if (props.disengaged_staff_impact === 'pulang_cepat') {
                    sStatus += ` (Pulang ${props.disengaged_staff_dismissal_time || '12:00'} WIB)`;
                }
                dHtml += `<li class="mt-1"><strong>Staf TU:</strong> <span class="badge bg-light text-dark border ms-1">${sStatus}</span></li>`;

                // Siswa
                let stStatus = props.disengaged_student_impact_label || 'KBM Normal';
                if (props.disengaged_student_impact === 'pulang_cepat') {
                    stStatus += ` (Selesai Jam ke-${props.disengaged_student_max_period || '-'} / ${props.disengaged_student_dismissal_time || '-'} WIB)`;
                }
                dHtml += `<li class="mt-1"><strong>Siswa Lain:</strong> <span class="badge bg-light text-dark border ms-1">${stStatus}</span></li>`;
                dHtml += '</ul>';

                disengagedContent.innerHTML = dHtml;
                disengagedRow.style.display = 'flex';
            }
        }

        // Action buttons
        const btnEdit = document.getElementById('btnEditEvent');
        const btnDelete = document.getElementById('btnDeleteEvent');
        const actionLeft = document.getElementById('detailActionLeft');
        actionLeft.innerHTML = '';

        if (props.source_type === 'supervision' && props.supervision_url) {
            actionLeft.innerHTML = `<a href="${props.supervision_url}" class="btn btn-outline-indigo fw-bold">
                <i class="ti ti-external-link me-1"></i> Buka Lembar Supervisi
            </a>`;
            btnEdit.style.display = 'none';
            btnDelete.style.display = 'none';
        } else if (props.can_edit) {
            btnEdit.style.display = 'inline-block';
            btnDelete.style.display = 'inline-block';
        } else {
            btnEdit.style.display = 'none';
            btnDelete.style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('modalEventDetail')).show();
    }

    function editCurrentEvent() {
        if (!currentEventData || currentEventData.extendedProps.source_type === 'supervision') return;

        // Hide detail modal
        bootstrap.Modal.getInstance(document.getElementById('modalEventDetail')).hide();

        const event = currentEventData;
        const props = event.extendedProps;
        const dbId = event.extendedProps.db_id || event.id.replace('evt_', '');

        document.getElementById('event_method').value = 'PUT';
        document.getElementById('event_id').value = dbId;
        document.getElementById('modalEventTitle').innerHTML = '<i class="ti ti-edit me-1"></i> Edit Kegiatan Sekolah';
        document.getElementById('btnSubmitEvent').innerHTML = '<i class="ti ti-check me-1"></i> Simpan Perubahan';

        const actId = props.school_activity_id || '';
        document.getElementById('input_school_activity_id').value = actId;
        document.getElementById('select_school_activity').value = actId;

        document.getElementById('input_title').value = event.title;
        document.getElementById('input_category').value = props.category;
        document.getElementById('input_location').value = props.location || '';
        document.getElementById('input_description').value = props.description || '';

        // Dates
        const startStr = event.startStr ? event.startStr.split('T')[0] : '';
        document.getElementById('input_start_date').value = startStr;

        let endStr = startStr;
        if (event.endStr) {
            const endDate = new Date(event.end);
            if (event.allDay) {
                endDate.setDate(endDate.getDate() - 1);
            }
            endStr = endDate.toISOString().split('T')[0];
        }
        document.getElementById('input_end_date').value = endStr;

        const isHoliday = Boolean(props.is_holiday) || ['lhb', 'lu', 'ls1', 'ls2', 'cb', 'lhr'].includes(props.category);
        const holInput = document.getElementById('input_is_holiday');
        if (holInput) {
            holInput.checked = isHoliday;
            onHolidayToggle(isHoliday);
        }

        // Peserta & Siswa Terlibat
        const learningImpact = props.learning_impact || 'bebas_kbm_kegiatan';
        document.getElementById('input_learning_impact').value = learningImpact;

        const targetAudience = props.target_audience || 'guru_staf';
        document.getElementById('input_target_audience').value = targetAudience;
        onTargetAudienceChange(targetAudience);

        document.getElementById('input_student_start_time').value = props.student_start_time || '07:00';
        document.getElementById('input_student_end_time').value = props.student_end_time || '12:00';

        // Grade levels
        const targetLevels = (props.target_grade_levels || []).map(String);
        document.querySelectorAll('.grade-level-cb').forEach(cb => {
            cb.checked = targetLevels.includes(String(cb.value));
        });

        // Classrooms
        const targetClassrooms = (props.target_classroom_ids || []).map(String);
        document.querySelectorAll('.classroom-target-cb').forEach(cb => {
            cb.checked = targetClassrooms.includes(String(cb.value));
        });

        // GTK yang Terlibat
        const teacherImpact = props.teacher_impact || 'bebas_mengajar_tugas';
        const teacherImpactEl = document.getElementById('input_teacher_impact');
        if (teacherImpactEl) {
            teacherImpactEl.value = teacherImpact;
        }
        document.getElementById('input_gtk_checkin_time').value = props.gtk_checkin_time || '07:00';
        document.getElementById('input_gtk_checkout_time').value = props.gtk_checkout_time || '13:30';

        // Check assigned users
        document.querySelectorAll('.user-assign-cb').forEach(cb => {
            const userId = parseInt(cb.value, 10);
            cb.checked = props.users && props.users.some(u => u.id === userId);
        });

        // Pihak Yang Tidak Terlibat
        const disTeacher = props.disengaged_teacher_impact || 'masuk_normal';
        document.getElementById('input_disengaged_teacher_impact').value = disTeacher;
        onDisengagedTeacherImpactChange(disTeacher);
        document.getElementById('input_disengaged_teacher_max_period').value = props.disengaged_teacher_max_period || '';

        const disStaff = props.disengaged_staff_impact || 'masuk_normal';
        document.getElementById('input_disengaged_staff_impact').value = disStaff;
        onDisengagedStaffImpactChange(disStaff);
        document.getElementById('input_disengaged_staff_dismissal_time').value = props.disengaged_staff_dismissal_time || '12:00';

        const disStudent = props.disengaged_student_impact || 'kbm_normal';
        document.getElementById('input_disengaged_student_impact').value = disStudent;
        onDisengagedStudentImpactChange(disStudent);
        document.getElementById('input_disengaged_student_max_period').value = props.disengaged_student_max_period || '';

        evaluateStaffAndDisengagedVisibility();

        new bootstrap.Modal(document.getElementById('modalEventForm')).show();
    }

    function deleteCurrentEvent() {
        if (!currentEventData) return;
        const dbId = currentEventData.extendedProps.db_id || currentEventData.id.replace('evt_', '');

        if (!confirm(`Apakah Anda yakin ingin menghapus agenda "${currentEventData.title}" dari kalender?`)) {
            return;
        }

        fetch(`/kurikulum/kalender-kegiatan/${dbId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalEventDetail')).hide();
                refetchCalendar();
            } else {
                alert(data.message || 'Gagal menghapus kegiatan.');
            }
        })
        .catch(err => alert('Terjadi kendala saat menghapus kegiatan: ' + err.message));
    }

    function submitEventForm(e) {
        e.preventDefault();
        const form = document.getElementById('formEvent');
        const method = document.getElementById('event_method').value;
        const eventId = document.getElementById('event_id').value;

        const url = method === 'PUT' 
            ? `/kurikulum/kalender-kegiatan/${eventId}` 
            : `{{ route('kurikulum.calendar.store') }}`;

        const formData = new FormData(form);
        const jsonData = {};
        formData.forEach((value, key) => {
            if (key.endsWith('[]')) {
                const cleanKey = key.slice(0, -2);
                if (!jsonData[cleanKey]) jsonData[cleanKey] = [];
                jsonData[cleanKey].push(value);
            } else {
                jsonData[key] = value;
            }
        });

        jsonData['is_all_day'] = 1;
        jsonData['is_holiday'] = document.getElementById('input_is_holiday') && document.getElementById('input_is_holiday').checked ? 1 : 0;

        const submitBtn = document.getElementById('btnSubmitEvent');
        const origText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...';
        }

        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jsonData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalEventForm')).hide();
                refetchCalendar();
            } else {
                alert(data.message || 'Gagal menyimpan kegiatan.');
            }
        })
        .catch(err => alert('Terjadi kendala saat menyimpan kegiatan: ' + err.message))
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            }
        });
    }
</script>
@endpush
