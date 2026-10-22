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
