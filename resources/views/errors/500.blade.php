@extends('errors.layout')

@section('title', 'Terjadi Kendala Sistem')

@section('content')
    <div class="error-icon-box bg-danger-lt text-danger">
        <i class="ti ti-server-off"></i>
    </div>

    <div>
        <span class="error-code-badge bg-danger-lt text-danger">
            <i class="ti ti-alert-octagon me-1"></i> HTTP 500 &bull; INTERNAL SERVER ERROR
        </span>
    </div>

    <h1 class="error-title">Terjadi Kendala pada Sistem</h1>

    <p class="error-desc">
        Server sekolah sedang mengalami kendala teknis saat memproses permintaan Anda. Data Anda tetap aman.
    </p>

    <div class="info-box">
        <div class="d-flex align-items-center mb-1">
            <i class="ti ti-info-circle text-primary me-2 fs-3"></i>
            <strong class="text-dark">Petunjuk &amp; Bantuan:</strong>
        </div>
        <ul class="mb-0 ps-3 text-muted">
            <li>Coba muat ulang halaman setelah beberapa saat.</li>
            <li>Jika kendala berlanjut, hubungi <strong>Administrator / Tim IT Sekolah</strong> dan informasikan kode <strong>HTTP 500</strong> beserta tindakan terakhir yang dilakukan.</li>
        </ul>
    </div>

    <div class="btn-action-group">
        <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload();">
            <i class="ti ti-refresh me-1"></i> Muat Ulang Halaman
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="goBackOrHome()">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </button>
        <a href="{{ url('/') }}" class="btn btn-primary">
            <i class="ti ti-home me-1"></i> Ke Beranda
        </a>
    </div>
@endsection
