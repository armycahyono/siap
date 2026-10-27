<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $wakaKurikulum;

    protected User $guru;

    protected Role $superAdminRole;

    protected Role $wakaRole;

    protected Role $guruRole;

    protected Role $supervisorRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();
        $this->wakaRole = Role::where('slug', 'waka_kurikulum')->firstOrFail();
        $this->guruRole = Role::where('slug', 'guru')->firstOrFail();
        $this->supervisorRole = Role::where('slug', 'supervisor')->firstOrFail();

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $this->wakaKurikulum = User::factory()->create([
            'role_id' => $this->wakaRole->id,
            'is_active' => true,
        ]);

        $this->guru = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1 — Akses matriks
     * test_authorized_user_can_view_role_permission_matrix
     */
    public function test_authorized_user_can_view_role_permission_matrix(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('roles.index'));

        $response->assertStatus(200);
        $response->assertSee('Matriks Hak Akses Modul Aplikasi');
        $response->assertSee('roles_present', false);
    }

    /**
     * Test 2 — Akses tanpa izin
     * test_unauthorized_user_cannot_access_or_update_roles
     */
    public function test_unauthorized_user_cannot_access_or_update_roles(): void
    {
        // Guru tidak memiliki izin manage_roles
        $this->actingAs($this->guru)
            ->get(route('roles.index'))
            ->assertStatus(403);

        $this->actingAs($this->guru)
            ->post(route('roles.update'), [
                'permissions' => [$this->guruRole->id => [1]],
            ])
            ->assertStatus(403);

        $this->actingAs($this->guru)
            ->post(route('roles.store-role'), [
                'name' => 'Peran Ilegal',
                'slug' => 'peran_ilegal',
            ])
            ->assertStatus(403);
    }

    /**
     * Test 3 — Perlindungan super admin
     * test_super_admin_always_retains_all_permissions
     */
    public function test_super_admin_always_retains_all_permissions(): void
    {
        $totalPermissionsCount = Permission::count();
        $this->assertGreaterThan(0, $totalPermissionsCount);

        // Kirim manipulasi yang mencoba mengosongkan permission super_admin
        $response = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'roles_present' => [$this->superAdminRole->id, $this->guruRole->id],
            'permissions' => [
                $this->superAdminRole->id => [], // Percobaan pengosongan
                $this->guruRole->id => [Permission::first()->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Super admin harus tetap memiliki SEMUA permission
        $this->assertEquals($totalPermissionsCount, $this->superAdminRole->fresh()->permissions()->count());
    }

    /**
     * Test 4 — Sinkronisasi valid
     * test_sync_permissions_succeeds_with_valid_matrix
     */
    public function test_sync_permissions_succeeds_with_valid_matrix(): void
    {
        $perm1 = Permission::where('slug', 'upload_rpp')->firstOrFail();
        $perm2 = Permission::where('slug', 'view_own_supervision')->firstOrFail();
        $wakaPermsBefore = $this->wakaRole->permissions()->count();

        $response = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'roles_present' => [$this->guruRole->id],
            'permissions' => [
                $this->guruRole->id => [$perm1->id, $perm2->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $guruPerms = $this->guruRole->fresh()->permissions->pluck('id')->all();
        $this->assertCount(2, $guruPerms);
        $this->assertContains($perm1->id, $guruPerms);
        $this->assertContains($perm2->id, $guruPerms);

        // Role di luar daftar pemrosesan tetap tidak berubah
        $this->assertEquals($wakaPermsBefore, $this->wakaRole->fresh()->permissions()->count());
    }

    /**
     * Test 5 — Permission tidak valid
     * test_sync_permissions_rejects_invalid_or_nonexistent_permission_id
     */
    public function test_sync_permissions_rejects_invalid_or_nonexistent_permission_id(): void
    {
        $invalidPermId = 999999;
        $this->assertDatabaseMissing('permissions', ['id' => $invalidPermId]);

        $guruPermsBefore = $this->guruRole->fresh()->permissions->pluck('id')->all();

        $response = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'roles_present' => [$this->guruRole->id],
            'permissions' => [
                $this->guruRole->id => [$invalidPermId],
            ],
        ]);

        // Ditolak dengan status redirect validation error (bukan 500)
        $response->assertStatus(302);
        $response->assertSessionHasErrors("permissions.{$this->guruRole->id}.0");

        // Permission yang tersimpan tidak berubah
        $this->assertEquals($guruPermsBefore, $this->guruRole->fresh()->permissions->pluck('id')->all());
    }

    /**
     * Test 6 — Payload kosong atau tidak lengkap
     * test_sync_permissions_rejects_malformed_payload_without_wiping_roles
     */
    public function test_sync_permissions_rejects_malformed_payload_without_wiping_roles(): void
    {
        $wakaPermsBefore = $this->wakaRole->permissions()->count();
        $guruPermsBefore = $this->guruRole->permissions()->count();
        $this->assertGreaterThan(0, $wakaPermsBefore);
        $this->assertGreaterThan(0, $guruPermsBefore);

        // 1. POST kosong total
        $response1 = $this->actingAs($this->superAdmin)->post(route('roles.update'), []);
        $response1->assertStatus(302);
        $response1->assertSessionHasErrors('permissions');

        // Pastikan tidak ada satupun role yang terhapus permission-nya
        $this->assertEquals($wakaPermsBefore, $this->wakaRole->fresh()->permissions()->count());
        $this->assertEquals($guruPermsBefore, $this->guruRole->fresh()->permissions()->count());

        // 2. POST dengan permissions array kosong [] tanpa roles_present
        $response2 = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'permissions' => [],
        ]);
        $response2->assertStatus(302);
        $response2->assertSessionHasErrors('permissions');

        $this->assertEquals($wakaPermsBefore, $this->wakaRole->fresh()->permissions()->count());
        $this->assertEquals($guruPermsBefore, $this->guruRole->fresh()->permissions()->count());

        // 3. Payload tidak konsisten: permissions memuat role yang tidak ada dalam roles_present
        $response3 = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'roles_present' => [$this->guruRole->id],
            'permissions' => [
                $this->wakaRole->id => [1], // wakaRole tidak ada di roles_present
            ],
        ]);
        $response3->assertStatus(302);
        $response3->assertSessionHasErrors('permissions');

        $this->assertEquals($wakaPermsBefore, $this->wakaRole->fresh()->permissions()->count());
        $this->assertEquals($guruPermsBefore, $this->guruRole->fresh()->permissions()->count());
    }

    /**
     * Test 7 — Pengosongan permission yang disengaja
     * test_sync_permissions_allows_intentionally_empty_permissions_for_selected_role
     */
    public function test_sync_permissions_allows_intentionally_empty_permissions_for_selected_role(): void
    {
        $wakaPermsBefore = $this->wakaRole->permissions()->count();
        $this->assertGreaterThan(0, $wakaPermsBefore);

        // Dalam formulir web, roles_present mengirim daftar role di halaman,
        // tetapi checkboxes guru sengaja tidak dicentang (permissions[$guru->id] tidak dikirim)
        $response = $this->actingAs($this->superAdmin)->post(route('roles.update'), [
            'roles_present' => [$this->guruRole->id, $this->wakaRole->id],
            'permissions' => [
                // $guruRole sengaja kosong/tidak ada di permissions
                $this->wakaRole->id => $this->wakaRole->permissions->pluck('id')->all(),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Guru dikosongkan secara sah karena sengaja di-uncheck
        $this->assertEquals(0, $this->guruRole->fresh()->permissions()->count());

        // Waka tetap mempertahankan seluruh permission aslinya
        $this->assertEquals($wakaPermsBefore, $this->wakaRole->fresh()->permissions()->count());
    }

    /**
     * Test 8 — Rollback transaksi
     * test_sync_permissions_rolls_back_on_failure_inside_transaction
     */
    public function test_sync_permissions_rolls_back_on_failure_inside_transaction(): void
    {
        $guruPermsBefore = $this->guruRole->fresh()->permissions->pluck('id')->sort()->values()->all();
        $supervisorPermsBefore = $this->supervisorRole->fresh()->permissions->pluck('id')->sort()->values()->all();

        $permUpload = Permission::where('slug', 'upload_rpp')->firstOrFail();
        $permViewOwn = Permission::where('slug', 'view_own_supervision')->firstOrFail();

        // Memicu kegagalan saat iterasi role berlangsung di dalam DB::transaction pada controller
        $queryCount = 0;
        $shouldFail = true;
        DB::listen(function ($query) use (&$queryCount, &$shouldFail) {
            if ($shouldFail && str_contains($query->sql, 'role_permissions')) {
                $queryCount++;
                // Simulasikan kegagalan database setelah setidaknya satu query role_permissions terjadi
                if ($queryCount >= 3) {
                    throw new \RuntimeException('Simulasi kegagalan query database saat sinkronisasi');
                }
            }
        });

        try {
            $this->actingAs($this->superAdmin)->post(route('roles.update'), [
                'roles_present' => [$this->guruRole->id, $this->supervisorRole->id],
                'permissions' => [
                    $this->guruRole->id => [$permUpload->id],
                    $this->supervisorRole->id => [$permViewOwn->id],
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulasi kegagalan query database', $e->getMessage());
        } finally {
            $shouldFail = false;
        }

        // Pastikan perubahan pada guruRole maupun supervisorRole di-rollback secara atomik ke kondisi semula
        $this->assertEquals($guruPermsBefore, $this->guruRole->fresh()->permissions->pluck('id')->sort()->values()->all());
        $this->assertEquals($supervisorPermsBefore, $this->supervisorRole->fresh()->permissions->pluck('id')->sort()->values()->all());
    }

    /**
     * Test 9 — Validasi role baru
     * test_store_new_role_validates_required_fields_and_unique_slug
     */
    public function test_store_new_role_validates_required_fields_and_unique_slug(): void
    {
        // 1. Validasi field wajib
        $resEmpty = $this->actingAs($this->superAdmin)->post(route('roles.store-role'), []);
        $resEmpty->assertStatus(302);
        $resEmpty->assertSessionHasErrors(['name', 'slug']);

        // 2. Validasi slug duplikat
        $resDuplicate = $this->actingAs($this->superAdmin)->post(route('roles.store-role'), [
            'name' => 'Guru Duplikat',
            'slug' => 'guru', // Slug yang sudah ada
            'description' => 'Test duplikat',
        ]);
        $resDuplicate->assertStatus(302);
        $resDuplicate->assertSessionHasErrors('slug');

        // 3. Sukses membuat role baru dengan slug valid
        $resSuccess = $this->actingAs($this->superAdmin)->post(route('roles.store-role'), [
            'name' => 'Pengawas Sekolah',
            'slug' => 'pengawas_sekolah',
            'description' => 'Pengawas Pembina SMK',
        ]);
        $resSuccess->assertRedirect();
        $resSuccess->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'Pengawas Sekolah',
            'slug' => 'pengawas_sekolah',
        ]);
    }

    /**
     * Test 10 — Pengguna non-super admin dilarang mencabut izin manage_roles dari seluruh perannya (Self-Lockout Prevention).
     */
    public function test_non_super_admin_cannot_self_lockout_by_stripping_manage_roles(): void
    {
        $permManageRoles = Permission::where('slug', 'manage_roles')->firstOrFail();

        // Berikan izin manage_roles kepada waka_kurikulum
        $this->wakaRole->permissions()->syncWithoutDetaching([$permManageRoles->id]);

        // Waka kurikulum login dan mencoba mencabut manage_roles dari perannya sendiri
        $response = $this->actingAs($this->wakaKurikulum)->post(route('roles.update'), [
            'roles_present' => [$this->wakaRole->id],
            'permissions' => [
                $this->wakaRole->id => [], // Mencabut semua izin termasuk manage_roles
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('permissions');

        // Izin manage_roles harus tetap ada pada wakaRole
        $this->assertTrue($this->wakaRole->fresh()->permissions->contains('slug', 'manage_roles'));
    }

    /**
     * Test 11 — Akun administrator yang memiliki role teknis super_admin dan position 'administrator' memiliki akses penuh ke administrasi sistem.
     */
    public function test_administrator_account_with_super_admin_role_has_full_administrative_access(): void
    {
        $adminUser = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $this->assertTrue($adminUser->isSuperAdmin());
        $this->assertTrue($adminUser->isSystemAdmin());
        $this->assertTrue($adminUser->hasRole('super_admin'));
        $this->assertTrue($adminUser->hasPermission('manage_roles'));

        // Dapat mengakses halaman matriks roles
        $this->actingAs($adminUser)
            ->get(route('roles.index'))
            ->assertStatus(200);

        // Dapat mengakses rute superadmin
        $this->actingAs($adminUser)
            ->get(route('users.index'))
            ->assertStatus(200);
    }

    /**
     * Test 12 — Akun dengan position 'administrator' tanpa role teknis super_admin TIDAK memperoleh akses root / universal bypass.
     */
    public function test_user_with_administrator_position_without_super_admin_role_does_not_get_root_bypass(): void
    {
        $bogusAdmin = User::factory()->create([
            'role_id' => $this->guruRole->id,
            'position' => 'administrator',
            'is_active' => true,
        ]);

        $this->assertFalse($bogusAdmin->isSuperAdmin());
        $this->assertFalse($bogusAdmin->hasRole('super_admin'));
        $this->assertFalse($bogusAdmin->hasPermission('manage_roles'));

        // Dilarang mengakses modul administrasi yang diproteksi role:super_admin
        $this->actingAs($bogusAdmin)
            ->get(route('users.index'))
            ->assertStatus(403);

        $this->actingAs($bogusAdmin)
            ->get(route('roles.index'))
            ->assertStatus(403);
    }

    /**
     * Test 13 — Akun guru dan staf tidak memperoleh hak akses root dan pemeriksaan role biasa tetap berfungsi normal.
     */
    public function test_guru_and_staf_accounts_do_not_have_root_access_and_regular_checks_remain_intact(): void
    {
        $stafRole = Role::where('slug', 'staf')->firstOrFail();
        $stafUser = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'staf',
            'is_active' => true,
        ]);

        $this->assertFalse($this->guru->isSuperAdmin());
        $this->assertFalse($stafUser->isSuperAdmin());

        $this->assertFalse($this->guru->hasRole('super_admin'));
        $this->assertFalse($stafUser->hasRole('super_admin'));

        $this->assertTrue($this->guru->hasRole('guru'));
        $this->assertTrue($stafUser->hasRole('staf'));

        $this->assertFalse($this->guru->hasRole('staf'));
        $this->assertFalse($stafUser->hasRole('guru'));

        $this->actingAs($this->guru)->get(route('users.index'))->assertStatus(403);
        $this->actingAs($stafUser)->get(route('users.index'))->assertStatus(403);
    }
}
