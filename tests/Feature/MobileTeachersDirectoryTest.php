<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTeachersDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $principal;

    protected User $vicePrincipal;

    protected User $regularTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        AcademicYear::where('id', '!=', $ay->id)->update(['is_active' => false]);

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator']);
        $ksRole = Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah']);
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Test',
            'username' => 'admin_test',
            'email' => 'admin_test@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->principal = User::create([
            'name' => 'Kepala Sekolah Test',
            'username' => 'ks_test',
            'email' => 'ks_test@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $ksRole->id,
            'is_active' => true,
        ]);

        $this->vicePrincipal = User::create([
            'name' => 'Waka Kurikulum Test',
            'username' => 'waka_test',
            'email' => 'waka_test@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $wakaRole->id,
            'is_active' => true,
        ]);

        $this->regularTeacher = User::create([
            'name' => 'Guru Biasa Test',
            'username' => 'guru_biasa',
            'email' => 'guru_biasa@test.sch.id',
            'password' => bcrypt('password'),
            'nigs' => '19850101',
            'phone' => '081234567890',
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_cannot_access_teachers_directory(): void
    {
        $response = $this->getJson('/api/v1/mobile/tasks/teachers-directory');
        $response->assertStatus(401);
    }

    public function test_regular_teacher_can_access_teachers_directory(): void
    {
        $response = $this->actingAs($this->regularTeacher)->getJson('/api/v1/mobile/tasks/teachers-directory');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_principal_can_access_teachers_directory(): void
    {
        $response = $this->actingAs($this->principal)->getJson('/api/v1/mobile/tasks/teachers-directory');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $teachers = $response->json('data.teachers');
        $this->assertNotEmpty($teachers);

        $target = collect($teachers)->firstWhere('username', 'guru_biasa');
        $this->assertNotNull($target);
        $this->assertEquals('Guru Biasa Test', $target['name']);
        $this->assertEquals('19850101', $target['nigs']);
        $this->assertEquals('081234567890', $target['phone']);
        $this->assertStringContainsString('https://wa.me/6281234567890', $target['wa_url']);
    }

    public function test_vice_principal_can_access_teachers_directory(): void
    {
        $response = $this->actingAs($this->vicePrincipal)->getJson('/api/v1/mobile/tasks/teachers-directory');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_super_admin_can_access_teachers_directory(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/mobile/tasks/teachers-directory');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_search_teachers_directory(): void
    {
        $response = $this->actingAs($this->principal)->getJson('/api/v1/mobile/tasks/teachers-directory?search=19850101');
        $response->assertStatus(200);

        $teachers = $response->json('data.teachers');
        $this->assertCount(1, $teachers);
        $this->assertEquals('guru_biasa', $teachers[0]['username']);
    }
}
