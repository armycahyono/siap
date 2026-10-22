<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Rekapitulasi Presensi Siswa - {{ $selectedClassroom ? 'Kelas '.$selectedClassroom->name : 'Semua Kelas' }}</title>
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
            font-size: 10pt;
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
            margin-bottom: 14px;
        }
        .title-box h3 {
            margin: 0;
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .title-box h4 {
            margin: 2px 0 0;
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .title-box p {
            margin: 3px 0 0;
            font-size: 9.5pt;
        }
        .person-section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .person-header {
            border: 1px solid #000;
            background-color: #f8f9fa;
            padding: 4px 8px;
            margin-bottom: 0;
            font-size: 9.5pt;
        }
        .person-header-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .person-header-table td {
            border: none;
            padding: 1px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 9pt;
            border-top: none;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 3px 5px;
        }
        table.data-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
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
        @if(($printType ?? '') === 'status')
            <h3>LAPORAN REKAPITULASI STATUS KEHADIRAN SISWA</h3>
        @elseif(($printType ?? '') === 'detail')
            <h3>LAPORAN RINCIAN PRESENSI INDIVIDU SISWA</h3>
        @else
            <h3>LAPORAN REKAPITULASI PRESENSI SISWA</h3>
        @endif
        @if(($printType ?? '') === 'detail' && count($people) === 1)
            <h4>{{ strtoupper($people->first()->name) }} (KELAS {{ strtoupper($people->first()->classroom_name) }})</h4>
        @else
            <h4>{{ $selectedClassroom ? 'KELAS '.strtoupper($selectedClassroom->name) : 'SEMUA KELAS' }}</h4>
        @endif
        <p>Periode : {{ $periodLabel }}</p>
    </div>

    @if(($printType ?? '') !== 'detail')
        <!-- TABEL REKAPITULASI STATUS KEHADIRAN SISWA -->
        <div style="margin-bottom: 18px; page-break-inside: auto;">
            @if(empty($printType) || $printType === 'all')
                <div style="font-weight: bold; font-size: 10pt; margin-bottom: 6px; text-transform: uppercase;">
                    I. REKAPITULASI STATUS KEHADIRAN SISWA {{ $selectedClassroom ? ' - KELAS '.strtoupper($selectedClassroom->name) : '' }}
                </div>
            @endif
            <table class="data-table" style="margin-bottom: 6px;">
                <thead>
                    <tr>
                        <th style="width: 30px;">NO</th>
                        <th style="width: 85px;">ID</th>
                        <th>NAMA</th>
                        <th style="width: 40px;">H</th>
                        <th style="width: 40px;">S</th>
                        <th style="width: 40px;">I</th>
                        <th style="width: 40px;">A</th>
                        <th style="width: 40px;">P</th>
                        <th style="width: 40px;">T</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($people as $idx => $p)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td class="text-center">{{ $p->pin }}</td>
                            <td>{{ $p->name }}</td>
                            <td class="text-center">{{ $p->hadir }}</td>
                            <td class="text-center">{{ $p->sakit }}</td>
                            <td class="text-center">{{ $p->izin }}</td>
                            <td class="text-center">{{ $p->alfa }}</td>
                            <td class="text-center">{{ $p->pulang }}</td>
                            <td class="text-center">{{ $p->terlambat }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">Tidak ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($people) > 0)
                    <tfoot>
                        <tr style="background-color: #f0f0f0; font-weight: bold;">
                            <td colspan="3" class="text-end">TOTAL :</td>
                            <td class="text-center">{{ $summary['total_hadir'] }}</td>
                            <td class="text-center">{{ $summary['total_sakit'] }}</td>
                            <td class="text-center">{{ $summary['total_izin'] }}</td>
                            <td class="text-center">{{ $summary['total_alfa'] }}</td>
                            <td class="text-center">{{ $summary['total_pulang'] }}</td>
                            <td class="text-center">{{ $summary['total_terlambat'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            <!-- Informasi Singkatan -->
            <div style="font-size: 8.5pt; color: #222; margin-top: 4px; margin-bottom: 14px;">
                <strong>Informasi :</strong><br>
                H = Hadir, S = Sakit, I = Izin, A = Alfa, P = Pulang Cepat, T = Terlambat
            </div>
        </div>
    @endif

    @if(($printType ?? '') !== 'status')
        @if(empty($printType) || $printType === 'all')
            <div style="font-weight: bold; font-size: 10pt; margin-bottom: 8px; text-transform: uppercase;">
                II. RINCIAN PRESENSI HARIAN PER SISWA
            </div>
        @endif

        <!-- DAFTAR PRESENSI PER SISWA -->
        @forelse($people as $idx => $person)
            <div class="person-section">
                <div class="person-header">
                    <table class="person-header-table">
                        <tr>
                            <td style="width: 65%;">
                                <strong>{{ $idx + 1 }}. Nama Siswa: {{ $person->name }}</strong>
                            </td>
                            <td style="width: 35%; text-align: right;">
                                <strong>Periode: {{ $periodLabel }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 8.5pt;">
                                NISN / ID: {{ $person->pin }} &bull; Kelas: {{ $person->classroom_name }}
                            </td>
                            <td style="font-size: 8.5pt; text-align: right;">
                                Total Kehadiran: <strong>{{ $person->total_days }} Hari</strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="font-size: 8pt; color: #333; padding-top: 2px;">
                                Rekap Status: Hadir: <strong>{{ $person->hadir }}</strong> &bull; Sakit: <strong>{{ $person->sakit }}</strong> &bull; Izin: <strong>{{ $person->izin }}</strong> &bull; Alfa: <strong>{{ $person->alfa }}</strong> &bull; Pulang Cepat: <strong>{{ $person->pulang }}</strong> &bull; Terlambat: <strong>{{ $person->terlambat }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 25px;">No</th>
                            <th style="width: 70px;">Hari</th>
                            <th style="width: 80px;">Tanggal</th>
                            <th style="width: 80px;">Jam Masuk</th>
                            <th style="width: 80px;">Jam Pulang</th>
                            <th style="width: 110px;">Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($person->records as $rIdx => $r)
                            <tr>
                                <td class="text-center">{{ $rIdx + 1 }}</td>
                                <td class="text-center">{{ $r->day_name }}</td>
                                <td class="text-center">{{ $r->date_formatted }}</td>
                                <td class="text-center">{{ $r->jam_masuk }}</td>
                                <td class="text-center">{{ $r->jam_pulang }}</td>
                                <td class="text-center">{{ $r->status }}</td>
                                <td>{{ $r->keterangan ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 10px;">
                                    <em>Tidak ada catatan presensi pada periode ini.</em>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <div style="text-align: center; padding: 40px;">
                <em>Tidak ada data siswa yang memenuhi kriteria pilihan.</em>
            </div>
        @endforelse
    @endif

    <!-- TANDA TANGAN RESMI -->
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
                    @if($selectedClassroom && $selectedClassroom->homeroomTeacher)
                        Wali Kelas {{ $selectedClassroom->name }}<br><br><br><br><br>
                        <strong><u>{{ $selectedClassroom->homeroomTeacher->name }}</u></strong><br>
                        @if(!empty($selectedClassroom->homeroomTeacher->nigs_fix ?: $selectedClassroom->homeroomTeacher->nigs))
                            NIGS. {{ $selectedClassroom->homeroomTeacher->nigs_fix ?: $selectedClassroom->homeroomTeacher->nigs }}
                        @endif
                    @else
                        Waka Kesiswaan<br><br><br><br><br>
                        <strong><u>{{ $setting->student_affairs_head_name ?? 'Drs. SUWITO' }}</u></strong><br>
                        @if(!empty($setting->student_affairs_head_nip))
                            NIP. {{ $setting->student_affairs_head_nip }}
                        @endif
                    @endif
                </td>
            </tr>
        </table>
    </div>
    </div><!-- /.a4-sheet -->

</body>
</html>
