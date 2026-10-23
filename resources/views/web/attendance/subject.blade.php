@extends('layouts.app')

@section('title', 'Presensi Mengajar Guru Mapel — SMK PGRI Kras')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $subAttActions = '';
    if (auth()->user()->isHomeroomTeacher() || auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah'])) {
        $subAttActions .= '<a href="' . route('attendance.homeroom-permits.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-heart-handshake me-1 text-info"></i> Izin Siswa Perwalian</a> ';
    }
    $subAttActions .= '<a href="' . route('attendance.subject.history') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-history me-1 text-warning"></i> Riwayat Mengajar</a> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalManualAttendance"><i class="ti ti-edit me-1"></i> Presensi Mandiri</button>';
@endphp
@include('layouts._hero_header', [
    'title' => 'Presensi Mengajar (Guru Mapel)',
    'subtitle' => 'Absensi kehadiran siswa di kelas saat pembelajaran berlangsung, termasuk dukungan jadwal reguler, tukar jam mengajar, dan guru pengganti.',
    'icon' => 'ti ti-chalkboard-teacher',
    'category' => 'PEMBELAJARAN & KBM',
    'actions' => $subAttActions
])

<!-- Pemilih Tanggal & Hari Aktif -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form action="{{ route('attendance.subject.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted fw-bold">
                        <i class="ti ti-calendar me-1"></i> Tanggal
                    </span>
                    <input type="date" name="date" class="form-control" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
                </div>
            </div>
            <div class="col-12 col-md-5">
                <span class="badge bg-blue-lt px-3 py-2 fs-4">
                    <i class="ti ti-calendar-event me-1"></i> Hari {{ $dayName }}, {{ $date->translatedFormat('d F Y') }}
                </span>
                @if($date->isToday())
                    <span class="badge bg-success-lt ms-1 px-2 py-1">Hari Ini</span>
                @endif
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <a href="{{ route('attendance.subject.index', ['date' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-clock-check me-1"></i> Kembali ke Hari Ini
                </a>
            </div>
        </form>
    </div>
</div>

@if($todayEvents->isNotEmpty())
    <div class="mb-3">
        @foreach($todayEvents as $tev)
            <div class="alert alert-important {{ $tev->category_badge }} border-0 shadow-sm d-flex align-items-center justify-content-between py-2 px-3 mb-2 rounded-3">
                <div class="d-flex align-items-center">
                    <i class="ti ti-calendar-star fs-2 me-2"></i>
                    <div>
                        <strong>Agenda Sekolah: {{ $tev->title }}</strong>
                        @if($tev->learning_impact !== 'normal')
                            <span class="badge bg-white text-dark ms-2 fw-bold">
                                {{ $tev->learning_impact_label }}
                                @if($tev->learning_impact === 'pulang_cepat' && ($tev->max_period || $tev->dismissal_time))
                                    ({{ $tev->max_period ? 'Maks Jam ke-' . $tev->max_period : '' }}{{ $tev->dismissal_time ? ' Pulang ' . $tev->dismissal_time . ' WIB' : '' }})
                                @endif
                            </span>
                        @endif
                        @if($tev->teacher_impact && $tev->teacher_impact !== 'normal')
                            <span class="badge bg-white text-dark ms-1 fw-bold">
                                {{ $tev->teacher_impact_label }}
                                @if($tev->gtk_checkin_time || $tev->gtk_checkout_time)
                                    ({{ $tev->gtk_checkin_time ?? '07:00' }} - {{ $tev->gtk_checkout_time ?? 'Selesai' }})
                                @endif
                            </span>
                        @endif
                        @if($tev->target_audience !== 'semua')
                            <span class="badge bg-dark-lt text-white ms-1">Sasaran: {{ ucfirst($tev->target_audience) }}</span>
                        @endif
                        @if($tev->teacher_instructions)
                            <div class="small mt-1 opacity-90"><i class="ti ti-info-circle me-1"></i> {{ $tev->teacher_instructions }}</div>
                        @endif
                    </div>
                </div>
                <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm btn-outline-light text-nowrap ms-2">
                    <i class="ti ti-calendar me-1"></i> Buka Kalender
                </a>
            </div>
        @endforeach
    </div>
@endif

@if(isset($teacherOnDutyEvents) && $teacherOnDutyEvents->isNotEmpty())
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-3 py-2 px-3 rounded-3">
        <i class="ti ti-shield-check fs-2 text-success me-3"></i>
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                Anda Ditugaskan Khusus Hari Ini (Gugur Kewajiban Mengajar Tatap Muka)
            </h5>
            <div class="small text-muted mt-1">
                @foreach($teacherOnDutyEvents as $tDuty)
                    <span class="badge bg-success text-white me-1">{{ $tDuty->title }}</span>
                    <span>Waktu Hadir GTK: <strong>{{ $tDuty->gtk_checkin_time ?? '07:00' }} - {{ $tDuty->gtk_checkout_time ?? 'Selesai' }} WIB</strong>.</span>
                @endforeach
                Jadwal mengajar reguler Anda di bawah ini dibebaskan tanpa mengurangi hak/presensi kehadiran Anda di sekolah.
            </div>
        </div>
    </div>
@endif

<!-- 1. JADWAL RUTIN SAYA HARI INI -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h3 class="card-title fw-bold text-dark mb-0">
            <i class="ti ti-calendar-user me-2 text-primary"></i>
            Jadwal Mengajar Saya (Hari {{ $dayName }})
        </h3>
        <div class="d-flex align-items-center gap-2">
            @if(isset($teacherOnDutyEvents) && $teacherOnDutyEvents->isNotEmpty())
                <span class="badge bg-success-lt fw-bold border border-success">
                    <i class="ti ti-award me-1"></i> Bertugas Khusus / Bebas Mengajar
                </span>
            @endif
            <span class="badge bg-primary text-white">{{ $mySchedules->count() }} Kelas Terjadwal</span>
        </div>
    </div>
    @if($mySchedules->isEmpty())
        @if($isWeekend)
            <div class="card-body py-4 text-center">
                <div class="avatar avatar-lg bg-success-lt text-success rounded-circle mx-auto mb-2">
                    <i class="ti ti-sun fs-1"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Akhir Pekan &bull; Tidak Ada KBM</h4>
                <p class="text-muted small mb-0">
                    Sekolah menerapkan KBM 5 hari kerja (Senin &ndash; Jumat). Hari {{ $dayName }} tidak ada kegiatan belajar mengajar reguler.
                </p>
            </div>
        @elseif($isHoliday)
            <div class="card-body py-4 text-center">
                <div class="avatar avatar-lg bg-danger-lt text-danger rounded-circle mx-auto mb-2">
                    <i class="ti ti-calendar-off fs-1"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Hari Libur Sekolah</h4>
                <p class="text-muted small mb-0">
                    {{ $holidayEvent?->title ?? 'Kegiatan Belajar Diliburkan' }}. Hari {{ $dayName }}, {{ $date->translatedFormat('d F Y') }} tidak ada kegiatan belajar mengajar di kelas.
                </p>
            </div>
        @else
            <div class="card-body py-4 text-center">
                <div class="avatar avatar-lg bg-blue-lt text-primary rounded-circle mx-auto mb-2">
                    <i class="ti ti-calendar-off fs-1"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Tidak Ada Jadwal Mengajar Rutin di Hari {{ $dayName }}</h4>
                <p class="text-muted small mb-0">
                    Anda tidak memiliki jadwal reguler di hari ini. Jika Anda mengisi jam guru lain (tukar jam / guru pengganti), silakan pilih dari daftar tukar jam di bawah.
                </p>
            </div>
        @endif
    @else
        <!-- Mobile Card View (Khusus Layar HP) -->
        <div class="d-md-none p-2 p-sm-3">
            @foreach($mySchedules as $sched)
                @php
                    $session = $savedSessions->get($sched->id);
                    $eventImpact = $sched->getEventImpact($todayEvents ?? null);
                    $cardWindow = $sched->getAttendanceWindowStatus($date, 15);
                    $isCardTooEarly = ! $session && ! $cardWindow['is_open'];
                @endphp
                <div class="card card-stacked shadow-sm border rounded-3 mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-blue-lt fw-bold px-2 py-1">
                                Jam {{ $sched->start_period }}-{{ $sched->end_period }} ({{ $sched->start_time ?? '-' }}-{{ $sched->end_time ?? '-' }})
                            </span>
                            @if($session)
                                <span class="badge bg-success text-white">
                                    <i class="ti ti-check me-1"></i> Sudah Diabsen
                                </span>
                            @elseif($eventImpact)
                                <span class="badge {{ $eventImpact['badge_class'] }}">
                                    <i class="ti {{ $eventImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($eventImpact['type'] === 'daring' ? 'ti-device-laptop' : ($eventImpact['type'] === 'libur' ? 'ti-calendar-off' : 'ti-home-check')) }} me-1"></i> {{ $eventImpact['badge_text'] }}
                                </span>
                            @elseif($isCardTooEarly)
                                <span class="badge bg-secondary-lt">
                                    <i class="ti ti-clock me-1"></i> Buka Pukul {{ $cardWindow['open_time'] ?? '-' }}
                                </span>
                            @else
                                <span class="badge bg-secondary-lt">
                                    <i class="ti ti-clock me-1"></i> Belum Diabsen
                                </span>
                            @endif
                        </div>

                        <div class="mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="mb-0 fw-bold text-dark fs-2">
                                    <i class="ti ti-door me-1 text-primary"></i> {{ $sched->classroom->name }}
                                </h3>
                                @if(isset($teacherOnDutyEvents) && $teacherOnDutyEvents->isNotEmpty())
                                    <span class="badge bg-success-lt border border-success" style="font-size: 0.68rem;">Tugas Khusus</span>
                                @endif
                            </div>
                            <div class="text-secondary fw-semibold mt-1">
                                {{ $sched->subject->name }}
                            </div>
                        </div>

                        @if(!$session && $eventImpact)
                            <div class="p-2 rounded-2 mb-3 small {{ $eventImpact['type'] === 'bebas_kbm' ? 'bg-purple-lt border border-purple-subtle text-purple-emphasis' : ($eventImpact['type'] === 'daring' ? 'bg-info-lt border border-info-subtle text-info-emphasis' : 'bg-light text-muted') }}">
                                <div class="fw-bold mb-0.5">
                                    <i class="ti {{ $eventImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($eventImpact['type'] === 'daring' ? 'ti-device-laptop' : 'ti-info-circle') }} me-1"></i>
                                    {{ $eventImpact['title'] }}
                                </div>
                                <div class="text-muted">{{ $eventImpact['note'] }}</div>
                            </div>
                        @endif

                        @if($session)
                            <div class="p-2 bg-light rounded-2 small text-muted mb-3 d-flex justify-content-around text-center">
                                <div>Hadir: <strong class="text-success">{{ $session->hadir_count }}</strong></div>
                                <div>Sakit: <strong class="text-warning">{{ $session->sakit_count }}</strong></div>
                                <div>Izin: <strong class="text-info">{{ $session->izin_count }}</strong></div>
                                <div>Alpa: <strong class="text-danger">{{ $session->alpa_count }}</strong></div>
                                @if($session->dispen_count > 0)
                                    <div>Dispen: <strong class="text-purple">{{ $session->dispen_count }}</strong></div>
                                @endif
                            </div>
                        @endif

                        <div class="d-grid gap-2">
                            @if($session)
                                <div class="d-flex gap-2">
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-warning flex-fill fw-bold">
                                        <i class="ti ti-edit me-1"></i> Edit Absen
                                    </a>
                                    <a href="{{ route('attendance.subject.show', $session) }}" class="btn btn-outline-secondary px-3" title="Detail Lembar Absen">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </div>
                            @elseif($eventImpact && $eventImpact['type'] === 'bebas_kbm')
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-outline-purple fw-bold py-2 shadow-sm">
                                    <i class="ti ti-sparkles me-1 fs-3"></i> Catat Jurnal / Absen Kegiatan
                                </a>
                            @elseif($eventImpact && $eventImpact['type'] === 'daring')
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-info fw-bold py-2 shadow-sm text-white">
                                    <i class="ti ti-device-laptop me-1 fs-3"></i> Masuk &amp; Absen KBM Daring
                                </a>
                            @elseif($isCardTooEarly)
                                <button type="button" class="btn btn-secondary disabled fw-bold py-2 shadow-none" style="opacity: 0.65; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $cardWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                    <i class="ti ti-lock me-1 fs-3"></i> Presensi Buka Pukul {{ $cardWindow['open_time'] }}
                                </button>
                            @else
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-primary fw-bold py-2 shadow-sm">
                                    <i class="ti ti-checklist me-1 fs-3"></i> Masuk &amp; Absen Kelas
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-vcenter card-table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="w-1 text-center">Jam Ke</th>
                        <th>Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th class="text-center">Status Absensi</th>
                        <th class="w-1 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mySchedules as $sched)
                        @php
                            $session = $savedSessions->get($sched->id);
                            // Cek apakah ada event kalender yang berdampak pada rombel ini
                            $schedEvent = $todayEvents->first(fn ($ev) => $ev->appliesToClassroom($sched->classroom));
                        @endphp
                        <tr>
                            <td class="text-center fw-bold text-dark">
                                <span class="badge bg-blue-lt fs-3 px-2 py-1">
                                    {{ $sched->start_period }} - {{ $sched->end_period }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                <i class="ti ti-clock me-1 text-secondary"></i>
                                {{ $sched->start_time ?? '-' }} - {{ $sched->end_time ?? '-' }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    <span class="fw-bold text-dark fs-3">
                                        <i class="ti ti-door me-1 text-cyan"></i> {{ $sched->classroom->name }}
                                    </span>
                                    @if(isset($teacherOnDutyEvents) && $teacherOnDutyEvents->isNotEmpty())
                                        <span class="badge bg-success-lt border border-success" title="Anda ditugaskan khusus (gugur kewajiban mengajar)">
                                            <i class="ti ti-shield-check me-1"></i> Gugur Jam (Tugas Khusus)
                                        </span>
                                    @endif

                                    @if($schedEvent && $schedEvent->learning_impact !== 'normal')
                                        @if($schedEvent->learning_impact === 'bebas_kbm_kegiatan')
                                            <span class="badge bg-purple-lt border border-purple" title="{{ $schedEvent->title }}">
                                                <i class="ti ti-sparkles me-1"></i> {{ $schedEvent->title }} (Bebas KBM)
                                            </span>
                                        @elseif($schedEvent->learning_impact === 'pembelajaran_daring')
                                            <span class="badge bg-info-lt border border-info" title="{{ $schedEvent->title }}">
                                                <i class="ti ti-device-laptop me-1"></i> Pembelajaran Daring
                                            </span>
                                        @elseif($schedEvent->learning_impact === 'pulang_cepat')
                                            @if($schedEvent->max_period && $sched->start_period > $schedEvent->max_period)
                                                <span class="badge bg-warning-lt border border-warning" title="KBM dipulangkan lebih awal jam ke-{{ $schedEvent->max_period }}">
                                                    <i class="ti ti-home-check me-1"></i> Pulang Cepat (Lewat Jam Ke-{{ $schedEvent->max_period }})
                                                </span>
                                            @endif
                                        @elseif($schedEvent->learning_impact === 'libur_khusus')
                                            <span class="badge bg-danger-lt border border-danger">
                                                <i class="ti ti-calendar-off me-1"></i> Diliburkan
                                            </span>
                                        @endif
                                    @elseif(!$schedEvent && $todayEvents->isNotEmpty())
                                        @php
                                            // Cek kebijakan untuk siswa/guru yang TIDAK terlibat
                                            $disEvent = $todayEvents->first(fn($ev) => !$ev->is_holiday && $ev->disengaged_student_impact !== 'kbm_normal');
                                        @endphp
                                        @if($disEvent)
                                            @if($disEvent->disengaged_student_impact === 'pembelajaran_daring')
                                                <span class="badge bg-info-lt border border-info" title="Siswa lain belajar daring karena {{ $disEvent->title }}">
                                                    <i class="ti ti-device-laptop me-1"></i> Pembelajaran Daring
                                                </span>
                                            @elseif($disEvent->disengaged_student_impact === 'pulang_cepat' && $disEvent->disengaged_student_max_period && $sched->start_period > $disEvent->disengaged_student_max_period)
                                                <span class="badge bg-warning-lt border border-warning" title="Siswa lain dipulangkan jam ke-{{ $disEvent->disengaged_student_max_period }} karena {{ $disEvent->title }}">
                                                    <i class="ti ti-home-check me-1"></i> Pulang Cepat (Lewat Jam Ke-{{ $disEvent->disengaged_student_max_period }})
                                                </span>
                                            @elseif($disEvent->disengaged_student_impact === 'bebas_kbm')
                                                <span class="badge bg-purple-lt border border-purple" title="Bebas KBM karena {{ $disEvent->title }}">
                                                    <i class="ti ti-sparkles me-1"></i> Bebas KBM
                                                </span>
                                            @elseif($disEvent->disengaged_student_impact === 'libur_khusus')
                                                <span class="badge bg-danger-lt border border-danger">
                                                    <i class="ti ti-calendar-off me-1"></i> Diliburkan Khusus
                                                </span>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $sched->subject->name }}</div>
                                <div class="text-muted small" style="font-size: 0.75rem;">Kode: {{ $sched->subject->code ?? '-' }}</div>
                            </td>
                            <td class="text-center">
                                @php
                                    $tableImpact = $sched->getEventImpact($todayEvents ?? null);
                                    $tableWindow = $sched->getAttendanceWindowStatus($date, 15);
                                    $isTableTooEarly = ! $session && ! $tableWindow['is_open'];
                                @endphp
                                @if($session)
                                    <span class="badge bg-success-lt px-2 py-1 mb-1">
                                        <i class="ti ti-check me-1"></i> Sudah Diabsen
                                    </span>
                                    <div class="small text-muted" style="font-size: 0.72rem;">
                                        H: <strong class="text-success">{{ $session->hadir_count }}</strong> |
                                        S: <strong class="text-warning">{{ $session->sakit_count }}</strong> |
                                        I: <strong class="text-info">{{ $session->izin_count }}</strong> |
                                        A: <strong class="text-danger">{{ $session->alpa_count }}</strong>
                                        @if($session->dispen_count > 0)
                                            | D: <strong class="text-purple">{{ $session->dispen_count }}</strong>
                                        @endif
                                    </div>
                                @elseif($tableImpact)
                                    <span class="badge {{ $tableImpact['badge_class'] }} px-2 py-1 mb-1">
                                        <i class="ti {{ $tableImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($tableImpact['type'] === 'daring' ? 'ti-device-laptop' : ($tableImpact['type'] === 'libur' ? 'ti-calendar-off' : 'ti-home-check')) }} me-1"></i> {{ $tableImpact['badge_text'] }}
                                    </span>
                                    <div class="small text-muted" style="font-size: 0.72rem;">
                                        {{ \Illuminate\Support\Str::limit($tableImpact['title'], 28) }}
                                    </div>
                                @elseif($isTableTooEarly)
                                    <span class="badge bg-secondary-lt px-2 py-1">
                                        <i class="ti ti-clock me-1"></i> Buka Pukul {{ $tableWindow['open_time'] ?? '-' }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-lt px-2 py-1">
                                        <i class="ti ti-clock me-1"></i> Belum Diabsen
                                    </span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($session)
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-outline-warning">
                                        <i class="ti ti-edit me-1"></i> Edit Absen
                                    </a>
                                    <a href="{{ route('attendance.subject.show', $session) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                @elseif($tableImpact && $tableImpact['type'] === 'bebas_kbm')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-outline-purple">
                                        <i class="ti ti-sparkles me-1"></i> Catat Jurnal
                                    </a>
                                @elseif($tableImpact && $tableImpact['type'] === 'daring')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-info text-white">
                                        <i class="ti ti-device-laptop me-1"></i> Absen Daring
                                    </a>
                                @elseif($isTableTooEarly)
                                    <button type="button" class="btn btn-sm btn-secondary disabled" style="opacity: 0.65; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $tableWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                        <i class="ti ti-lock me-1"></i> Buka {{ $tableWindow['open_time'] }}
                                    </button>
                                @else
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-checklist me-1"></i> Masuk & Absen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<!-- 2. TUKAR JAM MENGAJAR / GURU PENGGANTI HARI INI (Hanya Tampil Pada Hari Ada KBM atau Ada Riwayat Tukar Jam) -->
@if($isKbmDay || $swapSessions->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="card-title fw-bold text-dark mb-0 d-flex flex-wrap align-items-center gap-2">
                <span><i class="ti ti-arrows-left-right me-1 text-warning"></i> Tukar Jam Mengajar / Guru Pengganti (Kelas Kosong)</span>
                <span class="badge bg-warning-lt text-dark border">
                    <i class="ti ti-calendar me-1"></i>{{ $dayName }}, {{ $date->translatedFormat('d F Y') }}
                </span>
            </h3>
            <div class="text-muted small mt-1">
                Gunakan fitur ini jika Anda menggantikan jam guru lain pada tanggal <strong>{{ $dayName }}, {{ $date->translatedFormat('d F Y') }}</strong> (tukar jam pelajaran atau bertindak sebagai guru inval/pengganti).
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" data-bs-toggle="collapse" data-bs-target="#collapseSwapPicker">
            <i class="ti ti-search me-1"></i> Pilih Jadwal Guru Lain ({{ $date->translatedFormat('d M') }})
        </button>
    </div>

    <!-- Collapsible: Cari & Pilih Jadwal Guru Lain yang Digantikan -->
    <div class="collapse" id="collapseSwapPicker">
        <div class="card-body bg-light border-bottom p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h4 class="fw-bold text-dark mb-0">Pilih Jadwal Guru Lain yang Ingin Anda Gantikan:</h4>
                <span class="badge bg-white text-muted border">
                    <i class="ti ti-calendar me-1 text-primary"></i>Jadwal KBM: <strong>{{ $dayName }}, {{ $date->translatedFormat('d F Y') }}</strong>
                </span>
            </div>
            @if($allDaySchedules->isEmpty())
                <p class="text-muted small mb-0">Tidak ada jadwal kelas lain yang tersedia di hari {{ $dayName }}, {{ $date->translatedFormat('d F Y') }}.</p>
            @else
                <div class="table-responsive bg-white rounded border">
                    <table class="table table-vcenter table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal & Jam Ke</th>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru Asli (Sesuai Jadwal)</th>
                                <th class="text-end">Aksi Tukar Jam</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allDaySchedules as $otherSched)
                                @php
                                    $slotKey = "{$otherSched->classroom_id}_{$otherSched->start_period}";
                                    $isAttended = in_array($otherSched->id, $todayAttendedScheduleIds ?? []) || in_array($slotKey, $todayAttendedSlots ?? []);
                                    $attendedByName = $attendedTeacherByScheduleId[$otherSched->id] ?? ($attendedTeacherBySlot[$slotKey] ?? null);
                                    $subImpact = $otherSched->getEventImpact($todayEvents ?? null);
                                    $subWindow = $otherSched->getAttendanceWindowStatus($date, 15);
                                    $isSubTooEarly = ! $isAttended && ! $subImpact && ! $subWindow['is_open'];
                                @endphp
                                <tr>
                                    <td class="fw-bold">
                                        <div class="text-dark">Jam {{ $otherSched->start_period }} - {{ $otherSched->end_period }}</div>
                                        <div class="text-muted small">
                                            <i class="ti ti-clock me-1"></i>{{ $otherSched->start_time ?: '-' }} - {{ $otherSched->end_time ?: '-' }}
                                            &bull; <i class="ti ti-calendar me-0.5"></i>{{ $date->translatedFormat('d/m/Y') }}
                                        </div>
                                    </td>
                                    <td><strong class="text-cyan">{{ $otherSched->classroom->name }}</strong></td>
                                    <td>
                                        <div>{{ $otherSched->subject->name }}</div>
                                        @if(!$isAttended && $subImpact)
                                            <div class="small text-muted" style="font-size: 0.72rem;">
                                                <i class="ti {{ $subImpact['type'] === 'bebas_kbm' ? 'ti-sparkles text-purple' : ($subImpact['type'] === 'daring' ? 'ti-device-laptop text-info' : 'ti-info-circle') }} me-0.5"></i>
                                                {{ $subImpact['title'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="avatar avatar-xs bg-secondary-lt me-1">
                                            {{ substr($otherSched->teacher?->name ?? 'G', 0, 1) }}
                                        </span>
                                        {{ $otherSched->teacher?->name ?? '-' }}
                                    </td>
                                    <td class="text-end">
                                        @if($isAttended)
                                            <span class="badge bg-success-lt text-success me-1.5" style="font-size: 0.72rem;">
                                                <i class="ti ti-check me-0.5"></i> Sudah Diabsen{{ $attendedByName ? ' (' . $attendedByName . ')' : '' }}
                                            </span>
                                            <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'tukar_jam']) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti ti-eye me-1"></i> Buka / Edit Presensi
                                            </a>
                                        @elseif($subImpact)
                                            <span class="badge {{ $subImpact['badge_class'] }} me-1.5" style="font-size: 0.72rem;">
                                                <i class="ti {{ $subImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($subImpact['type'] === 'daring' ? 'ti-device-laptop' : ($subImpact['type'] === 'libur' ? 'ti-calendar-off' : 'ti-home-check')) }} me-0.5"></i> {{ $subImpact['badge_text'] }}
                                            </span>
                                            @if($subImpact['type'] === 'bebas_kbm')
                                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'tukar_jam']) }}" class="btn btn-sm btn-outline-purple">
                                                    <i class="ti {{ Str::contains(strtolower($subImpact['title']), 'pkl') ? 'ti-briefcase' : 'ti-sparkles' }} me-1"></i> {{ Str::contains(strtolower($subImpact['title']), 'pkl') ? 'Bebas KBM (PKL)' : 'Catat Jurnal Kegiatan' }}
                                                </a>
                                            @else
                                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'tukar_jam']) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="ti ti-device-laptop me-1"></i> KBM Daring
                                                </a>
                                            @endif
                                        @elseif($isSubTooEarly)
                                            <span class="badge bg-secondary-lt text-secondary me-1.5" style="font-size: 0.72rem;">
                                                <i class="ti ti-clock me-0.5"></i> Buka Pukul {{ $subWindow['open_time'] ?? '-' }}
                                            </span>
                                            <button type="button" class="btn btn-sm btn-secondary disabled" style="opacity: 0.65; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $subWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                                <i class="ti ti-lock me-1"></i> Buka {{ $subWindow['open_time'] }}
                                            </button>
                                        @else
                                            <span class="badge bg-warning-lt text-warning me-1.5" style="font-size: 0.72rem;">
                                                <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                            </span>
                                            <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'tukar_jam']) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="ti ti-arrows-left-right me-1"></i> Gantikan & Absen Kelas Ini
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Sesi Tukar Jam yang Sudah Diabsen oleh Guru Ini -->
    @if($swapSessions->isNotEmpty())
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jam Ke</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru Asli</th>
                            <th>Status Tukar</th>
                            <th class="text-center">Rekap Absen</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($swapSessions as $swap)
                            <tr>
                                <td class="fw-bold text-center">
                                    <span class="badge bg-warning-lt px-2 py-1">
                                        {{ $swap->start_period }} - {{ $swap->end_period }}
                                    </span>
                                </td>
                                <td><strong class="text-dark">{{ $swap->classroom->name }}</strong></td>
                                <td>{{ $swap->subject->name }}</td>
                                <td>
                                    <span class="text-muted small">Menggantikan:</span><br>
                                    <strong>{{ $swap->scheduledTeacher?->name ?? 'Jadwal Reguler' }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        {{ strtoupper(str_replace('_', ' ', $swap->swap_type ?? 'Tukar Jam')) }}
                                    </span>
                                    @if($swap->swap_notes)
                                        <div class="text-muted small" style="font-size: 0.72rem;">{{ $swap->swap_notes }}</div>
                                    @endif
                                </td>
                                <td class="text-center small">
                                    H: <strong class="text-success">{{ $swap->hadir_count }}</strong> |
                                    S: <strong class="text-warning">{{ $swap->sakit_count }}</strong> |
                                    I: <strong class="text-info">{{ $swap->izin_count }}</strong> |
                                    A: <strong class="text-danger">{{ $swap->alpa_count }}</strong>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $swap->lesson_schedule_id, 'date' => $swap->attendance_date->toDateString(), 'is_swap' => 1]) }}" class="btn btn-sm btn-outline-warning">
                                        <i class="ti ti-edit me-1"></i> Edit
                                    </a>
                                    <a href="{{ route('attendance.subject.show', $swap) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card-body py-3 text-center text-muted small">
            Belum ada sesi tukar jam yang Anda ambil untuk tanggal ini.
        </div>
    @endif
</div>
@endif

<!-- Modal Presensi Mandiri (Tanpa Jadwal Tetap) -->
<div class="modal modal-blur fade" id="modalManualAttendance" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <form action="{{ route('attendance.subject.take') }}" method="GET">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-edit me-1"></i> Presensi Kelas Mandiri / Tambahan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label required fw-semibold small">Pilih Rombel / Kelas</label>
                        <select name="classroom_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($classrooms as $cls)
                                <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-semibold small">Mata Pelajaran</label>
                        <select name="subject_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Mapel --</option>
                            @foreach($subjects as $sbj)
                                <option value="{{ $sbj->id }}">{{ $sbj->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required fw-semibold small">Mulai Jam Ke-</label>
                            <input type="number" name="start_period" class="form-control form-control-sm" min="1" max="15" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label required fw-semibold small">Sampai Jam Ke-</label>
                            <input type="number" name="end_period" class="form-control form-control-sm" min="1" max="15" value="2" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Keterangan Khusus</label>
                        <select name="swap_type" class="form-select form-select-sm">
                            <option value="mandiri">Jam Tambahan / Mandiri</option>
                            <option value="tukar_jam">Tukar Jam Pelajaran</option>
                            <option value="guru_pengganti">Guru Pengganti</option>
                            <option value="inval">Guru Inval Meja Piket</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Guru Asli yang Digantikan (Opsional)</label>
                        <select name="scheduled_teacher_id" class="form-select form-select-sm">
                            <option value="">-- Tidak Ada / Jadwal Sendiri --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ti ti-arrow-right me-1"></i> Buka Lembar Presensi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
