<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AcademicYearRolloverLog;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentPromotionController extends Controller
{
    /**
     * Tampilkan halaman pengelolaan kenaikan kelas & kelulusan siswa.
     */
    public function index(Request $request): View
    {
        $setting = SchoolSetting::getSetting();
        $defaultAcademicYear = session('active_academic_year', $setting->academic_year ?? '2026/2027');

        $tab = $request->query('tab', 'rollover');
        if ($tab === 'per_kelas') {
            $tab = 'rollover';
        }
        $sourceAcademicYear = $request->query('source_academic_year', $defaultAcademicYear);

        // Daftar tahun ajaran dari DB, kelas terdaftar, dan tahun aktif
        $academicYears = AcademicYear::orderByDesc('name')->get();
        $dbYears = $academicYears->isNotEmpty() ? $academicYears->pluck('name') : collect();
        $classroomYears = Classroom::select('academic_year')->distinct()->pluck('academic_year');

        $classroomCounts = Classroom::select('academic_year', DB::raw('count(*) as count'))
            ->groupBy('academic_year')
            ->pluck('count', 'academic_year');

        $yearsList = $classroomYears->merge($dbYears)->push($defaultAcademicYear)->filter()->unique()->sortDesc()->values();

        // Target tahun ajaran otomatis 1 tingkat berikutnya (+1 Tahun)
        if (preg_match('/^(\d{4})\/(\d{4})$/', $sourceAcademicYear, $matches)) {
            $nextStart = (int) $matches[1] + 1;
            $nextEnd = (int) $matches[2] + 1;
            $targetAcademicYear = "{$nextStart}/{$nextEnd}";
        } else {
            $targetAcademicYear = $sourceAcademicYear;
        }

        $targetYearsList = $yearsList->push($targetAcademicYear)->unique()->sortDesc()->values();

        // Ambil daftar kelas asal dan kelas tujuan
        $sourceClassrooms = Classroom::where('academic_year', $sourceAcademicYear)
            ->with(['gradeLevel', 'department'])
            ->orderBy('name')
            ->get();

        $targetClassrooms = Classroom::where('academic_year', $targetAcademicYear)
            ->with(['gradeLevel', 'department'])
            ->orderBy('name')
            ->get();

        $gradeLevels = GradeLevel::orderBy('order_index')->get();
        $levelX = $gradeLevels->firstWhere('level', 'X');
        $levelXI = $gradeLevels->firstWhere('level', 'XI');
        $levelXII = $gradeLevels->firstWhere('level', 'XII');

        // Matriks Pemetaan Seluruh Rombel Sekolah (Untuk Rollover Massal)
        $rolloverMatrix = $sourceClassrooms->map(function ($sc) use ($levelXI, $levelXII, $targetClassrooms) {
            $level = strtoupper($sc->gradeLevel?->level ?? '');
            $nameUpper = strtoupper($sc->name);
            $isGrade12 = ($level === 'XII') || str_starts_with($nameUpper, 'XII ') || str_contains($nameUpper, 'KELAS XII') || str_contains($nameUpper, 'XII-');

            $nextGradeLevel = null;
            $suggestedName = null;

            if ($isGrade12) {
                $defaultAction = 'lulus';
            } else {
                $defaultAction = 'naik';
                if ($level === 'X' || str_starts_with($nameUpper, 'X ') || str_contains($nameUpper, 'KELAS X')) {
                    $nextGradeLevel = $levelXI;
                    $suggestedName = preg_replace('/^X\b/i', 'XI', $sc->name);
                } elseif ($level === 'XI' || str_starts_with($nameUpper, 'XI ') || str_contains($nameUpper, 'KELAS XI')) {
                    $nextGradeLevel = $levelXII;
                    $suggestedName = preg_replace('/^XI\b/i', 'XII', $sc->name);
                }
            }

            // Cari apakah kelas tujuan sudah tersedia di target academic year
            $targetClassroom = null;
            if ($suggestedName) {
                $targetClassroom = $targetClassrooms->first(function ($tc) use ($suggestedName) {
                    return strcasecmp(trim($tc->name), trim($suggestedName)) === 0;
                });
            }

            $activeStudentsCount = Student::where('classroom_id', $sc->id)->where('status', 'aktif')->count();

            return [
                'source_classroom' => $sc,
                'is_grade_12' => $isGrade12,
                'default_action' => $defaultAction,
                'suggested_name' => $suggestedName,
                'next_grade_level' => $nextGradeLevel,
                'target_classroom' => $targetClassroom,
                'active_students_count' => $activeStudentsCount,
            ];
        });

        // Seluruh Siswa Aktif di Tahun Ajaran Asal (Untuk Pengecualian Rollover)
        $allActiveStudents = Student::whereIn('classroom_id', $sourceClassrooms->pluck('id'))
            ->where('status', 'aktif')
            ->with(['classroom.gradeLevel', 'classroom.department'])
            ->orderBy('name')
            ->get();

        $allActiveStudentsJson = $allActiveStudents->map(function ($s) {
            $level = strtoupper($s->classroom?->gradeLevel?->level ?? '');
            if (! $level && $s->classroom) {
                $cName = strtoupper($s->classroom->name);
                if (str_starts_with($cName, 'X ') || str_contains($cName, 'KELAS X')) {
                    $level = 'X';
                } elseif (str_starts_with($cName, 'XI ') || str_contains($cName, 'KELAS XI')) {
                    $level = 'XI';
                } elseif (str_starts_with($cName, 'XII ') || str_contains($cName, 'KELAS XII')) {
                    $level = 'XII';
                }
            }

            $nextLevel = ($level === 'X') ? 'XI' : (($level === 'XI') ? 'XII' : 'XII');

            return [
                'id' => (int) $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn ?: '-',
                'classroom_name' => $s->classroom?->name ?? '-',
                'classroom_id' => $s->classroom_id,
                'level' => $level,
                'next_level' => $nextLevel,
                'department_id' => $s->classroom?->department_id,
                'department_name' => $s->classroom?->department?->name ?? '',
            ];
        })->values();

        // Rombel Kandidat Khusus untuk Pengecualian (Korelasi Tingkat & Jurusan yang Logis)
        $candidateClassroomsJson = collect();

        foreach ($targetClassrooms as $tc) {
            $level = strtoupper($tc->gradeLevel?->level ?? '');
            if (! $level) {
                $cName = strtoupper($tc->name);
                if (str_starts_with($cName, 'X ') || str_contains($cName, 'KELAS X')) {
                    $level = 'X';
                } elseif (str_starts_with($cName, 'XI ') || str_contains($cName, 'KELAS XI')) {
                    $level = 'XI';
                } elseif (str_starts_with($cName, 'XII ') || str_contains($cName, 'KELAS XII')) {
                    $level = 'XII';
                }
            }
            $candidateClassroomsJson->push([
                'id' => (string) $tc->id,
                'name' => $tc->name,
                'level' => $level,
                'department_id' => $tc->department_id,
                'department_name' => $tc->department?->name ?? '',
                'is_existing' => true,
            ]);
        }

        foreach ($sourceClassrooms as $sc) {
            $scLevel = strtoupper($sc->gradeLevel?->level ?? '');
            if (! $scLevel) {
                $cName = strtoupper($sc->name);
                if (str_starts_with($cName, 'X ') || str_contains($cName, 'KELAS X')) {
                    $scLevel = 'X';
                } elseif (str_starts_with($cName, 'XI ') || str_contains($cName, 'KELAS XI')) {
                    $scLevel = 'XI';
                } elseif (str_starts_with($cName, 'XII ') || str_contains($cName, 'KELAS XII')) {
                    $scLevel = 'XII';
                }
            }

            // Proyeksi kelas yang sama (Tinggal Kelas)
            $sameName = $sc->name;
            if (! $candidateClassroomsJson->contains('name', $sameName)) {
                $candidateClassroomsJson->push([
                    'id' => 'auto_create:'.$sameName,
                    'name' => $sameName,
                    'level' => $scLevel,
                    'department_id' => $sc->department_id,
                    'department_name' => $sc->department?->name ?? '',
                    'is_existing' => false,
                ]);
            }

            // Proyeksi kelas tingkat berikutnya (Naik Beda Rombel)
            if ($scLevel === 'X' || $scLevel === 'XI') {
                $nextLevel = ($scLevel === 'X') ? 'XI' : 'XII';
                $nextName = preg_replace('/^'.preg_quote($scLevel, '/').'\b/i', $nextLevel, $sc->name);
                if (! $candidateClassroomsJson->contains('name', $nextName)) {
                    $candidateClassroomsJson->push([
                        'id' => 'auto_create:'.$nextName,
                        'name' => $nextName,
                        'level' => $nextLevel,
                        'department_id' => $sc->department_id,
                        'department_name' => $sc->department?->name ?? '',
                        'is_existing' => false,
                    ]);
                }
            }
        }

        // Riwayat Rollover (Untuk Fitur Rollback / Batalkan Kenaikan)
        $rolloverLogs = AcademicYearRolloverLog::with(['executor', 'rollbackExecutor'])
            ->latest()
            ->limit(5)
            ->get();

        // Data untuk Tab Pindah Rombel Cepat
        $transferSourceClassroomId = $request->query('transfer_source_classroom_id');
        $transferStudents = collect();
        $transferSourceClassroom = null;

        if ($transferSourceClassroomId) {
            $transferSourceClassroom = Classroom::find($transferSourceClassroomId);
            if ($transferSourceClassroom) {
                $transferStudents = Student::where('classroom_id', $transferSourceClassroomId)
                    ->where('status', 'aktif')
                    ->orderBy('name')
                    ->get();
            }
        }

        // Data untuk Tab Kenaikan Per Kelas (Model Lama)
        $sourceClassroomId = $request->query('source_classroom_id');
        $sourceClassroom = null;
        $students = collect();
        $isGrade12 = false;
        $suggestedTargetClassroom = null;

        if ($sourceClassroomId) {
            $sourceClassroom = Classroom::with(['gradeLevel', 'department', 'homeroomTeacher'])->find($sourceClassroomId);
            if ($sourceClassroom) {
                $students = Student::where('classroom_id', $sourceClassroomId)->where('status', 'aktif')->orderBy('name')->get();
                $level = strtoupper($sourceClassroom->gradeLevel?->level ?? '');
                $nameUpper = strtoupper($sourceClassroom->name);
                $isGrade12 = ($level === 'XII') || str_starts_with($nameUpper, 'XII ') || str_contains($nameUpper, 'KELAS XII') || str_contains($nameUpper, 'XII-');

                if (! $isGrade12) {
                    $currentPrefix = str_starts_with($nameUpper, 'X ') ? 'X' : (str_starts_with($nameUpper, 'XI ') ? 'XI' : '');
                    $nextPrefix = ($currentPrefix === 'X') ? 'XI' : (($currentPrefix === 'XI') ? 'XII' : '');
                    if ($nextPrefix && $currentPrefix) {
                        $predictedName = preg_replace('/^'.preg_quote($currentPrefix, '/').'\b/', $nextPrefix, $sourceClassroom->name);
                        $suggestedTargetClassroom = $targetClassrooms->first(fn ($c) => strcasecmp(trim($c->name), trim($predictedName)) === 0);
                    }
                }
            }
        }

        // Cek apakah di target academic year sudah ada siswa aktif (Peringatan Guard)
        $targetYearActiveStudentsCount = Student::whereHas('classroom', fn ($q) => $q->where('academic_year', $targetAcademicYear))
            ->where('status', 'aktif')
            ->count();

        return view('master.students.promotions', compact(
            'tab',
            'yearsList',
            'classroomCounts',
            'targetYearsList',
            'sourceAcademicYear',
            'targetAcademicYear',
            'sourceClassrooms',
            'targetClassrooms',
            'rolloverMatrix',
            'allActiveStudents',
            'allActiveStudentsJson',
            'candidateClassroomsJson',
            'rolloverLogs',
            'targetYearActiveStudentsCount',
            'transferSourceClassroomId',
            'transferSourceClassroom',
            'transferStudents',
            'sourceClassroomId',
            'sourceClassroom',
            'students',
            'isGrade12',
            'suggestedTargetClassroom'
        ));
    }

    /**
     * Eksekusi Transisi Tahun Ajaran Massal 1-Sekolah Sekaligus (Rollover Wizard).
     */
    public function executeRollover(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year' => 'required|string',
            'target_academic_year' => 'required|string',
            'classroom_mappings' => 'required|array',
            'classroom_mappings.*' => 'nullable|string', // target classroom ID or 'auto_create' or 'lulus'
            'auto_create_classrooms' => 'nullable|boolean',
            'exceptions' => 'nullable|array',
            'exceptions.*.action' => 'required|in:tinggal,pindah,mengulang,mutasi,keluar',
            'exceptions.*.target_classroom_id' => 'nullable|string',
            'exceptions.*.notes' => 'nullable|string|max:255',
        ]);

        $sourceAcademicYear = $validated['source_academic_year'];
        $targetAcademicYear = $validated['target_academic_year'];
        $mappings = $validated['classroom_mappings'];
        $exceptions = $validated['exceptions'] ?? [];

        // Pastikan Tahun Ajaran Tujuan terdaftar di tabel academic_years (jika belum ada, buat otomatis)
        AcademicYear::firstOrCreate(
            ['name' => $targetAcademicYear],
            [
                'semester' => 'Ganjil',
                'is_active' => false,
                'description' => 'Dibuat otomatis oleh Sistem Kenaikan Kelas',
            ]
        );

        $sourceClassrooms = Classroom::where('academic_year', $sourceAcademicYear)->get()->keyBy('id');
        $gradeLevels = GradeLevel::all()->keyBy('level');

        $promotedCount = 0;
        $graduatedCount = 0;
        $retainedCount = 0;
        $transferredCount = 0;
        $mutatedCount = 0;

        $batchCode = 'ROLLOVER-'.date('YmdHis').'-'.strtoupper(Str::random(4));
        $studentSnapshots = [];

        DB::transaction(function () use (
            $sourceAcademicYear,
            $targetAcademicYear,
            $mappings,
            $exceptions,
            $sourceClassrooms,
            $gradeLevels,
            $batchCode,
            &$studentSnapshots,
            &$promotedCount,
            &$graduatedCount,
            &$retainedCount,
            &$transferredCount,
            &$mutatedCount
        ) {
            // 1. Buat Rombel Tujuan Otomatis jika admin memilih 'auto_create'
            $resolvedClassroomMap = [];
            foreach ($mappings as $sourceClassId => $targetVal) {
                $sourceClass = $sourceClassrooms->get($sourceClassId);
                if (! $sourceClass) {
                    continue;
                }

                if ($targetVal === 'lulus') {
                    $resolvedClassroomMap[$sourceClassId] = 'lulus';

                    continue;
                }

                if ($targetVal === 'auto_create') {
                    // Prediksi nama kelas baru dan levelnya
                    $currentLevel = strtoupper($sourceClass->gradeLevel?->level ?? '');
                    $nextLevel = ($currentLevel === 'X') ? 'XI' : (($currentLevel === 'XI') ? 'XII' : 'XII');
                    $nextGradeLevel = $gradeLevels->get($nextLevel);

                    $newName = preg_replace('/^'.$currentLevel.'\b/i', $nextLevel, $sourceClass->name);

                    // Cari atau buat kelas di tahun ajaran baru
                    $newClassroom = Classroom::firstOrCreate([
                        'name' => $newName,
                        'academic_year' => $targetAcademicYear,
                    ], [
                        'grade_level_id' => $nextGradeLevel?->id,
                        'department_id' => $sourceClass->department_id,
                        'is_active' => true,
                    ]);

                    $resolvedClassroomMap[$sourceClassId] = $newClassroom->id;
                } elseif (is_numeric($targetVal)) {
                    $resolvedClassroomMap[$sourceClassId] = (int) $targetVal;
                }
            }

            // Helper untuk menyelesaikan ID kelas pengecualian (bisa numeric ID atau 'auto_create:NamaKelas')
            $resolveClassroom = function (?string $val, ?Classroom $fallbackSource = null) use ($targetAcademicYear, $gradeLevels): ?int {
                if (empty($val)) {
                    return null;
                }
                if (is_numeric($val)) {
                    return (int) $val;
                }
                if (str_starts_with($val, 'auto_create:')) {
                    $className = trim(substr($val, strlen('auto_create:')));
                    $existing = Classroom::where('name', $className)->where('academic_year', $targetAcademicYear)->first();
                    if ($existing) {
                        return $existing->id;
                    }

                    $cNameUpper = strtoupper($className);
                    $lvl = 'X';
                    if (str_starts_with($cNameUpper, 'XII ') || str_contains($cNameUpper, 'KELAS XII')) {
                        $lvl = 'XII';
                    } elseif (str_starts_with($cNameUpper, 'XI ') || str_contains($cNameUpper, 'KELAS XI')) {
                        $lvl = 'XI';
                    }

                    $newClass = Classroom::firstOrCreate([
                        'name' => $className,
                        'academic_year' => $targetAcademicYear,
                    ], [
                        'grade_level_id' => $gradeLevels->get($lvl)?->id ?? $fallbackSource?->grade_level_id,
                        'department_id' => $fallbackSource?->department_id,
                        'is_active' => true,
                    ]);

                    return $newClass->id;
                }

                return null;
            };

            // 2. Ambil seluruh siswa aktif di rombel asal
            $students = Student::whereIn('classroom_id', array_keys($resolvedClassroomMap))
                ->where('status', 'aktif')
                ->get();

            $targetAy = AcademicYear::where('name', $targetAcademicYear)->first();
            $targetAyId = $targetAy?->id;

            // 3. Proses setiap siswa
            foreach ($students as $student) {
                // Simpan snapshot keadaan saat ini (untuk fitur Rollback / Undo)
                $studentSnapshots[] = [
                    'student_id' => $student->id,
                    'old_classroom_id' => $student->classroom_id,
                    'old_status' => $student->status,
                    'old_is_active' => (bool) $student->is_active,
                    'old_graduation_year' => $student->graduation_year,
                    'old_last_classroom_name' => $student->last_classroom_name,
                ];

                $sourceClass = $sourceClassrooms->get($student->classroom_id);
                $sourceClassName = $sourceClass?->name ?? 'Kelas Asal';

                // Periksa apakah siswa memiliki pengecualian
                if (isset($exceptions[$student->id])) {
                    $exc = $exceptions[$student->id];
                    $action = $exc['action'];
                    $targetClassId = ! empty($exc['target_classroom_id']) ? $exc['target_classroom_id'] : null;

                    switch ($action) {
                        case 'tinggal':
                        case 'mengulang':
                            // Siswa tinggal kelas di tingkat yang sama
                            $targetResolvedId = $resolveClassroom($targetClassId, $sourceClass);
                            $finalClassId = $targetResolvedId ?: $student->classroom_id;

                            $student->update([
                                'classroom_id' => $finalClassId,
                                'status' => 'aktif',
                                'is_active' => true,
                                'last_classroom_name' => $sourceClassName,
                            ]);

                            if ($targetAyId && $finalClassId) {
                                $cls = Classroom::find($finalClassId);
                                StudentClassroom::updateOrCreate([
                                    'student_id' => $student->id,
                                    'academic_year_id' => $targetAyId,
                                ], [
                                    'classroom_id' => $finalClassId,
                                    'status' => 'aktif',
                                    'is_repeating' => true,
                                    'academic_year_name' => $targetAcademicYear,
                                    'classroom_name' => $cls?->name,
                                    'notes' => 'Tinggal kelas / mengulang',
                                ]);
                            }

                            $retainedCount++;
                            break;

                        case 'pindah':
                            // Naik tapi ke rombel tujuan berbeda
                            $targetResolvedId = $resolveClassroom($targetClassId, $sourceClass);
                            if ($targetResolvedId) {
                                $student->update([
                                    'classroom_id' => $targetResolvedId,
                                    'status' => 'aktif',
                                    'is_active' => true,
                                    'last_classroom_name' => $sourceClassName,
                                ]);

                                if ($targetAyId) {
                                    $cls = Classroom::find($targetResolvedId);
                                    StudentClassroom::updateOrCreate([
                                        'student_id' => $student->id,
                                        'academic_year_id' => $targetAyId,
                                    ], [
                                        'classroom_id' => $targetResolvedId,
                                        'status' => 'aktif',
                                        'is_repeating' => false,
                                        'academic_year_name' => $targetAcademicYear,
                                        'classroom_name' => $cls?->name,
                                        'notes' => 'Pindah rombel saat kenaikan',
                                    ]);
                                }

                                $transferredCount++;
                            }
                            break;

                        case 'mutasi':
                        case 'keluar':
                            $student->update([
                                'classroom_id' => null,
                                'status' => $action,
                                'is_active' => false,
                                'last_classroom_name' => $sourceClassName,
                            ]);

                            if ($targetAyId) {
                                StudentClassroom::updateOrCreate([
                                    'student_id' => $student->id,
                                    'academic_year_id' => $targetAyId,
                                ], [
                                    'classroom_id' => null,
                                    'status' => $action,
                                    'is_repeating' => false,
                                    'academic_year_name' => $targetAcademicYear,
                                    'classroom_name' => $sourceClassName,
                                    'notes' => 'Mutasi / Keluar saat kenaikan',
                                ]);
                            }

                            $mutatedCount++;
                            break;
                    }

                    continue;
                }

                // Jika tidak ada pengecualian, ikuti pemetaan rombel otomatis
                $targetMap = $resolvedClassroomMap[$student->classroom_id] ?? null;

                if ($targetMap === 'lulus') {
                    // Kelas XII Lulus menjadi Alumni
                    $student->update([
                        'classroom_id' => null,
                        'status' => 'lulus',
                        'is_active' => false,
                        'last_classroom_name' => $sourceClassName,
                        'graduation_year' => $targetAcademicYear,
                    ]);

                    if ($targetAyId) {
                        StudentClassroom::updateOrCreate([
                            'student_id' => $student->id,
                            'academic_year_id' => $targetAyId,
                        ], [
                            'classroom_id' => null,
                            'status' => 'lulus',
                            'is_repeating' => false,
                            'academic_year_name' => $targetAcademicYear,
                            'classroom_name' => $sourceClassName,
                            'notes' => 'Lulus tingkat akhir (Alumni)',
                        ]);
                    }

                    $graduatedCount++;
                } elseif (is_numeric($targetMap)) {
                    // Siswa naik kelas ke rombel tujuan
                    $student->update([
                        'classroom_id' => $targetMap,
                        'status' => 'aktif',
                        'is_active' => true,
                        'last_classroom_name' => $sourceClassName,
                    ]);

                    if ($targetAyId) {
                        $cls = Classroom::find((int) $targetMap);
                        StudentClassroom::updateOrCreate([
                            'student_id' => $student->id,
                            'academic_year_id' => $targetAyId,
                        ], [
                            'classroom_id' => (int) $targetMap,
                            'status' => 'aktif',
                            'is_repeating' => false,
                            'academic_year_name' => $targetAcademicYear,
                            'classroom_name' => $cls?->name,
                        ]);
                    }

                    $promotedCount++;
                }
            }

            // 4. Catat Log Transisi untuk Keamanan & Rollback
            AcademicYearRolloverLog::create([
                'batch_code' => $batchCode,
                'source_academic_year' => $sourceAcademicYear,
                'target_academic_year' => $targetAcademicYear,
                'executed_by' => auth()->id(),
                'summary' => [
                    'promoted' => $promotedCount,
                    'graduated' => $graduatedCount,
                    'retained' => $retainedCount,
                    'transferred' => $transferredCount,
                    'mutated' => $mutatedCount,
                    'total_affected' => count($studentSnapshots),
                ],
                'student_snapshots' => $studentSnapshots,
                'is_rolled_back' => false,
            ]);
        });

        $msg = "Transisi Tahun Ajaran ({$sourceAcademicYear} ke {$targetAcademicYear}) berhasil diproses secara aman! "
            ."{$promotedCount} siswa naik kelas, {$graduatedCount} siswa lulus (alumni), "
            ."{$transferredCount} siswa pindah rombel khusus, {$retainedCount} siswa tinggal kelas/mengulang.";

        return redirect()->route('students.promotions.index', ['tab' => 'rollover'])
            ->with('success', $msg);
    }

    /**
     * Batalkan Kenaikan Kelas (Rollback Undo) ke Posisi Awal.
     */
    public function rollbackRollover(AcademicYearRolloverLog $log): RedirectResponse
    {
        if ($log->is_rolled_back) {
            return back()->with('error', 'Transisi kenaikan kelas ini sudah pernah dibatalkan (rolled back) sebelumnya.');
        }

        DB::transaction(function () use ($log) {
            $snapshots = $log->student_snapshots;

            foreach ($snapshots as $snap) {
                $student = Student::find($snap['student_id']);
                if (! $student) {
                    continue;
                }

                $student->update([
                    'classroom_id' => $snap['old_classroom_id'],
                    'status' => $snap['old_status'],
                    'is_active' => $snap['old_is_active'],
                    'graduation_year' => $snap['old_graduation_year'],
                    'last_classroom_name' => $snap['old_last_classroom_name'],
                ]);
            }

            // Hapus atau reset riwayat student_classrooms pada target_academic_year yang dibuat oleh rollover ini
            $targetAy = AcademicYear::where('name', $log->target_academic_year)->first();
            if ($targetAy) {
                $affectedStudentIds = collect($snapshots)->pluck('student_id')->all();
                StudentClassroom::where('academic_year_id', $targetAy->id)
                    ->whereIn('student_id', $affectedStudentIds)
                    ->delete();
            }

            $log->update([
                'is_rolled_back' => true,
                'rolled_back_at' => now(),
                'rolled_back_by' => auth()->id(),
            ]);
        });

        return redirect()->route('students.promotions.index', ['tab' => 'rollover'])
            ->with('success', "Proses kenaikan kelas (Batch {$log->batch_code}) berhasil dibatalkan (Rollback)! Seluruh siswa telah dikembalikan ke kelas dan status semula.");
    }

    /**
     * Proses Pindah Rombel Cepat (Multi-Select Transfer Tool).
     */
    public function transferProcess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_classroom_id' => 'required|exists:classrooms,id',
            'target_classroom_id' => 'required|exists:classrooms,id|different:source_classroom_id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|exists:students,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $sourceClassroom = Classroom::findOrFail($validated['source_classroom_id']);
        $targetClassroom = Classroom::findOrFail($validated['target_classroom_id']);
        $studentIds = $validated['student_ids'];

        DB::transaction(function () use ($studentIds, $sourceClassroom, $targetClassroom) {
            Student::whereIn('id', $studentIds)->update([
                'classroom_id' => $targetClassroom->id,
                'status' => 'aktif',
                'is_active' => true,
                'last_classroom_name' => $sourceClassroom->name,
            ]);

            $targetAy = AcademicYear::where('name', $targetClassroom->academic_year)->first();
            if ($targetAy) {
                foreach ($studentIds as $sid) {
                    StudentClassroom::updateOrCreate([
                        'student_id' => $sid,
                        'academic_year_id' => $targetAy->id,
                    ], [
                        'classroom_id' => $targetClassroom->id,
                        'status' => 'aktif',
                        'is_repeating' => false,
                        'academic_year_name' => $targetClassroom->academic_year,
                        'classroom_name' => $targetClassroom->name,
                        'notes' => 'Pindah rombel cepat',
                    ]);
                }
            }
        });

        $count = count($studentIds);

        return redirect()->route('students.promotions.index', [
            'tab' => 'transfer',
            'transfer_source_classroom_id' => $sourceClassroom->id,
        ])->with('success', "Berhasil memindahkan {$count} siswa dari kelas '{$sourceClassroom->name}' ke kelas '{$targetClassroom->name}'.");
    }

    /**
     * Model Kenaikan Per Kelas (Kompatibilitas Model Lama).
     */
    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_classroom_id' => 'required|exists:classrooms,id',
            'target_academic_year' => 'nullable|string|max:30',
            'students' => 'required|array|min:1',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.action' => 'required|in:naik,pindah,tinggal,lulus,mengulang,mutasi,keluar',
            'students.*.target_classroom_id' => 'nullable|exists:classrooms,id',
            'students.*.graduation_year' => 'nullable|string|max:30',
        ]);

        $sourceClassroom = Classroom::findOrFail($validated['source_classroom_id']);
        $defaultGraduationYear = $request->input('bulk_graduation_year') ?: ($validated['target_academic_year'] ?: date('Y'));

        $promotedCount = 0;
        $graduatedCount = 0;
        $retainedCount = 0;
        $mutatedCount = 0;

        $targetAy = ! empty($validated['target_academic_year'])
            ? AcademicYear::where('name', $validated['target_academic_year'])->first()
            : (AcademicYear::getActive() ?? AcademicYear::where('name', $sourceClassroom->academic_year)->first());
        $targetAyId = $targetAy?->id;
        $targetAyName = $targetAy?->name ?? $validated['target_academic_year'];

        DB::transaction(function () use (
            $validated,
            $sourceClassroom,
            $defaultGraduationYear,
            $targetAyId,
            $targetAyName,
            &$promotedCount,
            &$graduatedCount,
            &$retainedCount,
            &$mutatedCount
        ) {
            foreach ($validated['students'] as $item) {
                $student = Student::find($item['student_id']);
                if (! $student) {
                    continue;
                }

                $action = $item['action'];
                $targetClassroomId = $item['target_classroom_id'] ?? null;
                $gradYear = ! empty($item['graduation_year']) ? $item['graduation_year'] : $defaultGraduationYear;

                switch ($action) {
                    case 'naik':
                    case 'pindah':
                        if ($targetClassroomId) {
                            $student->update([
                                'classroom_id' => $targetClassroomId,
                                'status' => 'aktif',
                                'is_active' => true,
                                'last_classroom_name' => $sourceClassroom->name,
                            ]);

                            if ($targetAyId) {
                                $cls = Classroom::find($targetClassroomId);
                                StudentClassroom::updateOrCreate([
                                    'student_id' => $student->id,
                                    'academic_year_id' => $targetAyId,
                                ], [
                                    'classroom_id' => $targetClassroomId,
                                    'status' => 'aktif',
                                    'is_repeating' => false,
                                    'academic_year_name' => $targetAyName,
                                    'classroom_name' => $cls?->name,
                                    'notes' => 'Kenaikan kelas manual',
                                ]);
                            }

                            $promotedCount++;
                        }
                        break;

                    case 'tinggal':
                    case 'mengulang':
                        $targetClass = $targetClassroomId ?: $sourceClassroom->id;
                        $student->update([
                            'classroom_id' => $targetClass,
                            'status' => 'aktif',
                            'is_active' => true,
                            'last_classroom_name' => $sourceClassroom->name,
                        ]);

                        if ($targetAyId) {
                            $cls = Classroom::find($targetClass);
                            StudentClassroom::updateOrCreate([
                                'student_id' => $student->id,
                                'academic_year_id' => $targetAyId,
                            ], [
                                'classroom_id' => $targetClass,
                                'status' => 'aktif',
                                'is_repeating' => true,
                                'academic_year_name' => $targetAyName,
                                'classroom_name' => $cls?->name,
                                'notes' => 'Tinggal kelas / mengulang',
                            ]);
                        }

                        $retainedCount++;
                        break;

                    case 'lulus':
                        $student->update([
                            'classroom_id' => null,
                            'status' => 'lulus',
                            'is_active' => false,
                            'last_classroom_name' => $sourceClassroom->name,
                            'graduation_year' => $gradYear,
                        ]);

                        if ($targetAyId) {
                            StudentClassroom::updateOrCreate([
                                'student_id' => $student->id,
                                'academic_year_id' => $targetAyId,
                            ], [
                                'classroom_id' => null,
                                'status' => 'lulus',
                                'is_repeating' => false,
                                'academic_year_name' => $targetAyName,
                                'classroom_name' => $sourceClassroom->name,
                                'notes' => 'Lulus tingkat akhir (Alumni)',
                            ]);
                        }

                        $graduatedCount++;
                        break;

                    case 'mutasi':
                    case 'keluar':
                        $student->update([
                            'classroom_id' => null,
                            'status' => $action,
                            'is_active' => false,
                            'last_classroom_name' => $sourceClassroom->name,
                        ]);

                        if ($targetAyId) {
                            StudentClassroom::updateOrCreate([
                                'student_id' => $student->id,
                                'academic_year_id' => $targetAyId,
                            ], [
                                'classroom_id' => null,
                                'status' => $action,
                                'is_repeating' => false,
                                'academic_year_name' => $targetAyName,
                                'classroom_name' => $sourceClassroom->name,
                                'notes' => 'Mutasi / keluar saat penataan',
                            ]);
                        }

                        $mutatedCount++;
                        break;
                }
            }
        });

        $messageParts = [];
        if ($promotedCount > 0) {
            $messageParts[] = "{$promotedCount} siswa berhasil naik kelas/pindah rombel";
        }
        if ($graduatedCount > 0) {
            $messageParts[] = "{$graduatedCount} siswa berhasil lulus (dialihkan ke Data Alumni)";
        }
        if ($retainedCount > 0) {
            $messageParts[] = "{$retainedCount} siswa tercatat tinggal kelas / mengulang";
        }
        if ($mutatedCount > 0) {
            $messageParts[] = "{$mutatedCount} siswa dimutasikan/keluar";
        }

        $summary = count($messageParts) > 0 ? implode(', ', $messageParts) : 'Tidak ada perubahan siswa.';

        return redirect()->route('students.promotions.index', [
            'tab' => 'per_kelas',
            'source_academic_year' => $sourceClassroom->academic_year,
            'source_classroom_id' => $sourceClassroom->id,
            'target_academic_year' => $validated['target_academic_year'],
        ])->with('success', "Kenaikan kelas selesai diproses: {$summary}.");
    }
}
