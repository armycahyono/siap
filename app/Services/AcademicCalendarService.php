<?php

namespace App\Services;

use App\Models\AcademicCalendarUpload;
use App\Models\CurriculumEvent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AcademicCalendarService
{
    /**
     * Get or create default baseline 2026/2027 Kaldik from uploaded file and standard dates.
     */
    public static function seedDefaultKaldik2026(int $uploadedBy = 1): AcademicCalendarUpload
    {
        $academicYear = '2026/2027';

        // Check if an upload already exists
        $upload = AcademicCalendarUpload::where('academic_year', $academicYear)->latest()->first();

        $destinationPath = 'academic_calendars/kaldik-2026-2027.pdf';

        if (! Storage::disk('public')->exists($destinationPath)) {
            // Source from user uploaded artifact (Kaldik 2026/2027)
            $sourceArtifact = 'C:\\Users\\USER\\.gemini\\antigravity\\brain\\b5582842-f9c9-4614-8a3d-3fa2e2577682\\.user_uploaded\\media_1790831157319.pdf';
            if (File::exists($sourceArtifact)) {
                Storage::disk('public')->put($destinationPath, File::get($sourceArtifact));
            } else {
                Storage::disk('public')->put($destinationPath, '%PDF-1.4 Mock Kaldik');
            }
        }

        $fullPath = storage_path('app/public/'.$destinationPath);
        $fileSize = File::exists($fullPath) ? File::size($fullPath) : 0;

        if (! $upload) {
            $upload = AcademicCalendarUpload::create([
                'academic_year' => $academicYear,
                'title' => 'Kalender Pendidikan SMK PGRI Kras TP 2026/2027',
                'original_filename' => 'Kaldik_SMK_PGRI_KRAS_2026_2027.pdf',
                'file_path' => $destinationPath,
                'file_size' => $fileSize,
                'uploaded_by' => $uploadedBy,
                'total_events' => 0,
                'is_active' => true,
                'notes' => 'Kalender resmi hari efektif, hari efektif fakultatif, dan libur sekolah Provinsi Jawa Timur.',
            ]);
        }

        // Generate events if none exist for this upload
        if (CurriculumEvent::where('academic_year', $academicYear)->where('academic_calendar_upload_id', $upload->id)->count() === 0) {
            self::generateDefaultEventsFor2026($upload);
        }

        return $upload;
    }

    /**
     * Generate all events from the official Kaldik 2026/2027 document.
     */
    public static function generateDefaultEventsFor2026(AcademicCalendarUpload $upload): void
    {
        $academicYear = $upload->academic_year;

        $events = [
            // Libur Hari Besar (LHB)
            [
                'title' => 'LHB: HUT Republik Indonesia ke-81',
                'description' => 'Hari Libur Nasional Hari Kemerdekaan Republik Indonesia',
                'category' => 'lhb',
                'start_date' => '2026-08-17',
                'end_date' => '2026-08-17',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Maulid Nabi Muhammad SAW',
                'description' => 'Hari Libur Nasional Peringatan Maulid Nabi Muhammad SAW 1448 H',
                'category' => 'lhb',
                'start_date' => '2026-08-25',
                'end_date' => '2026-08-25',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'CB: Cuti Bersama Hari Raya Natal',
                'description' => 'Cuti Bersama Pemerintah',
                'category' => 'cb',
                'start_date' => '2026-12-24',
                'end_date' => '2026-12-24',
                'is_holiday' => true,
                'color' => '#9333ea',
            ],
            [
                'title' => 'LHB: Hari Raya Natal',
                'description' => 'Hari Libur Nasional Natal',
                'category' => 'lhb',
                'start_date' => '2026-12-25',
                'end_date' => '2026-12-25',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Tahun Baru Masehi 2027',
                'description' => 'Hari Libur Nasional Tahun Baru 2027 Masehi',
                'category' => 'lhb',
                'start_date' => '2027-01-01',
                'end_date' => '2027-01-01',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Isra Mi\'raj Nabi Muhammad SAW',
                'description' => 'Hari Libur Nasional Peringatan Isra Mi\'raj 1448 H',
                'category' => 'lhb',
                'start_date' => '2027-01-05',
                'end_date' => '2027-01-05',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Tahun Baru Imlek 2578',
                'description' => 'Hari Libur Nasional Tahun Baru Imlek',
                'category' => 'lhb',
                'start_date' => '2027-02-06',
                'end_date' => '2027-02-06',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Raya Nyepi Tahun Saka 1949',
                'description' => 'Hari Libur Nasional Hari Suci Nyepi',
                'category' => 'lhb',
                'start_date' => '2027-03-09',
                'end_date' => '2027-03-09',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Raya Idul Fitri 1448 H',
                'description' => 'Hari Libur Nasional Idul Fitri 1448 H',
                'category' => 'lhb',
                'start_date' => '2027-03-10',
                'end_date' => '2027-03-11',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Wafat Isa Al-Masih',
                'description' => 'Hari Libur Nasional Peringatan Wafat Isa Al-Masih',
                'category' => 'lhb',
                'start_date' => '2027-03-26',
                'end_date' => '2027-03-26',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Paskah',
                'description' => 'Peringatan Hari Paskah',
                'category' => 'lhb',
                'start_date' => '2027-03-28',
                'end_date' => '2027-03-28',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Buruh Internasional',
                'description' => 'Hari Libur Nasional May Day',
                'category' => 'lhb',
                'start_date' => '2027-05-01',
                'end_date' => '2027-05-01',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Kenaikan Isa Al-Masih',
                'description' => 'Hari Libur Nasional Kenaikan Isa Al-Masih',
                'category' => 'lhb',
                'start_date' => '2027-05-06',
                'end_date' => '2027-05-06',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Raya Idul Adha 1448 H',
                'description' => 'Hari Libur Nasional Idul Adha',
                'category' => 'lhb',
                'start_date' => '2027-05-17',
                'end_date' => '2027-05-17',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Raya Waisak 2571',
                'description' => 'Hari Libur Nasional Tri Suci Waisak',
                'category' => 'lhb',
                'start_date' => '2027-05-20',
                'end_date' => '2027-05-20',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Hari Lahir Pancasila',
                'description' => 'Hari Libur Nasional Peringatan Hari Lahir Pancasila',
                'category' => 'lhb',
                'start_date' => '2027-06-01',
                'end_date' => '2027-06-01',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],
            [
                'title' => 'LHB: Tahun Baru Hijriyah 1449 H',
                'description' => 'Hari Libur Nasional 1 Muharram 1449 H',
                'category' => 'lhb',
                'start_date' => '2027-06-06',
                'end_date' => '2027-06-06',
                'is_holiday' => true,
                'color' => '#dc2626',
            ],

            // KTS & Momen Khusus
            [
                'title' => 'KTS: Kegiatan Tengah Semester Ganjil',
                'description' => 'Kegiatan Tengah Semester (KTS 3 Hari) untuk evaluasi, asesmen sumatif tengah semester, & pembinaan kesiswaan.',
                'category' => 'kts',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-03',
                'is_holiday' => false,
                'color' => '#16a34a',
            ],
            [
                'title' => 'KPP: Kegiatan Permulaan Puasa Ramadan 1448 H',
                'description' => 'Kegiatan permulaan puasa Ramadan (Fakultatif / Keagamaan).',
                'category' => 'kpp',
                'start_date' => '2027-02-08',
                'end_date' => '2027-02-10',
                'is_holiday' => false,
                'color' => '#d97706',
            ],
            [
                'title' => 'LHR: Libur Sekitar Hari Raya Idul Fitri',
                'description' => 'Libur sekitar Hari Raya Idul Fitri 1448 H.',
                'category' => 'lhr',
                'start_date' => '2027-03-03',
                'end_date' => '2027-03-18',
                'is_holiday' => true,
                'color' => '#ca8a04',
            ],
            [
                'title' => 'LS1: Libur Semester Ganjil',
                'description' => 'Libur Semester 1 untuk peserta didik.',
                'category' => 'ls1',
                'start_date' => '2026-12-26',
                'end_date' => '2027-01-02',
                'is_holiday' => true,
                'color' => '#8b5cf6',
            ],
            [
                'title' => 'LS2: Libur Akhir Semester Genap',
                'description' => 'Libur Semester 2 / Kenaikan Kelas untuk peserta didik.',
                'category' => 'ls2',
                'start_date' => '2027-06-21',
                'end_date' => '2027-07-10',
                'is_holiday' => true,
                'color' => '#7c3aed',
            ],

            // Kegiatan Utama Kurikulum
            [
                'title' => 'Awal Masuk Sekolah & MPLS TP 2026/2027',
                'description' => 'Hari Pertama Masuk Sekolah & Masa Pengenalan Lingkungan Sekolah.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-07-13',
                'end_date' => '2026-07-16',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Asesmen Nasional Berbasis Komputer (ANBK)',
                'description' => 'Pelaksanaan ANBK Utama SMK PGRI Kras.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-08-31',
                'end_date' => '2026-09-03',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Asesmen Sumatif Akhir Semester (ASAS) Ganjil',
                'description' => 'Pelaksanaan ujian akhir semester ganjil seluruh tingkat.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-11-30',
                'end_date' => '2026-12-11',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Pembagian Rapor Semester Ganjil',
                'description' => 'Penyerahan Laporan Hasil Belajar Semester Ganjil kepada wali murid.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-12-18',
                'end_date' => '2026-12-18',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Hari Pertama Masuk Sekolah Semester Genap',
                'description' => 'KBM Semester Genap dimulai.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-01-04',
                'end_date' => '2027-01-04',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Uji Kompetensi Keahlian (UKK) Praktik Kejuruan',
                'description' => 'Uji Kompetensi Keahlian Kelas XII bersama Penguji Eksternal DUDI.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-02-15',
                'end_date' => '2027-02-27',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Asesmen Sumatif Akhir Jenjang (ASAJ) Kelas XII',
                'description' => 'Ujian Akhir Sekolah Kelas XII.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-03-22',
                'end_date' => '2027-03-31',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Asesmen Sumatif Akhir Tahun (ASAT) Kelas X & XI',
                'description' => 'Pelaksanaan Penilaian Akhir Tahun Kenaikan Kelas.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-05-24',
                'end_date' => '2027-06-05',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
            [
                'title' => 'Pembagian Rapor Kenaikan Kelas Semester Genap',
                'description' => 'Penyerahan Laporan Hasil Belajar Kenaikan Kelas kepada wali murid.',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-06-18',
                'end_date' => '2027-06-18',
                'is_holiday' => false,
                'color' => '#2563eb',
            ],
        ];

        $count = 0;
        foreach ($events as $evt) {
            CurriculumEvent::create([
                'academic_calendar_upload_id' => $upload->id,
                'academic_year' => $academicYear,
                'title' => $evt['title'],
                'description' => $evt['description'],
                'category' => $evt['category'],
                'start_date' => $evt['start_date'],
                'end_date' => $evt['end_date'],
                'is_all_day' => true,
                'color' => $evt['color'],
                'is_holiday' => $evt['is_holiday'],
                'location' => 'SMK PGRI Kras',
                'created_by' => $upload->uploaded_by,
            ]);
            $count++;
        }

        $upload->update(['total_events' => $count]);
    }
}
