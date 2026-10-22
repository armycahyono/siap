<?php

namespace Database\Seeders;

use App\Models\SupervisionIndicator;
use App\Models\SupervisionPhase;
use Illuminate\Database\Seeder;

class SupervisionRubricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $phases = [
            [
                'code' => 'A',
                'name' => 'Kegiatan Pendahuluan',
                'order' => 1,
                'indicators' => [
                    'Membuka pembelajaran dengan salam dan doa',
                    'Apersepsi dan mengaitkan materi sebelumnya',
                    'Menyampaikan tujuan pembelajaran dan indikator ketercapaian',
                    'Memotivasi murid dengan pertanyaan pemantik / relevansi',
                    'Menyiapkan kondisi belajar murid secara fisik dan psikologis',
                ],
            ],
            [
                'code' => 'B',
                'name' => 'Kegiatan Inti',
                'order' => 2,
                'indicators' => [
                    'Penguasaan materi pembelajaran',
                    'Kejelasan penyampaian materi dan konsep',
                    'Penggunaan metode/model pembelajaran yang interaktif',
                    'Aktivitas murid dalam proses belajar',
                    'Interaksi positif antara Guru dan murid',
                    'Penggunaan media pembelajaran yang relevan',
                    'Pemanfaatan teknologi/digital dalam pembelajaran',
                    'Penerapan pembelajaran berdiferensiasi (proses/konten/produk)',
                    'Penguatan literasi dan numerasi dalam pembelajaran',
                    'Pengembangan kemampuan berpikir kritis dan pemecahan masalah',
                    'Kolaborasi dan kerja sama antar murid',
                    'Pengelolaan kelas yang kondusif dan disiplin positif',
                    'Asesmen formatif selama proses pembelajaran berlangsung',
                ],
            ],
            [
                'code' => 'C',
                'name' => 'Kegiatan Penutup',
                'order' => 3,
                'indicators' => [
                    'Refleksi bersama murid terhadap proses pembelajaran',
                    'Penguatan materi dan konfirmasi pemahaman',
                    'Pemberian umpan balik yang konstruktif',
                    'Penyusunan kesimpulan pembelajaran bersama murid',
                    'Penyampaian tindak lanjut dan penugasan atau rencana pertemuan berikutnya',
                ],
            ],
        ];

        foreach ($phases as $pData) {
            $phase = SupervisionPhase::firstOrCreate(
                ['name' => $pData['name']],
                [
                    'code' => $pData['code'],
                    'order' => $pData['order'],
                    'is_active' => true,
                ]
            );

            foreach ($pData['indicators'] as $index => $title) {
                $code = $pData['code'].'.'.($index + 1);
                SupervisionIndicator::firstOrCreate(
                    [
                        'phase_id' => $phase->id,
                        'title' => $title,
                    ],
                    [
                        'code' => $code,
                        'max_score' => 4,
                        'order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
