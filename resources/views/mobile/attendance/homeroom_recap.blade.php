@extends('layouts.mobile')

@section('title', 'Rekap Presensi Siswa Kelas — APK SIAP')
@section('page_title', 'Rekap Presensi Kelas')
@section('back_url', route('tasks'))

@section('content')
@if($myClassrooms->isEmpty())
    <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
        <i class="ti ti-users-minus fs-1 d-block mb-1 opacity-50"></i>
        <div class="fw-bold text-dark fs-3">Bukan Wali Kelas</div>
        <div class="small px-3 mt-1">Menu ini khusus diperuntukkan bagi Bapak/Ibu Guru yang mengemban tugas sebagai Wali Kelas aktif.</div>
    </div>
@else
    <!-- Filter Kelas & Mode Tab -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <form action="{{ route('attendance.homeroom-recap.index') }}" method="GET" id="recapFilterForm" class="d-flex flex-column gap-2">
                <input type="hidden" name="mode" id="recapModeInput" value="{{ $mode }}">

                <!-- Baris 1: Pilihan Kelas -->
                <div>
                    <select name="classroom_id" class="form-select form-select-sm w-100" style="font-size: 0.8rem;" onchange="this.form.submit()">
                        @foreach($myClassrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassroomId == $c->id ? 'selected' : '' }}>
                                Kelas {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Baris 2: Mode Tab Segmented (6 Tombol Grid 3x2) -->
                <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 5px !important; width: 100% !important;">
                    <button type="button" 
                            onclick="setRecapMode('daily')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'daily' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'daily' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-clock-hour-4 me-0.5"></i> Harian
                    </button>
                    <button type="button" 
                            onclick="setRecapMode('weekly')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'weekly' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'weekly' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-calendar-week me-0.5"></i> Mingguan
                    </button>
                    <button type="button" 
                            onclick="setRecapMode('monthly')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'monthly' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'monthly' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-calendar-month me-0.5"></i> Bulanan
                    </button>
                    <button type="button" 
                            onclick="setRecapMode('semester')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'semester' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'semester' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-school me-0.5"></i> Semester
                    </button>
                    <button type="button" 
                            onclick="setRecapMode('yearly')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'yearly' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'yearly' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-calendar-stats me-0.5"></i> Tahunan
                    </button>
                    <button type="button" 
                            onclick="setRecapMode('custom')" 
                            class="btn btn-sm rounded-pill fw-bold text-truncate {{ $mode === 'custom' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                            style="font-size: 0.72rem; min-height: 30px; {{ $mode === 'custom' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                        <i class="ti ti-calendar-time me-0.5"></i> Rentang
                    </button>
                </div>

                <!-- Baris 3: Input Kontekstual Sesuai Mode -->
                @if(in_array($mode, ['daily', 'weekly', 'monthly']))
                    <div class="d-flex align-items-center gap-1.5 bg-light p-1.5 rounded-2">
                        <span class="text-muted small px-1 text-nowrap" style="font-size: 0.72rem;">Tanggal Acuan:</span>
                        <input type="date" name="date" class="form-control form-control-sm flex-fill" style="font-size: 0.78rem;" value="{{ $date }}" onchange="this.form.submit()">
                    </div>
                @elseif($mode === 'semester')
                    <div class="d-flex align-items-center gap-1.5 bg-light p-1.5 rounded-2">
                        <span class="text-muted small px-1 text-nowrap" style="font-size: 0.72rem;">Semester:</span>
                        <select name="semester" class="form-select form-select-sm flex-fill" style="font-size: 0.78rem;" onchange="this.form.submit()">
                            <option value="ganjil" {{ ($activeSemester ?? '') === 'ganjil' ? 'selected' : '' }}>Semester Ganjil (Jul - Des)</option>
                            <option value="genap" {{ ($activeSemester ?? '') === 'genap' ? 'selected' : '' }}>Semester Genap (Jan - Jun)</option>
                        </select>
                    </div>
                @elseif($mode === 'custom')
                    <div class="d-flex align-items-center gap-1.5 bg-light p-1.5 rounded-2">
                        <input type="date" name="start_date" class="form-control form-control-sm flex-fill" style="font-size: 0.74rem;" value="{{ $startDateStr }}">
                        <span class="text-muted small">-</span>
                        <input type="date" name="end_date" class="form-control form-control-sm flex-fill" style="font-size: 0.74rem;" value="{{ $endDateStr }}">
                        <button type="submit" class="btn btn-sm btn-primary py-1 px-2.5 rounded-2" style="font-size: 0.74rem;">
                            Set
                        </button>
                    </div>
                @else
                    <div class="bg-light p-1.5 rounded-2 text-center text-muted" style="font-size: 0.72rem;">
                        Tahun Ajaran Aktif: <strong class="text-dark">{{ $academicYear }}</strong> (1 Tahun Penuh)
                    </div>
                @endif

                <!-- Baris 4: Tombol Ekspor Excel, Unduh PDF, & Cetak -->
                <div class="d-flex gap-1.5 pt-1 border-top mt-1">
                    <a href="{{ route('attendance.homeroom-recap.export-excel', request()->query()) }}" 
                       class="btn btn-sm btn-outline-success flex-fill rounded-pill fw-bold d-flex align-items-center justify-content-center gap-1 text-truncate external py-1 px-1.5" 
                       style="font-size: 0.7rem; min-height: 32px;"
                       title="Unduh format spreadsheet Excel/CSV">
                        <i class="ti ti-file-spreadsheet fs-3"></i>
                        <span>Excel</span>
                    </a>
                    <a href="{{ route('attendance.homeroom-recap.download-pdf', request()->query()) }}" 
                       class="btn btn-sm btn-outline-danger flex-fill rounded-pill fw-bold d-flex align-items-center justify-content-center gap-1 text-truncate external py-1 px-1.5" 
                       style="font-size: 0.7rem; min-height: 32px;"
                       title="Unduh dokumen PDF">
                        <i class="ti ti-download fs-3"></i>
                        <span>Unduh PDF</span>
                    </a>
                    <a href="{{ route('attendance.homeroom-recap.download-pdf', array_merge(request()->query(), ['action' => 'print'])) }}" 
                       target="_blank"
                       class="btn btn-sm btn-outline-primary flex-fill rounded-pill fw-bold d-flex align-items-center justify-content-center gap-1 text-truncate external py-1 px-1.5" 
                       style="font-size: 0.7rem; min-height: 32px;"
                       title="Buka dokumen PDF untuk langsung dicetak">
                        <i class="ti ti-printer fs-3"></i>
                        <span>Cetak</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- 1. TAMPILAN MODE HARIAN (BERDASARKAN JAM PELAJARAN KE-1 S.D N) --}}
    {{-- ======================================================== --}}
    @if($mode === 'daily')
        <!-- Header Info Hari & Navigator Tanggal -->
        <div class="card border-0 shadow-sm rounded-3 mb-2 bg-light">
            <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'daily', 'date' => $prevDate]) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                    <i class="ti ti-chevron-left fs-3"></i>
                </a>
                <div class="text-center overflow-hidden">
                    <div class="fw-bold text-dark small" style="font-size: 0.8rem;">
                        {{ $dayName }}, {{ $currentDate->translatedFormat('d M Y') }}
                    </div>
                    <div class="text-muted" style="font-size: 0.68rem;">
                        Matriks Kehadiran Siswa per Jam Pelajaran (JP)
                    </div>
                </div>
                <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'daily', 'date' => $nextDate]) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                    <i class="ti ti-chevron-right fs-3"></i>
                </a>
            </div>
        </div>

        <!-- Ringkasan Statistik Singkat Hari Ini -->
        @if(!empty($summaryStats))
            <div class="card border-0 shadow-sm rounded-3 mb-2">
                <div class="card-body p-2 px-2.5">
                    <div class="d-flex align-items-center justify-content-between gap-1 text-center" style="font-size: 0.7rem;">
                        <div class="flex-fill">
                            <div class="fw-bold text-success fs-3 lh-1">{{ $summaryStats['present_full'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Hadir</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-danger fs-3 lh-1">{{ $summaryStats['partial_truant'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Bolos</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-purple fs-3 lh-1">{{ $summaryStats['dispen'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Dispen</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-warning fs-3 lh-1">{{ $summaryStats['late'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Telat</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-purple fs-3 lh-1" style="color: #9333ea;">{{ $summaryStats['early_leave'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Pulang</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-warning fs-3 lh-1">{{ $summaryStats['sick'] ?? 0 }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Sakit</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-info fs-3 lh-1">{{ $summaryStats['permit'] ?? 0 }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Izin</div>
                        </div>
                        <div class="border-start" style="height: 22px;"></div>
                        <div class="flex-fill">
                            <div class="fw-bold text-danger-subtle fs-3 lh-1" style="color: #b91c1c;">{{ $summaryStats['alfa'] }}</div>
                            <div class="text-muted small mt-0.5" style="font-size: 0.62rem;">Alfa</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Petunjuk Kode Status JP -->
        <div class="d-flex align-items-center justify-content-between px-1 mb-1.5 text-muted flex-wrap gap-1" style="font-size: 0.64rem;">
            <span><strong class="text-success">H</strong>: Hadir</span>
            <span><strong class="text-purple">D</strong>: Dispen</span>
            <span><strong class="text-warning">T</strong>: Telat</span>
            <span><strong class="text-purple" style="color: #9333ea;">P</strong>: Pulang</span>
            <span><strong class="text-warning">S</strong>: Sakit</span>
            <span><strong class="text-info">I</strong>: Izin</span>
            <span><strong class="text-danger">A</strong>: Alfa</span>
        </div>

        <!-- Matriks Siswa per JP (Card List dengan Barisan JP Interaktif) -->
        <div class="mb-3">
            @forelse($studentGrid as $item)
                @php
                    $stu = $item['student'];
                    $permit = $item['permit'];
                @endphp
                <div class="card border-0 shadow-sm rounded-3 mb-1.5 {{ $item['is_truant'] ? 'border-start border-danger border-3' : '' }}">
                    <div class="card-body p-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <div class="overflow-hidden pe-1">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <span class="fw-bold text-dark fs-4 lh-sm text-truncate">{{ $stu->name }}</span>
                                    @if(!empty($item['permits_list']) && $item['permits_list']->isNotEmpty())
                                        @foreach($item['permits_list'] as $pItem)
                                            @php
                                                $pillStyle = match($pItem->permit_category ?? $pItem->permit_type) {
                                                    'sakit' => 'background-color: #fef3c7 !important; color: #92400e !important; border: 1px solid #fde68a !important;',
                                                    'dispen' => 'background-color: #f3e8ff !important; color: #6b21a8 !important; border: 1px solid #d8b4fe !important;',
                                                    'terlambat' => 'background-color: #fef9c3 !important; color: #854d0e !important; border: 1px solid #facc15 !important;',
                                                    'izin_pulang_cepat' => 'background-color: #fae8ff !important; color: #86198f !important; border: 1px solid #e879f9 !important;',
                                                    default => 'background-color: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd !important;',
                                                };
                                            @endphp
                                            <span class="badge fw-bold text-decoration-none shadow-none" style="font-size: 0.62rem; padding: 2.5px 7px; border-radius: 9999px; {{ $pillStyle }}">
                                                <i class="ti ti-file-text me-0.5"></i> {{ $pItem->permit_type_label }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                                <div class="text-muted small" style="font-size: 0.66rem;">
                                    NIS: {{ $stu->nisn ?? $stu->nis ?? '-' }}
                                </div>
                            </div>
                            <span class="badge {{ $item['final_badge'] }} flex-shrink-0" style="font-size: 0.65rem; padding: 2px 7px;">
                                {{ $item['final_status'] }}
                            </span>
                        </div>

                        <!-- Barisan Jam Pelajaran (J1, J2, J3, dst) dengan jarak rapat & kompak -->
                        <div class="d-flex overflow-x-auto pb-0.5" style="gap: 3px; scrollbar-width: none;">
                            @foreach($periods as $p)
                                @php
                                    $st = $item['periods'][$p] ?? ['status' => null, 'label' => '-', 'subject' => '-'];
                                    $bgBadge = match($st['status']) {
                                        'h' => 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                                        's' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
                                        'i' => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                        'a' => 'background: #fee2e2; color: #b91c1c; border: 1.5px solid #f87171; font-weight: bold;',
                                        'd' => 'background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe;',
                                        't' => 'background: #fef9c3; color: #854d0e; border: 1.5px solid #facc15; font-weight: bold;',
                                        'p' => 'background: #fae8ff; color: #86198f; border: 1.5px solid #e879f9; font-weight: bold;',
                                        default => 'background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1;',
                                    };
                                @endphp
                                <div class="text-center flex-shrink-0 rounded-1 p-0.5" style="min-width: 26px; width: 26px; {{ $bgBadge }}" title="Jam ke-{{ $p }}: {{ $st['subject'] }}">
                                    <div class="text-muted fw-bold" style="font-size: 0.54rem; line-height: 1;">J{{ $p }}</div>
                                    <div class="fw-bold mt-0.5" style="font-size: 0.70rem; line-height: 1;">{{ $st['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                    <i class="ti ti-users-minus fs-1 text-muted d-block mb-1"></i>
                    <div class="small fw-semibold">Belum ada data siswa di kelas ini.</div>
                </div>
            @endforelse
        </div>

    {{-- ======================================================== --}}
    {{-- 2. TAMPILAN MODE MINGGUAN (SENIN S.D JUMAT - 5 HARI KERJA)--}}
    {{-- ======================================================== --}}
    @elseif($mode === 'weekly')
        <!-- Header Info Pekan -->
        <div class="card border-0 shadow-sm rounded-3 mb-2 bg-light">
            <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'weekly', 'date' => $prevWeek]) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                    <i class="ti ti-chevron-left fs-3"></i>
                </a>
                <div class="text-center overflow-hidden">
                    <div class="fw-bold text-dark small" style="font-size: 0.8rem;">
                        Pekan: {{ $startOfWeek->translatedFormat('d M') }} - {{ $endOfWeek->translatedFormat('d M Y') }}
                    </div>
                    <div class="text-muted" style="font-size: 0.68rem;">
                        Rekap Harian per Pekan (Senin s.d. Jumat)
                    </div>
                </div>
                <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'weekly', 'date' => $nextWeek]) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                    <i class="ti ti-chevron-right fs-3"></i>
                </a>
            </div>
        </div>

        <div class="mb-3">
            @forelse($studentWeeklyGrid as $item)
                @php $stu = $item['student']; @endphp
                <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                    <div class="card-body p-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <div class="fw-bold text-dark fs-4 lh-sm text-truncate pe-1">{{ $stu->name }}</div>
                            <div class="small text-muted flex-shrink-0" style="font-size: 0.68rem;">
                                <span class="text-success fw-bold">{{ $item['total_h'] }}H</span> &bull; 
                                <span class="text-purple fw-bold">{{ $item['total_d'] }}D</span> &bull; 
                                <span class="text-warning fw-bold">{{ $item['total_t'] }}T</span> &bull; 
                                <span class="text-purple fw-bold" style="color: #9333ea;">{{ $item['total_p'] }}P</span> &bull; 
                                <span class="text-info fw-bold">{{ $item['total_i'] }}I</span> &bull; 
                                <span class="text-warning fw-bold">{{ $item['total_s'] }}S</span> &bull; 
                                <span class="text-danger fw-bold">{{ $item['total_a'] }}A</span>
                                @if($item['total_truant'] > 0)
                                    &bull; <span class="badge bg-danger text-white" style="font-size: 0.58rem;">{{ $item['total_truant'] }}x Bolos</span>
                                @endif
                            </div>
                        </div>

                        <!-- Baris 5 Hari (Sen s.d Jum) -->
                        <div class="d-flex gap-1 overflow-x-auto pb-0.5">
                            @foreach($weekDays as $dayInfo)
                                @php
                                    $dStat = $item['days'][$dayInfo['date']] ?? ['status' => '-', 'label' => '-', 'badge' => 'text-muted'];
                                @endphp
                                <div class="text-center flex-fill rounded-2 p-1 bg-light border" style="min-width: 44px;">
                                    <div class="text-muted" style="font-size: 0.58rem;">{{ $dayInfo['shortName'] }}</div>
                                    <div class="fw-bold small mt-0.5" style="font-size: 0.7rem;">
                                        <span class="badge {{ $dStat['badge'] }}" style="padding: 2px 4px; font-size: 0.65rem;">{{ $dStat['label'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                    <i class="ti ti-users-minus fs-1 text-muted d-block mb-1"></i>
                    <div class="small fw-semibold">Belum ada data siswa di kelas ini.</div>
                </div>
            @endforelse
        </div>

    {{-- ======================================================== --}}
    {{-- 3. TAMPILAN MODE BULANAN, SEMESTERAN, TAHUNAN, KUSTOM   --}}
    {{-- ======================================================== --}}
    @else
        <!-- Header Info Periode -->
        <div class="card border-0 shadow-sm rounded-3 mb-2 bg-light">
            <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                @if($mode === 'monthly')
                    <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['date' => $prevMonth])) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                        <i class="ti ti-chevron-left fs-3"></i>
                    </a>
                @elseif($mode === 'semester')
                    <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['semester' => 'ganjil'])) }}" class="btn btn-xs {{ ($activeSemester ?? '') === 'ganjil' ? 'btn-dark' : 'btn-outline-secondary' }} rounded-pill" style="font-size: 0.65rem;">
                        Ganjil
                    </a>
                @else
                    <div style="width: 28px;"></div>
                @endif

                <div class="text-center overflow-hidden flex-fill px-2">
                    <div class="fw-bold text-dark small text-truncate" style="font-size: 0.8rem;">
                        {{ $periodTitle ?? 'Rekapitulasi Presensi' }}
                    </div>
                    <div class="text-muted" style="font-size: 0.68rem;">
                        Total Hari Efektif KBM: {{ $totalActiveDays }} Hari
                    </div>
                </div>

                @if($mode === 'monthly')
                    <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['date' => $nextMonth])) }}" class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs" style="width: 28px; height: 28px; padding: 0;">
                        <i class="ti ti-chevron-right fs-3"></i>
                    </a>
                @elseif($mode === 'semester')
                    <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['semester' => 'genap'])) }}" class="btn btn-xs {{ ($activeSemester ?? '') === 'genap' ? 'btn-dark' : 'btn-outline-secondary' }} rounded-pill" style="font-size: 0.65rem;">
                        Genap
                    </a>
                @else
                    <div style="width: 28px;"></div>
                @endif
            </div>
        </div>

        <div class="mb-3">
            @forelse($studentMonthlyGrid as $item)
                @php $stu = $item['student']; @endphp
                <div class="card border-0 shadow-sm rounded-3 mb-1.5">
                    <div class="card-body p-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-bold text-dark fs-4 lh-sm text-truncate pe-1">{{ $stu->name }}</div>
                            <span class="badge {{ $item['attendance_rate'] >= 85 ? 'bg-success-lt text-success' : ($item['attendance_rate'] >= 75 ? 'bg-warning-lt text-warning' : 'bg-danger-lt text-danger') }} fw-bold" style="font-size: 0.7rem; padding: 2px 7px;">
                                {{ $item['attendance_rate'] }}% Hadir
                            </span>
                        </div>

                        <!-- Rekap Bulanan Siswa (Hadir, Dispen, Telat, Pulang, Sakit, Izin, Alfa, Bolos) -->
                        <div class="d-flex align-items-center justify-content-between gap-1 text-center bg-light rounded-2 p-1.5 overflow-x-auto" style="font-size: 0.68rem;">
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Hadir</span>
                                <strong class="text-success fs-3 lh-1">{{ $item['days_present'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Dispen</span>
                                <strong class="text-purple fs-3 lh-1">{{ $item['days_dispen'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Telat</span>
                                <strong class="text-warning fs-3 lh-1">{{ $item['days_late'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Pulang</span>
                                <strong class="text-purple fs-3 lh-1" style="color: #9333ea;">{{ $item['days_early_leave'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Sakit</span>
                                <strong class="text-warning fs-3 lh-1">{{ $item['days_sick'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Izin</span>
                                <strong class="text-info fs-3 lh-1">{{ $item['days_permit'] }}</strong>
                            </div>
                            <div class="border-start" style="height: 20px;"></div>
                            <div class="flex-fill" style="min-width: 32px;">
                                <span class="text-muted d-block" style="font-size: 0.58rem;">Alfa</span>
                                <strong class="text-danger fs-3 lh-1">{{ $item['days_alfa'] }}</strong>
                            </div>
                            @if($item['days_truant'] > 0)
                                <div class="border-start" style="height: 20px;"></div>
                                <div class="flex-fill" style="min-width: 32px;">
                                    <span class="text-muted d-block" style="font-size: 0.58rem;">Bolos</span>
                                    <strong class="text-danger fw-bold fs-3 lh-1">{{ $item['days_truant'] }}x</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                    <i class="ti ti-users-minus fs-1 text-muted d-block mb-1"></i>
                    <div class="small fw-semibold">Belum ada data siswa di kelas ini.</div>
                </div>
            @endforelse
        </div>
    @endif
@endif
@endsection

@push('scripts')
<script>
    function setRecapMode(mode) {
        const input = document.getElementById('recapModeInput');
        if (input) {
            input.value = mode;
            document.getElementById('recapFilterForm').submit();
        }
    }
</script>
@endpush
