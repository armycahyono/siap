<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\GtkDecreeImpactPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkDecreeImpactPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $teacherB;

    private AcademicYear $academicYear;

    private GtkDecreeImpactPreviewService $previewService;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $permMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permMaster->id]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Utama',
            'username' => 'admin_test',
        ]);

        $this->teacherA = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Budi',
            'username' => 'budi_guru',
        ]);

        $this->teacherB = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Siti',
            'username' => 'siti_guru',
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

        $this->previewService = new GtkDecreeImpactPreviewService;
    }

    public function test_preview_does_not_modify_any_database_records(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/030/SMK/2026',
            'title' => 'SK Uji Read-Only Preview',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'MTK-X'], ['name' => 'Matematika X']);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'teaching_hours' => 4,
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Snapshots counts and attributes before preview
        $decreeStatusBefore = $decree->status;
        $detailStatusBefore = $detail->implementation_status;
        $subjectAssignmentCountBefore = TeacherSubjectAssignment::count();
        $structuralAssignmentCountBefore = TeacherStructuralAssignment::count();
        $roleAssignmentCountBefore = UserRoleAssignment::count();

        // Execute preview
        $preview = $this->previewService->preview($decree);

        // Verify counts remain unchanged
        $this->assertEquals($decreeStatusBefore, $decree->fresh()->status);
        $this->assertEquals($detailStatusBefore, $detail->fresh()->implementation_status);
        $this->assertEquals($subjectAssignmentCountBefore, TeacherSubjectAssignment::count());
        $this->assertEquals($structuralAssignmentCountBefore, TeacherStructuralAssignment::count());
        $this->assertEquals($roleAssignmentCountBefore, UserRoleAssignment::count());

        $this->assertCount(1, $preview['items']);
    }

    public function test_identical_assignment_recognized_as_will_link(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'IND-X'], ['name' => 'Bahasa Indonesia X']);
        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKR 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );

        // Existing operational assignment for Teacher A
        $existing = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => '2026/2027',
            'hours_per_week' => 4,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/031/SMK/2026',
            'title' => 'SK Uji Tautkan KBM',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year' => '2026/2027',
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 4,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_WILL_LINK, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['will_link']);
        $this->assertEquals($existing->id, $preview['items'][0]['existing_record']->id);
    }

    public function test_new_assignment_recognized_as_will_create(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'ING-X'], ['name' => 'Bahasa Inggris X']);

        $decree = GtkDecree::create([
            'number' => '800/032/SMK/2026',
            'title' => 'SK Uji Buat Baru KBM',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year' => '2026/2027',
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'teaching_hours' => 3,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_WILL_CREATE, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['will_create']);
    }

    public function test_different_teacher_on_same_class_and_subject_detected_as_conflict(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'FIS-X'], ['name' => 'Fisika X']);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'TKJ']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKJ 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );

        // Teacher B is currently teaching Physics on X TKJ 1
        TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => '2026/2027',
            'hours_per_week' => 3,
        ]);

        // Decree intends to assign Teacher A to the exact same class and subject
        $decree = GtkDecree::create([
            'number' => '800/033/SMK/2026',
            'title' => 'SK Uji Konflik Guru KBM',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year' => '2026/2027',
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 3,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_CONFLICT, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['conflict']);
        $this->assertFalse($preview['summary']['is_ready_for_activation']);
        $this->assertNotNull($preview['items'][0]['conflict_comparison']);
    }

    public function test_single_holder_position_conflict_detected_on_overlapping_period(): void
    {
        // Single holder position (allows_multiple = false)
        $position = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KUR_TEST'],
            ['name' => 'Waka Kurikulum Uji', 'allows_multiple' => false]
        );

        // Teacher B holds this position actively from 2026-07-01 to 2027-06-30
        TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2026/2027',
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
            'is_active' => true,
        ]);

        // Decree intends to assign Teacher A to the same position during an overlapping period
        $decree = GtkDecree::create([
            'number' => '800/034/SMK/2026',
            'title' => 'SK Uji Konflik Jabatan Tunggal',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-08-01',
            'valid_until' => '2027-07-31',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $position->id,
            'valid_from' => '2026-08-01',
            'valid_until' => '2027-07-31',
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_CONFLICT, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['conflict']);
    }

    public function test_position_allowing_multiple_personnel_is_not_treated_as_conflict(): void
    {
        // Multi holder position (allows_multiple = true)
        $position = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_PENGEMBANG'],
            ['name' => 'Tim Pengembang Sekolah', 'allows_multiple' => true]
        );

        // Teacher B already holds this position
        TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2026/2027',
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
            'is_active' => true,
        ]);

        // Decree assigns Teacher A as an additional member
        $decree = GtkDecree::create([
            'number' => '800/035/SMK/2026',
            'title' => 'SK Uji Multi Jabatan',
            'type' => GtkDecree::TYPE_KEPANITIAAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'structural_position_id' => $position->id,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_WILL_CREATE, $preview['items'][0]['status']);
        $this->assertEquals(0, $preview['summary']['conflict']);
    }

    public function test_non_overlapping_tenure_periods_are_not_treated_as_conflict(): void
    {
        $position = StructuralPosition::firstOrCreate(
            ['code' => 'KEPSEK_TEST'],
            ['name' => 'Kepala Sekolah Uji', 'allows_multiple' => false]
        );

        // Old tenure ended on 2026-06-30
        TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'structural_position_id' => $position->id,
            'academic_year' => '2025/2026',
            'valid_from' => '2025-07-01',
            'valid_until' => '2026-06-30',
            'is_active' => true,
        ]);

        // New tenure for Teacher A starts on 2026-07-01 (No Date Overlap)
        $decree = GtkDecree::create([
            'number' => '800/036/SMK/2026',
            'title' => 'SK Uji Non Overlap Periode',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'issued_date' => '2026-06-25',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $position->id,
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_WILL_CREATE, $preview['items'][0]['status']);
        $this->assertEquals(0, $preview['summary']['conflict']);
    }

    public function test_archive_category_produces_no_operational_assignment_plan(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/037/SMK/2026',
            'title' => 'SK Uji Arsip Saja',
            'type' => GtkDecree::TYPE_LAINNYA,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'notes' => 'Pencatatan riwayat penghargaan pengabdian 10 tahun',
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_NOT_APPLICABLE, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['not_applicable']);
        $this->assertEquals(0, $preview['summary']['will_create']);
    }

    public function test_incomplete_details_are_properly_identified(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/038/SMK/2026',
            'title' => 'SK Uji Belum Lengkap',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Teaching assignment without subject_id
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => null,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_INCOMPLETE, $preview['items'][0]['status']);
        $this->assertEquals(1, $preview['summary']['incomplete']);
        $this->assertFalse($preview['summary']['is_ready_for_activation']);
    }

    public function test_piket_position_marked_as_incomplete_due_to_missing_day(): void
    {
        $piketPosition = StructuralPosition::firstOrCreate(
            ['code' => 'GURU_PIKET'],
            ['name' => 'Guru Piket Harian']
        );

        $decree = GtkDecree::create([
            'number' => '800/039/SMK/2026',
            'title' => 'SK Uji Guru Piket',
            'type' => GtkDecree::TYPE_GURU_PIKET,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'structural_position_id' => $piketPosition->id,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_INCOMPLETE, $preview['items'][0]['status']);
        $this->assertStringContainsString('Jadwal Piket', $preview['items'][0]['notes']);
    }

    public function test_homeroom_assignment_conflict_and_linking(): void
    {
        $dept = Department::firstOrCreate(['code' => 'AKL'], ['name' => 'Akuntansi']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X AKL 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );

        // Teacher B is currently homeroom teacher
        $classroom->update(['homeroom_teacher_id' => $this->teacherB->id]);

        $decree = GtkDecree::create([
            'number' => '800/040/SMK/2026',
            'title' => 'SK Uji Wali Kelas',
            'type' => GtkDecree::TYPE_WALI_KELAS,
            'academic_year' => '2026/2027',
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Attempting to assign Teacher A to X AKL 1
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'classroom_id' => $classroom->id,
        ]);

        $preview = $this->previewService->preview($decree);

        $this->assertEquals(GtkDecreeImpactPreviewService::STATUS_CONFLICT, $preview['items'][0]['status']);
        $this->assertStringContainsString($this->teacherB->name, $preview['items'][0]['notes']);
    }

    public function test_preview_respects_user_authorization(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/041/SMK/2026',
            'title' => 'SK Uji Otorisasi Preview',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Admin can access preview route
        $responseAdmin = $this->actingAs($this->admin)->get(route('gtk-decrees.preview', $decree));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Pratinjau Dampak');

        // Unauthorized teacher cannot access draft preview
        $responseTeacher = $this->actingAs($this->teacherA)->get(route('gtk-decrees.preview', $decree));
        $responseTeacher->assertStatus(403);
    }
}
