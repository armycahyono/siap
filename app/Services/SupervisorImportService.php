<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\TeacherSupervisorAssignment;
use App\Models\User;

class SupervisorImportService extends BaseExcelImportService
{
    /**
     * Import data tim supervisor dari file Excel (.xlsx) atau CSV (.csv).
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

        // Ambil semua guru aktif
        $allTeachers = User::operationalPersonnel()->where('is_active', true)->get();
        $supervisorRole = Role::where('slug', 'supervisor')->first();

        if (! $supervisorRole) {
            return [
                'success' => false,
                'imported_count' => 0,
                'updated' => [],
                'errors' => ['Peran supervisor tidak ditemukan dalam sistem.'],
            ];
        }

        $firstRow = $rows[0];
        $headerIndices = $this->detectHeaderColumns($firstRow);

        $startIndex = $headerIndices['has_header'] ? 1 : 0;
        $colTeacher = $headerIndices['teacher_col'];
        $colNigs = $headerIndices['nigs_col'];
        $colPos = $headerIndices['pos_col'];

        $importedCount = 0;
        $updatedList = [];
        $errorList = [];

        $academicYearId = AcademicYear::where('name', $academicYear)->value('id');

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rawTeacher = $row[$colTeacher] ?? '';
            $rawNigs = isset($row[$colNigs]) ? trim($row[$colNigs]) : '';
            $rawPos = isset($row[$colPos]) ? trim($row[$colPos]) : '';

            if (empty(trim($rawTeacher)) && empty($rawNigs)) {
                continue;
            }

            $cleanTeacher = $this->cleanTeacherName($rawTeacher);
            $teacher = $this->findTeacher($cleanTeacher, $rawNigs, $allTeachers);

            if (! $teacher) {
                $errorList[] = 'Baris '.($i + 1).": Guru '{$rawTeacher}' tidak ditemukan di database.";

                continue;
            }

            // Simpan ke TeacherSupervisorAssignment untuk tahun pelajaran terkait
            $posTitle = ! empty($rawPos) ? $rawPos : 'Supervisor';
            TeacherSupervisorAssignment::updateOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'academic_year' => $academicYear,
                ],
                [
                    'academic_year_id' => $academicYearId,
                    'position_title' => $posTitle,
                    'is_active' => true,
                ]
            );

            // Jangan downgrade waka_kurikulum atau super_admin
            if (! $teacher->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah'])) {
                $updateData = ['role_id' => $supervisorRole->id];
                if (! empty($rawPos) && ! str_contains(strtolower($rawPos), 'supervisor')) {
                    $updateData['position'] = $rawPos;
                }
                $teacher->update($updateData);
            }

            $updatedList[] = $teacher->name;
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
     * Deteksi kolom header.
     *
     * @param  array<int, string>  $headerRow
     * @return array{has_header: bool, teacher_col: int, nigs_col: int, pos_col: int}
     */
    protected function detectHeaderColumns(array $headerRow): array
    {
        $teacherCol = -1;
        $nigsCol = -1;
        $posCol = -1;

        foreach ($headerRow as $idx => $cell) {
            $lower = strtolower(trim($cell));
            if ($teacherCol === -1 && (str_contains($lower, 'guru') || str_contains($lower, 'nama') || str_contains($lower, 'supervisor'))) {
                $teacherCol = $idx;
            } elseif ($nigsCol === -1 && (str_contains($lower, 'nigs') || str_contains($lower, 'nip') || str_contains($lower, 'nuptk'))) {
                $nigsCol = $idx;
            } elseif ($posCol === -1 && (str_contains($lower, 'posisi') || str_contains($lower, 'jabatan') || str_contains($lower, 'keterangan') || str_contains($lower, 'tugas'))) {
                $posCol = $idx;
            }
        }

        $hasHeader = ($teacherCol !== -1);

        return [
            'has_header' => $hasHeader,
            'teacher_col' => $hasHeader ? $teacherCol : 0,
            'nigs_col' => $nigsCol !== -1 ? $nigsCol : 1,
            'pos_col' => $posCol !== -1 ? $posCol : 2,
        ];
    }
}
