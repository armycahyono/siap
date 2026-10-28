<?php

use App\Models\StructuralPosition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Reclassify technical & support positions to operational
        StructuralPosition::whereIn('code', [
            'STAF_TU',
            'SATPAM',
            'PENJAGA',
            'TOOLMAN_TKJ',
            'TOOLMAN_TKR',
            'KEBUN',
        ])->update(['type' => StructuralPosition::TYPE_OPERASIONAL]);

        // 2. Ensure standard operational GTK positions exist
        $standardOperational = [
            [
                'code' => 'TIM_KEDISIPLINAN',
                'name' => 'Tim Kedisiplinan & Tata Tertib',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Kesiswaan & Disiplin',
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'scope_type' => 'school',
                'description' => 'Penegakan tata tertib siswa, verifikasi pelanggaran kedisiplinan, dan koordinasi pembinaan.',
                'order' => 21,
                'is_active' => true,
            ],
            [
                'code' => 'OPERATOR',
                'name' => 'Operator Sekolah / Dapodik',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Layanan & Sistem',
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'scope_type' => 'school',
                'description' => 'Pengelolaan data pokok pendidikan (Dapodik), verval data GTK/siswa, dan sinkronisasi sistem.',
                'order' => 22,
                'is_active' => true,
            ],
            [
                'code' => 'PUSTAKAWAN',
                'name' => 'Pustakawan / Staf Perpustakaan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Unit & Fasilitas',
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'scope_type' => 'school',
                'description' => 'Pengelolaan sirkulasi buku, katalog perpustakaan, dan layanan literasi sekolah.',
                'order' => 23,
                'is_active' => true,
            ],
            [
                'code' => 'LABORAN',
                'name' => 'Laboran Praktik / Komputer',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Unit & Fasilitas',
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'scope_type' => 'school',
                'description' => 'Pemeliharaan perangkat laboratorium komputer/praktik dan pendampingan praktikum.',
                'order' => 24,
                'is_active' => true,
            ],
            [
                'code' => 'PETUGAS_KEBERSIHAN',
                'name' => 'Petugas Kebersihan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'category' => 'Layanan & Lingkungan',
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'scope_type' => 'school',
                'description' => 'Pemeliharaan kebersihan lingkungan sekolah, ruang kelas, dan fasilitas umum.',
                'order' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($standardOperational as $pos) {
            StructuralPosition::firstOrCreate(
                ['code' => $pos['code']],
                $pos
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
