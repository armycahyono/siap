@extends('layouts.mobile')

@section('title', 'Dashboard Guru Piket — APK SIAP')
@section('page_title', 'Menu Guru Piket')
@section('page_subtitle', $todayDay . ', ' . $today->translatedFormat('d F Y'))
@section('back_url', route('dashboard'))

@section('content')
<!-- Header Petugas & Jurnal Piket Hari Ini -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2.5 px-3 text-white">
        <!-- Judul kartu lurus dengan Jam (tanpa frame) -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold fs-3 lh-sm text-white d-flex align-items-center gap-1.5">
                <i class="ti ti-shield-check fs-2 text-warning"></i>
                <span>Meja Piket &amp; Ketertiban</span>
            </div>
            <div class="text-white-50 fw-semibold" style="font-size: 0.78rem;">
                {{ $currentTime }} WIB
            </div>
        </div>

        <!-- Kartu-kartu Kecil Petugas Piket (Berjajar Rapi dengan Grid) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 10px; width: 100%; box-sizing: border-box;">
            @forelse($todaySchedules as $ps)
                @php
                    $teacher = $ps->teacher;
                    $checkInTime = $teacherCheckInTimes->get($ps->teacher_id);
                    $isHadir = !empty($checkInTime);
                    if (!$isHadir && $todayJournal) {
                        $piketAtt = $todayJournal->attendances->firstWhere('teacher_id', $ps->teacher_id);
                        if ($piketAtt && in_array($piketAtt->status, ['hadir', 'h'])) {
                            $isHadir = true;
                            $checkInTime = $piketAtt->check_in_time ? \Illuminate\Support\Carbon::parse($piketAtt->check_in_time)->format('H:i') : null;
                        }
                    }

                    // Inisial jika tanpa foto
                    $initials = '';
                    if ($teacher?->name) {
                        $words = explode(' ', trim($teacher->name));
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    }
                @endphp
                <div class="d-flex align-items-center gap-1.5 p-1.5 rounded-2 shadow-xs" style="background: #ffffff; border: 1px solid rgba(255, 255, 255, 0.4); min-width: 0; box-sizing: border-box;">
                    <!-- Foto border kotak round -->
                    @if($teacher && $teacher->avatar)
                        <img src="{{ asset('storage/' . $teacher->avatar) }}" alt="{{ $teacher->name }}" class="flex-shrink-0 rounded-2" style="width: 32px; height: 32px; object-fit: cover; border: 1.5px solid #cbd5e1;" onerror="this.onerror=null; this.src='/favicon.ico';">
                    @else
                        <div class="flex-shrink-0 rounded-2 bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.7rem; border: 1.5px solid #cbd5e1;">
                            {{ $initials ?: 'GTK' }}
                        </div>
                    @endif

                    <!-- Di samping: Atas Nama, Bawah Jam Masuk Tanpa Frame -->
                    <div class="overflow-hidden flex-fill" style="min-width: 0;">
                        <div class="text-dark text-truncate lh-sm" style="font-size: 0.73rem; font-weight: 600;" title="{{ $teacher?->name }}">
                            {{ $teacher?->formatted_short_name ?? $teacher?->name ?? 'Petugas' }}
                        </div>
                        <div class="lh-sm mt-0.5 text-truncate" style="font-size: 0.68rem;">
                            @if($isHadir)
                                <span class="text-success fw-bold">{{ $checkInTime ? $checkInTime . ' WIB' : 'Hadir' }}</span>
                            @else
                                <span class="text-danger fw-semibold">Belum Masuk</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="grid-column: 1 / -1;">
                    <div class="text-white-50 small p-1.5 rounded-2 text-center" style="background: rgba(255, 255, 255, 0.08); font-size: 0.72rem;">
                        Belum ada jadwal petugas piket hari ini.
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Tombol / Kartu Mandiri Buka / Isi Jurnal Hari Ini (Warna Cerah Berdiri Sendiri) -->
@if(auth()->user()->canOperatePiket())
    <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important;">
        <a href="{{ route('piket.journals.today') }}" class="card-body p-2.5 px-3 d-flex align-items-center justify-content-between text-white text-decoration-none external" style="background: transparent !important;">
            <div class="d-flex align-items-center gap-2 overflow-hidden flex-fill">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white flex-shrink-0 shadow-xs" style="width: 36px; height: 36px;">
                    <i class="ti ti-notebook fs-2 text-warning"></i>
                </span>
                <div class="text-start overflow-hidden flex-fill">
                    <div class="fw-bold fs-4 lh-sm text-white text-truncate">{{ $todayJournal ? 'Buka Jurnal Hari Ini' : 'Mulai Jurnal Hari Ini' }}</div>
                    <div class="text-white-50 small mt-0.5 text-truncate" style="font-size: 0.68rem;">Catat kehadiran piket, perizinan &amp; kejadian</div>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0 ms-2" style="width: 28px; height: 28px; background: rgba(255, 255, 255, 0.25);">
                <i class="ti ti-chevron-right fs-2 text-white"></i>
            </div>
        </a>
    </div>
@else
    <div class="card border-0 shadow-sm rounded-3 mb-2 bg-light" style="border: 1px dashed #cbd5e1 !important;">
        <div class="card-body p-2.5 px-3 d-flex align-items-center justify-content-between text-muted" style="cursor: not-allowed; user-select: none;">
            <div class="d-flex align-items-center gap-2 overflow-hidden flex-fill">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary-lt flex-shrink-0" style="width: 36px; height: 36px;">
                    <i class="ti ti-notebook fs-2 text-secondary"></i>
                </span>
                <div class="text-start overflow-hidden flex-fill">
                    <div class="fw-bold fs-4 lh-sm text-secondary text-truncate">Jurnal Piket Hari Ini</div>
                    <div class="text-muted small mt-0.5 text-truncate" style="font-size: 0.68rem;">Hanya dapat diakses petugas piket aktif</div>
                </div>
            </div>
            <span class="badge bg-secondary-lt text-secondary" style="font-size: 0.65rem;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
        </div>
    </div>
@endif

<!-- Kartu Akses Cepat Meja Piket (4 Kolom Grid: Izin, Kasus, Inval, Tamu) -->
<div class="mobile-grid-4 mb-2">
    <!-- 1. Izin / Terlambat -->
    @if(auth()->user()->canOperatePiket())
        <a href="{{ route('piket.permits.index') }}" class="card card-link border-0 shadow-sm rounded-3 text-decoration-none p-2 text-center d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); border: 1px solid #7dd3fc !important; min-height: 80px;">
            <span class="avatar avatar-sm bg-primary text-white rounded-circle mb-1 shadow-xs" style="width: 30px; height: 30px;">
                <i class="ti ti-file-text fs-2"></i>
            </span>
            <div class="fw-bold text-dark fs-4 lh-sm">Izin</div>
            <div class="text-secondary small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;">Izin &amp; telat</div>
        </a>
    @else
        <div class="card border-0 shadow-sm rounded-3 p-2 text-center d-flex flex-column align-items-center justify-content-center opacity-60 bg-light" style="border: 1px dashed #cbd5e1 !important; min-height: 80px; cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket bertugas">
            <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1" style="width: 30px; height: 30px;">
                <i class="ti ti-file-text fs-2"></i>
            </span>
            <div class="fw-bold text-muted fs-4 lh-sm">Izin</div>
            <div class="text-muted small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;"><i class="ti ti-lock me-0.5"></i>Terkunci</div>
        </div>
    @endif

    <!-- 2. Kasus -->
    @if(auth()->user()->canManagePiketCases())
        <a href="{{ route('piket.incidents.index') }}" class="card card-link border-0 shadow-sm rounded-3 text-decoration-none p-2 text-center d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 1px solid #fca5a5 !important; min-height: 80px;">
            <span class="avatar avatar-sm bg-danger text-white rounded-circle mb-1 shadow-xs" style="width: 30px; height: 30px;">
                <i class="ti ti-alert-triangle fs-2"></i>
            </span>
            <div class="fw-bold text-dark fs-4 lh-sm">Kasus</div>
            <div class="text-secondary small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;">Laporan siswa</div>
        </a>
    @else
        <div class="card border-0 shadow-sm rounded-3 p-2 text-center d-flex flex-column align-items-center justify-content-center opacity-60 bg-light" style="border: 1px dashed #cbd5e1 !important; min-height: 80px; cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket / tim disiplin">
            <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1" style="width: 30px; height: 30px;">
                <i class="ti ti-alert-triangle fs-2"></i>
            </span>
            <div class="fw-bold text-muted fs-4 lh-sm">Kasus</div>
            <div class="text-muted small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;"><i class="ti ti-lock me-0.5"></i>Terkunci</div>
        </div>
    @endif

    <!-- 3. Inval -->
    @if(auth()->user()->canOperatePiket())
        <a href="{{ route('piket.inval.index') }}" class="card card-link border-0 shadow-sm rounded-3 text-decoration-none p-2 text-center d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); border: 1px solid #d8b4fe !important; min-height: 80px;">
            <span class="avatar avatar-sm bg-purple text-white rounded-circle mb-1 shadow-xs" style="width: 30px; height: 30px;">
                <i class="ti ti-user-x fs-2"></i>
            </span>
            <div class="fw-bold text-dark fs-4 lh-sm">Inval</div>
            <div class="text-secondary small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;">Guru ganti</div>
        </a>
    @else
        <div class="card border-0 shadow-sm rounded-3 p-2 text-center d-flex flex-column align-items-center justify-content-center opacity-60 bg-light" style="border: 1px dashed #cbd5e1 !important; min-height: 80px; cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket bertugas">
            <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1" style="width: 30px; height: 30px;">
                <i class="ti ti-user-x fs-2"></i>
            </span>
            <div class="fw-bold text-muted fs-4 lh-sm">Inval</div>
            <div class="text-muted small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;"><i class="ti ti-lock me-0.5"></i>Terkunci</div>
        </div>
    @endif

    <!-- 4. Tamu -->
    @if(auth()->user()->canOperatePiket())
        <a href="{{ route('piket.guests.index') }}" class="card card-link border-0 shadow-sm rounded-3 text-decoration-none p-2 text-center d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border: 1px solid #86efac !important; min-height: 80px;">
            <span class="avatar avatar-sm bg-success text-white rounded-circle mb-1 shadow-xs" style="width: 30px; height: 30px;">
                <i class="ti ti-users fs-2"></i>
            </span>
            <div class="fw-bold text-dark fs-4 lh-sm">Tamu</div>
            <div class="text-secondary small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;">Kunjungan</div>
        </a>
    @else
        <div class="card border-0 shadow-sm rounded-3 p-2 text-center d-flex flex-column align-items-center justify-content-center opacity-60 bg-light" style="border: 1px dashed #cbd5e1 !important; min-height: 80px; cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket bertugas">
            <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1" style="width: 30px; height: 30px;">
                <i class="ti ti-users fs-2"></i>
            </span>
            <div class="fw-bold text-muted fs-4 lh-sm">Tamu</div>
            <div class="text-muted small mt-0.5 text-truncate w-100" style="font-size: 0.65rem;"><i class="ti ti-lock me-0.5"></i>Terkunci</div>
        </div>
    @endif
</div>

<!-- Kartu Rekap Piket (1 Kartu Ringkas untuk Semua) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-2.5">
        <div class="fw-bold text-dark fs-4 mb-1.5 pb-1 border-bottom d-flex align-items-center justify-content-between">
            <span class="d-flex align-items-center gap-1.5">
                <i class="ti ti-chart-bar text-primary"></i> Rekap Piket Hari Ini
            </span>
            <span class="text-muted small" style="font-size: 0.68rem;">Live Data</span>
        </div>
        <div class="d-flex align-items-center justify-content-between text-center gap-1">
            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.permits.index', ['type' => 'terlambat']) }}" class="flex-fill text-decoration-none p-1 rounded-2 bg-light">
                    <div class="text-muted" style="font-size: 0.65rem;">Terlambat</div>
                    <div class="fw-bold text-warning fs-3 lh-1 mt-0.5">{{ $todayLateCount }}</div>
                </a>
            @else
                <div class="flex-fill p-1 rounded-2 bg-light text-muted" style="cursor: default;" title="Data terangkum (hanya petugas piket dapat membuka rekap)">
                    <div class="text-muted" style="font-size: 0.65rem;">Terlambat</div>
                    <div class="fw-bold text-warning fs-3 lh-1 mt-0.5">{{ $todayLateCount }}</div>
                </div>
            @endif

            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.permits.index', ['type' => 'izin_keluar_sementara']) }}" class="flex-fill text-decoration-none p-1 rounded-2 bg-light">
                    <div class="text-muted" style="font-size: 0.65rem;">Izin Keluar</div>
                    <div class="fw-bold text-primary fs-3 lh-1 mt-0.5">{{ $todayPermitLeaveCount }}</div>
                </a>
            @else
                <div class="flex-fill p-1 rounded-2 bg-light text-muted" style="cursor: default;" title="Data terangkum (hanya petugas piket dapat membuka rekap)">
                    <div class="text-muted" style="font-size: 0.65rem;">Izin Keluar</div>
                    <div class="fw-bold text-primary fs-3 lh-1 mt-0.5">{{ $todayPermitLeaveCount }}</div>
                </div>
            @endif

            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.inval.index') }}" class="flex-fill text-decoration-none p-1 rounded-2 bg-light">
                    <div class="text-muted" style="font-size: 0.65rem;">Butuh Inval</div>
                    <div class="fw-bold text-danger fs-3 lh-1 mt-0.5">{{ $todayInvalCount }}</div>
                </a>
            @else
                <div class="flex-fill p-1 rounded-2 bg-light text-muted" style="cursor: default;" title="Data terangkum (hanya petugas piket dapat membuka rekap)">
                    <div class="text-muted" style="font-size: 0.65rem;">Butuh Inval</div>
                    <div class="fw-bold text-danger fs-3 lh-1 mt-0.5">{{ $todayInvalCount }}</div>
                </div>
            @endif

            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.guests.index') }}" class="flex-fill text-decoration-none p-1 rounded-2 bg-light">
                    <div class="text-muted" style="font-size: 0.65rem;">Buku Tamu</div>
                    <div class="fw-bold text-success fs-3 lh-1 mt-0.5">{{ $todayGuestCount }}</div>
                </a>
            @else
                <div class="flex-fill p-1 rounded-2 bg-light text-muted" style="cursor: default;" title="Data terangkum (hanya petugas piket dapat membuka rekap)">
                    <div class="text-muted" style="font-size: 0.65rem;">Buku Tamu</div>
                    <div class="fw-bold text-success fs-3 lh-1 mt-0.5">{{ $todayGuestCount }}</div>
                </div>
            @endif

            @if(auth()->user()->canManagePiketCases())
                <a href="{{ route('piket.incidents.index') }}" class="flex-fill text-decoration-none p-1 rounded-2 bg-light">
                    <div class="text-muted" style="font-size: 0.65rem;">Kasus</div>
                    <div class="fw-bold text-danger fs-3 lh-1 mt-0.5">{{ $todayIncidentCount }}</div>
                </a>
            @else
                <div class="flex-fill p-1 rounded-2 bg-light text-muted" style="cursor: default;" title="Data terangkum (hanya petugas piket dapat membuka rekap)">
                    <div class="text-muted" style="font-size: 0.65rem;">Kasus</div>
                    <div class="fw-bold text-danger fs-3 lh-1 mt-0.5">{{ $todayIncidentCount }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Kartu Daftar Jam Pelajaran (Tabel Jam & Status KBM) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-1.5 pb-1 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-1.5">
                <i class="ti ti-clock text-primary"></i>
                <span>Daftar Jam Pelajaran {{ $todayDay === 'Jumat' ? '(Khusus Jumat)' : '' }}</span>
            </div>
            @if($isKbmFinished)
                <span class="badge bg-secondary-lt text-dark" style="font-size: 0.65rem; padding: 2px 7px;">
                    <i class="ti ti-check me-0.5 text-success"></i> KBM Selesai ({{ $kbmEndTime }} WIB)
                </span>
            @elseif($isOutsideKbm)
                <span class="badge bg-warning-lt text-dark" style="font-size: 0.65rem; padding: 2px 7px;">
                    Di Luar Jam KBM
                </span>
            @elseif($isBreak)
                <span class="badge bg-warning text-dark" style="font-size: 0.65rem; padding: 2px 7px;">
                    Jam Istirahat
                </span>
            @endif
        </div>

        @if($breakMessage)
            <div class="alert alert-info py-1 px-2 mb-2 rounded-2 d-flex align-items-center gap-1.5" style="font-size: 0.72rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;">
                <i class="ti ti-info-circle fs-3 text-success flex-shrink-0"></i>
                <span>{{ $breakMessage }}</span>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-bordered text-center mb-0" style="font-size: 0.72rem; line-height: 1.25;">
                <thead class="bg-light text-muted">
                    <tr>
                        <th style="width: 25%;">Jam Ke</th>
                        <th style="width: 45%;">Waktu (WIB)</th>
                        <th style="width: 30%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($periodTimes as $p => $times)
                        @if($p == 4)
                            <tr class="table-warning text-dark" style="background: #fef3c7 !important;">
                                <td class="py-0.5">-</td>
                                <td class="py-0.5">09:15 - 09:50</td>
                                <td class="py-0.5 fw-semibold">Istirahat 1</td>
                            </tr>
                        @elseif($todayDay !== 'Jumat' && $p == 8)
                            <tr class="table-warning text-dark" style="background: #fef3c7 !important;">
                                <td class="py-0.5">-</td>
                                <td class="py-0.5">12:30 - 13:00</td>
                                <td class="py-0.5 fw-semibold">Istirahat 2 &amp; Sholat</td>
                            </tr>
                        @endif

                        @php
                            // Baris jam sekarang HANYA aktif jika sedang jam KBM efektif aktif (tidak di luar jam KBM / tidak selesai)
                            $isCurrentPeriod = (!$isOutsideKbm && !$isWeekend && !$isBreak && $detectedPeriod == $p);
                            $isSelectedPeriod = ($selectedPeriod == $p);
                        @endphp
                        <tr class="{{ $isCurrentPeriod ? 'table-success fw-bold' : ($isSelectedPeriod && !$isOutsideKbm ? 'table-info' : '') }}" style="{{ $isCurrentPeriod ? 'background: #dcfce7 !important;' : '' }}">
                            <td class="py-0.5">
                                <a href="{{ route('piket.dashboard', ['period' => $p]) }}" class="text-decoration-none fw-bold {{ $isCurrentPeriod ? 'text-success' : 'text-dark' }}">
                                    Jam ke-{{ $p }}
                                </a>
                            </td>
                            <td class="py-0.5">{{ $times['start'] }} - {{ $times['end'] }}</td>
                            <td class="py-0.5">
                                @if($isCurrentPeriod)
                                    <span class="badge bg-success text-white" style="font-size: 0.6rem; padding: 1px 5px;">Sekarang</span>
                                @elseif($isSelectedPeriod && !$isOutsideKbm)
                                    <span class="badge bg-info text-white" style="font-size: 0.6rem; padding: 1px 5px;">Dipilih</span>
                                @elseif($isKbmFinished)
                                    <span class="text-muted" style="font-size: 0.65rem;">Selesai</span>
                                @else
                                    <span class="text-muted">KBM</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Kartu Seluruh Rombel Kelas (Tanpa Judul Jam Pelajaran Hari Ini di Atasnya) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <!-- Mini Status KBM Saat Ini -->
        <div class="p-1.5 bg-light rounded-2 d-flex justify-content-around text-center mb-2" style="font-size: 0.7rem;">
            @if($isKbmFinished)
                <div>Status Hari Ini: <strong class="text-success"><i class="ti ti-check"></i> KBM Selesai</strong></div>
                <div>Total Rombel: <strong class="text-primary">{{ count($classroomSchedules) }}</strong></div>
            @else
                <div>KBM: <strong class="text-success">{{ $countTeaching }}</strong></div>
                @if(!empty($countEvent))
                    <div>PKL/Bebas: <strong class="text-purple">{{ $countEvent }}</strong></div>
                @endif
                <div>Butuh Inval: <strong class="text-danger">{{ $countInvalNeeded }}</strong></div>
                <div>Diinval: <strong class="text-info">{{ $countInvalHandled }}</strong></div>
                <div>Kosong: <strong class="text-secondary">{{ $countFree }}</strong></div>
            @endif
        </div>

        <!-- Feed Kartu Seluruh Rombel Kelas -->
        <div class="d-flex flex-column gap-2">
            @foreach($classroomSchedules as $cs)
                @php
                    $isNeedInval = ($cs['status'] === 'inval_needed');
                    $isHandled = ($cs['status'] === 'inval_handled');
                    $isEvent = ($cs['status'] === 'event');
                    $classEvent = $cs['event'];
                    $stats = $cs['stats'];
                @endphp
                <div class="p-2 rounded-2 border {{ $isNeedInval ? 'bg-danger-lt border-danger' : ($isHandled ? 'bg-info-lt border-info' : ($isEvent ? 'border-purple-lt' : 'bg-white')) }}" style="box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <!-- Header Rombel: Nama Kelas & Status KBM / Dampak Kegiatan -->
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-bold text-dark fs-3">{{ $cs['classroom']?->name }}</span>
                        @php
                            $sched = $cs['schedule'];
                            $inval = $cs['inval'];
                            $cls = $cs['classroom'];
                            $attendanceUrl = null;
                            if ($sched) {
                                $params = [
                                    'schedule_id' => $sched->id,
                                    'date' => $today->toDateString(),
                                ];
                                if ($isHandled || $isNeedInval) {
                                    $params['is_swap'] = 1;
                                    $params['swap_type'] = 'inval';
                                }
                                $attendanceUrl = route('attendance.subject.take', $params);
                            } elseif (!empty($cs['attended_session'])) {
                                $attendanceUrl = route('attendance.subject.take', [
                                    'classroom_id' => $cls->id,
                                    'subject_id' => $cs['attended_session']->subject_id,
                                    'start_period' => $cs['attended_session']->start_period,
                                    'end_period' => $cs['attended_session']->end_period,
                                    'date' => $today->toDateString(),
                                ]);
                            }
                        @endphp
                        @if($classEvent && $classEvent->learning_impact !== 'normal')
                            <span class="badge {{ $classEvent->learning_impact_badge }}" style="font-size: 0.62rem;">
                                {{ $classEvent->learning_impact_short_label }}: {{ \Illuminate\Support\Str::limit($classEvent->title, 14) }}
                            </span>
                        @elseif($isKbmFinished)
                            <span class="badge bg-secondary-lt text-dark" style="font-size: 0.62rem;">KBM Selesai</span>
                        @elseif($isOutsideKbm)
                            <span class="badge bg-secondary-lt" style="font-size: 0.62rem;">Di Luar KBM</span>
                        @elseif($isNeedInval)
                            @if($attendanceUrl)
                                <a href="{{ $attendanceUrl }}" class="badge bg-danger text-white external text-decoration-none" style="font-size: 0.62rem;">Butuh Inval</a>
                            @else
                                <span class="badge bg-danger text-white" style="font-size: 0.62rem;">Butuh Inval</span>
                            @endif
                        @elseif($isHandled)
                            <div class="d-flex align-items-center gap-1">
                                @if(!empty($cs['is_attended']))
                                    @if($attendanceUrl)
                                        <a href="{{ $attendanceUrl }}" class="badge bg-success-lt text-success fw-bold external text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                        </a>
                                    @else
                                        <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                        </span>
                                    @endif
                                @else
                                    @if($attendanceUrl)
                                        <a href="{{ $attendanceUrl }}" class="badge bg-danger-lt text-danger fw-bold external text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                        </a>
                                    @else
                                        <span class="badge bg-danger-lt text-danger fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                        </span>
                                    @endif
                                @endif
                                @if($attendanceUrl)
                                    <a href="{{ $attendanceUrl }}" class="badge bg-info text-white external text-decoration-none" style="font-size: 0.62rem;">Terisi Inval</a>
                                @else
                                    <span class="badge bg-info text-white" style="font-size: 0.62rem;">Terisi Inval</span>
                                @endif
                            </div>
                        @elseif($cs['status'] === 'teaching')
                            <div class="d-flex align-items-center gap-1">
                                @if(!empty($cs['is_attended']))
                                    @if($attendanceUrl)
                                        <a href="{{ $attendanceUrl }}" class="badge bg-success-lt text-success fw-bold external text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                        </a>
                                    @else
                                        <span class="badge bg-success-lt text-success fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-check me-0.5"></i> Sudah Diabsen
                                        </span>
                                    @endif
                                @else
                                    @if($attendanceUrl)
                                        <a href="{{ $attendanceUrl }}" class="badge bg-danger-lt text-danger fw-bold external text-decoration-none" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                        </a>
                                    @else
                                        <span class="badge bg-danger-lt text-danger fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">
                                            <i class="ti ti-alert-circle me-0.5"></i> Belum Diabsen
                                        </span>
                                    @endif
                                @endif
                                @if($attendanceUrl)
                                    <a href="{{ $attendanceUrl }}" class="badge bg-success text-white external text-decoration-none" style="font-size: 0.62rem;">KBM Reguler</a>
                                @else
                                    <span class="badge bg-success text-white" style="font-size: 0.62rem;">KBM Reguler</span>
                                @endif
                            </div>
                        @else
                            <span class="badge bg-secondary-lt" style="font-size: 0.62rem;">Jam Kosong</span>
                        @endif
                    </div>

                    <!-- Sekarang: Guru & Mapel Jam Aktif atau Kegiatan/PKL -->
                    @if($isEvent || ($classEvent && in_array($classEvent->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus', 'pembelajaran_daring'])))
                        <div class="small lh-sm text-truncate mb-0.5" style="font-size: 0.74rem;">
                            <span class="text-muted">kegiatan :</span>
                            <strong class="text-purple">{{ $classEvent?->title ?? 'Kegiatan Sekolah' }}</strong>
                        </div>
                        <div class="text-secondary small text-truncate mb-1.5" style="font-size: 0.72rem;">
                            {{ ($classEvent?->learning_impact === 'pembelajaran_daring') ? 'Pembelajaran Daring (Online)' : (($classEvent?->learning_impact === 'libur_khusus') ? 'Libur Khusus Agenda' : 'Bebas KBM Reguler') }} &bull; {{ $classEvent?->date_summary }}
                        </div>
                    @else
                        <div class="small lh-sm text-truncate mb-0.5" style="font-size: 0.74rem;">
                            <span class="text-muted">{{ $isKbmFinished ? 'jam terakhir :' : ($isOutsideKbm ? 'jadwal :' : 'sekarang :') }}</span>
                            <strong>
                                @if($isHandled)
                                    {{ $cs['inval_teacher']?->formatted_short_name ?? $cs['inval_teacher']?->name }} (Inval)
                                @elseif($cs['schedule'])
                                    {{ $cs['teacher']?->formatted_short_name ?? $cs['teacher']?->name }}
                                @else
                                    -
                                @endif
                            </strong>
                        </div>
                        <div class="text-primary small text-truncate mb-1.5" style="font-size: 0.72rem; font-weight: 500;">
                            {{ $cs['subject']?->name ?? 'Tidak ada mapel' }}
                        </div>
                    @endif

                    <!-- Baris Absensi Siswa (1 Baris Ringkas: H B D T P S I A Huruf Besar Semua) -->
                    <div class="p-1 px-1.5 rounded-1 bg-light border d-flex align-items-center justify-content-between text-truncate" style="font-size: 0.68rem; line-height: 1;">
                        <span title="Hadir"><strong class="text-success">H</strong> {{ $stats['hadir'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Bolos"><strong class="text-danger">B</strong> {{ $stats['bolos'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Dispen"><strong class="text-purple">D</strong> {{ $stats['dispen'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Telat"><strong class="text-warning">T</strong> {{ $stats['telat'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Pulang Cepat"><strong class="text-info">P</strong> {{ $stats['pulang'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Sakit"><strong class="text-warning">S</strong> {{ $stats['sakit'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Izin"><strong class="text-primary">I</strong> {{ $stats['izin'] }}</span>
                        <span class="text-muted">|</span>
                        <span title="Alfa"><strong class="text-danger">A</strong> {{ $stats['alfa'] }}</span>
                    </div>

                    @if($isNeedInval)
                        <div class="mt-1.5">
                            @if(auth()->user()->canOperatePiket())
                                <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-danger rounded-pill w-100 py-0.5 fw-bold" style="font-size: 0.72rem;">
                                    + Tetapkan Guru Inval
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill w-100 py-0.5 fw-bold opacity-60" style="font-size: 0.72rem; cursor: not-allowed;" disabled title="Hanya untuk petugas piket bertugas">
                                    <i class="ti ti-lock me-1"></i> Butuh Guru Inval
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Navigasi Pintasan Modul Meja Piket (2 Baris x 3 Menu) -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-2.5">
        <div class="fw-bold text-dark fs-4 mb-2 pb-1 border-bottom d-flex align-items-center justify-content-between">
            <span class="d-flex align-items-center gap-1.5">
                <i class="ti ti-grid-dots text-primary"></i> Menu Lengkap Meja Piket
            </span>
            <span class="text-muted small" style="font-size: 0.68rem;">Akses Cepat</span>
        </div>

        <!-- Grid 2 Baris x 3 Menu Rapi -->
        <div class="mobile-grid-3 mb-2" style="font-size: 0.72rem; margin-bottom: 8px !important;">
            <!-- Baris 1: Rekap Izin -->
            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.permits.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                    <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle mb-1" style="width: 30px; height: 30px;">
                        <i class="ti ti-file-text fs-3"></i>
                    </span>
                    <span class="fw-bold text-dark lh-sm">Rekap Izin</span>
                </a>
            @else
                <div class="card border p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 bg-light opacity-60" style="cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket bertugas">
                    <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1 position-relative" style="width: 30px; height: 30px;">
                        <i class="ti ti-file-text fs-3"></i>
                    </span>
                    <span class="fw-bold text-muted lh-sm">Rekap Izin</span>
                    <span class="badge bg-secondary-lt text-secondary mt-0.5" style="font-size: 0.55rem; padding: 1px 4px;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
                </div>
            @endif

            <!-- Jadwal Piket (Terbuka untuk semua yang berhak ke meja piket) -->
            <a href="{{ route('piket.schedules.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                <span class="avatar avatar-sm bg-purple-lt text-purple rounded-circle mb-1" style="width: 30px; height: 30px;">
                    <i class="ti ti-calendar-user fs-3"></i>
                </span>
                <span class="fw-bold text-dark lh-sm">Jadwal Piket</span>
            </a>

            <!-- Buku Jurnal -->
            @if(auth()->user()->canOperatePiket())
                <a href="{{ route('piket.journals.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                    <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle mb-1" style="width: 30px; height: 30px;">
                        <i class="ti ti-notebook fs-3"></i>
                    </span>
                    <span class="fw-bold text-dark lh-sm">Buku Jurnal</span>
                </a>
            @else
                <div class="card border p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 bg-light opacity-60" style="cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket bertugas">
                    <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1 position-relative" style="width: 30px; height: 30px;">
                        <i class="ti ti-notebook fs-3"></i>
                    </span>
                    <span class="fw-bold text-muted lh-sm">Buku Jurnal</span>
                    <span class="badge bg-secondary-lt text-secondary mt-0.5" style="font-size: 0.55rem; padding: 1px 4px;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
                </div>
            @endif

            <!-- Baris 2: Buku Kasus -->
            @if(auth()->user()->canManagePiketCases())
                <a href="{{ route('piket.incidents.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                    <span class="avatar avatar-sm bg-danger-lt text-danger rounded-circle mb-1" style="width: 30px; height: 30px;">
                        <i class="ti ti-alert-triangle fs-3"></i>
                    </span>
                    <span class="fw-bold text-dark lh-sm">Buku Kasus</span>
                </a>
            @else
                <div class="card border p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 bg-light opacity-60" style="cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket / tim disiplin">
                    <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1 position-relative" style="width: 30px; height: 30px;">
                        <i class="ti ti-alert-triangle fs-3"></i>
                    </span>
                    <span class="fw-bold text-muted lh-sm">Buku Kasus</span>
                    <span class="badge bg-secondary-lt text-secondary mt-0.5" style="font-size: 0.55rem; padding: 1px 4px;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
                </div>
            @endif

            <!-- Tukar Jadwal -->
            @if(auth()->user()->canManagePiketSchedules())
                <a href="{{ route('piket.swaps.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                    <span class="avatar avatar-sm bg-teal-lt text-teal rounded-circle mb-1" style="width: 30px; height: 30px;">
                        <i class="ti ti-arrows-left-right fs-3"></i>
                    </span>
                    <span class="fw-bold text-dark lh-sm">Tukar Jadwal</span>
                </a>
            @else
                <div class="card border p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 bg-light opacity-60" style="cursor: not-allowed; user-select: none;" title="Hanya untuk guru dengan jadwal piket">
                    <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1 position-relative" style="width: 30px; height: 30px;">
                        <i class="ti ti-arrows-left-right fs-3"></i>
                    </span>
                    <span class="fw-bold text-muted lh-sm">Tukar Jadwal</span>
                    <span class="badge bg-secondary-lt text-secondary mt-0.5" style="font-size: 0.55rem; padding: 1px 4px;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
                </div>
            @endif

            <!-- Laporan -->
            @if(auth()->user()->canViewPiketReports())
                <a href="{{ route('piket.reports.index') }}" class="card border text-decoration-none p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 hover-shadow bg-light">
                    <span class="avatar avatar-sm bg-indigo-lt text-indigo rounded-circle mb-1" style="width: 30px; height: 30px;">
                        <i class="ti ti-report-analytics fs-3"></i>
                    </span>
                    <span class="fw-bold text-dark lh-sm">Laporan</span>
                </a>
            @else
                <div class="card border p-2 rounded-2 d-flex flex-column align-items-center justify-content-center text-center h-100 bg-light opacity-60" style="cursor: not-allowed; user-select: none;" title="Hanya untuk petugas piket / koordinator">
                    <span class="avatar avatar-sm bg-secondary-lt text-secondary rounded-circle mb-1 position-relative" style="width: 30px; height: 30px;">
                        <i class="ti ti-report-analytics fs-3"></i>
                    </span>
                    <span class="fw-bold text-muted lh-sm">Laporan</span>
                    <span class="badge bg-secondary-lt text-secondary mt-0.5" style="font-size: 0.55rem; padding: 1px 4px;"><i class="ti ti-lock me-0.5"></i>Terkunci</span>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
