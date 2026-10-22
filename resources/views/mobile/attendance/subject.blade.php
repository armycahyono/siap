@extends('layouts.mobile')

@section('title', 'Jurnal & Presensi KBM — APK SIAP')
@section('page_title', 'Jurnal & Presensi KBM')
@section('page_subtitle', auth()->user()?->name)
@section('back_url', route('dashboard'))

@push('styles')
<style>
    @keyframes floatCoffee {
        0%, 100% {
            transform: translateY(0px) rotate(0deg);
        }
        50% {
            transform: translateY(-6px) rotate(-1.5deg);
        }
    }

    @keyframes steamRise {
        0% {
            opacity: 0.25;
            transform: translateY(0) scale(0.9);
        }
        50% {
            opacity: 0.85;
            transform: translateY(-5px) scale(1.1);
        }
        100% {
            opacity: 0.25;
            transform: translateY(0) scale(0.9);
        }
    }

    .animated-coffee-card {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 50%, #fff7ed 100%) !important;
        border: 1.5px solid #fde68a !important;
        position: relative;
        overflow: hidden;
    }

    .animated-coffee-cup {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(217, 119, 6, 0.28);
        animation: floatCoffee 3s ease-in-out infinite;
        will-change: transform;
        transform: translateZ(0);
        position: relative;
    }

    .coffee-steam-icon {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 1.1rem;
        color: #d97706;
        animation: steamRise 2.5s ease-in-out infinite;
        will-change: transform, opacity;
    }

    .monitoring-chip-strip {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 4px !important;
        width: 100% !important;
        flex-wrap: nowrap !important;
    }

    .monitoring-pill-item {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 3px !important;
        padding: 5px 3px !important;
        border-radius: 9999px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        user-select: none !important;
        text-align: center !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .monitoring-pill-item:active {
        transform: scale(0.96) !important;
    }

    .monitoring-pill-num {
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        line-height: 1 !important;
    }

    .monitoring-pill-lbl {
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        line-height: 1 !important;
        letter-spacing: -0.01em !important;
    }

    .monitoring-detail-wrapper {
        margin-top: 14px !important;
        margin-bottom: 6px !important;
        display: none;
    }

    .monitoring-detail-card {
        border-radius: 0.65rem !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(16, 45, 82, 0.08) !important;
    }
</style>
@endpush

@section('content')
<!-- Pemilih Tanggal KBM -->
@php
    $isPastDate = $date->isPast() && !$date->isToday();
    $isFutureDate = $date->isFuture() && !$date->isToday();
@endphp
<!-- Tombol Navigasi Cepat KBM: Jadwal Pelajaran, Kalender Kegiatan, & Riwayat Absen (Sejajar di Atas Kartu Tanggal) -->
<div class="d-flex align-items-center gap-1.5 mb-2">
    <a href="{{ route('attendance.subject.school-schedule') }}" class="btn btn-sm btn-azure-lt rounded-pill flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1 text-decoration-none fw-semibold shadow-xs" style="font-size: 0.74rem;" title="Jadwal Pelajaran Sekolah">
        <i class="ti ti-calendar-time fs-3"></i>
        <span>Jadwal</span>
    </a>
    <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm btn-purple-lt rounded-pill flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1 text-decoration-none fw-semibold shadow-xs" style="font-size: 0.74rem;" title="Kalender Kegiatan Sekolah">
        <i class="ti ti-calendar-event fs-3"></i>
        <span>Kalender</span>
    </a>
    <a href="{{ route('attendance.subject.history') }}" class="btn btn-sm btn-teal-lt rounded-pill flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1 text-decoration-none fw-semibold shadow-xs" style="font-size: 0.74rem;" title="Riwayat Absen KBM">
        <i class="ti ti-history fs-3"></i>
        <span>Riwayat</span>
    </a>
</div>

<div class="card shadow-sm rounded-3 mb-2" style="border: 1px solid #3b82f6 !important;">
    <div class="card-body p-2 px-3">
        <form action="{{ route('attendance.subject.index') }}" method="GET" class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-1.5 flex-fill overflow-hidden">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                    <i class="ti ti-calendar fs-3"></i>
                </span>
                <input type="date" name="date" class="form-control form-control-sm border-0 bg-transparent fw-bold text-dark p-0" style="font-size: 0.88rem;" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
            </div>
            <div class="d-flex align-items-center flex-shrink-0">
                @if(!$date->isToday())
                    <a href="{{ route('attendance.subject.index', ['date' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5" style="font-size: 0.72rem;">
                        Hari Ini
                    </a>
                @else
                    <span class="badge bg-success-lt px-2.5 py-1" style="font-size: 0.68rem;">Hari Ini</span>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Agenda Sekolah Berdampak Bagi Guru (Model Sama Seperti Beranda) -->
@php
    $impactingAgendas = $teacherImpactingEvents ?? $todayEvents->filter(fn ($ev) => $ev->affectsTeacher(auth()->user(), $mySchedules));
@endphp

@if($impactingAgendas->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 24px; height: 24px;">
                        <i class="ti ti-calendar-event fs-4"></i>
                    </span>
                    <span class="text-muted fw-bold" style="font-size: 0.7rem;">Agenda Berdampak Bagi Anda ({{ $impactingAgendas->count() }}):</span>
                </div>
                <a href="{{ route('kurikulum.calendar.index') }}" class="badge bg-blue-lt text-primary text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                    Kalender
                </a>
            </div>
            @foreach($impactingAgendas as $agenda)
                <div class="py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="fw-bold text-dark fs-4 lh-sm text-truncate" title="{{ $agenda->title }}">
                        {{ $agenda->title }}
                    </div>
                    <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                        {{ $agenda->mobile_subtitle }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<!-- Bertugas Khusus / Bebas Tatap Muka -->
@if(isset($teacherOnDutyEvents) && $teacherOnDutyEvents->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: #ecfdf5; border-left: 4px solid #16a34a !important;">
        <div class="card-body p-2 px-3">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-shield-check text-success fs-2 flex-shrink-0"></i>
                <div>
                    <div class="fw-bold text-dark fs-4 lh-sm">Tugas Khusus Hari Ini</div>
                    <div class="text-muted small" style="font-size: 0.7rem;">
                        Gugur kewajiban tatap muka kelas reguler tanpa mengurangi presensi.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- MONITORING KBM HARI INI (KS, Kurikulum, Tim Kedisiplinan) -->
@if(!empty($kbmMonitoring))
    <div class="card shadow-sm rounded-3 mb-2.5" style="border: 1.5px solid #16a34a !important;">
        <div class="card-body p-2 px-3">
            <div class="d-flex align-items-center justify-content-between mb-2 pb-1.5 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 30px; height: 30px;">
                        <i class="ti ti-dashboard fs-3"></i>
                    </span>
                    <div>
                        <div class="fw-bold text-dark fs-3 lh-1">Monitoring KBM Hari Ini</div>
                        <div class="text-muted small mt-0.5" style="font-size: 0.68rem;">{{ $dayName }}, {{ $date->translatedFormat('d F Y') }}</div>
                    </div>
                </div>
                <span class="badge bg-primary text-white" style="font-size: 0.62rem; padding: 2px 6px;">Live Monitoring</span>
            </div>

            @if($kbmMonitoring['can_view_gtk'])
                <!-- 1. MONITORING GTK (Khusus KS & Kurikulum) -->
                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <a href="{{ route('attendance.report.teachers', ['period_type' => 'custom', 'start_date' => $date->toDateString(), 'end_date' => $date->toDateString()]) }}" class="fw-bold text-dark text-decoration-none d-inline-flex align-items-center gap-1 external" style="font-size: 0.84rem;">
                            <i class="ti ti-users-group text-primary"></i>
                            <span>Kehadiran GTK / Guru Pengajar</span>
                            <i class="ti ti-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                        </a>
                        <span class="text-muted small" style="font-size: 0.68rem;">
                            Total: {{ $kbmMonitoring['gtk']['all_count'] }} GTK
                        </span>
                    </div>

                    <!-- Pill Statistik GTK (2 Baris) -->
                    <div class="d-flex flex-column gap-1">
                        <!-- Baris 1: Hadir, Telat, Izin -->
                        <div class="monitoring-chip-strip">
                            <div class="monitoring-pill-item bg-success-lt border border-success" 
                                 onclick="toggleMonitoringDetail('gtk', 'present')" id="chip-gtk-present">
                                <span class="monitoring-pill-num text-success">{{ $kbmMonitoring['gtk']['present_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Hadir</span>
                            </div>
                            <div class="monitoring-pill-item bg-warning-lt border border-warning" 
                                 onclick="toggleMonitoringDetail('gtk', 'late')" id="chip-gtk-late">
                                <span class="monitoring-pill-num text-warning">{{ $kbmMonitoring['gtk']['late_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Telat</span>
                            </div>
                            <div class="monitoring-pill-item bg-purple-lt border border-purple" 
                                 onclick="toggleMonitoringDetail('gtk', 'permit')" id="chip-gtk-permit">
                                <span class="monitoring-pill-num text-purple">{{ $kbmMonitoring['gtk']['permit_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Izin</span>
                            </div>
                        </div>
                        <!-- Baris 2: Belum Masuk, Belum Mapel -->
                        <div class="monitoring-chip-strip">
                            <div class="monitoring-pill-item bg-pink-lt border border-pink" 
                                 onclick="toggleMonitoringDetail('gtk', 'awaiting')" id="chip-gtk-awaiting">
                                <span class="monitoring-pill-num text-pink">{{ $kbmMonitoring['gtk']['awaiting_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Belum Masuk</span>
                            </div>
                            <div class="monitoring-pill-item bg-danger-lt border border-danger" 
                                 onclick="toggleMonitoringDetail('gtk', 'unattended')" id="chip-gtk-unattended">
                                <span class="monitoring-pill-num text-danger">{{ $kbmMonitoring['gtk']['unattended_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Belum Mapel</span>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Data GTK (Ditampilkan hanya saat banner/chip diklik) -->
                    <div class="text-start monitoring-detail-wrapper" id="gtkDetailContainer" style="display: none;">
                        <div class="card monitoring-detail-card mb-0 overflow-hidden">
                            <div class="card-header p-1.5 px-2 bg-light d-flex align-items-center justify-content-between border-bottom">
                                <span class="fw-bold small text-dark d-flex align-items-center gap-1" id="gtkDetailTitle" style="font-size: 0.72rem;">
                                    <i class="ti ti-list-details text-primary"></i> Detail Data
                                </span>
                                <span class="cursor-pointer text-muted px-1" role="button" aria-label="Tutup" onclick="closeMonitoringDetail('gtk')" style="font-size: 0.85rem; line-height: 1; cursor: pointer;">
                                    <i class="ti ti-x"></i>
                                </span>
                            </div>
                            <div class="card-body p-2" style="max-height: 240px; overflow-y: auto;">
                                <!-- Detail: Hadir -->
                                <div id="gtkPane-present" class="gtk-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['gtk']['present_list']) && count($kbmMonitoring['gtk']['present_list']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['gtk']['present_list'] as $log)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1 text-truncate">
                                                        <span class="fw-semibold text-dark">{{ $log->user?->name ?? 'GTK' }}</span>
                                                    </div>
                                                    <span class="badge bg-success-lt text-success flex-shrink-0" style="font-size: 0.6rem;">
                                                        {{ \Carbon\Carbon::parse($log->scanned_at)->format('H:i') }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Belum ada GTK hadir hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail: Terlambat -->
                                <div id="gtkPane-late" class="gtk-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['gtk']['late_list']) && count($kbmMonitoring['gtk']['late_list']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['gtk']['late_list'] as $log)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1 text-truncate">
                                                        <span class="fw-semibold text-dark">{{ $log->user?->name ?? 'GTK' }}</span>
                                                    </div>
                                                    <span class="badge bg-warning-lt text-warning flex-shrink-0" style="font-size: 0.6rem;">
                                                        {{ \Carbon\Carbon::parse($log->scanned_at)->format('H:i') }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada GTK terlambat hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail: Izin/Cuti -->
                                <div id="gtkPane-permit" class="gtk-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['gtk']['permits_list']) && count($kbmMonitoring['gtk']['permits_list']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['gtk']['permits_list'] as $pmt)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $pmt->user?->name ?? 'GTK' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ ucfirst(str_replace('_', ' ', $pmt->permit_type ?? 'Izin')) }} &bull; {{ Str::limit($pmt->notes, 30) }}</div>
                                                    </div>
                                                    <span class="badge bg-purple-lt text-purple flex-shrink-0" style="font-size: 0.6rem;">Izin</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada GTK izin/cuti hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail: Belum Absen Masuk -->
                                <div id="gtkPane-awaiting" class="gtk-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['gtk']['awaiting_list']) && count($kbmMonitoring['gtk']['awaiting_list']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['gtk']['awaiting_list'] as $u)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <span class="fw-semibold text-dark text-truncate">{{ $u->name }}</span>
                                                    <span class="badge bg-danger-lt text-danger flex-shrink-0" style="font-size: 0.6rem;">Belum Masuk</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        @if(!empty($kbmMonitoring['gtk']['is_holiday']))
                                            <div class="text-center text-muted small py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <i class="ti ti-calendar-off me-0.5 text-danger"></i> Hari ini libur sekolah, tidak ada kewajiban presensi masuk.
                                            </div>
                                        @elseif(!empty($kbmMonitoring['gtk']['is_weekend']) && $kbmMonitoring['gtk']['all_count'] === 0)
                                            <div class="text-center text-muted small py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <i class="ti ti-calendar-event me-0.5 text-primary"></i> Akhir pekan (5 hari kerja). Tidak ada agenda dinas yang mewajibkan GTK hadir hari ini.
                                            </div>
                                        @else
                                            <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <i class="ti ti-check me-0.5"></i> Seluruh GTK yang bertugas telah absen masuk.
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                <!-- Detail: Belum Absen Mapel -->
                                <div id="gtkPane-unattended" class="gtk-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['gtk']['unattended_schedules']) && count($kbmMonitoring['gtk']['unattended_schedules']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['gtk']['unattended_schedules'] as $unSched)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-danger-subtle small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-bold text-dark text-truncate">{{ $unSched->teacher?->formatted_short_name ?? $unSched->teacher?->name }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $unSched->subject?->name ?? 'Mapel' }} &bull; {{ $unSched->classroom?->name }}</div>
                                                    </div>
                                                    <span class="badge bg-danger text-white flex-shrink-0" style="font-size: 0.6rem;">Jam {{ $unSched->start_period }}-{{ $unSched->end_period }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        @if(!empty($kbmMonitoring['gtk']['is_holiday']))
                                            <div class="text-center text-muted small py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <i class="ti ti-calendar-off me-0.5 text-danger"></i> Hari ini libur / bebas KBM, tidak ada jadwal presensi mapel.
                                            </div>
                                        @else
                                            <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <i class="ti ti-check me-0.5"></i> Seluruh guru yang terjadwal telah melakukan presensi mapel.
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div><!-- /#gtkDetailContainer -->
                </div>
            @endif

            @if($kbmMonitoring['can_view_student'])
                <!-- 2. MONITORING SISWA (KS, Kurikulum, dan Tim Kedisiplinan) -->
                @if($kbmMonitoring['can_view_gtk'])
                    <hr class="my-2.5" style="border-top: 1px solid #e2e8f0; opacity: 1;">
                @endif
                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <div>
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('attendance.homeroom-recap.index', ['date' => $date->toDateString()]) }}" class="fw-bold text-dark text-decoration-none d-inline-flex align-items-center gap-1 external" style="font-size: 0.84rem;">
                                    <i class="ti ti-school text-indigo"></i>
                                    <span>Kehadiran Siswa Hari Ini</span>
                                    <i class="ti ti-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                                </a>
                                @if(!empty($kbmMonitoring['siswa']['total_expected_students']) && $kbmMonitoring['siswa']['total_expected_students'] > 0 && ($kbmMonitoring['siswa']['total_completed_sessions'] ?? 0) > 0)
                                    <span class="badge bg-indigo-lt text-indigo ms-1 fw-bold" style="font-size: 0.65rem;">
                                        {{ $kbmMonitoring['siswa']['overall_attendance_rate'] }}% Hadir
                                    </span>
                                @endif
                            </div>
                            @if(($kbmMonitoring['siswa']['total_completed_sessions'] ?? 0) === 0)
                                <div class="text-warning fw-semibold mt-0.5 d-flex align-items-center gap-1" style="font-size: 0.64rem;">
                                    <i class="ti ti-clock-pause"></i> Presensi KBM belum dimulai
                                </div>
                            @endif
                        </div>
                        @if(!$kbmMonitoring['can_view_gtk'])
                            <span class="badge bg-indigo-lt text-indigo" style="font-size: 0.6rem;">Khusus Kedisiplinan</span>
                        @elseif(!empty($kbmMonitoring['siswa']['total_expected_students']))
                            <span class="text-muted small" style="font-size: 0.62rem;">
                                {{ $kbmMonitoring['siswa']['total_expected_students'] }} Siswa
                            </span>
                        @endif
                    </div>

                    <!-- Pill Statistik Siswa (2 Baris) -->
                    <div class="d-flex flex-column gap-1">
                        <!-- Baris 1: Hadir, Izin, Sakit -->
                        <div class="monitoring-chip-strip">
                            <div class="monitoring-pill-item bg-success-lt border border-success" 
                                 onclick="toggleMonitoringDetail('siswa', 'present')" id="chip-siswa-present">
                                <span class="monitoring-pill-num text-success">{{ $kbmMonitoring['siswa']['present_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Hadir</span>
                            </div>
                            <div class="monitoring-pill-item bg-info-lt border border-info" 
                                 onclick="toggleMonitoringDetail('siswa', 'izin')" id="chip-siswa-izin">
                                <span class="monitoring-pill-num text-info">{{ $kbmMonitoring['siswa']['izin_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Izin</span>
                            </div>
                            <div class="monitoring-pill-item bg-azure-lt border border-azure" 
                                 onclick="toggleMonitoringDetail('siswa', 'sakit')" id="chip-siswa-sakit">
                                <span class="monitoring-pill-num text-azure">{{ $kbmMonitoring['siswa']['sakit_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Sakit</span>
                            </div>
                        </div>
                        <!-- Baris 2: Telat, Pulang, Alfa, Bolos -->
                        <div class="monitoring-chip-strip">
                            <div class="monitoring-pill-item bg-warning-lt border border-warning" 
                                 onclick="toggleMonitoringDetail('siswa', 'late')" id="chip-siswa-late">
                                <span class="monitoring-pill-num text-warning">{{ $kbmMonitoring['siswa']['late_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Telat</span>
                            </div>
                            <div class="monitoring-pill-item bg-purple-lt border border-purple" 
                                 onclick="toggleMonitoringDetail('siswa', 'pulang')" id="chip-siswa-pulang">
                                <span class="monitoring-pill-num text-purple">{{ $kbmMonitoring['siswa']['pulang_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Pulang</span>
                            </div>
                            <div class="monitoring-pill-item bg-danger-lt border border-danger" 
                                 onclick="toggleMonitoringDetail('siswa', 'alfa')" id="chip-siswa-alfa">
                                <span class="monitoring-pill-num text-danger">{{ $kbmMonitoring['siswa']['alfa_count'] }}</span>
                                <span class="monitoring-pill-lbl text-muted">Alfa</span>
                            </div>
                            @php
                                $truancyCount = (int) ($kbmMonitoring['siswa']['truancy_count'] ?? 0);
                            @endphp
                            <div class="monitoring-pill-item {{ $truancyCount > 0 ? 'bg-white border border-danger shadow-xs' : 'bg-dark-lt border border-dark' }}" 
                                 onclick="toggleMonitoringDetail('siswa', 'truancy')" id="chip-siswa-truancy">
                                <span class="monitoring-pill-num {{ $truancyCount > 0 ? 'text-danger fw-bold' : 'text-dark' }}">{{ $truancyCount }}</span>
                                <span class="monitoring-pill-lbl {{ $truancyCount > 0 ? 'text-danger fw-bold' : 'text-muted' }}">Bolos</span>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Data Siswa (Ditampilkan hanya saat banner/chip diklik) -->
                    <div class="text-start monitoring-detail-wrapper" id="siswaDetailContainer" style="display: none;">
                        <div class="card monitoring-detail-card mb-0 overflow-hidden">
                            <div class="card-header p-1.5 px-2 bg-light d-flex align-items-center justify-content-between border-bottom">
                                <span class="fw-bold small text-dark d-flex align-items-center gap-1" id="siswaDetailTitle" style="font-size: 0.72rem;">
                                    <i class="ti ti-list-details text-indigo"></i> Detail Siswa
                                </span>
                                <span class="cursor-pointer text-muted px-1" role="button" aria-label="Tutup" onclick="closeMonitoringDetail('siswa')" style="font-size: 0.85rem; line-height: 1; cursor: pointer;">
                                    <i class="ti ti-x"></i>
                                </span>
                            </div>
                            <div class="card-body p-2" style="max-height: 240px; overflow-y: auto;">
                                <!-- Detail Siswa: Hadir -->
                                <div id="siswaPane-present" class="siswa-detail-pane" style="display: none;">
                                    <div class="p-2 mb-2 bg-success-lt rounded border border-success small" style="font-size: 0.7rem;">
                                        <div class="fw-bold text-success mb-0.5">
                                            <i class="ti ti-users-check me-1"></i> {{ $kbmMonitoring['siswa']['present_count'] }} Siswa Hadir ({{ $kbmMonitoring['siswa']['overall_attendance_rate'] }}%)
                                        </div>
                                        <div class="text-muted" style="font-size: 0.65rem;">
                                            Dari total {{ $kbmMonitoring['siswa']['total_expected_students'] }} siswa aktif yang terjadwal mengikuti KBM hari ini.
                                        </div>
                                    </div>
                                    @if(!empty($kbmMonitoring['siswa']['classroom_snapshots']) && count($kbmMonitoring['siswa']['classroom_snapshots']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['classroom_snapshots'] as $snap)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1 text-truncate">
                                                        <span class="fw-semibold text-dark">{{ $snap->classroom?->name ?? 'Kelas' }}</span>
                                                        <span class="text-muted ms-1" style="font-size: 0.62rem;">({{ $snap->present_students_count }}/{{ $snap->total_students }})</span>
                                                    </div>
                                                    <span class="badge {{ $snap->attendance_percentage >= 90 ? 'bg-success-lt text-success' : ($snap->attendance_percentage >= 75 ? 'bg-warning-lt text-warning' : 'bg-danger-lt text-danger') }} flex-shrink-0" style="font-size: 0.6rem;">
                                                        {{ round($snap->attendance_percentage, 1) }}%
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Data kelas belum tersedia.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Izin -->
                                <div id="siswaPane-izin" class="siswa-detail-pane" style="display: none;">
                                    @php
                                        $hasIzinData = (!empty($kbmMonitoring['siswa']['izin_permits']) && count($kbmMonitoring['siswa']['izin_permits']) > 0)
                                            || (!empty($kbmMonitoring['siswa']['izin_records']) && count($kbmMonitoring['siswa']['izin_records']) > 0);
                                    @endphp
                                    @if($hasIzinData)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['izin_permits'] as $p)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $p->classroom?->name }} &bull; {{ Str::limit($p->reason ?? 'Izin', 25) }}</div>
                                                    </div>
                                                    <span class="badge bg-info-lt text-info flex-shrink-0" style="font-size: 0.6rem;">Surat Piket</span>
                                                </div>
                                            @endforeach
                                            @foreach($kbmMonitoring['siswa']['izin_records'] as $r)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $r->session?->classroom?->name }} &bull; {{ $r->session?->subject?->name }}</div>
                                                    </div>
                                                    <span class="badge bg-info-lt text-info flex-shrink-0" style="font-size: 0.6rem;">{{ strtoupper($r->status) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa izin hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Sakit -->
                                <div id="siswaPane-sakit" class="siswa-detail-pane" style="display: none;">
                                    @php
                                        $hasSakitData = (!empty($kbmMonitoring['siswa']['sakit_permits']) && count($kbmMonitoring['siswa']['sakit_permits']) > 0)
                                            || (!empty($kbmMonitoring['siswa']['sakit_records']) && count($kbmMonitoring['siswa']['sakit_records']) > 0);
                                    @endphp
                                    @if($hasSakitData)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['sakit_permits'] as $p)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $p->classroom?->name }} &bull; {{ Str::limit($p->reason ?? 'Sakit', 25) }}</div>
                                                    </div>
                                                    <span class="badge bg-azure-lt text-azure flex-shrink-0" style="font-size: 0.6rem;">Surat Piket</span>
                                                </div>
                                            @endforeach
                                            @foreach($kbmMonitoring['siswa']['sakit_records'] as $r)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $r->session?->classroom?->name }} &bull; {{ $r->session?->subject?->name }}</div>
                                                    </div>
                                                    <span class="badge bg-azure-lt text-azure flex-shrink-0" style="font-size: 0.6rem;">SAKIT</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa sakit hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Terlambat -->
                                <div id="siswaPane-late" class="siswa-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['siswa']['late_permits']) && count($kbmMonitoring['siswa']['late_permits']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['late_permits'] as $p)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $p->classroom?->name }} &bull; Jam ke-{{ $p->entry_period ?? '-' }}</div>
                                                    </div>
                                                    <span class="badge bg-warning-lt text-warning flex-shrink-0" style="font-size: 0.6rem;">Terlambat</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa terlambat tercatat hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Pulang -->
                                <div id="siswaPane-pulang" class="siswa-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['siswa']['pulang_permits']) && count($kbmMonitoring['siswa']['pulang_permits']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['pulang_permits'] as $p)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $p->classroom?->name }} &bull; {{ Str::limit($p->reason ?? 'Izin Pulang', 25) }}</div>
                                                    </div>
                                                    <span class="badge bg-purple-lt text-purple flex-shrink-0" style="font-size: 0.6rem;">Pulang Cepat</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa izin pulang hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Alfa -->
                                <div id="siswaPane-alfa" class="siswa-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['siswa']['alfa_records']) && count($kbmMonitoring['siswa']['alfa_records']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['alfa_records'] as $r)
                                                <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-danger-subtle small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">{{ $r->session?->classroom?->name }} &bull; {{ $r->session?->subject?->name }}</div>
                                                    </div>
                                                    <span class="badge bg-danger text-white flex-shrink-0" style="font-size: 0.6rem;">Alfa</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">Tidak ada siswa alfa hari ini.</div>
                                    @endif
                                </div>

                                <!-- Detail Siswa: Indikasi Bolos KBM (Hadir sebagian / Scan Gerbang tapi Alfa Mapel) -->
                                <div id="siswaPane-truancy" class="siswa-detail-pane" style="display: none;">
                                    @if(!empty($kbmMonitoring['siswa']['truancy_list']) && count($kbmMonitoring['siswa']['truancy_list']) > 0)
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($kbmMonitoring['siswa']['truancy_list'] as $t)
                                                <div class="d-flex align-items-start justify-content-between p-1.5 bg-white rounded border border-dark-subtle small" style="font-size: 0.68rem;">
                                                    <div class="overflow-hidden pe-1">
                                                        <div class="fw-bold text-dark text-truncate">{{ $t->student_name }}</div>
                                                        <div class="text-muted mb-0.5" style="font-size: 0.62rem;">{{ $t->classroom_name }}</div>
                                                        <div class="text-danger fw-medium" style="font-size: 0.62rem;">
                                                            <i class="ti ti-alert-triangle me-0.5"></i> Alfa di: {{ Str::limit($t->alfa_subjects, 30) }} ({{ $t->alfa_count_jp }} JP)
                                                        </div>
                                                        <div class="text-success" style="font-size: 0.60rem;">
                                                            <i class="ti ti-check me-0.5"></i> Terdeteksi di: {{ Str::limit($t->present_subjects, 30) }}
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-dark text-white flex-shrink-0" style="font-size: 0.6rem;">Bolos KBM</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">Tidak ada indikasi siswa bolos KBM hari ini.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div><!-- /#siswaDetailContainer -->

                    @if($kbmMonitoring['siswa']['incidents_count'] > 0)
                        <div class="mt-2 p-1.5 rounded-2 bg-danger-lt border border-danger d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-1.5 overflow-hidden">
                                <i class="ti ti-shield-alert text-danger fs-3 flex-shrink-0"></i>
                                <span class="small fw-semibold text-danger text-truncate" style="font-size: 0.72rem;">
                                    {{ $kbmMonitoring['siswa']['incidents_count'] }} kasus pelanggaran siswa tercatat di meja piket
                                </span>
                            </div>
                            <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-danger rounded-pill px-2 py-0.5 flex-shrink-0" style="font-size: 0.65rem;">
                                Kasus
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif

<!-- DAFTAR JADWAL MENGAJAR SAYA (Hanya Tampil untuk Guru yang Punya Tugas Mengajar) -->
@if(!empty($hasTeachingDuty))
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between mb-1.5 px-1 gap-2">
        <span class="text-uppercase tracking-wider fw-bold text-muted small text-truncate" style="font-size: 0.72rem;">
            JADWAL HARI {{ strtoupper($dayName) }} ({{ $mySchedules->count() }} KELAS)
        </span>
        @if($isKbmDay && isset($allDaySchedules) && $allDaySchedules->isNotEmpty())
            <button type="button" class="btn btn-warning rounded-pill px-2.5 py-1 fw-bold flex-shrink-0 d-inline-flex align-items-center justify-content-center gap-1 shadow-none" onclick="openSubstituteScheduleSheet()" style="font-size: 0.68rem; line-height: 1; border: none; background-color: #fef3c7; color: #92400e; vertical-align: middle;">
                <i class="ti ti-arrows-left-right" style="font-size: 0.78rem; display: inline-flex; align-items: center; justify-content: center; line-height: 1;"></i>
                <span style="display: inline-block; line-height: 1;">Guru Pengganti</span>
            </button>
        @endif
    </div>

    @if($mySchedules->isEmpty())
        @if($isWeekend)
            <!-- Card Akhir Pekan (Sabtu / Minggu - KBM 5 Hari Kerja) -->
            <div class="card border-0 shadow-sm rounded-4 mb-2" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac !important;">
                <div class="card-body p-3 py-4 text-center d-flex flex-column align-items-center justify-content-center">
                    <div class="avatar avatar-lg bg-success text-white rounded-circle mb-2 shadow-sm" style="width: 48px; height: 48px;">
                        <i class="ti ti-sun fs-1"></i>
                    </div>
                    <span class="badge bg-success text-white rounded-pill px-3 py-1 mb-2 fw-bold" style="font-size: 0.7rem;">
                        AKHIR PEKAN &bull; TIDAK ADA KBM
                    </span>
                    <div class="fw-bold text-dark fs-3 lh-sm mb-1.5 text-center">
                        Selamat Berakhir Pekan!
                    </div>
                    <p class="small text-center mb-2" style="font-size: 0.76rem; max-width: 290px; line-height: 1.5; color: #166534;">
                        Sekolah menerapkan KBM 5 hari kerja (Senin &ndash; Jumat). Hari {{ $dayName }} tidak ada kegiatan belajar mengajar reguler.
                    </p>
                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mt-1">
                        <a href="{{ route('attendance.subject.history') }}" class="btn btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.72rem; background: #ffffff; color: #166534; border: 1.5px solid #86efac;">
                            <i class="ti ti-history me-1 text-success"></i> Riwayat KBM
                        </a>
                        <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.72rem; background: #ffffff; color: #195b9e; border: 1.5px solid #cbd5e1;">
                            <i class="ti ti-calendar me-1 text-primary"></i> Agenda Sekolah
                        </a>
                    </div>
                </div>
            </div>
        @elseif($isHoliday)
            <!-- Card Hari Libur Sekolah -->
            <div class="card border-0 shadow-sm rounded-4 mb-2" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border: 1.5px solid #fca5a5 !important;">
                <div class="card-body p-3 py-4 text-center d-flex flex-column align-items-center justify-content-center">
                    <div class="avatar avatar-lg bg-danger text-white rounded-circle mb-2 shadow-sm" style="width: 48px; height: 48px;">
                        <i class="ti ti-calendar-off fs-1"></i>
                    </div>
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 mb-2 fw-bold" style="font-size: 0.7rem;">
                        HARI LIBUR SEKOLAH
                    </span>
                    <div class="fw-bold text-dark fs-3 lh-sm mb-1.5 text-center">
                        {{ $holidayEvent?->title ?? 'Kegiatan Belajar Diliburkan' }}
                    </div>
                    <p class="small text-center mb-2" style="font-size: 0.76rem; max-width: 290px; line-height: 1.5; color: #991b1b;">
                        Hari {{ $dayName }}, {{ $date->translatedFormat('d F Y') }} adalah hari libur sekolah. Tidak ada kegiatan belajar mengajar di kelas.
                    </p>
                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mt-1">
                        <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.72rem; background: #ffffff; color: #b91c1c; border: 1.5px solid #fca5a5;">
                            <i class="ti ti-calendar me-1 text-danger"></i> Kalender Akademik
                        </a>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 mb-2 animated-coffee-card text-center py-4 px-3 d-flex flex-column align-items-center justify-content-center">
                <!-- Animasi Cangkir Kopi Melayang & Uap Halus (Posisi Center) -->
                <div class="position-relative mb-2.5 mt-1 d-inline-flex justify-content-center">
                    <span class="animated-coffee-cup">
                        <span class="coffee-steam-icon">
                            <i class="ti ti-flame"></i>
                        </span>
                        <i class="ti ti-coffee fs-1"></i>
                    </span>
                </div>

                <!-- Badge Rehat (Center) -->
                <div class="d-flex justify-content-center w-100 mb-2">
                    <span class="badge rounded-pill px-3 py-1 fw-bold" style="background: rgba(245, 158, 11, 0.18); color: #b45309; font-size: 0.72rem; letter-spacing: 0.02em;">
                        REHAT TATAP MUKA
                    </span>
                </div>

                <!-- Judul & Keterangan Apresiatif -->
                <div class="fw-bold text-dark fs-3 lh-sm mb-1.5 text-center" style="text-align: center !important;">
                    Waktunya Santai Sejenak! ☕
                </div>
                <p class="small text-center mb-3" style="font-size: 0.76rem; max-width: 290px; line-height: 1.5; color: #78716c; text-align: center !important; margin-left: auto !important; margin-right: auto !important;">
                    Hari {{ $dayName }} ini Anda tidak memiliki jadwal KBM reguler di kelas. Manfaatkan waktu untuk rehat, menyiapkan materi, atau koreksi tugas.
                </p>

                <!-- Tombol Pintasan Berguna -->
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <a href="{{ route('attendance.subject.history') }}" class="btn btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.72rem; background: #ffffff; color: #b45309; border: 1.5px solid #fde68a;">
                        <i class="ti ti-history me-1 text-warning"></i> Riwayat KBM
                    </a>
                    <a href="{{ route('kurikulum.calendar.index') }}" class="btn btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.72rem; background: #ffffff; color: #195b9e; border: 1.5px solid #cbd5e1;">
                        <i class="ti ti-calendar me-1 text-primary"></i> Agenda Sekolah
                    </a>
                </div>
            </div>
        @endif
    @else
        <div class="d-flex flex-column gap-1.5 mb-2">
            @foreach($mySchedules as $sched)
                @php
                    $session = $savedSessions->get($sched->id)
                        ?? ($savedSessionsBySlot->get("{$sched->classroom_id}_{$sched->start_period}") ?? null);
                    $eventImpact = $sched->getEventImpact($todayEvents ?? null);

                    // Evaluasi jendela waktu presensi (toleransi buka 15 menit sebelum jam mulai KBM)
                    $attendanceWindow = $sched->getAttendanceWindowStatus($date, 15);
                    $isTooEarly = ! $session && ! $attendanceWindow['is_open'];
                @endphp
                <div class="card border shadow-sm rounded-3 mb-2" style="background: #ffffff; border-color: #e2e8f0 !important; overflow: hidden;">
                    <div class="card-body p-3" style="box-sizing: border-box;">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="badge bg-blue-lt fw-bold px-2 py-0.5" style="font-size: 0.68rem;">
                                Jam {{ $sched->start_period }}-{{ $sched->end_period }} ({{ $sched->start_time ?: '-' }}-{{ $sched->end_time ?: '-' }})
                            </span>
                            @if($session)
                                <span class="badge bg-success text-white" style="font-size: 0.65rem; padding: 2px 6px;">
                                    <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                    @if($session->teacher_id && $session->teacher_id !== auth()->id())
                                        <span class="opacity-75">({{ Str::words($session->teacher?->name ?? 'Pengganti', 2, '') }})</span>
                                    @endif
                                </span>
                            @elseif($eventImpact)
                                <span class="badge {{ $eventImpact['badge_class'] }}" style="font-size: 0.65rem; padding: 2px 6px;">
                                    <i class="ti {{ $eventImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($eventImpact['type'] === 'daring' ? 'ti-device-laptop' : ($eventImpact['type'] === 'libur' ? 'ti-calendar-off' : 'ti-home-check')) }} me-0.5"></i> {{ $eventImpact['badge_text'] }}
                                </span>
                            @elseif($isTooEarly)
                                <span class="badge bg-secondary-lt text-secondary fw-semibold" style="font-size: 0.65rem; padding: 2px 6px;">
                                    <i class="ti ti-clock me-0.5"></i> Buka Pukul {{ $attendanceWindow['open_time'] ?? '-' }}
                                </span>
                            @else
                                <span class="badge bg-danger-lt text-danger fw-bold" style="font-size: 0.65rem; padding: 2px 6px;">
                                    <i class="ti ti-clock me-0.5"></i> Belum Diabsen
                                </span>
                            @endif
                        </div>

                        <div class="fw-bold text-dark fs-3 lh-sm mb-0.5">{{ $sched->classroom?->name }}</div>
                        <div class="text-primary fw-semibold small mb-2" style="font-size: 0.78rem;">
                            {{ $sched->subject?->name ?? 'Mata Pelajaran' }}
                        </div>

                        @if(!$session && $eventImpact)
                            <div class="p-2 rounded-2 mb-2.5 small {{ $eventImpact['type'] === 'bebas_kbm' ? 'bg-purple-lt border border-purple-subtle text-purple-emphasis' : ($eventImpact['type'] === 'daring' ? 'bg-info-lt border border-info-subtle text-info-emphasis' : 'bg-light text-muted') }}" style="font-size: 0.73rem; line-height: 1.35;">
                                <div class="fw-bold mb-0.5">
                                    <i class="ti {{ $eventImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($eventImpact['type'] === 'daring' ? 'ti-device-laptop' : 'ti-info-circle') }} me-1"></i>
                                    {{ $eventImpact['title'] }}
                                </div>
                                <div class="text-muted">{{ $eventImpact['note'] }}</div>
                            </div>
                        @endif

                        @if($session)
                            <!-- Rekap Absen Estetik & Rapi -->
                            <div class="d-flex align-items-center justify-content-between gap-1 p-1.5 px-2 rounded-2 mb-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #ecfdf5; border: 1px solid #d1fae5;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #059669;">H</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #047857;">{{ $session->hadir_count }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fefce8; border: 1px solid #fef08a;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #ca8a04;">T</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #a16207;">{{ $session->terlambat_count }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fff7ed; border: 1px solid #fed7aa;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #ea580c;">S</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #c2410c;">{{ $session->sakit_count }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #0284c7;">I</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #0369a1;">{{ $session->izin_count }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fef2f2; border: 1px solid #fecaca;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #dc2626;">A</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #b91c1c;">{{ $session->alpa_count }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #faf5ff; border: 1px solid #e9d5ff;">
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #7c3aed;">D</span>
                                    <span class="fw-bold" style="font-size: 0.75rem; color: #6d28d9;">{{ $session->dispen_count }}</span>
                                </div>
                            </div>

                            <!-- Navigasi Aksi: Edit Absen (Lurus di bawah H) & Lihat Absen (Lurus di bawah A/D) -->
                            <div class="d-flex align-items-center justify-content-between px-1" style="margin-top: 10px;">
                                <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Edit Absen">
                                    <i class="ti ti-edit fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                    <span>Edit Absen</span>
                                </a>
                                <a href="{{ route('attendance.subject.show', $session) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Lihat Lembar Absen">
                                    <i class="ti ti-eye fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                    <span>Lihat Absen</span>
                                </a>
                            </div>
                        @else
                            <div class="mt-2">
                                @if($eventImpact && $eventImpact['type'] === 'bebas_kbm')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-outline-purple fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-1.5 external" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 10px 16px !important; font-size: 0.86rem; text-decoration: none;">
                                        <i class="ti ti-sparkles fs-2"></i>
                                        <span>Catat Jurnal / Absen Kegiatan</span>
                                    </a>
                                @elseif($eventImpact && $eventImpact['type'] === 'daring')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-info fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-1.5 external text-white" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 10px 16px !important; font-size: 0.86rem; text-decoration: none;">
                                        <i class="ti ti-device-laptop fs-2"></i>
                                        <span>Masuk &amp; Absen KBM Daring</span>
                                    </a>
                                @elseif($isTooEarly)
                                    <button type="button" class="btn btn-secondary disabled fw-bold rounded-pill shadow-none d-flex align-items-center justify-content-center gap-1.5" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 10px 16px !important; font-size: 0.86rem; text-decoration: none; opacity: 0.65; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $attendanceWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                        <i class="ti ti-lock fs-2"></i>
                                        <span>Presensi Dibuka Pukul {{ $attendanceWindow['open_time'] }}</span>
                                    </button>
                                @else
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $sched->id, 'date' => $date->toDateString()]) }}" class="btn btn-primary fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-1.5 external" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 10px 16px !important; font-size: 0.88rem; text-decoration: none;">
                                        <i class="ti ti-checklist fs-2"></i>
                                        <span>Masuk &amp; Absen Kelas</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- SESI TUKAR JAM / GURU PENGGANTI (JIKA ADA) -->
    @if(isset($swapSessions) && $swapSessions->isNotEmpty())
        <div class="d-flex align-items-center justify-content-between mb-1.5 mt-3 px-1">
            <span class="text-uppercase tracking-wider fw-bold text-muted small" style="font-size: 0.72rem;">
                SESI GURU PENGGANTI / TUKAR JAM HARI INI
            </span>
        </div>
        <div class="d-flex flex-column gap-1.5 mb-2">
            @foreach($swapSessions as $swp)
                <div class="card border shadow-sm rounded-3 mb-2" style="border-left: 4px solid #f59e0b !important; border-color: #e2e8f0; overflow: hidden; background: #ffffff;">
                    <div class="card-body p-3" style="box-sizing: border-box;">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem;">
                                Jam {{ $swp->start_period }}-{{ $swp->end_period }} &bull; {{ ucfirst(str_replace('_', ' ', $swp->swap_type ?? 'tukar_jam')) }}
                            </span>
                            <span class="badge bg-success text-white" style="font-size: 0.65rem;">Selesai</span>
                        </div>
                        <div class="fw-bold text-dark fs-3 lh-sm mb-0.5">{{ $swp->classroom?->name }} &bull; {{ $swp->subject?->name }}</div>
                        <div class="text-muted small mt-0.5 mb-2" style="font-size: 0.72rem;">
                            Guru Terjadwal: {{ $swp->scheduledTeacher?->name ?? 'Jadwal Reguler' }}
                        </div>
                        <!-- Rekap Absen Estetik & Rapi -->
                        <div class="d-flex align-items-center justify-content-between gap-1 p-1.5 px-2 rounded-2 mb-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #ecfdf5; border: 1px solid #d1fae5;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #059669;">H</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #047857;">{{ $swp->hadir_count }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fefce8; border: 1px solid #fef08a;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #ca8a04;">T</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #a16207;">{{ $swp->terlambat_count }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fff7ed; border: 1px solid #fed7aa;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #ea580c;">S</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #c2410c;">{{ $swp->sakit_count }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #0284c7;">I</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #0369a1;">{{ $swp->izin_count }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fef2f2; border: 1px solid #fecaca;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #dc2626;">A</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #b91c1c;">{{ $swp->alpa_count }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #faf5ff; border: 1px solid #e9d5ff;">
                                <span style="font-size: 0.65rem; font-weight: 700; color: #7c3aed;">D</span>
                                <span class="fw-bold" style="font-size: 0.75rem; color: #6d28d9;">{{ $swp->dispen_count }}</span>
                            </div>
                        </div>
                        <!-- Navigasi Aksi: Edit Absen (Lurus di bawah H) & Lihat Absen (Lurus di bawah A/D) -->
                        <div class="d-flex align-items-center justify-content-between px-1" style="margin-top: 10px;">
                            <a href="{{ route('attendance.subject.take', ['schedule_id' => $swp->lesson_schedule_id, 'date' => $swp->attendance_date->toDateString(), 'classroom_id' => $swp->classroom_id, 'subject_id' => $swp->subject_id, 'start_period' => $swp->start_period, 'end_period' => $swp->end_period, 'is_swap' => 1]) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Edit Absen">
                                <i class="ti ti-edit fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                <span>Edit Absen</span>
                            </a>
                            <a href="{{ route('attendance.subject.show', $swp) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Lihat Lembar Absen">
                                <i class="ti ti-eye fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                <span>Lihat Absen</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@else
    @if($isKbmDay)
        <!-- TAMPILAN UNTUK KS / KURIKULUM / GURU TANPA JADWAL RUTIN HARI INI (HANYA PADA HARI ADA KBM) -->
        <div class="mb-3">
            @if(isset($swapSessions) && $swapSessions->isNotEmpty())
                <!-- SESI GURU PENGGANTI YANG SUDAH DIABSEN HARI INI -->
                <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
                    <span class="text-uppercase tracking-wider fw-bold text-muted small" style="font-size: 0.72rem;">
                        SESI GURU PENGGANTI / INVAL HARI INI
                    </span>
                    <button type="button" class="btn btn-warning rounded-pill px-2.5 py-1 fw-bold flex-shrink-0 d-inline-flex align-items-center justify-content-center gap-1 shadow-none" onclick="openSubstituteScheduleSheet()" style="font-size: 0.68rem; line-height: 1; border: none; background-color: #fef3c7; color: #92400e; vertical-align: middle;">
                        <i class="ti ti-arrows-left-right" style="font-size: 0.78rem; display: inline-flex; align-items: center; justify-content: center; line-height: 1;"></i>
                        <span style="display: inline-block; line-height: 1;">Gantikan Kelas Lagi</span>
                    </button>
                </div>
                <div class="d-flex flex-column gap-1.5 mb-2">
                    @foreach($swapSessions as $swp)
                        <div class="card border shadow-sm rounded-3 mb-2" style="border-left: 4px solid #f59e0b !important; border-color: #e2e8f0; overflow: hidden; background: #ffffff;">
                            <div class="card-body p-3" style="box-sizing: border-box;">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem;">
                                        Jam {{ $swp->start_period }}-{{ $swp->end_period }} &bull; {{ ucfirst(str_replace('_', ' ', $swp->swap_type ?? 'inval')) }}
                                    </span>
                                    <span class="badge bg-success text-white" style="font-size: 0.65rem;">Selesai</span>
                                </div>
                                <div class="fw-bold text-dark fs-3 lh-sm mb-0.5">{{ $swp->classroom?->name }} &bull; {{ $swp->subject?->name }}</div>
                                <div class="text-muted small mt-0.5 mb-2" style="font-size: 0.72rem;">
                                    Guru Terjadwal: {{ $swp->scheduledTeacher?->name ?? 'Jadwal Reguler' }}
                                </div>
                                <!-- Rekap Absen Estetik & Rapi -->
                                <div class="d-flex align-items-center justify-content-between gap-1 p-1.5 px-2 rounded-2 mb-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #ecfdf5; border: 1px solid #d1fae5;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #059669;">H</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #047857;">{{ $swp->hadir_count }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fefce8; border: 1px solid #fef08a;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #ca8a04;">T</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #a16207;">{{ $swp->terlambat_count }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fff7ed; border: 1px solid #fed7aa;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #ea580c;">S</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #c2410c;">{{ $swp->sakit_count }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #0284c7;">I</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #0369a1;">{{ $swp->izin_count }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #fef2f2; border: 1px solid #fecaca;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #dc2626;">A</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #b91c1c;">{{ $swp->alpa_count }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-fill justify-content-center py-0.5 rounded" style="background: #faf5ff; border: 1px solid #e9d5ff;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #7c3aed;">D</span>
                                        <span class="fw-bold" style="font-size: 0.75rem; color: #6d28d9;">{{ $swp->dispen_count }}</span>
                                    </div>
                                </div>
                                <!-- Navigasi Aksi: Edit Absen (Lurus di bawah H) & Lihat Absen (Lurus di bawah A/D) -->
                                <div class="d-flex align-items-center justify-content-between px-1" style="margin-top: 10px;">
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $swp->lesson_schedule_id, 'date' => $swp->attendance_date->toDateString(), 'classroom_id' => $swp->classroom_id, 'subject_id' => $swp->subject_id, 'start_period' => $swp->start_period, 'end_period' => $swp->end_period, 'is_swap' => 1]) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Edit Absen">
                                        <i class="ti ti-edit fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                        <span>Edit Absen</span>
                                    </a>
                                    <a href="{{ route('attendance.subject.show', $swp) }}" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1.5 fw-semibold external py-2 px-1 rounded-2" style="font-size: 0.8rem; line-height: 1;" title="Lihat Lembar Absen">
                                        <i class="ti ti-eye fs-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                                        <span>Lihat Absen</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
                    <span class="text-uppercase tracking-wider fw-bold text-muted small" style="font-size: 0.72rem;">
                        TUGAS MENGAJAR PENGGANTI / INVAL
                    </span>
                </div>
                <div class="card border shadow-sm rounded-3 p-3 text-center mb-2" style="border-color: #e2e8f0 !important; background: #ffffff;">
                    <div class="avatar avatar-md bg-warning-lt rounded-circle mx-auto mb-2 text-warning" style="width: 44px; height: 44px;">
                        <i class="ti ti-arrows-left-right fs-2"></i>
                    </div>
                    <div class="fw-bold text-dark fs-3 mb-1">Gantikan Kelas Kosong</div>
                    <div class="badge bg-warning-lt text-warning fw-bold px-2.5 py-1 mb-2" style="font-size: 0.72rem;">
                        <i class="ti ti-calendar me-1"></i> {{ $dayName }}, {{ $date->translatedFormat('d F Y') }}
                    </div>
                    <p class="text-muted small mb-2.5" style="font-size: 0.75rem;">
                        Anda tidak memiliki jadwal KBM rutin pada tanggal ini. Anda dapat masuk dan mengabsen kelas guru lain yang berhalangan hadir sebagai guru inval / pengganti.
                    </p>
                    <button type="button" class="btn btn-warning w-100 fw-bold rounded-pill py-2 shadow-sm d-flex align-items-center justify-content-center gap-1.5" onclick="openSubstituteScheduleSheet()" style="font-size: 0.86rem; box-sizing: border-box !important;">
                        <i class="ti ti-search fs-2"></i>
                        <span>Pilih Kelas Kosong ({{ $date->translatedFormat('d M') }})</span>
                    </button>
                </div>
            @endif
        </div>
    @elseif($isWeekend)
        <!-- AKHIR PEKAN (SABTU & MINGGU - KBM 5 HARI KERJA) -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac !important;">
            <div class="card-body p-3 py-4 text-center d-flex flex-column align-items-center justify-content-center">
                <div class="avatar avatar-lg bg-success text-white rounded-circle mb-2 shadow-sm" style="width: 48px; height: 48px;">
                    <i class="ti ti-sun fs-1"></i>
                </div>
                <span class="badge bg-success text-white rounded-pill px-3 py-1 mb-2 fw-bold" style="font-size: 0.7rem;">
                    AKHIR PEKAN &bull; TIDAK ADA KBM
                </span>
                <div class="fw-bold text-dark fs-3 lh-sm mb-1.5 text-center">
                    Selamat Berakhir Pekan!
                </div>
                <p class="small text-center mb-0" style="font-size: 0.76rem; max-width: 290px; line-height: 1.5; color: #166534;">
                    Sekolah menerapkan KBM 5 hari kerja (Senin &ndash; Jumat). Hari {{ $dayName }} tidak ada kegiatan belajar mengajar reguler maupun kelas kosong.
                </p>
            </div>
        </div>
    @elseif($isHoliday)
        <!-- HARI LIBUR SEKOLAH -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border: 1.5px solid #fca5a5 !important;">
            <div class="card-body p-3 py-4 text-center d-flex flex-column align-items-center justify-content-center">
                <div class="avatar avatar-lg bg-danger text-white rounded-circle mb-2 shadow-sm" style="width: 48px; height: 48px;">
                    <i class="ti ti-calendar-off fs-1"></i>
                </div>
                <span class="badge bg-danger text-white rounded-pill px-3 py-1 mb-2 fw-bold" style="font-size: 0.7rem;">
                    HARI LIBUR SEKOLAH
                </span>
                <div class="fw-bold text-dark fs-3 lh-sm mb-1.5 text-center">
                    {{ $holidayEvent?->title ?? 'Kegiatan Belajar Diliburkan' }}
                </div>
                <p class="small text-center mb-0" style="font-size: 0.76rem; max-width: 290px; line-height: 1.5; color: #991b1b;">
                    Hari {{ $dayName }}, {{ $date->translatedFormat('d F Y') }} adalah hari libur sekolah. Tidak ada kegiatan belajar mengajar maupun penugasan guru pengganti.
                </p>
            </div>
        </div>
    @endif
@endif
@endsection

@if($isKbmDay)
@push('modals')
<!-- MODAL BOTTOM SHEET: PILIH JADWAL GURU LAIN UNTUK DIGANTIKAN (INVAL / TUKAR JAM / KS / KURIKULUM) -->
<div class="sheet-modal sheet-substitute-picker" id="sheet-substitute-picker" data-swipe-to-close="false" style="height: 85vh !important; max-height: 85vh !important; border-radius: 20px 20px 0 0; background: #ffffff; overflow: hidden !important;">
    <div class="sheet-modal-inner" style="height: 100% !important; max-height: 85vh !important; overflow-y: auto !important; -webkit-overflow-scrolling: touch !important; touch-action: pan-y !important; overscroll-behavior-y: contain !important;">
        
        <!-- Sticky Top: Header & Search Bar Tetap di Atas Saat Scroll -->
        <div class="sticky-top" style="position: sticky; top: 0; z-index: 30; background: #ffffff;">
            <!-- Header Standar Seragam Sesuai Modal SIAP -->
            <div class="d-flex align-items-center justify-content-between px-3.5 py-3 border-bottom" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.95) 0%, rgba(25, 91, 158, 0.9) 100%); border-radius: 20px 20px 0 0; z-index: 10; min-height: 60px;">
                <div class="d-flex align-items-center gap-2.5">
                    <span class="avatar avatar-sm bg-white text-warning rounded-circle flex-shrink-0" style="width: 36px; height: 36px;">
                        <i class="ti ti-arrows-left-right fs-2"></i>
                    </span>
                    <div>
                        <div class="fw-bold text-white fs-3 lh-sm">Gantikan Guru / Kelas Kosong</div>
                        <div class="text-white-50 small mt-0.5" style="font-size: 0.74rem;">
                            <i class="ti ti-calendar me-0.5"></i> {{ $dayName }}, {{ $date->translatedFormat('d F Y') }}
                        </div>
                    </div>
                </div>
                <a href="javascript:void(0)" class="sheet-close text-white opacity-75 text-decoration-none d-inline-flex align-items-center justify-content-center p-0" style="font-size: 1.8rem; width: 34px; height: 34px; line-height: 1;" onclick="closeSubstituteScheduleSheet(); return false;">&times;</a>
            </div>

            <!-- Search & Info Bar (Fixed below header) -->
            <div class="p-3 pb-2 border-bottom shadow-sm" style="background: #f8fafc;">
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="ti ti-search text-muted"></i>
                    </span>
                    <input type="text" id="searchSubstituteInput" class="form-control border-start-0 bg-white" placeholder="Cari nama kelas, mapel, atau guru..." oninput="filterSubstituteCards()">
                </div>

                <!-- Filter Tab Kapsul: Lebar 100% Dibagi 2 Sama Rata (50% : 50%) Menggunakan Grid 1fr 1fr -->
                <div style="display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 8px !important; width: 100% !important; box-sizing: border-box !important;">
                    @if(!empty($activeCurrentPeriod))
                        <button type="button" class="btn btn-sm rounded-pill fw-bold shadow-none substitute-tab-btn active" id="btnFilterCurrentPeriod" onclick="setSubstitutePeriodFilter('current')" style="width: 100% !important; min-width: 0 !important; height: 34px !important; box-sizing: border-box !important; padding: 6px 4px !important; font-size: 0.72rem; line-height: 1.1; background: #195b9e; color: #ffffff; border: 1.5px solid #195b9e; text-align: center; display: flex; align-items: center; justify-content: center;">
                            <span class="text-truncate" style="max-width: 100%;">Jam ke-{{ $activeCurrentPeriod }} (Sekarang)</span>
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill fw-bold shadow-none substitute-tab-btn" id="btnFilterAllPeriod" onclick="setSubstitutePeriodFilter('all')" style="width: 100% !important; min-width: 0 !important; height: 34px !important; box-sizing: border-box !important; padding: 6px 4px !important; font-size: 0.72rem; line-height: 1.1; background: #ffffff; color: #64748b; border: 1.5px solid #cbd5e1; text-align: center; display: flex; align-items: center; justify-content: center;">
                            <span class="text-truncate" style="max-width: 100%;">Semua Jam</span>
                        </button>
                    @else
                        <button type="button" class="btn btn-sm rounded-pill fw-bold shadow-none substitute-tab-btn disabled" id="btnFilterCurrentPeriod" style="width: 100% !important; min-width: 0 !important; height: 34px !important; box-sizing: border-box !important; padding: 6px 4px !important; font-size: 0.72rem; line-height: 1.1; background: #f1f5f9; color: #94a3b8; border: 1.5px solid #e2e8f0; text-align: center; display: flex; align-items: center; justify-content: center; cursor: default;">
                            <span class="text-truncate" style="max-width: 100%;">Di Luar Jam KBM</span>
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill fw-bold shadow-none substitute-tab-btn active" id="btnFilterAllPeriod" onclick="setSubstitutePeriodFilter('all')" style="width: 100% !important; min-width: 0 !important; height: 34px !important; box-sizing: border-box !important; padding: 6px 4px !important; font-size: 0.72rem; line-height: 1.1; background: #195b9e; color: #ffffff; border: 1.5px solid #195b9e; text-align: center; display: flex; align-items: center; justify-content: center;">
                            <span class="text-truncate" style="max-width: 100%;">Semua Jam</span>
                        </button>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between text-muted small mt-1.5 px-0.5" style="font-size: 0.68rem;">
                    @if(!empty($activeCurrentPeriod))
                        <span><i class="ti ti-clock text-primary me-0.5"></i> Jam sekarang: <strong>Jam ke-{{ $activeCurrentPeriod }}</strong></span>
                    @else
                        <span><i class="ti ti-info-circle text-primary me-0.5"></i> Di luar jam KBM reguler</span>
                    @endif
                    <span id="substituteVisibleCountBadge" class="text-muted" style="font-size: 0.68rem;">
                        @if(!empty($activeCurrentPeriod))
                            {{ $allDaySchedules->filter(fn($s) => $activeCurrentPeriod >= $s->start_period && $activeCurrentPeriod <= $s->end_period)->count() }} Jadwal
                        @else
                            {{ $allDaySchedules->count() }} Jadwal
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- Daftar Jadwal Kelas Lain Hari Ini (Scrollable) -->
        <div class="p-3 pt-2.5" style="padding-bottom: calc(3.5rem + env(safe-area-inset-bottom, 24px)) !important;">
            <div id="substituteScheduleList" class="d-flex flex-column gap-2">
                @if(isset($allDaySchedules) && $allDaySchedules->isNotEmpty())
                    @php
                        // Sort: Belum diabsen jam sekarang paling atas, lalu belum diabsen jam lain, lalu bebas kbm, lalu yang sudah diabsen di bawah
                        $sortedOtherSchedules = $allDaySchedules->sortBy(function($item) use ($todayAttendedScheduleIds, $todayAttendedSlots, $activeCurrentPeriod, $todayEvents) {
                            $isCurrent = ($activeCurrentPeriod && $activeCurrentPeriod >= $item->start_period && $activeCurrentPeriod <= $item->end_period) ? 0 : 1;
                            $isAttended = (in_array($item->id, $todayAttendedScheduleIds ?? []) || in_array("{$item->classroom_id}_{$item->start_period}", $todayAttendedSlots ?? [])) ? 1 : 0;
                            $itemImpact = $item->getEventImpact($todayEvents ?? null);
                            $isExempted = ($itemImpact && in_array($itemImpact['type'], ['bebas_kbm', 'libur'])) ? 1 : 0;

                            if ($isAttended) {
                                return 100 + $isCurrent * 10;
                            }
                            if ($isExempted) {
                                return 50 + $isCurrent * 10;
                            }
                            return $isCurrent * 10;
                        });
                    @endphp
                    @foreach($sortedOtherSchedules as $otherSched)
                        @php
                            $slotKey = "{$otherSched->classroom_id}_{$otherSched->start_period}";
                            $isAttended = in_array($otherSched->id, $todayAttendedScheduleIds ?? []) || in_array($slotKey, $todayAttendedSlots ?? []);
                            $attendedByName = $attendedTeacherByScheduleId[$otherSched->id] ?? ($attendedTeacherBySlot[$slotKey] ?? null);
                            $isCurrentSlot = ($activeCurrentPeriod && $activeCurrentPeriod >= $otherSched->start_period && $activeCurrentPeriod <= $otherSched->end_period);

                            $subImpact = $otherSched->getEventImpact($todayEvents ?? null);
                            $subWindow = $otherSched->getAttendanceWindowStatus($date, 15);
                            $isTooEarlyOther = ! $isAttended && ! $subImpact && ! $subWindow['is_open'];
                        @endphp
                        <div class="card border shadow-none rounded-3 p-2.5 substitute-item-card" 
                             data-start-period="{{ $otherSched->start_period }}" 
                             data-end-period="{{ $otherSched->end_period }}" 
                             data-is-current="{{ $isCurrentSlot ? '1' : '0' }}" 
                             data-search="{{ strtolower($otherSched->classroom?->name . ' ' . $otherSched->subject?->name . ' ' . ($otherSched->teacher?->name ?? '') . ' ' . ($subImpact['title'] ?? '') . ' ' . ($subImpact['badge_text'] ?? '')) }}" 
                             style="border-color: {{ $isCurrentSlot ? '#3b82f6' : '#e2e8f0' }} !important; background: {{ $isCurrentSlot ? '#f8fafc' : '#ffffff' }}; {{ $isCurrentSlot ? 'border-left: 4px solid #195b9e !important;' : '' }} {{ (!empty($activeCurrentPeriod) && !$isCurrentSlot) ? 'display: none;' : '' }}">
                            
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge {{ $isCurrentSlot ? 'bg-primary text-white' : 'bg-blue-lt' }} fw-bold px-2 py-0.5" style="font-size: 0.66rem;">
                                        Jam {{ $otherSched->start_period }}-{{ $otherSched->end_period }} ({{ $otherSched->start_time ?: '-' }}-{{ $otherSched->end_time ?: '-' }})
                                    </span>
                                    @if($isCurrentSlot)
                                        <span class="badge bg-success text-white fw-bold px-1.5 py-0.5" style="font-size: 0.60rem;">
                                            <i class="ti ti-radar me-0.5"></i> Jam Berjalan
                                        </span>
                                    @endif
                                </div>
                                @if($isAttended)
                                    <span class="badge bg-success-lt text-success" style="font-size: 0.62rem; padding: 2px 6px;">
                                        <i class="ti ti-check me-0.5"></i> Sudah Diabsen{{ $attendedByName ? ' (' . $attendedByName . ')' : '' }}
                                    </span>
                                @elseif($subImpact)
                                    <span class="badge {{ $subImpact['badge_class'] }}" style="font-size: 0.62rem; padding: 2px 6px;">
                                        <i class="ti {{ $subImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($subImpact['type'] === 'daring' ? 'ti-device-laptop' : ($subImpact['type'] === 'libur' ? 'ti-calendar-off' : 'ti-home-check')) }} me-0.5"></i> {{ $subImpact['badge_text'] }}
                                    </span>
                                @elseif($isTooEarlyOther)
                                    <span class="badge bg-secondary-lt text-secondary fw-semibold" style="font-size: 0.62rem; padding: 2px 6px;">
                                        <i class="ti ti-clock me-0.5"></i> Buka Pukul {{ $subWindow['open_time'] ?? '-' }}
                                    </span>
                                @else
                                    <span class="badge bg-warning-lt text-warning fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">
                                        <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                    </span>
                                @endif
                            </div>

                            <div class="fw-bold text-dark fs-3 lh-sm mb-0.5">
                                {{ $otherSched->classroom?->name }} &bull; <span class="text-primary">{{ $otherSched->subject?->name }}</span>
                            </div>
                            <div class="text-muted small mb-2" style="font-size: 0.72rem;">
                                <span>Guru Terjadwal: <strong>{{ $otherSched->teacher?->name ?? '-' }}</strong></span>
                            </div>

                            @if(!$isAttended && $subImpact)
                                <div class="p-1.5 px-2 rounded-2 mb-2 small {{ $subImpact['type'] === 'bebas_kbm' ? 'bg-purple-lt border border-purple-subtle text-purple-emphasis' : ($subImpact['type'] === 'daring' ? 'bg-info-lt border border-info-subtle text-info-emphasis' : 'bg-light text-muted') }}" style="font-size: 0.70rem; line-height: 1.3;">
                                    <i class="ti {{ $subImpact['type'] === 'bebas_kbm' ? 'ti-sparkles' : ($subImpact['type'] === 'daring' ? 'ti-device-laptop' : 'ti-info-circle') }} me-1"></i>
                                    <strong>{{ $subImpact['title'] }}</strong> &ndash; {{ $subImpact['note'] }}
                                </div>
                            @endif

                            <div class="d-flex align-items-center gap-2">
                                @if($isAttended)
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'inval']) }}" class="btn btn-outline-secondary flex-fill fw-bold rounded-pill py-1.5 external d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="font-size: 0.8rem; box-sizing: border-box !important;">
                                        <i class="ti ti-arrows-left-right fs-3"></i>
                                        <span>Buka / Edit Presensi Kelas</span>
                                    </a>
                                @elseif($subImpact && $subImpact['type'] === 'bebas_kbm')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'inval']) }}" class="btn btn-outline-purple flex-fill fw-bold rounded-pill py-1.5 external d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="font-size: 0.8rem; box-sizing: border-box !important;">
                                        <i class="ti {{ Str::contains(strtolower($subImpact['title']), 'pkl') ? 'ti-briefcase' : 'ti-sparkles' }} fs-3"></i>
                                        <span>{{ Str::contains(strtolower($subImpact['title']), 'pkl') ? 'Bebas KBM (Siswa PKL)' : 'Catat Jurnal Kegiatan' }}</span>
                                    </a>
                                @elseif($subImpact && $subImpact['type'] === 'daring')
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'inval']) }}" class="btn btn-info flex-fill fw-bold rounded-pill py-1.5 external d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-white" style="font-size: 0.8rem; box-sizing: border-box !important;">
                                        <i class="ti ti-device-laptop fs-3"></i>
                                        <span>Gantikan KBM Daring</span>
                                    </a>
                                @elseif($isTooEarlyOther)
                                    <button type="button" class="btn btn-secondary disabled flex-fill fw-bold rounded-pill py-1.5 shadow-none d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.8rem; box-sizing: border-box !important; opacity: 0.65; cursor: not-allowed;" onclick="alert('Presensi kelas baru dapat dibuka mulai pukul {{ $subWindow['open_time'] }} (15 menit sebelum jam pelajaran dimulai).')">
                                        <i class="ti ti-lock fs-3"></i>
                                        <span>Buka Pukul {{ $subWindow['open_time'] }}</span>
                                    </button>
                                @else
                                    <a href="{{ route('attendance.subject.take', ['schedule_id' => $otherSched->id, 'date' => $date->toDateString(), 'is_swap' => 1, 'swap_type' => 'inval']) }}" class="btn btn-success flex-fill fw-bold rounded-pill py-1.5 external d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="font-size: 0.8rem; box-sizing: border-box !important; background-color: #16a34a !important; border-color: #16a34a !important; color: #ffffff !important;">
                                        <i class="ti ti-arrows-left-right fs-3"></i>
                                        <span>Gantikan & Absen Kelas Ini</span>
                                    </a>
                                @endif
                                <span class="text-muted small text-nowrap flex-shrink-0" style="font-size: 0.72rem;">
                                    <i class="ti ti-calendar me-0.5 text-primary"></i>{{ $date->translatedFormat('d M') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="ti ti-calendar-off fs-1 d-block mb-1 opacity-50"></i>
                        <div class="small fw-semibold">Tidak ada jadwal kelas lain pada hari {{ $dayName }}.</div>
                    </div>
                @endif
            </div>

            <!-- Empty Search Result -->
            <div id="substituteSearchEmpty" class="text-center py-4 text-muted" style="display: none;">
                <i class="ti ti-search-off fs-1 d-block mb-1 opacity-50"></i>
                <div class="small fw-semibold" id="substituteSearchEmptyText">Tidak ada kelas atau guru yang cocok.</div>
            </div>
        </div>
    </div>
</div>
@endpush
@endif

@push('scripts')
<script>
    const activeMonitoringState = {
        gtk: null,
        siswa: null
    };

    const monitoringTitles = {
        gtk: {
            present: 'GTK Hadir Hari Ini',
            late: 'GTK Terlambat Masuk Hari Ini',
            permit: 'GTK Izin / Cuti Hari Ini',
            awaiting: 'GTK Belum Absen Masuk Hari Ini',
            unattended: 'Jadwal Belum Absen Mapel Hari Ini'
        },
        siswa: {
            present: 'Rekap Siswa Hadir Hari Ini',
            izin: 'Siswa Izin Hari Ini',
            sakit: 'Siswa Sakit Hari Ini',
            late: 'Siswa Terlambat Datang Hari Ini',
            pulang: 'Siswa Izin Pulang Cepat Hari Ini',
            alfa: 'Siswa Alfa Mapel Hari Ini',
            truancy: 'Siswa Indikasi Bolos KBM Hari Ini'
        }
    };

    function toggleMonitoringDetail(group, key) {
        const container = document.getElementById(group + 'DetailContainer');
        const titleEl = document.getElementById(group + 'DetailTitle');
        if (!container) return;

        // Jika chip yang sama diklik lagi, tutup detailnya
        if (activeMonitoringState[group] === key) {
            closeMonitoringDetail(group);
            return;
        }

        // Buka container
        container.style.display = 'block';
        activeMonitoringState[group] = key;

        // Update Judul
        if (titleEl && monitoringTitles[group] && monitoringTitles[group][key]) {
            titleEl.innerHTML = '<i class="ti ti-list-details text-primary"></i> ' + monitoringTitles[group][key];
        }

        // Tampilkan pane yang sesuai, sembunyikan pane lain
        const panes = container.querySelectorAll('.' + group + '-detail-pane');
        panes.forEach(pane => {
            pane.style.display = 'none';
        });

        const targetPane = document.getElementById(group + 'Pane-' + key);
        if (targetPane) {
            targetPane.style.display = 'block';
        }

        // Beri visual highlight border pada chip yang aktif
        document.querySelectorAll('[id^="chip-' + group + '-"]').forEach(chip => {
            chip.style.boxShadow = 'none';
            chip.style.transform = 'none';
        });

        const activeChip = document.getElementById('chip-' + group + '-' + key);
        if (activeChip) {
            activeChip.style.boxShadow = '0 0 0 2px #195b9e, 0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            activeChip.style.transform = 'translateY(-1px)';
        }

        // Scroll halus ke container jika di layar kecil
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closeMonitoringDetail(group) {
        const container = document.getElementById(group + 'DetailContainer');
        if (container) {
            container.style.display = 'none';
        }
        activeMonitoringState[group] = null;

        document.querySelectorAll('[id^="chip-' + group + '-"]').forEach(chip => {
            chip.style.boxShadow = 'none';
            chip.style.transform = 'none';
        });
    }

    // Kontrol Sheet Modal: Gantikan Guru / Kelas Kosong
    function openSubstituteScheduleSheet() {
        const hasActivePeriod = {{ !empty($activeCurrentPeriod) ? 'true' : 'false' }};
        if (hasActivePeriod) {
            setSubstitutePeriodFilter('current');
        } else {
            setSubstitutePeriodFilter('all');
        }

        const sheetEl = document.querySelector('#sheet-substitute-picker');
        if (sheetEl) {
            const inner = sheetEl.querySelector('.sheet-modal-inner');
            if (inner) inner.scrollTop = 0;
        }

        if (typeof app !== 'undefined' && app.sheet) {
            let sheetInstance = app.sheet.get('#sheet-substitute-picker');
            if (!sheetInstance) {
                sheetInstance = app.sheet.create({
                    el: '#sheet-substitute-picker',
                    swipeToClose: false,
                    backdrop: true,
                    closeByBackdropClick: true
                });
            }
            sheetInstance.open();
        } else if (window.f7 && window.f7.sheet) {
            let sheetInstance = window.f7.sheet.get('#sheet-substitute-picker');
            if (!sheetInstance) {
                sheetInstance = window.f7.sheet.create({
                    el: '#sheet-substitute-picker',
                    swipeToClose: false,
                    backdrop: true,
                    closeByBackdropClick: true
                });
            }
            sheetInstance.open();
        } else {
            if (sheetEl) sheetEl.classList.add('modal-in');
        }
    }

    function closeSubstituteScheduleSheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            const sheetInstance = app.sheet.get('#sheet-substitute-picker');
            if (sheetInstance) {
                sheetInstance.close();
            } else {
                app.sheet.close('#sheet-substitute-picker');
            }
        } else if (window.f7 && window.f7.sheet) {
            const sheetInstance = window.f7.sheet.get('#sheet-substitute-picker');
            if (sheetInstance) {
                sheetInstance.close();
            } else {
                window.f7.sheet.close('#sheet-substitute-picker');
            }
        } else {
            const el = document.getElementById('sheet-substitute-picker');
            if (el) el.classList.remove('modal-in');
        }
    }

    // Mode filter periode saat ini: 'current' (jam sekarang) atau 'all' (semua jam)
    let currentPeriodFilterMode = '{{ !empty($activeCurrentPeriod) ? "current" : "all" }}';

    function setSubstitutePeriodFilter(mode) {
        currentPeriodFilterMode = mode;

        const btnCurrent = document.getElementById('btnFilterCurrentPeriod');
        const btnAll = document.getElementById('btnFilterAllPeriod');
        const hasActivePeriod = {{ !empty($activeCurrentPeriod) ? 'true' : 'false' }};

        if (mode === 'current') {
            if (btnCurrent) {
                btnCurrent.style.background = '#195b9e';
                btnCurrent.style.color = '#ffffff';
                btnCurrent.style.borderColor = '#195b9e';
                btnCurrent.classList.add('active');
            }
            if (btnAll) {
                btnAll.style.background = '#ffffff';
                btnAll.style.color = '#64748b';
                btnAll.style.borderColor = '#cbd5e1';
                btnAll.classList.remove('active');
            }
        } else {
            if (btnCurrent) {
                if (!hasActivePeriod) {
                    btnCurrent.style.background = '#f1f5f9';
                    btnCurrent.style.color = '#94a3b8';
                    btnCurrent.style.borderColor = '#e2e8f0';
                } else {
                    btnCurrent.style.background = '#ffffff';
                    btnCurrent.style.color = '#64748b';
                    btnCurrent.style.borderColor = '#cbd5e1';
                }
                btnCurrent.classList.remove('active');
            }
            if (btnAll) {
                btnAll.style.background = '#195b9e';
                btnAll.style.color = '#ffffff';
                btnAll.style.borderColor = '#195b9e';
                btnAll.classList.add('active');
            }
        }

        filterSubstituteCards();
    }

    function filterSubstituteCards() {
        const queryInput = document.getElementById('searchSubstituteInput');
        const q = (queryInput ? queryInput.value : '').toLowerCase().trim();
        const cards = document.querySelectorAll('.substitute-item-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const searchText = card.getAttribute('data-search') || '';
            const isCurrent = card.getAttribute('data-is-current') === '1';

            // Cek filter jam
            let matchesPeriod = true;
            if (currentPeriodFilterMode === 'current') {
                matchesPeriod = isCurrent;
            }

            // Cek pencarian teks
            const matchesText = !q || searchText.includes(q);

            if (matchesPeriod && matchesText) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Update badge total yang terlihat
        const countBadge = document.getElementById('substituteVisibleCountBadge');
        if (countBadge) {
            countBadge.textContent = visibleCount + ' Jadwal';
        }

        const emptyMsg = document.getElementById('substituteSearchEmpty');
        const emptyMsgText = document.getElementById('substituteSearchEmptyText');
        if (emptyMsg) {
            if (visibleCount === 0 && cards.length > 0) {
                emptyMsg.style.display = 'block';
                if (emptyMsgText) {
                    if (currentPeriodFilterMode === 'current' && !q) {
                        emptyMsgText.textContent = 'Tidak ada jadwal kelas lain pada jam pelajaran saat ini. Ketuk "Semua Jam" untuk melihat jam lainnya.';
                    } else {
                        emptyMsgText.textContent = 'Tidak ada kelas atau guru yang cocok dengan pencarian.';
                    }
                }
            } else {
                emptyMsg.style.display = 'none';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        filterSubstituteCards();
    });
</script>
@endpush

