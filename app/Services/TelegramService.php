<?php

namespace App\Services;

use App\Models\GtkPermit;
use App\Models\PiketDutySwap;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\PiketTelegramLog;
use App\Models\SchoolActivity;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TelegramService
{
    protected ?string $botToken;

    protected bool $enabled;

    protected ?string $disciplineChatId;

    protected ?string $piketChatId;

    protected ?string $supervisionChatId;

    protected ?string $activitiesChatId;

    protected ?string $teachersChatId;

    public function __construct()
    {
        $setting = SchoolSetting::getSetting();
        $this->botToken = $setting->telegram_bot_token ?: config('services.telegram.bot_token');
        $this->enabled = (bool) ($setting->telegram_notifications_enabled ?? false);
        $this->disciplineChatId = $setting->telegram_discipline_chat_id;
        $this->piketChatId = $setting->telegram_piket_chat_id;
        $this->supervisionChatId = $setting->telegram_supervision_chat_id;
        $this->activitiesChatId = $setting->telegram_activities_chat_id;
        $this->teachersChatId = $setting->telegram_teachers_chat_id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->botToken);
    }

    public function getBotToken(): ?string
    {
        return $this->botToken;
    }

    public function getDisciplineChatId(): ?string
    {
        return $this->disciplineChatId;
    }

    public function getPiketChatId(): ?string
    {
        return $this->piketChatId;
    }

    public function getSupervisionChatId(): ?string
    {
        return $this->supervisionChatId;
    }

    public function getActivitiesChatId(): ?string
    {
        return $this->activitiesChatId;
    }

    public function getTeachersChatId(): ?string
    {
        return $this->teachersChatId;
    }

    /**
     * Send message to specified chat_id via Telegram Bot API.
     */
    public function sendMessage(
        string $chatId,
        string $message,
        string $targetType = 'general',
        string $recipientName = 'System',
        string $parseMode = 'HTML',
        ?array $replyMarkup = null
    ): bool {
        if (! $this->isEnabled() && $targetType !== 'test') {
            return false;
        }

        if (empty($chatId) || empty($this->botToken)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ];

            if ($replyMarkup !== null) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::timeout(8)->post($url, $payload);

            $isSuccess = $response->successful() && ($response->json('ok') === true);

            PiketTelegramLog::create([
                'target_type' => $targetType,
                'recipient_name' => $recipientName,
                'chat_id' => $chatId,
                'message' => $message,
                'status' => $isSuccess ? 'sent' : 'failed',
                'response_payload' => $response->body(),
            ]);

            return $isSuccess;
        } catch (Throwable $e) {
            Log::error('Telegram notification error: '.$e->getMessage());

            PiketTelegramLog::create([
                'target_type' => $targetType,
                'recipient_name' => $recipientName,
                'chat_id' => $chatId,
                'message' => $message,
                'status' => 'failed',
                'response_payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            return false;
        }
    }

    /**
     * Send photo to specified chat_id via Telegram Bot API (supports local storage file or remote URL).
     */
    public function sendPhoto(
        string $chatId,
        string $photoPath,
        string $caption = '',
        string $targetType = 'general',
        string $recipientName = 'System',
        string $parseMode = 'HTML',
        ?array $replyMarkup = null
    ): bool {
        if (! $this->isEnabled() && $targetType !== 'test') {
            return false;
        }

        if (empty($chatId) || empty($this->botToken)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendPhoto";

        try {
            $fullPath = null;
            if (Storage::disk('public')->exists($photoPath)) {
                $fullPath = Storage::disk('public')->path($photoPath);
            } elseif (file_exists($photoPath)) {
                $fullPath = $photoPath;
            }

            $params = [
                'chat_id' => $chatId,
                'caption' => mb_substr($caption, 0, 1024),
                'parse_mode' => $parseMode,
            ];

            if ($replyMarkup !== null) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            if ($fullPath && file_exists($fullPath)) {
                $response = Http::timeout(15)
                    ->attach('photo', file_get_contents($fullPath), basename($fullPath))
                    ->post($url, $params);
            } else {
                $params['photo'] = $photoPath;
                $response = Http::timeout(10)->post($url, $params);
            }

            $isSuccess = $response->successful() && ($response->json('ok') === true);

            PiketTelegramLog::create([
                'target_type' => $targetType,
                'recipient_name' => $recipientName,
                'chat_id' => $chatId,
                'message' => '[Foto] '.$caption,
                'status' => $isSuccess ? 'sent' : 'failed',
                'response_payload' => $response->body(),
            ]);

            return $isSuccess;
        } catch (Throwable $e) {
            Log::error('Telegram sendPhoto error: '.$e->getMessage());

            // Fallback to sending text message
            return $this->sendMessage($chatId, $caption, $targetType, $recipientName, $parseMode, $replyMarkup);
        }
    }

    /**
     * Download a file from Telegram (e.g. photos sent in chat) and store in public storage.
     */
    public function downloadTelegramFile(string $fileId, string $subDir = 'piket/incidents'): ?string
    {
        if (empty($this->botToken)) {
            return null;
        }

        try {
            $getFileUrl = "https://api.telegram.org/bot{$this->botToken}/getFile?file_id={$fileId}";
            $res = Http::timeout(10)->get($getFileUrl);

            if (! $res->successful() || ! $res->json('ok')) {
                return null;
            }

            $telegramFilePath = $res->json('result.file_path');
            if (empty($telegramFilePath)) {
                return null;
            }

            $downloadUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$telegramFilePath}";
            $fileContent = Http::timeout(20)->get($downloadUrl)->body();

            $extension = pathinfo($telegramFilePath, PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = $subDir.'/tg_'.time().'_'.bin2hex(random_bytes(4)).'.'.$extension;

            Storage::disk('public')->put($fileName, $fileContent);

            return $fileName;
        } catch (Throwable $e) {
            Log::error('Download Telegram file error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Set Webhook URL in Telegram.
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'message' => 'Bot Token belum dikonfigurasi.'];
        }

        try {
            $endpoint = "https://api.telegram.org/bot{$this->botToken}/setWebhook";
            $params = ['url' => $url];
            if ($secretToken) {
                $params['secret_token'] = $secretToken;
            }

            $res = Http::timeout(10)->post($endpoint, $params);

            return [
                'success' => $res->successful() && $res->json('ok'),
                'message' => $res->json('description') ?? ($res->successful() ? 'Webhook berhasil disetel.' : 'Gagal menyetel webhook.'),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get current Webhook info.
     */
    public function getWebhookInfo(): array
    {
        if (empty($this->botToken)) {
            return ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0];
        }

        try {
            $res = Http::timeout(8)->get("https://api.telegram.org/bot{$this->botToken}/getWebhookInfo");

            return $res->json('result') ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Delete Webhook in Telegram.
     */
    public function deleteWebhook(): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'message' => 'Bot Token belum dikonfigurasi.'];
        }

        try {
            $res = Http::timeout(8)->post("https://api.telegram.org/bot{$this->botToken}/deleteWebhook");

            return [
                'success' => $res->successful() && $res->json('ok'),
                'message' => $res->json('description') ?? 'Webhook berhasil dihapus.',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // 1. NOTIFIKASI PIKET & KEDISIPLINAN
    // =========================================================================

    /**
     * Notifikasi saat siswa terlambat.
     */
    public function notifyStudentLate(
        PiketStudentPermit $permit,
        ?User $subjectTeacher = null,
        ?User $homeroomTeacher = null
    ): void {
        $studentName = $permit->student_name;
        $classroomName = $permit->classroom?->name ?? '-';
        $time = $permit->entry_time ? substr($permit->entry_time, 0, 5) : date('H:i');
        $period = $permit->period_assigned ?: 'Saat ini';
        $reason = $permit->reason ?: 'Tidak ada keterangan';
        $action = $permit->action_taken ?: 'Diberikan pembinaan';
        $officer = $permit->givenBy?->name ?? 'Guru Piket';

        $text = "🔔 <b>NOTIFIKASI SISWA TERLAMBAT</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Nama Siswa:</b> {$studentName}\n"
            ."🏫 <b>Kelas:</b> {$classroomName}\n"
            ."⏰ <b>Jam Masuk:</b> {$time} WIB (Jam ke-{$period})\n"
            ."📝 <b>Alasan:</b> {$reason}\n"
            ."⚖️ <b>Tindakan/Sanksi:</b> {$action}\n"
            ."👮 <b>Petugas Piket:</b> {$officer}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Siswa telah diberikan surat izin masuk kelas oleh guru piket.</i>';

        // 1. Notif ke Guru Mapel (jika ada chat_id)
        if ($subjectTeacher && $subjectTeacher->telegram_chat_id) {
            $msgTeacher = "<b>Kepada Yth. Bpk/Ibu Guru Mapel ({$subjectTeacher->name})</b>\n\n".$text;
            $this->sendMessage($subjectTeacher->telegram_chat_id, $msgTeacher, 'subject_teacher', $subjectTeacher->name);
            $permit->update(['notified_subject_teacher' => true]);
        }

        // 2. Notif ke Wali Kelas
        if ($homeroomTeacher && $homeroomTeacher->telegram_chat_id) {
            $msgWali = "<b>Kepada Yth. Wali Kelas ({$homeroomTeacher->name})</b>\n\n".$text;
            $this->sendMessage($homeroomTeacher->telegram_chat_id, $msgWali, 'homeroom_teacher', $homeroomTeacher->name);
            $permit->update(['notified_homeroom' => true]);
        }

        // 3. Notif ke Grup Kedisiplinan & BK
        if ($this->disciplineChatId) {
            $this->sendMessage($this->disciplineChatId, $text, 'discipline_group', 'Grup Kedisiplinan/BK');
        }
    }

    /**
     * Notifikasi saat siswa izin keluar / pulang cepat / tidak masuk seharian.
     *
     * @param  iterable<User>|null  $allSubjectTeachers
     */
    public function notifyStudentPermit(
        PiketStudentPermit $permit,
        ?User $homeroomTeacher = null,
        ?User $subjectTeacher = null,
        ?iterable $allSubjectTeachers = null
    ): void {
        $studentName = $permit->student_name;
        $classroomName = $permit->classroom?->name ?? '-';
        $typeLabel = $permit->permit_type_label;
        $time = $permit->departure_time ? substr($permit->departure_time, 0, 5) : ($permit->entry_time ? substr($permit->entry_time, 0, 5) : date('H:i'));
        $reason = $permit->reason;
        $officer = $permit->givenBy?->name ?? 'Guru Piket';

        $text = "📋 <b>NOTIFIKASI PERIZINAN SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📌 <b>Jenis Izin:</b> {$typeLabel}\n"
            ."👤 <b>Nama Siswa:</b> {$studentName}\n"
            ."🏫 <b>Kelas:</b> {$classroomName}\n"
            ."⏰ <b>Waktu:</b> {$time} WIB\n"
            ."📝 <b>Alasan:</b> {$reason}\n"
            ."👮 <b>Penerbit/Pencatat:</b> {$officer}\n"
            .'━━━━━━━━━━━━━━━━━━━━';

        // 1. Notif ke Guru Mapel jam tersebut (jika ada chat_id)
        if ($subjectTeacher && $subjectTeacher->telegram_chat_id) {
            $msgTeacher = "<b>Kepada Yth. Bpk/Ibu Guru Mapel ({$subjectTeacher->name})</b>\n\n".$text."\n<i>Pemberitahuan izin siswa pada jam KBM yang sedang berlangsung.</i>";
            $this->sendMessage($subjectTeacher->telegram_chat_id, $msgTeacher, 'subject_teacher', $subjectTeacher->name);
            $permit->update(['notified_subject_teacher' => true]);
        }

        // 1b. Jika ada daftar seluruh guru mapel hari ini (misal izin tidak masuk seharian)
        if ($allSubjectTeachers) {
            foreach ($allSubjectTeachers as $tch) {
                if ($tch && $tch->telegram_chat_id && (! $subjectTeacher || $tch->id !== $subjectTeacher->id)) {
                    $msgTch = "<b>Kepada Yth. Bpk/Ibu Guru Mapel ({$tch->name})</b>\n\n".$text."\n<i>Pemberitahuan izin siswa di kelas yang Anda ajar hari ini.</i>";
                    $this->sendMessage($tch->telegram_chat_id, $msgTch, 'subject_teacher', $tch->name);
                }
            }
        }

        // 2. Notif ke Wali Kelas (jika ada chat_id dan bukan dirinya sendiri yang menerbitkan)
        if ($homeroomTeacher && $homeroomTeacher->telegram_chat_id && (! $permit->given_by || $homeroomTeacher->id !== $permit->given_by)) {
            $msgWali = "<b>Kepada Yth. Wali Kelas ({$homeroomTeacher->name})</b>\n\n".$text;
            $this->sendMessage($homeroomTeacher->telegram_chat_id, $msgWali, 'homeroom_teacher', $homeroomTeacher->name);
            $permit->update(['notified_homeroom' => true]);
        }

        // 2b. Jika diterbitkan oleh Wali Kelas, kirimkan tembusan ke Petugas Piket Hari Ini
        if ($permit->givenBy && $homeroomTeacher && $permit->givenBy->id === $homeroomTeacher->id) {
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $today = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';
            $setting = SchoolSetting::getSetting();
            $academicYear = $setting->academic_year ?? '2026/2027';

            $piketOfficers = User::whereHas('piketSchedules', function ($q) use ($today, $academicYear) {
                $q->where('academic_year', $academicYear)
                    ->where('day', $today)
                    ->where('is_active', true);
            })
                ->whereNotNull('telegram_chat_id')
                ->get();

            foreach ($piketOfficers as $piketOfficer) {
                $msgPiket = "<b>🚨 PEMBERITAHUAN IZIN SISWA DARI WALI KELAS</b>\n\n"
                    ."Yth. Petugas Piket Hari Ini (<b>{$piketOfficer->name}</b>),\n"
                    ."Wali Kelas (<b>{$homeroomTeacher->name}</b>) telah mencatat izin siswa untuk hari ini:\n\n"
                    .$text;
                $this->sendMessage($piketOfficer->telegram_chat_id, $msgPiket, 'piket_officer', $piketOfficer->name);
            }
        }

        // 3. Notif ke Grup Tim Piket Harian Sekolah
        if ($this->piketChatId) {
            $this->sendMessage($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
        }

        // 4. Notif ke Grup Kedisiplinan/BK
        if ($this->disciplineChatId) {
            $this->sendMessage($this->disciplineChatId, $text, 'discipline_group', 'Grup Kedisiplinan/BK');
        }
    }

    /**
     * Notifikasi kelas kosong (inval) ke Waka Kurikulum dan Petugas Piket hari ini.
     */
    public function notifyInvalClassToWakaAndPiket(
        PiketInvalClass $inval
    ): void {
        $classroomName = $inval->classroom?->name ?? '-';
        $subjectName = $inval->subject?->name ?? 'Mata Pelajaran';
        $absentTeacherName = $inval->absentTeacher?->name ?? 'Guru Bersangkutan';
        $invalTeacherName = $inval->invalTeacher?->name ?? '<i>Belum Ditentukan</i>';
        $period = "Jam {$inval->period_start} - {$inval->period_end}";
        $reason = $inval->reason ?: 'Izin / Berhalangan';
        $task = $inval->assignment_material ? "\n📝 <b>Materi/Tugas:</b> {$inval->assignment_material}" : '';

        $text = "🔔 <b>NOTIFIKASI KELAS KOSONG / INVAL KBM</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."🏫 <b>Kelas:</b> {$classroomName}\n"
            ."📖 <b>Mapel:</b> {$subjectName} ({$period})\n"
            ."👤 <b>Guru Utama:</b> {$absentTeacherName}\n"
            ."👥 <b>Guru Inval:</b> {$invalTeacherName}\n"
            ."📌 <b>Alasan:</b> {$reason}"
            ."{$task}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Mohon bantuan pengondisian kelas dan pemantauan pembelajaran oleh piket.</i>';

        // 1. Japri ke Waka Kurikulum
        $setting = SchoolSetting::getSetting();
        $wakaKurikulum = $setting->curriculumHead
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();
        if ($wakaKurikulum && $wakaKurikulum->telegram_chat_id) {
            $msgWaka = "<b>Kepada Yth. Waka Kurikulum ({$wakaKurikulum->name})</b>\n\n".$text;
            $this->sendMessage($wakaKurikulum->telegram_chat_id, $msgWaka, 'waka_kurikulum', $wakaKurikulum->name);
        }

        // 2. Kirim ke Grup Tim Piket
        if ($this->piketChatId) {
            $this->sendMessage($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
        }

        // 3. Japri ke seluruh Petugas Piket hari ini
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';
        $academicYear = $setting->academic_year ?? '2026/2027';

        $piketOfficers = User::whereHas('piketSchedules', function ($q) use ($todayDay, $academicYear) {
            $q->where('academic_year', $academicYear)
                ->where('day', $todayDay)
                ->where('is_active', true);
        })
            ->whereNotNull('telegram_chat_id')
            ->get();

        foreach ($piketOfficers as $piketOfficer) {
            $msgPiket = "<b>[Tembusan Petugas Piket Hari Ini]</b>\n\n".$text;
            $this->sendMessage($piketOfficer->telegram_chat_id, $msgPiket, 'piket_officer', $piketOfficer->name);
        }
    }

    /**
     * Tembusan perizinan guru & penugasan kelas inval kepada Waka Kurikulum dan Grup Piket.
     *
     * @param  array<int, array{class: string, subject: string, period: string, task: string}>  $classList
     */
    public function notifyTeacherInvalToWaka(
        User $absentTeacher,
        string $day,
        string $dateFormatted,
        string $reason,
        array $classList,
        User $officer,
        ?User $wakaKurikulum = null,
        ?User $principal = null
    ): void {
        $classesText = '';
        foreach ($classList as $idx => $item) {
            $no = $idx + 1;
            $classesText .= "{$no}. <b>{$item['class']}</b> - {$item['subject']} ({$item['period']})\n"
                ."   📝 <i>Tugas:</i> {$item['task']}\n";
        }

        if (empty($classesText)) {
            $classesText = "<i>Izin umum / tanpa jam mengajar di kelas.</i>\n";
        }

        $isSelfSubmission = ($absentTeacher->id === $officer->id);
        $submitterLabel = $isSelfSubmission
            ? '📝 <b>Pengajuan:</b> Mandiri oleh Guru Bersangkutan'
            : "👮 <b>Pencatat Piket:</b> {$officer->name}";

        $text = "📋 <b>TEMBUSAN PERIZINAN GURU & TUGAS KELAS</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Guru Izin:</b> {$absentTeacher->name}\n"
            ."🗓️ <b>Hari/Tanggal:</b> {$day}, {$dateFormatted}\n"
            ."📌 <b>Alasan:</b> {$reason}\n\n"
            ."📚 <b>Kelas & Tugas Pembelajaran:</b>\n"
            ."{$classesText}\n"
            ."{$submitterLabel}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Laporan otomatis dari SIM Kurikulum untuk koordinasi KBM dan pengondisian kelas (inval).</i>';

        // 1. Tembusan ke Waka Kurikulum (Japri)
        if ($wakaKurikulum && $wakaKurikulum->telegram_chat_id) {
            $msgWaka = "<b>Kepada Yth. Waka Kurikulum ({$wakaKurikulum->name})</b>\n\n".$text;
            $this->sendMessage($wakaKurikulum->telegram_chat_id, $msgWaka, 'waka_kurikulum', $wakaKurikulum->name);
        }

        // 2. Tembusan ke Kepala Sekolah (Japri)
        if (! $principal) {
            $setting = SchoolSetting::getSetting();
            $principal = $setting->principal
                ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        }
        if ($principal && $principal->telegram_chat_id) {
            $msgPrincipal = "<b>Kepada Yth. Bapak/Ibu Kepala Sekolah ({$principal->name})</b>\n\n".$text;
            $this->sendMessage($principal->telegram_chat_id, $msgPrincipal, 'principal', $principal->name);
        }

        // 3. Jika pengajuan mandiri oleh guru, kirim juga notifikasi ke Petugas Piket yang bertugas pada hari tersebut
        if ($isSelfSubmission) {
            $setting = SchoolSetting::getSetting();
            $academicYear = $setting->academic_year ?? '2026/2027';

            $piketOfficers = User::whereHas('piketSchedules', function ($q) use ($day, $academicYear) {
                $q->where('academic_year', $academicYear)
                    ->where('day', $day)
                    ->where('is_active', true);
            })
                ->whereNotNull('telegram_chat_id')
                ->get();

            foreach ($piketOfficers as $piketOfficer) {
                if ($piketOfficer->id !== $absentTeacher->id) {
                    $msgPiket = "<b>🚨 PEMBERITAHUAN GURU IZIN & TUGAS KELAS</b>\n\n"
                        ."Yth. Petugas Piket (<b>{$piketOfficer->name}</b>),\n"
                        ."Rekan guru telah mengajukan izin mandiri untuk hari {$day}. Mohon bantuan pengondisian kelas dan penyerahan tugas.\n\n"
                        .$text;
                    $this->sendMessage($piketOfficer->telegram_chat_id, $msgPiket, 'piket_officer', $piketOfficer->name);
                }
            }
        }

        // 4. Kirim ke Grup Tim Piket Harian Sekolah
        if ($this->piketChatId) {
            $this->sendMessage($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
        }

        // 5. Konfirmasi ke Guru yang izin (jika ada chat_id dan bukan dirinya sendiri yang mengajukan via bot)
        if ($absentTeacher->telegram_chat_id && ! $isSelfSubmission) {
            $msgTeacher = "<b>Konfirmasi Perizinan Guru Terkirim</b>\n\n".$text;
            $this->sendMessage($absentTeacher->telegram_chat_id, $msgTeacher, 'teacher_absence', $absentTeacher->name);
        }
    }

    /**
     * Notifikasi pencatatan izin / cuti GTK ke Kepala Sekolah, Waka Kurikulum, dan Grup Piket.
     */
    public function notifyGtkPermit(GtkPermit $permit, ?User $actor = null): bool
    {
        $user = $permit->user;
        if (! $user) {
            return false;
        }

        $typeLabel = $permit->type_label ?? ucfirst($permit->type ?? $permit->permission_type);
        $startDate = Carbon::parse($permit->start_date)->translatedFormat('d F Y');
        $endDate = Carbon::parse($permit->end_date)->translatedFormat('d F Y');
        $period = ($permit->start_date === $permit->end_date)
            ? $startDate
            : "{$startDate} s/d {$endDate} ({$permit->total_days} hari)";

        $actorLabel = $actor ? $actor->name : 'Sistem / Mandiri';
        $statusLabel = $permit->isApproved() ? 'Disetujui' : ($permit->isPending() ? 'Menunggu Persetujuan Kepala Sekolah' : 'Ditolak');

        $text = "📋 <b>PEMBERITAHUAN IZIN / CUTI GTK</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Nama GTK:</b> {$user->name}\n"
            ."🏷️ <b>Jenis:</b> {$typeLabel}\n"
            ."📅 <b>Periode:</b> {$period}\n"
            ."📊 <b>Status:</b> {$statusLabel}\n"
            ."📝 <b>Alasan/Keterangan:</b> {$permit->reason}\n"
            .($permit->notes ? "📌 <b>Catatan:</b> {$permit->notes}\n" : '')
            ."👤 <b>Diajukan/Dicatat Oleh:</b> {$actorLabel}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .($permit->isApproved()
                ? '<i>Izin telah disetujui dan terintegrasi dengan rekapitulasi presensi GTK.</i>'
                : '<i>Pengajuan ini menunggu persetujuan Kepala Sekolah sebelum masuk rekap presensi.</i>');

        $sentCount = 0;
        $setting = SchoolSetting::getSetting();

        // 1. Kepala Sekolah
        $principal = $setting->principal
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();

        if ($principal && $principal->telegram_chat_id) {
            $prefix = $permit->isPending()
                ? "<b>[PENGAJUAN IZIN GTK]</b>\nKepada Yth. Bapak/Ibu Kepala Sekolah ({$principal->name}), terdapat permohonan izin GTK yang memerlukan persetujuan:\n\n"
                : "<b>Kepada Yth. Bapak/Ibu Kepala Sekolah ({$principal->name})</b>\n\n";
            $msgPrincipal = $prefix.$text;
            if ($this->sendMessage($principal->telegram_chat_id, $msgPrincipal, 'principal', $principal->name)) {
                $sentCount++;
            }
        }

        // 2. Waka Kurikulum (hanya jika approved)
        if ($permit->isApproved()) {
            $wakaKurikulum = $setting->curriculumHead
                ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

            if ($wakaKurikulum && $wakaKurikulum->telegram_chat_id) {
                $msgWaka = "<b>Kepada Yth. Waka Kurikulum ({$wakaKurikulum->name})</b>\n\n".$text;
                if ($this->sendMessage($wakaKurikulum->telegram_chat_id, $msgWaka, 'waka_kurikulum', $wakaKurikulum->name)) {
                    $sentCount++;
                }
            }

            // 3. Grup Tim Piket
            if ($this->piketChatId) {
                $this->sendMessage($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
            }
        }

        // 4. GTK bersangkutan
        if ($user->telegram_chat_id && ($actor?->id !== $user->id)) {
            $msgUser = "<b>Konfirmasi Pencatatan Izin / Cuti Anda</b>\n\n".$text;
            $this->sendMessage($user->telegram_chat_id, $msgUser, 'gtk_permit', $user->name);
        }

        return $sentCount > 0;
    }

    /**
     * Notifikasi hasil persetujuan/penolakan izin GTK ke GTK bersangkutan, Waka Kurikulum, dan Piket.
     */
    public function notifyGtkPermitDecision(GtkPermit $permit, User $approver): bool
    {
        $user = $permit->user;
        if (! $user) {
            return false;
        }

        $typeLabel = $permit->type_label ?? ucfirst($permit->permission_type);
        $period = $permit->formatted_period;
        $isApproved = $permit->isApproved();
        $statusHeader = $isApproved ? '✅ IZIN / CUTI GTK DISETUJUI' : '❌ IZIN / CUTI GTK DITOLAK';

        $text = "{$statusHeader}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Nama GTK:</b> {$user->name}\n"
            ."🏷️ <b>Jenis:</b> {$typeLabel}\n"
            ."📅 <b>Periode:</b> {$period}\n"
            ."📝 <b>Alasan:</b> {$permit->reason}\n"
            ."⚖️ <b>Keputusan Oleh:</b> {$approver->name} (Kepala Sekolah)\n"
            .($permit->approval_note ? "💬 <b>Catatan Kepala Sekolah:</b> {$permit->approval_note}\n" : '')
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($isApproved) {
            $text .= '<i>Izin resmi berlaku dan telah masuk ke sistem monitoring presensi & jurnal piket.</i>';
        } else {
            $text .= '<i>Pengajuan izin tidak disetujui. Silakan berkoordinasi langsung dengan pihak sekolah jika diperlukan.</i>';
        }

        // 1. Japri ke GTK bersangkutan
        if ($user->telegram_chat_id) {
            $this->sendMessage($user->telegram_chat_id, $text, 'gtk_permit', $user->name);
        }

        // 2. Jika disetujui, kirim juga ke Grup Piket dan Waka Kurikulum
        if ($isApproved) {
            $setting = SchoolSetting::getSetting();
            $wakaKurikulum = $setting->curriculumHead
                ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

            if ($wakaKurikulum && $wakaKurikulum->telegram_chat_id) {
                $msgWaka = "<b>Kepada Yth. Waka Kurikulum ({$wakaKurikulum->name})</b>\n\n".$text;
                $this->sendMessage($wakaKurikulum->telegram_chat_id, $msgWaka, 'waka_kurikulum', $wakaKurikulum->name);
            }

            if ($this->piketChatId) {
                $this->sendMessage($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
            }
        }

        return true;
    }

    /**
     * Notifikasi catatan kejadian/kasus siswa ke:
     * 1. Grup Tim Piket Harian
     * 2. Petugas Piket Hari Ini (japri)
     * 3. Wali Kelas
     * 4. Grup Kedisiplinan & BK
     */
    public function notifyIncident(
        PiketIncident $incident,
        ?User $homeroomTeacher = null
    ): void {
        $studentName = $incident->student_name;
        $classroomName = $incident->classroom?->name ?? '-';
        $category = $incident->category_label;
        $followUp = $incident->follow_up_label;
        $time = substr($incident->incident_time, 0, 5);
        $date = $incident->incident_date?->format('d/m/Y') ?? date('d/m/Y');
        $officer = $incident->recordedBy?->name ?? 'Petugas Piket';

        $text = "⚠️ <b>LAPORAN KEJADIAN / KASUS SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📅 <b>Waktu:</b> {$date} - {$time} WIB\n"
            ."👤 <b>Nama Siswa:</b> {$studentName}\n"
            ."🏫 <b>Kelas:</b> {$classroomName}\n"
            ."🏷️ <b>Kategori:</b> {$category}\n"
            ."📄 <b>Uraian:</b> {$incident->description}\n"
            ."🛠️ <b>Tindakan Piket:</b> {$incident->action_taken}\n"
            .($incident->confiscated_items ? "📦 <b>Barang Sitaan:</b> {$incident->confiscated_items}\n" : '')
            .($incident->evidence_photo ? "📷 <b>Bukti Foto:</b> Terlampir\n" : '')
            ."➡️ <b>Tindak Lanjut:</b> {$followUp}\n"
            ."👮 <b>Pencatat:</b> {$officer}\n"
            .'━━━━━━━━━━━━━━━━━━━━';

        $dispatch = function (string $chatId, string $message, string $targetType, string $recipientName) use ($incident) {
            if ($incident->evidence_photo) {
                return $this->sendPhoto($chatId, $incident->evidence_photo, $message, $targetType, $recipientName);
            }

            return $this->sendMessage($chatId, $message, $targetType, $recipientName);
        };

        // 1. Notif ke Grup Tim Piket Sekolah
        if ($this->piketChatId) {
            $dispatch($this->piketChatId, $text, 'piket_group', 'Grup Tim Piket Sekolah');
        }

        // 2. Notif Japri ke Petugas Piket Hari Ini
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';
        $setting = SchoolSetting::getSetting();
        $academicYear = $setting->academic_year ?? '2026/2027';

        $todayOfficers = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->with('teacher')
            ->get();

        foreach ($todayOfficers as $duty) {
            if ($duty->teacher && $duty->teacher->telegram_chat_id) {
                $msgOfficer = "<b>[Tembusan Petugas Piket Hari Ini - {$todayDay}]</b>\n\n".$text;
                $dispatch($duty->teacher->telegram_chat_id, $msgOfficer, 'piket_officer', $duty->teacher->name);
            }
        }

        // 3. Notif ke Wali Kelas
        if ($homeroomTeacher && $homeroomTeacher->telegram_chat_id) {
            $msgWali = "<b>Kepada Yth. Wali Kelas ({$homeroomTeacher->name})</b>\n\n".$text;
            $dispatch($homeroomTeacher->telegram_chat_id, $msgWali, 'homeroom_teacher', $homeroomTeacher->name);
        }

        // 4. Notif ke Grup Kedisiplinan & BK
        if ($this->disciplineChatId) {
            $dispatch($this->disciplineChatId, $text, 'discipline_group', 'Grup Kedisiplinan/BK');
        }

        // 5. Notif Japri ke Kepala Sekolah
        $principal = $setting->principal ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        if ($principal && $principal->telegram_chat_id) {
            $msgKS = "<b>[Laporan Meja Piket untuk Kepala Sekolah]</b>\n\n".$text;
            $dispatch($principal->telegram_chat_id, $msgKS, 'principal', $principal->name);
        }

        // 6. Notif Japri ke Waka Kesiswaan (jika ada)
        $wakaKesiswaan = User::all()->first(fn ($u) => $u->isWakaKesiswaan() && ! empty($u->telegram_chat_id));
        if ($wakaKesiswaan && (! $principal || $wakaKesiswaan->id !== $principal->id)) {
            $msgWaka = "<b>[Laporan Meja Piket untuk Waka Kesiswaan]</b>\n\n".$text;
            $dispatch($wakaKesiswaan->telegram_chat_id, $msgWaka, 'waka_kesiswaan', $wakaKesiswaan->name);
        }
    }

    /**
     * Notifikasi pengingat H-1 atau hari H ke guru piket.
     */
    public function notifyPiketDutyReminder(User $teacher, string $day, string $dateFormatted, bool $isToday = false): bool
    {
        if (empty($teacher->telegram_chat_id)) {
            return false;
        }

        $header = $isToday ? '☀️ <b>PENGINGAT PIKET HARI INI</b>' : '🌙 <b>PENGINGAT JADWAL PIKET BESOK</b>';
        $when = $isToday ? "hari ini (<b>{$day}, {$dateFormatted}</b>)" : "besok (<b>{$day}, {$dateFormatted}</b>)";

        $text = "{$header}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Halo Bpk/Ibu <b>{$teacher->name}</b>,\n"
            ."Mengingatkan bahwa jadwal tugas piket Anda adalah {$when}.\n\n"
            ."<b>Checklist Tugas Piket:</b>\n"
            ."1. Hadir lebih awal di pos piket gerbang/lobi.\n"
            ."2. Melayani perizinan & presensi siswa terlambat.\n"
            ."3. Mengondisikan kelas kosong (inval).\n"
            ."4. Mengisi dan menutup Jurnal Piket harian.\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Semangat bertugas dan terima kasih atas dedikasinya!</i>';

        return $this->sendMessage($teacher->telegram_chat_id, $text, 'piket_reminder', $teacher->name);
    }

    /**
     * Notifikasi permohonan tukar jadwal piket langsung ke guru pengganti.
     */
    public function notifyDutySwapRequested(PiketDutySwap $swap): bool
    {
        $replacement = $swap->replacementTeacher;
        if (! $replacement || empty($replacement->telegram_chat_id)) {
            return false;
        }

        $requester = $swap->requester;
        $dateFormatted = $swap->duty_date?->translatedFormat('l, d F Y') ?? date('d/m/Y');
        $schedule = $swap->originalSchedule;
        $roleLabel = $schedule ? ucfirst($schedule->role) : 'Petugas Piket';
        $location = $schedule?->duty_location ? " ({$schedule->duty_location})" : '';

        $text = "🔄 <b>PERMOHONAN TUKAR JADWAL PIKET</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Yth. Bpk/Ibu <b>{$replacement->name}</b>,\n\n"
            ."Rekan guru <b>{$requester?->name}</b> mengajukan permohonan tukar jadwal piket dengan Anda:\n\n"
            ."📅 <b>Hari / Tanggal:</b> {$dateFormatted}\n"
            ."📌 <b>Tugas:</b> {$roleLabel}{$location}\n"
            ."📝 <b>Alasan:</b> {$swap->reason}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."<i>💡 Pertukaran jadwal ini langsung sah dan aktif begitu Anda menyetujuinya tanpa verifikasi pihak lain.</i>\n\n"
            .'Apakah Anda bersedia menggantikan tugas piket tersebut?';

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Terima & Setujui', 'callback_data' => "pds_acc_{$swap->id}"],
                    ['text' => '❌ Tolak', 'callback_data' => "pds_rej_{$swap->id}"],
                ],
            ],
        ];

        return $this->sendMessage($replacement->telegram_chat_id, $text, 'duty_swap_request', $replacement->name, 'HTML', $replyMarkup);
    }

    /**
     * Notifikasi saat tukar jadwal piket disetujui oleh guru pengganti.
     */
    public function notifyDutySwapApproved(PiketDutySwap $swap): void
    {
        $requester = $swap->requester;
        $replacement = $swap->replacementTeacher;
        $dateFormatted = $swap->duty_date?->translatedFormat('l, d F Y') ?? date('d/m/Y');

        // 1. Notif Japri ke Guru Pemohon
        if ($requester && $requester->telegram_chat_id) {
            $textRequester = "🎉 <b>PERMOHONAN TUKAR PIKET DISETUJUI</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Halo Bpk/Ibu <b>{$requester->name}</b>,\n\n"
                ."Permohonan tukar jadwal piket Anda untuk tanggal <b>{$dateFormatted}</b> telah <b>DISETUJUI</b> oleh Bpk/Ibu <b>{$replacement?->name}</b>.\n\n"
                .'Tugas piket pada tanggal tersebut resmi digantikan oleh beliau. Terima kasih atas koordinasinya!';

            $this->sendMessage($requester->telegram_chat_id, $textRequester, 'duty_swap_approved', $requester->name);
        }

        // 2. Siarkan ke Grup Tim Piket Sekolah
        if ($this->piketChatId) {
            $textGroup = "🔄 <b>INFO PERGANTIAN JADWAL PIKET RESMI</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Telah disetujui pertukaran tugas piket:\n"
                ."📅 <b>Hari/Tanggal:</b> {$dateFormatted}\n"
                ."👤 <b>Guru Pemohon:</b> {$requester?->name}\n"
                ."👥 <b>Guru Pengganti:</b> {$replacement?->name}\n"
                ."📝 <b>Alasan:</b> {$swap->reason}\n"
                ."✅ <b>Status:</b> Disetujui langsung oleh Guru Pengganti\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                .'<i>Pembaruan otomatis dari SIM Kurikulum & Sistem Piket.</i>';

            $this->sendMessage($this->piketChatId, $textGroup, 'piket_group', 'Grup Tim Piket Sekolah');
        }

        // 3. Tembusan Japri ke Waka Kurikulum
        $setting = SchoolSetting::getSetting();
        $wakaKurikulum = $setting->curriculumHead
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

        if ($wakaKurikulum && $wakaKurikulum->telegram_chat_id) {
            $schedule = $swap->originalSchedule;
            $pos = $schedule ? ucfirst($schedule->role).($schedule->duty_location ? " ({$schedule->duty_location})" : '') : 'Petugas Piket';

            $textWaka = "<b>Kepada Yth. Waka Kurikulum ({$wakaKurikulum->name})</b>\n\n"
                ."🔄 <b>LAPORAN PERUBAHAN JADWAL PIKET</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Diberitahukan bahwa telah disetujui pertukaran jadwal piket:\n\n"
                ."📅 <b>Hari / Tanggal:</b> {$dateFormatted}\n"
                ."📌 <b>Pos / Tugas:</b> {$pos}\n"
                ."👤 <b>Guru Pemohon:</b> {$requester?->name}\n"
                ."👥 <b>Guru Pengganti:</b> {$replacement?->name}\n"
                ."📝 <b>Alasan:</b> {$swap->reason}\n"
                ."✅ <b>Status:</b> Disetujui langsung oleh Guru Pengganti\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                .'<i>Pembaruan jadwal telah resmi tercatat di SIM Kurikulum & Sistem Piket.</i>';

            $this->sendMessage($wakaKurikulum->telegram_chat_id, $textWaka, 'waka_kurikulum', $wakaKurikulum->name);
        }
    }

    /**
     * Notifikasi saat tukar jadwal piket ditolak oleh guru pengganti.
     */
    public function notifyDutySwapRejected(PiketDutySwap $swap): void
    {
        $requester = $swap->requester;
        $replacement = $swap->replacementTeacher;
        $dateFormatted = $swap->duty_date?->translatedFormat('l, d F Y') ?? date('d/m/Y');

        if ($requester && $requester->telegram_chat_id) {
            $text = "⚠️ <b>PERMOHONAN TUKAR PIKET DITOLAK</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Halo Bpk/Ibu <b>{$requester->name}</b>,\n\n"
                ."Mohon maaf, permohonan tukar jadwal piket Anda untuk tanggal <b>{$dateFormatted}</b> <b>DITOLAK</b> oleh Bpk/Ibu <b>{$replacement?->name}</b>.\n\n"
                .'Silakan berkoordinasi dengan rekan guru lain untuk mengajukan tukar piket.';

            $this->sendMessage($requester->telegram_chat_id, $text, 'duty_swap_rejected', $requester->name);
        }
    }

    // =========================================================================
    // 2. NOTIFIKASI SUPERVISI PEMBELAJARAN
    // =========================================================================

    /**
     * Pengingat jadwal observasi supervisi kepada Guru dan Supervisor.
     */
    public function notifySupervisionSchedule(Supervision $supervision, bool $isToday = false): bool
    {
        $teacher = $supervision->teacher;
        $supervisor = $supervision->supervisor;
        $date = $supervision->schedule_date ? Carbon::parse($supervision->schedule_date)->translatedFormat('l, d F Y') : '-';
        $time = $supervision->schedule_time ? substr($supervision->schedule_time, 0, 5).' WIB' : '-';
        $subject = $supervision->subject ?? '-';
        $class = $supervision->class_name ?? '-';

        $header = $isToday ? '🎯 <b>JADWAL SUPERVISI HARI INI</b>' : '📅 <b>PENGINGAT JADWAL SUPERVISI KELAS</b>';

        $textTeacher = "{$header}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Yth. Bpk/Ibu Guru <b>{$teacher?->name}</b>,\n\n"
            ."Mengingatkan agenda observasi supervisi pembelajaran Anda:\n"
            ."📖 <b>Mata Pelajaran:</b> {$subject}\n"
            ."🏫 <b>Kelas:</b> {$class}\n"
            ."🗓️ <b>Tanggal:</b> {$date}\n"
            ."⏰ <b>Waktu:</b> {$time}\n"
            ."👨‍🏫 <b>Supervisor:</b> {$supervisor?->name}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Mohon mempersiapkan modul ajar/RPP dan media pembelajaran terbaik. Sukses selalu!</i>';

        $textSupervisor = "{$header}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Yth. Tim Supervisor <b>{$supervisor?->name}</b>,\n\n"
            ."Mengingatkan agenda supervisi pembelajaran yang akan Anda ampu:\n"
            ."👤 <b>Guru:</b> {$teacher?->name}\n"
            ."📖 <b>Mapel:</b> {$subject} ({$class})\n"
            ."🗓️ <b>Waktu:</b> {$date}, {$time}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Silakan akses instrumen observasi melalui sistem saat kegiatan berlangsung.</i>';

        $sent = false;
        if ($teacher && $teacher->telegram_chat_id) {
            $sent = $this->sendMessage($teacher->telegram_chat_id, $textTeacher, 'supervision_reminder', $teacher->name);
        }
        if ($supervisor && $supervisor->telegram_chat_id) {
            $this->sendMessage($supervisor->telegram_chat_id, $textSupervisor, 'supervision_reminder', $supervisor->name);
        }
        if ($this->supervisionChatId) {
            $this->sendMessage($this->supervisionChatId, $textTeacher, 'supervision_group', 'Grup Supervisi');
        }

        return $sent;
    }

    /**
     * Notifikasi saat supervisi diajukan untuk disetujui Kepala Sekolah.
     */
    public function notifySupervisionSubmitted(Supervision $supervision): void
    {
        $principal = $supervision->principal ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        $teacher = $supervision->teacher;
        $supervisor = $supervision->supervisor;

        $text = "📝 <b>PENGAJUAN PERSETUJUAN SUPERVISI</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Yth. Bapak/Ibu Kepala Sekolah,\n\n"
            ."Laporan hasil supervisi telah selesai dan menunggu pengesahan Anda:\n"
            ."📄 <b>No. Laporan:</b> {$supervision->report_number}\n"
            ."👤 <b>Guru:</b> {$teacher?->name}\n"
            ."📖 <b>Mapel & Kelas:</b> {$supervision->subject} ({$supervision->class_name})\n"
            ."👨‍🏫 <b>Supervisor:</b> {$supervisor?->name}\n"
            ."📊 <b>Nilai Akhir:</b> {$supervision->percentage}% ({$supervision->predicate})\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Silakan periksa dan berikan pengesahan pada Menu Persetujuan Supervisi.</i>';

        if ($principal && $principal->telegram_chat_id) {
            $this->sendMessage($principal->telegram_chat_id, $text, 'supervision_approval', $principal->name);
        }

        if ($this->supervisionChatId) {
            $this->sendMessage($this->supervisionChatId, $text, 'supervision_group', 'Grup Supervisi');
        }
    }

    /**
     * Notifikasi saat hasil supervisi telah disetujui / dinilai.
     */
    public function notifySupervisionApproved(Supervision $supervision): void
    {
        $teacher = $supervision->teacher;
        $score = $supervision->percentage ?? 0;
        $predicate = $supervision->predicate ?? '-';

        $text = "🎉 <b>HASIL SUPERVISI TELAH DISAHKAN</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Yth. Bpk/Ibu <b>{$teacher?->name}</b>,\n\n"
            ."Laporan hasil pelaksanaan supervisi pembelajaran Anda telah disahkan oleh Kepala Sekolah:\n"
            ."📖 <b>Mata Pelajaran:</b> {$supervision->subject} ({$supervision->class_name})\n"
            ."📊 <b>Nilai Akhir:</b> {$score}% (Predikat: <b>{$predicate}</b>)\n"
            .'🗓️ <b>Tanggal Disahkan:</b> '.now()->translatedFormat('d F Y')."\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Rincian feedback dan sertifikat hasil supervisi dapat diunduh pada sistem. Terima kasih atas komitmen dedikasi Anda!</i>';

        if ($teacher && $teacher->telegram_chat_id) {
            $this->sendMessage($teacher->telegram_chat_id, $text, 'supervision_result', $teacher->name);
        }
    }

    // =========================================================================
    // 3. NOTIFIKASI KEGIATAN SEKOLAH & KURIKULUM
    // =========================================================================

    /**
     * Notifikasi agenda atau kegiatan sekolah ke grup dewan guru atau grup kegiatan.
     */
    public function notifySchoolActivity(SchoolActivity $activity, ?string $eventDate = null, ?string $notes = null): bool
    {
        $catLabel = $activity->category_label;
        $dateStr = $eventDate ?: 'Sesuai Kalender Akademik';

        $text = "📢 <b>PENGUMUMAN KEGIATAN SEKOLAH</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📌 <b>Nama Kegiatan:</b> {$activity->name}\n"
            ."🏷️ <b>Kategori:</b> {$catLabel}\n"
            ."🗓️ <b>Waktu Pelaksanaan:</b> {$dateStr}\n"
            .($activity->description ? "📝 <b>Keterangan:</b> {$activity->description}\n" : '')
            .($notes ? "ℹ️ <b>Catatan Tambahan:</b> {$notes}\n" : '')
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Diumumkan oleh Bagian Kurikulum & Manajemen Sekolah.</i>';

        $sent = false;
        // Kirim ke grup kegiatan/kurikulum jika diatur
        if ($this->activitiesChatId) {
            $sent = $this->sendMessage($this->activitiesChatId, $text, 'school_activity', 'Grup Kegiatan Sekolah');
        }

        // Kirim juga ke grup umum dewan guru
        if ($this->teachersChatId) {
            $sent = $this->sendMessage($this->teachersChatId, $text, 'school_activity', 'Grup Dewan Guru');
        }

        return $sent;
    }

    // =========================================================================
    // 4. PESAN BROADCAST / SIARAN UMUM SEKOLAH
    // =========================================================================

    /**
     * Kirim pesan broadcast/siaran umum ke grup sekolah yang dipilih.
     */
    public function sendBroadcastMessage(string $targetChatId, string $title, string $content, string $senderName = 'Manajemen Sekolah'): bool
    {
        $date = now()->translatedFormat('l, d F Y H:i');

        $text = "📢 <b>{$title}</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."{$content}\n\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."🏛️ <b>Pengirim:</b> {$senderName}\n"
            ."⏰ <b>Waktu Siar:</b> {$date} WIB";

        return $this->sendMessage($targetChatId, $text, 'broadcast', 'Broadcast: '.$title);
    }

    /**
     * Test kirim pesan bot Telegram.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $targetChatId, ?string $token = null): array
    {
        $botToken = $token ?: $this->botToken;

        if (empty($botToken)) {
            return ['success' => false, 'message' => 'Token Bot Telegram belum diisi.'];
        }

        if (empty($targetChatId)) {
            return ['success' => false, 'message' => 'Chat ID Telegram penerima belum diisi.'];
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $text = "🤖 <b>Uji Coba Koneksi Bot Telegram Sekolah</b>\n"
            ."Sistem Informasi Kurikulum, Supervisi & Piket - SMK PGRI KRAS.\n\n"
            .'✅ Pesan uji coba ini berhasil terkirim pada: '.date('d-m-Y H:i:s').' WIB.';

        try {
            $response = Http::timeout(6)->post($url, [
                'chat_id' => $targetChatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful() && $response->json('ok') === true) {
                return [
                    'success' => true,
                    'message' => 'Pesan uji coba berhasil terkirim ke Telegram!',
                ];
            }

            $errMsg = $response->json('description') ?? $response->body();

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan dari Telegram: '.$errMsg,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Koneksi ke server Telegram gagal: '.$e->getMessage(),
            ];
        }
    }
}
