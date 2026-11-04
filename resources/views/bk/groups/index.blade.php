@extends('layouts.app')

@section('title', 'Kelompok Binaan BK')

@section('content')
<div class="container-xl">
    <!-- Page Header (Model 2: Hero Context Banner) -->
    @include('layouts._hero_header', [
        'title' => 'Kelompok Binaan Siswa (BK)',
        'subtitle' => 'Pengelompokan siswa binaan lintas kelas untuk bimbingan karir, persiapan perguruan tinggi, magang kerja, atau konseling terfokus TP ' . $academicYear . '.',
        'icon' => 'ti ti-users-group',
        'category' => 'BIMBINGAN & KONSELING • KELOMPOK BINAAN',
        'academicYear' => $academicYear,
        'actions' => '
            <a href="' . route('bk.groups.create', ['academic_year' => $academicYear]) . '" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs">
                <i class="ti ti-plus me-1"></i> Buat Kelompok Baru
            </a>
            <a href="' . route('bk.dashboard', ['academic_year' => $academicYear]) . '" class="btn btn-sm btn-outline-light shadow-xs ms-1">
                <i class="ti ti-arrow-left me-1"></i> Dashboard BK
            </a>
        ',
    ])

    <!-- Filter & Pencarian -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form action="{{ route('bk.groups.index') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <div class="col-md-3">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Kategori Bimbingan --</option>
                        <option value="karir" {{ $category === 'karir' ? 'selected' : '' }}>Bimbingan Karir &amp; Peminatan</option>
                        <option value="akademik" {{ $category === 'akademik' ? 'selected' : '' }}>Bimbingan Belajar &amp; Akademik</option>
                        <option value="sosial_pribadi" {{ $category === 'sosial_pribadi' ? 'selected' : '' }}>Sosial &amp; Kepribadian</option>
                        <option value="kedisiplinan" {{ $category === 'kedisiplinan' ? 'selected' : '' }}>Kedisiplinan &amp; Sikap</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="ti ti-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama kelompok atau tujuan bimbingan..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
                    @if($category || $search)
                        <a href="{{ route('bk.groups.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Grid Kartu Kelompok Binaan -->
    <div class="row row-cards">
        @forelse($groups as $grp)
        <div class="col-md-6 col-lg-4">
            <div class="card card-sm border-0 shadow-sm h-100 hover-shadow transition">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-{{ $grp->category_badge_color }}-lt fw-bold fs-6">
                            {{ $grp->category_label }}
                        </span>
                        <span class="badge bg-blue-lt fw-bold">
                            <i class="ti ti-users me-1"></i> {{ $grp->students_count }} Siswa
                        </span>
                    </div>

                    <h3 class="card-title fw-bold text-dark mb-1">
                        <a href="{{ route('bk.groups.show', $grp) }}" class="text-reset text-decoration-none">
                            {{ $grp->name }}
                        </a>
                    </h3>

                    @if($grp->target_goal)
                        <div class="small text-primary mb-2 fw-semibold">
                            <i class="ti ti-target me-1"></i> Target: {{ $grp->target_goal }}
                        </div>
                    @endif

                    <p class="text-muted small flex-grow-1 mb-3">
                        {{ Str::limit($grp->description ?? 'Tidak ada keterangan tambahan.', 110) }}
                    </p>

                    <div class="border-top pt-2 mt-auto d-flex justify-content-between align-items-center">
                        <div class="small text-muted">
                            <i class="ti ti-user me-1"></i> {{ $grp->counselor?->name ?? 'Guru BK' }}
                        </div>
                        <a href="{{ route('bk.groups.show', $grp) }}" class="btn btn-sm btn-outline-primary">
                            Buka Detail <i class="ti ti-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="ti ti-users-minus text-muted" style="font-size: 3rem;"></i>
                    <h3 class="fw-bold mt-3 mb-1">Belum Ada Kelompok Binaan</h3>
                    <p class="text-muted small mb-3">
                        Buat kelompok binaan untuk mempermudah bimbingan karir kelompok, persiapan kerja, atau peminatan kuliah.
                    </p>
                    <a href="{{ route('bk.groups.create', ['academic_year' => $academicYear]) }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Buat Kelompok Pertama
                    </a>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $groups->links() }}
    </div>
</div>
@endsection
