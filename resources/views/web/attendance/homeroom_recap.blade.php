@extends('layouts.web')

@section('title', 'Rekap Presensi Siswa Kelas (Wali Kelas)')

@section('content')
<div class="container-xl">
    <!-- Header Halaman -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted text-uppercase">
                    Wali Kelas &bull; Presensi Terpadu
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Kembali ke Beranda">
                        <i class="ti ti-arrow-left"></i>
                    </a>
                    <h2 class="page-title text-dark fw-bold mb-0">
                        <i class="ti ti-clipboard-list text-primary me-1 fs-1"></i>
                        Rekap Presensi Siswa Kelas {{ $selectedClassroom?->name ?? '' }}
                    </h2>
                </div>
                <div class="text-secondary mt-1">
                    Penggabungan absensi seluruh guru mata pelajaran per jam pelajaran (JP), rekap mingguan, dan rekapitulasi bulanan.
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <a href="{{ route('attendance.homeroom-recap.export-excel', request()->query()) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-file-spreadsheet me-1"></i> Ekspor Excel
                </a>
                <a href="{{ route('attendance.homeroom-recap.download-pdf', request()->query()) }}" class="btn btn-sm btn-outline-danger">
                    <i class="ti ti-file-text me-1"></i> Unduh PDF
                </a>
                <a href="{{ route('attendance.homeroom-permits.index', ['classroom_id' => $selectedClassroomId]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-file-certificate me-1"></i> Izin Siswa Perwalian
                </a>
                <a href="{{ route('attendance.homeroom-recap.download-pdf', array_merge(request()->query(), ['action' => 'print'])) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-printer me-1"></i> Cetak Dokumen
                </a>
            </div>
        </div>
    </div>

    @if($myClassrooms->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ti ti-users-minus fs-1 text-muted d-block mb-2"></i>
                <h4 class="text-dark fw-bold">Bukan Wali Kelas Aktif</h4>
                <p class="text-muted small">Menu ini diperuntukkan bagi Bapak/Ibu Guru yang mengemban tugas tambahan sebagai Wali Kelas aktif.</p>
            </div>
        </div>
    @else
        <!-- Filter Card: Kelas, Mode Rekap & Tanggal / Rentang -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-2 px-3">
                <form action="{{ route('attendance.homeroom-recap.index') }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    @if($viewType)
                        <input type="hidden" name="view_type" value="{{ $viewType }}">
                    @endif

                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Pilih Kelas</label>
                        <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach($myClassrooms as $c)
                                <option value="{{ $c->id }}" {{ $selectedClassroomId == $c->id ? 'selected' : '' }}>
                                    Kelas {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(in_array($mode, ['daily', 'weekly', 'monthly']))
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Tanggal Acuan</label>
                            <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" onchange="this.form.submit()">
                        </div>
                    @elseif($mode === 'semester')
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Pilih Semester</label>
                            <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="ganjil" {{ ($activeSemester ?? '') === 'ganjil' ? 'selected' : '' }}>Semester Ganjil (Jul - Des)</option>
                                <option value="genap" {{ ($activeSemester ?? '') === 'genap' ? 'selected' : '' }}>Semester Genap (Jan - Jun)</option>
                            </select>
                        </div>
                    @elseif($mode === 'custom')
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDateStr }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDateStr }}">
                        </div>
                        <div class="col-md-1 pt-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="ti ti-check me-0.5"></i> Set
                            </button>
                        </div>
                    @else
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Tahun Ajaran</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="{{ $academicYear }} (Penuh 1 Tahun)" readonly>
                        </div>
                    @endif

                    <div class="{{ $mode === 'custom' ? 'col-md-4' : 'col-md-6' }} text-md-end pt-md-3 d-flex flex-wrap align-items-center justify-content-md-end gap-1">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'daily'])) }}" 
                               class="btn {{ $mode === 'daily' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Harian per Jam Pelajaran">
                                <i class="ti ti-clock-hour-4 me-0.5"></i> Harian
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'weekly'])) }}" 
                               class="btn {{ $mode === 'weekly' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Mingguan Senin-Jumat">
                                <i class="ti ti-calendar-week me-0.5"></i> Mingguan
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'monthly'])) }}" 
                               class="btn {{ $mode === 'monthly' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Bulanan">
                                <i class="ti ti-calendar-month me-0.5"></i> Bulanan
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'semester'])) }}" 
                               class="btn {{ $mode === 'semester' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Semester Ganjil / Genap">
                                <i class="ti ti-school me-0.5"></i> Semester
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'yearly'])) }}" 
                               class="btn {{ $mode === 'yearly' ? 'btn-primary' : 'btn-outline-secondary' }}" title="1 Tahun Ajaran Penuh">
                                <i class="ti ti-calendar-stats me-0.5"></i> Tahunan
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['mode' => 'custom', 'start_date' => $startDateStr, 'end_date' => $endDateStr])) }}" 
                               class="btn {{ $mode === 'custom' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Rentang Tanggal Tertentu">
                                <i class="ti ti-calendar-time me-0.5"></i> Kustom
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- 1. MODE HARIAN: TABEL MATRIKS JAM PELAJARAN (JP 1 S.D N) --}}
        {{-- ======================================================== --}}
        @if($mode === 'daily')
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0">
                            Matriks Presensi Harian: {{ $dayName }}, {{ $currentDate->translatedFormat('d F Y') }}
                        </h4>
                        <span class="text-muted small">Status kehadiran siswa per jam pelajaran terintegrasi</span>
                    </div>

                    <div class="d-flex align-items-center gap-1">
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'daily', 'date' => $prevDate]) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Hari Sebelumnya">
                            <i class="ti ti-chevron-left"></i>
                        </a>
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'daily', 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-ghost-primary">
                            Hari Ini
                        </a>
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'daily', 'date' => $nextDate]) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Hari Berikutnya">
                            <i class="ti ti-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Ringkasan Statistik Kartu -->
                @if(!empty($summaryStats))
                    <div class="card-body border-bottom py-2 bg-light-subtle">
                        <div class="row g-2 text-center">
                            <div class="col">
                                <div class="text-muted small">Total Siswa</div>
                                <div class="fw-bold fs-3 text-dark">{{ $summaryStats['total'] }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Hadir Penuh</div>
                                <div class="fw-bold fs-3 text-success">{{ $summaryStats['present_full'] }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Dispen</div>
                                <div class="fw-bold fs-3 text-purple" style="color: #6f42c1;">{{ $summaryStats['dispen'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Terlambat</div>
                                <div class="fw-bold fs-3 text-warning">{{ $summaryStats['late'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Pulang Cepat</div>
                                <div class="fw-bold fs-3 text-info">{{ $summaryStats['early_leave'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Sakit</div>
                                <div class="fw-bold fs-3 text-warning">{{ $summaryStats['sick'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Izin</div>
                                <div class="fw-bold fs-3 text-info">{{ $summaryStats['permit'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Indikasi Bolos</div>
                                <div class="fw-bold fs-3 text-danger">{{ $summaryStats['partial_truant'] }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Alfa Total</div>
                                <div class="fw-bold fs-3 text-danger-subtle" style="color: #b91c1c;">{{ $summaryStats['alfa'] }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-vcenter table-bordered table-striped card-table text-nowrap">
                        <thead class="bg-light text-center">
                            <tr>
                                <th rowspan="2" style="width: 40px;">No</th>
                                <th rowspan="2" class="text-start">Nama Siswa</th>
                                <th rowspan="2" style="width: 100px;">NISN / NIS</th>
                                <th colspan="{{ count($periods) }}" class="text-center bg-primary-lt text-primary fw-bold">
                                    Jam Pelajaran (JP) Ke-
                                </th>
                                <th rowspan="2" style="width: 130px;">Status Akhir</th>
                            </tr>
                            <tr>
                                @foreach($periods as $p)
                                    @php $sessInfo = $sessionsByPeriod[$p] ?? null; @endphp
                                    <th style="min-width: 48px; max-width: 65px;" class="p-1" title="{{ $sessInfo['subject_name'] ?? '-' }} &bull; {{ $sessInfo['teacher_name'] ?? '-' }}">
                                        <div class="fw-bold text-dark">JP {{ $p }}</div>
                                        <div class="text-muted text-truncate" style="font-size: 0.65rem; max-width: 60px;">
                                            {{ Str::limit($sessInfo['subject_name'] ?? '-', 8) }}
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($studentGrid as $idx => $item)
                                @php
                                    $stu = $item['student'];
                                    $permit = $item['permit'];
                                @endphp
                                <tr class="{{ $item['is_truant'] ? 'table-danger' : '' }}">
                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $stu->name }}</div>
                                        @if($permit)
                                            <small class="text-info d-block">
                                                <i class="ti ti-file-text me-0.5"></i> Surat Piket: {{ $permit->permit_type_label }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $stu->nisn ?? $stu->nis ?? '-' }}</td>

                                    <!-- Kolom Per Jam Pelajaran -->
                                    @foreach($periods as $p)
                                        @php
                                            $st = $item['periods'][$p] ?? ['status' => null, 'label' => '-', 'subject' => '-'];
                                            $badgeClass = match($st['status']) {
                                                'h' => 'bg-success text-white',
                                                's' => 'bg-warning text-dark',
                                                'i' => 'bg-info text-white',
                                                'a' => 'bg-danger text-white fw-bold',
                                                'd' => 'bg-purple text-white',
                                                't' => 'bg-warning text-dark fw-bold border border-warning',
                                                'p' => 'bg-purple text-white border border-purple',
                                                default => 'text-muted opacity-50',
                                            };
                                        @endphp
                                        <td class="text-center p-1">
                                            @if($st['status'])
                                                 <span class="badge {{ $badgeClass }}" style="min-width: 26px;" title="{{ $st['subject'] }}">
                                                     {{ $st['label'] }}
                                                 </span>
                                            @else
                                                 <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <!-- Status Akhir Hari Ini -->
                                    <td class="text-center">
                                        <span class="badge {{ $item['final_badge'] }}">
                                             {{ $item['final_status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($periods) + 4 }}" class="text-center py-4 text-muted">
                                        Belum ada data siswa di rombel ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-light py-2">
                    <div class="d-flex align-items-center gap-3 small text-muted flex-wrap">
                        <span><strong>Keterangan:</strong></span>
                        <span><span class="badge bg-success text-white">H</span> Hadir</span>
                        <span><span class="badge bg-purple text-white">D</span> Dispen</span>
                        <span><span class="badge bg-warning text-dark border border-warning">T</span> Terlambat</span>
                        <span><span class="badge bg-purple text-white border border-purple">P</span> Pulang Cepat</span>
                        <span><span class="badge bg-warning text-dark">S</span> Sakit</span>
                        <span><span class="badge bg-info text-white">I</span> Izin</span>
                        <span><span class="badge bg-danger text-white">A</span> Alfa</span>
                        <span><span class="text-muted">-</span> Belum Diabsen</span>
                    </div>
                </div>
            </div>

        {{-- ======================================================== --}}
        {{-- 2. MODE MINGGUAN: TABEL SENIN S.D JUMAT (5 HARI KERJA)   --}}
        {{-- ======================================================== --}}
        @elseif($mode === 'weekly')
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0">
                            Rekap Mingguan: {{ $startOfWeek->translatedFormat('d M') }} s.d. {{ $endOfWeek->translatedFormat('d M Y') }}
                        </h4>
                        <span class="text-muted small">Rekapitulasi status harian per pekan KBM (Senin s.d. Jumat)</span>
                    </div>

                    <div class="d-flex align-items-center gap-1">
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'weekly', 'date' => $prevWeek]) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Pekan Sebelumnya">
                            <i class="ti ti-chevron-left"></i>
                        </a>
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'weekly', 'date' => now()->toDateString()]) }}" class="btn btn-sm btn-ghost-primary">
                            Pekan Ini
                        </a>
                        <a href="{{ route('attendance.homeroom-recap.index', ['classroom_id' => $selectedClassroomId, 'mode' => 'weekly', 'date' => $nextWeek]) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Pekan Berikutnya">
                            <i class="ti ti-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter table-bordered table-striped card-table text-nowrap">
                        <thead class="bg-light text-center">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th class="text-start">Nama Siswa</th>
                                <th style="width: 100px;">NISN / NIS</th>
                                @foreach($weekDays as $dayInfo)
                                    <th style="min-width: 80px;">
                                        <div class="fw-bold">{{ $dayInfo['dayName'] }}</div>
                                        <div class="text-muted small">{{ $dayInfo['formatted'] }}</div>
                                    </th>
                                @endforeach
                                <th style="width: 50px;" class="text-success" title="Hadir">H</th>
                                <th style="width: 50px;" class="text-purple" title="Dispensasi">D</th>
                                <th style="width: 50px;" class="text-warning" title="Terlambat">T</th>
                                <th style="width: 50px;" class="text-info" title="Pulang Cepat">P</th>
                                <th style="width: 50px;" class="text-warning" title="Sakit">S</th>
                                <th style="width: 50px;" class="text-info" title="Izin">I</th>
                                <th style="width: 50px;" class="text-danger" title="Alfa">A</th>
                                <th style="width: 60px;" title="Bolos">Bolos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($studentWeeklyGrid as $idx => $item)
                                @php $stu = $item['student']; @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                    <td class="fw-bold text-dark">{{ $stu->name }}</td>
                                    <td class="text-muted small">{{ $stu->nisn ?? $stu->nis ?? '-' }}</td>

                                    @foreach($weekDays as $dayInfo)
                                        @php $dStat = $item['days'][$dayInfo['date']] ?? ['status' => '-', 'label' => '-', 'badge' => 'text-muted']; @endphp
                                        <td class="text-center">
                                            <span class="badge {{ $dStat['badge'] }}">
                                                {{ $dStat['label'] }}
                                            </span>
                                        </td>
                                    @endforeach

                                    <td class="text-center fw-bold text-success">{{ $item['total_h'] }}</td>
                                    <td class="text-center fw-bold text-purple" style="color: #6f42c1;">{{ $item['total_d'] }}</td>
                                    <td class="text-center fw-bold text-warning">{{ $item['total_t'] }}</td>
                                    <td class="text-center fw-bold text-info">{{ $item['total_p'] }}</td>
                                    <td class="text-center fw-bold text-warning">{{ $item['total_s'] }}</td>
                                    <td class="text-center fw-bold text-info">{{ $item['total_i'] }}</td>
                                    <td class="text-center fw-bold text-danger">{{ $item['total_a'] }}</td>
                                    <td class="text-center">
                                        @if($item['total_truant'] > 0)
                                            <span class="badge bg-danger text-white">{{ $item['total_truant'] }}x</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($weekDays) + 11 }}" class="text-center py-4 text-muted">Belum ada data siswa di rombel ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        {{-- ======================================================== --}}
        {{-- 3. MODE BULANAN, SEMESTERAN, TAHUNAN, KUSTOM: AKUMULASI  --}}
        {{-- ======================================================== --}}
        @else
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h4 class="card-title fw-bold text-dark mb-0">
                            {{ $periodTitle ?? 'Rekapitulasi Presensi' }}
                        </h4>
                        <span class="text-muted small">Total hari efektif KBM: <strong>{{ $totalActiveDays }} Hari</strong></span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Toggle Format Lensa: Internal vs Dapodik/Raport -->
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['view_type' => 'internal'])) }}" 
                               class="btn {{ $viewType !== 'dapodik' ? 'btn-primary' : 'btn-outline-primary' }}" title="Analisis detail kehadiran siswa (8 status)">
                                <i class="ti ti-chart-bar me-1"></i> Analisis Sekolah
                            </a>
                            <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['view_type' => 'dapodik'])) }}" 
                               class="btn {{ $viewType === 'dapodik' ? 'btn-success' : 'btn-outline-success' }}" title="Format resmi pelaporan Dapodik & Raport (H, S, I, A)">
                                <i class="ti ti-certificate me-1"></i> Format Raport / Dapodik
                            </a>
                        </div>

                        @if($mode === 'monthly')
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['date' => $prevMonth])) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Bulan Sebelumnya">
                                    <i class="ti ti-chevron-left"></i>
                                </a>
                                <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['date' => now()->toDateString()])) }}" class="btn btn-sm btn-ghost-primary">
                                    Bulan Ini
                                </a>
                                <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['date' => $nextMonth])) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Bulan Berikutnya">
                                    <i class="ti ti-chevron-right"></i>
                                </a>
                            </div>
                        @elseif($mode === 'semester')
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['semester' => 'ganjil'])) }}" 
                                   class="btn {{ ($activeSemester ?? '') === 'ganjil' ? 'btn-dark' : 'btn-outline-secondary' }}">
                                    Ganjil
                                </a>
                                <a href="{{ route('attendance.homeroom-recap.index', array_merge(request()->query(), ['semester' => 'genap'])) }}" 
                                   class="btn {{ ($activeSemester ?? '') === 'genap' ? 'btn-dark' : 'btn-outline-secondary' }}">
                                    Genap
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                @if($viewType === 'dapodik')
                    <!-- ============================================== -->
                    <!-- TABEL FORMAT RESMI DAPODIK / RAPORT (H, S, I, A) -->
                    <!-- ============================================== -->
                    <div class="card-body bg-success-lt py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="small text-success">
                            <i class="ti ti-info-circle me-1"></i>
                            <strong>Mode Pelaporan Raport & Dapodik:</strong> Terlambat, Dispensasi, Pulang Cepat, & Bolos parsial dihitung sebagai <strong>Hadir (H)</strong>.
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-success bg-white d-print-none" onclick="window.print()">
                            <i class="ti ti-printer me-1"></i> Cetak Format Raport
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter table-bordered table-striped card-table text-nowrap">
                            <thead class="bg-light text-center">
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th class="text-start">Nama Siswa</th>
                                    <th style="width: 110px;">NISN / NIS</th>
                                    <th style="width: 90px;" class="text-success fw-bold">Hadir (H)</th>
                                    <th style="width: 90px;" class="text-warning fw-bold">Sakit (S)</th>
                                    <th style="width: 90px;" class="text-info fw-bold">Izin (I)</th>
                                    <th style="width: 90px;" class="text-danger fw-bold">Alfa (A)</th>
                                    <th style="width: 100px;">% Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentMonthlyGrid as $idx => $item)
                                    @php $stu = $item['student']; @endphp
                                    <tr>
                                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                        <td class="fw-bold text-dark">{{ $stu->name }}</td>
                                        <td class="text-muted small">{{ $stu->nisn ?? $stu->nis ?? '-' }}</td>
                                        <td class="text-center fw-bold text-success fs-3">{{ $item['dapodik_present'] ?? $item['days_present'] }}</td>
                                        <td class="text-center fw-bold text-warning fs-3">{{ $item['dapodik_sick'] ?? $item['days_sick'] }}</td>
                                        <td class="text-center fw-bold text-info fs-3">{{ $item['dapodik_permit'] ?? $item['days_permit'] }}</td>
                                        <td class="text-center fw-bold text-danger fs-3">{{ $item['dapodik_alfa'] ?? $item['days_alfa'] }}</td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <span class="badge {{ $item['attendance_rate'] >= 85 ? 'bg-success text-white' : ($item['attendance_rate'] >= 75 ? 'bg-warning text-dark' : 'bg-danger text-white') }} fw-bold">
                                                    {{ $item['attendance_rate'] }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Belum ada data siswa di rombel ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <!-- ============================================== -->
                    <!-- TABEL ANALISIS DETAIL INTERNAL SEKOLAH (8 STATUS) -->
                    <!-- ============================================== -->
                    <div class="table-responsive">
                        <table class="table table-vcenter table-bordered table-striped card-table text-nowrap">
                            <thead class="bg-light text-center">
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th class="text-start">Nama Siswa</th>
                                    <th style="width: 100px;">NISN / NIS</th>
                                    <th style="width: 70px;" class="text-success">Hadir</th>
                                    <th style="width: 70px;" class="text-purple" style="color: #6f42c1;">Dispen</th>
                                    <th style="width: 70px;" class="text-warning">Telat</th>
                                    <th style="width: 70px;" class="text-info">Pulang</th>
                                    <th style="width: 70px;" class="text-warning">Sakit</th>
                                    <th style="width: 70px;" class="text-info">Izin</th>
                                    <th style="width: 70px;" class="text-danger">Alfa</th>
                                    <th style="width: 70px;">Bolos</th>
                                    <th style="width: 100px;">% Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentMonthlyGrid as $idx => $item)
                                    @php $stu = $item['student']; @endphp
                                    <tr>
                                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                        <td class="fw-bold text-dark">{{ $stu->name }}</td>
                                        <td class="text-muted small">{{ $stu->nisn ?? $stu->nis ?? '-' }}</td>
                                        <td class="text-center fw-bold text-success fs-3">{{ $item['days_present'] }}</td>
                                        <td class="text-center fw-bold text-purple fs-3" style="color: #6f42c1;">{{ $item['days_dispen'] ?? 0 }}</td>
                                        <td class="text-center fw-bold text-warning fs-3">{{ $item['days_late'] ?? 0 }}</td>
                                        <td class="text-center fw-bold text-info fs-3">{{ $item['days_early_leave'] ?? 0 }}</td>
                                        <td class="text-center fw-bold text-warning fs-3">{{ $item['days_sick'] }}</td>
                                        <td class="text-center fw-bold text-info fs-3">{{ $item['days_permit'] }}</td>
                                        <td class="text-center fw-bold text-danger fs-3">{{ $item['days_alfa'] }}</td>
                                        <td class="text-center">
                                            @if($item['days_truant'] > 0)
                                                <span class="badge bg-danger text-white">{{ $item['days_truant'] }} Hari</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <span class="badge {{ $item['attendance_rate'] >= 85 ? 'bg-success text-white' : ($item['attendance_rate'] >= 75 ? 'bg-warning text-dark' : 'bg-danger text-white') }} fw-bold">
                                                    {{ $item['attendance_rate'] }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-4 text-muted">Belum ada data siswa di rombel ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
