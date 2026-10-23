<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarUpload;
use App\Models\CurriculumEvent;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);

        $teacherRole = Role::create([
            'name' => 'Guru',
            'slug' => 'guru',
            'description' => 'Guru Pengampu',
            'is_system' => false,
        ]);

        $this->admin = User::create([
            'name' => 'Waka Kurikulum',
            'username' => 'waka',
            'email' => 'waka@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Ahmad Dahlan',
            'username' => 'ahmad',
            'email' => 'ahmad@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'short_name' => 'AD',
            'is_active' => true,
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK PGRI Kras',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'headmaster_name' => 'Drs. H. Sukamto, M.Pd.',
            'curriculum_leader_name' => 'Waka Kurikulum, S.Pd.',
            'city' => 'Kediri',
        ]);
    }

    public function test_calendar_index_page_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)->get(route('kurikulum.calendar.index'));

        $response->assertStatus(200);
        $response->assertSee('Kalender Sekolah');
        $response->assertSee('Kegiatan List');
        $response->assertSee('Pilih dari Agenda Kegiatan');
        $response->assertSee('2026/2027');
        $response->assertSee('Upload Kaldik');
    }

    public function test_calendar_events_json_feed_returns_data(): void
    {
        $event = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Rapat Kerja Kurikulum Awal TP',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-13',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('kurikulum.calendar.events', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => 'evt_'.$event->id,
            'title' => 'Rapat Kerja Kurikulum Awal TP',
        ]);
    }

    public function test_calendar_events_can_filter_by_category(): void
    {
        $eventCurriculum = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Workshop Modul Ajar',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);

        $eventHoliday = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'HUT Kemerdekaan RI',
            'category' => 'lhb',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'is_all_day' => true,
            'is_holiday' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('kurikulum.calendar.events', [
            'academic_year' => '2026/2027',
            'category' => 'lhb',
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'HUT Kemerdekaan RI']);
        $response->assertJsonMissing(['title' => 'Workshop Modul Ajar']);
    }

    public function test_calendar_events_can_filter_by_assigned_teacher(): void
    {
        $eventWithTeacher = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Penyusunan Kisi-kisi PTS',
            'category' => 'tugas_guru',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);
        $eventWithTeacher->users()->attach($this->teacher->id);

        $eventWithoutTeacher = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Koordinasi Manajemen',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);

        // Filter by teacher ID
        $response = $this->actingAs($this->admin)->getJson(route('kurikulum.calendar.events', [
            'academic_year' => '2026/2027',
            'teacher_id' => $this->teacher->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Penyusunan Kisi-kisi PTS']);
        $response->assertJsonMissing(['title' => 'Koordinasi Manajemen']);

        // Filter by my_events for that teacher
        $responseMy = $this->actingAs($this->teacher)->getJson(route('kurikulum.calendar.events', [
            'academic_year' => '2026/2027',
            'my_events' => '1',
        ]));

        $responseMy->assertStatus(200);
        $responseMy->assertJsonFragment(['title' => 'Penyusunan Kisi-kisi PTS']);
        $responseMy->assertJsonMissing(['title' => 'Koordinasi Manajemen']);
    }

    public function test_user_can_create_curriculum_event(): void
    {
        $payload = [
            'title' => 'Pelatihan Implementasi Kurikulum Merdeka',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-21',
            'start_time' => '08:00',
            'end_time' => '15:00',
            'is_all_day' => 0,
            'location' => 'Aula Pertemuan',
            'description' => 'Wajib dihadiri seluruh dewan guru.',
            'academic_year' => '2026/2027',
            'user_ids' => [$this->teacher->id],
        ];

        $response = $this->actingAs($this->admin)->postJson(route('kurikulum.calendar.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('curriculum_events', [
            'title' => 'Pelatihan Implementasi Kurikulum Merdeka',
            'category' => 'kegiatan_kurikulum',
            'location' => 'Aula Pertemuan',
            'academic_year' => '2026/2027',
        ]);

        $event = CurriculumEvent::where('title', 'Pelatihan Implementasi Kurikulum Merdeka')->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->users->contains($this->teacher));
    }

    public function test_user_can_update_curriculum_event(): void
    {
        $event = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Judul Lama',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);

        $updatePayload = [
            'title' => 'Judul Baru yang Diperbarui',
            'category' => 'penilaian',
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-23',
            'location' => 'Lab Komputer',
            'description' => 'Keterangan baru',
            'user_ids' => [$this->teacher->id],
        ];

        $response = $this->actingAs($this->admin)->putJson(route('kurikulum.calendar.update', $event), $updatePayload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('curriculum_events', [
            'id' => $event->id,
            'title' => 'Judul Baru yang Diperbarui',
            'category' => 'penilaian',
            'location' => 'Lab Komputer',
        ]);

        $this->assertTrue($event->fresh()->users->contains($this->teacher));
    }

    public function test_user_can_delete_curriculum_event(): void
    {
        $event = CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Kegiatan Yang Akan Dihapus',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'is_all_day' => true,
            'created_by' => $this->admin->id,
        ]);
        $event->users()->attach($this->teacher->id);

        $response = $this->actingAs($this->admin)->deleteJson(route('kurikulum.calendar.destroy', $event));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('curriculum_events', ['id' => $event->id]);
        $this->assertDatabaseMissing('curriculum_event_users', ['curriculum_event_id' => $event->id]);
    }

    public function test_user_can_upload_kaldik_document(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('kaldik_jatim_2026.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('kurikulum.calendar.upload'), [
            'kaldik_file' => $file,
            'title' => 'Kalender Pendidikan Jatim Resmi 2026/2027',
            'notes' => 'SK Dinas Pendidikan Jawa Timur',
            'academic_year' => '2026/2027',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('academic_calendar_uploads', [
            'academic_year' => '2026/2027',
            'title' => 'Kalender Pendidikan Jatim Resmi 2026/2027',
            'is_active' => true,
        ]);

        $upload = AcademicCalendarUpload::where('title', 'Kalender Pendidikan Jatim Resmi 2026/2027')->first();
        $this->assertNotNull($upload);
        Storage::disk('public')->assertExists($upload->file_path);
    }

    public function test_teacher_cannot_upload_kaldik_document(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->createWithContent('kaldik_guru.pdf', '%PDF-1.4 dummy content');

        $response = $this->actingAs($this->teacher)->post(route('kurikulum.calendar.upload'), [
            'kaldik_file' => $file,
            'title' => 'Coba Upload Guru',
        ]);

        $response->assertStatus(403);

        // Also check teacher does not see upload button in view
        $indexResponse = $this->actingAs($this->teacher)->get(route('kurikulum.calendar.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Upload Kaldik (PDF/Excel)');
    }

    public function test_user_can_download_and_preview_kaldik_document(): void
    {
        Storage::fake('public');

        $fakeFilePath = 'academic_calendars/test_kaldik.pdf';
        Storage::disk('public')->put($fakeFilePath, 'Fake PDF Content');

        $upload = AcademicCalendarUpload::create([
            'academic_year' => '2026/2027',
            'title' => 'Kaldik Test',
            'original_filename' => 'test_kaldik.pdf',
            'file_path' => $fakeFilePath,
            'file_size' => 1024,
            'uploaded_by' => $this->admin->id,
            'is_active' => true,
        ]);

        // Test download
        $downloadResponse = $this->actingAs($this->admin)->get(route('kurikulum.calendar.kaldik.download', $upload));
        $downloadResponse->assertStatus(200);

        // Test preview
        $previewResponse = $this->actingAs($this->admin)->get(route('kurikulum.calendar.kaldik.preview', $upload));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_supervision_events_are_included_in_calendar_feed(): void
    {
        $supervision = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->admin->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'subject' => 'Pemrograman Web',
            'class_name' => 'XI RPL 1',
            'schedule_date' => '2026-08-25',
            'schedule_time' => '09:00',
            'status' => 'terjadwal',
            'teaching_hours' => 2,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('kurikulum.calendar.events', [
            'academic_year' => '2026/2027',
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => 'spv_'.$supervision->id,
            'title' => 'Supervisi: '.$this->teacher->name.' (Pemrograman Web)',
        ]);
    }

    public function test_mobile_curriculum_calendar_renders_fab_and_sheet(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->admin)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('kurikulum.calendar.index'));

        $response->assertStatus(200);
        $response->assertSee('openCreateActivitySheet()', false);
        $response->assertSee('sheet-add-activity');
        $response->assertSee('Tambah Kegiatan Sekolah');
    }
}
