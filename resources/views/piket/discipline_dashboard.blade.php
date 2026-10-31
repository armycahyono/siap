@extends('layouts.app')

@section('title', 'Dashboard Kedisiplinan')

@section('content')
<div class="container-xl">
    <!-- Header Dashboard Kedisiplinan -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="avatar avatar-md bg-danger-lt text-danger rounded-circle">
                    <i class="ti ti-shield-exclamation fs-1"></i>
                </span>
            </div>
            <div class="col">
                <div class="page-pretitle text-uppercase fw-bold text-danger" style="letter-spacing: 0.05rem;">
                    SISTEM INFORMASI KETERTIBAN &amp; KEDISIPLINAN SISWA
                </div>
                <h2 class="page-title text-dark fw-bold">
                    Dashboard Kedisiplinan
                </h2>
                <div class="text-muted small mt-1">
                    Pemantauan ketertiban siswa, penanganan pelanggaran, verifikasi sanksi, dan koordinasi Tim Kedisiplinan.
                </div>
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
                <!-- Selector Tahun Ajaran -->
                <form action="{{ Route::has('discipline.dashboard') ? route('discipline.dashboard') : url('/kedisiplinan') }}" method="GET" class="d-flex align-items-center gap-1">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="ti ti-calendar me-1"></i> Tahun Ajaran:
                        </span>
                        <select name="academic_year" class="form-select form-select-sm fw-bold border-start-0 ps-0" onchange="this.form.submit()" style="min-width: 140px;">
                            @foreach($availableYears as $ay)
                                <option value="{{ $ay->name }}" {{ $ay->name === $academicYear ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                @if(auth()->user()->canRecordPiketIncident())
                <a href="{{ route('piket.incidents.create') }}" class="btn btn-sm btn-danger shadow-xs fw-bold">
                    <i class="ti ti-plus me-1"></i> Catat Kasus Baru
                </a>
                @endif
                <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                    <i class="ti ti-list me-1"></i> Daftar Kasus
                </a>
                <a href="{{ route('piket.reports.index', ['tab' => 'incidents']) }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                    <i class="ti ti-report me-1"></i> Rekap Pelanggaran
                </a>
            </div>
        </div>
    </div>

    <!-- 4 Kartu Metrik Utama -->
    <div class="row row-cards mb-4">
        <!-- 1. Pelanggaran Hari Ini -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-danger text-white">
                                <i class="ti ti-alert-triangle fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Pelanggaran Hari Ini</div>
                            <div class="h1 fw-bold mb-0 text-dark">
                                {{ $todayIncidentsCount }}
                                <span class="fs-4 text-muted font-weight-normal">kejadian</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Belum Ditangani -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-warning text-dark">
                                <i class="ti ti-clock-pause fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Belum Ditangani</div>
                            <div class="h1 fw-bold mb-0 text-warning">
                                {{ $unhandledCount }}
                                <span class="fs-4 text-muted font-weight-normal">kasus</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Tindak Lanjut -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-info text-white">
                                <i class="ti ti-arrow-forward fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Tindak Lanjut</div>
                            <div class="h1 fw-bold mb-0 text-info">
                                {{ $followUpCount }}
                                <span class="fs-4 text-muted font-weight-normal">rujukan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Pemantauan Aktif -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-teal border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-teal text-white">
                                <i class="ti ti-eye-check fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Pemantauan Aktif</div>
                            <div class="h1 fw-bold mb-0 text-teal">
                                {{ $activeMonitoringCount }}
                                <span class="fs-4 text-muted font-weight-normal">siswa</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris Grafik: Tren Pelanggaran & Jenis Pelanggaran -->
    <div class="row row-cards mb-4">
        <!-- Grafik Tren Pelanggaran -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-chart-line text-danger me-2"></i> Tren Pelanggaran (7 Hari Terakhir)
                    </h4>
                    <span class="badge bg-danger-lt">Harian</span>
                </div>
                <div class="card-body">
                    <div id="chart-trend-pelanggaran" style="min-height: 240px;"></div>
                </div>
            </div>
        </div>

        <!-- Grafik Jenis Pelanggaran -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-chart-pie text-warning me-2"></i> Jenis Pelanggaran
                    </h4>
                    <span class="badge bg-warning-lt">Distribusi Kategori</span>
                </div>
                <div class="card-body">
                    <div id="chart-jenis-pelanggaran" style="min-height: 240px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Tugas dan Kasus yang Membutuhkan Tindakan -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-checklist text-danger me-2"></i> Tugas dan Kasus yang Membutuhkan Tindakan
            </h4>
            <div class="card-actions">
                <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-outline-danger">
                    Lihat Semua Kasus <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Waktu &amp; Tanggal</th>
                        <th style="width: 20%;">Nama Siswa &amp; Kelas</th>
                        <th style="width: 12%;">Kategori</th>
                        <th style="width: 25%;">Uraian Kejadian</th>
                        <th style="width: 15%;">Barang Sitaan</th>
                        <th style="width: 13%;">Status &amp; Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($actionRequiredCases as $case)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $case->incident_date?->format('d/m/Y') }}</div>
                            <span class="text-muted small">{{ substr($case->incident_time, 0, 5) }} WIB</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $case->student_name }}</div>
                            <span class="badge bg-blue-lt">{{ $case->classroom?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-danger-lt">{{ $case->category_label }}</span>
                        </td>
                        <td>
                            <div class="text-dark small text-truncate" style="max-width: 280px;" title="{{ $case->description }}">
                                {{ $case->description }}
                            </div>
                            <span class="text-muted small fst-italic">Pencatat: {{ $case->recordedBy?->name ?? 'Piket' }}</span>
                        </td>
                        <td>
                            @if($case->confiscated_items)
                                <span class="badge bg-red-lt">
                                    <i class="ti ti-package me-1"></i> {{ $case->confiscated_items }}
                                </span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                @if($case->verification_status === 'menunggu_verifikasi')
                                    <span class="badge bg-warning text-dark">
                                        <i class="ti ti-clock me-1"></i> Menunggu Verifikasi
                                    </span>
                                @elseif($case->is_verified)
                                    <span class="badge bg-success text-white">
                                        <i class="ti ti-check me-1"></i> Terverifikasi
                                    </span>
                                @else
                                    <span class="badge bg-secondary text-white">{{ ucfirst($case->status) }}</span>
                                @endif
                                <a href="{{ route('piket.incidents.index', ['search' => $case->student_name]) }}" class="btn btn-xs btn-outline-danger mt-1">
                                    Kelola Tindakan
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="ti ti-circle-check fs-1 text-success d-block mb-1"></i>
                            Tidak ada kasus tertunda yang memerlukan tindakan verifikasi saat ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Baris: Jadwal Piket & Laporan Kejadian Hari Ini -->
    <div class="row row-cards mb-4">
        <!-- Jadwal Petugas Piket Hari Ini -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-calendar-user text-primary me-2"></i> Petugas Piket Hari Ini ({{ $todayDay }})
                    </h4>
                    <span class="badge {{ $todayJournal ? 'bg-success-lt' : 'bg-warning-lt' }}">
                        {{ $todayJournal ? 'Jurnal Dibuka' : 'Jurnal Belum Ada' }}
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($todayPiketSchedules as $schedule)
                        <div class="list-group-item d-flex align-items-center justify-content-between py-2.5">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle me-3">
                                    {{ strtoupper(substr($schedule->teacher?->name ?? 'P', 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $schedule->teacher?->name ?? '-' }}</div>
                                    <div class="text-muted small">Piket {{ $schedule->day }} &bull; {{ $schedule->academic_year }}</div>
                                </div>
                            </div>
                            <span class="badge bg-blue-lt">Petugas</span>
                        </div>
                        @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-calendar-off fs-1 text-secondary opacity-50 d-block mb-1"></i>
                            Tidak ada jadwal piket yang terdaftar untuk hari {{ $todayDay }}.
                        </div>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer bg-light py-2 text-end">
                    <a href="{{ route('piket.schedules.index') }}" class="btn btn-sm btn-link text-primary p-0">
                        Kelola Jadwal Piket <i class="ti ti-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Laporan Kejadian Terkini di Piket -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-file-alert text-danger me-2"></i> Laporan Kejadian Piket Terkini
                    </h4>
                    <a href="{{ route('piket.incidents.index') }}" class="small text-danger fw-bold text-decoration-none">
                        Semua Laporan <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal &amp; Waktu</th>
                                <th>Siswa</th>
                                <th>Kategori</th>
                                <th>Tindakan Piket</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPiketIncidents as $inc)
                            <tr>
                                <td class="text-nowrap">
                                    <div class="fw-semibold text-dark">{{ $inc->incident_date?->format('d/m/Y') }}</div>
                                    <div class="text-muted small">{{ substr($inc->incident_time, 0, 5) }} WIB</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $inc->student_name }}</div>
                                    <span class="badge bg-blue-lt small" style="font-size: 0.65rem;">{{ $inc->classroom?->name ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-danger-lt">{{ $inc->category_label }}</span>
                                </td>
                                <td>
                                    <div class="text-dark small text-truncate" style="max-width: 200px;">{{ $inc->action_taken }}</div>
                                    <span class="badge bg-purple-lt small" style="font-size: 0.65rem;">{{ $inc->follow_up_label }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Belum ada catatan kejadian pada periode ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Visitasi dan Pemantauan Siswa -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-home-heart text-teal me-2"></i> Visitasi dan Pemantauan Siswa Terkini
            </h4>
            <div class="card-actions">
                <a href="{{ route('visitations.index') }}" class="btn btn-sm btn-outline-teal">
                    Buka Modul Visitasi <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Tanggal &amp; Waktu</th>
                        <th style="width: 22%;">Siswa &amp; Kelas</th>
                        <th style="width: 18%;">Jenis Visitasi</th>
                        <th style="width: 20%;">Petugas Pelaksana</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 10%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentVisitations as $vis)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ ($vis->executed_date ?? $vis->planned_date)?->format('d/m/Y') ?? '-' }}</div>
                            <span class="text-muted small">{{ $vis->executed_date ? 'Terlaksana' : 'Rencana' }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $vis->student?->name ?? '-' }}</div>
                            <span class="badge bg-blue-lt">{{ $vis->student?->classroom?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-teal-lt">{{ $vis->visit_type_label }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $vis->primaryOfficer?->name ?? '-' }}</div>
                            <div class="text-muted small">Petugas Utama</div>
                        </td>
                        <td>
                            <span class="badge {{ $vis->status_badge_class }}">
                                {{ $vis->status_label }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('visitations.show', $vis) }}" class="btn btn-xs btn-outline-secondary">
                                <i class="ti ti-eye me-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="ti ti-home-off fs-1 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada riwayat visitasi dan pemantauan pada tahun ajaran ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Grafik Tren Pelanggaran (Line Chart)
    var trendDates = @json($trendDates);
    var trendCounts = @json($trendCounts);

    var trendOptions = {
        series: [{
            name: 'Kejadian Pelanggaran',
            data: trendCounts
        }],
        chart: {
            type: 'area',
            height: 240,
            toolbar: { show: false },
            fontFamily: 'inherit',
            sparkline: { enabled: false }
        },
        colors: ['#d63939'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2.5 },
        xaxis: {
            categories: trendDates,
            labels: { style: { colors: '#6c757d', fontSize: '11px' } }
        },
        yaxis: {
            labels: { style: { colors: '#6c757d', fontSize: '11px' } },
            min: 0,
            forceNiceScale: true
        },
        tooltip: { theme: 'light' },
        grid: { strokeDashArray: 4 }
    };

    var chartTrend = new ApexCharts(document.querySelector("#chart-trend-pelanggaran"), trendOptions);
    chartTrend.render();

    // 2. Grafik Jenis Pelanggaran (Donut Chart)
    var catLabels = @json($chartCategoryLabels);
    var catData = @json($chartCategoryData);

    if (catData.length === 0 || catData.every(x => x === 0)) {
        document.querySelector("#chart-jenis-pelanggaran").innerHTML = '<div class="text-center text-muted py-5"><i class="ti ti-chart-pie-off fs-1 opacity-50 d-block mb-1"></i>Belum ada data pelanggaran tercatat.</div>';
    } else {
        var jenisOptions = {
            series: catData,
            labels: catLabels,
            chart: {
                type: 'donut',
                height: 240,
                fontFamily: 'inherit'
            },
            colors: ['#d63939', '#f59f00', '#206bc4', '#4299e1', '#ae3ec9', '#74b816'],
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: true },
            tooltip: { theme: 'light' }
        };

        var chartJenis = new ApexCharts(document.querySelector("#chart-jenis-pelanggaran"), jenisOptions);
        chartJenis.render();
    }
});
</script>
@endpush
@endsection
