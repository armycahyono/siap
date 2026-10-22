@extends('layouts.mobile')

@section('title', 'Buku Tamu Piket — APK SIAP')
@section('page_title', 'Buku Tamu')
@section('page_subtitle', 'Layanan Tamu Meja Piket')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Top Action Card: Judul & Tombol Tambah Cepat -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="fw-bold fs-4 lh-sm">Buku Tamu Piket</div>
            <div class="text-white-50 small" style="font-size: 0.72rem;">
                {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-white fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-1 border-0 flex-shrink-0 sheet-open" data-sheet="#sheet-add-guest" style="font-size: 0.75rem; background-color: #ffffff; color: #ea580c !important; width: auto !important;">
            <i class="ti ti-plus fs-3" style="color: #ea580c !important;"></i>
            <span style="color: #ea580c !important;">Catat Tamu</span>
        </button>
    </div>
</div>

@if($period !== 'today' || ($date && $date !== now()->toDateString()) || $status || $search)
    <div class="d-flex justify-content-end mb-2 px-1">
        <a href="{{ route('piket.guests.index') }}" class="badge bg-primary text-white text-decoration-none rounded-pill px-2.5 py-1" style="font-size: 0.65rem;">
            <i class="ti ti-rotate-clockwise me-0.5"></i> Kembali ke Hari Ini
        </a>
    </div>
@endif

<!-- Card Filter Terpadu: Periode, Tanggal, Status, dan Pencarian -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('piket.guests.index') }}" method="GET" id="guestFilterForm" class="d-flex flex-column gap-2">
            <input type="hidden" name="period" id="guestPeriodInput" value="{{ $period }}">

            <!-- Baris 1: Mode Periode (3 Tombol Proporsional) -->
            <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; width: 100% !important;">
                <button type="button" 
                        onclick="setGuestPeriod('today')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'today' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'today' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-event me-0.5"></i> Hari Ini
                </button>
                <button type="button" 
                        onclick="setGuestPeriod('week')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'week' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'week' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-week me-0.5"></i> Pekan Ini
                </button>
                <button type="button" 
                        onclick="setGuestPeriod('month')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'month' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'month' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-month me-0.5"></i> Bulan Ini
                </button>
            </div>

            <!-- Baris 2: Pemilih Tanggal & Pilihan Status (50% - 50%) -->
            <div class="d-flex gap-2">
                <input type="date" 
                       name="date" 
                       class="form-control form-control-sm flex-fill" 
                       style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                       value="{{ $date }}" 
                       onchange="document.getElementById('guestPeriodInput').value = 'today'; this.form.submit()">

                <select name="status" 
                        class="form-select form-select-sm flex-fill" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="visiting" {{ $status === 'visiting' ? 'selected' : '' }}>Sedang Berkunjung</option>
                    <option value="checked_out" {{ $status === 'checked_out' ? 'selected' : '' }}>Selesai / Keluar</option>
                </select>
            </div>

            <!-- Baris 3: Formasi Pencarian Reusable -->
            <x-mobile.search-bar 
                name="search" 
                :value="$search" 
                placeholder="Cari nama tamu / instansi / keperluan..." 
                :reset-url="route('piket.guests.index')" 
                :has-active-filter="(bool) ($status || $period !== 'today' || ($date && $date !== now()->toDateString()))" 
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
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Total Tamu</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-success lh-1">{{ $stats['visiting'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Berkunjung</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-secondary lh-1">{{ $stats['checked_out'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Selesai</div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Feed Daftar Tamu -->
<div class="mb-4">
    @forelse($guests as $g)
        @php
            $isCheckedOut = !empty($g->check_out_time);
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-1.5 {{ $isCheckedOut ? '' : 'border-start border-success border-3' }}">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge {{ $isCheckedOut ? 'bg-secondary-lt' : 'bg-success text-white' }}" style="font-size: 0.65rem; padding: 2px 6px;">
                        {{ $isCheckedOut ? 'Selesai Berkunjung' : 'Sedang Berkunjung' }}
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        @if($g->badge_number)
                            <span class="badge bg-purple-lt me-1" style="font-size: 0.62rem;">Badge #{{ $g->badge_number }}</span>
                        @endif
                        {{ $g->visit_date?->format('d/m/Y') }}
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-0.5">
                    <div class="fw-bold text-dark fs-4 lh-sm">{{ $g->guest_name }}</div>
                    @if($g->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $g->phone) }}" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0" style="font-size: 0.65rem;">
                            <i class="ti ti-brand-whatsapp me-0.5"></i> WA
                        </a>
                    @endif
                </div>

                <div class="text-muted small mb-1" style="font-size: 0.7rem;">
                    <strong>{{ $g->institution ?: 'Masyarakat / Orang Tua' }}</strong>
                    &bull; Masuk: {{ substr($g->check_in_time, 0, 5) }} WIB
                    @if($isCheckedOut)
                        &bull; Keluar: {{ substr($g->check_out_time, 0, 5) }} WIB
                    @endif
                </div>

                <div class="p-1.5 bg-light rounded-2 small text-secondary mb-1.5" style="font-size: 0.72rem;">
                    <div><strong>Keperluan:</strong> {{ $g->purpose }}</div>
                    <div class="mt-0.5"><strong>Menemui:</strong> {{ $g->meet_with }}</div>
                    @if($g->notes)
                        <div class="text-muted mt-0.5" style="font-size: 0.68rem;">Catatan: {{ $g->notes }}</div>
                    @endif
                </div>

                <div class="d-flex align-items-center justify-content-between pt-1 border-top" style="font-size: 0.68rem;">
                    <span class="text-muted">
                        Piket: {{ $g->recordedBy?->formatted_short_name ?? $g->recordedBy?->name ?? 'Petugas' }}
                    </span>
                    <div class="d-flex gap-1.5">
                        @if(!$isCheckedOut)
                            <form action="{{ route('piket.guests.checkout', $g) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-2.5 py-0.5 fw-bold" style="font-size: 0.68rem;">
                                    <i class="ti ti-logout me-0.5"></i> Check-out
                                </button>
                            </form>
                        @endif
                        @if(auth()->user()->canOperatePiket())
                            <form action="{{ route('piket.guests.destroy', $g) }}" method="POST" onsubmit="return confirm('Hapus catatan tamu ini?')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" style="font-size: 0.7rem;">
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
            <i class="ti ti-users fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Tidak ada data tamu yang berkunjung pada tanggal ini.</div>
        </div>
    @endforelse

    @if($guests->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $guests->links() }}
        </div>
    @endif
</div>

<!-- Framework7 Bottom Sheet: Formulir Catat Tamu Baru -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-add-guest" style="height: auto; max-height: 85vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-user-plus text-primary"></i> Catat Tamu Baru
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <form action="{{ route('piket.guests.store') }}" method="POST">
            @csrf
            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="visit_date" class="form-control form-control-sm" value="{{ $date ?: now()->toDateString() }}" required>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Jam Tiba <span class="text-danger">*</span></label>
                    <input type="time" name="check_in_time" class="form-control form-control-sm" value="{{ now()->format('H:i') }}" required>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Nama Lengkap Tamu <span class="text-danger">*</span></label>
                <input type="text" name="guest_name" class="form-control form-control-sm" placeholder="Contoh: Bpk. Hariyadi" required style="font-size: 0.85rem;">
            </div>

            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Instansi / Lembaga</label>
                    <input type="text" name="institution" class="form-control form-control-sm" placeholder="Contoh: Cabdin Kediri / Wali Murid" style="font-size: 0.82rem;">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">No. HP / WhatsApp</label>
                    <input type="text" name="phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx" style="font-size: 0.82rem;">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Keperluan Kunjungan <span class="text-danger">*</span></label>
                <input type="text" name="purpose" class="form-control form-control-sm" placeholder="Contoh: Koordinasi BOS, Konsultasi siswa..." required style="font-size: 0.82rem;">
            </div>

            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Menemui Siapa <span class="text-danger">*</span></label>
                    <input type="text" name="meet_with" class="form-control form-control-sm" placeholder="Kepala Sekolah / Waka / BK..." required style="font-size: 0.82rem;">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Nomor Badge Tamu</label>
                    <input type="text" name="badge_number" class="form-control form-control-sm" placeholder="01" style="font-size: 0.82rem;">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1">Catatan Tambahan</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Kendaraan, barang titipan, dll..." style="font-size: 0.82rem;">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Kunjungan Tamu</span>
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setGuestPeriod(period) {
    document.getElementById('guestPeriodInput').value = period;
    document.getElementById('guestFilterForm').submit();
}
</script>
@endpush
