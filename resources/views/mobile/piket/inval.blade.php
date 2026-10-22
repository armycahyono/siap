@extends('layouts.mobile')

@section('title', 'Guru Inval & Kelas Kosong — APK SIAP')
@section('page_title', 'Guru Inval')
@section('page_subtitle', 'Pengondisian KBM Meja Piket')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Top Action Card: Judul & Tombol Tambah Cepat -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="fw-bold fs-4 lh-sm">Inval &amp; Kelas Kosong</div>
            <div class="text-white-50 small" style="font-size: 0.72rem;">
                {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-white fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-1 border-0 flex-shrink-0 sheet-open" data-sheet="#sheet-add-inval" style="font-size: 0.75rem; background-color: #ffffff; color: #ea580c !important; width: auto !important;">
            <i class="ti ti-plus fs-3" style="color: #ea580c !important;"></i>
            <span style="color: #ea580c !important;">Catat Inval</span>
        </button>
    </div>
</div>

@if($period !== 'today' || ($date && $date !== now()->toDateString()) || $status || $classroomId || $search)
    <div class="d-flex justify-content-end mb-2 px-1">
        <a href="{{ route('piket.inval.index') }}" class="badge bg-primary text-white text-decoration-none rounded-pill px-2.5 py-1" style="font-size: 0.65rem;">
            <i class="ti ti-rotate-clockwise me-0.5"></i> Kembali ke Hari Ini
        </a>
    </div>
@endif

<!-- Card Filter Terpadu: Periode, Tanggal, Kelas, Status, dan Pencarian -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('piket.inval.index') }}" method="GET" id="invalFilterForm" class="d-flex flex-column gap-2">
            <input type="hidden" name="period" id="invalPeriodInput" value="{{ $period }}">

            <!-- Baris 1: Mode Periode (3 Tombol Proporsional) -->
            <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; width: 100% !important;">
                <button type="button" 
                        onclick="setInvalPeriod('today')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'today' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'today' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-event me-0.5"></i> Hari Ini
                </button>
                <button type="button" 
                        onclick="setInvalPeriod('week')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'week' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'week' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-week me-0.5"></i> Pekan Ini
                </button>
                <button type="button" 
                        onclick="setInvalPeriod('month')" 
                        class="btn btn-sm rounded-pill fw-bold text-truncate {{ $period === 'month' ? 'btn-primary' : 'btn-light border text-muted' }}" 
                        style="font-size: 0.72rem; min-height: 32px; {{ $period === 'month' ? 'background: linear-gradient(135deg, #102d52 0%, #195b9e 100%) !important; color: #ffffff !important;' : '' }}">
                    <i class="ti ti-calendar-month me-0.5"></i> Bulan Ini
                </button>
            </div>

            <!-- Baris 2: Pemilih Tanggal & Pilihan Kelas (50% - 50%) -->
            <div class="d-flex gap-2">
                <input type="date" 
                       name="date" 
                       class="form-control form-control-sm flex-fill" 
                       style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                       value="{{ $date }}" 
                       onchange="document.getElementById('invalPeriodInput').value = 'today'; this.form.submit()">

                <select name="classroom_id" 
                        class="form-select form-select-sm flex-fill" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ $classroomId == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Baris 3: Status Penugasan Inval -->
            <div>
                <select name="status" 
                        class="form-select form-select-sm w-100" 
                        style="font-size: 0.78rem; height: 36px; border-radius: 8px;" 
                        onchange="this.form.submit()">
                    <option value="">Semua Status Inval</option>
                    <option value="unhandled" {{ $status === 'unhandled' ? 'selected' : '' }}>Belum Ada Inval / Butuh Pengganti</option>
                    <option value="handled" {{ $status === 'handled' ? 'selected' : '' }}>Sudah Ditugaskan Inval</option>
                </select>
            </div>

            <!-- Baris 4: Formasi Pencarian Reusable -->
            <x-mobile.search-bar 
                name="search" 
                :value="$search" 
                placeholder="Cari guru berhalangan / guru inval / mapel..." 
                :reset-url="route('piket.inval.index')" 
                :has-active-filter="(bool) ($status || $classroomId || $period !== 'today' || ($date && $date !== now()->toDateString()))" 
            />
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Rekap Angka Sesuai Filter -->
@if(!empty($stats))
    <div class="card border-0 shadow-sm rounded-3 mb-2 bg-white">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between gap-1 text-center" style="font-size: 0.7rem;">
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-dark lh-1">{{ $stats['total'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Semua Inval</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-danger lh-1">{{ $stats['unhandled'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Butuh Inval</div>
                </div>
                <div class="border-start" style="height: 20px;"></div>
                <div class="flex-fill">
                    <div class="fw-bold fs-3 text-success lh-1">{{ $stats['handled'] }}</div>
                    <div class="text-muted mt-0.5" style="font-size: 0.62rem;">Terisi Inval</div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Feed Daftar Kelas Inval -->
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-1.5 px-1">
        <span class="text-uppercase tracking-wider fw-bold text-muted small" style="font-size: 0.7rem;">
            Daftar Inval &amp; Kelas Kosong
        </span>
        <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.65rem;">
            <i class="ti ti-calendar me-0.5"></i>{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') }}
        </span>
    </div>
    @forelse($invals as $inv)
        @php
            $isHandled = !empty($inv->invalTeacher);
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-1.5 {{ $isHandled ? '' : 'border-start border-danger border-3' }}">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge bg-blue-lt text-primary fw-bold" style="font-size: 0.72rem;">
                        {{ $inv->classroom?->name ?? 'Kelas -' }}
                    </span>
                    <span class="badge {{ $isHandled ? 'bg-success-lt text-success' : 'bg-danger text-white' }}" style="font-size: 0.65rem; padding: 2px 6px;">
                        {{ $isHandled ? 'Terisi Inval' : 'Butuh Pengganti' }}
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between text-muted small mb-1" style="font-size: 0.7rem;">
                    <span>{{ $inv->subject?->name ?? 'Mata Pelajaran' }}</span>
                    <span>Jam ke-{{ $inv->period_start }} s.d {{ $inv->period_end }}</span>
                </div>

                <div class="p-1.5 bg-light rounded-2 small mb-1.5" style="font-size: 0.72rem;">
                    <div class="d-flex justify-content-between">
                        <span class="text-danger">
                            <i class="ti ti-user-x me-1"></i> <strong>{{ $inv->absentTeacher?->formatted_short_name ?? $inv->absentTeacher?->name ?? '-' }}</strong>
                        </span>
                        <span class="badge bg-danger-lt" style="font-size: 0.6rem;">{{ $inv->reason }}</span>
                    </div>
                    @if($isHandled)
                        <div class="text-success mt-1 pt-1 border-top">
                            <i class="ti ti-user-check me-1"></i> Inval: <strong>{{ $inv->invalTeacher?->formatted_short_name ?? $inv->invalTeacher?->name }}</strong>
                        </div>
                    @endif
                    @if($inv->assignment_material)
                        <div class="text-dark mt-1 pt-1 border-top" style="font-size: 0.7rem;">
                            <strong>Tugas Siswa:</strong> {{ $inv->assignment_material }}
                        </div>
                    @endif
                </div>

                @if(auth()->user()->canOperatePiket())
                    <div class="d-flex align-items-center justify-content-between pt-1 border-top" style="font-size: 0.68rem;">
                        <span class="text-muted">
                            {{ $inv->journal?->date?->format('d/m/Y') }}
                        </span>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm {{ $isHandled ? 'btn-outline-info' : 'btn-danger fw-bold' }} rounded-pill px-2.5 py-0.5" style="font-size: 0.68rem;" onclick="openEditInvalSheet('{{ $inv->id }}', '{{ $inv->inval_teacher_id }}', '{{ $inv->period_start }}', '{{ $inv->period_end }}', '{{ addslashes($inv->reason) }}', '{{ addslashes($inv->assignment_material ?? '') }}', '{{ addslashes($inv->notes ?? '') }}', '{{ addslashes($inv->classroom?->name ?? 'Kelas') }}')">
                                <i class="ti {{ $isHandled ? 'ti-edit' : 'ti-user-plus' }} me-0.5"></i>
                                {{ $isHandled ? 'Edit Inval' : '+ Tetapkan Guru' }}
                            </button>
                            <form action="{{ route('piket.inval.destroy', $inv) }}" method="POST" onsubmit="return confirm('Hapus catatan inval ini?')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none ms-1" style="font-size: 0.7rem;" title="Hapus">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-chalkboard fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Tidak ada kelas yang memerlukan inval pada tanggal ini.</div>
        </div>
    @endforelse

    @if($invals->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $invals->links() }}
        </div>
    @endif
</div>

<!-- Framework7 Bottom Sheet: Formulir Catat Inval -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-add-inval" style="height: auto; max-height: 85vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-chalkboard text-primary"></i> Catat Kelas Inval
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <form action="{{ route('piket.inval.store') }}" method="POST">
            @csrf
            <input type="hidden" name="date" value="{{ $date ?: now()->toDateString() }}">

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Kelas / Rombel <span class="text-danger">*</span></label>
                <select name="classroom_id" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Guru Berhalangan <span class="text-danger">*</span></label>
                <select name="absent_teacher_id" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="">-- Pilih Guru Berhalangan --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->formatted_short_name ?? $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Mata Pelajaran</label>
                <select name="subject_id" class="form-select form-select-sm" style="font-size: 0.82rem;">
                    <option value="">-- Pilih Mapel --</option>
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Jam Mulai <span class="text-danger">*</span></label>
                    <select name="period_start" class="form-select form-select-sm" required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}">Jam ke-{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Jam Selesai <span class="text-danger">*</span></label>
                    <select name="period_end" class="form-select form-select-sm" required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ $i == 2 ? 'selected' : '' }}>Jam ke-{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Alasan Berhalangan <span class="text-danger">*</span></label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Contoh: Sakit, Dinas luar, Keperluan keluarga" required style="font-size: 0.82rem;">
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Guru Pengganti (Inval)</label>
                <select name="inval_teacher_id" class="form-select form-select-sm" style="font-size: 0.82rem;">
                    <option value="">-- Belum Ditentukan / Menunggu --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->formatted_short_name ?? $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1">Materi / Tugas untuk Siswa</label>
                <textarea name="assignment_material" rows="2" class="form-control form-control-sm" placeholder="Mengerjakan LKS halaman 40, dikumpulkan di meja piket..." style="font-size: 0.82rem;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Jadwal Inval</span>
            </button>
        </form>
    </div>
</div>

<!-- Framework7 Bottom Sheet: Formulir Edit / Tetapkan Guru Inval -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-edit-inval" style="height: auto; max-height: 85vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-user-check text-primary"></i> <span id="edit-inval-title">Tetapkan Guru Inval</span>
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <div class="p-2 rounded-2 bg-light text-dark small mb-2 fw-semibold" id="edit-inval-class-badge" style="font-size: 0.78rem;">
            Kelas: <span id="edit-inval-class-name">-</span>
        </div>
        <form id="form-edit-inval" action="" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Guru Pengganti (Inval) <span class="text-danger">*</span></label>
                <select name="inval_teacher_id" id="edit-inval-teacher-id" class="form-select form-select-sm" style="font-size: 0.82rem;">
                    <option value="">-- Pilih Guru Pengganti --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->formatted_short_name ?? $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mobile-grid-2 mb-2">
                <div>
                    <label class="form-label small fw-bold mb-1">Jam Mulai <span class="text-danger">*</span></label>
                    <select name="period_start" id="edit-inval-period-start" class="form-select form-select-sm" required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}">Jam ke-{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Jam Selesai <span class="text-danger">*</span></label>
                    <select name="period_end" id="edit-inval-period-end" class="form-select form-select-sm" required>
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}">Jam ke-{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Alasan Berhalangan <span class="text-danger">*</span></label>
                <input type="text" name="reason" id="edit-inval-reason" class="form-control form-control-sm" required style="font-size: 0.82rem;">
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Materi / Tugas untuk Siswa</label>
                <textarea name="assignment_material" id="edit-inval-material" rows="2" class="form-control form-control-sm" placeholder="Mengerjakan tugas..." style="font-size: 0.82rem;"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1">Catatan Tambahan (Opsional)</label>
                <input type="text" name="notes" id="edit-inval-notes" class="form-control form-control-sm" placeholder="Catatan kondisi kelas..." style="font-size: 0.82rem;">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Perubahan Inval</span>
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setInvalPeriod(period) {
    document.getElementById('invalPeriodInput').value = period;
    document.getElementById('invalFilterForm').submit();
}

function openEditInvalSheet(id, teacherId, start, end, reason, material, notes, className) {
    const form = document.getElementById('form-edit-inval');
    form.action = "{{ url('piket/inval') }}/" + id;
    document.getElementById('edit-inval-class-name').textContent = className;
    document.getElementById('edit-inval-teacher-id').value = teacherId || '';
    document.getElementById('edit-inval-period-start').value = start || '1';
    document.getElementById('edit-inval-period-end').value = end || '2';
    document.getElementById('edit-inval-reason').value = reason || '';
    document.getElementById('edit-inval-material').value = material || '';
    document.getElementById('edit-inval-notes').value = notes || '';

    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.open('#sheet-edit-inval');
    }
}
</script>
@endpush
