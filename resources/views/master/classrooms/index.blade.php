@extends('layouts.app')

@section('title', 'Data Pokok Tingkat & Rombel - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $classroomActions = '<button type="button" class="btn btn-sm btn-outline-light shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateGrade"><i class="ti ti-layers-union me-1"></i> Tambah Tingkat</button> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateClass"><i class="ti ti-plus me-1"></i> Tambah Rombel</button>';
@endphp
@include('layouts._hero_header', [
    'title' => 'Data Pokok: Tingkat & Kelas (Rombel)',
    'subtitle' => 'Kelola rombongan belajar per tingkat, jurusan, dan penetapan wali kelas binaan TP ' . $academicYear . '.',
    'icon' => 'ti ti-layout-grid',
    'category' => 'DATA POKOK & STRUKTUR',
    'academicYear' => $academicYear,
    'stats' => [
        ['label' => 'Total Rombel', 'value' => $classrooms->total(), 'class' => 'text-white'],
        ['label' => 'Tingkat', 'value' => $gradeLevels->count(), 'class' => 'text-info'],
    ],
    'actions' => $classroomActions
])

@include('master.nav')

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('classrooms.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama rombel (cth: X TKR 1)..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="grade_level_id" class="form-select">
                    <option value="">-- Semua Tingkat --</option>
                    @foreach($gradeLevels as $gl)
                        <option value="{{ $gl->id }}" {{ $gradeId == $gl->id ? 'selected' : '' }}>{{ $gl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-select">
                    <option value="">-- Semua Jurusan --</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" {{ $deptId == $d->id ? 'selected' : '' }}>{{ $d->code }} - {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="academic_year" class="form-control" placeholder="TP 2025/2026" value="{{ $academicYear }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-light w-100">Filter</button>
                @if($search || $gradeId || $deptId || $academicYear != \App\Models\SchoolSetting::getSetting()->academic_year)
                    <a href="{{ route('classrooms.index') }}" class="btn btn-icon btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Nama Rombel</th>
                    <th>Tingkat</th>
                    <th>Program Keahlian (Jurusan)</th>
                    <th>Wali Kelas</th>
                    <th>Tahun Pelajaran</th>
                    <th class="text-center">Jml Mapel Aktif</th>
                    <th class="text-center">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classrooms as $cls)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt text-blue rounded me-2 fw-bold">
                                {{ $cls->gradeLevel?->level ?? 'X' }}
                            </span>
                            <div class="fw-bold text-dark fs-3">{{ $cls->name }}</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary-lt fw-bold">{{ $cls->gradeLevel?->name ?? '-' }}</span>
                    </td>
                    <td>
                        @if($cls->department)
                            <span class="badge bg-azure-lt fw-bold">{{ $cls->department->code }}</span>
                            <span class="small text-muted ms-1">{{ $cls->department->name }}</span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td>
                        @if($cls->homeroomTeacher)
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-xs bg-success-lt rounded-circle me-2">
                                    <i class="ti ti-user-check"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $cls->homeroomTeacher->name }}</div>
                                    <small class="text-muted">NIGS: {{ $cls->homeroomTeacher->nigs_fix ?? '-' }}</small>
                                </div>
                            </div>
                        @else
                            <span class="badge bg-warning-lt text-warning fw-semibold">
                                <i class="ti ti-alert-circle me-1"></i> Belum Ada Wali Kelas
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="text-dark small">{{ $cls->academic_year }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-purple-lt fw-bold">{{ $cls->subject_assignments_count }} Mapel</span>
                    </td>
                    <td class="text-center">
                        @if($cls->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-danger-lt">Non-Aktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditClass{{ $cls->id }}">
                                <i class="ti ti-edit"></i>
                            </button>
                            <form action="{{ route('classrooms.destroy', $cls) }}" method="POST" onsubmit="return confirm('Hapus rombel {{ $cls->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Modal Edit Rombel -->
                        <div class="modal fade text-start" id="modalEditClass{{ $cls->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('classrooms.update', $cls) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Rombel: {{ $cls->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Nama Rombel / Kelas</label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name', $cls->name) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Tingkat</label>
                                                <select name="grade_level_id" class="form-select" required>
                                                    @foreach($gradeLevels as $gl)
                                                        <option value="{{ $gl->id }}" {{ $cls->grade_level_id == $gl->id ? 'selected' : '' }}>
                                                            {{ $gl->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Program Keahlian (Jurusan)</label>
                                                <select name="department_id" class="form-select">
                                                    <option value="">-- Umum / Tanpa Jurusan --</option>
                                                    @foreach($departments as $d)
                                                        <option value="{{ $d->id }}" {{ $cls->department_id == $d->id ? 'selected' : '' }}>
                                                            {{ $d->code }} - {{ $d->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Wali Kelas</label>
                                                <select name="homeroom_teacher_id" class="form-select">
                                                    <option value="">-- Belum Ada Wali Kelas --</option>
                                                    @foreach($teachers as $t)
                                                        <option value="{{ $t->id }}" {{ $cls->homeroom_teacher_id == $t->id ? 'selected' : '' }}>
                                                            {{ $t->name }} (NIGS: {{ $t->nigs_fix ?? '-' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Tahun Pelajaran</label>
                                                <input type="text" name="academic_year" class="form-control" value="{{ old('academic_year', $cls->academic_year) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $cls->is_active ? 'checked' : '' }}>
                                                    <span class="form-check-label">Status Aktif</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 mb-2 d-block"></i>
                        Belum ada kelas / rombel yang terdaftar untuk filter ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($classrooms->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $classrooms->links() }}
    </div>
    @endif
</div>

<!-- Modal Tambah Rombel -->
<div class="modal fade" id="modalCreateClass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('classrooms.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Rombel Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nama Rombel / Kelas (cth: X TKR 1, XI TKJ 2)</label>
                        <input type="text" name="name" class="form-control" placeholder="X TKR 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Tingkat</label>
                        <select name="grade_level_id" class="form-select" required>
                            @foreach($gradeLevels as $gl)
                                <option value="{{ $gl->id }}">{{ $gl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program Keahlian (Jurusan)</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Umum / Tanpa Jurusan --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} - {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wali Kelas (Penugasan Awal)</label>
                        <select name="homeroom_teacher_id" class="form-select">
                            <option value="">-- Pilih Guru (Opsional) --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} (NIGS: {{ $t->nigs_fix ?? '-' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tahun Pelajaran</label>
                        <input type="text" name="academic_year" class="form-control" value="{{ \App\Models\SchoolSetting::getSetting()->academic_year }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label">Status Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Rombel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Tingkat -->
<div class="modal fade" id="modalCreateGrade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('classrooms.grade-levels.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Tingkat Kelas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Kode Tingkat (cth: X, XI, XII, XIII)</label>
                        <input type="text" name="level" class="form-control" placeholder="X" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Label Tingkat (cth: Tingkat X / Sepuluh)</label>
                        <input type="text" name="name" class="form-control" placeholder="Tingkat X (Sepuluh)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Urut</label>
                        <input type="number" name="order_index" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-secondary">Simpan Tingkat</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
