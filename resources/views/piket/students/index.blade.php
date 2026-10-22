@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Master Data Siswa
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-school text-primary me-2 fs-1"></i>
                    Data Siswa Sekolah
                </h2>
                <div class="text-secondary mt-1">
                    Database induk siswa per rombel untuk integrasi perizinan piket, keterlambatan, dan buku kasus.
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('piket.students.template') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-download me-1"></i> Download Format Template
                </a>
                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-import-student">
                    <i class="ti ti-file-upload me-1"></i> Import Siswa (Excel/CSV)
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-student">
                    <i class="ti ti-plus me-1"></i> Tambah Siswa Manual
                </button>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form action="{{ route('piket.students.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="classroom_id" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Cari nama siswa, NIS, atau NISN...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    @if($classroomId || $search)
                        <a href="{{ route('piket.students.index') }}" class="btn btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Students -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>NISN / NIS</th>
                        <th>Nama Siswa</th>
                        <th>L/P</th>
                        <th>Kelas & Wali Kelas</th>
                        <th>No. Kontak Orang Tua</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $idx => $s)
                        <tr>
                            <td>{{ $students->firstItem() + $idx }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $s->nis ?: '-' }}</div>
                                <span class="text-muted small">NISN: {{ $s->nisn ?: '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $s->name }}</div>
                                @if($s->phone_student)
                                    <span class="text-muted small"><i class="ti ti-phone me-1"></i>{{ $s->phone_student }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $s->gender === 'P' ? 'bg-pink text-white' : 'bg-blue text-white' }} px-2 py-1">
                                    {{ $s->gender }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-blue-lt fs-4 mb-1">{{ $s->classroom?->name ?? 'Belum Ditentukan' }}</span>
                                @if($s->classroom?->homeroomTeacher)
                                    <div class="text-muted small">Wali: {{ $s->classroom->homeroomTeacher->name }}</div>
                                @endif
                            </td>
                            <td>
                                @if($s->phone_parent)
                                    <span class="fw-bold text-dark small"><i class="ti ti-phone-call me-1 text-success"></i>{{ $s->phone_parent }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($s->is_active)
                                    <span class="badge bg-success text-white">Aktif</span>
                                @else
                                    <span class="badge bg-secondary text-white">Non-Aktif</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-edit-student-{{ $s->id }}">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <form action="{{ route('piket.students.destroy', $s) }}" method="POST" onsubmit="return confirm('Hapus siswa {{ $s->name }}?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Edit Siswa -->
                        <div class="modal fade" id="modal-edit-student-{{ $s->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('piket.students.update', $s) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Data Siswa</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label">NIS</label>
                                                    <input type="text" name="nis" class="form-control" value="{{ $s->nis }}">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">NISN</label>
                                                    <input type="text" name="nisn" class="form-control" value="{{ $s->nisn }}">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Nama Siswa</label>
                                                <input type="text" name="name" class="form-control" value="{{ $s->name }}" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label required">Jenis Kelamin</label>
                                                    <select name="gender" class="form-select" required>
                                                        <option value="L" {{ $s->gender === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                                        <option value="P" {{ $s->gender === 'P' ? 'selected' : '' }}>Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label required">Kelas</label>
                                                    <select name="classroom_id" class="form-select" required>
                                                        @foreach($classrooms as $c)
                                                            <option value="{{ $c->id }}" {{ $s->classroom_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">No. HP Orang Tua / Wali</label>
                                                <input type="text" name="phone_parent" class="form-control" value="{{ $s->phone_parent }}">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">No. HP Siswa</label>
                                                <input type="text" name="phone_student" class="form-control" value="{{ $s->phone_student }}">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status Siswa</label>
                                                <select name="is_active" class="form-select">
                                                    <option value="1" {{ $s->is_active ? 'selected' : '' }}>Aktif</option>
                                                    <option value="0" {{ !$s->is_active ? 'selected' : '' }}>Non-Aktif / Pindah</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="ti ti-school fs-1 d-block mb-2"></i>
                                Belum ada data siswa. Silakan klik tombol <strong>"Import Siswa (Excel/CSV)"</strong> atau <strong>"Tambah Siswa Manual"</strong> di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div class="card-footer py-2">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Tambah Siswa Manual -->
<div class="modal fade" id="modal-add-student" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.students.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Data Siswa Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">NIS</label>
                            <input type="text" name="nis" class="form-control" placeholder="Contoh: 21001">
                        </div>
                        <div class="col-6">
                            <label class="form-label">NISN</label>
                            <input type="text" name="nisn" class="form-control" placeholder="10 digit NISN">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Nama Lengkap Siswa</label>
                        <input type="text" name="name" class="form-control" placeholder="Nama siswa" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required">Jenis Kelamin</label>
                            <select name="gender" class="form-select" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Kelas</label>
                            <select name="classroom_id" class="form-select" required>
                                <option value="">-- Pilih Kelas --</option>
                                @foreach($classrooms as $c)
                                    <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Kontak Orang Tua / Wali</label>
                        <input type="text" name="phone_parent" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. HP Siswa</label>
                        <input type="text" name="phone_student" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Catatan khusus...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Siswa -->
<div class="modal fade" id="modal-import-student" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.students.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Import Data Siswa (Excel / CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info border-0 mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Gunakan file Excel (.xlsx) atau CSV (.csv). Unduh <a href="{{ route('piket.students.template') }}" class="fw-bold alert-link">Format Template Excel di sini</a>.
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Pilih File Excel / CSV</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt,.xls" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas Default (Opsional jika di file tidak ada kolom kelas)</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">-- Sesuaikan dengan nama kelas di file --</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ti ti-upload me-1"></i> Mulai Import Data Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
