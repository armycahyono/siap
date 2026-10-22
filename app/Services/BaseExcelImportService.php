<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use ZipArchive;

abstract class BaseExcelImportService
{
    /**
     * Parse file sesuai ekstensinya (XLSX, CSV, TXT, XLS).
     *
     * @return array<int, array<int, string>>
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $ext = strtolower($extension);

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseCsv($filePath);
        }

        if ($ext === 'xlsx') {
            return $this->parseXlsx($filePath);
        }

        // Fallback coba baca XLSX, jika gagal coba CSV
        try {
            return $this->parseXlsx($filePath);
        } catch (Exception) {
            return $this->parseCsv($filePath);
        }
    }

    /**
     * Membaca file CSV / TXT dengan auto-detect delimiter dan pembersihan BOM.
     *
     * @return array<int, array<int, string>>
     */
    protected function parseCsv(string $filePath): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);

            // Deteksi pemisah (koma, titik koma, atau tab)
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            while (($data = fgetcsv($handle, 2000, $delimiter)) !== false) {
                $cleanedRow = array_map(function ($val) {
                    $v = trim($val);

                    // Hapus UTF-8 BOM
                    return preg_replace('/^\xEF\xBB\xBF/', '', $v);
                }, $data);

                if (! empty(array_filter($cleanedRow))) {
                    $rows[] = $cleanedRow;
                }
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Membaca file XLSX menggunakan ZipArchive dan SimpleXML tanpa dependency eksternal.
     *
     * @return array<int, array<int, string>>
     */
    protected function parseXlsx(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new Exception('Gagal membuka file Excel (.xlsx). Pastikan file tidak rusak.');
        }

        // 1. Ekstraksi Shared Strings
        $sharedStrings = [];
        $sharedXmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlContent !== false) {
            $xml = simplexml_load_string($sharedXmlContent);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Ekstraksi Sheet1
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('#xl/worksheets/sheet\d+\.xml#', $stat['name'])) {
                    $sheetXmlContent = $zip->getFromName($stat['name']);
                    break;
                }
            }
        }

        $rows = [];
        if ($sheetXmlContent !== false) {
            $sheetXml = simplexml_load_string($sheetXmlContent);
            if ($sheetXml && isset($sheetXml->sheetData->row)) {
                foreach ($sheetXml->sheetData->row as $row) {
                    $rowCells = [];
                    foreach ($row->c as $cell) {
                        $cellRef = (string) $cell['r'];
                        preg_match('/^([A-Z]+)/', $cellRef, $matches);
                        $colLetters = $matches[1] ?? 'A';
                        $colIndex = $this->colLetterToIndex($colLetters);

                        $type = (string) $cell['t'];
                        $val = (string) $cell->v;

                        if ($type === 's' && isset($sharedStrings[(int) $val])) {
                            $cellValue = $sharedStrings[(int) $val];
                        } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                            $cellValue = (string) $cell->is->t;
                        } else {
                            $cellValue = $val;
                        }

                        $rowCells[$colIndex] = trim($cellValue);
                    }

                    if (! empty($rowCells)) {
                        $maxCol = max(array_keys($rowCells));
                        $normalizedRow = [];
                        for ($c = 0; $c <= $maxCol; $c++) {
                            $normalizedRow[$c] = $rowCells[$c] ?? '';
                        }
                        $rows[] = $normalizedRow;
                    }
                }
            }
        }

        $zip->close();

        return $rows;
    }

    /**
     * Konversi huruf kolom Excel (e.g. A, B, Z, AA) menjadi indeks 0-based integer.
     */
    protected function colLetterToIndex(string $col): int
    {
        $len = strlen($col);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Membersihkan nama guru dari karakter pemisah dan titik dua di awal.
     */
    public function cleanTeacherName(string $raw): string
    {
        $clean = trim($raw, " :-\t\n\r");

        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Normalisasi nama orang untuk pencocokan fuzzy (menghapus gelar akademis & non-huruf).
     */
    public function normalizePersonName(string $name): string
    {
        // Hapus gelar depan: Drs, Dra, Ir, H, Hj
        $clean = preg_replace('/\b(Drs|Dra|Ir|H|Hj)\.?\s+/i', '', $name);
        // Hapus gelar belakang: S.Pd, M.Pd, S.T, A.Md, dll
        $clean = preg_replace('/,\s*(S\.Pd|M\.Pd|S\.T|A\.Md|A\.Md\.T|S\.Fil|S\.Pd\.I|S\.Pt|S\.Kom|M\.M|M\.Si).*$/i', '', $clean);
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean);

        return strtolower(trim(preg_replace('/\s+/', ' ', $clean)));
    }

    /**
     * Cari Guru berdasarkan nama / NIGS / NIP / singkatan.
     *
     * @param  Collection<int, User>  $teachers
     */
    public function findTeacher(string $cleanName, string $nigs, Collection $teachers): ?User
    {
        // 1. Cari via NIGS / NIP jika ada
        if (! empty($nigs)) {
            $byNigs = $teachers->first(fn ($t) => ($t->nigs_fix === $nigs) || ($t->nigs === $nigs) || ($t->nip === $nigs));
            if ($byNigs) {
                return $byNigs;
            }
        }

        if (empty($cleanName)) {
            return null;
        }

        // 2. Exact match nama lengkap
        $match = $teachers->first(fn ($t) => strcasecmp($t->name, $cleanName) === 0);
        if ($match) {
            return $match;
        }

        // 3. Match dengan short_name
        $match = $teachers->first(fn ($t) => ! empty($t->short_name) && strcasecmp($t->short_name, $cleanName) === 0);
        if ($match) {
            return $match;
        }

        // 4. Fuzzy match nama (menghapus gelar akademis)
        $normSearch = $this->normalizePersonName($cleanName);
        if (! empty($normSearch)) {
            foreach ($teachers as $candidate) {
                $normCandidate = $this->normalizePersonName($candidate->name);
                if (
                    $normCandidate === $normSearch ||
                    str_contains($normCandidate, $normSearch) ||
                    str_contains($normSearch, $normCandidate)
                ) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
