<?php

namespace App\Http\Controllers;

use App\Models\PiketSchedule;
use App\Models\PiketTelegramLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\TelegramChannel;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        // Saluran Grup Dinamis, Daftar Peran, & Definisi Perintah
        $channels = TelegramChannel::orderBy('id')->get();
        $availableRoles = Role::orderBy('name')->get();
        $commandDefinitions = TelegramChannel::getDefaultCommandDefinitions();
        $commandCategories = TelegramChannel::getCommandCategories();
        $commandPresets = TelegramChannel::getCommandPresets();

        return view('settings.telegram.index', compact(
            'setting',
            'logs',
            'teachers',
            'totalSent',
            'totalFailed',
            'totalTeachersWithId',
            'targetType',
            'webhookInfo',
            'channels',
            'availableRoles',
            'commandDefinitions',
            'commandCategories',
            'commandPresets'
        ));
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
        $dataToUpdate = [
            'telegram_bot_token' => $validated['telegram_bot_token'] ?? null,
            'telegram_bot_username' => $validated['telegram_bot_username'] ?? null,
            'telegram_notifications_enabled' => $request->has('telegram_notifications_enabled'),
        ];

        // Jika form mengirim legacy chat id
        foreach (['telegram_discipline_chat_id', 'telegram_piket_chat_id', 'telegram_supervision_chat_id', 'telegram_activities_chat_id', 'telegram_teachers_chat_id'] as $field) {
            if ($request->has($field)) {
                $dataToUpdate[$field] = $validated[$field] ?? null;
            }
        }

        $setting->update($dataToUpdate);

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

        $channel = TelegramChannel::findByCode($validated['target_channel']);
        if ($channel) {
            $targetChatId = $channel->chat_id;
            $channelName = $channel->name;
        } else {
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
                    $targetChatId = $validated['target_channel'];
                    $channelName = 'Chat ID '.$targetChatId;
                    break;
            }
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

        try {
            $academicYear = app(AcademicYearContext::class)->getOperationalYear()->name;
        } catch (\Throwable $e) {
            Log::warning('TelegramBotController sendReminder operational year fallback: '.$e->getMessage());
            $academicYear = SchoolSetting::getMasterYear();
        }

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

    public function storeChannel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50|unique:telegram_channels,code',
            'chat_id' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'invite_link' => 'nullable|string|max:255',
            'allowed_roles' => 'nullable|array',
            'enabled_commands' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $code = ! empty($validated['code']) ? Str::slug($validated['code'], '_') : Str::slug($validated['name'], '_');
        if (TelegramChannel::where('code', $code)->exists()) {
            $code .= '_'.time();
        }

        $customButtons = [];
        if ($request->has('custom_buttons') && is_array($request->input('custom_buttons'))) {
            foreach ($request->input('custom_buttons') as $cmd => $btnData) {
                if (! is_array($btnData)) {
                    continue;
                }
                $customButtons[$cmd] = [
                    'label' => ! empty($btnData['label']) ? trim($btnData['label']) : null,
                    'description' => ! empty($btnData['description']) ? trim($btnData['description']) : null,
                    'show_reply_button' => ! empty($btnData['show_reply_button']),
                ];
            }
        }

        $channel = TelegramChannel::create([
            'name' => $validated['name'],
            'code' => $code,
            'chat_id' => $validated['chat_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'invite_link' => $validated['invite_link'] ?? null,
            'allowed_roles' => $validated['allowed_roles'] ?? [],
            'enabled_commands' => $validated['enabled_commands'] ?? [],
            'show_reply_keyboard' => $request->has('show_reply_keyboard'),
            'custom_buttons' => $customButtons,
            'is_active' => $request->has('is_active'),
        ]);

        if ($channel->chat_id && ! empty($channel->enabled_commands)) {
            $this->telegramService->setChatCommands($channel->chat_id, $channel->enabled_commands, $channel);
        }

        return back()->with('success', "Saluran Grup '{$channel->name}' berhasil ditambahkan.");
    }

    public function updateChannel(Request $request, TelegramChannel $channel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'chat_id' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'invite_link' => 'nullable|string|max:255',
            'allowed_roles' => 'nullable|array',
            'enabled_commands' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $customButtons = [];
        if ($request->has('custom_buttons') && is_array($request->input('custom_buttons'))) {
            foreach ($request->input('custom_buttons') as $cmd => $btnData) {
                if (! is_array($btnData)) {
                    continue;
                }
                $customButtons[$cmd] = [
                    'label' => ! empty($btnData['label']) ? trim($btnData['label']) : null,
                    'description' => ! empty($btnData['description']) ? trim($btnData['description']) : null,
                    'show_reply_button' => ! empty($btnData['show_reply_button']),
                ];
            }
        }

        $channel->update([
            'name' => $validated['name'],
            'chat_id' => ! empty($validated['chat_id']) ? $validated['chat_id'] : null,
            'description' => ! empty($validated['description']) ? $validated['description'] : null,
            'invite_link' => ! empty($validated['invite_link']) ? $validated['invite_link'] : null,
            'allowed_roles' => $validated['allowed_roles'] ?? [],
            'enabled_commands' => $validated['enabled_commands'] ?? [],
            'show_reply_keyboard' => $request->has('show_reply_keyboard'),
            'custom_buttons' => $customButtons,
            'is_active' => $request->has('is_active'),
        ]);

        if ($channel->chat_id && $channel->is_active && ! empty($channel->enabled_commands)) {
            $this->telegramService->setChatCommands($channel->chat_id, $channel->enabled_commands, $channel);
        }

        return back()->with('success', "Pengaturan saluran grup '{$channel->name}' berhasil diperbarui.");
    }

    public function destroyChannel(TelegramChannel $channel): RedirectResponse
    {
        $name = $channel->name;
        $channel->delete();

        return back()->with('success', "Saluran grup '{$name}' telah dihapus.");
    }

    public function syncChannelCommands(TelegramChannel $channel): RedirectResponse
    {
        if (empty($channel->chat_id)) {
            return back()->with('error', "Grup '{$channel->name}' belum memiliki Chat ID Telegram.");
        }

        $res = $this->telegramService->setChatCommands($channel->chat_id, $channel->enabled_commands ?? [], $channel);

        if ($res['success']) {
            return back()->with('success', $res['message']);
        }

        return back()->with('error', $res['message']);
    }

    public function syncAllCommands(): RedirectResponse
    {
        $res = $this->telegramService->syncAllChannelCommands();

        return back()->with('success', $res['message']);
    }

    public function inviteChannelMembers(TelegramChannel $channel): RedirectResponse
    {
        $res = $this->telegramService->sendInviteToEligibleUsers($channel);

        if ($res['success']) {
            return back()->with('success', $res['message']);
        }

        return back()->with('error', $res['message']);
    }
}
