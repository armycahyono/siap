@extends('layouts.app')

@section('title', 'Sesi Layanan Bimbingan & Konseling')

@section('content')
<div class="container-xl">
    @php
        $srvActions = '<a href="' . route('bk.services.create') . '" class="btn btn-sm btn-primary shadow-xs fw-bold"><i class="ti ti-plus me-1"></i> Catat Layanan Baru</a>' .
                      '<a href="' . route('bk.referrals.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-bell-ringing me-1"></i> Rujukan Piket</a>';
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Sesi Layanan Bimbingan & Konseling',
        'subtitle' => 'Daftar seluruh sesi bimbingan pribadi, sosial, belajar, dan karier siswa.',
        'icon' => 'ti ti-notes',
        'category' => 'BIMBINGAN & KONSELING',
        'academicYear' => $academicYear,
        'actions' => $srvActions
    ])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Early Warning System (EWS) Alerts -->
    @if(($ewsStats['pendingReferrals'] ?? 0) > 0 || ($ewsStats['overdueRtl'] ?? 0) > 0 || ($ewsStats['pendingVisitations'] ?? 0) > 0 || ($ewsStats['repeatIncidents'] ?? 0) > 0)
        <div class="card border-warning bg-warning-lt shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div class="d-flex align-items-center">
                        <span class="avatar avatar-sm bg-warning text-white rounded-circle me-3 flex-shrink-0">
                            <i class="ti ti-alert-triangle fs-2"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark">Sistem Peringatan Dini &amp; Pemantauan Tindak Lanjut (Early Warning System)</div>
                            <div class="text-muted small">Terdapat sejumlah kasus dan komitmen pembinaan siswa yang memerlukan perhatian segera:</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if(($ewsStats['pendingReferrals'] ?? 0) > 0)
                            <a href="{{ route('bk.referrals.index') }}" class="btn btn-sm btn-white border-warning text-dark shadow-xs">
                                <span class="badge bg-danger rounded-pill me-1">{{ $ewsStats['pendingReferrals'] }}</span> Rujukan Piket Menunggu
                            </a>
                        @endif
                        @if(($ewsStats['overdueRtl'] ?? 0) > 0)
                            <a href="{{ route('bk.services.index', ['academic_year' => $academicYear, 'follow_up_status' => 'sedang_berjalan']) }}" class="btn btn-sm btn-white border-warning text-dark shadow-xs">
                                <span class="badge bg-warning rounded-pill me-1 text-dark">{{ $ewsStats['overdueRtl'] }}</span> RTL Jatuh Tempo
                            </a>
                        @endif
                        @if(($ewsStats['pendingVisitations'] ?? 0) > 0)
                            <a href="{{ route('visitations.index', ['status' => 'dijadwalkan', 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-white border-warning text-dark shadow-xs">
                                <span class="badge bg-teal rounded-pill me-1">{{ $ewsStats['pendingVisitations'] }}</span> Visitasi Dijadwalkan
                            </a>
                        @endif
                        @if(($ewsStats['repeatIncidents'] ?? 0) > 0)
                            <a href="{{ route('piket.incidents.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-white border-warning text-dark shadow-xs">
                                <span class="badge bg-dark rounded-pill me-1">{{ $ewsStats['repeatIncidents'] }}</span> Siswa Kasus Berulang
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('bk.services.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $academicYear === $year ? 'selected' : '' }}>TP {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="service_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Bidang Layanan --</option>
                        <option value="pribadi" {{ $serviceType === 'pribadi' ? 'selected' : '' }}>Bimbingan Pribadi</option>
                        <option value="sosial" {{ $serviceType === 'sosial' ? 'selected' : '' }}>Bimbingan Sosial</option>
                        <option value="belajar" {{ $serviceType === 'belajar' ? 'selected' : '' }}>Bimbingan Belajar</option>
                        <option value="karier" {{ $serviceType === 'karier' ? 'selected' : '' }}>Bimbingan Karier</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="follow_up" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Tindak Lanjut --</option>
                        <option value="ada" {{ $followUp === 'ada' ? 'selected' : '' }}>Memiliki RTL</option>
                        <option value="belum" {{ $followUp === 'belum' ? 'selected' : '' }}>Belum Ada RTL</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / ringkasan masalah...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search me-1"></i> Filter</button>
                    @if($serviceType || $followUp || $search)
                        <a href="{{ route('bk.services.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Layanan -->
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h3 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-notes text-primary me-2"></i> Rekapitulasi Sesi Layanan BK ({{ $services->total() }})
            </h3>
            <a href="{{ route('bk.services.create', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-primary shadow-xs">
                <i class="ti ti-plus me-1"></i> Catat Layanan Baru
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Tanggal</th>
                        <th>Siswa</th>
                        <th style="width: 140px;">Bidang Layanan</th>
                        <th>Ringkasan Masalah &amp; Hasil</th>
                        <th>Sumber</th>
                        <th style="width: 150px;">Guru BK</th>
                        <th style="width: 110px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr>
                            <td class="fw-bold">{{ $service->service_date->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('bk.students.history', ['student' => $service->student, 'academic_year' => $academicYear]) }}" class="fw-bold text-decoration-none text-primary">
                                    {{ $service->student->name }}
                                </a>
                                @php
                                    $studentClass = $service->student->classroomForAcademicYear($academicYear) ?? $service->student->classroom;
                                @endphp
                                <div class="text-muted small">
                                    {{ $studentClass?->name ?? 'Kelas -' }}
                                    @if($service->student->nisn)
                                        &bull; NISN: {{ $service->student->nisn }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $service->service_type_badge_class }}">{{ $service->service_type_label }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 300px;">
                                    {{ $service->summary }}
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 300px;">
                                    <em>Hasil:</em> {{ $service->result }}
                                </div>
                                @if($service->follow_up_plan)
                                    <div class="text-primary small text-truncate mt-1" style="max-width: 300px;">
                                        <i class="ti ti-arrow-forward me-1"></i><em>RTL:</em> {{ $service->follow_up_plan }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($service->incident)
                                    <a href="{{ route('bk.referrals.show', $service->incident) }}" class="badge bg-danger-lt text-danger text-decoration-none">
                                        <i class="ti ti-link me-1"></i> Rujukan Piket
                                    </a>
                                @else
                                    <span class="badge bg-secondary-lt text-muted">Layanan Mandiri</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $service->counselor?->name ?? 'Guru BK' }}</div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('bk.services.show', $service) }}" class="btn btn-sm btn-outline-primary" title="Buka Detail">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a href="{{ route('bk.services.edit', $service) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-notes-off fs-1 d-block mb-2 text-muted"></i>
                                Belum ada catatan sesi layanan BK untuk kriteria filter ini.
                                <div class="mt-2">
                                    <a href="{{ route('bk.services.create') }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-plus me-1"></i> Catat Layanan Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($services->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between py-2">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
