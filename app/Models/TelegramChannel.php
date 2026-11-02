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
        if ($channel) {
            // Jika record saluran ada di database:
            // Hanya kembalikan chat_id jika saluran aktif dan chat_id tidak kosong
            if ($channel->is_active && ! empty($channel->chat_id)) {
                return $channel->chat_id;
            }

            // Jika record saluran ada di database tetapi chat_id NULL/kosong atau tidak aktif,
            // JANGAN pernah fallback ke nilai lama di SchoolSetting! Karena artinya saluran memang kosong.
            return null;
        }

        // Fallback ke SchoolSetting HANYA jika record TelegramChannel belum ada sama sekali di tabel
        $setting = SchoolSetting::first();
        if (! $setting) {
            return null;
        }

        $fallbackId = match ($code) {
            'discipline' => $setting->telegram_discipline_chat_id,
            'piket' => $setting->telegram_piket_chat_id,
            'supervision' => $setting->telegram_supervision_chat_id,
            'activities' => $setting->telegram_activities_chat_id,
            'gtk' => $setting->telegram_teachers_chat_id,
            default => null,
        };

        if (empty($fallbackId)) {
            return null;
        }

        // Cegah jika fallbackId sudah aktif digunakan oleh channel lain di tabel telegram_channels
        $claimedByOther = self::where('chat_id', $fallbackId)->where('is_active', true)->exists();
        if ($claimedByOther) {
            return null;
        }

        return $fallbackId;
    }

    /**
     * Sinkronkan konfigurasi Chat ID dari tabel telegram_channels ke school_settings.
     */
    public static function syncAllToSchoolSettings(): void
    {
        $setting = SchoolSetting::first();
        if (! $setting) {
            return;
        }

        $disciplineChannel = self::findByCode('discipline');
        $piketChannel = self::findByCode('piket');
        $supervisionChannel = self::findByCode('supervision');
        $activitiesChannel = self::findByCode('activities');
        $gtkChannel = self::findByCode('gtk');

        $setting->update([
            'telegram_discipline_chat_id' => ($disciplineChannel && $disciplineChannel->is_active) ? $disciplineChannel->chat_id : null,
            'telegram_piket_chat_id' => ($piketChannel && $piketChannel->is_active) ? $piketChannel->chat_id : null,
            'telegram_supervision_chat_id' => ($supervisionChannel && $supervisionChannel->is_active) ? $supervisionChannel->chat_id : null,
            'telegram_activities_chat_id' => ($activitiesChannel && $activitiesChannel->is_active) ? $activitiesChannel->chat_id : null,
            'telegram_teachers_chat_id' => ($gtkChannel && $gtkChannel->is_active) ? $gtkChannel->chat_id : null,
        ]);
    }

    /**
     * Apakah saluran grup ini terbuka untuk seluruh warga GTK (tanpa batasan penugasan tertentu).
     */
    public function isAllGtk(): bool
    {
        $roles = $this->allowed_roles ?? [];

        if (empty($roles) || in_array('semua_gtk', $roles) || in_array('all', $roles)) {
            return true;
        }

        if (count($roles) >= 15) {
            return true;
        }

        return false;
    }

    /**
     * Periksa apakah seorang user diizinkan mengakses perintah di saluran grup ini.
     */
    public function isUserAllowed(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isAllGtk()) {
            return true;
        }

        $roles = $this->allowed_roles ?? [];
        $userRoleSlug = $user->role?->slug;
        if ($userRoleSlug && in_array($userRoleSlug, $roles)) {
            return true;
        }

        if ($user->roleAssignments()->whereHas('role', fn ($q) => $q->whereIn('slug', $roles))->exists()) {
            return true;
        }

        if (in_array('wali_kelas', $roles) && $user->isHomeroomTeacher()) {
            return true;
        }

        if ((in_array('guru_piket', $roles) || in_array('piket_officer', $roles)) && $user->canOperatePiket()) {
            return true;
        }

        return false;
    }

    /**
     * Dapatkan daftar user/GTK yang memenuhi syarat bergabung ke grup ini berdasarkan allowed_roles.
     */
    public function getEligibleUsers(): Collection
    {
        if ($this->isAllGtk()) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        $roles = $this->allowed_roles ?? [];

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
     * Definisi lengkap seluruh perintah bot dengan teks label, deskripsi, icon, dan kategori.
     */
    public static function getDefaultCommandDefinitions(): array
    {
        return [
            // 1. Layanan & Menu Pribadi GTK (Jalur Pribadi)
            '/presensi' => [
                'command' => '/presensi',
                'name' => 'presensi',
                'label' => '📍 Cek Presensi Saya',
                'description' => 'Cek log kehadiran presensi mandiri saya hari ini',
                'icon' => 'ti-map-pin',
                'category' => 'pribadi',
            ],
            '/jadwalsaya' => [
                'command' => '/jadwalsaya',
                'name' => 'jadwalsaya',
                'label' => '📅 Jadwal Mengajar Saya',
                'description' => 'Jadwal tatap muka KBM mengajar saya di rombel',
                'icon' => 'ti-calendar',
                'category' => 'pribadi',
            ],
            '/ajukan_izin' => [
                'command' => '/ajukan_izin',
                'name' => 'ajukan_izin',
                'label' => '📝 Ajukan Izin Saya',
                'description' => 'Formulir pengajuan izin GTK mandiri & titip materi KBM',
                'icon' => 'ti-file-pencil',
                'category' => 'pribadi',
            ],
            '/tukar' => [
                'command' => '/tukar',
                'name' => 'tukar',
                'label' => '🔄 Tukar Jadwal Piket',
                'description' => 'Pengajuan tukar jadwal tugas jaga piket ke rekan guru',
                'icon' => 'ti-arrows-left-right',
                'category' => 'pribadi',
            ],

            // 2. Formulir Perizinan Siswa & GTK
            '/izin_siswa' => [
                'command' => '/izin_siswa',
                'name' => 'izin_siswa',
                'label' => '🎫 Perizinan Siswa',
                'description' => 'Formulir input izin/dispensasi siswa oleh Wali Kelas / Piket',
                'icon' => 'ti-ticket',
                'category' => 'perizinan',
            ],
            '/izin_gtk' => [
                'command' => '/izin_gtk',
                'name' => 'izin_gtk',
                'label' => '📋 Perizinan GTK / Guru',
                'description' => 'Pencatatan perizinan GTK oleh Tim Piket sekolah',
                'icon' => 'ti-clipboard-list',
                'category' => 'perizinan',
            ],

            // 3. Operasional Piket & KBM Harian
            '/piket' => [
                'command' => '/piket',
                'name' => 'piket',
                'label' => '📋 Tim Piket Hari Ini',
                'description' => 'Daftar guru & tim piket bertugas hari ini',
                'icon' => 'ti-clipboard-check',
                'category' => 'piket',
            ],
            '/jadwal' => [
                'command' => '/jadwal',
                'name' => 'jadwal',
                'label' => '🏫 Jadwal Jam Sekarang',
                'description' => 'Jadwal guru & mapel KBM jam sekarang',
                'icon' => 'ti-clock',
                'category' => 'piket',
            ],
            '/inval' => [
                'command' => '/inval',
                'name' => 'inval',
                'label' => '🚨 Cek Inval / Kelas Kosong',
                'description' => 'Cek kelas kosong & guru pengganti (inval)',
                'icon' => 'ti-user-exclamation',
                'category' => 'piket',
            ],
            '/guru_izin' => [
                'command' => '/guru_izin',
                'name' => 'guru_izin',
                'label' => '📝 Guru Izin Hari Ini',
                'description' => 'Daftar GTK yang izin / dinas luar hari ini',
                'icon' => 'ti-calendar-off',
                'category' => 'piket',
            ],
            '/izin_keluar' => [
                'command' => '/izin_keluar',
                'name' => 'izin_keluar',
                'label' => '🎫 Izin Keluar Gerbang',
                'description' => 'Daftar siswa izin keluar kelas / gerbang',
                'icon' => 'ti-ticket',
                'category' => 'piket',
            ],
            '/tamu' => [
                'command' => '/tamu',
                'name' => 'tamu',
                'label' => '👥 Buku Tamu Hari Ini',
                'description' => 'Daftar buku tamu kunjungan sekolah',
                'icon' => 'ti-users',
                'category' => 'piket',
            ],
            '/serah_terima' => [
                'command' => '/serah_terima',
                'name' => 'serah_terima',
                'label' => '🤝 Serah Terima Piket',
                'description' => 'Catatan serah terima jaga piket harian',
                'icon' => 'ti-notebook',
                'category' => 'piket',
            ],
            '/lapor' => [
                'command' => '/lapor',
                'name' => 'lapor',
                'label' => '⚠️ Lapor Kasus Siswa',
                'description' => 'Lapor kasus / pelanggaran siswa (foto bukti)',
                'icon' => 'ti-alert-triangle',
                'category' => 'disiplin',
            ],
            '/poin' => [
                'command' => '/poin',
                'name' => 'poin',
                'label' => '🔍 Cek Poin & Kasus Siswa',
                'description' => 'Cek poin & rekam kedisiplinan siswa',
                'icon' => 'ti-search',
                'category' => 'disiplin',
            ],
            '/terlambat' => [
                'command' => '/terlambat',
                'name' => 'terlambat',
                'label' => '⏰ Siswa Terlambat Hari Ini',
                'description' => 'Daftar siswa terlambat masuk gerbang hari ini',
                'icon' => 'ti-clock-exclamation',
                'category' => 'disiplin',
            ],
            '/bolos' => [
                'command' => '/bolos',
                'name' => 'bolos',
                'label' => '🏃 Pantau Siswa Bolos',
                'description' => 'Daftar siswa terindikasi bolos / alpa KBM',
                'icon' => 'ti-run',
                'category' => 'disiplin',
            ],
            '/top_kasus' => [
                'command' => '/top_kasus',
                'name' => 'top_kasus',
                'label' => '📊 Top Kasus Siswa',
                'description' => 'Statistik & tren pelanggaran siswa teratas',
                'icon' => 'ti-chart-bar',
                'category' => 'disiplin',
            ],
            '/cek_absen_siswa' => [
                'command' => '/cek_absen_siswa',
                'name' => 'cek_absen_siswa',
                'label' => '📊 Cek Absensi Siswa',
                'description' => 'Pantau absensi harian dan presensi rombel kelas',
                'icon' => 'ti-user-check',
                'category' => 'disiplin',
            ],
            '/agenda' => [
                'command' => '/agenda',
                'name' => 'agenda',
                'label' => '🗓️ Agenda Sekolah',
                'description' => 'Agenda kegiatan sekolah 7 hari ke depan',
                'icon' => 'ti-calendar-event',
                'category' => 'akademik',
            ],
            '/kaldik' => [
                'command' => '/kaldik',
                'name' => 'kaldik',
                'label' => '📅 Kalender Pendidikan',
                'description' => 'Kalender pendidikan & hari efektif sekolah',
                'icon' => 'ti-calendar',
                'category' => 'akademik',
            ],
            '/asesmen' => [
                'command' => '/asesmen',
                'name' => 'asesmen',
                'label' => '📝 Jadwal Asesmen / Ujian',
                'description' => 'Jadwal asesmen, ujian & sumatif KBM',
                'icon' => 'ti-file-text',
                'category' => 'akademik',
            ],
            '/supervisi' => [
                'command' => '/supervisi',
                'name' => 'supervisi',
                'label' => '📋 Supervisi Guru',
                'description' => 'Jadwal & pantauan supervisi guru',
                'icon' => 'ti-checkbox',
                'category' => 'akademik',
            ],
            '/ultah' => [
                'command' => '/ultah',
                'name' => 'ultah',
                'label' => '🎂 Ultah GTK Bulan Ini',
                'description' => 'Daftar GTK yang berulang tahun bulan ini',
                'icon' => 'ti-cake',
                'category' => 'gtk',
            ],
            '/pengumuman' => [
                'command' => '/pengumuman',
                'name' => 'pengumuman',
                'label' => '📢 Pengumuman Resmi',
                'description' => 'Pengumuman resmi terbaru sekolah',
                'icon' => 'ti-speakerphone',
                'category' => 'gtk',
            ],
            '/info_sekolah' => [
                'command' => '/info_sekolah',
                'name' => 'info_sekolah',
                'label' => 'ℹ️ Profil & Info Sekolah',
                'description' => 'Informasi profil, alamat & kontak sekolah',
                'icon' => 'ti-info-circle',
                'category' => 'gtk',
            ],
            '/rekap' => [
                'command' => '/rekap',
                'name' => 'rekap',
                'label' => '📊 Rekap Cepat Hari Ini',
                'description' => 'Rekapitulasi cepat operasional harian sekolah',
                'icon' => 'ti-report-analytics',
                'category' => 'gtk',
            ],
            '/bantuan' => [
                'command' => '/bantuan',
                'name' => 'bantuan',
                'label' => 'ℹ️ Panduan Bantuan',
                'description' => 'Panduan bantuan penggunaan bot di grup ini',
                'icon' => 'ti-help',
                'category' => 'utilitas',
            ],
            '/id' => [
                'command' => '/id',
                'name' => 'id',
                'label' => '🆔 Cek Chat ID Grup',
                'description' => 'Cek nomor Chat ID Telegram unik grup ini',
                'icon' => 'ti-fingerprint',
                'category' => 'utilitas',
            ],
        ];
    }

    /**
     * Kategori pengelompokan perintah.
     */
    public static function getCommandCategories(): array
    {
        return [
            'pribadi' => [
                'name' => 'Layanan & Menu Pribadi GTK',
                'icon' => 'ti-user-check',
                'badge' => 'bg-indigo text-white',
            ],
            'perizinan' => [
                'name' => 'Perizinan Siswa & GTK',
                'icon' => 'ti-ticket',
                'badge' => 'bg-azure text-white',
            ],
            'piket' => [
                'name' => 'Operasional Piket & KBM Harian',
                'icon' => 'ti-calendar-user',
                'badge' => 'bg-primary text-white',
            ],
            'disiplin' => [
                'name' => 'Kedisiplinan, BK & Kasus Siswa',
                'icon' => 'ti-shield-check',
                'badge' => 'bg-warning text-dark',
            ],
            'akademik' => [
                'name' => 'Agenda, Kaldik & Supervisi Guru',
                'icon' => 'ti-calendar-event',
                'badge' => 'bg-info text-white',
            ],
            'gtk' => [
                'name' => 'Warga GTK & Informasi Sekolah',
                'icon' => 'ti-users',
                'badge' => 'bg-success text-white',
            ],
            'utilitas' => [
                'name' => 'Bantuan & Utilitas Bot',
                'icon' => 'ti-settings',
                'badge' => 'bg-secondary text-white',
            ],
        ];
    }

    /**
     * Preset pilihan cepat perintah untuk masing-masing jenis grup & jalur pribadi.
     */
    public static function getCommandPresets(): array
    {
        return [
            'private_standard' => [
                'name' => 'Paket Standar Jalur Pribadi',
                'icon' => 'ti-user-check',
                'color' => 'btn-outline-indigo',
                'commands' => ['/presensi', '/jadwalsaya', '/ajukan_izin', '/piket', '/jadwal', '/inval', '/rekap', '/agenda', '/tamu', '/tukar', '/bantuan', '/id'],
            ],
            'private_complete' => [
                'name' => 'Paket Lengkap Jalur Pribadi',
                'icon' => 'ti-sparkles',
                'color' => 'btn-outline-primary',
                'commands' => ['/presensi', '/jadwalsaya', '/ajukan_izin', '/izin_siswa', '/izin_gtk', '/lapor', '/poin', '/piket', '/jadwal', '/inval', '/cek_absen_siswa', '/rekap', '/agenda', '/tamu', '/tukar', '/kaldik', '/ultah', '/pengumuman', '/info_sekolah', '/bantuan', '/id'],
            ],
            'piket' => [
                'name' => 'Paket Piket Harian',
                'icon' => 'ti-calendar-user',
                'color' => 'btn-outline-primary',
                'commands' => ['/piket', '/jadwal', '/inval', '/guru_izin', '/izin_keluar', '/serah_terima', '/tamu', '/rekap', '/bantuan', '/id'],
            ],
            'disiplin' => [
                'name' => 'Paket Kedisiplinan & BK',
                'icon' => 'ti-shield-check',
                'color' => 'btn-outline-warning',
                'commands' => ['/lapor', '/poin', '/terlambat', '/bolos', '/top_kasus', '/cek_absen_siswa', '/rekap', '/bantuan', '/id'],
            ],
            'kegiatan' => [
                'name' => 'Paket Kegiatan & Kaldik',
                'icon' => 'ti-calendar-star',
                'color' => 'btn-outline-info',
                'commands' => ['/agenda', '/kaldik', '/asesmen', '/supervisi', '/jadwal', '/bantuan', '/id'],
            ],
            'gtk' => [
                'name' => 'Paket Warga GTK',
                'icon' => 'ti-users-group',
                'color' => 'btn-outline-success',
                'commands' => ['/piket', '/jadwal', '/agenda', '/ultah', '/pengumuman', '/info_sekolah', '/rekap', '/bantuan', '/id'],
            ],
        ];
    }

    /**
     * Dapatkan atau buat saluran khusus untuk konfigurasi Bot Jalur Pribadi (Direct Message).
     */
    public static function getOrCreatePrivateChatChannel(): self
    {
        return self::firstOrCreate(
            ['code' => 'private_chat'],
            [
                'name' => 'Jalur Pribadi (Direct Message Bot)',
                'description' => 'Konfigurasi menu perintah dan tombol interaktif yang tampil saat GTK/guru chat langsung dengan Bot SIAP.',
                'allowed_roles' => ['semua_gtk'],
                'enabled_commands' => [
                    '/presensi',
                    '/jadwalsaya',
                    '/ajukan_izin',
                    '/izin_siswa',
                    '/piket',
                    '/jadwal',
                    '/inval',
                    '/cek_absen_siswa',
                    '/rekap',
                    '/agenda',
                    '/tamu',
                    '/tukar',
                    '/bantuan',
                    '/id',
                ],
                'show_reply_keyboard' => true,
                'custom_buttons' => [],
                'is_active' => true,
            ]
        );
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
     * Bangun susunan default Reply Keyboard untuk grup sekolah yang belum terdaftar khusus.
     */
    public static function buildDefaultGroupKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📋 Tim Piket Hari Ini'], ['text' => '🏫 Jadwal Jam Sekarang']],
                [['text' => '🚨 Cek Inval / Kelas Kosong'], ['text' => '🗓️ Agenda Sekolah']],
                [['text' => '📊 Rekap Cepat Hari Ini'], ['text' => 'ℹ️ Panduan Bantuan']],
                [['text' => '🆔 Cek Chat ID Grup']],
            ],
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
