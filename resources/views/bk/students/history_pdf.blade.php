<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Pribadi Siswa - {{ $student->name }}</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            color: #1a202c;
            line-height: 1.4;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 4px;
        }
        .kop-table td {
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
        }
        .kop-text h2 {
            font-size: 13px;
            margin: 0;
            text-transform: uppercase;
            font-weight: bold;
            color: #0f172a;
        }
        .kop-text h1 {
            font-size: 15px;
            margin: 2px 0;
            text-transform: uppercase;
            font-weight: bold;
            color: #1e3a8a;
        }
        .kop-text p {
            margin: 2px 0 0;
            font-size: 8.5px;
            color: #475569;
        }
        .doc-title {
            text-align: center;
            margin: 10px 0 14px 0;
        }
        .doc-title h3 {
            font-size: 13px;
            margin: 0;
            text-transform: uppercase;
            text-decoration: underline;
            color: #0f172a;
        }
        .doc-title span {
            font-size: 9.5px;
            color: #64748b;
        }
        .section-header {
            font-size: 10.5px;
            font-weight: bold;
            background-color: #f1f5f9;
            padding: 3px 6px;
            margin: 10px 0 5px 0;
            border-left: 3px solid #1e3a8a;
            color: #1e293b;
            text-transform: uppercase;
        }
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.info-table td {
            padding: 2.5px 4px;
            vertical-align: top;
        }
        table.info-table td.label {
            width: 25%;
            color: #475569;
            font-weight: 500;
        }
        table.info-table td.colon {
            width: 2%;
            text-align: center;
        }
        table.info-table td.value {
            width: 73%;
            color: #0f172a;
        }
        table.grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 9.5px;
        }
        table.grid-table th, table.grid-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.grid-table th {
            background-color: #f8fafc;
            color: #1e293b;
            font-weight: bold;
            text-align: left;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 2px;
            background-color: #e2e8f0;
            color: #334155;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sign-table td {
            text-align: center;
            vertical-align: top;
            width: 50%;
            padding: 4px;
            font-size: 10px;
        }
        .sign-space {
            height: 50px;
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
        <h3>BUKU PRIBADI SISWA DIGITAL (REKAPITULASI BK)</h3>
        <span>Tahun Pelajaran: {{ $academicYear }} &bull; Tanggal Cetak: {{ now()->translatedFormat('d F Y') }}</span>
    </div>

    <!-- 1. IDENTITAS SISWA -->
    <div class="section-header">1. Identitas Pribadi Siswa</div>
    <table class="info-table">
        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $student->name }}</strong> ({{ $student->gender === 'L' ? 'Laki-laki' : 'Perempuan' }})</td>
        </tr>
        <tr>
            <td class="label">NISN / NIS</td>
            <td class="colon">:</td>
            <td class="value">{{ $student->nisn ?? '-' }} / {{ $student->nis ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tempat, Tanggal Lahir</td>
            <td class="colon">:</td>
            <td class="value">{{ $student->pob ?? '-' }}, {{ $student->dob ? \Carbon\Carbon::parse($student->dob)->translatedFormat('d F Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kelas Saat Ini</td>
            <td class="colon">:</td>
            <td class="value">{{ $currentClassroom?->name ?? 'Belum Terdata' }} &bull; Konsentrasi Keahlian: {{ $student->classroom?->department?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nama Orang Tua / Wali</td>
            <td class="colon">:</td>
            <td class="value">Ayah: {{ $student->father_name ?? '-' }} | Ibu: {{ $student->mother_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alamat Domisili</td>
            <td class="colon">:</td>
            <td class="value">{{ $student->address ?? '-' }}</td>
        </tr>
    </table>

    <!-- 2. PEMINATAN & RENCANA KARIER -->
    <div class="section-header">2. Peminatan, Bakat & Rencana Karier Siswa</div>
    <table class="info-table">
        <tr>
            <td class="label">Arah Rencana Karier</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $careerProfile?->career_path_label ?? 'Belum ditentukan' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Target Pilihan Utama</td>
            <td class="colon">:</td>
            <td class="value">{{ $careerProfile?->target_choice ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Minat & Bakat Menonjol</td>
            <td class="colon">:</td>
            <td class="value">Minat: {{ $careerProfile?->interest_notes ?? '-' }} | Bakat: {{ $careerProfile?->talent_notes ?? '-' }}</td>
        </tr>
    </table>

    <!-- 3. PRESTASI & PENGHARGAAN -->
    @if($achievements->isNotEmpty())
        <div class="section-header">3. Prestasi & Penghargaan</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 40%;">Nama Prestasi / Kegiatan</th>
                    <th style="width: 25%;">Tingkat / Kategori</th>
                    <th style="width: 20%;">Peringkat / Hasil</th>
                </tr>
            </thead>
            <tbody>
                @foreach($achievements as $ach)
                    <tr>
                        <td>{{ $ach->achievement_date ? $ach->achievement_date->format('d/m/Y') : '-' }}</td>
                        <td><strong>{{ $ach->title }}</strong><br><span style="color:#64748b;">{{ $ach->organizer }}</span></td>
                        <td>{{ ucfirst($ach->level ?? '-') }} ({{ ucfirst($ach->category ?? '-') }})</td>
                        <td>{{ $ach->rank ?? 'Peserta / Juara' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 4. RIWAYAT SESI LAYANAN BK -->
    <div class="section-header">4. Rekam Jejak Layanan Bimbingan & Konseling</div>
    @if($services->isEmpty())
        <p style="font-size: 9.5px; color: #64748b; font-style: italic; margin: 4px 0 8px 0;">Tidak ada catatan masalah/layanan konseling untuk tahun ajaran ini (kondisi siswa baik).</p>
    @else
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 18%;">Bidang Layanan</th>
                    <th style="width: 32%;">Ringkasan Masalah & Hasil</th>
                    <th style="width: 35%;">Rencana Tindak Lanjut (RTL)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $srv)
                    <tr>
                        <td>{{ $srv->service_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $srv->service_type_label }}</strong></td>
                        <td>
                            <div>{{ Str::limit($srv->summary, 80) }}</div>
                            <div style="color: #1e3a8a; font-size: 8.5px; margin-top: 2px;"><em>Hasil:</em> {{ Str::limit($srv->result, 60) }}</div>
                        </td>
                        <td>
                            @if($srv->follow_up_plan)
                                <div>{{ $srv->follow_up_plan }}</div>
                                <div style="font-size: 8.5px; color: #475569; margin-top: 2px;">
                                    Status: <strong>{{ $srv->follow_up_status_label }}</strong>
                                    @if($srv->follow_up_target_date)
                                        | Target: {{ $srv->follow_up_target_date->format('d/m/Y') }}
                                    @endif
                                </div>
                            @else
                                <span style="color:#94a3b8;">- Selesai di tempat -</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 5. RIWAYAT VISITASI SISWA (JIKA ADA) -->
    @if($visitations->isNotEmpty())
        <div class="section-header">5. Riwayat Kunjungan Rumah (Home Visit) & Pemantauan</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 20%;">Jenis Kunjungan</th>
                    <th style="width: 30%;">Petugas Pelaksana</th>
                    <th style="width: 35%;">Hasil Kesepakatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($visitations as $vis)
                    <tr>
                        <td>{{ $vis->actual_date ? $vis->actual_date->format('d/m/Y') : $vis->planned_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $vis->visit_type_label }}</strong></td>
                        <td>{{ $vis->primaryOfficer?->name ?? 'Guru BK' }} {{ $vis->secondaryOfficer ? '& ' . $vis->secondaryOfficer->name : '' }}</td>
                        <td>{{ Str::limit($vis->result_summary, 120) ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 6. CATATAN KEDISIPLINAN RESMI (JIKA ADA) -->
    @if($incidents->isNotEmpty())
        <div class="section-header">6. Rekaman Pelanggaran Kedisiplinan Terverifikasi</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 25%;">Kategori Insiden</th>
                    <th style="width: 35%;">Uraian Kejadian</th>
                    <th style="width: 25%;">Sanksi / Pembinaan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidents as $inc)
                    <tr>
                        <td>{{ $inc->incident_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $inc->category_label }}</strong></td>
                        <td>{{ Str::limit($inc->description, 100) }}</td>
                        <td>{{ $inc->disciplinary_sanction ?? 'Pembinaan melekat di sekolah' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- TANDA TANGAN -->
    <table class="sign-table">
        <tr>
            <td>
                Mengetahui,<br>
                <strong>{{ $setting->headmaster_position ?? 'Kepala Sekolah' }}</strong>
                <div class="sign-space"></div>
                <strong>{{ $setting->headmaster_name ?? '....................................................' }}</strong><br>
                <span style="font-size: 8.5px; color: #475569;">NIP: {{ $setting->headmaster_nip ?? '-' }}</span>
            </td>
            <td>
                {{ $setting->city ?? 'Tempat' }}, {{ now()->translatedFormat('d F Y') }}<br>
                Koordinator / Guru BK Pembimbing,<br>
                <strong>Bimbingan & Konseling</strong>
                <div class="sign-space"></div>
                <strong>( .................................................... )</strong><br>
                <span style="font-size: 8.5px; color: #475569;">Guru Pembimbing Siswa</span>
            </td>
        </tr>
    </table>
</body>
</html>