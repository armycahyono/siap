<?php

namespace Tests\Feature;

use App\Models\Cbt\CbtUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtPasswordSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Role $guruRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guruRole = Role::firstOrCreate(
            ['slug' => 'guru'],
            ['name' => 'Guru', 'level' => 3, 'is_active' => true]
        );
    }

    public function test_password_change_in_siap_automatically_syncs_to_cbt(): void
    {
        $uniqueUsername = 'guru_sync_test_'.uniqid();
        $initialPassword = 'old-password-123';

        // 1. Create SIAP User
        $user = User::create([
            'name' => 'Guru Sync Test',
            'username' => $uniqueUsername,
            'email' => $uniqueUsername.'@example.com',
            'password' => $initialPassword,
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        // 2. Create matching CBT User
        $cbtUser = CbtUser::create([
            'username' => $uniqueUsername,
            'email' => $user->email,
            'password' => $user->password,
            'active' => 1,
            'first_name' => 'Guru',
            'last_name' => 'Sync Test',
        ]);

        $newPassword = 'new-secure-password-456';
        $user->update(['password' => $newPassword]);

        // Refresh CBT user from database
        $cbtUser->refresh();

        $this->assertEquals($user->password, $cbtUser->password);
        $this->assertTrue(Hash::check($newPassword, $cbtUser->password));
        $this->assertNotNull($user->password_changed_at);

        // Cleanup CBT user
        $cbtUser->delete();
    }

    public function test_password_change_in_cbt_automatically_syncs_to_siap(): void
    {
        $uniqueUsername = 'cbt_to_siap_'.uniqid();
        $initialPassword = 'initial-cbt-pass';

        // 1. Create SIAP User
        $user = User::create([
            'name' => 'CBT to SIAP Test',
            'username' => $uniqueUsername,
            'email' => $uniqueUsername.'@example.com',
            'password' => $initialPassword,
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        // 2. Create matching CBT User
        $cbtUser = CbtUser::create([
            'username' => $uniqueUsername,
            'email' => $user->email,
            'password' => $user->password,
            'active' => 1,
            'first_name' => 'CBT',
            'last_name' => 'Test',
        ]);

        $newCbtPasswordHash = Hash::make('updated-from-cbt-789');
        $cbtUser->update(['password' => $newCbtPasswordHash]);

        // Refresh SIAP user from database
        $user->refresh();

        $this->assertEquals($newCbtPasswordHash, $user->password);
        $this->assertTrue(Hash::check('updated-from-cbt-789', $user->password));

        // Cleanup CBT user
        $cbtUser->delete();
    }

    public function test_user_soft_deletes_work_properly(): void
    {
        $uniqueUsername = 'soft_delete_user_'.uniqid();

        $user = User::create([
            'name' => 'Soft Delete User',
            'username' => $uniqueUsername,
            'email' => $uniqueUsername.'@example.com',
            'password' => 'secret123',
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        $userId = $user->id;
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNull(User::find($userId));
        $this->assertNotNull(User::withTrashed()->find($userId));
    }
}
