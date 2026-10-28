<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BkConfidentialNote;
use App\Models\BkService;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisciplineTeamStandardizationTest extends TestCase
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

    private function createStudent(string $academicYear = '2026/2027', string $name = 'Siswa Test Disiplin'): Student
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

    private function createIncident(User $recorder, Student $student, array $attributes = []): PiketIncident
    {
        $journal = PiketJournal::firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $recorder->id,
            ]
        );

        return PiketIncident::create(array_merge([
            'piket_journal_id' => $journal->id,
            'academic_year' => '2026/2027',
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:00:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat dan atribut seragam tidak lengkap',
            'action_taken' => 'Pencatatan pelanggaran oleh piket',
            'follow_up_target' => 'selesai_di_piket',
            'status' => 'tercatat',
            'verification_status' => 'menunggu_verifikasi',
            'recorded_by' => $recorder->id,
        ], $attributes));
    }

    /**
     * Verifikasi 1: Anggota Tim Kedisiplinan dengan penugasan aktif dapat menjalankan tindakan yang diizinkan.
     */
    public function test_active_discipline_team_member_can_perform_authorized_actions(): void
    {
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', [
            'access_piket',
            'manage_piket_cases',
            'manage_discipline',
            'operate_student_affairs',
        ]);

        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($disiplinRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $incident = $this->createIncident($tatibUser, $student);

        // Can record / create incident
        $this->assertTrue($tatibUser->canRecordPiketIncident('2026/2027'));

        // Can manage piket cases
        $this->assertTrue($tatibUser->canManagePiketCases('2026/2027'));

        // Can verify discipline and impose sanctions
        $this->assertTrue($tatibUser->canVerifyDiscipline('2026/2027'));

        // Verify via endpoint
        $response = $this->actingAs($tatibUser)->post(route('piket.incidents.verify', $incident->id), [
            'verification_status' => 'terverifikasi',
            'verification_notes' => 'Terbukti melanggar aturan tata tertib',
            'disciplinary_sanction' => 'Peringatan tertulis dan pembinaan kedisiplinan',
            'parental_involvement' => 1,
            'parental_involvement_notes' => 'Panggilan orang tua dijadwalkan',
        ]);

        $response->assertRedirect();
        $incident->refresh();
        $this->assertTrue($incident->isVerified());
        $this->assertEquals('terverifikasi', $incident->verification_status);
        $this->assertEquals('Peringatan tertulis dan pembinaan kedisiplinan', $incident->disciplinary_sanction);

        // Can export parental summons letter
        $pdfRes = $this->actingAs($tatibUser)->get(route('piket.incidents.surat_panggilan', $incident->id));
        $pdfRes->assertOk();
    }

    /**
     * Verifikasi 2: Pengguna tanpa penugasan aktif tidak memperoleh kewenangan Tim Kedisiplinan.
     */
    public function test_user_without_active_assignment_has_no_discipline_authority(): void
    {
        $teacher = User::factory()->create(['position' => 'guru']);
        $student = $this->createStudent();
        $incident = $this->createIncident($teacher, $student);

        $this->assertFalse($teacher->canManageDiscipline('2026/2027'));
        $this->assertFalse($teacher->canVerifyDiscipline('2026/2027'));

        // Verify endpoint must return 403 Forbidden
        $response = $this->actingAs($teacher)->post(route('piket.incidents.verify', $incident->id), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Sanksi ilegal',
        ]);
        $response->assertForbidden();

        // Print summons letter must return 403 Forbidden
        $pdfRes = $this->actingAs($teacher)->get(route('piket.incidents.surat_panggilan', $incident->id));
        $pdfRes->assertForbidden();
    }

    /**
     * Verifikasi 3: Guru BK murni tetap dapat menjalankan fungsi BK, tetapi TIDAK dapat memverifikasi kedisiplinan.
     */
    public function test_pure_guru_bk_can_operate_bk_but_cannot_verify_discipline(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['access_piket', 'access_bk']);
        $guruBk = User::factory()->create(['position' => 'guru']);
        $guruBk->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $recorder = User::factory()->create(['position' => 'guru']);
        $incident = $this->createIncident($recorder, $student);

        // Guru BK operates BK and sees counseling notes
        $this->assertTrue($guruBk->canOperateBk('2026/2027'));
        $this->assertTrue($guruBk->canAccessCounselingNotes('2026/2027'));

        // Guru BK CANNOT verify discipline
        $this->assertFalse($guruBk->canVerifyDiscipline('2026/2027'));
        $this->assertFalse($guruBk->canManageDiscipline('2026/2027'));

        $response = $this->actingAs($guruBk)->post(route('piket.incidents.verify', $incident->id), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Sanksi oleh BK (ilegal)',
        ]);
        $response->assertForbidden();
    }

    /**
     * Verifikasi 4: Anggota Tim Kedisiplinan yang bukan Guru BK tidak memperoleh akses catatan rahasia BK.
     */
    public function test_discipline_team_member_cannot_access_bk_confidential_notes(): void
    {
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', [
            'access_piket',
            'manage_piket_cases',
            'manage_discipline',
        ]);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($disiplinRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $counselor = User::factory()->create(['position' => 'guru']);

        $service = BkService::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'academic_year' => '2026/2027',
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Keluarga dan trauma masa lalu',
            'result' => 'Siswa menceritakan masalah keluarga',
            'status' => 'selesai',
        ]);

        BkConfidentialNote::create([
            'bk_service_id' => $service->id,
            'notes' => 'Catatan sangat rahasia kondisi psikologis siswa',
            'created_by' => $counselor->id,
        ]);

        $this->assertFalse($tatibUser->canOperateBk('2026/2027'));
        $this->assertFalse($tatibUser->canAccessCounselingNotes('2026/2027'));

        // Accessing BK service detail must return 403 Forbidden
        $response = $this->actingAs($tatibUser)->get(route('bk.services.show', $service->id));
        $response->assertForbidden();
    }

    /**
     * Verifikasi 5: Personel dengan multi-penugasan (Guru BK + Tim Kedisiplinan) dapat menjalankan kedua fungsi.
     */
    public function test_multi_assigned_personnel_can_execute_both_roles(): void
    {
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['access_piket', 'access_bk']);
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_piket_cases', 'manage_discipline']);

        $user = User::factory()->create(['position' => 'guru']);
        $user->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);
        $user->assignRole($disiplinRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $incident = $this->createIncident($user, $student);

        // Can operate both BK and Discipline
        $this->assertTrue($user->canOperateBk('2026/2027'));
        $this->assertTrue($user->canAccessCounselingNotes('2026/2027'));
        $this->assertTrue($user->canVerifyDiscipline('2026/2027'));
        $this->assertTrue($user->canManageDiscipline('2026/2027'));

        // Can verify incident
        $verifyRes = $this->actingAs($user)->post(route('piket.incidents.verify', $incident->id), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Tugas sosial di perpustakaan',
            'follow_up_target' => 'rujuk_bk',
        ]);
        $verifyRes->assertRedirect();
        $this->assertDatabaseHas('bk_referrals', ['piket_incident_id' => $incident->id]);
    }

    /**
     * Verifikasi 6: Pembatasan tahun ajaran bekerja tepat (penugasan lampau tidak memberi hak tahun aktif).
     */
    public function test_expired_or_past_academic_year_assignment_revokes_authority(): void
    {
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['manage_discipline']);
        $user = User::factory()->create(['position' => 'guru']);

        // Assigned only in past academic year 2025/2026
        $user->assignRole($disiplinRole, ['academic_year' => '2025/2026', 'status' => true]);

        $student = $this->createStudent('2026/2027');
        $incident = $this->createIncident($user, $student, ['academic_year' => '2026/2027']);

        $this->assertTrue($user->canVerifyDiscipline('2025/2026'));
        $this->assertFalse($user->canVerifyDiscipline('2026/2027'));

        $response = $this->actingAs($user)->post(route('piket.incidents.verify', $incident->id), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Sanksi tidak sah',
        ]);
        $response->assertForbidden();
    }

    /**
     * Verifikasi 7: Transisi status kejadian terlindungi di backend.
     */
    public function test_incident_status_transitions_are_strictly_guarded(): void
    {
        $user = User::factory()->create(['position' => 'guru']);
        $student = $this->createStudent();
        $incident = $this->createIncident($user, $student, ['status' => 'selesai']);

        // Standard user without discipline authority cannot reopen a completed case
        $response = $this->actingAs($user)->post(route('piket.incidents.status', $incident->id), [
            'status' => 'ditindaklanjuti',
        ]);
        $response->assertForbidden();

        // But Discipline Team member can reopen it
        $disiplinRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_piket_cases', 'manage_discipline']);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($disiplinRole, ['academic_year' => '2026/2027', 'status' => true]);

        $reopenRes = $this->actingAs($tatibUser)->post(route('piket.incidents.status', $incident->id), [
            'status' => 'ditindaklanjuti',
        ]);
        $reopenRes->assertRedirect();
        $incident->refresh();
        $this->assertEquals('ditindaklanjuti', $incident->status);
    }

    /**
     * Verifikasi 8: Perlindungan penghapusan rekaman yang telah terverifikasi.
     */
    public function test_verified_incidents_cannot_be_deleted_by_regular_staff(): void
    {
        $recorder = User::factory()->create(['position' => 'guru']);
        $student = $this->createStudent();
        $incident = $this->createIncident($recorder, $student, [
            'verification_status' => 'terverifikasi',
            'status' => 'ditindaklanjuti',
        ]);

        // Recorder cannot delete once verified
        $delRes = $this->actingAs($recorder)->delete(route('piket.incidents.destroy', $incident->id));
        $delRes->assertForbidden();

        // Super Admin can delete
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $adminDelRes = $this->actingAs($admin)->delete(route('piket.incidents.destroy', $incident->id));
        $adminDelRes->assertRedirect();
        $this->assertDatabaseMissing('piket_incidents', ['id' => $incident->id]);
    }

    /**
     * Verifikasi 9: Tenaga Kependidikan (Satpam / Staf Piket) dapat mencatat kejadian saat bertugas.
     */
    public function test_tendik_satpam_with_piket_assignment_can_record_incident(): void
    {
        $stafRole = $this->getOrCreateRole('staf', 'Staf / Tendik');
        $piketRole = $this->getOrCreateRole('guru_piket', 'Petugas Piket', ['access_piket', 'operate_piket', 'manage_piket_cases']);

        $satpam = User::factory()->create([
            'role_id' => $stafRole->id,
            'position' => 'satpam',
        ]);
        $satpam->assignRole($piketRole, ['academic_year' => '2026/2027', 'status' => true]);

        $this->assertTrue($satpam->canRecordPiketIncident('2026/2027'));

        $student = $this->createStudent();

        $response = $this->actingAs($satpam)->post(route('piket.incidents.store'), [
            'incident_date' => now()->toDateString(),
            'incident_time' => '07:15:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $student->classroom_id,
            'category' => 'seragam',
            'description' => 'Tidak memakai dasi dan topi saat upacara',
            'action_taken' => 'Pencatatan pelanggaran oleh satpam',
            'follow_up_target' => 'selesai_di_piket',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('piket_incidents', [
            'student_id' => $student->id,
            'recorded_by' => $satpam->id,
            'category' => 'seragam',
        ]);
    }

    /**
     * Verifikasi UI 1: Menu Tim Kedisiplinan hanya tampil bagi pengguna berwenang.
     */
    public function test_ui_menu_tim_kedisiplinan_visibility_matches_user_authority(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_piket_cases', 'manage_discipline']);
        $bkRole = $this->getOrCreateRole('guru_bk', 'Guru BK', ['access_bk', 'operate_bk']);
        $guruRole = $this->getOrCreateRole('guru', 'Guru Mata Pelajaran');

        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $bkUser = User::factory()->create(['position' => 'guru']);
        $bkUser->assignRole($bkRole, ['academic_year' => '2026/2027', 'status' => true]);

        $regularUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        // Tim Kedisiplinan: melihat menu Tim Kedisiplinan, TIDAK melihat menu BK
        $tatibRes = $this->actingAs($tatibUser)->get(route('piket.incidents.index'));
        $tatibRes->assertOk();
        $tatibRes->assertSee('Tim Kedisiplinan');
        $tatibRes->assertDontSee('Bimbingan & Konseling');

        // Guru BK murni: melihat menu BK, TIDAK melihat menu Tim Kedisiplinan
        $bkRes = $this->actingAs($bkUser)->get(route('bk.referrals.index'));
        $bkRes->assertOk();
        $bkRes->assertSee('Bimbingan & Konseling');
        $bkRes->assertDontSee('Tim Kedisiplinan');

        // Guru reguler: tidak melihat menu Tim Kedisiplinan maupun BK
        $regRes = $this->actingAs($regularUser)->get(route('dashboard'));
        $regRes->assertOk();
        $regRes->assertDontSee('Tim Kedisiplinan');
        $regRes->assertDontSee('Bimbingan & Konseling');
    }

    /**
     * Verifikasi UI 2: Halaman desktop merender ringkasan metrik statistik dan modal detail kasus.
     */
    public function test_ui_desktop_renders_discipline_dashboard_metrics_and_detail_modal(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_piket_cases', 'manage_discipline']);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $incident = $this->createIncident($tatibUser, $student, [
            'category' => 'gadget_terlarang',
            'description' => 'Memainkan game online saat KBM matematika',
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Penyitaan HP selama 3 hari dan surat peringatan 1',
        ]);

        $response = $this->actingAs($tatibUser)->get(route('piket.incidents.index'));
        $response->assertOk();

        // Cek metrik statistik dashboard
        $response->assertSee('Total Kasus');
        $response->assertSee('Terverifikasi');
        $response->assertSee('Perlu Verifikasi');

        // Cek filter form terpadu
        $response->assertSee('verification_status');
        $response->assertSee('classroom_id');

        // Cek tombol dan modal detail
        $response->assertSee('modal-detail-'.$incident->id);
        $response->assertSee('Penyitaan HP selama 3 hari');
        $response->assertSee('Memainkan game online saat KBM matematika');

        // Cek tombol Verifikasi muncul untuk Tim Kedisiplinan
        $response->assertSee('modal-verify-'.$incident->id);
    }

    /**
     * Verifikasi UI 3: Tampilan mobile merender status verifikasi dan sheet modal verifikasi.
     */
    public function test_ui_mobile_renders_verification_badge_and_sheet_modal(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_piket_cases', 'manage_discipline']);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $incident = $this->createIncident($tatibUser, $student, [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Tugas edukatif di perpustakaan',
        ]);

        $response = $this->actingAs($tatibUser)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; Mobile)'])
            ->get(route('piket.incidents.index'));

        $response->assertOk();
        $response->assertSee('Terverifikasi');
        $response->assertSee('Tugas edukatif di perpustakaan');
        $response->assertSee('sheet-verify-incident');
        $response->assertSee('openVerifyIncidentSheet');
    }

    /**
     * Verifikasi UI 4: Halaman penugasan pegawai merender pintasan Tim Kedisiplinan dan peran lintas modul.
     */
    public function test_ui_employee_assignments_renders_discipline_team_pills_and_roles(): void
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', ['access_piket', 'manage_discipline']);
        $this->getOrCreateRole('koordinator_kedisiplinan', 'Koordinator Kedisiplinan', ['access_piket', 'manage_discipline']);

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $response = $this->actingAs($admin)->get(route('employee-assignments.index'));
        $response->assertOk();
        $response->assertSee('Tim Kedisiplinan');
        $response->assertSee('Koordinator Kedisiplinan');
        $response->assertSee('Pintasan Penugasan');
    }

    /**
     * Verifikasi UI 5: Sidebar navigasi Tim Kedisiplinan dan submenunya bekerja konsisten tanpa bentrok dengan Piket Sekolah.
     */
    public function test_ui_sidebar_tim_kedisiplinan_menu_and_submenus_active_state_behavior(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', [
            'access_piket',
            'manage_piket_cases',
            'manage_discipline',
            'view_piket_reports',
            'record_piket_incidents',
            'access_visitations',
        ]);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        // 1. Saat di halaman Kasus & Pelanggaran (piket.incidents.index)
        $response = $this->actingAs($tatibUser)->get(route('piket.incidents.index'));
        $response->assertOk();

        // Menu Tim Kedisiplinan tampil beserta submenunya
        $response->assertSee('Tim Kedisiplinan');
        $response->assertSee('Kasus &amp; Pelanggaran', false);
        $response->assertSee('Rekap Pelanggaran');

        // Tombol aksi "Catat Kasus Baru" ada di header halaman, bukan di sub-menu sidebar
        $response->assertSee('Catat Kasus Baru');

        // Tim Kedisiplinan aktif
        $response->assertSee('href="#navbar-tatib"', false);

        // Menu Visitasi & Pemantauan tampil sebagai menu mandiri di atas Piket Sekolah
        $response->assertSee('Visitasi &amp; Pemantauan', false);
        $posVisitasi = strpos($response->getContent(), 'href="'.route('visitations.index').'"');
        $posPiket = strpos($response->getContent(), 'href="#navbar-piket"');
        $this->assertNotFalse($posVisitasi);
        $this->assertNotFalse($posPiket);
        $this->assertTrue($posVisitasi < $posPiket, 'Menu Visitasi & Pemantauan harus terletak di atas Piket Sekolah');

        // 2. Saat di Rekap Pelanggaran (piket.reports.index?tab=incidents)
        $reportResponse = $this->actingAs($tatibUser)->get(route('piket.reports.index', ['tab' => 'incidents']));
        $reportResponse->assertOk();
        $reportResponse->assertSee('Rekapitulasi Kasus & Kejadian Siswa', false);

        // Submenu rekap pelanggaran aktif
        $reportResponse->assertSee('Rekap Pelanggaran');

        // 3. Pastikan visitasi tidak dirender dua kali di sidebar untuk Tim Kedisiplinan
        $visitationCount = substr_count($response->getContent(), 'href="'.route('visitations.index').'"');
        $this->assertEquals(1, $visitationCount, 'Visitasi & Pemantauan hanya boleh dirender 1 kali pada sidebar Tim Kedisiplinan');
    }

    /**
     * Verifikasi UI 6: Rekapitulasi Pelanggaran menampilkan kolom verifikasi kedisiplinan, sanksi, dan cetak.
     */
    public function test_ui_piket_reports_incidents_tab_renders_discipline_verification_and_sanction(): void
    {
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan', [
            'access_piket',
            'manage_discipline',
            'view_piket_reports',
        ]);
        $tatibUser = User::factory()->create(['position' => 'guru']);
        $tatibUser->assignRole($tatibRole, ['academic_year' => '2026/2027', 'status' => true]);

        $student = $this->createStudent();
        $incident = $this->createIncident($tatibUser, $student, [
            'description' => 'Membawa vape di lingkungan sekolah',
            'confiscated_items' => '1 unit Vape Pod',
            'disciplinary_sanction' => 'Panggilan orang tua dan skorsing 2 hari',
            'verification_status' => 'terverifikasi',
            'verified_by' => $tatibUser->id,
            'verified_at' => now(),
            'status' => 'proses',
        ]);

        // Cek halaman index laporan tab incidents
        $response = $this->actingAs($tatibUser)->get(route('piket.reports.index', ['tab' => 'incidents']));
        $response->assertOk();
        $response->assertSee('Sanksi Kedisiplinan');
        $response->assertSee('Status &amp; Verifikasi', false);
        $response->assertSee('Panggilan orang tua dan skorsing 2 hari');
        $response->assertSee('1 unit Vape Pod');
        $response->assertSee('Terverifikasi');

        // Cek export CSV incidents
        $exportResponse = $this->actingAs($tatibUser)->get(route('piket.reports.export', ['tab' => 'incidents']));
        $exportResponse->assertOk();
        $this->assertStringContainsString('rekap-piket-incidents', (string) $exportResponse->headers->get('content-disposition'));

        // Cek print format incidents
        $printResponse = $this->actingAs($tatibUser)->get(route('piket.reports.print', ['tab' => 'incidents']));
        $printResponse->assertOk();
        $printResponse->assertSee('Sanksi / Tindakan');
        $printResponse->assertSee('Panggilan orang tua dan skorsing 2 hari');
        $printResponse->assertSee('1 unit Vape Pod');
        $printResponse->assertSee('Terverifikasi');
    }
}
