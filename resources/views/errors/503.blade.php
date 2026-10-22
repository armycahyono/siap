@extends('errors.layout')

@section('title', 'Pemeliharaan Sistem')

@section('content')
    <div class="error-icon-box bg-azure-lt text-azure">
        <i class="ti ti-tools"></i>
    </div>

    <div>
        <span class="error-code-badge bg-azure-lt text-azure">
            <i class="ti ti-tool me-1"></i> HTTP 503 &bull; SERVICE UNAVAILABLE
        </span>
    </div>

    <h1 class="error-title">Sistem Sedang Dipelihara</h1>

    <p class="error-desc">
        {{ $exception?->getMessage() ?: 'Layanan SIAP saat ini sedang dalam proses pemeliharaan rutin, pencadangan data, atau peningkatan sistem oleh Tim IT Sekolah.' }}
    </p>

    <div class="info-box">
        <div class="d-flex align-items-center mb-1">
            <i class="ti ti-info-circle text-primary me-2 fs-3"></i>
            <strong class="text-dark">Informasi:</strong>
        </div>
        <ul class="mb-0 ps-3 text-muted">
            <li>Pemeliharaan biasanya berlangsung beberapa saat. Silakan tunggu sebentar.</li>
            <li>Semua data presensi dan aktivitas Anda tersimpan dengan aman di server lokal sekolah.</li>
            <li>Tekan tombol di bawah untuk memeriksa apakah layanan sudah kembali online.</li>
        </ul>
    </div>

    <div class="btn-action-group">
        <button type="button" class="btn btn-primary" onclick="window.location.reload();">
            <i class="ti ti-refresh me-1"></i> Periksa / Muat Ulang Sekarang
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="goBackOrHome()">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </button>
    </div>
@endsection
