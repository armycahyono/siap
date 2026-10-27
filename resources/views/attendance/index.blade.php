@extends('layouts.app')

@section('title', 'Live Presensi Sekolah (Mesin, GPS & Telegram)')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $attActions = '<span class="badge bg-success text-white shadow-xs me-2 d-none d-sm-inline-flex align-items-center" id="liveBadge" title="Browser mengecek data scan baru otomatis setiap 3.5 detik"><span class="status-dot status-dot-animated bg-white me-1"></span> Live Auto-Sync</span> ';
    if (auth()->user()->canManageManualTeacherAttendance()) {
        $attActions .= '<a href="' . route('attendance.manual-gtk.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-bolt-off me-1 text-warning"></i> Presensi GTK Susulan</a> ';
    }
    if (auth()->user()->canManageAttendanceDevices()) {
        $attActions .= '<a href="' . route('attendance.devices.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-devices me-1 text-info"></i> Kelola Mesin</a> ';
    }
@endphp
<div class="container-xl px-0 mb-3">
    @include('layouts._hero_header', [
        'title' => 'Live Presensi Sekolah',
        'subtitle' => 'Pemantauan presensi terpadu GTK dan siswa secara real-time dari seluruh jalur: Mesin Biometrik (Fingerprint/Wajah/RFID), Geotagging GPS Mobile, dan Bot Telegram.',
        'icon' => 'ti ti-broadcast',
        'category' => 'KEDISIPLINAN & OPERASIONAL',
        'actions' => $attActions
    ])
</div>

<div class="page-body">
    <div class="container-xl">
        <!-- Banner Toast Saat Scan Baru Masuk -->
        <div id="liveScanToast" class="alert alert-info alert-dismissible shadow-sm mb-3 d-none animate__animated animate__fadeInDown" role="alert">
            <div class="d-flex align-items-center">
                <span class="spinner-grow spinner-grow-sm text-info me-2" role="status"></span>
                <div id="liveScanToastText" class="fw-bold">Scan presensi baru terdeteksi!</div>
            </div>
        </div>

        <!-- Filter Tanggal & Status Mesin Ringkas -->
        <div class="card shadow-sm border-0 mb-3 d-print-none">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-2 mb-lg-0">
                        <form method="GET" action="{{ route('attendance.index') }}" class="d-flex align-items-center gap-2">
                            @if(request('channel'))
                                <input type="hidden" name="channel" value="{{ request('channel') }}">
                            @endif
                            <span class="text-secondary small fw-medium">Tanggal:</span>
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-calendar"></i></span>
                                <input type="date" name="date" id="currentDateInput" class="form-control form-control-sm" value="{{ $date === 'all' ? '' : $date }}" onchange="this.form.submit()">
                            </div>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('attendance.index', ['date' => now()->toDateString(), 'channel' => request('channel')]) }}" class="btn btn-outline-secondary {{ $date === now()->toDateString() ? 'active' : '' }}">Hari Ini</a>
                                <a href="{{ route('attendance.index', ['date' => 'all', 'channel' => request('channel')]) }}" class="btn btn-outline-secondary {{ $date === 'all' ? 'active' : '' }}">Semua</a>
                            </div>
                            <span class="badge bg-blue-lt ms-2">
                                <i class="ti ti-calendar-event me-1"></i> {{ $targetDay }}
                            </span>
                        </form>
                    </div>
                    <div class="col-lg-7 text-lg-end text-secondary small">
                        <div class="d-inline-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                            <span class="badge bg-purple-lt text-purple" title="Presensi dari mesin fingerprint/face/kartu">
                                <i class="ti ti-device-watch me-1"></i> Mesin: <strong class="ms-1" id="statMachineScans">{{ $stats['machine_scans'] ?? 0 }}</strong>
                            </span>
                            <span class="badge bg-primary-lt text-primary" title="Presensi dari PWA Geotagging GPS">
                                <i class="ti ti-map-pin me-1"></i> GPS: <strong class="ms-1" id="statGpsScans">{{ $stats['gps_scans'] ?? 0 }}</strong>
                            </span>
                            <span class="badge bg-info-lt text-info" title="Presensi dari Bot Telegram">
                                <i class="ti ti-brand-telegram me-1"></i> Telegram: <strong class="ms-1" id="statTelegramScans">{{ $stats['telegram_scans'] ?? 0 }}</strong>
                            </span>
                            <span class="text-dark fw-bold ms-1">Total: <span class="text-teal fs-3" id="statTotalScans">{{ $stats['total_scans'] }}</span> scan</span>
                            @if(auth()->user()->canManageAttendanceDevices())
                                <a href="{{ route('attendance.devices.index') }}" class="btn btn-sm btn-outline-primary ms-1 py-0">
                                    <i class="ti ti-devices me-1"></i> Mesin
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kartu Statistik Cepat Operasional Hari Ini -->
        <div class="row row-cards mb-3 d-print-none">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-teal">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-teal text-white avatar">
                                    <i class="ti ti-school fs-2"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium fs-2 fw-bold text-dark" id="statScheduledTeachers">
                                    {{ $stats['scheduled_teachers_count'] }}
                                </div>
                                <div class="text-secondary small">Guru & Staf Tugas {{ $targetDay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 {{ ($stats['late_teachers_count'] > 0 || $stats['early_teachers_count'] > 0) ? 'border-danger' : 'border-success' }}">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="{{ ($stats['late_teachers_count'] > 0 || $stats['early_teachers_count'] > 0) ? 'bg-danger' : 'bg-success' }} text-white avatar">
                                    <i class="ti ti-user-exclamation fs-2"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="fs-2 fw-bold text-dark" id="statLateTeachers">{{ $stats['late_teachers_count'] }}</span>
                                    <span class="text-danger small">Terlambat</span>
                                    <span class="text-secondary">/</span>
                                    <span class="fs-3 fw-bold text-warning" id="statEarlyTeachers">{{ $stats['early_teachers_count'] }}</span>
                                    <span class="text-warning small">Pulang Awal</span>
                                </div>
                                <div class="text-secondary small">Evaluasi Jam Guru & Staf</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 {{ ($stats['late_students_count'] > 0 || $stats['early_students_count'] > 0) ? 'border-warning' : 'border-success' }}">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-warning text-white avatar">
                                    <i class="ti ti-clock-pause fs-2"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="fs-2 fw-bold text-dark" id="statLateStudents">{{ $stats['late_students_count'] }}</span>
                                    <span class="text-danger small">Terlambat</span>
                                    <span class="text-secondary">/</span>
                                    <span class="fs-3 fw-bold text-orange" id="statEarlyStudents">{{ $stats['early_students_count'] }}</span>
                                    <span class="text-orange small">Pulang Awal</span>
                                </div>
                                <div class="text-secondary small">Disiplin Jam Siswa</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar">
                                    <i class="ti ti-scan fs-2"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium fs-2 fw-bold text-dark" id="statTotalScans">
                                    {{ $stats['total_scans'] }}
                                </div>
                                <div class="text-secondary small">Total Scan Masuk Hari Ini</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigasi Presensi Terpadu -->
        <div class="card mb-3 d-print-none shadow-sm border-0">
            <div class="card-header border-bottom py-2">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="#tab-teachers" class="nav-link active fw-bold" data-bs-toggle="tab" aria-selected="true" role="tab">
                            <i class="ti ti-school me-2 text-teal"></i> Presensi Guru & Staf ({{ $teacherAttendanceList->count() }})
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-students-late" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-alert-circle me-2 text-warning"></i> Siswa Terlambat ({{ $lateStudents->count() }})
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-students-early" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-clock-minus me-2 text-orange"></i> Siswa Pulang Awal ({{ $earlyStudents->count() }})
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-students-absent" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-user-x me-2 text-danger"></i> Siswa Belum Hadir / Izin ({{ $absentStudents->count() }})
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-students-truancy" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-run me-2 text-dark"></i> Siswa Bolos KBM ({{ $truancyStudents->count() }})
                            @if($truancyStudents->count() > 0)
                                <span class="badge bg-danger ms-1">{{ $truancyStudents->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#tab-raw-logs" class="nav-link fw-bold" data-bs-toggle="tab" aria-selected="false" tabindex="-1" role="tab">
                            <i class="ti ti-broadcast me-2 text-primary"></i> Log Aktivitas Presensi (Semua Jalur)
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- TAB 1: PRESENSI GURU & STAF BERDASARKAN JADWAL HARI INI -->
            <div class="tab-pane active show" id="tab-teachers" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between py-2 gap-2">
                        <div>
                            <h3 class="card-title fw-bold mb-0">Daftar Kehadiran Guru & Staf Hari {{ $targetDay }}</h3>
                            <div class="text-secondary small">
                                @if($schoolStartTimeStr)
                                    Standar jam sekolah: <strong>{{ $schoolStartTimeStr }} - {{ $schoolEndTimeStr }} WIB</strong>. Guru Mapel dihitung sesuai jam mengajarnya; Staf TU & Guru Piket mengikuti jam sekolah penuh.
                                @else
                                    Status: <strong class="text-teal">{{ $holidayReason ?? "Hari Libur ({$targetDay})" }}</strong> &bull; Guru dan Tenaga Kependidikan tidak ada kewajiban masuk.
                                @endif
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Tepat Waktu: {{ $teacherAttendanceList->where('status', 'ontime')->count() }}</span>
                            <span class="badge bg-danger-lt"><i class="ti ti-clock-pause me-1"></i> Terlambat: {{ $teacherAttendanceList->where('status', 'late')->count() }}</span>
                            <span class="badge bg-warning-lt"><i class="ti ti-clock-minus me-1"></i> Pulang Awal: {{ $teacherAttendanceList->where('checkout_status', 'early')->count() }}</span>
                            <span class="badge bg-secondary-lt"><i class="ti ti-clock me-1"></i> Belum/Menunggu: {{ $teacherAttendanceList->whereIn('status', ['waiting', 'absent'])->count() }}</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-center">No</th>
                                    <th>Nama Guru & Staf</th>
                                    <th>Jadwal / Tugas Hari Ini</th>
                                    <th class="text-center" style="min-width: 170px;">Jam Datang & Masuk</th>
                                    <th class="text-center" style="min-width: 170px;">Jam Pulang & Keluar</th>
                                </tr>
                            </thead>
                            <tbody id="teacherAttendanceTableBody">
                                @forelse($teacherAttendanceList as $idx => $t)
                                    <tr id="teacher-row-{{ $t->id }}">
                                        <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="avatar avatar-xs bg-teal-lt text-teal rounded-circle me-2 font-monospace fw-bold">
                                                    {{ $t->id }}
                                                </span>
                                                <div>
                                                    <div class="fw-bold text-dark fs-3">{{ $t->name }}</div>
                                                    <div class="text-secondary small d-flex align-items-center gap-1 flex-wrap mt-1">
                                                        <span>{{ $t->position }}</span>
                                                        @if($t->nigs)
                                                            &bull; <code>NIGS: {{ $t->nigs }}</code>
                                                        @endif
                                                        <span class="ms-1">
                                                            @if($t->is_piket && $t->has_schedule)
                                                                <span class="badge bg-purple-lt text-purple small">Piket &amp; Mengajar</span>
                                                            @elseif($t->is_piket)
                                                                <span class="badge bg-teal-lt text-teal small">Guru Piket</span>
                                                            @elseif($t->is_staff_tu)
                                                                <span class="badge bg-blue-lt text-blue small">Staf TU</span>
                                                            @elseif($t->has_schedule)
                                                                <span class="badge bg-indigo-lt text-indigo small">Guru Mapel</span>
                                                            @else
                                                                <span class="badge bg-azure-lt text-azure small">Bebas Jam</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark small d-block">
                                                <i class="ti ti-notebook text-teal me-1"></i> {{ $t->lessons_summary }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($t->earliest_start_time)
                                                <div class="badge bg-blue-lt font-monospace small mb-1">
                                                    <i class="ti ti-clock me-1"></i> Target: {{ $t->earliest_start_time }}
                                                </div>
                                            @endif
                                            <div>
                                                @if($t->first_scan_at)
                                                    <strong class="text-dark font-monospace fs-3">
                                                        {{ $t->first_scan_at->format('H:i:s') }}
                                                    </strong>
                                                    @if(!empty($t->first_scan_channel))
                                                        <div class="mt-1">
                                                            <span class="badge {{ $t->first_scan_channel['class'] }} small">
                                                                <i class="{{ $t->first_scan_channel['icon'] }} me-1"></i>{{ $t->first_scan_channel['label'] }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <span class="text-secondary small d-block">via {{ ucfirst($t->verify_type ?: 'Scan') }}</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary-lt">Belum Scan</span>
                                                @endif
                                            </div>
                                            <span class="badge bg-{{ $t->status_badge }}-lt fw-bold mt-1">
                                                @if($t->status === 'ontime')
                                                    <i class="ti ti-circle-check me-1 text-success"></i>
                                                @elseif($t->status === 'late')
                                                    <i class="ti ti-alert-triangle me-1 text-danger"></i>
                                                @elseif($t->status === 'absent')
                                                    <i class="ti ti-circle-x me-1 text-danger"></i>
                                                @else
                                                    <i class="ti ti-clock me-1"></i>
                                                @endif
                                                {{ $t->status_text }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($t->expected_end_time)
                                                <div class="badge bg-blue-lt font-monospace small mb-1">
                                                    <i class="ti ti-clock-check me-1"></i> Target: {{ $t->expected_end_time }}
                                                </div>
                                            @endif
                                            <div>
                                                @if($t->last_scan_at)
                                                    <strong class="text-dark font-monospace fs-3">
                                                        {{ $t->last_scan_at->format('H:i:s') }}
                                                    </strong>
                                                    @if(!empty($t->last_scan_channel))
                                                        <div class="mt-1">
                                                            <span class="badge {{ $t->last_scan_channel['class'] }} small">
                                                                <i class="{{ $t->last_scan_channel['icon'] }} me-1"></i>{{ $t->last_scan_channel['label'] }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                @elseif($t->first_scan_at)
                                                    <span class="text-muted small italic">-</span>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </div>
                                            @if($t->checkout_status !== 'none')
                                                <span class="badge bg-{{ $t->checkout_badge }}-lt fw-bold mt-1">
                                                    @if($t->checkout_status === 'ontime')
                                                        <i class="ti ti-circle-check me-1 text-success"></i>
                                                    @elseif($t->checkout_status === 'early')
                                                        <i class="ti ti-clock-minus me-1 text-warning"></i>
                                                    @elseif($t->checkout_status === 'missing')
                                                        <i class="ti ti-alert-circle me-1 text-orange"></i>
                                                    @elseif($t->checkout_status === 'active')
                                                        <i class="ti ti-run me-1 text-info"></i>
                                                    @endif
                                                    {{ $t->checkout_text }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-muted"><i class="ti ti-calendar-off fs-1"></i></div>
                                                <p class="empty-title">Tidak ada guru/staf bertugas pada hari {{ $targetDay }}</p>
                                                <p class="empty-subtitle text-secondary">
                                                    Jadwal mengajar kosong atau hari libur.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CATATAN KETERLAMBATAN SISWA -->
            <div class="tab-pane" id="tab-students-late" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex align-items-center justify-content-between py-2">
                        <div>
                            <h3 class="card-title fw-bold mb-0 text-warning">Daftar Siswa Terlambat Masuk</h3>
                            <div class="text-secondary small">Siswa yang melakukan scan setelah batas masuk sekolah (pukul <strong>07:00 WIB</strong>).</div>
                        </div>
                        <span class="badge bg-warning text-white fs-4 px-3 py-1">{{ $lateStudents->count() }} Siswa Terlambat</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-center">No</th>
                                    <th>NISN / NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Waktu Scan</th>
                                    <th class="text-center">Keterlambatan</th>
                                    <th class="text-center">Metode</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lateStudents as $idx => $s)
                                    <tr>
                                        <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                        <td><code>{{ $s->nisn }}</code></td>
                                        <td><strong class="text-dark fs-3">{{ $s->name }}</strong></td>
                                        <td><span class="badge bg-indigo-lt fw-bold">{{ $s->classroom }}</span></td>
                                        <td class="text-center font-monospace fw-bold text-danger fs-3">
                                            {{ $s->scanned_at->format('H:i:s') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger-lt text-danger fw-bold fs-4 py-1 px-2">
                                                <i class="ti ti-clock-pause me-1"></i> {{ $s->status_text }}
                                            </span>
                                        </td>
                                        <td class="text-center text-secondary small">
                                            {{ ucfirst($s->verify_type ?: 'Scan') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-success"><i class="ti ti-circle-check fs-1"></i></div>
                                                <p class="empty-title text-success">Tidak Ada Siswa Terlambat</p>
                                                <p class="empty-subtitle text-secondary">
                                                    Semua siswa yang telah hadir tiba sebelum pukul 07:00 WIB.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CATATAN SISWA PULANG AWAL -->
            <div class="tab-pane" id="tab-students-early" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between py-2 gap-2">
                        <div>
                            <h3 class="card-title fw-bold mb-0 text-orange">Daftar Siswa Pulang Lebih Awal</h3>
                            <div class="text-secondary small">Siswa yang melakukan scan keluar sebelum jam bubar sekolah hari {{ $targetDay }} (pukul <strong>{{ $schoolEndTimeStr }} WIB</strong>).</div>
                        </div>
                        <span class="badge bg-orange text-white fs-4 px-3 py-1">{{ $earlyStudents->count() }} Siswa Pulang Awal</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-center">No</th>
                                    <th>NISN / NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Scan Masuk</th>
                                    <th class="text-center">Scan Pulang</th>
                                    <th class="text-center">Keterangan</th>
                                    <th class="text-center">Metode</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($earlyStudents as $idx => $s)
                                    <tr>
                                        <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                        <td><code>{{ $s->nisn }}</code></td>
                                        <td><strong class="text-dark fs-3">{{ $s->name }}</strong></td>
                                        <td><span class="badge bg-indigo-lt fw-bold">{{ $s->classroom }}</span></td>
                                        <td class="text-center font-monospace fw-bold text-success fs-3">
                                            {{ $s->scanned_in_at->format('H:i:s') }}
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-orange fs-3">
                                            {{ $s->scanned_out_at->format('H:i:s') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning-lt text-warning fw-bold fs-4 py-1 px-2">
                                                <i class="ti ti-clock-minus me-1"></i> {{ $s->status_text }}
                                            </span>
                                        </td>
                                        <td class="text-center text-secondary small">
                                            {{ ucfirst($s->verify_type ?: 'Scan') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-success"><i class="ti ti-circle-check fs-1"></i></div>
                                                <p class="empty-title text-success">Tidak Ada Siswa Pulang Lebih Awal</p>
                                                <p class="empty-subtitle text-secondary">
                                                    Tidak ada siswa yang melakukan scan kepulangan sebelum pukul {{ $schoolEndTimeStr }} WIB.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: SISWA BELUM HADIR -->
            <div class="tab-pane" id="tab-students-absent" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex align-items-center justify-content-between py-2">
                        <div>
                            <h3 class="card-title fw-bold mb-0 text-danger">Daftar Siswa Belum Hadir / Belum Scan</h3>
                            <div class="text-secondary small">Siswa yang belum ada catatan scan absensi sama sekali pada tanggal ini.</div>
                        </div>
                        <span class="badge bg-danger text-white fs-4 px-3 py-1">{{ $absentStudents->count() }} Belum Hadir</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-center">No</th>
                                    <th>NISN / NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($absentStudents as $idx => $s)
                                    <tr>
                                        <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                        <td><code>{{ $s->nisn }}</code></td>
                                        <td><strong class="text-dark">{{ $s->name }}</strong></td>
                                        <td><span class="badge bg-secondary-lt">{{ $s->classroom }}</span></td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $s->status_badge ?? 'danger' }}-lt text-{{ $s->status_badge ?? 'danger' }} fw-bold">
                                                {{ $s->status_text }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-success"><i class="ti ti-checks fs-1"></i></div>
                                                <p class="empty-title text-success">Lengkap 100%</p>
                                                <p class="empty-subtitle text-secondary">Seluruh siswa telah hadir dan memiliki catatan presensi.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: CATATAN SISWA INDIKASI BOLOS KBM -->
            <div class="tab-pane" id="tab-students-truancy" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between py-2 gap-2">
                        <div>
                            <h3 class="card-title fw-bold mb-0 text-dark">Daftar Siswa Terindikasi Bolos KBM</h3>
                            <div class="text-secondary small">Siswa yang terdeteksi scan fingerprint gerbang atau hadir di sesi awal, tetapi tercatat <strong>Alfa (A)</strong> di jam pelajaran kelas tanpa surat izin piket.</div>
                        </div>
                        <span class="badge bg-dark text-white fs-4 px-3 py-1">{{ $truancyStudents->count() }} Terindikasi Bolos</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-center">No</th>
                                    <th>NISN / NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Scan Masuk Gerbang</th>
                                    <th class="text-center">Jam Pelajaran Alfa</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($truancyStudents as $idx => $s)
                                    <tr>
                                        <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                        <td><code>{{ $s->nisn }}</code></td>
                                        <td><strong class="text-dark fs-3">{{ $s->name }}</strong></td>
                                        <td><span class="badge bg-indigo-lt fw-bold">{{ $s->classroom }}</span></td>
                                        <td class="text-center font-monospace fw-bold text-success fs-3">
                                            {{ $s->scanned_in_at ? $s->scanned_in_at->format('H:i:s') : '-' }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger text-white fw-bold fs-4 py-1 px-2">
                                                {{ $s->alfa_jp }} JP Alfa
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-dark-lt text-dark fw-bold">
                                                <i class="ti ti-alert-triangle me-1 text-danger"></i> Bolos KBM
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-success"><i class="ti ti-shield-check fs-1"></i></div>
                                                <p class="empty-title text-success">Tidak Ada Siswa Bolos KBM</p>
                                                <p class="empty-subtitle text-secondary">
                                                    Semua siswa yang hadir di sekolah tertib mengikuti KBM di dalam kelas.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 6: LOG AKTIVITAS PRESENSI REAL-TIME (SEMUA JALUR) -->
            <div class="tab-pane" id="tab-raw-logs" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <h3 class="card-title mb-0 fw-bold">
                                <i class="ti ti-broadcast text-primary me-1"></i> Log Aktivitas Presensi Real-Time
                            </h3>
                            <span class="badge bg-secondary-lt" id="rawLogTotalBadge">{{ $logs->total() }} entri</span>
                        </div>
                        <form method="GET" action="{{ route('attendance.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <select name="channel" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                <option value="">Semua Jalur Absen</option>
                                <option value="machine" {{ ($channel ?? '') === 'machine' ? 'selected' : '' }}>Mesin Biometrik</option>
                                <option value="gps" {{ ($channel ?? '') === 'gps' ? 'selected' : '' }}>Presensi GPS (Mobile)</option>
                                <option value="telegram" {{ ($channel ?? '') === 'telegram' ? 'selected' : '' }}>Bot Telegram</option>
                            </select>
                            <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                <option value="">Semua Kategori</option>
                                <option value="teacher" {{ $type === 'teacher' ? 'selected' : '' }}>Guru & Staf</option>
                                <option value="student" {{ $type === 'student' ? 'selected' : '' }}>Siswa</option>
                                <option value="unknown" {{ $type === 'unknown' ? 'selected' : '' }}>Tidak Dikenal</option>
                            </select>
                            <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="check_in" {{ $statusFilter === 'check_in' ? 'selected' : '' }}>Masuk</option>
                                <option value="check_out" {{ $statusFilter === 'check_out' ? 'selected' : '' }}>Pulang</option>
                            </select>
                            <div class="input-icon">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / PIN..." value="{{ $search }}">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1">No</th>
                                    <th>Waktu Presensi</th>
                                    <th>Jalur Absen</th>
                                    <th>Kategori</th>
                                    <th>PIN / ID</th>
                                    <th>Nama Lengkap</th>
                                    <th>Status Absen</th>
                                    <th>Metode Verifikasi</th>
                                    <th>Perangkat / Sumber</th>
                                </tr>
                            </thead>
                            <tbody id="rawLogsTableBody">
                                @forelse($logs as $index => $log)
                                    @php
                                        $chBadge = $log->channel_badge;
                                    @endphp
                                    <tr id="log-row-{{ $log->id }}">
                                        <td class="text-secondary">{{ $logs->firstItem() + $index }}</td>
                                        <td class="fw-bold">
                                            <i class="ti ti-clock text-muted me-1"></i>
                                            {{ $log->scanned_at ? $log->scanned_at->format('H:i:s') : '-' }}
                                            <span class="text-secondary small fw-normal d-block">
                                                {{ $log->scanned_at ? $log->scanned_at->translatedFormat('d F Y') : '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $chBadge['class'] }} fw-bold">
                                                <i class="{{ $chBadge['icon'] }} me-1"></i>{{ $chBadge['label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($log->user_type === 'teacher')
                                                <span class="badge bg-teal-lt"><i class="ti ti-school me-1"></i>Guru & Staf</span>
                                            @elseif($log->user_type === 'student')
                                                <span class="badge bg-indigo-lt"><i class="ti ti-user me-1"></i>Siswa</span>
                                            @else
                                                <span class="badge bg-orange-lt"><i class="ti ti-help me-1"></i>Tidak Dikenal</span>
                                            @endif
                                        </td>
                                        <td>
                                            <code class="fw-bold fs-3 text-dark">{{ $log->pin }}</code>
                                        </td>
                                        <td>
                                            @if($log->user)
                                                <span class="fw-bold text-dark">{{ $log->user->name }}</span>
                                            @elseif($log->student)
                                                <span class="fw-bold text-dark">{{ $log->student->name }}</span>
                                                <div class="text-secondary small">Kelas: {{ $log->student->classroom?->name }}</div>
                                            @else
                                                <span class="text-secondary fst-italic">{{ $log->name ?: 'Belum Terdaftar' }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-blue-lt text-primary fw-bold">
                                                <i class="ti ti-scan me-1"></i>Scan Terverifikasi
                                            </span>
                                            @if($log->status === 'check_out')
                                                <div class="text-secondary small" style="font-size: 11px;">
                                                    <i class="ti ti-arrow-right me-1"></i>Status: Pulang
                                                </div>
                                            @elseif($log->status === 'check_in')
                                                <div class="text-secondary small" style="font-size: 11px;">
                                                    <i class="ti ti-arrow-left me-1"></i>Status: Masuk
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small d-flex align-items-center">
                                                <i class="{{ $log->verify_icon }} text-primary fs-2 me-1"></i>
                                                {{ $log->verify_type_label }}
                                            </span>
                                        </td>
                                        <td class="text-secondary small">
                                            @if($log->verify_type === 'geotag' || $log->device_sn === 'MOBILE-PWA')
                                                <span class="text-primary fw-medium"><i class="ti ti-device-mobile me-1"></i>GPS Mobile (PWA)</span>
                                            @elseif($log->verify_type === 'telegram' || $log->device_sn === 'TELEGRAM')
                                                <span class="text-info fw-medium"><i class="ti ti-brand-telegram me-1"></i>Bot Telegram</span>
                                            @else
                                                <span><i class="ti ti-device-watch me-1"></i>{{ $log->device ? $log->device->name : ($log->device_sn ?: 'Mesin Biometrik') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyRawLogRow">
                                        <td colspan="9" class="text-center py-5">
                                            <div class="empty">
                                                <div class="empty-icon text-muted"><i class="ti ti-broadcast-off fs-1"></i></div>
                                                <p class="empty-title">Belum ada rekaman presensi yang masuk</p>
                                                <p class="empty-subtitle text-secondary">
                                                    Catatan presensi dari semua jalur (Mesin, GPS, Telegram) akan langsung muncul secara otomatis di sini secara real-time.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($logs->hasPages())
                        <div class="card-footer d-flex align-items-center justify-content-between py-2">
                            <p class="m-0 text-secondary small">
                                Menampilkan {{ $logs->firstItem() }} s/d {{ $logs->lastItem() }} dari {{ $logs->total() }} entri
                            </p>
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: PANDUAN & DAFTAR ID MASTER GURU UNTUK MESIN -->
<div class="modal fade" id="modal-teacher-ids" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="ti ti-id me-2"></i> Panduan & Daftar ID Mesin Guru ({{ $allTeachersMaster->count() }} Guru & Staf)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="ti ti-info-circle fs-2 mt-1"></i>
                        <div class="small">
                            <strong>Cara Mendaftarkan Wajah/Jari Guru di Mesin:</strong>
                            Tekan <strong>[Menu]</strong> pada mesin &rarr; <strong>Pengguna</strong> &rarr; <strong>Tambah Pengguna</strong> &rarr; Masukkan <strong>ID Mesin (PIN)</strong> sesuai angka di bawah &rarr; Daftarkan Wajah/Jari &rarr; Simpan.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <input type="text" id="modalFilterTeacherInput" class="form-control form-control-sm w-50" placeholder="Ketik nama guru atau ID..." onkeyup="filterModalTeacherTable()">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                        <i class="ti ti-printer me-1"></i> Cetak Lembar Panduan
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover" id="modalTeacherTable">
                        <thead>
                            <tr class="table-light">
                                <th class="w-1 text-center">No</th>
                                <th class="text-center" style="width: 120px;">ID Mesin (PIN)</th>
                                <th>Nama Lengkap Guru</th>
                                <th>NIGS</th>
                                <th>Jabatan</th>
                                <th class="text-center">Status Rekam</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allTeachersMaster as $idx => $t)
                                <tr>
                                    <td class="text-center text-secondary">{{ $idx + 1 }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-3 px-3 font-monospace fw-bold text-white">
                                            {{ $t->nigs ?: $t->id }}
                                        </span>
                                    </td>
                                    <td><strong>{{ $t->name }}</strong></td>
                                    <td><code>{{ $t->nigs ?: '-' }}</code></td>
                                    <td><span class="badge bg-secondary-lt">{{ $t->position ?: 'Guru' }}</span></td>
                                    <td class="text-center">
                                        @if($t->is_enrolled)
                                            <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Terdaftar</span>
                                        @else
                                            <span class="badge bg-warning-lt"><i class="ti ti-clock me-1"></i> Belum Rekam</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: REAL-TIME AUTO-POLLING SETIAP 3.5 DETIK (TANPA RELOAD BROWSER) -->
<script>
let lastLogId = {{ $latestLogId }};
let targetDate = "{{ $date }}";
let pollInterval = 3500; // 3.5 seconds

function pollNewAttendanceLogs() {
    let url = "{{ route('attendance.api.latest') }}?after_id=" + lastLogId + "&date=" + encodeURIComponent(targetDate);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.new_logs && data.new_logs.length > 0) {
                // Update latest ID
                lastLogId = data.latest_id;

                // Play soft notification sound (Web Audio API beep)
                playNotificationBeep();

                // Show Toast Notification
                let toast = document.getElementById('liveScanToast');
                let toastText = document.getElementById('liveScanToastText');
                if (toast && toastText) {
                    let first = data.new_logs[0];
                    toastText.innerHTML = `<strong>${first.name}</strong> baru saja melakukan scan presensi (${first.status_label}) pukul ${first.scanned_time} via ${first.verify_type_label}!`;
                    toast.classList.remove('d-none');
                    setTimeout(() => toast.classList.add('d-none'), 8000);
                }

                // Prepend to Raw Logs Table
                let tbody = document.getElementById('rawLogsTableBody');
                let emptyRow = document.getElementById('emptyRawLogRow');
                if (emptyRow) emptyRow.remove();

                data.new_logs.forEach(log => {
                    if (!document.getElementById('log-row-' + log.id)) {
                        let rowHtml = `
                            <tr id="log-row-${log.id}" class="table-success animate__animated animate__fadeIn">
                                <td class="text-secondary"><span class="badge bg-green text-white">BARU</span></td>
                                <td class="fw-bold font-monospace text-primary fs-3">
                                    <i class="ti ti-clock me-1"></i>${log.scanned_time}
                                    <span class="text-secondary small fw-normal d-block">${log.scanned_date}</span>
                                </td>
                                <td>
                                    <span class="badge ${log.channel_class || 'bg-secondary-lt'} fw-bold">
                                        <i class="${log.channel_icon || 'ti ti-check'} me-1"></i>${log.channel_label || 'Mesin'}
                                    </span>
                                </td>
                                <td><span class="badge bg-teal-lt">${log.user_type_label}</span></td>
                                <td><code class="fw-bold fs-3 text-dark">${log.pin}</code></td>
                                <td><strong class="text-dark fs-3">${log.name}</strong></td>
                                <td>
                                    <span class="badge bg-blue-lt text-primary fw-bold">
                                        <i class="ti ti-scan me-1"></i>Scan Terverifikasi
                                    </span>
                                    <div class="text-secondary small" style="font-size: 11px;">
                                        <i class="ti ti-arrow-right me-1"></i>Status: ${log.status_label}
                                    </div>
                                </td>
                                <td><span class="small"><i class="${log.verify_icon || 'ti ti-fingerprint'} text-primary fs-2 me-1"></i>${log.verify_type_label}</span></td>
                                <td class="text-secondary small">${log.device_name}</td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('afterbegin', rowHtml);
                    }
                });

                // Update Counters
                if (data.stats) {
                    let stTotal = document.getElementById('statTotalScans');
                    let rawTotal = document.getElementById('rawLogTotalBadge');
                    let stMachine = document.getElementById('statMachineScans');
                    let stGps = document.getElementById('statGpsScans');
                    let stTelegram = document.getElementById('statTelegramScans');
                    if (stTotal) stTotal.innerText = data.stats.total_scans;
                    if (rawTotal) rawTotal.innerText = data.stats.total_scans + ' entri';
                    if (stMachine && data.stats.machine_scans !== undefined) stMachine.innerText = data.stats.machine_scans;
                    if (stGps && data.stats.gps_scans !== undefined) stGps.innerText = data.stats.gps_scans;
                    if (stTelegram && data.stats.telegram_scans !== undefined) stTelegram.innerText = data.stats.telegram_scans;
                }
            }

            // Update Device Status Badge
            if (data.device_status) {
                let devBadge = document.getElementById('deviceStatusBadge');
                let devLast = document.getElementById('deviceLastAct');
                if (devBadge) {
                    devBadge.className = 'badge ' + (data.device_status === 'online' ? 'bg-success' : 'bg-secondary');
                    devBadge.innerText = data.device_status.toUpperCase();
                }
                if (devLast && data.device_last_activity) {
                    devLast.innerText = 'Aktivitas: ' + data.device_last_activity;
                }
            }
        })
        .catch(err => {
            console.log('Live sync polling error:', err);
        });
}

// Generate a gentle notification chime using Web Audio API
function playNotificationBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.setValueAtTime(880.00, audioCtx.currentTime + 0.1); // A5
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
    } catch (e) {}
}

// Start polling
setInterval(pollNewAttendanceLogs, pollInterval);

function filterModalTeacherTable() {
    let input = document.getElementById("modalFilterTeacherInput");
    let filter = input.value.toLowerCase();
    let table = document.getElementById("modalTeacherTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

function toggleStudentFilter(val) {
    let wrap = document.getElementById('studentFilterWrapper');
    if (wrap) {
        wrap.style.display = (val === 'students' || val === 'both') ? 'block' : 'none';
    }
}
</script>
@endsection
