<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileHomeroomPermitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $homeroomTeacher;

    protected User $otherTeacher;

    protected Classroom $classroom;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        AcademicYear::where('id', '!=', $ay->id)->update(['is_active' => false]);

        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->homeroomTeacher = User::create([
            'name' => 'Wali Kelas Perm Test',
            'username' => 'walikelaspermtest',
            'email' => 'walikperm@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $this->otherTeacher = User::create([
            'name' => 'Guru Lain Perm Test',
            'username' => 'gurulainpermtest',
            'email' => 'gurulainperm@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['level' => 'XI'], ['name' => 'Kelas XI']);

        $this->classroom = Classroom::create([
            'name' => 'XI RPL 2',
            'academic_year' => '2026/2027',
            'academic_year_id' => $ay->id,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $this->homeroomTeacher->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Dina Siswa Test',
            'nis' => '67890',
            'nisn' => '0098761234',
            'gender' => 'P',
            'is_active' => true,
            'phone_parent' => '085566778899',
        ]);

        StudentClassroom::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    public function test_homeroom_teacher_can_fetch_student_permits(): void
    {
        $response = $this->actingAs($this->homeroomTeacher)
            ->getJson("/api/v1/mobile/tasks/homeroom/permits?classroom_id={$this->classroom->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'can_manage' => true,
                    'selected_classroom' => [
                        'id' => $this->classroom->id,
                        'name' => 'XI RPL 2',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'my_classrooms',
                    'selected_classroom',
                    'students',
                    'permits',
                ],
            ]);
    }

    public function test_homeroom_teacher_can_store_student_permit(): void
    {
        $todayStr = Carbon::today()->toDateString();

        $payload = [
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'permit_category' => 'sakit',
            'permit_date' => $todayStr,
            'reason' => 'Sakit demam dan batuk pilek, istirahat di rumah.',
        ];

        $response = $this->actingAs($this->homeroomTeacher)
            ->postJson('/api/v1/mobile/tasks/homeroom/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => 'sakit',
            'reporter_type' => 'wali_kelas',
            'given_by' => $this->homeroomTeacher->id,
        ]);
    }

    public function test_homeroom_teacher_can_delete_permit(): void
    {
        $todayStr = Carbon::today()->toDateString();
        $permit = PiketStudentPermit::create([
            'permit_number' => 'IZN-TEST-999',
            'permit_date' => $todayStr,
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => 'izin',
            'reason' => 'Acara keluarga',
            'given_by' => $this->homeroomTeacher->id,
            'status' => 'diberikan',
        ]);

        $response = $this->actingAs($this->homeroomTeacher)
            ->deleteJson("/api/v1/mobile/tasks/homeroom/permits/{$permit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('piket_student_permits', [
            'id' => $permit->id,
        ]);
    }

    public function test_other_teacher_cannot_store_permit_for_other_classroom(): void
    {
        $todayStr = Carbon::today()->toDateString();

        $payload = [
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'permit_category' => 'sakit',
            'permit_date' => $todayStr,
            'reason' => 'Ilegal',
        ];

        $response = $this->actingAs($this->otherTeacher)
            ->postJson('/api/v1/mobile/tasks/homeroom/permits/store', $payload);

        $response->assertStatus(403);
    }

    public function test_homeroom_teacher_can_store_permit_with_blank_end_date_and_batch_students(): void
    {
        $student2 = Student::create([
            'name' => 'Bambang Siswa Test',
            'nis' => '67891',
            'is_active' => true,
            'classroom_id' => $this->classroom->id,
        ]);

        $todayStr = Carbon::today()->addDays(2)->toDateString();

        $payload = [
            'classroom_id' => $this->classroom->id,
            'student_ids' => [$this->student->id, $student2->id],
            'permit_category' => 'izin',
            'permit_date' => $todayStr,
            'end_date' => '', // Empty string from browser form input
            'reason' => 'Ada acara perwakilan keluarga',
        ];

        $response = $this->actingAs($this->homeroomTeacher)
            ->postJson('/api/v1/mobile/tasks/homeroom/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_saved' => 2,
                ],
            ]);

        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $this->student->id,
            'end_date' => null,
            'permit_category' => 'izin',
            'reporter_type' => 'wali_kelas',
        ]);

        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $student2->id,
            'end_date' => null,
            'permit_category' => 'izin',
            'reporter_type' => 'wali_kelas',
        ]);
    }

    public function test_dashboard_and_permits_pages_contain_homeroom_shortcuts(): void
    {
        $dash = $this->get('/mobile/pages/dashboard.html');
        $dash->assertOk()
            ->assertSee('dash-homeroom-card')
            ->assertSee('Perwalian Siswa')
            ->assertSee('/tasks/homeroom/permits');

        $permits = $this->get('/mobile/pages/tasks/permits.html');
        $permits->assertOk()
            ->assertSee('banner-homeroom-student-permits')
            ->assertSee('Wali Kelas? Catat Izin Murid Perwalian')
            ->assertSee('/tasks/homeroom/permits');
    }
}
