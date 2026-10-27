<?php

namespace Tests\Feature;

use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PiketCalendarAcademicYearTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorizedUser;

    protected User $unauthorizedUser;

    protected User $teacher1;

    protected User $teacher2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();
        $this->authorizedUser = User::firstOrCreate(
            ['username' => 'waka_cal_test'],
            [
                'name' => 'Waka Cal Test',
                'short_name' => 'WakaCal',
                'email' => 'waka_cal@example.com',
                'password' => bcrypt('password'),
                'role_id' => $wakaRole->id,
                'is_active' => true,
            ]
        );

        $guruRole = Role::where('slug', 'guru')->firstOrFail();
        $this->unauthorizedUser = User::firstOrCreate(
            ['username' => 'guru_cal_test'],
            [
                'name' => 'Guru Cal Test',
                'short_name' => 'GuruCal',
                'email' => 'guru_cal@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );

        $this->teacher1 = User::firstOrCreate(
            ['username' => 'guru_sched_1'],
            [
                'name' => 'Guru Jadwal Satu',
                'short_name' => 'GuruSatu',
                'email' => 'guru_sched_1@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );

        $this->teacher2 = User::firstOrCreate(
            ['username' => 'guru_sched_2'],
            [
                'name' => 'Guru Jadwal Dua',
                'short_name' => 'GuruDua',
                'email' => 'guru_sched_2@example.com',
                'password' => bcrypt('password'),
                'role_id' => $guruRole->id,
                'is_active' => true,
            ]
        );
    }

    /** 1. Pengguna berizin dapat membuka kalender piket */
    public function test_authorized_user_can_access_piket_calendar(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('piket.calendar.index'));

        $response->assertStatus(200);
        $response->assertViewIs('piket.calendar.index');
        $response->assertSee('Kalender Petugas Piket');
    }

    /** 2. Tahun eksplisit yang valid memfilter jadwal kalender dengan benar */
    public function test_valid_explicit_query_academic_year_filters_calendar_schedules(): void
    {
        PiketSchedule::create([
            'academic_year' => '2025/2026',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher1->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher2->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('piket.calendar.index', ['academic_year' => '2025/2026', 'semester' => 'Ganjil']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
        $response->assertSee('GuruSatu');
        $response->assertDontSee('GuruDua');
    }

    /** 3. Tahun eksplisit mengalahkan session dengan tahun berbeda */
    public function test_explicit_query_academic_year_overrides_different_session(): void
    {
        PiketSchedule::create([
            'academic_year' => '2025/2026',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher1->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher2->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2026/2027'])
            ->get(route('piket.calendar.index', ['academic_year' => '2025/2026', 'semester' => 'Ganjil']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
        $response->assertSee('GuruSatu');
        $response->assertDontSee('GuruDua');
    }

    /** 4. Session valid digunakan ketika query tidak diberikan */
    public function test_valid_session_year_is_used_when_no_explicit_query(): void
    {
        PiketSchedule::create([
            'academic_year' => '2025/2026',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher1->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher2->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('piket.calendar.index', ['semester' => 'Ganjil']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', $response->viewData('academicYear'));
        $response->assertSee('GuruSatu');
        $response->assertDontSee('GuruDua');
    }

    /** 5. Session tidak valid kembali ke master year */
    public function test_invalid_session_year_falls_back_to_master_year(): void
    {
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher2->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '9999/9998'])
            ->get(route('piket.calendar.index', ['semester' => 'Ganjil']));

        $response->assertStatus(200);
        $this->assertEquals('2026/2027', $response->viewData('academicYear'));
        $response->assertSee('GuruDua');
    }

    /** 6. Tahun eksplisit yang tidak terdaftar menghasilkan kesalahan validasi, bukan HTTP 500 */
    public function test_unregistered_explicit_academic_year_yields_validation_error_not_500(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->from(route('piket.calendar.index'))
            ->get(route('piket.calendar.index', ['academic_year' => 'TahunKalenderFiktif/2099']));

        $response->assertStatus(302);
        $response->assertRedirect(route('piket.calendar.index'));
        $response->assertSessionHasErrors('academic_year');
    }

    /** 7. Tahun valid tanpa jadwal menampilkan kalender secara normal */
    public function test_valid_academic_year_without_schedules_renders_normally(): void
    {
        // 2024/2025 ada di database namun tidak memiliki jadwal piket
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('piket.calendar.index', ['academic_year' => '2024/2025', 'semester' => 'Ganjil']));

        $response->assertStatus(200);
        $this->assertEquals('2024/2025', $response->viewData('academicYear'));
        $this->assertNotEmpty($response->viewData('calendarData'));
        $response->assertDontSee('GuruSatu');
        $response->assertDontSee('GuruDua');
    }

    /** 8. Request GET tidak mengubah session atau master year */
    public function test_get_request_does_not_mutate_session_or_master_year(): void
    {
        $initialMaster = SchoolSetting::getMasterYear();

        $response = $this->actingAs($this->authorizedUser)
            ->withSession(['active_academic_year' => '2025/2026'])
            ->get(route('piket.calendar.index', ['academic_year' => '2024/2025']));

        $response->assertStatus(200);
        $this->assertEquals('2025/2026', session('active_academic_year'));
        $this->assertEquals($initialMaster, SchoolSetting::getMasterYear());
    }

    /** 9. Pengguna tanpa permission tetap ditolak */
    public function test_unauthorized_user_is_forbidden_from_piket_calendar(): void
    {
        // Guru tidak memiliki izin manage_piket_schedules -> 403
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('piket.calendar.index'));
        $response->assertStatus(403);

        // Guest unauthenticated -> 302 login
        auth()->logout();
        $guestResponse = $this->get(route('piket.calendar.index'));
        $guestResponse->assertRedirect(route('login'));
    }

    /** 10. Konfigurasi master year yang tidak valid tetap menghasilkan RuntimeException, bukan validation error */
    public function test_invalid_master_year_configuration_causes_runtime_exception(): void
    {
        SchoolSetting::first()->update(['academic_year' => '2099/2100']);

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Master academic year '2099/2100' is not available.");

        $this->actingAs($this->authorizedUser)->get(route('piket.calendar.index'));
    }
}
