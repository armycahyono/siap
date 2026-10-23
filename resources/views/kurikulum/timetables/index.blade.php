@extends('layouts.app')

@section('title', 'Arsip Dokumen Jadwal PDF')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $ttActions = '<a href="' . route('kurikulum.schedules.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-calendar-time me-1 text-info"></i> Lihat Jadwal Pelajaran</a> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalUploadArchive"><i class="ti ti-cloud-upload me-1"></i> Upload File PDF Baru</button>';
@endphp
@include('layouts._hero_header', [
    'title' => 'Arsip Dokumen Jadwal PDF',
    'subtitle' => 'Penyimpanan dan pratinjau langsung file PDF jadwal pelajaran kelas (aSc Timetables).',
    'icon' => 'ti ti-file-text',
    'category' => 'KURIKULUM & ARSIP',
    'actions' => $ttActions
])

<div class="row g-3">
    <!-- Kolom Kiri: Tabel Arsip Jadwal -->
    <div class="col-lg-6 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-files me-2 text-primary"></i> Daftar Dokumen PDF
                </h4>
                <span class="text-muted small">
                    <i class="ti ti-pointer me-1"></i> Klik baris untuk melihat pratinjau
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table">
                    <thead class="bg-light">
                        <tr>
                            <th>Dokumen</th>
                            <th>Tahun / Smt</th>
                            <th class="text-center">Status</th>
                            <th class="text-end" style="width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($uploads as $item)
                            @php
                                $isActive = $item->is_active;
                                $isAsli = str_contains(strtolower($item->title . ' ' . $item->original_filename . ' ' . $item->file_path), 'asli');
                            @endphp
                            <tr class="timetable-row {{ $loop->first ? 'active-preview' : '' }}" 
                                style="cursor: pointer; transition: all 0.15s ease;"
                                data-id="{{ $item->id }}"
                                data-title="{{ $item->title }}"
                                data-filename="{{ $item->original_filename }}"
                                data-is-asli="{{ $isAsli ? '1' : '0' }}"
                                data-preview-url="{{ route('kurikulum.timetables.preview', $item) }}"
                                data-download-url="{{ route('kurikulum.timetables.download', $item) }}"
                                data-translation-url="{{ route('kurikulum.timetables.translation', $item) }}"
                                onclick="selectTimetable(this)">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-md bg-purple-lt text-purple rounded-3 me-2 flex-shrink-0">
                                            <i class="ti ti-file-type-pdf fs-2"></i>
                                        </span>
                                        <div class="overflow-hidden">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span class="badge bg-purple text-white px-2 py-0 fw-semibold" style="font-size: 0.65rem;">
                                                    <i class="ti ti-file-text me-1"></i> aSc Timetables PDF
                                                </span>
                                                @if(($item->invalid_translation_items ?? 0) > 0)
                                                    <span class="badge bg-danger text-white px-2 py-0 fw-semibold" style="font-size: 0.65rem;">
                                                        {{ $item->invalid_translation_items }} Perlu Koreksi
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="fw-bold text-dark text-truncate doc-title" style="max-width: 230px;" title="{{ $item->title }}">
                                                {{ $item->title }}
                                            </div>
                                            <div class="text-muted small text-truncate mt-1" style="font-size: 0.72rem;">
                                                {{ $item->original_filename }} &bull; {{ number_format($item->file_size / 1024, 1) }} KB
                                            </div>
                                            <div class="text-secondary small mt-1" style="font-size: 0.7rem;">
                                                <i class="ti ti-layout-grid me-1"></i>{{ $item->total_classes }} Rombel &bull; <i class="ti ti-clock me-1"></i>{{ $item->total_translation_items ?? $item->total_lessons }} Slot
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-blue-lt border border-primary px-2 py-1 fw-bold" style="font-size: 0.72rem;">
                                        {{ $item->academic_year }}<br>{{ $item->semester }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($item->is_active)
                                        <span class="badge bg-teal text-white px-2 py-1" style="font-size: 0.7rem;">
                                            <i class="ti ti-check me-1"></i> Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-lt text-muted px-2 py-1" style="font-size: 0.7rem;">
                                            Arsip / Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end" onclick="event.stopPropagation()">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        <a href="{{ route('kurikulum.timetables.translation', $item) }}" class="btn btn-sm btn-outline-primary px-2" title="Lihat & Koreksi Tabel Terjemahan">
                                            <i class="ti ti-table-options me-1"></i> Terjemahan
                                        </a>

                                        <a href="{{ route('kurikulum.timetables.download', $item) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Download File PDF">
                                            <i class="ti ti-download fs-4"></i>
                                        </a>

                                        @if(!$item->is_active)
                                        <form action="{{ route('kurikulum.timetables.reapply', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Terapkan dokumen jadwal ini menjadi jadwal aktif?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-teal" title="Terapkan & Aktifkan">
                                                <i class="ti ti-rotate fs-4"></i>
                                            </button>
                                        </form>
                                        @endif

                                        <form action="{{ route('kurikulum.timetables.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus arsip jadwal dan data terjemahannya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Hapus Dokumen">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="ti ti-folder-off fs-1 d-block mb-2 text-muted"></i>
                                    Belum ada dokumen jadwal PDF yang diarsipkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($uploads->hasPages())
                <div class="card-footer py-2">
                    {{ $uploads->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Kolom Kanan: Pratinjau PDF Terpilih -->
    <div class="col-lg-6 col-xl-6">
        <div class="card border-0 shadow-sm sticky-top" style="top: 80px; z-index: 10;">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="overflow-hidden me-2">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-red text-white me-2">PDF</span>
                        <span id="previewTypeBadge" class="badge bg-purple text-white"></span>
                    </div>
                    <h4 class="card-title fw-bold text-dark m-0 text-truncate" id="previewTitle">
                        Memuat Pratinjau...
                    </h4>
                    <div class="text-muted small text-truncate mt-1" id="previewFilename" style="font-size: 0.75rem;">
                        -
                    </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <a href="#" id="previewTranslationBtn" class="btn btn-sm btn-outline-primary" title="Buka Tabel Hasil Terjemahan">
                        <i class="ti ti-table-options me-1"></i> Hasil Terjemahan
                    </a>
                    <a href="#" id="previewExternalBtn" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di Tab Baru">
                        <i class="ti ti-external-link me-1"></i> Buka Penuh
                    </a>
                    <a href="#" id="previewDownloadBtn" class="btn btn-sm btn-secondary" title="Download File Ini">
                        <i class="ti ti-download me-1"></i> Unduh
                    </a>
                </div>
            </div>

            <div class="card-body p-0 position-relative" style="background-color: #525659; min-height: 720px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; overflow: hidden;">
                <!-- Loading State -->
                <div id="previewLoader" class="position-absolute top-50 start-50 translate-middle text-white text-center d-none" style="z-index: 5;">
                    <div class="spinner-border text-light mb-2" role="status"></div>
                    <div class="small">Memuat Dokumen PDF...</div>
                </div>

                <!-- PDF Viewer Frame -->
                <iframe id="pdfFrame" src="" style="width: 100%; height: 740px; border: none;" allowfullscreen></iframe>

                <!-- Empty State -->
                <div id="previewEmpty" class="position-absolute top-50 start-50 translate-middle text-center text-white p-4 d-none">
                    <i class="ti ti-file-off fs-1 text-light opacity-50 mb-2"></i>
                    <h5 class="fw-bold">Tidak Ada Dokumen yang Dipilih</h5>
                    <p class="small text-light opacity-75">Klik salah satu baris jadwal di tabel sebelah kiri untuk menampilkan pratinjaunya di sini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Upload Dokumen PDF -->
<div class="modal fade" id="modalUploadArchive" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('kurikulum.timetables.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti ti-cloud-upload me-1 text-primary"></i> Upload Arsip File Jadwal PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required fw-bold">Judul Dokumen</label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Jadwal Kelas Semester Ganjil 2026/2027 (Revisi 1)" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label required fw-bold">Tahun Pelajaran</label>
                        <input type="text" name="academic_year" class="form-control" value="{{ $setting->academic_year ?? '2026/2027' }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label required fw-bold">Semester</label>
                        <select name="semester" class="form-select" required>
                            <option value="Ganjil" {{ ($setting->semester ?? 'Ganjil') === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                            <option value="Genap" {{ ($setting->semester ?? 'Ganjil') === 'Genap' ? 'selected' : '' }}>Genap</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Pilih File PDF</label>
                    <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                    <div class="form-text small">Maksimal 15 MB, format file .pdf (dari aSc Timetables).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Catatan / Keterangan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan tambahan dokumen jadwal..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan & Sinkronkan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.timetable-row.active-preview {
    background-color: #f0fdfa !important;
    border-left: 4px solid #0d9488 !important;
}
.timetable-row.active-preview .doc-title {
    color: #0d9488 !important;
}
.timetable-row:hover {
    background-color: #f8fafc;
}
</style>
@endpush

@push('scripts')
<script>
function selectTimetable(rowElement) {
    // Remove active state from all rows
    document.querySelectorAll('.timetable-row').forEach(row => {
        row.classList.remove('active-preview');
    });

    // Add active state to clicked row
    rowElement.classList.add('active-preview');

    const title = rowElement.getAttribute('data-title');
    const filename = rowElement.getAttribute('data-filename');
    const isAsli = rowElement.getAttribute('data-is-asli') === '1';
    const previewUrl = rowElement.getAttribute('data-preview-url');
    const downloadUrl = rowElement.getAttribute('data-download-url');
    const translationUrl = rowElement.getAttribute('data-translation-url');

    // Update Header
    document.getElementById('previewTitle').innerText = title;
    document.getElementById('previewFilename').innerText = filename;
    document.getElementById('previewExternalBtn').href = previewUrl;
    document.getElementById('previewDownloadBtn').href = downloadUrl;
    if (document.getElementById('previewTranslationBtn')) {
        document.getElementById('previewTranslationBtn').href = translationUrl;
    }

    const badgeEl = document.getElementById('previewTypeBadge');
    if (badgeEl) {
        if (isAsli) {
            badgeEl.className = 'badge bg-purple text-white';
            badgeEl.innerHTML = '<i class="ti ti-check me-1"></i> File Asli (aSc)';
        } else {
            badgeEl.className = 'badge bg-blue-lt text-blue';
            badgeEl.innerHTML = '<i class="ti ti-arrows-transfer-down me-1"></i> Hasil Terjemahan';
        }
    }

    // Show Loader
    const loader = document.getElementById('previewLoader');
    const iframe = document.getElementById('pdfFrame');
    const emptyState = document.getElementById('previewEmpty');

    emptyState.classList.add('d-none');
    loader.classList.remove('d-none');

    iframe.onload = function() {
        loader.classList.add('d-none');
    };

    iframe.src = previewUrl;
}

// Auto select first row on load
document.addEventListener('DOMContentLoaded', function() {
    const firstRow = document.querySelector('.timetable-row');
    if (firstRow) {
        selectTimetable(firstRow);
    } else {
        document.getElementById('previewTitle').innerText = 'Tidak Ada Dokumen';
        document.getElementById('previewFilename').innerText = '-';
        document.getElementById('pdfFrame').classList.add('d-none');
        document.getElementById('previewEmpty').classList.remove('d-none');
        document.getElementById('previewExternalBtn').classList.add('disabled');
        document.getElementById('previewDownloadBtn').classList.add('disabled');
    }
});
</script>
@endpush
