@extends('layouts.app')

@section('title', 'Detail Visitasi Siswa: ' . $visitation->student->name)

@section('content')
<div class="container-xl">
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a href="{{ route('visitations.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Visitasi
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('visitations.pdf', $visitation) }}" class="btn btn-sm btn-outline-danger">
                <i class="ti ti-file-text me-1"></i> Cetak Berita Acara (PDF)
            </a>
            @if(auth()->user()->canOperateBk())
                <a href="{{ route('bk.students.history', $visitation->student) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-address-book me-1"></i> Buka Buku Pribadi Siswa
                </a>
            @endif
            @if($canEdit)
                <a href="{{ route('visitations.edit', $visitation) }}" class="btn btn-sm btn-teal text-white">
                    <i class="ti ti-edit me-1"></i> Perbarui Laporan Hasil
                </a>
            @endif
        </div>
    </div>

    <!-- Hero Card -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-body p-4" style="background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-xl rounded-circle bg-teal text-white fw-bold fs-1 shadow-sm flex-shrink-0">
                        {{ strtoupper(substr($visitation->student->name, 0, 2)) }}
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge {{ $visitation->status_badge_class }} fs-4 px-2 py-1">
                                {{ $visitation->status_label }}
                            </span>
                            <span class="badge bg-teal-lt text-teal fw-semibold">
                                {{ $visitation->visit_type_label }}
                            </span>
                        </div>
                        <h1 class="h2 fw-bold mb-1 text-dark">{{ $visitation->student->name }}</h1>
                        <div class="text-muted small d-flex flex-wrap align-items-center gap-3">
                            <span><i class="ti ti-id me-1"></i>NISN: <strong>{{ $visitation->student->nisn ?? '-' }}</strong></span>
                            <span><i class="ti ti-school me-1"></i>Kelas: <strong>{{ $visitation->student->classroom?->name ?? 'Belum ada rombel' }}</strong></span>
                            <span><i class="ti ti-calendar me-1"></i>TP {{ $visitation->academic_year }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="text-muted small">Rencana Pelaksanaan</div>
                    <span class="badge bg-blue text-white fs-3 px-3 py-1 shadow-xs">
                        {{ $visitation->planned_date?->format('d F Y') }}
                    </span>
                    @if($visitation->executed_date)
                        <div class="text-success small mt-1">
                            <i class="ti ti-check me-1"></i>Terlaksana: {{ $visitation->executed_date->format('d/m/Y') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Detail Informasi & Penugasan -->
        <div class="col-lg-4">
            <!-- Card Penugasan Petugas -->
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-light py-2">
                    <h4 class="card-title text-dark mb-0 fw-bold">
                        <i class="ti ti-users me-2 text-primary"></i> Tim Petugas Visitasi
                    </h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Koordinator / Petugas Utama:</div>
                        <div class="fw-bold text-dark fs-3">{{ $visitation->primaryOfficer?->name }}</div>
                        <div class="text-muted small">{{ $visitation->primaryOfficer?->position ?? 'Guru/Staf' }}</div>
                    </div>
                    @if($visitation->secondaryOfficer)
                        <div class="mb-3">
                            <div class="text-muted small">Petugas Pendamping:</div>
                            <div class="fw-semibold text-dark">{{ $visitation->secondaryOfficer->name }}</div>
                            <div class="text-muted small">{{ $visitation->secondaryOfficer->position ?? 'Guru/Staf' }}</div>
                        </div>
                    @endif
                    <div class="pt-2 border-top">
                        <div class="text-muted small">Diusulkan Oleh:</div>
                        <div class="text-dark small">{{ $visitation->requester?->name }} ({{ $visitation->created_at->format('d/m/Y H:i') }})</div>
                    </div>
                </div>
            </div>

            <!-- Card Dokumen Lampiran Privat -->
            <div class="card border shadow-none">
                <div class="card-header bg-light py-2">
                    <h4 class="card-title text-dark mb-0 fw-bold">
                        <i class="ti ti-file-text me-2 text-teal"></i> Berita Acara &amp; Bukti
                    </h4>
                </div>
                <div class="card-body">
                    @if($visitation->attachment_path)
                        <div class="p-3 bg-light rounded border text-center">
                            <i class="ti ti-file-certificate fs-1 text-teal d-block mb-2"></i>
                            <div class="fw-bold text-dark small mb-2">Dokumen Berita Acara Tersimpan</div>
                            <a href="{{ route('visitations.attachment', $visitation) }}" class="btn btn-sm btn-outline-teal w-100">
                                <i class="ti ti-download me-1"></i> Unduh Lampiran Privat
                            </a>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted small">
                            <i class="ti ti-file-off fs-2 d-block mb-1"></i>
                            Belum ada dokumen surat tugas atau berita acara yang diunggah.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Uraian Kebutuhan, Hasil & Rencana Tindak Lanjut -->
        <div class="col-lg-8">
            <!-- Card Alasan / Latar Belakang -->
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-light py-2">
                    <h4 class="card-title text-dark mb-0 fw-bold">
                        <i class="ti ti-help me-2 text-primary"></i> Alasan &amp; Latar Belakang Kebutuhan Visitasi
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-dark mb-0" style="white-space: pre-line;">{{ $visitation->reason }}</p>
                </div>
            </div>

            <!-- Card Hasil Pelaksanaan Visitasi -->
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h4 class="card-title text-dark mb-0 fw-bold">
                        <i class="ti ti-clipboard-check me-2 text-success"></i> Hasil Pelaksanaan &amp; Kondisi Siswa
                    </h4>
                    @if($visitation->executed_date)
                        <span class="badge bg-success-lt text-success">Terlaksana: {{ $visitation->executed_date->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($visitation->result_summary)
                        <p class="text-dark mb-0" style="white-space: pre-line;">{{ $visitation->result_summary }}</p>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-clock fs-2 d-block mb-1"></i>
                            Kegiatan belum dilaporkan hasil pelaksanaannya. Petugas yang ditugaskan dapat mengisi ringkasan hasil melalui menu Perbarui Laporan.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card Rencana Tindak Lanjut -->
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-light py-2">
                    <h4 class="card-title text-dark mb-0 fw-bold">
                        <i class="ti ti-arrow-forward me-2 text-teal"></i> Rencana Tindak Lanjut (RTL)
                    </h4>
                </div>
                <div class="card-body">
                    @if($visitation->follow_up_plan)
                        <p class="text-dark mb-0" style="white-space: pre-line;">{{ $visitation->follow_up_plan }}</p>
                    @else
                        <div class="text-muted small">Belum ada rencana tindak lanjut yang dicatat.</div>
                    @endif
                </div>
            </div>

            <!-- Card Catatan Konfidensial (Hanya untuk yang Berwenang) -->
            @if($canAccessConfidential)
                <div class="card border-danger-subtle bg-red-lt shadow-none">
                    <div class="card-header bg-danger text-white py-2">
                        <h4 class="card-title mb-0 fw-bold">
                            <i class="ti ti-lock me-2"></i> Catatan Sensitif / Konfidensial (Akses Sangat Terbatas)
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($visitation->confidential_notes)
                            <p class="text-dark mb-0" style="white-space: pre-line;">{{ $visitation->confidential_notes }}</p>
                        @else
                            <div class="text-muted small">Tidak ada catatan keluarga sensitif untuk kegiatan ini.</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
