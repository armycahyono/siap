<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Izin - {{ $permit->permit_number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace, sans-serif;
            font-size: 9pt;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 5px;
            width: 74mm;
            line-height: 1.25;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        .header h3 {
            margin: 0;
            font-size: 11pt;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 8pt;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            margin: 4px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 7.5pt;
        }
        .signatures {
            width: 100%;
            margin-top: 10px;
            text-align: center;
            font-size: 8pt;
        }
        .signatures td {
            width: 50%;
            vertical-align: top;
        }
        .sig-space {
            height: 35px;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px; text-align: center;">
        <button onclick="window.print()" style="padding: 5px 12px; background: #0066cc; color: #fff; border: none; border-radius: 3px; cursor: pointer; font-size: 9pt; font-weight: bold;">
            🖨️ Cetak Slip
        </button>
    </div>

    <!-- Header Sekolah -->
    <div class="header">
        <h3>{{ $setting->school_name ?? 'SMK PGRI KRAS' }}</h3>
        <p>MEJA PIKET KEDISIPLINAN</p>
    </div>

    <div class="divider"></div>

    <div class="title">
        SLIP {{ $permit->permit_type_label }}
    </div>

    <div class="text-center" style="font-size: 8pt; margin-bottom: 4px;">
        No: <strong>{{ $permit->permit_number }}</strong>
    </div>

    <div class="divider"></div>

    <table>
        <tr>
            <td style="width: 32%;">Tanggal</td>
            <td style="width: 3%;">:</td>
            <td>{{ $permit->permit_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>:</td>
            <td>{{ substr($permit->entry_time ?: $permit->departure_time ?: '', 0, 5) }} WIB</td>
        </tr>
        <tr>
            <td>Nama Siswa</td>
            <td>:</td>
            <td class="bold">{{ $permit->student_name }}</td>
        </tr>
        <tr>
            <td>Kelas</td>
            <td>:</td>
            <td class="bold">{{ $permit->classroom?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Wali Kelas</td>
            <td>:</td>
            <td>{{ $permit->classroom?->homeroomTeacher?->formatted_short_name ?? '-' }}</td>
        </tr>
        @if($permit->period_assigned)
        <tr>
            <td>Masuk Jam</td>
            <td>:</td>
            <td class="bold">Jam ke-{{ $permit->period_assigned }}</td>
        </tr>
        @endif
        <tr>
            <td>Alasan</td>
            <td>:</td>
            <td>{{ $permit->reason }}</td>
        </tr>
        @if($permit->action_taken)
        <tr>
            <td>Sanksi/Tindakan</td>
            <td>:</td>
            <td>{{ $permit->action_taken }}</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>

    <div style="font-size: 7.5pt; font-style: italic; text-align: center; margin: 4px 0;">
        *Serahkan slip ini kepada Bpk/Ibu Guru Pengajar di kelas.*
    </div>

    <!-- Tanda Tangan -->
    <table class="signatures">
        <tr>
            <td>
                Siswa,<br>
                <div class="sig-space"></div>
                ({{ substr($permit->student_name, 0, 15) }})
            </td>
            <td>
                Guru Piket,<br>
                <div class="sig-space"></div>
                ({{ $permit->givenBy?->formatted_short_name ?? 'Petugas' }})
            </td>
        </tr>
    </table>

    <div class="footer">
        Dicetak: {{ date('d/m/Y H:i:s') }} WIB<br>
        <i>~ Disiplin Kunci Kesuksesan ~</i>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
