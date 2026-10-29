@extends('layouts.mobile')

@section('title', 'Daftar Perizinan Siswa — APK SIAP')
@section('page_title', 'Daftar Perizinan Siswa')
@section('page_subtitle', 'Buku Kendali Meja Piket Hari Ini')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Top Action Card: Judul & Tombol Tambah Cepat -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="fw-bold fs-4 lh-sm">Daftar Perizinan Siswa</div>
            <div class="text-white-50 small" style="font-size: 0.72rem;">
                {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <a href="{{ route('piket.permits.create') }}" class="btn btn-sm btn-white fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-1 border-0" style="font-size: 0.75rem; background-color: #ffffff; color: #ea580c !important;">
            <i class="ti ti-plus fs-3" style="color: #ea580c !important;"></i>
            <span style="color: #ea580c !important;">Buat Izin</span>
        </a>
    </div>
</div>

@if($period !== 'today' || ($date && $date !== now()->toDateString()) || $type || $classroomId || $search)
    <div class="d-flex justify-content-end mb-2 px-1">
        <a href="{{ route('piket.permits.index') }}" class="badge bg-primary text-white text-decoration-none rounded-pill px-2.5 py-1" style="font-size: 0.65rem;">
            <i class="ti ti-rotate-clockwise me-0.5"></i> Kembali ke Hari Ini
        </a>
    </div>
@endif

<!-- Card Filter Terpadu: Periode, Kelas, Jenis Izin, dan Pencarian Rapi -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('piket.permits.index') }}" method="GET" id="permitFilterForm" class="d-flex flex-column gap-2">
            <input type="hidden" name="period" id="periodInput" value="{{ $period }}">

            <!-- Baris 1: Mode Periode (3 Tombol Proporsional) -->
            <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; width: 100% !important;">
                <button type="button" 
                        onclick="setPermitPeriod('today')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'today' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'today' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-event me-0.5"></i> Hari Ini
                </button>
                <button type="button" 
                        onclick="setPermitPeriod('week')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'week' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'week' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-week me-0.5"></i> Pekan Ini
                </button>
                <button type="button" 
                        onclick="setPermitPeriod('month')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'month' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'month' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-month me-0.5"></i> Bulan Ini
                </button>
            </div>

            <!-- Baris 2: Pemilih Tanggal & Pilihan Kelas (Berdampingan 50% - 50%) -->
            <div class="d-flex gap-2">
                <input type="date" 
                       name="date" 
                       class="form-control form-control-sm flex-fill" 
                       style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                       value="{{ $date }}" 
                       onchange="document.getElementById('periodInput').value = 'today'; this.form.submit()">

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

            <!-- Baris 3: Dropdown Kategori Jenis Izin -->
            <div>
                <select name="type" 
                        class="form-select form-select-sm w-100" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Jenis Izin</option>
                    <option value="terlambat" {{ $type === 'terlambat' ? 'selected' : '' }}>Terlambat Masuk</option>
                    <option value="izin_keluar_sementara" {{ $type === 'izin_keluar_sementara' ? 'selected' : '' }}>Keluar Sementara</option>
                    <option value="izin_pulang_cepat" {{ $type === 'izin_pulang_cepat' ? 'selected' : '' }}>Pulang Cepat</option>
                    <option value="izin_tidak_masuk" {{ $type === 'izin_tidak_masuk' ? 'selected' : '' }}>Tidak Masuk (S/I/D)</option>
                </select>
            </div>

            <!-- Baris 4: Formasi Pencarian Rapi (Komponen Reusable) -->
            <x-mobile.search-bar 
                name="search" 
                :value="$search" 
                placeholder="Cari nama siswa / no izin..." 
                :reset-url="route('piket.permits.index')" 
                :has-active-filter="(bool) ($type || $classroomId || $period !== 'today' || ($date && $date !== now()->toDateString()))" 
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
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Semua</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-warning lh-1">{{ $stats['terlambat'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Terlambat</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-primary lh-1">{{ $stats['keluar'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Keluar</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-danger lh-1">{{ $stats['pulang'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Pulang</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-secondary lh-1">{{ $stats['tidak_masuk'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Absen</div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Feed Daftar Izin Siswa -->
<div class="mb-4">
    @forelse($permits as $p)
        @php
            $typeBadge = match($p->permit_type) {
                'terlambat' => 'bg-warning-lt text-dark border border-warning',
                'izin_keluar_sementara' => 'bg-blue-lt text-primary border border-primary',
                'izin_pulang_cepat' => 'bg-danger-lt text-danger border border-danger',
                default => 'bg-secondary-lt text-secondary border',
            };
            $typeLabel = match($p->permit_type) {
                'terlambat' => 'Terlambat',
                'izin_keluar_sementara' => 'Keluar',
                'izin_pulang_cepat' => 'Pulang Cepat',
                default => 'Tidak Masuk',
            };
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-1.5">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge {{ $typeBadge }}" style="font-size: 0.65rem; padding: 2px 6px;">
                        {{ $typeLabel }}
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        <span class="badge bg-light text-dark border me-1" style="font-size: 0.62rem;">{{ $p->permit_number }}</span>
                        {{ $p->permit_date?->format('d/m/Y') }}
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-0.5">
                    <div class="fw-bold text-dark fs-4 lh-sm">{{ $p->student_name }}</div>
                    <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.68rem;">
                        {{ $p->classroom?->name ?? 'Kelas -' }}
                    </span>
                </div>

                <div class="text-muted small mb-1" style="font-size: 0.7rem;">
                    @if($p->permit_type === 'terlambat' && $p->entry_time)
                        Tiba pukul <strong>{{ substr($p->entry_time, 0, 5) }} WIB</strong>
                        @if($p->period_assigned) &bull; Masuk Jam Ke-{{ $p->period_assigned }} @endif
                    @elseif($p->departure_time)
                        Keluar: <strong>{{ substr($p->departure_time, 0, 5) }}</strong>
                        @if($p->return_time) s.d <strong>{{ substr($p->return_time, 0, 5) }} WIB</strong> @endif
                    @endif
                </div>

                <div class="p-1.5 bg-light rounded-2 small text-secondary mb-1.5" style="font-size: 0.72rem;">
                    <strong>Alasan:</strong> {{ $p->reason }}
                    @if($p->action_taken)
                        <div class="text-success mt-0.5"><strong>Sanksi:</strong> {{ $p->action_taken }}</div>
                    @endif
                </div>

                <div class="d-flex align-items-center justify-content-between pt-1 border-top" style="font-size: 0.68rem;">
                    <span class="text-muted">
                        Piket: {{ $p->givenBy?->formatted_short_name ?? $p->givenBy?->name ?? 'Petugas' }}
                    </span>
                    <div class="d-flex align-items-center gap-1.5">
                        <a href="{{ route('piket.permits.slip', $p) }}" target="_blank" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-2.5 py-0.5 shadow-xs d-flex align-items-center gap-1 external" style="font-size: 0.68rem;" title="Cetak Slip Izin Thermal">
                            <i class="ti ti-printer fs-3"></i>
                            <span>Cetak Slip</span>
                        </a>
                        <a href="{{ route('piket.permits.show', $p) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                            <i class="ti ti-eye me-0.5"></i> Detail
                        </a>
                        @if(auth()->user()->canOperatePiket())
                            <form action="{{ route('piket.permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus surat izin ini?')" class="d-inline">
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
            <i class="ti ti-file-text fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Tidak ada surat perizinan siswa pada kriteria ini.</div>
        </div>
    @endforelse

    @if($permits->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $permits->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function setPermitPeriod(mode) {
        const input = document.getElementById('periodInput');
        if (input) {
            input.value = mode;
            document.getElementById('permitFilterForm').submit();
        }
    }
</script>
@endpush
