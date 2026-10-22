@extends('layouts.app')

@section('title', 'Hasil Terjemahan Jadwal: ' . $upload->title)

@section('content')
<div class="row g-3 mb-3 align-items-center justify-content-between">
    <div class="col-md-7">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('kurikulum.timetables.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Kembali ke Arsip
            </a>
            <span class="badge bg-blue-lt fw-bold">{{ $upload->academic_year }} &bull; Semester {{ $upload->semester }}</span>
            @if($upload->is_active)
                <span class="badge bg-teal text-white"><i class="ti ti-check me-1"></i> Sedang Aktif di Jadwal</span>
            @else
                <span class="badge bg-secondary-lt text-muted">Draft / Belum Diterapkan</span>
            @endif
        </div>
        <h2 class="page-title text-navy fw-bold m-0" style="color: #102d52;">
            <i class="ti ti-table-options me-2 text-primary"></i> Tabel Hasil Terjemahan Jadwal
        </h2>
        <div class="text-muted small mt-1">
            Dokumen Sumber: <strong>{{ $upload->original_filename }}</strong> &mdash; Periksa dan koreksi data sebelum diterapkan menjadi jadwal aktif.
        </div>
    </div>
    <div class="col-md-5 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
        <form action="{{ route('kurikulum.timetables.translation.reparse', $upload) }}" method="POST" onsubmit="return confirm('Terjemahkan ulang dari file PDF asli? Data koreksi manual yang belum diterapkan akan di-reset sesuai isi PDF asli.')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary" title="Ekstrak ulang dari file PDF asli">
                <i class="ti ti-refresh me-1"></i> Terjemahkan Ulang PDF
            </button>
        </form>

        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddSlot">
            <i class="ti ti-plus me-1"></i> Tambah Slot
        </button>

        <form action="{{ route('kurikulum.timetables.translation.apply', $upload) }}" method="POST" onsubmit="return confirm('Terapkan hasil terjemahan ini menjadi Jadwal Pelajaran Aktif? Jadwal aktif sebelumnya untuk semester ini akan digantikan.')">
            @csrf
            <button type="submit" class="btn btn-success shadow-sm" {{ $invalidItems > 0 ? 'title=Peringatan: Masih ada slot yang perlu dikoreksi' : '' }}>
                <i class="ti ti-checks me-1"></i> Terapkan ke Jadwal Aktif
            </button>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar bg-blue-lt text-blue rounded-3 me-3">
                    <i class="ti ti-clock fs-2"></i>
                </span>
                <div>
                    <div class="text-muted small">Total Slot Jam</div>
                    <div class="fw-bold fs-3 text-dark">{{ $totalItems }} <span class="fs-5 fw-normal text-muted">Slot</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar bg-purple-lt text-purple rounded-3 me-3">
                    <i class="ti ti-layout-grid fs-2"></i>
                </span>
                <div>
                    <div class="text-muted small">Total Rombel Kelas</div>
                    <div class="fw-bold fs-3 text-dark">{{ $classes->count() }} <span class="fs-5 fw-normal text-muted">Rombel</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar bg-green-lt text-green rounded-3 me-3">
                    <i class="ti ti-circle-check fs-2"></i>
                </span>
                <div>
                    <div class="text-muted small">Slot Valid (Siap Aktif)</div>
                    <div class="fw-bold fs-3 text-success">{{ $validItems }} <span class="fs-5 fw-normal text-muted">Slot</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <span class="avatar {{ $invalidItems > 0 ? 'bg-danger-lt text-danger' : 'bg-secondary-lt text-secondary' }} rounded-3 me-3">
                    <i class="ti {{ $invalidItems > 0 ? 'ti-alert-circle' : 'ti-circle-minus' }} fs-2"></i>
                </span>
                <div>
                    <div class="text-muted small">Perlu Koreksi</div>
                    <div class="fw-bold fs-3 {{ $invalidItems > 0 ? 'text-danger' : 'text-secondary' }}">{{ $invalidItems }} <span class="fs-5 fw-normal text-muted">Slot</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($invalidItems > 0)
<div class="alert alert-warning d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
    <i class="ti ti-alert-triangle fs-2 me-3 text-warning"></i>
    <div>
        <strong>Perhatian:</strong> Ditemukan <strong>{{ $invalidItems }} slot terjemahan</strong> yang belum cocok dengan master data (rombel, mapel, atau guru). Anda dapat mengklik tombol <strong>Edit</strong> pada baris terkait untuk mengoreksinya sebelum diterapkan ke jadwal aktif.
    </div>
</div>
@endif

<!-- Filter & Search Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('kurikulum.timetables.translation', $upload) }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1 text-muted">Pilih Kelas / Rombel</label>
                <select name="class_name" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Rombel ({{ $classes->count() }} Kelas) --</option>
                    @foreach($classes as $c)
                        <option value="{{ $c }}" {{ $selectedClass === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1 text-muted">Hari</label>
                <select name="day" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Hari --</option>
                    @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $d)
                        <option value="{{ $d }}" {{ $selectedDay === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1 text-muted">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Status --</option>
                    <option value="valid" {{ $selectedStatus === 'valid' ? 'selected' : '' }}>Hanya Valid ({{ $validItems }})</option>
                    <option value="invalid" {{ $selectedStatus === 'invalid' ? 'selected' : '' }}>Perlu Koreksi ({{ $invalidItems }})</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold mb-1 text-muted">Cari Mapel / Guru</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Ketik kode mapel atau nama guru..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100" title="Terapkan Filter">
                    <i class="ti ti-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Main Table: Translation Items -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold text-dark m-0">
            <i class="ti ti-list-check me-2 text-primary"></i> Daftar Slot Terjemahan
            @if($selectedClass)
                <span class="badge bg-purple-lt text-purple ms-2">Kelas {{ $selectedClass }}</span>
            @endif
        </h4>
        <span class="text-muted small">
            Menampilkan {{ $items->firstItem() ?? 0 }} - {{ $items->lastItem() ?? 0 }} dari {{ $items->total() }} slot
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead class="bg-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th style="width: 110px;">Hari</th>
                    <th style="width: 120px;">Jam Ke</th>
                    <th style="width: 130px;">Waktu (WIB)</th>
                    <th style="width: 130px;">Rombel</th>
                    <th>Mata Pelajaran</th>
                    <th>Guru Pengampu</th>
                    <th class="text-center" style="width: 120px;">Status</th>
                    <th class="text-end" style="width: 110px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $idx => $slot)
                    <tr class="{{ ! $slot->is_valid ? 'bg-danger-lt' : '' }}">
                        <td class="text-muted small">{{ $items->firstItem() + $idx }}</td>
                        <td>
                            <span class="badge bg-secondary-lt fw-bold">{{ $slot->day }}</span>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt text-blue px-2 py-1 fw-bold">
                                Jam {{ $slot->start_period }} - {{ $slot->end_period }}
                            </span>
                            <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                {{ ($slot->end_period - $slot->start_period) + 1 }} JP
                            </div>
                        </td>
                        <td>
                            <span class="text-dark fw-semibold" style="font-size: 0.8rem;">
                                {{ $slot->start_time ?: '-' }} &mdash; {{ $slot->end_time ?: '-' }}
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold text-purple">
                                {{ $slot->class_name }}
                            </span>
                            @if($slot->classroom)
                                <div class="text-muted small" style="font-size: 0.7rem;">ID: #{{ $slot->classroom->id }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-bold text-dark">
                                <span class="badge bg-azure text-white me-1">{{ $slot->subject_code }}</span>
                                {{ $slot->subject?->name ?? 'Mata Pelajaran Tidak Terdaftar' }}
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">
                                @if($slot->teacher)
                                    <i class="ti ti-user me-1 text-primary"></i> {{ $slot->teacher->name }}
                                @else
                                    <span class="text-danger fw-bold"><i class="ti ti-user-x me-1"></i> Inisial '{{ $slot->teacher_nickname }}' belum terhubung</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            @if($slot->is_valid)
                                <span class="badge bg-teal text-white px-2 py-1" style="font-size: 0.72rem;">
                                    <i class="ti ti-check me-1"></i> Valid
                                </span>
                            @else
                                <span class="badge bg-danger text-white px-2 py-1" style="font-size: 0.72rem;" title="{{ $slot->status_message }}">
                                    <i class="ti ti-alert-triangle me-1"></i> Perlu Koreksi
                                </span>
                                @if($slot->status_message)
                                    <div class="text-danger small mt-1" style="font-size: 0.68rem;">{{ $slot->status_message }}</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-icon btn-ghost-primary" title="Koreksi Slot Ini"
                                    onclick="openEditModal({{ json_encode($slot) }})">
                                    <i class="ti ti-edit fs-4"></i>
                                </button>
                                <form action="{{ route('kurikulum.timetables.translation.destroy', [$upload, $slot]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus slot ini dari terjemahan?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Hapus Slot">
                                        <i class="ti ti-trash fs-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="ti ti-table-off fs-1 d-block mb-2 text-muted"></i>
                            Tidak ada slot terjemahan yang sesuai dengan filter yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
        <div class="card-footer py-2">
            {{ $items->links() }}
        </div>
    @endif
</div>

<!-- Modal Edit / Koreksi Slot -->
<div class="modal fade" id="modalEditSlot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditSlot" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-edit me-1 text-primary"></i> Koreksi Slot Terjemahan Jadwal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required fw-bold">Kelas / Rombel</label>
                    <select name="classroom_id" id="edit_classroom_id" class="form-select" required>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} (TP. {{ $c->academic_year }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label required fw-bold">Hari</label>
                        <select name="day" id="edit_day" class="form-select" required>
                            @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $d)
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
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Koreksi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah Slot Baru -->
<div class="modal fade" id="modalAddSlot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.timetables.translation.store', $upload) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-plus me-1 text-primary"></i> Tambah Slot Baru ke Terjemahan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required fw-bold">Kelas / Rombel</label>
                    <select name="classroom_id" class="form-select" required>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClass === $c->name ? 'selected' : '' }}>{{ $c->name }} (TP. {{ $c->academic_year }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label required fw-bold">Hari</label>
                        <select name="day" class="form-select" required>
                            @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $d)
                                <option value="{{ $d }}" {{ $selectedDay === $d ? 'selected' : '' }}>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Mulai</label>
                        <select name="start_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label required fw-bold">Jam Selesai</label>
                        <select name="end_period" class="form-select" required>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}" {{ $i == 2 ? 'selected' : '' }}>Jam {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Mata Pelajaran</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}">[{{ $s->code }}] {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Guru Pengampu</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">-- Pilih Guru Pengampu --</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Tambahkan Slot</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEditModal(slot) {
    const form = document.getElementById('formEditSlot');
    form.action = "{{ url('kurikulum/arsip-jadwal/' . $upload->id . '/terjemahan') }}/" + slot.id;

    if (slot.classroom_id) {
        document.getElementById('edit_classroom_id').value = slot.classroom_id;
    }
    document.getElementById('edit_day').value = slot.day;
    document.getElementById('edit_start_period').value = slot.start_period;
    document.getElementById('edit_end_period').value = slot.end_period;

    if (slot.subject_id) {
        document.getElementById('edit_subject_id').value = slot.subject_id;
    }
    if (slot.teacher_id) {
        document.getElementById('edit_teacher_id').value = slot.teacher_id;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEditSlot'));
    modal.show();
}
</script>
@endpush
@endsection
