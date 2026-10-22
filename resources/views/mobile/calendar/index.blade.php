@extends('layouts.mobile')

@section('title', 'Kalender Kegiatan — APK SIAP')
@section('page_title', 'Kalender Kegiatan')
@section('back_url', route('dashboard'))

@push('styles')
<style>
    /* Mini Calendar Grid */
    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 3px;
        text-align: center;
    }
    .cal-weekday {
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
        padding: 4px 0;
    }
    .cal-weekday.sunday {
        color: #ef4444;
    }
    .cal-cell {
        position: relative;
        min-height: 44px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.15s ease;
        background: #ffffff;
        border: 1px solid #f1f5f9;
        user-select: none;
    }
    .cal-cell:active {
        transform: scale(0.94);
    }
    .cal-cell.is-other-month {
        opacity: 0.3;
        background: transparent;
    }
    .cal-cell.is-holiday .cal-day-num {
        color: #dc2626;
        font-weight: 700;
    }
    .cal-cell.is-sunday .cal-day-num {
        color: #ef4444;
    }
    .cal-day-num {
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1.2;
    }
    .cal-cell.is-today .cal-day-num {
        background: #195b9e;
        color: #ffffff !important;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .cal-cell.is-active {
        background: #e0f2fe !important;
        border: 2px solid #0284c7 !important;
    }
    .cal-dots {
        display: flex;
        gap: 2px;
        align-items: center;
        justify-content: center;
        height: 6px;
        margin-top: 2px;
    }
    .cal-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
    }

    /* Filter Chips */
    .filter-chips-container {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 2px 0 6px 0;
    }
    .filter-chips-container::-webkit-scrollbar {
        display: none;
    }
    .filter-chip {
        white-space: nowrap;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .filter-chip.active {
        background: #195b9e;
        color: #ffffff;
        border-color: #195b9e;
        box-shadow: 0 2px 6px rgba(25, 91, 158, 0.25);
    }

    /* Agenda Event Card */
    .agenda-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        overflow: hidden;
    }
    .agenda-card:active {
        transform: scale(0.99);
    }
    .agenda-card-strip {
        width: 4px;
        flex-shrink: 0;
    }
    .agenda-date-box {
        width: 48px;
        flex-shrink: 0;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4px 2px;
    }
</style>
@endpush

@section('content')
<!-- Header Card: Info Tahun Ajaran & Berkas Kaldik -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); color: #ffffff;">
    <div class="card-body p-2.5 px-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="text-white-50 small" style="font-size: 0.7rem;">Kalender Akademik &bull; T.A. {{ $academicYear }}</div>
                <div class="fw-bold text-white fs-4 lh-sm">
                    {{ $setting->school_name ?? 'Kalender Kegiatan Sekolah' }}
                </div>
            </div>
            @if($activeUpload)
                <a href="{{ route('kurikulum.calendar.kaldik.preview', $activeUpload->id) }}" target="_blank" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-sm flex-shrink-0" style="font-size: 0.72rem;">
                    <i class="ti ti-file-text"></i> Berkas Kaldik
                </a>
            @endif
        </div>
        <div class="d-flex align-items-center gap-1.5 flex-wrap text-white-50" style="border-top: 1px solid rgba(255, 255, 255, 0.2); margin-top: 6px; padding-top: 5px; font-size: 0.72rem;">
            <span><strong class="text-white">{{ $totalEvents }}</strong> Kegiatan</span>
            <span>|</span>
            <span><strong class="text-white">{{ $totalHolidays }}</strong> Hari Libur</span>
            <span>|</span>
            <span><strong class="text-white">{{ $totalCurriculumAgendas }}</strong> Agenda Kurikulum</span>
        </div>
    </div>
</div>

<!-- Navigasi Bulan & Kalender Bulanan -->
<div class="card border-0 shadow-sm rounded-3 mb-2">
    <div class="card-body p-2.5">
        <!-- Month Navigator -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-1">
                <button type="button" id="btnPrevMonth" class="btn btn-sm btn-light border-0 rounded-circle p-1" style="width: 32px; height: 32px;" title="Bulan Sebelumnya">
                    <i class="ti ti-chevron-left fs-3 text-dark"></i>
                </button>
                <div id="monthYearTitle" class="fw-bold text-dark fs-3 ps-1" style="min-width: 130px;">
                    Oktober 2026
                </div>
                <button type="button" id="btnNextMonth" class="btn btn-sm btn-light border-0 rounded-circle p-1" style="width: 32px; height: 32px;" title="Bulan Berikutnya">
                    <i class="ti ti-chevron-right fs-3 text-dark"></i>
                </button>
            </div>
            <button type="button" id="btnToday" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                <i class="ti ti-rotate-clockwise me-0.5"></i> Bulan Ini
            </button>
        </div>

        <!-- Kalender Weekday Header -->
        <div class="cal-grid mb-1">
            <div class="cal-weekday sunday">Min</div>
            <div class="cal-weekday">Sen</div>
            <div class="cal-weekday">Sel</div>
            <div class="cal-weekday">Rab</div>
            <div class="cal-weekday">Kam</div>
            <div class="cal-weekday">Jum</div>
            <div class="cal-weekday">Sab</div>
        </div>

        <!-- Kalender Days Grid (Diisi oleh JS) -->
        <div id="calendarDaysGrid" class="cal-grid">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- Petunjuk Warna Dot -->
        <div class="d-flex align-items-center justify-content-center gap-3 mt-2 pt-2 border-top" style="font-size: 0.68rem; color: #64748b;">
            <div class="d-flex align-items-center gap-1">
                <span class="cal-dot" style="background: #dc2626;"></span> Libur
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="cal-dot" style="background: #8b5cf6;"></span> Bebas KBM
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="cal-dot" style="background: #0284c7;"></span> Daring / Khusus
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="cal-dot" style="background: #16a34a;"></span> Kegiatan Sekolah
            </div>
        </div>
    </div>
</div>

<!-- Filter Kategori Chips -->
<div class="mb-2">
    <div class="filter-chips-container">
        <button type="button" class="filter-chip active" data-filter="all">Semua</button>
        <button type="button" class="filter-chip" data-filter="libur">Hari Libur</button>
        <button type="button" class="filter-chip" data-filter="bebas_kbm">Bebas KBM</button>
        <button type="button" class="filter-chip" data-filter="daring">KBM Daring</button>
        <button type="button" class="filter-chip" data-filter="kegiatan">Kegiatan Siswa</button>
        <button type="button" class="filter-chip" data-filter="guru">Tugas Guru</button>
    </div>
</div>

<!-- Section Daftar Agenda -->
<div class="mb-3">
    <!-- Header Section Agenda -->
    <div class="d-flex align-items-center justify-content-between mb-2 px-1">
        <div>
            <div class="fw-bold text-dark fs-4 lh-sm" id="agendaHeadingTitle">Agenda Kegiatan</div>
            <div class="text-muted small" id="agendaHeadingSubtitle" style="font-size: 0.7rem;">Menampilkan agenda bulan berjalan</div>
        </div>
        <button type="button" id="btnShowAllMonth" class="btn btn-sm btn-link text-primary text-decoration-none p-0 fw-bold" style="font-size: 0.72rem; display: none;">
            Semua Bulan Ini
        </button>
    </div>

    <!-- Container Kartu Agenda -->
    <div id="agendaListContainer" class="d-flex flex-column gap-2 mb-5">
        <!-- Rendered by JavaScript -->
    </div>
</div>

@if(auth()->user()->canViewAllSchedules())
    <!-- Floating Action Button (FAB) Tambah Kegiatan -->
    <div style="position: fixed; bottom: calc(64px + env(safe-area-inset-bottom, 16px)); right: 16px; z-index: 500;">
        <button type="button" onclick="openCreateActivitySheet()" class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); border: 2px solid rgba(255, 255, 255, 0.3);">
            <i class="ti ti-plus fs-1 text-white"></i>
        </button>
    </div>
@endif
@endsection

@if(auth()->user()->canViewAllSchedules())
@push('modals')
<!-- Framework7 Bottom Sheet: Formulir Tambah Kegiatan Sekolah -->
<div class="sheet-modal sheet-modal-bottom" id="sheet-add-activity" data-swipe-to-close="false" style="height: auto; max-height: 90vh; border-radius: 20px 20px 0 0; background: #ffffff; overflow: hidden !important;">
    <div class="toolbar" style="background: linear-gradient(135deg, #102d52 0%, #195b9e 100%); border-radius: 20px 20px 0 0;">
        <div class="toolbar-inner px-3">
            <div class="left fw-bold text-white fs-3 d-flex align-items-center gap-1.5">
                <i class="ti ti-calendar-plus text-warning"></i> <span>Tambah Kegiatan Sekolah</span>
            </div>
            <div class="right">
                <a class="link text-white-50 text-decoration-none p-1" href="javascript:void(0)" onclick="closeCreateActivitySheet(); return false;">
                    <i class="ti ti-x fs-2"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="sheet-modal-inner p-3" style="max-height: calc(90vh - 56px); overflow-y: auto; -webkit-overflow-scrolling: touch; padding-bottom: calc(2rem + env(safe-area-inset-bottom, 16px)) !important;">
        <form id="form-add-activity" onsubmit="submitCreateActivity(event)">
            @csrf

            <!-- 1. Kategori & Judul Kegiatan -->
            <div class="mb-2.5">
                <label class="form-label small fw-bold mb-1 text-dark">Kategori Kegiatan <span class="text-danger">*</span></label>
                <select name="category" id="mob_input_category" class="form-select form-select-sm" required onchange="onMobCategoryChange(this.value)">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($categories as $catKey => $cat)
                        <option value="{{ $catKey }}" {{ $catKey === 'kegiatan_kurikulum' ? 'selected' : '' }}>
                            {{ $cat['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2.5">
                <label class="form-label small fw-bold mb-1 text-dark">Nama / Judul Kegiatan <span class="text-danger">*</span></label>
                <input type="text" name="title" id="mob_input_title" class="form-control form-control-sm" placeholder="Contoh: Asesmen Sumatif Akhir Semester, MPLS, dll." required>
            </div>

            <!-- 2. Waktu & Tanggal -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small fw-bold mb-1 text-dark">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" id="mob_input_start_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold mb-1 text-dark">Tanggal Selesai</label>
                    <input type="date" name="end_date" id="mob_input_end_date" class="form-control form-control-sm" placeholder="Opsional">
                </div>
            </div>

            <!-- Switch Sehari Penuh & Jam -->
            <div class="p-2 bg-light rounded-2 border mb-2.5">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="mob_input_is_all_day" name="is_all_day" value="1" checked onchange="onMobAllDayToggle(this.checked)">
                    <label class="form-check-label small fw-bold text-dark" for="mob_input_is_all_day">
                        Sepanjang Hari (Sehari Penuh)
                    </label>
                </div>
                <div id="mob_box_times" class="row g-2 mt-1" style="display: none;">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Jam Mulai:</label>
                        <input type="time" name="start_time" id="mob_input_start_time" class="form-control form-control-sm" value="07:00">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Jam Selesai:</label>
                        <input type="time" name="end_time" id="mob_input_end_time" class="form-control form-control-sm" value="12:00">
                    </div>
                </div>
            </div>

            <!-- Switch Hari Libur Sekolah Penuh -->
            <div class="p-2 bg-red-lt rounded-2 border border-danger-subtle mb-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="mob_input_is_holiday" name="is_holiday" value="1" onchange="onMobHolidayToggle(this.checked)">
                    <label class="form-check-label small fw-bold text-danger" for="mob_input_is_holiday">
                        <i class="ti ti-calendar-off me-0.5"></i> Libur Sekolah Penuh (Bebas KBM & Presensi)
                    </label>
                </div>
                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.2;">
                    Jika dicentang, seluruh warga sekolah bebas KBM dan presensi.
                </div>
            </div>

            <!-- Section Peserta & Dampak KBM (Disembunyikan jika libur) -->
            <div id="mob_section_participants">
                <!-- A. Sasaran Kelompok Siswa & Dampak Pembelajaran -->
                <div class="card border border-info-subtle shadow-none bg-azure-lt mb-3">
                    <div class="card-body p-2.5">
                        <div class="fw-bold text-dark small mb-1.5 d-flex align-items-center">
                            <i class="ti ti-school text-primary me-1 fs-3"></i> Sasaran Siswa & Dampak KBM
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Kelompok Siswa:</label>
                            <select name="target_audience" id="mob_input_target_audience" class="form-select form-select-sm" onchange="onMobTargetAudienceChange(this.value)">
                                <option value="semua" selected>Semua Siswa (Seluruh Sekolah)</option>
                                <option value="tingkat">Tingkat Tertentu (Contoh: Hanya Kelas XII)</option>
                                <option value="rombel">Rombel Tertentu (Pilih Rombel)</option>
                                <option value="guru_staf">Khusus Guru / Staf (Tanpa Siswa)</option>
                            </select>
                        </div>

                        <!-- Target Tingkat Checkbox -->
                        <div id="mob_box_target_tingkat" class="mb-2 p-2 bg-white rounded border" style="display: none;">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Pilih Tingkat:</label>
                            <div class="d-flex gap-3 flex-wrap">
                                @foreach($gradeLevels as $gl)
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" name="target_grade_levels[]" value="{{ $gl->level }}">
                                        <span class="form-check-label small" style="font-size: 0.75rem;">Kelas {{ $gl->level }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Target Rombel Checkbox -->
                        <div id="mob_box_target_rombel" class="mb-2 p-2 bg-white rounded border" style="display: none; max-height: 120px; overflow-y: auto;">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size: 0.72rem;">Pilih Rombel Kelas:</label>
                            <div class="row g-1">
                                @foreach($classrooms as $cls)
                                    <div class="col-6">
                                        <label class="form-check mb-0.5">
                                            <input class="form-check-input" type="checkbox" name="target_classroom_ids[]" value="{{ $cls->id }}">
                                            <span class="form-check-label small text-truncate d-block" style="font-size: 0.72rem;">{{ $cls->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Status Dampak KBM Siswa Terlibat -->
                        <div id="mob_box_student_learning_impact">
                            <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Dampak Terhadap KBM Kelas Terlibat:</label>
                            <select name="learning_impact" id="mob_input_learning_impact" class="form-select form-select-sm">
                                <option value="bebas_kbm_kegiatan" selected>Bebas KBM (Ujian / PKL / Kegiatan Khusus)</option>
                                <option value="pembelajaran_daring">Pembelajaran Daring</option>
                                <option value="pulang_cepat">Pulang Cepat</option>
                                <option value="normal">KBM Normal (Sesuai Jadwal)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- B. Penugasan Guru / GTK (Opsional) -->
                <div class="card border border-success-subtle shadow-none bg-green-lt mb-3">
                    <div class="card-body p-2.5">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <div class="fw-bold text-dark small d-flex align-items-center">
                                <i class="ti ti-user-check text-success me-1 fs-3"></i> GTK yang Bertugas (Opsional)
                            </div>
                            <span class="text-muted" id="mobGtkCountBadge" style="font-size: 0.68rem;">0 terpilih</span>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0.5" style="font-size: 0.72rem;">Kewajiban Mengajar Guru Terlibat:</label>
                            <select name="teacher_impact" id="mob_input_teacher_impact" class="form-select form-select-sm">
                                <option value="bebas_mengajar_tugas">Gugur Kewajiban Mengajar (Bebas Inval/Tugas Khusus)</option>
                                <option value="normal">Tetap Mengajar Reguler Sesuai Jadwal</option>
                            </select>
                        </div>
                        <div class="p-2 bg-white rounded border" style="max-height: 140px; overflow-y: auto;">
                            @foreach($allGtk as $gtk)
                                <label class="form-check mb-1">
                                    <input class="form-check-input mob-gtk-cb" type="checkbox" name="user_ids[]" value="{{ $gtk->id }}" onchange="updateMobGtkCount()">
                                    <span class="form-check-label small text-truncate d-block" style="font-size: 0.74rem;">
                                        {{ $gtk->name }}
                                        <span class="badge {{ $gtk->isStaff() ? 'bg-secondary-lt' : 'bg-primary-lt' }}" style="font-size: 0.6rem; padding: 1px 4px;">
                                            {{ $gtk->isStaff() ? 'Staf' : 'Guru' }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- C. Keterangan / Petunjuk -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-dark">Instruksi Guru / Catatan Kegiatan (Opsional)</label>
                    <textarea name="teacher_instructions" id="mob_input_instructions" rows="2" class="form-control form-control-sm" placeholder="Contoh: Siswa memakai seragam pramuka, berkumpul di aula..." style="font-size: 0.78rem;"></textarea>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit" id="mobBtnSubmitActivity" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2" style="font-size: 0.92rem; background: linear-gradient(135deg, #102d52 0%, #195b9e 100%);">
                <i class="ti ti-check fs-2"></i>
                <span>Simpan Kegiatan</span>
            </button>
        </form>
    </div>
</div>
@endpush
@endif

@push('scripts')
<script>
    // Master data events dari backend
    const allEventsData = @json($eventsJson);

    // State Kalender Mobile
    const monthNamesId = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const dayNamesId = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const shortMonthNamesId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    let currentYear = now.getFullYear();
    let currentMonth = now.getMonth(); // 0-11
    let selectedDate = null; // Format YYYY-MM-DD atau null jika lihat semua bulan
    let currentFilter = 'all';

    // Helper format tanggal Indonesia
    function formatIndoDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const d = parseInt(parts[2], 10);
        const m = parseInt(parts[1], 10) - 1;
        const y = parts[0];
        return `${d} ${shortMonthNamesId[m]} ${y}`;
    }

    function formatDayName(dateStr) {
        if (!dateStr) return '';
        const dateObj = new Date(dateStr + 'T00:00:00');
        return dayNamesId[dateObj.getDay()];
    }

    // Periksa apakah sebuah tanggal masuk rentang event
    function isDateInEventRange(dateStr, ev) {
        if (!ev.start_date) return false;
        const start = ev.start_date;
        const end = ev.end_date || ev.start_date;
        return dateStr >= start && dateStr <= end;
    }

    // Dapatkan event untuk tanggal tertentu
    function getEventsForDate(dateStr) {
        return allEventsData.filter(ev => isDateInEventRange(dateStr, ev));
    }

    // Render Grid Kalender
    function renderCalendar() {
        const titleEl = document.getElementById('monthYearTitle');
        const gridEl = document.getElementById('calendarDaysGrid');
        if (!titleEl || !gridEl) return;

        titleEl.textContent = `${monthNamesId[currentMonth]} ${currentYear}`;
        gridEl.innerHTML = '';

        // Hari pertama bulan (0 = Minggu, 1 = Senin, ...)
        const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

        // 1. Padding hari dari bulan sebelumnya
        for (let i = firstDayOfMonth - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const prevMonthDate = new Date(currentYear, currentMonth - 1, dayNum);
            const dateStr = `${prevMonthDate.getFullYear()}-${String(prevMonthDate.getMonth() + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;

            const cell = document.createElement('div');
            cell.className = 'cal-cell is-other-month';
            cell.dataset.date = dateStr;
            cell.innerHTML = `<span class="cal-day-num">${dayNum}</span>`;
            cell.addEventListener('click', () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                selectedDate = dateStr;
                renderCalendar();
                renderAgendaList();
            });
            gridEl.appendChild(cell);
        }

        // 2. Hari di bulan aktif
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dateObj = new Date(currentYear, currentMonth, day);
            const dayOfWeek = dateObj.getDay();

            const events = getEventsForDate(dateStr);
            const isHoliday = events.some(e => e.is_holiday) || dayOfWeek === 0;
            const isToday = (dateStr === todayStr);
            const isSelected = (selectedDate === dateStr);

            const cell = document.createElement('div');
            cell.className = 'cal-cell';
            if (isToday) cell.classList.add('is-today');
            if (isSelected) cell.classList.add('is-active');
            if (isHoliday) cell.classList.add('is-holiday');
            if (dayOfWeek === 0) cell.classList.add('is-sunday');
            cell.dataset.date = dateStr;

            // Render Dot Indikator Event
            let dotsHtml = '';
            if (events.length > 0) {
                dotsHtml = '<div class="cal-dots">';
                // Ambil maks 3 dot warna
                const maxDots = Math.min(events.length, 3);
                for (let e = 0; e < maxDots; e++) {
                    const ev = events[e];
                    let dotColor = ev.category_color || '#195b9e';
                    if (ev.is_holiday) dotColor = '#dc2626';
                    else if (ev.learning_impact === 'bebas_kbm_kegiatan') dotColor = '#8b5cf6';
                    else if (ev.learning_impact === 'pembelajaran_daring') dotColor = '#0284c7';
                    else if (ev.learning_impact === 'pulang_cepat') dotColor = '#f59e0b';

                    dotsHtml += `<span class="cal-dot" style="background-color: ${dotColor};"></span>`;
                }
                dotsHtml += '</div>';
            }

            cell.innerHTML = `
                <span class="cal-day-num">${day}</span>
                ${dotsHtml}
            `;

            cell.addEventListener('click', () => {
                if (selectedDate === dateStr) {
                    // Klik tanggal yang sama -> unselect untuk lihat semua bulan
                    selectedDate = null;
                } else {
                    selectedDate = dateStr;
                }
                renderCalendar();
                renderAgendaList();
            });

            gridEl.appendChild(cell);
        }

        // 3. Padding hari ke bulan berikutnya (agar genap kelipatan 7 baris)
        const totalRendered = firstDayOfMonth + daysInMonth;
        const nextDaysPadding = (7 - (totalRendered % 7)) % 7;
        for (let nextDay = 1; nextDay <= nextDaysPadding; nextDay++) {
            const nextMonthDate = new Date(currentYear, currentMonth + 1, nextDay);
            const dateStr = `${nextMonthDate.getFullYear()}-${String(nextMonthDate.getMonth() + 1).padStart(2, '0')}-${String(nextDay).padStart(2, '0')}`;

            const cell = document.createElement('div');
            cell.className = 'cal-cell is-other-month';
            cell.dataset.date = dateStr;
            cell.innerHTML = `<span class="cal-day-num">${nextDay}</span>`;
            cell.addEventListener('click', () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                selectedDate = dateStr;
                renderCalendar();
                renderAgendaList();
            });
            gridEl.appendChild(cell);
        }
    }

    // Filter logic untuk event
    function passesFilter(ev) {
        if (currentFilter === 'all') return true;
        if (currentFilter === 'libur') return Boolean(ev.is_holiday);
        if (currentFilter === 'bebas_kbm') return ev.learning_impact === 'bebas_kbm_kegiatan';
        if (currentFilter === 'daring') return ev.learning_impact === 'pembelajaran_daring';
        if (currentFilter === 'kegiatan') return ['kts', 'ke', 'kpp', 'kegiatan_kurikulum', 'lainnya'].includes(ev.category);
        if (currentFilter === 'guru') return ['tugas_guru', 'supervisi'].includes(ev.category);
        return true;
    }

    // Render Daftar Agenda
    function renderAgendaList() {
        const containerEl = document.getElementById('agendaListContainer');
        const headingTitleEl = document.getElementById('agendaHeadingTitle');
        const headingSubtitleEl = document.getElementById('agendaHeadingSubtitle');
        const btnShowAllMonthEl = document.getElementById('btnShowAllMonth');
        if (!containerEl) return;

        let filteredEvents = [];

        if (selectedDate) {
            // Mode fokus tanggal tertentu
            const dayName = formatDayName(selectedDate);
            const indoDate = formatIndoDate(selectedDate);
            const isToday = (selectedDate === todayStr);

            headingTitleEl.textContent = isToday ? `Agenda Hari Ini (${dayName}, ${indoDate})` : `Agenda: ${dayName}, ${indoDate}`;
            btnShowAllMonthEl.style.display = 'block';

            const eventsOnDate = getEventsForDate(selectedDate).filter(passesFilter);
            filteredEvents = eventsOnDate;
            headingSubtitleEl.textContent = `${filteredEvents.length} agenda ditemukan`;
        } else {
            // Mode seluruh bulan
            headingTitleEl.textContent = `Agenda Bulan ${monthNamesId[currentMonth]} ${currentYear}`;
            btnShowAllMonthEl.style.display = 'none';

            // Ambil semua event yang rentangnya bersentuhan dengan bulan aktif
            const monthStartStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-01`;
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const monthEndStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;

            filteredEvents = allEventsData.filter(ev => {
                if (!ev.start_date) return false;
                const end = ev.end_date || ev.start_date;
                return (ev.start_date <= monthEndStr && end >= monthStartStr) && passesFilter(ev);
            });

            // Urutkan berdasarkan tanggal mulai asc
            filteredEvents.sort((a, b) => a.start_date.localeCompare(b.start_date));
            headingSubtitleEl.textContent = `${filteredEvents.length} agenda kegiatan terdaftar`;
        }

        // Jika tidak ada agenda yang ditemukan
        if (filteredEvents.length === 0) {
            if (selectedDate) {
                containerEl.innerHTML = `
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-3 text-center">
                            <span class="avatar avatar-md bg-green-lt text-success rounded-circle mb-2 mx-auto" style="width: 44px; height: 44px;">
                                <i class="ti ti-calendar-check fs-2"></i>
                            </span>
                            <div class="fw-bold text-dark fs-3 lh-sm mb-1">KBM Normal Sesuai Jadwal</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">
                                Tidak ada agenda khusus pada tanggal ini. Kegiatan pembelajaran berlangsung normal.
                            </div>
                        </div>
                    </div>
                `;
            } else {
                containerEl.innerHTML = `
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-3 text-center">
                            <span class="avatar avatar-md bg-blue-lt text-primary rounded-circle mb-2 mx-auto" style="width: 44px; height: 44px;">
                                <i class="ti ti-calendar fs-2"></i>
                            </span>
                            <div class="fw-bold text-dark fs-3 lh-sm mb-1">Belum Ada Agenda Khusus</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">
                                Tidak ada agenda kurikulum atau kegiatan terdaftar pada bulan ${monthNamesId[currentMonth]} ${currentYear}.
                            </div>
                        </div>
                    </div>
                `;
            }
            return;
        }

        // Render Kartu Agenda
        let html = '';
        filteredEvents.forEach(ev => {
            const startParts = ev.start_date ? ev.start_date.split('-') : [];
            const dayNum = startParts[2] ? parseInt(startParts[2], 10) : '';
            const monthShort = startParts[1] ? shortMonthNamesId[parseInt(startParts[1], 10) - 1] : '';

            // Multi-hari teks
            let dateRangeText = formatIndoDate(ev.start_date);
            if (ev.end_date && ev.end_date !== ev.start_date) {
                dateRangeText = `${formatIndoDate(ev.start_date)} — ${formatIndoDate(ev.end_date)}`;
            }

            // Waktu
            let timeText = '';
            if (!ev.is_all_day && ev.start_time) {
                timeText = ` &bull; ${ev.start_time}${ev.end_time ? ' - ' + ev.end_time : ''}`;
            }

            // Strip warna
            const stripColor = ev.category_color || '#195b9e';

            // KBM badge
            let impactBadgeHtml = '';
            if (ev.is_holiday) {
                impactBadgeHtml = `<span class="badge bg-danger text-white flex-shrink-0" style="font-size: 0.65rem; padding: 2px 6px;">Libur</span>`;
            } else if (ev.learning_impact && ev.learning_impact !== 'normal') {
                impactBadgeHtml = `<span class="badge bg-purple text-white flex-shrink-0" style="font-size: 0.65rem; padding: 2px 6px;">${ev.learning_impact_label}</span>`;
            }

            // Teacher instructions
            let instrHtml = '';
            if (ev.teacher_instructions) {
                instrHtml = `
                    <div class="text-muted small mt-1 p-1 px-1.5 rounded" style="font-size: 0.68rem; background: #f8fafc; border-left: 3px solid #cbd5e1;">
                        <i class="ti ti-info-circle text-primary me-0.5"></i> ${ev.teacher_instructions}
                    </div>
                `;
            }

            // Assigned teachers
            let teachersHtml = '';
            if (ev.assigned_teachers && ev.assigned_teachers.length > 0) {
                teachersHtml = `
                    <div class="text-muted small mt-1 text-truncate" style="font-size: 0.68rem;">
                        <i class="ti ti-users text-secondary me-0.5"></i> <strong>GTK Bertugas:</strong> ${ev.assigned_teachers.join(', ')}
                    </div>
                `;
            }

            html += `
                <div class="agenda-card d-flex shadow-sm">
                    <div class="agenda-card-strip" style="background-color: ${stripColor};"></div>
                    <div class="p-2 px-2.5 flex-fill">
                        <div class="d-flex align-items-start gap-2">
                            <div class="agenda-date-box">
                                <div class="fw-bold text-dark fs-3 lh-1">${dayNum}</div>
                                <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">${monthShort}</div>
                            </div>
                            <div class="flex-fill overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                    <span class="badge ${ev.category_badge || 'bg-blue text-white'} flex-shrink-0" style="font-size: 0.62rem; padding: 2px 6px;">
                                        ${ev.category_label || 'Kegiatan'}
                                    </span>
                                    ${impactBadgeHtml}
                                </div>
                                <div class="fw-bold text-dark fs-4 lh-sm text-truncate" title="${ev.title}">
                                    ${ev.title}
                                </div>
                                <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">
                                    <i class="ti ti-calendar me-0.5 text-primary"></i> ${dateRangeText}${timeText}
                                    ${ev.location ? ` &bull; <i class="ti ti-map-pin me-0.5 text-danger"></i>${ev.location}` : ''}
                                </div>
                                ${instrHtml}
                                ${teachersHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        containerEl.innerHTML = html;
    }

    // Inisialisasi Event Listener
    document.addEventListener('DOMContentLoaded', function () {
        // Navigasi Bulan
        document.getElementById('btnPrevMonth')?.addEventListener('click', function () {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            selectedDate = null;
            renderCalendar();
            renderAgendaList();
        });

        document.getElementById('btnNextMonth')?.addEventListener('click', function () {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            selectedDate = null;
            renderCalendar();
            renderAgendaList();
        });

        document.getElementById('btnToday')?.addEventListener('click', function () {
            currentYear = now.getFullYear();
            currentMonth = now.getMonth();
            selectedDate = todayStr;
            renderCalendar();
            renderAgendaList();
        });

        document.getElementById('btnShowAllMonth')?.addEventListener('click', function () {
            selectedDate = null;
            renderCalendar();
            renderAgendaList();
        });

        // Filter Chips
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', function () {
                document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                renderAgendaList();
            });
        });

        // Initial Render
        renderCalendar();
        renderAgendaList();
    });

    // Mobile Sheet Handlers
    function openCreateActivitySheet() {
        const todayVal = selectedDate || todayStr;
        const startDateInput = document.getElementById('mob_input_start_date');
        if (startDateInput && !startDateInput.value) {
            startDateInput.value = todayVal;
        }

        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.open('#sheet-add-activity');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.open('#sheet-add-activity');
        } else {
            const el = document.getElementById('sheet-add-activity');
            if (el) el.classList.add('modal-in');
        }
    }

    function closeCreateActivitySheet() {
        if (typeof app !== 'undefined' && app.sheet) {
            app.sheet.close('#sheet-add-activity');
        } else if (window.f7 && window.f7.sheet) {
            window.f7.sheet.close('#sheet-add-activity');
        } else {
            const el = document.getElementById('sheet-add-activity');
            if (el) el.classList.remove('modal-in');
        }
    }

    function onMobAllDayToggle(isChecked) {
        const boxTimes = document.getElementById('mob_box_times');
        if (boxTimes) {
            boxTimes.style.display = isChecked ? 'none' : 'flex';
        }
    }

    function onMobHolidayToggle(isHoliday) {
        const sectionParticipants = document.getElementById('mob_section_participants');
        if (sectionParticipants) {
            sectionParticipants.style.display = isHoliday ? 'none' : 'block';
        }
    }

    function onMobCategoryChange(cat) {
        const isHolidayCat = ['lhb', 'lu', 'ls1', 'ls2', 'lhr', 'cb'].includes(cat);
        const holidayCb = document.getElementById('mob_input_is_holiday');
        if (holidayCb) {
            holidayCb.checked = isHolidayCat;
            onMobHolidayToggle(isHolidayCat);
        }
    }

    function onMobTargetAudienceChange(val) {
        const boxTingkat = document.getElementById('mob_box_target_tingkat');
        const boxRombel = document.getElementById('mob_box_target_rombel');
        const boxImpact = document.getElementById('mob_box_student_learning_impact');

        if (boxTingkat) boxTingkat.style.display = (val === 'tingkat') ? 'block' : 'none';
        if (boxRombel) boxRombel.style.display = (val === 'rombel') ? 'block' : 'none';
        if (boxImpact) boxImpact.style.display = (val === 'guru_staf') ? 'none' : 'block';
    }

    function updateMobGtkCount() {
        const checked = document.querySelectorAll('.mob-gtk-cb:checked').length;
        const badge = document.getElementById('mobGtkCountBadge');
        if (badge) {
            badge.textContent = `${checked} terpilih`;
        }
    }

    async function submitCreateActivity(e) {
        e.preventDefault();
        const form = document.getElementById('form-add-activity');
        const submitBtn = document.getElementById('mobBtnSubmitActivity');
        const originalBtnHtml = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        const formData = new FormData(form);

        // Ensure unchecked switches submit proper booleans if not checked
        if (!document.getElementById('mob_input_is_all_day').checked) {
            formData.set('is_all_day', '0');
        }
        if (!document.getElementById('mob_input_is_holiday').checked) {
            formData.set('is_holiday', '0');
        }

        try {
            const res = await fetch("{{ route('kurikulum.calendar.store') }}", {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const data = await res.json();

            if (res.ok && data.success) {
                closeCreateActivitySheet();
                form.reset();

                if (typeof app !== 'undefined' && app.toast) {
                    app.toast.create({
                        text: data.message || 'Kegiatan berhasil ditambahkan!',
                        position: 'top',
                        closeTimeout: 3000,
                        cssClass: 'bg-success text-white'
                    }).open();
                } else {
                    alert(data.message || 'Kegiatan berhasil ditambahkan!');
                }

                // Reload halaman untuk menyegarkan agenda & kalender
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            } else {
                let errorMsg = data.message || 'Gagal menyimpan kegiatan.';
                if (data.errors) {
                    const firstErr = Object.values(data.errors)[0];
                    if (Array.isArray(firstErr)) {
                        errorMsg = firstErr[0];
                    }
                }
                alert(errorMsg);
            }
        } catch (err) {
            console.error(err);
            alert('Terjadi kesalahan koneksi saat menyimpan kegiatan.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }
</script>
@endpush
