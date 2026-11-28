<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-lms-integration-token';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        config()->set('services.lms.token', $this->token);
        config()->set('services.lms.url', 'https://lms.example.test');
    }

    public function test_teachers_endpoint_only_returns_admin_and_guru_and_excludes_staff(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $stafRole = Role::firstOrCreate(['slug' => 'staf'], ['name' => 'Staf']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $guru = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $staffByPosition = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'staf',
            'is_active' => true,
        ]);

        $staffByRole = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $staffLegacyPosition = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Satpam',
            'is_active' => true,
        ]);

        $inactiveGuru = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/integration/lms/teachers');

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('siap_user_id')->all();

        $this->assertContains($admin->id, $returnedIds);
        $this->assertContains($guru->id, $returnedIds);
        $this->assertNotContains($staffByPosition->id, $returnedIds);
        $this->assertNotContains($staffByRole->id, $returnedIds);
        $this->assertNotContains($staffLegacyPosition->id, $returnedIds);
        $this->assertNotContains($inactiveGuru->id, $returnedIds);

        $byId = collect($response->json('data'))->keyBy('siap_user_id');
        $this->assertSame('admin', $byId[$admin->id]['base_role']);
        $this->assertSame('guru', $byId[$guru->id]['base_role']);
    }

    public function test_sso_verify_rejects_staff_and_allows_guru_and_admin(): void
    {
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $stafRole = Role::firstOrCreate(['slug' => 'staf'], ['name' => 'Staf']);

        $staff = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'staf',
            'is_active' => true,
        ]);

        $guru = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $staffCode = Str::random(64);
        Cache::put("lms_sso_code:{$staffCode}", $staff->id, now()->addMinute());

        $this->withToken($this->token)
            ->postJson('/api/integration/lms/sso/verify', ['code' => $staffCode])
            ->assertForbidden();

        $guruCode = Str::random(64);
        Cache::put("lms_sso_code:{$guruCode}", $guru->id, now()->addMinute());

        $this->withToken($this->token)
            ->postJson('/api/integration/lms/sso/verify', ['code' => $guruCode])
            ->assertOk()
            ->assertJsonPath('siap_user_id', $guru->id)
            ->assertJsonPath('base_role', 'guru');
    }

    public function test_sso_launch_blocks_staff_and_redirects_guru(): void
    {
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $stafRole = Role::firstOrCreate(['slug' => 'staf'], ['name' => 'Staf']);

        $staff = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'staf',
            'is_active' => true,
        ]);

        $guru = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('lms.launch'))
            ->assertForbidden();

        $response = $this->actingAs($guru)
            ->get(route('lms.launch'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://lms.example.test/sso/callback?code=', (string) $response->headers->get('Location'));
    }

    public function test_teachers_endpoint_includes_active_year_structural_positions_for_teachers_only(): void
    {
        $activeYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
        );
        AcademicYear::where('id', '!=', $activeYear->id)->update(['is_active' => false]);
        $activeYear->update(['is_active' => true]);

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $wakaSarpras = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_SARPRAS'],
            ['name' => 'Waka Sarpras', 'category' => 'Wakil Kepala Sekolah', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'is_active' => true]
        );
        $kaproTkj = StructuralPosition::firstOrCreate(
            ['code' => 'KAPRO_TKJ'],
            ['name' => 'Kaprogli TKJ', 'category' => 'Kepala Program Keahlian', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'is_active' => true]
        );
        $wakaHumas = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_HUMAS'],
            ['name' => 'Waka Humas', 'category' => 'Wakil Kepala Sekolah', 'type' => StructuralPosition::TYPE_STRUKTURAL, 'is_active' => true]
        );
        $operasionalPos = StructuralPosition::firstOrCreate(
            ['code' => 'TIM_KEDISIPLINAN'],
            ['name' => 'Tim Kedisiplinan', 'category' => 'Kesiswaan & Disiplin', 'type' => StructuralPosition::TYPE_OPERASIONAL, 'is_active' => true]
        );

        $guru = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $guru->id,
            'structural_position_id' => $wakaSarpras->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $guru->id,
            'structural_position_id' => $kaproTkj->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Jabatan tahun ajaran lama tidak boleh ikut terkirim
        TeacherStructuralAssignment::create([
            'teacher_id' => $guru->id,
            'structural_position_id' => $wakaHumas->id,
            'academic_year' => '2025/2026',
            'is_active' => true,
        ]);

        // Jabatan operasional (non-struktural) tidak ikut
        TeacherStructuralAssignment::create([
            'teacher_id' => $guru->id,
            'structural_position_id' => $operasionalPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Admin tidak menerima jabatan struktural guru
        TeacherStructuralAssignment::create([
            'teacher_id' => $admin->id,
            'structural_position_id' => $wakaSarpras->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/integration/lms/teachers');

        $response->assertOk();

        $byId = collect($response->json('data'))->keyBy('siap_user_id');

        $guruRoles = collect($byId[$guru->id]['assignments'])->pluck('role_type')->all();
        $this->assertContains('waka_sarpras', $guruRoles);
        $this->assertContains('kapro_tkj', $guruRoles);
        $this->assertNotContains('waka_humas', $guruRoles);
        $this->assertNotContains('tim_kedisiplinan', $guruRoles);

        $adminRoles = collect($byId[$admin->id]['assignments'])->pluck('role_type')->all();
        $this->assertNotContains('waka_sarpras', $adminRoles);
    }
}
