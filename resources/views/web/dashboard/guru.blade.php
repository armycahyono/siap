@extends('layouts.web')

@section('title', 'Portal GTK — SMK PGRI Kras')

@section('content')
@php
    $dashboardAnnouncement = \App\Models\Announcement::active()
        ->forUser(auth()->user())
        ->whereDoesntHave('reads', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->orderByDesc('is_pinned')
        ->latest('published_at')
        ->first();
@endphp
@if($dashboardAnnouncement)
    <div class="alert alert-important alert-primary shadow-sm mb-4 border-0" role="alert" style="background: linear-gradient(135deg, #102d52 0%, #1d4ed8 100%); color: #fff;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar avatar-md bg-white text-primary rounded-circle shadow-sm">
                    <i class="ti ti-speakerphone fs-1"></i>
                </span>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-0.5">
                        <span class="badge bg-warning text-dark fw-bold">PENGUMUMAN TERBARU</span>
                        <span class="small text-white-50">{{ $dashboardAnnouncement->published_at ? $dashboardAnnouncement->published_at->translatedFormat('d M Y, H:i') : '' }}</span>
                    </div>
                    <h3 class="fw-bold text-white mb-1">{{ $dashboardAnnouncement->title }}</h3>
                    <div class="text-white-50 small text-truncate" style="max-width: 650px;">
                        {{ strip_tags($dashboardAnnouncement->content) }}
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-white text-primary fw-bold shadow-sm">
                    <i class="ti ti-eye me-1"></i> Baca Pengumuman
                </a>
                <a href="{{ $dashboardAnnouncement->whatsapp_share_url }}" target="_blank" class="btn btn-sm btn-success shadow-sm">
                    <i class="ti ti-brand-whatsapp me-1"></i> Bagikan WA
                </a>
            </div>
        </div>
    </div>
@endif

<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $guruActions = '';
    if (!empty($myCheckInToday)) {
        $guruActions .= '
            <div class="d-flex align-items-center gap-2 bg-success text-white px-3 py-1.5 rounded-3 shadow-xs">
                <i class="ti ti-circle-check fs-2"></i>
                <div>
                    <div class="small fw-semibold" style="font-size: 0.72rem;">Presensi Hari Ini</div>
                    <div class="fw-bold font-monospace">' . $myCheckInToday->scanned_at->format('H:i') . ' WIB</div>
                </div>
            </div>
        ';
    } else {
        $guruActions .= '
            <a href="' . route('attendance.geotag.index') . '" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs">
                <i class="ti ti-map-pin me-1"></i> Absen GPS Sekarang
            </a>
        ';
    }
@endphp

@include('layouts._hero_header', [
    'title' => 'Selamat Datang, ' . $user->name,
    'subtitle' => \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y') . ' • Portal Operasional Mandiri GTK ' . $setting->school_name . '.',
    'icon' => 'ti ti-user-check',
    'category' => 'PORTAL GTK & GURU',
    'academicYear' => $setting->academic_year,
    'actions' => $guruActions,
])

<!-- Pintasan Cepat Rutinitas GTK -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <a href="{{ route('attendance.geotag.index') }}" class="btn btn-light w-100 text-start py-2 px-3 border shadow-xs h-100">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-primary-lt text-primary rounded-circle"><i class="ti ti-map-pin"></i></span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark small text-truncate">Presensi GPS</div>
                            <div class="text-muted" style="font-size: 0.7rem;">Geotagging HP</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="{{ route('attendance.subject.index') }}" class="btn btn-light w-100 text-start py-2 px-3 border shadow-xs h-100">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-success-lt text-success rounded-circle"><i class="ti ti-book"></i></span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark small text-truncate">Jurnal KBM</div>
                            <div class="text-muted" style="font-size: 0.7rem;">Absensi Kelas</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="{{ route('attendance.homeroom-permits.index') }}" class="btn btn-light w-100 text-start py-2 px-3 border shadow-xs h-100">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-warning-lt text-warning rounded-circle"><i class="ti ti-clipboard-list"></i></span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark small text-truncate">Izin Perwalian</div>
                            <div class="text-muted" style="font-size: 0.7rem;">Siswa Rombel</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="{{ route('piket.dashboard') }}" class="btn btn-light w-100 text-start py-2 px-3 border shadow-xs h-100">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xs bg-teal-lt text-teal rounded-circle"><i class="ti ti-shield-check"></i></span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark small text-truncate">Piket Sekolah</div>
                            <div class="text-muted" style="font-size: 0.7rem;">Buku Tamu &amp; Inval</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

    <!-- Portal Supervisi Guru (Desktop View) -->
    <div class="row g-3 mb-4 align-items-center">
        <div class="col">
            <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
                <i class="ti ti-school me-2"></i> Portal Supervisi Guru
            </h2>
            <div class="text-muted small">
                Selamat datang, <strong>{{ $user->name }}</strong> ({{ $user->subject }} — {{ $user->target_class ?: 'Semua Kelas' }}).
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card card-sm border-0 shadow-sm border-start border-primary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-blue-lt"><i class="ti ti-calendar-event fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted small">Agenda Supervisi Anda</div>
                            <div class="h2 mb-0 fw-bold text-dark">{{ $supervisions->count() }} Kegiatan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card card-sm border-0 shadow-sm border-start border-success border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-green-lt"><i class="ti ti-circle-check fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted small">Supervisi Selesai & Disahkan</div>
                            <div class="h2 mb-0 fw-bold text-success">{{ $completedCount }} Kegiatan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card card-sm border-0 shadow-sm border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-yellow-lt"><i class="ti ti-clock fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted small">Menunggu Pelaksanaan</div>
                            <div class="h2 mb-0 fw-bold text-warning">{{ $pendingActionCount }} Kegiatan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-list-details me-2 text-primary"></i> Daftar Berkas Supervisi Pembelajaran Anda
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr class="text-muted small">
                                <th>Tanggal & Waktu</th>
                                <th>Mata Pelajaran & Kelas</th>
                                <th>Supervisor</th>
                                <th>Modul Ajar / RPP</th>
                                <th>Hasil & Predikat</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supervisions as $spv)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $spv->schedule_date->translatedFormat('d F Y') }}</div>
                                    <small class="text-muted">{{ $spv->schedule_time }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $spv->subject }}</div>
                                    <span class="badge bg-secondary-lt small">{{ $spv->class_name }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-blue-lt">
                                        <i class="ti ti-user-check me-1"></i>{{ $spv->supervisor->name }}
                                    </span>
                                </td>
                                <td>
                                    @if($spv->rpp_file_path)
                                        <a href="{{ asset('storage/' . $spv->rpp_file_path) }}" target="_blank" class="badge bg-green-lt text-decoration-none">
                                            <i class="ti ti-file-check me-1"></i> Terunggah
                                        </a>
                                    @else
                                        <span class="badge bg-warning-lt">
                                            <i class="ti ti-file-upload me-1"></i> Belum Diunggah
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($spv->percentage > 0)
                                        <div>
                                            <strong class="text-primary">{{ $spv->percentage }}%</strong>
                                            <span class="badge bg-{{ $spv->predicate === 'Sangat Baik' ? 'success' : 'primary' }} ms-1">
                                                {{ $spv->predicate }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-muted small">Belum dinilai</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $spv->status_color }} text-white">
                                        {{ $spv->status_label }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="{{ route('supervisions.show', $spv) }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-eye me-1"></i> Buka Berkas
                                        </a>
                                        @if(in_array($spv->status, ['disetujui', 'selesai']))
                                        <a href="{{ route('reports.print', $spv) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="ti ti-printer"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ti ti-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada jadwal supervisi yang ditugaskan kepada Anda.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
