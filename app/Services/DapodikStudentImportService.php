<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\StudentMutation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DapodikStudentImportService extends BaseExcelImportService
{
    /**
     * Import atau sinkronisasi data siswa dari file ekspor Dapodik Kemendikbud (Daftar PD .csv / .xlsx).
     *
     * @param  array{auto_graduate_grade_12?: bool, auto_mutasi_missing_students?: bool, auto_create_classes?: bool}  $options
     * @return array{
     *     success: bool,
     *     total_processed: int,
     *     imported_count: int,
     *     updated_count: int,
     *     promoted_count: int,
     *     graduated_count: int,
     *     mutated_count: int,
     *     skipped_count: int,
     *     details: array<string>,
     *     errors: array<string>
     * }
     */
    public function import(string $filePath, string $extension, ?int $defaultClassroomId = null, ?string $academicYear = null, array $options = []): array
    {
        $rows = $this->parseFile($filePath, $extension);

        if (empty($rows)) {
            return [
                'success' => false,
                'total_processed' => 0,
                'imported_count' => 0,
                'updated_count' => 0,
                'promoted_count' => 0,
                'graduated_count' => 0,
                'mutated_count' => 0,
                'skipped_count' => 0,
                'details' => [],
                'errors' => ['File Dapodik kosong atau format file tidak dapat dibaca.'],
            ];
        }

        // Ambil data rombel / kelas untuk tahun ajaran target
        if ($academicYear) {
            $classrooms = Classroom::where('academic_year', $academicYear)->get();
        } else {
            $classrooms = Classroom::where('is_active', true)->get();
        }

        // 1. Temukan baris header utama Dapodik
        $headerRowIndex = null;
        $maxScan = min(15, count($rows));
        for ($i = 0; $i < $maxScan; $i++) {
            $rowStr = strtolower(implode(' ', $rows[$i]));
            if (str_contains($rowStr, 'nipd') && str_contains($rowStr, 'nisn') && str_contains($rowStr, 'nama')) {
                $headerRowIndex = $i;
                break;
            }
        }

        if ($headerRowIndex === null) {
            return [
                'success' => false,
                'total_processed' => 0,
                'imported_count' => 0,
                'updated_count' => 0,
                'promoted_count' => 0,
                'graduated_count' => 0,
                'mutated_count' => 0,
                'skipped_count' => 0,
                'details' => [],
                'errors' => ['Header kolom Dapodik tidak ditemukan. Pastikan file adalah file ekspor resmi "Daftar PD" dari Dapodik.'],
            ];
        }

        // Peta index kolom berdasarkan baris header
        $colMap = $this->buildColumnMap($rows[$headerRowIndex]);

        // Susun nama kolom lengkap dari baris header (termasuk sub-header ortu jika ada)
        $rawHeaderRow = $rows[$headerRowIndex];
        $subHeaderRow = ($headerRowIndex + 1 < count($rows)) ? $rows[$headerRowIndex + 1] : [];
        $headerNames = [];
        $currentParent = '';
        foreach ($rawHeaderRow as $colIdx => $colName) {
            $main = trim((string) $colName);
            $sub = trim((string) ($subHeaderRow[$colIdx] ?? ''));
            if ($main !== '' && in_array($main, ['Data Ayah', 'Data Ibu', 'Data Wali'])) {
                $currentParent = $main;
            } elseif ($main !== '' && ! str_starts_with($main, 'Data ')) {
                $currentParent = '';
            }

            if ($sub !== '' && $currentParent !== '' && ! is_numeric($sub)) {
                $headerNames[$colIdx] = "{$currentParent} - {$sub}";
            } elseif ($main !== '') {
                $headerNames[$colIdx] = $main;
            } else {
                $headerNames[$colIdx] = $sub ?: "Kolom_{$colIdx}";
            }
        }

        // Lewati sub-header bertingkat
        $dataStartIndex = $headerRowIndex + 1;
        while ($dataStartIndex < count($rows)) {
            $checkFirst = trim((string) ($rows[$dataStartIndex][0] ?? ''));
            $checkName = trim((string) ($rows[$dataStartIndex][$colMap['name'] ?? 1] ?? ''));

            if ($checkName === '' || is_numeric($checkName) || strtolower($checkName) === 'nama' || in_array(strtolower($checkFirst), ['1', 'no'])) {
                $isRealStudent = ! empty($checkName) && ! is_numeric($checkName) && ! empty($rows[$dataStartIndex][$colMap['nisn'] ?? 4] ?? '');
                if (! $isRealStudent) {
                    $dataStartIndex++;

                    continue;
                }
            }
            break;
        }

        $autoGraduateGrade12 = (bool) ($options['auto_graduate_grade_12'] ?? false);
        $autoMutasiMissing = (bool) ($options['auto_mutasi_missing_students'] ?? false);
        $autoCreateClasses = (bool) ($options['auto_create_classes'] ?? true);

        $academicYearRecord = AcademicYear::firstOrCreate(['name' => $academicYear], [
            'semester' => 'Ganjil',
            'is_active' => false,
            'is_locked' => false,
        ]);
        $academicYearId = $academicYearRecord->id;
        $startYear = (int) substr($academicYear, 0, 4);

        // Cari TP Tepat Sebelumnya (Contoh: jika target 2026/2027, TP sebelumnya adalah 2025/2026)
        $previousAcademicYearName = ($startYear - 1).'/'.$startYear;
        $previousAcademicYearRecord = AcademicYear::where('name', $previousAcademicYearName)->first();

        // Catat seluruh siswa kelas XII pada TP sebelumnya (atau TP berjalan jika upload ulang TP yang sama)
        $activeGrade12Before = [];
        if ($autoGraduateGrade12) {
            $grade12Query = Student::where('status', 'aktif')
                ->where(function ($query) use ($academicYear, $previousAcademicYearName, $previousAcademicYearRecord) {
                    $query->where(function ($q) use ($previousAcademicYearRecord, $previousAcademicYearName) {
                        if ($previousAcademicYearRecord) {
                            $q->whereHas('studentClassrooms', function ($sc) use ($previousAcademicYearRecord) {
                                $sc->where('academic_year_id', $previousAcademicYearRecord->id)
                                    ->where(function ($sub) {
                                        $sub->whereHas('classroom.gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                            ->orWhere('classroom_name', 'like', 'XII %')
                                            ->orWhere('classroom_name', 'like', '12 %');
                                    });
                            });
                        }
                        $q->orWhereHas('classroom', function ($c) use ($previousAcademicYearName) {
                            $c->where('academic_year', $previousAcademicYearName)
                                ->where(function ($sub) {
                                    $sub->whereHas('gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                        ->orWhere('name', 'like', 'XII %')
                                        ->orWhere('name', 'like', '12 %');
                                });
                        });
                    })->orWhere(function ($q) use ($academicYear) {
                        // Fallback jika belum ada data di TP sebelumnya, gunakan rombel kelas XII di TP target
                        $q->whereHas('classroom', function ($c) use ($academicYear) {
                            $c->where('academic_year', $academicYear)
                                ->where(function ($sub) {
                                    $sub->whereHas('gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                        ->orWhere('name', 'like', 'XII %')
                                        ->orWhere('name', 'like', '12 %');
                                });
                        });
                    });
                });

            $activeGrade12Before = $grade12Query->pluck('id')->all();
        }

        // Catat seluruh siswa aktif kelas X dan XI pada TP sebelumnya (atau TP berjalan)
        $activeUnderclassBefore = [];
        if ($autoMutasiMissing) {
            $underclassQuery = Student::where('status', 'aktif')
                ->where(function ($query) use ($academicYear, $previousAcademicYearName, $previousAcademicYearRecord) {
                    $query->where(function ($q) use ($previousAcademicYearRecord, $previousAcademicYearName) {
                        if ($previousAcademicYearRecord) {
                            $q->whereHas('studentClassrooms', function ($sc) use ($previousAcademicYearRecord) {
                                $sc->where('academic_year_id', $previousAcademicYearRecord->id)
                                    ->whereDoesntHave('classroom.gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                    ->where('classroom_name', 'not like', 'XII %')
                                    ->where('classroom_name', 'not like', '12 %');
                            });
                        }
                        $q->orWhereHas('classroom', function ($c) use ($previousAcademicYearName) {
                            $c->where('academic_year', $previousAcademicYearName)
                                ->whereDoesntHave('gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                ->where('name', 'not like', 'XII %')
                                ->where('name', 'not like', '12 %');
                        });
                    })->orWhere(function ($q) use ($academicYear) {
                        // Fallback jika belum ada data di TP sebelumnya, gunakan rombel kelas X/XI di TP target
                        $q->whereHas('classroom', function ($c) use ($academicYear) {
                            $c->where('academic_year', $academicYear)
                                ->whereDoesntHave('gradeLevel', fn ($gl) => $gl->where('level', 'XII'))
                                ->where('name', 'not like', 'XII %')
                                ->where('name', 'not like', '12 %');
                        });
                    });
                });

            $activeUnderclassBefore = $underclassQuery->pluck('id')->all();
        }

        $importedCount = 0;
        $updatedCount = 0;
        $promotedCount = 0;
        $skippedCount = 0;
        $processedStudentIds = [];
        $processedClassroomIds = [];
        $processedGrades = [];
        $details = [];
        $errors = [];

        for ($r = $dataStartIndex; $r < count($rows); $r++) {
            $row = $rows[$r];

            // Abaikan baris kosong
            $filtered = array_filter($row, fn ($v) => trim((string) $v) !== '');
            if (empty($filtered)) {
                continue;
            }

            $name = mb_strtoupper(trim((string) ($row[$colMap['name']] ?? '')), 'UTF-8');
            if ($name === '' || is_numeric($name)) {
                continue;
            }

            // NISN
            $nisn = trim((string) ($row[$colMap['nisn']] ?? ''));
            if (in_array(strtolower($nisn), ['-', '0', 'null', 'none']) || strlen($nisn) < 4) {
                $nisn = '';
            }

            // NIPD / NIS (Nomor Induk Peserta Didik)
            $nipd = trim((string) ($row[$colMap['nipd']] ?? ''));
            if (in_array($nipd, ['-', '0', 'null']) || strlen($nipd) < 2) {
                $nipd = '';
            }

            // NIK
            $nik = trim((string) ($row[$colMap['nik']] ?? ''));
            if (in_array($nik, ['-', '0', 'null']) || strlen($nik) < 8) {
                $nik = '';
            }

            // Jenis Kelamin
            $rawGender = strtoupper(trim((string) ($row[$colMap['gender']] ?? 'L')));
            $gender = in_array($rawGender, ['P', 'PEREMPUAN', 'WANITA']) ? 'P' : 'L';

            // Tempat & Tanggal Lahir
            $birthPlace = trim((string) ($row[$colMap['birth_place']] ?? '')) ?: null;
            $rawBirthDate = trim((string) ($row[$colMap['birth_date']] ?? ''));
            $birthDate = $this->parseDate($rawBirthDate);

            // Agama
            $religion = trim((string) ($row[$colMap['religion']] ?? '')) ?: 'Islam';

            // Alamat Lengkap
            $address = $this->composeAddress($row, $colMap);

            // Rombel Saat Ini & Matching Kelas di SIAP
            $rawRombel = trim((string) ($row[$colMap['rombel']] ?? ''));
            $classroomId = $defaultClassroomId;
            $matchedClass = null;
            if ($rawRombel !== '') {
                $matchedClass = $this->matchClassroom($classrooms, $rawRombel, $academicYear, $autoCreateClasses, $academicYearId);
                if ($matchedClass) {
                    $classroomId = $matchedClass->id;
                }
            }

            // Data Ayah
            $fatherName = trim((string) ($row[$colMap['father_name']] ?? '')) ?: null;
            $fatherJob = trim((string) ($row[$colMap['father_job']] ?? '')) ?: null;

            // Data Ibu
            $motherName = trim((string) ($row[$colMap['mother_name']] ?? '')) ?: null;
            $motherJob = trim((string) ($row[$colMap['mother_job']] ?? '')) ?: null;

            // Data Wali
            $guardianName = trim((string) ($row[$colMap['guardian_name']] ?? '')) ?: null;
            $guardianJob = trim((string) ($row[$colMap['guardian_job']] ?? '')) ?: null;

            // Nomor HP Siswa & Ortu
            $hp = trim((string) ($row[$colMap['hp'] ?? -1] ?? ''));
            $telp = trim((string) ($row[$colMap['telepon'] ?? -1] ?? ''));
            $phoneStudent = $hp ?: ($telp ?: null);
            $phoneParent = $telp ?: ($hp ?: null);

            // Payload mentah lengkap seluruh kolom Dapodik (key sesuai nama header asli)
            $dapodikPayload = [];
            foreach ($headerNames as $hIdx => $hColName) {
                $val = trim((string) ($row[$hIdx] ?? ''));
                if ($val !== '') {
                    $dapodikPayload[$hColName] = $val;
                }
            }

            // Ekstrak Kolom Terstruktur Tambahan
            $email = trim((string) ($row[$colMap['email'] ?? -1] ?? '')) ?: null;
            $postalCode = trim((string) ($row[$colMap['kode_pos'] ?? -1] ?? '')) ?: null;
            $residenceType = trim((string) ($row[$colMap['jenis_tinggal'] ?? -1] ?? '')) ?: null;
            $transportation = trim((string) ($row[$colMap['alat_transportasi'] ?? -1] ?? '')) ?: null;

            $rawDist = trim((string) ($row[$colMap['jarak_rumah'] ?? -1] ?? ''));
            $distanceKm = (is_numeric(str_replace(',', '.', $rawDist)) && (float) str_replace(',', '.', $rawDist) >= 0) ? (float) str_replace(',', '.', $rawDist) : null;

            $rawLat = trim((string) ($row[$colMap['lintang'] ?? -1] ?? ''));
            $latitude = is_numeric(str_replace(',', '.', $rawLat)) ? (float) str_replace(',', '.', $rawLat) : null;
            $rawLng = trim((string) ($row[$colMap['bujur'] ?? -1] ?? ''));
            $longitude = is_numeric(str_replace(',', '.', $rawLng)) ? (float) str_replace(',', '.', $rawLng) : null;

            $familyCardNo = trim((string) ($row[$colMap['no_kk'] ?? -1] ?? '')) ?: null;
            $birthCertNo = trim((string) ($row[$colMap['no_akta_lahir'] ?? -1] ?? '')) ?: null;

            $rawChild = trim((string) ($row[$colMap['anak_ke'] ?? -1] ?? ''));
            $childOrder = is_numeric($rawChild) ? (int) $rawChild : null;
            $rawSibl = trim((string) ($row[$colMap['jml_saudara'] ?? -1] ?? ''));
            $siblingsCount = is_numeric($rawSibl) ? (int) $rawSibl : null;

            $rawWeight = trim((string) ($row[$colMap['berat_badan'] ?? -1] ?? ''));
            $weightKg = is_numeric($rawWeight) ? (int) $rawWeight : null;
            $rawHeight = trim((string) ($row[$colMap['tinggi_badan'] ?? -1] ?? ''));
            $heightCm = is_numeric($rawHeight) ? (int) $rawHeight : null;
            $rawHead = trim((string) ($row[$colMap['lingkar_kepala'] ?? -1] ?? ''));
            $headCircumference = is_numeric($rawHead) ? (int) $rawHead : null;
            $specialNeeds = trim((string) ($row[$colMap['kebutuhan_khusus'] ?? -1] ?? '')) ?: null;

            $nationalExamNo = trim((string) ($row[$colMap['no_peserta_ujian'] ?? -1] ?? '')) ?: null;
            $diplomaSerialNo = trim((string) ($row[$colMap['no_seri_ijazah'] ?? -1] ?? '')) ?: null;
            $skhunNo = trim((string) ($row[$colMap['skhun'] ?? -1] ?? '')) ?: null;

            $rawPip = strtolower(trim((string) ($row[$colMap['layak_pip'] ?? -1] ?? '')));
            $isPipEligible = in_array($rawPip, ['ya', '1', 'true', 'layak']);
            $pipReason = trim((string) ($row[$colMap['alasan_layak_pip'] ?? -1] ?? '')) ?: null;

            $rawKip = strtolower(trim((string) ($row[$colMap['penerima_kip'] ?? -1] ?? '')));
            $kipRecipient = in_array($rawKip, ['ya', '1', 'true']);
            $kipNumber = trim((string) ($row[$colMap['nomor_kip'] ?? -1] ?? '')) ?: null;
            $kipName = trim((string) ($row[$colMap['nama_di_kip'] ?? -1] ?? '')) ?: null;
            $kksNumber = trim((string) ($row[$colMap['nomor_kks'] ?? -1] ?? '')) ?: null;

            $rawKps = strtolower(trim((string) ($row[$colMap['penerima_kps'] ?? -1] ?? '')));
            $kpsRecipient = in_array($rawKps, ['ya', '1', 'true']);
            $kpsNumber = trim((string) ($row[$colMap['no_kps'] ?? -1] ?? '')) ?: null;

            $bankName = trim((string) ($row[$colMap['bank'] ?? -1] ?? '')) ?: null;
            $bankAccountNo = trim((string) ($row[$colMap['no_rekening_bank'] ?? -1] ?? '')) ?: null;
            $bankAccountName = trim((string) ($row[$colMap['rekening_atas_nama'] ?? -1] ?? '')) ?: null;

            $fatherBirthYear = trim((string) ($row[$colMap['father_birth_year'] ?? -1] ?? '')) ?: null;
            $fatherEducation = trim((string) ($row[$colMap['father_education'] ?? -1] ?? '')) ?: null;
            $fatherIncome = trim((string) ($row[$colMap['father_income'] ?? -1] ?? '')) ?: null;
            $fatherNik = trim((string) ($row[$colMap['father_nik'] ?? -1] ?? '')) ?: null;

            $motherBirthYear = trim((string) ($row[$colMap['mother_birth_year'] ?? -1] ?? '')) ?: null;
            $motherEducation = trim((string) ($row[$colMap['mother_education'] ?? -1] ?? '')) ?: null;
            $motherIncome = trim((string) ($row[$colMap['mother_income'] ?? -1] ?? '')) ?: null;
            $motherNik = trim((string) ($row[$colMap['mother_nik'] ?? -1] ?? '')) ?: null;

            $guardianBirthYear = trim((string) ($row[$colMap['guardian_birth_year'] ?? -1] ?? '')) ?: null;
            $guardianEducation = trim((string) ($row[$colMap['guardian_education'] ?? -1] ?? '')) ?: null;
            $guardianIncome = trim((string) ($row[$colMap['guardian_income'] ?? -1] ?? '')) ?: null;
            $guardianNik = trim((string) ($row[$colMap['guardian_nik'] ?? -1] ?? '')) ?: null;

            // Catatan Tambahan (Sekolah Asal, PIP/KIP jika ada)
            $notesArr = [];
            $sekolahAsal = trim((string) ($row[$colMap['sekolah_asal'] ?? -1] ?? ''));
            if ($sekolahAsal !== '' && $sekolahAsal !== '-') {
                $notesArr[] = "Asal Sekolah: {$sekolahAsal}";
            }
            if ($kipRecipient) {
                $notesArr[] = 'Penerima KIP';
            }
            if ($isPipEligible) {
                $notesArr[] = 'Layak PIP';
            }
            $notes = ! empty($notesArr) ? implode(' | ', $notesArr) : null;

            // Cari Siswa yang Sudah Ada Berdasarkan NISN, NIK, NIPD, atau Nama + Kelas
            $student = null;
            if (! empty($nisn)) {
                $student = Student::where('nisn', $nisn)->first();
            }
            if (! $student && ! empty($nik)) {
                $student = Student::where('nik', $nik)->first();
            }
            if (! $student && ! empty($nipd)) {
                $student = Student::where('nis', $nipd)->first();
            }
            if (! $student && ! empty($classroomId)) {
                $candidate = Student::where('name', $name)
                    ->where('classroom_id', $classroomId)
                    ->first();
                if ($candidate) {
                    $hasDifferentNisn = ! empty($nisn) && ! empty($candidate->nisn) && $candidate->nisn !== $nisn;
                    $hasDifferentNik = ! empty($nik) && ! empty($candidate->nik) && $candidate->nik !== $nik;
                    $hasDifferentNis = ! empty($nipd) && ! empty($candidate->nis) && $candidate->nis !== $nipd;

                    if (! $hasDifferentNisn && ! $hasDifferentNik && ! $hasDifferentNis) {
                        $student = $candidate;
                    }
                }
            }

            $payload = [
                'nisn' => $nisn ?: ($student?->nisn),
                'nis' => $nipd ?: ($student?->nis),
                'nik' => $nik ?: ($student?->nik),
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $birthPlace ?: ($student?->birth_place),
                'birth_date' => $birthDate ?: ($student?->birth_date),
                'religion' => $religion ?: ($student?->religion),
                'address' => $address ?: ($student?->address),
                'classroom_id' => $classroomId ?: ($student?->classroom_id),
                'father_name' => $fatherName ?: ($student?->father_name),
                'father_job' => $fatherJob ?: ($student?->father_job),
                'father_phone' => $phoneParent ?: ($student?->father_phone),
                'mother_name' => $motherName ?: ($student?->mother_name),
                'mother_job' => $motherJob ?: ($student?->mother_job),
                'guardian_name' => $guardianName ?: ($student?->guardian_name),
                'guardian_job' => $guardianJob ?: ($student?->guardian_job),
                'phone_student' => $phoneStudent ?: ($student?->phone_student),
                'phone_parent' => $phoneParent ?: ($student?->phone_parent),
                'email' => $email ?: ($student?->email),
                'postal_code' => $postalCode ?: ($student?->postal_code),
                'residence_type' => $residenceType ?: ($student?->residence_type),
                'transportation' => $transportation ?: ($student?->transportation),
                'distance_to_school_km' => $distanceKm ?? ($student?->distance_to_school_km),
                'latitude' => $latitude ?? ($student?->latitude),
                'longitude' => $longitude ?? ($student?->longitude),
                'family_card_no' => $familyCardNo ?: ($student?->family_card_no),
                'birth_certificate_no' => $birthCertNo ?: ($student?->birth_certificate_no),
                'child_order' => $childOrder ?? ($student?->child_order),
                'siblings_count' => $siblingsCount ?? ($student?->siblings_count),
                'weight_kg' => $weightKg ?? ($student?->weight_kg),
                'height_cm' => $heightCm ?? ($student?->height_cm),
                'head_circumference_cm' => $headCircumference ?? ($student?->head_circumference_cm),
                'special_needs' => $specialNeeds ?: ($student?->special_needs),
                'school_origin' => $sekolahAsal ?: ($student?->school_origin),
                'national_exam_no' => $nationalExamNo ?: ($student?->national_exam_no),
                'diploma_serial_no' => $diplomaSerialNo ?: ($student?->diploma_serial_no),
                'skhun_no' => $skhunNo ?: ($student?->skhun_no),
                'is_pip_eligible' => $isPipEligible ?: ($student?->is_pip_eligible ?? false),
                'pip_reason' => $pipReason ?: ($student?->pip_reason),
                'kip_recipient' => $kipRecipient ?: ($student?->kip_recipient ?? false),
                'kip_number' => $kipNumber ?: ($student?->kip_number),
                'kip_name' => $kipName ?: ($student?->kip_name),
                'kks_number' => $kksNumber ?: ($student?->kks_number),
                'kps_recipient' => $kpsRecipient ?: ($student?->kps_recipient ?? false),
                'kps_number' => $kpsNumber ?: ($student?->kps_number),
                'bank_name' => $bankName ?: ($student?->bank_name),
                'bank_account_no' => $bankAccountNo ?: ($student?->bank_account_no),
                'bank_account_name' => $bankAccountName ?: ($student?->bank_account_name),
                'father_birth_year' => $fatherBirthYear ?: ($student?->father_birth_year),
                'father_education' => $fatherEducation ?: ($student?->father_education),
                'father_income' => $fatherIncome ?: ($student?->father_income),
                'father_nik' => $fatherNik ?: ($student?->father_nik),
                'mother_birth_year' => $motherBirthYear ?: ($student?->mother_birth_year),
                'mother_education' => $motherEducation ?: ($student?->mother_education),
                'mother_income' => $motherIncome ?: ($student?->mother_income),
                'mother_nik' => $motherNik ?: ($student?->mother_nik),
                'guardian_birth_year' => $guardianBirthYear ?: ($student?->guardian_birth_year),
                'guardian_education' => $guardianEducation ?: ($student?->guardian_education),
                'guardian_income' => $guardianIncome ?: ($student?->guardian_income),
                'guardian_nik' => $guardianNik ?: ($student?->guardian_nik),
                'notes' => $notes ?: ($student?->notes),
                'dapodik_payload' => ! empty($dapodikPayload) ? $dapodikPayload : ($student?->dapodik_payload),
                'status' => 'aktif',
                'is_active' => true,
            ];

            // Deteksi tingkatan kelas siswa
            $gradeLevel = $matchedClass?->gradeLevel?->level ?? null;
            if (! $gradeLevel && $matchedClass) {
                if (str_starts_with($matchedClass->name, 'XII') || str_starts_with($matchedClass->name, '12')) {
                    $gradeLevel = 'XII';
                } elseif (str_starts_with($matchedClass->name, 'XI') || str_starts_with($matchedClass->name, '11')) {
                    $gradeLevel = 'XI';
                } elseif (str_starts_with($matchedClass->name, 'X') || str_starts_with($matchedClass->name, '10')) {
                    $gradeLevel = 'X';
                }
            }
            if (! $gradeLevel && ! empty($rawRombel)) {
                if (str_starts_with($rawRombel, 'XII') || str_starts_with($rawRombel, '12')) {
                    $gradeLevel = 'XII';
                } elseif (str_starts_with($rawRombel, 'XI') || str_starts_with($rawRombel, '11')) {
                    $gradeLevel = 'XI';
                } elseif (str_starts_with($rawRombel, 'X') || str_starts_with($rawRombel, '10')) {
                    $gradeLevel = 'X';
                }
            }

            // Hitung estimasi Tahun Angkatan Masuk (entry_year) jika belum ada
            if (empty($student?->entry_year)) {
                $calculatedEntryYear = match ($gradeLevel) {
                    'XII' => $startYear - 2,
                    'XI' => $startYear - 1,
                    default => $startYear,
                };
                $payload['entry_year'] = $calculatedEntryYear;
            }

            // PROTEKSI INTEGRITAS DATA:
            // Jika siswa sudah berstatus mutasi, keluar, atau lulus resmi di SIAP,
            // upload Dapodik dilarang keras mengubah statusnya kembali menjadi aktif!
            if ($student && in_array($student->status, ['mutasi', 'keluar', 'lulus'])) {
                unset($payload['status'], $payload['is_active']);
                if ($student->classroom_id === null) {
                    unset($payload['classroom_id']);
                }
            }

            // Integrasi Konteks Tahun:
            // Jika yang di-upload adalah TP lampau/arsip dan siswa sudah memiliki data kelas di TP yang lebih baru,
            // jangan menimpa students.classroom_id utama (agar tidak mendegradasi kelas siswa di TP aktif berjalan).
            $currentSystemMasterYear = SchoolSetting::getMasterYear();
            if ($student && $student->classroom_id && $academicYear !== $currentSystemMasterYear) {
                // Cek apakah siswa sudah memiliki riwayat di TP yang lebih tinggi/baru
                $hasNewerEnrollment = StudentClassroom::where('student_id', $student->id)
                    ->where('academic_year_id', '>', $academicYearId)
                    ->exists();
                if ($hasNewerEnrollment) {
                    unset($payload['classroom_id'], $payload['status'], $payload['is_active']);
                }
            }

            try {
                DB::transaction(function () use (
                    $student,
                    $payload,
                    $classroomId,
                    $gradeLevel,
                    $academicYearId,
                    $academicYear,
                    $matchedClass,
                    $name,
                    &$importedCount,
                    &$updatedCount,
                    &$promotedCount,
                    &$processedStudentIds,
                    &$processedClassroomIds,
                    &$processedGrades,
                    &$details
                ) {
                    $targetStudent = null;
                    $localPromoted = false;
                    $localUpdated = false;
                    $localImported = false;
                    $localDetail = null;

                    if ($student) {
                        $oldClassroomId = $student->classroom_id;
                        $oldClassName = $student->classroom?->name;

                        if ($classroomId && $oldClassroomId && $oldClassroomId != $classroomId && isset($payload['classroom_id'])) {
                            $payload['last_classroom_name'] = $oldClassName;
                            $localPromoted = true;
                            $targetClassName = $matchedClass?->name ?? 'Rombel Baru';
                            $localDetail = "{$name} (Naik/Pindah Rombel dari {$oldClassName} ke {$targetClassName})";
                        } else {
                            $localUpdated = true;
                            $localDetail = "{$name} (Diperbarui Biodata)";
                        }

                        $student->update($payload);
                        $targetStudent = $student;
                    } else {
                        $newStudent = Student::create($payload);
                        $targetStudent = $newStudent;
                        $localImported = true;
                        $localDetail = "{$name} (Siswa Baru Ditambahkan)";
                    }

                    // Catat / sinkronkan riwayat penempatan rombel siswa (student_classrooms)
                    if ($targetStudent && $classroomId && $academicYearId) {
                        // Deteksi apakah siswa mengulang (tinggal kelas)
                        $isRepeating = false;
                        $prevEnrollment = StudentClassroom::where('student_id', $targetStudent->id)
                            ->where('academic_year_id', '<', $academicYearId)
                            ->latest('academic_year_id')
                            ->with('classroom.gradeLevel')
                            ->first();

                        if ($prevEnrollment && $matchedClass) {
                            $prevGrade = $prevEnrollment->classroom?->gradeLevel?->level;
                            $currGrade = $matchedClass->gradeLevel?->level;
                            if ($prevGrade && $currGrade && $prevGrade === $currGrade) {
                                $isRepeating = true;
                            }
                        }

                        StudentClassroom::updateOrCreate(
                            [
                                'student_id' => $targetStudent->id,
                                'academic_year_id' => $academicYearId,
                            ],
                            [
                                'classroom_id' => $classroomId,
                                'status' => $targetStudent->status ?: 'aktif',
                                'is_repeating' => $isRepeating,
                                'academic_year_name' => $academicYear,
                                'classroom_name' => $matchedClass?->name ?? 'Rombel',
                            ]
                        );
                    }

                    // Hanya perbarui metrik dan log memori jika seluruh penulisan database siswa ini berhasil!
                    if ($localImported) {
                        $importedCount++;
                    } elseif ($localPromoted) {
                        $promotedCount++;
                    } elseif ($localUpdated) {
                        $updatedCount++;
                    }

                    $processedStudentIds[] = $targetStudent->id;
                    if ($classroomId) {
                        $processedClassroomIds[$classroomId] = true;
                    }
                    if ($gradeLevel) {
                        $processedGrades[$gradeLevel] = true;
                    }
                    if ($localDetail) {
                        $details[] = $localDetail;
                    }
                });
            } catch (Throwable $e) {
                $errors[] = 'Baris '.($r + 1)." ({$name}): ".$e->getMessage();
                $skippedCount++;
            }
        }

        // Luluskan Otomatis Siswa Kelas XII dari TP sebelumnya yang Tidak Ada di File Dapodik Baru
        $graduatedCount = 0;
        if ($autoGraduateGrade12 && ! empty($activeGrade12Before) && ! empty($processedStudentIds)) {
            $isSingleClassroomScope = $defaultClassroomId !== null || count($processedClassroomIds) <= 1;
            $hasSeniorGradeInFile = ! empty($processedGrades['XII']) || ! empty($processedGrades['XI']);

            if ($skippedCount > 0) {
                $details[] = "Kelulusan otomatis kelas XII ditangguhkan karena terdapat {$skippedCount} baris siswa yang dilewati atau gagal diproses.";
                $errors[] = "Kelulusan otomatis kelas XII ditangguhkan demi integritas data karena berkas mengandung {$skippedCount} baris bermasalah.";
            } elseif ($isSingleClassroomScope) {
                $details[] = 'Kelulusan otomatis kelas XII ditangguhkan karena berkas terdeteksi sebagai berkas parsial (hanya mencakup satu rombel).';
                $errors[] = 'Kelulusan otomatis kelas XII ditangguhkan untuk mencegah siswa alumni salah kelulusan pada import parsial.';
            } elseif (! $hasSeniorGradeInFile) {
                $details[] = 'Kelulusan otomatis kelas XII ditangguhkan karena berkas hanya memuat rombel kelas X (PPDB / berkas tingkat bawah).';
                $errors[] = 'Kelulusan otomatis kelas XII ditangguhkan karena berkas tidak memuat tingkatan kelas atas.';
            } else {
                $graduatingStudentIds = array_diff($activeGrade12Before, $processedStudentIds);

                if (! empty($graduatingStudentIds)) {
                    try {
                        DB::transaction(function () use ($graduatingStudentIds, $previousAcademicYearName, $academicYear, &$graduatedCount, &$details) {
                            $graduatingStudents = Student::whereIn('id', $graduatingStudentIds)
                                ->with('classroom')
                                ->get();

                            foreach ($graduatingStudents as $gStudent) {
                                $lastClass = $gStudent->classroom?->name ?? 'Kelas XII';
                                $gradYear = $previousAcademicYearName ?? $gStudent->classroom?->academic_year ?? $academicYear;

                                $gStudent->update([
                                    'status' => 'lulus',
                                    'is_active' => false,
                                    'classroom_id' => null,
                                    'last_classroom_name' => $lastClass,
                                    'graduation_year' => $gradYear,
                                ]);

                                $graduatedCount++;
                                $details[] = "{$gStudent->name} (Lulus Otomatis ke Alumni - Terakhir {$lastClass})";
                            }
                        });
                    } catch (Throwable $e) {
                        $errors[] = 'Gagal memproses kelulusan otomatis kelas XII: '.$e->getMessage();
                    }
                }
            }
        }

        // Pindahkan Otomatis Siswa Kelas X & XI dari TP sebelumnya yang Tidak Ada di File Dapodik Baru ke Mutasi / Keluar
        $mutatedCount = 0;
        if ($autoMutasiMissing && ! empty($activeUnderclassBefore) && ! empty($processedStudentIds)) {
            $isSingleClassroomScope = $defaultClassroomId !== null || count($processedClassroomIds) <= 1;

            $hasActiveGrade11 = Classroom::where('is_active', true)
                ->where(function ($q) {
                    $q->whereHas('gradeLevel', fn ($gl) => $gl->where('level', 'XI'))
                        ->orWhere('name', 'like', 'XI %')
                        ->orWhere('name', 'like', '11 %');
                })->exists();

            $totalUnderclassActive = count($activeUnderclassBefore);
            $processedUnderclassCount = count(array_intersect($activeUnderclassBefore, $processedStudentIds));

            if ($skippedCount > 0) {
                $details[] = "Mutasi otomatis siswa tidak terdaftar ditangguhkan karena terdapat {$skippedCount} baris siswa yang dilewati atau gagal diproses.";
                $errors[] = "Mutasi otomatis siswa ditangguhkan demi integritas data karena berkas mengandung {$skippedCount} baris bermasalah.";
            } elseif ($isSingleClassroomScope) {
                $details[] = 'Mutasi otomatis siswa tidak terdaftar ditangguhkan karena berkas terdeteksi sebagai berkas parsial (hanya mencakup satu rombel).';
                $errors[] = 'Mutasi otomatis ditangguhkan untuk melindungi siswa aktif di rombel lain.';
            } elseif ($hasActiveGrade11 && empty($processedGrades['XI'])) {
                $details[] = 'Mutasi otomatis siswa tidak terdaftar ditangguhkan karena berkas tidak memuat siswa kelas XI sementara rombel kelas XI aktif terdaftar di sistem.';
                $errors[] = 'Mutasi otomatis ditangguhkan untuk mencegah siswa kelas XI termutasi secara keliru.';
            } elseif ($totalUnderclassActive >= 10 && $processedUnderclassCount < ($totalUnderclassActive * 0.5)) {
                $details[] = "Mutasi otomatis siswa tidak terdaftar ditangguhkan karena jumlah siswa aktif yang cocok dalam berkas ({$processedUnderclassCount}) kurang dari 50% dari total populasi siswa aktif ({$totalUnderclassActive}).";
                $errors[] = 'Mutasi otomatis ditangguhkan demi keamanan data (ambang batas anomali terlampaui).';
            } else {
                $mutatingStudentIds = array_diff($activeUnderclassBefore, $processedStudentIds);

                if (! empty($mutatingStudentIds)) {
                    try {
                        DB::transaction(function () use ($mutatingStudentIds, $academicYear, &$mutatedCount, &$details) {
                            $mutatingStudents = Student::whereIn('id', $mutatingStudentIds)
                                ->with('classroom')
                                ->get();

                            foreach ($mutatingStudents as $mStudent) {
                                $lastClass = $mStudent->classroom?->name ?? 'Kelas X/XI';

                                $mStudent->update([
                                    'status' => 'mutasi',
                                    'is_active' => false,
                                    'classroom_id' => null,
                                    'last_classroom_name' => $lastClass,
                                    'notes' => trim(($mStudent->notes ? $mStudent->notes.' | ' : '').'Tidak terdaftar di Dapodik baru (Mutasi/Keluar)'),
                                ]);

                                // Catat ke tabel riwayat mutasi resmi siswa jika belum ada
                                StudentMutation::firstOrCreate(
                                    [
                                        'student_id' => $mStudent->id,
                                        'type' => 'keluar',
                                    ],
                                    [
                                        'mutation_date' => now()->toDateString(),
                                        'reason_category' => 'Lainnya',
                                        'reason_description' => "Tidak terdaftar pada Dapodik Tahun Pelajaran {$academicYear} (Sistem Otomatis)",
                                        'recorded_by' => auth()->id() ?? 1,
                                    ]
                                );

                                $mutatedCount++;
                                $details[] = "{$mStudent->name} (Mutasi/Keluar Otomatis - Terakhir {$lastClass})";
                            }
                        });
                    } catch (Throwable $e) {
                        $errors[] = 'Gagal memproses mutasi otomatis siswa tidak terdaftar: '.$e->getMessage();
                    }
                }
            }
        }

        $totalProcessed = $importedCount + $updatedCount + $promotedCount;

        return [
            'success' => $totalProcessed > 0 || $graduatedCount > 0 || $mutatedCount > 0,
            'total_processed' => $totalProcessed,
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'promoted_count' => $promotedCount,
            'graduated_count' => $graduatedCount,
            'mutated_count' => $mutatedCount,
            'skipped_count' => $skippedCount,
            'details' => $details,
            'errors' => $errors,
        ];
    }

    /**
     * Membangun pemetaan index kolom berdasarkan baris header Dapodik.
     *
     * @param  array<int, string>  $headerRow
     * @return array<string, int>
     */
    protected function buildColumnMap(array $headerRow): array
    {
        // Default index jika sesuai urutan standar Dapodik 66 kolom
        $map = [
            'name' => 1,
            'nipd' => 2,
            'gender' => 3,
            'nisn' => 4,
            'birth_place' => 5,
            'birth_date' => 6,
            'nik' => 7,
            'religion' => 8,
            'address' => 9,
            'rt' => 10,
            'rw' => 11,
            'dusun' => 12,
            'kelurahan' => 13,
            'kecamatan' => 14,
            'kode_pos' => 15,
            'jenis_tinggal' => 16,
            'alat_transportasi' => 17,
            'telepon' => 18,
            'hp' => 19,
            'email' => 20,
            'skhun' => 21,
            'penerima_kps' => 22,
            'no_kps' => 23,
            'father_name' => 24,
            'father_birth_year' => 25,
            'father_education' => 26,
            'father_job' => 27,
            'father_income' => 28,
            'father_nik' => 29,
            'mother_name' => 30,
            'mother_birth_year' => 31,
            'mother_education' => 32,
            'mother_job' => 33,
            'mother_income' => 34,
            'mother_nik' => 35,
            'guardian_name' => 36,
            'guardian_birth_year' => 37,
            'guardian_education' => 38,
            'guardian_job' => 39,
            'guardian_income' => 40,
            'guardian_nik' => 41,
            'rombel' => 42,
            'no_peserta_ujian' => 43,
            'no_seri_ijazah' => 44,
            'penerima_kip' => 45,
            'nomor_kip' => 46,
            'nama_di_kip' => 47,
            'nomor_kks' => 48,
            'no_akta_lahir' => 49,
            'bank' => 50,
            'no_rekening_bank' => 51,
            'rekening_atas_nama' => 52,
            'layak_pip' => 53,
            'alasan_layak_pip' => 54,
            'kebutuhan_khusus' => 55,
            'sekolah_asal' => 56,
            'anak_ke' => 57,
            'lintang' => 58,
            'bujur' => 59,
            'no_kk' => 60,
            'berat_badan' => 61,
            'tinggi_badan' => 62,
            'lingkar_kepala' => 63,
            'jml_saudara' => 64,
            'jarak_rumah' => 65,
        ];

        // Pemetaan dinamis berbasis teks header jika urutan kolom berbeda
        foreach ($headerRow as $idx => $cell) {
            $lower = strtolower(trim((string) $cell));
            if ($lower === '') {
                continue;
            }

            if ($lower === 'nama' || (str_contains($lower, 'nama') && ! str_contains($lower, 'ayah') && ! str_contains($lower, 'ibu') && ! str_contains($lower, 'wali') && ! str_contains($lower, 'kip') && ! str_contains($lower, 'rekening'))) {
                $map['name'] = $idx;
            } elseif (str_contains($lower, 'nipd')) {
                $map['nipd'] = $idx;
            } elseif ($lower === 'jk' || str_contains($lower, 'kelamin')) {
                $map['gender'] = $idx;
            } elseif (str_contains($lower, 'nisn')) {
                $map['nisn'] = $idx;
            } elseif (str_contains($lower, 'tempat lahir')) {
                $map['birth_place'] = $idx;
            } elseif (str_contains($lower, 'tanggal lahir')) {
                $map['birth_date'] = $idx;
            } elseif ($lower === 'nik' || (str_contains($lower, 'nik') && ! str_contains($lower, 'ayah') && ! str_contains($lower, 'ibu') && ! str_contains($lower, 'wali'))) {
                $map['nik'] = $idx;
            } elseif (str_contains($lower, 'agama')) {
                $map['religion'] = $idx;
            } elseif ($lower === 'alamat') {
                $map['address'] = $idx;
            } elseif ($lower === 'rt') {
                $map['rt'] = $idx;
            } elseif ($lower === 'rw') {
                $map['rw'] = $idx;
            } elseif (str_contains($lower, 'dusun')) {
                $map['dusun'] = $idx;
            } elseif (str_contains($lower, 'kelurahan') || str_contains($lower, 'desa')) {
                $map['kelurahan'] = $idx;
            } elseif (str_contains($lower, 'kecamatan')) {
                $map['kecamatan'] = $idx;
            } elseif (str_contains($lower, 'kode pos')) {
                $map['kode_pos'] = $idx;
            } elseif (str_contains($lower, 'jenis tinggal')) {
                $map['jenis_tinggal'] = $idx;
            } elseif (str_contains($lower, 'transportasi')) {
                $map['alat_transportasi'] = $idx;
            } elseif (str_contains($lower, 'telepon') && ! str_contains($lower, 'hp')) {
                $map['telepon'] = $idx;
            } elseif ($lower === 'hp' || str_contains($lower, 'nomor hp') || str_contains($lower, 'no hp')) {
                $map['hp'] = $idx;
            } elseif ($lower === 'email' || str_contains($lower, 'e-mail')) {
                $map['email'] = $idx;
            } elseif (str_contains($lower, 'skhun')) {
                $map['skhun'] = $idx;
            } elseif (str_contains($lower, 'penerima kps')) {
                $map['penerima_kps'] = $idx;
            } elseif (str_contains($lower, 'no. kps') || str_contains($lower, 'nomor kps')) {
                $map['no_kps'] = $idx;
            } elseif (str_contains($lower, 'nama ayah')) {
                $map['father_name'] = $idx;
            } elseif (str_contains($lower, 'pekerjaan ayah')) {
                $map['father_job'] = $idx;
            } elseif (str_contains($lower, 'nama ibu')) {
                $map['mother_name'] = $idx;
            } elseif (str_contains($lower, 'pekerjaan ibu')) {
                $map['mother_job'] = $idx;
            } elseif (str_contains($lower, 'nama wali')) {
                $map['guardian_name'] = $idx;
            } elseif (str_contains($lower, 'pekerjaan wali')) {
                $map['guardian_job'] = $idx;
            } elseif (str_contains($lower, 'rombel')) {
                $map['rombel'] = $idx;
            } elseif (str_contains($lower, 'no peserta ujian') || str_contains($lower, 'ujian nasional')) {
                $map['no_peserta_ujian'] = $idx;
            } elseif (str_contains($lower, 'seri ijazah')) {
                $map['no_seri_ijazah'] = $idx;
            } elseif (str_contains($lower, 'penerima kip')) {
                $map['penerima_kip'] = $idx;
            } elseif (str_contains($lower, 'nomor kip') || str_contains($lower, 'no kip')) {
                $map['nomor_kip'] = $idx;
            } elseif (str_contains($lower, 'nama di kip')) {
                $map['nama_di_kip'] = $idx;
            } elseif (str_contains($lower, 'nomor kks') || str_contains($lower, 'no kks')) {
                $map['nomor_kks'] = $idx;
            } elseif (str_contains($lower, 'akta lahir')) {
                $map['no_akta_lahir'] = $idx;
            } elseif ($lower === 'bank') {
                $map['bank'] = $idx;
            } elseif (str_contains($lower, 'nomor rekening') || str_contains($lower, 'no rekening')) {
                $map['no_rekening_bank'] = $idx;
            } elseif (str_contains($lower, 'rekening atas nama')) {
                $map['rekening_atas_nama'] = $idx;
            } elseif (str_contains($lower, 'layak pip')) {
                $map['layak_pip'] = $idx;
            } elseif (str_contains($lower, 'alasan layak pip')) {
                $map['alasan_layak_pip'] = $idx;
            } elseif (str_contains($lower, 'kebutuhan khusus')) {
                $map['kebutuhan_khusus'] = $idx;
            } elseif (str_contains($lower, 'sekolah asal')) {
                $map['sekolah_asal'] = $idx;
            } elseif (str_contains($lower, 'anak ke')) {
                $map['anak_ke'] = $idx;
            } elseif (str_contains($lower, 'lintang')) {
                $map['lintang'] = $idx;
            } elseif (str_contains($lower, 'bujur')) {
                $map['bujur'] = $idx;
            } elseif (str_contains($lower, 'no kk') || str_contains($lower, 'kartu keluarga')) {
                $map['no_kk'] = $idx;
            } elseif (str_contains($lower, 'berat badan')) {
                $map['berat_badan'] = $idx;
            } elseif (str_contains($lower, 'tinggi badan')) {
                $map['tinggi_badan'] = $idx;
            } elseif (str_contains($lower, 'lingkar kepala')) {
                $map['lingkar_kepala'] = $idx;
            } elseif (str_contains($lower, 'saudara')) {
                $map['jml_saudara'] = $idx;
            } elseif (str_contains($lower, 'jarak')) {
                $map['jarak_rumah'] = $idx;
            }
        }

        return $map;
    }

    /**
     * Menggabungkan komponen alamat jalan, RT, RW, Dusun, Desa, Kecamatan menjadi alamat lengkap yang rapi.
     *
     * @param  array<int, string>  $row
     * @param  array<string, int>  $colMap
     */
    protected function composeAddress(array $row, array $colMap): ?string
    {
        $parts = [];

        $mainAddr = trim((string) ($row[$colMap['address'] ?? -1] ?? ''));
        if ($mainAddr !== '' && $mainAddr !== '-') {
            $parts[] = $mainAddr;
        }

        $rt = trim((string) ($row[$colMap['rt'] ?? -1] ?? ''));
        $rw = trim((string) ($row[$colMap['rw'] ?? -1] ?? ''));
        if ($rt !== '' && $rt !== '-' || $rw !== '' && $rw !== '-') {
            $rtStr = ($rt !== '' && $rt !== '-') ? "RT {$rt}" : '';
            $rwStr = ($rw !== '' && $rw !== '-') ? "RW {$rw}" : '';
            $parts[] = trim("{$rtStr} {$rwStr}");
        }

        $dusun = trim((string) ($row[$colMap['dusun'] ?? -1] ?? ''));
        if ($dusun !== '' && $dusun !== '-') {
            $parts[] = str_starts_with(strtolower($dusun), 'dsn') ? $dusun : "Dsn. {$dusun}";
        }

        $desa = trim((string) ($row[$colMap['kelurahan'] ?? -1] ?? ''));
        if ($desa !== '' && $desa !== '-') {
            $parts[] = str_starts_with(strtolower($desa), 'desa') || str_starts_with(strtolower($desa), 'kel') ? $desa : "Ds. {$desa}";
        }

        $kec = trim((string) ($row[$colMap['kecamatan'] ?? -1] ?? ''));
        if ($kec !== '' && $kec !== '-') {
            $parts[] = str_starts_with(strtolower($kec), 'kec') ? $kec : "Kec. {$kec}";
        }

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Cocokkan nama Rombel Dapodik ke Classroom SIAP.
     * Contoh:
     * - "XII TKR 1" -> "XII TKR 1"
     * - "X AK" -> "X AKL 1"
     * - "XI TKJ" -> "XI TKJ 1"
     */
    public function matchClassroom(Collection &$classrooms, string $rawRombel, ?string $academicYear = null, bool $autoCreate = false, ?int $academicYearId = null): ?Classroom
    {
        $clean = trim($rawRombel);
        if ($clean === '') {
            return null;
        }

        // 1. Pencocokan langsung (exact)
        $noSpaceInput = strtolower((string) preg_replace('/[\s\-_]/', '', $clean));
        $exact = $classrooms->first(function ($c) use ($clean, $noSpaceInput) {
            $cNoSpace = strtolower((string) preg_replace('/[\s\-_]/', '', $c->name));

            return strcasecmp($c->name, $clean) === 0 || $cNoSpace === $noSpaceInput;
        });

        if ($exact) {
            return $exact;
        }

        // 2. Normalisasi Tingkat (X, XI, XII)
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

        // 4. Deteksi nomor rombel (1, 2, 3, 4)
        $withoutGrade = preg_replace('/(^|\b)(xii|xi|x|12|11|10)(\b|$)/i', '', $clean);
        $number = '1';
        if (preg_match('/\b([1-4])\b/', (string) $withoutGrade, $m)) {
            $number = $m[1];
        }

        $targetName = "{$grade} {$major} {$number}";
        $matched = $classrooms->first(function ($c) use ($targetName) {
            return strcasecmp($c->name, $targetName) === 0;
        });

        if ($matched) {
            return $matched;
        }

        // Jika rombel spesifik (misal X TKR 2, X TKR 3) belum ada dan autoCreate aktif, buat rombel persis tersebut!
        if ($autoCreate && $academicYear) {
            $gl = GradeLevel::where('level', $grade)->first();

            $deptQuery = Department::query();
            if ($major === 'TKR') {
                $deptQuery->where(fn ($q) => $q->where('name', 'like', '%Kendaraan%')->orWhere('name', 'like', '%TKR%')->orWhere('code', 'TKR'));
            } elseif ($major === 'TKJ') {
                $deptQuery->where(fn ($q) => $q->where('name', 'like', '%Jaringan%')->orWhere('name', 'like', '%TKJ%')->orWhere('code', 'TKJ'));
            } elseif ($major === 'AKL') {
                $deptQuery->where(fn ($q) => $q->where('name', 'like', '%Akuntansi%')->orWhere('name', 'like', '%AK%')->orWhere('code', 'AKL'));
            }
            $dept = $deptQuery->first() ?? Department::first();

            $newClass = Classroom::firstOrCreate([
                'name' => $targetName,
                'academic_year' => $academicYear,
            ], [
                'academic_year_id' => $academicYearId,
                'grade_level_id' => $gl?->id,
                'department_id' => $dept?->id,
                'is_active' => true,
            ]);

            if ($academicYearId && ! $newClass->academic_year_id) {
                $newClass->update(['academic_year_id' => $academicYearId]);
            }

            $classrooms->push($newClass);

            return $newClass;
        }

        // Fallback terakhir jika autoCreate tidak aktif: cari kelas pertama dengan tingkat & jurusan sama
        $firstPrefix = $classrooms->first(function ($c) use ($grade, $major) {
            return str_starts_with(strtoupper($c->name), "{$grade} {$major}");
        });

        if ($firstPrefix) {
            return $firstPrefix;
        }

        return null;
    }

    /**
     * Parse date string into Y-m-d format safely.
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

        // 3. Format serial date dari Excel jika tersimpan numerik
        if (is_numeric($rawDate) && (int) $rawDate > 10000 && (int) $rawDate < 80000) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) $rawDate)->format('Y-m-d');
            } catch (Throwable) {
                // Ignore fallback
            }
        }

        return null;
    }
}
