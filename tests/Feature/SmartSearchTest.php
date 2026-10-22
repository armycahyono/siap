<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolStakeholder;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $guruRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guruRole = Role::create([
            'name' => 'Guru Pengajar',
            'slug' => 'guru',
            'is_system' => true,
        ]);

        $this->user = User::create([
            'name' => 'Budi Santoso, M.Pd.',
            'username' => 'budi_santoso',
            'email' => 'budi@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'is_active' => true,
            'nigs_fix' => '198501012010011001',
        ]);
    }

    public function test_guest_cannot_access_smart_search(): void
    {
        $response = $this->getJson(route('smart-search', ['q' => 'Budi']));

        $response->assertUnauthorized();
    }

    public function test_short_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('smart-search', ['q' => 'B']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 0,
                'results' => [],
            ]);
    }

    public function test_search_teachers_scope(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('smart-search', [
                'q' => 'Santoso',
                'scope' => 'teachers',
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('scope', 'teachers');

        $this->assertNotEmpty($response->json('results'));
        $this->assertEquals('Budi Santoso, M.Pd.', $response->json('results.0.title'));
    }

    public function test_search_subjects_scope(): void
    {
        Subject::create([
            'code' => 'MTK-WJB',
            'name' => 'Matematika Wajib Kelas X',
            'category' => 'Umum',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('smart-search', [
                'q' => 'Matematika',
                'scope' => 'subjects',
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('results'));
        $this->assertStringContainsString('Matematika Wajib', $response->json('results.0.title'));
    }

    public function test_search_classrooms_scope(): void
    {
        $grade = GradeLevel::create([
            'level' => 'XII',
            'name' => 'Kelas XII',
            'order_index' => 3,
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'XII RPL 2',
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('smart-search', [
                'q' => 'RPL 2',
                'scope' => 'classrooms',
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('results'));
        $this->assertEquals('XII RPL 2', $response->json('results.0.title'));
    }

    public function test_search_stakeholders_scope(): void
    {
        SchoolStakeholder::create([
            'name' => 'Drs. H. Mulyono',
            'category' => 'yayasan',
            'position' => 'Ketua Pembina Yayasan',
            'order_index' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('smart-search', [
                'q' => 'Mulyono',
                'scope' => 'stakeholders',
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('results'));
        $this->assertEquals('Drs. H. Mulyono', $response->json('results.0.title'));
    }

    public function test_auto_detects_scope_from_referer(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['referer' => 'https://kurikulum.test/teachers'])
            ->getJson(route('smart-search', ['q' => 'Budi']));

        $response->assertOk()
            ->assertJsonPath('scope', 'teachers');

        $this->assertNotEmpty($response->json('results'));
    }
}
