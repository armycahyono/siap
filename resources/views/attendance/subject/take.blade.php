@extends('layouts.app')

@section('title', 'Lembar Absen Kelas — ' . $classroom->name)

@section('content')
<form action="{{ route('attendance.subject.store') }}" method="POST">
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

    <!-- Header & Action Buttons -->
    <div class="row g-2 align-items-center mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('attendance.subject.index', ['date' => $date->toDateString()]) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                    <i class="ti ti-arrow-left"></i>
                </a>
                <h2 class="page-title fw-bold text-dark mb-0">
                    Lembar Presensi Kelas: {{ $classroom->name }}
                </h2>
                @if($isSwap)
                    <span class="badge bg-warning text-dark px-2 py-1">
                        <i class="ti ti-arrows-left-right me-1"></i> Mode Tukar Jam / Guru Pengganti
                    </span>
                @endif
            </div>
            <div class="text-muted small">
                Mata Pelajaran: <strong>{{ $subject->name }}</strong> &bull;
                Jam Ke-{{ $startPeriod }} s/d {{ $endPeriod }} &bull;
                Tanggal: <strong>{{ $date->translatedFormat('l, d F Y') }}</strong>
            </div>
        </div>
        <div class="col-auto ms-auto d-flex gap-2 mt-2 mt-md-0">
            <button type="submit" class="btn btn-sm btn-primary shadow-sm px-3">
                <i class="ti ti-device-floppy me-1"></i> Simpan Presensi Siswa
            </button>
        </div>
    </div>

    <!-- Informasi Tukar Jam / Guru Pengganti (Jika Ada) -->
    @if($isSwap || ($scheduledTeacher && $scheduledTeacher->id !== auth()->id()))
        <div class="alert alert-warning border-0 shadow-sm mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="alert-title mb-1 fw-bold">
                        <i class="ti ti-alert-triangle me-1"></i> Sesi Mengajar Sebagai Guru Pengganti / Tukar Jam
                    </h4>
                    <div class="text-secondary small">
                        Guru Sesuai Jadwal: <strong>{{ $scheduledTeacher?->name ?? 'Belum Ditentukan' }}</strong>. Anda bertindak sebagai pengajar riil yang mengisi dan mengabsen kelas ini.
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small fw-bold mb-0">Jenis:</label>
                    <select name="swap_type" class="form-select form-select-sm w-auto">
                        <option value="tukar_jam" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'tukar_jam' ? 'selected' : '' }}>Tukar Jam Pelajaran</option>
                        <option value="guru_pengganti" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'guru_pengganti' ? 'selected' : '' }}>Guru Pengganti</option>
                        <option value="inval" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'inval' ? 'selected' : '' }}>Inval Meja Piket</option>
                        <option value="mandiri" {{ old('swap_type', $session?->swap_type ?? $swapType) == 'mandiri' ? 'selected' : '' }}>Tambahan / Mandiri</option>
                    </select>
                    @if($scheduledTeacher)
                        <input type="hidden" name="scheduled_teacher_id" value="{{ $scheduledTeacher->id }}">
                    @else
                        <select name="scheduled_teacher_id" class="form-select form-select-sm w-auto">
                            <option value="">-- Guru Asli --</option>
                            @foreach($allTeachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>
            <div class="mt-2">
                <input type="text" name="swap_notes" class="form-control form-control-sm" placeholder="Catatan tukar jam (contoh: Jam ke-3 tukar dengan jam ke-1 Bu Siti)..." value="{{ old('swap_notes', $session?->swap_notes) }}">
            </div>
        </div>
    @endif

    <!-- Informasi Dampak Agenda Sekolah dari Kalender Kurikulum (Jika Ada) -->
    @if(isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact !== 'normal')
        <div class="alert {{ $classroomEvent->learning_impact === 'libur_khusus' ? 'alert-danger' : ($classroomEvent->learning_impact === 'bebas_kbm_kegiatan' ? 'alert-purple' : ($classroomEvent->learning_impact === 'pembelajaran_daring' ? 'alert-info' : 'alert-warning')) }} border-0 shadow-sm mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="alert-title mb-1 fw-bold d-flex align-items-center">
                        <i class="ti ti-calendar-event me-2 fs-2"></i>
                        Agenda Sekolah: {{ $classroomEvent->title }}
                    </h4>
                    <div class="small">
                        Status Pembelajaran: <strong>{{ $classroomEvent->learning_impact_label }}</strong>.
                        @if($classroomEvent->learning_impact === 'bebas_kbm_kegiatan')
                            Siswa kelas ini berpartisipasi dalam agenda sekolah / PKL / Gladi dan gugur kewajiban tatap muka di kelas (Otomatis Dispensasi).
                        @elseif($classroomEvent->learning_impact === 'pembelajaran_daring')
                            Siswa kelas ini melaksanakan <strong>Pembelajaran Daring</strong> dari rumah. Guru mapel dapat memberikan materi/tugas daring.
                        @elseif($classroomEvent->learning_impact === 'pulang_cepat')
                            Siswa dipulangkan lebih awal{{ $classroomEvent->max_period ? ' setelah jam ke-' . $classroomEvent->max_period : '' }}{{ $classroomEvent->dismissal_time ? ' (Pukul ' . $classroomEvent->dismissal_time . ' WIB)' : '' }}.
                        @endif
                        @if($classroomEvent->teacher_instructions)
                            <div class="mt-1 fw-semibold"><i class="ti ti-info-circle me-1"></i> Petunjuk: {{ $classroomEvent->teacher_instructions }}</div>
                        @endif
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-white text-dark fw-bold px-3 py-2 fs-4 shadow-xs">
                        {{ $classroomEvent->learning_impact_label }}
                    </span>
                </div>
            </div>
        </div>
    @elseif(isset($disengagedEvent) && $disengagedEvent)
        <div class="alert {{ $disengagedEvent->disengaged_student_impact === 'libur_khusus' ? 'alert-danger' : ($disengagedEvent->disengaged_student_impact === 'pembelajaran_daring' ? 'alert-info' : 'alert-warning') }} border-0 shadow-sm mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="alert-title mb-1 fw-bold d-flex align-items-center">
                        <i class="ti ti-info-circle me-2 fs-2"></i>
                        Penyesuaian KBM Kelas: {{ $disengagedEvent->disengaged_student_impact_label }}
                    </h4>
                    <div class="small">
                        Terkait agenda sekolah <strong>{{ $disengagedEvent->title }}</strong>, siswa yang tidak terlibat ditetapkan <strong>{{ $disengagedEvent->disengaged_student_impact_label }}</strong>
                        @if($disengagedEvent->disengaged_student_impact === 'pulang_cepat')
                            (Selesai jam ke-{{ $disengagedEvent->disengaged_student_max_period ?? '-' }} / {{ $disengagedEvent->disengaged_student_dismissal_time ?? '-' }} WIB).
                        @elseif($disengagedEvent->disengaged_student_impact === 'pembelajaran_daring')
                            (Belajar daring dari rumah).
                        @endif
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-white text-dark fw-bold px-3 py-2 fs-4 shadow-xs">
                        {{ $disengagedEvent->disengaged_student_impact_label }}
                    </span>
                </div>
            </div>
        </div>
    @endif

    <!-- Jurnal & Pokok Bahasan Pembelajaran -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small text-dark mb-1">Materi / Pokok Bahasan Hari Ini (Jurnal)</label>
                    <input type="text" name="topic" class="form-control form-control-sm" placeholder="Contoh: Bab 3 - Persamaan Garis Lurus dan Latihan Soal..." value="{{ old('topic', $session?->topic ?? ($classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan' ? 'Kegiatan: ' . $classroomEvent->title : ($classroomEvent && $classroomEvent->learning_impact === 'pembelajaran_daring' ? 'Pembelajaran Daring: ' . $classroomEvent->title : ''))) }}">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small text-dark mb-1">Catatan Proses Pembelajaran / Kejadian di Kelas</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Siswa aktif berdiskusi kelompok..." value="{{ old('notes', $session?->notes ?? ($classroomEvent ? $classroomEvent->teacher_instructions : '')) }}">
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action: Tandai Semua Hadir -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="text-muted small">
            Daftar Siswa Kelas <strong>{{ $classroom->name }}</strong> (Total: {{ $students->count() }} Siswa)
        </div>
        <div class="btn-group btn-group-sm">
            @if(isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan')
                <button type="button" class="btn btn-outline-purple" id="btnSetAllDispen">
                    <i class="ti ti-sparkles me-1"></i> Tandai Semua Dispen ({{ $classroomEvent->title }})
                </button>
            @endif
            <button type="button" class="btn btn-outline-success" id="btnSetAllHadir">
                <i class="ti ti-check-all me-1"></i> Tandai Semua Hadir
            </button>
        </div>
    </div>

    <!-- Notifikasi Warisan Absen Sesi Terdahulu (Jika ada) -->
    @if(isset($earlierSession) && $earlierSession && !$session)
        <div class="alert bg-blue-lt border border-blue-subtle shadow-sm mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-history fs-2 text-primary"></i>
                <div>
                    <h4 class="alert-title mb-0.5 fw-bold text-primary">
                        Meneruskan Rekap Presensi Jam {{ $earlierSession->start_period }}-{{ $earlierSession->end_period }}
                    </h4>
                    <div class="small text-secondary">
                        Siswa yang berhalangan (Sakit, Izin, Alpa, atau Dispen) otomatis diteruskan dari jam pelajaran <strong>{{ $earlierSession->subject?->name }}</strong> (Guru: {{ $earlierSession->teacher?->name }}). Anda tetap dapat memperbarui status siswa jika ada perubahan kehadiran di jam Anda.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Tabel Lembar Absen Siswa -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="w-1 text-center">No</th>
                        <th>NISN / NIS</th>
                        <th>Nama Siswa</th>
                        <th class="text-center" style="width: 320px;">Kehadiran</th>
                        <th>Catatan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $idx => $student)
                        @php
                            $rec = $existingRecords->get($student->id);
                            $prevRec = $previousRecords->get($student->id);
                            $permit = $permits->get($student->id);
                            $scanLog = $machineLogs->get($student->id);

                            // Tentukan status default otomatis jika belum ada record tersimpan sebelumnya
                            $defaultStatus = 'h';
                            $defaultNotes = '';

                            // 1. Jika ada event bebas KBM (PKL / KI / Gladi), default otomatis Dispen (d)
                            if (isset($classroomEvent) && $classroomEvent && $classroomEvent->learning_impact === 'bebas_kbm_kegiatan') {
                                $defaultStatus = 'd';
                                $defaultNotes = $classroomEvent->title;
                            }

                            // 2. Teruskan status dari jam pelajaran sebelumnya hari ini di kelas yang sama
                            if ($prevRec && $prevRec->status !== 'h') {
                                $defaultStatus = $prevRec->status;
                                $defaultNotes = $prevRec->notes ?: ('Dari jam ' . $earlierSession->start_period . ': ' . $prevRec->status_label);
                            }

                            // 3. Timpa jika ada surat perizinan resmi piket
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
                                    if (!$affectedPeriod || $startPeriod >= $affectedPeriod) {
                                        $defaultStatus = 'i';
                                        $defaultNotes = 'Izin Pulang Cepat (JP ' . ($affectedPeriod ?? '-') . '): ' . $permit->reason;
                                    }
                                } elseif ($permit->permit_type === 'terlambat') {
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
                        <tr>
                            <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                            <td class="small text-muted">{{ $student->nisn ?? $student->nis ?? '-' }}</td>
                            <td>
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    <strong class="text-dark">{{ $student->name }}</strong>
                                    <span class="badge bg-secondary-lt" style="font-size: 0.7rem;">{{ $student->gender == 'L' ? 'L' : 'P' }}</span>
                                    
                                    {{-- Badge dari Perizinan Piket / Wali Kelas --}}
                                    @if($permit)
                                        @if($permit->permit_type === 'terlambat')
                                            <span class="badge bg-warning-lt text-dark border border-warning" title="Izin Masuk Piket: {{ $permit->reason }} (Pukul {{ $permit->entry_time ?? '-' }})">
                                                <i class="ti ti-clock-exclamation me-1"></i> Terlambat ({{ $permit->entry_time ? \Illuminate\Support\Str::substr($permit->entry_time, 0, 5) : 'Piket' }})
                                            </span>
                                        @elseif($permit->permit_type === 'izin_keluar_sementara')
                                            <span class="badge bg-blue-lt border border-primary" title="Izin Keluar Sementara: {{ $permit->reason }} (Jam ke: {{ $permit->period_assigned ?? '-' }})">
                                                <i class="ti ti-door-exit me-1"></i> Izin Keluar (Jam {{ $permit->period_assigned ?? '-' }})
                                            </span>
                                        @elseif($permit->permit_type === 'izin_pulang_cepat')
                                            <span class="badge bg-danger-lt border border-danger" title="Izin Pulang Cepat: {{ $permit->reason }} (Pukul {{ $permit->departure_time ?? '-' }})">
                                                <i class="ti ti-home-up me-1"></i> Pulang Cepat ({{ $permit->departure_time ? \Illuminate\Support\Str::substr($permit->departure_time, 0, 5) : 'Piket' }})
                                            </span>
                                        @else
                                            <span class="badge {{ $permit->permit_category === 'sakit' ? 'bg-warning-lt border border-warning' : ($permit->permit_category === 'dispen' ? 'bg-purple-lt border border-purple' : 'bg-info-lt border border-info') }}" title="{{ $permit->permit_type_label }}: {{ $permit->reason }} (Pelapor: {{ ucfirst(str_replace('_', ' ', $permit->reporter_type ?? 'Piket')) }})">
                                                <i class="ti ti-note me-1"></i> {{ $permit->permit_type_label }}
                                                @if($permit->reporter_type === 'wali_kelas')
                                                    <span class="fw-normal">(Wali Kelas)</span>
                                                @endif
                                            </span>
                                        @endif
                                    @endif

                                    {{-- Badge dari Mesin Absensi --}}
                                    @if($scanLog)
                                        <span class="badge bg-success-lt text-success" title="Tap Mesin Absensi: {{ $scanLog->scanned_at->format('H:i:s') }}">
                                            <i class="ti ti-fingerprint me-1"></i> Tap: {{ $scanLog->scanned_at->format('H:i') }}
                                        </span>
                                    @else
                                        <span class="badge bg-muted-lt text-muted" title="Belum ada data tap mesin absensi pada hari ini">
                                            <i class="ti ti-fingerprint-off me-1"></i> Belum Tap
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group w-100" role="group">
                                    <!-- Hadir -->
                                    <input type="radio" class="btn-check radio-status-h" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_h" value="h" {{ $curStatus == 'h' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-success px-2 py-1" for="status_{{ $student->id }}_h" title="Hadir">Hadir</label>

                                    <!-- Terlambat (T) -->
                                    <input type="radio" class="btn-check" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_t" value="t" {{ $curStatus == 't' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-warning px-2 py-1" for="status_{{ $student->id }}_t" title="Terlambat Masuk Kelas">Telat</label>

                                    <!-- Sakit -->
                                    <input type="radio" class="btn-check" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_s" value="s" {{ $curStatus == 's' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-secondary px-2 py-1" for="status_{{ $student->id }}_s" title="Sakit" style="color: #d97706; border-color: #d97706;">Sakit</label>

                                    <!-- Izin -->
                                    <input type="radio" class="btn-check" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_i" value="i" {{ $curStatus == 'i' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-info px-2 py-1" for="status_{{ $student->id }}_i" title="Izin">Izin</label>

                                    <!-- Alpa -->
                                    <input type="radio" class="btn-check" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_a" value="a" {{ $curStatus == 'a' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-danger px-2 py-1" for="status_{{ $student->id }}_a" title="Alpa">Alpa</label>

                                    <!-- Dispen -->
                                    <input type="radio" class="btn-check radio-status-d" name="attendance[{{ $student->id }}][status]" id="status_{{ $student->id }}_d" value="d" {{ $curStatus == 'd' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-purple px-2 py-1" for="status_{{ $student->id }}_d" title="Dispensasi" style="color: #7c3aed; border-color: #7c3aed;">Dispen</label>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="attendance[{{ $student->id }}][notes]" class="form-control form-control-sm" placeholder="Catatan..." value="{{ $curNotes }}">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Tidak ada siswa terdaftar di rombel {{ $classroom->name }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light py-2 px-3 d-flex align-items-center justify-content-between">
            <span class="text-muted small">Pastikan semua siswa terverifikasi dengan benar sebelum menyimpan.</span>
            <button type="submit" class="btn btn-sm btn-primary shadow-sm px-4">
                <i class="ti ti-device-floppy me-1"></i> Simpan Presensi Siswa
            </button>
        </div>
    </div>

    <!-- Floating Action Button Simpan (Khusus Mobile) -->
    <div class="d-md-none position-fixed" style="bottom: calc(75px + env(safe-area-inset-bottom, 0px)); right: 1rem; z-index: 1035;">
        <button type="submit" class="btn btn-primary rounded-pill shadow-lg px-3 py-2 fw-bold d-flex align-items-center gap-2" style="box-shadow: 0 6px 18px rgba(25, 91, 158, 0.45) !important;">
            <i class="ti ti-device-floppy fs-2"></i>
            <span>Simpan Absen</span>
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var btnAllHadir = document.getElementById('btnSetAllHadir');
        if (btnAllHadir) {
            btnAllHadir.addEventListener('click', function() {
                var hadirs = document.querySelectorAll('.radio-status-h');
                hadirs.forEach(function(radio) {
                    radio.checked = true;
                });
            });
        }

        var btnAllDispen = document.getElementById('btnSetAllDispen');
        if (btnAllDispen) {
            btnAllDispen.addEventListener('click', function() {
                var dispens = document.querySelectorAll('.radio-status-d');
                dispens.forEach(function(radio) {
                    radio.checked = true;
                });
            });
        }
    });
</script>
@endpush
