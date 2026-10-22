<?php

namespace Tests\Feature;

use App\Models\CurriculumEvent;
use App\Models\SchoolActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolActivityMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_waka_can_view_school_activities_index(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('kurikulum.activities.index'));
        $response->assertStatus(200);
        $response->assertSee('Kegiatan Sekolah');
        $response->assertSee('Kurikulum');
        $response->assertSee('Nama Kegiatan');
        $response->assertSee('Total Acuan Kegiatan');
        $response->assertSee('Sudah Terjadwal');
        $response->assertSee('Belum Terjadwal');
        $response->assertSee('modalPreviewActivity');
        $response->assertSee('openPreviewModal');
    }

    public function test_waka_can_create_new_school_activity(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->post(route('kurikulum.activities.store'), [
            'name' => 'Workshop Kurikulum Merdeka Terapan',
            'code' => 'WKM-2026',
            'category' => 'smk_khusus',
            'target_participant' => 'Seluruh Pendidik dan Tenaga Kependidikan',
            'description' => 'Peningkatan kompetensi pembuatan modul ajar dan asesmen',
            'color' => '#8b5cf6',
            'default_duration_days' => 2,
            'is_mandatory' => 1,
            'is_kaldik' => 0,
            'is_active' => 1,
            'order_index' => 99,
        ]);

        $response->assertRedirect(route('kurikulum.activities.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('school_activities', [
            'code' => 'WKM-2026',
            'name' => 'Workshop Kurikulum Merdeka Terapan',
            'category' => 'smk_khusus',
        ]);
    }

    public function test_waka_can_update_school_activity(): void
    {
        $waka = User::where('username', 'waka')->first();
        $activity = SchoolActivity::first();

        $response = $this->actingAs($waka)->put(route('kurikulum.activities.update', $activity), [
            'name' => 'Nama Kegiatan Diperbarui',
            'code' => 'TEST-UPD',
            'category' => 'akademik_ujian',
            'target_participant' => 'Siswa Kelas XII',
            'description' => 'Deskripsi update',
            'color' => '#10b981',
            'default_duration_days' => 5,
            'is_mandatory' => 1,
            'is_kaldik' => 1,
            'is_active' => 1,
            'order_index' => 10,
        ]);

        $response->assertRedirect(route('kurikulum.activities.index'));
        $this->assertDatabaseHas('school_activities', [
            'id' => $activity->id,
            'name' => 'Nama Kegiatan Diperbarui',
            'code' => 'TEST-UPD',
        ]);
    }

    public function test_waka_can_delete_school_activity(): void
    {
        $waka = User::where('username', 'waka')->first();
        $activity = SchoolActivity::create([
            'name' => 'Kegiatan Khusus Untuk Dihapus',
            'code' => 'DEL-ME',
            'category' => 'lainnya',
            'is_active' => 1,
        ]);

        $response = $this->actingAs($waka)->delete(route('kurikulum.activities.destroy', $activity));
        $response->assertRedirect(route('kurikulum.activities.index'));
        $this->assertDatabaseMissing('school_activities', [
            'id' => $activity->id,
        ]);
    }

    public function test_waka_can_quick_schedule_school_activity_into_curriculum_calendar(): void
    {
        $waka = User::where('username', 'waka')->first();
        $activity = SchoolActivity::create([
            'name' => 'Kunjungan Industri Jurusan TKR ke Astra Motor',
            'code' => 'KI-TKR',
            'category' => 'smk_khusus',
            'color' => '#f59e0b',
            'default_duration_days' => 3,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($waka)->post(route('kurikulum.activities.quick-schedule', $activity), [
            'academic_year' => '2026/2027',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'location' => 'PT Astra Honda Motor Semarang',
            'description' => 'Kunjungan observasi budaya kerja 5S industri',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('curriculum_events', [
            'school_activity_id' => $activity->id,
            'title' => 'Kunjungan Industri Jurusan TKR ke Astra Motor',
            'academic_year' => '2026/2027',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'location' => 'PT Astra Honda Motor Semarang',
        ]);

        // Verify status in index now shows scheduled and has Ubah Jadwal button
        $indexResponse = $this->actingAs($waka)->get(route('kurikulum.activities.index', ['academic_year' => '2026/2027']));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('KI-TKR');
        $indexResponse->assertSee('Terjadwal');
        $indexResponse->assertSee('Ubah Jadwal');
    }

    public function test_waka_can_update_existing_schedule_for_school_activity(): void
    {
        $waka = User::where('username', 'waka')->first();
        $activity = SchoolActivity::create([
            'name' => 'Penilaian Akhir Semester Ganjil',
            'code' => 'PAS-1',
            'category' => 'akademik_ujian',
            'is_active' => 1,
        ]);

        // First schedule
        $this->actingAs($waka)->post(route('kurikulum.activities.quick-schedule', $activity), [
            'academic_year' => '2026/2027',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-25',
            'location' => 'Kampus 1',
        ]);

        $event = CurriculumEvent::where('school_activity_id', $activity->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('2026-11-20', $event->start_date->format('Y-m-d'));

        // Update schedule to new dates and location
        $response = $this->actingAs($waka)->post(route('kurikulum.activities.quick-schedule', $activity), [
            'event_id' => $event->id,
            'academic_year' => '2026/2027',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-06',
            'location' => 'Kampus 1 & 2',
            'notes' => 'Jadwal diundur sesuai edaran dinas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $event->refresh();
        $this->assertEquals('2026-12-01', $event->start_date->format('Y-m-d'));
        $this->assertEquals('2026-12-06', $event->end_date->format('Y-m-d'));
        $this->assertEquals('Kampus 1 & 2', $event->location);
        $this->assertStringContainsString('Jadwal diundur sesuai edaran dinas', $event->description);
    }

    public function test_filtering_school_activities_by_status(): void
    {
        $waka = User::where('username', 'waka')->first();

        // Filter terjadwal
        $resScheduled = $this->actingAs($waka)->get(route('kurikulum.activities.index', [
            'status' => 'terjadwal',
            'academic_year' => '2026/2027',
        ]));
        $resScheduled->assertStatus(200);

        // Filter belum_terjadwal
        $resUnscheduled = $this->actingAs($waka)->get(route('kurikulum.activities.index', [
            'status' => 'belum_terjadwal',
            'academic_year' => '2026/2027',
        ]));
        $resUnscheduled->assertStatus(200);
    }

    public function test_calendar_index_view_contains_master_activity_data(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('kurikulum.calendar.index'));
        $response->assertStatus(200);
        $response->assertSee('Acuan Kegiatan');
        $response->assertSee('Belum Terjadwal');
        $response->assertSee('Pilih dari Acuan Kegiatan Sekolah');
    }
}
