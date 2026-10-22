@extends('layouts.mobile')

@section('title', 'Detail Presensi Kelas ' . $session->classroom->name)
@section('page_title', 'Detail ' . $session->classroom->name)
@section('page_subtitle', $session->subject->name . ' • ' . $session->attendance_date->translatedFormat('d M Y'))
@section('back_url', route('attendance.subject.index', ['date' => $session->attendance_date->toDateString()]))

@section('content')
<!-- Card Ringkasan Sesi -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                Jam {{ $session->start_period }}-{{ $session->end_period }}
            </span>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                    {{ $session->attendance_date->translatedFormat('l, d F Y') }}
                </span>
                <a href="{{ route('attendance.subject.take', ['schedule_id' => $session->lesson_schedule_id, 'date' => $session->attendance_date->toDateString(), 'classroom_id' => $session->classroom_id, 'subject_id' => $session->subject_id, 'start_period' => $session->start_period, 'end_period' => $session->end_period, 'is_swap' => $session->is_swap ? 1 : 0]) }}" class="btn btn-warning btn-sm fw-bold px-2 py-0.5 rounded-pill shadow-none" style="font-size: 0.68rem; line-height: 1.2;">
                    <i class="ti ti-edit me-0.5"></i> Edit
                </a>
            </div>
        </div>
        <div class="fw-bold fs-3 lh-sm">{{ $session->classroom->name }} &bull; {{ $session->subject->name }}</div>
        <div class="text-white-50 small mt-0.5" style="font-size: 0.72rem;">
            Guru: {{ $session->teacher->name }}
            @if($session->is_swap)
                <span class="badge bg-warning text-dark ms-1">Tukar Jam</span>
            @endif
        </div>
    </div>
</div>

<!-- Statistik Kehadiran (H | T | S | I | A | D) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="d-flex justify-content-between align-items-center text-center text-muted" style="font-size: 0.78rem;">
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Hadir</span>
                <span class="fw-bold text-success fs-3 lh-1">{{ $session->hadir_count }}</span>
            </div>
            <span class="text-muted opacity-50">|</span>
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Telat</span>
                <span class="fw-bold text-warning fs-3 lh-1">{{ $session->terlambat_count }}</span>
            </div>
            <span class="text-muted opacity-50">|</span>
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Sakit</span>
                <span class="fw-bold text-warning fs-3 lh-1">{{ $session->sakit_count }}</span>
            </div>
            <span class="text-muted opacity-50">|</span>
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Izin</span>
                <span class="fw-bold text-info fs-3 lh-1">{{ $session->izin_count }}</span>
            </div>
            <span class="text-muted opacity-50">|</span>
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Alpa</span>
                <span class="fw-bold text-danger fs-3 lh-1">{{ $session->alpa_count }}</span>
            </div>
            <span class="text-muted opacity-50">|</span>
            <div>
                <span class="d-block text-muted small" style="font-size: 0.65rem;">Dispen</span>
                <span class="fw-bold text-purple fs-3 lh-1">{{ $session->dispen_count }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Jurnal KBM -->
@if($session->topic || $session->notes)
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            @if($session->topic)
                <div class="mb-1">
                    <span class="text-muted small fw-semibold" style="font-size: 0.7rem;">Materi / Pokok Bahasan:</span>
                    <div class="fw-semibold text-dark fs-4">{{ $session->topic }}</div>
                </div>
            @endif
            @if($session->notes)
                <div>
                    <span class="text-muted small fw-semibold" style="font-size: 0.7rem;">Catatan Kelas:</span>
                    <div class="text-secondary small">{{ $session->notes }}</div>
                </div>
            @endif
        </div>
    </div>
@endif

<!-- Daftar Siswa -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-2 px-2.5">
        <div class="fw-bold text-dark fs-4 mb-2 ps-1">
            Daftar Kehadiran Siswa ({{ $session->records->count() }})
        </div>
        <div class="d-flex flex-column gap-1.5">
            @foreach($session->records as $idx => $record)
                @php
                    $badgeClass = match($record->status) {
                        'h' => 'bg-success text-white',
                        't' => 'bg-warning text-dark',
                        's' => 'bg-warning text-dark',
                        'i' => 'bg-info text-white',
                        'a' => 'bg-danger text-white',
                        'd' => 'bg-purple text-white',
                        default => 'bg-secondary text-white',
                    };
                    $statusLabel = match($record->status) {
                        'h' => 'Hadir',
                        't' => 'Terlambat',
                        's' => 'Sakit',
                        'i' => 'Izin',
                        'a' => 'Alpa',
                        'd' => 'Dispen',
                        default => $record->status,
                    };
                @endphp
                <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light">
                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                        <span class="text-muted small fw-bold" style="font-size: 0.72rem; min-width: 18px;">{{ $idx + 1 }}</span>
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark fs-4 text-truncate lh-sm">{{ $record->student?->name ?? 'Siswa' }}</div>
                            <div class="text-muted small" style="font-size: 0.68rem;">{{ $record->student?->nisn ?? $record->student?->nis ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ms-2 text-end">
                        <span class="badge {{ $badgeClass }}" style="font-size: 0.7rem; padding: 3px 8px;">
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Bottom Action Bar (Natural Flow, no floating/hovering in center) -->
<div class="pt-2 pb-4 mb-3">
    <a href="{{ route('attendance.subject.take', ['schedule_id' => $session->lesson_schedule_id, 'date' => $session->attendance_date->toDateString(), 'classroom_id' => $session->classroom_id, 'subject_id' => $session->subject_id, 'start_period' => $session->start_period, 'end_period' => $session->end_period, 'is_swap' => $session->is_swap ? 1 : 0]) }}" class="btn btn-warning w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.9rem;">
        <i class="ti ti-edit fs-2"></i>
        <span>Edit Lembar Presensi Ini</span>
    </a>
</div>
@endsection
