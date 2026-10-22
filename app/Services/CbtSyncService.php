<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Cbt\CbtGuru;
use App\Models\Cbt\CbtJadwal;
use App\Models\Cbt\CbtJurusan;
use App\Models\Cbt\CbtKelas;
use App\Models\Cbt\CbtKelasSiswa;
use App\Models\Cbt\CbtMapel;
use App\Models\Cbt\CbtRekapNilai;
use App\Models\Cbt\CbtSemester;
use App\Models\Cbt\CbtSiswa;
use App\Models\Cbt\CbtTahunPelajaran;
use App\Models\Cbt\CbtUser;
use App\Models\Cbt\CbtUserGroup;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CbtSyncService
{
    /**
     * Check if connection to CBT database is working.
     */
    public function testConnection(): bool
    {
        try {
            DB::connection('cbt')->getPdo();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get summary status comparing SIAP and CBT records.
     */
    public function getSyncStatus(): array
    {
        $isConnected = $this->testConnection();

        if (! $isConnected) {
            return [
                'connected' => false,
                'siap' => [],
                'cbt' => [],
            ];
        }

        $activeAy = AcademicYear::getActive();

        return [
            'connected' => true,
            'active_academic_year' => $activeAy?->name,
            'active_semester' => $activeAy?->semester ?? 'Ganjil',
            'siap' => [
                'academic_years' => AcademicYear::count(),
                'departments' => Department::where('is_active', true)->count(),
                'classrooms' => Classroom::where('is_active', true)->count(),
                'subjects' => Subject::where('is_active', true)->count(),
                'teachers' => User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'waka_kurikulum', 'kepala_sekolah', 'supervisor']))->count(),
                'active_students' => Student::where('status', 'aktif')->count(),
            ],
            'cbt' => [
                'tp' => CbtTahunPelajaran::count(),
                'active_tp' => CbtTahunPelajaran::where('active', 1)->value('tahun'),
                'jurusan' => CbtJurusan::count(),
                'kelas' => CbtKelas::count(),
                'mapel' => CbtMapel::count(),
                'guru' => CbtGuru::count(),
                'siswa' => CbtSiswa::count(),
                'kelas_siswa' => CbtKelasSiswa::count(),
                'users' => CbtUser::count(),
                'jadwal_ujian' => CbtJadwal::count(),
                'rekap_nilai' => CbtRekapNilai::count(),
            ],
        ];
    }

    /**
     * Sync Academic Years from SIAP to CBT (master_tp).
     */
    public function syncTahunPelajaran(): array
    {
        return DB::connection('cbt')->transaction(function () {
            CbtTahunPelajaran::query()->update(['active' => 0]);
            $siapYears = AcademicYear::all();
            $created = 0;
            $updated = 0;

            foreach ($siapYears as $year) {
                $cbtTp = CbtTahunPelajaran::where('tahun', $year->name)->first();

                if ($cbtTp) {
                    $cbtTp->update(['active' => $year->is_active ? 1 : 0]);
                    $updated++;
                } else {
                    CbtTahunPelajaran::create([
                        'tahun' => $year->name,
                        'active' => $year->is_active ? 1 : 0,
                    ]);
                    $created++;
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $siapYears->count(),
            ];
        });
    }

    /**
     * Sync active semester from SIAP to CBT (master_smt).
     */
    public function syncSemester(): array
    {
        return DB::connection('cbt')->transaction(function () {
            $activeYear = AcademicYear::getActive();
            $currentSemester = $activeYear?->semester ?? 'Ganjil';

            $activeIdSmt = strcasecmp($currentSemester, 'Genap') === 0 ? 2 : 1;

            CbtSemester::where('id_smt', $activeIdSmt)->update(['active' => 1]);
            CbtSemester::where('id_smt', '!=', $activeIdSmt)->update(['active' => 0]);

            return [
                'success' => true,
                'active_semester' => $currentSemester,
                'active_id_smt' => $activeIdSmt,
            ];
        });
    }

    /**
     * Sync departments from SIAP to CBT (master_jurusan).
     */
    public function syncJurusan(): array
    {
        return DB::connection('cbt')->transaction(function () {
            $departments = Department::all();
            $created = 0;
            $updated = 0;

            foreach ($departments as $dept) {
                $cbtJurusan = CbtJurusan::where('kode_jurusan', $dept->code)->first();

                if ($cbtJurusan) {
                    $cbtJurusan->update([
                        'nama_jurusan' => $dept->name,
                        'status' => $dept->is_active ? 1 : 0,
                        'deletable' => 1,
                    ]);
                    $updated++;
                } else {
                    CbtJurusan::create([
                        'nama_jurusan' => $dept->name,
                        'kode_jurusan' => $dept->code,
                        'status' => $dept->is_active ? 1 : 0,
                        'deletable' => 1,
                    ]);
                    $created++;
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $departments->count(),
            ];
        });
    }

    /**
     * Sync subjects from SIAP to CBT (master_mapel).
     */
    public function syncMapel(): array
    {
        return DB::connection('cbt')->transaction(function () {
            $subjects = Subject::where('is_active', true)->get();
            $created = 0;
            $updated = 0;

            foreach ($subjects as $index => $subject) {
                $cbtMapel = CbtMapel::where('kode', $subject->code)->first();

                $data = [
                    'nama_mapel' => $subject->name,
                    'kode' => $subject->code,
                    'kelompok' => $subject->category ?? '-',
                    'jenjang' => 3, // SMK
                    'status' => 1,
                    'deletable' => 1,
                    'urutan' => $subject->order_index ?? ($index + 1),
                    'urutan_tampil' => $subject->order_index ?? ($index + 1),
                ];

                if ($cbtMapel) {
                    $cbtMapel->update($data);
                    $updated++;
                } else {
                    CbtMapel::create($data);
                    $created++;
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $subjects->count(),
            ];
        });
    }

    /**
     * Sync teachers from SIAP to CBT (master_guru + users).
     */
    public function syncGuru(): array
    {
        return DB::connection('cbt')->transaction(function () {
            $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['guru', 'waka_kurikulum', 'kepala_sekolah', 'supervisor']))->get();

            $created = 0;
            $updated = 0;

            foreach ($teachers as $teacher) {
                // Identitas permanen guru = NIGS (nigs_fix / nigs bersih)
                $nigs = $teacher->nigs_fix ?: ($teacher->nigs ? preg_replace('/[^0-9]/', '', $teacher->nigs) : null);
                if (! $nigs) {
                    $nigs = ! empty($teacher->nip) ? trim($teacher->nip) : 'GURU'.str_pad((string) $teacher->id, 4, '0', STR_PAD_LEFT);
                }

                // Username dan password disamakan persis dengan yang ada di SIAP
                $username = ! empty($teacher->username) ? trim($teacher->username) : $nigs;
                $nik = ! empty($teacher->nik) ? trim($teacher->nik) : null;
                $email = ! empty($teacher->email) ? $teacher->email : $nigs.'@guru.cbt';
                $passwordHash = ! empty($teacher->password) ? $teacher->password : password_hash('123456', PASSWORD_BCRYPT);

                // 1. Ensure CBT User account exists (match by SIAP username, NIGS, or email)
                $cbtUser = CbtUser::where('username', $username)
                    ->orWhere('username', $nigs)
                    ->orWhere('email', $email)
                    ->first();

                $nameParts = explode(' ', trim($teacher->name));
                $firstName = $nameParts[0];
                $lastName = count($nameParts) > 1 ? end($nameParts) : '';

                if (! $cbtUser) {
                    $cbtUser = CbtUser::create([
                        'username' => $username,
                        'password' => $passwordHash,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $teacher->phone,
                        'active' => 1,
                        'created_on' => time(),
                    ]);

                    // Assign group 2 (guru)
                    CbtUserGroup::firstOrCreate([
                        'user_id' => $cbtUser->id,
                        'group_id' => 2,
                    ]);
                } else {
                    $cbtUser->update([
                        'username' => $username,
                        'password' => $passwordHash,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $teacher->phone,
                        'active' => 1,
                    ]);
                }

                // 2. Ensure CbtGuru record exists with NIGS as permanent ID (nip) and NIK as no_ktp
                $cbtGuru = CbtGuru::where('nip', $nigs)
                    ->orWhere('id_user', $cbtUser->id)
                    ->when($nik, fn ($q) => $q->orWhere('no_ktp', $nik))
                    ->first();

                // Parse Tempat & Tanggal Lahir from birth_info
                $tempatLahir = null;
                $tglLahir = null;
                if (! empty($teacher->birth_info)) {
                    $birthParts = explode(',', $teacher->birth_info, 2);
                    $tempatLahir = trim($birthParts[0]);
                    $tglLahir = isset($birthParts[1]) ? trim($birthParts[1]) : null;
                }

                $guruData = [
                    'id_user' => $cbtUser->id,
                    'nip' => $nigs, // NIGS sebagai identitas permanen
                    'nama_guru' => $teacher->name,
                    'email' => $email,
                    'kode_guru' => $teacher->short_name ?? substr($teacher->name, 0, 3),
                    'username' => $username, // Username sama dengan SIAP
                    'password' => $passwordHash, // Password sama dengan SIAP
                    'no_ktp' => $nik, // NIK sebagai verifikasi
                    'tempat_lahir' => $tempatLahir,
                    'tgl_lahir' => $tglLahir,
                    'jenis_kelamin' => $teacher->gender === 'P' ? 'Perempuan' : 'Laki-laki',
                    'no_hp' => $teacher->phone,
                    'nuptk' => $teacher->nuptk,
                    'alamat_jalan' => $teacher->address,
                    'status_aktif' => $teacher->is_active ? 'Aktif' : 'Nonaktif',
                ];

                if ($cbtGuru) {
                    $cbtGuru->update($guruData);
                    $updated++;
                } else {
                    CbtGuru::create($guruData);
                    $created++;
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $teachers->count(),
            ];
        });
    }

    /**
     * Sync classrooms from SIAP to CBT (master_kelas).
     */
    public function syncKelas(?int $academicYearId = null): array
    {
        return DB::connection('cbt')->transaction(function () use ($academicYearId) {
            $academicYear = $academicYearId
                ? AcademicYear::findOrFail($academicYearId)
                : AcademicYear::getActive();

            if (! $academicYear) {
                return ['success' => false, 'message' => 'Tahun pelajaran aktif tidak ditemukan di SIAP'];
            }

            // Find matching CbtTahunPelajaran & CbtSemester
            $cbtTp = CbtTahunPelajaran::where('tahun', $academicYear->name)->first();
            if (! $cbtTp) {
                $cbtTp = CbtTahunPelajaran::create(['tahun' => $academicYear->name, 'active' => 1]);
            }

            $activeSmt = strcasecmp($academicYear->semester ?? 'Ganjil', 'Genap') === 0 ? 2 : 1;

            $classrooms = Classroom::with(['gradeLevel', 'department', 'homeroomTeacher'])
                ->where('is_active', true)
                ->where(function ($q) use ($academicYear) {
                    $q->where('academic_year_id', $academicYear->id)
                        ->orWhere('academic_year', $academicYear->name);
                })
                ->get();

            $created = 0;
            $updated = 0;

            foreach ($classrooms as $room) {
                // Determine CBT level_id (10, 11, 12)
                $rawLevel = $room->gradeLevel?->level;
                $level = match (strtoupper(trim((string) $rawLevel))) {
                    '10', 'X' => 10,
                    '11', 'XI' => 11,
                    '12', 'XII' => 12,
                    default => 10,
                };
                if (! in_array($level, [10, 11, 12])) {
                    if (str_starts_with($room->name, 'XII')) {
                        $level = 12;
                    } elseif (str_starts_with($room->name, 'XI')) {
                        $level = 11;
                    } else {
                        $level = 10;
                    }
                }

                // Find matching CbtJurusan
                $jurusanId = null;
                if ($room->department) {
                    $jurusanId = CbtJurusan::where('kode_jurusan', $room->department->code)->value('id_jurusan');
                }

                // Find matching homeroom teacher in CBT using permanent NIGS or NIK
                $guruId = null;
                if ($room->homeroomTeacher) {
                    $teacher = $room->homeroomTeacher;
                    $nigs = $teacher->nigs_fix ?: ($teacher->nigs ? preg_replace('/[^0-9]/', '', $teacher->nigs) : null);
                    if ($nigs) {
                        $guruId = CbtGuru::where('nip', $nigs)->value('id_guru');
                    }
                    if (! $guruId && ! empty($teacher->nik)) {
                        $guruId = CbtGuru::where('no_ktp', trim($teacher->nik))->value('id_guru');
                    }
                    if (! $guruId && ! empty($teacher->nip)) {
                        $guruId = CbtGuru::where('nip', trim($teacher->nip))->value('id_guru');
                    }
                    if (! $guruId) {
                        $guruId = CbtGuru::where('nama_guru', trim($teacher->name))->value('id_guru');
                    }
                }

                $studentCount = Student::where('classroom_id', $room->id)->where('status', 'aktif')->count();

                $cbtKelas = CbtKelas::where('nama_kelas', $room->name)
                    ->where('id_tp', $cbtTp->id_tp)
                    ->where('id_smt', $activeSmt)
                    ->first();

                $kelasData = [
                    'id_tp' => $cbtTp->id_tp,
                    'id_smt' => $activeSmt,
                    'nama_kelas' => $room->name,
                    'kode_kelas' => Str::slug($room->name, '_'),
                    'jurusan_id' => $jurusanId,
                    'level_id' => $level,
                    'guru_id' => $guruId,
                    'jumlah_siswa' => $studentCount,
                    'set_siswa' => '0',
                ];

                if ($cbtKelas) {
                    $cbtKelas->update($kelasData);
                    $updated++;
                } else {
                    CbtKelas::create($kelasData);
                    $created++;
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => $classrooms->count(),
                'tp' => $cbtTp->tahun,
            ];
        });
    }

    /**
     * Sync active students from SIAP to CBT (master_siswa, users, and kelas_siswa).
     */
    public function syncSiswa(?int $academicYearId = null): array
    {
        return DB::connection('cbt')->transaction(function () use ($academicYearId) {
            $academicYear = $academicYearId
                ? AcademicYear::findOrFail($academicYearId)
                : AcademicYear::getActive();

            if (! $academicYear) {
                return ['success' => false, 'message' => 'Tahun pelajaran aktif tidak ditemukan di SIAP'];
            }

            $cbtTp = CbtTahunPelajaran::where('tahun', $academicYear->name)->first();
            if (! $cbtTp) {
                $cbtTp = CbtTahunPelajaran::create(['tahun' => $academicYear->name, 'active' => 1]);
            }

            $activeSmt = strcasecmp($academicYear->semester ?? 'Ganjil', 'Genap') === 0 ? 2 : 1;

            // Fetch only active students in SIAP
            $students = Student::with(['classroom.gradeLevel'])
                ->where('status', 'aktif')
                ->get();

            // Cache CbtKelas map for quick lookup
            $cbtKelasMap = CbtKelas::where('id_tp', $cbtTp->id_tp)
                ->where('id_smt', $activeSmt)
                ->pluck('id_kelas', 'nama_kelas')
                ->toArray();

            $created = 0;
            $updated = 0;
            $assignedKelas = 0;

            foreach ($students as $student) {
                $nisn = ! empty($student->nisn) ? trim($student->nisn) : null;
                if (! $nisn) {
                    continue; // NISN is essential as identity key
                }

                $nis = ! empty($student->nis) ? trim($student->nis) : $nisn;
                $username = $nisn;
                $email = $nis.'@siswa.cbt';

                // 1. CBT User account
                $cbtUser = CbtUser::where('username', $username)->first();

                $nameParts = explode(' ', trim($student->name));
                $firstName = $nameParts[0];
                $lastName = count($nameParts) > 1 ? end($nameParts) : '';

                if (! $cbtUser) {
                    // Default initial password is NISN
                    $hashedPassword = password_hash($nisn, PASSWORD_BCRYPT);

                    $cbtUser = CbtUser::create([
                        'username' => $username,
                        'password' => $hashedPassword,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'active' => 1,
                        'created_on' => time(),
                    ]);

                    // Assign group 3 (siswa)
                    CbtUserGroup::firstOrCreate([
                        'user_id' => $cbtUser->id,
                        'group_id' => 3,
                    ]);
                } else {
                    $cbtUser->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'active' => 1,
                    ]);
                }

                // 2. Master Siswa
                $cbtSiswa = CbtSiswa::where('nisn', $nisn)->first();

                $rawLevelAwal = $student->classroom?->gradeLevel?->level;
                $levelAwal = match (strtoupper(trim((string) $rawLevelAwal))) {
                    '10', 'X' => 10,
                    '11', 'XI' => 11,
                    '12', 'XII' => 12,
                    default => 10,
                };

                $siswaData = [
                    'nisn' => $nisn,
                    'nis' => $nis,
                    'nama' => $student->name,
                    'jenis_kelamin' => $student->gender ?? 'L',
                    'username' => $username,
                    'password' => $nisn, // Plain readable for exam card printing
                    'kelas_awal' => $levelAwal,
                    'tahun_masuk' => $student->entry_year,
                    'sekolah_asal' => $student->school_origin,
                    'tempat_lahir' => $student->birth_place,
                    'tanggal_lahir' => $student->birth_date?->format('Y-m-d'),
                    'agama' => $student->religion,
                    'alamat' => $student->address,
                    'nik' => $student->nik ?? '',
                    'warga_negara' => 'WNI',
                    'nama_ayah' => $student->father_name,
                    'nama_ibu' => $student->mother_name,
                ];

                if (! $cbtSiswa) {
                    $siswaData['uid'] = (string) Str::uuid();
                    $cbtSiswa = CbtSiswa::create($siswaData);
                    $created++;
                } else {
                    $cbtSiswa->update($siswaData);
                    $updated++;
                }

                // 3. Assign Student to Classroom in CBT (kelas_siswa)
                $classroomName = $student->classroom?->name;
                if ($classroomName && isset($cbtKelasMap[$classroomName])) {
                    $cbtKelasId = $cbtKelasMap[$classroomName];
                    $idKelasSiswa = (int) ($cbtTp->id_tp.$activeSmt.$cbtSiswa->id_siswa);

                    CbtKelasSiswa::updateOrCreate(
                        ['id_kelas_siswa' => $idKelasSiswa],
                        [
                            'id_tp' => $cbtTp->id_tp,
                            'id_smt' => $activeSmt,
                            'id_siswa' => $cbtSiswa->id_siswa,
                            'id_kelas' => $cbtKelasId,
                        ]
                    );

                    $assignedKelas++;
                }
            }

            // Update jumlah_siswa on all active classes based on kelas_siswa
            $classCounts = CbtKelasSiswa::where('id_tp', $cbtTp->id_tp)
                ->where('id_smt', $activeSmt)
                ->groupBy('id_kelas')
                ->selectRaw('id_kelas, count(*) as total')
                ->pluck('total', 'id_kelas');

            foreach ($classCounts as $classId => $count) {
                CbtKelas::where('id_kelas', $classId)->update(['jumlah_siswa' => $count]);
            }

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'assigned_kelas' => $assignedKelas,
                'total_active' => $students->count(),
            ];
        });
    }

    /**
     * Full Sync SIAP to CBT in correct sequence.
     */
    public function syncAll(?int $academicYearId = null): array
    {
        $tpResult = $this->syncTahunPelajaran();
        $smtResult = $this->syncSemester();
        $jurusanResult = $this->syncJurusan();
        $mapelResult = $this->syncMapel();
        $guruResult = $this->syncGuru();
        $kelasResult = $this->syncKelas($academicYearId);
        $siswaResult = $this->syncSiswa($academicYearId);

        return [
            'success' => true,
            'timestamp' => now()->toDateTimeString(),
            'tahun_pelajaran' => $tpResult,
            'semester' => $smtResult,
            'jurusan' => $jurusanResult,
            'mapel' => $mapelResult,
            'guru' => $guruResult,
            'kelas' => $kelasResult,
            'siswa' => $siswaResult,
        ];
    }

    /**
     * Pull exam results from CBT (cbt_rekap_nilai) for review in SIAP.
     */
    public function pullExamResults(?string $tahun = null, ?string $smt = null, ?int $jadwalId = null): Collection
    {
        $query = CbtRekapNilai::query();

        if ($tahun) {
            $query->where('tp', $tahun);
        }

        if ($smt) {
            $query->where('smt', $smt);
        }

        if ($jadwalId) {
            $query->where('id_jadwal', $jadwalId);
        }

        return $query->orderBy('tp', 'desc')
            ->orderBy('id_jadwal', 'desc')
            ->orderBy('kelas')
            ->orderBy('nama_siswa')
            ->get();
    }

    /**
     * Get list of exam schedules in CBT for filtering.
     */
    public function getCbtJadwalList(?string $tahun = null): Collection
    {
        $query = CbtJadwal::query()
            ->select('cbt_jadwal.*', 'cbt_bank_soal.bank_kode', 'cbt_bank_soal.bank_nama', 'master_mapel.nama_mapel', 'cbt_jenis.nama_jenis', 'master_tp.tahun')
            ->leftJoin('cbt_bank_soal', 'cbt_jadwal.id_bank', '=', 'cbt_bank_soal.id_bank')
            ->leftJoin('master_mapel', 'cbt_bank_soal.bank_mapel_id', '=', 'master_mapel.id_mapel')
            ->leftJoin('cbt_jenis', 'cbt_jadwal.id_jenis', '=', 'cbt_jenis.id_jenis')
            ->leftJoin('master_tp', 'cbt_jadwal.id_tp', '=', 'master_tp.id_tp');

        if ($tahun) {
            $query->where('master_tp.tahun', $tahun);
        }

        return $query->orderBy('cbt_jadwal.tgl_mulai', 'desc')->get();
    }
}
