@extends('layouts.app')

@section('title', 'Jadwal Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $schedActions = '';
    if (auth()->user()->hasPermission('manage_schedules')) {
        $schedActions = '<a href="' . route('schedules.create') . '" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs"><i class="ti ti-calendar-plus me-1"></i> Buat Jadwal Supervisi Baru</a>';
    }
@endphp
@include('layouts._hero_header', [
    'title' => 'Agenda Jadwal Supervisi Guru',
    'subtitle' => 'Kelola jadwal dan penugasan tim supervisor untuk pemantauan dan evaluasi pembelajaran di kelas.',
    'icon' => 'ti ti-calendar-event',
    'category' => 'SUPERVISI AKADEMIK',
    'actions' => $schedActions
])

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('schedules.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Guru, supervisor, mapel..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Tahun Pelajaran</label>
                <select name="academic_year" class="form-select">
                    <option value="all" {{ $academicYear === 'all' ? 'selected' : '' }}>Semua Tahun</option>
                    @foreach($existingYears as $yr)
                        <option value="{{ $yr }}" {{ $academicYear === $yr ? 'selected' : '' }}>TP {{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Semester</label>
                <select name="semester" class="form-select">
                    <option value="all" {{ $semester === 'all' ? 'selected' : '' }}>Semua Semester</option>
                    <option value="Ganjil" {{ $semester === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester === 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Filter Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status Supervisi</option>
                    <option value="terjadwal" {{ $status == 'terjadwal' ? 'selected' : '' }}>Terjadwal</option>
                    <option value="praobservasi" {{ $status == 'praobservasi' ? 'selected' : '' }}>Praobservasi Selesai</option>
                    <option value="observasi" {{ $status == 'observasi' ? 'selected' : '' }}>Sedang Observasi</option>
                    <option value="pascaobservasi" {{ $status == 'pascaobservasi' ? 'selected' : '' }}>Pascaobservasi Selesai</option>
                    <option value="menunggu_persetujuan" {{ $status == 'menunggu_persetujuan' ? 'selected' : '' }}>Persetujuan KS</option>
                    <option value="disetujui" {{ $status == 'disetujui' ? 'selected' : '' }}>Disetujui / Selesai</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $status || ($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== $setting->semester))
                    <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Schedule Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Guru yang Disupervisi</th>
                    <th>Waktu Pelaksanaan</th>
                    <th>Mata Pelajaran & Kelas</th>
                    <th>Supervisor</th>
                    <th>Modul Ajar</th>
                    <th>Nilai</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $item)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($item->teacher->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $item->teacher->name }}</div>
                                <small class="text-muted">NIP: {{ $item->teacher->nip ?: '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><i class="ti ti-calendar me-1 text-primary"></i>{{ $item->schedule_date->translatedFormat('d M Y') }}</div>
                        <small class="text-muted"><i class="ti ti-clock me-1"></i>{{ $item->schedule_time }}</small>
                    </td>
                    <td>
                        <div class="text-dark fw-semibold">{{ $item->subject }}</div>
                        <span class="badge bg-secondary-lt small">{{ $item->class_name }}</span>
                    </td>
                    <td>
                        <span class="badge bg-blue-lt">
                            <i class="ti ti-user-check me-1"></i>{{ $item->supervisor->name }}
                        </span>
                    </td>
                    <td>
                        @if($item->rpp_file_path)
                            <a href="{{ asset('storage/' . $item->rpp_file_path) }}" target="_blank" class="badge bg-green-lt text-decoration-none" title="Lihat Modul Ajar">
                                <i class="ti ti-file-text me-1"></i> Tersedia
                            </a>
                        @else
                            <span class="badge bg-warning-lt" title="Guru belum mengunggah modul ajar">
                                <i class="ti ti-clock-pause me-1"></i> Belum
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($item->percentage > 0)
                            <span class="fw-bold text-primary">{{ $item->percentage }}%</span>
                            <span class="badge bg-{{ $item->predicate === 'Sangat Baik' ? 'success' : 'primary' }} ms-1">
                                {{ $item->predicate }}
                            </span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $item->status_color }} text-white">
                            {{ $item->status_label }}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('supervisions.show', $item) }}" class="btn btn-sm btn-primary" title="Buka Berkas Supervisi">
                                <i class="ti ti-eye me-1"></i> Buka
                            </a>
                            @if(in_array($item->status, ['disetujui', 'selesai']))
                            <a href="{{ route('reports.print', $item) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Laporan">
                                <i class="ti ti-printer"></i>
                            </a>
                            <a href="{{ route('reports.pdf', $item) }}" class="btn btn-sm btn-outline-danger" title="Download PDF">
                                <i class="ti ti-file-type-pdf"></i>
                            </a>
                            @endif
                            @if(auth()->user()->hasPermission('manage_schedules'))
                            <a href="{{ route('schedules.edit', $item) }}" class="btn btn-sm btn-outline-secondary" title="Edit Jadwal">
                                <i class="ti ti-pencil"></i>
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-calendar-off fs-1 d-block mb-2 text-secondary"></i>
                        Tidak ada agenda supervisi yang ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($schedules->hasPages())
    <div class="card-footer py-2">
        {{ $schedules->links() }}
    </div>
    @endif
</div>
@endsection
