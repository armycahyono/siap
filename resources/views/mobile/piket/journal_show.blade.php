@extends('layouts.mobile')

@section('title', 'Jurnal Piket ' . $journal->date?->format('d/m/Y') . ' — APK SIAP')
@section('page_title', 'Jurnal Piket Harian')
@section('page_subtitle', $journal->date?->translatedFormat('l, d F Y'))
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Header Card Status Jurnal -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-end mb-1">
            @if($journal->isCompleted())
                <span class="badge bg-success text-white" style="font-size: 0.65rem;">
                    <i class="ti ti-check me-0.5"></i> Selesai &amp; Divalidasi
                </span>
            @else
                <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">
                    <i class="ti ti-clock me-0.5"></i> Sedang Berlangsung
                </span>
            @endif
        </div>
        <div class="fw-bold fs-3 lh-sm mb-1">Jurnal Piket Meja Utama</div>
        <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
            Koordinator: <strong>{{ $journal->coordinator?->formatted_short_name ?? $journal->coordinator?->name ?? 'Belum ditentukan' }}</strong>
            @if($journal->isCompleted())
                &bull; Ditutup: {{ $journal->completed_at?->format('H:i') }} WIB
            @endif
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('piket.journals.print', $journal) }}" target="_blank" class="btn btn-sm btn-outline-light rounded-pill flex-fill py-1 text-white" style="font-size: 0.72rem;">
                <i class="ti ti-printer me-0.5"></i> Cetak Laporan
            </a>
            @if(!$journal->isCompleted())
                <form action="{{ route('piket.journals.complete', $journal) }}" method="POST" onsubmit="return confirm('Selesaikan dan tutup jurnal piket hari ini?')" class="flex-fill m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success fw-bold rounded-pill w-100 py-1" style="font-size: 0.72rem;">
                        <i class="ti ti-circle-check me-0.5"></i> Tutup Jurnal
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<!-- Statistik Ringkas Jurnal (Grid 2x2) -->
<div class="mobile-grid-2 mb-2">
    <div class="card border shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Petugas Piket</span>
            <div class="fw-bold text-primary fs-2 lh-1 mt-0.5">
                {{ $journal->attendances->where('status', 'hadir')->count() }}/{{ $journal->attendances->count() }}
                <span style="font-size: 0.7rem;">Hadir</span>
            </div>
        </div>
    </div>
    <div class="card border shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Kelas Butuh Inval</span>
            <div class="fw-bold text-danger fs-2 lh-1 mt-0.5">{{ $journal->invalClasses->count() }} <span style="font-size: 0.7rem;">Kelas</span></div>
        </div>
    </div>
    <div class="card border shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Izin / Terlambat</span>
            <div class="fw-bold text-warning fs-2 lh-1 mt-0.5">{{ $journal->studentPermits->count() }} <span style="font-size: 0.7rem;">Siswa</span></div>
        </div>
    </div>
    <div class="card border shadow-sm rounded-3">
        <div class="card-body p-2 text-center">
            <span class="text-muted small" style="font-size: 0.68rem;">Tamu Berkunjung</span>
            <div class="fw-bold text-success fs-2 lh-1 mt-0.5">{{ $journal->guests->count() }} <span style="font-size: 0.7rem;">Orang</span></div>
        </div>
    </div>
</div>

<!-- Section: Petugas Piket Hari Ini -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold text-dark fs-4">
                <i class="ti ti-users text-primary me-1"></i> Petugas Piket ({{ $journal->attendances->where('status', 'hadir')->count() }}/{{ $journal->attendances->count() }} Hadir)
            </div>
        </div>
        <div class="d-flex flex-column gap-1.5">
            @forelse($journal->attendances as $att)
                @php
                    $teacher = $att->teacher;
                    $initials = '';
                    if ($teacher?->name) {
                        $words = explode(' ', trim($teacher->name));
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    }
                @endphp
                <div class="p-2 rounded-2 border bg-light d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 overflow-hidden w-100">
                        @if($teacher && $teacher->avatar)
                            <img src="{{ asset('storage/' . $teacher->avatar) }}" alt="{{ $teacher->name }}" class="flex-shrink-0 rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.onerror=null; this.src='/favicon.ico';">
                        @else
                            <div class="flex-shrink-0 rounded-circle bg-primary-lt text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.72rem;">
                                {{ $initials ?: 'GTK' }}
                            </div>
                        @endif
                        <div class="overflow-hidden flex-fill">
                            <div class="d-flex align-items-center justify-content-between gap-1">
                                <div class="fw-bold text-dark small lh-sm text-truncate">{{ $teacher?->formatted_short_name ?? $teacher?->name ?? 'Petugas' }}</div>
                                <div class="flex-shrink-0" style="font-size: 0.72rem;">
                                    @if($att->status === 'hadir')
                                        <span class="text-success fw-bold">Hadir</span>
                                    @elseif($att->status === 'terlambat')
                                        <span class="text-warning fw-bold">Terlambat</span>
                                    @elseif(in_array($att->status, ['izin', 'sakit']))
                                        <span class="text-info fw-bold">{{ ucfirst($att->status) }}</span>
                                    @elseif($att->status === 'belum_hadir')
                                        <span class="text-secondary fw-semibold">Belum Hadir</span>
                                    @else
                                        <span class="text-danger fw-bold">Alpa</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-muted text-truncate" style="font-size: 0.68rem;">
                                @if($att->notes)
                                    <span class="text-secondary fw-semibold">{{ $att->notes }}</span> &bull;
                                @endif
                                @if($att->status === 'hadir' || $att->check_in_time)
                                    M: {{ $att->check_in_time ? substr($att->check_in_time, 0, 5) . ' WIB' : '-' }}
                                    &bull; P: {{ $att->check_out_time ? substr($att->check_out_time, 0, 5) . ' WIB' : '-' }}
                                @elseif($att->status === 'belum_hadir')
                                    <span class="text-danger fw-semibold">Belum Presensi</span>
                                @elseif(in_array($att->status, ['izin', 'sakit']))
                                    <span class="text-info fw-semibold">{{ ucfirst($att->status) }}</span>
                                @else
                                    <span class="text-danger fw-semibold">Alpa</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted small text-center py-2">Belum ada daftar petugas piket terjadwal.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Section: Guru Inval / Kelas Kosong -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold text-dark fs-4">
                <i class="ti ti-chalkboard text-info me-1"></i> Kelas Inval ({{ $journal->invalClasses->count() }})
            </div>
            @if(!$journal->isCompleted())
                <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-outline-info rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                    + Inval
                </a>
            @endif
        </div>
        <div class="d-flex flex-column gap-1.5">
            @forelse($journal->invalClasses as $inv)
                <div class="p-2 rounded-2 border bg-white">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.68rem;">{{ $inv->classroom?->name }}</span>
                        <span class="text-muted small" style="font-size: 0.68rem;">Jam ke-{{ $inv->period_start }}-{{ $inv->period_end }}</span>
                    </div>
                    <div class="small text-danger lh-sm">
                        <i class="ti ti-user-x me-0.5"></i> {{ $inv->absentTeacher?->formatted_short_name ?? $inv->absentTeacher?->name }} ({{ $inv->reason }})
                    </div>
                    <div class="small text-success lh-sm mt-0.5">
                        <i class="ti ti-user-check me-0.5"></i> Inval: <strong>{{ $inv->invalTeacher?->formatted_short_name ?? $inv->invalTeacher?->name ?? 'Belum Ditugaskan' }}</strong>
                    </div>
                </div>
            @empty
                <div class="text-muted small text-center py-2">Tidak ada laporan kelas butuh inval.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Section: Siswa Terlambat / Izin -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold text-dark fs-4">
                <i class="ti ti-door-exit text-warning me-1"></i> Perizinan Siswa ({{ $journal->studentPermits->count() }})
            </div>
            @if(!$journal->isCompleted())
                <a href="{{ route('piket.permits.create') }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                    + Izin
                </a>
            @endif
        </div>
        <div class="d-flex flex-column gap-1.5">
            @forelse($journal->studentPermits as $p)
                <div class="p-2 rounded-2 border bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark small lh-sm">{{ $p->student_name }} ({{ $p->classroom?->name }})</div>
                        <div class="text-muted" style="font-size: 0.68rem;">{{ $p->reason }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                        <span class="badge bg-warning-lt text-dark" style="font-size: 0.62rem;">
                            {{ match($p->permit_type) { 'terlambat' => 'Terlambat', 'izin_keluar_sementara' => 'Keluar', 'izin_pulang_cepat' => 'Pulang Cepat', default => 'Izin' } }}
                        </span>
                        <a href="{{ route('piket.permits.slip', $p) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size: 0.65rem;" title="Cetak Slip">
                            <i class="ti ti-printer"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-muted small text-center py-2">Tidak ada perizinan siswa.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Section: Buku Tamu -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold text-dark fs-4">
                <i class="ti ti-users text-success me-1"></i> Tamu Berkunjung ({{ $journal->guests->count() }})
            </div>
            @if(!$journal->isCompleted())
                <a href="{{ route('piket.guests.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                    + Tamu
                </a>
            @endif
        </div>
        <div class="d-flex flex-column gap-1.5">
            @forelse($journal->guests as $g)
                <div class="p-2 rounded-2 border bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark small lh-sm">{{ $g->guest_name }}</div>
                        <div class="text-muted" style="font-size: 0.68rem;">{{ $g->institution ?: 'Wali/Umum' }} &bull; Menemui: {{ $g->meet_with }}</div>
                    </div>
                    <span class="badge {{ $g->check_out_time ? 'bg-secondary-lt' : 'bg-success-lt text-success' }}" style="font-size: 0.62rem;">
                        {{ $g->check_out_time ? 'Selesai' : 'Aktif' }}
                    </span>
                </div>
            @empty
                <div class="text-muted small text-center py-2">Tidak ada data kunjungan tamu.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Section: Catatan Situasi Umum & Pengesahan Jurnal -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-2 px-3">
        <div class="fw-bold text-dark fs-4 mb-2 pb-1 border-bottom d-flex align-items-center gap-1.5">
            <i class="ti ti-file-description text-primary"></i> Catatan Situasi Umum &amp; Pengesahan
        </div>
        <form action="{{ route('piket.journals.update-notes', $journal) }}" method="POST">
            @csrf
            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Koordinator Guru Piket Hari Ini</label>
                <select name="coordinator_id" class="form-select form-select-sm" {{ $journal->isCompleted() ? 'disabled' : '' }} style="font-size: 0.82rem;">
                    <option value="">-- Pilih Koordinator --</option>
                    @foreach($scheduledTeachers as $sched)
                        <option value="{{ $sched->teacher_id }}" {{ $journal->coordinator_id == $sched->teacher_id ? 'selected' : '' }}>
                            {{ $sched->teacher?->formatted_short_name ?? $sched->teacher?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Catatan Situasi Umum Sekolah</label>
                <textarea name="general_notes" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan kondisi cuaca, pelaksanaan apel/upacara, kebersihan, situasi keamanan, dll..." {{ $journal->isCompleted() ? 'disabled' : '' }} style="font-size: 0.82rem;">{{ $journal->general_notes }}</textarea>
            </div>
            @if(!$journal->isCompleted())
                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-1.5 fw-bold" style="font-size: 0.82rem;">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Catatan Umum
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
