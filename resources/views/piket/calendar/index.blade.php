@extends('layouts.app')

@section('title', 'Kalender Piket')

@section('content')
<div class="container-xl">
    <!-- Header -->
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase tracking-wider text-muted">
                    Monitoring Jadwal
                </div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-calendar-event text-primary me-2 fs-1"></i>
                    Kalender Petugas Piket
                </h2>
                <div class="text-secondary mt-1">
                    Visualisasi jadwal piket harian, status kehadiran, dan jurnal per bulan.
                </div>
            </div>
            <div class="col-auto ms-auto d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('piket.calendar.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-chevron-left"></i> {{ $prevMonth->translatedFormat('F') }}
                    </a>
                    <button class="btn btn-sm btn-primary fw-bold" disabled>
                        {{ $currentMonth->translatedFormat('F Y') }}
                    </button>
                    <a href="{{ route('piket.calendar.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="btn btn-sm btn-outline-secondary">
                        {{ $nextMonth->translatedFormat('F') }} <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
                <a href="{{ route('piket.calendar.index') }}" class="btn btn-sm btn-white">
                    Bulan Ini
                </a>
            </div>
        </div>
    </div>

    <!-- Calendar Table Grid -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" style="table-layout: fixed; min-width: 900px;">
                <thead class="text-center text-uppercase bg-light" style="font-size: 0.8rem; letter-spacing: 0.05rem;">
                    <tr>
                        <th style="width: 14.28%;">Senin</th>
                        <th style="width: 14.28%;">Selasa</th>
                        <th style="width: 14.28%;">Rabu</th>
                        <th style="width: 14.28%;">Kamis</th>
                        <th style="width: 14.28%;">Jumat</th>
                        <th style="width: 14.28%;">Sabtu</th>
                        <th style="width: 14.28%;" class="text-danger">Minggu</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $dayCounter = 1;
                        $totalCells = ceil(($daysInMonth + $startDayOfWeek - 1) / 7) * 7;
                    @endphp

                    @for($cell = 1; $cell <= $totalCells; $cell++)
                        @if(($cell - 1) % 7 === 0)
                            <tr>
                        @endif

                        @php
                            $isDayInMonth = ($cell >= $startDayOfWeek && $dayCounter <= $daysInMonth);
                            $data = $isDayInMonth ? ($calendarData[$dayCounter] ?? null) : null;
                        @endphp

                        @if($isDayInMonth && $data)
                            <td class="p-2 align-top {{ $data['isToday'] ? 'bg-yellow-lt border-warning border-2' : ($data['isSunday'] ? 'bg-light' : 'bg-white') }}" style="height: 130px; vertical-align: top;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge {{ $data['isToday'] ? 'bg-warning text-dark fw-bold' : ($data['isSunday'] ? 'bg-danger text-white' : 'bg-secondary text-white') }} rounded-pill" style="font-size: 0.8rem;">
                                        {{ $dayCounter }}
                                    </span>
                                    @if($data['journal'])
                                        <a href="{{ route('piket.journals.show', $data['journal']) }}" class="badge {{ $data['journal']->isCompleted() ? 'bg-success text-white' : 'bg-info text-white' }} text-decoration-none">
                                            <i class="ti ti-notebook"></i> Jurnal
                                        </a>
                                    @endif
                                </div>

                                @if(!$data['isSunday'])
                                    <!-- Daftar Guru Piket -->
                                    <div class="mt-1" style="font-size: 0.76rem;">
                                        @foreach($data['schedules'] as $sch)
                                            @php
                                                // Cek apakah guru ini ditukar hari ini
                                                $swap = $data['swaps']->firstWhere('original_schedule_id', $sch->id);
                                            @endphp
                                            <div class="text-truncate mb-1 p-1 rounded {{ $sch->role === 'koordinator' ? 'bg-purple-lt text-purple fw-bold' : 'bg-blue-lt text-dark' }}">
                                                @if($swap)
                                                    <span class="text-decoration-line-through text-muted">{{ $sch->teacher?->formatted_short_name }}</span>
                                                    <i class="ti ti-arrow-right text-success mx-1"></i>
                                                    <span class="text-success fw-bold">{{ $swap->replacementTeacher?->formatted_short_name }}</span>
                                                @else
                                                    <i class="ti {{ $sch->role === 'koordinator' ? 'ti-crown' : 'ti-user' }} me-1"></i>
                                                    {{ $sch->teacher?->formatted_short_name ?? '-' }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Quick Stats if Journal exists -->
                                    @if($data['journal'])
                                        <div class="d-flex gap-1 mt-1 flex-wrap" style="font-size: 0.68rem;">
                                            @if($data['journal']->student_permits_count > 0)
                                                <span class="badge bg-warning-lt py-0 px-1 text-dark" title="Siswa Terlambat/Izin">
                                                    {{ $data['journal']->student_permits_count }} izin
                                                </span>
                                            @endif
                                            @if($data['journal']->inval_classes_count > 0)
                                                <span class="badge bg-info-lt py-0 px-1" title="Kelas Inval">
                                                    {{ $data['journal']->inval_classes_count }} inval
                                                </span>
                                            @endif
                                            @if($data['journal']->incidents_count > 0)
                                                <span class="badge bg-danger-lt py-0 px-1" title="Kasus">
                                                    {{ $data['journal']->incidents_count }} kasus
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <div class="text-center text-muted small mt-4">
                                        Libur
                                    </div>
                                @endif
                            </td>
                            @php $dayCounter++; @endphp
                        @else
                            <td class="p-2 bg-light-subtle" style="height: 130px;"></td>
                        @endif

                        @if($cell % 7 === 0)
                            </tr>
                        @endif
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
