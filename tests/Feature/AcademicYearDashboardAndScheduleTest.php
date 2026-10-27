<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\LessonSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Supervision;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearDashboardAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $operationalYear;

    protected AcademicYear $archiveYear;

    protected User $superAdmin;

    protected User $wakaKurikulum;

    protected User $teacher;

    protected User $supervisor;

    protected Classroom $operationalClassroom;

    protected Classroom $archiveClassroom;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->operationalYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'semester' => 'Ganjil',
                'is_active' => true,
                'start_date' => '2026-07-15',
                'end_date' => '2027-06-30',
            ]
        );

        $this->archiveYear = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            [
                'semester' => 'Genap',
                'is_active' => false,
                'start_date' => '2024-07-15',
                'end_date' => '2025-06-30',
            ]
        );

        $setting = SchoolSetting::first() ?? new SchoolSetting;
        $setting->academic_year = '2026/2027';
        $setting->semester = 'Ganjil';
        $setting->save();

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $wakaRole = Role::where('slug', 'waka_kurikulum')->first();
        $guruRole = Role::where('slug', 'guru')->first();
        $supervisorRole = Role::where('slug', 'supervisor')->first();

        $this->superAdmin = User::factory()->create([
            'role_id' => $superAdminRole?->id,
            'is_active' => true,
        ]);

        $this->wakaKurikulum = User::factory()->create([
            'role_id' => $wakaRole?->id,
            'is_active' => true,
        ]);

        $this->teacher = User::factory()->create([
            'name' => 'Guru Pengajar',
            'role_id' => $guruRole?->id,
            'is_active' => true,
        ]);

        $this->supervisor = User::factory()->create([
            'name' => 'Supervisor Penilai',
            'role_id' => $supervisorRole?->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $this->operationalClassroom = Classroom::create([
            'name' => 'XII TKJ Operasional',
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->operationalYear->id,
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        $this->archiveClassroom = Classroom::create([
            'name' => 'XII TKJ Arsip Lawas',
            'academic_year' => '2024/2025',
            'academic_year_id' => $this->archiveYear->id,
            'grade_level_id' => $grade->id,
            'department_id' => $dept->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'is_active' => false,
        ]);

        $this->subject = Subject::firstOrCreate(
            ['code' => 'KOD-TKJ'],
            ['name' => 'Jaringan Komputer', 'is_active' => true]
        );
    }

    /**
     * 1. Sesi tahun arsip di browser tidak mengubah statistik operasional dashboard (Command Center).
     */
    public function test_archive_session_does_not_alter_dashboard_operational_statistics(): void
    {
        // KBM Jadwal hari ini untuk tahun operasional 2026/2027
        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };

        LessonSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $this->operationalClassroom->id,
            'subject_id' => $this->subject->id,
            'day' => $todayDay,
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        // KBM Jadwal untuk tahun arsip 2024/2025
        LessonSchedule::create([
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $this->archiveClassroom->id,
            'subject_id' => $this->subject->id,
            'day' => $todayDay,
            'start_period' => 3,
            'end_period' => 4,
            'start_time' => '08:30:00',
            'end_time' => '10:00:00',
        ]);

        // Akses dashboard waka kurikulum dengan session arsip aktif
        $response = $this->actingAs($this->wakaKurikulum)
            ->withSession([
                'active_academic_year' => '2024/2025',
                'active_semester' => 'Genap',
            ])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Statistik rombel aktif harus tetap menghitung rombel tahun operasional
        $response->assertViewHas('totalClassroomsCount', function ($count) {
            // Hanya rombel 2026/2027 (operationalClassroom) yang dihitung, bukan archiveClassroom
            return $count >= 1;
        });

        $activeClassrooms = $response->viewData('activeClassrooms');
        $this->assertTrue($activeClassrooms->contains('id', $this->operationalClassroom->id));
        $this->assertFalse($activeClassrooms->contains('id', $this->archiveClassroom->id));

        // Jadwal KBM hari ini harus tetap milik 2026/2027
        $todaySchedules = $response->viewData('todayAllSchedules');
        $this->assertTrue($todaySchedules->every(fn ($s) => $s->academic_year === '2026/2027'));
    }

    /**
     * 2. Pembuatan jadwal supervisi baru di create & store menggunakan tahun operasional secara default.
     */
    public function test_new_supervision_schedule_creation_defaults_to_operational_year_despite_archive_session(): void
    {
        // 1. GET form create dengan session arsip aktif
        $createResponse = $this->actingAs($this->wakaKurikulum)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->get(route('schedules.create'));

        $createResponse->assertStatus(200);
        $createResponse->assertViewHas('academicYear', '2026/2027');

        // 2. POST store tanpa menyertakan academic_year (default)
        $storeResponse = $this->actingAs($this->wakaKurikulum)
            ->withSession(['active_academic_year' => '2024/2025'])
            ->post(route('schedules.store'), [
                'teacher_id' => $this->teacher->id,
                'supervisor_id' => $this->supervisor->id,
                'schedule_date' => now()->addDays(2)->toDateString(),
                'schedule_time' => '08:00 - 09:30 WIB',
                'subject' => 'Jaringan Komputer',
                'class_name' => 'XII TKJ Operasional',
                'expertise_program' => 'Teknik Komputer & Jaringan',
            ]);

        $storeResponse->assertRedirect();

        $schedule = Supervision::latest('id')->first();
        $this->assertNotNull($schedule);
        $this->assertEquals('2026/2027', $schedule->academic_year);
    }

    /**
     * 3. Parameter tahun target yang eksplisit divalidasi dan ditolak jika format atau datanya tidak valid.
     */
    public function test_explicit_target_year_is_validated_and_rejects_invalid_input(): void
    {
        // Mencoba input target tahun ngawur / manipulatif
        $response = $this->actingAs($this->wakaKurikulum)->post(route('schedules.store'), [
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'schedule_date' => now()->addDays(2)->toDateString(),
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jaringan Komputer',
            'class_name' => 'XII TKJ Operasional',
            'academic_year' => 'TAHUN_PALSU_9999',
        ]);

        $response->assertSessionHasErrors('academic_year');
    }

    /**
     * 4. Request manipulasi academic_year dan semester pada update tidak mengubah data tersimpan.
     */
    public function test_updating_schedule_ignores_manipulation_of_academic_year_and_semester(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2024/2025',
            'semester' => 'Genap',
            'schedule_date' => '2025-02-10',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jaringan Komputer Lama',
            'class_name' => 'XII TKJ Arsip Lawas',
            'status' => 'terjadwal',
        ]);

        // Kirim manipulasi academic_year dan semester di body PUT
        $updateResponse = $this->actingAs($this->wakaKurikulum)
            ->withSession(['active_academic_year' => '2026/2027'])
            ->put(route('schedules.update', $schedule), [
                'teacher_id' => $this->teacher->id,
                'supervisor_id' => $this->supervisor->id,
                'schedule_date' => '2025-02-12',
                'schedule_time' => '09:00 - 10:30 WIB',
                'subject' => 'Jaringan Komputer Update',
                'class_name' => 'XII TKJ Arsip Lawas',
                'academic_year' => '2026/2027', // Dicoba dimanipulasi
                'semester' => 'Ganjil',          // Dicoba dimanipulasi
                'status' => 'terjadwal',
            ]);

        $updateResponse->assertRedirect();
        $schedule->refresh();

        // Tetap mempertahankan tahun asal 2024/2025 dan semester Genap
        $this->assertEquals('2024/2025', $schedule->academic_year);
        $this->assertEquals('Genap', $schedule->semester);
    }

    /**
     * 5. Pengeditan field jadwal lain tetap berhasil.
     */
    public function test_updating_schedule_successfully_updates_allowed_fields(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-10',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Mata Pelajaran Awal',
            'class_name' => 'XII TKJ 1',
            'expertise_program' => 'TKJ',
            'schedule_notes' => 'Catatan awal',
            'status' => 'terjadwal',
        ]);

        $response = $this->actingAs($this->wakaKurikulum)->put(route('schedules.update', $schedule), [
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'schedule_date' => '2026-09-12',
            'schedule_time' => '10:00 - 11:30 WIB',
            'subject' => 'Mata Pelajaran Diperbarui',
            'class_name' => 'XII TKJ 2',
            'expertise_program' => 'Teknik Komputer & Jaringan',
            'schedule_notes' => 'Catatan diperbarui secara sah',
            'status' => 'praobservasi',
        ]);

        $response->assertRedirect(route('schedules.index', [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]));
        $response->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals('2026-09-12', $schedule->schedule_date->toDateString());
        $this->assertEquals('10:00 - 11:30 WIB', $schedule->schedule_time);
        $this->assertEquals('Mata Pelajaran Diperbarui', $schedule->subject);
        $this->assertEquals('XII TKJ 2', $schedule->class_name);
        $this->assertEquals('Catatan diperbarui secara sah', $schedule->schedule_notes);
        $this->assertEquals('praobservasi', $schedule->status);
        $this->assertEquals('2026/2027', $schedule->academic_year);
        $this->assertEquals('Ganjil', $schedule->semester);
    }

    /**
     * 6. Jadwal berstatus terjadwal dapat dihapus jika diizinkan aturan bisnis.
     */
    public function test_schedule_in_terjadwal_status_can_be_deleted_by_authorized_user(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-15',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jadwal Hapus Uji',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'terjadwal',
        ]);

        $response = $this->actingAs($this->wakaKurikulum)->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect(route('schedules.index', [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]));
        $response->assertSessionHas('success', 'Jadwal supervisi berhasil dihapus.');
        $this->assertDatabaseMissing('supervisions', ['id' => $schedule->id]);
    }

    /**
     * 7. Jadwal berstatus selesai atau disetujui tidak dapat dihapus.
     */
    public function test_schedule_with_status_selesai_or_disetujui_cannot_be_deleted(): void
    {
        $scheduleDisetujui = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-15',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jadwal Disetujui',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'disetujui',
        ]);

        $res1 = $this->actingAs($this->wakaKurikulum)->delete(route('schedules.destroy', $scheduleDisetujui));
        $res1->assertRedirect();
        $res1->assertSessionHas('error');
        $this->assertDatabaseHas('supervisions', ['id' => $scheduleDisetujui->id]);

        $scheduleSelesai = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-16',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jadwal Selesai',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'selesai',
        ]);

        $res2 = $this->actingAs($this->wakaKurikulum)->delete(route('schedules.destroy', $scheduleSelesai));
        $res2->assertRedirect();
        $res2->assertSessionHas('error');
        $this->assertDatabaseHas('supervisions', ['id' => $scheduleSelesai->id]);
    }

    /**
     * 8. Jadwal yang sudah memiliki penilaian tidak dapat dihapus.
     */
    public function test_schedule_with_existing_scores_cannot_be_deleted(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-15',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jadwal Berpenilaian',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'terjadwal', // Status masih terjadwal tetapi sudah ada entri skor
        ]);

        $indicator = SupervisionIndicator::first();
        SupervisionScore::create([
            'supervision_id' => $schedule->id,
            'indicator_id' => $indicator->id,
            'score' => 4,
        ]);

        $response = $this->actingAs($this->wakaKurikulum)->delete(route('schedules.destroy', $schedule));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('supervisions', ['id' => $schedule->id]);
    }

    /**
     * 9. Jadwal yang sudah disahkan tidak dapat dihapus.
     */
    public function test_schedule_approved_by_principal_cannot_be_deleted(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-15',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jadwal Disahkan',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'terjadwal',
            'approved_by_principal' => true,
            'principal_approved_at' => now(),
        ]);

        $response = $this->actingAs($this->wakaKurikulum)->delete(route('schedules.destroy', $schedule));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('supervisions', ['id' => $schedule->id]);
    }

    /**
     * 10. Pengguna tanpa wewenang (guru biasa) dilarang membuat, mengubah, atau menghapus jadwal supervisi (HTTP 403).
     */
    public function test_unauthorized_users_cannot_manage_supervision_schedules(): void
    {
        $schedule = Supervision::create([
            'teacher_id' => $this->teacher->id,
            'supervisor_id' => $this->supervisor->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-09-10',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Jaringan Komputer',
            'class_name' => 'XII TKJ Operasional',
            'status' => 'terjadwal',
        ]);

        // Guru biasa mencoba akses create
        $this->actingAs($this->teacher)
            ->get(route('schedules.create'))
            ->assertStatus(403);

        // Guru biasa mencoba store
        $this->actingAs($this->teacher)
            ->post(route('schedules.store'), [
                'teacher_id' => $this->teacher->id,
                'supervisor_id' => $this->supervisor->id,
                'schedule_date' => '2026-09-15',
                'schedule_time' => '08:00 - 09:30 WIB',
            ])
            ->assertStatus(403);

        // Guru biasa mencoba update
        $this->actingAs($this->teacher)
            ->put(route('schedules.update', $schedule), [
                'teacher_id' => $this->teacher->id,
                'supervisor_id' => $this->supervisor->id,
                'schedule_date' => '2026-09-15',
                'schedule_time' => '08:00 - 09:30 WIB',
                'subject' => 'Hacking Jadwal',
                'class_name' => 'XII TKJ Operasional',
                'status' => 'terjadwal',
            ])
            ->assertStatus(403);

        // Guru biasa mencoba delete
        $this->actingAs($this->teacher)
            ->delete(route('schedules.destroy', $schedule))
            ->assertStatus(403);
    }
}
