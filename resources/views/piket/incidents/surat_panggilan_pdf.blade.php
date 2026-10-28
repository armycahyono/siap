<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Panggilan Orang Tua - {{ $incident->student_name }}</title>
    <style>
        @page {
            margin: 15mm 20mm 20mm 20mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: #000000;
            line-height: 1.5;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 3px double #000000;
            padding-bottom: 8px;
        }
        .kop-table td {
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
        }
        .kop-text h3 {
            font-size: 14px;
            margin: 0;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kop-text h2 {
            font-size: 16px;
            margin: 2px 0;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kop-text p {
            margin: 2px 0 0;
            font-size: 10px;
        }
        .letter-meta {
            width: 100%;
            margin-bottom: 16px;
        }
        .letter-meta td {
            vertical-align: top;
            font-size: 11.5px;
        }
        .recipient {
            margin-bottom: 16px;
            font-size: 12px;
        }
        .content {
            text-align: justify;
            margin-bottom: 16px;
        }
        .content p {
            margin: 0 0 10px 0;
            text-indent: 25px;
        }
        table.detail-table {
            width: 90%;
            margin: 8px auto 14px auto;
            border-collapse: collapse;
        }
        table.detail-table td {
            padding: 3px 6px;
            vertical-align: top;
            font-size: 11.5px;
        }
        table.detail-table td.lbl {
            width: 28%;
            font-weight: bold;
        }
        table.detail-table td.col {
            width: 3%;
            text-align: center;
        }
        table.detail-table td.val {
            width: 69%;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .sign-table td {
            text-align: center;
            vertical-align: top;
            width: 50%;
            padding: 4px;
            font-size: 11.5px;
        }
        .sign-space {
            height: 60px;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT -->
    <table class="kop-table">
        <tr>
            <td class="kop-text">
                <h3>PEMERINTAH DAERAH PROVINSI</h3>
                <h2>{{ $setting->school_name ?? 'SMK NEGERI CONTOH' }}</h2>
                <p>{{ $setting->school_address ?? 'Jl. Pendidikan No. 1, Kota' }} | Telp: {{ $setting->school_phone ?? '-' }} | Pos-el: {{ $setting->school_email ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <!-- NOMOR & TANGGAL SURAT -->
    <table class="letter-meta">
        <tr>
            <td style="width: 60%;">
                <table>
                    <tr>
                        <td style="width: 75px;">Nomor</td>
                        <td style="width: 10px;">:</td>
                        <td>421.5 / {{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }} / BK-Kesiswaan / {{ date('Y') }}</td>
                    </tr>
                    <tr>
                        <td>Lampiran</td>
                        <td>:</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>Perihal</td>
                        <td>:</td>
                        <td><strong>Panggilan Orang Tua / Wali Siswa</strong></td>
                    </tr>
                </table>
            </td>
            <td style="width: 40%; text-align: right;">
                {{ $setting->city ?? 'Tempat' }}, {{ now()->translatedFormat('d F Y') }}
            </td>
        </tr>
    </table>

    <!-- TUJUAN SURAT -->
    <div class="recipient">
        Kepada Yth.<br>
        <strong>Bapak / Ibu Orang Tua / Wali Siswa</strong><br>
        dari: <strong>{{ $incident->student_name }}</strong><br>
        di Tempat
    </div>

    <!-- ISI SURAT -->
    <div class="content">
        <p>Dengan hormat,</p>
        <p>
            Sehubungan dengan pembinaan tata tertib dan perkembangan kedisiplinan peserta didik di sekolah, bersama ini kami mengharapkan kehadiran Bapak/Ibu Orang Tua/Wali pada pertemuan koordinasi pembinaan siswa:
        </p>

        <!-- IDENTITAS SISWA -->
        <table class="detail-table">
            <tr>
                <td class="lbl">Nama Siswa</td>
                <td class="col">:</td>
                <td class="val"><strong>{{ $incident->student_name }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">NISN / NIS</td>
                <td class="col">:</td>
                <td class="val">{{ $incident->student?->nisn ?? '-' }} / {{ $incident->student?->nis ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">Kelas</td>
                <td class="col">:</td>
                <td class="val">{{ $incident->classroom?->name ?? ($incident->student?->classroom?->name ?? 'Terdata') }}</td>
            </tr>
            <tr>
                <td class="lbl">Kategori Masalah</td>
                <td class="col">:</td>
                <td class="val"><strong>{{ $incident->category_label }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Uraian Kejadian</td>
                <td class="col">:</td>
                <td class="val">{{ $incident->description }}</td>
            </tr>
            @if($incident->disciplinary_sanction)
            <tr>
                <td class="lbl">Sanksi / Tindak Lanjut</td>
                <td class="col">:</td>
                <td class="val"><em>{{ $incident->disciplinary_sanction }}</em></td>
            </tr>
            @endif
        </table>

        <p>Pertemuan pembinaan bersama Orang Tua/Wali dijadwalkan pada:</p>

        <!-- WAKTU PERTEMUAN -->
        <table class="detail-table">
            <tr>
                <td class="lbl">Hari / Tanggal</td>
                <td class="col">:</td>
                <td class="val"><strong>{{ now()->addDay()->translatedFormat('l, d F Y') }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Waktu</td>
                <td class="col">:</td>
                <td class="val">Pukul 08.30 WIB s.d. Selesai</td>
            </tr>
            <tr>
                <td class="lbl">Tempat</td>
                <td class="col">:</td>
                <td class="val">Ruang Bimbingan & Konseling (BK) / Ruang Kesiswaan</td>
            </tr>
            <tr>
                <td class="lbl">Menemui</td>
                <td class="col">:</td>
                <td class="val">Wali Kelas / Guru BK / Tim Kedisiplinan Sekolah</td>
            </tr>
        </table>

        <p>
            Mengingat pentingnya koordinasi pembinaan demi masa depan dan kelancaran pendidikan putra/putri Bapak/Ibu, kami sangat mengharapkan kehadiran Bapak/Ibu tepat pada waktunya.
        </p>
        <p>
            Demikian surat panggilan ini kami sampaikan. Atas perhatian, kehadiran, dan kerja sama yang baik, kami ucapkan terima kasih.
        </p>
    </div>

    <!-- TANDA TANGAN -->
    <table class="sign-table">
        <tr>
            <td>
                Wali Kelas,<br><br>
                <div class="sign-space"></div>
                <strong>{{ $incident->classroom?->homeroomTeacher?->name ?? ($incident->student?->classroom?->homeroomTeacher?->name ?? '( .................................................... )') }}</strong><br>
                <span>NIP: {{ $incident->classroom?->homeroomTeacher?->nip ?? '-' }}</span>
            </td>
            <td>
                Guru BK / Tim Kedisiplinan,<br><br>
                <div class="sign-space"></div>
                <strong>{{ $incident->verifier?->name ?? ($incident->recordedBy?->name ?? '( .................................................... )') }}</strong><br>
                <span>NIP: {{ $incident->verifier?->nip ?? '-' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 30px;">
                Mengetahui,<br>
                <strong>{{ $setting->headmaster_position ?? 'Kepala Sekolah' }} / Waka Kesiswaan</strong>
                <div class="sign-space"></div>
                <strong>{{ $setting->headmaster_name ?? '....................................................' }}</strong><br>
                <span>NIP: {{ $setting->headmaster_nip ?? '-' }}</span>
            </td>
        </tr>
    </table>
</body>
</html>