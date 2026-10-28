@extends('layouts.app')

@section('title', 'Jadwalkan Visitasi & Pemantauan Siswa')

@section('content')
<div class="container-xl">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <a href="{{ route('visitations.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Visitasi
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3">
            <h3 class="card-title text-dark fw-bold mb-0">
                <i class="ti ti-calendar-plus me-2 text-teal"></i> Form Penjadwalan &amp; Penugasan Visitasi Siswa
            </h3>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('visitations.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="source_type" value="{{ $sourceType ?? 'mandiri' }}">
                <input type="hidden" name="source_id" value="{{ $sourceId }}">

                <div class="row g-3">
                    <!-- Pilih Siswa -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark required">Siswa Sasaran:</label>
                        @if($selectedStudent)
                            <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
                            <div class="p-3 bg-light rounded border">
                                <div class="fw-bold fs-3 text-dark">{{ $selectedStudent->name }}</div>
                                <div class="text-muted small">
                                    NISN: {{ $selectedStudent->nisn ?? '-' }} &bull; Kelas: {{ $selectedStudent->classroom?->name ?? 'Belum ada rombel' }}
                                </div>
                            </div>
                        @else
                            <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                <option value="">-- Cari & Pilih Siswa --</option>
                                @foreach($students as $st)
                                    <option value="{{ $st->id }}" {{ old('student_id') == $st->id ? 'selected' : '' }}>
                                        {{ $st->name }} (NISN: {{ $st->nisn ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <!-- Jenis Visitasi -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark required">Jenis Kegiatan Visitasi:</label>
                        <select name="visit_type" class="form-select @error('visit_type') is-invalid @enderror" required>
                            <option value="kunjungan_rumah" {{ old('visit_type', $visitType) === 'kunjungan_rumah' ? 'selected' : '' }}>Kunjungan Rumah (Home Visit)</option>
                            <option value="pemantauan_khusus" {{ old('visit_type', $visitType) === 'pemantauan_khusus' ? 'selected' : '' }}>Pemantauan Kondisi / Perkembangan Khusus</option>
                            <option value="tindak_lanjut_absensi" {{ old('visit_type', $visitType) === 'tindak_lanjut_absensi' ? 'selected' : '' }}>Tindak Lanjut Masalah Kehadiran / Presensi</option>
                            <option value="komunikasi_ortu" {{ old('visit_type', $visitType) === 'komunikasi_ortu' ? 'selected' : '' }}>Kunjungan / Komunikasi Orang Tua</option>
                            <option value="tindak_lanjut_kasus" {{ old('visit_type', $visitType) === 'tindak_lanjut_kasus' ? 'selected' : '' }}>Tindak Lanjut Kasus / Kejadian Pelanggaran</option>
                            <option value="lainnya" {{ old('visit_type', $visitType) === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                        @error('visit_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Petugas Utama -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark required">Petugas Pelaksana Utama:</label>
                        <select name="primary_officer_id" class="form-select @error('primary_officer_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Petugas Utama --</option>
                            @foreach($officers as $officer)
                                <option value="{{ $officer->id }}" {{ old('primary_officer_id', auth()->id()) == $officer->id ? 'selected' : '' }}>
                                    {{ $officer->name }} ({{ $officer->position ?? 'Guru/Staf' }})
                                </option>
                            @endforeach
                        </select>
                        @error('primary_officer_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Guru BK, Wali Kelas, atau Tim Kesiswaan yang bertindak sebagai koordinator lapangan.</small>
                    </div>

                    <!-- Petugas Pendamping -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Petugas Pendamping (Opsional):</label>
                        <select name="secondary_officer_id" class="form-select @error('secondary_officer_id') is-invalid @enderror">
                            <option value="">-- Tidak Ada Petugas Pendamping --</option>
                            @foreach($officers as $officer)
                                <option value="{{ $officer->id }}" {{ old('secondary_officer_id') == $officer->id ? 'selected' : '' }}>
                                    {{ $officer->name }} ({{ $officer->position ?? 'Guru/Staf' }})
                                </option>
                            @endforeach
                        </select>
                        @error('secondary_officer_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Tanggal Rencana -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark required">Tanggal Rencana Pelaksanaan:</label>
                        <input type="date" name="planned_date" class="form-control @error('planned_date') is-invalid @enderror" value="{{ old('planned_date', date('Y-m-d')) }}" required>
                        @error('planned_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Status Awal -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark required">Status Penugasan:</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="dijadwalkan" {{ old('status', 'dijadwalkan') === 'dijadwalkan' ? 'selected' : '' }}>Dijadwalkan</option>
                            <option value="diajukan" {{ old('status') === 'diajukan' ? 'selected' : '' }}>Diajukan (Menunggu Persetujuan)</option>
                        </select>
                    </div>

                    <!-- Lampiran Surat Tugas / Dokumen Awal (Private Storage) -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Surat Tugas / Dokumen Pendukung:</label>
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">PDF atau Gambar (Maks 10MB). Tersimpan privat.</small>
                    </div>

                    <!-- Alasan / Latar Belakang Kebutuhan Visitasi -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark required">Alasan / Latar Belakang &amp; Tujuan Visitasi:</label>
                        <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" placeholder="Jelaskan alasan visitasi, kondisi siswa, indikasi masalah kehadiran / belajar / perilaku yang memerlukan kunjungan..." required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Catatan Konfidensial (Hanya untuk yang Berwenang) -->
                    <div class="col-12">
                        <div class="p-3 bg-red-lt rounded border border-danger-subtle">
                            <label class="form-label fw-bold text-danger mb-1">
                                <i class="ti ti-lock me-1"></i> Catatan Sensitif / Konfidensial Keluarga (Privat):
                            </label>
                            <textarea name="confidential_notes" rows="2" class="form-control border-danger-subtle" placeholder="Catatan sensitif mengenai latar belakang keluarga, konflik rumah tangga, riwayat kesehatan mental yang HANYA boleh diakses oleh petugas pelaksana dan Guru BK...">{{ old('confidential_notes') }}</textarea>
                            <small class="text-muted d-block mt-1">
                                Data ini dilindungi secara ketat di sisi server dan tidak akan tampil di profil umum siswa.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('visitations.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-teal text-white">
                        <i class="ti ti-check me-1"></i> Simpan &amp; Jadwalkan Visitasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
