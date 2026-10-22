@extends('layouts.app')

@section('title', 'Presensi GTK Susulan (Kendala Listrik/Server) — SIAP')

@section('content')
<div class="page-header d-print-none mb-3">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-arrows mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Presensi Sekolah</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('attendance.report.teachers') }}">Presensi Guru</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Presensi Susulan GTK</li>
                    </ol>
                </nav>
                <h2 class="page-title d-flex align-items-center">
                    <i class="ti ti-bolt-off text-warning me-2 fs-1"></i>
                    Presensi Susulan GTK (Kendala Listrik / Server)
                </h2>
                <div class="text-secondary small mt-1">
                    Formulir darurat untuk mencatat kehadiran guru &amp; staf yang hadir saat terjadi pemadaman listrik atau server lokal sekolah offline.
                </div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Kembali ke Presensi Harian
                    </a>
                    <a href="{{ route('attendance.report.teachers') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-file-text me-1"></i> Rekap Presensi Guru
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">

        <!-- Banner Info Penting -->
        <div class="alert alert-info shadow-sm border-0 mb-3" role="alert">
            <div class="d-flex align-items-start">
                <div class="me-3 mt-1">
                    <i class="ti ti-info-circle fs-1 text-info"></i>
                </div>
                <div>
                    <h4 class="alert-title mb-1 fw-bold">Hak Akses Khusus Admin &amp; Waka</h4>
                    <div class="text-secondary small">
                        Fitur ini khusus digunakan ketika mesin absensi atau server lokal sekolah tidak dapat diakses (misal saat pemadaman listrik). 
                        Guru yang dicatat kehadirannya melalui menu ini akan langsung terhitung <strong>Hadir</strong> pada rekap presensi guru dan jurnal piket pada tanggal yang dipilih.
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Kolom Kiri: Form Input Presensi Susulan -->
            <div class="col-lg-8">
                <!-- Card 1: Pemilihan Tanggal Presensi Susulan -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light py-2">
                        <h4 class="card-title fw-bold text-dark mb-0">
                            <i class="ti ti-calendar me-1 text-primary"></i> 1. Tentukan Tanggal Presensi
                        </h4>
                    </div>
                    <div class="card-body py-3">
                        <form method="GET" action="{{ route('attendance.manual-gtk.index') }}" class="row g-2 align-items-center">
                            <div class="col-auto">
                                <label class="col-form-label small fw-bold">Pilih Tanggal:</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-icon">
                                    <span class="input-icon-addon"><i class="ti ti-calendar"></i></span>
                                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()">
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-refresh me-1"></i> Muat Data Tanggal Ini
                                </button>
                            </div>
                            <div class="col-auto ms-md-auto">
                                <span class="badge bg-purple-lt fs-4 py-1 px-2">
                                    <i class="ti ti-calendar-event me-1"></i> Hari {{ $targetDay }}, {{ $targetCarbon->translatedFormat('d F Y') }}
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card 2: Form Simpan Presensi Susulan -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                        <h4 class="card-title fw-bold text-dark mb-0">
                            <i class="ti ti-users-plus me-1 text-teal"></i> 2. Pilih Guru &amp; Atur Jam Masuk / Pulang
                        </h4>
                        <span class="badge bg-blue-lt">Hari {{ $targetDay }}</span>
                    </div>

                    <form method="POST" action="{{ route('attendance.manual-gtk.store') }}" id="manualGtkForm">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">

                        <div class="card-body">
                            <!-- Pengaturan Jam dan Alasan -->
                            <div class="row g-3 p-3 bg-light rounded-3 mb-3 border">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Jam Masuk <span class="text-danger">*</span></label>
                                    <input type="time" name="check_in_time" class="form-control form-control-sm" value="06:45" required>
                                    <div class="form-text small">Default: 06:45 WIB</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Jam Pulang (Opsional)</label>
                                    <input type="time" name="check_out_time" class="form-control form-control-sm" value="{{ $targetDay === 'Jumat' ? '12:30' : '15:00' }}">
                                    <div class="form-text small">Kosongkan jika hanya scan masuk</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Alasan / Catatan Presensi Susulan <span class="text-danger">*</span></label>
                                    <input type="text" name="reason" id="reasonInput" class="form-control form-control-sm" placeholder="Misal: Pemadaman Listrik Sekolah / Server Offline" value="Pemadaman Listrik Sekolah" required list="reasonTemplates">
                                    <datalist id="reasonTemplates">
                                        <option value="Pemadaman Listrik Sekolah">
                                        <option value="Server Lokal Offline / Gangguan Jaringan">
                                        <option value="Force Majeure / Cuaca Ekstrem">
                                        <option value="Tugas Dinas Luar Sekolah">
                                    </datalist>
                                    <div class="form-text small">Catatan ini akan tersimpan pada audit trail</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" name="overwrite_existing" value="1">
                                        <span class="form-check-label small text-secondary">
                                            Timpa log jika guru yang dipilih sudah memiliki log manual pada tanggal ini
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Tombol Shortcut Pemilihan Cepat & Search -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" onclick="selectScheduledTeachers()">
                                        <i class="ti ti-calendar-check me-1"></i> Pilih Guru Berjadwal {{ $targetDay }} ({{ count($scheduledTeacherIds) + count($piketTeacherIds) }})
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="selectAllTeachers()">
                                        <i class="ti ti-check-all me-1"></i> Pilih Semua GTK
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deselectAllTeachers()">
                                        <i class="ti ti-x me-1"></i> Batal Semua
                                    </button>
                                </div>
                                <div class="input-icon" style="min-width: 200px;">
                                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                    <input type="text" id="teacherSearchBox" class="form-control form-control-sm" placeholder="Cari nama guru..." onkeyup="filterTeacherList()">
                                </div>
                            </div>

                            <!-- Indikator Jumlah Terpilih -->
                            <div class="alert alert-secondary py-1 px-3 mb-2 d-flex justify-content-between align-items-center">
                                <span class="small">
                                    Terpilih: <strong id="selectedCountBadge" class="text-primary">0</strong> dari {{ $teachers->count() }} GTK
                                </span>
                                <span class="text-muted small">
                                    Centang kotak di samping nama guru yang hadir
                                </span>
                            </div>

                            <!-- Daftar Guru & Staf dengan Checkbox -->
                            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-vcenter table-hover card-table table-sm">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">
                                                <input type="checkbox" id="checkAllHead" class="form-check-input" onchange="toggleAllCheckboxes(this)">
                                            </th>
                                            <th>Nama GTK</th>
                                            <th>Peran / Jadwal Hari {{ $targetDay }}</th>
                                            <th>Status Presensi Saat Ini</th>
                                        </tr>
                                    </thead>
                                    <tbody id="teacherTableBody">
                                        @foreach($teachers as $teacher)
                                            @php
                                                $hasSched = in_array($teacher->id, $scheduledTeacherIds);
                                                $isPiket = in_array($teacher->id, $piketTeacherIds);
                                                $logs = $existingLogs->get($teacher->id, collect());
                                                $permit = $gtkPermits->get($teacher->id);
                                                $hasLogs = $logs->isNotEmpty();
                                                $firstLog = $logs->first();
                                                $lastLog = $logs->count() > 1 ? $logs->last() : null;
                                            @endphp
                                            <tr class="teacher-row" 
                                                data-id="{{ $teacher->id }}" 
                                                data-name="{{ strtolower($teacher->name) }}"
                                                data-scheduled="{{ ($hasSched || $isPiket) ? '1' : '0' }}">
                                                <td class="text-center">
                                                    <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" class="form-check-input teacher-checkbox" onchange="updateSelectedCount()">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        @if($teacher->avatar)
                                                            <img src="{{ asset('storage/' . $teacher->avatar) }}" alt="{{ $teacher->name }}" class="avatar avatar-xs rounded-circle me-2" onerror="this.onerror=null; this.src='/favicon.ico';">
                                                        @else
                                                            <span class="avatar avatar-xs rounded-circle bg-blue-lt text-primary fw-bold me-2">
                                                                {{ substr($teacher->name, 0, 1) }}
                                                            </span>
                                                        @endif
                                                        <div>
                                                            <div class="fw-bold text-dark lh-sm">{{ $teacher->formatted_short_name ?? $teacher->name }}</div>
                                                            <div class="text-muted small" style="font-size: 0.72rem;">{{ $teacher->formatted_id ?? ($teacher->nip ?: 'NIP -') }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @if($hasSched)
                                                            <span class="badge bg-teal-lt" style="font-size: 0.68rem;">
                                                                <i class="ti ti-book me-0.5"></i> Jadwal KBM
                                                            </span>
                                                        @endif
                                                        @if($isPiket)
                                                            <span class="badge bg-purple-lt" style="font-size: 0.68rem;">
                                                                <i class="ti ti-shield me-0.5"></i> Guru Piket
                                                            </span>
                                                        @endif
                                                        @if(!$hasSched && !$isPiket)
                                                            <span class="badge bg-light text-muted" style="font-size: 0.68rem;">
                                                                Tidak Ada Jam
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($hasLogs)
                                                        <span class="badge bg-success-lt fw-bold" style="font-size: 0.7rem;">
                                                            <i class="ti ti-check me-0.5"></i> Hadir ({{ $firstLog->scanned_at?->format('H:i') }}{{ $lastLog ? ' - ' . $lastLog->scanned_at?->format('H:i') : '' }})
                                                        </span>
                                                        @if($firstLog->device_sn === 'MANUAL_SUSULAN')
                                                            <span class="badge bg-secondary-lt" style="font-size: 0.65rem;" title="Dicatat manual susulan">Susulan</span>
                                                        @endif
                                                    @elseif($permit)
                                                        <span class="badge bg-info-lt" style="font-size: 0.7rem;">
                                                            <i class="ti ti-file-text me-0.5"></i> Izin: {{ ucfirst($permit->permission_type) }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-danger-lt" style="font-size: 0.7rem;">
                                                            Belum Presensi
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer bg-white text-end py-3 border-top">
                            <button type="submit" class="btn btn-primary shadow-sm" onclick="return confirm('Simpan presensi susulan untuk guru yang dipilih?')">
                                <i class="ti ti-device-floppy me-1"></i> Simpan Presensi Susulan Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Kolom Kanan: Panduan & Riwayat Presensi Susulan -->
            <div class="col-lg-4">
                <!-- Panduan Prosedur -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-2">
                        <h4 class="card-title fw-bold text-dark mb-0">
                            <i class="ti ti-help-circle me-1 text-info"></i> Petunjuk Prosedur
                        </h4>
                    </div>
                    <div class="card-body p-3">
                        <ol class="ps-3 mb-0 text-secondary small lh-lg">
                            <li>Pilih <strong>tanggal</strong> saat pemadaman listrik/kendala server terjadi.</li>
                            <li>Tentukan <strong>jam masuk</strong> dan <strong>jam pulang</strong> (sesuai jam operasional sekolah).</li>
                            <li>Tuliskan <strong>alasan</strong> pencatatan susulan untuk keperluan arsip &amp; transparansi audit.</li>
                            <li>Klik tombol <strong>"Pilih Guru Berjadwal"</strong> untuk mencentang otomatis guru yang memiliki jam mengajar atau tugas piket hari itu, atau centang manual.</li>
                            <li>Klik <strong>Simpan Presensi Susulan</strong>. Data log akan otomatis masuk ke laporan presensi dan menyinkronkan jurnal piket pada tanggal tersebut.</li>
                        </ol>
                    </div>
                </div>

                <!-- Riwayat Entri Susulan Terbaru -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
                        <h4 class="card-title fw-bold text-dark mb-0">
                            <i class="ti ti-history me-1 text-secondary"></i> Log Susulan Terbaru
                        </h4>
                        <span class="badge bg-secondary-lt">{{ $recentManualLogs->count() }} Data</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            @forelse($recentManualLogs as $log)
                                @php
                                    $payload = json_decode($log->raw_payload ?? '{}', true);
                                    $reasonText = $payload['reason'] ?? 'Manual Susulan';
                                    $inputtedBy = $payload['inputted_by_name'] ?? 'Admin/Waka';
                                @endphp
                                <div class="list-group-item p-2.5">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="fw-bold text-dark small text-truncate" style="max-width: 180px;">
                                            {{ $log->user?->formatted_short_name ?? $log->name }}
                                        </div>
                                        <span class="badge {{ $log->status === 'check_in' ? 'bg-success-lt' : 'bg-primary-lt' }}" style="font-size: 0.65rem;">
                                            {{ $log->status === 'check_in' ? 'Masuk' : 'Pulang' }} {{ $log->scanned_at?->format('H:i') }}
                                        </span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        <i class="ti ti-calendar me-0.5"></i> {{ $log->scanned_at?->translatedFormat('d M Y') }} &bull;
                                        <span class="text-secondary">{{ $reasonText }}</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mt-1 pt-1 border-top" style="font-size: 0.68rem;">
                                        <span class="text-muted">Oleh: {{ $inputtedBy }}</span>
                                        <form action="{{ route('attendance.manual-gtk.destroy', $log) }}" method="POST" onsubmit="return confirm('Hapus log presensi susulan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger p-0 text-decoration-none" style="font-size: 0.68rem;">
                                                <i class="ti ti-trash me-0.5"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    <i class="ti ti-inbox fs-2 d-block mb-1 text-secondary opacity-50"></i>
                                    Belum ada log presensi susulan yang dicatat.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Pilih guru yang memiliki jadwal mengajar / piket pada hari target
    function selectScheduledTeachers() {
        const rows = document.querySelectorAll('.teacher-row');
        rows.forEach(row => {
            const isScheduled = row.getAttribute('data-scheduled') === '1';
            const checkbox = row.querySelector('.teacher-checkbox');
            if (checkbox) {
                checkbox.checked = isScheduled;
            }
        });
        updateSelectedCount();
    }

    // Pilih semua GTK
    function selectAllTeachers() {
        const checkboxes = document.querySelectorAll('.teacher-checkbox');
        checkboxes.forEach(cb => {
            // Hanya centang baris yang terlihat (tidak terfilter)
            const row = cb.closest('tr');
            if (row && row.style.display !== 'none') {
                cb.checked = true;
            }
        });
        updateSelectedCount();
    }

    // Batalkan semua centang
    function deselectAllTeachers() {
        const checkboxes = document.querySelectorAll('.teacher-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const headCb = document.getElementById('checkAllHead');
        if (headCb) headCb.checked = false;
        updateSelectedCount();
    }

    // Toggle dari checkbox header
    function toggleAllCheckboxes(masterCb) {
        const checkboxes = document.querySelectorAll('.teacher-checkbox');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row && row.style.display !== 'none') {
                cb.checked = masterCb.checked;
            }
        });
        updateSelectedCount();
    }

    // Hitung jumlah checkbox yang tercentang
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.teacher-checkbox:checked').length;
        const badge = document.getElementById('selectedCountBadge');
        if (badge) {
            badge.textContent = checked;
        }
    }

    // Filter daftar nama guru secara realtime
    function filterTeacherList() {
        const filter = document.getElementById('teacherSearchBox').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.teacher-row');

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            if (name.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedCount();
    });
</script>
@endsection
