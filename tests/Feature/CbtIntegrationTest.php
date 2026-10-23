<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\User;
use App\Services\CbtSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CbtIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'level' => 1,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
    }

    public function test_cbt_database_connection_can_be_established(): void
    {
        $service = app(CbtSyncService::class);
        $this->assertTrue($service->testConnection());
    }

    public function test_cbt_sync_service_status_returns_valid_structure(): void
    {
        $service = app(CbtSyncService::class);
        $status = $service->getSyncStatus();

        $this->assertTrue($status['connected']);
        $this->assertArrayHasKey('siap', $status);
        $this->assertArrayHasKey('cbt', $status);
        $this->assertEquals('2026/2027', $status['active_academic_year']);
    }

    public function test_cbt_sync_service_syncs_academic_years(): void
    {
        $service = app(CbtSyncService::class);
        $result = $service->syncTahunPelajaran();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['total']);
    }

    public function test_cbt_integration_dashboard_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('kurikulum.cbt.index'));
        $response->assertStatus(200);
        $response->assertSee('Integrasi GarudaCBT');
        $response->assertSee('Terhubung (db_cbt)');
    }

    public function test_cbt_jadwal_list_can_be_retrieved(): void
    {
        $service = app(CbtSyncService::class);
        $jadwalList = $service->getCbtJadwalList();

        $this->assertInstanceOf(Collection::class, $jadwalList);
    }

    public function test_cbt_sync_endpoint_requires_authentication(): void
    {
        $response = $this->post(route('kurikulum.cbt.sync'), ['type' => 'tp']);
        $response->assertRedirect(route('login'));
    }

    public function test_cbt_sync_endpoint_accessible_via_ajax(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('kurikulum.cbt.sync'), ['type' => 'tp']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }
}
