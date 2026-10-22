<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi Siswa Kelas {{ $selectedClassroom?->name }}</title>
    <style>
        @page {
            margin: 12mm 10mm;
        }
        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #0f172a;
            line-height: 1.35;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #102d52;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 14px;
            font-weight: bold;
            color: #102d52;
            margin: 0;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 12px;
            margin: 3px 0 0;
            color: #1e293b;
        }
        .header p {
            margin: 2px 0 0;
            font-size: 8.5px;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 12px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #64748b;
            padding: 4px 5px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        
        .badge-status {
            font-weight: bold;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 3px;
            display: inline-block;
        }
        .status-h { color: #15803d; }
        .status-s { color: #b45309; }
        .status-i { color: #0369a1; }
        .status-d { color: #6d28d9; }
        .status-a { color: #b91c1c; font-weight: bold; }
        .status-b { color: #b91c1c; font-weight: bold; background: #fee2e2; }

        .summary-box {
            margin-top: 10px;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 4px;
        }
        .signature-table {
            width: 100%;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 9.5px;
        }
    </style>
</head>
<body>

    <!-- KOP & HEADER DOKUMEN -->
    <div class="header">
        <h1>{{ $setting->school_name ?? 'SMK PGRI KRAS' }}</h1>
        <p>{{ $setting->school_address ?? 'Sistem Informasi Akademik & Presensi (SIAP)' }}</p>
    </div>

    @php
        $judulMode = match($mode) {
            'daily' => 'HARIAN',
            'weekly' => 'MINGGUAN',
            'monthly' => 'BULANAN',
            'semester' => 'SEMESTERAN',
            'yearly' => 'TAHUNAN',
            'custom' => 'PERIODE TERTENTU',
            default => 'KBM',
        };
    @endphp
    <div style="text-align: center; margin-bottom: 10px;">
        <h2 style="font-size: 12px; font-weight: bold; color: #102d52; margin: 0; text-transform: uppercase;">
            REKAPITULASI PRESENSI KBM {{ $judulMode }}
        </h2>
    </div>

    <!-- INFORMASI KELAS & PERIODE -->
    <table class="meta-table">
        <tr>
            <td style="width: 15%; font-weight: bold;">Kelas</td>
            <td style="width: 35%;">: {{ $selectedClassroom?->name ?? '-' }}</td>
            <td style="width: 15%; font-weight: bold;">Tahun Ajaran</td>
            <td style="width: 35%;">: {{ $academicYear }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Wali Kelas</td>
            <td>: {{ $selectedClassroom?->homeroomTeacher?->name ?? '-' }}</td>
            <td style="font-weight: bold;">Periode</td>
            <td>: 
                @php
                    $years = explode('/', (string) $academicYear);
                    $y1 = $years[0] ?? date('Y');
                    $y2 = $years[1] ?? ($y1 + 1);
                @endphp
                @if($mode === 'daily')
                    {{ $dayName }}, {{ $currentDate->translatedFormat('d F Y') }}
                @elseif($mode === 'weekly')
                    {{ $startOfWeek->translatedFormat('d M Y') }} - {{ $endOfWeek->translatedFormat('d M Y') }}
                @elseif($mode === 'monthly')
                    {{ $monthName }}
                @elseif($mode === 'semester')
                    {{ ($activeSemester ?? 'ganjil') === 'genap' ? 'Januari '.$y2.' - Juni '.$y2 : 'Juli '.$y1.' - Desember '.$y1 }}
                @elseif($mode === 'yearly')
                    Juli {{ $y1 }} - Juni {{ $y2 }}
                @elseif($mode === 'custom')
                    {{ $startDate->translatedFormat('d M Y') }} - {{ $endDate->translatedFormat('d M Y') }}
                @else
                    {{ $periodTitle ?? '-' }}
                @endif
            </td>
        </tr>
    </table>

    {{-- ========================================================= --}}
    {{-- 1. TABEL PDF: MODE HARIAN (PER JAM PELAJARAN / JP) --}}
    {{-- ========================================================= --}}
    @if($mode === 'daily')
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 20px; padding: 3px 1px;">No</th>
                    <th rowspan="2" style="width: 65px; padding: 3px 2px;">NISN</th>
                    <th rowspan="2" style="width: 180px; text-align: left; padding-left: 6px;">Nama Siswa</th>
                    <th rowspan="2" style="width: 22px; padding: 3px 1px;">L/P</th>
                    <th colspan="{{ count($periods) }}" style="padding: 3px 1px;">Jam Pelajaran (JP)</th>
                    <th rowspan="2" style="width: 80px; padding: 3px 2px;">Status Akhir</th>
                </tr>
                <tr>
                    @foreach($periods as $p)
                        <th style="width: 19px; padding: 3px 0;">{{ $p }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($studentGrid as $row)
                    @php $stu = $row['student']; @endphp
                    <tr>
                        <td class="text-center" style="padding: 3px 1px;">{{ $loop->iteration }}</td>
                        <td class="text-center" style="padding: 3px 2px; font-size: 8px;">{{ $stu->nisn ?: $stu->nis ?: '-' }}</td>
                        <td style="padding: 3px 6px;">{{ $stu->name }}</td>
                        <td class="text-center" style="padding: 3px 1px;">{{ $stu->gender ?? '-' }}</td>
                        @foreach($periods as $p)
                            @php 
                                $pItem = $row['periods'][$p] ?? null;
                                $lbl = $pItem['label'] ?? '-';
                                $cls = match(strtolower($lbl)) {
                                    'h' => 'status-h',
                                    's' => 'status-s',
                                    'i' => 'status-i',
                                    'd' => 'status-d',
                                    'a' => 'status-a',
                                    default => ''
                                };
                            @endphp
                            <td class="text-center fw-bold {{ $cls }}" style="padding: 3px 0;">{{ $lbl }}</td>
                        @endforeach
                        <td class="text-center {{ $row['is_truant'] ? 'status-b' : '' }}" style="padding: 3px 2px; font-size: 8.5px;">
                            {{ $row['final_status'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + count($periods) }}" class="text-center">Tidak ada data siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Ringkasan Statistik -->
        <div class="summary-box">
            <strong>Ringkasan Presensi Hari Ini:</strong>
            Hadir Penuh: <strong>{{ $summaryStats['present_full'] }}</strong> |
            Bolos Sebagian JP: <strong>{{ $summaryStats['partial_truant'] }}</strong> |
            Dispensasi: <strong>{{ $summaryStats['dispen'] }}</strong> |
            Sakit: <strong>{{ $summaryStats['sick'] }}</strong> |
            Izin: <strong>{{ $summaryStats['permit'] }}</strong> |
            Pulang Cepat: <strong>{{ $summaryStats['early_leave'] }}</strong> |
            Terlambat: <strong>{{ $summaryStats['late'] }}</strong> |
            Alfa: <strong>{{ $summaryStats['alfa'] }}</strong>
        </div>

    {{-- ========================================================= --}}
    {{-- 2. TABEL PDF: MODE MINGGUAN (SENIN S.D JUMAT) --}}
    {{-- ========================================================= --}}
    @elseif($mode === 'weekly')
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 25px;">No</th>
                    <th rowspan="2" style="width: 75px;">NISN</th>
                    <th rowspan="2">Nama Siswa</th>
                    <th rowspan="2" style="width: 25px;">L/P</th>
                    <th colspan="{{ count($weekDays) }}">Hari Kehadiran</th>
                    <th colspan="7">Total Kehadiran Pekan Ini</th>
                </tr>
                <tr>
                    @foreach($weekDays as $d)
                        <th style="width: 45px;">{{ $d['dayName'] }}<br><span style="font-size: 7.5px; font-weight: normal;">{{ $d['formatted'] }}</span></th>
                    @endforeach
                    <th style="width: 22px;">H</th>
                    <th style="width: 22px;">S</th>
                    <th style="width: 22px;">I</th>
                    <th style="width: 22px;">D</th>
                    <th style="width: 22px;">T</th>
                    <th style="width: 22px;">A</th>
                    <th style="width: 28px;">Bolos</th>
                </tr>
            </thead>
            <tbody>
                @forelse($studentWeeklyGrid as $row)
                    @php $stu = $row['student']; @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center">{{ $stu->nisn ?: $stu->nis ?: '-' }}</td>
                        <td>{{ $stu->name }}</td>
                        <td class="text-center">{{ $stu->gender ?? '-' }}</td>
                        @foreach($weekDays as $d)
                            @php 
                                $lbl = $row['days'][$d['date']]['label'] ?? '-';
                                $cls = match(strtolower($lbl)) {
                                    'h' => 'status-h',
                                    's' => 'status-s',
                                    'i' => 'status-i',
                                    'd' => 'status-d',
                                    'a' => 'status-a',
                                    'bolos' => 'status-b',
                                    default => ''
                                };
                            @endphp
                            <td class="text-center fw-bold {{ $cls }}">{{ $lbl }}</td>
                        @endforeach
                        <td class="text-center status-h fw-bold">{{ $row['total_h'] }}</td>
                        <td class="text-center status-s">{{ $row['total_s'] }}</td>
                        <td class="text-center status-i">{{ $row['total_i'] }}</td>
                        <td class="text-center status-d">{{ $row['total_d'] }}</td>
                        <td class="text-center">{{ $row['total_t'] }}</td>
                        <td class="text-center status-a fw-bold">{{ $row['total_a'] }}</td>
                        <td class="text-center {{ $row['total_truant'] > 0 ? 'status-b fw-bold' : '' }}">{{ $row['total_truant'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 11 + count($weekDays) }}" class="text-center">Tidak ada data siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    {{-- ========================================================= --}}
    {{-- 3. TABEL PDF: MODE BULANAN (REKAP DAPODIK & PERSENTASE) --}}
    {{-- ========================================================= --}}
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 18px; padding: 3px 1px;">No</th>
                    <th rowspan="2" style="width: 58px; padding: 3px 1px;">NISN</th>
                    <th rowspan="2" style="width: 180px; text-align: left; padding-left: 6px;">Nama Siswa</th>
                    <th rowspan="2" style="width: 18px; padding: 3px 1px;">L/P</th>
                    <th colspan="7" style="padding: 3px 1px;">Detail Rekap Sekolah</th>
                    <th rowspan="2" style="width: 38px; padding: 3px 1px;">% Hadir</th>
                    <th colspan="4" style="padding: 3px 1px;">Standar Dapodik</th>
                </tr>
                <tr>
                    <th style="width: 17px; padding: 3px 0;">H</th>
                    <th style="width: 17px; padding: 3px 0;">S</th>
                    <th style="width: 17px; padding: 3px 0;">I</th>
                    <th style="width: 17px; padding: 3px 0;">D</th>
                    <th style="width: 17px; padding: 3px 0;">T</th>
                    <th style="width: 17px; padding: 3px 0;">A</th>
                    <th style="width: 20px; padding: 3px 0;">B</th>
                    <th style="width: 17px; padding: 3px 0;">H</th>
                    <th style="width: 17px; padding: 3px 0;">S</th>
                    <th style="width: 17px; padding: 3px 0;">I</th>
                    <th style="width: 17px; padding: 3px 0;">A</th>
                </tr>
            </thead>
            <tbody>
                @forelse($studentMonthlyGrid as $row)
                    @php $stu = $row['student']; @endphp
                    <tr>
                        <td class="text-center" style="padding: 3px 1px;">{{ $loop->iteration }}</td>
                        <td class="text-center" style="padding: 3px 1px; font-size: 8px;">{{ $stu->nisn ?: $stu->nis ?: '-' }}</td>
                        <td style="padding: 3px 6px;">{{ $stu->name }}</td>
                        <td class="text-center" style="padding: 3px 1px;">{{ $stu->gender ?? '-' }}</td>
                        <td class="text-center status-h fw-bold" style="padding: 3px 0;">{{ $row['days_present'] }}</td>
                        <td class="text-center status-s" style="padding: 3px 0;">{{ $row['days_sick'] }}</td>
                        <td class="text-center status-i" style="padding: 3px 0;">{{ $row['days_permit'] }}</td>
                        <td class="text-center status-d" style="padding: 3px 0;">{{ $row['days_dispen'] }}</td>
                        <td class="text-center" style="padding: 3px 0;">{{ $row['days_late'] }}</td>
                        <td class="text-center status-a fw-bold" style="padding: 3px 0;">{{ $row['days_alfa'] }}</td>
                        <td class="text-center {{ $row['days_truant'] > 0 ? 'status-b' : '' }}" style="padding: 3px 0;">{{ $row['days_truant'] }}</td>
                        <td class="text-center fw-bold" style="padding: 3px 1px; font-size: 8.5px;">{{ $row['attendance_rate'] }}%</td>
                        <td class="text-center status-h fw-bold" style="padding: 3px 0;">{{ $row['dapodik_present'] }}</td>
                        <td class="text-center status-s" style="padding: 3px 0;">{{ $row['dapodik_sick'] }}</td>
                        <td class="text-center status-i" style="padding: 3px 0;">{{ $row['dapodik_permit'] }}</td>
                        <td class="text-center status-a fw-bold" style="padding: 3px 0;">{{ $row['dapodik_alfa'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="text-center">Tidak ada data siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <!-- TANDA TANGAN WALI KELAS & KEPALA SEKOLAH -->
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah
                <div style="height: 50px;"></div>
                <strong>{{ $setting->principal_name ?? 'Kepala Sekolah' }}</strong><br>
                <span>NIP. {{ $setting->principal_nip ?? '-' }}</span>
            </td>
            <td>
                {{ $setting->school_city ?? 'Kota' }}, {{ now()->translatedFormat('d F Y') }}<br>
                Wali Kelas {{ $selectedClassroom?->name }}
                <div style="height: 50px;"></div>
                <strong>{{ $selectedClassroom?->homeroomTeacher?->name ?? 'Wali Kelas' }}</strong><br>
                <span>NIP. {{ $selectedClassroom?->homeroomTeacher?->nip ?? '-' }}</span>
            </td>
        </tr>
    </table>

</body>
</html>
