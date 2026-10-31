<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'chat_id',
        'description',
        'invite_link',
        'allowed_roles',
        'enabled_commands',
        'show_reply_keyboard',
        'custom_buttons',
        'is_active',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'enabled_commands' => 'array',
        'show_reply_keyboard' => 'boolean',
        'custom_buttons' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Dapatkan channel berdasarkan kodenya.
     */
    public static function findByCode(string $code): ?self
    {
        return self::where('code', $code)->first();
    }

    /**
     * Dapatkan Chat ID grup berdasarkan kodenya.
     */
    public static function getChatId(string $code): ?string
    {
        $channel = self::findByCode($code);
        if ($channel && $channel->is_active && ! empty($channel->chat_id)) {
            return $channel->chat_id;
        }

        // Fallback ke SchoolSetting jika belum diisi di TelegramChannel
        $setting = SchoolSetting::first();
        if (! $setting) {
            return null;
        }

        return match ($code) {
            'discipline' => $setting->telegram_discipline_chat_id,
            'piket' => $setting->telegram_piket_chat_id,
            'supervision' => $setting->telegram_supervision_chat_id,
            'activities' => $setting->telegram_activities_chat_id,
            'gtk' => $setting->telegram_teachers_chat_id,
            default => null,
        };
    }

    /**
     * Dapatkan daftar user/GTK yang memenuhi syarat bergabung ke grup ini berdasarkan allowed_roles.
     */
    public function getEligibleUsers(): Collection
    {
        $roles = $this->allowed_roles ?? [];
        if (empty($roles)) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        return User::where('is_active', true)
            ->where(function ($q) use ($roles) {
                $q->whereHas('role', fn ($sub) => $sub->whereIn('slug', $roles))
                    ->orWhereHas('roleAssignments.role', fn ($sub) => $sub->whereIn('slug', $roles));

                // Jika grup mengizinkan Wali Kelas, sertakan guru yang tercatat sebagai wali kelas aktif di tabel classrooms
                if (in_array('wali_kelas', $roles)) {
                    $q->orWhereIn('id', Classroom::whereNotNull('homeroom_teacher_id')->pluck('homeroom_teacher_id'));
                }

                // Jika grup mengizinkan Guru Piket, sertakan guru yang memiliki jadwal piket aktif
                if (in_array('guru_piket', $roles) || in_array('piket_officer', $roles)) {
                    $q->orWhereIn('id', PiketSchedule::where('is_active', true)->pluck('teacher_id'));
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Definisi lengkap seluruh perintah bot dengan teks label dan deskripsi default.
     */
    public static function getDefaultCommandDefinitions(): array
    {
        return [
            '/piket' => [
                'command' => '/piket',
                'name' => 'piket',
                'label' => '📋 Tim Piket Hari Ini',
                'description' => 'Daftar guru & tim piket bertugas hari ini',
                'icon' => 'ti-clipboard-check',
            ],
            '/jadwal' => [
                'command' => '/jadwal',
                'name' => 'jadwal',
                'label' => '🏫 Jadwal Jam Sekarang',
                'description' => 'Jadwal guru & mapel KBM jam sekarang',
                'icon' => 'ti-clock',
            ],
            '/inval' => [
                'command' => '/inval',
                'name' => 'inval',
                'label' => '🚨 Cek Inval / Kelas Kosong',
                'description' => 'Cek kelas kosong & guru pengganti (inval)',
                'icon' => 'ti-user-exclamation',
            ],
            '/lapor' => [
                'command' => '/lapor',
                'name' => 'lapor',
                'label' => '⚠️ Lapor Kasus Siswa',
                'description' => 'Lapor kasus / pelanggaran siswa (foto bukti)',
                'icon' => 'ti-alert-triangle',
            ],
            '/poin' => [
                'command' => '/poin',
                'name' => 'poin',
                'label' => '🔍 Cek Poin & Kasus Siswa',
                'description' => 'Cek poin & rekam kedisiplinan siswa',
                'icon' => 'ti-search',
            ],
            '/terlambat' => [
                'command' => '/terlambat',
                'name' => 'terlambat',
                'label' => '⏰ Siswa Terlambat Hari Ini',
                'description' => 'Daftar siswa terlambat masuk gerbang hari ini',
                'icon' => 'ti-clock-exclamation',
            ],
            '/bolos' => [
                'command' => '/bolos',
                'name' => 'bolos',
                'label' => '🏃 Pantau Siswa Bolos',
                'description' => 'Daftar siswa terindikasi bolos / alpa KBM',
                'icon' => 'ti-run',
            ],
            '/top_kasus' => [
                'command' => '/top_kasus',
                'name' => 'top_kasus',
                'label' => '📊 Top Kasus Siswa',
                'description' => 'Statistik & tren pelanggaran siswa teratas',
                'icon' => 'ti-chart-bar',
            ],
            '/guru_izin' => [
                'command' => '/guru_izin',
                'name' => 'guru_izin',
                'label' => '📝 Guru Izin Hari Ini',
                'description' => 'Daftar GTK yang izin / dinas luar hari ini',
                'icon' => 'ti-calendar-off',
            ],
            '/izin_keluar' => [
                'command' => '/izin_keluar',
                'name' => 'izin_keluar',
                'label' => '🎫 Izin Keluar Gerbang',
                'description' => 'Daftar siswa izin keluar kelas / gerbang',
                'icon' => 'ti-ticket',
            ],
            '/tamu' => [
                'command' => '/tamu',
                'name' => 'tamu',
                'label' => '👥 Buku Tamu Hari Ini',
                'description' => 'Daftar buku tamu kunjungan sekolah',
                'icon' => 'ti-users',
            ],
            '/serah_terima' => [
                'command' => '/serah_terima',
                'name' => 'serah_terima',
                'label' => '🤝 Serah Terima Piket',
                'description' => 'Catatan serah terima jaga piket harian',
                'icon' => 'ti-notebook',
            ],
            '/agenda' => [
                'command' => '/agenda',
                'name' => 'agenda',
                'label' => '🗓️ Agenda Sekolah',
                'description' => 'Agenda kegiatan sekolah 7 hari ke depan',
                'icon' => 'ti-calendar-event',
            ],
            '/kaldik' => [
                'command' => '/kaldik',
                'name' => 'kaldik',
                'label' => '📅 Kalender Pendidikan',
                'description' => 'Kalender pendidikan & hari efektif sekolah',
                'icon' => 'ti-calendar',
            ],
            '/asesmen' => [
                'command' => '/asesmen',
                'name' => 'asesmen',
                'label' => '📝 Jadwal Asesmen / Ujian',
                'description' => 'Jadwal asesmen, ujian & sumatif KBM',
                'icon' => 'ti-file-text',
            ],
            '/supervisi' => [
                'command' => '/supervisi',
                'name' => 'supervisi',
                'label' => '📋 Supervisi Guru',
                'description' => 'Jadwal & pantauan supervisi guru',
                'icon' => 'ti-checkbox',
            ],
            '/ultah' => [
                'command' => '/ultah',
                'name' => 'ultah',
                'label' => '🎂 Ultah GTK Bulan Ini',
                'description' => 'Daftar GTK yang berulang tahun bulan ini',
                'icon' => 'ti-cake',
            ],
            '/info_sekolah' => [
                'command' => '/info_sekolah',
                'name' => 'info_sekolah',
                'label' => 'ℹ️ Profil & Info Sekolah',
                'description' => 'Informasi profil, alamat & kontak sekolah',
                'icon' => 'ti-info-circle',
            ],
            '/pengumuman' => [
                'command' => '/pengumuman',
                'name' => 'pengumuman',
                'label' => '📢 Pengumuman Resmi',
                'description' => 'Pengumuman resmi terbaru sekolah',
                'icon' => 'ti-speakerphone',
            ],
            '/rekap' => [
                'command' => '/rekap',
                'name' => 'rekap',
                'label' => '📊 Rekap Cepat Hari Ini',
                'description' => 'Rekapitulasi cepat operasional harian sekolah',
                'icon' => 'ti-report-analytics',
            ],
            '/bantuan' => [
                'command' => '/bantuan',
                'name' => 'bantuan',
                'label' => 'ℹ️ Panduan Bantuan',
                'description' => 'Panduan bantuan penggunaan bot di grup ini',
                'icon' => 'ti-help',
            ],
            '/id' => [
                'command' => '/id',
                'name' => 'id',
                'label' => '🆔 Cek Chat ID Grup',
                'description' => 'Cek nomor Chat ID Telegram unik grup ini',
                'icon' => 'ti-fingerprint',
            ],
        ];
    }

    /**
     * Dapatkan label tombol untuk suatu perintah di saluran ini.
     */
    public function getButtonLabel(string $cmd): string
    {
        $custom = $this->custom_buttons[$cmd]['label'] ?? null;
        if (! empty($custom)) {
            return trim($custom);
        }

        $defs = self::getDefaultCommandDefinitions();

        return $defs[$cmd]['label'] ?? $cmd;
    }

    /**
     * Dapatkan deskripsi perintah untuk Telegram API setMyCommands.
     */
    public function getCommandDescription(string $cmd): string
    {
        $custom = $this->custom_buttons[$cmd]['description'] ?? null;
        if (! empty($custom)) {
            return trim($custom);
        }

        $defs = self::getDefaultCommandDefinitions();

        return $defs[$cmd]['description'] ?? "Perintah {$cmd}";
    }

    /**
     * Periksa apakah perintah ini dipilih sebagai tombol Reply Keyboard di grup ini.
     */
    public function isReplyButtonEnabled(string $cmd): bool
    {
        $conf = $this->custom_buttons[$cmd] ?? null;
        if ($conf !== null && isset($conf['show_reply_button'])) {
            return (bool) $conf['show_reply_button'];
        }

        return true;
    }

    /**
     * Bangun susunan Reply Keyboard untuk saluran grup ini.
     */
    public function buildReplyKeyboard(): array
    {
        if (! $this->show_reply_keyboard) {
            return ['remove_keyboard' => true];
        }

        $enabled = $this->enabled_commands ?? [];
        $selectedButtons = [];

        foreach ($enabled as $cmd) {
            if ($this->isReplyButtonEnabled($cmd)) {
                $selectedButtons[] = $this->getButtonLabel($cmd);
            }
        }

        if (empty($selectedButtons)) {
            return ['remove_keyboard' => true];
        }

        $rows = [];
        $chunks = array_chunk($selectedButtons, 2);
        foreach ($chunks as $chunk) {
            $btnRow = [];
            foreach ($chunk as $label) {
                $btnRow[] = ['text' => $label];
            }
            $rows[] = $btnRow;
        }

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /**
     * Mengetahui perintah asli dari teks tombol kustom yang dikirim pengguna.
     */
    public static function resolveCommandFromText(string $text, ?self $channel = null): ?string
    {
        $cleanText = trim($text);
        if (str_starts_with($cleanText, '/')) {
            return null;
        }

        $defs = self::getDefaultCommandDefinitions();

        // 1. Cek konfigurasi kustom saluran jika ada
        if ($channel && ! empty($channel->custom_buttons)) {
            foreach ($channel->custom_buttons as $cmd => $conf) {
                if (! empty($conf['label']) && mb_strtolower(trim($conf['label'])) === mb_strtolower($cleanText)) {
                    return $cmd;
                }
            }
        }

        // 2. Cek label default
        foreach ($defs as $cmd => $info) {
            if (mb_strtolower(trim($info['label'])) === mb_strtolower($cleanText)) {
                return $cmd;
            }
        }

        return null;
    }
}
