@extends('layouts.app')

@section('title', 'Detail Presensi Kelas — ' . $session->classroom->name)

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('attendance.subject.index', ['date' => $session->attendance_date->toDateString()]) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                <i class="ti ti-arrow-left"></i>
            </a>
            <h2 class="page-title fw-bold text-dark mb-0">
                Detail Presensi Kelas: {{ $session->classroom->name }}
            </h2>
            @if($session->is_swap)
                <span class="badge bg-warning text-dark px-2 py-1">
                    <i class="ti ti-arrows-left-right me-1"></i> Mode Tukar Jam
                </span>
            @endif
        </div>
        <div class="text-muted small">
            Mapel: <strong>{{ $session->subject->name }}</strong> &bull;
            Guru: <strong>{{ $session->teacher->name }}</strong> &bull;
            Tanggal: <strong>{{ $session->attendance_date->translatedFormat('l, d F Y') }}</strong> (Jam Ke-{{ $session->start_period }} s/d {{ $session->end_period }})
        </div>
    </div>
    <div class="col-auto ms-auto d-flex gap-2">
        <a href="{{ route('attendance.subject.take', ['schedule_id' => $session->lesson_schedule_id, 'date' => $session->attendance_date->toDateString(), 'classroom_id' => $session->classroom_id, 'subject_id' => $session->subject_id, 'start_period' => $session->start_period, 'end_period' => $session->end_period, 'is_swap' => $session->is_swap ? 1 : 0]) }}" class="btn btn-sm btn-outline-warning">
            <i class="ti ti-edit me-1"></i> Edit Data Presensi
        </a>
    </div>
</div>

<!-- Statistik Ringkasan Kehadiran Sesi Ini -->
<div class="row row-cards mb-3 g-2">
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2">
            <div class="text-muted small">Total Siswa</div>
            <div class="h2 mb-0 fw-bold text-dark">{{ $session->records->count() }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2 bg-success-lt">
            <div class="text-success small fw-bold">Hadir (H)</div>
            <div class="h2 mb-0 fw-bold text-success">{{ $session->hadir_count }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2 bg-warning-lt">
            <div class="text-warning small fw-bold">Sakit (S)</div>
            <div class="h2 mb-0 fw-bold text-warning">{{ $session->sakit_count }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2 bg-info-lt">
            <div class="text-info small fw-bold">Izin (I)</div>
            <div class="h2 mb-0 fw-bold text-info">{{ $session->izin_count }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2 bg-danger-lt">
            <div class="text-danger small fw-bold">Alpa (A)</div>
            <div class="h2 mb-0 fw-bold text-danger">{{ $session->alpa_count }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card card-sm border-0 shadow-sm text-center py-2 bg-purple-lt">
            <div class="text-purple small fw-bold">Dispen (D)</div>
            <div class="h2 mb-0 fw-bold text-purple">{{ $session->dispen_count }}</div>
        </div>
    </div>
</div>

<!-- Informasi Materi Jurnal / Tukar Jam -->
@if($session->topic || $session->notes || $session->is_swap)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-3">
                @if($session->topic)
                    <div class="col-12 col-md-6">
                        <div class="text-muted small fw-bold text-uppercase">Materi / Pokok Bahasan:</div>
                        <div class="fw-semibold text-dark">{{ $session->topic }}</div>
                    </div>
                @endif
                @if($session->notes)
                    <div class="col-12 col-md-6">
                        <div class="text-muted small fw-bold text-uppercase">Catatan Kejadian Kelas:</div>
                        <div class="text-dark">{{ $session->notes }}</div>
                    </div>
                @endif
                @if($session->is_swap)
                    <div class="col-12">
                        <div class="badge bg-warning text-dark px-2 py-1">
                            Status: {{ strtoupper(str_replace('_', ' ', $session->swap_type ?? 'Tukar Jam')) }}
                            (Guru Terjadwal: {{ $session->scheduledTeacher?->name ?? 'Jadwal Reguler' }})
                        </div>
                        @if($session->swap_notes)
                            <div class="text-muted small mt-1">Catatan Tukar: {{ $session->swap_notes }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

<!-- Tabel Rincian Kehadiran Siswa -->
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="w-1 text-center">No</th>
                    <th>NISN / NIS</th>
                    <th>Nama Siswa</th>
                    <th class="text-center">Status</th>
                    <th>Catatan Siswa</th>
                </tr>
            </thead>
            <tbody>
                @foreach($session->records as $idx => $record)
                    <tr>
                        <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                        <td class="text-muted small">{{ $record->student->nisn ?? $record->student->nis ?? '-' }}</td>
                        <td>
                            <strong class="text-dark">{{ $record->student->name }}</strong>
                            <span class="badge bg-secondary-lt ms-1">{{ $record->student->gender }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $record->status_badge }} px-2 py-1">
                                {{ $record->status_label }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $record->notes ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
