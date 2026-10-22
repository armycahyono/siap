<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\PiketDutySwap;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PiketFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $teacher;

    protected Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'super_admin')->first()?->id,
                'is_active' => true,
            ]);

        $this->teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);
        $this->teacher->update(['telegram_chat_id' => '123456789']);

        $this->classroom = Classroom::first() ?? Classroom::create([
            'name' => 'XII TKJ 1',
            'academic_year' => '2026/2027',
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);
    }

    public function test_piket_dashboard_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Guru Piket');
        $response->assertSee('Jadwal Pelajaran & Guru Mapel Jam Sekarang', false);
        $response->assertSee('Sedang KBM Aktif:');
    }

    public function test_piket_dashboard_schedule_detects_weekend_and_outside_kbm_correctly(): void
    {
        // 1. Uji saat hari Sabtu (Libur Akhir Pekan)
        Carbon::setTestNow('2026-10-03 10:00:00'); // Sabtu
        $responseWeekend = $this->actingAs($this->user)->get(route('piket.dashboard'));
        $responseWeekend->assertStatus(200);
        $responseWeekend->assertSee('Sabtu');
        $responseWeekend->assertSee('Libur Akhir Pekan (Tidak Ada KBM Reguler)');

        // 2. Uji saat hari Senin dini hari (Di Luar Jam KBM Efektif - Belum Dimulai)
        Carbon::setTestNow('2026-10-05 05:30:00'); // Senin 05:30 WIB
        $responseBefore = $this->actingAs($this->user)->get(route('piket.dashboard'));
        $responseBefore->assertStatus(200);
        $responseBefore->assertSee('Senin');
        $responseBefore->assertSee('Di Luar Jam KBM Efektif');

        // 3. Uji saat hari Senin jam KBM aktif (Jam ke-1)
        Carbon::setTestNow('2026-10-05 07:15:00'); // Senin 07:15 WIB
        $responseActive = $this->actingAs($this->user)->get(route('piket.dashboard'));
        $responseActive->assertStatus(200);
        $responseActive->assertSee('Senin');
        $responseActive->assertSee('Jam ke-1');

        Carbon::setTestNow(); // Reset time mock
    }

    public function test_piket_schedules_can_be_viewed_and_stored(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.schedules.index'));
        $response->assertStatus(200);

        $storeResponse = $this->actingAs($this->user)->post(route('piket.schedules.store'), [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Kamis',
            'teacher_id' => $this->teacher->id,
            'role' => 'koordinator',
            'duty_location' => 'Lobi Utama',
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('piket_schedules', [
            'day' => 'Kamis',
            'teacher_id' => $this->teacher->id,
            'role' => 'koordinator',
        ]);
    }

    public function test_piket_duty_swap_can_be_submitted_and_approved(): void
    {
        $schedule = PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->user->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('piket.swaps.store'), [
            'original_schedule_id' => $schedule->id,
            'replacement_teacher_id' => $this->teacher->id,
            'duty_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Ada dinas pelatihan',
        ]);

        $response->assertRedirect();
        $swap = PiketDutySwap::where('requester_id', $this->user->id)->first();
        $this->assertNotNull($swap);

        $approveResponse = $this->actingAs($this->user)->post(route('piket.swaps.approve', $swap));
        $approveResponse->assertRedirect();
        $this->assertEquals('disetujui', $swap->fresh()->status);
    }

    public function test_piket_calendar_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.calendar.index'));

        $response->assertStatus(200);
        $response->assertSee('Kalender Petugas Piket');
    }

    public function test_piket_journal_today_and_attendance_flow(): void
    {
        $todayResponse = $this->actingAs($this->user)->get(route('piket.journals.today'));
        $todayResponse->assertRedirect();

        $journal = PiketJournal::whereDate('date', Carbon::today())->first() ?? PiketJournal::first();
        $this->assertNotNull($journal);

        $showResponse = $this->actingAs($this->user)->get(route('piket.journals.show', $journal));
        $showResponse->assertStatus(200);

        // Update general notes
        $notesResponse = $this->actingAs($this->user)->post(route('piket.journals.update-notes', $journal), [
            'general_notes' => 'Situasi sekolah kondusif',
            'coordinator_id' => $this->user->id,
        ]);
        $notesResponse->assertRedirect();
        $this->assertEquals('Situasi sekolah kondusif', $journal->fresh()->general_notes);

        // Complete journal
        $completeResponse = $this->actingAs($this->user)->post(route('piket.journals.complete', $journal));
        $completeResponse->assertRedirect();
        $this->assertEquals('selesai', $journal->fresh()->status);
    }

    public function test_journal_automatically_populates_scheduled_officer_attendances_and_mobile_view(): void
    {
        $schedTeacher = PiketSchedule::where('day', 'Senin')->where('is_active', true)->first();
        if (! $schedTeacher) {
            $schedTeacher = PiketSchedule::create([
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'day' => 'Senin',
                'teacher_id' => $this->teacher->id,
                'role' => 'anggota',
                'duty_location' => 'Pintu Gerbang Utama',
                'is_active' => true,
            ]);
        }

        // Cari tanggal Senin berikutnya
        $mondayDate = Carbon::parse('next monday')->toDateString();

        $journal = PiketJournal::create([
            'date' => $mondayDate,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'status' => 'berlangsung',
        ]);

        $this->assertEquals(0, $journal->attendances()->count());

        // Akses via mobile
        $response = $this->actingAs($this->user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148')
            ->get(route('piket.journals.show', $journal));

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $journal->fresh()->attendances()->count());
        $response->assertSee($schedTeacher->teacher->formatted_short_name ?? $schedTeacher->teacher->name);
        $response->assertDontSee('Belum ada daftar petugas piket terjadwal.');
        $response->assertSee('Belum Hadir');
        $response->assertSee('Belum Presensi');

        // Pastikan statusnya belum_hadir
        $att = $journal->fresh()->attendances()->where('teacher_id', $schedTeacher->teacher_id)->first();
        $this->assertNotNull($att);
        $this->assertEquals('belum_hadir', $att->status);
        $this->assertNull($att->check_in_time);

        // Simulasikan presensi GTK (scan log masuk mesin/geotag)
        AttendanceLog::create([
            'pin' => '12345',
            'user_id' => $schedTeacher->teacher_id,
            'user_type' => 'teacher',
            'scanned_at' => Carbon::parse($mondayDate.' 06:45:00'),
        ]);

        $syncResponse = $this->actingAs($this->user)->post(route('piket.journals.sync-machine', $journal));
        $syncResponse->assertRedirect();

        $this->assertEquals('hadir', $att->fresh()->status);
        $this->assertEquals('06:45:00', $att->fresh()->check_in_time);
    }

    public function test_student_permit_creation_and_slip_view(): void
    {
        $student = Student::create([
            'name' => 'Fulan bin Fulan',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        $storeResponse = $this->actingAs($this->user)->post(route('piket.permits.store'), [
            'permit_type' => 'terlambat',
            'permit_date' => date('Y-m-d'),
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $this->classroom->id,
            'reason' => 'Kesiangan bangun',
            'entry_time' => '07:25',
            'period_assigned' => '1',
            'action_taken' => 'Hafalan surat pendek',
        ]);

        $storeResponse->assertRedirect();
        $permit = PiketStudentPermit::where('student_name', $student->name)->first();
        $this->assertNotNull($permit);

        // Test slip print view
        $slipResponse = $this->actingAs($this->user)->get(route('piket.permits.slip', $permit));
        $slipResponse->assertStatus(200);
        $slipResponse->assertSee('SLIP Terlambat Masuk');
    }

    public function test_student_permit_create_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.permits.create'));
        $response->assertStatus(200);
        $response->assertSee('Penerbitan Surat Izin Siswa');
        $response->assertSee('Terlambat Masuk');
    }

    public function test_piket_incident_create_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.incidents.create'));
        $response->assertStatus(200);
        $response->assertSee('Catat Kejadian / Kasus Siswa');
    }

    public function test_student_autocomplete_search_api(): void
    {
        Student::create([
            'name' => 'Bambang Sudarsono',
            'nis' => '998877',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('piket.students.search', ['q' => 'Bambang']));

        $response->assertStatus(200);
        $expectedTeacher = $this->classroom->fresh()->homeroomTeacher?->formatted_short_name
            ?? $this->classroom->fresh()->homeroomTeacher?->name;

        $response->assertJsonFragment([
            'name' => 'BAMBANG SUDARSONO',
            'nis' => '998877',
            'homeroom_teacher' => $expectedTeacher,
        ]);
    }

    public function test_piket_inval_class_can_be_stored(): void
    {
        $response = $this->actingAs($this->user)->post(route('piket.inval.store'), [
            'date' => date('Y-m-d'),
            'classroom_id' => $this->classroom->id,
            'absent_teacher_id' => $this->teacher->id,
            'period_start' => 2,
            'period_end' => 3,
            'reason' => 'Sakit Flu',
            'assignment_material' => 'Kerjakan LKS halaman 10',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('piket_inval_classes', [
            'classroom_id' => $this->classroom->id,
            'absent_teacher_id' => $this->teacher->id,
            'reason' => 'Sakit Flu',
        ]);
    }

    public function test_piket_incident_can_be_recorded(): void
    {
        $response = $this->actingAs($this->user)->post(route('piket.incidents.store'), [
            'incident_date' => date('Y-m-d'),
            'incident_time' => '08:15',
            'student_name' => 'Siswa Bermasalah',
            'classroom_id' => $this->classroom->id,
            'category' => 'seragam',
            'description' => 'Tidak mengenakan atribut lengkap',
            'action_taken' => 'Diberikan peringatan lisan',
            'follow_up_target' => 'selesai_di_piket',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('piket_incidents', [
            'student_name' => 'Siswa Bermasalah',
            'category' => 'seragam',
        ]);
    }

    public function test_piket_guest_can_be_recorded_and_checked_out(): void
    {
        $response = $this->actingAs($this->user)->post(route('piket.guests.store'), [
            'visit_date' => date('Y-m-d'),
            'check_in_time' => '09:00',
            'guest_name' => 'Bpk. Hendra Tamu',
            'institution' => 'Wali Murid',
            'purpose' => 'Konsultasi nilai',
            'meet_with' => 'Wali Kelas',
        ]);

        $response->assertRedirect();
        $guest = PiketGuest::where('guest_name', 'Bpk. Hendra Tamu')->first();
        $this->assertNotNull($guest);

        $checkoutResponse = $this->actingAs($this->user)->post(route('piket.guests.checkout', $guest));
        $checkoutResponse->assertRedirect();
        $this->assertNotNull($guest->fresh()->check_out_time);
    }

    public function test_piket_reports_and_export_csv(): void
    {
        $response = $this->actingAs($this->user)->get(route('piket.reports.index', ['tab' => 'permits']));
        $response->assertStatus(200);

        $exportResponse = $this->actingAs($this->user)->get(route('piket.reports.export', [
            'tab' => 'permits',
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $exportResponse->assertStatus(200);
        $this->assertTrue(str_contains($exportResponse->headers->get('content-type'), 'text/csv'));
    }

    public function test_telegram_settings_can_be_updated(): void
    {
        $response = $this->actingAs($this->user)->post(route('piket.telegram.settings'), [
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_bot_username' => '@TestPiketBot',
            'telegram_discipline_chat_id' => '-100987654321',
            'telegram_notifications_enabled' => '1',
        ]);

        $response->assertRedirect();
        $setting = SchoolSetting::getSetting();
        $this->assertEquals('@TestPiketBot', $setting->telegram_bot_username);
        $this->assertTrue($setting->telegram_notifications_enabled);
    }

    public function test_penugasan_guru_shows_piket_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('teacher-assignments.index', ['tab' => 'piket']));
        $response->assertStatus(200);
        $response->assertSee('Penugasan Piket');
    }

    public function test_piket_incident_can_be_recorded_with_photo_evidence(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->image('bukti_rokok.jpg', 600, 400);

        $response = $this->actingAs($this->user)->post(route('piket.incidents.store'), [
            'incident_date' => date('Y-m-d'),
            'incident_time' => '10:00',
            'student_name' => 'Siswa Pelanggar',
            'classroom_id' => $this->classroom->id,
            'category' => 'rokok',
            'description' => 'Membawa rokok di lingkungan sekolah',
            'action_taken' => 'Barang disita dan pembinaan',
            'follow_up_target' => 'rujuk_bk',
            'evidence_photo' => $photo,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('piket_incidents', [
            'student_name' => 'Siswa Pelanggar',
            'category' => 'rokok',
        ]);

        $incident = PiketIncident::where('student_name', 'Siswa Pelanggar')->first();
        $this->assertNotNull($incident->evidence_photo);
        Storage::disk('public')->assertExists($incident->evidence_photo);
    }

    public function test_telegram_webhook_responds_to_commands(): void
    {
        Http::fake();

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 999999999,
                    'first_name' => 'Budi',
                ],
                'chat' => [
                    'id' => 999999999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        // Test /inval command
        $responseInval = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123457,
            'message' => [
                'message_id' => 2,
                'from' => [
                    'id' => 999999999,
                    'first_name' => 'Budi',
                ],
                'chat' => [
                    'id' => 999999999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/inval',
            ],
        ]);
        $responseInval->assertStatus(200);
        $responseInval->assertJson(['ok' => true]);

        // Test /piket command
        $responsePiket = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123458,
            'message' => [
                'message_id' => 3,
                'from' => [
                    'id' => 999999999,
                    'first_name' => 'Budi',
                ],
                'chat' => [
                    'id' => 999999999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/piket',
            ],
        ]);
        $responsePiket->assertStatus(200);
        $responsePiket->assertJson(['ok' => true]);
    }

    public function test_telegram_webhook_get_returns_status_info(): void
    {
        $response = $this->getJson(route('telegram.webhook'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'online',
        ]);
    }

    public function test_regular_teacher_can_view_dashboard_but_cannot_access_operational_menu(): void
    {
        // Guru biasa tanpa tugas piket hari ini
        $responseDashboard = $this->actingAs($this->teacher)->get(route('piket.dashboard'));
        $responseDashboard->assertStatus(200);
        $responseDashboard->assertDontSee('Tukar Jadwal Piket');
        $responseDashboard->assertDontSee('Kalender Piket');

        $responseSchedules = $this->actingAs($this->teacher)->get(route('piket.schedules.index'));
        $responseSchedules->assertStatus(200);
        $responseSchedules->assertDontSee('Tukar Jadwal Piket');

        // Akses menu tukar jadwal dan kalender dilarang untuk guru biasa (403)
        $responseSwaps = $this->actingAs($this->teacher)->get(route('piket.swaps.index'));
        $responseSwaps->assertStatus(403);

        $responseCalendar = $this->actingAs($this->teacher)->get(route('piket.calendar.index'));
        $responseCalendar->assertStatus(403);

        // Akses menu operasional piket dilarang (403)
        $responseJournal = $this->actingAs($this->teacher)->get(route('piket.journals.index'));
        $responseJournal->assertStatus(403);

        $responsePermits = $this->actingAs($this->teacher)->get(route('piket.permits.index'));
        $responsePermits->assertStatus(403);

        $responseReports = $this->actingAs($this->teacher)->get(route('piket.reports.index'));
        $responseReports->assertStatus(403);

        // Mengubah master jadwal piket dilarang (403)
        $responseStoreSchedule = $this->actingAs($this->teacher)->post(route('piket.schedules.store'), [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher->id,
            'role' => 'anggota',
        ]);
        $responseStoreSchedule->assertStatus(403);
    }

    public function test_teacher_scheduled_today_can_access_operational_menu(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00')); // Monday
        $today = 'Senin';

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $today,
            'teacher_id' => $this->teacher->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);

        $responseJournal = $this->actingAs($this->teacher)->get(route('piket.journals.index'));
        $responseJournal->assertStatus(200);

        $responsePermits = $this->actingAs($this->teacher)->get(route('piket.permits.index'));
        $responsePermits->assertStatus(200);
    }

    public function test_telegram_bot_can_issue_student_permit_and_forward_to_subject_teacher(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);

        $this->user->update(['telegram_chat_id' => '999999999']);

        $student = Student::first() ?? Student::create([
            'name' => 'Budi Santoso',
            'nis' => '12345',
            'nisn' => '0012345678',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        // Step 1: Start /izin_siswa
        $resStart = $this->postJson(route('telegram.webhook'), [
            'update_id' => 200001,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => '/izin_siswa',
            ],
        ]);
        $resStart->assertStatus(200);

        // Step 2: Input student name
        $resName = $this->postJson(route('telegram.webhook'), [
            'update_id' => 200002,
            'message' => [
                'message_id' => 11,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => $student->name,
            ],
        ]);
        $resName->assertStatus(200);

        // Step 3: Choose permit type via callback
        $resType = $this->postJson(route('telegram.webhook'), [
            'update_id' => 200003,
            'callback_query' => [
                'id' => 'cb_123',
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 12,
                    'chat' => ['id' => 999999999, 'type' => 'private'],
                ],
                'data' => 'sp_type_terlambat',
            ],
        ]);
        $resType->assertStatus(200);

        // Step 4: Input reason
        $resReason = $this->postJson(route('telegram.webhook'), [
            'update_id' => 200004,
            'message' => [
                'message_id' => 13,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => 'Ban sepeda motor bocor di jalan',
            ],
        ]);
        $resReason->assertStatus(200);

        // Assert database has permit
        $this->assertDatabaseHas('piket_student_permits', [
            'student_name' => $student->name,
            'permit_type' => 'terlambat',
            'reason' => 'Ban sepeda motor bocor di jalan',
            'status' => 'diberikan',
        ]);
    }

    public function test_telegram_bot_can_record_teacher_absence_and_forward_to_waka_kurikulum(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 124]], 200),
        ]);

        $this->user->update(['telegram_chat_id' => '999999999']);

        // Set Waka Kurikulum with telegram
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_kurikulum'], ['name' => 'Waka Kurikulum']);
        $wakaUser = User::factory()->create([
            'username' => 'waka_kurikulum_test',
            'name' => 'Bpk Waka Kurikulum',
            'role_id' => $wakaRole->id,
            'telegram_chat_id' => '888888888',
            'is_active' => true,
        ]);
        $setting = SchoolSetting::getSetting();
        $setting->update([
            'curriculum_head_id' => $wakaUser->id,
            'curriculum_head_name' => $wakaUser->name,
            'telegram_notifications_enabled' => true,
        ]);

        Carbon::setTestNow('2026-10-02 10:00:00');

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
        ];
        $today = $dayNames[now()->dayOfWeekIso] ?? 'Senin';

        $subject = Subject::first() ?? Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $today,
            'start_period' => 1,
            'end_period' => 3,
        ]);

        // Step 1: Start /izin_guru
        $resStart = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300001,
            'message' => [
                'message_id' => 20,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => '/izin_guru',
            ],
        ]);
        $resStart->assertStatus(200);

        // Step 2: Input teacher name
        $resName = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300002,
            'message' => [
                'message_id' => 21,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => $this->teacher->name,
            ],
        ]);
        $resName->assertStatus(200);

        // Step 3: Choose day (today)
        $resDay = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300003,
            'callback_query' => [
                'id' => 'cb_day',
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 22,
                    'chat' => ['id' => 999999999, 'type' => 'private'],
                ],
                'data' => 'tp_day_today',
            ],
        ]);
        $resDay->assertStatus(200);

        // Step 4: Choose class (all)
        $resCls = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300004,
            'callback_query' => [
                'id' => 'cb_cls',
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 23,
                    'chat' => ['id' => 999999999, 'type' => 'private'],
                ],
                'data' => 'tp_cls_all',
            ],
        ]);
        $resCls->assertStatus(200);

        // Step 5: Choose reason
        $resRsn = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300005,
            'callback_query' => [
                'id' => 'cb_rsn',
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'message' => [
                    'message_id' => 24,
                    'chat' => ['id' => 999999999, 'type' => 'private'],
                ],
                'data' => 'tp_rsn_Sakit',
            ],
        ]);
        $resRsn->assertStatus(200);

        // Step 6: Input task
        $resTask = $this->postJson(route('telegram.webhook'), [
            'update_id' => 300006,
            'message' => [
                'message_id' => 25,
                'from' => ['id' => 999999999, 'first_name' => 'Admin'],
                'chat' => ['id' => 999999999, 'type' => 'private'],
                'date' => time(),
                'text' => 'Mengerjakan LKS Bab 2 Halaman 30 No 1-10',
            ],
        ]);
        $resTask->assertStatus(200);

        $this->assertDatabaseHas('piket_inval_classes', [
            'absent_teacher_id' => $this->teacher->id,
            'reason' => 'Sakit',
            'assignment_material' => 'Mengerjakan LKS Bab 2 Halaman 30 No 1-10',
        ]);

        Carbon::setTestNow();
    }

    public function test_teacher_can_link_telegram_account_via_hubungkan_command(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $teacherUser = User::factory()->create([
            'username' => 'guru.ahmad',
            'nip' => '198501012010011005',
            'name' => 'Ahmad Soleh, S.Pd.',
            'telegram_chat_id' => null,
            'is_active' => true,
        ]);

        $res = $this->postJson(route('telegram.webhook'), [
            'update_id' => 400001,
            'message' => [
                'message_id' => 30,
                'from' => ['id' => 777666555, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 777666555, 'type' => 'private'],
                'date' => time(),
                'text' => '/hubungkan 198501012010011005',
            ],
        ]);
        $res->assertStatus(200);

        $teacherUser->refresh();
        $this->assertEquals('777666555', $teacherUser->telegram_chat_id);
    }

    public function test_teacher_can_update_telegram_chat_id_in_profile(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('profile.update'), [
            'name' => $this->teacher->name,
            'email' => $this->teacher->email,
            'telegram_chat_id' => '1122334455',
        ]);

        $response->assertSessionHas('success');
        $this->teacher->refresh();
        $this->assertEquals('1122334455', $this->teacher->telegram_chat_id);
    }

    public function test_regular_teacher_can_submit_self_absence_and_assignment_forwarded_to_principal_waka_and_piket(): void
    {
        Carbon::setTestNow('2026-10-02 10:00:00'); // Friday

        $principal = User::factory()->create([
            'username' => 'principal.bambang',
            'name' => 'Dr. H. Bambang, M.Pd.',
            'telegram_chat_id' => '88888888',
            'is_active' => true,
        ]);
        $principal->role()->associate(Role::where('slug', 'kepala_sekolah')->first());
        $principal->save();

        $waka = User::factory()->create([
            'username' => 'waka.siti',
            'name' => 'Dra. Hj. Siti, M.Pd.',
            'telegram_chat_id' => '77777777',
            'is_active' => true,
        ]);
        $waka->role()->associate(Role::where('slug', 'waka_kurikulum')->first());
        $waka->save();

        $piketOfficer = User::factory()->create([
            'username' => 'piket.budi',
            'name' => 'Budi Santoso, S.Pd.',
            'telegram_chat_id' => '66666666',
            'is_active' => true,
        ]);
        $piketOfficer->role()->associate(Role::where('slug', 'guru')->first());
        $piketOfficer->save();

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Jumat',
            'teacher_id' => $piketOfficer->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $this->teacher->update(['telegram_chat_id' => '55555555']);

        $setting = SchoolSetting::getSetting();
        $setting->update([
            'principal_id' => $principal->id,
            'curriculum_head_id' => $waka->id,
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_notifications_enabled' => true,
        ]);

        $subject = Subject::first() ?? Subject::create([
            'name' => 'Fisika',
            'code' => 'FIS',
            'is_active' => true,
        ]);

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Jumat',
            'start_period' => 1,
            'end_period' => 2,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);

        // 1. Guru menekan tombol '📝 Ajukan Izin Saya'
        $resStart = $this->postJson(route('telegram.webhook'), [
            'update_id' => 500001,
            'message' => [
                'message_id' => 101,
                'from' => ['id' => 55555555, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 55555555, 'type' => 'private'],
                'date' => time(),
                'text' => '📝 Ajukan Izin Saya',
            ],
        ]);
        $resStart->assertStatus(200);

        // 2. Guru memilih Hari Ini (Jumat)
        $resDay = $this->postJson(route('telegram.webhook'), [
            'update_id' => 500002,
            'callback_query' => [
                'id' => 'cb_stp_day',
                'from' => ['id' => 55555555, 'first_name' => 'Ahmad'],
                'message' => [
                    'message_id' => 102,
                    'chat' => ['id' => 55555555, 'type' => 'private'],
                ],
                'data' => 'stp_day_today',
            ],
        ]);
        $resDay->assertStatus(200);

        // 3. Guru memilih seluruh jadwal kelas hari Jumat
        $resCls = $this->postJson(route('telegram.webhook'), [
            'update_id' => 500003,
            'callback_query' => [
                'id' => 'cb_stp_cls',
                'from' => ['id' => 55555555, 'first_name' => 'Ahmad'],
                'message' => [
                    'message_id' => 103,
                    'chat' => ['id' => 55555555, 'type' => 'private'],
                ],
                'data' => 'stp_cls_all',
            ],
        ]);
        $resCls->assertStatus(200);

        // 4. Guru memilih alasan 'Sakit'
        $resRsn = $this->postJson(route('telegram.webhook'), [
            'update_id' => 500004,
            'callback_query' => [
                'id' => 'cb_stp_rsn',
                'from' => ['id' => 55555555, 'first_name' => 'Ahmad'],
                'message' => [
                    'message_id' => 104,
                    'chat' => ['id' => 55555555, 'type' => 'private'],
                ],
                'data' => 'stp_rsn_Sakit',
            ],
        ]);
        $resRsn->assertStatus(200);

        // 5. Guru mengetikkan materi tugas
        $resTask = $this->postJson(route('telegram.webhook'), [
            'update_id' => 500005,
            'message' => [
                'message_id' => 105,
                'from' => ['id' => 55555555, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 55555555, 'type' => 'private'],
                'date' => time(),
                'text' => 'Mengerjakan latihan Fisika Bab 2 Hal 40 nomor 1-5 di buku PR.',
            ],
        ]);
        $resTask->assertStatus(200);

        // Verifikasi data tersimpan di database
        $this->assertDatabaseHas('piket_inval_classes', [
            'absent_teacher_id' => $this->teacher->id,
            'reason' => 'Sakit',
            'assignment_material' => 'Mengerjakan latihan Fisika Bab 2 Hal 40 nomor 1-5 di buku PR.',
        ]);

        // Verifikasi pesan Telegram terkirim ke Kepala Sekolah, Waka Kurikulum, Petugas Piket, dan Pemohon
        Http::assertSent(function ($request) {
            $body = $request->data();
            $chatId = $body['chat_id'] ?? null;

            return in_array($chatId, ['88888888', '77777777', '66666666', '55555555']);
        });

        Carbon::setTestNow();
    }

    public function test_homeroom_teacher_can_submit_student_leave_via_telegram_bot(): void
    {
        Carbon::setTestNow('2026-10-02 07:30:00'); // Jumat
        $setting = SchoolSetting::getSetting();
        $setting->update([
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_notifications_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
        ];
        $today = $dayNames[now()->dayOfWeekIso] ?? 'Jumat';

        $guruRole = Role::where('slug', 'guru')->first();

        // 1. Buat Wali Kelas yang TIDAK memiliki jadwal piket hari ini
        $waliUser = User::factory()->create([
            'username' => 'wali_kelas_test',
            'name' => 'Bpk Wali Kelas',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '11223344',
            'is_active' => true,
        ]);
        $this->classroom->update(['homeroom_teacher_id' => $waliUser->id]);

        // 2. Buat Petugas Piket hari ini
        $piketUser = User::factory()->create([
            'username' => 'piket_user_test',
            'name' => 'Ibu Petugas Piket',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '22334455',
            'is_active' => true,
        ]);
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'teacher_id' => $piketUser->id,
            'day' => $today,
            'is_active' => true,
        ]);

        // 3. Buat Guru Mapel yang mengajar di kelas hari ini
        $mapelUser = User::factory()->create([
            'username' => 'guru_biologi_test',
            'name' => 'Guru Biologi',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '33445566',
            'is_active' => true,
        ]);
        $subjectBio = Subject::create([
            'name' => 'Biologi',
            'code' => 'BIO',
            'is_active' => true,
        ]);
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $mapelUser->id,
            'subject_id' => $subjectBio->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $today,
            'start_period' => 1,
            'end_period' => 4,
        ]);

        // 4. Buat Siswa di kelas bimbingan wali
        $student = Student::create([
            'name' => 'Budi Santoso',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        // Pastikan waliUser bukan petugas piket
        $this->assertFalse($waliUser->canOperatePiket());
        $this->assertTrue($waliUser->isHomeroomTeacher());

        // Step 1: Wali Kelas memulai formulir izin siswa
        $resStart = $this->postJson(route('telegram.webhook'), [
            'update_id' => 600001,
            'message' => [
                'message_id' => 201,
                'from' => ['id' => 11223344, 'first_name' => 'Bpk Wali'],
                'chat' => ['id' => 11223344, 'type' => 'private'],
                'date' => time(),
                'text' => '🎫 Izin Siswa (Wali Kelas)',
            ],
        ]);
        $resStart->assertStatus(200);

        // Step 2: Wali Kelas mencari siswa binaannya
        $resSearch = $this->postJson(route('telegram.webhook'), [
            'update_id' => 600002,
            'message' => [
                'message_id' => 202,
                'from' => ['id' => 11223344, 'first_name' => 'Bpk Wali'],
                'chat' => ['id' => 11223344, 'type' => 'private'],
                'date' => time(),
                'text' => $student->name,
            ],
        ]);
        $resSearch->assertStatus(200);

        // Step 3: Wali Kelas memilih jenis izin (Izin Tidak Masuk Seharian)
        $resType = $this->postJson(route('telegram.webhook'), [
            'update_id' => 600003,
            'callback_query' => [
                'id' => 'cb_sp_type_not_present',
                'from' => ['id' => 11223344, 'first_name' => 'Bpk Wali'],
                'message' => [
                    'message_id' => 203,
                    'chat' => ['id' => 11223344, 'type' => 'private'],
                ],
                'data' => 'sp_type_izin_tidak_masuk',
            ],
        ]);
        $resType->assertStatus(200);

        // Step 4: Wali Kelas mengetikkan alasan izin
        $resReason = $this->postJson(route('telegram.webhook'), [
            'update_id' => 600004,
            'message' => [
                'message_id' => 204,
                'from' => ['id' => 11223344, 'first_name' => 'Bpk Wali'],
                'chat' => ['id' => 11223344, 'type' => 'private'],
                'date' => time(),
                'text' => 'Sakit demam, orang tua sudah kirim surat dokter via WhatsApp.',
            ],
        ]);
        $resReason->assertStatus(200);

        // Verifikasi database: Izin siswa berhasil tercatat dengan given_by = Wali Kelas
        $this->assertDatabaseHas('piket_student_permits', [
            'student_id' => $student->id,
            'permit_type' => 'izin_tidak_masuk',
            'reason' => 'Sakit demam, orang tua sudah kirim surat dokter via WhatsApp.',
            'given_by' => $waliUser->id,
            'status' => 'diberikan',
        ]);

        // Verifikasi Telegram: Pesan diteruskan ke Guru Mapel (33445566) dan Petugas Piket (22334455)
        Http::assertSent(function ($request) {
            $body = $request->data();
            $chatId = $body['chat_id'] ?? null;

            return $chatId === '33445566' || $chatId === '22334455';
        });

        Carbon::setTestNow();
    }

    public function test_teacher_can_request_duty_swap_and_replacement_teacher_verifies_directly_via_telegram(): void
    {
        Carbon::setTestNow('2026-10-02 08:00:00'); // Jumat
        $setting = SchoolSetting::getSetting();
        $wakaRole = Role::where('slug', 'waka_kurikulum')->first()
            ?? Role::create(['name' => 'Waka Kurikulum', 'slug' => 'waka_kurikulum']);

        $wakaUser = User::factory()->create([
            'username' => 'waka_swap_test',
            'name' => 'Ibu Waka Kurikulum',
            'role_id' => $wakaRole->id,
            'telegram_chat_id' => '77334455',
            'is_active' => true,
        ]);

        $setting->update([
            'academic_year' => '2026/2027',
            'curriculum_head_id' => $wakaUser->id,
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_notifications_enabled' => true,
            'telegram_piket_chat_id' => '999111222',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 888]], 200),
        ]);

        $guruRole = Role::where('slug', 'guru')->first();

        // 1. Buat Guru A (pemohon) dan Guru B (pengganti)
        $guruA = User::factory()->create([
            'username' => 'ahmad_soleh_test',
            'name' => 'Ahmad Soleh',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '77112233',
            'is_active' => true,
        ]);

        $guruB = User::factory()->create([
            'username' => 'budi_handoko_test',
            'name' => 'Budi Handoko',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '77223344',
            'is_active' => true,
        ]);

        $schedule = PiketSchedule::create([
            'academic_year' => '2026/2027',
            'teacher_id' => $guruA->id,
            'day' => 'Jumat',
            'role' => 'anggota',
            'duty_location' => 'Lobi Depan',
            'is_active' => true,
        ]);

        // Step 1: Guru A menekan tombol '🔄 Tukar Jadwal Piket'
        $resStart = $this->postJson(route('telegram.webhook'), [
            'update_id' => 700001,
            'message' => [
                'message_id' => 301,
                'from' => ['id' => 77112233, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 77112233, 'type' => 'private'],
                'date' => time(),
                'text' => '🔄 Tukar Jadwal Piket',
            ],
        ]);
        $resStart->assertStatus(200);

        // Step 2: Guru A memilih tanggal tukar piket (Jumat depan: 2026-10-09)
        $resDate = $this->postJson(route('telegram.webhook'), [
            'update_id' => 700002,
            'callback_query' => [
                'id' => 'cb_dswap_dt',
                'from' => ['id' => 77112233, 'first_name' => 'Ahmad'],
                'message' => [
                    'message_id' => 302,
                    'chat' => ['id' => 77112233, 'type' => 'private'],
                ],
                'data' => 'dswap_dt_2026-10-09',
            ],
        ]);
        $resDate->assertStatus(200);

        // Step 3: Guru A mengetik nama Guru Pengganti (Budi Handoko)
        $resTeacher = $this->postJson(route('telegram.webhook'), [
            'update_id' => 700003,
            'message' => [
                'message_id' => 303,
                'from' => ['id' => 77112233, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 77112233, 'type' => 'private'],
                'date' => time(),
                'text' => 'Budi Handoko',
            ],
        ]);
        $resTeacher->assertStatus(200);

        // Step 4: Guru A mengetikkan alasan pergantian
        $resReason = $this->postJson(route('telegram.webhook'), [
            'update_id' => 700004,
            'message' => [
                'message_id' => 304,
                'from' => ['id' => 77112233, 'first_name' => 'Ahmad'],
                'chat' => ['id' => 77112233, 'type' => 'private'],
                'date' => time(),
                'text' => 'Menghadiri workshop dinas luar kota.',
            ],
        ]);
        $resReason->assertStatus(200);

        // Verifikasi database: swap tersimpan dengan status 'menunggu'
        $swap = PiketDutySwap::where('requester_id', $guruA->id)->first();
        $this->assertNotNull($swap);
        $this->assertEquals('menunggu', $swap->status);
        $this->assertEquals($guruB->id, $swap->replacement_teacher_id);
        $this->assertEquals('2026-10-09', $swap->duty_date->format('Y-m-d'));

        // Verifikasi notifikasi terkirim ke Guru B (77223344)
        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['chat_id'] ?? null) === '77223344';
        });

        // Step 5: Guru B (yang bersangkutan) langsung MENYETUJUI via Telegram tanpa perlu pihak lain
        $resApprove = $this->postJson(route('telegram.webhook'), [
            'update_id' => 700005,
            'callback_query' => [
                'id' => 'cb_pds_acc',
                'from' => ['id' => 77223344, 'first_name' => 'Budi'],
                'message' => [
                    'message_id' => 401,
                    'chat' => ['id' => 77223344, 'type' => 'private'],
                ],
                'data' => "pds_acc_{$swap->id}",
            ],
        ]);
        $resApprove->assertStatus(200);

        // Verifikasi database: swap status langsung 'disetujui' dan approved_by = Guru B
        $swap->refresh();
        $this->assertEquals('disetujui', $swap->status);
        $this->assertEquals($guruB->id, $swap->approved_by);
        $this->assertNotNull($swap->approved_at);

        // Verifikasi konfirmasi terkirim ke Guru A (77112233), Waka Kurikulum (77334455) & Grup Tim Piket (999111222)
        Http::assertSent(function ($request) {
            $body = $request->data();
            $chatId = $body['chat_id'] ?? null;

            return $chatId === '77112233' || $chatId === '77334455' || $chatId === '999111222';
        });

        Carbon::setTestNow();
    }

    public function test_teacher_can_request_duty_swap_and_replacement_teacher_can_reject_via_telegram(): void
    {
        Carbon::setTestNow('2026-10-02 08:00:00'); // Jumat
        $setting = SchoolSetting::getSetting();
        $setting->update([
            'academic_year' => '2026/2027',
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_notifications_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 888]], 200),
        ]);

        $guruRole = Role::where('slug', 'guru')->first();

        $guruA = User::factory()->create([
            'username' => 'ahmad_rej_test',
            'name' => 'Ahmad Rej',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '66112233',
            'is_active' => true,
        ]);

        $guruB = User::factory()->create([
            'username' => 'budi_rej_test',
            'name' => 'Budi Rej',
            'role_id' => $guruRole?->id,
            'telegram_chat_id' => '66223344',
            'is_active' => true,
        ]);

        $schedule = PiketSchedule::create([
            'academic_year' => '2026/2027',
            'teacher_id' => $guruA->id,
            'day' => 'Jumat',
            'role' => 'anggota',
            'duty_location' => 'Pos Gerbang',
            'is_active' => true,
        ]);

        $swap = PiketDutySwap::create([
            'original_schedule_id' => $schedule->id,
            'requester_id' => $guruA->id,
            'replacement_teacher_id' => $guruB->id,
            'duty_date' => '2026-10-09',
            'reason' => 'Ada acara mendadak',
            'status' => 'menunggu',
        ]);

        // Guru B menolak permohonan tukar piket
        $resReject = $this->postJson(route('telegram.webhook'), [
            'update_id' => 800001,
            'callback_query' => [
                'id' => 'cb_pds_rej',
                'from' => ['id' => 66223344, 'first_name' => 'Budi'],
                'message' => [
                    'message_id' => 501,
                    'chat' => ['id' => 66223344, 'type' => 'private'],
                ],
                'data' => "pds_rej_{$swap->id}",
            ],
        ]);
        $resReject->assertStatus(200);

        $swap->refresh();
        $this->assertEquals('ditolak', $swap->status);
        $this->assertEquals($guruB->id, $swap->approved_by);

        // Notifikasi penolakan dikirim ke Guru A
        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['chat_id'] ?? null) === '66112233';
        });

        Carbon::setTestNow();
    }

    public function test_piket_officer_today_and_scheduled_teacher_can_access_duty_swaps_and_reports(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00')); // Monday (Senin)

        // Guru yang bertugas piket hari ini (seperti Army Widhi)
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->teacher->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $this->assertTrue($this->teacher->isPiketOfficerToday());
        $this->assertTrue($this->teacher->canManagePiketSchedules());
        $this->assertTrue($this->teacher->canViewPiketReports());

        // Bisa akses tukar jadwal tanpa 403
        $responseSwaps = $this->actingAs($this->teacher)->get(route('piket.swaps.index'));
        $responseSwaps->assertStatus(200);

        // Bisa akses laporan tanpa 403
        $responseReports = $this->actingAs($this->teacher)->get(route('piket.reports.index'));
        $responseReports->assertStatus(200);

        // Guru lain yang punya jadwal piket besok (Selasa) juga bisa akses tukar jadwal
        $tomorrowTeacher = User::factory()->create([
            'username' => 'guru_selasa',
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Selasa',
            'teacher_id' => $tomorrowTeacher->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $this->assertFalse($tomorrowTeacher->isPiketOfficerToday());
        $this->assertTrue($tomorrowTeacher->canManagePiketSchedules());

        $resTomorrowSwap = $this->actingAs($tomorrowTeacher)->get(route('piket.swaps.index'));
        $resTomorrowSwap->assertStatus(200);

        Carbon::setTestNow();
    }

    public function test_piket_dashboard_disables_unauthorized_links_for_non_duty_teacher(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 8, 0, 0)); // Hari Senin

        // Teacher has schedule on Tuesday, not today (Monday)
        $nonDutyTeacher = User::factory()->create([
            'username' => 'guru_nonduty',
            'role_id' => Role::where('slug', 'guru')->first()?->id,
            'is_active' => true,
        ]);
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Selasa',
            'teacher_id' => $nonDutyTeacher->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $this->assertFalse($nonDutyTeacher->canOperatePiket());
        $this->assertFalse($nonDutyTeacher->canManagePiketCases());

        // 1. Mobile View
        $mobileResponse = $this->actingAs($nonDutyTeacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('piket.dashboard'));

        $mobileResponse->assertStatus(200);
        $mobileResponse->assertSee('Terkunci');
        $mobileResponse->assertDontSee(route('piket.journals.today'));
        $mobileResponse->assertSee(route('piket.schedules.index'));
        $mobileResponse->assertSee(route('piket.swaps.index'));

        // 2. Desktop Web View
        $webResponse = $this->actingAs($nonDutyTeacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
            ->get(route('piket.dashboard'));

        $webResponse->assertStatus(200);
        $webResponse->assertSee('disabled');
        $webResponse->assertDontSee(route('piket.journals.today'));

        Carbon::setTestNow();
    }
}
