<?php

namespace Tests\Feature;

use App\Models\SupervisionIndicator;
use App\Models\SupervisionPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilineSupervisionIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_store_and_display_multiline_indicator(): void
    {
        $user = User::where('username', 'superadmin')->first();

        $phase = SupervisionPhase::firstOrCreate(
            ['code' => 'A'],
            ['name' => 'Kegiatan Pendahuluan', 'order' => 1, 'is_active' => true]
        );

        $multilineTitle = "Tindakan implementasi perencanaan selaras dengan perencanaan pembelajaran pada:\na. awal pembelajaran\nb. inti pembelajaran dalam proses memahami, mengaplikasikan dan merefleksi\nc. penutupan pembelajaran";

        $response = $this->actingAs($user)->post(route('rubrics.store-indicator'), [
            'phase_id' => $phase->id,
            'code' => 'A.1',
            'title' => $multilineTitle,
            'description' => "Panduan amatan:\n- Amati keselarasan RPP\n- Catat respon siswa",
            'max_score' => 4,
            'order' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('supervision_indicators', [
            'code' => 'A.1',
            'phase_id' => $phase->id,
            'title' => $multilineTitle,
        ]);

        $indicator = SupervisionIndicator::where('code', 'A.1')->where('phase_id', $phase->id)->first();
        $this->assertNotNull($indicator);

        // Verify index view renders with pre-line
        $indexResponse = $this->actingAs($user)->get(route('rubrics.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('white-space: pre-line', false);
        $indexResponse->assertSee($multilineTitle);

        // Verify updating with longer multiline text (> 255 chars)
        $longerTitle = $multilineTitle."\nd. tindak lanjut asesmen formatif berkelanjutan untuk memastikan ketercapaian kompetensi seluruh peserta didik.";
        $this->assertGreaterThan(255, mb_strlen($longerTitle));

        $updateResponse = $this->actingAs($user)->put(route('rubrics.update-indicator', $indicator), [
            'phase_id' => $phase->id,
            'code' => 'A.1',
            'title' => $longerTitle,
            'description' => 'Diperbarui',
            'max_score' => 4,
            'order' => 1,
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect();
        $indicator->refresh();
        $this->assertEquals($longerTitle, $indicator->title);
    }
}
