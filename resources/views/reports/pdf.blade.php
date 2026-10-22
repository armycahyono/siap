<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Supervisi {{ $supervision->teacher->name }}</title>
    <style>
        @page {
            margin: 15mm 15mm;
        }
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #1a2a3b;
            line-height: 1.4;
        }
        .kop {
            text-align: center;
            border-bottom: 2px solid #102d52;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .kop h1 {
            font-size: 16px;
            color: #102d52;
            margin: 0;
            text-transform: uppercase;
        }
        .kop h2 {
            font-size: 12px;
            margin: 3px 0 0;
        }
        .kop p {
            margin: 2px 0 0;
            font-size: 9px;
            color: #475569;
        }
        .title {
            text-align: center;
            margin-bottom: 15px;
        }
        .title h3 {
            font-size: 13px;
            margin: 0;
            text-decoration: underline;
        }
        .title span {
            font-size: 10px;
            color: #64748b;
        }
        .section-title {
            font-weight: bold;
            font-size: 11px;
            color: #102d52;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
            margin: 12px 0 6px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #475569;
            padding: 5px 6px;
        }
        .data-table th {
            background-color: #f1f5f9;
            text-align: center;
        }
        .phase-header {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .note-box {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            margin-bottom: 6px;
            background: #fafafa;
        }
        .note-box b {
            display: block;
            margin-bottom: 2px;
            color: #1e293b;
        }
        .signature-table {
            width: 100%;
            margin-top: 25px;
            text-align: center;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Halaman 1 -->
    <div class="kop">
        <h1>{{ $schoolInfo['name'] }}</h1>
        <h2>SUPERVISI PEMBELAJARAN GURU TAHUN PELAJARAN {{ $supervision->academic_year }}</h2>
        <p>{{ $schoolInfo['address'] }} | Telp: {{ $schoolInfo['phone'] }}</p>
    </div>

    <div class="title">
        <h3>LEMBAR IDENTITAS & PRAOBSERVASI</h3>
        <span>Nomor: {{ $supervision->report_number }}</span>
    </div>

    <div class="section-title">A. Identitas Guru & KBM</div>
    @php
        $teacherIdLabel = !empty($teacher['nip']) ? 'NIP' : 'NIGS';
        $teacherIdVal = !empty($teacher['nip']) ? $teacher['nip'] : ($teacher['nigs_fix'] ?? $teacher['nigs'] ?? '-');
        $hasValidNuptk = !empty($teacher['nuptk']);
    @endphp
    <table>
        <tr>
            <td style="width: 18%; font-weight: bold;">Nama Guru</td>
            <td style="width: 34%; padding-right: 8px;">: {{ $teacher['name'] }}</td>
            <td style="width: 18%; font-weight: bold;">{{ $teacherIdLabel }}{{ $hasValidNuptk ? ' / NUPTK' : '' }}</td>
            <td style="width: 30%;">: {{ $teacherIdVal }}{{ $hasValidNuptk ? ' / ' . $teacher['nuptk'] : '' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Mata Pelajaran</td>
            <td style="padding-right: 8px;">: {{ $supervision->subject }}</td>
            <td style="font-weight: bold;">Kelas</td>
            <td>: {{ $supervision->class_name }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Tahun Pelajaran</td>
            <td style="padding-right: 8px;">: {{ $supervision->academic_year }}</td>
            <td style="font-weight: bold;">Kons. Keahlian</td>
            <td>: {{ $supervision->expertise_program ?: 'Semua Jurusan' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Tanggal Supervisi</td>
            <td style="padding-right: 8px;">: {{ $supervision->schedule_date->translatedFormat('d F Y') }}</td>
            <td style="font-weight: bold;">Nama Supervisor</td>
            <td>: {{ $supervisor['name'] }}</td>
        </tr>
    </table>

    <div class="section-title">B. Instrumen Praobservasi</div>
    <div class="note-box">
        <b>1. Materi Pokok:</b> {{ $supervision->pre_material ?: '-' }}
    </div>
    <div class="note-box">
        <b>2. Tujuan Pembelajaran:</b> {!! nl2br(e($supervision->pre_objectives ?: '-')) !!}
    </div>
    <div class="note-box">
        <b>3. Model / Metode:</b> {{ $supervision->pre_method ?: '-' }}
    </div>
    <div class="note-box">
        <b>4. Media Pembelajaran:</b> {{ $supervision->pre_media ?: '-' }}
    </div>
    <div class="note-box">
        <b>5. Catatan Supervisor Sebelum Observasi:</b> {{ $supervision->pre_supervisor_notes ?: '-' }}
    </div>

    <div class="page-break"></div>

    <!-- Halaman 2: Butir Observasi -->
    <div class="kop">
        <h1>{{ $schoolInfo['name'] }}</h1>
        <p>INSTRUMEN OBSERVASI PEMBELAJARAN KELAS</p>
    </div>

    <div class="title">
        <h3>HASIL PENILAIAN INDIKATOR KINERJA GURU</h3>
        <span>Guru: {{ $teacher['name'] }} | {{ $supervision->subject }}</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%;">No</th>
                <th>Aspek Indikator Pembelajaran</th>
                <th style="width: 14%;">Skor (1-4)</th>
                <th style="width: 35%;">Catatan Pengamatan</th>
            </tr>
        </thead>
        <tbody>
            @php
                $globalNo = 1;
            @endphp
            @foreach($rubricPhases as $phase)
            <tr class="phase-header">
                <td colspan="4">{{ $phase['name'] }}</td>
            </tr>
            @foreach($phase['indicators'] as $ind)
            <tr>
                <td style="text-align: center;">{{ $globalNo++ }}</td>
                <td>{{ $ind['title'] }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $ind['score'] ?? '-' }}</td>
                <td>{{ $ind['notes'] ?? '-' }}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- Halaman 3: Hasil & Pengesahan -->
    <div class="kop">
        <h1>{{ $schoolInfo['name'] }}</h1>
        <p>LEMBAR HASIL AKHIR & PENGESAHAN SUPERVISI</p>
    </div>

    <div class="title">
        <h3>REKAPITULASI HASIL & PENGESAHAN</h3>
        <span>No: {{ $supervision->report_number }}</span>
    </div>

    <div class="section-title">A. Ringkasan Skor & Predikat</div>
    <table class="data-table" style="text-align: center;">
        <thead>
            <tr>
                <th>Komponen</th>
                <th>Total Skor</th>
                <th>Skor Maksimal</th>
                <th>Persentase</th>
                <th>Predikat</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold; text-align: left;">Observasi Pembelajaran di Kelas</td>
                <td style="font-weight: bold;">{{ $supervision->total_score }}</td>
                <td>{{ $supervision->max_score }}</td>
                <td style="font-weight: bold; color: #102d52;">{{ $supervision->percentage }}%</td>
                <td style="font-weight: bold;">{{ $supervision->predicate }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">B. Catatan Supervisor</div>
    <div class="note-box">
        <b>Catatan Positif:</b> {{ $supervision->post_positive ?: '-' }}
    </div>
    <div class="note-box">
        <b>Hal yang Perlu Ditingkatkan:</b> {{ $supervision->post_improvement ?: '-' }}
    </div>
    <div class="note-box">
        <b>Rekomendasi:</b> {{ $supervision->post_recommendation ?: '-' }}
    </div>
    <div class="note-box">
        <b>Rencana Tindak Lanjut:</b> {{ $supervision->post_followup ?: '-' }}
    </div>
    @if($supervision->principal_notes)
    <div class="note-box">
        <b>Catatan Kepala Sekolah:</b> {{ $supervision->principal_notes }}
    </div>
    @endif

    <table class="signature-table">
        <tr>
            <td>
                <div>Mengetahui & Mengesahkan,<br>Kepala {{ $schoolInfo['name'] }}</div>
                <div style="height: 60px; line-height: 60px;">
                    @if($supervision->approved_by_principal)
                        <img src="{{ $qrCodeData }}" style="height: 55px;" alt="QR Code">
                    @endif
                </div>
                <div><strong>{{ $principal['name'] }}</strong></div>
                @if(!empty($principal['nip']))
                    <div style="font-size: 9px;">NIP. {{ $principal['nip'] }}</div>
                @endif
            </td>
            <td>
                <div>Kediri, {{ $supervision->schedule_date?->translatedFormat('d F Y') ?? date('d F Y') }}<br>Supervisor Penilai,</div>
                <div style="height: 60px;"></div>
                <div><strong>{{ $supervisor['name'] }}</strong></div>
                @if(!empty($supervisor['nip']))
                    <div style="font-size: 9px;">NIP. {{ $supervisor['nip'] }}</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
