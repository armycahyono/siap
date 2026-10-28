@extends('layouts.app')

@section('title', 'Pratinjau Dampak Penerapan SK — ' . $gtkDecree->number)

@section('content')
@include('layouts._hero_header', [
    'title' => 'Pratinjau Dampak: ' . $gtkDecree->number,
    'subtitle' => 'Analisis simulasi perencanaan penugasan operasional (Read-Only) sebelum SK diaktifkan ke sistem.',
    'icon' => 'ti ti-file-search',
    'category' => 'PRATINJAU DAMPAK SK GTK &bull; TP ' . $impact['target_academic_year'],
    'academicYear' => $impact['target_academic_year'],
    'actions' => '<a href="' . route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) . '" class="btn btn-sm btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Kembali ke Detail SK</a>'
])

@include('master.nav')

<!-- Status Kesiapan Penerapan -->
@if($impact['summary']['conflict'] > 0)
    <div class="alert alert-danger border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start">
            <i class="ti ti-alert-triangle fs-1 me-3 text-danger mt-1"></i>
            <div>
                <h4 class="alert-title fw-bold text-danger mb-1">Ditemukan {{ $impact['summary']['conflict'] }} Konflik Penugasan</h4>
                <div class="text-secondary small">
                    SK ini <strong>belum siap diaktifkan</strong> karena terdapat benturan data dengan penugasan operasional yang saat ini sedang aktif (misalnya guru lain sudah mengajar rombel &amp; mapel yang sama, atau jabatan tunggal yang sedang aktif). Selesaikan konflik terlebih dahulu pada rincian SK sebelum aktivasi final.
                </div>
            </div>
        </div>
    </div>
@elseif($impact['summary']['incomplete'] > 0)
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start">
            <i class="ti ti-info-circle fs-1 me-3 text-warning mt-1"></i>
            <div>
                <h4 class="alert-title fw-bold text-warning mb-1">Terdapat {{ $impact['summary']['incomplete'] }} Butir Belum Lengkap</h4>
                <div class="text-secondary small">
                    Beberapa butir keputusan memerlukan perbaikan data atau referensi master (misalnya mata pelajaran atau jabatan belum dipilih) sebelum dapat diterapkan.
                </div>
            </div>
        </div>
    </div>
@elseif($impact['summary']['total'] === 0)
    <div class="alert alert-secondary border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="ti ti-file-off fs-2 me-2 text-secondary"></i>
            <div>
                <strong>Belum Ada Rincian Penugasan:</strong> Draf SK ini belum memiliki butir keputusan. Silakan tambahkan rincian penugasan terlebih dahulu pada halaman detail SK.
            </div>
        </div>
    </div>
@else
    <div class="alert alert-success border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="ti ti-circle-check fs-1 me-3 text-success"></i>
            <div>
                <h4 class="alert-title fw-bold text-success mb-1">SK Siap Diterapkan (Tanpa Konflik)</h4>
                <div class="text-secondary small">
                    Seluruh butir keputusan telah dianalisis. Tidak ditemukan benturan data operasional. SK ini siap dieksekusi pada rilis aktivasi final.
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Metrik Ringkasan Klasifikasi Dampak -->
<div class="row row-cards mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">Total Rincian</div>
                <h2 class="m-0 fw-bold text-dark">{{ $impact['summary']['total'] }}</h2>
                <small class="text-muted">Butir SK</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-info small fw-bold text-uppercase">Akan Ditautkan</div>
                <h2 class="m-0 fw-bold text-info">{{ $impact['summary']['will_link'] }}</h2>
                <small class="text-muted">Eksisting cocok</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-success small fw-bold text-uppercase">Akan Dibuat</div>
                <h2 class="m-0 fw-bold text-success">{{ $impact['summary']['will_create'] }}</h2>
                <small class="text-muted">Penugasan baru</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-danger small fw-bold text-uppercase">Konflik</div>
                <h2 class="m-0 fw-bold text-danger">{{ $impact['summary']['conflict'] }}</h2>
                <small class="text-muted">Benturan aktif</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-warning small fw-bold text-uppercase">Belum Lengkap</div>
                <h2 class="m-0 fw-bold text-warning">{{ $impact['summary']['incomplete'] }}</h2>
                <small class="text-muted">Perlu perbaikan</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold text-uppercase">Tanpa Penugasan</div>
                <h2 class="m-0 fw-bold text-secondary">{{ $impact['summary']['not_applicable'] }}</h2>
                <small class="text-muted">Khusus arsip</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Rincian Pratinjau Dampak -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div>
            <h4 class="card-title m-0 fw-bold text-dark">
                <i class="ti ti-table me-1 text-primary"></i> Tabel Analisis Simulasi Penerapan
            </h4>
            <small class="text-muted">Hasil perbandingan setiap butir SK terhadap data operasional aktual SIAP.</small>
        </div>
        <a href="{{ route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-pencil me-1"></i> Edit Butir Rincian SK
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr class="bg-light text-muted small text-uppercase">
                    <th style="width: 40px;">No</th>
                    <th>GTK Penerima</th>
                    <th>Kategori &amp; Butir SK</th>
                    <th>Status Analisis</th>
                    <th>Rekomendasi Tindakan</th>
                    <th>Keterangan / Temuan Sistem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($impact['items'] as $idx => $item)
                    <tr class="{{ $item['status'] === \App\Services\GtkDecreeImpactPreviewService::STATUS_CONFLICT ? 'table-danger-subtle' : '' }}">
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
                                    @if($item['detail']->teaching_hours)
                                        <span class="badge bg-purple-lt ms-1">{{ $item['detail']->teaching_hours }} Jam</span>
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
                            <span class="badge {{ $item['status_badge'] }} fw-bold">
                                {{ $item['status_label'] }}
                            </span>
                        </td>
                        <td>
                            <span class="small fw-semibold text-dark">{{ $item['recommendation'] }}</span>
                        </td>
                        <td>
                            <div class="small {{ $item['status'] === \App\Services\GtkDecreeImpactPreviewService::STATUS_CONFLICT ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ $item['notes'] }}
                            </div>

                            @if($item['conflict_comparison'])
                                <div class="mt-2 p-2 bg-white rounded border border-danger small">
                                    <div class="fw-bold text-danger mb-1"><i class="ti ti-arrows-cross me-1"></i> Detail Perbandingan Konflik:</div>
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <div class="text-muted text-xs text-uppercase fw-bold">Rencana dalam SK:</div>
                                            @foreach($item['conflict_comparison']['rencana_sk'] as $k => $v)
                                                <div><strong>{{ ucfirst(str_replace('_', ' ', $k)) }}:</strong> {{ $v }}</div>
                                            @endforeach
                                        </div>
                                        <div class="col-sm-6 border-start ps-2">
                                            <div class="text-danger text-xs text-uppercase fw-bold">Data Aktif di Sistem:</div>
                                            @php
                                                $compData = $item['conflict_comparison']['data_eksisting'] ?? ($item['conflict_comparison']['pejabat_aktif'] ?? ($item['conflict_comparison']['wali_kelas_saat_ini'] ?? []));
                                            @endphp
                                            @foreach($compData as $k => $v)
                                                <div><strong>{{ ucfirst(str_replace('_', ' ', $k)) }}:</strong> {{ $v }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="ti ti-list-off fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-bold">Belum Ada Rincian Penugasan yang Dianalisis</div>
                            <small>Tambahkan rincian penugasan pada SK ini untuk melihat simulasi dampak penerapannya.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-light d-flex align-items-center justify-content-between p-3 border-top">
        <a href="{{ route('gtk-decrees.show', ['gtkDecree' => $gtkDecree, 'tab' => 'details']) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Detail Draf SK
        </a>

        <!-- Tombol Aktivasi Terhubung ke Modal Konfirmasi (Langkah 3B) -->
        <div class="d-flex align-items-center gap-2">
            @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                @php
                    $isFuture = $gtkDecree->valid_from && $gtkDecree->valid_from->startOfDay()->gt(now()->startOfDay());
                    $isReady = $impact['summary']['is_ready_for_activation'] && ! $isFuture;
                @endphp

                @if(! $isReady)
                    <span class="text-muted small d-none d-md-inline me-2">
                        @if($isFuture)
                            <i class="ti ti-clock text-warning me-1"></i> Tanggal efektif belum tiba ({{ $gtkDecree->valid_from->format('d/m/Y') }})
                        @elseif($impact['summary']['conflict'] > 0)
                            <i class="ti ti-alert-triangle text-danger me-1"></i> Ditemukan konflik penugasan aktif
                        @elseif($impact['summary']['incomplete'] > 0)
                            <i class="ti ti-info-circle text-warning me-1"></i> Butir penugasan belum lengkap
                        @endif
                    </span>
                @endif

                <button type="button" class="btn btn-success fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalActivateDecree" {{ $isReady ? '' : 'disabled' }}>
                    <i class="ti ti-check me-1"></i> Terapkan &amp; Aktifkan Penugasan
                </button>
            @elseif($gtkDecree->status !== \App\Models\GtkDecree::STATUS_DRAFT)
                <span class="badge bg-secondary text-white py-2 px-3">
                    <i class="ti ti-lock me-1"></i> SK Sudah Berstatus {{ strtoupper($gtkDecree->status) }}
                </span>
            @endif
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI AKTIVASI SK GTK ================= -->
@if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
@php
    $isFutureEffective = $gtkDecree->valid_from && $gtkDecree->valid_from->startOfDay()->gt(now()->startOfDay());
    $hasConflict = $impact['summary']['conflict'] > 0;
    $hasIncomplete = $impact['summary']['incomplete'] > 0;
    $hasNoDetails = $impact['summary']['total'] === 0;
    $canProceedActivation = $impact['summary']['is_ready_for_activation'] && ! $isFutureEffective && ! $hasNoDetails;
@endphp
<div class="modal fade" id="modalActivateDecree" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('gtk-decrees.activate', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow" id="formActivateDecree" onsubmit="handleActivationSubmit(this)">
            @csrf
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-check-circle me-1"></i> Konfirmasi Penerapan &amp; Aktivasi SK
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="avatar avatar-lg bg-success-lt text-success rounded-circle mb-2 mx-auto">
                        <i class="ti ti-file-certificate fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $gtkDecree->number }}</h4>
                    <div class="text-muted small">{{ $gtkDecree->title }}</div>
                </div>

                <div class="border rounded p-3 bg-light mb-3">
                    <div class="text-muted text-xs text-uppercase fw-bold mb-2">Ringkasan Dampak Operasional:</div>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-success fw-bold fs-3">{{ $impact['summary']['will_create'] }}</div>
                                <div class="text-muted text-xs">Akan Dibuat</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-info fw-bold fs-3">{{ $impact['summary']['will_link'] }}</div>
                                <div class="text-muted text-xs">Akan Ditautkan</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-secondary fw-bold fs-3">{{ $impact['summary']['not_applicable'] }}</div>
                                <div class="text-muted text-xs">Arsip Saja</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($hasConflict)
                    <div class="alert alert-danger border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-alert-triangle fs-2 me-2 text-danger mt-1"></i>
                        <div>
                            <strong>Tidak Dapat Diaktifkan:</strong> Terdapat <strong>{{ $impact['summary']['conflict'] }} konflik</strong> dengan penugasan aktif. Selesaikan konflik terlebih dahulu pada rincian SK.
                        </div>
                    </div>
                @elseif($hasIncomplete)
                    <div class="alert alert-warning border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-info-circle fs-2 me-2 text-warning mt-1"></i>
                        <div>
                            <strong>Data Belum Lengkap:</strong> Terdapat <strong>{{ $impact['summary']['incomplete'] }} butir</strong> penugasan yang memerlukan data lengkap atau konfigurasi jadwal mingguan khusus.
                        </div>
                    </div>
                @elseif($hasNoDetails)
                    <div class="alert alert-secondary border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-file-off fs-2 me-2 text-secondary mt-1"></i>
                        <div>
                            <strong>Belum Ada Rincian:</strong> SK draf belum memiliki butir penugasan.
                        </div>
                    </div>
                @elseif($isFutureEffective)
                    <div class="alert alert-warning border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-clock-pause fs-2 me-2 text-warning mt-1"></i>
                        <div>
                            <strong>Tanggal Efektif Belum Tiba:</strong> SK ini mulai berlaku pada <strong>{{ $gtkDecree->valid_from->format('d/m/Y') }}</strong> (masa depan). Sistem belum mendukung aktivasi terjadwal; silakan aktifkan saat tanggal efektif tiba.
                        </div>
                    </div>
                @else
                    <div class="alert alert-success border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-info-circle fs-2 me-2 text-success mt-1"></i>
                        <div>
                            <strong>Perhatian:</strong> Aktivasi akan menerapkan penugasan ke modul operasional (KBM, Wali Kelas, Struktural) secara permanen. Status SK akan berubah menjadi <strong>Aktif</strong>. Validasi ulang akan dilakukan oleh server sebelum eksekusi.
                        </div>
                    </div>
                @endif
            </div>
            <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" class="btn btn-success fw-bold" id="btnSubmitActivation" {{ $canProceedActivation ? '' : 'disabled' }}>
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinnerActivation" role="status" aria-hidden="true"></span>
                    <i class="ti ti-check me-1" id="iconActivation"></i>
                    <span id="textActivation">Ya, Terapkan &amp; Aktifkan SK</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleActivationSubmit(form) {
    const btn = document.getElementById('btnSubmitActivation');
    const spinner = document.getElementById('spinnerActivation');
    const icon = document.getElementById('iconActivation');
    const text = document.getElementById('textActivation');
    if (btn) {
        btn.disabled = true;
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        if (text) text.innerText = 'Memproses Aktivasi...';
    }
}
</script>
@endif
    </div>
</div>
@endsection
