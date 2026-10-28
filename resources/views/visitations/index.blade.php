@extends('layouts.app')

@section('title', 'Visitasi & Pemantauan Siswa')

@section('content')
<div class="container-xl">
    <!-- Header Hero -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-body p-4 position-relative" style="background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-xl rounded-circle bg-teal text-white fw-bold fs-1 shadow-sm flex-shrink-0">
                        <i class="ti ti-home-heart fs-1"></i>
                    </span>
                    <div>
                        <span class="badge bg-teal text-white fw-semibold mb-1" style="letter-spacing: 0.05rem;">
                            KESISWAAN &amp; BIMBINGAN KONSELING
                        </span>
                        <h1 class="h2 fw-bold mb-1 text-dark">Visitasi &amp; Pemantauan Siswa</h1>
                        <p class="text-muted small mb-0">
                            Pencatatan kunjungan rumah (home visit), pemantauan berkala, penanganan kehadiran, dan koordinasi multi-petugas (Wali Kelas, Guru BK, Kesiswaan).
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('visitations.create') }}" class="btn btn-teal text-white shadow-xs fw-semibold">
                        <i class="ti ti-plus me-1"></i> Jadwalkan Visitasi Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border shadow-none">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-blue-lt text-blue"><i class="ti ti-calendar-event fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-dark fw-bold fs-2">{{ $stats['total'] }}</div>
                            <div class="text-muted small">Total Agenda TP {{ $academicYear }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border shadow-none">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-warning-lt text-warning"><i class="ti ti-clock fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-dark fw-bold fs-2">{{ $stats['dijadwalkan'] }}</div>
                            <div class="text-muted small">Diajukan / Dijadwalkan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border shadow-none">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-info-lt text-info"><i class="ti ti-run fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-dark fw-bold fs-2">{{ $stats['terlaksana'] }}</div>
                            <div class="text-muted small">Terlaksana (Menunggu RTL)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border shadow-none">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar bg-success-lt text-success"><i class="ti ti-circle-check fs-2"></i></span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-dark fw-bold fs-2">{{ $stats['selesai'] }}</div>
                            <div class="text-muted small">Selesai &amp; Ditutup</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('visitations.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $academicYear === $year ? 'selected' : '' }}>TP {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="visit_type" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis Kegiatan --</option>
                        <option value="kunjungan_rumah" {{ $visitType === 'kunjungan_rumah' ? 'selected' : '' }}>Kunjungan Rumah (Home Visit)</option>
                        <option value="pemantauan_khusus" {{ $visitType === 'pemantauan_khusus' ? 'selected' : '' }}>Pemantauan Kondisi Khusus</option>
                        <option value="tindak_lanjut_absensi" {{ $visitType === 'tindak_lanjut_absensi' ? 'selected' : '' }}>Tindak Lanjut Kehadiran</option>
                        <option value="komunikasi_ortu" {{ $visitType === 'komunikasi_ortu' ? 'selected' : '' }}>Komunikasi Orang Tua / Wali</option>
                        <option value="tindak_lanjut_kasus" {{ $visitType === 'tindak_lanjut_kasus' ? 'selected' : '' }}>Tindak Lanjut Kasus / Kejadian</option>
                        <option value="lainnya" {{ $visitType === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="diajukan" {{ $status === 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                        <option value="dijadwalkan" {{ $status === 'dijadwalkan' ? 'selected' : '' }}>Dijadwalkan</option>
                        <option value="terlaksana" {{ $status === 'terlaksana' ? 'selected' : '' }}>Terlaksana</option>
                        <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                        <option value="dibatalkan" {{ $status === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / alasan...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if($status || $visitType || $search)
                        <a href="{{ route('visitations.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Visitasi -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Tanggal</th>
                        <th>Siswa &amp; Kelas</th>
                        <th>Jenis Kegiatan</th>
                        <th>Petugas Ditugaskan</th>
                        <th>Alasan &amp; Ringkasan Hasil</th>
                        <th class="text-center" style="width: 120px;">Status</th>
                        <th class="text-end" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visitations as $visit)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $visit->planned_date?->format('d/m/Y') }}</div>
                                @if($visit->executed_date)
                                    <div class="text-muted small">Aktual: {{ $visit->executed_date->format('d/m/Y') }}</div>
                                @else
                                    <div class="text-muted small">Jadwal Rencana</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold fs-3 text-dark">{{ $visit->student?->name }}</div>
                                <span class="badge bg-blue-lt">{{ $visit->student?->classroom?->name ?? 'Belum ada rombel' }}</span>
                                <div class="text-muted small">NISN: {{ $visit->student?->nisn ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-teal-lt fw-bold">{{ $visit->visit_type_label }}</span>
                                <div class="text-muted small mt-1">Sumber: {{ $visit->source_type_label }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><i class="ti ti-user me-1 text-primary"></i>{{ $visit->primaryOfficer?->name }}</div>
                                @if($visit->secondaryOfficer)
                                    <div class="small text-muted"><i class="ti ti-users me-1"></i>Pendamping: {{ $visit->secondaryOfficer->name }}</div>
                                @endif
                                <div class="text-muted small" style="font-size: 0.72rem;">Pengusul: {{ $visit->requester?->name }}</div>
                            </td>
                            <td>
                                <div class="text-dark small fw-semibold">{{ Str::limit($visit->reason, 80) }}</div>
                                @if($visit->result_summary)
                                    <div class="text-muted small mt-1"><strong>Hasil:</strong> {{ Str::limit($visit->result_summary, 90) }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $visit->status_badge_class }}">{{ $visit->status_label }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('visitations.show', $visit) }}" class="btn btn-sm btn-outline-primary" title="Buka Detail">
                                    <i class="ti ti-eye me-1"></i> Detail
                                </a>
                                @if($visit->canUserEdit(auth()->user(), $visit->academic_year))
                                    <a href="{{ route('visitations.edit', $visit) }}" class="btn btn-sm btn-ghost-secondary" title="Edit Hasil">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="avatar avatar-md rounded-circle bg-light text-muted mb-2">
                                    <i class="ti ti-home-x fs-2"></i>
                                </div>
                                <div class="fw-bold">Belum Ada Agenda Visitasi Siswa</div>
                                <div class="small">Belum ada catatan kunjungan rumah atau pemantauan khusus untuk filter ini.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($visitations->hasPages())
            <div class="card-footer d-flex justify-content-end py-2">
                {{ $visitations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
