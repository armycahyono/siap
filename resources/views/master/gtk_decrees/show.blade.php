@extends('layouts.app')

@section('title', 'Detail SK GTK — ' . $gtkDecree->number)

@section('content')
@php
    $statusColor = match($gtkDecree->status) {
        \App\Models\GtkDecree::STATUS_DRAFT => 'warning',
        \App\Models\GtkDecree::STATUS_ACTIVE => 'success',
        \App\Models\GtkDecree::STATUS_EXPIRED => 'secondary',
        \App\Models\GtkDecree::STATUS_CANCELLED => 'danger',
        default => 'primary',
    };

    $statusLabel = \App\Models\GtkDecree::STATUSES[$gtkDecree->status] ?? $gtkDecree->status;

    $heroActions = '<div class="d-flex align-items-center gap-2 flex-wrap">';
    $heroActions .= '<a href="' . route('gtk-decrees.index') . '" class="btn btn-sm btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>';

    if ($gtkDecree->hasPrivateDocument()) {
        $heroActions .= '<a href="' . route('gtk-decrees.document', ['gtkDecree' => $gtkDecree, 'preview' => 1]) . '" target="_blank" class="btn btn-sm btn-light text-dark fw-bold"><i class="ti ti-file-text me-1"></i> Pratinjau PDF</a>';
        $heroActions .= '<a href="' . route('gtk-decrees.document', $gtkDecree) . '" class="btn btn-sm btn-outline-light"><i class="ti ti-download me-1"></i> Unduh PDF</a>';
    }

    if ($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT) {
        $heroActions .= '<a href="' . route('gtk-decrees.edit', $gtkDecree) . '" class="btn btn-sm btn-warning text-dark fw-bold"><i class="ti ti-edit me-1"></i> Edit Draf</a>';
        $heroActions .= '<button type="button" class="btn btn-sm btn-success text-white fw-bold" data-bs-toggle="modal" data-bs-target="#modalActivateDecree"><i class="ti ti-check me-1"></i> Aktifkan SK</button>';
    }
    $heroActions .= '<a href="' . route('gtk-decrees.preview', $gtkDecree) . '" class="btn btn-sm btn-info text-white fw-bold"><i class="ti ti-file-search me-1"></i> Pratinjau Dampak</a>';
    if ($canManage) {
        $heroActions .= '<a href="' . route('gtk-decrees.cancel-preview', $gtkDecree) . '" class="btn btn-sm btn-outline-danger fw-bold"><i class="ti ti-file-x me-1"></i> Pratinjau Pembatalan</a>';
    }
    $heroActions .= '</div>';
@endphp

@include('layouts._hero_header', [
    'title' => 'SK: ' . $gtkDecree->number,
    'subtitle' => $gtkDecree->title,
    'icon' => 'ti ti-file-certificate',
    'category' => 'ARSIP SK GTK &bull; STATUS: ' . strtoupper($statusLabel),
    'academicYear' => $gtkDecree->academicYear?->name ?? $gtkDecree->academic_year ?? null,
    'actions' => $heroActions
])

@include('master.nav')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-center">
            <i class="ti ti-circle-check fs-2 me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-center">
            <i class="ti ti-alert-triangle fs-2 me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="ti ti-alert-triangle fs-2 me-2 mt-1"></i>
            <div>
                <div class="fw-bold mb-1">Terdapat kesalahan pada input:</div>
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

@if($gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
    <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <i class="ti ti-info-circle fs-1 me-3 text-warning"></i>
            <div>
                <strong class="d-block text-dark">Mode Penyusunan Draf SK GTK</strong>
                <span class="small text-muted">
                    SK ini berstatus <strong>Draf</strong>. Penerima dan rincian penugasan di bawah belum diterapkan ke sistem operasional pembelajaran atau hak akses aplikasi.
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($canManage)
                <button type="button" class="btn btn-sm btn-success fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalActivateDecree">
                    <i class="ti ti-check me-1"></i> Terapkan &amp; Aktifkan Penugasan
                </button>
            @endif
        </div>
    </div>
@else
    <div class="alert alert-secondary border-0 shadow-sm mb-4 d-flex align-items-center">
        <i class="ti ti-lock fs-2 me-2 text-secondary"></i>
        <div>
            <strong>Status Arsip: {{ $statusLabel }}</strong> — Dokumen ini bersifat arsip baca-saja. Perubahan metadata dan rincian telah dikunci untuk menjaga integritas data kepegawaian.
        </div>
    </div>
@endif

<!-- Tabs Navigasi Detail SK -->
@php
    $currentTab = request()->query('tab', 'summary');
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-bottom p-2 bg-white">
        <ul class="nav nav-tabs card-header-tabs border-0 gap-2">
            <li class="nav-item">
                <a class="nav-link {{ $currentTab === 'summary' ? 'active fw-bold' : '' }}" href="#tab-summary" data-bs-toggle="tab">
                    <i class="ti ti-file-info me-1"></i> Ringkasan &amp; Dokumen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $currentTab === 'recipients' ? 'active fw-bold' : '' }}" href="#tab-recipients" data-bs-toggle="tab">
                    <i class="ti ti-users me-1"></i> Penerima GTK
                    <span class="badge bg-secondary-lt ms-1">{{ $gtkDecree->recipients->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $currentTab === 'details' ? 'active fw-bold' : '' }}" href="#tab-details" data-bs-toggle="tab">
                    <i class="ti ti-list-check me-1"></i> Rincian Keputusan
                    <span class="badge bg-primary-lt ms-1">{{ $gtkDecree->details->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $currentTab === 'preview' ? 'active fw-bold' : '' }}" href="#tab-preview" data-bs-toggle="tab">
                    <i class="ti ti-file-search me-1"></i> Pratinjau Dampak
                    @if(isset($impactPreview))
                        @if($impactPreview['summary']['conflict'] > 0)
                            <span class="badge bg-danger text-white ms-1">{{ $impactPreview['summary']['conflict'] }} Konflik</span>
                        @elseif($impactPreview['summary']['incomplete'] > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $impactPreview['summary']['incomplete'] }} Belum Lengkap</span>
                        @else
                            <span class="badge bg-success text-white ms-1">Siap</span>
                        @endif
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $currentTab === 'audit' ? 'active fw-bold' : '' }}" href="#tab-audit" data-bs-toggle="tab">
                    <i class="ti ti-history me-1"></i> Jejak Audit
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        <!-- ================= TAB 1: RINGKASAN & DOKUMEN ================= -->
        <div class="tab-pane fade {{ $currentTab === 'summary' ? 'show active' : '' }}" id="tab-summary">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="ti ti-file-description me-1 text-primary"></i> Informasi Naskah Dinas SK
                        </h5>

                        <table class="table table-sm table-borderless text-dark mb-4">
                            <tbody>
                                <tr>
                                    <td class="text-muted" style="width: 200px;">Nomor SK Resmi</td>
                                    <td class="fw-bold">: {{ $gtkDecree->number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Judul / Perihal</td>
                                    <td class="fw-bold">: {{ $gtkDecree->title }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jenis SK</td>
                                    <td>: <span class="badge bg-blue-lt">{{ \App\Models\GtkDecree::TYPES[$gtkDecree->type] ?? $gtkDecree->type }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tahun Ajaran</td>
                                    <td>: {{ $gtkDecree->academicYear?->name ?? $gtkDecree->academic_year ?? 'Semua / Tidak Terikat' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Penetapan</td>
                                    <td>: {{ $gtkDecree->issued_date ? $gtkDecree->issued_date->translatedFormat('d F Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">TMT (Terhitung Mulai)</td>
                                    <td>: <span class="badge bg-azure-lt">{{ $gtkDecree->tmt_date ? $gtkDecree->tmt_date->translatedFormat('d F Y') : '-' }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Masa Berlaku Penugasan</td>
                                    <td>: 
                                        {{ $gtkDecree->valid_from ? $gtkDecree->valid_from->translatedFormat('d F Y') : '-' }}
                                        s.d {{ $gtkDecree->valid_until ? $gtkDecree->valid_until->translatedFormat('d F Y') : 'Selesai / Terbuka' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Pejabat Penetap</td>
                                    <td>: {{ $gtkDecree->signer_name ?: '-' }} ({{ $gtkDecree->signer_title ?: 'Kepala Sekolah' }})</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status Dokumen</td>
                                    <td>: <span class="badge bg-{{ $statusColor }} text-uppercase">{{ $statusLabel }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Konsiderans / Catatan</td>
                                    <td>: {{ $gtkDecree->description ?: '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="col-lg-5">
                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="ti ti-file-text me-1 text-primary"></i> Berkas Dokumen PDF Resmi
                        </h5>

                        @if($gtkDecree->file_path && $gtkDecree->hasPrivateDocument())
                            <div class="card bg-light border p-3 mb-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar avatar-lg bg-danger text-white rounded me-3">
                                        <i class="ti ti-file-text fs-1"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-truncate" title="{{ $gtkDecree->original_filename }}">{{ $gtkDecree->original_filename ?: 'Dokumen_SK.pdf' }}</div>
                                        <small class="text-muted d-block">Ukuran: {{ round($gtkDecree->file_size / 1024, 1) }} KB &bull; PDF</small>
                                        <small class="text-success"><i class="ti ti-lock me-1"></i> Tersimpan di disk privat aman</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('gtk-decrees.document', ['gtkDecree' => $gtkDecree, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-danger flex-fill">
                                        <i class="ti ti-eye me-1"></i> Buka Pratinjau
                                    </a>
                                    <a href="{{ route('gtk-decrees.document', $gtkDecree) }}" class="btn btn-sm btn-danger flex-fill">
                                        <i class="ti ti-download me-1"></i> Unduh Berkas
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="card bg-light border-dashed p-4 text-center mb-3">
                                <i class="ti ti-file-off fs-1 text-muted d-block mb-2"></i>
                                <div class="fw-bold text-dark">Belum Ada Dokumen PDF</div>
                                <small class="text-muted">Berkas lampiran SK fisik belum diunggah.</small>
                            </div>
                        @endif

                        @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                            <div class="card border p-3">
                                <h6 class="fw-bold mb-2 text-dark">
                                    <i class="ti ti-upload me-1"></i> {{ $gtkDecree->file_path ? 'Ganti Berkas PDF' : 'Unggah Berkas PDF' }}
                                </h6>
                                <form action="{{ route('gtk-decrees.update', $gtkDecree) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <!-- Hidden metadata mirror -->
                                    <input type="hidden" name="number" value="{{ $gtkDecree->number }}">
                                    <input type="hidden" name="title" value="{{ $gtkDecree->title }}">
                                    <input type="hidden" name="type" value="{{ $gtkDecree->type }}">
                                    <input type="hidden" name="academic_year_id" value="{{ $gtkDecree->academic_year_id }}">
                                    <input type="hidden" name="issued_date" value="{{ $gtkDecree->issued_date ? $gtkDecree->issued_date->format('Y-m-d') : '' }}">
                                    <input type="hidden" name="tmt_date" value="{{ $gtkDecree->tmt_date ? $gtkDecree->tmt_date->format('Y-m-d') : '' }}">
                                    <input type="hidden" name="valid_from" value="{{ $gtkDecree->valid_from ? $gtkDecree->valid_from->format('Y-m-d') : '' }}">
                                    <input type="hidden" name="valid_until" value="{{ $gtkDecree->valid_until ? $gtkDecree->valid_until->format('Y-m-d') : '' }}">
                                    <input type="hidden" name="signer_name" value="{{ $gtkDecree->signer_name }}">
                                    <input type="hidden" name="signer_title" value="{{ $gtkDecree->signer_title }}">

                                    <div class="mb-2">
                                        <input type="file" name="pdf_document" accept=".pdf,application/pdf" class="form-control form-control-sm" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="ti ti-check me-1"></i> Unggah PDF Sekarang
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: PENERIMA GTK ================= -->
        <div class="tab-pane fade {{ $currentTab === 'recipients' ? 'show active' : '' }}" id="tab-recipients">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark m-0">Daftar GTK Penerima SK</h5>
                        <small class="text-muted">Personil yang tercantum dalam naskah keputusan resmi (dapat memuat GTK tanpa penugasan operasional langsung).</small>
                    </div>
                    @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                        <button type="button" class="btn btn-sm btn-primary shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddRecipients">
                            <i class="ti ti-user-plus me-1"></i> Tambah Penerima GTK
                        </button>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                        <thead>
                            <tr class="bg-light text-muted small text-uppercase">
                                <th style="width: 40px;">No</th>
                                <th>Nama Lengkap GTK</th>
                                <th>Identitas (NIP/NIGS)</th>
                                <th>Status / Peran Akun</th>
                                <th>Butir Keputusan</th>
                                <th>Catatan Penerima</th>
                                @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                                    <th class="text-end" style="width: 80px;">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gtkDecree->recipients as $idx => $recipient)
                                <tr>
                                    <td class="text-muted">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $recipient->user?->name }}</div>
                                        <div class="text-muted text-xs">{{ $recipient->user?->email ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark font-monospace">
                                            {{ $recipient->user?->formatted_id ?: '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-lt">
                                            {{ $recipient->user?->role?->name ?? 'GTK' }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $detailCount = $recipient->details->count();
                                        @endphp
                                        @if($detailCount > 0)
                                            <span class="badge bg-azure-lt fw-bold">
                                                <i class="ti ti-check me-1"></i> {{ $detailCount }} Butir Tugas
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-lt text-muted">
                                                Tanpa rincian tugas (Arsip/SK Kolektif)
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="small text-muted">{{ $recipient->notes ?: '-' }}</span>
                                    </td>
                                    @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                                        <td class="text-end">
                                            <form action="{{ route('gtk-decrees.recipients.destroy', ['gtkDecree' => $gtkDecree, 'recipient' => $recipient]) }}" method="POST" onsubmit="return confirm('Hapus {{ $recipient->user?->name }} dari daftar penerima SK? Seluruh butir rincian tugas terkait dalam draf ini juga akan terhapus.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="Hapus dari Draf">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="ti ti-users fs-1 d-block mb-2 text-secondary"></i>
                                        <div class="fw-bold">Belum Ada Penerima GTK</div>
                                        <small>Klik tombol <strong>"Tambah Penerima GTK"</strong> di atas untuk menambahkan guru atau staf ke draf SK ini.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: RINCIAN KEPUTUSAN ================= -->
        <div class="tab-pane fade {{ $currentTab === 'details' ? 'show active' : '' }}" id="tab-details">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark m-0">Rincian Butir Keputusan SK</h5>
                        <small class="text-muted">Daftar penugasan KBM, tugas tambahan, struktural, kepanitiaan, atau arsip dokumentasi.</small>
                    </div>
                    @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddBulkDetails">
                                <i class="ti ti-table-plus me-1"></i> Input Massal (Mode B)
                            </button>
                            <button type="button" class="btn btn-sm btn-primary shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddDetail">
                                <i class="ti ti-plus me-1"></i> Tambah Rincian (Mode A)
                            </button>
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                        <thead>
                            <tr class="bg-light text-muted small text-uppercase">
                                <th style="width: 40px;">No</th>
                                <th>GTK Penerima</th>
                                <th>Kategori</th>
                                <th>Penugasan / Jabatan / Mapel</th>
                                <th>Rombel / Kelas</th>
                                <th>Jam/Mg</th>
                                <th>Masa Berlaku</th>
                                <th>Status Penerapan</th>
                                <th>Asal</th>
                                @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                                    <th class="text-end" style="width: 60px;">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gtkDecree->details as $idx => $detail)
                                <tr>
                                    <td class="text-muted">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $detail->user?->name }}</div>
                                        <div class="text-muted text-xs">{{ $detail->user?->formatted_id }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-blue-lt">
                                            {{ \App\Models\GtkDecreeDetail::CATEGORIES[$detail->assignment_category] ?? $detail->assignment_category }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($detail->assignment_category === \App\Models\GtkDecreeDetail::CATEGORY_MENGAJAR)
                                            <div class="fw-bold">{{ $detail->subject?->name ?? '-' }}</div>
                                            <small class="text-muted">Kode: {{ $detail->subject?->code ?? '-' }}</small>
                                        @elseif(in_array($detail->assignment_category, [\App\Models\GtkDecreeDetail::CATEGORY_STRUKTURAL, \App\Models\GtkDecreeDetail::CATEGORY_OPERASIONAL]))
                                            <div class="fw-bold">{{ $detail->structuralPosition?->name ?? '-' }}</div>
                                            <small class="text-muted">Kategori: {{ $detail->structuralPosition?->category ?? '-' }}</small>
                                        @elseif($detail->assignment_category === \App\Models\GtkDecreeDetail::CATEGORY_PERAN_SISTEM)
                                            <div class="fw-bold">{{ $detail->role?->name ?? '-' }}</div>
                                            <small class="text-muted">Hak Akses Sistem</small>
                                        @elseif($detail->assignment_category === \App\Models\GtkDecreeDetail::CATEGORY_ARSIP)
                                            <div class="fw-bold text-muted fst-italic">Arsip / Catatan Resmi</div>
                                            <small class="text-muted">{{ $detail->notes ?: '-' }}</small>
                                        @else
                                            <div>{{ $detail->notes ?: '-' }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-secondary">
                                            {{ $detail->classroom?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($detail->teaching_hours)
                                            <span class="badge bg-purple-lt fw-bold">{{ $detail->teaching_hours }} Jam</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-xs text-muted">
                                            {{ $detail->valid_from ? $detail->valid_from->format('d/m/Y') : '-' }}
                                            s.d {{ $detail->valid_until ? $detail->valid_until->format('d/m/Y') : 'Selesai' }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($detail->implementation_status === \App\Models\GtkDecreeDetail::STATUS_PENDING)
                                            <span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i> Menunggu</span>
                                        @elseif($detail->implementation_status === \App\Models\GtkDecreeDetail::STATUS_APPLIED)
                                            <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i> Diterapkan</span>
                                        @elseif($detail->implementation_status === \App\Models\GtkDecreeDetail::STATUS_SKIPPED)
                                            <span class="badge bg-secondary text-white"><i class="ti ti-archive me-1"></i> Dilewati (Arsip)</span>
                                        @elseif($detail->implementation_status === \App\Models\GtkDecreeDetail::STATUS_CONFLICT)
                                            <span class="badge bg-danger text-white"><i class="ti ti-alert-triangle me-1"></i> Konflik</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $detail->implementation_status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-muted small text-uppercase">
                                            {{ \App\Models\GtkDecreeDetail::LINK_TYPES[$detail->link_type] ?? $detail->link_type }}
                                        </span>
                                    </td>
                                    @if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
                                        <td class="text-end">
                                            <form action="{{ route('gtk-decrees.details.destroy', ['gtkDecree' => $gtkDecree, 'detail' => $detail]) }}" method="POST" onsubmit="return confirm('Hapus butir rincian tugas ini dari draf SK?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="Hapus Butir">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="ti ti-list-check fs-1 d-block mb-2 text-secondary"></i>
                                        <div class="fw-bold">Belum Ada Rincian Butir Keputusan</div>
                                        <small>Klik <strong>"Tambah Rincian"</strong> (Mode A) atau <strong>"Input Massal"</strong> (Mode B) di atas untuk menambahkan butir penugasan.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: JEJAK AUDIT ================= -->
        <div class="tab-pane fade {{ $currentTab === 'audit' ? 'show active' : '' }}" id="tab-audit">
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="ti ti-history me-1 text-primary"></i> Jejak Audit &amp; Riwayat Modifikasi
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card bg-light border p-3">
                            <div class="text-muted small text-uppercase fw-bold">Pembuat Draf Pertama</div>
                            <div class="fw-bold fs-3 text-dark mt-1">{{ $gtkDecree->createdBy?->name ?? 'Sistem' }}</div>
                            <small class="text-muted">Waktu: {{ $gtkDecree->created_at ? $gtkDecree->created_at->translatedFormat('d F Y, H:i:s') : '-' }}</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card bg-light border p-3">
                            <div class="text-muted small text-uppercase fw-bold">Pengubah Terakhir</div>
                            <div class="fw-bold fs-3 text-dark mt-1">{{ $gtkDecree->updatedBy?->name ?? ($gtkDecree->createdBy?->name ?? 'Sistem') }}</div>
                            <small class="text-muted">Waktu: {{ $gtkDecree->updated_at ? $gtkDecree->updated_at->translatedFormat('d F Y, H:i:s') : '-' }}</small>
                        </div>
                    </div>

                    @if($gtkDecree->activated_at)
                        <div class="col-md-6">
                            <div class="card bg-success-lt border p-3">
                                <div class="text-success small text-uppercase fw-bold">Waktu Aktivasi SK</div>
                                <div class="fw-bold fs-3 text-dark mt-1">{{ $gtkDecree->activatedBy?->name ?? 'Sistem' }}</div>
                                <small class="text-muted">Waktu: {{ $gtkDecree->activated_at->translatedFormat('d F Y, H:i:s') }}</small>
                            </div>
                        </div>
                    @endif

                    @if($gtkDecree->cancelled_at)
                        <div class="col-md-6">
                            <div class="card bg-danger-lt border p-3">
                                <div class="text-danger small text-uppercase fw-bold">Waktu Pembatalan SK</div>
                                <div class="fw-bold fs-3 text-dark mt-1">{{ $gtkDecree->cancelledBy?->name ?? 'Sistem' }}</div>
                                <small class="text-muted">Waktu: {{ $gtkDecree->cancelled_at->translatedFormat('d F Y, H:i:s') }}</small>
                                <div class="mt-2 text-danger small"><strong>Alasan:</strong> {{ $gtkDecree->cancellation_reason ?: '-' }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: PRATINJAU DAMPAK (READ-ONLY) ================= -->
        <div class="tab-pane fade {{ $currentTab === 'preview' ? 'show active' : '' }}" id="tab-preview">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark m-0">Pratinjau Dampak Penerapan SK (Simulasi Read-Only)</h5>
                        <small class="text-muted">Analisis rencana perubahan penugasan operasional di sistem sebelum SK diaktifkan.</small>
                    </div>
                    <a href="{{ route('gtk-decrees.preview', $gtkDecree) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-external-link me-1"></i> Buka Halaman Penuh Pratinjau
                    </a>
                </div>

                @if(isset($impactPreview))
                    <!-- Metrik Ringkas -->
                    <div class="row row-cards mb-4">
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-muted text-xs fw-bold text-uppercase">Total Butir</div>
                                    <h3 class="m-0 fw-bold text-dark">{{ $impactPreview['summary']['total'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-info text-xs fw-bold text-uppercase">Akan Ditautkan</div>
                                    <h3 class="m-0 fw-bold text-info">{{ $impactPreview['summary']['will_link'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-success text-xs fw-bold text-uppercase">Akan Dibuat</div>
                                    <h3 class="m-0 fw-bold text-success">{{ $impactPreview['summary']['will_create'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-danger text-xs fw-bold text-uppercase">Konflik</div>
                                    <h3 class="m-0 fw-bold text-danger">{{ $impactPreview['summary']['conflict'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-warning text-xs fw-bold text-uppercase">Belum Lengkap</div>
                                    <h3 class="m-0 fw-bold text-warning">{{ $impactPreview['summary']['incomplete'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <div class="card bg-light border-0 shadow-xs">
                                <div class="card-body p-2 text-center">
                                    <div class="text-secondary text-xs fw-bold text-uppercase">Arsip Saja</div>
                                    <h3 class="m-0 fw-bold text-secondary">{{ $impactPreview['summary']['not_applicable'] }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Pratinjau Cepat -->
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr class="bg-light text-muted small text-uppercase">
                                    <th style="width: 40px;">No</th>
                                    <th>GTK Penerima</th>
                                    <th>Kategori &amp; Butir SK</th>
                                    <th>Status Analisis</th>
                                    <th>Rekomendasi Tindakan</th>
                                    <th>Catatan / Benturan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($impactPreview['items'] as $pIdx => $pItem)
                                    <tr class="{{ $pItem['status'] === \App\Services\GtkDecreeImpactPreviewService::STATUS_CONFLICT ? 'table-danger-subtle' : '' }}">
                                        <td class="text-muted">{{ $pIdx + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $pItem['user']?->name ?? 'GTK' }}</div>
                                            <div class="text-muted text-xs">{{ $pItem['user']?->formatted_id ?? '-' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-blue-lt mb-1">{{ $pItem['category_label'] }}</span>
                                            <div class="fw-bold text-dark small">
                                                @if($pItem['category'] === \App\Models\GtkDecreeDetail::CATEGORY_MENGAJAR)
                                                    {{ $pItem['detail']->subject?->name ?? 'Mapel belum dipilih' }}
                                                    @if($pItem['detail']->classroom)
                                                        <span class="text-muted">({{ $pItem['detail']->classroom->name }})</span>
                                                    @endif
                                                @elseif(in_array($pItem['category'], [\App\Models\GtkDecreeDetail::CATEGORY_STRUKTURAL, \App\Models\GtkDecreeDetail::CATEGORY_OPERASIONAL]))
                                                    {{ $pItem['detail']->structuralPosition?->name ?? 'Jabatan belum dipilih' }}
                                                @elseif($pItem['category'] === \App\Models\GtkDecreeDetail::CATEGORY_PERAN_SISTEM)
                                                    {{ $pItem['detail']->role?->name ?? 'Role belum dipilih' }}
                                                @else
                                                    {{ $pItem['detail']->notes ?: 'Catatan Arsip' }}
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $pItem['status_badge'] }} fw-bold">
                                                {{ $pItem['status_label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold text-dark">{{ $pItem['recommendation'] }}</span>
                                        </td>
                                        <td>
                                            <div class="small {{ $pItem['status'] === \App\Services\GtkDecreeImpactPreviewService::STATUS_CONFLICT ? 'text-danger fw-bold' : 'text-muted' }}">
                                                {{ $pItem['notes'] }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Belum ada rincian penugasan yang dapat dianalisis.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL TAMBAH PENERIMA GTK ================= -->
@if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
<div class="modal fade" id="modalAddRecipients" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('gtk-decrees.recipients.store', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-user-plus me-1"></i> Tambah Personil GTK Penerima SK
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Pilih satu atau beberapa personil GTK yang tercantum dalam naskah dinas SK ini. GTK yang sudah terdaftar akan otomatis dilewati agar tidak terjadi duplikasi.
                </p>

                <div class="mb-3">
                    <label class="form-label required">Pilih Personil GTK (Pilih Satu atau Lebih)</label>
                    <select name="user_ids[]" class="form-select" size="8" multiple required>
                        @foreach($operationalPersonnel as $person)
                            @php
                                $isAlreadyAdded = $gtkDecree->recipients->pluck('user_id')->contains($person->id);
                            @endphp
                            <option value="{{ $person->id }}" {{ $isAlreadyAdded ? 'disabled' : '' }}>
                                {{ $person->name }} ({{ $person->formatted_id }}) — {{ $person->role?->name }} {{ $isAlreadyAdded ? '[Sudah Terdaftar]' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint text-muted">Tekan tombol <strong>Ctrl</strong> (atau Cmd di Mac) sambil mengklik untuk memilih beberapa GTK sekaligus.</small>
                </div>

                <div class="mb-2">
                    <label class="form-label">Catatan Penerima (Opsional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Contoh: Lampiran I Nomor Urut 01">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-plus me-1"></i> Tambahkan ke Draf SK
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL TAMBAH RINCIAN TUNGGAL (MODE A) ================= -->
<div class="modal fade" id="modalAddDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('gtk-decrees.details.store', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-plus me-1"></i> Tambah Butir Rincian Keputusan (Mode A)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label required">Personil GTK Penerima</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Pilih GTK --</option>
                            @foreach($gtkDecree->recipients as $rec)
                                <option value="{{ $rec->user_id }}">{{ $rec->user?->name }} ({{ $rec->user?->formatted_id }})</option>
                            @endforeach
                            <!-- Opsi tambahan dari seluruh GTK jika belum terdaftar -->
                            <optgroup label="Personil Lainnya (Otomatis ditambahkan sebagai penerima)">
                                @foreach($operationalPersonnel as $p)
                                    @if(!$gtkDecree->recipients->pluck('user_id')->contains($p->id))
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->formatted_id }})</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label required">Kategori Penugasan</label>
                        <select name="assignment_category" id="modalCategorySelect" class="form-select" required onchange="updateDynamicDetailFields()">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach(\App\Models\GtkDecreeDetail::CATEGORIES as $ckey => $clabel)
                                <option value="{{ $ckey }}">{{ $clabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Dynamic Fields: Mengajar -->
                    <div class="col-md-6 dynamic-field" id="field-subject" style="display: none;">
                        <label class="form-label required">Mata Pelajaran Diampu</label>
                        <select name="subject_id" class="form-select">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->code }} - {{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 dynamic-field" id="field-classroom" style="display: none;">
                        <label class="form-label">Rombongan Belajar / Kelas</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">-- Semua / Tidak Terikat Kelas Tertentu --</option>
                            @foreach($classrooms as $cls)
                                <option value="{{ $cls->id }}">{{ $cls->name }} (TP {{ $cls->academic_year }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 dynamic-field" id="field-teaching-hours" style="display: none;">
                        <label class="form-label">Jam Mengajar (Tatap Muka / Minggu)</label>
                        <input type="number" name="teaching_hours" class="form-control" min="1" max="60" placeholder="Contoh: 4">
                    </div>

                    <!-- Dynamic Fields: Struktural & Operasional -->
                    <div class="col-md-8 dynamic-field" id="field-structural" style="display: none;">
                        <label class="form-label required">Jabatan Struktural / Operasional</label>
                        <select name="structural_position_id" class="form-select">
                            <option value="">-- Pilih Jabatan --</option>
                            @foreach($structuralPositions as $sp)
                                <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->category ?? 'Struktural' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Dynamic Fields: Peran Sistem -->
                    <div class="col-md-6 dynamic-field" id="field-role" style="display: none;">
                        <label class="form-label required">Peran Hak Akses Sistem (Role)</label>
                        <select name="role_id" class="form-select">
                            <option value="">-- Pilih Role --</option>
                            @foreach($roles as $rl)
                                <option value="{{ $rl->id }}">{{ $rl->name }} ({{ $rl->slug }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Rentang Tanggal Spesifik Butir -->
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Mulai Butir Tugas</label>
                        <input type="date" name="valid_from" class="form-control" value="{{ $gtkDecree->valid_from ? $gtkDecree->valid_from->format('Y-m-d') : '' }}">
                        <small class="form-hint text-muted">Kosongkan jika sama dengan tanggal mulai SK.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Akhir Butir Tugas</label>
                        <input type="date" name="valid_until" class="form-control" value="{{ $gtkDecree->valid_until ? $gtkDecree->valid_until->format('Y-m-d') : '' }}">
                        <small class="form-hint text-muted">Kosongkan jika sama dengan tanggal akhir SK.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan / Keterangan Butir Keputusan</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Tambahkan rincian keterangan tugas spesifik..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-plus me-1"></i> Simpan Butir Keputusan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL INPUT MASSAL (MODE B) ================= -->
<div class="modal fade" id="modalAddBulkDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <form action="{{ route('gtk-decrees.details.bulk-store', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-table-plus me-1"></i> Input Massal Butir Keputusan (Mode B)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted small mb-2">
                    Tambahkan beberapa butir penugasan sekaligus dalam format tabel. Sangat praktis untuk menginput penugasan mengajar KBM atau pembagian wali kelas secara beruntun.
                </p>

                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-bordered table-sm align-middle" id="bulkTable">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th style="width: 25%;">Personil GTK *</th>
                                <th style="width: 18%;">Kategori *</th>
                                <th style="width: 22%;">Mapel / Jabatan</th>
                                <th style="width: 15%;">Kelas / Rombel</th>
                                <th style="width: 10%;">Jam/Mg</th>
                                <th style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bulkTableBody">
                            <!-- Baris template dinamis akan di-generate via JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addBulkRow()">
                        <i class="ti ti-plus me-1"></i> Tambah Baris Baru
                    </button>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Seluruh Baris Massal
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ================= MODAL KONFIRMASI AKTIVASI SK GTK ================= -->
@if($canManage && $gtkDecree->status === \App\Models\GtkDecree::STATUS_DRAFT)
@php
    $isFutureEffective = $gtkDecree->valid_from && $gtkDecree->valid_from->startOfDay()->gt(now()->startOfDay());
    $hasConflict = isset($impactPreview) && $impactPreview['summary']['conflict'] > 0;
    $hasIncomplete = isset($impactPreview) && $impactPreview['summary']['incomplete'] > 0;
    $hasNoDetails = isset($impactPreview) && $impactPreview['summary']['total'] === 0;
    $canProceedActivation = isset($impactPreview) && $impactPreview['summary']['is_ready_for_activation'] && ! $isFutureEffective && ! $hasNoDetails;
@endphp
<div class="modal fade" id="modalActivateDecree" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('gtk-decrees.activate', $gtkDecree) }}" method="POST" class="modal-content border-0 shadow" id="formActivateDecree" onsubmit="handleActivationSubmit(this)">
            @csrf
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-check-circle me-1"></i> Konfirmasi Aktivasi SK GTK
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
                    <div class="text-muted text-xs text-uppercase fw-bold mb-2">Simulasi Dampak Penerapan Operasional:</div>
                    @if(isset($impactPreview))
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-success fw-bold fs-3">{{ $impactPreview['summary']['will_create'] }}</div>
                                    <div class="text-muted text-xs">Akan Dibuat</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-info fw-bold fs-3">{{ $impactPreview['summary']['will_link'] }}</div>
                                    <div class="text-muted text-xs">Akan Ditautkan</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-secondary fw-bold fs-3">{{ $impactPreview['summary']['not_applicable'] }}</div>
                                    <div class="text-muted text-xs">Arsip Saja</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @if($hasConflict)
                    <div class="alert alert-danger border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-alert-triangle fs-2 me-2 text-danger mt-1"></i>
                        <div>
                            <strong>Tidak Dapat Diaktifkan:</strong> Ditemukan <strong>{{ $impactPreview['summary']['conflict'] }} konflik</strong> dengan penugasan operasional aktif. Harap selesaikan konflik terlebih dahulu melalui tab Pratinjau Dampak.
                        </div>
                    </div>
                @elseif($hasIncomplete)
                    <div class="alert alert-warning border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-info-circle fs-2 me-2 text-warning mt-1"></i>
                        <div>
                            <strong>Data Belum Lengkap:</strong> Terdapat <strong>{{ $impactPreview['summary']['incomplete'] }} butir</strong> penugasan yang belum lengkap atau memerlukan konfigurasi jadwal khusus pada modul terkait.
                        </div>
                    </div>
                @elseif($hasNoDetails)
                    <div class="alert alert-secondary border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-file-off fs-2 me-2 text-secondary mt-1"></i>
                        <div>
                            <strong>Belum Ada Rincian:</strong> Draf SK belum memiliki butir rincian penugasan untuk diterapkan.
                        </div>
                    </div>
                @elseif($isFutureEffective)
                    <div class="alert alert-warning border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-clock-pause fs-2 me-2 text-warning mt-1"></i>
                        <div>
                            <strong>Tanggal Efektif Belum Tiba:</strong> SK ini terhitung mulai berlaku tanggal <strong>{{ $gtkDecree->valid_from->format('d/m/Y') }}</strong> (masa depan). Sistem belum mendukung aktivasi terjadwal; silakan aktifkan saat tanggal efektif tiba.
                        </div>
                    </div>
                @else
                    <div class="alert alert-success border-0 d-flex align-items-start mb-0">
                        <i class="ti ti-info-circle fs-2 me-2 text-success mt-1"></i>
                        <div>
                            <strong>Perhatian:</strong> Aktivasi akan menerapkan penugasan ke modul operasional (KBM, Wali Kelas, Struktural) secara permanen. Status SK akan berubah menjadi <strong>Aktif</strong> dan pengeditan draf akan dikunci. Validasi ulang akan dilakukan oleh server sebelum eksekusi.
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
@endif

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
function updateDynamicDetailFields() {
    const cat = document.getElementById('modalCategorySelect').value;
    const allDynamic = document.querySelectorAll('.dynamic-field');
    allDynamic.forEach(el => el.style.display = 'none');

    if (cat === 'mengajar') {
        document.getElementById('field-subject').style.display = 'block';
        document.getElementById('field-classroom').style.display = 'block';
        document.getElementById('field-teaching-hours').style.display = 'block';
    } else if (cat === 'struktural' || cat === 'operasional') {
        document.getElementById('field-structural').style.display = 'block';
        document.getElementById('field-classroom').style.display = 'block';
    } else if (cat === 'peran_sistem') {
        document.getElementById('field-role').style.display = 'block';
    }
}

let bulkRowIndex = 0;
const personnelOptions = `
    <option value="">-- Pilih GTK --</option>
    @foreach($operationalPersonnel as $p)
        <option value="{{ $p->id }}">{{ addslashes($p->name) }}</option>
    @endforeach
`;

const subjectOptions = `
    <option value="">-- Pilih Mapel --</option>
    @foreach($subjects as $sub)
        <option value="{{ $sub->id }}">{{ addslashes($sub->name) }}</option>
    @endforeach
`;

const classroomOptions = `
    <option value="">-- Rombel (Opsional) --</option>
    @foreach($classrooms as $cls)
        <option value="{{ $cls->id }}">{{ addslashes($cls->name) }}</option>
    @endforeach
`;

const structuralOptions = `
    <option value="">-- Pilih Jabatan --</option>
    @foreach($structuralPositions as $sp)
        <option value="{{ $sp->id }}">{{ addslashes($sp->name) }}</option>
    @endforeach
`;

function addBulkRow() {
    const tbody = document.getElementById('bulkTableBody');
    const tr = document.createElement('tr');
    tr.id = 'bulk-row-' + bulkRowIndex;

    tr.innerHTML = `
        <td>
            <select name="bulk_details[${bulkRowIndex}][user_id]" class="form-select form-select-sm" required>
                ${personnelOptions}
            </select>
        </td>
        <td>
            <select name="bulk_details[${bulkRowIndex}][assignment_category]" class="form-select form-select-sm" onchange="onBulkCategoryChange(${bulkRowIndex})" required>
                <option value="mengajar">Mengajar (KBM)</option>
                <option value="operasional">Operasional / Wali Kelas</option>
                <option value="struktural">Jabatan Struktural</option>
                <option value="arsip">Dokumentasi Arsip Saja</option>
            </select>
        </td>
        <td>
            <div id="bulk-target-select-${bulkRowIndex}">
                <select name="bulk_details[${bulkRowIndex}][subject_id]" class="form-select form-select-sm">
                    ${subjectOptions}
                </select>
            </div>
        </td>
        <td>
            <select name="bulk_details[${bulkRowIndex}][classroom_id]" class="form-select form-select-sm">
                ${classroomOptions}
            </select>
        </td>
        <td>
            <input type="number" name="bulk_details[${bulkRowIndex}][teaching_hours]" class="form-control form-control-sm" placeholder="Jam" min="1" max="60">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="removeBulkRow(${bulkRowIndex})" title="Hapus Baris">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    bulkRowIndex++;
}

function removeBulkRow(idx) {
    const el = document.getElementById('bulk-row-' + idx);
    if (el) el.remove();
}

function onBulkCategoryChange(idx) {
    const row = document.getElementById('bulk-row-' + idx);
    const cat = row.querySelector(`select[name="bulk_details[${idx}][assignment_category]"]`).value;
    const targetDiv = document.getElementById('bulk-target-select-' + idx);

    if (cat === 'mengajar') {
        targetDiv.innerHTML = `
            <select name="bulk_details[${idx}][subject_id]" class="form-select form-select-sm">
                ${subjectOptions}
            </select>
        `;
    } else if (cat === 'struktural' || cat === 'operasional') {
        targetDiv.innerHTML = `
            <select name="bulk_details[${idx}][structural_position_id]" class="form-select form-select-sm">
                ${structuralOptions}
            </select>
        `;
    } else {
        targetDiv.innerHTML = `
            <input type="text" name="bulk_details[${idx}][notes]" class="form-control form-control-sm" placeholder="Keterangan arsip">
        `;
    }
}

// Inisialisasi 3 baris awal saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('bulkTableBody')) {
        addBulkRow();
        addBulkRow();
        addBulkRow();
    }
});
</script>
@endsection
