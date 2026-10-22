@extends('layouts.app')

@section('title', 'Dashboard Supervisi')

@section('content')
@include('dashboard._mobile_apk_home')

<div class="d-none d-md-block">
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-layout-dashboard me-2"></i> Dashboard Supervisi Guru
        </h2>
        <div class="text-muted small">
            Ringkasan pelaksanaan supervisi pembelajaran tahun pelajaran {{ $setting->academic_year }} (Semester {{ $setting->semester }}).
        </div>
    </div>
    <div class="col-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
        @if(auth()->user()->hasPermission('manage_teachers'))
        <a href="{{ route('teachers.create') }}" class="btn btn-primary">
            <i class="ti ti-user-plus me-1"></i> Tambah Guru
        </a>
        @endif
        @if(auth()->user()->hasPermission('manage_schedules'))
        <a href="{{ route('schedules.create') }}" class="btn btn-warning text-dark fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
            <i class="ti ti-calendar-plus me-1"></i> Jadwalkan Supervisi
        </a>
        @endif
    </div>
</div>

@if(isset($gtkPermitsToday) && $gtkPermitsToday->isNotEmpty())
<div class="alert alert-warning alert-dismissible shadow-sm mb-4 border-warning border-start border-4" role="alert">
    <div class="d-flex align-items-center mb-2">
        <i class="ti ti-bell-ringing fs-2 me-2 text-warning"></i>
        <h4 class="alert-title mb-0 fw-bold">Pemberitahuan Izin & Cuti GTK Hari Ini ({{ $gtkPermitsToday->count() }} Orang)</h4>
        <a href="{{ route('attendance.gtk-permits.index') }}" class="btn btn-sm btn-outline-warning ms-auto">
            <i class="ti ti-list-details me-1"></i> Kelola / Lihat Rincian
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-borderless table-vcenter mb-0">
            <tbody>
                @foreach($gtkPermitsToday as $permit)
                    @php
                        $badgeColors = [
                            'sakit' => 'bg-danger-lt text-danger',
                            'izin' => 'bg-warning-lt text-warning',
                            'cuti' => 'bg-azure-lt text-azure',
                            'dinas_luar' => 'bg-indigo-lt text-indigo',
                        ];
                        $badgeClass = $badgeColors[$permit->permission_type] ?? 'bg-secondary-lt';
                    @endphp
                    <tr>
                        <td class="ps-0 py-1" style="width: 250px;">
                            <strong>{{ $permit->user?->name ?? 'GTK' }}</strong>
                        </td>
                        <td class="py-1" style="width: 120px;">
                            <span class="badge {{ $badgeClass }}">{{ $permit->type_label }}</span>
                        </td>
                        <td class="py-1 text-secondary">
                            {{ $permit->reason }}
                            @if($permit->duration_days > 1)
                                <span class="badge bg-light text-muted ms-1">{{ $permit->formatted_period }} ({{ $permit->duration_days }} hari)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Metric Cards -->
<div class="row row-deck row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar bg-blue-lt"><i class="ti ti-users fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Total Guru Terdaftar</div>
                        <div class="h1 mb-0 fw-bold text-dark">{{ $teachersCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar bg-green-lt"><i class="ti ti-circle-check fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Sudah Disupervisi</div>
                        <div class="h1 mb-0 fw-bold text-success">{{ $completedSupervisions }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-warning border-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar bg-yellow-lt"><i class="ti ti-clock-pause fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Persetujuan KS</div>
                        <div class="h1 mb-0 fw-bold text-warning">{{ $pendingApprovalCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-info border-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar bg-azure-lt"><i class="ti ti-percentage fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Rata-rata Nilai Sekolah</div>
                        <div class="h1 mb-0 fw-bold text-info">{{ $averageScore }}%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Row: Table & Progress Widget -->
<div class="row g-4 mb-4">
    <!-- Upcoming Schedules -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-calendar me-2 text-primary"></i> Jadwal Supervisi Terdekat
                </h3>
                <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua Jadwal</a>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Guru</th>
                            <th>Mata Pelajaran / Kelas</th>
                            <th>Tanggal & Waktu</th>
                            <th>Supervisor</th>
                            <th>Status</th>
                            <th class="w-1 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingSchedules as $spv)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm bg-blue-lt rounded-circle me-2 fw-bold">
                                        {{ substr($spv->teacher->name, 0, 1) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $spv->teacher->name }}</div>
                                        <div class="text-muted small">NIP: {{ $spv->teacher->nip ?: '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">{{ $spv->subject }}</div>
                                <span class="badge bg-secondary-lt small">{{ $spv->class_name }}</span>
                            </td>
                            <td>
                                <div><i class="ti ti-calendar-event me-1 text-muted"></i>{{ $spv->schedule_date->translatedFormat('d M Y') }}</div>
                                <small class="text-muted">{{ $spv->schedule_time }}</small>
                            </td>
                            <td>
                                <span class="badge bg-blue-lt">
                                    <i class="ti ti-user-check me-1"></i>{{ $spv->supervisor->name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $spv->status_color }} text-white">
                                    {{ $spv->status_label }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('supervisions.show', $spv) }}" class="btn btn-sm btn-primary">
                                    Buka
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="ti ti-calendar-off fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada jadwal supervisi yang direncanakan.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Progress & Distribution Sidebar -->
    <div class="col-lg-4">
        <!-- Progress Bar Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h3 class="card-title fw-bold text-dark mb-3">Progres Supervisi Sekolah</h3>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Ketercapaian Pelaksanaan:</span>
                    <strong class="text-primary">{{ $completionRate }}%</strong>
                </div>
                <div class="progress progress-lg mb-3">
                    <div class="progress-bar bg-primary" style="width: {{ $completionRate }}%" role="progressbar"></div>
                </div>
                <div class="small text-muted mb-4">
                    Sebanyak <strong>{{ $completedSupervisions }}</strong> dari <strong>{{ $teachersCount }}</strong> guru telah selesai disupervisi.
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('schedules.index') }}" class="btn btn-outline-primary">
                        <i class="ti ti-calendar me-1"></i> Buka Agenda Supervisi
                    </a>
                    <a href="{{ route('reports.recap') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-report me-1"></i> Lihat Rekapitulasi Lengkap
                    </a>
                </div>
            </div>
        </div>

        <!-- Predicate Breakdown Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="card-title fw-bold text-dark mb-3">Distribusi Predikat Guru</h3>
                <div class="space-y-2">
                    @foreach($predicateCounts as $pred => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            @if($pred === 'Sangat Baik')
                                <span class="badge badge-sangat-baik me-1">{{ $pred }}</span>
                            @elseif($pred === 'Baik')
                                <span class="badge badge-baik me-1">{{ $pred }}</span>
                            @elseif($pred === 'Cukup')
                                <span class="badge badge-cukup me-1">{{ $pred }}</span>
                            @else
                                <span class="badge badge-perlu-bimbingan me-1">{{ $pred }}</span>
                            @endif
                        </div>
                        <span class="fw-bold">{{ $count }} Guru</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Stepper / Guidance Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h3 class="card-title fw-bold text-navy mb-4">Alur Kerja Standar Pelaksanaan Supervisi Guru</h3>
        <div class="row g-3 text-center">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-blue text-white rounded-circle mb-2 fw-bold">1</div>
                    <div class="fw-bold">Jadwal & Modul Ajar</div>
                    <small class="text-muted">Waka menetapkan jadwal & guru unggah RPP</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-blue text-white rounded-circle mb-2 fw-bold">2</div>
                    <div class="fw-bold">Praobservasi</div>
                    <small class="text-muted">Dialog kesiapan dan tujuan pembelajaran</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-blue text-white rounded-circle mb-2 fw-bold">3</div>
                    <div class="fw-bold">Observasi & Catatan</div>
                    <small class="text-muted">Penilaian 23 butir indikator + foto dokumentasi</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-green text-white rounded-circle mb-2 fw-bold">4</div>
                    <div class="fw-bold">Pengesahan Kepsek</div>
                    <small class="text-muted">Validasi digital QR Code & cetak laporan resmi</small>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.d-none d-md-block -->
@endsection
