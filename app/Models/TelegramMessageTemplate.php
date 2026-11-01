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
        ];
    }
}
