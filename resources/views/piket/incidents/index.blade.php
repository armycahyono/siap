@extends('layouts.app')

@section('title', 'Catatan Kejadian & Kasus Siswa')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Buku Kasus Piket
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-alert-triangle text-danger me-2 fs-1"></i>
                    Catatan Kejadian & Pelanggaran Siswa
                </h2>
                <div class="text-secondary mt-1">
                    Pencatatan penanganan awal kasus tata tertib di meja piket dan rujukan ke Wali Kelas / Guru BK / Kesiswaan.
                </div>
            </div>
            @if(auth()->user()->canManagePiketCases())
            <div class="col-auto ms-auto">
                <a href="{{ route('piket.incidents.create') }}" class="btn btn-sm btn-danger shadow-sm">
                    <i class="ti ti-plus me-1"></i> + Catat Kejadian / Kasus Baru
                </a>
            </div>
            @endif
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('piket.incidents.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">-- Semua Kategori --</option>
                        <option value="kedisiplinan" {{ $category === 'kedisiplinan' ? 'selected' : '' }}>Kedisiplinan</option>
                        <option value="seragam" {{ $category === 'seragam' ? 'selected' : '' }}>Seragam / Kerapian</option>
                        <option value="gadget_terlarang" {{ $category === 'gadget_terlarang' ? 'selected' : '' }}>Gadget / HP</option>
                        <option value="perkelahian" {{ $category === 'perkelahian' ? 'selected' : '' }}>Pertikaian</option>
                        <option value="rokok" {{ $category === 'rokok' ? 'selected' : '' }}>Rokok / Vape</option>
                        <option value="kesehatan_pingsan" {{ $category === 'kesehatan_pingsan' ? 'selected' : '' }}>Kesehatan / Pingsan</option>
                        <option value="lainnya" {{ $category === 'lainnya' ? 'selected' : '' }}>Lain-lain</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="tercatat" {{ $status === 'tercatat' ? 'selected' : '' }}>Tercatat</option>
                        <option value="ditindaklanjuti" {{ $status === 'ditindaklanjuti' ? 'selected' : '' }}>Ditindaklanjuti</option>
                        <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / uraian kasus...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if($category || $status || $search)
                        <a href="{{ route('piket.incidents.index') }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Table Incidents -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Kejadian</th>
                        <th>Siswa & Kelas</th>
                        <th>Kategori</th>
                        <th>Uraian Kejadian & Tindakan</th>
                        <th>Tindak Lanjut & Sitaan</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $inc)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $inc->incident_date?->format('d/m/Y') }}</div>
                                <div class="text-muted small"><i class="ti ti-clock me-1"></i>{{ substr($inc->incident_time, 0, 5) }} WIB</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-3">{{ $inc->student_name }}</div>
                                <span class="badge bg-blue-lt">{{ $inc->classroom?->name ?? 'Umum' }}</span>
                                @if($inc->classroom?->homeroomTeacher)
                                    <div class="text-muted small">Wali: {{ $inc->classroom->homeroomTeacher->formatted_short_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span>
                            </td>
                            <td>
                                <div class="text-dark small fw-bold">{{ $inc->description }}</div>
                                <div class="text-secondary small mt-1"><strong>Tindakan Piket:</strong> {{ $inc->action_taken }}</div>
                                @if($inc->evidence_photo)
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-2" onclick="showIncidentPhoto('{{ $inc->evidence_photo_url }}', '{{ addslashes($inc->student_name) }}', '{{ addslashes($inc->category_label) }}', '{{ addslashes($inc->description) }}')">
                                            <i class="ti ti-camera fs-3"></i>
                                            <span>Lihat Bukti Foto</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-purple-lt">{{ $inc->follow_up_label }}</span>
                                @if($inc->confiscated_items)
                                    <div class="text-danger small mt-1"><i class="ti ti-box me-1"></i> Sitaan: {{ $inc->confiscated_items }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($inc->status === 'selesai')
                                    <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i> Selesai</span>
                                @elseif($inc->status === 'ditindaklanjuti')
                                    <span class="badge bg-info text-white"><i class="ti ti-arrow-forward me-1"></i> Ditindaklanjuti</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i> Tercatat</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->canManagePiketCases())
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-status-{{ $inc->id }}" title="Perbarui Status Kasus">
                                    <i class="ti ti-edit me-1"></i> Update
                                </button>
                                <form action="{{ route('piket.incidents.destroy', $inc) }}" method="POST" onsubmit="return confirm('Hapus data catatan kasus siswa ini?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost-danger" title="Hapus Kasus">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>

                                <!-- Modal Update Status Kasus -->
                                <div class="modal fade" id="modal-status-{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content shadow-lg border-0 text-start">
                                            <form action="{{ route('piket.incidents.status', $inc) }}" method="POST">
                                                @csrf
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold text-dark mb-0">
                                                        <i class="ti ti-adjustments-horizontal text-primary me-2"></i> Update Status Kasus
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3 p-3 bg-light rounded border">
                                                        <div class="fw-bold fs-3 text-dark">{{ $inc->student_name }}</div>
                                                        <div class="text-muted small">
                                                            {{ $inc->classroom?->name ?? 'Umum' }} &bull; Kategori: <span class="badge bg-danger-lt fw-bold">{{ $inc->category_label }}</span>
                                                        </div>
                                                        <div class="text-secondary small mt-2">
                                                            <strong>Uraian:</strong> {{ $inc->description }}
                                                        </div>
                                                    </div>

                                                    <label class="form-label fw-bold text-dark required mb-2">Pilih Status Penanganan Kasus:</label>
                                                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="tercatat" class="form-selectgroup-input" {{ $inc->status === 'tercatat' ? 'checked' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-warning fw-bold"><i class="ti ti-clock me-1"></i> Tercatat</div>
                                                                    <div class="text-muted small">Kasus baru dicatat di meja piket, menunggu tindak lanjut.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="ditindaklanjuti" class="form-selectgroup-input" {{ $inc->status === 'ditindaklanjuti' ? 'checked' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-info fw-bold"><i class="ti ti-arrow-forward me-1"></i> Ditindaklanjuti</div>
                                                                    <div class="text-muted small">Sedang dalam penanganan / telah dirujuk ke Wali Kelas / BK / Kesiswaan.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                        <label class="form-selectgroup-item flex-fill">
                                                            <input type="radio" name="status" value="selesai" class="form-selectgroup-input" {{ $inc->status === 'selesai' ? 'checked' : '' }}>
                                                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <span class="form-selectgroup-check"></span>
                                                                </div>
                                                                <div>
                                                                    <div class="font-weight-medium text-success fw-bold"><i class="ti ti-circle-check me-1"></i> Selesai</div>
                                                                    <div class="text-muted small">Pembinaan telah tuntas dan selesai.</div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary shadow-sm">
                                                        <i class="ti ti-device-floppy me-1"></i> Simpan Status
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-shield-check fs-1 d-block mb-1 text-success"></i>
                                Tidak ada catatan kasus atau pelanggaran siswa untuk filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($incidents->hasPages())
            <div class="card-footer py-2">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Bukti Foto Kasus -->
<div class="modal fade" id="modalIncidentPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="ti ti-camera text-primary"></i>
                        <span>Bukti Foto Kejadian: <span id="modalStudentName" class="text-primary"></span></span>
                    </h5>
                    <div class="text-muted small mt-1">
                        Kategori: <span id="modalCategoryBadge" class="badge bg-danger-lt fw-bold"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3 bg-dark-subtle">
                <img id="modalPhotoImage" src="#" alt="Bukti Foto" class="img-fluid rounded shadow-sm" style="max-height: 500px; width: auto; object-fit: contain;">
                <div class="p-3 bg-white text-start rounded mt-3 shadow-sm">
                    <label class="fw-bold small text-muted text-uppercase mb-1">Kronologi / Uraian:</label>
                    <p id="modalDescription" class="text-dark mb-0 small"></p>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <a id="modalDownloadBtn" href="#" target="_blank" download class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Unduh Foto Asli
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showIncidentPhoto(photoUrl, studentName, category, description) {
    document.getElementById('modalPhotoImage').src = photoUrl;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalCategoryBadge').textContent = category;
    document.getElementById('modalDescription').textContent = description;
    document.getElementById('modalDownloadBtn').href = photoUrl;

    const modal = new bootstrap.Modal(document.getElementById('modalIncidentPhoto'));
    modal.show();
}
</script>
@endpush
@endsection
