@extends('layouts.app')

@section('title', 'Laporan Presensi Siswa')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Rekapitulasi Presensi Individu Siswa</div>
                <h2 class="page-title d-flex align-items-center">
                    <i class="ti ti-users-group me-2 text-indigo"></i> Presensi Siswa Per-Kelas
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Ke Mesin Absensi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">

        <!-- Form Filter Periode & Kelas -->
        <div class="card shadow-sm border-0 mb-3 d-print-none">
            <div class="card-header bg-light py-2">
                <h4 class="card-title mb-0 small text-uppercase fw-bold text-secondary">
                    <i class="ti ti-filter me-1 text-primary"></i> Parameter Filter Rekap Presensi Siswa
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.report.students') }}" id="reportFilterForm">
                    <!-- Baris 1: Pilihan Jenis Periode & Kelas -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Jenis Periode</label>
                            <select name="period_type" id="periodTypeSelect" class="form-select form-select-sm" onchange="togglePeriodFields()">
                                <option value="monthly" {{ $periodType === 'monthly' ? 'selected' : '' }}>Bulanan (Pilih Bulan)</option>
                                <option value="weekly" {{ $periodType === 'weekly' ? 'selected' : '' }}>Mingguan (Pilih Minggu)</option>
                                <option value="semester" {{ $periodType === 'semester' ? 'selected' : '' }}>Semester (Ganjil / Genap)</option>
                                <option value="yearly" {{ $periodType === 'yearly' ? 'selected' : '' }}>Tahunan (Penuh 1 Tahun)</option>
                                <option value="custom" {{ $periodType === 'custom' ? 'selected' : '' }}>Periode Tertentu (Rentang Tanggal)</option>
                            </select>
                        </div>

                        <!-- 1. Bulanan -->
                        <div class="col-md-5 period-field" id="field-monthly">
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-bold">Bulan</label>
                                    <select name="month" class="form-select form-select-sm" onchange="updatePrintUrl()">
                                        @foreach(range(1, 12) as $m)
                                            <option value="{{ $m }}" {{ (int)($dateInfo['month'] ?? now()->month) === $m ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-bold">Tahun</label>
                                    <input type="number" name="year" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="updatePrintUrl()">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Mingguan -->
                        <div class="col-md-5 period-field d-none" id="field-weekly">
                            <label class="form-label small fw-bold">Pilih Tanggal Acuan Minggu</label>
                            <input type="date" name="week_date" class="form-control form-control-sm" value="{{ $dateInfo['weekDate'] ?? now()->toDateString() }}" onchange="updatePrintUrl()">
                        </div>

                        <!-- 3. Semester -->
                        <div class="col-md-5 period-field d-none" id="field-semester">
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-bold">Semester</label>
                                    <select name="semester" class="form-select form-select-sm" onchange="updatePrintUrl()">
                                        <option value="1" {{ ($dateInfo['semester'] ?? '1') === '1' ? 'selected' : '' }}>Semester Ganjil (Juli - Des)</option>
                                        <option value="2" {{ ($dateInfo['semester'] ?? '1') === '2' ? 'selected' : '' }}>Semester Genap (Jan - Juni)</option>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-bold">Tahun</label>
                                    <input type="number" name="year_sem" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="document.querySelector('input[name=year]').value=this.value; updatePrintUrl();">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Tahunan -->
                        <div class="col-md-5 period-field d-none" id="field-yearly">
                            <label class="form-label small fw-bold">Tahun</label>
                            <input type="number" name="year_annual" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="document.querySelector('input[name=year]').value=this.value; updatePrintUrl();">
                        </div>

                        <!-- 5. Kustom / Periode Tertentu -->
                        <div class="col-md-5 period-field d-none" id="field-custom">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Dari Tanggal</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" onchange="updatePrintUrl()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" onchange="updatePrintUrl()">
                                </div>
                            </div>
                        </div>

                        <!-- Pilihan Kelas -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-indigo">
                                <i class="ti ti-building me-1"></i> Pilih Rombel / Kelas
                            </label>
                            <select name="classroom_id" id="classroomIdSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                                @if(!$isHomeroomOnly)
                                    <option value="">-- Semua Kelas --</option>
                                @endif
                                @foreach($classrooms as $c)
                                    <option value="{{ $c->id }}" {{ (int)$classroomId === $c->id ? 'selected' : '' }}>
                                        Kelas {{ $c->name }} (Wali: {{ $c->homeroomTeacher?->name ?? 'Belum Ditentukan' }})
                                    </option>
                                @endforeach
                            </select>
                            @if($isHomeroomOnly)
                                <div class="form-hint small text-muted">Mode Wali Kelas: Anda hanya memiliki akses ke kelas binaan Anda.</div>
                            @endif
                        </div>
                    </div>

                    <!-- Baris 2: Cari & Tombol -->
                    <input type="hidden" name="student_id" value="{{ $studentId }}">
                    <div class="row g-3 align-items-end pt-2 border-top">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Cari Nama Siswa / NISN / ID</label>
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Ketik nama atau NISN siswa..." value="{{ $search }}">
                            </div>
                        </div>

                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-indigo btn-sm flex-fill">
                                <i class="ti ti-filter me-1"></i> Tampilkan Rekap
                            </button>
                            <a href="{{ route('attendance.report.students') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                <i class="ti ti-rotate me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kartu Statistik Rekapitulasi Siswa -->
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-indigo">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-indigo text-white avatar"><i class="ti ti-users fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="fs-2 fw-bold text-dark">{{ $summary['total_students'] }} Siswa</div>
                                <div class="text-secondary small">{{ $selectedClassroom ? 'Total Siswa di Kelas '.$selectedClassroom->name : 'Total Siswa Terpilih' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-success text-white avatar"><i class="ti ti-user-check fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="fs-2 fw-bold text-dark">{{ $summary['active_attendees'] }} Siswa</div>
                                <div class="text-secondary small">Pernah Scan Hadir</div>
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
                                <span class="bg-primary text-white avatar"><i class="ti ti-calendar-check fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="fs-2 fw-bold text-dark">{{ $summary['total_days_attended'] }} Hari</div>
                                <div class="text-secondary small">Total Kehadiran Siswa</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-warning text-white avatar"><i class="ti ti-clock-pause fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="fs-2 fw-bold text-dark">{{ $summary['total_late'] }} Hari</div>
                                <div class="text-secondary small">Total Keterlambatan Tercatat</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL REKAPITULASI STATUS KEHADIRAN SISWA -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-teal text-white d-flex justify-content-between align-items-center py-2">
                <h3 class="card-title text-white mb-0">
                    <i class="ti ti-table me-1"></i> Rekap Status Kehadiran Siswa {{ $selectedClassroom ? ' - Kelas '.$selectedClassroom->name : '' }}
                </h3>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-teal">
                        Periode: {{ $periodLabel }}
                    </span>
                    <a href="{{ route('attendance.report.students.print', array_merge(request()->query(), ['print_type' => 'status'])) }}" target="_blank" id="btnPrintStatus" class="btn btn-sm btn-light text-teal fw-bold">
                        <i class="ti ti-printer me-1"></i> Cetak A4
                    </a>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-vcenter table-hover table-bordered card-table text-nowrap">
                    <thead class="table-light sticky-top">
                        <tr class="text-center align-middle">
                            <th style="width: 50px;">NO</th>
                            <th style="width: 120px;">ID</th>
                            <th class="text-start">NAMA</th>
                            <th style="width: 80px;" class="text-success">HADIR</th>
                            <th style="width: 80px;" class="text-warning">SAKIT</th>
                            <th style="width: 80px;" class="text-info">IZIN</th>
                            <th style="width: 80px;" class="text-danger">ALFA</th>
                            <th style="width: 90px;" class="text-purple">PULANG</th>
                            <th style="width: 95px;" class="text-secondary">TERLAMBAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($people as $idx => $p)
                            <tr class="align-middle text-center {{ ($selectedPerson && $selectedPerson->id === $p->id) ? 'table-active border-indigo border-start border-3' : '' }}">
                                <td class="text-secondary">{{ $idx + 1 }}</td>
                                <td><code>{{ $p->pin }}</code></td>
                                <td class="text-start fw-normal">
                                    <a href="javascript:void(0)" onclick="navigateToStudent('{{ $p->id }}')" class="text-reset text-decoration-none fw-semibold d-inline-flex align-items-center" title="Pilih {{ $p->name }} untuk melihat rincian">
                                        {{ $p->name }}
                                        <i class="ti ti-chevron-right ms-1 text-muted small"></i>
                                    </a>
                                </td>
                                <td class="text-success">{{ $p->hadir }}</td>
                                <td class="text-warning">{{ $p->sakit }}</td>
                                <td class="text-info">{{ $p->izin }}</td>
                                <td class="text-danger">{{ $p->alfa }}</td>
                                <td class="text-purple">{{ $p->pulang }}</td>
                                <td class="text-secondary">{{ $p->terlambat }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">Tidak ada data untuk ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($people) > 0)
                        <tfoot class="table-light fw-bold text-center">
                            <tr>
                                <td colspan="3" class="text-end">TOTAL :</td>
                                <td class="text-success">{{ $summary['total_hadir'] }}</td>
                                <td class="text-warning">{{ $summary['total_sakit'] }}</td>
                                <td class="text-info">{{ $summary['total_izin'] }}</td>
                                <td class="text-danger">{{ $summary['total_alfa'] }}</td>
                                <td class="text-purple">{{ $summary['total_pulang'] }}</td>
                                <td class="text-secondary">{{ $summary['total_terlambat'] }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <!-- RINCIAN PRESENSI INDIVIDU SISWA -->
        <div id="rincian-individu" class="card shadow-sm border-0 mb-4">
            <!-- Card Header -->
            <div class="card-header bg-indigo text-white d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
                <h3 class="card-title text-white mb-0">
                    <i class="ti ti-list-details me-1"></i> Rincian Presensi Individu
                    @if($selectedPerson)
                        : {{ $selectedPerson->name }}
                    @endif
                </h3>
                @if($selectedPerson)
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white text-indigo">
                            Kelas {{ $selectedPerson->classroom_name }}
                        </span>
                        <a href="{{ route('attendance.report.students.print', array_merge(request()->query(), ['print_type' => 'detail', 'student_id' => $selectedPerson->id])) }}" target="_blank" id="btnPrintDetail" class="btn btn-sm btn-light text-indigo fw-bold">
                            <i class="ti ti-printer me-1"></i> Cetak A4
                        </a>
                        <button type="button" onclick="navigateToStudent(null)" class="btn btn-sm btn-outline-light" title="Reset Pilihan">
                            <i class="ti ti-x me-1"></i> Reset Pilihan
                        </button>
                    </div>
                @endif
            </div>

            <!-- Dropdown Toolbar -->
            <div class="card-body border-bottom py-2 bg-indigo-lt">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label mb-0 fw-semibold text-indigo text-nowrap" for="selectIndividualStudent">
                        <i class="ti ti-users me-1"></i> Pilih Siswa:
                    </label>
                    <select id="selectIndividualStudent" class="form-select form-select-sm" style="max-width: 380px;"
                        onchange="navigateToStudent(this.value || null)">
                        <option value="">-- Pilih Nama Siswa --</option>
                        @foreach($people as $pIdx => $p)
                            <option value="{{ $p->id }}"
                                {{ ($selectedPerson && $selectedPerson->id == $p->id) ? 'selected' : '' }}>
                                {{ $pIdx + 1 }}. {{ $p->name }} (NISN: {{ $p->pin }})
                            </option>
                        @endforeach
                    </select>
                    @if($selectedPerson)
                        <span class="badge bg-indigo text-white">
                            <i class="ti ti-check me-1"></i> Aktif: {{ $selectedPerson->name }}
                        </span>
                    @endif
                </div>
            </div>

            @if($selectedPerson)
                <!-- Header Per-Siswa -->
                <div class="card-header bg-light py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-md bg-indigo-lt text-indigo fw-bold">
                            <i class="ti ti-user fs-2"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark fs-3">
                                {{ $selectedPerson->name }}
                            </div>
                            <div class="text-secondary small">
                                NISN / ID: <code>{{ $selectedPerson->pin }}</code> &bull; Kelas: <strong>{{ $selectedPerson->classroom_name }}</strong>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="badge bg-success-lt">Hadir: {{ $selectedPerson->hadir }}</span>
                                <span class="badge bg-warning-lt">Sakit: {{ $selectedPerson->sakit }}</span>
                                <span class="badge bg-info-lt">Izin: {{ $selectedPerson->izin }}</span>
                                <span class="badge bg-danger-lt">Alfa: {{ $selectedPerson->alfa }}</span>
                                <span class="badge bg-purple-lt">Pulang Cepat: {{ $selectedPerson->pulang }}</span>
                                <span class="badge bg-secondary-lt">Terlambat: {{ $selectedPerson->terlambat }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-indigo text-white px-2 py-1">
                            Periode: {{ $periodLabel }}
                        </div>
                        <div class="text-secondary small mt-1">
                            Total Hadir: <strong class="text-indigo">{{ $selectedPerson->hadir }} Hari</strong>
                        </div>
                    </div>
                </div>

                <!-- Tabel Riwayat Presensi Individu Siswa -->
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table table-striped-columns">
                        <thead>
                            <tr class="bg-white">
                                <th class="w-1 text-center">No</th>
                                <th style="width: 110px;">Hari</th>
                                <th style="width: 120px;">Tanggal</th>
                                <th class="text-center text-success" style="width: 120px;"><i class="ti ti-login me-1"></i> Jam Masuk</th>
                                <th class="text-center text-warning" style="width: 120px;"><i class="ti ti-logout me-1"></i> Jam Pulang</th>
                                <th style="width: 160px;">Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($selectedPerson->records as $rIdx => $r)
                                <tr>
                                    <td class="text-center text-secondary">{{ $rIdx + 1 }}</td>
                                    <td>{{ $r->day_name }}</td>
                                    <td>{{ $r->date_formatted }}</td>
                                    <td class="text-center font-monospace">{{ $r->jam_masuk }}</td>
                                    <td class="text-center font-monospace">{{ $r->jam_pulang }}</td>
                                    <td>
                                        @if(str_contains($r->status, 'Terlambat'))
                                            <span class="badge bg-danger-lt fw-normal">{{ $r->status }}</span>
                                        @elseif(str_contains($r->status, 'Hari Libur'))
                                            <span class="badge bg-azure-lt fw-normal">{{ $r->status }}</span>
                                        @elseif(str_contains($r->status, 'Tepat Waktu') || $r->status === 'Hadir')
                                            <span class="badge bg-success-lt fw-normal">{{ $r->status }}</span>
                                        @elseif($r->status === 'Alfa')
                                            <span class="badge bg-danger text-white fw-bold">Alfa</span>
                                        @elseif($r->status === 'Sakit')
                                            <span class="badge bg-warning text-dark fw-normal">Sakit</span>
                                        @elseif($r->status === 'Izin')
                                            <span class="badge bg-info-lt text-dark fw-normal">Izin</span>
                                        @elseif($r->status === 'Pulang')
                                            <span class="badge bg-orange-lt text-dark fw-normal">Pulang</span>
                                        @else
                                            <span class="badge bg-secondary-lt fw-normal">{{ $r->status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($r->keterangan) && $r->keterangan !== '-')
                                            <span class="text-secondary small">{{ $r->keterangan }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-secondary">
                                        <em>Belum ada rekaman presensi masuk/pulang untuk {{ $selectedPerson->name }} pada periode {{ $periodLabel }}.</em>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Placeholder saat belum memilih individu siswa -->
                <div class="card-body text-center py-5 bg-light-subtle">
                    <span class="avatar avatar-lg bg-indigo-lt text-indigo mb-3">
                        <i class="ti ti-user-search fs-1"></i>
                    </span>
                    <h4 class="fw-bold text-dark mb-1">Pilih Nama pada Dropdown di Atas</h4>
                    <p class="text-secondary small mb-0" style="max-width: 480px; margin: 0 auto;">
                        Gunakan dropdown di atas untuk memilih siswa dari kelas yang sedang ditampilkan.
                    </p>
                </div>
            @endif
        </div>

    </div>
</div>

<script>
    function navigateToStudent(id) {
        const url = new URL(window.location.href);
        if (id) {
            url.searchParams.set('student_id', id);
        } else {
            url.searchParams.delete('student_id');
        }
        url.hash = 'rincian-individu';
        window.location.href = url.toString();
    }

    function togglePeriodFields() {
        const periodType = document.getElementById('periodTypeSelect').value;
        document.querySelectorAll('.period-field').forEach(el => {
            el.classList.add('d-none');
            el.querySelectorAll('input, select').forEach(inp => inp.disabled = true);
        });

        const targetField = document.getElementById('field-' + periodType);
        if (targetField) {
            targetField.classList.remove('d-none');
            targetField.querySelectorAll('input, select').forEach(inp => inp.disabled = false);
        }
        updatePrintUrl();
    }

    function updatePrintUrl() {
        const form = document.getElementById('reportFilterForm');
        if (!form) return;

        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [k, v] of formData.entries()) {
            if (v !== '' && v !== null) {
                params.append(k, v);
            }
        }

        const printBaseUrl = "{{ route('attendance.report.students.print') }}";

        // Cetak Status Kehadiran Saja
        const statusParams = new URLSearchParams(params);
        statusParams.set('print_type', 'status');
        statusParams.delete('student_id');
        const btnStatus = document.getElementById('btnPrintStatus');
        if (btnStatus) btnStatus.href = printBaseUrl + '?' + statusParams.toString();

        // Cetak Rincian Presensi Individu Saja
        const detailParams = new URLSearchParams(params);
        detailParams.set('print_type', 'detail');
        @if($selectedPerson)
            detailParams.set('student_id', '{{ $selectedPerson->id }}');
        @endif
        const btnDetail = document.getElementById('btnPrintDetail');
        if (btnDetail) btnDetail.href = printBaseUrl + '?' + detailParams.toString();
    }

    document.addEventListener('DOMContentLoaded', function() {
        togglePeriodFields();
        updatePrintUrl();

        const form = document.getElementById('reportFilterForm');
        if (form) {
            form.addEventListener('change', updatePrintUrl);
            form.addEventListener('input', updatePrintUrl);
        }
    });
</script>
@endsection
