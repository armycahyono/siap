<?php

namespace Tests\Feature;

use App\Models\SchoolStakeholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolStakeholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authorized_user_can_view_stakeholders_page(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('stakeholders.index'));

        $response->assertStatus(200);
        $response->assertSee('Pihak Terkait');
        $response->assertSee('Pengurus Yayasan');
        $response->assertSee('Pengurus Komite Sekolah');
        $response->assertSee('Pengawas Sekolah / Pembina');
        $response->assertSee('Pejabat Dinas Pendidikan');
    }

    public function test_can_filter_stakeholders_by_category(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get(route('stakeholders.index', ['category' => 'pengawas']));

        $response->assertStatus(200);
        $response->assertSee('Pengawas Pembina SMK');
        $response->assertDontSee('H. Sunaryo, S.Sos.');
    }

    public function test_can_create_new_stakeholder(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->post(route('stakeholders.store'), [
            'category' => 'pengawas',
            'name' => 'Dra. Hj. Siti Fatimah, M.Pd.',
            'position' => 'Pengawas Manajerial SMK',
            'institution' => 'Cabang Dinas Pendidikan Wilayah Kediri',
            'nip' => '19690101 199403 2 005',
            'rank_group' => 'Pembina Utama Muda, IV/c',
            'phone' => '081234567899',
            'email' => 'siti.pengawas@jatimprov.go.id',
            'is_signatory' => '1',
            'endorsement_title' => 'Menyetujui,',
            'order_index' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('stakeholders.index', ['category' => 'pengawas']));
        $this->assertDatabaseHas('school_stakeholders', [
            'name' => 'Dra. Hj. Siti Fatimah, M.Pd.',
            'position' => 'Pengawas Manajerial SMK',
            'nip' => '19690101 199403 2 005',
            'is_signatory' => 1,
        ]);
    }

    public function test_can_update_stakeholder(): void
    {
        $waka = User::where('username', 'waka')->first();
        $stakeholder = SchoolStakeholder::first();

        $response = $this->actingAs($waka)->put(route('stakeholders.update', $stakeholder), [
            'category' => $stakeholder->category,
            'name' => 'Nama Baru Diperbarui',
            'position' => 'Jabatan Diperbarui',
            'institution' => $stakeholder->institution,
            'is_signatory' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('stakeholders.index', ['category' => $stakeholder->category]));
        $this->assertDatabaseHas('school_stakeholders', [
            'id' => $stakeholder->id,
            'name' => 'Nama Baru Diperbarui',
            'position' => 'Jabatan Diperbarui',
        ]);
    }

    public function test_can_delete_stakeholder(): void
    {
        $waka = User::where('username', 'waka')->first();
        $stakeholder = SchoolStakeholder::create([
            'category' => 'komite',
            'name' => 'Anggota Komite Percobaan',
            'position' => 'Anggota Komite',
            'institution' => 'Komite SMK PGRI Kras',
            'is_signatory' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($waka)->delete(route('stakeholders.destroy', $stakeholder));

        $response->assertRedirect(route('stakeholders.index'));
        $this->assertDatabaseMissing('school_stakeholders', [
            'id' => $stakeholder->id,
        ]);
    }
}
