@extends('layouts.app')

@section('title', 'Tukar Jadwal Piket')

@section('content')
<div class="container-xl">
    <!-- Header (Model 2: Hero Context Banner) -->
    @include('layouts._hero_header', [
        'title' => 'Tukar Jadwal Piket (Duty Swap)',
        'subtitle' => 'Ajukan permohonan pergantian hari piket resmi jika berhalangan tugas.',
        'icon' => 'ti ti-arrows-left-right',
        'category' => 'FLEKSIBILITAS TUGAS PIKET',
        'actions' => '<button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-swap"><i class="ti ti-plus me-1"></i> Ajukan Tukar Piket</button>'
    ])

    <!-- Table Swaps -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead class="table-light">
                    <tr>
                        <th>Pemohon (Jadwal Asli)</th>
                        <th>Guru Pengganti</th>
                        <th>Tanggal Tugas</th>
                        <th>Alasan Pergantian</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi / Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($swaps as $swap)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $swap->requester?->name ?? '-' }}</div>
                                <span class="badge bg-blue-lt">Hari {{ $swap->originalSchedule?->day ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $swap->replacementTeacher?->name ?? '-' }}</div>
                                <span class="text-muted small">{{ $swap->replacementTeacher?->formatted_id ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $swap->duty_date?->translatedFormat('d F Y') }}</div>
                                <span class="text-muted small">({{ $swap->duty_date?->translatedFormat('l') }})</span>
                            </td>
                            <td>
                                <div class="text-secondary">{{ $swap->reason }}</div>
                            </td>
                            <td class="text-center">
                                @if($swap->status === 'disetujui')
                                    <span class="badge bg-success text-white"><i class="ti ti-check me-1"></i> Disetujui</span>
                                    @if($swap->approver)
                                        <div class="text-muted small mt-1">Oleh: {{ $swap->approver->name }}</div>
                                    @endif
                                @elseif($swap->status === 'ditolak')
                                    <span class="badge bg-danger text-white"><i class="ti ti-x me-1"></i> Ditolak</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i> Menunggu Persetujuan</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($swap->status === 'menunggu')
                                    @if(auth()->user()->canManagePiketSchedules() || auth()->id() === $swap->replacement_teacher_id)
                                    <div class="d-flex justify-content-end gap-1">
                                        <form action="{{ route('piket.swaps.approve', $swap) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="ti ti-check"></i> Setujui
                                            </button>
                                        </form>
                                        <form action="{{ route('piket.swaps.reject', $swap) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="ti ti-x"></i> Tolak
                                            </button>
                                        </form>
                                    </div>
                                    @else
                                    <span class="badge bg-warning-lt text-warning"><i class="ti ti-clock me-1"></i> Menunggu Guru Pengganti</span>
                                    @endif
                                @else
                                    <span class="text-muted small">Selesai diverifikasi</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="ti ti-arrows-diff fs-1 d-block mb-1"></i>
                                Belum ada riwayat permohonan tukar jadwal piket.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($swaps->hasPages())
            <div class="card-footer py-2">
                {{ $swaps->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Form Tukar Piket -->
<div class="modal fade" id="modal-add-swap" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('piket.swaps.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Ajukan Permohonan Tukar Piket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Jadwal Asli Anda</label>
                        <select name="original_schedule_id" class="form-select" required>
                            @forelse($mySchedules as $ms)
                                <option value="{{ $ms->id }}">Hari {{ $ms->day }} ({{ $ms->role }} - {{ $ms->duty_location ?: 'Meja Piket' }})</option>
                            @empty
                                <option value="" disabled>Anda belum terdaftar dalam jadwal piket aktif.</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Guru Pengganti</label>
                        <select name="replacement_teacher_id" class="form-select" required>
                            <option value="">-- Pilih Guru Pengganti --</option>
                            @foreach($allTeachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->formatted_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Tanggal Tugas Yang Ditukar</label>
                        <input type="date" name="duty_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Alasan Tukar Piket</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Contoh: Ada penugasan dinas luar dari Kepala Sekolah..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim Permohonan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
