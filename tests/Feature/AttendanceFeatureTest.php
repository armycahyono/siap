<?php

namespace Tests\Feature;

use App\Models\AttendanceBiometric;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\GtkPermit;
use App\Models\LessonSchedule;
use App\Models\PiketInvalClass;
use App\Models\PiketJournal;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_adms_handshake_returns_expected_options(): void
    {
        $response = $this->get('/iclock/cdata?SN=REVO-WDV-001&options=all');

        $response->assertStatus(200);
        $response->assertSee('GET OPTION FROM: REVO-WDV-001');
        $response->assertSee('Realtime=1');

        $this->assertDatabaseHas('attendance_devices', [
            'device_sn' => 'REVO-WDV-001',
            'status' => 'online',
        ]);
    }

    public function test_adms_push_matches_teacher_by_nigs_and_student_by_nisn(): void
    {
        $role = Role::where('slug', 'guru')->first();

        // Create teacher
        $teacher = User::create([
            'name' => 'Ahmad Fauzi, S.Pd.',
            'username' => 'ahmad.fauzi',
            'email' => 'ahmad@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'nigs' => 'GUR-001',
            'is_active' => true,
        ]);

        // Create student
        $classroom = Classroom::first();

        $student = Student::create([
            'name' => 'Budi Pratama',
            'nisn' => '0081234567',
            'nis' => '10201',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        // Verified attendance device
        AttendanceDevice::create([
            'name' => 'Fingerspot Revo Test',
            'device_sn' => 'REVO-WDV-001',
            'ip_address' => '127.0.0.1',
            'port' => 5005,
            'server_port' => 8014,
            'status' => 'online',
            'is_active' => true,
        ]);

        // Simulated push from Revo WDV-204BNC:
        // Line 1: Teacher scan (GUR-001)
        // Line 2: Student scan (0081234567)
        // Line 3: Unknown scan (99999)
        $payload = "GUR-001\t2026-10-03 07:15:22\t1\t0\t0\n"
                 ."0081234567\t2026-10-03 07:20:10\t15\t0\t0\n"
                 ."99999\t2026-10-03 07:25:00\t1\t0\t0";

        $response = $this->call('POST', '/iclock/cdata?SN=REVO-WDV-001&table=ATTLOG', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK: 3');

        // Verify teacher attendance recorded
        $this->assertDatabaseHas('attendance_logs', [
            'pin' => 'GUR-001',
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => 'Ahmad Fauzi, S.Pd.',
            'status' => 'check_in',
            'verify_type' => 'fingerprint',
        ]);

        // Verify student attendance recorded
        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '0081234567',
            'user_type' => 'student',
            'student_id' => $student->id,
            'name' => 'BUDI PRATAMA',
            'status' => 'check_in',
            'verify_type' => 'face',
        ]);

        // Verify unknown attendance recorded
        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '99999',
            'user_type' => 'unknown',
            'status' => 'check_in',
        ]);
    }

    public function test_attendance_dashboard_view_and_sync_command(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $device = AttendanceDevice::create([
            'name' => 'Revo WDV-204BNC',
            'device_sn' => 'SN-TEST-99',
            'ip_address' => '192.168.1.25',
            'port' => 5005,
            'server_port' => 8014,
            'status' => 'online',
        ]);

        $response = $this->actingAs($admin)->get(route('attendance.devices.index'));
        $response->assertStatus(200);
        $response->assertSee('Manajemen Mesin Absensi');
        $response->assertSee('192.168.1.25');

        // Test sending sync command
        $syncResponse = $this->actingAs($admin)->post(route('attendance.devices.sync', $device->id));
        $syncResponse->assertRedirect();

        $this->assertDatabaseHas('attendance_device_commands', [
            'device_sn' => 'SN-TEST-99',
            'command' => 'DATA QUERY ATTLOG',
            'status' => 'pending',
        ]);
    }

    public function test_realtime_api_returns_json_and_new_logs(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $response = $this->actingAs($admin)->get(route('attendance.api.latest', ['after_id' => 0]));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'new_logs',
            'latest_id',
            'stats' => ['total_scans', 'total_teachers', 'total_students', 'total_unknown'],
            'device_status',
            'device_last_activity',
        ]);
    }

    public function test_attendance_report_and_print_view(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // Ensure foundation name exists in school settings
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'SMK PGRI 1 KEDIRI',
                'foundation_name' => "Perwakilan yayasan pembina lembaga pendidikan\npersatuan guru republik Indonesia (yplp-pgri)\nkabupaten kediri - jawa timur",
            ]
        );

        // Test backward-compatible redirect from /presensi/laporan
        $legacyRedirect = $this->actingAs($admin)->get(route('attendance.report'));
        $legacyRedirect->assertRedirect(route('attendance.report.teachers'));

        $teacher = User::operationalPersonnel()->first();

        // Test teacher/staff report index with date range (default view shows rekap table and guide placeholder)
        $reportResponse = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-04',
        ]));
        $reportResponse->assertStatus(200);
        $reportResponse->assertSee('Presensi Guru');
        $reportResponse->assertSee('28 September 2026 s/d 04 Oktober 2026');
        $reportResponse->assertSee('Rekap Status Kehadiran');
        $reportResponse->assertSee('Rincian Presensi Individu');

        // Test with selected individual user to view detailed daily rows
        if ($teacher) {
            $detailResponse = $this->actingAs($admin)->get(route('attendance.report.teachers', [
                'start_date' => '2026-09-28',
                'end_date' => '2026-10-04',
                'user_id' => $teacher->id,
            ]));
            $detailResponse->assertStatus(200);
            $detailResponse->assertSee('Jam Masuk');
            $detailResponse->assertSee('Jam Pulang');
            $detailResponse->assertSee('Keterangan');
        }

        // Test monthly preset and individual user filter
        $monthlyReport = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'period_type' => 'monthly',
            'month' => 1,
            'year' => 2026,
            'sub_type' => 'teacher',
        ]));
        $monthlyReport->assertStatus(200);
        $monthlyReport->assertSee('Bulan Januari 2026');

        // Test print view for teachers & staff
        $printResponse = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'period_type' => 'monthly',
            'month' => 1,
            'year' => 2026,
            'sub_type' => 'teacher',
        ]));
        $printResponse->assertStatus(200);
        $printResponse->assertSee('LAPORAN REKAPITULASI PRESENSI');
        $printResponse->assertSee('GURU DAN TENAGA KEPENDIDIKAN');
        $printResponse->assertSee('PERWAKILAN YAYASAN PEMBINA LEMBAGA PENDIDIKAN');
        $printResponse->assertSee('PERSATUAN GURU REPUBLIK INDONESIA (YPLP-PGRI)');
        $printResponse->assertSee('KABUPATEN KEDIRI - JAWA TIMUR');
        $printResponse->assertSee('Bulan Januari 2026');
    }

    public function test_teacher_and_student_schedule_arrival_and_departure_calculation(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // Target test date: a Monday
        $targetDate = '2026-10-05'; // Monday

        // Access dashboard for that date
        $response = $this->actingAs($admin)->get(route('attendance.index', ['date' => $targetDate]));
        $response->assertStatus(200);
        $response->assertViewHas('teacherAttendanceList');
        $response->assertViewHas('lateStudents');
        $response->assertViewHas('earlyStudents');
        $response->assertViewHas('absentStudents');
        $response->assertViewHas('schoolStartTimeStr');
        $response->assertViewHas('schoolEndTimeStr');
    }

    public function test_weekend_has_no_attendance_obligation_for_teachers_and_students(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // 2026-10-03 is a Saturday
        $saturday = '2026-10-03';
        $response = $this->actingAs($admin)->get(route('attendance.index', ['date' => $saturday]));
        $response->assertStatus(200);
        $response->assertSee('Hari Libur (Sabtu)');

        // On weekend, absentStudents should be empty because there is no duty obligation
        $absentStudents = $response->viewData('absentStudents');
        $this->assertCount(0, $absentStudents);
    }

    public function test_individual_user_recap_and_consolidated_check_in_out(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $teacher = User::where('is_active', true)->where('id', '!=', $admin->id)->first() ?? $admin;

        // Create 3 scans on the same date for this teacher (morning, mid-day, and afternoon)
        $testDate = '2026-10-06';
        AttendanceLog::create([
            'pin' => (string) $teacher->id,
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => $teacher->name,
            'scanned_at' => Carbon::parse("{$testDate} 06:45:10"),
            'status' => 'check_in',
        ]);
        AttendanceLog::create([
            'pin' => (string) $teacher->id,
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => $teacher->name,
            'scanned_at' => Carbon::parse("{$testDate} 10:15:00"),
            'status' => 'check_in',
        ]);
        AttendanceLog::create([
            'pin' => (string) $teacher->id,
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => $teacher->name,
            'scanned_at' => Carbon::parse("{$testDate} 15:30:25"),
            'status' => 'check_out',
        ]);

        // Request report filtered by this specific teacher
        $response = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'start_date' => $testDate,
            'end_date' => $testDate,
            'user_id' => $teacher->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee($teacher->name);
        $response->assertSee('06:45:10'); // Jam Masuk
        $response->assertSee('15:30:25'); // Jam Pulang
        $response->assertSee('Guru'); // Kategori

        // Request print view for this specific teacher
        $printResponse = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'start_date' => $testDate,
            'end_date' => $testDate,
            'user_id' => $teacher->id,
        ]));
        $printResponse->assertStatus(200);
        $printResponse->assertSee($teacher->name);
        $printResponse->assertSee('06:45:10');
        $printResponse->assertSee('15:30:25');
    }

    public function test_attendance_during_school_calendar_holiday(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // Create a holiday event on a Wednesday
        $holidayDate = '2026-10-07';
        CurriculumEvent::create([
            'title' => 'Libur Nasional Hari Besar',
            'category' => 'lhb',
            'academic_year' => '2026/2027',
            'start_date' => $holidayDate,
            'end_date' => $holidayDate,
            'is_holiday' => true,
            'created_by' => $admin->id,
        ]);

        $teacher = User::where('is_active', true)->where('id', '!=', $admin->id)->first();

        // Scan on holiday
        AttendanceLog::create([
            'pin' => (string) $teacher->id,
            'user_type' => 'teacher',
            'user_id' => $teacher->id,
            'name' => $teacher->name,
            'scanned_at' => Carbon::parse("{$holidayDate} 08:30:00"),
            'status' => 'check_in',
        ]);

        // Main index on holiday shows holiday reason and does not mark anyone absent
        $indexResponse = $this->actingAs($admin)->get(route('attendance.index', ['date' => $holidayDate]));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Libur Nasional Hari Besar');
        $indexResponse->assertSee('Guru dan Tenaga Kependidikan tidak ada kewajiban masuk');

        // Report rekap on holiday date shows Hadir (Hari Libur)
        $reportResponse = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'start_date' => $holidayDate,
            'end_date' => $holidayDate,
            'user_id' => $teacher->id,
        ]));
        $reportResponse->assertStatus(200);
        $reportResponse->assertSee('Hadir (Hari Libur)');
    }

    public function test_student_attendance_report_and_access_control(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $classroom = Classroom::first();

        $student = Student::create([
            'name' => 'Dimas Anggara',
            'nisn' => '0099887766',
            'nis' => '10301',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $testDate = '2026-10-08';
        AttendanceLog::create([
            'pin' => '0099887766',
            'user_type' => 'student',
            'student_id' => $student->id,
            'name' => $student->name,
            'scanned_at' => Carbon::parse("{$testDate} 06:40:00"),
            'status' => 'check_in',
        ]);
        AttendanceLog::create([
            'pin' => '0099887766',
            'user_type' => 'student',
            'student_id' => $student->id,
            'name' => $student->name,
            'scanned_at' => Carbon::parse("{$testDate} 14:00:00"),
            'status' => 'check_out',
        ]);

        // 1. Admin accesses student report (default filter shows rekap table and guide placeholder)
        $studentReportResponse = $this->actingAs($admin)->get(route('attendance.report.students', [
            'classroom_id' => $classroom->id,
            'start_date' => $testDate,
            'end_date' => $testDate,
        ]));
        $studentReportResponse->assertStatus(200);
        $studentReportResponse->assertSee('Presensi Siswa');
        $studentReportResponse->assertSee('DIMAS ANGGARA');
        $studentReportResponse->assertSee('Rincian Presensi Individu');

        // Admin accesses student report with selected student_id to view individual breakdown
        $studentDetailResponse = $this->actingAs($admin)->get(route('attendance.report.students', [
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'start_date' => $testDate,
            'end_date' => $testDate,
        ]));
        $studentDetailResponse->assertStatus(200);
        $studentDetailResponse->assertSee('06:40:00');
        $studentDetailResponse->assertSee('14:00:00');

        $studentPrintResponse = $this->actingAs($admin)->get(route('attendance.report.students.print', [
            'classroom_id' => $classroom->id,
            'start_date' => $testDate,
            'end_date' => $testDate,
        ]));
        $studentPrintResponse->assertStatus(200);
        $studentPrintResponse->assertSee('LAPORAN REKAPITULASI PRESENSI SISWA');
        $studentPrintResponse->assertSee('DIMAS ANGGARA');

        // 2. Regular teacher (no homeroom, no TU, no admin) cannot access teacher or student report
        $guruRole = Role::where('slug', 'guru')->first();
        $regularTeacher = User::create([
            'name' => 'Guru Biasa Non Wali',
            'username' => 'guru.biasa',
            'email' => 'guru.biasa@example.com',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'position' => 'Guru Mapel',
            'is_active' => true,
        ]);

        $this->actingAs($regularTeacher)
            ->get(route('attendance.report.teachers'))
            ->assertStatus(403);

        $this->actingAs($regularTeacher)
            ->get(route('attendance.report.students'))
            ->assertStatus(403);

        // 3. Homeroom teacher (wali kelas) CAN access student report
        $classroom->update(['homeroom_teacher_id' => $regularTeacher->id]);
        $regularTeacher->refresh();

        $homeroomReportResponse = $this->actingAs($regularTeacher)->get(route('attendance.report.students', [
            'start_date' => $testDate,
            'end_date' => $testDate,
        ]));
        $homeroomReportResponse->assertStatus(200);
        $homeroomReportResponse->assertSee('DIMAS ANGGARA');

        // Wali kelas still cannot access teacher/staff report
        $this->actingAs($regularTeacher)
            ->get(route('attendance.report.teachers'))
            ->assertStatus(403);
    }

    public function test_system_administrator_is_excluded_from_business_flows(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isSystemAdmin());

        // 1. OperationalPersonnel scope strictly excludes admin
        $operationalUsers = User::operationalPersonnel()->get();
        $this->assertFalse($operationalUsers->contains('id', $admin->id));
        $this->assertFalse($operationalUsers->contains('username', 'superadmin'));

        // 2. Attendance report GTK (Web view & Print view) does not show Administrator Sistem
        $reportResponse = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
        ]));
        $reportResponse->assertStatus(200);
        $people = $reportResponse->viewData('people');
        $this->assertFalse(collect($people)->contains('id', $admin->id));
        $teachersList = $reportResponse->viewData('teachersList');
        $this->assertFalse($teachersList->contains('id', $admin->id));

        $printResponse = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
        ]));
        $printResponse->assertStatus(200);
        $printResponse->assertDontSee('Administrator Sistem');

        // 3. Attendance dashboard index view does not include admin in master teacher list or daily duty
        $indexResponse = $this->actingAs($admin)->get(route('attendance.index'));
        $indexResponse->assertStatus(200);
        $teacherList = $indexResponse->viewData('teacherAttendanceList');
        $this->assertFalse($teacherList->contains('id', $admin->id));

        // 4. Biometric scan with admin's ID does not classify as teacher
        $payload = "{$admin->id}\t2026-10-09 07:05:00\t1\t0\t0";
        $admsResponse = $this->call('POST', '/iclock/cdata?SN=REVO-WDV-001&table=ATTLOG', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);
        $admsResponse->assertStatus(200);

        $this->assertDatabaseMissing('attendance_logs', [
            'pin' => (string) $admin->id,
            'user_type' => 'teacher',
            'user_id' => $admin->id,
        ]);
    }

    public function test_rekap_status_kehadiran_tables_for_gtk_and_students(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // 1. GTK Report & Print
        $gtkReport = $this->actingAs($admin)->get(route('attendance.report.teachers', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'sub_type' => 'all',
        ]));
        $gtkReport->assertStatus(200);
        $gtkReport->assertSee('Rekap Status Kehadiran');
        $gtkReport->assertSee('HADIR');
        $gtkReport->assertSee('SAKIT');
        $gtkReport->assertSee('IZIN');
        $gtkReport->assertSee('CUTI');
        $gtkReport->assertSee('DINAS LUAR');
        $gtkReport->assertSee('ALFA');
        $gtkReport->assertSee('TERLAMBAT');

        $gtkPrint = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'sub_type' => 'all',
        ]));
        $gtkPrint->assertStatus(200);
        $gtkPrint->assertSee('REKAPITULASI STATUS KEHADIRAN');
        $gtkPrint->assertSee('DL = Dinas Luar');
        $gtkPrint->assertSee('C = Cuti');

        // 2. Student Report & Print
        $studentReport = $this->actingAs($admin)->get(route('attendance.report.students', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
        ]));
        $studentReport->assertStatus(200);
        $studentReport->assertSee('Rekap Status Kehadiran Siswa');
        $studentReport->assertSee('HADIR');
        $studentReport->assertSee('SAKIT');
        $studentReport->assertSee('IZIN');
        $studentReport->assertSee('ALFA');
        $studentReport->assertSee('PULANG');
        $studentReport->assertSee('TERLAMBAT');

        $studentPrint = $this->actingAs($admin)->get(route('attendance.report.students.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
        ]));
        $studentPrint->assertStatus(200);
        $studentPrint->assertSee('REKAPITULASI STATUS KEHADIRAN SISWA');
        $studentPrint->assertSee('P = Pulang Cepat');
    }

    public function test_print_separate_status_and_detail_for_gtk_and_students(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        // 1. Verify top button "Cetak Lembar Resmi" is removed from web views
        $gtkWeb = $this->actingAs($admin)->get(route('attendance.report.teachers'));
        $gtkWeb->assertStatus(200);
        $gtkWeb->assertDontSee('Cetak Lembar Resmi (A4)');
        $gtkWeb->assertSee('btnPrintStatus');
        $gtkWeb->assertSee('btnPrintDetail');

        $studentWeb = $this->actingAs($admin)->get(route('attendance.report.students'));
        $studentWeb->assertStatus(200);
        $studentWeb->assertDontSee('Cetak Lembar Resmi (A4)');
        $studentWeb->assertSee('btnPrintStatus');
        $studentWeb->assertSee('btnPrintDetail');

        // 2. GTK Print Status Only: contains status table, does NOT contain individual daily records table
        $gtkPrintStatus = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'print_type' => 'status',
        ]));
        $gtkPrintStatus->assertStatus(200);
        $gtkPrintStatus->assertSee('LAPORAN REKAPITULASI STATUS KEHADIRAN');
        $gtkPrintStatus->assertSee('DL = Dinas Luar');
        $gtkPrintStatus->assertDontSee('II. RINCIAN PRESENSI HARIAN PER INDIVIDU');

        // 3. GTK Print Detail Only: contains individual daily records, does NOT contain status recap table
        $gtkPrintDetail = $this->actingAs($admin)->get(route('attendance.report.teachers.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'print_type' => 'detail',
        ]));
        $gtkPrintDetail->assertStatus(200);
        $gtkPrintDetail->assertSee('LAPORAN RINCIAN PRESENSI INDIVIDU');
        $gtkPrintDetail->assertDontSee('I. REKAPITULASI STATUS KEHADIRAN');

        // 4. Student Print Status Only: contains student status table, does NOT contain individual daily table
        $studentPrintStatus = $this->actingAs($admin)->get(route('attendance.report.students.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'print_type' => 'status',
        ]));
        $studentPrintStatus->assertStatus(200);
        $studentPrintStatus->assertSee('LAPORAN REKAPITULASI STATUS KEHADIRAN SISWA');
        $studentPrintStatus->assertSee('P = Pulang Cepat');
        $studentPrintStatus->assertDontSee('II. RINCIAN PRESENSI HARIAN PER SISWA');

        // 5. Student Print Detail Only: contains student individual records, does NOT contain status recap table
        $studentPrintDetail = $this->actingAs($admin)->get(route('attendance.report.students.print', [
            'period_type' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'print_type' => 'detail',
        ]));
        $studentPrintDetail->assertStatus(200);
        $studentPrintDetail->assertSee('LAPORAN RINCIAN PRESENSI INDIVIDU SISWA');
        $studentPrintDetail->assertDontSee('I. REKAPITULASI STATUS KEHADIRAN SISWA');
    }

    public function test_report_individual_detail_selection_and_drill_down_behavior(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $teacher = User::operationalPersonnel()->first();
        $student = Student::first();

        // 1. GTK Report: Default access (no user_id) shows Rekap Status table and placeholder guide
        $gtkDefault = $this->actingAs($admin)->get(route('attendance.report.teachers'));
        $gtkDefault->assertStatus(200);
        $gtkDefault->assertSee('Rekap Status Kehadiran');
        $gtkDefault->assertSee('Pilih Nama pada Dropdown di Atas');
        $gtkDefault->assertDontSee('Hari, Tanggal');

        // GTK Report: With user_id shows individual breakdown table with required columns
        if ($teacher) {
            $gtkDetail = $this->actingAs($admin)->get(route('attendance.report.teachers', [
                'user_id' => $teacher->id,
            ]));
            $gtkDetail->assertStatus(200);
            $gtkDetail->assertSee('Rincian Presensi Individu');
            $gtkDetail->assertSee($teacher->name);
            $gtkDetail->assertSee('Jam Masuk');
            $gtkDetail->assertSee('Jam Pulang');
            $gtkDetail->assertSee('Keterangan');
            $gtkDetail->assertDontSee('Pilih Nama pada Dropdown di Atas');
        }

        // 2. Student Report: Default access (no student_id) shows Rekap Status Siswa table and placeholder guide
        $studentDefault = $this->actingAs($admin)->get(route('attendance.report.students'));
        $studentDefault->assertStatus(200);
        $studentDefault->assertSee('Rekap Status Kehadiran Siswa');
        $studentDefault->assertSee('Pilih Nama pada Dropdown di Atas');
        $studentDefault->assertDontSee('Hari, Tanggal');

        // Student Report: With student_id shows individual student breakdown table
        if ($student) {
            $studentDetail = $this->actingAs($admin)->get(route('attendance.report.students', [
                'classroom_id' => $student->classroom_id,
                'student_id' => $student->id,
            ]));
            $studentDetail->assertStatus(200);
            $studentDetail->assertSee('Rincian Presensi Individu');
            $studentDetail->assertSee($student->name);
            $studentDetail->assertSee('Jam Masuk');
            $studentDetail->assertSee('Jam Pulang');
            $studentDetail->assertSee('Keterangan');
            $studentDetail->assertDontSee('Pilih Nama pada Dropdown di Atas');
        }
    }

    public function test_gtk_permit_synchronization_from_piket_inval_records(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        $adi = User::where('name', 'like', '%Adi Nuranto%')->first();

        $this->assertNotNull($adi);

        $journal = PiketJournal::create([
            'date' => '2026-10-05',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'coordinator_id' => $admin->id,
            'status' => 'berlangsung',
        ]);

        $classroom = Classroom::first();

        PiketInvalClass::create([
            'piket_journal_id' => $journal->id,
            'classroom_id' => $classroom->id,
            'absent_teacher_id' => $adi->id,
            'period_start' => 2,
            'period_end' => 3,
            'reason' => 'Izin Kepentingan Keluarga',
            'assignment_material' => 'literasi di perpus',
            'notes' => 'Dicatat melalui Telegram Bot Piket',
        ]);

        // Ensure sync runs and GtkPermit is present
        GtkPermit::syncFromPiketInvals();

        $permit = GtkPermit::where('user_id', $adi->id)
            ->whereDate('start_date', '2026-10-05')
            ->first();

        $this->assertNotNull($permit);
        $this->assertEquals('izin', $permit->permission_type);
        $this->assertStringContainsString('Izin Kepentingan Keluarga', $permit->reason);

        // 1. Visit Izin & Cuti GTK web page - Adi Nuranto must be listed
        $pageResponse = $this->actingAs($admin)->get(route('attendance.gtk-permits.index', [
            'month' => '2026-10',
            'user_id' => $adi->id,
        ]));
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('ADI NURANTO');
        $pageResponse->assertSee('Izin Kepentingan Keluarga');

        // 2. Test manual sync endpoint
        $syncResponse = $this->actingAs($admin)->post(route('attendance.gtk-permits.sync-piket'));
        $syncResponse->assertRedirect();
        $syncResponse->assertSessionHas('success');
    }

    public function test_non_admin_cannot_access_devices_menu(): void
    {
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // Non-admin accessing devices menu should get 403 Forbidden
        $response = $this->actingAs($teacher)->get(route('attendance.devices.index'));
        $response->assertStatus(403);
    }

    public function test_unregistered_device_is_created_as_unverified_and_push_is_ignored(): void
    {
        // 1. Device connects via handshake
        $response = $this->get('/iclock/cdata?SN=UNKNOWN-SN-999&options=all');
        $response->assertStatus(200);

        // 2. Device must be saved with is_active = false
        $this->assertDatabaseHas('attendance_devices', [
            'device_sn' => 'UNKNOWN-SN-999',
            'is_active' => false,
        ]);

        // 3. Pushing attendance data from unverified device must be ignored
        $pushPayload = "GUR-001\t2026-10-04 07:15:22\t1\t0\t0\n";
        $pushResponse = $this->call('POST', '/iclock/cdata?SN=UNKNOWN-SN-999&table=ATTLOG', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $pushPayload);

        $pushResponse->assertStatus(200);
        $pushResponse->assertSee('OK: 0');
    }

    public function test_admin_can_approve_unverified_device(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $device = AttendanceDevice::create([
            'name' => 'Perangkat Baru (192.168.1.50)',
            'device_sn' => 'SN-PENDING-001',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
            'server_port' => 8014,
            'status' => 'online',
            'is_active' => false,
        ]);

        // Admin approves device
        $response = $this->actingAs($admin)->post(route('attendance.devices.approve', $device->id), [
            'name' => 'Solution X601 Lobby Depan',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
        ]);

        $response->assertRedirect(route('attendance.devices.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_devices', [
            'id' => $device->id,
            'name' => 'Solution X601 Lobby Depan',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_view_biometrics_master_vault(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        AttendanceBiometric::create([
            'pin' => '41301',
            'bio_type' => 'fingerprint',
            'finger_id' => 6,
            'size' => 1032,
            'valid' => 1,
            'template' => 'SolTUzIxAAAD...',
            'source_device_sn' => 'SN-SOURCE-001',
        ]);

        $response = $this->actingAs($admin)->get(route('attendance.devices.biometrics'));
        $response->assertStatus(200);
        $response->assertSee('Bank Master Biometrik Sekolah');
        $response->assertSee('41301');
        $response->assertSee('Telunjuk Kanan');
    }

    public function test_super_admin_can_broadcast_biometrics_to_devices(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $target = AttendanceDevice::create([
            'name' => 'Solution X606-S Cabang',
            'device_sn' => 'SN-TARGET-002',
            'ip_address' => '192.168.1.13',
            'port' => 4370,
            'server_port' => 8014,
            'status' => 'online',
            'is_active' => true,
        ]);

        AttendanceBiometric::create([
            'pin' => '41301',
            'bio_type' => 'fingerprint',
            'finger_id' => 6,
            'size' => 1032,
            'valid' => 1,
            'template' => 'SolTUzIxAAAD...',
            'source_device_sn' => 'SN-SOURCE-001',
        ]);

        $response = $this->actingAs($admin)->post(route('attendance.devices.biometrics.broadcast'), [
            'target_device_id' => $target->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_device_commands', [
            'device_sn' => 'SN-TARGET-002',
            'status' => 'pending',
        ]);
    }

    public function test_super_admin_can_delete_biometric_from_master(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $bio = AttendanceBiometric::create([
            'pin' => '88888',
            'bio_type' => 'fingerprint',
            'finger_id' => 5,
            'size' => 1000,
            'valid' => 1,
            'template' => 'TEMP...',
            'source_device_sn' => 'SN-SOURCE-001',
        ]);

        $response = $this->actingAs($admin)->delete(route('attendance.devices.biometrics.destroy', $bio->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('attendance_biometrics', [
            'id' => $bio->id,
        ]);
    }

    public function test_school_schedule_page_accessible_by_all_users_and_link_shown_in_date_card(): void
    {
        $role = Role::where('slug', 'guru')->first();
        $teacher = User::factory()->create([
            'username' => 'guru_test',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        // 1. Periksa halaman KBM mobile: Link 'Jadwal Sekolah' harus ada di kartu tanggal di atas Hari Ini & Riwayat
        $mobileKbmResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.index'));

        $mobileKbmResponse->assertStatus(200);
        $mobileKbmResponse->assertSee(route('attendance.subject.school-schedule'));
        $mobileKbmResponse->assertSee('Jadwal Pelajaran');
        $mobileKbmResponse->assertSee('Riwayat');

        // 2. Periksa halaman Jadwal Pelajaran Sekolah: Dapat diakses oleh semua user tanpa 403 Forbidden
        $scheduleResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.school-schedule'));

        $scheduleResponse->assertStatus(200);
        $scheduleResponse->assertSee('Jadwal Pelajaran Sekolah');
        $scheduleResponse->assertSee('Senin');
        $scheduleResponse->assertSee('Selasa');
        $scheduleResponse->assertSee('Rabu');
        $scheduleResponse->assertSee('Kamis');
        $scheduleResponse->assertSee('Jumat');

        // 3. Periksa filter hari
        $filterDayResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.school-schedule', ['day' => 'Kamis']));

        $filterDayResponse->assertStatus(200);
        $filterDayResponse->assertSee('Hari Kamis');

        // 4. Periksa filter spesifik kelas, guru, dan pencarian
        $classroom = Classroom::first() ?? Classroom::create([
            'name' => 'XII TKJ 2',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
        $subject = Subject::first() ?? Subject::create([
            'name' => 'Pemrograman Web',
            'code' => 'PW01',
        ]);
        $schedule = LessonSchedule::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Kamis',
            'start_period' => 1,
            'end_period' => 2,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $filterClassResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.school-schedule', [
                'day' => 'Kamis',
                'classroom_id' => $classroom->id,
            ]));
        $filterClassResponse->assertStatus(200);
        $filterClassResponse->assertSee($classroom->name);
        $filterClassResponse->assertSee($subject->name);

        $filterTeacherResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.school-schedule', [
                'day' => 'Kamis',
                'teacher_id' => $teacher->id,
            ]));
        $filterTeacherResponse->assertStatus(200);
        $filterTeacherResponse->assertSee($subject->name);

        $filterSearchResponse = $this->actingAs($teacher)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
            ->get(route('attendance.subject.school-schedule', [
                'day' => 'Kamis',
                'search' => $subject->name,
            ]));
        $filterSearchResponse->assertStatus(200);
        $filterSearchResponse->assertSee($subject->name);
    }
}
