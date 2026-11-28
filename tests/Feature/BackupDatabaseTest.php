<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($this->backupDir);
    }

    protected function tearDown(): void
    {
        foreach (File::files($this->backupDir) as $file) {
            if (str_contains($file->getFilename(), '_test_cleanup_') || str_ends_with($file->getFilename(), '.sqlite.gz')) {
                File::delete($file->getRealPath());
            }
        }

        parent::tearDown();
    }

    public function test_app_backup_command_creates_backup_and_applies_retention(): void
    {
        $oldDailyFile = "{$this->backupDir}/siap_test_cleanup_2020-01-02_020000.sql.gz";
        File::put($oldDailyFile, 'old');

        $this->artisan('app:backup --keep-days=7 --keep-weekly=4 --keep-monthly=6')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($oldDailyFile);

        $createdSqliteBackups = collect(File::files($this->backupDir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sqlite.gz'));

        $this->assertNotEmpty($createdSqliteBackups);
    }

    public function test_super_admin_can_manage_backups_and_guru_is_forbidden(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

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

        $this->actingAs($guru)
            ->get(route('backups.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('backups.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('backups.store'))
            ->assertRedirect();

        $backupFile = collect(File::files($this->backupDir))
            ->first(fn ($f) => str_ends_with($f->getFilename(), '.sqlite.gz'));

        $this->assertNotNull($backupFile);

        $this->actingAs($admin)
            ->get(route('backups.download', $backupFile->getFilename()))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('backups.destroy', $backupFile->getFilename()))
            ->assertRedirect();

        $this->assertFileDoesNotExist($backupFile->getRealPath());
    }
}
