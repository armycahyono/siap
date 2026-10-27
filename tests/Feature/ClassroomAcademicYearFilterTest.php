<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ClassroomAcademicYearFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $wakaUser;

    protected User $guruUser;

    protected GradeLevel $gradeLevel;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();
        $this->wakaUser = User::firstOrCreate(
            ['username' => 'waka_test'],
            [
                'name' => 'Waka Test',
                'email' => 'waka_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $wakaRole->id,
                'is_active' => true,
            ]
        );

        $guruRole = Role::where('slug', 'guru')->firstOrFail();
        $this->guruUser = User::firstOrCreate(
            ['username' => 'guru_test'],
            [
                'name' => 'Guru Test',
                'email' => 'guru_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );

        $this->gradeLevel = GradeLevel::firstOrFail();
        $this->department = Department::firstOrFail();
    }

    /** 1. Pengguna berizin dapat membuka halaman kelas dan menerima HTTP 200 */
    public function test_authorized_user_can_access_classrooms_page_and_receives_200(): void
    {
        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index'));

        $response->assertStatus(200);
        $response->assertViewIs('master.classrooms.index');
    }

    /** 2. Query tahun ajaran eksplisit yang valid dipakai sebagai filter */
    public function test_valid_explicit_query_academic_year_filters_classrooms_accordingly(): void
    {
        $cls2025 = Classroom::create([
            'name' => 'X-TKR-2025',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $cls2026 = Classroom::create([
            'name' => 'X-TKR-2026',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index', ['academic_year' => '2025/2026']));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-2025');
        $response->assertDontSee('X-TKR-2026');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 3. Query tahun ajaran eksplisit mengalahkan session yang berbeda */
    public function test_explicit_query_academic_year_overrides_different_session_year(): void
    {
        Classroom::create([
            'name' => 'X-TKR-SESSI',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-TKR-QUERY',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)
            ->withSession(['active_academic_year' => '2026/2027'])
            ->get(route('classrooms.index', ['academic_year' => '2025/2026']));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-QUERY');
        $response->assertDontSee('X-TKR-SESSI');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 4. Session valid dipakai ketika tidak ada query eksplisit */
    public function test_valid_session_year_is_used_when_no_explicit_query(): void
    {
        Classroom::create([
            'name' => 'X-TKR-SESSI-ONLY',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-TKR-MASTER-YEAR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('classrooms.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-SESSI-ONLY');
        $response->assertDontSee('X-TKR-MASTER-YEAR');
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
    }

    /** 5. Session tidak valid mengikuti fallback ke master year yang valid */
    public function test_invalid_session_year_falls_back_to_valid_master_year(): void
    {
        Classroom::create([
            'name' => 'X-TKR-MASTER-TARGET',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)
            ->withSession(['active_academic_year' => '9999/9998'])
            ->get(route('classrooms.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-MASTER-TARGET');
        $this->assertEquals('2026/2027', $response->viewData('academicYear'));
    }

    /** 6. Tanpa query dan tanpa session, master year digunakan */
    public function test_without_query_and_session_master_year_is_used(): void
    {
        Classroom::create([
            'name' => 'X-TKR-DEFAULT-MASTER',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index'));

        $response->assertStatus(200);
        $response->assertSee('X-TKR-DEFAULT-MASTER');
        $this->assertEquals('2026/2027', $response->viewData('academicYear'));
    }

    /** 7. Tahun ajaran eksplisit yang tidak terdaftar menghasilkan validasi yang sesuai, bukan HTTP 500 */
    public function test_unregistered_explicit_academic_year_yields_validation_error_not_500(): void
    {
        $response = $this->actingAs($this->wakaUser)
            ->from(route('classrooms.index'))
            ->get(route('classrooms.index', ['academic_year' => 'TahunFiktif/9999']));

        $response->assertStatus(302);
        $response->assertRedirect(route('classrooms.index'));
        $response->assertSessionHasErrors('academic_year');
    }

    /** 8. Tahun valid tanpa kelas menghasilkan halaman kosong yang normal */
    public function test_valid_academic_year_without_classrooms_renders_normal_empty_state(): void
    {
        // 2024/2025 ada di database tetapi belum memiliki classroom
        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index', ['academic_year' => '2024/2025']));

        $response->assertStatus(200);
        $this->assertEquals('2024/2025', $response->viewData('academicYear'));
        $this->assertTrue($response->viewData('classrooms')->isEmpty());
    }

    /** 9. GET tidak mengubah session atau master year */
    public function test_get_request_does_not_mutate_session_or_master_year(): void
    {
        $initialMaster = SchoolSetting::getMasterYear();

        $response = $this->actingAs($this->wakaUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('classrooms.index', ['academic_year' => '2024/2025']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', session('active_academic_year'));
        $this->assertEquals($initialMaster, SchoolSetting::getMasterYear());
    }

    /** 10. Middleware dan otorisasi tetap berlaku untuk pengguna tanpa izin */
    public function test_unauthorized_user_cannot_access_classrooms_page(): void
    {
        // Guru tidak memiliki izin manage_master_data
        $response = $this->actingAs($this->guruUser)->get(route('classrooms.index'));
        $response->assertStatus(403);

        // Guest / unauthenticated diarahkan ke login
        auth()->logout();
        $guestResponse = $this->get(route('classrooms.index'));
        $guestResponse->assertRedirect(route('login'));
    }

    /** 11. Konfigurasi master year tidak valid tidak disamarkan sebagai kesalahan input eksplisit */
    public function test_invalid_master_year_configuration_is_not_disguised_as_validation_error(): void
    {
        SchoolSetting::first()->update(['academic_year' => '2099/2100']);

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Master academic year '2099/2100' is not available.");

        $this->actingAs($this->wakaUser)->get(route('classrooms.index'));
    }

    /** 12. Filter rombel mencocokkan academic_year_id dan string academic_year secara konsisten */
    public function test_classrooms_index_filters_by_both_academic_year_id_and_legacy_string_academic_year(): void
    {
        $ay2026 = AcademicYear::where('name', '2026/2027')->firstOrFail();
        $ay2025 = AcademicYear::firstOrCreate(['name' => '2025/2026'], [
            'semester' => 'Genap',
            'is_active' => false,
        ]);

        // 1. Rombel modern: memiliki academic_year_id dan string academic_year
        $clsModern = Classroom::create([
            'name' => 'X-MODERN-BOTH',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ay2026->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // 2. Rombel legacy: hanya memiliki string academic_year (academic_year_id = null)
        $clsLegacy = Classroom::create([
            'name' => 'X-LEGACY-STR-ONLY',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => null,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // 3. Rombel dengan string khusus / berbeda tetapi academic_year_id valid
        $clsIdOnly = Classroom::create([
            'name' => 'X-ID-MATCHED-CUSTOM-STR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ay2026->id,
            'academic_year' => '2026/2027-FormatLain',
            'is_active' => true,
        ]);

        // 4. Rombel tahun berbeda (2025/2026)
        $clsOtherYear = Classroom::create([
            'name' => 'X-DIFF-YEAR-2025',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ay2025->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index', ['academic_year' => '2026/2027']));

        $response->assertStatus(200);
        $response->assertSee('X-MODERN-BOTH');
        $response->assertSee('X-LEGACY-STR-ONLY');
        $response->assertSee('X-ID-MATCHED-CUSTOM-STR');
        $response->assertDontSee('X-DIFF-YEAR-2025');
    }

    /** 13. Verifikasi menyeluruh terhadap skenario filter rombel berdasarkan ID dan string */
    public function test_classrooms_filter_strictly_respects_id_precedence_and_null_id_fallback(): void
    {
        $ayTarget = AcademicYear::where('name', '2026/2027')->firstOrFail();
        $ayOther = AcademicYear::firstOrCreate(['name' => '2025/2026'], [
            'semester' => 'Genap',
            'is_active' => false,
        ]);

        // Skenario 1: ID dan string sama-sama menunjuk tahun yang dipilih
        Classroom::create([
            'name' => 'CLS-SCENARIO-1-MATCH-BOTH',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ayTarget->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Skenario 2: ID menunjuk tahun yang dipilih, string berbeda (mengikuti ID)
        Classroom::create([
            'name' => 'CLS-SCENARIO-2-ID-TARGET-DIFF-STR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ayTarget->id,
            'academic_year' => '2026/2027-CustomAlias',
            'is_active' => true,
        ]);

        // Skenario 3: ID menunjuk tahun lain, string cocok dengan tahun yang dipilih (DILARANG TAMPIL)
        Classroom::create([
            'name' => 'CLS-SCENARIO-3-CONFLICT-ID-OTHER',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ayOther->id,
            'academic_year' => '2026/2027', // string cocok, tapi ID milik 2025/2026
            'is_active' => true,
        ]);

        // Skenario 4: ID NULL, string cocok (fallback data lama, HARUS TAMPIL)
        Classroom::create([
            'name' => 'CLS-SCENARIO-4-NULL-ID-MATCH-STR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => null,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Skenario 5: ID NULL, string berbeda (DILARANG TAMPIL)
        Classroom::create([
            'name' => 'CLS-SCENARIO-5-NULL-ID-DIFF-STR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => null,
            'academic_year' => '2024/2025',
            'is_active' => true,
        ]);

        // Skenario 6: Data tahun lain yang konsisten (DILARANG TAMPIL)
        Classroom::create([
            'name' => 'CLS-SCENARIO-6-CONSISTENT-OTHER-YEAR',
            'grade_level_id' => $this->gradeLevel->id,
            'department_id' => $this->department->id,
            'academic_year_id' => $ayOther->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->wakaUser)->get(route('classrooms.index', ['academic_year' => '2026/2027']));

        $response->assertStatus(200);
        $response->assertSee('CLS-SCENARIO-1-MATCH-BOTH');
        $response->assertSee('CLS-SCENARIO-2-ID-TARGET-DIFF-STR');
        $response->assertDontSee('CLS-SCENARIO-3-CONFLICT-ID-OTHER');
        $response->assertSee('CLS-SCENARIO-4-NULL-ID-MATCH-STR');
        $response->assertDontSee('CLS-SCENARIO-5-NULL-ID-DIFF-STR');
        $response->assertDontSee('CLS-SCENARIO-6-CONSISTENT-OTHER-YEAR');
    }
}
