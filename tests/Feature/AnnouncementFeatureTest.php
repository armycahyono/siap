<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\TelegramChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnnouncementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $principal;

    protected User $waka;

    protected User $kajur;

    protected User $teacher;

    protected User $piketTeacher;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        SchoolSetting::firstOrCreate(['id' => 1])->update([
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_notifications_enabled' => true,
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator']);
        $ksRole = Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah']);
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $piketRole = Role::firstOrCreate(['slug' => 'guru_piket'], ['name' => 'Guru Piket']);

        $this->admin = User::firstOrCreate(['username' => 'admin_test'], [
            'name' => 'Admin Test',
            'email' => 'admin_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->principal = User::create([
            'name' => 'Bpk. Kepala Sekolah',
            'username' => 'ks_test',
            'email' => 'ks_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $ksRole->id,
            'is_active' => true,
        ]);

        $this->waka = User::create([
            'name' => 'Ibu Waka Kurikulum',
            'username' => 'waka_test',
            'email' => 'waka_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $wakaRole->id,
            'is_active' => true,
        ]);

        $this->kajur = User::create([
            'name' => 'Bpk. Kaprogli TKJ',
            'username' => 'kajur_test',
            'email' => 'kajur_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'position' => 'Kaprogli TKJ',
            'is_active' => true,
        ]);

        $this->department = Department::firstOrCreate(
            ['code' => 'TKJ'],
            [
                'name' => 'Teknik Komputer dan Jaringan',
                'head_of_department_id' => $this->kajur->id,
                'is_active' => true,
            ]
        );
        $this->department->update(['head_of_department_id' => $this->kajur->id]);

        $this->teacher = User::create([
            'name' => 'Guru Biasa',
            'username' => 'guru_biasa',
            'email' => 'guru_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $this->piketTeacher = User::create([
            'name' => 'Guru Piket Test',
            'username' => 'guru_piket_test',
            'email' => 'piket_ann@test.local',
            'password' => bcrypt('password'),
            'role_id' => $piketRole->id,
            'is_active' => true,
        ]);
    }

    public function test_only_authorized_leaders_can_publish_announcements(): void
    {
        // 1. Regular Teacher attempting to create announcement -> 403 Forbidden
        $response = $this->actingAs($this->teacher)->post(route('announcements.store'), [
            'title' => 'Pengumuman Ilegal dari Guru',
            'content' => 'Ini tidak boleh lolos',
            'target_type' => 'all',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('announcements', ['title' => 'Pengumuman Ilegal dari Guru']);

        // 2. Kepala Sekolah creates announcement -> Success
        $responseKS = $this->actingAs($this->principal)->post(route('announcements.store'), [
            'title' => 'Instruksi Penting Kepala Sekolah',
            'content' => 'Seluruh GTK wajib hadir apel pagi.',
            'target_type' => 'all',
        ]);
        $responseKS->assertRedirect(route('announcements.index'));
        $this->assertDatabaseHas('announcements', ['title' => 'Instruksi Penting Kepala Sekolah']);

        // 3. Waka Kurikulum creates announcement -> Success
        $responseWaka = $this->actingAs($this->waka)->post(route('announcements.store'), [
            'title' => 'Jadwal Penilaian Akhir Semester',
            'content' => 'Dimohon bapak ibu guru menyerahkan naskah soal.',
            'target_type' => 'all',
        ]);
        $responseWaka->assertRedirect(route('announcements.index'));
        $this->assertDatabaseHas('announcements', ['title' => 'Jadwal Penilaian Akhir Semester']);

        // 4. Kajur / Kaprogli creates announcement -> Success
        $this->assertTrue($this->kajur->isHeadOfDepartment());
        $responseKajur = $this->actingAs($this->kajur)->post(route('announcements.store'), [
            'title' => 'Rapat Praktik Jurusan TKJ',
            'content' => 'Persiapan Uji Kompetensi Keahlian (UKK).',
            'target_type' => 'department',
            'target_departments' => [$this->department->id],
        ]);
        $responseKajur->assertRedirect(route('announcements.index'));
        $this->assertDatabaseHas('announcements', ['title' => 'Rapat Praktik Jurusan TKJ']);
    }

    public function test_target_audience_filtering_works_correctly(): void
    {
        // Announcement A: Semua Warga GTK
        $annAll = Announcement::create([
            'title' => 'Pengumuman Semua GTK',
            'content' => 'Konten untuk semua.',
            'author_id' => $this->principal->id,
            'target_type' => 'all',
            'published_at' => now(),
            'is_active' => true,
        ]);

        // Announcement B: Khusus Guru Piket
        $annPiket = Announcement::create([
            'title' => 'Pengumuman Khusus Tim Piket',
            'content' => 'Konten untuk petugas piket.',
            'author_id' => $this->waka->id,
            'target_type' => 'role',
            'target_roles' => ['guru_piket'],
            'published_at' => now(),
            'is_active' => true,
        ]);

        // When Regular Teacher views announcements
        $teacherList = Announcement::active()->forUser($this->teacher)->get();
        $this->assertTrue($teacherList->contains('id', $annAll->id));
        $this->assertFalse($teacherList->contains('id', $annPiket->id));

        // When Piket Teacher views announcements
        $piketList = Announcement::active()->forUser($this->piketTeacher)->get();
        $this->assertTrue($piketList->contains('id', $annAll->id));
        $this->assertTrue($piketList->contains('id', $annPiket->id));

        // When Principal / Leader views announcements -> Can see all
        $leaderList = Announcement::active()->forUser($this->principal)->get();
        $this->assertTrue($leaderList->contains('id', $annAll->id));
        $this->assertTrue($leaderList->contains('id', $annPiket->id));
    }

    public function test_announcement_dispatches_telegram_broadcast(): void
    {
        TelegramChannel::create([
            'name' => 'Warga GTK',
            'code' => 'warga_gtk_ann',
            'chat_id' => '-100999888111',
            'allowed_roles' => ['semua_gtk'],
            'is_active' => true,
        ]);

        Http::swap(new Factory);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $response = $this->actingAs($this->principal)->post(route('announcements.store'), [
            'title' => 'Edaran Libur Nasional',
            'content' => 'Besok adalah hari libur nasional.',
            'target_type' => 'all',
            'send_telegram' => '1',
        ]);

        $response->assertRedirect(route('announcements.index'));

        $announcement = Announcement::where('title', 'Edaran Libur Nasional')->first();
        $this->assertNotNull($announcement);
        $this->assertNotNull($announcement->telegram_sent_at);
        $this->assertGreaterThan(0, $announcement->telegram_sent_count);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Edaran Libur Nasional')
                && str_contains($request['text'], 'PENGUMUMAN RESMI SEKOLAH');
        });
    }

    public function test_whatsapp_share_text_and_url_formatting(): void
    {
        $ann = Announcement::create([
            'title' => 'Pengumuman Rapat Pleno',
            'content' => 'Rapat pleno akan dilaksanakan pukul 13.00 WIB.',
            'author_id' => $this->principal->id,
            'target_type' => 'all',
            'published_at' => now(),
            'is_active' => true,
        ]);

        $waText = $ann->getWhatsAppShareText();
        $this->assertStringContainsString('PENGUMUMAN RESMI SEKOLAH', $waText);
        $this->assertStringContainsString('Pengumuman Rapat Pleno', $waText);
        $this->assertStringContainsString('Bpk. Kepala Sekolah', $waText);

        $waUrl = $ann->whatsapp_share_url;
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $waUrl);
    }

    public function test_read_tracking_and_mark_as_read(): void
    {
        $ann = Announcement::create([
            'title' => 'Pengumuman Belum Dibaca',
            'content' => 'Silakan dibaca segera.',
            'author_id' => $this->principal->id,
            'target_type' => 'all',
            'published_at' => now(),
            'is_active' => true,
        ]);

        // Teacher initially has not read it
        $this->assertFalse($ann->isReadBy($this->teacher));
        $this->assertEquals(1, $this->teacher->unreadAnnouncementsCount());

        // Teacher views it via show endpoint
        $response = $this->actingAs($this->teacher)->getJson(route('announcements.show', $ann->id));
        $response->assertOk();

        // Now marked as read
        $this->assertTrue($ann->fresh()->isReadBy($this->teacher));
        $this->assertEquals(0, $this->teacher->unreadAnnouncementsCount());
    }

    public function test_mobile_api_announcements_endpoints(): void
    {
        $ann = Announcement::create([
            'title' => 'Pengumuman Mobile API',
            'content' => 'Tes endpoint mobile flutter.',
            'author_id' => $this->principal->id,
            'target_type' => 'all',
            'published_at' => now(),
            'is_active' => true,
        ]);

        // 1. GET /api/v1/mobile/announcements
        $responseList = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/announcements');
        $responseList->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'unread_count', 'pagination']);

        // 2. GET /api/v1/mobile/announcements/{id}
        $responseShow = $this->actingAs($this->teacher)->getJson('/api/v1/mobile/announcements/'.$ann->id);
        $responseShow->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Pengumuman Mobile API')
            ->assertJsonPath('data.is_read', true);

        // 3. POST /api/v1/mobile/announcements by leader -> 201 Created
        $responseCreate = $this->actingAs($this->principal)->postJson('/api/v1/mobile/announcements', [
            'title' => 'Pengumuman Dibuat dari Mobile',
            'content' => 'Kepala sekolah membuat pengumuman langsung dari HP.',
            'target_type' => 'all',
            'send_telegram' => false,
        ]);
        $responseCreate->assertStatus(201);
        $this->assertDatabaseHas('announcements', ['title' => 'Pengumuman Dibuat dari Mobile']);

        // 4. POST /api/v1/mobile/announcements by regular teacher -> 403 Forbidden
        $responseFail = $this->actingAs($this->teacher)->postJson('/api/v1/mobile/announcements', [
            'title' => 'Guru mencoba buat pengumuman',
            'content' => 'Gagal',
            'target_type' => 'all',
        ]);
        $responseFail->assertStatus(403);
    }

    public function test_telegram_webhook_pengumuman_command_renders_announcement(): void
    {
        Announcement::create([
            'title' => 'Libur Semester Ganjil',
            'content' => 'Libur semester ganjil dimulai tanggal 20 Desember.',
            'author_id' => $this->principal->id,
            'target_type' => 'all',
            'published_at' => now(),
            'is_active' => true,
        ]);

        Http::swap(new Factory);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 888]], 200),
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => [
                    'id' => 8051871751,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 8051871751,
                    'first_name' => 'Army',
                ],
                'text' => '/pengumuman',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Libur Semester Ganjil')
                && str_contains($request['text'], 'Bpk. Kepala Sekolah');
        });
    }
}
