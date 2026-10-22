<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketDutySwap;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\SubjectAttendanceSession;
use App\Models\Supervision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Dapatkan semua notifikasi tugas & pemberitahuan aktif untuk user saat ini.
     */
    public static function getUserNotifications(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $notifications = collect();
        $now = Carbon::now();
        $currentTimeStr = $now->format('H:i');
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $todayDay = $today->locale('id')->isoFormat('dddd');
        $setting = SchoolSetting::getSetting();
        $academicYear = $setting->academic_year ?? '2026/2027';
        $semester = $setting->semester ?? 'Ganjil';

        // ---------------------------------------------------------------------
        // 0. DATA DASAR PERAN & KEANGGOTAAN HARI INI
        // ---------------------------------------------------------------------
        $isPrincipal = $user->isPrincipal() || $user->isSuperAdmin();
        $isWakaKurikulum = $user->isWakaKurikulum() || $user->isSuperAdmin();
        $isWakaKesiswaan = $user->isWakaKesiswaan() || $user->isSuperAdmin();
        $isDisciplineTeam = $user->isDisciplineTeam() || $user->isSuperAdmin();
        $isCounselingBK = $user->isCounselingBK() || $user->isSuperAdmin();

        // Cek apakah user adalah Petugas Piket atau Koordinator Piket hari ini
        $piketScheduleToday = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->where('teacher_id', $user->id)
            ->first();
        $isPiketOfficerToday = (bool) $piketScheduleToday || $user->isSuperAdmin();

        // Cari Jurnal Piket Hari ini untuk mendeteksi Koordinator Piket
        $todayJournal = PiketJournal::whereDate('date', $todayStr)->first();
        $isCoordinatorPiketToday = false;
        if ($todayJournal && $todayJournal->coordinator_id === $user->id) {
            $isCoordinatorPiketToday = true;
        } elseif ($piketScheduleToday && $piketScheduleToday->role === 'koordinator') {
            $isCoordinatorPiketToday = true;
        }

        // Cek apakah user adalah Wali Kelas
        $myHomeroomClassroomIds = Classroom::where('homeroom_teacher_id', $user->id)->pluck('id');
        $isHomeroomTeacher = $myHomeroomClassroomIds->isNotEmpty();

        // Kalender Kegiatan yang Berdampak Hari Ini
        $allTodayEvents = CurriculumEvent::with('users')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->when($academicYear, function ($q) use ($academicYear) {
                $q->where(function ($sub) use ($academicYear) {
                    $sub->where('academic_year', $academicYear)
                        ->orWhereNull('academic_year');
                });
            })
            ->get();

        try {
            // =================================================================
            // A. NOTIFIKASI KEJADIAN / KASUS SISWA & PERIZINAN MEJA PIKET
            // Sasaran Kejadian: Kepala Sekolah, Waka Kesiswaan, Tim Kedisiplinan, BK,
            //                   Wali Kelas, dan Seluruh Petugas Piket Hari Ini.
            // =================================================================

            // A1. Kasus Siswa Hari Ini
            $canSeeAllIncidents = $isPrincipal || $isWakaKesiswaan || $isDisciplineTeam || $isCounselingBK || $isPiketOfficerToday;

            if ($canSeeAllIncidents) {
                $incidentsTodayCount = PiketIncident::whereDate('incident_date', $todayStr)->count();
                if ($incidentsTodayCount > 0) {
                    $notifications->push([
                        'id' => 'incidents_today_general',
                        'title' => 'Catatan Kasus / Kejadian Siswa',
                        'message' => "Tercatat {$incidentsTodayCount} kejadian siswa di meja piket hari ini.",
                        'badge' => 'Kasus Siswa',
                        'badge_class' => 'bg-danger-lt text-danger',
                        'icon' => 'ti ti-alert-triangle text-danger',
                        'url' => route('piket.incidents.index'),
                    ]);
                }
            } elseif ($isHomeroomTeacher) {
                // Untuk Wali Kelas: Kasus siswa yang terjadi di kelas bimbingannya
                $homeroomIncidentsCount = PiketIncident::whereDate('incident_date', $todayStr)
                    ->whereIn('classroom_id', $myHomeroomClassroomIds)
                    ->count();
                if ($homeroomIncidentsCount > 0) {
                    $notifications->push([
                        'id' => 'incidents_today_homeroom',
                        'title' => 'Kejadian Siswa Kelas Anda',
                        'message' => "Ada {$homeroomIncidentsCount} catatan kasus siswa kelas Anda di meja piket hari ini.",
                        'badge' => 'Wali Kelas',
                        'badge_class' => 'bg-danger-lt text-danger',
                        'icon' => 'ti ti-alert-triangle text-danger',
                        'url' => route('piket.incidents.index'),
                    ]);
                }
            }

            // A2. Siswa Terlambat / Izin Pulang Cepat
            // Sasaran: Tim Kedisiplinan & Wali Kelas
            if ($isDisciplineTeam || $isWakaKesiswaan || $isPrincipal) {
                $studentPermitsToday = PiketStudentPermit::whereDate('permit_date', $todayStr)
                    ->whereIn('permit_type', ['terlambat', 'izin_pulang_cepat'])
                    ->count();
                if ($studentPermitsToday > 0) {
                    $notifications->push([
                        'id' => 'disiplin_permits',
                        'title' => 'Keterlambatan & Izin Pulang Siswa',
                        'message' => "Tercatat {$studentPermitsToday} siswa izin terlambat / pulang cepat di meja piket hari ini.",
                        'badge' => 'Tata Tertib',
                        'badge_class' => 'bg-warning-lt text-warning',
                        'icon' => 'ti ti-clock-exclamation text-warning',
                        'url' => route('piket.permits.index'),
                    ]);
                }
            }

            if ($isHomeroomTeacher) {
                $homeroomPermitsToday = PiketStudentPermit::whereDate('permit_date', $todayStr)
                    ->whereIn('classroom_id', $myHomeroomClassroomIds)
                    ->whereIn('permit_type', ['terlambat', 'izin_pulang_cepat'])
                    ->count();
                if ($homeroomPermitsToday > 0) {
                    $notifications->push([
                        'id' => 'homeroom_student_permits',
                        'title' => 'Siswa Kelas Anda Terlambat / Pulang Cepat',
                        'message' => "Ada {$homeroomPermitsToday} siswa di kelas Anda izin terlambat atau pulang cepat hari ini.",
                        'badge' => 'Izin Kelas',
                        'badge_class' => 'bg-warning-lt text-warning',
                        'icon' => 'ti ti-clock-exclamation text-warning',
                        'url' => route('piket.permits.index'),
                    ]);
                }
            }

            // =================================================================
            // B. KEGIATAN SEKOLAH & KEGIATAN BEBAS KBM (H-1 & AWAL HARI H)
            // =================================================================
            $tomorrowStr = Carbon::tomorrow()->toDateString();

            // Ambil semua agenda yang mulai H-1 (besok) atau hari H (hari ini)
            $relevantEvents = CurriculumEvent::with('users')
                ->where(function ($q) use ($todayStr, $tomorrowStr) {
                    $q->whereDate('start_date', $todayStr)
                        ->orWhereDate('start_date', $tomorrowStr);
                })
                ->when($academicYear, function ($q) use ($academicYear) {
                    $q->where(function ($sub) use ($academicYear) {
                        $sub->where('academic_year', $academicYear)
                            ->orWhereNull('academic_year');
                    });
                })
                ->get();

            // Jadwal mengajar user jika guru
            $myTeachingSchedules = LessonSchedule::with('classroom')->where('teacher_id', $user->id)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->when($semester, fn ($q) => $q->where('semester', $semester))
                ->get();

            foreach ($relevantEvents as $ev) {
                $isStartDateToday = ($ev->start_date?->toDateString() === $todayStr);
                $timingLabel = $isStartDateToday ? 'Hari Ini' : 'Besok (H-1)';

                // B1. Kegiatan Bebas KBM
                $isFreeKbm = (
                    in_array($ev->learning_impact, ['bebas_kbm_kegiatan', 'libur_khusus', 'pembelajaran_daring'])
                    || $ev->is_holiday
                    || $ev->teacher_impact === 'diliburkan_penuh'
                );

                if ($isFreeKbm) {
                    // Cek apakah guru mengajar rombel yang bebas KBM atau guru bertugas
                    $teacherAffected = $ev->affectsTeacher($user, $myTeachingSchedules);
                    if ($teacherAffected) {
                        $notifications->push([
                            'id' => 'free_kbm_event_'.$ev->id.'_'.$timingLabel,
                            'title' => "Bebas KBM ({$timingLabel}): {$ev->title}",
                            'message' => "Agenda \"{$ev->title}\" ({$timingLabel}) membebaskan tatap muka KBM kelas/tugas mengajar Anda.",
                            'badge' => $ev->student_impact_text ?: 'Bebas KBM',
                            'badge_class' => 'bg-purple-lt text-purple',
                            'icon' => 'ti ti-sparkles text-purple',
                            'url' => route('kurikulum.calendar.index'),
                        ]);
                    }
                } else {
                    // B2. Kegiatan Sekolah Umum lewat Kalender Kegiatan
                    // Sasaran: GTK yang terlibat / berdampak
                    if ($ev->affectsTeacher($user, $myTeachingSchedules)) {
                        $notifications->push([
                            'id' => 'general_event_'.$ev->id.'_'.$timingLabel,
                            'title' => "Agenda Sekolah ({$timingLabel}): {$ev->title}",
                            'message' => "Agenda \"{$ev->title}\" dijadwalkan {$timingLabel}. Pastikan kesiapan kegiatan.",
                            'badge' => $ev->category_label ?: 'Agenda',
                            'badge_class' => 'bg-blue-lt text-primary',
                            'icon' => 'ti ti-calendar-event text-primary',
                            'url' => route('kurikulum.calendar.index'),
                        ]);
                    }
                }
            }

            // =================================================================
            // C. MONITORING KBM & GURU BELUM ABSEN (REALTIME)
            // =================================================================
            $periodTimes = LessonSchedule::periodTimes($todayDay);

            // Ambil semua jadwal KBM aktif hari ini
            $todayAllSchedules = LessonSchedule::with(['classroom', 'teacher', 'subject'])
                ->where('day', $todayDay)
                ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                ->when($semester, fn ($q) => $q->where('semester', $semester))
                ->get();

            // Filter jadwal yang tidak bebas KBM / tidak libur
            $actionableAllSchedules = $todayAllSchedules->filter(function ($sched) use ($allTodayEvents) {
                $impact = $sched->getEventImpact($allTodayEvents);

                return ! ($impact && in_array($impact['type'], ['bebas_kbm', 'libur']));
            });

            // Sesi presensi yang sudah tersimpan hari ini (baik via lesson_schedule_id maupun mapping slot rombel & jam)
            $takenSessions = SubjectAttendanceSession::whereDate('attendance_date', $todayStr)->get();
            $takenSessionScheduleIds = $takenSessions->pluck('lesson_schedule_id')->filter()->unique();
            $takenSlotKeys = $takenSessions->map(fn ($s) => "{$s->classroom_id}_{$s->start_period}")->unique();

            // Identifikasi jadwal yang belum diabsen dan telah berjalan lebih dari 10 menit
            $schedulesLate10Min = collect();

            foreach ($actionableAllSchedules as $sched) {
                $isAttended = $takenSessionScheduleIds->contains($sched->id)
                    || $takenSlotKeys->contains("{$sched->classroom_id}_{$sched->start_period}");

                if ($isAttended) {
                    continue; // Sudah diabsen
                }

                $startTime = $sched->start_time;
                if (! $startTime && isset($periodTimes[$sched->start_period]['start'])) {
                    $startTime = $periodTimes[$sched->start_period]['start'];
                }

                if ($startTime) {
                    $startCarbon = Carbon::createFromTimeString($startTime);
                    $diffMinutes = $startCarbon->diffInMinutes($now, false);

                    // Presensi kelas belum selesai / guru belum absen:
                    // adalah presensi kelas yang sudah berjalan lebih dari 10 menit tapi belum di absen!
                    if ($diffMinutes > 10) {
                        $schedulesLate10Min->push($sched);
                    }
                }
            }

            // C1. Koordinator Piket: Monitoring KBM : Guru Belum Absen (>10 Menit)
            if ($isCoordinatorPiketToday || $user->isSuperAdmin()) {
                if ($schedulesLate10Min->isNotEmpty()) {
                    $firstLate = $schedulesLate10Min->first();
                    $teacherName = $firstLate->teacher?->formatted_short_name ?? $firstLate->teacher?->name ?? 'Guru';
                    $className = $firstLate->classroom?->name ?? '-';
                    $subjectName = $firstLate->subject?->name ?? 'Mapel';
                    $lateCount = $schedulesLate10Min->count();

                    $msg = $lateCount === 1
                        ? "Kelas {$className} mapel {$subjectName} ({$teacherName}) telah berjalan >10 menit tapi belum diabsen. Mohon pengecekan."
                        : "Ada {$lateCount} kelas telah berjalan >10 menit tapi belum diabsen (termasuk {$className} - {$teacherName}). Mohon pengecekan.";

                    $notifications->push([
                        'id' => 'piket_late_kbm_check',
                        'title' => 'Monitoring KBM : Guru Belum Absen',
                        'message' => $msg,
                        'badge' => 'Cek Kelas',
                        'badge_class' => 'bg-danger-lt text-danger',
                        'icon' => 'ti ti-urgent text-danger',
                        'url' => route('attendance.subject.index'),
                    ]);
                }
            }

            // C2. Waka Kurikulum: Monitoring KBM : Guru Belum Absen (>10 Menit)
            if ($isWakaKurikulum) {
                if ($schedulesLate10Min->isNotEmpty()) {
                    $distinctTeachersLate = $schedulesLate10Min->pluck('teacher_id')->unique()->count();
                    $totalSessionsLate = $schedulesLate10Min->count();
                    $notifications->push([
                        'id' => 'kur_late_kbm_recap',
                        'title' => 'Monitoring KBM : Guru Belum Absen',
                        'message' => "Saat ini ada {$distinctTeachersLate} guru ({$totalSessionsLate} sesi kelas) telah berjalan lebih 10 menit tapi belum diisi presensinya.",
                        'badge' => 'Monitoring KBM',
                        'badge_class' => 'bg-danger text-white',
                        'icon' => 'ti ti-alert-circle text-danger',
                        'url' => route('attendance.subject.index'),
                    ]);
                }
            }

            // C3. Guru Terkait: Presensi Kelas Belum Selesai (jadwal yang sudah berjalan lebih 10 menit tapi belum diabsen)
            $myTodaySchedules = $actionableAllSchedules->where('teacher_id', $user->id);
            if ($myTodaySchedules->isNotEmpty()) {
                $myLate10MinSchedules = $schedulesLate10Min->where('teacher_id', $user->id);
                if ($myLate10MinSchedules->isNotEmpty()) {
                    $firstMyLate = $myLate10MinSchedules->first();
                    $clsName = $firstMyLate->classroom?->name ?? 'Kelas';
                    $sbjName = $firstMyLate->subject?->name ?? 'Mapel';
                    $lateMyCount = $myLate10MinSchedules->count();

                    $msg = $lateMyCount === 1
                        ? "Jam pelajaran {$clsName} ({$sbjName}) telah berjalan lebih dari 10 menit tapi belum diabsen. Segera buka dan isi presensi kelas."
                        : "Ada {$lateMyCount} sesi kelas mengajar Anda telah berjalan lebih dari 10 menit tapi belum diisi presensinya.";

                    $notifications->push([
                        'id' => 'my_unattended_kbm',
                        'title' => 'Presensi Kelas Belum Selesai',
                        'message' => $msg,
                        'badge' => 'Belum Diabsen',
                        'badge_class' => 'bg-danger-lt text-danger',
                        'icon' => 'ti ti-clock-play text-danger',
                        'url' => route('attendance.subject.index'),
                    ]);
                }
            }

            // =================================================================
            // D. KELAS KOSONG (BUTUH INVAL)
            // Sasaran: Waka Kurikulum & Seluruh Petugas Piket Hari Ini
            // =================================================================
            $needInvalCount = PiketInvalClass::whereHas('journal', fn ($q) => $q->whereDate('date', $todayStr))
                ->whereNull('inval_teacher_id')
                ->count();

            if ($needInvalCount > 0 && ($isWakaKurikulum || $isPiketOfficerToday)) {
                $notifications->push([
                    'id' => 'need_inval_classes',
                    'title' => 'Inval Jam Kosong Butuh Guru Pengganti',
                    'message' => "Ada {$needInvalCount} jam pelajaran kosong hari ini yang belum ditetapkan guru pengganti (inval).",
                    'badge' => 'Inval KBM',
                    'badge_class' => 'bg-warning-lt text-warning',
                    'icon' => 'ti ti-user-x text-warning',
                    'url' => route('piket.inval'),
                ]);
            }

            // =================================================================
            // E. PERMINTAAN TUKAR JADWAL PIKET
            // - Sasaran: Guru pengganti (permohonan menunggu)
            // - Setelah disetujui: Notifikasi juga kepada Waka Kurikulum
            // =================================================================
            $swapRequestsCount = PiketDutySwap::where('replacement_teacher_id', $user->id)
                ->whereIn('status', ['menunggu', 'pending'])
                ->count();
            if ($swapRequestsCount > 0) {
                $notifications->push([
                    'id' => 'piket_swap_request',
                    'title' => 'Permintaan Tukar Jadwal Piket',
                    'message' => "Ada {$swapRequestsCount} rekan guru mengajukan tukar jadwal piket dengan Anda.",
                    'badge' => 'Tukar Piket',
                    'badge_class' => 'bg-purple-lt text-purple',
                    'icon' => 'ti ti-arrows-exchange text-purple',
                    'url' => route('piket.swaps.index'),
                ]);
            }

            if ($isWakaKurikulum) {
                $recentApprovedSwaps = PiketDutySwap::whereDate('updated_at', $todayStr)
                    ->where('status', 'disetujui')
                    ->count();
                if ($recentApprovedSwaps > 0) {
                    $notifications->push([
                        'id' => 'kur_approved_piket_swaps',
                        'title' => 'Pembaruan Jadwal Piket Disetujui',
                        'message' => "Ada {$recentApprovedSwaps} pertukaran jadwal piket resmi disetujui hari ini.",
                        'badge' => 'Jadwal Piket',
                        'badge_class' => 'bg-teal-lt text-teal',
                        'icon' => 'ti ti-arrows-exchange text-teal',
                        'url' => route('piket.swaps.index'),
                    ]);
                }
            }

            // =================================================================
            // F. NOTIFIKASI LAINNYA (PERSETUJUAN IZIN GTK & SUPERVISI)
            // =================================================================
            if ($isPrincipal) {
                $pendingGtkPermitsCount = GtkPermit::pending()->count();
                if ($pendingGtkPermitsCount > 0) {
                    $notifications->push([
                        'id' => 'ks_pending_gtk_permits',
                        'title' => 'Persetujuan Izin GTK',
                        'message' => "Ada {$pendingGtkPermitsCount} pengajuan izin/cuti GTK menunggu persetujuan Anda.",
                        'badge' => 'Menunggu',
                        'badge_class' => 'bg-warning-lt text-warning',
                        'icon' => 'ti ti-file-certificate text-warning',
                        'url' => DeviceDetector::isMobile() ? route('tasks') : route('attendance.gtk-permits.index', ['status' => 'pending']),
                    ]);
                }

                $unapprovedPiketJournalsCount = PiketJournal::whereDate('date', $todayStr)
                    ->where('status', 'draft')
                    ->count();
                if ($unapprovedPiketJournalsCount > 0) {
                    $notifications->push([
                        'id' => 'ks_piket_journal',
                        'title' => 'Jurnal Piket Hari Ini',
                        'message' => 'Jurnal piket harian sedang berjalan di meja piket.',
                        'badge' => 'Piket',
                        'badge_class' => 'bg-teal-lt text-teal',
                        'icon' => 'ti ti-clipboard-check text-teal',
                        'url' => route('piket.dashboard'),
                    ]);
                }
            }

            if ($isPrincipal || $isWakaKurikulum) {
                $gtkPermitsTodayCount = GtkPermit::approved()
                    ->whereDate('start_date', '<=', $todayStr)
                    ->whereDate('end_date', '>=', $todayStr)
                    ->count();
                if ($gtkPermitsTodayCount > 0) {
                    $notifications->push([
                        'id' => 'gtk_permits_today',
                        'title' => 'GTK Izin / Cuti Hari Ini',
                        'message' => "Ada {$gtkPermitsTodayCount} GTK sedang izin/cuti/dinas luar hari ini.",
                        'badge' => 'Izin GTK',
                        'badge_class' => 'bg-purple-lt text-purple',
                        'icon' => 'ti ti-file-certificate text-purple',
                        'url' => DeviceDetector::isMobile() ? route('tasks') : route('attendance.gtk-permits.index'),
                    ]);
                }

                $pendingSupervisionsCount = Supervision::where('status', 'menunggu_persetujuan')->count();
                if ($pendingSupervisionsCount > 0) {
                    $notifications->push([
                        'id' => 'supervision_approval',
                        'title' => 'Pengesahan Supervisi',
                        'message' => "Ada {$pendingSupervisionsCount} berkas supervisi menunggu pengesahan KS / Kurikulum.",
                        'badge' => 'Supervisi',
                        'badge_class' => 'bg-blue-lt text-primary',
                        'icon' => 'ti ti-certificate text-primary',
                        'url' => DeviceDetector::isMobile() ? route('tasks') : route('supervisions.results'),
                    ]);
                }
            }

        } catch (\Throwable $e) {
            // Fail safely
        }

        return $notifications;
    }
}
