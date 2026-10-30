@extends('layouts.app')

@section('title', $group->name . ' - Kelompok Binaan BK')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="avatar avatar-md bg-{{ $group->category_badge_color }}-lt text-{{ $group->category_badge_color }} rounded-circle">
                    <i class="ti ti-users-group fs-1"></i>
                </span>
            </div>
            <div class="col">
                <div class="page-pretitle text-uppercase fw-bold text-{{ $group->category_badge_color }}" style="letter-spacing: 0.05rem;">
                    {{ $group->category_label }} &bull; TP. {{ $group->academic_year }}
                </div>
                <h2 class="page-title text-dark fw-bold">
                    {{ $group->name }}
                </h2>
                <div class="text-muted small mt-1">
                    Pembimbing: <strong>{{ $group->counselor?->name ?? 'Guru BK' }}</strong> &bull; Total: <strong>{{ $group->students->count() }} Siswa Binaan</strong>
                </div>
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('bk.groups.index', ['academic_year' => $group->academic_year]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar
                </a>
                <button type="button" class="btn btn-sm btn-outline-teal" data-bs-toggle="modal" data-bs-target="#modalAddStudents">
                    <i class="ti ti-user-plus me-1"></i> Tambah Anggota Siswa
                </button>
                <a href="{{ route('bk.services.create', ['service_mode' => 'kelompok', 'bk_group_id' => $group->id, 'academic_year' => $group->academic_year]) }}" class="btn btn-sm btn-primary fw-bold shadow-xs">
                    <i class="ti ti-message-2-share me-1"></i> Catat Sesi Bimbingan Kelompok
                </a>
            </div>
        </div>
    </div>

    <!-- Info Banner & Target -->
    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-{{ $group->category_badge_color }}-lt fw-bold fs-6">{{ $group->category_label }}</span>
                        @if($group->target_goal)
                            <span class="badge bg-primary-lt fw-bold fs-6"><i class="ti ti-target me-1"></i> Target: {{ $group->target_goal }}</span>
                        @endif
                    </div>
                    <p class="text-secondary m-0">
                        {{ $group->description ?: 'Tidak ada deskripsi tambahan untuk kelompok ini.' }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-teal-lt border-start border-teal border-3">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-uppercase fw-bold text-teal">Aktivitas Bimbingan Kelompok</div>
                    <div class="h2 fw-bold text-dark my-1">{{ $group->services->count() }} Sesi Terlaksana</div>
                    <div class="small text-muted">
                        Semua bimbingan kelompok ini tercatat otomatis di Buku Pribadi masing-masing siswa.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Siswa Anggota -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-users me-1 text-primary"></i> Daftar Siswa Anggota Kelompok ({{ $group->students->count() }})
            </h4>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th class="w-1 text-center">No</th>
                        <th>Nama Siswa</th>
                        <th>Kelas / Jurusan</th>
                        <th>Rencana Pasca-Lulus</th>
                        <th>Target Karir / Kampus</th>
                        <th>Bergabung</th>
                        <th class="w-1 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($group->students as $idx => $st)
                    @php
                        $cp = $st->careerProfiles->first();
                        $plan = $cp?->post_graduation_plan;
                        $planBadge = match($plan) {
                            'bekerja' => '<span class="badge bg-teal-lt fw-bold">Bekerja</span>',
                            'melanjutkan' => '<span class="badge bg-purple-lt fw-bold">Kuliah</span>',
                            'wirausaha' => '<span class="badge bg-orange-lt fw-bold">Wirausaha</span>',
                            default => '<span class="badge bg-secondary-lt">Belum Memutuskan</span>',
                        };
                        $targetDesc = match($plan) {
                            'bekerja' => ($cp->target_industry ? $cp->target_industry . ' • ' : '') . ($cp->target_job_position ?? '-'),
                            'melanjutkan' => ($cp->target_university ? $cp->target_university . ' • ' : '') . ($cp->target_study_program ?? '-'),
                            'wirausaha' => $cp->entrepreneurship_type ?? 'Usaha Mandiri',
                            default => '-',
                        };
                    @endphp
                    <tr>
                        <td class="text-center text-muted fw-bold">{{ $idx + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle me-2 fw-bold">
                                    {{ strtoupper(substr($st->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark">{{ $st->name }}</div>
                                    <small class="text-muted">NISN: {{ $st->nisn ?? '-' }} | NIS: {{ $st->nis ?? '-' }}</small>
                                </div>
                            </div>
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
                            <div class="small text-dark fw-semibold">{{ $targetDesc }}</div>
                        </td>
                        <td>
                            <small class="text-muted">{{ $st->pivot->joined_at ? date('d/m/Y', strtotime($st->pivot->joined_at)) : '-' }}</small>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="{{ route('bk.students.history', ['student' => $st, 'academic_year' => $group->academic_year]) }}" class="btn btn-sm btn-icon btn-ghost-primary" title="Buka Buku Pribadi Siswa">
                                    <i class="ti ti-address-book fs-3"></i>
                                </a>
                                <form action="{{ route('bk.groups.remove-student', ['group' => $group, 'student' => $st]) }}" method="POST" onsubmit="return confirm('Keluarkan {{ $st->name }} dari kelompok ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Keluarkan dari Kelompok">
                                        <i class="ti ti-user-x fs-3"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="ti ti-users text-secondary fs-1 mb-2 d-block"></i>
                            Kelompok ini belum memiliki anggota siswa.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat Sesi Bimbingan Kelompok Ini -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title fw-bold text-dark m-0">
                <i class="ti ti-history me-1 text-primary"></i> Riwayat Sesi Bimbingan Kelompok Ini
            </h4>
            <a href="{{ route('bk.services.create', ['service_mode' => 'kelompok', 'bk_group_id' => $group->id, 'academic_year' => $group->academic_year]) }}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus me-1"></i> Catat Sesi Baru
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th>Tanggal</th>
                        <th>Topik / Ringkasan Materi</th>
                        <th>Hasil &amp; Kesepakatan</th>
                        <th>Rencana Tindak Lanjut</th>
                        <th class="w-1 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($group->services as $srv)
                    <tr>
                        <td class="text-nowrap fw-bold text-dark">
                            <i class="ti ti-calendar me-1 text-muted"></i>
                            {{ $srv->service_date ? $srv->service_date->format('d M Y') : '-' }}
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ Str::limit($srv->summary, 80) }}</div>
                            <small class="text-muted">Konselor: {{ $srv->counselor?->name ?? 'Guru BK' }}</small>
                        </td>
                        <td>
                            <small class="text-secondary">{{ Str::limit($srv->result, 80) }}</small>
                        </td>
                        <td>
                            @if($srv->follow_up_plan)
                                <span class="badge bg-yellow-lt">{{ Str::limit($srv->follow_up_plan, 35) }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('bk.services.show', $srv) }}" class="btn btn-sm btn-outline-primary">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            Belum ada sesi bimbingan yang dicatat untuk kelompok ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Anggota Siswa -->
<div class="modal modal-blur fade" id="modalAddStudents" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('bk.groups.add-students', $group) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Anggota Siswa Binaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Pilih siswa aktif yang ingin ditambahkan ke kelompok <strong>{{ $group->name }}</strong>.
                    </p>

                    <div class="table-responsive border rounded" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-vcenter m-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="w-1 text-center"><i class="ti ti-check"></i></th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($availableStudents as $avSt)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="student_ids[]" value="{{ $avSt->id }}" class="form-check-input">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $avSt->name }}</div>
                                        <small class="text-muted">NISN: {{ $avSt->nisn ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $avSt->classroom?->name ?? '-' }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Semua siswa sudah masuk ke kelompok ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-user-plus me-1"></i> Tambahkan ke Kelompok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
