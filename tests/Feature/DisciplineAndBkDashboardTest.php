<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisciplineAndBkDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']
        );
    }

    private function getOrCreateRole(string $slug, string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name]);
        foreach ($permissions as $pSlug) {
            $perm = Permission::firstOrCreate(['slug' => $pSlug], ['name' => $pSlug, 'group' => 'Test']);
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        return $role;
    }

    private function createStudent(string $academicYear = '2026/2027', string $name = 'Siswa Test Dashboard'): Student
    {
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer']);
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X TKJ 1'],
            ['academic_year' => $academicYear, 'grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        return Student::create([
            'nisn' => (string) fake()->unique()->numerify('##########'),
            'name' => $name,
            'classroom_id' => $classroom->id,
            'academic_year' => $academicYear,
            'status' => 'aktif',
        ]);
    }

    /**
     * Test 1: Dashboard Kedisiplinan dapat diakses dengan metrik, grafik, tugas, jadwal, dan visitasi lengkap.
     */
    public function test_discipline_dashboard_accessible_with_all_components(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', [
            'access_piket',
            'manage_discipline',
            'manage_piket_cases',
            'view_piket_reports',
        ]);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();

        // 1. Buat Jurnal Piket & Kejadian
        $journal = PiketJournal::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'date' => now()->toDateString(),
            'status' => 'berlangsung',
            'coordinator_id' => $tatibUser->id,
        ]);

        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'academic_year' => '2026/2027',
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:00:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat dan membawa barang terlarang',
            'action_taken' => 'Pencatatan pelanggaran',
            'confiscated_items' => '1 unit Barang Sitaan',
            'follow_up_target' => 'rujuk_bk',
            'status' => 'tercatat',
            'verification_status' => 'menunggu_verifikasi',
            'recorded_by' => $tatibUser->id,
        ]);

        // 2. Buat Jadwal Piket
        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $todayDay = $dayNames[now()->dayOfWeekIso] ?? 'Senin';
        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'day' => $todayDay,
            'teacher_id' => $tatibUser->id,
        ]);

        // 3. Buat Data Visitasi
        StudentVisitation::create([
            'student_id' => $student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'tindak_lanjut_kasus',
            'source_type' => 'kasus_kedisiplinan',
            'source_id' => $incident->id,
            'reason' => 'Pemantauan rumah untuk kasus kedisiplinan berulang',
            'requested_by' => $tatibUser->id,
            'primary_officer_id' => $tatibUser->id,
            'planned_date' => now()->toDateString(),
            'status' => 'dijadwalkan',
        ]);

        // Akses Dashboard Kedisiplinan
        $response = $this->actingAs($tatibUser)->get(route('discipline.dashboard'));
        $response->assertOk();

        // Cek Komponen Header & Selector
        $response->assertSee('Dashboard Kedisiplinan');
        $response->assertSee('Tahun Ajaran:');
        $response->assertSee('2026/2027');

        // Cek 4 Metrik Utama
        $response->assertSee('Pelanggaran Hari Ini');
        $response->assertSee('Belum Ditangani');
        $response->assertSee('Tindak Lanjut');
        $response->assertSee('Pemantauan Aktif');

        // Cek Panel Grafik
        $response->assertSee('Tren Pelanggaran (7 Hari Terakhir)');
        $response->assertSee('Jenis Pelanggaran');

        // Cek Tugas dan Kasus yang Membutuhkan Tindakan
        $response->assertSee('Tugas dan Kasus yang Membutuhkan Tindakan');
        $response->assertSee('Terlambat dan membawa barang terlarang');
        $response->assertSee('1 unit Barang Sitaan');
        $response->assertSee('Menunggu Verifikasi');

        // Cek Jadwal Piket dan Laporan Kejadian
        $response->assertSee('Petugas Piket Hari Ini');
        $response->assertSee($tatibUser->name);
        $response->assertSee('Laporan Kejadian Piket Terkini');

        // Cek Visitasi dan Pemantauan
        $response->assertSee('Visitasi dan Pemantauan Siswa Terkini');
        $response->assertSee('Tindak Lanjut Kasus / Kejadian');
    }

    /**
     * Test 2: Pengguna tanpa hak kedisiplinan dilarang mengakses Dashboard Kedisiplinan.
     */
    public function test_discipline_dashboard_forbidden_for_unauthorized_user(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $user = User::factory()->create(['position' => 'guru', 'role_id' => $guruRole->id]);

        $response = $this->actingAs($user)->get(route('discipline.dashboard'));
        $response->assertForbidden();
    }

    /**
     * Test 3: Dashboard BK dapat diakses dengan metrik, agenda, rujukan, ringkasan bimbingan, visitasi, dan log aktivitas.
     */
    public function test_bk_dashboard_accessible_with_all_components(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['operate_bk']);
        $bkUser = User::factory()->create(['position' => 'guru']);
        $bkUser->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();

        // 1. Buat Layanan BK
        $service = BkService::create([
            'student_id' => $student->id,
            'academic_year' => '2026/2027',
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Konseling penyesuaian diri dan kedisiplinan belajar',
            'result' => 'Siswa berkomitmen memperbaiki kedisiplinan.',
            'follow_up_plan' => 'Konseling lanjutan 1 minggu kemudian',
            'follow_up_target_date' => now()->addDays(7)->toDateString(),
            'follow_up_status' => 'sedang_berjalan',
            'counselor_id' => $bkUser->id,
        ]);

        // 2. Buat Rujukan Piket
        $journal = PiketJournal::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'date' => now()->toDateString(),
            'status' => 'berlangsung',
            'coordinator_id' => $bkUser->id,
        ]);
        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'academic_year' => '2026/2027',
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'category' => 'akademik',
            'description' => 'Sering tertidur saat jam KBM pertama',
            'action_taken' => 'Dirujuk ke Guru BK',
            'follow_up_target' => 'rujuk_bk',
            'status' => 'proses',
            'recorded_by' => $bkUser->id,
        ]);
        BkReferral::create([
            'piket_incident_id' => $incident->id,
            'status' => 'menunggu',
            'notes' => 'Mohon pendampingan konseling motivasi belajar',
        ]);

        // 3. Buat Visitasi Home Visit BK
        StudentVisitation::create([
            'student_id' => $student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'kunjungan_rumah',
            'source_type' => 'layanan_bk',
            'source_id' => $service->id,
            'reason' => 'Home visit asesmen lingkungan keluarga',
            'requested_by' => $bkUser->id,
            'primary_officer_id' => $bkUser->id,
            'planned_date' => now()->toDateString(),
            'status' => 'diajukan',
        ]);

        // Akses Dashboard BK
        $response = $this->actingAs($bkUser)->get(route('bk.dashboard'));
        $response->assertOk();

        // Cek Komponen Header & Selector
        $response->assertSee('Dashboard BK');
        $response->assertSee('Tahun Ajaran:');
        $response->assertSee('2026/2027');

        // Cek 4 Kartu Metrik Utama BK
        $response->assertSee('Kasus Aktif');
        $response->assertSee('Menunggu Tindak Lanjut');
        $response->assertSee('Jadwal Hari Ini');
        $response->assertSee('Perlu Evaluasi');

        // Cek Agenda Layanan BK
        $response->assertSee('Agenda Layanan BK');
        $response->assertSee($student->name);
        $response->assertSee('Bimbingan Pribadi');

        // Cek Kasus dan Pembinaan yang Memerlukan Tindak Lanjut
        $response->assertSee('Kasus dan Pembinaan yang Memerlukan Tindak Lanjut');
        $response->assertSee('Sering tertidur saat jam KBM pertama');
        $response->assertSee('Buat Sesi BK');

        // Cek 2-Column Split: Ringkasan Pembinaan & Visitasi
        $response->assertSee('Ringkasan Pembinaan');
        $response->assertSee('Bimbingan Pribadi');
        $response->assertSee('Bimbingan Sosial');
        $response->assertSee('Bimbingan Belajar');
        $response->assertSee('Bimbingan Karier');
        $response->assertSee('Visitasi &amp; Pemantauan', false);

        // Cek Riwayat Aktivitas Sesuai Hak Akses
        $response->assertSee('Riwayat Aktivitas Sesuai Hak Akses');
        $response->assertSee('Konseling penyesuaian diri dan kedisiplinan belajar');
    }

    /**
     * Test 4: Pengguna tanpa hak BK dilarang mengakses Dashboard BK.
     */
    public function test_bk_dashboard_forbidden_for_unauthorized_user(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $user = User::factory()->create(['position' => 'guru', 'role_id' => $guruRole->id]);

        $response = $this->actingAs($user)->get(route('bk.dashboard'));
        $response->assertForbidden();
    }

    /**
     * Test 5: Sidebar memuat link Dashboard Kedisiplinan dan Dashboard BK.
     */
    public function test_sidebar_contains_dashboard_links(): void
    {
        // 1. Tim Kedisiplinan melihat Dashboard Kedisiplinan
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_discipline']);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $tatibResponse = $this->actingAs($tatibUser)->get(route('discipline.dashboard'));
        $tatibResponse->assertOk();
        $tatibResponse->assertSee(route('discipline.dashboard'));
        $tatibResponse->assertSee('Dashboard Kedisiplinan');

        // 2. Guru BK melihat Dashboard BK
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['operate_bk']);
        $bkUser = User::factory()->create(['position' => 'guru']);
        $bkUser->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);

        $bkResponse = $this->actingAs($bkUser)->get(route('bk.dashboard'));
        $bkResponse->assertOk();
        $bkResponse->assertSee(route('bk.dashboard'));
        $bkResponse->assertSee('Dashboard BK');
    }
}
