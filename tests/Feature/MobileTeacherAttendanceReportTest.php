<?php

namespace Tests\Feature;

use App\Http\Controllers\Attendance\AttendanceController;
use App\Models\AcademicYear;
use App\Models\DailyKbmRoster;
use App\Models\Role;
use App\Models\User;
use App\Services\DailyKbmSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTeacherAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $principal;

    protected User $regularTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        AcademicYear::where('id', '!=', $ay->id)->update(['is_active' => false]);

        $ksRole = Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->principal = User::create([
            'name' => 'Kepala Sekolah Test',
            'username' => 'kepalasekolahtest',
            'email' => 'ks@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $ksRole->id,
            'is_active' => true,
        ]);

        $this->regularTeacher = User::create([
            'name' => 'Guru Biasa Test',
            'username' => 'gurubiasatest',
            'email' => 'gurubiasa@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);
    }

    public function test_principal_can_fetch_teacher_attendance_report(): void
    {
        $todayStr = Carbon::today()->toDateString();

        $response = $this->actingAs($this->principal)
            ->getJson("/api/v1/mobile/attendance/report-teachers?period_type=custom&start_date={$todayStr}&end_date={$todayStr}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'start_date' => $todayStr,
                    'end_date' => $todayStr,
                    'period_type' => 'custom',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'start_date',
                    'end_date',
                    'period_label',
                    'period_type',
                    'summary',
                    'people' => [
                        '*' => [
                            'id',
                            'name',
                            'nip',
                            'hadir',
                            'terlambat',
                            'persentase_kehadiran',
                        ],
                    ],
                    'export_urls' => [
                        'print',
                        'pdf',
                        'excel',
                    ],
                ],
            ]);
    }

    public function test_principal_can_download_teacher_attendance_pdf_and_excel(): void
    {
        $todayStr = Carbon::today()->toDateString();

        // Test PDF download
        $pdfRes = $this->actingAs($this->principal)
            ->get("/presensi/laporan/guru-staf/unduh-pdf?period_type=custom&start_date={$todayStr}&end_date={$todayStr}");
        $pdfRes->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // Test Excel (.xlsx) download
        $xlsxRes = $this->actingAs($this->principal)
            ->get("/presensi/laporan/guru-staf/ekspor-excel?period_type=custom&start_date={$todayStr}&end_date={$todayStr}");
        $xlsxRes->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_regular_teacher_cannot_access_teacher_attendance_report(): void
    {
        $response = $this->actingAs($this->regularTeacher)
            ->getJson('/api/v1/mobile/attendance/report-teachers');

        $response->assertStatus(403);
    }

    public function test_guru_bk_attendance_is_full_time_like_staff(): void
    {
        $bkRole = Role::firstOrCreate(['slug' => 'guru_bk'], ['name' => 'Guru BK']);
        $guruBk = User::create([
            'name' => 'Guru BK Hebat',
            'username' => 'gurubkhebat',
            'email' => 'gurubk@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $bkRole->id,
            'position' => 'Guru BK',
            'is_active' => true,
        ]);

        $this->assertTrue($guruBk->isCounselingBK());

        // 1. In AttendanceController index (live list), Guru BK follows full-time school operational hours
        $res = $this->actingAs($this->principal)->get('/presensi');
        $res->assertStatus(200)
            ->assertSee('Guru BK Hebat')
            ->assertSee('Layanan BK &amp; Bimbingan Konseling Siswa (Full Time)', false);

        // 2. In buildTeacherStaffIndividualRecap, Guru BK is obligated every school day like staff even without lesson schedules
        $attController = app(AttendanceController::class);
        $recap = $attController->buildTeacherStaffIndividualRecap(
            Carbon::parse('next monday')->toDateString(),
            Carbon::parse('next monday')->toDateString(),
            'guru',
            $guruBk->id
        );

        $person = $recap['people']->first();
        $this->assertNotNull($person);
        $this->assertTrue($person->is_bk);
        $this->assertEquals('Guru BK', $person->position);

        // 3. In DailyKbmSnapshotService, Guru BK is expected on regular workdays
        $snapshotService = app(DailyKbmSnapshotService::class);
        $snapshotService->generateSnapshotForDate(Carbon::parse('next monday'));

        $roster = DailyKbmRoster::where('user_id', $guruBk->id)
            ->where('date', Carbon::parse('next monday')->toDateString())
            ->first();

        $this->assertNotNull($roster);
        $this->assertTrue($roster->is_expected_checkin);
    }
}
