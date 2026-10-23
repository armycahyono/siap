@extends('layouts.app')

@section('title', 'Pascaobservasi: ' . $supervision->teacher->name)

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Tahap 3: Umpan Balik & Rekomendasi Pascaobservasi',
    'subtitle' => 'Refleksi hasil amatan pembelajaran, apresiasi kelebihan guru, dan penetapan tindak lanjut pembinaan: ' . $supervision->teacher->name . ' (' . $supervision->subject . ' — ' . $supervision->class_name . ').',
    'icon' => 'ti ti-message-dots',
    'category' => 'SUPERVISI AKADEMIK',
    'academicYear' => $supervision->academic_year ?? null,
    'actions' => '<a href="' . route('supervisions.show', $supervision) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-arrow-left me-1"></i> Kembali ke Berkas</a>'
])

<!-- Header Identity Banner -->
<div class="card bg-blue-lt border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center small">
            <div class="col-md-3"><strong>Guru:</strong> {{ $supervision->teacher->name }}</div>
            <div class="col-md-3"><strong>Mata Pelajaran:</strong> {{ $supervision->subject }}</div>
            <div class="col-md-3"><strong>Nilai Perolehan:</strong> <span class="fw-bold text-primary">{{ $supervision->percentage }}% ({{ $supervision->predicate }})</span></div>
            <div class="col-md-3"><strong>Supervisor:</strong> {{ $supervision->supervisor->name }}</div>
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
                        Supervisi ini telah resmi disetujui & disahkan oleh Kepala Sekolah sehingga catatan pascaobservasi telah dikunci permanen.
                    @else
                        Pengisian dan perubahan catatan pascaobservasi hanya dapat dilakukan oleh Supervisor penilai yang ditugaskan.
                    @endif
                </div>
            </div>
        @endif

        <form action="{{ route('supervisions.save-post-observation', $supervision) }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold text-success">
                    <i class="ti ti-thumb-up me-1"></i> 1. Catatan Positif & Kelebihan Pembelajaran
                </label>
                <div class="text-muted small mb-1">Apresiasi terhadap aspek-aspek yang sudah berjalan sangat baik selama proses KBM:</div>
                <textarea name="post_positive" class="form-control" rows="3" placeholder="Contoh: Penguasaan materi sangat baik, interaksi antarsiswa hidup, media digital digunakan secara efektif..." {{ $canConduct ? 'required' : 'readonly disabled' }}>{{ old('post_positive', $supervision->post_positive) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold text-warning">
                    <i class="ti ti-alert-circle me-1"></i> 2. Hal-hal yang Perlu Ditingkatkan / Diperbaiki
                </label>
                <div class="text-muted small mb-1">Identifikasi kelemahan atau aspek KBM yang membutuhkan pembenahan:</div>
                <textarea name="post_improvement" class="form-control" rows="3" placeholder="Contoh: Perlu memperhatikan alokasi waktu pada sesi penutup, diferensiasi asesmen belum tampak jelas..." {{ $canConduct ? 'required' : 'readonly disabled' }}>{{ old('post_improvement', $supervision->post_improvement) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold text-primary">
                    <i class="ti ti-bulb me-1"></i> 3. Rekomendasi Solutif dari Supervisor
                </label>
                <div class="text-muted small mb-1">Langkah konkrit yang disarankan kepada guru untuk peningkatan mutu pengajaran:</div>
                <textarea name="post_recommendation" class="form-control" rows="3" placeholder="Contoh: Memanfaatkan rubrik penilaian mandiri (self-assessment), mengikuti workshop kurikulum merdeka..." {{ $canConduct ? 'required' : 'readonly disabled' }}>{{ old('post_recommendation', $supervision->post_recommendation) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label {{ $canConduct ? 'required' : '' }} fw-bold text-info">
                    <i class="ti ti-arrow-forward me-1"></i> 4. Rencana Tindak Lanjut Pembinaan
                </label>
                <div class="text-muted small mb-1">Aktivitas lanjutan yang akan dilakukan oleh guru / program sekolah:</div>
                <textarea name="post_followup" class="form-control" rows="2" placeholder="Contoh: Supervisi klinis kedua pada minggu ke-3 bulan depan, berbagi praktik baik di MGMP sekolah..." {{ $canConduct ? 'required' : 'readonly disabled' }}>{{ old('post_followup', $supervision->post_followup) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold text-dark">
                    <i class="ti ti-messages me-1"></i> 5. Catatan Kesepakatan Hasil Diskusi dengan Guru (Pascaobservasi)
                </label>
                <div class="text-muted small mb-1">Rangkuman dialog reflektif dua arah antara supervisor dan guru setelah selesai observasi:</div>
                <textarea name="post_discussion" class="form-control" rows="2" placeholder="Guru menerima hasil evaluasi dan berkomitmen untuk..." {{ $canConduct ? '' : 'readonly disabled' }}>{{ old('post_discussion', $supervision->post_discussion) }}</textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('supervisions.observation', $supervision) }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Observasi
                </a>
                <div class="d-flex gap-2">
                    @if($canConduct)
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Catatan Pascaobservasi
                    </button>
                    @endif
                    <a href="{{ route('supervisions.documentation', $supervision) }}" class="btn btn-warning text-dark fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                        Lanjut ke Dokumentasi Foto <i class="ti ti-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
