@extends('layouts.mobile')

@section('title', 'SIAP SMK PGRI KRAS')

@section('content')
@php
    $nigsVal = $user->nigs_fix ?: ($user->nigs ?: ($user->id_number !== '-' ? $user->id_number : null));
    $nigsDisplay = $nigsVal ? "NIGS.{$nigsVal}" : ($user->formatted_id !== '-' ? $user->formatted_id : 'GTK');
    $primaryPosition = $user->structuralPositions->first()?->name ?: ($user->position ?: ($user->role?->name ?? 'Tenaga Pendidik'));
    $nameLen = mb_strlen($user->name);
    $initialHomeFontSize = match (true) {
        $nameLen > 36 => '0.80rem',
        $nameLen > 28 => '0.88rem',
        $nameLen > 22 => '0.96rem',
        default => '1.05rem',
    };
@endphp
<!-- Header Kartu Identitas GTK -->
<div class="card border-0 shadow-sm rounded-3 text-white" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; margin-top: 6px !important; margin-bottom: 8px !important; cursor: pointer;" onclick="window.location.href='{{ route('profile') }}'">
    <div class="card-body p-2 px-3">
        <div class="d-flex align-items-center gap-2.5">
            @if($user->avatar)
                <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="avatar avatar-md rounded-circle border border-2 border-white shadow-sm flex-shrink-0" style="width: 44px; height: 44px; object-fit: cover;">
            @else
                <span class="avatar avatar-md rounded-circle bg-warning text-dark fw-bold border border-2 border-white shadow-sm flex-shrink-0" style="width: 44px; height: 44px; font-size: 1rem; background-color: var(--brand-gold) !important; color: #fff !important;">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </span>
            @endif
            <div class="overflow-hidden flex-fill">
                <div id="homeUserNameContainer" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <span id="homeUserNameText" class="fw-bold text-white lh-sm" style="display: inline-block; white-space: nowrap; font-size: {{ $initialHomeFontSize }};">
                        {{ $user->name }}
                    </span>
                </div>
                <div class="text-white-50 small mt-1 text-truncate" style="font-size: 0.78rem;">
                    {{ $nigsDisplay }} | {{ $primaryPosition }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Cards: Hadir Hari Ini & Pengajuan Izin GTK (Grid 2 Kolom Berdampingan) -->
@php
    $hasCheckedIn = !empty($myCheckInToday);
    $hasApprovedPermit = empty($myCheckInToday) && !empty($myGtkPermitToday);
    $hasCheckedOut = !empty($myCheckOutToday);
@endphp
<div class="mobile-grid-2">
    <!-- Status Hadir Hari Ini -->
    <div class="card shadow-sm rounded-3" 
         style="@if($hasCheckedIn) background: #f0fdf4 !important; border: 1.5px solid #86efac !important; @elseif($hasApprovedPermit) background: #fffbeb !important; border: 1.5px solid #fde68a !important; @else background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; @endif">
        <div class="card-body p-2 d-flex flex-column justify-content-between h-100">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted fw-semibold" style="font-size: 0.68rem;">Status Hadir:</span>
                @if(empty($myCheckInToday))
                    @if(!empty($myGtkPermitToday))
                        <span class="badge bg-warning-lt text-warning" style="padding: 2px 6px; font-size: 0.62rem;">Disetujui</span>
                    @else
                        <a href="{{ route('attendance.geotag.index') }}" class="badge bg-success text-white text-decoration-none" style="padding: 2px 6px; font-size: 0.65rem;">Absen</a>
                    @endif
                @else
                    <span class="badge bg-success-lt" style="padding: 2px 6px; font-size: 0.62rem;">{{ $myCheckInToday->status === 'terlambat' ? 'Terlambat' : 'Tepat Waktu' }}</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-1.5">
                @if(!empty($myCheckInToday))
                    <span class="avatar avatar-sm bg-success-lt text-success rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-check fs-4"></i>
                    </span>
                    <div class="fw-bold text-success fs-4 lh-1 text-truncate">
                        {{ $myCheckInToday->scanned_at->format('H:i') }} <span style="font-size: 0.65rem;">WIB</span>
                    </div>
                @elseif(!empty($myGtkPermitToday))
                    <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-calendar-time fs-4"></i>
                    </span>
                    <div class="fw-bold text-warning fs-4 lh-1 text-truncate">
                        {{ $myGtkPermitToday->permit_type_label ?: ($myGtkPermitToday->type_label ?: 'Izin / Cuti') }}
                    </div>
                @else
                    <span class="avatar avatar-sm bg-danger-lt text-danger rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-clock-exclamation fs-4"></i>
                    </span>
                    <div class="fw-bold text-danger fs-4 lh-1 text-truncate">
                        Belum Absen
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Pulang Hari Ini -->
    <div class="card shadow-sm rounded-3" 
         style="@if($hasCheckedOut) background: #eff6ff !important; border: 1.5px solid #93c5fd !important; @else background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; @endif">
        <div class="card-body p-2 d-flex flex-column justify-content-between h-100">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted fw-semibold" style="font-size: 0.68rem;">Status Pulang:</span>
                @if(!empty($myCheckOutToday))
                    <span class="badge bg-blue-lt text-primary" style="padding: 2px 6px; font-size: 0.62rem;">Selesai</span>
                @elseif(!empty($myCheckInToday))
                    <a href="{{ route('attendance.geotag.index') }}" class="badge bg-primary text-white text-decoration-none" style="padding: 2px 6px; font-size: 0.65rem;">Pulang</a>
                @else
                    <span class="badge bg-secondary-lt" style="padding: 2px 6px; font-size: 0.62rem;">-</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-1.5">
                @if(!empty($myCheckOutToday))
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-logout fs-4"></i>
                    </span>
                    <div class="fw-bold text-primary fs-4 lh-1 text-truncate">
                        {{ $myCheckOutToday->scanned_at->format('H:i') }} <span style="font-size: 0.65rem;">WIB</span>
                    </div>
                @elseif(!empty($myCheckInToday))
                    <span class="avatar avatar-sm bg-info-lt text-info rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-briefcase fs-4"></i>
                    </span>
                    <div class="fw-bold text-dark fs-4 lh-1 text-truncate">
                        Bertugas
                    </div>
                @else
                    <span class="avatar avatar-sm bg-secondary-lt text-muted rounded-circle flex-shrink-0" style="width: 26px; height: 26px;">
                        <i class="ti ti-clock-off fs-4"></i>
                    </span>
                    <div class="fw-bold text-muted fs-4 lh-1 text-truncate">
                        Belum Pulang
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Agenda Sekolah Hari Ini (List Kegiatan dari Kalender Kegiatan) -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-2 px-2.5">
        @if(!empty($todaySchoolAgendas) && $todaySchoolAgendas->isNotEmpty())
            <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-calendar-event fs-4"></i>
                    </span>
                    <span class="text-muted fw-bold" style="font-size: 0.7rem;">Agenda Kegiatan Hari Ini ({{ $todaySchoolAgendas->count() }}):</span>
                    @if($user->isPrincipal() || $user->isSuperAdmin())
                        <span class="badge bg-purple-lt text-purple" style="font-size: 0.58rem; padding: 1px 5px;">Seluruh Sekolah</span>
                    @endif
                </div>
                <a href="{{ route('kurikulum.calendar.index') }}" class="badge bg-blue-lt text-primary text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                    Kalender
                </a>
            </div>
            @foreach($todaySchoolAgendas as $agenda)
                <div class="py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="fw-bold text-dark fs-4 lh-sm text-truncate" title="{{ $agenda->title }}">
                        {{ $agenda->title }}
                    </div>
                    <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                        {{ $agenda->mobile_subtitle }}
                    </div>
                </div>
            @endforeach
        @else
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                        <i class="ti ti-calendar-event fs-3"></i>
                    </span>
                    <div class="overflow-hidden">
                        <div class="text-muted fw-semibold" style="font-size: 0.65rem;">Agenda Sekolah:</div>
                        <div class="fw-bold text-dark fs-4 lh-sm text-truncate">
                            KBM Normal
                        </div>
                    </div>
                </div>
                <a href="{{ route('kurikulum.calendar.index') }}" class="badge bg-blue-lt text-primary text-decoration-none flex-shrink-0" style="font-size: 0.62rem;">
                    Kalender
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Notifikasi & Pemberitahuan Terhubung dengan User -->
@if(!empty($userNotifications) && $userNotifications->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mt-2">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="avatar avatar-sm bg-danger-lt text-danger rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-bell-ringing fs-4"></i>
                    </span>
                    <span class="text-muted fw-bold" style="font-size: 0.7rem;">Pemberitahuan &amp; Tugas Peran ({{ $userNotifications->count() }}):</span>
                </div>
                <span class="badge bg-danger text-white" style="font-size: 0.6rem; padding: 2px 6px;">Perlu Perhatian</span>
            </div>
            @foreach($userNotifications as $notif)
                <a href="{{ $notif['url'] }}" class="d-flex align-items-center gap-2 py-2 text-decoration-none {{ !$loop->last ? 'border-bottom' : '' }}">
                    <span class="avatar avatar-sm rounded-circle flex-shrink-0" style="width: 28px; height: 28px; background: #f8fafc; border: 1px solid #e2e8f0;">
                        <i class="{{ $notif['icon'] }} fs-3"></i>
                    </span>
                    <div class="overflow-hidden flex-fill" style="min-width: 0;">
                        <div class="d-flex align-items-center gap-1.5 overflow-hidden">
                            <span class="fw-bold text-dark fs-4 lh-sm text-truncate">{{ $notif['title'] }}</span>
                            <span class="badge {{ $notif['badge_class'] }} flex-shrink-0" style="font-size: 0.6rem; padding: 1px 5px;">{{ $notif['badge'] }}</span>
                        </div>
                        <div class="text-muted small lh-sm mt-0.5 text-truncate" style="font-size: 0.7rem;">
                            {{ $notif['message'] }}
                        </div>
                    </div>
                    <i class="ti ti-chevron-right text-muted fs-4 flex-shrink-0 ms-auto"></i>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if(!empty($isPiketToday))
    <!-- Banner Tugas Piket Hari Ini di Beranda -->
    <div class="card border-0 shadow-sm rounded-3 mt-2" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border: 1.5px solid #d8b4fe !important;">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <div class="d-flex align-items-center gap-1.5 overflow-hidden flex-fill" style="min-width: 0;">
                    <span class="avatar avatar-sm bg-purple text-white rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-shield-check fs-4"></i>
                    </span>
                    <span class="fw-bold text-dark fs-4 lh-sm text-truncate">Kewajiban Piket Hari Ini</span>
                </div>
                <a href="{{ route('piket.swaps.index') }}" class="text-decoration-none fw-bold flex-shrink-0 d-inline-flex align-items-center gap-1 external" style="font-size: 0.72rem; color: #7c3aed !important;">
                    <span>Tukar Jadwal</span>
                    <i class="ti ti-arrows-left-right" style="font-size: 0.75rem;"></i>
                </a>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-1 pt-1 border-top" style="border-color: rgba(216, 180, 254, 0.45) !important;">
                <span class="small text-truncate" style="font-size: 0.68rem; color: #6b21a8;">
                    Pos: {{ $todayPiketSchedule?->duty_location ?? ($todayPiketSchedule?->post_name ?? 'Meja Piket Utama') }}
                </span>
                <a href="{{ route('piket.dashboard') }}" class="btn btn-sm btn-purple rounded-pill px-2.5 py-0.5 fw-bold shadow-xs flex-shrink-0 external" style="font-size: 0.68rem; background: #7c3aed; color: #ffffff;">
                    Buka Piket
                </a>
            </div>
        </div>
    </div>
@elseif(!empty($myWeeklyPiketSchedules) && $myWeeklyPiketSchedules->isNotEmpty())
    <!-- Banner Jadwal Piket (Saat tidak piket hari ini) -->
    <div class="card border-0 shadow-sm rounded-3 mt-2" style="background: #faf5ff; border: 1.5px solid #e9d5ff !important;">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-1.5 overflow-hidden flex-fill" style="min-width: 0;">
                    <span class="avatar avatar-sm bg-purple-lt text-purple rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-shield-check fs-4"></i>
                    </span>
                    <span class="fw-bold text-dark fs-4 lh-sm text-truncate">
                        Jadwal Piket: <span class="text-purple">{{ $myWeeklyPiketSchedules->pluck('day')->implode(', ') }}</span>
                    </span>
                </div>
                <a href="{{ route('piket.swaps.index') }}" class="text-decoration-none fw-bold flex-shrink-0 d-inline-flex align-items-center gap-1 external" style="font-size: 0.72rem; color: #7c3aed !important;">
                    <span>Tukar Jadwal</span>
                    <i class="ti ti-arrows-left-right" style="font-size: 0.75rem;"></i>
                </a>
            </div>
            <div class="d-flex align-items-center justify-content-between text-muted small mt-1 pt-1 border-top" style="font-size: 0.68rem; border-color: rgba(216, 180, 254, 0.45) !important;">
                <span class="text-truncate">
                    Pos: {{ $myWeeklyPiketSchedules->first()?->duty_location ?: 'Meja Piket Utama' }}
                </span>
                <a href="{{ route('piket.dashboard') }}" class="text-decoration-none fw-semibold flex-shrink-0 external" style="font-size: 0.68rem; color: #7c3aed !important;">
                    Buka Piket &rarr;
                </a>
            </div>
        </div>
    </div>
@endif

<!-- Card Jadwal KBM Hari Ini (Hanya Tampil Jika Punya Jadwal KBM Hari Ini & Bukan Staff / KS) -->
@if(!$user->isStaff() && !$user->isPrincipal() && !empty($todayTeachingSchedules) && $todayTeachingSchedules->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mb-1 mt-2">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-book fs-4"></i>
                    </span>
                    <span class="text-muted fw-bold" style="font-size: 0.7rem;">Jadwal Mengajar Hari Ini ({{ $todayTeachingSchedules->count() }}):</span>
                </div>
                <a href="{{ route('attendance.subject.index') }}" class="badge bg-primary-lt text-primary text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                    Semua
                </a>
            </div>
            @foreach($todayTeachingSchedules as $sched)
                @php
                    $isAttended = in_array($sched->id, ($mySavedScheduleIds ?? collect())->toArray());
                    $eventImpact = $sched->getEventImpact($allTodayEvents ?? null);
                    $isExempted = $eventImpact && in_array($eventImpact['type'], ['bebas_kbm', 'libur']);
                    $dashWindow = $sched->getAttendanceWindowStatus(now(), 15);
                    $isTooEarly = ! $isAttended && ! $isExempted && ! $dashWindow['is_open'];
                @endphp
                <div class="py-1.5 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <!-- Baris 1: Jam & Kelas di kiri, Tombol/Banner Absen lurus di kanan -->
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-0.5">
                        <div class="fw-bold text-dark fs-4 lh-sm text-truncate">
                            Jam {{ $sched->start_period }}-{{ $sched->end_period }} &bull; <span class="text-primary">{{ $sched->classroom?->name }}</span>
                        </div>
                        @if($isAttended)
                            <span class="badge bg-success-lt text-success fw-bold rounded-pill px-2.5 py-0.5 flex-shrink-0 d-inline-flex align-items-center gap-1" style="font-size: 0.68rem; line-height: 1.2;">
                                <i class="ti ti-check"></i> Sudah Diabsen
                            </span>
                        @elseif($isExempted)
                            <span class="badge {{ $eventImpact['badge_class'] }} fw-bold rounded-pill px-2.5 py-0.5 flex-shrink-0 d-inline-flex align-items-center gap-1" style="font-size: 0.68rem; line-height: 1.2;">
                                <i class="ti {{ $eventImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : 'ti-calendar-off' }}"></i> {{ $eventImpact['badge_text'] }}
                            </span>
                        @elseif($isTooEarly)
                            <button type="button" class="btn btn-sm btn-secondary disabled rounded-pill px-2 py-0.5 flex-shrink-0" style="font-size: 0.66rem; line-height: 1.3; opacity: 0.7; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $dashWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                <i class="ti ti-lock me-0.5"></i> Buka {{ $dashWindow['open_time'] }}
                            </button>
                        @else
                            <a href="{{ route('attendance.subject.take', ['classroom_id' => $sched->classroom_id, 'subject_id' => $sched->subject_id, 'start_period' => $sched->start_period, 'end_period' => $sched->end_period, 'lesson_schedule_id' => $sched->id, 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-primary rounded-pill px-2.5 py-0.5 flex-shrink-0" style="font-size: 0.72rem; line-height: 1.3;">
                                Absen
                            </a>
                        @endif
                    </div>
                    <!-- Baris 2: Mapel & Waktu Mengajar (Leluasa di bawah, tidak mendesak tombol/banner) -->
                    <div class="d-flex align-items-center justify-content-between text-muted small text-truncate" style="font-size: 0.72rem;">
                        <span class="text-truncate">
                            {{ $sched->subject?->name ?? 'Mata Pelajaran' }} ({{ $sched->start_time ?: '-' }} - {{ $sched->end_time ?: '-' }})
                        </span>
                        @if($isExempted)
                            <span class="text-purple fw-semibold flex-shrink-0 ms-1 text-truncate" style="font-size: 0.68rem;" title="{{ $eventImpact['title'] }}">
                                &bull; {{ \Illuminate\Support\Str::limit($eventImpact['title'], 22) }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
    function fitHomeUserName() {
        const container = document.getElementById('homeUserNameContainer');
        const text = document.getElementById('homeUserNameText');
        if (!container || !text) return;
        const maxW = container.clientWidth - 4;
        if (maxW <= 0) return;
        let size = parseFloat(window.getComputedStyle(text).fontSize);
        while (text.scrollWidth > maxW && size > 9.5) {
            size -= 0.5;
            text.style.fontSize = size + 'px';
        }
    }
    fitHomeUserName();
    document.addEventListener('DOMContentLoaded', fitHomeUserName);
    window.addEventListener('resize', fitHomeUserName);
</script>
@endpush
