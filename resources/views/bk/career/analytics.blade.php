@extends('layouts.app')

@section('title', 'Analisis Bimbingan Karir & Penyaluran BKK')

@section('content')
<div class="container-xl">
    <!-- Header Dashboard Karir -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="avatar avatar-md bg-teal-lt text-teal rounded-circle">
                    <i class="ti ti-briefcase fs-1"></i>
                </span>
            </div>
            <div class="col">
                <div class="page-pretitle text-uppercase fw-bold text-teal" style="letter-spacing: 0.05rem;">
                    BIMBINGAN KARIR &amp; BURSA KERJA KHUSUS (BKK)
                </div>
                <h2 class="page-title text-dark fw-bold">
                    Analisis Karir &amp; Penyaluran Siswa
                </h2>
                <div class="text-muted small mt-1">
                    Pemetaan rencana pasca-kelulusan (Bekerja, Melanjutkan, Wirausaha), intelijen industri target, dan data penyaluran kerja DUDI.
                </div>
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
                <!-- Selector Tahun Ajaran -->
                <form action="{{ route('bk.career.analytics') }}" method="GET" class="d-flex align-items-center gap-1">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="ti ti-calendar me-1"></i> TP:
                        </span>
                        <select name="academic_year" class="form-select form-select-sm fw-bold border-start-0 ps-0" onchange="this.form.submit()" style="min-width: 140px;">
                            @foreach($availableYears as $ay)
                                <option value="{{ $ay->name }}" {{ $ay->name === $academicYear ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_active ? '(Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <a href="{{ route('bk.dashboard', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Dashboard BK
                </a>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-primary d-none d-md-inline-flex">
                    <i class="ti ti-printer me-1"></i> Cetak Laporan Karir
                </button>
            </div>
        </div>
    </div>

    <!-- 4 Kartu Metrik Utama Pola BMW (Bekerja, Melanjutkan, Wirausaha) -->
    <div class="row row-cards mb-4">
        <!-- 1. Bekerja -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm border-start border-teal border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-teal text-white">
                                <i class="ti ti-tool fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Bekerja (DUDI / Industri)</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $bekerjaCount }} <small class="fs-4 text-muted fw-normal">Siswa</small></div>
                            <div class="small fw-semibold text-teal mt-1">{{ $bekerjaPercent }}% dari responden karir</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Melanjutkan Kuliah -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm border-start border-primary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-primary text-white">
                                <i class="ti ti-school fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Melanjutkan Kuliah (PTN/PTS)</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $melanjutkanCount }} <small class="fs-4 text-muted fw-normal">Siswa</small></div>
                            <div class="small fw-semibold text-primary mt-1">{{ $melanjutkanPercent }}% dari responden karir</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Wirausaha -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm border-start border-warning border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-warning text-white">
                                <i class="ti ti-bulb fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Wirausaha Mandiri</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $wirausahaCount }} <small class="fs-4 text-muted fw-normal">Siswa</small></div>
                            <div class="small fw-semibold text-warning mt-1">{{ $wirausahaPercent }}% dari responden karir</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Belum Memutuskan / Respon Karir -->
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm border-0 shadow-sm border-start border-secondary border-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md rounded bg-secondary text-white">
                                <i class="ti ti-help fs-1"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="text-secondary small fw-bold text-uppercase">Belum Memutuskan</div>
                            <div class="h1 fw-bold mb-0 text-dark">{{ $belumMemutuskanCount }} <small class="fs-4 text-muted fw-normal">Siswa</small></div>
                            <div class="small text-muted mt-1">Total Profil: {{ $totalProfiles }} / {{ $totalStudents }} Siswa</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2 Kolom Analisis Top Industri & Top Kampus -->
    <div class="row g-3 mb-4">
        <!-- Top Industri Mitra DUDI -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-building-factory-2 me-1 text-teal"></i> Top 5 Sektor Industri Peminatan Kerja (BKK)
                    </h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Bidang Industri Target</th>
                                    <th class="text-center" style="width: 120px;">Jumlah Siswa</th>
                                    <th class="text-center" style="width: 100px;">Porsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topIndustries as $ind)
                                @php
                                    $indPct = $bekerjaCount > 0 ? round(($ind->total / $bekerjaCount) * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <i class="ti ti-check text-teal me-1"></i> {{ $ind->target_industry }}
                                    </td>
                                    <td class="text-center fw-bold">{{ $ind->total }} Siswa</td>
                                    <td class="text-center">
                                        <span class="badge bg-teal-lt">{{ $indPct }}%</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Belum ada data industri target yang diisi.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Perguruan Tinggi Target -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h4 class="card-title fw-bold text-dark m-0">
                        <i class="ti ti-certificate me-1 text-primary"></i> Top 5 Kampus / Perguruan Tinggi Target
                    </h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Nama Perguruan Tinggi Target</th>
                                    <th class="text-center" style="width: 120px;">Jumlah Siswa</th>
                                    <th class="text-center" style="width: 100px;">Porsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topUniversities as $univ)
                                @php
                                    $univPct = $melanjutkanCount > 0 ? round(($univ->total / $melanjutkanCount) * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <i class="ti ti-school text-primary me-1"></i> {{ $univ->target_university }}
                                    </td>
                                    <td class="text-center fw-bold">{{ $univ->total }} Siswa</td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-lt">{{ $univPct }}%</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Belum ada data kampus target yang diisi.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Tabel Siswa Siap Salur BKK -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="card-title fw-bold text-dark m-0">
                    <i class="ti ti-list-search me-1 text-primary"></i> Direktori Siswa &amp; Penyaluran Kerja BKK
                </h4>
                <div class="text-muted small mt-1">
                    Filter kandidat siswa berdasarkan minat kerja, bidang industri target, atau jurusan untuk rekrutmen mitra DUDI.
                </div>
            </div>
        </div>
        <div class="card-body border-bottom bg-light-subtle py-2">
            <form action="{{ route('bk.career.analytics') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">

                <div class="col-md-3">
                    <select name="plan" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Rencana Pasca-Lulus --</option>
                        <option value="bekerja" {{ $planFilter === 'bekerja' ? 'selected' : '' }}>Bekerja (DUDI / Industri)</option>
                        <option value="melanjutkan" {{ $planFilter === 'melanjutkan' ? 'selected' : '' }}>Melanjutkan Kuliah</option>
                        <option value="wirausaha" {{ $planFilter === 'wirausaha' ? 'selected' : '' }}>Wirausaha Mandiri</option>
                        <option value="belum_memutuskan" {{ $planFilter === 'belum_memutuskan' ? 'selected' : '' }}>Belum Memutuskan</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas / Rombel --</option>
                        @foreach($classrooms as $cls)
                            <option value="{{ $cls->id }}" {{ $classroomId == $cls->id ? 'selected' : '' }}>
                                {{ $cls->name }} ({{ $cls->department?->code ?? 'Umum' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="ti ti-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, industri, posisi, atau kampus..." value="{{ $search }}">
                    </div>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
                    @if($planFilter || $classroomId || $search)
                        <a href="{{ route('bk.career.analytics', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th class="w-1 text-center">No</th>
                        <th>Nama Siswa</th>
                        <th>Kelas / Jurusan</th>
                        <th>Rencana Kelulusan</th>
                        <th>Spesifikasi Target Karir / Pendidikan</th>
                        <th>Keahlian / Hobi</th>
                        <th class="w-1 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($candidates as $idx => $st)
                    @php
                        $cp = $st->careerProfiles->first();
                        $plan = $cp?->post_graduation_plan;
                        $planBadge = match($plan) {
                            'bekerja' => '<span class="badge bg-teal-lt fw-bold fs-6"><i class="ti ti-tool me-1"></i> Bekerja</span>',
                            'melanjutkan' => '<span class="badge bg-purple-lt fw-bold fs-6"><i class="ti ti-school me-1"></i> Kuliah</span>',
                            'wirausaha' => '<span class="badge bg-orange-lt fw-bold fs-6"><i class="ti ti-bulb me-1"></i> Wirausaha</span>',
                            default => '<span class="badge bg-secondary-lt fs-6">Belum Tahu</span>',
                        };
                    @endphp
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $candidates->firstItem() + $idx }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $st->name }}</div>
                            <small class="text-muted">NISN: {{ $st->nisn ?? '-' }} | No HP: {{ $st->phone ?: ($st->parent_phone ?: '-') }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $st->classroom?->name ?? '-' }} ({{ $st->classroom?->department?->code ?? '-' }})
                            </span>
                        </td>
                        <td>
                            {!! $planBadge !!}
                        </td>
                        <td>
                            @if($plan === 'bekerja')
                                <div class="fw-semibold text-dark">{{ $cp->target_industry ?: 'Industri Umum' }}</div>
                                <small class="text-muted">Posisi Impian: {{ $cp->target_job_position ?: '-' }}</small>
                            @elseif($plan === 'melanjutkan')
                                <div class="fw-semibold text-primary">{{ $cp->target_university ?: 'Perguruan Tinggi' }}</div>
                                <small class="text-muted">Prodi: {{ $cp->target_study_program ?: '-' }}</small>
                            @elseif($plan === 'wirausaha')
                                <div class="fw-semibold text-warning">{{ $cp->entrepreneurship_type ?: 'Wirausaha' }}</div>
                                <small class="text-muted">{{ Str::limit($cp->business_plan_summary ?? '-', 40) }}</small>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="small text-secondary">{{ Str::limit($cp->skills_to_develop ?: ($cp->talents ?: '-'), 50) }}</div>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('bk.students.history', ['student' => $st, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-primary" title="Buka Buku Pribadi Siswa">
                                <i class="ti ti-address-book me-1"></i> Profil
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="ti ti-search fs-1 mb-2 d-block text-secondary"></i>
                            Tidak ada siswa yang sesuai dengan filter pencarian karir ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white py-2">
            {{ $candidates->links() }}
        </div>
    </div>
</div>
@endsection
