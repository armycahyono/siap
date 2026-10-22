<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Laporan Piket ({{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }})</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10.5pt;
            color: #000;
            margin: 20px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header h2 { margin: 0; font-size: 15pt; text-transform: uppercase; font-weight: bold; }
        .header h3 { margin: 2px 0; font-size: 13pt; font-weight: bold; }
        .header p { margin: 2px 0; font-size: 9pt; }
        .title-box { text-align: center; margin-bottom: 15px; }
        .title-box h4 { margin: 0; text-transform: uppercase; font-size: 12pt; text-decoration: underline; }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9.5pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        table.data-table th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .text-center { text-align: center; }
        .signature-table {
            width: 100%;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-table td {
            vertical-align: top;
            width: 50%;
            text-align: center;
            font-size: 10pt;
        }
        @media print {
            body { margin: 10mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0066cc; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            Cetak Dokumen
        </button>
    </div>

    <!-- Kop Surat -->
    <div class="header">
        <h2>{{ $setting->school_name ?? 'SMK PGRI KRAS' }}</h2>
        <h3>LAPORAN REKAPITULASI OPERASIONAL PIKET</h3>
        <p>{{ $setting->school_address ?? 'Jl. Raya Kras, Kediri, Jawa Timur' }} &bull; Telp: {{ $setting->school_phone ?? '-' }} &bull; Email: {{ $setting->school_email ?? '-' }}</p>
    </div>

    <div class="title-box">
        <h4>
            REKAPITULASI {{ strtoupper(str_replace('_', ' ', $tab)) }} PIKET
        </h4>
        <div style="font-size: 10pt; margin-top: 3px;">
            Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}</strong> s.d. <strong>{{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}</strong>
            @if($classroom)
                &bull; Kelas: <strong>{{ $classroom->name }}</strong>
            @endif
        </div>
    </div>

    @if($tab === 'permits')
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 15%;">No. Izin</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 20%;">Nama Siswa</th>
                    <th style="width: 10%;">Kelas</th>
                    <th style="width: 15%;">Jenis Izin</th>
                    <th style="width: 10%;">Waktu</th>
                    <th style="width: 13%;">Petugas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permits as $idx => $p)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $p->permit_number }}</td>
                        <td class="text-center">{{ $p->permit_date?->format('d/m/Y') }}</td>
                        <td>{{ $p->student_name }}</td>
                        <td class="text-center">{{ $p->classroom?->name ?? '-' }}</td>
                        <td>{{ $p->permit_type_label }}</td>
                        <td class="text-center">{{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }}</td>
                        <td>{{ $p->givenBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">Nihil.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($tab === 'inval')
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 10%;">Kelas</th>
                    <th style="width: 23%;">Guru Berhalangan</th>
                    <th style="width: 15%;">Mapel & Jam</th>
                    <th style="width: 23%;">Guru Inval</th>
                    <th style="width: 12%;">Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invals as $idx => $inv)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $inv->journal?->date?->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $inv->classroom?->name ?? '-' }}</td>
                        <td>{{ $inv->absentTeacher?->name ?? '-' }}</td>
                        <td>{{ $inv->subject?->name ?? '-' }} (Jam {{ $inv->period_start }}-{{ $inv->period_end }})</td>
                        <td>{{ $inv->invalTeacher?->name ?? 'Belum ada' }}</td>
                        <td>{{ $inv->reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Nihil.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($tab === 'incidents')
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 20%;">Nama Siswa</th>
                    <th style="width: 10%;">Kelas</th>
                    <th style="width: 15%;">Kategori</th>
                    <th style="width: 23%;">Uraian Kasus</th>
                    <th style="width: 15%;">Tindak Lanjut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incidents as $idx => $inc)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $inc->incident_date?->format('d/m/Y') }}</td>
                        <td>{{ $inc->student_name }}</td>
                        <td class="text-center">{{ $inc->classroom?->name ?? '-' }}</td>
                        <td>{{ $inc->category_label }}</td>
                        <td>{{ $inc->description }}</td>
                        <td>{{ $inc->follow_up_label }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Nihil.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 12%;">Check-In/Out</th>
                    <th style="width: 20%;">Nama Tamu</th>
                    <th style="width: 18%;">Instansi</th>
                    <th style="width: 20%;">Keperluan</th>
                    <th style="width: 13%;">Menemui</th>
                </tr>
            </thead>
            <tbody>
                @forelse($guests as $idx => $g)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $g->visit_date?->format('d/m/Y') }}</td>
                        <td class="text-center">{{ substr($g->check_in_time, 0, 5) }} - {{ $g->check_out_time ? substr($g->check_out_time, 0, 5) : 'Selesai' }}</td>
                        <td>{{ $g->guest_name }}</td>
                        <td>{{ $g->institution ?: '-' }}</td>
                        <td>{{ $g->purpose }}</td>
                        <td>{{ $g->meet_with }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Nihil.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <!-- Tanda Tangan -->
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah
                <div style="height: 60px;"></div>
                <strong><u>{{ $setting->principal_name ?? 'Endang Patmowati, S.Pd.' }}</u></strong><br>
                NIP. {{ $setting->principal_nip ?? '-' }}
            </td>
            <td>
                Kras, {{ date('d F Y') }}<br>
                Waka Kurikulum / Kesiswaan
                <div style="height: 60px;"></div>
                <strong><u>{{ $setting->curriculum_head_name ?? '................................................' }}</u></strong><br>
                NIP. {{ $setting->curriculum_head_nip ?? '-' }}
            </td>
        </tr>
    </table>
</body>
</html>
