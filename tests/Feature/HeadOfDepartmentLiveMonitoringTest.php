<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\LiveMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadOfDepartmentLiveMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $kajurUser;

    protected Department $departmentAkl;

    protected Classroom $aklClassroom;

    protected Classroom $tkrClassroom;

    protected Student $aklStudent;

    protected Student $tkrStudent;

    protected string $academicYear = '2026/2027';

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => $this->academicYear,
            'semester' => 'Ganjil',
        ]);

        AcademicYear::create([
            'name' => $this->academicYear,
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'level' => 10,
            'name' => '10',
            'order_index' => 1,
            'is_active' => true,
        ]);

        $this->kajurUser = User::factory()->create([
            'name' => 'Kajur Akuntansi',
            'email' => 'kajur_akl@test.com',
            'position' => 'Kepala Program Keahlian Akuntansi',
        ]);

        $this->departmentAkl = Department::create([
            'name' => 'Akuntansi dan Keuangan',
            'code' => 'AKL',
            'head_of_department_id' => $this->kajurUser->id,
            'is_active' => true,
        ]);

        $departmentTkr = Department::create([
            'name' => 'Teknik Kendaraan Ringan',
            'code' => 'TKR',
            'is_active' => true,
        ]);

        $this->aklClassroom = Classroom::create([
            'name' => 'X AKL 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => $this->academicYear,
            'department_id' => $this->departmentAkl->id,
            'is_active' => true,
        ]);

        $this->tkrClassroom = Classroom::create([
            'name' => 'X TKR 1',
            'grade_level_id' => $gradeLevel->id,
            'academic_year' => $this->academicYear,
            'department_id' => $departmentTkr->id,
            'is_active' => true,
        ]);

        $this->aklStudent = Student::create([
            'name' => 'Siswa AKL',
            'classroom_id' => $this->aklClassroom->id,
            'nis' => '1001',
            'nisn' => '0012345001',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $this->tkrStudent = Student::create([
            'name' => 'Siswa TKR',
            'classroom_id' => $this->tkrClassroom->id,
            'nis' => '1002',
            'nisn' => '0012345002',
            'gender' => 'L',
            'is_active' => true,
        ]);
    }

    public function test_user_can_detect_head_of_department_and_resolve_classrooms(): void
    {
        $this->assertTrue($this->kajurUser->isHeadOfDepartment($this->academicYear));

        $managedDepts = $this->kajurUser->getManagedDepartments($this->academicYear);
        $this->assertCount(1, $managedDepts);
        $this->assertEquals('AKL', $managedDepts->first()->code);

        $classrooms = $this->kajurUser->getDepartmentClassrooms($this->academicYear);
        $this->assertTrue($classrooms->contains('id', $this->aklClassroom->id));
        $this->assertFalse($classrooms->contains('id', $this->tkrClassroom->id));
    }

    public function test_live_monitoring_service_scopes_to_head_of_department_classrooms(): void
    {
        $service = app(LiveMonitoringService::class);
        $data = $service->buildLiveMonitoringData($this->kajurUser, now(), $this->academicYear, null);

        $this->assertNotNull($data);
        $this->assertTrue($data['is_head_of_department']);
        $this->assertEquals('kaprogli', $data['role_key']);
        $this->assertEquals('Akuntansi dan Keuangan', $data['department_name']);
        $this->assertEquals('AKL', $data['department_code']);
        $this->assertEquals(1, $data['total_students']);
        $this->assertCount(1, $data['classrooms']);
        $this->assertEquals('X AKL 1', $data['classrooms'][0]['name']);
    }

    public function test_tasks_mobile_view_renders_department_live_monitoring(): void
    {
        $this->actingAs($this->kajurUser);

        $response = $this->get(route('tasks', ['view_mode' => 'mobile']));

        $response->assertOk();
        $response->assertViewHas('departmentLiveMonitoring');
        $response->assertSee('Monitoring Siswa Jurusan AKL');
        $response->assertSee('X AKL 1');
        $response->assertDontSee('X TKR 1');
    }

    public function test_mobile_tasks_api_includes_department_monitoring(): void
    {
        $this->actingAs($this->kajurUser);

        $response = $this->getJson('/api/v1/mobile/tasks');

        $response->assertOk();
        $response->assertJsonPath('data.department.is_head_of_department', true);
        $response->assertJsonPath('data.department.department_code', 'AKL');
        $response->assertJsonPath('data.department.total_students', 1);
    }

    public function test_mobile_kbm_api_includes_department_scoped_live_monitoring(): void
    {
        $this->actingAs($this->kajurUser);

        $response = $this->getJson('/api/v1/mobile/kbm');

        $response->assertOk();
        $response->assertJsonPath('data.live_monitoring.is_head_of_department', true);
        $response->assertJsonPath('data.live_monitoring.department_code', 'AKL');
        $response->assertJsonPath('data.live_monitoring.total_students', 1);
    }

    public function test_kajur_can_access_recap_and_see_all_department_classrooms(): void
    {
        $this->actingAs($this->kajurUser);

        // Web / Mobile Blade endpoint
        $response = $this->get(route('attendance.homeroom-recap.index', [
            'academic_year' => $this->academicYear,
        ]));

        $response->assertOk();
        $response->assertViewHas('myClassrooms', function ($classrooms) {
            return $classrooms->contains('id', $this->aklClassroom->id)
                && ! $classrooms->contains('id', $this->tkrClassroom->id);
        });

        // Mobile API endpoint
        $apiResponse = $this->getJson('/api/v1/mobile/tasks/homeroom/recap?academic_year='.$this->academicYear);
        $apiResponse->assertOk();
        $apiResponse->assertJsonPath('data.my_classrooms.0.id', $this->aklClassroom->id);
        $this->assertCount(1, $apiResponse->json('data.my_classrooms'));
    }

    public function test_kajur_can_download_pdf_and_export_excel_for_department_classroom(): void
    {
        $this->actingAs($this->kajurUser);

        // Download PDF for AKL classroom
        $pdfResponse = $this->get(route('attendance.homeroom-recap.download-pdf', [
            'classroom_id' => $this->aklClassroom->id,
            'academic_year' => $this->academicYear,
            'mode' => 'daily',
        ]));
        $pdfResponse->assertOk();

        // Print PDF (action=print)
        $printResponse = $this->get(route('attendance.homeroom-recap.download-pdf', [
            'classroom_id' => $this->aklClassroom->id,
            'academic_year' => $this->academicYear,
            'mode' => 'daily',
            'action' => 'print',
        ]));
        $printResponse->assertOk();

        // Export Excel for AKL classroom
        $excelResponse = $this->get(route('attendance.homeroom-recap.export-excel', [
            'classroom_id' => $this->aklClassroom->id,
            'academic_year' => $this->academicYear,
            'mode' => 'daily',
        ]));
        $excelResponse->assertOk();
    }

    public function test_kajur_cannot_access_or_download_pdf_for_other_department_classroom(): void
    {
        $this->actingAs($this->kajurUser);

        // Attempt to view recap for TKR classroom should return 403
        $response = $this->get(route('attendance.homeroom-recap.index', [
            'classroom_id' => $this->tkrClassroom->id,
            'academic_year' => $this->academicYear,
        ]));
        $response->assertStatus(403);

        // Attempt to download PDF for TKR classroom should return 403
        $pdfResponse = $this->get(route('attendance.homeroom-recap.download-pdf', [
            'classroom_id' => $this->tkrClassroom->id,
            'academic_year' => $this->academicYear,
            'mode' => 'daily',
        ]));
        $pdfResponse->assertStatus(403);
    }

    public function test_kajur_has_permits_access_for_department_classroom(): void
    {
        $this->actingAs($this->kajurUser);

        $liveMonitoring = app(LiveMonitoringService::class)->buildLiveMonitoringData(
            $this->kajurUser,
            now(),
            $this->academicYear,
            null
        );

        $this->assertTrue($liveMonitoring['can_manage_permits']);
        $this->assertTrue($liveMonitoring['can_manage_visitations']);
        $this->assertTrue($liveMonitoring['can_view_recap']);

        // Kajur can store permit for AKL student
        $postData = [
            'classroom_id' => $this->aklClassroom->id,
            'student_id' => $this->aklStudent->id,
            'permit_category' => 'izin',
            'permit_date' => now()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ];

        $webStore = $this->post(route('attendance.homeroom-permits.store'), $postData);
        $webStore->assertRedirect();

        // Kajur cannot store permit for TKR student
        $forbiddenPost = $this->post(route('attendance.homeroom-permits.store'), [
            'classroom_id' => $this->tkrClassroom->id,
            'student_id' => $this->tkrStudent->id,
            'permit_category' => 'izin',
            'permit_date' => now()->toDateString(),
            'reason' => 'Bukan rombelnya',
        ]);
        $forbiddenPost->assertStatus(403);

        // Mobile API permit store test for tomorrow
        $mobileApiStore = $this->postJson('/api/v1/mobile/tasks/homeroom/permits/store', [
            'classroom_id' => $this->aklClassroom->id,
            'student_ids' => [$this->aklStudent->id],
            'permit_category' => 'sakit',
            'permit_date' => now()->addDay()->toDateString(),
            'reason' => 'Demam flu',
        ]);
        $mobileApiStore->assertOk();
    }

    public function test_counselor_bk_cannot_manage_permits_in_live_monitoring(): void
    {
        $bkUser = User::factory()->create([
            'name' => 'Guru BK Konselor',
            'position' => 'Guru BK',
        ]);

        $this->actingAs($bkUser);

        $liveMonitoring = app(LiveMonitoringService::class)->buildLiveMonitoringData(
            $bkUser,
            now(),
            $this->academicYear,
            null
        );

        $this->assertNotNull($liveMonitoring);
        $this->assertEquals('bk', $liveMonitoring['role_key']);
        $this->assertFalse($liveMonitoring['can_manage_permits']);
        $this->assertTrue($liveMonitoring['can_manage_visitations']);
        $this->assertTrue($liveMonitoring['can_view_recap']);
    }
}
