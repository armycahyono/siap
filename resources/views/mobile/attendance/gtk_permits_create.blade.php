@extends('layouts.mobile')

@section('title', 'Formulir Izin & Cuti GTK — APK SIAP')
@section('page_title', 'Formulir Izin / Cuti')
@section('page_subtitle', 'Pengajuan Perizinan GTK Mandiri')
@section('back_url', route('dashboard'))

@section('content')
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <!-- Informasi Ringkas Alur Persetujuan -->
        <div class="alert alert-info py-2 px-3 mb-3 small rounded-3 d-flex align-items-start gap-2 border-0" style="background: #e0f2fe; color: #0369a1;">
            <i class="ti ti-info-circle fs-3 text-primary flex-shrink-0 mt-0.5"></i>
            <div style="font-size: 0.76rem; line-height: 1.35;">
                Pengajuan izin mandiri akan dikirimkan ke <strong>Kepala Sekolah</strong> untuk diverifikasi. Setelah disetujui, kehadiran Anda otomatis tercatat sah.
            </div>
        </div>

        <form action="{{ route('attendance.gtk-permits.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- GTK Pengaju (Hanya Kepala Sekolah, Seluruh Wakil Kepala Sekolah, dan Super Admin yang bisa memilihkan untuk orang lain) -->
            @if(auth()->user()->canSubmitPermitForOthers())
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-dark">
                        Pilih Guru / Tenaga Kependidikan <span class="text-danger">*</span>
                    </label>
                    <select name="user_id" class="form-select form-select-sm" required style="font-size: 0.85rem; padding: 8px 12px;">
                        @foreach($personnel as $p)
                            <option value="{{ $p->id }}" {{ auth()->id() == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->isStaff() ? 'Staf' : 'Guru' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <div class="p-2 rounded-2 mb-3 bg-light border d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-primary-lt text-primary rounded-circle" style="width: 32px; height: 32px;">
                        <i class="ti ti-user fs-3"></i>
                    </span>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-dark fs-4 lh-sm text-truncate">{{ auth()->user()->name }}</div>
                        <div class="text-muted small" style="font-size: 0.7rem;">
                            {{ auth()->user()->position ?: (auth()->user()->role?->name ?? 'Tenaga Pendidik') }}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Kategori Perizinan (Tepat 2 Baris, 2 Kolom) -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1.5 text-dark">
                    Kategori Perizinan <span class="text-danger">*</span>
                </label>
                <div class="permit-category-grid">
                    <!-- 1. Sakit -->
                    <label class="permit-pill-label m-0">
                        <input type="radio" name="permission_type" value="sakit" class="d-none permit-type-radio" checked>
                        <span class="btn-permit-pill pill-sakit d-flex align-items-center justify-content-center gap-1.5 py-2 px-2 rounded-3 border">
                            <i class="ti ti-first-aid-kit fs-3"></i>
                            <span class="fw-bold" style="font-size: 0.82rem;">Sakit</span>
                        </span>
                    </label>

                    <!-- 2. Izin Pribadi -->
                    <label class="permit-pill-label m-0">
                        <input type="radio" name="permission_type" value="izin" class="d-none permit-type-radio">
                        <span class="btn-permit-pill pill-izin d-flex align-items-center justify-content-center gap-1.5 py-2 px-2 rounded-3 border">
                            <i class="ti ti-file-text fs-3"></i>
                            <span class="fw-bold" style="font-size: 0.82rem;">Izin Pribadi</span>
                        </span>
                    </label>

                    <!-- 3. Cuti Resmi -->
                    <label class="permit-pill-label m-0">
                        <input type="radio" name="permission_type" value="cuti" class="d-none permit-type-radio">
                        <span class="btn-permit-pill pill-cuti d-flex align-items-center justify-content-center gap-1.5 py-2 px-2 rounded-3 border">
                            <i class="ti ti-calendar-event fs-3"></i>
                            <span class="fw-bold" style="font-size: 0.82rem;">Cuti Resmi</span>
                        </span>
                    </label>

                    <!-- 4. Dinas Luar -->
                    <label class="permit-pill-label m-0">
                        <input type="radio" name="permission_type" value="dinas_luar" class="d-none permit-type-radio">
                        <span class="btn-permit-pill pill-dinas d-flex align-items-center justify-content-center gap-1.5 py-2 px-2 rounded-3 border">
                            <i class="ti ti-briefcase fs-3"></i>
                            <span class="fw-bold" style="font-size: 0.82rem;">Dinas Luar</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Rentang Tanggal Izin (1 Baris) -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small fw-bold mb-0 text-dark">
                        Rentang Hari Izin <span class="text-danger">*</span>
                    </label>
                    <span class="badge bg-primary text-white" id="badgeCalcDuration" style="font-size: 0.68rem; padding: 2px 7px;">1 Hari</span>
                </div>
                <div class="d-flex align-items-center gap-1.5">
                    <div class="flex-fill">
                        <input type="date" name="start_date" id="form_start_date" class="form-control form-control-sm text-center" value="{{ now()->toDateString() }}" required onchange="calcDuration()" style="font-size: 0.82rem; padding: 6px 8px;">
                    </div>
                    <span class="text-muted small fw-semibold flex-shrink-0" style="font-size: 0.72rem;">s/d</span>
                    <div class="flex-fill">
                        <input type="date" name="end_date" id="form_end_date" class="form-control form-control-sm text-center" placeholder="Sampai" onchange="calcDuration()" style="font-size: 0.82rem; padding: 6px 8px;">
                    </div>
                </div>
            </div>

            <!-- Alasan / Keperluan -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Alasan / Keperluan Izin <span class="text-danger">*</span>
                </label>
                <textarea name="reason" rows="3" class="form-control form-control-sm" placeholder="Contoh: Sakit demam dan rawat jalan / Menghadiri kegiatan kedinasan / Urusan keluarga penting..." required style="font-size: 0.85rem; border-radius: 8px;"></textarea>
                @error('reason')
                    <div class="text-danger small mt-0.5">{{ $message }}</div>
                @enderror
            </div>

            <!-- Catatan Tambahan -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Catatan Titipan Tugas / Pembelajaran (Opsional)
                </label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Modul dan lembar kerja telah diserahkan ke guru piket" style="font-size: 0.85rem; border-radius: 8px;">
            </div>

            <!-- Bukti Dokumen / Surat Foto -->
            <div class="mb-4">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Dokumen / Foto Surat Bukti (Opsional)
                </label>
                <input type="file" name="document" class="form-control form-control-sm" accept="image/*,.pdf" capture="environment" style="font-size: 0.82rem; border-radius: 8px;">
                <div class="text-muted mt-1" style="font-size: 0.7rem;">
                    Format JPG, PNG, atau PDF (Surat Keterangan Dokter, Undangan Dinas, dll.)
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm py-2 px-3 rounded-pill fw-semibold" style="font-size: 0.85rem;">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary btn-sm flex-fill py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.9rem;">
                    <i class="ti ti-send fs-3"></i>
                    <span>Kirim Pengajuan Izin</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.permit-category-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
}
.permit-pill-label {
    display: block !important;
    width: 100% !important;
    cursor: pointer;
}
.btn-permit-pill {
    cursor: pointer;
    background: #ffffff;
    color: #475569;
    border-color: #cbd5e1 !important;
    transition: all 0.2s ease;
    width: 100% !important;
    box-sizing: border-box !important;
}
.permit-type-radio:checked + .pill-sakit {
    background: #fef3c7 !important;
    border-color: #d97706 !important;
    color: #92400e !important;
}
.permit-type-radio:checked + .pill-izin {
    background: #e0f2fe !important;
    border-color: #0284c7 !important;
    color: #0369a1 !important;
}
.permit-type-radio:checked + .pill-cuti {
    background: #f3e8ff !important;
    border-color: #9333ea !important;
    color: #6b21a8 !important;
}
.permit-type-radio:checked + .pill-dinas {
    background: #dbeafe !important;
    border-color: #2563eb !important;
    color: #1e40af !important;
}
</style>

<script>
function calcDuration() {
    const sVal = document.getElementById('form_start_date').value;
    const eVal = document.getElementById('form_end_date').value || sVal;
    const badge = document.getElementById('badgeCalcDuration');
    if (sVal && eVal) {
        const d1 = new Date(sVal);
        const d2 = new Date(eVal);
        const diff = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
        if (diff > 0) {
            badge.textContent = diff + ' Hari';
            badge.className = 'badge bg-primary text-white';
        } else {
            badge.textContent = 'Tanggal Tidak Valid';
            badge.className = 'badge bg-danger text-white';
        }
    }
}
</script>
@endsection
