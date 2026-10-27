<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $teacher;

    protected Role $superAdminRole;

    protected Role $guruRole;

    protected Role $waliKelasRole;

    protected Role $kurikulumRole;

    protected Classroom $classA;

    protected Classroom $classB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Akses penuh', 'is_system' => true]
        );

        $this->guruRole = Role::firstOrCreate(
            ['slug' => 'guru'],
            ['name' => 'Guru', 'description' => 'Dewan Guru', 'is_system' => true]
        );

        $this->waliKelasRole = Role::firstOrCreate(
            ['slug' => 'wali_kelas'],
            ['name' => 'Wali Kelas', 'description' => 'Wali Kelas', 'is_system' => false]
        );

        $this->kurikulumRole = Role::firstOrCreate(
            ['slug' => 'waka_kurikulum'],
            ['name' => 'Waka Kurikulum', 'description' => 'Waka Kurikulum', 'is_system' => false]
        );

        $permManageHomeroom = Permission::firstOrCreate(
            ['slug' => 'manage_homeroom'],
            ['name' => 'Kelola Rombel', 'group' => 'Akademik']
        );
        $this->waliKelasRole->permissions()->syncWithoutDetaching([$permManageHomeroom->id]);

        $permOperateKur = Permission::firstOrCreate(
            ['slug' => 'operate_curriculum'],
            ['name' => 'Operasi Kurikulum', 'group' => 'Kurikulum']
        );
        $this->kurikulumRole->permissions()->syncWithoutDetaching([$permOperateKur->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Test',
            'username' => 'superadmin_test',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $this->superAdminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Guru Pengajar Test',
            'username' => 'guru_test',
            'email' => 'guru@test.com',
            'password' => Hash::make('password'),
            'role_id' => $this->guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);

        $this->classA = Classroom::create([
            'name' => 'X TKR 1',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
        ]);

        $this->classB = Classroom::create([
            'name' => 'X TKR 2',
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
        ]);
    }

    public function test_super_admin_can_assign_contextual_role_to_teacher(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.assignments.store', $this->teacher), [
                'role_id' => $this->waliKelasRole->id,
                'academic_year' => '2026/2027',
                'context_type' => Classroom::class,
                'context_id' => $this->classA->id,
                'notes' => 'Wali Kelas X TKR 1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $this->teacher->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->classA->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $this->teacher->refresh();
        $this->assertTrue($this->teacher->hasRole('wali_kelas'));
        $this->assertTrue($this->teacher->hasRole('wali_kelas', $this->classA));
        $this->assertFalse($this->teacher->hasRole('wali_kelas', $this->classB));

        $this->assertTrue($this->teacher->hasPermission('manage_homeroom', $this->classA));
        $this->assertFalse($this->teacher->hasPermission('manage_homeroom', $this->classB));
    }

    public function test_inactive_role_assignment_is_ignored(): void
    {
        $assignment = UserRoleAssignment::create([
            'user_id' => $this->teacher->id,
            'role_id' => $this->kurikulumRole->id,
            'academic_year' => '2026/2027',
            'status' => false, // Inactive
        ]);

        $this->teacher->refresh();
        $this->assertFalse($this->teacher->hasRole('waka_kurikulum'));
        $this->assertFalse($this->teacher->hasPermission('operate_curriculum'));

        // Toggle to active
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.assignments.toggle', $assignment));

        $response->assertSessionHas('success');
        $this->teacher->refresh();
        $this->assertTrue($this->teacher->hasRole('waka_kurikulum'));
        $this->assertTrue($this->teacher->hasPermission('operate_curriculum'));
    }

    public function test_expired_role_assignment_is_ignored(): void
    {
        UserRoleAssignment::create([
            'user_id' => $this->teacher->id,
            'role_id' => $this->kurikulumRole->id,
            'academic_year' => '2026/2027',
            'valid_from' => now()->subMonths(6)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(), // Expired yesterday
            'status' => true,
        ]);

        $this->teacher->refresh();
        $this->assertFalse($this->teacher->hasRole('waka_kurikulum'));
    }

    public function test_super_admin_can_delete_role_assignment(): void
    {
        $assignment = UserRoleAssignment::create([
            'user_id' => $this->teacher->id,
            'role_id' => $this->waliKelasRole->id,
            'context_type' => Classroom::class,
            'context_id' => $this->classA->id,
            'academic_year' => '2026/2027',
            'status' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('users.assignments.destroy', $assignment));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('user_role_assignments', ['id' => $assignment->id]);

        $this->teacher->refresh();
        $this->assertFalse($this->teacher->hasRole('wali_kelas'));
    }
}
