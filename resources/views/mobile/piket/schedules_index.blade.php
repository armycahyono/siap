@extends('layouts.mobile')

@section('title', 'Jadwal Petugas Piket — APK SIAP')
@section('page_title', 'Jadwal Petugas Piket')
@section('page_subtitle', 'TP ' . $academicYear . ' (' . $semester . ')')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Header Info Card -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                TP {{ $academicYear }} &bull; Semester {{ $semester }}
            </span>
            <span class="badge bg-white-lt text-white" style="font-size: 0.65rem;">
                Hari Ini: {{ match(now()->dayOfWeekIso) { 1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', default=>'Minggu' } }}
            </span>
        </div>
        <div class="fw-bold fs-3 lh-sm mb-1">Jadwal Tugas Meja Piket</div>
        <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
            Pembagian tugas harian, koordinator lapangan, dan pos meja piket.
        </div>
        @if(auth()->user()->canManagePiketSchedules())
            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill w-100 py-1.5 shadow-sm sheet-open d-flex align-items-center justify-content-center gap-1.5" data-sheet="#sheet-add-schedule" style="font-size: 0.82rem;">
                <i class="ti ti-user-plus fs-3"></i>
                <span>+ Tetapkan Petugas Baru</span>
            </button>
        @endif
    </div>
</div>

@php
    $currentDayName = match(now()->dayOfWeekIso) { 1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', default=>'Senin' };
@endphp

<!-- Day Navigation Pills -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-1.5 d-flex gap-1 overflow-auto" id="sched-day-tabs">
        @foreach($days as $day)
            <button type="button" class="btn btn-sm rounded-pill flex-fill sched-tab-btn {{ $day === $currentDayName ? 'btn-primary fw-bold active' : 'btn-light text-dark' }}" data-day="{{ $day }}" style="font-size: 0.72rem; white-space: nowrap; padding: 4px 10px;">
                {{ $day }} ({{ $groupedSchedules[$day]->count() }})
            </button>
        @endforeach
    </div>
</div>

<!-- Schedules List Content per Day -->
<div class="mb-4">
    @foreach($days as $day)
        <div class="sched-day-panel {{ $day === $currentDayName ? '' : 'd-none' }}" id="panel-{{ $day }}">
            <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
                <span class="fw-bold text-dark fs-4">Hari {{ $day }}</span>
                <span class="text-muted small" style="font-size: 0.7rem;">{{ $groupedSchedules[$day]->count() }} Petugas Bertugas</span>
            </div>

            @forelse($groupedSchedules[$day] as $ps)
                @php
                    $isKoord = ($ps->role === 'koordinator');
                    $isMe = (auth()->id() === $ps->teacher_id);
                @endphp
                <div class="card border-0 shadow-sm rounded-3 mb-1.5 {{ $isMe ? 'border border-purple' : '' }}">
                    <div class="card-body p-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge {{ $isKoord ? 'bg-danger text-white' : 'bg-blue-lt text-primary' }}" style="font-size: 0.65rem; padding: 2px 7px;">
                                <i class="ti {{ $isKoord ? 'ti-crown' : 'ti-user-shield' }} me-0.5"></i>
                                {{ $isKoord ? 'Koordinator Piket' : 'Anggota Piket' }}
                            </span>
                            @if($isMe)
                                <span class="badge bg-purple text-white" style="font-size: 0.62rem;">Jadwal Anda</span>
                            @endif
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-0.5">
                            <div class="fw-bold text-dark fs-4 lh-sm">
                                {{ $ps->teacher?->formatted_short_name ?? $ps->teacher?->name }}
                            </div>
                            @if($ps->teacher?->phone)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $ps->teacher->phone) }}" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0" style="font-size: 0.65rem;">
                                    <i class="ti ti-brand-whatsapp me-0.5"></i> WA
                                </a>
                            @endif
                        </div>

                        <div class="d-flex align-items-center justify-content-between text-muted small mt-1" style="font-size: 0.72rem;">
                            <span>
                                <i class="ti ti-map-pin me-0.5 text-secondary"></i> Pos: <strong>{{ $ps->duty_location ?: 'Meja Piket Utama' }}</strong>
                            </span>
                            @if(auth()->user()->canManagePiketSchedules())
                                <form action="{{ route('piket.schedules.destroy', $ps) }}" method="POST" onsubmit="return confirm('Hapus penugasan piket ini?')" class="d-inline">
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
            @empty
                <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                    <i class="ti ti-calendar-x fs-1 d-block mb-1 opacity-50"></i>
                    <div class="small fw-semibold">Belum ada petugas piket yang ditetapkan untuk hari {{ $day }}.</div>
                </div>
            @endforelse
        </div>
    @endforeach
</div>

@if(auth()->user()->canManagePiketSchedules())
<!-- Framework7 Bottom Sheet: Tambah Petugas Piket -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-add-schedule" style="height: auto; max-height: 85vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-calendar-user text-primary"></i> Tetapkan Petugas Piket
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <form action="{{ route('piket.schedules.store') }}" method="POST">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Hari Bertugas <span class="text-danger">*</span></label>
                <select name="day" class="form-select form-select-sm" required style="font-size: 0.85rem;">
                    @foreach($days as $d)
                        <option value="{{ $d }}" {{ $d === $currentDayName ? 'selected' : '' }}>Hari {{ $d }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Guru Petugas <span class="text-danger">*</span></label>
                <select name="teacher_id" class="form-select form-select-sm" required style="font-size: 0.85rem;">
                    <option value="">-- Pilih Guru --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->formatted_short_name ?? $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Peran Petugas <span class="text-danger">*</span></label>
                    <select name="role" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                        <option value="anggota">Anggota Piket</option>
                        <option value="koordinator">Koordinator</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Pos / Lokasi Tugas</label>
                    <input type="text" name="duty_location" class="form-control form-control-sm" placeholder="Meja Piket Utama" value="Meja Piket Utama" style="font-size: 0.82rem;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2 mt-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Penetapan Piket</span>
            </button>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('.sched-tab-btn');
    const panels = document.querySelectorAll('.sched-day-panel');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const selectedDay = this.getAttribute('data-day');

            tabButtons.forEach(b => {
                b.classList.remove('btn-primary', 'fw-bold', 'active');
                b.classList.add('btn-light', 'text-dark');
            });
            this.classList.remove('btn-light', 'text-dark');
            this.classList.add('btn-primary', 'fw-bold', 'active');

            panels.forEach(p => {
                if (p.id === 'panel-' + selectedDay) {
                    p.classList.remove('d-none');
                } else {
                    p.classList.add('d-none');
                }
            });
        });
    });
});
</script>
@endpush
