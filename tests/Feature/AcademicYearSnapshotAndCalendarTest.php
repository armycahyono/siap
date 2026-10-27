<?php

namespace Tests\Feature;

use App\Models\AcademicCalendarUpload;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\DailyClassroomKbmSnapshot;
use App\Models\DailyKbmRoster;
use App\Models\DailyScheduleTarget;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicCalendarService;
use App\Services\DailyKbmSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcademicYearSnapshotAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher;

    protected Classroom $classroom2026;

    protected Classroom $classroom2024;

    protected Subject $subject;

    protected AcademicYear $year2024;

    protected AcademicYear $year2026;

    protected AcademicYear $year2027;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], [
            'name' => 'Super Admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);

        $teacherRole = Role::firstOrCreate(['slug' => 'guru'], [
            'name' => 'Guru',
            'description' => 'Guru Pengampu',
            'is_system' => false,
        ]);

        $this->year2024 = AcademicYear::create([
            'name' => '2024/2025',
            'semester' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $this->year2026 = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->year2027 = AcademicYear::create([
            'name' => '2027/2028',
            'semester' => 'Ganjil',
            'start_date' => '2027-07-15',
            'end_date' => '2028-06-20',
            'is_active' => false,
        ]);

        SchoolSetting::create([
            'school_name' => 'SMK Negeri 1 Test',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
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
            'name' => 'Guru Pengampu',
            'username' => 'guru1',
            'email' => 'guru1@test.com',
            'password' => bcrypt('password'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        $dept = Department::create(['code' => 'TKR', 'name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::create(['name' => 'X', 'level' => 10]);

        $this->classroom2026 = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->year2026->id,
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        $this->classroom2024 = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => '2024/2025',
            'academic_year_id' => $this->year2024->id,
            'department_id' => $dept->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'MAT-X',
            'name' => 'Matematika X',
            'category' => 'Umum',
            'is_active' => true,
        ]);

        // Buat jadwal untuk 2026/2027 dan 2024/2025
        LessonSchedule::create([
            'classroom_id' => $this->classroom2026->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);
    }

    /**
     * Skenario 1 — Sesi tampilan arsip tidak mencemari snapshot tanggal berjalan.
     */
    public function test_archive_session_does_not_contaminate_current_date_snapshot(): void
    {
        // Set sesi pengguna ke tahun arsip 2024/2025
        session(['active_academic_year' => '2024/2025']);

        $service = app(DailyKbmSnapshotService::class);
        $today = Carbon::today();

        $service->generateSnapshotForDate($today);

        $dateStr = $today->toDateString();

        $roster = DailyKbmRoster::whereDate('date', $dateStr)->first();
        $this->assertNotNull($roster);
        $this->assertEquals('2026/2027', $roster->academic_year);

        $snapshot = DailyClassroomKbmSnapshot::whereDate('date', $dateStr)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('2026/2027', $snapshot->academic_year);
    }

    /**
     * Skenario 2 — Snapshot historis tidak otomatis menggunakan tahun operasional yang tidak sesuai.
     */
    public function test_historical_snapshot_uses_matching_historical_calendar_year(): void
    {
        $historicalDate = Carbon::parse('2024-10-15'); // Berada dalam rentang TP 2024/2025

        $service = app(DailyKbmSnapshotService::class);
        $service->generateSnapshotForDate($historicalDate);

        $dateStr = $historicalDate->toDateString();

        $roster = DailyKbmRoster::whereDate('date', $dateStr)->first();
        $this->assertNotNull($roster);
        // Harus menggunakan tahun ajaran historis 2024/2025, bukan 2026/2027
        $this->assertEquals('2024/2025', $roster->academic_year);

        $snapshot = DailyClassroomKbmSnapshot::whereDate('date', $dateStr)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('2024/2025', $snapshot->academic_year);
    }

    /**
     * Skenario 3 — Kalender baru menggunakan tahun target yang benar tanpa tercemar sesi arsip.
     */
    public function test_new_calendar_event_defaults_to_operational_year_ignoring_archive_session(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('kurikulum.calendar.store'), [
                'title' => 'Rapat Awal Semester',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-08-01',
                // academic_year sengaja tidak dikirim
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertJson(['success' => true]);

        $event = CurriculumEvent::where('title', 'Rapat Awal Semester')->first();
        $this->assertNotNull($event);
        $this->assertEquals('2026/2027', $event->academic_year);
    }

    /**
     * Skenario 4 — Tahun target eksplisit yang valid tetap dihormati.
     */
    public function test_explicit_target_academic_year_is_honored_in_calendar_event_and_upload(): void
    {
        // 1. Buat event dengan target eksplisit 2027/2028
        $response = $this->actingAs($this->admin)
            ->post(route('kurikulum.calendar.store'), [
                'title' => 'Persiapan TP 2027/2028',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2027-08-01',
                'academic_year' => '2027/2028',
            ]);

        $response->assertSessionHasNoErrors();
        $event = CurriculumEvent::where('title', 'Persiapan TP 2027/2028')->first();
        $this->assertNotNull($event);
        $this->assertEquals('2027/2028', $event->academic_year);

        // 2. Upload dokumen Kaldik untuk 2027/2028
        $file = UploadedFile::fake()->create('kaldik_2027.pdf', 500, 'application/pdf');

        $uploadResponse = $this->actingAs($this->admin)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('kurikulum.calendar.upload'), [
                'kaldik_file' => $file,
                'title' => 'Kaldik Resmi 2027/2028',
                'academic_year' => '2027/2028',
            ]);

        $uploadResponse->assertSessionHasNoErrors();
        $upload = AcademicCalendarUpload::where('title', 'Kaldik Resmi 2027/2028')->first();
        $this->assertNotNull($upload);
        $this->assertEquals('2027/2028', $upload->academic_year);
    }

    /**
     * Skenario 5 — Tahun ajaran tidak valid ditolak secara terkendali.
     */
    public function test_unregistered_academic_year_is_rejected_by_calendar_and_upload(): void
    {
        // 1. Event dengan tahun fiktif
        $response = $this->actingAs($this->admin)
            ->post(route('kurikulum.calendar.store'), [
                'title' => 'Kegiatan Fiktif',
                'category' => 'kegiatan_kurikulum',
                'start_date' => '2026-08-01',
                'academic_year' => '1999/2000',
            ]);

        $response->assertSessionHasErrors('academic_year');

        // 2. Upload dengan tahun fiktif
        $file = UploadedFile::fake()->create('kaldik_fiktif.pdf', 500, 'application/pdf');
        $uploadResponse = $this->actingAs($this->admin)
            ->post(route('kurikulum.calendar.upload'), [
                'kaldik_file' => $file,
                'academic_year' => '1999/2000',
            ]);

        $uploadResponse->assertSessionHasErrors('academic_year');
    }

    /**
     * Skenario 6 — Inisialisasi kalender tidak membuat duplikasi data.
     */
    public function test_calendar_initialization_does_not_create_duplicates(): void
    {
        // Panggil seed default 2026 dua kali
        $upload1 = AcademicCalendarService::seedDefaultKaldik2026($this->admin->id);
        $countUploadsAfterFirst = AcademicCalendarUpload::where('academic_year', '2026/2027')->count();
        $countEventsAfterFirst = CurriculumEvent::where('academic_year', '2026/2027')->count();

        $upload2 = AcademicCalendarService::seedDefaultKaldik2026($this->admin->id);
        $countUploadsAfterSecond = AcademicCalendarUpload::where('academic_year', '2026/2027')->count();
        $countEventsAfterSecond = CurriculumEvent::where('academic_year', '2026/2027')->count();

        $this->assertEquals($upload1->id, $upload2->id);
        $this->assertEquals($countUploadsAfterFirst, $countUploadsAfterSecond);
        $this->assertEquals($countEventsAfterFirst, $countEventsAfterSecond);
    }

    /**
     * Skenario 7 — Data historis yang sudah ada tetap utuh.
     */
    public function test_existing_historical_snapshot_is_preserved_intact(): void
    {
        $historicalDate = Carbon::parse('2024-09-02');
        $dateStr = $historicalDate->toDateString();

        $sched2024 = LessonSchedule::create([
            'classroom_id' => $this->classroom2024->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'day' => 'Senin',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
        ]);

        // Buat snapshot historis buatan
        DailyKbmRoster::create([
            'date' => $dateStr,
            'user_id' => $this->teacher->id,
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'user_type' => 'teacher',
            'is_expected_checkin' => true,
            'is_weekend' => false,
            'is_holiday' => false,
            'active_schedules_count' => 2,
        ]);

        DailyScheduleTarget::create([
            'date' => $dateStr,
            'lesson_schedule_id' => $sched2024->id,
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $this->classroom2024->id,
            'subject_id' => $this->subject->id,
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        DailyClassroomKbmSnapshot::create([
            'date' => $dateStr,
            'classroom_id' => $this->classroom2024->id,
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'is_kbm_active' => true,
            'total_students' => 30,
        ]);

        // Panggil getOrGenerateSnapshot tanpa forceSync saat sesi admin di 2026/2027
        session(['active_academic_year' => '2026/2027']);
        $service = app(DailyKbmSnapshotService::class);
        $data = $service->getOrGenerateSnapshot($historicalDate, false);

        $this->assertNotEmpty($data['rosters']);
        $this->assertEquals('2024/2025', $data['rosters']->first()->academic_year);

        $this->assertNotEmpty($data['schedules']);
        $this->assertEquals('2024/2025', $data['schedules']->first()->academic_year);

        $this->assertNotEmpty($data['classrooms']);
        $this->assertEquals('2024/2025', $data['classrooms']->first()->academic_year);
    }
}
