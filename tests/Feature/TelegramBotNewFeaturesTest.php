<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Permission;
use App\Models\PiketGuest;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\SchoolActivity;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceSession;
use App\Models\TelegramChannel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotNewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected Classroom $classroom;

    protected Subject $subject;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-10-26 adalah hari Senin
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00', 'Asia/Jakarta'));

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 12345]], 200),
        ]);

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'SMK PGRI Kras',
            'telegram_bot_token' => 'dummy-bot-token-12345',
            'telegram_bot_username' => 'smkpgrikras_bot',
            'telegram_notifications_enabled' => true,
        ]);

        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru Mata Pelajaran', 'description' => 'Guru']);

        $this->teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
            'telegram_chat_id' => '123456789',
            'nip' => '198501012010011001',
        ]);
        $this->teacher->assignRole($guruRole, ['academic_year' => '2026/2027', 'status' => true]);

        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $this->classroom = Classroom::firstOrCreate(
            ['name' => 'X TKJ 1'],
            ['grade_level_id' => $grade->id, 'academic_year' => '2026/2027']
        );

        $this->subject = Subject::firstOrCreate(['code' => 'TKJ01'], ['name' => 'Dasar Jaringan']);

        $this->student = Student::firstOrCreate(
            ['nis' => '12345'],
            [
                'name' => 'Budi Santoso',
                'classroom_id' => $this->classroom->id,
                'academic_year' => '2026/2027',
                'status' => 'aktif',
            ]
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_telegram_bot_my_attendance_command(): void
    {
        AttendanceLog::create([
            'pin' => '12345',
            'user_type' => 'teacher',
            'user_id' => $this->teacher->id,
            'scanned_at' => Carbon::today()->setTime(6, 45),
            'verify_type' => 'geotag',
            'device_sn' => 'GEOTAG_MOBILE',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Budi'],
                'text' => '/presensi',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'STATUS PRESENSI GTK HARI INI')
                && str_contains($request['text'], '06:45 WIB');
        });
    }

    public function test_telegram_bot_my_teaching_schedule_command(): void
    {
        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Budi'],
                'text' => '/jadwalsaya',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'JADWAL MENGAJAR SAYA HARI INI')
                && str_contains($request['text'], 'X TKJ 1')
                && str_contains($request['text'], 'Dasar Jaringan');
        });
    }

    public function test_telegram_bot_school_agenda_command(): void
    {
        $activity = SchoolActivity::create([
            'name' => 'Penilaian Tengah Semester',
            'category' => 'assessment',
            'is_active' => true,
        ]);

        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'category' => 'assessment',
            'title' => 'PTS Semester Ganjil',
            'school_activity_id' => $activity->id,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
            'is_holiday' => false,
            'location' => 'Kampus 1',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Budi'],
                'text' => '/agenda',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'AGENDA & KEGIATAN SEKOLAH TERDEKAT')
                && str_contains($request['text'], 'PTS Semester Ganjil');
        });
    }

    public function test_telegram_bot_today_guests_command(): void
    {
        $journal = PiketJournal::create([
            'date' => Carbon::today()->toDateString(),
            'academic_year' => '2026/2027',
            'status' => 'berlangsung',
        ]);

        PiketGuest::create([
            'piket_journal_id' => $journal->id,
            'visit_date' => Carbon::today()->toDateString(),
            'check_in_time' => '09:00:00',
            'guest_name' => 'Drs. H. Hartono, M.Pd.',
            'institution' => 'Dinas Pendidikan',
            'purpose' => 'Monitoring KBM',
            'meet_with' => 'Kepala Sekolah',
            'recorded_by' => $this->teacher->id,
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Budi'],
                'text' => '/tamu',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'BUKU TAMU PIKET HARI INI')
                && str_contains($request['text'], 'Drs. H. Hartono, M.Pd.');
        });
    }

    public function test_telegram_bot_student_discipline_check_command(): void
    {
        PiketIncident::create([
            'academic_year' => '2026/2027',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '08:00:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'seragam',
            'description' => 'Tidak memakai ikat pinggang resmi sekolah',
            'action_taken' => 'Peringatan lisan di meja piket',
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Peringatan Lisan',
            'recorded_by' => $this->teacher->id,
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Budi'],
                'text' => '/poin 12345',
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'REKAM JEJAK KEDISIPLINAN SISWA')
                && (stripos($request['text'], 'Budi Santoso') !== false)
                && str_contains($request['text'], 'Tidak memakai ikat pinggang resmi sekolah');
        });
    }

    public function test_teaching_reminder_command_runs_successfully(): void
    {
        $this->artisan('telegram:teaching-reminder', ['--test' => true])
            ->assertSuccessful();
    }

    public function test_teaching_reminder_sends_notification_within_10_minutes_window(): void
    {
        // Jam 06:51 WIB (9 menit sebelum Jam 1 mulai pukul 07:00)
        Carbon::setTestNow(Carbon::parse('2026-10-26 06:51:00', 'Asia/Jakarta'));

        LessonSchedule::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $this->artisan('telegram:teaching-reminder', ['--force' => true])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'PENGINGAT KBM — 10 MENIT LAGI')
                && str_contains($request['text'], 'X TKJ 1')
                && str_contains($request['text'], 'Dasar Jaringan');
        });
    }

    public function test_homeroom_teacher_can_access_student_attendance_in_own_classroom(): void
    {
        // Tetapkan teacher sebagai wali kelas untuk $this->classroom
        $this->classroom->update([
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['first_name' => 'Pak Guru'],
                'text' => '/cek_absen_siswa',
            ],
        ]);

        $response->assertOk();

        // Bot harus langsung menampilkan informasi kelas dan tombol siswa (Budi Santoso)
        Http::assertSent(function ($request) {
            $isSendMessage = str_contains($request->url(), 'sendMessage');
            $hasClassName = str_contains($request['text'], 'X TKJ 1');
            $hasStudentButton = false;

            if (isset($request['reply_markup'])) {
                $markup = is_string($request['reply_markup']) ? json_decode($request['reply_markup'], true) : $request['reply_markup'];
                $hasStudentButton = stripos(json_encode($markup), 'Budi Santoso') !== false;
            }

            return $isSendMessage && $hasClassName && $hasStudentButton;
        });
    }

    public function test_homeroom_teacher_cannot_access_students_from_other_classroom(): void
    {
        // Guru menjadi wali kelas X TKJ 1
        $this->classroom->update([
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        // Buat kelas lain dan siswa lain
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $otherClassroom = Classroom::create([
            'name' => 'X TKR 1',
            'grade_level_id' => $grade->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $otherStudent = Student::create([
            'nis' => '99999',
            'name' => 'Siswa Kelas Lain',
            'classroom_id' => $otherClassroom->id,
            'academic_year' => '2026/2027',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // Simpan sesi aktif wali kelas
        Cache::put('tg_piket_state_123456789', [
            'flow' => 'check_student_attendance',
            'step' => 'select_student',
            'user_id' => $this->teacher->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => null,
            'page' => 1,
        ], now()->addMinutes(15));

        // Guru wali kelas X TKJ 1 mencoba memanggil callback siswa dari kelas lain
        $response = $this->postJson(route('telegram.webhook'), [
            'callback_query' => [
                'id' => 'cb_unauth_123',
                'from' => ['id' => 123456789, 'first_name' => 'Pak Guru'],
                'message' => ['chat' => ['id' => 123456789], 'message_id' => 100],
                'data' => "att_std_{$otherStudent->id}",
            ],
        ]);

        $response->assertOk();

        // Bot harus menolak akses
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Akses Ditolak')
                && str_contains($request['text'], 'kelas perwalian Anda sendiri');
        });
    }

    public function test_bk_teacher_can_access_any_classroom_and_view_recap(): void
    {
        // Buat akun Guru BK
        $bkRole = Role::firstOrCreate(['slug' => 'bk'], ['name' => 'Bimbingan Konseling', 'description' => 'Guru BK']);
        $bkUser = User::factory()->create([
            'role_id' => $bkRole->id,
            'position' => 'guru bk',
            'telegram_chat_id' => '88888888',
            'nip' => '198901012015011002',
        ]);
        $bkUser->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);

        // Kirim perintah cek absen siswa dari Guru BK
        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'chat' => ['id' => 88888888],
                'from' => ['first_name' => 'Bu BK'],
                'text' => '📊 Cek Absen Siswa',
            ],
        ]);

        $response->assertOk();

        // Bot menyajikan daftar kelas untuk dipilih
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'CEK REKAP ABSEN & KEJADIAN SISWA')
                && str_contains($request['text'], 'Pilih kelas siswa yang ingin diperiksa');
        });
    }

    public function test_student_attendance_recap_renders_data_correctly_for_selected_period(): void
    {
        // Buat sesi KBM dan catatan presensi siswa
        $session = SubjectAttendanceSession::create([
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'attendance_date' => '2026-10-26',
            'start_period' => 1,
            'end_period' => 2,
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
        ]);

        SubjectAttendanceRecord::create([
            'subject_attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'h',
        ]);

        // Buat izin piket
        PiketStudentPermit::create([
            'permit_number' => 'IZN-2026-001',
            'permit_date' => '2026-10-26',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'terlambat',
            'reason' => 'Ban motor bocor di jalan',
            'given_by' => $this->teacher->id,
            'status' => 'diberikan',
        ]);

        // Buat kejadian/kasus siswa
        PiketIncident::create([
            'academic_year' => '2026/2027',
            'incident_date' => '2026-10-26',
            'incident_time' => '07:30:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'seragam',
            'description' => 'Kaos kaki tidak sesuai warna ketentuan sekolah',
            'action_taken' => 'Peringatan lisan di meja piket',
            'verification_status' => 'verified',
            'recorded_by' => $this->teacher->id,
        ]);

        // Guru menjadi wali kelas
        $this->classroom->update([
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        // Simpan state pemilihan siswa
        $cacheKey = 'tg_piket_state_123456789';
        Cache::put($cacheKey, [
            'flow' => 'check_student_attendance',
            'step' => 'select_period',
            'user_id' => $this->teacher->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'page' => 1,
        ], now()->addMinutes(15));

        // Panggil callback periode 'minggu'
        $response = $this->postJson(route('telegram.webhook'), [
            'callback_query' => [
                'id' => 'cb_period_777',
                'from' => ['id' => 123456789, 'first_name' => 'Pak Guru'],
                'message' => ['chat' => ['id' => 123456789], 'message_id' => 101],
                'data' => 'att_per_minggu',
            ],
        ]);

        $response->assertOk();

        // Bot harus mengirimkan pesan rekap presensi KBM, perizinan piket, dan kejadian siswa
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'REKAP PRESENSI & KEJADIAN SISWA')
                && stripos($request['text'], 'Budi Santoso') !== false
                && str_contains($request['text'], 'X TKJ 1')
                && str_contains($request['text'], 'PRESENSI KBM MATA PELAJARAN')
                && str_contains($request['text'], 'CATATAN PERIZINAN MEJA PIKET')
                && str_contains($request['text'], 'Ban motor bocor di jalan')
                && str_contains($request['text'], 'CATATAN KEJADIAN / PELANGGARAN')
                && str_contains($request['text'], 'Kaos kaki tidak sesuai');
        });
    }

    public function test_group_casual_chat_is_ignored(): void
    {
        TelegramChannel::updateOrCreate(
            ['code' => 'piket'],
            [
                'name' => 'Grup Piket',
                'chat_id' => '-100111222333',
                'enabled_commands' => ['/piket', '/inval'],
                'is_active' => true,
            ]
        );

        Http::fake();

        // Pesan obrolan biasa (tanpa awalan /) di grup
        $response = $this->postJson(route('telegram.webhook'), [
            'message' => [
                'message_id' => 99,
                'chat' => ['id' => -100111222333, 'type' => 'supergroup'],
                'from' => ['id' => 123456789, 'first_name' => 'Pak Guru'],
                'text' => 'Halo bapak ibu, besok ada rapat ya?',
            ],
        ]);

        $response->assertOk();

        // Tidak ada pesan terkirim ke Telegram
        Http::assertNothingSent();
    }

    public function test_group_command_routing_and_channel_scoping(): void
    {
        TelegramChannel::updateOrCreate(
            ['code' => 'piket'],
            [
                'name' => 'Grup Piket',
                'chat_id' => '-100111222333',
                'enabled_commands' => ['/piket'],
                'is_active' => true,
            ]
        );

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        // Perintah yang tidak diizinkan di grup ini (/agenda tidak ada di enabled_commands)
        $this->postJson(route('telegram.webhook'), [
            'message' => [
                'message_id' => 100,
                'chat' => ['id' => -100111222333, 'type' => 'supergroup'],
                'from' => ['id' => 123456789, 'first_name' => 'Pak Guru'],
                'text' => '/agenda@smkpgrikras_bot',
            ],
        ])->assertOk();

        // Karena /agenda dinonaktifkan di grup ini, bot diam (tidak merespons dan tidak replyUnknown)
        Http::assertNothingSent();

        // Perintah yang diizinkan (/piket)
        $this->postJson(route('telegram.webhook'), [
            'message' => [
                'message_id' => 101,
                'chat' => ['id' => -100111222333, 'type' => 'supergroup'],
                'from' => ['id' => 123456789, 'first_name' => 'Pak Guru'],
                'text' => '/piket',
            ],
        ])->assertOk();

        // Bot merespons /piket
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'TIM PIKET HARI INI');
        });
    }

    public function test_telegram_channel_management_and_invite(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $perm = Permission::firstOrCreate(['slug' => 'manage_settings'], ['name' => 'Manage Settings', 'group' => 'Settings']);
        $adminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $admin->assignRole($adminRole, ['academic_year' => '2026/2027', 'status' => true]);

        // 1. Simpan channel baru
        $response = $this->actingAs($admin)->post(route('telegram.channel.store'), [
            'name' => 'Grup Panitia Ujian',
            'chat_id' => '-10099887766',
            'description' => 'Saluran koordinasi panitia ujian',
            'allowed_roles' => ['guru'],
            'enabled_commands' => ['/jadwal', '/bantuan'],
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('telegram_channels', [
            'name' => 'Grup Panitia Ujian',
            'chat_id' => '-10099887766',
        ]);

        $channel = TelegramChannel::where('name', 'Grup Panitia Ujian')->first();

        // 2. Sync commands to Telegram API
        Http::fake([
            'https://api.telegram.org/*/setMyCommands' => Http::response(['ok' => true, 'result' => true], 200),
            'https://api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $syncRes = $this->actingAs($admin)->post(route('telegram.channel.sync-commands', $channel->id));
        $syncRes->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setMyCommands')
                && $request['scope']['type'] === 'chat'
                && $request['scope']['chat_id'] === '-10099887766';
        });

        // 3. Invite eligible users (teacher has role guru and telegram_chat_id 123456789)
        $channel->update(['invite_link' => 'https://t.me/+AbCdEfG']);

        $inviteRes = $this->actingAs($admin)->post(route('telegram.channel.invite', $channel->id));
        $inviteRes->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '123456789'
                && str_contains($request['text'], 'UNDANGAN BERGABUNG KE GRUP SEKOLAH')
                && str_contains($request['text'], 'https://t.me/+AbCdEfG');
        });
    }
}
