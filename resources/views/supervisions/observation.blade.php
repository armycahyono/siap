@extends('layouts.app')

@section('title', 'Observasi Pembelajaran: ' . $supervision->teacher->name)

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-notebook me-2"></i> Tahap 2: Instrumen Observasi Pembelajaran
        </h2>
        <div class="text-muted small">
            Rubrik penilaian KBM di kelas. <strong>Legenda Skor:</strong> 1 = Belum Terlihat · 2 = Mulai Terlihat · 3 = Terlihat · 4 = Sangat Baik
        </div>
    </div>
    <div class="col-auto">
        <a href="{{ route('supervisions.show', $supervision) }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Berkas
        </a>
    </div>
</div>

<!-- Header Identity Banner -->
<div class="card bg-blue-lt border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center small">
            <div class="col-md-3"><strong>Guru:</strong> {{ $supervision->teacher->name }}</div>
            <div class="col-md-3"><strong>Mata Pelajaran:</strong> {{ $supervision->subject }}</div>
            <div class="col-md-3"><strong>Kelas:</strong> {{ $supervision->class_name }}</div>
            <div class="col-md-3"><strong>Supervisor:</strong> {{ $supervision->supervisor->name }}</div>
        </div>
    </div>
</div>

@php
    $canConduct = auth()->user()->canEditSupervision($supervision);
@endphp

@if(! $canConduct)
    <div class="alert alert-info d-flex align-items-center mb-4">
        <i class="ti ti-info-circle fs-2 me-2"></i>
        <div>
            <strong>Mode Pratinjau (Hanya Lihat):</strong>
            @if($supervision->status === 'disetujui' || $supervision->status === 'selesai')
                Supervisi ini telah resmi disetujui & disahkan oleh Kepala Sekolah sehingga lembar nilai telah dikunci permanen.
            @else
                Pengisian dan perubahan skor observasi hanya dapat dilakukan oleh Supervisor penilai yang ditugaskan.
            @endif
        </div>
    </div>
@endif

<form id="observationForm" action="{{ route('supervisions.save-observation', $supervision) }}" method="POST">
    @csrf

    <div class="row g-4">
        <!-- Main Form (Rubric Indicators) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Pelaksanaan Observasi</label>
                            <input type="date" name="obs_date" class="form-control" value="{{ old('obs_date', $supervision->obs_date ? $supervision->obs_date->format('Y-m-d') : date('Y-m-d')) }}" {{ $canConduct ? 'required' : 'readonly disabled' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Waktu Mulai Observasi</label>
                            <input type="text" name="obs_time" class="form-control" placeholder="07.35 WIB" value="{{ old('obs_time', $supervision->obs_time ?: date('H:i') . ' WIB') }}" {{ $canConduct ? 'required' : 'readonly disabled' }}>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rubric Phases Accordions -->
            <div class="accordion" id="rubricAccordion">
                @foreach($phases as $phaseIndex => $phase)
                <div class="accordion-item card border-0 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header" id="heading-{{ $phase->id }}">
                        <button class="accordion-button fw-bold text-navy bg-white py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $phase->id }}" aria-expanded="true">
                            <span class="badge bg-primary text-white me-2">{{ $phase->code ?: ($phaseIndex + 1) }}</span>
                            {{ $phase->name }}
                            <span class="badge bg-secondary-lt ms-2 small">{{ $phase->indicators->count() }} Indikator</span>
                        </button>
                    </h2>
                    <div id="collapse-{{ $phase->id }}" class="accordion-collapse collapse show">
                        <div class="accordion-body p-3 pt-0">
                            @foreach($phase->indicators as $indIndex => $indicator)
                            @php
                                $val = $existingScores[$indicator->id] ?? 0;
                                $note = $existingNotes[$indicator->id] ?? '';
                            @endphp
                            <div class="py-3 border-bottom indicator-item" data-max="{{ $indicator->max_score }}">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                    <div class="fw-bold text-dark pe-2" style="font-size: 0.95rem;">
                                        <span class="text-muted small me-1">{{ $indicator->code ?: ($indIndex + 1) . '.' }}</span>
                                        {{ $indicator->title }}
                                    </div>

                                    <!-- Score Radios 1-4 -->
                                    <div class="btn-group score-pill-group" role="group">
                                        @for($s = 1; $s <= $indicator->max_score; $s++)
                                            <input type="radio" 
                                                class="btn-check score-radio" 
                                                name="scores[{{ $indicator->id }}]" 
                                                id="score-{{ $indicator->id }}-{{ $s }}" 
                                                value="{{ $s }}" 
                                                {{ $val == $s ? 'checked' : '' }} 
                                                {{ $canConduct ? '' : 'disabled' }}
                                                onchange="calculateTotals()">
                                            <label class="btn btn-sm btn-outline-secondary px-3 {{ $val == $s ? 'active fw-bold' : '' }}" for="score-{{ $indicator->id }}-{{ $s }}">
                                                {{ $s }}
                                            </label>
                                        @endfor
                                    </div>
                                </div>

                                @if($indicator->description)
                                    <div class="text-muted small mb-2 fst-italic">{{ $indicator->description }}</div>
                                @endif

                                <!-- Supervisor note per indicator -->
                                <input type="text" 
                                    name="notes[{{ $indicator->id }}]" 
                                    class="form-control form-control-sm text-secondary bg-light" 
                                    placeholder="Catatan amatan supervisor untuk butir ini (opsional)..." 
                                    value="{{ $note }}"
                                    {{ $canConduct ? '' : 'readonly disabled' }}>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Sticky Right Sidebar: Live Calculator -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px; z-index: 10;">
                <div class="card-header bg-white py-3">
                    <h3 class="card-title fw-bold text-dark mb-0">
                        <i class="ti ti-calculator me-2 text-primary"></i> Ringkasan Nilai Realtime
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="text-muted small mb-1">Capaian Persentase:</div>
                        <div class="display-3 fw-bold text-primary mb-1" id="livePercentage">
                            {{ $supervision->percentage }}%
                        </div>
                        <div>
                            <span class="badge bg-primary px-3 py-1 fs-3" id="livePredicate">
                                {{ $supervision->predicate ?: 'Belum Lengkap' }}
                            </span>
                        </div>
                    </div>

                    <div class="list-group list-group-flush mb-4">
                        <div class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Total Skor Perolehan:</span>
                            <strong class="text-dark fs-3" id="liveTotalScore">{{ $supervision->total_score }}</strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Skor Maksimal Ideal:</span>
                            <strong class="text-dark fs-3" id="liveMaxScore">{{ $supervision->max_score ?: 92 }}</strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Butir Terisi:</span>
                            <strong class="text-dark" id="liveFilledCount">0</strong>
                        </div>
                    </div>

                    <!-- Grading Scale Info -->
                    <div class="p-3 bg-light rounded-3 small mb-4 border">
                        <strong class="d-block text-dark mb-1">Standar Predikat Sekolah:</strong>
                        <div class="d-flex justify-content-between"><span>Sangat Baik:</span> <span>≥ {{ $setting->grade_a_min }}%</span></div>
                        <div class="d-flex justify-content-between"><span>Baik:</span> <span>{{ $setting->grade_b_min }}% - {{ $setting->grade_a_min - 1 }}%</span></div>
                        <div class="d-flex justify-content-between"><span>Cukup:</span> <span>{{ $setting->grade_c_min }}% - {{ $setting->grade_b_min - 1 }}%</span></div>
                        <div class="d-flex justify-content-between text-danger"><span>Perlu Bimbingan:</span> <span>< {{ $setting->grade_c_min }}%</span></div>
                    </div>

                    <div class="d-grid gap-2">
                        @if($canConduct)
                        <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Penilaian Sementara
                        </button>
                        <button type="submit" name="continue" value="1" class="btn btn-warning text-dark fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                            Simpan & Lanjut ke Catatan Pasca <i class="ti ti-arrow-right ms-1"></i>
                        </button>
                        @else
                        <a href="{{ route('supervisions.post-observation', $supervision) }}" class="btn btn-warning text-dark fw-bold" style="background-color: #c99a3b; border-color: #c99a3b; color: #fff !important;">
                            Lihat Catatan Pascaobservasi <i class="ti ti-arrow-right ms-1"></i>
                        </a>
                        <a href="{{ route('supervisions.show', $supervision) }}" class="btn btn-outline-secondary">
                            Kembali ke Berkas Supervisi
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const GRADE_A = {{ (int) $setting->grade_a_min }};
    const GRADE_B = {{ (int) $setting->grade_b_min }};
    const GRADE_C = {{ (int) $setting->grade_c_min }};

    function calculateTotals() {
        let totalScore = 0;
        let maxScore = 0;
        let filledCount = 0;

        const items = document.querySelectorAll('.indicator-item');
        items.forEach(item => {
            const max = parseInt(item.getAttribute('data-max')) || 4;
            maxScore += max;

            const checked = item.querySelector('.score-radio:checked');
            if (checked) {
                totalScore += parseInt(checked.value) || 0;
                filledCount++;
            }
        });

        const percentage = maxScore > 0 ? ((totalScore / maxScore) * 100).toFixed(2) : 0;

        let predicate = 'Perlu Bimbingan';
        let badgeClass = 'bg-danger';

        if (percentage >= GRADE_A) {
            predicate = 'Sangat Baik';
            badgeClass = 'bg-success';
        } else if (percentage >= GRADE_B) {
            predicate = 'Baik';
            badgeClass = 'bg-primary';
        } else if (percentage >= GRADE_C) {
            predicate = 'Cukup';
            badgeClass = 'bg-warning text-dark';
        }

        document.getElementById('liveTotalScore').innerText = totalScore;
        document.getElementById('liveMaxScore').innerText = maxScore;
        document.getElementById('livePercentage').innerText = percentage + '%';
        document.getElementById('liveFilledCount').innerText = filledCount + ' / ' + items.length;

        const predBadge = document.getElementById('livePredicate');
        predBadge.innerText = predicate;
        predBadge.className = 'badge px-3 py-1 fs-3 ' + badgeClass;
    }

    // Run once on initial load
    window.addEventListener('DOMContentLoaded', calculateTotals);
</script>
@endpush
@endsection
