<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>Verifikasi Keaslian Dokumen Supervisi — {{ $setting->school_name }}</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Source Sans 3', sans-serif;
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-center min-vh-100 py-4">
    <div class="container container-tight py-4" style="max-width: 650px;">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            @if($supervision && $supervision->approved_by_principal)
            <!-- Verified Header -->
            <div class="card-status-top bg-success"></div>
            <div class="card-body p-4 p-md-5 text-center">
                <div class="avatar avatar-xl bg-success-lt text-success rounded-circle mx-auto mb-3">
                    <i class="ti ti-circle-check fs-1"></i>
                </div>
                <span class="badge bg-success text-white px-3 py-1 text-uppercase fw-bold mb-2">
                    Dokumen Resmi Terverifikasi
                </span>
                <h2 class="fw-bold text-dark mb-1">Sistem Supervisi Pembelajaran</h2>
                <div class="text-muted small mb-4">{{ $setting->school_name }}</div>

                <div class="p-3 bg-light rounded-3 text-start mb-4 border">
                    <div class="row g-2 small">
                        <div class="col-4 text-muted">Nomor Laporan:</div>
                        <div class="col-8 fw-bold font-monospace text-primary">{{ $supervision->report_number }}</div>

                        <div class="col-4 text-muted">Nama Guru:</div>
                        <div class="col-8 fw-bold text-dark">{{ $supervision->teacher->name }}</div>

                        <div class="col-4 text-muted">NIP:</div>
                        <div class="col-8 text-dark">{{ $supervision->teacher->nip ?: '-' }}</div>

                        <div class="col-4 text-muted">Mata Pelajaran:</div>
                        <div class="col-8 text-dark">{{ $supervision->subject }} ({{ $supervision->class_name }})</div>

                        <div class="col-4 text-muted">Tanggal Supervisi:</div>
                        <div class="col-8 text-dark">{{ $supervision->schedule_date->translatedFormat('d F Y') }}</div>

                        <div class="col-4 text-muted">Hasil Observasi:</div>
                        <div class="col-8 fw-bold text-success">{{ $supervision->percentage }}% — {{ $supervision->predicate }}</div>

                        <div class="col-4 text-muted">Supervisor:</div>
                        <div class="col-8 text-dark">{{ $supervision->supervisor->name }}</div>

                        <div class="col-4 text-muted">Disahkan Oleh:</div>
                        <div class="col-8 fw-bold text-dark">{{ $setting->principal_name }} (Kepala Sekolah)</div>

                        <div class="col-4 text-muted">Waktu Pengesahan:</div>
                        <div class="col-8 text-dark">{{ $supervision->principal_approved_at?->translatedFormat('d F Y, H:i') }} WIB</div>
                    </div>
                </div>

                <div class="alert alert-success py-2 small mb-0">
                    <i class="ti ti-shield-check me-1"></i> Dokumen ini sah dan terdaftar resmi pada pangkalan data kurikulum {{ $setting->school_name }}.
                </div>
            </div>
            @else
            <!-- Invalid / Not Approved Yet -->
            <div class="card-status-top bg-danger"></div>
            <div class="card-body p-4 p-md-5 text-center">
                <div class="avatar avatar-xl bg-danger-lt text-danger rounded-circle mx-auto mb-3">
                    <i class="ti ti-alert-triangle fs-1"></i>
                </div>
                <span class="badge bg-danger text-white px-3 py-1 text-uppercase fw-bold mb-2">
                    Dokumen Tidak Valid / Belum Disahkan
                </span>
                <h2 class="fw-bold text-dark mb-1">Verifikasi Gagal</h2>
                <p class="text-muted small mt-2">
                    Kode barcode atau dokumen supervisi tidak ditemukan dalam sistem resmi atau belum melalui tahap pengesahan Kepala Sekolah.
                </p>
                <div class="font-monospace small text-muted p-2 bg-light rounded mt-3">
                    Token: {{ $token }}
                </div>
            </div>
            @endif

            <div class="card-footer bg-white text-center py-3">
                <small class="text-muted">&copy; {{ date('Y') }} {{ $setting->school_name }} — Penjaminan Mutu Pendidikan</small>
            </div>
        </div>
    </div>
</body>
</html>
