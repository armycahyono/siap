@extends('layouts.web')

@section('title', 'Pusat Komando & Operasional Sekolah')

@section('content')
<!-- Page Header -->
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="page-title text-navy fw-bold mb-0" style="color: #102d52;">
                <i class="ti ti-layout-dashboard me-2"></i> Dashboard Pusat Komando Sekolah
            </h2>
            <span class="badge bg-primary-lt">TP {{ $setting->academic_year }} (Semester {{ $setting->semester }})</span>
        </div>
        <div class="text-muted small">
            Pusat kendali harian operasional KBM, presensi GTK, ketertiban piket, dan kesiswaan — {{ now()->translatedFormat('l, d F Y') }}.
        </div>
    </div>
    <div class="col-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
        <a href="{{ route('kurikulum.schedules.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="ti ti-calendar-time me-1"></i> Roster KBM
        </a>
        <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-fingerprint me-1"></i> Presensi GTK
        </a>
        <a href="{{ route('piket.dashboard') }}" class="btn btn-outline-warning btn-sm text-dark">
            <i class="ti ti-shield-check me-1"></i> Layanan Piket
        </a>
        <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-outline-info btn-sm">
            <i class="ti ti-calendar-event me-1"></i> Kalender Sekolah
        </a>
        <a href="{{ route('students.index') }}" class="btn btn-outline-indigo btn-sm">
            <i class="ti ti-school me-1"></i> Data Siswa
        </a>
    </div>
</div>

<!-- Daily Operational Pulse: Live KBM, Agenda Kalender, & Petugas Piket -->
<div class="row g-3 mb-4">
    <!-- Live KBM Status -->
    <div class="col-md-4">
        <div class="card card-sm border-0 shadow-sm h-100 bg-light-subtle">
            <div class="card-body">
                <div class="d-flex align-items-center mb-1">
                    <span class="avatar avatar-xs bg-blue-lt me-2"><i class="ti ti-clock-play fs-3"></i></span>
                    <strong class="text-dark small text-uppercase fw-bold">Denyut KBM Hari Ini</strong>
                </div>
                <div class="h3 mb-1 text-primary fw-bold">{{ $detectedPeriodLabel }}</div>
                <div class="text-muted small">
                    Hari <strong>{{ $todayDay }}</strong> &bull; Total <strong>{{ $todayTotalSchedulesCount }}</strong> sesi mengajar aktif di <strong>{{ $totalClassroomsCount }}</strong> rombel.
                </div>
            </div>
        </div>
    </div>

    <!-- Agenda / Kalender Hari Ini -->
    <div class="col-md-4">
        <div class="card card-sm border-0 shadow-sm h-100 bg-light-subtle">
            <div class="card-body">
                <div class="d-flex align-items-center mb-1">
                    <span class="avatar avatar-xs bg-yellow-lt me-2"><i class="ti ti-calendar-event fs-3"></i></span>
                    <strong class="text-dark small text-uppercase fw-bold">Agenda &amp; Kalender Sekolah</strong>
                </div>
                @if($todaySchoolAgenda)
                    <div class="h3 mb-1 text-dark text-truncate fw-bold" title="{{ $todaySchoolAgenda->title }}">
                        {{ $todaySchoolAgenda->title }}
                    </div>
                    <div class="text-muted small">
                        @if($todaySchoolAgenda->is_holiday)
                            <span class="badge bg-danger text-white me-1">Libur Sekolah</span>
                        @else
                            <span class="badge bg-success-lt me-1">KBM Berjalan</span>
                        @endif
                        <span class="badge bg-blue-lt">{{ $todaySchoolAgenda->category_label ?? 'Kegiatan' }}</span>
                    </div>
                @else
                    <div class="h3 mb-1 text-muted fw-bold">KBM Normal</div>
                    <div class="text-muted small">Tidak ada agenda kegiatan khusus hari ini.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Petugas Piket Hari Ini -->
    <div class="col-md-4">
        <div class="card card-sm border-0 shadow-sm h-100 bg-light-subtle">
            <div class="card-body">
                <div class="d-flex align-items-center mb-1">
                    <span class="avatar avatar-xs bg-teal-lt me-2"><i class="ti ti-user-shield fs-3"></i></span>
                    <strong class="text-dark small text-uppercase fw-bold">Petugas Piket Hari Ini</strong>
                    <a href="{{ route('piket.dashboard') }}" class="ms-auto small text-primary text-decoration-none">Buka Piket &rarr;</a>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    @forelse($todayPiketOfficers as $piket)
                        <span class="badge bg-white text-dark border shadow-xs py-1 px-2" title="{{ $piket->teacher?->name }}">
                            <i class="ti ti-user me-1 text-teal"></i>{{ $piket->teacher?->short_name ?: Str::limit($piket->teacher?->name, 16) }}
                        </span>
                    @empty
                        <span class="text-muted small fst-italic">Belum ada petugas piket terjadwal untuk hari {{ $todayDay }}.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pusat Tindakan & Persetujuan (Pending Approvals Hub) -->
@if(isset($totalPendingActionsCount) && $totalPendingActionsCount > 0)
<div class="card border-0 shadow-sm mb-4 border-start border-danger border-4 bg-red-lt">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <span class="avatar bg-danger text-white me-3"><i class="ti ti-alert-triangle fs-2"></i></span>
                <div>
                    <h4 class="mb-0 fw-bold text-danger">Memerlukan Tindakan / Persetujuan Segera ({{ $totalPendingActionsCount }} Hal)</h4>
                    <div class="small text-muted">
                        @if($pendingGtkPermits->isNotEmpty())
                            <strong>{{ $pendingGtkPermits->count() }}</strong> permohonan izin/cuti GTK menunggu verifikasi pimpinan sekolah.
                        @endif
                    </div>
                </div>
            </div>
            <div>
                @if($pendingGtkPermits->isNotEmpty())
                <a href="{{ route('attendance.gtk-permits.index') }}" class="btn btn-sm btn-danger">
                    <i class="ti ti-check me-1"></i> Tinjau Izin GTK ({{ $pendingGtkPermits->count() }})
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Alert Izin & Cuti GTK Hari Ini -->
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

<!-- 4 Pilar Operasional Sekolah (Metric Cards) -->
<div class="row row-deck row-cards mb-4">
    <!-- 1. Presensi GTK -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="avatar bg-blue-lt"><i class="ti ti-user-check fs-2"></i></span>
                    <a href="{{ route('attendance.index') }}" class="small text-muted text-decoration-none">Detail &rarr;</a>
                </div>
                <div class="font-weight-medium text-muted small">Presensi GTK Hari Ini</div>
                <div class="h1 mb-2 fw-bold text-dark">{{ $gtkPresentCount }} <span class="text-muted fs-4 fw-normal">/ {{ $totalGtkCount }}</span></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-success-lt small">{{ $gtkPresentCount }} Hadir</span>
                    <span class="badge bg-warning-lt small">{{ $gtkPermitsTodayCount }} Izin/Cuti</span>
                    <span class="badge bg-secondary-lt small">{{ $gtkUnscannedCount }} Belum Scan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Kondisi KBM Rombel -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-azure border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="avatar bg-azure-lt"><i class="ti ti-chalkboard fs-2"></i></span>
                    <a href="{{ route('kurikulum.schedules.index') }}" class="small text-muted text-decoration-none">Roster &rarr;</a>
                </div>
                <div class="font-weight-medium text-muted small">Kondisi KBM Rombel</div>
                <div class="h1 mb-2 fw-bold text-azure">{{ $totalClassroomsCount }} <span class="text-muted fs-4 fw-normal">Rombel</span></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-azure-lt small">{{ $todayTotalSchedulesCount }} Sesi Terjadwal</span>
                    <span class="badge bg-teal-lt small">{{ $detectedPeriod ? 'Jam ke-'.$detectedPeriod : 'Di Luar Jam' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Kesiswaan & Demografi -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-indigo border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="avatar bg-indigo-lt"><i class="ti ti-school fs-2"></i></span>
                    <a href="{{ route('students.index') }}" class="small text-muted text-decoration-none">Data &rarr;</a>
                </div>
                <div class="font-weight-medium text-muted small">Peserta Didik Aktif</div>
                <div class="h1 mb-2 fw-bold text-indigo">{{ $activeStudentsCount }} <span class="text-muted fs-4 fw-normal">Siswa</span></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-indigo-lt small">X: {{ $studentsByGrade['X'] ?? 0 }}</span>
                    <span class="badge bg-purple-lt small">XI: {{ $studentsByGrade['XI'] ?? 0 }}</span>
                    <span class="badge bg-blue-lt small">XII: {{ $studentsByGrade['XII'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Layanan & Ketertiban Piket -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="avatar bg-yellow-lt"><i class="ti ti-shield-check fs-2"></i></span>
                    <a href="{{ route('piket.dashboard') }}" class="small text-muted text-decoration-none">Piket &rarr;</a>
                </div>
                <div class="font-weight-medium text-muted small">Ketertiban &amp; Layanan Piket</div>
                <div class="h1 mb-2 fw-bold text-warning">{{ $todayStudentPermitsCount }} <span class="text-muted fs-4 fw-normal">Izin Siswa</span></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-danger-lt small">{{ $todayIncidentsCount }} Kasus/Insiden</span>
                    <span class="badge bg-teal-lt small">{{ $todayPiketOfficers->count() }} Petugas Jaga</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Row: Live KBM, Agenda Kalender Sekolah, & Komposisi -->
<div class="row g-4 mb-4">
    <!-- Left Column: Monitoring KBM Sesi Hari Ini & Agenda Kegiatan Mendatang -->
    <div class="col-lg-8">
        <!-- Live Monitoring Sesi KBM Hari Ini -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-chalkboard me-2 text-primary"></i> Sesi Mengajar KBM Hari {{ $todayDay }}
                    </h3>
                    <div class="text-muted small mt-1">
                        @if($detectedPeriod)
                            Menampilkan sesi mengajar pada <strong>{{ $detectedPeriodLabel }}</strong>
                        @else
                            Ringkasan sesi mengajar aktif hari ini
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('kurikulum.schedules.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-calendar me-1"></i> Roster Lengkap
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru Pengampu</th>
                            <th class="text-center">Jam Ke-</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currentPeriodSchedules as $sched)
                            @php
                                $isTeacherPermitted = isset($gtkPermitsToday) && $gtkPermitsToday->contains('user_id', $sched->teacher_id);
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge bg-primary-lt fw-bold px-2 py-1">
                                        {{ $sched->classroom?->name ?? 'Semua Kelas' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $sched->subject?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $sched->subject?->code ?? '' }}</small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-xs bg-light rounded-circle me-2 fw-bold text-dark">
                                            {{ substr($sched->teacher?->name ?? 'G', 0, 1) }}
                                        </span>
                                        <div>
                                            <div class="text-dark fw-bold small">{{ $sched->teacher?->name ?? '-' }}</div>
                                            @if($isTeacherPermitted)
                                                <span class="badge bg-danger-lt text-danger" style="font-size: 0.7rem;">
                                                    <i class="ti ti-alert-circle me-1"></i>Sedang Izin / Cuti
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        Jam {{ $sched->start_period }} - {{ $sched->end_period }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($isTeacherPermitted)
                                        <span class="badge bg-warning-lt text-warning">Butuh Inval</span>
                                    @else
                                        <span class="badge bg-success-lt text-success">KBM Berjalan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="ti ti-calendar-off fs-1 d-block mb-2 text-secondary"></i>
                                    Tidak ada sesi mengajar yang terjadwal untuk hari {{ $todayDay }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Agenda & Kalender Kegiatan Sekolah Mendatang -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-calendar-event me-2 text-warning"></i> Agenda &amp; Kegiatan Sekolah Mendatang
                    </h3>
                    <div class="text-muted small mt-1">Kegiatan akademik, kesiswaan, dan hari libur berdasarkan Kalender Pendidikan</div>
                </div>
                <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm btn-outline-primary">Buka Kalender</a>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Kegiatan</th>
                            <th>Kategori</th>
                            <th>Tanggal Pelaksanaan</th>
                            <th>Sasaran Peserta</th>
                            <th>Status KBM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingSchoolEvents as $event)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $event->title }}</div>
                                @if($event->location)
                                    <small class="text-muted"><i class="ti ti-map-pin me-1"></i>{{ $event->location }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ $event->category_label ?? 'Kegiatan' }}
                                </span>
                            </td>
                            <td>
                                <div><i class="ti ti-calendar me-1 text-muted"></i>{{ $event->start_date?->translatedFormat('d M Y') }}</div>
                                @if($event->start_date != $event->end_date)
                                    <small class="text-muted">s/d {{ $event->end_date?->translatedFormat('d M Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $event->target_audience_label ?? 'Semua' }}
                                </span>
                            </td>
                            <td>
                                @if($event->is_holiday)
                                    <span class="badge bg-danger text-white">Libur Sekolah</span>
                                @else
                                    <span class="badge bg-success-lt text-success">KBM Berjalan</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="ti ti-calendar-off fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada agenda kegiatan yang tercatat dalam kalender pendidikan mendatang.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Komposisi Siswa, Rekap Presensi GTK, & Ringkasan Piket -->
    <div class="col-lg-4">
        <!-- Distribusi Kompetensi Keahlian (Siswa per Jurusan) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="card-title fw-bold text-dark mb-0">Komposisi Peserta Didik</h3>
                    <a href="{{ route('students.index') }}" class="small text-muted text-decoration-none">Kelola Siswa &rarr;</a>
                </div>
                <div class="space-y-3">
                    <!-- TKR -->
                    @php
                        $tkrCount = $studentsByDept['TKR'] ?? 0;
                        $tkrPct = $activeStudentsCount > 0 ? round(($tkrCount / $activeStudentsCount) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-blue">TKR</strong> (Teknik Kendaraan Ringan)</span>
                            <strong>{{ $tkrCount }} Siswa ({{ $tkrPct }}%)</strong>
                        </div>
                        <div class="progress progress-xs">
                            <div class="progress-bar bg-blue" style="width: {{ $tkrPct }}%"></div>
                        </div>
                    </div>

                    <!-- TKJ -->
                    @php
                        $tkjCount = $studentsByDept['TKJ'] ?? 0;
                        $tkjPct = $activeStudentsCount > 0 ? round(($tkjCount / $activeStudentsCount) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-teal">TKJ</strong> (Teknik Komputer &amp; Jaringan)</span>
                            <strong>{{ $tkjCount }} Siswa ({{ $tkjPct }}%)</strong>
                        </div>
                        <div class="progress progress-xs">
                            <div class="progress-bar bg-teal" style="width: {{ $tkjPct }}%"></div>
                        </div>
                    </div>

                    <!-- AKL -->
                    @php
                        $aklCount = $studentsByDept['AKL'] ?? 0;
                        $aklPct = $activeStudentsCount > 0 ? round(($aklCount / $activeStudentsCount) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-orange">AKL</strong> (Akuntansi &amp; Keuangan Lembaga)</span>
                            <strong>{{ $aklCount }} Siswa ({{ $aklPct }}%)</strong>
                        </div>
                        <div class="progress progress-xs">
                            <div class="progress-bar bg-orange" style="width: {{ $aklPct }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3 mt-3 d-flex justify-content-between text-muted small">
                    <span>Siswa Aktif: <strong>{{ $activeStudentsCount }}</strong></span>
                    <span>Alumni / Mutasi: <strong>{{ $alumniStudentsCount + $mutatedStudentsCount }}</strong></span>
                </div>
            </div>
        </div>

        <!-- Rekapitulasi Presensi GTK Hari Ini -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="card-title fw-bold text-dark mb-0">Kehadiran GTK Hari Ini</h3>
                    <a href="{{ route('attendance.index') }}" class="small text-muted text-decoration-none">Buka Absensi &rarr;</a>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Tingkat Kehadiran:</span>
                    @php
                        $presentPct = $totalGtkCount > 0 ? round(($gtkPresentCount / $totalGtkCount) * 100, 1) : 0;
                    @endphp
                    <strong class="text-primary">{{ $presentPct }}%</strong>
                </div>
                <div class="progress progress-sm mb-3">
                    <div class="progress-bar bg-success" style="width: {{ $presentPct }}%"></div>
                    <div class="progress-bar bg-warning" style="width: {{ $totalGtkCount > 0 ? round(($gtkPermitsTodayCount / $totalGtkCount) * 100, 1) : 0 }}%"></div>
                </div>
                <div class="row g-2 text-center small">
                    <div class="col-4">
                        <div class="p-2 bg-success-lt rounded border">
                            <div class="fw-bold fs-3 text-success">{{ $gtkPresentCount }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Hadir</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-warning-lt rounded border">
                            <div class="fw-bold fs-3 text-warning">{{ $gtkPermitsTodayCount }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Izin/Cuti</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-secondary-lt rounded border">
                            <div class="fw-bold fs-3 text-secondary">{{ $gtkUnscannedCount }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Belum</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ringkasan Layanan Piket Hari Ini -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="card-title fw-bold text-dark mb-0">Layanan Piket Hari Ini</h3>
                    <a href="{{ route('piket.dashboard') }}" class="small text-muted text-decoration-none">Buka Piket &rarr;</a>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-2 border">
                            <div class="h2 mb-0 fw-bold text-primary">{{ $todayStudentPermitsCount }}</div>
                            <div class="small text-muted">Izin Siswa Keluar/Masuk</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-2 border">
                            <div class="h2 mb-0 fw-bold text-danger">{{ $todayIncidentsCount }}</div>
                            <div class="small text-muted">Insiden / Kasus Siswa</div>
                        </div>
                    </div>
                </div>
                <div class="d-grid mt-3">
                    <a href="{{ route('piket.permits.create') }}" class="btn btn-outline-warning btn-sm text-dark">
                        <i class="ti ti-plus me-1"></i> Terbitkan Izin Siswa
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
