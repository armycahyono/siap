<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

class HomeroomImportService extends BaseExcelImportService
{
    /**
     * Import data wali kelas dari file Excel (.xlsx) atau CSV (.csv).
     *
     * @return array{success: bool, imported_count: int, updated: array<string>, errors: array<string>}
     */
    public function import(string $filePath, string $extension, string $academicYear): array
    {
        $rows = $this->parseFile($filePath, $extension);

        if (empty($rows)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'updated' => [],
                'errors' => ['File kosong atau format data tidak dapat dibaca.'],
            ];
        }

        // Ambil semua guru aktif dan calon wali kelas
        $allTeachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah']))
            ->operationalPersonnel()
            ->where('is_active', true)
            ->get();

        // Ambil kelas pada tahun ajaran yang dipilih
        $classrooms = Classroom::where('academic_year', $academicYear)->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::all();
        }

        // Deteksi baris header jika ada
        $firstRow = $rows[0];
        $headerIndices = $this->detectHeaderColumns($firstRow);

        $startIndex = 0;
        if ($headerIndices['has_header']) {
            $startIndex = 1;
            $colClass = $headerIndices['class_col'];
            $colTeacher = $headerIndices['teacher_col'];
            $colNigs = $headerIndices['nigs_col'];
        } else {
            $colClass = 0;
            $colTeacher = 1;
            $colNigs = 2;
        }

        $importedCount = 0;
        $updatedList = [];
        $errorList = [];

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rawClass = $row[$colClass] ?? '';
            $rawTeacher = $row[$colTeacher] ?? '';
            $rawNigs = isset($row[$colNigs]) ? trim($row[$colNigs]) : '';

            if (empty(trim($rawClass)) && empty(trim($rawTeacher))) {
                continue;
            }

            $cleanClassName = $this->cleanClassroomName($rawClass);
            if (empty($cleanClassName)) {
                continue;
            }

            // Cari Classroom
            $classroom = $this->findClassroom($cleanClassName, $classrooms);
            if (! $classroom) {
                $errorList[] = 'Baris '.($i + 1).": Kelas '{$rawClass}' tidak ditemukan di sistem.";

                continue;
            }

            $cleanTeacherName = $this->cleanTeacherName($rawTeacher);
            if (empty($cleanTeacherName) && empty($rawNigs)) {
                // Jika sengaja dikosongkan, reset wali kelas
                $classroom->update(['homeroom_teacher_id' => null]);
                $updatedList[] = "{$classroom->name}: (Dikosongkan)";
                $importedCount++;

                continue;
            }

            // Cari Guru
            $teacher = $this->findTeacher($cleanTeacherName, $rawNigs, $allTeachers);
            if (! $teacher) {
                $errorList[] = 'Baris '.($i + 1).": Guru '{$rawTeacher}' untuk kelas {$classroom->name} tidak ditemukan.";

                continue;
            }

            $classroom->update(['homeroom_teacher_id' => $teacher->id]);
            $updatedList[] = "{$classroom->name} &rarr; {$teacher->name}";
            $importedCount++;
        }

        return [
            'success' => $importedCount > 0,
            'imported_count' => $importedCount,
            'updated' => $updatedList,
            'errors' => $errorList,
        ];
    }

    /**
     * Deteksi otomatis kolom header pada baris pertama.
     *
     * @param  array<int, string>  $headerRow
     * @return array{has_header: bool, class_col: int, teacher_col: int, nigs_col: int}
     */
    protected function detectHeaderColumns(array $headerRow): array
    {
        $classCol = -1;
        $teacherCol = -1;
        $nigsCol = -1;

        foreach ($headerRow as $idx => $cell) {
            $lower = strtolower(trim($cell));
            if ($classCol === -1 && (str_contains($lower, 'kelas') || str_contains($lower, 'rombel'))) {
                $classCol = $idx;
            } elseif ($teacherCol === -1 && (str_contains($lower, 'wali') || str_contains($lower, 'guru') || (str_contains($lower, 'nama') && ! str_contains($lower, 'kelas')))) {
                $teacherCol = $idx;
            } elseif ($nigsCol === -1 && (str_contains($lower, 'nigs') || str_contains($lower, 'nip') || str_contains($lower, 'nuptk'))) {
                $nigsCol = $idx;
            }
        }

        $hasHeader = ($classCol !== -1 && $teacherCol !== -1);

        return [
            'has_header' => $hasHeader,
            'class_col' => $hasHeader ? $classCol : 0,
            'teacher_col' => $hasHeader ? $teacherCol : 1,
            'nigs_col' => $nigsCol !== -1 ? $nigsCol : 2,
        ];
    }

    /**
     * Membersihkan nama rombel dari prefix kata 'Wali Kelas', 'Rombel', titik dua, spasi berlebih.
     */
    public function cleanClassroomName(string $raw): string
    {
        $clean = preg_replace('/^(wali\s+kelas|wali|kelas|rombel)\s+/i', '', trim($raw));
        $clean = trim($clean, " :-\t\n\r");
        // Alias singkatan kejuruan misal: XI AK 1 -> XI AKL 1
        $clean = preg_replace('/\bAK\b/i', 'AKL', $clean);

        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Cari Classroom berdasarkan nama bersih.
     *
     * @param  Collection<int, Classroom>  $classrooms
     */
    protected function findClassroom(string $cleanName, Collection $classrooms): ?Classroom
    {
        // 1. Exact match (case insensitive)
        $match = $classrooms->first(fn ($c) => strcasecmp($c->name, $cleanName) === 0);
        if ($match) {
            return $match;
        }

        // 2. Match without spaces (e.g. "XTKR1" vs "X TKR 1")
        $noSpaceTarget = str_replace(' ', '', strtolower($cleanName));
        $match = $classrooms->first(fn ($c) => str_replace(' ', '', strtolower($c->name)) === $noSpaceTarget);
        if ($match) {
            return $match;
        }

        // 3. Like match
        return $classrooms->first(fn ($c) => str_contains(strtolower($c->name), strtolower($cleanName)) || str_contains(strtolower($cleanName), strtolower($c->name)));
    }
}
