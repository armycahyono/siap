<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Master Data
            ['slug' => 'manage_users', 'name' => 'Kelola Data Pengguna', 'group' => 'Pengguna & Akses'],
            ['slug' => 'manage_roles', 'name' => 'Kelola Hak Akses & Peran', 'group' => 'Pengguna & Akses'],
            ['slug' => 'manage_teachers', 'name' => 'Kelola Data Guru', 'group' => 'Master Data'],
            ['slug' => 'manage_master_data', 'name' => 'Kelola Data Pokok (Mapel, Jurusan, Kelas, Penugasan)', 'group' => 'Master Data'],
            ['slug' => 'manage_rubrics', 'name' => 'Kelola Indikator & Rubrik', 'group' => 'Master Data'],
            ['slug' => 'manage_settings', 'name' => 'Kelola Pengaturan Sekolah', 'group' => 'Pengaturan'],

            // Supervisi Operasional
            ['slug' => 'manage_schedules', 'name' => 'Kelola Jadwal Supervisi', 'group' => 'Supervisi'],
            ['slug' => 'conduct_supervision', 'name' => 'Melakukan Penilaian Supervisi', 'group' => 'Supervisi'],
            ['slug' => 'upload_rpp', 'name' => 'Unggah RPP / Modul Ajar', 'group' => 'Supervisi'],
            ['slug' => 'view_own_supervision', 'name' => 'Lihat Hasil Supervisi Sendiri', 'group' => 'Supervisi'],
            ['slug' => 'view_all_supervision', 'name' => 'Lihat Semua Supervisi Sekolah', 'group' => 'Supervisi'],
            ['slug' => 'approve_supervision', 'name' => 'Menyetujui & Pengesahan Supervisi', 'group' => 'Supervisi'],
            ['slug' => 'export_reports', 'name' => 'Cetak & Ekspor Laporan (PDF/Excel)', 'group' => 'Laporan'],

            // Piket Operasional
            ['slug' => 'access_piket', 'name' => 'Akses Menu & Jadwal Piket', 'group' => 'Piket'],
            ['slug' => 'operate_piket', 'name' => 'Operasional Meja Piket (Jurnal, Izin, Inval, Tamu)', 'group' => 'Piket'],
            ['slug' => 'manage_piket_cases', 'name' => 'Kelola Catatan Kasus & Kejadian Siswa', 'group' => 'Piket'],
            ['slug' => 'manage_piket_schedules', 'name' => 'Kelola Master Jadwal & Persetujuan Piket', 'group' => 'Piket'],
            ['slug' => 'view_piket_reports', 'name' => 'Rekapitulasi & Ekspor Laporan Piket', 'group' => 'Piket'],
            ['slug' => 'manage_piket_settings', 'name' => 'Pengaturan Bot Telegram Piket', 'group' => 'Piket'],

            // Bidang Operasional Waka
            ['slug' => 'operate_curriculum', 'name' => 'Operasional Kurikulum & Jadwal', 'group' => 'Kurikulum'],
            ['slug' => 'operate_cbt', 'name' => 'Sinkronisasi & Operasional CBT', 'group' => 'Kurikulum'],
            ['slug' => 'operate_student_affairs', 'name' => 'Operasional Kesiswaan & Kedisiplinan', 'group' => 'Kesiswaan'],
            ['slug' => 'manage_discipline', 'name' => 'Kelola Disiplin & Penanganan Pelanggaran', 'group' => 'Kesiswaan'],
            ['slug' => 'access_bk', 'name' => 'Akses Operasional Bimbingan Konseling', 'group' => 'BK'],
            ['slug' => 'operate_sarpras', 'name' => 'Operasional Sarana & Prasarana', 'group' => 'Sarpras'],
            ['slug' => 'operate_humas', 'name' => 'Operasional Humas & Kemitraan DUDI', 'group' => 'Humas'],
            ['slug' => 'operate_library', 'name' => 'Operasional Perpustakaan Sekolah', 'group' => 'Perpustakaan'],
            ['slug' => 'operate_finance', 'name' => 'Operasional Keuangan & Bendahara', 'group' => 'Keuangan'],
            ['slug' => 'operate_tu', 'name' => 'Operasional Tata Usaha & Kepegawaian', 'group' => 'Tata Usaha'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        $rolesData = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Akses penuh ke seluruh sistem dan konfigurasi aplikasi',
                'permissions' => array_column($permissions, 'slug'),
            ],
            [
                'name' => 'Waka Kurikulum',
                'slug' => 'waka_kurikulum',
                'description' => 'Pengelola operasional supervisi, jadwal, guru, dan rekapitulasi sekolah',
                'permissions' => [
                    'manage_teachers',
                    'manage_master_data',
                    'manage_rubrics',
                    'manage_schedules',
                    'conduct_supervision',
                    'view_all_supervision',
                    'approve_supervision',
                    'export_reports',
                    'manage_settings',
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'manage_piket_schedules',
                    'view_piket_reports',
                    'manage_piket_settings',
                ],
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'description' => 'Menilai guru yang ditugaskan (praobservasi, observasi, dokumentasi, catatan)',
                'permissions' => [
                    'conduct_supervision',
                    'upload_rpp',
                    'view_own_supervision',
                    'export_reports',
                    'access_piket',
                ],
            ],
            [
                'name' => 'Guru',
                'slug' => 'guru',
                'description' => 'Melihat jadwal, mengunggah RPP/Modul Ajar, dan melihat hasil supervisi sendiri',
                'permissions' => [
                    'upload_rpp',
                    'view_own_supervision',
                    'export_reports',
                    'access_piket',
                ],
            ],
            [
                'name' => 'Guru BK',
                'slug' => 'guru_bk',
                'description' => 'Guru Bimbingan dan Konseling siswa',
                'permissions' => [
                    'access_piket',
                    'access_bk',
                ],
            ],
            [
                'name' => 'Kepala Sekolah',
                'slug' => 'kepala_sekolah',
                'description' => 'Monitoring rekapitulasi sekolah dan persetujuan pengesahan supervisi',
                'permissions' => [
                    'view_all_supervision',
                    'approve_supervision',
                    'export_reports',
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'manage_piket_schedules',
                    'view_piket_reports',
                ],
            ],
            [
                'name' => 'Operator Kurikulum',
                'slug' => 'operator_kurikulum',
                'description' => 'Membantu operasional kurikulum, jadwal pelajaran, kalender kegiatan, dan integrasi CBT',
                'permissions' => [
                    'manage_master_data',
                    'manage_schedules',
                    'upload_rpp',
                    'view_own_supervision',
                    'export_reports',
                    'operate_curriculum',
                    'operate_cbt',
                ],
            ],
            [
                'name' => 'Tim Kedisiplinan',
                'slug' => 'tim_kedisiplinan',
                'description' => 'Tim Penegak Disiplin, Tata Tertib, dan Verifikasi Pelanggaran Siswa',
                'permissions' => [
                    'access_piket',
                    'manage_piket_cases',
                    'view_piket_reports',
                    'operate_student_affairs',
                    'manage_discipline',
                ],
            ],
            [
                'name' => 'Koordinator Kedisiplinan',
                'slug' => 'koordinator_kedisiplinan',
                'description' => 'Koordinator Tim Penegak Disiplin dan Tata Tertib Siswa',
                'permissions' => [
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'view_piket_reports',
                    'operate_student_affairs',
                    'manage_discipline',
                ],
            ],
            [
                'name' => 'Operator Kesiswaan',
                'slug' => 'operator_kesiswaan',
                'description' => 'Membantu operasional kesiswaan, catatan kasus & kejadian siswa, dan kedisiplinan',
                'permissions' => [
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'view_piket_reports',
                    'operate_student_affairs',
                    'manage_discipline',
                ],
            ],
            [
                'name' => 'Operator Sarpras',
                'slug' => 'operator_sarpras',
                'description' => 'Membantu operasional sarana prasarana, inventaris alat & ruang kelas/lab',
                'permissions' => [
                    'export_reports',
                    'operate_sarpras',
                ],
            ],
            [
                'name' => 'Operator Humas',
                'slug' => 'operator_humas',
                'description' => 'Membantu operasional kemitraan industri (DUDI), penempatan PKL/Prakerin, dan tracer study',
                'permissions' => [
                    'export_reports',
                    'operate_humas',
                ],
            ],
            [
                'name' => 'Waka Kesiswaan',
                'slug' => 'waka_kesiswaan',
                'description' => 'Pengelola bidang kesiswaan, pembinaan karakter, kedisiplinan, dan tata tertib siswa',
                'permissions' => [
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'manage_piket_schedules',
                    'view_piket_reports',
                    'operate_student_affairs',
                    'manage_discipline',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Waka Sarpras',
                'slug' => 'waka_sarpras',
                'description' => 'Pengelola sarana dan prasarana sekolah, pemeliharaan aset dan fasilitas',
                'permissions' => [
                    'manage_master_data',
                    'operate_sarpras',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Waka Humas',
                'slug' => 'waka_humas',
                'description' => 'Pengelola hubungan masyarakat, kemitraan dunia usaha/industri, dan bursa kerja',
                'permissions' => [
                    'operate_humas',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Wali Kelas',
                'slug' => 'wali_kelas',
                'description' => 'Wali kelas pembina rombongan belajar dan pemantau perkembangan siswa',
                'permissions' => [
                    'access_piket',
                    'view_piket_reports',
                ],
            ],
            [
                'name' => 'Petugas Piket',
                'slug' => 'guru_piket',
                'description' => 'Petugas operasional meja piket harian sekolah',
                'permissions' => [
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'view_piket_reports',
                ],
            ],
            [
                'name' => 'Kepala Tata Usaha',
                'slug' => 'kepala_tu',
                'description' => 'Koordinator ketatausahaan, administrasi kepegawaian, dan persuratan sekolah',
                'permissions' => [
                    'manage_master_data',
                    'operate_tu',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Petugas Perpustakaan',
                'slug' => 'petugas_perpustakaan',
                'description' => 'Pengelola layanan dan sirkulasi perpustakaan sekolah',
                'permissions' => [
                    'operate_library',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Laboran / Toolman',
                'slug' => 'laboran_toolman',
                'description' => 'Teknisi laboratorium dan toolman bengkel kejuruan',
                'permissions' => [
                    'operate_sarpras',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Bendahara',
                'slug' => 'bendahara',
                'description' => 'Pengelola administrasi keuangan sekolah',
                'permissions' => [
                    'operate_finance',
                    'export_reports',
                ],
            ],
            [
                'name' => 'Staf / Tendik',
                'slug' => 'staf',
                'description' => 'Tenaga kependidikan umum / staf pendukung sekolah',
                'permissions' => [],
            ],
            [
                'name' => 'Operator Dapodik / TU',
                'slug' => 'operator',
                'description' => 'Membantu operasional administrasi sekolah, pendataan pokok GTK dan siswa',
                'permissions' => [
                    'manage_master_data',
                    'export_reports',
                ],
            ],
        ];

        foreach ($rolesData as $r) {
            $role = Role::firstOrCreate(
                ['slug' => $r['slug']],
                ['name' => $r['name'], 'description' => $r['description']]
            );

            $permIds = Permission::whereIn('slug', $r['permissions'])->pluck('id')->toArray();
            $role->permissions()->sync($permIds);
        }
    }
}
