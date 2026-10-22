@extends('layouts.app')

@section('title', 'Tinjau & Pengesahan Supervisi: ' . $supervision->teacher->name)

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-certificate me-2 text-warning"></i> Lembar Verifikasi & Persetujuan Supervisi
        </h2>
        <div class="text-muted small">
            Periksa seluruh hasil supervisi sebelum menyetujui, menandatangani, dan menerbitkan dokumen resmi.
        </div>
    </div>
    <div class="col-auto d-flex gap-2">
        <a href="{{ route('approvals.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Antrean
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Main Review Summary -->
    <div class="col-lg-8">
        <!-- Identity -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h3 class="card-title fw-bold text-dark mb-0">Identitas Laporan</h3>
                <span class="badge bg-secondary-lt font-monospace">{{ $supervision->report_number ?: 'SPV/AUTO/SMK PGRI KRAS/' . date('Y') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Guru yang Disupervisi:</small>
                        <h3 class="fw-bold text-navy mb-0">{{ $supervision->teacher->name }}</h3>
                        <small class="text-muted">
                            @if(!empty($supervision->teacher->nip))
                                NIP: {{ $supervision->teacher->nip }}
                            @else
                                NIGS: {{ $supervision->teacher->nigs_fix ?: $supervision->teacher->nigs ?: '-' }}
                            @endif
                            @if(!empty($supervision->teacher->nuptk))
                                | NUPTK: {{ $supervision->teacher->nuptk }}
                            @endif
                        </small>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Supervisor yang Menilai:</small>
                        <h3 class="fw-bold text-dark mb-0">{{ $supervision->supervisor->name }}</h3>
                        <small class="text-muted">
                            @if(!empty($supervision->supervisor->nip))
                                NIP: {{ $supervision->supervisor->nip }}
                            @else
                                NIGS: {{ $supervision->supervisor->nigs_fix ?: $supervision->supervisor->nigs ?: '-' }}
                            @endif
                            @if(!empty($supervision->supervisor->nuptk))
                                | NUPTK: {{ $supervision->supervisor->nuptk }}
                            @endif
                        </small>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Mata Pelajaran:</small>
                        <strong>{{ $supervision->subject }}</strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Kelas & Keahlian:</small>
                        <strong>{{ $supervision->class_name }}</strong> ({{ $supervision->expertise_program ?: 'Umum' }})
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Tanggal Supervisi:</small>
                        <strong>{{ $supervision->schedule_date->translatedFormat('d F Y') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scores Breakdown -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-list-check me-2 text-primary"></i> Ringkasan Nilai Komponen
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr class="text-muted small">
                            <th>Komponen Supervisi</th>
                            <th class="text-center">Skor Perolehan</th>
                            <th class="text-center">Skor Maksimal</th>
                            <th class="text-center">Ketercapaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $grouped = $supervision->scores->groupBy(fn($s) => $s->indicator?->phase?->name ?? 'Lainnya');
                        @endphp
                        @foreach($grouped as $phaseName => $scoresGroup)
                        @php
                            $pTotal = $scoresGroup->sum('score');
                            $pMax = $scoresGroup->sum(fn($s) => $s->indicator?->max_score ?? 4);
                            $pPct = $pMax > 0 ? round(($pTotal / $pMax) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="fw-bold text-dark">{{ $phaseName }}</td>
                            <td class="text-center fw-bold">{{ $pTotal }}</td>
                            <td class="text-center text-muted">{{ $pMax }}</td>
                            <td class="text-center">
                                <span class="badge bg-blue-lt fw-bold">{{ $pPct }}%</span>
                            </td>
                        </tr>
                        @endforeach
                        <tr class="table-primary fw-bold">
                            <td class="text-dark">TOTAL KESELURUHAN (23 BUTIR)</td>
                            <td class="text-center text-dark">{{ $supervision->total_score }}</td>
                            <td class="text-center text-dark">{{ $supervision->max_score }}</td>
                            <td class="text-center">
                                <span class="badge bg-primary text-white fw-bold">{{ $supervision->percentage }}%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Supervisor Notes & Feedbacks -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-messages me-2 text-primary"></i> Catatan & Rekomendasi Supervisor
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong class="text-success d-block mb-1"><i class="ti ti-thumb-up me-1"></i> Catatan Positif:</strong>
                    <div class="p-3 bg-light rounded text-dark">{{ $supervision->post_positive ?: '-' }}</div>
                </div>
                <div class="mb-3">
                    <strong class="text-warning d-block mb-1"><i class="ti ti-alert-circle me-1"></i> Hal yang Perlu Ditingkatkan:</strong>
                    <div class="p-3 bg-light rounded text-dark">{{ $supervision->post_improvement ?: '-' }}</div>
                </div>
                <div class="mb-3">
                    <strong class="text-primary d-block mb-1"><i class="ti ti-bulb me-1"></i> Rekomendasi:</strong>
                    <div class="p-3 bg-light rounded text-dark">{{ $supervision->post_recommendation ?: '-' }}</div>
                </div>
                <div class="mb-0">
                    <strong class="text-info d-block mb-1"><i class="ti ti-arrow-forward me-1"></i> Rencana Tindak Lanjut:</strong>
                    <div class="p-3 bg-light rounded text-dark">{{ $supervision->post_followup ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Approval Action Panel -->
    <div class="col-lg-4">
        <!-- Score Highlights -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center p-4">
                <span class="text-muted small d-block">Hasil Akhir Supervisi</span>
                <div class="display-3 fw-bold text-primary mb-1">{{ $supervision->percentage }}%</div>
                <div>
                    <span class="badge bg-{{ $supervision->predicate === 'Sangat Baik' ? 'success' : 'primary' }} fs-3 px-3 py-1">
                        {{ $supervision->predicate }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Approve Form -->
        <div class="card border-0 shadow-sm mb-4 border-top border-success border-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-check me-2 text-success"></i> Sahkan Dokumen Supervisi
                </h3>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="ti ti-user-check me-1"></i> Penyetuju: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->role?->name }})
                </div>
                <form action="{{ route('approvals.approve', $supervision) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Persetujuan (Opsional):</label>
                        <textarea name="principal_notes" class="form-control" rows="3" placeholder="Tuliskan catatan apresiasi atau arahan pengesahan...">Telah diperiksa dan disetujui. Pertahankan kinerja pembelajaran yang baik.</textarea>
                    </div>
                    <div class="small text-muted mb-3">
                        <i class="ti ti-info-circle me-1"></i> Dokumen akan resmi disetujui, ditandatangani secara digital, dan barcode QR validasi keaslian akan diaktifkan.
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold fs-3">
                        <i class="ti ti-certificate me-1"></i> Sahkan & Terbitkan Laporan
                    </button>
                </form>
            </div>
        </div>

        <!-- Reject / Revision Form -->
        <div class="card border-0 shadow-sm border-top border-warning border-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-rotate me-2 text-warning"></i> Kembalikan untuk Revisi
                </h3>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('approvals.reject', $supervision) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label required fw-bold">Alasan / Catatan Revisi:</label>
                        <textarea name="revision_notes" class="form-control" rows="3" placeholder="Tuliskan bagian yang perlu dilengkapi atau diperbaiki oleh supervisor..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning text-dark w-100 fw-bold">
                        <i class="ti ti-rotate-2 me-1"></i> Kembalikan ke Supervisor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
