<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class SchoolSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolSetting::firstOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'school_npsn' => '20534431',
                'school_address' => 'Jl. Raya Kras No. 12, Kec. Kras, Kab. Kediri, Jawa Timur',
                'school_phone' => '(0354) 771234',
                'school_email' => 'smkpgrikras@gmail.com',
                'school_website' => 'https://smkpgrikras.sch.id',
                'principal_name' => 'Endang Patmowati, S.Pd.',
                'principal_nip' => null,
                'curriculum_head_name' => null,
                'curriculum_head_nip' => null,
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'grade_a_min' => 86,
                'grade_b_min' => 76,
                'grade_c_min' => 61,
            ]
        );
    }
}
