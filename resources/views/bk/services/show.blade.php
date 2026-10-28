@extends('layouts.app')

@section('title', 'Detail Sesi Layanan BK')

@section('content')
<div class="container-xl">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="{{ $service->incident ? route('bk.referrals.show', $service->incident) : route('bk.services.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
        <div class="btn-group">
            <a href="{{ route('bk.services.edit', $service) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-edit me-1"></i> Edit Layanan
            </a>
            <form action="{{ route('bk.services.destroy', $service) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sesi layanan BK ini?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger ms-1">
                    <i class="ti ti-trash me-1"></i> Hapus
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-8">
            <!-- Kartu Utama Layanan -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge {{ $service->service_type_badge_class }} mb-1">{{ $service->service_type_label }}</span>
                        <h3 class="card-title fw-bold text-dark mb-0">Sesi Layanan Bimbingan &amp; Konseling</h3>
                    </div>
                    <div class="text-muted small">
                        TP. {{ $service->academic_year }} &bull; {{ $service->service_date->format('d F Y') }}
                    </div>
                </div>
                <div class="card-body">
                    <!-- Ringkasan Masalah -->
                    <div class="mb-4">
                        <label class="form-label text-muted small text-uppercase fw-bold">Ringkasan Masalah / Keluhan Siswa</label>
                        <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-line;">{{ $service->summary }}</div>
                    </div>

                    <!-- Hasil / Kesimpulan -->
                    <div class="mb-4">
                        <label class="form-label text-muted small text-uppercase fw-bold">Hasil Konseling &amp; Kesepakatan</label>
                        <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-line;">{{ $service->result }}</div>
                    </div>

                    <!-- Rencana Tindak Lanjut (RTL) & Monitoring -->
                    <div class="card bg-azure-lt border-azure mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-checklist fs-2 text-primary me-2"></i>
                                    <div>
                                        <h4 class="card-title text-primary mb-0 fw-bold">Rencana Tindak Lanjut (RTL) &amp; Monitoring</h4>
                                        <span class="text-muted small">Pemantauan progres dan hasil tindak lanjut layanan konseling</span>
                                    </div>
                                </div>
                                <span class="badge {{ $service->follow_up_status_badge_class }}">
                                    {{ $service->follow_up_status_label }}
                                </span>
                            </div>

                            @if($service->follow_up_plan)
                                <div class="p-3 bg-white rounded border border-azure-subtle text-dark mb-3" style="white-space: pre-line;">{{ $service->follow_up_plan }}</div>
                            @else
                                <div class="text-muted small italic mb-3">Belum ada rencana tindak lanjut yang ditentukan.</div>
                            @endif

                            <div class="row g-2 mb-3 small">
                                <div class="col-sm-6">
                                    <div class="text-muted">Target Pelaksanaan:</div>
                                    <div class="fw-semibold text-dark">
                                        <i class="ti ti-calendar me-1"></i>{{ $service->follow_up_target_date ? $service->follow_up_target_date->format('d F Y') : '-' }}
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted">Penanggung Jawab (PIC):</div>
                                    <div class="fw-semibold text-dark">
                                        <i class="ti ti-user-check me-1"></i>{{ $service->followUpAssignee?->name ?? ($service->counselor?->name ?? 'Konselor Pelaksana') }}
                                    </div>
                                </div>
                            </div>

                            @if($service->follow_up_evaluation_notes)
                                <div class="p-3 bg-white rounded border border-success-subtle mb-3">
                                    <label class="form-label text-success small fw-bold mb-1">
                                        <i class="ti ti-notes me-1"></i> Catatan Evaluasi &amp; Hasil Monitoring:
                                    </label>
                                    <div class="text-dark small" style="white-space: pre-line;">{{ $service->follow_up_evaluation_notes }}</div>
                                    @if($service->follow_up_evaluated_at)
                                        <div class="text-muted mt-2" style="font-size: 0.72rem;">
                                            Dievaluasi pada: {{ $service->follow_up_evaluated_at->format('d/m/Y H:i') }} WIB
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-update-rtl">
                                    <i class="ti ti-edit me-1"></i> Perbarui RTL &amp; Evaluasi
                                </button>
                                <a href="{{ route('visitations.create', ['student_id' => $service->student_id, 'bk_service_id' => $service->id, 'type' => 'kunjungan_rumah']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-home-check me-1"></i> Eskalasi ke Kunjungan Rumah (Visitasi)
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Konseling Rahasia (Confidential) -->
                    @if($canAccessConfidential)
                        <div class="card bg-danger-lt border-danger mt-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ti ti-lock fs-2 text-danger me-2"></i>
                                    <h4 class="card-title text-danger mb-0 fw-bold">Catatan Konseling Rahasia (Confidential)</h4>
                                </div>
                                <p class="text-danger-emphasis small mb-3">
                                    Data ini terenkripsi dan terlindungi di server; hanya Guru BK yang berwenang dan Super Admin yang dapat membacanya.
                                </p>
                                @if($service->confidentialNote && !empty($service->confidentialNote->notes))
                                    <div class="p-3 bg-white rounded border border-danger-subtle text-dark" style="white-space: pre-line;">{{ $service->confidentialNote->notes }}</div>
                                    <div class="text-muted small mt-2" style="font-size: 0.72rem;">
                                        Dicatat oleh: {{ $service->confidentialNote->author?->name ?? 'Guru BK' }} pada {{ $service->confidentialNote->created_at->format('d/m/Y H:i') }} WIB
                                    </div>
                                @else
                                    <div class="text-muted small italic">Tidak ada catatan rahasia yang disimpan untuk sesi layanan ini.</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        Konselor Pelaksana: <strong>{{ $service->counselor?->name ?? 'Guru BK' }}</strong>
                    </span>
                    <span class="text-muted small">
                        Dicatat pada: {{ $service->created_at->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Profil Siswa & Hubungan Rujukan -->
        <div class="col-lg-4">
            <!-- Profil Singkat Siswa -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-primary-lt">
                    <h3 class="card-title fw-bold text-primary mb-0">
                        <i class="ti ti-user me-2"></i> Profil Siswa
                    </h3>
                </div>
                <div class="card-body">
                    <h3 class="mb-1 text-dark">{{ $service->student->name }}</h3>
                    @php
                        $studentClass = $service->student->classroomForAcademicYear($service->academic_year) ?? $service->student->classroom;
                    @endphp
                    <div class="text-muted small mb-3">
                        {{ $studentClass?->name ?? 'Kelas tidak terdata' }}
                        @if($service->student->nisn)
                            &bull; NISN: {{ $service->student->nisn }}
                        @endif
                    </div>
                    <a href="{{ route('bk.students.history', ['student' => $service->student, 'academic_year' => $service->academic_year]) }}" class="btn btn-sm btn-outline-primary w-100">
                        <i class="ti ti-history me-1"></i> Buka Riwayat Lengkap BK
                    </a>
                </div>
            </div>

            <!-- Terkait Rujukan Kasus Piket -->
            @if($service->incident)
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger-lt">
                        <h3 class="card-title fw-bold text-danger mb-0">
                            <i class="ti ti-link me-2"></i> Asal Rujukan Piket
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-1">Tanggal: {{ $service->incident->incident_date->format('d/m/Y') }}</div>
                        <div class="fw-bold mb-2">{{ $service->incident->category_label }}</div>
                        <p class="small text-muted mb-3">{{ Str::limit($service->incident->description, 120) }}</p>
                        <a href="{{ route('bk.referrals.show', $service->incident) }}" class="btn btn-sm btn-outline-danger w-100">
                            <i class="ti ti-arrow-right me-1"></i> Buka Rujukan Kasus
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Perbarui RTL -->
<div class="modal fade" id="modal-update-rtl" tabindex="-1" aria-labelledby="modalUpdateRtlLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('bk.services.rtl', $service) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalUpdateRtlLabel">
                        <i class="ti ti-checklist me-2 text-primary"></i> Perbarui Rencana Tindak Lanjut (RTL)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rencana Tindak Lanjut</label>
                        <textarea name="follow_up_plan" class="form-control" rows="3" placeholder="Uraian tindak lanjut konseling...">{{ old('follow_up_plan', $service->follow_up_plan) }}</textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status Pelaksanaan RTL <span class="text-danger">*</span></label>
                            <select name="follow_up_status" class="form-select" required>
                                <option value="belum_dilaksanakan" {{ old('follow_up_status', $service->follow_up_status) === 'belum_dilaksanakan' ? 'selected' : '' }}>Belum Dilaksanakan</option>
                                <option value="sedang_berjalan" {{ old('follow_up_status', $service->follow_up_status) === 'sedang_berjalan' ? 'selected' : '' }}>Sedang Berjalan</option>
                                <option value="selesai" {{ old('follow_up_status', $service->follow_up_status) === 'selesai' ? 'selected' : '' }}>Selesai</option>
                                <option value="dibatalkan" {{ old('follow_up_status', $service->follow_up_status) === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Target Tanggal</label>
                            <input type="date" name="follow_up_target_date" class="form-control" value="{{ old('follow_up_target_date', $service->follow_up_target_date?->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Penanggung Jawab (PIC)</label>
                        <select name="follow_up_assignee_id" class="form-select">
                            <option value="">-- Tetapkan Konselor Sendiri --</option>
                            @foreach($officers as $officer)
                                <option value="{{ $officer->id }}" {{ old('follow_up_assignee_id', $service->follow_up_assignee_id) == $officer->id ? 'selected' : '' }}>
                                    {{ $officer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Hasil &amp; Evaluasi RTL</label>
                        <textarea name="follow_up_evaluation_notes" class="form-control" rows="3" placeholder="Hasil tindak lanjut, perubahan perilaku siswa, evaluasi perkembangan...">{{ old('follow_up_evaluation_notes', $service->follow_up_evaluation_notes) }}</textarea>
                        <div class="form-hint">Catatan ini mendokumentasikan efektivitas layanan konseling dan evaluasi tindak lanjut.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Pembaruan RTL
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
