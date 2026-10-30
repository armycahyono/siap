@extends('layouts.app')

@section('title', 'Penugasan GTK - Guru & Tenaga Kependidikan')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Penugasan GTK (Guru & Tenaga Kependidikan)',
    'subtitle' => 'Kelola penugasan GTK terpadu: Pengampu Mata Pelajaran, Wali Kelas & BK, Jabatan Struktural, serta Tugas Operasional Sekolah.',
    'icon' => 'ti ti-briefcase',
    'category' => 'DATA POKOK & SDM',
    'academicYear' => $academicYear,
])

@include('master.nav')

<!-- Sub-Tab Navigasi Penugasan GTK -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom-0 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'mapel' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-notebook me-2 text-primary"></i>
                            1. Mata Pelajaran
                            <span class="badge bg-primary-lt ms-2">{{ $assignments->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'walikelas' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-user-star me-2 text-success"></i>
                            2. Wali Kelas &amp; BK
                            <span class="badge bg-success-lt ms-2">{{ $classrooms->whereNotNull('homeroom_teacher_id')->count() }}/{{ $classrooms->count() }} Kelas &bull; {{ $bkAssignments->count() }} BK</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'struktural' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-briefcase me-2 text-indigo"></i>
                            3. Struktural
                            <span class="badge bg-indigo-lt ms-2">{{ $structuralAssignments->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'operasional', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'operasional' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-settings-cog me-2 text-azure"></i>
                            4. Operasional
                            <span class="badge bg-azure-lt ms-2">{{ $operationalAssignments->total() }}</span>
                        </a>
                    </li>
                    @if($tab === 'supervisor')
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear]) }}" 
                           class="nav-link active fw-bold border-bottom-primary">
                            <i class="ti ti-shield-check me-2 text-warning"></i>
                            Tim Supervisor Sekolah
                            <span class="badge bg-warning-lt ms-2">{{ $supervisors->count() }}</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

@if($tab === 'mapel')
<!-- ======================= TAB 1: MAPEL DIAMPU ======================= -->

<!-- Card Rekapitulasi Beban Mengajar Guru -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-layout-dashboard me-2 text-blue"></i>
                Rekap Beban Mengajar Guru
            </h4>
            <div class="text-muted small mt-1">Daftar seluruh guru yang memiliki penugasan jam mengajar pada Tahun Pelajaran {{ $academicYear }}</div>
        </div>
        <div class="card-actions">
            <span class="badge bg-blue-lt fs-4 px-2 py-1">{{ $teacherRecap->count() }} Guru Mengajar</span>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            @forelse($teacherRecap as $tr)
            @php
                $uniqueSubjects = $tr->subjectAssignments->pluck('subject.name')->filter()->unique();
                $uniqueClasses = $tr->subjectAssignments->pluck('classroom.name')->filter()->unique();
                $totalHours = $tr->subjectAssignments->sum('hours_per_week');
                $homeroom = $tr->homeroomClassrooms->pluck('name')->implode(', ');
            @endphp
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="card card-stacked h-100 border shadow-none bg-light-subtle">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <!-- Header Guru -->
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm bg-primary text-white rounded-circle me-2 fw-bold shadow-sm">
                                        {{ strtoupper(substr($tr->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 160px;" title="{{ $tr->name }}">{{ $tr->name }}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">NIGS: {{ $tr->nigs ?: '-' }}</div>
                                    </div>
                                </div>
                                <span class="badge bg-primary-lt fw-bold fs-5 text-nowrap">
                                    {{ $totalHours }} JP
                                </span>
                            </div>

                            @if($homeroom)
                            <div class="mb-2">
                                <span class="badge bg-success-lt text-truncate" style="max-width: 100%; font-size: 0.7rem;" title="Wali Kelas {{ $homeroom }}">
                                    <i class="ti ti-user-star me-1"></i> Wali Kelas: {{ $homeroom }}
                                </span>
                            </div>
                            @endif

                            <!-- Mapel yang Diampu -->
                            <div class="mb-2">
                                <div class="text-muted small fw-semibold mb-1" style="font-size: 0.72rem;">
                                    <i class="ti ti-book me-1 text-primary"></i> Mapel Diampu:
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($uniqueSubjects as $sn)
                                        <span class="badge bg-blue-lt text-truncate" style="max-width: 220px; font-size: 0.7rem;" title="{{ $sn }}">
                                            {{ $sn }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Kelas yang Diajar -->
                            <div class="mb-2">
                                <div class="text-muted small fw-semibold mb-1" style="font-size: 0.72rem;">
                                    <i class="ti ti-layout-grid me-1 text-teal"></i> Kelas yang Diajar:
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($uniqueClasses as $cn)
                                        <span class="badge bg-teal-lt" style="font-size: 0.7rem;">
                                            {{ $cn }}
                                        </span>
                                    @empty
                                        <span class="text-muted fst-italic" style="font-size: 0.72rem;">(Umum / Tanpa Rombel)</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- Tombol View Detail Penugasan -->
                        <div class="pt-2 border-top mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 shadow-none" data-bs-toggle="modal" data-bs-target="#modalDetailTeacher_{{ $tr->id }}">
                                <i class="ti ti-eye me-1"></i> Lihat Detail Penugasan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-4 text-muted">
                <i class="ti ti-users-off fs-1 d-block mb-1 text-secondary opacity-50"></i>
                Belum ada data penugasan mapel pada tahun pelajaran ini.
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('teacher-assignments.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="mapel">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <div class="col-md-3">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari guru, mapel, atau rombel..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="teacher_id" class="form-select">
                    <option value="">-- Semua Guru --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" {{ $teacherId == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->nigs ?? '-' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="subject_id" class="form-select">
                    <option value="">-- Semua Mapel --</option>
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}" {{ $subjectId == $s->id ? 'selected' : '' }}>{{ $s->code }} - {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="classroom_id" class="form-select">
                    <option value="">-- Semua Kelas --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-light w-100">Filter</button>
                @if($search || $teacherId || $subjectId || $classroomId)
                    <a href="{{ route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $academicYear]) }}" class="btn btn-icon btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table Penugasan Mapel -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold text-dark m-0">
            <i class="ti ti-table me-2 text-primary"></i> Daftar Penugasan Guru & Mata Pelajaran
        </h4>
        <div class="card-actions">
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateAssignment">
                <i class="ti ti-plus me-1"></i> Tambah Penugasan Mapel
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Nama Guru</th>
                    <th>NIGS / Status</th>
                    <th>Mata Pelajaran yang Diampu</th>
                    <th>Kelas / Rombel</th>
                    <th class="text-center">Jam / Minggu (JP)</th>
                    <th>Semester</th>
                    <th>Catatan / Keterangan</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $assign)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($assign->teacher?->name ?? 'G', 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $assign->teacher?->name ?? '-' }}</div>
                                <small class="text-muted">{{ $assign->teacher?->email ?? '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $assign->teacher?->nigs ?? '-' }}</div>
                        <small class="badge bg-secondary-lt">{{ $assign->teacher?->gtk_status ?? 'GTY' }}</small>
                    </td>
                    <td>
                        <div>
                            <span class="badge bg-purple-lt fw-bold me-1">{{ $assign->subject?->code ?? '-' }}</span>
                            <span class="fw-semibold text-dark">{{ $assign->subject?->name ?? '-' }}</span>
                        </div>
                        <small class="text-muted">{{ $assign->subject?->category ?? 'Umum' }}</small>
                    </td>
                    <td>
                        @if($assign->classroom)
                            <span class="badge bg-blue-lt fw-bold fs-4">{{ $assign->classroom->name }}</span>
                            <div class="small text-muted">{{ $assign->classroom->department?->code ?? '' }}</div>
                        @else
                            <span class="text-muted small fst-italic">Semua Kelas Terkait</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success-lt fw-bold fs-4">{{ $assign->hours_per_week }} JP</span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">{{ $assign->semester }}</span>
                    </td>
                    <td>
                        <small class="text-muted">{{ $assign->notes ?: '-' }}</small>
                    </td>
                    <td class="text-center">
                        <form action="{{ route('teacher-assignments.destroy-subject', $assign) }}" method="POST" onsubmit="return confirm('Hapus penugasan mapel ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Penugasan">
                                <i class="ti ti-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-notebook-off fs-1 mb-2 d-block"></i>
                        Tidak ada data penugasan mata pelajaran yang sesuai.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($assignments->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $assignments->links() }}
    </div>
    @endif
</div>

<!-- Modal Tambah Penugasan Mapel -->
<div class="modal fade" id="modalCreateAssignment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('teacher-assignments.store-subject') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Penugasan Mapel Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Anda dapat menugaskan guru yang sama untuk <strong>beberapa mata pelajaran</strong> yang berbeda.
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Pilih Guru</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} (NIGS: {{ $t->nigs ?? '-' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Pilih Mata Pelajaran</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} - {{ $s->name }} ({{ $s->category }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kelas / Rombel Target</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">-- Umum / Tanpa Rombel Tertentu --</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->department?->code ?? 'Umum' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Jam Pelajaran / Minggu (JP)</label>
                            <input type="number" name="hours_per_week" class="form-control" value="2" min="1" max="40" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Semester</label>
                            <select name="semester" class="form-select" required>
                                <option value="Semua">Semua Semester</option>
                                <option value="Ganjil">Semester Ganjil</option>
                                <option value="Genap">Semester Genap</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tahun Pelajaran</label>
                        <input type="text" name="academic_year" class="form-control" value="{{ $academicYear }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Contoh: Pengampu teori & jobsheet bengkel">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Penugasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals Detail Penugasan Mengajar Per Guru -->
@foreach($teacherRecap as $tr)
<div class="modal fade" id="modalDetailTeacher_{{ $tr->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light-subtle">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-md bg-primary text-white rounded-circle me-3 fw-bold fs-3 shadow-sm">
                        {{ strtoupper(substr($tr->name, 0, 1)) }}
                    </span>
                    <div>
                        <h4 class="modal-title fw-bold text-dark mb-0">{{ $tr->name }}</h4>
                        <div class="text-muted small">
                            NIGS: {{ $tr->nigs ?: '-' }}
                            @if($tr->nip) | NIP: {{ $tr->nip }} @endif
                            | Status: <span class="badge bg-success-lt">Aktif Mengajar</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Info Summary -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <div class="p-2 border rounded bg-light text-center h-100">
                            <div class="text-muted small">Total Beban Mengajar</div>
                            <div class="fs-2 fw-bold text-primary">{{ $tr->subjectAssignments->sum('hours_per_week') }} JP / Minggu</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-2 border rounded bg-light text-center h-100">
                            <div class="text-muted small">Jumlah Rombel / Kelas</div>
                            <div class="fs-2 fw-bold text-teal">{{ $tr->subjectAssignments->pluck('classroom_id')->filter()->unique()->count() }} Rombel</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-2 border rounded bg-light text-center h-100">
                            <div class="text-muted small">Tugas Tambahan Wali Kelas</div>
                            <div class="fs-3 fw-bold text-dark text-truncate" title="{{ $tr->homeroomClassrooms->pluck('name')->implode(', ') }}">
                                {{ $tr->homeroomClassrooms->pluck('name')->implode(', ') ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-2">
                    <i class="ti ti-list-details me-1 text-primary"></i> Rincian Penugasan Mapel & Kelas (TP. {{ $academicYear }})
                </h5>
                <div class="table-responsive border rounded mb-2">
                    <table class="table table-sm table-striped table-hover m-0">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th style="width: 40px;" class="text-center">No</th>
                                <th>Mata Pelajaran</th>
                                <th>Kode Mapel</th>
                                <th>Kelas / Rombel</th>
                                <th class="text-center">Beban (JP)</th>
                                <th class="text-center">Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tr->subjectAssignments as $idx => $sa)
                            <tr>
                                <td class="text-center text-muted fw-bold">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $sa->subject?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $sa->subject?->category ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-blue-lt font-monospace">{{ $sa->subject?->code ?? '-' }}</span>
                                </td>
                                <td>
                                    @if($sa->classroom)
                                        <span class="badge bg-teal-lt fs-5">{{ $sa->classroom->name }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Semua / Umum</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-primary">
                                    {{ $sa->hours_per_week }} JP
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-lt">{{ $sa->semester ?? 'Semua' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">Belum ada rincian penugasan mapel.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">Total Jam Mengajar:</td>
                                <td class="text-center text-primary fs-3">{{ $tr->subjectAssignments->sum('hours_per_week') }} JP</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $academicYear, 'teacher_id' => $tr->id]) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-filter me-1"></i> Filter Tabel Bawah Untuk Guru Ini
                </a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach

@elseif($tab === 'walikelas')
<!-- ======================= TAB 2: WALI KELAS ======================= -->

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h4 class="card-title fw-bold text-dark m-0">
            <i class="ti ti-user-star me-2 text-success"></i>
            Daftar Penetapan Wali Kelas (TP. {{ $academicYear }})
        </h4>
        <div class="card-actions d-flex align-items-center gap-2">
            <a href="{{ route('teacher-assignments.template-homeroom', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-download me-1"></i> Format Template
            </a>
            <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportHomeroom">
                <i class="ti ti-file-spreadsheet me-1"></i> Import Excel
            </button>
            <span class="badge bg-success-lt fw-bold">
                {{ $classrooms->whereNotNull('homeroom_teacher_id')->count() }} dari {{ $classrooms->count() }} Kelas Sudah Ada Wali Kelas
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 160px;">Nama Rombel</th>
                    <th>Wali Kelas Saat Ini</th>
                    <th>No. Handphone (WA)</th>
                    <th class="text-center" style="width: 120px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classrooms as $cls)
                <tr>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $cls->name }}</div>
                        <small class="text-muted">TP. {{ $cls->academic_year }}</small>
                    </td>
                    <td>
                        @if($cls->homeroomTeacher)
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-success-lt text-success rounded-circle me-2 fw-bold">
                                    {{ strtoupper(substr($cls->homeroomTeacher->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $cls->homeroomTeacher->name }}</div>
                                    <small class="text-muted">NIGS: {{ $cls->homeroomTeacher->nigs ?? '-' }}</small>
                                </div>
                            </div>
                        @else
                            <div class="badge bg-warning-lt text-warning fw-bold py-1 px-2">
                                <i class="ti ti-alert-triangle me-1"></i> Belum Ditetapkan
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($cls->homeroomTeacher && $cls->homeroomTeacher->phone)
                            @php
                                $rawPhone = preg_replace('/[^0-9]/', '', $cls->homeroomTeacher->phone);
                                $waPhone = str_starts_with($rawPhone, '0') ? '62' . substr($rawPhone, 1) : (str_starts_with($rawPhone, '62') ? $rawPhone : '62' . $rawPhone);
                            @endphp
                            <a href="https://wa.me/{{ $waPhone }}" target="_blank" class="text-success text-decoration-none fw-semibold d-inline-flex align-items-center" title="Hubungi via WhatsApp">
                                <i class="ti ti-brand-whatsapp me-1 fs-3"></i>{{ $cls->homeroomTeacher->phone }}
                            </a>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($cls->homeroom_teacher_id)
                            <span class="badge bg-success-lt">Terpenuhi</span>
                        @else
                            <span class="badge bg-warning-lt">Kosong</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalSetWali{{ $cls->id }}">
                            <i class="ti ti-user-edit me-1"></i> Atur Wali Kelas
                        </button>

                        <!-- Modal Atur Wali Kelas -->
                        <div class="modal fade text-start" id="modalSetWali{{ $cls->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('teacher-assignments.update-homeroom', $cls) }}" method="POST">
                                        @csrf
                                        <div class="modal-header">
                                             <h5 class="modal-title fw-bold">Atur Wali Kelas: {{ $cls->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Pilih Guru sebagai Wali Kelas</label>
                                                <select name="homeroom_teacher_id" class="form-select">
                                                    <option value="">-- Kosongkan / Belum Ditetapkan --</option>
                                                    @foreach($teachers as $t)
                                                        <option value="{{ $t->id }}" {{ $cls->homeroom_teacher_id == $t->id ? 'selected' : '' }}>
                                                            {{ $t->name }} (NIGS: {{ $t->nigs ?? '-' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="form-hint small mt-2">
                                                    Guru yang dipilih akan bertanggung jawab sebagai pembimbing kelas dan pencatatan perkembangan siswa di rombel {{ $cls->name }}.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Wali Kelas</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 mb-2 d-block"></i>
                        Belum ada kelas / rombel terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Import Excel Wali Kelas -->
<div class="modal fade" id="modalImportHomeroom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('teacher-assignments.import-homeroom') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="ti ti-file-spreadsheet me-2"></i> Import Data Wali Kelas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-start mb-3">
                    <i class="ti ti-info-circle fs-2 me-2 mt-1"></i>
                    <div class="small">
                        Unggah file Excel (<strong>.xlsx</strong>) atau <strong>.csv</strong> berisi daftar kelas dan nama wali kelas untuk TP. <strong>{{ $academicYear }}</strong>.
                        Sistem otomatis mengenali nama rombel dan nama guru (lengkap beserta gelar atau format baris aSc).
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih File Excel / CSV</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt,.xls" required>
                    <div class="form-text small">Mendukung format: <code>.xlsx</code>, <code>.csv</code> (Maks. 10 MB)</div>
                </div>

                <div class="card bg-light border p-3 mb-2">
                    <div class="fw-bold small text-dark mb-1"><i class="ti ti-bulb me-1 text-warning"></i> Format yang Didukung:</div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li><strong>Tabel Excel Standar:</strong> Kolom 1: <em>Nama Kelas</em> (contoh: <code>X TKR 1</code>), Kolom 2: <em>Nama Guru</em> (contoh: <code>Indra Yustiawan, S.Pd</code>), Kolom 3 (Opsional): <em>NIGS/NIP</em>.</li>
                        <li><strong>Daftar Salinan Dokumen:</strong> Baris seperti <code>Wali Kelas X TKR 1 : Indra Yustiawan</code> otomatis dibersihkan dan dicocokkan.</li>
                    </ul>
                </div>

                <div class="text-end">
                    <a href="{{ route('teacher-assignments.template-homeroom', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-link text-decoration-none">
                        <i class="ti ti-download me-1"></i> Download Format Template Excel (.csv)
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="ti ti-cloud-upload me-1"></i> Upload & Terapkan Wali Kelas
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================= BAGIAN 2: PENUGASAN GURU BK ======================= -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-heart-handshake me-2 text-primary"></i>
                Daftar Penugasan Guru BK (Bimbingan Konseling) — TP. {{ $academicYear }}
            </h4>
            <div class="text-muted small mt-1">
                Guru BK dapat mendampingi satu atau beberapa rombel kelas secara fleksibel dan terisolasi dari wewenang Tim Kedisiplinan.
            </div>
        </div>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAssignBk">
                <i class="ti ti-plus me-1"></i> Tugaskan Guru BK
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th class="w-1 text-center">No</th>
                    <th>Nama Guru BK</th>
                    <th>Rombel / Kelas Asuhan</th>
                    <th class="text-center">Beban Binaan</th>
                    <th>Nomor SK &amp; TMT</th>
                    <th>Catatan</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bkAssignments as $idx => $bk)
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $idx + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($bk->user?->name ?? 'B', 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $bk->user?->name ?? 'Guru BK' }}</div>
                                <small class="text-muted">NIGS: {{ $bk->user?->nigs ?? '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($bk->context_type && $bk->context)
                            <span class="badge bg-teal-lt fs-5"><i class="ti ti-school me-1"></i> {{ $bk->context->name }}</span>
                        @else
                            <span class="badge bg-blue-lt fs-5"><i class="ti ti-world me-1"></i> Seluruh Rombel (Umum)</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($bk->context_type && $bk->context)
                            @php
                                $sCount = $classroomStudentCounts[$bk->context_id] ?? ($bk->context?->students()->where('status', 'aktif')->count() ?? 0);
                            @endphp
                            <span class="badge bg-azure-lt fs-6 fw-bold" title="Jumlah siswa aktif di kelas {{ $bk->context->name }}">
                                <i class="ti ti-users me-1"></i> {{ $sCount }} Siswa
                            </span>
                        @else
                            <span class="badge bg-purple-lt fs-6 fw-bold" title="Membina seluruh siswa sekolah secara global">
                                <i class="ti ti-world me-1"></i> Seluruh Siswa
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="small">
                            @if($bk->sk_number)
                                <div class="fw-semibold text-dark"><i class="ti ti-file-certificate me-1 text-primary"></i>{{ $bk->sk_number }}</div>
                            @endif
                            @if($bk->valid_from)
                                <div class="text-muted"><i class="ti ti-calendar me-1"></i>TMT: {{ $bk->valid_from->format('d/m/Y') }}</div>
                            @endif
                            @if(!$bk->sk_number && !$bk->valid_from)
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <small class="text-muted">{{ $bk->notes ?? '-' }}</small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success-lt">Aktif</span>
                    </td>
                    <td class="text-center">
                        <form action="{{ route('teacher-assignments.destroy-bk', $bk) }}" method="POST" onsubmit="return confirm('Cabut penugasan BK untuk {{ $bk->user?->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cabut Penugasan BK">
                                <i class="ti ti-trash me-1"></i> Cabut
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="ti ti-mood-empty fs-1 mb-2 d-block text-secondary"></i>
                        Belum ada penetapan Guru BK untuk TP. {{ $academicYear }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Penugasan Guru BK -->
<div class="modal fade" id="modalAssignBk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('teacher-assignments.store-bk') }}" method="POST">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-heart-handshake me-1 text-primary"></i> Tugaskan Guru BK (Bimbingan Konseling)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="ti ti-info-circle me-1"></i> Satu Guru BK dapat mendampingi beberapa kelas / rombel sekaligus.
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Guru BK</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru --</option>
                            @foreach($allTeachers as $t)
                                <option value="{{ $t->id }}">
                                    {{ $t->name }} ({{ $t->position ?? $t->role?->name ?? 'GTK' }}){{ $t->nip ? ' • NIP: ' . $t->nip : ($t->nigs ? ' • NIGS: ' . $t->nigs : '') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold mb-1">Pilih Rombel / Kelas Bimbingan</label>
                        <small class="text-muted d-block mb-2">
                            Centang kelas yang akan diasuh.
                        </small>

                        <!-- Checklist Rombel Interaktif -->
                        <div class="border rounded bg-white p-2" style="max-height: 270px; overflow-y: auto;">
                            <div class="row g-2">
                                @foreach($classrooms as $cls)
                                    @php
                                        $assignedBk = $assignedClassroomBkMap[$cls->id] ?? null;
                                        $isTaken = (bool) $assignedBk;
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="card card-sm border {{ $isTaken ? 'bg-light-subtle text-muted' : 'bg-white hover-shadow' }} p-2">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="checkbox"
                                                           name="classroom_ids[]"
                                                           value="{{ $cls->id }}"
                                                           class="form-check-input bk-class-checkbox"
                                                           data-students="{{ $cls->students_count ?? 0 }}"
                                                           {{ $isTaken ? 'disabled' : '' }}>
                                                    <div>
                                                        <div class="fw-bold {{ $isTaken ? 'text-secondary text-decoration-line-through' : 'text-dark' }}">
                                                            {{ $cls->name }}
                                                        </div>
                                                        <div class="text-muted" style="font-size: 11px;">
                                                            {{ $cls->department?->code ?? 'Umum' }} &bull; {{ $cls->students_count ?? 0 }} Siswa
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    @if($isTaken)
                                                        <span class="badge bg-secondary-lt fs-7" title="Kelas ini sudah dibina oleh {{ $assignedBk['user_name'] }}">
                                                            <i class="ti ti-lock me-1"></i> {{ $assignedBk['short_name'] ?? $assignedBk['user_name'] }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-teal-lt fs-7">
                                                            Tersedia
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Indikator Live Beban Binaan (Rasio 1:150) -->
                        <div id="bkWorkloadBox" class="card card-sm bg-light-subtle border mt-2">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold text-dark">
                                        <i class="ti ti-scale me-1 text-primary"></i> Estimasi Beban Binaan:
                                    </span>
                                    <span id="bkWorkloadBadge" class="badge bg-secondary-lt">0 Siswa (0 Rombel)</span>
                                </div>
                                <div class="progress progress-sm mb-1" style="height: 6px;">
                                    <div id="bkWorkloadProgress" class="progress-bar bg-primary" style="width: 0%"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 11px;">
                                    <span>Target Standar: 150 Siswa (1:150)</span>
                                    <span id="bkWorkloadStatus" class="fw-semibold text-muted">Seluruh Rombel (Umum)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK Penugasan</label>
                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 421.5/012/BK/2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                            <input type="date" name="sk_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan / Uraian Tugas (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Guru BK Tingkat X dan Konselor Karir"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Simpan Penugasan BK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@elseif($tab === 'supervisor')
<!-- ======================= TAB 3: TIM SUPERVISOR ======================= -->

<!-- Info Card -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <div class="row align-items-center">
            <div class="col">
                <h4 class="fw-bold text-dark mb-1">
                    <i class="ti ti-shield-check me-2 text-warning"></i> Tim Supervisor Akademik (TP. {{ $academicYear }})
                </h4>
                <div class="text-muted small">
                    Daftar pendidik yang diberikan tugas dan wewenang untuk menilai pelaksanaan pembelajaran guru (praobservasi, observasi kelas, dan evaluasi hasil supervisi).
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <a href="{{ route('teacher-assignments.template-supervisor', ['academic_year' => $academicYear]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Format Template
                </a>
                <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportSupervisor">
                    <i class="ti ti-file-spreadsheet me-1"></i> Import Excel
                </button>
                <button type="button" class="btn btn-warning text-dark fw-bold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAssignSupervisor" style="background-color: #f59e0b; border-color: #f59e0b; color: #fff !important;">
                    <i class="ti ti-user-plus me-1"></i> + Tugaskan Guru
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th class="w-1">No</th>
                    <th>Nama Supervisor</th>
                    <th>Mata Pelajaran Pokok</th>
                    <th>Peran / Status</th>
                    <th>Jabatan / Keterangan</th>
                    <th class="text-center">Beban Supervisi</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisors as $index => $spv)
                <tr>
                    <td class="text-muted">{{ $index + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-warning-lt text-warning rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($spv->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $spv->name }}</div>
                                <small class="text-muted">NIGS: {{ $spv->nigs ?: '-' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $spv->subject ?: 'Umum / Adaptif' }}</span>
                        <div class="small text-muted">{{ $spv->expertise_program ?: '-' }}</div>
                    </td>
                    <td>
                        @if($spv->hasRole('waka_kurikulum'))
                            <span class="badge bg-primary text-white fw-bold">
                                <i class="ti ti-crown me-1"></i> Waka Kurikulum (Koordinator)
                            </span>
                        @else
                            <span class="badge bg-warning text-dark fw-bold">
                                <i class="ti ti-shield-check me-1"></i> Supervisor Aktif
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="text-dark small">{{ $spv->supervisorAssignments->first()?->position_title ?: ($spv->position ?: 'Guru / Supervisor') }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-blue-lt fs-3 fw-bold">{{ $spv->supervisions_as_supervisor_count }} Jadwal</span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <a href="{{ route('schedules.index', ['search' => $spv->name, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-primary" title="Lihat Jadwal Supervisi">
                                <i class="ti ti-calendar me-1"></i> Jadwal
                            </a>
                            @if(!$spv->hasRole('waka_kurikulum') && !$spv->hasRole('super_admin'))
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRemoveSupervisor{{ $spv->id }}" title="Cabut Tugas Supervisor">
                                <i class="ti ti-trash"></i>
                            </button>

                            <!-- Modal Konfirmasi Cabut -->
                            <div class="modal modal-blur fade" id="modalRemoveSupervisor{{ $spv->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        <div class="modal-status bg-danger"></div>
                                        <div class="modal-body text-center py-4">
                                            <i class="ti ti-alert-triangle fs-1 text-danger mb-2 d-block"></i>
                                            <h3 class="fw-bold">Cabut Tugas Supervisor?</h3>
                                            <div class="text-muted small">
                                                Apakah Anda yakin ingin mencabut wewenang supervisor dari <strong>{{ $spv->name }}</strong> untuk Tahun Pelajaran <strong>{{ $academicYear }}</strong>?
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <div class="w-100">
                                                <div class="row">
                                                    <div class="col">
                                                        <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Batal</button>
                                                    </div>
                                                    <div class="col">
                                                        <form action="{{ route('teacher-assignments.remove-supervisor', $spv) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                                            <button type="submit" class="btn btn-danger w-100">Ya, Cabut</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-user-x fs-1 mb-2 d-block"></i>
                        Belum ada guru yang ditugaskan sebagai supervisor.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tugaskan Guru sebagai Supervisor -->
<div class="modal modal-blur fade" id="modalAssignSupervisor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('teacher-assignments.assign-supervisor') }}" method="POST">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-user-plus me-1 text-warning"></i> Tugaskan Guru sebagai Supervisor (TP. {{ $academicYear }})
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Guru yang ditugaskan akan memperoleh akses menu <strong>Supervisor</strong> dan otomatis muncul dalam daftar pilihan supervisor saat penjadwalan supervisi pada TP <strong>{{ $academicYear }}</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Guru</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru yang Akan Ditugaskan --</option>
                            @foreach($candidateTeachers as $ct)
                                @php
                                    $mapel = $ct->subject ?: ($ct->subjectAssignments->first()?->subject?->name ?? '-');
                                @endphp
                                <option value="{{ $ct->id }}">
                                    {{ $ct->name }} (Mapel: {{ $mapel }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-hint small mt-1">Menampilkan seluruh guru yang belum ditugaskan sebagai supervisor pada TP {{ $academicYear }}.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jabatan / Tugas Tambahan Supervisor (Opsional)</label>
                        <input type="text" name="position_title" class="form-control" placeholder="Contoh: Koordinator Supervisor Kejuruan / Senior">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark" style="background-color: #f59e0b; border-color: #f59e0b; color: #fff !important;">
                        <i class="ti ti-check me-1"></i> Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Import Excel Supervisor -->
<div class="modal fade" id="modalImportSupervisor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('teacher-assignments.import-supervisor') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="ti ti-file-spreadsheet me-2"></i> Import Tim Supervisor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-start mb-3">
                    <i class="ti ti-info-circle fs-2 me-2 mt-1"></i>
                    <div class="small">
                        Unggah file Excel (<strong>.xlsx</strong>) atau <strong>.csv</strong> berisi daftar nama guru yang ditugaskan sebagai Tim Supervisor Sekolah.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih File Excel / CSV</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt,.xls" required>
                    <div class="form-text small">Mendukung format: <code>.xlsx</code>, <code>.csv</code> (Maks. 10 MB)</div>
                </div>

                <div class="card bg-light border p-3 mb-2">
                    <div class="fw-bold small text-dark mb-1"><i class="ti ti-bulb me-1 text-warning"></i> Format yang Didukung:</div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Kolom 1: <em>Nama Guru</em> (contoh: <code>Supriyanto, ST</code>)</li>
                        <li>Kolom 2 (Opsional): <em>NIGS / NIP</em> (contoh: <code>40601</code>)</li>
                        <li>Kolom 3 (Opsional): <em>Tugas Tambahan / Keterangan</em> (contoh: <code>Koordinator Supervisor Kejuruan</code>)</li>
                    </ul>
                </div>

                <div class="text-end">
                    <a href="{{ route('teacher-assignments.template-supervisor', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-link text-decoration-none">
                        <i class="ti ti-download me-1"></i> Download Format Template Excel (.csv)
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="ti ti-cloud-upload me-1"></i> Upload & Terapkan Supervisor
                </button>
            </div>
        </form>
    </div>
</div>

@elseif($tab === 'struktural')
<!-- ======================= TAB 4: PENUGASAN STRUKTURAL ======================= -->

<!-- Card Info & Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('teacher-assignments.index') }}" method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="struktural">
            
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tahun Ajaran</label>
                <select name="academic_year" class="form-select" onchange="this.form.submit()">
                    <option value="{{ $academicYear }}">{{ $academicYear }} (Aktif)</option>
                    <option value="2025/2026" {{ $academicYear === '2025/2026' ? 'selected' : '' }}>2025/2026</option>
                    <option value="2026/2027" {{ $academicYear === '2026/2027' ? 'selected' : '' }}>2026/2027</option>
                    <option value="2027/2028" {{ $academicYear === '2027/2028' ? 'selected' : '' }}>2027/2028</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Filter Kategori</label>
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($structuralCategories as $cat)
                        <option value="{{ $cat }}" {{ ($category ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari Jabatan / Guru / SK</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama jabatan, guru, kode, SK..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1 d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($search || $positionId || $teacherId || !empty($category))
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear]) }}" class="btn btn-icon btn-outline-secondary" title="Reset Filter">
                            <i class="ti ti-rotate-2"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Card Penugasan Struktural -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-briefcase me-2 text-indigo"></i>
                Daftar Jabatan &amp; Penugasan Struktural (TP. {{ $academicYear }})
            </h4>
            <div class="text-muted small mt-1">
                Kelola master jabatan pimpinan & unit kerja, tanda tangan digital, serta penetapan SK & TMT penugasan guru.
            </div>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreatePosition">
                <i class="ti ti-plus me-1"></i> Tambah Jabatan
            </button>
            <button type="button" class="btn btn-sm btn-indigo" data-bs-toggle="modal" data-bs-target="#modalAssignStructural">
                <i class="ti ti-user-plus me-1"></i> Tugaskan Guru
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportStructural">
                <i class="ti ti-file-spreadsheet me-1"></i> Import Excel
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Jabatan</th>
                    <th style="width: 100px;">Kode</th>
                    <th>GTK yang Ditugaskan</th>
                    <th>Nomor SK &amp; TMT</th>
                    <th style="width: 110px;" class="text-center">Tanda Tangan</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rowNo = $structuralAssignments->firstItem();
                @endphp
                @forelse($structuralAssignments as $posIndex => $pos)
                @php
                    $catClass = match($pos->category) {
                        'Pimpinan' => 'bg-danger-lt',
                        'Wakil Kepala Sekolah' => 'bg-indigo-lt',
                        'Kepala Program Keahlian' => 'bg-cyan-lt',
                        'Kepala Unit & Fasilitas' => 'bg-teal-lt',
                        'Tata Kelola & Keuangan' => 'bg-purple-lt',
                        default => 'bg-secondary-lt'
                    };
                    $isMultiple = (bool) $pos->allows_multiple;
                    $activeCount = $pos->activeAssignments->count();
                @endphp

                @if($isMultiple && $activeCount > 0)
                    {{-- JAMAK: Render 1 baris per personil penugasan --}}
                    @foreach($pos->activeAssignments as $asgnIndex => $asgn)
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $rowNo++ }}</td>
                        <td>
                            <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                            @if($pos->description)
                                <div class="text-muted small">{{ $pos->description }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                        </td>
                        <td>
                            @if($asgn->teacher)
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-xs bg-indigo-lt rounded-circle me-2 fw-bold" style="font-size: 0.70rem;">
                                        {{ strtoupper(substr($asgn->teacher->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $asgn->teacher->name }}</div>
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            NIGS: {{ $asgn->teacher->nigs ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Data personil tidak ditemukan</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if($asgn->sk_number)
                                    <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.75rem;">
                                        <i class="ti ti-file-certificate me-1 text-primary"></i>{{ $asgn->sk_number }}
                                    </div>
                                @endif
                                @if($asgn->sk_date)
                                    <div class="text-muted" style="font-size: 0.70rem;">
                                        <i class="ti ti-calendar me-1"></i>TMT: {{ $asgn->sk_date->format('d/m/Y') }}
                                    </div>
                                @endif
                                @if($asgn->valid_until)
                                    <div class="text-indigo fw-medium" style="font-size: 0.70rem;">
                                        <i class="ti ti-calendar-time me-1"></i>Masa: s.d. {{ $asgn->valid_until->format('d/m/Y') }}
                                    </div>
                                @else
                                    <div class="text-warning fw-medium" style="font-size: 0.70rem;" title="Tanggal akhir SK belum diisi. Penugasan hanya aktif pada tahun ajaran asal.">
                                        <i class="ti ti-alert-triangle me-1"></i>Perlu Verifikasi Masa
                                    </div>
                                @endif
                                @if($asgn->notes)
                                    <div class="text-secondary fst-italic" style="font-size: 0.70rem;" title="{{ $asgn->notes }}">
                                        <i class="ti ti-notes me-1"></i>{{ Str::limit($asgn->notes, 30) }}
                                    </div>
                                @endif
                                @if(!$asgn->sk_number && !$asgn->sk_date && !$asgn->notes)
                                    <span class="text-muted" style="font-size: 0.70rem;">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            @if($pos->signature_image_path)
                                <a href="{{ route('structural-positions.signature', $pos) }}" target="_blank" title="Lihat Tanda Tangan">
                                    <img src="{{ route('structural-positions.signature', $pos) }}" alt="TTD {{ $pos->name }}" style="height: 38px; max-width: 80px; object-fit: contain;" class="border p-1 rounded bg-white shadow-xs">
                                </a>
                            @else
                                <span class="text-muted small fst-italic">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-lt">Aktif</span>
                            @if($asgn->academic_year !== $academicYear)
                                <div class="mt-1">
                                    <span class="badge bg-purple-lt" style="font-size: 0.65rem;" title="SK Multi-Tahun asal {{ $asgn->academic_year }}">SK {{ $asgn->academic_year }}</span>
                                </div>
                            @elseif(!$asgn->valid_until)
                                <div class="mt-1">
                                    <span class="badge bg-warning-lt text-dark" style="font-size: 0.65rem;" title="Masa akhir SK belum diverifikasi definitif."><i class="ti ti-help me-1"></i> Belum Verifikasi Akhir</span>
                                </div>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditAsgn{{ $asgn->id }}" title="Ubah SK / Ganti Personil Ini">
                                    <i class="ti ti-edit me-1"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalAddPersonilPos{{ $pos->id }}" title="Tambah Personil Baru untuk {{ $pos->name }}">
                                    <i class="ti ti-plus"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditPos{{ $pos->id }}">
                                            <i class="ti ti-adjustments me-2 text-muted"></i> Edit Master Jabatan
                                        </button>
                                        @if($asgn->is_active)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('teacher-assignments.deactivate-structural', $asgn) }}" method="POST" onsubmit="return confirm('Hentikan masa penugasan {{ $asgn->teacher?->name }} untuk jabatan {{ $pos->name }}? Riwayat SK dan data penugasan akan tetap tersimpan.');">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-warning">
                                                    <i class="ti ti-calendar-off me-2"></i> Hentikan Masa Jabatan
                                                </button>
                                            </form>
                                        @endif
                                        <div class="dropdown-divider"></div>
                                        <form action="{{ route('teacher-assignments.destroy-structural', $asgn) }}" method="POST" onsubmit="return confirm('Hapus data penugasan {{ $asgn->teacher?->name }} dari jabatan {{ $pos->name }}? Data ini akan terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="ti ti-trash me-2"></i> Hapus Penugasan
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @else
                    {{-- TUNGGAL atau JAMAK BELUM TERISI: Render 1 baris --}}
                    @php
                        $currentAsgn = $pos->activeAssignments->first();
                    @endphp
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $rowNo++ }}</td>
                        <td>
                            <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                            @if($pos->description)
                                <div class="text-muted small">{{ $pos->description }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                        </td>
                        <td>
                            @if($currentAsgn && $currentAsgn->teacher)
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-xs bg-indigo-lt rounded-circle me-2 fw-bold" style="font-size: 0.70rem;">
                                        {{ strtoupper(substr($currentAsgn->teacher->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $currentAsgn->teacher->name }}</div>
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            NIGS: {{ $currentAsgn->teacher->nigs ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-secondary-lt text-secondary fst-italic">Belum Ditugaskan</span>
                            @endif
                        </td>
                        <td>
                            @if($currentAsgn)
                                <div class="small">
                                    @if($currentAsgn->sk_number)
                                        <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.75rem;">
                                            <i class="ti ti-file-certificate me-1 text-primary"></i>{{ $currentAsgn->sk_number }}
                                        </div>
                                    @endif
                                    @if($currentAsgn->sk_date)
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            <i class="ti ti-calendar me-1"></i>TMT: {{ $currentAsgn->sk_date->format('d/m/Y') }}
                                        </div>
                                    @endif
                                    @if($currentAsgn->valid_until)
                                        <div class="text-indigo fw-medium" style="font-size: 0.70rem;">
                                            <i class="ti ti-calendar-time me-1"></i>Masa: s.d. {{ $currentAsgn->valid_until->format('d/m/Y') }}
                                        </div>
                                    @else
                                        <div class="text-warning fw-medium" style="font-size: 0.70rem;" title="Tanggal akhir SK belum diisi. Penugasan hanya aktif pada tahun ajaran asal.">
                                            <i class="ti ti-alert-triangle me-1"></i>Perlu Verifikasi Masa
                                        </div>
                                    @endif
                                    @if($currentAsgn->notes)
                                        <div class="text-secondary fst-italic" style="font-size: 0.70rem;" title="{{ $currentAsgn->notes }}">
                                            <i class="ti ti-notes me-1"></i>{{ Str::limit($currentAsgn->notes, 30) }}
                                        </div>
                                    @endif
                                    @if(!$currentAsgn->sk_number && !$currentAsgn->sk_date && !$currentAsgn->notes)
                                        <span class="text-muted" style="font-size: 0.70rem;">-</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($pos->signature_image_path)
                                <a href="{{ route('structural-positions.signature', $pos) }}" target="_blank" title="Lihat Tanda Tangan">
                                    <img src="{{ route('structural-positions.signature', $pos) }}" alt="TTD {{ $pos->name }}" style="height: 38px; max-width: 80px; object-fit: contain;" class="border p-1 rounded bg-white shadow-xs">
                                </a>
                            @else
                                <span class="text-muted small fst-italic">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($currentAsgn)
                                <span class="badge bg-success-lt">Aktif</span>
                                @if($currentAsgn->academic_year !== $academicYear)
                                    <div class="mt-1">
                                        <span class="badge bg-purple-lt" style="font-size: 0.65rem;" title="SK Multi-Tahun asal {{ $currentAsgn->academic_year }}">SK {{ $currentAsgn->academic_year }}</span>
                                    </div>
                                @elseif(!$currentAsgn->valid_until)
                                    <div class="mt-1">
                                        <span class="badge bg-warning-lt text-dark" style="font-size: 0.65rem;" title="Masa akhir SK belum diverifikasi definitif."><i class="ti ti-help me-1"></i> Belum Verifikasi Akhir</span>
                                    </div>
                                @endif
                            @else
                                <span class="badge bg-warning-lt">Kosong</span>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                @if($currentAsgn)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditAsgn{{ $currentAsgn->id }}" title="Ubah SK / Ganti Pejabat">
                                        <i class="ti ti-edit me-1"></i> Ganti Pejabat
                                    </button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddPersonilPos{{ $pos->id }}" title="Tugaskan Pejabat">
                                        <i class="ti ti-user-plus me-1"></i> Tugaskan
                                    </button>
                                @endif
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditPos{{ $pos->id }}">
                                            <i class="ti ti-adjustments me-2 text-muted"></i> Edit Master Jabatan
                                        </button>
                                        @if($currentAsgn)
                                            @if($currentAsgn->is_active)
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('teacher-assignments.deactivate-structural', $currentAsgn) }}" method="POST" onsubmit="return confirm('Hentikan masa penugasan {{ $currentAsgn->teacher?->name }} untuk jabatan {{ $pos->name }}? Riwayat SK dan data penugasan akan tetap tersimpan.');">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="ti ti-calendar-off me-2"></i> Hentikan Masa Jabatan
                                                    </button>
                                                </form>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('teacher-assignments.destroy-structural', $currentAsgn) }}" method="POST" onsubmit="return confirm('Hapus data penugasan {{ $currentAsgn->teacher?->name }} dari jabatan {{ $pos->name }}? Data ini akan terhapus.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ti ti-trash me-2"></i> Hapus Penugasan
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif

                <!-- Modal Tambah / Ganti Personil untuk Jabatan ini -->
                @php
                    $posCurrentAsgn = $pos->activeAssignments->first();
                @endphp
                <div class="modal fade" id="modalAddPersonilPos{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('teacher-assignments.store-structural') }}" method="POST">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                <input type="hidden" name="structural_position_id" value="{{ $pos->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-briefcase me-1 {{ $pos->allows_multiple ? 'text-teal' : ($posCurrentAsgn ? 'text-warning' : 'text-indigo') }}"></i>
                                        {{ $pos->allows_multiple ? 'Tambah Personil' : ($posCurrentAsgn ? 'Ganti Pejabat' : 'Tugaskan Pejabat') }}: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @if(!$pos->allows_multiple)
                                        @if($posCurrentAsgn)
                                            <div class="alert alert-warning py-2 px-3 mb-3 small">
                                                <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i> Kapasitas Penugasan: Tunggal (hanya 1 orang)</div>
                                                <div>Kode: <code>{{ $pos->code ?? '-' }}</code> &bull; Saat ini dijabat oleh: <strong>{{ $posCurrentAsgn->teacher?->name }}</strong> (NIGS: {{ $posCurrentAsgn->teacher?->nigs ?? '-' }}).</div>
                                                <div class="text-danger mt-1"><i class="ti ti-info-circle me-1"></i> Menyimpan form ini akan <strong>menggantikan</strong> pejabat di atas.</div>
                                            </div>
                                        @else
                                            <div class="alert alert-info py-2 px-3 mb-3 small">
                                                <i class="ti ti-info-circle me-1"></i> <strong>Kapasitas Penugasan: Tunggal (hanya 1 orang)</strong> &bull; Kode: <code>{{ $pos->code ?? '-' }}</code> (Belum ada pejabat aktif).
                                            </div>
                                        @endif
                                    @else
                                        <div class="alert alert-teal py-2 px-3 mb-3 small">
                                            <i class="ti ti-users me-1 text-teal"></i> <strong>Kapasitas Penugasan: Jamak (bisa lebih 1 orang)</strong> &bull; Kode: <code>{{ $pos->code ?? '-' }}</code>. Penugasan baru akan ditambahkan ke <strong>baris baru</strong>.
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Pilih Pegawai / Guru / Staf</label>
                                        <select name="teacher_id" class="form-select" required>
                                            <option value="">-- Pilih Pegawai yang Ditugaskan --</option>
                                            @foreach($allTeachers as $at)
                                                <option value="{{ $at->id }}">
                                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nomor SK Pengangkatan</label>
                                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                                            <input type="date" name="sk_date" class="form-control" value="{{ date('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                                            <input type="date" name="valid_from" class="form-control" value="{{ date('Y-m-d') }}">
                                            <div class="form-text small text-muted">Default: tanggal SK</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                                            <input type="date" name="valid_until" class="form-control">
                                            <div class="form-text small text-muted">Saran master: {{ $pos->default_tenure_years ?? 1 }} tahun. Kosongkan jika belum diketahui / SK tahunan.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> {{ $posCurrentAsgn && !$pos->allows_multiple ? 'Ganti Pejabat' : 'Simpan Penugasan' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modals Edit untuk masing-masing penugasan personil yang ada -->
                @foreach($pos->activeAssignments as $asgn)
                <div class="modal fade" id="modalEditAsgn{{ $asgn->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('teacher-assignments.store-structural') }}" method="POST">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                <input type="hidden" name="structural_position_id" value="{{ $pos->id }}">
                                <input type="hidden" name="assignment_id" value="{{ $asgn->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-briefcase me-1 text-indigo"></i>
                                        Ubah Penugasan: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Pegawai / Guru / Staf</label>
                                        <select name="teacher_id" class="form-select" required>
                                            @foreach($allTeachers as $at)
                                                <option value="{{ $at->id }}" {{ ($asgn->teacher_id == $at->id) ? 'selected' : '' }}>
                                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nomor SK Pengangkatan</label>
                                            <input type="text" name="sk_number" class="form-control" value="{{ old('sk_number', $asgn->sk_number) }}" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                                            <input type="date" name="sk_date" class="form-control" value="{{ old('sk_date', $asgn->sk_date ? $asgn->sk_date->format('Y-m-d') : date('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                                            <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $asgn->valid_from ? $asgn->valid_from->format('Y-m-d') : ($asgn->sk_date ? $asgn->sk_date->format('Y-m-d') : date('Y-m-d'))) }}">
                                            <div class="form-text small text-muted">Default: tanggal SK</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                                            <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $asgn->valid_until ? $asgn->valid_until->format('Y-m-d') : '') }}">
                                            <div class="form-text small text-muted">Saran master: {{ $pos->default_tenure_years ?? 1 }} tahun. Kosongkan jika belum diketahui / SK tahunan.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan...">{{ old('notes', $asgn->notes) }}</textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> Perbarui Penugasan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Modal Edit Master Jabatan -->
                <div class="modal fade" id="modalEditPos{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('structural-positions.update', $pos) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Master Jabatan: {{ $pos->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Nama Jabatan</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $pos->name) }}" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Kode Singkat</label>
                                            <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code', $pos->code) }}" placeholder="Contoh: KS, WAKA_KUR">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Urutan Tampilan</label>
                                            <input type="number" name="order" class="form-control" value="{{ old('order', $pos->order) }}" min="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" {{ empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Tunggal (hanya 1 orang)</span>
                                                            <span class="text-muted small">Input baru menggantikan pejabat</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" {{ !empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Jamak (bisa lebih 1 orang)</span>
                                                            <span class="text-muted small">Bisa tambah personil baris baru</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori Jabatan</label>
                                        <input type="text" name="category" class="form-control" list="categoryOptions" value="{{ old('category', $pos->category) }}" placeholder="Pilih atau ketik kategori...">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Keterangan / Uraian Tugas</label>
                                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi jabatan...">{{ old('description', $pos->description) }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Scan Tanda Tangan (Upload Tanda Tangan)</label>
                                        <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                        <small class="text-muted">Maksimal 2MB. Disarankan PNG berlatar transparan.</small>
                                        @if($pos->signature_image_path)
                                            <div class="mt-2 p-2 bg-light rounded border d-flex align-items-center justify-content-between">
                                                <img src="{{ route('structural-positions.signature', $pos) }}" alt="TTD" style="height: 35px; max-width: 80px; object-fit: contain;">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="del_sig_pos_{{ $pos->id }}">
                                                    <label class="form-check-label text-danger small" for="del_sig_pos_{{ $pos->id }}">Hapus TTD</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $pos->is_active ? 'checked' : '' }}>
                                            <span class="form-check-label fw-bold">Status Jabatan Aktif</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-briefcase-off fs-1 mb-2 d-block text-secondary"></i>
                        Belum ada data jabatan struktural yang sesuai kriteria.
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreatePosition">
                                <i class="ti ti-plus me-1"></i> Tambah Master Jabatan Pertama
                            </button>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($structuralAssignments->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between">
        <div class="text-muted small">
            Menampilkan {{ $structuralAssignments->firstItem() }} s/d {{ $structuralAssignments->lastItem() }} dari {{ $structuralAssignments->total() }} data
        </div>
        <div>
            {{ $structuralAssignments->links() }}
        </div>
    </div>
    @endif
</div>

<!-- Modal Tambah Master Jabatan -->
<div class="modal fade" id="modalCreatePosition" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('structural-positions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="struktural">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="ti ti-plus me-1 text-primary"></i> Tambah Master Jabatan Struktural</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nama Jabatan</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Koordinator Lab Multimedia" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Kode Singkat</label>
                            <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: KOOR_LAB">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="order" class="form-control" value="{{ \App\Models\StructuralPosition::max('order') + 1 }}" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Tunggal (hanya 1 orang)</span>
                                            <span class="text-muted small">Input baru menggantikan pejabat</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Jamak (bisa lebih 1 orang)</span>
                                            <span class="text-muted small">Bisa tambah personil baris baru</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori Jabatan</label>
                        <input type="text" name="category" class="form-control" list="categoryOptions" placeholder="Pilih atau ketik kategori...">
                        <datalist id="categoryOptions">
                            <option value="Pimpinan">
                            <option value="Wakil Kepala Sekolah">
                            <option value="Kepala Program Keahlian">
                            <option value="Kepala Unit & Fasilitas">
                            <option value="Tata Kelola & Keuangan">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan / Uraian Tugas</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi jabatan..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Scan Tanda Tangan (Upload Tanda Tangan)</label>
                        <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <small class="text-muted">Maksimal 2MB. Disarankan PNG berlatar transparan.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label fw-bold">Status Jabatan Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">Simpan Jabatan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Penugasan Struktural Bebas -->
<div class="modal fade" id="modalAssignStructural" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('teacher-assignments.store-structural') }}" method="POST">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-briefcase me-1 text-indigo"></i> Tambah Penugasan Struktural
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Jabatan Struktural</label>
                        <select name="structural_position_id" id="selectStructuralPosition" class="form-select" required>
                            <option value="">-- Pilih Jabatan Struktural --</option>
                            @foreach($structuralPositionChoices as $sp)
                                @php
                                    $spCurrentAsgn = $sp->activeAssignments->first();
                                @endphp
                                <option value="{{ $sp->id }}" 
                                    data-allows-multiple="{{ $sp->allows_multiple ? '1' : '0' }}"
                                    data-code="{{ $sp->code ?? '-' }}"
                                    data-current-name="{{ $spCurrentAsgn?->teacher?->name ?? '' }}"
                                    data-current-nigs="{{ $spCurrentAsgn?->teacher?->nigs ? 'NIGS: ' . $spCurrentAsgn->teacher->nigs : '' }}"
                                    {{ $positionId == $sp->id ? 'selected' : '' }}>
                                    {{ $sp->name }} ({{ $sp->category ?? 'Umum' }}) — [{{ $sp->allows_multiple ? 'Jamak' : 'Tunggal' }}]
                                </option>
                            @endforeach
                        </select>
                        <div id="alertPositionCapacity" class="mt-2 d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Guru / Pegawai</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru yang Ditugaskan --</option>
                            @foreach($allTeachers as $at)
                                <option value="{{ $at->id }}" {{ $teacherId == $at->id ? 'selected' : '' }}>
                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK Pengangkatan</label>
                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                            <input type="date" name="sk_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                            <input type="date" name="valid_from" class="form-control" value="{{ date('Y-m-d') }}">
                            <div class="form-text small text-muted">Default: tanggal SK</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                            <input type="date" name="valid_until" class="form-control">
                            <div class="form-text small text-muted">Saran master: jabatan terkait. Kosongkan jika belum diketahui / SK tahunan.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Excel Penugasan Struktural -->
<div class="modal fade" id="modalImportStructural" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('teacher-assignments.import-structural') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="ti ti-file-spreadsheet me-2"></i> Import Penugasan Struktural</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-start mb-3">
                    <i class="ti ti-info-circle fs-2 me-2 mt-1"></i>
                    <div class="small">
                        Unggah file Excel (<strong>.xlsx</strong>) atau <strong>.csv</strong> berisi daftar penugasan jabatan struktural sekolah untuk TP. <strong>{{ $academicYear }}</strong>.
                        Sistem otomatis mengenali nama/kode jabatan dan mencocokkan identitas guru.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih File Excel / CSV</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt,.xls" required>
                    <div class="form-text small">Mendukung format: <code>.xlsx</code>, <code>.csv</code> (Maks. 10 MB)</div>
                </div>

                <div class="card bg-light border p-3 mb-2">
                    <div class="fw-bold small text-dark mb-1"><i class="ti ti-bulb me-1 text-warning"></i> Format yang Didukung:</div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Kolom 1: <em>Nama Jabatan</em> (contoh: <code>Waka Sarpras</code>, <code>Ka. Bengkel</code>, atau kodenya <code>WAKA_SARPRAS</code>)</li>
                        <li>Kolom 2: <em>Nama Guru</em> (contoh: <code>Supriyanto, ST</code>)</li>
                        <li>Kolom 3 (Opsional): <em>NIGS / NIP</em></li>
                        <li>Kolom 4 (Opsional): <em>Nomor SK</em></li>
                        <li>Kolom 5 (Opsional): <em>Tanggal SK / TMT</em></li>
                        <li>Kolom 6 (Opsional): <em>Catatan / Keterangan</em></li>
                    </ul>
                </div>

                <div class="text-end">
                    <a href="{{ route('teacher-assignments.template-structural', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-link text-decoration-none">
                        <i class="ti ti-download me-1"></i> Download Format Template Excel (.csv)
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="ti ti-cloud-upload me-1"></i> Upload & Terapkan Penugasan
                </button>
            </div>
        </form>
    </div>
</div>

@elseif($tab === 'operasional')
<!-- ======================= TAB 4: PENUGASAN OPERASIONAL ======================= -->

<!-- Card Info & Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('teacher-assignments.index') }}" method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="operasional">
            
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tahun Ajaran</label>
                <select name="academic_year" class="form-select" onchange="this.form.submit()">
                    <option value="{{ $academicYear }}">{{ $academicYear }} (Aktif)</option>
                    <option value="2025/2026" {{ $academicYear === '2025/2026' ? 'selected' : '' }}>2025/2026</option>
                    <option value="2026/2027" {{ $academicYear === '2026/2027' ? 'selected' : '' }}>2026/2027</option>
                    <option value="2027/2028" {{ $academicYear === '2027/2028' ? 'selected' : '' }}>2027/2028</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Filter Kategori</label>
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($operationalCategories as $cat)
                        <option value="{{ $cat }}" {{ ($category ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari Tugas / GTK / SK</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama tugas, GTK, kode, SK..." value="{{ $search }}">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1 d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($search || $positionId || $teacherId || !empty($category))
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'operasional', 'academic_year' => $academicYear]) }}" class="btn btn-icon btn-outline-secondary" title="Reset Filter">
                            <i class="ti ti-rotate-2"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Card Penugasan Operasional -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div>
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-settings-cog me-2 text-azure"></i>
                Daftar Tugas &amp; Penugasan Operasional (TP. {{ $academicYear }})
            </h4>
            <div class="text-muted small mt-1">
                Kelola penugasan operasional sekolah (seperti Tim Kedisiplinan, Operator Sistem, Laboran, Toolman, Petugas Perpustakaan, dsb).
            </div>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateOperationalPosition">
                <i class="ti ti-plus me-1"></i> Tambah Master Tugas
            </button>
            <button type="button" class="btn btn-sm btn-azure" data-bs-toggle="modal" data-bs-target="#modalAssignOperational">
                <i class="ti ti-user-plus me-1"></i> Tugaskan Personil Operasional
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Tugas Operasional</th>
                    <th style="width: 110px;">Kode</th>
                    <th style="width: 100px;">Cakupan</th>
                    <th>GTK yang Ditugaskan</th>
                    <th>Nomor SK &amp; TMT</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $opRowNo = $operationalAssignments->firstItem();
                @endphp
                @forelse($operationalAssignments as $posIndex => $pos)
                @php
                    $isMultiple = (bool) $pos->allows_multiple;
                    $activeCount = $pos->activeAssignments->count();
                @endphp

                @if($isMultiple && $activeCount > 0)
                    {{-- JAMAK: Render 1 baris per personil penugasan --}}
                    @foreach($pos->activeAssignments as $asgnIndex => $asgn)
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $opRowNo++ }}</td>
                        <td>
                            <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                            @if($pos->description)
                                <div class="text-muted small">{{ $pos->description }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt">{{ $pos->scope_label }}</span>
                        </td>
                        <td>
                            @if($asgn->teacher)
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-xs bg-azure-lt rounded-circle me-2 fw-bold" style="font-size: 0.70rem;">
                                        {{ strtoupper(substr($asgn->teacher->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $asgn->teacher->name }}</div>
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            NIGS: {{ $asgn->teacher->nigs ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Data personil tidak ditemukan</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if($asgn->sk_number)
                                    <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.75rem;">
                                        <i class="ti ti-file-certificate me-1 text-primary"></i>{{ $asgn->sk_number }}
                                    </div>
                                @endif
                                @if($asgn->sk_date)
                                    <div class="text-muted" style="font-size: 0.70rem;">
                                        <i class="ti ti-calendar me-1"></i>TMT: {{ $asgn->sk_date->format('d/m/Y') }}
                                    </div>
                                @endif
                                @if($asgn->valid_until)
                                    <div class="text-indigo fw-medium" style="font-size: 0.70rem;">
                                        <i class="ti ti-calendar-time me-1"></i>Masa: s.d. {{ $asgn->valid_until->format('d/m/Y') }}
                                    </div>
                                @else
                                    <div class="text-warning fw-medium" style="font-size: 0.70rem;" title="Tanggal akhir SK belum diisi. Penugasan hanya aktif pada tahun ajaran asal.">
                                        <i class="ti ti-alert-triangle me-1"></i>Perlu Verifikasi Masa
                                    </div>
                                @endif
                                @if($asgn->notes)
                                    <div class="text-secondary fst-italic" style="font-size: 0.70rem;" title="{{ $asgn->notes }}">
                                        <i class="ti ti-notes me-1"></i>{{ Str::limit($asgn->notes, 30) }}
                                    </div>
                                @endif
                                @if(!$asgn->sk_number && !$asgn->sk_date && !$asgn->notes)
                                    <span class="text-muted" style="font-size: 0.70rem;">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-lt">Aktif</span>
                            @if($asgn->academic_year !== $academicYear)
                                <div class="mt-1">
                                    <span class="badge bg-purple-lt" style="font-size: 0.65rem;" title="SK Multi-Tahun asal {{ $asgn->academic_year }}">SK {{ $asgn->academic_year }}</span>
                                </div>
                            @elseif(!$asgn->valid_until)
                                <div class="mt-1">
                                    <span class="badge bg-warning-lt text-dark" style="font-size: 0.65rem;" title="Masa akhir SK belum diverifikasi definitif."><i class="ti ti-help me-1"></i> Belum Verifikasi Akhir</span>
                                </div>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditOpAsgn{{ $asgn->id }}" title="Ubah SK / Ganti Personil Ini">
                                    <i class="ti ti-edit me-1"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalAddPersonilOpPos{{ $pos->id }}" title="Tambah Personil Baru untuk {{ $pos->name }}">
                                    <i class="ti ti-plus"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditOpPos{{ $pos->id }}">
                                            <i class="ti ti-adjustments me-2 text-muted"></i> Edit Master Tugas
                                        </button>
                                        @if($asgn->is_active)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('teacher-assignments.deactivate-operational', $asgn) }}" method="POST" onsubmit="return confirm('Hentikan masa penugasan {{ $asgn->teacher?->name }} untuk tugas {{ $pos->name }}? Riwayat SK dan data penugasan akan tetap tersimpan.');">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-warning">
                                                    <i class="ti ti-calendar-off me-2"></i> Hentikan Masa Penugasan
                                                </button>
                                            </form>
                                        @endif
                                        <div class="dropdown-divider"></div>
                                        <form action="{{ route('teacher-assignments.destroy-operational', $asgn) }}" method="POST" onsubmit="return confirm('Hapus data penugasan {{ $asgn->teacher?->name }} dari tugas {{ $pos->name }}? Data ini akan terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="ti ti-trash me-2"></i> Hapus Penugasan
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @else
                    {{-- TUNGGAL atau JAMAK BELUM TERISI: Render 1 baris --}}
                    @php
                        $currentOpAsgn = $pos->activeAssignments->first();
                    @endphp
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $opRowNo++ }}</td>
                        <td>
                            <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                            @if($pos->description)
                                <div class="text-muted small">{{ $pos->description }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt">{{ $pos->scope_label }}</span>
                        </td>
                        <td>
                            @if($currentOpAsgn && $currentOpAsgn->teacher)
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-xs bg-azure-lt rounded-circle me-2 fw-bold" style="font-size: 0.70rem;">
                                        {{ strtoupper(substr($currentOpAsgn->teacher->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $currentOpAsgn->teacher->name }}</div>
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            NIGS: {{ $currentOpAsgn->teacher->nigs ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-warning-lt text-warning fw-bold py-1 px-2">
                                    <i class="ti ti-alert-triangle me-1"></i> Belum Ditetapkan
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($currentOpAsgn)
                                <div class="small">
                                    @if($currentOpAsgn->sk_number)
                                        <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.75rem;">
                                            <i class="ti ti-file-certificate me-1 text-primary"></i>{{ $currentOpAsgn->sk_number }}
                                        </div>
                                    @endif
                                    @if($currentOpAsgn->sk_date)
                                        <div class="text-muted" style="font-size: 0.70rem;">
                                            <i class="ti ti-calendar me-1"></i>TMT: {{ $currentOpAsgn->sk_date->format('d/m/Y') }}
                                        </div>
                                    @endif
                                    @if($currentOpAsgn->valid_until)
                                        <div class="text-indigo fw-medium" style="font-size: 0.70rem;">
                                            <i class="ti ti-calendar-time me-1"></i>Masa: s.d. {{ $currentOpAsgn->valid_until->format('d/m/Y') }}
                                        </div>
                                    @else
                                        <div class="text-warning fw-medium" style="font-size: 0.70rem;" title="Tanggal akhir SK belum diisi. Penugasan hanya aktif pada tahun ajaran asal.">
                                            <i class="ti ti-alert-triangle me-1"></i>Perlu Verifikasi Masa
                                        </div>
                                    @endif
                                    @if($currentOpAsgn->notes)
                                        <div class="text-secondary fst-italic" style="font-size: 0.70rem;" title="{{ $currentOpAsgn->notes }}">
                                            <i class="ti ti-notes me-1"></i>{{ Str::limit($currentOpAsgn->notes, 30) }}
                                        </div>
                                    @endif
                                    @if(!$currentOpAsgn->sk_number && !$currentOpAsgn->sk_date && !$currentOpAsgn->notes)
                                        <span class="text-muted" style="font-size: 0.70rem;">-</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($currentOpAsgn)
                                <span class="badge bg-success-lt">Aktif</span>
                                @if($currentOpAsgn->academic_year !== $academicYear)
                                    <div class="mt-1">
                                        <span class="badge bg-purple-lt" style="font-size: 0.65rem;" title="SK Multi-Tahun asal {{ $currentOpAsgn->academic_year }}">SK {{ $currentOpAsgn->academic_year }}</span>
                                    </div>
                                @elseif(!$currentOpAsgn->valid_until)
                                    <div class="mt-1">
                                        <span class="badge bg-warning-lt text-dark" style="font-size: 0.65rem;" title="Masa akhir SK belum diverifikasi definitif."><i class="ti ti-help me-1"></i> Belum Verifikasi Akhir</span>
                                    </div>
                                @endif
                            @else
                                <span class="badge bg-warning-lt">Kosong</span>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                @if($currentOpAsgn)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditOpAsgn{{ $currentOpAsgn->id }}" title="Ubah SK / Ganti Personil">
                                        <i class="ti ti-edit me-1"></i> Ganti Personil
                                    </button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddPersonilOpPos{{ $pos->id }}" title="Tugaskan Personil">
                                        <i class="ti ti-user-plus me-1"></i> Tugaskan
                                    </button>
                                @endif
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditOpPos{{ $pos->id }}">
                                            <i class="ti ti-adjustments me-2 text-muted"></i> Edit Master Tugas
                                        </button>
                                        @if($currentOpAsgn)
                                            @if($currentOpAsgn->is_active)
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('teacher-assignments.deactivate-operational', $currentOpAsgn) }}" method="POST" onsubmit="return confirm('Hentikan masa penugasan {{ $currentOpAsgn->teacher?->name }} untuk tugas {{ $pos->name }}? Riwayat SK dan data penugasan akan tetap tersimpan.');">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="ti ti-calendar-off me-2"></i> Hentikan Masa Penugasan
                                                    </button>
                                                </form>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('teacher-assignments.destroy-operational', $currentOpAsgn) }}" method="POST" onsubmit="return confirm('Hapus data penugasan {{ $currentOpAsgn->teacher?->name }} dari tugas {{ $pos->name }}? Data ini akan terhapus.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ti ti-trash me-2"></i> Hapus Penugasan
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif

                <!-- Modal Tambah / Ganti Personil Operasional untuk Posisi ini -->
                @php
                    $posCurrentOpAsgn = $pos->activeAssignments->first();
                @endphp
                <div class="modal fade" id="modalAddPersonilOpPos{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('teacher-assignments.store-operational') }}" method="POST">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                <input type="hidden" name="structural_position_id" value="{{ $pos->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-settings-cog me-1 {{ $pos->allows_multiple ? 'text-teal' : ($posCurrentOpAsgn ? 'text-warning' : 'text-azure') }}"></i>
                                        {{ $pos->allows_multiple ? 'Tambah Personil' : ($posCurrentOpAsgn ? 'Ganti Personil' : 'Tugaskan Personil') }}: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @if(!$pos->allows_multiple)
                                        @if($posCurrentOpAsgn)
                                            <div class="alert alert-warning py-2 px-3 mb-3 small">
                                                <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i> Kapasitas Penugasan: Tunggal (hanya 1 orang)</div>
                                                <div>Kode: <code>{{ $pos->code ?? '-' }}</code> &bull; Saat ini ditugaskan kepada: <strong>{{ $posCurrentOpAsgn->teacher?->name }}</strong> (NIGS: {{ $posCurrentOpAsgn->teacher?->nigs ?? '-' }}).</div>
                                                <div class="text-danger mt-1"><i class="ti ti-info-circle me-1"></i> Menyimpan form ini akan <strong>menggantikan</strong> personil di atas (riwayat lama tetap tersimpan).</div>
                                            </div>
                                        @else
                                            <div class="alert alert-info py-2 px-3 mb-3 small">
                                                <i class="ti ti-info-circle me-1"></i> <strong>Kapasitas Penugasan: Tunggal (hanya 1 orang)</strong> &bull; Kode: <code>{{ $pos->code ?? '-' }}</code> (Belum ada personil aktif).
                                            </div>
                                        @endif
                                    @else
                                        <div class="alert alert-teal py-2 px-3 mb-3 small">
                                            <i class="ti ti-users me-1 text-teal"></i> <strong>Kapasitas Penugasan: Jamak (bisa lebih 1 orang)</strong> &bull; Kode: <code>{{ $pos->code ?? '-' }}</code>. Penugasan baru akan ditambahkan ke <strong>baris baru</strong>.
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Pilih Pegawai / Guru / Staf</label>
                                        <select name="teacher_id" class="form-select" required>
                                            <option value="">-- Pilih Personil yang Ditugaskan --</option>
                                            @foreach($allTeachers as $at)
                                                <option value="{{ $at->id }}">
                                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nomor SK Penugasan</label>
                                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                                            <input type="date" name="sk_date" class="form-control" value="{{ date('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                                            <input type="date" name="valid_from" class="form-control" value="{{ date('Y-m-d') }}">
                                            <div class="form-text small text-muted">Default: tanggal SK</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                                            <input type="date" name="valid_until" class="form-control">
                                            <div class="form-text small text-muted">Saran master: {{ $pos->default_tenure_years ?? 1 }} tahun. Kosongkan jika belum diketahui / SK tahunan.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> {{ $posCurrentOpAsgn && !$pos->allows_multiple ? 'Ganti Personil' : 'Simpan Penugasan' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modals Edit untuk masing-masing penugasan personil operasional yang ada -->
                @foreach($pos->activeAssignments as $asgn)
                <div class="modal fade" id="modalEditOpAsgn{{ $asgn->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('teacher-assignments.store-operational') }}" method="POST">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                <input type="hidden" name="structural_position_id" value="{{ $pos->id }}">
                                <input type="hidden" name="assignment_id" value="{{ $asgn->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-settings-cog me-1 text-azure"></i>
                                        Ubah Penugasan Operasional: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Pegawai / Guru / Staf</label>
                                        <select name="teacher_id" class="form-select" required>
                                            @foreach($allTeachers as $at)
                                                <option value="{{ $at->id }}" {{ ($asgn->teacher_id == $at->id) ? 'selected' : '' }}>
                                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nomor SK Penugasan</label>
                                            <input type="text" name="sk_number" class="form-control" value="{{ old('sk_number', $asgn->sk_number) }}" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                                            <input type="date" name="sk_date" class="form-control" value="{{ old('sk_date', $asgn->sk_date ? $asgn->sk_date->format('Y-m-d') : date('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                                            <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $asgn->valid_from ? $asgn->valid_from->format('Y-m-d') : ($asgn->sk_date ? $asgn->sk_date->format('Y-m-d') : date('Y-m-d'))) }}">
                                            <div class="form-text small text-muted">Default: tanggal SK</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                                            <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $asgn->valid_until ? $asgn->valid_until->format('Y-m-d') : '') }}">
                                            <div class="form-text small text-muted">Saran master: {{ $pos->default_tenure_years ?? 1 }} tahun. Kosongkan jika belum diketahui / SK tahunan.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan...">{{ old('notes', $asgn->notes) }}</textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> Perbarui Penugasan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Modal Edit Master Tugas Operasional -->
                <div class="modal fade" id="modalEditOpPos{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('structural-positions.update', $pos) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Edit Master Tugas: {{ $pos->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Nama Tugas</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $pos->name) }}" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Kode Singkat</label>
                                            <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code', $pos->code) }}" placeholder="Contoh: TIM_KEDISIPLINAN">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Urutan</label>
                                            <input type="number" name="order" class="form-control" value="{{ old('order', $pos->order) }}" min="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input" {{ empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Tunggal (1 orang)</span>
                                                            <span class="text-muted small">Input baru menggantikan</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-selectgroup-item w-100">
                                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" {{ !empty($pos->allows_multiple) ? 'checked' : '' }}>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                                        <span>
                                                            <span class="fw-bold d-block text-dark">Jamak (&gt; 1 orang)</span>
                                                            <span class="text-muted small">Tambah personil baris baru</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Cakupan Tugas</label>
                                            <select name="scope_type" class="form-select">
                                                @foreach(\App\Models\StructuralPosition::SCOPES as $skVal => $skLbl)
                                                    <option value="{{ $skVal }}" {{ $pos->scope_type === $skVal ? 'selected' : '' }}>{{ $skLbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Masa Jabatan Default</label>
                                            <select name="default_tenure_years" class="form-select">
                                                @foreach(\App\Models\StructuralPosition::TENURE_OPTIONS as $tYr)
                                                    <option value="{{ $tYr }}" {{ (int) $pos->default_tenure_years === $tYr ? 'selected' : '' }}>{{ $tYr }} Tahun</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori</label>
                                        <input type="text" name="category" class="form-control" value="{{ old('category', $pos->category) }}" placeholder="Contoh: Kesiswaan, Sarpras...">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Keterangan / Uraian Tugas</label>
                                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi...">{{ old('description', $pos->description) }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $pos->is_active ? 'checked' : '' }}>
                                            <span class="form-check-label fw-bold">Status Tugas Aktif</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-settings-off fs-1 mb-2 d-block text-secondary"></i>
                        Belum ada data tugas operasional yang sesuai kriteria.
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateOperationalPosition">
                                <i class="ti ti-plus me-1"></i> Tambah Master Tugas Operasional Pertama
                            </button>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($operationalAssignments->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between">
        <div class="text-muted small">
            Menampilkan {{ $operationalAssignments->firstItem() }} s/d {{ $operationalAssignments->lastItem() }} dari {{ $operationalAssignments->total() }} data
        </div>
        <div>
            {{ $operationalAssignments->links() }}
        </div>
    </div>
    @endif
</div>

<!-- Modal Tambah Master Tugas Operasional -->
<div class="modal fade" id="modalCreateOperationalPosition" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('structural-positions.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="operasional">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="ti ti-plus me-1 text-primary"></i> Tambah Master Tugas Operasional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nama Tugas Operasional</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Tim Kedisiplinan Siswa" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Kode Singkat</label>
                            <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: TIM_KEDISIPLINAN">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="order" class="form-control" value="{{ \App\Models\StructuralPosition::max('order') + 1 }}" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Kapasitas Penugasan</label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="0" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-user text-indigo fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Tunggal (1 orang)</span>
                                            <span class="text-muted small">Input baru menggantikan</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-selectgroup-item w-100">
                                    <input type="radio" name="allows_multiple" value="1" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label d-flex align-items-center p-2 text-start">
                                        <span class="me-2"><i class="ti ti-users text-teal fs-2"></i></span>
                                        <span>
                                            <span class="fw-bold d-block text-dark">Jamak (&gt; 1 orang)</span>
                                            <span class="text-muted small">Tambah personil baris baru</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cakupan Tugas</label>
                            <select name="scope_type" class="form-select">
                                @foreach(\App\Models\StructuralPosition::SCOPES as $skVal => $skLbl)
                                    <option value="{{ $skVal }}">{{ $skLbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Masa Jabatan Default</label>
                            <select name="default_tenure_years" class="form-select">
                                @foreach(\App\Models\StructuralPosition::TENURE_OPTIONS as $tYr)
                                    <option value="{{ $tYr }}" {{ $tYr === 1 ? 'selected' : '' }}>{{ $tYr }} Tahun</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <input type="text" name="category" class="form-control" placeholder="Contoh: Kesiswaan, Kepegawaian, Sarpras...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan / Uraian Tugas</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Uraian singkat tupoksi penugasan operasional..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label fw-bold">Status Tugas Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">Simpan Master Tugas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Penugasan Operasional Bebas -->
<div class="modal fade" id="modalAssignOperational" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('teacher-assignments.store-operational') }}" method="POST">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-settings-cog me-1 text-azure"></i> Tambah Penugasan Operasional
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Tugas Operasional</label>
                        <select name="structural_position_id" id="selectOperationalPosition" class="form-select" required>
                            <option value="">-- Pilih Tugas Operasional --</option>
                            @foreach($operationalPositionChoices as $op)
                                @php
                                    $opCurrentAsgn = $op->activeAssignments->first();
                                @endphp
                                <option value="{{ $op->id }}" 
                                    data-allows-multiple="{{ $op->allows_multiple ? '1' : '0' }}"
                                    data-code="{{ $op->code ?? '-' }}"
                                    data-current-name="{{ $opCurrentAsgn?->teacher?->name ?? '' }}"
                                    data-current-nigs="{{ $opCurrentAsgn?->teacher?->nigs ? 'NIGS: ' . $opCurrentAsgn->teacher->nigs : '' }}"
                                    {{ $positionId == $op->id ? 'selected' : '' }}>
                                    {{ $op->name }} ({{ $op->category ?? 'Umum' }}) — [{{ $op->allows_multiple ? 'Jamak' : 'Tunggal' }}]
                                </option>
                            @endforeach
                        </select>
                        <div id="alertOperationalCapacity" class="mt-2 d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Personil / GTK</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Pegawai yang Ditugaskan --</option>
                            @foreach($allTeachers as $at)
                                <option value="{{ $at->id }}" {{ $teacherId == $at->id ? 'selected' : '' }}>
                                    {{ $at->name }} ({{ $at->position ?? $at->role?->name ?? 'GTK' }}){{ $at->nip ? ' • NIP: ' . $at->nip : ($at->nigs ? ' • NIGS: ' . $at->nigs : '') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor SK Penugasan</label>
                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                            <input type="date" name="sk_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">TMT Mulai Berlaku</label>
                            <input type="date" name="valid_from" class="form-control" value="{{ date('Y-m-d') }}">
                            <div class="form-text small text-muted">Default: tanggal SK</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Masa Berlaku Sampai (Opsional)</label>
                            <input type="date" name="valid_until" class="form-control">
                            <div class="form-text small text-muted">Saran master: tugas terkait. Kosongkan jika belum diketahui / SK tahunan.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Structural Position Capacity Alert
    const posSelect = document.getElementById('selectStructuralPosition');
    const alertBox = document.getElementById('alertPositionCapacity');

    if (posSelect && alertBox) {
        function updateCapacityAlert() {
            const opt = posSelect.options[posSelect.selectedIndex];
            if (!opt || !opt.value) {
                alertBox.className = 'mt-2 d-none';
                alertBox.innerHTML = '';
                return;
            }

            const allowsMultiple = opt.getAttribute('data-allows-multiple') === '1';
            const code = opt.getAttribute('data-code') || '-';
            const curName = opt.getAttribute('data-current-name');
            const curNigs = opt.getAttribute('data-current-nigs');

            if (!allowsMultiple) {
                if (curName) {
                    alertBox.className = 'mt-2 alert alert-warning py-2 px-3 small';
                    alertBox.innerHTML = '<div class="fw-bold mb-1"><i class="ti ti-alert-triangle text-warning me-1"></i> Kapasitas Penugasan: Tunggal (hanya 1 orang)</div>' +
                        '<div>Kode: <code>' + code + '</code> &bull; Saat ini dijabat oleh: <strong>' + curName + '</strong> ' + (curNigs ? '(' + curNigs + ')' : '') + '.</div>' +
                        '<div class="text-danger mt-1"><i class="ti ti-info-circle me-1"></i> Menyimpan form ini akan <strong>menggantikan</strong> pejabat di atas.</div>';
                } else {
                    alertBox.className = 'mt-2 alert alert-info py-2 px-3 small';
                    alertBox.innerHTML = '<div><i class="ti ti-info-circle me-1"></i> <strong>Kapasitas Penugasan: Tunggal (hanya 1 orang)</strong> &bull; Kode: <code>' + code + '</code> (Belum ada pejabat aktif).</div>';
                }
            } else {
                alertBox.className = 'mt-2 alert alert-teal py-2 px-3 small';
                alertBox.innerHTML = '<div><i class="ti ti-users me-1 text-teal"></i> <strong>Kapasitas Penugasan: Jamak (bisa lebih 1 orang)</strong> &bull; Kode: <code>' + code + '</code>. Penugasan baru akan ditambahkan ke <strong>baris baru</strong>.</div>';
            }
        }

        posSelect.addEventListener('change', updateCapacityAlert);
        if (posSelect.value) {
            updateCapacityAlert();
        }
    }

    // Operational Position Capacity Alert
    const opSelect = document.getElementById('selectOperationalPosition');
    const opAlertBox = document.getElementById('alertOperationalCapacity');

    if (opSelect && opAlertBox) {
        function updateOpCapacityAlert() {
            const opt = opSelect.options[opSelect.selectedIndex];
            if (!opt || !opt.value) {
                opAlertBox.className = 'mt-2 d-none';
                opAlertBox.innerHTML = '';
                return;
            }

            const allowsMultiple = opt.getAttribute('data-allows-multiple') === '1';
            const code = opt.getAttribute('data-code') || '-';
            const curName = opt.getAttribute('data-current-name');
            const curNigs = opt.getAttribute('data-current-nigs');

            if (!allowsMultiple) {
                if (curName) {
                    opAlertBox.className = 'mt-2 alert alert-warning py-2 px-3 small';
                    opAlertBox.innerHTML = '<div class="fw-bold mb-1"><i class="ti ti-alert-triangle text-warning me-1"></i> Kapasitas Penugasan: Tunggal (hanya 1 orang)</div>' +
                        '<div>Kode: <code>' + code + '</code> &bull; Saat ini ditugaskan kepada: <strong>' + curName + '</strong> ' + (curNigs ? '(' + curNigs + ')' : '') + '.</div>' +
                        '<div class="text-danger mt-1"><i class="ti ti-info-circle me-1"></i> Menyimpan form ini akan <strong>menggantikan</strong> personil di atas.</div>';
                } else {
                    opAlertBox.className = 'mt-2 alert alert-info py-2 px-3 small';
                    opAlertBox.innerHTML = '<div><i class="ti ti-info-circle me-1"></i> <strong>Kapasitas Penugasan: Tunggal (hanya 1 orang)</strong> &bull; Kode: <code>' + code + '</code> (Belum ada personil aktif).</div>';
                }
            } else {
                opAlertBox.className = 'mt-2 alert alert-teal py-2 px-3 small';
                opAlertBox.innerHTML = '<div><i class="ti ti-users me-1 text-teal"></i> <strong>Kapasitas Penugasan: Jamak (bisa lebih 1 orang)</strong> &bull; Kode: <code>' + code + '</code>. Penugasan baru akan ditambahkan ke <strong>baris baru</strong>.</div>';
            }
        }

        opSelect.addEventListener('change', updateOpCapacityAlert);
        if (opSelect.value) {
            updateOpCapacityAlert();
        }
    }

    // Live Workload Calculator for BK Assignments (Target 1:150)
    const bkCheckboxes = document.querySelectorAll('.bk-class-checkbox');
    const bkWorkloadBadge = document.getElementById('bkWorkloadBadge');
    const bkWorkloadProgress = document.getElementById('bkWorkloadProgress');
    const bkWorkloadStatus = document.getElementById('bkWorkloadStatus');

    if (bkCheckboxes.length > 0 && bkWorkloadBadge && bkWorkloadProgress && bkWorkloadStatus) {
        function updateBkWorkload() {
            let totalStudents = 0;
            let selectedCount = 0;

            bkCheckboxes.forEach(cb => {
                if (cb.checked) {
                    selectedCount++;
                    totalStudents += parseInt(cb.getAttribute('data-students') || '0', 10);
                }
            });

            if (selectedCount === 0) {
                bkWorkloadBadge.className = 'badge bg-purple-lt';
                bkWorkloadBadge.textContent = 'Seluruh Rombel (Umum)';
                bkWorkloadProgress.style.width = '100%';
                bkWorkloadProgress.className = 'progress-bar bg-purple';
                bkWorkloadStatus.className = 'fw-semibold text-purple';
                bkWorkloadStatus.textContent = 'Membina Seluruh Siswa Sekolah';
                return;
            }

            const percentage = Math.min(Math.round((totalStudents / 150) * 100), 100);
            bkWorkloadProgress.style.width = percentage + '%';

            if (totalStudents >= 130 && totalStudents <= 165) {
                bkWorkloadBadge.className = 'badge bg-success text-white fw-bold';
                bkWorkloadProgress.className = 'progress-bar bg-success';
                bkWorkloadStatus.className = 'fw-bold text-success';
                bkWorkloadStatus.textContent = 'Beban Ideal (' + percentage + '% dari target 150)';
            } else if (totalStudents < 130) {
                bkWorkloadBadge.className = 'badge bg-warning-lt fw-bold';
                bkWorkloadProgress.className = 'progress-bar bg-warning';
                bkWorkloadStatus.className = 'fw-semibold text-warning';
                bkWorkloadStatus.textContent = 'Di Bawah Standar (' + percentage + '% dari target 150)';
            } else {
                bkWorkloadBadge.className = 'badge bg-danger text-white fw-bold';
                bkWorkloadProgress.className = 'progress-bar bg-danger';
                bkWorkloadStatus.className = 'fw-bold text-danger';
                bkWorkloadStatus.textContent = 'Beban Berlebih / Overload (' + totalStudents + '/150 Siswa)';
            }

            bkWorkloadBadge.textContent = totalStudents + ' Siswa (' + selectedCount + ' Rombel)';
        }

        bkCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBkWorkload);
        });

        updateBkWorkload();
    }
});
</script>
@endpush

@endsection
