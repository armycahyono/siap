@extends('layouts.app')

@section('title', 'Perbarui Laporan Visitasi: ' . $visitation->student->name)

@section('content')
<div class="container-xl">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <a href="{{ route('visitations.show', $visitation) }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Detail Visitasi
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3">
            <h3 class="card-title text-dark fw-bold mb-0">
                <i class="ti ti-edit me-2 text-teal"></i> Form Pelaksanaan &amp; Laporan Hasil Visitasi Siswa
            </h3>
        </div>
        <div class="card-body p-4">
            <div class="mb-4 p-3 bg-light rounded border">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold fs-3 text-dark">{{ $visitation->student->name }}</div>
                        <div class="text-muted small">
                            {{ $visitation->visit_type_label }} &bull; Rencana: {{ $visitation->planned_date?->format('d/m/Y') }} &bull; Petugas: {{ $visitation->primaryOfficer?->name }}
                        </div>
                    </div>
                    <span class="badge {{ $visitation->status_badge_class }}">{{ $visitation->status_label }}</span>
                </div>
            </div>

            <form action="{{ route('visitations.update', $visitation) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Tanggal Aktual Pelaksanaan -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark required">Tanggal Pelaksanaan Aktual:</label>
                        <input type="date" name="executed_date" class="form-control @error('executed_date') is-invalid @enderror" value="{{ old('executed_date', $visitation->executed_date?->format('Y-m-d') ?? date('Y-m-d')) }}">
                        @error('executed_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Status Perkembangan -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark required">Status Kegiatan:</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="dijadwalkan" {{ old('status', $visitation->status) === 'dijadwalkan' ? 'selected' : '' }}>Dijadwalkan</option>
                            <option value="terlaksana" {{ old('status', $visitation->status) === 'terlaksana' ? 'selected' : '' }}>Terlaksana (Menunggu RTL / Monitoring)</option>
                            <option value="selesai" {{ old('status', $visitation->status) === 'selesai' ? 'selected' : '' }}>Selesai &amp; Ditutup</option>
                            <option value="dibatalkan" {{ old('status', $visitation->status) === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Ringkasan Hasil Pelaksanaan -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Ringkasan Hasil Pelaksanaan &amp; Temuan:</label>
                        <textarea name="result_summary" rows="4" class="form-control @error('result_summary') is-invalid @enderror" placeholder="Tuliskan temuan kunjungan rumah, kondisi lingkungan belajar siswa, tanggapan orang tua, klarifikasi masalah kehadiran, atau kesepakatan yang dicapai...">{{ old('result_summary', $visitation->result_summary) }}</textarea>
                        @error('result_summary')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Rencana Tindak Lanjut -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Rencana Tindak Lanjut (RTL):</label>
                        <textarea name="follow_up_plan" rows="3" class="form-control @error('follow_up_plan') is-invalid @enderror" placeholder="Rencana pendampingan di sekolah, pemantauan presensi mingguan, rujukan ke BK, atau jadwal evaluasi lanjutan...">{{ old('follow_up_plan', $visitation->follow_up_plan) }}</textarea>
                        @error('follow_up_plan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Dokumen Berita Acara / Foto Kegiatan -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Unggah / Ganti Berita Acara &amp; Dokumentasi (Privat):</label>
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                        @if($visitation->attachment_path)
                            <div class="small text-success mt-1">
                                <i class="ti ti-check me-1"></i>Dokumen berita acara sudah ada. Unggah file baru hanya jika ingin menggantinya.
                            </div>
                        @endif
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Catatan Konfidensial (Jika Berwenang) -->
                    @if($canAccessConfidential)
                        <div class="col-12">
                            <div class="p-3 bg-red-lt rounded border border-danger-subtle">
                                <label class="form-label fw-bold text-danger mb-1">
                                    <i class="ti ti-lock me-1"></i> Catatan Sensitif / Konfidensial Keluarga:
                                </label>
                                <textarea name="confidential_notes" rows="3" class="form-control border-danger-subtle" placeholder="Catatan pribadi keluarga yang tidak boleh dibagikan umum...">{{ old('confidential_notes', $visitation->confidential_notes) }}</textarea>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('visitations.show', $visitation) }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-teal text-white">
                        <i class="ti ti-check me-1"></i> Simpan Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
