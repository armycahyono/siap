<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Hanya memasukkan fondasi sistem:
     * - Role & Permission
     * - Tahun Ajaran Aktif
     * - 23 Butir Rubrik Supervisi
     * - Pengaturan Dasar Sekolah
     * - 1 Akun Super Admin Sistem
     * - Master Kejuruan, Jenjang & Mata Pelajaran Standar
     *
     * Tanpa data dummy / sampel: Guru (0), Siswa (0), Supervisi (0), Piket (0), dsb.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AcademicYearSeeder::class,
            SupervisionRubricSeeder::class,
            SchoolSettingSeeder::class,
            UserSeeder::class,
            MasterDataSeeder::class,
        ]);
    }
}
