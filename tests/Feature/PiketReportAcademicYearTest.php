<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PiketReportAcademicYearTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorizedUser;

    protected User $unauthorizedUser;

    protected GradeLevel $gradeLevel;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // User with permission 'view_piket_reports' and 'access_piket' (e.g. waka_kurikulum)
        $wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();
        $this->authorizedUser = User::firstOrCreate(
            ['username' => 'waka_piket_test'],
            [
                'name' => 'Waka Piket Test',
                'email' => 'waka_piket@example.com',
                'password' => bcrypt('password'),
                'role_id' => $wakaRole->id,
                'is_active' => true,
            ]
        );

        // User without 'view_piket_reports' permission (e.g. guru)
        $guruRole = Role::where('slug', 'guru')->firstOrFail();
        $this->unauthorizedUser = User::firstOrCreate(
            ['username' => 'guru_piket_test'],
            [
                'name' => 'Guru Piket Test',
                'email' => 'guru_piket@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );

        $this->gradeLevel = GradeLevel::firstOrFail();
        $this->department = Department::firstOrFail();
    }

    /** 1. Pengguna dengan permission yang tepat dapat membuka laporan piket */
    public function test_authorized_user_can_access_piket_reports_page(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('piket.reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('piket.reports.index');
        $response->assertSee('Rekapitulasi & Laporan Piket');
    }

    /** 2. Tahun ajaran eksplisit yang valid memfilter daftar kelas sesuai tahun */
    public function test_valid_explicit_query_academic_year_filters_classrooms(): void
    {
        Classroom::create([
            'name' => 'X-TKR-PIKET-2025',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-TKR-PIKET-2026',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('piket.reports.index', ['academic_year' => '2025/2026']));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-PIKET-2025');
        $response->assertDontSee('X-TKR-PIKET-2026');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 3. Query eksplisit mengalahkan session yang berbeda */
    public function test_explicit_query_academic_year_overrides_different_session(): void
    {
        Classroom::create([
            'name' => 'X-TKR-PIKET-SESSI',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-TKR-PIKET-QUERY',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2026/2027'])
            ->get(route('piket.reports.index', ['academic_year' => '2025/2026']));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-PIKET-QUERY');
        $response->assertDontSee('X-TKR-PIKET-SESSI');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 4. Session valid digunakan ketika tidak ada query eksplisit */
    public function test_valid_session_year_is_used_when_no_explicit_query(): void
    {
        Classroom::create([
            'name' => 'X-TKR-SESSION-CLASS',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-TKR-MASTER-CLASS',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('piket.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-SESSION-CLASS');
        $response->assertDontSee('X-TKR-MASTER-CLASS');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 5. Session tidak valid mengikuti fallback ke master year */
    public function test_invalid_session_year_falls_back_to_master_year(): void
    {
        Classroom::create([
            'name' => 'X-TKR-MASTER-TARGET',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '9999/9998'])
            ->get(route('piket.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-MASTER-TARGET');
        $this->assertEquals('2026/2027', $response->viewData('academicYear'));
    }

    /** 6. Tanpa query dan session, master year digunakan */
    public function test_without_query_and_session_master_year_is_used(): void
    {
        Classroom::create([
            'name' => 'X-TKR-DEFAULT-TARGET',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('piket.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-DEFAULT-TARGET');
        $this->assertEquals('2026/2027', $response->viewData('academicYear'));
    }

    /** 7. Tahun eksplisit yang tidak terdaftar menghasilkan validation error, bukan HTTP 500 */
    public function test_unregistered_explicit_academic_year_yields_validation_error_not_500(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->from(route('piket.reports.index'))
            ->get(route('piket.reports.index', ['academic_year' => 'TahunPiketFiktif/2099']));

        $response->assertStatus(302);
        $response->assertRedirect(route('piket.reports.index'));
        $response->assertSessionHasErrors('academic_year');
    }

    /** 8. Tahun valid tanpa kelas menghasilkan halaman normal dengan daftar kelas kosong */
    public function test_valid_academic_year_without_classrooms_renders_normally(): void
    {
        // 2024/2025 ada di DB tapi belum ada kelas
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('piket.reports.index', ['academic_year' => '2024/2025']));

        $response->assertStatus(200);
        $this->assertEquals('2024/2025', $response->viewData('academicYear'));
        $this->assertTrue($response->viewData('classrooms')->isEmpty());
    }

    /** 9. GET tidak mengubah session atau master year */
    public function test_get_request_does_not_mutate_session_or_master_year(): void
    {
        $initialMaster = SchoolSetting::getMasterYear();

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('piket.reports.index', ['academic_year' => '2024/2025']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', session('active_academic_year'));
        $this->assertEquals($initialMaster, SchoolSetting::getMasterYear());
    }

    /** 10. Pengguna tanpa permission tetap ditolak sesuai middleware yang berlaku */
    public function test_unauthorized_user_is_forbidden_from_piket_reports(): void
    {
        // Guru tidak memiliki view_piket_reports -> 403
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('piket.reports.index'));
        $response->assertStatus(403);

        // Guest unauthenticated -> 302 login
        auth()->logout();
        $guestResponse = $this->get(route('piket.reports.index'));
        $guestResponse->assertRedirect(route('login'));
    }

    /** 11. Query data transaksi piket tetap berjalan berdasarkan rentang tanggal dan tidak difilter tahun ajaran */
    public function test_piket_transaction_query_is_filtered_by_date_range_not_academic_year(): void
    {
        $classroom = Classroom::create([
            'name' => 'X-TKR-HISTORIS',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $student = Student::firstOrCreate(
            ['nisn' => '9988776655'],
            [
                'name' => 'Siswa Uji Piket',
                'nis' => '12345',
                'gender' => 'L',
                'is_active' => true,
            ]
        );

        $today = Carbon::now()->toDateString();

        PiketStudentPermit::create([
            'permit_number' => 'IZN-TEST-001',
            'permit_date' => $today,
            'student_name' => 'Siswa Uji Piket',
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'permit_type' => 'terlambat',
            'reason' => 'Ban bocor',
            'given_by' => $this->authorizedUser->id,
        ]);

        // Akses dengan viewing year 2026/2027 (bukan tahun kelas 2025/2026)
        $response = $this->actingAs($this->authorizedUser)->get(route('piket.reports.index', [
            'academic_year' => '2026/2027',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        // Transaksi tanggal hari ini tetap tampil di tabel laporan
        $response->assertSee('IZN-TEST-001');
        $response->assertSee('Siswa Uji Piket');
    }

    /** 12. Konfigurasi master year yang tidak valid menghasilkan RuntimeException, bukan validation error */
    public function test_invalid_master_year_configuration_causes_runtime_exception(): void
    {
        SchoolSetting::first()->update(['academic_year' => '2099/2100']);

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Master academic year '2099/2100' is not available.");

        $this->actingAs($this->authorizedUser)->get(route('piket.reports.index'));
    }
}
