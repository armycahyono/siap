<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $years = [
            [
                'name' => '2024/2025',
                'semester' => 'Genap',
                'start_date' => '2024-07-15',
                'end_date' => '2025-06-20',
                'is_active' => false,
                'is_locked' => true,
                'description' => 'Arsip Tahun Pelajaran 2024/2025 (Terkunci)',
            ],
            [
                'name' => '2025/2026',
                'semester' => 'Genap',
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-19',
                'is_active' => false,
                'is_locked' => true,
                'description' => 'Arsip Tahun Pelajaran 2025/2026 (Terkunci)',
            ],
            [
                'name' => '2026/2027',
                'semester' => 'Ganjil',
                'start_date' => '2026-07-13',
                'end_date' => '2027-06-25',
                'is_active' => true,
                'is_locked' => false,
                'description' => 'Tahun Pelajaran Berjalan (Aktif Operasional)',
            ],
            [
                'name' => '2027/2028',
                'semester' => 'Ganjil',
                'start_date' => '2027-07-12',
                'end_date' => '2028-06-23',
                'is_active' => false,
                'is_locked' => false,
                'description' => 'Perencanaan Tahun Pelajaran Mendatang',
            ],
        ];

        foreach ($years as $item) {
            AcademicYear::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
