@extends('layouts.app')

@section('title', 'Catat Sesi Layanan BK')

@section('content')
<div class="container-xl">
    <div class="mb-3">
        <a href="{{ $incident ? route('bk.referrals.show', $incident) : route('bk.services.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h4 class="alert-title"><i class="ti ti-alert-circle me-1"></i> Terjadi kesalahan input:</h4>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form action="{{ route('bk.services.store') }}" method="POST">
                @csrf
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary-lt">
                        <h3 class="card-title fw-bold text-primary mb-0">
                            <i class="ti ti-notes me-2"></i> Formulir Pencatatan Sesi Layanan Bimbingan &amp; Konseling
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Referensi Rujukan Piket (Jika Ada) -->
                        @if($incident)
                            <input type="hidden" name="piket_incident_id" value="{{ $incident->id }}">
                            <div class="alert alert-info d-flex align-items-center mb-4">
                                <i class="ti ti-link fs-2 me-3"></i>
                                <div>
                                    <div class="fw-bold">Terkait Kasus Meja Piket:</div>
                                    <div class="small">
                                        Tanggal: {{ $incident->incident_date->format('d/m/Y') }} &bull;
                                        Kategori: {{ $incident->category_label }} &bull;
                                        Uraian: {{ Str::limit($incident->description, 100) }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Pilihan Moda Layanan BK -->
                        <div class="mb-4 pb-3 border-bottom">
                            <label class="form-label required fw-bold mb-2">Moda Layanan Bimbingan</label>
                            @php
                                $defaultMode = request('service_mode', request('bk_group_id') ? 'kelompok' : (old('service_mode', 'individual')));
                            @endphp
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="service_mode" value="individual" class="form-selectgroup-input" {{ $defaultMode === 'individual' ? 'checked' : '' }} onchange="toggleServiceMode('individual')">
                                        <span class="form-selectgroup-label d-flex align-items-center p-2">
                                            <i class="ti ti-user fs-2 text-primary me-2"></i>
                                            <div class="text-start">
                                                <div class="fw-bold text-dark">Individual</div>
                                                <small class="text-muted">1 Siswa Konseling</small>
                                            </div>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="service_mode" value="kelompok" class="form-selectgroup-input" {{ $defaultMode === 'kelompok' ? 'checked' : '' }} onchange="toggleServiceMode('kelompok')">
                                        <span class="form-selectgroup-label d-flex align-items-center p-2">
                                            <i class="ti ti-users-group fs-2 text-teal me-2"></i>
                                            <div class="text-start">
                                                <div class="fw-bold text-dark">Kelompok Binaan</div>
                                                <small class="text-muted">Kelompok Karir / Binaan</small>
                                            </div>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="service_mode" value="klasikal" class="form-selectgroup-input" {{ $defaultMode === 'klasikal' ? 'checked' : '' }} onchange="toggleServiceMode('klasikal')">
                                        <span class="form-selectgroup-label d-flex align-items-center p-2">
                                            <i class="ti ti-school fs-2 text-purple me-2"></i>
                                            <div class="text-start">
                                                <div class="fw-bold text-dark">Klasikal (Kelas)</div>
                                                <small class="text-muted">1 Rombel Penuh</small>
                                            </div>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Moda 1: Siswa Individual -->
                            <div class="col-md-8" id="modeIndividualBox">
                                <label class="form-label required fw-bold">Siswa yang Dibimbing</label>
                                @if($selectedStudent)
                                    @php
                                        $selectedClass = $selectedStudent->classroomForAcademicYear($academicYear) ?? $selectedStudent->classroom;
                                    @endphp
                                    <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
                                    <div class="form-control bg-light d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark">{{ $selectedStudent->name }}</span>
                                        <span class="badge bg-blue-lt">{{ $selectedClass?->name ?? 'Kelas -' }} &bull; NISN: {{ $selectedStudent->nisn ?? '-' }}</span>
                                    </div>
                                @else
                                    <select name="student_id" id="student_select" class="form-select">
                                        <option value="">-- Cari / Pilih Siswa --</option>
                                        @foreach($students as $st)
                                            @php
                                                $stClass = $st->classroomForAcademicYear($academicYear) ?? $st->classroom;
                                            @endphp
                                            <option value="{{ $st->id }}" {{ old('student_id') == $st->id ? 'selected' : '' }}>
                                                {{ $st->name }} ({{ $stClass?->name ?? 'Kelas -' }} &bull; NISN: {{ $st->nisn ?? '-' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-muted small">Pilih siswa aktif untuk sesi konseling individual ini.</span>
                                @endif
                            </div>

                            <!-- Moda 2: Kelompok Binaan -->
                            <div class="col-md-8 d-none" id="modeKelompokBox">
                                <label class="form-label required fw-bold">Pilih Kelompok Binaan</label>
                                @php
                                    $reqGroupId = request('bk_group_id', old('bk_group_id'));
                                @endphp
                                <select name="bk_group_id" id="group_select" class="form-select">
                                    <option value="">-- Pilih Kelompok Binaan --</option>
                                    @foreach($bkGroups as $grp)
                                        <option value="{{ $grp->id }}" {{ $reqGroupId == $grp->id ? 'selected' : '' }}>
                                            {{ $grp->name }} ({{ $grp->category_label }} &bull; {{ $grp->students_count }} Siswa)
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-muted small">Materi bimbingan akan tercatat ke buku pribadi semua anggota kelompok ini.</span>
                            </div>

                            <!-- Moda 3: Bimbingan Klasikal (1 Kelas) -->
                            <div class="col-md-8 d-none" id="modeKlasikalBox">
                                <label class="form-label required fw-bold">Pilih Kelas / Rombel</label>
                                <select name="classroom_id" id="classroom_select" class="form-select">
                                    <option value="">-- Pilih Rombel --</option>
                                    @foreach($classrooms as $cls)
                                        <option value="{{ $cls->id }}" {{ old('classroom_id') == $cls->id ? 'selected' : '' }}>
                                            {{ $cls->name }} ({{ $cls->department?->code ?? 'Umum' }})
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-muted small">Bimbingan klasikal akan otomatis terhubung ke seluruh siswa di kelas ini.</span>
                            </div>

                            <!-- Tahun Ajaran -->
                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tahun Ajaran</label>
                                <input type="text" name="academic_year" class="form-control" value="{{ old('academic_year', $academicYear) }}" readonly required>
                            </div>

                            <!-- Tanggal Layanan -->
                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tanggal Layanan</label>
                                <input type="date" name="service_date" class="form-control" value="{{ old('service_date', now()->toDateString()) }}" required>
                            </div>

                            <!-- Bidang Layanan BK -->
                            <div class="col-md-8">
                                <label class="form-label required fw-bold">Bidang Layanan Bimbingan</label>
                                <div class="row g-2">
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="pribadi" class="form-selectgroup-input" {{ old('service_type', 'pribadi') === 'pribadi' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Pribadi</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="sosial" class="form-selectgroup-input" {{ old('service_type') === 'sosial' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Sosial</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="belajar" class="form-selectgroup-input" {{ old('service_type') === 'belajar' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Belajar</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="service_type" value="karier" class="form-selectgroup-input" {{ old('service_type') === 'karier' ? 'checked' : '' }}>
                                            <span class="form-selectgroup-label text-center p-2">Karier</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Ringkasan Layanan -->
                            <div class="col-12">
                                <label class="form-label required fw-bold">Ringkasan Masalah / Uraian Layanan</label>
                                <textarea name="summary" rows="4" class="form-control" placeholder="Tuliskan latar belakang masalah, keluhan siswa, atau materi bimbingan yang dibahas..." required>{{ old('summary') }}</textarea>
                            </div>

                            <!-- Hasil / Kesimpulan Konseling -->
                            <div class="col-12">
                                <label class="form-label required fw-bold">Hasil / Kesimpulan Konseling</label>
                                <textarea name="result" rows="3" class="form-control" placeholder="Tuliskan pemahaman baru siswa, kesepakatan, komitmen perubahan, atau hasil konseling..." required>{{ old('result') }}</textarea>
                            </div>

                            <!-- Rencana Tindak Lanjut -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Rencana Tindak Lanjut (RTL)</label>
                                <textarea name="follow_up_plan" rows="2" class="form-control" placeholder="Contoh: Pemantauan kehadiran pekan depan, koordinasi dengan orang tua, dsb...">{{ old('follow_up_plan') }}</textarea>
                            </div>
                        </div>

                        <!-- Bagian Catatan Rahasia Konseling (Confidential Notes) -->
                        @if($canAccessConfidential)
                            <div class="mt-4 pt-3 border-top">
                                <div class="card bg-danger-lt border-danger">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ti ti-lock fs-2 text-danger me-2"></i>
                                            <h4 class="card-title text-danger mb-0 fw-bold">Catatan Konseling Rahasia (Confidential)</h4>
                                        </div>
                                        <p class="text-danger-emphasis small mb-3">
                                            Informasi pada bagian ini <strong>bersifat sangat rahasia</strong> dan hanya dapat diakses oleh Guru BK yang sah serta Super Admin. Catatan ini <em>tidak akan pernah ditampilkan</em> pada rekapitulasi umum, halaman wali kelas, piket, atau notifikasi.
                                        </p>
                                        <textarea name="confidential_notes" rows="3" class="form-control bg-white" placeholder="Tuliskan observasi sensitif, fakta tersembunyi, atau catatan psikologis pribadi yang wajib dirahasiakan...">{{ old('confidential_notes') }}</textarea>
                                        <span class="text-muted small mt-1 d-block">Kosongkan jika tidak ada catatan khusus yang perlu dirahasiakan.</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center bg-light">
                        <a href="{{ $incident ? route('bk.referrals.show', $incident) : route('bk.services.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Sesi Layanan BK
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleServiceMode(mode) {
    const indBox = document.getElementById('modeIndividualBox');
    const grpBox = document.getElementById('modeKelompokBox');
    const clsBox = document.getElementById('modeKlasikalBox');

    const stSelect = document.getElementById('student_select');
    const grpSelect = document.getElementById('group_select');
    const clsSelect = document.getElementById('classroom_select');

    if (mode === 'kelompok') {
        indBox.classList.add('d-none');
        grpBox.classList.remove('d-none');
        clsBox.classList.add('d-none');
        if (stSelect) stSelect.removeAttribute('required');
        if (grpSelect) grpSelect.setAttribute('required', 'required');
        if (clsSelect) clsSelect.removeAttribute('required');
    } else if (mode === 'klasikal') {
        indBox.classList.add('d-none');
        grpBox.classList.add('d-none');
        clsBox.classList.remove('d-none');
        if (stSelect) stSelect.removeAttribute('required');
        if (grpSelect) grpSelect.removeAttribute('required');
        if (clsSelect) clsSelect.setAttribute('required', 'required');
    } else {
        indBox.classList.remove('d-none');
        grpBox.classList.add('d-none');
        clsBox.classList.add('d-none');
        if (stSelect) stSelect.setAttribute('required', 'required');
        if (grpSelect) grpSelect.removeAttribute('required');
        if (clsSelect) clsSelect.removeAttribute('required');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkedMode = document.querySelector('input[name="service_mode"]:checked');
    if (checkedMode) {
        toggleServiceMode(checkedMode.value);
    }
});
</script>
@endpush
@endsection
