@extends('layouts.app')

@section('title', 'Detail Rujukan Kasus Meja Piket — BK')

@section('content')
<div class="container-xl">
    <div class="mb-3">
        <a href="{{ route('bk.referrals.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Rujukan
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row row-cards">
        <!-- Kolom Kiri: Informasi Kasus & Siswa -->
        <div class="col-lg-7">
            <!-- Kartu Identitas Kasus Piket -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-danger-lt d-flex justify-content-between align-items-center">
                    <h3 class="card-title text-danger fw-bold mb-0">
                        <i class="ti ti-alert-triangle me-2"></i> Informasi Kejadian dari Meja Piket
                    </h3>
                    <span class="badge bg-danger text-white">{{ $incident->category_label }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Nama Siswa</span>
                            <span class="fw-bold fs-3 text-dark">{{ $incident->student_name }}</span>
                            @if($incident->student)
                                <div class="mt-1">
                                    <a href="{{ route('bk.students.history', ['student' => $incident->student, 'academic_year' => $academicYear]) }}" class="btn btn-xs btn-outline-primary" style="font-size: 0.75rem;">
                                        <i class="ti ti-history me-1"></i> Riwayat Layanan Siswa Ini
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Kelas &amp; NISN</span>
                            <span class="fw-semibold text-dark">{{ $incident->classroom?->name ?? 'Kelas -' }}</span>
                            <div class="text-muted small">NISN: {{ $incident->student?->nisn ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Waktu Kejadian</span>
                            <span class="fw-semibold text-dark">{{ $incident->incident_date->format('d F Y') }}</span>
                            <div class="text-muted small">{{ substr($incident->incident_time, 0, 5) }} WIB</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small">Petugas Pencatat Piket</span>
                            <span class="fw-semibold text-dark">{{ $incident->recordedBy?->name ?? 'Petugas Piket' }}</span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Uraian Kejadian Siswa</span>
                            <div class="p-3 bg-light rounded border text-dark mt-1" style="white-space: pre-line;">{{ $incident->description }}</div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Tindakan Awal Meja Piket</span>
                            <div class="p-3 bg-light rounded border text-dark mt-1" style="white-space: pre-line;">{{ $incident->action_taken }}</div>
                        </div>
                        @if($incident->confiscated_items)
                            <div class="col-12">
                                <span class="text-muted d-block small">Barang Diamankan / Disita</span>
                                <div class="p-2 bg-warning-lt rounded border text-warning-emphasis mt-1">{{ $incident->confiscated_items }}</div>
                            </div>
                        @endif
                        @if($incident->evidence_photo)
                            <div class="col-12">
                                <span class="text-muted d-block small mb-1">Foto Bukti Meja Piket</span>
                                <a href="{{ route('piket.incidents.photo', $incident) }}" target="_blank" class="d-inline-block border rounded p-1">
                                    <img src="{{ route('piket.incidents.photo', $incident) }}" alt="Bukti Kejadian" class="img-fluid rounded" style="max-height: 220px;">
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Status Tindak Lanjut BK & Formulir Update -->
        <div class="col-lg-5">
            @php
                $bkRef = $incident->bkReferral;
                $currentStatus = $bkRef?->status ?? 'menunggu';
            @endphp
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-primary-lt">
                    <h3 class="card-title text-primary fw-bold mb-0">
                        <i class="ti ti-clock-check me-2"></i> Status Tindak Lanjut BK
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center p-3 rounded {{ $bkRef?->status_badge_class ?? 'bg-warning-lt text-warning' }}">
                        <div class="small text-uppercase fw-bold opacity-75">Status Saat Ini</div>
                        <div class="h2 mb-0">{{ $bkRef?->status_label ?? 'Menunggu Tindak Lanjut' }}</div>
                        @if($bkRef?->handler)
                            <div class="small mt-1 text-muted">Ditangani oleh: <strong>{{ $bkRef->handler->name }}</strong></div>
                        @endif
                        @if($bkRef?->completed_at)
                            <div class="small text-muted">Selesai pada: {{ $bkRef->completed_at->format('d/m/Y H:i') }} WIB</div>
                        @endif
                    </div>

                    @if($bkRef?->notes)
                        <div class="mb-3">
                            <span class="text-muted d-block small">Catatan Perkembangan BK:</span>
                            <div class="p-2 bg-light rounded border small text-dark mt-1">{{ $bkRef->notes }}</div>
                        </div>
                    @endif

                    <!-- Form Update Status BK -->
                    <form action="{{ route('bk.referrals.status', $incident) }}" method="POST" class="border-top pt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Perbarui Status Tindak Lanjut</label>
                            <select name="status" class="form-select" required>
                                <option value="menunggu" {{ $currentStatus === 'menunggu' ? 'selected' : '' }}>Menunggu Tindak Lanjut</option>
                                <option value="diproses" {{ $currentStatus === 'diproses' ? 'selected' : '' }}>Sedang Diproses BK</option>
                                <option value="selesai" {{ $currentStatus === 'selesai' ? 'selected' : '' }}>Selesai Ditindaklanjuti</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan Progres / Tindak Lanjut</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="Tuliskan catatan tindak lanjut bimbingan...">{{ old('notes', $bkRef?->notes) }}</textarea>
                            <span class="text-muted small">Catatan ini bersifat operasional rujukan (bukan catatan rahasia).</span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan Status
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Baris Penuh: Sesi Layanan Bimbingan & Konseling Terkait Kasus Ini -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-notes me-2 text-primary"></i> Sesi Layanan Konseling untuk Kasus Ini ({{ $incident->bkServices->count() }})
                    </h3>
                    <a href="{{ route('bk.services.create', ['incident_id' => $incident->id]) }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i> Catat Sesi Layanan Baru
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Tanggal</th>
                                <th style="width: 140px;">Jenis Layanan</th>
                                <th>Ringkasan Masalah</th>
                                <th>Hasil &amp; Rencana Tindak Lanjut</th>
                                <th style="width: 160px;">Guru BK</th>
                                <th style="width: 100px;" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incident->bkServices as $service)
                                <tr>
                                    <td class="fw-bold">{{ $service->service_date->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge {{ $service->service_type_badge_class }}">{{ $service->service_type_label }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ Str::limit($service->summary, 80) }}</div>
                                    </td>
                                    <td>
                                        <div class="small text-muted">{{ Str::limit($service->result, 80) }}</div>
                                        @if($service->follow_up_plan)
                                            <div class="small text-primary mt-1"><em>RTL:</em> {{ Str::limit($service->follow_up_plan, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small fw-semibold">{{ $service->counselor?->name ?? 'Guru BK' }}</div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('bk.services.show', $service) }}" class="btn btn-xs btn-outline-primary">
                                            <i class="ti ti-eye me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Belum ada sesi layanan BK yang dicatat untuk rujukan piket ini.
                                        <div class="mt-2">
                                            <a href="{{ route('bk.services.create', ['incident_id' => $incident->id]) }}" class="btn btn-sm btn-primary">
                                                <i class="ti ti-plus me-1"></i> Mulai Catat Layanan BK
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
