<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\PiketDutySwap;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobilePiketEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected User $piketOfficer;

    protected Classroom $classroom;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        AcademicYear::where('id', '!=', $ay->id)->update(['is_active' => false]);

        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);

        $this->piketOfficer = User::create([
            'name' => 'Petugas Piket Test',
            'username' => 'petugaspikettest',
            'email' => 'piket@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $grade = GradeLevel::firstOrCreate(['level' => 'XII'], ['name' => 'Kelas XII']);

        $this->classroom = Classroom::create([
            'name' => 'XII MM 1',
            'academic_year' => '2026/2027',
            'academic_year_id' => $ay->id,
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Citra Siswa Test',
            'nis' => '11223',
            'nisn' => '0011223344',
            'gender' => 'P',
            'is_active' => true,
        ]);

        PiketSchedule::create([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => 'Senin',
            'teacher_id' => $this->piketOfficer->id,
            'role' => 'koordinator',
            'is_active' => true,
        ]);
    }

    public function test_can_fetch_piket_weekly_schedules(): void
    {
        $response = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket/schedules');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'academic_year' => '2026/2027',
                    'semester' => 'Ganjil',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'days',
                    'schedules' => [
                        'Senin',
                        'Selasa',
                        'Rabu',
                        'Kamis',
                        'Jumat',
                        'Sabtu',
                    ],
                ],
            ]);
    }

    public function test_can_fetch_piket_journals_archive_and_detail(): void
    {
        $todayStr = Carbon::today()->toDateString();
        $journal = PiketJournal::create([
            'date' => $todayStr,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'status' => 'berlangsung',
            'coordinator_id' => $this->piketOfficer->id,
            'notes' => 'Catatan harian aman terkendali',
        ]);

        $resList = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket/journals');

        $resList->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'journals' => [
                        '*' => [
                            'id',
                            'date',
                            'coordinator_name',
                            'permits_count',
                        ],
                    ],
                    'pagination',
                ],
            ]);

        $resDetail = $this->actingAs($this->piketOfficer)
            ->getJson("/api/v1/mobile/tasks/piket/journals/{$journal->id}");

        $resDetail->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'journal' => [
                        'id' => $journal->id,
                        'date' => $todayStr,
                        'coordinator_name' => $this->piketOfficer->name,
                    ],
                ],
            ]);
    }

    public function test_can_confirm_student_return_time_for_gate_permit(): void
    {
        $todayStr = Carbon::today()->toDateString();
        $permit = PiketStudentPermit::create([
            'permit_number' => 'SK-TEST-001',
            'permit_date' => $todayStr,
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'izin_keluar_sementara',
            'reason' => 'Mengambil berkas penting di rumah',
            'departure_time' => '09:00:00',
            'return_time' => null,
            'given_by' => $this->piketOfficer->id,
            'status' => 'diberikan',
        ]);

        $response = $this->actingAs($this->piketOfficer)
            ->postJson("/api/v1/mobile/tasks/piket/permits/{$permit->id}/return");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $permit->refresh();
        $this->assertNotNull($permit->return_time);
    }

    public function test_can_fetch_piket_reports(): void
    {
        $response = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket/reports');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'start_date',
                    'end_date',
                    'stats' => [
                        'total_late',
                        'total_leave',
                        'total_inval',
                        'total_incidents',
                        'total_guests',
                    ],
                ],
            ]);
    }

    public function test_piket_extended_spa_pages_are_accessible(): void
    {
        $this->get('/mobile/pages/tasks/piket_schedules.html')
            ->assertStatus(200)
            ->assertSee('Jadwal Tim Piket Mingguan');

        $this->get('/mobile/pages/tasks/piket_journals.html')
            ->assertStatus(200)
            ->assertSee('Arsip Jurnal Piket Harian');

        $this->get('/mobile/pages/tasks/piket_reports.html')
            ->assertStatus(200)
            ->assertSee('Laporan &amp; Tren Piket', false);
    }

    public function test_piket_data_returns_school_end_time_and_closing_state(): void
    {
        $response = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'school_end_time',
                    'is_near_closing_time',
                    'should_remind_close_journal',
                ],
            ]);
    }

    public function test_coordinator_receives_close_journal_notification_near_or_after_closing(): void
    {
        // Set time to past school end time on Monday (e.g., 15:30)
        Carbon::setTestNow(Carbon::parse('next monday 15:30:00'));

        $notifications = NotificationService::getUserNotifications($this->piketOfficer);
        $notifIds = $notifications->pluck('id')->all();

        $this->assertContains('piket_close_journal_reminder', $notifIds);

        Carbon::setTestNow(); // Reset mock time
    }

    public function test_non_coordinator_does_not_receive_close_journal_notification(): void
    {
        // Set time to past school end time on Monday (e.g., 15:30)
        Carbon::setTestNow(Carbon::parse('next monday 15:30:00'));

        $otherTeacher = User::create([
            'name' => 'Guru Lain Non Koordinator',
            'username' => 'gurulainnonkoordinator',
            'email' => 'gurulain@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $this->piketOfficer->role_id,
            'is_active' => true,
        ]);

        $notifications = NotificationService::getUserNotifications($otherTeacher);
        $notifIds = $notifications->pluck('id')->all();

        $this->assertNotContains('piket_close_journal_reminder', $notifIds);

        Carbon::setTestNow(); // Reset mock time
    }

    public function test_piket_page_has_plain_text_officer_title_and_closing_alert_banner(): void
    {
        $response = $this->get('/mobile/pages/tasks/piket.html');

        $response->assertStatus(200)
            ->assertSee('id="piket-close-journal-alert"', false)
            ->assertSee('Pengingat Tutup Jurnal', false)
            ->assertSee('id="piket-shift-status-badge"', false)
            ->assertSee('Piket Hari Ini', false)
            ->assertSee('/tasks/piket/kbm', false)
            ->assertSee('Jadwal KBM', false);
    }

    public function test_can_fetch_piket_live_kbm_data(): void
    {
        $response = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket/live-kbm?period=2');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_period' => 2,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'today',
                    'today_day',
                    'current_time',
                    'selected_period',
                    'active_period_times',
                    'period_times',
                    'summary' => [
                        'total_classrooms',
                        'teaching_count',
                        'attended_count',
                        'unattended_count',
                    ],
                    'classrooms' => [
                        '*' => [
                            'classroom_id',
                            'classroom_name',
                            'homeroom_teacher_name',
                            'status',
                            'status_label',
                            'is_attended',
                            'subject_name',
                            'teacher_name',
                            'attendance_stats' => [
                                'hadir',
                                'sakit',
                                'izin',
                                'dispen',
                                'alfa',
                                'bolos',
                                'telat',
                                'pulang',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_piket_live_kbm_spa_page_is_accessible(): void
    {
        $this->get('/mobile/pages/tasks/piket_kbm.html')
            ->assertStatus(200)
            ->assertSee('Jadwal Live KBM Kelas')
            ->assertSee('id="piket-kbm-period-selector"', false)
            ->assertSee('id="piket-kbm-classes-list"', false);
    }

    public function test_substitute_piket_officer_gets_meja_piket_access_today(): void
    {
        $guruRole = Role::where('slug', 'guru')->first();
        $substituteTeacher = User::create([
            'name' => 'Guru Pengganti Piket',
            'username' => 'gurupenggantipiket',
            'email' => 'pengganti@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $this->assertFalse($substituteTeacher->canAccessMejaPiket());

        $origSchedule = PiketSchedule::create([
            'teacher_id' => $this->piketOfficer->id,
            'day' => 'Senin',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Create duty swap approved for today
        PiketDutySwap::create([
            'original_schedule_id' => $origSchedule->id,
            'requester_id' => $this->piketOfficer->id,
            'replacement_teacher_id' => $substituteTeacher->id,
            'duty_date' => Carbon::today()->toDateString(),
            'reason' => 'Menggantikan tugas piket karena dinas luar',
            'status' => 'disetujui',
            'approved_by' => $this->piketOfficer->id,
            'approved_at' => Carbon::now(),
        ]);

        $this->assertTrue($substituteTeacher->isPiketOfficerToday());
        $this->assertTrue($substituteTeacher->canAccessMejaPiket());

        // Verify substitute teacher can access piket data API
        $this->actingAs($substituteTeacher)
            ->getJson('/api/v1/mobile/tasks/piket')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_batch_student_permit_creation_and_grouped_slip_printing(): void
    {
        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso];

        PiketSchedule::create([
            'teacher_id' => $this->piketOfficer->id,
            'day' => $todayDay,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        // Create 2 classrooms: X TKR 2 and X TKR 3
        $grade = GradeLevel::first();
        $classA = Classroom::create([
            'name' => 'X TKR 2',
            'academic_year' => '2026/2027',
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);
        $classB = Classroom::create([
            'name' => 'X TKR 3',
            'academic_year' => '2026/2027',
            'grade_level_id' => $grade->id,
            'is_active' => true,
        ]);

        // Create 4 students: 2 in Class A, 2 in Class B
        $s1 = Student::create(['name' => 'Ahmad Dani', 'nis' => '10234', 'classroom_id' => $classA->id, 'is_active' => true]);
        $s2 = Student::create(['name' => 'Budi Santoso', 'nis' => '10235', 'classroom_id' => $classA->id, 'is_active' => true]);
        $s3 = Student::create(['name' => 'Candra Wijaya', 'nis' => '10301', 'classroom_id' => $classB->id, 'is_active' => true]);
        $s4 = Student::create(['name' => 'Dedi Kusuma', 'nis' => '10302', 'classroom_id' => $classB->id, 'is_active' => true]);

        $payload = [
            'permit_type' => 'terlambat',
            'permit_date' => Carbon::today()->toDateString(),
            'student_ids' => [$s1->id, $s2->id, $s3->id, $s4->id],
            'reason' => 'Ban bocor bersama di dekat lampu merah',
            'entry_time' => '07:20',
            'action_taken' => 'Pembinaan piket dan diizinkan masuk kelas',
        ];

        $response = $this->actingAs($this->piketOfficer)
            ->postJson('/api/v1/mobile/tasks/piket/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_batch' => true,
                'total_students' => 4,
                'total_classes' => 2,
            ]);

        $batchId = $response->json('batch_id');
        $this->assertNotEmpty($batchId);

        // Verify database records
        $permits = PiketStudentPermit::where('batch_id', $batchId)->get();
        $this->assertCount(4, $permits);

        // Verify batch print slip renders grouped by classroom
        $this->actingAs($this->piketOfficer)
            ->get(route('piket.permits.batch-slip', ['batchId' => $batchId]))
            ->assertStatus(200)
            ->assertSee('X TKR 2')
            ->assertSee($s1->name)
            ->assertSee($s2->name)
            ->assertSee('X TKR 3')
            ->assertSee($s3->name)
            ->assertSee($s4->name)
            ->assertSee('Ban bocor bersama di dekat lampu merah');
    }

    public function test_waka_can_print_permit_slip_and_batch_slip(): void
    {
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_sarpras'], ['name' => 'Waka Sarpras']);
        $wakaUser = User::create([
            'name' => 'Waka Sarpras Test',
            'username' => 'wakasarprastest',
            'email' => 'waka_sarpras@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $wakaRole->id,
            'is_active' => true,
        ]);

        $permit = PiketStudentPermit::create([
            'permit_number' => 'IZN-20261105-099',
            'permit_date' => Carbon::today()->toDateString(),
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'permit_type' => 'terlambat',
            'permit_category' => 'terlambat',
            'reason' => 'Rantai motor putus',
            'entry_time' => '07:35',
            'period_assigned' => '2',
            'given_by' => $this->piketOfficer->id,
            'status' => 'diberikan',
        ]);

        // Waka should be able to view and print the permit slip without 403 Access Denied
        $this->actingAs($wakaUser)
            ->get(route('piket.permits.slip', $permit))
            ->assertStatus(200)
            ->assertSee('SLIP')
            ->assertSee($this->student->name)
            ->assertSee('Rantai motor putus');
    }

    public function test_piket_officer_can_input_izin_tidak_masuk_with_category_source_date_range_and_attachment(): void
    {
        Storage::fake('local');

        $this->student->update(['classroom_id' => $this->classroom->id]);

        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso];

        PiketSchedule::create([
            'teacher_id' => $this->piketOfficer->id,
            'day' => $todayDay,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $fakeFile = UploadedFile::fake()->image('surat_dokter.jpg');
        $today = Carbon::today()->toDateString();
        $endDate = Carbon::today()->addDays(2)->toDateString();

        $payload = [
            'permit_type' => 'izin_tidak_masuk',
            'permit_category' => 'sakit',
            'delivery_source' => 'titip_teman',
            'classroom_id' => $this->classroom->id,
            'permit_date' => $today,
            'end_date' => $endDate,
            'student_ids' => [$this->student->id],
            'reason' => 'Demam tinggi dan istirahat dokter',
            'action_taken' => 'Surat dititipkan teman sekelas ke meja piket',
            'attachment' => $fakeFile,
        ];

        $response = $this->actingAs($this->piketOfficer)
            ->post('/api/v1/mobile/tasks/piket/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_students' => 1,
            ]);

        $permit = PiketStudentPermit::where('student_id', $this->student->id)
            ->where('permit_type', 'izin_tidak_masuk')
            ->first();

        $this->assertNotNull($permit);
        $this->assertEquals('sakit', $permit->permit_category);
        $this->assertEquals($endDate, $permit->end_date->toDateString());
        $this->assertEquals('piket', $permit->reporter_type);
        $this->assertStringContainsString('Surat fisik dititipkan ke teman sekelas', $permit->action_taken);
        $this->assertNotEmpty($permit->attachment_path);
        Storage::disk('local')->assertExists($permit->attachment_path);

        // Verify getPiketData returns attachment_url and end_date
        $piketDataResponse = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket');

        $piketDataResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $permitsList = $piketDataResponse->json('data.permits');
        $found = collect($permitsList)->firstWhere('id', $permit->id);
        $this->assertNotNull($found);
        $this->assertEquals('sakit', $found['permit_category']);
        $this->assertEquals(Carbon::parse($endDate)->format('d/m/Y'), $found['end_date']);
        $this->assertEquals('piket', $found['reporter_type']);
        $this->assertNotEmpty($found['attachment_url']);
        $this->assertNotEmpty($found['attachment_url']);
    }

    public function test_can_issue_batch_permit_for_multiple_students_without_dropping_students(): void
    {
        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $todayDay = $dayNames[Carbon::today()->dayOfWeekIso];

        PiketSchedule::firstOrCreate([
            'teacher_id' => $this->piketOfficer->id,
            'day' => $todayDay,
            'academic_year' => '2026/2027',
        ], [
            'is_active' => true,
        ]);

        $st1 = $this->student;
        $st1->update(['classroom_id' => $this->classroom->id]);
        $st2 = Student::create([
            'name' => 'Siswa Dua Test',
            'nis' => '11224',
            'nisn' => '0011223345',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);
        $st3 = Student::create([
            'name' => 'Siswa Tiga Test',
            'nis' => '11225',
            'nisn' => '0011223346',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);
        $st4 = Student::create([
            'name' => 'Siswa Empat Test',
            'nis' => '11226',
            'nisn' => '0011223347',
            'gender' => 'P',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        $today = Carbon::today()->toDateString();

        // Simulate st1 already having a permit earlier today
        $journal = PiketJournal::firstOrCreate(
            ['date' => $today],
            [
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $this->piketOfficer->id,
            ]
        );

        $existingPermit = PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => 'IZN-OLD-001',
            'permit_date' => $today,
            'student_id' => $st1->id,
            'student_name' => $st1->name,
            'classroom_id' => $st1->classroom_id,
            'permit_type' => 'terlambat',
            'permit_category' => 'terlambat',
            'reason' => 'Alasan lama',
            'entry_time' => '07:15',
            'given_by' => $this->piketOfficer->id,
            'status' => 'diberikan',
        ]);

        // Now issue a batch permit for all 4 students (including st1)
        $payload = [
            'permit_type' => 'terlambat',
            'permit_date' => $today,
            'student_ids' => [$st1->id, $st2->id, $st3->id, $st4->id],
            'reason' => 'Terlambat rombongan ban bocor',
            'entry_time' => '07:45',
        ];

        $response = $this->actingAs($this->piketOfficer)
            ->post('/api/v1/mobile/tasks/piket/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_batch' => true,
                'total_students' => 4,
            ]);

        $batchId = $response->json('batch_id');
        $this->assertNotEmpty($batchId);

        // Verify all 4 students are linked to this batch_id
        $permitsInBatch = PiketStudentPermit::where('batch_id', $batchId)->get();
        $this->assertCount(4, $permitsInBatch);
        $this->assertTrue($permitsInBatch->contains('student_id', $st1->id));
        $this->assertTrue($permitsInBatch->contains('student_id', $st2->id));
        $this->assertTrue($permitsInBatch->contains('student_id', $st3->id));
        $this->assertTrue($permitsInBatch->contains('student_id', $st4->id));

        // Verify getPiketData returns batch_id in permitsList
        $piketDataResponse = $this->actingAs($this->piketOfficer)
            ->getJson('/api/v1/mobile/tasks/piket');

        $piketDataResponse->assertStatus(200);
        $permitsList = $piketDataResponse->json('data.permits');
        $found = collect($permitsList)->firstWhere('batch_id', $batchId);
        $this->assertNotNull($found);
        $this->assertEquals($batchId, $found['batch_id']);

        // Verify printSlip with batch_id automatically renders batch slip (without needing ?batch=1)
        $slipResponse = $this->actingAs($this->piketOfficer)
            ->get(route('piket.permits.slip', ['permit' => $permitsInBatch->first()->id]));

        $slipResponse->assertStatus(200);
        $slipResponse->assertSee($st1->name);
        $slipResponse->assertSee($st2->name);
        $slipResponse->assertSee($st3->name);
        $slipResponse->assertSee($st4->name);
    }

    public function test_can_issue_permit_with_multiple_reasons(): void
    {
        $this->student->update(['classroom_id' => $this->classroom->id]);
        $today = Carbon::today()->toDateString();
        $payload = [
            'permit_type' => 'terlambat',
            'permit_date' => $today,
            'student_id' => $this->student->id,
            'reasons' => [
                'Bangun kesiangan',
                'Kendaraan mogok / ban bocor',
                'Hujan lebat di jalan',
            ],
            'entry_time' => '07:35',
        ];

        $response = $this->actingAs($this->piketOfficer)
            ->post('/api/v1/mobile/tasks/piket/permits/store', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $permit = PiketStudentPermit::where('student_id', $this->student->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($permit);
        $this->assertStringContainsString('Bangun kesiangan', $permit->reason);
        $this->assertStringContainsString('Kendaraan mogok / ban bocor', $permit->reason);
        $this->assertStringContainsString('Hujan lebat di jalan', $permit->reason);
    }
}
