<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\GtkPermit;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supervision;
use App\Models\SupervisionPhoto;
use App\Models\TimetableUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SensitiveFileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $principal;

    protected User $teacherA;

    protected User $teacherB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Storage::fake('local');
        Storage::fake('public');

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $principalRole = Role::where('slug', 'kepala_sekolah')->first();
        $teacherRole = Role::where('slug', 'guru')->first();

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'username' => 'superadmin_test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $this->principal = User::create([
            'name' => 'Kepala Sekolah',
            'username' => 'kepsek_test',
            'email' => 'kepsek@test.com',
            'password' => bcrypt('password'),
            'role_id' => $principalRole->id,
            'is_active' => true,
        ]);

        $this->teacherA = User::create([
            'name' => 'Guru A',
            'username' => 'gurua_test',
            'email' => 'gurua@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
            'nigs' => '1000000001',
        ]);

        $this->teacherB = User::create([
            'name' => 'Guru B',
            'username' => 'gurub_test',
            'email' => 'gurub@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
            'nigs' => '1000000002',
        ]);
    }

    public function test_unauthenticated_guests_cannot_access_sensitive_files(): void
    {
        Storage::disk('local')->put('attendance/geotag/test.jpg', 'fake-image');
        $log = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => '1000000001',
            'user_type' => 'teacher',
            'user_id' => $this->teacherA->id,
            'name' => $this->teacherA->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'raw_payload' => json_encode(['selfie_path' => 'attendance/geotag/test.jpg']),
        ]);

        $response = $this->get(route('attendance.geotag.selfie', $log));
        $this->assertTrue(in_array($response->status(), [302, 401]));

        Storage::disk('local')->put('gtk_permits/doc.pdf', 'fake-pdf');
        $permit = GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'sakit',
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Sakit demam',
            'document_path' => 'gtk_permits/doc.pdf',
        ]);

        $response = $this->get(route('attendance.gtk-permits.document', $permit));
        $this->assertTrue(in_array($response->status(), [302, 401]));
    }

    public function test_unauthorized_user_cannot_access_other_teacher_selfie(): void
    {
        Storage::disk('local')->put('attendance/geotag/selfie_a.jpg', 'binary-photo-data');

        $logA = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => '1000000001',
            'user_type' => 'teacher',
            'user_id' => $this->teacherA->id,
            'name' => $this->teacherA->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'raw_payload' => json_encode(['selfie_path' => 'attendance/geotag/selfie_a.jpg']),
        ]);

        // Guru B mencoba melihat selfie milik Guru A
        $response = $this->actingAs($this->teacherB)->get(route('attendance.geotag.selfie', $logA));
        $response->assertStatus(403);
    }

    public function test_authorized_user_and_superadmin_can_access_selfie(): void
    {
        Storage::disk('local')->put('attendance/geotag/selfie_a.jpg', 'binary-photo-data');

        $logA = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => '1000000001',
            'user_type' => 'teacher',
            'user_id' => $this->teacherA->id,
            'name' => $this->teacherA->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'raw_payload' => json_encode(['selfie_path' => 'attendance/geotag/selfie_a.jpg']),
        ]);

        // Pemilik selfie (Guru A) dapat mengakses
        $response = $this->actingAs($this->teacherA)->get(route('attendance.geotag.selfie', $logA));
        $response->assertStatus(200);

        // Super Admin dapat mengakses
        $response = $this->actingAs($this->superAdmin)->get(route('attendance.geotag.selfie', $logA));
        $response->assertStatus(200);

        // Kepala Sekolah dapat mengakses
        $response = $this->actingAs($this->principal)->get(route('attendance.geotag.selfie', $logA));
        $response->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_access_other_teacher_gtk_permit_document(): void
    {
        Storage::disk('local')->put('gtk_permits/med_cert.pdf', 'medical-certificate');

        $permitA = GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'sakit',
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Sakit',
            'document_path' => 'gtk_permits/med_cert.pdf',
        ]);

        // Guru B dilarang melihat dokumen medis Guru A
        $response = $this->actingAs($this->teacherB)->get(route('attendance.gtk-permits.document', $permitA));
        $response->assertStatus(403);

        // Guru A (pemilik) boleh melihat dokumennya
        $response = $this->actingAs($this->teacherA)->get(route('attendance.gtk-permits.document', $permitA));
        $response->assertStatus(200);

        // Kepala Sekolah boleh melihat untuk verifikasi izin
        $response = $this->actingAs($this->principal)->get(route('attendance.gtk-permits.document', $permitA));
        $response->assertStatus(200);
    }

    public function test_path_traversal_is_blocked_with_not_found(): void
    {
        $log = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => '1000000001',
            'user_type' => 'teacher',
            'user_id' => $this->teacherA->id,
            'name' => $this->teacherA->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            'raw_payload' => json_encode(['selfie_path' => '../../../../windows/system32/cmd.exe']),
        ]);

        $response = $this->actingAs($this->teacherA)->get(route('attendance.geotag.selfie', $log));
        $response->assertStatus(404);
    }

    public function test_dual_disk_backward_compatibility_fallback_to_public(): void
    {
        // Simulasikan file warisan lama yang hanya tersimpan di disk 'public'
        Storage::disk('public')->put('gtk_permits/legacy_doc.jpg', 'legacy-public-data');
        $this->assertFalse(Storage::disk('local')->exists('gtk_permits/legacy_doc.jpg'));

        $permit = GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'cuti',
            'status' => 'approved',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Cuti Tahunan',
            'document_path' => 'gtk_permits/legacy_doc.jpg',
        ]);

        // Tetap dapat diakses via route privat melalui fallback disk public
        $response = $this->actingAs($this->teacherA)->get(route('attendance.gtk-permits.document', $permit));
        $response->assertStatus(200);
    }

    public function test_new_gtk_permit_upload_is_stored_on_local_private_disk(): void
    {
        $file = UploadedFile::fake()->create('surat_dokter.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->teacherA)->post(route('attendance.gtk-permits.store'), [
            'user_id' => $this->teacherA->id,
            'permission_type' => 'sakit',
            'start_date' => now()->toDateString(),
            'reason' => 'Sakit tipes',
            'document' => $file,
        ]);

        $permit = GtkPermit::where('user_id', $this->teacherA->id)->latest()->first();
        $this->assertNotNull($permit);
        $this->assertNotNull($permit->document_path);

        // Pastikan tersimpan di disk privat 'local', BUKAN 'public'
        $this->assertTrue(Storage::disk('local')->exists($permit->document_path));
        $this->assertFalse(Storage::disk('public')->exists($permit->document_path));
    }

    public function test_supervision_rpp_and_photo_authorization(): void
    {
        Storage::disk('local')->put('modul_ajar/rpp_secret.pdf', 'secret-lesson-plan');
        Storage::disk('local')->put('dokumentasi_supervisi/photo.jpg', 'secret-photo');

        $supervision = Supervision::create([
            'teacher_id' => $this->teacherA->id,
            'supervisor_id' => $this->teacherB->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'subject' => 'Matematika',
            'class_name' => 'X-A',
            'schedule_date' => now()->toDateString(),
            'status' => 'terjadwal',
            'rpp_file_path' => 'modul_ajar/rpp_secret.pdf',
        ]);

        $photo = SupervisionPhoto::create([
            'supervision_id' => $supervision->id,
            'file_path' => 'dokumentasi_supervisi/photo.jpg',
            'caption' => 'Kegiatan Kelas',
            'category' => 'Pembelajaran',
            'taken_at' => now(),
        ]);

        // Guru yang disupervisi (Guru A) boleh mengunduh RPP dan melihat foto
        $response = $this->actingAs($this->teacherA)->get(route('supervisions.download-rpp', $supervision));
        $response->assertStatus(200);

        $response = $this->actingAs($this->teacherA)->get(route('supervisions.view-photo', $photo));
        $response->assertStatus(200);

        // Supervisor (Guru B) boleh mengunduh RPP dan melihat foto
        $response = $this->actingAs($this->teacherB)->get(route('supervisions.download-rpp', $supervision));
        $response->assertStatus(200);

        // Guru lain (user luar) diblokir 403
        $otherTeacher = User::create([
            'name' => 'Guru Luar',
            'username' => 'guruluar_test',
            'email' => 'luar@test.com',
            'password' => bcrypt('password'),
            'role_id' => Role::where('slug', 'guru')->first()->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherTeacher)->get(route('supervisions.download-rpp', $supervision));
        $response->assertStatus(403);

        $response = $this->actingAs($otherTeacher)->get(route('supervisions.view-photo', $photo));
        $response->assertStatus(403);
    }

    public function test_migration_command_moves_files_from_public_to_local_disk(): void
    {
        // Siapkan file di disk public
        $filename = 'gtk_permits/legacy_to_migrate.pdf';
        $content = 'content-to-migrate-sha256-verified';
        Storage::disk('public')->put($filename, $content);
        $this->assertFalse(Storage::disk('local')->exists($filename));

        GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'dinas_luar',
            'status' => 'approved',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Pelatihan',
            'document_path' => $filename,
        ]);

        // Jalankan perintah migrasi dengan flag --delete-source
        $exitCode = Artisan::call('files:migrate-sensitive', ['--delete-source' => true]);
        $this->assertEquals(0, $exitCode);

        // Verifikasi file sekarang berada di disk 'local' dan terhapus dari 'public'
        $this->assertTrue(Storage::disk('local')->exists($filename));
        $this->assertFalse(Storage::disk('public')->exists($filename));
        $this->assertEquals($content, Storage::disk('local')->get($filename));
    }

    public function test_migration_command_dry_run_does_not_modify_files_or_database(): void
    {
        $filename = 'gtk_permits/dry_run_doc.pdf';
        Storage::disk('public')->put($filename, 'content-dry-run');

        GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'izin',
            'status' => 'approved',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Keperluan Keluarga',
            'document_path' => $filename,
        ]);

        $exitCode = Artisan::call('files:migrate-sensitive', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        // Verifikasi: file di public TIDAK dihapus dan file di local BELUM dibuat
        $this->assertTrue(Storage::disk('public')->exists($filename));
        $this->assertFalse(Storage::disk('local')->exists($filename));
    }

    public function test_migration_conflicting_destination_file_is_detected_and_source_retained(): void
    {
        $filename = 'gtk_permits/conflict_doc.pdf';
        Storage::disk('public')->put($filename, 'original-public-content');
        Storage::disk('local')->put($filename, 'different-private-content');

        GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'cuti',
            'status' => 'approved',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Cuti',
            'document_path' => $filename,
        ]);

        // Jalankan migrasi dengan --delete-source
        $exitCode = Artisan::call('files:migrate-sensitive', ['--delete-source' => true]);
        $this->assertEquals(1, $exitCode); // Gagal karena ada konflik

        // Sumber di public TIDAK boleh dihapus saat ada konflik hash
        $this->assertTrue(Storage::disk('public')->exists($filename));
        // Tujuan di local tidak tertimpa
        $this->assertEquals('different-private-content', Storage::disk('local')->get($filename));
    }

    public function test_historic_selfie_with_only_selfie_url_can_be_accessed_via_private_route(): void
    {
        $selfieRelative = 'attendance/geotag/2026-10/old_selfie_sample.jpg';
        Storage::disk('local')->put($selfieRelative, 'binary-image-data-old');

        $log = AttendanceLog::create([
            'device_sn' => 'MOBILE-PWA',
            'pin' => '1000000001',
            'user_type' => 'teacher',
            'user_id' => $this->teacherA->id,
            'name' => $this->teacherA->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'geotag',
            // Format log historis: hanya memuat selfie_url, tanpa selfie_path
            'raw_payload' => json_encode([
                'latitude' => -7.95,
                'longitude' => 111.97,
                'selfie_url' => 'https://siap.test/storage/'.$selfieRelative,
            ]),
        ]);

        // Pemilik presensi (Guru A) harus tetap dapat mengakses foto melalui route privat
        $response = $this->actingAs($this->teacherA)->get(route('attendance.geotag.selfie', $log));
        $response->assertStatus(200);

        // Guru lain tetap diblokir
        $response = $this->actingAs($this->teacherB)->get(route('attendance.geotag.selfie', $log));
        $response->assertStatus(403);
    }

    public function test_svg_upload_is_strictly_rejected_on_homeroom_student_permits(): void
    {
        $gradeLevel = GradeLevel::first() ?? GradeLevel::create(['name' => '10', 'level' => 10]);
        $classroom = Classroom::create([
            'name' => 'X-RPL-1',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacherA->id,
        ]);

        $student = Student::create([
            'name' => 'Siswa Test',
            'nis' => '12345',
            'nisn' => '0012345678',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $svgFile = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($this->teacherA)->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'permit_category' => 'sakit',
            'permit_date' => now()->toDateString(),
            'reason' => 'Sakit perut',
            'attachment' => $svgFile,
        ]);

        // Harus gagal validasi karena SVG bukan format yang diizinkan
        $response->assertSessionHasErrors('attachment');
    }

    public function test_allowed_attachment_formats_are_accepted_on_homeroom_permits(): void
    {
        $gradeLevel = GradeLevel::first() ?? GradeLevel::create(['name' => '10', 'level' => 10]);
        $classroom = Classroom::create([
            'name' => 'X-RPL-2',
            'academic_year' => '2026/2027',
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacherA->id,
        ]);

        $student = Student::create([
            'name' => 'Siswa Test 2',
            'nis' => '12346',
            'nisn' => '0012345679',
            'gender' => 'P',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $pngFile = UploadedFile::fake()->image('surat_dokter.png', 600, 400);

        $response = $this->actingAs($this->teacherA)->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'permit_category' => 'sakit',
            'permit_date' => now()->toDateString(),
            'reason' => 'Sakit flu',
            'attachment' => $pngFile,
        ]);

        $response->assertRedirect(route('attendance.homeroom-permits.index'));
        $permit = PiketStudentPermit::where('student_id', $student->id)->latest()->first();
        $this->assertNotNull($permit);
        $this->assertNotNull($permit->attachment_path);
        // Terverifikasi tersimpan di disk privat
        $this->assertTrue(Storage::disk('local')->exists($permit->attachment_path));
    }

    public function test_new_timetable_upload_is_stored_on_private_disk_and_protected(): void
    {
        $pdfFile = UploadedFile::fake()->create('jadwal_baru.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($this->superAdmin)->post(route('kurikulum.timetables.store'), [
            'pdf_file' => $pdfFile,
            'title' => 'Jadwal Semester Genap',
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
        ]);

        $upload = TimetableUpload::latest()->first();
        $this->assertNotNull($upload);

        // Wajib tersimpan di disk local, bukan public
        $this->assertTrue(Storage::disk('local')->exists($upload->file_path));
        $this->assertFalse(Storage::disk('public')->exists($upload->file_path));

        // Guru biasa dilarang mengunduh arsip jadwal
        $response = $this->actingAs($this->teacherA)->get(route('kurikulum.timetables.download', $upload));
        $response->assertStatus(403);

        // Super Admin boleh mengunduh
        $response = $this->actingAs($this->superAdmin)->get(route('kurikulum.timetables.download', $upload));
        $response->assertStatus(200);
    }

    public function test_geisa_backup_download_authorization_and_protection(): void
    {
        $backupDir = storage_path('app/private/backups/geisa_04');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        file_put_contents($backupDir.'/device_info.json', json_encode(['device' => 'Geisa 04']));

        // 1. Guest ditolak (redirect ke login / 401)
        $response = $this->get(route('attendance.devices.backup-geisa.download', ['file' => 'info']));
        $this->assertTrue(in_array($response->status(), [302, 401]));

        // 2. Guru biasa ditolak 403 Forbidden
        $response = $this->actingAs($this->teacherA)->get(route('attendance.devices.backup-geisa.download', ['file' => 'info']));
        $response->assertStatus(403);

        // 3. Super Admin diizinkan mengunduh
        $response = $this->actingAs($this->superAdmin)->get(route('attendance.devices.backup-geisa.download', ['file' => 'info']));
        $response->assertStatus(200);

        // 4. Parameter file tidak valid menghasilkan 404
        $response = $this->actingAs($this->superAdmin)->get(route('attendance.devices.backup-geisa.download', ['file' => 'arbitrary_invalid']));
        $response->assertStatus(404);
    }

    public function test_missing_sensitive_file_returns_404_not_found(): void
    {
        // Record database valid, namun file fisik tidak ada di disk local maupun public
        $permit = GtkPermit::create([
            'user_id' => $this->teacherA->id,
            'permission_type' => 'sakit',
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Sakit',
            'document_path' => 'gtk_permits/non_existent_file.pdf',
        ]);

        $response = $this->actingAs($this->teacherA)->get(route('attendance.gtk-permits.document', $permit));
        $response->assertStatus(404);
    }
}
