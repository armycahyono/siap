@extends('layouts.app')

@section('title', 'Dashboard Bimbingan & Konseling')

@section('content')
<div class="container-xl">
    <!-- Header Dashboard BK -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="avatar avatar-md bg-primary-lt text-primary rounded-circle">
                    <i class="ti ti-heart-handshake fs-1"></i>
                </span>
            </div>
            <div class="col">
                <div class="page-pretitle text-uppercase fw-bold text-primary" style="letter-spacing: 0.05rem;">
                    BIMBINGAN &amp; KONSELING SISWA DIGITAL
                </div>
                <h2 class="page-title text-dark fw-bold">
                    Dashboard BK
                </h2>
                <div class="text-muted small mt-1">
                    Pusat layanan konseling, agenda pembinaan, monitoring perkembangan siswa, dan tindak lanjut rujukan piket.
                </div>
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
                <!-- Selector Tahun Ajaran -->
                <form action="{{ Route::has('bk.dashboard') ? route('bk.dashboard') : url('/bk/dashboard') }}" method="GET" class="d-flex align-items-center gap-1">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="ti ti-calendar me-1"></i> Tahun Ajaran:
                        </span>
                        <select name="academic_year" class="form-select form-select-sm fw-bold border-start-0 ps-0" onchange="this.form.submit()" style="min-width: 140px;">
                            @foreach($availableYears as $ay)
                                <option value="{{ $ay->name }}" {{ $ay->name === $academicYear ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <a href="{{ route('bk.services.create') }}" class="btn btn-sm btn-primary shadow-xs fw-bold">
                    <i class="ti ti-plus me-1"></i> Catat Sesi Layanan
                </a>
                <a href="{{ route('bk.referrals.index') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                    <i class="ti ti-bell-ringing me-1"></i> Rujukan Piket
                </a>
                <a href="{{ route('bk.students.index') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                    <i class="ti ti-address-book me-1"></i> Buku Pribadi Siswa
                </a>
            </div>
        </div>
    </div>

    <!-- 4 Kartu Metrik Utama BK -->
    <div class="row row-cards mb-4">
        <!-- 1. Kasus Aktif -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-primary text-white">
                                <i class="ti ti-heart-rate-monitor fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Kasus Aktif</div>
                            <div class="h1 fw-bold mb-0 text-dark">
                                {{ $activeCasesCount }}
                                <span class="fs-4 text-muted font-weight-normal">kasus</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Menunggu Tindak Lanjut -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-warning text-dark">
                                <i class="ti ti-hourglass-empty fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Menunggu Tindak Lanjut</div>
                            <div class="h1 fw-bold mb-0 text-warning">
                                {{ $waitingFollowUpCount }}
                                <span class="fs-4 text-muted font-weight-normal">rujukan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Jadwal Hari Ini -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-info text-white">
                                <i class="ti ti-calendar-event fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Jadwal Hari Ini</div>
                            <div class="h1 fw-bold mb-0 text-info">
                                {{ $todaySchedulesCount }}
                                <span class="fs-4 text-muted font-weight-normal">sesi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Perlu Evaluasi -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-danger text-white">
                                <i class="ti ti-clipboard-check fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Perlu Evaluasi</div>
                            <div class="h1 fw-bold mb-0 text-danger">
                                {{ $needsEvaluationCount }}
                                <span class="fs-4 text-muted font-weight-normal">monitoring</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Agenda Layanan BK -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-calendar-time text-primary me-2"></i> Agenda Layanan BK
            </h4>
            <div class="card-actions">
                <a href="{{ route('bk.services.index') }}" class="btn btn-sm btn-outline-primary">
                    Semua Sesi Layanan <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Tanggal &amp; Waktu</th>
                        <th style="width: 22%;">Siswa &amp; Kelas</th>
                        <th style="width: 18%;">Bidang Layanan</th>
                        <th style="width: 22%;">Guru BK Konselor</th>
                        <th style="width: 13%;">Status RTL</th>
                        <th style="width: 10%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($upcomingServices as $service)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $service->service_date?->format('d/m/Y') }}</div>
                            <span class="text-muted small">{{ $service->service_date?->isToday() ? 'Hari Ini' : $service->service_date?->diffForHumans() }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $service->student?->name ?? '-' }}</div>
                            <span class="badge bg-blue-lt">{{ $service->student?->classroom?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $service->service_type_badge_class }}">
                                {{ $service->service_type_label }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $service->counselor?->name ?? '-' }}</div>
                            <span class="text-muted small">Konselor Pembina</span>
                        </td>
                        <td>
                            <span class="badge {{ $service->follow_up_status_badge_class }}">
                                {{ $service->follow_up_status_label }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('bk.services.show', $service) }}" class="btn btn-xs btn-outline-primary">
                                <i class="ti ti-eye me-1"></i> Buka Sesi
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="ti ti-calendar-off fs-1 text-secondary opacity-50 d-block mb-1"></i>
                            Tidak ada agenda layanan BK terjadwal untuk saat ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section: Kasus dan Pembinaan yang Memerlukan Tindak Lanjut (Rujukan Meja Piket) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-bell-ringing text-warning me-2"></i> Kasus dan Pembinaan yang Memerlukan Tindak Lanjut
                </h4>
                <div class="text-muted small mt-0.5">Rujukan kasus tata tertib dari Meja Piket Sekolah yang membutuhkan bimbingan konseling.</div>
            </div>
            <div class="card-actions">
                <a href="{{ route('bk.referrals.index') }}" class="btn btn-sm btn-outline-warning text-dark">
                    Lihat Semua Rujukan <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Tanggal Rujukan</th>
                        <th style="width: 22%;">Nama Siswa &amp; Kelas</th>
                        <th style="width: 18%;">Kategori Kasus Piket</th>
                        <th style="width: 27%;">Uraian / Catatan Pengantar</th>
                        <th style="width: 18%;" class="text-center">Aksi Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingReferrals as $ref)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $ref->incident?->incident_date?->format('d/m/Y') ?? $ref->created_at?->format('d/m/Y') }}</div>
                            <span class="badge {{ $ref->status_badge_class }}">{{ $ref->status_label }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $ref->incident?->student_name ?? $ref->student?->name ?? '-' }}</div>
                            <span class="badge bg-blue-lt">{{ $ref->incident?->classroom?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-danger-lt">{{ $ref->incident?->category_label ?? 'Kejadian Piket' }}</span>
                        </td>
                        <td>
                            <div class="text-dark small text-truncate" style="max-width: 300px;" title="{{ $ref->incident?->description }}">
                                {{ $ref->incident?->description ?? '-' }}
                            </div>
                            @if($ref->notes)
                                <div class="text-muted small fst-italic mt-0.5">Catatan: {{ $ref->notes }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($ref->incident?->student_id)
                            <a href="{{ route('bk.services.create', ['student_id' => $ref->incident->student_id, 'incident_id' => $ref->incident_id]) }}" class="btn btn-xs btn-primary fw-bold">
                                <i class="ti ti-plus me-1"></i> Buat Sesi BK
                            </a>
                            @else
                            <a href="{{ route('bk.referrals.show', $ref->incident_id) }}" class="btn btn-xs btn-outline-secondary">
                                Periksa Rujukan
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="ti ti-check-circle fs-1 text-success d-block mb-1"></i>
                            Semua rujukan kasus dari meja piket telah berhasil ditindaklanjuti.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2-Column Split: Ringkasan Pembinaan & Visitasi/Pemantauan -->
    <div class="row row-cards mb-4">
        <!-- Kolom Kiri: Ringkasan Pembinaan -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-chart-pie text-primary me-2"></i> Ringkasan Pembinaan
                    </h4>
                    <span class="badge bg-primary-lt">Total {{ $totalServices }} Layanan</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($serviceTypes as $typeKey => $meta)
                        @php
                            $cnt = $serviceTypeCounts[$typeKey] ?? 0;
                            $pct = $totalServices > 0 ? round(($cnt / $totalServices) * 100) : 0;
                        @endphp
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-light h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="avatar avatar-sm bg-{{ $meta['color'] }}-lt text-{{ $meta['color'] }} rounded">
                                        <i class="{{ $meta['icon'] }} fs-2"></i>
                                    </span>
                                    <span class="h2 mb-0 fw-bold text-dark">{{ $cnt }}</span>
                                </div>
                                <div class="fw-bold text-dark small">{{ $meta['label'] }}</div>
                                <div class="progress progress-sm mt-2">
                                    <div class="progress-bar bg-{{ $meta['color'] }}" style="width: {{ $pct }}%" role="progressbar"></div>
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.7rem;">{{ $pct }}% dari total bimbingan</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Visitasi & Pemantauan Siswa (Home Visit BK) -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-home-heart text-teal me-2"></i> Visitasi &amp; Pemantauan
                    </h4>
                    <a href="{{ route('visitations.index') }}" class="small text-teal fw-bold text-decoration-none">
                        Buka Visitasi <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Siswa &amp; Kelas</th>
                                <th>Jenis Visitasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentVisitations as $vis)
                            <tr>
                                <td class="text-nowrap">
                                    <div class="fw-semibold text-dark">{{ ($vis->executed_date ?? $vis->planned_date)?->format('d/m/Y') }}</div>
                                    <div class="text-muted small">{{ $vis->primaryOfficer?->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $vis->student?->name ?? '-' }}</div>
                                    <span class="badge bg-blue-lt small" style="font-size: 0.65rem;">{{ $vis->student?->classroom?->name ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-teal-lt" style="font-size: 0.68rem;">{{ $vis->visit_type_label }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $vis->status_badge_class }}" style="font-size: 0.68rem;">
                                        {{ $vis->status_label }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Belum ada data visitasi pada tahun ajaran ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Riwayat Aktivitas sesuai Hak Akses -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-history text-secondary me-2"></i> Riwayat Aktivitas Sesuai Hak Akses
                </h4>
                <div class="text-muted small mt-0.5">Catatan konseling mendalam terlindungi asas kerahasiaan BK dan hanya dapat diakses oleh konselor yang berwenang.</div>
            </div>
            <div class="card-actions">
                <a href="{{ route('bk.services.index') }}" class="btn btn-sm btn-outline-secondary">
                    Buku Jurnal BK <i class="ti ti-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Waktu Pelaksanaan</th>
                        <th style="width: 22%;">Siswa &amp; Kelas</th>
                        <th style="width: 18%;">Bidang &amp; Layanan</th>
                        <th style="width: 27%;">Ringkasan Layanan Publik</th>
                        <th style="width: 18%;">Konselor Pembina</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $act)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $act->service_date?->format('d/m/Y') }}</div>
                            <span class="text-muted small">{{ $act->service_date?->diffForHumans() }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $act->student?->name ?? '-' }}</div>
                            <span class="badge bg-blue-lt">{{ $act->student?->classroom?->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $act->service_type_badge_class }}">
                                {{ $act->service_type_label }}
                            </span>
                        </td>
                        <td>
                            <div class="text-dark small text-truncate" style="max-width: 320px;" title="{{ $act->summary }}">
                                {{ $act->summary ?: 'Sesi bimbingan konseling terlaksana.' }}
                            </div>
                            @if($act->has_follow_up)
                                <div class="text-info small mt-0.5"><i class="ti ti-arrow-forward me-1"></i>RTL: {{ $act->follow_up_status_label }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $act->counselor?->name ?? '-' }}</div>
                            <span class="text-muted small">Guru BK</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            Belum ada riwayat aktivitas layanan BK pada periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
