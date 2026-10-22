<?php

namespace Tests\Feature;

use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_supervision_captures_complete_immutable_snapshot(): void
    {
        $supervision = Supervision::first();
        $this->assertNotNull($supervision, 'Seed data exists');

        $snapshot = $supervision->captureSnapshot(true);

        $this->assertTrue($supervision->hasSnapshot());
        $this->assertArrayHasKey('frozen_at', $snapshot);
        $this->assertArrayHasKey('school', $snapshot);
        $this->assertArrayHasKey('teacher', $snapshot);
        $this->assertArrayHasKey('phases', $snapshot);
        $this->assertNotEmpty($snapshot['phases']);
    }

    public function test_report_print_preserves_snapshot_data_even_if_master_changes(): void
    {
        $supervision = Supervision::first();
        $supervision->captureSnapshot(true);

        $originalSchoolName = $supervision->snapshot_data['school']['name'];

        // Change master school setting temporarily
        $setting = SchoolSetting::getSetting();
        $realName = $setting->school_name;
        $setting->update(['school_name' => 'NAMA SEKOLAH BERUBAH TAHUN DEPAN']);

        $user = User::first();
        $response = $this->actingAs($user)->get(route('reports.print', $supervision));

        $response->assertStatus(200);
        // The printed report should render the frozen snapshot school name, NOT the changed master name!
        $response->assertSee($originalSchoolName);
        $response->assertDontSee('NAMA SEKOLAH BERUBAH TAHUN DEPAN');

        // Restore real name
        $setting->update(['school_name' => $realName]);
    }

    public function test_principal_approval_automatically_freezes_snapshot(): void
    {
        $supervision = Supervision::where('status', 'menunggu_persetujuan')->first();
        if (! $supervision) {
            $supervision = Supervision::first();
            $supervision->update(['status' => 'menunggu_persetujuan', 'snapshot_data' => null]);
        } else {
            $supervision->update(['snapshot_data' => null]);
        }

        $principal = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first();
        $this->assertNotNull($principal);

        $response = $this->actingAs($principal)->post(route('approvals.approve', $supervision), [
            'principal_notes' => 'Disetujui untuk arsip sekolah.',
        ]);

        $response->assertRedirect(route('approvals.index'));

        $supervision->refresh();
        $this->assertEquals('disetujui', $supervision->status);
        $this->assertTrue($supervision->hasSnapshot());
        $this->assertEquals('Disetujui untuk arsip sekolah.', $supervision->snapshot_data['principal']['notes']);
    }
}
