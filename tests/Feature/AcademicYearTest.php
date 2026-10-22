<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $regularTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'level' => 1]);
        $teacherRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru Pengampu', 'level' => 4]);

        $this->admin = User::factory()->create([
            'username' => 'admin_academic_test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->regularTeacher = User::factory()->create([
            'username' => 'guru_academic_test',
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_academic_years_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('academic-years.index'));

        $response->assertStatus(200);
        $response->assertSee('Tahun Pelajaran');
    }

    public function test_admin_can_create_academic_year(): void
    {
        $response = $this->actingAs($this->admin)->post(route('academic-years.store'), [
            'name' => '2029/2030',
            'semester' => 'Ganjil',
            'start_date' => '2029-07-16',
            'end_date' => '2030-06-21',
            'description' => 'Tahun Pelajaran Masa Depan',
        ]);

        $response->assertRedirect(route('academic-years.index'));
        $this->assertDatabaseHas('academic_years', [
            'name' => '2029/2030',
            'semester' => 'Ganjil',
        ]);
    }

    public function test_admin_can_set_active_academic_year(): void
    {
        $year = AcademicYear::firstOrCreate(['name' => '2028/2029'], [
            'semester' => 'Genap',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('academic-years.set-active', $year));

        $response->assertRedirect(route('academic-years.index'));
        $this->assertTrue($year->fresh()->is_active);
    }

    public function test_admin_can_toggle_lock_academic_year(): void
    {
        $year = AcademicYear::firstOrCreate(['name' => '2023/2024'], [
            'semester' => 'Genap',
            'is_active' => false,
            'is_locked' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('academic-years.toggle-lock', $year));

        $response->assertRedirect(route('academic-years.index'));
        $this->assertTrue($year->fresh()->is_locked);
    }

    public function test_all_users_can_view_school_profile(): void
    {
        $response = $this->actingAs($this->regularTeacher)->get(route('settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Profil Sekolah');
        $response->assertSee('1. Identitas Sekolah');
        $response->assertSee('2. Legalitas dan Status Sekolah');
        $response->assertSee('3. Akreditasi Sekolah');
        $response->assertSee('4. Visi, Misi dan Tujuan Sekolah');
    }

    public function test_all_users_can_view_teachers_and_students(): void
    {
        $teacherResponse = $this->actingAs($this->regularTeacher)->get(route('teachers.index'));
        $teacherResponse->assertStatus(200);

        $studentResponse = $this->actingAs($this->regularTeacher)->get(route('students.index'));
        $studentResponse->assertStatus(200);
    }
}
