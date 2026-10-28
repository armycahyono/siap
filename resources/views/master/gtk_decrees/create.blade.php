@extends('layouts.app')

@section('title', 'Buat Draf SK GTK — Data Pokok')

@section('content')
@include('layouts._hero_header', [
    'title' => 'Buat Draf SK GTK Baru',
    'subtitle' => 'Pencatatan naskah dinas Surat Keputusan penugasan guru dan tenaga kependidikan ke dalam arsip sistem.',
    'icon' => 'ti ti-file-plus',
    'category' => 'ARSIP SK GTK',
    'academicYear' => $activeYear->name ?? null,
    'actions' => '<a href="' . route('gtk-decrees.index') . '" class="btn btn-sm btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar SK</a>'
])

@include('master.nav')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="ti ti-alert-triangle fs-2 me-2 mt-1"></i>
            <div>
                <div class="fw-bold mb-1">Terdapat kesalahan pada isian formulir:</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <form action="{{ route('gtk-decrees.store') }}" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm">
            @csrf

            <div class="card-header bg-white border-bottom p-3">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-warning-lt rounded-circle me-2">
                        <i class="ti ti-pencil fs-3 text-warning"></i>
                    </span>
                    <div>
                        <h4 class="card-title m-0 fw-bold text-dark">Formulir Metadata &amp; Dokumen SK GTK</h4>
                        <small class="text-muted">Dokumen yang disimpan pertama kali akan berstatus <strong>Draf</strong> dan belum memengaruhi hak akses operasional.</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Bagian 1: Identitas SK -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-id me-1 text-primary"></i> 1. Identitas Dokumen SK
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required">Nomor SK Resmi</label>
                        <input type="text" name="number" class="form-control @error('number') is-invalid @enderror" placeholder="Contoh: 800/012/SMK-TI/VII/2026" value="{{ old('number') }}" required>
                        <small class="form-hint text-muted">Nomor surat dinas diinput manual sesuai penomoran agenda tata usaha sekolah.</small>
                        @error('number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Jenis Surat Keputusan</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">-- Pilih Jenis SK --</option>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label required">Judul / Perihal SK</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" placeholder="Contoh: Pembagian Tugas Guru dalam Kegiatan Belajar Mengajar Semester Ganjil TA 2026/2027" value="{{ old('title') }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tahun Ajaran Terkait</label>
                        <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror">
                            <option value="">-- Tidak Terikat TP Tertentu --</option>
                            @foreach($academicYears as $ay)
                                <option value="{{ $ay->id }}" {{ (old('academic_year_id') == $ay->id || (!old('academic_year_id') && $activeYear && $activeYear->id == $ay->id)) ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('academic_year_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Bagian 2: Tanggal Formal SK (Dibedakan secara tegas) -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-calendar-time me-1 text-primary"></i> 2. Tanggal Legalitas &amp; Periode Berlaku
                </h5>
                <div class="alert alert-info py-2 px-3 small border-0 mb-3">
                    <i class="ti ti-info-circle me-1"></i> <strong>Ketentuan Regulasi:</strong> Tanggal Penetapan (penandatanganan), TMT (mulai terhitung), dan Tanggal Mulai Berlaku penugasan dibedakan secara tegas di sistem.
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label required">Tanggal Penetapan</label>
                        <input type="date" name="issued_date" class="form-control @error('issued_date') is-invalid @enderror" value="{{ old('issued_date', date('Y-m-d')) }}" required>
                        <small class="form-hint text-muted">Tanggal ditandatangani.</small>
                        @error('issued_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">TMT (Terhitung Mulai)</label>
                        <input type="date" name="tmt_date" class="form-control @error('tmt_date') is-invalid @enderror" value="{{ old('tmt_date', date('Y-m-d')) }}" required>
                        <small class="form-hint text-muted">TMT resmi kepegawaian.</small>
                        @error('tmt_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">Tanggal Mulai Berlaku</label>
                        <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from', date('Y-m-d')) }}" required>
                        <small class="form-hint text-muted">Awal berlaku penugasan.</small>
                        @error('valid_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir Berlaku</label>
                        <input type="date" name="valid_until" class="form-control @error('valid_until') is-invalid @enderror" value="{{ old('valid_until') }}">
                        <small class="form-hint text-muted">Opsional (bila berjangka).</small>
                        @error('valid_until')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Bagian 3: Pejabat Penetap & Lampiran Berkas -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-signature me-1 text-primary"></i> 3. Pejabat Penetap &amp; Berkas PDF Privat
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nama Pejabat Penetap</label>
                        <input type="text" name="signer_name" class="form-control @error('signer_name') is-invalid @enderror" placeholder="Contoh: Drs. H. Ahmad Fauzi, M.Pd." value="{{ old('signer_name') }}">
                        @error('signer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Jabatan Pejabat Penetap</label>
                        <input type="text" name="signer_title" class="form-control @error('signer_title') is-invalid @enderror" placeholder="Contoh: Kepala Sekolah" value="{{ old('signer_title', 'Kepala Sekolah') }}">
                        @error('signer_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keterangan / Konsiderans Tambahan</label>
                        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Tambahkan catatan latar belakang atau keterangan pendukung lainnya...">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Unggah Dokumen PDF Resmi (Privat)</label>
                        <input type="file" name="pdf_document" accept=".pdf,application/pdf" class="form-control @error('pdf_document') is-invalid @enderror">
                        <small class="form-hint text-muted">
                            <i class="ti ti-lock me-1 text-success"></i> Dokumen disimpan di area penyimpanan privat server (`storage/app/private/sk_gtk/`), tidak dapat diakses tanpa autentikasi. Format .pdf, ukuran maksimal 10 MB.
                        </small>
                        @error('pdf_document')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between p-3">
                <a href="{{ route('gtk-decrees.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-x me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Draf SK &amp; Lanjutkan ke Penerima
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
