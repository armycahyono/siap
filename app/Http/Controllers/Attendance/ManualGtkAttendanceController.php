<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Piket\PiketJournalController;
use App\Models\AttendanceLog;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DeviceDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManualGtkAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        if (! Auth::user()->canManageManualTeacherAttendance()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencatat presensi GTK susulan.');
        }

        if (DeviceDetector::isMobile($request)) {
            abort(403, 'Presensi susulan GTK hanya dapat diakses melalui versi desktop/komputer.');
        }

        $date = $request->query('date', now()->toDateString());
        try {
            $targetCarbon = Carbon::parse($date);
        } catch (\Throwable) {
            $targetCarbon = Carbon::today();
            $date = $targetCarbon->toDateString();
        }

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $targetDay = $dayNames[$targetCarbon->dayOfWeekIso] ?? 'Senin';

        // Guru yang memiliki jadwal KBM & Piket pada hari tersebut
        $scheduledTeacherIds = LessonSchedule::where('day', $targetDay)->pluck('teacher_id')->unique()->toArray();
        $piketTeacherIds = PiketSchedule::where('day', $targetDay)->where('is_active', true)->pluck('teacher_id')->unique()->toArray();

        // Seluruh GTK aktif
        $teachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        // Log scan pada tanggal tersebut
        $existingLogs = AttendanceLog::where('user_type', 'teacher')
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('user_id');

        // Izin GTK yang disetujui pada tanggal tersebut
        $gtkPermits = GtkPermit::approved()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->keyBy('user_id');

        // Riwayat presensi manual susulan terbaru (maks 20)
        $recentManualLogs = AttendanceLog::where('device_sn', 'MANUAL_SUSULAN')
            ->with('user')
            ->latest('id')
            ->take(20)
            ->get();

        return view('attendance.manual_gtk.index', compact(
            'date',
            'targetDay',
            'targetCarbon',
            'teachers',
            'scheduledTeacherIds',
            'piketTeacherIds',
            'existingLogs',
            'gtkPermits',
            'recentManualLogs'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Auth::user()->canManageManualTeacherAttendance()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mencatat presensi GTK susulan.');
        }

        if (DeviceDetector::isMobile($request)) {
            abort(403, 'Presensi susulan GTK hanya dapat diakses melalui versi desktop/komputer.');
        }

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'exists:users,id',
            'check_in_time' => 'required|string',
            'check_out_time' => 'nullable|string',
            'reason' => 'required|string|max:255',
            'overwrite_existing' => 'nullable|boolean',
        ]);

        $dateStr = $validated['date'];
        $checkInTime = $validated['check_in_time'];
        $checkOutTime = $validated['check_out_time'] ?: null;
        $reason = $validated['reason'];
        $overwrite = ! empty($validated['overwrite_existing']);

        $targetDate = Carbon::parse($dateStr);
        $inDateTime = Carbon::parse($dateStr.' '.$checkInTime.':00');
        $outDateTime = $checkOutTime ? Carbon::parse($dateStr.' '.$checkOutTime.':00') : null;

        $teachers = User::whereIn('id', $validated['teacher_ids'])->get();
        $processedCount = 0;

        DB::transaction(function () use ($teachers, $dateStr, $inDateTime, $outDateTime, $reason, $overwrite, &$processedCount) {
            foreach ($teachers as $teacher) {
                $pin = $teacher->nigs ?: (string) $teacher->id;
                $rawPayload = [
                    'source' => 'MANUAL_SUSULAN',
                    'reason' => $reason,
                    'inputted_by_id' => Auth::id(),
                    'inputted_by_name' => Auth::user()->name,
                    'inputted_at' => now()->toDateTimeString(),
                ];

                if ($overwrite) {
                    AttendanceLog::where('user_id', $teacher->id)
                        ->whereDate('scanned_at', $dateStr)
                        ->where('device_sn', 'MANUAL_SUSULAN')
                        ->delete();
                }

                $hasCheckIn = AttendanceLog::where('user_id', $teacher->id)
                    ->whereDate('scanned_at', $dateStr)
                    ->where('status', 'check_in')
                    ->exists();

                if (! $hasCheckIn || $overwrite) {
                    AttendanceLog::create([
                        'device_sn' => 'MANUAL_SUSULAN',
                        'pin' => $pin,
                        'user_type' => 'teacher',
                        'user_id' => $teacher->id,
                        'name' => $teacher->name,
                        'scanned_at' => $inDateTime,
                        'status' => 'check_in',
                        'verify_type' => 'manual',
                        'work_code' => 'FORCE_MAJEURE',
                        'raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES),
                    ]);
                }

                if ($outDateTime) {
                    $hasCheckOut = AttendanceLog::where('user_id', $teacher->id)
                        ->whereDate('scanned_at', $dateStr)
                        ->where('status', 'check_out')
                        ->exists();

                    if (! $hasCheckOut || $overwrite) {
                        AttendanceLog::create([
                            'device_sn' => 'MANUAL_SUSULAN',
                            'pin' => $pin,
                            'user_type' => 'teacher',
                            'user_id' => $teacher->id,
                            'name' => $teacher->name,
                            'scanned_at' => $outDateTime,
                            'status' => 'check_out',
                            'verify_type' => 'manual',
                            'work_code' => 'FORCE_MAJEURE',
                            'raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                }

                $processedCount++;
            }

            // Sinkronkan jurnal piket jika ada pada tanggal tersebut
            $journal = PiketJournal::whereDate('date', $dateStr)->first();
            if ($journal) {
                app(PiketJournalController::class)->syncMachineAttendance($journal);
            }
        });

        ActivityLogger::log(
            'create',
            "Mencatat presensi susulan GTK untuk {$processedCount} guru pada tanggal {$dateStr} (Alasan: {$reason})",
            null,
            ['date' => $dateStr, 'count' => $processedCount, 'reason' => $reason]
        );

        return redirect()->route('attendance.manual-gtk.index', ['date' => $dateStr])
            ->with('success', "Presensi susulan untuk {$processedCount} guru/staf pada tanggal {$targetDate->translatedFormat('d F Y')} berhasil dicatat.");
    }

    public function destroy(AttendanceLog $log): RedirectResponse
    {
        if (! Auth::user()->canManageManualTeacherAttendance()) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        if ($log->device_sn !== 'MANUAL_SUSULAN') {
            return back()->with('error', 'Hanya data presensi susulan/manual yang dapat dihapus dari menu ini.');
        }

        $dateStr = Carbon::parse($log->scanned_at)->toDateString();
        $userName = $log->user?->name ?? $log->name;
        $log->delete();

        // Resinkronkan jurnal piket jika ada
        $journal = PiketJournal::whereDate('date', $dateStr)->first();
        if ($journal) {
            app(PiketJournalController::class)->syncMachineAttendance($journal);
        }

        ActivityLogger::log('delete', "Menghapus log presensi manual susulan untuk {$userName} pada tanggal {$dateStr}");

        return back()->with('success', "Log presensi susulan untuk {$userName} berhasil dihapus.");
    }
}
