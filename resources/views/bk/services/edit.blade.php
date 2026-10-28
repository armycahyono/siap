@extends('layouts.app')

@section('title', 'Edit Sesi Layanan BK')

@section('content')
<div class="container-xl">
    <div class="mb-3">
        <a href="{{ route('bk.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Detail Sesi
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h4 class="alert-title"><i class="ti ti-alert-circle me-1"></i> Terjadi kesalahan input:</h4>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form action="{{ route('bk.services.update', $service) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary-lt">
                        <h3 class="card-title fw-bold text-primary mb-0">
                            <i class="ti ti-edit me-2"></i> Perbarui Sesi Layanan Bimbingan &amp; Konseling
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Siswa (Readonly) -->
                            <div class="col-md-8">
                                <label class="form-label text-muted small fw-bold">Siswa</label>
                                <div class="form-control bg-light">
                                    <strong>{{ $service->student->name }}</strong>
                                    ({{ $service->student->classroom?->name ?? 'Kelas -' }} &bull; NISN: {{ $service->student->nisn ?? '-' }})
                                </div>
                            </div>

                            <!-- Tahun Ajaran (Readonly) -->
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Tahun Ajaran</label>
                                <input type="text" class="form-control bg-light" value="{{ $service->academic_year }}" readonly>
                            </div>

                            <!-- Tanggal Layanan -->
                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tanggal Layanan</label>
                                <input type="date" name="service_date" class="form-control" value="{{ old('service_date', $service->service_date->format('Y-m-d')) }}" required>
                            </div>

                            <!-- Bidang Layanan -->
                            <div class="col-md-8">
                                <label class="form-label required fw-bold">Bidang Layanan Bimbingan</label>
                                <div class="row g-2">
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="pribadi" class="form-selectgroup-input" {{ old('service_type', $service->service_type) === 'pribadi' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Pribadi</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="sosial" class="form-selectgroup-input" {{ old('service_type', $service->service_type) === 'sosial' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Sosial</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="belajar" class="form-selectgroup-input" {{ old('service_type', $service->service_type) === 'belajar' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Belajar</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="karier" class="form-selectgroup-input" {{ old('service_type', $service->service_type) === 'karier' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Karier</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Ringkasan Layanan -->
                            <div class="col-12">
                                <label class="form-label required fw-bold">Ringkasan Masalah / Uraian Layanan</label>
                                <textarea name="summary" rows="4" class="form-control" required>{{ old('summary', $service->summary) }}</textarea>
                            </div>

                            <!-- Hasil Konseling -->
                            <div class="col-12">
                                <label class="form-label required fw-bold">Hasil / Kesimpulan Konseling</label>
                                <textarea name="result" rows="3" class="form-control" required>{{ old('result', $service->result) }}</textarea>
                            </div>

                            <!-- Rencana Tindak Lanjut -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Rencana Tindak Lanjut (RTL)</label>
                                <textarea name="follow_up_plan" rows="2" class="form-control">{{ old('follow_up_plan', $service->follow_up_plan) }}</textarea>
                            </div>
                        </div>

                        <!-- Catatan Konseling Rahasia (Hanya jika berwenang) -->
                        @if($canAccessConfidential)
                            <div class="mt-4 pt-3 border-top">
                                <div class="card bg-danger-lt border-danger">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ti ti-lock fs-2 text-danger me-2"></i>
                                            <h4 class="card-title text-danger mb-0 fw-bold">Catatan Konseling Rahasia (Confidential)</h4>
                                        </div>
                                        <p class="text-danger-emphasis small mb-3">
                                            Informasi ini strictly confidential, terlindungi di server hanya untuk Guru BK dan Super Admin.
                                        </p>
                                        <textarea name="confidential_notes" rows="3" class="form-control bg-white" placeholder="Tuliskan catatan konseling rahasia...">{{ old('confidential_notes', $service->confidentialNote?->notes) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center bg-light">
                        <a href="{{ route('bk.services.show', $service) }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
