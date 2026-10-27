<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\HomeroomImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeroomSecurityAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherA;

    protected User $teacherB;

    protected User $teacherOther;

    protected Role $superAdminRole;

    protected Role $guruRole;

    protected Role $waliKelasRole;

    protected AcademicYear $ay2025;

    protected AcademicYear $ay2026;

    protected Classroom $class1;

    protected Classroom $class2;

    protected Classroom $classPast;

    protected Student $student1;

    protected Student $student2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $this->guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $this->waliKelasRole = Role::firstOrCreate(['slug' => 'wali_kelas'], ['name' => 'Wali Kelas']);

        $permManageHomeroom = Permission::firstOrCreate(
            ['slug' => 'manage_homeroom'],
            ['name' => 'Kelola Rombel', 'group' => 'Akademik']
        );
        $this->waliKelasRole->permissions()->syncWithoutDetaching([$permManageHomeroom->id]);

        $this->adminUser = User::create([
            'name' => 'Admin User Test',
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->superAdminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $this->ay2025 = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['semester' => 'Ganjil', 'is_active' => false]
        );

        $this->ay2026 = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $setting = SchoolSetting::firstOrCreate(['id' => 1]);
        $setting->update([
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ay2026->id,
            'semester' => 'Ganjil',
        ]);

        $this->teacherA = User::create([
            'name' => 'Guru A (Wali Lama)',
            'username' => 'guru_a_test',
            'email' => 'guru_a@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $this->teacherB = User::create([
            'name' => 'Guru B (Wali Baru)',
            'username' => 'guru_b_test',
            'email' => 'guru_b@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $this->teacherOther = User::create([
            'name' => 'Guru Other (Wali Kelas Lain)',
            'username' => 'guru_other_test',
            'email' => 'guru_other@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['level' => 10], ['name' => 'Tingkat X', 'order_index' => 1]);

        // Class 1 (TP 2026/2027) awalnya diampu Teacher A
        $this->class1 = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ay2026->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->teacherA->id,
            'is_active' => true,
        ]);

        // Class 2 (TP 2026/2027) diampu Teacher A juga (multi-kelas)
        $this->class2 = Classroom::create([
            'name' => 'X TKR 2',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ay2026->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->teacherA->id,
            'is_active' => true,
        ]);

        // Class Past (TP 2025/2026) diampu Teacher A pada tahun lalu
        $this->classPast = Classroom::create([
            'name' => 'X TKR Past',
            'academic_year' => '2025/2026',
            'academic_year_id' => $this->ay2025->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->teacherA->id,
            'is_active' => true,
        ]);

        $this->student1 = Student::create([
            'name' => 'Siswa Kelas 1',
            'nis' => '1001',
            'nisn' => '001001',
            'gender' => 'L',
            'classroom_id' => $this->class1->id,
            'is_active' => true,
        ]);

        $this->student2 = Student::create([
            'name' => 'Siswa Kelas 2',
            'nis' => '1002',
            'nisn' => '001002',
            'gender' => 'P',
            'classroom_id' => $this->class2->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Guru yang tercatat sebagai wali kelas pada rombel yang diperiksa memperoleh akses yang semestinya.
     */
    public function test_legitimate_homeroom_teacher_has_access_to_classroom_and_students(): void
    {
        // Teacher A adalah wali kelas yang sah untuk Class 1
        $this->assertTrue($this->teacherA->isHomeroomTeacher($this->class1));
        $this->assertTrue($this->teacherA->isHomeroomTeacher());

        // Dapat mengakses detail siswa Class 1
        $response = $this->actingAs($this->teacherA)->getJson(route('students.show', $this->student1));
        $response->assertStatus(200);
        $response->assertJsonPath('student.name', 'SISWA KELAS 1');

        // Dapat mengakses halaman izin siswa Class 1
        $resPermit = $this->actingAs($this->teacherA)->get(route('attendance.homeroom-permits.index', [
            'academic_year' => '2026/2027',
            'classroom_id' => $this->class1->id,
        ]));
        $resPermit->assertStatus(200);
    }

    /**
     * 2. Setelah homeroom_teacher_id diganti, guru lama tidak dapat lagi menggunakan contextual role lama untuk memperoleh akses ke rombel tersebut.
     */
    public function test_old_homeroom_teacher_cannot_access_replaced_classroom_via_stale_contextual_role(): void
    {
        // Pasang contextual role wali_kelas untuk Teacher A pada Class 1
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Ganti wali kelas Class 1 menjadi Teacher B di tabel classrooms
        $this->class1->update(['homeroom_teacher_id' => $this->teacherB->id]);

        // Pastikan record contextual role lama Teacher A masih ada di tabel (mensimulasikan role yang belum dibersihkan)
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $this->class1->id,
            'status' => true,
        ]);

        // Verifikasi: Teacher A TIDAK BOLEH lagi dianggap wali kelas untuk Class 1
        $this->assertFalse($this->teacherA->fresh()->isHomeroomTeacher($this->class1));

        // Verifikasi: Teacher B yang baru menjadi wali kelas yang sah untuk Class 1
        $this->assertTrue($this->teacherB->fresh()->isHomeroomTeacher($this->class1));

        // Teacher A ditolak saat mengakses data siswa Class 1
        $resA = $this->actingAs($this->teacherA)->getJson(route('students.show', $this->student1));
        $resA->assertStatus(403);

        // Teacher B diizinkan saat mengakses data siswa Class 1
        $resB = $this->actingAs($this->teacherB)->getJson(route('students.show', $this->student1));
        $resB->assertStatus(200);
    }

    /**
     * 3. Guru yang masih menjadi wali kelas pada rombel lain tetap memperoleh akses ke rombel tersebut.
     */
    public function test_teacher_retaining_another_homeroom_class_can_still_access_that_retained_class(): void
    {
        // Class 1 diganti ke Teacher B
        $this->class1->update(['homeroom_teacher_id' => $this->teacherB->id]);

        // Teacher A masih menjadi wali kelas Class 2
        $this->assertTrue($this->teacherA->fresh()->isHomeroomTeacher($this->class2));
        $this->assertTrue($this->teacherA->fresh()->isHomeroomTeacher()); // Masih punya rombel aktif

        // Teacher A dapat mengakses data siswa di Class 2
        $response = $this->actingAs($this->teacherA)->getJson(route('students.show', $this->student2));
        $response->assertStatus(200);
        $response->assertJsonPath('student.name', 'SISWA KELAS 2');

        // Tetapi Teacher A ditolak jika mencoba mengakses siswa di Class 1
        $resClass1 = $this->actingAs($this->teacherA)->getJson(route('students.show', $this->student1));
        $resClass1->assertStatus(403);
    }

    /**
     * 4. Guru lama tidak dapat menggunakan jalur tampilan maupun ekspor data siswa untuk melewati otorisasi.
     */
    public function test_old_homeroom_teacher_cannot_bypass_student_export_authorization(): void
    {
        // Pasang contextual role kedaluwarsa pada Teacher A untuk Class 1
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Ganti Class 1 ke Teacher B dan lepaskan Class 2 juga dari Teacher A
        $this->class1->update(['homeroom_teacher_id' => $this->teacherB->id]);
        $this->class2->update(['homeroom_teacher_id' => $this->teacherOther->id]);

        // Teacher A sekarang sudah tidak memegang rombel aktif apa pun di 2026/2027
        // Jalur ekspor harus menolak Teacher A (403 Forbidden)
        $resExport = $this->actingAs($this->teacherA)->get(route('students.export', [
            'academic_year' => '2026/2027',
            'classroom_id' => $this->class1->id,
        ]));
        $resExport->assertStatus(403);

        // Jika Teacher A mencoba mengekspor secara umum tanpa parameter classroom_id, harus tetap 403
        $resExportGeneral = $this->actingAs($this->teacherA)->get(route('students.export', [
            'academic_year' => '2026/2027',
        ]));
        $resExportGeneral->assertStatus(403);

        // Sebaliknya, Teacher B yang sah dapat mengekspor Class 1
        $resExportB = $this->actingAs($this->teacherB)->get(route('students.export', [
            'academic_year' => '2026/2027',
            'classroom_id' => $this->class1->id,
        ]));
        $resExportB->assertStatus(200);
    }

    /**
     * 5. Rekap presensi hanya memuat rombel pada tahun ajaran yang sesuai.
     */
    public function test_homeroom_attendance_recap_only_loads_classrooms_for_the_requested_academic_year(): void
    {
        // Teacher A mengampu Class 1 di 2026/2027 dan Class Past di 2025/2026
        // Saat melihat TP 2026/2027, hanya Class 1 dan Class 2 yang boleh muncul di daftar rombel Teacher A
        $response = $this->actingAs($this->teacherA)->get(route('attendance.homeroom-recap.index', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $myClassrooms = $response->viewData('myClassrooms');
        $this->assertNotNull($myClassrooms);

        // Rombel 2026/2027 harus ada
        $this->assertTrue($myClassrooms->contains('id', $this->class1->id));
        $this->assertTrue($myClassrooms->contains('id', $this->class2->id));

        // Rombel 2025/2026 (tahun lalu) TIDAK BOLEH muncul pada rekap 2026/2027
        $this->assertFalse($myClassrooms->contains('id', $this->classPast->id));

        // Jika Teacher A mencoba meminta classroom_id tahun lalu secara paksa pada sesi 2026/2027, harus ditolak 403
        $resPastForbidden = $this->actingAs($this->teacherA)->get(route('attendance.homeroom-recap.index', [
            'academic_year' => '2026/2027',
            'classroom_id' => $this->classPast->id,
        ]));
        $resPastForbidden->assertStatus(403);

        // Jika Teacher A beralih melihat tahun ajaran 2025/2026 secara sah:
        $res2025 = $this->actingAs($this->teacherA)->get(route('attendance.homeroom-recap.index', [
            'academic_year' => '2025/2026',
        ]));
        $res2025->assertStatus(200);
        $classrooms2025 = $res2025->viewData('myClassrooms');
        $this->assertTrue($classrooms2025->contains('id', $this->classPast->id));
        $this->assertFalse($classrooms2025->contains('id', $this->class1->id));
    }

    /**
     * 6. Fitur izin siswa tetap bekerja untuk wali kelas yang sah.
     */
    public function test_homeroom_permits_work_for_legitimate_homeroom_teacher(): void
    {
        // Teacher A dapat membuka formulir catat izin siswa untuk rombel binaannya
        $resForm = $this->actingAs($this->teacherA)->get(route('attendance.homeroom-permits.create', [
            'classroom_id' => $this->class1->id,
        ]));
        $resForm->assertStatus(200);

        // Teacher A dapat menyimpan izin siswa
        $resStore = $this->actingAs($this->teacherA)->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $this->class1->id,
            'student_id' => $this->student1->id,
            'permit_category' => 'izin',
            'permit_date' => now()->toDateString(),
            'reason' => 'Ada keperluan keluarga',
        ]);
        $resStore->assertRedirect();
        $this->assertDatabaseHas('piket_student_permits', [
            'classroom_id' => $this->class1->id,
            'student_id' => $this->student1->id,
            'permit_category' => 'izin',
            'reason' => 'Ada keperluan keluarga',
        ]);

        // Guru lain (Teacher B) ditolak saat mencoba mencatat izin siswa untuk Class 1 (karena saat ini masih diampu Teacher A)
        $resStoreInvalid = $this->actingAs($this->teacherB)->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $this->class1->id,
            'student_id' => $this->student1->id,
            'permit_category' => 'izin',
            'permit_date' => now()->toDateString(),
            'reason' => 'Izin tanpa hak',
        ]);
        $resStoreInvalid->assertStatus(403);
    }

    /**
     * 7. Penugasan wali kelas via TeacherAssignmentController::updateHomeroom mensinkronkan user_role_assignments dan hak akses.
     */
    public function test_update_homeroom_synchronizes_user_role_assignments_and_grants_permission(): void
    {
        // Pasang penugasan awal Teacher A di user_role_assignments
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $this->assertTrue($this->teacherA->fresh()->hasPermission('manage_homeroom', $this->class1));
        $this->assertFalse($this->teacherB->fresh()->hasPermission('manage_homeroom', $this->class1));

        // Admin menugaskan Teacher B sebagai wali kelas baru untuk Class 1
        $response = $this->actingAs($this->adminUser)->post(route('teacher-assignments.update-homeroom', $this->class1), [
            'homeroom_teacher_id' => $this->teacherB->id,
        ]);
        $response->assertRedirect();

        // Single source of truth terupdate
        $this->assertEquals($this->teacherB->id, $this->class1->fresh()->homeroom_teacher_id);

        // Record contextual role Teacher B tercipta dan aktif
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherB->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'status' => true,
        ]);

        // Record contextual role Teacher A dinonaktifkan
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'status' => false,
        ]);

        // Hak akses kontekstual: Teacher B dapat 'manage_homeroom', Teacher A tidak lagi
        // Hak akses kontekstual: Teacher B dapat 'manage_homeroom', Teacher A tidak lagi
        $this->assertTrue($this->teacherB->fresh()->hasPermission('manage_homeroom', $this->class1));
        $this->assertFalse($this->teacherA->fresh()->hasPermission('manage_homeroom', $this->class1));
    }

    /**
     * 8. Pergantian wali kelas pada satu rombel tidak mencabut peran kontekstual pada rombel binaan lainnya.
     */
    public function test_replacing_homeroom_teacher_preserves_other_class_assignments(): void
    {
        // Teacher A ditugaskan pada Class 1 dan Class 2
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class2->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Ganti Class 1 ke Teacher B
        $this->actingAs($this->adminUser)->post(route('teacher-assignments.update-homeroom', $this->class1), [
            'homeroom_teacher_id' => $this->teacherB->id,
        ]);

        // Class 1 untuk Teacher A dinonaktifkan
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $this->class1->id,
            'status' => false,
        ]);

        // Class 2 untuk Teacher A HARUS TETAP AKTIF
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $this->class2->id,
            'status' => true,
        ]);

        $this->assertTrue($this->teacherA->fresh()->hasPermission('manage_homeroom', $this->class2));
        $this->assertFalse($this->teacherA->fresh()->hasPermission('manage_homeroom', $this->class1));
    }

    /**
     * 9. Mengosongkan wali kelas menonaktifkan contextual role pada rombel tersebut.
     */
    public function test_clearing_homeroom_teacher_deactivates_contextual_role(): void
    {
        UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->class1->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Admin mengosongkan wali kelas
        $response = $this->actingAs($this->adminUser)->post(route('teacher-assignments.update-homeroom', $this->class1), [
            'homeroom_teacher_id' => '',
        ]);
        $response->assertRedirect();

        $this->assertNull($this->class1->fresh()->homeroom_teacher_id);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $this->class1->id,
            'status' => false,
        ]);

        $this->assertFalse($this->teacherA->fresh()->hasPermission('manage_homeroom', $this->class1));
    }

    /**
     * 10. Pembuatan dan pembaruan kelas via ClassroomController mensinkronkan contextual role.
     */
    public function test_classroom_controller_store_and_update_synchronizes_contextual_role(): void
    {
        $grade = GradeLevel::first();

        // 1. Store rombel baru dengan wali kelas Teacher B
        $resStore = $this->actingAs($this->adminUser)->post(route('classrooms.store'), [
            'name' => 'XI TKR 1',
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->teacherB->id,
        ]);
        $resStore->assertRedirect();

        $newClass = Classroom::where('name', 'XI TKR 1')->firstOrFail();
        $this->assertEquals($this->teacherB->id, $newClass->homeroom_teacher_id);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherB->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $newClass->id,
            'status' => true,
        ]);

        // 2. Update rombel mengganti wali kelas ke Teacher Other
        $resUpdate = $this->actingAs($this->adminUser)->put(route('classrooms.update', $newClass), [
            'name' => 'XI TKR 1 (Updated)',
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->teacherOther->id,
        ]);
        $resUpdate->assertRedirect();

        $this->assertEquals($this->teacherOther->id, $newClass->fresh()->homeroom_teacher_id);

        // Teacher B dinonaktifkan
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherB->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $newClass->id,
            'status' => false,
        ]);

        // Teacher Other diaktifkan
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherOther->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $newClass->id,
            'status' => true,
        ]);

        // 3. Update rombel mengosongkan wali kelas
        $resClear = $this->actingAs($this->adminUser)->put(route('classrooms.update', $newClass), [
            'name' => 'XI TKR 1 (Cleared)',
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => '',
        ]);
        $resClear->assertRedirect();

        $this->assertNull($newClass->fresh()->homeroom_teacher_id);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherOther->id,
            'role_id' => $this->waliKelasRole->id,
            'context_id' => $newClass->id,
            'status' => false,
        ]);
    }

    /**
     * 11. Penghapusan kelas via ClassroomController membersihkan contextual role terkait.
     */
    public function test_classroom_controller_destroy_cleans_up_classroom_roles(): void
    {
        $grade = GradeLevel::first();

        $classToDelete = Classroom::create([
            'name' => 'XII TKR Del',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ay2026->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->teacherB->id,
            'is_active' => true,
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->teacherB->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $classToDelete->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'context_type' => Classroom::class,
            'context_id' => $classToDelete->id,
        ]);

        $resDelete = $this->actingAs($this->adminUser)->delete(route('classrooms.destroy', $classToDelete));
        $resDelete->assertRedirect();

        // Rombel terhapus dan role kontekstual terhapus (tidak meninggalkan orphan)
        $this->assertDatabaseMissing('classrooms', ['id' => $classToDelete->id]);
        $this->assertDatabaseMissing('user_role_assignments', [
            'context_type' => Classroom::class,
            'context_id' => $classToDelete->id,
        ]);
    }

    /**
     * 12. Import wali kelas via HomeroomImportService mensinkronkan user_role_assignments.
     */
    public function test_homeroom_import_service_synchronizes_contextual_roles(): void
    {
        // Siapkan file CSV import sementara
        $csvContent = "Nama Rombel,Nama Guru,NIGS\n";
        $csvContent .= "{$this->class1->name},{$this->teacherB->name},\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'hr_import_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        try {
            $importer = app(HomeroomImportService::class);
            $result = $importer->import($tmpFile, 'csv', '2026/2027');

            $this->assertTrue($result['success']);
            $this->assertEquals(1, $result['imported_count']);

            // Class 1 terupdate ke Teacher B
            $this->assertEquals($this->teacherB->id, $this->class1->fresh()->homeroom_teacher_id);

            // Contextual role terupdate ke Teacher B
            $this->assertDatabaseHas('user_role_assignments', [
                'user_id' => $this->teacherB->id,
                'role_id' => $this->waliKelasRole->id,
                'context_type' => Classroom::class,
                'context_id' => $this->class1->id,
                'status' => true,
            ]);

            // Teacher B memiliki permission 'manage_homeroom' untuk Class 1
            // Hak akses kontekstual: Teacher B dapat 'manage_homeroom', Teacher A tidak lagi
            $this->assertTrue($this->teacherB->fresh()->hasPermission('manage_homeroom', $this->class1));
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
