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
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Services\GtkDecreeActivationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkDecreeActivationUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private User $teacher;

    private AcademicYear $academicYear;

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
            'username' => 'admin_sk',
        ]);

        $this->regularUser = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Tanpa Izin SK',
            'username' => 'guru_no_perm',
        ]);

        $this->teacher = User::factory()->create([
            'role_id' => $roleGuru->id,
            'name' => 'Guru Matpel Dian',
            'username' => 'dian_guru',
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

    /**
     * 1. Tombol aktivasi dan modal konfirmasi tersedia bagi pengguna berwenang pada SK draft.
     */
    public function test_activation_button_and_modal_are_rendered_for_authorized_users_on_draft(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/UI-001/2026',
            'title' => 'SK Draf Uji UI',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'TIK-X'], ['name' => 'TIK X']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacher->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Cek pada halaman detail SK (show)
        $responseShow = $this->actingAs($this->admin)->get(route('gtk-decrees.show', $decree));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Aktifkan SK');
        $responseShow->assertSee('modalActivateDecree');
        $responseShow->assertSee(route('gtk-decrees.activate', $decree));

        // Cek pada halaman pratinjau dampak (preview)
        $responsePreview = $this->actingAs($this->admin)->get(route('gtk-decrees.preview', $decree));
        $responsePreview->assertStatus(200);
        $responsePreview->assertSee('Terapkan &amp; Aktifkan Penugasan', false);
        $responsePreview->assertSee('modalActivateDecree');
    }

    /**
     * 2. Pengguna tanpa izin tidak dapat melihat tombol aktivasi dan ditolak jika mengakses endpoint.
     */
    public function test_unauthorized_user_cannot_see_activation_button_and_is_forbidden_on_post(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/UI-002/2026',
            'title' => 'SK Uji Otorisasi',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'MTK-UI'], ['name' => 'Matematika UI']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacher->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Pengguna biasa mencoba akses langsung ke endpoint draf / POST aktivasi: 403 Forbidden
        $responseShow = $this->actingAs($this->regularUser)->get(route('gtk-decrees.show', $decree));
        $responseShow->assertStatus(403);

        // Pengguna biasa mencoba POST aktivasi langsung: 403 Forbidden
        $responsePost = $this->actingAs($this->regularUser)->post(route('gtk-decrees.activate', $decree));
        $responsePost->assertStatus(403);
    }

    /**
     * 3. SK yang sudah aktif tidak menampilkan tombol aktivasi dan tidak bisa diaktifkan ulang.
     */
    public function test_active_decree_does_not_render_activation_button_and_rejects_post(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/UI-003/2026',
            'title' => 'SK Sudah Aktif',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by' => $this->admin->id,
        ]);

        $responseShow = $this->actingAs($this->admin)->get(route('gtk-decrees.show', $decree));
        $responseShow->assertStatus(200);
        $responseShow->assertDontSee('modalActivateDecree');

        // Request POST aktivasi ulang ditolak
        $responsePost = $this->actingAs($this->admin)->post(route('gtk-decrees.activate', $decree));
        $responsePost->assertRedirect(route('gtk-decrees.preview', $decree));
        $responsePost->assertSessionHas('error');
    }

    /**
     * 4. Aktivasi berhasil dan pengguna mendapat notifikasi sukses dengan ringkasan lengkap.
     */
    public function test_successful_activation_redirects_with_detailed_success_message(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/UI-004/2026',
            'title' => 'SK Sukses Aktivasi',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'BIO-UI'], ['name' => 'Biologi UI']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT UI', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacher->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 3,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.activate', $decree));

        $response->assertRedirect(route('gtk-decrees.show', $decree));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('berhasil diaktifkan', session('success'));
        $this->assertStringContainsString('1 penugasan baru dibuat', session('success'));

        $this->assertEquals(GtkDecree::STATUS_ACTIVE, $decree->fresh()->status);
    }

    /**
     * 5. Konflik baru saat eksekusi aktivasi membatalkan transaksi dan mengembalikan error ramah.
     */
    public function test_conflict_during_activation_aborts_and_redirects_with_error(): void
    {
        $subject = Subject::firstOrCreate(['code' => 'KIM-UI'], ['name' => 'Kimia UI']);
        $dept = Department::firstOrCreate(['code' => 'TJKT'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['level' => 'X'], ['name' => 'Tingkat X']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TJKT UI 2', 'academic_year' => $this->academicYear->name],
            ['grade_level_id' => $grade->id, 'department_id' => $dept->id, 'academic_year_id' => $this->academicYear->id]
        );

        // Buat penugasan operasional oleh guru lain (konflik)
        $otherTeacher = User::factory()->create(['name' => 'Guru Saingan']);
        TeacherSubjectAssignment::create([
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_year' => $this->academicYear->name,
            'hours_per_week' => 2,
        ]);

        $decree = GtkDecree::create([
            'number' => '800/UI-005/2026',
            'title' => 'SK Konflik UI',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'academic_year_id' => $this->academicYear->id,
            'academic_year' => $this->academicYear->name,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacher->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teaching_hours' => 2,
            'valid_from' => '2026-07-01',
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.activate', $decree));

        $response->assertRedirect(route('gtk-decrees.preview', $decree));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('konflik', strtolower(session('error')));

        // Status SK tetap draf
        $this->assertEquals(GtkDecree::STATUS_DRAFT, $decree->fresh()->status);
    }

    /**
     * 6. SK dengan tanggal efektif di masa depan menampilkan pesan yang jelas dan ditolak.
     */
    public function test_future_effective_date_decree_returns_clear_explanation(): void
    {
        $futureDate = Carbon::now()->addMonths(1)->format('Y-m-d');

        $decree = GtkDecree::create([
            'number' => '800/UI-006/2026',
            'title' => 'SK Masa Depan UI',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => now()->toDateString(),
            'tmt_date' => $futureDate,
            'valid_from' => $futureDate,
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        $subject = Subject::firstOrCreate(['code' => 'FIS-UI'], ['name' => 'Fisika UI']);
        GtkDecreeDetail::create([
            'gtk_decree_id' => $decree->id,
            'user_id' => $this->teacher->id,
            'assignment_category' => GtkDecreeDetail::CATEGORY_MENGAJAR,
            'subject_id' => $subject->id,
            'valid_from' => $futureDate,
            'implementation_status' => GtkDecreeDetail::STATUS_PENDING,
        ]);

        // Pada halaman detail, modal menampilkan alert tanggal efektif belum tiba
        $responseShow = $this->actingAs($this->admin)->get(route('gtk-decrees.show', $decree));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Tanggal Efektif Belum Tiba');

        // Request POST aktivasi ditolak dengan pesan yang jelas
        $responsePost = $this->actingAs($this->admin)->post(route('gtk-decrees.activate', $decree));
        $responsePost->assertRedirect(route('gtk-decrees.preview', $decree));
        $responsePost->assertSessionHas('error');
        $this->assertStringContainsString('masa depan', session('error'));
    }

    /**
     * 7. Kesalahan sistem tak terduga tidak mengekspos detail teknis / SQL kepada pengguna.
     */
    public function test_unexpected_exception_masks_internal_details(): void
    {
        $decree = GtkDecree::create([
            'number' => '800/UI-007/2026',
            'title' => 'SK Uji Error Handler',
            'type' => GtkDecree::TYPE_PEMBAGIAN_TUGAS,
            'issued_date' => '2026-07-01',
            'tmt_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'status' => GtkDecree::STATUS_DRAFT,
        ]);

        // Mocking service agar melempar unexpected PDOException / RuntimeException
        $this->mock(GtkDecreeActivationService::class, function ($mock) {
            $mock->shouldReceive('activate')
                ->andThrow(new \RuntimeException('Sensitive DB query failed: SELECT * FROM secret_passwords'));
        });

        $response = $this->actingAs($this->admin)->post(route('gtk-decrees.activate', $decree));

        $response->assertRedirect(route('gtk-decrees.preview', $decree));
        $response->assertSessionHas('error');

        // Pesan error tidak memuat SQL rahasia atau keyword sensitive
        $this->assertStringNotContainsString('secret_passwords', session('error'));
        $this->assertStringNotContainsString('SELECT * FROM', session('error'));
        $this->assertStringContainsString('kesalahan teknis', strtolower(session('error')));
    }
}
