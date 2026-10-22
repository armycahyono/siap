@extends('layouts.app')

@section('title', 'Riwayat Presensi Mengajar — SMK PGRI Kras')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('attendance.subject.index') }}" class="btn btn-sm btn-icon btn-outline-secondary">
                <i class="ti ti-arrow-left"></i>
            </a>
            <h2 class="page-title fw-bold text-dark mb-0">
                <i class="ti ti-history me-2 text-primary"></i>
                Riwayat Presensi Mengajar Saya
            </h2>
        </div>
        <div class="text-muted small">
            Daftar seluruh sesi presensi kelas dan pembelajaran yang pernah Anda laksanakan.
        </div>
    </div>
    <div class="col-auto ms-auto">
        <a href="{{ route('attendance.subject.index') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus me-1"></i> Presensi Hari Ini
        </a>
    </div>
</div>

<!-- Filter Pencarian -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form action="{{ route('attendance.subject.history') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Rombel / Kelas</label>
                <select name="classroom_id" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Mata Pelajaran</label>
                <select name="subject_id" class="form-select form-select-sm">
                    <option value="">Semua Mapel</option>
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}" {{ $subjectId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end gap-1 mt-auto">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                <a href="{{ route('attendance.subject.history') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                    <i class="ti ti-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Riwayat Presensi -->
<div class="card border-0 shadow-sm mb-4">
    @if($sessions->isEmpty())
        <div class="card-body py-5 text-center text-muted">
            <div class="avatar avatar-lg bg-blue-lt text-primary rounded-circle mx-auto mb-2">
                <i class="ti ti-notes-off fs-1"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Tidak Ada Data Presensi</h4>
            <p class="small mb-0">Belum ada sesi presensi yang tersimpan pada rentang filter ini.</p>
        </div>
    @else
        <div class="table-responsive d-none d-md-block">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Ke</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Materi / Jurnal</th>
                        <th class="text-center">Kehadiran</th>
                        <th class="w-1 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sessions as $sess)
                        <tr>
                            <td>
                                <strong>{{ $sess->attendance_date->translatedFormat('d M Y') }}</strong>
                                <div class="text-muted small" style="font-size: 0.72rem;">{{ $sess->attendance_date->translatedFormat('l') }}</div>
                            </td>
                            <td>
                                <span class="badge bg-blue-lt px-2 py-1">
                                    {{ $sess->start_period }} - {{ $sess->end_period }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-3">{{ $sess->classroom->name }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $sess->subject->name }}</div>
                                @if($sess->is_swap)
                                    <span class="badge bg-warning-lt text-dark" style="font-size: 0.7rem;">
                                        Tukar: {{ $sess->scheduledTeacher?->name ?? 'Jadwal Lain' }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 250px;">
                                    {{ $sess->topic ?? '-' }}
                                </div>
                            </td>
                            <td class="text-center small">
                                H: <strong class="text-success">{{ $sess->hadir_count }}</strong> |
                                S: <strong class="text-warning">{{ $sess->sakit_count }}</strong> |
                                I: <strong class="text-info">{{ $sess->izin_count }}</strong> |
                                A: <strong class="text-danger">{{ $sess->alpa_count }}</strong>
                                @if($sess->dispen_count > 0)
                                    | D: <strong class="text-purple">{{ $sess->dispen_count }}</strong>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('attendance.subject.show', $sess) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-eye me-1"></i> Rincian
                                </a>
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sess->lesson_schedule_id, 'date' => $sess->attendance_date->toDateString(), 'classroom_id' => $sess->classroom_id, 'subject_id' => $sess->subject_id, 'start_period' => $sess->start_period, 'end_period' => $sess->end_period, 'is_swap' => $sess->is_swap ? 1 : 0]) }}" class="btn btn-sm btn-outline-warning">
                                    <i class="ti ti-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List (d-md-none) -->
        <div class="d-md-none p-3">
            @foreach($sessions as $sess)
                <div class="card border shadow-sm mb-3 rounded-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-blue-lt fw-bold px-2 py-1">
                                Jam {{ $sess->start_period }} - {{ $sess->end_period }}
                            </span>
                            <small class="text-muted">
                                {{ $sess->attendance_date->translatedFormat('d M Y') }} ({{ $sess->attendance_date->translatedFormat('l') }})
                            </small>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-bold text-dark fs-3">{{ $sess->classroom->name }}</div>
                            <span class="text-primary fw-semibold small">{{ $sess->subject->name }}</span>
                        </div>
                        @if($sess->is_swap)
                            <div class="mb-2">
                                <span class="badge bg-warning-lt text-dark" style="font-size: 0.7rem;">
                                    Tukar: {{ $sess->scheduledTeacher?->name ?? 'Jadwal Lain' }}
                                </span>
                            </div>
                        @endif
                        @if($sess->topic)
                            <div class="p-2 bg-light rounded text-dark small mb-2">
                                <strong>Materi:</strong> {{ $sess->topic }}
                            </div>
                        @endif
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 pt-2 border-top">
                            <div class="small">
                                <span class="badge bg-success-lt me-1">H: {{ $sess->hadir_count }}</span>
                                <span class="badge bg-warning-lt me-1">S: {{ $sess->sakit_count }}</span>
                                <span class="badge bg-info-lt me-1">I: {{ $sess->izin_count }}</span>
                                <span class="badge bg-danger-lt">A: {{ $sess->alpa_count }}</span>
                                @if($sess->dispen_count > 0)
                                    <span class="badge bg-purple-lt ms-1">D: {{ $sess->dispen_count }}</span>
                                @endif
                            </div>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('attendance.subject.show', $sess) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-eye me-1"></i> Rincian
                                </a>
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sess->lesson_schedule_id, 'date' => $sess->attendance_date->toDateString(), 'classroom_id' => $sess->classroom_id, 'subject_id' => $sess->subject_id, 'start_period' => $sess->start_period, 'end_period' => $sess->end_period, 'is_swap' => $sess->is_swap ? 1 : 0]) }}" class="btn btn-sm btn-outline-warning">
                                    <i class="ti ti-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card-footer d-flex justify-content-center py-2">
            {{ $sessions->links() }}
        </div>
    @endif
</div>
@endsection
