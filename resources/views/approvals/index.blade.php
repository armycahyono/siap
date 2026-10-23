@extends('layouts.app')

@section('title', 'Persetujuan Kepala Sekolah')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Lembar Persetujuan & Pengesahan Supervisi',
    'subtitle' => 'Daftar hasil supervisi pembelajaran yang telah difinalisasi oleh Supervisor dan siap disetujui/disahkan oleh Kepala Sekolah atau Waka Kurikulum.',
    'icon' => 'ti ti-checklist',
    'category' => 'SUPERVISI & PENGESAHAN',
    'academicYear' => $academicYear !== 'all' ? $academicYear : null,
])

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('approvals.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Tahun Pelajaran</label>
                <select name="academic_year" class="form-select">
                    <option value="all" {{ $academicYear === 'all' ? 'selected' : '' }}>Semua Tahun Pelajaran</option>
                    @foreach($existingYears as $yr)
                        <option value="{{ $yr }}" {{ $academicYear === $yr ? 'selected' : '' }}>TP {{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Semester</label>
                <select name="semester" class="form-select">
                    <option value="all" {{ $semester === 'all' ? 'selected' : '' }}>Semua Semester</option>
                    <option value="Ganjil" {{ $semester === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester === 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Terapkan Filter
                </button>
                @if(($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== $setting->semester))
                    <a href="{{ route('approvals.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Pending Approvals -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title fw-bold text-dark mb-0">
            <i class="ti ti-clock-pause me-2 text-warning"></i> Persetujuan KS ({{ $pendingSupervisions->count() }})
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>No. Laporan</th>
                    <th>Nama Guru</th>
                    <th>Mata Pelajaran & Kelas</th>
                    <th>Supervisor</th>
                    <th>Skor Observasi</th>
                    <th>Predikat</th>
                    <th>Tanggal Masuk</th>
                    <th class="text-center">Aksi Pengesahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingSupervisions as $spv)
                <tr>
                    <td>
                        <span class="badge bg-secondary-lt fw-bold font-monospace">{{ $spv->report_number ?: 'Menunggu No.' }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">{{ $spv->teacher->name }}</div>
                        <small class="text-muted">NIP: {{ $spv->teacher->nip ?: '-' }}</small>
                    </td>
                    <td>
                        <div class="text-dark fw-semibold">{{ $spv->subject }}</div>
                        <span class="badge bg-secondary-lt small">{{ $spv->class_name }}</span>
                    </td>
                    <td>
                        <span class="badge bg-blue-lt">
                            <i class="ti ti-user-check me-1"></i>{{ $spv->supervisor->name }}
                        </span>
                    </td>
                    <td>
                        <strong class="text-primary fs-3">{{ $spv->percentage }}%</strong>
                        <small class="text-muted">({{ $spv->total_score }}/{{ $spv->max_score }})</small>
                    </td>
                    <td>
                        <span class="badge bg-{{ $spv->predicate === 'Sangat Baik' ? 'success' : 'primary' }} px-2 py-1">
                            {{ $spv->predicate }}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">{{ $spv->updated_at->translatedFormat('d M Y, H:i') }}</small>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('approvals.show', $spv) }}" class="btn btn-sm btn-primary fw-bold">
                            <i class="ti ti-certificate me-1"></i> Tinjau & Sahkan
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-circle-check fs-1 d-block mb-2 text-success"></i>
                        Semua laporan supervisi telah disetujui. Tidak ada berkas yang tertunda!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- History of Approvals -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h3 class="card-title fw-bold text-dark mb-0">
            <i class="ti ti-history me-2 text-primary"></i> Riwayat Supervisi yang Telah Disahkan
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr class="text-muted small">
                    <th>No. Laporan</th>
                    <th>Nama Guru</th>
                    <th>Mata Pelajaran</th>
                    <th>Tanggal Disahkan</th>
                    <th>Nilai Akhir</th>
                    <th>Status Dokumen</th>
                    <th class="text-center">Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historySupervisions as $spv)
                <tr>
                    <td class="font-monospace fw-bold">{{ $spv->report_number }}</td>
                    <td>{{ $spv->teacher->name }}</td>
                    <td>{{ $spv->subject }} ({{ $spv->class_name }})</td>
                    <td>{{ $spv->principal_approved_at?->translatedFormat('d F Y') }}</td>
                    <td>
                        <strong>{{ $spv->percentage }}%</strong> ({{ $spv->predicate }})
                    </td>
                    <td>
                        <span class="badge bg-success text-white">
                            <i class="ti ti-check me-1"></i> Sah & Terverifikasi
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('reports.print', $spv) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Dokumen">
                            <i class="ti ti-printer"></i>
                        </a>
                        <a href="{{ route('reports.pdf', $spv) }}" class="btn btn-sm btn-outline-danger" title="Download PDF">
                            <i class="ti ti-file-type-pdf"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat pengesahan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
