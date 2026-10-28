@extends('layouts.app')

@section('title', 'Buku Pribadi Siswa Digital — Direktori Siswa')

@section('content')
<div class="container-xl">
    @php
        $bkActions = '<a href="' . route('bk.services.index', ['academic_year' => $academicYear]) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-notes me-1"></i> Sesi Layanan BK</a>' .
                     '<a href="' . route('bk.referrals.index', ['academic_year' => $academicYear]) . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-bell-ringing me-1"></i> Rujukan Piket</a>';
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Buku Pribadi Siswa Digital',
        'subtitle' => 'Direktori profil perkembangan, peminatan karier, akademik, dan kedisiplinan siswa.',
        'icon' => 'ti ti-address-book',
        'category' => 'BIMBINGAN & KONSELING',
        'academicYear' => $academicYear,
        'actions' => $bkActions
    ])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('bk.students.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="academic_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $academicYear === $year ? 'selected' : '' }}>TP {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="classroom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas / Rombel --</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ (string)$classroomId === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari nama siswa / NISN / NIS...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search me-1"></i> Cari</button>
                    @if($classroomId || $search)
                        <a href="{{ route('bk.students.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Siswa -->
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h3 class="card-title fw-bold text-dark mb-0">
                <i class="ti ti-users text-primary me-2"></i> Direktori Siswa ({{ $students->total() }})
            </h3>
            <span class="badge bg-blue text-white">TP {{ $academicYear }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th style="width: 170px;">Kelas &amp; Jurusan</th>
                        <th style="width: 180px;">Rencana Kelulusan (BMW)</th>
                        <th style="width: 140px;" class="text-center">Sesi BK (TP Ini)</th>
                        <th style="width: 160px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $st)
                        @php
                            $career = $st->careerProfiles->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm {{ $st->gender === 'P' ? 'bg-pink-lt text-pink' : 'bg-blue-lt text-blue' }} rounded-circle me-2">
                                        {{ substr($st->name, 0, 1) }}
                                    </span>
                                    <div>
                                        <a href="{{ route('bk.students.history', ['student' => $st, 'academic_year' => $academicYear]) }}" class="fw-bold text-dark text-decoration-none hover-primary">
                                            {{ $st->name }}
                                        </a>
                                        <div class="small text-muted">
                                            NISN: {{ $st->nisn ?? '-' }} &bull; NIS: {{ $st->nis ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $st->classroom?->name ?? 'Kelas -' }}</div>
                                <div class="small text-muted">{{ $st->classroom?->department?->name ?? '-' }}</div>
                            </td>
                            <td>
                                @if($career && $career->post_graduation_plan === 'bekerja')
                                    <span class="badge bg-success-lt text-success"><i class="ti ti-briefcase me-1"></i> Bekerja</span>
                                    @if($career->target_industry)
                                        <div class="small text-muted text-truncate" style="max-width: 170px;" title="{{ $career->target_industry }}">{{ $career->target_industry }}</div>
                                    @endif
                                @elseif($career && $career->post_graduation_plan === 'melanjutkan')
                                    <span class="badge bg-azure-lt text-azure"><i class="ti ti-school me-1"></i> Kuliah</span>
                                    @if($career->target_university)
                                        <div class="small text-muted text-truncate" style="max-width: 170px;" title="{{ $career->target_university }}">{{ $career->target_university }}</div>
                                    @endif
                                @elseif($career && $career->post_graduation_plan === 'wirausaha')
                                    <span class="badge bg-warning-lt text-warning"><i class="ti ti-building-store me-1"></i> Wirausaha</span>
                                    @if($career->entrepreneurship_type)
                                        <div class="small text-muted text-truncate" style="max-width: 170px;" title="{{ $career->entrepreneurship_type }}">{{ $career->entrepreneurship_type }}</div>
                                    @endif
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Belum Memutuskan</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($st->bk_services_count > 0)
                                    <span class="badge bg-primary text-white">{{ $st->bk_services_count }} Sesi</span>
                                @else
                                    <span class="text-muted small">0 Sesi</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('bk.students.history', ['student' => $st, 'academic_year' => $academicYear]) }}" class="btn btn-sm btn-primary shadow-xs">
                                    <i class="ti ti-id me-1"></i> Buku Pribadi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="ti ti-users-minus fs-1 d-block mb-2 text-muted"></i>
                                Tidak ditemukan data siswa yang sesuai dengan filter pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between py-2">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
