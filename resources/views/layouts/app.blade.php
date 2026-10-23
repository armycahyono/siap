<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>@yield('title', 'SIAP') — {{ \App\Models\SchoolSetting::getSetting()->school_name }} (Sistem Informasi Administrasi dan Pendidikan)</title>
    
    <!-- PWA & Mobile Meta Tags -->
    <meta name="theme-color" content="#195b9e"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
    <meta name="apple-mobile-web-app-title" content="SIAP"/>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --tblr-font-sans-serif: 'Source Sans 3', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
            --brand-navy: #102d52;
            --brand-blue: #195b9e;
            --brand-gold: #c99a3b;
        }
        body {
            font-feature-settings: "cv03", "cv04", "cv11";
            background-color: #f6f8fb;
        }

        /* Mobile Bottom Navigation Bar Styles */
        @media (max-width: 767.98px) {
            body {
                padding-bottom: calc(70px + env(safe-area-inset-bottom, 0px)) !important;
            }
            .mobile-bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: calc(60px + env(safe-area-inset-bottom, 0px));
                padding-bottom: env(safe-area-inset-bottom, 0px);
                background: rgba(255, 255, 255, 0.96);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                z-index: 1040;
                display: flex;
                align-items: center;
                justify-content: space-around;
                box-shadow: 0 -4px 20px rgba(16, 45, 82, 0.08);
                border-top: 1px solid rgba(0, 0, 0, 0.07);
            }
            .mobile-nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-decoration: none !important;
                color: #64748b;
                font-size: 0.72rem;
                font-weight: 600;
                flex: 1;
                height: 100%;
                transition: color 0.15s ease;
                -webkit-tap-highlight-color: transparent;
            }
            .mobile-nav-item i {
                font-size: 1.35rem;
                margin-bottom: 2px;
                line-height: 1;
            }
            .mobile-nav-item.active {
                color: var(--brand-blue);
            }
            .mobile-nav-item.active i {
                transform: scale(1.12);
            }
            .mobile-nav-action {
                position: relative;
            }
            .mobile-nav-action .action-circle {
                width: 46px;
                height: 46px;
                background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(25, 91, 158, 0.35);
                margin-top: -18px;
                margin-bottom: 2px;
                border: 3px solid #ffffff;
                transition: transform 0.2s ease;
            }
            .mobile-nav-action.active .action-circle {
                background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
                box-shadow: 0 4px 14px rgba(22, 163, 74, 0.4);
                transform: scale(1.08);
            }
            .mobile-nav-action .action-circle i {
                font-size: 1.35rem;
                margin-bottom: 0;
                color: #ffffff !important;
            }
        }
        @media (min-width: 768px) {
            .mobile-bottom-nav {
                display: none !important;
            }
        }
        .navbar-brand-autodark {
            color: #fff !important;
        }
        .score-pill-group .btn-check:checked + .btn {
            background-color: var(--brand-blue) !important;
            border-color: var(--brand-blue) !important;
            color: #fff !important;
            font-weight: bold;
        }
        .step-item.active .step-item-marker {
            background-color: var(--brand-blue);
            color: #fff;
        }
        .badge-sangat-baik { background-color: #15803d; color: #fff; }
        .badge-baik { background-color: #1d4ed8; color: #fff; }
        .badge-cukup { background-color: #d97706; color: #fff; }
        .badge-perlu-bimbingan { background-color: #b91c1c; color: #fff; }

        /* Workflow Stepper Styling */
        .workflow-stepper {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 10px 0 5px;
            margin: 0;
            list-style: none;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .workflow-step {
            position: relative;
            flex: 1 1 0;
            min-width: 110px;
            text-align: center;
            padding: 0 6px;
        }
        .workflow-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 18px;
            left: 50%;
            width: 100%;
            height: 3px;
            background-color: #e2e8f0;
            z-index: 1;
        }
        .workflow-step.step-completed:not(:last-child)::after {
            background-color: #16a34a;
        }
        .workflow-marker {
            position: relative;
            z-index: 2;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin: 0 auto 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s ease-in-out;
        }
        .workflow-step.step-completed .workflow-marker {
            background-color: #16a34a;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35);
        }
        .workflow-step.step-current .workflow-marker {
            background-color: #102d52;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(16, 45, 82, 0.2);
        }
        .workflow-step.step-pending .workflow-marker {
            background-color: #f1f5f9;
            border: 2px solid #cbd5e1;
            color: #94a3b8;
        }
        .workflow-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
            line-height: 1.25;
        }
        .workflow-step.step-completed .workflow-title {
            color: #0f172a;
        }
        .workflow-step.step-current .workflow-title {
            color: #102d52;
            font-weight: 700;
        }
        .workflow-step.step-pending .workflow-title {
            color: #64748b;
        }
        .workflow-desc {
            font-size: 11px;
            line-height: 1.2;
        }

        /* Sidebar Navigation Active & Hover Styling */
        .navbar-vertical .navbar-nav .nav-item {
            margin: 2px 4px;
        }
        .navbar-vertical .navbar-nav .nav-link {
            border-radius: 8px;
            padding: 7px 8px;
            color: #cbd5e1 !important;
            font-weight: 500;
            border-left: 4px solid transparent;
            transition: all 0.15s ease-in-out;
        }
        .navbar-vertical .navbar-nav .nav-link .nav-link-icon {
            margin-right: 0.45rem !important;
            min-width: 1.5rem;
        }
        .navbar-vertical .navbar-nav .nav-link .nav-link-title {
            font-size: 0.86rem;
            line-height: 1.25;
        }
        .navbar-vertical .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-left-color: rgba(245, 158, 11, 0.5);
        }
        .navbar-vertical .navbar-nav .nav-item.active > .nav-link,
        .navbar-vertical .navbar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-left: 4px solid #f59e0b !important;
            box-shadow: none !important;
        }
        .navbar-vertical .navbar-nav .nav-item.active > .nav-link .nav-link-title,
        .navbar-vertical .navbar-nav .nav-link.active .nav-link-title {
            color: #ffffff !important;
            font-weight: 600 !important;
        }
        .navbar-vertical .navbar-nav .nav-item.active > .nav-link .nav-link-icon i,
        .navbar-vertical .navbar-nav .nav-link.active .nav-link-icon i {
            color: #fbbf24 !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
        }
        .navbar-vertical #sidebar-menu .dropdown-menu {
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            margin: 4px 0 6px 1.15rem !important;
            padding: 2px 0 2px 0.75rem !important;
            border-left: 2px solid rgba(255, 255, 255, 0.15) !important;
        }
        .navbar-vertical #sidebar-menu .dropdown-item {
            border-radius: 6px;
            color: #cbd5e1 !important;
            padding: 6px 10px 6px 12px;
            font-size: 0.83rem;
            margin-bottom: 2px;
            border-left: 3px solid transparent;
            transition: all 0.15s ease-in-out;
        }
        .navbar-vertical #sidebar-menu .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-left-color: rgba(245, 158, 11, 0.5);
        }
        .navbar-vertical #sidebar-menu .dropdown-item.active,
        .navbar-vertical #sidebar-menu .dropdown-item:active {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-left: 3px solid #f59e0b !important;
            box-shadow: none !important;
        }
        .navbar-vertical #sidebar-menu .dropdown-item.active i {
            color: #fbbf24 !important;
        }

        /* ========================================================
           VERTICAL FLOATING SIDEBAR & MINI/COLLAPSED MODE (DESKTOP)
           ======================================================== */
        /* 1. Permanent Floating Style for Web/Desktop */
        aside.navbar-vertical,
        aside.navbar-vertical.navbar-expand {
            position: fixed !important;
            top: 0.75rem !important;
            bottom: 0.75rem !important;
            left: 0.75rem !important;
            height: calc(100vh - 1.5rem) !important;
            width: 16.75rem !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 12px 35px -4px rgba(16, 45, 82, 0.35), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            transition: width 0.32s cubic-bezier(0.4, 0, 0.2, 1),
                        padding 0.32s cubic-bezier(0.4, 0, 0.2, 1),
                        box-shadow 0.32s ease !important;
            z-index: 1040 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            overflow-y: hidden !important;
            overflow-x: hidden !important;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }

        aside.navbar-vertical::-webkit-scrollbar,
        aside.navbar-vertical.navbar-expand::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        aside.navbar-vertical > .container-fluid {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
            min-height: 100% !important;
            max-height: 100% !important;
            padding: 0.5rem 0.4rem !important;
            overflow: hidden !important;
        }

        aside.navbar-vertical #sidebar-menu {
            display: flex !important;
            flex-direction: column !important;
            flex-grow: 1 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            width: 100% !important;
            padding-right: 2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        /* Subtle smooth scrollbar for sidebar menu */
        aside.navbar-vertical #sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }
        aside.navbar-vertical #sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }
        aside.navbar-vertical #sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        aside.navbar-vertical #sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        /* Page wrapper adjustment for floating sidebar */
        .page-wrapper {
            margin-left: 18.25rem !important;
            min-height: 100vh !important;
            transition: margin-left 0.32s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* Micro-interaction: Icon animations on hover */
        .navbar-vertical .navbar-nav .nav-link .nav-link-icon i {
            display: inline-block;
            transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.18s ease;
        }
        .navbar-vertical .navbar-nav .nav-link:hover .nav-link-icon i {
            transform: scale(1.18) rotate(4deg);
        }
        .navbar-vertical .navbar-nav .nav-link {
            transition: background-color 0.2s ease, transform 0.2s ease, border-left-color 0.2s ease, color 0.2s ease;
        }
        .navbar-vertical .navbar-nav .nav-link:hover {
            transform: translateX(3px);
        }

        /* Submenu dropdown animations */
        .navbar-vertical #sidebar-menu .dropdown-menu {
            transition: opacity 0.25s ease, max-height 0.3s ease;
        }
        .navbar-vertical .nav-link.dropdown-toggle::after {
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .navbar-vertical .nav-link.dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(90deg);
        }

        /* Brand container spacing */
        .sidebar-brand-container {
            padding-left: 0.75rem !important;
            padding-right: 0.5rem !important;
        }

        /* 2. Mini / Icon-Only Collapsed Mode */
        body.sidebar-collapsed aside.navbar-vertical {
            width: 4.8rem !important;
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
        }
        body.sidebar-collapsed .page-wrapper {
            margin-left: 5.8rem !important;
        }

        /* Brand adjustment in collapsed state */
        body.sidebar-collapsed .sidebar-brand-text,
        body.sidebar-collapsed .sidebar-toggle-full-btn {
            display: none !important;
        }
        body.sidebar-collapsed .sidebar-toggle-mini-btn {
            display: inline-flex !important;
            margin-top: 4px;
        }
        body.sidebar-collapsed .sidebar-brand-container {
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            gap: 6px;
        }
        body.sidebar-collapsed .sidebar-brand-container a {
            padding-left: 0 !important;
        }
        body.sidebar-collapsed .sidebar-brand-container .avatar {
            margin-right: 0 !important;
        }

        .btn-action-sidebar-toggle:hover {
            color: #ffffff !important;
            transform: scale(1.15);
        }

        /* Hide text labels, section titles, count badges & dropdown arrows in mini mode */
        body.sidebar-collapsed .navbar-vertical .nav-link-title,
        body.sidebar-collapsed .navbar-vertical .nav-link.dropdown-toggle::after,
        body.sidebar-collapsed .navbar-vertical .sidebar-section-header,
        body.sidebar-collapsed .navbar-vertical .nav-link .badge {
            display: none !important;
            opacity: 0;
        }

        /* Center icon in mini mode */
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-item {
            margin: 4px 2px !important;
        }
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-link {
            justify-content: center !important;
            padding: 10px 0 !important;
            text-align: center;
            border-left: 3px solid transparent !important;
            border-radius: 12px;
            position: relative;
        }
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-link:hover {
            transform: scale(1.06);
        }
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-link .nav-link-icon {
            margin: 0 !important;
        }
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-item.active > .nav-link,
        body.sidebar-collapsed .navbar-vertical .navbar-nav .nav-link.active {
            background-color: rgba(245, 158, 11, 0.18) !important;
            border-left: 3px solid #f59e0b !important;
        }

        /* Hide open dropdown sub-lists in mini mode */
        body.sidebar-collapsed .navbar-vertical #sidebar-menu .dropdown-menu {
            display: none !important;
        }

        /* Floating Tooltip saat Hover Ikon di Mode Mini */
        body.sidebar-collapsed .navbar-vertical .nav-link {
            position: relative;
        }
        body.sidebar-collapsed .navbar-vertical .nav-link[data-sidebar-title]:hover::after {
            content: attr(data-sidebar-title);
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%) scale(1);
            background-color: #0f172a;
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 1060;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: tooltipPop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        @keyframes tooltipPop {
            from {
                opacity: 0;
                transform: translateY(-50%) scale(0.85);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) scale(1);
            }
        }
    </style>
    <script>
        (function() {
            try {
                if (localStorage.getItem('sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                    document.addEventListener('DOMContentLoaded', function() {
                        document.body.classList.add('sidebar-collapsed');
                    });
                }
            } catch (e) {}
        })();
    </script>
    @stack('styles')
</head>
<body>
    <div class="page">
        <!-- Sidebar -->
        <aside class="navbar navbar-vertical navbar-expand" id="main-sidebar" style="background: var(--brand-navy);" data-bs-theme="dark">
            <div class="container-fluid">
                @php $appSchoolLogo = \App\Models\SchoolSetting::getSetting()->logo_path; @endphp
                <div class="d-flex align-items-center justify-content-between w-100 py-3 border-bottom border-white-subtle mb-2 sidebar-brand-container">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none text-reset overflow-hidden ps-2">
                        @if($appSchoolLogo)
                            <img src="{{ asset('storage/' . $appSchoolLogo) }}" alt="Logo" class="me-3 flex-shrink-0" style="max-height: 40px; max-width: 46px; object-fit: contain;">
                        @else
                            <span class="avatar avatar-md bg-yellow text-dark rounded-3 me-3 fw-bold shadow-sm flex-shrink-0" style="background-color: var(--brand-gold) !important; color: #fff !important; font-size: 0.95rem; min-width: 40px; height: 40px;">
                                SIAP
                            </span>
                        @endif
                        <div class="text-start sidebar-brand-text">
                            <div class="text-uppercase tracking-wider fw-bold text-truncate" style="font-size: 0.76rem; letter-spacing: 0.08rem; color: #93c5fd !important;">SMK PGRI KRAS</div>
                            <div class="fw-bold text-white fs-3 text-truncate" style="line-height: 1.2; letter-spacing: 0.01rem;">SIAP</div>
                        </div>
                    </a>
                    <button type="button" class="btn btn-action-sidebar-toggle sidebar-toggle-full-btn text-white-50 p-1 border-0 bg-transparent shadow-none" title="Perkecil Sidebar" aria-label="Perkecil Sidebar" style="cursor: pointer; transition: color 0.2s ease, transform 0.2s ease;">
                        <i class="ti ti-chevron-left fs-2" id="sidebarToggleIcon"></i>
                    </button>
                    <button type="button" class="btn btn-action-sidebar-toggle sidebar-toggle-mini-btn text-white-50 p-1 border-0 bg-transparent shadow-none d-none" title="Perluas Sidebar" aria-label="Perluas Sidebar" style="cursor: pointer; transition: color 0.2s ease, transform 0.2s ease;">
                        <i class="ti ti-chevron-right fs-2"></i>
                    </button>
                </div>
                
                <div class="navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">
                        <!-- Group: Utama -->
                        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('dashboard') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-layout-dashboard fs-2"></i>
                                </span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('kurikulum.activities.*', 'school-activities.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('kurikulum.activities.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-calendar-star fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Agenda Kegiatan</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('kurikulum.calendar.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('kurikulum.calendar.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-calendar-event fs-2 text-info"></i>
                                </span>
                                <span class="nav-link-title">Kalender Sekolah</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('notepads.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('notepads.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-notes fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Notepad &amp; Pengingat</span>
                            </a>
                        </li>

                        <!-- Group: Pembelajaran & Kelas -->
                        <li class="nav-item pt-3 pb-1 px-2 sidebar-section-header">
                            <span class="text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08rem; color: #93c5fd !important;">
                                <i class="ti ti-book me-1"></i> Pembelajaran &amp; Kelas
                            </span>
                        </li>

                        <li class="nav-item {{ request()->routeIs('kurikulum.schedules.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('kurikulum.schedules.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-calendar-time fs-2"></i>
                                </span>
                                <span class="nav-link-title">Jadwal Pelajaran</span>
                            </a>
                        </li>

                        @if(auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']))
                        <li class="nav-item {{ request()->routeIs('kurikulum.timetables.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('kurikulum.timetables.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-file-text fs-2"></i>
                                </span>
                                <span class="nav-link-title">Arsip Jadwal PDF</span>
                            </a>
                        </li>
                        @endif

                        <!-- Presensi Mengajar Siswa oleh Guru Mapel & Jurnal KBM -->
                        <li class="nav-item {{ request()->routeIs('attendance.subject.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('attendance.subject.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-chalkboard-teacher fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Absen Mengajar (Mapel)</span>
                            </a>
                        </li>

                        <!-- Laporan Izin Siswa Perwalian oleh Wali Kelas / Admin -->
                        @if(auth()->user()->isHomeroomTeacher() || auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']))
                        <li class="nav-item {{ request()->routeIs('attendance.homeroom-permits.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('attendance.homeroom-permits.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-heart-handshake fs-2 text-indigo"></i>
                                </span>
                                <span class="nav-link-title">Izin Siswa Perwalian</span>
                            </a>
                        </li>
                        @endif

                        @if(auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']))
                        <li class="nav-item {{ request()->routeIs('kurikulum.cbt.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('kurikulum.cbt.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-device-laptop fs-2 text-cyan"></i>
                                </span>
                                <span class="nav-link-title">Integrasi CBT</span>
                            </a>
                        </li>
                        @endif

                        <!-- Group: Kedisiplinan & Operasional -->
                        <li class="nav-item pt-3 pb-1 px-2 sidebar-section-header">
                            <span class="text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08rem; color: #93c5fd !important;">
                                <i class="ti ti-shield-check me-1"></i> Kedisiplinan &amp; Operasional
                            </span>
                        </li>

                        <!-- Menu Presensi Geotagging GPS (Mobile & Desktop) -->
                        <li class="nav-item {{ request()->routeIs('attendance.geotag*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('attendance.geotag.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-map-pin fs-2 text-primary"></i>
                                </span>
                                <span class="nav-link-title">Presensi GPS</span>
                            </a>
                        </li>

                        <!-- Menu Pribadi: Izin & Cuti Saya (Milik Semua GTK) -->
                        <li class="nav-item {{ (request()->routeIs('attendance.gtk-permits*') && request('scope') === 'personal') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('attendance.gtk-permits.index', ['scope' => 'personal']) }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-file-text fs-2 text-indigo"></i>
                                </span>
                                <span class="nav-link-title">Izin &amp; Cuti Saya</span>
                            </a>
                        </li>

                        <!-- Menu Piket Sekolah -->
                        @if(auth()->user()->canAccessPiket())
                        <li class="nav-item dropdown {{ request()->routeIs('piket.*') ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-piket" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('piket.*') ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-shield-check fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Piket Sekolah</span>
                            </a>
                            <div class="dropdown-menu {{ request()->routeIs('piket.*') ? 'show' : '' }}">
                                <a class="dropdown-item {{ request()->routeIs('piket.dashboard') ? 'active' : '' }}" href="{{ route('piket.dashboard') }}">
                                    <i class="ti ti-layout-dashboard me-2"></i> Dashboard Piket
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('piket.schedules.*') ? 'active' : '' }}" href="{{ route('piket.schedules.index') }}">
                                    <i class="ti ti-calendar-user me-2"></i> Jadwal &amp; Petugas
                                </a>
                                @if(auth()->user()->canManagePiketSchedules())
                                <a class="dropdown-item {{ request()->routeIs('piket.swaps.*') ? 'active' : '' }}" href="{{ route('piket.swaps.index') }}">
                                    <i class="ti ti-arrows-left-right me-2"></i> Tukar Jadwal Piket
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('piket.calendar.*') ? 'active' : '' }}" href="{{ route('piket.calendar.index') }}">
                                    <i class="ti ti-calendar me-2"></i> Kalender Piket
                                </a>
                                @endif

                                @if(auth()->user()->canOperatePiket())
                                <div class="dropdown-divider"></div>
                                <h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.65rem;">Operasional Meja Piket</h6>
                                <a class="dropdown-item {{ request()->routeIs('piket.journals.*') ? 'active' : '' }}" href="{{ route('piket.journals.index') }}">
                                    <i class="ti ti-notebook me-2"></i> Jurnal Piket Harian
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('piket.permits.*') ? 'active' : '' }}" href="{{ route('piket.permits.index') }}">
                                    <i class="ti ti-door-exit me-2"></i> Perizinan Siswa
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('piket.inval.*') ? 'active' : '' }}" href="{{ route('piket.inval.index') }}">
                                    <i class="ti ti-chalkboard me-2"></i> Guru Inval / Kosong
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('piket.guests.*') ? 'active' : '' }}" href="{{ route('piket.guests.index') }}">
                                    <i class="ti ti-address-book me-2"></i> Buku Tamu
                                </a>
                                @endif

                                @if(auth()->user()->canManagePiketCases())
                                <a class="dropdown-item {{ request()->routeIs('piket.incidents.*') ? 'active' : '' }}" href="{{ route('piket.incidents.index') }}">
                                    <i class="ti ti-alert-triangle me-2"></i> Catatan Kasus / Kejadian
                                </a>
                                @endif

                                @if(auth()->user()->canViewPiketReports())
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item {{ request()->routeIs('piket.reports.*') ? 'active' : '' }}" href="{{ route('piket.reports.index') }}">
                                    <i class="ti ti-report-analytics me-2"></i> Rekapitulasi &amp; Laporan
                                </a>
                                @endif

                                @if(auth()->user()->canManagePiketSettings())
                                <a class="dropdown-item {{ request()->routeIs('piket.telegram.*') ? 'active' : '' }}" href="{{ route('piket.telegram.index') }}">
                                    <i class="ti ti-brand-telegram me-2 text-info"></i> Bot Telegram Piket
                                </a>
                                @endif
                            </div>
                        </li>
                        @endif

                        <!-- Rekapitulasi Presensi (Pimpinan / Waka / Admin) -->
                        @php
                            $canAccessAttendanceRecap = auth()->user()->canAccessTeacherAttendanceReport() 
                                || auth()->user()->canAccessStudentAttendanceReport() 
                                || auth()->user()->canManageAttendanceDevices() 
                                || auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah']);
                            $todayGtkPermitBadgeCount = \App\Models\GtkPermit::whereDate('start_date', '<=', now()->toDateString())
                                ->whereDate('end_date', '>=', now()->toDateString())
                                ->count();
                            $pendingGtkBadgeCount = auth()->user()->isPrincipal()
                                ? \App\Models\GtkPermit::where('status', 'pending')->count()
                                : 0;
                        @endphp
                        @if($canAccessAttendanceRecap)
                        <li class="nav-item dropdown {{ (request()->routeIs('attendance.report.*', 'attendance.devices*', 'attendance.index') || (request()->routeIs('attendance.gtk-permits*') && request('scope') !== 'personal')) ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-rekap-presensi" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ (request()->routeIs('attendance.report.*', 'attendance.devices*', 'attendance.index') || (request()->routeIs('attendance.gtk-permits*') && request('scope') !== 'personal')) ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-report-analytics fs-2 text-teal"></i>
                                </span>
                                <span class="nav-link-title">Rekap Presensi</span>
                                @if($pendingGtkBadgeCount > 0)
                                    <span class="badge bg-danger text-white fw-bold ms-auto">{{ $pendingGtkBadgeCount }}</span>
                                @elseif($todayGtkPermitBadgeCount > 0)
                                    <span class="badge bg-warning text-dark fw-bold ms-auto">{{ $todayGtkPermitBadgeCount }}</span>
                                @endif
                            </a>
                            <div class="dropdown-menu {{ (request()->routeIs('attendance.report.*', 'attendance.devices*', 'attendance.index') || (request()->routeIs('attendance.gtk-permits*') && request('scope') !== 'personal')) ? 'show' : '' }}">
                                @if(auth()->user()->canAccessTeacherAttendanceReport())
                                <a class="dropdown-item {{ request()->routeIs('attendance.report.teachers*') ? 'active' : '' }}" href="{{ route('attendance.report.teachers') }}">
                                    <i class="ti ti-user-check me-2 text-teal"></i> Presensi Guru &amp; Staf
                                </a>
                                <a class="dropdown-item d-flex align-items-center {{ (request()->routeIs('attendance.gtk-permits*') && request('scope') !== 'personal') ? 'active' : '' }}" href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage']) }}">
                                    <i class="ti ti-file-certificate me-2 text-warning"></i> Daftar Izin &amp; Cuti GTK
                                    @if($pendingGtkBadgeCount > 0)
                                        <span class="badge bg-danger text-white fw-bold ms-auto" title="{{ $pendingGtkBadgeCount }} permohonan butuh persetujuan">{{ $pendingGtkBadgeCount }}</span>
                                    @elseif($todayGtkPermitBadgeCount > 0)
                                        <span class="badge bg-warning text-dark fw-bold ms-auto" title="{{ $todayGtkPermitBadgeCount }} GTK berhalangan hari ini">{{ $todayGtkPermitBadgeCount }}</span>
                                    @endif
                                </a>
                                @endif

                                @if(auth()->user()->canAccessStudentAttendanceReport())
                                <a class="dropdown-item {{ request()->routeIs('attendance.report.students*') ? 'active' : '' }}" href="{{ route('attendance.report.students') }}">
                                    <i class="ti ti-users-group me-2 text-indigo"></i> Presensi Siswa
                                </a>
                                @endif

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item {{ request()->routeIs('attendance.index') ? 'active' : '' }}" href="{{ route('attendance.index') }}">
                                    <i class="ti ti-calendar-check me-2 text-cyan"></i> Log Absensi (Mesin)
                                </a>

                                @if(auth()->user()->canManageAttendanceDevices())
                                <a class="dropdown-item {{ request()->routeIs('attendance.devices*') ? 'active' : '' }}" href="{{ route('attendance.devices.index') }}">
                                    <i class="ti ti-devices me-2 text-primary"></i> Mesin Absensi
                                </a>
                                @endif
                            </div>
                        </li>
                        @endif

                        <!-- Group: Supervisi Guru -->
                        <li class="nav-item pt-3 pb-1 px-2 sidebar-section-header">
                            <span class="text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08rem; color: #93c5fd !important;">
                                <i class="ti ti-certificate me-1"></i> Supervisi Guru
                            </span>
                        </li>

                        <li class="nav-item dropdown {{ request()->routeIs('schedules.*', 'supervisions.*', 'reports.history', 'reports.print-index', 'approvals.*', 'reports.recap', 'rubrics.*') ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-supervisi" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('schedules.*', 'supervisions.*', 'reports.history', 'reports.print-index', 'approvals.*', 'reports.recap', 'rubrics.*') ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-git-fork fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Supervisi Guru</span>
                                @php
                                    $pendingApprovalCount = 0;
                                    if (auth()->user()->hasPermission('approve_supervision') || auth()->user()->hasRole('kepala_sekolah') || auth()->user()->hasRole('super_admin')) {
                                        $pendingApprovalCount = \App\Models\Supervision::where('status', 'menunggu_persetujuan')->count();
                                    }
                                @endphp
                                @if($pendingApprovalCount > 0)
                                    <span class="badge bg-warning text-dark fw-bold ms-auto">{{ $pendingApprovalCount }}</span>
                                @endif
                            </a>
                            <div class="dropdown-menu {{ request()->routeIs('schedules.*', 'supervisions.*', 'reports.history', 'reports.print-index', 'approvals.*', 'reports.recap', 'rubrics.*') ? 'show' : '' }}">
                                <a class="dropdown-item {{ request()->routeIs('supervisions.dashboard') ? 'active' : '' }}" href="{{ route('supervisions.dashboard') }}">
                                    <i class="ti ti-layout-dashboard me-2 text-primary"></i> Dashboard Supervisi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('schedules.*') ? 'active' : '' }}" href="{{ route('schedules.index') }}">
                                    <i class="ti ti-calendar-event me-2"></i> Jadwal Supervisi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('supervisions.preobs', 'supervisions.pre-observation') ? 'active' : '' }}" href="{{ route('supervisions.preobs') }}">
                                    <i class="ti ti-clipboard-list me-2 text-info"></i> Pra Observasi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('supervisions.obs', 'supervisions.observation') ? 'active' : '' }}" href="{{ route('supervisions.obs') }}">
                                    <i class="ti ti-notebook me-2 text-indigo"></i> Observasi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('supervisions.docs', 'supervisions.documentation') ? 'active' : '' }}" href="{{ route('supervisions.docs') }}">
                                    <i class="ti ti-photo me-2 text-cyan"></i> Dokumentasi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('supervisions.results', 'supervisions.post-observation', 'supervisions.show') ? 'active' : '' }}" href="{{ route('supervisions.results') }}">
                                    <i class="ti ti-award me-2 text-yellow"></i> Hasil Supervisi
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item {{ request()->routeIs('reports.history') ? 'active' : '' }}" href="{{ route('reports.history') }}">
                                    <i class="ti ti-history me-2"></i> Riwayat Supervisi
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('reports.print-index') ? 'active' : '' }}" href="{{ route('reports.print-index') }}">
                                    <i class="ti ti-printer me-2"></i> Cetak Laporan
                                </a>
                                @if(auth()->user()->hasPermission('approve_supervision') || auth()->user()->hasRole('kepala_sekolah') || auth()->user()->hasRole('super_admin'))
                                <a class="dropdown-item d-flex align-items-center {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">
                                    <i class="ti ti-checklist me-2 text-warning"></i> Persetujuan
                                    @if($pendingApprovalCount > 0)
                                        <span class="badge bg-warning text-dark fw-bold ms-auto">{{ $pendingApprovalCount }}</span>
                                    @endif
                                </a>
                                @endif
                                @if(auth()->user()->hasPermission('view_all_supervision') || auth()->user()->hasRole('waka_kurikulum') || auth()->user()->hasRole('super_admin'))
                                <a class="dropdown-item {{ request()->routeIs('reports.recap') ? 'active' : '' }}" href="{{ route('reports.recap') }}">
                                    <i class="ti ti-chart-bar me-2"></i> Rekap Supervisi
                                </a>
                                @endif
                                @if(auth()->user()->hasPermission('manage_rubrics') || auth()->user()->hasRole('waka_kurikulum') || auth()->user()->hasRole('super_admin'))
                                <a class="dropdown-item {{ request()->routeIs('rubrics.*') ? 'active' : '' }}" href="{{ route('rubrics.index') }}">
                                    <i class="ti ti-list-check me-2"></i> Indikator dan Rubik
                                </a>
                                @endif
                            </div>
                        </li>

                        <!-- Group: Data Pokok (Termasuk Profil Sekolah sesuai permintaan) -->
                        <li class="nav-item pt-3 pb-1 px-2 sidebar-section-header">
                            <span class="text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08rem; color: #93c5fd !important;">
                                <i class="ti ti-database me-1"></i> Data Pokok
                            </span>
                        </li>

                        <!-- Profil Sekolah (Sesuai permintaan: termasuk di dalam Data Pokok) -->
                        <li class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('settings.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-building fs-2 text-primary"></i>
                                </span>
                                <span class="nav-link-title">Profil Sekolah</span>
                            </a>
                        </li>

                        <!-- Data Guru -->
                        <li class="nav-item {{ request()->routeIs('teachers.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('teachers.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-users fs-2"></i>
                                </span>
                                <span class="nav-link-title">Data Guru</span>
                            </a>
                        </li>

                        <!-- Data Siswa -->
                        <li class="nav-item {{ request()->routeIs('students.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('students.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-school fs-2"></i>
                                </span>
                                <span class="nav-link-title">Data Siswa</span>
                            </a>
                        </li>

                        <!-- Master Akademik (Dropdown) -->
                        @if(auth()->user()->hasPermission('manage_master_data') || auth()->user()->hasPermission('manage_settings') || auth()->user()->hasRole('waka_kurikulum') || auth()->user()->hasRole('super_admin'))
                        <li class="nav-item dropdown {{ request()->routeIs('academic-years.*', 'departments.*', 'classrooms.*', 'subjects.*', 'structural-positions.*', 'teacher-assignments.*', 'stakeholders.*', 'activity-categories.*') ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-master" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('academic-years.*', 'departments.*', 'classrooms.*', 'subjects.*', 'structural-positions.*', 'teacher-assignments.*', 'stakeholders.*', 'activity-categories.*') ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-folders fs-2"></i>
                                </span>
                                <span class="nav-link-title">Master Akademik</span>
                            </a>
                            <div class="dropdown-menu {{ request()->routeIs('academic-years.*', 'departments.*', 'classrooms.*', 'subjects.*', 'structural-positions.*', 'teacher-assignments.*', 'stakeholders.*', 'activity-categories.*') ? 'show' : '' }}">
                                <a class="dropdown-item {{ request()->routeIs('academic-years.*') ? 'active' : '' }}" href="{{ route('academic-years.index') }}">
                                    <i class="ti ti-calendar-stats me-2"></i> Tahun Ajaran
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}">
                                    <i class="ti ti-school me-2"></i> Jurusan / Keahlian
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}">
                                    <i class="ti ti-layout-grid me-2"></i> Tingkat &amp; Rombel
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('subjects.*') ? 'active' : '' }}" href="{{ route('subjects.index') }}">
                                    <i class="ti ti-books me-2"></i> Mata Pelajaran
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('teacher-assignments.*', 'structural-positions.*') ? 'active' : '' }}" href="{{ route('teacher-assignments.index') }}">
                                    <i class="ti ti-user-check me-2"></i> Penugasan Guru
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('stakeholders.*') ? 'active' : '' }}" href="{{ route('stakeholders.index') }}">
                                    <i class="ti ti-users-group me-2"></i> Pejabat &amp; Pihak Terkait
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('activity-categories.*') ? 'active' : '' }}" href="{{ route('activity-categories.index') }}">
                                    <i class="ti ti-tags me-2"></i> Kategori Kegiatan
                                </a>
                            </div>
                        </li>
                        @endif

                        <!-- Group: Pengaturan & Sistem -->
                        <li class="nav-item pt-3 pb-1 px-2 sidebar-section-header">
                            <span class="text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08rem; color: #93c5fd !important;">
                                <i class="ti ti-settings me-1"></i> Pengaturan &amp; Sistem
                            </span>
                        </li>

                        @if(auth()->user()->hasPermission('manage_settings') || auth()->user()->hasRole('waka_kurikulum') || auth()->user()->hasRole('super_admin'))
                        <li class="nav-item {{ request()->routeIs('telegram.*', 'piket.telegram.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('telegram.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-brand-telegram fs-2 text-info"></i>
                                </span>
                                <span class="nav-link-title">Bot Telegram &amp; Notifikasi</span>
                            </a>
                        </li>
                        @endif

                        @if(auth()->user()->hasPermission('manage_roles') || auth()->user()->hasRole('super_admin'))
                        <li class="nav-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('roles.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-shield-lock fs-2"></i>
                                </span>
                                <span class="nav-link-title">Hak Akses &amp; Peran</span>
                            </a>
                        </li>
                        @endif

                        <li class="nav-item {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}">
                            @php
                                $pendingFeedbackCount = \App\Models\AppFeedback::where('status', 'menunggu')->count();
                            @endphp
                            <a class="nav-link d-flex align-items-center" href="{{ route('feedbacks.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-bulb fs-2 text-warning"></i>
                                </span>
                                <span class="nav-link-title">Catatan Perbaikan</span>
                                @if($pendingFeedbackCount > 0 && (auth()->user()->isWakaKurikulum() || auth()->user()->hasRole('super_admin')))
                                    <span class="badge bg-warning text-dark fw-bold ms-auto">{{ $pendingFeedbackCount }}</span>
                                @endif
                            </a>
                        </li>

                        @if(auth()->user()->hasRole('super_admin'))
                        <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('users.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-user-cog fs-2 text-primary"></i>
                                </span>
                                <span class="nav-link-title">Manajemen User</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('active-users.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('active-users.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-user-check fs-2 text-success"></i>
                                </span>
                                <span class="nav-link-title">User Aktif</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('activity-logs.index') }}">
                                <span class="nav-link-icon d-inline-block">
                                    <i class="ti ti-activity fs-2 text-info"></i>
                                </span>
                                <span class="nav-link-title">Log Aktivitas</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </aside>

        <div class="page-wrapper">
            <!-- Header -->
            <!-- Header (Model 2: Modern Compact Bar) -->
            <header class="navbar navbar-expand d-flex d-print-none sticky-top bg-white border-bottom shadow-xs py-2">
                <div class="container-fluid px-3">
                    <button type="button" class="btn btn-icon btn-light me-2.5 btn-action-sidebar-toggle border shadow-none" title="Kecilkan / Perluas Sidebar" aria-label="Toggle Sidebar" style="width: 36px; height: 36px; border-radius: 9px;">
                        <i class="ti ti-layout-sidebar fs-2 text-dark"></i>
                    </button>
                    
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge px-2.5 py-1 text-white fw-bold shadow-xs" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); font-size: 0.82rem; letter-spacing: 0.04rem;">
                            SIAP
                        </span>
                        <span class="badge bg-blue-lt px-2 py-1 fw-bold d-none d-sm-inline-flex align-items-center">
                            <i class="ti ti-school me-1"></i> SMK PGRI KRAS
                        </span>
                        <span class="text-secondary small d-none d-md-inline-flex align-items-center ms-1">
                            <i class="ti ti-calendar-event me-1 text-muted"></i> {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                        </span>
                    </div>

                    <div class="navbar-nav flex-row order-md-last ms-auto align-items-center gap-2">
                        <!-- Tahun Pelajaran Switcher -->
                        @include('layouts._academic_year_switcher', ['prefix' => 'dt'])

                        <!-- Lonceng Notifikasi & Tugas -->
                        @include('layouts._notifications_dropdown')

                        <!-- Profil Pengguna -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-1 rounded-2 hover-shadow-xs" data-bs-toggle="dropdown" aria-label="Menu Pengguna" style="transition: background 0.15s ease;">
                                <span class="avatar avatar-sm text-white rounded-2 shadow-xs fw-bold" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); width: 34px; height: 34px;">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </span>
                                <div class="d-none d-xl-block ps-2 text-start">
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.82rem; max-width: 150px;">{{ auth()->user()->name }}</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">{{ auth()->user()->role?->name ?? 'Pengguna' }}</div>
                                </div>
                                <i class="ti ti-chevron-down ms-1 text-muted fs-4 d-none d-xl-inline"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow shadow-md border" style="border-radius: 10px;">
                                <div class="px-3 py-2 border-bottom bg-light-subtle">
                                    <div class="fw-bold text-dark small">{{ auth()->user()->name }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ auth()->user()->email ?? '-' }}</div>
                                </div>
                                <a href="{{ route('profile') }}" class="dropdown-item py-2">
                                    <i class="ti ti-user me-2 text-primary"></i> Profil Saya
                                </a>
                                @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('waka_kurikulum'))
                                <a href="{{ route('settings.index') }}" class="dropdown-item py-2">
                                    <i class="ti ti-building me-2 text-indigo"></i> Data Sekolah
                                </a>
                                @endif
                                <div class="dropdown-divider my-1"></div>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="ti ti-logout me-2"></i> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page body -->
            <div class="page-body">
                <div class="container-xl">
                    <!-- Archive Year Banner Indicator -->
                    @if(\App\Models\SchoolSetting::isViewingArchive())
                        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm border-0" style="background-color: #fefce8; border-left: 4px solid #eab308 !important;">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-history fs-1 text-warning me-3"></i>
                                <div>
                                    <div class="fw-bold text-dark">Mode Arsip: Anda sedang melihat data Tahun Pelajaran {{ \App\Models\SchoolSetting::getSetting()->academic_year }}</div>
                                    <div class="text-muted small">Data guru, penugasan, rombel kelas, jadwal, dan supervisi yang tampil disesuaikan dengan periode tahun ini.</div>
                                </div>
                            </div>
                            <div class="ms-3 flex-shrink-0">
                                <a href="{{ route('academic-year.reset') }}" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm">
                                    <i class="ti ti-rotate-2 me-1"></i> Kembali ke Tahun Master ({{ \App\Models\SchoolSetting::getMasterYear() }})
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Session Alerts -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-circle-check fs-2 me-2"></i>
                                <div>{{ session('success') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('info'))
                        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-info-circle fs-2 me-2"></i>
                                <div>{{ session('info') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-alert-triangle fs-2 me-2"></i>
                                <div>{{ session('warning') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('danger'))
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-trash-x fs-2 me-2"></i>
                                <div>{{ session('danger') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error') || $errors->any())
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-alert-triangle fs-2 me-2"></i>
                                <div>
                                    @if(session('error'))
                                        {{ session('error') }}
                                    @else
                                        <strong>Terjadi kesalahan input:</strong>
                                        <ul class="mb-0 mt-1 ps-3">
                                            @foreach($errors->all() as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>

            <!-- Footer -->
            <footer class="footer footer-transparent d-print-none py-3 border-top bg-white">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <span class="text-secondary small">Versi Aplikasi 1.0 — Tabler UI</span>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <span class="text-secondary small">
                                &copy; {{ date('Y') }} <strong>{{ \App\Models\SchoolSetting::getSetting()->school_name }}</strong>. SIAP &mdash; Sistem Informasi Administrasi dan Pendidikan.
                            </span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Tabler Core JS -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
    <!-- Smart Search (Pencarian Pintar) -->
    <script src="{{ asset('js/smart-search.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtns = document.querySelectorAll('.btn-action-sidebar-toggle');
            var headerToggleIcon = document.getElementById('headerSidebarToggleIcon');

            // Populate data-sidebar-title on nav links for smooth floating tooltip when collapsed
            var navLinks = document.querySelectorAll('.navbar-vertical .nav-link');
            navLinks.forEach(function(link) {
                var titleElem = link.querySelector('.nav-link-title');
                if (titleElem) {
                    var titleText = titleElem.textContent.trim();
                    if (titleText) {
                        link.setAttribute('data-sidebar-title', titleText);
                    }
                }
            });

            function syncSidebarState(isCollapsed) {
                if (isCollapsed) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                }
            }

            // Sync initial state
            try {
                var isInitiallyCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
                syncSidebarState(isInitiallyCollapsed);
            } catch (e) {}

            toggleBtns.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var currentlyCollapsed = document.body.classList.contains('sidebar-collapsed');
                    var willCollapse = !currentlyCollapsed;
                    syncSidebarState(willCollapse);
                    try {
                        localStorage.setItem('sidebar_collapsed', willCollapse ? 'true' : 'false');
                    } catch (err) {}
                });
            });

            // Preserve sidebar menu scroll position across page reloads/clicks
            var sidebarMenu = document.getElementById('sidebar-menu');
            if (sidebarMenu) {
                var savedScroll = null;
                try {
                    savedScroll = sessionStorage.getItem('siap_sidebar_scroll');
                } catch(e) {}

                if (savedScroll !== null) {
                    sidebarMenu.scrollTop = parseInt(savedScroll, 10);
                } else {
                    // Fallback: auto-scroll to active menu item
                    var activeItem = sidebarMenu.querySelector('.dropdown-item.active, .nav-link.active');
                    if (activeItem) {
                        activeItem.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    }
                }

                sidebarMenu.addEventListener('scroll', function() {
                    try {
                        sessionStorage.setItem('siap_sidebar_scroll', sidebarMenu.scrollTop);
                    } catch(e) {}
                }, { passive: true });

                sidebarMenu.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        try {
                            sessionStorage.setItem('siap_sidebar_scroll', sidebarMenu.scrollTop);
                        } catch(e) {}
                    });
                });
            }
        });
    </script>

    <!-- PWA Offline Indicator -->
    <div id="pwa-offline-indicator" class="alert alert-warning border-0 shadow-lg position-fixed start-50 translate-middle-x d-none" style="bottom: 1.5rem; z-index: 9999; border-radius: 50rem; padding: 0.6rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger rounded-circle p-1"></span>
            <span class="fw-semibold small">Mode Offline: Koneksi internet terputus</span>
        </div>
    </div>

    <!-- PWA Service Worker Registration & Online/Offline Handlers -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(reg) {
                    // Service worker registered successfully
                }).catch(function(err) {
                    console.warn('SIAP PWA: Service Worker registration failed: ', err);
                });
            });
        }

        (function() {
            var indicator = document.getElementById('pwa-offline-indicator');
            if (!indicator) return;

            function updateNetworkStatus() {
                if (navigator.onLine) {
                    if (!indicator.classList.contains('d-none')) {
                        indicator.classList.remove('alert-warning');
                        indicator.classList.add('alert-success');
                        indicator.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="badge bg-success rounded-circle p-1"></span><span class="fw-semibold small">Koneksi internet kembali normal</span></div>';
                        setTimeout(function() {
                            indicator.classList.add('d-none');
                            indicator.classList.remove('alert-success');
                            indicator.classList.add('alert-warning');
                        }, 3000);
                    }
                } else {
                    indicator.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="badge bg-danger rounded-circle p-1"></span><span class="fw-semibold small">Mode Offline: Koneksi internet terputus</span></div>';
                    indicator.classList.remove('d-none');
                }
            }

            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);

            // Initial check if opened while offline
            if (!navigator.onLine) {
                updateNetworkStatus();
            }
        })();
    </script>

    @auth
    <!-- Mobile Bottom Navigation Bar (GTK Routine Mobile Hub) -->
    <nav class="mobile-bottom-nav d-md-none">
        <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') || request()->is('/') ? 'active' : '' }}">
            <i class="ti ti-home-2"></i>
            <span>Beranda</span>
        </a>
        <a href="{{ route('attendance.subject.index') }}" class="mobile-nav-item {{ request()->routeIs('attendance.subject.*') ? 'active' : '' }}">
            <i class="ti ti-book"></i>
            <span>KBM</span>
        </a>
        <a href="{{ route('attendance.geotag.index') }}" class="mobile-nav-item mobile-nav-action {{ request()->routeIs('attendance.geotag.*') ? 'active' : '' }}">
            <div class="action-circle">
                <i class="ti ti-map-pin"></i>
            </div>
            <span>Absen</span>
        </a>
        <a href="#" class="mobile-nav-item" data-bs-toggle="modal" data-bs-target="#modalMobileQuickTasks">
            <i class="ti ti-layout-grid"></i>
            <span>Tugas</span>
        </a>
        <a href="{{ route('profile') }}" class="mobile-nav-item {{ request()->routeIs('profile*') ? 'active' : '' }}">
            <i class="ti ti-user"></i>
            <span>Profil</span>
        </a>
    </nav>

    <!-- Modal Quick Tasks GTK (Bottom Sheet on Mobile) -->
    <div class="modal fade" id="modalMobileQuickTasks" tabindex="-1" aria-labelledby="modalMobileQuickTasksLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalMobileQuickTasksLabel">
                        <i class="ti ti-apps text-primary fs-3"></i> Pintasan Rutinitas GTK
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('attendance.geotag.index') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-primary text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-map-pin fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Absen GPS</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Geotagging GTK</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('attendance.subject.index') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-success text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-book fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Jurnal KBM</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Presensi Kelas</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('attendance.homeroom-permits.index') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-warning text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-clipboard-list fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Izin Perwalian</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Siswa Rombel</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('piket.dashboard') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-teal text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-shield-check fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Piket Sekolah</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Buku Tamu &amp; Inval</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('attendance.gtk-permits.index') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-purple text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-calendar-time fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Izin / Cuti GTK</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Pengajuan Cuti</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('kurikulum.calendar.index') }}" class="card card-link text-center p-3 border rounded-3 text-decoration-none h-100 bg-light hover-shadow">
                                <span class="avatar avatar-sm bg-info text-white rounded-circle mx-auto mb-2">
                                    <i class="ti ti-calendar-event fs-3"></i>
                                </span>
                                <span class="fw-bold text-dark small d-block">Kalender</span>
                                <span class="text-muted" style="font-size: 0.7rem;">Agenda Sekolah</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endauth

    @stack('scripts')
</body>
</html>
