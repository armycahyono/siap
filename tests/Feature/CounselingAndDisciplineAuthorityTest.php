<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\Role;
use App\Models\StructuralPosition;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CounselingAndDisciplineAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function getOrCreateRole(string $slug, string $name, array $permissions = ['access_piket']): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name]);
        foreach ($permissions as $pSlug) {
            $perm = Permission::firstOrCreate(['slug' => $pSlug], ['name' => $pSlug, 'group' => 'Piket']);
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        return $role;
    }

    private function assignGuruBk(User $user, string $academicYear, bool $status = true): UserRoleAssignment
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['access_piket']);

        return UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $bkRole->id,
            'status' => $status,
            'academic_year' => $academicYear,
        ]);
    }

    // =========================================================================
    // SKENARIO 1: Penugasan aktif guru_bk diakui pada tahun ajaran yang sesuai
    // =========================================================================
    public function test_scenario_1_active_guru_bk_assignment_recognized_for_assigned_academic_year(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        $this->assignGuruBk($teacher, '2026/2027');

        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertTrue($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 2: Penugasan guru_bk pada tahun ajaran lama tidak memberi akses pada tahun baru
    // =========================================================================
    public function test_scenario_2_old_academic_year_assignment_does_not_grant_access_to_new_academic_year(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        $this->assignGuruBk($teacher, '2025/2026');

        $this->assertTrue($teacher->isCounselingBK('2025/2026'));
        $this->assertFalse($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 3: Guru mapel BK tanpa penugasan peran guru_bk tidak dapat akses catatan rahasia
    // =========================================================================
    public function test_scenario_3_subject_teacher_of_bk_without_guru_bk_role_assignment_cannot_access_counseling_notes(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $bkSubject = Subject::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Bimbingan dan Konseling', 'category' => 'Umum', 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $bkSubject->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'hours_per_week' => 2,
        ]);

        $this->assertFalse($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 4: Pengguna dengan users.position = 'Guru BK' tanpa penugasan resmi tidak mendapat akses
    // =========================================================================
    public function test_scenario_4_user_with_position_guru_bk_without_official_assignment_does_not_gain_access(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Guru Bimbingan Konseling (BK)',
        ]);

        $this->assertFalse($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 5: Pengguna dengan role global BK tanpa penugasan kontekstual tidak mendapat akses
    // =========================================================================
    public function test_scenario_5_user_with_global_role_bk_without_contextual_assignment_does_not_gain_access(): void
    {
        $bkRole = $this->getOrCreateRole('bk', 'Guru BK');
        $teacher = User::factory()->create([
            'role_id' => $bkRole->id,
            'position' => 'guru',
        ]);

        // Global role saja tanpa UserRoleAssignment aktif untuk tahun ajaran
        $this->assertFalse($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));

        // Kompatibilitas: jika ada UserRoleAssignment sah dengan role 'bk', akses tetap berlaku
        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $bkRole->id,
            'status' => true,
            'academic_year' => '2026/2027',
        ]);
        $teacher->unsetRelation('activeRoleAssignments');

        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertTrue($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 6: Jabatan struktural Koordinator BK saja tidak memberi akses catatan rahasia
    // =========================================================================
    public function test_scenario_6_structural_position_koordinator_bk_alone_does_not_grant_counseling_notes_access(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $bkPos = StructuralPosition::firstOrCreate(
            ['code' => 'BK'],
            ['name' => 'Koordinator Bimbingan dan Konseling', 'category' => 'Unit Kerja', 'order' => 8, 'is_active' => true]
        );

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $teacher->id,
            'structural_position_id' => $bkPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertFalse($teacher->isCounselingBK('2026/2027'));
        $this->assertFalse($teacher->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 7: Guru BK != Tim Kedisiplinan; Tim Kedisiplinan tidak dapat baca catatan rahasia
    // =========================================================================
    public function test_scenario_7_guru_bk_separated_from_discipline_team_and_discipline_cannot_read_counseling_notes(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        // Guru BK murni
        $guruBk = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);
        $this->assignGuruBk($guruBk, '2026/2027');

        $this->assertTrue($guruBk->isCounselingBK('2026/2027'));
        $this->assertFalse($guruBk->isDisciplineTeam('2026/2027'));
        $this->assertFalse($guruBk->canManageDiscipline());

        // Anggota Tim Kedisiplinan murni
        $tatibUser = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => '2026/2027',
        ]);

        $this->assertTrue($tatibUser->isDisciplineTeam('2026/2027'));
        $this->assertTrue($tatibUser->canManageDiscipline());
        $this->assertFalse($tatibUser->isCounselingBK('2026/2027'));
        $this->assertFalse($tatibUser->canAccessCounselingNotes('2026/2027'));
    }

    // =========================================================================
    // SKENARIO 8: Pengguna merangkap Guru BK dan Tim Kedisiplinan memiliki kedua kewenangan
    // =========================================================================
    public function test_scenario_8_user_with_dual_assignment_guru_bk_and_discipline_team_has_both_authorities(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        // Penugasan Guru BK
        $this->assignGuruBk($teacher, '2026/2027');

        // Penugasan Tim Kedisiplinan
        UserRoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => '2026/2027',
        ]);

        $this->assertTrue($teacher->isCounselingBK('2026/2027'));
        $this->assertTrue($teacher->canAccessCounselingNotes('2026/2027'));
        $this->assertTrue($teacher->isDisciplineTeam('2026/2027'));
        $this->assertTrue($teacher->canManageDiscipline());
    }

    // =========================================================================
    // SKENARIO 9: Guru BK hanya melihat kasus Piket yang dirujuk ke BK (rujuk_bk)
    // =========================================================================
    public function test_scenario_9_guru_bk_can_access_incidents_index_and_sees_only_rujuk_bk_cases(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin', ['access_piket', 'manage_piket_cases']);
        $guruRole = $this->getOrCreateRole('guru', 'Guru', ['access_piket']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI RPL 1'],
            ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'is_active' => true]
        );

        $studentBk = Student::create([
            'nisn' => '1111111111',
            'name' => 'Siswa Perlu Konseling',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);
        $studentWali = Student::create([
            'nisn' => '2222222222',
            'name' => 'Siswa Rujuk Wali Kelas',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);
        $studentKesiswaan = Student::create([
            'nisn' => '3333333333',
            'name' => 'Siswa Kasus Kesiswaan',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);

        $journal = PiketJournal::firstOrCreate(
            ['date' => now()->toDateString()],
            ['academic_year' => $ay->name, 'semester' => 'Ganjil', 'status' => 'berlangsung', 'coordinator_id' => $admin->id]
        );

        $incidentBk = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '07:30',
            'student_id' => $studentBk->id,
            'student_name' => $studentBk->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Sering murung dan terlambat',
            'action_taken' => 'Konseling awal di meja piket',
            'follow_up_target' => 'rujuk_bk',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        $incidentWali = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:00',
            'student_id' => $studentWali->id,
            'student_name' => $studentWali->name,
            'classroom_id' => $classroom->id,
            'category' => 'seragam',
            'description' => 'Atribut seragam tidak lengkap',
            'action_taken' => 'Teguran lisan',
            'follow_up_target' => 'rujuk_wali_kelas',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        $incidentKesiswaan = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30',
            'student_id' => $studentKesiswaan->id,
            'student_name' => $studentKesiswaan->name,
            'classroom_id' => $classroom->id,
            'category' => 'rokok',
            'description' => 'Membawa rokok ke sekolah',
            'action_taken' => 'Disita rokoknya',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Setup Guru BK melalui UserRoleAssignment sah
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $ay->name);

        $this->assertTrue($guruBk->isCounselingBK($ay->name));
        $this->assertFalse($guruBk->hasPermission('manage_piket_cases'));
        $this->assertFalse($guruBk->canManagePiketCases());
        $this->assertTrue($guruBk->canViewPiketCases());

        // 1. Guru BK dapat mengakses index tanpa 403
        $response = $this->actingAs($guruBk)->get(route('piket.incidents.index'));
        $response->assertStatus(200);

        // 2. Guru BK hanya melihat kasus rujuk_bk
        $response->assertSee($studentBk->name);
        $response->assertDontSee($studentWali->name);
        $response->assertDontSee($studentKesiswaan->name);

        // Verifikasi koleksi data yang dikirim ke view
        $incidentsInView = $response->viewData('incidents');
        $this->assertCount(1, $incidentsInView);
        $this->assertEquals($incidentBk->id, $incidentsInView->first()->id);
    }

    // =========================================================================
    // SKENARIO 10: Notifikasi kasus untuk Guru BK hanya menghitung kasus rujuk_bk
    // =========================================================================
    public function test_scenario_10_notification_for_guru_bk_counts_only_rujuk_bk_incidents(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'X RPL 1'],
            ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'is_active' => true]
        );

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $journal = PiketJournal::firstOrCreate(
            ['date' => now()->toDateString()],
            ['academic_year' => $ay->name, 'semester' => 'Ganjil', 'status' => 'berlangsung', 'coordinator_id' => $admin->id]
        );

        $student1 = Student::create([
            'nisn' => '7777777771',
            'name' => 'Siswa Kasus BK Notif',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);
        $student2 = Student::create([
            'nisn' => '7777777772',
            'name' => 'Siswa Kasus Umum Notif',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);

        // Kasus 1: Rujuk BK
        PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '07:30',
            'student_id' => $student1->id,
            'student_name' => $student1->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Perlu bimbingan konseling',
            'action_taken' => 'Dirujuk ke BK',
            'follow_up_target' => 'rujuk_bk',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Kasus 2: Kasus Umum (Kesiswaan)
        PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:00',
            'student_id' => $student2->id,
            'student_name' => $student2->name,
            'classroom_id' => $classroom->id,
            'category' => 'seragam',
            'description' => 'Sepatu tidak sesuai ketentuan',
            'action_taken' => 'Teguran',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Setup Guru BK
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $ay->name);

        $bkNotifs = NotificationService::getUserNotifications($guruBk);
        $bkNotifIds = $bkNotifs->pluck('id')->all();

        // Guru BK hanya menerima notifikasi rujukan BK (bukan rekap umum seluruh kasus piket)
        $this->assertContains('incidents_today_bk', $bkNotifIds);
        $this->assertNotContains('incidents_today_general', $bkNotifIds);

        $bkAlert = $bkNotifs->firstWhere('id', 'incidents_today_bk');
        $this->assertStringContainsString('1 rujukan kasus siswa', $bkAlert['message']);

        // Setup Tim Kedisiplinan: Menerima rekap seluruh kejadian piket (total 2 kasus)
        $tatibUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => $ay->name,
        ]);

        $tatibNotifs = NotificationService::getUserNotifications($tatibUser);
        $tatibNotifIds = $tatibNotifs->pluck('id')->all();
        $this->assertContains('incidents_today_general', $tatibNotifIds);
        $generalAlert = $tatibNotifs->firstWhere('id', 'incidents_today_general');
        $this->assertStringContainsString('2 kejadian siswa', $generalAlert['message']);
    }

    // =========================================================================
    // SKENARIO 11: Super Admin tetap memiliki akses administratif
    // =========================================================================
    public function test_scenario_11_super_admin_retains_full_administrative_access(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin', ['access_piket', 'manage_piket_cases']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->canAccessCounselingNotes());
        $this->assertTrue($admin->canManageDiscipline());
        $this->assertTrue($admin->canManagePiketCases());
        $this->assertTrue($admin->canViewPiketCases());
    }

    // =========================================================================
    // SKENARIO 12: Penonaktifan penugasan Guru BK tidak merusak penugasan lain
    // =========================================================================
    public function test_scenario_12_deactivating_guru_bk_assignment_does_not_affect_other_assignments(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );
        $grade = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);

        $teacher = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        // Penugasan wali kelas
        $classroom = Classroom::create([
            'name' => 'X TKR 1',
            'academic_year' => $ay->name,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        // Penugasan Guru BK
        $bkAssignment = $this->assignGuruBk($teacher, $ay->name);

        $this->assertTrue($teacher->isCounselingBK($ay->name));
        $this->assertTrue($teacher->canAccessCounselingNotes($ay->name));
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));

        // Nonaktifkan penugasan Guru BK
        $bkAssignment->update(['status' => false]);
        $teacher->unsetRelation('activeRoleAssignments');

        // Hak akses BK berakhir
        $this->assertFalse($teacher->isCounselingBK($ay->name));
        $this->assertFalse($teacher->canAccessCounselingNotes($ay->name));

        // Namun penugasan wali kelas tetap utuh
        $this->assertTrue($teacher->isHomeroomTeacher($classroom));
        $this->assertTrue($teacher->isHomeroomTeacher());
    }

    // =========================================================================
    // PENGUJIAN INTEGRASI ALUR PIKET & BATASAN KEWENANGAN TAMBAHAN
    // =========================================================================
    public function test_incident_reporting_and_referral_to_bk_intact(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $grade = GradeLevel::firstOrCreate(['name' => 'XII'], ['level' => 12]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $classroom = Classroom::firstOrCreate(
            ['name' => 'XII TKJ 1'],
            [
                'academic_year' => $ay->name,
                'grade_level_id' => $grade->id,
                'department_id' => $dept->id,
                'is_active' => true,
            ]
        );

        $student = Student::firstOrCreate(
            ['nisn' => '1234567890'],
            ['name' => 'Budi Santoso', 'classroom_id' => $classroom->id, 'academic_year' => $ay->name, 'status' => 'aktif']
        );

        // 1. Laporkan kejadian dengan follow_up_target = 'rujuk_bk'
        $response = $this->actingAs($admin)->post(route('piket.incidents.store'), [
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat dan perlu pembinaan motivasi',
            'action_taken' => 'Diberikan peringatan piket dan dirujuk',
            'follow_up_target' => 'rujuk_bk',
            'evidence_photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertRedirect(route('piket.incidents.index'));

        $incident = PiketIncident::where('student_id', $student->id)->latest()->first();
        $this->assertNotNull($incident);
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);
        $this->assertEquals('tercatat', $incident->status);
        $this->assertNotNull($incident->evidence_photo);

        // 2. Setup Guru BK sah melalui penugasan kontekstual
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $ay->name);

        $this->assertTrue($guruBk->isCounselingBK($ay->name));
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);

        // Guru BK dapat melihat foto untuk kejadian yang dirujuk ke BK
        $photoResp = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incident));
        $photoResp->assertOk();

        // 3. Kasus lain yang dirujuk ke kesiswaan (bukan BK)
        $incidentKesiswaan = PiketIncident::create([
            'piket_journal_id' => $incident->piket_journal_id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '09:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'perkelahian',
            'description' => 'Insiden perkelahian',
            'action_taken' => 'Diamankan',
            'follow_up_target' => 'rujuk_kesiswaan',
            'evidence_photo' => $incident->evidence_photo,
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Guru BK tidak dapat melihat foto kejadian yang tidak dirujuk ke BK
        $photoRespForbidden = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incidentKesiswaan));
        $photoRespForbidden->assertForbidden();

        // KECUALI jika Guru BK adalah pelapor (recorded_by) kejadian tersebut
        $incidentReportedByBk = PiketIncident::create([
            'piket_journal_id' => $incident->piket_journal_id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '09:15',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'seragam',
            'description' => 'Insiden dicatat langsung oleh guru BK',
            'action_taken' => 'Teguran',
            'follow_up_target' => 'rujuk_kesiswaan',
            'evidence_photo' => $incident->evidence_photo,
            'recorded_by' => $guruBk->id,
            'status' => 'tercatat',
        ]);
        $photoRespReporter = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incidentReportedByBk));
        $photoRespReporter->assertOk();

        // ATAU jika Guru BK adalah wali kelas siswa terkait
        $classroomPerwalian = Classroom::create([
            'name' => 'XII TKJ 2',
            'academic_year' => $ay->name,
            'grade_level_id' => $grade->id,
            'homeroom_teacher_id' => $guruBk->id,
            'is_active' => true,
        ]);
        $studentWali = Student::create([
            'nisn' => '5555555555',
            'name' => 'Siswa Perwalian Guru BK',
            'classroom_id' => $classroomPerwalian->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);
        $incidentWaliBk = PiketIncident::create([
            'piket_journal_id' => $incident->piket_journal_id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '09:30',
            'student_id' => $studentWali->id,
            'student_name' => $studentWali->name,
            'classroom_id' => $classroomPerwalian->id,
            'category' => 'seragam',
            'description' => 'Kasus perwalian guru BK',
            'action_taken' => 'Teguran',
            'follow_up_target' => 'rujuk_kesiswaan',
            'evidence_photo' => $incident->evidence_photo,
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);
        $photoRespHomeroom = $this->actingAs($guruBk)->get(route('piket.incidents.photo', $incidentWaliBk));
        $photoRespHomeroom->assertOk();

        // Tim Kedisiplinan tetap dapat melihatnya
        $tatibUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => $ay->name,
        ]);

        $photoRespTatib = $this->actingAs($tatibUser)->get(route('piket.incidents.photo', $incidentKesiswaan));
        $photoRespTatib->assertOk();
    }

    public function test_guru_bk_cannot_destroy_or_create_incident(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin', ['access_piket', 'manage_piket_cases']);
        $guruRole = $this->getOrCreateRole('guru', 'Guru', ['access_piket']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['semester' => 'Ganjil', 'is_active' => true]);
        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $classroom = Classroom::firstOrCreate(['name' => 'XI RPL 1'], ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'is_active' => true]);
        $student = Student::create([
            'nisn' => '4444444444',
            'name' => 'Siswa Kasus Uji',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);

        $journal = PiketJournal::firstOrCreate(
            ['date' => now()->toDateString()],
            ['academic_year' => $ay->name, 'semester' => 'Ganjil', 'status' => 'berlangsung', 'coordinator_id' => $admin->id]
        );

        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '07:30',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Uraian kasus',
            'action_taken' => 'Tindakan',
            'follow_up_target' => 'rujuk_bk',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $ay->name);

        // Guru BK tidak dapat membuka halaman pencatatan kasus piket
        $createResponse = $this->actingAs($guruBk)->get(route('piket.incidents.create'));
        $createResponse->assertStatus(403);

        // Guru BK tidak dapat menghapus catatan kasus piket
        $deleteResponse = $this->actingAs($guruBk)->delete(route('piket.incidents.destroy', $incident));
        $deleteResponse->assertStatus(403);
        $this->assertModelExists($incident);
    }

    public function test_waka_kesiswaan_has_discipline_coordination_authority_without_automatic_counseling(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $wakaPos = StructuralPosition::firstOrCreate(
            ['code' => 'WAKA_KESISWAAN'],
            ['name' => 'Waka Kesiswaan', 'category' => 'Wakil Kepala Sekolah', 'order' => 4, 'is_active' => true]
        );

        $wakaUser = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'guru',
        ]);

        TeacherStructuralAssignment::create([
            'teacher_id' => $wakaUser->id,
            'structural_position_id' => $wakaPos->id,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertTrue($wakaUser->isWakaKesiswaan());
        $this->assertFalse($wakaUser->isDisciplineTeam());
        $this->assertTrue($wakaUser->canManageDiscipline());

        // Waka Kesiswaan tidak otomatis menjadi konselor BK
        $this->assertFalse($wakaUser->isCounselingBK('2026/2027'));
        $this->assertFalse($wakaUser->canAccessCounselingNotes('2026/2027'));
    }

    public function test_regular_teacher_without_bk_or_piket_authority_cannot_access_incidents_index(): void
    {
        $guruRole = $this->getOrCreateRole('guru', 'Guru', ['access_piket']);
        $regularTeacher = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        $response = $this->actingAs($regularTeacher)->get(route('piket.incidents.index'));
        $response->assertStatus(403);
    }

    public function test_piket_officer_and_admin_retain_full_case_management(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin', ['access_piket', 'manage_piket_cases']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['semester' => 'Ganjil', 'is_active' => true]);
        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $classroom = Classroom::firstOrCreate(['name' => 'XI RPL 1'], ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'is_active' => true]);
        $student = Student::create([
            'nisn' => '4444444444',
            'name' => 'Siswa Kasus Uji',
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);

        $journal = PiketJournal::firstOrCreate(
            ['date' => now()->toDateString()],
            ['academic_year' => $ay->name, 'semester' => 'Ganjil', 'status' => 'berlangsung', 'coordinator_id' => $admin->id]
        );

        $incident = PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '07:30',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Uraian kasus',
            'action_taken' => 'Tindakan',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);

        // Admin can access index
        $adminIndex = $this->actingAs($admin)->get(route('piket.incidents.index'));
        $adminIndex->assertStatus(200);

        // Admin can delete incident
        $deleteResp = $this->actingAs($admin)->delete(route('piket.incidents.destroy', $incident));
        $deleteResp->assertRedirect();
        $this->assertModelMissing($incident);
    }
}
