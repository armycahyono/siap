@extends('errors.layout')

@section('title', 'Akses Ditolak')

@section('content')
    <div class="error-icon-box bg-danger-lt text-danger">
        <i class="ti ti-shield-lock"></i>
    </div>

    <div>
        <span class="error-code-badge bg-danger-lt text-danger">
            <i class="ti ti-alert-triangle me-1"></i> HTTP 403 &bull; FORBIDDEN
        </span>
    </div>

    <h1 class="error-title">Akses Tidak Diizinkan</h1>

    <p class="error-desc">
        {{ $exception?->getMessage() ?: 'Maaf, akun Anda tidak memiliki hak akses atau wewenang untuk membuka halaman/fitur ini.' }}
    </p>

    <div class="info-box">
        <div class="d-flex align-items-center mb-1">
            <i class="ti ti-info-circle text-primary me-2 fs-3"></i>
            <strong class="text-dark">Petunjuk &amp; Bantuan:</strong>
        </div>
        <ul class="mb-0 ps-3 text-muted">
            <li>Pastikan Anda masuk (login) dengan akun yang memiliki peran/otoritas sesuai.</li>
            <li>Jika Anda bertugas sebagai guru piket atau waka, pastikan jadwal dan penugasan Anda sudah aktif.</li>
            <li>Jika merasa ini kesalahan sistem, silakan hubungi <strong>Administrator / Tim IT Sekolah</strong> dengan menyebutkan kode <strong>HTTP 403</strong>.</li>
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
