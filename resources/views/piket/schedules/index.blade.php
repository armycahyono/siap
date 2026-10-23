@extends('layouts.app')

@section('title', 'Jadwal & Petugas Piket')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @php
        $schedPiketActions = '';
        if (auth()->user()->canManagePiketSchedules()) {
            $schedPiketActions = '<a href="' . route('piket.swaps.index') . '" class="btn btn-sm btn-outline-light shadow-xs"><i class="ti ti-arrows-left-right me-1 text-info"></i> Tukar Jadwal Piket</a> ' .
                '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-schedule"><i class="ti ti-plus me-1"></i> Tambah Petugas Piket</button>';
        }
    @endphp
    @include('layouts._hero_header', [
        'title' => 'Jadwal & Penetapan Petugas Piket',
        'subtitle' => 'Kelola pembagian hari tugas, koordinator harian, dan pos piket per semester.',
        'icon' => 'ti ti-calendar-user',
        'category' => 'PENGORGANISASIAN PIKET',
        'actions' => $schedPiketActions
    ])

    <!-- Filter TP & Semester -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form action="{{ route('piket.schedules.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto fw-bold text-dark">
                    <i class="ti ti-filter me-1"></i> Filter:
                </div>
                <div class="col-md-3">
                    <input type="text" name="academic_year" class="form-control" value="{{ $academicYear }}" placeholder="Tahun Pelajaran (2026/2027)">
                </div>
                <div class="col-md-2">
                    <select name="semester" class="form-select">
                        <option value="Ganjil" {{ $semester === 'Ganjil' ? 'selected' : '' }}>Semester Ganjil</option>
                        <option value="Genap" {{ $semester === 'Genap' ? 'selected' : '' }}>Semester Genap</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards Hari Piket (Senin - Sabtu) -->
    <div class="row g-4">
        @foreach($days as $day)
            @php
                $daySchedules = $groupedSchedules[$day] ?? collect();
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card shadow-sm h-100 border-0 border-top border-primary border-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h3 class="card-title fw-bold text-dark mb-0">
                            <span class="badge bg-primary text-white me-2 px-2 py-1">{{ $day }}</span>
                        </h3>
                        <span class="badge bg-blue-lt fw-bold">{{ $daySchedules->count() }} Guru</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse($daySchedules as $sched)
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center">
                                        @php
                                            $isCoord = ($sched->role === 'koordinator');
                                        @endphp
                                        <span class="avatar avatar-md rounded-circle {{ $isCoord ? 'bg-purple text-white shadow-sm' : 'bg-blue-lt text-primary' }} me-3 fw-bold"
                                              @if($isCoord) title="Koordinator Piket" data-bs-toggle="tooltip" @endif>
                                            {{ substr($sched->teacher?->name ?? 'G', 0, 1) }}
                                        </span>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                {{ $sched->teacher?->name ?? '-' }}
                                            </div>
                                            <div class="text-secondary small mt-1">
                                                <i class="ti ti-map-pin me-1 text-muted"></i>{{ $sched->duty_location ?: 'Meja Piket Utama' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        @if($sched->teacher?->phone)
                                            @php
                                                $waGreeting = urlencode("Halo Bapak/Ibu " . ($sched->teacher?->short_name ?: $sched->teacher?->name) . ", saya ingin berkoordinasi terkait tugas piket hari " . $day . " di SMK PGRI Kras.");
                                            @endphp
                                            <a href="{{ $sched->teacher->whatsapp_url }}?text={{ $waGreeting }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-icon btn-outline-success rounded-circle" 
                                               title="Chat WhatsApp ({{ $sched->teacher->phone }})"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-brand-whatsapp fs-2"></i>
                                            </a>
                                        @endif
                                        @if(auth()->user()->canManagePiketSchedules())
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Aksi
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modal-edit-{{ $sched->id }}">
                                                    <i class="ti ti-edit me-2 text-warning"></i> Edit Pos & Peran
                                                </button>
                                                <form action="{{ route('piket.schedules.destroy', $sched) }}" method="POST" onsubmit="return confirm('Hapus guru ini dari jadwal piket hari {{ $day }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="ti ti-trash me-2"></i> Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Modal Edit Schedule -->
                                <div class="modal fade" id="modal-edit-{{ $sched->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('piket.schedules.update', $sched) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Edit Petugas Piket</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Guru</label>
                                                        <select name="teacher_id" class="form-select" required>
                                                            @foreach($teachers as $t)
                                                                <option value="{{ $t->id }}" {{ $sched->teacher_id == $t->id ? 'selected' : '' }}>
                                                                    {{ $t->name }} ({{ $t->formatted_id }}){{ $t->phone ? ' - '.$t->phone : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Hari Tugas</label>
                                                        <select name="day" class="form-select" required>
                                                            @foreach($days as $d)
                                                                <option value="{{ $d }}" {{ $sched->day == $d ? 'selected' : '' }}>{{ $d }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Peran</label>
                                                        <select name="role" class="form-select" required>
                                                            <option value="anggota" {{ $sched->role == 'anggota' ? 'selected' : '' }}>Anggota Piket</option>
                                                            <option value="koordinator" {{ $sched->role == 'koordinator' ? 'selected' : '' }}>Koordinator Harian</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Pos / Lokasi Tugas</label>
                                                        <input type="text" name="duty_location" class="form-control" value="{{ $sched->duty_location }}" placeholder="Contoh: Gerbang Depan / Lobi / Gedung A">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status Aktif</label>
                                                        <select name="is_active" class="form-select">
                                                            <option value="1" {{ $sched->is_active ? 'selected' : '' }}>Aktif</option>
                                                            <option value="0" {{ !$sched->is_active ? 'selected' : '' }}>Non-Aktif</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted">
                                    <i class="ti ti-user-x fs-2 d-block mb-1"></i>
                                    Belum ada petugas piket.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Tambah Petugas Piket -->
<div class="modal fade" id="modal-add-schedule" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.schedules.store') }}" method="POST">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Petugas Piket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Hari Tugas</label>
                        <select name="day" class="form-select" required>
                            @foreach($days as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Pilih Guru</label>
                        <select name="teacher_id" class="form-select select2" required>
                            <option value="">-- Pilih Guru --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->formatted_id }}){{ $t->phone ? ' - '.$t->phone : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Peran</label>
                        <select name="role" class="form-select" required>
                            <option value="anggota">Anggota Piket</option>
                            <option value="koordinator">Koordinator Harian</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pos / Lokasi Tugas</label>
                        <input type="text" name="duty_location" class="form-control" placeholder="Contoh: Gerbang Depan / Lobi / Gedung A & B">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan ke Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
