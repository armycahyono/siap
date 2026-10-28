<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAssignmentStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );
    }

    private function getOrCreateRole(string $slug, string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name]);
        foreach ($permissions as $pSlug) {
            $perm = Permission::firstOrCreate(['slug' => $pSlug], ['name' => $pSlug, 'group' => 'Test']);
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        return $role;
    }

    private function createAdmin(): User
    {
        $role = $this->getOrCreateRole('super_admin', 'Super Admin', ['manage_master_data']);

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => 'administrator',
        ]);
    }

    /**
     * Test 1: Satu akun dapat memiliki multi-penugasan (Guru Mapel + Wali Kelas + Guru Piket) tanpa duplikasi user.
     */
    public function test_single_account_can_have_multiple_concurrent_assignments(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $waliRole = $this->getOrCreateRole('wali_kelas', 'Wali Kelas');
        $piketRole = $this->getOrCreateRole('guru_piket', 'Petugas Piket');
        $supervisorRole = $this->getOrCreateRole('supervisor', 'Supervisor');

        $user = User::factory()->create([
            'name' => 'Budi Utomo, S.Pd.',
            'nip' => '198501012010011001',
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        $ay = '2026/2027';

        // Add 3 assignments
        $user->assignRole($waliRole, ['academic_year' => $ay]);
        $user->assignRole($piketRole, ['academic_year' => $ay]);
        $user->assignRole($supervisorRole, ['academic_year' => $ay]);

        $this->assertEquals(1, User::where('nip', '198501012010011001')->count());
        $this->assertTrue($user->hasRole('wali_kelas', $ay));
        $this->assertTrue($user->hasRole('guru_piket', $ay));
        $this->assertTrue($user->hasRole('supervisor', $ay));
        $this->assertTrue($user->hasMultipleAssignments($ay));
        $this->assertCount(3, $user->activeRoleAssignments);
    }

    /**
     * Test 2: Guru BK murni tidak dapat mengakses catatan disiplin/tatib atau sebaliknya.
     */
    public function test_pure_guru_bk_cannot_access_discipline_and_vice_versa(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK');
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $guruBk = User::factory()->create(['position' => 'guru']);
        $guruBk->assignRole($bkRole, ['academic_year' => '2026/2027']);

        $timDisiplin = User::factory()->create(['position' => 'guru']);
        $timDisiplin->assignRole($disiplinRole, ['academic_year' => '2026/2027']);

        // Guru BK can operate BK and see counseling notes, but CANNOT manage discipline
        $this->assertTrue($guruBk->canOperateBk('2026/2027'));
        $this->assertTrue($guruBk->canAccessCounselingNotes('2026/2027'));
        $this->assertFalse($guruBk->canManageDiscipline('2026/2027'));

        // Tim Disiplin can manage discipline, but CANNOT operate BK or see counseling notes
        $this->assertTrue($timDisiplin->canManageDiscipline('2026/2027'));
        $this->assertFalse($timDisiplin->canOperateBk('2026/2027'));
        $this->assertFalse($timDisiplin->canAccessCounselingNotes('2026/2027'));
    }

    /**
     * Test 3: Pegawai dengan penugasan ganda (Guru BK + Tim Kedisiplinan) dapat mengakses kedua fungsi.
     */
    public function test_dual_assignment_user_can_access_both_functions_properly(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK');
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $user = User::factory()->create(['position' => 'guru']);
        $user->assignRole($bkRole, ['academic_year' => '2026/2027']);
        $user->assignRole($disiplinRole, ['academic_year' => '2026/2027']);

        $this->assertTrue($user->canOperateBk('2026/2027'));
        $this->assertTrue($user->canAccessCounselingNotes('2026/2027'));
        $this->assertTrue($user->canManageDiscipline('2026/2027'));
    }

    /**
     * Test 4: Penugasan dengan rentang tanggal kedaluwarsa otomatis tidak aktif.
     */
    public function test_expired_assignment_is_automatically_inactive(): void
    {
        $role = $this->getOrCreateRole('supervisor', 'Supervisor');
        $user = User::factory()->create(['position' => 'guru']);

        $assignment = $user->assignRole($role, [
            'academic_year' => '2026/2027',
            'valid_from' => now()->subMonths(6)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
            'status' => true,
        ]);

        $this->assertFalse($assignment->isActive('2026/2027'));
        $this->assertEquals('expired', $assignment->effective_status);
        $this->assertEquals('Kedaluwarsa', $assignment->status_label);
        $this->assertFalse($user->hasRole('supervisor', '2026/2027'));
    }

    /**
     * Test 5: Penugasan ditutup/dicabut tetap tersimpan sebagai riwayat dan tidak aktif.
     */
    public function test_closed_or_revoked_assignment_retains_history_and_is_inactive(): void
    {
        $admin = $this->createAdmin();
        $role = $this->getOrCreateRole('koordinator_kedisiplinan', 'Koordinator Kedisiplinan');
        $user = User::factory()->create(['position' => 'guru']);

        $assignment = $user->assignRole($role, [
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $this->assertTrue($assignment->isActive());

        // Close assignment officially via controller endpoint
        $response = $this->actingAs($admin)->post(route('employee-assignments.close', $assignment->id), [
            'closing_date' => now()->toDateString(),
            'closing_notes' => 'Rotasi semester genap',
        ]);

        $response->assertRedirect();
        $assignment->refresh();

        $this->assertFalse($assignment->status);
        $this->assertFalse($assignment->isActive());
        $this->assertStringContainsString('Rotasi semester genap', $assignment->notes);
        $this->assertEquals('inactive', $assignment->effective_status);
        $this->assertDatabaseHas('user_role_assignments', ['id' => $assignment->id]);
    }

    /**
     * Test 6: Filter tahun ajaran pada penugasan berfungsi tepat.
     */
    public function test_academic_year_filtering_on_assignments_works_accurately(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK');
        $kurikulumRole = $this->getOrCreateRole('waka_kurikulum', 'Waka Kurikulum');
        $user = User::factory()->create(['position' => 'guru']);

        $user->assignRole($bkRole, ['academic_year' => '2025/2026']);
        $user->assignRole($kurikulumRole, ['academic_year' => '2026/2027']);

        // Check 2025/2026
        $this->assertTrue($user->hasRole('guru_bk', '2025/2026'));
        $this->assertFalse($user->hasRole('waka_kurikulum', '2025/2026'));

        // Check 2026/2027
        $this->assertFalse($user->hasRole('guru_bk', '2026/2027'));
        $this->assertTrue($user->hasRole('waka_kurikulum', '2026/2027'));
    }

    /**
     * Test 7: Wali Kelas terikat pada kelas dan tahun ajaran yang sesuai.
     */
    public function test_homeroom_teacher_bound_to_specific_classroom_and_academic_year(): void
    {
        $dept = Department::create(['name' => 'Teknik Komputer', 'code' => 'TKJ']);
        $grade = GradeLevel::create(['name' => 'Tingkat 10', 'level' => 10]);

        $teacher1 = User::factory()->create(['position' => 'guru']);
        $teacher2 = User::factory()->create(['position' => 'guru']);

        $classroomA = Classroom::create([
            'name' => 'X TKJ 1',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $teacher1->id,
            'is_active' => true,
        ]);

        $classroomB = Classroom::create([
            'name' => 'X TKJ 2',
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $teacher2->id,
            'is_active' => true,
        ]);

        $this->assertTrue($teacher1->isHomeroomTeacher($classroomA));
        $this->assertFalse($teacher1->isHomeroomTeacher($classroomB));
        $this->assertTrue($teacher1->isHomeroomTeacher(academicYear: '2026/2027'));
        $this->assertFalse($teacher1->isHomeroomTeacher(academicYear: '2024/2025'));
    }

    /**
     * Test 8: Staf tendik dengan penugasan non-guru (Perpustakaan, Lab, TU, Satpam) tidak memiliki izin guru/akademik.
     */
    public function test_non_teacher_staff_tendik_does_not_have_academic_or_teaching_rights(): void
    {
        $stafRole = $this->getOrCreateRole('staf', 'Staf / Tendik');
        $libRole = $this->getOrCreateRole('petugas_perpustakaan', 'Petugas Perpustakaan');

        $staff = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'pustakawan',
        ]);
        $staff->assignRole($libRole, ['academic_year' => '2026/2027']);

        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isTeacher());
        $this->assertFalse($staff->hasTeachingDuty());
        $this->assertTrue($staff->isLibrarian('2026/2027'));
        $this->assertFalse($staff->canOperateBk('2026/2027'));
        $this->assertFalse($staff->canManageDiscipline('2026/2027'));
    }

    /**
     * Test 9: Super Admin tetap memiliki akses penuh.
     */
    public function test_super_admin_retains_full_system_access(): void
    {
        $admin = $this->createAdmin();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->isPrincipal());
        $this->assertTrue($admin->isHeadmaster());
        $this->assertTrue($admin->canManageCurriculum());
        $this->assertTrue($admin->canOperateBk());
        $this->assertTrue($admin->canAccessCounselingNotes());
        $this->assertTrue($admin->canManageDiscipline());
        $this->assertTrue($admin->canAccessVisitations());
    }

    /**
     * Test 10: Penugasan lintas modul tidak saling tumpang tindih atau merusak permission modul lain.
     */
    public function test_cross_module_assignments_do_not_leak_or_corrupt_other_modules(): void
    {
        $toolmanRole = $this->getOrCreateRole('laboran_toolman', 'Laboran Toolman', ['operate_sarpras']);
        $sarprasUser = User::factory()->create(['position' => 'staf']);
        $sarprasUser->assignRole($toolmanRole, ['academic_year' => '2026/2027']);

        $this->assertTrue($sarprasUser->isLaboranOrToolman('2026/2027'));
        $this->assertTrue($sarprasUser->hasPermission('operate_sarpras', '2026/2027'));

        // Must not have counseling, discipline, or curriculum rights
        $this->assertFalse($sarprasUser->canOperateBk('2026/2027'));
        $this->assertFalse($sarprasUser->canAccessCounselingNotes('2026/2027'));
        $this->assertFalse($sarprasUser->canManageDiscipline('2026/2027'));
        $this->assertFalse($sarprasUser->canManageCurriculum());
    }

    /**
     * Test 11: Employee Assignment Controller Full CRUD & Actions.
     */
    public function test_employee_assignment_controller_endpoints(): void
    {
        $admin = $this->createAdmin();
        $targetUser = User::factory()->create(['position' => 'guru']);
        $role = $this->getOrCreateRole('guru_piket', 'Petugas Piket');

        // 1. Index
        $response = $this->actingAs($admin)->get(route('employee-assignments.index'));
        $response->assertOk();
        $response->assertViewIs('assignments.index');

        // 2. Store
        $response = $this->actingAs($admin)->post(route('employee-assignments.store'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
            'academic_year' => '2026/2027',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addMonths(6)->toDateString(),
            'sk_number' => 'SK/800/2026',
            'notes' => 'Piket hari Senin',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
            'sk_number' => 'SK/800/2026',
        ]);

        $assignment = UserRoleAssignment::where('user_id', $targetUser->id)->first();

        // 3. Update
        $response = $this->actingAs($admin)->put(route('employee-assignments.update', $assignment->id), [
            'role_id' => $role->id,
            'academic_year' => '2026/2027',
            'sk_number' => 'SK/800/2026/REV-1',
            'notes' => 'Piket hari Kamis',
        ]);
        $response->assertRedirect();
        $assignment->refresh();
        $this->assertEquals('SK/800/2026/REV-1', $assignment->sk_number);

        // 4. Toggle
        $response = $this->actingAs($admin)->post(route('employee-assignments.toggle', $assignment->id));
        $response->assertRedirect();
        $assignment->refresh();
        $this->assertFalse($assignment->status);

        // 5. Destroy
        $response = $this->actingAs($admin)->delete(route('employee-assignments.destroy', $assignment->id));
        $response->assertRedirect();
        $this->assertDatabaseMissing('user_role_assignments', ['id' => $assignment->id]);
    }

    /**
     * Test 12: Bulk close assignments for academic year.
     */
    public function test_bulk_close_assignments_for_academic_year(): void
    {
        $admin = $this->createAdmin();
        $user1 = User::factory()->create(['position' => 'guru']);
        $user2 = User::factory()->create(['position' => 'guru']);
        $role = $this->getOrCreateRole('guru_piket', 'Petugas Piket');

        $user1->assignRole($role, ['academic_year' => '2026/2027', 'status' => true]);
        $user2->assignRole($role, ['academic_year' => '2026/2027', 'status' => true]);

        $response = $this->actingAs($admin)->post(route('employee-assignments.bulk-close'), [
            'academic_year' => '2026/2027',
            'closing_date' => now()->toDateString(),
            'closing_notes' => 'Tutup tahun ajaran serentak',
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, UserRoleAssignment::where('academic_year', '2026/2027')->where('status', true)->count());
        $this->assertEquals(2, UserRoleAssignment::where('academic_year', '2026/2027')->where('status', false)->count());
    }

    /**
     * Test 13: Rollover assignments to new academic year.
     */
    public function test_rollover_assignments_to_new_academic_year(): void
    {
        $admin = $this->createAdmin();
        $user1 = User::factory()->create(['position' => 'guru']);
        $role = $this->getOrCreateRole('guru_bk', 'Guru BK');

        $user1->assignRole($role, ['academic_year' => '2026/2027', 'status' => true, 'sk_number' => 'SK/2026/BK']);

        $response = $this->actingAs($admin)->post(route('employee-assignments.rollover'), [
            'source_academic_year' => '2026/2027',
            'target_academic_year' => '2027/2028',
            'new_sk_number' => 'SK/2027/BK-BARU',
            'close_previous' => 1,
        ]);

        $response->assertRedirect();

        // Target year assignment exists and active
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $user1->id,
            'role_id' => $role->id,
            'academic_year' => '2027/2028',
            'status' => true,
            'sk_number' => 'SK/2027/BK-BARU',
        ]);

        // Source year assignment is closed
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $user1->id,
            'role_id' => $role->id,
            'academic_year' => '2026/2027',
            'status' => false,
        ]);
    }

    /**
     * Test 14: Artisan command siap:rollover-assignments.
     */
    public function test_artisan_command_rollover_assignments(): void
    {
        $user = User::factory()->create(['position' => 'guru']);
        $role = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $user->assignRole($role, ['academic_year' => '2026/2027', 'status' => true]);

        $exitCode = $this->artisan('siap:rollover-assignments', [
            'from' => '2026/2027',
            'to' => '2027/2028',
            '--close-previous' => true,
            '--sk' => 'SK/KEDISIPLINAN/2027',
        ])->run();

        $this->assertEquals(0, $exitCode);

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $user->id,
            'academic_year' => '2027/2028',
            'status' => true,
            'sk_number' => 'SK/KEDISIPLINAN/2027',
        ]);
    }
}
