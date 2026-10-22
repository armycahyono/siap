@extends('layouts.app')

@section('title', 'Berkas Supervisi: ' . $supervision->teacher->name)

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-file-certificate me-2"></i> Berkas Supervisi Pembelajaran
        </h2>
        <div class="text-muted small">
            Nomor Laporan: <strong>{{ $supervision->report_number ?: 'Akan digenerate saat difinalisasi' }}</strong> | Tahun Pelajaran: {{ $supervision->academic_year }} ({{ $supervision->semester }})
        </div>
    </div>
    <div class="col-auto d-flex gap-2">
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Jadwal
        </a>
        @if(in_array($supervision->status, ['disetujui', 'selesai', 'menunggu_persetujuan']))
        <a href="{{ route('reports.print', $supervision) }}" target="_blank" class="btn btn-primary">
            <i class="ti ti-printer me-1"></i> Cetak Dokumen (A4)
        </a>
        <a href="{{ route('reports.pdf', $supervision) }}" class="btn btn-danger">
            <i class="ti ti-file-type-pdf me-1"></i> Download PDF
        </a>
        @endif
    </div>
</div>

<!-- Workflow Stepper Indicator -->
@php
    $workflowSteps = [
        [
            'num' => 1,
            'title' => 'Modul Ajar / RPP',
            'is_done' => !empty($supervision->rpp_file_path),
            'done_label' => 'Sudah Diunggah',
            'pending_label' => 'Menunggu Unggah',
        ],
        [
            'num' => 2,
            'title' => 'Praobservasi',
            'is_done' => !empty($supervision->pre_completed_at),
            'done_label' => 'Selesai',
            'pending_label' => 'Belum Diisi',
        ],
        [
            'num' => 3,
            'title' => 'Observasi Kelas',
            'is_done' => !empty($supervision->obs_completed_at),
            'done_label' => $supervision->percentage . '% (' . ($supervision->predicate ?: 'Selesai') . ')',
            'pending_label' => 'Belum Dinilai',
        ],
        [
            'num' => 4,
            'title' => 'Pascaobservasi',
            'is_done' => !empty($supervision->post_completed_at),
            'done_label' => 'Catatan Tersimpan',
            'pending_label' => 'Belum Diisi',
        ],
        [
            'num' => 5,
            'title' => 'Dokumentasi',
            'is_done' => ($supervision->photos->count() > 0),
            'done_label' => $supervision->photos->count() . ' Foto Lampiran',
            'pending_label' => '0 Foto Lampiran',
        ],
        [
            'num' => 6,
            'title' => 'Pengesahan Kepsek',
            'is_done' => (bool) $supervision->approved_by_principal,
            'done_label' => 'Resmi Disahkan',
            'pending_label' => ($supervision->status === 'menunggu_persetujuan' ? 'Menunggu KS' : 'Belum Diajukan'),
        ],
    ];

    $firstUndoneIdx = null;
    foreach ($workflowSteps as $idx => $st) {
        if (!$st['is_done']) {
            $firstUndoneIdx = $idx;
            break;
        }
    }
@endphp

@if(!$supervision->approved_by_principal && !empty($supervision->principal_notes))
<div class="alert alert-warning border-start border-warning border-4 shadow-sm mb-4" role="alert">
    <div class="d-flex align-items-start">
        <div class="me-3 mt-1 text-warning">
            <i class="ti ti-alert-triangle fs-1"></i>
        </div>
        <div class="flex-grow-1">
            <h4 class="alert-title fw-bold text-warning mb-1">
                Catatan Revisi / Arahan dari Kepala Sekolah
            </h4>
            <div class="text-secondary bg-white p-3 rounded border border-warning-subtle mt-2">
                {!! nl2br(e($supervision->principal_notes)) !!}
            </div>
            <div class="text-muted small mt-2">
                <i class="ti ti-info-circle me-1"></i> Berkas supervisi ini telah dikembalikan oleh Kepala Sekolah. Silakan sesuaikan instrumen atau berkas supervisi sesuai arahan di atas, lalu finalisasi kembali.
            </div>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="text-uppercase text-muted fw-bold small">
            <i class="ti ti-route me-1 text-primary"></i> Linimasa Alur Kegiatan Supervisi
        </span>
        <span class="badge bg-{{ $supervision->status_color }} text-white">
            Status: {{ $supervision->status_label }}
        </span>
    </div>
    <div class="card-body pt-2 pb-3 px-3">
        <div class="workflow-stepper">
            @foreach($workflowSteps as $idx => $step)
                @php
                    if ($step['is_done']) {
                        $stateClass = 'step-completed';
                    } elseif ($idx === $firstUndoneIdx) {
                        $stateClass = 'step-current';
                    } else {
                        $stateClass = 'step-pending';
                    }
                @endphp
                <div class="workflow-step {{ $stateClass }}">
                    <div class="workflow-marker">
                        @if($step['is_done'])
                            <i class="ti ti-check fs-2"></i>
                        @else
                            {{ $step['num'] }}
                        @endif
                    </div>
                    <div class="workflow-title">{{ $step['title'] }}</div>
                    <div class="workflow-desc">
                        @if($step['is_done'])
                            <span class="text-success fw-semibold"><i class="ti ti-check me-1"></i>{{ $step['done_label'] }}</span>
                        @elseif($idx === $firstUndoneIdx)
                            <span class="badge bg-primary-lt fw-semibold">{{ $step['pending_label'] }}</span>
                        @else
                            <span class="text-muted">{{ $step['pending_label'] }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Identity & Context Card -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-id me-2 text-primary"></i> Identitas Pelaksanaan Supervisi
                </h3>
                <span class="badge bg-{{ $supervision->status_color }} ms-auto text-white">
                    {{ $supervision->status_label }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Nama Guru yang Disupervisi:</div>
                        <div class="h3 fw-bold text-navy mb-0">{{ $supervision->teacher->name }}</div>
                        <div class="text-muted small">
                            @if(!empty($supervision->teacher->nip))
                                NIP: {{ $supervision->teacher->nip }}
                            @else
                                NIGS: {{ $supervision->teacher->nigs_fix ?: $supervision->teacher->nigs ?: '-' }}
                            @endif
                            @if(!empty($supervision->teacher->nuptk))
                                | NUPTK: {{ $supervision->teacher->nuptk }}
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Supervisor Penilai:</div>
                        <div class="h3 fw-bold text-dark mb-0">{{ $supervision->supervisor->name }}</div>
                        <div class="text-muted small">
                            @if(!empty($supervision->supervisor->nip))
                                NIP: {{ $supervision->supervisor->nip }}
                            @else
                                NIGS: {{ $supervision->supervisor->nigs_fix ?: $supervision->supervisor->nigs ?: '-' }}
                            @endif
                            @if(!empty($supervision->supervisor->nuptk))
                                | NUPTK: {{ $supervision->supervisor->nuptk }}
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Mata Pelajaran:</div>
                        <strong class="text-dark">{{ $supervision->subject }}</strong>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Kelas & Keahlian:</div>
                        <strong class="text-dark">{{ $supervision->class_name }}</strong> ({{ $supervision->expertise_program ?: 'Umum' }})
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Waktu Pelaksanaan:</div>
                        <strong class="text-dark">{{ $supervision->schedule_date->translatedFormat('l, d F Y') }}</strong>
                        <div class="text-muted small">{{ $supervision->schedule_time }}</div>
                    </div>
                </div>

                @if($supervision->schedule_notes)
                <div class="alert alert-light border mt-3 mb-0">
                    <i class="ti ti-notes me-1 text-muted"></i> <strong>Catatan Jadwal:</strong> {{ $supervision->schedule_notes }}
                </div>
                @endif
            </div>
        </div>

        <!-- Modul Ajar / RPP Upload Card (Feature for Guru) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-file-upload me-2 text-primary"></i> Dokumen RPP / Modul Ajar (Unggahan Guru)
                </h3>
            </div>
            <div class="card-body">
                @if($supervision->rpp_file_path)
                    <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-file-check fs-1 text-success me-3"></i>
                            <div>
                                <h4 class="alert-title mb-1 fw-bold text-dark">{{ $supervision->rpp_original_name }}</h4>
                                <div class="text-muted small">Diunggah pada: {{ $supervision->rpp_uploaded_at?->translatedFormat('d F Y, H:i') }} WIB</div>
                            </div>
                        </div>
                        <a href="{{ asset('storage/' . $supervision->rpp_file_path) }}" target="_blank" class="btn btn-success">
                            <i class="ti ti-download me-1"></i> Buka / Unduh Modul
                        </a>
                    </div>
                @else
                    <div class="alert alert-warning mb-3">
                        <i class="ti ti-alert-triangle me-1"></i> Guru belum mengunggah berkas RPP / Modul Ajar untuk pelaksanaan supervisi ini.
                    </div>
                @endif

                @php
                    $canUploadRpp = ! in_array($supervision->status, ['disetujui', 'selesai']) && (
                        auth()->id() === $supervision->teacher_id ||
                        auth()->id() === $supervision->supervisor_id ||
                        auth()->user()->hasRole('super_admin') ||
                        auth()->user()->isWakaKurikulum()
                    );
                @endphp

                @if($canUploadRpp)
                <!-- Upload Form -->
                <form action="{{ route('supervisions.upload-rpp', $supervision) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                    @csrf
                    <label class="form-label fw-bold small text-muted">Unggah / Ganti Berkas Modul Ajar (PDF atau Word, maks 15MB):</label>
                    <div class="input-group">
                        <input type="file" name="rpp_file" class="form-control" accept=".pdf,.doc,.docx" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-upload me-1"></i> Upload Modul Ajar
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>

        <!-- Tahapan Supervisi Menu Cards -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-player-play me-2 text-primary"></i> Pelaksanaan & Pengisian Instrumen
                </h3>
            </div>
            <div class="list-group list-group-flush">
                <!-- 1. Praobservasi -->
                <a href="{{ route('supervisions.pre-observation', $supervision) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt text-primary rounded-circle me-3 fw-bold">1</span>
                        <div>
                            <div class="fw-bold text-dark">Tahap 1: Dialog Praobservasi Pembelajaran</div>
                            <div class="text-muted small">Mencatat kesiapan, materi, tujuan, media, metode, dan fokus amatan.</div>
                        </div>
                    </div>
                    <div>
                        @if($supervision->pre_completed_at)
                            <span class="badge bg-success me-2">Selesai</span>
                        @else
                            <span class="badge bg-secondary-lt me-2">Belum Diisi</span>
                        @endif
                        <i class="ti ti-chevron-right text-muted"></i>
                    </div>
                </a>

                <!-- 2. Observasi -->
                <a href="{{ route('supervisions.observation', $supervision) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt text-primary rounded-circle me-3 fw-bold">2</span>
                        <div>
                            <div class="fw-bold text-dark">Tahap 2: Instrumen Observasi Kelas (23 Butir Penilaian)</div>
                            <div class="text-muted small">Penilaian skala 1-4 (Pendahuluan, Inti, Penutup) dengan kalkulasi skor otomatis.</div>
                        </div>
                    </div>
                    <div>
                        @if($supervision->obs_completed_at)
                            <span class="badge bg-success me-2">{{ $supervision->percentage }}%</span>
                        @else
                            <span class="badge bg-secondary-lt me-2">Belum Dinilai</span>
                        @endif
                        <i class="ti ti-chevron-right text-muted"></i>
                    </div>
                </a>

                <!-- 3. Pascaobservasi -->
                <a href="{{ route('supervisions.post-observation', $supervision) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt text-primary rounded-circle me-3 fw-bold">3</span>
                        <div>
                            <div class="fw-bold text-dark">Tahap 3: Catatan & Rekomendasi Pascaobservasi</div>
                            <div class="text-muted small">Catatan positif, aspek perbaikan, rekomendasi pembinaan, dan tindak lanjut.</div>
                        </div>
                    </div>
                    <div>
                        @if($supervision->post_completed_at)
                            <span class="badge bg-success me-2">Tersimpan</span>
                        @else
                            <span class="badge bg-secondary-lt me-2">Belum Diisi</span>
                        @endif
                        <i class="ti ti-chevron-right text-muted"></i>
                    </div>
                </a>

                <!-- 4. Dokumentasi Foto -->
                <a href="{{ route('supervisions.documentation', $supervision) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt text-primary rounded-circle me-3 fw-bold">4</span>
                        <div>
                            <div class="fw-bold text-dark">Tahap 4: Dokumentasi Foto Kegiatan Supervisi</div>
                            <div class="text-muted small">Unggah foto KBM, interaksi siswa, dan media pembelajaran untuk lampiran cetak.</div>
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-blue-lt me-2">{{ $supervision->photos->count() }} Foto</span>
                        <i class="ti ti-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Right Summary & Actions Sidebar -->
    <div class="col-lg-4">
        <!-- Score Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center p-4">
                <span class="badge bg-blue-lt text-uppercase px-3 py-1 mb-2">Ringkasan Hasil Observasi</span>
                <div class="display-3 fw-bold text-navy mb-1" style="color: #102d52;">
                    {{ $supervision->percentage }}%
                </div>
                <div class="h2 mb-3">
                    <span class="badge bg-{{ $supervision->predicate ? match($supervision->predicate) { 'Sangat Baik' => 'success', 'Baik' => 'primary', 'Cukup' => 'warning', default => 'danger' } : 'secondary' }} px-3 py-1">
                        {{ $supervision->predicate ?: 'Belum Ada Predikat' }}
                    </span>
                </div>
                <div class="row border-top pt-3 text-muted">
                    <div class="col-6 border-end">
                        <div class="small">Total Skor</div>
                        <strong class="h3 mb-0 text-dark">{{ $supervision->total_score }}</strong>
                    </div>
                    <div class="col-6">
                        <div class="small">Skor Maksimal</div>
                        <strong class="h3 mb-0 text-dark">{{ $supervision->max_score }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval / Digital Signature Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="card-title fw-bold text-dark mb-0">
                    <i class="ti ti-certificate me-2 text-warning"></i> Pengesahan & Tanda Tangan
                </h3>
            </div>
            <div class="card-body">
                <!-- Supervisor Signature Status -->
                <div class="d-flex align-items-center mb-3">
                    <i class="ti ti-user-check fs-2 text-primary me-2"></i>
                    <div>
                        <div class="fw-bold text-dark">Supervisor: {{ $supervision->supervisor->name }}</div>
                        <small class="text-muted">
                            {{ $supervision->supervisor_signed_at ? 'Ditandatangani pada ' . $supervision->supervisor_signed_at->translatedFormat('d M Y, H:i') : 'Menunggu finalisasi supervisor' }}
                        </small>
                    </div>
                </div>

                <!-- Principal Approval Status -->
                <div class="d-flex align-items-center mb-4">
                    <i class="ti ti-award fs-2 text-warning me-2"></i>
                    <div>
                        <div class="fw-bold text-dark">Kepala Sekolah: {{ $setting->principal_name }}</div>
                        <small class="text-muted">
                            @if($supervision->approved_by_principal)
                                <span class="text-success fw-bold"><i class="ti ti-check"></i> Resmi Disetujui & Disahkan</span>
                                ({{ $supervision->principal_approved_at?->translatedFormat('d M Y') }})
                                @if($supervision->principal_notes)
                                    <div class="small text-muted fst-italic mt-1">Catatan KS: "{{ $supervision->principal_notes }}"</div>
                                @endif
                            @else
                                <span class="text-warning">Belum disahkan oleh Kepala Sekolah</span>
                            @endif
                        </small>
                    </div>
                </div>

                @if($supervision->qr_token)
                <div class="p-3 bg-light rounded-3 text-center border mb-3">
                    <div class="small text-muted mb-2 fw-semibold">QR Code Verifikasi Dokumen:</div>
                    <div class="d-inline-block bg-white p-2 border rounded shadow-sm">
                        <img src="{{ (new \chillerlan\QRCode\QRCode())->render(route('verify.supervision', $supervision->qr_token)) }}" alt="QR Code Verifikasi Dokumen" style="width: 130px; height: 130px;" class="img-fluid d-block mx-auto">
                    </div>
                    <div class="small text-muted mt-2">
                        <a href="{{ route('verify.supervision', $supervision->qr_token) }}" target="_blank" class="text-decoration-none">
                            <i class="ti ti-external-link me-1"></i> Buka Halaman Verifikasi Publik
                        </a>
                    </div>
                </div>
                @endif

                <!-- Supervisor Finalization / Approval Action Button -->
                @if(auth()->user()->id === $supervision->supervisor_id || auth()->user()->hasPermission('approve_supervision') || auth()->user()->hasRole('super_admin') || auth()->user()->isWakaKurikulum() || auth()->user()->canEditSupervision($supervision))
                    @if(!in_array($supervision->status, ['menunggu_persetujuan', 'disetujui', 'selesai']) && $supervision->obs_completed_at && (auth()->user()->id === $supervision->supervisor_id || auth()->user()->hasRole('super_admin') || auth()->user()->isWakaKurikulum() || auth()->user()->canEditSupervision($supervision)))
                    <form action="{{ route('supervisions.finalize', $supervision) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memfinalisasi supervisi ini dan mengajukannya untuk disahkan?')">
                        @csrf
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                            <i class="ti ti-send me-1"></i> Finalisasi & Ajukan ke Kepsek
                        </button>
                    </form>
                    @elseif($supervision->status === 'menunggu_persetujuan')
                    <div class="alert alert-info py-2 px-3 small mb-0 text-center">
                        <i class="ti ti-clock me-1"></i> Telah diajukan & sedang menunggu persetujuan KS / Waka Kurikulum.
                    </div>
                    @if(auth()->user()->hasPermission('approve_supervision'))
                    <div class="mt-2">
                        <a href="{{ route('approvals.show', $supervision) }}" class="btn btn-primary w-100 fw-bold">
                            <i class="ti ti-certificate me-1"></i> Buka Lembar Persetujuan KS
                        </a>
                    </div>
                    @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
