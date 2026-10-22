<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class TimetableParserService
{
    /**
     * Parse uploaded PDF file into structured schedule data.
     *
     * Nama kelas di dokumen aSc Timetables menggunakan format pendek
     * (misal "X AK", "X TKJ") yang otomatis diterjemahkan ke nama
     * kanonik ("X AKL 1", "X TKJ 1") sebelum proses sinkronisasi.
     */
    public function parsePdf(string $filePath): array
    {
        // 1. Parser Utama: Geometric Python Engine (pypdf) dengan deteksi koordinat 2D
        // Mendeteksi posisi kotak kartu jam pelajaran secara presisi (jam 1 s.d. 10 & hari)
        $pythonScript = app_path('Services/asc_timetable_parser.py');
        if (file_exists($pythonScript) && file_exists($filePath)) {
            $command = 'python '.escapeshellarg($pythonScript).' '.escapeshellarg($filePath).' 2>&1';
            $output = @shell_exec($command);
            if ($output) {
                $jsonStart = strpos($output, '{');
                if ($jsonStart !== false) {
                    $jsonStr = substr($output, $jsonStart);
                    $data = json_decode($jsonStr, true);
                    if (is_array($data) && ! isset($data['error']) && count($data) > 0) {
                        return $data;
                    }
                }
            }
        }

        // 2. Fallback: Smalot Parser (sekuensial teks)
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $scheduleByClass = [];

        // Terjemahan nama PDF singkat → nama kanonik (sumber kebenaran tunggal)
        $aliasMap = [
            'X AK' => 'X AKL 1',
            'X TKJ' => 'X TKJ 1',
            'XI AK' => 'XI AKL 1',
            'XI TKJ' => 'XI TKJ 1',
            'XII AK' => 'XII AKL 1',
        ];

        $classes = array_keys(TimetableSyncService::syncClassrooms());
        $subjects = array_keys(TimetableSyncService::getSubjectDefinitions());
        $teachers = array_keys(TimetableSyncService::getTeacherMapping());

        foreach ($pages as $pageNumber => $page) {
            $text = $page->getText();
            $detectedClass = null;

            // Deteksi nama kelas — cek alias aSc pendek terlebih dahulu
            foreach ($aliasMap as $alias => $canonical) {
                if (preg_match('/\b'.preg_quote($alias, '/').'\b/i', $text)) {
                    $detectedClass = $canonical;
                    break;
                }
            }

            // Fallback: cek nama kanonik langsung
            if (! $detectedClass) {
                foreach ($classes as $className) {
                    if (preg_match('/\b'.preg_quote($className, '/').'\b/i', $text)) {
                        $detectedClass = $className;
                        break;
                    }
                }
            }

            if (! $detectedClass) {
                continue;
            }

            if (! isset($scheduleByClass[$detectedClass])) {
                $scheduleByClass[$detectedClass] = [
                    'Senin' => [],
                    'Selasa' => [],
                    'Rabu' => [],
                    'Kamis' => [],
                    'Jumat' => [],
                ];
            }

            // Extract lines or tokens
            $lines = explode("\n", $text);
            $cleanLines = array_values(array_filter(array_map('trim', $lines)));

            // Extract subject + teacher pairs from page
            $currentDay = 'Senin';
            $days = ['Sen' => 'Senin', 'Sel' => 'Selasa', 'Ra' => 'Rabu', 'Ka' => 'Kamis', 'Ju' => 'Jumat'];

            for ($i = 0; $i < count($cleanLines) - 1; $i++) {
                $line = strtoupper($cleanLines[$i]);
                $nextLine = strtoupper($cleanLines[$i + 1]);

                foreach ($days as $shortDay => $fullDay) {
                    if ($line === strtoupper($shortDay)) {
                        $currentDay = $fullDay;
                    }
                }

                if (in_array($line, $subjects) && in_array($nextLine, $teachers)) {
                    $scheduleByClass[$detectedClass][$currentDay][] = [
                        'subject' => $line,
                        'teacher' => $nextLine,
                        'start' => count($scheduleByClass[$detectedClass][$currentDay]) + 1,
                        'end' => count($scheduleByClass[$detectedClass][$currentDay]) + 2,
                    ];
                    $i++; // Skip teacher line
                }
            }
        }

        return $scheduleByClass;
    }
}
