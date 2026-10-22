@extends('layouts.app')

@section('title', 'Jurnal Piket Harian')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Buku Jurnal Harian
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-notebook text-primary me-2 fs-1"></i>
                    Jurnal Piket Harian
                </h2>
                <div class="text-secondary mt-1">
                    Rekam jejak kehadiran petugas, monitoring kelas kosong, perizinan siswa, dan catatan umum harian.
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="{{ route('piket.journals.today') }}" class="btn btn-sm btn-primary d-flex align-items-center shadow-sm">
                    <i class="ti ti-calendar-plus me-2 fs-3"></i> Buka / Isi Jurnal Hari Ini
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bulan -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('piket.journals.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto fw-bold text-dark small">
                    <i class="ti ti-filter me-1"></i> Filter Bulan:
                </div>
                <div class="col-md-3">
                    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Terapkan</button>
                    @if($month)
                        <a href="{{ route('piket.journals.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Jurnal -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal Jurnal</th>
                        <th>Koordinator Piket</th>
                        <th>Petugas Hadir</th>
                        <th>Aktivitas & Catatan</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journals as $j)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $j->date?->translatedFormat('d F Y') }}</div>
                                <span class="badge bg-blue-lt">{{ $j->date?->translatedFormat('l') }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $j->coordinator?->name ?? 'Belum Ditentukan' }}</div>
                                <span class="text-muted small">{{ $j->coordinator?->formatted_id ?? '-' }}</span>
                            </td>
                            <td>
                                @php
                                    $present = $j->attendances->where('status', 'hadir')->count();
                                    $total = $j->attendances->count();
                                @endphp
                                <span class="badge {{ $present === $total && $total > 0 ? 'bg-success-lt' : 'bg-warning-lt' }} fw-bold">
                                    {{ $present }} / {{ $total }} Hadir
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-light text-dark border">
                                        <i class="ti ti-door-exit me-1 text-primary"></i> {{ $j->student_permits_count }} Izin
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="ti ti-chalkboard me-1 text-info"></i> {{ $j->inval_classes_count }} Inval
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="ti ti-alert-triangle me-1 text-danger"></i> {{ $j->incidents_count }} Kasus
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="ti ti-users me-1 text-success"></i> {{ $j->guests_count }} Tamu
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($j->isCompleted())
                                    <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i> Selesai & Divalidasi</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i> Sedang Berlangsung</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('piket.journals.show', $j) }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-eye me-1"></i> Detail / Isi
                                </a>
                                <a href="{{ route('piket.journals.print', $j) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-printer"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="ti ti-notebook-off fs-1 d-block mb-2"></i>
                                Belum ada riwayat jurnal piket yang dibuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($journals->hasPages())
            <div class="card-footer py-2">
                {{ $journals->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
