<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $setting->academic_year ?? '2025/2026';

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
            $dept = Department::updateOrCreate(['code' => $d['code']], $d);
            $deptMap[$d['code']] = $dept;
        }

        // 2. TINGKAT (Grade Levels)
        $grades = [
            ['level' => 'X', 'name' => 'Tingkat X (Sepuluh)', 'order_index' => 1],
            ['level' => 'XI', 'name' => 'Tingkat XI (Sebelas)', 'order_index' => 2],
            ['level' => 'XII', 'name' => 'Tingkat XII (Dua Belas)', 'order_index' => 3],
        ];

        $gradeMap = [];
        foreach ($grades as $g) {
            $grade = GradeLevel::updateOrCreate(['level' => $g['level']], $g);
            $gradeMap[$g['level']] = $grade;
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

        $subjectMap = [];
        foreach ($subjects as $s) {
            $subj = Subject::updateOrCreate(
                ['code' => $s['code']],
                [
                    'name' => $s['name'],
                    'category' => $s['category'],
                    'department_id' => $s['dept'] ? ($deptMap[$s['dept']]->id ?? null) : null,
                    'order_index' => $s['order'],
                    'is_active' => true,
                ]
            );
            $subjectMap[$s['code']] = $subj;
        }

        // 4. KELAS / ROMBEL & PENUGASAN WALI KELAS
        $waliSukarni = User::where('nigs_fix', '48903')->first(); // Drs. Sukarni
        $waliJumari = User::where('nigs_fix', '49901')->first(); // Jumari, S.Pd.
        $waliOkky = User::where('nigs_fix', '42005')->first(); // Okky Willyand
        $waliDita = User::where('nigs_fix', '41705')->first(); // Dita Maghfiratul
        $waliSupriyanto = User::where('nigs_fix', '40601')->first(); // Supriyanto, ST

        $waliIndra = User::where('nigs_fix', '41709')->first(); // Indra Yustiawan
        $waliAfif = User::where('nigs_fix', '42009')->first(); // Afif Bey Ridhotulloh
        $waliLutfi = User::where('nigs_fix', '42414')->first(); // Lutfi Andri Saputro
        $waliDiah = User::where('nigs_fix', '42409')->first(); // Diah Ayu Kumalasari
        $waliEko = User::where('nigs_fix', '42007')->first(); // Eko Aqthoena Ulfa
        $waliQurnia = User::where('nigs_fix', '41701')->first(); // Qurnia Ni'matul Ulfah
        $waliAgung = User::where('nigs_fix', '41708')->first(); // Agung Prasetyo
        $waliRisna = User::where('nigs_fix', '41904')->first(); // Risna Hilaliyah
        $waliNurma = User::where('nigs_fix', '40802')->first(); // Nurma Fauziah
        $waliMiftahur = User::where('nigs_fix', '42106')->first(); // Miftahur Rohmah
        $waliLeo = User::where('nigs_fix', '42211')->first(); // Leo Hermawan Santoso
        $waliIlham = User::where('nigs_fix', '41711')->first(); // Ilham Budi Santoso
        $waliReno = User::where('nigs_fix', '42001')->first(); // Reno Auliya Anggraini
        $waliAdi = User::where('nigs_fix', '41101')->first(); // Adi Nuranto
        $waliEndangP = User::where('nigs_fix', '40801')->first(); // Endang Pungkaswati
        $waliYoga = User::where('nigs_fix', '41707')->first(); // Yoga Anggorina Kukuh K.
        $waliMakmun = User::where('nigs_fix', '42406')->first(); // Moh. Makmun Solikhi

        $classes = [
            // Kelas X
            ['name' => 'X TKR 1', 'level' => 'X', 'dept' => 'TKR', 'wali' => $waliIndra?->id],
            ['name' => 'X TKR 2', 'level' => 'X', 'dept' => 'TKR', 'wali' => $waliAfif?->id],
            ['name' => 'X TKR 3', 'level' => 'X', 'dept' => 'TKR', 'wali' => $waliLutfi?->id],
            ['name' => 'X TKJ 1', 'level' => 'X', 'dept' => 'TKJ', 'wali' => $waliDiah?->id],
            ['name' => 'X AKL 1', 'level' => 'X', 'dept' => 'AKL', 'wali' => $waliEko?->id],

            // Kelas XI
            ['name' => 'XI TKR 1', 'level' => 'XI', 'dept' => 'TKR', 'wali' => $waliQurnia?->id],
            ['name' => 'XI TKR 2', 'level' => 'XI', 'dept' => 'TKR', 'wali' => $waliAgung?->id],
            ['name' => 'XI TKR 3', 'level' => 'XI', 'dept' => 'TKR', 'wali' => $waliRisna?->id],
            ['name' => 'XI TKJ 1', 'level' => 'XI', 'dept' => 'TKJ', 'wali' => $waliNurma?->id],
            ['name' => 'XI AKL 1', 'level' => 'XI', 'dept' => 'AKL', 'wali' => $waliMiftahur?->id],

            // Kelas XII
            ['name' => 'XII TKR 1', 'level' => 'XII', 'dept' => 'TKR', 'wali' => $waliLeo?->id],
            ['name' => 'XII TKR 2', 'level' => 'XII', 'dept' => 'TKR', 'wali' => $waliIlham?->id],
            ['name' => 'XII TKR 3', 'level' => 'XII', 'dept' => 'TKR', 'wali' => $waliReno?->id],
            ['name' => 'XII TKR 4', 'level' => 'XII', 'dept' => 'TKR', 'wali' => $waliAdi?->id],
            ['name' => 'XII TKJ 1', 'level' => 'XII', 'dept' => 'TKJ', 'wali' => $waliEndangP?->id],
            ['name' => 'XII TKJ 2', 'level' => 'XII', 'dept' => 'TKJ', 'wali' => $waliYoga?->id],
            ['name' => 'XII AKL 1', 'level' => 'XII', 'dept' => 'AKL', 'wali' => $waliMakmun?->id],
        ];

        $classMap = [];
        foreach ($classes as $c) {
            $cls = Classroom::updateOrCreate(
                ['name' => $c['name'], 'academic_year' => $academicYear],
                [
                    'grade_level_id' => $gradeMap[$c['level']]->id,
                    'department_id' => $deptMap[$c['dept']]->id,
                    'homeroom_teacher_id' => $c['wali'],
                    'is_active' => true,
                ]
            );
            $classMap[$c['name']] = $cls;
        }

        // 5. PENUGASAN MAPEL DIAMPU (1 GURU BISA LEBIH DARI 1 MAPEL)
        $teacherAssignments = [
            // Drs. Sukarni (NIGS 48903) mengampu 2 MAPEL: PPKN dan SEJ
            [
                'teacher' => $waliSukarni,
                'subject' => $subjectMap['PPKN'] ?? null,
                'class' => $classMap['X TKR 1'] ?? null,
                'hours' => 2,
                'notes' => 'Pengampu PPKN Kelas X',
            ],
            [
                'teacher' => $waliSukarni,
                'subject' => $subjectMap['PPKN'] ?? null,
                'class' => $classMap['X TKR 2'] ?? null,
                'hours' => 2,
                'notes' => 'Pengampu PPKN Kelas X',
            ],
            [
                'teacher' => $waliSukarni,
                'subject' => $subjectMap['SEJ'] ?? null,
                'class' => $classMap['X TKJ 1'] ?? null,
                'hours' => 2,
                'notes' => 'Pengampu Sejarah Indonesia (Mapel Kedua)',
            ],

            // Nurma Fauziah (NIGS 40802) mengampu 2 MAPEL: IPAS dan INF
            [
                'teacher' => User::where('nigs_fix', '40802')->first(),
                'subject' => $subjectMap['IPAS'] ?? null,
                'class' => $classMap['X TKR 1'] ?? null,
                'hours' => 4,
                'notes' => 'Fisika & Kimia Kejuruan',
            ],
            [
                'teacher' => User::where('nigs_fix', '40802')->first(),
                'subject' => $subjectMap['INF'] ?? null,
                'class' => $classMap['X AKL 1'] ?? null,
                'hours' => 2,
                'notes' => 'Literasi Digital Informatika',
            ],

            // Endang Pungkaswati (NIGS 40801) mengampu Matematika di beberapa kelas
            [
                'teacher' => User::where('nigs_fix', '40801')->first(),
                'subject' => $subjectMap['MTK'] ?? null,
                'class' => $classMap['XI TKR 1'] ?? null,
                'hours' => 4,
                'notes' => 'Matematika Terapan',
            ],
            [
                'teacher' => User::where('nigs_fix', '40801')->first(),
                'subject' => $subjectMap['MTK'] ?? null,
                'class' => $classMap['XI TKJ 1'] ?? null,
                'hours' => 4,
                'notes' => 'Matematika Terapan',
            ],

            // Okky Willyand (NIGS 42005) mengampu 2 MAPEL: TKJ-ASJ dan INF
            [
                'teacher' => $waliOkky,
                'subject' => $subjectMap['TKJ-ASJ'] ?? null,
                'class' => $classMap['XI TKJ 1'] ?? null,
                'hours' => 6,
                'notes' => 'Server Linux & Konfigurasi Layanan Jaringan',
            ],
            [
                'teacher' => $waliOkky,
                'subject' => $subjectMap['INF'] ?? null,
                'class' => $classMap['X TKJ 1'] ?? null,
                'hours' => 3,
                'notes' => 'Dasar Algoritma & Komputer',
            ],

            // Dita Maghfiratul (NIGS 41705) mengampu 2 MAPEL: AKL-PRAK dan AKL-KOMPAK
            [
                'teacher' => $waliDita,
                'subject' => $subjectMap['AKL-PRAK'] ?? null,
                'class' => $classMap['X AKL 1'] ?? null,
                'hours' => 5,
                'notes' => 'Siklus Akuntansi Jasa & Dagang',
            ],
            [
                'teacher' => $waliDita,
                'subject' => $subjectMap['AKL-KOMPAK'] ?? null,
                'class' => $classMap['XI AKL 1'] ?? null,
                'hours' => 4,
                'notes' => 'Aplikasi Komputer Spreadsheet / MYOB',
            ],

            // Jumari, S.Pd. (NIGS 49901) mengampu 2 MAPEL: TKR-PMKR dan TKR-PKKR
            [
                'teacher' => $waliJumari,
                'subject' => $subjectMap['TKR-PMKR'] ?? null,
                'class' => $classMap['XI TKR 1'] ?? null,
                'hours' => 6,
                'notes' => 'Tune-up & Overhaul Mesin Otomotif',
            ],
            [
                'teacher' => $waliJumari,
                'subject' => $subjectMap['TKR-PKKR'] ?? null,
                'class' => $classMap['XII TKR 1'] ?? null,
                'hours' => 6,
                'notes' => 'Sistem Starter & Pengisian',
            ],

            // Supriyanto, ST (Supervisor / NIGS 40601) mengampu TKR-PSPT
            [
                'teacher' => $waliSupriyanto,
                'subject' => $subjectMap['TKR-PSPT'] ?? null,
                'class' => $classMap['XII TKR 1'] ?? null,
                'hours' => 6,
                'notes' => 'Sistem Rem ABS & Transmisi Manual',
            ],

            // Suwarni, S.PdI (NIGS 48904) mengampu PAI
            [
                'teacher' => User::where('nigs_fix', '48904')->first(),
                'subject' => $subjectMap['PAI'] ?? null,
                'class' => $classMap['X TKR 1'] ?? null,
                'hours' => 3,
                'notes' => 'Pendidikan Agama Islam',
            ],
            [
                'teacher' => User::where('nigs_fix', '48904')->first(),
                'subject' => $subjectMap['PAI'] ?? null,
                'class' => $classMap['X TKJ 1'] ?? null,
                'hours' => 3,
                'notes' => 'Pendidikan Agama Islam',
            ],

            // Candra Tri Setyono (NIGS 41404) mengampu PJOK
            [
                'teacher' => User::where('nigs_fix', '41404')->first(),
                'subject' => $subjectMap['PJOK'] ?? null,
                'class' => $classMap['X TKR 1'] ?? null,
                'hours' => 3,
                'notes' => 'Olahraga dan Kebugaran',
            ],
            [
                'teacher' => User::where('nigs_fix', '41404')->first(),
                'subject' => $subjectMap['PJOK'] ?? null,
                'class' => $classMap['X TKJ 1'] ?? null,
                'hours' => 3,
                'notes' => 'Olahraga dan Kebugaran',
            ],
        ];

        foreach ($teacherAssignments as $ta) {
            if ($ta['teacher'] && $ta['subject']) {
                TeacherSubjectAssignment::updateOrCreate(
                    [
                        'teacher_id' => $ta['teacher']->id,
                        'subject_id' => $ta['subject']->id,
                        'classroom_id' => $ta['class']?->id,
                        'academic_year' => $academicYear,
                    ],
                    [
                        'hours_per_week' => $ta['hours'],
                        'notes' => $ta['notes'],
                    ]
                );
            }
        }

        // 5. JABATAN STRUKTURAL (Structural Positions)
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
            StructuralPosition::updateOrCreate(
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
