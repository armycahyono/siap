<?php

namespace App\Services;

use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

class StructuralImportService extends BaseExcelImportService
{
    /**
     * Import data penugasan struktural dari file Excel (.xlsx) atau CSV (.csv).
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

        // Ambil data jabatan struktural aktif
        $positions = StructuralPosition::where('is_active', true)->get();

        // Ambil semua guru aktif
        $allTeachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah']))
            ->operationalPersonnel()
            ->where('is_active', true)
            ->get();

        // Deteksi kolom header
        $firstRow = $rows[0];
        $headerIndices = $this->detectHeaderColumns($firstRow);

        $startIndex = $headerIndices['has_header'] ? 1 : 0;
        $colPos = $headerIndices['pos_col'];
        $colTeacher = $headerIndices['teacher_col'];
        $colNigs = $headerIndices['nigs_col'];
        $colSkNum = $headerIndices['sk_num_col'];
        $colSkDate = $headerIndices['sk_date_col'];
        $colNotes = $headerIndices['notes_col'];

        $importedCount = 0;
        $updatedList = [];
        $errorList = [];

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rawPos = $row[$colPos] ?? '';
            $rawTeacher = $row[$colTeacher] ?? '';
            $rawNigs = isset($row[$colNigs]) ? trim($row[$colNigs]) : '';
            $rawSkNum = isset($row[$colSkNum]) ? trim($row[$colSkNum]) : null;
            $rawSkDate = isset($row[$colSkDate]) ? trim($row[$colSkDate]) : null;
            $rawNotes = isset($row[$colNotes]) ? trim($row[$colNotes]) : null;

            if (empty(trim($rawPos)) && empty(trim($rawTeacher))) {
                continue;
            }

            // Cari StructuralPosition
            $position = $this->findPosition($rawPos, $positions);
            if (! $position) {
                $errorList[] = 'Baris '.($i + 1).": Jabatan '{$rawPos}' tidak dikenali di sistem.";

                continue;
            }

            $cleanTeacher = $this->cleanTeacherName($rawTeacher);
            if (empty($cleanTeacher) && empty($rawNigs)) {
                // Hapus penugasan untuk jabatan ini pada tahun ajaran tsb
                TeacherStructuralAssignment::where('structural_position_id', $position->id)
                    ->where('academic_year', $academicYear)
                    ->delete();
                $updatedList[] = "{$position->name}: (Dikosongkan)";
                $importedCount++;

                continue;
            }

            // Cari Guru
            $teacher = $this->findTeacher($cleanTeacher, $rawNigs, $allTeachers);
            if (! $teacher) {
                $errorList[] = 'Baris '.($i + 1).": Guru '{$rawTeacher}' untuk jabatan {$position->name} tidak ditemukan.";

                continue;
            }

            // Format tanggal SK jika valid
            $skDate = null;
            if (! empty($rawSkDate)) {
                $timestamp = strtotime($rawSkDate);
                if ($timestamp !== false) {
                    $skDate = date('Y-m-d', $timestamp);
                }
            }

            TeacherStructuralAssignment::updateOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'structural_position_id' => $position->id,
                    'academic_year' => $academicYear,
                ],
                [
                    'sk_number' => $rawSkNum ?: null,
                    'sk_date' => $skDate,
                    'notes' => $rawNotes ?: null,
                    'is_active' => true,
                ]
            );

            if ($position->name && ! $teacher->hasRole(['super_admin', 'kepala_sekolah'])) {
                $teacher->update(['position' => $position->name]);
            }

            $updatedList[] = "{$position->name} &rarr; {$teacher->name}";
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
     * @return array{has_header: bool, pos_col: int, teacher_col: int, nigs_col: int, sk_num_col: int, sk_date_col: int, notes_col: int}
     */
    protected function detectHeaderColumns(array $headerRow): array
    {
        $posCol = -1;
        $teacherCol = -1;
        $nigsCol = -1;
        $skNumCol = -1;
        $skDateCol = -1;
        $notesCol = -1;

        foreach ($headerRow as $idx => $cell) {
            $lower = strtolower(trim($cell));
            if ($posCol === -1 && (str_contains($lower, 'jabatan') || str_contains($lower, 'posisi') || str_contains($lower, 'tugas'))) {
                $posCol = $idx;
            } elseif ($teacherCol === -1 && (str_contains($lower, 'guru') || str_contains($lower, 'nama') || str_contains($lower, 'pejabat'))) {
                $teacherCol = $idx;
            } elseif ($nigsCol === -1 && (str_contains($lower, 'nigs') || str_contains($lower, 'nip') || str_contains($lower, 'nuptk'))) {
                $nigsCol = $idx;
            } elseif ($skNumCol === -1 && (str_contains($lower, 'sk') && ! str_contains($lower, 'tgl') && ! str_contains($lower, 'tanggal'))) {
                $skNumCol = $idx;
            } elseif ($skDateCol === -1 && (str_contains($lower, 'tmt') || str_contains($lower, 'tanggal') || str_contains($lower, 'tgl'))) {
                $skDateCol = $idx;
            } elseif ($notesCol === -1 && (str_contains($lower, 'ket') || str_contains($lower, 'catatan'))) {
                $notesCol = $idx;
            }
        }

        $hasHeader = ($posCol !== -1 && $teacherCol !== -1);

        return [
            'has_header' => $hasHeader,
            'pos_col' => $hasHeader ? $posCol : 0,
            'teacher_col' => $hasHeader ? $teacherCol : 1,
            'nigs_col' => $nigsCol !== -1 ? $nigsCol : 2,
            'sk_num_col' => $skNumCol !== -1 ? $skNumCol : 3,
            'sk_date_col' => $skDateCol !== -1 ? $skDateCol : 4,
            'notes_col' => $notesCol !== -1 ? $notesCol : 5,
        ];
    }

    /**
     * Cari StructuralPosition berdasarkan kode, nama lengkap, atau kata kunci.
     *
     * @param  Collection<int, StructuralPosition>  $positions
     */
    protected function findPosition(string $rawPos, Collection $positions): ?StructuralPosition
    {
        $clean = trim($rawPos, " :-\t\n\r");
        if (empty($clean)) {
            return null;
        }

        // 1. Exact match kode
        $match = $positions->first(fn ($p) => strcasecmp($p->code, $clean) === 0);
        if ($match) {
            return $match;
        }

        // 2. Exact match nama
        $match = $positions->first(fn ($p) => strcasecmp($p->name, $clean) === 0);
        if ($match) {
            return $match;
        }

        // 3. Normalisasi string
        $lower = strtolower($clean);

        // Alias matching
        if (str_contains($lower, 'sarpras')) {
            return $positions->first(fn ($p) => $p->code === 'WAKA_SARPRAS');
        }
        if (str_contains($lower, 'kurikulum') || str_contains($lower, 'kur')) {
            return $positions->first(fn ($p) => $p->code === 'WAKA_KUR');
        }
        if (str_contains($lower, 'kesiswaan')) {
            return $positions->first(fn ($p) => $p->code === 'WAKA_KESISWAAN');
        }
        if (str_contains($lower, 'humas') || str_contains($lower, 'hubungan')) {
            return $positions->first(fn ($p) => $p->code === 'WAKA_HUMAS');
        }
        if (str_contains($lower, 'bendahara') || str_contains($lower, 'keuangan')) {
            return $positions->first(fn ($p) => $p->code === 'BENDAHARA');
        }
        if (str_contains($lower, 'tkr') || str_contains($lower, 'otomotif')) {
            return $positions->first(fn ($p) => $p->code === 'KAPRO_TKR');
        }
        if (str_contains($lower, 'tkj') || str_contains($lower, 'jaringan')) {
            return $positions->first(fn ($p) => $p->code === 'KAPRO_TKJ');
        }
        if (str_contains($lower, 'akl') || str_contains($lower, 'akuntansi') || str_contains($lower, 'ak')) {
            return $positions->first(fn ($p) => $p->code === 'KAPRO_AKL');
        }
        if (str_contains($lower, 'perpus')) {
            return $positions->first(fn ($p) => $p->code === 'KA_PERPUS');
        }
        if (str_contains($lower, 'bengkel')) {
            return $positions->first(fn ($p) => $p->code === 'KA_BENGKEL');
        }
        if (str_contains($lower, 'lab') || str_contains($lower, 'komputer')) {
            return $positions->first(fn ($p) => $p->code === 'KA_LAB_KOMP');
        }
        if (str_contains($lower, 'tu') || str_contains($lower, 'tata usaha')) {
            return $positions->first(fn ($p) => $p->code === 'KA_TU');
        }
        if (str_contains($lower, 'kepala sekolah') || $lower === 'ks') {
            return $positions->first(fn ($p) => $p->code === 'KS');
        }

        // 4. Substring contains match
        return $positions->first(fn ($p) => str_contains(strtolower($p->name), $lower) || str_contains($lower, strtolower($p->name)));
    }
}
