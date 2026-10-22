@extends('layouts.app')

@section('title', 'Cetak Laporan Supervisi - Sistem Supervisi')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <h2 class="page-title fw-bold text-dark">
            <i class="ti ti-printer me-2 text-primary"></i>
            Cetak Laporan Supervisi
        </h2>
        <div class="text-muted small">Cetak lembar laporan resmi berstandar A4 dengan barcode QR-Code verifikasi dinas</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('reports.history') }}" class="btn btn-outline-secondary">
            <i class="ti ti-history me-1"></i> Buka Riwayat Supervisi
        </a>
    </div>
</div>

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('reports.print-index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama guru, mapel, no laporan..." value="{{ $search }}">
                </div>
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
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || ($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== 'all'))
                    <a href="{{ route('reports.print-index') }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Tabel Laporan Siap Cetak -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>No. Laporan</th>
                    <th>Nama Guru</th>
                    <th>Mata Pelajaran</th>
                    <th>Tanggal Supervisi</th>
                    <th class="text-center">Nilai & Predikat</th>
                    <th class="text-center">Pengesahan Kepsek</th>
                    <th class="w-1 text-center">Aksi Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisions as $s)
                <tr>
                    <td>
                        <span class="badge bg-purple-lt fw-bold">{{ $s->report_number ?: 'Dalam Proses' }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">{{ $s->teacher->name }}</div>
                        <small class="text-muted">NIGS: {{ $s->teacher->nigs_fix ?? '-' }}</small>
                    </td>
                    <td>
                        <span class="fw-semibold text-primary">{{ $s->subject }}</span>
                        <div class="small text-muted">{{ $s->class_name }}</div>
                    </td>
                    <td>
                        <div>{{ $s->schedule_date ? $s->schedule_date->translatedFormat('d F Y') : '-' }}</div>
                    </td>
                    <td class="text-center">
                        @if($s->obs_completed_at)
                            <div class="fw-bold text-dark fs-3">{{ $s->percentage }}%</div>
                            <span class="badge bg-{{ $s->predicate_color }}-lt">{{ $s->predicate }}</span>
                        @else
                            <span class="text-muted small fst-italic">Belum dinilai</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($s->approved_by_principal)
                            <span class="badge bg-success-lt text-success fw-bold">
                                <i class="ti ti-check me-1"></i> Disetujui
                            </span>
                        @else
                            <span class="badge bg-secondary-lt text-muted">
                                Menunggu Pengesahan
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('reports.print', $s) }}" target="_blank" class="btn btn-sm btn-primary shadow-sm" title="Buka Format Cetak A4">
                                <i class="ti ti-printer me-1"></i> Cetak A4
                            </a>
                            <a href="{{ route('reports.pdf', $s) }}" class="btn btn-sm btn-outline-secondary" title="Download File PDF">
                                <i class="ti ti-download me-1"></i> PDF
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-printer-off fs-1 mb-2 d-block"></i>
                        Belum ada dokumen laporan yang dapat dicetak pada Tahun Pelajaran {{ $academicYear }}.
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
