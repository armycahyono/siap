<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $admin;

    protected Role $guruRole;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guruRole = Role::create([
            'name' => 'Guru Pengajar',
            'slug' => 'guru',
            'description' => 'Guru Pengajar',
            'is_system' => true,
        ]);

        $this->adminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => 'super_admin',
            'description' => 'Super Administrator Sistem',
            'is_system' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Budi Santoso, S.Pd.',
            'username' => 'budi_guru',
            'email' => 'budi@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->guruRole->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Sekolah',
            'username' => 'admin_sekolah',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'grade_a_min' => 86,
            'grade_b_min' => 76,
            'grade_c_min' => 61,
        ]);
    }

    public function test_desktop_user_gets_pure_web_layout_with_sidebar(): void
    {
        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        // Has desktop sidebar
        $response->assertSee('navbar-vertical');
        $response->assertSee('sidebar-menu');
        // Does NOT have mobile app bar
        $response->assertDontSee('mobile-top-bar');
    }

    public function test_mobile_user_gets_pure_mobile_layout_without_sidebar(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        // Renders mobile dashboard & layout
        $response->assertSee('SIAP SMK PGRI KRAS');
        $response->assertSee('mobile-bottom-nav');
        $response->assertSee('mobile-top-bar');
        // Pure mobile has ZERO desktop sidebar
        $response->assertDontSee('navbar-vertical');
        $response->assertDontSee('id="sidebar-menu"', false);
    }

    public function test_admin_on_mobile_gets_clean_apk_home_gtk(): void
    {
        $mobileAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

        $response = $this->actingAs($this->admin)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('SIAP SMK PGRI KRAS');
        $response->assertSee('mobile-bottom-nav');
        // Zero desktop sidebar residue on mobile
        $response->assertDontSee('navbar-vertical');
    }

    public function test_profile_page_splits_between_mobile_and_desktop(): void
    {
        // Desktop
        $resDesktop = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
            ->get(route('profile'));
        $resDesktop->assertStatus(200);
        $resDesktop->assertSee('navbar-vertical');

        // Mobile
        $resMobile = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 12; Pixel 6) Chrome/100.0.0.0 Mobile Safari/537.36'])
            ->get(route('profile'));
        $resMobile->assertStatus(200);
        $resMobile->assertSee('mobile-bottom-nav');
        $resMobile->assertDontSee('navbar-vertical');
    }

    public function test_query_parameter_view_mode_allows_manual_switching(): void
    {
        // On desktop browser, forced mobile view via ?view_mode=mobile
        $response = $this->actingAs($this->teacher)->get(route('dashboard', ['view_mode' => 'mobile']));
        $response->assertStatus(200);
        $response->assertSee('SIAP SMK PGRI KRAS');
        $response->assertDontSee('navbar-vertical');

        // On mobile browser, forced web view via ?view_mode=web
        $responseWeb = $this->actingAs($this->teacher)->get(route('dashboard', ['view_mode' => 'web']));
        $responseWeb->assertStatus(200);
        $responseWeb->assertSee('navbar-vertical');
    }

    public function test_mobile_user_on_piket_routes_gets_pure_mobile_layout(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $routes = [
            route('piket.dashboard'),
            route('piket.permits.create'),
            route('piket.permits.index'),
            route('piket.inval.index'),
            route('piket.guests.index'),
            route('piket.incidents.create'),
            route('piket.incidents.index'),
            route('piket.reports.index'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->admin)
                ->withHeaders(['User-Agent' => $mobileAgent])
                ->get($url);

            $response->assertStatus(200);
            $response->assertSee('mobile-bottom-nav');
            $response->assertDontSee('navbar-vertical');
        }
    }

    public function test_mobile_user_on_attendance_routes_gets_pure_mobile_layout(): void
    {
        $mobileAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

        $routes = [
            route('attendance.subject.index'),
            route('attendance.geotag.index'),
            route('attendance.gtk-permits.index'),
            route('attendance.homeroom-permits.index'),
            route('attendance.subject.history'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->admin)
                ->withHeaders(['User-Agent' => $mobileAgent])
                ->get($url);

            $response->assertStatus(200);
            $response->assertSee('mobile-bottom-nav');
            $response->assertDontSee('navbar-vertical');
        }
    }

    public function test_mobile_user_can_access_tugas_page_with_mobile_layout(): void
    {
        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Senin',
        };

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $todayDay,
            'teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('tasks'));

        $response->assertStatus(200);
        $response->assertSee('Tugas dan Tanggung Jawab');
        $response->assertSee('Meja Piket Sekolah');
        $response->assertSee('Tugas Wali Kelas');
        $response->assertSee('Izin / Cuti GTK Mandiri');
        $response->assertSee('mobile-bottom-nav');
        $response->assertDontSee('navbar-vertical');
    }

    public function test_beranda_displays_status_hadir_and_izin_gtk_side_by_side(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Status Hadir:');
        $response->assertSee('Izin / Cuti GTK:');
        $response->assertSee('Agenda Sekolah:');
        $response->assertSee(route('profile'), false);
    }

    public function test_mobile_layout_renders_form_input_borders(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('attendance.gtk-permits.index'));

        $response->assertStatus(200);
        // Has clear input border styles in layout
        $response->assertSee('.form-control,', false);
        $response->assertSee('border: 1.5px solid #cbd5e1', false);
    }

    public function test_beranda_displays_today_curriculum_events_from_calendar(): void
    {
        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Penilaian Tengah Semester (PTS)',
            'category' => 'kts',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'is_holiday' => false,
            'learning_impact' => 'pulang_cepat',
        ]);

        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Penilaian Tengah Semester (PTS)');
        $response->assertSee('Agenda Kegiatan Hari Ini (1):');
        $response->assertDontSee('Kegiatan Tengah Semester (KTS)');
        $response->assertSee('Pulang Cepat');
        $response->assertSee('Semua Siswa');
    }

    public function test_mobile_agenda_displays_custom_subtitle_format(): void
    {
        $event = new CurriculumEvent([
            'start_date' => '2026-10-08',
            'end_date' => '2026-10-29',
            'target_audience' => 'tingkat',
            'target_grade_levels' => [12],
            'learning_impact' => 'bebas_kbm_kegiatan',
        ]);

        $this->assertEquals('Tgl:08/10-29/10/26 | Kelas XII | Bebas KBM', $event->mobile_subtitle);
    }

    public function test_mobile_displays_shortened_kbm_learning_impact_labels(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Praktik Kerja Lapangan (PKL)',
            'category' => 'lainnya',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'is_holiday' => false,
            'learning_impact' => 'bebas_kbm_kegiatan',
        ]);

        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Belajar Dari Rumah Banjir',
            'category' => 'lainnya',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'is_holiday' => false,
            'learning_impact' => 'pembelajaran_daring',
        ]);

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Bebas KBM');
        $response->assertSee('KBM Daring');
        $response->assertDontSee('Bebas KBM (Kegiatan Khusus: PKL / KI / Ujian / Gladi)');
        $response->assertDontSee('Pembelajaran Daring (Dari Rumah)');

        $eventNormal = new CurriculumEvent(['learning_impact' => 'normal']);
        $eventDaring = new CurriculumEvent(['learning_impact' => 'pembelajaran_daring']);
        $eventBebas = new CurriculumEvent(['learning_impact' => 'bebas_kbm_kegiatan']);

        $this->assertEquals('KBM Normal', $eventNormal->learning_impact_short_label);
        $this->assertEquals('KBM Daring', $eventDaring->learning_impact_short_label);
        $this->assertEquals('Bebas KBM', $eventBebas->learning_impact_short_label);
    }

    public function test_calendar_route_renders_mobile_calendar_view_on_mobile_devices(): void
    {
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $responseMobile = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('kurikulum.calendar.index'));

        $responseMobile->assertStatus(200);
        $responseMobile->assertViewIs('mobile.calendar.index');
        $responseMobile->assertSee('Kalender Kegiatan');
        $responseMobile->assertSee('calendarDaysGrid');

        $desktopAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

        $responseDesktop = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $desktopAgent])
            ->get(route('kurikulum.calendar.index'));

        $responseDesktop->assertStatus(200);
        $responseDesktop->assertViewIs('kurikulum.calendar.index');
    }

    public function test_mobile_kbm_page_only_shows_events_impacting_logged_in_teacher(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00'); // Senin
        $mobileAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36';

        $gl12 = GradeLevel::create(['level' => 12, 'name' => 'XII']);
        $gl10 = GradeLevel::create(['level' => 10, 'name' => 'X']);

        $cls12 = Classroom::create([
            'name' => 'XII RPL 1',
            'grade_level_id' => $gl12->id,
            'academic_year' => '2026/2027',
        ]);

        $cls10 = Classroom::create([
            'name' => 'X TKJ 1',
            'grade_level_id' => $gl10->id,
            'academic_year' => '2026/2027',
        ]);

        $sub = Subject::create([
            'name' => 'Pemrograman Web',
            'code' => 'PW12',
        ]);

        $todayDay = now()->locale('id')->isoFormat('dddd');

        // Teacher teaches Class XII today
        LessonSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'classroom_id' => $cls12->id,
            'subject_id' => $sub->id,
            'teacher_id' => $this->teacher->id,
            'day' => $todayDay,
            'start_period' => 1,
            'end_period' => 2,
        ]);

        // Event 1: Affects Class XII (impacts this teacher)
        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Uji Kompetensi Keahlian XII',
            'category' => 'lainnya',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'target_audience' => 'rombel',
            'target_classroom_ids' => [$cls12->id],
            'learning_impact' => 'bebas_kbm_kegiatan',
        ]);

        // Event 2: Affects Class X only (does NOT impact this teacher)
        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => 'Masa Pengenalan Lingkungan X',
            'category' => 'lainnya',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'target_audience' => 'rombel',
            'target_classroom_ids' => [$cls10->id],
            'learning_impact' => 'bebas_kbm_kegiatan',
        ]);

        $response = $this->actingAs($this->teacher)
            ->withHeaders(['User-Agent' => $mobileAgent])
            ->get(route('attendance.subject.index'));

        $response->assertStatus(200);
        $response->assertSee('Uji Kompetensi Keahlian XII');
        $response->assertSee('Agenda Berdampak Bagi Anda');
        $response->assertDontSee('Masa Pengenalan Lingkungan X');

        Carbon::setTestNow();
    }
}
