<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
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
            SupervisionSampleSeeder::class,
            SchoolActivitySeeder::class,
            SchoolStakeholderSeeder::class,
        ]);
    }
}
