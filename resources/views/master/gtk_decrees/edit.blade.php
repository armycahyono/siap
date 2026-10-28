@extends('layouts.app')

@section('title', 'Edit Draf SK GTK — Data Pokok')

@section('content')
@include('layouts._hero_header', [
    'title' => 'Edit Draf SK GTK',
    'subtitle' => 'Perbarui nomor SK, judul, tanggal legalitas, pejabat penetap, atau ganti berkas PDF draf.',
    'icon' => 'ti ti-file-pencil',
    'category' => 'ARSIP SK GTK',
    'academicYear' => $gtkDecree->academicYear?->name ?? $gtkDecree->academic_year ?? null,
    'actions' => '<a href="' . route('gtk-decrees.show', $gtkDecree) . '" class="btn btn-sm btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Kembali ke Detail SK</a>'
])

@include('master.nav')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="ti ti-alert-triangle fs-2 me-2 mt-1"></i>
            <div>
                <div class="fw-bold mb-1">Terdapat kesalahan pada formulir:</div>
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
        <form action="{{ route('gtk-decrees.update', $gtkDecree) }}" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm">
            @csrf
            @method('PUT')

            <div class="card-header bg-white border-bottom p-3">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-sm bg-warning-lt rounded-circle me-2">
                        <i class="ti ti-pencil fs-3 text-warning"></i>
                    </span>
                    <div>
                        <h4 class="card-title m-0 fw-bold text-dark">Perbarui Metadata Draf SK: {{ $gtkDecree->number }}</h4>
                        <small class="text-muted">Perubahan metadata hanya dapat dilakukan selama SK berstatus <strong>Draf</strong>.</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Identitas SK -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-id me-1 text-primary"></i> 1. Identitas Dokumen SK
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required">Nomor SK Resmi</label>
                        <input type="text" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $gtkDecree->number) }}" required>
                        <small class="form-hint text-muted">Nomor surat dinas diinput manual.</small>
                        @error('number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">Jenis Surat Keputusan</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('type', $gtkDecree->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label required">Judul / Perihal SK</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $gtkDecree->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tahun Ajaran Terkait</label>
                        <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror">
                            <option value="">-- Tidak Terikat TP Tertentu --</option>
                            @foreach($academicYears as $ay)
                                <option value="{{ $ay->id }}" {{ old('academic_year_id', $gtkDecree->academic_year_id) == $ay->id ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('academic_year_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Tanggal Formal SK -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-calendar-time me-1 text-primary"></i> 2. Tanggal Legalitas &amp; Periode Berlaku
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label required">Tanggal Penetapan</label>
                        <input type="date" name="issued_date" class="form-control @error('issued_date') is-invalid @enderror" value="{{ old('issued_date', $gtkDecree->issued_date ? $gtkDecree->issued_date->format('Y-m-d') : '') }}" required>
                        <small class="form-hint text-muted">Tanggal ditandatangani.</small>
                        @error('issued_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">TMT (Terhitung Mulai)</label>
                        <input type="date" name="tmt_date" class="form-control @error('tmt_date') is-invalid @enderror" value="{{ old('tmt_date', $gtkDecree->tmt_date ? $gtkDecree->tmt_date->format('Y-m-d') : '') }}" required>
                        <small class="form-hint text-muted">TMT resmi kepegawaian.</small>
                        @error('tmt_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">Tanggal Mulai Berlaku</label>
                        <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from', $gtkDecree->valid_from ? $gtkDecree->valid_from->format('Y-m-d') : '') }}" required>
                        <small class="form-hint text-muted">Awal berlaku penugasan.</small>
                        @error('valid_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir Berlaku</label>
                        <input type="date" name="valid_until" class="form-control @error('valid_until') is-invalid @enderror" value="{{ old('valid_until', $gtkDecree->valid_until ? $gtkDecree->valid_until->format('Y-m-d') : '') }}">
                        <small class="form-hint text-muted">Opsional (bila berjangka).</small>
                        @error('valid_until')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Pejabat Penetap & Dokumen PDF -->
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-signature me-1 text-primary"></i> 3. Pejabat Penetap &amp; Berkas PDF Privat
                </h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nama Pejabat Penetap</label>
                        <input type="text" name="signer_name" class="form-control @error('signer_name') is-invalid @enderror" value="{{ old('signer_name', $gtkDecree->signer_name) }}">
                        @error('signer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Jabatan Pejabat Penetap</label>
                        <input type="text" name="signer_title" class="form-control @error('signer_title') is-invalid @enderror" value="{{ old('signer_title', $gtkDecree->signer_title) }}">
                        @error('signer_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keterangan / Konsiderans Tambahan</label>
                        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $gtkDecree->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Berkas Dokumen PDF Privat</label>
                        @if($gtkDecree->file_path && $gtkDecree->hasPrivateDocument())
                            <div class="p-2 mb-2 bg-light rounded d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="ti ti-file-text fs-2 text-danger me-2"></i>
                                    <span class="fw-bold">{{ $gtkDecree->original_filename ?: 'Dokumen_SK.pdf' }}</span>
                                    <span class="text-muted small">({{ round($gtkDecree->file_size / 1024, 1) }} KB)</span>
                                </div>
                                <a href="{{ route('gtk-decrees.document', ['gtkDecree' => $gtkDecree, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-eye me-1"></i> Buka Pratinjau
                                </a>
                            </div>
                        @endif
                        <input type="file" name="pdf_document" accept=".pdf,application/pdf" class="form-control @error('pdf_document') is-invalid @enderror">
                        <small class="form-hint text-muted">
                            Unggah berkas PDF baru jika ingin mengganti dokumen saat ini. Berkas lama akan otomatis dihapus secara aman setelah berkas baru berhasil disimpan.
                        </small>
                        @error('pdf_document')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between p-3">
                <a href="{{ route('gtk-decrees.show', $gtkDecree) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-x me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-device-floppy me-1"></i> Perbarui Metadata Draf
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
