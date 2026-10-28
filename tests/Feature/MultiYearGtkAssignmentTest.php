<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiYearGtkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $ay2024;

    protected AcademicYear $ay2025;

    protected AcademicYear $ay2026;

    protected AcademicYear $ay2027;

    protected User $admin;

    protected Role $guruRole;

    protected Role $bkRole;

    protected Role $kedisiplinanRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ay2024 = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            ['semester' => 'Ganjil', 'is_active' => false, 'start_date' => '2024-07-01', 'end_date' => '2025-06-30']
        );

        $this->ay2025 = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['semester' => 'Ganjil', 'is_active' => false, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
        );

        $this->ay2026 = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
        );

        $this->ay2027 = AcademicYear::firstOrCreate(
            ['name' => '2027/2028'],
            ['semester' => 'Ganjil', 'is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
        );

        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'description' => 'Super Admin']);
        $this->guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru', 'description' => 'Guru']);
        Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah', 'description' => 'Kepala Sekolah']);
        Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum', 'description' => 'Waka Kurikulum']);
        $this->bkRole = Role::firstOrCreate(['slug' => 'guru_bk'], ['name' => 'Guru BK', 'description' => 'Guru BK']);
        $this->kedisiplinanRole = Role::firstOrCreate(['slug' => 'tim_kedisiplinan'], ['name' => 'Tim Kedisiplinan', 'description' => 'Tim Kedisiplinan']);

        $this->admin = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1. SK struktural yang masih berlaku tetap dikenali pada tahun ajaran berikutnya (overlapping dates).
     */
    public function test_multi_year_structural_assignment_remains_active_across_academic_years(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        $ksPosition = StructuralPosition::updateOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'default_tenure_years' => 4,
                'is_active' => true,
                'order' => 1,
            ]
        );

        // Buat penugasan di tahun 2024/2025 dengan masa berlaku 4 tahun (2024-07-01 s.d. 2028-06-30)
        $assignment = TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $ksPosition->id,
            'academic_year' => '2024/2025',
            'sk_number' => 'SK-KS-2024-001',
            'sk_date' => '2024-07-01',
            'valid_from' => '2024-07-01',
            'valid_until' => '2028-06-30',
            'is_active' => true,
        ]);

        // Verifikasi scopeActiveForAcademicYear murni berbasis irisan tanggal
        $this->assertTrue(TeacherStructuralAssignment::where('id', $assignment->id)->activeForAcademicYear('2024/2025')->exists());
        $this->assertTrue(TeacherStructuralAssignment::where('id', $assignment->id)->activeForAcademicYear('2025/2026')->exists());
        $this->assertTrue(TeacherStructuralAssignment::where('id', $assignment->id)->activeForAcademicYear('2026/2027')->exists());
        $this->assertTrue(TeacherStructuralAssignment::where('id', $assignment->id)->activeForAcademicYear('2027/2028')->exists());
        $this->assertFalse(TeacherStructuralAssignment::where('id', $assignment->id)->activeForAcademicYear('2028/2029')->exists());

        // Verifikasi User authorization method: isPrincipal()
        $this->assertTrue($teacher->isPrincipal('2024/2025'));
        $this->assertTrue($teacher->isPrincipal('2025/2026'));
        $this->assertTrue($teacher->isPrincipal('2026/2027'));
        $this->assertFalse($teacher->isPrincipal('2028/2029'));
    }

    /**
     * 2. SK yang sudah kedaluwarsa tidak memberikan kewenangan.
     */
    public function test_expired_assignment_does_not_grant_authority(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        $ksPos = StructuralPosition::updateOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'default_tenure_years' => 1,
                'is_active' => true,
                'order' => 1,
            ]
        );

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $ksPos->id,
            'academic_year' => '2024/2025',
            'sk_number' => 'SK-KS-EXPIRED',
            'sk_date' => '2024-07-01',
            'valid_from' => '2024-07-01',
            'valid_until' => '2025-06-30', // Berakhir di akhir 2024/2025
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isPrincipal('2024/2025'));
        $this->assertFalse($teacher->isPrincipal('2025/2026'), 'SK yang telah kedaluwarsa tidak boleh memberikan kewenangan pada 2025/2026');
        $this->assertFalse($teacher->isPrincipal('2026/2027'));
    }

    /**
     * 3. Pergantian pejabat tidak menghapus riwayat pejabat lama.
     */
    public function test_single_holder_position_replacement_deactivates_and_preserves_history(): void
    {
        $teacherOld = User::factory()->create(['name' => 'Pejabat Lama', 'role_id' => $this->guruRole->id]);
        $teacherNew = User::factory()->create(['name' => 'Pejabat Baru', 'role_id' => $this->guruRole->id]);

        $ksPos = StructuralPosition::updateOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'default_tenure_years' => 4,
                'is_active' => true,
                'order' => 1,
            ]
        );

        $oldAsgn = TeacherStructuralAssignment::create([
            'teacher_id' => $teacherOld->id,
            'structural_position_id' => $ksPos->id,
            'academic_year' => '2024/2025',
            'sk_number' => 'SK-OLD-KS',
            'sk_date' => '2024-07-01',
            'valid_from' => '2024-07-01',
            'valid_until' => '2028-06-30',
            'is_active' => true,
        ]);

        // Pada tahun 2026/2027, terjadi pergantian pejabat baru
        $response = $this->actingAs($this->admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacherNew->id,
            'structural_position_id' => $ksPos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-NEW-KS',
            'sk_date' => '2026-07-01',
            'valid_from' => '2026-07-01',
            'valid_until' => '2030-06-30',
        ]);

        $response->assertRedirect();

        $oldAsgn->refresh();
        $newAsgn = TeacherStructuralAssignment::where('teacher_id', $teacherNew->id)->first();

        // Riwayat lama tetap ada di database, tidak dihapus
        $this->assertDatabaseHas('teacher_structural_assignments', [
            'id' => $oldAsgn->id,
            'sk_number' => 'SK-OLD-KS',
            'is_active' => false,
        ]);

        // Pejabat baru aktif
        $this->assertNotNull($newAsgn);
        $this->assertTrue($newAsgn->is_active);

        // Otorisasi tahun 2026/2027: hanya pejabat baru yang diakui
        $this->assertFalse($teacherOld->isPrincipal('2026/2027'));
        $this->assertTrue($teacherNew->isPrincipal('2026/2027'));
    }

    /**
     * 4. Penugasan tahunan/tanpa valid_until tidak otomatis aktif pada tahun ajaran berikutnya.
     */
    public function test_assignment_without_valid_until_does_not_extend_to_next_year(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        $wakaPos = StructuralPosition::updateOrCreate(
            ['code' => 'WAKA_KURIKULUM'],
            [
                'name' => 'Wakil Kepala Sekolah Bidang Kurikulum',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'default_tenure_years' => 3,
                'is_active' => true,
                'order' => 2,
            ]
        );

        // Operator menyimpan tanpa mengisi valid_until (tanggal akhir belum diketahui / SK tahunan)
        $response = $this->actingAs($this->admin)->post(route('teacher-assignments.store-structural'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $wakaPos->id,
            'academic_year' => '2024/2025',
            'sk_number' => 'SK-WAKA-TAHUNAN',
            'sk_date' => '2024-07-01',
            'valid_from' => '2024-07-01',
            'valid_until' => null, // Dikosongkan
        ]);

        $response->assertRedirect();

        $asgn = TeacherStructuralAssignment::where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($asgn);
        $this->assertNull($asgn->valid_until, 'valid_until harus tetap null jika tidak diisi operator');

        // Harus aktif pada tahun asalnya (2024/2025)
        $this->assertTrue(TeacherStructuralAssignment::where('id', $asgn->id)->activeForAcademicYear('2024/2025')->exists());

        // TIDAK BOLEH otomatis aktif pada tahun berikutnya (2025/2026)
        $this->assertFalse(
            TeacherStructuralAssignment::where('id', $asgn->id)->activeForAcademicYear('2025/2026')->exists(),
            'Penugasan tanpa valid_until tidak boleh otomatis aktif lintas tahun'
        );
        $this->assertFalse($teacher->isWakaKurikulum('2025/2026'));
    }

    /**
     * 5. Penugasan BK dan Tim Kedisiplinan tetap independen.
     */
    public function test_bk_and_discipline_assignments_remain_independent(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        // 1. Penugasan BK melalui storeBkAssignment
        $this->actingAs($this->admin)->post(route('teacher-assignments.store-bk'), [
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-BK-2026',
            'sk_date' => '2026-07-01',
        ]);

        // 2. Penugasan Tim Kedisiplinan melalui storeOperationalAssignment
        $disciplinePos = StructuralPosition::updateOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            [
                'name' => 'Tim Kedisiplinan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'default_tenure_years' => 1,
                'is_active' => true,
                'order' => 10,
            ]
        );

        $this->actingAs($this->admin)->post(route('teacher-assignments.store-operational'), [
            'teacher_id' => $teacher->id,
            'structural_position_id' => $disciplinePos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-DISC-2026',
            'sk_date' => '2026-07-01',
        ]);

        $this->assertTrue($teacher->isCounselingBK('2026/2027'), 'Harus berwenang sebagai Guru BK');
        $this->assertTrue($teacher->isDisciplineTeam('2026/2027'), 'Harus berwenang sebagai Tim Kedisiplinan');
    }

    /**
     * 6. Pencabutan/deaktivasi satu tugas tidak mencabut kewenangan dari tugas lain yang masih aktif.
     */
    public function test_deactivation_of_one_assignment_does_not_revoke_other_active_assignments(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        // Posisi 1: Tim Kedisiplinan
        $disciplinePos = StructuralPosition::updateOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            [
                'name' => 'Tim Kedisiplinan',
                'type' => StructuralPosition::TYPE_OPERASIONAL,
                'allows_multiple' => true,
                'is_active' => true,
            ]
        );
        $opAsgn = TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $disciplinePos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-DISC',
            'is_active' => true,
        ]);
        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $this->kedisiplinanRole->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Posisi 2: Guru BK
        $bkAsgn = UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $this->bkRole->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        // Hentikan penugasan Tim Kedisiplinan
        $response = $this->actingAs($this->admin)->post(route('teacher-assignments.deactivate-operational', $opAsgn));
        $response->assertRedirect();

        $opAsgn->refresh();
        $this->assertFalse($opAsgn->is_active);

        // Otorisasi Tim Kedisiplinan dicabut, tetapi Guru BK HARUS TETAP AKTIF
        $this->assertFalse($teacher->isDisciplineTeam('2026/2027'));
        $this->assertTrue($teacher->isCounselingBK('2026/2027'), 'Kewenangan Guru BK tidak boleh terpengaruh');
    }

    /**
     * 7. Penugasan wali kelas dan piket tidak mengalami regresi.
     */
    public function test_homeroom_and_piket_remain_independent_and_regression_free(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->guruRole->id]);

        $dept = Department::firstOrCreate(['code' => 'RPL'], ['name' => 'Rekayasa Perangkat Lunak']);
        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);

        $classroom = Classroom::create([
            'name' => 'XII RPL 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->ay2026->id,
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        PiketSchedule::create([
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'day' => 'Senin',
            'is_active' => true,
        ]);

        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->piketSchedules()->where('academic_year', '2026/2027')->where('is_active', true)->exists());
    }

    /**
     * 8. Data lama yang masa berlakunya belum diketahui ditangani secara eksplisit dan aman.
     */
    public function test_legacy_data_without_valid_until_is_handled_safely(): void
    {
        $teacher = User::factory()->create(['name' => 'Guru Pejabat Lama', 'role_id' => $this->guruRole->id]);

        $ksPos = StructuralPosition::updateOrCreate(
            ['code' => 'KS'],
            [
                'name' => 'Kepala Sekolah',
                'type' => StructuralPosition::TYPE_STRUKTURAL,
                'allows_multiple' => false,
                'is_active' => true,
            ]
        );

        // Data legacy: valid_from dan valid_until adalah null
        $legacy = TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $ksPos->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK-LEGACY-001',
            'sk_date' => '2026-05-31',
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ]);

        // Buka halaman index 2026/2027
        $response = $this->actingAs($this->admin)->get(route('teacher-assignments.index', [
            'tab' => 'struktural',
            'academic_year' => '2026/2027',
        ]));

        $response->assertOk();
        $response->assertSee('Guru Pejabat Lama');
        // Harus muncul peringatan verifikasi masa
        $response->assertSee('Perlu Verifikasi Masa');
        $response->assertSee('Belum Verifikasi Akhir');

        // Pastikan TIDAK bocor ke tahun ajaran berikutnya (2027/2028)
        $this->assertFalse(
            TeacherStructuralAssignment::where('id', $legacy->id)->activeForAcademicYear('2027/2028')->exists(),
            'Data legacy tanpa tanggal akhir tidak boleh bocor ke tahun ajaran baru'
        );
        $this->assertFalse($teacher->isPrincipal('2027/2028'));
    }
}
