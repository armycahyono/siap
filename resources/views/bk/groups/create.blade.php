@extends('layouts.app')

@section('title', 'Buat Kelompok Binaan BK')

@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-uppercase fw-bold text-teal" style="letter-spacing: 0.05rem;">
                    BIMBINGAN &amp; KONSELING &bull; KELOMPOK BINAAN
                </div>
                <h2 class="page-title text-dark fw-bold">
                    Buat Kelompok Binaan Siswa
                </h2>
                <div class="text-muted small mt-1">
                    TP. {{ $academicYear }} &bull; Kelompokkan siswa untuk bimbingan karir kelompok, persiapan PTN, magang kerja, atau pembinaan terfokus.
                </div>
            </div>
            <div class="col-auto">
                <a href="{{ route('bk.groups.index', ['academic_year' => $academicYear]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('bk.groups.store') }}" method="POST">
        @csrf
        <input type="hidden" name="academic_year" value="{{ $academicYear }}">

        <div class="row g-3">
            <!-- Kolom Kiri: Informasi Kelompok -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-info-circle me-1 text-primary"></i> Profil Kelompok
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required fw-bold">Nama Kelompok Binaan</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Bimbingan Karir Industri Otomotif Astra" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label required fw-bold">Kategori Bimbingan</label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="karir" {{ old('category') === 'karir' ? 'selected' : '' }}>Bimbingan Karir &amp; Peminatan (Kerja/Kuliah/Wirausaha)</option>
                                <option value="akademik" {{ old('category') === 'akademik' ? 'selected' : '' }}>Bimbingan Belajar &amp; Akademik</option>
                                <option value="sosial_pribadi" {{ old('category') === 'sosial_pribadi' ? 'selected' : '' }}>Sosial &amp; Pengembangan Kepribadian</option>
                                <option value="kedisiplinan" {{ old('category') === 'kedisiplinan' ? 'selected' : '' }}>Kedisiplinan &amp; Pembinaan Karakter</option>
                            </select>
                            @error('category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Target Capaian / Sasaran</label>
                            <input type="text" name="target_goal" class="form-control" placeholder="Contoh: Siap tes rekrutmen PT Astra / Lolos SNBP 2026" value="{{ old('target_goal') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Uraian / Deskripsi Rencana Bimbingan</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Jelaskan tujuan pembentukan kelompok, jadwal tentatif, dan fokus materi bimbingan...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Pemilihan Anggota Siswa -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="card-title fw-bold text-dark m-0">
                            <i class="ti ti-users me-1 text-teal"></i> Anggota Siswa Binaan
                        </h4>
                        <span id="selectedCountBadge" class="badge bg-teal-lt fw-bold">0 Siswa Terpilih</span>
                    </div>
                    <div class="card-body">
                        <!-- Filter Pencarian Siswa -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <select id="filterClassroom" class="form-select form-select-sm">
                                    <option value="">-- Semua Kelas / Rombel --</option>
                                    @foreach($classrooms as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->department?->code ?? 'Umum' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="ti ti-search text-muted"></i></span>
                                    <input type="text" id="filterStudentName" class="form-control" placeholder="Cari nama atau NISN siswa...">
                                </div>
                            </div>
                        </div>

                        <!-- Daftar Siswa dengan Checkbox -->
                        <div class="table-responsive border rounded" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-vcenter m-0" id="tableStudents">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="w-1 text-center">
                                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                        </th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Rencana Karir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($students as $st)
                                    @php
                                        $cp = $st->careerProfiles->first();
                                        $plan = $cp?->post_graduation_plan;
                                        $planBadge = match($plan) {
                                            'bekerja' => '<span class="badge bg-teal-lt">Kerja: '.Str::limit($cp->target_industry ?? 'Industri', 15).'</span>',
                                            'melanjutkan' => '<span class="badge bg-purple-lt">Kuliah: '.Str::limit($cp->target_university ?? 'Kampus', 15).'</span>',
                                            'wirausaha' => '<span class="badge bg-orange-lt">Wirausaha</span>',
                                            default => '<span class="badge bg-secondary-lt">Belum Memutuskan</span>',
                                        };
                                    @endphp
                                    <tr class="student-row" data-class-id="{{ $st->classroom_id }}" data-name="{{ strtolower($st->name . ' ' . $st->nisn) }}">
                                        <td class="text-center">
                                            <input type="checkbox" name="student_ids[]" value="{{ $st->id }}" class="form-check-input student-checkbox">
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $st->name }}</div>
                                            <small class="text-muted">NISN: {{ $st->nisn ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $st->classroom?->name ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            {!! $planBadge !!}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="ti ti-info-circle me-1"></i> Anda dapat menambahkan atau mengeluarkan siswa dari kelompok binaan ini kapan saja setelah dibuat.
                        </small>
                    </div>
                    <div class="card-footer bg-white border-top py-3 d-flex justify-content-end gap-2">
                        <a href="{{ route('bk.groups.index', ['academic_year' => $academicYear]) }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="ti ti-check me-1"></i> Simpan Kelompok Binaan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterClass = document.getElementById('filterClassroom');
    const filterName = document.getElementById('filterStudentName');
    const rows = document.querySelectorAll('.student-row');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const selectAll = document.getElementById('selectAllCheckbox');
    const countBadge = document.getElementById('selectedCountBadge');

    function filterTable() {
        const clsVal = filterClass.value;
        const nameVal = filterName.value.toLowerCase().trim();

        rows.forEach(r => {
            const rowCls = r.getAttribute('data-class-id');
            const rowName = r.getAttribute('data-name');

            const matchCls = !clsVal || rowCls === clsVal;
            const matchName = !nameVal || rowName.includes(nameVal);

            if (matchCls && matchName) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }

    function updateCount() {
        let count = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) count++;
        });
        countBadge.textContent = count + ' Siswa Terpilih';
    }

    if (filterClass) filterClass.addEventListener('change', filterTable);
    if (filterName) filterName.addEventListener('input', filterTable);

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rows.forEach(r => {
                if (r.style.display !== 'none') {
                    const cb = r.querySelector('.student-checkbox');
                    if (cb) cb.checked = selectAll.checked;
                }
            });
            updateCount();
        });
    }
});
</script>
@endpush
@endsection
