@extends('layouts.mobile')

@php
    $isPrincipalOrSuperAdmin = auth()->user()->isPrincipal() || auth()->user()->isSuperAdmin();
    $canApprove = $isPrincipalOrSuperAdmin && !$isPersonalScope;
    $currentStatus = $canApprove ? request('status', 'pending') : 'all';
@endphp

@section('title', ($isPersonalScope ? 'Izin & Cuti Saya' : ($currentStatus === 'pending' ? 'Persetujuan Izin GTK' : 'Daftar Izin & Cuti GTK')) . ' — APK SIAP')
@section('page_title', ($isPersonalScope ? 'Izin & Cuti Saya' : ($currentStatus === 'pending' ? 'Persetujuan Izin GTK' : 'Daftar Izin & Cuti GTK')))
@section('page_subtitle', $isPersonalScope ? 'Pengajuan Mandiri: ' . auth()->user()?->name : 'Panel Pimpinan: ' . auth()->user()?->name)
@section('back_url', 'back')

@section('content')

@if($canManageGtkPermits)
<!-- Switcher Scope: Mode Pimpinan vs Mode Mandiri GTK -->
<div class="mb-2">
    <div class="p-1 rounded-3 bg-white shadow-sm border d-flex gap-1">
        <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'manage']) }}" 
           class="flex-fill py-1.5 px-2 rounded-2 text-center text-decoration-none fw-bold {{ !$isPersonalScope ? 'text-white' : 'text-secondary' }}"
           style="{{ !$isPersonalScope ? 'background-color: #102d52; box-shadow: 0 2px 6px rgba(16,45,82,0.3);' : 'font-size: 0.74rem;' }}">
            <i class="ti ti-building-community me-1"></i> Daftar Izin GTK
        </a>
        <a href="{{ route('attendance.gtk-permits.index', ['scope' => 'personal']) }}" 
           class="flex-fill py-1.5 px-2 rounded-2 text-center text-decoration-none fw-bold {{ $isPersonalScope ? 'text-white' : 'text-secondary' }}"
           style="{{ $isPersonalScope ? 'background-color: #4f46e5; box-shadow: 0 2px 6px rgba(79,70,229,0.3);' : 'font-size: 0.74rem;' }}">
            <i class="ti ti-user-check me-1"></i> Izin &amp; Cuti Saya
        </a>
    </div>
</div>
@endif

@if($canApprove)
<!-- Tab Navigasi Persetujuan vs Riwayat (Khusus Kepala Sekolah / Super Admin di Mode Pimpinan) -->
<div class="mb-2.5">
    <div class="custom-tab-switch p-1 rounded-pill bg-white shadow-sm border d-flex">
        <a href="{{ route('attendance.gtk-permits.index', array_merge(request()->except('page'), ['scope' => 'manage', 'status' => 'pending'])) }}" 
           class="tab-switch-item flex-fill py-1.5 px-2 rounded-pill text-center fw-bold text-decoration-none {{ $currentStatus === 'pending' ? 'active-pending' : 'text-muted' }}">
            <i class="ti ti-clock-exclamation me-1"></i> Perlu Persetujuan ({{ $pendingPermitsCount ?? 0 }})
        </a>
        <a href="{{ route('attendance.gtk-permits.index', array_merge(request()->except('page'), ['scope' => 'manage', 'status' => 'all'])) }}" 
           class="tab-switch-item flex-fill py-1.5 px-2 rounded-pill text-center fw-bold text-decoration-none {{ $currentStatus !== 'pending' ? 'active-all' : 'text-muted' }}">
            <i class="ti ti-history me-1"></i> Semua Riwayat GTK
        </a>
    </div>
</div>
@endif

<!-- Filter Periode Bulan -->
<div class="card border-0 shadow-sm rounded-3 mb-2.5">
    <div class="card-body p-2 px-3">
        <form action="{{ route('attendance.gtk-permits.index') }}" method="GET" class="d-flex align-items-center justify-content-between gap-2">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="status" value="{{ $currentStatus }}">

            <div class="d-flex align-items-center gap-1.5 text-dark fw-bold" style="font-size: 0.8rem;">
                <span class="avatar avatar-xs bg-primary-lt text-primary rounded-circle" style="width: 24px; height: 24px;">
                    <i class="ti ti-calendar-event fs-3"></i>
                </span>
                <span>Periode Bulan:</span>
            </div>

            <div class="d-flex align-items-center gap-1">
                <input type="month" name="month" class="form-control form-control-sm text-center fw-bold border bg-light text-dark" 
                       style="font-size: 0.82rem; width: 140px; padding: 5px 8px; border-radius: 8px;" 
                       value="{{ $month }}" onchange="this.form.submit()">
                @if($month && $month !== now()->format('Y-m'))
                    <a href="{{ route('attendance.gtk-permits.index', ['scope' => $scope, 'status' => $currentStatus, 'month' => now()->format('Y-m')]) }}" 
                       class="btn btn-sm btn-icon btn-outline-secondary" style="width: 30px; height: 30px; padding: 0;" title="Reset Bulan Ini">
                        <i class="ti ti-rotate-2 fs-3"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Daftar Pengajuan / Persetujuan Izin GTK -->
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2 px-1">
        <div class="d-flex align-items-center gap-1.5">
            <span class="avatar avatar-xs {{ $currentStatus === 'pending' ? 'bg-warning-lt text-warning' : ($isPersonalScope ? 'bg-indigo-lt text-indigo' : 'bg-primary-lt text-primary') }} rounded-circle" style="width: 20px; height: 20px;">
                <i class="ti {{ $currentStatus === 'pending' ? 'ti-clock' : ($isPersonalScope ? 'ti-user-check' : 'ti-file-text') }}" style="font-size: 0.75rem;"></i>
            </span>
            <span class="fw-bold text-dark" style="font-size: 0.82rem;">
                @if($isPersonalScope)
                    Riwayat Izin / Cuti Diri Sendiri
                @elseif($currentStatus === 'pending')
                    Menunggu Konfirmasi Pimpinan
                @else
                    Daftar Seluruh Izin &amp; Cuti GTK
                @endif
            </span>
        </div>
        <span class="badge bg-white text-muted border" style="font-size: 0.68rem;">Halaman {{ $permits->currentPage() }} dari {{ max(1, $permits->lastPage()) }}</span>
    </div>

    @forelse($permits as $p)
        @php
            $typeBg = match($p->permission_type) {
                'sakit' => '#fef3c7',
                'cuti' => '#f3e8ff',
                'dinas_luar' => '#dbeafe',
                default => '#e0f2fe',
            };
            $typeColor = match($p->permission_type) {
                'sakit' => '#92400e',
                'cuti' => '#6b21a8',
                'dinas_luar' => '#1e40af',
                default => '#0369a1',
            };
            $typeIcon = match($p->permission_type) {
                'sakit' => 'ti-first-aid-kit',
                'cuti' => 'ti-calendar-event',
                'dinas_luar' => 'ti-briefcase',
                default => 'ti-file-text',
            };
            $typeLabel = match($p->permission_type) {
                'sakit' => 'Sakit',
                'cuti' => 'Cuti Resmi',
                'dinas_luar' => 'Dinas Luar',
                default => 'Izin Pribadi',
            };

            $statusBadgeClass = match($p->status) {
                'approved' => 'badge-soft-success',
                'rejected' => 'badge-soft-danger',
                default => 'badge-soft-warning',
            };
            $statusLabel = match($p->status) {
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => 'Menunggu KS',
            };
            $statusIcon = match($p->status) {
                'approved' => 'ti-check',
                'rejected' => 'ti-x',
                default => 'ti-clock',
            };
        @endphp

        <div class="permit-item-card mb-2.5 {{ $p->isPending() ? 'border-pending' : '' }}">
            <!-- Header Kartu: Profil Pemohon & Status -->
            <div class="permit-card-top p-2.5 pb-2 d-flex align-items-start justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <div class="user-avatar-initial flex-shrink-0" style="background: {{ $typeBg }}; color: {{ $typeColor }};">
                        <i class="ti {{ $typeIcon }} fs-3"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-dark fs-4 lh-sm text-truncate" title="{{ $p->user?->name }}">
                            {{ $p->user?->name ?? 'GTK' }}
                        </div>
                        <div class="text-muted small text-truncate" style="font-size: 0.7rem; line-height: 1.2;">
                            <span class="badge {{ $p->user?->isStaff() ? 'bg-secondary-lt' : 'bg-blue-lt' }} py-0 px-1" style="font-size: 0.62rem;">
                                {{ $p->user?->isStaff() ? 'Staf TU' : 'Guru' }}
                            </span>
                            {{ $p->user?->position ?: ($p->user?->role?->name ?? 'Tenaga Pendidik') }}
                        </div>
                    </div>
                </div>

                <!-- Status Badge -->
                <div class="flex-shrink-0 text-end">
                    <span class="custom-status-badge {{ $statusBadgeClass }}">
                        <i class="ti {{ $statusIcon }}"></i>
                        <span>{{ $statusLabel }}</span>
                    </span>
                    <div class="text-muted mt-0.5" style="font-size: 0.65rem;">
                        {{ $p->created_at->diffForHumans() }}
                    </div>
                </div>
            </div>

            <!-- Detail Perizinan (Waktu & Kategori) -->
            <div class="px-2.5 py-1.5 bg-light-subtle border-top border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="custom-type-pill" style="background: {{ $typeBg }}; color: {{ $typeColor }};">
                            <i class="ti {{ $typeIcon }}"></i> {{ $typeLabel }}
                        </span>
                        <span class="badge bg-white text-dark border fw-bold" style="font-size: 0.68rem; padding: 2px 7px;">
                            {{ $p->duration_days }} Hari
                        </span>
                    </div>

                    <div class="text-dark small fw-semibold d-flex align-items-center gap-1" style="font-size: 0.74rem;">
                        <i class="ti ti-calendar text-primary"></i>
                        @if($p->start_date && $p->end_date && $p->start_date->ne($p->end_date))
                            <span>{{ $p->start_date->translatedFormat('d M') }} &ndash; {{ $p->end_date->translatedFormat('d M Y') }}</span>
                        @else
                            <span>{{ $p->start_date?->translatedFormat('d M Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Isi Alasan & Catatan -->
            <div class="p-2.5 pt-2">
                <div class="reason-box p-2 rounded-2 mb-2">
                    <div class="text-muted small fw-bold mb-0.5" style="font-size: 0.68rem;">ALASAN / KEPERLUAN:</div>
                    <div class="text-dark fw-medium" style="font-size: 0.8rem; line-height: 1.4;">
                        {{ $p->reason }}
                    </div>

                    @if($p->notes)
                        <div class="mt-1.5 pt-1.5 border-top border-light-subtle text-secondary small" style="font-size: 0.72rem;">
                            <i class="ti ti-notes text-muted me-1"></i><strong>Catatan:</strong> {{ $p->notes }}
                        </div>
                    @endif

                    @if($p->approval_note)
                        <div class="mt-1.5 pt-1.5 border-top border-light-subtle {{ $p->isRejected() ? 'text-danger' : 'text-success' }} small" style="font-size: 0.72rem;">
                            <i class="ti {{ $p->isRejected() ? 'ti-alert-circle' : 'ti-circle-check' }} me-1"></i>
                            <strong>Catatan KS:</strong> {{ $p->approval_note }}
                        </div>
                    @endif
                </div>

                <!-- Footer Aksi & Lampiran -->
                <div class="d-flex align-items-center justify-content-between pt-1 flex-wrap gap-2">
                    <!-- Lampiran Berkas Bukti -->
                    <div>
                        @if($p->document_path)
                            <a href="{{ asset('storage/' . $p->document_path) }}" target="_blank" 
                               class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-pill fw-semibold text-decoration-none d-inline-flex align-items-center gap-1" 
                               style="font-size: 0.72rem;">
                                <i class="ti ti-paperclip fs-3"></i>
                                <span>Lihat Surat / Bukti</span>
                            </a>
                        @else
                            <span class="text-muted small d-inline-flex align-items-center gap-1" style="font-size: 0.7rem;">
                                <i class="ti ti-file-off opacity-50"></i> Tanpa lampiran
                            </span>
                        @endif
                    </div>

                    <!-- Tombol Aksi Persetujuan / Batalkan -->
                    <div class="d-flex align-items-center gap-1.5 ms-auto">
                        @if($canApprove && $p->isPending())
                            <!-- Tombol Setujui -->
                            <form action="{{ route('attendance.gtk-permits.approve', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Setujui pengajuan izin ini?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success py-1 px-3 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                    <i class="ti ti-check fs-3"></i>
                                    <span>Setujui</span>
                                </button>
                            </form>

                            <!-- Tombol Tolak -->
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2.5 rounded-pill fw-bold d-inline-flex align-items-center gap-1" 
                                    style="font-size: 0.75rem;" 
                                    onclick="openRejectBottomSheet({{ $p->id }}, '{{ addslashes($p->user?->name ?? 'GTK') }}')">
                                <i class="ti ti-x fs-3"></i>
                                <span>Tolak</span>
                            </button>
                        @endif

                        @if($canApprove || (auth()->id() === $p->user_id && $p->isPending()))
                            <!-- Tombol Batalkan -->
                            <form action="{{ route('attendance.gtk-permits.destroy', $p) }}" method="POST" onsubmit="return confirm('Batalkan pengajuan izin ini?')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-ghost-danger p-1 text-danger text-decoration-none" title="Batalkan Pengajuan">
                                    <i class="ti ti-trash fs-3"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3 py-5 px-3 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 220px; text-align: center !important;">
            <div class="avatar avatar-lg bg-light text-muted rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 58px; height: 58px; margin: 0 auto !important;">
                <i class="ti ti-file-certificate fs-1 opacity-50" style="font-size: 1.75rem !important;"></i>
            </div>
            <div class="fw-bold text-dark fs-3 text-center w-100" style="text-align: center !important;">Tidak Ada Data Izin</div>
            <div class="small text-muted mt-1 text-center w-100" style="font-size: 0.78rem; line-height: 1.4; max-width: 320px; margin: 0 auto !important; text-align: center !important;">
                {{ $currentStatus === 'pending' ? 'Semua pengajuan izin GTK telah diproses atau belum ada pengajuan baru.' : 'Tidak ditemukan data perizinan pada periode yang dipilih.' }}
            </div>
        </div>
    @endforelse

    @if($permits->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $permits->links() }}
        </div>
    @endif

    <!-- Tombol Ajukan Izin / Cuti Mandiri -->
    <div class="mt-3 pt-2">
        <a href="{{ route('attendance.gtk-permits.create') }}" 
           class="btn w-100 py-2.5 rounded-pill fw-bold text-white shadow-sm d-flex align-items-center justify-content-center gap-2"
           style="background-color: #4f46e5; font-size: 0.85rem;">
            <i class="ti ti-plus fs-2"></i>
            <span>+ Ajukan Izin / Cuti Saya</span>
        </a>
    </div>
</div>

<!-- Bottom Sheet Penolakan Izin (Pengganti prompt() browser) -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-reject-gtk-permit" style="height: auto; max-height: 80vh;">
    <div class="sheet-modal-swipe-step"></div>
    <div class="toolbar" style="background: linear-gradient(135deg, rgba(16, 45, 82, 0.92) 0%, rgba(25, 91, 158, 0.88) 100%) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-white fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-alert-triangle text-warning"></i> <span>Tolak Izin GTK</span>
            </div>
            <div class="right">
                <a class="link sheet-close text-white" href="#" style="opacity: 0.9;"><i class="ti ti-x fs-2"></i></a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3">
        <form id="formRejectBottomSheet" method="POST" action="">
            @csrf
            <div class="mb-2 text-muted small" style="font-size: 0.78rem;">
                Anda akan menolak pengajuan izin untuk <strong class="text-dark" id="rejectUserNameLabel">GTK</strong>.
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Alasan Penolakan <span class="text-danger">*</span>
                </label>
                <textarea name="approval_note" id="rejectApprovalNoteInput" rows="3" class="form-control form-control-sm" 
                          placeholder="Contoh: Jadwal pengawasan ujian tidak bisa ditinggalkan / Mohon koordinasikan jadwal mengajar..." 
                          required style="font-size: 0.85rem; border-radius: 8px;"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-2 rounded-pill sheet-close fw-semibold">
                    Batal
                </button>
                <button type="submit" class="btn btn-danger btn-sm flex-fill py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5">
                    <i class="ti ti-x fs-3"></i>
                    <span>Konfirmasi Tolak</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Custom Tab Switch */
.custom-tab-switch {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
}
.tab-switch-item {
    font-size: 0.75rem;
    transition: all 0.2s ease;
}
.tab-switch-item.active-pending {
    background: #f59e0b !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35);
}
.tab-switch-item.active-all {
    background: #195b9e !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(25, 91, 158, 0.35);
}

/* Kartu Item Izin */
.permit-item-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(16, 45, 82, 0.04);
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.permit-item-card.border-pending {
    border-left: 4.5px solid #f59e0b !important;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.08);
}
.user-avatar-initial {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.custom-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 7px;
    border-radius: 9999px;
    line-height: 1;
}
.badge-soft-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.badge-soft-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.badge-soft-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.custom-type-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    line-height: 1.2;
}
.reason-box {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}
</style>

<script>
function openRejectBottomSheet(permitId, userName) {
    document.getElementById('rejectUserNameLabel').textContent = userName;
    document.getElementById('formRejectBottomSheet').action = '/presensi/izin-cuti-gtk/' + permitId + '/tolak';
    document.getElementById('rejectApprovalNoteInput').value = '';

    if (typeof app !== 'undefined' && app.sheet) {
        app.sheet.open('#sheet-reject-gtk-permit');
    } else {
        const sheetEl = document.querySelector('#sheet-reject-gtk-permit');
        if (sheetEl) sheetEl.classList.add('modal-in');
    }
}
</script>
@endsection
