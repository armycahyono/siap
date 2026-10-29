<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>Masuk — SIAP | SMK PGRI KRAS</title>
    
    <!-- PWA & Mobile Meta Tags -->
    <meta name="theme-color" content="#195b9e"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
    <meta name="apple-mobile-web-app-title" content="SIAP"/>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
    
    <!-- Tabler CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Source Sans 3', sans-serif;
            background-color: #f1f5f9;
        }
        .hero-banner {
            background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-center min-vh-100 py-4">
    <div class="container container-normal py-4" style="max-width: 950px;">
        <div class="card card-stacked shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="row g-0">
                <!-- Left Hero Section -->
                <div class="col-lg-5 hero-banner text-white p-5 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            @if($setting->logo_path && file_exists(public_path('storage/' . $setting->logo_path)))
                                <img src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo" class="me-3" style="max-height: 48px; max-width: 60px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));">
                            @else
                                <img src="{{ asset('icons/icon-192x192.png') }}" alt="Logo" class="me-3" style="max-height: 48px; max-width: 60px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));">
                            @endif
                            <div>
                                <h3 class="mb-0 text-white fw-bold">SMK PGRI KRAS</h3>
                                <small class="text-white-50">Tahun Pelajaran {{ $setting->academic_year }}</small>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold mb-3 text-white">SIAP</h2>
                        <p class="text-blue-100" style="color: #dbeafe;">
                            Sistem Informasi Administrasi dan Pendidikan &mdash; Layanan terpadu manajemen administrasi, supervisi, dan operasional pendidikan sekolah.
                        </p>
                    </div>
                </div>

                <!-- Right Login Form -->
                <div class="col-lg-7 p-4 p-md-5 bg-white d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <h2 class="h3 fw-bold text-dark mb-1">Masuk ke Akun Anda</h2>
                        <p class="text-muted small">Silakan masukkan username dan password untuk melanjutkan.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="ti ti-alert-circle me-1"></i> {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('login.post') }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username atau Email</label>
                            <div class="input-group input-group-flat">
                                <span class="input-group-text"><i class="ti ti-user text-muted"></i></span>
                                <input type="text" name="login" id="loginField" class="form-control" placeholder="Contoh: waka / supervisor / guru1" value="{{ old('login') }}" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Password</label>
                            </div>
                            <div class="input-group input-group-flat">
                                <span class="input-group-text"><i class="ti ti-lock text-muted"></i></span>
                                <input type="password" name="password" id="passwordField" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                                <span class="input-group-text">
                                    <button type="button" class="btn btn-link link-secondary p-0 text-decoration-none" id="togglePasswordBtn" title="Lihat password" aria-label="Lihat password">
                                        <i class="ti ti-eye fs-2" id="togglePasswordIcon"></i>
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="mb-4 d-flex justify-content-between">
                            <label class="form-check mb-0">
                                <input type="checkbox" name="remember" class="form-check-input"/>
                                <span class="form-check-label text-muted small">Ingat saya di perangkat ini</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" style="background-color: #195b9e;">
                            <i class="ti ti-login me-1"></i> Masuk Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePasswordBtn')?.addEventListener('click', function () {
            const passwordField = document.getElementById('passwordField');
            const icon = document.getElementById('togglePasswordIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('ti-eye');
                icon.classList.add('ti-eye-off');
                this.setAttribute('title', 'Sembunyikan password');
                this.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('ti-eye-off');
                icon.classList.add('ti-eye');
                this.setAttribute('title', 'Lihat password');
                this.setAttribute('aria-label', 'Lihat password');
            }
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function (err) {
                    console.warn('SIAP PWA: Service Worker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>
