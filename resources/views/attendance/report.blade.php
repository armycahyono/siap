@extends('layouts.app')

@section('title', 'Laporan Rekapitulasi Presensi')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Laporan & Rekapitulasi Resmi</div>
                <h2 class="page-title d-flex align-items-center">
                    <i class="ti ti-file-analytics me-2 text-indigo"></i> Rekap & Laporan Presensi
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Ke Mesin Absensi
                    </a>
                    <a href="{{ route('attendance.report.print', request()->query()) }}" target="_blank" id="btnPrintTop" onclick="openPrintReport(event)" class="btn btn-sm btn-teal">
                        <i class="ti ti-printer me-1"></i> Cetak Lembar Resmi (A4)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">

        <!-- Form Filter Periode & Pengguna -->
        <div class="card shadow-sm border-0 mb-3 d-print-none">
            <div class="card-header bg-light py-2">
                <h4 class="card-title mb-0 small text-uppercase fw-bold text-secondary">
                    <i class="ti ti-filter me-1 text-primary"></i> Parameter Filter Rekapitulasi
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.report') }}" id="reportFilterForm">
                    <!-- Baris 1: Pilihan Jenis Periode & Kelompok User -->
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

                        <!-- Kolom Dinamis Berdasarkan Jenis Periode -->
                        <!-- 1. Bulanan -->
                        <div class="col-md-5 period-field" id="field-monthly">
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-bold">Bulan</label>
                                    <select name="month" class="form-select form-select-sm">
                                        @foreach(range(1, 12) as $m)
                                            <option value="{{ $m }}" {{ (int)($dateInfo['month'] ?? now()->month) === $m ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-bold">Tahun</label>
                                    <input type="number" name="year" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Mingguan -->
                        <div class="col-md-5 period-field d-none" id="field-weekly">
                            <label class="form-label small fw-bold">Pilih Tanggal Acuan Minggu</label>
                            <input type="date" name="week_date" class="form-control form-control-sm" value="{{ $dateInfo['weekDate'] ?? now()->toDateString() }}">
                        </div>

                        <!-- 3. Semester -->
                        <div class="col-md-5 period-field d-none" id="field-semester">
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-bold">Semester</label>
                                    <select name="semester" class="form-select form-select-sm">
                                        <option value="1" {{ ($dateInfo['semester'] ?? '1') === '1' ? 'selected' : '' }}>Semester Ganjil (Juli - Des)</option>
                                        <option value="2" {{ ($dateInfo['semester'] ?? '1') === '2' ? 'selected' : '' }}>Semester Genap (Jan - Juni)</option>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-bold">Tahun</label>
                                    <input type="number" name="year_sem" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="document.querySelector('input[name=year]').value=this.value">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Tahunan -->
                        <div class="col-md-5 period-field d-none" id="field-yearly">
                            <label class="form-label small fw-bold">Tahun</label>
                            <input type="number" name="year_annual" class="form-control form-control-sm" value="{{ $dateInfo['year'] ?? now()->year }}" min="2020" max="2035" onchange="document.querySelector('input[name=year]').value=this.value">
                        </div>

                        <!-- 5. Kustom / Periode Tertentu -->
                        <div class="col-md-5 period-field d-none" id="field-custom">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Dari Tanggal</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                                </div>
                            </div>
                        </div>

                        <!-- Kelompok Pengguna -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kelompok Pengguna</label>
                            <select name="user_type" id="userTypeSelect" class="form-select form-select-sm" onchange="toggleUserSelects()">
                                <option value="all" {{ $userType === 'all' ? 'selected' : '' }}>Semua Pengguna</option>
                                <option value="teacher" {{ $userType === 'teacher' ? 'selected' : '' }}>Guru dan Tenaga Kependidikan</option>
                                <option value="student" {{ $userType === 'student' ? 'selected' : '' }}>Siswa</option>
                            </select>
                        </div>
                    </div>

                    <!-- Baris 2: Filter Perorangan (Spesifik Nama) & Pencarian -->
                    <div class="row g-3 align-items-end pt-2 border-top">
                        <!-- Dropdown Guru / Staf Spesifik -->
                        <div class="col-md-4" id="selectTeacherCol">
                            <label class="form-label small fw-bold text-teal">
                                <i class="ti ti-user me-1"></i> Rekap Per-Guru & Staf (Opsional)
                            </label>
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">-- Semua Guru & Staf --</option>
                                @foreach($teachersList as $t)
                                    <option value="{{ $t->id }}" {{ (int)$userId === $t->id ? 'selected' : '' }}>
                                        {{ $t->name }} ({{ $t->nigs_fix ?: ($t->nigs ?: 'ID: '.$t->id) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Dropdown Siswa Spesifik -->
                        <div class="col-md-4 d-none" id="selectStudentCol">
                            <label class="form-label small fw-bold text-indigo">
                                <i class="ti ti-users me-1"></i> Rekap Per-Siswa (Opsional)
                            </label>
                            <select name="student_id" class="form-select form-select-sm">
                                <option value="">-- Semua Siswa --</option>
                                @foreach($studentsList as $s)
                                    <option value="{{ $s->id }}" {{ (int)$studentId === $s->id ? 'selected' : '' }}>
                                        {{ $s->name }} ({{ $s->classroom?->name ?: '-' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Cari Cepat Nama / PIN -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Cari Nama / ID / PIN</label>
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Ketik nama atau PIN..." value="{{ $search }}">
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="ti ti-filter me-1"></i> Tampilkan Laporan
                            </button>
                            <a href="{{ route('attendance.report') }}" class="btn btn-outline-secondary btn-sm" title="Reset Semua Filter">
                                <i class="ti ti-rotate me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Banner Profil Rekapitulasi Individu (Jika Memilih User Tertentu) -->
        @if($selectedProfile)
            <div class="card bg-teal-lt border-teal mb-3 shadow-sm">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md bg-teal text-white">
                                <i class="ti ti-user-check fs-2"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-uppercase small fw-bold text-teal">Rekapitulasi Presensi Individu</div>
                            <h3 class="card-title fw-bold text-dark mb-0">{{ $selectedProfile->name }}</h3>
                            <div class="text-secondary small mt-1">
                                <span class="me-3"><strong>ID / NIP:</strong> {{ $selectedProfile->pin_nip }}</span>
                                <span class="me-3"><strong>Jabatan / Kelas:</strong> {{ $selectedProfile->position }}</span>
                                <span><strong>Kategori:</strong> {{ $selectedProfile->category }}</span>
                            </div>
                        </div>
                        <div class="col-auto text-end">
                            <div class="h2 mb-0 fw-bold text-teal">{{ $summary['total_recap_rows'] }} Hari</div>
                            <div class="text-secondary small">Total Kehadiran Periode Ini</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Kartu Statistik Rekapitulasi -->
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar"><i class="ti ti-calendar-check fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium fs-2 fw-bold text-dark">{{ $summary['total_recap_rows'] }}</div>
                                <div class="text-secondary small">Total Hari Kehadiran</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm shadow-sm border-0 border-start border-3 border-teal">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-teal text-white avatar"><i class="ti ti-users fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium fs-2 fw-bold text-dark">{{ $summary['unique_people'] }}</div>
                                <div class="text-secondary small">Orang Tercatat</div>
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
                                <span class="bg-success text-white avatar"><i class="ti ti-door-exit fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium fs-2 fw-bold text-dark">{{ $summary['total_with_checkout'] }}</div>
                                <div class="text-secondary small">Presensi Lengkap (Scan Pulang)</div>
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
                                <div class="font-weight-medium fs-2 fw-bold text-dark">{{ $summary['total_late'] }}</div>
                                <div class="text-secondary small">Keterlambatan Tercatat</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Rekapitulasi Presensi (1 Baris per Orang per Hari) -->
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between py-2 gap-2">
                <div>
                    <h3 class="card-title fw-bold mb-0">Tabel Rekapitulasi Presensi Resmi</h3>
                    <div class="text-secondary small">
                        Periode: <strong class="text-dark">{{ $periodLabel }}</strong>
                        &bull; Kategori: <strong>{{ match($userType) { 'teacher' => 'Guru dan Tenaga Kependidikan', 'student' => 'Siswa', default => 'Semua Pengguna' } }}</strong>
                        @if($selectedProfile)
                            &bull; Individu: <strong class="text-teal">{{ $selectedProfile->name }}</strong>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-lt">{{ $paginatedLogs->total() }} baris rekap</span>
                    <a href="{{ route('attendance.report.print', request()->query()) }}" target="_blank" id="btnPrintTable" onclick="openPrintReport(event)" class="btn btn-sm btn-teal">
                        <i class="ti ti-printer me-1"></i> Cetak Dokumen Ini
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table">
                    <thead>
                        <tr>
                            <th class="w-1 text-center">No</th>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>ID / PIN</th>
                            <th>Nama Lengkap</th>
                            <th>Kategori</th>
                            <th class="text-center text-success"><i class="ti ti-login me-1"></i> Jam Masuk</th>
                            <th class="text-center text-warning"><i class="ti ti-logout me-1"></i> Jam Pulang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedLogs as $idx => $row)
                            <tr>
                                <td class="text-center text-secondary">{{ $paginatedLogs->firstItem() + $idx }}</td>
                                <td>{{ $row->day_name }}</td>
                                <td>{{ $row->date_formatted }}</td>
                                <td><code>{{ $row->pin }}</code></td>
                                <td>{{ $row->name }}</td>
                                <td>
                                    <span class="badge {{ $row->user_type === 'teacher' ? 'bg-teal-lt' : 'bg-indigo-lt' }}">
                                        {{ $row->category }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span>{{ $row->jam_masuk }}</span>
                                </td>
                                <td class="text-center">
                                    @if($row->jam_pulang !== '-')
                                        <span>{{ $row->jam_pulang }}</span>
                                    @else
                                        <span class="text-secondary small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(str_contains($row->status, 'Terlambat'))
                                        <span class="badge bg-danger-lt fw-normal">{{ $row->status }}</span>
                                    @elseif(str_contains($row->status, 'Hari Libur'))
                                        <span class="badge bg-azure-lt fw-normal">{{ $row->status }}</span>
                                    @elseif(str_contains($row->status, 'Tepat Waktu'))
                                        <span class="badge bg-success-lt fw-normal">{{ $row->status }}</span>
                                    @else
                                        <span class="badge bg-secondary-lt fw-normal">{{ $row->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="empty">
                                        <div class="empty-icon text-muted"><i class="ti ti-file-off fs-1"></i></div>
                                        <p class="empty-title">Tidak ada data rekapitulasi presensi pada periode ini</p>
                                        <p class="empty-subtitle text-secondary">
                                            Silakan sesuaikan pilihan periode, bulan, atau nama pengguna yang dicari.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginatedLogs->hasPages())
                <div class="card-footer d-flex align-items-center justify-content-between py-2">
                    <p class="m-0 text-secondary small">
                        Menampilkan {{ $paginatedLogs->firstItem() }} s/d {{ $paginatedLogs->lastItem() }} dari {{ $paginatedLogs->total() }} entri
                    </p>
                    {{ $paginatedLogs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function togglePeriodFields() {
        const periodType = document.getElementById('periodTypeSelect').value;
        document.querySelectorAll('.period-field').forEach(el => el.classList.add('d-none'));

        const targetField = document.getElementById('field-' + periodType);
        if (targetField) {
            targetField.classList.remove('d-none');
        }
        updatePrintLinks();
    }

    function toggleUserSelects() {
        const userType = document.getElementById('userTypeSelect').value;
        const teacherCol = document.getElementById('selectTeacherCol');
        const studentCol = document.getElementById('selectStudentCol');

        if (userType === 'teacher') {
            teacherCol.classList.remove('d-none');
            studentCol.classList.add('d-none');
        } else if (userType === 'student') {
            teacherCol.classList.add('d-none');
            studentCol.classList.remove('d-none');
        } else {
            teacherCol.classList.remove('d-none');
            studentCol.classList.add('d-none');
        }
        updatePrintLinks();
    }

    function getPrintUrl() {
        const form = document.getElementById('reportFilterForm');
        const params = new URLSearchParams();

        if (form) {
            const periodType = document.getElementById('periodTypeSelect')?.value || 'monthly';
            params.set('period_type', periodType);

            if (periodType === 'monthly') {
                const month = form.querySelector('select[name="month"]')?.value;
                const year = form.querySelector('input[name="year"]')?.value;
                if (month) params.set('month', month);
                if (year) params.set('year', year);
            } else if (periodType === 'weekly') {
                const weekDate = form.querySelector('input[name="week_date"]')?.value;
                if (weekDate) params.set('week_date', weekDate);
            } else if (periodType === 'semester') {
                const sem = form.querySelector('select[name="semester"]')?.value;
                const yearSem = form.querySelector('input[name="year_sem"]')?.value;
                if (sem) params.set('semester', sem);
                if (yearSem) params.set('year', yearSem);
            } else if (periodType === 'yearly') {
                const yearAnnual = form.querySelector('input[name="year_annual"]')?.value;
                if (yearAnnual) params.set('year', yearAnnual);
            } else if (periodType === 'custom') {
                const startDate = form.querySelector('input[name="start_date"]')?.value;
                const endDate = form.querySelector('input[name="end_date"]')?.value;
                if (startDate) params.set('start_date', startDate);
                if (endDate) params.set('end_date', endDate);
            }

            const userType = document.getElementById('userTypeSelect')?.value || 'all';
            params.set('user_type', userType);

            const userId = form.querySelector('select[name="user_id"]')?.value;
            if (userId) params.set('user_id', userId);

            const studentId = form.querySelector('select[name="student_id"]')?.value;
            if (studentId) params.set('student_id', studentId);

            const search = form.querySelector('input[name="search"]')?.value;
            if (search) params.set('search', search);
        }

        return "{{ route('attendance.report.print') }}?" + params.toString();
    }

    function updatePrintLinks() {
        const url = getPrintUrl();
        const btnTop = document.getElementById('btnPrintTop');
        const btnTable = document.getElementById('btnPrintTable');
        if (btnTop) btnTop.href = url;
        if (btnTable) btnTable.href = url;
    }

    function openPrintReport(e) {
        if (e) {
            e.preventDefault();
        }
        const url = getPrintUrl();
        window.open(url, '_blank');
    }

    document.addEventListener('DOMContentLoaded', function() {
        togglePeriodFields();
        toggleUserSelects();
        updatePrintLinks();

        const form = document.getElementById('reportFilterForm');
        if (form) {
            form.addEventListener('change', updatePrintLinks);
            form.addEventListener('input', updatePrintLinks);
        }
    });
</script>
@endsection
