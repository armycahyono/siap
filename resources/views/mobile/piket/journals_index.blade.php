@extends('layouts.mobile')

@section('title', 'Riwayat Jurnal Piket — APK SIAP')
@section('page_title', 'Jurnal Piket')
@section('page_subtitle', 'Rekam Jejak Piket Harian')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Header Card & Aksi Jurnal Hari Ini -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-end mb-1">
            <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                {{ now()->translatedFormat('d M Y') }}
            </span>
        </div>
        <div class="fw-bold fs-3 lh-sm mb-1">Buku Jurnal Piket Harian</div>
        <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
            Monitoring kehadiran petugas, perizinan siswa, guru inval, dan situasi umum sekolah.
        </div>
        @if(auth()->user()->canOperatePiket())
            <a href="{{ route('piket.journals.today') }}" class="btn btn-sm bg-white fw-bold rounded-pill w-100 py-1.5 shadow-sm d-flex align-items-center justify-content-center gap-1.5 border-0" style="font-size: 0.82rem; color: #16a34a !important;">
                <i class="ti ti-notebook fs-3" style="color: #16a34a;"></i>
                <span style="color: #16a34a;">Buka / Isi Jurnal Hari Ini</span>
            </a>
        @endif
    </div>
</div>

<!-- Filter Bulan -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('piket.journals.index') }}" method="GET" class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm flex-fill">
                <span class="input-group-text bg-white text-muted"><i class="ti ti-calendar"></i></span>
                <input type="month" name="month" class="form-control" style="font-size: 0.78rem;" value="{{ $month }}">
                <button type="submit" class="btn btn-primary" style="font-size: 0.78rem;">
                    Filter
                </button>
            </div>
            @if($month)
                <a href="{{ route('piket.journals.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.78rem;" title="Reset">
                    <i class="ti ti-x"></i>
                </a>
            @endif
        </form>
    </div>
</div>

<!-- Feed Daftar Jurnal -->
<div class="mb-4">
    @forelse($journals as $j)
        @php
            $isDone = $j->isCompleted();
            $presentCount = $j->attendances->where('status', 'hadir')->count();
            $totalAtt = $j->attendances->count();
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-2 {{ $isDone ? '' : 'border-start border-warning border-3' }}">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge {{ $isDone ? 'bg-success text-white' : 'bg-warning text-dark' }}" style="font-size: 0.65rem; padding: 2px 7px;">
                        <i class="ti {{ $isDone ? 'ti-check' : 'ti-clock' }} me-0.5"></i>
                        {{ $isDone ? 'Selesai & Divalidasi' : 'Sedang Berlangsung' }}
                    </span>
                    <span class="badge bg-blue-lt fw-bold" style="font-size: 0.65rem;">
                        {{ $j->date?->translatedFormat('l') }}
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-0.5">
                    <div class="fw-bold text-dark fs-3 lh-sm">
                        {{ $j->date?->translatedFormat('d F Y') }}
                    </div>
                    @if($totalAtt > 0)
                        <span class="badge {{ $presentCount === $totalAtt ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}" style="font-size: 0.65rem;">
                            {{ $presentCount }}/{{ $totalAtt }} Hadir
                        </span>
                    @endif
                </div>

                <div class="text-muted small mb-1.5" style="font-size: 0.72rem;">
                    Koordinator: <strong>{{ $j->coordinator?->formatted_short_name ?? $j->coordinator?->name ?? 'Belum Ditentukan' }}</strong>
                    @if($isDone && $j->completed_at)
                        &bull; Ditutup: {{ $j->completed_at->format('H:i') }} WIB
                    @endif
                </div>

                <!-- Chips Aktivitas: Warna Soft Lebih Lembut -->
                <div class="d-flex gap-1 flex-wrap mb-2" style="font-size: 0.68rem;">
                    <span class="badge bg-warning-lt text-warning border-0" style="font-weight: 600;">
                        <i class="ti ti-door-exit me-0.5"></i> {{ $j->student_permits_count }} Izin
                    </span>
                    <span class="badge bg-purple-lt text-purple border-0" style="font-weight: 600;">
                        <i class="ti ti-chalkboard me-0.5"></i> {{ $j->inval_classes_count }} Inval
                    </span>
                    <span class="badge bg-danger-lt text-danger border-0" style="font-weight: 600;">
                        <i class="ti ti-alert-triangle me-0.5"></i> {{ $j->incidents_count }} Kasus
                    </span>
                    <span class="badge bg-success-lt text-success border-0" style="font-weight: 600;">
                        <i class="ti ti-users me-0.5"></i> {{ $j->guests_count }} Tamu
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 mt-2 border-top gap-2">
                    <a href="{{ route('piket.journals.print', $j) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                        <i class="ti ti-printer me-0.5"></i> Cetak Format Dinas
                    </a>
                    <a href="{{ route('piket.journals.show', $j) }}" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.72rem;">
                        <span>Buka Jurnal</span>
                        <i class="ti ti-chevron-right ms-0.5"></i>
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-notebook fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Belum ada catatan jurnal piket pada bulan ini.</div>
        </div>
    @endforelse

    @if($journals->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $journals->links() }}
        </div>
    @endif
</div>
@endsection
