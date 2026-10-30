<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BkGroup;
use App\Models\BkService;
use App\Models\BkStudentCareerProfile;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BkGroupAndCareerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $counselor;

    protected Classroom $classroom;

    protected Student $studentA;

    protected Student $studentB;

    protected string $academicYear = '2026/2027';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(['name' => $this->academicYear], [
            'semester' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $setting = SchoolSetting::getSetting();
        $setting->update([
            'academic_year' => $this->academicYear,
            'operational_academic_year' => $this->academicYear,
        ]);

        $bkRole = Role::whereIn('slug', ['guru_bk', 'bk'])->first() ?? Role::create(['name' => 'Guru BK', 'slug' => 'guru_bk']);

        $this->counselor = User::create([
            'name' => 'Guru BK Hebat',
            'username' => 'gurubk',
            'email' => 'gurubk@example.com',
            'password' => bcrypt('password123'),
            'role_id' => $bkRole->id,
            'is_active' => true,
        ]);

        // Beri assignment BK aktif
        UserRoleAssignment::create([
            'user_id' => $this->counselor->id,
            'role_id' => $bkRole->id,
            'academic_year' => $this->academicYear,
            'status' => true,
        ]);

        $dept = Department::first() ?? Department::create(['name' => 'Teknik Komputer', 'code' => 'TKJ']);
        $grade = GradeLevel::first() ?? GradeLevel::create(['name' => 'XII', 'level' => 12]);

        $this->classroom = Classroom::create([
            'name' => 'XII TKJ 1',
            'academic_year' => $this->academicYear,
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        $this->studentA = Student::create([
            'name' => 'Budi Santoso',
            'nisn' => '0011223344',
            'classroom_id' => $this->classroom->id,
            'status' => 'aktif',
        ]);

        $this->studentB = Student::create([
            'name' => 'Siti Nurhaliza',
            'nisn' => '0011223355',
            'classroom_id' => $this->classroom->id,
            'status' => 'aktif',
        ]);

        BkStudentCareerProfile::create([
            'student_id' => $this->studentA->id,
            'academic_year' => $this->academicYear,
            'post_graduation_plan' => 'bekerja',
            'target_industry' => 'Otomotif & Manufaktur',
            'target_job_position' => 'Teknisi Perakitan',
        ]);

        BkStudentCareerProfile::create([
            'student_id' => $this->studentB->id,
            'academic_year' => $this->academicYear,
            'post_graduation_plan' => 'melanjutkan',
            'target_university' => 'Institut Teknologi Bandung',
            'target_study_program' => 'Teknik Informatika',
        ]);
    }

    public function test_counselor_can_create_bk_group_with_students(): void
    {
        $response = $this->actingAs($this->counselor)
            ->post(route('bk.groups.store'), [
                'name' => 'Kelompok Peminatan Astra 2026',
                'category' => 'karir',
                'academic_year' => $this->academicYear,
                'target_goal' => 'Lolos Rekrutmen Astra',
                'description' => 'Bimbingan terfokus tes fisik & psikotes',
                'student_ids' => [$this->studentA->id],
            ]);

        $response->assertRedirect(route('bk.groups.index', ['academic_year' => $this->academicYear]));

        $this->assertDatabaseHas('bk_groups', [
            'name' => 'Kelompok Peminatan Astra 2026',
            'category' => 'karir',
            'academic_year' => $this->academicYear,
            'counselor_id' => $this->counselor->id,
        ]);

        $group = BkGroup::where('name', 'Kelompok Peminatan Astra 2026')->first();
        $this->assertNotNull($group);
        $this->assertTrue($group->students->contains($this->studentA));
    }

    public function test_counselor_can_record_group_counseling_session(): void
    {
        $group = BkGroup::create([
            'name' => 'Kelompok Peminatan Kuliah PTN',
            'category' => 'karir',
            'academic_year' => $this->academicYear,
            'counselor_id' => $this->counselor->id,
        ]);
        $group->students()->attach([$this->studentA->id, $this->studentB->id]);

        $response = $this->actingAs($this->counselor)
            ->post(route('bk.services.store'), [
                'service_mode' => 'kelompok',
                'bk_group_id' => $group->id,
                'academic_year' => $this->academicYear,
                'service_date' => now()->toDateString(),
                'service_type' => 'karier',
                'summary' => 'Sosialisasi SNBP dan Bedah Jurusan Teknik',
                'result' => 'Siswa memahami passing grade dan persiapan portofolio',
                'follow_up_plan' => 'Simulasi pemilihan prodi pekan depan',
                'participant_ids' => [$this->studentA->id, $this->studentB->id],
            ]);

        $service = BkService::where('summary', 'Sosialisasi SNBP dan Bedah Jurusan Teknik')->first();
        $this->assertNotNull($service);
        $response->assertRedirect(route('bk.services.show', $service));

        $this->assertEquals('kelompok', $service->service_mode);
        $this->assertEquals($group->id, $service->bk_group_id);
        $this->assertCount(2, $service->participants);
    }

    public function test_counselor_can_access_career_analytics_dashboard(): void
    {
        $response = $this->actingAs($this->counselor)
            ->get(route('bk.career.analytics', ['academic_year' => $this->academicYear]));

        $response->assertOk()
            ->assertViewIs('bk.career.analytics')
            ->assertSee('Bekerja (DUDI / Industri)')
            ->assertSee('Melanjutkan Kuliah (PTN/PTS)');
    }

    public function test_admin_cannot_assign_classroom_already_assigned_to_another_bk_counselor(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $otherCounselor = User::factory()->create([
            'name' => 'Guru BK Kedua',
            'role_id' => Role::whereIn('slug', ['guru_bk', 'bk'])->value('id'),
            'is_active' => true,
        ]);

        // Kelas XII TKJ 1 sudah di-assign ke $this->counselor
        UserRoleAssignment::create([
            'user_id' => $this->counselor->id,
            'role_id' => Role::whereIn('slug', ['guru_bk', 'bk'])->value('id'),
            'academic_year' => $this->academicYear,
            'context_type' => Classroom::class,
            'context_id' => $this->classroom->id,
            'status' => true,
        ]);

        // Coba assign kelas yang sama ke $otherCounselor
        $response = $this->actingAs($admin)
            ->post(route('teacher-assignments.store-bk'), [
                'teacher_id' => $otherCounselor->id,
                'academic_year' => $this->academicYear,
                'classroom_ids' => [$this->classroom->id],
            ]);

        $response->assertSessionHas('error');
    }

    public function test_admin_sees_locked_classroom_in_assignment_page(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $this->counselor->update(['short_name' => 'Bu Hebat']);

        UserRoleAssignment::create([
            'user_id' => $this->counselor->id,
            'role_id' => Role::whereIn('slug', ['guru_bk', 'bk'])->value('id'),
            'academic_year' => $this->academicYear,
            'context_type' => Classroom::class,
            'context_id' => $this->classroom->id,
            'status' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('teacher-assignments.index', ['tab' => 'walikelas', 'academic_year' => $this->academicYear]));

        $response->assertOk()
            ->assertSee('Dibina: Bu Hebat')
            ->assertSee('disabled');
    }
}
