<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAndRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_teacher_cannot_create_or_modify_schedules(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // Accessing create schedule
        $response = $this->actingAs($guru)->get(route('schedules.create'));
        $response->assertStatus(403);

        // Submitting new schedule
        $response = $this->actingAs($guru)->post(route('schedules.store'), [
            'teacher_id' => $guru->id,
            'schedule_date' => now()->addDays(2)->toDateString(),
            'schedule_time' => '08:00',
            'subject' => 'Matematika',
            'class_name' => 'X TKJ 1',
        ]);
        $response->assertStatus(403);
    }

    public function test_teacher_cannot_grade_or_conduct_supervision(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $supervision = Supervision::where('teacher_id', $guru->id)->first();

        $this->assertNotNull($supervision);

        // Attempting to save pre-observation as teacher
        $response = $this->actingAs($guru)->post(route('supervisions.save-pre-observation', $supervision), [
            'pre_goals' => 'Tujuan',
            'pre_method' => 'Metode',
        ]);
        $response->assertStatus(403);

        // Attempting to save observation grades as teacher
        $response = $this->actingAs($guru)->post(route('supervisions.save-observation', $supervision), [
            'scores' => [1 => 4, 2 => 4],
        ]);
        $response->assertStatus(403);

        // Attempting to save post-observation as teacher
        $response = $this->actingAs($guru)->post(route('supervisions.save-post-observation', $supervision), [
            'post_strengths' => 'Catatan',
        ]);
        $response->assertStatus(403);
    }

    public function test_unassigned_supervisor_cannot_grade_other_teachers_supervision(): void
    {
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        $supervisors = User::whereHas('role', fn ($q) => $q->where('slug', 'supervisor'))->get();

        if ($supervisors->count() < 2) {
            $otherSupervisor = User::factory()->create([
                'role_id' => $supervisors->first()?->role_id ?? $supervisorRole?->id,
            ]);
        } else {
            $otherSupervisor = $supervisors->last();
        }

        $supervision = Supervision::where('supervisor_id', '!=', $otherSupervisor->id)->first();
        $this->assertNotNull($supervision);

        $response = $this->actingAs($otherSupervisor)->post(route('supervisions.save-observation', $supervision), [
            'scores' => [1 => 4],
        ]);
        $response->assertStatus(403);
    }

    public function test_teacher_cannot_access_master_data_or_settings(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // Departments
        $response = $this->actingAs($guru)->get(route('departments.index'));
        $response->assertStatus(403);

        // Subjects
        $response = $this->actingAs($guru)->get(route('subjects.index'));
        $response->assertStatus(403);

        // Settings (Profil Sekolah dapat dilihat semua user, tetapi guru tidak bisa mengedit)
        $response = $this->actingAs($guru)->get(route('settings.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($guru)->post(route('settings.update'), ['school_name' => 'Hack']);
        $response->assertStatus(403);

        // Recap
        $response = $this->actingAs($guru)->get(route('reports.recap'));
        $response->assertStatus(403);
    }

    public function test_supervisor_cannot_approve_supervision_as_principal(): void
    {
        $supervisor = User::whereHas('role', fn ($q) => $q->where('slug', 'supervisor'))->first()
            ?? User::factory()->create(['role_id' => Role::where('slug', 'supervisor')->value('id')]);
        $supervision = Supervision::first();

        $response = $this->actingAs($supervisor)->post(route('approvals.approve', $supervision));
        $response->assertStatus(403);
    }

    public function test_cannot_edit_or_alter_supervision_once_approved(): void
    {
        $supervision = Supervision::first();
        $supervisor = $supervision->supervisor;

        $supervision->update([
            'status' => 'disetujui',
            'approved_by_principal' => true,
            'principal_approved_at' => now(),
        ]);

        // Supervisor attempts to alter observation on approved document
        $response = $this->actingAs($supervisor)->post(route('supervisions.save-observation', $supervision), [
            'scores' => [1 => 1],
        ]);
        $response->assertStatus(403);
    }

    public function test_waka_kurikulum_can_access_and_approve_supervision(): void
    {
        $waka = User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first();
        $this->assertNotNull($waka);

        $supervision = Supervision::first();
        $supervision->update(['status' => 'menunggu_persetujuan', 'approved_by_principal' => false]);

        $response = $this->actingAs($waka)->get(route('approvals.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($waka)->post(route('approvals.approve', $supervision), [
            'principal_notes' => 'Disetujui oleh Waka Kurikulum.',
        ]);

        $response->assertRedirect(route('approvals.index'));
        $supervision->refresh();
        $this->assertEquals('disetujui', $supervision->status);
        $this->assertTrue($supervision->approved_by_principal);
    }
}
