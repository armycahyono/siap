@extends('layouts.app')

@section('title', 'Guru Inval & Kelas Kosong')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @include('layouts._hero_header', [
        'title' => 'Guru Inval & Pengondisian Kelas Kosong',
        'subtitle' => 'Pencatatan guru yang berhalangan hadir, penugasan guru pengganti (inval), dan monitoring tugas kelas.',
        'icon' => 'ti ti-chalkboard',
        'category' => 'KONDUSIFITAS KBM & INVAL',
        'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-inval"><i class="ti ti-plus me-1"></i> Catat Kelas Kosong / Inval</button>'
    ])

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('piket.inval.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto fw-bold text-dark small">
                    <i class="ti ti-calendar me-1"></i> Tanggal:
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if($date)
                        <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-ghost-secondary">Hari Ini</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Inval -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h3 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-clipboard-list me-1 text-primary"></i> Daftar Kelas Kosong &amp; Inval
            </h3>
            <span class="badge bg-blue-lt px-3 py-1.5 fs-4">
                <i class="ti ti-calendar me-1"></i>{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal & Kelas</th>
                        <th>Guru Berhalangan</th>
                        <th>Mapel & Jam Pelajaran</th>
                        <th>Guru Inval / Pengganti</th>
                        <th>Alasan & Tugas Siswa</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invals as $inv)
                        <tr>
                            <td>
                                <span class="badge bg-blue-lt fs-3 mb-1">{{ $inv->classroom?->name ?? '-' }}</span>
                                <div class="text-muted small">
                                    <i class="ti ti-calendar me-1"></i>{{ $inv->journal?->date?->format('d/m/Y') }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-danger">{{ $inv->absentTeacher?->name ?? '-' }}</div>
                                <span class="badge bg-danger-lt">{{ $inv->reason }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $inv->subject?->name ?? 'Mata Pelajaran' }}</div>
                                <span class="text-secondary small">Jam ke-{{ $inv->period_start }} s.d {{ $inv->period_end }}</span>
                            </td>
                            <td>
                                @if($inv->invalTeacher)
                                    <div class="fw-bold text-success">{{ $inv->invalTeacher->name }}</div>
                                    <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i> Terisi</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="ti ti-alert-circle me-1"></i> Belum Ada Inval</span>
                                @endif
                            </td>
                            <td>
                                @if($inv->assignment_material)
                                    <div class="text-dark small"><strong>Tugas:</strong> {{ $inv->assignment_material }}</div>
                                @else
                                    <span class="text-muted small">Tidak ada tugas tertulis</span>
                                @endif
                                @if($inv->notes)
                                    <div class="text-muted small">{{ $inv->notes }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-edit-{{ $inv->id }}">
                                    <i class="ti ti-edit"></i> Edit Inval
                                </button>
                                <form action="{{ route('piket.inval.destroy', $inv) }}" method="POST" onsubmit="return confirm('Hapus data kelas inval ini?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Edit Inval -->
                        <div class="modal fade" id="modal-edit-{{ $inv->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('piket.inval.update', $inv) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Tugaskan Guru Inval ({{ $inv->classroom?->name }})</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Pilih Guru Pengganti / Inval</label>
                                                <select name="inval_teacher_id" class="form-select">
                                                    <option value="">-- Belum Ada Guru Pengganti --</option>
                                                    @foreach($teachers as $t)
                                                        <option value="{{ $t->id }}" {{ $inv->inval_teacher_id == $t->id ? 'selected' : '' }}>
                                                            {{ $t->name }} ({{ $t->formatted_id }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label">Jam Mulai</label>
                                                    <input type="number" name="period_start" class="form-control" value="{{ $inv->period_start }}" min="1" max="15" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Jam Selesai</label>
                                                    <input type="number" name="period_end" class="form-control" value="{{ $inv->period_end }}" min="1" max="15" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Alasan Guru Tidak Hadir</label>
                                                <input type="text" name="reason" class="form-control" value="{{ $inv->reason }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Materi / Tugas yang Ditinggalkan</label>
                                                <textarea name="assignment_material" class="form-control" rows="3">{{ $inv->assignment_material }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Catatan Tambahan</label>
                                                <input type="text" name="notes" class="form-control" value="{{ $inv->notes }}">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Inval</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="ti ti-school fs-1 d-block mb-2 text-success"></i>
                                Tidak ada catatan kelas kosong atau guru inval untuk tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invals->hasPages())
            <div class="card-footer py-2">
                {{ $invals->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Tambah Inval -->
<div class="modal fade" id="modal-add-inval" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.inval.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Catat Guru Kosong & Penugasan Inval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ $date ?: date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Kelas</label>
                        <select name="classroom_id" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Guru yang Berhalangan Hadir</label>
                        <select name="absent_teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru Berhalangan --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->formatted_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="subject_id" class="form-select">
                            <option value="">-- Pilih Mapel (Opsional) --</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label required">Mulai Jam Ke-</label>
                            <input type="number" name="period_start" class="form-control" value="1" min="1" max="15" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Sampai Jam Ke-</label>
                            <input type="number" name="period_end" class="form-control" value="2" min="1" max="15" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Alasan Berhalangan</label>
                        <select name="reason" class="form-select" required>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin Pribadi">Izin Pribadi</option>
                            <option value="Dinas Luar">Dinas Luar / Rapat Dinas</option>
                            <option value="Cuti">Cuti</option>
                            <option value="Pelatihan / Diklat">Pelatihan / Diklat</option>
                            <option value="Tanpa Keterangan">Tanpa Keterangan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tugaskan Guru Inval / Pengganti</label>
                        <select name="inval_teacher_id" class="form-select">
                            <option value="">-- Belum Ditentukan / Piket Mandiri --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->formatted_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Materi / Tugas yang Ditinggalkan</label>
                        <textarea name="assignment_material" class="form-control" rows="3" placeholder="Tuliskan tugas siswa yang ditinggalkan guru..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Inval</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
