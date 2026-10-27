<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\User;
use App\Services\DapodikStudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class DapodikImportIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Classroom $classX;

    protected Classroom $classXII;

    protected AcademicYear $academicYear;

    protected AcademicYear $prevAcademicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Administrator', 'is_system' => true]
        );

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_dapodik_test'],
            [
                'name' => 'Admin Dapodik Test',
                'email' => 'admin_dapodik@example.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $dept = Department::firstOrCreate(
            ['code' => 'TKR'],
            ['name' => 'Teknik Kendaraan Ringan Otomotif']
        );

        $grade10 = GradeLevel::firstOrCreate(
            ['level' => 'X'],
            ['name' => 'Tingkat X', 'order_index' => 1, 'is_active' => true]
        );

        $grade12 = GradeLevel::firstOrCreate(
            ['level' => 'XII'],
            ['name' => 'Tingkat XII', 'order_index' => 3, 'is_active' => true]
        );

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'semester' => 'Ganjil',
                'is_active' => true,
                'is_locked' => false,
            ]
        );

        $this->prevAcademicYear = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'semester' => 'Genap',
                'is_active' => false,
                'is_locked' => false,
            ]
        );

        $this->classX = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2026/2027'],
            [
                'grade_level_id' => $grade10->id,
                'department_id' => $dept->id,
                'academic_year_id' => $this->academicYear->id,
                'is_active' => true,
            ]
        );

        $this->classXII = Classroom::firstOrCreate(
            ['name' => 'XII TKR 1', 'academic_year' => '2025/2026'],
            [
                'grade_level_id' => $grade12->id,
                'department_id' => $dept->id,
                'academic_year_id' => $this->prevAcademicYear->id,
                'is_active' => true,
            ]
        );

        SchoolSetting::firstOrCreate(
            ['id' => 1],
            [
                'school_name' => 'SMK PGRI KRAS',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'threshold_a' => 90,
                'threshold_b' => 80,
                'threshold_c' => 70,
            ]
        );
    }

    protected function buildDapodikCsv(array $students): string
    {
        $csv = "Daftar Peserta Didik,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."SMK PGRI KRAS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."No,Nama,NIPD,JK,NISN,Tempat Lahir,Tanggal Lahir,NIK,Agama,Alamat,RT,RW,Dusun,Kelurahan,Kecamatan,Kode Pos,Jenis Tinggal,Alat Transportasi,Telepon,HP,E-Mail,SKHUN,Penerima KPS,No. KPS,Nama Ayah,Tahun Lahir Ayah,Jenjang Pendidikan Ayah,Pekerjaan Ayah,Penghasilan Ayah,NIK Ayah,Nama Ibu,Tahun Lahir Ibu,Jenjang Pendidikan Ibu,Pekerjaan Ibu,Penghasilan Ibu,NIK Ibu,Nama Wali,Tahun Lahir Wali,Jenjang Pendidikan Wali,Pekerjaan Wali,Penghasilan Wali,NIK Wali,Rombel Saat Ini,No Peserta Ujian Nasional,No Seri Ijazah,Penerima KIP,Nomor KIP,Nama di KIP,Nomor KKS,No Registrasi Akta Lahir,Bank,Nomor Rekening Bank,Rekening Atas Nama,Layak PIP,Alasan Layak PIP,Kebutuhan Khusus,Sekolah Asal,Anak ke-berapa,Lintang,Bujur,No KK,Berat Badan,Tinggi Badan,Lingkar Kepala,Jml Saudara Kandung,Jarak Rumah ke Sekolah (KM)\n"
            ."Data Pribadi,,,,,,,,,,,,,,,,,,,,,,,,Data Ayah Kandung,,,,,,Data Ibu Kandung,,,,,,Data Wali,,,,,,Lanjutan,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66\n";

        foreach ($students as $idx => $s) {
            $cols = array_fill(0, 66, '');
            $cols[0] = $idx + 1;
            $cols[1] = $s['name'] ?? '';
            $cols[2] = $s['nipd'] ?? '100'.($idx + 1);
            $cols[3] = $s['gender'] ?? 'L';
            $cols[4] = $s['nisn'] ?? '00998877'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $cols[5] = 'Kediri';
            $cols[6] = '2008-01-01';
            $cols[7] = '350612345678'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            $cols[8] = 'Islam';
            $cols[9] = 'Kras';
            $cols[42] = $s['rombel'] ?? 'X TKR 1';

            $csv .= implode(',', $cols)."\n";
        }

        return $csv;
    }

    public function test_dapodik_import_success_persists_student_and_placement_atomically(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Fajar Pratama', 'nisn' => '0081234501', 'rombel' => 'X TKR 1'],
            ['name' => 'Gita Gutawa', 'nisn' => '0081234502', 'gender' => 'P', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_ok_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => false,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertEquals(0, $result['skipped_count']);

        $fajar = Student::where('nisn', '0081234501')->first();
        $gita = Student::where('nisn', '0081234502')->first();

        $this->assertNotNull($fajar);
        $this->assertEquals('FAJAR PRATAMA', $fajar->name);
        $this->assertEquals($this->classX->id, $fajar->classroom_id);

        $this->assertNotNull($gita);
        $this->assertEquals('GITA GUTAWA', $gita->name);
        $this->assertEquals($this->classX->id, $gita->classroom_id);

        // Verify placements
        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $fajar->id,
            'classroom_id' => $this->classX->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $gita->id,
            'classroom_id' => $this->classX->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
    }

    public function test_dapodik_import_rolls_back_student_when_student_classroom_fails(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Gagal Penempatan', 'nisn' => '0089999001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_fail_');
        file_put_contents($tempFile, $csvContent);

        // Force exception when inserting StudentClassroom for this student
        StudentClassroom::creating(function (StudentClassroom $model) {
            throw new RuntimeException('Simulasi kegagalan database pada student_classrooms.');
        });

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => false,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertEquals(0, $result['imported_count']);
        $this->assertEquals(1, $result['skipped_count']);
        $this->assertNotEmpty($result['errors']);

        // Assert Student was ROLLED BACK and does NOT exist in database!
        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA GAGAL PENEMPATAN',
        ]);
        $this->assertDatabaseMissing('students', [
            'nisn' => '0089999001',
        ]);
        $this->assertDatabaseMissing('student_classrooms', [
            'classroom_id' => $this->classX->id,
        ]);
    }

    public function test_dapodik_import_per_student_transaction_isolates_failures_without_leaving_partial_records(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa A Sukses', 'nisn' => '0089001001', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa B Crash', 'nisn' => '0089001002', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa C Sukses', 'nisn' => '0089001003', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_isolate_');
        file_put_contents($tempFile, $csvContent);

        // Crash only on Siswa B
        StudentClassroom::creating(function (StudentClassroom $model) {
            $student = Student::find($model->student_id);
            if ($student && $student->name === 'SISWA B CRASH') {
                throw new RuntimeException('Simulasi kegagalan khusus Siswa B.');
            }
        });

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => false,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertEquals(1, $result['skipped_count']);

        // Siswa A and Siswa C must be saved completely
        $this->assertDatabaseHas('students', ['name' => 'SISWA A SUKSES', 'nisn' => '0089001001']);
        $this->assertDatabaseHas('students', ['name' => 'SISWA C SUKSES', 'nisn' => '0089001003']);

        $studentA = Student::where('nisn', '0089001001')->first();
        $studentC = Student::where('nisn', '0089001003')->first();
        $this->assertDatabaseHas('student_classrooms', ['student_id' => $studentA->id]);
        $this->assertDatabaseHas('student_classrooms', ['student_id' => $studentC->id]);

        // Siswa B must be COMPLETELY ROLLED BACK: no student, no student_classroom
        $this->assertDatabaseMissing('students', ['name' => 'SISWA B CRASH']);
        $this->assertDatabaseMissing('students', ['nisn' => '0089001002']);
    }

    public function test_dapodik_import_suspends_subsequent_processes_when_batch_contains_skipped_rows(): void
    {
        // 1. Existing Grade 12 student on 2025/2026
        $budiGrade12 = Student::create([
            'name' => 'BUDI CALON ALUMNI',
            'nisn' => '0071111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        // 2. Existing Grade 10 student on 2025/2026
        $jokoUnderclass = Student::create([
            'name' => 'JOKO SISWA AKTIF',
            'nisn' => '0071111002',
            'gender' => 'L',
            'classroom_id' => $this->classX->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $jokoUnderclass->id,
            'classroom_id' => $this->classX->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classX->name,
        ]);

        // Dapodik file has 2 students, neither is Budi or Joko, BUT Row 1 fails!
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Gagal Baris', 'nisn' => '0079999001', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa Sukses Baris', 'nisn' => '0079999002', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_susp_');
        file_put_contents($tempFile, $csvContent);

        // Force crash on first student
        StudentClassroom::creating(function (StudentClassroom $model) {
            $student = Student::find($model->student_id);
            if ($student && $student->name === 'SISWA GAGAL BARIS') {
                throw new RuntimeException('Crash baris pertama');
            }
        });

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => true,
            'auto_mutasi_missing_students' => true,
        ]);

        @unlink($tempFile);

        $this->assertEquals(1, $result['skipped_count']);
        $this->assertEquals(0, $result['graduated_count']);
        $this->assertEquals(0, $result['mutated_count']);

        // Verify subsequent processes were SUSPENDED: Budi NOT graduated, Joko NOT mutated!
        $this->assertEquals('aktif', $budiGrade12->fresh()->status);
        $this->assertTrue($budiGrade12->fresh()->is_active);

        $this->assertEquals('aktif', $jokoUnderclass->fresh()->status);
        $this->assertTrue($jokoUnderclass->fresh()->is_active);

        // Verify notification in details/errors
        $hasSuspendedNote = collect($result['details'])->contains(fn ($msg) => str_contains($msg, 'ditangguhkan'));
        $this->assertTrue($hasSuspendedNote, 'Details harus mencantumkan penangguhan kelulusan/mutasi otomatis.');
    }

    public function test_dapodik_import_executes_subsequent_processes_when_batch_has_zero_skips(): void
    {
        // Setup class XI to represent full multi-classroom / multi-grade school structure
        $grade11 = GradeLevel::firstOrCreate(
            ['level' => 'XI'],
            ['name' => 'Tingkat XI', 'order_index' => 2, 'is_active' => true]
        );
        $classXI = Classroom::firstOrCreate(
            ['name' => 'XI TKR 1', 'academic_year' => '2026/2027'],
            [
                'grade_level_id' => $grade11->id,
                'department_id' => $this->classX->department_id,
                'academic_year_id' => $this->academicYear->id,
                'is_active' => true,
            ]
        );

        // 1. Existing Grade 12 student on 2025/2026 (not in file -> should be graduated)
        $budiGrade12 = Student::create([
            'name' => 'BUDI SIAP LULUS',
            'nisn' => '0061111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        // File contains students from multiple classrooms across grades (X and XI) with 0 skips
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Baru Bersih X', 'nisn' => '0069999001', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa Bersih XI', 'nisn' => '0069999002', 'rombel' => 'XI TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_clean_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => true,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertEquals(0, $result['skipped_count']);
        $this->assertEquals(1, $result['graduated_count']);

        // Budi must be graduated
        $freshBudi = $budiGrade12->fresh();
        $this->assertEquals('lulus', $freshBudi->status);
        $this->assertFalse($freshBudi->is_active);
        $this->assertNull($freshBudi->classroom_id);
    }

    public function test_default_options_do_not_graduate_or_mutate_missing_students(): void
    {
        // 1. Existing Grade 12 student
        $budiGrade12 = Student::create([
            'name' => 'BUDI TETAP AKTIF DEF',
            'nisn' => '0041111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        // 2. Existing underclass student
        $jokoUnderclass = Student::create([
            'name' => 'JOKO TETAP AKTIF DEF',
            'nisn' => '0041111002',
            'gender' => 'L',
            'classroom_id' => $this->classX->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $jokoUnderclass->id,
            'classroom_id' => $this->classX->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classX->name,
        ]);

        // File with other students (options omitted -> default false)
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Baru Default', 'nisn' => '0049999001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_def_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        // Call import without passing auto options (or with empty array)
        $result = $service->import($tempFile, 'csv', null, '2026/2027', []);

        @unlink($tempFile);

        $this->assertEquals(0, $result['graduated_count']);
        $this->assertEquals(0, $result['mutated_count']);

        // Assert neither student was graduated or mutated
        $this->assertEquals('aktif', $budiGrade12->fresh()->status);
        $this->assertTrue($budiGrade12->fresh()->is_active);

        $this->assertEquals('aktif', $jokoUnderclass->fresh()->status);
        $this->assertTrue($jokoUnderclass->fresh()->is_active);
    }

    public function test_dapodik_import_suspends_auto_graduate_when_file_is_single_classroom_partial(): void
    {
        $budiGrade12 = Student::create([
            'name' => 'BUDI LINDUNG SINGLE CLS',
            'nisn' => '0031111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        // File only contains 1 classroom
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Kelas Tunggal 1', 'nisn' => '0039999001', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa Kelas Tunggal 2', 'nisn' => '0039999002', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_single_cls_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => true,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertEquals(0, $result['graduated_count']);
        $this->assertEquals('aktif', $budiGrade12->fresh()->status);
        $this->assertTrue($budiGrade12->fresh()->is_active);

        $hasSuspendedNote = collect($result['details'])->contains(fn ($msg) => str_contains($msg, 'ditangguhkan karena berkas terdeteksi sebagai berkas parsial'));
        $this->assertTrue($hasSuspendedNote);
    }

    public function test_dapodik_import_suspends_auto_graduate_when_file_only_contains_grade_x(): void
    {
        // Multi-classroom file, but all are Grade X (e.g. PPDB files)
        $grade10 = GradeLevel::where('level', 'X')->first();
        $classX2 = Classroom::firstOrCreate(
            ['name' => 'X TKR 2', 'academic_year' => '2026/2027'],
            [
                'grade_level_id' => $grade10->id,
                'department_id' => $this->classX->department_id,
                'academic_year_id' => $this->academicYear->id,
                'is_active' => true,
            ]
        );

        $budiGrade12 = Student::create([
            'name' => 'BUDI LINDUNG GRADE X ONLY',
            'nisn' => '0021111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa X 1', 'nisn' => '0029999001', 'rombel' => 'X TKR 1'],
            ['name' => 'Siswa X 2', 'nisn' => '0029999002', 'rombel' => 'X TKR 2'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_gx_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => true,
            'auto_mutasi_missing_students' => false,
        ]);

        @unlink($tempFile);

        $this->assertEquals(0, $result['graduated_count']);
        $this->assertEquals('aktif', $budiGrade12->fresh()->status);
        $this->assertTrue($budiGrade12->fresh()->is_active);

        $hasSuspendedNote = collect($result['details'])->contains(fn ($msg) => str_contains($msg, 'hanya memuat rombel kelas X'));
        $this->assertTrue($hasSuspendedNote);
    }

    public function test_dapodik_import_suspends_auto_mutasi_when_file_is_single_classroom_partial(): void
    {
        // 2 students in existing class X
        $joko1 = Student::create([
            'name' => 'JOKO KELAS LAIN',
            'nisn' => '0011111001',
            'gender' => 'L',
            'classroom_id' => $this->classX->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $joko1->id,
            'classroom_id' => $this->classX->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classX->name,
        ]);

        // Upload a single classroom file (which does NOT include Joko)
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Rombel Terpisah', 'nisn' => '0019999001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_mut_single_');
        file_put_contents($tempFile, $csvContent);

        $service = new DapodikStudentImportService;
        $result = $service->import($tempFile, 'csv', null, '2026/2027', [
            'auto_graduate_grade_12' => false,
            'auto_mutasi_missing_students' => true,
        ]);

        @unlink($tempFile);

        $this->assertEquals(0, $result['mutated_count']);
        // Joko must remain active, NOT mutated!
        $this->assertEquals('aktif', $joko1->fresh()->status);
        $this->assertTrue($joko1->fresh()->is_active);

        $hasSuspendedNote = collect($result['details'])->contains(fn ($msg) => str_contains($msg, 'ditangguhkan karena berkas terdeteksi sebagai berkas parsial'));
        $this->assertTrue($hasSuspendedNote);
    }

    public function test_student_controller_import_dapodik_defaults_to_safe_options_when_checkboxes_omitted(): void
    {
        $budiGrade12 = Student::create([
            'name' => 'BUDI CONTROLLER SAFE',
            'nisn' => '0001111001',
            'gender' => 'L',
            'classroom_id' => $this->classXII->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        StudentClassroom::create([
            'student_id' => $budiGrade12->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Controller Safe', 'nisn' => '0009999001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_ctrl_safe_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Safe.csv', 'text/csv', null, true);

        // Post without auto_graduate_grade_12 or auto_mutasi_missing_students
        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2027',
            ]);

        $response->assertRedirect(route('students.index'));

        // Budi must remain active
        $this->assertEquals('aktif', $budiGrade12->fresh()->status);
        $this->assertTrue($budiGrade12->fresh()->is_active);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_student_controller_import_dapodik_handles_fatal_error_gracefully_without_500(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Controller Fatal', 'nisn' => '0059999001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_ctrl_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Ctrl.csv', 'text/csv', null, true);

        // Force exception during Student creation
        Student::creating(function (Student $model) {
            if ($model->name === 'SISWA CONTROLLER FATAL') {
                throw new RuntimeException('Error database controller crash.');
            }
        });

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2027',
            ]);

        // Controller catches error and redirects back with error flash (NO 500)
        $response->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA CONTROLLER FATAL',
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_with_registered_valid_academic_year_succeeds(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Reg Valid', 'nisn' => '0089001001', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_val_reg_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Reg.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2027',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $student = Student::where('nisn', '0089001001')->firstOrFail();
        $placement = StudentClassroom::where('student_id', $student->id)->where('academic_year_id', $this->academicYear->id)->firstOrFail();
        $this->assertEquals('2026/2027', $placement->academic_year_name);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_with_new_unregistered_academic_year_creates_standard_record(): void
    {
        $targetYear = '2028/2029';
        $this->assertDatabaseMissing('academic_years', ['name' => $targetYear]);

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Tahun Depan', 'nisn' => '0089002002', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_new_ay_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_New.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => $targetYear,
                'auto_create_classes' => '1',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        // Verify standard AcademicYear attributes
        $createdAy = AcademicYear::where('name', $targetYear)->firstOrFail();
        $this->assertEquals('Ganjil', $createdAy->semester);
        $this->assertEquals('2028-07-01', $createdAy->start_date->format('Y-m-d'));
        $this->assertEquals('2029-06-30', $createdAy->end_date->format('Y-m-d'));
        $this->assertFalse($createdAy->is_active);
        $this->assertFalse($createdAy->is_locked);

        $student = Student::where('nisn', '0089002002')->firstOrFail();
        $placement = StudentClassroom::where('student_id', $student->id)->where('academic_year_id', $createdAy->id)->firstOrFail();
        $this->assertEquals($targetYear, $placement->academic_year_name);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_rejects_invalid_year_format(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Format Error', 'nisn' => '0089003003', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_fmt_err_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Fmt.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026-2027',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Format tahun pelajaran target tidak valid', session('error'));

        $this->assertDatabaseMissing('students', ['nisn' => '0089003003']);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_rejects_invalid_year_span_where_end_not_greater_than_start(): void
    {
        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Span Error', 'nisn' => '0089004004', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_span_err_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Span.csv', 'text/csv', null, true);

        // 1. end year equals start year (2026/2026)
        $response1 = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2026',
            ]);
        $response1->assertRedirect(route('students.index'));
        $response1->assertSessionHas('error');
        $this->assertStringContainsString('Rentang tahun pelajaran tidak valid', session('error'));

        // 2. end year earlier than start year (2026/2025)
        $response2 = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2025',
            ]);
        $response2->assertRedirect(route('students.index'));
        $response2->assertSessionHas('error');
        $this->assertStringContainsString('Rentang tahun pelajaran tidak valid', session('error'));

        // 3. end year spans more than 1 year (2026/2030)
        $response3 = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2030',
            ]);
        $response3->assertRedirect(route('students.index'));
        $response3->assertSessionHas('error');
        $this->assertStringContainsString('Rentang tahun pelajaran tidak valid', session('error'));

        $this->assertDatabaseMissing('students', ['nisn' => '0089004004']);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_rejects_locked_target_academic_year(): void
    {
        $lockedYear = AcademicYear::firstOrCreate(['name' => '2023/2024'], [
            'semester' => 'Genap',
            'is_active' => false,
            'is_locked' => true,
        ]);
        $lockedYear->update(['is_locked' => true]);

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Locked Year', 'nisn' => '0089005005', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_locked_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Locked.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2023/2024',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('sedang dikunci (arsip permanen)', session('error'));

        $this->assertDatabaseMissing('students', ['nisn' => '0089005005']);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_handles_activation_failure_with_honest_warning_without_deleting_students(): void
    {
        $targetYear = '2029/2030';

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Warning Test', 'nisn' => '0089006006', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_warn_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Warn.csv', 'text/csv', null, true);

        // Force SchoolSetting update to throw exception during setAsActive()
        SchoolSetting::updating(function () {
            throw new RuntimeException('Koneksi sinkronisasi setting sekolah terputus.');
        });

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => $targetYear,
                'auto_create_classes' => '1',
                'set_as_active_year' => '1',
            ]);

        $response->assertRedirect(route('students.index'));
        // Must NOT show misleading success
        $response->assertSessionMissing('success');
        // Must show honest warning explaining partial success
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('Sinkronisasi data siswa dari Dapodik berhasil', session('warning'));
        $this->assertStringContainsString('NAMUN penetapan Tahun Pelajaran 2029/2030 sebagai tahun aktif sekolah gagal', session('warning'));

        // Crucial: Imported students are PRESERVED and not deleted
        $student = Student::where('nisn', '0089006006')->firstOrFail();
        $this->assertEquals('SISWA WARNING TEST', $student->name);

        // Active year in SchoolSetting is NOT changed to the failed target
        $setting = SchoolSetting::first();
        $this->assertEquals('2026/2027', $setting->academic_year);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_without_set_as_active_does_not_mutate_operational_year(): void
    {
        $targetYear = '2030/2031';

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Inactive Import', 'nisn' => '0089007007', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_no_act_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_NoAct.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => $targetYear,
                'auto_create_classes' => '1',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');
        $this->assertStringNotContainsString('kini resmi aktif', session('success'));

        // Target year created but remains inactive
        $createdAy = AcademicYear::where('name', $targetYear)->firstOrFail();
        $this->assertFalse($createdAy->is_active);

        // Current operational year remains 2026/2027
        $this->assertTrue($this->academicYear->fresh()->is_active);
        $this->assertEquals('2026/2027', SchoolSetting::first()->academic_year);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_preserves_student_placement_history_from_previous_years(): void
    {
        $existingStudent = Student::create([
            'name' => 'Siswa Riwayat Utuh',
            'nisn' => '0089008008',
            'gender' => 'L',
            'status' => 'aktif',
            'classroom_id' => $this->classXII->id,
            'is_active' => true,
        ]);

        $historicalPlacement = StudentClassroom::create([
            'student_id' => $existingStudent->id,
            'classroom_id' => $this->classXII->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'status' => 'aktif',
            'is_repeating' => false,
            'academic_year_name' => '2025/2026',
            'classroom_name' => $this->classXII->name,
        ]);

        $csvContent = $this->buildDapodikCsv([
            ['name' => 'Siswa Riwayat Utuh', 'nisn' => '0089008008', 'rombel' => 'X TKR 1'],
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_hist_');
        file_put_contents($tempFile, $csvContent);
        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_Hist.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import-dapodik'), [
                'file' => $uploadedFile,
                'academic_year' => '2026/2027',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        // Verify historical placement in 2025/2026 is UNTOUCHED
        $this->assertDatabaseHas('student_classrooms', [
            'id' => $historicalPlacement->id,
            'student_id' => $existingStudent->id,
            'academic_year_id' => $this->prevAcademicYear->id,
            'academic_year_name' => '2025/2026',
            'classroom_id' => $this->classXII->id,
        ]);

        // Verify new placement in 2026/2027 is ALSO recorded
        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $existingStudent->id,
            'academic_year_id' => $this->academicYear->id,
            'academic_year_name' => '2026/2027',
            'classroom_id' => $this->classX->id,
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}
