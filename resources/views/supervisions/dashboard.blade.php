@extends('layouts.web')

@section('title', 'Dashboard Supervisi Guru')

@section('content')
<!-- Header Page -->
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="page-title text-navy fw-bold mb-0" style="color: #102d52;">
                <i class="ti ti-git-fork me-2 text-warning"></i> Dashboard Supervisi Guru
            </h2>
            <span class="badge bg-primary-lt">TP {{ $academicYear }} (Semester {{ $semester }})</span>
        </div>
        <div class="text-muted small">
            Pusat pemantauan, evaluasi mutu pembelajaran, dan progres pelaksanaan supervisi akademik guru.
        </div>
    </div>
    <div class="col-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
        @if(auth()->user()->hasPermission('manage_schedules'))
        <a href="{{ route('schedules.create') }}" class="btn btn-warning text-dark fw-bold btn-sm" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
            <i class="ti ti-calendar-plus me-1"></i> Jadwalkan Supervisi
        </a>
        @endif
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="ti ti-calendar-event me-1"></i> Agenda Jadwal
        </a>
        <a href="{{ route('reports.recap') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-report me-1"></i> Rekapitulasi Nilai
        </a>
    </div>
</div>

<!-- Pending Approvals Alert -->
@if(isset($pendingApprovalCount) && $pendingApprovalCount > 0 && (auth()->user()->hasPermission('approve_supervision') || auth()->user()->hasRole('kepala_sekolah') || auth()->user()->hasRole('super_admin')))
<div class="alert alert-warning alert-dismissible shadow-sm mb-4 border-warning border-start border-4" role="alert">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <i class="ti ti-clock-pause fs-2 me-2 text-warning"></i>
            <div>
                <h4 class="alert-title mb-0 fw-bold">Pemberitahuan Persetujuan Supervisi</h4>
                <div class="text-secondary small">
                    Terdapat <strong>{{ $pendingApprovalCount }}</strong> berkas supervisi yang telah selesai dievaluasi dan sedang menunggu tanda tangan/pengesahan Kepala Sekolah.
                </div>
            </div>
        </div>
        <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-warning text-dark fw-bold">
            <i class="ti ti-checklist me-1"></i> Buka Antrean Persetujuan ({{ $pendingApprovalCount }})
        </a>
    </div>
</div>
@endif

<!-- Metric Cards: 4 Pilar Supervisi -->
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

<!-- Main Row: Tabel Jadwal Terdekat & Sidebar Progres -->
<div class="row g-4 mb-4">
    <!-- Left Column: Upcoming Schedules & Recent Supervisions -->
    <div class="col-lg-8">
        <!-- Upcoming Schedules -->
        <div class="card border-0 shadow-sm mb-4">
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
                            <th>Tanggal &amp; Waktu</th>
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
                                        {{ substr($spv->teacher?->name ?? 'G', 0, 1) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $spv->teacher?->name ?? '-' }}</div>
                                        <div class="text-muted small">NIP: {{ $spv->teacher?->nip ?: '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">{{ $spv->subject }}</div>
                                <span class="badge bg-secondary-lt small">{{ $spv->class_name }}</span>
                            </td>
                            <td>
                                <div><i class="ti ti-calendar-event me-1 text-muted"></i>{{ $spv->schedule_date?->translatedFormat('d M Y') }}</div>
                                <small class="text-muted">{{ $spv->schedule_time }}</small>
                            </td>
                            <td>
                                <span class="badge bg-blue-lt">
                                    <i class="ti ti-user-check me-1"></i>{{ $spv->supervisor?->name ?? '-' }}
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

        <!-- Recent Completed Supervisions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-history me-2 text-success"></i> Aktivitas Supervisi Terbaru
                </h3>
                <a href="{{ route('reports.history') }}" class="btn btn-sm btn-outline-secondary">Riwayat Lengkap</a>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Guru</th>
                            <th>Mata Pelajaran</th>
                            <th>Nilai Akhir</th>
                            <th>Predikat</th>
                            <th>Status Dokumen</th>
                            <th class="w-1 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSupervisions as $rspv)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $rspv->teacher?->name ?? '-' }}</div>
                                <small class="text-muted">{{ $rspv->class_name }}</small>
                            </td>
                            <td>{{ $rspv->subject }}</td>
                            <td>
                                @if($rspv->percentage !== null)
                                    <strong class="text-dark">{{ $rspv->percentage }}%</strong>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($rspv->predicate)
                                    <span class="badge badge-{{ Str::slug($rspv->predicate) }}">{{ $rspv->predicate }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $rspv->status_color }} text-white">
                                    {{ $rspv->status_label }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('supervisions.show', $rspv) }}" class="btn btn-sm btn-outline-primary">
                                    Buka
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Belum ada berkas supervisi yang selesai dievaluasi.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Progres, Predikat & Navigasi Tahapan -->
    <div class="col-lg-4">
        <!-- Progress Bar Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="card-title fw-bold text-dark mb-0">Progres Ketercapaian</h3>
                    <span class="badge bg-primary-lt fw-bold">{{ $completionRate }}%</span>
                </div>
                <div class="progress progress-lg mb-3">
                    <div class="progress-bar bg-primary" style="width: {{ $completionRate }}%" role="progressbar"></div>
                </div>
                <div class="small text-muted mb-4">
                    Sebanyak <strong>{{ $completedSupervisions }}</strong> dari <strong>{{ $teachersCount }}</strong> guru telah selesai disupervisi pada semester ini.
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('schedules.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-calendar me-1"></i> Buka Agenda Supervisi
                    </a>
                    <a href="{{ route('reports.recap') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-report me-1"></i> Lihat Rekapitulasi Lengkap
                    </a>
                </div>
            </div>
        </div>

        <!-- Predicate Breakdown Card -->
        <div class="card border-0 shadow-sm mb-4">
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

        <!-- Menu Pintas Tahapan Supervisi -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="card-title fw-bold text-dark mb-3">Akses Cepat Tahapan</h3>
                <div class="list-group list-group-flush">
                    <a href="{{ route('supervisions.preobs') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                        <i class="ti ti-clipboard-list fs-2 text-info me-3"></i>
                        <div>
                            <div class="fw-bold">Tahap 1: Pra Observasi</div>
                            <small class="text-muted">Dialog kesiapan & telaah modul ajar</small>
                        </div>
                    </a>
                    <a href="{{ route('supervisions.obs') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                        <i class="ti ti-notebook fs-2 text-indigo me-3"></i>
                        <div>
                            <div class="fw-bold">Tahap 2: Observasi Kelas</div>
                            <small class="text-muted">Penilaian 23 butir indikator pembelajaran</small>
                        </div>
                    </a>
                    <a href="{{ route('supervisions.docs') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                        <i class="ti ti-photo fs-2 text-cyan me-3"></i>
                        <div>
                            <div class="fw-bold">Tahap 3: Dokumentasi Foto</div>
                            <small class="text-muted">Bukti foto pelaksanaan pembelajaran</small>
                        </div>
                    </a>
                    <a href="{{ route('supervisions.results') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                        <i class="ti ti-award fs-2 text-yellow me-3"></i>
                        <div>
                            <div class="fw-bold">Tahap 4: Hasil & Pasca Observasi</div>
                            <small class="text-muted">Umpan balik, skor akhir & tindak lanjut</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Stepper Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h3 class="card-title fw-bold text-navy mb-4">Alur Kerja Standar Pelaksanaan Supervisi Guru</h3>
        <div class="row g-3 text-center">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-blue text-white rounded-circle mb-2 fw-bold">1</div>
                    <div class="fw-bold">Jadwal &amp; Modul Ajar</div>
                    <small class="text-muted">Waka menetapkan jadwal &amp; guru unggah RPP</small>
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
                    <div class="fw-bold">Observasi &amp; Catatan</div>
                    <small class="text-muted">Penilaian 23 butir indikator + foto dokumentasi</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="avatar avatar-md bg-green text-white rounded-circle mb-2 fw-bold">4</div>
                    <div class="fw-bold">Pengesahan Kepsek</div>
                    <small class="text-muted">Validasi digital QR Code &amp; cetak laporan resmi</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
