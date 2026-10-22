@extends('layouts.app')

@php
    $stageTitles = [
        'preobs' => ['title' => 'Praobservasi', 'desc' => 'Daftar supervisi untuk pengisian dan peninjauan perangkat/catatan praobservasi', 'icon' => 'clipboard-list', 'btn' => 'Mulai / Buka Praobservasi', 'badge' => 'Praobservasi', 'color' => 'blue'],
        'obs' => ['title' => 'Observasi Pembelajaran', 'desc' => 'Daftar pelaksanaan penilaian 23 butir instrumen observasi di kelas', 'icon' => 'notebook', 'btn' => 'Mulai / Buka Observasi', 'badge' => 'Observasi', 'color' => 'indigo'],
        'docs' => ['title' => 'Dokumentasi Supervisi', 'desc' => 'Kelola unggahan foto dokumentasi kegiatan belajar mengajar dan supervisi', 'icon' => 'photo', 'btn' => 'Kelola Foto', 'badge' => 'Dokumentasi', 'color' => 'cyan'],
        'results' => ['title' => 'Hasil Supervisi', 'desc' => 'Rangkuman penilaian, catatan pascaobservasi, dan finalisasi supervisi', 'icon' => 'award', 'btn' => 'Lihat Hasil & Catatan', 'badge' => 'Hasil', 'color' => 'yellow'],
    ];

    $info = $stageTitles[$stage] ?? $stageTitles['preobs'];
@endphp

@section('title', $info['title'] . ' - Sistem Supervisi')

@section('content')
<div class="row g-2 align-items-center mb-3">
    <div class="col">
        <h2 class="page-title fw-bold text-dark">
            <i class="ti ti-{{ $info['icon'] }} me-2 text-{{ $info['color'] }}"></i>
            {{ $info['title'] }}
        </h2>
        <div class="text-muted small">{{ $info['desc'] }}</div>
    </div>
    @if(auth()->user()->hasPermission('manage_schedules'))
    <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('schedules.create') }}" class="btn btn-primary shadow-sm">
            <i class="ti ti-calendar-plus me-1"></i> Tambah Jadwal Baru
        </a>
    </div>
    @endif
</div>

<!-- Navigasi Alur Supervisi -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2">
            <li class="nav-item">
                <a class="nav-link {{ $stage === 'preobs' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('supervisions.preobs', request()->query()) }}">
                    <i class="ti ti-clipboard-list me-1 fs-3"></i>
                    <span>1. Praobservasi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $stage === 'obs' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('supervisions.obs', request()->query()) }}">
                    <i class="ti ti-notebook me-1 fs-3"></i>
                    <span>2. Observasi Kelas</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $stage === 'docs' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('supervisions.docs', request()->query()) }}">
                    <i class="ti ti-photo me-1 fs-3"></i>
                    <span>3. Dokumentasi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $stage === 'results' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" href="{{ route('supervisions.results', request()->query()) }}">
                    <i class="ti ti-award me-1 fs-3"></i>
                    <span>4. Hasil & Catatan</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Filter Pencarian & Periode -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ url()->current() }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari guru, mata pelajaran, rombel..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="academic_year" class="form-select">
                    <option value="all" {{ $academicYear === 'all' ? 'selected' : '' }}>Semua Tahun Pelajaran</option>
                    @foreach($existingYears as $yr)
                        <option value="{{ $yr }}" {{ $academicYear === $yr ? 'selected' : '' }}>TP {{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="semester" class="form-select">
                    <option value="all" {{ $semester === 'all' ? 'selected' : '' }}>Semua Semester</option>
                    <option value="Ganjil" {{ $semester === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester === 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || ($academicYear && $academicYear !== $setting->academic_year) || ($semester && $semester !== 'all'))
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary" title="Reset"><i class="ti ti-rotate-2"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Tabel Supervisi -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr class="text-muted small">
                    <th>Guru & Mata Pelajaran</th>
                    <th>Kelas / Rombel</th>
                    <th>Jadwal / Waktu</th>
                    <th>Supervisor</th>
                    @if($stage === 'obs' || $stage === 'results')
                        <th class="text-center">Skor & Predikat</th>
                    @elseif($stage === 'docs')
                        <th class="text-center">Jml Foto</th>
                    @endif
                    <th class="text-center">Status</th>
                    <th class="w-1 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisions as $s)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-blue-lt text-blue rounded-circle me-2 fw-bold">
                                {{ strtoupper(substr($s->teacher->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="fw-bold text-dark">{{ $s->teacher->name }}</div>
                                <div class="small text-muted">
                                    <span class="text-primary fw-semibold">{{ $s->subject }}</span>
                                    @if($s->teacher->nigs_fix) &bull; NIGS: {{ $s->teacher->nigs_fix }} @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary-lt fw-bold">{{ $s->class_name }}</span>
                    </td>
                    <td>
                        <div><i class="ti ti-calendar me-1 text-muted"></i>{{ $s->schedule_date ? $s->schedule_date->translatedFormat('d F Y') : '-' }}</div>
                        <small class="text-muted"><i class="ti ti-clock me-1"></i>{{ $s->schedule_time }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $s->supervisor->name }}</div>
                        <small class="text-muted">{{ $s->supervisor->role->name }}</small>
                    </td>
                    @if($stage === 'obs' || $stage === 'results')
                    <td class="text-center">
                        @if($s->obs_completed_at)
                            <div class="fw-bold text-dark fs-3">{{ $s->percentage }}%</div>
                            <span class="badge bg-{{ $s->predicate_color }}-lt">{{ $s->predicate }}</span>
                        @else
                            <span class="text-muted small fst-italic">Belum dinilai</span>
                        @endif
                    </td>
                    @elseif($stage === 'docs')
                    <td class="text-center">
                        <span class="badge bg-cyan-lt fw-bold fs-4">{{ $s->photos->count() }} Foto</span>
                    </td>
                    @endif
                    <td class="text-center">
                        <span class="badge bg-{{ $s->status_color }} text-white">
                            {{ $s->status_label }}
                        </span>
                    </td>
                    <td class="text-center">
                        @php
                            $targetRoute = match($stage) {
                                'preobs' => route('supervisions.pre-observation', $s),
                                'obs' => route('supervisions.observation', $s),
                                'docs' => route('supervisions.documentation', $s),
                                'results' => route('supervisions.show', $s),
                                default => route('supervisions.show', $s),
                            };
                            $canConduct = auth()->user()->canEditSupervision($s);
                            $btnLabel = $canConduct ? $info['btn'] : 'Buka / Lihat';
                        @endphp
                        <a href="{{ $targetRoute }}" class="btn btn-sm {{ $canConduct ? 'btn-primary' : 'btn-outline-primary' }} shadow-sm whitespace-nowrap">
                            <i class="ti ti-{{ $canConduct ? 'arrow-right' : 'eye' }} me-1"></i> {{ $btnLabel }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 mb-2 d-block"></i>
                        Belum ada supervisi yang tercatat untuk tahapan ini pada Tahun Pelajaran {{ $academicYear }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($supervisions->hasPages())
    <div class="card-footer d-flex align-items-center py-2">
        {{ $supervisions->links() }}
    </div>
    @endif
</div>
@endsection
