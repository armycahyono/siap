@extends('layouts.app')

@section('title', 'Catat Izin Siswa Perwalian')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted text-uppercase">Wali Kelas &bull; Input Izin Siswa</div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-file-pencil text-primary me-2 fs-1"></i>
                    Formulir Laporan Izin Siswa Perwalian
                </h2>
                <div class="text-secondary mt-1">
                    Kelas: <strong>{{ $selectedClassroom->name }}</strong> &bull; Wali Kelas: <strong>{{ auth()->user()->name }}</strong>
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('attendance.homeroom-permits.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Riwayat
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <form action="{{ route('attendance.homeroom-permits.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="classroom_id" value="{{ $selectedClassroom->id }}">

                    <div class="card-body p-4">
                        @if($myClassrooms->count() > 1)
                            <div class="mb-3">
                                <label class="form-label required fw-bold text-dark">Pilih Kelas Perwalian</label>
                                <select class="form-select form-select-sm" onchange="window.location.href='{{ route('attendance.homeroom-permits.create') }}?classroom_id=' + this.value">
                                    @foreach($myClassrooms as $c)
                                        <option value="{{ $c->id }}" {{ $selectedClassroom->id == $c->id ? 'selected' : '' }}>Kelas {{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- 1. Pilih Siswa -->
                        <div class="mb-3">
                            <label class="form-label required fw-bold text-dark">Pilih Siswa</label>
                            <select name="student_id" id="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Nama Siswa di Kelas {{ $selectedClassroom->name }} --</option>
                                @foreach($students as $st)
                                    <option value="{{ $st->id }}" {{ old('student_id') == $st->id ? 'selected' : '' }}>
                                        {{ $st->name }} ({{ $st->gender == 'L' ? 'L' : 'P' }} &bull; NIS: {{ $st->nisn ?? $st->nis ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- 2. Kategori Izin -->
                        <div class="mb-3">
                            <label class="form-label required fw-bold text-dark">Kategori Ketidakhadiran</label>
                            <div class="row g-2">
                                <div class="col-12 col-sm-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_category" value="sakit" class="form-selectgroup-input" {{ old('permit_category', 'sakit') === 'sakit' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-3 text-center">
                                            <i class="ti ti-first-aid-kit fs-2 text-warning mb-1"></i>
                                            <span class="fw-bold">Sakit (S)</span>
                                            <span class="text-muted small">Kondisi tubuh sakit</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_category" value="izin" class="form-selectgroup-input" {{ old('permit_category') === 'izin' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-3 text-center">
                                            <i class="ti ti-calendar-event fs-2 text-info mb-1"></i>
                                            <span class="fw-bold">Izin (I)</span>
                                            <span class="text-muted small">Kepentingan keluarga</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_category" value="dispen" class="form-selectgroup-input" {{ old('permit_category') === 'dispen' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-3 text-center">
                                            <i class="ti ti-award fs-2 text-purple mb-1"></i>
                                            <span class="fw-bold">Dispen (D)</span>
                                            <span class="text-muted small">Tugas / Lomba sekolah</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Rentang Tanggal Izin -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold text-dark">Tanggal Mulai Izin</label>
                                <input type="date" name="permit_date" class="form-control @error('permit_date') is-invalid @enderror" value="{{ old('permit_date', date('Y-m-d')) }}" required>
                                @error('permit_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Sampai Tanggal (Opsional)</label>
                                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}" placeholder="Kosongkan jika hanya 1 hari">
                                <span class="text-muted small">Isi jika siswa izin lebih dari 1 hari (misal opname/istirahat dokter).</span>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- 4. Alasan / Pesan dari Orang Tua -->
                        <div class="mb-3">
                            <label class="form-label required fw-bold text-dark">Alasan / Keterangan dari Wali Murid</label>
                            <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" placeholder="Contoh: Sakit demam tinggi sejak semalam, istirahat di rumah (chat WA ibu kandung)..." required>{{ old('reason') }}</textarea>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 quick-reason" data-reason="Sakit demam & flu, istirahat di rumah">Demam/Flu</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 quick-reason" data-reason="Periksa ke dokter / opname RS">Periksa Dokter / RS</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 quick-reason" data-reason="Acara keluarga penting di luar kota">Acara Keluarga</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 quick-reason" data-reason="Takziyah / ada keluarga yang meninggal dunia">Takziyah</button>
                            </div>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- 5. Foto Surat Dokter / Screenshot WA (Opsional) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Foto Surat Izin / Bukti Chat WA (Opsional)</label>
                            <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept="image/*">
                            <span class="text-muted small">Format foto/gambar (JPG, PNG). Maksimal 3MB.</span>
                            @error('attachment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Info Otomatisasi -->
                        <div class="alert alert-success border-0 shadow-none d-flex align-items-center mb-0">
                            <i class="ti ti-check-double fs-1 me-3 text-success"></i>
                            <div class="small">
                                <strong>Langsung Terhubung:</strong> Begitu disimpan, guru piket dan seluruh guru mata pelajaran yang masuk ke kelas {{ $selectedClassroom->name }} pada tanggal tersebut akan langsung melihat tanda izin/sakit pada siswa ini tanpa perlu konfirmasi manual lagi.
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light text-end py-3 px-4">
                        <a href="{{ route('attendance.homeroom-permits.index') }}" class="btn btn-outline-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-primary shadow-sm">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Laporan Izin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.quick-reason').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const textarea = document.querySelector('textarea[name="reason"]');
                if (textarea) {
                    textarea.value = this.dataset.reason;
                    textarea.focus();
                }
            });
        });
    });
</script>
@endpush
@endsection
