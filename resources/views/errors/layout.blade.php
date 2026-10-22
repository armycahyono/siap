<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>@yield('title') — SIAP</title>
    
    <meta name="theme-color" content="#195b9e"/>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --tblr-font-sans-serif: 'Source Sans 3', -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, sans-serif;
            --brand-navy: #102d52;
            --brand-blue: #195b9e;
        }
        body {
            font-family: var(--tblr-font-sans-serif);
            background-color: #f6f8fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }
        .error-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(16, 45, 82, 0.08), 0 8px 10px -6px rgba(16, 45, 82, 0.04);
            border: 1px solid #e2e8f0;
            max-width: 520px;
            width: 100%;
            padding: 2.25rem 2rem;
            text-align: center;
        }
        .error-icon-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 2.5rem;
        }
        .error-code-badge {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--brand-navy);
            margin-bottom: 0.75rem;
        }
        .error-desc {
            font-size: 0.95rem;
            line-height: 1.55;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.75rem;
            text-align: left;
            font-size: 0.85rem;
            color: #475569;
        }
        .btn-action-group {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        @media (min-width: 480px) {
            .btn-action-group {
                flex-direction: row;
            }
            .btn-action-group .btn {
                flex: 1;
            }
        }
        .brand-footer {
            margin-top: 1.75rem;
            font-size: 0.775rem;
            color: #94a3b8;
            font-weight: 500;
        }
    </style>
    @yield('styles')
</head>
<body>
    <div class="error-card">
        @yield('content')

        <div class="brand-footer">
            SIAP &bull; Sistem Informasi Administrasi &amp; Pendidikan
        </div>
    </div>

    <script>
        function goBackOrHome() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = "{{ url('/') }}";
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
