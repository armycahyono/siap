<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketDutySwap;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\SubjectAttendanceRecord;
use App\Models\User;
use App\Services\AcademicYearContext;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class TelegramBotWebhookController extends Controller
{
    public function __construct(protected TelegramService $telegramService) {}

    /**
     * Dapatkan tahun ajaran operasional untuk webhook telegram tanpa ketergantungan sesi web.
     */
    protected function getOperationalAcademicYear(): string
    {
        try {
            return app(AcademicYearContext::class)->getOperationalYear()->name;
        } catch (Throwable $e) {
            Log::warning('Telegram webhook operational academic year fallback: '.$e->getMessage());

            return SchoolSetting::getMasterYear();
        }
    }

    /**
     * Handle incoming webhook updates from Telegram Bot API.
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return response()->json([
                'status' => 'online',
                'service' => 'SIM Kurikulum - Telegram Bot Webhook Endpoint',
                'message' => 'Endpoint aktif dan siap menerima data POST dari Telegram Bot API.',
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);
        }

        try {
            $update = $request->all();

            // 1. Handle Callback Query (Klik Inline Keyboard Button)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);

                return response()->json(['ok' => true]);
            }

            // 2. Handle Message (Teks, Perintah, atau Foto)
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);

                return response()->json(['ok' => true]);
            }
        } catch (Throwable $e) {
            Log::error('Telegram bot webhook exception: '.$e->getMessage(), [
                'exception' => $e,
                'update' => $request->all(),
            ]);

            // Notify user about error if possible
            $chatId = $request->input('message.chat.id') ?? $request->input('callback_query.message.chat.id');
            if ($chatId) {
                try {
                    $this->telegramService->sendMessage(
                        (string) $chatId,
                        '⚠️ <i>Maaf, terjadi kendala saat memproses permintaan Anda. Silakan coba kembali sesaat lagi.</i>',
                        'bot_reply',
                        'System',
                        'HTML',
                        $this->getMainMenuKeyboard()
                    );
                } catch (Throwable) {
                    // Ignore notification failure
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle incoming text or photo messages.
     */
    protected function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if (empty($chatId)) {
            return;
        }

        $senderName = $message['from']['first_name'] ?? 'Petugas';
        $text = trim($message['text'] ?? '');
        $photos = $message['photo'] ?? null;

        // Cek apakah akun pengirim terdaftar di SIM Kurikulum
        $user = User::where('telegram_chat_id', $chatId)->first();

        // Cek apakah ada state percakapan interaktif (misal pelaporan kasus)
        $cacheKey = "tg_piket_state_{$chatId}";
        $state = Cache::get($cacheKey);

        if ($state) {
            $this->handleStatefulConversation($chatId, $text, $photos, $state, $user, $senderName);

            return;
        }

        // Command router
        $cmd = mb_strtolower($text);

        switch (true) {
            case $cmd === '/start':
                $this->commandStart($chatId, $user, $senderName);
                break;

            case $cmd === '/help' || $cmd === 'bantuan' || $cmd === '/panduan' || $cmd === 'panduan' || $cmd === 'ℹ️ panduan bantuan' || $cmd === 'panduan bantuan':
                $this->commandHelp($chatId, $user, $senderName);
                break;

            case $cmd === '/izin_siswa' || $cmd === '/izinsiswa' || $cmd === '/izin' || $cmd === '🎫 perizinan siswa' || $cmd === 'perizinan siswa' || $cmd === '🎫 izin siswa (wali kelas)' || $cmd === 'izin siswa (wali kelas)' || $cmd === 'izin siswa wali kelas':
                $this->startStudentPermit($chatId, $user);
                break;

            case $cmd === '/izin_gtk' || $cmd === '/izingtk' || $cmd === '/izin_guru' || $cmd === '/izinguru' || $cmd === '📋 perizinan gtk' || $cmd === 'perizinan gtk' || $cmd === '📋 perizinan gtk / guru' || $cmd === 'perizinan gtk / guru' || $cmd === '📋 perizinan guru' || $cmd === 'perizinan guru':
                $this->startTeacherPermit($chatId, $user);
                break;

            case $cmd === '/ajukan_izin' || $cmd === '/izin_mandiri' || $cmd === '📝 ajukan izin saya' || $cmd === 'ajukan izin saya' || $cmd === 'ajukan izin' || $cmd === 'izin mandiri':
                $this->startSelfTeacherPermit($chatId, $user);
                break;

            case $cmd === '/piket' || $cmd === '📋 tim piket hari ini':
                $this->commandPiketToday($chatId, $user);
                break;

            case $cmd === '/jadwal' || $cmd === '🏫 jadwal jam sekarang':
                $this->commandCurrentSchedule($chatId, $user);
                break;

            case $cmd === '/inval' || $cmd === '🚨 cek inval / kelas kosong':
                $this->commandInvalToday($chatId, $user);
                break;

            case $cmd === '/rekap' || $cmd === '📊 rekap cepat hari ini':
                $this->commandQuickSummary($chatId, $user);
                break;

            case $cmd === '/tukar' || $cmd === '/tukar_piket' || $cmd === '/tukarpiket' || $cmd === '🔄 tukar jadwal piket' || $cmd === 'tukar jadwal piket':
                $this->startDutySwap($chatId, $user);
                break;

            case $cmd === '/id' || $cmd === '/chatid' || $cmd === '🆔 cek chat id' || $cmd === 'cek chat id':
                $this->commandCheckId($chatId, $user, $senderName);
                break;

            case str_starts_with($cmd, '/hubungkan') || str_starts_with($cmd, '/link') || str_starts_with($cmd, '/tautkan'):
                $this->commandLinkAccount($chatId, $text, $user, $senderName);
                break;

            case $cmd === '/presensi' || $cmd === '📍 cek presensi saya' || $cmd === 'cek presensi saya' || $cmd === 'cek presensi' || $cmd === 'presensi':
                $this->commandMyAttendance($chatId, $user);
                break;

            case $cmd === '/jadwalsaya' || $cmd === '/jadwal_saya' || $cmd === '📅 jadwal mengajar saya' || $cmd === 'jadwal mengajar saya' || $cmd === 'jadwal saya':
                $this->commandMyTeachingSchedule($chatId, $user);
                break;

            case $cmd === '/agenda' || $cmd === '🗓️ agenda sekolah' || $cmd === 'agenda sekolah' || $cmd === 'agenda':
                $this->commandSchoolAgenda($chatId, $user);
                break;

            case $cmd === '/tamu' || $cmd === '👥 buku tamu hari ini' || $cmd === 'buku tamu hari ini' || $cmd === 'buku tamu':
                $this->commandTodayGuests($chatId, $user);
                break;

            case $cmd === '/cek_absen_siswa' || $cmd === '/absen_siswa' || $cmd === '/presensi_siswa' || $cmd === '📊 cek absen siswa' || $cmd === 'cek absen siswa' || $cmd === 'cek absen':
                $this->startCheckStudentAttendance($chatId, $user);
                break;

            case str_starts_with($cmd, '/poin') || str_starts_with($cmd, '/kasus_siswa') || str_starts_with($cmd, '/pelanggaran') || $cmd === '🔍 cek kasus siswa' || $cmd === 'cek kasus siswa':
                $this->commandStudentIncidentsCheck($chatId, $text, $user);
                break;

            case $cmd === '/lapor' || $cmd === '/kasus' || $cmd === '⚠️ lapor kasus siswa':
                $this->startIncidentReport($chatId, $user);
                break;

            default:
                // Jika pesan tidak dikenal dan bukan perintah
                $this->replyUnknown($chatId, $user);
                break;
        }
    }

    /**
     * Default reply keyboard menu for piket officers or regular teachers.
     */
    protected function getMainMenuKeyboard(?User $user = null): array
    {
        if (! $user) {
            return [
                'keyboard' => [
                    [['text' => 'ℹ️ Panduan Bantuan'], ['text' => '🆔 Cek Chat ID']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        $canOperate = $user && $user->canOperatePiket();
        $canCheckAttendance = $user && $this->canCheckStudentAttendance($user);

        if ($canOperate) {
            $piketRows = [
                [['text' => '🎫 Perizinan Siswa'], ['text' => '📋 Perizinan GTK / Guru']],
                [['text' => '⚠️ Lapor Kasus Siswa'], ['text' => '🚨 Cek Inval / Kelas Kosong']],
                [['text' => '📍 Cek Presensi Saya'], ['text' => '📅 Jadwal Mengajar Saya']],
                [['text' => '📊 Cek Absen Siswa'], ['text' => '🏫 Jadwal Jam Sekarang']],
                [['text' => '📋 Tim Piket Hari Ini'], ['text' => '📊 Rekap Cepat Hari Ini']],
                [['text' => '👥 Buku Tamu Hari Ini'], ['text' => '🗓️ Agenda Sekolah']],
                [['text' => '🔄 Tukar Jadwal Piket'], ['text' => 'ℹ️ Panduan Bantuan']],
                [['text' => '🆔 Cek Chat ID']],
            ];

            return [
                'keyboard' => $piketRows,
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        // Menu untuk Guru biasa / Wali Kelas / BK / Kedisiplinan
        $isHomeroom = $user && $user->isHomeroomTeacher();

        $rows = [
            [['text' => '📍 Cek Presensi Saya'], ['text' => '📅 Jadwal Mengajar Saya']],
        ];

        $actionRow = [['text' => '📝 Ajukan Izin Saya']];
        if ($isHomeroom) {
            $actionRow[] = ['text' => '🎫 Izin Siswa (Wali Kelas)'];
        }
        $rows[] = $actionRow;

        if ($canCheckAttendance) {
            $rows[] = [['text' => '📊 Cek Absen Siswa'], ['text' => '🏫 Jadwal Jam Sekarang']];
            $rows[] = [['text' => '📋 Tim Piket Hari Ini'], ['text' => '🚨 Cek Inval / Kelas Kosong']];
            $rows[] = [['text' => '📊 Rekap Cepat Hari Ini'], ['text' => '🗓️ Agenda Sekolah']];
        } else {
            $rows[] = [['text' => '📋 Tim Piket Hari Ini'], ['text' => '🏫 Jadwal Jam Sekarang']];
            $rows[] = [['text' => '🚨 Cek Inval / Kelas Kosong'], ['text' => '📊 Rekap Cepat Hari Ini']];
            $rows[] = [['text' => '🗓️ Agenda Sekolah']];
        }

        $rows[] = [['text' => '🔄 Tukar Jadwal Piket'], ['text' => 'ℹ️ Panduan Bantuan']];
        $rows[] = [['text' => '🆔 Cek Chat ID']];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /**
     * Command: /start
     */
    protected function commandStart(string $chatId, ?User $user, string $senderName): void
    {
        if (! $user) {
            $msg = "🤖 <b>SELAMAT DATANG DI BOT TELEGRAM SIAP</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Halo Bpk/Ibu <b>{$senderName}</b>,\n\n"
                ."Layanan asisten pintar terintegrasi SIM Kurikulum & Sistem Informasi Administrasi Pendidikan (SIAP) SMK PGRI Kras.\n\n"
                ."⚠️ <b>Status Akun: Belum Terhubung</b>\n"
                ."Akun Telegram Anda saat ini belum ditautkan ke akun GTK pada sistem SIAP.\n\n"
                ."🔗 <b>Cara Cepat Menautkan Akun Anda:</b>\n"
                ."Ketik perintah di bawah ini (ganti dengan NIP atau Username akun SIAP Anda):\n"
                ."👉 <code>/hubungkan NIP_ATAU_USERNAME</code>\n\n"
                ."<i>Contoh:</i>\n"
                ."<code>/hubungkan 198501012010011005</code> atau <code>/hubungkan ahmad_guru</code>\n\n"
                ."🆔 <b>Chat ID Anda:</b> <code>{$chatId}</code>\n"
                ."(Anda juga dapat mengirimkan nomor ID di atas ke Administrator SIM untuk ditautkan manual).\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                .'📖 <i>Ketik <code>/help</code> atau klik tombol <b>ℹ️ Panduan Bantuan</b> di bawah untuk membaca panduan lengkap fitur bot.</i>';

            $this->telegramService->sendMessage(
                $chatId,
                $msg,
                'bot_reply',
                $senderName,
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        $roleName = $user->role?->name ?? 'Guru/Staf';
        $isPiket = $user->canOperatePiket();
        $isHomeroom = $user->isHomeroomTeacher();

        $roleBadges = [];
        if ($isPiket) {
            $roleBadges[] = '👮 Petugas Piket Aktif';
        }
        if ($isHomeroom) {
            $roleBadges[] = '👨‍🏫 Wali Kelas';
        }
        $roleInfo = ! empty($roleBadges) ? implode(' | ', $roleBadges) : $roleName;

        $msg = "🤖 <b>ASISTEN TELEGRAM SIAP</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Halo Bpk/Ibu <b>{$user->name}</b>,\n"
            ."✅ <b>Status:</b> Terhubung ({$roleInfo})\n\n"
            ."Selamat datang kembali di asisten digital SIAP SMK PGRI Kras. Bot siap melayani operasional harian, perizinan, dan pemantauan KBM Anda.\n\n"
            ."Gunakan tombol menu interaktif di bawah untuk memilih aksi dengan cepat, atau ketik <code>/help</code> untuk membuka buku panduan lengkap fitur bot.\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>💡 Tips: Ketik <code>/batal</code> kapan saja jika ingin membatalkan proses pengisian formulir interaktif.</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $user->name,
            'HTML',
            $this->getMainMenuKeyboard($user)
        );
    }

    /**
     * Command: /help (Buku Panduan Lengkap Pengguna)
     */
    protected function commandHelp(string $chatId, ?User $user, string $senderName): void
    {
        $roleName = $user?->role?->name ?? 'Guru/Staf';
        $isPiket = $user && $user->canOperatePiket();
        $isHomeroom = $user && $user->isHomeroomTeacher();

        $statusHeader = $user
            ? "✅ <b>Akun Terhubung:</b> {$user->name} (<i>{$roleName}</i>)"
            : "⚠️ <b>Status Akun:</b> Belum Terhubung (Chat ID: <code>{$chatId}</code>)";

        $msg = "📖 <b>PANDUAN PENGGUNAAN BOT TELEGRAM SIAP</b>\n"
            ."SMK PGRI Kras — Sistem Administrasi & Pendidikan\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."{$statusHeader}\n\n"
            ."<b>1️⃣ CARA MENAUTKAN AKUN (JIKA BELUM)</b>\n"
            ."• Ketik perintah:\n"
            ."  <code>/hubungkan NIP_ATAU_USERNAME</code>\n"
            ."  <i>Contoh:</i> <code>/hubungkan 198501012010011005</code>\n"
            ."• Atau salin Chat ID Anda (ketik <code>/id</code>) dan serahkan ke Tim IT / Admin SIM untuk dipasangkan ke profil Anda.\n\n"
            ."<b>2️⃣ INFORMASI REAL-TIME & LAYANAN GTK</b>\n"
            ."• <code>/presensi</code> atau tombol <b>📍 Cek Presensi Saya</b>\n"
            ."  Melihat status scan masuk & pulang Anda hari ini beserta metode presensinya.\n"
            ."• <code>/jadwalsaya</code> atau tombol <b>📅 Jadwal Mengajar Saya</b>\n"
            ."  Melihat jadwal KBM pribadi Anda hari ini (kelas, mapel, jam ke, & waktu lonceng).\n"
            ."• <code>/piket</code> atau tombol <b>📋 Tim Piket Hari Ini</b>\n"
            ."  Melihat siapa saja guru yang bertugas piket hari ini.\n"
            ."• <code>/jadwal</code> atau tombol <b>🏫 Jadwal Jam Sekarang</b>\n"
            ."  Melihat guru pengajar & mapel yang sedang KBM di seluruh rombel jam ini.\n"
            ."• <code>/inval</code> atau tombol <b>🚨 Cek Inval / Kelas Kosong</b>\n"
            ."  Melihat kelas kosong yang ditinggal guru izin/tugas dan penugasan guru pengganti (inval).\n"
            ."• <code>/rekap</code> atau tombol <b>📊 Rekap Cepat Hari Ini</b>\n"
            ."  Melihat rekap jumlah siswa terlambat, izin keluar/pulang, kasus, dan buku tamu.\n"
            ."• <code>/tamu</code> atau tombol <b>👥 Buku Tamu Hari Ini</b>\n"
            ."  Melihat daftar tamu yang berkunjung ke sekolah hari ini.\n"
            ."• <code>/agenda</code> atau tombol <b>🗓️ Agenda Sekolah</b>\n"
            ."  Melihat agenda kegiatan sekolah & kalender akademik 7 hari ke depan.\n"
            ."• <code>/poin NAMA_ATAU_NIS</code>\n"
            ."  Mengecek catatan rekam jejak pelanggaran & keterlambatan siswa tertentu.\n"
            ."• <code>/id</code> atau tombol <b>🆔 Cek Chat ID</b>\n"
            ."  Mengetahui nomor identitas Telegram unik Anda.\n\n"
            ."<b>3️⃣ LAYANAN MANDIRI GURU</b>\n"
            ."• <code>/ajukan_izin</code> atau tombol <b>📝 Ajukan Izin Saya</b>\n"
            ."  Bila berhalangan hadir, ajukan izin mandiri & titipkan tugas/materi kelas dari bot. Tembusan otomatis terkirim ke Meja Piket, Waka Kurikulum, dan Kepala Sekolah.\n"
            ."• <code>/tukar</code> atau tombol <b>🔄 Tukar Jadwal Piket</b>\n"
            ."  Mengajukan permohonan tukar hari piket ke rekan guru yang bersedia secara interaktif.\n\n";

        if ($isHomeroom || ! $user) {
            $msg .= "<b>4️⃣ FITUR KHUSUS WALI KELAS, KEDISIPLINAN & BK</b>\n"
                ."• <code>/cek_absen_siswa</code> atau tombol <b>📊 Cek Absen Siswa</b>\n"
                ."  Melihat rekap presensi KBM siswa, riwayat perizinan piket, dan catatan kejadian/pelanggaran siswa pada periode tertentu (Minggu ini, Bulan ini, atau Semua/Awal TP hingga hari ini).\n"
                ."  <i>*Catatan: Wali Kelas dibatasi hanya dapat memeriksa siswa di kelas perwaliannya sendiri.</i>\n"
                ."• <code>/izin_siswa</code> atau tombol <b>🎫 Izin Siswa (Wali Kelas)</b>\n"
                ."  Menerbitkan izin resmi (sakit, izin urusan keluarga, dll.) untuk siswa di kelas binaan Anda langsung dari chat tanpa harus datang fisik ke meja piket.\n\n";
        }

        if ($isPiket || ! $user) {
            $msg .= "<b>5️⃣ FITUR OPERASIONAL PETUGAS PIKET</b>\n"
                ."• <b>🎫 Perizinan Siswa:</b>\n"
                ."  Mencatat siswa terlambat, izin keluar sekolah sementara, atau izin pulang cepat. Sistem otomatis mengirim notifikasi ke wali kelas & guru jam KBM tersebut.\n"
                ."• <b>📋 Perizinan GTK / Guru (/izin_gtk):</b>\n"
                ."  Mencatat izin rekan GTK di meja piket serta mencatat kelas kosong / penugasan guru inval.\n"
                ."• <b>⚠️ Lapor Kasus Siswa (/lapor atau /kasus):</b>\n"
                ."  Melaporkan pelanggaran tata tertib siswa lengkap dengan <b>foto barang bukti</b> dari kamera HP. Laporan langsung masuk buku piket dan diteruskan ke Tim Kedisiplinan & BK.\n\n";
        }

        $msg .= "<b>6️⃣ NOTIFIKASI OTOMATIS YANG ANDA TERIMA</b>\n"
            ."Setelah akun terhubung, bot akan mengirimkan pesan otomatis ke Telegram Anda:\n"
            ."🔔 <b>Pengingat KBM:</b> Dikirimkan otomatis 10 menit sebelum jam mengajar Anda dimulai.\n"
            ."🔔 <b>Pengingat Piket:</b> Dikirimkan H-1 sore & hari H pagi.\n"
            ."🔔 <b>Tembusan Siswa:</b> Saat ada siswa di kelas binaan/KBM Anda yang terlambat atau izin keluar.\n"
            ."🔔 <b>Penugasan Inval:</b> Pemberitahuan saat Anda ditugaskan menggantikan kelas kosong.\n"
            ."🔔 <b>Perizinan & Supervisi:</b> Tembusan persetujuan izin GTK & hasil supervisi pembelajaran.\n\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'💡 <i>Tips: Jika sedang berada dalam dialog pengisian data dan ingin membatalkan, cukup ketik <code>/batal</code> kapan saja.</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $senderName,
            'HTML',
            $this->getMainMenuKeyboard($user)
        );
    }

    /**
     * Command: /piket
     */
    protected function commandPiketToday(string $chatId, ?User $user): void
    {
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
        $todayFormatted = Carbon::today()->translatedFormat('l, d F Y');

        $academicYear = $this->getOperationalAcademicYear();

        $schedules = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('is_active', true)
            ->with('teacher')
            ->orderByRaw("CASE WHEN role = 'koordinator' THEN 0 ELSE 1 END")
            ->get();

        $journal = PiketJournal::whereDate('date', Carbon::today()->format('Y-m-d'))->first();
        $journalStatus = $journal ? strtoupper($journal->status) : 'BELUM DIBUAT';

        $text = "📋 <b>TIM PIKET HARI INI</b>\n"
            ."🗓️ <b>{$todayFormatted}</b>\n"
            ."Tahun Ajaran: {$academicYear}\n"
            ."Status Jurnal: <code>{$journalStatus}</code>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($schedules->isEmpty()) {
            $text .= "<i>Belum ada petugas piket yang dijadwalkan untuk hari {$todayDay}.</i>\n";
        } else {
            $no = 1;
            foreach ($schedules as $sched) {
                $roleLabel = ($sched->role === 'koordinator') ? ' ⭐ (Koordinator)' : '';
                $teacherName = $sched->teacher?->name ?? 'Belum Ditugaskan';
                $hasTelegram = $sched->teacher?->telegram_chat_id ? '📱' : '⚪';
                $text .= "{$no}. <b>{$teacherName}</b>{$roleLabel} {$hasTelegram}\n";
                $no++;
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Keterangan: (⭐) Koordinator Piket &bull; (📱) Akun Telegram Aktif</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /jadwal (Jadwal KBM Jam Pelajaran Saat Ini)
     */
    protected function commandCurrentSchedule(string $chatId, ?User $user = null): void
    {
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
        $currentTime = Carbon::now()->format('H:i');

        $periodTimes = [
            1 => ['start' => '07:00', 'end' => '07:45'],
            2 => ['start' => '07:45', 'end' => '08:30'],
            3 => ['start' => '08:30', 'end' => '09:15'],
            4 => ['start' => '09:50', 'end' => '10:30'],
            5 => ['start' => '10:30', 'end' => '11:10'],
            6 => ['start' => '11:10', 'end' => '11:50'],
            7 => ['start' => '11:50', 'end' => '12:30'],
            8 => ['start' => '13:00', 'end' => '13:40'],
            9 => ['start' => '13:40', 'end' => '14:20'],
            10 => ['start' => '14:20', 'end' => '15:00'],
        ];

        $detectedPeriod = null;
        $periodLabel = '';
        foreach ($periodTimes as $p => $times) {
            if ($currentTime >= $times['start'] && $currentTime < $times['end']) {
                $detectedPeriod = $p;
                $periodLabel = "Jam ke-{$p} ({$times['start']} - {$times['end']} WIB)";
                break;
            }
        }

        if (! $detectedPeriod) {
            if ($currentTime >= '09:15' && $currentTime < '09:50') {
                $periodLabel = 'Waktu Istirahat 1 (09:15 - 09:50 WIB)';
            } elseif ($currentTime >= '12:30' && $currentTime < '13:00') {
                $periodLabel = 'Waktu Istirahat & Sholat (12:30 - 13:00 WIB)';
            } elseif ($currentTime < '07:00') {
                $periodLabel = 'KBM Belum Dimulai (Sebelum Pukul 07:00)';
            } else {
                $periodLabel = 'KBM Efektif Telah Selesai (Setelah Pukul 15:00)';
            }
        }

        $academicYear = $this->getOperationalAcademicYear();

        $text = "🏫 <b>JADWAL PELAJARAN KELAS SAAT INI</b>\n"
            ."⏰ <b>Waktu:</b> {$currentTime} WIB\n"
            ."📌 <b>Status:</b> {$periodLabel}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if (! $detectedPeriod) {
            $text .= "Saat ini berada di luar jam pelajaran aktif. Silakan pantau kembali saat jam KBM berlangsung.\n";
            $text .= '━━━━━━━━━━━━━━━━━━━━';
            $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        // Ambil jadwal pelajaran untuk jam ke-$detectedPeriod
        $schedules = LessonSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->where('start_period', '<=', $detectedPeriod)
            ->where('end_period', '>=', $detectedPeriod)
            ->with(['classroom', 'subject', 'teacher'])
            ->get();

        if ($schedules->isEmpty()) {
            $text .= "<i>Tidak ada data jadwal pelajaran untuk Jam ke-{$detectedPeriod}.</i>\n";
        } else {
            foreach ($schedules as $s) {
                $className = $s->classroom?->name ?? 'Kelas ?';
                $subjectName = $s->subject?->name ?? 'Mapel ?';
                $teacherName = $s->teacher?->formatted_short_name ?? ($s->teacher?->name ?? 'Guru ?');

                $text .= "• <b>{$className}:</b> {$subjectName}\n"
                    ."  👨‍🏫 {$teacherName}\n";
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Gunakan untuk memeriksa kehadiran guru dan ketertiban kelas.</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /inval (Cek Inval & Kelas Kosong Hari Ini)
     */
    protected function commandInvalToday(string $chatId, ?User $user = null): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $academicYear = $this->getOperationalAcademicYear();

        $invals = PiketInvalClass::whereHas('journal', function ($q) use ($today, $academicYear) {
            $q->whereDate('date', $today)
                ->where('academic_year', $academicYear);
        })
            ->with(['absentTeacher', 'invalTeacher', 'classroom', 'subject'])
            ->get();

        $text = "🚨 <b>STATUS KELAS KOSONG & INVAL HARI INI</b>\n"
            .'🗓️ <b>'.Carbon::today()->translatedFormat('l, d F Y')."</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($invals->isEmpty()) {
            $text .= "✅ <i>Alhamdulillah, belum ada laporan kelas kosong atau guru tidak hadir hari ini. Semua KBM berjalan normal.</i>\n";
        } else {
            foreach ($invals as $inv) {
                $class = $inv->classroom?->name ?? '-';
                $absent = $inv->absentTeacher?->formatted_short_name ?? ($inv->absentTeacher?->name ?? 'Guru Utama');
                $substitute = $inv->invalTeacher?->formatted_short_name ?? ($inv->invalTeacher?->name ?? 'Belum Ditugaskan');
                $subject = $inv->subject?->name ?? 'Mapel';
                $period = "Jam ke-{$inv->period_start} s/d {$inv->period_end}";
                $statusIcon = $inv->inval_teacher_id ? '🔄 Terinval' : '⚠️ Butuh Inval';

                $text .= "• <b>{$class} - {$subject}</b> ({$period})\n"
                    ."  👤 Guru Berhalangan: {$absent} ({$inv->reason})\n"
                    ."  👮 Guru Pengganti: <b>{$substitute}</b>\n"
                    .($inv->assignment_material ? "  📝 Tugas: {$inv->assignment_material}\n" : '')
                    ."  📌 Status: <code>{$statusIcon}</code>\n\n";
            }
        }

        $text .= '━━━━━━━━━━━━━━━━━━━━';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /rekap (Rekap Cepat Hari Ini)
     */
    protected function commandQuickSummary(string $chatId, ?User $user = null): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $totalPermits = PiketStudentPermit::whereDate('created_at', $today)->count();
        $latePermits = PiketStudentPermit::whereDate('created_at', $today)->where('permit_type', 'terlambat')->count();
        $leavePermits = PiketStudentPermit::whereDate('created_at', $today)->whereIn('permit_type', ['izin_keluar_sementara', 'izin_pulang_cepat'])->count();
        $totalIncidents = PiketIncident::whereDate('incident_date', $today)->count();
        $totalGuests = PiketGuest::whereDate('visit_date', $today)->count();

        $text = "📊 <b>REKAP CEPAT PIKET HARI INI</b>\n"
            .'🗓️ <b>'.Carbon::today()->translatedFormat('l, d F Y')."</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."🔔 <b>Siswa Terlambat:</b> {$latePermits} siswa\n"
            ."🏃 <b>Izin Keluar / Pulang Cepat:</b> {$leavePermits} siswa\n"
            ."📑 <b>Total Surat Izin Diterbitkan:</b> {$totalPermits} surat\n"
            ."⚠️ <b>Laporan Kejadian/Kasus:</b> {$totalIncidents} kasus\n"
            ."👥 <b>Buku Tamu Berkunjung:</b> {$totalGuests} orang\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Data diperbarui secara real-time dari database piket sekolah.</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /id (Cek ID Telegram Pengirim)
     */
    protected function commandCheckId(string $chatId, ?User $user, string $senderName): void
    {
        $roleName = $user?->role?->name ?? 'Guru/Staf';
        $status = $user
            ? "✅ Akun Anda sudah terhubung dengan: <b>{$user->name}</b> ({$roleName})"
            : '⚪ Akun Anda <b>belum terhubung</b> ke SIM Kurikulum.';

        $text = "🆔 <b>INFORMASI AKUN TELEGRAM ANDA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Nama: <b>{$senderName}</b>\n"
            ."Chat ID Telegram: <code>{$chatId}</code>\n"
            ."Status: {$status}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."<b>Cara Menghubungkan Akun Guru:</b>\n"
            ."1. <b>Otomatis lewat Bot:</b> Ketik perintah:\n"
            ."   <code>/hubungkan [NIP atau Username Anda]</code>\n"
            ."   <i>Contoh: <code>/hubungkan 19850101...</code> atau <code>/hubungkan ahmad.soleh</code></i>\n\n"
            ."2. <b>Lewat Web SIM Kurikulum:</b>\n"
            ."   Login ke web &rarr; Menu <b>Profil Saya</b> &rarr; masukkan Chat ID <code>{$chatId}</code> pada kolom Telegram Chat ID.";

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $senderName, 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /presensi (Cek Status Presensi Pribadi GTK Hari Ini)
     */
    protected function commandMyAttendance(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $today = Carbon::today('Asia/Jakarta');
        $todayFormatted = $today->translatedFormat('l, d F Y');

        // Cek log presensi GTK hari ini
        $logs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $user->id)
            ->whereDate('scanned_at', $today)
            ->orderBy('scanned_at', 'asc')
            ->get();

        $checkIn = $logs->first();
        $checkOut = $logs->count() > 1 ? $logs->last() : null;

        // Cek apakah ada izin/cuti aktif hari ini
        $permit = GtkPermit::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        $text = "📍 <b>STATUS PRESENSI GTK HARI INI</b>\n"
            ."🗓️ <b>{$todayFormatted}</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Nama:</b> {$user->name}\n"
            .'📌 <b>NIP / NIGS:</b> '.($user->nip ?? $user->nigs ?? '-')."\n\n";

        if ($permit) {
            $text .= "ℹ️ <b>Status Khusus:</b> Izin / Cuti Resmi\n"
                ."• <b>Jenis:</b> {$permit->type_label}\n"
                ."• <b>Alasan:</b> {$permit->reason}\n\n";
        }

        if ($checkIn) {
            $inTime = $checkIn->scanned_at->format('H:i');
            $inMethod = $checkIn->verify_type_label;
            $text .= "🟢 <b>Presensi Masuk:</b> Pukul <b>{$inTime} WIB</b>\n"
                ."   Metode: <code>{$inMethod}</code>\n\n";
        } else {
            $text .= "⚪ <b>Presensi Masuk:</b> <i>Belum tercatat presensi masuk</i>\n\n";
        }

        if ($checkOut) {
            $outTime = $checkOut->scanned_at->format('H:i');
            $outMethod = $checkOut->verify_type_label;
            $text .= "🔴 <b>Presensi Pulang:</b> Pukul <b>{$outTime} WIB</b>\n"
                ."   Metode: <code>{$outMethod}</code>\n\n";
        } else {
            $text .= "⚪ <b>Presensi Pulang:</b> <i>Belum tercatat presensi pulang</i>\n\n";
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Presensi tersinkronisasi otomatis dari mesin biometrik & aplikasi mobile.</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /jadwalsaya (Jadwal Mengajar Pribadi Guru Hari Ini)
     */
    protected function commandMyTeachingSchedule(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $today = Carbon::today('Asia/Jakarta');
        $todayDay = $this->getTodayDayName();
        $academicYear = $this->getOperationalAcademicYear();

        $schedules = LessonSchedule::with(['classroom', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day', $todayDay)
            ->where('academic_year', $academicYear)
            ->orderBy('start_period', 'asc')
            ->get();

        $text = "📅 <b>JADWAL MENGAJAR SAYA HARI INI</b>\n"
            ."🗓️ <b>{$todayDay}, {$today->translatedFormat('d F Y')}</b> (TP. {$academicYear})\n"
            ."👤 <b>Guru:</b> {$user->name}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($schedules->isEmpty()) {
            $text .= "🌴 <i>Tidak ada jadwal KBM yang tercatat untuk Anda pada hari {$todayDay}. Selamat beraktivitas atau bertugas lainnya!</i>\n";
        } else {
            $times = LessonSchedule::periodTimes($todayDay);
            foreach ($schedules as $idx => $sch) {
                $cls = $sch->classroom?->name ?? 'Kelas -';
                $sub = $sch->subject?->name ?? 'Mata Pelajaran';
                $pStart = $sch->start_period;
                $pEnd = $sch->end_period;
                $timeStart = $times[$pStart]['start'] ?? ($sch->start_time ?? '-');
                $timeEnd = $times[$pEnd]['end'] ?? ($sch->end_time ?? '-');

                $num = $idx + 1;
                $text .= "<b>{$num}. {$cls} — {$sub}</b>\n"
                    ."   ⏰ Jam ke-{$pStart} s/d {$pEnd} ({$timeStart} - {$timeEnd} WIB)\n\n";
            }
            $text .= "<i>💡 Notifikasi otomatis akan dikirimkan 10 menit sebelum jam KBM dimulai.</i>\n";
        }

        $text .= '━━━━━━━━━━━━━━━━━━━━';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /agenda (Agenda Kegiatan & Kalender Akademik 7 Hari ke Depan)
     */
    protected function commandSchoolAgenda(string $chatId, ?User $user = null): void
    {
        $today = Carbon::today('Asia/Jakarta');
        $nextWeek = $today->copy()->addDays(7);

        $events = CurriculumEvent::with('schoolActivity')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('start_date', '<=', $nextWeek)
            ->orderBy('start_date', 'asc')
            ->take(8)
            ->get();

        $text = "🗓️ <b>AGENDA & KEGIATAN SEKOLAH TERDEKAT</b>\n"
            ."Periode: {$today->translatedFormat('d M')} s/d {$nextWeek->translatedFormat('d M Y')}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($events->isEmpty()) {
            $text .= "✅ <i>Belum ada agenda khusus atau kegiatan kalender akademik dalam 7 hari ke depan. KBM berjalan normal.</i>\n";
        } else {
            foreach ($events as $ev) {
                $start = Carbon::parse($ev->start_date)->translatedFormat('d M Y');
                $end = Carbon::parse($ev->end_date)->translatedFormat('d M Y');
                $dateRange = ($start === $end) ? $start : "{$start} s/d {$end}";
                $holidayBadge = $ev->is_holiday ? ' 🔴 [LIBUR]' : '';

                $text .= "• <b>{$ev->title}</b>{$holidayBadge}\n"
                    ."  📅 {$dateRange}\n"
                    .($ev->location ? "  📍 Lokasi: {$ev->location}\n" : '')
                    .($ev->description ? "  📝 <i>{$ev->description}</i>\n" : '')
                    ."\n";
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Sumber: Kalender Akademik & Agenda Kegiatan Kurikulum.</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'User', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /tamu (Buku Tamu Meja Piket Hari Ini)
     */
    protected function commandTodayGuests(string $chatId, ?User $user = null): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $guests = PiketGuest::whereDate('visit_date', $today)
            ->orderBy('check_in_time', 'desc')
            ->get();

        $text = "👥 <b>BUKU TAMU PIKET HARI INI</b>\n"
            ."🗓️ <b>{$today->translatedFormat('l, d F Y')}</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n";

        if ($guests->isEmpty()) {
            $text .= "📝 <i>Belum ada catatan buku tamu yang masuk hari ini.</i>\n";
        } else {
            foreach ($guests as $idx => $g) {
                $num = $idx + 1;
                $in = $g->check_in_time ? substr($g->check_in_time, 0, 5) : '-';
                $out = $g->check_out_time ? substr($g->check_out_time, 0, 5) : 'Belum selesai';
                $inst = $g->institution ? " ({$g->institution})" : '';

                $text .= "<b>{$num}. {$g->guest_name}</b>{$inst}\n"
                    ."   🕐 Waktu: {$in} - {$out} WIB\n"
                    ."   🎯 Keperluan: {$g->purpose}\n"
                    .($g->meet_with ? "   👤 Bertemu: {$g->meet_with}\n" : '')
                    ."\n";
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            ."<i>Total tamu berkunjung hari ini: {$guests->count()} orang.</i>";

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user?->name ?? 'User', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /poin <nama/nis> atau /kasus_siswa <nama/nis>
     */
    protected function commandStudentIncidentsCheck(string $chatId, string $rawText, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $parts = preg_split('/\s+/', trim($rawText), 2);
        $query = trim($parts[1] ?? '');

        if (empty($query)) {
            $msg = "🔍 <b>Pengecekan Kasus & Pelanggaran Siswa</b>\n\n"
                ."Silakan ketik perintah diikuti Nama atau NIS siswa:\n"
                ."👉 <code>/poin NAMA_ATAU_NIS</code>\n\n"
                ."<i>Contoh:</i>\n"
                .'<code>/poin Ahmad</code> atau <code>/poin 12345</code>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $students = Student::with('classroom')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('nis', $query)
                    ->orWhere('nisn', $query);
            })
            ->take(5)
            ->get();

        if ($students->isEmpty()) {
            $msg = "❌ <b>Siswa Tidak Ditemukan!</b>\n\n"
                ."Tidak ditemukan siswa dengan kata kunci: <i>\"{$query}\"</i>.";
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        if ($students->count() > 1) {
            $msg = "🔍 <b>Ditemukan beberapa siswa yang mirip:</b>\n"
                ."Silakan ketik ulang dengan nama lebih spesifik atau gunakan NIS:\n\n";
            foreach ($students as $st) {
                $cls = $st->classroom?->name ?? 'Kelas -';
                $msg .= "• <b>{$st->name}</b> (NIS: <code>{$st->nis}</code>) — {$cls}\n";
            }
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $student = $students->first();
        $academicYear = $this->getOperationalAcademicYear();
        $classroomName = $student->classroom?->name ?? 'Kelas -';

        $incidents = PiketIncident::where('student_id', $student->id)
            ->where('academic_year', $academicYear)
            ->orderBy('incident_date', 'desc')
            ->get();

        $latePermitsCount = PiketStudentPermit::where('student_id', $student->id)
            ->where('permit_type', 'terlambat')
            ->count();

        $text = "👤 <b>REKAM JEJAK KEDISIPLINAN SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."• <b>Nama:</b> {$student->name}\n"
            ."• <b>Kelas:</b> {$classroomName}\n"
            ."• <b>NIS:</b> {$student->nis}\n"
            ."• <b>Tahun Ajaran:</b> {$academicYear}\n\n"
            ."📊 <b>Ringkasan Pelanggaran:</b>\n"
            ."• Kasus Kejadian Piket: <b>{$incidents->count()} kali</b>\n"
            ."• Keterlambatan Hadir: <b>{$latePermitsCount} kali</b>\n\n";

        if ($incidents->isEmpty()) {
            $text .= "✅ <i>Alhamdulillah, siswa ini belum memiliki catatan kasus kejadian pelanggaran pada tahun ajaran ini.</i>\n";
        } else {
            $text .= "📋 <b>Riwayat Kasus Terakhir:</b>\n";
            foreach ($incidents->take(3) as $inc) {
                $date = Carbon::parse($inc->incident_date)->format('d/m/Y');
                $cat = ucfirst(str_replace('_', ' ', $inc->category));
                $vStatus = $inc->verification_status === 'terverifikasi' ? '✅ Terverifikasi' : '⏳ Menunggu';
                $sanction = $inc->disciplinary_sanction ? "\n     Sanksi: <i>{$inc->disciplinary_sanction}</i>" : '';

                $text .= "• <b>{$date}</b> [{$cat}] ({$vStatus})\n"
                    ."     <i>\"{$inc->description}\"</i>{$sanction}\n";
            }
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Data terhubung dengan Modul Meja Piket, Tim Kedisiplinan, & Guru BK.</i>';

        $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Command: /hubungkan atau /link (Menautkan akun Telegram ke data Guru secara mandiri)
     */
    protected function commandLinkAccount(string $chatId, string $text, ?User $currentUser, string $senderName): void
    {
        $parts = preg_split('/\s+/', trim($text), 2);
        $identifier = $parts[1] ?? '';

        if (empty($identifier)) {
            $msg = "ℹ️ <b>PANDUAN MENGHUBUNGKAN AKUN TELEGRAM</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Silakan ketik perintah diikuti dengan <b>NIP</b> atau <b>Username</b> akun SIM Kurikulum Anda:\n\n"
                ."<code>/hubungkan [NIP atau Username]</code>\n\n"
                ."<i>Contoh:\n"
                ."<code>/hubungkan 198501012010011005</code>\n"
                ."atau\n"
                ."<code>/hubungkan ahmad.soleh</code></i>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Chat ID Telegram Anda saat ini: <code>{$chatId}</code>";

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', $this->getMainMenuKeyboard($currentUser));

            return;
        }

        $teacher = User::where('is_active', true)
            ->where(function ($q) use ($identifier) {
                $q->where('nip', $identifier)
                    ->orWhere('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if (! $teacher) {
            $msg = "❌ <b>Data Guru Tidak Ditemukan!</b>\n\n"
                ."Tidak ditemukan akun aktif dengan NIP / Username / Email: <code>{$identifier}</code>.\n\n"
                .'Pastikan NIP atau Username yang Anda ketikkan sesuai dengan yang terdaftar pada sistem.';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', $this->getMainMenuKeyboard($currentUser));

            return;
        }

        $teacher->update([
            'telegram_chat_id' => $chatId,
        ]);

        $roleName = $teacher->role?->name ?? 'Guru/Staf';
        $msg = "🎉 <b>AKUN TELEGRAM BERHASIL DITAUTKAN!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Selamat Bpk/Ibu <b>{$teacher->name}</b>,\n"
            ."Akun Telegram Anda telah resmi terhubung ke SIM Kurikulum.\n\n"
            ."👤 <b>Nama:</b> {$teacher->name}\n"
            ."📌 <b>Peran:</b> {$roleName}\n"
            ."🆔 <b>Chat ID:</b> <code>{$chatId}</code>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Mulai sekarang, Anda akan menerima:\n"
            ."• Tembusan izin siswa yang berada di jam KBM Anda\n"
            ."• Tembusan penugasan kelas / inval\n"
            .'• Pengingat jadwal piket & informasi kurikulum';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $teacher->name, 'HTML', $this->getMainMenuKeyboard($teacher));
    }

    /**
     * Kirim notifikasi akses ditolak jika akun Telegram belum terhubung.
     */
    protected function replyAccessDenied(string $chatId): void
    {
        $msg = "⚠️ <b>Akses Ditolak</b>\n\n"
            ."Layanan interaktif ini hanya dapat diakses oleh Petugas Piket / Guru yang akun Telegram-nya telah ditautkan di SIM Kurikulum.\n\n"
            ."Chat ID Anda adalah: <code>{$chatId}</code>\n"
            .'Silakan hubungi administrator SIM Kurikulum untuk menautkan akun Anda.';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'User', 'HTML', $this->getMainMenuKeyboard());
    }

    /**
     * Dapatkan jam pelajaran saat ini berdasarkan rentang waktu lonceng sekolah.
     */
    protected function getCurrentPeriod(): ?int
    {
        $currentTime = Carbon::now()->format('H:i');
        $periodTimes = LessonSchedule::periodTimes();

        foreach ($periodTimes as $p => $times) {
            if ($currentTime >= $times['start'] && $currentTime < $times['end']) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Dapatkan nama hari hari ini dalam bahasa Indonesia.
     */
    protected function getTodayDayName(): string
    {
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        return $dayNames[Carbon::today()->dayOfWeekIso] ?? 'Senin';
    }

    // =========================================================================
    // 1. ALUR INTERAKTIF: PERIZINAN SISWA
    // =========================================================================

    /**
     * Mulai Wizard Penerbitan Izin Siswa (/izin_siswa)
     */
    protected function startStudentPermit(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $canPiket = $user->canOperatePiket();
        $isHomeroom = $user->isHomeroomTeacher();

        if (! $canPiket && ! $isHomeroom) {
            $msg = "⚠️ <b>Akses Dibatasi</b>\n\n"
                ."Fitur perizinan siswa dapat digunakan oleh <b>Petugas Piket yang bertugas hari ini</b> atau <b>Wali Kelas</b>.\n\n"
                ."Akun Anda (<b>{$user->name}</b>) tercatat tidak memiliki jadwal piket hari ini dan bukan merupakan Wali Kelas.";

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $isHomeroomOnly = ! $canPiket && $isHomeroom;
        $title = $isHomeroomOnly ? '👨‍🏫 <b>FORM PERIZINAN SISWA (WALI KELAS)</b>' : '🎫 <b>FORM PENERBITAN IZIN SISWA</b>';
        $roleLabel = $isHomeroomOnly ? 'Wali Kelas' : 'Petugas Piket';

        $cacheKey = "tg_piket_state_{$chatId}";
        Cache::put($cacheKey, [
            'flow' => 'student_permit',
            'step' => 'awaiting_student_name',
            'user_id' => $user->id,
            'student_id' => null,
            'student_name' => null,
            'classroom_id' => null,
            'permit_type' => null,
            'reason' => null,
        ], now()->addMinutes(15));

        $msg = "{$title}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Oleh: <b>{$user->name}</b> ({$roleLabel})\n\n"
            ."Silakan ketik <b>Nama Siswa</b> atau <b>NIS</b> yang mengajukan izin:\n"
            ."<i>(Contoh: Budi Santoso atau 2401)</i>\n\n"
            .'<i>(Ketik <code>/batal</code> untuk membatalkan pengisian)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name);
    }

    /**
     * Menangani input teks pada alur perizinan siswa.
     */
    protected function handleStudentPermitFlow(
        string $chatId,
        string $text,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";
        $step = $state['step'] ?? '';

        if ($step === 'awaiting_student_name') {
            $isHomeroomOnly = $user && ! $user->canOperatePiket() && $user->isHomeroomTeacher();

            $query = Student::where(function ($q) use ($text) {
                $q->where('name', 'like', "%{$text}%")
                    ->orWhere('nis', $text);
            });

            if ($isHomeroomOnly) {
                $homeroomClassIds = $user->homeroomClassrooms()->pluck('id')->toArray();
                $query->whereIn('classroom_id', $homeroomClassIds);
            }

            $students = $query->with('classroom')
                ->take(6)
                ->get();

            if ($students->isEmpty()) {
                $extraNote = $isHomeroomOnly ? ' di kelas yang Anda bimbing' : '';
                $this->telegramService->sendMessage(
                    $chatId,
                    "⚠️ Data siswa dengan nama / NIS <i>\"{$text}\"</i> tidak ditemukan{$extraNote}.\n\n"
                    ."Silakan ketik ulang nama atau NIS yang benar:\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>',
                    'bot_reply',
                    $senderName,
                    'HTML'
                );

                return;
            }

            if ($students->count() === 1) {
                $st = $students->first();
                $state['student_id'] = $st->id;
                $state['student_name'] = $st->name;
                $state['classroom_id'] = $st->classroom_id;
                $state['step'] = 'awaiting_permit_type';
                Cache::put($cacheKey, $state, now()->addMinutes(15));

                $this->promptStudentPermitType($chatId, $st->name, $st->classroom?->name);

                return;
            }

            // Jika lebih dari 1 siswa cocok, tampilkan tombol pilihan
            $inlineKeyboard = [];
            foreach ($students as $st) {
                $clsName = $st->classroom?->name ?? 'Kelas ?';
                $inlineKeyboard[] = [
                    ['text' => "👤 {$st->name} ({$clsName})", 'callback_data' => "sp_st_{$st->id}"],
                ];
            }
            $inlineKeyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

            $msg = "🔍 <b>Ditemukan beberapa nama siswa yang mirip:</b>\nSilakan pilih siswa yang dimaksud:";
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', [
                'inline_keyboard' => $inlineKeyboard,
            ]);

            return;
        }

        if ($step === 'awaiting_student_reason') {
            if (empty($text)) {
                $this->telegramService->sendMessage($chatId, 'Silakan ketik alasan izin siswa terlebih dahulu.');

                return;
            }

            $state['reason'] = $text;
            $this->completeStudentPermit($chatId, $state, $user ?? User::first(), $text);

            return;
        }
    }

    /**
     * Tampilkan tombol pilihan jenis izin siswa.
     */
    protected function promptStudentPermitType(string $chatId, string $studentName, ?string $className): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⏰ Terlambat Masuk', 'callback_data' => 'sp_type_terlambat'],
                    ['text' => '🚪 Izin Keluar Sementara', 'callback_data' => 'sp_type_izin_keluar_sementara'],
                ],
                [
                    ['text' => '🏠 Izin Pulang Cepat', 'callback_data' => 'sp_type_izin_pulang_cepat'],
                    ['text' => '📄 Tidak Masuk / Dispensasi', 'callback_data' => 'sp_type_izin_tidak_masuk'],
                ],
                [
                    ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                ],
            ],
        ];

        $cls = $className ?: 'Kelas Umum';
        $msg = "👤 <b>Siswa Terpilih:</b> {$studentName} ({$cls})\n\n"
            .'Silakan pilih <b>Jenis Izin</b> di bawah:';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas', 'HTML', $keyboard);
    }

    /**
     * Menangani callback inline button pada perizinan siswa.
     */
    protected function handleStudentPermitCallback(
        string $chatId,
        string $data,
        array $state,
        ?User $user
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";

        if (str_starts_with($data, 'sp_st_')) {
            $studentId = (int) substr($data, 6);
            $student = Student::with('classroom')->find($studentId);

            if ($student) {
                $state['student_id'] = $student->id;
                $state['student_name'] = $student->name;
                $state['classroom_id'] = $student->classroom_id;
                $state['step'] = 'awaiting_permit_type';
                Cache::put($cacheKey, $state, now()->addMinutes(15));

                $this->promptStudentPermitType($chatId, $student->name, $student->classroom?->name);
            }

            return;
        }

        if (str_starts_with($data, 'sp_type_')) {
            $type = substr($data, 8);
            $state['permit_type'] = $type;
            $state['step'] = 'awaiting_student_reason';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $labels = [
                'terlambat' => '⏰ Terlambat Masuk',
                'izin_keluar_sementara' => '🚪 Izin Keluar Sementara',
                'izin_pulang_cepat' => '🏠 Izin Pulang Cepat',
                'izin_tidak_masuk' => '📄 Izin Tidak Masuk / Dispensasi',
            ];
            $typeLabel = $labels[$type] ?? ucfirst($type);

            $msg = "📌 <b>Jenis Izin:</b> {$typeLabel}\n\n"
                ."Silakan ketik <b>Alasan Izin</b> siswa:\n"
                ."<i>(Contoh: Ban sepeda motor bocor / Sakit pusing perlu ke UKS atau pulang / Ada acara keluarga mendesak)</i>\n\n"
                .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas', 'HTML');

            return;
        }
    }

    /**
     * Simpan perizinan siswa dan forward otomatis ke guru mapel jam aktif dan wali kelas.
     */
    protected function completeStudentPermit(
        string $chatId,
        array $state,
        User $officer,
        string $reason
    ): void {
        $todayStr = Carbon::today()->toDateString();
        $setting = SchoolSetting::getSetting();
        $academicYear = $this->getOperationalAcademicYear();

        $journal = PiketJournal::firstOrCreate(
            ['date' => $todayStr],
            [
                'academic_year' => $academicYear,
                'semester' => $setting->semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $officer->id,
            ]
        );

        $countToday = PiketStudentPermit::whereDate('permit_date', $todayStr)->count() + 1;
        $permitNumber = sprintf('IZN-%s-%03d', Carbon::parse($todayStr)->format('Ymd'), $countToday);

        $currentTimeStr = Carbon::now()->format('H:i');
        $currentPeriod = $this->getCurrentPeriod();
        $periodAssigned = $currentPeriod ? "Jam ke-{$currentPeriod}" : null;

        $permit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => $permitNumber,
            'permit_date' => $todayStr,
            'student_id' => $state['student_id'] ?? null,
            'student_name' => $state['student_name'] ?? 'Siswa',
            'classroom_id' => $state['classroom_id'],
            'permit_type' => $state['permit_type'] ?? 'terlambat',
            'reason' => $reason,
            'entry_time' => ($state['permit_type'] === 'terlambat' ? $currentTimeStr : null),
            'departure_time' => ($state['permit_type'] !== 'terlambat' ? $currentTimeStr : null),
            'period_assigned' => $periodAssigned,
            'action_taken' => 'Diterbitkan surat izin oleh Petugas Piket',
            'given_by' => $officer->id,
            'status' => 'diberikan',
        ]);

        $classroom = $permit->classroom_id ? Classroom::with('homeroomTeacher')->find($permit->classroom_id) : null;
        $homeroomTeacher = $classroom?->homeroomTeacher;
        $todayDay = $this->getTodayDayName();

        // Cari guru mapel jam aktif KBM sekarang di kelas tersebut
        $subjectTeacher = null;
        $activeSubjectName = null;
        if ($currentPeriod && $permit->classroom_id) {
            $currentSchedule = LessonSchedule::where('academic_year', $academicYear)
                ->where('classroom_id', $permit->classroom_id)
                ->where('day', $todayDay)
                ->where('start_period', '<=', $currentPeriod)
                ->where('end_period', '>=', $currentPeriod)
                ->with(['teacher', 'subject'])
                ->first();

            $subjectTeacher = $currentSchedule?->teacher;
            $activeSubjectName = $currentSchedule?->subject?->name;
        }

        // Ambil semua guru yang mengajar di kelas tersebut hari ini (terutama jika izin seharian/tidak masuk)
        $allDayTeachers = null;
        if ($permit->classroom_id) {
            $allDayTeachers = LessonSchedule::where('academic_year', $academicYear)
                ->where('classroom_id', $permit->classroom_id)
                ->where('day', $todayDay)
                ->with('teacher')
                ->get()
                ->pluck('teacher')
                ->filter()
                ->unique('id');
        }

        // Teruskan notifikasi secara otomatis ke Guru Mapel jam sekarang, Wali Kelas, Petugas Piket, & Dewan Guru
        if ($permit->permit_type === 'terlambat') {
            $this->telegramService->notifyStudentLate($permit, $subjectTeacher, $homeroomTeacher);
        } else {
            $this->telegramService->notifyStudentPermit($permit, $homeroomTeacher, $subjectTeacher, $allDayTeachers);
        }

        $isSubmittedByHomeroom = $homeroomTeacher && $officer->id === $homeroomTeacher->id;
        $officerRole = $isSubmittedByHomeroom ? 'Wali Kelas' : 'Petugas Piket';

        $teacherInfo = $subjectTeacher
            ? ($subjectTeacher->telegram_chat_id ? "✅ Terkirim ke Bpk/Ibu <b>{$subjectTeacher->name}</b>" : "⚪ Bpk/Ibu <b>{$subjectTeacher->name}</b> (belum aktifkan Telegram)")
            : '<i>(Tidak ada jadwal guru di jam aktif ini)</i>';

        $waliInfo = $isSubmittedByHomeroom
            ? "✅ Diterbitkan langsung oleh Wali Kelas (<b>{$officer->name}</b>)"
            : ($homeroomTeacher
                ? ($homeroomTeacher->telegram_chat_id ? "✅ Terkirim ke Bpk/Ibu <b>{$homeroomTeacher->name}</b>" : "⚪ Bpk/Ibu <b>{$homeroomTeacher->name}</b> (belum aktifkan Telegram)")
                : '<i>(Wali kelas belum ditentukan)</i>');

        $periodText = $currentPeriod ? " (Jam ke-{$currentPeriod})" : '';
        $subjectText = $activeSubjectName ? " [Mapel: {$activeSubjectName}]" : '';

        $slipUrl = URL::temporarySignedRoute('piket.permits.signed-slip', now()->addDays(2), [
            'permit' => $permit->id,
            'autoprint' => 1,
        ]);

        $successMsg = "✅ <b>SURAT IZIN SISWA BERHASIL DITERBITKAN!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."No. Izin: <b>{$permit->permit_number}</b>\n"
            ."👤 <b>Siswa:</b> {$permit->student_name}\n"
            ."🏫 <b>Kelas:</b> {$classroom?->name}\n"
            ."📌 <b>Jenis Izin:</b> {$permit->permit_type_label}\n"
            ."⏰ <b>Waktu:</b> {$currentTimeStr} WIB{$periodText}\n"
            ."📝 <b>Alasan:</b> {$permit->reason}\n"
            ."👮 <b>Penerbit:</b> {$officer->name} ({$officerRole})\n\n"
            ."📢 <b>Notifikasi Telah Diteruskan ke:</b>\n"
            ."• Guru Mapel Jam Ini{$subjectText}: {$teacherInfo}\n"
            ."• Wali Kelas: {$waliInfo}\n"
            .($isSubmittedByHomeroom ? "• Petugas Piket Hari Ini: Disiarkan via Japri & Grup\n" : '')
            .($allDayTeachers && $allDayTeachers->isNotEmpty() ? "• Seluruh Guru Mapel Hari Ini: Disiarkan ({$allDayTeachers->count()} Guru)\n" : '')
            ."• Tim Kedisiplinan & BK: Disiarkan\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."🖨️ <b>Cetak Slip Printer Thermal:</b>\n"
            ."👉 <a href=\"{$slipUrl}\">Buka & Cetak Slip Thermal (58mm / 80mm)</a>\n\n"
            .'<i>Surat izin telah resmi tersimpan di SIM Kurikulum & Sistem Piket.</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $successMsg,
            'bot_reply',
            $officer->name,
            'HTML',
            $this->getMainMenuKeyboard($officer)
        );

        Cache::forget("tg_piket_state_{$chatId}");
    }

    // =========================================================================
    // 2. ALUR INTERAKTIF: PERIZINAN GURU & TUGAS KELAS (INVAL)
    // =========================================================================

    /**
     * Mulai Wizard Perizinan Guru & Penugasan Inval (/izin_guru)
     */
    protected function startTeacherPermit(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        if (! $user->canOperatePiket()) {
            $msg = "⚠️ <b>Akses Khusus Petugas Piket</b>\n\n"
                ."Fitur <b>📋 Perizinan Guru</b> digunakan oleh <b>Petugas Piket yang bertugas hari ini</b> untuk mencatat izin rekan guru lain.\n\n"
                .'Untuk mengajukan izin bagi <b>diri Anda sendiri</b> beserta materi tugas kelas, silakan gunakan tombol <b>📝 Ajukan Izin Saya</b> atau ketik <code>/ajukan_izin</code>.';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";
        Cache::put($cacheKey, [
            'flow' => 'teacher_permit',
            'step' => 'awaiting_teacher_name',
            'user_id' => $user->id,
            'teacher_id' => null,
            'day' => null,
            'date' => null,
            'selected_schedule_ids' => [],
            'reason' => null,
            'task_material' => null,
        ], now()->addMinutes(15));

        $msg = "📋 <b>PENCATATAN PERIZINAN GURU & TUGAS (INVAL)</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Petugas: <b>{$user->name}</b>\n\n"
            ."Silakan ketik <b>Nama Guru</b> yang berhalangan hadir / izin:\n"
            ."<i>(Contoh: Ahmad Soleh)</i>\n\n"
            .'<i>(Ketik <code>/batal</code> untuk membatalkan proses)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name);
    }

    /**
     * Menangani input teks pada alur perizinan guru.
     */
    protected function handleTeacherPermitFlow(
        string $chatId,
        string $text,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";
        $step = $state['step'] ?? '';

        if ($step === 'awaiting_teacher_name') {
            $teachers = User::operationalPersonnel()
                ->where('is_active', true)
                ->where(function ($q) use ($text) {
                    $q->where('name', 'like', "%{$text}%")
                        ->orWhere('nip', $text);
                })
                ->take(6)
                ->get();

            if ($teachers->isEmpty()) {
                $this->telegramService->sendMessage(
                    $chatId,
                    "⚠️ Guru dengan nama / NIP <i>\"{$text}\"</i> tidak ditemukan.\n\n"
                    ."Silakan ketik kembali nama guru yang sesuai:\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>',
                    'bot_reply',
                    $senderName,
                    'HTML'
                );

                return;
            }

            if ($teachers->count() === 1) {
                $teacher = $teachers->first();
                $state['teacher_id'] = $teacher->id;
                Cache::put($cacheKey, $state, now()->addMinutes(15));

                $this->promptTeacherDaySelection($chatId, $state, $teacher);

                return;
            }

            $inlineKeyboard = [];
            foreach ($teachers as $t) {
                $inlineKeyboard[] = [
                    ['text' => "👨‍🏫 {$t->name}", 'callback_data' => "tp_t_{$t->id}"],
                ];
            }
            $inlineKeyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

            $msg = "🔍 <b>Ditemukan beberapa nama guru yang sesuai:</b>\nSilakan pilih guru yang dimaksud:";
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', [
                'inline_keyboard' => $inlineKeyboard,
            ]);

            return;
        }

        if ($step === 'awaiting_teacher_custom_reason') {
            if (empty($text)) {
                $this->telegramService->sendMessage($chatId, 'Silakan ketikkan alasan izin guru terlebih dahulu.');

                return;
            }

            $state['reason'] = $text;
            $state['step'] = 'awaiting_teacher_task';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->promptTeacherTaskInput($chatId, $state);

            return;
        }

        if ($step === 'awaiting_teacher_task') {
            $task = ($text === '/lewati' || mb_strtolower($text) === 'lewati')
                ? 'Tidak ada tugas tertulis dari guru mapel.'
                : $text;

            $this->completeTeacherPermit($chatId, $state, $user ?? User::first(), $task);

            return;
        }
    }

    /**
     * Tampilkan pilihan hari izin guru.
     */
    protected function promptTeacherDaySelection(string $chatId, array $state, User $teacher): void
    {
        $todayName = $this->getTodayDayName();

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "📅 Hari Ini ({$todayName})", 'callback_data' => 'tp_day_today'],
                ],
                [
                    ['text' => 'Senin', 'callback_data' => 'tp_day_Senin'],
                    ['text' => 'Selasa', 'callback_data' => 'tp_day_Selasa'],
                ],
                [
                    ['text' => 'Rabu', 'callback_data' => 'tp_day_Rabu'],
                    ['text' => 'Kamis', 'callback_data' => 'tp_day_Kamis'],
                ],
                [
                    ['text' => 'Jumat', 'callback_data' => 'tp_day_Jumat'],
                    ['text' => 'Sabtu', 'callback_data' => 'tp_day_Sabtu'],
                ],
                [
                    ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                ],
            ],
        ];

        $msg = "👨‍🏫 <b>Guru Terpilih:</b> {$teacher->name}\n\n"
            .'Pilih <b>Hari Izin</b> guru yang bersangkutan:';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas', 'HTML', $keyboard);
    }

    /**
     * Menangani callback inline button pada perizinan guru.
     */
    protected function handleTeacherPermitCallback(
        string $chatId,
        string $data,
        array $state,
        ?User $user
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";

        if (str_starts_with($data, 'tp_t_')) {
            $teacherId = (int) substr($data, 5);
            $teacher = User::find($teacherId);

            if ($teacher) {
                $state['teacher_id'] = $teacher->id;
                Cache::put($cacheKey, $state, now()->addMinutes(15));
                $this->promptTeacherDaySelection($chatId, $state, $teacher);
            }

            return;
        }

        if (str_starts_with($data, 'tp_day_')) {
            $dayParam = substr($data, 7);
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $todayName = $this->getTodayDayName();
            $targetDay = $dayParam === 'today' ? $todayName : $dayParam;

            // Hitung tanggal target
            $targetDate = Carbon::today();
            for ($i = 0; $i < 7; $i++) {
                $check = Carbon::today()->addDays($i);
                if (($dayNames[$check->dayOfWeekIso] ?? '') === $targetDay) {
                    $targetDate = $check;
                    break;
                }
            }

            $state['day'] = $targetDay;
            $state['date'] = $targetDate->format('Y-m-d');

            $teacher = User::find($state['teacher_id']);
            if (! $teacher) {
                $this->telegramService->sendMessage($chatId, 'Data guru tidak ditemukan. Silakan mulai kembali.');
                Cache::forget($cacheKey);

                return;
            }

            $academicYear = $this->getOperationalAcademicYear();

            // Ambil jadwal mengajar guru pada hari tersebut
            $schedules = LessonSchedule::where('teacher_id', $teacher->id)
                ->where('day', $targetDay)
                ->where('academic_year', $academicYear)
                ->with(['classroom', 'subject'])
                ->orderBy('start_period')
                ->get();

            if ($schedules->isEmpty()) {
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '✅ Lanjutkan Izin Umum', 'callback_data' => 'tp_cls_none']],
                        [['text' => '🔙 Pilih Hari Lain', 'callback_data' => "tp_t_{$teacher->id}"]],
                        [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']],
                    ],
                ];

                $msg = "ℹ️ Bpk/Ibu <b>{$teacher->name}</b> tidak memiliki jadwal mengajar di kelas pada hari <b>{$targetDay}</b>.\n\n"
                    .'Apakah tetap ingin mencatat perizinan ketidakhadiran (izin umum/dinas)?';

                $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas', 'HTML', $keyboard);

                return;
            }

            // Tampilkan pilihan kelas hari tersebut
            $keyboard = [];
            $keyboard[] = [
                ['text' => "✨ Semua Kelas Hari {$targetDay} ({$schedules->count()} Kelas)", 'callback_data' => 'tp_cls_all'],
            ];

            $text = "📚 <b>Jadwal Mengajar {$teacher->name} (Hari {$targetDay}):</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n";

            $no = 1;
            foreach ($schedules as $sched) {
                $clsName = $sched->classroom?->name ?? 'Kelas ?';
                $subName = $sched->subject?->name ?? 'Mapel';
                $periodStr = "Jam ke-{$sched->start_period}".($sched->end_period > $sched->start_period ? " s/d {$sched->end_period}" : '');

                $text .= "{$no}. <b>{$clsName}</b> - {$subName} ({$periodStr})\n";
                $keyboard[] = [
                    ['text' => "{$no}. {$clsName} ({$periodStr})", 'callback_data' => "tp_cls_{$sched->id}"],
                ];
                $no++;
            }

            $keyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

            $text .= "━━━━━━━━━━━━━━━━━━━━\n"
                .'Silakan pilih kelas yang ingin diberikan penugasan / materi:';

            $state['step'] = 'awaiting_class_selection';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->telegramService->sendMessage($chatId, $text, 'bot_reply', 'Petugas', 'HTML', [
                'inline_keyboard' => $keyboard,
            ]);

            return;
        }

        if (str_starts_with($data, 'tp_cls_')) {
            $clsParam = substr($data, 7);

            if ($clsParam === 'all') {
                $academicYear = $this->getOperationalAcademicYear();
                $schedIds = LessonSchedule::where('teacher_id', $state['teacher_id'])
                    ->where('day', $state['day'])
                    ->where('academic_year', $academicYear)
                    ->pluck('id')
                    ->toArray();

                $state['selected_schedule_ids'] = $schedIds;
            } elseif ($clsParam === 'none') {
                $state['selected_schedule_ids'] = [];
            } else {
                $state['selected_schedule_ids'] = [(int) $clsParam];
            }

            $state['step'] = 'awaiting_teacher_reason';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $reasonKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🤒 Sakit', 'callback_data' => 'tp_rsn_Sakit'],
                        ['text' => '🏖️ Cuti', 'callback_data' => 'tp_rsn_Cuti'],
                    ],
                    [
                        ['text' => '🚗 Dinas Luar / Diklat', 'callback_data' => 'tp_rsn_Dinas Luar'],
                        ['text' => '🏠 Izin Pribadi / Keluarga', 'callback_data' => 'tp_rsn_Izin Pribadi'],
                    ],
                    [
                        ['text' => '📝 Alasan Lainnya', 'callback_data' => 'tp_rsn_Lainnya'],
                        ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                    ],
                ],
            ];

            $msg = 'Silakan pilih <b>Alasan Izin / Berhalangan</b> guru:';
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas', 'HTML', $reasonKeyboard);

            return;
        }

        if (str_starts_with($data, 'tp_rsn_')) {
            $reasonParam = substr($data, 7);

            if ($reasonParam === 'Lainnya') {
                $state['step'] = 'awaiting_teacher_custom_reason';
                Cache::put($cacheKey, $state, now()->addMinutes(15));

                $msg = "📝 Silakan ketikkan <b>Alasan Izin</b> guru:\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';
                $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas');

                return;
            }

            $state['reason'] = $reasonParam;
            $state['step'] = 'awaiting_teacher_task';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->promptTeacherTaskInput($chatId, $state);

            return;
        }
    }

    /**
     * Tampilkan prompt input tugas/materi pembelajaran untuk kelas.
     */
    protected function promptTeacherTaskInput(string $chatId, array $state): void
    {
        $teacher = User::find($state['teacher_id']);
        $teacherName = $teacher?->name ?? 'Guru';
        $dayName = $state['day'] ?? 'hari tugas';

        $msg = "📝 <b>INPUT TUGAS / MATERI PEMBELAJARAN</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Guru: <b>{$teacherName}</b> (Hari {$dayName})\n"
            ."Alasan: <b>{$state['reason']}</b>\n\n"
            ."Silakan ketikkan <b>Tugas atau Materi Pembelajaran</b> yang ditinggalkan untuk kelas tersebut:\n\n"
            ."<i>Contoh:\nMengerjakan LKS Bab 3 Halaman 45 Nomor 1-10 di buku catatan, dikumpulkan ke ketua kelas dan diserahkan ke meja piket.</i>\n\n"
            .'<i>(Ketik <code>/lewati</code> jika tidak ada tugas khusus, atau <code>/batal</code> untuk membatalkan)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas');
    }

    /**
     * Simpan data guru izin & tugas kelas (inval), serta kirim tembusan ke Waka Kurikulum.
     */
    protected function completeTeacherPermit(
        string $chatId,
        array $state,
        User $officer,
        string $task
    ): void {
        $teacher = User::find($state['teacher_id']);
        if (! $teacher) {
            $this->telegramService->sendMessage($chatId, 'Data guru tidak ditemukan.');
            Cache::forget("tg_piket_state_{$chatId}");

            return;
        }

        $targetDay = $state['day'] ?? $this->getTodayDayName();
        $targetDate = $state['date'] ?? Carbon::today()->format('Y-m-d');
        $targetDateFormatted = Carbon::parse($targetDate)->translatedFormat('l, d F Y');

        $setting = SchoolSetting::getSetting();
        $academicYear = $this->getOperationalAcademicYear();

        $journal = PiketJournal::firstOrCreate(
            ['date' => $targetDate],
            [
                'academic_year' => $academicYear,
                'semester' => $setting->semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $officer->id,
            ]
        );

        $selectedIds = $state['selected_schedule_ids'] ?? [];
        $classList = [];

        if (! empty($selectedIds)) {
            foreach ($selectedIds as $schedId) {
                $sched = LessonSchedule::with(['classroom', 'subject'])->find($schedId);
                if ($sched) {
                    PiketInvalClass::create([
                        'piket_journal_id' => $journal->id,
                        'classroom_id' => $sched->classroom_id,
                        'absent_teacher_id' => $teacher->id,
                        'subject_id' => $sched->subject_id,
                        'inval_teacher_id' => null,
                        'period_start' => $sched->start_period,
                        'period_end' => $sched->end_period,
                        'reason' => $state['reason'] ?? 'Izin',
                        'assignment_material' => $task,
                        'notes' => 'Dicatat melalui Telegram Bot Piket',
                    ]);

                    $clsName = $sched->classroom?->name ?? 'Kelas ?';
                    $subName = $sched->subject?->name ?? 'Mapel';
                    $periodStr = "Jam ke-{$sched->start_period}".($sched->end_period > $sched->start_period ? " s/d {$sched->end_period}" : '');

                    $classList[] = [
                        'class' => $clsName,
                        'subject' => $subName,
                        'period' => $periodStr,
                        'task' => $task,
                    ];
                }
            }
        } else {
            $classList[] = [
                'class' => 'Umum (Tanpa Jam Mengajar)',
                'subject' => 'Umum',
                'period' => 'Seharian',
                'task' => $task,
            ];
        }

        // Simpan juga ke modul perizinan resmi GTK agar otomatis menggugurkan status Alfa pada rekap presensi
        $reasonLower = strtolower($state['reason'] ?? '');
        $permitType = match (true) {
            str_contains($reasonLower, 'sakit') => 'sakit',
            str_contains($reasonLower, 'cuti') => 'cuti',
            str_contains($reasonLower, 'dinas') => 'dinas_luar',
            default => 'izin',
        };

        GtkPermit::create([
            'user_id' => $teacher->id,
            'permission_type' => $permitType,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'duration_days' => 1,
            'reason' => $state['reason'].($task ? " (Tugas: {$task})" : ''),
            'notes' => "Dicatat oleh Petugas Piket {$officer->name} via Bot Telegram",
            'approved_by' => $officer->id,
        ]);

        // Cari Waka Kurikulum & Kepala Sekolah untuk dikirimkan tembusan resmi
        $wakaKurikulum = $setting->curriculumHead
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

        $principal = $setting->principal
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();

        $this->telegramService->notifyTeacherInvalToWaka(
            $teacher,
            $targetDay,
            $targetDateFormatted,
            $state['reason'] ?? 'Izin',
            $classList,
            $officer,
            $wakaKurikulum,
            $principal
        );

        $classesSummary = '';
        foreach ($classList as $idx => $item) {
            $no = $idx + 1;
            $classesSummary .= "{$no}. <b>{$item['class']}</b> - {$item['subject']} ({$item['period']})\n";
        }

        $wakaStatus = $wakaKurikulum
            ? ($wakaKurikulum->telegram_chat_id ? "✅ Terkirim ke Telegram Bpk/Ibu <b>{$wakaKurikulum->name}</b>" : "⚪ Bpk/Ibu <b>{$wakaKurikulum->name}</b> (belum aktifkan Telegram)")
            : '<i>(Data Waka Kurikulum belum ditentukan)</i>';

        $principalStatus = $principal
            ? ($principal->telegram_chat_id ? "✅ Terkirim ke Telegram Bpk/Ibu <b>{$principal->name}</b>" : "⚪ Bpk/Ibu <b>{$principal->name}</b> (belum aktifkan Telegram)")
            : '<i>(Data Kepala Sekolah belum ditentukan)</i>';

        $successMsg = "✅ <b>PERIZINAN GURU & TUGAS KELAS BERHASIL DICATAT!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Guru Izin:</b> {$teacher->name}\n"
            ."🗓️ <b>Hari/Tanggal:</b> {$targetDay}, {$targetDateFormatted}\n"
            ."📌 <b>Alasan:</b> {$state['reason']}\n\n"
            ."📚 <b>Kelas Terinval:</b>\n{$classesSummary}\n"
            ."📝 <b>Tugas Pembelajaran:</b>\n<i>\"{$task}\"</i>\n\n"
            ."👮 <b>Pencatat Piket:</b> {$officer->name}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📢 <b>Tembusan Laporan Telah Diteruskan ke:</b>\n"
            ."• Kepala Sekolah: {$principalStatus}\n"
            ."• Waka Kurikulum: {$wakaStatus}\n"
            ."• Grup Tim Piket Harian Sekolah: Disiarkan\n"
            ."• Sistem Jurnal Piket & Inval SIM Kurikulum\n"
            .'━━━━━━━━━━━━━━━━━━━━';

        $this->telegramService->sendMessage(
            $chatId,
            $successMsg,
            'bot_reply',
            $officer->name,
            'HTML',
            $this->getMainMenuKeyboard($officer)
        );

        Cache::forget("tg_piket_state_{$chatId}");
    }

    // =========================================================================
    // 2B. ALUR INTERAKTIF: PENGAJUAN IZIN MANDIRI GURU & PENYERAHAN TUGAS
    // =========================================================================

    /**
     * Mulai Wizard Pengajuan Izin Mandiri Guru & Penyerahan Tugas (/ajukan_izin)
     */
    protected function startSelfTeacherPermit(string $chatId, ?User $user): void
    {
        if (! $user) {
            $msg = "⚠️ <b>Akun Telegram Belum Terhubung</b>\n\n"
                ."Untuk mengajukan perizinan secara mandiri, akun Telegram Anda harus ditautkan ke SIM Kurikulum terlebih dahulu.\n\n"
                ."Silakan ketik perintah:\n"
                ."<code>/hubungkan [NIP atau Username Anda]</code>\n\n"
                .'<i>Contoh: <code>/hubungkan 198501012010011005</code></i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'User', 'HTML', $this->getMainMenuKeyboard());

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";
        Cache::put($cacheKey, [
            'flow' => 'self_teacher_permit',
            'step' => 'awaiting_day',
            'user_id' => $user->id,
            'teacher_id' => $user->id,
            'day' => null,
            'date' => null,
            'selected_schedule_ids' => [],
            'reason' => null,
            'task_material' => null,
        ], now()->addMinutes(15));

        $this->promptSelfTeacherDaySelection($chatId, $user);
    }

    /**
     * Tampilkan pilihan hari izin untuk guru bersangkutan.
     */
    protected function promptSelfTeacherDaySelection(string $chatId, User $user): void
    {
        $todayName = $this->getTodayDayName();
        $todayFormatted = Carbon::today()->translatedFormat('d F Y');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "📅 Hari Ini ({$todayName}, {$todayFormatted})", 'callback_data' => 'stp_day_today'],
                ],
                [
                    ['text' => 'Senin', 'callback_data' => 'stp_day_Senin'],
                    ['text' => 'Selasa', 'callback_data' => 'stp_day_Selasa'],
                ],
                [
                    ['text' => 'Rabu', 'callback_data' => 'stp_day_Rabu'],
                    ['text' => 'Kamis', 'callback_data' => 'stp_day_Kamis'],
                ],
                [
                    ['text' => 'Jumat', 'callback_data' => 'stp_day_Jumat'],
                    ['text' => 'Sabtu', 'callback_data' => 'stp_day_Sabtu'],
                ],
                [
                    ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                ],
            ],
        ];

        $msg = "📝 <b>PENGAJUAN IZIN GURU & PENYERAHAN TUGAS</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Guru: <b>{$user->name}</b>\n"
            ."ID: <code>{$user->formatted_id}</code>\n\n"
            ."Silakan pilih <b>Hari / Tanggal</b> izin Anda:\n"
            .'<i>(Laporan izin & tugas otomatis diteruskan ke Petugas Piket, Waka Kurikulum, & Kepala Sekolah)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $keyboard);
    }

    /**
     * Menangani callback inline button pada izin mandiri guru.
     */
    protected function handleSelfTeacherPermitCallback(
        string $chatId,
        string $data,
        array $state,
        ?User $user
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";

        if (! $user) {
            $user = User::find($state['teacher_id'] ?? null);
        }

        if (! $user) {
            $this->replyAccessDenied($chatId);
            Cache::forget($cacheKey);

            return;
        }

        if ($data === 'stp_back_day') {
            $this->promptSelfTeacherDaySelection($chatId, $user);

            return;
        }

        if (str_starts_with($data, 'stp_day_')) {
            $dayParam = substr($data, 8);
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $todayName = $this->getTodayDayName();
            $targetDay = $dayParam === 'today' ? $todayName : $dayParam;

            // Hitung tanggal target
            $targetDate = Carbon::today();
            for ($i = 0; $i < 7; $i++) {
                $check = Carbon::today()->addDays($i);
                if (($dayNames[$check->dayOfWeekIso] ?? '') === $targetDay) {
                    $targetDate = $check;
                    break;
                }
            }

            $state['day'] = $targetDay;
            $state['date'] = $targetDate->format('Y-m-d');

            $academicYear = $this->getOperationalAcademicYear();

            // Ambil jadwal mengajar guru pada hari tersebut
            $schedules = LessonSchedule::where('teacher_id', $user->id)
                ->where('day', $targetDay)
                ->where('academic_year', $academicYear)
                ->with(['classroom', 'subject'])
                ->orderBy('start_period')
                ->get();

            if ($schedules->isEmpty()) {
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '✅ Lanjutkan Izin Umum', 'callback_data' => 'stp_cls_none']],
                        [['text' => '🔙 Pilih Hari Lain', 'callback_data' => 'stp_back_day']],
                        [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']],
                    ],
                ];

                $msg = "ℹ️ Anda tidak memiliki jadwal KBM di kelas pada hari <b>{$targetDay}</b>.\n\n"
                    .'Apakah tetap ingin mengajukan izin ketidakhadiran (izin umum/dinas)?';

                $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $keyboard);

                return;
            }

            // Tampilkan pilihan kelas hari tersebut
            $keyboard = [];
            $keyboard[] = [
                ['text' => "✨ Semua Kelas Hari {$targetDay} ({$schedules->count()} Kelas)", 'callback_data' => 'stp_cls_all'],
            ];

            $text = "📚 <b>Jadwal Mengajar Anda (Hari {$targetDay}):</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n";

            $no = 1;
            foreach ($schedules as $sched) {
                $clsName = $sched->classroom?->name ?? 'Kelas ?';
                $subName = $sched->subject?->name ?? 'Mapel';
                $periodStr = "Jam ke-{$sched->start_period}".($sched->end_period > $sched->start_period ? " s/d {$sched->end_period}" : '');

                $text .= "{$no}. <b>{$clsName}</b> - {$subName} ({$periodStr})\n";
                $keyboard[] = [
                    ['text' => "{$no}. {$clsName} ({$periodStr})", 'callback_data' => "stp_cls_{$sched->id}"],
                ];
                $no++;
            }

            $keyboard[] = [
                ['text' => '🔙 Pilih Hari Lain', 'callback_data' => 'stp_back_day'],
                ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
            ];

            $text .= "━━━━━━━━━━━━━━━━━━━━\n"
                .'Silakan pilih kelas yang ingin diberikan penugasan / materi:';

            $state['step'] = 'awaiting_class_selection';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->telegramService->sendMessage($chatId, $text, 'bot_reply', $user->name, 'HTML', [
                'inline_keyboard' => $keyboard,
            ]);

            return;
        }

        if (str_starts_with($data, 'stp_cls_')) {
            $clsParam = substr($data, 8);

            if ($clsParam === 'all') {
                $academicYear = $this->getOperationalAcademicYear();
                $schedIds = LessonSchedule::where('teacher_id', $user->id)
                    ->where('day', $state['day'])
                    ->where('academic_year', $academicYear)
                    ->pluck('id')
                    ->toArray();

                $state['selected_schedule_ids'] = $schedIds;
            } elseif ($clsParam === 'none') {
                $state['selected_schedule_ids'] = [];
            } else {
                $state['selected_schedule_ids'] = [(int) $clsParam];
            }

            $state['step'] = 'awaiting_teacher_reason';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $reasonKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🤒 Sakit', 'callback_data' => 'stp_rsn_Sakit'],
                        ['text' => '🏖️ Cuti', 'callback_data' => 'stp_rsn_Cuti'],
                    ],
                    [
                        ['text' => '🚗 Dinas Luar / Diklat', 'callback_data' => 'stp_rsn_Dinas Luar'],
                        ['text' => '🏠 Izin Pribadi / Keluarga', 'callback_data' => 'stp_rsn_Izin Pribadi'],
                    ],
                    [
                        ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                    ],
                ],
            ];

            $msg = "📋 <b>PILIH / KETIK ALASAN IZIN</b>\n\n"
                ."Silakan pilih salah satu alasan cepat di bawah, atau ketik langsung alasan Anda secara manual via pesan chat:\n"
                .'<i>(Contoh: Mengikuti MGMP / Menghadiri Acara Keluarga)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $reasonKeyboard);

            return;
        }

        if (str_starts_with($data, 'stp_rsn_')) {
            $reason = substr($data, 8);
            $state['reason'] = $reason;
            $state['step'] = 'awaiting_task_material';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $msg = "📝 <b>MATERI TUGAS UNTUK KELAS</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Guru: <b>{$user->name}</b>\n"
                ."Alasan: <b>{$reason}</b>\n\n"
                ."Silakan ketikkan <b>Tugas / Bahan Pembelajaran</b> untuk kelas yang Anda tinggalkan:\n\n"
                ."<i>Contoh:\nMembaca Buku Paket Bab 3 Hal 45-50 dan mengerjakan latihan no 1-5 di buku tulis. Dikumpulkan ke guru inval / petugas piket.</i>\n\n"
                .'<i>(Ketik <code>/batal</code> jika ingin membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name);

            return;
        }
    }

    /**
     * Menangani input teks untuk alur izin mandiri guru.
     */
    protected function handleSelfTeacherPermitFlow(
        string $chatId,
        string $text,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $step = $state['step'] ?? '';

        if (! $user) {
            $user = User::find($state['teacher_id'] ?? null);
        }

        if ($step === 'awaiting_teacher_reason') {
            $state['reason'] = trim($text);
            $state['step'] = 'awaiting_task_material';
            Cache::put("tg_piket_state_{$chatId}", $state, now()->addMinutes(15));

            $msg = "📝 <b>MATERI TUGAS UNTUK KELAS</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Alasan: <b>{$state['reason']}</b>\n\n"
                ."Silakan ketikkan <b>Tugas / Bahan Pembelajaran</b> untuk kelas yang Anda tinggalkan:\n\n"
                ."<i>Contoh:\nMembaca Buku Paket Bab 3 Hal 45-50 dan mengerjakan latihan no 1-5 di buku tulis. Dikumpulkan ke guru inval / petugas piket.</i>\n\n"
                .'<i>(Ketik <code>/batal</code> jika ingin membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user?->name ?? $senderName);

            return;
        }

        if ($step === 'awaiting_task_material') {
            $task = trim($text);
            $this->completeSelfTeacherPermit($chatId, $state, $user, $task);

            return;
        }
    }

    /**
     * Simpan data pengajuan izin mandiri guru & tugas kelas (inval),
     * serta kirim notifikasi ke Petugas Piket Hari Ini, Waka Kurikulum, & Kepala Sekolah.
     */
    protected function completeSelfTeacherPermit(
        string $chatId,
        array $state,
        User $user,
        string $task
    ): void {
        $setting = SchoolSetting::getSetting();
        $academicYear = $this->getOperationalAcademicYear();
        $targetDate = $state['date'] ?? Carbon::today()->format('Y-m-d');
        $targetDay = $state['day'] ?? $this->getTodayDayName();
        $targetDateFormatted = Carbon::parse($targetDate)->translatedFormat('l, d F Y');

        // Pastikan ada Jurnal Piket untuk hari tersebut
        $journal = PiketJournal::firstOrCreate(
            ['date' => $targetDate],
            [
                'academic_year' => $academicYear,
                'semester' => $setting->semester ?? 'Ganjil',
                'status' => 'berlangsung',
                'general_notes' => 'Dibuat otomatis dari Pengajuan Izin Mandiri Guru Bot Telegram',
            ]
        );

        $classList = [];
        $selectedSchedIds = $state['selected_schedule_ids'] ?? [];

        foreach ($selectedSchedIds as $schedId) {
            $sched = LessonSchedule::with(['classroom', 'subject'])->find($schedId);
            if (! $sched) {
                continue;
            }

            PiketInvalClass::create([
                'piket_journal_id' => $journal->id,
                'classroom_id' => $sched->classroom_id,
                'subject_id' => $sched->subject_id,
                'absent_teacher_id' => $user->id,
                'inval_teacher_id' => null,
                'period_start' => $sched->start_period,
                'period_end' => $sched->end_period,
                'reason' => $state['reason'] ?? 'Izin',
                'assignment_material' => $task,
                'notes' => 'Pengajuan Izin Mandiri Guru via Bot Telegram',
            ]);

            $clsName = $sched->classroom?->name ?? 'Kelas ?';
            $subName = $sched->subject?->name ?? 'Mapel';
            $periodStr = "Jam ke-{$sched->start_period}".($sched->end_period > $sched->start_period ? " s/d {$sched->end_period}" : '');

            $classList[] = [
                'class' => $clsName,
                'subject' => $subName,
                'period' => $periodStr,
                'task' => $task,
            ];
        }

        if (empty($classList)) {
            $classList[] = [
                'class' => '-',
                'subject' => 'Izin Tidak Masuk',
                'period' => 'Seharian',
                'task' => $task,
            ];
        }

        // Simpan juga ke modul perizinan resmi GTK agar otomatis menggugurkan status Alfa pada rekap presensi
        $reasonLower = strtolower($state['reason'] ?? '');
        $permitType = match (true) {
            str_contains($reasonLower, 'sakit') => 'sakit',
            str_contains($reasonLower, 'cuti') => 'cuti',
            str_contains($reasonLower, 'dinas') => 'dinas_luar',
            default => 'izin',
        };

        GtkPermit::create([
            'user_id' => $user->id,
            'permission_type' => $permitType,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'duration_days' => 1,
            'reason' => $state['reason'].($task ? " (Tugas: {$task})" : ''),
            'notes' => 'Diajukan mandiri via Bot Telegram',
            'approved_by' => $user->id,
        ]);

        // Cari Waka Kurikulum & Kepala Sekolah untuk dikirimkan tembusan resmi
        $wakaKurikulum = $setting->curriculumHead
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();

        $principal = $setting->principal
            ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();

        $this->telegramService->notifyTeacherInvalToWaka(
            $user,
            $targetDay,
            $targetDateFormatted,
            $state['reason'] ?? 'Izin',
            $classList,
            $user,
            $wakaKurikulum,
            $principal
        );

        $classesSummary = '';
        foreach ($classList as $idx => $item) {
            $no = $idx + 1;
            $classesSummary .= "{$no}. <b>{$item['class']}</b> - {$item['subject']} ({$item['period']})\n";
        }

        $wakaStatus = $wakaKurikulum
            ? ($wakaKurikulum->telegram_chat_id ? "✅ Terkirim ke Telegram Bpk/Ibu <b>{$wakaKurikulum->name}</b>" : "⚪ Bpk/Ibu <b>{$wakaKurikulum->name}</b> (belum aktifkan Telegram)")
            : '<i>(Data Waka Kurikulum belum ditentukan)</i>';

        $principalStatus = $principal
            ? ($principal->telegram_chat_id ? "✅ Terkirim ke Telegram Bpk/Ibu <b>{$principal->name}</b>" : "⚪ Bpk/Ibu <b>{$principal->name}</b> (belum aktifkan Telegram)")
            : '<i>(Data Kepala Sekolah belum ditentukan)</i>';

        $classesBlock = ! empty($classesSummary)
            ? "📚 <b>Kelas Terinval:</b>\n{$classesSummary}\n"
            : "📚 <b>Kelas:</b> <i>Izin umum / tanpa jam kelas</i>\n";

        $successMsg = "🎉 <b>PENGAJUAN IZIN & TUGAS BERHASIL DIKIRIM!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Bpk/Ibu <b>{$user->name}</b>, permohonan izin Anda telah resmi tercatat di SIM Kurikulum.\n\n"
            ."🗓️ <b>Hari/Tanggal:</b> {$targetDay}, {$targetDateFormatted}\n"
            ."📌 <b>Alasan:</b> {$state['reason']}\n\n"
            ."{$classesBlock}"
            ."📝 <b>Tugas Pembelajaran:</b>\n<i>\"{$task}\"</i>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📢 <b>Notifikasi Laporan Otomatis Telah Diteruskan ke:</b>\n"
            ."• Kepala Sekolah: {$principalStatus}\n"
            ."• Waka Kurikulum: {$wakaStatus}\n"
            ."• Petugas Piket Hari {$targetDay}: Terkirim ke Telegram\n"
            ."• Grup Tim Piket Harian Sekolah: Disiarkan\n"
            ."• Antrean Inval SIM Kurikulum: Tersimpan\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'<i>Petugas piket akan mengondisikan kelas dan menyampaikan instruksi tugas Anda kepada siswa. Semoga lekas selesai urusannya / lekas sembuh.</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $successMsg,
            'bot_reply',
            $user->name,
            'HTML',
            $this->getMainMenuKeyboard($user)
        );

        Cache::forget("tg_piket_state_{$chatId}");
    }

    // =========================================================================
    // 2c. ALUR INTERAKTIF: TUKAR JADWAL PIKET (DUTY SWAP)
    // =========================================================================

    /**
     * Mulai Wizard Tukar Jadwal Piket (/tukar_piket)
     */
    protected function startDutySwap(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $setting = SchoolSetting::getSetting();
        $academicYear = $this->getOperationalAcademicYear();

        $mySchedules = PiketSchedule::where('teacher_id', $user->id)
            ->where('academic_year', $academicYear)
            ->where('is_active', true)
            ->get();

        if ($mySchedules->isEmpty()) {
            $msg = "⚠️ <b>Tidak Ada Jadwal Piket Aktif</b>\n\n"
                ."Akun Anda (<b>{$user->name}</b>) tercatat tidak memiliki jadwal piket aktif pada tahun ajaran {$academicYear}.\n\n"
                .'Fitur tukar jadwal piket hanya dapat diajukan oleh guru yang terdaftar dalam jadwal piket sekolah.';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";

        if ($mySchedules->count() === 1) {
            $schedule = $mySchedules->first();
            Cache::put($cacheKey, [
                'flow' => 'duty_swap',
                'step' => 'awaiting_swap_date',
                'schedule_id' => $schedule->id,
                'duty_date' => null,
                'replacement_teacher_id' => null,
                'reason' => null,
            ], now()->addMinutes(15));

            $this->promptSwapDateSelection($chatId, $schedule, $user->name);

            return;
        }

        // Jika memiliki lebih dari 1 jadwal piket aktif
        Cache::put($cacheKey, [
            'flow' => 'duty_swap',
            'step' => 'awaiting_schedule_choice',
            'schedule_id' => null,
            'duty_date' => null,
            'replacement_teacher_id' => null,
            'reason' => null,
        ], now()->addMinutes(15));

        $inlineKeyboard = [];
        foreach ($mySchedules as $s) {
            $loc = $s->duty_location ? " ({$s->duty_location})" : '';
            $inlineKeyboard[] = [
                ['text' => "🗓️ Hari {$s->day} - {$s->role_label}{$loc}", 'callback_data' => "dswap_sch_{$s->id}"],
            ];
        }
        $inlineKeyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

        $msg = "🔄 <b>FORM TUKAR JADWAL PIKET</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Anda memiliki beberapa jadwal piket aktif.\n"
            .'Silakan pilih jadwal piket yang ingin ditukar:';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', [
            'inline_keyboard' => $inlineKeyboard,
        ]);
    }

    /**
     * Tampilkan pilihan tanggal tugas piket yang ingin ditukar.
     */
    protected function promptSwapDateSelection(string $chatId, PiketSchedule $schedule, string $senderName): void
    {
        $dayMap = [
            'Senin' => Carbon::MONDAY,
            'Selasa' => Carbon::TUESDAY,
            'Rabu' => Carbon::WEDNESDAY,
            'Kamis' => Carbon::THURSDAY,
            'Jumat' => Carbon::FRIDAY,
            'Sabtu' => Carbon::SATURDAY,
            'Minggu' => Carbon::SUNDAY,
        ];
        $targetDow = $dayMap[$schedule->day] ?? Carbon::MONDAY;

        $today = Carbon::today();
        $upcomingDates = [];
        for ($i = 0; $i < 28; $i++) {
            $checkDate = $today->copy()->addDays($i);
            if ($checkDate->dayOfWeek === $targetDow) {
                $upcomingDates[] = $checkDate;
                if (count($upcomingDates) >= 3) {
                    break;
                }
            }
        }

        $inlineKeyboard = [];
        foreach ($upcomingDates as $d) {
            $label = $d->translatedFormat('l, d M Y');
            if ($d->isToday()) {
                $label .= ' (Hari Ini)';
            } elseif ($d->isTomorrow()) {
                $label .= ' (Besok)';
            }
            $inlineKeyboard[] = [
                ['text' => "📅 {$label}", 'callback_data' => 'dswap_dt_'.$d->format('Y-m-d')],
            ];
        }
        $inlineKeyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

        $roleLabel = ucfirst($schedule->role);
        $loc = $schedule->duty_location ? " ({$schedule->duty_location})" : '';

        $msg = "🔄 <b>FORM TUKAR JADWAL PIKET</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Jadwal: <b>Hari {$schedule->day} - {$roleLabel}{$loc}</b>\n\n"
            ."Silakan pilih <b>Tanggal Tugas Piket</b> yang ingin Anda tukarkan:\n"
            .'<i>(Atau ketik manual tanggalnya, contoh: <code>'.date('Y-m-d')."</code>)</i>\n\n"
            .'<i>(Ketik <code>/batal</code> untuk membatalkan pengisian)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', [
            'inline_keyboard' => $inlineKeyboard,
        ]);
    }

    /**
     * Tangani callback query alur tukar piket.
     */
    protected function handleDutySwapCallback(
        string $chatId,
        string $data,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";

        if (str_starts_with($data, 'dswap_sch_')) {
            $scheduleId = (int) substr($data, 10);
            $schedule = PiketSchedule::find($scheduleId);
            if (! $schedule) {
                return;
            }

            $state['schedule_id'] = $schedule->id;
            $state['step'] = 'awaiting_swap_date';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->promptSwapDateSelection($chatId, $schedule, $senderName);

            return;
        }

        if (str_starts_with($data, 'dswap_dt_')) {
            $selectedDate = substr($data, 9);
            $state['duty_date'] = $selectedDate;
            $state['step'] = 'awaiting_replacement_teacher';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $dateFormatted = Carbon::parse($selectedDate)->translatedFormat('l, d F Y');

            $msg = "📅 <b>Tanggal Terpilih:</b> {$dateFormatted}\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Silakan ketik <b>Nama Guru Pengganti</b> yang akan menggantikan tugas piket Anda:\n"
                ."<i>(Contoh: Ahmad Soleh atau Budi)</i>\n\n"
                .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML');

            return;
        }

        if (str_starts_with($data, 'dswap_rep_')) {
            $teacherId = (int) substr($data, 10);
            $replacement = User::find($teacherId);
            if (! $replacement) {
                return;
            }

            $state['replacement_teacher_id'] = $replacement->id;
            $state['step'] = 'awaiting_swap_reason';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $msg = "👥 <b>Guru Pengganti Terpilih:</b> {$replacement->name}\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Silakan ketik <b>alasan pertukaran jadwal piket</b>:\n"
                ."<i>(Contoh: Ada tugas dinas luar / Menghadiri workshop / Sakit)</i>\n\n"
                .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML');

            return;
        }
    }

    /**
     * Tangani input teks pada alur tukar jadwal piket.
     */
    protected function handleDutySwapFlow(
        string $chatId,
        string $text,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";
        $step = $state['step'] ?? '';

        if ($step === 'awaiting_swap_date') {
            try {
                $dt = Carbon::parse($text);
            } catch (Throwable) {
                $this->telegramService->sendMessage(
                    $chatId,
                    '⚠️ Format tanggal tidak dikenali. Silakan ketik format <code>YYYY-MM-DD</code> (contoh: <code>'.date('Y-m-d')."</code>) atau pilih tombol tanggal di atas:\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>',
                    'bot_reply',
                    $senderName,
                    'HTML'
                );

                return;
            }

            $state['duty_date'] = $dt->format('Y-m-d');
            $state['step'] = 'awaiting_replacement_teacher';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $dateFormatted = $dt->translatedFormat('l, d F Y');
            $msg = "📅 <b>Tanggal Terpilih:</b> {$dateFormatted}\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Silakan ketik <b>Nama Guru Pengganti</b> yang akan menggantikan tugas piket Anda:\n"
                ."<i>(Contoh: Ahmad Soleh atau Budi)</i>\n\n"
                .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML');

            return;
        }

        if ($step === 'awaiting_replacement_teacher') {
            $teachers = User::operationalPersonnel()
                ->where('id', '!=', $user?->id ?? 0)
                ->where('name', 'like', "%{$text}%")
                ->where('is_active', true)
                ->take(6)
                ->get();

            if ($teachers->isEmpty()) {
                $this->telegramService->sendMessage(
                    $chatId,
                    "⚠️ Data guru dengan nama <i>\"{$text}\"</i> tidak ditemukan.\n\n"
                    ."Silakan ketik ulang nama guru pengganti yang benar:\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>',
                    'bot_reply',
                    $senderName,
                    'HTML'
                );

                return;
            }

            if ($teachers->count() === 1) {
                $rep = $teachers->first();
                $state['replacement_teacher_id'] = $rep->id;
                $state['step'] = 'awaiting_swap_reason';
                Cache::put($cacheKey, $state, now()->addMinutes(15));

                $msg = "👥 <b>Guru Pengganti:</b> {$rep->name}\n"
                    ."━━━━━━━━━━━━━━━━━━━━\n"
                    ."Silakan ketik <b>alasan pertukaran jadwal piket</b>:\n"
                    ."<i>(Contoh: Ada tugas dinas luar / Menghadiri workshop / Sakit)</i>\n\n"
                    .'<i>(Ketik <code>/batal</code> untuk membatalkan)</i>';

                $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML');

                return;
            }

            // Pilihan jika lebih dari 1
            $inlineKeyboard = [];
            foreach ($teachers as $t) {
                $inlineKeyboard[] = [
                    ['text' => "👤 {$t->name}", 'callback_data' => "dswap_rep_{$t->id}"],
                ];
            }
            $inlineKeyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

            $msg = "🔍 <b>Ditemukan beberapa nama guru yang cocok:</b>\nSilakan pilih guru pengganti:";
            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', [
                'inline_keyboard' => $inlineKeyboard,
            ]);

            return;
        }

        if ($step === 'awaiting_swap_reason') {
            if (empty($text)) {
                $this->telegramService->sendMessage($chatId, 'Silakan ketik alasan pertukaran jadwal piket terlebih dahulu.');

                return;
            }

            $state['reason'] = $text;
            $this->completeDutySwap($chatId, $state, $user ?? User::first(), $text);

            return;
        }
    }

    /**
     * Simpan permohonan tukar piket dan kirimkan verifikasi langsung ke guru pengganti.
     */
    protected function completeDutySwap(
        string $chatId,
        array $state,
        User $user,
        string $reason
    ): void {
        $schedule = PiketSchedule::find($state['schedule_id']);
        $replacement = User::find($state['replacement_teacher_id']);

        if (! $schedule || ! $replacement) {
            $this->telegramService->sendMessage($chatId, '⚠️ Terjadi kesalahan data jadwal atau guru pengganti.', 'bot_reply', $user->name);
            Cache::forget("tg_piket_state_{$chatId}");

            return;
        }

        $swap = PiketDutySwap::create([
            'original_schedule_id' => $schedule->id,
            'requester_id' => $user->id,
            'replacement_teacher_id' => $replacement->id,
            'duty_date' => $state['duty_date'],
            'reason' => $reason,
            'status' => 'menunggu',
        ]);

        // Kirim permintaan verifikasi langsung ke Guru Pengganti via Telegram
        $notified = $this->telegramService->notifyDutySwapRequested($swap);

        $dateFormatted = Carbon::parse($state['duty_date'])->translatedFormat('l, d F Y');
        $notifStatus = $notified
            ? "✅ Notifikasi verifikasi telah dikirim langsung ke Telegram Bpk/Ibu <b>{$replacement->name}</b> untuk disetujui."
            : "⚪ Bpk/Ibu <b>{$replacement->name}</b> belum mengaktifkan Telegram. Anda dapat menghubungi beliau via WhatsApp atau beliau dapat menyetujuinya lewat menu web SIM Piket.";

        $successMsg = "🔄 <b>PERMOHONAN TUKAR JADWAL PIKET DIAJUKAN!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."📅 <b>Hari / Tanggal Tugas:</b> {$dateFormatted}\n"
            ."📌 <b>Tugas Asli:</b> {$schedule->role_label} ({$schedule->duty_location})\n"
            ."👤 <b>Pemohon:</b> {$user->name}\n"
            ."👥 <b>Guru Pengganti:</b> {$replacement->name}\n"
            ."📝 <b>Alasan:</b> {$reason}\n\n"
            ."📲 <b>Status Verifikasi:</b>\n"
            ."{$notifStatus}\n\n"
            ."<i>💡 Sesuai ketentuan, pertukaran piket ini akan langsung aktif begitu Bpk/Ibu <b>{$replacement->name}</b> menyetujuinya tanpa memerlukan verifikasi pihak lain.</i>";

        $this->telegramService->sendMessage(
            $chatId,
            $successMsg,
            'bot_reply',
            $user->name,
            'HTML',
            $this->getMainMenuKeyboard($user)
        );

        Cache::forget("tg_piket_state_{$chatId}");
    }

    /**
     * Memproses persetujuan / penolakan tukar jadwal piket langsung oleh guru pengganti.
     */
    protected function handleDutySwapApprovalCallback(
        string $chatId,
        int $swapId,
        bool $isApproved,
        ?User $user,
        string $senderName
    ): void {
        $swap = PiketDutySwap::with(['requester', 'replacementTeacher', 'originalSchedule'])->find($swapId);

        if (! $swap) {
            $this->telegramService->sendMessage($chatId, '⚠️ Data permohonan tukar jadwal piket tidak ditemukan atau sudah dihapus.', 'bot_reply', $senderName);

            return;
        }

        if ($swap->status !== 'menunggu') {
            $statusText = $swap->status === 'disetujui' ? 'TELAH DISETUJUI' : 'TELAH DITOLAK';
            $this->telegramService->sendMessage(
                $chatId,
                "ℹ️ Permohonan tukar jadwal piket ini sudah diproses sebelumnya (Status: <b>{$statusText}</b>).",
                'bot_reply',
                $senderName,
                'HTML'
            );

            return;
        }

        // Verifikasi bahwa yang memproses adalah guru pengganti yang bersangkutan!
        if (! $user || $user->id !== $swap->replacement_teacher_id) {
            $this->telegramService->sendMessage(
                $chatId,
                "⚠️ <b>Akses Ditolak</b>\n\n"
                ."Permohonan tukar piket ini ditujukan kepada Bpk/Ibu <b>{$swap->replacementTeacher?->name}</b>. Hanya beliau yang dapat memverifikasi atau menyetujuinya.",
                'bot_reply',
                $senderName,
                'HTML'
            );

            return;
        }

        $dateFormatted = $swap->duty_date?->translatedFormat('l, d F Y') ?? date('d/m/Y');

        if ($isApproved) {
            $swap->update([
                'status' => 'disetujui',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->telegramService->notifyDutySwapApproved($swap);

            $msg = "✅ <b>TUKAR JADWAL PIKET RESMI ANDA SETUJUI!</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Terima kasih Bpk/Ibu <b>{$user->name}</b>.\n"
                ."Anda telah menyetujui pertukaran jadwal piket menggantikan Bpk/Ibu <b>{$swap->requester?->name}</b> pada:\n"
                ."📅 <b>Hari / Tanggal:</b> {$dateFormatted}\n\n"
                .'<i>Pergantian jadwal ini telah sah tercatat di SIM Kurikulum. Notifikasi telah otomatis dikirimkan ke guru pemohon, tembusan japri ke Waka Kurikulum, dan disiarkan ke Grup Tim Piket Sekolah.</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));
        } else {
            $swap->update([
                'status' => 'ditolak',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->telegramService->notifyDutySwapRejected($swap);

            $msg = "❌ <b>TUKAR JADWAL PIKET TELAH DITOLAK</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n"
                ."Anda telah menolak permohonan tukar jadwal piket dari Bpk/Ibu <b>{$swap->requester?->name}</b> untuk tanggal {$dateFormatted}.\n\n"
                .'<i>Pemberitahuan penolakan telah dikirimkan ke beliau agar dapat mencari alternatif pengganti lain.</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));
        }
    }

    // =========================================================================
    // 3. ALUR INTERAKTIF: LAPOR KASUS SISWA (INCIDENT)
    // =========================================================================

    /**
     * Mulai Wizard Pelaporan Kasus Siswa (/lapor)
     */
    protected function startIncidentReport(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        if (! $user->canManagePiketCases()) {
            $msg = "⚠️ <b>Akses Khusus Petugas Piket & Tim BK</b>\n\n"
                ."Fitur pelaporan kasus siswa hanya dapat digunakan oleh <b>Petugas Piket yang bertugas hari ini</b> atau Administrator/Tim BK.\n\n"
                ."Akun Anda (<b>{$user->name}</b>) tercatat tidak memiliki jadwal piket pada hari ini.";

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";
        Cache::put($cacheKey, [
            'flow' => 'incident',
            'step' => 'awaiting_student',
            'user_id' => $user->id,
            'student_id' => null,
            'student_name' => null,
            'classroom_id' => null,
            'category' => null,
            'description' => null,
            'action_taken' => null,
        ], now()->addMinutes(15));

        $msg = "⚠️ <b>FORM PELAPORAN KASUS / KEJADIAN SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Petugas: <b>{$user->name}</b>\n\n"
            ."Silakan ketik <b>Nama Siswa</b> atau <b>NIS</b> siswa yang terlibat:\n\n"
            .'<i>(Ketik <code>/batal</code> untuk membatalkan pengisian)</i>';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name);
    }

    /**
     * Menangani alur percakapan step-by-step formulir yang sedang aktif.
     */
    protected function handleStatefulConversation(
        string $chatId,
        string $text,
        ?array $photos,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";

        // Jika user ingin membatalkan
        if (mb_strtolower($text) === '/batal' || mb_strtolower($text) === 'batal') {
            Cache::forget($cacheKey);
            $this->telegramService->sendMessage(
                $chatId,
                '❌ <i>Proses telah dibatalkan.</i>',
                'bot_reply',
                $senderName,
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        $flow = $state['flow'] ?? 'incident';

        if ($flow === 'student_permit') {
            $this->handleStudentPermitFlow($chatId, $text, $state, $user, $senderName);

            return;
        }

        if ($flow === 'teacher_permit') {
            $this->handleTeacherPermitFlow($chatId, $text, $state, $user, $senderName);

            return;
        }

        if ($flow === 'self_teacher_permit') {
            $this->handleSelfTeacherPermitFlow($chatId, $text, $state, $user, $senderName);

            return;
        }

        if ($flow === 'duty_swap') {
            $this->handleDutySwapFlow($chatId, $text, $state, $user, $senderName);

            return;
        }

        if ($flow === 'check_student_attendance') {
            $this->handleCheckStudentAttendanceFlow($chatId, $text, $state, $user, $senderName);

            return;
        }

        // Default: Incident report flow
        $this->handleIncidentReportFlow($chatId, $text, $photos, $state, $user, $senderName);
    }

    /**
     * Menangani alur step-by-step pelaporan insiden/kasus siswa.
     */
    protected function handleIncidentReportFlow(
        string $chatId,
        string $text,
        ?array $photos,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        $cacheKey = "tg_piket_state_{$chatId}";
        $step = $state['step'] ?? '';

        // STEP 1: Menginput Nama / NIS Siswa
        if ($step === 'awaiting_student') {
            $studentSearch = Student::where('name', 'like', "%{$text}%")
                ->orWhere('nis', $text)
                ->with('classroom')
                ->take(5)
                ->get();

            if ($studentSearch->isNotEmpty()) {
                $matchedStudent = $studentSearch->first();
                $state['student_id'] = $matchedStudent->id;
                $state['student_name'] = $matchedStudent->name;
                $state['classroom_id'] = $matchedStudent->classroom_id;
                $classroomName = $matchedStudent->classroom?->name ?? 'Kelas Tidak Diketahui';
            } else {
                $state['student_id'] = null;
                $state['student_name'] = $text;
                $state['classroom_id'] = null;
                $classroomName = 'Umum';
            }

            $state['step'] = 'awaiting_category';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $categoryKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Kedisiplinan', 'callback_data' => 'cat_kedisiplinan'],
                        ['text' => 'Seragam/Atribut', 'callback_data' => 'cat_seragam'],
                    ],
                    [
                        ['text' => 'Merokok/Vape', 'callback_data' => 'cat_rokok'],
                        ['text' => 'HP / Gadget', 'callback_data' => 'cat_gadget_terlarang'],
                    ],
                    [
                        ['text' => 'Perkelahian', 'callback_data' => 'cat_perkelahian'],
                        ['text' => 'Kesehatan/Pingsan', 'callback_data' => 'cat_kesehatan_pingsan'],
                    ],
                    [
                        ['text' => 'Lain-lain', 'callback_data' => 'cat_lainnya'],
                    ],
                    [
                        ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
                    ],
                ],
            ];

            $msg = "👤 <b>Siswa Terpilih:</b> {$state['student_name']} ({$classroomName})\n\n"
                .'Silakan pilih <b>Kategori Kejadian / Kasus</b> di bawah:';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', $categoryKeyboard);

            return;
        }

        // STEP 2: Menginput Uraian & Tindakan
        if ($step === 'awaiting_description') {
            if (empty($text)) {
                $this->telegramService->sendMessage($chatId, 'Silakan ketikkan uraian kejadian terlebih dahulu.');

                return;
            }

            $state['description'] = $text;
            $state['action_taken'] = 'Tindakan awal dan pembinaan oleh petugas piket.';
            $state['step'] = 'awaiting_photo';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $photoKeyboard = [
                'inline_keyboard' => [
                    [['text' => '⏭️ Lewati Bukti Foto', 'callback_data' => 'skip_photo']],
                    [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']],
                ],
            ];

            $msg = "📝 <b>Uraian Tercatat:</b>\n<i>\"{$text}\"</i>\n\n"
                ."📸 <b>Lampirkan Bukti Berupa Foto:</b>\n"
                ."Silakan kirimkan <b>1 foto bukti</b> langsung dari kamera atau galeri Telegram Anda.\n"
                .'<i>Atau klik tombol <b>Lewati Bukti Foto</b> di bawah jika tidak ada foto.</i>';

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $senderName, 'HTML', $photoKeyboard);

            return;
        }

        // STEP 3: Menerima Foto Bukti Kejadian
        if ($step === 'awaiting_photo') {
            $photoPath = null;

            if ($photos && is_array($photos)) {
                $largestPhoto = end($photos);
                $fileId = $largestPhoto['file_id'] ?? null;

                if ($fileId) {
                    $photoPath = $this->telegramService->downloadTelegramFile($fileId, 'piket/incidents');
                }
            } elseif (mb_strtolower($text) === '/lewati') {
                $photoPath = null;
            } else {
                $this->telegramService->sendMessage(
                    $chatId,
                    "Kirimkan foto bukti atau klik tombol <b>Lewati Bukti Foto</b> di bawah.\n(Ketik <code>/lewati</code> jika tidak ada foto)"
                );

                return;
            }

            $this->saveAndBroadcastIncident($chatId, $state, $photoPath, $user ?? User::first());
            Cache::forget($cacheKey);

            return;
        }
    }

    /**
     * Handle Inline Callback Queries (Tombol Kategori, Lewati Foto, Pilihan Siswa/Guru/Hari/Kelas).
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $data = $callbackQuery['data'] ?? '';

        $user = User::where('telegram_chat_id', $chatId)->first();
        $senderName = $callbackQuery['from']['first_name'] ?? 'Petugas';

        // 2d. Callback untuk Verifikasi / Persetujuan Tukar Piket Langsung oleh Guru Pengganti (tidak membutuhkan state session)
        if (str_starts_with($data, 'pds_acc_')) {
            $swapId = (int) substr($data, 8);
            $this->handleDutySwapApprovalCallback($chatId, $swapId, true, $user, $senderName);

            return;
        }

        if (str_starts_with($data, 'pds_rej_')) {
            $swapId = (int) substr($data, 8);
            $this->handleDutySwapApprovalCallback($chatId, $swapId, false, $user, $senderName);

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";
        $state = Cache::get($cacheKey);

        if (! $state) {
            $this->telegramService->sendMessage($chatId, 'Sesi telah kedaluwarsa atau selesai. Silakan pilih menu kembali.');

            return;
        }

        // 0. Tombol Batal
        if ($data === 'cancel_flow') {
            Cache::forget($cacheKey);
            $this->telegramService->sendMessage(
                $chatId,
                '❌ <i>Proses telah dibatalkan.</i>',
                'bot_reply',
                $user?->name ?? 'Petugas',
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        // 1. Callback untuk Alur Perizinan Siswa
        if (str_starts_with($data, 'sp_')) {
            $this->handleStudentPermitCallback($chatId, $data, $state, $user);

            return;
        }

        // 2. Callback untuk Alur Perizinan Guru
        if (str_starts_with($data, 'tp_')) {
            $this->handleTeacherPermitCallback($chatId, $data, $state, $user);

            return;
        }

        // 2b. Callback untuk Alur Izin Mandiri Guru
        if (str_starts_with($data, 'stp_')) {
            $this->handleSelfTeacherPermitCallback($chatId, $data, $state, $user);

            return;
        }

        // 2c. Callback untuk Alur Pengajuan Tukar Jadwal Piket
        if (str_starts_with($data, 'dswap_')) {
            $this->handleDutySwapCallback($chatId, $data, $state, $user, $senderName);

            return;
        }

        // 2e. Callback untuk Alur Cek Absen Siswa (Wali Kelas / BK / Kedisiplinan)
        if (str_starts_with($data, 'att_')) {
            $this->handleStudentAttendanceCallback($chatId, $data, $state, $user);

            return;
        }

        // Tombol noop (hanya label informasi)
        if ($data === 'noop') {
            return;
        }

        // 3. Callback untuk Alur Pelaporan Kasus Siswa (Existing)
        if (str_starts_with($data, 'cat_')) {
            $cat = substr($data, 4);
            $state['category'] = $cat;
            $state['step'] = 'awaiting_description';
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $labels = [
                'kedisiplinan' => 'Kedisiplinan',
                'seragam' => 'Pelanggaran Seragam / Kerapian',
                'gadget_terlarang' => 'HP / Gadget Terlarang',
                'perkelahian' => 'Pertikaian / Perkelahian',
                'rokok' => 'Rokok / Vape',
                'kesehatan_pingsan' => 'Kesehatan / Pingsan',
                'lainnya' => 'Lain-lain',
            ];
            $catLabel = $labels[$cat] ?? ucfirst($cat);

            $msg = "🏷️ <b>Kategori Terpilih:</b> {$catLabel}\n\n"
                ."Silakan tuliskan <b>Uraian / Kronologi Kejadian</b> serta tindakan awal yang dilakukan:\n\n"
                ."<i>Contoh:\nSiswa kedapatan merokok di toilet belakang saat jam istirahat. Dilakukan pembinaan meja piket dan rokok disita.</i>";

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', 'Petugas');

            return;
        }

        // 4. Lewati Foto Bukti
        if ($data === 'skip_photo') {
            $user = User::find($state['user_id']) ?? User::first();
            $this->saveAndBroadcastIncident($chatId, $state, null, $user);
            Cache::forget($cacheKey);

            return;
        }
    }

    /**
     * Simpan incident ke database dan kirim notifikasi otomatis ke semua pihak.
     */
    protected function saveAndBroadcastIncident(
        string $chatId,
        array $state,
        ?string $photoPath,
        User $officer
    ): void {
        $today = Carbon::today()->format('Y-m-d');
        $setting = SchoolSetting::getSetting();
        $academicYear = $this->getOperationalAcademicYear();

        $journal = PiketJournal::firstOrCreate(
            ['date' => $today],
            [
                'academic_year' => $academicYear,
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $officer->id,
            ]
        );

        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => $today,
            'incident_time' => Carbon::now()->format('H:i:s'),
            'student_id' => $state['student_id'] ?? null,
            'student_name' => $state['student_name'] ?? 'Siswa',
            'classroom_id' => $state['classroom_id'] ?? null,
            'category' => $state['category'] ?? 'kedisiplinan',
            'description' => $state['description'] ?? 'Dilaporkan melalui Bot Telegram Petugas Piket',
            'action_taken' => $state['action_taken'] ?? 'Tindakan awal oleh petugas piket',
            'follow_up_target' => 'selesai_di_piket',
            'confiscated_items' => null,
            'evidence_photo' => $photoPath,
            'recorded_by' => $officer->id,
            'status' => 'tercatat',
        ]);

        // Broadcast otomatis ke Grup Piket, Petugas Piket Hari Ini, Wali Kelas, dan Tim BK!
        $classroom = $incident->classroom_id ? Classroom::with('homeroomTeacher')->find($incident->classroom_id) : null;
        $homeroomTeacher = $classroom?->homeroomTeacher;
        $this->telegramService->notifyIncident($incident, $homeroomTeacher);

        $photoInfo = $photoPath ? "\n📷 <i>Foto bukti berhasil diunggah & dilampirkan.</i>" : '';

        $successMsg = "✅ <b>LAPORAN KEJADIAN BERHASIL DISIMPAN & DITERUSKAN!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Nomor Kasus: <b>INC-{$incident->id}</b>\n"
            ."Siswa: <b>{$incident->student_name}</b> ({$classroom?->name})\n"
            ."Kategori: <b>{$incident->category_label}</b>\n"
            ."Pencatat: <b>{$officer->name}</b>\n"
            ."{$photoInfo}\n\n"
            ."📢 <b>Laporan otomatis diteruskan secara real-time ke:</b>\n"
            ."1. Grup Tim Piket Harian Sekolah\n"
            ."2. Japri ke Petugas Piket Hari Ini\n"
            ."3. Wali Kelas Siswa\n"
            ."4. Grup Kedisiplinan & BK Sekolah\n"
            .'━━━━━━━━━━━━━━━━━━━━';

        $this->telegramService->sendMessage(
            $chatId,
            $successMsg,
            'bot_reply',
            $officer->name,
            'HTML',
            $this->getMainMenuKeyboard($officer)
        );
    }

    /**
     * Memeriksa apakah user berhak mengakses fitur Cek Absen Siswa.
     */
    protected function canCheckStudentAttendance(?User $user, ?string $academicYear = null): bool
    {
        if (! $user) {
            return false;
        }

        $academicYear = $academicYear ?? $this->getOperationalAcademicYear();

        if ($user->isSuperAdmin() || $user->isPrincipal()) {
            return true;
        }

        if ($user->canManageDiscipline($academicYear) || $user->canOperateBk($academicYear) || $user->canOperatePiket()) {
            return true;
        }

        return $user->isHomeroomTeacher(academicYear: $academicYear);
    }

    /**
     * Memeriksa apakah user dibatasi hanya untuk kelas perwaliannya saja.
     */
    protected function isHomeroomOnlyForAttendance(User $user, ?string $academicYear = null): bool
    {
        $academicYear = $academicYear ?? $this->getOperationalAcademicYear();

        if ($user->isSuperAdmin() || $user->isPrincipal()) {
            return false;
        }

        if ($user->canManageDiscipline($academicYear) || $user->canOperateBk($academicYear) || $user->canOperatePiket()) {
            return false;
        }

        return $user->isHomeroomTeacher(academicYear: $academicYear);
    }

    /**
     * Validasi akses user ke kelas tertentu.
     */
    protected function canAccessClassroom(User $user, Classroom $classroom, ?string $academicYear = null): bool
    {
        $academicYear = $academicYear ?? $this->getOperationalAcademicYear();

        if (! $this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            return true;
        }

        if ((int) $classroom->homeroom_teacher_id === (int) $user->id) {
            return true;
        }

        return $user->isHomeroomTeacher($classroom, $academicYear);
    }

    /**
     * Validasi akses user ke siswa tertentu.
     */
    protected function canAccessStudent(User $user, Student $student, ?string $academicYear = null): bool
    {
        $academicYear = $academicYear ?? $this->getOperationalAcademicYear();

        if (! $this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            return true;
        }

        $classroom = $student->classroomForAcademicYear($academicYear) ?? $student->classroom;
        if (! $classroom) {
            return false;
        }

        return $this->canAccessClassroom($user, $classroom, $academicYear);
    }

    /**
     * Memulai alur cek rekap absen & kejadian siswa.
     */
    protected function startCheckStudentAttendance(string $chatId, ?User $user): void
    {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $academicYear = $this->getOperationalAcademicYear();

        if (! $this->canCheckStudentAttendance($user, $academicYear)) {
            $msg = "⚠️ <b>Akses Dibatasi</b>\n\n"
                ."Fitur <b>Cek Absen Siswa</b> hanya dapat digunakan oleh <b>Wali Kelas</b> (terbatas untuk siswanya), <b>Tim Kedisiplinan</b>, atau <b>Guru BK</b>.\n\n"
                ."Akun Anda (<b>{$user->name}</b>) tercatat tidak memiliki kewenangan tersebut.";

            $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";

        // Jika Wali Kelas murni
        if ($this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            $homeroomClassrooms = Classroom::where('is_active', true)
                ->where('homeroom_teacher_id', $user->id)
                ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                ->get();

            if ($homeroomClassrooms->isEmpty()) {
                $homeroomClassrooms = $user->homeroomClassrooms()
                    ->where('is_active', true)
                    ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                    ->get();
            }

            if ($homeroomClassrooms->isEmpty()) {
                $msg = "⚠️ <b>Data Kelas Tidak Ditemukan</b>\n\n"
                    ."Akun Anda tercatat sebagai Wali Kelas namun tidak memiliki rombel kelas aktif yang diampu pada tahun ajaran <b>{$academicYear}</b>.\n\n"
                    .'Silakan hubungi Administrator SIM Kurikulum.';

                $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user->name, 'HTML', $this->getMainMenuKeyboard($user));

                return;
            }

            // Jika hanya mengampu 1 kelas perwalian, langsung loncat ke pemilihan siswa
            if ($homeroomClassrooms->count() === 1) {
                $singleClassroom = $homeroomClassrooms->first();
                Cache::put($cacheKey, [
                    'flow' => 'check_student_attendance',
                    'step' => 'select_student',
                    'user_id' => $user->id,
                    'classroom_id' => $singleClassroom->id,
                    'student_id' => null,
                    'page' => 1,
                ], now()->addMinutes(15));

                $this->showStudentSelectionList($chatId, $singleClassroom, 1, $user);

                return;
            }

            // Jika mengampu lebih dari 1 kelas
            Cache::put($cacheKey, [
                'flow' => 'check_student_attendance',
                'step' => 'select_classroom',
                'user_id' => $user->id,
                'classroom_id' => null,
                'student_id' => null,
                'page' => 1,
            ], now()->addMinutes(15));

            $this->showClassroomSelectionList($chatId, $user, 1, $homeroomClassrooms);

            return;
        }

        // Kedisiplinan / Guru BK / Super Admin / Petugas Piket (bisa memilih semua kelas aktif)
        Cache::put($cacheKey, [
            'flow' => 'check_student_attendance',
            'step' => 'select_classroom',
            'user_id' => $user->id,
            'classroom_id' => null,
            'student_id' => null,
            'page' => 1,
        ], now()->addMinutes(15));

        $this->showClassroomSelectionList($chatId, $user, 1);
    }

    /**
     * Tampilkan pilihan daftar kelas dengan tombol inline.
     */
    protected function showClassroomSelectionList(
        string $chatId,
        User $user,
        int $page = 1,
        ?Collection $classroomsList = null
    ): void {
        $academicYear = $this->getOperationalAcademicYear();

        if ($classroomsList === null) {
            $classroomsList = Classroom::where('is_active', true)
                ->when($academicYear, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get();
        }

        if ($classroomsList->isEmpty()) {
            $this->telegramService->sendMessage(
                $chatId,
                '⚠️ Tidak ada data kelas aktif yang ditemukan di sistem.',
                'bot_reply',
                $user->name,
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        $perPage = 10;
        $totalItems = $classroomsList->count();
        $totalPages = (int) ceil($totalItems / $perPage);
        $page = max(1, min($page, $totalPages));

        $pagedClassrooms = $classroomsList->slice(($page - 1) * $perPage, $perPage)->values();

        $keyboard = [];
        $currentRow = [];
        foreach ($pagedClassrooms as $cls) {
            $currentRow[] = [
                'text' => '🏫 '.$cls->name,
                'callback_data' => "att_cls_{$cls->id}",
            ];
            if (count($currentRow) === 2) {
                $keyboard[] = $currentRow;
                $currentRow = [];
            }
        }
        if (! empty($currentRow)) {
            $keyboard[] = $currentRow;
        }

        // Navigasi pagination jika halaman kelas > 1
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = ['text' => '⬅️ Hal '.($page - 1), 'callback_data' => 'att_clspg_'.($page - 1)];
            }
            $navRow[] = ['text' => "📄 {$page}/{$totalPages}", 'callback_data' => 'noop'];
            if ($page < $totalPages) {
                $navRow[] = ['text' => 'Hal '.($page + 1).' ➡️', 'callback_data' => 'att_clspg_'.($page + 1)];
            }
            $keyboard[] = $navRow;
        }

        $keyboard[] = [
            ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
        ];

        $roleNote = $this->isHomeroomOnlyForAttendance($user, $academicYear)
            ? 'Pilih kelas binaan Anda:'
            : 'Pilih kelas siswa yang ingin diperiksa:';

        $msg = "📊 <b>CEK REKAP ABSEN & KEJADIAN SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."{$roleNote}\n\n"
            .'<i>(Ketik <code>/batal</code> kapan saja untuk keluar)</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $user->name,
            'HTML',
            ['inline_keyboard' => $keyboard]
        );
    }

    /**
     * Tampilkan pilihan daftar siswa dalam satu kelas dengan tombol inline & pagination.
     */
    protected function showStudentSelectionList(
        string $chatId,
        Classroom $classroom,
        int $page,
        User $user
    ): void {
        $academicYear = $this->getOperationalAcademicYear();

        if (! $this->canAccessClassroom($user, $classroom, $academicYear)) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $students = Student::query()
            ->where('is_active', true)
            ->where(function ($q) use ($classroom) {
                $q->where('classroom_id', $classroom->id)
                    ->orWhereHas('studentClassrooms', fn ($sc) => $sc->where('classroom_id', $classroom->id));
            })
            ->orderBy('name')
            ->get();

        if ($students->isEmpty()) {
            $keyboard = [];
            if (! $this->isHomeroomOnlyForAttendance($user, $academicYear)) {
                $keyboard[] = [['text' => '🏫 Pilih Kelas Lain', 'callback_data' => 'att_back_cls']];
            }
            $keyboard[] = [['text' => '❌ Batal', 'callback_data' => 'cancel_flow']];

            $this->telegramService->sendMessage(
                $chatId,
                "⚠️ Tidak ada data siswa aktif yang terdaftar di kelas <b>{$classroom->name}</b>.",
                'bot_reply',
                $user->name,
                'HTML',
                ['inline_keyboard' => $keyboard]
            );

            return;
        }

        $perPage = 8;
        $totalItems = $students->count();
        $totalPages = (int) ceil($totalItems / $perPage);
        $page = max(1, min($page, $totalPages));

        $pagedStudents = $students->slice(($page - 1) * $perPage, $perPage)->values();

        $keyboard = [];
        $currentRow = [];
        foreach ($pagedStudents as $student) {
            $displayName = Str::limit($student->name, 16);
            $currentRow[] = [
                'text' => '👤 '.$displayName,
                'callback_data' => "att_std_{$student->id}",
            ];
            if (count($currentRow) === 2) {
                $keyboard[] = $currentRow;
                $currentRow = [];
            }
        }
        if (! empty($currentRow)) {
            $keyboard[] = $currentRow;
        }

        // Navigasi halaman siswa
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = ['text' => '⬅️ Hal '.($page - 1), 'callback_data' => 'att_stdpg_'.($page - 1)];
            }
            $navRow[] = ['text' => "📄 {$page}/{$totalPages}", 'callback_data' => 'noop'];
            if ($page < $totalPages) {
                $navRow[] = ['text' => 'Hal '.($page + 1).' ➡️', 'callback_data' => 'att_stdpg_'.($page + 1)];
            }
            $keyboard[] = $navRow;
        }

        $bottomNav = [];
        if (! $this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            $bottomNav[] = ['text' => '🏫 Ganti Kelas', 'callback_data' => 'att_back_cls'];
        }
        $bottomNav[] = ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'];
        $keyboard[] = $bottomNav;

        $msg = "🏫 <b>Kelas: {$classroom->name}</b>\n"
            ."Total: <b>{$totalItems} siswa</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Silakan klik nama siswa pada tombol di bawah:\n\n"
            .'💡 <i>Tips: Anda juga dapat langsung <b>mengetik Nama atau NIS</b> siswa di chat untuk pencarian cepat.</i>';

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $user->name,
            'HTML',
            ['inline_keyboard' => $keyboard]
        );
    }

    /**
     * Tampilkan dialog pemilihan periode laporan absen.
     */
    protected function promptPeriodSelection(
        string $chatId,
        Student $student,
        Classroom $classroom,
        User $user
    ): void {
        $academicYear = $this->getOperationalAcademicYear();

        if (! $this->canAccessStudent($user, $student, $academicYear)) {
            $this->telegramService->sendMessage(
                $chatId,
                '⚠️ <b>Akses Ditolak:</b> Anda hanya diizinkan memeriksa rekap siswa di kelas perwalian Anda sendiri.',
                'bot_reply',
                $user->name,
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";
        Cache::put($cacheKey, [
            'flow' => 'check_student_attendance',
            'step' => 'select_period',
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'page' => 1,
        ], now()->addMinutes(15));

        $keyboard = [
            [['text' => '📅 Minggu Ini (Awal Minggu s/d Hari Ini)', 'callback_data' => 'att_per_minggu']],
            [['text' => '🗓️ Bulan Ini (Awal Bulan s/d Hari Ini)', 'callback_data' => 'att_per_bulan']],
            [['text' => '📚 Semua (Awal TP Aktif s/d Hari Ini)', 'callback_data' => 'att_per_semua']],
            [
                ['text' => '👤 Pilih Siswa Lain', 'callback_data' => 'att_back_std'],
                ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
            ],
        ];

        $nisInfo = $student->nis ? "NIS: <code>{$student->nis}</code>" : 'NIS: -';
        $msg = "👤 <b>PROFIL SISWA TERPILIH</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."• Nama  : <b>{$student->name}</b>\n"
            ."• Kelas : <b>{$classroom->name}</b>\n"
            ."• {$nisInfo}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'Silakan pilih <b>Periode Absen</b> yang ingin dilihat:';

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $user->name,
            'HTML',
            ['inline_keyboard' => $keyboard]
        );
    }

    /**
     * Callback handler untuk flow Cek Absen Siswa.
     */
    protected function handleStudentAttendanceCallback(
        string $chatId,
        string $data,
        array $state,
        ?User $user
    ): void {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $cacheKey = "tg_piket_state_{$chatId}";

        // 1. Pindah Halaman Kelas
        if (str_starts_with($data, 'att_clspg_')) {
            $page = (int) substr($data, 10);
            $state['page'] = $page;
            Cache::put($cacheKey, $state, now()->addMinutes(15));
            $this->showClassroomSelectionList($chatId, $user, $page);

            return;
        }

        // 2. Pilih Kelas
        if (str_starts_with($data, 'att_cls_')) {
            $classroomId = (int) substr($data, 8);
            $classroom = Classroom::find($classroomId);
            if (! $classroom) {
                $this->telegramService->sendMessage($chatId, '⚠️ Kelas tidak ditemukan.');

                return;
            }

            $state['classroom_id'] = $classroomId;
            $state['step'] = 'select_student';
            $state['page'] = 1;
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->showStudentSelectionList($chatId, $classroom, 1, $user);

            return;
        }

        // 3. Kembali ke Daftar Kelas
        if ($data === 'att_back_cls') {
            $state['step'] = 'select_classroom';
            $state['classroom_id'] = null;
            $state['student_id'] = null;
            $state['page'] = 1;
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->showClassroomSelectionList($chatId, $user, 1);

            return;
        }

        // 4. Pindah Halaman Siswa
        if (str_starts_with($data, 'att_stdpg_')) {
            $page = (int) substr($data, 10);
            $classroomId = $state['classroom_id'] ?? null;
            $classroom = Classroom::find($classroomId);
            if (! $classroom) {
                $this->telegramService->sendMessage($chatId, '⚠️ Sesi tidak valid. Silakan mulai ulang.');

                return;
            }

            $state['page'] = $page;
            Cache::put($cacheKey, $state, now()->addMinutes(15));
            $this->showStudentSelectionList($chatId, $classroom, $page, $user);

            return;
        }

        // 5. Kembali ke Daftar Siswa
        if ($data === 'att_back_std') {
            $classroomId = $state['classroom_id'] ?? null;
            $classroom = Classroom::find($classroomId);
            if (! $classroom) {
                $this->showClassroomSelectionList($chatId, $user, 1);

                return;
            }

            $state['step'] = 'select_student';
            $state['student_id'] = null;
            $page = $state['page'] ?? 1;
            Cache::put($cacheKey, $state, now()->addMinutes(15));

            $this->showStudentSelectionList($chatId, $classroom, $page, $user);

            return;
        }

        // 6. Pilih Siswa
        if (str_starts_with($data, 'att_std_')) {
            $studentId = (int) substr($data, 8);
            $student = Student::find($studentId);
            if (! $student) {
                $this->telegramService->sendMessage($chatId, '⚠️ Data siswa tidak ditemukan.');

                return;
            }

            $classroomId = $state['classroom_id'] ?? $student->classroom_id;
            $classroom = Classroom::find($classroomId) ?? $student->classroom;

            if (! $classroom) {
                $this->telegramService->sendMessage($chatId, '⚠️ Data kelas siswa tidak ditemukan.');

                return;
            }

            $this->promptPeriodSelection($chatId, $student, $classroom, $user);

            return;
        }

        // 7. Pilih Periode (att_per_minggu, att_per_bulan, att_per_semua)
        if (str_starts_with($data, 'att_per_')) {
            $period = substr($data, 8);
            $studentId = $state['student_id'] ?? null;
            $student = Student::find($studentId);
            if (! $student) {
                $this->telegramService->sendMessage($chatId, '⚠️ Data siswa tidak ditemukan atau sesi kedaluwarsa. Silakan mulai ulang.');

                return;
            }

            $classroomId = $state['classroom_id'] ?? $student->classroom_id;
            $classroom = Classroom::find($classroomId) ?? $student->classroom;

            if (! $classroom) {
                $this->telegramService->sendMessage($chatId, '⚠️ Data kelas tidak valid.');

                return;
            }

            $this->renderStudentAttendanceReport($chatId, $student, $classroom, $period, $user);

            return;
        }
    }

    /**
     * Tangani input ketikan teks pada alur Cek Absen Siswa (pencarian nama/NIS cepat).
     */
    protected function handleCheckStudentAttendanceFlow(
        string $chatId,
        string $text,
        array $state,
        ?User $user,
        string $senderName
    ): void {
        if (! $user) {
            $this->replyAccessDenied($chatId);

            return;
        }

        $academicYear = $this->getOperationalAcademicYear();
        $classroomId = $state['classroom_id'] ?? null;
        $classroom = $classroomId ? Classroom::find($classroomId) : null;

        $query = Student::query()->where('is_active', true);

        // Jika Wali Kelas murni, kunci pencarian hanya pada kelas binaannya
        if ($this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            $homeroomClassroomIds = Classroom::where('is_active', true)
                ->where('homeroom_teacher_id', $user->id)
                ->pluck('id')
                ->toArray();

            $relationIds = $user->homeroomClassrooms()
                ->where('is_active', true)
                ->pluck('classrooms.id')
                ->toArray();

            $homeroomClassroomIds = array_unique(array_merge($homeroomClassroomIds, $relationIds));

            $query->where(function ($q) use ($homeroomClassroomIds) {
                $q->whereIn('classroom_id', $homeroomClassroomIds)
                    ->orWhereHas('studentClassrooms', fn ($sc) => $sc->whereIn('classroom_id', $homeroomClassroomIds));
            });
        } elseif ($classroom) {
            $query->where(function ($q) use ($classroom) {
                $q->where('classroom_id', $classroom->id)
                    ->orWhereHas('studentClassrooms', fn ($sc) => $sc->where('classroom_id', $classroom->id));
            });
        }

        $students = $query->where(function ($q) use ($text) {
            $q->where('name', 'like', "%{$text}%")
                ->orWhere('nis', $text);
        })->get();

        if ($students->isEmpty()) {
            $scopeText = $classroom ? "di kelas <b>{$classroom->name}</b>" : 'di kelas binaan Anda';
            $this->telegramService->sendMessage(
                $chatId,
                "⚠️ Siswa dengan nama atau NIS \"<b>{$text}</b>\" tidak ditemukan {$scopeText}.\n\nSilakan coba kata kunci lain atau pilih melalui menu:",
                'bot_reply',
                $user->name
            );

            return;
        }

        if ($students->count() === 1) {
            $singleStudent = $students->first();
            $targetClassroom = $classroom ?? $singleStudent->classroom;
            if (! $targetClassroom) {
                $targetClassroom = Classroom::find($singleStudent->classroom_id);
            }

            if (! $targetClassroom) {
                $this->telegramService->sendMessage($chatId, '⚠️ Rombel kelas siswa tidak ditemukan.');

                return;
            }

            $this->promptPeriodSelection($chatId, $singleStudent, $targetClassroom, $user);

            return;
        }

        // Tampilkan beberapa tombol siswa yang cocok
        $keyboard = [];
        $currentRow = [];
        foreach ($students->take(8) as $s) {
            $sClass = $s->classroom?->name ? " ({$s->classroom->name})" : '';
            $currentRow[] = [
                'text' => '👤 '.Str::limit($s->name.$sClass, 20),
                'callback_data' => "att_std_{$s->id}",
            ];
            if (count($currentRow) === 2) {
                $keyboard[] = $currentRow;
                $currentRow = [];
            }
        }
        if (! empty($currentRow)) {
            $keyboard[] = $currentRow;
        }

        $keyboard[] = [
            ['text' => '❌ Batal', 'callback_data' => 'cancel_flow'],
        ];

        $this->telegramService->sendMessage(
            $chatId,
            "🔍 Ditemukan <b>{$students->count()} siswa</b> yang cocok dengan \"<b>{$text}</b>\". Silakan pilih salah satu:",
            'bot_reply',
            $user->name,
            'HTML',
            ['inline_keyboard' => $keyboard]
        );
    }

    /**
     * Render pesan rekap presensi KBM, riwayat izin piket, dan kejadian siswa.
     */
    protected function renderStudentAttendanceReport(
        string $chatId,
        Student $student,
        Classroom $classroom,
        string $period,
        User $user
    ): void {
        $academicYear = $this->getOperationalAcademicYear();

        if (! $this->canAccessStudent($user, $student, $academicYear)) {
            $this->telegramService->sendMessage(
                $chatId,
                '⚠️ <b>Akses Ditolak:</b> Anda hanya berhak memeriksa rekap siswa di kelas perwalian Anda sendiri.',
                'bot_reply',
                $user->name,
                'HTML',
                $this->getMainMenuKeyboard($user)
            );

            return;
        }

        $endDate = Carbon::now('Asia/Jakarta')->endOfDay();

        if ($period === 'minggu') {
            $startDate = Carbon::now('Asia/Jakarta')->startOfWeek()->startOfDay();
            $periodTitle = 'Minggu Ini ('.$startDate->translatedFormat('d M').' - '.$endDate->translatedFormat('d M Y').')';
        } elseif ($period === 'bulan') {
            $startDate = Carbon::now('Asia/Jakarta')->startOfMonth()->startOfDay();
            $periodTitle = 'Bulan Ini ('.$startDate->translatedFormat('F Y').')';
        } else {
            $period = 'semua';
            $activeAy = AcademicYear::where('is_active', true)->first();
            if ($activeAy && $activeAy->start_date) {
                $startDate = Carbon::parse($activeAy->start_date, 'Asia/Jakarta')->startOfDay();
            } else {
                if (preg_match('/^(\d{4})/', $academicYear, $matches)) {
                    $startDate = Carbon::create((int) $matches[1], 7, 1, 0, 0, 0, 'Asia/Jakarta');
                } else {
                    $startYear = Carbon::now('Asia/Jakarta')->month >= 7 ? Carbon::now('Asia/Jakarta')->year : Carbon::now('Asia/Jakarta')->year - 1;
                    $startDate = Carbon::create($startYear, 7, 1, 0, 0, 0, 'Asia/Jakarta');
                }
            }
            $periodTitle = 'Awal TP Aktif s/d Hari Ini ('.$startDate->translatedFormat('d M Y').' - '.$endDate->translatedFormat('d M Y').')';
        }

        $startDateYmd = $startDate->format('Y-m-d');
        $endDateYmd = $endDate->format('Y-m-d');

        // 1. Data Presensi KBM (Subject Attendance Records)
        $attendanceRecords = SubjectAttendanceRecord::where('student_id', $student->id)
            ->whereHas('session', function ($q) use ($startDateYmd, $endDateYmd) {
                $q->whereDate('attendance_date', '>=', $startDateYmd)
                    ->whereDate('attendance_date', '<=', $endDateYmd);
            })
            ->with(['session.subject', 'session.teacher'])
            ->get();

        $hCount = $attendanceRecords->where('status', 'h')->count();
        $tCount = $attendanceRecords->where('status', 't')->count();
        $sCount = $attendanceRecords->where('status', 's')->count();
        $iCount = $attendanceRecords->where('status', 'i')->count();
        $aCount = $attendanceRecords->where('status', 'a')->count();
        $dCount = $attendanceRecords->where('status', 'd')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0
            ? round((($hCount + $tCount) / $totalSessions) * 100, 1)
            : 100.0;

        $nonPresentRecords = $attendanceRecords->filter(fn ($r) => in_array($r->status, ['a', 's', 'i', 't', 'd']))
            ->sortByDesc(fn ($r) => $r->session?->attendance_date?->timestamp ?? 0)
            ->take(4);

        $attendanceNotesList = [];
        foreach ($nonPresentRecords as $rec) {
            $recDate = $rec->session?->attendance_date?->translatedFormat('d M') ?? '-';
            $statusLabel = $rec->status_label ?? strtoupper($rec->status);
            $subjectName = $rec->session?->subject?->name ?? 'KBM';
            $attendanceNotesList[] = "• {$recDate}: <b>{$statusLabel}</b> ({$subjectName})";
        }

        // 2. Data Perizinan Meja Piket
        $permits = PiketStudentPermit::where('student_id', $student->id)
            ->whereDate('permit_date', '>=', $startDateYmd)
            ->whereDate('permit_date', '<=', $endDateYmd)
            ->orderByDesc('permit_date')
            ->get();

        $permitLateCount = $permits->where('permit_type', 'terlambat')->count();
        $permitLeaveCount = $permits->where('permit_type', 'izin_keluar_sementara')->count();
        $permitEarlyCount = $permits->where('permit_type', 'izin_pulang_cepat')->count();

        $recentPermitsList = [];
        foreach ($permits->take(3) as $permit) {
            $pDate = $permit->permit_date?->translatedFormat('d M') ?? '-';
            $typeLabel = match ($permit->permit_type) {
                'terlambat' => 'Terlambat Masuk',
                'izin_keluar_sementara' => 'Keluar Sementara',
                'izin_pulang_cepat' => 'Pulang Cepat',
                default => ucfirst($permit->permit_type ?? 'Izin'),
            };
            $reason = Str::limit($permit->reason ?? 'Tidak ada keterangan', 35);
            $recentPermitsList[] = "• {$pDate}: <b>{$typeLabel}</b> ({$reason})";
        }

        // 3. Data Kasus / Kejadian Siswa (Piket Incident)
        $incidents = PiketIncident::where(function ($q) use ($student) {
            $q->where('student_id', $student->id)
                ->orWhere('student_name', $student->name);
        })
            ->whereDate('incident_date', '>=', $startDateYmd)
            ->whereDate('incident_date', '<=', $endDateYmd)
            ->orderByDesc('incident_date')
            ->get();

        $totalIncidents = $incidents->count();
        $recentIncidentsList = [];
        foreach ($incidents->take(3) as $inc) {
            $incDate = $inc->incident_date?->translatedFormat('d M Y') ?? '-';
            $cat = $inc->category_label ?? ucfirst($inc->category ?? 'Kejadian');
            $desc = Str::limit($inc->description ?? '-', 50);
            $status = $inc->verification_status === 'verified' ? '✅ Terverifikasi' : '⏳ Menunggu Verifikasi';
            $recentIncidentsList[] = "• <b>{$incDate}</b> [{$cat}]\n  <i>{$desc}</i> ({$status})";
        }

        // Susun teks pesan lengkap
        $msg = "📊 <b>REKAP PRESENSI & KEJADIAN SISWA</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."👤 <b>Nama:</b> {$student->name}\n"
            ."🏫 <b>Kelas:</b> {$classroom->name}\n"
            .'🆔 <b>NIS:</b> '.($student->nis ?? '-')."\n"
            ."📅 <b>Periode:</b> {$periodTitle}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."📈 <b>PRESENSI KBM MATA PELAJARAN:</b>\n"
            ."• Total Sesi KBM : <b>{$totalSessions} pertemuan</b>\n"
            ."• Hadir (H)      : <b>{$hCount}</b>\n"
            ."• Terlambat (T)  : <b>{$tCount}</b>\n"
            ."• Sakit (S)      : <b>{$sCount}</b>\n"
            ."• Izin (I)       : <b>{$iCount}</b>\n"
            ."• Alpa (A)       : <b>{$aCount}</b>\n"
            ."• Dispen (D)     : <b>{$dCount}</b>\n"
            ."📊 <b>Tingkat Kehadiran: {$attendanceRate}%</b>\n";

        if (! empty($attendanceNotesList)) {
            $msg .= "\n<i>Catatan Absen Terakhir:</i>\n".implode("\n", $attendanceNotesList)."\n";
        }

        $msg .= "\n🎫 <b>CATATAN PERIZINAN MEJA PIKET:</b>\n"
            ."• Terlambat Masuk : <b>{$permitLateCount}x</b>\n"
            ."• Keluar Sementara: <b>{$permitLeaveCount}x</b>\n"
            ."• Pulang Cepat    : <b>{$permitEarlyCount}x</b>\n";

        if (! empty($recentPermitsList)) {
            $msg .= "\n<i>Riwayat Izin Terakhir:</i>\n".implode("\n", $recentPermitsList)."\n";
        }

        $msg .= "\n⚠️ <b>CATATAN KEJADIAN / PELANGGARAN:</b>\n"
            ."Total Kejadian: <b>{$totalIncidents} kasus</b>\n";

        if ($totalIncidents === 0) {
            $msg .= "✅ <i>Bersih / Tidak ada catatan kejadian pada periode ini.</i>\n";
        } else {
            $msg .= implode("\n", $recentIncidentsList)."\n";
            if ($totalIncidents > 3) {
                $more = $totalIncidents - 3;
                $msg .= "<i>(+ {$more} catatan kejadian lainnya di sistem)</i>\n";
            }
        }

        $msg .= "\n━━━━━━━━━━━━━━━━━━━━\n"
            .'⏱️ <i>Digenerate: '.Carbon::now('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB</i>';

        // Susun inline buttons
        $keyboard = [
            [
                ['text' => ($period === 'minggu' ? '✅ ' : '').'📅 Minggu Ini', 'callback_data' => 'att_per_minggu'],
                ['text' => ($period === 'bulan' ? '✅ ' : '').'🗓️ Bulan Ini', 'callback_data' => 'att_per_bulan'],
                ['text' => ($period === 'semua' ? '✅ ' : '').'📚 Semua', 'callback_data' => 'att_per_semua'],
            ],
            [
                ['text' => '👤 Pilih Siswa Lain', 'callback_data' => 'att_back_std'],
            ],
        ];

        if (! $this->isHomeroomOnlyForAttendance($user, $academicYear)) {
            $keyboard[1][] = ['text' => '🏫 Ganti Kelas', 'callback_data' => 'att_back_cls'];
        }

        $keyboard[] = [
            ['text' => '❌ Selesai', 'callback_data' => 'cancel_flow'],
        ];

        $this->telegramService->sendMessage(
            $chatId,
            $msg,
            'bot_reply',
            $user->name,
            'HTML',
            ['inline_keyboard' => $keyboard]
        );
    }

    /**
     * Kirim respons jika pesan tidak dikenali.
     */
    protected function replyUnknown(string $chatId, ?User $user): void
    {
        $msg = "Halo! Perintah tidak dikenali.\n\n"
            .'Silakan pilih salah satu tombol menu tugas piket di bawah atau ketik <code>/help</code> untuk panduan lengkap.';

        $this->telegramService->sendMessage($chatId, $msg, 'bot_reply', $user?->name ?? 'Petugas', 'HTML', $this->getMainMenuKeyboard($user));
    }

    /**
     * Answer callback query to Telegram API.
     */
    protected function answerCallbackQuery(string $callbackQueryId): void
    {
        $botToken = $this->telegramService->getBotToken();
        if (empty($botToken) || empty($callbackQueryId)) {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
            ]);
        } catch (Throwable) {
            // Silently ignore
        }
    }
}
