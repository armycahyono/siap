@extends('layouts.app')

@section('title', 'Data Pokok Pihak Terkait & Pejabat - Sistem Supervisi')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Data Pokok: Pihak Terkait & Pejabat',
    'subtitle' => 'Kelola data Pengurus Yayasan, Komite Sekolah, Pengawas Pembina, dan Pejabat Dinas penandatangan dokumen.',
    'icon' => 'ti ti-users-group',
    'category' => 'DATA POKOK & STAKEHOLDER',
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateStakeholder"><i class="ti ti-plus me-1"></i> Tambah Pejabat / Pengurus</button>'
])

@include('master.nav')

<!-- Category Filter Tabs -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-2">
        <ul class="nav nav-pills gap-1">
            <li class="nav-item">
                <a class="nav-link {{ $category === 'all' ? 'active' : '' }}" href="{{ route('stakeholders.index', ['category' => 'all', 'search' => $search]) }}">
                    <i class="ti ti-list me-1"></i> Semua
                    <span class="badge bg-secondary-lt ms-1">{{ $counts['all'] }}</span>
                </a>
            </li>
            @foreach($categories as $catKey => $catDef)
            <li class="nav-item">
                <a class="nav-link {{ $category === $catKey ? 'active' : '' }}" href="{{ route('stakeholders.index', ['category' => $catKey, 'search' => $search]) }}">
                    <i class="{{ $catDef['icon'] }} me-1"></i> {{ $catDef['label'] }}
                    <span class="badge bg-secondary-lt ms-1">{{ $counts[$catKey] ?? 0 }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</div>

<!-- Search Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('stakeholders.index') }}" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="category" value="{{ $category }}">
            <div class="col-md-9">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama pejabat, NIP, NIK, jabatan, atau instansi..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
            </div>
            @if($search)
            <div class="col-md-1">
                <a href="{{ route('stakeholders.index', ['category' => $category]) }}" class="btn btn-link text-muted p-0">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Pejabat / Pengurus</th>
                    <th>Kategori & Jabatan</th>
                    <th>Instansi / Lembaga</th>
                    <th>Fungsi Pengesahan</th>
                    <th style="width: 110px;">Tanda Tangan</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stakeholders as $index => $item)
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $stakeholders->firstItem() + $index }}</td>
                    <td>
                        <div class="fw-bold text-dark fs-3">{{ $item->name }}</div>
                        <div class="text-muted small">
                            @if($item->nip)
                                <span>NIP. {{ $item->nip }}</span>
                            @elseif($item->nik)
                                <span>NIK. {{ $item->nik }}</span>
                            @endif
                            @if($item->rank_group)
                                <span class="ms-1 badge bg-light text-secondary border">{{ $item->rank_group }}</span>
                            @endif
                        </div>
                        @if($item->phone || $item->email)
                        <div class="text-muted small mt-1">
                            @if($item->phone)<i class="ti ti-phone me-1"></i>{{ $item->phone }}@endif
                            @if($item->email)<i class="ti ti-mail ms-2 me-1"></i>{{ $item->email }}@endif
                        </div>
                        @endif
                    </td>
                    <td>
                        <div>
                            <span class="badge {{ $item->category_badge }} fw-semibold mb-1">
                                <i class="{{ $item->category_icon }} me-1"></i> {{ $item->category_label }}
                            </span>
                        </div>
                        <div class="fw-semibold text-secondary">{{ $item->position }}</div>
                    </td>
                    <td>
                        <span class="text-dark">{{ $item->institution ?: '-' }}</span>
                    </td>
                    <td>
                        @if($item->is_signatory)
                            <span class="badge bg-green-lt fw-bold">
                                <i class="ti ti-writing me-1"></i> Penandatangan
                            </span>
                            @if($item->endorsement_title)
                                <div class="text-muted small fst-italic mt-1">"{{ $item->endorsement_title }}"</div>
                            @endif
                        @else
                            <span class="badge bg-light text-muted">Bukan Penandatangan</span>
                        @endif
                    </td>
                    <td>
                        @if($item->signature_image_path)
                            <a href="{{ route('stakeholders.signature', $item) }}" target="_blank">
                                <img src="{{ route('stakeholders.signature', $item) }}" alt="TTD" style="height: 38px; max-width: 80px; object-fit: contain;" class="border p-1 rounded bg-white">
                            </a>
                        @else
                            <span class="text-muted small fst-italic">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($item->is_active)
                            <span class="badge bg-success-lt">Aktif</span>
                        @else
                            <span class="badge bg-secondary-lt">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditStakeholder_{{ $item->id }}" title="Edit Data">
                                <i class="ti ti-edit"></i>
                            </button>
                            <form action="{{ route('stakeholders.destroy', $item) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data {{ $item->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Data">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                <!-- Modal Edit -->
                <div class="modal fade" id="modalEditStakeholder_{{ $item->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('stakeholders.update', $item) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        <i class="ti ti-edit me-1 text-primary"></i> Edit Pihak Terkait / Pejabat
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <!-- Bagian 1: Kategori & Jabatan -->
                                        <div class="col-12">
                                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                                <i class="ti ti-briefcase me-1"></i> 1. Informasi Jabatan & Instansi
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Kategori Peran</label>
                                            <select name="category" class="form-select" required>
                                                @foreach($categories as $catKey => $catDef)
                                                    <option value="{{ $catKey }}" {{ $item->category === $catKey ? 'selected' : '' }}>
                                                        {{ $catDef['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Nama Lengkap & Gelar</label>
                                            <input type="text" name="name" class="form-control" value="{{ $item->name }}" placeholder="Contoh: Drs. H. Ahmad Sudirman, M.Pd." required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Jabatan Resmi</label>
                                            <input type="text" name="position" class="form-control" value="{{ $item->position }}" placeholder="Contoh: Pengawas Pembina SMK / Ketua Komite" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Instansi / Organisasi</label>
                                            <input type="text" name="institution" class="form-control" value="{{ $item->institution }}" placeholder="Contoh: Cabang Dinas Pendidikan Wilayah Kediri">
                                        </div>

                                        <!-- Bagian 2: Data Kepegawaian & Kontak -->
                                        <div class="col-12 mt-3">
                                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                                <i class="ti ti-id me-1"></i> 2. Identitas Kepegawaian & Kontak
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">NIP (Khusus ASN / Pengawas / Dinas)</label>
                                            <input type="text" name="nip" class="form-control" value="{{ $item->nip }}" placeholder="Contoh: 19680512 199303 1 004">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Pangkat / Golongan Ruang (Khusus ASN)</label>
                                            <input type="text" name="rank_group" class="form-control" value="{{ $item->rank_group }}" placeholder="Contoh: Pembina Utama Muda / IV/c">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">NIK / No. Identitas Yayasan/Komite</label>
                                            <input type="text" name="nik" class="form-control" value="{{ $item->nik }}" placeholder="Nomor KTP / Identitas">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">No. Telepon / WhatsApp</label>
                                            <input type="text" name="phone" class="form-control" value="{{ $item->phone }}" placeholder="08xxxxxxxxxx">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="{{ $item->email }}" placeholder="nama@instansi.go.id">
                                        </div>

                                        <!-- Bagian 3: Pengesahan & Tanda Tangan -->
                                        <div class="col-12 mt-3">
                                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                                <i class="ti ti-signature me-1"></i> 3. Pengaturan Penandatangan Dokumen
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_signatory" value="1" id="edit_signatory_{{ $item->id }}" {{ $item->is_signatory ? 'checked' : '' }}>
                                                <label class="form-check-label fw-bold" for="edit_signatory_{{ $item->id }}">
                                                    Aktif sebagai Penandatangan Dokumen Sekolah
                                                </label>
                                            </div>
                                            <small class="text-muted">Centang jika figur ini menandatangani lembar pengesahan (Kaldik, KOSP, Jadwal, dsb).</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Teks Format Pengesahan</label>
                                            <input type="text" name="endorsement_title" class="form-control" list="endorsementList" value="{{ $item->endorsement_title }}" placeholder="Pilih atau ketik (contoh: Mengetahui, / Menyetujui,)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">File Scan Tanda Tangan (PNG Transparan)</label>
                                            <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                            <small class="text-muted">Maksimal 2MB. Disarankan berlatar transparan.</small>
                                            @if($item->signature_image_path)
                                                <div class="mt-2 p-2 bg-light rounded border d-flex align-items-center justify-content-between">
                                                    <img src="{{ route('stakeholders.signature', $item) }}" alt="TTD" style="height: 35px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="del_sig_{{ $item->id }}">
                                                        <label class="form-check-label text-danger small" for="del_sig_{{ $item->id }}">Hapus Scan TTD</label>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Urutan Tampilan</label>
                                            <input type="number" name="order_index" class="form-control" value="{{ $item->order_index }}" min="0">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Status</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_active_{{ $item->id }}" {{ $item->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="edit_active_{{ $item->id }}">Aktif</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Catatan Tambahan (Opsional)</label>
                                            <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal">{{ $item->notes }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary ms-auto">
                                        <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="ti ti-users-off fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        Belum ada data pihak terkait atau pejabat pada kategori ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($stakeholders->hasPages())
    <div class="card-footer d-flex align-items-center">
        {{ $stakeholders->links() }}
    </div>
    @endif
</div>

<!-- Datalist untuk Teks Pengesahan -->
<datalist id="endorsementList">
    <option value="Mengetahui,">
    <option value="Menyetujui,">
    <option value="Mengesahkan,">
    <option value="Mengetahui / Mengesahkan,">
</datalist>

<!-- Modal Create -->
<div class="modal fade" id="modalCreateStakeholder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('stakeholders.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="ti ti-plus me-1 text-primary"></i> Tambah Pihak Terkait & Pejabat
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Bagian 1: Kategori & Jabatan -->
                        <div class="col-12">
                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                <i class="ti ti-briefcase me-1"></i> 1. Informasi Jabatan & Instansi
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Kategori Peran</label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $catKey => $catDef)
                                    <option value="{{ $catKey }}" {{ (old('category') ?: $category) === $catKey ? 'selected' : '' }}>
                                        {{ $catDef['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Nama Lengkap & Gelar</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: Drs. H. Ahmad Sudirman, M.Pd." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Jabatan Resmi</label>
                            <input type="text" name="position" class="form-control" value="{{ old('position') }}" placeholder="Contoh: Pengawas Pembina SMK / Ketua Komite" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instansi / Organisasi</label>
                            <input type="text" name="institution" class="form-control" value="{{ old('institution') }}" placeholder="Contoh: Cabang Dinas Pendidikan Wilayah Kediri">
                        </div>

                        <!-- Bagian 2: Data Kepegawaian & Kontak -->
                        <div class="col-12 mt-3">
                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                <i class="ti ti-id me-1"></i> 2. Identitas Kepegawaian & Kontak
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NIP (Khusus ASN / Pengawas / Dinas)</label>
                            <input type="text" name="nip" class="form-control" value="{{ old('nip') }}" placeholder="Contoh: 19680512 199303 1 004">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pangkat / Golongan Ruang (Khusus ASN)</label>
                            <input type="text" name="rank_group" class="form-control" value="{{ old('rank_group') }}" placeholder="Contoh: Pembina Utama Muda / IV/c">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NIK / No. Identitas Yayasan/Komite</label>
                            <input type="text" name="nik" class="form-control" value="{{ old('nik') }}" placeholder="Nomor KTP / Identitas">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. Telepon / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="nama@instansi.go.id">
                        </div>

                        <!-- Bagian 3: Pengesahan & Tanda Tangan -->
                        <div class="col-12 mt-3">
                            <div class="border-bottom pb-2 mb-2 fw-bold text-primary">
                                <i class="ti ti-signature me-1"></i> 3. Pengaturan Penandatangan Dokumen
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_signatory" value="1" id="create_signatory" checked>
                                <label class="form-check-label fw-bold" for="create_signatory">
                                    Aktif sebagai Penandatangan Dokumen Sekolah
                                </label>
                            </div>
                            <small class="text-muted">Centang jika figur ini menandatangani lembar pengesahan (Kaldik, KOSP, Jadwal, dsb).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teks Format Pengesahan</label>
                            <input type="text" name="endorsement_title" class="form-control" list="endorsementList" value="{{ old('endorsement_title', 'Mengetahui,') }}" placeholder="Pilih atau ketik (contoh: Mengetahui, / Menyetujui,)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">File Scan Tanda Tangan (PNG Transparan)</label>
                            <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <small class="text-muted">Maksimal 2MB. Disarankan berlatar transparan.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Urutan Tampilan</label>
                            <input type="number" name="order_index" class="form-control" value="{{ old('order_index', 1) }}" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="create_active" checked>
                                <label class="form-check-label" for="create_active">Aktif</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Pejabat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
