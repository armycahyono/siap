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

    @if(isset($groupedPermits) && $groupedPermits->isNotEmpty())
        {{-- MODE CETAK BATCH / ROMBONGAN (TERKELOMPOK PER ROMBEL / KELAS) --}}
        @foreach($groupedPermits as $classroomId => $classPermits)
            @php
                $firstPermit = $classPermits->first();
                $classroom = $firstPermit->classroom;
            @endphp
            <div class="slip-group-item">
                <!-- Header Sekolah -->
                <div class="header">
                    <h3>{{ $setting->school_name ?? 'SMK PGRI KRAS' }}</h3>
                    <p>MEJA PIKET KEDISIPLINAN</p>
                </div>

                <div class="divider"></div>

                <div class="title">
                    SLIP {{ $firstPermit->permit_type_label }}
                </div>

                <div class="text-center" style="font-size: 8.5pt; margin-bottom: 4px;">
                    Kelas: <strong>{{ $classroom?->name ?? 'ROMBEL' }}</strong>
                    @if(!empty($batchId))
                        <br><span style="font-size: 7pt; color: #555; font-family: monospace;">Rombongan: {{ $batchId }}</span>
                    @endif
                </div>

                <div class="divider"></div>

                <table>
                    <tr>
                        <td style="width: 32%;">Tanggal</td>
                        <td style="width: 3%;">:</td>
                        <td>{{ $firstPermit->permit_date?->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td>Waktu</td>
                        <td>:</td>
                        <td>{{ substr($firstPermit->entry_time ?: $firstPermit->departure_time ?: '', 0, 5) }} WIB</td>
                    </tr>
                    <tr>
                        <td>Kelas</td>
                        <td>:</td>
                        <td class="bold">{{ $classroom?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Wali Kelas</td>
                        <td>:</td>
                        <td>{{ $classroom?->homeroomTeacher?->formatted_short_name ?? '-' }}</td>
                    </tr>
                    @if($firstPermit->period_assigned)
                    <tr>
                        <td>Masuk Jam</td>
                        <td>:</td>
                        <td class="bold">Jam ke-{{ $firstPermit->period_assigned }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Alasan</td>
                        <td>:</td>
                        <td>{{ $firstPermit->reason }}</td>
                    </tr>
                    @if($firstPermit->action_taken)
                    <tr>
                        <td>Sanksi/Tindakan</td>
                        <td>:</td>
                        <td>{{ $firstPermit->action_taken }}</td>
                    </tr>
                    @endif
                </table>

                <!-- Daftar Siswa Rombel Ini -->
                <div style="margin-top: 6px;">
                    <div style="font-weight: bold; font-size: 8pt; margin-bottom: 2px;">
                        Daftar Siswa Terlampir ({{ $classPermits->count() }} Siswa):
                    </div>
                    <table style="border: 1px solid #000; font-size: 8pt; width: 100%;">
                        <thead>
                            <tr style="background: #f0f0f0; border-bottom: 1px solid #000;">
                                <th style="padding: 2px; width: 12%; text-align: center;">No</th>
                                <th style="padding: 2px; text-align: left;">Nama Siswa</th>
                                <th style="padding: 2px; width: 30%; text-align: right;">NIS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classPermits as $idx => $p)
                            <tr style="border-bottom: 1px dotted #ccc;">
                                <td style="text-align: center; padding: 2px;">{{ $idx + 1 }}</td>
                                <td class="bold" style="padding: 2px;">{{ $p->student_name }}</td>
                                <td style="text-align: right; padding: 2px; font-size: 7.5pt; font-family: monospace;">{{ $p->student?->nis ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>

                <div style="font-size: 7.5pt; font-style: italic; text-align: center; margin: 4px 0;">
                    *Serahkan slip ini kepada Bpk/Ibu Guru Pengajar di kelas {{ $classroom?->name }}.*
                </div>

                <!-- Tanda Tangan -->
                <table class="signatures">
                    <tr>
                        <td>
                            Paraf Guru Mapel,<br>
                            <div class="sig-space"></div>
                            (....................)
                        </td>
                        <td>
                            Guru Piket,<br>
                            <div class="sig-space"></div>
                            ({{ $firstPermit->givenBy?->formatted_short_name ?? 'Petugas' }})
                        </td>
                    </tr>
                </table>

                <div class="footer">
                    Dicetak: {{ date('d/m/Y H:i:s') }} WIB<br>
                    <i>~ Disiplin Kunci Kesuksesan ~</i>
                </div>
            </div>

            @if(! $loop->last)
                <div style="page-break-after: always; margin: 20px 0; border-top: 1px dashed #666;" class="no-print-screen"></div>
            @endif
        @endforeach
    @else
        {{-- MODE CETAK TUNGGAL (1 SISWA) --}}
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
    @endif

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
