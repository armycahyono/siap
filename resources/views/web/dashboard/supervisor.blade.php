@extends('layouts.web')

@section('title', 'Dashboard Supervisor')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-eye-check me-2"></i> Dashboard Penilai (Supervisor)
        </h2>
        <div class="text-muted small">
            Anda ditugaskan menilai guru-guru berikut pada Tahun Pelajaran {{ $setting->academic_year }} (Semester {{ $setting->semester }}).
        </div>
    </div>
</div>

<div class="row row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar bg-blue-lt"><i class="ti ti-clipboard-list fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Guru yang Ditugaskan</div>
                        <div class="h2 mb-0 fw-bold text-dark">{{ $totalAssigned }}</div>
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
                        <span class="avatar bg-green-lt"><i class="ti ti-check fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Selesai Dinilai</div>
                        <div class="h2 mb-0 fw-bold text-success">{{ $completedCount }}</div>
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
                        <span class="avatar bg-yellow-lt"><i class="ti ti-loader fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Sedang Dalam Proses</div>
                        <div class="h2 mb-0 fw-bold text-warning">{{ $inProgressCount }}</div>
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
                        <span class="avatar bg-azure-lt"><i class="ti ti-calendar fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium text-muted small">Belum Dimulai</div>
                        <div class="h2 mb-0 fw-bold text-info">{{ $scheduledCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-checklist me-2 text-primary"></i> Daftar Guru yang Ditugaskan untuk Disupervisi
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Nama Guru</th>
                            <th>Mata Pelajaran & Kelas</th>
                            <th>Tanggal Supervisi</th>
                            <th>Modul Ajar Guru</th>
                            <th>Skor / Predikat</th>
                            <th>Status Supervisi</th>
                            <th class="text-center">Aksi Pelaksanaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignedSupervisions as $spv)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm bg-blue-lt rounded-circle me-2 fw-bold">
                                        {{ substr($spv->teacher->name, 0, 1) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $spv->teacher->name }}</div>
                                        <small class="text-muted">NIP: {{ $spv->teacher->nip ?: '-' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">{{ $spv->subject }}</div>
                                <span class="badge bg-secondary-lt small">{{ $spv->class_name }}</span>
                            </td>
                            <td>
                                <div><i class="ti ti-calendar me-1 text-muted"></i>{{ $spv->schedule_date->translatedFormat('d M Y') }}</div>
                                <small class="text-muted">{{ $spv->schedule_time }}</small>
                            </td>
                            <td>
                                @if($spv->rpp_file_path)
                                    <a href="{{ asset('storage/' . $spv->rpp_file_path) }}" target="_blank" class="badge bg-green-lt text-decoration-none">
                                        <i class="ti ti-file-download me-1"></i> Lihat Modul
                                    </a>
                                @else
                                    <span class="badge bg-secondary-lt text-muted">Belum ada file</span>
                                @endif
                            </td>
                            <td>
                                @if($spv->percentage > 0)
                                    <strong>{{ $spv->percentage }}%</strong>
                                    <span class="badge bg-{{ $spv->predicate === 'Sangat Baik' ? 'success' : 'primary' }} ms-1">
                                        {{ $spv->predicate }}
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $spv->status_color }} text-white">
                                    {{ $spv->status_label }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($spv->status === 'terjadwal')
                                    <a href="{{ route('supervisions.pre-observation', $spv) }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-clipboard-list me-1"></i> Mulai Praobservasi
                                    </a>
                                @elseif($spv->status === 'praobservasi')
                                    <a href="{{ route('supervisions.observation', $spv) }}" class="btn btn-sm btn-warning text-dark fw-bold">
                                        <i class="ti ti-notebook me-1"></i> Nilai Observasi
                                    </a>
                                @elseif($spv->status === 'observasi')
                                    <a href="{{ route('supervisions.post-observation', $spv) }}" class="btn btn-sm btn-info text-white">
                                        <i class="ti ti-message-dots me-1"></i> Catatan Pasca
                                    </a>
                                @else
                                    <a href="{{ route('supervisions.show', $spv) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye me-1"></i> Berkas Supervisi
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-clipboard-off fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada penugasan supervisi untuk akun Anda.
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
