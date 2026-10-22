<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionScore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupervisionSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = SchoolSetting::getSetting();
        $supervisor = User::where('username', 'supervisor')->first() ?? User::whereHas('role', fn ($q) => $q->where('slug', 'supervisor'))->first();
        $waka = User::where('username', 'waka')->first() ?? User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();
        $kepsek = User::where('username', 'kepsek')->first() ?? User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();

        // Ambil guru nyata dari database SMK PGRI KRAS
        $guruList = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))
            ->where('is_active', true)
            ->take(4)
            ->get();

        $guru1 = $guruList->get(0) ?? User::where('username', 'guru_48902')->first();
        $guru2 = $guruList->get(1) ?? User::where('username', 'guru_48903')->first();
        $guru3 = $guruList->get(2) ?? User::where('username', 'guru_49901')->first();
        $guru4 = $guruList->get(3) ?? User::where('username', 'guru_40801')->first();

        if (! $guru1 || ! $supervisor) {
            return;
        }

        $indicators = SupervisionIndicator::where('is_active', true)->get();

        // 1. Selesai dan Disetujui Kepala Sekolah (Guru 1)
        $spv1 = Supervision::create([
            'report_number' => 'SPV/001/SMK PGRI KRAS/2026',
            'academic_year' => $setting->academic_year,
            'semester' => $setting->semester,
            'teacher_id' => $guru1->id,
            'supervisor_id' => $supervisor->id,
            'principal_id' => $kepsek?->id,
            'subject' => 'Dasar-dasar Teknik Informatika',
            'class_name' => 'X TKJ 1',
            'expertise_program' => 'Teknik Komputer dan Jaringan (TKJ)',
            'schedule_date' => now()->subDays(5)->format('Y-m-d'),
            'schedule_time' => '07.30 - 09.00 WIB',
            'schedule_notes' => 'Supervisi KBM tatap muka materi Jaringan Lokal (LAN)',
            'status' => 'disetujui',

            // RPP
            'rpp_file_path' => null,
            'rpp_original_name' => 'Modul_Ajar_Informatika_X_TKJ.pdf',
            'rpp_uploaded_at' => now()->subDays(6),

            // Praobservasi
            'pre_material' => 'Topologi dan Konfigurasi Jaringan Lokal (LAN)',
            'pre_objectives' => '1. Peserta didik mampu menjelaskan konsep dasar topologi bintang.\n2. Peserta didik mampu merancang skema pengkabelan UTP/RJ-45.',
            'pre_method' => 'Project-Based Learning (PjBL) dipadu Praktik Kelompok',
            'pre_media' => 'Cisco Packet Tracer, Kabel UTP, Crimping Tool, LCD Proyektor',
            'pre_strategy' => 'Demonstrasi guru dilanjutkan eksplorasi mandiri dalam kelompok kerja heterogen',
            'pre_focus' => 'Interaksi antar peserta didik dan pembelajaran berdiferensiasi pada modul praktik',
            'pre_readiness' => 'Perangkat ajar, jobsheet, dan alat bahan lab komputer sudah siap 100%',
            'pre_supervisor_notes' => 'Tujuan dan instrumen jobsheet sangat jelas. Fokuskan pada manajemen waktu saat sesi praktik pengkabelan.',
            'pre_completed_at' => now()->subDays(5),

            // Observasi
            'obs_date' => now()->subDays(5)->format('Y-m-d'),
            'obs_time' => '07.35 WIB',
            'total_score' => 84,
            'max_score' => 92,
            'percentage' => 91.30,
            'predicate' => 'Sangat Baik',
            'obs_completed_at' => now()->subDays(5),

            // Pascaobservasi
            'post_positive' => 'Penguasaan materi sangat mendalam. Antusiasme siswa tinggi dan pembelajaran berdiferensiasi tampak dari pembagian tugas tim yang seimbang.',
            'post_improvement' => 'Pemanfaatan timer digital di layar LCD saat sesi pengerjaan kelompok agar alokasi waktu penutup tidak tergesa-gesa.',
            'post_recommendation' => 'Direkomendasikan menjadi guru model / pengimbas praktik baik pembelajaran aktif di MGMP Kejuruan.',
            'post_followup' => 'Penyusunan video pembelajaran singkat untuk pengayaan peserta didik yang belum tuntas.',
            'post_discussion' => 'Guru menerima hasil evaluasi dengan sangat antusias dan berkomitmen mengoptimalkan sesi refleksi murid.',
            'post_completed_at' => now()->subDays(4),

            // Tanda Tangan & Pengesahan
            'supervisor_signed_at' => now()->subDays(4),
            'approved_by_principal' => true,
            'principal_approved_at' => now()->subDays(3),
            'principal_notes' => 'Luar biasa, pertahankan inovasi pembelajaran praktik kejuruan berpusat pada siswa.',
            'qr_token' => Str::random(40),
            'finalized_at' => now()->subDays(3),
        ]);

        // Berikan skor untuk spv1
        foreach ($indicators as $ind) {
            SupervisionScore::create([
                'supervision_id' => $spv1->id,
                'indicator_id' => $ind->id,
                'score' => rand(3, 4),
                'notes' => 'Terlaksana dengan baik sesuai modul.',
            ]);
        }

        // 2. Menunggu Persetujuan Kepala Sekolah (Guru 2)
        $spv2 = Supervision::create([
            'report_number' => 'SPV/002/SMK PGRI KRAS/2026',
            'academic_year' => $setting->academic_year,
            'semester' => $setting->semester,
            'teacher_id' => $guru2->id,
            'supervisor_id' => $waka->id,
            'principal_id' => $kepsek?->id,
            'subject' => 'Konsentrasi Keahlian TKR',
            'class_name' => 'XI TKR 1',
            'expertise_program' => 'Teknik Kendaraan Ringan (TKR)',
            'schedule_date' => now()->subDays(2)->format('Y-m-d'),
            'schedule_time' => '09.30 - 11.00 WIB',
            'schedule_notes' => 'Supervisi Bengkel Otomotif - Sistem Injeksi Bahan Bakar',
            'status' => 'menunggu_persetujuan',
            'pre_material' => 'Perawatan Berkala Sistem Electronic Fuel Injection (EFI)',
            'pre_objectives' => 'Mendiagnosis kerusakan sensor EFI menggunakan scanner OBD-II',
            'pre_method' => 'Problem-Based Learning di Bengkel TKRO',
            'pre_media' => 'Engine Stand Toyota Avanza, Intelligent Tester OBD-II',
            'pre_strategy' => 'Studi kasus kerusakan riil pada kendaraan',
            'pre_focus' => 'Kepatuhan SOP K3 dan penggunaan alat ukur scanner',
            'pre_readiness' => 'Kendaraan praktik dan scanner telah dikalibrasi',
            'pre_supervisor_notes' => 'SOP K3 harus ditekankan sejak awal pembukaan pembelajaran.',
            'pre_completed_at' => now()->subDays(2),
            'obs_date' => now()->subDays(2)->format('Y-m-d'),
            'total_score' => 78,
            'max_score' => 92,
            'percentage' => 84.78,
            'predicate' => 'Baik',
            'obs_completed_at' => now()->subDays(2),
            'post_positive' => 'Disiplin SOP K3 sangat baik, demonstrasi scanner akurat dan murid terlibat aktif.',
            'post_improvement' => 'Perlu memperkuat lembar kerja siswa (Jobsheet) agar kesimpulan troubleshooting lebih terstruktur.',
            'post_recommendation' => 'Mengikuti pelatihan lanjutan otomotif kendaraan hybrid / listrik.',
            'post_followup' => 'Pengadaan modul ajar digital interaktif.',
            'post_discussion' => 'Guru sepakat menambahkan diagram alir diagnosa pada jobsheet.',
            'post_completed_at' => now()->subDays(1),
            'supervisor_signed_at' => now()->subDays(1),
            'approved_by_principal' => false,
            'qr_token' => Str::random(40),
        ]);

        foreach ($indicators as $ind) {
            SupervisionScore::create([
                'supervision_id' => $spv2->id,
                'indicator_id' => $ind->id,
                'score' => rand(3, 4),
                'notes' => null,
            ]);
        }

        // 3. Sedang dalam tahap Observasi Pembelajaran (Guru 3)
        Supervision::create([
            'report_number' => 'SPV/003/SMK PGRI KRAS/2026',
            'academic_year' => $setting->academic_year,
            'semester' => $setting->semester,
            'teacher_id' => $guru3->id,
            'supervisor_id' => $supervisor->id,
            'subject' => 'Konsentrasi Keahlian AKL',
            'class_name' => 'XI AKL 1',
            'expertise_program' => 'Akuntansi dan Keuangan Lembaga (AKL)',
            'schedule_date' => now()->format('Y-m-d'),
            'schedule_time' => '10.00 - 11.30 WIB',
            'schedule_notes' => 'Supervisi Laboratorium Komputer Akuntansi',
            'status' => 'observasi',
            'pre_material' => 'Penyusunan Jurnal Penyesuaian Perusahaan Jasa',
            'pre_objectives' => 'Menganalisis transaksi penyesuaian akhir periode dan menyusun jurnal',
            'pre_method' => 'Discovery Learning & Praktik Spreadsheet',
            'pre_media' => 'Excel & Buku Besar Pembantu',
            'pre_strategy' => 'Analisis bukti transaksi riil bengkel/laundry',
            'pre_focus' => 'Ketelitian dan keaktifan murid menyelesaikan lembar kerja',
            'pre_readiness' => 'Modul dan data transaksi simulasi sudah siap',
            'pre_supervisor_notes' => 'Hubungkan dengan kasus nyata akuntansi UMKM di lingkungan sekitar Kras Kediri.',
            'pre_completed_at' => now(),
        ]);

        // 4. Baru Terjadwal (Guru 4)
        Supervision::create([
            'report_number' => null,
            'academic_year' => $setting->academic_year,
            'semester' => $setting->semester,
            'teacher_id' => $guru4->id,
            'supervisor_id' => $waka->id,
            'subject' => 'Informatika',
            'class_name' => 'X TKJ 1',
            'expertise_program' => 'Teknik Komputer dan Jaringan (TKJ)',
            'schedule_date' => now()->addDays(3)->format('Y-m-d'),
            'schedule_time' => '08.00 - 09.30 WIB',
            'schedule_notes' => 'Supervisi Praktik Studio Desain Grafis Komputer',
            'status' => 'terjadwal',
        ]);
    }
}
