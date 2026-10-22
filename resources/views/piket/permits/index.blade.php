@extends('layouts.app')

@section('title', 'Perizinan & Keterlambatan Siswa')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Layanan Kedisiplinan Siswa
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-door-exit text-primary me-2 fs-1"></i>
                    Perizinan & Keterlambatan Siswa
                </h2>
                <div class="text-secondary mt-1">
                    Penerbitan surat izin masuk kelas, izin keluar sementara, dan izin pulang cepat.
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('piket.permits.create') }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="ti ti-plus me-1"></i> + Terbitkan Izin Siswa
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('piket.permits.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" title="Pilih Tanggal">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis Izin --</option>
                        <option value="terlambat" {{ $type === 'terlambat' ? 'selected' : '' }}>Terlambat Masuk</option>
                        <option value="izin_keluar_sementara" {{ $type === 'izin_keluar_sementara' ? 'selected' : '' }}>Izin Keluar Sementara</option>
                        <option value="izin_pulang_cepat" {{ $type === 'izin_pulang_cepat' ? 'selected' : '' }}>Izin Pulang Cepat</option>
                        <option value="izin_tidak_masuk" {{ $type === 'izin_tidak_masuk' ? 'selected' : '' }}>Izin Tidak Masuk</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="classroom_id" class="form-select form-select-sm">
                        <option value="">-- Semua Kelas --</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / no izin...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if($date || $type || $classroomId || $search)
                        <a href="{{ route('piket.permits.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Permits -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>No. Izin & Waktu</th>
                        <th>Nama Siswa & Kelas</th>
                        <th>Jenis Izin</th>
                        <th>Alasan & Sanksi</th>
                        <th>Petugas Piket</th>
                        <th class="text-center">Notifikasi Telegram</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permits as $p)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $p->permit_number }}</div>
                                <div class="text-muted small">
                                    <i class="ti ti-calendar me-1"></i>{{ $p->permit_date?->format('d/m/Y') }} &bull;
                                    <i class="ti ti-clock me-1"></i>{{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }} WIB
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $p->student_name }}</div>
                                <span class="badge bg-blue-lt">{{ $p->classroom?->name ?? '-' }}</span>
                                @if($p->classroom?->homeroomTeacher)
                                    <div class="text-muted small">Wali: {{ $p->classroom->homeroomTeacher->name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $p->permit_type_badge_class }}">{{ $p->permit_type_label }}</span>
                                @if($p->period_assigned)
                                    <div class="text-muted small mt-1">Masuk jam ke-{{ $p->period_assigned }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="text-secondary small"><strong>Alasan:</strong> {{ $p->reason }}</div>
                                @if($p->action_taken)
                                    <div class="text-muted small"><strong>Sanksi:</strong> {{ $p->action_taken }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark small">{{ $p->givenBy?->name ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column gap-1 align-items-center">
                                    @if($p->notified_homeroom)
                                        <span class="badge bg-success-lt" style="font-size: 0.7rem;"><i class="ti ti-check me-1"></i> Wali Kelas</span>
                                    @endif
                                    @if($p->notified_subject_teacher)
                                        <span class="badge bg-info-lt" style="font-size: 0.7rem;"><i class="ti ti-check me-1"></i> Guru Mapel</span>
                                    @endif
                                    @if(!$p->notified_homeroom && !$p->notified_subject_teacher)
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('piket.permits.slip', $p) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Cetak Slip Termal / Kertas">
                                        <i class="ti ti-printer me-1"></i> Slip
                                    </a>
                                    <a href="{{ route('piket.permits.show', $p) }}" class="btn btn-sm btn-ghost-secondary" title="Detail">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <form action="{{ route('piket.permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus data izin ini?')" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost-danger" title="Hapus">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-mood-smile fs-1 d-block mb-1 text-success"></i>
                                Tidak ada data perizinan siswa untuk filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($permits->hasPages())
            <div class="card-footer py-2">
                {{ $permits->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
