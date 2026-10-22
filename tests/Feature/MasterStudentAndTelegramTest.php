<?php

namespace Tests\Feature;

use App\Models\AcademicYearRolloverLog;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterStudentAndTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $teacher;

    protected Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'super_admin')->first()?->id,
                'is_active' => true,
            ]);

        $this->teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);

        $this->classroom = Classroom::first() ?? Classroom::create([
            'name' => 'X TKRO 1',
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);
    }

    public function test_master_students_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('students.index'));

        $response->assertStatus(200);
        $response->assertSee('Data Siswa');
        $response->assertSee('Data Pokok');
    }

    public function test_student_can_be_stored_with_rich_data(): void
    {
        $response = $this->actingAs($this->user)->post(route('students.store'), [
            'name' => 'Bima Arya Sakti',
            'classroom_id' => $this->classroom->id,
            'nisn' => '0089876543',
            'nis' => '24001',
            'nik' => '3506123456780099',
            'gender' => 'L',
            'birth_place' => 'Kediri',
            'birth_date' => '2008-06-15',
            'religion' => 'Islam',
            'address' => 'Jl. Pahlawan No. 45 Kras',
            'father_name' => 'Surya Sakti',
            'father_job' => 'PNS',
            'father_phone' => '081234567899',
            'mother_name' => 'Dewi Sartika',
            'mother_job' => 'Ibu Rumah Tangga',
            'mother_phone' => '081234567898',
            'phone_student' => '085712345699',
            'notes' => 'Juara Olimpiade Matematika',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('students', [
            'name' => 'BIMA ARYA SAKTI',
            'nik' => '3506123456780099',
            'father_name' => 'Surya Sakti',
            'mother_name' => 'Dewi Sartika',
            'religion' => 'Islam',
        ]);
    }

    public function test_student_show_returns_json_details(): void
    {
        $student = Student::create([
            'name' => 'Putri Ayu',
            'classroom_id' => $this->classroom->id,
            'gender' => 'P',
            'nisn' => '0081239999',
            'father_name' => 'Bapak Ayu',
        ]);

        $response = $this->actingAs($this->user)->get(route('students.show', $student));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'student' => [
                'name' => 'PUTRI AYU',
            ],
            'primary_parent' => 'Bapak Ayu',
        ]);
    }

    public function test_student_can_be_updated(): void
    {
        $student = Student::create([
            'name' => 'Siswa Lama',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->put(route('students.update', $student), [
            'name' => 'Siswa Baru Diperbarui',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'nik' => '3506123456780888',
            'address' => 'Alamat Baru',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertEquals('SISWA BARU DIPERBARUI', $student->fresh()->name);
        $this->assertEquals('3506123456780888', $student->fresh()->nik);
    }

    public function test_student_can_be_deleted(): void
    {
        $student = Student::create([
            'name' => 'Siswa Dihapus',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
        ]);

        $response = $this->actingAs($this->user)->delete(route('students.destroy', $student));

        $response->assertRedirect();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_student_template_and_export_download(): void
    {
        $templateResponse = $this->actingAs($this->user)->get(route('students.template'));
        $templateResponse->assertStatus(200);
        $this->assertTrue(str_contains($templateResponse->headers->get('content-type'), 'text/csv'));

        $exportResponse = $this->actingAs($this->user)->get(route('students.export'));
        $exportResponse->assertStatus(200);
        $this->assertTrue(str_contains($exportResponse->headers->get('content-type'), 'text/csv'));
    }

    public function test_student_search_api(): void
    {
        Student::create([
            'name' => 'Zulfa Amelia',
            'classroom_id' => $this->classroom->id,
            'gender' => 'P',
            'nisn' => '0091122334',
        ]);

        $response = $this->actingAs($this->user)->get(route('students.search', ['q' => 'Zulfa']));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'ZULFA AMELIA',
        ]);
    }

    public function test_settings_telegram_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('telegram.index'));

        $response->assertStatus(200);
        $response->assertSee('Pengaturan Bot Telegram');
        $response->assertSee('Grup Supervisi Guru');
        $response->assertSee('Grup Kegiatan Sekolah');
    }

    public function test_settings_telegram_channels_can_be_updated(): void
    {
        $response = $this->actingAs($this->user)->post(route('telegram.settings'), [
            'telegram_bot_token' => '123456789:AAEFGH_token_sekolah_123',
            'telegram_bot_username' => 'smkpgrikras_bot',
            'telegram_discipline_chat_id' => '-100111111111',
            'telegram_piket_chat_id' => '-100222222222',
            'telegram_supervision_chat_id' => '-100333333333',
            'telegram_activities_chat_id' => '-100444444444',
            'telegram_teachers_chat_id' => '-100555555555',
            'telegram_notifications_enabled' => '1',
        ]);

        $response->assertRedirect();
        $setting = SchoolSetting::getSetting();
        $this->assertEquals('-100333333333', $setting->telegram_supervision_chat_id);
        $this->assertEquals('-100444444444', $setting->telegram_activities_chat_id);
        $this->assertEquals('-100555555555', $setting->telegram_teachers_chat_id);
    }

    public function test_settings_telegram_teacher_chat_id_can_be_updated(): void
    {
        $response = $this->actingAs($this->user)->post(route('telegram.teacher-chat-id'), [
            'teacher_id' => $this->teacher->id,
            'telegram_chat_id' => '987654321',
            'telegram_username' => 'guru_teladan',
        ]);

        $response->assertRedirect();
        $this->assertEquals('987654321', $this->teacher->fresh()->telegram_chat_id);
        $this->assertEquals('guru_teladan', $this->teacher->fresh()->telegram_username);
    }

    public function test_backward_compatibility_piket_routes_work(): void
    {
        $piketStudentsResponse = $this->actingAs($this->user)->get(route('piket.students.index'));
        $piketStudentsResponse->assertStatus(200);

        $piketTelegramResponse = $this->actingAs($this->user)->get(route('piket.telegram.index'));
        $piketTelegramResponse->assertStatus(200);
    }

    public function test_student_import_service_parses_various_date_formats(): void
    {
        $service = new StudentImportService;

        // 1. Format YYYY-MM-DD (ISO)
        $this->assertEquals('2008-05-14', $service->parseDate('2008-05-14'));
        $this->assertEquals('2007-12-01', $service->parseDate('2007-12-01'));
        $this->assertEquals('2009-01-05', $service->parseDate('2009/01/05'));

        // 2. Format DD/MM/YYYY atau DD-MM-YYYY atau DD.MM.YYYY
        $this->assertEquals('2008-05-14', $service->parseDate('14/05/2008'));
        $this->assertEquals('2008-05-14', $service->parseDate('14-05-2008'));
        $this->assertEquals('2008-05-14', $service->parseDate('14.05.2008'));

        // 3. Serial Excel (39582 = 14 Mei 2008)
        $this->assertEquals('2008-05-14', $service->parseDate('39582'));

        // 4. Nama Bulan Indonesia
        $this->assertEquals('2008-05-14', $service->parseDate('14 Mei 2008'));
        $this->assertEquals('2007-08-17', $service->parseDate('17 Agustus 2007'));
    }

    public function test_student_import_via_csv_with_yyyy_mm_dd_date(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0087654321,24101,3506123456780002,Farhan Ramadhan,X TKRO 1,L,Kediri,2008-07-20,Islam,Kras,Ayah Farhan,Wiraswasta,08111,Ibu Farhan,IRT,08222,,,,08111,08333,Catatan tes\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'students_test.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post(route('students.import'), [
            'file' => $uploadedFile,
            'classroom_id' => $this->classroom->id,
        ]);

        $response->assertRedirect();
        $student = Student::where('name', 'FARHAN RAMADHAN')->first();
        $this->assertNotNull($student);
        $this->assertEquals('2008-07-20', $student->birth_date?->format('Y-m-d'));
        $this->assertEquals($this->classroom->id, $student->classroom_id);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_student_import_service_matches_classroom_aliases(): void
    {
        $service = new StudentImportService;

        // Ambil kelas yang sudah di-seed
        $classrooms = Classroom::all();

        // 1. Alias X AK, X AKL, 10 AK -> X AKL 1
        $matchedXAk = $service->matchClassroom($classrooms, 'X AK');
        $this->assertNotNull($matchedXAk);
        $this->assertEquals('X AKL 1', $matchedXAk->name);

        $matchedXAkLower = $service->matchClassroom($classrooms, 'x ak');
        $this->assertEquals('X AKL 1', $matchedXAkLower?->name);

        $matched10Ak = $service->matchClassroom($classrooms, '10 AK');
        $this->assertEquals('X AKL 1', $matched10Ak?->name);

        // 2. Alias X TKJ, 10 TKJ -> X TKJ 1
        $matchedXTkj = $service->matchClassroom($classrooms, 'X TKJ');
        $this->assertNotNull($matchedXTkj);
        $this->assertEquals('X TKJ 1', $matchedXTkj->name);

        $matched10Tkj = $service->matchClassroom($classrooms, '10 tkj');
        $this->assertEquals('X TKJ 1', $matched10Tkj?->name);

        // 3. Tingkat XI dan XII
        $matchedXiAk = $service->matchClassroom($classrooms, 'XI AK');
        $this->assertEquals('XI AKL 1', $matchedXiAk?->name);

        $matchedXiTkj = $service->matchClassroom($classrooms, 'XI TKJ');
        $this->assertEquals('XI TKJ 1', $matchedXiTkj?->name);

        // 4. Spesifik rombel nomor (XII TKJ 2, X TKR 2)
        $matchedXiiTkj2 = $service->matchClassroom($classrooms, 'XII TKJ 2');
        $this->assertEquals('XII TKJ 2', $matchedXiiTkj2?->name);

        $matchedXTkr2 = $service->matchClassroom($classrooms, 'X TKR 2');
        $this->assertEquals('X TKR 2', $matchedXTkr2?->name);
    }

    public function test_student_import_via_csv_resolves_x_ak_and_x_tkj_automatically(): void
    {
        $csvContent = "\xEF\xBB\xBF"
            ."NISN,NIS,NIK,Nama Siswa,Kelas,JK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,No HP Ayah,Nama Ibu,Pekerjaan Ibu,No HP Ibu,Nama Wali,Pekerjaan Wali,No HP Wali,No HP Ortu,No HP Siswa,Catatan\n"
            ."0081111111,24201,3506123456780003,Siti Nurhaliza,X AK,P,Kediri,2008-08-15,Islam,Kras,Ayah Siti,Petani,08111,Ibu Siti,IRT,08222,,,,08111,08333,Juara 1\n"
            ."0082222222,24202,3506123456780004,Bayu Pratama,x tkj,L,Kediri,2008-09-20,Islam,Kras,Ayah Bayu,PNS,08111,Ibu Bayu,Guru,08222,,,,08111,08333,Aktif Osis\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_class_test_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'students_class_test.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post(route('students.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect();

        $studentAk = Student::where('name', 'SITI NURHALIZA')->first();
        $this->assertNotNull($studentAk);
        $expectedAklClass = Classroom::where('name', 'X AKL 1')->first();
        $this->assertEquals($expectedAklClass->id, $studentAk->classroom_id);

        $studentTkj = Student::where('name', 'BAYU PRATAMA')->first();
        $this->assertNotNull($studentTkj);
        $expectedTkjClass = Classroom::where('name', 'X TKJ 1')->first();
        $this->assertEquals($expectedTkjClass->id, $studentTkj->classroom_id);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_student_import_with_jenis_kelamin_header_does_not_overwrite_students(): void
    {
        // Simulasi export Dapodik dengan header "Jenis Kelamin"
        $csvContent = "\xEF\xBB\xBF"
            ."No,Nama,Jenis Kelamin,NISN,NIK,Tempat Lahir,Tanggal Lahir,Agama,Alamat,Nama Ayah,Pekerjaan Ayah,Nama Ibu,Pekerjaan Ibu,Rombel Saat Ini\n"
            ."1,Ahmad Dani,L,0081000001,3506123456780011,Kediri,2008-01-01,Islam,Dusun Kromasan,Ayah 1,Tani,Ibu 1,IRT,X AK\n"
            ."2,Budi Santoso,L,0081000002,3506123456780012,Kediri,2008-02-02,Islam,Dusun Kromasan,Ayah 2,Tani,Ibu 2,IRT,X AK\n"
            ."3,Citra Kirana,P,0081000003,3506123456780013,Kediri,2008-03-03,Islam,Dusun Kromasan,Ayah 3,Tani,Ibu 3,IRT,X AK\n"
            ."4,Dewi Lestari,P,0081000004,3506123456780014,Kediri,2008-04-04,Islam,Dusun Kromasan,Ayah 4,Tani,Ibu 4,IRT,X AK\n"
            ."5,Eko Prasetyo,L,0081000005,3506123456780015,Kediri,2008-05-05,Islam,Dusun Kromasan,Ayah 5,Tani,Ibu 5,IRT,X AK\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_dapodik_test_');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'dapodik_test.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post(route('students.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect();

        // Pastikan KELIMA siswa berhasil masuk dan tidak saling menimpa!
        $this->assertDatabaseHas('students', ['name' => 'AHMAD DANI', 'gender' => 'L']);
        $this->assertDatabaseHas('students', ['name' => 'BUDI SANTOSO', 'gender' => 'L']);
        $this->assertDatabaseHas('students', ['name' => 'CITRA KIRANA', 'gender' => 'P']);
        $this->assertDatabaseHas('students', ['name' => 'DEWI LESTARI', 'gender' => 'P']);
        $this->assertDatabaseHas('students', ['name' => 'EKO PRASETYO', 'gender' => 'L']);

        // Pastikan NIS tidak diisi 'L' atau 'P'
        $ahmad = Student::where('name', 'AHMAD DANI')->first();
        $this->assertNotEquals('L', $ahmad?->nis);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_student_import_creates_and_updates_students(): void
    {
        $dapodikCsv = "Daftar Peserta Didik,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."SMK PGRI KRAS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."No,Nama,NIPD,JK,NISN,Tempat Lahir,Tanggal Lahir,NIK,Agama,Alamat,RT,RW,Dusun,Kelurahan,Kecamatan,Kode Pos,Jenis Tinggal,Alat Transportasi,Telepon,HP,E-Mail,SKHUN,Penerima KPS,No. KPS,Nama Ayah,Tahun Lahir Ayah,Jenjang Pendidikan Ayah,Pekerjaan Ayah,Penghasilan Ayah,NIK Ayah,Nama Ibu,Tahun Lahir Ibu,Jenjang Pendidikan Ibu,Pekerjaan Ibu,Penghasilan Ibu,NIK Ibu,Nama Wali,Tahun Lahir Wali,Jenjang Pendidikan Wali,Pekerjaan Wali,Penghasilan Wali,NIK Wali,Rombel Saat Ini,No Peserta Ujian Nasional,No Seri Ijazah,Penerima KIP,Nomor KIP,Nama di KIP,Nomor KKS,No Registrasi Akta Lahir,Bank,Nomor Rekening Bank,Rekening Atas Nama,Layak PIP,Alasan Layak PIP,Kebutuhan Khusus,Sekolah Asal,Anak ke-berapa,Lintang,Bujur,No KK,Berat Badan,Tinggi Badan,Lingkar Kepala,Jml Saudara Kandung,Jarak Rumah ke Sekolah (KM)\n"
            ."Data Pribadi,,,,,,,,,,,,,,,,,,,,,,,,Data Ayah Kandung,,,,,,Data Ibu Kandung,,,,,,Data Wali,,,,,,Lanjutan,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66\n"
            ."1,Ahmad Dapodik,4354/142.07,L,0081234567,Kediri,2007-06-25,3506123456789999,Islam,Jl. Melati No. 10,02,01,Dusun Krajan,Desa Kras,Kecamatan Kras,64172,Bersama orang tua,Sepeda Motor,0354123456,081234567890,ahmad@test.com,,Tidak,,Bapak Ahmad,1975,SMA,Wiraswasta,1000000,,Ibu Ahmad,1978,SMP,IRT,0,,Wali Ahmad,,,,,,XII TKJ 2,,,,Ya,123456,Ahmad Dapodik,,,,,Tidak,,Tidak ada,SMPN 1 Kras,1,-7.9,112.0,3506123456780001,55,165,54,2,2\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'dapodik_test_');
        file_put_contents($tempFile, $dapodikCsv);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'Daftar_PD_SMK_PGRI_KRAS.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post(route('students.import-dapodik'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $student = Student::where('nisn', '0081234567')->first();
        $this->assertNotNull($student);
        $this->assertEquals('AHMAD DAPODIK', $student->name);
        $this->assertEquals('4354/142.07', $student->nis);
        $this->assertEquals('L', $student->gender);
        $this->assertEquals('3506123456789999', $student->nik);
        $this->assertEquals('2007-06-25', $student->birth_date?->format('Y-m-d'));
        $this->assertEquals('Kediri', $student->birth_place);
        $this->assertStringContainsString('Jl. Melati No. 10', $student->address);
        $this->assertStringContainsString('RT 02 RW 01', $student->address);
        $this->assertEquals('081234567890', $student->phone_student);
        $this->assertEquals('0354123456', $student->phone_parent);
        $this->assertEquals('Bapak Ahmad', $student->father_name);
        $this->assertEquals('Ibu Ahmad', $student->mother_name);

        $expectedClass = Classroom::where('name', 'XII TKJ 2')->first();
        $this->assertEquals($expectedClass?->id, $student->classroom_id);

        // Simulasi import ulang tahun berikutnya / kenaikan kelas / ganti no HP
        $updatedCsv = "Daftar Peserta Didik,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."SMK PGRI KRAS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."No,Nama,NIPD,JK,NISN,Tempat Lahir,Tanggal Lahir,NIK,Agama,Alamat,RT,RW,Dusun,Kelurahan,Kecamatan,Kode Pos,Jenis Tinggal,Alat Transportasi,Telepon,HP,E-Mail,SKHUN,Penerima KPS,No. KPS,Nama Ayah,Tahun Lahir Ayah,Jenjang Pendidikan Ayah,Pekerjaan Ayah,Penghasilan Ayah,NIK Ayah,Nama Ibu,Tahun Lahir Ibu,Jenjang Pendidikan Ibu,Pekerjaan Ibu,Penghasilan Ibu,NIK Ibu,Nama Wali,Tahun Lahir Wali,Jenjang Pendidikan Wali,Pekerjaan Wali,Penghasilan Wali,NIK Wali,Rombel Saat Ini,No Peserta Ujian Nasional,No Seri Ijazah,Penerima KIP,Nomor KIP,Nama di KIP,Nomor KKS,No Registrasi Akta Lahir,Bank,Nomor Rekening Bank,Rekening Atas Nama,Layak PIP,Alasan Layak PIP,Kebutuhan Khusus,Sekolah Asal,Anak ke-berapa,Lintang,Bujur,No KK,Berat Badan,Tinggi Badan,Lingkar Kepala,Jml Saudara Kandung,Jarak Rumah ke Sekolah (KM)\n"
            ."Data Pribadi,,,,,,,,,,,,,,,,,,,,,,,,Data Ayah Kandung,,,,,,Data Ibu Kandung,,,,,,Data Wali,,,,,,Lanjutan,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66\n"
            ."1,Ahmad Dapodik Baru,4354/142.07,L,0081234567,Kediri,2007-06-25,3506123456789999,Islam,Jl. Melati No. 10,02,01,Dusun Krajan,Desa Kras,Kecamatan Kras,64172,Bersama orang tua,Sepeda Motor,0899999999,0899999999,ahmad@test.com,,Tidak,,Bapak Ahmad,1975,SMA,Wiraswasta,1000000,,Ibu Ahmad,1978,SMP,IRT,0,,Wali Ahmad,,,,,,XII TKR 1,,,,Ya,123456,Ahmad Dapodik,,,,,Tidak,,Tidak ada,SMPN 1 Kras,1,-7.9,112.0,3506123456780001,55,165,54,2,2\n";

        file_put_contents($tempFile, $updatedCsv);
        $uploadedUpdate = new UploadedFile($tempFile, 'Daftar_PD_Update.csv', 'text/csv', null, true);

        $responseUpdate = $this->actingAs($this->user)->post(route('students.import-dapodik'), [
            'file' => $uploadedUpdate,
        ]);
        $responseUpdate->assertRedirect();

        // Pastikan tidak duplikat, dan data ter-update
        $this->assertEquals(1, Student::where('nisn', '0081234567')->count());
        $freshStudent = $student->fresh();
        $this->assertEquals('AHMAD DAPODIK BARU', $freshStudent->name);
        $this->assertEquals('0899999999', $freshStudent->phone_student);
        $expectedNewClass = Classroom::where('name', 'XII TKR 1')->first();
        $this->assertEquals($expectedNewClass?->id, $freshStudent->classroom_id);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_dapodik_import_automatically_graduates_old_grade_12_and_promotes_grade_11_to_12(): void
    {
        $classGrade12 = Classroom::where('name', 'XII TKJ 2')->first();
        $classGrade11 = Classroom::where('name', 'XI TKJ 1')->first();

        // 1. Siswa kelas XII tahun lalu (akan lulus karena tidak ada di file Dapodik baru)
        $budiGrade12 = Student::create([
            'name' => 'Budi Angkatan Lama',
            'nisn' => '0012345001',
            'nis' => '4001',
            'gender' => 'L',
            'classroom_id' => $classGrade12->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // 2. Siswa kelas XI tahun lalu (akan naik ke kelas XII di file Dapodik baru)
        $antonGrade11 = Student::create([
            'name' => 'Anton Naik Kelas',
            'nisn' => '0012345002',
            'nis' => '4002',
            'gender' => 'L',
            'classroom_id' => $classGrade11->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // File Dapodik tahun ajaran baru: Anton naik ke XII TKJ 2, Dimas siswa baru kelas X, Budi sudah TIDAK ADA
        $newYearDapodikCsv = "Daftar Peserta Didik,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."SMK PGRI KRAS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."No,Nama,NIPD,JK,NISN,Tempat Lahir,Tanggal Lahir,NIK,Agama,Alamat,RT,RW,Dusun,Kelurahan,Kecamatan,Kode Pos,Jenis Tinggal,Alat Transportasi,Telepon,HP,E-Mail,SKHUN,Penerima KPS,No. KPS,Nama Ayah,Tahun Lahir Ayah,Jenjang Pendidikan Ayah,Pekerjaan Ayah,Penghasilan Ayah,NIK Ayah,Nama Ibu,Tahun Lahir Ibu,Jenjang Pendidikan Ibu,Pekerjaan Ibu,Penghasilan Ibu,NIK Ibu,Nama Wali,Tahun Lahir Wali,Jenjang Pendidikan Wali,Pekerjaan Wali,Penghasilan Wali,NIK Wali,Rombel Saat Ini,No Peserta Ujian Nasional,No Seri Ijazah,Penerima KIP,Nomor KIP,Nama di KIP,Nomor KKS,No Registrasi Akta Lahir,Bank,Nomor Rekening Bank,Rekening Atas Nama,Layak PIP,Alasan Layak PIP,Kebutuhan Khusus,Sekolah Asal,Anak ke-berapa,Lintang,Bujur,No KK,Berat Badan,Tinggi Badan,Lingkar Kepala,Jml Saudara Kandung,Jarak Rumah ke Sekolah (KM)\n"
            ."Data Pribadi,,,,,,,,,,,,,,,,,,,,,,,,Data Ayah Kandung,,,,,,Data Ibu Kandung,,,,,,Data Wali,,,,,,Lanjutan,,,,,,,,,,,,,,,,,,,,,,,,,\n"
            ."1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66\n"
            ."1,Anton Naik Kelas,4002,L,0012345002,Kediri,2008-01-01,3506123456780001,Islam,Kras,,,,,,,Sepeda Motor,,081234567891,,,,Tidak,,Ayah Anton,,,Wiraswasta,,,Ibu Anton,,,IRT,,,,,,,,,XII TKJ 2,,,,Tidak,,,,,,,,,Tidak,,Tidak ada,SMPN 1 Kras,1,,,,50,160,54,1,1\n"
            ."2,Dimas Siswa Baru,4003,L,0012345003,Kediri,2009-02-02,3506123456780002,Islam,Kras,,,,,,,Jalan Kaki,,081234567892,,,,Tidak,,Ayah Dimas,,,Petani,,,Ibu Dimas,,,IRT,,,,,,,,,X TKJ 1,,,,Tidak,,,,,,,,,Tidak,,Tidak ada,SMPN 2 Kras,1,,,,45,155,53,2,2\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rollover_dapodik_');
        file_put_contents($tempFile, $newYearDapodikCsv);

        $uploadedFile = new UploadedFile($tempFile, 'Daftar_PD_New_Year.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->user)->post(route('students.import-dapodik'), [
            'file' => $uploadedFile,
            'auto_graduate_grade_12' => '1',
            'auto_create_classes' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. Verifikasi Anton: Naik ke kelas XII TKJ 2, last_classroom_name mencatat rombel lama XI TKJ 1
        $freshAnton = $antonGrade11->fresh();
        $this->assertEquals($classGrade12->id, $freshAnton->classroom_id);
        $this->assertEquals('XI TKJ 1', $freshAnton->last_classroom_name);
        $this->assertEquals('aktif', $freshAnton->status);
        $this->assertTrue($freshAnton->is_active);

        // 2. Verifikasi Budi: Otomatis diluluskan menjadi Alumni (tidak ada di file Dapodik baru)
        $freshBudi = $budiGrade12->fresh();
        $this->assertEquals('lulus', $freshBudi->status);
        $this->assertFalse($freshBudi->is_active);
        $this->assertNull($freshBudi->classroom_id);
        $this->assertEquals('XII TKJ 2', $freshBudi->last_classroom_name);

        // 3. Verifikasi Rombel XII TKJ 2: Hanya berisi Anton, Budi tidak dobel di dalamnya!
        $activeInGrade12 = Student::where('classroom_id', $classGrade12->id)->where('status', 'aktif')->get();
        $this->assertEquals(1, $activeInGrade12->count());
        $this->assertEquals($freshAnton->id, $activeInGrade12->first()->id);

        // 4. Verifikasi Siswa Baru Dimas terdaftar di X TKJ 1
        $dimas = Student::where('nisn', '0012345003')->first();
        $this->assertNotNull($dimas);
        $this->assertEquals('DIMAS SISWA BARU', $dimas->name);
        $expectedDimasClass = Classroom::where('name', 'X TKJ 1')->first();
        $this->assertEquals($expectedDimasClass?->id, $dimas->classroom_id);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    public function test_student_tabs_can_be_accessed(): void
    {
        Student::create([
            'name' => 'Siswa Aktif Test',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        Student::create([
            'name' => 'Siswa Alumni Test',
            'classroom_id' => null,
            'last_classroom_name' => 'XII TKRO 1',
            'gender' => 'P',
            'status' => 'lulus',
            'graduation_year' => '2025/2026',
            'is_active' => false,
        ]);

        Student::create([
            'name' => 'Siswa Mutasi Test',
            'classroom_id' => null,
            'last_classroom_name' => 'X TKRO 1',
            'gender' => 'L',
            'status' => 'mutasi',
            'is_active' => false,
        ]);

        // 1. Tab Aktif
        $resAktif = $this->actingAs($this->user)->get(route('students.index', ['tab' => 'aktif']));
        $resAktif->assertStatus(200);
        $resAktif->assertSee('SISWA AKTIF TEST');
        $resAktif->assertDontSee('SISWA ALUMNI TEST');

        // 2. Tab Alumni
        $resAlumni = $this->actingAs($this->user)->get(route('students.index', ['tab' => 'alumni']));
        $resAlumni->assertStatus(200);
        $resAlumni->assertSee('SISWA ALUMNI TEST');
        $resAlumni->assertDontSee('SISWA AKTIF TEST');

        // 3. Tab Mutasi
        $resMutasi = $this->actingAs($this->user)->get(route('students.index', ['tab' => 'mutasi']));
        $resMutasi->assertStatus(200);
        $resMutasi->assertSee('SISWA MUTASI TEST');
        $resMutasi->assertDontSee('SISWA AKTIF TEST');
    }

    public function test_student_promotions_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('students.promotions.index', [
            'source_classroom_id' => $this->classroom->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Penataan Rombel');
        $response->assertSee($this->classroom->name);
    }

    public function test_student_promotions_process_promotion_and_graduation(): void
    {
        $targetClassroom = Classroom::create([
            'name' => 'XI TKRO 1',
            'academic_year' => '2027/2028',
            'grade_level_id' => $this->classroom->grade_level_id,
            'is_active' => true,
        ]);

        $st1 = Student::create([
            'name' => 'Siswa Naik Kelas',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $st2 = Student::create([
            'name' => 'Siswa Tinggal Kelas',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $st3 = Student::create([
            'name' => 'Siswa Lulus Menjadi Alumni',
            'classroom_id' => $this->classroom->id,
            'gender' => 'P',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('students.promotions.process'), [
            'source_classroom_id' => $this->classroom->id,
            'target_academic_year' => '2027/2028',
            'students' => [
                [
                    'student_id' => $st1->id,
                    'action' => 'naik',
                    'target_classroom_id' => $targetClassroom->id,
                ],
                [
                    'student_id' => $st2->id,
                    'action' => 'tinggal',
                    'target_classroom_id' => $this->classroom->id,
                ],
                [
                    'student_id' => $st3->id,
                    'action' => 'lulus',
                    'graduation_year' => '2027',
                ],
            ],
        ]);

        $response->assertRedirect();

        // st1 must be in target classroom, status aktif
        $this->assertEquals($targetClassroom->id, $st1->fresh()->classroom_id);
        $this->assertEquals('aktif', $st1->fresh()->status);
        $this->assertTrue((bool) $st1->fresh()->is_active);

        // st2 must remain in grade, status aktif
        $this->assertEquals($this->classroom->id, $st2->fresh()->classroom_id);
        $this->assertEquals('aktif', $st2->fresh()->status);

        // st3 must be alumni with graduation year
        $this->assertNull($st3->fresh()->classroom_id);
        $this->assertEquals('lulus', $st3->fresh()->status);
        $this->assertFalse((bool) $st3->fresh()->is_active);
        $this->assertEquals('2027', $st3->fresh()->graduation_year);
        $this->assertEquals($this->classroom->name, $st3->fresh()->last_classroom_name);
    }

    public function test_student_sorting_by_name_classroom_and_homeroom(): void
    {
        Student::create([
            'name' => 'Zara Zaskia',
            'classroom_id' => $this->classroom->id,
            'gender' => 'P',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        Student::create([
            'name' => 'Aditya Pratama',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // Sort name asc
        $resAsc = $this->actingAs($this->user)->get(route('students.index', ['sort' => 'name', 'direction' => 'asc']));
        $resAsc->assertStatus(200);
        $resAsc->assertSeeInOrder(['ADITYA PRATAMA', 'ZARA ZASKIA']);

        // Sort name desc
        $resDesc = $this->actingAs($this->user)->get(route('students.index', ['sort' => 'name', 'direction' => 'desc']));
        $resDesc->assertStatus(200);
        $resDesc->assertSeeInOrder(['ZARA ZASKIA', 'ADITYA PRATAMA']);

        // Sort classroom
        $resClass = $this->actingAs($this->user)->get(route('students.index', ['sort' => 'classroom', 'direction' => 'asc']));
        $resClass->assertStatus(200);

        // Sort homeroom
        $resHome = $this->actingAs($this->user)->get(route('students.index', ['sort' => 'homeroom', 'direction' => 'asc']));
        $resHome->assertStatus(200);
    }

    public function test_academic_year_rollover_promotes_graduates_and_handles_exceptions(): void
    {
        $gradeX = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Kelas X']);
        $gradeXI = GradeLevel::firstOrCreate(['level' => 'XI'], ['name' => 'Kelas XI']);
        $gradeXII = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Kelas XII']);

        $classX = Classroom::create([
            'name' => 'X TKRO 1',
            'academic_year' => '2025/2026',
            'grade_level_id' => $gradeX->id,
            'is_active' => true,
        ]);

        $classXII = Classroom::create([
            'name' => 'XII TKRO 1',
            'academic_year' => '2025/2026',
            'grade_level_id' => $gradeXII->id,
            'is_active' => true,
        ]);

        $studentPromoted = Student::create([
            'name' => 'Siswa Naik Otomatis',
            'classroom_id' => $classX->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $studentRetained = Student::create([
            'name' => 'Siswa Tinggal Kelas',
            'classroom_id' => $classX->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $studentGraduate = Student::create([
            'name' => 'Siswa Lulus XII',
            'classroom_id' => $classXII->id,
            'gender' => 'P',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('students.promotions.rollover'), [
            'source_academic_year' => '2025/2026',
            'target_academic_year' => '2026/2027',
            'classroom_mappings' => [
                $classX->id => 'auto_create',
                $classXII->id => 'lulus',
            ],
            'exceptions' => [
                $studentRetained->id => [
                    'action' => 'tinggal',
                    'target_classroom_id' => null,
                ],
            ],
        ]);

        $response->assertRedirect(route('students.promotions.index', ['tab' => 'rollover']));
        $response->assertSessionHas('success');

        // Check log created
        $log = AcademicYearRolloverLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('2025/2026', $log->source_academic_year);
        $this->assertEquals('2026/2027', $log->target_academic_year);
        $this->assertFalse((bool) $log->is_rolled_back);

        // Check student promoted to auto-created XI TKRO 1
        $targetClassXI = Classroom::where('name', 'XI TKRO 1')->where('academic_year', '2026/2027')->first();
        $this->assertNotNull($targetClassXI);
        $this->assertEquals($targetClassXI->id, $studentPromoted->fresh()->classroom_id);
        $this->assertEquals('aktif', $studentPromoted->fresh()->status);
        $this->assertEquals('X TKRO 1', $studentPromoted->fresh()->last_classroom_name);

        // Check student retained
        $this->assertEquals($classX->id, $studentRetained->fresh()->classroom_id);
        $this->assertEquals('aktif', $studentRetained->fresh()->status);

        // Check student graduated
        $this->assertNull($studentGraduate->fresh()->classroom_id);
        $this->assertEquals('lulus', $studentGraduate->fresh()->status);
        $this->assertFalse((bool) $studentGraduate->fresh()->is_active);
        $this->assertEquals('2026/2027', $studentGraduate->fresh()->graduation_year);
    }

    public function test_academic_year_rollover_can_be_rolled_back(): void
    {
        $gradeX = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Kelas X']);

        $classX = Classroom::create([
            'name' => 'X DPIB 1',
            'academic_year' => '2025/2026',
            'grade_level_id' => $gradeX->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'Budi Rolled Back',
            'classroom_id' => $classX->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // Rollover first
        $this->actingAs($this->user)->post(route('students.promotions.rollover'), [
            'source_academic_year' => '2025/2026',
            'target_academic_year' => '2026/2027',
            'classroom_mappings' => [
                $classX->id => 'auto_create',
            ],
        ]);

        $targetClass = Classroom::where('name', 'XI DPIB 1')->where('academic_year', '2026/2027')->first();
        $this->assertEquals($targetClass->id, $student->fresh()->classroom_id);

        $log = AcademicYearRolloverLog::latest()->first();

        // Rollback
        $response = $this->actingAs($this->user)->post(route('students.promotions.rollback', $log));
        $response->assertRedirect(route('students.promotions.index', ['tab' => 'rollover']));
        $response->assertSessionHas('success');

        $this->assertTrue((bool) $log->fresh()->is_rolled_back);
        $this->assertEquals($classX->id, $student->fresh()->classroom_id);
        $this->assertEquals('aktif', $student->fresh()->status);
        $this->assertTrue((bool) $student->fresh()->is_active);
    }

    public function test_quick_transfer_moves_selected_students_between_classrooms(): void
    {
        $gradeX = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Kelas X']);

        $classA = Classroom::create([
            'name' => 'X TBSM 1',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeX->id,
            'is_active' => true,
        ]);

        $classB = Classroom::create([
            'name' => 'X TBSM 2',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeX->id,
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'name' => 'Siswa Pindah',
            'classroom_id' => $classA->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'name' => 'Siswa Tetap',
            'classroom_id' => $classA->id,
            'gender' => 'P',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('students.transfers.process'), [
            'source_classroom_id' => $classA->id,
            'target_classroom_id' => $classB->id,
            'student_ids' => [$student1->id],
            'reason' => 'Pemerataan jumlah siswa per rombel',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals($classB->id, $student1->fresh()->classroom_id);
        $this->assertEquals('X TBSM 1', $student1->fresh()->last_classroom_name);
        $this->assertEquals($classA->id, $student2->fresh()->classroom_id);
    }

    public function test_record_student_mutation_with_evidence_and_download(): void
    {
        Storage::fake('public');

        $student = Student::create([
            'name' => 'Siswa Mundur Test',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('surat_pengunduran_diri.pdf', 150, 'application/pdf');

        $response = $this->actingAs($this->user)->post(route('students.mutations.store'), [
            'student_id' => $student->id,
            'type' => 'keluar',
            'mutation_date' => '2026-10-09',
            'reason_category' => 'mengundurkan_diri',
            'reason_description' => 'Ingin fokus membantu usaha keluarga',
            'target_school' => null,
            'letter_number' => '421/001/SP/2026',
            'attachment' => $file,
        ]);

        $response->assertRedirect(route('students.index', ['tab' => 'mutasi']));
        $response->assertSessionHas('success');

        $mutation = StudentMutation::where('student_id', $student->id)->first();
        $this->assertNotNull($mutation);
        $this->assertEquals('keluar', $mutation->type);
        $this->assertEquals('mengundurkan_diri', $mutation->reason_category);
        $this->assertEquals('surat_pengunduran_diri.pdf', $mutation->attachment_name);
        $this->assertNotNull($mutation->attachment_path);
        Storage::disk('public')->assertExists($mutation->attachment_path);

        // Student status updated
        $freshStudent = $student->fresh();
        $this->assertEquals('keluar', $freshStudent->status);
        $this->assertFalse((bool) $freshStudent->is_active);
        $this->assertNull($freshStudent->classroom_id);

        // Download attachment test
        $dlResponse = $this->actingAs($this->user)->get(route('students.mutations.download', $mutation->id));
        $dlResponse->assertOk();

        // Destroy / revert mutation test
        $delResponse = $this->actingAs($this->user)->delete(route('students.mutations.destroy', $mutation->id));
        $delResponse->assertRedirect();
        $delResponse->assertSessionHas('success');

        $this->assertDatabaseMissing('student_mutations', ['id' => $mutation->id]);
        Storage::disk('public')->assertMissing($mutation->attachment_path);
        $this->assertEquals('aktif', $student->fresh()->status);
        $this->assertEquals($this->classroom->id, $student->fresh()->classroom_id);
    }

    public function test_automatic_target_academic_year_creation_on_rollover(): void
    {
        $gradeX = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Kelas X']);

        $class = Classroom::create([
            'name' => 'X TJAT 1',
            'academic_year' => '2028/2029',
            'grade_level_id' => $gradeX->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'Siswa Auto TP',
            'classroom_id' => $class->id,
            'gender' => 'L',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $targetYear = '2029/2030';
        $this->assertDatabaseMissing('academic_years', ['name' => $targetYear]);

        $response = $this->actingAs($this->user)->post(route('students.promotions.rollover'), [
            'source_academic_year' => '2028/2029',
            'target_academic_year' => $targetYear,
            'classroom_mappings' => [
                $class->id => 'auto_create',
            ],
            'exceptions' => [],
        ]);

        $response->assertRedirect(route('students.promotions.index', ['tab' => 'rollover']));
        $this->assertDatabaseHas('academic_years', ['name' => $targetYear]);
    }

    public function test_students_active_alumni_and_mutations_are_scoped_by_academic_year(): void
    {
        // Setup data siswa berbagai angkatan
        // 1. Angkatan 2023 (di TP 25/26 Kelas XII aktif, di TP 26/27 Alumni)
        $student23 = Student::create([
            'name' => 'Siswa Angkatan Dua Tiga',
            'entry_year' => 2023,
            'status' => 'lulus',
            'graduation_year' => '2025/2026',
            'is_active' => false,
        ]);

        // 2. Angkatan 2025 (di TP 25/26 Kelas X aktif, di TP 26/27 Kelas XI)
        $student25 = Student::create([
            'name' => 'Siswa Angkatan Dua Lima',
            'entry_year' => 2025,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // 3. Angkatan 2022 (Alumni di 25/26 dan 26/27)
        $student22 = Student::create([
            'name' => 'Siswa Angkatan Dua Dua',
            'entry_year' => 2022,
            'status' => 'lulus',
            'graduation_year' => '2024/2025',
            'is_active' => false,
        ]);

        // 4. Siswa Mutasi di Oktober 2026 (masuk TP 2026/2027)
        $studentMutasi = Student::create([
            'name' => 'Siswa Mutasi Oktober Dua Enam',
            'entry_year' => 2024,
            'status' => 'keluar',
            'is_active' => false,
        ]);
        StudentMutation::create([
            'student_id' => $studentMutasi->id,
            'type' => 'keluar',
            'mutation_date' => '2026-10-14',
            'reason_category' => 'mengundurkan_diri',
            'recorded_by' => $this->user->id,
        ]);

        // --- SKENARIO 1: Konteks Tahun Pelajaran 2025/2026 ---
        // Siswa aktif harus memuat Angkatan 2023 & 2025
        $resAktif25 = $this->actingAs($this->user)->get(route('students.index', [
            'tab' => 'aktif',
            'academic_year' => '2025/2026',
        ]));
        $resAktif25->assertStatus(200);
        $resAktif25->assertSee('SISWA ANGKATAN DUA TIGA');
        $resAktif25->assertSee('SISWA ANGKATAN DUA LIMA');
        $resAktif25->assertDontSee('SISWA ANGKATAN DUA DUA');

        // Alumni di 2025/2026 harus memuat Angkatan 2022, dan TIDAK memuat Angkatan 2023
        $resAlumni25 = $this->actingAs($this->user)->get(route('students.index', [
            'tab' => 'alumni',
            'academic_year' => '2025/2026',
        ]));
        $resAlumni25->assertStatus(200);
        $resAlumni25->assertSee('SISWA ANGKATAN DUA DUA');
        $resAlumni25->assertDontSee('SISWA ANGKATAN DUA TIGA');

        // Mutasi di 2025/2026 TIDAK memuat siswa yang mutasi pada Oktober 2026
        $resMutasi25 = $this->actingAs($this->user)->get(route('students.index', [
            'tab' => 'mutasi',
            'academic_year' => '2025/2026',
        ]));
        $resMutasi25->assertStatus(200);
        $resMutasi25->assertDontSee('SISWA MUTASI OKTOBER DUA ENAM');

        // --- SKENARIO 2: Konteks Tahun Pelajaran 2026/2027 ---
        // Alumni di 2026/2027 sekarang memuat Angkatan 2023
        $resAlumni26 = $this->actingAs($this->user)->get(route('students.index', [
            'tab' => 'alumni',
            'academic_year' => '2026/2027',
        ]));
        $resAlumni26->assertStatus(200);
        $resAlumni26->assertSee('SISWA ANGKATAN DUA TIGA');

        // Mutasi di 2026/2027 sekarang memuat siswa mutasi Oktober 2026
        $resMutasi26 = $this->actingAs($this->user)->get(route('students.index', [
            'tab' => 'mutasi',
            'academic_year' => '2026/2027',
        ]));
        $resMutasi26->assertStatus(200);
        $resMutasi26->assertSee('SISWA MUTASI OKTOBER DUA ENAM');
    }
}
