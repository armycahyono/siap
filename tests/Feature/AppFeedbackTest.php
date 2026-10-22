<?php

namespace Tests\Feature;

use App\Models\AppFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_view_feedback_page(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        $response = $this->actingAs($guru)->get(route('feedbacks.index'));

        $response->assertStatus(200);
        $response->assertSee('Catatan & Masukan Perbaikan Aplikasi');
    }

    public function test_user_can_submit_feedback_note_with_optional_attachment(): void
    {
        Storage::fake('public');

        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        $file = UploadedFile::fake()->create('screenshot.png', 500, 'image/png');

        $response = $this->actingAs($guru)->post(route('feedbacks.store'), [
            'category' => 'saran_fitur',
            'priority' => 'tinggi',
            'title' => 'Tambahkan fitur ekspor CSV otomatis',
            'description' => 'Mohon tambahkan tombol untuk mengekspor data observasi langsung ke CSV.',
            'attachment' => $file,
        ]);

        $response->assertRedirect(route('feedbacks.index'));

        $this->assertDatabaseHas('app_feedback', [
            'user_id' => $guru->id,
            'title' => 'Tambahkan fitur ekspor CSV otomatis',
            'category' => 'saran_fitur',
            'priority' => 'tinggi',
            'status' => 'menunggu',
        ]);

        $feedback = AppFeedback::first();
        $this->assertNotNull($feedback->attachment_path);
        Storage::disk('public')->assertExists($feedback->attachment_path);
    }

    public function test_admin_can_respond_and_update_feedback_status(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $waka = User::where('username', 'waka')->first();

        $feedback = AppFeedback::create([
            'user_id' => $guru->id,
            'category' => 'laporan_bug',
            'priority' => 'sedang',
            'title' => 'Tombol cetak kadang tidak merespon di HP',
            'description' => 'Ketika dibuka lewat smartphone, tombol cetak tidak bisa diklik.',
            'status' => 'menunggu',
        ]);

        $response = $this->actingAs($waka)->put(route('feedbacks.response', $feedback), [
            'status' => 'selesai',
            'admin_notes' => 'Kendala telah diperbaiki pada pembaruan responsive view.',
        ]);

        $response->assertRedirect(route('feedbacks.index'));

        $feedback->refresh();
        $this->assertEquals('selesai', $feedback->status);
        $this->assertEquals('Kendala telah diperbaiki pada pembaruan responsive view.', $feedback->admin_notes);
        $this->assertEquals($waka->id, $feedback->responded_by_id);
    }

    public function test_regular_user_cannot_update_feedback_response(): void
    {
        $guru1 = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $guru2 = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->skip(1)->first();

        $feedback = AppFeedback::create([
            'user_id' => $guru1->id,
            'category' => 'saran_fitur',
            'priority' => 'sedang',
            'title' => 'Saran UI',
            'description' => 'Deskripsi',
            'status' => 'menunggu',
        ]);

        $response = $this->actingAs($guru2)->put(route('feedbacks.response', $feedback), [
            'status' => 'selesai',
            'admin_notes' => 'Diubah oleh guru',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_pending_feedback(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        $feedback = AppFeedback::create([
            'user_id' => $guru->id,
            'category' => 'saran_fitur',
            'priority' => 'rendah',
            'title' => 'Salah tulis saran',
            'description' => 'Mau dihapus',
            'status' => 'menunggu',
        ]);

        $response = $this->actingAs($guru)->delete(route('feedbacks.destroy', $feedback));

        $response->assertRedirect(route('feedbacks.index'));
        $this->assertDatabaseMissing('app_feedback', ['id' => $feedback->id]);
    }
}
