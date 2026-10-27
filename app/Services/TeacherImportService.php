<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherImportService extends BaseExcelImportService
{
    /**
     * Import data guru / GTK dari file Excel (.xlsx) atau CSV (.csv).
     *
     * @return array{success: bool, imported_count: int, updated_count: int, updated: array<string>, errors: array<string>}
     */
    public function import(string $filePath, string $extension): array
    {
        $rows = $this->parseFile($filePath, $extension);

        if (empty($rows)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'updated_count' => 0,
                'updated' => [],
                'errors' => ['File kosong atau format data tidak dapat dibaca.'],
            ];
        }

        $guruRole = Role::where('slug', 'guru')->first();
        $kepsekRole = Role::where('slug', 'kepala_sekolah')->first();
        $wakaRole = Role::where('slug', 'waka_kurikulum')->first();
        $supervisorRole = Role::where('slug', 'supervisor')->first();

        $defaultRoleId = $guruRole?->id ?? 1;

        $setting = SchoolSetting::getSetting();
        $schoolDomain = 'smkpgrikras.sch.id';
        if (! empty($setting->school_email) && str_contains($setting->school_email, '@')) {
            $parts = explode('@', $setting->school_email);
            if (! empty($parts[1])) {
                $schoolDomain = $parts[1];
            }
        }

        // Cari baris header di 10 baris pertama
        $startIndex = 0;
        $headerIndices = ['has_header' => false];
        $maxScan = min(10, count($rows));

        for ($r = 0; $r < $maxScan; $r++) {
            $candidate = $this->detectHeaderColumns($rows[$r]);
            if ($candidate['has_header']) {
                $headerIndices = $candidate;
                $startIndex = $r + 1;
                break;
            }
        }

        if (! $headerIndices['has_header']) {
            $headerIndices = $this->detectHeaderColumns($rows[0]);
            if ($headerIndices['has_header']) {
                $startIndex = 1;
            }
        }

        $importedCount = 0;
        $updatedCount = 0;
        $updatedList = [];
        $errorList = [];

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];

            $name = $this->getCellValue($row, $headerIndices['name_col'] ?? -1);
            if (empty($name)) {
                continue;
            }

            // Bersihkan nama guru (misal awalan nomor "1. " atau sejenisnya)
            $cleanName = preg_replace('/^\d+[\.\-\s]+/', '', $name);
            $cleanName = trim($cleanName);

            if (empty($cleanName)) {
                continue;
            }

            $nigsRaw = $this->getCellValue($row, $headerIndices['nigs_col'] ?? -1);
            $nigsFixRaw = $this->getCellValue($row, $headerIndices['nigs_fix_col'] ?? -1);
            $nigs = ! empty($nigsFixRaw) ? preg_replace('/[^a-zA-Z0-9]/', '', $nigsFixRaw) : (! empty($nigsRaw) ? preg_replace('/[^a-zA-Z0-9]/', '', $nigsRaw) : null);

            $nip = $this->getCellValue($row, $headerIndices['nip_col'] ?? -1);
            $nuptk = $this->getCellValue($row, $headerIndices['nuptk_col'] ?? -1);
            $nik = $this->getCellValue($row, $headerIndices['nik_col'] ?? -1);
            $phone = $this->getCellValue($row, $headerIndices['phone_col'] ?? -1);
            $gender = $this->getCellValue($row, $headerIndices['gender_col'] ?? -1);
            if (! empty($gender)) {
                $gUpper = strtoupper(trim($gender));
                $gender = str_starts_with($gUpper, 'P') ? 'P' : 'L';
            }

            $birthInfo = $this->getCellValue($row, $headerIndices['birth_info_col'] ?? -1);
            $gtkStatus = $this->getCellValue($row, $headerIndices['gtk_status_col'] ?? -1);
            $position = $this->getCellValue($row, $headerIndices['position_col'] ?? -1);
            if (empty($position)) {
                $position = 'Guru';
            }

            $educationLevel = $this->getCellValue($row, $headerIndices['education_level_col'] ?? -1);
            $major = $this->getCellValue($row, $headerIndices['major_col'] ?? -1);
            $address = $this->getCellValue($row, $headerIndices['address_col'] ?? -1);
            $shortName = $this->getCellValue($row, $headerIndices['short_name_col'] ?? -1);
            if (! empty($shortName)) {
                $shortName = strtoupper(trim($shortName));
            }

            $email = $this->getCellValue($row, $headerIndices['email_col'] ?? -1);
            $username = $this->getCellValue($row, $headerIndices['username_col'] ?? -1);
            $rawPassword = $this->getCellValue($row, $headerIndices['password_col'] ?? -1);

            // Tentukan role berdasarkan jabatan
            $roleId = $defaultRoleId;
            $posUpper = strtoupper($position);
            if (str_contains($posUpper, 'KEPALA SEKOLAH')) {
                $roleId = $kepsekRole?->id ?? $roleId;
            } elseif (str_contains($posUpper, 'WAKA KURIKULUM') || str_contains($posUpper, 'WAKASEK KURIKULUM')) {
                $roleId = $wakaRole?->id ?? $roleId;
            } elseif (str_contains($posUpper, 'SUPERVISOR')) {
                $roleId = $supervisorRole?->id ?? $roleId;
            }

            // Cari apakah guru sudah ada di database
            $existing = null;
            if (! empty($nigs)) {
                $existing = User::where('nigs', $nigs)->first();
            }
            if (! $existing && ! empty($nip)) {
                $existing = User::where('nip', $nip)->first();
            }
            if (! $existing && ! empty($nik)) {
                $existing = User::where('nik', $nik)->first();
            }
            if (! $existing && ! empty($nuptk)) {
                $existing = User::where('nuptk', $nuptk)->first();
            }
            if (! $existing) {
                $existing = User::whereRaw('LOWER(name) = ?', [strtolower($cleanName)])->first();
            }

            if ($existing) {
                // Update guru yang sudah ada (TIDAK PERNAH MENIMPA PASSWORD)
                $updateData = [
                    'name' => $cleanName,
                    'is_active' => true,
                ];

                if (! empty($nigs)) {
                    $updateData['nigs'] = $nigs;
                }
                if (! empty($nip)) {
                    $updateData['nip'] = $nip;
                }
                if (! empty($nuptk)) {
                    $updateData['nuptk'] = $nuptk;
                }
                if (! empty($nik)) {
                    $updateData['nik'] = $nik;
                }
                if (! empty($phone)) {
                    $updateData['phone'] = $phone;
                }
                if (! empty($gender)) {
                    $updateData['gender'] = $gender;
                }
                if (! empty($birthInfo)) {
                    $updateData['birth_info'] = $birthInfo;
                }
                if (! empty($gtkStatus)) {
                    $updateData['gtk_status'] = $gtkStatus;
                }
                if (! empty($position)) {
                    $groupPosition = User::isStaffPosition($position) ? 'staf' : (str_contains(strtolower($position), 'administrator') ? 'administrator' : 'guru');
                    $updateData['position'] = $groupPosition;
                }
                if (! empty($educationLevel)) {
                    $updateData['education_level'] = $educationLevel;
                }
                if (! empty($major)) {
                    $updateData['major'] = $major;
                }
                if (! empty($address)) {
                    $updateData['address'] = $address;
                }
                if (! empty($shortName)) {
                    $updateData['short_name'] = $shortName;
                }

                $existing->update($updateData);
                $updatedList[] = $existing->name.' (diperbarui)';
                $updatedCount++;
            } else {
                // Guru baru, generate username dan email unik
                if (empty($username)) {
                    if (! empty($nigsFix)) {
                        $username = 'guru_'.$nigsFix;
                    } elseif (! empty($nip)) {
                        $username = 'guru_'.substr(preg_replace('/[^0-9]/', '', $nip), -6);
                    } else {
                        $slug = Str::slug($cleanName, '_');
                        $username = 'guru_'.substr($slug, 0, 15);
                    }
                }

                // Pastikan username unik
                $baseUsername = $username;
                $uCounter = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $baseUsername.'_'.$uCounter;
                    $uCounter++;
                }

                if (empty($email)) {
                    $email = $username.'@'.$schoolDomain;
                }

                // Pastikan email unik
                $baseEmail = $email;
                $eCounter = 1;
                while (User::where('email', $email)->exists()) {
                    $prefix = strstr($baseEmail, '@', true);
                    $domain = substr(strstr($baseEmail, '@'), 1);
                    $email = $prefix.'_'.$eCounter.'@'.$domain;
                    $eCounter++;
                }

                $password = ! empty($rawPassword) ? $rawPassword : 'password';

                $groupPosition = User::isStaffPosition($position) ? 'staf' : (str_contains(strtolower($position), 'administrator') ? 'administrator' : 'guru');

                User::create([
                    'name' => $cleanName,
                    'short_name' => $shortName ?: null,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role_id' => $roleId,
                    'nigs' => $nigs ?: null,
                    'nip' => $nip ?: null,
                    'nuptk' => $nuptk ?: null,
                    'nik' => $nik ?: null,
                    'phone' => $phone ?: null,
                    'gender' => $gender ?: null,
                    'birth_info' => $birthInfo ?: null,
                    'gtk_status' => $gtkStatus ?: null,
                    'position' => $groupPosition,
                    'education_level' => $educationLevel ?: null,
                    'major' => $major ?: null,
                    'address' => $address ?: null,
                    'is_active' => true,
                ]);

                $updatedList[] = $cleanName.' (baru)';
                $importedCount++;
            }
        }

        return [
            'success' => ($importedCount + $updatedCount) > 0,
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'updated' => $updatedList,
            'errors' => $errorList,
        ];
    }

    /**
     * Helper untuk mengambil nilai cell dengan aman.
     *
     * @param  array<int, string>  $row
     */
    protected function getCellValue(array $row, int $colIndex): string
    {
        if ($colIndex < 0 || ! isset($row[$colIndex])) {
            return '';
        }

        return trim((string) $row[$colIndex]);
    }

    /**
     * Deteksi kolom header secara fleksibel (Bahasa Indonesia & Dapodik).
     *
     * @param  array<int, string>  $headerRow
     * @return array<string, mixed>
     */
    protected function detectHeaderColumns(array $headerRow): array
    {
        $cols = [
            'has_header' => false,
            'name_col' => -1,
            'nigs_col' => -1,
            'nigs_fix_col' => -1,
            'nip_col' => -1,
            'nuptk_col' => -1,
            'nik_col' => -1,
            'phone_col' => -1,
            'gender_col' => -1,
            'birth_info_col' => -1,
            'gtk_status_col' => -1,
            'position_col' => -1,
            'education_level_col' => -1,
            'major_col' => -1,
            'address_col' => -1,
            'short_name_col' => -1,
            'email_col' => -1,
            'username_col' => -1,
            'password_col' => -1,
        ];

        foreach ($headerRow as $idx => $cell) {
            $lower = strtolower(trim($cell));

            if ($cols['name_col'] === -1 && (
                $lower === 'nama' ||
                str_contains($lower, 'nama lengkap') ||
                str_contains($lower, 'nama guru') ||
                $lower === 'guru'
            )) {
                $cols['name_col'] = $idx;
            } elseif ($cols['nigs_col'] === -1 && str_contains($lower, 'nigs')) {
                $cols['nigs_col'] = $idx;
            } elseif ($cols['nip_col'] === -1 && ($lower === 'nip' || str_contains($lower, 'nomor induk pegawai'))) {
                $cols['nip_col'] = $idx;
            } elseif ($cols['nuptk_col'] === -1 && str_contains($lower, 'nuptk')) {
                $cols['nuptk_col'] = $idx;
            } elseif ($cols['nik_col'] === -1 && ($lower === 'nik' || str_contains($lower, 'no ktp') || str_contains($lower, 'no. ktp'))) {
                $cols['nik_col'] = $idx;
            } elseif ($cols['phone_col'] === -1 && (
                str_contains($lower, 'hp') ||
                str_contains($lower, 'telepon') ||
                str_contains($lower, 'telp') ||
                str_contains($lower, 'whatsapp') ||
                str_contains($lower, 'wa')
            )) {
                $cols['phone_col'] = $idx;
            } elseif ($cols['gender_col'] === -1 && (
                $lower === 'jk' ||
                str_contains($lower, 'jenis kelamin') ||
                str_contains($lower, 'gender') ||
                str_contains($lower, 'l/p')
            )) {
                $cols['gender_col'] = $idx;
            } elseif ($cols['birth_info_col'] === -1 && (
                str_contains($lower, 'ttl') ||
                str_contains($lower, 'tempat tanggal lahir') ||
                str_contains($lower, 'tempat, tanggal lahir') ||
                str_contains($lower, 'tgl lahir')
            )) {
                $cols['birth_info_col'] = $idx;
            } elseif ($cols['gtk_status_col'] === -1 && (
                str_contains($lower, 'status gtk') ||
                str_contains($lower, 'status kepegawaian') ||
                str_contains($lower, 'kepegawaian') ||
                $lower === 'status'
            )) {
                $cols['gtk_status_col'] = $idx;
            } elseif ($cols['position_col'] === -1 && (
                str_contains($lower, 'jabatan') ||
                str_contains($lower, 'tugas') ||
                str_contains($lower, 'posisi')
            )) {
                $cols['position_col'] = $idx;
            } elseif ($cols['education_level_col'] === -1 && (
                str_contains($lower, 'pendidikan') ||
                str_contains($lower, 'jenjang') ||
                str_contains($lower, 'ijazah')
            )) {
                $cols['education_level_col'] = $idx;
            } elseif ($cols['major_col'] === -1 && (
                str_contains($lower, 'jurusan') ||
                str_contains($lower, 'prodi') ||
                str_contains($lower, 'program studi')
            )) {
                $cols['major_col'] = $idx;
            } elseif ($cols['address_col'] === -1 && (
                str_contains($lower, 'alamat') ||
                str_contains($lower, 'domisili')
            )) {
                $cols['address_col'] = $idx;
            } elseif ($cols['short_name_col'] === -1 && (
                str_contains($lower, 'singkatan') ||
                str_contains($lower, 'kode guru') ||
                str_contains($lower, 'short name')
            )) {
                $cols['short_name_col'] = $idx;
            } elseif ($cols['email_col'] === -1 && str_contains($lower, 'email')) {
                $cols['email_col'] = $idx;
            } elseif ($cols['username_col'] === -1 && str_contains($lower, 'username')) {
                $cols['username_col'] = $idx;
            } elseif ($cols['password_col'] === -1 && (str_contains($lower, 'password') || str_contains($lower, 'sandi'))) {
                $cols['password_col'] = $idx;
            }
        }

        $cols['has_header'] = ($cols['name_col'] !== -1);

        return $cols;
    }
}
