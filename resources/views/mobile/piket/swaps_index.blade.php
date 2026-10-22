@extends('layouts.mobile')

@section('title', 'Tukar Jadwal Piket — APK SIAP')
@section('page_title', 'Tukar Jadwal Piket')
@section('page_subtitle', 'Permohonan Pergantian Tugas')
@section('back_url', route('piket.dashboard'))

@section('content')
<!-- Header Card -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
    <div class="card-body p-2 px-3 text-white d-flex align-items-center justify-content-between">
        <div>
            <div class="fw-bold fs-4 lh-sm">Tukar Jadwal Piket</div>
            <div class="text-white-50 small" style="font-size: 0.72rem;">Permohonan saling tukar shift piket antar guru</div>
        </div>
        <button type="button" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 shadow-sm sheet-open" data-sheet="#sheet-add-swap" style="font-size: 0.75rem;">
            <i class="ti ti-arrows-left-right me-1"></i> + Ajukan
        </button>
    </div>
</div>

<!-- Feed Daftar Permohonan Tukar Jadwal -->
<div class="mb-4">
    @forelse($swaps as $s)
        @php
            $isWaiting = in_array($s->status, ['menunggu', 'pending']);
            $isApproved = in_array($s->status, ['disetujui', 'approved']);
            $canRespond = $isWaiting && (auth()->id() === $s->replacement_teacher_id || auth()->user()->canManagePiketSchedules());
        @endphp
        <div class="card border-0 shadow-sm rounded-3 mb-1.5 {{ $isWaiting ? 'border-start border-warning border-3' : ($isApproved ? 'border-start border-success border-3' : 'border-start border-danger border-3') }}">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge {{ $isApproved ? 'bg-success text-white' : ($isWaiting ? 'bg-warning text-dark' : 'bg-danger text-white') }}" style="font-size: 0.65rem; padding: 2px 7px;">
                        {{ ucfirst($s->status) }}
                    </span>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        <i class="ti ti-calendar me-0.5"></i>
                        {{ $s->duty_date?->format('d/m/Y') }} ({{ $s->originalSchedule?->day ?? '-' }})
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="flex-fill">
                        <div class="text-muted" style="font-size: 0.68rem;">Guru Pemohon:</div>
                        <div class="fw-bold text-dark fs-4 lh-sm">{{ $s->requester?->formatted_short_name ?? $s->requester?->name }}</div>
                    </div>
                    <i class="ti ti-arrow-narrow-right text-primary fs-2"></i>
                    <div class="flex-fill">
                        <div class="text-muted" style="font-size: 0.68rem;">Guru Pengganti:</div>
                        <div class="fw-bold text-primary fs-4 lh-sm">{{ $s->replacementTeacher?->formatted_short_name ?? $s->replacementTeacher?->name }}</div>
                    </div>
                </div>

                <div class="p-1.5 bg-light rounded-2 small text-secondary mb-1.5" style="font-size: 0.72rem;">
                    <strong>Alasan:</strong> {{ $s->reason }}
                    @if($s->notes)
                        <div class="text-muted mt-0.5" style="font-size: 0.68rem;">Catatan: {{ $s->notes }}</div>
                    @endif
                </div>

                @if($canRespond)
                    <div class="d-flex gap-2 pt-1 border-top">
                        <form action="{{ route('piket.swaps.approve', $s) }}" method="POST" class="flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success rounded-pill w-100 py-1 fw-bold" style="font-size: 0.75rem;">
                                <i class="ti ti-check me-0.5"></i> Setujui
                            </button>
                        </form>
                        <form action="{{ route('piket.swaps.reject', $s) }}" method="POST" class="flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill w-100 py-1 fw-bold" style="font-size: 0.75rem;">
                                <i class="ti ti-x me-0.5"></i> Tolak
                            </button>
                        </form>
                    </div>
                @else
                    <div class="d-flex align-items-center justify-content-between pt-1 border-top text-muted" style="font-size: 0.68rem;">
                        <span>Pos: {{ $s->originalSchedule?->duty_location ?: 'Meja Piket Utama' }}</span>
                        @if($s->approver)
                            <span>Diproses: {{ $s->approver->formatted_short_name ?? $s->approver->name }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
            <i class="ti ti-arrows-left-right fs-1 d-block mb-1 opacity-50"></i>
            <div class="small fw-semibold">Belum ada permohonan tukar jadwal piket.</div>
        </div>
    @endforelse

    @if($swaps->hasPages())
        <div class="d-flex justify-content-center mt-2">
            {{ $swaps->links() }}
        </div>
    @endif
</div>

<!-- Framework7 Bottom Sheet: Formulir Tukar Jadwal -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-add-swap" style="height: auto; max-height: 85vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-dark fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-arrows-left-right text-primary"></i> Ajukan Tukar Jadwal
            </div>
            <div class="right">
                <a class="link sheet-close text-muted" href="#"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="overflow-y: auto;">
        <form action="{{ route('piket.swaps.store') }}" method="POST">
            @csrf
            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Pilih Jadwal Piket Anda <span class="text-danger">*</span></label>
                <select name="original_schedule_id" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="">-- Pilih Hari Tugas Piket Anda --</option>
                    @foreach($mySchedules as $ms)
                        <option value="{{ $ms->id }}">
                            Hari {{ $ms->day }} ({{ $ms->duty_location ?: 'Meja Piket Utama' }})
                        </option>
                    @endforeach
                </select>
                @if($mySchedules->isEmpty())
                    <div class="text-danger small mt-0.5" style="font-size: 0.7rem;">Anda tidak terdaftar dalam jadwal piket semester ini.</div>
                @endif
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Guru Pengganti (Tujuan Tukar) <span class="text-danger">*</span></label>
                <select name="replacement_teacher_id" class="form-select form-select-sm" required style="font-size: 0.82rem;">
                    <option value="">-- Pilih Guru Pengganti --</option>
                    @foreach($allTeachers as $at)
                        <option value="{{ $at->id }}">{{ $at->formatted_short_name ?? $at->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1">Tanggal Tugas yang Ditukar <span class="text-danger">*</span></label>
                <input type="date" name="duty_date" class="form-control form-control-sm" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" required style="font-size: 0.82rem;">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1">Alasan Tukar Jadwal <span class="text-danger">*</span></label>
                <textarea name="reason" rows="2" class="form-control form-control-sm" placeholder="Contoh: Ada keperluan dinas luar mendesak..." required style="font-size: 0.82rem;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;" {{ $mySchedules->isEmpty() ? 'disabled' : '' }}>
                <i class="ti ti-check fs-2"></i>
                <span>Kirim Permohonan Tukar</span>
            </button>
        </form>
    </div>
</div>
@endsection
