<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $teacher;

    protected Role $superAdminRole;

    protected Role $guruRole;

    protected Role $kurikulumRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => 'super_admin',
            'description' => 'Super Administrator Sistem',
            'is_system' => true,
        ]);

        $this->guruRole = Role::create([
            'name' => 'Guru Pengajar',
            'slug' => 'guru',
            'description' => 'Guru Pengajar',
            'is_system' => true,
        ]);

        $this->kurikulumRole = Role::create([
            'name' => 'Waka Kurikulum',
            'slug' => 'waka_kurikulum',
            'description' => 'Waka Kurikulum',
            'is_system' => false,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Utama',
            'username' => 'superadmin',
            'email' => 'superadmin@sekolah.sch.id',
            'password' => Hash::make('password123'),
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Guru Pengajar',
            'username' => 'guru1',
            'email' => 'guru1@sekolah.sch.id',
            'password' => Hash::make('password123'),
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_view_user_management_page(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Manajemen Akun Pengguna');
        $response->assertSee($this->superAdmin->name);
        $response->assertSee($this->teacher->name);
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_create_a_second_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.store'), [
                'name' => 'Super Admin Kedua',
                'short_name' => 'Admin 2',
                'username' => 'admin2',
                'email' => 'admin2@sekolah.sch.id',
                'role_id' => $this->superAdminRole->id,
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
                'position' => 'Super Admin Cadangan',
                'phone' => '081299998888',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'admin2',
            'email' => 'admin2@sekolah.sch.id',
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $newUser = User::where('username', 'admin2')->first();
        $this->assertTrue(Hash::check('secret1234', $newUser->password));
        $this->assertTrue($newUser->isSuperAdmin());
    }

    public function test_super_admin_can_update_user_details(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->put(route('users.update', $this->teacher), [
                'name' => 'Guru Pengajar Updated',
                'short_name' => 'Guru Edit',
                'username' => 'guru1_edited',
                'email' => 'guru1_new@sekolah.sch.id',
                'role_id' => $this->kurikulumRole->id,
                'position' => 'Koordinator Kurikulum',
                'nip' => '198501012010011001',
                'phone' => '081234567890',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->teacher->id,
            'name' => 'Guru Pengajar Updated',
            'username' => 'guru1_edited',
            'email' => 'guru1_new@sekolah.sch.id',
            'role_id' => $this->kurikulumRole->id,
            'nip' => '198501012010011001',
        ]);
    }

    public function test_super_admin_cannot_demote_themselves_if_they_are_the_only_super_admin(): void
    {
        // There is only 1 super admin in setUp()
        $response = $this->actingAs($this->superAdmin)
            ->put(route('users.update', $this->superAdmin), [
                'name' => $this->superAdmin->name,
                'username' => $this->superAdmin->username,
                'email' => $this->superAdmin->email,
                'role_id' => $this->guruRole->id, // Try demoting to guru
                'is_active' => 1,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
            'role_id' => $this->superAdminRole->id,
        ]);
    }

    public function test_super_admin_can_reset_user_password(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.reset-password', $this->teacher), [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->teacher->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->teacher->password));
    }

    public function test_super_admin_can_toggle_user_status(): void
    {
        $this->assertTrue($this->teacher->is_active);

        // Toggle to inactive
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.toggle-status', $this->teacher));

        $response->assertRedirect(route('users.index'));
        $this->assertFalse($this->teacher->fresh()->is_active);

        // Toggle back to active
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.toggle-status', $this->teacher));

        $response->assertRedirect(route('users.index'));
        $this->assertTrue($this->teacher->fresh()->is_active);
    }

    public function test_super_admin_cannot_toggle_their_own_status(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.toggle-status', $this->superAdmin));

        $response->assertSessionHas('error');
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->superAdmin));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_super_admin_can_delete_a_user_without_official_records(): void
    {
        $userToDelete = User::create([
            'name' => 'Akun Sementara',
            'username' => 'temp_user',
            'email' => 'temp@test.com',
            'password' => Hash::make('password'),
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $userToDelete));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }
}
