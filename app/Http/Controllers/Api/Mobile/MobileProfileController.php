<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\GtkDecree;
use App\Models\LessonSchedule;
use App\Models\SubjectAttendanceSession;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileProfileController extends Controller
{
    /**
     * Get GTK profile, teaching assignments, structural roles, and active decrees for SIAP Mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        // 1. Mata Pelajaran yang diampu
        $userSubjects = LessonSchedule::where('teacher_id', $user->id)
            ->with('subject')
            ->get()
            ->pluck('subject.name')
            ->unique()
            ->filter()
            ->values();

        if ($userSubjects->isEmpty() && $user->subject) {
            $userSubjects = collect(explode(',', $user->subject))->map(fn ($s) => trim($s))->filter()->values();
        }

        // 2. Dokumen SK GTK aktif yang menaungi GTK ini
        $activeDecrees = $user->gtkDecrees()
            ->where('status', GtkDecree::STATUS_ACTIVE)
            ->latest('tmt_date')
            ->get()
            ->map(function ($decree) {
                return [
                    'id' => $decree->id,
                    'number' => $decree->decree_number,
                    'title' => $decree->title,
                    'type' => $decree->type_label ?? $decree->decree_type,
                    'tmt_date' => $decree->tmt_date?->format('d M Y'),
                    'end_date' => $decree->end_date ? $decree->end_date->format('d M Y') : 'Seterusnya / Definitif',
                    'signatory_name' => $decree->signatory_name,
                    'signatory_position' => $decree->signatory_position,
                    'has_pdf' => (bool) $decree->pdf_file_path,
                    'download_url' => $decree->pdf_file_path ? route('gtk-decrees.download', $decree) : null,
                ];
            });

        // 3. Penugasan struktural aktif
        $activeStructuralPositions = $user->structuralAssignments()
            ->with('structuralPosition')
            ->where('is_active', true)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'position_name' => $assignment->structuralPosition?->name ?? 'Tugas Tambahan',
                    'start_date' => $assignment->start_date?->format('d M Y'),
                    'end_date' => $assignment->end_date ? $assignment->end_date->format('d M Y') : null,
                ];
            });

        // 4. Kelas perwalian aktif
        $activeHomeroom = $user->homeroomClassrooms()
            ->where('is_active', true)
            ->get()
            ->map(function ($classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->grade_level ?? null,
                    'academic_year' => $classroom->academic_year ?? '-',
                ];
            });

        $avatarUrl = $user->avatar
            ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/'.$user->avatar))
            : asset('assets/images/avatar-default.png');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'short_name' => $user->short_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'nip' => $user->nip,
                    'nuptk' => $user->nuptk,
                    'nigs' => $user->nigs,
                    'nik' => $user->nik,
                    'position' => $user->position,
                    'gtk_status' => $user->gtk_status ?? 'GTY / Tetap',
                    'education_level' => $user->education_level,
                    'major' => $user->major,
                    'faculty' => $user->faculty,
                    'address' => $user->address,
                    'avatar_url' => $avatarUrl,
                ],
                'subjects' => $userSubjects,
                'structural_positions' => $activeStructuralPositions,
                'homeroom_classrooms' => $activeHomeroom,
                'active_decrees' => $activeDecrees,
                'activity_logs' => $this->getUserActivityLogs($user, 35),
            ],
        ]);
    }

    /**
     * Ambil log aktivitas pengguna (mendukung filter dan limit).
     */
    public function getActivityLogs(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Belum terautentikasi.',
            ], 401);
        }

        $limit = min((int) $request->input('limit', 40), 100);
        $logs = $this->getUserActivityLogs($user, $limit);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Mengumpulkan riwayat log aktivitas pengguna dari berbagai sumber data (Web & Mobile).
     */
    protected function getUserActivityLogs(User $user, int $limit = 40): array
    {
        $activities = collect();

        // 1. Ambil dari UserActivityLog
        $ualLogs = UserActivityLog::where('user_id', $user->id)
            ->latest()
            ->take($limit)
            ->get();

        $loggedAttIds = [];
        $loggedKbmIds = [];

        foreach ($ualLogs as $log) {
            $action = strtolower($log->action ?? '');
            $ua = $log->user_agent ?? '';
            $props = is_array($log->properties) ? $log->properties : [];
            $isMobile = str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone') || (($props['source'] ?? '') === 'Mobile');
            $channel = $isMobile ? 'Mobile' : 'Web';

            if ($log->subject_type === AttendanceLog::class && $log->subject_id) {
                $loggedAttIds[] = (int) $log->subject_id;
            }
            if ($log->subject_type === SubjectAttendanceSession::class && $log->subject_id) {
                $loggedKbmIds[] = (int) $log->subject_id;
            }

            $category = 'sistem';
            $categoryLabel = 'Sistem';
            $badgeColor = 'slate';
            $title = $log->action_label ?? ucfirst(str_replace('_', ' ', $action));

            if (str_contains($action, 'login') || str_contains($action, 'logout') || str_contains($action, 'profile') || str_contains($action, 'password')) {
                $category = 'akun';
                $categoryLabel = 'Akun';
                $badgeColor = str_contains($action, 'login') ? 'emerald' : (str_contains($action, 'profile') ? 'blue' : 'slate');
                if (str_contains($action, 'profile')) {
                    $title = 'Edit Data Profil';
                }
                if (str_contains($action, 'password')) {
                    $title = 'Ganti Kata Sandi';
                }
            } elseif (str_contains($action, 'attendance') || str_contains($action, 'presensi')) {
                $category = 'presensi';
                $categoryLabel = 'Presensi';
                $badgeColor = 'emerald';
                $title = 'Presensi Datang / Pulang';
            } elseif (str_contains($action, 'kbm')) {
                $category = 'kbm';
                $categoryLabel = 'Absen KBM';
                $badgeColor = 'indigo';
                $title = 'Presensi KBM Kelas';
            } elseif (str_contains($action, 'permit') || str_contains($action, 'izin')) {
                $category = 'tugas';
                $categoryLabel = 'Izin & Cuti';
                $badgeColor = 'amber';
                $title = str_contains($action, 'action') ? 'Tindak Lanjut Izin' : 'Pengajuan Izin';
            } elseif (str_contains($action, 'visitation')) {
                $category = 'tugas';
                $categoryLabel = 'Visitasi';
                $badgeColor = 'cyan';
                $title = 'Jadwal Visitasi';
            } elseif (str_contains($action, 'bk')) {
                $category = 'tugas';
                $categoryLabel = 'Layanan BK';
                $badgeColor = 'indigo';
                $title = 'Konseling BK';
            } elseif (str_contains($action, 'incident') || str_contains($action, 'piket')) {
                $category = 'tugas';
                $categoryLabel = 'Piket & Kejadian';
                $badgeColor = 'purple';
                $title = 'Laporan Kejadian';
            } elseif (str_contains($action, 'verify') || str_contains($action, 'approve')) {
                $category = 'verifikasi';
                $categoryLabel = 'Verifikasi';
                $badgeColor = 'teal';
                $title = 'Verifikasi Kedisiplinan';
            }

            $activities->push([
                'id' => 'ual_'.$log->id,
                'source_type' => 'user_activity_log',
                'action' => $action,
                'category' => $category,
                'category_label' => $categoryLabel,
                'badge_color' => $badgeColor,
                'title' => $title,
                'description' => $log->description,
                'channel' => $channel,
                'timestamp' => $log->created_at?->timestamp ?? 0,
                'date_formatted' => $log->created_at?->translatedFormat('d M Y, H:i').' WIB',
                'time_human' => $log->created_at ? Carbon::parse($log->created_at)->diffForHumans() : '-',
            ]);
        }

        // 2. Ambil dari AttendanceLog (Presensi Masuk & Pulang) yang belum ada di activities
        $attLogs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereNotIn('id', $loggedAttIds)
            ->latest('scanned_at')
            ->take($limit)
            ->get();

        foreach ($attLogs as $att) {
            $raw = json_decode($att->raw_payload ?? '{}', true) ?: [];
            $isMobile = ($raw['source'] ?? '') === 'PWA_MOBILE' || str_contains($raw['user_agent'] ?? '', 'Mobile');
            $channel = $isMobile ? 'Mobile' : 'Web';
            $status = strtolower($att->status ?? 'check_in');
            $isCheckIn = in_array($status, ['check_in', 'masuk', '1']);
            $label = $isCheckIn ? 'Presensi Masuk' : 'Presensi Pulang';
            $color = $isCheckIn ? 'emerald' : 'blue';
            $dist = isset($raw['distance_to_school_meters']) ? " (Jarak: {$raw['distance_to_school_meters']} m)" : '';
            $note = ! empty($raw['notes']) ? " • Catatan: {$raw['notes']}" : '';

            $activities->push([
                'id' => 'att_'.$att->id,
                'source_type' => 'attendance_log',
                'action' => $isCheckIn ? 'presensi_masuk' : 'presensi_pulang',
                'category' => 'presensi',
                'category_label' => 'Presensi GTK',
                'badge_color' => $color,
                'title' => $label.' ('.$att->scanned_at?->format('H:i').' WIB)',
                'description' => "Presensi geotagging {$label} berhasil{$dist}{$note}.",
                'channel' => $channel,
                'timestamp' => $att->scanned_at?->timestamp ?? 0,
                'date_formatted' => $att->scanned_at?->translatedFormat('d M Y, H:i').' WIB',
                'time_human' => $att->scanned_at ? Carbon::parse($att->scanned_at)->diffForHumans() : '-',
            ]);
        }

        // 3. Ambil dari SubjectAttendanceSession (Absen KBM) yang belum ada di activities
        $kbmSessions = SubjectAttendanceSession::where('teacher_id', $user->id)
            ->whereNotIn('id', $loggedKbmIds)
            ->with(['classroom', 'subject'])
            ->latest('created_at')
            ->take($limit)
            ->get();

        foreach ($kbmSessions as $sess) {
            $className = $sess->classroom?->name ?? 'Kelas';
            $subjectName = $sess->subject?->name ?? 'Mapel';
            $topic = $sess->topic ? " • Materi: {$sess->topic}" : '';

            $activities->push([
                'id' => 'kbm_'.$sess->id,
                'source_type' => 'subject_attendance',
                'action' => 'kbm_attendance',
                'category' => 'kbm',
                'category_label' => 'Absen KBM',
                'badge_color' => 'indigo',
                'title' => "Presensi KBM {$className} ({$sess->attendance_date?->format('d/m/Y')})",
                'description' => "Mengabsen KBM {$subjectName} (Jam ke-{$sess->start_period}-{$sess->end_period}){$topic}.",
                'channel' => 'Web',
                'timestamp' => $sess->created_at?->timestamp ?? 0,
                'date_formatted' => $sess->created_at?->translatedFormat('d M Y, H:i').' WIB',
                'time_human' => $sess->created_at ? Carbon::parse($sess->created_at)->diffForHumans() : '-',
            ]);
        }

        return $activities->sortByDesc('timestamp')->values()->take($limit)->all();
    }
}
