<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SimpleXlsxGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_with_manage_teachers_permission_can_download_excel_template(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $response = $this->actingAs($admin)->get(route('teachers.template'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'spreadsheetml.sheet'));
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), 'template_import_guru.xlsx'));

        $content = $response->streamedContent();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_dl_');
        file_put_contents($tempFile, $content);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempFile));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Nama Lengkap', $sheetXml);
        $this->assertStringContainsString('NIGS', $sheetXml);
        $zip->close();
        unlink($tempFile);
    }

    public function test_guest_or_unauthorized_user_cannot_access_import_or_template(): void
    {
        $guestResponse = $this->get(route('teachers.template'));
        $guestResponse->assertRedirect(route('login'));

        $guruRole = Role::where('slug', 'guru')->first();
        $guru = User::factory()->create([
            'username' => 'test_unauth_guru',
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $responseTemplate = $this->actingAs($guru)->get(route('teachers.template'));
        $responseTemplate->assertStatus(403);

        $responseImport = $this->actingAs($guru)->post(route('teachers.import'), []);
        $responseImport->assertStatus(403);
    }

    public function test_admin_can_import_teachers_from_xlsx_file(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $headers = [
            'No', 'Nama Lengkap', 'NIGS', 'NIP', 'NUPTK', 'NIK', 'JK (L/P)',
            'Tempat Tanggal Lahir', 'No HP', 'Alamat', 'Status GTK', 'Jabatan',
            'Pendidikan', 'Jurusan', 'Singkatan Nama',
        ];

        $rows = [
            [
                '1', 'Hendra Kusuma, M.Pd.', 'G-771', '198105052007011005', '5566778899001122',
                '3506050505810002', 'L', 'Kediri, 5 Mei 1981', '081333444555',
                'Jl. Veteran No. 8, Kediri', 'GTY', 'Guru', 'S2', 'Manajemen Pendidikan', 'HDK',
            ],
        ];

        $xlsxContent = SimpleXlsxGenerator::createBinary('Data Guru', $headers, $rows);
        $file = UploadedFile::fake()->createWithContent('import_guru.xlsx', $xlsxContent);

        $response = $this->actingAs($admin)->post(route('teachers.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Hendra Kusuma, M.Pd.',
            'nigs' => 'G771',
            'nip' => '198105052007011005',
            'phone' => '081333444555',
            'short_name' => 'HDK',
            'education_level' => 'S2',
        ]);
    }

    public function test_admin_can_import_new_teachers_from_csv(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $csvContent = implode("\n", [
            'No,Nama Lengkap,NIGS,NIP,NUPTK,NIK,JK (L/P),Tempat Tanggal Lahir,No HP,Alamat,Status GTK,Jabatan,Pendidikan,Jurusan,Singkatan Nama',
            '1,Budi Wicaksono S.Pd.,G-991,198001012005011001,1122334455667788,3506010101800001,L,"Kediri, 01-01-1980",081234567899,Jl. Dahlia No 10,GTY,Guru,S1,Pendidikan Fisika,BWS',
        ]);

        $file = UploadedFile::fake()->createWithContent('import_guru.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('teachers.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Wicaksono S.Pd.',
            'nigs' => 'G991',
            'nip' => '198001012005011001',
            'phone' => '081234567899',
            'short_name' => 'BWS',
        ]);
    }

    public function test_import_updates_existing_teacher_without_changing_password(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $guruRole = Role::where('slug', 'guru')->first();

        $existingTeacher = User::factory()->create([
            'name' => 'Siti Aminah S.Pd.',
            'username' => 'guru_siti_aminah',
            'role_id' => $guruRole->id,
            'nip' => '198202022006022002',
            'nigs' => 'G992',
            'phone' => '081111111111',
            'password' => Hash::make('secret_password_lama'),
            'is_active' => true,
        ]);

        $oldPasswordHash = $existingTeacher->password;

        $csvContent = implode("\n", [
            'No,Nama Lengkap,NIGS,NIP,NUPTK,NIK,JK (L/P),Tempat Tanggal Lahir,No HP,Alamat,Status GTK,Jabatan,Pendidikan,Jurusan,Singkatan Nama',
            '1,Siti Aminah S.Pd.,G-992,198202022006022002,,,P,,089999999999,Jl. Anyar No 12,GTY,Guru,S2,Magister Pendidikan,SMA',
        ]);

        $file = UploadedFile::fake()->createWithContent('update_guru.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('teachers.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('success');

        $existingTeacher->refresh();
        $this->assertEquals('089999999999', $existingTeacher->phone);
        $this->assertEquals('Jl. Anyar No 12', $existingTeacher->address);
        $this->assertEquals('S2', $existingTeacher->education_level);
        $this->assertEquals($oldPasswordHash, $existingTeacher->password, 'Password must not be changed on import');
    }

    public function test_can_import_teachers_from_semicolon_delimited_csv(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $csvContent = implode("\n", [
            'No;Nama Lengkap;NIGS;NIP;NUPTK;NIK;JK (L/P);Tempat Tanggal Lahir;No HP;Alamat;Status GTK;Jabatan;Pendidikan;Jurusan;Singkatan Nama',
            '1;Rina Wijayanti S.Pd.;G-993;198703032008032003;;;P;Kediri 10-10-1987;081234567811;Jl. Melati 15;GTT;Guru;S1;Pendidikan Biologi;RNW',
        ]);

        $file = UploadedFile::fake()->createWithContent('import_guru_semicolon.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('teachers.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Rina Wijayanti S.Pd.',
            'nigs' => 'G993',
            'short_name' => 'RNW',
            'education_level' => 'S1',
        ]);
    }
}
