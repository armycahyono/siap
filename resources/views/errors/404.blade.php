@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')

@section('content')
    <div class="error-icon-box bg-warning-lt text-warning">
        <i class="ti ti-map-pin-off"></i>
    </div>

    <div>
        <span class="error-code-badge bg-warning-lt text-warning">
            <i class="ti ti-search me-1"></i> HTTP 404 &bull; NOT FOUND
        </span>
    </div>

    <h1 class="error-title">Halaman Tidak Ditemukan</h1>

    <p class="error-desc">
        {{ $exception?->getMessage() ?: 'Halaman atau tautan yang Anda tuju tidak ditemukan, telah dipindahkan, atau alamat URL yang dimasukkan kurang tepat.' }}
    </p>

    <div class="info-box">
        <div class="d-flex align-items-center mb-1">
            <i class="ti ti-info-circle text-primary me-2 fs-3"></i>
            <strong class="text-dark">Petunjuk &amp; Bantuan:</strong>
        </div>
        <ul class="mb-0 ps-3 text-muted">
            <li>Periksa kembali penulisan alamat URL pada browser.</li>
            <li>Kembali ke halaman sebelumnya atau gunakan menu navigasi utama untuk mencari halaman yang diinginkan.</li>
            <li>Laporkan kepada <strong>Tim IT Sekolah</strong> dengan kode <strong>HTTP 404</strong> jika tautan berasal dari menu aplikasi resmi.</li>
        </ul>
    </div>

    <div class="btn-action-group">
        <button type="button" class="btn btn-outline-secondary" onclick="goBackOrHome()">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Halaman Sebelumnya
        </button>
        <a href="{{ url('/') }}" class="btn btn-primary">
            <i class="ti ti-home me-1"></i> Ke Beranda
        </a>
    </div>
@endsection
