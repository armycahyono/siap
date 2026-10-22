<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\PiketAttendance;
use App\Models\PiketGuest;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PiketSampleSeeder extends Seeder
{
    public function run(): void
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $setting->academic_year ?? '2026/2027';
        $semester = $setting->semester ?? 'Ganjil';

        $classrooms = Classroom::where('academic_year', $academicYear)->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::all();
        }

        $teachers = User::where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum']))
            ->get();

        if ($teachers->isEmpty() || $classrooms->isEmpty()) {
            return;
        }

        // 1. Seed Sample Students dengan Biodata Lengkap & Data Ortu
        $sampleStudents = [
            [
                'name' => 'Ahmad Rizki Pratama',
                'gender' => 'L',
                'nis' => '21001',
                'nisn' => '0071234501',
                'nik' => '3506123456780001',
                'birth_place' => 'Kediri',
                'birth_date' => '2008-04-12',
                'religion' => 'Islam',
                'address' => 'Jl. Merdeka No. 15, Kras, Kediri',
                'father_name' => 'Bambang Pratama',
                'father_job' => 'Wiraswasta',
                'father_phone' => '081234567801',
                'mother_name' => 'Siti Khotimah',
                'mother_job' => 'Ibu Rumah Tangga',
                'mother_phone' => '081234567802',
                'phone_parent' => '081234567801',
                'phone_student' => '085712345601',
            ],
            [
                'name' => 'Citra Dewi Anggraini',
                'gender' => 'P',
                'nis' => '21003',
                'nisn' => '0071234503',
                'nik' => '3506123456780003',
                'birth_place' => 'Kediri',
                'birth_date' => '2008-09-25',
                'religion' => 'Islam',
                'address' => 'Dsn. Sumberarum RT 01 RW 02, Kras',
                'father_name' => 'Supriyanto',
                'father_job' => 'PNS',
                'father_phone' => '081234567803',
                'mother_name' => 'Endah Sulistyowati',
                'mother_job' => 'Guru',
                'mother_phone' => '081234567804',
                'phone_parent' => '081234567803',
                'phone_student' => '085712345603',
            ],
            [
                'name' => 'Dimas Bagus Saputra',
                'gender' => 'L',
                'nis' => '21004',
                'nisn' => '0071234504',
                'nik' => '3506123456780004',
                'birth_place' => 'Blitar',
                'birth_date' => '2008-01-18',
                'religion' => 'Islam',
                'address' => 'Jl. Kenanga No. 8, Rejotangan, Tulungagung',
                'father_name' => 'Agus Saputra',
                'father_job' => 'Karyawan Swasta',
                'father_phone' => '081234567805',
                'mother_name' => 'Sri Wahyuni',
                'mother_job' => 'Pedagang',
                'mother_phone' => '081234567806',
                'phone_parent' => '081234567805',
                'phone_student' => '085712345604',
            ],
        ];

        $classroomChunk = $classrooms->values();
        foreach ($sampleStudents as $idx => $st) {
            $c = $classroomChunk[$idx % count($classroomChunk)];
            Student::updateOrCreate(
                ['nis' => $st['nis']],
                array_merge($st, ['classroom_id' => $c->id, 'is_active' => true])
            );
        }

        // 2. Seed Picket Schedules (Senin - Jumat)
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $teacherIndex = 0;

        foreach ($days as $day) {
            // Assign 3 guru per hari
            for ($k = 0; $k < 3; $k++) {
                if (! isset($teachers[$teacherIndex])) {
                    $teacherIndex = 0;
                }
                $t = $teachers[$teacherIndex++];

                PiketSchedule::firstOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'semester' => $semester,
                        'day' => $day,
                        'teacher_id' => $t->id,
                    ],
                    [
                        'role' => ($k === 0) ? 'koordinator' : 'anggota',
                        'duty_location' => ($k === 0) ? 'Lobi & Meja Utama' : (($k === 1) ? 'Pos Gerbang Depan' : 'Patroli Gedung & Kelas'),
                        'is_active' => true,
                    ]
                );
            }
        }

        // 3. Seed Today's Journal & Attendance
        $today = Carbon::today();
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayDay = $dayNames[$today->dayOfWeekIso] ?? 'Senin';

        $todayScheds = PiketSchedule::where('academic_year', $academicYear)
            ->where('day', $todayDay)
            ->get();

        $coordinator = $todayScheds->firstWhere('role', 'koordinator')?->teacher_id ?? $teachers->first()->id;

        $journal = PiketJournal::firstOrCreate(
            ['date' => $today->toDateString()],
            [
                'academic_year' => $academicYear,
                'semester' => $semester,
                'coordinator_id' => $coordinator,
                'general_notes' => 'Kegiatan literasi pagi dan pembiasaan sholat dhuha berjalan dengan tertib. Cuaca cerah berawan.',
                'status' => 'berlangsung',
            ]
        );

        foreach ($todayScheds as $sch) {
            PiketAttendance::firstOrCreate(
                [
                    'piket_journal_id' => $journal->id,
                    'teacher_id' => $sch->teacher_id,
                ],
                [
                    'status' => 'hadir',
                    'check_in_time' => '06:45:00',
                    'notes' => $sch->duty_location,
                ]
            );
        }

        // 4. Seed Sample Student Permits for Today
        $studentA = Student::first();
        if ($studentA) {
            PiketStudentPermit::firstOrCreate(
                ['permit_number' => 'IZN-'.date('Ymd').'-001'],
                [
                    'piket_journal_id' => $journal->id,
                    'permit_date' => $today->toDateString(),
                    'student_id' => $studentA->id,
                    'student_name' => $studentA->name,
                    'classroom_id' => $studentA->classroom_id,
                    'permit_type' => 'terlambat',
                    'reason' => 'Ban sepeda motor bocor di perjalanan ke sekolah',
                    'entry_time' => '07:20:00',
                    'period_assigned' => '1',
                    'action_taken' => 'Membaca doa & surat pendek di meja piket',
                    'given_by' => $coordinator,
                    'status' => 'diberikan',
                ]
            );
        }

        // 5. Seed Inval Class for Today
        $subject = Subject::first();
        if ($subject && $classrooms->isNotEmpty()) {
            PiketInvalClass::firstOrCreate(
                [
                    'piket_journal_id' => $journal->id,
                    'classroom_id' => $classrooms->first()->id,
                    'absent_teacher_id' => $teachers->count() > 3 ? $teachers[3]->id : $teachers->first()->id,
                ],
                [
                    'subject_id' => $subject->id,
                    'inval_teacher_id' => $coordinator,
                    'period_start' => 3,
                    'period_end' => 4,
                    'reason' => 'Dinas Luar menghadiri MGMP Kabupaten',
                    'assignment_material' => 'Mengerjakan LKS Bab 3 Halaman 45-48 dikumpulkan di meja guru',
                ]
            );
        }

        // 6. Seed Guest Book
        PiketGuest::firstOrCreate(
            [
                'piket_journal_id' => $journal->id,
                'guest_name' => 'Drs. H. Mulyono',
                'visit_date' => $today->toDateString(),
            ],
            [
                'check_in_time' => '08:30:00',
                'institution' => 'Pengawas SMK Cabdin Kediri',
                'phone' => '081234567899',
                'purpose' => 'Koordinasi supervisi klinis dan kurikulum merdeka',
                'meet_with' => 'Kepala Sekolah & Waka Kurikulum',
                'badge_number' => 'T-01',
                'notes' => 'Diterima di ruang Kepala Sekolah',
                'recorded_by' => $coordinator,
            ]
        );
    }
}
