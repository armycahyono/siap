@extends('layouts.mobile')

@section('title', 'Tugas GTK - SIAP SMK PGRI KRAS')
@section('page_title', 'Tugas dan Tanggung Jawab')
@section('page_subtitle', auth()->user()?->name)
@section('back_url', 'back')

@push('styles')
<style>
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
        margin-top: 10px !important;
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

@if(! ($user->isPrincipal() || $user->isSuperAdmin()))
<!-- KARTU IZIN & CUTI SAYA (UNTUK SEMUA GTK: DILETAKKAN PALING ATAS DENGAN WARNA ORANGE SOFT) -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: #fffaf0; border: 1.5px solid #fed7aa !important;">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom" style="border-color: #ffedd5 !important;">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rounded-circle flex-shrink-0" style="width: 34px; height: 34px; background: #ea580c; color: #ffffff;">
                    <i class="ti ti-file-text fs-2"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark fs-3 lh-1">Izin &amp; Cuti Saya</div>
                    <div class="text-muted small mt-0.5" style="font-size: 0.7rem; color: #9a3412 !important;">Pengajuan Izin / Cuti GTK Mandiri</div>
                </div>
            </div>
        </div>

        @if(!empty($myGtkPermitToday))
            <div class="p-2 rounded-2 mb-2.5 d-flex align-items-center justify-content-between" style="background: #fff7ed; border: 1px solid #fdba74;">
                <div class="small fw-semibold" style="font-size: 0.75rem; color: #c2410c;">
                    <strong>Status Hari Ini:</strong> {{ $myGtkPermitToday->permit_type_label }}
                </div>
                <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.6rem;">Aktif</span>
            </div>
        @endif

        <div style="display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; width: 100% !important;">
            <a href="{{ route('attendance.gtk-permits.create') }}" 
               class="btn rounded-pill fw-bold text-white text-decoration-none d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-truncate py-2" 
               style="font-size: 0.74rem; min-height: 38px; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%) !important; border: 1.5px solid #ea580c !important;">
                <i class="ti ti-plus fs-3 flex-shrink-0 text-white"></i> 
                <span class="text-truncate text-white">Ajukan Izin / Cuti Baru</span>
            </a>
            <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'personal']) }}" 
               class="btn rounded-pill fw-bold text-decoration-none d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-truncate py-2" 
               style="font-size: 0.74rem; min-height: 38px; background: #ffffff !important; border: 1.5px solid #fed7aa !important; color: #c2410c !important;">
                <i class="ti ti-history fs-3 flex-shrink-0" style="color: #c2410c !important;"></i> 
                <span class="text-truncate" style="color: #c2410c !important;">Riwayat Saya</span>
            </a>
        </div>
    </div>
</div>
@endif

@php
    $hasPiketAccess = !empty($isPiketToday) || (!empty($myWeeklyPiketSchedules) && $myWeeklyPiketSchedules->isNotEmpty()) || $user->canManagePiketSchedules() || $user->isSuperAdmin();
@endphp

@if($hasPiketAccess)
<!-- 1. KARTU PIKET SEKOLAH -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="border: 1.5px solid #a855f7 !important;">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-purple text-white shadow-sm rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="ti ti-shield-check fs-2"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark fs-3 lh-1">Kartu Piket Sekolah</div>
                    <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">
                        @if(!empty($isPiketToday))
                            Pos: {{ $todayPiketSchedule?->duty_location ?: 'Meja Piket Utama' }} ({{ $todayPiketSchedule?->role === 'koordinator' ? 'Koordinator' : 'Anggota' }})
                        @elseif(!empty($myWeeklyPiketSchedules) && $myWeeklyPiketSchedules->isNotEmpty())
                            Jadwal Anda: <strong>{{ $myWeeklyPiketSchedules->pluck('day')->implode(', ') }}</strong> ({{ $myWeeklyPiketSchedules->first()?->duty_location ?: 'Meja Piket Utama' }})
                        @else
                            Operasional Gerbang, Inval &amp; Tamu
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1">
                @if(!empty($isPiketToday))
                    <span class="badge bg-purple text-white" style="font-size: 0.6rem; padding: 2px 5px;">Aktif</span>
                    <span class="badge bg-purple-lt text-purple" style="font-size: 0.6rem; padding: 2px 5px;">Hari Ini</span>
                @elseif(!empty($myWeeklyPiketSchedules) && $myWeeklyPiketSchedules->isNotEmpty())
                    <span class="badge bg-purple-lt text-purple" style="font-size: 0.6rem; padding: 2px 6px;">
                        {{ $myWeeklyPiketSchedules->pluck('day')->implode(', ') }}
                    </span>
                @else
                    <span class="badge bg-purple-lt text-purple" style="font-size: 0.6rem; padding: 2px 5px;">Piket</span>
                @endif
            </div>
        </div>

        <!-- Menu Aksi Meja Piket (Grid 2 Kolom) -->
        <div class="mobile-grid-2">
            {{-- 1. Meja Piket --}}
            <a href="{{ route('piket.dashboard') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-layout-dashboard fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Meja Piket</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Dashboard Guru Piket</span>
            </a>

            {{-- 2. Jurnal Piket --}}
            <a href="{{ route('piket.journals.index') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-notebook fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Jurnal Piket</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Laporan Harian Piket</span>
            </a>

            {{-- 3. Izin Siswa --}}
            <a href="{{ route('piket.permits.create') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-clipboard-plus fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Izin Siswa</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Catat Masuk / Pulang</span>
            </a>

            {{-- 4. Buku Tamu --}}
            <a href="{{ route('piket.guests.index') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-users fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Buku Tamu</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Kunjungan Sekolah</span>
            </a>

            {{-- 5. Jadwal Piket --}}
            <a href="{{ route('piket.schedules.index') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-calendar-user fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Jadwal Piket</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Petugas Senin - Sabtu</span>
            </a>

            {{-- 6. Tukar Jadwal --}}
            <a href="{{ route('piket.swaps.index') }}" class="card card-link text-decoration-none p-2.5 rounded-2 d-flex flex-column align-items-center text-center shadow-none" style="background: #f0f7ff !important; border: 1.5px solid #3b82f6 !important;">
                <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 28px; height: 28px;">
                    <i class="ti ti-arrows-left-right fs-3"></i>
                </span>
                <span class="fw-bold text-dark fs-4 lh-sm">Tukar Jadwal</span>
                <span class="text-muted small mt-0.5" style="font-size: 0.65rem;">Ganti Shift Piket</span>
            </a>
        </div>
    </div>
</div>
@endif

<!-- 2. KARTU TUGAS WALI KELAS -->
@if(!empty($myHomeroomClassroom))
<div class="card border-0 shadow-sm rounded-3 mb-2" style="border: 1.5px solid #f59e0b !important;">
    <div class="card-body p-3">
        <!-- Header Kartu -->
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="ti ti-heart-handshake fs-2"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark fs-3 lh-1">Tugas Wali Kelas</div>
                    <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">Pendampingan Siswa Rombel</div>
                </div>
            </div>
            <span class="badge bg-warning-lt text-warning fw-bold" style="font-size: 0.65rem; padding: 2px 7px;">
                {{ $myHomeroomClassroom->name }}
            </span>
        </div>

        <!-- Pemilih Tanggal (< Hari Ini / Tanggal >) - Frameless Arrows -->
        <div class="p-1.5 px-2 rounded-2 mb-2 bg-light border d-flex align-items-center justify-content-between gap-1">
            <a href="{{ route('tasks', ['date' => $prevDate]) }}" class="text-secondary text-decoration-none d-inline-flex align-items-center justify-content-center p-1 rounded-circle" style="width: 28px; height: 28px; transition: background 0.15s ease;" title="Hari Sebelumnya">
                <i class="ti ti-chevron-left fs-2"></i>
            </a>

            <div class="d-flex align-items-center justify-content-center gap-1.5 flex-fill text-center overflow-hidden">
                <i class="ti ti-calendar text-primary fs-3 flex-shrink-0"></i>
                <span class="fw-bold text-dark small text-truncate" style="font-size: 0.78rem;">
                    {{ $dayName }}, {{ $date->translatedFormat('d M Y') }}
                </span>
                @if($date->isToday())
                    <span class="badge bg-success-lt text-success fw-bold flex-shrink-0" style="font-size: 0.58rem; padding: 1px 5px;">Hari Ini</span>
                @else
                    <a href="{{ route('tasks') }}" class="badge bg-primary-lt text-primary text-decoration-none fw-bold flex-shrink-0" style="font-size: 0.58rem; padding: 1px 5px;" title="Kembali ke Hari Ini">
                        Ke Hari Ini
                    </a>
                @endif
            </div>

            <a href="{{ route('tasks', ['date' => $nextDate]) }}" class="text-secondary text-decoration-none d-inline-flex align-items-center justify-content-center p-1 rounded-circle" style="width: 28px; height: 28px; transition: background 0.15s ease;" title="Hari Berikutnya">
                <i class="ti ti-chevron-right fs-2"></i>
            </a>
        </div>

        <!-- LIVE MONITORING SISWA KELAS PERWALIAN (8 STATUS LENGKAP DARI SEMUA SUMBER KBM & PIKET) -->
        <div class="mb-2">
            @if(($homeroomLiveMonitoring['session_count'] ?? 0) === 0)
                <div class="text-warning fw-semibold mb-1.5 d-flex align-items-center gap-1" style="font-size: 0.68rem;">
                    <i class="ti ti-clock-pause"></i> Presensi KBM belum diisi guru mapel
                </div>
            @endif

            <!-- Pill Statistik Siswa Rombel (2 Baris x 4 Chip Lengkap) -->
            <div class="d-flex flex-column gap-1">
                <!-- Baris 1: Hadir, Telat, Sakit, Izin -->
                <div class="monitoring-chip-strip">
                    <div class="monitoring-pill-item bg-success-lt border border-success" 
                         onclick="toggleHomeroomMonitoringDetail('present')" id="chip-homeroom-present">
                        <span class="monitoring-pill-num text-success">{{ $homeroomLiveMonitoring['hadir_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Hadir</span>
                    </div>
                    <div class="monitoring-pill-item bg-warning-lt border border-warning" 
                         onclick="toggleHomeroomMonitoringDetail('late')" id="chip-homeroom-late">
                        <span class="monitoring-pill-num text-warning">{{ $homeroomLiveMonitoring['late_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Telat</span>
                    </div>
                    <div class="monitoring-pill-item bg-azure-lt border border-azure" 
                         onclick="toggleHomeroomMonitoringDetail('sakit')" id="chip-homeroom-sakit">
                        <span class="monitoring-pill-num text-azure">{{ $homeroomLiveMonitoring['sakit_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Sakit</span>
                    </div>
                    <div class="monitoring-pill-item bg-info-lt border border-info" 
                         onclick="toggleHomeroomMonitoringDetail('izin')" id="chip-homeroom-izin">
                        <span class="monitoring-pill-num text-info">{{ $homeroomLiveMonitoring['izin_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Izin</span>
                    </div>
                </div>

                <!-- Baris 2: Dispen, Pulang, Alfa, Bolos -->
                <div class="monitoring-chip-strip">
                    <div class="monitoring-pill-item bg-indigo-lt border border-indigo" 
                         onclick="toggleHomeroomMonitoringDetail('dispen')" id="chip-homeroom-dispen">
                        <span class="monitoring-pill-num text-indigo">{{ $homeroomLiveMonitoring['dispen_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Dispen</span>
                    </div>
                    <div class="monitoring-pill-item bg-purple-lt border border-purple" 
                         onclick="toggleHomeroomMonitoringDetail('pulang')" id="chip-homeroom-pulang">
                        <span class="monitoring-pill-num text-purple">{{ $homeroomLiveMonitoring['pulang_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Pulang</span>
                    </div>
                    <div class="monitoring-pill-item bg-danger-lt border border-danger" 
                         onclick="toggleHomeroomMonitoringDetail('alfa')" id="chip-homeroom-alfa">
                        <span class="monitoring-pill-num text-danger">{{ $homeroomLiveMonitoring['alfa_count'] ?? 0 }}</span>
                        <span class="monitoring-pill-lbl text-muted">Alfa</span>
                    </div>
                    @php
                        $homeroomTruancyCount = (int) ($homeroomLiveMonitoring['truancy_count'] ?? 0);
                    @endphp
                    <div class="monitoring-pill-item {{ $homeroomTruancyCount > 0 ? 'bg-white border border-danger shadow-xs' : 'bg-dark-lt border border-dark' }}" 
                         onclick="toggleHomeroomMonitoringDetail('truancy')" id="chip-homeroom-truancy">
                        <span class="monitoring-pill-num {{ $homeroomTruancyCount > 0 ? 'text-danger fw-bold' : 'text-dark' }}">{{ $homeroomTruancyCount }}</span>
                        <span class="monitoring-pill-lbl {{ $homeroomTruancyCount > 0 ? 'text-danger fw-bold' : 'text-muted' }}">Bolos</span>
                    </div>
                </div>
            </div>

            <!-- Detail Data Siswa Kelas Perwalian (Muncul saat Pill diklik) -->
            <div class="text-start monitoring-detail-wrapper" id="homeroomDetailContainer" style="display: none;">
                <div class="card monitoring-detail-card mb-0 overflow-hidden">
                    <div class="card-header p-1.5 px-2 bg-light d-flex align-items-center justify-content-between border-bottom">
                        <span class="fw-bold small text-dark d-flex align-items-center gap-1" id="homeroomDetailTitle" style="font-size: 0.72rem;">
                            <i class="ti ti-list-details text-indigo"></i> Detail Siswa {{ $myHomeroomClassroom->name }}
                        </span>
                        <span class="cursor-pointer text-muted px-1" role="button" aria-label="Tutup" onclick="closeHomeroomMonitoringDetail()" style="font-size: 0.85rem; line-height: 1; cursor: pointer;">
                            <i class="ti ti-x"></i>
                        </span>
                    </div>
                    <div class="card-body p-2" style="max-height: 220px; overflow-y: auto;">
                        <!-- Detail: Hadir -->
                        <div id="homeroomPane-present" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['present_records']) && count($homeroomLiveMonitoring['present_records']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @php
                                        $uniquePresentStudents = collect($homeroomLiveMonitoring['present_records'])->unique('student_id');
                                    @endphp
                                    @foreach($uniquePresentStudents as $r)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-success-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Mapel: {{ $r->session?->subject?->name ?? 'KBM Aktif' }}</div>
                                            </div>
                                            <span class="badge bg-success text-white flex-shrink-0" style="font-size: 0.6rem;">Hadir</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Belum ada siswa tercatat hadir di mapel hari ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Sakit -->
                        <div id="homeroomPane-sakit" class="homeroom-detail-pane" style="display: none;">
                            @php
                                $hasHomeroomSakit = (!empty($homeroomLiveMonitoring['sakit_permits']) && count($homeroomLiveMonitoring['sakit_permits']) > 0)
                                    || (!empty($homeroomLiveMonitoring['sakit_records']) && count($homeroomLiveMonitoring['sakit_records']) > 0);
                            @endphp
                            @if($hasHomeroomSakit)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['sakit_permits'] as $p)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-azure-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">{{ Str::limit($p->reason ?? 'Surat Sakit', 28) }}</div>
                                            </div>
                                            <span class="badge bg-azure-lt text-azure flex-shrink-0" style="font-size: 0.6rem;">Surat Sakit</span>
                                        </div>
                                    @endforeach
                                    @foreach($homeroomLiveMonitoring['sakit_records'] as $r)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-azure-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Mapel: {{ $r->session?->subject?->name }}</div>
                                            </div>
                                            <span class="badge bg-azure text-white flex-shrink-0" style="font-size: 0.6rem;">Sakit</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa sakit pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Izin Murni -->
                        <div id="homeroomPane-izin" class="homeroom-detail-pane" style="display: none;">
                            @php
                                $hasHomeroomIzin = (!empty($homeroomLiveMonitoring['izin_permits']) && count($homeroomLiveMonitoring['izin_permits']) > 0)
                                    || (!empty($homeroomLiveMonitoring['izin_records']) && count($homeroomLiveMonitoring['izin_records']) > 0);
                            @endphp
                            @if($hasHomeroomIzin)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['izin_permits'] as $p)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-info-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">{{ Str::limit($p->reason ?? 'Izin', 28) }}</div>
                                            </div>
                                            <span class="badge bg-info-lt text-info flex-shrink-0" style="font-size: 0.6rem;">Surat Izin</span>
                                        </div>
                                    @endforeach
                                    @foreach($homeroomLiveMonitoring['izin_records'] as $r)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-info-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Mapel: {{ $r->session?->subject?->name }}</div>
                                            </div>
                                            <span class="badge bg-info text-white flex-shrink-0" style="font-size: 0.6rem;">Izin</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa izin pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Dispensasi -->
                        <div id="homeroomPane-dispen" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['dispen_records']) && count($homeroomLiveMonitoring['dispen_records']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['dispen_records'] as $r)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-indigo-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Mapel: {{ $r->session?->subject?->name }}</div>
                                            </div>
                                            <span class="badge bg-indigo text-white flex-shrink-0" style="font-size: 0.6rem;">Dispen</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa dispensasi pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Terlambat -->
                        <div id="homeroomPane-late" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['late_permits']) && count($homeroomLiveMonitoring['late_permits']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['late_permits'] as $p)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Jam ke-{{ $p->period_assigned ?? '-' }} &bull; {{ Str::limit($p->reason ?? 'Terlambat', 25) }}</div>
                                            </div>
                                            <span class="badge bg-warning-lt text-warning flex-shrink-0" style="font-size: 0.6rem;">Terlambat</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa terlambat tercatat pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Pulang Cepat -->
                        <div id="homeroomPane-pulang" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['pulang_permits']) && count($homeroomLiveMonitoring['pulang_permits']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['pulang_permits'] as $p)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $p->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">{{ Str::limit($p->reason ?? 'Izin Pulang', 28) }}</div>
                                            </div>
                                            <span class="badge bg-purple-lt text-purple flex-shrink-0" style="font-size: 0.6rem;">Pulang Cepat</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted small py-2" style="font-size: 0.7rem;">Tidak ada siswa izin pulang pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Alfa -->
                        <div id="homeroomPane-alfa" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['alfa_records']) && count($homeroomLiveMonitoring['alfa_records']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['alfa_records'] as $r)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border border-danger-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-semibold text-dark text-truncate">{{ $r->student?->name ?? 'Siswa' }}</div>
                                                <div class="text-muted" style="font-size: 0.62rem;">Mapel: {{ $r->session?->subject?->name }}</div>
                                            </div>
                                            <span class="badge bg-danger text-white flex-shrink-0" style="font-size: 0.6rem;">Alfa</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">Tidak ada siswa alfa pada tanggal ini.</div>
                            @endif
                        </div>

                        <!-- Detail: Bolos KBM -->
                        <div id="homeroomPane-truancy" class="homeroom-detail-pane" style="display: none;">
                            @if(!empty($homeroomLiveMonitoring['truancy_list']) && count($homeroomLiveMonitoring['truancy_list']) > 0)
                                <div class="d-flex flex-column gap-1">
                                    @foreach($homeroomLiveMonitoring['truancy_list'] as $t)
                                        <div class="d-flex align-items-start justify-content-between p-1.5 bg-white rounded border border-dark-subtle small" style="font-size: 0.68rem;">
                                            <div class="overflow-hidden pe-1">
                                                <div class="fw-bold text-dark text-truncate">{{ $t->student_name }}</div>
                                                <div class="text-danger fw-medium" style="font-size: 0.62rem;">
                                                    <i class="ti ti-alert-triangle me-0.5"></i> Alfa di: {{ Str::limit($t->alfa_subjects, 28) }} ({{ $t->alfa_count_jp }} JP)
                                                </div>
                                                <div class="text-success" style="font-size: 0.60rem;">
                                                    <i class="ti ti-check me-0.5"></i> Terdeteksi di: {{ Str::limit($t->present_subjects, 28) }}
                                                </div>
                                            </div>
                                            <span class="badge bg-dark text-white flex-shrink-0" style="font-size: 0.6rem;">Bolos KBM</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-success small py-2 fw-semibold" style="font-size: 0.7rem;">Tidak ada indikasi siswa bolos KBM pada tanggal ini.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div><!-- /#homeroomDetailContainer -->
        </div>

        <!-- LOG & CATATAN KEJADIAN SISWA HARI INI (PELANGGARAN, BK, TERLAMBAT, PULANG, BOLOS) -->
        @php
            $studentEvents = $homeroomLiveMonitoring['student_events'] ?? collect();
            $eventCount = $studentEvents->count();
        @endphp
        <div class="mt-2 mb-2">
            <div class="d-flex align-items-center justify-content-between mb-1.5 px-0.5">
                <span class="text-uppercase tracking-wider fw-bold text-muted small d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;">
                    <i class="ti ti-activity text-primary"></i> Catatan &amp; Kejadian Siswa ({{ $eventCount }})
                </span>
                @if($eventCount > 0)
                    <span class="badge bg-warning-lt text-warning fw-bold" style="font-size: 0.62rem;">Perlu Perhatian</span>
                @else
                    <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.62rem;">Kondusif</span>
                @endif
            </div>

            @if($eventCount > 0)
                <div class="d-flex flex-column gap-1.5">
                    @foreach($studentEvents as $ev)
                        <div class="card border shadow-none rounded-2 p-2 mb-0" style="background: #ffffff; border-color: #e2e8f0 !important;">
                            <div class="d-flex align-items-start justify-content-between gap-1.5 mb-1">
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark fs-4 lh-sm text-truncate">{{ $ev['student_name'] }}</div>
                                    <div class="text-muted small d-inline-flex align-items-center gap-1 mt-0.5" style="font-size: 0.65rem;">
                                        @if($ev['time'] !== '-')
                                            <i class="ti ti-clock text-secondary"></i> {{ $ev['time'] }} WIB &bull;
                                        @endif
                                        <span>{{ $ev['title'] }}</span>
                                    </div>
                                </div>
                                <span class="badge {{ $ev['badge_class'] }} flex-shrink-0" style="font-size: 0.62rem;">
                                    {{ $ev['badge'] }}
                                </span>
                            </div>
                            <div class="p-1.5 rounded-2 bg-light small text-dark mb-1" style="font-size: 0.68rem; line-height: 1.4;">
                                <div><strong>Keterangan:</strong> {{ $ev['description'] }}</div>
                                @if(!empty($ev['action']))
                                    <div class="text-muted mt-0.5"><strong>Tindakan:</strong> {{ $ev['action'] }}</div>
                                @endif
                                @if(!empty($ev['note']))
                                    <div class="text-danger mt-0.5"><strong>Catatan:</strong> {{ $ev['note'] }}</div>
                                @endif
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.62rem;">
                                <span><i class="ti ti-user-check me-0.5"></i> Dicatat: {{ $ev['officer'] }}</span>
                                @if(!empty($ev['photo']))
                                    <a href="{{ $ev['photo'] }}" target="_blank" class="text-primary text-decoration-none fw-semibold d-inline-flex align-items-center gap-0.5 external">
                                        <i class="ti ti-photo"></i> Bukti Foto
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-2.5 rounded-2 text-center text-muted bg-light border border-dashed" style="font-size: 0.72rem;">
                    <i class="ti ti-circle-check text-success fs-2 d-block mb-1"></i>
                    Tidak ada catatan insiden, panggilan BK, maupun dispensasi untuk siswa kelas ini pada tanggal terpilih.
                </div>
            @endif
        </div>

        <!-- Garis Pembatas Antara Monitoring Presensi & Tombol Aksi -->
        <hr class="my-2.5" style="border-top: 1px solid #e2e8f0; opacity: 1;">

        <!-- Tombol Aksi Wali Kelas (Biru untuk Catat Izin & Hijau untuk Rekap Absen) -->
        <div style="display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; width: 100% !important;">
            <a href="{{ route('attendance.homeroom-permits.index', ['classroom_id' => $myHomeroomClassroom->id, 'date' => $date->toDateString()]) }}" 
               class="btn btn-primary rounded-pill fw-bold text-white text-decoration-none d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-truncate py-2" 
               style="font-size: 0.74rem; min-height: 38px; color: #ffffff !important; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; border: 1.5px solid #195b9e !important;">
                <i class="ti ti-file-certificate fs-3 flex-shrink-0 text-white"></i> 
                <span class="text-truncate text-white">Izin Siswa</span>
            </a>
            <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $myHomeroomClassroom->id, 'date' => $date->toDateString()]) }}" 
               class="btn btn-success rounded-pill fw-bold text-white text-decoration-none d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-truncate py-2" 
               style="font-size: 0.74rem; min-height: 38px; color: #ffffff !important; background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important; border: 1.5px solid #10b981 !important;">
                <i class="ti ti-clipboard-list fs-3 flex-shrink-0 text-white"></i> 
                <span class="text-truncate text-white">Rekap Absen Kelas</span>
            </a>
        </div>
    </div>
</div>
@endif

@if($user->isPrincipal() || $user->isWakaKurikulum() || $user->isSuperAdmin())
<!-- 3. KARTU DAFTAR IZIN & CUTI GTK (KEPALA SEKOLAH & WAKA KURIKULUM) -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="border: 1.5px solid #195b9e !important;">
    <div class="card-body p-3">
        <!-- Header Kartu -->
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 34px; height: 34px; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); box-shadow: 0 2px 6px rgba(25, 91, 158, 0.3);">
                    <i class="ti ti-file-certificate fs-2" style="font-size: 1.25rem;"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark fs-3 lh-1">Daftar Izin &amp; Cuti GTK</div>
                    <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">{{ $user->isPrincipal() ? 'Persetujuan & Monitoring Seluruh GTK' : 'Monitoring Perizinan Seluruh GTK' }}</div>
                </div>
            </div>
        </div>

        @if(($user->isPrincipal() || $user->isSuperAdmin()) && $pendingGtkPermits->isNotEmpty())
        <!-- 1. BAGIAN: BUTUH PERSETUJUAN KEPALA SEKOLAH (HANYA MUNCUL PADA KEPALA SEKOLAH JIKA ADA PERMINTAAN IZIN) -->
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1.5">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="badge bg-warning text-dark fw-bold rounded-pill" style="font-size: 0.65rem; padding: 2px 8px;">
                        <i class="ti ti-clock-pause me-0.5"></i> Perlu Persetujuan
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">({{ $pendingGtkPermits->count() }})</span>
                </div>
                <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage', 'status' => 'pending']) }}" class="text-warning small fw-bold text-decoration-none" style="font-size: 0.68rem;">
                    Proses Semua &raquo;
                </a>
            </div>

            <div class="d-flex flex-column gap-1.5">
                @foreach($pendingGtkPermits as $permit)
                    @php
                        $typeBadgeClass = match($permit->permission_type) {
                            'sakit' => 'bg-warning-lt text-dark',
                            'cuti' => 'bg-purple-lt text-purple',
                            'dinas_luar' => 'bg-blue-lt text-primary',
                            default => 'bg-info-lt text-info',
                        };
                        $typeIcon = match($permit->permission_type) {
                            'sakit' => 'ti-first-aid-kit',
                            'cuti' => 'ti-calendar-event',
                            'dinas_luar' => 'ti-briefcase',
                            default => 'ti-file-text',
                        };
                        $typeLabel = match($permit->permission_type) {
                            'sakit' => 'Sakit',
                            'cuti' => 'Cuti',
                            'dinas_luar' => 'Dinas Luar',
                            default => 'Izin',
                        };
                    @endphp
                    <div class="p-2 rounded-2 border d-flex flex-column gap-1" style="background: #fffbeb; border-color: #fde68a !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-1.5 overflow-hidden pe-1">
                                <span class="avatar avatar-xs rounded-circle flex-shrink-0 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px; background: rgba(0,0,0,0.05);">
                                    <i class="ti {{ $typeIcon }} text-dark" style="font-size: 0.75rem;"></i>
                                </span>
                                <span class="fw-bold text-dark fs-4 lh-sm text-truncate">
                                    {{ $permit->user?->name ?? 'GTK' }}
                                </span>
                            </div>
                            <span class="badge {{ $typeBadgeClass }} flex-shrink-0" style="font-size: 0.62rem; padding: 2px 6px;">
                                <i class="ti {{ $typeIcon }} me-0.5"></i> {{ $typeLabel }} ({{ $permit->duration_days }}h)
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between text-muted small" style="font-size: 0.7rem;">
                            <span class="text-truncate" style="max-width: 65%;">
                                <i class="ti ti-calendar me-0.5"></i>
                                @if($permit->start_date && $permit->end_date && $permit->start_date->ne($permit->end_date))
                                    {{ $permit->start_date->format('d/m') }} - {{ $permit->end_date->format('d/m/Y') }}
                                @else
                                    {{ $permit->start_date?->format('d/m/Y') }}
                                @endif
                            </span>
                            <span class="text-muted">{{ $permit->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-dark small text-truncate" style="font-size: 0.72rem; line-height: 1.25;">
                            <em>"{{ $permit->reason }}"</em>
                        </div>
                        <!-- Aksi Cepat Setujui / Tinjau -->
                        <div class="d-flex align-items-center justify-content-between pt-1 border-top border-warning-subtle mt-0.5">
                            @if($permit->document_path)
                                <a href="{{ $permit->document_url }}" target="_blank" class="badge bg-white text-primary border text-decoration-none py-0.5 px-1.5" style="font-size: 0.62rem;">
                                    <i class="ti ti-paperclip"></i> Bukti
                                </a>
                            @else
                                <span class="text-muted" style="font-size: 0.65rem;">Tanpa lampiran</span>
                            @endif
                            <div class="d-flex gap-1.5">
                                @if($user->isPrincipal() || $user->isSuperAdmin())
                                    <form id="form-approve-permit-{{ $permit->id }}" action="{{ route('attendance.gtk-permits.approve', $permit) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="button" class="btn btn-sm btn-success py-0.5 px-2.5 rounded-pill fw-bold" style="font-size: 0.68rem;" onclick="confirmInApp('Setujui Pengajuan Izin', 'Apakah Anda yakin ingin menyetujui izin {{ addslashes($permit->user?->name ?? 'GTK') }}?', function() { document.getElementById('form-approve-permit-{{ $permit->id }}').submit(); })">
                                            <i class="ti ti-check me-0.5"></i> Setujui
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage', 'status' => 'pending']) }}" class="btn btn-sm btn-outline-secondary py-0.5 px-2 rounded-pill fw-semibold" style="font-size: 0.68rem;">
                                    Tinjau
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- 2. BAGIAN: GTK IZIN / CUTI HARI INI -->
        <div>
            <div class="d-flex align-items-center justify-content-between mb-1.5">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="badge bg-blue-lt text-primary fw-bold rounded-pill" style="font-size: 0.65rem; padding: 2px 8px;">
                        <i class="ti ti-calendar-user me-0.5"></i> Izin Hari Ini ({{ \Carbon\Carbon::today()->translatedFormat('d M') }})
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">({{ $todayApprovedGtkPermits->count() }} GTK)</span>
                </div>
                <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage', 'date' => now()->toDateString()]) }}" class="text-primary small fw-bold text-decoration-none" style="font-size: 0.68rem;">
                    Lihat Rekap &raquo;
                </a>
            </div>

            @if($todayApprovedGtkPermits->isNotEmpty())
                <div class="d-flex flex-column gap-1">
                    @foreach($todayApprovedGtkPermits as $permit)
                        @php
                            $todayBadgeClass = match($permit->permission_type) {
                                'sakit' => 'bg-warning text-dark',
                                'cuti' => 'bg-purple text-white',
                                'dinas_luar' => 'bg-primary text-white',
                                default => 'bg-info text-white',
                            };
                            $todayIcon = match($permit->permission_type) {
                                'sakit' => 'ti-first-aid-kit',
                                'cuti' => 'ti-calendar-event',
                                'dinas_luar' => 'ti-briefcase',
                                default => 'ti-file-text',
                            };
                            $todayLabel = match($permit->permission_type) {
                                'sakit' => 'Sakit',
                                'cuti' => 'Cuti',
                                'dinas_luar' => 'Dinas Luar',
                                default => 'Izin',
                            };
                        @endphp
                        <div class="p-1.5 px-2 rounded-2 bg-light border d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-1.5 overflow-hidden pe-2">
                                <span class="avatar avatar-xs rounded-circle flex-shrink-0 d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px; background: #e2e8f0;">
                                    <i class="ti {{ $todayIcon }} text-dark" style="font-size: 0.8rem;"></i>
                                </span>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark fs-4 lh-sm text-truncate">
                                        {{ $permit->user?->name ?? 'GTK' }}
                                    </div>
                                    <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                                        {{ $permit->reason ?: ($permit->user?->position ?: 'Guru/Staf') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge {{ $todayBadgeClass }}" style="font-size: 0.62rem; padding: 2px 6px;">
                                    {{ $todayLabel }}
                                </span>
                                <div class="text-muted small" style="font-size: 0.62rem;">
                                    {{ $permit->duration_days }} Hari
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-2 rounded-2 text-center text-muted small bg-light" style="font-size: 0.74rem;">
                    <i class="ti ti-users-group text-primary me-1"></i> Tidak ada GTK yang tercatat izin atau cuti hari ini (Semua bertugas).
                </div>
            @endif
        </div>
    </div>
</div>
@endif

@if(!empty($pendingSupervisions) && $pendingSupervisions->isNotEmpty())
<!-- KARTU PENGESAHAN SUPERVISI AKADEMIK (KEPALA SEKOLAH & KURIKULUM) -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="border: 1.5px solid #206bc4 !important;">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rounded-circle flex-shrink-0 text-white" style="width: 34px; height: 34px; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);">
                    <i class="ti ti-certificate fs-2" style="font-size: 1.25rem;"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark fs-3 lh-1">Pengesahan Supervisi</div>
                    <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">Menunggu Pengesahan KS / Kurikulum</div>
                </div>
            </div>
            <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem; padding: 2px 7px;">
                {{ $pendingSupervisions->count() }} Berkas
            </span>
        </div>

        <div class="d-flex flex-column gap-2">
            @foreach($pendingSupervisions as $supervision)
                <div class="rounded-3 border overflow-hidden bg-white shadow-sm" style="border-color: #cbd5e1 !important; border-radius: 0.75rem !important;">
                    <!-- Bagian Konten Informasi Supervisi -->
                    <div class="p-2.5 pb-2">
                        <!-- 1. Header Guru & Mapel -->
                        <div class="mb-1.5">
                            <div class="fw-bold text-dark fs-3 lh-sm text-truncate">
                                {{ $supervision->teacher?->name ?? 'Guru Pengajar' }}
                            </div>
                            <div class="text-muted small text-truncate" style="font-size: 0.72rem;">
                                {{ $supervision->subject ?: 'Mata Pelajaran' }} &bull; {{ $supervision->class_name ?: 'Kelas' }}
                            </div>
                        </div>

                        <!-- 2. Info Supervisor & Predikat / Nilai -->
                        <div class="d-flex align-items-center justify-content-between pt-1 border-top border-light text-muted" style="font-size: 0.68rem;">
                            <span class="text-truncate">
                                <i class="ti ti-user-check me-0.5 text-primary"></i> Supervisor: {{ $supervision->supervisor?->name ?? '-' }}
                            </span>
                            <span class="fw-semibold text-dark flex-shrink-0">
                                @if(!empty($supervision->predicate))
                                    Predikat: <span class="badge bg-success-lt text-success" style="font-size: 0.62rem; padding: 2px 6px;">{{ $supervision->predicate }}</span>
                                @elseif($supervision->percentage)
                                    Nilai: <span class="badge bg-blue-lt text-primary" style="font-size: 0.62rem; padding: 2px 6px;">{{ $supervision->percentage }}%</span>
                                @else
                                    <span class="text-muted">Observasi Selesai</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- 3. Split Action Bar (Presisi 50:50 Menempel di Bawah Kartu dengan Sudut Melengkung Selaras) -->
                    <div class="d-flex border-top m-0 p-0" style="border-color: #e2e8f0 !important; width: 100%; border-radius: 0 0 0.75rem 0.75rem !important; overflow: hidden !important;">
                        <!-- Tombol Revisi (Kiri - Presisi 50% & Sudut Bawah Kiri Melengkung) -->
                        <button type="button" 
                                class="btn text-dark fw-bold d-inline-flex align-items-center justify-content-center gap-1 border-0 m-0 py-2 px-2" 
                                style="flex: 0 0 50% !important; width: 50% !important; max-width: 50% !important; box-sizing: border-box !important; height: 38px; font-size: 0.76rem; background: #fffdf5; border-right: 1px solid #e2e8f0 !important; border-radius: 0 0 0 0.75rem !important; transition: background 0.15s ease;"
                                onclick="openSupervisionRevisionSheet({{ $supervision->id }}, '{{ addslashes($supervision->teacher?->name ?? 'Guru') }}', '{{ addslashes($supervision->subject ?? 'Mapel') }}')">
                            <i class="ti ti-rotate-2 text-warning fs-3"></i>
                            <span class="text-dark fw-bold">Revisi</span>
                        </button>

                        <!-- Tombol Sahkan (Kanan - Presisi 50% & Sudut Bawah Kanan Melengkung) -->
                        <form id="form-approve-supervision-{{ $supervision->id }}" 
                              action="{{ route('approvals.approve', $supervision) }}" 
                              method="POST" 
                              class="m-0 p-0 d-flex" 
                              style="flex: 0 0 50% !important; width: 50% !important; max-width: 50% !important; box-sizing: border-box !important; border-radius: 0 0 0.75rem 0 !important; overflow: hidden !important;">
                            @csrf
                            <input type="hidden" name="redirect_to" value="tasks">
                            <button type="button" 
                                    class="btn btn-success fw-bold d-inline-flex align-items-center justify-content-center gap-1 border-0 w-100 m-0 py-2 px-2" 
                                    style="width: 100% !important; height: 38px; font-size: 0.76rem; background: linear-gradient(135deg, #15803d 0%, #16a34a 100%); border-radius: 0 0 0.75rem 0 !important; box-sizing: border-box !important;"
                                    onclick="confirmInApp('Pengesahan Supervisi', 'Sahkan hasil supervisi guru {{ addslashes($supervision->teacher?->name ?? 'Guru') }}?', function() { document.getElementById('form-approve-supervision-{{ $supervision->id }}').submit(); }, 'Sahkan Sekarang', 'btn-success')">
                                <i class="ti ti-check fs-3"></i>
                                <span>Sahkan</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif



@push('modals')
<!-- Custom Mobile In-App Confirmation Modal (Framework7 & Standalone Compatible) -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-inapp-confirm" style="height: auto; border-radius: 20px 20px 0 0; background: #ffffff;">
    <div class="sheet-modal-inner p-3">
        <div class="text-center pt-2 pb-1">
            <div class="avatar avatar-lg rounded-circle mx-auto mb-2 text-primary" style="width: 52px; height: 52px; background: #eff6ff; border: 2px solid #bfdbfe;">
                <i class="ti ti-help-circle fs-1" style="font-size: 1.8rem;"></i>
            </div>
            <div class="fw-bold text-dark fs-3 mb-1" id="inappConfirmTitle">Konfirmasi</div>
            <div class="text-muted small px-3 mb-3" id="inappConfirmMessage" style="font-size: 0.8rem; line-height: 1.4;">
                Apakah Anda yakin ingin melanjutkan tindakan ini?
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-2 rounded-pill sheet-close fw-semibold" onclick="closeInAppConfirm()">
                    Batal
                </button>
                <button type="button" class="btn btn-primary btn-sm flex-fill py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5" id="inappConfirmActionBtn">
                    <span>Ya, Lanjutkan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Sheet Popup untuk Catatan Revisi Supervisi -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-supervision-revision" style="height: auto; border-radius: 20px 20px 0 0; background: #ffffff;">
    <div class="sheet-modal-inner p-3">
        <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.92) 0%, rgba(25, 91, 158, 0.88) 100%); margin: -1rem -1rem 1rem -1rem; padding: 1rem 1.25rem; border-radius: 20px 20px 0 0;">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm bg-warning text-dark rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="ti ti-rotate-2 fs-3"></i>
                </span>
                <div>
                    <div class="fw-bold text-white fs-3 lh-1">Kembalikan untuk Revisi</div>
                    <div class="text-white-50 small mt-0.5" id="supervisionRevisionTeacherLabel" style="font-size: 0.72rem;">Guru Pengajar</div>
                </div>
            </div>
            <a href="#" class="sheet-close text-white opacity-75 fs-2 lh-1 text-decoration-none">&times;</a>
        </div>

        <form id="formSupervisionRevisionSheet" method="POST" action="">
            @csrf
            <input type="hidden" name="redirect_to" value="tasks">
            <div class="mb-3">
                <label class="form-label fw-bold text-dark small mb-1" style="font-size: 0.78rem;">
                    Catatan Bagian yang Perlu Direvisi <span class="text-danger">*</span>
                </label>
                <textarea name="revision_notes" 
                          id="supervisionRevisionNotesInput" 
                          class="form-control rounded-3" 
                          rows="3" 
                          placeholder="Tuliskan catatan perbaikan atau rubrik yang perlu dilengkapi oleh supervisor..." 
                          required 
                          style="font-size: 0.8rem;"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-2 rounded-pill sheet-close fw-semibold">
                    Batal
                </button>
                <button type="submit" class="btn btn-warning text-dark btn-sm flex-fill py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5" style="background-color: var(--brand-gold) !important; border-color: var(--brand-gold) !important;">
                    <i class="ti ti-rotate-2 fs-3"></i>
                    <span>Kirim Revisi</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endpush

<script>
let inAppConfirmCallback = null;

function confirmInApp(title, message, onConfirm, btnText = 'Ya, Lanjutkan', btnClass = 'btn-primary') {
    inAppConfirmCallback = onConfirm;
    document.getElementById('inappConfirmTitle').textContent = title;
    document.getElementById('inappConfirmMessage').textContent = message;

    const actionBtn = document.getElementById('inappConfirmActionBtn');
    actionBtn.className = 'btn btn-sm flex-fill py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5 ' + btnClass;
    actionBtn.querySelector('span').textContent = btnText;

    actionBtn.onclick = function() {
        closeInAppConfirm();
        if (typeof inAppConfirmCallback === 'function') {
            inAppConfirmCallback();
        }
    };

    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.open('#sheet-inapp-confirm');
    } else {
        const sheetEl = document.querySelector('#sheet-inapp-confirm');
        if (sheetEl) sheetEl.classList.add('modal-in');
    }
}

function closeInAppConfirm() {
    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.close('#sheet-inapp-confirm');
    } else {
        const sheetEl = document.querySelector('#sheet-inapp-confirm');
        if (sheetEl) sheetEl.classList.remove('modal-in');
    }
}

function openSupervisionRevisionSheet(supervisionId, teacherName, subjectName) {
    document.getElementById('supervisionRevisionTeacherLabel').textContent = teacherName + ' (' + subjectName + ')';
    document.getElementById('formSupervisionRevisionSheet').action = '/persetujuan/' + supervisionId + '/tolak';
    document.getElementById('supervisionRevisionNotesInput').value = '';

    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.open('#sheet-supervision-revision');
    } else {
        const sheetEl = document.querySelector('#sheet-supervision-revision');
        if (sheetEl) sheetEl.classList.add('modal-in');
    }
}

// Homeroom Live Monitoring Pill Handlers (8 Status Lengkap)
let activeHomeroomMonitoringKey = null;
const homeroomMonitoringTitles = {
    present: 'Siswa Hadir {{ $myHomeroomClassroom?->name }}',
    late: 'Siswa Terlambat Datang {{ $myHomeroomClassroom?->name }}',
    sakit: 'Siswa Sakit {{ $myHomeroomClassroom?->name }}',
    izin: 'Siswa Izin {{ $myHomeroomClassroom?->name }}',
    dispen: 'Siswa Dispensasi {{ $myHomeroomClassroom?->name }}',
    pulang: 'Siswa Izin Pulang Cepat {{ $myHomeroomClassroom?->name }}',
    alfa: 'Siswa Alfa Mapel {{ $myHomeroomClassroom?->name }}',
    truancy: 'Siswa Indikasi Bolos KBM {{ $myHomeroomClassroom?->name }}'
};

function toggleHomeroomMonitoringDetail(key) {
    const container = document.getElementById('homeroomDetailContainer');
    const titleEl = document.getElementById('homeroomDetailTitle');
    if (!container) return;

    if (activeHomeroomMonitoringKey === key) {
        closeHomeroomMonitoringDetail();
        return;
    }

    container.style.display = 'block';
    activeHomeroomMonitoringKey = key;

    if (titleEl && homeroomMonitoringTitles[key]) {
        titleEl.innerHTML = '<i class="ti ti-list-details text-primary"></i> ' + homeroomMonitoringTitles[key];
    }

    const panes = container.querySelectorAll('.homeroom-detail-pane');
    panes.forEach(pane => {
        pane.style.display = 'none';
    });

    const targetPane = document.getElementById('homeroomPane-' + key);
    if (targetPane) {
        targetPane.style.display = 'block';
    }

    document.querySelectorAll('[id^="chip-homeroom-"]').forEach(chip => {
        chip.style.boxShadow = 'none';
        chip.style.transform = 'none';
    });

    const activeChip = document.getElementById('chip-homeroom-' + key);
    if (activeChip) {
        activeChip.style.boxShadow = '0 0 0 2px #d97706, 0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        activeChip.style.transform = 'translateY(-1px)';
    }

    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeHomeroomMonitoringDetail() {
    const container = document.getElementById('homeroomDetailContainer');
    if (container) {
        container.style.display = 'none';
    }
    activeHomeroomMonitoringKey = null;

    document.querySelectorAll('[id^="chip-homeroom-"]').forEach(chip => {
        chip.style.boxShadow = 'none';
        chip.style.transform = 'none';
    });
}
</script>
@endsection
