@extends('layouts.app')

@section('title', 'Dokumentasi Foto: ' . $supervision->teacher->name)

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Tahap 4: Dokumentasi Foto Kegiatan Supervisi',
    'subtitle' => 'Unggah foto kegiatan KBM di kelas, media belajar, interaksi murid, dan sesi diskusi sebagai lampiran bukti fisik laporan resmi: ' . $supervision->teacher->name . ' (' . $supervision->subject . ' — ' . $supervision->class_name . ').',
    'icon' => 'ti ti-photo',
    'category' => 'SUPERVISI AKADEMIK',
    'academicYear' => $supervision->academic_year ?? null,
    'actions' => '<a href="' . route('supervisions.show', $supervision) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-arrow-left me-1"></i> Kembali ke Berkas</a>'
])
@php
    $canConduct = auth()->user()->canEditSupervision($supervision);
@endphp

<div class="row g-4 mb-4">
    <!-- Upload Card -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-upload me-2 text-primary"></i> Unggah Foto Baru
                </h3>
            </div>
            <div class="card-body p-4">
                @if($canConduct)
                <form action="{{ route('supervisions.upload-photo', $supervision) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Pilih File Foto (JPG/PNG/WEBP, Maks 8MB)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">Kategori Foto</label>
                        <select name="category" class="form-select" required>
                            <option value="Foto Guru saat mengajar">Foto Guru saat mengajar di depan kelas</option>
                            <option value="Foto kegiatan pembelajaran">Foto kegiatan pembelajaran interaktif</option>
                            <option value="Foto aktivitas murid">Foto aktivitas murid / diskusi kelompok</option>
                            <option value="Foto media/perangkat pembelajaran">Foto media / perangkat / alat lab yang digunakan</option>
                            <option value="Foto kegiatan supervisi">Foto wawancara / supervisi kelas</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Keterangan / Caption Foto</label>
                        <input type="text" name="caption" class="form-control" placeholder="Contoh: Guru mendemonstrasikan simulator Cisco kepada siswa...">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="ti ti-photo-plus me-1"></i> Upload Foto Dokumentasi
                    </button>
                </form>
                @else
                <div class="alert alert-info d-flex align-items-center mb-0">
                    <i class="ti ti-info-circle fs-2 me-2"></i>
                    <div>
                        <strong>Mode Pratinjau:</strong>
                        @if($supervision->status === 'disetujui' || $supervision->status === 'selesai')
                            Supervisi ini telah resmi disetujui & disahkan oleh Kepala Sekolah sehingga dokumentasi foto telah dikunci permanen.
                        @else
                            Hanya Supervisor penilai yang ditugaskan yang dapat menambah atau menghapus foto dokumentasi.
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Photo Gallery -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-library-photo me-2 text-primary"></i> Foto yang Telah Diunggah ({{ $photos->count() }})
                </h3>
                <span class="badge bg-blue-lt">Lampiran Halaman 4 Laporan</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    @forelse($photos as $photo)
                    <div class="col-sm-6">
                        <div class="card card-sm border shadow-none overflow-hidden h-100">
                            <img src="{{ $photo->url }}" alt="{{ $photo->caption }}" class="card-img-top" style="height: 180px; object-fit: cover;">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <span class="badge bg-secondary-lt small mb-1">{{ $photo->category }}</span>
                                    <div class="fw-bold text-dark small">{{ $photo->caption ?: 'Dokumentasi Supervisi' }}</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                    <small class="text-muted">{{ $photo->created_at->format('d/m/Y H:i') }}</small>
                                    @if($canConduct)
                                    <form action="{{ route('supervisions.delete-photo', $photo) }}" method="POST" onsubmit="return confirm('Hapus foto ini dari dokumentasi?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus Foto">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="ti ti-photo-off fs-1 d-block mb-2 text-secondary"></i>
                        Belum ada foto dokumentasi yang diunggah untuk supervisi ini.
                    </div>
                    @endforelse
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('supervisions.show', $supervision) }}" class="btn btn-primary fw-bold">
                    <i class="ti ti-check me-1"></i> Selesai & Kembali ke Hub Supervisi
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
