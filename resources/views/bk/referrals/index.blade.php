@extends('layouts.app')

@section('title', 'Rujukan Kasus Siswa ke BK')

@section('content')
<div class="container-xl">
    @php
        $bkActions = '<a href="' . route('bk.services.create') . '" class="btn btn-sm btn-primary shadow-xs fw-bold"><i class="ti ti-plus me-1"></i> Catat Layanan BK</a>' .
                     '<a href="' . route('bk.services.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-notes me-1"></i> Daftar Sesi Layanan</a>';
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Rujukan Kasus Siswa ke Bimbingan & Konseling',
        'subtitle' => 'Daftar kasus kejadian dari meja piket yang secara khusus dirujuk untuk bimbingan dan konseling siswa.',
        'icon' => 'ti ti-heart-handshake',
        'category' => 'BIMBINGAN & KONSELING',
        'academicYear' => $academicYear,
        'actions' => $bkActions
    ])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Ringkasan Statistik Rujukan -->
    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="ti ti-bell-ringing fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Total Rujukan Piket</div>
                            <div class="h2 mb-0">{{ $totalRujukan }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar rounded-circle">
                                <i class="ti ti-clock fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Menunggu Tindak Lanjut</div>
                            <div class="h2 mb-0 text-warning">{{ $totalMenunggu }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="ti ti-loader fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Sedang Diproses</div>
                            <div class="h2 mb-0 text-info">{{ $totalDiproses }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar rounded-circle">
                                <i class="ti ti-check fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium text-muted">Selesai Ditindaklanjuti</div>
                            <div class="h2 mb-0 text-success">{{ $totalSelesai }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('bk.referrals.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $academicYear === $year ? 'selected' : '' }}>TP {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Status BK --</option>
                        <option value="menunggu" {{ $status === 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                        <option value="diproses" {{ $status === 'diproses' ? 'selected' : '' }}>Sedang Diproses</option>
                        <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas --</option>
                        @foreach($classrooms as $cls)
                            <option value="{{ $cls->id }}" {{ $classroomId == $cls->id ? 'selected' : '' }}>{{ $cls->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / uraian kejadian...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search me-1"></i> Filter</button>
                    @if($status || $classroomId || $search)
                        <a href="{{ route('bk.referrals.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Kasus Rujukan -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Waktu Kejadian</th>
                        <th>Identitas Siswa</th>
                        <th>Kategori Kasus</th>
                        <th>Uraian Kejadian &amp; Tindakan Piket</th>
                        <th style="width: 140px;">Status Tindak Lanjut BK</th>
                        <th style="width: 110px;" class="text-center">Sesi BK</th>
                        <th style="width: 140px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                        @php
                            $bkRef = $incident->bkReferral;
                            $refStatus = $bkRef?->status ?? 'menunggu';
                            $refStatusLabel = $bkRef?->status_label ?? 'Menunggu';
                            $refStatusBadge = $bkRef?->status_badge_class ?? 'bg-warning-lt text-warning';
                            $sessionsCount = $incident->bkServices->count();
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $incident->incident_date->format('d/m/Y') }}</div>
                                <div class="text-muted small">{{ substr($incident->incident_time, 0, 5) }} WIB</div>
                            </td>
                            <td>
                                @if($incident->student)
                                    <a href="{{ route('bk.students.history', ['student' => $incident->student, 'academic_year' => $academicYear]) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $incident->student_name }}
                                    </a>
                                @else
                                    <span class="fw-bold text-dark">{{ $incident->student_name }}</span>
                                @endif
                                <div class="text-muted small">
                                    {{ $incident->classroom?->name ?? ($incident->student?->classroomForAcademicYear($academicYear)?->name ?? 'Kelas tidak terdata') }}
                                    @if($incident->student?->nisn)
                                        &bull; NISN: {{ $incident->student->nisn }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-danger-lt text-danger">{{ $incident->category_label }}</span>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 320px;" title="{{ $incident->description }}">
                                    {{ $incident->description }}
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 320px;">
                                    <em>Piket:</em> {{ $incident->action_taken }}
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $refStatusBadge }}">{{ $refStatusLabel }}</span>
                                @if($bkRef?->handler)
                                    <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                        <i class="ti ti-user me-1"></i>{{ $bkRef->handler->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($sessionsCount > 0)
                                    <span class="badge bg-teal-lt text-teal fw-bold">
                                        <i class="ti ti-notes me-1"></i>{{ $sessionsCount }} Sesi
                                    </span>
                                @else
                                    <span class="badge bg-secondary-lt text-muted">Belum ada</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('bk.referrals.show', $incident) }}" class="btn btn-sm btn-outline-primary" title="Buka Detail Rujukan">
                                        <i class="ti ti-eye me-1"></i> Tindak Lanjut
                                    </a>
                                    <a href="{{ route('bk.services.create', ['incident_id' => $incident->id]) }}" class="btn btn-sm btn-primary" title="Catat Sesi Layanan BK">
                                        <i class="ti ti-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-check-circle fs-1 text-success d-block mb-2"></i>
                                Tidak ada catatan kasus piket yang dirujuk ke BK untuk kriteria pencarian ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($incidents->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between py-2">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
