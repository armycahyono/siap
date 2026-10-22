@extends('layouts.mobile')

@section('title', 'Rekap Presensi GTK — APK SIAP')
@section('page_title', 'Rekap Presensi GTK')
@section('page_subtitle', 'Guru & Tenaga Kependidikan')
@section('back_url', route('attendance.subject.index'))

@php
    $isSingleDay = ($startDate === $endDate);
    $prevDate = \Carbon\Carbon::parse($startDate)->subDay()->toDateString();
    $nextDate = \Carbon\Carbon::parse($startDate)->addDay()->toDateString();
    $currentCarbon = \Carbon\Carbon::parse($startDate);
@endphp

@section('content')
<!-- Filter & Parameter Rekap Presensi GTK -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form method="GET" action="{{ route('attendance.report.teachers') }}" id="mobileReportForm" class="d-flex flex-column gap-2">
            <!-- Baris 1: Mode Periode & Kelompok GTK -->
            <div class="d-flex gap-2">
                <div class="flex-fill" style="min-width: 0;">
                    <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Periode</label>
                    <select name="period_type" id="periodTypeSelect" class="form-select form-select-sm" style="font-size: 0.78rem;" onchange="toggleMobilePeriodFields()">
                        <option value="custom" {{ $periodType === 'custom' ? 'selected' : '' }}>Harian / Tanggal</option>
                        <option value="monthly" {{ $periodType === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                    </select>
                </div>
                <div class="flex-fill" style="min-width: 0;">
                    <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Kelompok GTK</label>
                    <select name="sub_type" class="form-select form-select-sm" style="font-size: 0.78rem;" onchange="this.form.submit()">
                        <option value="all" {{ $subType === 'all' ? 'selected' : '' }}>Semua GTK</option>
                        <option value="guru" {{ $subType === 'guru' ? 'selected' : '' }}>Guru Saja</option>
                        <option value="staf" {{ $subType === 'staf' ? 'selected' : '' }}>Staf Saja</option>
                    </select>
                </div>
            </div>

            <!-- Baris 2: Field Tanggal / Bulan -->
            <!-- 1. Mode Tanggal (Custom/Harian) -->
            <div id="fieldMobileCustom" class="{{ $periodType === 'monthly' ? 'd-none' : '' }}">
                <div class="d-flex gap-2 align-items-center">
                    <div class="flex-fill" style="min-width: 0;">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Dari Tanggal</label>
                        <input type="date" name="start_date" id="inputStartDate" class="form-control form-control-sm" style="font-size: 0.78rem;" value="{{ $startDate }}" onchange="this.form.submit()">
                    </div>
                    <div class="flex-fill" style="min-width: 0;">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Sampai Tanggal</label>
                        <input type="date" name="end_date" id="inputEndDate" class="form-control form-control-sm" style="font-size: 0.78rem;" value="{{ $endDate }}" onchange="this.form.submit()">
                    </div>
                </div>
            </div>

            <!-- 2. Mode Bulanan -->
            <div id="fieldMobileMonthly" class="{{ $periodType !== 'monthly' ? 'd-none' : '' }}">
                <div class="d-flex gap-2 align-items-center">
                    <div class="flex-fill" style="min-width: 0;">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Bulan</label>
                        <select name="month" class="form-select form-select-sm" style="font-size: 0.78rem;" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ (int)($dateInfo['month'] ?? now()->month) === $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="width: 100px;">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.68rem; font-weight: 600;">Tahun</label>
                        <input type="number" name="year" class="form-control form-control-sm" style="font-size: 0.78rem;" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="this.form.submit()">
                    </div>
                </div>
            </div>

            <!-- Baris 3: Cari Nama & Tombol Filter (Ukuran tombol filter disamakan dengan form Tahun agar kolom cari nama lebih panjang) -->
            <div class="d-flex gap-2 align-items-center">
                <div class="flex-fill position-relative" style="min-width: 0;">
                    <input type="text" name="search" class="form-control form-control-sm" style="font-size: 0.78rem; padding-left: 28px !important; {{ $search ? 'padding-right: 28px !important;' : '' }}" placeholder="Cari nama atau PIN GTK..." value="{{ $search }}">
                    <i class="ti ti-search text-muted position-absolute" style="left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.85rem; pointer-events: none;"></i>
                    @if($search)
                        <a href="{{ route('attendance.report.teachers', array_merge(request()->except('search'), ['period_type' => $periodType, 'sub_type' => $subType])) }}" class="position-absolute text-muted external text-decoration-none d-flex align-items-center justify-content-center" style="right: 6px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px;" title="Hapus pencarian">
                            <i class="ti ti-x fs-3"></i>
                        </a>
                    @endif
                </div>
                <div style="width: 100px;" class="flex-shrink-0">
                    <button type="submit" class="btn btn-sm btn-primary rounded-2 w-100 d-flex align-items-center justify-content-center gap-1" style="font-size: 0.78rem; height: 32px;">
                        <i class="ti ti-filter"></i>
                        <span>Filter</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($isSingleDay && $periodType === 'custom')
    <!-- Navigator Tanggal Harian (Jika Tampilan 1 Hari) -->
    <div class="card border-0 shadow-sm rounded-3 mb-2 bg-light">
        <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
            <a href="{{ route('attendance.report.teachers', ['period_type' => 'custom', 'start_date' => $prevDate, 'end_date' => $prevDate, 'sub_type' => $subType, 'search' => $search]) }}" 
               class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs external" style="width: 28px; height: 28px; padding: 0;">
                <i class="ti ti-chevron-left fs-3"></i>
            </a>
            <div class="text-center overflow-hidden">
                <div class="fw-bold text-dark small" style="font-size: 0.82rem;">
                    {{ $currentCarbon->translatedFormat('l, d F Y') }}
                </div>
                <div class="text-muted" style="font-size: 0.68rem;">
                    Rekap Presensi Harian GTK
                </div>
            </div>
            <a href="{{ route('attendance.report.teachers', ['period_type' => 'custom', 'start_date' => $nextDate, 'end_date' => $nextDate, 'sub_type' => $subType, 'search' => $search]) }}" 
               class="btn btn-sm btn-icon btn-white border rounded-circle shadow-xs external" style="width: 28px; height: 28px; padding: 0;">
                <i class="ti ti-chevron-right fs-3"></i>
            </a>
        </div>
    </div>
@endif

<!-- Ringkasan Statistik Rekapitulasi (Chips / Stat Bar) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-2.5">
        <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
            <span class="text-dark fw-bold" style="font-size: 0.78rem;">
                <i class="ti ti-chart-bar text-primary me-0.5"></i> Ringkasan {{ $periodLabel }}
            </span>
            <span class="badge bg-primary text-white" style="font-size: 0.65rem;">
                {{ $summary['total_people'] }} GTK
            </span>
        </div>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; text-align: center;">
            <div class="p-1.5 rounded-2 bg-success-lt border border-success" style="border-opacity: 0.3;">
                <div class="fw-bold text-success fs-3 lh-1">{{ $summary['total_hadir'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Hadir</div>
            </div>
            <div class="p-1.5 rounded-2 bg-warning-lt border border-warning" style="border-opacity: 0.3;">
                <div class="fw-bold text-warning fs-3 lh-1">{{ $summary['total_late'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Telat</div>
            </div>
            <div class="p-1.5 rounded-2 bg-warning-lt border border-warning" style="border-opacity: 0.3;">
                <div class="fw-bold text-warning fs-3 lh-1">{{ $summary['total_sakit'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Sakit</div>
            </div>
            <div class="p-1.5 rounded-2 bg-blue-lt border border-info" style="border-opacity: 0.3;">
                <div class="fw-bold text-info fs-3 lh-1">{{ $summary['total_izin'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Izin</div>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; text-align: center; margin-top: 4px;">
            <div class="p-1.5 rounded-2 bg-purple-lt border border-purple" style="border-opacity: 0.3;">
                <div class="fw-bold text-purple fs-3 lh-1">{{ $summary['total_cuti'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Cuti</div>
            </div>
            <div class="p-1.5 rounded-2 bg-blue-lt border border-primary" style="border-opacity: 0.3;">
                <div class="fw-bold text-primary fs-3 lh-1">{{ $summary['total_dinas_luar'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Dinas Luar</div>
            </div>
            <div class="p-1.5 rounded-2 bg-danger-lt border border-danger" style="border-opacity: 0.3;">
                <div class="fw-bold text-danger fs-3 lh-1">{{ $summary['total_alfa'] }}</div>
                <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Alfa</div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Rekap Kehadiran Guru & Staf -->
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
        <span class="fw-bold text-dark" style="font-size: 0.82rem;">
            <i class="ti ti-users-group text-primary me-0.5"></i> Daftar Rekap GTK ({{ count($people) }})
        </span>
        <a href="{{ route('attendance.report.teachers.print', array_merge(request()->query(), ['print_type' => 'status'])) }}" target="_blank" class="badge bg-white text-primary border external" style="font-size: 0.68rem; text-decoration: none;">
            <i class="ti ti-printer me-0.5"></i> Cetak A4
        </a>
    </div>

    @forelse($people as $idx => $p)
        @php
            $isPersonActive = ($selectedPerson && $selectedPerson->id === $p->id);
            $initials = collect(explode(' ', $p->name))->map(fn($part) => mb_substr($part, 0, 1))->take(2)->implode('');
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-2 {{ $isPersonActive ? 'border border-primary' : '' }}" style="{{ $isPersonActive ? 'border-width: 1.5px !important;' : '' }}">
            <div class="card-body p-2 px-3">
                <!-- Baris Identitas GTK -->
                <div class="d-flex align-items-center gap-2 overflow-hidden mb-1.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" 
                         style="width: 34px; height: 34px; font-size: 0.75rem; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
                        {{ strtoupper($initials) }}
                    </div>
                    <div class="overflow-hidden flex-fill">
                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;" title="{{ $p->name }}">
                            {{ $p->name }}
                        </div>
                        <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 0.7rem;">
                            <span class="badge {{ $p->is_staff ? 'bg-secondary-lt' : 'bg-primary-lt' }}" style="font-size: 0.6rem; padding: 1px 5px;">
                                {{ $p->category }}
                            </span>
                            <span>PIN: <code>{{ $p->pin }}</code></span>
                        </div>
                    </div>
                </div>

                <!-- Kapsul Rekap Absen: Klik untuk Membuka Rincian Riwayat -->
                <div class="p-1 px-2 rounded-2 bg-light border d-flex align-items-center justify-content-between text-center" 
                     onclick="toggleGtkDetail({{ $p->id }})" 
                     style="font-size: 0.74rem; cursor: pointer; user-select: none;" 
                     role="button" 
                     title="Klik untuk melihat rincian presensi">
                    <div class="d-flex align-items-center justify-content-around flex-fill pe-1">
                        <div><span class="text-muted">H:</span> <strong class="text-success">{{ $p->hadir }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">T:</span> <strong class="text-warning">{{ $p->terlambat }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">S:</span> <strong class="text-warning">{{ $p->sakit }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">I:</span> <strong class="text-info">{{ $p->izin }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">C:</span> <strong class="text-purple">{{ $p->cuti }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">DL:</span> <strong class="text-primary">{{ $p->dinas_luar }}</strong></div>
                        <span class="text-muted opacity-50">|</span>
                        <div><span class="text-muted">A:</span> <strong class="text-danger">{{ $p->alfa }}</strong></div>
                    </div>
                    <div class="ps-1.5 border-start d-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px;">
                        <i class="ti {{ $isPersonActive ? 'ti-chevron-down text-primary' : 'ti-chevron-right text-muted' }} fs-4" id="gtk-icon-{{ $p->id }}"></i>
                    </div>
                </div>

                <!-- Collapsible Rincian Presensi Harian -->
                <div id="gtk-detail-{{ $p->id }}" class="mt-2 pt-2 border-top" style="{{ $isPersonActive ? 'display: block;' : 'display: none;' }}">
                    <div class="d-flex align-items-center justify-content-between mb-1.5 px-0.5">
                        <span class="text-muted fw-bold" style="font-size: 0.72rem;">
                            <i class="ti ti-calendar-time me-0.5"></i> Riwayat Harian ({{ $p->records->count() }} Hari)
                        </span>
                        <a href="{{ route('attendance.report.teachers.print', array_merge(request()->query(), ['print_type' => 'detail', 'user_id' => $p->id])) }}" target="_blank" class="text-primary external text-decoration-none" style="font-size: 0.7rem;">
                            <i class="ti ti-printer me-0.5"></i> Cetak Detail
                        </a>
                    </div>

                    @forelse($p->records as $r)
                        @php
                            $badgeClass = match(true) {
                                str_contains(strtolower($r->status), 'terlambat') => 'bg-warning-lt text-warning border-warning',
                                str_contains(strtolower($r->status), 'tepat waktu') => 'bg-success-lt text-success border-success',
                                str_contains(strtolower($r->status), 'hadir') => 'bg-success-lt text-success border-success',
                                str_contains(strtolower($r->status), 'sakit') => 'bg-warning-lt text-warning border-warning',
                                str_contains(strtolower($r->status), 'izin') => 'bg-blue-lt text-info border-info',
                                str_contains(strtolower($r->status), 'cuti') => 'bg-purple-lt text-purple border-purple',
                                str_contains(strtolower($r->status), 'dinas luar') => 'bg-blue-lt text-primary border-primary',
                                str_contains(strtolower($r->status), 'alfa') => 'bg-danger-lt text-danger border-danger',
                                default => 'bg-secondary-lt text-secondary',
                            };
                        @endphp
                        <div class="p-1.5 rounded-2 bg-light border mb-1" style="font-size: 0.72rem;">
                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                <span class="fw-bold text-dark">
                                    {{ $r->day_name }}, {{ $r->date_formatted }}
                                </span>
                                <span class="badge {{ $badgeClass }}" style="font-size: 0.62rem; padding: 2px 6px;">
                                    {{ $r->status }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.68rem;">
                                <span>
                                    <i class="ti ti-login text-success me-0.5"></i> Masuk: <strong>{{ $r->jam_masuk }}</strong>
                                    <span class="mx-1">&bull;</span>
                                    <i class="ti ti-logout text-warning me-0.5"></i> Pulang: <strong>{{ $r->jam_pulang }}</strong>
                                </span>
                            </div>
                            @if(!empty($r->keterangan) && $r->keterangan !== '-')
                                <div class="text-secondary small mt-0.5 pt-0.5 border-top" style="font-size: 0.65rem;">
                                    <i class="ti ti-info-circle me-0.5 text-muted"></i> {{ $r->keterangan }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-2" style="font-size: 0.72rem;">
                            Tidak ada riwayat presensi tercatat untuk periode ini.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-users-minus fs-1 d-block mb-1 opacity-50"></i>
            <div class="fw-bold text-dark fs-3">Tidak Ada Data GTK</div>
            <div class="small px-3 mt-1">Tidak ditemukan data presensi guru &amp; staf untuk parameter yang dipilih.</div>
            <div class="mt-3">
                <a href="{{ route('attendance.report.teachers') }}" class="btn btn-sm btn-outline-primary external">
                    <i class="ti ti-rotate me-1"></i> Reset Filter
                </a>
            </div>
        </div>
    @endforelse
</div>

<script>
function toggleMobilePeriodFields() {
    const periodType = document.getElementById('periodTypeSelect').value;
    const fieldCustom = document.getElementById('fieldMobileCustom');
    const fieldMonthly = document.getElementById('fieldMobileMonthly');

    if (periodType === 'monthly') {
        fieldCustom.classList.add('d-none');
        fieldMonthly.classList.remove('d-none');
    } else {
        fieldCustom.classList.remove('d-none');
        fieldMonthly.classList.add('d-none');
    }
}

function toggleGtkDetail(id) {
    const el = document.getElementById('gtk-detail-' + id);
    const icon = document.getElementById('gtk-icon-' + id);
    if (!el) return;

    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
        if (icon) {
            icon.classList.remove('ti-chevron-right', 'text-muted');
            icon.classList.add('ti-chevron-down', 'text-primary');
        }
    } else {
        el.style.display = 'none';
        if (icon) {
            icon.classList.remove('ti-chevron-down', 'text-primary');
            icon.classList.add('ti-chevron-right', 'text-muted');
        }
    }
}
</script>
@endsection
