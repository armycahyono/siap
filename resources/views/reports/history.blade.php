@extends('layouts.app')

@section('title', 'Riwayat Supervisi - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $histActions = '';
    if (auth()->user()->hasPermission('view_all_supervision')) {
        $histActions = '<a href="' . route('reports.recap') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-chart-bar me-1 text-info"></i> Rekapitulasi Sekolah</a>';
    }
@endphp
@include('layouts._hero_header', [
    'title' => 'Riwayat Supervisi',
    'subtitle' => 'Arsip lengkap hasil supervisi pembelajaran dan evaluasi guru.',
    'icon' => 'ti ti-history',
    'category' => 'SUPERVISI & ARSIP',
    'actions' => $histActions
])

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('reports.history') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Guru, mapel, status..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="terjadwal" {{ $status === 'terjadwal' ? 'selected' : '' }}>Terjadwal</option>
                    <option value="praobservasi" {{ $status === 'praobservasi' ? 'selected' : '' }}>Praobservasi</option>
                    <option value="observasi" {{ $status === 'observasi' ? 'selected' : '' }}>Sedang Observasi</option>
                    <option value="menunggu_persetujuan" {{ $status === 'menunggu_persetujuan' ? 'selected' : '' }}>Persetujuan KS</option>
                    <option value="disetujui" {{ $status === 'disetujui' ? 'selected' : '' }}>Selesai / Disetujui</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="academic_year" class="form-select">
                    <option value="all" {{ $academicYear === 'all' ? 'selected' : '' }}>Semua Tahun Pelajaran</option>
                    @foreach($existingYears as $yr)
                        <option value="{{ $yr }}" {{ $academicYear === $yr ? 'selected' : '' }}>TP {{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="semester" class="form-select">
                    <option value="all" {{ $semester === 'all' ? 'selected' : '' }}>Semua Semester</option>
                    <option value="Ganjil" {{ $semester === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester === 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $status || ($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== 'all'))
                    <a href="{{ route('reports.history') }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Tabel Riwayat Supervisi -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Nama Guru</th>
                    <th>Mata Pelajaran</th>
                    <th>Tanggal Supervisi</th>
                    <th>Supervisor</th>
                    <th class="text-center">Skor</th>
                    <th class="text-center">Predikat</th>
                    <th class="text-center">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisions as $s)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($s->teacher->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $s->teacher->name }}</div>
                                <small class="text-muted">NIGS: {{ $s->teacher->nigs ?? '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold text-primary">{{ $s->subject }}</span>
                        <div class="small text-muted">{{ $s->class_name }}</div>
                    </td>
                    <td>
                        <div>{{ $s->schedule_date ? $s->schedule_date->translatedFormat('d F Y') : '-' }}</div>
                        <small class="text-muted">{{ $s->schedule_time }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $s->supervisor->name }}</div>
                        <small class="text-muted">{{ $s->supervisor->role->name }}</small>
                    </td>
                    <td class="text-center">
                        @if($s->obs_completed_at)
                            <span class="fw-bold fs-3 text-dark">{{ $s->percentage }}%</span>
                            <div class="small text-muted">{{ $s->total_score }}/{{ $s->max_score }}</div>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($s->predicate)
                            <span class="badge bg-{{ $s->predicate_color }}-lt">{{ $s->predicate }}</span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $s->status_color }} text-white">
                            {{ $s->status_label }}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('supervisions.show', $s) }}" class="btn btn-sm btn-outline-primary" title="Lihat Hasil Supervisi">
                                <i class="ti ti-eye me-1"></i> Lihat
                            </a>
                            <a href="{{ route('reports.print', $s) }}" target="_blank" class="btn btn-sm btn-primary" title="Cetak Laporan Supervisi">
                                <i class="ti ti-printer me-1"></i> Cetak
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-history-off fs-1 mb-2 d-block"></i>
                        Belum ada riwayat supervisi yang tercatat pada Tahun Pelajaran {{ $academicYear }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($supervisions->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $supervisions->links() }}
    </div>
    @endif
</div>
@endsection
