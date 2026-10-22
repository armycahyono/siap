<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>@yield('title', 'APK SIAP &bull; Home GTK')</title>
    
    <!-- PWA Web App Manifest & Android/iOS Meta -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#102d52">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SIAP GTK">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
    
    <!-- Preconnect & DNS-Prefetch for Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">

    <!-- Local Vendor Assets (Self-Hosted for Offline & Intranet Independence) -->
    <link rel="stylesheet" href="{{ asset('vendor/framework7/framework7-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
    
    <!-- Google Fonts with Native System Fallback -->
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --f7-font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --f7-theme-color: #195b9e;
            --f7-theme-color-rgb: 25, 91, 158;
            --brand-navy: #102d52;
            --brand-blue: #195b9e;
            --brand-gold: #c99a3b;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            font-family: var(--f7-font-family);
            background-color: #f1f5f9;
            color: #1e293b;
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            user-select: none;
        }

        #app {
            min-height: 100%;
            height: 100%;
            width: 100%;
            position: relative;
        }

        /* Material Top App Bar (Framework7 Navbar) */
        .mobile-top-bar.navbar {
            background-color: #102d52 !important;
            background-image: url('/images/mobile/navbar-bg.webp') !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-position: center right !important;
            color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(16, 45, 82, 0.25);
            height: calc(56px + env(safe-area-inset-top, 0px));
            padding-top: env(safe-area-inset-top, 0px);
        }
        .mobile-top-bar .navbar-bg {
            background: transparent !important;
        }
        .mobile-top-bar .title {
            color: #ffffff !important;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        /* Material Bottom Tabbar (Framework7 Toolbar) */
        .mobile-bottom-nav.toolbar {
            height: calc(62px + env(safe-area-inset-bottom, 0px)) !important;
            padding-bottom: env(safe-area-inset-bottom, 0px) !important;
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 -4px 20px rgba(16, 45, 82, 0.08) !important;
            border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
            overflow: visible !important;
            contain: none !important;
        }
        .mobile-bottom-nav .toolbar-inner {
            padding: 0 4px;
            overflow: visible !important;
            contain: none !important;
        }
        .mobile-bottom-nav .tab-link {
            color: #64748b !important;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 4px 0;
            transition: all 0.2s ease;
        }
        .mobile-bottom-nav .tab-link.mobile-nav-action {
            overflow: visible !important;
            contain: none !important;
        }
        .mobile-bottom-nav .tab-link i {
            font-size: 1.35rem;
            margin-bottom: 2px;
            line-height: 1;
            transition: transform 0.2s ease;
        }
        .mobile-bottom-nav .tab-link.tab-link-active,
        .mobile-bottom-nav .tab-link.active {
            color: var(--brand-blue) !important;
        }
        .mobile-bottom-nav .tab-link.tab-link-active i,
        .mobile-bottom-nav .tab-link.active i {
            transform: scale(1.14);
            color: var(--brand-blue) !important;
        }

        /* Center Floating Action Button (Absen GPS) */
        .mobile-nav-action {
            position: relative;
            overflow: visible !important;
            contain: none !important;
        }
        .mobile-nav-action .action-circle {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(25, 91, 158, 0.45);
            margin-top: -24px;
            margin-bottom: 2px;
            border: 3.5px solid #ffffff;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 100;
        }
        .mobile-nav-action.tab-link-active .action-circle,
        .mobile-nav-action.active .action-circle {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.45);
            transform: scale(1.08);
        }
        .mobile-nav-action .action-circle i {
            font-size: 1.45rem !important;
            color: #ffffff !important;
            margin-bottom: 0 !important;
        }

        /* Page Content container */
        .page-content {
            padding-top: calc(68px + env(safe-area-inset-top, 0px)) !important;
            padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
            background-color: #f1f5f9;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        /* Native-style Card Utilities for Mobile */
        .card {
            background: #ffffff;
            border-radius: 0.85rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 2px 6px rgba(16, 45, 82, 0.04);
            margin: 0 0 8px 0 !important;
            position: relative;
            overflow: hidden;
            box-sizing: border-box !important;
            width: 100%;
        }
        .card-body {
            padding: 0.75rem;
            box-sizing: border-box !important;
        }

        /* Clean Mobile CSS Grid System (No Framework7 conflicts) */
        .mobile-grid-2 {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
            margin-bottom: 8px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .mobile-grid-3 {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
            margin-bottom: 8px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .mobile-grid-4 {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 6px !important;
            margin-bottom: 8px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .mobile-grid-2 > .card,
        .mobile-grid-2 > a.card,
        .mobile-grid-3 > .card,
        .mobile-grid-3 > a.card,
        .mobile-grid-4 > .card,
        .mobile-grid-4 > a.card {
            margin: 0 !important;
            width: 100% !important;
            height: 100% !important;
            box-sizing: border-box !important;
        }
        .d-flex { display: flex !important; }
        .d-none { display: none !important; }
        .flex-column { flex-direction: column !important; }
        .flex-row { flex-direction: row !important; }
        .align-items-center { align-items: center !important; }
        .align-items-start { align-items: flex-start !important; }
        .justify-content-between { justify-content: space-between !important; }
        .justify-content-center { justify-content: center !important; }
        .flex-fill { flex: 1 1 auto !important; }
        .flex-shrink-0 { flex-shrink: 0 !important; }
        .gap-1 { gap: 0.25rem !important; }
        .gap-1\.5 { gap: 0.38rem !important; }
        .gap-2 { gap: 0.5rem !important; }
        .gap-2\.5 { gap: 0.65rem !important; }
        .gap-3 { gap: 1rem !important; }
        .w-100 { width: 100% !important; box-sizing: border-box !important; }
        .h-100 { height: 100% !important; }

        /* Typography & Spacing */
        .text-white { color: #ffffff !important; }
        .text-white-50 { color: rgba(255, 255, 255, 0.65) !important; }
        .text-muted { color: #64748b !important; }
        .text-dark { color: #0f172a !important; }
        .text-primary { color: #195b9e !important; }
        .text-success { color: #16a34a !important; }
        .text-warning { color: #d97706 !important; }
        .text-danger { color: #dc2626 !important; }
        .text-info { color: #0284c7 !important; }
        .fw-bold { font-weight: 700 !important; }
        .fw-semibold { font-weight: 600 !important; }
        .text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .text-center { text-align: center !important; }
        .text-start { text-align: left !important; }
        .text-end { text-align: right !important; }
        .mx-auto { margin-left: auto !important; margin-right: auto !important; }
        .lh-1 { line-height: 1 !important; }
        .lh-sm { line-height: 1.25 !important; }
        .small { font-size: 0.8rem; }
        .fs-1 { font-size: 1.5rem !important; }
        .fs-2 { font-size: 1.25rem !important; }
        .fs-3 { font-size: 1rem !important; }
        .fs-4 { font-size: 0.88rem !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-1 { margin-bottom: 0.25rem !important; }
        .mb-1\.5 { margin-bottom: 0.38rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-3 { margin-bottom: 0.75rem !important; }
        .mb-4 { margin-bottom: 1rem !important; }
        .mt-0\.5 { margin-top: 0.125rem !important; }
        .mt-1 { margin-top: 0.25rem !important; }
        .my-1 { margin-top: 0.25rem !important; margin-bottom: 0.25rem !important; }
        .g-1\.5 { margin-right: -0.25rem !important; margin-left: -0.25rem !important; }
        .g-1\.5 > * { padding-right: 0.25rem !important; padding-left: 0.25rem !important; margin-top: 0.5rem; }
        .p-2 { padding: 0.5rem !important; }
        .p-2\.5 { padding: 0.65rem !important; }
        .p-3 { padding: 1rem !important; }
        .py-1 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
        .py-1\.5 { padding-top: 0.38rem !important; padding-bottom: 0.38rem !important; }
        .py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
        .py-3 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
        .px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
        .px-2\.5 { padding-left: 0.65rem !important; padding-right: 0.65rem !important; }
        .px-3 { padding-left: 1rem !important; padding-right: 1rem !important; }
        .pe-1 { padding-right: 0.25rem !important; }
        .pe-2 { padding-right: 0.5rem !important; }
        .ps-1 { padding-left: 0.25rem !important; }
        .rounded-2 { border-radius: 0.5rem !important; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            line-height: 1.2;
        }
        .bg-white-lt { background: rgba(255, 255, 255, 0.18); color: #ffffff; }
        .bg-blue-lt { background: #dbeafe; color: #1e40af; }
        .bg-success-lt { background: #dcfce7; color: #166534; }
        .bg-warning-lt { background: #fef3c7; color: #92400e; }
        .bg-purple-lt { background: #f3e8ff; color: #6b21a8; }
        .bg-danger-lt { background: #fee2e2; color: #991b1b; }
        .bg-teal-lt { background: #ccfbf1; color: #115e59; }
        .bg-purple { background: #7c3aed !important; color: #ffffff !important; }
        .bg-primary { background: #195b9e !important; color: #ffffff !important; }
        .bg-blue { background: #2563eb !important; color: #ffffff !important; }
        .bg-success { background: #16a34a !important; color: #ffffff !important; }
        .bg-warning { background: #d97706 !important; color: #ffffff !important; }
        .bg-danger { background: #dc2626 !important; color: #ffffff !important; }
        .bg-info { background: #0284c7 !important; color: #ffffff !important; }

        /* Border Utilities */
        .border { border: 1.5px solid #cbd5e1 !important; }
        .border-0 { border: 0 !important; }
        .border-top { border-top: 1px solid #cbd5e1 !important; }
        .border-bottom { border-bottom: 1px solid #e2e8f0 !important; }
        .border-start { border-left: 1px solid #cbd5e1 !important; }
        .border-end { border-right: 1px solid #cbd5e1 !important; }
        .border-primary { border-color: #195b9e !important; }
        .border-success { border-color: #16a34a !important; }
        .border-warning { border-color: #f59e0b !important; }
        .border-danger { border-color: #ef4444 !important; }
        .border-secondary { border-color: #94a3b8 !important; }
        .border-purple { border-color: #a855f7 !important; }
        .border-info { border-color: #0284c7 !important; }

        /* ==========================================================================
           Form Controls & Input Styling (Borders Tegas & Jelas di Mobile)
           ========================================================================== */
        .form-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            line-height: 1.3;
        }

        .form-control,
        .form-select,
        input[type="text"],
        input[type="search"],
        input[type="password"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        input[type="datetime-local"],
        input[type="month"],
        select,
        textarea,
        .item-input-wrap input,
        .item-input-wrap select,
        .item-input-wrap textarea {
            display: block;
            width: 100%;
            padding: 0.52rem 0.75rem !important;
            font-size: 0.85rem !important;
            font-weight: 400;
            line-height: 1.45;
            color: #1e293b !important;
            background-color: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
            box-sizing: border-box !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        /* Khusus Select Dropdown */
        .form-select,
        select,
        .item-input-wrap select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 14px 10px !important;
            padding-right: 2.25rem !important;
            cursor: pointer;
        }

        /* Textarea minimum height */
        textarea,
        textarea.form-control {
            min-height: 70px;
            resize: vertical;
        }

        /* Focus State: Border Biru Khas SIAP */
        .form-control:focus,
        .form-select:focus,
        input[type="text"]:focus,
        input[type="search"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        input[type="datetime-local"]:focus,
        input[type="month"]:focus,
        select:focus,
        textarea:focus,
        .item-input-wrap input:focus,
        .item-input-wrap select:focus,
        .item-input-wrap textarea:focus {
            border-color: #195b9e !important;
            background-color: #ffffff !important;
            outline: 0 !important;
            box-shadow: 0 0 0 3px rgba(25, 91, 158, 0.18) !important;
        }

        /* Ukuran Input Small (.form-control-sm & .form-select-sm) */
        .form-control-sm,
        .form-select-sm {
            padding: 0.42rem 0.65rem !important;
            font-size: 0.8rem !important;
            border-radius: 0.45rem !important;
        }

        /* Input File */
        input[type="file"],
        .form-control[type="file"] {
            padding: 0.4rem 0.65rem !important;
            font-size: 0.78rem !important;
            background: #f8fafc !important;
            border: 1.5px dashed #cbd5e1 !important;
            cursor: pointer;
        }

        /* Input Group (Pencarian & Add-ons) */
        .input-group {
            position: relative;
            display: flex !important;
            flex-wrap: nowrap;
            align-items: stretch;
            width: 100%;
        }
        .input-group > .form-control,
        .input-group > .form-select,
        .input-group > input {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
            border-radius: 0 !important;
        }
        .input-group > :first-child {
            border-top-left-radius: 0.5rem !important;
            border-bottom-left-radius: 0.5rem !important;
        }
        .input-group > :last-child {
            border-top-right-radius: 0.5rem !important;
            border-bottom-right-radius: 0.5rem !important;
        }
        .input-group-text {
            display: flex;
            align-items: center;
            padding: 0.42rem 0.75rem;
            font-size: 0.82rem;
            font-weight: 400;
            line-height: 1.4;
            color: #64748b;
            text-align: center;
            white-space: nowrap;
            background-color: #f8fafc !important;
            border: 1.5px solid #cbd5e1 !important;
        }
        .input-group > .input-group-text:first-child {
            border-right: 0 !important;
            border-top-left-radius: 0.5rem !important;
            border-bottom-left-radius: 0.5rem !important;
        }
        .input-group > .form-control:not(:first-child) {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
        .input-group > .btn {
            border: 1.5px solid #195b9e !important;
            padding: 0.42rem 0.85rem;
            border-radius: 0;
        }
        .input-group > .btn:last-child {
            border-top-right-radius: 0.5rem !important;
            border-bottom-right-radius: 0.5rem !important;
        }
        .input-group > .btn-outline-secondary {
            border: 1.5px solid #cbd5e1 !important;
            border-left: 0 !important;
        }

        /* Radio & Checkbox Native Styling */
        input[type="radio"]:not(.d-none),
        input[type="checkbox"]:not(.d-none) {
            width: 16px !important;
            height: 16px !important;
            accent-color: #195b9e;
            cursor: pointer;
            vertical-align: middle;
            margin-right: 0.35rem;
        }

        /* Placeholder styling */
        ::placeholder {
            color: #94a3b8 !important;
            opacity: 1;
        }

        /* Avatars */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .avatar-sm { width: 34px; height: 34px; font-size: 0.85rem; }
        .avatar-md { width: 44px; height: 44px; font-size: 1rem; }
        .rounded-circle { border-radius: 50% !important; }
        .rounded-3 { border-radius: 0.75rem !important; }
        .rounded-4 { border-radius: 1rem !important; }
        .rounded-pill { border-radius: 9999px !important; }

        /* Buttons */
        .btn {
            display: inline-flex !important;
            width: auto !important;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            box-sizing: border-box !important;
            max-width: 100%;
        }
        .btn-sm { padding: 5px 12px; font-size: 0.78rem; border-radius: 9999px; }
        .btn-primary { background: #195b9e; color: #ffffff; border-color: #195b9e; }
        .btn-outline-secondary { background: transparent; color: #64748b; border-color: #cbd5e1; }

        /* Interactive Card Touch Effect */
        .card-link {
            text-decoration: none !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .card-link:active {
            transform: scale(0.98);
        }

        /* Quick Tasks Sheet Modal (Framework7 Native Bottom Sheet) */
        .sheet-modal {
            z-index: 12500 !important;
        }
        .sheet-backdrop {
            z-index: 12400 !important;
        }
        .sheet-modal.sheet-substitute-picker {
            border-radius: 1.25rem 1.25rem 0 0 !important;
            background: #ffffff !important;
            height: 85vh !important;
            max-height: 85vh !important;
            overflow: hidden !important;
        }
        .sheet-modal.sheet-substitute-picker .sheet-modal-inner {
            height: 100% !important;
            max-height: 85vh !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            touch-action: pan-y !important;
            overscroll-behavior-y: contain !important;
        }
        .sheet-modal.sheet-quick-tasks {
            border-radius: 1.5rem 1.5rem 0 0 !important;
            background: #ffffff !important;
            max-height: 80vh;
        }
        .sheet-modal .toolbar {
            background: #f8fafc !important;
            border-radius: 1.5rem 1.5rem 0 0 !important;
            border-bottom: 1px solid #e2e8f0;
        }
        .sheet-modal-swipe-step {
            width: 36px;
            height: 4px;
            background: #cbd5e1;
            border-radius: 2px;
            margin: 8px auto 4px auto;
        }

        /* Toast Styling */
        .toast {
            border-radius: 1rem !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
        }
        /* Instant Top Page Transition Progress Bar */
        #mobilePageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, #c99a3b 0%, #38bdf8 100%);
            z-index: 999999;
            pointer-events: none;
            opacity: 0;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.15s ease;
        }
        #mobilePageLoader.loading {
            opacity: 1;
            width: 78%;
        }
        /* Hotwire Turbo Progress Bar Styling */
        .turbo-progress-bar {
            height: 3px !important;
            background: linear-gradient(90deg, #c99a3b 0%, #38bdf8 100%) !important;
            z-index: 999999 !important;
        }
    </style>
    @stack('styles')
</head>
<body class="color-theme-blue">
    <!-- Fast Page Transition Indicator -->
    <div id="mobilePageLoader"></div>

    <!-- Framework7 App Root Element -->
    <div id="app">
        <!-- Statusbar Overlay -->
        <div class="statusbar"></div>

        <!-- Main View -->
        <div class="view view-main view-init">
            <div class="page" data-name="mobile-home">
                
                @auth
                <!-- Material Design Top App Bar (Framework7 Navbar) -->
                <div class="navbar mobile-top-bar">
                    <div class="navbar-bg"></div>
                    <div class="navbar-inner" style="padding-left: 6px !important; padding-right: 14px !important;">
                        <div class="left d-flex align-items-center" style="margin-left: 2px; margin-right: 10px; gap: 4px;">
                            @hasSection('back_url')
                                @php
                                    $backUrlVal = trim($__env->yieldContent('back_url'));
                                    $isHistoryBack = in_array($backUrlVal, ['back', 'history', 'previous', 'javascript:void(0);']);
                                @endphp
                                @if($isHistoryBack)
                                    <a href="javascript:void(0);" onclick="if (window.history.length > 1 && (!document.referrer || document.referrer.indexOf(window.location.host) !== -1)) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; } return false;" class="link icon-only external text-white d-flex align-items-center justify-content-center p-0" aria-label="Kembali" style="min-width: 28px; width: 28px; height: 38px;">
                                        <i class="ti ti-arrow-left fs-2"></i>
                                    </a>
                                @else
                                    <a href="@yield('back_url')" @hasSection('back_onclick') onclick="@yield('back_onclick')" @endif class="link icon-only external text-white d-flex align-items-center justify-content-center p-0" aria-label="Kembali" style="min-width: 28px; width: 28px; height: 38px;">
                                        <i class="ti ti-arrow-left fs-2"></i>
                                    </a>
                                @endif
                            @endif
                            @php
                                $appSchoolSetting = \App\Models\SchoolSetting::getSetting();
                                $appSchoolLogo = $appSchoolSetting->logo_path;
                            @endphp
                            @if($appSchoolLogo)
                                <img src="{{ asset('storage/' . $appSchoolLogo) }}" alt="Logo" class="flex-shrink-0" style="max-height: 35px; max-width: 35px; object-fit: contain;">
                            @else
                                <img src="{{ asset('icons/icon-192x192.png') }}" alt="Logo" class="flex-shrink-0" style="max-height: 35px; max-width: 35px; object-fit: contain;" onerror="this.src='/favicon.ico'">
                            @endif
                        </div>
                        <div class="title text-white overflow-hidden" style="min-width: 0;">
                            @hasSection('page_title')
                                <div class="fw-bold lh-1 fs-3 text-truncate" style="font-size: 1.05rem; letter-spacing: -0.01em;">@yield('page_title')</div>
                                @hasSection('page_subtitle')
                                    <div class="text-white-50 small text-truncate" style="font-size: 0.72rem; font-weight: 500; margin-top: 2px;">@yield('page_subtitle')</div>
                                @endif
                            @else
                                <div class="fw-bold lh-1 fs-3 text-truncate" style="font-size: 1.05rem; letter-spacing: -0.01em;">SIAP SMK PGRI KRAS</div>
                                @php
                                    $appSchoolSetting = $appSchoolSetting ?? \App\Models\SchoolSetting::getSetting();
                                    $appAyText = 'Tahun Pelajaran ' . ($appSchoolSetting->academic_year ?? '2026/2027');
                                @endphp
                                <div class="text-white-50 small text-truncate" style="font-size: 0.72rem; font-weight: 500; margin-top: 2px;">{{ $appAyText }}</div>
                            @endif
                        </div>
                        <div class="right d-flex align-items-center gap-1.5" style="margin-right: -4px;">
                            @php
                                $mobileNotifications = collect();
                                try {
                                    if (auth()->check()) {
                                        $mobileNotifications = \App\Services\NotificationService::getUserNotifications(auth()->user());
                                    }
                                } catch (\Throwable $e) {
                                    $mobileNotifications = collect();
                                }
                                $hasNotification = $mobileNotifications->isNotEmpty();
                                $notificationCount = $mobileNotifications->count();
                                $currentUser = auth()->user();
                                $avatarInitial = strtoupper(substr($currentUser?->name ?? 'U', 0, 1));
                            @endphp

                            <!-- Foto Profil User -->
                            <a href="{{ route('profile') }}" class="external text-decoration-none d-inline-flex align-items-center justify-content-center" title="Profil Saya" style="width: 32px; height: 32px;">
                                @if(!empty($currentUser?->avatar) && file_exists(public_path('storage/' . $currentUser->avatar)))
                                    <span class="avatar avatar-sm rounded-circle shadow-xs" style="width: 28px; height: 28px; background-image: url('{{ asset('storage/' . $currentUser->avatar) }}'); background-size: cover; background-position: center; border: 1.5px solid rgba(255, 255, 255, 0.85);"></span>
                                @else
                                    <span class="avatar avatar-sm rounded-circle shadow-xs bg-white text-primary fw-bold" style="width: 28px; height: 28px; font-size: 0.75rem; border: 1.5px solid rgba(255, 255, 255, 0.85); display: inline-flex; align-items: center; justify-content: center;">
                                        {{ $avatarInitial }}
                                    </span>
                                @endif
                            </a>

                            <!-- Tombol Lonceng Notifikasi (Digeser ke Pinggir Kanan) -->
                            <a href="javascript:void(0)" onclick="openMobileNotificationSheet(); return false;" class="link icon-only external text-white" title="Notifikasi" style="position: relative; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px;">
                                <i class="ti ti-bell fs-2"></i>
                                @if($hasNotification)
                                    <span style="position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; background-color: #ef4444; border: 1.5px solid #ffffff; border-radius: 50%;"></span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>
                @endauth

                <!-- Main Mobile Content -->
                <div class="page-content px-2">

                    <!-- Flash Alerts / Toasts Handled in JS and Inline Fallback -->
                    @if(session('success'))
                        <div class="card border-0 mb-3" style="background: #ecfdf5; border-left: 4px solid #16a34a !important;">
                            <div class="card-body py-2 px-3 d-flex align-items-center text-success">
                                <i class="ti ti-circle-check fs-2 me-2 flex-shrink-0"></i>
                                <div class="small fw-semibold">{{ session('success') }}</div>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="card border-0 mb-3" style="background: #fef2f2; border-left: 4px solid #dc2626 !important;">
                            <div class="card-body py-2 px-3 d-flex align-items-center text-danger">
                                <i class="ti ti-alert-triangle fs-2 me-2 flex-shrink-0"></i>
                                <div class="small fw-semibold">{{ session('error') }}</div>
                            </div>
                        </div>
                    @endif

                    @yield('content')
                </div>

                @auth
                <!-- Material Design Bottom Tabbar (Framework7 Toolbar with 5 Pinned Tabs) -->
                <div class="toolbar toolbar-bottom tabbar tabbar-icons mobile-bottom-nav">
                    <div class="toolbar-inner">
                        <a href="{{ route('dashboard') }}" class="tab-link external mobile-nav-item {{ request()->routeIs('dashboard') || request()->is('/') ? 'tab-link-active active' : '' }}">
                            <i class="ti ti-home-2"></i>
                            <span class="tabbar-label">Beranda</span>
                        </a>
                        <a href="{{ route('attendance.subject.index') }}" class="tab-link external mobile-nav-item {{ request()->routeIs('attendance.subject.*') ? 'tab-link-active active' : '' }}">
                            <i class="ti ti-book"></i>
                            <span class="tabbar-label">KBM</span>
                        </a>
                        <a href="{{ route('attendance.geotag.index') }}" class="tab-link external mobile-nav-item mobile-nav-action {{ request()->routeIs('attendance.geotag.*') ? 'tab-link-active active' : '' }}">
                            <div class="action-circle">
                                <i class="ti ti-map-pin"></i>
                            </div>
                            <span class="tabbar-label">Absen</span>
                        </a>
                        <a href="{{ route('tasks') }}" class="tab-link external mobile-nav-item {{ request()->routeIs('tasks*') ? 'tab-link-active active' : '' }}">
                            <i class="ti ti-layout-grid"></i>
                            <span class="tabbar-label">Tugas</span>
                        </a>
                        <a href="{{ route('profile') }}" class="tab-link external mobile-nav-item {{ request()->routeIs('profile*') ? 'tab-link-active active' : '' }}">
                            <i class="ti ti-user"></i>
                            <span class="tabbar-label">Profil</span>
                        </a>
                    </div>
                </div>
                @endauth
                
                @auth
                <!-- MODAL BOTTOM SHEET: PUSAT PEMBERITAHUAN & TUGAS PERAN -->
                <div class="sheet-modal" id="sheet-mobile-notifications" style="height: auto; max-height: 85vh; border-radius: 20px 20px 0 0; background: #ffffff;">
                    <div class="sheet-modal-inner overflow-auto" style="max-height: 85vh; padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 16px)) !important;">
                        <!-- Header Standar Seragam Sesuai Pengesahan Supervisi & Modal SIAP (Tinggi Luwes & Nyaman) -->
                        <div class="d-flex align-items-center justify-content-between px-3.5 py-3 border-bottom sticky-top" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.95) 0%, rgba(25, 91, 158, 0.9) 100%); border-radius: 20px 20px 0 0; z-index: 10; min-height: 64px;">
                            <div class="d-flex align-items-center gap-2.5">
                                <span class="avatar avatar-sm bg-white text-primary rounded-circle flex-shrink-0" style="width: 36px; height: 36px;">
                                    <i class="ti ti-bell fs-2"></i>
                                </span>
                                <div>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <div class="fw-bold text-white fs-3 lh-sm">Pemberitahuan &amp; Tugas</div>
                                        @if(($notificationCount ?? 0) > 0)
                                            <span class="badge bg-danger rounded-pill px-2 py-0.5 text-white" style="font-size: 0.68rem; font-weight: 700; line-height: 1;">{{ $notificationCount }}</span>
                                        @endif
                                    </div>
                                    <div class="text-white-50 small mt-1" style="font-size: 0.74rem;">
                                        {{ ($notificationCount ?? 0) > 0 ? $notificationCount . ' item memerlukan perhatian' : 'Semua tugas telah selesai' }}
                                    </div>
                                </div>
                            </div>
                            <a href="javascript:void(0)" class="sheet-close text-white opacity-75 text-decoration-none d-inline-flex align-items-center justify-content-center p-0" style="font-size: 1.8rem; width: 34px; height: 34px; line-height: 1;" onclick="closeMobileNotificationSheet(); return false;">&times;</a>
                        </div>

                        <!-- Daftar Notifikasi -->
                        <div class="p-3">
                            @if(isset($mobileNotifications) && $mobileNotifications->isNotEmpty())
                                <div class="d-flex flex-column gap-2.5">
                                    @foreach($mobileNotifications as $notif)
                                        <a href="{{ $notif['url'] ?? 'javascript:void(0)' }}" class="card border text-decoration-none shadow-none p-2.5 rounded-3 external" style="background: #ffffff; border-color: #e2e8f0 !important;" onclick="closeMobileNotificationSheet();">
                                            <div class="d-flex align-items-start gap-2.5">
                                                <div class="avatar avatar-sm rounded-circle flex-shrink-0 bg-light d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; border: 1px solid #e2e8f0;">
                                                    <i class="{{ $notif['icon'] ?? 'ti ti-info-circle text-primary' }} fs-2"></i>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                                        <div class="fw-bold text-dark small text-truncate" style="font-size: 0.84rem;">
                                                            {{ $notif['title'] }}
                                                        </div>
                                                        @if(!empty($notif['badge']))
                                                            <span class="badge {{ $notif['badge_class'] ?? 'bg-primary-lt text-primary' }} flex-shrink-0" style="font-size: 0.68rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                                                {{ $notif['badge'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-muted small text-break" style="font-size: 0.76rem; line-height: 1.35;">
                                                        {{ $notif['message'] }}
                                                    </div>
                                                </div>
                                                <div class="text-muted-lt align-self-center ps-1">
                                                    <i class="ti ti-chevron-right text-muted fs-3"></i>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <div class="avatar avatar-lg bg-teal-lt rounded-circle mx-auto mb-3" style="width: 56px; height: 56px;">
                                        <i class="ti ti-circle-check fs-1 text-teal"></i>
                                    </div>
                                    <div class="fw-bold text-dark fs-3 mb-1">Tidak Ada Tugas Baru</div>
                                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">
                                        Semua tugas harian, persetujuan, dan pemberitahuan Anda sudah up to date.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endauth
                
                @stack('modals')

            </div>
        </div>
    </div>

    <!-- Local Vendor JS (Framework7 Bundle) -->
    <script src="{{ asset('vendor/framework7/framework7-bundle.min.js') }}"></script>

    <script>
        // Inisialisasi Framework7 dengan Tema Material Design (MD)
        const app = new Framework7({
            el: '#app',
            name: 'SIAP GTK',
            theme: 'md', // True Material Design for Android APK
            darkMode: false,
            touch: {
                materialRipple: true,
                activeState: true,
            },
            view: {
                browserHistory: false,
            },
            sheet: {
                swipeToClose: true,
                backdrop: true,
            }
        });

        // Kontrol Sheet Modal Notifikasi
        function openMobileNotificationSheet() {
            if (typeof app !== 'undefined' && app && app.sheet) {
                app.sheet.open('#sheet-mobile-notifications');
            } else if (window.f7 && window.f7.sheet) {
                window.f7.sheet.open('#sheet-mobile-notifications');
            } else {
                const el = document.getElementById('sheet-mobile-notifications');
                if (el) el.classList.add('modal-in');
            }
        }

        function closeMobileNotificationSheet() {
            if (typeof app !== 'undefined' && app && app.sheet) {
                app.sheet.close('#sheet-mobile-notifications');
            } else if (window.f7 && window.f7.sheet) {
                window.f7.sheet.close('#sheet-mobile-notifications');
            } else {
                const el = document.getElementById('sheet-mobile-notifications');
                if (el) el.classList.remove('modal-in');
            }
        }

        // Pastikan Framework7 tidak mencegat link biasa & berikan respon instan
        function setupMobileNavigation() {
            const loader = document.getElementById('mobilePageLoader');

            document.querySelectorAll('a:not([data-sheet]):not([href^="#"])').forEach(function(el) {
                el.classList.add('external');
            });

            document.querySelectorAll('form').forEach(function(el) {
                el.classList.add('external');
            });

            // Respon instan saat tab bawah diketuk (0ms visual feedback)
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            document.querySelectorAll('.mobile-bottom-nav .tab-link').forEach(function(tab) {
                const href = (tab.getAttribute('href') || '').replace(/\/+$/, '');
                if (href && currentPath === href) {
                    tab.classList.add('tab-link-active', 'active');
                }

                tab.addEventListener('click', function() {
                    document.querySelectorAll('.mobile-bottom-nav .tab-link').forEach(function(t) {
                        t.classList.remove('tab-link-active', 'active');
                    });
                    this.classList.add('tab-link-active', 'active');
                    if (loader) {
                        loader.classList.add('loading');
                    }
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupMobileNavigation);
        } else {
            setupMobileNavigation();
        }

        // Registrasi PWA Service Worker (Precache Vendor Lokal)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').catch(function(err) {
                    console.warn('SIAP PWA: SW registration error: ', err);
                });
            });
        }
    </script>

    @stack('scripts')
</body>
</html>