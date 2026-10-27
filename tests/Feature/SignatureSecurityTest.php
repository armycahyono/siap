<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolStakeholder;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $normalTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $wakaRole = Role::where('slug', 'waka_kurikulum')->first()
            ?? Role::create(['slug' => 'waka_kurikulum', 'name' => 'Waka Kurikulum', 'description' => 'Wakil Kepala Kurikulum']);

        $perm = Permission::where('slug', 'manage_master_data')->first();
        if ($perm && ! $wakaRole->permissions()->where('permissions.id', $perm->id)->exists()) {
            $wakaRole->permissions()->attach($perm->id);
        }

        $this->adminUser = User::firstOrCreate(
            ['username' => 'waka_test'],
            [
                'name' => 'Waka Kurikulum Test',
                'email' => 'waka_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $wakaRole->id,
                'is_active' => true,
            ]
        );

        $guruRole = Role::where('slug', 'guru')->first()
            ?? Role::create(['slug' => 'guru', 'name' => 'Guru Pengajar', 'description' => 'Guru']);

        $this->normalTeacher = User::firstOrCreate(
            ['username' => 'guru_test'],
            [
                'name' => 'Guru Biasa Test',
                'email' => 'guru_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );
    }

    /**
     * Skenario 1: Upload tanda tangan baru menyimpan file ke disk privat ('local').
     */
    public function test_01_upload_tanda_tangan_baru_menyimpan_file_ke_disk_privat(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $file = UploadedFile::fake()->image('signature_pos.png', 200, 100);

        // Upload pada StructuralPosition
        $response = $this->actingAs($this->adminUser)->post(route('structural-positions.store'), [
            'name' => 'Wakil Kepala Hubin',
            'code' => 'WAKA_HUBIN',
            'signature_image' => $file,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('teacher-assignments.index', ['tab' => 'struktural']));

        $pos = StructuralPosition::where('code', 'WAKA_HUBIN')->first();
        $this->assertNotNull($pos);
        $this->assertNotNull($pos->signature_image_path);
        Storage::disk('local')->assertExists($pos->signature_image_path);
        Storage::disk('public')->assertMissing($pos->signature_image_path);

        // Upload pada SchoolStakeholder
        $fileSh = UploadedFile::fake()->image('signature_sh.png', 200, 100);
        $responseSh = $this->actingAs($this->adminUser)->post(route('stakeholders.store'), [
            'category' => 'komite',
            'name' => 'Ketua Komite H. Ahmad',
            'position' => 'Ketua Komite',
            'signature_image' => $fileSh,
            'is_active' => true,
        ]);

        $responseSh->assertRedirect(route('stakeholders.index', ['category' => 'komite']));

        $sh = SchoolStakeholder::where('name', 'Ketua Komite H. Ahmad')->first();
        $this->assertNotNull($sh);
        $this->assertNotNull($sh->signature_image_path);
        Storage::disk('local')->assertExists($sh->signature_image_path);
        Storage::disk('public')->assertMissing($sh->signature_image_path);
    }

    /**
     * Skenario 2: Pengguna tanpa autentikasi tidak dapat mengakses tanda tangan melalui endpoint privat.
     */
    public function test_02_pengguna_tanpa_autentikasi_tidak_dapat_mengakses_tanda_tangan_melalui_endpoint_privat(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('signatures/sample_pos.png', 'test-pos-content');
        Storage::disk('local')->put('signatures/sample_sh.png', 'test-sh-content');

        $pos = StructuralPosition::create([
            'name' => 'Kepala Lab',
            'code' => 'KAPROGLI',
            'signature_image_path' => 'signatures/sample_pos.png',
        ]);

        $sh = SchoolStakeholder::create([
            'category' => 'yayasan',
            'name' => 'Ketua Pembina',
            'position' => 'Ketua Pembina Yayasan',
            'signature_image_path' => 'signatures/sample_sh.png',
        ]);

        // Request guest tanpa auth diarahkan ke halaman login
        $this->get(route('structural-positions.signature', $pos))->assertRedirect(route('login'));
        $this->get(route('stakeholders.signature', $sh))->assertRedirect(route('login'));
    }

    /**
     * Skenario 3: Pengguna tanpa kewenangan tidak dapat mengakses tanda tangan (403 Forbidden).
     */
    public function test_03_pengguna_tanpa_kewenangan_tidak_dapat_mengakses_tanda_tangan(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('signatures/sample_pos.png', 'test-pos-content');
        Storage::disk('local')->put('signatures/sample_sh.png', 'test-sh-content');

        $pos = StructuralPosition::create([
            'name' => 'Kepala Bengkel',
            'code' => 'KABENG',
            'signature_image_path' => 'signatures/sample_pos.png',
        ]);

        $sh = SchoolStakeholder::create([
            'category' => 'pengawas',
            'name' => 'Drs. Supriyanto, M.Pd.',
            'position' => 'Pengawas Pembina',
            'signature_image_path' => 'signatures/sample_sh.png',
        ]);

        // Guru biasa tanpa permission manage_master_data ditolak dengan status 403
        $this->actingAs($this->normalTeacher)
            ->get(route('structural-positions.signature', $pos))
            ->assertStatus(403);

        $this->actingAs($this->normalTeacher)
            ->get(route('stakeholders.signature', $sh))
            ->assertStatus(403);
    }

    /**
     * Skenario 4: Pengguna berwenang dapat mengambil tanda tangan sesuai kebutuhan aplikasi.
     */
    public function test_04_pengguna_berwenang_dapat_mengambil_tanda_tangan(): void
    {
        Storage::fake('local');
        $rawImageContent = UploadedFile::fake()->image('valid_sig.png', 100, 50)->get();
        Storage::disk('local')->put('signatures/sample_pos.png', $rawImageContent);
        Storage::disk('local')->put('signatures/sample_sh.png', $rawImageContent);

        $pos = StructuralPosition::create([
            'name' => 'Kepala Perpustakaan',
            'code' => 'KAPERPUS',
            'signature_image_path' => 'signatures/sample_pos.png',
        ]);

        $sh = SchoolStakeholder::create([
            'category' => 'dinas',
            'name' => 'Kepala Cabdin',
            'position' => 'Kacabdin',
            'signature_image_path' => 'signatures/sample_sh.png',
        ]);

        // 1. Admin/Waka dengan permission manage_master_data berhasil mengambil tanda tangan
        $responsePos = $this->actingAs($this->adminUser)->get(route('structural-positions.signature', $pos));
        $responsePos->assertStatus(200);

        $responseSh = $this->actingAs($this->adminUser)->get(route('stakeholders.signature', $sh));
        $responseSh->assertStatus(200);

        // 2. Guru yang secara resmi ditugaskan pada jabatan tersebut berwenang melihat tanda tangan jabatannya
        TeacherStructuralAssignment::create([
            'teacher_id' => $this->normalTeacher->id,
            'structural_position_id' => $pos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $responseAssigned = $this->actingAs($this->normalTeacher)->get(route('structural-positions.signature', $pos));
        $responseAssigned->assertStatus(200);
    }

    /**
     * Skenario 5: Path traversal atau manipulasi path ditolak.
     */
    public function test_05_path_traversal_atau_manipulasi_path_ditolak(): void
    {
        Storage::fake('local');

        // Kasus 1: Path traversal dengan ../..
        $posTraversal = StructuralPosition::create([
            'name' => 'Hacker Pos',
            'signature_image_path' => 'signatures/../../config/app.php',
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('structural-positions.signature', $posTraversal))
            ->assertStatus(404);

        // Kasus 2: Path di luar direktori signatures/
        $posOutside = StructuralPosition::create([
            'name' => 'Outside Pos',
            'signature_image_path' => 'uploads/malicious.png',
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('structural-positions.signature', $posOutside))
            ->assertStatus(404);

        // Kasus 3: Path kosong / null
        $posEmpty = StructuralPosition::create([
            'name' => 'Empty Pos',
            'signature_image_path' => null,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('structural-positions.signature', $posEmpty))
            ->assertStatus(404);
    }

    /**
     * Skenario 6: Pembuatan PDF tetap berhasil dan tanda tangan tampil.
     */
    public function test_06_pembuatan_pdf_tetap_berhasil_dan_tanda_tangan_tampil(): void
    {
        Storage::fake('local');
        $samplePng = UploadedFile::fake()->image('doc_sig.png', 80, 40)->get();
        Storage::disk('local')->put('signatures/doc_sig.png', $samplePng);

        $pos = StructuralPosition::create([
            'name' => 'Kepala Sekolah Pengesahan',
            'code' => 'KS_PDF_TEST',
            'signature_image_path' => 'signatures/doc_sig.png',
        ]);

        $sh = SchoolStakeholder::create([
            'category' => 'yayasan',
            'name' => 'Ketua Yayasan',
            'position' => 'Ketua',
            'signature_image_path' => 'signatures/doc_sig.png',
        ]);

        // Verifikasi metode getSignatureRealPath()
        $realPathPos = $pos->getSignatureRealPath();
        $this->assertNotNull($realPathPos);
        $this->assertFileExists($realPathPos);

        // Verifikasi metode getSignatureBase64()
        $base64Pos = $pos->getSignatureBase64();
        $this->assertNotNull($base64Pos);
        $this->assertStringStartsWith('data:image/png;base64,', $base64Pos);

        $base64Sh = $sh->getSignatureBase64();
        $this->assertNotNull($base64Sh);
        $this->assertStringStartsWith('data:image/png;base64,', $base64Sh);

        // Uji pembuatan PDF menggunakan Dompdf tanpa error remote file
        $html = "
            <html>
                <body>
                    <h1>Lembar Pengesahan</h1>
                    <p>Jabatan: {$pos->name}</p>
                    <img src=\"{$base64Pos}\" alt=\"Tanda Tangan Digital\" style=\"height: 50px;\">
                </body>
            </html>
        ";

        $pdf = Pdf::loadHTML($html);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    /**
     * Skenario 7: Penggantian tanda tangan tidak menghilangkan file lama jika upload baru gagal.
     */
    public function test_07_penggantian_tanda_tangan_tidak_menghilangkan_file_lama_jika_upload_baru_gagal(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->image('old_sig.png', 100, 50);
        $this->actingAs($this->adminUser)->post(route('structural-positions.store'), [
            'name' => 'Bendahara Sekolah Test 07',
            'code' => 'BENDAHARA_TEST_07',
            'signature_image' => $initialFile,
        ]);

        $pos = StructuralPosition::where('code', 'BENDAHARA_TEST_07')->first();
        $this->assertNotNull($pos);
        $oldPath = $pos->signature_image_path;
        $this->assertNotNull($oldPath);
        Storage::disk('local')->assertExists($oldPath);

        // Simulasi pengiriman file baru yang tidak valid (bukan gambar / teks biasa)
        $invalidFile = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');

        $response = $this->actingAs($this->adminUser)->put(route('structural-positions.update', $pos), [
            'name' => 'Bendahara Sekolah Test 07',
            'code' => 'BENDAHARA_TEST_07',
            'signature_image' => $invalidFile,
        ]);

        $response->assertSessionHasErrors('signature_image');

        // Pastikan berkas lama masih tetap ada di disk privat dan path database tidak rusak
        $pos->refresh();
        $this->assertEquals($oldPath, $pos->signature_image_path);
        Storage::disk('local')->assertExists($oldPath);
    }

    /**
     * Skenario 8: File tanda tangan lama dapat dimigrasikan dengan aman.
     */
    public function test_08_file_tanda_tangan_lama_dapat_dimigrasikan_dengan_aman(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $legacyContent = UploadedFile::fake()->image('legacy_sig.png', 120, 60)->get();
        $legacyPath = 'signatures/sig_legacy_001.png';

        // Simpan file warisan di disk publik
        Storage::disk('public')->put($legacyPath, $legacyContent);

        $pos = StructuralPosition::create([
            'name' => 'Waka Humas Legacy',
            'signature_image_path' => $legacyPath,
        ]);

        $sh = SchoolStakeholder::create([
            'category' => 'komite',
            'name' => 'Bendahara Komite',
            'position' => 'Bendahara',
            'signature_image_path' => $legacyPath,
        ]);

        // Jalankan migrasi berkas sensitif dengan opsi penghapusan berkas sumber publik
        $exitCode = Artisan::call('files:migrate-sensitive', [
            '--delete-source' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Berkas sekarang harus ada di disk privat dan bersih dari disk publik
        Storage::disk('local')->assertExists($legacyPath);
        Storage::disk('public')->assertMissing($legacyPath);

        // Verifikasi integritas isi berkas (SHA-256)
        $this->assertEquals(
            hash('sha256', $legacyContent),
            hash('sha256', Storage::disk('local')->get($legacyPath))
        );
    }

    /**
     * Skenario 9: Migrasi yang dijalankan ulang tidak merusak data (idempoten).
     */
    public function test_09_migrasi_yang_dijalankan_ulang_tidak_merusak_data(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $content = 'idempotent-signature-data';
        $path = 'signatures/sig_idem_002.png';

        Storage::disk('public')->put($path, $content);

        StructuralPosition::create([
            'name' => 'Waka Sarpras',
            'signature_image_path' => $path,
        ]);

        // Migrasi putaran pertama
        $exitCode1 = Artisan::call('files:migrate-sensitive', ['--delete-source' => true]);
        $this->assertEquals(0, $exitCode1);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        // Migrasi putaran kedua (ulang)
        $exitCode2 = Artisan::call('files:migrate-sensitive', ['--delete-source' => true]);
        $this->assertEquals(0, $exitCode2);

        // File tetap ada di disk privat dan isinya tidak berubah
        Storage::disk('local')->assertExists($path);
        $this->assertEquals($content, Storage::disk('local')->get($path));
    }

    /**
     * Skenario 10: File tanda tangan lama tidak lagi dapat diakses melalui URL publik setelah proses pengamanan selesai.
     */
    public function test_10_file_tanda_tangan_lama_tidak_lagi_dapat_diakses_melalui_url_publik(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $sigContent = 'secret-signature-bytes';
        $sigPath = 'signatures/sig_pos_secret.png';

        // File awalnya ada di public
        Storage::disk('public')->put($sigPath, $sigContent);
        $this->assertTrue(Storage::disk('public')->exists($sigPath));

        StructuralPosition::create([
            'name' => 'Kepala Sekolah Rahasia',
            'signature_image_path' => $sigPath,
        ]);

        // Migrasikan dan bersihkan dari publik
        Artisan::call('files:migrate-sensitive', ['--delete-source' => true]);

        // 1. Verifikasi disk publik tidak lagi menyimpan berkas
        Storage::disk('public')->assertMissing($sigPath);
        $this->assertFalse(Storage::disk('public')->exists($sigPath));

        // 2. Berkas hanya tersimpan di disk privat
        Storage::disk('local')->assertExists($sigPath);

        // 3. Akses via web harus melalui endpoint terotorisasi
        $pos = StructuralPosition::where('signature_image_path', $sigPath)->first();
        $this->actingAs($this->adminUser)
            ->get(route('structural-positions.signature', $pos))
            ->assertStatus(200);

        // Pengguna tanpa izin tetap diblokir
        $this->actingAs($this->normalTeacher)
            ->get(route('structural-positions.signature', $pos))
            ->assertStatus(403);
    }
}
