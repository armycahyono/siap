<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\LessonSchedule;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableItem;
use App\Models\TimetableUpload;
use App\Models\User;

class TimetableSyncService
{
    /**
     * Get Teacher Nickname Mapping to User ID
     */
    public static function getTeacherMapping(): array
    {
        return [
            'MAKMUN' => 50, // MOH. MAKMUN SOLIKHI, S.Pd.
            'ROKHIM' => 31, // ROKHIM, S.Pd.
            'RANI' => 44, // MAHARANI WAHYU ISMAIL, S.Pd
            'DIAH' => 52, // DIAH AYU KUMALASARI, S.Pd.
            'RIMA' => 39, // AYU RIMA WARDHANI, S.Pd.
            'EFENDI' => 20, // EFENDI SAPUTRA
            'RENO' => 34, // RENO AULIYA ANGGRAINI, S.Pd.
            'QURNIA' => 24, // QURNIA NI'MATUL ULFAH, S.Pd
            'EKO' => 37, // EKO AQTHOENA ULFA, S.Pd.
            'UTORO' => 13, // UTORO, S.Fil., S.Pd
            'MIFTAH' => 42, // MIFTAHUR ROHMAH, S.Pd.
            'KARNI' => 5,  // Drs. SUKARNI
            'DITA' => 26, // DITA MAGHFIRATUL MAS'RURIN, S.Pd.
            'KRIS' => 54, // KRISDIANTORO, S.Pd.
            'PUNGKAS' => 14, // ENDANG PUNGKASWATI, S.Pd
            'AGUNG' => 28, // AGUNG PRASETYO, S.Pd.
            'RISNA' => 32, // RISNA HILALIYAH, S.Pd
            'OKKY' => 35, // OKKY WILLYAND SUPRAPTO, S.Kom.
            'YOGA' => 27, // YOGA ANGGORINA KUKUH KURNIAWAN, S.Pd.
            'BUDI' => 30, // ILHAM BUDI SANTOSO, M.Pd.
            'DEVI' => 45, // DEVI AJENG AYU WIBOWO, S.Pd.
            'BAYU' => 40, // BAYU HADI KUNCORO, S.Kom
            'TETUKO' => 49, // TETUKO CAHYO WICAKSONO, A.Md.
            'ADI NUR' => 17, // ADI NURANTO, S.Pd.
            'RUDI' => 57, // RUDI DWI SUSANTO, S.Pd.
            'SUHADAK' => 18, // SUHADAK, ST
            'INDRA' => 29, // INDRA YUSTIAWAN, M.Pd.
            'ELLA' => 33, // LAYLATUL ROCHMAH, S.Pd.I
            'ROFIK' => 43, // MUHAMMAD ROFIK ASHARI, S.Pd.
            'MUNIF' => 53, // MUNIF ARDIANSYAH, S.Pd.
            'AFIF' => 38, // AFIF BEY RIDHOTULLOH, A.Md.
            'LUTFI' => 55, // LUTFI ANDRI SAPUTRO, S.T
            'ALIYA' => 36, // ALIYA NUSAIBA, S.Pd.
            'ILHAM ALI' => 46, // MUCHAMMAD ILHAM ALI NURUDDIN, S.T
            'CANDRA' => 21, // CANDRA TRI SETYONO, S.Pd.
            'LEO' => 47, // LEO HERMAWAN SANTOSO, A.Md.T.
            'AYU' => 25, // AYU DWI WAHYU SAPUTRI, S.Pd
            'SUPRI' => 12, // SUPRIYANTO, ST
            'JUMARI' => 9,  // JUMARI, S.Pd.
            'WARNI' => 6,  // SUWARNI, S.PdI
            'NURMA' => 15, // NURMA FAUZIAH, S.Pd.
        ];
    }

    /**
     * Map Subject Code to Details
     */
    public static function getSubjectDefinitions(): array
    {
        return [
            'PAI' => [
                'name' => 'Pendidikan Agama Islam & Budi Pekerti',
                'category' => 'Umum',
            ],
            'PKN' => [
                'name' => 'Pendidikan Pancasila dan Kewarganegaraan',
                'category' => 'Umum',
            ],
            'BIND' => [
                'name' => 'Bahasa Indonesia',
                'category' => 'Umum',
            ],
            'MTK' => [
                'name' => 'Matematika',
                'category' => 'Umum',
            ],
            'SEJ' => [
                'name' => 'Sejarah Indonesia',
                'category' => 'Umum',
            ],
            'BING' => [
                'name' => 'Bahasa Inggris',
                'category' => 'Umum',
            ],
            'PJOK' => [
                'name' => 'Pendidikan Jasmani, Olahraga & Kesehatan',
                'category' => 'Umum',
            ],
            'B.JAWA' => [
                'name' => 'Bahasa Jawa (Muatan Lokal)',
                'category' => 'Muatan Lokal',
            ],
            'INF' => [
                'name' => 'Informatika',
                'category' => 'Umum',
            ],
            'IPAS' => [
                'name' => 'Projek Ilmu Pengetahuan Alam dan Sosial (IPAS)',
                'category' => 'Umum',
            ],
            'BK' => [
                'name' => 'Bimbingan dan Konseling',
                'category' => 'Bimbingan Konseling',
            ],
            'SR' => [
                'name' => 'Seni Rupa',
                'category' => 'Umum',
            ],
            'PGRI' => [
                'name' => 'Ke-PGRI-an',
                'category' => 'Muatan Lokal',
            ],
            'DDAKL' => [
                'name' => 'Dasar-dasar Program Keahlian AKL',
                'category' => 'Kejuruan',
            ],
            'DDTJKT' => [
                'name' => 'Dasar-dasar Program Keahlian TJKT',
                'category' => 'Kejuruan',
            ],
            'DDTO' => [
                'name' => 'Dasar-dasar Teknik Otomotif',
                'category' => 'Kejuruan',
            ],
            'KONSJUR AK' => [
                'name' => 'Konsentrasi Keahlian Akuntansi',
                'category' => 'Kejuruan',
            ],
            'MAPIL AK' => [
                'name' => 'Mata Pelajaran Pilihan Akuntansi',
                'category' => 'Kejuruan',
            ],
            'KONSJUR TKJ' => [
                'name' => 'Konsentrasi Keahlian TKJ',
                'category' => 'Kejuruan',
            ],
            'MAPIL TKJ' => [
                'name' => 'Mata Pelajaran Pilihan TKJ',
                'category' => 'Kejuruan',
            ],
            'KONSJUR TKR' => [
                'name' => 'Konsentrasi Keahlian TKR',
                'category' => 'Kejuruan',
            ],
            'MAPIL TKR' => [
                'name' => 'Mata Pelajaran Pilihan TKR',
                'category' => 'Kejuruan',
            ],
            'KIK' => [
                'name' => 'Projek Kreatif dan Kewirausahaan (PKK/KIK)',
                'category' => 'Kejuruan',
            ],
        ];
    }

    /**
     * Synchronize Master Classrooms
     */
    public static function syncClassrooms(): array
    {
        $deptTKR = Department::where('code', 'TKR')->first() ?? Department::create(['code' => 'TKR', 'name' => 'Teknik Kendaraan Ringan Otomotif']);
        $deptTKJ = Department::where('code', 'TKJ')->first() ?? Department::create(['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan']);
        $deptAKL = Department::where('code', 'AKL')->first() ?? Department::create(['code' => 'AKL', 'name' => 'Akuntansi dan Keuangan Lembaga']);

        // Nama kanonik sesuai dengan aSc Timetables — menjadi sumber kebenaran tunggal.
        // X AK  => X AKL 1,  X TKJ  => X TKJ 1
        // XI AK => XI AKL 1, XI TKJ => XI TKJ 1
        // XII AK => XII AKL 1
        $classDefinitions = [
            'X AKL 1' => ['grade' => 'X',   'dept' => $deptAKL->id],
            'X TKJ 1' => ['grade' => 'X',   'dept' => $deptTKJ->id],
            'X TKR 1' => ['grade' => 'X',   'dept' => $deptTKR->id],
            'X TKR 2' => ['grade' => 'X',   'dept' => $deptTKR->id],
            'X TKR 3' => ['grade' => 'X',   'dept' => $deptTKR->id],

            'XI AKL 1' => ['grade' => 'XI',  'dept' => $deptAKL->id],
            'XI TKJ 1' => ['grade' => 'XI',  'dept' => $deptTKJ->id],
            'XI TKR 1' => ['grade' => 'XI',  'dept' => $deptTKR->id],
            'XI TKR 2' => ['grade' => 'XI',  'dept' => $deptTKR->id],
            'XI TKR 3' => ['grade' => 'XI',  'dept' => $deptTKR->id],

            'XII AKL 1' => ['grade' => 'XII', 'dept' => $deptAKL->id],
            'XII TKJ 1' => ['grade' => 'XII', 'dept' => $deptTKJ->id],
            'XII TKJ 2' => ['grade' => 'XII', 'dept' => $deptTKJ->id],
            'XII TKR 1' => ['grade' => 'XII', 'dept' => $deptTKR->id],
            'XII TKR 2' => ['grade' => 'XII', 'dept' => $deptTKR->id],
            'XII TKR 3' => ['grade' => 'XII', 'dept' => $deptTKR->id],
            'XII TKR 4' => ['grade' => 'XII', 'dept' => $deptTKR->id],
        ];

        $classrooms = [];
        foreach ($classDefinitions as $name => $meta) {
            $gradeId = match ($meta['grade']) {
                'X' => 1,
                'XI' => 2,
                'XII' => 3,
                default => 1,
            };

            $classroom = Classroom::updateOrCreate(
                ['name' => $name, 'academic_year' => '2026/2027'],
                [
                    'academic_year_id' => 3,
                    'grade_level_id' => $gradeId,
                    'department_id' => $meta['dept'],
                    'is_active' => true,
                ]
            );
            $classrooms[$name] = $classroom;
        }

        return $classrooms;
    }

    /**
     * Synchronize Master Subjects
     */
    public static function syncSubjects(): array
    {
        $subjects = [];
        foreach (self::getSubjectDefinitions() as $code => $info) {
            $subject = Subject::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $info['name'],
                    'category' => $info['category'],
                ]
            );
            $subjects[$code] = $subject;
        }

        return $subjects;
    }

    /**
     * Save Schedule Data Array and Sync Assignments
     */
    public static function processScheduleData(array $scheduleByClass, ?TimetableUpload $upload = null): array
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $upload?->academic_year ?? $setting->academic_year ?? '2026/2027';
        $semester = $upload?->semester ?? $setting->semester ?? 'Ganjil';

        $classrooms = self::syncClassrooms();
        $subjects = self::syncSubjects();
        $teacherMap = self::getTeacherMapping();

        $totalLessons = 0;
        $totalClasses = 0;

        // Sumber data 1 pintu: bersihkan jadwal lama pada tahun ajaran & semester ini
        LessonSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->delete();

        // Collect assignment hours: [teacher_id_subject_id_classroom_id => hours]
        $assignmentHours = [];

        foreach ($scheduleByClass as $className => $days) {
            $classroom = $classrooms[$className] ?? Classroom::where('name', $className)->first();
            if (! $classroom) {
                continue;
            }
            $totalClasses++;

            foreach ($days as $day => $slots) {
                foreach ($slots as $slot) {
                    $subjCode = $slot['subject'];
                    $teacherNick = $slot['teacher'];
                    $startPeriod = $slot['start'];
                    $endPeriod = $slot['end'];

                    $subject = $subjects[$subjCode] ?? Subject::where('code', $subjCode)->first();
                    $teacherId = $teacherMap[$teacherNick] ?? null;

                    if (! $subject || ! $teacherId) {
                        continue;
                    }

                    $times = LessonSchedule::getTimeForPeriod($startPeriod, $endPeriod);

                    LessonSchedule::create([
                        'timetable_upload_id' => $upload?->id,
                        'classroom_id' => $classroom->id,
                        'teacher_id' => $teacherId,
                        'subject_id' => $subject->id,
                        'academic_year' => $academicYear,
                        'semester' => $semester,
                        'day' => $day,
                        'start_period' => $startPeriod,
                        'end_period' => $endPeriod,
                        'start_time' => $times['start_time'],
                        'end_time' => $times['end_time'],
                    ]);

                    $totalLessons++;

                    // Aggregate teaching hours per week
                    $hours = ($endPeriod - $startPeriod) + 1;
                    $key = "{$teacherId}_{$subject->id}_{$classroom->id}";
                    if (! isset($assignmentHours[$key])) {
                        $assignmentHours[$key] = [
                            'teacher_id' => $teacherId,
                            'subject_id' => $subject->id,
                            'classroom_id' => $classroom->id,
                            'hours' => 0,
                        ];
                    }
                    $assignmentHours[$key]['hours'] += $hours;
                }
            }
        }

        // Re-build TeacherSubjectAssignment sepenuhnya dari LessonSchedule
        // (Sumber data hanya 1 pintu: jadwal dari upload PDF)
        TeacherSubjectAssignment::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->delete();

        $rows = LessonSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->selectRaw('teacher_id, subject_id, classroom_id, academic_year, semester, SUM(end_period - start_period + 1) as total_hours')
            ->groupBy('teacher_id', 'subject_id', 'classroom_id', 'academic_year', 'semester')
            ->get();

        foreach ($rows as $row) {
            TeacherSubjectAssignment::create([
                'teacher_id' => $row->teacher_id,
                'subject_id' => $row->subject_id,
                'classroom_id' => $row->classroom_id,
                'academic_year' => $row->academic_year,
                'semester' => $row->semester,
                'hours_per_week' => $row->total_hours,
                'notes' => 'Disinkronkan otomatis dari Jadwal Pelajaran (aSc Timetables)',
            ]);
        }

        if ($upload) {
            $upload->update([
                'total_classes' => $totalClasses,
                'total_lessons' => $totalLessons,
            ]);
        }

        return [
            'total_classes' => $totalClasses,
            'total_lessons' => $totalLessons,
            'total_assignments' => $rows->count(),
        ];
    }

    /**
     * Parse raw timetable matrix into editable translation items (Staging Table)
     */
    public static function populateTranslationItems(array $scheduleByClass, TimetableUpload $upload): array
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $upload->academic_year ?? $setting->academic_year ?? '2026/2027';

        // Ensure master classrooms and subjects exist
        $classrooms = self::syncClassrooms();
        $subjects = self::syncSubjects();
        $teacherMap = self::getTeacherMapping();

        // Clear existing translation items for this upload
        $upload->items()->delete();

        $totalItems = 0;
        $validItems = 0;
        $invalidItems = 0;
        $distinctClasses = [];

        foreach ($scheduleByClass as $className => $days) {
            $classroom = $classrooms[$className]
                ?? Classroom::where('name', $className)->where('academic_year', $academicYear)->where('is_active', true)->first()
                ?? Classroom::where('name', $className)->where('academic_year', $academicYear)->first()
                ?? Classroom::where('name', $className)->first();

            $distinctClasses[$className] = true;

            foreach ($days as $day => $slots) {
                foreach ($slots as $slot) {
                    $subjCode = $slot['subject'] ?? '';
                    $teacherNick = $slot['teacher'] ?? '';
                    $startPeriod = (int) ($slot['start'] ?? 1);
                    $endPeriod = (int) ($slot['end'] ?? 1);

                    $subject = $subjects[$subjCode] ?? Subject::where('code', $subjCode)->first();
                    $teacherId = $teacherMap[$teacherNick] ?? null;
                    $teacher = $teacherId ? User::find($teacherId) : null;

                    $times = LessonSchedule::getTimeForPeriod($startPeriod, $endPeriod);

                    $isValid = true;
                    $statusMessage = null;

                    if (! $classroom) {
                        $isValid = false;
                        $statusMessage = "Rombel '{$className}' belum terdaftar di master kelas.";
                    } elseif (! $subject) {
                        $isValid = false;
                        $statusMessage = "Mata pelajaran '{$subjCode}' belum terdaftar di master mapel.";
                    } elseif (! $teacher) {
                        $isValid = false;
                        $statusMessage = "Guru dengan inisial '{$teacherNick}' belum dipetakan.";
                    }

                    TimetableItem::create([
                        'timetable_upload_id' => $upload->id,
                        'class_name' => $className,
                        'classroom_id' => $classroom?->id,
                        'day' => $day,
                        'start_period' => $startPeriod,
                        'end_period' => $endPeriod,
                        'start_time' => $times['start_time'],
                        'end_time' => $times['end_time'],
                        'subject_code' => $subjCode,
                        'subject_id' => $subject?->id,
                        'teacher_nickname' => $teacherNick,
                        'teacher_id' => $teacher?->id,
                        'is_valid' => $isValid,
                        'status_message' => $statusMessage,
                    ]);

                    $totalItems++;
                    if ($isValid) {
                        $validItems++;
                    } else {
                        $invalidItems++;
                    }
                }
            }
        }

        $upload->update([
            'total_classes' => count($distinctClasses),
            'total_lessons' => $totalItems,
        ]);

        return [
            'total_classes' => count($distinctClasses),
            'total_lessons' => $totalItems,
            'total_valid' => $validItems,
            'total_invalid' => $invalidItems,
        ];
    }

    /**
     * Apply verified translation items directly to live schedule
     */
    public static function applyTranslationToLive(TimetableUpload $upload): array
    {
        $setting = SchoolSetting::getSetting();
        $academicYear = $upload->academic_year ?? $setting->academic_year ?? '2026/2027';
        $semester = $upload->semester ?? $setting->semester ?? 'Ganjil';

        $items = $upload->items()->where('is_valid', true)->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada item hasil terjemahan yang valid untuk diterapkan.',
                'total_classes' => 0,
                'total_lessons' => 0,
            ];
        }

        // 1. Bersihkan jadwal lama pada tahun ajaran & semester ini
        LessonSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->delete();

        $totalLessons = 0;
        $uniqueClassIds = [];

        foreach ($items as $item) {
            LessonSchedule::create([
                'timetable_upload_id' => $upload->id,
                'classroom_id' => $item->classroom_id,
                'teacher_id' => $item->teacher_id,
                'subject_id' => $item->subject_id,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'day' => $item->day,
                'start_period' => $item->start_period,
                'end_period' => $item->end_period,
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
            ]);

            $totalLessons++;
            $uniqueClassIds[$item->classroom_id] = true;
        }

        // 2. Re-build TeacherSubjectAssignment dari LessonSchedule
        TeacherSubjectAssignment::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->delete();

        $rows = LessonSchedule::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->selectRaw('teacher_id, subject_id, classroom_id, academic_year, semester, SUM(end_period - start_period + 1) as total_hours')
            ->groupBy('teacher_id', 'subject_id', 'classroom_id', 'academic_year', 'semester')
            ->get();

        foreach ($rows as $row) {
            TeacherSubjectAssignment::create([
                'teacher_id' => $row->teacher_id,
                'subject_id' => $row->subject_id,
                'classroom_id' => $row->classroom_id,
                'academic_year' => $row->academic_year,
                'semester' => $row->semester,
                'hours_per_week' => $row->total_hours,
                'notes' => 'Disinkronkan dari Hasil Terjemahan aSc Timetables ('.$upload->title.')',
            ]);
        }

        // 3. Update status aktif upload arsip
        TimetableUpload::where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->update(['is_active' => false]);

        $upload->update([
            'is_active' => true,
            'total_classes' => count($uniqueClassIds),
            'total_lessons' => $totalLessons,
        ]);

        return [
            'success' => true,
            'total_classes' => count($uniqueClassIds),
            'total_lessons' => $totalLessons,
            'total_assignments' => $rows->count(),
        ];
    }
}
