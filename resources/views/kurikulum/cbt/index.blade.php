@extends('layouts.app')

@section('title', 'Integrasi GarudaCBT')

@section('content')
<!-- Page Header (Model 2: Hero Context Banner) -->
@php
    $cbtConnBadge = $status['connected'] 
        ? '<span class="badge bg-success text-white shadow-xs me-2"><i class="ti ti-circle-check me-1"></i> Terhubung (db_cbt)</span>'
        : '<span class="badge bg-danger text-white shadow-xs me-2"><i class="ti ti-alert-circle me-1"></i> Terputus</span>';
    $cbtActions = $cbtConnBadge . 
        '<a href="http://localhost/CBT/" target="_blank" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-external-link me-1 text-info"></i> Buka Portal CBT</a> ' .
        '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" onclick="confirmSync(\'all\', \'Semua Data Pokok, Guru, Kelas, dan Siswa Aktif\')"><i class="ti ti-refresh me-1"></i> Sinkronkan Semua ke CBT</button>';
@endphp
<div class="container-xl px-0 mb-3">
    @include('layouts._hero_header', [
        'title' => 'Integrasi GarudaCBT',
        'subtitle' => 'Sinkronisasi dua arah data akademik SIAP (Source of Truth) dengan mesin ujian online GarudaCBT.',
        'icon' => 'ti ti-plug-connected',
        'category' => 'KURIKULUM & ASESMEN',
        'actions' => $cbtActions
    ])
</div>

<div class="page-body">
    <div class="container-xl">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible" role="alert">
                <div class="d-flex">
                    <div><i class="ti ti-check icon alert-icon"></i></div>
                    <div>{{ session('success') }}</div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible" role="alert">
                <div class="d-flex">
                    <div><i class="ti ti-alert-triangle icon alert-icon"></i></div>
                    <div>{{ session('error') }}</div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        @endif

        <!-- Metric Cards: SIAP vs CBT -->
        <div class="row row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-primary text-white avatar">
                                    <i class="ti ti-calendar"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium">Tahun & Semester Aktif</div>
                                <div class="text-muted small">
                                    SIAP: <strong>{{ $status['active_academic_year'] ?? '-' }} ({{ $status['active_semester'] ?? '-' }})</strong><br>
                                    CBT: <strong>{{ $status['cbt']['active_tp'] ?? '-' }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-green text-white avatar">
                                    <i class="ti ti-school"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium">Siswa Aktif Terdaftar</div>
                                <div class="text-muted small">
                                    SIAP: <strong>{{ $status['siap']['active_students'] ?? 0 }}</strong> siswa<br>
                                    CBT: <strong>{{ $status['cbt']['siswa'] ?? 0 }}</strong> akun ujian
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-azure text-white avatar">
                                    <i class="ti ti-user-check"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium">Guru / Penguji</div>
                                <div class="text-muted small">
                                    SIAP: <strong>{{ $status['siap']['teachers'] ?? 0 }}</strong> guru<br>
                                    CBT: <strong>{{ $status['cbt']['guru'] ?? 0 }}</strong> guru terdaftar
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-purple text-white avatar">
                                    <i class="ti ti-chalkboard"></i>
                                </span>
                            </div>
                            <div class="col">
                                <div class="font-weight-medium">Kelas / Rombel</div>
                                <div class="text-muted small">
                                    SIAP: <strong>{{ $status['siap']['classrooms'] ?? 0 }}</strong> kelas<br>
                                    CBT: <strong>{{ $status['cbt']['kelas'] ?? 0 }}</strong> kelas CBT
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Actions Panel -->
        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="ti ti-upload me-2 text-primary"></i>Panel Sinkronisasi Data SIAP &rarr; GarudaCBT</h3>
                    <p class="card-subtitle">Pilih entitas data yang ingin diperbarui secara selektif atau sekaligus.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light-lt h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h4 class="mb-1"><i class="ti ti-calendar-event me-1"></i> Tahun & Semester</h4>
                                <p class="text-muted small mb-2">Sinkronkan nama tahun ajaran dan aktifkan semester berjalan.</p>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('tp', 'Tahun Pelajaran')">Sync TP</button>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('semester', 'Semester Aktif')">Sync Semester</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light-lt h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h4 class="mb-1"><i class="ti ti-books me-1"></i> Jurusan & Mata Pelajaran</h4>
                                <p class="text-muted small mb-2">Perbarui daftar program keahlian dan mata pelajaran kurikulum.</p>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('jurusan', 'Program Keahlian/Jurusan')">Sync Jurusan</button>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('mapel', 'Mata Pelajaran')">Sync Mapel</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light-lt h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h4 class="mb-1"><i class="ti ti-users me-1"></i> Guru & Rombel Kelas</h4>
                                <p class="text-muted small mb-2">Sinkronkan akun pembuat soal guru dan struktur rombongan belajar.</p>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('guru', 'Guru & Akun Login')">Sync Guru</button>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="confirmSync('kelas', 'Kelas & Rombel')">Sync Kelas</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded border-primary bg-primary-lt d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h4 class="mb-1 text-primary"><i class="ti ti-id-badge-2 me-1"></i> Sinkronisasi Siswa Aktif & Pembuatan Akun Ujian CBT</h4>
                                <div class="text-muted small">
                                    Hanya siswa dengan status <strong>aktif</strong> di SIAP yang akan disinkronkan ke master siswa CBT dan di-mapping ke kelas ujiannya. Akun login CBT dibuat dengan <strong>Username = NISN</strong> dan <strong>Password awal = NISN</strong>.
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-primary" onclick="confirmSync('siswa', 'Siswa Aktif & Akun Ujian')">
                                    <i class="ti ti-user-plus me-1"></i> Sinkronkan Siswa & Akun Login
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two-Way Section: Tarik Hasil Ujian CBT ke SIAP -->
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="card-title"><i class="ti ti-download me-2 text-success"></i>Data Hasil Ujian dari GarudaCBT &rarr; SIAP</h3>
                    <p class="card-subtitle">Rekapitulasi nilai ujian yang telah dikerjakan siswa di portal CBT.</p>
                </div>
                <form method="GET" action="{{ route('kurikulum.cbt.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Tahun</option>
                        @foreach($academicYears as $ay)
                            <option value="{{ $ay->name }}" {{ $selectedYear == $ay->name ? 'selected' : '' }}>{{ $ay->name }}</option>
                        @endforeach
                    </select>
                    <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Semester</option>
                        <option value="Ganjil" {{ $selectedSmt == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                        <option value="Genap" {{ $selectedSmt == 'Genap' ? 'selected' : '' }}>Genap</option>
                    </select>
                    @if($jadwalList->isNotEmpty())
                    <select name="jadwal_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Jadwal Ujian</option>
                        @foreach($jadwalList as $j)
                            <option value="{{ $j->id_jadwal }}" {{ $selectedJadwal == $j->id_jadwal ? 'selected' : '' }}>
                                {{ $j->nama_jenis ?? 'Ujian' }}: {{ $j->nama_mapel ?? $j->bank_kode }} ({{ $j->tgl_mulai }})
                            </option>
                        @endforeach
                    </select>
                    @endif
                    <a href="{{ route('kurikulum.cbt.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover table-striped">
                    <thead>
                        <tr>
                            <th class="w-1">No</th>
                            <th>No. Peserta / NISN</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Jenis Ujian</th>
                            <th class="text-center">Nilai PG</th>
                            <th class="text-center">Nilai Esai</th>
                            <th class="text-center">Waktu Pengerjaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($examResults as $index => $result)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <span class="font-monospace fw-bold">{{ $result->no_peserta ?: ($result->id_siswa ?? '-') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $result->nama_siswa }}</strong>
                                </td>
                                <td><span class="badge bg-blue-lt">{{ $result->kelas }}</span></td>
                                <td>
                                    <span class="badge bg-purple-lt">{{ $result->kode_jenis ?? 'Ujian' }}</span>
                                    <small class="text-muted d-block">{{ $result->tp }} ({{ $result->smt }})</small>
                                </td>
                                <td class="text-center fw-bold text-primary">{{ $result->nilai_pg ?? '0' }}</td>
                                <td class="text-center fw-bold text-teal">{{ $result->nilai_esai ?? '0' }}</td>
                                <td class="text-center text-muted small">
                                    {{ $result->mulai ? \Carbon\Carbon::parse($result->mulai)->format('d/m/Y H:i') : '-' }}
                                    <br>Durasi: {{ $result->durasi ?? '-' }} mnt
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                                    Belum ada data rekapitulasi nilai ujian dari GarudaCBT untuk filter yang dipilih.
                                    <div class="small mt-1">Data akan otomatis muncul di sini setelah siswa menyelesaikan tes di portal CBT.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($examResults->isNotEmpty())
            <div class="card-footer d-flex align-items-center">
                <p class="m-0 text-muted">Menampilkan <span>{{ $examResults->count() }}</span> data hasil ujian.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Hidden sync form -->
<form id="sync-form" method="POST" action="{{ route('kurikulum.cbt.sync') }}" style="display: none;">
    @csrf
    <input type="hidden" name="type" id="sync-type" value="all">
</form>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmSync(type, label) {
        Swal.fire({
            title: 'Sinkronisasi ' + label + '?',
            text: 'Data di database GarudaCBT (db_cbt) akan disinkronkan dengan data terkini dari SIAP.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#206bc4',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="ti ti-refresh"></i> Ya, Jalankan Sinkronisasi',
            cancelButtonText: 'Batal',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const form = document.getElementById('sync-form');
                document.getElementById('sync-type').value = type;
                
                return fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        type: type
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.message || 'Gagal sinkronisasi'); });
                    }
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Terjadi kesalahan: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value && result.value.success) {
                Swal.fire({
                    title: 'Berhasil!',
                    text: result.value.message || 'Sinkronisasi data ke CBT selesai.',
                    icon: 'success',
                    confirmButtonText: 'Selesai'
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    }
</script>
@endpush
@endsection
