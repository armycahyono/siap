<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Rekapitulasi Presensi - {{ $setting->school_name ?? 'SMK PGRI KRAS' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 18mm 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10.5pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Tampilan Layar (Screen / Desktop Preview - Format Lembar Kertas A4) */
        @media screen {
            body {
                background-color: #525659;
                min-height: 100vh;
            }
            .a4-sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 25px auto 40px auto;
                padding: 12mm 15mm 18mm 15mm;
                background: #ffffff;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), 0 1px 4px rgba(0, 0, 0, 0.2);
                border-radius: 2px;
                position: relative;
            }
            @media (max-width: 220mm) {
                .a4-sheet {
                    width: 96%;
                    margin: 15px auto;
                    padding: 8mm 10mm;
                }
            }
        }

        /* Tampilan Saat Dicetak (Physical Paper / Print Dialog) */
        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .a4-sheet {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: transparent !important;
            }
        }

        /* Topbar Navigasi & Tombol Cetak */
        .no-print-bar {
            background: #2b2d30;
            color: #fff;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
        }
        .no-print-bar .preview-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        .no-print-bar .badge-a4 {
            background: #198754;
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .no-print-bar .btn-group {
            display: flex;
            gap: 10px;
        }
        .no-print-bar button {
            cursor: pointer;
            border: none;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .no-print-bar .btn-print {
            background: #20c997;
            color: #fff;
        }
        .no-print-bar .btn-print:hover {
            background: #0ca678;
        }
        .no-print-bar .btn-close {
            background: #495057;
            color: #f8f9fa;
        }
        .no-print-bar .btn-close:hover {
            background: #343a40;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h2 {
            margin: 0;
            font-size: 11pt;
            font-weight: 600;
            text-transform: uppercase;
            line-height: 1.25;
        }
        .header h1 {
            margin: 4px 0 2px;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0 0;
            font-size: 9pt;
            font-style: normal;
        }
        .title-box {
            text-align: center;
            margin-bottom: 12px;
        }
        .title-box h3 {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .title-box h4 {
            margin: 2px 0 0;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .title-box p {
            margin: 4px 0 0;
            font-size: 10pt;
        }
        .profile-card {
            margin-bottom: 12px;
            border: 1px solid #000;
            padding: 6px 10px;
            font-size: 9.5pt;
        }
        .profile-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin: 0;
        }
        .profile-table td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9.5pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        table.data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .signature-section {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            border: none;
        }
        .signature-table td {
            border: none;
            padding: 0;
            vertical-align: top;
            width: 50%;
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div class="preview-info">
            <span>📄 Pratinjau Dokumen Cetak</span>
            <span class="badge-a4">KERTAS A4 PORTRAIT (210 × 297 mm)</span>
        </div>
        <div class="btn-group">
            <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Dokumen Ini (Print)</button>
            <button type="button" class="btn-close" onclick="window.close()">✕ Tutup</button>
        </div>
    </div>

    <div class="a4-sheet">

    <!-- KOP SURAT RESMI -->
    <div class="header">
        @if(!empty($setting->foundation_name))
            @foreach(preg_split('/\r\n|\r|\n/', trim($setting->foundation_name)) as $line)
                @if(trim($line))
                    <h2>{{ strtoupper(trim($line)) }}</h2>
                @endif
            @endforeach
        @endif
        <h1>{{ strtoupper($setting->school_name ?? 'SMK PGRI KRAS') }}</h1>
        <p>{{ $setting->school_address ?? 'Jl. Raya Kras No. 12, Kec. Kras, Kab. Kediri, Jawa Timur' }} &bull; Telp: {{ $setting->school_phone ?? '(0354) 771234' }}</p>
        <p>NPSN: {{ $setting->school_npsn ?? '20534431' }} &bull; Website: {{ $setting->school_website ?? 'https://smkpgrikras.sch.id' }} &bull; Email: {{ $setting->school_email ?? 'smkpgrikras@gmail.com' }}</p>
    </div>

    <!-- JUDUL LAPORAN -->
    <div class="title-box">
        <h3>LAPORAN REKAPITULASI PRESENSI</h3>
        <h4>{{ match($userType ?? 'all') { 'teacher' => 'GURU DAN TENAGA KEPENDIDIKAN', 'student' => 'SISWA', default => 'SEMUA PENGGUNA' } }}</h4>
        <p>Periode : {{ $periodLabel }}</p>
    </div>

    <!-- PROFIL INDIVIDU (JIKA MEMILIH GURU / SISWA TERTENTU) -->
    @if($selectedProfile)
        <div class="profile-card">
            <table class="profile-table">
                <tr>
                    <td style="width: 120px;"><strong>Nama Lengkap</strong></td>
                    <td style="width: 15px;">:</td>
                    <td><strong>{{ $selectedProfile->name }}</strong></td>
                    <td style="width: 130px;"><strong>Total Hari Hadir</strong></td>
                    <td style="width: 15px;">:</td>
                    <td><strong>{{ $recapItems->count() }} Hari</strong></td>
                </tr>
                <tr>
                    <td><strong>NIP / NIGS / PIN</strong></td>
                    <td>:</td>
                    <td>{{ $selectedProfile->pin_nip }}</td>
                    <td><strong>Kategori</strong></td>
                    <td>:</td>
                    <td>{{ $selectedProfile->category }} ({{ $selectedProfile->position }})</td>
                </tr>
            </table>
        </div>
    @endif

    <!-- TABEL REKAPITULASI PRESENSI (1 BARIS PER ORANG PER HARI) -->
    <table class="data-table">
        @if($selectedProfile)
            <!-- Tabel Format Individu -->
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 70px;">Hari</th>
                    <th style="width: 85px;">Tanggal</th>
                    <th style="width: 80px;">Jam Masuk</th>
                    <th style="width: 80px;">Jam Pulang</th>
                    <th>Status</th>
                    <th style="width: 85px;">Metode</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recapItems as $idx => $row)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $row->day_name }}</td>
                        <td class="text-center">{{ $row->date_formatted }}</td>
                        <td class="text-center">{{ $row->jam_masuk }}</td>
                        <td class="text-center">{{ $row->jam_pulang }}</td>
                        <td>{{ $row->status }}</td>
                        <td class="text-center">{{ $row->verify_type }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px;">
                            <em>Tidak ada rekaman presensi pada periode tanggal yang dipilih.</em>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        @else
            <!-- Tabel Format Rekapitulasi Umum (Semua Pengguna) -->
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 65px;">Hari</th>
                    <th style="width: 80px;">Tanggal</th>
                    <th style="width: 55px;">PIN</th>
                    <th>Nama Lengkap</th>
                    <th style="width: 100px;">Kategori</th>
                    <th style="width: 75px;">Jam Masuk</th>
                    <th style="width: 75px;">Jam Pulang</th>
                    <th style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recapItems as $idx => $row)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $row->day_name }}</td>
                        <td class="text-center">{{ $row->date_formatted }}</td>
                        <td class="text-center">{{ $row->pin }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="text-center">{{ $row->category }}</td>
                        <td class="text-center">{{ $row->jam_masuk }}</td>
                        <td class="text-center">{{ $row->jam_pulang }}</td>
                        <td class="text-center">{{ $row->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 20px;">
                            <em>Tidak ada rekaman rekapitulasi presensi pada periode tanggal yang dipilih.</em>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        @endif
    </table>

    <!-- TANDA TANGAN -->
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td style="text-align: center;">
                    Mengetahui,<br>
                    Kepala Sekolah<br><br><br><br><br>
                    <strong><u>{{ $setting->principal_name ?? 'ENDANG PATMOWATI, S.Pd' }}</u></strong><br>
                    @if(!empty($setting->principal_nip))
                        NIP. {{ $setting->principal_nip }}
                    @endif
                </td>
                <td style="text-align: center;">
                    Kediri, {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}<br>
                    Waka Kurikulum<br><br><br><br><br>
                    <strong><u>{{ $setting->curriculum_head_name ?? 'ROKHIM, S.Pd.' }}</u></strong><br>
                    @if(!empty($setting->curriculum_head_nip))
                        NIP. {{ $setting->curriculum_head_nip }}
                    @endif
                </td>
            </tr>
        </table>
    </div>
    </div><!-- /.a4-sheet -->

</body>
</html>
