<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\StructuralPosition;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Hanya menyiapkan struktur kejuruan, tingkat kelas, daftar mapel standar,
     * dan struktur jabatan organisasi. Tanpa data guru atau penugasan mengajar hardcoded.
     */
    public function run(): void
    {
        // 1. JURUSAN (Departments)
        $departments = [
            [
                'code' => 'TKR',
                'name' => 'Teknik Kendaraan Ringan Otomotif',
                'description' => 'Program keahlian teknik otomotif perawatan dan perbaikan kendaraan ringan.',
            ],
            [
                'code' => 'TKJ',
                'name' => 'Teknik Komputer dan Jaringan',
                'description' => 'Program keahlian perakitan komputer, instalasi jaringan LAN/WAN, administrasi server dan cloud.',
            ],
            [
                'code' => 'AKL',
                'name' => 'Akuntansi dan Keuangan Lembaga',
                'description' => 'Program keahlian pencatatan keuangan, spreadsheet akuntansi, perpajakan, dan perbankan.',
            ],
        ];

        $deptMap = [];
        foreach ($departments as $d) {
            $dept = Department::firstOrCreate(['code' => $d['code']], $d);
            $deptMap[$d['code']] = $dept;
        }

        // 2. TINGKAT (Grade Levels)
        $grades = [
            ['level' => 'X', 'name' => 'Tingkat X (Sepuluh)', 'order_index' => 1],
            ['level' => 'XI', 'name' => 'Tingkat XI (Sebelas)', 'order_index' => 2],
            ['level' => 'XII', 'name' => 'Tingkat XII (Dua Belas)', 'order_index' => 3],
        ];

        foreach ($grades as $g) {
            GradeLevel::firstOrCreate(['level' => $g['level']], $g);
        }

        // 3. MATA PELAJARAN (Subjects)
        $subjects = [
            // Muatan Umum
            ['code' => 'PAI', 'name' => 'Pendidikan Agama Islam & Budi Pekerti', 'category' => 'Umum', 'dept' => null, 'order' => 1],
            ['code' => 'PPKN', 'name' => 'Pendidikan Pancasila dan Kewarganegaraan', 'category' => 'Umum', 'dept' => null, 'order' => 2],
            ['code' => 'BIND', 'name' => 'Bahasa Indonesia', 'category' => 'Umum', 'dept' => null, 'order' => 3],
            ['code' => 'MTK', 'name' => 'Matematika', 'category' => 'Umum', 'dept' => null, 'order' => 4],
            ['code' => 'SEJ', 'name' => 'Sejarah Indonesia', 'category' => 'Umum', 'dept' => null, 'order' => 5],
            ['code' => 'BING', 'name' => 'Bahasa Inggris', 'category' => 'Umum', 'dept' => null, 'order' => 6],
            ['code' => 'PJOK', 'name' => 'Pendidikan Jasmani, Olahraga & Kesehatan', 'category' => 'Umum', 'dept' => null, 'order' => 7],
            ['code' => 'BJAW', 'name' => 'Bahasa Jawa (Muatan Lokal)', 'category' => 'Muatan Lokal', 'dept' => null, 'order' => 8],
            ['code' => 'INF', 'name' => 'Informatika', 'category' => 'Umum', 'dept' => null, 'order' => 9],
            ['code' => 'IPAS', 'name' => 'Projek Ilmu Pengetahuan Alam dan Sosial (IPAS)', 'category' => 'Umum', 'dept' => null, 'order' => 10],

            // Kejuruan TKR
            ['code' => 'TKR-PMKR', 'name' => 'Pemeliharaan Mesin Kendaraan Ringan (PMKR)', 'category' => 'Kejuruan', 'dept' => 'TKR', 'order' => 11],
            ['code' => 'TKR-PKKR', 'name' => 'Pemeliharaan Kelistrikan Kendaraan Ringan (PKKR)', 'category' => 'Kejuruan', 'dept' => 'TKR', 'order' => 12],
            ['code' => 'TKR-PSPT', 'name' => 'Pemeliharaan Sasis & Pemindah Tenaga (PSPT)', 'category' => 'Kejuruan', 'dept' => 'TKR', 'order' => 13],
            ['code' => 'TKR-PKK', 'name' => 'Produk Kreatif & Kewirausahaan (TKR)', 'category' => 'Kejuruan', 'dept' => 'TKR', 'order' => 14],

            // Kejuruan TKJ
            ['code' => 'TKJ-AIJ', 'name' => 'Administrasi Infrastruktur Jaringan (AIJ)', 'category' => 'Kejuruan', 'dept' => 'TKJ', 'order' => 15],
            ['code' => 'TKJ-ASJ', 'name' => 'Administrasi Sistem Jaringan (ASJ)', 'category' => 'Kejuruan', 'dept' => 'TKJ', 'order' => 16],
            ['code' => 'TKJ-TLJ', 'name' => 'Teknologi Layanan Jaringan (TLJ)', 'category' => 'Kejuruan', 'dept' => 'TKJ', 'order' => 17],
            ['code' => 'TKJ-PKK', 'name' => 'Produk Kreatif & Kewirausahaan (TKJ)', 'category' => 'Kejuruan', 'dept' => 'TKJ', 'order' => 18],

            // Kejuruan AKL
            ['code' => 'AKL-PRAK', 'name' => 'Praktik Akuntansi Perusahaan Jasa, Dagang & Manufaktur', 'category' => 'Kejuruan', 'dept' => 'AKL', 'order' => 19],
            ['code' => 'AKL-KOMPAK', 'name' => 'Komputer Akuntansi (Spreadsheet & MYOB)', 'category' => 'Kejuruan', 'dept' => 'AKL', 'order' => 20],
            ['code' => 'AKL-PAJAK', 'name' => 'Administrasi Pajak & Keuangan', 'category' => 'Kejuruan', 'dept' => 'AKL', 'order' => 21],
            ['code' => 'AKL-PKK', 'name' => 'Produk Kreatif & Kewirausahaan (AKL)', 'category' => 'Kejuruan', 'dept' => 'AKL', 'order' => 22],
        ];

        foreach ($subjects as $s) {
            Subject::firstOrCreate(
                ['code' => $s['code']],
                [
                    'name' => $s['name'],
                    'category' => $s['category'],
                    'department_id' => $s['dept'] ? ($deptMap[$s['dept']]->id ?? null) : null,
                    'order_index' => $s['order'],
                    'is_active' => true,
                ]
            );
        }

        // 4. JABATAN STRUKTURAL (Structural Positions)
        $structuralPositions = [
            ['name' => 'Kepala Sekolah', 'code' => 'KS', 'category' => 'Pimpinan', 'order' => 1],
            ['name' => 'Waka Kurikulum', 'code' => 'WAKA_KUR', 'category' => 'Wakil Kepala Sekolah', 'order' => 2],
            ['name' => 'Waka Sarpras', 'code' => 'WAKA_SARPRAS', 'category' => 'Wakil Kepala Sekolah', 'order' => 3],
            ['name' => 'Waka Kesiswaan', 'code' => 'WAKA_KESISWAAN', 'category' => 'Wakil Kepala Sekolah', 'order' => 4],
            ['name' => 'Waka Humas', 'code' => 'WAKA_HUMAS', 'category' => 'Wakil Kepala Sekolah', 'order' => 5],
            ['name' => 'Bendahara Sekolah', 'code' => 'BENDAHARA', 'category' => 'Tata Kelola & Keuangan', 'order' => 6],
            ['name' => 'Kaprogli TKR', 'code' => 'KAPRO_TKR', 'category' => 'Kepala Program Keahlian', 'order' => 7],
            ['name' => 'Kaprogli TKJ', 'code' => 'KAPRO_TKJ', 'category' => 'Kepala Program Keahlian', 'order' => 8],
            ['name' => 'Kaprogli AKL', 'code' => 'KAPRO_AKL', 'category' => 'Kepala Program Keahlian', 'order' => 9],
            ['name' => 'Ka. Perpus', 'code' => 'KA_PERPUS', 'category' => 'Kepala Unit & Fasilitas', 'order' => 10],
            ['name' => 'Ka. Bengkel', 'code' => 'KA_BENGKEL', 'category' => 'Kepala Unit & Fasilitas', 'order' => 11],
            ['name' => 'Ka. Lab Komputer', 'code' => 'KA_LAB_KOMP', 'category' => 'Kepala Unit & Fasilitas', 'order' => 12],
            ['name' => 'Ka. TU', 'code' => 'KA_TU', 'category' => 'Tata Kelola & Keuangan', 'order' => 13],
        ];

        foreach ($structuralPositions as $sp) {
            StructuralPosition::firstOrCreate(
                ['code' => $sp['code']],
                [
                    'name' => $sp['name'],
                    'category' => $sp['category'],
                    'order' => $sp['order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
