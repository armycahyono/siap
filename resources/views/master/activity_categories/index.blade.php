@extends('layouts.app')

@section('title', 'Data Pokok Kategori Kegiatan - SIAP')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@include('layouts._hero_header', [
    'title' => 'Data Pokok: Kategori Kegiatan',
    'subtitle' => 'Kelola klasifikasi agenda kegiatan sekolah, pembeda Kaldik resmi vs program internal, dan warna pada kalender terpadu.',
    'icon' => 'ti ti-tags',
    'category' => 'DATA POKOK & KALENDER',
    'stats' => [
        ['label' => 'Total', 'value' => $totalCategories, 'class' => 'text-white'],
        ['label' => 'Kaldik Resmi', 'value' => $totalKaldik, 'class' => 'text-info'],
        ['label' => 'Internal', 'value' => $totalInternal, 'class' => 'text-warning'],
        ['label' => 'Aktif', 'value' => $totalActive, 'class' => 'text-success'],
    ],
    'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalCreateCategory"><i class="ti ti-plus me-1"></i> Tambah Kategori</button>'
])

@include('master.nav')

<!-- Summary Stat Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-primary text-white avatar shadow-sm me-3">
                        <i class="ti ti-tags fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Total Kategori</div>
                        <div class="fw-bold fs-2 text-dark">{{ $totalCategories }} <span class="fs-4 fw-normal text-muted">Item</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #16a34a !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-success text-white avatar shadow-sm me-3">
                        <i class="ti ti-check fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Kategori Aktif</div>
                        <div class="fw-bold fs-2 text-success">{{ $totalActive }} <span class="fs-4 fw-normal text-muted">Aktif</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #dc2626 !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-danger text-white avatar shadow-sm me-3">
                        <i class="ti ti-calendar-event fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Kategori Kaldik</div>
                        <div class="fw-bold fs-2 text-danger">{{ $totalKaldik }} <span class="fs-4 fw-normal text-muted">Kaldik</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm border-0 shadow-sm" style="border-left: 4px solid #0284c7 !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <span class="bg-azure text-white avatar shadow-sm me-3">
                        <i class="ti ti-school fs-2"></i>
                    </span>
                    <div>
                        <div class="text-muted small fw-medium">Program Internal</div>
                        <div class="fw-bold fs-2 text-primary">{{ $totalInternal }} <span class="fs-4 fw-normal text-muted">Internal</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('activity-categories.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama kategori, kode, deskripsi..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Tipe Kategori --</option>
                    <option value="internal" {{ $type === 'internal' ? 'selected' : '' }}>Program Mandiri Sekolah</option>
                    <option value="kaldik" {{ $type === 'kaldik' ? 'selected' : '' }}>Regulasi Kaldik Resmi</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100 fw-semibold">
                    <i class="ti ti-filter me-1"></i> Saring
                </button>
            </div>
            @if($search || $type)
            <div class="col-md-1 text-center">
                <a href="{{ route('activity-categories.index') }}" class="btn btn-link text-muted p-0">Reset</a>
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
                    <th style="width: 140px;">Kode (Slug)</th>
                    <th>Nama &amp; Label Kategori</th>
                    <th style="width: 130px;">Warna Kalender</th>
                    <th>Deskripsi / Ruang Lingkup</th>
                    <th style="width: 130px;" class="text-center">Tipe</th>
                    <th style="width: 130px;" class="text-center">Penggunaan</th>
                    <th class="text-center" style="width: 90px;">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $index => $cat)
                <tr>
                    <td class="text-center text-muted fw-bold">{{ $categories->firstItem() + $index }}</td>
                    <td>
                        <span class="badge bg-blue-lt fw-bold font-monospace">{{ $cat->code }}</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge px-2 py-1 {{ $cat->computed_badge_class }}" style="background-color: {{ $cat->color }}; color: #ffffff;">
                                <i class="ti ti-tag me-1"></i> {{ $cat->name }}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-block rounded shadow-sm border" style="width: 22px; height: 22px; background-color: {{ $cat->color }};"></span>
                            <span class="font-monospace small text-muted">{{ $cat->color }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="text-muted small">{{ $cat->description ?: '-' }}</div>
                    </td>
                    <td class="text-center">
                        @if($cat->is_kaldik)
                            <span class="badge bg-danger-lt fw-bold">
                                <i class="ti ti-file-certificate me-1"></i> Kaldik Dinas
                            </span>
                        @else
                            <span class="badge bg-azure-lt fw-bold">
                                <i class="ti ti-school me-1"></i> Program Sekolah
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="small fw-semibold text-dark">
                            {{ $cat->school_activities_count }} Agenda
                        </div>
                        <div class="small text-muted">
                            {{ $cat->curriculum_events_count }} Jadwal Kalender
                        </div>
                    </td>
                    <td class="text-center">
                        @if($cat->is_active)
                            <span class="badge bg-success-lt fw-bold"><i class="ti ti-check me-1"></i> Aktif</span>
                        @else
                            <span class="badge bg-secondary-lt fw-bold"><i class="ti ti-x me-1"></i> Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick='openEditModal(@json($cat))' title="Edit Kategori">
                                <i class="ti ti-pencil"></i>
                            </button>
                            @if($cat->school_activities_count == 0 && $cat->curriculum_events_count == 0)
                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('{{ route('activity-categories.destroy', $cat->id) }}', '{{ $cat->name }}')" title="Hapus Kategori">
                                    <i class="ti ti-trash"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-secondary disabled" title="Tidak dapat dihapus karena digunakan pada agenda/jadwal" disabled>
                                    <i class="ti ti-lock"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="ti ti-tags-off fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-bold">Belum ada kategori kegiatan</div>
                        <div class="small">Silakan tambahkan kategori baru melalui tombol di kanan atas.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($categories->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <div class="text-muted small">
            Menampilkan {{ $categories->firstItem() }} s/d {{ $categories->lastItem() }} dari {{ $categories->total() }} kategori
        </div>
        <div>
            {{ $categories->links() }}
        </div>
    </div>
    @endif
</div>

<!-- Modal Tambah Kategori -->
<div class="modal modal-blur fade" id="modalCreateCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('activity-categories.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-plus me-1 text-primary"></i> Tambah Kategori Kegiatan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Nama Kategori</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Ekstrakurikuler &amp; Lomba" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label">Kode / Slug (Opsional)</label>
                        <input type="text" name="code" class="form-control font-monospace" placeholder="Otomatis dibuat jika kosong">
                        <div class="form-text small">Huruf kecil, angka, atau underscore (_)</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Urutan Tampilan</label>
                        <input type="number" name="order_index" class="form-control" value="{{ (int) $totalCategories + 1 }}" min="0">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Warna Label / Kalender</label>
                        <div class="input-group">
                            <input type="color" id="create_color_picker" class="form-control form-control-color" value="#2563eb" oninput="document.getElementById('create_color_text').value = this.value">
                            <input type="text" name="color" id="create_color_text" class="form-control font-monospace" value="#2563eb" oninput="document.getElementById('create_color_picker').value = this.value">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Badge Class Tabler</label>
                        <select name="badge_class" class="form-select">
                            <option value="bg-primary text-white">Biru Primary (bg-primary)</option>
                            <option value="bg-azure text-white">Azure / Laut (bg-azure)</option>
                            <option value="bg-blue text-white">Biru Tua (bg-blue)</option>
                            <option value="bg-indigo text-white">Indigo / Ungu (bg-indigo)</option>
                            <option value="bg-purple text-white">Purple (bg-purple)</option>
                            <option value="bg-success text-white">Hijau (bg-success)</option>
                            <option value="bg-warning text-dark">Kuning / Amber (bg-warning)</option>
                            <option value="bg-danger text-white">Merah (bg-danger)</option>
                            <option value="bg-secondary text-white">Abu-abu (bg-secondary)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi / Contoh Kegiatan</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Tuliskan contoh kegiatan dalam kategori ini..."></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_kaldik" value="1">
                            <span class="form-check-label fw-semibold">Kategori Kaldik Resmi</span>
                        </label>
                        <div class="form-text small">Tandai jika bersumber dari regulasi resmi dinas</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                            <span class="form-check-label fw-semibold">Status Aktif</span>
                        </label>
                        <div class="form-text small">Kategori dapat dipilih di form input</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal modal-blur fade" id="modalEditCategory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="formEditCategory" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="ti ti-pencil me-1 text-primary"></i> Edit Kategori Kegiatan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Nama Kategori</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label required">Kode / Slug</label>
                        <input type="text" name="code" id="edit_code" class="form-control font-monospace" required>
                        <div class="form-text small">Pengubahan kode akan otomatis menyinkronkan kegiatan terkait</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Urutan Tampilan</label>
                        <input type="number" name="order_index" id="edit_order_index" class="form-control" min="0">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Warna Label / Kalender</label>
                        <div class="input-group">
                            <input type="color" id="edit_color_picker" class="form-control form-control-color" oninput="document.getElementById('edit_color_text').value = this.value">
                            <input type="text" name="color" id="edit_color_text" class="form-control font-monospace" oninput="document.getElementById('edit_color_picker').value = this.value">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Badge Class Tabler</label>
                        <select name="badge_class" id="edit_badge_class" class="form-select">
                            <option value="bg-primary text-white">Biru Primary (bg-primary)</option>
                            <option value="bg-azure text-white">Azure / Laut (bg-azure)</option>
                            <option value="bg-blue text-white">Biru Tua (bg-blue)</option>
                            <option value="bg-indigo text-white">Indigo / Ungu (bg-indigo)</option>
                            <option value="bg-purple text-white">Purple (bg-purple)</option>
                            <option value="bg-success text-white">Hijau (bg-success)</option>
                            <option value="bg-warning text-dark">Kuning / Amber (bg-warning)</option>
                            <option value="bg-danger text-white">Merah (bg-danger)</option>
                            <option value="bg-secondary text-white">Abu-abu (bg-secondary)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi / Contoh Kegiatan</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_kaldik" id="edit_is_kaldik" value="1">
                            <span class="form-check-label fw-semibold">Kategori Kaldik Resmi</span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <span class="form-check-label fw-semibold">Status Aktif</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-device-floppy me-1"></i> Perbarui Kategori
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Form Delete Hidden -->
<form id="formDeleteCategory" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
    function openEditModal(category) {
        const form = document.getElementById('formEditCategory');
        form.action = `/activity-categories/${category.id}`;

        document.getElementById('edit_name').value = category.name || '';
        document.getElementById('edit_code').value = category.code || '';
        document.getElementById('edit_order_index').value = category.order_index ?? 0;
        
        const color = category.color || '#2563eb';
        document.getElementById('edit_color_picker').value = color;
        document.getElementById('edit_color_text').value = color;

        document.getElementById('edit_badge_class').value = category.badge_class || 'bg-primary text-white';
        document.getElementById('edit_description').value = category.description || '';
        document.getElementById('edit_is_kaldik').checked = Boolean(category.is_kaldik);
        document.getElementById('edit_is_active').checked = Boolean(category.is_active);

        new bootstrap.Modal(document.getElementById('modalEditCategory')).show();
    }

    function confirmDelete(url, name) {
        if (confirm(`Apakah Anda yakin ingin menghapus kategori kegiatan "${name}"?`)) {
            const form = document.getElementById('formDeleteCategory');
            form.action = url;
            form.submit();
        }
    }
</script>
@endpush
@endsection
