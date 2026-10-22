@extends('layouts.mobile')

@section('title', 'Catat Izin Siswa Perwalian')
@section('page_title', 'Catat Izin Siswa')
@section('page_subtitle', 'Kelas ' . $selectedClassroom->name)
@section('back_url', route('attendance.homeroom-permits.index', ['classroom_id' => $selectedClassroom->id]))

@section('content')
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body p-3">
        <form action="{{ route('attendance.homeroom-permits.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Pilih Kelas (Jika mengampu > 1 kelas) -->
            @if($myClassrooms->count() > 1)
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Rombel / Kelas</label>
                    <select name="classroom_id" class="form-select form-select-sm" onchange="window.location.href='{{ route('attendance.homeroom-permits.create') }}?classroom_id='+this.value">
                        @foreach($myClassrooms as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassroom->id == $c->id ? 'selected' : '' }}>Kelas {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="classroom_id" value="{{ $selectedClassroom->id }}">
            @endif

            <!-- Pilih Siswa -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Nama Siswa <span class="text-danger">*</span>
                </label>
                <select name="student_id" class="form-select form-select-sm" required style="font-size: 0.85rem; padding: 8px 12px;">
                    <option value="">-- Pilih Siswa Kelas {{ $selectedClassroom->name }} --</option>
                    @foreach($students as $st)
                        <option value="{{ $st->id }}" {{ old('student_id') == $st->id ? 'selected' : '' }}>
                            {{ $st->name }} ({{ $st->nisn ?? $st->nis ?? '-' }})
                        </option>
                    @endforeach
                </select>
                @error('student_id')
                    <div class="text-danger small mt-0.5">{{ $message }}</div>
                @enderror
            </div>

            <!-- Kategori Izin (Pill Buttons) -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Kategori Izin <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2">
                    <label class="flex-fill text-center">
                        <input type="radio" name="permit_category" value="sakit" class="d-none permit-radio" {{ old('permit_category', 'sakit') == 'sakit' ? 'checked' : '' }}>
                        <span class="btn-category-pill pill-sakit">Sakit</span>
                    </label>
                    <label class="flex-fill text-center">
                        <input type="radio" name="permit_category" value="izin" class="d-none permit-radio" {{ old('permit_category') == 'izin' ? 'checked' : '' }}>
                        <span class="btn-category-pill pill-izin">Izin</span>
                    </label>
                    <label class="flex-fill text-center">
                        <input type="radio" name="permit_category" value="dispen" class="d-none permit-radio" {{ old('permit_category') == 'dispen' ? 'checked' : '' }}>
                        <span class="btn-category-pill pill-dispen">Dispen</span>
                    </label>
                </div>
                @error('permit_category')
                    <div class="text-danger small mt-0.5">{{ $message }}</div>
                @enderror
            </div>

            <!-- Tanggal Mulai & Sampai -->
            <div class="mobile-grid-2 mb-3">
                <div>
                    <label class="form-label small fw-bold mb-1">Dari Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="permit_date" class="form-control form-control-sm" value="{{ old('permit_date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Sampai (Opsional)</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ old('end_date') }}" placeholder="Jika > 1 hari">
                </div>
            </div>

            <!-- Alasan / Keterangan -->
            <div class="mb-3">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Alasan / Keterangan Surat / Chat WA <span class="text-danger">*</span>
                </label>
                <textarea name="reason" rows="3" class="form-control form-control-sm" placeholder="Contoh: Sakit demam tinggi sejak kemarin, istirahat dokter 2 hari..." required style="font-size: 0.85rem;">{{ old('reason') }}</textarea>
                @error('reason')
                    <div class="text-danger small mt-0.5">{{ $message }}</div>
                @enderror
            </div>

            <!-- Lampiran Foto / Surat -->
            <div class="mb-4">
                <label class="form-label small fw-bold mb-1 text-dark">
                    Foto Surat Dokter / Bukti Pesan (Opsional)
                </label>
                <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*" capture="environment">
                <small class="text-muted" style="font-size: 0.72rem;">Format: JPG/PNG, maks 3MB.</small>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan &amp; Terbitkan Izin Siswa</span>
            </button>
        </form>
    </div>
</div>

<style>
    .btn-category-pill {
        display: block;
        padding: 8px 0;
        font-size: 0.85rem;
        font-weight: 700;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        transition: all 0.15s ease;
        user-select: none;
    }
    .permit-radio:checked + .pill-sakit {
        background: #d97706 !important;
        border-color: #d97706 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.35);
    }
    .permit-radio:checked + .pill-izin {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35);
    }
    .permit-radio:checked + .pill-dispen {
        background: #7c3aed !important;
        border-color: #7c3aed !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(124, 58, 237, 0.35);
    }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Menyimpan...';
            }
        });
    }
});
</script>
@endpush
@endsection
