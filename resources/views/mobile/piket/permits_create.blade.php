@extends('layouts.mobile')

@section('title', 'Terbitkan Surat Izin Siswa — APK SIAP')
@section('page_title', 'Terbitkan Izin Siswa')
@section('page_subtitle', 'Layanan Piket Kedisiplinan')
@section('back_url', route('piket.dashboard'))

@section('content')
<form action="{{ route('piket.permits.store') }}" method="POST" id="form-mobile-permit" enctype="multipart/form-data">
    @csrf

    <!-- Pilihan Jenis Izin (Grid 2x2) -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <label class="form-label small fw-bold text-dark mb-1.5">
                <i class="ti ti-category text-primary me-1"></i> Jenis Perizinan
            </label>
            <div class="mobile-grid-2 mb-0">
                <div id="card_type_terlambat" 
                     class="card border p-2 text-center cursor-pointer m-0 permit-type-card {{ $defaultType === 'terlambat' ? 'border-warning bg-warning-lt' : 'bg-white' }}" 
                     onclick="selectPermitType('terlambat')"
                     style="cursor: pointer;">
                    <input type="radio" name="permit_type" value="terlambat" class="d-none permit-type-radio" {{ $defaultType === 'terlambat' ? 'checked' : '' }}>
                    <i class="ti ti-clock-exclamation fs-2 text-warning d-block mb-1"></i>
                    <div class="fw-bold small lh-1">Terlambat</div>
                    <span class="text-muted" style="font-size: 0.65rem;">Masuk kelas</span>
                </div>

                <div id="card_type_izin_keluar_sementara" 
                     class="card border p-2 text-center cursor-pointer m-0 permit-type-card {{ $defaultType === 'izin_keluar_sementara' ? 'border-primary bg-blue-lt' : 'bg-white' }}" 
                     onclick="selectPermitType('izin_keluar_sementara')"
                     style="cursor: pointer;">
                    <input type="radio" name="permit_type" value="izin_keluar_sementara" class="d-none permit-type-radio" {{ $defaultType === 'izin_keluar_sementara' ? 'checked' : '' }}>
                    <i class="ti ti-door-exit fs-2 text-primary d-block mb-1"></i>
                    <div class="fw-bold small lh-1">Keluar</div>
                    <span class="text-muted" style="font-size: 0.65rem;">Sementara</span>
                </div>

                <div id="card_type_izin_pulang_cepat" 
                     class="card border p-2 text-center cursor-pointer m-0 permit-type-card {{ $defaultType === 'izin_pulang_cepat' ? 'border-danger bg-danger-lt' : 'bg-white' }}" 
                     onclick="selectPermitType('izin_pulang_cepat')"
                     style="cursor: pointer;">
                    <input type="radio" name="permit_type" value="izin_pulang_cepat" class="d-none permit-type-radio" {{ $defaultType === 'izin_pulang_cepat' ? 'checked' : '' }}>
                    <i class="ti ti-home-check fs-2 text-danger d-block mb-1"></i>
                    <div class="fw-bold small lh-1">Pulang</div>
                    <span class="text-muted" style="font-size: 0.65rem;">Cepat/sakit</span>
                </div>

                <div id="card_type_izin_tidak_masuk" 
                     class="card border p-2 text-center cursor-pointer m-0 permit-type-card {{ $defaultType === 'izin_tidak_masuk' ? 'border-purple bg-purple-lt' : 'bg-white' }}" 
                     onclick="selectPermitType('izin_tidak_masuk')"
                     style="cursor: pointer;">
                    <input type="radio" name="permit_type" value="izin_tidak_masuk" class="d-none permit-type-radio" {{ $defaultType === 'izin_tidak_masuk' ? 'checked' : '' }}>
                    <i class="ti ti-file-text fs-2 text-purple d-block mb-1"></i>
                    <div class="fw-bold small lh-1">Tidak Masuk</div>
                    <span class="text-muted" style="font-size: 0.65rem;">Surat/Kabar</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Identitas Siswa & Kelas -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <label class="form-label small fw-bold text-dark mb-1">
                <i class="ti ti-user-search text-primary me-1"></i> Identitas Siswa <span class="text-danger">*</span>
            </label>
            <div class="position-relative mb-2">
                <div class="d-flex align-items-center gap-1.5 w-100 m-0">
                    <!-- Kolom Teks Pencarian Pintar Siswa -->
                    <input type="text" 
                           id="m_student_search" 
                           class="form-control form-control-sm flex-fill" 
                           style="font-size: 0.8rem; height: 36px; border-radius: 8px;" 
                           placeholder="Ketik nama atau NIS siswa..." 
                           autocomplete="off">

                    <!-- Tombol Cari (Kotak Biru Proporsional) -->
                    <button type="button" 
                            id="m_btn_do_search" 
                            class="btn btn-primary d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" 
                            style="height: 36px; width: 42px; border-radius: 8px; padding: 0;" 
                            title="Cari Siswa">
                        <i class="ti ti-search fs-2"></i>
                    </button>

                    <!-- Tombol Reset (Border) -->
                    <button type="button" 
                            id="m_btn_clear_search" 
                            class="btn btn-outline-secondary d-flex align-items-center justify-content-center flex-shrink-0" 
                            style="height: 36px; width: 36px; border-radius: 8px; padding: 0; display: none;" 
                            title="Hapus / Reset">
                        <i class="ti ti-x fs-2"></i>
                    </button>
                </div>

                <!-- Dropdown Hasil Pencarian Pintar -->
                <div id="m_student_autocomplete" 
                     class="position-absolute w-100 shadow-sm mt-1 bg-white border rounded-3 p-0" 
                     style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;">
                </div>
            </div>

            <input type="hidden" name="student_id" id="m_student_id" value="">

            <div class="mb-2">
                <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Nama Siswa Terpilih / Ketik Manual:</label>
                <input type="text" name="student_name" id="m_student_name" class="form-control form-control-sm fw-bold" placeholder="Nama Lengkap Siswa" required>
            </div>

            <div class="mb-1">
                <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Kelas / Rombel:</label>
                <select name="classroom_id" id="m_classroom_id" class="form-select form-select-sm fw-bold" required>
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" data-homeroom="{{ $c->homeroomTeacher?->name ?? 'Belum ada wali' }}">
                            {{ $c->name }} (Wali: {{ $c->homeroomTeacher?->formatted_short_name ?? '-' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div id="m_homeroom_info" class="text-info small fw-bold mt-1" style="display: none; font-size: 0.7rem;">
                <i class="ti ti-info-circle me-1"></i> Notifikasi otomatis ke Wali: <span id="m_homeroom_name">-</span>
            </div>
        </div>
    </div>

    <!-- Waktu & Pengaturan Perizinan -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <!-- 1. Tanggal Izin / Mulai (Selalu Ada) -->
            <div class="mb-2" id="m_box_date">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">
                    <span id="m_label_date">Tanggal Izin</span> <span class="text-danger">*</span>
                </label>
                <input type="date" name="permit_date" id="m_permit_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
            </div>

            <!-- 2. Khusus Terlambat: Jam Tiba & Diizinkan Masuk Jam Ke- (Tanpa jam keluar, tanpa jam kembali) -->
            <div id="m_box_late_wrapper" style="display: block;">
                <div class="mobile-grid-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Jam Tiba (WIB) <span class="text-danger">*</span></label>
                        <input type="time" name="entry_time" id="m_entry_time" class="form-control form-control-sm" value="{{ date('H:i') }}">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Masuk Jam Ke-</label>
                        <select name="period_assigned" id="m_period_assigned" class="form-select form-select-sm">
                            <option value="">-- Jam KBM --</option>
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">Jam ke-{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Khusus Keluar Sementara: Jam Keluar & Perkiraan Kembali -->
            <div id="m_box_leave_times" style="display: none;">
                <div class="mobile-grid-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Jam Keluar (WIB) <span class="text-danger">*</span></label>
                        <input type="time" name="departure_time" id="m_leave_departure" class="form-control form-control-sm" value="{{ date('H:i') }}">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Perkiraan Kembali (WIB)</label>
                        <input type="time" name="return_time" id="m_leave_return" class="form-control form-control-sm">
                    </div>
                </div>
            </div>

            <!-- 4. Khusus Pulang Cepat: Jam Pulang Saja (Tanpa Jam Keluar, Tanpa Jam Kembali) -->
            <div id="m_box_early_home" style="display: none;" class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Jam Pulang Cepat (WIB) <span class="text-danger">*</span></label>
                <input type="time" name="home_departure_time" id="m_home_departure" class="form-control form-control-sm" value="{{ date('H:i') }}">
            </div>

            <!-- 5. Khusus Tidak Masuk: Tanpa jam apa pun (Hanya kategori surat, sampai tanggal, dan lampiran) -->
            <div id="m_box_absence" style="display: none;">
                <div class="mobile-grid-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Kategori Surat <span class="text-danger">*</span></label>
                        <select name="permit_category" id="m_permit_category" class="form-select form-select-sm fw-bold">
                            <option value="sakit">Sakit (S)</option>
                            <option value="izin" selected>Izin Keperluan (I)</option>
                            <option value="dispen">Dispensasi / Tugas (D)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Sampai Tanggal (Opsional)</label>
                        <input type="date" name="end_date" id="m_end_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" title="Jika izin lebih dari 1 hari">
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">
                        <i class="ti ti-camera me-1 text-purple"></i> Foto Surat / Bukti Pesan Ortu (Opsional)
                    </label>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*,application/pdf" capture="environment">
                    <span class="text-muted" style="font-size: 0.65rem;">Bisa berupa foto lembar surat dokter, surat ortu, atau bukti chat WhatsApp.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Alasan & Sanksi Edukatif -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-2 px-3">
            <div class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Alasan <span class="text-danger">*</span></label>
                <textarea name="reason" id="m_reason" class="form-control form-control-sm" rows="2" placeholder="Alasan keterlambatan atau perizinan..." required style="font-size: 0.8rem;"></textarea>
                <div class="d-flex gap-1 mt-1 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-reason" data-val="Bangun kesiangan" style="font-size: 0.65rem;">Kesiangan</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-reason" data-val="Kendaraan bermasalah / ban bocor" style="font-size: 0.65rem;">Ban Bocor</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-reason" data-val="Sakit / Kondisi tubuh kurang fit" style="font-size: 0.65rem;">Sakit</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-reason" data-val="Keperluan keluarga mendesak" style="font-size: 0.65rem;">Keluarga</button>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold mb-1" style="font-size: 0.75rem;">Tindakan / Sanksi Edukatif</label>
                <input type="text" name="action_taken" id="m_action_taken" class="form-control form-control-sm" placeholder="Contoh: Doa di piket, operasi semut...">
                <div class="d-flex gap-1 mt-1 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-action" data-val="Membaca Surat Pendek / Doa di Meja Piket" style="font-size: 0.65rem;">Surat Pendek</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-action" data-val="Operasi semut pungut sampah 10 menit" style="font-size: 0.65rem;">Operasi Semut</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2 m-quick-action" data-val="Teguran lisan & pengarahan kedisiplinan" style="font-size: 0.65rem;">Pengarahan</button>
                </div>
            </div>

            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="notify_telegram" value="1" id="m_notify_telegram" checked>
                <label class="form-check-label small" for="m_notify_telegram" style="font-size: 0.75rem;">
                    Kirim notifikasi Telegram ke Wali &amp; Guru
                </label>
            </div>
        </div>
    </div>

    <!-- Tombol Simpan -->
    <div class="mb-4">
        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem;">
            <i class="ti ti-check fs-2"></i>
            <span>Terbitkan Izin Siswa</span>
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Fungsi global pemilihan jenis izin
function selectPermitType(type) {
    const radio = document.querySelector('input[name="permit_type"][value="' + type + '"]');
    if (radio) {
        radio.checked = true;
    }

    const cards = {
        'terlambat': { id: 'card_type_terlambat', activeClass: 'border-warning bg-warning-lt' },
        'izin_keluar_sementara': { id: 'card_type_izin_keluar_sementara', activeClass: 'border-primary bg-blue-lt' },
        'izin_pulang_cepat': { id: 'card_type_izin_pulang_cepat', activeClass: 'border-danger bg-danger-lt' },
        'izin_tidak_masuk': { id: 'card_type_izin_tidak_masuk', activeClass: 'border-purple bg-purple-lt' }
    };

    // Reset dan atur warna kartu
    Object.keys(cards).forEach(key => {
        const item = cards[key];
        const el = document.getElementById(item.id);
        if (el) {
            el.classList.remove('border-warning', 'bg-warning-lt', 'border-primary', 'bg-blue-lt', 'border-danger', 'bg-danger-lt', 'border-purple', 'bg-purple-lt');
            if (key === type) {
                el.classList.remove('bg-white');
                const classes = item.activeClass.split(' ');
                classes.forEach(c => el.classList.add(c));
            } else {
                el.classList.add('bg-white');
            }
        }
    });

    const boxLateWrapper = document.getElementById('m_box_late_wrapper');
    const boxLeaveTimes = document.getElementById('m_box_leave_times');
    const boxEarlyHome = document.getElementById('m_box_early_home');
    const boxAbsence = document.getElementById('m_box_absence');
    const labelDate = document.getElementById('m_label_date');

    // Sembunyikan semua terlebih dahulu
    if (boxLateWrapper) boxLateWrapper.style.setProperty('display', 'none', 'important');
    if (boxLeaveTimes) boxLeaveTimes.style.setProperty('display', 'none', 'important');
    if (boxEarlyHome) boxEarlyHome.style.setProperty('display', 'none', 'important');
    if (boxAbsence) boxAbsence.style.setProperty('display', 'none', 'important');

    if (type === 'terlambat') {
        // 1. TERLAMBAT: Tanggal Terlambat, Jam Tiba, Masuk Jam Ke-
        // (Tanpa jam keluar, tanpa perkiraan kembali)
        if (labelDate) labelDate.textContent = 'Tanggal Terlambat';
        if (boxLateWrapper) boxLateWrapper.style.setProperty('display', 'block', 'important');
    } else if (type === 'izin_keluar_sementara') {
        // 2. KELUAR SEMENTARA: Tanggal Izin, Jam Keluar, Perkiraan Jam Kembali
        if (labelDate) labelDate.textContent = 'Tanggal Izin Keluar';
        if (boxLeaveTimes) boxLeaveTimes.style.setProperty('display', 'block', 'important');
    } else if (type === 'izin_pulang_cepat') {
        // 3. PULANG CEPAT: Tanggal Izin & Jam Pulang Cepat Saja
        // (Tanpa Jam Keluar, Tanpa Perkiraan Kembali)
        if (labelDate) labelDate.textContent = 'Tanggal Pulang Cepat';
        if (boxEarlyHome) boxEarlyHome.style.setProperty('display', 'block', 'important');
    } else if (type === 'izin_tidak_masuk') {
        // 4. TIDAK MASUK: Tanggal Mulai, Kategori Surat (S/I/D), Sampai Tanggal, Foto Bukti
        // (Tanpa jam apa pun)
        if (labelDate) labelDate.textContent = 'Tanggal Mulai Tidak Masuk';
        if (boxAbsence) boxAbsence.style.setProperty('display', 'block', 'important');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi jenis perizinan default saat DOM siap
    const defaultCheckedRadio = document.querySelector('input[name="permit_type"]:checked');
    const initialType = defaultCheckedRadio ? defaultCheckedRadio.value : 'terlambat';
    selectPermitType(initialType);

    // Quick Reason Chips
    document.querySelectorAll('.m-quick-reason').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('m_reason').value = this.getAttribute('data-val');
        });
    });

    // Quick Action Chips
    document.querySelectorAll('.m-quick-action').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('m_action_taken').value = this.getAttribute('data-val');
        });
    });

    // Student Autocomplete Search
    const searchInput = document.getElementById('m_student_search');
    const autoList = document.getElementById('m_student_autocomplete');
    const searchBtn = document.getElementById('m_btn_do_search');
    const clearBtn = document.getElementById('m_btn_clear_search');
    const studentIdInput = document.getElementById('m_student_id');
    const studentNameInput = document.getElementById('m_student_name');
    const classroomSelect = document.getElementById('m_classroom_id');
    const homeroomInfo = document.getElementById('m_homeroom_info');
    const homeroomName = document.getElementById('m_homeroom_name');

    let debounceTimer;

    function triggerSearch(immediate = false) {
        const query = searchInput.value.trim();
        clearBtn.style.display = query ? 'flex' : 'none';

        clearTimeout(debounceTimer);
        if (query.length < 2) {
            autoList.style.display = 'none';
            return;
        }

        const runFetch = () => {
            autoList.innerHTML = '<div class="list-group-item text-muted small p-2 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Mencari data siswa...</div>';
            autoList.style.display = 'block';

            fetch(`{{ route('piket.students.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    autoList.innerHTML = '';
                    if (data.length === 0) {
                        autoList.innerHTML = '<div class="list-group-item text-muted small p-2 text-center">Siswa tidak ditemukan</div>';
                        autoList.style.display = 'block';
                        return;
                    }
                    data.forEach(s => {
                        const item = document.createElement('a');
                        item.href = 'javascript:void(0)';
                        item.style.cssText = 'display: block; padding: 6px 12px; text-decoration: none; border-bottom: 1px solid #f1f5f9; background: #ffffff; cursor: pointer; transition: background 0.15s ease;';
                        item.innerHTML = `
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.82rem; line-height: 1.2; margin: 0 0 2px 0;">${s.name}</div>
                            <div style="font-size: 0.68rem; color: #195b9e; font-weight: 600; line-height: 1; margin: 0;">Kelas ${s.classroom_name || '-'}</div>
                        `;
                        item.addEventListener('mouseenter', () => item.style.background = '#f8fafc');
                        item.addEventListener('mouseleave', () => item.style.background = '#ffffff');
                        item.addEventListener('click', function (e) {
                            e.preventDefault();
                            studentIdInput.value = s.id;
                            studentNameInput.value = s.name;
                            if (s.classroom_id) {
                                classroomSelect.value = s.classroom_id;
                                classroomSelect.dispatchEvent(new Event('change'));
                            }
                            searchInput.value = s.name;
                            autoList.style.display = 'none';
                        });
                        autoList.appendChild(item);
                    });
                    autoList.style.display = 'block';
                })
                .catch(() => {
                    autoList.style.display = 'none';
                });
        };

        if (immediate) {
            runFetch();
        } else {
            debounceTimer = setTimeout(runFetch, 250);
        }
    }

    searchInput.addEventListener('input', function () {
        triggerSearch(false);
    });

    if (searchBtn) {
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            triggerSearch(true);
        });
    }

    // Tekan Enter di kolom pencarian pintar membuka referensi langsung
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerSearch(true);
        }
    });

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        autoList.style.display = 'none';
        searchInput.focus();
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !autoList.contains(e.target)) {
            autoList.style.display = 'none';
        }
    });

    classroomSelect.addEventListener('change', function () {
        const selectedOpt = this.options[this.selectedIndex];
        const homeroom = selectedOpt ? selectedOpt.getAttribute('data-homeroom') : '';
        if (homeroom && homeroom !== 'Belum ada wali') {
            homeroomName.textContent = homeroom;
            homeroomInfo.style.display = 'block';
        } else {
            homeroomInfo.style.display = 'none';
        }
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
