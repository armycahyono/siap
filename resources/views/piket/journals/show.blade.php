@extends('layouts.app')

@section('title', 'Jurnal Piket - ' . $journal->date?->translatedFormat('d F Y'))

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <a href="{{ route('piket.journals.index') }}" class="btn btn-link text-secondary p-0 mb-1">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Riwayat Jurnal
                </a>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-notebook text-primary me-2 fs-1"></i>
                    Jurnal Piket: {{ $journal->date?->translatedFormat('l, d F Y') }}
                </h2>
                <div class="text-secondary mt-1">
                    <span class="badge bg-purple-lt me-1">TP {{ $journal->academic_year }} ({{ $journal->semester }})</span>
                    @if($journal->isCompleted())
                        <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i> Selesai & Divalidasi</span>
                        <span class="text-muted small ms-1">Ditutup oleh {{ $journal->completer?->name ?? 'Petugas' }} pada {{ $journal->completed_at?->format('H:i') }} WIB</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i> Sedang Berlangsung</span>
                    @endif
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <a href="{{ route('piket.journals.print', $journal) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-printer me-1"></i> Cetak Format Dinas
                </a>
                @if(!$journal->isCompleted())
                    <form action="{{ route('piket.journals.complete', $journal) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan dan memvalidasi Jurnal Piket hari ini? Data tidak akan bisa diubah kembali.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success shadow-sm">
                            <i class="ti ti-circle-check me-1"></i> Selesaikan & Tutup Jurnal
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Section 1 & 2: Guru Inval & Perizinan Siswa -->
    <div class="row g-4 mb-4">
        <!-- Guru Inval / Kelas Kosong -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-chalkboard text-info me-2"></i>
                        1. Guru Berhalangan & Guru Inval ({{ $journal->invalClasses->count() }})
                    </h3>
                        @if(!$journal->isCompleted())
                        <a href="{{ route('piket.inval.index') }}" class="btn btn-sm btn-outline-info">
                            + Tambah Inval
                        </a>
                    @endif
                </div>
                <!-- Mobile List (d-md-none) -->
                <div class="d-md-none p-2">
                    @forelse($journal->invalClasses as $inv)
                        <div class="card border shadow-none mb-2 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-blue-lt fw-bold fs-4">{{ $inv->classroom?->name ?? '-' }}</span>
                                    <span class="badge bg-secondary-lt">Jam {{ $inv->period_start }}-{{ $inv->period_end }}</span>
                                </div>
                                <div class="small mb-1">
                                    <span class="text-danger fw-bold"><del>{{ $inv->absentTeacher?->formatted_short_name ?? '-' }}</del></span>
                                    <span class="text-muted">({{ $inv->subject?->name ?? 'Mapel' }} &bull; {{ $inv->reason }})</span>
                                </div>
                                <div class="mt-2 pt-2 border-top">
                                    <span class="badge bg-green-lt fw-bold">
                                        <i class="ti ti-user-check me-1"></i> Guru Inval: {{ $inv->invalTeacher?->name ?? 'Belum Ditugaskan' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-check fs-2 text-success d-block mb-1"></i>
                            Tidak ada laporan guru berhalangan hari ini.
                        </div>
                    @endforelse
                </div>

                <!-- Desktop Table (d-none d-md-block) -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter card-table">
                        <thead class="table-light">
                            <tr>
                                <th>Kelas & Jam</th>
                                <th>Guru Berhalangan</th>
                                <th>Guru Pengganti / Inval</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($journal->invalClasses as $inv)
                                <tr>
                                    <td>
                                        <span class="badge bg-blue-lt fw-bold">{{ $inv->classroom?->name ?? '-' }}</span>
                                        <div class="text-muted small mt-1">Jam {{ $inv->period_start }}-{{ $inv->period_end }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-danger">{{ $inv->absentTeacher?->formatted_short_name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $inv->subject?->name ?? 'Mapel' }} &bull; ({{ $inv->reason }})</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-green-lt fw-bold">
                                            {{ $inv->invalTeacher?->name ?? 'Belum Ditugaskan' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="ti ti-check fs-2 text-success d-block mb-1"></i>
                                        Tidak ada laporan guru berhalangan hari ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Perizinan Siswa -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-door-exit text-warning me-2"></i>
                        2. Perizinan & Keterlambatan Siswa ({{ $journal->studentPermits->count() }})
                    </h3>
                    @if(!$journal->isCompleted())
                        <a href="{{ route('piket.permits.create') }}" class="btn btn-sm btn-outline-warning text-dark">
                            + Buat Izin
                        </a>
                    @endif
                </div>
                <!-- Mobile List (d-md-none) -->
                <div class="d-md-none p-2">
                    @forelse($journal->studentPermits as $p)
                        <div class="card border shadow-none mb-2 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $p->student_name }}</div>
                                        <span class="badge bg-blue-lt">{{ $p->classroom?->name ?? '-' }}</span>
                                    </div>
                                    <span class="badge {{ $p->permit_type_badge_class }}">{{ $p->permit_type_label }}</span>
                                </div>
                                <div class="text-muted small my-1">
                                    <i class="ti ti-clock me-1"></i>{{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }} WIB &bull; {{ $p->reason }}
                                </div>
                                <div class="pt-2 border-top text-end">
                                    <a href="{{ route('piket.permits.slip', $p) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Cetak Slip">
                                        <i class="ti ti-printer me-1"></i> Slip Izin
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-mood-smile fs-2 text-success d-block mb-1"></i>
                            Tidak ada perizinan atau siswa terlambat hari ini.
                        </div>
                    @endforelse
                </div>

                <!-- Desktop Table (d-none d-md-block) -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-vcenter card-table">
                        <thead class="table-light">
                            <tr>
                                <th>Siswa & Kelas</th>
                                <th>Jenis Izin</th>
                                <th>Waktu</th>
                                <th class="text-end">Slip</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($journal->studentPermits as $p)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $p->student_name }}</div>
                                        <span class="badge bg-blue-lt">{{ $p->classroom?->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $p->permit_type_badge_class }}">{{ $p->permit_type_label }}</span>
                                        <div class="text-muted small text-truncate" style="max-width: 150px;">{{ $p->reason }}</div>
                                    </td>
                                    <td>
                                        <span class="text-dark small fw-bold">
                                            {{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }} WIB
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('piket.permits.slip', $p) }}" target="_blank" class="btn btn-sm btn-ghost-primary" title="Cetak Slip">
                                            <i class="ti ti-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="ti ti-mood-smile fs-2 text-success d-block mb-1"></i>
                                        Tidak ada perizinan atau siswa terlambat hari ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3 & 4: Catatan Kasus & Buku Tamu -->
    <div class="row g-4 mb-4">
        <!-- Catatan Kasus -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-alert-triangle text-danger me-2"></i>
                        3. Catatan Kejadian / Kasus ({{ $journal->incidents->count() }})
                    </h3>
                    @if(!$journal->isCompleted())
                        <a href="{{ route('piket.incidents.create') }}" class="btn btn-sm btn-outline-danger">
                            + Catat Kasus
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($journal->incidents as $inc)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark">{{ $inc->student_name }} ({{ $inc->classroom?->name ?? '-' }})</span>
                                    <span class="badge bg-danger-lt">{{ $inc->category_label }}</span>
                                </div>
                                <div class="text-secondary small mt-1">{{ $inc->description }}</div>
                                <div class="text-muted small mt-1">
                                    <strong>Tindakan:</strong> {{ $inc->action_taken }} &bull;
                                    <strong>Status:</strong> <span class="badge bg-secondary-lt">{{ $inc->follow_up_label }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ti ti-shield-check fs-2 text-success d-block mb-1"></i>
                                Tidak ada kejadian pelanggaran atau kasus tercatat hari ini. Kondisi tertib.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Buku Tamu -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-address-book text-success me-2"></i>
                        4. Buku Tamu ({{ $journal->guests->count() }})
                    </h3>
                    @if(!$journal->isCompleted())
                        <a href="{{ route('piket.guests.index') }}" class="btn btn-sm btn-outline-success">
                            + Buku Tamu
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($journal->guests as $g)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark">{{ $g->guest_name }} ({{ $g->institution ?: 'Umum' }})</span>
                                    <span class="badge bg-blue-lt">{{ substr($g->check_in_time, 0, 5) }} WIB</span>
                                </div>
                                <div class="text-secondary small mt-1">
                                    <strong>Keperluan:</strong> {{ $g->purpose }} (Menemui: {{ $g->meet_with }})
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ti ti-users fs-2 d-block mb-1"></i>
                                Belum ada buku tamu hari ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 5: Catatan Situasi Umum Sekolah & Pengesahan -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <h3 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-file-description text-primary me-2"></i>
                5. Catatan Situasi Umum & Pengesahan Jurnal
            </h3>
        </div>
        <form action="{{ route('piket.journals.update-notes', $journal) }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Koordinator Guru Piket Hari Ini</label>
                        <select name="coordinator_id" class="form-select" {{ $journal->isCompleted() ? 'disabled' : '' }}>
                            <option value="">-- Pilih Koordinator --</option>
                            @foreach($scheduledTeachers as $sched)
                                <option value="{{ $sched->teacher_id }}" {{ $journal->coordinator_id == $sched->teacher_id ? 'selected' : '' }}>
                                    {{ $sched->teacher?->name }} ({{ $sched->teacher?->formatted_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan Situasi Umum Sekolah</label>
                        <textarea name="general_notes" class="form-control" rows="4" placeholder="Tuliskan catatan kondisi cuaca, pelaksanaan apel/upacara, kebersihan lingkungan, atau hal penting lainnya..." {{ $journal->isCompleted() ? 'disabled' : '' }}>{{ $journal->general_notes }}</textarea>
                    </div>
                </div>
            </div>
            @if(!$journal->isCompleted())
                <div class="card-footer bg-white text-end py-3">
                    <button type="submit" class="btn btn-primary w-100 w-sm-auto">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Catatan Umum
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
