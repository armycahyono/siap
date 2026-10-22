@extends('layouts.mobile')

@section('title', 'Jadwal Pelajaran Sekolah — APK SIAP')
@section('page_title', 'Jadwal Pelajaran Sekolah')
@section('page_subtitle', 'TP ' . $academicYear . ' (' . $semester . ')')
@section('back_url', route('attendance.subject.index'))

@section('content')
<!-- Header Card Hero -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="fw-bold fs-3 lh-sm">Jadwal Pelajaran Sekolah</div>
            <span class="text-white opacity-75 small" style="font-size: 0.72rem;">
                Hari Ini: {{ $todayDay }}
            </span>
        </div>
        <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
            Daftar seluruh jadwal KBM kelas dan guru SMK PGRI Kras.
        </div>
        <div class="d-flex align-items-center justify-content-between">
            <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                Hari terpilih: {{ $selectedDay }}
            </span>
        </div>
    </div>
</div>

<!-- Tab Navigasi Hari (Senin s/d Jumat) -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-1.5 d-flex gap-1">
        @foreach($days as $day)
            <a href="{{ route('attendance.subject.school-schedule', array_filter(['day' => $day, 'classroom_id' => $classroomId, 'teacher_id' => $teacherId, 'search' => $search])) }}" 
               class="btn btn-sm rounded-pill flex-fill text-decoration-none text-center px-1 py-1 {{ $day === $selectedDay ? 'btn-primary fw-bold text-white shadow-xs' : 'btn-light text-dark' }}" 
               style="font-size: 0.75rem;">
                {{ $day }}
            </a>
        @endforeach
    </div>
</div>

<!-- Filter Kelas, Guru & Pencarian -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2 px-3">
        <form action="{{ route('attendance.subject.school-schedule') }}" method="GET" class="d-flex flex-column gap-1.5" id="form-filter-schedule">
            <input type="hidden" name="day" value="{{ $selectedDay }}">
            
            <div class="mobile-grid-2">
                <div>
                    <label class="form-label small fw-bold mb-0.5 text-muted" style="font-size: 0.7rem;">
                        <i class="ti ti-building me-0.5 text-primary"></i> Filter Kelas:
                    </label>
                    <select name="classroom_id" class="form-select form-select-sm rounded-2" style="font-size: 0.75rem;" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas ({{ $classrooms->count() }}) --</option>
                        @foreach($classrooms as $c)
                            <option value="{{ $c->id }}" {{ (string) $classroomId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-0.5 text-muted" style="font-size: 0.7rem;">
                        <i class="ti ti-user me-0.5 text-primary"></i> Filter Guru:
                    </label>
                    <select name="teacher_id" class="form-select form-select-sm rounded-2" style="font-size: 0.75rem;" onchange="this.form.submit()">
                        <option value="">-- Semua Guru ({{ $teachers->count() }}) --</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ (string) $teacherId === (string) $t->id ? 'selected' : '' }}>
                                {{ $t->formatted_short_name ?? $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted border-end-0">
                    <i class="ti ti-search fs-3"></i>
                </span>
                <input type="text" name="search" id="live-schedule-search" class="form-control form-control-sm border-start-0" placeholder="Cari mapel, guru, atau kelas..." value="{{ $search }}" style="font-size: 0.75rem;">
                <button type="submit" class="btn btn-primary btn-sm px-2.5 fw-bold" style="font-size: 0.72rem;">
                    Cari
                </button>
                @if($classroomId || $teacherId || $search !== '')
                    <a href="{{ route('attendance.subject.school-schedule', ['day' => $selectedDay]) }}" class="btn btn-outline-secondary btn-sm px-2" title="Reset Filter">
                        <i class="ti ti-x"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Chip Status Filter Aktif -->
@if($selectedClassroom || $selectedTeacher || $search !== '')
    <div class="d-flex align-items-center gap-1 mb-2 px-1 flex-wrap" style="font-size: 0.7rem;">
        <span class="text-muted">Filter aktif:</span>
        @if($selectedClassroom)
            <span class="badge bg-blue-lt d-inline-flex align-items-center gap-1">
                Kelas: {{ $selectedClassroom->name }}
                <a href="{{ route('attendance.subject.school-schedule', array_filter(['day' => $selectedDay, 'teacher_id' => $teacherId, 'search' => $search])) }}" class="text-primary text-decoration-none">&times;</a>
            </span>
        @endif
        @if($selectedTeacher)
            <span class="badge bg-purple-lt d-inline-flex align-items-center gap-1">
                Guru: {{ $selectedTeacher->formatted_short_name ?? $selectedTeacher->name }}
                <a href="{{ route('attendance.subject.school-schedule', array_filter(['day' => $selectedDay, 'classroom_id' => $classroomId, 'search' => $search])) }}" class="text-purple text-decoration-none">&times;</a>
            </span>
        @endif
        @if($search !== '')
            <span class="badge bg-warning-lt d-inline-flex align-items-center gap-1">
                "{{ $search }}"
                <a href="{{ route('attendance.subject.school-schedule', array_filter(['day' => $selectedDay, 'classroom_id' => $classroomId, 'teacher_id' => $teacherId])) }}" class="text-warning text-decoration-none">&times;</a>
            </span>
        @endif
    </div>
@endif

<!-- DAFTAR JADWAL PELAJARAN -->
<div class="mb-4" id="schedule-container">
    @if($schedules->isEmpty())
        <!-- State Kosong -->
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <div class="card-body p-3">
                <i class="ti ti-calendar-off fs-1 d-block mb-1 text-secondary opacity-50"></i>
                <div class="fw-bold text-dark fs-4 mb-0.5">Tidak Ada Jadwal Ditemukan</div>
                <div class="small text-muted mb-2">
                    Tidak ada jadwal pelajaran tercatat untuk hari <strong>{{ $selectedDay }}</strong>
                    @if($selectedClassroom) di kelas <strong>{{ $selectedClassroom->name }}</strong>@endif
                    @if($selectedTeacher) oleh guru <strong>{{ $selectedTeacher->formatted_short_name ?? $selectedTeacher->name }}</strong>@endif
                    @if($search !== '') dengan kata kunci "{{ $search }}"@endif.
                </div>
                <a href="{{ route('attendance.subject.school-schedule', ['day' => $selectedDay]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 0.75rem;">
                    <i class="ti ti-rotate-2 me-1"></i> Tampilkan Semua Jadwal Hari {{ $selectedDay }}
                </a>
            </div>
        </div>
    @elseif($selectedClassroom)
        {{-- MODE 1: TAMPILAN TIMELINE SPESIFIK 1 KELAS --}}
        <div class="card border-0 shadow-sm rounded-3 mb-2">
            <div class="card-header bg-white p-2.5 px-3 d-flex align-items-center justify-content-between border-bottom">
                <div>
                    <div class="fw-bold text-dark fs-3 lh-sm">{{ $selectedClassroom->name }}</div>
                    <div class="text-muted small" style="font-size: 0.68rem;">
                        Wali Kelas: <strong>{{ $selectedClassroom->homeroomTeacher?->formatted_short_name ?? $selectedClassroom->homeroomTeacher?->name ?? 'Belum ditentukan' }}</strong>
                    </div>
                </div>
                <span class="badge bg-primary-lt fw-bold" style="font-size: 0.72rem;">
                    {{ $schedules->sum(fn($s) => $s->end_period - $s->start_period + 1) }} JP Total
                </span>
            </div>
            <div class="card-body p-2 px-3">
                <div class="d-flex flex-column gap-1.5">
                    @foreach($schedules as $s)
                        @php
                            $isCurrentActive = ($activePeriod && $activePeriod >= $s->start_period && $activePeriod <= $s->end_period);
                            $startTime = $s->start_time ?: ($periodTimes[$s->start_period]['start'] ?? '');
                            $endTime = $s->end_time ?: ($periodTimes[$s->end_period]['end'] ?? '');
                        @endphp
                        <div class="p-2 rounded-2 border schedule-item-card {{ $isCurrentActive ? 'bg-primary-lt border-primary shadow-xs' : 'bg-light border-light' }}" data-search-text="{{ strtolower(($s->subject?->name ?? '') . ' ' . ($s->teacher?->name ?? '') . ' ' . ($s->classroom?->name ?? '')) }}">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="badge {{ $isCurrentActive ? 'bg-primary text-white' : 'bg-secondary text-white' }}" style="font-size: 0.68rem; padding: 2px 7px;">
                                        Jam {{ $s->start_period }} s.d {{ $s->end_period }}
                                    </span>
                                    <span class="text-muted small fw-semibold" style="font-size: 0.68rem;">
                                        <i class="ti ti-clock me-0.5"></i>{{ $startTime }} - {{ $endTime }} WIB
                                    </span>
                                </div>
                                @if($isCurrentActive)
                                    <span class="badge bg-success text-white" style="font-size: 0.62rem; padding: 2px 6px;">
                                        <i class="ti ti-live-photo me-0.5"></i> Berlangsung
                                    </span>
                                @endif
                            </div>
                            <div class="fw-bold text-dark fs-4 lh-sm mb-0.5">
                                <i class="ti ti-book text-primary me-0.5"></i> {{ $s->subject?->name ?? 'Mata Pelajaran' }}
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-muted small" style="font-size: 0.72rem;">
                                <span>
                                    <i class="ti ti-user me-0.5 text-secondary"></i> {{ $s->teacher?->formatted_short_name ?? $s->teacher?->name ?? 'Belum ditentukan' }}
                                </span>
                                @if($s->teacher?->phone)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s->teacher->phone) }}" target="_blank" class="text-success text-decoration-none" title="Chat WhatsApp">
                                        <i class="ti ti-brand-whatsapp fs-3"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    @elseif($selectedTeacher)
        {{-- MODE 2: TAMPILAN TIMELINE SPESIFIK 1 GURU --}}
        <div class="card border-0 shadow-sm rounded-3 mb-2">
            <div class="card-header bg-white p-2.5 px-3 d-flex align-items-center justify-content-between border-bottom">
                <div>
                    <div class="fw-bold text-dark fs-3 lh-sm">{{ $selectedTeacher->formatted_short_name ?? $selectedTeacher->name }}</div>
                    <div class="text-muted small" style="font-size: 0.68rem;">
                        NIP/ID: {{ $selectedTeacher->formatted_id ?: '-' }}
                    </div>
                </div>
                <span class="badge bg-purple-lt fw-bold" style="font-size: 0.72rem;">
                    {{ $schedules->sum(fn($s) => $s->end_period - $s->start_period + 1) }} JP Mengajar
                </span>
            </div>
            <div class="card-body p-2 px-3">
                <div class="d-flex flex-column gap-1.5">
                    @foreach($schedules as $s)
                        @php
                            $isCurrentActive = ($activePeriod && $activePeriod >= $s->start_period && $activePeriod <= $s->end_period);
                            $startTime = $s->start_time ?: ($periodTimes[$s->start_period]['start'] ?? '');
                            $endTime = $s->end_time ?: ($periodTimes[$s->end_period]['end'] ?? '');
                        @endphp
                        <div class="p-2 rounded-2 border schedule-item-card {{ $isCurrentActive ? 'bg-primary-lt border-primary shadow-xs' : 'bg-light border-light' }}" data-search-text="{{ strtolower(($s->subject?->name ?? '') . ' ' . ($s->teacher?->name ?? '') . ' ' . ($s->classroom?->name ?? '')) }}">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="badge {{ $isCurrentActive ? 'bg-primary text-white' : 'bg-secondary text-white' }}" style="font-size: 0.68rem; padding: 2px 7px;">
                                        Jam {{ $s->start_period }} s.d {{ $s->end_period }}
                                    </span>
                                    <span class="text-muted small fw-semibold" style="font-size: 0.68rem;">
                                        <i class="ti ti-clock me-0.5"></i>{{ $startTime }} - {{ $endTime }} WIB
                                    </span>
                                </div>
                                <span class="badge bg-blue-lt fw-bold" style="font-size: 0.68rem;">
                                    Kelas {{ $s->classroom?->name ?? '-' }}
                                </span>
                            </div>
                            <div class="fw-bold text-dark fs-4 lh-sm mb-0.5">
                                <i class="ti ti-book text-primary me-0.5"></i> {{ $s->subject?->name ?? 'Mata Pelajaran' }}
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-muted small" style="font-size: 0.72rem;">
                                <span>
                                    Wali Kelas: {{ $s->classroom?->homeroomTeacher?->formatted_short_name ?? '-' }}
                                </span>
                                @if($isCurrentActive)
                                    <span class="badge bg-success text-white" style="font-size: 0.6rem; padding: 2px 5px;">
                                        Sedang Berlangsung
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    @else
        {{-- MODE 3: TAMPILAN KELOMPOK PER-KELAS (SEMUA KELAS) --}}
        @foreach($groupedByClassroom as $cId => $classSchedules)
            @php
                $classroom = $classSchedules->first()?->classroom;
                $totalJp = $classSchedules->sum(fn($s) => $s->end_period - $s->start_period + 1);
            @endphp
            @if($classroom)
                <div class="card border-0 shadow-sm rounded-3 mb-2 classroom-schedule-group" data-classroom-name="{{ strtolower($classroom->name) }}">
                    <div class="card-header bg-white p-2 px-3 d-flex align-items-center justify-content-between border-bottom">
                        <div class="d-flex align-items-center gap-1.5 overflow-hidden">
                            <span class="avatar avatar-sm bg-blue-lt text-primary rounded-circle flex-shrink-0" style="width: 28px; height: 28px;">
                                <i class="ti ti-building fs-3"></i>
                            </span>
                            <div class="overflow-hidden text-truncate">
                                <div class="fw-bold text-dark fs-4 lh-sm text-truncate">{{ $classroom->name }}</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                                    Wali: {{ $classroom->homeroomTeacher?->formatted_short_name ?? '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <span class="badge bg-light text-dark border" style="font-size: 0.68rem;">
                                {{ $totalJp }} JP
                            </span>
                            <a href="{{ route('attendance.subject.school-schedule', ['day' => $selectedDay, 'classroom_id' => $classroom->id]) }}" class="btn btn-sm btn-ghost-primary p-1 rounded-circle" title="Lihat Fokus Kelas Ini">
                                <i class="ti ti-chevron-right fs-2"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-2 px-2.5">
                        <div class="d-flex flex-column gap-1">
                            @foreach($classSchedules as $s)
                                @php
                                    $isCurrentActive = ($activePeriod && $activePeriod >= $s->start_period && $activePeriod <= $s->end_period);
                                    $startTime = $s->start_time ?: ($periodTimes[$s->start_period]['start'] ?? '');
                                    $endTime = $s->end_time ?: ($periodTimes[$s->end_period]['end'] ?? '');
                                @endphp
                                <div class="p-1.5 px-2 rounded-2 border d-flex align-items-center justify-content-between gap-1 schedule-item-card {{ $isCurrentActive ? 'bg-primary-lt border-primary' : 'bg-light border-light' }}" data-search-text="{{ strtolower(($s->subject?->name ?? '') . ' ' . ($s->teacher?->name ?? '') . ' ' . $classroom->name) }}">
                                    <div class="d-flex align-items-center gap-1.5 flex-fill overflow-hidden">
                                        <span class="badge {{ $isCurrentActive ? 'bg-primary text-white' : 'bg-secondary text-white' }} flex-shrink-0" style="font-size: 0.65rem; padding: 2px 6px;">
                                            Jam {{ $s->start_period }}-{{ $s->end_period }}
                                        </span>
                                        <div class="overflow-hidden text-truncate">
                                            <div class="fw-bold text-dark lh-sm text-truncate" style="font-size: 0.76rem;">
                                                {{ $s->subject?->name ?? '-' }}
                                            </div>
                                            <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                                                <i class="ti ti-user me-0.5"></i>{{ $s->teacher?->formatted_short_name ?? $s->teacher?->name ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0" style="font-size: 0.68rem;">
                                        <span class="text-muted d-block">{{ $startTime }}</span>
                                        @if($isCurrentActive)
                                            <span class="badge bg-success text-white" style="font-size: 0.58rem; padding: 1px 4px;">Aktif</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</div>

<!-- Navigasi Pintasan Cepat Kembali ke KBM -->
<div class="p-1 text-center mb-4">
    <a href="{{ route('attendance.subject.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" style="font-size: 0.75rem;">
        <i class="ti ti-arrow-left me-1"></i> Kembali ke Presensi KBM
    </a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('live-schedule-search');
    if (!searchInput) return;

    // Filter instan di sisi klien
    searchInput.addEventListener('input', function() {
        var query = this.value.trim().toLowerCase();
        if (query === '') {
            document.querySelectorAll('.schedule-item-card').forEach(function(el) {
                el.classList.remove('d-none');
            });
            document.querySelectorAll('.classroom-schedule-group').forEach(function(el) {
                el.classList.remove('d-none');
            });
            return;
        }

        document.querySelectorAll('.classroom-schedule-group').forEach(function(group) {
            var items = group.querySelectorAll('.schedule-item-card');
            var groupMatch = false;

            items.forEach(function(item) {
                var text = item.getAttribute('data-search-text') || '';
                if (text.indexOf(query) !== -1) {
                    item.classList.remove('d-none');
                    groupMatch = true;
                } else {
                    item.classList.add('d-none');
                }
            });

            if (groupMatch) {
                group.classList.remove('d-none');
            } else {
                group.classList.add('d-none');
            }
        });
    });
});
</script>
@endpush
@endsection
