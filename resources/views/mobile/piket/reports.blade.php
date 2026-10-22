@extends('layouts.mobile')

@section('title', 'Laporan Rekap Piket — APK SIAP')
@section('page_title', 'Laporan Piket')
@section('page_subtitle', 'Rekapitulasi Layanan Meja Piket')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Header Card -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="fw-bold fs-3 lh-sm mb-1">Rekapitulasi Meja Piket</div>
        <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
            Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s.d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        </div>
        <form action="{{ route('piket.reports.index') }}" method="GET" class="d-flex gap-1.5 align-items-center mb-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="date" name="start_date" class="form-control form-control-sm text-dark flex-fill" style="font-size: 0.75rem;" value="{{ $startDate }}">
            <input type="date" name="end_date" class="form-control form-control-sm text-dark flex-fill" style="font-size: 0.75rem;" value="{{ $endDate }}">
            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-2.5" style="font-size: 0.75rem;">
                Filter
            </button>
        </form>
        <a href="{{ route('piket.reports.print', ['start_date' => $startDate, 'end_date' => $endDate, 'tab' => $tab]) }}" target="_blank" class="btn btn-sm btn-outline-light rounded-pill w-100 py-1 text-white fw-bold d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.75rem;">
            <i class="ti ti-printer fs-3"></i>
            <span>Cetak Rekapitulasi Format Dinas</span>
        </a>
    </div>
</div>

<!-- 5 Stat Cards (Grid 2 & 3) -->
<div class="mobile-grid-2 mb-2">
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Siswa Terlambat</span>
            <div class="fw-bold text-warning fs-2 lh-1 mt-0.5">{{ $stats['total_late'] }} <span style="font-size: 0.7rem;">Siswa</span></div>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Izin Keluar/Pulang</span>
            <div class="fw-bold text-primary fs-2 lh-1 mt-0.5">{{ $stats['total_leave'] }} <span style="font-size: 0.7rem;">Siswa</span></div>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Kelas Diinval</span>
            <div class="fw-bold text-danger fs-2 lh-1 mt-0.5">{{ $stats['total_inval'] }} <span style="font-size: 0.7rem;">Kelas</span></div>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Kasus Siswa</span>
            <div class="fw-bold text-danger fs-2 lh-1 mt-0.5">{{ $stats['total_incidents'] }} <span style="font-size: 0.7rem;">Kasus</span></div>
        </div>
    </div>
</div>

<!-- Tab Navigation Pilihan Kategori -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-1.5 d-flex gap-1 overflow-auto">
        <a href="{{ route('piket.reports.index', ['tab' => 'permits', 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm rounded-pill flex-fill {{ $tab === 'permits' ? 'btn-primary fw-bold' : 'btn-light text-dark' }}" style="font-size: 0.72rem; white-space: nowrap;">
            Izin ({{ $permits->count() }})
        </a>
        <a href="{{ route('piket.reports.index', ['tab' => 'inval', 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm rounded-pill flex-fill {{ $tab === 'inval' ? 'btn-primary fw-bold' : 'btn-light text-dark' }}" style="font-size: 0.72rem; white-space: nowrap;">
            Inval ({{ $invals->count() }})
        </a>
        <a href="{{ route('piket.reports.index', ['tab' => 'incidents', 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm rounded-pill flex-fill {{ $tab === 'incidents' ? 'btn-primary fw-bold' : 'btn-light text-dark' }}" style="font-size: 0.72rem; white-space: nowrap;">
            Kasus ({{ $incidents->count() }})
        </a>
        <a href="{{ route('piket.reports.index', ['tab' => 'guests', 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm rounded-pill flex-fill {{ $tab === 'guests' ? 'btn-primary fw-bold' : 'btn-light text-dark' }}" style="font-size: 0.72rem; white-space: nowrap;">
            Tamu ({{ $guests->count() }})
        </a>
    </div>
</div>

<!-- Tab Content Feed -->
<div class="mb-4">
    @if($tab === 'permits')
        @forelse($permits as $p)
            <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-0.5">
                        <span class="fw-bold text-dark fs-4">{{ $p->student_name }}</span>
                        <span class="badge bg-blue-lt" style="font-size: 0.65rem;">{{ $p->classroom?->name }}</span>
                    </div>
                    <div class="text-muted small" style="font-size: 0.7rem;">
                        {{ $p->permit_date?->format('d/m/Y') }} &bull; {{ $p->reason }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted small">Tidak ada data izin pada periode ini.</div>
        @endforelse
    @elseif($tab === 'inval')
        @forelse($invals as $inv)
            <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-0.5">
                        <span class="fw-bold text-dark fs-4">{{ $inv->classroom?->name }}</span>
                        <span class="badge bg-secondary-lt" style="font-size: 0.65rem;">Jam {{ $inv->period_start }}-{{ $inv->period_end }}</span>
                    </div>
                    <div class="text-danger small" style="font-size: 0.7rem;">
                        Guru: {{ $inv->absentTeacher?->formatted_short_name ?? $inv->absentTeacher?->name }} ({{ $inv->reason }})
                    </div>
                    <div class="text-success small" style="font-size: 0.7rem;">
                        Inval: {{ $inv->invalTeacher?->formatted_short_name ?? $inv->invalTeacher?->name ?? 'Belum Ditugaskan' }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted small">Tidak ada kelas inval pada periode ini.</div>
        @endforelse
    @elseif($tab === 'incidents')
        @forelse($incidents as $inc)
            <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-0.5">
                        <span class="fw-bold text-dark fs-4">{{ $inc->student_name }}</span>
                        <span class="badge bg-danger-lt" style="font-size: 0.65rem;">{{ ucfirst(str_replace('_', ' ', $inc->category)) }}</span>
                    </div>
                    <div class="text-muted small" style="font-size: 0.7rem;">
                        {{ $inc->incident_date?->format('d/m/Y') }} &bull; {{ $inc->description }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted small">Tidak ada catatan kasus pada periode ini.</div>
        @endforelse
    @elseif($tab === 'guests')
        @forelse($guests as $g)
            <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                <div class="card-body p-2 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-0.5">
                        <span class="fw-bold text-dark fs-4">{{ $g->guest_name }}</span>
                        <span class="text-muted small" style="font-size: 0.68rem;">{{ $g->visit_date?->format('d/m/Y') }}</span>
                    </div>
                    <div class="text-muted small" style="font-size: 0.7rem;">
                        {{ $g->institution ?: 'Wali/Umum' }} &bull; Menemui: {{ $g->meet_with }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted small">Tidak ada kunjungan tamu pada periode ini.</div>
        @endforelse
    @endif
</div>
@endsection
