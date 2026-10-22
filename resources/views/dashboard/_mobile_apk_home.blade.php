<!-- ========================================================================= -->
<!-- KHUSUS TAMPILAN HP (MOBILE / APK NATIVE VIEW - d-md-none)                 -->
<!-- ========================================================================= -->
<div class="d-md-none">
    <!-- Header: APK SIAP - Home GTK -->
    <div class="card border-0 shadow-sm rounded-4 mb-3 text-white" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="badge bg-white-lt text-white px-2 py-1">
                    <i class="ti ti-device-mobile me-1"></i> APK SIAP &bull; Home GTK
                </span>
                <small class="text-white-50">{{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="avatar avatar-md rounded-circle border border-2 border-white shadow-sm flex-shrink-0" style="object-fit: cover;">
                @else
                    <span class="avatar avatar-md rounded-circle bg-warning text-dark fw-bold border border-2 border-white shadow-sm flex-shrink-0">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </span>
                @endif
                <div class="text-truncate">
                    <h3 class="mb-0 text-white fw-bold text-truncate">Halo, {{ $user->name }}</h3>
                    <div class="text-white-50 small mt-1 d-flex flex-wrap align-items-center gap-1">
                        <span class="badge bg-blue-lt text-white border-0 py-0 px-2">{{ $user->formatted_id }}</span>
                        <span>&bull; {{ $user->position ?: ($user->role?->name ?? 'GTK') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Cards: Hadir Hari Ini & Agenda Sekolah -->
    <div class="row g-2 mb-3">
        <!-- Status Hadir Hari Ini -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3" 
                 style="@if(!empty($myCheckInToday)) background: #f0fdf4 !important; border: 1.5px solid #86efac !important; @elseif(!empty($myGtkPermitToday)) background: #fffbeb !important; border: 1.5px solid #fde68a !important; @else background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; @endif">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            @if(!empty($myCheckInToday))
                                <span class="avatar avatar-sm bg-success text-white rounded-circle flex-shrink-0">
                                    <i class="ti ti-check fs-2"></i>
                                </span>
                                <div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">Status Hadir Hari Ini:</div>
                                    <div class="fw-bold text-success fs-3 lh-1">
                                        {{ $myCheckInToday->scanned_at->format('H:i') }} WIB
                                        <span class="badge bg-success-lt ms-1">{{ $myCheckInToday->status === 'terlambat' ? 'Terlambat' : 'Tepat Waktu' }}</span>
                                    </div>
                                </div>
                            @elseif(!empty($myGtkPermitToday))
                                <span class="avatar avatar-sm bg-warning text-dark rounded-circle flex-shrink-0">
                                    <i class="ti ti-calendar-time fs-2"></i>
                                </span>
                                <div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">Status Hadir Hari Ini:</div>
                                    <div class="fw-bold text-warning fs-3 lh-1">
                                        {{ $myGtkPermitToday->permit_type_label ?: ($myGtkPermitToday->type_label ?: 'Izin / Cuti') }}
                                    </div>
                                </div>
                            @else
                                <span class="avatar avatar-sm bg-danger text-white rounded-circle flex-shrink-0">
                                    <i class="ti ti-clock-exclamation fs-2"></i>
                                </span>
                                <div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">Status Hadir Hari Ini:</div>
                                    <div class="fw-bold text-danger fs-3 lh-1">Belum Presensi</div>
                                </div>
                            @endif
                        </div>
                        @if(empty($myCheckInToday))
                            <a href="{{ route('attendance.geotag.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                <i class="ti ti-map-pin me-1"></i> Absen GPS
                            </a>
                        @else
                            <a href="{{ route('attendance.geotag.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="font-size: 0.75rem;">
                                Detail
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Agenda Sekolah Hari Ini -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0">
                            <i class="ti ti-calendar-event fs-2"></i>
                        </span>
                        <div class="flex-fill text-truncate">
                            <div class="text-muted small" style="font-size: 0.72rem;">Agenda Sekolah:</div>
                            <div class="fw-bold text-dark fs-4 text-truncate">
                                @if(!empty($todaySchoolAgenda))
                                    {{ $todaySchoolAgenda->title }}
                                    <span class="badge bg-purple-lt ms-1">{{ $todaySchoolAgenda->learning_impact_short_label }}</span>
                                @else
                                    Hari Efektif KBM Normal
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MENU UTAMA RUTINITAS -->
    <div class="mb-3">
        <div class="text-uppercase tracking-wider fw-bold text-muted small mb-2 ps-1">
            MENU UTAMA RUTINITAS:
        </div>
        <div class="row g-2">
            <!-- 1. Jurnal & Presensi Kelas -->
            <div class="col-12">
                <a href="{{ route('attendance.subject.index') }}" class="card border shadow-sm rounded-3 text-decoration-none hover-shadow p-3 d-flex flex-row align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-md bg-success-lt text-success rounded-3 flex-shrink-0">
                            <i class="ti ti-notebook fs-1"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark fs-3 mb-0">📝 Jurnal & Presensi Kelas</div>
                            <div class="text-muted small">Absensi siswa saat KBM berlangsung</div>
                        </div>
                    </div>
                    <i class="ti ti-chevron-right text-muted fs-2"></i>
                </a>
            </div>

            <!-- 2. Menu Guru Piket (Jika bertugas) -->
            <div class="col-12">
                <a href="{{ route('piket.dashboard') }}" class="card border shadow-sm rounded-3 text-decoration-none hover-shadow p-3 d-flex flex-row align-items-center justify-content-between {{ !empty($isPiketToday) ? 'border-purple bg-purple-lt' : '' }}">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-md {{ !empty($isPiketToday) ? 'bg-purple text-white shadow-sm' : 'bg-teal-lt text-teal' }} rounded-3 flex-shrink-0">
                            <i class="ti ti-shield-check fs-1"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark fs-3 mb-0 d-flex align-items-center gap-2">
                                <span>🛡️ Menu Guru Piket</span>
                                @if(!empty($isPiketToday))
                                    <span class="badge bg-purple text-white">Bertugas Hari Ini!</span>
                                @endif
                            </div>
                            <div class="text-muted small">
                                @if(!empty($isPiketToday))
                                    Pos: <strong>{{ $todayPiketSchedule?->duty_location ?: 'Meja Piket Utama' }}</strong> ({{ $todayPiketSchedule?->role === 'koordinator' ? 'Koordinator' : 'Anggota' }})
                                @elseif(!empty($myWeeklyPiketSchedules) && $myWeeklyPiketSchedules->isNotEmpty())
                                    Jadwal: <strong>{{ $myWeeklyPiketSchedules->pluck('day')->implode(', ') }}</strong> &bull; Tukar jadwal &amp; informasi piket
                                @else
                                    Izin siswa keluar/masuk, buku tamu & inval
                                @endif
                            </div>
                        </div>
                    </div>
                    <i class="ti ti-chevron-right text-muted fs-2"></i>
                </a>
            </div>

            <!-- 3. Info Wali Kelas (Jika bertugas) -->
            @if(!empty($myHomeroomClassroom))
                <div class="col-12">
                    <a href="{{ route('attendance.homeroom-permits.index') }}" class="card border shadow-sm rounded-3 text-decoration-none hover-shadow p-3 d-flex flex-row align-items-center justify-content-between border-warning-subtle">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-warning-lt text-warning rounded-3 flex-shrink-0">
                                <i class="ti ti-heart-handshake fs-1"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-dark fs-3 mb-0">👨‍🏫 Info Wali Kelas ({{ $myHomeroomClassroom->name }})</div>
                                <div class="text-muted small">Catat surat sakit/izin dari wali murid</div>
                            </div>
                        </div>
                        <i class="ti ti-chevron-right text-muted fs-2"></i>
                    </a>
                </div>
            @endif

            <!-- 4. Izin & Cuti (Pembeda Jelas: Pimpinan vs Pribadi) -->
            @if(auth()->user()->isPrincipal() || auth()->user()->isWakaKurikulum() || auth()->user()->isSuperAdmin())
                <!-- Menu Pimpinan: Daftar Izin & Cuti Seluruh GTK -->
                <div class="col-12">
                    <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage']) }}" class="card border shadow-sm rounded-3 text-decoration-none hover-shadow p-3 d-flex flex-row align-items-center justify-content-between" style="border-left: 4px solid #195b9e !important;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-md rounded-3 flex-shrink-0 text-white" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
                                <i class="ti ti-file-certificate fs-1"></i>
                            </span>
                            <div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <div class="fw-bold text-dark fs-3 mb-0">🏢 Daftar Izin &amp; Cuti GTK</div>
                                    <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.60rem; padding: 1px 5px;">Pimpinan</span>
                                </div>
                                <div class="text-muted small">{{ auth()->user()->isPrincipal() ? 'Persetujuan & monitoring seluruh guru/staf' : 'Monitoring perizinan seluruh GTK' }}</div>
                            </div>
                        </div>
                        <i class="ti ti-chevron-right text-muted fs-2"></i>
                    </a>
                </div>
            @endif

            <!-- Menu Pribadi: Izin & Cuti Saya (Milik Semua GTK) -->
            <div class="col-12">
                <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'personal']) }}" class="card border shadow-sm rounded-3 text-decoration-none hover-shadow p-3 d-flex flex-row align-items-center justify-content-between" style="border-left: 4px solid #4f46e5 !important;">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-md bg-indigo-lt text-indigo rounded-3 flex-shrink-0">
                            <i class="ti ti-file-text fs-1"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark fs-3 mb-0">👤 Izin &amp; Cuti Saya</div>
                            <div class="text-muted small">Ajukan surat izin dinas luar, sakit, atau cuti pribadi</div>
                        </div>
                    </div>
                    <i class="ti ti-chevron-right text-muted fs-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- JADWAL SAYA HARI INI -->
    <div class="mb-4">
        <div class="text-uppercase tracking-wider fw-bold text-muted small mb-2 ps-1">
            JADWAL SAYA HARI INI ({{ $todayDay ?? 'Hari Ini' }}):
        </div>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-3">
                @if(!empty($todayTeachingSchedules) && $todayTeachingSchedules->isNotEmpty())
                    @foreach($todayTeachingSchedules as $sched)
                        <div class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div>
                                <div class="fw-bold text-dark fs-4">
                                    &bull; Jam {{ $sched->start_period }}-{{ $sched->end_period }} : 
                                    <span class="text-primary">{{ $sched->classroom?->name }}</span>
                                </div>
                                <div class="text-muted small">
                                    {{ $sched->subject?->name ?? 'Mata Pelajaran' }} &bull; ({{ $sched->start_time ?: '-' }} - {{ $sched->end_time ?: '-' }})
                                </div>
                            </div>
                            <a href="{{ route('attendance.subject.take', ['classroom_id' => $sched->classroom_id, 'subject_id' => $sched->subject_id, 'start_period' => $sched->start_period, 'end_period' => $sched->end_period, 'lesson_schedule_id' => $sched->id, 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                Absen
                            </a>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-3 text-muted">
                        <i class="ti ti-coffee fs-1 d-block mb-1 opacity-50"></i>
                        <div class="small fw-semibold">Tidak ada jadwal mengajar tatap muka untuk Anda hari ini ({{ $todayDay ?? 'Hari Ini' }}).</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Notice Pemisah Layar HP dan Desktop -->
    <div class="alert alert-light border rounded-3 text-muted small d-flex align-items-center mb-4">
        <i class="ti ti-device-laptop fs-1 me-2 text-primary flex-shrink-0"></i>
        <div>
            <strong>Pemberitahuan Sistem:</strong> Modul administrasi kurikulum, rubrik supervisi, master data, dan cetak dokumen dinas dirancang khusus untuk layar monitor komputer / laptop.
        </div>
    </div>
</div>
