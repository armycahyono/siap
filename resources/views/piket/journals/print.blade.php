<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Piket - {{ $journal->date?->translatedFormat('d F Y') }}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            margin: 20px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header h2 {
            margin: 0;
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header h3 {
            margin: 2px 0;
            font-size: 13pt;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 9pt;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 15px;
        }
        .doc-title h4 {
            margin: 0;
            font-size: 13pt;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 10.5pt;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        .section-title {
            font-weight: bold;
            font-size: 10.5pt;
            margin-top: 12px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        table.data-table th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .notes-box {
            border: 1px solid #000;
            padding: 8px;
            min-height: 40px;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-table td {
            vertical-align: top;
            width: 50%;
            text-align: center;
            font-size: 10.5pt;
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
        <h3>LAPORAN JURNAL HARIAN GURU PIKET</h3>
        <p>{{ $setting->school_address ?? 'Jl. Raya Kras, Kediri, Jawa Timur' }} &bull; Telp: {{ $setting->school_phone ?? '-' }} &bull; Email: {{ $setting->school_email ?? '-' }}</p>
    </div>

    <!-- Metadata -->
    <table class="meta-table">
        <tr>
            <td style="width: 18%;"><strong>Hari, Tanggal</strong></td>
            <td style="width: 2%;">:</td>
            <td style="width: 35%;">{{ $journal->date?->translatedFormat('l, d F Y') }}</td>
            <td style="width: 20%;"><strong>Tahun Pelajaran</strong></td>
            <td style="width: 2%;">:</td>
            <td style="width: 23%;">{{ $journal->academic_year }} ({{ $journal->semester }})</td>
        </tr>
        <tr>
            <td><strong>Koordinator Piket</strong></td>
            <td>:</td>
            <td>{{ $journal->coordinator?->name ?? '-' }}</td>
            <td><strong>Status Jurnal</strong></td>
            <td>:</td>
            <td>{{ $journal->isCompleted() ? 'Tervalidasi / Selesai' : 'Berlangsung' }}</td>
        </tr>
    </table>

    <!-- 1. Guru Inval -->
    <div class="section-title">I. GURU BERHALANGAN HADIR & PENUGASAN GURU INVAL</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 12%;">Kelas</th>
                <th style="width: 25%;">Guru Berhalangan</th>
                <th style="width: 18%;">Mapel & Jam Ke</th>
                <th style="width: 25%;">Guru Inval / Pengganti</th>
                <th style="width: 15%;">Alasan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journal->invalClasses as $idx => $inv)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $inv->classroom?->name ?? '-' }}</td>
                    <td>{{ $inv->absentTeacher?->name ?? '-' }}</td>
                    <td>{{ $inv->subject?->name ?? '-' }} (Jam {{ $inv->period_start }}-{{ $inv->period_end }})</td>
                    <td>{{ $inv->invalTeacher?->name ?? 'Belum ada' }}</td>
                    <td>{{ $inv->reason }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Nihil. Semua guru mengajar sesuai jadwal.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 2. Perizinan & Siswa Terlambat -->
    <div class="section-title">II. REKAPITULASI PERIZINAN & SISWA TERLAMBAT</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">No. Izin</th>
                <th style="width: 25%;">Nama Siswa</th>
                <th style="width: 12%;">Kelas</th>
                <th style="width: 18%;">Jenis Izin</th>
                <th style="width: 10%;">Waktu</th>
                <th style="width: 15%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journal->studentPermits as $idx => $p)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $p->permit_number }}</td>
                    <td>{{ $p->student_name }}</td>
                    <td class="text-center">{{ $p->classroom?->name ?? '-' }}</td>
                    <td>{{ $p->permit_type_label }}</td>
                    <td class="text-center">{{ substr($p->entry_time ?: $p->departure_time ?: '', 0, 5) }}</td>
                    <td>{{ $p->reason }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Nihil. Tidak ada perizinan siswa.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 3. Kejadian Kasus -->
    <div class="section-title">III. CATATAN KEJADIAN / KASUS PELANGGARAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 22%;">Nama Siswa & Kelas</th>
                <th style="width: 18%;">Kategori</th>
                <th style="width: 30%;">Uraian Kejadian</th>
                <th style="width: 25%;">Tindakan Piket & Tindak Lanjut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journal->incidents as $idx => $inc)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $inc->student_name }} ({{ $inc->classroom?->name ?? '-' }})</td>
                    <td>{{ $inc->category_label }}</td>
                    <td>{{ $inc->description }}</td>
                    <td>{{ $inc->action_taken }} &bull; {{ $inc->follow_up_label }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Nihil. Kondisi lingkungan sekolah aman dan tertib.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 4. Situasi Umum -->
    <div class="section-title">IV. CATATAN SITUASI UMUM SEKOLAH</div>
    <div class="notes-box">
        {{ $journal->general_notes ?: 'Situasi kegiatan belajar mengajar berjalan kondusif, tertib, dan lancar.' }}
    </div>

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
                Kras, {{ $journal->date?->translatedFormat('d F Y') }}<br>
                Koordinator Guru Piket
                <div style="height: 60px;"></div>
                <strong><u>{{ $journal->coordinator?->name ?? '................................................' }}</u></strong><br>
                NIP. {{ $journal->coordinator?->id_number ?? '-' }}
            </td>
        </tr>
    </table>
</body>
</html>
