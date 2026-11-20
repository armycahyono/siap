<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $homeroomUser;

    protected User $regularTeacher;

    protected Classroom $myClassroom;

    protected Classroom $otherClassroom;

    protected Student $binaanStudent;

    protected Student $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $activeAy = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
        );
        SchoolSetting::updateOrCreate(['id' => 1], ['academic_year' => '2026/2027']);

        $gradeLevel = GradeLevel::first() ?? GradeLevel::create(['name' => '10', 'level' => 10]);

        $this->homeroomUser = User::factory()->create([
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);

        $this->regularTeacher = User::factory()->create([
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);

        $this->myClassroom = Classroom::create([
            'name' => 'X TKRO 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->homeroomUser->id,
            'is_active' => true,
        ]);

        $this->otherClassroom = Classroom::create([
            'name' => 'X TKJ 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => null,
            'is_active' => true,
        ]);

        $this->binaanStudent = Student::create([
            'name' => 'Budi Binaan',
            'classroom_id' => $this->myClassroom->id,
            'gender' => 'L',
            'nisn' => '1111111111',
            'nis' => '1001',
            'nik' => '3506123456781111',
            'family_card_no' => '3506123456782222',
            'phone_student' => '081200000001',
            'address' => 'Jl. Mawar 1',
            'father_name' => 'Pak Budi',
            'father_phone' => '081200000002',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $this->otherStudent = Student::create([
            'name' => 'Siti Luar',
            'classroom_id' => $this->otherClassroom->id,
            'gender' => 'P',
            'nisn' => '2222222222',
            'nis' => '1002',
            'nik' => '3506123456783333',
            'family_card_no' => '3506123456784444',
            'phone_student' => '081300000001',
            'address' => 'Jl. Melati 2',
            'status' => 'aktif',
            'is_active' => true,
        ]);
    }

    public function test_all_gtk_can_access_mobile_students_directory(): void
    {
        $response = $this->actingAs($this->regularTeacher)->getJson('/api/v1/mobile/tasks/students-directory');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json('data.students');
        $this->assertNotEmpty($data);
    }

    public function test_homeroom_teacher_defaults_to_binaan_students(): void
    {
        $response = $this->actingAs($this->homeroomUser)->getJson('/api/v1/mobile/tasks/students-directory');
        $response->assertStatus(200);

        $students = $response->json('data.students');
        $this->assertCount(1, $students);
        $this->assertEquals('BUDI BINAAN', $students[0]['name']);
        $this->assertTrue($students[0]['is_binaan']);
        $this->assertTrue($students[0]['can_edit']);
        // Data pribadi lengkap tersedia untuk wali kelas binaan
        $this->assertEquals('3506123456781111', $students[0]['nik']);
    }

    public function test_homeroom_teacher_can_view_all_students_when_requested(): void
    {
        $response = $this->actingAs($this->homeroomUser)->getJson('/api/v1/mobile/tasks/students-directory?scope=all');
        $response->assertStatus(200);

        $students = $response->json('data.students');
        $this->assertCount(2, $students);
    }

    public function test_homeroom_teacher_can_update_contact_and_address_of_binaan_student(): void
    {
        $response = $this->actingAs($this->homeroomUser)->postJson("/api/v1/mobile/tasks/students/{$this->binaanStudent->id}/update-contact", [
            'phone_student' => '089999999999',
            'address' => 'Alamat Baru Kras 123',
            'father_name' => 'Pak Budi Baru',
            'father_phone' => '088888888888',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->binaanStudent->refresh();
        $this->assertEquals('089999999999', $this->binaanStudent->phone_student);
        $this->assertEquals('Alamat Baru Kras 123', $this->binaanStudent->address);
        $this->assertEquals('Pak Budi Baru', $this->binaanStudent->father_name);
        $this->assertEquals('088888888888', $this->binaanStudent->father_phone);
    }

    public function test_homeroom_teacher_cannot_update_student_outside_their_binaan(): void
    {
        $response = $this->actingAs($this->homeroomUser)->postJson("/api/v1/mobile/tasks/students/{$this->otherStudent->id}/update-contact", [
            'address' => 'Alamat Salah',
        ]);

        $response->assertStatus(403);
    }

    public function test_regular_teacher_cannot_update_any_student(): void
    {
        $response = $this->actingAs($this->regularTeacher)->postJson("/api/v1/mobile/tasks/students/{$this->binaanStudent->id}/update-contact", [
            'address' => 'Alamat Salah',
        ]);

        $response->assertStatus(403);
    }
}
