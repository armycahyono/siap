<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Supervision;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminMenuTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $teacher;

    protected Role $superAdminRole;

    protected Role $guruRole;

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

        $this->superAdmin = User::create([
            'name' => 'Super Admin Test',
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Guru Test',
            'username' => 'guru_test',
            'email' => 'guru@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'grade_a_min' => 86,
            'grade_b_min' => 76,
            'grade_c_min' => 61,
        ]);
    }

    public function test_super_admin_can_access_active_users_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('active-users.index'));

        $response->assertStatus(200);
        $response->assertSee('Monitoring User Aktif & Sesi Login');
        $response->assertSee('Sesi Online Real-Time');
        $response->assertSee('Semua Akun Pengguna');
    }

    public function test_non_super_admin_cannot_access_active_users_page(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('active-users.index'));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_activity_logs_page(): void
    {
        ActivityLogger::log('login', 'User berhasil login ke sistem', null, null, $this->superAdmin);

        $response = $this->actingAs($this->superAdmin)->get(route('activity-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Pengguna');
        $response->assertSee('User berhasil login ke sistem');
    }

    public function test_non_super_admin_cannot_access_activity_logs_page(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('activity-logs.index'));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_force_logout_user_session(): void
    {
        $sessionId = 'test_session_token_123';
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $this->teacher->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome Windows',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('active-users.destroy-session', $sessionId));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);

        // Verifikasi tercatat di log aktivitas
        $this->assertDatabaseHas('user_activity_logs', [
            'action' => 'force_logout',
        ]);
    }

    public function test_super_admin_can_toggle_user_active_status(): void
    {
        $this->assertTrue($this->teacher->is_active);

        // Nonaktifkan guru
        $response = $this->actingAs($this->superAdmin)
            ->post(route('active-users.toggle-status', $this->teacher));

        $response->assertRedirect();
        $this->assertFalse($this->teacher->fresh()->is_active);

        // Verifikasi tercatat di log aktivitas
        $this->assertDatabaseHas('user_activity_logs', [
            'action' => 'toggle_status',
        ]);

        // Aktifkan kembali
        $this->actingAs($this->superAdmin)
            ->post(route('active-users.toggle-status', $this->teacher));

        $this->assertTrue($this->teacher->fresh()->is_active);
    }

    public function test_super_admin_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('active-users.toggle-status', $this->superAdmin));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_super_admin_can_clear_activity_logs(): void
    {
        ActivityLogger::log('create', 'Log aktivitas 1', null, null, $this->superAdmin);
        ActivityLogger::log('update', 'Log aktivitas 2', null, null, $this->superAdmin);

        $this->assertEquals(2, UserActivityLog::count());

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('activity-logs.clear', ['range' => 'all']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Sisa hanya 1 log pembersihan itu sendiri
        $this->assertDatabaseHas('user_activity_logs', [
            'action' => 'delete',
        ]);
    }

    public function test_auth_actions_are_recorded_in_activity_logs(): void
    {
        // 1. Login
        $this->post('/login', [
            'login' => 'guru_test',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->teacher->id,
            'action' => 'login',
        ]);

        // 2. Logout
        $this->actingAs($this->teacher)->post('/logout');

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->teacher->id,
            'action' => 'logout',
        ]);
    }

    public function test_model_crud_lifecycle_is_automatically_recorded_with_diffs(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Create model
        $subject = Subject::create([
            'code' => 'MTK-TEST',
            'name' => 'Matematika Uji Coba',
            'category' => 'Umum',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'create',
            'subject_type' => Subject::class,
            'subject_id' => $subject->id,
        ]);

        // 2. Update model and check before-after diff
        $subject->update([
            'name' => 'Matematika Terapan Lanjut',
        ]);

        $updateLog = UserActivityLog::where('subject_type', Subject::class)
            ->where('subject_id', $subject->id)
            ->where('action', 'update')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertArrayHasKey('perubahan', $updateLog->properties);
        $this->assertEquals('Matematika Uji Coba', $updateLog->properties['perubahan']['name']['lama']);
        $this->assertEquals('Matematika Terapan Lanjut', $updateLog->properties['perubahan']['name']['baru']);

        // 3. Delete model
        $subject->delete();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'delete',
            'subject_type' => Subject::class,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_supervision_approval_and_rejection_are_recorded_in_activity_logs(): void
    {
        $kepsekRole = Role::create([
            'name' => 'Kepala Sekolah',
            'slug' => 'kepala_sekolah',
            'is_system' => true,
        ]);

        $kepsek = User::create([
            'name' => 'Kepala Sekolah Test',
            'username' => 'kepsek_test',
            'email' => 'kepsek@test.com',
            'password' => bcrypt('password'),
            'role_id' => $kepsekRole->id,
            'is_active' => true,
        ]);

        $supervision = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->superAdmin->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '08:00',
            'subject' => 'Pemrograman Web',
            'class_name' => 'XII RPL 1',
            'status' => 'menunggu_persetujuan',
            'total_score' => 88,
            'percentage' => 88,
            'predicate' => 'Baik',
        ]);

        // Rejection test
        $response = $this->actingAs($this->superAdmin)->post(route('approvals.reject', $supervision), [
            'revision_notes' => 'Tolong lengkapi foto observasi dan catatan tindak lanjut.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'reject',
            'subject_type' => Supervision::class,
            'subject_id' => $supervision->id,
        ]);

        // Put back to pending approval
        $supervision->update(['status' => 'menunggu_persetujuan']);

        // Approval test
        $response = $this->actingAs($this->superAdmin)->post(route('approvals.approve', $supervision), [
            'principal_notes' => 'Laporan telah diperiksa dan disetujui.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'approve',
            'subject_type' => Supervision::class,
            'subject_id' => $supervision->id,
        ]);
    }
}
