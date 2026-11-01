<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramMessageTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'command',
        'template',
        'default_template',
        'available_placeholders',
        'description',
        'is_active',
    ];

    protected $casts = [
        'available_placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Render pesan berdasarkan kode template dan data variabel pengganti.
     */
    public static function render(string $code, array $data = [], ?string $fallback = null): string
    {
        $templateModel = self::where('code', $code)->first();

        $content = null;
        if ($templateModel && $templateModel->is_active && ! empty($templateModel->template)) {
            $content = $templateModel->template;
        } elseif ($templateModel && ! empty($templateModel->default_template)) {
            $content = $templateModel->default_template;
        } else {
            $content = $fallback ?? '';
        }

        if (empty($content)) {
            return $fallback ?? '';
        }

        // Otomatis inject data umum jika belum di-set
        if (! isset($data['nama_sekolah'])) {
            $setting = SchoolSetting::first();
            $data['nama_sekolah'] = $setting?->school_name ?: 'SMK PGRI KRAS';
        }

        // Ganti semua placeholder {key} dengan nilainya
        foreach ($data as $key => $value) {
            $valStr = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
            $content = str_replace('{'.$key.'}', $valStr, $content);
        }

        return $content;
    }

    /**
     * Sinkronkan template default bawaan pabrik ke database (menambah yang belum ada dan memperbarui metadata).
     */
    public static function syncDefaultTemplates(): void
    {
        foreach (self::getDefaultSeedData() as $item) {
            $existing = self::where('code', $item['code'])->first();
            if (! $existing) {
                self::create($item);
            } else {
                $existing->update([
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'command' => $item['command'],
                    'default_template' => $item['default_template'],
                    'available_placeholders' => $item['available_placeholders'],
                    'description' => $item['description'],
                ]);
            }
        }
    }

    /**
     * Kembalikan isi template ke default bawaan pabrik.
     */
    public function resetToDefault(): void
    {
        $this->update([
            'template' => $this->default_template,
        ]);
    }

    /**
     * Data template bawaan pabrik awal untuk migrasi dan reset massal.
     */
    public static function getDefaultSeedData(): array
    {
        return [
            [
                'code' => 'piket',
                'name' => 'Laporan Tim Piket Hari Ini',
                'category' => 'piket',
                'command' => '/piket',
                'description' => 'Balasan saat pengguna atau tombol menanyakan daftar guru & tim piket yang bertugas hari ini.',
                'template' => "📋 <b>TIM PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\nTahun Ajaran: {tahun_ajaran}\nStatus Jurnal: <code>{status_jurnal}</code>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_petugas}\n━━━━━━━━━━━━━━━━━━━━\n<i>Keterangan: (⭐) Koordinator Piket • (📱) Akun Telegram Aktif</i>",
                'default_template' => "📋 <b>TIM PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\nTahun Ajaran: {tahun_ajaran}\nStatus Jurnal: <code>{status_jurnal}</code>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_petugas}\n━━━━━━━━━━━━━━━━━━━━\n<i>Keterangan: (⭐) Koordinator Piket • (📱) Akun Telegram Aktif</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal pelaksanaan (contoh: Senin, 02 November 2026)',
                    'tahun_ajaran' => 'Tahun pelajaran aktif (contoh: 2026/2027)',
                    'status_jurnal' => 'Status jurnal piket (DIBUKA / BELUM DIBUAT / SELESAI)',
                    'daftar_petugas' => 'Daftar berurutan nama-nama guru piket hari ini',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'jadwal',
                'name' => 'Jadwal Pelajaran KBM Jam Sekarang',
                'category' => 'kbm',
                'command' => '/jadwal',
                'description' => 'Balasan saat mengecek jadwal KBM dan kehadiran guru pada jam pelajaran yang sedang berlangsung.',
                'template' => "🏫 <b>JADWAL PELAJARAN KELAS SAAT INI</b>\n⏰ <b>Waktu:</b> {waktu} WIB\n📌 <b>Status:</b> {status_jam}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_jadwal}\n━━━━━━━━━━━━━━━━━━━━\n<i>Gunakan untuk memeriksa kehadiran guru dan ketertiban kelas.</i>",
                'default_template' => "🏫 <b>JADWAL PELAJARAN KELAS SAAT INI</b>\n⏰ <b>Waktu:</b> {waktu} WIB\n📌 <b>Status:</b> {status_jam}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_jadwal}\n━━━━━━━━━━━━━━━━━━━━\n<i>Gunakan untuk memeriksa kehadiran guru dan ketertiban kelas.</i>",
                'available_placeholders' => [
                    'waktu' => 'Jam sekarang (contoh: 08:15)',
                    'status_jam' => 'Label jam KBM (contoh: Jam ke-2 (07:45 - 08:30 WIB))',
                    'daftar_jadwal' => 'Daftar jadwal per kelas, mapel, dan guru pengajar',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'inval',
                'name' => 'Status Kelas Kosong & Inval KBM',
                'category' => 'kbm',
                'command' => '/inval',
                'description' => 'Balasan laporan kelas yang gurunya berhalangan hadir beserta penugasan guru pengganti (inval).',
                'template' => "🚨 <b>STATUS KELAS KOSONG & INVAL HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_inval}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data diperbarui real-time dari pencatatan meja piket.</i>",
                'default_template' => "🚨 <b>STATUS KELAS KOSONG & INVAL HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_inval}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data diperbarui real-time dari pencatatan meja piket.</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_inval' => 'Daftar kelas kosong, alasan izin guru, dan guru penggantinya',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'rekap',
                'name' => 'Rekap Cepat Operasional Piket',
                'category' => 'piket',
                'command' => '/rekap',
                'description' => 'Ringkasan statistik harian: jumlah siswa terlambat, izin keluar, buku tamu, dan kasus.',
                'template' => "📊 <b>REKAP CEPAT PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n🔔 <b>Siswa Terlambat:</b> {total_terlambat} siswa\n🏃 <b>Izin Keluar / Pulang Cepat:</b> {total_izin_keluar} siswa\n📑 <b>Total Surat Izin Diterbitkan:</b> {total_surat_izin} surat\n⚠️ <b>Laporan Kejadian/Kasus:</b> {total_kasus} kasus\n👥 <b>Buku Tamu Berkunjung:</b> {total_tamu} orang\n━━━━━━━━━━━━━━━━━━━━\n<i>Data diperbarui secara real-time dari database piket sekolah.</i>",
                'default_template' => "📊 <b>REKAP CEPAT PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n🔔 <b>Siswa Terlambat:</b> {total_terlambat} siswa\n🏃 <b>Izin Keluar / Pulang Cepat:</b> {total_izin_keluar} siswa\n📑 <b>Total Surat Izin Diterbitkan:</b> {total_surat_izin} surat\n⚠️ <b>Laporan Kejadian/Kasus:</b> {total_kasus} kasus\n👥 <b>Buku Tamu Berkunjung:</b> {total_tamu} orang\n━━━━━━━━━━━━━━━━━━━━\n<i>Data diperbarui secara real-time dari database piket sekolah.</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'total_terlambat' => 'Angka jumlah siswa terlambat',
                    'total_izin_keluar' => 'Angka jumlah siswa izin keluar/pulang cepat',
                    'total_surat_izin' => 'Total surat izin diterbitkan',
                    'total_kasus' => 'Jumlah kasus/insiden siswa',
                    'total_tamu' => 'Jumlah pengunjung buku tamu',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'agenda',
                'name' => 'Agenda & Kegiatan Sekolah Terdekat',
                'category' => 'informasi',
                'command' => '/agenda',
                'description' => 'Balasan pengumuman agenda terdekat dalam kalender pendidikan & kurikulum sekolah.',
                'template' => "🗓️ <b>AGENDA & KEGIATAN SEKOLAH TERDEKAT</b>\nPeriode: {periode}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_agenda}\n━━━━━━━━━━━━━━━━━━━━\n<i>Sumber: Kalender Akademik & Agenda Kegiatan Kurikulum.</i>",
                'default_template' => "🗓️ <b>AGENDA & KEGIATAN SEKOLAH TERDEKAT</b>\nPeriode: {periode}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_agenda}\n━━━━━━━━━━━━━━━━━━━━\n<i>Sumber: Kalender Akademik & Agenda Kegiatan Kurikulum.</i>",
                'available_placeholders' => [
                    'periode' => 'Rentang tanggal 7 hari ke depan',
                    'daftar_agenda' => 'Daftar rincian kegiatan terdekat (nama agenda, tanggal, lokasi)',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'tamu',
                'name' => 'Catatan Buku Tamu Sekolah',
                'category' => 'piket',
                'command' => '/tamu',
                'description' => 'Balasan rincian tamu umum/kedinasan yang berkunjung ke meja piket hari ini.',
                'template' => "👥 <b>BUKU TAMU PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_tamu}\n━━━━━━━━━━━━━━━━━━━━\n<i>Total tamu berkunjung hari ini: {total_tamu} orang.</i>",
                'default_template' => "👥 <b>BUKU TAMU PIKET HARI INI</b>\n🗓️ <b>{tanggal}</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_tamu}\n━━━━━━━━━━━━━━━━━━━━\n<i>Total tamu berkunjung hari ini: {total_tamu} orang.</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal kunjungan',
                    'daftar_tamu' => 'Daftar nama tamu, instansi, jam berkunjung, dan tujuan',
                    'total_tamu' => 'Total tamu hari ini',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'ultah',
                'name' => 'Ucapan Selamat Ulang Tahun GTK',
                'category' => 'informasi',
                'command' => '/ultah',
                'description' => 'Balasan doa dan ucapan selamat ulang tahun untuk Bapak/Ibu Guru dan Karyawan yang berulang tahun hari ini.',
                'template' => "🎂 <b>INFO ULANG TAHUN KELUARGA GTK</b>\n📅 <b>Hari Ini:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_ultah}\n━━━━━━━━━━━━━━━━━━━━\n<i>Keluarga Besar {nama_sekolah}</i>",
                'default_template' => "🎂 <b>INFO ULANG TAHUN KELUARGA GTK</b>\n📅 <b>Hari Ini:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_ultah}\n━━━━━━━━━━━━━━━━━━━━\n<i>Keluarga Besar {nama_sekolah}</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_ultah' => 'Daftar nama GTK yang berulang tahun beserta ucapan doa',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'info_sekolah',
                'name' => 'Profil & Informasi Resmi Sekolah',
                'category' => 'informasi',
                'command' => '/info_sekolah',
                'description' => 'Balasan kartu profil sekolah: NPSN, pimpinan, data siswa, guru, rombel, alamat, dan kontak.',
                'template' => "🏫 <b>PROFIL & INFORMASI RESMI SEKOLAH</b>\n<b>{nama_sekolah}</b>\n━━━━━━━━━━━━━━━━━━━━\n📌 <b>NPSN:</b> <code>{npsn}</code>\n👤 <b>Kepala Sekolah:</b> {kepala_sekolah}\n📅 <b>Tahun Pelajaran:</b> {tahun_ajaran}\n\n📊 <b>Data Statistik Warga Sekolah:</b>\n• 👥 Peserta Didik Aktif: <b>{total_siswa}</b> siswa\n• 🏫 Rombongan Belajar: <b>{total_rombel}</b> kelas\n• 👨‍🏫 Dewan Guru Pengajar: <b>{total_guru}</b> guru\n• 💼 Tenaga Kependidikan: <b>{total_staf}</b> staf/TU\n\n📍 <b>Alamat:</b> {alamat}\n📞 <b>Kontak:</b> {kontak}\n━━━━━━━━━━━━━━━━━━━━\n<i>Sistem Informasi Administrasi dan Pendidikan (SIAP)</i>",
                'default_template' => "🏫 <b>PROFIL & INFORMASI RESMI SEKOLAH</b>\n<b>{nama_sekolah}</b>\n━━━━━━━━━━━━━━━━━━━━\n📌 <b>NPSN:</b> <code>{npsn}</code>\n👤 <b>Kepala Sekolah:</b> {kepala_sekolah}\n📅 <b>Tahun Pelajaran:</b> {tahun_ajaran}\n\n📊 <b>Data Statistik Warga Sekolah:</b>\n• 👥 Peserta Didik Aktif: <b>{total_siswa}</b> siswa\n• 🏫 Rombongan Belajar: <b>{total_rombel}</b> kelas\n• 👨‍🏫 Dewan Guru Pengajar: <b>{total_guru}</b> guru\n• 💼 Tenaga Kependidikan: <b>{total_staf}</b> staf/TU\n\n📍 <b>Alamat:</b> {alamat}\n📞 <b>Kontak:</b> {kontak}\n━━━━━━━━━━━━━━━━━━━━\n<i>Sistem Informasi Administrasi dan Pendidikan (SIAP)</i>",
                'available_placeholders' => [
                    'nama_sekolah' => 'Nama lengkap sekolah',
                    'npsn' => 'Nomor Pokok Sekolah Nasional',
                    'kepala_sekolah' => 'Nama Kepala Sekolah',
                    'tahun_ajaran' => 'Tahun ajaran operasional',
                    'total_siswa' => 'Jumlah seluruh siswa aktif',
                    'total_rombel' => 'Jumlah rombongan belajar / kelas',
                    'total_guru' => 'Jumlah dewan guru aktif',
                    'total_staf' => 'Jumlah staf & tata usaha',
                    'alamat' => 'Alamat lengkap sekolah',
                    'kontak' => 'Nomor telepon / kontak resmi',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'pengumuman',
                'name' => 'Pengumuman & Edaran Resmi',
                'category' => 'informasi',
                'command' => '/pengumuman',
                'description' => 'Balasan untuk menampilkan edaran atau siaran pengumuman resmi terbaru dari manajemen sekolah.',
                'template' => "📢 <b>PENGUMUMAN & EDARAN RESMI TERBARU</b>\n━━━━━━━━━━━━━━━━━━━━\n{isi_pengumuman}\n━━━━━━━━━━━━━━━━━━━━\n<i>Pemberitahuan Resmi {nama_sekolah}</i>",
                'default_template' => "📢 <b>PENGUMUMAN & EDARAN RESMI TERBARU</b>\n━━━━━━━━━━━━━━━━━━━━\n{isi_pengumuman}\n━━━━━━━━━━━━━━━━━━━━\n<i>Pemberitahuan Resmi {nama_sekolah}</i>",
                'available_placeholders' => [
                    'waktu_terbit' => 'Waktu publikasi pengumuman',
                    'isi_pengumuman' => 'Isi teks edaran pengumuman',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'start_group',
                'name' => 'Sambutan Menu Cepat di Grup',
                'category' => 'umum',
                'command' => '/start',
                'description' => 'Pesan pembuka saat bot diajak berinteraksi atau mengetik /menu /start di dalam saluran grup.',
                'template' => "🤖 <b>MENU TOMBOL INTERAKTIF SIAP</b>\nSaluran: <b>{nama_grup}</b>\n━━━━━━━━━━━━━━━━━━━━\nHalo Bapak/Ibu Petugas & GTK, menu tombol cepat telah aktif untuk grup ini.\n\nGunakan tombol reply di bawah layar untuk memeriksa data piket dan informasi sekolah dengan mudah.\n━━━━━━━━━━━━━━━━━━━━\n<i>Ketik /help untuk panduan lengkap atau /menu untuk memunculkan tombol kembali.</i>",
                'default_template' => "🤖 <b>MENU TOMBOL INTERAKTIF SIAP</b>\nSaluran: <b>{nama_grup}</b>\n━━━━━━━━━━━━━━━━━━━━\nHalo Bapak/Ibu Petugas & GTK, menu tombol cepat telah aktif untuk grup ini.\n\nGunakan tombol reply di bawah layar untuk memeriksa data piket dan informasi sekolah dengan mudah.\n━━━━━━━━━━━━━━━━━━━━\n<i>Ketik /help untuk panduan lengkap atau /menu untuk memunculkan tombol kembali.</i>",
                'available_placeholders' => [
                    'nama_grup' => 'Nama saluran grup Telegram',
                    'nama_pengirim' => 'Nama pengguna pengirim',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'guru_izin',
                'name' => 'Daftar Guru Izin & Inval Hari Ini',
                'category' => 'piket',
                'command' => '/guru_izin',
                'description' => 'Balasan daftar guru berhalangan hadir dinas beserta penugasan guru pengganti (inval).',
                'template' => "📋 <b>DAFTAR GURU IZIN & INVAL HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_guru_izin}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data sinkron dengan Modul Izin GTK & Meja Piket {nama_sekolah}</i>",
                'default_template' => "📋 <b>DAFTAR GURU IZIN & INVAL HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_guru_izin}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data sinkron dengan Modul Izin GTK & Meja Piket {nama_sekolah}</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_guru_izin' => 'Daftar guru izin dan penugasan guru pengganti (inval)',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'izin_keluar',
                'name' => 'Daftar Siswa Izin Keluar / Pulang',
                'category' => 'piket',
                'command' => '/izin_keluar',
                'description' => 'Balasan rincian siswa yang mendapatkan surat izin keluar kelas atau pulang cepat hari ini.',
                'template' => "🎫 <b>PERIZINAN SISWA KELUAR/PULANG HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_izin_keluar}\n━━━━━━━━━━━━━━━━━━━━\n<i>Verifikasi surat perizinan resmi meja piket {nama_sekolah}</i>",
                'default_template' => "🎫 <b>PERIZINAN SISWA KELUAR/PULANG HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_izin_keluar}\n━━━━━━━━━━━━━━━━━━━━\n<i>Verifikasi surat perizinan resmi meja piket {nama_sekolah}</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_izin_keluar' => 'Daftar rincian siswa yang izin keluar atau pulang cepat',
                    'total_izin_keluar' => 'Total surat izin keluar yang diterbitkan',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'serah_terima',
                'name' => 'Ringkasan Serah Terima Jurnal Piket',
                'category' => 'piket',
                'command' => '/serah_terima',
                'description' => 'Balasan statistik serah terima meja piket saat pergantian jam atau penutupan jurnal harian.',
                'template' => "📑 <b>RINGKASAN SERAH TERIMA JURNAL PIKET</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n👥 <b>Petugas:</b> {petugas_piket}\n\n📊 <b>Statistik Operasional Hari Ini:</b>\n• ⏰ Siswa Terlambat: <b>{total_terlambat}</b> anak\n• 🎫 Perizinan Siswa: <b>{total_izin}</b> berkas\n• 🚨 Kelas Inval / Pengganti: <b>{total_inval}</b> kelas\n• 🤝 Buku Tamu Sekolah: <b>{total_tamu}</b> kunjungan\n• ⚠️ Kejadian / Insiden: <b>{total_kasus}</b> kasus\n━━━━━━━━━━━━━━━━━━━━\nStatus: <b>Jurnal Siap Ditutup & Diserahkan</b> ke Waka/Pimpinan.",
                'default_template' => "📑 <b>RINGKASAN SERAH TERIMA JURNAL PIKET</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n👥 <b>Petugas:</b> {petugas_piket}\n\n📊 <b>Statistik Operasional Hari Ini:</b>\n• ⏰ Siswa Terlambat: <b>{total_terlambat}</b> anak\n• 🎫 Perizinan Siswa: <b>{total_izin}</b> berkas\n• 🚨 Kelas Inval / Pengganti: <b>{total_inval}</b> kelas\n• 🤝 Buku Tamu Sekolah: <b>{total_tamu}</b> kunjungan\n• ⚠️ Kejadian / Insiden: <b>{total_kasus}</b> kasus\n━━━━━━━━━━━━━━━━━━━━\nStatus: <b>Jurnal Siap Ditutup & Diserahkan</b> ke Waka/Pimpinan.",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'petugas_piket' => 'Nama-nama petugas piket hari ini',
                    'total_terlambat' => 'Jumlah siswa terlambat hari ini',
                    'total_izin' => 'Jumlah perizinan siswa hari ini',
                    'total_inval' => 'Jumlah kelas pengganti (inval) hari ini',
                    'total_tamu' => 'Jumlah tamu berkunjung hari ini',
                    'total_kasus' => 'Jumlah insiden/kejadian hari ini',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'terlambat',
                'name' => 'Daftar Siswa Terlambat Hari Ini',
                'category' => 'disiplin',
                'command' => '/terlambat',
                'description' => 'Balasan daftar siswa yang datang terlambat masuk gerbang sekolah hari ini.',
                'template' => "⏰ <b>REKAP KETERLAMBATAN SISWA HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_terlambat}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data real-time dari Meja Piket & Presensi {nama_sekolah}</i>",
                'default_template' => "⏰ <b>REKAP KETERLAMBATAN SISWA HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_terlambat}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data real-time dari Meja Piket & Presensi {nama_sekolah}</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_terlambat' => 'Rincian nama siswa, kelas, waktu tiba, dan alasan terlambat',
                    'total_terlambat' => 'Jumlah siswa terlambat hari ini',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'bolos',
                'name' => 'Pantauan Siswa Terindikasi Alpa / Bolos',
                'category' => 'disiplin',
                'command' => '/bolos',
                'description' => 'Balasan rekap siswa yang tidak hadir tanpa izin pada jam pelajaran KBM hari ini.',
                'template' => "🚨 <b>REKAP SISWA ALPA / BOLOS KBM HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_bolos}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data bersumber dari Jurnal Presensi KBM Guru Mapel {nama_sekolah}</i>",
                'default_template' => "🚨 <b>REKAP SISWA ALPA / BOLOS KBM HARI INI</b>\n📅 <b>Hari/Tgl:</b> {tanggal}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_bolos}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data bersumber dari Jurnal Presensi KBM Guru Mapel {nama_sekolah}</i>",
                'available_placeholders' => [
                    'tanggal' => 'Hari dan tanggal hari ini',
                    'daftar_bolos' => 'Daftar nama siswa, kelas, dan mapel dengan status alpa/bolos',
                    'total_bolos' => 'Jumlah siswa terdata alpa/bolos',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'top_kasus',
                'name' => 'Top Statistik Pelanggaran Siswa',
                'category' => 'disiplin',
                'command' => '/top_kasus',
                'description' => 'Balasan statistik tren 5 kategori pelanggaran siswa teratas bulan ini.',
                'template' => "📊 <b>TOP 5 KASUS & PELANGGARAN SISWA</b>\n🗓️ <b>Periode:</b> {periode}\n━━━━━━━━━━━━━━━━━━━━\nTotal Insiden Tercatat: <b>{total_kasus} kasus</b>\n\n{daftar_top_kasus}\n━━━━━━━━━━━━━━━━━━━━\n<i>Laporan evaluasi untuk Tim Kedisiplinan, BK, dan Wali Kelas</i>",
                'default_template' => "📊 <b>TOP 5 KASUS & PELANGGARAN SISWA</b>\n🗓️ <b>Periode:</b> {periode}\n━━━━━━━━━━━━━━━━━━━━\nTotal Insiden Tercatat: <b>{total_kasus} kasus</b>\n\n{daftar_top_kasus}\n━━━━━━━━━━━━━━━━━━━━\n<i>Laporan evaluasi untuk Tim Kedisiplinan, BK, dan Wali Kelas</i>",
                'available_placeholders' => [
                    'periode' => 'Bulan dan tahun periode evaluasi',
                    'total_kasus' => 'Total insiden pelanggaran siswa tercatat',
                    'daftar_top_kasus' => 'Grafik batang teks dan jumlah kejadian per kategori',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'kaldik',
                'name' => 'Kalender Pendidikan & Agenda Bulanan',
                'category' => 'akademik',
                'command' => '/kaldik',
                'description' => 'Balasan kalender pendidikan dan agenda khusus sekolah pada bulan berjalan.',
                'template' => "🗓️ <b>AGENDA & KALENDER PENDIDIKAN</b>\n📆 <b>Bulan:</b> {bulan}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_kaldik}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data bersumber dari Kalender Pendidikan Resmi {nama_sekolah}</i>",
                'default_template' => "🗓️ <b>AGENDA & KALENDER PENDIDIKAN</b>\n📆 <b>Bulan:</b> {bulan}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_kaldik}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data bersumber dari Kalender Pendidikan Resmi {nama_sekolah}</i>",
                'available_placeholders' => [
                    'bulan' => 'Bulan dan tahun berjalan',
                    'daftar_kaldik' => 'Rincian kegiatan, rentang tanggal, dan hari libur',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'asesmen',
                'name' => 'Jadwal Asesmen & Ujian Mendatang',
                'category' => 'akademik',
                'command' => '/asesmen',
                'description' => 'Balasan daftar agenda asesmen sumatif, PTS, PAS, PAT, atau UKK yang akan datang.',
                'template' => "📝 <b>JADWAL ASESMEN & UJIAN MENDATANG</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_asesmen}\n━━━━━━━━━━━━━━━━━━━━\n<i>Persiapkan administrasi dan perangkat pembelajaran dengan baik</i>",
                'default_template' => "📝 <b>JADWAL ASESMEN & UJIAN MENDATANG</b>\n━━━━━━━━━━━━━━━━━━━━\n{daftar_asesmen}\n━━━━━━━━━━━━━━━━━━━━\n<i>Persiapkan administrasi dan perangkat pembelajaran dengan baik</i>",
                'available_placeholders' => [
                    'daftar_asesmen' => 'Rincian nama asesmen/ujian, rentang tanggal, dan hitung mundur hari',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'supervisi',
                'name' => 'Jadwal Supervisi Pembelajaran Guru',
                'category' => 'akademik',
                'command' => '/supervisi',
                'description' => 'Balasan agenda pelaksanaan supervisi dan observasi pembelajaran guru.',
                'template' => "📋 <b>JADWAL SUPERVISI PEMBELAJARAN GURU</b>\n🗓️ <b>Rentang:</b> {rentang}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_supervisi}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data resmi dari Modul Supervisi Akademik {nama_sekolah}</i>",
                'default_template' => "📋 <b>JADWAL SUPERVISI PEMBELAJARAN GURU</b>\n🗓️ <b>Rentang:</b> {rentang}\n━━━━━━━━━━━━━━━━━━━━\n{daftar_supervisi}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data resmi dari Modul Supervisi Akademik {nama_sekolah}</i>",
                'available_placeholders' => [
                    'rentang' => 'Keterangan pekan supervisi (Pekan Berjalan / Mendatang)',
                    'daftar_supervisi' => 'Daftar guru yang diobservasi, supervisor, mapel, kelas, dan status',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'id',
                'name' => 'Cek Chat ID & Informasi Akun',
                'category' => 'umum',
                'command' => '/id',
                'description' => 'Balasan nomor Chat ID Telegram dan panduan menghubungkan ke akun SIAP.',
                'template' => "👤 <b>INFORMASI AKUN PRIBADI TELEGRAM ANDA</b>\n━━━━━━━━━━━━━━━━━━━━\nNama: <b>{nama_pengirim}</b>\n🆔 <b>User ID Telegram Pribadi:</b> <code>{chat_id}</code>\nStatus: {status_akun}\n━━━━━━━━━━━━━━━━━━━━\n<b>Cara Menghubungkan Akun Guru:</b>\n1. <b>Otomatis lewat Bot:</b> Ketik perintah:\n   <code>/hubungkan [NIP atau Username Anda]</code>\n   <i>Contoh: <code>/hubungkan 19850101...</code> atau <code>/hubungkan ahmad.soleh</code></i>\n\n2. <b>Lewat Web SIAP:</b>\n   Login ke web &rarr; Menu <b>Profil Saya</b> &rarr; masukkan Chat ID <code>{chat_id}</code> pada kolom Telegram Chat ID.",
                'default_template' => "👤 <b>INFORMASI AKUN PRIBADI TELEGRAM ANDA</b>\n━━━━━━━━━━━━━━━━━━━━\nNama: <b>{nama_pengirim}</b>\n🆔 <b>User ID Telegram Pribadi:</b> <code>{chat_id}</code>\nStatus: {status_akun}\n━━━━━━━━━━━━━━━━━━━━\n<b>Cara Menghubungkan Akun Guru:</b>\n1. <b>Otomatis lewat Bot:</b> Ketik perintah:\n   <code>/hubungkan [NIP atau Username Anda]</code>\n   <i>Contoh: <code>/hubungkan 19850101...</code> atau <code>/hubungkan ahmad.soleh</code></i>\n\n2. <b>Lewat Web SIAP:</b>\n   Login ke web &rarr; Menu <b>Profil Saya</b> &rarr; masukkan Chat ID <code>{chat_id}</code> pada kolom Telegram Chat ID.",
                'available_placeholders' => [
                    'nama_pengirim' => 'Nama profil pengirim',
                    'chat_id' => 'Nomor unik Telegram Chat ID',
                    'status_akun' => 'Status keterhubungan dengan akun GTK di sistem',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
            [
                'code' => 'poin',
                'name' => 'Rekam Jejak Kedisiplinan Siswa',
                'category' => 'disiplin',
                'command' => '/poin',
                'description' => 'Balasan rekam jejak pelanggaran dan akumulasi keterlambatan siswa tertentu.',
                'template' => "👤 <b>REKAM JEJAK KEDISIPLINAN SISWA</b>\n━━━━━━━━━━━━━━━━━━━━\n• <b>Nama:</b> {nama_siswa}\n• <b>Kelas:</b> {kelas}\n• <b>NIS:</b> {nis}\n• <b>Tahun Ajaran:</b> {tahun_ajaran}\n\n📊 <b>Ringkasan Pelanggaran:</b>\n• Kasus Kejadian Piket: <b>{total_kasus} kali</b>\n• Keterlambatan Hadir: <b>{total_terlambat} kali</b>\n\n{riwayat_kasus}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data terhubung dengan Modul Meja Piket, Tim Kedisiplinan, & Guru BK {nama_sekolah}.</i>",
                'default_template' => "👤 <b>REKAM JEJAK KEDISIPLINAN SISWA</b>\n━━━━━━━━━━━━━━━━━━━━\n• <b>Nama:</b> {nama_siswa}\n• <b>Kelas:</b> {kelas}\n• <b>NIS:</b> {nis}\n• <b>Tahun Ajaran:</b> {tahun_ajaran}\n\n📊 <b>Ringkasan Pelanggaran:</b>\n• Kasus Kejadian Piket: <b>{total_kasus} kali</b>\n• Keterlambatan Hadir: <b>{total_terlambat} kali</b>\n\n{riwayat_kasus}\n━━━━━━━━━━━━━━━━━━━━\n<i>Data terhubung dengan Modul Meja Piket, Tim Kedisiplinan, & Guru BK {nama_sekolah}.</i>",
                'available_placeholders' => [
                    'nama_siswa' => 'Nama lengkap siswa',
                    'kelas' => 'Nama rombel kelas siswa',
                    'nis' => 'Nomor Induk Siswa',
                    'tahun_ajaran' => 'Tahun ajaran operasional',
                    'total_kasus' => 'Jumlah kasus kejadian siswa',
                    'total_terlambat' => 'Jumlah keterlambatan siswa',
                    'riwayat_kasus' => 'Daftar riwayat 3 kasus kejadian terakhir siswa',
                    'nama_sekolah' => 'Nama resmi sekolah',
                ],
                'is_active' => true,
            ],
        ];
    }
}
