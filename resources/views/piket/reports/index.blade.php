@extends('layouts.app')

@section('title', 'Rekapitulasi & Laporan Piket')

@section('content')
<!-- Header (Model 2: Hero Context Banner) -->
@php
    $piketRepActions = '<a href="' . route('piket.reports.export', ['tab' => $tab, 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-file-spreadsheet me-1 text-success"></i> Ekspor CSV / Excel</a> ' .
        '<a href="' . route('piket.reports.print', ['tab' => $tab, 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) . '" target="_blank" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs"><i class="ti ti-printer me-1"></i> Cetak Format Dinas</a>';
@endphp
@include('layouts._hero_header', [
    'title' => 'Rekapitulasi & Laporan Piket',
    'subtitle' => 'Laporan komprehensif kehadiran petugas piket, perizinan siswa, kelas kosong, kasus kedisiplinan, dan buku tamu.',
    'icon' => 'ti ti-report-analytics',
    'category' => 'EVALUASI & PELAPORAN PIKET',
    'actions' => $piketRepActions
])

<!-- Filter Rentang Tanggal & Kelas -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form action="{{ route('piket.reports.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="col-auto fw-bold text-dark">
                <i class="ti ti-calendar-stats me-1"></i> Periode:
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text small">Dari</span>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text small">Sampai</span>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="classroom_id" class="form-select">
                    <option value="">-- Semua Kelas --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards Ringkasan Periode Ini -->
<div class="row row-cards mb-4">
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="card card-sm shadow-sm border-0 border-start border-warning border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">TOTAL TERLAMBAT</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $stats['total_late'] }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="card card-sm shadow-sm border-0 border-start border-primary border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">IZIN KELUAR / PULANG</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $stats['total_leave'] }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="card card-sm shadow-sm border-0 border-start border-info border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">KELAS INVAL / KOSONG</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $stats['total_inval'] }} <span class="fs-4 text-muted font-weight-normal">sesi</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="card card-sm shadow-sm border-0 border-start border-danger border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">KASUS & PELANGGARAN</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $stats['total_incidents'] }} <span class="fs-4 text-muted font-weight-normal">kejadian</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="card card-sm shadow-sm border-0 border-start border-success border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">BUKU TAMU</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $stats['total_guests'] }} <span class="fs-4 text-muted font-weight-normal">tamu</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs Card -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white p-2">
        <ul class="nav nav-tabs card-header-tabs border-bottom-0">
            <li class="nav-item">
                <a href="{{ route('piket.reports.index', ['tab' => 'permits', 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) }}" 
                   class="nav-link {{ $tab === 'permits' ? 'active fw-bold text-primary' : 'text-secondary' }}">
                    <i class="ti ti-door-exit me-1 text-primary"></i> Perizinan & Keterlambatan
                    <span class="badge bg-primary-lt ms-2">{{ $permits->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.reports.index', ['tab' => 'inval', 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) }}" 
                   class="nav-link {{ $tab === 'inval' ? 'active fw-bold text-info' : 'text-secondary' }}">
                    <i class="ti ti-chalkboard me-1 text-info"></i> Kelas Kosong & Inval
                    <span class="badge bg-info-lt ms-2">{{ $invals->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.reports.index', ['tab' => 'incidents', 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) }}" 
                   class="nav-link {{ $tab === 'incidents' ? 'active fw-bold text-danger' : 'text-secondary' }}">
                    <i class="ti ti-alert-triangle me-1 text-danger"></i> Kasus & Kejadian
                    <span class="badge bg-danger-lt ms-2">{{ $incidents->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.reports.index', ['tab' => 'guests', 'start_date' => $startDate, 'end_date' => $endDate, 'classroom_id' => $classroomId]) }}" 
                   class="nav-link {{ $tab === 'guests' ? 'active fw-bold text-success' : 'text-secondary' }}">
                    <i class="ti ti-address-book me-1 text-success"></i> Buku Tamu
                    <span class="badge bg-success-lt ms-2">{{ $guests->count() }}</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Data Table Card -->
<div class="card border-0 shadow-sm">
    @if($tab === 'permits')
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-door-exit text-primary me-2"></i> Rekapitulasi Perizinan & Keterlambatan Siswa
            </h4>
            <div class="card-actions">
                <span class="badge bg-primary-lt fs-4">{{ $permits->count() }} Data</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. Izin & Tanggal</th>
                        <th>Nama Siswa & Kelas</th>
                        <th>Jenis Izin</th>
                        <th>Waktu</th>
                        <th>Alasan</th>
                        <th>Tindakan / Sanksi</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permits as $p)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $p->permit_number }}</div>
                                <span class="text-muted small">{{ $p->permit_date?->format('d/m/Y') }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $p->student_name }}</div>
                                <span class="badge bg-blue-lt">{{ $p->classroom?->name ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $p->permit_type_badge_class }}">{{ $p->permit_type_label }}</span>
                            </td>
                            <td>{{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }} WIB</td>
                            <td><span class="text-secondary small">{{ $p->reason }}</span></td>
                            <td><span class="text-muted small">{{ $p->action_taken ?: '-' }}</span></td>
                            <td><span class="small">{{ $p->givenBy?->name ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Tidak ada data perizinan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($tab === 'inval')
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-chalkboard text-info me-2"></i> Rekapitulasi Kelas Kosong & Guru Inval
            </h4>
            <div class="card-actions">
                <span class="badge bg-info-lt fs-4">{{ $invals->count() }} Data</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal & Kelas</th>
                        <th>Guru Berhalangan</th>
                        <th>Mata Pelajaran</th>
                        <th>Jam Ke-</th>
                        <th>Guru Inval / Pengganti</th>
                        <th>Alasan</th>
                        <th>Materi / Tugas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invals as $inv)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $inv->classroom?->name ?? '-' }}</div>
                                <span class="text-muted small">{{ $inv->journal?->date?->format('d/m/Y') }}</span>
                            </td>
                            <td><span class="fw-bold text-danger">{{ $inv->absentTeacher?->name ?? '-' }}</span></td>
                            <td>{{ $inv->subject?->name ?? '-' }}</td>
                            <td>Jam {{ $inv->period_start }}-{{ $inv->period_end }}</td>
                            <td>
                                @if($inv->invalTeacher)
                                    <span class="badge bg-success-lt fw-bold">{{ $inv->invalTeacher->name }}</span>
                                @else
                                    <span class="badge bg-warning-lt">Belum Ada</span>
                                @endif
                            </td>
                            <td>{{ $inv->reason }}</td>
                            <td><span class="small text-muted">{{ $inv->assignment_material ?: '-' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Tidak ada data kelas inval pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($tab === 'incidents')
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-alert-triangle text-danger me-2"></i> Rekapitulasi Kasus & Kejadian Siswa
            </h4>
            <div class="card-actions">
                <span class="badge bg-danger-lt fs-4">{{ $incidents->count() }} Data</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu &amp; Tanggal</th>
                        <th>Nama Siswa &amp; Kelas</th>
                        <th>Kategori</th>
                        <th>Uraian Kejadian</th>
                        <th>Tindakan Piket</th>
                        <th>Sanksi Kedisiplinan</th>
                        <th>Tindak Lanjut</th>
                        <th>Status &amp; Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $inc)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $inc->incident_date?->format('d/m/Y') }}</div>
                                <span class="text-muted small">{{ substr($inc->incident_time, 0, 5) }} WIB</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $inc->student_name }}</div>
                                <span class="badge bg-blue-lt">{{ $inc->classroom?->name ?? '-' }}</span>
                            </td>
                            <td><span class="badge bg-danger-lt">{{ $inc->category_label }}</span></td>
                            <td>
                                <span class="small text-secondary">{{ $inc->description }}</span>
                                @if($inc->confiscated_items)
                                    <div class="small text-muted mt-1">
                                        <i class="ti ti-package fs-5 me-1 text-danger"></i>Sitaan: <span class="text-danger fw-semibold">{{ $inc->confiscated_items }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><span class="small text-dark">{{ $inc->action_taken }}</span></td>
                            <td>
                                @if($inc->discipline_action)
                                    <span class="small text-dark fw-medium">{{ $inc->discipline_action }}</span>
                                @else
                                    <span class="text-muted small fst-italic">-</span>
                                @endif
                            </td>
                            <td><span class="badge bg-purple-lt">{{ $inc->follow_up_label }}</span></td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge {{ $inc->status === 'selesai' ? 'bg-success text-white' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($inc->status) }}
                                    </span>
                                    @if($inc->is_verified)
                                        <span class="badge bg-green-lt" title="Diverifikasi oleh {{ $inc->verifiedBy?->name ?? 'Tim Kedisiplinan' }}">
                                            <i class="ti ti-check me-1"></i> Terverifikasi
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-lt">
                                            <i class="ti ti-clock me-1"></i> Belum Verif
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="ti ti-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Tidak ada catatan kasus pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @else
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-address-book text-success me-2"></i> Rekapitulasi Buku Tamu Sekolah
            </h4>
            <div class="card-actions">
                <span class="badge bg-success-lt fs-4">{{ $guests->count() }} Data</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal & Waktu</th>
                        <th>Nama Tamu & Kontak</th>
                        <th>Instansi</th>
                        <th>Keperluan</th>
                        <th>Bertemu Dengan</th>
                        <th>No. Badge</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guests as $g)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $g->visit_date?->format('d/m/Y') }}</div>
                                <span class="text-muted small">{{ substr($g->check_in_time, 0, 5) }} WIB</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $g->guest_name }}</div>
                                <span class="text-muted small">{{ $g->phone ?: '-' }}</span>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $g->institution ?: 'Masyarakat / Orang Tua' }}</span></td>
                            <td><span class="small text-dark">{{ $g->purpose }}</span></td>
                            <td><span class="small fw-bold">{{ $g->meet_with }}</span></td>
                            <td>{{ $g->badge_number ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $g->check_out_time ? 'bg-secondary-lt' : 'bg-success text-white' }}">
                                    {{ $g->check_out_time ? 'Selesai' : 'Sedang Berkunjung' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Tidak ada buku tamu pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
