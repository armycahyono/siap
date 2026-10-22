@extends('layouts.app')

@section('title', 'Dashboard Piket')

@section('content')
<div class="container-xl">
    <!-- Page Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Modul Operasional & Kedisiplinan
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Kembali ke Beranda">
                        <i class="ti ti-arrow-left"></i>
                    </a>
                    <h2 class="page-title text-dark fw-bold mb-0">
                        <i class="ti ti-shield-check text-primary me-1 fs-1"></i>
                        Dashboard Guru Piket
                    </h2>
                </div>
                <div class="text-secondary mt-1">
                    <span class="badge bg-blue-lt me-1">{{ $todayDay }}, {{ $today->translatedFormat('d F Y') }}</span>
                    <span class="badge bg-purple-lt me-1">TP {{ $academicYear }} ({{ $semester }})</span>
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
                @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.journals.today') }}" class="btn btn-sm btn-primary d-flex align-items-center shadow-sm">
                    <i class="ti ti-notebook me-2 fs-3"></i>
                    {{ $todayJournal ? 'Buka Jurnal Hari Ini' : 'Mulai Jurnal Hari Ini' }}
                </a>
                @endif
                @if(auth()->user()->canViewPiketReports())
                <a href="{{ route('piket.reports.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-file-text me-1"></i> Rekap & Laporan
                </a>
                @endif
            </div>
        </div>
    </div>

    @if(auth()->user()->canOperatePiket() || auth()->user()->canManagePiketCases())
    <!-- Quick Action Bar -->
    <div class="card bg-primary-lt border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="fw-bold text-dark fs-4 d-flex align-items-center">
                        <i class="ti ti-bolt text-warning me-2 fs-2"></i> Aksi Cepat Meja Piket:
                    </div>
                </div>
                <div class="col-md-9 d-flex flex-wrap gap-2 justify-content-md-end">
                    @if(auth()->user()->canOperatePiket())
                    <a href="{{ route('piket.permits.create', ['type' => 'terlambat']) }}" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm">
                        <i class="ti ti-clock-exclamation me-1 fs-3"></i> + Siswa Terlambat
                    </a>
                    <a href="{{ route('piket.permits.create', ['type' => 'izin_keluar_sementara']) }}" class="btn btn-sm btn-white shadow-sm">
                        <i class="ti ti-door-exit me-1 text-primary"></i> + Izin Keluar/Pulang
                    </a>
                    <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-white shadow-sm">
                        <i class="ti ti-user-x me-1 text-danger"></i> + Guru Kosong / Inval
                    </a>
                    <a href="{{ route('piket.guests.index') }}" class="btn btn-sm btn-white shadow-sm">
                        <i class="ti ti-user-check me-1 text-success"></i> + Buku Tamu
                    </a>
                    @endif
                    @if(auth()->user()->canManagePiketCases())
                    <a href="{{ route('piket.incidents.create') }}" class="btn btn-sm btn-danger shadow-sm">
                        <i class="ti ti-alert-triangle me-1"></i> + Catat Kasus
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Statistik Hari Ini (Cards) -->
    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-warning text-dark">
                                <i class="ti ti-clock-exclamation fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Terlambat Hari Ini</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $todayLateCount }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-primary text-white">
                                <i class="ti ti-door-exit fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Izin Keluar / Pulang</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $todayPermitLeaveCount }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-info text-white">
                                <i class="ti ti-user-x fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Kelas Inval / Kosong</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $todayInvalCount }} <span class="fs-4 text-muted font-weight-normal">kelas</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm shadow-sm border-0 border-start border-success border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-success text-white">
                                <i class="ti ti-users-group fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Tamu Berkunjung</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $todayGuestCount }} <span class="fs-4 text-muted font-weight-normal">orang</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- JADWAL PELAJARAN & GURU MAPEL DI MASING-MASING KELAS JAM SEKARANG -->
    <!-- ========================================================================= -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col">
                    <div class="d-flex align-items-center">
                        <span class="avatar avatar-md rounded bg-primary-lt text-primary me-3">
                            <i class="ti ti-clock-play fs-1"></i>
                        </span>
                        <div>
                            <h3 class="card-title fw-bold text-dark mb-1">
                                Jadwal Pelajaran & Guru Mapel Jam Sekarang
                            </h3>
                            <div class="text-secondary small d-flex flex-wrap align-items-center gap-2">
                                <span>
                                    <i class="ti ti-calendar-event me-1 text-primary"></i> <b>{{ $selectedDay }}</b>, Jam ke-<b>{{ $selectedPeriod }}</b> ({{ $activePeriodTimes['start'] }} - {{ $activePeriodTimes['end'] }} WIB)
                                </span>
                                <span>&bull;</span>
                                <span class="badge bg-blue-lt">
                                    <i class="ti ti-clock me-1"></i> Jam Sistem: {{ $currentTime }} WIB
                                </span>
                                @if(!$isWeekend && $isOutsideKbm)
                                    <span class="badge bg-secondary text-white fw-bold">
                                        <i class="ti ti-moon me-1"></i> {{ $breakMessage ?: 'Di Luar Jam KBM Efektif' }}
                                    </span>
                                @elseif(!$isWeekend && $isBreak)
                                    <span class="badge bg-warning-lt fw-bold">
                                        <i class="ti ti-cup me-1"></i> {{ $breakMessage }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-auto d-flex flex-wrap align-items-center gap-2 mt-2 mt-md-0">
                    <!-- Period Selector Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary btn-sm dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">
                            <i class="ti ti-hourglass-empty me-1"></i> Jam ke-{{ $selectedPeriod }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="dropdown-header">Pilih Jam Pelajaran:</li>
                            @foreach($periodTimes as $p => $times)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center {{ $selectedPeriod == $p ? 'active fw-bold' : '' }}" 
                                       href="{{ route('piket.dashboard', ['period' => $p]) }}">
                                        <span>Jam {{ $p }} ({{ $times['start'] }} - {{ $times['end'] }})</span>
                                        @if(!$isWeekend && $detectedPeriod == $p)
                                            <span class="badge bg-success text-white ms-2" style="font-size: 0.65rem;">Sekarang</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if($selectedPeriod != $detectedPeriod)
                        <a href="{{ route('piket.dashboard') }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke jam sekarang">
                            <i class="ti ti-rotate-2 me-1"></i> Jam Sekarang
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if($isWeekend)
            <div class="alert alert-warning d-flex align-items-center m-3 mb-0" role="alert">
                <i class="ti ti-calendar-off fs-1 me-3 text-warning"></i>
                <div>
                    <div class="fw-bold">Hari ini {{ $todayDay }} — Libur Akhir Pekan (Tidak Ada KBM Reguler)</div>
                    <div class="text-secondary small">Seluruh kelas bebas dari kegiatan belajar mengajar reguler. KBM sekolah aktif setiap hari Senin s/d Jumat.</div>
                </div>
            </div>
        @endif

        <!-- Mini Status Counters Bar -->
        <div class="card-body py-2 px-3 bg-light border-bottom border-top">
            <div class="row text-center g-2">
                <div class="col-6 col-md-3">
                    <span class="text-muted small">Sedang KBM Aktif:</span>
                    <span class="badge bg-success text-white ms-1 fw-bold">{{ $countTeaching }} Kelas</span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted small">Guru Berhalangan:</span>
                    <span class="badge bg-danger text-white ms-1 fw-bold">{{ $countInvalNeeded }} Butuh Inval</span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted small">Telah Diinval:</span>
                    <span class="badge bg-info text-white ms-1 fw-bold">{{ $countInvalHandled }} Kelas</span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted small">Jam Kosong / Bebas:</span>
                    <span class="badge bg-secondary text-white ms-1">{{ $countFree }} Kelas</span>
                </div>
            </div>
        </div>

        <!-- Grid Cards Tiap Kelas -->
        <div class="card-body p-3">
            <div class="row g-3">
                @foreach($classroomSchedules as $cs)
                    @php
                        $c = $cs['classroom'];
                        $status = $cs['status'];
                        $sched = $cs['schedule'];
                        $inval = $cs['inval'];
                    @endphp
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <div class="card h-100 shadow-sm border-0 border-top border-3 {{ $status === 'inval_needed' ? 'border-danger bg-red-lt' : ($status === 'inval_handled' ? 'border-info' : ($status === 'teaching' ? 'border-primary' : 'border-secondary')) }}">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <!-- Baris Atas: Nama Kelas & Badge Status -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-bold text-dark fs-3">{{ $c->name }}</span>
                                    @php
                                        $attendanceUrl = null;
                                        if ($sched) {
                                            $params = [
                                                'schedule_id' => $sched->id,
                                                'date' => $today->toDateString(),
                                            ];
                                            if ($status === 'inval_handled' || $status === 'inval_needed') {
                                                $params['is_swap'] = 1;
                                                $params['swap_type'] = 'inval';
                                            }
                                            $attendanceUrl = route('attendance.subject.take', $params);
                                        } elseif (!empty($cs['attended_session'])) {
                                            $attendanceUrl = route('attendance.subject.take', [
                                                'classroom_id' => $c->id,
                                                'subject_id' => $cs['attended_session']->subject_id,
                                                'start_period' => $cs['attended_session']->start_period,
                                                'end_period' => $cs['attended_session']->end_period,
                                                'date' => $today->toDateString(),
                                            ]);
                                        }
                                    @endphp
                                    @if($status === 'inval_needed')
                                        @if($attendanceUrl)
                                            <a href="{{ $attendanceUrl }}" class="badge bg-danger text-white text-decoration-none">Butuh Inval!</a>
                                        @else
                                            <span class="badge bg-danger text-white">Butuh Inval!</span>
                                        @endif
                                    @elseif($status === 'inval_handled')
                                        <div class="d-flex align-items-center gap-1">
                                            @if(!empty($cs['is_attended']))
                                                @if($attendanceUrl)
                                                    <a href="{{ $attendanceUrl }}" class="badge bg-success-lt text-success fw-bold text-decoration-none" style="font-size: 0.68rem;">
                                                        <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                                    </a>
                                                @else
                                                    <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.68rem;">
                                                        <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                                    </span>
                                                @endif
                                            @else
                                                @if($attendanceUrl)
                                                    <a href="{{ $attendanceUrl }}" class="badge bg-danger-lt text-danger fw-bold text-decoration-none" style="font-size: 0.68rem;">
                                                        <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                                    </a>
                                                @else
                                                    <span class="badge bg-danger-lt text-danger fw-bold" style="font-size: 0.68rem;">
                                                        <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                                    </span>
                                                @endif
                                            @endif
                                            @if($attendanceUrl)
                                                <a href="{{ $attendanceUrl }}" class="badge bg-info text-white text-decoration-none">Diinval</a>
                                            @else
                                                <span class="badge bg-info text-white">Diinval</span>
                                            @endif
                                        </div>
                                    @elseif($status === 'teaching')
                                        <div class="d-flex align-items-center gap-1">
                                            @if(!empty($cs['is_attended']))
                                                @if($attendanceUrl)
                                                    <a href="{{ $attendanceUrl }}" class="badge bg-success-lt text-success fw-bold text-decoration-none" style="font-size: 0.68rem;">
                                                        <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                                    </a>
                                                @else
                                                    <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.68rem;">
                                                        <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                                    </span>
                                                @endif
                                            @else
                                                @if($attendanceUrl)
                                                    <a href="{{ $attendanceUrl }}" class="badge bg-danger-lt text-danger fw-bold text-decoration-none" style="font-size: 0.68rem;">
                                                        <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                                    </a>
                                                @else
                                                    <span class="badge bg-danger-lt text-danger fw-bold" style="font-size: 0.68rem;">
                                                        <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                                    </span>
                                                @endif
                                            @endif
                                            @if($attendanceUrl)
                                                <a href="{{ $attendanceUrl }}" class="badge bg-primary-lt text-decoration-none">Jam {{ $sched->start_period }}-{{ $sched->end_period }}</a>
                                            @else
                                                <span class="badge bg-primary-lt">Jam {{ $sched->start_period }}-{{ $sched->end_period }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge bg-light text-muted border">{{ $isWeekend ? 'Libur' : 'Kosong' }}</span>
                                    @endif
                                </div>

                                <!-- Konten Tengah: Mata Pelajaran & Guru -->
                                <div class="my-2">
                                    @if($status === 'inval_needed')
                                        <div class="fw-bold text-danger mb-1 fs-4">
                                            <i class="ti ti-alert-triangle me-1"></i> {{ $inval->subject?->name ?? ($sched?->subject?->name ?? 'Mata Pelajaran') }}
                                        </div>
                                        <div class="small text-danger mb-2">
                                            Guru Absen: <b>{{ $inval->absentTeacher?->name ?? ($sched?->teacher?->name ?? '-') }}</b>
                                            @if($inval->reason)
                                                <div class="text-muted" style="font-size: 0.72rem;">Ket: {{ $inval->reason }}</div>
                                            @endif
                                        </div>
                                        <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-danger w-100 shadow-sm">
                                            <i class="ti ti-user-plus me-1"></i> Tentukan Guru Inval
                                        </a>

                                    @elseif($status === 'inval_handled')
                                        <div class="fw-bold text-dark mb-1 fs-4">
                                            {{ $inval->subject?->name ?? ($sched?->subject?->name ?? '-') }}
                                        </div>
                                        <div class="small text-muted mb-1">
                                            Guru Asli: <span class="text-decoration-line-through">{{ $inval->absentTeacher?->name ?? '-' }}</span>
                                        </div>
                                        <div class="small p-1 px-2 rounded bg-success-lt border border-success">
                                            <i class="ti ti-user-check text-success me-1"></i> Inval: <b>{{ $inval->invalTeacher?->name ?? '-' }}</b>
                                        </div>

                                    @elseif($status === 'teaching')
                                        <div class="fw-bold text-dark mb-1 fs-4 text-truncate" title="{{ $sched->subject?->name }}">
                                            <i class="ti ti-book me-1 text-primary"></i> {{ $sched->subject?->name ?? '-' }}
                                        </div>
                                        <div class="small text-dark fw-bold text-truncate" title="{{ $sched->teacher?->name }}">
                                            <i class="ti ti-user me-1 text-secondary"></i> {{ $sched->teacher?->name ?? '-' }}
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                            <i class="ti ti-clock me-1"></i> {{ $sched->start_time ?: $activePeriodTimes['start'] }} - {{ $sched->end_time ?: $activePeriodTimes['end'] }} WIB
                                        </div>

                                    @else
                                        <div class="text-center py-3 text-muted">
                                            <i class="ti {{ $isWeekend ? 'ti-calendar-off' : 'ti-coffee' }} fs-1 d-block mb-1 text-secondary opacity-50"></i>
                                            <span class="small">{{ $isWeekend ? 'Libur Akhir Pekan' : 'Tidak ada KBM terjadwal' }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Baris Bawah: Wali Kelas -->
                                <div class="pt-2 border-top mt-auto d-flex align-items-center justify-content-between text-muted" style="font-size: 0.75rem;">
                                    <span title="Wali Kelas">
                                        <i class="ti ti-user-star me-1 text-success"></i> {{ $c->homeroomTeacher?->name ?? 'Belum ada wali' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Main Grid Content -->
    <div class="row g-4">
        <!-- Kolom Kiri: Petugas Hari Ini & Perizinan Terbaru -->
        <div class="col-lg-7">
            <!-- Card Petugas Piket Hari Ini -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-user-check text-primary me-2"></i>
                        Petugas Piket Hari Ini ({{ $todayDay }})
                    </h3>
                    <a href="{{ route('piket.schedules.index') }}" class="btn btn-sm btn-outline-secondary">
                        Lihat Jadwal Lengkap
                    </a>
                </div>
                <!-- Mobile View (d-md-none) -->
                <div class="d-md-none p-2">
                    @forelse($todaySchedules as $sched)
                        @php
                            $att = $todayJournal?->attendances->firstWhere('teacher_id', $sched->teacher_id);
                        @endphp
                        <div class="card border shadow-none mb-2 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-sm rounded-circle {{ $sched->role === 'koordinator' ? 'bg-purple text-white' : 'bg-blue-lt text-primary' }} me-2 fw-bold">
                                            {{ substr($sched->teacher?->name ?? 'G', 0, 1) }}
                                        </span>
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <span>{{ $sched->teacher?->name ?? '-' }}</span>
                                                @if($sched->teacher?->phone)
                                                    @php
                                                        $dashWaGreeting = urlencode("Halo Bapak/Ibu " . ($sched->teacher?->short_name ?: $sched->teacher?->name) . ", saya ingin berkoordinasi terkait tugas piket hari ini (" . $todayDay . ") di SMK PGRI Kras.");
                                                    @endphp
                                                    <a href="{{ $sched->teacher->whatsapp_url }}?text={{ $dashWaGreeting }}" target="_blank" class="text-success ms-1" title="Chat WhatsApp">
                                                        <i class="ti ti-brand-whatsapp fs-3"></i>
                                                    </a>
                                                @endif
                                            </div>
                                            <div class="text-muted small">{{ $sched->teacher?->formatted_id ?? '-' }}</div>
                                        </div>
                                    </div>
                                    @if($sched->role === 'koordinator')
                                        <span class="badge bg-purple text-white"><i class="ti ti-crown me-1"></i> Koordinator</span>
                                    @else
                                        <span class="badge bg-secondary-lt">Anggota</span>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                                    <span class="text-muted"><i class="ti ti-map-pin me-1"></i>{{ $sched->duty_location ?: 'Meja Piket Utama' }}</span>
                                    <div>
                                        @if($att)
                                            @if($att->status === 'hadir')
                                                <span class="badge bg-success-lt fw-bold"><i class="ti ti-check me-1"></i> Hadir ({{ substr($att->check_in_time ?? '', 0, 5) ?: '-' }})</span>
                                            @elseif($att->status === 'izin')
                                                <span class="badge bg-warning-lt fw-bold">Izin</span>
                                            @elseif($att->status === 'sakit')
                                                <span class="badge bg-info-lt fw-bold">Sakit</span>
                                            @elseif($att->status === 'belum_hadir')
                                                <span class="badge bg-secondary-lt fw-bold text-secondary">Belum Hadir</span>
                                            @else
                                                <span class="badge bg-danger-lt fw-bold">Alpa</span>
                                            @endif
                                        @else
                                            <span class="badge bg-light text-muted">Belum Dicatat</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-calendar-off fs-1 d-block mb-1"></i>
                            Belum ada jadwal petugas piket hari ini.
                        </div>
                    @endforelse
                </div>

                <!-- Desktop Table View (d-none d-md-block) -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter table-hover card-table">
                        <thead class="table-light">
                            <tr>
                                <th>Petugas Piket</th>
                                <th>Peran</th>
                                <th>Pos Tugas</th>
                                <th class="text-center">Presensi Hari Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todaySchedules as $sched)
                                @php
                                    $att = $todayJournal?->attendances->firstWhere('teacher_id', $sched->teacher_id);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm rounded-circle {{ $sched->role === 'koordinator' ? 'bg-purple text-white shadow-sm' : 'bg-blue-lt text-primary' }} me-2 fw-bold"
                                                  @if($sched->role === 'koordinator') title="Koordinator Piket" data-bs-toggle="tooltip" @endif>
                                                {{ substr($sched->teacher?->name ?? 'G', 0, 1) }}
                                            </span>
                                            <div>
                                                <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                    <span>{{ $sched->teacher?->name ?? '-' }}</span>
                                                    @if($sched->teacher?->phone)
                                                        @php
                                                            $dashWaGreeting = urlencode("Halo Bapak/Ibu " . ($sched->teacher?->short_name ?: $sched->teacher?->name) . ", saya ingin berkoordinasi terkait tugas piket hari ini (" . $todayDay . ") di SMK PGRI Kras.");
                                                        @endphp
                                                        <a href="{{ $sched->teacher->whatsapp_url }}?text={{ $dashWaGreeting }}" target="_blank" class="text-success ms-1" title="Chat WhatsApp ({{ $sched->teacher->phone }})" data-bs-toggle="tooltip">
                                                            <i class="ti ti-brand-whatsapp fs-3"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="text-muted small">{{ $sched->teacher?->formatted_id ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($sched->role === 'koordinator')
                                            <span class="badge bg-purple text-white"><i class="ti ti-crown me-1"></i> Koordinator</span>
                                        @else
                                            <span class="badge bg-secondary-lt">Anggota</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-secondary small">{{ $sched->duty_location ?: 'Meja Piket Utama' }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($att)
                                            @if($att->status === 'hadir')
                                                <span class="badge bg-success-lt fw-bold"><i class="ti ti-check me-1"></i> Hadir ({{ substr($att->check_in_time ?? '', 0, 5) ?: '-' }})</span>
                                            @elseif($att->status === 'izin')
                                                <span class="badge bg-warning-lt fw-bold">Izin</span>
                                            @elseif($att->status === 'sakit')
                                                <span class="badge bg-info-lt fw-bold">Sakit</span>
                                            @elseif($att->status === 'belum_hadir')
                                                <span class="badge bg-secondary-lt fw-bold text-secondary">Belum Hadir</span>
                                            @else
                                                <span class="badge bg-danger-lt fw-bold">Alpa</span>
                                            @endif
                                        @else
                                            <span class="badge bg-light text-muted">Belum Dicatat</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="ti ti-calendar-off fs-1 d-block mb-1"></i>
                                        Belum ada jadwal petugas piket yang ditetapkan untuk hari {{ $todayDay }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Perizinan Siswa Terbaru -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-history text-warning me-2"></i>
                        Aktivitas Perizinan Siswa Hari Ini
                    </h3>
                    <a href="{{ route('piket.permits.index') }}" class="btn btn-sm btn-outline-secondary">
                        Lihat Semua Perizinan
                    </a>
                </div>
                <!-- Mobile View (d-md-none) -->
                <div class="d-md-none p-2">
                    @forelse($recentPermits as $permit)
                        <div class="card border shadow-none mb-2 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-bold text-dark fs-4 mb-0">{{ $permit->student_name }}</div>
                                        <span class="badge bg-blue-lt">{{ $permit->classroom?->name ?? '-' }}</span>
                                    </div>
                                    <span class="badge {{ $permit->permit_type_badge_class }}">{{ $permit->permit_type_label }}</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <i class="ti ti-clock text-primary"></i>
                                        <span>{{ substr($permit->entry_time ?: $permit->departure_time ?: '', 0, 5) }} WIB &bull; No: <code>{{ $permit->permit_number }}</code></span>
                                    </div>
                                    <div class="text-secondary bg-light p-2 rounded">
                                        <strong>Alasan:</strong> {{ $permit->reason }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 pt-2 border-top">
                                    <a href="{{ route('piket.permits.slip', $permit) }}" target="_blank" class="btn btn-sm btn-outline-primary flex-fill">
                                        <i class="ti ti-printer me-1"></i> Cetak Slip
                                    </a>
                                    <a href="{{ route('piket.permits.show', $permit) }}" class="btn btn-sm btn-ghost-secondary">
                                        <i class="ti ti-eye"></i> Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-mood-smile fs-1 d-block mb-1 text-success"></i>
                            Belum ada perizinan siswa yang dicatat hari ini. Kondisi tertib!
                        </div>
                    @endforelse
                </div>

                <!-- Desktop Table View (d-none d-md-block) -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter table-hover card-table">
                        <thead class="table-light">
                            <tr>
                                <th>No. Izin & Jam</th>
                                <th>Nama Siswa & Kelas</th>
                                <th>Jenis & Alasan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPermits as $permit)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $permit->permit_number }}</div>
                                        <div class="text-muted small">
                                            <i class="ti ti-clock me-1"></i>{{ substr($permit->entry_time ?: $permit->departure_time ?: '', 0, 5) }} WIB
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $permit->student_name }}</div>
                                        <span class="badge bg-blue-lt">{{ $permit->classroom?->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <div><span class="badge {{ $permit->permit_type_badge_class }}">{{ $permit->permit_type_label }}</span></div>
                                        <div class="text-muted small text-truncate" style="max-width: 200px;">{{ $permit->reason }}</div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('piket.permits.slip', $permit) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Cetak Slip Izin">
                                            <i class="ti ti-printer"></i> Slip
                                        </a>
                                        <a href="{{ route('piket.permits.show', $permit) }}" class="btn btn-sm btn-ghost-secondary">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="ti ti-mood-smile fs-1 d-block mb-1 text-success"></i>
                                        Belum ada perizinan siswa yang dicatat hari ini. Kondisi tertib!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Kelas Inval & Buku Tamu & Kasus -->
        <div class="col-lg-5">
            <!-- Card Kelas Inval / Pengganti -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-chalkboard text-info me-2"></i>
                        Guru Kosong & Inval Hari Ini
                    </h3>
                    <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-outline-info">
                        Kelola Inval
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($todayInvals as $inval)
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span class="badge bg-red-lt fs-4 px-2 py-1">{{ $inval->classroom?->name ?? '-' }}</span>
                                    </div>
                                    <div class="col">
                                        <div class="fw-bold text-dark">
                                            <span class="text-danger"><del>{{ $inval->absentTeacher?->formatted_short_name ?? '-' }}</del></span>
                                            <span class="text-muted">({{ $inval->reason }})</span>
                                        </div>
                                        <div class="text-secondary small">
                                            Jam ke-{{ $inval->period_start }} s.d {{ $inval->period_end }} &bull; {{ $inval->subject?->name ?? 'Mapel' }}
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge bg-green-lt">
                                                <i class="ti ti-arrow-right me-1"></i> Inval: {{ $inval->invalTeacher?->name ?? 'Belum Ditentukan' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ti ti-school fs-1 d-block mb-1 text-success"></i>
                                Semua guru hadir. Tidak ada kelas kosong hari ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Card Buku Tamu Hari Ini -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-address-book text-success me-2"></i>
                        Buku Tamu Hari Ini
                    </h3>
                    <a href="{{ route('piket.guests.index') }}" class="btn btn-sm btn-outline-success">
                        Semua Tamu
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($recentGuests as $guest)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark">{{ $guest->guest_name }}</span>
                                    <span class="badge {{ $guest->check_out_time ? 'bg-secondary-lt' : 'bg-success-lt' }}">
                                        {{ $guest->check_out_time ? 'Selesai' : 'Sedang Berkunjung' }}
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="ti ti-building me-1"></i>{{ $guest->institution ?: 'Umum / Orang Tua' }} &bull;
                                    <i class="ti ti-clock me-1"></i>{{ substr($guest->check_in_time, 0, 5) }} WIB
                                </div>
                                <div class="text-secondary small mt-1">
                                    <strong>Keperluan:</strong> {{ $guest->purpose }} (Menemui: {{ $guest->meet_with }})
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ti ti-users fs-1 d-block mb-1"></i>
                                Belum ada buku tamu yang dicatat hari ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>
@endsection
