@extends('layouts.app')

@section('title', 'Buat Jadwal Supervisi')

@section('content')
<div class="row g-3 mb-4 align-items-center">
    <div class="col">
        <h2 class="page-title text-navy fw-bold" style="color: #102d52;">
            <i class="ti ti-calendar-plus me-2"></i> Buat Jadwal Supervisi Baru
        </h2>
        <div class="text-muted small">Pilih guru yang akan disupervisi, hubungkan dengan jadwal mengajarnya, dan tetapkan supervisor penilai.</div>
    </div>
    <div class="col-auto">
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar Jadwal
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 920px;">
    <div class="card-body p-4">
        <form action="{{ route('schedules.store') }}" method="POST" id="formSupervisionSchedule">
            @csrf

            <div class="alert alert-info d-flex align-items-center mb-4 py-2">
                <i class="ti ti-calendar me-2 fs-2 text-info"></i>
                <div>
                    Jadwal supervisi ini dibuat untuk <strong>Tahun Pelajaran: {{ $academicYear }}</strong>, <strong>Semester {{ $semester }}</strong>.
                </div>
            </div>

            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">

            <!-- Row 1: Guru & Supervisor -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required fw-bold">Pilih Guru yang Disupervisi</label>
                    <select name="teacher_id" id="teacherSelect" class="form-select fw-semibold" required onchange="onTeacherChange()">
                        <option value="">-- Pilih Guru --</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" 
                                data-subject="{{ $t->subject }}"
                                data-class="{{ $t->target_class }}"
                                data-expertise="{{ $t->expertise_program }}"
                                {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                                {{ $t->name }} ({{ $t->subject ?: 'Guru' }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small text-muted">Pilih guru untuk melihat dan menghubungkan jadwal mengajarnya secara otomatis.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required fw-bold">Tugaskan Supervisor (Penilai)</label>
                    <select name="supervisor_id" class="form-select fw-semibold" required>
                        <option value="">-- Pilih Supervisor --</option>
                        @foreach($supervisors as $s)
                            <option value="{{ $s->id }}" {{ old('supervisor_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->name }} ({{ $s->role->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Interaktif: Daftar Jadwal Mengajar Guru Terpilih -->
            <div id="lessonScheduleSection" class="mb-4" style="display: none;">
                <div class="card border border-primary-subtle shadow-none" style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-xs bg-primary text-white rounded">
                                <i class="ti ti-clock-play"></i>
                            </span>
                            <strong class="text-primary" style="font-size: 0.95rem;">
                                Pilih Jadwal Mengajar Guru di Kelas (TP {{ $academicYear }})
                            </strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="scheduleCountBadge" class="badge bg-primary text-white">0 Jadwal</span>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearScheduleSelection()" title="Reset pilihan dan isi manual">
                                <i class="ti ti-rotate-2 me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 pt-1">
                        <div class="text-muted small mb-2">
                            Klik salah satu jadwal mengajar di bawah ini. Sistem akan <strong>otomatis mengisi kelas, jam ke, mapel, dan rekomendasi tanggal supervisi</strong>:
                        </div>
                        <div id="scheduleGridList" class="row g-2">
                            <!-- Populated dynamically via JS -->
                        </div>
                        <div id="noScheduleNotice" class="alert alert-warning py-2 px-3 small mb-0" style="display: none;">
                            <i class="ti ti-info-circle me-1"></i> Guru ini belum memiliki jadwal pelajaran terdaftar pada TP {{ $academicYear }}. Anda tetap dapat mengisi tanggal, jam, kelas, dan mapel secara manual pada formulir di bawah.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Tanggal & Waktu -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required fw-bold">Tanggal Supervisi</label>
                    <input type="date" name="schedule_date" id="scheduleDateField" class="form-control fw-semibold" 
                           value="{{ old('schedule_date', date('Y-m-d')) }}" required onchange="validateDayMatch()">
                    
                    <!-- Smart Helper Tanggal Sesuai Hari Mengajar -->
                    <div id="smartDateHelper" class="mt-2 p-2 bg-light rounded border border-light-subtle" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-bold text-dark">
                                <i class="ti ti-bulb text-warning me-1"></i> Jadwal mengajar pada hari <span id="targetDayName" class="text-primary fw-bold">Senin</span>
                            </span>
                            <span id="dayMatchStatus"></span>
                        </div>
                        <div class="small text-muted mb-1">Pilih rekomendasi tanggal <span id="targetDayName2">Senin</span> terdekat:</div>
                        <div id="quickDateButtons" class="d-flex flex-wrap gap-1">
                            <!-- Quick buttons populated dynamically -->
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required fw-bold">Jam Pelajaran / Waktu</label>
                    <input type="text" name="schedule_time" id="timeField" class="form-control fw-semibold" 
                           placeholder="Contoh: Jam ke 1 - 3 (07.00 - 09.15 WIB)" 
                           value="{{ old('schedule_time', '07.30 - 09.00 WIB') }}" required>
                    <div class="form-text small text-muted">Otomatis terisi ketika memilih jadwal mengajar guru di atas.</div>
                </div>
            </div>

            <!-- Row 3: Mapel, Rombel, Konsentrasi Keahlian -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label required fw-bold">Mata Pelajaran</label>
                    <input type="text" name="subject" id="subjectField" list="subjectList" class="form-control fw-semibold" 
                           placeholder="Pilih atau ketik mapel..." value="{{ old('subject') }}" required>
                    <datalist id="subjectList">
                        @foreach($subjects as $sb)
                            <option value="{{ $sb->name }}">{{ $sb->code }} ({{ $sb->category }})</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label required fw-bold">Kelas Yang Diajar (Rombel)</label>
                    <input type="text" name="class_name" id="classField" list="classList" class="form-control fw-semibold" 
                           placeholder="Pilih atau ketik rombel..." value="{{ old('class_name') }}" required>
                    <datalist id="classList">
                        @foreach($classrooms as $cr)
                            <option value="{{ $cr->name }}">{{ $cr->department?->code }} - {{ $cr->gradeLevel?->name }}</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Konsentrasi Keahlian (Jurusan)</label>
                    <input type="text" name="expertise_program" id="expertiseField" class="form-control" 
                           placeholder="Contoh: Akuntansi dan Keuangan Lembaga" value="{{ old('expertise_program') }}">
                </div>
            </div>

            <!-- Row 4: Catatan -->
            <div class="mb-4">
                <label class="form-label fw-bold">Catatan Tambahan untuk Supervisor / Guru</label>
                <textarea name="schedule_notes" class="form-control" rows="3" placeholder="Fokus materi, lokasi ruangan/lab, instrumen, atau instruksi awal...">{{ old('schedule_notes') }}</textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('schedules.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="ti ti-calendar-plus me-1"></i> Simpan Jadwal Supervisi
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .schedule-card-item {
        cursor: pointer;
        transition: all 0.18s ease-in-out;
        border: 2px solid #e2e8f0;
        background-color: #ffffff;
        border-radius: 8px;
    }
    .schedule-card-item:hover {
        border-color: #93c5fd;
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .schedule-card-item.selected-schedule {
        border-color: #2563eb !important;
        background-color: #eff6ff !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .quick-date-btn {
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.3rem 0.65rem;
        border-radius: 6px;
        background-color: #ffffff;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe;
        transition: all 0.15s ease-in-out;
        cursor: pointer;
    }
    .quick-date-btn:hover {
        background-color: #2563eb !important;
        border-color: #2563eb !important;
        color: #ffffff !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
    }
    .quick-date-btn:active, .quick-date-btn.active, .quick-date-btn.selected-date {
        background-color: #1e40af !important;
        border-color: #1e40af !important;
        color: #ffffff !important;
    }
</style>

<script>
    const teachersSchedulesMap = @json($teachersSchedulesMap);
    let currentSelectedSchedule = null;

    const dayNameToIndex = {
        'Minggu': 0,
        'Senin': 1,
        'Selasa': 2,
        'Rabu': 3,
        'Kamis': 4,
        'Jumat': 5,
        'Sabtu': 6
    };

    const indexToDayName = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    const dayBadgeColors = {
        'Senin': 'bg-blue text-white',
        'Selasa': 'bg-teal text-white',
        'Rabu': 'bg-purple text-white',
        'Kamis': 'bg-indigo text-white',
        'Jumat': 'bg-green text-white',
        'Sabtu': 'bg-orange text-white'
    };

    function onTeacherChange() {
        const select = document.getElementById('teacherSelect');
        const teacherId = select.value;

        const section = document.getElementById('lessonScheduleSection');
        const gridList = document.getElementById('scheduleGridList');
        const noScheduleNotice = document.getElementById('noScheduleNotice');
        const countBadge = document.getElementById('scheduleCountBadge');

        clearScheduleSelection();

        if (!teacherId) {
            section.style.display = 'none';
            return;
        }

        const schedules = teachersSchedulesMap[teacherId] || [];

        section.style.display = 'block';
        gridList.innerHTML = '';

        if (schedules.length > 0) {
            countBadge.innerText = `${schedules.length} Sesi Mengajar`;
            noScheduleNotice.style.display = 'none';

            schedules.forEach((sch, index) => {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4';

                const badgeColor = dayBadgeColors[sch.day] || 'bg-secondary text-white';

                col.innerHTML = `
                    <div class="schedule-card-item p-2 h-100" id="schCard_${sch.id}" onclick="selectSchedule(${sch.id})">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge ${badgeColor} fw-bold px-2">${sch.day}</span>
                            <span class="badge bg-dark-lt text-dark fw-bold border">${sch.class_name}</span>
                        </div>
                        <div class="fw-bold text-dark text-truncate" title="${sch.subject_name}" style="font-size: 0.88rem;">
                            ${sch.subject_name}
                        </div>
                        <div class="small text-muted d-flex align-items-center gap-1 mt-1" style="font-size: 0.78rem;">
                            <i class="ti ti-clock text-primary"></i> ${sch.time_formatted}
                        </div>
                    </div>
                `;
                gridList.appendChild(col);
            });

            // Auto-select first schedule if available and fields are empty
            if (schedules.length > 0) {
                selectSchedule(schedules[0].id);
            }
        } else {
            countBadge.innerText = '0 Sesi';
            noScheduleNotice.style.display = 'block';

            // Fallback to data attributes on select option
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt) {
                document.getElementById('subjectField').value = selectedOpt.getAttribute('data-subject') || '';
                document.getElementById('classField').value = selectedOpt.getAttribute('data-class') || '';
                document.getElementById('expertiseField').value = selectedOpt.getAttribute('data-expertise') || '';
            }
        }
    }

    function selectSchedule(scheduleId) {
        const teacherId = document.getElementById('teacherSelect').value;
        const schedules = teachersSchedulesMap[teacherId] || [];
        const sch = schedules.find(s => s.id === scheduleId);

        if (!sch) return;

        currentSelectedSchedule = sch;

        // Visual highlight
        document.querySelectorAll('.schedule-card-item').forEach(el => el.classList.remove('selected-schedule'));
        const activeCard = document.getElementById(`schCard_${sch.id}`);
        if (activeCard) {
            activeCard.classList.add('selected-schedule');
        }

        // Fill form fields
        document.getElementById('subjectField').value = sch.subject_name || '';
        document.getElementById('classField').value = sch.class_name || '';
        document.getElementById('expertiseField').value = sch.department_name || '';
        document.getElementById('timeField').value = sch.time_formatted || '';

        // Show smart date helper for this day
        setupDateHelper(sch.day);
    }

    function clearScheduleSelection() {
        currentSelectedSchedule = null;
        document.querySelectorAll('.schedule-card-item').forEach(el => el.classList.remove('selected-schedule'));
        document.getElementById('smartDateHelper').style.display = 'none';
        document.getElementById('dayMatchStatus').innerHTML = '';
    }

    function setupDateHelper(dayName) {
        const targetDayIdx = dayNameToIndex[dayName];
        if (targetDayIdx === undefined) {
            document.getElementById('smartDateHelper').style.display = 'none';
            return;
        }

        const helperContainer = document.getElementById('smartDateHelper');
        document.getElementById('targetDayName').innerText = dayName;
        document.getElementById('targetDayName2').innerText = dayName;
        helperContainer.style.display = 'block';

        // Calculate 4 upcoming dates that fall on this day
        const btnContainer = document.getElementById('quickDateButtons');
        btnContainer.innerHTML = '';

        const today = new Date();
        const upcomingDates = [];

        for (let i = 0; upcomingDates.length < 4 && i < 35; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            if (d.getDay() === targetDayIdx) {
                upcomingDates.push(new Date(d));
            }
        }

        upcomingDates.forEach(dateObj => {
            const yyyy = dateObj.getFullYear();
            const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
            const dd = String(dateObj.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;

            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            const labelFormatted = dateObj.toLocaleDateString('id-ID', options);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn quick-date-btn shadow-sm';
            btn.innerHTML = `<i class="ti ti-calendar me-1"></i> ${dayName}, ${labelFormatted}`;
            btn.onclick = () => {
                document.querySelectorAll('.quick-date-btn').forEach(b => b.classList.remove('selected-date'));
                btn.classList.add('selected-date');
                document.getElementById('scheduleDateField').value = dateStr;
                validateDayMatch();
            };
            btnContainer.appendChild(btn);
        });

        validateDayMatch();
    }

    function validateDayMatch() {
        if (!currentSelectedSchedule) {
            document.getElementById('dayMatchStatus').innerHTML = '';
            return;
        }

        const dateVal = document.getElementById('scheduleDateField').value;
        if (!dateVal) {
            document.getElementById('dayMatchStatus').innerHTML = '';
            return;
        }

        const parts = dateVal.split('-');
        if (parts.length !== 3) return;

        // Note: Months in JS Date are 0-indexed
        const selectedDate = new Date(parts[0], parts[1] - 1, parts[2]);
        const selectedDayIdx = selectedDate.getDay();
        const selectedDayName = indexToDayName[selectedDayIdx];

        const targetDayName = currentSelectedSchedule.day;
        const targetDayIdx = dayNameToIndex[targetDayName];

        const statusEl = document.getElementById('dayMatchStatus');

        if (selectedDayIdx === targetDayIdx) {
            statusEl.innerHTML = `<span class="badge bg-success-lt text-success fw-bold border border-success"><i class="ti ti-check me-1"></i> Sesuai Hari ${targetDayName}</span>`;
        } else {
            statusEl.innerHTML = `<span class="badge bg-warning-lt text-warning fw-bold border border-warning" title="Jadwal mengajar aslinya adalah hari ${targetDayName}"><i class="ti ti-alert-triangle me-1"></i> Tanggal ini hari ${selectedDayName} (Bukan ${targetDayName})</span>`;
        }
    }

    // Auto-trigger on page load if teacher already selected
    window.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('teacherSelect');
        if (select.value) {
            onTeacherChange();
        }
    });
</script>
@endsection
