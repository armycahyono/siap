<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassroom;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class StudentImportService extends BaseExcelImportService
{
    /**
     * Import data siswa dari file Excel (.xlsx) atau CSV (.csv).
     *
     * @return array{success: bool, imported_count: int, updated: array<string>, errors: array<string>}
     */
    public function import(string $filePath, string $extension, ?int $defaultClassroomId = null, ?string $academicYear = null): array
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

        // Ambil mapping kelas
        $classroomsQuery = Classroom::query();
        $academicYearId = null;
        if ($academicYear) {
            $classroomsQuery->where('academic_year', $academicYear);
            $ayRecord = AcademicYear::where('name', $academicYear)->first();
            $academicYearId = $ayRecord?->id;
        }
        $classrooms = $classroomsQuery->get();

        // Deteksi header dari beberapa baris awal (mengantisipasi jika ada judul tabel di baris 1-5)
        $startIndex = 0;
        $headerMap = ['has_header' => false];
        $maxHeaderScan = min(10, count($rows));
        for ($r = 0; $r < $maxHeaderScan; $r++) {
            $candidateMap = $this->detectStudentHeaders($rows[$r]);
            if ($candidateMap['has_header']) {
                $headerMap = $candidateMap;
                $startIndex = $r + 1;
                break;
            }
        }

        // Jika tidak terdeteksi header pada baris manapun, gunakan baris pertama
        if (! $headerMap['has_header']) {
            $headerMap = $this->detectStudentHeaders($rows[0]);
            if ($headerMap['has_header']) {
                $startIndex = 1;
            }
        }

        $importedCount = 0;
        $updated = [];
        $errors = [];

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Abaikan baris kosong
            $nonEmpty = array_filter($row, fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $name = mb_strtoupper(trim((string) ($row[$headerMap['name']] ?? '')), 'UTF-8');
            if (empty($name)) {
                $errors[] = 'Baris '.($i + 1).': Nama siswa kosong.';

                continue;
            }

            // Sanitasi NISN
            $nisn = trim((string) ($row[$headerMap['nisn']] ?? ''));
            if (in_array($nisn, ['-', '0', 'null', 'none']) || strlen($nisn) < 4) {
                $nisn = '';
            }

            // Sanitasi NIS (jangan simpan 'L' atau 'P' atau '-' atau angka tidak valid)
            $nis = trim((string) ($row[$headerMap['nis']] ?? ''));
            if (in_array(strtoupper($nis), ['L', 'P', 'LK', 'PR', '-', '0']) || strlen($nis) < 2) {
                $nis = '';
            }

            // Sanitasi NIK
            $nik = isset($headerMap['nik']) ? trim((string) ($row[$headerMap['nik']] ?? '')) : '';
            if (in_array($nik, ['-', '0']) || strlen($nik) < 8) {
                $nik = '';
            }

            $className = trim((string) ($row[$headerMap['classroom']] ?? ''));
            $gender = strtoupper(trim((string) ($row[$headerMap['gender']] ?? 'L')));
            $gender = in_array($gender, ['P', 'PEREMPUAN', 'WANITA']) ? 'P' : 'L';

            $birthPlace = isset($headerMap['birth_place']) ? trim((string) ($row[$headerMap['birth_place']] ?? '')) : null;
            $rawBirthDate = isset($headerMap['birth_date']) ? trim((string) ($row[$headerMap['birth_date']] ?? '')) : null;
            $birthDate = $this->parseDate($rawBirthDate);

            $religion = isset($headerMap['religion']) ? trim((string) ($row[$headerMap['religion']] ?? '')) : null;

            // Format Alamat Lengkap (termasuk RT/RW/Dusun/Desa jika ada)
            $address = isset($headerMap['address']) ? trim((string) ($row[$headerMap['address']] ?? '')) : null;
            if (empty($address) || $address === '-') {
                $parts = [];
                $rt = isset($headerMap['rt']) ? trim((string) ($row[$headerMap['rt']] ?? '')) : '';
                $rw = isset($headerMap['rw']) ? trim((string) ($row[$headerMap['rw']] ?? '')) : '';
                if ($rt !== '' || $rw !== '') {
                    $parts[] = 'RT '.$rt.'/RW '.$rw;
                }
                if (isset($headerMap['dusun']) && trim((string) ($row[$headerMap['dusun']] ?? '')) !== '' && trim((string) $row[$headerMap['dusun']]) !== '-') {
                    $parts[] = 'Dsn. '.trim((string) $row[$headerMap['dusun']]);
                }
                if (isset($headerMap['village']) && trim((string) ($row[$headerMap['village']] ?? '')) !== '' && trim((string) $row[$headerMap['village']]) !== '-') {
                    $parts[] = 'Desa/Kel. '.trim((string) $row[$headerMap['village']]);
                }
                if (isset($headerMap['district']) && trim((string) ($row[$headerMap['district']] ?? '')) !== '' && trim((string) $row[$headerMap['district']]) !== '-') {
                    $parts[] = 'Kec. '.trim((string) $row[$headerMap['district']]);
                }
                if (! empty($parts)) {
                    $address = implode(', ', $parts);
                }
            }

            $fatherName = isset($headerMap['father_name']) ? trim((string) ($row[$headerMap['father_name']] ?? '')) : null;
            $fatherJob = isset($headerMap['father_job']) ? trim((string) ($row[$headerMap['father_job']] ?? '')) : null;
            $fatherPhone = isset($headerMap['father_phone']) ? trim((string) ($row[$headerMap['father_phone']] ?? '')) : null;

            $motherName = isset($headerMap['mother_name']) ? trim((string) ($row[$headerMap['mother_name']] ?? '')) : null;
            $motherJob = isset($headerMap['mother_job']) ? trim((string) ($row[$headerMap['mother_job']] ?? '')) : null;
            $motherPhone = isset($headerMap['mother_phone']) ? trim((string) ($row[$headerMap['mother_phone']] ?? '')) : null;

            $guardianName = isset($headerMap['guardian_name']) ? trim((string) ($row[$headerMap['guardian_name']] ?? '')) : null;
            $guardianJob = isset($headerMap['guardian_job']) ? trim((string) ($row[$headerMap['guardian_job']] ?? '')) : null;
            $guardianPhone = isset($headerMap['guardian_phone']) ? trim((string) ($row[$headerMap['guardian_phone']] ?? '')) : null;

            $phoneParent = trim((string) ($row[$headerMap['phone_parent']] ?? ''));
            $phoneStudent = trim((string) ($row[$headerMap['phone_student']] ?? ''));
            $notes = isset($headerMap['notes']) ? trim((string) ($row[$headerMap['notes']] ?? '')) : null;

            // Tentukan kelas
            $targetClassroomId = $defaultClassroomId;
            if (! empty($className)) {
                $matchedClass = $this->matchClassroom($classrooms, $className);
                if ($matchedClass) {
                    $targetClassroomId = $matchedClass->id;
                }
            }

            // Cari siswa yang sudah ada secara aman
            $student = null;
            if (! empty($nisn)) {
                $student = Student::where('nisn', $nisn)->first();
            }
            if (! $student && ! empty($nis)) {
                $student = Student::where('nis', $nis)->first();
            }
            if (! $student && ! empty($nik)) {
                $student = Student::where('nik', $nik)->first();
            }
            if (! $student && ! empty($targetClassroomId)) {
                $student = Student::where('name', $name)
                    ->where('classroom_id', $targetClassroomId)
                    ->first();
            }

            $payload = [
                'nisn' => $nisn ?: ($student?->nisn),
                'nis' => $nis ?: ($student?->nis),
                'nik' => $nik ?: ($student?->nik),
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $birthPlace ?: ($student?->birth_place),
                'birth_date' => $birthDate ?: ($student?->birth_date),
                'religion' => $religion ?: ($student?->religion),
                'address' => $address ?: ($student?->address),
                'classroom_id' => $targetClassroomId ?: ($student?->classroom_id),
                'father_name' => $fatherName ?: ($student?->father_name),
                'father_job' => $fatherJob ?: ($student?->father_job),
                'father_phone' => $fatherPhone ?: ($student?->father_phone),
                'mother_name' => $motherName ?: ($student?->mother_name),
                'mother_job' => $motherJob ?: ($student?->mother_job),
                'mother_phone' => $motherPhone ?: ($student?->mother_phone),
                'guardian_name' => $guardianName ?: ($student?->guardian_name),
                'guardian_job' => $guardianJob ?: ($student?->guardian_job),
                'guardian_phone' => $guardianPhone ?: ($student?->guardian_phone),
                'phone_parent' => $phoneParent ?: ($student?->phone_parent ?: $fatherPhone ?: $motherPhone ?: $guardianPhone),
                'phone_student' => $phoneStudent ?: ($student?->phone_student),
                'notes' => $notes ?: ($student?->notes),
                'status' => 'aktif',
                'is_active' => true,
            ];

            // Proteksi status jika siswa sudah resmi mutasi/keluar/lulus di SIAP
            if ($student && in_array($student->status, ['mutasi', 'keluar', 'lulus'])) {
                unset($payload['status'], $payload['is_active']);
                if ($student->classroom_id === null) {
                    unset($payload['classroom_id']);
                }
            }

            $targetStudent = null;
            if ($student) {
                $student->update($payload);
                $targetStudent = $student;
                $updated[] = "{$name} (Diperbarui)";
            } else {
                $newStudent = Student::create($payload);
                $targetStudent = $newStudent;
                $importedCount++;
            }

            // Sinkronkan ke riwayat penempatan rombel siswa (student_classrooms)
            if ($targetStudent && $targetClassroomId && $academicYearId) {
                StudentClassroom::updateOrCreate(
                    [
                        'student_id' => $targetStudent->id,
                        'academic_year_id' => $academicYearId,
                    ],
                    [
                        'classroom_id' => $targetClassroomId,
                        'status' => $targetStudent->status ?: 'aktif',
                        'is_repeating' => false,
                        'academic_year_name' => $academicYear,
                        'classroom_name' => $student?->classroom?->name ?? $matchedClass?->name ?? 'Rombel',
                    ]
                );
            }
        }

        return [
            'success' => ($importedCount > 0 || count($updated) > 0),
            'imported_count' => $importedCount,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Parse date string into Y-m-d format safely.
     * Mendukung format:
     * - YYYY-MM-DD (contoh: 2008-05-14)
     * - YYYY/MM/DD (contoh: 2008/05/14)
     * - DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY (contoh: 14/05/2008, 14-05-2008, 14.05.2008)
     * - Tanggal serial angka Excel (contoh: 39582 -> 2008-05-14)
     * - Nama bulan bahasa Indonesia (contoh: 14 Mei 2008, 17 Agustus 2007)
     */
    public function parseDate(?string $rawDate): ?string
    {
        if (empty($rawDate)) {
            return null;
        }

        $rawDate = trim((string) $rawDate);

        // 1. Format standar YYYY-MM-DD atau YYYY/MM/DD
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $rawDate, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 2. Format Indonesia DD/MM/YYYY atau DD-MM-YYYY atau DD.MM.YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $rawDate, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 3. Format serial date dari Excel jika tersimpan numerik (antara 10000 dan 80000)
        if (is_numeric($rawDate) && (int) $rawDate > 10000 && (int) $rawDate < 80000) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) $rawDate)->format('Y-m-d');
            } catch (Throwable) {
                // Lanjut ke fallback
            }
        }

        // 4. Format tanggal dengan nama bulan Indonesia (contoh: 14 Mei 2008)
        $indonesianMonths = [
            'januari' => 1, 'jan' => 1,
            'februari' => 2, 'feb' => 2,
            'maret' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'jun' => 6,
            'juli' => 7, 'jul' => 7,
            'agustus' => 8, 'agu' => 8, 'ags' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'okt' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'des' => 12,
        ];
        if (preg_match('/^(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})$/', $rawDate, $m)) {
            $monthKey = strtolower($m[2]);
            if (isset($indonesianMonths[$monthKey])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], $indonesianMonths[$monthKey], (int) $m[1]);
            }
        }

        try {
            return Carbon::parse($rawDate)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolusi nama kelas secara fleksibel dan cerdas.
     * Contoh:
     * - "X AK", "X AKL", "10 AK" -> "X AKL 1"
     * - "X TKJ", "10 TKJ" -> "X TKJ 1"
     * - "XI AK", "11 AK" -> "XI AKL 1"
     * - "XI TKJ" -> "XI TKJ 1"
     * - "XII AK" -> "XII AKL 1"
     * - "XII TKJ 2" -> "XII TKJ 2"
     * - "X TKR", "X TKRO" -> "X TKR 1"
     * - "X TKR 2", "X TKRO 2" -> "X TKR 2"
     *
     * @param  iterable<int, Classroom>  $classrooms
     */
    public function matchClassroom(iterable $classrooms, ?string $className): ?Classroom
    {
        if (empty($className)) {
            return null;
        }

        $clean = trim((string) $className);
        if ($clean === '') {
            return null;
        }

        $classroomsCol = $classrooms instanceof Collection
            ? $classrooms
            : collect($classrooms);

        // 1. Pencocokan langsung (persis atau tanpa spasi/tanda hubung)
        $noSpaceInput = strtolower((string) preg_replace('/[\s\-_]/', '', $clean));
        $exact = $classroomsCol->first(function ($c) use ($clean, $noSpaceInput) {
            $cNoSpace = strtolower((string) preg_replace('/[\s\-_]/', '', $c->name));

            return strcasecmp($c->name, $clean) === 0 || $cNoSpace === $noSpaceInput;
        });

        if ($exact) {
            return $exact;
        }

        // 2. Normalisasi Tingkat / Level (X/10, XI/11, XII/12)
        $grade = null;
        if (preg_match('/(^|\b)(xii|12)(\b|$)/i', $clean)) {
            $grade = 'XII';
        } elseif (preg_match('/(^|\b)(xi|11)(\b|$)/i', $clean)) {
            $grade = 'XI';
        } elseif (preg_match('/(^|\b)(x|10)(\b|$)/i', $clean)) {
            $grade = 'X';
        }

        // 3. Normalisasi Jurusan (AK/AKL -> AKL, TKJ -> TKJ, TKR/TKRO -> TKR)
        $major = null;
        if (preg_match('/\b(akl|akuntansi|ak)\b/i', $clean)) {
            $major = 'AKL';
        } elseif (preg_match('/\b(tkj)\b/i', $clean)) {
            $major = 'TKJ';
        } elseif (preg_match('/\b(tkro|tkr|otomotif)\b/i', $clean)) {
            $major = 'TKR';
        }

        if (! $grade || ! $major) {
            return null;
        }

        // 4. Deteksi Rombel / Nomor Kelas (1, 2, 3, 4)
        // Hapus kata tingkat (10, 11, 12, x, xi, xii) agar angka tingkat tidak dianggap rombel
        $withoutGrade = preg_replace('/(^|\b)(xii|xi|x|12|11|10)(\b|$)/i', '', $clean);
        $number = '1'; // Default ke rombel 1 jika tidak dicantumkan (contoh: "X AK" -> "X AKL 1", "X TKJ" -> "X TKJ 1")
        if (preg_match('/\b([1-4])\b/', (string) $withoutGrade, $m)) {
            $number = $m[1];
        }

        // Cari kelas yang cocok: e.g. "X AKL 1" atau "X TKJ 1"
        $targetName = "{$grade} {$major} {$number}";
        $matched = $classroomsCol->first(function ($c) use ($targetName) {
            return strcasecmp($c->name, $targetName) === 0;
        });

        if ($matched) {
            return $matched;
        }

        // Fallback: kelas pertama dengan tingkat dan jurusan yang sama
        return $classroomsCol->first(function ($c) use ($grade, $major) {
            return str_starts_with(strtoupper($c->name), "{$grade} {$major}");
        });
    }

    /**
     * @param  array<int, string>  $row
     * @return array<string, int|bool>
     */
    protected function detectStudentHeaders(array $row): array
    {
        $map = [
            'has_header' => false,
            'nisn' => 1,
            'nis' => 2,
            'name' => 3,
            'classroom' => 4,
            'gender' => 5,
            'phone_parent' => 6,
            'phone_student' => 7,
        ];

        $matchedHeadersCount = 0;

        foreach ($row as $idx => $cell) {
            $lower = strtolower(trim((string) $cell));
            if ($lower === '') {
                continue;
            }

            // 1. NISN (Harus dicek sebelum NIS)
            if (str_contains($lower, 'nisn')) {
                $map['nisn'] = $idx;
                $matchedHeadersCount++;
            }
            // 2. Jenis Kelamin / Gender / JK (HARUS dicek sebelum NIS dan NIK)
            // Karena kata "jenis" mengandung "nis", dan "jk" atau "gender"
            elseif (
                str_contains($lower, 'kelamin')
                || str_contains($lower, 'gender')
                || in_array($lower, ['jk', 'j/k', 'l/p', 'l / p', 'p/l'])
                || preg_match('/\b(jk|gender)\b/i', $lower)
            ) {
                $map['gender'] = $idx;
                $matchedHeadersCount++;
            }
            // 3. NIK (KTP Siswa) - pastikan bukan "teknik", "elektronik", "mekanik"
            elseif (
                ! str_contains($lower, 'teknik')
                && ! str_contains($lower, 'elektronik')
                && ! str_contains($lower, 'mekanik')
                && (str_contains($lower, 'nik') || str_contains($lower, 'no ktp') || str_contains($lower, 'no. ktp') || str_contains($lower, 'nomor ktp'))
            ) {
                $map['nik'] = $idx;
                $matchedHeadersCount++;
            }
            // 4. NIS / NIPD / No Induk - pastikan bukan "jenis", "teknis", "nisn"
            elseif (
                ! str_contains($lower, 'jenis')
                && ! str_contains($lower, 'teknis')
                && ! str_contains($lower, 'nisn')
                && (
                    str_contains($lower, 'nipd')
                    || str_contains($lower, 'no induk')
                    || str_contains($lower, 'no. induk')
                    || str_contains($lower, 'nomor induk')
                    || in_array($lower, ['nis', 'no nis', 'no. nis', 'nomor nis'])
                    || preg_match('/\b(nis|nipd)\b/i', $lower)
                )
            ) {
                $map['nis'] = $idx;
                $matchedHeadersCount++;
            }
            // 5. Nama Siswa (bukan nama ayah/ibu/wali/sekolah/ortu/dusun)
            elseif (
                str_contains($lower, 'nama')
                && ! str_contains($lower, 'ayah')
                && ! str_contains($lower, 'ibu')
                && ! str_contains($lower, 'wali')
                && ! str_contains($lower, 'bapak')
                && ! str_contains($lower, 'orang tua')
                && ! str_contains($lower, 'ortu')
                && ! str_contains($lower, 'sekolah')
                && ! str_contains($lower, 'dusun')
            ) {
                $map['name'] = $idx;
                $matchedHeadersCount++;
            }
            // 6. Kelas / Rombel / Rombongan Belajar
            elseif (
                str_contains($lower, 'kelas')
                || str_contains($lower, 'rombel')
                || str_contains($lower, 'rombongan belajar')
            ) {
                $map['classroom'] = $idx;
                $matchedHeadersCount++;
            }
            // 7. Tempat Lahir
            elseif (
                str_contains($lower, 'tempat lahir')
                || str_contains($lower, 'tmp_lahir')
                || str_contains($lower, 'tmp lahir')
                || str_contains($lower, 'tempat_lahir')
            ) {
                $map['birth_place'] = $idx;
                $matchedHeadersCount++;
            }
            // 8. Tanggal Lahir
            elseif (
                str_contains($lower, 'tgl lahir')
                || str_contains($lower, 'tanggal lahir')
                || str_contains($lower, 'tgl_lahir')
                || str_contains($lower, 'tanggal_lahir')
                || str_contains($lower, 'tgllahir')
            ) {
                $map['birth_date'] = $idx;
                $matchedHeadersCount++;
            }
            // 9. Agama
            elseif (str_contains($lower, 'agama')) {
                $map['religion'] = $idx;
                $matchedHeadersCount++;
            }
            // 10. Alamat Jalan / Lengkap
            elseif (str_contains($lower, 'alamat')) {
                $map['address'] = $idx;
                $matchedHeadersCount++;
            }
            // Kolom RT, RW, Dusun, Desa/Kelurahan, Kecamatan (Format Dapodik)
            elseif ($lower === 'rt' || str_starts_with($lower, 'rt ') || str_contains($lower, 'no rt')) {
                $map['rt'] = $idx;
            } elseif ($lower === 'rw' || str_starts_with($lower, 'rw ') || str_contains($lower, 'no rw')) {
                $map['rw'] = $idx;
            } elseif (str_contains($lower, 'dusun')) {
                $map['dusun'] = $idx;
            } elseif (str_contains($lower, 'kelurahan') || str_contains($lower, 'desa')) {
                $map['village'] = $idx;
            } elseif (str_contains($lower, 'kecamatan')) {
                $map['district'] = $idx;
            }
            // 11. Data Ayah
            elseif (
                (str_contains($lower, 'ayah') || str_contains($lower, 'bapak'))
                && str_contains($lower, 'pekerjaan')
            ) {
                $map['father_job'] = $idx;
                $matchedHeadersCount++;
            } elseif (
                (str_contains($lower, 'ayah') || str_contains($lower, 'bapak'))
                && (str_contains($lower, 'hp') || str_contains($lower, 'telp') || str_contains($lower, 'telepon') || str_contains($lower, 'wa'))
            ) {
                $map['father_phone'] = $idx;
                $matchedHeadersCount++;
            } elseif (str_contains($lower, 'ayah') || str_contains($lower, 'bapak')) {
                $map['father_name'] = $idx;
                $matchedHeadersCount++;
            }
            // 12. Data Ibu
            elseif (str_contains($lower, 'ibu') && str_contains($lower, 'pekerjaan')) {
                $map['mother_job'] = $idx;
                $matchedHeadersCount++;
            } elseif (str_contains($lower, 'ibu') && (str_contains($lower, 'hp') || str_contains($lower, 'telp') || str_contains($lower, 'telepon') || str_contains($lower, 'wa'))) {
                $map['mother_phone'] = $idx;
                $matchedHeadersCount++;
            } elseif (str_contains($lower, 'ibu')) {
                $map['mother_name'] = $idx;
                $matchedHeadersCount++;
            }
            // 13. Data Wali
            elseif (str_contains($lower, 'wali') && str_contains($lower, 'pekerjaan')) {
                $map['guardian_job'] = $idx;
                $matchedHeadersCount++;
            } elseif (str_contains($lower, 'wali') && (str_contains($lower, 'hp') || str_contains($lower, 'telp') || str_contains($lower, 'telepon') || str_contains($lower, 'wa'))) {
                $map['guardian_phone'] = $idx;
                $matchedHeadersCount++;
            } elseif (str_contains($lower, 'wali')) {
                $map['guardian_name'] = $idx;
                $matchedHeadersCount++;
            }
            // 14. Kontak Orang Tua / Telepon Rumah
            elseif (
                str_contains($lower, 'hp ortu')
                || str_contains($lower, 'kontak ortu')
                || str_contains($lower, 'telp ortu')
                || str_contains($lower, 'telepon rumah')
                || str_contains($lower, 'telp rumah')
                || str_contains($lower, 'telepon ortu')
            ) {
                $map['phone_parent'] = $idx;
                $matchedHeadersCount++;
            }
            // 15. Kontak Siswa / HP Siswa / Telepon Seluler
            elseif (
                str_contains($lower, 'hp siswa')
                || str_contains($lower, 'wa siswa')
                || str_contains($lower, 'telp siswa')
                || str_contains($lower, 'telepon seluler')
                || str_contains($lower, 'nomor telepon seluler')
                || (
                    str_contains($lower, 'hp')
                    && ! str_contains($lower, 'ayah')
                    && ! str_contains($lower, 'ibu')
                    && ! str_contains($lower, 'wali')
                    && ! str_contains($lower, 'ortu')
                )
            ) {
                $map['phone_student'] = $idx;
                $matchedHeadersCount++;
            }
            // 16. Catatan
            elseif (str_contains($lower, 'catatan') || str_contains($lower, 'keterangan')) {
                $map['notes'] = $idx;
            }
        }

        // Baris dianggap header jika minimal ada 2 kolom kunci yang cocok
        if ($matchedHeadersCount >= 2 || isset($map['name'], $map['nisn']) || isset($map['name'], $map['gender'])) {
            $map['has_header'] = true;
        }

        return $map;
    }
}
