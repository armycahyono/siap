@extends('layouts.app')

@section('title', 'Data Siswa — Data Pokok')

@section('content')
<!-- Header Halaman -->
<div class="page-header d-print-none mb-4">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle text-uppercase tracking-wider text-muted">
                Data Pokok Sekolah
            </div>
            <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                <i class="ti ti-school text-primary me-2 fs-1"></i>
                Data Induk Siswa & Alumni
                <span class="badge bg-primary-lt text-primary ms-2 fs-5">TP. {{ $academicYear }}</span>
            </h2>
            <div class="text-secondary mt-1">
                Basis data induk kesiswaan (PPDB & Dapodik): biodata lengkap siswa aktif, riwayat alumni lulusan, mutasi, dan kontak orang tua.
            </div>
        </div>
        <div class="col-auto ms-auto d-flex flex-wrap gap-2">
            <a href="{{ route('students.promotions.index') }}" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm">
                <i class="ti ti-arrow-up-right-circle me-1"></i> Kenaikan & Kelulusan Kelas
            </a>
            @if($tab !== 'alumni' && $tab !== 'mutasi')
                <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-mutasi-siswa">
                    <i class="ti ti-user-x me-1"></i> Catat Mutasi / Keluar
                </button>
            @endif
            <a href="{{ route('students.template') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-download me-1"></i> Format Excel
            </a>
            <a href="{{ route('students.export', ['classroom_id' => $classroomId, 'gender' => $gender, 'search' => $search, 'tab' => $tab, 'graduation_year' => $graduationYear, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-success">
                <i class="ti ti-file-spreadsheet me-1"></i> Ekspor CSV
            </a>
            <button type="button" class="btn btn-sm btn-indigo text-white shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-import-dapodik" style="background-color: #6366f1; border-color: #6366f1;">
                <i class="ti ti-cloud-upload me-1"></i> Upload Dapodik
            </button>
            <button type="button" class="btn btn-sm btn-info text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-import-siswa">
                <i class="ti ti-file-import me-1"></i> Import Siswa
            </button>
            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-create-siswa">
                <i class="ti ti-plus me-1"></i> Tambah Siswa
            </button>
        </div>
    </div>
</div>

<!-- Stat Cards Ringkasan Siswa -->
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-primary border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">TOTAL RECORD (TP. {{ $academicYear }})</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $totalStudents }} <span class="fs-4 text-muted font-weight-normal">data</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-blue border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">LAKI-LAKI AKTIF</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $maleCount }} <span class="fs-4 text-muted font-weight-normal">siswa</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-pink border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">PEREMPUAN AKTIF</div>
                <div class="h2 fw-bold text-dark mb-0">{{ $femaleCount }} <span class="fs-4 text-muted font-weight-normal">siswi</span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-sm shadow-sm border-0 border-start border-success border-3">
            <div class="card-body py-2">
                <div class="text-secondary small fw-bold">{{ $tab === 'alumni' ? 'ALUMNI (TP. '.$academicYear.')' : ($tab === 'mutasi' ? 'MUTASI (TP. '.$academicYear.')' : 'SISWA AKTIF (TP. '.$academicYear.')') }}</div>
                <div class="h2 fw-bold text-dark mb-0">
                    {{ $tab === 'alumni' ? $alumniCount : ($tab === 'mutasi' ? $mutatedCount : $activeCount) }}
                    <span class="fs-4 text-muted font-weight-normal">orang</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigasi Kategori Siswa -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link py-2 {{ $tab === 'aktif' ? 'active fw-bold' : 'text-muted' }}" href="{{ route('students.index', ['tab' => 'aktif', 'academic_year' => $academicYear]) }}">
                    <i class="ti ti-school me-2 fs-3"></i> Siswa Aktif Terdaftar
                    <span class="badge {{ $tab === 'aktif' ? 'bg-white text-primary' : 'bg-primary-lt' }} ms-2">{{ $activeCount }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 {{ $tab === 'alumni' ? 'active fw-bold' : 'text-muted' }}" href="{{ route('students.index', ['tab' => 'alumni', 'academic_year' => $academicYear]) }}">
                    <i class="ti ti-certificate me-2 fs-3"></i> Data Alumni (Siswa Lulus)
                    <span class="badge {{ $tab === 'alumni' ? 'bg-white text-warning' : 'bg-warning-lt' }} ms-2">{{ $alumniCount }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2 {{ $tab === 'mutasi' ? 'active fw-bold' : 'text-muted' }}" href="{{ route('students.index', ['tab' => 'mutasi', 'academic_year' => $academicYear]) }}">
                    <i class="ti ti-arrows-left-right me-2 fs-3"></i> Mutasi / Keluar
                    <span class="badge {{ $tab === 'mutasi' ? 'bg-white text-secondary' : 'bg-secondary-lt' }} ms-2">{{ $mutatedCount }}</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Card Filter & Pencarian -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form action="{{ route('students.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">

            @if($tab === 'aktif')
                <div class="col-md-3">
                    <select name="classroom_id" class="form-select">
                        <option value="">-- Semua Kelas Aktif --</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ (string)$classroomId === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ $c->academic_year }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif($tab === 'alumni')
                <div class="col-md-3">
                    <select name="graduation_year" class="form-select">
                        <option value="">-- Semua Tahun Kelulusan --</option>
                        @foreach($availableGraduationYears as $yr)
                            <option value="{{ $yr }}" {{ (string)$graduationYear === (string)$yr ? 'selected' : '' }}>
                                Lulus Tahun {{ $yr }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif($tab === 'mutasi')
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Semua (Mutasi & Keluar) --</option>
                        <option value="mutasi" {{ $status === 'mutasi' ? 'selected' : '' }}>Mutasi Keluar</option>
                        <option value="keluar" {{ $status === 'keluar' ? 'selected' : '' }}>Keluar / DO</option>
                    </select>
                </div>
            @endif

            <div class="col-md-7">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama siswa, NISN, NIS, wali kelas, orang tua..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Terapkan
                </button>
                @if($classroomId || $status || $graduationYear || $search)
                    <a href="{{ route('students.index', ['tab' => $tab, 'academic_year' => $academicYear]) }}" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="ti ti-rotate-2"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Card Tabel Siswa -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-list-details text-primary me-2"></i>
                @if($tab === 'alumni')
                    Daftar Alumni SMK PGRI Kras
                @elseif($tab === 'mutasi')
                    Daftar Siswa Mutasi / Keluar
                @else
                    Daftar Induk Siswa Aktif
                @endif
            </h4>
            <div class="text-secondary small mt-1">
                <i class="ti ti-info-circle me-1 text-info"></i>
                <em>Klik pada baris siswa mana saja untuk membuka buku induk / biodata lengkap Dapodik siswa.</em>
            </div>
        </div>
        <div class="card-actions">
            <span class="badge bg-primary-lt fs-4">{{ $students->total() }} Data Ditemukan</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter table-hover table-striped mb-0 student-table">
            <thead class="table-light">
                <tr>
                    <th style="width: 45px;" class="text-center">No</th>
                    @if($tab === 'mutasi')
                        <th style="width: 24%;">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none d-inline-flex align-items-center">
                                Nama Lengkap Siswa
                                @if($sort === 'name')
                                    <i class="ti ti-arrow-narrow-{{ $direction === 'asc' ? 'up' : 'down' }} text-primary ms-1"></i>
                                @else
                                    <i class="ti ti-arrows-sort text-muted ms-1 opacity-50"></i>
                                @endif
                            </a>
                        </th>
                        <th style="width: 14%;">Kelas Terakhir</th>
                        <th style="width: 20%;">Jenis & Kategori Alasan</th>
                        <th style="width: 18%;">Tgl & Sekolah Tujuan</th>
                        <th style="width: 12%;">Berkas Bukti</th>
                    @else
                        <th style="width: 25%;">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none d-inline-flex align-items-center">
                                Nama Lengkap Siswa
                                @if($sort === 'name')
                                    <i class="ti ti-arrow-narrow-{{ $direction === 'asc' ? 'up' : 'down' }} text-primary ms-1"></i>
                                @else
                                    <i class="ti ti-arrows-sort text-muted ms-1 opacity-50"></i>
                                @endif
                            </a>
                        </th>
                        <th style="width: 10%;">Agama</th>
                        <th style="width: 20%;">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'classroom', 'direction' => ($sort === 'classroom' && $direction === 'asc') ? 'desc' : 'asc']) }}" class="text-dark text-decoration-none d-inline-flex align-items-center">
                                {{ $tab === 'alumni' ? 'Kelas & Th. Lulus' : 'Kelas & Wali Kelas' }}
                                @if($sort === 'classroom')
                                    <i class="ti ti-arrow-narrow-{{ $direction === 'asc' ? 'up' : 'down' }} text-primary ms-1"></i>
                                @else
                                    <i class="ti ti-arrows-sort text-muted ms-1 opacity-50"></i>
                                @endif
                            </a>
                        </th>
                        <th style="width: 16%;">Kontak Siswa</th>
                        <th style="width: 18%;">Nama Ortu & Kontak</th>
                    @endif
                    <th class="text-end" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $index => $s)
                    @php
                        $enrolment = $s->studentClassrooms->first();
                        $displayClassName = $enrolment?->classroom?->name 
                            ?? $enrolment?->classroom_name 
                            ?? ($s->classroom?->name ?? ($s->last_classroom_name ?? '-'));
                        $displayHomeroom = $enrolment?->classroom?->homeroomTeacher?->name 
                            ?? ($s->classroom?->homeroomTeacher?->name ?? '-');

                        if ($tab === 'aktif') {
                            $contextStatusLabel = 'Aktif';
                            $contextStatusBadge = 'bg-success-lt text-success';
                        } elseif ($tab === 'alumni') {
                            $contextStatusLabel = 'Alumni / Lulus';
                            $contextStatusBadge = 'bg-purple-lt text-purple';
                        } elseif ($tab === 'mutasi') {
                            $contextStatusLabel = $s->status_label;
                            $contextStatusBadge = $s->status_badge;
                        } else {
                            $contextStatusLabel = $s->status_label;
                            $contextStatusBadge = $s->status_badge;
                        }
                    @endphp
                    <tr class="student-row" style="cursor: pointer;"
                        data-student="{{ json_encode($s) }}"
                        data-classroom="{{ $displayClassName }}"
                        data-homeroom="{{ $displayHomeroom }}"
                        data-status-label="{{ $contextStatusLabel }}"
                        data-status-badge="{{ $contextStatusBadge }}"
                        title="Klik baris untuk melihat biodata lengkap">
                        <td class="text-center text-muted small">{{ $students->firstItem() + $index }}</td>
                        @if($tab === 'mutasi')
                            <td>
                                <div class="text-dark fw-bold">{{ $s->name }}</div>
                                <div class="text-muted font-monospace d-flex align-items-center flex-wrap gap-1 mt-1" style="font-size: 0.78rem;">
                                    <span class="badge {{ $s->gender === 'L' ? 'bg-blue-lt text-primary' : 'bg-pink-lt text-pink' }} px-1 py-0" style="font-size: 0.68rem; line-height: 1.3;" title="{{ $s->gender_label }}">
                                        {{ $s->gender === 'L' ? 'L' : 'P' }}
                                    </span>
                                    @if($s->nisn)
                                        <span>NISN: {{ $s->nisn }}</span>
                                    @endif
                                    @if($s->nis)
                                        <span class="text-secondary">(NIS: {{ $s->nis }})</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-lt">{{ $s->last_classroom_name ?: ($s->classroom?->name ?? '-') }}</span>
                            </td>
                            <td>
                                <div><span class="badge {{ $s->status_badge }}">{{ $s->status_label }}</span></div>
                                @if($s->latestMutation)
                                    <div class="small text-dark mt-1 fw-bold">{{ $s->latestMutation->reason_category_label }}</div>
                                    @if($s->latestMutation->reason_description)
                                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="{{ $s->latestMutation->reason_description }}">
                                            {{ $s->latestMutation->reason_description }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($s->latestMutation)
                                    <div class="small text-dark">
                                        <i class="ti ti-calendar me-1 text-muted"></i>{{ $s->latestMutation->mutation_date ? $s->latestMutation->mutation_date->format('d/m/Y') : '-' }}
                                    </div>
                                    @if($s->latestMutation->target_school)
                                        <div class="small text-primary mt-1" title="Sekolah Tujuan: {{ $s->latestMutation->target_school }}">
                                            <i class="ti ti-school me-1"></i>{{ $s->latestMutation->target_school }}
                                        </div>
                                    @endif
                                    @if($s->latestMutation->letter_number)
                                        <div class="small text-muted" style="font-size: 0.75rem;">
                                            No: {{ $s->latestMutation->letter_number }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                @if($s->latestMutation && $s->latestMutation->attachment_path)
                                    <a href="{{ route('students.mutations.download', $s->latestMutation->id) }}" class="btn btn-xs btn-outline-danger d-inline-flex align-items-center gap-1 shadow-sm" onclick="event.stopPropagation()" title="Unduh Bukti: {{ $s->latestMutation->attachment_name }}">
                                        <i class="ti ti-paperclip"></i>
                                        <span class="text-truncate" style="max-width: 90px;">{{ $s->latestMutation->attachment_name ?: 'Bukti' }}</span>
                                    </a>
                                @else
                                    <span class="text-muted small"><em>-</em></span>
                                @endif
                            </td>
                            <td class="text-end" onclick="event.stopPropagation()">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-detail-siswa" 
                                        data-student="{{ json_encode($s) }}"
                                        data-classroom="{{ $displayClassName }}"
                                        data-homeroom="{{ $displayHomeroom }}"
                                        data-status-label="{{ $contextStatusLabel }}"
                                        data-status-badge="{{ $contextStatusBadge }}"
                                        title="Lihat Biodata Lengkap">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                    @if($s->latestMutation)
                                        <form action="{{ route('students.mutations.destroy', $s->latestMutation->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin MEMBATALKAN mutasi siswa {{ $s->name }} dan mengembalikannya ke status Aktif?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Batalkan Mutasi (Kembalikan ke Aktif)">
                                                <i class="ti ti-rotate-2"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('students.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data siswa {{ $s->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @else
                            <td>
                                <div class="text-dark d-flex align-items-center gap-1">
                                    <span>{{ $s->name }}</span>
                                    @php
                                        $currentEnrolment = $s->studentClassrooms->first();
                                    @endphp
                                    @if($currentEnrolment && $currentEnrolment->is_repeating)
                                        <span class="badge bg-danger-lt px-1 py-0" style="font-size: 0.68rem;" title="Siswa Tinggal Kelas / Mengulang">Mengulang</span>
                                    @endif
                                </div>
                                <div class="text-muted font-monospace d-flex align-items-center flex-wrap gap-1 mt-1" style="font-size: 0.78rem;">
                                    <span class="badge {{ $s->gender === 'L' ? 'bg-blue-lt text-primary' : 'bg-pink-lt text-pink' }} px-1 py-0" style="font-size: 0.68rem; line-height: 1.3;" title="{{ $s->gender_label }}">
                                        {{ $s->gender === 'L' ? 'L' : 'P' }}
                                    </span>
                                    @if($s->entry_year)
                                        <span class="badge bg-teal-lt px-1 py-0" style="font-size: 0.68rem;" title="Tahun Angkatan Siswa">
                                            Akt. {{ $s->entry_year }}
                                        </span>
                                    @endif
                                    @if($s->nisn)
                                        <span>NISN: {{ $s->nisn }}</span>
                                    @endif
                                    @if($s->nis)
                                        <span class="text-secondary">(NIS: {{ $s->nis }})</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small">{{ $s->religion ?: '-' }}</span>
                            </td>
                            <td>
                                @php
                                    $enrolment = $s->studentClassrooms->first();
                                    $displayClassName = $enrolment?->classroom?->name 
                                        ?? $enrolment?->classroom_name 
                                        ?? ($s->classroom?->name ?? ($s->last_classroom_name ?? 'Belum ada kelas'));
                                    $displayHomeroom = $enrolment?->classroom?->homeroomTeacher?->name 
                                        ?? ($s->classroom?->homeroomTeacher?->name ?? '-');
                                @endphp
                                <div class="text-dark">
                                    {{ $displayClassName }}
                                </div>
                                @if($tab === 'alumni')
                                    <div class="text-muted small" style="font-size: 0.78rem;">
                                        <i class="ti ti-calendar me-1 text-warning"></i> Lulus {{ $s->graduation_year ?: '-' }}
                                    </div>
                                @else
                                    <div class="text-muted small" style="font-size: 0.78rem;" title="Wali Kelas: {{ $displayHomeroom }}">
                                        <i class="ti ti-user-star me-1 text-success"></i> {{ $displayHomeroom }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($s->phone_student)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s->phone_student) }}" target="_blank" class="small text-decoration-none text-primary" onclick="event.stopPropagation()" title="Chat WhatsApp Siswa">
                                        <i class="ti ti-brand-whatsapp text-success me-1"></i>{{ $s->phone_student }}
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="small text-dark" title="{{ $s->primary_parent_name }}">
                                    {{ $s->primary_parent_name ?: '-' }}
                                </div>
                                @if($s->primary_parent_phone)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s->primary_parent_phone) }}" target="_blank" class="small text-decoration-none text-success" onclick="event.stopPropagation()" title="Chat WhatsApp Orang Tua">
                                        <i class="ti ti-brand-whatsapp me-1"></i>{{ $s->primary_parent_phone }}
                                    </a>
                                @endif
                            </td>
                            <td class="text-end" onclick="event.stopPropagation()">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-detail-siswa" 
                                        data-student="{{ json_encode($s) }}"
                                        data-classroom="{{ $displayClassName }}"
                                        data-homeroom="{{ $displayHomeroom }}"
                                        data-status-label="{{ $contextStatusLabel }}"
                                        data-status-badge="{{ $contextStatusBadge }}"
                                        title="Lihat Biodata Lengkap">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                    @if($tab === 'aktif' || $s->status === 'aktif')
                                        <button type="button" class="btn btn-sm btn-outline-warning btn-trigger-mutasi"
                                            data-student-id="{{ $s->id }}"
                                            data-student-name="{{ $s->name }}"
                                            data-classroom-name="{{ $displayClassName }}"
                                            data-nisn="{{ $s->nisn ?: '-' }}"
                                            title="Catat Mutasi / Keluar">
                                            <i class="ti ti-user-x"></i>
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-siswa"
                                        data-student="{{ json_encode($s) }}"
                                        title="Edit Siswa">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <form action="{{ route('students.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data siswa {{ $s->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="ti ti-school-off fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            Tidak ada data siswa yang ditemukan pada kategori ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($students->hasPages())
        <div class="card-footer bg-white d-flex align-items-center justify-content-between py-2">
            <div class="text-muted small">
                Menampilkan {{ $students->firstItem() }} s/d {{ $students->lastItem() }} dari {{ $students->total() }} data siswa
            </div>
            <div>
                {{ $students->links() }}
            </div>
        </div>
    @endif
</div>

<!-- ========================================================================= -->
<!-- MODAL: DETAIL PROFIL LENGKAP SISWA (BUKU INDUK PPDB & DAPODIK) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-detail-siswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="ti ti-id-badge text-primary me-2 fs-2"></i>
                    Buku Induk & Biodata Lengkap Siswa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Header Siswa -->
                <div class="d-flex align-items-center mb-4 p-3 bg-white border rounded shadow-sm">
                    <div class="avatar avatar-xl bg-blue-lt text-primary rounded-circle me-3 fw-bold fs-2">
                        <span id="dt-initial">S</span>
                    </div>
                    <div>
                        <h3 class="fw-bold text-dark mb-1" id="dt-name">Nama Siswa</h3>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-primary-lt" id="dt-classroom">Kelas</span>
                            <span class="badge bg-info-lt" id="dt-gender">Laki-laki</span>
                            <span class="badge bg-success-lt" id="dt-status">Aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Box Arsip Mutasi / Keluar (Jika Ada) -->
                <div id="dt-mutation-box" class="alert alert-danger border-0 shadow-sm mb-3" style="display: none;">
                    <div class="d-flex align-items-start">
                        <i class="ti ti-file-certificate fs-1 me-3 text-danger"></i>
                        <div class="flex-grow-1">
                            <h4 class="alert-title fw-bold mb-1 text-danger">
                                <span id="dt-mutation-type-badge" class="badge bg-danger text-white me-2">MUTASI</span>
                                Riwayat Mutasi / Keluar & Dokumen Bukti
                            </h4>
                            <div class="row g-2 mt-1 small">
                                <div class="col-sm-6">
                                    <span class="text-muted">Tanggal Efektif:</span>
                                    <strong id="dt-mutation-date" class="text-dark ms-1">-</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Kategori Alasan:</span>
                                    <strong id="dt-mutation-category" class="text-dark ms-1">-</strong>
                                </div>
                                <div class="col-sm-6" id="dt-mutation-school-wrapper">
                                    <span class="text-muted">Sekolah Tujuan:</span>
                                    <strong id="dt-mutation-school" class="text-primary ms-1">-</strong>
                                </div>
                                <div class="col-sm-6" id="dt-mutation-letter-wrapper">
                                    <span class="text-muted">Nomor Surat/BA:</span>
                                    <strong id="dt-mutation-letter" class="text-dark ms-1">-</strong>
                                </div>
                                <div class="col-12" id="dt-mutation-desc-wrapper">
                                    <span class="text-muted">Keterangan / Kronologi:</span>
                                    <div id="dt-mutation-desc" class="p-2 bg-white rounded border mt-1 text-dark">-</div>
                                </div>
                                <div class="col-12 mt-2" id="dt-mutation-attachment-wrapper">
                                    <a href="#" id="dt-mutation-download-btn" class="btn btn-sm btn-outline-danger shadow-sm">
                                        <i class="ti ti-download me-1"></i> <span id="dt-mutation-file-name">Unduh Berkas Bukti (Surat Pengunduran Diri / BA)</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 1: Identitas Pribadi -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light py-2">
                        <h5 class="card-title fw-bold text-dark m-0 fs-4">
                            <i class="ti ti-user me-2 text-primary"></i> 1. Identitas Pribadi Siswa
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="text-muted small">NISN (Nomor Induk Siswa Nasional):</div>
                                <div class="fw-bold font-monospace text-dark" id="dt-nisn">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">NIS (Nomor Induk Sekolah):</div>
                                <div class="fw-bold font-monospace text-dark" id="dt-nis">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">NIK (Nomor Induk Kependudukan):</div>
                                <div class="fw-bold font-monospace text-dark" id="dt-nik">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">No. Kartu Keluarga (KK):</div>
                                <div class="fw-bold font-monospace text-dark" id="dt-family-card-no">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">No. Registrasi Akta Lahir:</div>
                                <div class="fw-bold text-dark" id="dt-birth-cert-no">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Agama:</div>
                                <div class="fw-bold text-dark" id="dt-religion">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Tempat & Tanggal Lahir:</div>
                                <div class="fw-bold text-dark" id="dt-birth">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Urutan Kelahiran:</div>
                                <div class="fw-bold text-dark" id="dt-child-order-siblings">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Wali Kelas:</div>
                                <div class="fw-bold text-success" id="dt-homeroom">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Tahun Masuk / Angkatan:</div>
                                <div class="fw-bold text-dark" id="dt-entry-year">-</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Alamat Tempat Tinggal:</div>
                                <div class="fw-bold text-dark" id="dt-address">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">No. HP / WA Siswa:</div>
                                <div class="fw-bold text-primary font-monospace" id="dt-phone-student">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Email Siswa:</div>
                                <div class="fw-bold text-dark" id="dt-email">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Data Orang Tua & Wali -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light py-2">
                        <h5 class="card-title fw-bold text-dark m-0 fs-4">
                            <i class="ti ti-users me-2 text-info"></i> 2. Data Orang Tua & Wali Siswa
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <!-- Ayah -->
                            <div class="col-md-4 border-end">
                                <div class="badge bg-blue-lt mb-2">Data Ayah Kandung</div>
                                <div class="text-muted small">Nama Ayah:</div>
                                <div class="fw-bold text-dark" id="dt-father-name">-</div>
                                <div class="text-muted small mt-1">NIK Ayah:</div>
                                <div class="font-monospace small text-dark" id="dt-father-nik">-</div>
                                <div class="text-muted small mt-1">Tahun Lahir & Pendidikan:</div>
                                <div class="text-dark small" id="dt-father-edu">-</div>
                                <div class="text-muted small mt-1">Pekerjaan:</div>
                                <div class="text-dark small" id="dt-father-job">-</div>
                                <div class="text-muted small mt-1">Penghasilan:</div>
                                <div class="text-dark small" id="dt-father-income">-</div>
                                <div class="text-muted small mt-1">No. HP / WA:</div>
                                <div class="font-monospace small text-primary" id="dt-father-phone">-</div>
                            </div>
                            <!-- Ibu -->
                            <div class="col-md-4 border-end">
                                <div class="badge bg-pink-lt mb-2">Data Ibu Kandung</div>
                                <div class="text-muted small">Nama Ibu:</div>
                                <div class="fw-bold text-dark" id="dt-mother-name">-</div>
                                <div class="text-muted small mt-1">NIK Ibu:</div>
                                <div class="font-monospace small text-dark" id="dt-mother-nik">-</div>
                                <div class="text-muted small mt-1">Tahun Lahir & Pendidikan:</div>
                                <div class="text-dark small" id="dt-mother-edu">-</div>
                                <div class="text-muted small mt-1">Pekerjaan:</div>
                                <div class="text-dark small" id="dt-mother-job">-</div>
                                <div class="text-muted small mt-1">Penghasilan:</div>
                                <div class="text-dark small" id="dt-mother-income">-</div>
                                <div class="text-muted small mt-1">No. HP / WA:</div>
                                <div class="font-monospace small text-primary" id="dt-mother-phone">-</div>
                            </div>
                            <!-- Wali -->
                            <div class="col-md-4">
                                <div class="badge bg-secondary-lt mb-2">Data Wali (Jika Ada)</div>
                                <div class="text-muted small">Nama Wali:</div>
                                <div class="fw-bold text-dark" id="dt-guardian-name">-</div>
                                <div class="text-muted small mt-1">NIK Wali:</div>
                                <div class="font-monospace small text-dark" id="dt-guardian-nik">-</div>
                                <div class="text-muted small mt-1">Tahun Lahir & Pendidikan:</div>
                                <div class="text-dark small" id="dt-guardian-edu">-</div>
                                <div class="text-muted small mt-1">Pekerjaan:</div>
                                <div class="text-dark small" id="dt-guardian-job">-</div>
                                <div class="text-muted small mt-1">Penghasilan:</div>
                                <div class="text-dark small" id="dt-guardian-income">-</div>
                                <div class="text-muted small mt-1">No. HP / WA:</div>
                                <div class="font-monospace small text-primary" id="dt-guardian-phone">-</div>
                            </div>
                            <!-- Kontak Utama Ortu -->
                            <div class="col-12 bg-light p-2 rounded mt-2">
                                <span class="fw-bold text-dark small"><i class="ti ti-bell-ringing me-1 text-warning"></i> Kontak Utama Notifikasi Sekolah (Ortu/Wali):</span>
                                <span class="fw-bold text-success font-monospace ms-2" id="dt-phone-parent">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 3: Zonasi, Transportasi & Antropometri (Dapodik) -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light py-2">
                        <h5 class="card-title fw-bold text-dark m-0 fs-4">
                            <i class="ti ti-map-pin me-2 text-warning"></i> 3. Zonasi, Transportasi & Data Fisik
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-sm-4">
                                <div class="text-muted small">Jarak ke Sekolah:</div>
                                <div class="fw-bold text-dark" id="dt-distance">-</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-muted small">Moda Transportasi:</div>
                                <div class="fw-bold text-dark" id="dt-transportation">-</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-muted small">Jenis Tempat Tinggal:</div>
                                <div class="fw-bold text-dark" id="dt-residence-type">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Sekolah Asal:</div>
                                <div class="fw-bold text-dark" id="dt-school-origin">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Titik Koordinat (Lintang, Bujur):</div>
                                <div class="fw-bold font-monospace text-dark" id="dt-coordinates">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Antropometri (TB / BB / Lingkar Kepala):</div>
                                <div class="fw-bold text-dark" id="dt-physical">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Kebutuhan Khusus:</div>
                                <div class="fw-bold text-dark" id="dt-special-needs">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 4: Program Bantuan Siswa & Rekening -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light py-2">
                        <h5 class="card-title fw-bold text-dark m-0 fs-4">
                            <i class="ti ti-gift me-2 text-success"></i> 4. Program Bantuan Siswa & Rekening
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="text-muted small">Program Indonesia Pintar (PIP):</div>
                                <div class="fw-bold text-dark" id="dt-pip-status">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Kartu Indonesia Pintar (KIP):</div>
                                <div class="fw-bold text-dark" id="dt-kip-status">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Nomor KKS / KPS:</div>
                                <div class="fw-bold text-dark" id="dt-kks-kps">-</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Rekening Bank Bantuan:</div>
                                <div class="fw-bold text-dark font-monospace" id="dt-bank-account">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 5: Catatan Tambahan -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h5 class="card-title fw-bold text-dark m-0 fs-4">
                            <i class="ti ti-notes me-2 text-secondary"></i> 5. Catatan Tambahan Siswa
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="text-secondary" id="dt-notes">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: TAMBAH SISWA BARU -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-create-siswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <form action="{{ route('students.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-user-plus me-2 fs-2"></i>
                        Tambah Siswa Baru ke Data Pokok
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Nav Tabs Tambah -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active fw-bold" data-bs-toggle="tab" href="#tab-add-pribadi" role="tab">
                                <i class="ti ti-user me-1 text-primary"></i> 1. Identitas Pribadi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab-add-ortu" role="tab">
                                <i class="ti ti-users me-1 text-info"></i> 2. Orang Tua & Wali
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab-add-kontak" role="tab">
                                <i class="ti ti-phone-call me-1 text-success"></i> 3. Kontak & Status
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: Identitas Pribadi -->
                        <div class="tab-pane fade show active" id="tab-add-pribadi" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Nama Lengkap Siswa</label>
                                    <input type="text" name="name" class="form-control" required placeholder="Contoh: Muhammad Rizki Pratama">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Kelas / Rombel</label>
                                    <select name="classroom_id" class="form-select" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        @foreach($classrooms as $c)
                                            <option value="{{ $c->id }}" {{ (string)$classroomId === (string)$c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->academic_year }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NISN</label>
                                    <input type="text" name="nisn" class="form-control" placeholder="10 digit nomor NISN">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NIS</label>
                                    <input type="text" name="nis" class="form-control" placeholder="Nomor Induk Sekolah">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NIK (No. KTP/KK)</label>
                                    <input type="text" name="nik" class="form-control" placeholder="16 digit NIK">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Jenis Kelamin</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tempat Lahir</label>
                                    <input type="text" name="birth_place" class="form-control" placeholder="Kota / Kabupaten lahir">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="birth_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Agama</label>
                                    <select name="religion" class="form-select">
                                        <option value="">-- Pilih Agama --</option>
                                        <option value="Islam">Islam</option>
                                        <option value="Kristen Protestan">Kristen Protestan</option>
                                        <option value="Katolik">Katolik</option>
                                        <option value="Hindu">Hindu</option>
                                        <option value="Buddha">Buddha</option>
                                        <option value="Konghucu">Konghucu</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. HP / WA Siswa</label>
                                    <input type="text" name="phone_student" class="form-control" placeholder="08xxxxxxxxxx">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat Lengkap Tempat Tinggal</label>
                                    <textarea name="address" class="form-control" rows="2" placeholder="Nama Dusun, RT/RW, Desa/Kelurahan, Kecamatan, Kabupaten"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Data Orang Tua & Wali -->
                        <div class="tab-pane fade" id="tab-add-ortu" role="tabpanel">
                            <div class="row g-3">
                                <!-- Data Ayah -->
                                <div class="col-12">
                                    <h5 class="fw-bold text-primary mb-2 border-bottom pb-1"><i class="ti ti-man me-1"></i> Data Ayah Kandung</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Ayah</label>
                                    <input type="text" name="father_name" class="form-control" placeholder="Nama lengkap ayah">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Ayah</label>
                                    <input type="text" name="father_job" class="form-control" placeholder="Contoh: Wiraswasta, PNS, Petani">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Ayah</label>
                                    <input type="text" name="father_phone" class="form-control" placeholder="08xxxxxxxxxx">
                                </div>

                                <!-- Data Ibu -->
                                <div class="col-12 mt-3">
                                    <h5 class="fw-bold text-pink mb-2 border-bottom pb-1"><i class="ti ti-woman me-1"></i> Data Ibu Kandung</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Ibu</label>
                                    <input type="text" name="mother_name" class="form-control" placeholder="Nama lengkap ibu">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Ibu</label>
                                    <input type="text" name="mother_job" class="form-control" placeholder="Contoh: Ibu Rumah Tangga, Guru, Pedagang">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Ibu</label>
                                    <input type="text" name="mother_phone" class="form-control" placeholder="08xxxxxxxxxx">
                                </div>

                                <!-- Data Wali -->
                                <div class="col-12 mt-3">
                                    <h5 class="fw-bold text-secondary mb-2 border-bottom pb-1"><i class="ti ti-user-check me-1"></i> Data Wali (Opsional)</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Wali</label>
                                    <input type="text" name="guardian_name" class="form-control" placeholder="Nama wali jika tinggal dengan wali">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Wali</label>
                                    <input type="text" name="guardian_job" class="form-control" placeholder="Pekerjaan wali">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Wali</label>
                                    <input type="text" name="guardian_phone" class="form-control" placeholder="08xxxxxxxxxx">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Kontak & Status -->
                        <div class="tab-pane fade" id="tab-add-kontak" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">
                                        <i class="ti ti-bell-ringing me-1 text-warning"></i> Kontak Utama Ortu/Wali (Untuk Notifikasi)
                                    </label>
                                    <input type="text" name="phone_parent" class="form-control" placeholder="Nomor WA yang aktif menerima info sekolah">
                                    <small class="text-muted">Nomor ini akan menjadi tujuan utama notifikasi keterlambatan, izin, dan info penting sekolah.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Siswa</label>
                                    <select name="status" class="form-select">
                                        <option value="aktif" selected>Aktif Terdaftar</option>
                                        <option value="lulus">Lulus (Alumni)</option>
                                        <option value="mutasi">Mutasi Keluar</option>
                                        <option value="keluar">Keluar / DO</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tahun Masuk / Angkatan (Opsional)</label>
                                    <input type="text" name="entry_year" class="form-control" placeholder="Contoh: 2024/2025">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tahun Kelulusan (Jika Alumni)</label>
                                    <input type="text" name="graduation_year" class="form-control" placeholder="Contoh: 2026/2027">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan Tambahan (Kesehatan, Prestasi, Kebutuhan Khusus, dll)</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Tuliskan catatan khusus terkait siswa..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-check me-1"></i> Simpan Data Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT DATA SISWA -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-edit-siswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <form id="form-edit-siswa" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-edit me-2 fs-2"></i>
                        Edit Biodata Siswa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Nav Tabs Edit -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active fw-bold" data-bs-toggle="tab" href="#tab-edit-pribadi" role="tab">
                                <i class="ti ti-user me-1 text-primary"></i> 1. Identitas Pribadi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab-edit-ortu" role="tab">
                                <i class="ti ti-users me-1 text-info"></i> 2. Orang Tua & Wali
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab-edit-kontak" role="tab">
                                <i class="ti ti-phone-call me-1 text-success"></i> 3. Kontak & Status
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: Identitas Pribadi -->
                        <div class="tab-pane fade show active" id="tab-edit-pribadi" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Nama Lengkap Siswa</label>
                                    <input type="text" name="name" id="edit_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kelas / Rombel</label>
                                    <select name="classroom_id" id="edit_classroom_id" class="form-select">
                                        <option value="">-- Tanpa Rombel (Alumni/Mutasi) --</option>
                                        @foreach($classrooms as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->academic_year }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NISN</label>
                                    <input type="text" name="nisn" id="edit_nisn" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NIS</label>
                                    <input type="text" name="nis" id="edit_nis" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NIK</label>
                                    <input type="text" name="nik" id="edit_nik" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Jenis Kelamin</label>
                                    <select name="gender" id="edit_gender" class="form-select" required>
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tempat Lahir</label>
                                    <input type="text" name="birth_place" id="edit_birth_place" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="birth_date" id="edit_birth_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Agama</label>
                                    <select name="religion" id="edit_religion" class="form-select">
                                        <option value="">-- Pilih Agama --</option>
                                        <option value="Islam">Islam</option>
                                        <option value="Kristen Protestan">Kristen Protestan</option>
                                        <option value="Katolik">Katolik</option>
                                        <option value="Hindu">Hindu</option>
                                        <option value="Buddha">Buddha</option>
                                        <option value="Konghucu">Konghucu</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. HP / WA Siswa</label>
                                    <input type="text" name="phone_student" id="edit_phone_student" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat Lengkap</label>
                                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Data Orang Tua & Wali -->
                        <div class="tab-pane fade" id="tab-edit-ortu" role="tabpanel">
                            <div class="row g-3">
                                <!-- Data Ayah -->
                                <div class="col-12">
                                    <h5 class="fw-bold text-primary mb-2 border-bottom pb-1"><i class="ti ti-man me-1"></i> Data Ayah Kandung</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Ayah</label>
                                    <input type="text" name="father_name" id="edit_father_name" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Ayah</label>
                                    <input type="text" name="father_job" id="edit_father_job" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Ayah</label>
                                    <input type="text" name="father_phone" id="edit_father_phone" class="form-control">
                                </div>

                                <!-- Data Ibu -->
                                <div class="col-12 mt-3">
                                    <h5 class="fw-bold text-pink mb-2 border-bottom pb-1"><i class="ti ti-woman me-1"></i> Data Ibu Kandung</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Ibu</label>
                                    <input type="text" name="mother_name" id="edit_mother_name" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Ibu</label>
                                    <input type="text" name="mother_job" id="edit_mother_job" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Ibu</label>
                                    <input type="text" name="mother_phone" id="edit_mother_phone" class="form-control">
                                </div>

                                <!-- Data Wali -->
                                <div class="col-12 mt-3">
                                    <h5 class="fw-bold text-secondary mb-2 border-bottom pb-1"><i class="ti ti-user-check me-1"></i> Data Wali (Opsional)</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Wali</label>
                                    <input type="text" name="guardian_name" id="edit_guardian_name" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pekerjaan Wali</label>
                                    <input type="text" name="guardian_job" id="edit_guardian_job" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. HP / WA Wali</label>
                                    <input type="text" name="guardian_phone" id="edit_guardian_phone" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Kontak & Status -->
                        <div class="tab-pane fade" id="tab-edit-kontak" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">
                                        <i class="ti ti-bell-ringing me-1 text-warning"></i> Kontak Utama Ortu/Wali (Untuk Notifikasi)
                                    </label>
                                    <input type="text" name="phone_parent" id="edit_phone_parent" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Siswa</label>
                                    <select name="status" id="edit_status" class="form-select">
                                        <option value="aktif">Aktif Terdaftar</option>
                                        <option value="lulus">Lulus (Alumni)</option>
                                        <option value="mutasi">Mutasi Keluar</option>
                                        <option value="keluar">Keluar / DO</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tahun Masuk / Angkatan</label>
                                    <input type="text" name="entry_year" id="edit_entry_year" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tahun Kelulusan (Jika Alumni)</label>
                                    <input type="text" name="graduation_year" id="edit_graduation_year" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Kelas Terakhir (Untuk Alumni/Mutasi)</label>
                                    <input type="text" name="last_classroom_name" id="edit_last_classroom_name" class="form-control" placeholder="Contoh: XII TKR 1">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan Tambahan</label>
                                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="ti ti-check me-1"></i> Perbarui Data Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: IMPORT DATA SISWA -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-import-siswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="{{ route('students.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="ti ti-file-import me-2 fs-2"></i>
                        Import Siswa dari Excel / CSV
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm d-flex mb-3">
                        <i class="ti ti-info-circle fs-2 me-2"></i>
                        <div>
                            <div>Pastikan kolom sesuai format template resmi (NISN, NIK, Nama, Kelas, JK, Tempat/Tgl Lahir, Data Ortu, dan No HP).</div>
                            <div class="mt-1 small">💡 <b>Format Tanggal Lahir:</b> Sistem mendukung format <code>YYYY-MM-DD</code> (contoh: <code>2008-05-15</code>) maupun <code>DD/MM/YYYY</code> / format tanggal Excel bawaan.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Pilih File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" class="form-control" required accept=".xlsx,.xls,.csv,.txt">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tentukan Kelas Default (Opsional)</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">-- Otomatis Berdasarkan Kolom Kelas di File --</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->academic_year }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Jika kolom kelas di Excel kosong, siswa akan dimasukkan ke kelas default ini.</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="{{ route('students.template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-download me-1"></i> Unduh Format Template
                        </a>
                        <span class="text-muted small">Maksimal 5MB</span>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white shadow-sm">
                        <i class="ti ti-upload me-1"></i> Mulai Impor Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: UPLOAD DATA DAPODIK KEMENDIKBUD -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-import-dapodik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form action="{{ route('students.import-dapodik') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <!-- Opsi Otomasi Berjalan Penuh di Belakang Layar -->
                <input type="hidden" name="auto_graduate_grade_12" value="1">
                <input type="hidden" name="auto_mutasi_missing_students" value="1">
                <input type="hidden" name="auto_create_classes" value="1">

                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center text-white">
                        <i class="ti ti-cloud-upload me-2 fs-2"></i>
                        Sinkronisasi/Upload Data dari Dapodik
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-primary border-0 shadow-sm d-flex mb-3" style="background-color: #eef2ff; color: #3730a3;">
                        <i class="ti ti-info-circle fs-2 me-2 flex-shrink-0"></i>
                        <div class="small">
                            <div class="fw-bold mb-1">Unggah Langsung File Resmi Dapodik:</div>
                            <div>Cukup unggah file unduhan asli <b>Daftar PD (Peserta Didik)</b> dari aplikasi Dapodik Kemendikbud (format <code>.xlsx</code> atau <code>.csv</code> tanpa perlu diubah susunannya).</div>
                            <div class="mt-2 text-muted">
                                💡 <b>Otomasi Penuh Sistem:</b>
                                <ul class="mb-0 ps-3">
                                    <li>Siswa baru otomatis ditambahkan ke kelas X.</li>
                                    <li>Kenaikan kelas & pembaruan rombel otomatis disesuaikan.</li>
                                    <li>Siswa kelas XII tahun lalu otomatis dialihkan ke status Alumni.</li>
                                    <li>Siswa lama yang tidak terdaftar otomatis dipindahkan ke status Mutasi / Keluar.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih File Dapodik (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" class="form-control" required accept=".xlsx,.xls,.csv,.txt">
                        <small class="text-muted">Gunakan file ekspor resmi dari aplikasi Dapodik (Maksimal 10MB).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Tahun Pelajaran Target</label>
                        <select name="academic_year" class="form-select" required>
                            @foreach($availableAcademicYears ?? [$academicYear] as $ay)
                                <option value="{{ $ay }}" {{ (string)$academicYear === (string)$ay ? 'selected' : '' }}>
                                    Tahun Pelajaran {{ $ay }} {{ (string)$academicYear === (string)$ay ? '(Aktif Saat Ini)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Data rombel, kenaikan kelas, dan sinkronisasi siswa akan langsung dikaitkan ke tahun pelajaran ini.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white shadow-sm" style="background-color: #4f46e5; border-color: #4f46e5;">
                        <i class="ti ti-cloud-upload me-1"></i> Mulai Sinkronisasi Dapodik
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($tab !== 'alumni' && $tab !== 'mutasi')
<!-- ========================================================================= -->
<!-- MODAL: PENCATATAN MUTASI / KELUAR SISWA DENGAN BERKAS BUKTI -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-mutasi-siswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form action="{{ route('students.mutations.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                        <i class="ti ti-user-x me-2 fs-2"></i>
                        Pencatatan Mutasi / Pengunduran Diri Siswa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm mb-3">
                        <div class="d-flex">
                            <i class="ti ti-info-circle fs-2 me-2 text-info"></i>
                            <div class="small">
                                Rekam mutasi siswa keluar sebelum kenaikan kelas. Lengkapi alasan, kategori penyebab keluar, dan lampirkan berkas bukti (surat pengunduran diri orang tua, surat mutasi masuk/keluar, atau berita acara pelanggaran/BK) agar sekolah dapat menganalisis data kesiswaan di kemudian hari.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold required text-dark">Pilih Siswa yang Mutasi / Keluar</label>
                            <div class="position-relative">
                                <div class="input-icon">
                                    <span class="input-icon-addon"><i class="ti ti-search text-muted"></i></span>
                                    <input type="text" 
                                           id="mutasi-student-search" 
                                           class="form-control" 
                                           placeholder="Ketik minimal 2 huruf nama atau NISN siswa..." 
                                           autocomplete="off">
                                </div>
                                <input type="hidden" name="student_id" id="mutasi-student-id" required>
                                
                                <div id="mutasi-student-results" 
                                     class="dropdown-menu w-100 shadow-lg p-1" 
                                     style="max-height: 250px; overflow-y: auto; display: none; position: absolute; top: 100%; left: 0; z-index: 1060;">
                                </div>
                            </div>
                            
                            <!-- Kartu Preview Siswa Terpilih -->
                            <div id="mutasi-selected-preview" class="mt-2 p-2 bg-light rounded border d-none align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm bg-danger-lt rounded-circle me-2"><i class="ti ti-user-x"></i></span>
                                    <div>
                                        <div class="fw-bold text-dark" id="mutasi-preview-name">-</div>
                                        <div class="text-muted small">
                                            Kelas: <span class="fw-bold text-dark" id="mutasi-preview-class">-</span> &bull; NISN: <span class="font-monospace" id="mutasi-preview-nisn">-</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon" id="btn-mutasi-clear-student" title="Ganti Siswa">
                                    <i class="ti ti-x"></i>
                                </button>
                            </div>
                            <div class="form-text text-muted small mt-1">
                                Ketik nama atau NISN untuk menampilkan referensi data siswa aktif.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Jenis Keputusan</label>
                            <select name="type" class="form-select" required>
                                <option value="mutasi">📤 Pindah / Mutasi ke Sekolah Lain</option>
                                <option value="keluar">📄 Mengundurkan Diri (Permohonan Sendiri)</option>
                                <option value="dikeluarkan">⛔ Dikeluarkan karena Pelanggaran / Disiplin</option>
                                <option value="drop_out">⚠️ Putus Sekolah (Drop Out)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Tanggal Efektif Mutasi / Keluar</label>
                            <input type="date" name="mutation_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Kategori Alasan / Faktor Penyebab</label>
                            <select name="reason_category" class="form-select" required>
                                <option value="pindah_domisili">🏠 Pindah Domisili / Ikut Orang Tua</option>
                                <option value="ekonomi_keluarga">💰 Kendala Ekonomi / Finansial Keluarga</option>
                                <option value="pelanggaran_disiplin">⚖️ Pelanggaran Tata Tertib / Kasus Disiplin</option>
                                <option value="mengundurkan_diri">✍️ Mengundurkan Diri / Tidak Berminat</option>
                                <option value="bekerja">💼 Bekerja / Membantu Usaha Orang Tua</option>
                                <option value="kesehatan">🏥 Kondisi Kesehatan / Sakit Menahun</option>
                                <option value="menikah">💍 Menikah / Berkeluarga</option>
                                <option value="lainnya">❓ Lain-lain / Tidak Diketahui</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark">Sekolah Tujuan (Jika Mutasi)</label>
                            <input type="text" name="target_school" class="form-control" placeholder="Contoh: SMAN 1 Surabaya / SMKN 2 Bandung">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark">Nomor Surat / Berita Acara</label>
                            <input type="text" name="letter_number" class="form-control" placeholder="Contoh: 421.3/088/SMK/X/2026">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark">Unggah Dokumen Bukti (Surat Permohonan / BA / dll)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text small">Format: PDF, JPG, PNG, DOC/DOCX. Maksimal 5 MB.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark">Uraian Alasan / Kronologi (Opsional)</label>
                            <textarea name="reason_description" class="form-control" rows="3" placeholder="Jelaskan alasan detail, hasil bimbingan konseling (BK), kronologi pelanggaran, atau riwayat pendukung..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger fw-bold shadow-sm" onclick="return confirm('Apakah Anda yakin ingin memproses mutasi / pengunduran diri siswa ini? Siswa akan dipindahkan ke Tab Mutasi.');">
                        <i class="ti ti-check me-1"></i> Simpan Data Mutasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
.student-row:hover {
    background-color: rgba(var(--tblr-primary-rgb), 0.05) !important;
}
</style>
@endpush

@push('scripts')
<script>
function openStudentDetailModal(student, classroomName, homeroomName, statusLabel, statusBadge) {
    document.getElementById('dt-initial').textContent = (student.name || 'S').charAt(0).toUpperCase();
    document.getElementById('dt-name').textContent = student.name || '-';
    document.getElementById('dt-classroom').textContent = classroomName || '-';
    document.getElementById('dt-gender').textContent = student.gender === 'P' ? 'Perempuan' : 'Laki-laki';
    
    const statusElem = document.getElementById('dt-status');
    statusElem.textContent = statusLabel || (student.is_active ? 'Aktif Terdaftar' : 'Non-Aktif');
    statusElem.className = 'badge ' + (statusBadge || (student.is_active ? 'bg-success-lt' : 'bg-secondary-lt'));

    document.getElementById('dt-nisn').textContent = student.nisn || '-';
    document.getElementById('dt-nis').textContent = student.nis || '-';
    document.getElementById('dt-nik').textContent = student.nik || '-';
    document.getElementById('dt-family-card-no').textContent = student.family_card_no || '-';
    document.getElementById('dt-birth-cert-no').textContent = student.birth_certificate_no || '-';
    document.getElementById('dt-religion').textContent = student.religion || '-';

    let birthStr = '-';
    if (student.birth_place && student.birth_date) {
        birthStr = student.birth_place + ', ' + student.birth_date.split('T')[0];
    } else if (student.birth_place) {
        birthStr = student.birth_place;
    } else if (student.birth_date) {
        birthStr = student.birth_date.split('T')[0];
    }
    document.getElementById('dt-birth').textContent = birthStr;

    let siblingStr = '-';
    if (student.child_order && student.siblings_count) {
        siblingStr = `Anak ke-${student.child_order} dari ${student.siblings_count} bersaudara`;
    } else if (student.child_order) {
        siblingStr = `Anak ke-${student.child_order}`;
    }
    document.getElementById('dt-child-order-siblings').textContent = siblingStr;

    document.getElementById('dt-homeroom').textContent = homeroomName || '-';
    document.getElementById('dt-address').textContent = student.address || '-';
    document.getElementById('dt-phone-student').textContent = student.phone_student || '-';
    document.getElementById('dt-email').textContent = student.email || '-';
    document.getElementById('dt-entry-year').textContent = student.entry_year || '-';

    // Data Ortu
    document.getElementById('dt-father-name').textContent = student.father_name || '-';
    document.getElementById('dt-father-nik').textContent = student.father_nik || '-';
    document.getElementById('dt-father-edu').textContent = [student.father_birth_year ? `Thn: ${student.father_birth_year}` : null, student.father_education].filter(Boolean).join(' | ') || '-';
    document.getElementById('dt-father-job').textContent = student.father_job || '-';
    document.getElementById('dt-father-income').textContent = student.father_income || '-';
    document.getElementById('dt-father-phone').textContent = student.father_phone || '-';

    document.getElementById('dt-mother-name').textContent = student.mother_name || '-';
    document.getElementById('dt-mother-nik').textContent = student.mother_nik || '-';
    document.getElementById('dt-mother-edu').textContent = [student.mother_birth_year ? `Thn: ${student.mother_birth_year}` : null, student.mother_education].filter(Boolean).join(' | ') || '-';
    document.getElementById('dt-mother-job').textContent = student.mother_job || '-';
    document.getElementById('dt-mother-income').textContent = student.mother_income || '-';
    document.getElementById('dt-mother-phone').textContent = student.mother_phone || '-';

    document.getElementById('dt-guardian-name').textContent = student.guardian_name || '-';
    document.getElementById('dt-guardian-nik').textContent = student.guardian_nik || '-';
    document.getElementById('dt-guardian-edu').textContent = [student.guardian_birth_year ? `Thn: ${student.guardian_birth_year}` : null, student.guardian_education].filter(Boolean).join(' | ') || '-';
    document.getElementById('dt-guardian-job').textContent = student.guardian_job || '-';
    document.getElementById('dt-guardian-income').textContent = student.guardian_income || '-';
    document.getElementById('dt-guardian-phone').textContent = student.guardian_phone || '-';

    document.getElementById('dt-phone-parent').textContent = student.phone_parent || '-';

    // Dapodik Zonasi, Transportasi & Fisik
    document.getElementById('dt-distance').textContent = student.distance_to_school_km ? `${student.distance_to_school_km} km` : '-';
    document.getElementById('dt-transportation').textContent = student.transportation || '-';
    document.getElementById('dt-residence-type').textContent = student.residence_type || '-';
    document.getElementById('dt-school-origin').textContent = student.school_origin || '-';

    let coords = '-';
    if (student.latitude || student.longitude) {
        coords = `${student.latitude || '-'}, ${student.longitude || '-'}`;
    }
    document.getElementById('dt-coordinates').textContent = coords;

    let physical = [];
    if (student.height_cm) physical.push(`TB: ${student.height_cm} cm`);
    if (student.weight_kg) physical.push(`BB: ${student.weight_kg} kg`);
    if (student.head_circumference_cm) physical.push(`LK: ${student.head_circumference_cm} cm`);
    document.getElementById('dt-physical').textContent = physical.length > 0 ? physical.join(' | ') : '-';

    document.getElementById('dt-special-needs').textContent = student.special_needs || 'Tidak ada';

    // Bantuan & Bank
    let pipText = student.is_pip_eligible || '-';
    if (student.pip_reason) {
        pipText += ` (${student.pip_reason})`;
    }
    document.getElementById('dt-pip-status').textContent = pipText;

    let kipText = student.kip_recipient || '-';
    if (student.kip_number) {
        kipText += ` (No: ${student.kip_number})`;
    }
    document.getElementById('dt-kip-status').textContent = kipText;

    let kksKps = [];
    if (student.kks_number) kksKps.push(`KKS: ${student.kks_number}`);
    if (student.kps_number) kksKps.push(`KPS: ${student.kps_number}`);
    document.getElementById('dt-kks-kps').textContent = kksKps.length > 0 ? kksKps.join(' | ') : '-';

    let bankText = '-';
    if (student.bank_name || student.bank_account_no) {
        bankText = `${student.bank_name || ''} ${student.bank_account_no || ''}`;
        if (student.bank_account_name) {
            bankText += ` a.n ${student.bank_account_name}`;
        }
    }
    document.getElementById('dt-bank-account').textContent = bankText.trim() || '-';

    document.getElementById('dt-notes').textContent = student.notes || 'Tidak ada catatan tambahan.';

    // Tampilkan Riwayat Mutasi / Dokumen Bukti jika ada
    const mutationBox = document.getElementById('dt-mutation-box');
    const mutation = student.latest_mutation;
    if (mutationBox) {
        if (mutation) {
            mutationBox.style.display = 'block';
            document.getElementById('dt-mutation-type-badge').textContent = (mutation.type || 'MUTASI').toUpperCase();
            document.getElementById('dt-mutation-date').textContent = mutation.mutation_date ? mutation.mutation_date.split('T')[0] : '-';
            
            const categoryLabels = {
                'pindah_domisili': 'Pindah Domisili / Ikut Orang Tua',
                'ekonomi_keluarga': 'Kendala Ekonomi / Finansial',
                'pelanggaran_disiplin': 'Pelanggaran Disiplin / Tata Tertib',
                'mengundurkan_diri': 'Mengundurkan Diri',
                'bekerja': 'Bekerja / Usaha',
                'kesehatan': 'Kesehatan / Sakit Menahun',
                'menikah': 'Menikah',
                'lainnya': 'Lain-lain'
            };
            document.getElementById('dt-mutation-category').textContent = categoryLabels[mutation.reason_category] || (mutation.reason_category ? mutation.reason_category.replace(/_/g, ' ') : '-');

            const schoolWrapper = document.getElementById('dt-mutation-school-wrapper');
            if (mutation.target_school) {
                schoolWrapper.style.display = 'block';
                document.getElementById('dt-mutation-school').textContent = mutation.target_school;
            } else {
                schoolWrapper.style.display = 'none';
            }

            const letterWrapper = document.getElementById('dt-mutation-letter-wrapper');
            if (mutation.letter_number) {
                letterWrapper.style.display = 'block';
                document.getElementById('dt-mutation-letter').textContent = mutation.letter_number;
            } else {
                letterWrapper.style.display = 'none';
            }

            const descWrapper = document.getElementById('dt-mutation-desc-wrapper');
            if (mutation.reason_description) {
                descWrapper.style.display = 'block';
                document.getElementById('dt-mutation-desc').textContent = mutation.reason_description;
            } else {
                descWrapper.style.display = 'none';
            }

            const attachWrapper = document.getElementById('dt-mutation-attachment-wrapper');
            if (mutation.attachment_path) {
                attachWrapper.style.display = 'block';
                const dlBtn = document.getElementById('dt-mutation-download-btn');
                dlBtn.href = `/siswa/mutasi/${mutation.id}/download`;
                document.getElementById('dt-mutation-file-name').textContent = mutation.attachment_name ? `Unduh: ${mutation.attachment_name}` : 'Unduh Berkas Bukti (Surat/BA)';
            } else {
                attachWrapper.style.display = 'none';
            }
        } else {
            mutationBox.style.display = 'none';
        }
    }

    const modal = new bootstrap.Modal(document.getElementById('modal-detail-siswa'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Klik pada baris tabel siswa (Interactive Row Click)
    document.querySelectorAll('.student-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('button, a, form, input, select, .no-modal-trigger')) {
                return;
            }
            try {
                const student = JSON.parse(this.dataset.student);
                const classroomName = this.dataset.classroom || '-';
                const homeroomName = this.dataset.homeroom || '-';
                const statusLabel = this.dataset.statusLabel || '';
                const statusBadge = this.dataset.statusBadge || '';
                openStudentDetailModal(student, classroomName, homeroomName, statusLabel, statusBadge);
            } catch (err) {
                console.error("Gagal memuat detail siswa:", err);
            }
        });
    });

    // 2. Klik pada tombol detail mata di kolom aksi
    document.querySelectorAll('.btn-detail-siswa').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const student = JSON.parse(this.dataset.student);
            const classroomName = this.dataset.classroom || '-';
            const homeroomName = this.dataset.homeroom || '-';
            const statusLabel = this.dataset.statusLabel || '';
            const statusBadge = this.dataset.statusBadge || '';
            openStudentDetailModal(student, classroomName, homeroomName, statusLabel, statusBadge);
        });
    });

    // 3. Handle Autocomplete Mutasi Siswa & Trigger dari baris tabel
    const mutasiSearchInput = document.getElementById('mutasi-student-search');
    const mutasiHiddenId = document.getElementById('mutasi-student-id');
    const mutasiDropdown = document.getElementById('mutasi-student-results');
    const mutasiPreviewBox = document.getElementById('mutasi-selected-preview');
    const mutasiPreviewName = document.getElementById('mutasi-preview-name');
    const mutasiPreviewClass = document.getElementById('mutasi-preview-class');
    const mutasiPreviewNisn = document.getElementById('mutasi-preview-nisn');
    const mutasiClearBtn = document.getElementById('btn-mutasi-clear-student');

    function selectMutasiStudent(student) {
        if (!student || !student.id) return;
        if (mutasiHiddenId) mutasiHiddenId.value = student.id;
        if (mutasiSearchInput) {
            mutasiSearchInput.value = student.name;
            mutasiSearchInput.classList.add('bg-light');
        }
        if (mutasiPreviewName) mutasiPreviewName.textContent = student.name;
        if (mutasiPreviewClass) mutasiPreviewClass.textContent = student.classroom_name || '-';
        if (mutasiPreviewNisn) mutasiPreviewNisn.textContent = student.nisn || '-';
        if (mutasiPreviewBox) {
            mutasiPreviewBox.classList.remove('d-none');
            mutasiPreviewBox.classList.add('d-flex');
        }
        if (mutasiDropdown) {
            mutasiDropdown.style.display = 'none';
            mutasiDropdown.innerHTML = '';
        }
    }

    function clearMutasiStudent() {
        if (mutasiHiddenId) mutasiHiddenId.value = '';
        if (mutasiSearchInput) {
            mutasiSearchInput.value = '';
            mutasiSearchInput.classList.remove('bg-light');
            mutasiSearchInput.focus();
        }
        if (mutasiPreviewBox) {
            mutasiPreviewBox.classList.remove('d-flex');
            mutasiPreviewBox.classList.add('d-none');
        }
        if (mutasiDropdown) {
            mutasiDropdown.style.display = 'none';
            mutasiDropdown.innerHTML = '';
        }
    }

    if (mutasiClearBtn) {
        mutasiClearBtn.addEventListener('click', clearMutasiStudent);
    }

    let mutasiSearchTimer = null;
    if (mutasiSearchInput) {
        mutasiSearchInput.addEventListener('input', function() {
            clearTimeout(mutasiSearchTimer);
            const query = this.value.trim();
            if (query.length < 2) {
                if (mutasiDropdown) {
                    mutasiDropdown.style.display = 'none';
                    mutasiDropdown.innerHTML = '';
                }
                if (!query) {
                    if (mutasiHiddenId) mutasiHiddenId.value = '';
                    if (mutasiPreviewBox) {
                        mutasiPreviewBox.classList.remove('d-flex');
                        mutasiPreviewBox.classList.add('d-none');
                    }
                }
                return;
            }

            mutasiSearchTimer = setTimeout(() => {
                fetch(`{{ route('students.search-api') }}?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!mutasiDropdown) return;
                        if (!data || data.length === 0) {
                            mutasiDropdown.innerHTML = `<div class="p-2 text-muted small text-center"><i class="ti ti-info-circle me-1"></i> Tidak ditemukan siswa aktif cocok</div>`;
                            mutasiDropdown.style.display = 'block';
                            return;
                        }

                        let html = '';
                        data.forEach(s => {
                            html += `
                                <a href="javascript:void(0)" class="dropdown-item py-2 border-bottom mutasi-dropdown-option"
                                   data-id="${s.id}"
                                   data-name="${s.name}"
                                   data-classroom="${s.classroom_name || '-'}"
                                   data-nisn="${s.nisn || '-'}">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <div>
                                            <div class="fw-bold text-dark">${s.name}</div>
                                            <div class="text-muted small">
                                                <span class="badge bg-azure-lt me-1">${s.classroom_name || 'Tanpa Kelas'}</span>
                                                NISN: <span class="font-monospace">${s.nisn || '-'}</span>
                                            </div>
                                        </div>
                                        <i class="ti ti-chevron-right text-muted small"></i>
                                    </div>
                                </a>
                            `;
                        });

                        mutasiDropdown.innerHTML = html;
                        mutasiDropdown.style.display = 'block';

                        mutasiDropdown.querySelectorAll('.mutasi-dropdown-option').forEach(item => {
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                selectMutasiStudent({
                                    id: this.dataset.id,
                                    name: this.dataset.name,
                                    classroom_name: this.dataset.classroom,
                                    nisn: this.dataset.nisn
                                });
                            });
                        });
                    })
                    .catch(err => {
                        console.error('Search student failed:', err);
                    });
            }, 250);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (mutasiSearchInput && !mutasiSearchInput.contains(e.target) && mutasiDropdown && !mutasiDropdown.contains(e.target)) {
                mutasiDropdown.style.display = 'none';
            }
        });
    }

    // Handle Trigger Mutasi dari baris siswa aktif
    document.querySelectorAll('.btn-trigger-mutasi').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const studentId = this.dataset.studentId;
            const studentName = this.dataset.studentName;
            const classroomName = this.dataset.classroomName || '-';
            const studentNisn = this.dataset.nisn || '-';

            selectMutasiStudent({
                id: studentId,
                name: studentName,
                classroom_name: classroomName,
                nisn: studentNisn
            });

            const modal = new bootstrap.Modal(document.getElementById('modal-mutasi-siswa'));
            modal.show();
        });
    });

    // 4. Handle Edit Siswa Modal
    document.querySelectorAll('.btn-edit-siswa').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const student = JSON.parse(this.dataset.student);
            const form = document.getElementById('form-edit-siswa');
            form.action = `/siswa/${student.id}`;

            document.getElementById('edit_name').value = student.name || '';
            document.getElementById('edit_classroom_id').value = student.classroom_id || '';
            document.getElementById('edit_nisn').value = student.nisn || '';
            document.getElementById('edit_nis').value = student.nis || '';
            document.getElementById('edit_nik').value = student.nik || '';
            document.getElementById('edit_gender').value = student.gender || 'L';
            document.getElementById('edit_birth_place').value = student.birth_place || '';

            if (student.birth_date) {
                const datePart = student.birth_date.split('T')[0];
                document.getElementById('edit_birth_date').value = datePart;
            } else {
                document.getElementById('edit_birth_date').value = '';
            }

            document.getElementById('edit_religion').value = student.religion || '';
            document.getElementById('edit_phone_student').value = student.phone_student || '';
            document.getElementById('edit_address').value = student.address || '';

            document.getElementById('edit_father_name').value = student.father_name || '';
            document.getElementById('edit_father_job').value = student.father_job || '';
            document.getElementById('edit_father_phone').value = student.father_phone || '';

            document.getElementById('edit_mother_name').value = student.mother_name || '';
            document.getElementById('edit_mother_job').value = student.mother_job || '';
            document.getElementById('edit_mother_phone').value = student.mother_phone || '';

            document.getElementById('edit_guardian_name').value = student.guardian_name || '';
            document.getElementById('edit_guardian_job').value = student.guardian_job || '';
            document.getElementById('edit_guardian_phone').value = student.guardian_phone || '';

            document.getElementById('edit_phone_parent').value = student.phone_parent || '';
            document.getElementById('edit_status').value = student.status || 'aktif';
            document.getElementById('edit_graduation_year').value = student.graduation_year || '';
            document.getElementById('edit_last_classroom_name').value = student.last_classroom_name || '';
            document.getElementById('edit_entry_year').value = student.entry_year || '';
            document.getElementById('edit_notes').value = student.notes || '';

            const modal = new bootstrap.Modal(document.getElementById('modal-edit-siswa'));
            modal.show();
        });
    });
});
</script>
@endpush
