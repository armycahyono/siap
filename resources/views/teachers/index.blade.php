@extends('layouts.app')

@section('title', 'Data Guru')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-users me-2"></i> Manajemen Data Guru
        </h2>
        <div class="text-muted small">Kelola data identitas guru, NIP/NUPTK, singkatan nama untuk jadwal, dan riwayat supervisi.</div>
    </div>
    <div class="col-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-teal shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDaftarSingkatan" style="border-color: #0d9488; color: #0d9488;">
            <i class="ti ti-id me-1"></i> Daftar Singkatan Nama Guru
        </button>
        @if(auth()->user()->hasPermission('manage_teachers'))
        <a href="{{ route('teachers.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Tambah Guru Baru
        </a>
        @endif
    </div>
</div>

@include('master.nav')

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('teachers.index') }}" class="row g-3">
            <div class="col-md-8 col-lg-7">
                <label class="form-label small text-muted">Pencarian Data Guru</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama guru, NIK, NIGS, No. HP, alamat, pendidikan..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-4 col-lg-5 d-flex align-items-end gap-2">
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-filter me-1"></i> Cari
                </button>
                @if($search)
                    <a href="{{ route('teachers.index', ['academic_year' => $academicYear, 'semester' => $semester]) }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th style="min-width: 290px;">Guru & Identitas</th>
                    <th style="min-width: 175px;">TTL & Pendidikan Terakhir</th>
                    <th style="min-width: 200px;">No. HP & Alamat Rumah</th>
                    <th style="min-width: 150px; max-width: 190px;">Penugasan & Mapel</th>
                    <th style="min-width: 120px;">Status Supervisi</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teachers as $t)
                @php
                    $latestSpv = $t->supervisionsAsTeacher->first();

                    // Penugasan struktural aktif (dari tabel teacher_structural_assignments)
                    $strukturalList = $t->structuralAssignments
                        ->map(fn($sa) => $sa->structuralPosition?->name)
                        ->filter()
                        ->values();

                    // Kelas binaan (dari homeroom_teacher_id di tabel classrooms)
                    $kelasBinaan = $t->homeroomClassrooms;

                    // Mata pelajaran dari lesson_schedules (tersinkron dari PDF)
                    $mapelList = $t->subjectAssignments
                        ->map(fn($sa) => $sa->subject?->name)
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-start">
                            <span class="avatar avatar-sm bg-blue-lt rounded-circle me-2 fw-bold flex-shrink-0 mt-1">
                                {{ strtoupper(substr($t->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark text-nowrap" style="font-size: 0.90rem;">{{ $t->name }}</div>
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    <span class="badge bg-teal-lt text-teal fw-bold px-2 py-0" style="font-size: 0.72rem;" title="Singkatan Nama untuk Jadwal">
                                        <i class="ti ti-id me-1"></i>{{ $t->formatted_short_name }}
                                    </span>
                                    @if($t->nigs_fix ?? $t->nigs)
                                        <span class="badge bg-purple-lt fw-bold font-monospace" style="font-size: 0.70rem;" title="NIGS (Nomor Induk Guru Sekolah)">
                                            NIGS: {{ $t->nigs_fix ?? $t->nigs }}
                                        </span>
                                    @endif
                                    @if($t->nip)
                                        <span class="badge bg-azure-lt fw-bold font-monospace" style="font-size: 0.70rem;" title="NIP">
                                            NIP: {{ $t->nip }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="small text-dark fw-semibold">
                            <i class="ti ti-calendar me-1 text-muted"></i>{{ $t->birth_info ?: '-' }}
                        </div>
                        <div class="mt-1">
                            @if($t->education_level)
                                <span class="badge bg-indigo-lt fw-semibold" style="font-size: 0.70rem;">{{ $t->education_level }}</span>
                            @endif
                            @if($t->major)
                                <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ $t->major }}">{{ $t->major }}</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div>
                            @if($t->phone)
                                @php
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $t->phone);
                                    if (str_starts_with($cleanPhone, '0')) {
                                        $cleanPhone = '62' . substr($cleanPhone, 1);
                                    }
                                @endphp
                                <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="text-success fw-bold text-decoration-none small" title="Hubungi via WhatsApp">
                                    <i class="ti ti-brand-whatsapp me-1 fs-3 align-middle"></i>{{ $t->phone }}
                                </a>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1 text-wrap" style="max-width: 250px; line-height: 1.3;" title="{{ $t->address }}">
                            <i class="ti ti-map-pin me-1 text-danger"></i>{{ Str::limit($t->address ?: 'Alamat belum diisi', 55) }}
                        </div>
                    </td>
                    <td>
                        {{-- GTK Status & Role --}}
                        @if($t->gtk_status || ($t->role && !in_array($t->role->slug, ['guru'])))
                            <div class="mb-1">
                                @if($t->gtk_status)
                                    <span class="badge bg-{{ $t->gtk_status === 'PNS' ? 'blue' : ($t->gtk_status === 'GTT' ? 'orange' : 'teal') }}-lt fw-bold me-1" style="font-size: 0.68rem;">
                                        {{ $t->gtk_status }}
                                    </span>
                                @endif
                                @if($t->role && !in_array($t->role->slug, ['guru']))
                                    <span class="badge bg-purple-lt text-purple fw-semibold" style="font-size: 0.68rem;">
                                        {{ $t->role->name }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Jabatan struktural aktif berjajar ke bawah --}}
                        @foreach($strukturalList as $jabatan)
                            <div class="mb-1">
                                <span class="badge bg-indigo-lt text-indigo fw-semibold text-wrap text-start lh-sm" style="font-size: 0.68rem;">
                                    <i class="ti ti-briefcase me-1"></i>{{ $jabatan }}
                                </span>
                            </div>
                        @endforeach

                        {{-- Wali Kelas berjajar ke bawah --}}
                        @foreach($kelasBinaan as $kb)
                            <div class="mb-1">
                                <span class="badge bg-pink-lt text-pink fw-semibold text-wrap text-start lh-sm" style="font-size: 0.68rem;">
                                    <i class="ti ti-school me-1"></i>Wali {{ $kb->name }}
                                </span>
                            </div>
                        @endforeach

                        {{-- Semua Mata Pelajaran yang ditugaskan berjajar ke bawah --}}
                        @if($mapelList->isNotEmpty())
                            <div class="mt-1">
                                @foreach($mapelList as $mapel)
                                    <div class="mb-1">
                                        <span class="badge bg-blue-lt text-blue text-wrap text-start lh-sm" style="font-size: 0.68rem;">
                                            <i class="ti ti-book me-1"></i>{{ $mapel }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($strukturalList->isEmpty() && $kelasBinaan->isEmpty())
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($latestSpv)
                            <span class="badge bg-{{ $latestSpv->status_color }} text-white">
                                {{ $latestSpv->status_label }}
                            </span>
                            @if($latestSpv->percentage > 0)
                                <div class="small text-muted mt-1">{{ $latestSpv->percentage }}% ({{ $latestSpv->predicate }})</div>
                            @endif
                        @else
                            <span class="badge bg-secondary-lt">Belum Disupervisi</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalDetailGuru{{ $t->id }}" title="Lihat Profil Lengkap">
                                <i class="ti ti-eye"></i>
                            </button>
                            @if(auth()->user()->hasPermission('manage_schedules'))
                            <a href="{{ route('schedules.create', ['teacher_id' => $t->id]) }}" class="btn btn-sm btn-outline-primary" title="Jadwalkan Supervisi">
                                <i class="ti ti-calendar-plus"></i>
                            </a>
                            @endif
                            @if(auth()->user()->hasPermission('manage_teachers'))
                            <a href="{{ route('teachers.edit', $t) }}" class="btn btn-sm btn-outline-secondary" title="Edit Guru">
                                <i class="ti ti-pencil"></i>
                            </a>
                            <form action="{{ route('teachers.destroy', $t) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data guru ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Guru">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="ti ti-users-minus fs-1 d-block mb-2 text-secondary"></i>
                        Tidak ada data guru yang ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($teachers->hasPages())
    <div class="card-footer py-2">
        {{ $teachers->links() }}
    </div>
    @endif
</div>

{{-- Modal Detail Lengkap Setiap Guru --}}
@foreach($teachers as $t)
@php
    $modalStruktural = $t->structuralAssignments
        ->map(fn($sa) => $sa->structuralPosition?->name)
        ->filter()
        ->values();
    $modalKelas = $t->homeroomClassrooms;
    $modalMapel = $t->subjectAssignments
        ->map(fn($sa) => $sa->subject?->name)
        ->filter()
        ->unique()
        ->values();
    $cleanPhone = null;
    if ($t->phone) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $t->phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
    }
@endphp
<div class="modal fade" id="modalDetailGuru{{ $t->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center">
                    <span class="avatar avatar-md bg-white text-primary rounded-circle me-3 fw-bold fs-2 shadow-sm">
                        {{ strtoupper(substr($t->name, 0, 1)) }}
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">{{ $t->name }}</h5>
                        <div class="text-white-50 small mt-1">
                            <span class="badge bg-white text-primary fw-bold me-1">Singkatan: {{ $t->formatted_short_name }}</span>
                            @if($t->gtk_status)
                                <span class="badge bg-white-lt text-white me-1">{{ $t->gtk_status }}</span>
                            @endif
                            @if($t->role)
                                <span class="badge bg-white-lt text-white">{{ $t->role->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    {{-- Identitas Kepegawaian --}}
                    <div class="col-12">
                        <h6 class="text-uppercase text-muted fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="ti ti-id me-1 text-primary"></i> Identitas Kepegawaian & Kependudukan
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border border-light-subtle">
                            <div class="text-muted small">NIK (Nomor Induk Kependudukan)</div>
                            <div class="fw-bold font-monospace text-dark fs-3">{{ $t->nik ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border border-light-subtle">
                            <div class="text-muted small">NIGS (Nomor Induk Guru Sekolah)</div>
                            <div class="fw-bold font-monospace text-dark fs-3">{{ $t->nigs_fix ?? $t->nigs ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">NIP (Pegawai Negeri Sipil)</div>
                            <div class="fw-semibold text-dark">{{ $t->nip ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">NUPTK</div>
                            <div class="fw-semibold text-dark">{{ $t->nuptk ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Tempat, Tanggal Lahir</div>
                            <div class="fw-semibold text-dark">{{ $t->birth_info ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Jenis Kelamin</div>
                            <div class="fw-semibold text-dark">
                                @if($t->gender === 'L')
                                    Laki-laki
                                @elseif($t->gender === 'P')
                                    Perempuan
                                @else
                                    {{ $t->gender ?: '-' }}
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Kontak & Tempat Tinggal --}}
                    <div class="col-12 mt-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="ti ti-address-book me-1 text-primary"></i> Kontak & Domisili Rumah
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">No. Handphone / WhatsApp</div>
                            <div class="fw-semibold text-dark">
                                @if($cleanPhone)
                                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="text-success text-decoration-none">
                                        <i class="ti ti-brand-whatsapp me-1 fs-3 align-middle"></i>{{ $t->phone }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold text-dark">{{ $t->email ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Alamat Lengkap Rumah</div>
                            <div class="fw-semibold text-dark">{{ $t->address ?: '-' }}</div>
                            @if($t->city || $t->postal_code)
                                <div class="text-muted small mt-1">
                                    {{ $t->city }}{{ $t->city && $t->postal_code ? ', ' : '' }}{{ $t->postal_code ? 'Kode Pos: ' . $t->postal_code : '' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Pendidikan Terakhir --}}
                    <div class="col-12 mt-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="ti ti-school me-1 text-primary"></i> Pendidikan Terakhir
                        </h6>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Jenjang Pendidikan</div>
                            <div class="fw-bold text-primary">{{ $t->education_level ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Program Studi / Jurusan</div>
                            <div class="fw-semibold text-dark">{{ $t->major ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Tahun Lulus</div>
                            <div class="fw-semibold text-dark">{{ $t->graduation_year ?: '-' }}</div>
                        </div>
                    </div>
                    @if($t->faculty)
                    <div class="col-12">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small">Fakultas / Perguruan Tinggi</div>
                            <div class="fw-semibold text-dark">{{ $t->faculty }}</div>
                        </div>
                    </div>
                    @endif

                    {{-- Tugas & Penugasan --}}
                    <div class="col-12 mt-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="ti ti-briefcase me-1 text-primary"></i> Penugasan & Beban Mengajar
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small mb-1">Jabatan Struktural</div>
                            @forelse($modalStruktural as $jbt)
                                <span class="badge bg-indigo-lt text-indigo me-1 mb-1">{{ $jbt }}</span>
                            @empty
                                <span class="text-muted small">Tidak ada jabatan struktural</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border-bottom">
                            <div class="text-muted small mb-1">Wali Kelas</div>
                            @forelse($modalKelas as $kls)
                                <span class="badge bg-pink-lt text-pink me-1 mb-1">Wali {{ $kls->name }}</span>
                            @empty
                                <span class="text-muted small">Bukan wali kelas</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2">
                            <div class="text-muted small mb-1">Mata Pelajaran yang Diampu</div>
                            @forelse($modalMapel as $mpl)
                                <span class="badge bg-blue-lt text-blue me-1 mb-1">{{ $mpl }}</span>
                            @empty
                                <span class="text-muted small">Belum ada mata pelajaran terhubung</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                @if(auth()->user()->hasPermission('manage_teachers'))
                <a href="{{ route('teachers.edit', $t) }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-pencil me-1"></i> Edit Data Guru
                </a>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach

<!-- Modal Daftar Singkatan Nama Guru -->
<div class="modal fade" id="modalDaftarSingkatan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom">
                <div>
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="ti ti-id me-1 text-teal" style="color: #0d9488;"></i> Daftar Singkatan Nama Guru (Jadwal Pelajaran)
                    </h5>
                    <div class="text-muted small">Daftar nama guru tanpa gelar dan format singkatan yang digunakan pada jadwal kelas.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="p-3 bg-light border-bottom">
                <div class="input-group">
                    <span class="input-group-text"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" id="searchAbbreviationInput" class="form-control" placeholder="Ketik nama atau singkatan guru untuk mencari..." onkeyup="filterAbbreviationTable()">
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover table-striped mb-0" id="tableAbbreviations">
                        <thead class="bg-light text-muted small">
                            <tr>
                                <th style="width: 45px;" class="text-center">No</th>
                                <th style="min-width: 170px;">Singkatan Nama (Tanpa Gelar)</th>
                                <th>Nama Lengkap & Gelar</th>
                                <th>Mata Pelajaran</th>
                                <th class="text-center" style="width: 70px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allTeachers as $idx => $teacherItem)
                                <tr>
                                    <td class="text-muted text-center">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="badge bg-teal-lt text-teal fw-bold px-2 py-1 fs-4 border border-teal-subtle">
                                            {{ $teacherItem->formatted_short_name }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $teacherItem->name }}</div>
                                        <div class="text-muted small">NIGS: {{ $teacherItem->nigs_fix ?? $teacherItem->nigs ?: '-' }} &bull; NIK: {{ $teacherItem->nik ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="text-secondary small">{{ $teacherItem->subject ?: '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('teachers.edit', $teacherItem) }}" class="btn btn-sm btn-icon btn-ghost-primary" title="Ubah Singkatan">
                                            <i class="ti ti-edit fs-4"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <span class="text-muted small me-auto"><i class="ti ti-info-circle me-1"></i> Total {{ count($allTeachers) }} guru terdaftar dalam direktori singkatan.</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterAbbreviationTable() {
    const input = document.getElementById('searchAbbreviationInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('tableAbbreviations');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}
</script>
@endpush
