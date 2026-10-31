<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileSupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $supervisor;

    protected User $principal;

    protected Supervision $supervision;

    protected SupervisionPhase $phase;

    protected SupervisionIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Storage::fake('local');
        Storage::fake('public');

        SchoolSetting::getSetting()->update([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'grade_a_min' => 86,
            'grade_b_min' => 76,
            'grade_c_min' => 61,
        ]);

        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $ksRole = Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah']);

        $this->teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'name' => 'Guru Target Test',
            'email' => 'guru.target@test.com',
        ]);

        $this->supervisor = User::factory()->create([
            'role_id' => $guruRole->id,
            'name' => 'Supervisor Senior Test',
            'email' => 'spv.senior@test.com',
        ]);

        $this->principal = User::factory()->create([
            'role_id' => $ksRole->id,
            'name' => 'Kepala Sekolah Test',
            'email' => 'ks.test@test.com',
        ]);

        $this->phase = SupervisionPhase::firstOrCreate(
            ['code' => 'A'],
            ['name' => 'Kegiatan Pendahuluan', 'order' => 1, 'is_active' => true]
        );

        $this->indicator = SupervisionIndicator::firstOrCreate(
            ['code' => 'A1'],
            [
                'phase_id' => $this->phase->id,
                'title' => 'Apersepsi dan Motivasi',
                'description' => 'Guru memberikan apersepsi yang relevan',
                'max_score' => 4,
                'order' => 1,
                'is_active' => true,
            ]
        );

        $this->supervision = Supervision::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'subject' => 'Pemrograman Web',
            'class_name' => 'XII RPL 1',
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '08:00 - 09:30',
            'status' => 'terjadwal',
        ]);
    }

    public function test_teacher_and_supervisor_can_access_mobile_supervisions_dashboard(): void
    {
        // 1. As Teacher
        $resTeacher = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/tasks/supervisions');

        $resTeacher->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.counts.as_teacher_total', 1)
            ->assertJsonPath('data.as_teacher.0.id', $this->supervision->id)
            ->assertJsonPath('data.can_approve', false);

        // 2. As Supervisor
        $resSpv = $this->actingAs($this->supervisor)
            ->getJson('/api/v1/mobile/tasks/supervisions');

        $resSpv->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.counts.as_supervisor_total', 1)
            ->assertJsonPath('data.as_supervisor.0.id', $this->supervision->id);

        // 3. As Principal
        $resKs = $this->actingAs($this->principal)
            ->getJson('/api/v1/mobile/tasks/supervisions');

        $resKs->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_approve', true);
    }

    public function test_user_can_view_supervision_detail_with_rubrics_and_permissions(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->getJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->supervision->id)
            ->assertJsonPath('data.subject', 'Pemrograman Web')
            ->assertJsonPath('data.permissions.can_conduct_obs', true)
            ->assertJsonPath('data.permissions.is_supervisor', true);
    }

    public function test_teacher_can_upload_rpp_and_save_pre_observation_via_mobile(): void
    {
        $file = UploadedFile::fake()->create('modul_ajar_web.pdf', 500, 'application/pdf');

        $uploadRes = $this->actingAs($this->teacher)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/rpp", [
                'rpp_file' => $file,
            ]);

        $uploadRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_file', true);

        $this->assertNotNull($this->supervision->fresh()->rpp_file_path);

        $preRes = $this->actingAs($this->teacher)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/praobservasi", [
                'pre_material' => 'REST API Architecture',
                'pre_objectives' => 'Siswa dapat membuat endpoint API',
                'pre_method' => 'Project Based Learning',
                'pre_media' => 'VS Code & Postman',
                'pre_focus' => 'Interaksi aktif antar siswa',
            ]);

        $preRes->assertOk()->assertJsonPath('success', true);

        $fresh = $this->supervision->fresh();
        $this->assertEquals('praobservasi', $fresh->status);
        $this->assertEquals('REST API Architecture', $fresh->pre_material);
    }

    public function test_supervisor_can_score_observation_and_recalculate_percentage_and_predicate(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/observasi", [
                'obs_date' => now()->toDateString(),
                'obs_time' => '08:15',
                'scores' => [
                    $this->indicator->id => 4,
                ],
                'notes' => [
                    $this->indicator->id => 'Apersepsi sangat kontekstual dengan dunia kerja',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'observasi');

        $fresh = $this->supervision->fresh();
        $this->assertEquals(4, $fresh->total_score);
        $this->assertGreaterThan(0, $fresh->percentage);
        $this->assertNotEmpty($fresh->predicate);
    }

    public function test_supervisor_can_upload_and_delete_classroom_photo_via_mobile(): void
    {
        $photoFile = UploadedFile::fake()->image('kbm_active.jpg');

        $uploadRes = $this->actingAs($this->supervisor)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/photos", [
                'photo' => $photoFile,
                'caption' => 'Suasana diskusi kelompok di kelas',
                'category' => 'Kegiatan Pembelajaran',
            ]);

        $uploadRes->assertOk()
            ->assertJsonPath('success', true);

        $photoId = $uploadRes->json('data.id');
        $this->assertDatabaseHas('supervision_photos', [
            'id' => $photoId,
            'supervision_id' => $this->supervision->id,
        ]);

        // Delete photo
        $deleteRes = $this->actingAs($this->supervisor)
            ->deleteJson("/api/v1/mobile/tasks/supervisions/photos/{$photoId}");

        $deleteRes->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('supervision_photos', ['id' => $photoId]);
    }

    public function test_supervisor_can_finalize_and_principal_can_approve_via_mobile(): void
    {
        // 1. Post Observation notes
        $postRes = $this->actingAs($this->supervisor)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/pascaobservasi", [
                'post_positive' => 'Siswa sangat antusias saat praktik coding.',
                'post_improvement' => 'Manajemen waktu pada sesi tanya jawab.',
                'post_recommendation' => 'Gunakan timer digital pada layar proyektor.',
                'post_followup' => 'Diskusi berkala dalam MGMP sekolah.',
            ]);

        $postRes->assertOk()->assertJsonPath('success', true);

        // 2. Finalize by supervisor
        $finalRes = $this->actingAs($this->supervisor)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/finalize");

        $finalRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'menunggu_persetujuan');

        $this->assertEquals('menunggu_persetujuan', $this->supervision->fresh()->status);

        // 3. Approve by Principal
        $apprRes = $this->actingAs($this->principal)
            ->postJson("/api/v1/mobile/tasks/supervisions/{$this->supervision->id}/approve", [
                'action' => 'approve',
                'principal_notes' => 'Sangat memuaskan, pertahankan!',
            ]);

        $apprRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'disetujui');

        $fresh = $this->supervision->fresh();
        $this->assertEquals('disetujui', $fresh->status);
        $this->assertTrue((bool) $fresh->approved_by_principal);
        $this->assertNotEmpty($fresh->principal_approved_at);
        $this->assertNotEmpty($fresh->snapshot_data);
    }
}
