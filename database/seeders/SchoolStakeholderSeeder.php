<?php

namespace Database\Seeders;

use App\Models\SchoolStakeholder;
use Illuminate\Database\Seeder;

class SchoolStakeholderSeeder extends Seeder
{
    public function run(): void
    {
        $stakeholders = [
            [
                'category' => 'yayasan',
                'name' => 'Drs. H. M. Zainuddin, M.M.',
                'position' => 'Ketua PPLP-PT PGRI / Yayasan',
                'institution' => 'YPLP / PPLP-PT PGRI Kediri',
                'nip' => null,
                'nik' => '3506123456780001',
                'rank_group' => null,
                'phone' => '081234567890',
                'email' => 'yayasan.pgrikediri@gmail.com',
                'is_signatory' => true,
                'endorsement_title' => 'Mengetahui,',
                'order_index' => 1,
                'is_active' => true,
                'notes' => 'Pengurus Badan Pembina Harian Yayasan',
            ],
            [
                'category' => 'komite',
                'name' => 'H. Sunaryo, S.Sos.',
                'position' => 'Ketua Komite Sekolah',
                'institution' => 'Komite SMK PGRI Kras',
                'nip' => null,
                'nik' => '3506123456780002',
                'rank_group' => null,
                'phone' => '085712345678',
                'email' => 'komite.smkpgrikras@gmail.com',
                'is_signatory' => true,
                'endorsement_title' => 'Mengetahui,',
                'order_index' => 2,
                'is_active' => true,
                'notes' => 'Penandatangan lembar pengesahan KOSP dan Kalender Pendidikan',
            ],
            [
                'category' => 'pengawas',
                'name' => 'Drs. Agus Suharmanto, M.Pd.',
                'position' => 'Pengawas Pembina SMK',
                'institution' => 'Cabang Dinas Pendidikan Wilayah Kediri',
                'nip' => '19680512 199303 1 004',
                'nik' => null,
                'rank_group' => 'Pembina Utama Muda, IV/c',
                'phone' => '081335678901',
                'email' => 'agus.pengawas@jatimprov.go.id',
                'is_signatory' => true,
                'endorsement_title' => 'Menyetujui,',
                'order_index' => 3,
                'is_active' => true,
                'notes' => 'Pengawas Pembina Sekolah Cabang Dinas Wilayah Kediri',
            ],
            [
                'category' => 'dinas',
                'name' => 'Adi Prayitno, S.Pd., M.M.',
                'position' => 'Kepala Cabang Dinas Pendidikan Wilayah Kediri',
                'institution' => 'Cabang Dinas Pendidikan Wilayah Kediri (Kota & Kab. Kediri)',
                'nip' => '19700815 199702 1 003',
                'nik' => null,
                'rank_group' => 'Pembina Tingkat I, IV/b',
                'phone' => '081298765432',
                'email' => 'cabdin.kediri@dindik.jatimprov.go.id',
                'is_signatory' => true,
                'endorsement_title' => 'Mengesahkan,',
                'order_index' => 4,
                'is_active' => true,
                'notes' => 'Pejabat Pengesah Dokumen Kurikulum / KOSP Satuan Pendidikan',
            ],
        ];

        foreach ($stakeholders as $item) {
            SchoolStakeholder::firstOrCreate(
                ['name' => $item['name'], 'position' => $item['position']],
                $item
            );
        }
    }
}
