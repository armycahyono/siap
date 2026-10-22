<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $reportNumFormatted = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', ' '], '_', $supervision->report_number ?: 'SPV_'.str_pad($supervision->id, 3, '0', STR_PAD_LEFT)));
        $teacherNameClean = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $teacher['name'] ?? $supervision->teacher?->name ?? 'Guru'));
    @endphp
    <title>Laporan_{{ $reportNumFormatted }} ({{ $teacherNameClean }})</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    
    <style>
        :root {
            --navy: #102d52;
            --blue: #195b9e;
            --ink: #1a2a3b;
            --line: #cbd5e1;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f1f5f9;
            font-family: "Source Sans 3", Arial, sans-serif;
            color: var(--ink);
            font-size: 13px;
            line-height: 1.5;
        }

        /* Top Action Bar (hidden on print) */
        .no-print-bar {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: 0.2s;
        }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: var(--navy); }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }

        /* A4 Page Container */
        .print-page {
            width: 210mm;
            min-height: 297mm;
            background: #ffffff;
            margin: 20px auto;
            padding: 20mm 20mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
        }

        /* Kop Surat Resmi */
        .kop-surat {
            text-align: center;
            border-bottom: 3px double var(--navy);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .kop-surat h1 {
            font-family: "Libre Baskerville", serif;
            font-size: 18px;
            color: var(--navy);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .kop-surat h2 {
            font-size: 14px;
            margin: 4px 0 0;
            font-weight: 700;
        }
        .kop-surat p {
            margin: 2px 0 0;
            font-size: 11px;
            color: #475569;
        }

        .report-title-box {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-title-box h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--navy);
            margin: 0;
            text-decoration: underline;
        }
        .report-title-box span {
            font-size: 12px;
            color: #64748b;
        }

        /* Tables */
        .info-table, .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 12px;
        }
        .info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .info-table td.col-label-left {
            width: 17%;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
        }
        .info-table td.col-sep {
            width: 2%;
            text-align: center;
        }
        .info-table td.col-val-left {
            width: 35%;
            padding-right: 18px;
            white-space: nowrap;
        }
        .info-table td.col-label-right {
            width: 16%;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
        }
        .info-table td.col-val-right {
            width: 28%;
        }
        .info-table td.label {
            width: 25%;
            font-weight: 700;
            color: #334155;
        }
        .info-table td.separator {
            width: 2%;
        }

        .data-table th, .data-table td {
            border: 1px solid #475569;
            padding: 6px 8px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: var(--navy);
            font-weight: 700;
            text-align: center;
        }
        .data-table tr.phase-row {
            background-color: #e2e8f0;
            font-weight: 700;
        }

        .section-header {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            border-bottom: 1px solid var(--line);
            padding-bottom: 4px;
            margin: 16px 0 10px;
            text-transform: uppercase;
        }

        .note-box {
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        .note-box b {
            display: block;
            color: #334155;
            margin-bottom: 2px;
        }

        /* Signatures Grid */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            text-align: center;
        }
        .signature-box {
            padding: 10px;
        }
        .sign-date[contenteditable="true"], .sign-city[contenteditable="true"] {
            outline: none;
            border-bottom: 1px dashed transparent;
            cursor: pointer;
            padding: 1px 3px;
            border-radius: 2px;
            transition: all 0.2s ease;
        }
        .sign-date[contenteditable="true"]:hover, .sign-city[contenteditable="true"]:hover,
        .sign-date[contenteditable="true"]:focus, .sign-city[contenteditable="true"]:focus {
            border-bottom: 1px dashed #2563eb;
            background-color: #eff6ff;
        }
        .signature-space {
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .signature-space img {
            max-height: 70px;
        }

        /* Photos Grid */
        .photo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 15px;
        }
        .photo-card {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px;
            text-align: center;
        }
        .photo-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 4px;
        }

        /* Print Media Styles */
        @media print {
            body { background: #fff; }
            .no-print-bar { display: none !important; }
            .print-page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                min-height: auto;
                padding: 15mm 15mm;
                page-break-after: always;
            }
            .print-page:last-child {
                page-break-after: avoid;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <!-- Action Bar -->
    <div class="no-print-bar">
        <div>
            <strong style="color: var(--navy);">Preview Cetak Laporan Supervisi Guru</strong> — {{ $supervision->report_number }}
            @if($supervision->hasSnapshot())
                <span style="display: inline-flex; align-items: center; gap: 4px; background: #e6f4ea; color: #137333; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 8px; font-weight: 700;">
                    <i class="ti ti-lock"></i> ARSIP TERKUNCI (SNAPSHOT PERMANEN)
                </span>
            @endif
        </div>

        <!-- Controls: Tanggal & Kota Penetapan -->
        <div style="display: flex; align-items: center; gap: 8px; background: #ffffff; padding: 4px 12px; border-radius: 6px; border: 1.5px solid #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 4px;">
                <i class="ti ti-map-pin" style="color: #64748b; font-size: 13px;"></i>
                <input type="text" id="inputPenetapanCity" value="{{ $penetapanCity ?? 'Kediri' }}" 
                       placeholder="Kota" title="Kota Penetapan / Tanda Tangan"
                       style="font-size: 11.5px; font-weight: 600; width: 75px; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none;"
                       oninput="updatePenetapanCity(this.value)">
                <span style="color: #64748b; font-weight: 700;">,</span>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <i class="ti ti-calendar-event" style="color: var(--navy); font-size: 15px;"></i>
                <label for="inputPenetapanDate" style="font-size: 11px; font-weight: 700; color: #1e293b; margin: 0; white-space: nowrap;">
                    Tgl Penetapan:
                </label>
                <input type="date" id="inputPenetapanDate" value="{{ $rawPenetapanDate ?? now()->format('Y-m-d') }}" 
                       title="Pilih tanggal penetapan laporan"
                       style="font-size: 11.5px; font-weight: 600; padding: 3px 8px; border: 1.5px solid var(--navy); border-radius: 4px; outline: none; cursor: pointer;"
                       onchange="updatePenetapanDate(this.value)">
            </div>
            <span style="font-size: 10px; color: #64748b;">(Bisa diedit)</span>
        </div>

        <div style="display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="ti ti-printer"></i> CETAK / SIMPAN SEBAGAI PDF
            </button>
            <a href="{{ route('reports.pdf', $supervision) }}" class="btn btn-danger">
                <i class="ti ti-file-type-pdf"></i> Download PDF Server
            </a>
            <a href="{{ route('supervisions.show', $supervision) }}" class="btn btn-secondary">
                <i class="ti ti-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- ================= HALAMAN 1: IDENTITAS & PRAOBSERVASI ================= -->
    <div class="print-page">
        <!-- Kop Surat -->
        <div class="kop-surat">
            <h1>{{ $schoolInfo['name'] }}</h1>
            <h2>SUPERVISI PEMBELAJARAN GURU TAHUN PELAJARAN {{ $supervision->academic_year }}</h2>
            <p>{{ $schoolInfo['address'] }} | Telp: {{ $schoolInfo['phone'] }} | Email: {{ $schoolInfo['email'] }}</p>
        </div>

        <div class="report-title-box">
            <h3>LEMBAR IDENTITAS & CATATAN PRAOBSERVASI</h3>
            <span>Nomor Dokumen: {{ $supervision->report_number ?: 'SPV/AUTO/SMK PGRI KRAS/' . date('Y') }}</span>
        </div>

        <div class="section-header">A. Identitas Guru & KBM</div>
        @php
            $teacherIdLabel = !empty($teacher['nip']) ? 'NIP' : 'NIGS';
            $teacherIdVal = !empty($teacher['nip']) ? $teacher['nip'] : ($teacher['nigs_fix'] ?? $teacher['nigs'] ?? '-');
            $hasValidNuptk = !empty($teacher['nuptk']);
        @endphp
        <table class="info-table">
            <tr>
                <td class="col-label-left">Nama Guru</td>
                <td class="col-sep">:</td>
                <td class="col-val-left"><strong>{{ $teacher['name'] }}</strong></td>
                <td class="col-label-right">{{ $teacherIdLabel }}{{ $hasValidNuptk ? ' / NUPTK' : '' }}</td>
                <td class="col-sep">:</td>
                <td class="col-val-right">{{ $teacherIdVal }}{{ $hasValidNuptk ? ' / ' . $teacher['nuptk'] : '' }}</td>
            </tr>
            <tr>
                <td class="col-label-left">Mata Pelajaran</td>
                <td class="col-sep">:</td>
                <td class="col-val-left"><strong>{{ $supervision->subject }}</strong></td>
                <td class="col-label-right">Kelas</td>
                <td class="col-sep">:</td>
                <td class="col-val-right">{{ $supervision->class_name }}</td>
            </tr>
            <tr>
                <td class="col-label-left">Tahun Pelajaran</td>
                <td class="col-sep">:</td>
                <td class="col-val-left">{{ $supervision->academic_year }}</td>
                <td class="col-label-right">Kons. Keahlian</td>
                <td class="col-sep">:</td>
                <td class="col-val-right">{{ $supervision->expertise_program ?: 'Semua Jurusan' }}</td>
            </tr>
            <tr>
                <td class="col-label-left">Tanggal Supervisi</td>
                <td class="col-sep">:</td>
                <td class="col-val-left">{{ $supervision->schedule_date->translatedFormat('d F Y') }}</td>
                <td class="col-label-right">Nama Supervisor</td>
                <td class="col-sep">:</td>
                <td class="col-val-right">{{ $supervisor['name'] }}</td>
            </tr>
        </table>

        <div class="section-header">B. Instrumen Tanya Jawab Praobservasi</div>
        <div class="note-box">
            <b>1. Materi Pokok Pembelajaran yang Akan Diajarkan:</b>
            {{ $supervision->pre_material ?: '-' }}
        </div>

        <div class="note-box">
            <b>2. Tujuan Pembelajaran (Indikator Ketercapaian):</b>
            {!! nl2br(e($supervision->pre_objectives ?: '-')) !!}
        </div>

        <div class="note-box">
            <b>3. Model / Metode Pembelajaran yang Digunakan:</b>
            {{ $supervision->pre_method ?: '-' }}
        </div>

        <div class="note-box">
            <b>4. Media dan Alat Pembelajaran:</b>
            {{ $supervision->pre_media ?: '-' }}
        </div>

        <div class="note-box">
            <b>5. Strategi Pembelajaran & Pengorganisasian Murid:</b>
            {{ $supervision->pre_strategy ?: '-' }}
        </div>

        <div class="note-box">
            <b>6. Hal yang Menjadi Fokus Amatan:</b>
            {{ $supervision->pre_focus ?: '-' }}
        </div>

        <div class="note-box">
            <b>7. Kesiapan Perangkat Ajar & Pembelajaran:</b>
            {{ $supervision->pre_readiness ?: '-' }}
        </div>

        <div class="note-box" style="border-left: 3px solid var(--blue);">
            <b>8. Catatan Supervisor Sebelum Pelaksanaan Observasi:</b>
            {{ $supervision->pre_supervisor_notes ?: '-' }}
        </div>
    </div>

    <!-- ================= HALAMAN 2: INSTRUMEN OBSERVASI (23 BUTIR) ================= -->
    <div class="print-page page-break">
        <div class="kop-surat">
            <h1>{{ $schoolInfo['name'] }}</h1>
            <p>INSTRUMEN OBSERVASI PEMBELAJARAN KELAS</p>
        </div>

        <div class="report-title-box">
            <h3>HASIL PENILAIAN INDIKATOR KINERJA GURU</h3>
            <span>Guru: {{ $teacher['name'] }} | Mapel: {{ $supervision->subject }} | Tanggal: {{ $supervision->schedule_date->translatedFormat('d F Y') }}</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th>Aspek Indikator Pembelajaran yang Diamati</th>
                    <th style="width: 12%;">Skor (1-4)</th>
                    <th style="width: 35%;">Catatan Pengamatan Supervisor</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $globalNo = 1;
                @endphp
                @foreach($rubricPhases as $phase)
                <tr class="phase-row">
                    <td colspan="4">{{ $phase['name'] }}</td>
                </tr>
                @foreach($phase['indicators'] as $ind)
                <tr>
                    <td style="text-align: center;">{{ $globalNo++ }}</td>
                    <td>{{ $ind['title'] }}</td>
                    <td style="text-align: center; font-weight: bold; font-size: 13px;">{{ $ind['score'] ?? '-' }}</td>
                    <td>{{ $ind['notes'] ?? '-' }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>

        <div style="font-size: 11px; color: #64748b; font-style: italic; margin-top: 5px;">
            Keterangan Skala Skor: 1 = Belum Terlihat · 2 = Mulai Terlihat · 3 = Terlihat · 4 = Sangat Baik
        </div>
    </div>

    <!-- ================= HALAMAN 3: REKAPITULASI, REKOMENDASI & PENGESAHAN ================= -->
    <div class="print-page page-break">
        <div class="kop-surat">
            <h1>{{ $schoolInfo['name'] }}</h1>
            <p>LEMBAR HASIL AKHIR & PENGESAHAN SUPERVISI</p>
        </div>

        <div class="report-title-box">
            <h3>REKAPITULASI HASIL & PENGESAHAN DOKUMEN</h3>
            <span>Nomor Surat Pengesahan: {{ $supervision->report_number }}</span>
        </div>

        <div class="section-header">A. Ringkasan Skor & Predikat Capaian</div>
        <table class="data-table" style="text-align: center;">
            <thead>
                <tr>
                    <th>Komponen Penilaian</th>
                    <th>Total Skor Diperoleh</th>
                    <th>Skor Maksimal Ideal</th>
                    <th>Persentase Ketercapaian</th>
                    <th>Predikat Akhir</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight: 700; text-align: left;">Observasi KBM di Kelas</td>
                    <td style="font-weight: 700; font-size: 14px;">{{ $supervision->total_score }}</td>
                    <td style="font-size: 14px;">{{ $supervision->max_score }}</td>
                    <td style="font-weight: 800; font-size: 15px; color: var(--navy);">{{ $supervision->percentage }}%</td>
                    <td style="font-weight: 800; font-size: 14px;">{{ $supervision->predicate }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-header">B. Catatan Evaluasi & Rekomendasi Pembinaan</div>
        <div class="note-box">
            <b>Catatan Positif / Kelebihan:</b>
            {{ $supervision->post_positive ?: '-' }}
        </div>
        <div class="note-box">
            <b>Hal yang Perlu Ditingkatkan:</b>
            {{ $supervision->post_improvement ?: '-' }}
        </div>
        <div class="note-box">
            <b>Rekomendasi dari Supervisor:</b>
            {{ $supervision->post_recommendation ?: '-' }}
        </div>
        <div class="note-box">
            <b>Rencana Tindak Lanjut:</b>
            {{ $supervision->post_followup ?: '-' }}
        </div>
        @if($supervision->principal_notes)
        <div class="note-box" style="border-left: 3px solid #16a34a; background: #f0fdf4;">
            <b style="color: #166534;">Catatan / Arahan Kepala Sekolah:</b>
            {{ $supervision->principal_notes }}
        </div>
        @endif

        <!-- Lembar Pengesahan Tanda Tangan Basah & Digital QR Code -->
        <div class="signature-grid">
            <!-- Kolom Kepala Sekolah + QR Code Digital (Sebelah Kiri) -->
            <div class="signature-box">
                <div>Mengetahui & Mengesahkan,</div>
                <div>Kepala {{ $schoolInfo['name'] }}</div>
                <div class="signature-space">
                    @if($supervision->approved_by_principal)
                        <img src="{{ $qrCodeData }}" alt="QR Code Validasi Resmi" title="Pindai untuk verifikasi keaslian dokumen">
                    @endif
                </div>
                <div><strong>{{ $principal['name'] }}</strong></div>
                @if(!empty($principal['nip']))
                    <div style="font-size: 11px;">NIP. {{ $principal['nip'] }}</div>
                @endif
            </div>

            <!-- Kolom Supervisor (Sebelah Kanan) -->
            <div class="signature-box">
                <div><span class="sign-city">{{ $penetapanCity ?? 'Kediri' }}</span>, <span class="sign-date" contenteditable="true" title="Klik untuk mengedit langsung tanggal penetapan">{{ $formattedPenetapanDate ?? $supervision->schedule_date->translatedFormat('d F Y') }}</span></div>
                <div>Supervisor Penilai,</div>
                <div class="signature-space">
                    <!-- Tanda tangan basah space -->
                </div>
                <div><strong>{{ $supervisor['name'] }}</strong></div>
                @if(!empty($supervisor['nip']))
                    <div style="font-size: 11px;">NIP. {{ $supervisor['nip'] }}</div>
                @endif
            </div>
        </div>
    </div>

    <!-- ================= HALAMAN 4: DOKUMENTASI FOTO (JIKA ADA) ================= -->
    @if($supervision->photos->count() > 0)
    <div class="print-page page-break">
        <div class="kop-surat">
            <h1>{{ $schoolInfo['name'] }}</h1>
            <p>LAMPIRAN DOKUMENTASI KEGIATAN SUPERVISI</p>
        </div>

        <div class="report-title-box">
            <h3>LAMPIRAN BUKTI FISIK KEGIATAN KBM DI KELAS</h3>
            <span>Guru: {{ $teacher['name'] }} | {{ $supervision->subject }}</span>
        </div>

        <div class="photo-grid">
            @foreach($supervision->photos as $pIndex => $photo)
            <div class="photo-card">
                <img src="{{ $photo->url }}" alt="{{ $photo->caption }}">
                <div style="font-weight: 700; font-size: 12px; margin-top: 6px;">Dokumentasi {{ $pIndex + 1 }}: {{ $photo->category }}</div>
                <div style="font-size: 11px; color: #64748b;">{{ $photo->caption ?: '-' }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <script>
        const indonesianMonths = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        function formatIndonesianDate(dateStr) {
            if (!dateStr) return '';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            const year = parts[0];
            const monthIndex = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            return `${day} ${indonesianMonths[monthIndex]} ${year}`;
        }

        function updatePenetapanDate(val) {
            if (!val) return;
            const formatted = formatIndonesianDate(val);
            document.querySelectorAll('.sign-date').forEach(el => {
                el.innerText = formatted;
            });
        }

        function updatePenetapanCity(val) {
            const city = val.trim() || 'Kediri';
            document.querySelectorAll('.sign-city').forEach(el => {
                el.innerText = city;
            });
        }
    </script>
</body>
</html>
