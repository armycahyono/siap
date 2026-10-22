@extends('layouts.app')

@section('title', 'Terbitkan Surat Izin Siswa')

@section('content')
<div class="container-xl">
    <!-- Header Standar Tabler -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted text-uppercase">Piket Harian &bull; Perizinan Siswa</div>
                <h2 class="page-title text-dark fw-bold d-flex align-items-center">
                    <i class="ti ti-file-certificate text-primary me-2 fs-1"></i>
                    Penerbitan Surat Izin Siswa
                </h2>
                <div class="text-secondary mt-1">
                    Isi formulir berikut untuk mencatat izin siswa serta mengirimkan notifikasi otomatis ke Guru Mapel & Wali Kelas.
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('piket.permits.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <form action="{{ route('piket.permits.store') }}" method="POST" id="form-permit">
                    @csrf
                    <div class="card-body p-4">
                        <!-- 1. Jenis Izin -->
                        <div class="mb-4">
                            <label class="form-label required fw-bold text-dark mb-2">Pilih Jenis Perizinan</label>
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_type" value="terlambat" class="form-selectgroup-input" {{ $defaultType === 'terlambat' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-2 text-center h-100">
                                            <i class="ti ti-clock-exclamation fs-2 text-warning mb-1"></i>
                                            <span class="fw-bold small">Terlambat Masuk</span>
                                            <span class="text-muted d-none d-sm-inline" style="font-size: 0.75rem;">Izin masuk kelas</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_type" value="izin_keluar_sementara" class="form-selectgroup-input" {{ $defaultType === 'izin_keluar_sementara' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-2 text-center h-100">
                                            <i class="ti ti-door-exit fs-2 text-blue mb-1"></i>
                                            <span class="fw-bold small">Keluar Sementara</span>
                                            <span class="text-muted d-none d-sm-inline" style="font-size: 0.75rem;">Kembali ke sekolah</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_type" value="izin_pulang_cepat" class="form-selectgroup-input" {{ $defaultType === 'izin_pulang_cepat' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-2 text-center h-100">
                                            <i class="ti ti-home-check fs-2 text-danger mb-1"></i>
                                            <span class="fw-bold small">Pulang Cepat</span>
                                            <span class="text-muted d-none d-sm-inline" style="font-size: 0.75rem;">Sakit / darurat</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-selectgroup-item w-100">
                                        <input type="radio" name="permit_type" value="izin_tidak_masuk" class="form-selectgroup-input" {{ $defaultType === 'izin_tidak_masuk' ? 'checked' : '' }}>
                                        <span class="form-selectgroup-label d-flex flex-column align-items-center py-2 px-2 text-center h-100">
                                            <i class="ti ti-file-text fs-2 text-purple mb-1"></i>
                                            <span class="fw-bold small">Tidak Masuk</span>
                                            <span class="text-muted d-none d-sm-inline" style="font-size: 0.75rem;">Surat / Kabar Ortu</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Pencarian & Pemilihan Siswa (Solid Background Autocomplete) -->
                        <div class="mb-4 p-3 bg-light rounded border position-relative">
                            <label class="form-label required fw-bold text-dark">
                                <i class="ti ti-user-search text-primary me-1"></i> Data Siswa
                            </label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted border-end-0">
                                        <i class="ti ti-search"></i>
                                    </span>
                                    <input type="text" id="student_search_input" class="form-control border-start-0" placeholder="Ketik nama atau NIS siswa untuk mencari otomatis..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" id="btn_clear_search" style="display: none;" title="Hapus pencarian">
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>
                                <!-- Dropdown hasil pencarian dengan latar belakang putih solid & shadow -->
                                <div id="student_autocomplete_list" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded-3" style="z-index: 1050; display: none; max-height: 280px; overflow-y: auto; background-color: #ffffff !important;"></div>
                            </div>

                            <input type="hidden" name="student_id" id="student_id" value="">

                            <div class="row g-2 mt-2">
                                <div class="col-md-7">
                                    <label class="form-label small text-muted">Nama Siswa Terpilih / Ketik Manual:</label>
                                    <input type="text" name="student_name" id="student_name" class="form-control fw-bold" placeholder="Nama Lengkap Siswa" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small text-muted">Kelas / Rombel:</label>
                                    <select name="classroom_id" id="classroom_id" class="form-select fw-bold" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        @foreach($classrooms as $c)
                                            <option value="{{ $c->id }}" data-homeroom="{{ $c->homeroomTeacher?->name ?? 'Belum ada wali' }}">
                                                {{ $c->name }} (Wali: {{ $c->homeroomTeacher?->formatted_short_name ?? '-' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div id="homeroom_badge" class="mt-2 text-info small fw-bold" style="display: none;">
                                <i class="ti ti-info-circle me-1"></i> Notifikasi Telegram akan otomatis diteruskan ke Wali Kelas: <span id="homeroom_name">-</span>
                            </div>
                        </div>

                        <!-- 3. Detail Waktu & Jam Pelajaran -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label required" id="label_permit_date">Tanggal Izin</label>
                                <input type="date" name="permit_date" id="web_permit_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <!-- Khusus Terlambat -->
                            <div class="col-md-4" id="box_entry_time">
                                <label class="form-label required">Jam Tiba / Masuk (WIB)</label>
                                <input type="time" name="entry_time" class="form-control" value="{{ date('H:i') }}">
                            </div>
                            <div class="col-md-4" id="box_period_assigned">
                                <label class="form-label">Diizinkan Masuk Jam Ke-</label>
                                <select name="period_assigned" class="form-select">
                                    <option value="">-- Jam KBM Saat Ini --</option>
                                    @for($i = 1; $i <= 10; $i++)
                                        <option value="{{ $i }}">Jam Pelajaran ke-{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>

                            <!-- Khusus Keluar Sementara: Jam Keluar & Estimasi Kembali -->
                            <div class="col-md-4" id="box_departure_time" style="display: none;">
                                <label class="form-label required">Jam Keluar</label>
                                <input type="time" name="departure_time" class="form-control" value="{{ date('H:i') }}">
                            </div>
                            <div class="col-md-4" id="box_return_time" style="display: none;">
                                <label class="form-label">Perkiraan Jam Kembali</label>
                                <input type="time" name="return_time" class="form-control">
                            </div>

                            <!-- Khusus Pulang Cepat: Jam Pulang Saja (Tanpa Jam Kembali) -->
                            <div class="col-md-4" id="box_home_departure_time" style="display: none;">
                                <label class="form-label required">Jam Pulang Cepat (WIB)</label>
                                <input type="time" name="home_departure_time" class="form-control" value="{{ date('H:i') }}">
                            </div>
                        </div>

                        <!-- Khusus Tidak Masuk: Kategori Surat, Sampai Tanggal, Lampiran Foto Surat -->
                        <div id="box_web_absence" class="row g-3 mb-3" style="display: none;">
                            <div class="col-md-4">
                                <label class="form-label required">Kategori Surat / Izin</label>
                                <select name="permit_category" class="form-select fw-bold">
                                    <option value="sakit">Sakit (S)</option>
                                    <option value="izin" selected>Izin Keperluan (I)</option>
                                    <option value="dispen">Dispensasi / Tugas (D)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sampai Tanggal (Opsional)</label>
                                <input type="date" name="end_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Foto Surat / Bukti Ortu (Opsional)</label>
                                <input type="file" name="attachment" class="form-control" accept="image/*,application/pdf">
                            </div>
                        </div>

                        <!-- 4. Alasan & Tindakan / Sanksi Edukatif -->
                        <div class="mb-3">
                            <label class="form-label required">Alasan Keterlambatan / Izin</label>
                            <textarea name="reason" class="form-control" rows="2" placeholder="Contoh: Terlambat karena ban motor bocor di perjalanan..." required></textarea>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-reason" data-reason="Bangun kesiangan">Kesiangan</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-reason" data-reason="Kendaraan bermasalah / ban bocor">Kendaraan Macet/Bocor</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-reason" data-reason="Sakit / Kondisi tubuh kurang fit">Sakit</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-reason" data-reason="Keperluan mendesak keluarga">Keperluan Keluarga</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tindakan / Sanksi Edukatif yang Diberikan</label>
                            <input type="text" name="action_taken" id="action_taken" class="form-control" placeholder="Contoh: Membaca doa / Juz Amma di mushola, memungut 10 sampah halaman...">
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-action" data-action="Membaca Surat Pendek / Doa di Meja Piket">Hafalan Surat Pendek</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-action" data-action="Pungut sampah di halaman sekolah selama 10 menit">Operasi Bersih Lingkungan</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-action" data-action="Membaca dan merangkum buku di Perpustakaan">Literasi Perpustakaan</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2 quick-action" data-action="Teguran lisan & pengarahan kedisiplinan">Teguran & Pengarahan</button>
                            </div>
                        </div>

                        <!-- Info Telegram Bot -->
                        <div class="alert alert-info border-0 d-flex align-items-center">
                            <i class="ti ti-brand-telegram fs-1 me-3"></i>
                            <div>
                                <div class="fw-bold">Integrasi Bot Telegram Otomatis:</div>
                                <div class="small">
                                    Setelah surat izin ini disimpan, sistem akan langsung mengirim notifikasi real-time ke akun Telegram <strong>Wali Kelas</strong>, <strong>Guru Mapel</strong> yang sedang mengajar, dan <strong>Grup Kedisiplinan/BK</strong>.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-end gap-2 py-3">
                        <a href="{{ route('piket.permits.index') }}" class="btn btn-outline-secondary order-2 order-sm-1">Batal</a>
                        <button type="submit" class="btn btn-primary shadow-sm order-1 order-sm-2">
                            <i class="ti ti-check me-1"></i> Terbitkan Izin & Kirim Notifikasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Radio permit type logic
    const radioTypes = document.querySelectorAll('input[name="permit_type"]');
    const boxEntryTime = document.getElementById('box_entry_time');
    const boxPeriod = document.getElementById('box_period_assigned');
    const boxDepartureTime = document.getElementById('box_departure_time');
    const boxReturnTime = document.getElementById('box_return_time');
    const boxHomeDepartureTime = document.getElementById('box_home_departure_time');
    const boxWebAbsence = document.getElementById('box_web_absence');
    const labelPermitDate = document.getElementById('label_permit_date');

    function toggleTimeBoxes(val) {
        if (val === 'terlambat') {
            // TERLAMBAT: Jam Tiba, Masuk Jam Ke- (Tanpa jam keluar, tanpa perkiraan kembali)
            if (labelPermitDate) labelPermitDate.textContent = 'Tanggal Terlambat';
            boxEntryTime.style.display = 'block';
            boxPeriod.style.display = 'block';
            boxDepartureTime.style.display = 'none';
            boxReturnTime.style.display = 'none';
            boxHomeDepartureTime.style.display = 'none';
            boxWebAbsence.style.display = 'none';
        } else if (val === 'izin_keluar_sementara') {
            // KELUAR SEMENTARA: Jam Keluar & Perkiraan Kembali
            if (labelPermitDate) labelPermitDate.textContent = 'Tanggal Izin Keluar';
            boxEntryTime.style.display = 'none';
            boxPeriod.style.display = 'none';
            boxDepartureTime.style.display = 'block';
            boxReturnTime.style.display = 'block';
            boxHomeDepartureTime.style.display = 'none';
            boxWebAbsence.style.display = 'none';
        } else if (val === 'izin_pulang_cepat') {
            // PULANG CEPAT: Jam Pulang Saja (Tanpa Jam Keluar, Tanpa Perkiraan Kembali)
            if (labelPermitDate) labelPermitDate.textContent = 'Tanggal Pulang Cepat';
            boxEntryTime.style.display = 'none';
            boxPeriod.style.display = 'none';
            boxDepartureTime.style.display = 'none';
            boxReturnTime.style.display = 'none';
            boxHomeDepartureTime.style.display = 'block';
            boxWebAbsence.style.display = 'none';
        } else if (val === 'izin_tidak_masuk') {
            // TIDAK MASUK: Tanpa Jam Apa Pun (Hanya Kategori Surat, Sampai Tanggal, Foto Surat)
            if (labelPermitDate) labelPermitDate.textContent = 'Tanggal Mulai Tidak Masuk';
            boxEntryTime.style.display = 'none';
            boxPeriod.style.display = 'none';
            boxDepartureTime.style.display = 'none';
            boxReturnTime.style.display = 'none';
            boxHomeDepartureTime.style.display = 'none';
            boxWebAbsence.style.display = 'flex';
        }
    }

    radioTypes.forEach(r => {
        r.addEventListener('change', function () {
            toggleTimeBoxes(this.value);
        });
        if (r.checked) {
            toggleTimeBoxes(r.value);
        }
    });

    // 2. Smart Autocomplete Search for Students (Solid Background & Clear Button)
    const searchInput = document.getElementById('student_search_input');
    const autoList = document.getElementById('student_autocomplete_list');
    const btnClearSearch = document.getElementById('btn_clear_search');
    const studentIdField = document.getElementById('student_id');
    const studentNameField = document.getElementById('student_name');
    const classroomSelect = document.getElementById('classroom_id');
    const homeroomBadge = document.getElementById('homeroom_badge');
    const homeroomNameSpan = document.getElementById('homeroom_name');

    let debounceTimer;

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function () {
            searchInput.value = '';
            studentIdField.value = '';
            studentNameField.value = '';
            autoList.style.display = 'none';
            btnClearSearch.style.display = 'none';
            searchInput.focus();
        });
    }

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (btnClearSearch) {
            btnClearSearch.style.display = query.length > 0 ? 'block' : 'none';
        }

        if (query.length < 2) {
            autoList.style.display = 'none';
            autoList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`{{ route('piket.students.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    autoList.innerHTML = '';
                    if (data.length === 0) {
                        autoList.innerHTML = `<div class="list-group-item bg-white text-muted small p-3 text-center border-0"><i class="ti ti-info-circle me-1"></i> Tidak ada siswa ditemukan. Anda dapat mengetik nama manual di bawah.</div>`;
                        autoList.style.display = 'block';
                        return;
                    }

                    data.forEach(item => {
                        const div = document.createElement('a');
                        div.href = 'javascript:void(0)';
                        div.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 bg-white border-bottom';
                        div.innerHTML = `
                            <div>
                                <div class="fw-bold text-dark">${item.name}</div>
                                <div class="text-muted small">NIS: ${item.nis || '-'} &bull; NISN: ${item.nisn || '-'}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-blue-lt">${item.classroom_name}</span>
                                <div class="text-secondary small" style="font-size: 0.72rem;">Wali: ${item.homeroom_teacher}</div>
                            </div>
                        `;
                        div.addEventListener('click', function () {
                            studentIdField.value = item.id;
                            studentNameField.value = item.name;
                            if (item.classroom_id) {
                                classroomSelect.value = item.classroom_id;
                                homeroomNameSpan.textContent = item.homeroom_teacher;
                                homeroomBadge.style.display = 'block';
                            }
                            autoList.style.display = 'none';
                            searchInput.value = item.name;
                            if (btnClearSearch) btnClearSearch.style.display = 'block';
                        });
                        autoList.appendChild(div);
                    });
                    autoList.style.display = 'block';
                })
                .catch(() => {
                    autoList.style.display = 'none';
                });
        }, 250);
    });

    // Close autocomplete on click outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !autoList.contains(e.target)) {
            autoList.style.display = 'none';
        }
    });

    classroomSelect.addEventListener('change', function () {
        const selectedOpt = this.options[this.selectedIndex];
        const homeroom = selectedOpt.getAttribute('data-homeroom');
        if (homeroom) {
            homeroomNameSpan.textContent = homeroom;
            homeroomBadge.style.display = 'block';
        } else {
            homeroomBadge.style.display = 'none';
        }
    });

    // Quick Reason buttons
    document.querySelectorAll('.quick-reason').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelector('textarea[name="reason"]').value = this.getAttribute('data-reason');
        });
    });

    // Quick Action buttons
    document.querySelectorAll('.quick-action').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('action_taken').value = this.getAttribute('data-action');
        });
    });

    // Cegah submit berulang (double submit prevention)
    const permitForm = document.querySelector('form');
    if (permitForm) {
        permitForm.addEventListener('submit', function () {
            const btn = permitForm.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Menerbitkan Izin...';
            }
        });
    }
});
</script>
@endpush
