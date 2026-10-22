@extends('layouts.app')

@section('title', 'Rekapitulasi Supervisi Sekolah')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-chart-bar me-2"></i> Rekapitulasi Hasil Supervisi Pembelajaran Sekolah
        </h2>
        <div class="text-muted small">
            Ringkasan evaluasi KBM seluruh guru untuk laporan kurikulum dan instansi terkait.
        </div>
    </div>
    <div class="col-auto d-flex gap-2">
        <a href="{{ route('reports.export-excel', ['academic_year' => $academicYear, 'semester' => $semester]) }}" class="btn btn-success">
            <i class="ti ti-file-spreadsheet me-1"></i> Ekspor ke Excel (.CSV)
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.recap') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">Tahun Pelajaran</label>
                <select name="academic_year" class="form-select">
                    <option value="all" {{ $academicYear === 'all' ? 'selected' : '' }}>Semua Tahun Pelajaran</option>
                    @foreach($existingYears as $yr)
                        <option value="{{ $yr }}" {{ $academicYear === $yr ? 'selected' : '' }}>TP {{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Semester</label>
                <select name="semester" class="form-select">
                    <option value="all" {{ $semester === 'all' ? 'selected' : '' }}>Semua Semester</option>
                    <option value="Ganjil" {{ $semester == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester == 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Filter Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="disetujui" {{ $status == 'disetujui' ? 'selected' : '' }}>Disetujui / Selesai</option>
                    <option value="menunggu_persetujuan" {{ $status == 'menunggu_persetujuan' ? 'selected' : '' }}>Persetujuan KS</option>
                    <option value="terjadwal" {{ $status == 'terjadwal' ? 'selected' : '' }}>Terjadwal</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Terapkan
                </button>
                @if($status || ($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== $setting->semester))
                    <a href="{{ route('reports.recap') }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Guru Terdaftar</div>
                <div class="h2 mb-0 fw-bold text-dark">{{ $totalTeachers }} Orang</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Telah Disupervisi & Disahkan</div>
                <div class="h2 mb-0 fw-bold text-success">{{ $completedCount }} Orang</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Rata-rata Nilai Sekolah</div>
                <div class="h2 mb-0 fw-bold text-primary">{{ $averageScore }}%</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Sedang Dalam Proses</div>
                <div class="h2 mb-0 fw-bold text-warning">{{ $inProgressCount }} Agenda</div>
            </div>
        </div>
    </div>
</div>

<!-- Recap Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h3 class="card-title fw-bold text-dark mb-0">
            Daftar Rekapitulasi Supervisi Guru (TP. {{ $academicYear }} — Semester {{ $semester }})
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover table-striped">
            <thead>
                <tr class="text-muted small">
                    <th>No</th>
                    <th>No. Laporan</th>
                    <th>Nama Guru</th>
                    <th>NIP</th>
                    <th>Mata Pelajaran & Kelas</th>
                    <th>Supervisor</th>
                    <th>Tanggal Supervisi</th>
                    <th>Total Skor</th>
                    <th>Persentase</th>
                    <th>Predikat</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisions as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="font-monospace small fw-bold">{{ $item->report_number ?: '-' }}</td>
                    <td>
                        <div class="fw-bold text-dark">{{ $item->teacher->name }}</div>
                    </td>
                    <td>{{ $item->teacher->nip ?: '-' }}</td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->subject }}</div>
                        <small class="text-muted">{{ $item->class_name }}</small>
                    </td>
                    <td>{{ $item->supervisor->name }}</td>
                    <td>{{ $item->schedule_date->translatedFormat('d/m/Y') }}</td>
                    <td><strong>{{ $item->total_score }}</strong> / {{ $item->max_score }}</td>
                    <td>
                        @if($item->percentage > 0)
                            <strong class="text-primary">{{ $item->percentage }}%</strong>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($item->predicate)
                            <span class="badge bg-{{ $item->predicate === 'Sangat Baik' ? 'success' : ($item->predicate === 'Baik' ? 'primary' : 'warning') }} text-white">
                                {{ $item->predicate }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $item->status_color }} text-white small">
                            {{ $item->status_label }}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('supervisions.show', $item) }}" class="btn btn-sm btn-outline-primary" title="Buka Detail">
                                <i class="ti ti-eye"></i>
                            </a>
                            @if(in_array($item->status, ['disetujui', 'selesai']))
                            <a href="{{ route('reports.print', $item) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Laporan">
                                <i class="ti ti-printer"></i>
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="text-center py-5 text-muted">
                        <i class="ti ti-report-off fs-1 d-block mb-2 text-secondary"></i>
                        Tidak ada data supervisi pada periode {{ $academicYear }} ({{ $semester }}).
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
