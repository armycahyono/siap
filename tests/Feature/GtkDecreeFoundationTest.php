<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\GtkDecreeRecipient;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GtkDecreeFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherA;

    private User $teacherB;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Sekolah',
            'username' => 'admin_sk',
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
    }

    public function test_gtk_decree_tables_exist_with_proper_columns(): void
    {
        $this->assertTrue(Schema::hasTable('gtk_decrees'));
        $this->assertTrue(Schema::hasTable('gtk_decree_recipients'));
        $this->assertTrue(Schema::hasTable('gtk_decree_details'));

        // Check required columns on gtk_decrees
        $this->assertTrue(Schema::hasColumns('gtk_decrees', [
            'id', 'number', 'title', 'type', 'academic_year_id', 'academic_year',
            'issued_date', 'tmt_date', 'valid_from', 'valid_until',
            'signer_name', 'signer_title', 'file_path', 'original_filename',
            'file_size', 'description', 'status', 'activated_at', 'activated_by',
            'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by',
        ]));

        // Check columns on gtk_decree_recipients
        $this->assertTrue(Schema::hasColumns('gtk_decree_recipients', [
            'id', 'gtk_decree_id', 'user_id', 'notes',
        ]));

        // Check columns on gtk_decree_details
        $this->assertTrue(Schema::hasColumns('gtk_decree_details', [
            'id', 'gtk_decree_id', 'gtk_decree_recipient_id', 'user_id',
            'assignment_category', 'subject_id', 'classroom_id',
            'structural_position_id', 'role_id', 'teaching_hours',
            'valid_from', 'valid_until', 'implementation_status', 'link_type',
            'operational_type', 'operational_id', 'teacher_subject_assignment_id',
            'teacher_structural_assignment_id', 'user_role_assignment_id',
            'conflict_notes', 'notes', 'created_by', 'updated_by',
        ]));
    }

    public function test_can_create_decree_with_distinct_dates_and_relations(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/001/SMK/VII/2026',
            'title' => 'SK Pembagian Tugas KBM dan Tugas Tambahan TA 2026/2027',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-06-25',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'valid_until' => '2027-06-30',
            'signer_name' => 'Drs. H. Ahmad Fauzi, M.Pd.',
            'signer_title' => 'Kepala Sekolah',
            'status' => GtkDecree::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('gtk_decrees', [
            'id' => $decree->id,
            'number' => '800/001/SMK/VII/2026',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $this->assertEquals('2026-06-25', $decree->issued_date->format('Y-m-d'));
        $this->assertEquals('2026-07-01', $decree->tmt_date->format('Y-m-d'));
        $this->assertEquals('2026-07-15', $decree->valid_from->format('Y-m-d'));
        $this->assertEquals('2027-06-30', $decree->valid_until->format('Y-m-d'));
        $this->assertEquals($this->academicYear->id, $decree->academicYear->id);
        $this->assertEquals($this->admin->id, $decree->createdBy->id);
    }

    public function test_recipients_can_be_stored_separately_without_operational_assignments(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/002/SMK/VII/2026',
            'title' => 'SK Penerima SK GTK Khusus',
            'type' => GtkDecree::TYPE_PENGANGKATAN,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        // Attach recipient A and recipient B
        $recipientA = GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
            'notes' => 'Penerima tanpa beban mengajar (tugas khusus)',
        ]);

        $recipientB = GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherB->id,
        ]);

        $this->assertCount(2, $decree->recipients);
        $this->assertCount(2, $decree->users);
        $this->assertTrue($decree->users->contains($this->teacherA));
        $this->assertTrue($decree->users->contains($this->teacherB));

        // Operational assignments remain untouched
        $this->assertEquals(0, TeacherSubjectAssignment::count());
        $this->assertEquals(0, TeacherStructuralAssignment::count());
        $this->assertEquals(0, UserRoleAssignment::count());

        // Reverse relation on User
        $this->assertCount(1, $this->teacherA->gtkDecrees);
        $this->assertEquals($decree->id, $this->teacherA->gtkDecrees->first()->id);
    }

    public function test_cannot_duplicate_recipient_on_same_decree(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/003/SMK/VII/2026',
            'title' => 'SK Uji Duplikasi Penerima',
            'type' => GtkDecree::TYPE_LAINNYA,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
        ]);

        $this->expectException(QueryException::class);

        // Attempt duplicate recipient insertion
        GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
        ]);
    }

    public function test_decree_details_support_teaching_structural_and_archive_categories(): void
    {
        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKR 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );
        $subject = Subject::firstOrCreate(
            ['code' => 'MAT-X'],
            ['name' => 'Matematika X', 'category' => 'Umum']
        );
        $position = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KUR'],
            ['name' => 'Waka Kurikulum', 'category' => 'Pimpinan']
        );

        $decree = GtkDecree::create([
            'number' => '800/004/SMK/VII/2026',
            'title' => 'SK Penetapan Butir Penugasan',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'valid_until' => '2027-06-30',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $recipientA = GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
        ]);

        // 1. Detail Mengajar
        $detailMengajar = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'gtk_decree_recipient_id' => $recipientA->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 4,
            'valid_from' => '2026-07-15',
            'valid_until' => '2027-06-30',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        // 2. Detail Struktural
        $detailStruktural = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'gtk_decree_recipient_id' => $recipientA->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $position->id,
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
            'link_type' => GtkDecreeDetail::LINK_TYPE_CREATED,
        ]);

        // 3. Detail Bersifat Arsip (tidak menghasilkan penugasan operasional)
        $detailArsip = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'gtk_decree_recipient_id' => $recipientA->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'implementation_status' => GtkDecreeDetail::STATUS_SKIPPED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_ARCHIVED,
            'notes' => 'Pencatatan riwayat penghargaan masa bakti',
        ]);

        $this->assertCount(3, $decree->details);
        $this->assertCount(3, $recipientA->details);
        $this->assertEquals(GtkDecreeDetail::CATEGORY_MENGAJAR, $detailMengajar->assignment_category);
        $this->assertEquals($subject->id, $detailMengajar->subject->id);
        $this->assertEquals($classroom->id, $detailMengajar->classroom->id);
        $this->assertEquals(4, $detailMengajar->teaching_hours);
        $this->assertEquals(GtkDecreeDetail::CATEGORY_STRUKTURAL, $detailStruktural->assignment_category);
        $this->assertEquals($position->id, $detailStruktural->structuralPosition->id);
        $this->assertEquals(GtkDecreeDetail::CATEGORY_ARSIP, $detailArsip->assignment_category);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_ARCHIVED, $detailArsip->link_type);
    }

    public function test_detail_references_operational_assignment_safely_without_cascade_deletion(): void
    {
        $subject = Subject::firstOrCreate(
            ['code' => 'KIM-X'],
            ['name' => 'Kimia X', 'category' => 'Umum']
        );

        $operationalAssignment = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 3,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/005/SMK/VII/2026',
            'title' => 'SK Tautan Penugasan Eksisting',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $recipient = GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
        ]);

        $detail = GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'gtk_decree_recipient_id' => $recipient->id,
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'implementation_status' => GtkDecreeDetail::STATUS_APPLIED,
            'link_type' => GtkDecreeDetail::LINK_TYPE_LINKED,
            'teacher_subject_assignment_id' => $operationalAssignment->id,
            'operational_type' => TeacherSubjectAssignment::class,
            'operational_id' => $operationalAssignment->id,
        ]);

        // Test safe accessor
        $this->assertNotNull($detail->operational_assignment);
        $this->assertEquals($operationalAssignment->id, $detail->operational_assignment->id);

        // Deleting the decree must NOT delete the operational assignment
        $decree->delete();

        $this->assertDatabaseMissing('gtk_decrees', ['id' => $decree->id]);
        $this->assertDatabaseMissing('gtk_decree_recipients', ['id' => $recipient->id]);
        $this->assertDatabaseMissing('gtk_decree_details', ['id' => $detail->id]);

        // The operational assignment is completely intact
        $this->assertDatabaseHas('teacher_subject_assignments', ['id' => $operationalAssignment->id]);
    }

    public function test_private_file_helper_and_storage_path(): void
    {
        Storage::fake('local');

        $relativePath = 'sk_gtk/sk_resmi_2026.pdf';
        Storage::disk('local')->put($relativePath, '%PDF-1.4 Mock SK GTK Content');

        $decree = GtkDecree::create([
            'number' => '800/006/SMK/VII/2026',
            'title' => 'SK dengan Dokumen Privat',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
            'file_path' => $relativePath,
            'original_filename' => 'SK_Pembagian_Tugas_2026.pdf',
            'file_size' => 10240,
        ]);

        $this->assertTrue($decree->hasPrivateDocument());
        $this->assertNotNull($decree->getPrivateDocumentAbsolutePath());

        // Public disk must NOT have this file (private guarantee)
        Storage::fake('public');
        $this->assertFalse(Storage::disk('public')->exists($relativePath));
    }
}
