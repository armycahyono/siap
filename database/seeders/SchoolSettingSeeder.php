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
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'school_npsn' => '20534431',
                'school_address' => 'Jl. Raya Kras No. 12, Kec. Kras, Kab. Kediri, Jawa Timur',
                'school_phone' => '(0354) 771234',
                'school_email' => 'smkpgrikras@gmail.com',
                'school_website' => 'https://smkpgrikras.sch.id',
                'principal_name' => 'Endang Patmowati, S.Pd.',
                'principal_nip' => '19750812 200501 2 008',
                'curriculum_head_name' => 'Budi Santoso, S.Pd.',
                'curriculum_head_nip' => '19820315 200801 1 012',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'grade_a_min' => 86,
                'grade_b_min' => 76,
                'grade_c_min' => 61,
            ]
        );
    }
}
