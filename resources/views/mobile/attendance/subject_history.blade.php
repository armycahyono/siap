@extends('layouts.mobile')

@section('title', 'Riwayat Presensi KBM — APK SIAP')
@section('page_title', 'Riwayat Presensi KBM')
@section('back_url', route('attendance.subject.index'))

@section('content')
<!-- Link Rekap Presensi Siswa -->
<div class="d-flex justify-content-end mb-1.5 px-0.5">
    <a href="{{ route('attendance.homeroom-recap.index') }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1" style="font-size: 0.8rem;">
        <i class="ti ti-clipboard-list fs-3"></i>
        <span>Rekap Presensi Siswa</span>
        <i class="ti ti-chevron-right" style="font-size: 0.72rem;"></i>
    </a>
</div>

<!-- Filter Pencarian Riwayat -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('attendance.subject.history') }}" method="GET" class="d-flex flex-column gap-1.5">
            <div class="mobile-grid-2">
                <div>
                    <label class="form-label small fw-bold mb-0.5" style="font-size: 0.72rem;">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" style="font-size: 0.75rem;" value="{{ $startDate }}">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-0.5" style="font-size: 0.72rem;">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" style="font-size: 0.75rem;" value="{{ $endDate }}">
                </div>
            </div>
            <div class="mobile-grid-2">
                <div>
                    <select name="classroom_id" class="form-select form-select-sm" style="font-size: 0.75rem;">
                        <option value="">Semua Kelas</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="subject_id" class="form-select form-select-sm" style="font-size: 0.75rem;">
                        <option value="">Semua Mapel</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}" {{ $subjectId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill rounded-pill fw-bold" style="font-size: 0.75rem;">
                    <i class="ti ti-filter me-1"></i> Terapkan Filter
                </button>
                @if($classroomId || $subjectId || $startDate != now()->startOfMonth()->toDateString())
                    <a href="{{ route('attendance.subject.history') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size: 0.75rem;" title="Reset Filter">
                        <i class="ti ti-x"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Daftar Sesi Presensi KBM -->
<div class="mb-4">
    @forelse($sessions as $sess)
        <div class="card border-0 shadow-sm rounded-3 mb-1.5">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge bg-blue-lt fw-bold" style="font-size: 0.65rem; padding: 2px 6px;">
                        Jam {{ $sess->start_period }}-{{ $sess->end_period }}
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        <i class="ti ti-calendar me-0.5"></i>
                        {{ $sess->attendance_date->translatedFormat('d M Y') }}
                    </span>
                </div>

                <div class="fw-bold text-dark fs-3 lh-sm">{{ $sess->classroom?->name }}</div>
                <div class="text-primary fw-semibold small mb-1" style="font-size: 0.75rem;">
                    {{ $sess->subject?->name }}
                    @if($sess->is_swap)
                        <span class="badge bg-warning-lt text-warning fw-bold ms-1" style="font-size: 0.62rem;">
                            {{ ucfirst(str_replace('_', ' ', $sess->swap_type ?? 'inval')) }}
                        </span>
                    @endif
                </div>

                @if($sess->is_swap && $sess->scheduledTeacher)
                    <div class="text-muted small mb-1" style="font-size: 0.68rem;">
                        <i class="ti ti-user me-0.5 text-secondary"></i> Guru Terjadwal: <strong>{{ $sess->scheduledTeacher->name }}</strong>
                    </div>
                @endif

                @if($sess->topic)
                    <div class="p-1.5 bg-light rounded-2 text-dark small mb-1.5" style="font-size: 0.72rem;">
                        <strong>Materi:</strong> {{ $sess->topic }}
                    </div>
                @endif

                <div class="d-flex align-items-center justify-content-between pt-1 border-top">
                    <div class="d-flex gap-1" style="font-size: 0.68rem;">
                        <span class="badge bg-success-lt" style="padding: 2px 5px;">H: {{ $sess->hadir_count }}</span>
                        <span class="badge bg-warning-lt" style="padding: 2px 5px;">S: {{ $sess->sakit_count }}</span>
                        <span class="badge bg-info-lt" style="padding: 2px 5px;">I: {{ $sess->izin_count }}</span>
                        <span class="badge bg-danger-lt" style="padding: 2px 5px;">A: {{ $sess->alpa_count }}</span>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="{{ route('attendance.subject.show', $sess) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0.5" style="font-size: 0.72rem;">
                            <i class="ti ti-eye me-0.5"></i> Detail
                        </a>
                        <a href="{{ route('attendance.subject.take', ['schedule_id' => $sess->lesson_schedule_id, 'date' => $sess->attendance_date->toDateString(), 'classroom_id' => $sess->classroom_id, 'subject_id' => $sess->subject_id, 'start_period' => $sess->start_period, 'end_period' => $sess->end_period, 'is_swap' => $sess->is_swap ? 1 : 0]) }}" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0.5" style="font-size: 0.72rem;">
                            <i class="ti ti-edit me-0.5"></i> Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-notes-off fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Tidak ada riwayat presensi yang ditemukan.</div>
        </div>
    @endforelse

    @if($sessions->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $sessions->links() }}
        </div>
    @endif
</div>
@endsection
