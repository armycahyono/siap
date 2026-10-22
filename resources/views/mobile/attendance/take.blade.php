@extends('layouts.mobile')

@section('title', 'Absensi KBM — ' . $classroom->name)
@section('page_title', 'Absensi KBM')
@section('back_url', route('attendance.subject.index', ['date' => $date->toDateString()]))

@section('content')
@php
    $timeRange = ($schedule && $schedule->start_time && $schedule->end_time)
        ? \Illuminate\Support\Carbon::parse($schedule->start_time)->format('H:i') . ' - ' . \Illuminate\Support\Carbon::parse($schedule->end_time)->format('H:i')
        : (\App\Models\LessonSchedule::getTimeForPeriod($startPeriod, $endPeriod)['start_time'] . ' - ' . \App\Models\LessonSchedule::getTimeForPeriod($startPeriod, $endPeriod)['end_time']);
    $homeroomTeacherName = $classroom->homeroomTeacher?->name ?? 'Belum Ditentukan';
    
    // Guru Mapel (Jadwal asli atau guru mapel terjadwal, gunakan nama lengkap beserta gelar)
    $originalTeacher = $scheduledTeacher ?? $schedule?->teacher ?? $session?->scheduledTeacher ?? null;
    $originalTeacherName = $originalTeacher?->name ?? 'Belum Ditentukan';
    
    // Guru Pengganti / Guru yang mengajar
    $substituteTeacherName = null;
    if ($activeInval && $activeInval->invalTeacher) {
        $substituteTeacherName = $activeInval->invalTeacher->name . ' (Piket)';
    } elseif ($session && $session->is_swap && $session->teacher) {
        $substituteTeacherName = $session->teacher->name . ' (' . ($session->swap_type ? ucwords(str_replace('_', ' ', $session->swap_type)) : 'Pengganti') . ')';
    } elseif ($isSwap && auth()->user()) {
        $substituteTeacherName = auth()->user()->name . ' (' . ($swapType ? ucwords(str_replace('_', ' ', $swapType)) : 'Pengganti') . ')';
    }
@endphp

<!-- Kartu Identitas KBM (Paling Atas - Biru Soft) -->
<div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #f0f7ff 0%, #e6f0fa 100%); border: 1.5px solid #bfdbfe !important;">
    <div class="card-body p-2 px-2.5">
        <div class="table-responsive">
            <table class="table table-borderless table-sm mb-0" style="font-size: 0.78rem; line-height: 1.25;">
                <tbody>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="width: 95px; font-weight: 500;">Kelas</td>
                        <td class="text-secondary py-0 px-0.5" style="width: 8px;">:</td>
                        <td class="fw-bold text-dark py-0 pe-0">{{ $classroom->name }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Wali kelas</td>
                        <td class="text-secondary py-0 px-0.5">:</td>
                        <td class="text-dark py-0 pe-0">{{ $homeroomTeacherName }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Tanggal</td>
                        <td class="text-secondary py-0 px-0.5">:</td>
                        <td class="text-dark py-0 pe-0">{{ $date->translatedFormat('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Jam Pelajaran</td>
                        <td class="text-secondary py-0 px-0.5">:</td>
                        <td class="text-dark py-0 pe-0">{{ $startPeriod }}-{{ $endPeriod }} ({{ $timeRange }})</td>
                    </tr>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Mapel</td>
                        <td class="text-secondary py-0 px-0.5">:</td>
                        <td class="fw-bold text-dark py-0 pe-0">{{ $subject->name }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Guru mapel</td>
                        <td class="text-secondary py-0 px-0.5">:</td>
                        <td class="text-dark py-0 pe-0">{{ $originalTeacherName }}</td>
                    </tr>
                    @if($substituteTeacherName)
                        <tr>
                            <td class="text-secondary text-nowrap py-0 ps-0 pe-1" style="font-weight: 500;">Guru ganti</td>
                            <td class="text-secondary py-0 px-0.5">:</td>
                            <td class="text-primary fw-semibold py-0 pe-0">{{ $substituteTeacherName }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<form action="{{ route('attendance.subject.store') }}" method="POST" id="formTakeAttendance" class="external">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <input type="hidden" name="classroom_id" value="{{ $classroom->id }}">
    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    <input type="hidden" name="start_period" value="{{ $startPeriod }}">
    <input type="hidden" name="end_period" value="{{ $endPeriod }}">
    @if($schedule)
        <input type="hidden" name="lesson_schedule_id" value="{{ $schedule->id }}">
    @endif
    <input type="hidden" name="is_swap" value="{{ $isSwap ? '1' : '0' }}">

    <!-- Sesi Info & Mode Tukar Jam (Hanya Tampil Jika Menggantikan Guru Lain) -->
    @if($isSwap || ($scheduledTeacher && $scheduledTeacher->id !== auth()->id()))
        <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: #fffbeb; border-left: 4px solid #f59e0b !important;">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ti ti-arrows-left-right text-warning fs-2"></i>
                    <div>
                        <div class="fw-bold text-dark fs-4">Guru Pengganti / Tukar Jam</div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Jadwal Asli: <strong>{{ $scheduledTeacher?->name ?? 'Belum Ditentukan' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <select name="swap_type" class="form-select form-select-sm" style="font-size: 0.75rem;">
                        <option value="tukar_jam" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'tukar_jam' ? 'selected' : '' }}>Tukar Jam Pelajaran</option>
                        <option value="guru_pengganti" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'guru_pengganti' ? 'selected' : '' }}>Guru Pengganti</option>
                        <option value="inval" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'inval' ? 'selected' : '' }}>Inval Meja Piket</option>
                        <option value="mandiri" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'mandiri' ? 'selected' : '' }}>Tambahan / Mandiri</option>
                    </select>
                    @if($scheduledTeacher)
                        <input type="hidden" name="scheduled_teacher_id" value="{{ $scheduledTeacher->id }}">
                    @endif
                </div>
                <input type="text" name="swap_notes" class="form-control form-control-sm mt-1" style="font-size: 0.75rem;" placeholder="Catatan tukar jam..." value="{{ old('swap_notes', $session?->swap_notes) }}">
            </div>
        </div>
    @endif

    <!-- Agenda Sekolah (Jika Berdampak) -->
    @if(isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact !== 'normal')
        <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: #fdf4ff; border-left: 4px solid #a855f7 !important;">
            <div class="card-body p-2 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-calendar-event text-purple fs-2"></i>
                    <div>
                        <div class="fw-bold text-dark fs-4">Agenda: {{ $classroomEvent->title }}</div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Status: <span class="badge bg-purple text-white">{{ $classroomEvent->learning_impact_short_label }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Jurnal KBM (Materi, Catatan & Tombol Simpan Absen) -->
    <div class="card border-0 shadow-sm rounded-3 mb-2">
        <div class="card-body p-2 px-3">
            <div class="d-flex align-items-center justify-content-between mb-1.5 pb-1 border-bottom" style="gap: 6px;">
                <div class="fw-bold text-dark d-flex align-items-center gap-1" style="font-size: 0.78rem; line-height: 1.2; white-space: nowrap;">
                    <i class="ti ti-notebook text-primary" style="font-size: 0.9rem;"></i>
                    <span>Jurnal KBM Hari Ini</span>
                </div>
                <button type="submit" id="btnTopSubmitAttendance" class="fw-bold border-0 bg-transparent p-0 text-decoration-none shadow-none text-end" style="font-size: 0.78rem; cursor: pointer; user-select: none; outline: none; border: none; background: transparent; color: #195b9e !important; white-space: nowrap;">
                    Simpan Absen
                </button>
            </div>
            <div class="mb-1.5">
                <input type="text" name="topic" class="form-control form-control-sm" style="font-size: 0.8rem;" placeholder="Materi / Topik Bahasan (opsional)..." value="{{ old('topic', $session?->topic ?? ($classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan' ? 'Kegiatan: ' . $classroomEvent->title : '')) }}">
            </div>
            <div>
                <input type="text" name="notes" class="form-control form-control-sm" style="font-size: 0.78rem;" placeholder="Catatan proses / kejadian kelas (opsional)..." value="{{ old('notes', $session?->notes ?? '') }}">
            </div>
        </div>
    </div>

    <!-- Kartu Rekap Absen Siswa (Biru Muda Soft & Counter Kotak Berwarna) -->
    <div class="card border-0 shadow-sm rounded-3 mb-2" style="background: linear-gradient(135deg, #f0f7ff 0%, #e8f3fc 100%); border: 1.5px solid #bfdbfe !important;">
        <div class="card-body p-2 px-2.5">
            <div class="d-flex align-items-center justify-content-between mb-1.5 px-0.5">
                <span class="text-dark small fw-bold" style="font-size: 0.76rem;">
                    <i class="ti ti-users text-primary me-0.5"></i> Rekap absen (<strong>{{ $students->count() }}</strong> siswa)
                </span>
                @if(isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan')
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0.5 text-nowrap" id="btnSetAllDispen" style="font-size: 0.68rem; white-space: nowrap;">
                        Semua Dispen
                    </button>
                @endif
            </div>

            <!-- Strip Kotak Rekap Status (H, T, S, I, A, D) Sesuai Warna Tombol Absen -->
            <div class="d-flex align-items-center justify-content-between gap-1 w-100">
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #16a34a; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">H</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-h">0</div>
                </div>
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #ca8a04; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">T</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-t">0</div>
                </div>
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #d97706; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">S</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-s">0</div>
                </div>
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #0284c7; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">I</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-i">0</div>
                </div>
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #dc2626; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">A</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-a">0</div>
                </div>
                <div class="rekap-box flex-fill text-center rounded-2 py-1" style="background: #7c3aed; color: #ffffff; min-width: 0;">
                    <div style="font-size: 0.65rem; font-weight: 700; line-height: 1;">D</div>
                    <div class="fw-bold fs-4 lh-1 mt-0.5" id="count-status-d">0</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifikasi Warisan Absen Sesi Terdahulu (Jika ada) -->
    @if(isset($earlierSession) && $earlierSession && !$session)
        <div class="alert bg-blue-lt border border-blue-subtle rounded-3 p-2 px-2.5 mb-2 shadow-xs" style="font-size: 0.73rem; line-height: 1.35;">
            <div class="d-flex align-items-center gap-1.5 fw-bold text-primary mb-0.5">
                <i class="ti ti-history fs-2"></i>
                <span>Meneruskan Absen Jam {{ $earlierSession->start_period }}-{{ $earlierSession->end_period }}</span>
            </div>
            <div class="text-secondary">
                Data ketidakhadiran (S/I/A/D) otomatis diambil dari mapel <strong>{{ $earlierSession->subject?->name }}</strong> ({{ $earlierSession->teacher?->name }}). Anda dapat mengubahnya jika ada siswa yang baru datang/berubah status.
            </div>
        </div>
    @endif

    <!-- Daftar Siswa (Framework7 Mobile Cards) -->
    <div class="mobile-student-list mb-4">
        @forelse($students as $idx => $student)
            @php
                $rec = $existingRecords->get($student->id);
                $prevRec = $previousRecords->get($student->id);
                $permit = $permits->get($student->id);
                $scanLog = $machineLogs->get($student->id);

                // Default status
                $defaultStatus = 'h';
                $defaultNotes = '';

                // 1. Jika ada event bebas KBM (PKL / Gladi)
                if (isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan') {
                    $defaultStatus = 'd';
                    $defaultNotes = $classroomEvent->title;
                }

                // 2. Teruskan status dari jam pelajaran sebelumnya hari ini di kelas yang sama
                if ($prevRec && $prevRec->status !== 'h') {
                    $defaultStatus = $prevRec->status;
                    $defaultNotes = $prevRec->notes ?: ('Dari jam ' . $earlierSession->start_period . ': ' . $prevRec->status_label);
                }

                // 3. Timpa jika ada surat izin resmi piket
                if ($permit) {
                    $affectedPeriod = $permit->getAffectedPeriod();

                    if ($permit->permit_type === 'izin_tidak_masuk') {
                        $defaultStatus = match ($permit->permit_category) {
                            'sakit' => 's',
                            'dispen' => 'd',
                            default => 'i',
                        };
                        $defaultNotes = $permit->reason;
                    } elseif ($permit->permit_type === 'izin_pulang_cepat') {
                        // Jika jam KBM ini dimulai setelah atau sama dengan jam siswa izin pulang
                        if (!$affectedPeriod || $startPeriod >= $affectedPeriod) {
                            $defaultStatus = 'i';
                            $defaultNotes = 'Izin Pulang Cepat (JP ' . ($affectedPeriod ?? '-') . '): ' . $permit->reason;
                        }
                    } elseif ($permit->permit_type === 'terlambat') {
                        // Jika jam KBM ini sebelum jam masuk siswa terlambat
                        if ($affectedPeriod && $endPeriod < $affectedPeriod) {
                            $defaultStatus = 'i';
                            $defaultNotes = 'Terlambat (masuk JP ' . $affectedPeriod . '): ' . $permit->reason;
                        } else {
                            $defaultStatus = 'h';
                            $defaultNotes = 'Hadir Terlambat: ' . $permit->reason;
                        }
                    } elseif ($permit->permit_type === 'izin_keluar_sementara') {
                        if ($affectedPeriod && $startPeriod <= $affectedPeriod && $endPeriod >= $affectedPeriod) {
                            $defaultStatus = 'd';
                            $defaultNotes = 'Izin Keluar: ' . $permit->reason;
                        }
                    }
                }

                $curStatus = old("attendance.{$student->id}.status", $rec?->status ?? $defaultStatus);
                $curNotes = old("attendance.{$student->id}.notes", $rec?->notes ?? $defaultNotes);
            @endphp

            <div class="card border-0 shadow-sm rounded-3 mb-1.5 student-card" id="student_card_{{ $student->id }}">
                <div class="card-body p-2 px-2.5">
                    <!-- Baris Atas: Nama, No, Info Tap & Izin -->
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                            <span class="avatar avatar-sm rounded-circle flex-shrink-0 fw-bold {{ $student->gender == 'L' ? 'bg-blue-lt text-primary' : 'bg-pink-lt text-danger' }}" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                {{ $idx + 1 }}
                            </span>
                            <div class="overflow-hidden">
                                <div class="fw-bold text-dark fs-4 text-truncate lh-sm">{{ $student->name }}</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.68rem;">
                                    {{ $student->nisn ?? $student->nis ?? '-' }} &bull; {{ $student->gender == 'L' ? 'L' : 'P' }}
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge Tap / Izin -->
                        <div class="flex-shrink-0 ms-2 text-end">
                            @if($permit)
                                <span class="badge {{ $permit->permit_category === 'sakit' ? 'bg-warning-lt' : ($permit->permit_category === 'dispen' ? 'bg-purple-lt' : 'bg-info-lt') }}" style="font-size: 0.62rem; padding: 2px 5px;">
                                    {{ $permit->permit_type_label }}
                                </span>
                            @elseif($scanLog)
                                <span class="badge bg-success-lt" style="font-size: 0.62rem; padding: 2px 5px;">
                                    Tap {{ $scanLog->scanned_at->format('H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Baris Tengah: 6 Touch Toggle Status Buttons -->
                    <div class="status-btn-group d-flex gap-1 mb-1">
                        <!-- Hadir (H) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="h" class="radio-status radio-status-h d-none" {{ $curStatus == 'h' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-h">H</span>
                        </label>

                        <!-- Terlambat (T) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="t" class="radio-status radio-status-t d-none" {{ $curStatus == 't' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-t">T</span>
                        </label>

                        <!-- Sakit (S) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="s" class="radio-status radio-status-s d-none" {{ $curStatus == 's' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-s">S</span>
                        </label>

                        <!-- Izin (I) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="i" class="radio-status radio-status-i d-none" {{ $curStatus == 'i' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-i">I</span>
                        </label>

                        <!-- Alpa (A) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="a" class="radio-status radio-status-a d-none" {{ $curStatus == 'a' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-a">A</span>
                        </label>

                        <!-- Dispen (D) -->
                        <label class="status-option flex-fill text-center">
                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="d" class="radio-status radio-status-d d-none" {{ $curStatus == 'd' ? 'checked' : '' }}>
                            <span class="btn-status-pill status-d">D</span>
                        </label>
                    </div>

                    <!-- Baris Bawah: Catatan Individual (Opsional) -->
                    <div class="mt-1">
                        <input type="text" name="attendance[{{ $student->id }}][notes]" class="form-control form-control-sm student-note-input" style="font-size: 0.72rem; padding: 2px 6px;" placeholder="Catatan per siswa..." value="{{ $curNotes }}">
                    </div>
                </div>
            </div>
        @empty
            <div class="card border-0 shadow-sm rounded-3 py-4 text-center text-muted">
                <i class="ti ti-users-minus fs-1 d-block mb-1 opacity-50"></i>
                <div class="small fw-semibold">Tidak ada siswa terdaftar di rombel {{ $classroom->name }}.</div>
            </div>
        @endforelse
    </div>

    <!-- Tombol Simpan Presensi Kelas (Alur Alami Tanpa Melayang Menutupi Siswa) -->
    <div class="d-flex justify-content-center pt-2 pb-4 mb-3">
        <button type="submit" id="btnBottomSubmitAttendance" class="btn btn-primary px-4 py-2.5 fw-bold rounded-pill shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="font-size: 0.92rem; min-width: 220px; max-width: 90%;">
            <i class="ti ti-device-floppy fs-2"></i>
            <span>Simpan Presensi Kelas</span>
        </button>
    </div>
</form>

<style>
    /* Styling Touch Status Pills */
    .btn-status-pill {
        display: block;
        padding: 6px 0;
        font-size: 0.82rem;
        font-weight: 700;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        transition: all 0.15s ease;
        user-select: none;
    }
    .status-option input:checked + .status-h {
        background: #16a34a !important;
        border-color: #16a34a !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35);
    }
    .status-option input:checked + .status-t {
        background: #ca8a04 !important;
        border-color: #ca8a04 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(202, 138, 4, 0.35);
    }
    .status-option input:checked + .status-s {
        background: #d97706 !important;
        border-color: #d97706 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.35);
    }
    .status-option input:checked + .status-i {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35);
    }
    .status-option input:checked + .status-a {
        background: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
    }
    .status-option input:checked + .status-d {
        background: #7c3aed !important;
        border-color: #7c3aed !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(124, 58, 237, 0.35);
    }
    .bg-pink-lt { background: #fdf2f8 !important; color: #db2777 !important; }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        function updateAttendanceCounters() {
            var counts = { h: 0, t: 0, s: 0, i: 0, a: 0, d: 0 };
            var checkedRadios = document.querySelectorAll('.radio-status:checked');
            checkedRadios.forEach(function(radio) {
                var val = (radio.value || '').toLowerCase();
                if (counts.hasOwnProperty(val)) {
                    counts[val]++;
                }
            });

            document.getElementById('count-status-h').textContent = counts.h;
            document.getElementById('count-status-t').textContent = counts.t;
            document.getElementById('count-status-s').textContent = counts.s;
            document.getElementById('count-status-i').textContent = counts.i;
            document.getElementById('count-status-a').textContent = counts.a;
            document.getElementById('count-status-d').textContent = counts.d;
        }

        // Jalankan kalkulasi saat halaman dibuka
        updateAttendanceCounters();

        // Jalankan kalkulasi setiap kali radio status siswa diubah
        var allRadios = document.querySelectorAll('.radio-status');
        allRadios.forEach(function(radio) {
            radio.addEventListener('change', updateAttendanceCounters);
        });

        var btnAllDispen = document.getElementById('btnSetAllDispen');
        if (btnAllDispen) {
            btnAllDispen.addEventListener('click', function() {
                var dispens = document.querySelectorAll('.radio-status-d');
                dispens.forEach(function(radio) {
                    radio.checked = true;
                });
                updateAttendanceCounters();
            });
        }

        var form = document.getElementById('formTakeAttendance');
        var topBtn = document.getElementById('btnTopSubmitAttendance');
        var bottomBtn = document.getElementById('btnBottomSubmitAttendance');

        if (form) {
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return false;
                }
                form.dataset.submitting = 'true';

                if (topBtn) {
                    topBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" style="width: 0.75rem; height: 0.75rem;"></span>Menyimpan...';
                    topBtn.style.pointerEvents = 'none';
                    topBtn.style.opacity = '0.7';
                }
                if (bottomBtn) {
                    bottomBtn.disabled = true;
                    bottomBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan Presensi...';
                }
            });
        }
    });
</script>
@endpush
