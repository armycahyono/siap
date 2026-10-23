@extends('layouts.app')

@section('title', 'Penugasan Guru - Sistem Supervisi')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <h2 class="page-title fw-bold text-dark">Penugasan Guru (Mapel, Wali Kelas, Supervisor & Struktural)</h2>
        <div class="text-muted small">Kelola penugasan guru sebagai Wali Kelas, Pengampu Mata Pelajaran, Tim Supervisor, serta Pejabat Struktural Sekolah</div>
    </div>
</div>

@include('master.nav')

<!-- Sub-Tab Navigasi Penugasan -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom-0 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'mapel', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'mapel' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-notebook me-2 text-primary"></i>
                            Mapel Diampu (Guru Pengampu)
                            <span class="badge bg-primary-lt ms-2">{{ $assignments->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'walikelas' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-user-star me-2 text-success"></i>
                            Penugasan Wali Kelas
                            <span class="badge bg-success-lt ms-2">{{ $classrooms->whereNotNull('homeroom_teacher_id')->count() }}/{{ $classrooms->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'supervisor', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'supervisor' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-shield-check me-2 text-warning"></i>
                            Tim Supervisor Sekolah
                            <span class="badge bg-warning-lt ms-2">{{ $supervisors->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'struktural', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'struktural' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-briefcase me-2 text-indigo"></i>
                            Penugasan Struktural
                            <span class="badge bg-indigo-lt ms-2">{{ $structuralAssignments->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('teacher-assignments.index', ['tab' => 'piket', 'academic_year' => $academicYear]) }}" 
                           class="nav-link {{ $tab === 'piket' ? 'active fw-bold border-bottom-primary' : 'text-muted' }}">
                            <i class="ti ti-shield-check me-2 text-warning"></i>
                            Penugasan Piket
                            <span class="badge bg-warning-lt ms-2">{{ $piketSchedules->count() }}</span>
                        </a>
                    </li>
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
                                        <div class="text-muted" style="font-size: 0.72rem;">NIGS: {{ $tr->nigs_fix ?: ($tr->nigs ?: '-') }}</div>
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
                        <option value="{{ $t->id }}" {{ $teacherId == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->nigs_fix ?? '-' }})</option>
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
                        <div class="fw-semibold text-dark">{{ $assign->teacher?->nigs_fix ?? '-' }}</div>
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
                                <option value="{{ $t->id }}">{{ $t->name }} (NIGS: {{ $t->nigs_fix ?? '-' }})</option>
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
                            NIGS: {{ $tr->nigs_fix ?: ($tr->nigs ?: '-') }}
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
                                    <small class="text-muted">NIGS: {{ $cls->homeroomTeacher->nigs_fix ?? '-' }}</small>
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
                                                            {{ $t->name }} (NIGS: {{ $t->nigs_fix ?? '-' }})
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
                                <small class="text-muted">NIGS: {{ $spv->nigs_fix ?: '-' }}</small>
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
            <a href="{{ route('teacher-assignments.template-structural', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-download me-1"></i> Format Template
            </a>
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
                    <th style="width: 120px;">Kode</th>
                    <th>Nama Jabatan Struktural</th>
                    <th>Kategori</th>
                    <th>Pejabat / Guru yang Ditugaskan</th>
                    <th>Nomor SK & TMT</th>
                    <th style="width: 110px;" class="text-center">Tanda Tangan</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($structuralAssignments as $index => $pos)
                @php
                    $currentAsgn = $pos->activeAssignments->first();
                    $catClass = match($pos->category) {
                        'Pimpinan' => 'bg-danger-lt',
                        'Wakil Kepala Sekolah' => 'bg-indigo-lt',
                        'Kepala Program Keahlian' => 'bg-cyan-lt',
                        'Kepala Unit & Fasilitas' => 'bg-teal-lt',
                        'Tata Kelola & Keuangan' => 'bg-purple-lt',
                        default => 'bg-secondary-lt'
                    };
                @endphp
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $structuralAssignments->firstItem() + $index }}</td>
                    <td>
                        <span class="badge bg-blue-lt fw-bold font-monospace">{{ $pos->code ?? '-' }}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $pos->name }}</div>
                        @if($pos->description)
                            <div class="text-muted small">{{ $pos->description }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $catClass }}">{{ $pos->category ?? 'Umum' }}</span>
                    </td>
                    <td>
                        @if($currentAsgn && $currentAsgn->teacher)
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-indigo-lt rounded-circle me-2 fw-bold">
                                    {{ strtoupper(substr($currentAsgn->teacher->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $currentAsgn->teacher->name }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        NIGS: {{ $currentAsgn->teacher->nigs_fix ?? '-' }} | NIP: {{ $currentAsgn->teacher->nip ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-lt text-secondary fst-italic">Belum Ditugaskan</span>
                                <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAssignPos{{ $pos->id }}">
                                    <i class="ti ti-plus me-1"></i> Tugaskan
                                </button>
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($currentAsgn && ($currentAsgn->sk_number || $currentAsgn->sk_date || $currentAsgn->notes))
                            @if($currentAsgn->sk_number)
                                <div class="fw-semibold text-dark small text-nowrap">
                                    <i class="ti ti-file-certificate me-1 text-primary"></i>{{ $currentAsgn->sk_number }}
                                </div>
                            @endif
                            @if($currentAsgn->sk_date)
                                <div class="text-muted small text-nowrap" style="font-size: 0.72rem;">
                                    <i class="ti ti-calendar me-1"></i>TMT: {{ $currentAsgn->sk_date->format('d/m/Y') }}
                                </div>
                            @endif
                            @if($currentAsgn->notes)
                                <div class="text-secondary small fst-italic mt-1" style="font-size: 0.72rem;" title="{{ $currentAsgn->notes }}">
                                    <i class="ti ti-notes me-1"></i>{{ Str::limit($currentAsgn->notes, 35) }}
                                </div>
                            @endif
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pos->signature_image_path)
                            <a href="{{ asset('storage/' . $pos->signature_image_path) }}" target="_blank" title="Lihat Tanda Tangan">
                                <img src="{{ asset('storage/' . $pos->signature_image_path) }}" alt="TTD {{ $pos->name }}" style="height: 38px; max-width: 80px; object-fit: contain;" class="border p-1 rounded bg-white shadow-xs">
                            </a>
                        @else
                            <span class="text-muted small fst-italic">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pos->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAssignPos{{ $pos->id }}" title="{{ $currentAsgn ? 'Ubah Pejabat / SK' : 'Tugaskan Guru' }}">
                                <i class="ti {{ $currentAsgn ? 'ti-user-edit' : 'ti-user-plus' }} me-1"></i>
                                {{ $currentAsgn ? 'Ubah' : 'Tugaskan' }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditPos{{ $pos->id }}" title="Edit Master Jabatan">
                                <i class="ti ti-edit"></i>
                            </button>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                    @if($currentAsgn)
                                    <form action="{{ route('teacher-assignments.destroy-structural', $currentAsgn) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mencabut penugasan guru ini dari jabatan {{ $pos->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-warning">
                                            <i class="ti ti-user-x me-2"></i> Cabut Penugasan Guru
                                        </button>
                                    </form>
                                    <div class="dropdown-divider"></div>
                                    @endif
                                    <form action="{{ route('structural-positions.destroy', $pos) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus master jabatan {{ $pos->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="ti ti-trash me-2"></i> Hapus Master Jabatan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Modal Tugaskan / Ubah Guru untuk Jabatan ini -->
                <div class="modal fade" id="modalAssignPos{{ $pos->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('teacher-assignments.store-structural') }}" method="POST">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                <input type="hidden" name="structural_position_id" value="{{ $pos->id }}">
                                @if($currentAsgn)
                                    <input type="hidden" name="assignment_id" value="{{ $currentAsgn->id }}">
                                @endif
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-briefcase me-1 text-indigo"></i>
                                        {{ $currentAsgn ? 'Ubah Penugasan' : 'Tugaskan Guru' }}: {{ $pos->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required fw-bold">Pilih Guru / Pejabat</label>
                                        <select name="teacher_id" class="form-select" required>
                                            <option value="">-- Pilih Guru yang Ditugaskan --</option>
                                            @foreach($allTeachers as $at)
                                                <option value="{{ $at->id }}" {{ ($currentAsgn?->teacher_id == $at->id) ? 'selected' : '' }}>
                                                    {{ $at->name }} {{ $at->nip ? '(NIP: ' . $at->nip . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nomor SK Pengangkatan</label>
                                            <input type="text" name="sk_number" class="form-control" value="{{ old('sk_number', $currentAsgn?->sk_number) }}" placeholder="Contoh: 421.5/028/SMK-PK/2026">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tanggal SK / TMT</label>
                                            <input type="date" name="sk_date" class="form-control" value="{{ old('sk_date', $currentAsgn?->sk_date ? $currentAsgn->sk_date->format('Y-m-d') : date('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Keterangan / Catatan Tambahan (Opsional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tugas pokok atau wewenang tambahan...">{{ old('notes', $currentAsgn?->notes) }}</textarea>
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
                                                <img src="{{ asset('storage/' . $pos->signature_image_path) }}" alt="TTD" style="height: 35px; max-width: 80px; object-fit: contain;">
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
                    <td colspan="9" class="text-center py-5 text-muted">
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
                        <select name="structural_position_id" class="form-select" required>
                            <option value="">-- Pilih Jabatan Struktural --</option>
                            @foreach($structuralPositions as $sp)
                                <option value="{{ $sp->id }}" {{ $positionId == $sp->id ? 'selected' : '' }}>
                                    {{ $sp->name }} ({{ $sp->category ?? 'Umum' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih Guru / Pegawai</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru yang Ditugaskan --</option>
                            @foreach($allTeachers as $at)
                                <option value="{{ $at->id }}" {{ $teacherId == $at->id ? 'selected' : '' }}>
                                    {{ $at->name }} {{ $at->nip ? '(NIP: ' . $at->nip . ')' : '' }}
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
@elseif($tab === 'piket')
<!-- ======================= TAB 5: PENUGASAN PIKET ======================= -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <div>
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-shield-check text-warning me-2"></i>
                        Rekapitulasi Penugasan Guru Piket (TP {{ $academicYear }})
                    </h3>
                    <div class="text-secondary small mt-1">
                        Daftar guru yang ditugaskan sebagai petugas piket harian. Untuk pengelolaan operasional harian, kunjungi modul piket.
                    </div>
                </div>
                <div>
                    <a href="{{ route('piket.schedules.index', ['academic_year' => $academicYear]) }}" class="btn btn-primary">
                        <i class="ti ti-settings me-1"></i> Kelola Jadwal Piket Lengkap
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Hari Tugas</th>
                            <th>Nama Guru Petugas</th>
                            <th>Peran</th>
                            <th>Pos / Lokasi Tugas</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($piketSchedules as $idx => $ps)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>
                                    <span class="badge bg-primary text-white px-2 py-1">{{ $ps->day }}</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-3">{{ $ps->teacher?->name ?? '-' }}</div>
                                    <span class="text-muted small">{{ $ps->teacher?->formatted_id ?? '-' }}</span>
                                </td>
                                <td>
                                    @if($ps->role === 'koordinator')
                                        <span class="badge bg-purple text-white"><i class="ti ti-crown me-1"></i> Koordinator</span>
                                    @else
                                        <span class="badge bg-secondary-lt">Anggota</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-secondary small">{{ $ps->duty_location ?: 'Meja Piket Utama' }}</span>
                                </td>
                                <td class="text-center">
                                    @if($ps->is_active)
                                        <span class="badge bg-success-lt">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary-lt">Non-Aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="ti ti-calendar-off fs-1 d-block mb-2"></i>
                                    Belum ada penugasan guru piket untuk Tahun Pelajaran {{ $academicYear }}.
                                    <div class="mt-2">
                                        <a href="{{ route('piket.schedules.index') }}" class="btn btn-sm btn-outline-primary">
                                            + Buat Jadwal Petugas Piket
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endif

@endsection
