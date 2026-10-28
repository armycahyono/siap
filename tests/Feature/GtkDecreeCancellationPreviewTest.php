<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkDecreeCancellationPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $regularUser;

    private AcademicYear $academicYear;

    private GtkDecreeCancellationPreviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $permMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permMaster->id]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Pengelola SK',
            'username' => 'admin_cancel_test',
        ]);

        $this->teacherA = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Bambang',
            'username' => 'bambang_guru',
        ]);

        $this->regularUser = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'User Non Pengelola',
            'username' => 'user_biasa_cancel',
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

        $this->service = new GtkDecreeCancellationPreviewService;
    }

    /**
     * 1. Pratinjau SK draft tidak mengubah data operasional.
     */
    public function test_draft_decree_cancellation_preview_does_not_modify_operational_data(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-001/2026',
            'title' => 'SK Draf Uji Batal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'TIK-B1'], ['name' => 'TIK B1']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $initialAssignments = TeacherSubjectAssignment::count();

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_NO_IMPACT, $result['items'][0]['status']);
        $this->assertEquals($initialAssignments, TeacherSubjectAssignment::count());
        $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
    }

    /**
     * 2. Pratinjau SK aktif tidak mengubah status SK.
     */
    public function test_active_decree_cancellation_preview_does_not_modify_decree_status(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-002/2026',
            'title' => 'SK Aktif Uji Batal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by' => $this->admin->id,
        ]);

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
        $this->assertNull($decree->fresh()->cancelled_at);
        $this->assertNull($decree->fresh()->cancelled_by);
    }

    /**
     * 3. Pratinjau tidak mengubah status rincian.
     */
    public function test_preview_does_not_modify_detail_implementation_status(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-003/2026',
            'title' => 'SK Status Rincian',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_ARCHIVED,
        ]);

        $this->service->preview($decree);

        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detail->fresh()->implementation_status);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_ARCHIVED, $detail->fresh()->link_type);
    }

    /**
     * 4. Pratinjau tidak mengubah penugasan guru mapel.
     */
    public function test_preview_does_not_modify_teacher_subject_assignments(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'KIM-B4'], ['name' => 'Kimia B4']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT B4', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $assignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 3,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/CANC-004/2026',
            'title' => 'SK Mapel KBM',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
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

        $this->service->preview($decree);

        // Data penugasan mengajar tetap utuh
        $this->assertDatabaseHas('teacher_subject_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $this->teacherA->id,
            'hours_per_week' => 3,
        ]);
    }

    /**
     * 5. Pratinjau tidak mengubah wali kelas atau peran kontekstual.
     */
    public function test_preview_does_not_modify_classroom_homeroom_or_contextual_roles(): void
    {
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT B5', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id, 'homeroom_teacher_id' => $this->teacherA->id]
        );

        $roleWali = Role::firstOrCreate(['slug' => 'wali_kelas'], ['name' => 'Wali Kelas']);
        $roleAssignment = UserRoleAssignment::create([
            'user_id' => $this->teacherA->id,
            'role_id' => $roleWali->id,
            'context_type' => Classroom::class,
            'context_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'status' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/CANC-005/2026',
            'title' => 'SK Wali Kelas',
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

        $result = $this->service->preview($decree);

        // Wali kelas ditandai perlu pemeriksaan manual
        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_MANUAL_REVIEW, $result['items'][0]['status']);

        // Data wali kelas di database tetap utuh
        $this->assertEquals($this->teacherA->id, $classroom->fresh()->homeroom_teacher_id);
        $this->assertTrue($roleAssignment->fresh()->status);
    }

    /**
     * 6. Pratinjau tidak mengubah jabatan struktural.
     */
    public function test_preview_does_not_modify_teacher_structural_assignments(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA-HUMAS'],
            ['name' => 'Waka Humas', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => false, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/CANC-006/2026',
            'title' => 'SK Waka Humas',
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

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_CANDIDATE_DEACTIVATE, $result['items'][0]['status']);
        $this->assertTrue($assignment->fresh()->is_active);
    }

    /**
     * 7. Penugasan yang ditautkan ke data lama diklasifikasikan sebagai akan_dilepas.
     */
    public function test_linked_assignment_is_marked_as_will_unlink_not_candidate_deactivate(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'KEPALA-LAB'],
            ['name' => 'Kepala Lab Komputer', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => false, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/CANC-007/2026',
            'title' => 'SK Tautan Kepala Lab',
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
            'link_type' => GtkDecreeDetail::LINK_TYPE_LINKED, // Hanya ditautkan
        ]);

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_WILL_UNLINK, $result['items'][0]['status']);
    }

    /**
     * 8. Penugasan dengan indikasi sumber SK lain ditandai dilindungi_sumber_lain.
     */
    public function test_assignment_with_other_active_decree_is_marked_as_protected(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'STAF-ADMIN'],
            ['name' => 'Staf Tata Usaha', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => true, 'is_active' => true]
        );

        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'is_active' => true,
        ]);

        // SK Pertama (Aktif)
        $decree1 = GtkDecree::create([
            'number' => '800/SK-UTAMA/2026',
            'title' => 'SK Utama Staf',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree1->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
        ]);

        // SK Kedua yang akan diuji pembatalannya
        $decree2 = GtkDecree::create([
            'number' => '800/SK-KEDUA/2026',
            'title' => 'SK Kedua Penegasan',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree2->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'operational_type' => TeacherStructuralAssignment::class,
            'operational_id' => $assignment->id,
            'teacher_structural_assignment_id' => $assignment->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        $result = $this->service->preview($decree2);

        // Karena SK 1 masih aktif menaungi jabatan ini, penugasan dilindungi oleh SK lain
        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_PROTECTED_BY_OTHER, $result['items'][0]['status']);
    }

    /**
     * 9. Rincian arsip tidak menghasilkan dampak operasional.
     */
    public function test_archive_details_do_not_produce_operational_impact(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-009/2026',
            'title' => 'SK Khusus Arsip',
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

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_NO_IMPACT, $result['items'][0]['status']);
        $this->assertEquals(1, $result['summary']['no_impact']);
    }

    /**
     * 10. SK yang sudah dibatalkan tidak menawarkan pembatalan ulang.
     */
    public function test_already_cancelled_decree_does_not_offer_re_cancellation(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-010/2026',
            'title' => 'SK Dibatalkan Masa Lalu',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_CANCELLED,
            'cancelled_at' => now()->subDays(5),
            'cancellation_reason' => 'Dicabut karena salah input',
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'implementation_status' => GtkDecreeDetail::STATUS_CANCELLED,
        ]);

        $result = $this->service->preview($decree);

        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_ALREADY_INACTIVE, $result['items'][0]['status']);
        $this->assertStringContainsString('dibatalkan sebelumnya', $result['items'][0]['reason']);
    }

    /**
     * 11. Pengguna tanpa izin tidak dapat mengakses data pratinjau pembatalan.
     */
    public function test_unauthorized_user_cannot_access_cancellation_preview(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-011/2026',
            'title' => 'SK Akses Otorisasi',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('gtk-decrees.cancel-preview', $decree));

        $response->assertStatus(403);
    }

    /**
     * 12. Pratinjau tidak mengubah jadwal pelajaran atau jadwal piket.
     */
    public function test_preview_does_not_modify_lesson_schedules_or_piket_schedules(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'BIO-B12'], ['name' => 'Biologi B12']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT B12', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $assignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        // Buat jadwal pelajaran aktif
        $schedule = LessonSchedule::create([
            'academic_year' => $this->academicYear->name,
            'semester' => 'Ganjil',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacherA->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        $decree = GtkDecree::create([
            'number' => '800/CANC-012/2026',
            'title' => 'SK Jadwal Aman',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        GtkDecreeDetail::create([
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

        $initialSchedulesCount = LessonSchedule::count();

        $result = $this->service->preview($decree);

        // Karena ada jadwal aktif, harus ditandai manual review
        $this->assertEquals(GtkDecreeCancellationPreviewService::STATUS_MANUAL_REVIEW, $result['items'][0]['status']);

        // Jadwal pelajaran tidak boleh terhapus atau berubah
        $this->assertEquals($initialSchedulesCount, LessonSchedule::count());
        $this->assertDatabaseHas('lesson_schedules', [
            'id' => $schedule->id,
            'teacher_id' => $this->teacherA->id,
        ]);
    }

    /**
     * 13. Pengguna dengan hak kelola dapat melihat antarmuka pratinjau pembatalan.
     */
    public function test_authorized_user_can_access_cancellation_preview_view(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-VIEW/2026',
            'title' => 'SK Uji Tampilan Pembatalan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('gtk-decrees.cancel-preview', $decree));

        $response->assertStatus(200);
        $response->assertSee('Pratinjau Pembatalan');
        $response->assertSee('Kandidat Dinonaktifkan');
        $response->assertSee('Batalkan Dokumen SK Ini');
    }

    /**
     * 14. Pratinjau pembatalan bersifat murni read-only pada pemanggilan berulang kali.
     */
    public function test_cancellation_preview_is_strictly_read_only_across_multiple_runs(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/CANC-IDEM/2026',
            'title' => 'SK Uji Read-Only Berulang',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'SBK-X'], ['name' => 'Seni Budaya']);
        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        // Jalankan pratinjau 3 kali berturut-turut
        $this->service->preview($decree);
        $this->service->preview($decree);
        $this->service->preview($decree);

        // Pastikan tidak ada modifikasi pada status SK maupun detail
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detail->fresh()->implementation_status);
        $this->assertNull($decree->fresh()->cancelled_at);
    }
}
