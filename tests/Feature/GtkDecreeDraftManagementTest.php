<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkDecree;
use App\Models\GtkDecreeDetail;
use App\Models\GtkDecreeRecipient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GtkDecreeDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $wakaKurikulum;

    private User $teacherA;

    private User $teacherB;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $roleWaka = Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum']);
        $permMaster = Permission::firstOrCreate(['slug' => 'manage_master_data'], ['name' => 'Kelola Data Master']);
        $roleWaka->permissions()->syncWithoutDetaching([$permMaster->id]);
        $roleGuru = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->admin = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Utama',
            'username' => 'admin_utama',
        ]);

        $this->wakaKurikulum = User::factory()->create([
            'role_id' => $roleWaka->id,
            'name' => 'Waka Kurikulum',
            'username' => 'waka_kur',
        ]);

        $this->teacherA = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Joko',
            'username' => 'joko_guru',
        ]);

        $this->teacherB = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Rina',
            'username' => 'rina_guru',
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

    public function test_authorized_user_can_view_decree_index_and_metrics(): void
    {
        GtkDecree::create([
            'number' => '800/010/SMK/2026',
            'title' => 'SK Draf Pembagian Tugas',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->admin)->get(route('gtk-decrees.index'));

        $response->assertStatus(200);
        $response->assertSee('Arsip SK GTK');
        $response->assertSee('800/010/SMK/2026');
        $response->assertSee('SK Draf Pembagian Tugas');
    }

    public function test_unauthorized_user_without_permission_cannot_access_create_or_store(): void
    {
        $response = $this->actingAs($this->teacherA)->get(route('gtk-decrees.create'));
        $response->assertStatus(403);

        $responsePost = $this->actingAs($this->teacherA)->post(route('gtk-decrees.store'), [
            'number' => '800/999/SMK/2026',
            'title' => 'SK Liar',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
        ]);
        $responsePost->assertStatus(403);
    }

    public function test_authorized_user_can_create_draft_decree_with_manual_number(): void
    {
        $response = $this->actingAs($this->wakaKurikulum)->post(route('gtk-decrees.store'), [
            'number' => '800/011/SMK/VII/2026',
            'title' => 'SK Pembagian Tugas Guru TA 2026/2027',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'valid_until' => '2027-06-30',
            'signer_name' => 'Dr. H. Subagyo, M.Pd.',
            'signer_title' => 'Kepala Sekolah',
            'description' => 'Konsiderans kurikulum merdeka',
        ]);

        $decree = GtkDecree::where('number', '800/011/SMK/VII/2026')->first();
        $this->assertNotNull($decree);
        $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->status);
        $this->assertEquals($this->wakaKurikulum->id, $decree->created_by);
        $response->assertRedirect(route('gtk-decrees.show', $decree));
    }

    public function test_validation_rules_for_creating_draft_decree(): void
    {
        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.store'), [
            'number' => '', // required
            'title' => '', // required
            'type' => 'invalid_type', // in list
            'issued_date' => 'not-a-date',
            'valid_from' => '2026-07-15',
            'valid_until' => '2026-07-01', // before valid_from
        ]);

        $response->assertSessionHasErrors(['number', 'title', 'type', 'issued_date', 'valid_until']);
    }

    public function test_upload_and_secure_download_and_preview_of_private_pdf(): void
    {
        Storage::fake('local');

        $fakePdf = UploadedFile::fake()->create('sk_pembagian_tugas.pdf', 500, 'application/pdf');

        $this->actingAs($this->admin)->post(route('gtk-decrees.store'), [
            'number' => '800/012/SMK/2026',
            'title' => 'SK dengan Berkas PDF Privat',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'pdf_document' => $fakePdf,
        ]);

        $decree = GtkDecree::where('number', '800/012/SMK/2026')->first();
        $this->assertNotNull($decree->file_path);
        Storage::disk('local')->assertExists($decree->file_path);

        // Test preview route
        $previewResponse = $this->actingAs($this->admin)->get(route('gtk-decrees.document', ['gtkDecree' => $decree, 'preview' => 1]));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'application/pdf');

        // Test download route
        $downloadResponse = $this->actingAs($this->admin)->get(route('gtk-decrees.document', $decree));
        $downloadResponse->assertStatus(200);

        // Unauthorized user cannot download
        $unauthResponse = $this->actingAs($this->teacherA)->get(route('gtk-decrees.document', $decree));
        $unauthResponse->assertStatus(403);
    }

    public function test_edit_draft_decree_and_safe_pdf_replacement(): void
    {
        Storage::fake('local');

        $initialPdf = UploadedFile::fake()->create('old_sk.pdf', 300, 'application/pdf');

        $decree = GtkDecree::create([
            'number' => '800/013/SMK/2026',
            'title' => 'SK Draf Awal',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
            'file_path' => $initialPdf->store('sk_gtk', 'local'),
            'original_filename' => 'old_sk.pdf',
        ]);

        $oldPath = $decree->file_path;
        Storage::disk('local')->assertExists($oldPath);

        // Replace with new PDF
        $newPdf = UploadedFile::fake()->create('new_sk.pdf', 400, 'application/pdf');

        $response = $this->actingAs($this->admin)->put(route('gtk-decrees.update', $decree), [
            'number' => '800/013/SMK/2026-REV',
            'title' => 'SK Draf Revisi Judul',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-02',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'pdf_document' => $newPdf,
        ]);

        $response->assertRedirect(route('gtk-decrees.show', $decree));

        $decree->refresh();
        $this->assertEquals('800/013/SMK/2026-REV', $decree->number);
        $this->assertEquals('SK Draf Revisi Judul', $decree->title);

        // Old file must be cleaned up and new file stored
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($decree->file_path);
        $this->assertEquals('new_sk.pdf', $decree->original_filename);
    }

    public function test_cannot_edit_non_draft_decree(): void
    {
        $activeDecree = GtkDecree::create([
            'number' => '800/014/SMK/2026',
            'title' => 'SK yang Sudah Aktif',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $responseEdit = $this->actingAs($this->admin)->get(route('gtk-decrees.edit', $activeDecree));
        $responseEdit->assertStatus(403);

        $responseUpdate = $this->actingAs($this->admin)->put(route('gtk-decrees.update', $activeDecree), [
            'number' => '800/014/SMK/2026-MOD',
            'title' => 'Judul Berubah',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
        ]);
        $responseUpdate->assertStatus(403);
    }

    public function test_can_add_multiple_recipients_and_prevents_duplicate_in_same_decree(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/015/SMK/2026',
            'title' => 'SK Uji Penerima',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.recipients.store', $decree), [
            'user_ids' => [$this->teacherA->id, $this->teacherB->id],
            'notes' => 'Penerima lampiran satu',
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, $decree->recipients()->count());

        // Re-adding same recipients should safely skip duplicates without throwing SQL error
        $responseDuplicate = $this->actingAs($this->admin)->post(route('gtk-decrees.recipients.store', $decree), [
            'user_ids' => [$this->teacherA->id],
        ]);
        $responseDuplicate->assertRedirect();
        $this->assertEquals(2, $decree->recipients()->count());
    }

    public function test_can_remove_recipient_from_draft(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/016/SMK/2026',
            'title' => 'SK Uji Hapus Penerima',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $recipient = GtkDecreeRecipient::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacherA->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('gtk-decrees.recipients.destroy', ['gtkDecree' => $decree, 'recipient' => $recipient]));
        $response->assertRedirect();
        $this->assertEquals(0, $decree->recipients()->count());
    }

    public function test_mode_a_can_add_teaching_structural_and_archive_details(): void
    {
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer Jaringan']);
        $grade = GradeLevel::firstOrCreate(['level' => 'XI'], ['name' => 'Tingkat XI']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI TKJ 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );
        $subject = Subject::firstOrCreate(['code' => 'TLJ'], ['name' => 'Teknologi Layanan Jaringan']);
        $position = StructuralPosition::firstOrCreate(['code' => 'KA_LAB_KOMP'], ['name' => 'Ka. Lab Komputer']);

        $decree = GtkDecree::create([
            'number' => '800/017/SMK/2026',
            'title' => 'SK Uji Mode A Detail',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'valid_until' => '2027-06-30',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // 1. Tambah Rincian Mengajar (Mode A)
        $this->actingAs($this->admin)->post(route('gtk-decrees.details.store', $decree), [
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 6,
        ]);

        // 2. Tambah Rincian Struktural (Mode A)
        $this->actingAs($this->admin)->post(route('gtk-decrees.details.store', $decree), [
            'user_id' => $this->teacherA->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_STRUKTURAL,
            'structural_position_id' => $position->id,
        ]);

        // 3. Tambah Rincian Arsip (Mode A)
        $this->actingAs($this->admin)->post(route('gtk-decrees.details.store', $decree), [
            'user_id' => $this->teacherB->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_ARSIP,
            'notes' => 'Pencatatan riwayat penugasan arsip masa lalu',
        ]);

        $this->assertEquals(3, $decree->details()->count());

        $archiveDetail = $decree->details()->where('assignment_category', GtkDecreeDetail::CATEGORY_ARSIP)->first();
        $this->assertEquals(GtkDecreeDetail::STATUS_SKIPPED, $archiveDetail->implementation_status);
        $this->assertEquals(GtkDecreeDetail::LINK_TYPE_ARCHIVED, $archiveDetail->link_type);
    }

    public function test_mode_b_can_add_bulk_details(): void
    {
        $dept = Department::firstOrCreate(['code' => 'AKL'], ['name' => 'Akuntansi']);
        $grade = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Tingkat XII']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'XII AKL 1'],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year' => '2026/2027']
        );
        $subjectA = Subject::firstOrCreate(['code' => 'PAJAK'], ['name' => 'Perpajakan']);
        $subjectB = Subject::firstOrCreate(['code' => 'AK-KEU'], ['name' => 'Akuntansi Keuangan']);

        $decree = GtkDecree::create([
            'number' => '800/018/SMK/2026',
            'title' => 'SK Uji Mode B Bulk Details',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-15',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.details.bulk-store', $decree), [
            'bulk_details' => [
                [
                    'user_id' => $this->teacherA->id,
                    'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
                    'subject_id' => $subjectA->id,
                    'classroom_id' => $classroom->id,
                    'teaching_hours' => 4,
                ],
                [
                    'user_id' => $this->teacherB->id,
                    'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
                    'subject_id' => $subjectB->id,
                    'classroom_id' => $classroom->id,
                    'teaching_hours' => 4,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, $decree->details()->count());
        $this->assertEquals(2, $decree->recipients()->count());
    }

    public function test_can_delete_draft_decree_without_affecting_operational_tables(): void
    {
        Storage::fake('local');

        $fakeFile = UploadedFile::fake()->create('sk_draft.pdf', 200, 'application/pdf');
        $storedPath = $fakeFile->store('sk_gtk', 'local');

        $subject = Subject::firstOrCreate(['code' => 'SEJ-X'], ['name' => 'Sejarah X']);
        $operational = TeacherSubjectAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'hours_per_week' => 2,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/019/SMK/2026',
            'title' => 'SK Draf Dihapus',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'file_path' => $storedPath,
            'status' => GtkDecree::STATUS_DRAFT,
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
            'teacher_subject_assignment_id' => $operational->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('gtk-decrees.destroy', $decree));
        $response->assertRedirect(route('gtk-decrees.index'));

        // Decree, recipient, detail deleted
        $this->assertDatabaseMissing('gtk_decrees', ['id' => $decree->id]);
        $this->assertDatabaseMissing('gtk_decree_recipients', ['id' => $recipient->id]);
        $this->assertDatabaseMissing('gtk_decree_details', ['id' => $detail->id]);

        // File deleted from private storage
        Storage::disk('local')->assertMissing($storedPath);

        // Operational record MUST remain untouched
        $this->assertDatabaseHas('teacher_subject_assignments', ['id' => $operational->id]);
    }

    public function test_cannot_delete_non_draft_decree(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/020/SMK/2026',
            'title' => 'SK Aktif Tidak Boleh Dihapus',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('gtk-decrees.destroy', $decree));
        $response->assertStatus(403);

        $this->assertDatabaseHas('gtk_decrees', ['id' => $decree->id]);
    }
}
