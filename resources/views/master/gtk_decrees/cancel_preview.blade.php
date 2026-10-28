@extends('layouts.app')

@section('title', 'Pratinjau Dampak Pembatalan SK — ' . $gtkDecree->number)

@section('content')
@include('layouts._hero_header', [
    'title' => 'Pratinjau Pembatalan: ' . $gtkDecree->number,
    'subtitle' => 'Analisis simulasi dampak pencabutan/pembatalan SK (Read-Only) sebelum tindakan eksekusi pembatalan dilakukan.',
    'icon' => 'ti ti-file-x',
    'category' => 'SIMULASI PEMBATALAN SK GTK &bull; STATUS: ' . strtoupper($gtkDecree->status),
    'academicYear' => $gtkDecree->academicYear?->name ?? $gtkDecree->academic_year ?? null,
    'actions' => '<a href="' . route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) . '" class="btn btn-sm btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Kembali ke Detail SK</a>'
])

@include('master.nav')

<!-- Alert Status & Peringatan Simulasi Read-Only -->
@if($gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="ti ti-info-circle fs-1 me-3 text-info"></i>
            <div>
                <h4 class="alert-title fw-bold text-info mb-1">SK Masih Berstatus Draf</h4>
                <div class="text-secondary small">
                    Dokumen ini belum pernah diaktifkan sehingga tidak ada penugasan operasional yang pernah dibuat atau diterapkan ke sistem. Pembatalan hanya akan menandai draf dokumen ini sebagai dibatalkan tanpa berdampak pada data operasional sekolah.
                </div>
            </div>
        </div>
    </div>
@elseif($gtkDecree->status === \App\Models\GtkDecree::STATUS_CANCELLED)
    <div class="alert alert-secondary border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="ti ti-ban fs-1 me-3 text-secondary"></i>
            <div>
                <h4 class="alert-title fw-bold text-secondary mb-1">SK Sudah Dibatalkan Sebelumnya</h4>
                <div class="text-secondary small">
                    Dokumen SK ini telah berstatus dibatalkan pada tanggal {{ $gtkDecree->cancelled_at ? $gtkDecree->cancelled_at->translatedFormat('d F Y, H:i') : '-' }}. Tidak ada tindakan pembatalan ulang yang dapat dilakukan.
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start">
            <i class="ti ti-alert-triangle fs-1 me-3 text-warning mt-1"></i>
            <div>
                <h4 class="alert-title fw-bold text-dark mb-1">Mode Simulasi Pratinjau Dampak Pembatalan (Read-Only)</h4>
                <div class="text-secondary small">
                    Halaman ini menampilkan <strong>perkiraan dampak</strong> jika SK ini dibatalkan. Tidak ada perubahan yang dilakukan pada data operasional, status penugasan guru, wali kelas, hak akses, maupun jadwal pelajaran saat membuka halaman ini.
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Metrik Ringkasan Klasifikasi Dampak Pembatalan -->
<div class="row row-cards mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">Total Butir</div>
                <h2 class="m-0 fw-bold text-dark">{{ $cancellation['summary']['total'] }}</h2>
                <small class="text-muted">Rincian SK</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-danger small fw-bold text-uppercase">Kandidat Dinonaktifkan</div>
                <h2 class="m-0 fw-bold text-danger">{{ $cancellation['summary']['candidate_deactivate'] }}</h2>
                <small class="text-muted">Produk asli SK ini</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-success small fw-bold text-uppercase">Dilindungi SK Lain</div>
                <h2 class="m-0 fw-bold text-success">{{ $cancellation['summary']['protected_by_other'] }}</h2>
                <small class="text-muted">Ada SK aktif lain</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-info small fw-bold text-uppercase">Akan Dilepas</div>
                <h2 class="m-0 fw-bold text-info">{{ $cancellation['summary']['will_unlink'] }}</h2>
                <small class="text-muted">Hanya tautan SK</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-warning small fw-bold text-uppercase">Perlu Periksa Manual</div>
                <h2 class="m-0 fw-bold text-warning">{{ $cancellation['summary']['manual_review'] }}</h2>
                <small class="text-muted">Wali kelas / Jadwal KBM</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold text-uppercase">Tidak Berdampak</div>
                <h2 class="m-0 fw-bold text-secondary">{{ $cancellation['summary']['no_impact'] + $cancellation['summary']['already_inactive'] }}</h2>
                <small class="text-muted">Arsip / Nonaktif</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Analisis Rincian Dampak Pembatalan -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="card-title m-0 fw-bold text-dark">
                <i class="ti ti-list-details me-1 text-danger"></i> Rincian Analisis Perkiraan Dampak Pembatalan
            </h4>
            <small class="text-muted">Analisis dampak untuk setiap butir keputusan jika status SK ini dicabut atau dibatalkan.</small>
        </div>
        <a href="{{ route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-file-description me-1"></i> Lihat Data Asli SK
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr class="bg-light text-muted small text-uppercase">
                    <th style="width: 40px;">No</th>
                    <th>GTK Penerima</th>
                    <th>Kategori &amp; Rincian Penugasan</th>
                    <th>Hubungan Awal</th>
                    <th>Klasifikasi Dampak</th>
                    <th>Perkiraan Tindakan Jika Dibatalkan</th>
                    <th>Temuan &amp; Dasar Pertimbangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cancellation['items'] as $idx => $item)
                    <tr class="{{ $item['status'] === \App\Services\GtkDecreeCancellationPreviewService::STATUS_CANDIDATE_DEACTIVATE ? 'table-danger-subtle' : ($item['status'] === \App\Services\GtkDecreeCancellationPreviewService::STATUS_MANUAL_REVIEW ? 'table-warning-subtle' : '') }}">
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $item['user']?->name ?? 'GTK' }}</div>
                            <div class="text-muted text-xs">{{ $item['user']?->formatted_id ?? '-' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt mb-1">{{ $item['category_label'] }}</span>
                            <div class="fw-bold text-dark">
                                @if($item['category'] === \App\Models\GtkDecreeDetail::CATEGORY_MENGAJAR)
                                    {{ $item['detail']->subject?->name ?? 'Mapel belum dipilih' }}
                                    @if($item['detail']->classroom)
                                        <span class="text-muted">({{ $item['detail']->classroom->name }})</span>
                                    @endif
                                @elseif(in_array($item['category'], [\App\Models\GtkDecreeDetail::CATEGORY_STRUKTURAL, \App\Models\GtkDecreeDetail::CATEGORY_OPERASIONAL]))
                                    {{ $item['detail']->structuralPosition?->name ?? 'Jabatan belum dipilih' }}
                                    @if($item['detail']->classroom)
                                        <span class="text-muted">({{ $item['detail']->classroom->name }})</span>
                                    @endif
                                @elseif($item['category'] === \App\Models\GtkDecreeDetail::CATEGORY_PERAN_SISTEM)
                                    {{ $item['detail']->role?->name ?? 'Role belum dipilih' }}
                                @else
                                    {{ $item['detail']->notes ?: 'Catatan Arsip' }}
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $item['link_type_label'] }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $item['status_badge'] }} fw-bold">
                                {{ $item['status_label'] }}
                            </span>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">
                                {{ $item['estimated_impact'] }}
                            </div>
                        </td>
                        <td>
                            <div class="small text-muted">
                                {{ $item['reason'] }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="ti ti-list-off fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-bold">Belum Ada Butir Rincian Keputusan</div>
                            <small>Tidak ada rincian penugasan yang dapat dianalisis untuk dokumen SK ini.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-light d-flex align-items-center justify-content-between p-3 border-top flex-wrap gap-2">
        <a href="{{ route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Detail SK
        </a>

        <!-- Tombol Aksi Pembatalan (Langkah 4B) -->
        <div class="d-flex align-items-center gap-2">
            @if($canManage && $gtkDecree->status !== \App\Models\GtkDecree::STATUS_CANCELLED)
                <button type="button" class="btn btn-danger fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCancelDecree">
                    <i class="ti ti-ban me-1"></i> Batalkan Dokumen SK Ini
                </button>
            @elseif($gtkDecree->status === \App\Models\GtkDecree::STATUS_CANCELLED)
                <span class="badge bg-secondary text-white py-2 px-3">
                    <i class="ti ti-lock me-1"></i> Dokumen Ini Sudah Berstatus Dibatalkan
                </span>
            @endif
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI PEMBATALAN SK GTK ================= -->
@if($canManage && $gtkDecree->status !== \App\Models\GtkDecree::STATUS_CANCELLED)
<div class="modal fade" id="modalCancelDecree" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('gtk-decrees.cancel', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow" id="formCancelDecree" onsubmit="handleCancelSubmit(this)">
            @csrf
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-alert-triangle me-1"></i> Konfirmasi Pembatalan SK GTK
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="avatar avatar-lg bg-danger-lt text-danger rounded-circle mb-2 mx-auto">
                        <i class="ti ti-file-x fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $gtkDecree->number }}</h4>
                    <div class="text-muted small">{{ $gtkDecree->title }}</div>
                </div>

                <div class="border rounded p-3 bg-light mb-3">
                    <div class="text-muted text-xs text-uppercase fw-bold mb-2">Ringkasan Hasil Dampak Pembatalan:</div>
                    <div class="row g-2 text-center small">
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-danger fw-bold fs-3">{{ $cancellation['summary']['candidate_deactivate'] }}</div>
                                <div class="text-muted text-xs">Dinonaktifkan</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-info fw-bold fs-3">{{ $cancellation['summary']['will_unlink'] }}</div>
                                <div class="text-muted text-xs">Tautan Dilepas</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-success fw-bold fs-3">{{ $cancellation['summary']['protected_by_other'] }}</div>
                                <div class="text-muted text-xs">Dilindungi SK Lain</div>
                            </div>
                        </div>
                    </div>
                    @if($cancellation['summary']['manual_review'] > 0)
                        <div class="mt-2 text-warning small fw-bold">
                            <i class="ti ti-alert-circle me-1"></i> {{ $cancellation['summary']['manual_review'] }} penugasan tetap aktif demi kelancaran KBM sekolah (memerlukan SK pengganti).
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold text-dark">Alasan Resmi Pembatalan SK</label>
                    <textarea name="cancellation_reason" class="form-control" rows="3" placeholder="Contoh: Terjadi kesalahan penugasan jam mengajar / perubahan struktur organisasi dinas..." required minlength="3" maxlength="500"></textarea>
                    <small class="text-muted">Alasan ini akan dicatat ke dalam audit trail sistem kepegawaian.</small>
                </div>

                <div class="alert alert-danger border-0 d-flex align-items-start mb-0">
                    <i class="ti ti-alert-triangle fs-2 me-2 text-danger mt-1"></i>
                    <div class="small">
                        <strong>Peringatan Yuridis:</strong> Tindakan pembatalan akan mengubah status SK menjadi <strong>Dibatalkan</strong> dan menghentikan keabsahan penugasan terkait yang dibuat oleh SK ini. File PDF dan riwayat audit tetap dipertahankan.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" class="btn btn-danger fw-bold" id="btnSubmitCancel">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinnerCancel" role="status" aria-hidden="true"></span>
                    <i class="ti ti-ban me-1" id="iconCancel"></i>
                    <span id="textCancel">Ya, Batalkan SK Ini</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleCancelSubmit(form) {
    const btn = document.getElementById('btnSubmitCancel');
    const spinner = document.getElementById('spinnerCancel');
    const icon = document.getElementById('iconCancel');
    const text = document.getElementById('textCancel');
    if (btn) {
        btn.disabled = true;
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        if (text) text.innerText = 'Memproses Pembatalan...';
    }
}
</script>
@endif
    </div>
</div>
@endsection
