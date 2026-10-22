@extends('layouts.mobile')

@section('title', 'Buku Kasus Siswa — APK SIAP')
@section('page_title', 'Kasus Siswa')
@section('page_subtitle', 'Catatan Kedisiplinan Meja Piket')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Top Action Card: Judul & Tombol Tambah Cepat -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%);">
    <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="fw-bold fs-4 lh-sm">Daftar Kasus Siswa</div>
            <div class="text-white-50 small" style="font-size: 0.72rem;">
                {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <a href="{{ route('piket.incidents.create') }}" class="btn btn-sm btn-white fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-1 border-0" style="font-size: 0.75rem; background-color: #ffffff; color: #ea580c !important;">
            <i class="ti ti-plus fs-3" style="color: #ea580c !important;"></i>
            <span style="color: #ea580c !important;">Catat Kasus</span>
        </a>
    </div>
</div>

@if($period !== 'today' || ($date && $date !== now()->toDateString()) || $category || $status || $classroomId || $search)
    <div class="d-flex justify-content-end mb-2 px-1">
        <a href="{{ route('piket.incidents.index') }}" class="badge bg-danger text-white text-decoration-none rounded-pill px-2.5 py-1" style="font-size: 0.65rem;">
            <i class="ti ti-rotate-clockwise me-0.5"></i> Kembali ke Hari Ini
        </a>
    </div>
@endif

<!-- Card Filter Terpadu: Periode, Tanggal, Kelas, Kategori Kasus, dan Pencarian -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('piket.incidents.index') }}" method="GET" id="incidentFilterForm" class="d-flex flex-column gap-2">
            <input type="hidden" name="period" id="incidentPeriodInput" value="{{ $period }}">

            <!-- Baris 1: Mode Periode (3 Tombol Proporsional) -->
            <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; width: 100% !important;">
                <button type="button" 
                        onclick="setIncidentPeriod('today')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'today' ? 'btn-danger' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'today' ? 'background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-event me-0.5"></i> Hari Ini
                </button>
                <button type="button" 
                        onclick="setIncidentPeriod('week')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'week' ? 'btn-danger' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'week' ? 'background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-week me-0.5"></i> Pekan Ini
                </button>
                <button type="button" 
                        onclick="setIncidentPeriod('month')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'month' ? 'btn-danger' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'month' ? 'background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-month me-0.5"></i> Bulan Ini
                </button>
            </div>

            <!-- Baris 2: Pemilih Tanggal & Pilihan Kelas (50% - 50%) -->
            <div class="d-flex gap-2">
                <input type="date" 
                       name="date" 
                       class="form-control form-control-sm flex-fill" 
                       style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                       value="{{ $date }}" 
                       onchange="document.getElementById('incidentPeriodInput').value = 'today'; this.form.submit()">

                <select name="classroom_id" 
                        class="form-select form-select-sm flex-fill" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Baris 3: Dropdown Kategori & Status Kasus (50% - 50%) -->
            <div class="d-flex gap-2">
                <select name="category" 
                        class="form-select form-select-sm flex-fill" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <option value="kedisiplinan" {{ $category === 'kedisiplinan' ? 'selected' : '' }}>Kedisiplinan</option>
                    <option value="seragam" {{ $category === 'seragam' ? 'selected' : '' }}>Seragam / Kerapian</option>
                    <option value="gadget_terlarang" {{ $category === 'gadget_terlarang' ? 'selected' : '' }}>HP / Gadget</option>
                    <option value="perkelahian" {{ $category === 'perkelahian' ? 'selected' : '' }}>Perkelahian</option>
                    <option value="rokok" {{ $category === 'rokok' ? 'selected' : '' }}>Rokok / Vape</option>
                    <option value="kesehatan_pingsan" {{ $category === 'kesehatan_pingsan' ? 'selected' : '' }}>Kesehatan / Pingsan</option>
                    <option value="lainnya" {{ $category === 'lainnya' ? 'selected' : '' }}>Lain-lain</option>
                </select>

                <select name="status" 
                        class="form-select form-select-sm flex-fill" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="tercatat" {{ $status === 'tercatat' ? 'selected' : '' }}>Tercatat</option>
                    <option value="ditindaklanjuti" {{ $status === 'ditindaklanjuti' ? 'selected' : '' }}>Ditindaklanjuti</option>
                    <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <!-- Baris 4: Formasi Pencarian Reusable -->
            <x-mobile.search-bar 
                name="search" 
                :value="$search" 
                placeholder="Cari nama siswa / uraian kasus..." 
                :reset-url="route('piket.incidents.index')" 
                :has-active-filter="(bool) ($category || $status || $classroomId || $period !== 'today' || ($date && $date !== now()->toDateString()))" 
            />
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Rekap Angka Sesuai Filter -->
@if(!empty($stats))
    <div class="card border-0 shadow-sm rounded-3 mb-2 bg-white">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between gap-1 text-center" style="font-size: 0.7rem;">
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-dark lh-1">{{ $stats['total'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Semua Kasus</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-warning lh-1">{{ $stats['tercatat'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Tercatat</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-info lh-1">{{ $stats['ditindaklanjuti'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Ditindaklanjuti</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-success lh-1">{{ $stats['selesai'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Selesai</div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Feed Daftar Kasus Siswa -->
<div class="mb-4">
    @forelse($incidents as $inc)
        @php
            $catBadge = match($inc->category) {
                'kedisiplinan' => 'bg-warning-lt text-warning border border-warning',
                'seragam' => 'bg-info-lt text-info border border-info',
                'gadget_terlarang' => 'bg-purple-lt text-purple border border-purple',
                'perkelahian', 'rokok' => 'bg-danger-lt text-danger border border-danger',
                'kesehatan_pingsan' => 'bg-teal-lt text-teal border border-teal',
                default => 'bg-secondary-lt text-secondary border',
            };
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-1.5 border-start border-danger border-3">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge {{ $catBadge }}" style="font-size: 0.65rem; padding: 2px 6px;">
                            {{ $inc->category_label ?? ucfirst(str_replace('_', ' ', $inc->category)) }}
                        </span>
                        @if($inc->status === 'selesai')
                            <span class="badge bg-success text-white" style="font-size: 0.62rem; padding: 2px 5px;"><i class="ti ti-check me-0.5"></i>Selesai</span>
                        @elseif($inc->status === 'ditindaklanjuti')
                            <span class="badge bg-info text-white" style="font-size: 0.62rem; padding: 2px 5px;">Ditindaklanjuti</span>
                        @else
                            <span class="badge bg-warning text-dark" style="font-size: 0.62rem; padding: 2px 5px;">Tercatat</span>
                        @endif
                    </div>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        {{ $inc->incident_date?->translatedFormat('d M') }} &bull; {{ substr($inc->incident_time, 0, 5) }} WIB
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-0.5">
                    <div class="fw-bold text-dark fs-4 lh-sm">{{ $inc->student_name }}</div>
                    <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.68rem;">
                        {{ $inc->classroom?->name ?? 'Kelas -' }}
                    </span>
                </div>

                <div class="p-1.5 bg-light rounded-2 small text-secondary mb-1.5" style="font-size: 0.72rem;">
                    <div class="text-dark mb-0.5"><strong>Kronologi:</strong> {{ $inc->description }}</div>
                    @if($inc->action_taken)
                        <div class="text-danger"><strong>Penanganan:</strong> {{ $inc->action_taken }}</div>
                    @endif
                    <div class="text-muted mt-0.5" style="font-size: 0.68rem;">
                        Tindak Lanjut: <strong>{{ $inc->follow_up_label ?? ucfirst(str_replace('_', ' ', $inc->follow_up_target)) }}</strong>
                        @if($inc->confiscated_items) &bull; Disita: {{ $inc->confiscated_items }} @endif
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-1 border-top" style="font-size: 0.68rem;">
                    <span class="text-muted">
                        Piket: {{ $inc->recordedBy?->formatted_short_name ?? $inc->recordedBy?->name ?? 'Petugas' }}
                    </span>
                    <div class="d-flex align-items-center gap-1.5">
                        @if($inc->evidence_photo)
                            <a href="{{ asset('storage/' . $inc->evidence_photo) }}" target="_blank" class="badge bg-blue-lt text-decoration-none py-1 px-1.5" style="font-size: 0.65rem;">
                                <i class="ti ti-photo me-0.5"></i> Foto
                            </a>
                        @endif
                        @if(auth()->user()->canManagePiketCases())
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5" style="font-size: 0.68rem;" onclick="openUpdateStatusSheet('{{ $inc->id }}', '{{ $inc->status }}', '{{ addslashes($inc->student_name) }}')">
                                <i class="ti ti-edit me-0.5"></i> Status
                            </button>
                            <form action="{{ route('piket.incidents.destroy', $inc) }}" method="POST" onsubmit="return confirm('Hapus catatan kasus ini?')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none ms-1" style="font-size: 0.7rem;" title="Hapus">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-shield-check fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Tidak ada catatan kasus/kejadian pada filter ini.</div>
        </div>
    @endforelse

    @if($incidents->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $incidents->links() }}
        </div>
    @endif
</div>

<!-- Framework7 Bottom Sheet: Update Status Penanganan Kasus -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-update-status-incident" style="height: auto; max-height: 80vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-edit text-primary"></i> Update Status Kasus
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <div class="p-2 rounded-2 bg-light text-dark small mb-2 fw-semibold" style="font-size: 0.78rem;">
            Siswa: <span id="incident-student-name" class="text-primary">-</span>
        </div>
        <form id="form-update-incident-status" action="" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1">Status Penanganan <span class="text-danger">*</span></label>
                <select name="status" id="incident-status-select" class="form-select form-select-sm" required style="font-size: 0.85rem;">
                    <option value="tercatat">Tercatat di Meja Piket</option>
                    <option value="ditindaklanjuti">Sedang Ditindaklanjuti (Wali Kelas / BK / Kesiswaan)</option>
                    <option value="selesai">Selesai (Kasus Tuntas Ditangani)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Perubahan Status</span>
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setIncidentPeriod(period) {
    document.getElementById('incidentPeriodInput').value = period;
    document.getElementById('incidentFilterForm').submit();
}

function openUpdateStatusSheet(id, currentStatus, studentName) {
    const form = document.getElementById('form-update-incident-status');
    form.action = "{{ url('piket/kasus') }}/" + id + "/status";
    document.getElementById('incident-student-name').textContent = studentName;
    document.getElementById('incident-status-select').value = currentStatus;

    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.open('#sheet-update-status-incident');
    }
}
</script>
@endpush
