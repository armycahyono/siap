<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Kunjungan Rumah - {{ $visitation->student->name }}</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1a202c;
            line-height: 1.45;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
        }
        .kop-table td {
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
        }
        .kop-text h2 {
            font-size: 14px;
            margin: 0;
            text-transform: uppercase;
            font-weight: bold;
            color: #0f172a;
        }
        .kop-text h1 {
            font-size: 16px;
            margin: 2px 0;
            text-transform: uppercase;
            font-weight: bold;
            color: #1e3a8a;
        }
        .kop-text p {
            margin: 2px 0 0;
            font-size: 9px;
            color: #475569;
        }
        .doc-title {
            text-align: center;
            margin: 12px 0 16px 0;
        }
        .doc-title h3 {
            font-size: 13px;
            margin: 0;
            text-transform: uppercase;
            text-decoration: underline;
            color: #0f172a;
        }
        .doc-title span {
            font-size: 10px;
            color: #64748b;
        }
        .section-header {
            font-size: 11px;
            font-weight: bold;
            background-color: #f1f5f9;
            padding: 4px 8px;
            margin: 12px 0 6px 0;
            border-left: 3px solid #1e3a8a;
            color: #1e293b;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.data-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.data-table td.label {
            width: 28%;
            color: #475569;
            font-weight: 500;
        }
        table.data-table td.colon {
            width: 2%;
            text-align: center;
        }
        table.data-table td.value {
            width: 70%;
            color: #0f172a;
        }
        .content-box {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            border-radius: 4px;
            background-color: #ffffff;
            margin-bottom: 8px;
            min-height: 40px;
            white-space: pre-line;
            font-size: 10.5px;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .sign-table td {
            text-align: center;
            vertical-align: top;
            width: 33.33%;
            padding: 4px;
            font-size: 10.5px;
        }
        .sign-space {
            height: 55px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
            background-color: #e2e8f0;
            color: #334155;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT -->
    <table class="kop-table">
        <tr>
            <td class="kop-text">
                <h2>PEMERINTAH PROVINSI / DAERAH</h2>
                <h1>{{ $setting->school_name ?? 'SMK NEGERI CONTOH' }}</h1>
                <p>{{ $setting->school_address ?? 'Jl. Pendidikan No. 1, Kota' }} | Telp: {{ $setting->school_phone ?? '-' }} | Email: {{ $setting->school_email ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-title">
        <h3>BERITA ACARA KUNJUNGAN RUMAH (HOME VISIT)</h3>
        <span>Tahun Pelajaran: {{ $visitation->academic_year }} &bull; Ref ID: HV-{{ str_pad($visitation->id, 5, '0', STR_PAD_LEFT) }}</span>
    </div>

    <!-- IDENTITAS SISWA -->
    <div class="section-header">A. Identitas Siswa</div>
    <table class="data-table">
        <tr>
            <td class="label">Nama Lengkap Siswa</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $visitation->student->name }}</strong></td>
        </tr>
        <tr>
            <td class="label">NISN / NIS</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->student->nisn ?? '-' }} / {{ $visitation->student->nis ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kelas / Program Keahlian</td>
            <td class="colon">:</td>
            <td class="value">
                @php
                    $stdClass = $visitation->student->classroomForAcademicYear($visitation->academic_year) ?? $visitation->student->classroom;
                @endphp
                {{ $stdClass?->name ?? 'Belum terdata' }}
            </td>
        </tr>
        <tr>
            <td class="label">Nama Orang Tua / Wali</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->student->father_name ?? ($visitation->student->mother_name ?? ($visitation->student->guardian_name ?? 'Orang Tua / Wali')) }}</td>
        </tr>
        <tr>
            <td class="label">Alamat Kunjungan</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->location_address ?? ($visitation->student->address ?? 'Sesuai domisili siswa') }}</td>
        </tr>
    </table>

    <!-- PELAKSANAAN KUNJUNGAN -->
    <div class="section-header">B. Data Pelaksanaan Kunjungan</div>
    <table class="data-table">
        <tr>
            <td class="label">Jenis Kegiatan</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $visitation->visit_type_label }}</strong></td>
        </tr>
        <tr>
            <td class="label">Tanggal Pelaksanaan</td>
            <td class="colon">:</td>
            <td class="value">
                {{ $visitation->actual_date ? $visitation->actual_date->translatedFormat('l, d F Y') : $visitation->planned_date->translatedFormat('l, d F Y') }}
                ({{ $visitation->status_label }})
            </td>
        </tr>
        <tr>
            <td class="label">Petugas Pelaksana Utama</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->primaryOfficer?->name ?? '-' }} ({{ $visitation->primaryOfficer?->position ?? 'Guru BK' }})</td>
        </tr>
        @if($visitation->secondaryOfficer)
        <tr>
            <td class="label">Petugas Pendamping</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->secondaryOfficer->name }} ({{ $visitation->secondaryOfficer->position ?? 'Wali Kelas / Tim Disiplin' }})</td>
        </tr>
        @endif
        <tr>
            <td class="label">Inisiator / Pengusul</td>
            <td class="colon">:</td>
            <td class="value">{{ $visitation->requestedBy?->name ?? 'Wali Kelas' }}</td>
        </tr>
    </table>

    <!-- LATAR BELAKANG / ALASAN -->
    <div class="section-header">C. Maksud & Alasan Kunjungan</div>
    <div class="content-box">{{ $visitation->reason }}</div>

    <!-- HASIL KUNJUNGAN / KESEPAKATAN -->
    <div class="section-header">D. Hasil Kunjungan & Kesepakatan Bersama</div>
    <div class="content-box">
        @if($visitation->result_summary)
            {{ $visitation->result_summary }}
        @else
            <em>(Kunjungan belum terlaksana atau laporan hasil belum diisi oleh petugas)</em>
        @endif
    </div>

    <!-- TANDA TANGAN PIHAK TERKAIT -->
    <table class="sign-table">
        <tr>
            <td>
                Mengetahui / Menyetujui,<br>
                <strong>Orang Tua / Wali Siswa</strong>
                <div class="sign-space"></div>
                <strong>( .................................................... )</strong>
            </td>
            <td>
                Petugas Pendamping,<br>
                <strong>{{ $visitation->secondaryOfficer ? 'Wali Kelas / Pendamping' : 'Wali Kelas' }}</strong>
                <div class="sign-space"></div>
                <strong>{{ $visitation->secondaryOfficer?->name ?? ($visitation->student->classroom?->homeroomTeacher?->name ?? '( .................................................... )') }}</strong>
            </td>
            <td>
                {{ $setting->city ?? 'Tempat' }}, {{ $visitation->actual_date ? $visitation->actual_date->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}<br>
                Petugas Pelaksana Utama,<br>
                <strong>Guru Bimbingan & Konseling</strong>
                <div class="sign-space"></div>
                <strong>{{ $visitation->primaryOfficer?->name ?? 'Guru BK' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding-top: 25px;">
                Mengetahui,<br>
                <strong>{{ $setting->headmaster_position ?? 'Kepala Sekolah' }} / Waka Kesiswaan</strong>
                <div class="sign-space"></div>
                <strong>{{ $setting->headmaster_name ?? '....................................................' }}</strong><br>
                <span style="font-size: 9px; color: #475569;">NIP: {{ $setting->headmaster_nip ?? '-' }}</span>
            </td>
        </tr>
    </table>
</body>
</html>