@extends('layouts.web')

@section('title', 'Izin Siswa Perwalian (Wali Kelas)')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @php
        $hrPermitActions = '';
        if ($myClassrooms->isNotEmpty()) {
            $hrPermitActions .= '<a href="' . route('attendance.homeroom-permits.create', ['classroom_id' => $selectedClassroomId]) . '" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs"><i class="ti ti-plus me-1"></i> Catat Izin Siswa</a> ';
        }
        $hrPermitActions .= '<a href="' . route('attendance.subject.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-chalkboard-teacher me-1 text-info"></i> Absen Mengajar</a>';
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Laporan Izin Siswa Perwalian',
        'subtitle' => 'Catat pemberitahuan izin / sakit siswa dari wali murid agar langsung terhubung ke Meja Piket dan Lembar Absen Guru Mengajar.',
        'icon' => 'ti ti-heart-handshake',
        'category' => 'WALI KELAS & PRESENSI',
        'actions' => $hrPermitActions
    ])

    <!-- Alert Informasi Alur Kerja -->
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
        <i class="ti ti-info-circle fs-1 me-3 text-info"></i>
        <div class="small">
            <strong>Otomatisasi Sistem:</strong> Data izin atau sakit yang Anda laporkan di sini akan <strong>otomatis terbit</strong> di Meja Piket Harian dan <strong>otomatis terisi</strong> pada lembar presensi seluruh Guru Mata Pelajaran yang mengajar di kelas ini hari ini.
        </div>
    </div>

    @if($myClassrooms->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ti ti-users-minus fs-1 text-muted d-block mb-2"></i>
                <h4 class="text-dark fw-bold">Anda Belum Ditugaskan Sebagai Wali Kelas</h4>
                <p class="text-muted small">Menu ini diperuntukkan bagi Bapak/Ibu Guru yang mengemban tugas tambahan sebagai Wali Kelas pada tahun ajaran aktif.</p>
            </div>
        </div>
    @else
        <!-- Filter Bar -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-2">
                <form action="{{ route('attendance.homeroom-permits.index') }}" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach($myClassrooms as $c)
                                <option value="{{ $c->id }}" {{ $selectedClassroomId == $c->id ? 'selected' : '' }}>
                                    Kelas {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" title="Filter Tanggal">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa atau alasan izin...">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                        @if($date || $search)
                            <a href="{{ route('attendance.homeroom-permits.index', ['classroom_id' => $selectedClassroomId, 'date' => '']) }}" class="btn btn-sm btn-ghost-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Riwayat Izin -->
        <div class="card shadow-sm border-0">
            <!-- Mobile Card View (Khusus Layar HP) -->
            <div class="d-md-none p-2 p-sm-3">
                @forelse($permits as $idx => $p)
                    <div class="card card-stacked shadow-sm border rounded-3 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge {{ $p->permit_type_badge_class }}">
                                    {{ $p->permit_type_label }}
                                </span>
                                <span class="text-muted small">
                                    <i class="ti ti-calendar me-1"></i>
                                    @if($p->end_date && $p->end_date->ne($p->permit_date))
                                        {{ $p->permit_date?->format('d/m/Y') }} - {{ $p->end_date?->format('d/m/Y') }}
                                    @else
                                        {{ $p->permit_date?->format('d/m/Y') }}
                                    @endif
                                </span>
                            </div>
                            <div class="mb-2">
                                <h4 class="mb-0 fw-bold text-dark fs-3">{{ $p->student_name }}</h4>
                                <div class="text-muted small">
                                    NIS: {{ $p->student?->nisn ?? $p->student?->nis ?? '-' }} &bull; Kelas: {{ $p->classroom?->name }}
                                </div>
                            </div>
                            <div class="p-2 bg-light rounded-2 small text-secondary mb-3">
                                <strong>Alasan:</strong> {{ $p->reason }}
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    @if($p->attachment_path)
                                        <a href="{{ $p->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-info py-1 px-2">
                                            <i class="ti ti-paperclip me-1"></i> Foto Bukti
                                        </a>
                                    @else
                                        <span class="text-muted small">Tanpa Surat</span>
                                    @endif
                                </div>
                                <div>
                                    <form action="{{ route('attendance.homeroom-permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data izin ini?')" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost-danger">
                                            <i class="ti ti-trash me-1"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">
                        <i class="ti ti-clipboard-check fs-1 text-success d-block mb-1"></i>
                        Belum ada data izin siswa tercatat pada kelas ini.
                    </div>
                @endforelse
            </div>

            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-vcenter table-hover card-table">
                    <thead class="table-light">
                        <tr>
                            <th class="w-1 text-center">No</th>
                            <th>No. Izin & Tanggal</th>
                            <th>Nama Siswa & NIS</th>
                            <th>Kategori & Alasan</th>
                            <th>Bukti / Surat</th>
                            <th>Pelapor</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($permits as $idx => $p)
                            <tr>
                                <td class="text-center text-muted small">{{ $permits->firstItem() + $idx }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $p->permit_number }}</div>
                                    <div class="text-muted small">
                                        <i class="ti ti-calendar me-1"></i>
                                        @if($p->end_date && $p->end_date->ne($p->permit_date))
                                            {{ $p->permit_date?->format('d/m/Y') }} s/d {{ $p->end_date?->format('d/m/Y') }}
                                        @else
                                            {{ $p->permit_date?->format('d/m/Y') }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-dark fs-3">{{ $p->student_name }}</strong>
                                    <div class="text-muted small">
                                        NIS: {{ $p->student?->nisn ?? $p->student?->nis ?? '-' }} &bull; Kelas: {{ $p->classroom?->name }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $p->permit_type_badge_class }} mb-1">
                                        {{ $p->permit_type_label }}
                                    </span>
                                    <div class="text-secondary small">
                                        <strong>Alasan:</strong> {{ $p->reason }}
                                    </div>
                                </td>
                                <td>
                                    @if($p->attachment_path)
                                        <a href="{{ $p->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-info py-0 px-2" title="Lihat Foto Bukti / Surat">
                                            <i class="ti ti-paperclip me-1"></i> Lihat Foto
                                        </a>
                                    @else
                                        <span class="text-muted small">Tanpa Surat</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark">{{ $p->givenBy?->formatted_short_name ?? $p->givenBy?->name ?? '-' }}</div>
                                    <span class="badge bg-secondary-lt" style="font-size: 0.68rem;">{{ ucfirst(str_replace('_', ' ', $p->reporter_type ?? 'wali_kelas')) }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <form action="{{ route('attendance.homeroom-permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data izin ini?')" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost-danger" title="Hapus Izin">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ti ti-clipboard-check fs-1 d-block mb-1 text-success"></i>
                                    Belum ada data izin siswa tercatat pada kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($permits->hasPages())
                <div class="card-footer py-2">
                    {{ $permits->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
