@extends('layouts.app')

@section('title', 'Kenaikan Kelas & Penataan Rombel — Data Pokok')

@section('content')
<!-- Header Halaman -->
<div class="page-header d-print-none mb-4">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle text-uppercase tracking-wider text-muted">
                Kesiswaan & Rombongan Belajar
            </div>
            <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                <i class="ti ti-arrow-up-right-circle text-primary me-2 fs-1"></i>
                Kenaikan Kelas, Kelulusan & Penataan Rombel
            </h2>
            <div class="text-secondary mt-1">
                Alat transisi tahun ajaran serentak se-sekolah, penanganan siswa tinggal/pindah rombel, serta pemindahan kelas cepat.
            </div>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Kembali ke Data Siswa
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible shadow-sm mb-4" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-check fs-2 me-2"></i></div>
            <div>
                <h4 class="alert-title fw-bold">Berhasil!</h4>
                <div class="text-secondary">{{ session('success') }}</div>
            </div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible shadow-sm mb-4" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-alert-circle fs-2 me-2"></i></div>
            <div>
                <h4 class="alert-title fw-bold">Gagal!</h4>
                <div class="text-secondary">{{ session('error') }}</div>
            </div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible shadow-sm mb-4" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-alert-triangle fs-2 me-2"></i></div>
            <div>
                <h4 class="alert-title fw-bold">Terjadi Kesalahan Validasi!</h4>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<!-- Stat Cards Ringkasan Kenaikan & Penataan Rombel (Diletakkan di Atas Tab) -->
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-primary border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold text-uppercase">Total Siswa Aktif</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $allActiveStudents->count() }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-azure border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold text-uppercase">Total Rombel Berjalan</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $sourceClassrooms->count() }} <span class="fs-4 text-muted font-weight-normal">kelas</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-success border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold text-uppercase">Proyeksi Naik Kelas (X & XI)</div>
                <div class="h2 fw-bold text-success mb-0">{{ $rolloverMatrix->where('is_grade_12', false)->sum('active_students_count') }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-warning border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold text-uppercase">Proyeksi Kelulusan (Kls XII)</div>
                <div class="h2 fw-bold text-warning mb-0">{{ $rolloverMatrix->where('is_grade_12', true)->sum('active_students_count') }} <span class="fs-4 text-muted font-weight-normal">alumni</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Navigasi Tab Mode Kenaikan & Pindah Rombel -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link py-2 {{ $tab === 'rollover' ? 'active fw-bold' : 'text-muted' }}" 
                   href="{{ route('students.promotions.index', ['tab' => 'rollover', 'source_academic_year' => $sourceAcademicYear, 'target_academic_year' => $targetAcademicYear]) }}">
                    <i class="ti ti-rocket me-2 fs-3 text-warning"></i> 
                    1. Transisi Massal 1-Sekolah (Rollover Cepat)
                    <span class="badge {{ $tab === 'rollover' ? 'bg-white text-primary' : 'bg-success-lt' }} ms-2">Direkomendasikan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 {{ $tab === 'transfer' ? 'active fw-bold' : 'text-muted' }}" 
                   href="{{ route('students.promotions.index', ['tab' => 'transfer']) }}">
                    <i class="ti ti-arrows-exchange me-2 fs-3 text-primary"></i> 
                    2. Pindah Rombel Siswa (Kapan Saja)
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: TRANSISI MASSAL 1-SEKOLAH (ROLLOVER WIZARD) -->
<!-- ========================================================================= -->
@if($tab === 'rollover')
    @if($targetYearActiveStudentsCount > 0)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <div class="d-flex">
                <i class="ti ti-alert-triangle fs-1 me-3 text-warning"></i>
                <div>
                    <h4 class="alert-title fw-bold">Perhatian: Tahun Ajaran Tujuan Sudah Memiliki Data Siswa Aktif</h4>
                    <div class="text-secondary">
                        Terdeteksi sebanyak <strong>{{ $targetYearActiveStudentsCount }} siswa aktif</strong> sudah terdaftar di Tahun Ajaran <strong>{{ $targetAcademicYear }}</strong>.
                        Pastikan Anda tidak memproses kenaikan ganda untuk angkatan yang sama. Jika Anda ingin membatalkan kenaikan sebelumnya, silakan gunakan tombol <strong>Rollback</strong> di bagian bawah halaman ini.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('students.promotions.rollover') }}" method="POST" id="form-rollover-all">
        @csrf

        <!-- Langkah 1: Pengaturan Tahun Ajaran -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                    <span class="badge bg-primary text-white rounded-circle me-2">1</span>
                    Tentukan Tahun Ajaran Asal dan Tujuan
                </h4>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-dark">Tahun Ajaran Berjalan (Asal)</label>
                        <select name="source_academic_year" class="form-select" onchange="window.location.href='{{ route('students.promotions.index') }}?tab=rollover&source_academic_year=' + encodeURIComponent(this.value)">
                            @foreach($yearsList as $yr)
                                <option value="{{ $yr }}" {{ $sourceAcademicYear === $yr ? 'selected' : '' }}>
                                    TP {{ $yr }}{{ isset($classroomCounts[$yr]) ? ' (' . $classroomCounts[$yr] . ' Kelas Terdaftar)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="text-muted small mt-1">Seluruh siswa aktif di tahun ajaran ini akan diproses serentak.</div>
                    </div>
                    <div class="col-md-2 text-center d-none d-md-block">
                        <i class="ti ti-arrow-right fs-1 text-primary"></i>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-dark">Tahun Ajaran Baru (Tujuan Kenaikan Otomatis)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success-lt text-success fw-bold"><i class="ti ti-sparkles me-1"></i> Otomatis</span>
                            <input type="text" class="form-control fw-bold bg-light text-dark" value="TP {{ $targetAcademicYear }}" readonly>
                            <input type="hidden" name="target_academic_year" value="{{ $targetAcademicYear }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Langkah 2: Pengecualian Siswa (Hanya Siswa Tinggal Kelas / Pindah Rombel Tertentu) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                        <span class="badge bg-warning text-dark rounded-circle me-2">2</span>
                        Tandai Pengecualian Siswa (Hanya yang Tidak Naik / Pindah Rombel)
                    </h4>
                    <div class="text-secondary small mt-1">
                        Cukup tambahkan siswa yang <strong>Tinggal Kelas</strong>, <strong>Pindah Rombel Tertentu</strong>, atau <strong>Mutasi</strong>. Seluruh siswa lain di sekolah <strong>OTOMATIS NAIK KELAS / LULUS</strong>.
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" onclick="addExceptionRow()">
                        <i class="ti ti-plus me-1"></i> Tambah Pengecualian
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="overflow-x: visible;">
                    <table class="table table-vcenter table-hover mb-0" id="table-exceptions">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 280px;">Nama Siswa (Ketik Nama)</th>
                                <th style="width: 140px;">NISN</th>
                                <th style="width: 140px;">Kelas Asal</th>
                                <th style="width: 220px;">Tindakan Khusus</th>
                                <th style="width: 260px;">Rombel Tujuan Khusus</th>
                                <th style="width: 45px;"></th>
                            </tr>
                        </thead>
                        <tbody id="exception-rows">
                            <tr id="empty-exception-row">
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="ti ti-circle-check fs-2 text-success d-block mb-1"></i>
                                    Belum ada pengecualian. <strong>Seluruh siswa di sekolah dianggap NAIK KELAS / LULUS normal.</strong>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="addExceptionRow()">
                                            <i class="ti ti-plus me-1"></i> Tambah Pengecualian Sekarang
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center border-top">
                    <button type="button" class="btn btn-sm btn-outline-warning fw-bold text-dark" onclick="addExceptionRow()">
                        <i class="ti ti-user-plus me-1"></i> Tambah Siswa
                    </button>
                    <span class="text-secondary small">
                        <i class="ti ti-info-circle me-1"></i> Ketik nama siswa untuk memunculkan referensi nama. NISN dan Kelas Asal akan terisi otomatis.
                    </span>
                </div>
            </div>
        </div>

        <!-- Langkah 3: Matriks Pemetaan Rombel Sekolah Otomatis -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0 d-flex align-items-center">
                    <span class="badge bg-info text-white rounded-circle me-2">3</span>
                    Matriks Pemetaan Rombel Seluruh Sekolah
                </h4>
                <div class="text-secondary small mt-1">
                    Sistem otomatis memetakan rombel tingkat berikutnya. Jika rombel di tahun ajaran baru belum ada, sistem akan <strong>membuatkannya secara otomatis</strong>.
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th>Rombel Asal ({{ $sourceAcademicYear }})</th>
                            <th>Tingkat</th>
                            <th>Siswa Aktif</th>
                            <th>Rombel Tujuan ({{ $targetAcademicYear }})</th>
                            <th>Status Rombel Baru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rolloverMatrix as $idx => $m)
                            <tr>
                                <td class="text-muted small">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="text-dark">{{ $m['source_classroom']->name }}</div>
                                    <div class="text-muted small">Wali: {{ $m['source_classroom']->homeroomTeacher?->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $m['is_grade_12'] ? 'bg-warning-lt' : 'bg-primary-lt' }}">
                                        {{ $m['is_grade_12'] ? 'Tingkat XII (Kelulusan)' : 'Tingkat ' . ($m['source_classroom']->gradeLevel?->level ?: '-') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $m['active_students_count'] }} siswa</span>
                                </td>
                                <td>
                                    @if($m['is_grade_12'])
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-warning text-dark"><i class="ti ti-certificate me-1"></i> LULUS (Alumni)</span>
                                            <input type="hidden" name="classroom_mappings[{{ $m['source_classroom']->id }}]" value="lulus">
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            @if($m['target_classroom'])
                                                <span class="fw-bold text-dark">{{ $m['target_classroom']->name }}</span>
                                                <input type="hidden" name="classroom_mappings[{{ $m['source_classroom']->id }}]" value="{{ $m['target_classroom']->id }}">
                                            @else
                                                <span class="fw-bold text-primary">{{ $m['suggested_name'] }}</span>
                                                <input type="hidden" name="classroom_mappings[{{ $m['source_classroom']->id }}]" value="auto_create">
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($m['is_grade_12'])
                                        <span class="text-success small"><i class="ti ti-check me-1"></i> Dialihkan ke Data Alumni</span>
                                    @elseif($m['target_classroom'])
                                        <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Rombel Siap Pakai</span>
                                    @else
                                        <span class="badge bg-info-lt"><i class="ti ti-sparkles me-1"></i> Akan Dibuat Otomatis</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Tidak ada data rombel pada Tahun Ajaran {{ $sourceAcademicYear }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    <i class="ti ti-shield-check text-success me-1"></i>
                    Diproteksi transaksi database: Proses 100% aman dan dapat dibatalkan kapan saja via tombol Rollback.
                </div>
                <div>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow" onclick="return confirm('Apakah Anda yakin ingin mengeksekusi Transisi Kenaikan Kelas Tahun Ajaran untuk seluruh sekolah?');">
                        <i class="ti ti-rocket me-1"></i> Eksekusi Transisi Tahun Ajaran Sekarang
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Riwayat Transisi & Fitur Rollback / Batalkan Kenaikan -->
    @if($rolloverLogs->isNotEmpty())
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-history text-primary me-2"></i> Riwayat Transisi Tahun Ajaran & Tombol Pembatalan (Rollback)
                </h4>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Batch Kode</th>
                            <th>Transisi Tahun Ajaran</th>
                            <th>Waktu Eksekusi & Oleh</th>
                            <th>Ringkasan Siswa</th>
                            <th>Status Transisi</th>
                            <th class="text-end">Aksi Pengaman</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rolloverLogs as $log)
                            <tr>
                                <td class="font-monospace small">{{ $log->batch_code }}</td>
                                <td>
                                    <strong>{{ $log->source_academic_year }}</strong>
                                    <i class="ti ti-arrow-right mx-1 text-muted"></i>
                                    <strong>{{ $log->target_academic_year }}</strong>
                                </td>
                                <td class="small">
                                    <div>{{ $log->created_at->format('d/m/Y H:i') }}</div>
                                    <div class="text-muted">{{ $log->executor?->name ?? 'Admin' }}</div>
                                </td>
                                <td class="small">
                                    @php $sum = $log->summary ?? []; @endphp
                                    <span>Naik: <strong>{{ $sum['promoted'] ?? 0 }}</strong>, </span>
                                    <span>Lulus: <strong>{{ $sum['graduated'] ?? 0 }}</strong>, </span>
                                    <span>Tinggal: <strong>{{ $sum['retained'] ?? 0 }}</strong></span>
                                </td>
                                <td>
                                    @if($log->is_rolled_back)
                                        <span class="badge bg-danger-lt">Dibatalkan (Rolled Back)</span>
                                        <div class="text-muted small" style="font-size: 0.72rem;">
                                            {{ $log->rolled_back_at?->format('d/m/Y H:i') }}
                                        </div>
                                    @else
                                        <span class="badge bg-success-lt">Aktif Diterapkan</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(!$log->is_rolled_back)
                                        <form action="{{ route('students.promotions.rollback', $log->id) }}" method="POST" class="d-inline" onsubmit="return confirm('PERINGATAN PENGAMAN: Apakah Anda yakin ingin MEMBATALKAN kenaikan kelas Batch ini? Seluruh siswa akan dikembalikan ke posisi kelas semula.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan Kenaikan Kelas Ini">
                                                <i class="ti ti-rotate-2 me-1"></i> Batalkan (Rollback)
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">Telah di-rollback</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

<!-- ========================================================================= -->
<!-- TAB 2: PINDAH ROMBEL SISWA (CLASSROOM TRANSFER TOOL) -->
<!-- ========================================================================= -->
@elseif($tab === 'transfer')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-arrows-exchange text-primary me-2"></i> Alat Pemindahan Rombel Siswa (Cepat & Fleksibel)
            </h4>
            <div class="text-secondary small mt-1">
                Gunakan alat ini untuk memindahkan satu atau banyak siswa sekaligus dari satu kelas ke kelas lainnya kapan saja.
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Rombel Asal -->
            <form action="{{ route('students.promotions.index') }}" method="GET" class="row g-3 align-items-end mb-4">
                <input type="hidden" name="tab" value="transfer">
                <div class="col-md-5">
                    <label class="form-label fw-bold text-dark">Pilih Kelas Asal Siswa</label>
                    <select name="transfer_source_classroom_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Kelas Asal --</option>
                        @foreach($sourceClassrooms as $sc)
                            <option value="{{ $sc->id }}" {{ (string)$transferSourceClassroomId === (string)$sc->id ? 'selected' : '' }}>
                                {{ $sc->name }} ({{ $sc->academic_year }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-search me-1"></i> Tampilkan Siswa
                    </button>
                </div>
            </form>

            @if($transferSourceClassroom)
                @if($transferStudents->isEmpty())
                    <div class="alert alert-info py-4 text-center">
                        <i class="ti ti-users-minus fs-1 d-block mb-2 text-info opacity-50"></i>
                        Tidak ada siswa aktif di kelas <strong>{{ $transferSourceClassroom->name }}</strong>.
                    </div>
                @else
                    <form action="{{ route('students.transfers.process') }}" method="POST">
                        @csrf
                        <input type="hidden" name="source_classroom_id" value="{{ $transferSourceClassroom->id }}">

                        <div class="card border mb-3">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">
                                    Daftar Siswa Kelas {{ $transferSourceClassroom->name }} ({{ $transferStudents->count() }} siswa)
                                </span>
                                <div>
                                    <label class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" id="check-all-transfer" onclick="toggleSelectAllTransfer(this)">
                                        <span class="form-check-label fw-bold small">Pilih Semua Siswa</span>
                                    </label>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-vcenter table-hover table-striped mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 40px;">Pilih</th>
                                            <th style="width: 140px;">NISN / NIS</th>
                                            <th>Nama Lengkap Siswa</th>
                                            <th>L/P</th>
                                            <th>Kontak Siswa</th>
                                            <th>Wali Kelas Saat Ini</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($transferStudents as $tst)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="student_ids[]" value="{{ $tst->id }}" class="form-check-input transfer-checkbox">
                                                </td>
                                                <td class="font-monospace small">
                                                    <div>{{ $tst->nisn ?: '-' }}</div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">NIS: {{ $tst->nis ?: '-' }}</div>
                                                </td>
                                                <td>
                                                    <div class="text-dark">{{ $tst->name }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $tst->gender === 'L' ? 'bg-blue-lt' : 'bg-pink-lt' }}">
                                                        {{ $tst->gender }}
                                                    </span>
                                                </td>
                                                <td class="small">{{ $tst->phone_student ?: '-' }}</td>
                                                <td class="small text-muted">{{ $transferSourceClassroom->homeroomTeacher?->name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Panel Tujuan Pemindahan -->
                        <div class="card border border-primary border-2 shadow-sm bg-primary-lt">
                            <div class="card-body p-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold text-dark">Pindahkan ke Rombel / Kelas Tujuan:</label>
                                        <select name="target_classroom_id" class="form-select" required>
                                            <option value="">-- Pilih Kelas Tujuan --</option>
                                            @foreach($sourceClassrooms as $tc)
                                                @if($tc->id !== $transferSourceClassroom->id)
                                                    <option value="{{ $tc->id }}">
                                                        {{ $tc->name }} ({{ $tc->academic_year }}) - Wali: {{ $tc->homeroomTeacher?->name ?? 'Belum ada' }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-dark">Alasan Pemindahan (Opsional):</label>
                                        <input type="text" name="reason" class="form-control" placeholder="Contoh: Pemerataan rombel / permintaan wali murid">
                                    </div>
                                    <div class="col-md-3 mt-md-auto">
                                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm" onclick="return confirmTransferAction()">
                                            <i class="ti ti-arrows-right me-1"></i> Pindahkan Siswa Terpilih
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-school fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    Silakan pilih <strong>Kelas Asal Siswa</strong> pada panel di atas untuk memulai pemindahan.
                </div>
            @endif
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
const allStudents = {!! json_encode($allActiveStudentsJson ?? []) !!};
const candidateClassrooms = {!! json_encode($candidateClassroomsJson ?? []) !!};

let rowCounter = 0;

function getSelectedStudentIds() {
    const ids = [];
    document.querySelectorAll('.student-id-input').forEach(input => {
        if (input.value) {
            ids.push(parseInt(input.value));
        }
    });
    return ids;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function highlightMatch(text, query) {
    if (!query) return escapeHtml(text);
    const escapedText = escapeHtml(text);
    const escapedQuery = escapeHtml(query);
    const idx = escapedText.toLowerCase().indexOf(escapedQuery.toLowerCase());
    if (idx === -1) return escapedText;
    return escapedText.substring(0, idx) +
        '<mark class="bg-warning-lt p-0 text-dark fw-bold">' + escapedText.substring(idx, idx + query.length) + '</mark>' +
        escapedText.substring(idx + query.length);
}

function populateTargetClassOptions(row, student, action) {
    const targetClassSelect = row.querySelector('.student-target-class-select');
    if (!targetClassSelect) return;

    if (!student) {
        targetClassSelect.innerHTML = '<option value="">-- Ketik dan Pilih Siswa Terlebih Dahulu --</option>';
        targetClassSelect.disabled = true;
        return;
    }

    if (action === 'mutasi' || action === 'keluar') {
        targetClassSelect.innerHTML = '<option value="">(Tidak Memerlukan Rombel)</option>';
        targetClassSelect.value = '';
        targetClassSelect.disabled = true;
        return;
    }

    targetClassSelect.disabled = false;

    let targetLevel = student.level;
    let labelHelp = '';

    if (action === 'tinggal') {
        targetLevel = student.level;
        labelHelp = `Tingkat ${targetLevel} (Tinggal Kelas)`;
    } else if (action === 'pindah') {
        targetLevel = student.next_level;
        labelHelp = `Tingkat ${targetLevel} (Naik Rombel Lain)`;
    }

    // Filter candidate classrooms: matching target level & same department
    const matchingRooms = candidateClassrooms.filter(c => {
        if (c.level !== targetLevel) return false;

        // Cocokkan jurusan / program keahlian
        if (student.department_id && c.department_id) {
            return student.department_id === c.department_id;
        }

        if (student.department_code && c.department_code) {
            return student.department_code.toUpperCase() === c.department_code.toUpperCase();
        }

        if (student.classroom_name) {
            const parts = student.classroom_name.split(' ');
            if (parts.length > 1) {
                const progCode = parts[1].toUpperCase();
                return c.name.toUpperCase().includes(progCode);
            }
        }

        return true;
    });

    let html = `<option value="">-- Tetap di Rombel ${escapeHtml(student.classroom_name)} (TP Baru) --</option>`;

    if (matchingRooms.length > 0) {
        html += `<optgroup label="Pilihan Rombel Sesuai: ${labelHelp}">`;
        matchingRooms.forEach(c => {
            const isSame = (c.name.trim().toUpperCase() === student.classroom_name.trim().toUpperCase());
            const extraTag = isSame ? '(Kelas yang Sama)' : '';
            html += `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name)} ${extraTag}</option>`;
        });
        html += `</optgroup>`;
    } else {
        html += `<option value="" disabled>(Tidak ada rombel alternatif di tingkat/jurusan ini)</option>`;
    }

    targetClassSelect.innerHTML = html;
}

function addExceptionRow() {
    // Remove empty placeholder row if exists
    const emptyRow = document.getElementById('empty-exception-row');
    if (emptyRow) {
        emptyRow.remove();
    }

    rowCounter++;
    const rowId = 'exc-row-' + rowCounter;
    const tbody = document.getElementById('exception-rows');
    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.className = 'exception-row-item align-middle';

    tr.innerHTML = `
        <td>
            <div class="position-relative student-autocomplete-wrapper">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" 
                           class="form-control form-control-sm student-input-search" 
                           placeholder="Ketik nama siswa..." 
                           autocomplete="off">
                </div>
                <input type="hidden" class="student-id-input" value="">
                <div class="dropdown-menu w-100 shadow-lg student-results-dropdown p-1" 
                     style="max-height: 250px; overflow-y: auto; display: none; position: absolute; top: 100%; left: 0; z-index: 1050;">
                </div>
            </div>
        </td>
        <td>
            <div class="student-nisn-display text-muted small font-monospace">-</div>
        </td>
        <td>
            <div class="student-class-display text-muted small">-</div>
        </td>
        <td>
            <select class="form-select form-select-sm student-action-select" disabled onchange="handleActionChange(this)">
                <option value="tinggal">⏸️ Tinggal Kelas (Tidak Naik / Tidak Lulus)</option>
                <option value="pindah">🔀 Naik tapi Pindah Rombel</option>
                <option value="mutasi">📤 Mutasi Keluar Sekolah</option>
                <option value="keluar">❌ Keluar / Drop Out</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm student-target-class-select" disabled>
                <option value="">-- Ketik dan Pilih Siswa Terlebih Dahulu --</option>
            </select>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteExceptionRow('${rowId}')" title="Hapus Baris">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    // Setup autocomplete for this new row
    setupAutocompleteForRow(tr);

    // Focus on the search input
    const searchInput = tr.querySelector('.student-input-search');
    if (searchInput) {
        searchInput.focus();
    }
}

function setupAutocompleteForRow(tr) {
    const input = tr.querySelector('.student-input-search');
    const hiddenId = tr.querySelector('.student-id-input');
    const dropdown = tr.querySelector('.student-results-dropdown');
    const nisnDisplay = tr.querySelector('.student-nisn-display');
    const classDisplay = tr.querySelector('.student-class-display');
    const actionSelect = tr.querySelector('.student-action-select');
    const targetClassSelect = tr.querySelector('.student-target-class-select');

    function renderMatches(query) {
        const selectedIds = getSelectedStudentIds();
        const currentId = hiddenId.value ? parseInt(hiddenId.value) : null;
        const q = (query || '').trim().toLowerCase();

        const matches = allStudents.filter(s => {
            // Exclude already selected students in other rows
            if (selectedIds.includes(s.id) && s.id !== currentId) {
                return false;
            }
            if (!q) {
                return true;
            }
            return s.name.toLowerCase().includes(q) || 
                   (s.nisn && s.nisn.toLowerCase().includes(q)) || 
                   (s.classroom_name && s.classroom_name.toLowerCase().includes(q));
        }).slice(0, 12);

        if (matches.length === 0) {
            dropdown.innerHTML = `
                <div class="p-2 text-center text-muted small">
                    <i class="ti ti-user-x me-1"></i> Tidak ditemukan siswa yang cocok
                </div>
            `;
            dropdown.style.display = 'block';
            return;
        }

        let html = '';
        matches.forEach(s => {
            html += `
                <a href="javascript:void(0)" 
                   class="dropdown-item py-2 px-2 border-bottom text-wrap" 
                   data-id="${s.id}" 
                   data-name="${escapeHtml(s.name)}" 
                   data-nisn="${escapeHtml(s.nisn)}" 
                   data-class="${escapeHtml(s.classroom_name)}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="fw-bold text-dark text-truncate me-2">${highlightMatch(s.name, q)}</div>
                        <span class="badge bg-blue-lt text-nowrap">${escapeHtml(s.classroom_name)}</span>
                    </div>
                    <div class="small text-muted font-monospace">NISN: ${highlightMatch(s.nisn, q)}</div>
                </a>
            `;
        });

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('mousedown', function(e) {
                e.preventDefault(); // prevent blur before click
                const studentId = parseInt(this.dataset.id);
                const studentName = this.dataset.name;
                const studentNisn = this.dataset.nisn;
                const studentClass = this.dataset.class;
                const studentObj = allStudents.find(s => s.id === studentId);

                input.value = studentName;
                hiddenId.value = studentId;

                // Update table cells
                nisnDisplay.innerHTML = `<span class="badge bg-light text-dark font-monospace">${studentNisn}</span>`;
                classDisplay.innerHTML = `<span class="badge bg-azure-lt">${studentClass}</span>`;

                // Set field names and enable
                actionSelect.name = `exceptions[${studentId}][action]`;
                actionSelect.disabled = false;

                targetClassSelect.name = `exceptions[${studentId}][target_classroom_id]`;

                // Close dropdown
                dropdown.style.display = 'none';

                // Populate relevant target classrooms based on student level/department & current action
                populateTargetClassOptions(tr, studentObj, actionSelect.value);
            });
        });
    }

    input.addEventListener('input', function() {
        renderMatches(this.value);
    });

    input.addEventListener('focus', function() {
        renderMatches(this.value);
    });

    input.addEventListener('blur', function() {
        setTimeout(() => {
            dropdown.style.display = 'none';
        }, 200);
    });
}

function deleteExceptionRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
    }

    const tbody = document.getElementById('exception-rows');
    if (tbody.querySelectorAll('.exception-row-item').length === 0) {
        tbody.innerHTML = `
            <tr id="empty-exception-row">
                <td colspan="6" class="text-center py-4 text-muted">
                    <i class="ti ti-circle-check fs-2 text-success d-block mb-1"></i>
                    Belum ada pengecualian. <strong>Seluruh siswa di sekolah dianggap NAIK KELAS / LULUS normal.</strong>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="addExceptionRow()">
                            <i class="ti ti-plus me-1"></i> Tambah Pengecualian Sekarang
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
}

function handleActionChange(selectElem) {
    const row = selectElem.closest('tr');
    if (!row) return;
    const hiddenId = row.querySelector('.student-id-input');
    const studentId = hiddenId && hiddenId.value ? parseInt(hiddenId.value) : null;
    const student = studentId ? allStudents.find(s => s.id === studentId) : null;

    populateTargetClassOptions(row, student, selectElem.value);
}

function toggleSelectAllTransfer(masterCheckbox) {
    document.querySelectorAll('.transfer-checkbox').forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
}

function confirmTransferAction() {
    const checked = document.querySelectorAll('.transfer-checkbox:checked').length;
    if (checked === 0) {
        alert('Pilih setidaknya 1 siswa yang ingin dipindahkan.');
        return false;
    }
    return confirm(`Apakah Anda yakin ingin memindahkan ${checked} siswa terpilih ke kelas tujuan?`);
}

document.addEventListener('DOMContentLoaded', function() {
    const rolloverForm = document.getElementById('form-rollover-all');
    if (rolloverForm) {
        rolloverForm.addEventListener('submit', function() {
            // Clean up any row without student selected before form submission
            document.querySelectorAll('#exception-rows .exception-row-item').forEach(row => {
                const hiddenInput = row.querySelector('.student-id-input');
                if (!hiddenInput || !hiddenInput.value) {
                    row.remove();
                }
            });
        });
    }
});
</script>
@endpush
