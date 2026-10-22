<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use App\Models\TimetableUpload;
use App\Services\TimetableSyncService;
use Illuminate\Database\Seeder;

class TimetableSyncSeeder extends Seeder
{
    public function run(): void
    {
        $setting = SchoolSetting::getSetting();

        $scheduleData = [
            'X AK' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'MAKMUN'],
                    ['start' => 4, 'end' => 7, 'subject' => 'DDAKL', 'teacher' => 'ROKHIM'],
                    ['start' => 8, 'end' => 9, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 10, 'end' => 10, 'subject' => 'BK', 'teacher' => 'RANI'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'INF', 'teacher' => 'RIMA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'SR', 'teacher' => 'RENO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'QURNIA'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PJOK', 'teacher' => 'EKO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'MIFTAH'],
                    ['start' => 8, 'end' => 9, 'subject' => 'DDAKL', 'teacher' => 'DITA'],
                    ['start' => 10, 'end' => 10, 'subject' => 'PGRI', 'teacher' => 'KARNI'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                    ['start' => 6, 'end' => 7, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'QURNIA'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'DDAKL', 'teacher' => 'DITA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                ],
            ],

            'X TKJ' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PJOK', 'teacher' => 'EKO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'QURNIA'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'DDTJKT', 'teacher' => 'OKKY'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'MAKMUN'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 3, 'end' => 3, 'subject' => 'PGRI', 'teacher' => 'KARNI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 8, 'end' => 8, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 9, 'end' => 10, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'IPAS', 'teacher' => 'QURNIA'],
                    ['start' => 4, 'end' => 4, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 5, 'end' => 7, 'subject' => 'DDTJKT', 'teacher' => 'BAYU'],
                    ['start' => 8, 'end' => 10, 'subject' => 'INF', 'teacher' => 'TETUKO'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'DDTJKT', 'teacher' => 'OKKY'],
                    ['start' => 4, 'end' => 5, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'SR', 'teacher' => 'RENO'],
                ],
            ],

            'X TKR 1' => [
                'Senin' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'ADI NUR'],
                    ['start' => 4, 'end' => 5, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                    ['start' => 6, 'end' => 7, 'subject' => 'SR', 'teacher' => 'RENO'],
                    ['start' => 8, 'end' => 8, 'subject' => 'PGRI', 'teacher' => 'KARNI'],
                    ['start' => 9, 'end' => 10, 'subject' => 'BING', 'teacher' => 'INDRA'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PJOK', 'teacher' => 'EKO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'ELLA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                    ['start' => 8, 'end' => 10, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 3, 'end' => 4, 'subject' => 'BIND', 'teacher' => 'ADI NUR'],
                    ['start' => 5, 'end' => 7, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'INF', 'teacher' => 'RENO'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                    ['start' => 4, 'end' => 7, 'subject' => 'DDTO', 'teacher' => 'ROFIK'],
                    ['start' => 8, 'end' => 9, 'subject' => 'PKN', 'teacher' => 'MIFTAH'],
                    ['start' => 10, 'end' => 10, 'subject' => 'BK', 'teacher' => 'RUDI'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                    ['start' => 4, 'end' => 5, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                ],
            ],

            'X TKR 2' => [
                'Senin' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BIND', 'teacher' => 'ADI NUR'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'ADI NUR'],
                    ['start' => 4, 'end' => 7, 'subject' => 'DDTO', 'teacher' => 'AFIF'],
                    ['start' => 8, 'end' => 8, 'subject' => 'PGRI', 'teacher' => 'KARNI'],
                    ['start' => 9, 'end' => 10, 'subject' => 'SR', 'teacher' => 'RENO'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                    ['start' => 4, 'end' => 5, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PJOK', 'teacher' => 'EKO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'ELLA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'INF', 'teacher' => 'RENO'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'RISNA'],
                ],
            ],

            'X TKR 3' => [
                'Senin' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'PGRI', 'teacher' => 'KARNI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'SR', 'teacher' => 'RENO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'MIFTAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'LUTFI'],
                    ['start' => 4, 'end' => 7, 'subject' => 'DDTO', 'teacher' => 'TETUKO'],
                    ['start' => 8, 'end' => 10, 'subject' => 'IPAS', 'teacher' => 'NURMA'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'INF', 'teacher' => 'ALIYA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 8, 'end' => 8, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 9, 'end' => 10, 'subject' => 'BING', 'teacher' => 'INDRA'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 6, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PJOK', 'teacher' => 'EKO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'DDTO', 'teacher' => 'SUHADAK'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                ],
            ],

            'XI AK' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 4, 'end' => 7, 'subject' => 'MAPIL AK', 'teacher' => 'ALIYA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR AK', 'teacher' => 'DITA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'MIFTAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'ILHAM ALI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'ILHAM ALI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR AK', 'teacher' => 'DITA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'LEO'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PJOK', 'teacher' => 'CANDRA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                    ['start' => 6, 'end' => 7, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                ],
            ],

            'XI TKJ' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'NURMA'],
                    ['start' => 4, 'end' => 4, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 5, 'end' => 7, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 4, 'end' => 5, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'NURMA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 4, 'end' => 7, 'subject' => 'MAPIL TKJ', 'teacher' => 'OKKY'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PJOK', 'teacher' => 'CANDRA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'RENO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 3, 'end' => 4, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 5, 'end' => 7, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                ],
            ],

            'XI TKR 1' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 8, 'end' => 9, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 10, 'end' => 10, 'subject' => 'BK', 'teacher' => 'DEVI'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PJOK', 'teacher' => 'CANDRA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KIK', 'teacher' => 'QURNIA'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                    ['start' => 4, 'end' => 7, 'subject' => 'MAPIL TKR', 'teacher' => 'AFIF'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'LUTFI'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'QURNIA'],
                ],
            ],

            'XI TKR 2' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'ILHAM ALI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PJOK', 'teacher' => 'CANDRA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'ILHAM ALI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'MIFTAH'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 4, 'end' => 7, 'subject' => 'MAPIL TKR', 'teacher' => 'TETUKO'],
                ],
            ],

            'XI TKR 3' => [
                'Senin' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PJOK', 'teacher' => 'CANDRA'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR TKR', 'teacher' => 'ILHAM ALI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ILHAM ALI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'SEJ', 'teacher' => 'KRIS'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 4, 'end' => 7, 'subject' => 'MAPIL TKR', 'teacher' => 'LUTFI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                ],
            ],

            'XII AK' => [
                'Senin' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR AK', 'teacher' => 'DITA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MAPIL AK', 'teacher' => 'DITA'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR AK', 'teacher' => 'DITA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'MIFTAH'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR AK', 'teacher' => 'ROKHIM'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL AK', 'teacher' => 'DITA'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 3, 'end' => 3, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'B.JAWA', 'teacher' => 'MIFTAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                ],
            ],

            'XII TKJ 1' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 8, 'end' => 9, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 10, 'end' => 10, 'subject' => 'BK', 'teacher' => 'RUDI'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKJ', 'teacher' => 'OKKY'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'MIFTAH'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKJ', 'teacher' => 'OKKY'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 1, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 2, 'end' => 3, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'RENO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                ],
            ],

            'XII TKJ 2' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKJ', 'teacher' => 'OKKY'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'BAYU'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKJ', 'teacher' => 'OKKY'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                    ['start' => 4, 'end' => 7, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 8, 'end' => 9, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 10, 'end' => 10, 'subject' => 'BK', 'teacher' => 'RUDI'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'DIAH'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 8, 'end' => 8, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 9, 'end' => 10, 'subject' => 'KIK', 'teacher' => 'RIMA'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'WARNI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR TKJ', 'teacher' => 'AYU'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'DIAH'],
                ],
            ],

            'XII TKR 1' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'TETUKO'],
                    ['start' => 4, 'end' => 6, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 7, 'end' => 7, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKR', 'teacher' => 'LUTFI'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MAPIL TKR', 'teacher' => 'LUTFI'],
                    ['start' => 4, 'end' => 6, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 7, 'end' => 7, 'subject' => 'BK', 'teacher' => 'RUDI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KIK', 'teacher' => 'ILHAM ALI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'TETUKO'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'ILHAM ALI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'MIFTAH'],
                ],
            ],

            'XII TKR 2' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'MAPIL TKR', 'teacher' => 'AFIF'],
                    ['start' => 4, 'end' => 4, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 5, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'BUDI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUHADAK'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 2, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 3, 'end' => 5, 'subject' => 'PAI', 'teacher' => 'MUNIF'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 6, 'end' => 8, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUHADAK'],
                    ['start' => 9, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'LEO'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KIK', 'teacher' => 'CANDRA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKR', 'teacher' => 'AFIF'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'BIND', 'teacher' => 'AGUNG'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KIK', 'teacher' => 'CANDRA'],
                    ['start' => 6, 'end' => 6, 'subject' => 'BK', 'teacher' => 'RANI'],
                ],
            ],

            'XII TKR 3' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'LUTFI'],
                    ['start' => 4, 'end' => 6, 'subject' => 'MTK', 'teacher' => 'MAKMUN'],
                    ['start' => 7, 'end' => 7, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKR', 'teacher' => 'TETUKO'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                    ['start' => 4, 'end' => 6, 'subject' => 'PAI', 'teacher' => 'EKO'],
                    ['start' => 7, 'end' => 7, 'subject' => 'BK', 'teacher' => 'RANI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKR', 'teacher' => 'TETUKO'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'QURNIA'],
                    ['start' => 4, 'end' => 5, 'subject' => 'PKN', 'teacher' => 'RENO'],
                    ['start' => 6, 'end' => 7, 'subject' => 'KONSJUR TKR', 'teacher' => 'ILHAM ALI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ILHAM ALI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'INDRA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'EFENDI'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'LUTFI'],
                    ['start' => 4, 'end' => 4, 'subject' => 'KIK', 'teacher' => 'QURNIA'],
                    ['start' => 5, 'end' => 6, 'subject' => 'BING', 'teacher' => 'INDRA'],
                ],
            ],

            'XII TKR 4' => [
                'Senin' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 6, 'end' => 7, 'subject' => 'B.JAWA', 'teacher' => 'YOGA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'MAPIL TKR', 'teacher' => 'AFIF'],
                ],
                'Selasa' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                    ['start' => 4, 'end' => 4, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 5, 'end' => 7, 'subject' => 'MTK', 'teacher' => 'PUNGKAS'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                ],
                'Rabu' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'ROFIK'],
                    ['start' => 4, 'end' => 6, 'subject' => 'MAPIL TKR', 'teacher' => 'AFIF'],
                    ['start' => 7, 'end' => 7, 'subject' => 'BK', 'teacher' => 'DEVI'],
                    ['start' => 8, 'end' => 10, 'subject' => 'BIND', 'teacher' => 'ADI NUR'],
                ],
                'Kamis' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'PAI', 'teacher' => 'WARNI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'KIK', 'teacher' => 'ALIYA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 8, 'end' => 10, 'subject' => 'KONSJUR TKR', 'teacher' => 'JUMARI'],
                ],
                'Jumat' => [
                    ['start' => 1, 'end' => 3, 'subject' => 'KONSJUR TKR', 'teacher' => 'SUPRI'],
                    ['start' => 4, 'end' => 5, 'subject' => 'BING', 'teacher' => 'RISNA'],
                    ['start' => 6, 'end' => 7, 'subject' => 'PKN', 'teacher' => 'UTORO'],
                ],
            ],
        ];

        // Create an initial TimetableUpload record for the archive
        $upload = TimetableUpload::create([
            'academic_year' => $setting->academic_year ?? '2026/2027',
            'semester' => $setting->semester ?? 'Ganjil',
            'title' => 'Jadwal Pelajaran Kelas TA 2026/2027 (aSc Timetables)',
            'original_filename' => 'Jadwal_Kelas_SMK_PGRI_KRAS_2026.pdf',
            'file_path' => 'timetables/Jadwal_Kelas_SMK_PGRI_KRAS_2026.pdf',
            'file_size' => 1048576,
            'uploaded_by' => 1,
            'notes' => 'Sinkronisasi awal dari dokumen cetak aSc Timetables (17 Rombel)',
            'total_classes' => count($scheduleData),
            'total_lessons' => 0,
            'is_active' => true,
        ]);

        $result = TimetableSyncService::processScheduleData($scheduleData, $upload);

        $this->command->info("Sync completed: {$result['total_classes']} classes, {$result['total_lessons']} lessons, {$result['total_assignments']} assignments.");
    }
}
