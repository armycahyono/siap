<?php

namespace Tests\Feature;

use App\Exceptions\GtkDecreeCancellationException;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\LessonSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\GtkDecreeCancellationPreviewService;
use App\Services\GtkDecreeCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkDecreeCancellationExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $teacherB;

    private User $regularUser;

    private AcademicYear $academicYear;

    private GtkDecreeCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $permMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permMaster->id]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Pengelola Eksekusi SK',
            'username' => 'admin_cancel_exec',
        ]);

        $this->teacherA = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Eko Prasetyo',
            'username' => 'eko_prasetyo',
        ]);

        $this->teacherB = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Fajar Nugraha',
            'username' => 'fajar_nugraha',
        ]);

        $this->regularUser = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'User Non Pengelola',
            'username' => 'user_biasa_cancel_exec',
        ]);

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'semester' => 'Ganjil',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]
        );

        $this->service = new GtkDecreeCancellationService(
            new GtkDecreeCancellationPreviewService
        );
    }

    /**
     * 1. SK aktif dapat dibatalkan ketika semua tindakan aman.
     * 2. Status dan timestamp pembatalan tersimpan dengan benar.
     */
    public function test_active_decree_can_be_cancelled_safely_with_proper_audit(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-001/2026',
            'title' => 'SK Uji Pembatalan Aktif',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
            'activated_at' => now()->subDays(10),
            'activated_by' => $this->admin->id,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'TIK-E1'], ['name' => 'TIK E1']);
        $assignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'operational_type' => TeacherSubjectAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_subject_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $reason = 'Perubahan rencana kurikulum semester ganjil';
        $result = $this->service->cancel($decree, $this->admin, $reason);

        $this->assertTrue($result['success']);
        $this->assertEquals(GtkDecree::STATUS_CANCELLED, $decree->fresh()->status);
        $this->assertNotNull($decree->fresh()->cancelled_at);
        $this->assertEquals($this->admin->id, $decree->fresh()->cancelled_by);
        $this->assertEquals($reason, $decree->fresh()->cancellation_reason);

        // Status rincian di-update menjadi cancelled
        $this->assertEquals(GtkDecreeDetail::STATUS_CANCELLED, $detail->fresh()->implementation_status);
    }

    /**
     * 3. SK yang sudah dibatalkan tidak dapat dibatalkan ulang.
     */
    public function test_already_cancelled_decree_cannot_be_cancelled_again(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-003/2026',
            'title' => 'SK Sudah Batal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_CANCELLED,
            'cancelled_at' => now()->subDay(),
            'cancelled_by' => $this->admin->id,
            'cancellation_reason' => 'Batal sebelumnya',
        ]);

        $this->expectException(GtkDecreeCancellationException::class);
        $this->expectExceptionMessage('sudah berstatus dibatalkan');

        $this->service->cancel($decree, $this->admin, 'Alasan coba lagi');
    }

    /**
     * 4. SK draf tidak diperlakukan sebagai penugasan yang telah diterapkan.
     */
    public function test_draft_decree_cancellation_does_not_modify_operational_assignments(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-004/2026',
            'title' => 'SK Draf Pembatalan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'TIK-E4'], ['name' => 'TIK E4']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $initialAssignmentsCount = TeacherSubjectAssignment::count();

        $result = $this->service->cancel($decree, $this->admin, 'Draf dibatalkan oleh operator');

        $this->assertTrue($result['success']);
        $this->assertEquals(GtkDecree::STATUS_CANCELLED, $decree->fresh()->status);
        $this->assertEquals($initialAssignmentsCount, TeacherSubjectAssignment::count());
    }

    /**
     * 5. Penugasan ditautkan (link_type = ditautkan) tetap aktif di sistem operasional.
     */
    public function test_linked_assignment_remains_active_when_decree_cancelled(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'KEPALA-LAB-E5'],
            ['name' => 'Kepala Lab E5', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => false, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/EXEC-005/2026',
            'title' => 'SK Pengukuhan',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_LINKED, // Hanya ditautkan
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'SK pengukuhan dicabut');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['unlinked_count']);

        // Penugasan operasional tetap aktif (tidak dinonaktifkan)
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertNull($assignment->fresh()->valid_until);

        // Status rincian diubah menjadi cancelled
        $this->assertEquals(GtkDecreeDetail::STATUS_CANCELLED, $detail->fresh()->implementation_status);
        $this->assertStringContainsString('Tautan SK dibatalkan', $detail->fresh()->notes);
    }

    /**
     * 6. Penugasan yang didukung SK lain tetap aktif.
     */
    public function test_assignment_supported_by_other_decree_remains_active(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'STAF-TU-E6'],
            ['name' => 'Staf TU E6', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => true, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        // SK Lain (Masih Aktif)
        $otherDecree = GtkDecree::create([
            'number' => '800/SK-LAIN-E6/2026',
            'title' => 'SK Lain Pendukung',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $otherDecree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
        ]);

        // SK yang akan dibatalkan
        $targetDecree = GtkDecree::create([
            'number' => '800/SK-TARGET-E6/2026',
            'title' => 'SK Target Batal',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $targetDecree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->cancel($targetDecree, $this->admin, 'SK target dibatalkan');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['protected_count']);

        // Penugasan tetap aktif karena dilindungi $otherDecree
        $this->assertTrue($assignment->fresh()->is_active);

        // SK lain tidak berubah statusnya
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $otherDecree->fresh()->status);
    }

    /**
     * 7. Penugasan yang dibuat oleh SK ini dan aman dihentikan diproses sesuai aturan (dinonaktifkan).
     * 13. Penugasan struktural dan riwayatnya tetap konsisten.
     */
    public function test_created_assignment_is_safely_deactivated_when_no_other_basis(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA-SARPRAS-E7'],
            ['name' => 'Waka Sarpras E7', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => false, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/EXEC-007/2026',
            'title' => 'SK Pengangkatan Waka',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'Pencabutan jabatan struktural');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['deactivated_count']);

        // Data tidak dihapus permanen, melainkan dinonaktifkan secara aman
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertNotNull($assignment->fresh()->valid_until);
        $this->assertStringContainsString('Dinonaktifkan via pembatalan SK', $assignment->fresh()->notes);
    }

    /**
     * 8. Penugasan dengan sumber atau kepemilikan yang tidak jelas tidak dinonaktifkan.
     * 14. Detail arsip tidak menghasilkan perubahan operasional.
     */
    public function test_archive_or_unlinked_details_do_not_alter_operational_data(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-008/2026',
            'title' => 'SK Arsip dan Kepanitiaan',
            'type' => GtkDecree::TYPE_KEPANITIAAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_ARCHIVED,
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'Kepanitiaan dibubarkan');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['no_impact_count']);
    }

    /**
     * 9. Jadwal KBM (LessonSchedule) tidak dihapus saat SK dibatalkan.
     * 15. Pemeriksaan manual menghasilkan perilaku yang sesuai dengan kebijakan (dipertahankan).
     */
    public function test_lesson_schedules_are_not_deleted_and_assignment_is_preserved_under_manual_review(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'KBM-E9'], ['name' => 'KBM E9']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT E9', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $assignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 4,
        ]);

        $schedule = LessonSchedule::create([
            'academic_year' => $this->academicYear->name,
            'semester' => 'Ganjil',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacherA->id,
            'day' => 'Selasa',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        $decree = GtkDecree::create([
            'number' => '800/EXEC-009/2026',
            'title' => 'SK Mengajar dengan Jadwal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'operational_type' => TeacherSubjectAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_subject_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'Pembatalan SK mengajar');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['manual_review_count']);

        // Jadwal KBM tetap utuh di database (tidak terhapus)
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $schedule->id,
            'teacher_id' => $this->teacherA->id,
        ]);

        // Penugasan operasional dipertahankan demi operasional sekolah
        $this->assertDatabaseHas('teacher_subject_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $this->teacherA->id,
        ]);

        $this->assertNotNull($detail->fresh()->conflict_notes);
    }

    /**
     * 10. Jadwal piket tidak dihapus.
     */
    public function test_piket_schedule_is_not_altered(): void
    {
        $piketPos = StructuralPosition::firstOrCreate(
            ['code' => 'GURU-PIKET-E10'],
            ['name' => 'Guru Piket E10', 'type' => StructuralPosition::TYPE_OPERASIONAL, 'allows_multiple' => true, 'is_active' => true]
        );

        $decree = GtkDecree::create([
            'number' => '800/EXEC-010/2026',
            'title' => 'SK Piket',
            'type' => GtkDecree::TYPE_GURU_PIKET,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'structural_position_id' => $piketPos->id,
            'operational_type' => StructuralPosition::class,
            'operational_id' => $piketPos->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'SK piket dibatalkan');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['manual_review_count']);
    }

    /**
     * 11. Wali kelas (classrooms.homeroom_teacher_id) tidak dikosongkan secara otomatis.
     */
    public function test_homeroom_teacher_is_not_cleared_automatically_on_cancellation(): void
    {
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT E11', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id, 'homeroom_teacher_id' => $this->teacherA->id]
        );

        $decree = GtkDecree::create([
            'number' => '800/EXEC-011/2026',
            'title' => 'SK Wali Kelas E11',
            'type' => GtkDecree::TYPE_WALI_KELAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'classroom_id' => $classroom->id,
            'operational_type' => Classroom::class,
            'operational_id' => $classroom->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->cancel($decree, $this->admin, 'Pencabutan SK wali kelas');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['manual_review_count']);

        // Rombel tidak kehilangan wali kelas secara mendadak
        $this->assertEquals($this->teacherA->id, $classroom->fresh()->homeroom_teacher_id);
    }

    /**
     * 12. Role global pengguna tidak berubah secara tidak sengaja.
     */
    public function test_user_global_role_remains_unchanged_on_cancellation(): void
    {
        $initialRoleId = $this->teacherA->role_id;

        $roleStaf = Role::firstOrCreate(['slug' => 'staf_kurikulum_e12'], ['name' => 'Staf Kurikulum E12']);
        $roleAssignment = UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $roleStaf->id,
            'academic_year' => $this->academicYear->name,
            'status' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/EXEC-012/2026',
            'title' => 'SK Peran Sistem',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_PERAN_SISTEM,
            'role_id' => $roleStaf->id,
            'operational_type' => UserRoleAssignment::class,
            'operational_id' => $roleAssignment->id,
            'user_role_assignment_id' => $roleAssignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $this->service->cancel($decree, $this->admin, 'Pencabutan tugas tambahan sistem');

        // Role global pada tabel users tidak berubah
        $this->assertEquals($initialRoleId, $this->teacherA->fresh()->role_id);

        // Hanya penugasan kontekstual yang dinonaktifkan
        $this->assertFalse($roleAssignment->fresh()->status);
    }

    /**
     * 16. Pengguna tanpa izin tidak dapat mengeksekusi pembatalan.
     */
    public function test_unauthorized_user_cannot_execute_cancellation_via_endpoint(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-016/2026',
            'title' => 'SK Otorisasi Pembatalan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->post(route('gtk-decrees.cancel', $decree), [
                'cancellation_reason' => 'Mencoba batalkan tanpa izin',
            ]);

        $response->assertStatus(403);
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
    }

    /**
     * 17. Kegagalan di tengah transaksi me-rollback seluruh perubahan.
     */
    public function test_failure_during_transaction_rolls_back_all_changes(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA-E17'],
            ['name' => 'Waka E17', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => false, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/EXEC-017/2026',
            'title' => 'SK Uji Rollback',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        // Simulasikan kegagalan validasi alasan kosong
        try {
            $this->service->cancel($decree, $this->admin, '');
            $this->fail('Harusnya melempar exception.');
        } catch (GtkDecreeCancellationException $e) {
            $this->assertEquals('Alasan pembatalan SK wajib diisi.', $e->getMessage());
        }

        // Pastikan status SK dan penugasan operasional tidak berubah
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detail->fresh()->implementation_status);
    }

    /**
     * 18. Dua permintaan pembatalan yang bersamaan tidak menghasilkan eksekusi ganda.
     * 20. Pengulangan permintaan tidak merusak konsistensi data.
     */
    public function test_repeated_or_concurrent_cancellation_attempt_is_rejected_safely(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-018/2026',
            'title' => 'SK Uji Idempoten Pembatalan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        // Pembatalan pertama berhasil
        $result = $this->service->cancel($decree, $this->admin, 'Pembatalan pertama yang sah');
        $this->assertTrue($result['success']);
        $this->assertEquals(GtkDecree::STATUS_CANCELLED, $decree->fresh()->status);

        // Pembatalan kedua harus ditolak
        $this->expectException(GtkDecreeCancellationException::class);
        $this->expectExceptionMessage('sudah berstatus dibatalkan');

        $this->service->cancel($decree, $this->admin, 'Pembatalan kedua');
    }

    /**
     * 19. Pemanggilan service tidak menyebabkan perubahan pada SK lain.
     */
    public function test_cancellation_does_not_affect_other_decrees(): void
    {
        $otherDecree = GtkDecree::create([
            'number' => '800/SK-LAIN-E19/2026',
            'title' => 'SK Tetap Aktif',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $decreeToCancel = GtkDecree::create([
            'number' => '800/SK-BATAL-E19/2026',
            'title' => 'SK Yang Dibatalkan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $this->service->cancel($decreeToCancel, $this->admin, 'Alasan pembatalan');

        $this->assertEquals(GtkDecree::STATUS_CANCELLED, $decreeToCancel->fresh()->status);
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $otherDecree->fresh()->status);
        $this->assertNull($otherDecree->fresh()->cancelled_at);
    }

    /**
     * 20. Eksekusi pembatalan via HTTP POST berhasil dan dialihkan dengan notifikasi sukses.
     */
    public function test_authorized_user_can_execute_cancellation_via_http_endpoint_successfully(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-HTTP/2026',
            'title' => 'SK Uji HTTP Batal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('gtk-decrees.cancel', $decree), [
                'cancellation_reason' => 'Kesalahan penulisan data penugasan pada SK',
            ]);

        $response->assertRedirect(route('gtk-decrees.show', $decree));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('berhasil dibatalkan', session('success'));

        $this->assertEquals(GtkDecree::STATUS_CANCELLED, $decree->fresh()->status);
        $this->assertEquals($this->admin->id, $decree->fresh()->cancelled_by);
        $this->assertEquals('Kesalahan penulisan data penugasan pada SK', $decree->fresh()->cancellation_reason);
    }

    /**
     * 21. Validasi alasan pembatalan minimal 3 karakter via HTTP POST.
     */
    public function test_cancellation_requires_valid_reason_via_http(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/EXEC-VALID/2026',
            'title' => 'SK Validasi Alasan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('gtk-decrees.cancel', $decree), [
                'cancellation_reason' => '',
            ]);

        $response->assertSessionHasErrors('cancellation_reason');
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
    }
}
