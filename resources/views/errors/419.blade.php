@extends('errors.layout')

@section('title', 'Sesi Berakhir')

@section('content')
    <div class="error-icon-box bg-warning-lt text-warning">
        <i class="ti ti-clock-pause"></i>
    </div>

    <div>
        <span class="error-code-badge bg-warning-lt text-warning">
            <i class="ti ti-clock me-1"></i> HTTP 419 &bull; PAGE EXPIRED
        </span>
    </div>

    <h1 class="error-title">Sesi Halaman Telah Berakhir</h1>

    <p class="error-desc">
        Halaman telah terbuka terlalu lama tanpa aktivitas sehingga token keamanan (CSRF) kedaluwarsa demi keamanan akun Anda.
    </p>

    <div class="info-box">
        <div class="d-flex align-items-center mb-1">
            <i class="ti ti-info-circle text-primary me-2 fs-3"></i>
            <strong class="text-dark">Petunjuk &amp; Bantuan:</strong>
        </div>
        <ul class="mb-0 ps-3 text-muted">
            <li>Silakan muat ulang halaman untuk memperbarui token keamanan lalu coba kirim kembali data Anda.</li>
            <li>Jika Anda otomatis keluar dari sistem, silakan lakukan login ulang.</li>
            <li>Sebutkan kode <strong>HTTP 419</strong> jika kendala berulang saat menyimpan formulir.</li>
        </ul>
    </div>

    <div class="btn-action-group">
        <button type="button" class="btn btn-primary" onclick="window.location.reload();">
            <i class="ti ti-refresh me-1"></i> Muat Ulang Halaman
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="goBackOrHome()">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Halaman Sebelumnya
        </button>
    </div>
@endsection
