@extends('layouts.app')

@section('title', 'Jadwal Pelajaran')

@section('content')
<div class="row g-3 mb-4 align-items-center justify-content-between">
    <div class="col-md-6">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-calendar-time me-2"></i> Jadwal Pelajaran
        </h2>
        <div class="text-muted small">
            Manajemen jadwal belajar mengajar mingguan (Jadwal Kelas & Jadwal Guru) Tahun Pelajaran {{ $academicYear }}.
        </div>
    </div>
    <div class="col-md-6 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
        <button type="button" class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPrintSchedule">
            <i class="ti ti-printer me-1"></i> Cetak Jadwal
        </button>
        @if($canViewAll)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalManualInput">
            <i class="ti ti-plus me-1"></i> Tambah Jadwal Manual
        </button>
        @endif
    </div>
</div>

<!-- Mode Tampilan Switch & Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="row g-3 align-items-center justify-content-between">
            <!-- View Mode Switch -->
            <div class="col-lg-5">
                <div class="btn-group w-100" role="group">
                    <a href="{{ route('kurikulum.schedules.index', ['view' => 'class', 'classroom_id' => $selectedClassroom?->id, 'academic_year' => $academicYear]) }}" 
                       class="btn {{ $viewType === 'class' ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' }}">
                        <i class="ti ti-school me-1"></i> Jadwal Kelas / Rombel
                    </a>
                    <a href="{{ route('kurikulum.schedules.index', ['view' => 'teacher', 'teacher_id' => $selectedTeacher?->id, 'academic_year' => $academicYear]) }}" 
                       class="btn {{ $viewType === 'teacher' ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' }}">
                        <i class="ti ti-user-check me-1"></i> Jadwal Mengajar Guru
                    </a>
                </div>
            </div>

            <!-- Selector (Class or Teacher) -->
            <div class="col-lg-5">
                <form method="GET" action="{{ route('kurikulum.schedules.index') }}" id="filterForm" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    <input type="hidden" name="academic_year" value="{{ $academicYear }}">

                    @if($viewType === 'class')
                        <label class="form-label mb-0 fw-bold text-nowrap text-muted"><i class="ti ti-layout-grid me-1"></i> Pilih Kelas:</label>
                        <select name="classroom_id" class="form-select fw-bold border-primary shadow-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— {{ $isTeacherRestricted ? 'Semua Kelas Relevan ('.count($classrooms).')' : 'Semua Kelas' }} —</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}" {{ $selectedClassroom?->id == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->department?->name ?? 'Umum' }})
                                </option>
                            @endforeach
                        </select>
                    @else
                        <label class="form-label mb-0 fw-bold text-nowrap text-muted"><i class="ti ti-users me-1"></i> {{ $isTeacherRestricted ? 'Guru:' : 'Pilih Guru:' }}</label>
                        <select name="teacher_id" class="form-select fw-bold border-primary shadow-sm" onchange="document.getElementById('filterForm').submit()" {{ $isTeacherRestricted ? 'disabled' : '' }}>
                            @if($isTeacherRestricted)
                                <option value="{{ auth()->id() }}" selected>{{ auth()->user()->name }} (Anda)</option>
                            @else
                                <option value="">— Semua Guru (Hanya Yang Memiliki Jam) —</option>
                                @if($selectedTeacher && !$teachers->contains('id', $selectedTeacher->id))
                                    <option value="{{ $selectedTeacher->id }}" selected>
                                        {{ $selectedTeacher->name }}
                                    </option>
                                @endif
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}" {{ $selectedTeacher?->id == $t->id ? 'selected' : '' }}>
                                        {{ $t->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    @endif
                </form>
            </div>

            <!-- Active Status Badge -->
            <div class="col-lg-2 text-lg-end">
                <span class="badge bg-blue-lt border border-primary px-2 py-1">
                    <i class="ti ti-calendar me-1"></i> Tahun Pelajaran {{ $academicYear }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Active Timetable Banner (if uploaded from PDF) -->
@if($activeUpload)
<div class="alert alert-info d-flex align-items-center justify-content-between p-2 mb-3 shadow-sm border-0" style="background-color: #f0fdfa; border-left: 4px solid #0d9488 !important;">
    <div class="d-flex align-items-center">
        <i class="ti ti-file-check fs-2 text-teal me-2" style="color: #0d9488;"></i>
        <div>
            <strong class="text-teal" style="color: #0f766e;">Sumber Data: {{ $activeUpload->title }}</strong>
            <span class="text-muted ms-2 small">({{ $activeUpload->total_classes }} Rombel, {{ $activeUpload->total_lessons }} Slot Pelajaran &bull; Diupload {{ $activeUpload->created_at->format('d M Y') }})</span>
        </div>
    </div>
    @if(auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']))
    <div class="d-flex gap-2">
        <a href="{{ route('kurikulum.timetables.translation', $activeUpload) }}" class="btn btn-sm btn-teal text-white shadow-sm" style="background-color: #0d9488;">
            <i class="ti ti-table-options me-1"></i> Buka Tabel Terjemahan & Koreksi
        </a>
        <a href="{{ route('kurikulum.timetables.download', $activeUpload) }}" class="btn btn-sm btn-outline-teal" style="border-color: #0d9488; color: #0d9488;">
            <i class="ti ti-download me-1"></i> Download PDF Asli
        </a>
    </div>
    @endif
</div>
@endif

<!-- Timetable Displays -->
@if(($viewType === 'class' && !$selectedClassroom) || ($viewType === 'teacher' && !$selectedTeacher))
    {{-- ===== TAMPILAN SEMUA (STACKED CARDS KE BAWAH) ===== --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="fw-bold text-dark m-0">
                <i class="ti {{ $viewType === 'class' ? 'ti-school' : 'ti-users' }} me-2 text-primary"></i>
                @if($viewType === 'class')
                    {{ $isTeacherRestricted ? 'Jadwal Kelas Yang Relevan (' . count($classrooms) . ' Kelas)' : 'Jadwal Pelajaran Seluruh Kelas (' . count($classrooms) . ' Kelas)' }}
                @else
                    {{ $isTeacherRestricted ? 'Jadwal Mengajar Anda: ' . auth()->user()->name : 'Jadwal Mengajar Guru (' . count($teachers) . ' Guru Memiliki Jam)' }}
                @endif
            </h4>
            <div class="text-muted small">
                @if($viewType === 'class')
                    {{ $isTeacherRestricted ? 'Hanya menampilkan kelas tempat Anda mengajar atau perwalian kelas pada TP ' . $academicYear . '.' : 'Menampilkan grid jadwal per kelas secara berurutan ke bawah.' }}
                @else
                    {{ $isTeacherRestricted ? 'Menampilkan seluruh jadwal jam mengajar Anda pada Tahun Pelajaran ' . $academicYear . '.' : 'Hanya menampilkan guru yang memiliki jam mengajar pada Tahun Pelajaran ' . $academicYear . '.' }}
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="{{ route('kurikulum.schedules.print', ['view' => $viewType, 'target' => 'all', 'academic_year' => $academicYear]) }}" target="_blank" class="btn btn-sm btn-primary shadow-sm">
                <i class="ti ti-printer me-1"></i> Cetak Semua {{ $viewType === 'class' ? ($isTeacherRestricted ? 'Kelas Relevan (' . count($classrooms) . ' Lembar)' : 'Kelas (' . count($classrooms) . ' Lembar)') : ($isTeacherRestricted ? 'Jadwal Saya' : 'Guru (' . count($teachers) . ' Lembar)') }}
            </a>
            <span class="badge bg-blue text-white"><span class="badge-dot bg-white me-1"></span> Umum</span>
            <span class="badge bg-orange text-white"><span class="badge-dot bg-white me-1"></span> Kejuruan</span>
            <span class="badge bg-teal text-white"><span class="badge-dot bg-white me-1"></span> Mulok</span>
            <span class="badge bg-green text-white"><span class="badge-dot bg-white me-1"></span> BK</span>
        </div>
    </div>

    @if($viewType === 'class')
        @if($classrooms->isEmpty())
            <div class="card border-0 shadow-sm p-5 text-center text-muted mb-4">
                <i class="ti ti-calendar-off fs-1 mb-2 text-secondary"></i>
                <h5 class="fw-bold">Belum Ada Jadwal Kelas Yang Relevan</h5>
                <p class="small mb-0">Belum ada rombel kelas yang terhubung dengan penugasan jam mengajar atau perwalian Anda pada Tahun Pelajaran {{ $academicYear }}.</p>
            </div>
        @else
            @foreach($classrooms as $c)
                @php
                    $gridData = $allGrids[$c->id] ?? null;
                    $grid = $gridData['grid'] ?? [];
                    $totalSlot = $gridData['total'] ?? 0;
                @endphp
                <div class="card border-0 shadow-sm mb-4" id="class-{{ $c->id }}">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary text-white fs-3 px-3 py-2">
                                <i class="ti ti-school me-1"></i> {{ $c->name }}
                            </span>
                            <span class="badge bg-blue-lt">
                                {{ $c->department?->name ?? 'Umum' }}
                            </span>
                            @if($c->homeroomTeacher)
                                <span class="badge bg-light text-dark border">
                                    <i class="ti ti-user me-1 text-primary"></i> Wali: {{ $c->homeroomTeacher->name }}
                                </span>
                            @endif
                            <span class="badge {{ $totalSlot > 0 ? 'bg-success-lt' : 'bg-danger-lt' }}">
                                {{ $totalSlot }} Slot Terisi
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('kurikulum.schedules.print', ['view' => 'class', 'target' => 'specific', 'classroom_id' => $c->id, 'academic_year' => $academicYear]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Lembar Jadwal Kelas Ini">
                                <i class="ti ti-printer me-1"></i> Cetak
                            </a>
                            <a href="{{ route('kurikulum.schedules.index', ['view' => 'class', 'classroom_id' => $c->id, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-focus-2 me-1"></i> Fokus Kelas Ini
                            </a>
                        </div>
                    </div>
                    @include('kurikulum.schedules._grid', [
                        'grid' => $grid,
                        'periods' => $periods,
                        'days' => $days,
                        'viewType' => 'class',
                    ])
                </div>
            @endforeach
        @endif
    @else
        @forelse($teachers as $t)
            @php
                $gridData = $allGrids[$t->id] ?? null;
                $grid = $gridData['grid'] ?? [];
                $totalHours = $gridData['total'] ?? 0;
            @endphp
            <div class="card border-0 shadow-sm mb-4" id="teacher-{{ $t->id }}">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-primary text-white fs-3 px-3 py-2">
                            <i class="ti ti-user me-1"></i> {{ $t->name }}
                        </span>
                        <span class="badge {{ $totalHours > 0 ? 'bg-success-lt' : 'bg-secondary-lt' }}">
                            <i class="ti ti-clock me-1"></i> {{ $totalHours }} Jam Pelajaran
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('kurikulum.schedules.print', ['view' => 'teacher', 'target' => 'specific', 'teacher_id' => $t->id, 'academic_year' => $academicYear]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Lembar Jadwal Guru Ini">
                            <i class="ti ti-printer me-1"></i> Cetak
                        </a>
                        <a href="{{ route('kurikulum.schedules.index', ['view' => 'teacher', 'teacher_id' => $t->id, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-focus-2 me-1"></i> Fokus Guru Ini
                        </a>
                    </div>
                </div>
                @include('kurikulum.schedules._grid', [
                    'grid' => $grid,
                    'periods' => $periods,
                    'days' => $days,
                    'viewType' => 'teacher',
                ])
            </div>
        @empty
            <div class="card border-0 shadow-sm p-5 text-center text-muted mb-4">
                <i class="ti ti-calendar-off fs-1 mb-2 text-secondary"></i>
                <h5 class="fw-bold">Tidak ada guru dengan jam mengajar</h5>
                <p class="small mb-0">Belum ada guru yang memiliki jam mengajar pada Tahun Pelajaran {{ $academicYear }}.</p>
            </div>
        @endforelse
    @endif

@else
    {{-- ===== TAMPILAN GRID TUNGGAL (SPESIFIK KELAS / GURU) ===== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title fw-bold text-dark m-0">
                    @if($viewType === 'class')
                        <i class="ti ti-school me-2 text-primary"></i> Jadwal Pelajaran Kelas: <span class="text-primary">{{ $selectedClassroom->name }}</span>
                        <span class="badge bg-blue-lt ms-2">{{ $selectedClassroom->department?->name ?? 'Umum' }}</span>
                        @if($selectedClassroom->homeroomTeacher)
                            <span class="badge bg-light text-dark border ms-1">
                                <i class="ti ti-user me-1 text-primary"></i> Wali: {{ $selectedClassroom->homeroomTeacher->name }}
                            </span>
                        @endif
                    @else
                        <i class="ti ti-user me-2 text-primary"></i> Jadwal Mengajar: <span class="text-primary">{{ $selectedTeacher->name }}</span>
                    @endif
                </h4>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <a href="{{ route('kurikulum.schedules.print', ['view' => $viewType, 'target' => 'specific', 'classroom_id' => $selectedClassroom?->id, 'teacher_id' => $selectedTeacher?->id, 'academic_year' => $academicYear]) }}" target="_blank" class="btn btn-sm btn-primary shadow-sm">
                    <i class="ti ti-printer me-1"></i> Cetak Lembar Ini
                </a>
                <span class="badge bg-blue text-white"><span class="badge-dot bg-white me-1"></span> Umum</span>
                <span class="badge bg-orange text-white"><span class="badge-dot bg-white me-1"></span> Kejuruan</span>
                <span class="badge bg-teal text-white"><span class="badge-dot bg-white me-1"></span> Mulok</span>
                <span class="badge bg-green text-white"><span class="badge-dot bg-white me-1"></span> BK</span>
            </div>
        </div>
        @include('kurikulum.schedules._grid', [
            'grid' => $scheduleGrid,
            'periods' => $periods,
            'days' => $days,
            'viewType' => $viewType,
        ])
    </div>
@endif

<!-- Modal Input Manual Jadwal -->
<div class="modal fade" id="modalManualInput" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.schedules.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-calendar-plus me-1 text-primary"></i> Tambah Jadwal Pelajaran Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required fw-bold">Kelas / Rombel</label>
                    <select name="classroom_id" class="form-select" required>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassroom?->id == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->department?->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label required fw-bold">Mata Pelajaran</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}">[{{ $s->code }}] {{ $s->name }} ({{ $s->category }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label required fw-bold">Guru Pengampu</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">-- Pilih Guru --</option>
                        @foreach($allTeachers ?? $teachers as $t)
                            <option value="{{ $t->id }}" {{ $selectedTeacher?->id == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label required fw-bold">Hari</label>
                        <select name="day" class="form-select" required>
                            @foreach($days as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Mulai</label>
                        <select name="start_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam {{ $i }} ({{ $periods[$i]['start'] }})</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Selesai</label>
                        <select name="end_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam {{ $i }} ({{ $periods[$i]['end'] }})</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Upload PDF Jadwal -->
<div class="modal fade" id="modalUploadPdf" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.schedules.upload') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-file-upload me-1 text-teal" style="color: #0d9488;"></i> Upload PDF Jadwal Kelas (aSc Timetables)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex mb-3">
                    <i class="ti ti-info-circle fs-2 me-2"></i>
                    <div class="small">
                        Upload dokumen cetak PDF jadwal kelas dari aplikasi <strong>aSc Timetables</strong>. Sistem akan otomatis mengidentifikasi rombel, mata pelajaran, penugasan guru, dan menyinkronkan seluruh jadwal pelajaran secara instan.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih File PDF</label>
                    <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                    <div class="form-text small">Format file: <code>.pdf</code>, ukuran maksimal 15 MB.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Judul Arsip Jadwal (Opsional)</label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Jadwal Pelajaran TP 2026/2027">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Catatan Perubahan (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Revisi per 09 Juli 2026"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-teal text-white" style="background-color: #0d9488;"><i class="ti ti-cloud-upload me-1"></i> Upload & Sinkronkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Jadwal -->
<div class="modal fade" id="modalEditSlot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditSlot" action="" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-edit me-1 text-primary"></i> Edit Slot Jadwal Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required fw-bold">Mata Pelajaran</label>
                    <select name="subject_id" id="edit_subject_id" class="form-select" required>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}">[{{ $s->code }}] {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label required fw-bold">Guru Pengampu</label>
                    <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                        @foreach($allTeachers ?? $teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label required fw-bold">Hari</label>
                        <select name="day" id="edit_day" class="form-select" required>
                            @foreach($days as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Mulai</label>
                        <select name="start_period" id="edit_start_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Selesai</label>
                        <select name="end_period" id="edit_end_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Perbarui</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cetak Jadwal -->
<div class="modal fade" id="modalPrintSchedule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.schedules.print') }}" method="GET" target="_blank" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="ti ti-printer me-2"></i> Cetak Jadwal Pelajaran (A4 Landscape)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. Pilihan Jenis Jadwal -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark">1. Jenis Jadwal yang Ingin Dicetak</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="view" id="print_view_class" value="class" {{ $viewType === 'class' ? 'checked' : '' }} onchange="togglePrintScope()">
                            <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="print_view_class">
                                <i class="ti ti-school fs-2 mb-1"></i>
                                <span class="fw-bold">Jadwal Kelas</span>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="view" id="print_view_teacher" value="teacher" {{ $viewType === 'teacher' ? 'checked' : '' }} onchange="togglePrintScope()">
                            <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="print_view_teacher">
                                <i class="ti ti-user-check fs-2 mb-1"></i>
                                <span class="fw-bold">Jadwal Guru</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Pilihan Target (Semua vs Tertentu) -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark">2. Pilihan Lembar Cetak</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="target" id="print_target_all" value="all" checked onchange="togglePrintScope()">
                            <label class="form-check-label fw-semibold" for="print_target_all">
                                <strong id="label_print_all">Cetak Semua Kelas ({{ count($classrooms) }} Rombel)</strong>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="target" id="print_target_specific" value="specific" onchange="togglePrintScope()">
                            <label class="form-check-label fw-semibold" for="print_target_specific">
                                Pilih Tertentu
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. Dropdown Pilih Tertentu (Kelas) -->
                <div class="mb-3" id="group_select_class" style="display: none;">
                    <label class="form-label fw-bold">Pilih Kelas / Rombel</label>
                    <select name="classroom_id" class="form-select">
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassroom?->id == $c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ $c->department?->name ?? 'Umum' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 4. Dropdown Pilih Tertentu (Guru) -->
                <div class="mb-3" id="group_select_teacher" style="display: none;">
                    <label class="form-label fw-bold">Pilih Guru Pengampu (Hanya Yang Memiliki Jam)</label>
                    <select name="teacher_id" class="form-select">
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ $selectedTeacher?->id == $t->id ? 'selected' : '' }}>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 5. Periode Tahun Pelajaran -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Tahun Pelajaran</label>
                    <input type="text" name="academic_year" class="form-control bg-light" value="{{ $academicYear }}" readonly>
                </div>

                <!-- 6. Tanggal & Kota Penetapan Dokumen -->
                <div class="row g-2 mb-2">
                    <div class="col-md-7">
                        <label class="form-label fw-bold">
                            <i class="ti ti-calendar-event me-1 text-primary"></i> Tanggal Penetapan / Tanda Tangan
                        </label>
                        <input type="date" name="penetapan_date" class="form-control border-primary" value="{{ now()->format('Y-m-d') }}">
                        <div class="form-text small text-muted">Sesuaikan jika jadwal disahkan pada tanggal tertentu.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Kota Penetapan</label>
                        <input type="text" name="penetapan_city" class="form-control" value="Kediri">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-printer me-1"></i> Buka Lembar Cetak
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditModal(scheduleId, teacherId, subjectId, day, startPeriod, endPeriod) {
    const form = document.getElementById('formEditSlot');
    form.action = `/kurikulum/jadwal-pelajaran/${scheduleId}`;
    document.getElementById('edit_teacher_id').value = teacherId;
    document.getElementById('edit_subject_id').value = subjectId;
    document.getElementById('edit_day').value = day;
    document.getElementById('edit_start_period').value = startPeriod;
    document.getElementById('edit_end_period').value = endPeriod;

    new bootstrap.Modal(document.getElementById('modalEditSlot')).show();
}

function togglePrintScope() {
    const isClass = document.getElementById('print_view_class').checked;
    const isSpecific = document.getElementById('print_target_specific').checked;

    @if($isTeacherRestricted)
    document.getElementById('label_print_all').innerText = isClass
        ? 'Cetak Semua Kelas Relevan ({{ count($classrooms) }} Rombel)'
        : 'Cetak Jadwal Saya ({{ auth()->user()->name }})';
    @else
    document.getElementById('label_print_all').innerText = isClass
        ? 'Cetak Semua Kelas ({{ count($classrooms) }} Rombel)'
        : 'Cetak Semua Guru ({{ count($teachers) }} Guru Memiliki Jam)';
    @endif

    document.getElementById('group_select_class').style.display = (isClass && isSpecific) ? 'block' : 'none';
    document.getElementById('group_select_teacher').style.display = (!isClass && isSpecific) ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    togglePrintScope();
});
</script>
@endpush
