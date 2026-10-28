<?php

namespace Tests\Feature;

use App\Exceptions\GtkDecreeActivationException;
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
use App\Services\GtkDecreeActivationService;
use App\Services\GtkDecreeImpactPreviewService;
use App\Services\HomeroomAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkDecreeActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $teacherB;

    private User $regularUser;

    private AcademicYear $academicYear;

    private GtkDecreeActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $permMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permMaster->id]);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Pengelola',
            'username' => 'admin_pengelola',
        ]);

        $this->teacherA = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Budi Santoso',
            'username' => 'budi_santoso',
        ]);

        $this->teacherB = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Siti Rahayu',
            'username' => 'siti_rahayu',
        ]);

        $this->regularUser = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Biasa Tanpa Izin',
            'username' => 'guru_biasa',
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

        $this->service = new GtkDecreeActivationService(
            new GtkDecreeImpactPreviewService,
            new HomeroomAssignmentService
        );
    }

    /**
     * 1. Aktivasi SK valid membuat penugasan yang belum ada.
     */
    public function test_activation_creates_new_assignments_when_none_exist(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/101/SMK/2026',
            'title' => 'SK Pembagian Tugas KBM',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'FIS-X'], ['name' => 'Fisika X']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 1', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 4,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $result = $this->service->activate($decree, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['created_count']);
        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
        $this->assertNotNull($decree->fresh()->activated_at);
        $this->assertEquals($this->admin->id, $decree->fresh()->activated_by);

        // Periksa data operasional TeacherSubjectAssignment dibuat
        $this->assertDatabaseHas('teacher_subject_assignments', [
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 4,
        ]);

        // Periksa detail SK ditautkan ke record baru
        $detailFresh = $detail->fresh();
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detailFresh->implementation_status);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_CREATED, $detailFresh->link_type);
        $this->assertEquals(TeacherSubjectAssignment::class, $detailFresh->operational_type);
        $this->assertNotNull($detailFresh->operational_id);
    }

    /**
     * 2. Penugasan identik ditautkan tanpa duplikasi.
     */
    public function test_activation_links_identical_existing_assignments_without_duplication(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'KIM-X'], ['name' => 'Kimia X']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 2', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        // Penugasan operasional sudah ada sebelumnya
        $existing = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 3,
            'notes' => 'Catatan penugasan lama',
        ]);

        $decree = GtkDecree::create([
            'number' => '800/102/SMK/2026',
            'title' => 'SK Penetapan Kimia',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 3,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $initialCount = TeacherSubjectAssignment::count();

        $result = $this->service->activate($decree, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['linked_count']);
        $this->assertEquals(0, $result['created_count']);

        // Pastikan tidak ada record baru yang dibuat (idempoten)
        $this->assertEquals($initialCount, TeacherSubjectAssignment::count());

        $detailFresh = $detail->fresh();
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detailFresh->implementation_status);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_LINKED, $detailFresh->link_type);
        $this->assertEquals($existing->id, $detailFresh->operational_id);
    }

    /**
     * 3. Konflik menggagalkan seluruh transaksi.
     */
    public function test_activation_aborts_entire_transaction_on_conflict(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'BIO-X'], ['name' => 'Biologi X']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 3', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        // Guru B sudah mengajar mapel & kelas ini di TP yang sama
        TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/103/SMK/2026',
            'title' => 'SK Konflik Mengajar',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Rincian SK menugaskan Guru A pada mapel & kelas yang sama
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 2,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $this->expectException(GtkDecreeActivationException::class);
        $this->expectExceptionMessage('konflik penugasan');

        try {
            $this->service->activate($decree, $this->admin);
        } finally {
            // Pastikan status SK tetap draft dan tidak aktif
            $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
            $this->assertNull($decree->fresh()->activated_at);
        }
    }

    /**
     * 4. Rincian belum lengkap menggagalkan seluruh transaksi.
     */
    public function test_activation_aborts_entire_transaction_on_incomplete_detail(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/104/SMK/2026',
            'title' => 'SK Detail Belum Lengkap',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Rincian kategori mengajar tanpa mata pelajaran (subject_id null)
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => null,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $this->expectException(GtkDecreeActivationException::class);
        $this->expectExceptionMessage('belum lengkap');

        try {
            $this->service->activate($decree, $this->admin);
        } finally {
            $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
        }
    }

    /**
     * 5. Kegagalan satu rincian me-rollback seluruh perubahan.
     */
    public function test_single_detail_failure_rolls_back_all_operational_changes(): void
    {
        $subjectValid = Subject::firstOrCreate(['code' => 'SEJ-X'], ['name' => 'Sejarah X']);
        $subjectConflict = Subject::firstOrCreate(['code' => 'GEO-X'], ['name' => 'Geografi X']);

        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 4', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        // Guru B memegang Geografi (akan menyebabkan konflik pada rincian 2)
        TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'subject_id' => $subjectConflict->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/105/SMK/2026',
            'title' => 'SK Kombinasi Valid dan Konflik',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Rincian 1: Sejarah (Valid, belum ada sebelumnya)
        $detail1 = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subjectValid->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 2,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Rincian 2: Geografi (Konflik dengan Guru B)
        $detail2 = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subjectConflict->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 2,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        try {
            $this->service->activate($decree, $this->admin);
            $this->fail('Harusnya melempar exception konflik.');
        } catch (GtkDecreeActivationException $e) {
            $this->assertEquals('conflict', $e->getErrorType());
        }

        // Verifikasi rollback: Penugasan Sejarah TIDAK boleh dibuat di database
        $this->assertDatabaseMissing('teacher_subject_assignments', [
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subjectValid->id,
        ]);

        // Status rincian 1 dan 2 tetap pending
        $this->assertEquals(GtkDecreeDetail::STATUS_PENDING, $detail1->fresh()->implementation_status);
        $this->assertEquals(GtkDecreeDetail::STATUS_PENDING, $detail2->fresh()->implementation_status);
        $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
    }

    /**
     * 6. SK yang sudah aktif tidak bisa diterapkan ulang.
     */
    public function test_already_active_decree_cannot_be_reactivated(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/106/SMK/2026',
            'title' => 'SK Sudah Aktif',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by' => $this->admin->id,
        ]);

        $this->expectException(GtkDecreeActivationException::class);
        $this->expectExceptionMessage('sudah berstatus aktif');

        $this->service->activate($decree, $this->admin);
    }

    /**
     * 7. SK yang dibatalkan tidak bisa diaktifkan kembali.
     */
    public function test_cancelled_decree_cannot_be_activated(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/107/SMK/2026',
            'title' => 'SK Dibatalkan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $this->admin->id,
            'cancellation_reason' => 'Perubahan kebijakan dinas',
        ]);

        $this->expectException(GtkDecreeActivationException::class);
        $this->expectExceptionMessage('telah dibatalkan');

        $this->service->activate($decree, $this->admin);
    }

    /**
     * 8. SK dengan tanggal efektif di masa depan tidak mengaktifkan penugasan terlalu dini.
     */
    public function test_future_effective_date_decree_is_rejected(): void
    {
        // Tetapkan tanggal SK di masa depan
        $futureDate = Carbon::now()->addMonths(2)->format('Y-m-d');

        $decree = GtkDecree::create([
            'number' => '800/108/SMK/2026',
            'title' => 'SK Masa Depan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => now()->toDateString(),
            'tmt_date' => $futureDate,
            'valid_from' => $futureDate,
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'ART-X'], ['name' => 'Seni Budaya']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'valid_from' => $futureDate,
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $this->expectException(GtkDecreeActivationException::class);
        $this->expectExceptionMessage('masih di masa depan');

        $this->service->activate($decree, $this->admin);
    }

    /**
     * 9. Aktivasi tidak membuat jadwal pelajaran tanpa data jadwal yang diperlukan.
     */
    public function test_activation_does_not_create_lesson_schedules(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/109/SMK/2026',
            'title' => 'SK Tanpa Pembuatan Jadwal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'ENG-X'], ['name' => 'Bahasa Inggris X']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 5', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 4,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $initialScheduleCount = LessonSchedule::count();

        $this->service->activate($decree, $this->admin);

        // Jumlah jadwal pelajaran harus tetap sama (tidak dibuat otomatis)
        $this->assertEquals($initialScheduleCount, LessonSchedule::count());
    }

    /**
     * 10. Aktivasi tidak mengubah role atau permission global pengguna secara tidak semestinya.
     */
    public function test_activation_does_not_alter_global_user_roles_improperly(): void
    {
        $roleSuper = Role::where('slug', 'super_admin')->first();
        $initialTeacherRoleId = $this->teacherA->role_id;

        $decree = GtkDecree::create([
            'number' => '800/110/SMK/2026',
            'title' => 'SK Peran Sistem',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'academic_year_id' => $this->academicYear->id,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $specialRole = Role::firstOrCreate(['slug' => 'staf_kurikulum'], ['name' => 'Staf Kurikulum']);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_PERAN_SISTEM,
            'role_id' => $specialRole->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $this->service->activate($decree, $this->admin);

        // Role global pada tabel users TIDAK berubah
        $this->assertEquals($initialTeacherRoleId, $this->teacherA->fresh()->role_id);
        $this->assertNotEquals($roleSuper->id, $this->teacherA->fresh()->role_id);

        // Hanya tercatat pada user_role_assignments kontekstual
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'role_id' => $specialRole->id,
            'status' => true,
        ]);
    }

    /**
     * 11. Rincian arsip tidak membuat penugasan operasional.
     */
    public function test_archive_details_do_not_create_operational_assignments(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/111/SMK/2026',
            'title' => 'SK Kepanitiaan Arsip',
            'type' => GtkDecree::TYPE_KEPANITIAAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
            'notes' => 'Panitia Peringatan Hari Guru (arsip)',
        ]);

        $countSubject = TeacherSubjectAssignment::count();
        $countStructural = TeacherStructuralAssignment::count();
        $countRole = UserRoleAssignment::count();

        $result = $this->service->activate($decree, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['archived_count']);

        // Tidak ada tabel operasional yang bertambah
        $this->assertEquals($countSubject, TeacherSubjectAssignment::count());
        $this->assertEquals($countStructural, TeacherStructuralAssignment::count());
        $this->assertEquals($countRole, UserRoleAssignment::count());

        $detailFresh = $detail->fresh();
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detailFresh->implementation_status);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_ARCHIVED, $detailFresh->link_type);
        $this->assertNull($detailFresh->operational_id);
    }

    /**
     * 12. Rincian yang sudah diterapkan tidak menghasilkan duplikasi saat permintaan diulang.
     */
    public function test_already_applied_details_do_not_duplicate_on_repeated_activation(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'IPS-X'], ['name' => 'IPS Terpadu']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 6', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $decree = GtkDecree::create([
            'number' => '800/112/SMK/2026',
            'title' => 'SK Uji Idempotensi',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $existingAssignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        // Simulasikan rincian yang sudah berstatus APPLIED dan memiliki operational_id
        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 2,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_LINKED,
            'operational_type' => TeacherSubjectAssignment::class,
            'operational_id' => $existingAssignment->id,
            'teacher_subject_assignment_id' => $existingAssignment->id,
        ]);

        $initialCount = TeacherSubjectAssignment::count();

        $result = $this->service->activate($decree, $this->admin);

        $this->assertTrue($result['success']);
        // Tidak ada penugasan baru yang tercipta
        $this->assertEquals($initialCount, TeacherSubjectAssignment::count());
    }

    /**
     * 13. Pengguna tanpa permission tidak dapat menjalankan aktivasi melalui endpoint controller.
     */
    public function test_unauthorized_user_cannot_activate_decree_via_endpoint(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/113/SMK/2026',
            'title' => 'SK Otorisasi',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'BIN-X'], ['name' => 'Bahasa Indonesia']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Request POST aktivasi oleh pengguna biasa tanpa izin manage_master_data
        $response = $this->actingAs($this->regularUser)
            ->post(route('gtk-decrees.activate', $decree));

        $response->assertStatus(403);
        $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
    }

    /**
     * 14. Penautan penugasan tidak menghapus sumber SK yang sudah ada.
     */
    public function test_linking_assignment_preserves_existing_sk_references(): void
    {
        $pos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA-SARPRAS'],
            ['name' => 'Wakil Kepala Bidang Sarpras', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'allows_multiple' => true, 'is_active' => true]
        );

        // Penugasan struktural lama yang merujuk pada SK Pertama
        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'structural_position_id' => $pos->id,
            'academic_year' => $this->academicYear->name,
            'sk_number' => '800/001/LAMA/2025',
            'sk_date' => '2025-07-01',
            'valid_from' => '2025-07-01',
            'valid_until' => '2027-06-30',
            'notes' => 'Dasar hukum SK Pertama',
            'is_active' => true,
        ]);

        // Buat SK Baru yang menautkan jabatan tersebut
        $decree2 = GtkDecree::create([
            'number' => '800/114/BARU/2026',
            'title' => 'SK Konfirmasi Waka Sarpras',
            'type' => GtkDecree::TYPE_TUGAS_TAMBAHAN,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree2->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $pos->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $this->service->activate($decree2, $this->admin);

        // Nomor SK asli pada penugasan operasional tidak berubah/tertimpa
        $this->assertEquals('800/001/LAMA/2025', $assignment->fresh()->sk_number);

        // Namun rincian SK baru tetap berhasil ditautkan
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_LINKED, $detail->fresh()->link_type);
        $this->assertEquals($assignment->id, $detail->fresh()->operational_id);
    }

    /**
     * 15. Aktivasi wali kelas menetapkan homeroom_teacher_id dan menyinkronkan peran kontekstual.
     */
    public function test_activation_sets_homeroom_teacher_and_syncs_contextual_role(): void
    {
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT 7', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        $decree = GtkDecree::create([
            'number' => '800/115/SMK/2026',
            'title' => 'SK Wali Kelas X TJKT 7',
            'type' => GtkDecree::TYPE_WALI_KELAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_OPERASIONAL,
            'classroom_id' => $classroom->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $result = $this->service->activate($decree, $this->admin);

        $this->assertTrue($result['success']);
        // Kolom homeroom_teacher_id pada classroom terisi Guru A
        $this->assertEquals($this->teacherA->id, $classroom->fresh()->homeroom_teacher_id);

        // Peran kontekstual wali_kelas dibuat di user_role_assignments
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacherA->id,
            'context_type' => Classroom::class,
            'context_id' => $classroom->id,
            'status' => true,
        ]);

        $detailFresh = $detail->fresh();
        $this->assertEquals(GtkDecreeDetail::STATUS_APPLIED, $detailFresh->implementation_status);
        $this->assertEquals(Classroom::class, $detailFresh->operational_type);
        $this->assertEquals($classroom->id, $detailFresh->operational_id);
    }

    /**
     * 16. Pengguna dengan permission dapat mengaktifkan SK via HTTP endpoint controller dengan sukses.
     */
    public function test_authorized_user_can_activate_decree_via_endpoint_successfully(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/116/SMK/2026',
            'title' => 'SK Aktivasi Via HTTP',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'PAI-X'], ['name' => 'Pendidikan Agama Islam']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'teaching_hours' => 3,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('gtk-decrees.activate', $decree));

        $response->assertRedirect(route('gtk-decrees.show', $decree));
        $response->assertSessionHas('success');

        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
        $this->assertNotNull($decree->fresh()->activated_at);
        $this->assertEquals($this->admin->id, $decree->fresh()->activated_by);
    }
}
