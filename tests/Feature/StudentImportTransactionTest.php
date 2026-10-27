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
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class StudentImportTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Classroom $classroom;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Administrator', 'is_system' => true]
        );

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_import_test'],
            [
                'name' => 'Admin Import Test',
                'email' => 'admin_import@example.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $dept = Department::firstOrCreate(
            ['code' => 'TKR'],
            ['name' => 'Teknik Kendaraan Ringan Otomotif']
        );

        $grade = GradeLevel::firstOrCreate(
            ['level' => 'X'],
            ['name' => 'Tingkat X', 'order_index' => 1, 'is_active' => true]
        );

        $this->classroom = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2026/2027'],
            [
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'is_active' => true,
            ]
        );

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'semester' => 'Ganjil',
                'is_active' => true,
                'is_locked' => false,
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

    public function test_import_students_success_persists_students_and_classroom_placements_atomically(): void
    {
        $existingStudent = Student::create([
            'name' => 'SISWA LAMA',
            'nisn' => '0011223344',
            'nis' => '1001',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'address' => 'Alamat Lama',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0099887711,2001,3506123456780001,Ahmad Santoso,X TKR 1,L,Kediri,2008-01-10,Islam,Kras,,,,,,,,,,,\n"
            ."0011223344,1001,3506123456780002,Siswa Lama,X TKR 1,L,Kediri,2008-02-12,Islam,Alamat Baru Update,,,,,,,,,,,\n"
            ."0099887722,2002,3506123456780003,Budi Utomo,X TKR 1,L,Kediri,2008-03-15,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_success_');
        file_put_contents($tempFile, $csvContent);

        $service = new StudentImportService;
        $result = $service->import($tempFile, 'csv', $this->classroom->id, '2026/2027');

        @unlink($tempFile);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertCount(1, $result['updated']);

        // Assert 2 new students created
        $this->assertDatabaseHas('students', [
            'nisn' => '0099887711',
            'name' => 'AHMAD SANTOSO',
            'classroom_id' => $this->classroom->id,
        ]);

        $this->assertDatabaseHas('students', [
            'nisn' => '0099887722',
            'name' => 'BUDI UTOMO',
            'classroom_id' => $this->classroom->id,
        ]);

        // Assert existing student updated
        $this->assertDatabaseHas('students', [
            'id' => $existingStudent->id,
            'nisn' => '0011223344',
            'address' => 'Alamat Baru Update',
        ]);

        // Assert student_classrooms created for all 3
        $ahmad = Student::where('nisn', '0099887711')->first();
        $budi = Student::where('nisn', '0099887722')->first();

        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $ahmad->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $budi->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $this->assertDatabaseHas('student_classrooms', [
            'student_id' => $existingStudent->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
    }

    public function test_import_students_rolls_back_entirely_when_failure_occurs_midway(): void
    {
        $existingStudent = Student::create([
            'name' => 'SISWA TETAP ASLI',
            'nisn' => '0055443322',
            'nis' => '1002',
            'gender' => 'P',
            'classroom_id' => $this->classroom->id,
            'address' => 'Alamat Asli Tidak Boleh Berubah',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0077889901,3001,3506123456780004,Siswa Pertama Sukses Awal,X TKR 1,L,Kediri,2008-04-10,Islam,Kras,,,,,,,,,,,\n"
            ."0055443322,1002,3506123456780005,Siswa Tetap Asli,X TKR 1,P,Kediri,2008-05-12,Islam,Alamat Tercemar,,,,,,,,,,,\n"
            ."0077889903,3003,3506123456780006,Siswa Ketiga Memicu Error,X TKR 1,L,Kediri,2008-06-15,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_fail_');
        file_put_contents($tempFile, $csvContent);

        // Simulate crash on saving the 3rd student
        Student::creating(function (Student $model) {
            if ($model->name === 'SISWA KETIGA MEMICU ERROR') {
                throw new RuntimeException('Simulasi kegagalan database di baris ketiga.');
            }
        });

        $service = new StudentImportService;
        $result = $service->import($tempFile, 'csv', $this->classroom->id, '2026/2027');

        @unlink($tempFile);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('dibatalkan', $result['errors'][0]);

        // Rollback verification: Student 1 must NOT exist
        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA PERTAMA SUKSES AWAL',
        ]);

        // Rollback verification: Existing student must remain untouched
        $this->assertDatabaseHas('students', [
            'id' => $existingStudent->id,
            'address' => 'Alamat Asli Tidak Boleh Berubah',
        ]);
        $this->assertDatabaseMissing('students', [
            'id' => $existingStudent->id,
            'address' => 'Alamat Tercemar',
        ]);

        // Rollback verification: Student 3 must NOT exist
        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA KETIGA MEMICU ERROR',
        ]);

        // Rollback verification: No student_classrooms created for the failed batch
        $this->assertDatabaseMissing('student_classrooms', [
            'classroom_id' => $this->classroom->id,
            'academic_year_name' => '2026/2027',
            'status' => 'aktif',
        ]);
    }

    public function test_import_students_rolls_back_when_student_classrooms_fails_after_student_created(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0066778899,4001,3506123456780007,Siswa Gagal Rombel,X TKR 1,L,Kediri,2008-07-20,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_class_fail_');
        file_put_contents($tempFile, $csvContent);

        // Simulate crash during StudentClassroom saving
        StudentClassroom::creating(function (StudentClassroom $model) {
            throw new RuntimeException('Simulasi kegagalan constraint student_classrooms.');
        });

        $service = new StudentImportService;
        $result = $service->import($tempFile, 'csv', $this->classroom->id, '2026/2027');

        @unlink($tempFile);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);

        // Crucial: Student record must ALSO be rolled back!
        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA GAGAL ROMBEL',
        ]);

        $this->assertDatabaseMissing('student_classrooms', [
            'classroom_id' => $this->classroom->id,
        ]);
    }

    public function test_validation_rejected_rows_do_not_produce_unwanted_database_writes(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0011111111,5001,, ,X TKR 1,L,Kediri,2008-08-01,Islam,Kras,,,,,,,,,,,\n" // Nama kosong
            ."0022222222,5002,,   ,X TKR 1,P,Kediri,2008-08-02,Islam,Kras,,,,,,,,,,,\n"; // Nama hanya spasi

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_val_fail_');
        file_put_contents($tempFile, $csvContent);

        $service = new StudentImportService;
        $result = $service->import($tempFile, 'csv', $this->classroom->id, '2026/2027');

        @unlink($tempFile);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Nama siswa kosong', $result['errors'][0]);

        $this->assertDatabaseMissing('students', [
            'nisn' => '0011111111',
        ]);
        $this->assertDatabaseMissing('students', [
            'nisn' => '0022222222',
        ]);
    }

    public function test_student_controller_import_handles_fatal_database_error_gracefully_without_500(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0033333333,6001,3506123456780008,Siswa Controller Crash,X TKR 1,L,Kediri,2008-09-01,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_ctrl_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'students_crash.csv',
            'text/csv',
            null,
            true
        );

        // Force exception during Student saving
        Student::creating(function (Student $model) {
            if ($model->name === 'SISWA CONTROLLER CRASH') {
                throw new RuntimeException('Error database fatal.');
            }
        });

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import'), [
                'file' => $uploadedFile,
                'classroom_id' => $this->classroom->id,
            ]);

        // Controller catches error and redirects back with error flash (NO 500)
        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');

        // Verify Rollback
        $this->assertDatabaseMissing('students', [
            'name' => 'SISWA CONTROLLER CRASH',
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_import_student_uses_operational_year_even_when_session_viewing_year_differs(): void
    {
        $operationalAy = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'is_locked' => false]
        );
        $operationalAy->setAsActive();

        $historicalAy = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            ['semester' => 'Genap', 'is_active' => false, 'is_locked' => true]
        );

        $grade = GradeLevel::first();
        $dept = Department::first();

        $class2026 = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2026/2027'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        $class2024 = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2024/2025'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0099887766,7701,3506123456780999,Siswa Tahun Baru,X TKR 1,L,Kediri,2008-10-01,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_op_year_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'students_op.csv', 'text/csv', null, true);

        // Admin has session viewing year set to historical 2024/2025
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->from(route('students.index'))
            ->post(route('students.import'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $student = Student::where('nisn', '0099887766')->firstOrFail();
        $this->assertEquals($class2026->id, $student->classroom_id);

        $placement = StudentClassroom::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals($class2026->id, $placement->classroom_id);
        $this->assertEquals($operationalAy->id, $placement->academic_year_id);
        $this->assertEquals('2026/2027', $placement->academic_year_name);

        $this->assertDatabaseMissing('student_classrooms', [
            'student_id' => $student->id,
            'academic_year_id' => $historicalAy->id,
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_import_student_with_invalid_explicit_academic_year_is_rejected_gracefully(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0099887755,7702,3506123456780998,Siswa Uji Tolak,X TKR 1,L,Kediri,2008-10-01,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_invalid_ay_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'students_invalid_ay.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import'), [
                'file' => $uploadedFile,
                'academic_year' => '9999/9998',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Tahun pelajaran target tidak terdaftar', session('error'));

        $this->assertDatabaseMissing('students', [
            'nisn' => '0099887755',
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_import_student_with_valid_explicit_academic_year_is_honored(): void
    {
        $explicitAy = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['semester' => 'Genap', 'is_active' => false, 'is_locked' => false]
        );

        $grade = GradeLevel::first();
        $dept = Department::first();

        $class2025 = Classroom::firstOrCreate(
            ['name' => 'X TKR 1', 'academic_year' => '2025/2026'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0099887744,7703,3506123456780997,Siswa Uji Eksplisit,X TKR 1,L,Kediri,2008-10-01,Islam,Kras,,,,,,,,,,,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_valid_ay_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'students_valid_ay.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->from(route('students.index'))
            ->post(route('students.import'), [
                'file' => $uploadedFile,
                'academic_year' => '2025/2026',
            ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $student = Student::where('nisn', '0099887744')->firstOrFail();
        $this->assertEquals($class2025->id, $student->classroom_id);

        $placement = StudentClassroom::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals($class2025->id, $placement->classroom_id);
        $this->assertEquals($explicitAy->id, $placement->academic_year_id);
        $this->assertEquals('2025/2026', $placement->academic_year_name);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}
