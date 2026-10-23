<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::where('slug', 'super_admin')->first();
        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test'],
            [
                'name' => 'Administrator Utama',
                'email' => 'admin_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
    }

    public function test_command_center_dashboard_renders_with_four_kpi_pillars(): void
    {
        $setting = SchoolSetting::firstOrCreate([], [
            'school_name' => 'SMK PGRI KRAS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $dept = Department::firstOrCreate(['code' => 'TKR'], ['name' => 'Teknik Kendaraan Ringan']);
        $grade = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Kelas XII']);

        $classroom = Classroom::firstOrCreate(
            ['name' => 'XII TKR 1', 'academic_year' => '2026/2027'],
            [
                'department_id' => $dept->id,
                'grade_level_id' => $grade->id,
                'is_active' => true,
            ]
        );

        $teacherRole = Role::where('slug', 'guru')->first();
        $teacher = User::firstOrCreate(
            ['username' => 'guru_kbm'],
            [
                'name' => 'Budi Santoso, S.Pd.',
                'email' => 'budi_guru@example.com',
                'password' => bcrypt('password'),
                'role_id' => $teacherRole->id,
                'is_active' => true,
            ]
        );

        $subject = Subject::firstOrCreate(['code' => 'MTK-XII'], ['name' => 'Matematika Terapan']);

        LessonSchedule::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'day' => match (now()->dayOfWeekIso) {
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', default => 'Minggu',
            },
            'start_period' => 1,
            'end_period' => 7,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        Student::create([
            'name' => 'AHMAD SISWA TEST',
            'nisn' => '9988776655',
            'classroom_id' => $classroom->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Pusat Komando Sekolah');
        $response->assertSee('Dashboard Supervisi Guru');
        $response->assertSee('Presensi GTK Hari Ini');
        $response->assertSee('Kondisi KBM Rombel');
        $response->assertSee('Peserta Didik Aktif');
        $response->assertSee('Sudah Disupervisi');
        $response->assertSee('XII TKR 1');
        $response->assertSee('Matematika Terapan');
        $response->assertSee('Budi Santoso, S.Pd.');
    }

    public function test_command_center_displays_pending_approvals_and_piket_officers(): void
    {
        $teacherRole = Role::where('slug', 'guru')->first();
        $teacher = User::firstOrCreate(
            ['username' => 'guru_piket_test'],
            [
                'name' => 'Siti Aminah, M.Pd.',
                'email' => 'siti_piket@example.com',
                'password' => bcrypt('password'),
                'role_id' => $teacherRole->id,
                'is_active' => true,
            ]
        );

        $todayDay = match (now()->dayOfWeekIso) {
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', default => 'Minggu',
        };

        PiketSchedule::create([
            'teacher_id' => $teacher->id,
            'day' => $todayDay,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        GtkPermit::create([
            'user_id' => $teacher->id,
            'permission_type' => 'izin',
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration_days' => 1,
            'reason' => 'Urusan Dinas Luar Kota',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Memerlukan Tindakan / Persetujuan Segera');
        $response->assertSee('Tinjau Izin GTK');
        $response->assertSee('Petugas Piket Hari Ini');
        $response->assertSee('Siti Aminah, M.Pd.');
    }
}
