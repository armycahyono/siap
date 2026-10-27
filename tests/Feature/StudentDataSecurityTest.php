<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDataSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $homeroomUser;

    protected Classroom $classroom;

    protected Classroom $otherClassroom;

    protected Student $testStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $gradeLevel = GradeLevel::first() ?? GradeLevel::create(['name' => '10', 'level' => 10]);

        $this->adminUser = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'super_admin')->first()?->id,
                'is_active' => true,
            ]);

        $this->homeroomUser = User::factory()->create([
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);

        $this->teacherUser = User::factory()->create([
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);

        $this->classroom = Classroom::create([
            'name' => 'X TKRO 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->homeroomUser->id,
            'is_active' => true,
        ]);

        $this->otherClassroom = Classroom::create([
            'name' => 'X TKJ 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => null,
            'is_active' => true,
        ]);

        $this->testStudent = Student::create([
            'name' => 'Aditya Pratama Synthetic',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'nisn' => '0098765432',
            'nis' => '24099',
            'nik' => '3506123456789999',
            'family_card_no' => '3506123456788888',
            'birth_certificate_no' => 'AKTA-99887766',
            'birth_place' => 'Kediri',
            'birth_date' => '2008-05-12',
            'religion' => 'Islam',
            'address' => 'Jl. Mawar No. 10 Kras',
            'father_name' => 'Bambang Synthetic',
            'father_nik' => '3506123456787777',
            'father_job' => 'Wiraswasta',
            'father_phone' => '081234567801',
            'father_income' => 'Rp 15.000.000',
            'mother_name' => 'Endang Synthetic',
            'mother_nik' => '3506123456786666',
            'mother_job' => 'PNS',
            'mother_phone' => '081234567802',
            'mother_income' => 'Rp 8.000.000',
            'phone_student' => '085712345601',
            'phone_parent' => '081234567801',
            'bank_name' => 'Bank Rahasia Negara',
            'bank_account_no' => 'REK-BANK-55443322',
            'bank_account_name' => 'Aditya Pratama',
            'latitude' => -7.912345,
            'longitude' => 112.987654,
            'special_needs' => 'Kebutuhan Khusus Medis',
            'pip_reason' => 'Alasan Sangat Khusus PIP',
            'kks_number' => 'KKS-998811',
            'kps_number' => 'KPS-776622',
            'kip_number' => 'KIP-554433',
            'dapodik_payload' => ['raw_secret_data' => 'secret_payload_value_123'],
            'is_active' => true,
            'status' => 'aktif',
        ]);
    }

    public function test_authorized_user_can_access_student_list_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.index'));
        $response->assertStatus(200);
        $response->assertSee('Data Induk Siswa & Alumni');
    }

    public function test_student_list_html_does_not_leak_sensitive_pii_in_dom(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.index'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Data pribadi tidak boleh muncul di HTML hanya karena serialisasi model
        $this->assertStringNotContainsString('3506123456789999', $content, 'NIK Siswa bocor di DOM HTML!');
        $this->assertStringNotContainsString('3506123456788888', $content, 'Nomor KK bocor di DOM HTML!');
        $this->assertStringNotContainsString('AKTA-99887766', $content, 'Nomor Akta Lahir bocor di DOM HTML!');
        $this->assertStringNotContainsString('3506123456787777', $content, 'NIK Ayah bocor di DOM HTML!');
        $this->assertStringNotContainsString('3506123456786666', $content, 'NIK Ibu bocor di DOM HTML!');
        $this->assertStringNotContainsString('REK-BANK-55443322', $content, 'Nomor Rekening Bank bocor di DOM HTML!');
        $this->assertStringNotContainsString('Bank Rahasia Negara', $content, 'Nama Bank bocor di DOM HTML!');
        $this->assertStringNotContainsString('-7.912345', $content, 'Koordinat Latitude bocor di DOM HTML!');
        $this->assertStringNotContainsString('112.987654', $content, 'Koordinat Longitude bocor di DOM HTML!');
        $this->assertStringNotContainsString('secret_payload_value_123', $content, 'Dapodik raw payload bocor di DOM HTML!');
        $this->assertStringNotContainsString('Kebutuhan Khusus Medis', $content, 'Catatan kebutuhan khusus bocor di DOM HTML!');
        $this->assertStringNotContainsString('Alasan Sangat Khusus PIP', $content, 'Alasan PIP bocor di DOM HTML!');
        $this->assertStringNotContainsString('Rp 15.000.000', $content, 'Penghasilan orang tua bocor di DOM HTML!');

        // 2. Data non-sensitif yang diperlukan antarmuka tetap tersedia
        $this->assertStringContainsString('ADITYA PRATAMA SYNTHETIC', $content);
        $this->assertStringContainsString('0098765432', $content);
        $this->assertStringContainsString('data-student=', $content);
    }

    public function test_student_model_hidden_attributes_prevent_accidental_json_leakage(): void
    {
        $json = json_encode($this->testStudent);

        $this->assertStringNotContainsString('3506123456789999', $json);
        $this->assertStringNotContainsString('3506123456788888', $json);
        $this->assertStringNotContainsString('REK-BANK-55443322', $json);
        $this->assertStringNotContainsString('-7.912345', $json);
        $this->assertStringNotContainsString('secret_payload_value_123', $json);
    }

    public function test_student_to_safe_ui_array_contains_only_safe_fields(): void
    {
        $safeArray = $this->testStudent->toSafeUiArray();

        $this->assertEquals($this->testStudent->id, $safeArray['id']);
        $this->assertEquals('ADITYA PRATAMA SYNTHETIC', $safeArray['name']);
        $this->assertEquals('0098765432', $safeArray['nisn']);
        $this->assertEquals('24099', $safeArray['nis']);
        $this->assertEquals('L', $safeArray['gender']);

        // Pastikan atribut sensitif tidak ada dalam key array
        $this->assertArrayNotHasKey('nik', $safeArray);
        $this->assertArrayNotHasKey('family_card_no', $safeArray);
        $this->assertArrayNotHasKey('birth_certificate_no', $safeArray);
        $this->assertArrayNotHasKey('father_nik', $safeArray);
        $this->assertArrayNotHasKey('mother_nik', $safeArray);
        $this->assertArrayNotHasKey('guardian_nik', $safeArray);
        $this->assertArrayNotHasKey('father_income', $safeArray);
        $this->assertArrayNotHasKey('mother_income', $safeArray);
        $this->assertArrayNotHasKey('guardian_income', $safeArray);
        $this->assertArrayNotHasKey('bank_name', $safeArray);
        $this->assertArrayNotHasKey('bank_account_no', $safeArray);
        $this->assertArrayNotHasKey('bank_account_name', $safeArray);
        $this->assertArrayNotHasKey('latitude', $safeArray);
        $this->assertArrayNotHasKey('longitude', $safeArray);
        $this->assertArrayNotHasKey('dapodik_payload', $safeArray);
        $this->assertArrayNotHasKey('special_needs', $safeArray);
        $this->assertArrayNotHasKey('pip_reason', $safeArray);
    }

    public function test_student_show_endpoint_rejects_unauthorized_user_with_403(): void
    {
        // Guru biasa yang bukan wali kelas siswa dan tidak memiliki manage_master_data
        $response = $this->actingAs($this->teacherUser)->get(route('students.show', $this->testStudent));

        $response->assertStatus(403);
    }

    public function test_homeroom_teacher_cannot_access_student_outside_assigned_classroom(): void
    {
        // Siswa di kelas lain (X TKJ 1) yang bukan perwalian homeroomUser
        $otherStudent = Student::create([
            'name' => 'Siswa Kelas Lain',
            'classroom_id' => $this->otherClassroom->id,
            'gender' => 'L',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->homeroomUser)->get(route('students.show', $otherStudent));

        $response->assertStatus(403);
    }

    public function test_homeroom_teacher_can_access_student_in_their_assigned_classroom_with_general_biodata_only(): void
    {
        // Wali kelas dari rombel siswa yang bersangkutan
        $response = $this->actingAs($this->homeroomUser)->get(route('students.show', $this->testStudent));

        $response->assertStatus(200);

        // Biodata umum dan kontak tersedia
        $response->assertJsonFragment([
            'name' => 'ADITYA PRATAMA SYNTHETIC',
            'nisn' => '0098765432',
            'phone_parent' => '081234567801',
        ]);

        // NIK, No KK, Rekening Bank, Koordinat GPS, dan Penghasilan TIDAK dibocorkan ke wali kelas
        $studentData = $response->json('student');
        $this->assertArrayNotHasKey('nik', $studentData);
        $this->assertArrayNotHasKey('family_card_no', $studentData);
        $this->assertArrayNotHasKey('bank_account_no', $studentData);
        $this->assertArrayNotHasKey('latitude', $studentData);
        $this->assertArrayNotHasKey('longitude', $studentData);
        $this->assertArrayNotHasKey('father_income', $studentData);
        $this->assertArrayNotHasKey('dapodik_payload', $studentData);
    }

    public function test_student_detail_never_includes_incidents_or_permits(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.show', $this->testStudent));

        $response->assertStatus(200);
        $studentData = $response->json('student');

        $this->assertArrayNotHasKey('incidents', $studentData);
        $this->assertArrayNotHasKey('permits', $studentData);
        $this->assertNull($response->json('incidents'));
        $this->assertNull($response->json('permits'));
    }

    public function test_student_show_endpoint_reveals_sensitive_data_for_authorized_super_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.show', $this->testStudent));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'nik' => '3506123456789999',
            'family_card_no' => '3506123456788888',
            'bank_account_no' => 'REK-BANK-55443322',
        ]);
    }

    public function test_edit_data_endpoint_is_forbidden_for_users_without_manage_master_data(): void
    {
        // Guru biasa
        $teacherResp = $this->actingAs($this->teacherUser)->get(route('students.edit-data', $this->testStudent));
        $teacherResp->assertStatus(403);

        // Wali kelas (hanya berhak melihat, tidak berhak mengedit data master siswa)
        $homeroomResp = $this->actingAs($this->homeroomUser)->get(route('students.edit-data', $this->testStudent));
        $homeroomResp->assertStatus(403);
    }

    public function test_edit_data_endpoint_returns_only_form_fields_for_authorized_manager(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.edit-data', $this->testStudent));

        $response->assertStatus(200);
        $editStudent = $response->json('student');

        // Field formulir edit tersedia
        $this->assertEquals($this->testStudent->id, $editStudent['id']);
        $this->assertEquals('ADITYA PRATAMA SYNTHETIC', $editStudent['name']);
        $this->assertEquals('3506123456789999', $editStudent['nik']);
        $this->assertEquals('0098765432', $editStudent['nisn']);

        // Field non-form yang sensitif TIDAK dikembalikan
        $this->assertArrayNotHasKey('family_card_no', $editStudent);
        $this->assertArrayNotHasKey('bank_account_no', $editStudent);
        $this->assertArrayNotHasKey('latitude', $editStudent);
        $this->assertArrayNotHasKey('longitude', $editStudent);
        $this->assertArrayNotHasKey('father_income', $editStudent);
        $this->assertArrayNotHasKey('dapodik_payload', $editStudent);
        $this->assertArrayNotHasKey('incidents', $editStudent);
        $this->assertArrayNotHasKey('permits', $editStudent);
    }

    public function test_homeroom_teacher_cannot_access_student_if_assigned_classroom_is_inactive(): void
    {
        // Nonaktifkan rombel perwalian
        $this->classroom->update(['is_active' => false]);
        $this->testStudent->refresh();

        // Wali kelas tidak boleh dapat mengakses detail siswa pada kelas nonaktif
        $response = $this->actingAs($this->homeroomUser)->get(route('students.show', $this->testStudent));
        $response->assertStatus(403);

        // Kembalikan ke aktif untuk integritas state pengujian
        $this->classroom->update(['is_active' => true]);
    }

    public function test_authorized_manager_can_access_student_detail_even_if_classroom_is_inactive(): void
    {
        // Nonaktifkan rombel perwalian
        $this->classroom->update(['is_active' => false]);
        $this->testStudent->refresh();

        // Pengelola data master (misal super admin) tetap berhak mengakses detail siswa
        $response = $this->actingAs($this->adminUser)->get(route('students.show', $this->testStudent));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'ADITYA PRATAMA SYNTHETIC',
        ]);

        // Kembalikan ke aktif
        $this->classroom->update(['is_active' => true]);
    }

    public function test_is_homeroom_teacher_helper_respects_classroom_active_status_globally(): void
    {
        // 1. Saat kelas aktif, helper mengembalikan true
        $this->classroom->update(['is_active' => true]);
        $this->assertTrue($this->homeroomUser->isHomeroomTeacher($this->classroom));
        $this->assertTrue($this->homeroomUser->isHomeroomTeacher());

        // 2. Saat kelas dinonaktifkan, helper mengembalikan false
        $this->classroom->update(['is_active' => false]);
        $this->assertFalse($this->homeroomUser->isHomeroomTeacher($this->classroom));
        $this->assertFalse($this->homeroomUser->isHomeroomTeacher());

        // 3. Guru lain tetap mengembalikan false
        $this->assertFalse($this->teacherUser->isHomeroomTeacher($this->classroom));
        $this->assertFalse($this->teacherUser->isHomeroomTeacher());

        // Kembalikan ke aktif
        $this->classroom->update(['is_active' => true]);
    }

    public function test_guest_cannot_access_student_export(): void
    {
        $response = $this->get(route('students.export'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_teacher_receives_403_on_student_export(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('students.export'));
        $response->assertStatus(403);
    }

    public function test_staff_without_export_permission_receives_403(): void
    {
        $staffUser = User::factory()->create([
            'role_id' => Role::where('slug', 'staf')->first()?->id ?? $this->teacherUser->role_id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staffUser)->get(route('students.export'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_export_all_students_with_nik(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();
        $this->assertStringContainsString('NIK', $content);
        $this->assertStringContainsString($this->testStudent->nik, $content);
        $this->assertStringContainsString('ADITYA PRATAMA SYNTHETIC', $content);
    }

    public function test_manager_with_manage_master_data_permission_can_export_students(): void
    {
        $managerUser = User::factory()->create([
            'role_id' => Role::where('slug', 'waka_kurikulum')->first()?->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($managerUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();
        $this->assertStringContainsString('NIK', $content);
        $this->assertStringContainsString('ADITYA PRATAMA SYNTHETIC', $content);
    }

    public function test_active_homeroom_teacher_can_export_assigned_classroom(): void
    {
        $response = $this->actingAs($this->homeroomUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();
        $this->assertStringContainsString('ADITYA PRATAMA SYNTHETIC', $content);
    }

    public function test_homeroom_teacher_cannot_export_other_classroom_via_parameter_tampering(): void
    {
        $response = $this->actingAs($this->homeroomUser)->get(route('students.export', [
            'classroom_id' => $this->otherClassroom->id,
        ]));
        $response->assertStatus(403);
    }

    public function test_homeroom_teacher_without_active_classroom_receives_403(): void
    {
        $this->classroom->update(['is_active' => false]);

        $response = $this->actingAs($this->homeroomUser)->get(route('students.export'));
        $response->assertStatus(403);

        $this->classroom->update(['is_active' => true]);
    }

    public function test_homeroom_teacher_cannot_export_alumni_or_mutasi(): void
    {
        $alumniResponse = $this->actingAs($this->homeroomUser)->get(route('students.export', ['tab' => 'alumni']));
        $alumniResponse->assertStatus(403);

        $mutasiResponse = $this->actingAs($this->homeroomUser)->get(route('students.export', ['tab' => 'mutasi']));
        $mutasiResponse->assertStatus(403);
    }

    public function test_invalid_academic_year_parameter_is_rejected(): void
    {
        // 1. Tahun ajaran yang tidak terdaftar di sistem harus 404
        $invalidAyResponse = $this->actingAs($this->adminUser)->get(route('students.export', [
            'academic_year' => '1990/1991_MALICIOUS',
        ]));
        $invalidAyResponse->assertStatus(404);

        // 2. Tahun ajaran sah tapi wali kelas tidak memiliki rombel aktif di tahun tersebut harus 403
        $otherAy = AcademicYear::firstOrCreate(['name' => '2024/2025'], ['semester' => 'Ganjil', 'is_active' => false]);
        $waliResponse = $this->actingAs($this->homeroomUser)->get(route('students.export', [
            'academic_year' => '2024/2025',
        ]));
        $waliResponse->assertStatus(403);
    }

    public function test_csv_for_homeroom_teacher_does_not_contain_nik(): void
    {
        $response = $this->actingAs($this->homeroomUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();

        // Header CSV wali kelas tidak boleh memuat kolom NIK
        $this->assertStringNotContainsString(',NIK,', $content);
        // Nilai NIK siswa tidak boleh ada di isi baris CSV
        $this->assertStringNotContainsString('3506123456789999', $content);
    }

    public function test_csv_for_homeroom_teacher_does_not_contain_restricted_sensitive_data(): void
    {
        $response = $this->actingAs($this->homeroomUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();

        $this->assertStringNotContainsString('3506123456788888', $content); // No KK
        $this->assertStringNotContainsString('AKTA-99887766', $content); // No Akta
        $this->assertStringNotContainsString('REK-BANK-55443322', $content); // Rekening Bank
        $this->assertStringNotContainsString('-7.912345', $content); // GPS Latitude
        $this->assertStringNotContainsString('112.987654', $content); // GPS Longitude
        $this->assertStringNotContainsString('secret_payload_value_123', $content); // Dapodik payload
        $this->assertStringNotContainsString('3506123456787777', $content); // NIK Ayah
        $this->assertStringNotContainsString('3506123456786666', $content); // NIK Ibu
    }

    public function test_csv_for_authorized_manager_retains_required_administrative_columns(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('students.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();

        $this->assertStringContainsString('NIK', $content);
        $this->assertStringContainsString('3506123456789999', $content);
        $this->assertStringContainsString('0098765432', $content);
        $this->assertStringContainsString('Bambang Synthetic', $content);
        $this->assertStringContainsString('Endang Synthetic', $content);
    }

    public function test_ui_export_button_visibility_matches_user_authorization(): void
    {
        // 1. Super Admin melihat tombol ekspor
        $adminView = $this->actingAs($this->adminUser)->get(route('students.index'));
        $adminView->assertStatus(200);
        $adminView->assertSee('Ekspor CSV');

        // 2. Wali kelas aktif melihat tombol ekspor
        $waliView = $this->actingAs($this->homeroomUser)->get(route('students.index'));
        $waliView->assertStatus(200);
        $waliView->assertSee('Ekspor CSV');

        // 3. Guru biasa tanpa rombel TIDAK melihat tombol ekspor
        $teacherView = $this->actingAs($this->teacherUser)->get(route('students.index'));
        $teacherView->assertStatus(200);
        $teacherView->assertDontSee('Ekspor CSV');
    }
}
