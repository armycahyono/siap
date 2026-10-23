@extends('layouts.app')

@section('title', 'Praobservasi: ' . $supervision->teacher->name)

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Tahap 1: Catatan Praobservasi Pembelajaran',
    'subtitle' => 'Wawancara awal kesiapan pembelajaran guru sebelum memasuki ruang kelas: ' . $supervision->teacher->name . ' (' . $supervision->subject . ' — ' . $supervision->class_name . ').',
    'icon' => 'ti ti-clipboard-list',
    'category' => 'SUPERVISI AKADEMIK',
    'academicYear' => $supervision->academic_year ?? null,
    'actions' => '<a href="' . route('supervisions.show', $supervision) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-arrow-left me-1"></i> Kembali ke Berkas</a>'
])

<!-- Header Identity Banner -->
<div class="card bg-blue-lt border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2">
            <div class="col-md-3">
                <small class="text-muted d-block">Nama Guru:</small>
                <strong class="text-dark">{{ $supervision->teacher->name }}</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Mata Pelajaran:</small>
                <strong class="text-dark">{{ $supervision->subject }}</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Kelas & Jurusan:</small>
                <strong class="text-dark">{{ $supervision->class_name }}</strong> ({{ $supervision->expertise_program ?: 'Umum' }})
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Supervisor:</small>
                <strong class="text-dark">{{ $supervision->supervisor->name }}</strong>
            </div>
        </div>
    </div>
</div>

@php
    $canConduct = auth()->user()->canEditSupervision($supervision);
@endphp

<div class="card border-0 shadow-sm" style="max-width: 900px;">
    <div class="card-body p-4">
        @if(! $canConduct)
            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="ti ti-info-circle fs-2 me-2"></i>
                <div>
                    <strong>Mode Pratinjau (Hanya Lihat):</strong>
                    @if($supervision->status === 'disetujui' || $supervision->status === 'selesai')
                        Supervisi ini telah resmi disetujui & disahkan oleh Kepala Sekolah sehingga data instrumen telah dikunci permanen.
                    @else
                        Pengisian dan perubahan instrumen praobservasi hanya dapat dilakukan oleh Supervisor penilai yang ditugaskan.
                    @endif
                </div>
            </div>
        @endif

        <form action="{{ route('supervisions.save-pre-observation', $supervision) }}" method="POST">
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold">Materi Pokok yang Akan Diajarkan</label>
                    <input type="text" name="pre_material" class="form-control" placeholder="Contoh: Konfigurasi Routing Dinamis..." value="{{ old('pre_material', $supervision->pre_material) }}" {{ $canConduct ? 'required' : 'readonly disabled' }}>
                </div>
                <div class="col-md-6">
                    <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold">Model / Metode Pembelajaran</label>
                    <input type="text" name="pre_method" class="form-control" placeholder="Contoh: Project-Based Learning (PjBL) / Demonstrasi" value="{{ old('pre_method', $supervision->pre_method) }}" {{ $canConduct ? 'required' : 'readonly disabled' }}>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold">Tujuan Pembelajaran yang Ingin Dicapai</label>
                <textarea name="pre_objectives" class="form-control" rows="3" placeholder="Tuliskan tujuan pembelajaran spesifik yang akan dicapai peserta didik..." {{ $canConduct ? 'required' : 'readonly disabled' }}>{{ old('pre_objectives', $supervision->pre_objectives) }}</textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold">Media & Alat Pembelajaran yang Digunakan</label>
                    <input type="text" name="pre_media" class="form-control" placeholder="Contoh: Simulator Cisco, Alat Praktik Lab, LCD..." value="{{ old('pre_media', $supervision->pre_media) }}" {{ $canConduct ? 'required' : 'readonly disabled' }}>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Strategi Pembelajaran</label>
                    <input type="text" name="pre_strategy" class="form-control" placeholder="Contoh: Pembagian kelompok heterogen, tanya jawab pemantik..." value="{{ old('pre_strategy', $supervision->pre_strategy) }}" {{ $canConduct ? '' : 'readonly disabled' }}>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Aspek Fokus yang Ingin Diamati oleh Guru</label>
                    <input type="text" name="pre_focus" class="form-control" placeholder="Contoh: Keterlibatan murid pasif, diferensiasi produk..." value="{{ old('pre_focus', $supervision->pre_focus) }}" {{ $canConduct ? '' : 'readonly disabled' }}>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tingkat Kesiapan Pembelajaran</label>
                    <input type="text" name="pre_readiness" class="form-control" placeholder="Contoh: Perangkat ajar dan alat lab siap 100%" value="{{ old('pre_readiness', $supervision->pre_readiness) }}" {{ $canConduct ? '' : 'readonly disabled' }}>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold text-primary">Catatan Masukan Supervisor Sebelum Pelaksanaan Observasi</label>
                <textarea name="pre_supervisor_notes" class="form-control" rows="3" placeholder="Berikan arahan, saran fokus amatan, atau rekomendasi teknis awal..." {{ $canConduct ? '' : 'readonly disabled' }}>{{ old('pre_supervisor_notes', $supervision->pre_supervisor_notes) }}</textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('supervisions.show', $supervision) }}" class="btn btn-secondary">
                    Kembali
                </a>
                <div class="d-flex gap-2">
                    @if($canConduct)
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Catatan Praobservasi
                    </button>
                    @endif
                    <a href="{{ route('supervisions.observation', $supervision) }}" class="btn btn-warning text-dark fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                        Lanjut ke Observasi <i class="ti ti-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
