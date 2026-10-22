<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Jadwal Pelajaran — {{ $setting->school_name ?? 'SMK PGRI KRAS' }}</title>
    
    <!-- Tabler Icons & Google Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.3;
        }

        /* Top Bar - Hidden when printing */
        .no-print-bar {
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }

        /* Page Container */
        .print-container {
            max-width: 1120px;
            margin: 20px auto;
        }

        .print-sheet {
            background: #ffffff;
            width: 100%;
            padding: 20px 24px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            page-break-after: always;
            break-after: page;
        }

        .print-sheet:last-child {
            page-break-after: avoid;
            break-after: avoid;
            margin-bottom: 0;
        }

        /* KOP Surat */
        .school-header {
            text-align: center;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
            position: relative;
        }

        .school-title {
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            color: #0f172a;
        }

        .school-subtitle {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 2px;
        }

        .school-meta {
            font-size: 9.5px;
            color: #475569;
            margin-top: 2px;
        }

        /* Schedule Title Section */
        .schedule-title-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 10px;
            padding: 4px 8px;
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
        }

        .schedule-main-title {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }

        .schedule-info-chips {
            display: flex;
            gap: 12px;
            font-size: 10px;
            color: #334155;
            font-weight: 600;
        }

        /* Timetable Table */
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 9.5px;
            table-layout: fixed;
        }

        .timetable-table th, 
        .timetable-table td {
            border: 1px solid #64748b;
            vertical-align: middle;
        }

        .timetable-table th {
            background-color: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
            padding: 5px 2px;
        }

        .timetable-table .col-hari {
            width: 75px;
            font-weight: 700;
            background-color: #f8fafc;
            text-align: center;
            font-size: 10.5px;
        }

        .timetable-table .col-period {
            width: calc((100% - 75px - 60px) / 10);
        }

        .timetable-table .col-break {
            width: 30px;
            background-color: #fef08a !important;
            font-weight: 800;
            font-size: 8px;
            color: #854d0e;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            padding: 4px 2px;
            letter-spacing: 0.05rem;
        }

        .timetable-table td.col-break {
            background-color: #fef9c3 !important;
        }

        .period-header-num {
            font-size: 11px;
            font-weight: 800;
            color: #1e3a8a;
        }

        .period-header-time {
            font-size: 8px;
            color: #64748b;
            font-weight: 500;
        }

        /* Slot Card */
        .slot-box {
            padding: 4px 3px;
            height: 52px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            background-color: #ffffff;
        }

        .slot-title {
            font-weight: 800;
            font-size: 9.5px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .slot-subtitle {
            font-size: 8.5px;
            font-weight: 600;
            color: #2563eb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .slot-time {
            font-size: 7.5px;
            color: #64748b;
            border-top: 0.5px dashed #cbd5e1;
            padding-top: 1px;
            margin-top: 1px;
        }

        .empty-slot {
            height: 52px;
            color: #cbd5e1;
            font-size: 12px;
        }

        /* Signatures Section */
        .signatures-section {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            padding: 0 30px;
            font-size: 10px;
        }

        .signature-box {
            text-align: center;
            width: 240px;
        }

        .signature-space {
            height: 50px;
        }

        .sign-name {
            font-weight: 800;
            text-decoration: underline;
            color: #0f172a;
        }

        .sign-nip {
            font-size: 9px;
            color: #475569;
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

        /* Print Media Styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 6mm 8mm;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .print-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin-bottom: 0 !important;
                width: 100% !important;
            }

            .timetable-table th,
            .timetable-table td {
                border: 1px solid #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .timetable-table .col-break {
                background-color: #fef08a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .timetable-table td.col-break {
                background-color: #fef9c3 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- Print Action Bar (Hidden on Print) -->
    <div class="no-print-bar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="ti ti-printer" style="font-size: 22px; color: #2563eb;"></i>
            <div>
                <strong style="font-size: 14px; color: #0f172a;">Pratinjau Cetak Jadwal Pelajaran</strong>
                <div style="font-size: 11px; color: #64748b;">
                    Mode: {{ $viewType === 'class' ? 'Jadwal Kelas' : 'Jadwal Guru' }} &bull; Total: {{ count($sheets) }} Lembar &bull; Format: A4 Landscape
                </div>
            </div>
        </div>

        <!-- Controls: Tanggal & Kota Penetapan -->
        <div style="display: flex; align-items: center; gap: 8px; background: #ffffff; padding: 5px 12px; border-radius: 6px; border: 1.5px solid #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 4px;">
                <i class="ti ti-map-pin" style="color: #64748b; font-size: 13px;"></i>
                <input type="text" id="inputPenetapanCity" value="{{ $penetapanCity ?? 'Kediri' }}" 
                       placeholder="Kota" title="Kota Penetapan / Tanda Tangan"
                       style="font-size: 11.5px; font-weight: 600; width: 75px; padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none;"
                       oninput="updatePenetapanCity(this.value)">
                <span style="color: #64748b; font-weight: 700;">,</span>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <i class="ti ti-calendar-event" style="color: #2563eb; font-size: 15px;"></i>
                <label for="inputPenetapanDate" style="font-size: 11px; font-weight: 700; color: #1e293b; margin: 0; white-space: nowrap;">
                    Tgl Penetapan:
                </label>
                <input type="date" id="inputPenetapanDate" value="{{ $rawPenetapanDate ?? now()->format('Y-m-d') }}" 
                       title="Pilih tanggal penetapan / pengesahan jadwal"
                       style="font-size: 11.5px; font-weight: 600; padding: 4px 8px; border: 1.5px solid #2563eb; border-radius: 4px; outline: none; cursor: pointer;"
                       onchange="updatePenetapanDate(this.value)">
            </div>
            <span style="font-size: 10px; color: #64748b;">(Bisa diedit langsung)</span>
        </div>

        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="ti ti-printer"></i> Cetak Sekarang (Print / PDF)
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="ti ti-x"></i> Tutup
            </button>
        </div>
    </div>

    <!-- Printable Sheets Container -->
    <div class="print-container">
        @foreach($sheets as $sheet)
            @php
                $entity = $sheet['entity'];
                $grid = $sheet['grid'];
                $total = $sheet['total'];
            @endphp

            <div class="print-sheet">
                <!-- KOP Surat Sekolah -->
                <div class="school-header">
                    <div class="school-title">{{ $setting->school_name ?? 'SMK PGRI KRAS KEDIRI' }}</div>
                    <div class="school-subtitle">KURIKULUM OPERASIONAL SATUAN PENDIDIKAN (KOSP)</div>
                    <div class="school-meta">
                        {{ $setting->school_address ?? 'Jl. Raya Kras, Ds. Kras, Kec. Kras, Kab. Kediri' }} &bull; 
                        Telp: {{ $setting->school_phone ?? '(0354) 771234' }} &bull; 
                        Website: {{ $setting->school_website ?? 'smkpgrikras.sch.id' }}
                    </div>
                </div>

                <!-- Schedule Title Info -->
                <div class="schedule-title-box">
                    <div>
                        <div class="schedule-main-title">
                            @if($viewType === 'class')
                                JADWAL PELAJARAN KELAS: {{ $entity->name }}
                            @else
                                JADWAL MENGAJAR GURU: {{ $entity->name }}
                            @endif
                        </div>
                    </div>
                    <div class="schedule-info-chips">
                        <span><strong>Tahun Pelajaran:</strong> {{ $academicYear }}</span>
                        <span>&bull;</span>
                        @if($viewType === 'class')
                            <span><strong>Jurusan:</strong> {{ $entity->department?->name ?? 'Umum' }}</span>
                            @if($entity->homeroomTeacher)
                                <span>&bull;</span>
                                <span><strong>Wali Kelas:</strong> {{ $entity->homeroomTeacher->name }}</span>
                            @endif
                            <span>&bull;</span>
                            <span><strong>Total:</strong> {{ $total }} Slot</span>
                        @else
                            <span><strong>Nama Lengkap:</strong> {{ $entity->name }}</span>
                            <span>&bull;</span>
                            <span><strong>Beban Mengajar:</strong> {{ $total }} JP / Minggu</span>
                        @endif
                    </div>
                </div>

                <!-- Timetable Grid Table -->
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th class="col-hari">HARI</th>
                            @for($p = 1; $p <= 10; $p++)
                                @if($p == 4)
                                    <th class="col-break">IST 1</th>
                                @endif
                                @if($p == 8)
                                    <th class="col-break">IST 2</th>
                                @endif
                                <th class="col-period">
                                    <div class="period-header-num">Jam {{ $p }}</div>
                                    <div class="period-header-time">{{ $periods[$p]['start'] }}-{{ $periods[$p]['end'] }}</div>
                                </th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($days as $day)
                            <tr>
                                <td class="col-hari">{{ $day }}</td>

                                @php $skipCount = 0; @endphp
                                @for($p = 1; $p <= 10; $p++)
                                    @if($p == 4)
                                        <td class="col-break"></td>
                                    @endif
                                    @if($p == 8)
                                        <td class="col-break"></td>
                                    @endif

                                    @if($skipCount > 0)
                                        @php $skipCount--; continue; @endphp
                                    @endif

                                    @php $slot = $grid[$day][$p] ?? null; @endphp

                                    @if($slot && $slot['is_first'])
                                        @php
                                            $colSpan = $slot['span'];
                                            $skipCount = $colSpan - 1;
                                            $raw = $slot['raw'];
                                        @endphp
                                        <td colspan="{{ $colSpan }}" style="padding: 2px;">
                                            <div class="slot-box">
                                                <div>
                                                    <div class="slot-title">{{ $slot['title'] }}</div>
                                                    <div class="slot-subtitle">{{ $slot['subtitle'] }}</div>
                                                </div>
                                                <div class="slot-time">
                                                    @if($slot['start'] === $slot['end'])
                                                        Jam {{ $slot['start'] }}
                                                    @else
                                                        Jam {{ $slot['start'] }}-{{ $slot['end'] }}
                                                    @endif
                                                    ({{ $slot['start_time'] ?? $raw->start_time }}-{{ $slot['end_time'] ?? $raw->end_time }})
                                                </div>
                                            </div>
                                        </td>
                                    @elseif(!$slot)
                                        <td>
                                            <div class="empty-slot">&mdash;</div>
                                        </td>
                                    @endif
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Signatures -->
                <div class="signatures-section">
                    <div class="signature-box">
                        <div>Mengetahui,</div>
                        <div>Kepala {{ $setting->school_name ?? 'SMK PGRI KRAS' }}</div>
                        <div class="signature-space"></div>
                        <div class="sign-name">{{ $principal?->name ?? 'Dra. ENDANG PATMOWATI' }}</div>
                        <div class="sign-nip">NIP. {{ $principal?->nip ?? '-' }}</div>
                    </div>
                    <div class="signature-box">
                        <div><span class="sign-city">{{ $penetapanCity ?? 'Kediri' }}</span>, <span class="sign-date" contenteditable="true" title="Klik untuk mengedit langsung tanggal penetapan">{{ $formattedPenetapanDate ?? now()->translatedFormat('d F Y') }}</span></div>
                        <div>Waka Bidang Kurikulum</div>
                        <div class="signature-space"></div>
                        <div class="sign-name">{{ $waka?->name ?? 'ROKHIM, S.Pd.' }}</div>
                        <div class="sign-nip">NIP. {{ $waka?->nip ?? '-' }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

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
