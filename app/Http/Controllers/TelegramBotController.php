<?php

namespace App\Http\Controllers;

use App\Models\PiketSchedule;
use App\Models\PiketTelegramLog;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramBotController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();

        // Ambil riwayat log pengiriman telegram (dengan filter tipe jika ada)
        $targetType = $request->query('target_type');
        $logQuery = PiketTelegramLog::latest();
        if ($targetType) {
            $logQuery->where('target_type', $targetType);
        }
        $logs = $logQuery->paginate(15)->withQueryString();

        // Ambil daftar guru beserta Telegram Chat ID mereka
        $teachers = User::operationalPersonnel()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah']))
            ->orderBy('name')
            ->get();

        // Statistik Log
        $totalSent = PiketTelegramLog::where('status', 'sent')->count();
        $totalFailed = PiketTelegramLog::where('status', 'failed')->count();
        $totalTeachersWithId = User::whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->count();
        $webhookInfo = $this->telegramService->getWebhookInfo();

        return view('settings.telegram.index', compact('setting', 'logs', 'teachers', 'totalSent', 'totalFailed', 'totalTeachersWithId', 'targetType', 'webhookInfo'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_bot_username' => 'nullable|string|max:100',
            'telegram_discipline_chat_id' => 'nullable|string|max:100',
            'telegram_piket_chat_id' => 'nullable|string|max:100',
            'telegram_supervision_chat_id' => 'nullable|string|max:100',
            'telegram_activities_chat_id' => 'nullable|string|max:100',
            'telegram_teachers_chat_id' => 'nullable|string|max:100',
            'telegram_notifications_enabled' => 'nullable|boolean',
        ]);

        $setting = SchoolSetting::getSetting();
        $setting->update([
            'telegram_bot_token' => $validated['telegram_bot_token'] ?? null,
            'telegram_bot_username' => $validated['telegram_bot_username'] ?? null,
            'telegram_discipline_chat_id' => $validated['telegram_discipline_chat_id'] ?? null,
            'telegram_piket_chat_id' => $validated['telegram_piket_chat_id'] ?? null,
            'telegram_supervision_chat_id' => $validated['telegram_supervision_chat_id'] ?? null,
            'telegram_activities_chat_id' => $validated['telegram_activities_chat_id'] ?? null,
            'telegram_teachers_chat_id' => $validated['telegram_teachers_chat_id'] ?? null,
            'telegram_notifications_enabled' => $request->has('telegram_notifications_enabled'),
        ]);

        return back()->with('success', 'Konfigurasi Bot Telegram & Saluran Notifikasi Sekolah berhasil disimpan.');
    }

    public function updateTeacherChatId(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'telegram_chat_id' => 'nullable|string|max:100',
            'telegram_username' => 'nullable|string|max:100',
        ]);

        $teacher = User::findOrFail($validated['teacher_id']);
        $teacher->update([
            'telegram_chat_id' => $validated['telegram_chat_id'] ?: null,
            'telegram_username' => $validated['telegram_username'] ?: null,
        ]);

        return back()->with('success', "Chat ID Telegram untuk {$teacher->name} berhasil diperbarui.");
    }

    public function testSend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_chat_id' => 'required|string|max:100',
        ]);

        $result = $this->telegramService->testConnection($validated['test_chat_id']);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function sendBroadcast(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_channel' => 'required|string',
            'broadcast_title' => 'required|string|max:200',
            'broadcast_message' => 'required|string',
        ]);

        $setting = SchoolSetting::getSetting();
        $targetChatId = null;
        $channelName = 'Grup Sekolah';

        switch ($validated['target_channel']) {
            case 'discipline':
                $targetChatId = $setting->telegram_discipline_chat_id;
                $channelName = 'Grup Kedisiplinan & BK';
                break;
            case 'piket':
                $targetChatId = $setting->telegram_piket_chat_id;
                $channelName = 'Grup Tim Piket Sekolah';
                break;
            case 'supervision':
                $targetChatId = $setting->telegram_supervision_chat_id;
                $channelName = 'Grup Supervisi Guru';
                break;
            case 'activities':
                $targetChatId = $setting->telegram_activities_chat_id;
                $channelName = 'Grup Kegiatan Sekolah';
                break;
            case 'teachers':
                $targetChatId = $setting->telegram_teachers_chat_id;
                $channelName = 'Grup Dewan Guru';
                break;
            default:
                // Jika input langsung berupa chat_id
                $targetChatId = $validated['target_channel'];
                $channelName = 'Chat ID '.$targetChatId;
                break;
        }

        if (empty($targetChatId)) {
            return back()->with('error', "Saluran {$channelName} belum memiliki Chat ID yang dikonfigurasi.");
        }

        $senderName = auth()->user()?->name ?? 'Manajemen Sekolah';
        $success = $this->telegramService->sendBroadcastMessage(
            $targetChatId,
            $validated['broadcast_title'],
            $validated['broadcast_message'],
            $senderName
        );

        if ($success) {
            return back()->with('success', "Pesan siaran berhasil dikirim ke {$channelName}.");
        }

        return back()->with('error', "Gagal mengirim pesan siaran ke {$channelName}. Pastikan Bot aktif dan sudah bergabung ke grup.");
    }

    public function sendReminder(Request $request): RedirectResponse
    {
        $type = $request->input('type', 'tomorrow'); // 'tomorrow' or 'today'
        $isToday = $type === 'today';

        $targetDate = $isToday ? Carbon::today() : Carbon::tomorrow();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $targetDayName = $dayNames[$targetDate->dayOfWeekIso] ?? 'Senin';

        $setting = SchoolSetting::getSetting();
        $academicYear = session('active_academic_year', $setting->academic_year ?? '2026/2027');

        $schedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $targetDayName)
            ->where('is_active', true)
            ->with('teacher')
            ->get();

        if ($schedules->isEmpty()) {
            return back()->with('warning', "Tidak ada petugas piket yang terjadwal untuk hari {$targetDayName}.");
        }

        $sentCount = 0;
        foreach ($schedules as $sched) {
            if ($sched->teacher && $sched->teacher->telegram_chat_id) {
                $success = $this->telegramService->notifyPiketDutyReminder(
                    $sched->teacher,
                    $targetDayName,
                    $targetDate->format('d/m/Y'),
                    $isToday
                );
                if ($success) {
                    $sentCount++;
                }
            }
        }

        return back()->with('success', "Notifikasi pengingat piket ({$targetDayName}) berhasil dikirim ke {$sentCount} guru piket.");
    }

    public function sendSupervisionReminder(Request $request): RedirectResponse
    {
        $type = $request->input('type', 'tomorrow');
        $isToday = $type === 'today';
        $targetDate = $isToday ? Carbon::today()->format('Y-m-d') : Carbon::tomorrow()->format('Y-m-d');

        $supervisions = Supervision::where('schedule_date', $targetDate)
            ->whereIn('status', ['terjadwal', 'pra_observasi'])
            ->with(['teacher', 'supervisor'])
            ->get();

        if ($supervisions->isEmpty()) {
            $formatted = Carbon::parse($targetDate)->translatedFormat('l, d F Y');

            return back()->with('warning', "Tidak ada agenda supervisi pembelajaran yang terjadwal pada {$formatted}.");
        }

        $sentCount = 0;
        foreach ($supervisions as $spv) {
            $success = $this->telegramService->notifySupervisionSchedule($spv, $isToday);
            if ($success) {
                $sentCount++;
            }
        }

        return back()->with('success', "Pengingat supervisi berhasil dikirim untuk {$sentCount} agenda supervisi.");
    }

    public function setWebhook(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'webhook_url' => 'required|url',
        ]);

        $res = $this->telegramService->setWebhook($validated['webhook_url']);

        if ($res['success']) {
            return back()->with('success', $res['message']);
        }

        return back()->with('error', $res['message']);
    }

    public function deleteWebhook(): RedirectResponse
    {
        $res = $this->telegramService->deleteWebhook();

        if ($res['success']) {
            return back()->with('success', $res['message']);
        }

        return back()->with('error', $res['message']);
    }
}
