<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BkConfidentialNote;
use App\Models\BkReferral;
use App\Models\BkService;
use App\Models\BkStudentAchievement;
use App\Models\BkStudentAssessment;
use App\Models\BkStudentCareerProfile;
use App\Models\BkStudentDevelopmentNote;
use App\Models\BkStudentDocument;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\PiketIncident;
use App\Models\PiketJournal;
use App\Models\PiketStudentPermit;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentClassroom;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BkOperationalIntegrationTest extends TestCase
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

    private function createStudent(string $academicYear, string $name = 'Ahmad Siswa Test'): Student
    {
        $ay = AcademicYear::firstOrCreate(
            ['name' => $academicYear],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI TKJ 1'],
            ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        return Student::create([
            'nisn' => (string) fake()->unique()->numerify('##########'),
            'name' => $name,
            'classroom_id' => $classroom->id,
            'academic_year' => $ay->name,
            'status' => 'aktif',
        ]);
    }

    private function createIncident(string $academicYear, string $followUpTarget = 'rujuk_bk', ?Student $student = null): PiketIncident
    {
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $ay = AcademicYear::firstOrCreate(
            ['name' => $academicYear],
            ['semester' => 'Ganjil', 'is_active' => true]
        );

        $grade = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI TKJ 1'],
            ['academic_year' => $ay->name, 'grade_level_id' => $grade->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        if (! $student) {
            $student = $this->createStudent($academicYear);
        }

        $today = now()->toDateString();
        $journal = PiketJournal::whereDate('date', $today)->first();
        if (! $journal) {
            $journal = PiketJournal::create([
                'date' => $today,
                'academic_year' => $ay->name,
                'semester' => 'Ganjil',
                'status' => 'berlangsung',
                'coordinator_id' => $admin->id,
            ]);
        }

        return PiketIncident::create([
            'piket_journal_id' => $journal->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:15',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat berulang dan terlihat murung',
            'action_taken' => 'Pembinaan meja piket dan rujukan ke BK',
            'follow_up_target' => $followUpTarget,
            'recorded_by' => $admin->id,
            'status' => 'tercatat',
        ]);
    }

    public function test_guru_bk_can_view_valid_bk_referrals_and_filter_by_status_and_academic_year(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incidentBk = $this->createIncident($academicYear, 'rujuk_bk');
        $incidentKesiswaan = $this->createIncident($academicYear, 'rujuk_kesiswaan');

        // Buka dashboard rujukan BK
        $response = $this->actingAs($guruBk)->get(route('bk.referrals.index', ['academic_year' => $academicYear]));
        $response->assertStatus(200);

        // Hanya kasus rujuk_bk yang tampil
        $response->assertSee($incidentBk->student_name);
        $response->assertSee('Rujukan Kasus Siswa ke Bimbingan & Konseling');

        // Kasus rujuk_kesiswaan tidak masuk dalam daftar rujukan BK
        $incidentsInView = $response->viewData('incidents');
        $this->assertTrue($incidentsInView->pluck('id')->contains($incidentBk->id));
        $this->assertFalse($incidentsInView->pluck('id')->contains($incidentKesiswaan->id));
    }

    public function test_non_bk_user_cannot_access_bk_referrals_or_services(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        // Tim Kedisiplinan murni (bukan BK)
        $tatibUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => $academicYear,
        ]);

        // Guru biasa
        $regularTeacher = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        // Akses rujukan BK harus 403 Forbidden
        $this->actingAs($tatibUser)->get(route('bk.referrals.index', ['academic_year' => $academicYear]))->assertForbidden();
        $this->actingAs($regularTeacher)->get(route('bk.referrals.index', ['academic_year' => $academicYear]))->assertForbidden();

        // Akses layanan BK harus 403 Forbidden
        $this->actingAs($tatibUser)->get(route('bk.services.index', ['academic_year' => $academicYear]))->assertForbidden();
        $this->actingAs($regularTeacher)->get(route('bk.services.index', ['academic_year' => $academicYear]))->assertForbidden();
    }

    public function test_guru_bk_can_update_bk_referral_status_without_changing_piket_status(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');
        $this->assertEquals('tercatat', $incident->status);
        $this->assertEquals('menunggu', $incident->bk_follow_up_status);

        // Guru BK memperbarui status tindak lanjut BK menjadi 'diproses'
        $response = $this->actingAs($guruBk)->post(route('bk.referrals.status', $incident), [
            'status' => 'diproses',
            'notes' => 'Siswa dipanggil ke ruang BK untuk bimbingan awal',
        ]);

        $response->assertRedirect(route('bk.referrals.show', $incident));

        // Verifikasi tabel bk_referrals terisi dan berubah
        $referral = BkReferral::where('piket_incident_id', $incident->id)->first();
        $this->assertNotNull($referral);
        $this->assertEquals('diproses', $referral->status);
        $this->assertEquals($guruBk->id, $referral->handled_by);
        $this->assertNull($referral->completed_at);

        // PENTING: Status operasional piket tetap 'tercatat' (tidak terubah secara sepihak)
        $incident->refresh();
        $this->assertEquals('tercatat', $incident->status);

        // Perbarui status menjadi 'selesai'
        $responseSelesai = $this->actingAs($guruBk)->post(route('bk.referrals.status', $incident), [
            'status' => 'selesai',
            'notes' => 'Bimbingan tuntas dengan komitmen siswa',
        ]);
        $responseSelesai->assertRedirect();

        $referral->refresh();
        $this->assertEquals('selesai', $referral->status);
        $this->assertNotNull($referral->completed_at);

        // Status piket tetap tidak terpengaruh
        $incident->refresh();
        $this->assertEquals('tercatat', $incident->status);
    }

    public function test_guru_bk_can_record_bk_service_linked_to_referral_and_student(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');

        $serviceData = [
            'student_id' => $incident->student_id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Konseling mengenai masalah motivasi belajar dan kedisiplinan pagi.',
            'result' => 'Siswa menyadari kendala begadang dan berjanji mengatur waktu tidur.',
            'follow_up_plan' => 'Cek kehadiran setiap hari Senin dan Kamis.',
        ];

        $response = $this->actingAs($guruBk)->post(route('bk.services.store'), $serviceData);
        $response->assertRedirect();

        $service = BkService::where('student_id', $incident->student_id)->first();
        $this->assertNotNull($service);
        $this->assertEquals('pribadi', $service->service_type);
        $this->assertEquals($incident->id, $service->piket_incident_id);
        $this->assertEquals($guruBk->id, $service->counselor_id);
        $this->assertEquals($academicYear, $service->academic_year);

        // Referral status otomatis berubah menjadi 'diproses' jika sebelumnya 'menunggu'
        $referral = BkReferral::where('piket_incident_id', $incident->id)->first();
        $this->assertNotNull($referral);
        $this->assertEquals('diproses', $referral->status);
    }

    public function test_single_referral_can_have_multiple_bk_service_sessions(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');

        // Sesi 1
        $this->actingAs($guruBk)->post(route('bk.services.store'), [
            'student_id' => $incident->student_id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Sesi awal identifikasi masalah',
            'result' => 'Identifikasi faktor keluarga',
            'follow_up_plan' => 'Pertemuan lanjutan lusa',
        ]);

        // Sesi 2
        $this->actingAs($guruBk)->post(route('bk.services.store'), [
            'student_id' => $incident->student_id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->addDays(2)->toDateString(),
            'service_type' => 'sosial',
            'summary' => 'Sesi lanjutan pendampingan',
            'result' => 'Siswa menunjukkan kemajuan sikap',
            'follow_up_plan' => 'Pemantauan berkala',
        ]);

        $incident->refresh();
        $this->assertCount(2, $incident->bkServices);
    }

    public function test_confidential_notes_created_and_accessible_only_by_authorized_counselor_or_admin(): void
    {
        $academicYear = '2026/2027';
        $adminRole = $this->getOrCreateRole('super_admin', 'Super Admin');
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'position' => 'administrator']);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');

        $confidentialContent = 'Catatan rahasia: Siswa memiliki trauma psikologis keluarga.';

        // Simpan sesi layanan beserta catatan rahasia
        $response = $this->actingAs($guruBk)->post(route('bk.services.store'), [
            'student_id' => $incident->student_id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Konseling pribadi mendalam',
            'result' => 'Siswa bersedia bercerita terbuka',
            'confidential_notes' => $confidentialContent,
        ]);
        $response->assertRedirect();

        $service = BkService::latest('id')->first();
        $this->assertNotNull($service->confidentialNote);
        $this->assertEquals($confidentialContent, $service->confidentialNote->notes);

        // Guru BK dapat membaca catatan rahasia pada halaman show
        $showRespBk = $this->actingAs($guruBk)->get(route('bk.services.show', $service));
        $showRespBk->assertOk();
        $showRespBk->assertSee($confidentialContent);

        // Super Admin dapat membaca catatan rahasia pada halaman show
        $showRespAdmin = $this->actingAs($admin)->get(route('bk.services.show', $service));
        $showRespAdmin->assertOk();
        $showRespAdmin->assertSee($confidentialContent);
    }

    public function test_unauthorized_user_cannot_read_or_tamper_confidential_notes(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $tatibRole = $this->getOrCreateRole('tim_kedisiplinan', 'Tim Kedisiplinan');

        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $tatibUser = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        UserRoleAssignment::create([
            'user_id' => $tatibUser->id,
            'role_id' => $tatibRole->id,
            'status' => true,
            'academic_year' => $academicYear,
        ]);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');

        $service = BkService::create([
            'student_id' => $incident->student_id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Konseling pribadi',
            'result' => 'Siswa didampingi',
            'counselor_id' => $guruBk->id,
        ]);

        BkConfidentialNote::create([
            'bk_service_id' => $service->id,
            'notes' => 'Catatan rahasia yang tidak boleh dibaca pihak lain',
            'created_by' => $guruBk->id,
        ]);

        // Anggota Tim Kedisiplinan mencoba membuka endpoint show layanan BK -> 403 Forbidden
        $this->actingAs($tatibUser)->get(route('bk.services.show', $service))->assertForbidden();

        // Anggota Tim Kedisiplinan mencoba membuka riwayat BK siswa -> 403 Forbidden
        $this->actingAs($tatibUser)->get(route('bk.students.history', $incident->student))->assertForbidden();

        // Anggota Tim Kedisiplinan mencoba mengirim request update catatan rahasia -> 403 Forbidden
        $this->actingAs($tatibUser)->put(route('bk.services.update', $service), [
            'service_date' => now()->toDateString(),
            'service_type' => 'pribadi',
            'summary' => 'Hack update',
            'result' => 'Hack update',
            'confidential_notes' => 'Diubah oleh pihak tidak berwenang',
        ])->assertForbidden();

        // Data catatan rahasia tetap tidak berubah
        $service->refresh();
        $this->assertEquals('Catatan rahasia yang tidak boleh dibaca pihak lain', $service->confidentialNote->notes);
    }

    public function test_bk_assignment_respects_academic_year(): void
    {
        $oldYear = '2025/2026';
        $newYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');

        $teacher = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($teacher, $oldYear); // Ditugaskan di tahun 2025/2026

        // Akses untuk tahun lama diizinkan
        $this->actingAs($teacher)->get(route('bk.referrals.index', ['academic_year' => $oldYear]))->assertOk();

        // Akses untuk tahun baru ditolak (403 Forbidden) karena penugasan tidak aktif di 2026/2027
        $this->actingAs($teacher)->get(route('bk.referrals.index', ['academic_year' => $newYear]))->assertForbidden();
    }

    public function test_student_counseling_history_displays_services_and_referred_incidents(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');
        $student = $incident->student;

        $service = BkService::create([
            'student_id' => $student->id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => now()->toDateString(),
            'service_type' => 'sosial',
            'summary' => 'Bimbingan adaptasi sosial siswa',
            'result' => 'Siswa dapat berinteraksi lebih baik',
            'counselor_id' => $guruBk->id,
        ]);

        $response = $this->actingAs($guruBk)->get(route('bk.students.history', $student));
        $response->assertOk();
        $response->assertSee($student->name);
        $response->assertSee('Bimbingan adaptasi sosial siswa');
        $response->assertSee('Riwayat Kasus Piket Dirujuk ke BK');
    }

    public function test_buku_pribadi_siswa_digital_tabs_and_data_display(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $incident = $this->createIncident($academicYear, 'rujuk_bk');
        $student = $incident->student;

        // Lengkapi data master siswa untuk biodata & keluarga
        $student->update([
            'father_name' => 'Bambang Sudarmono',
            'father_job' => 'Wiraswasta',
            'mother_name' => 'Siti Aminah',
            'mother_job' => 'Guru',
            'school_origin' => 'SMP Negeri 1 Surabaya',
            'height_cm' => 165,
            'weight_kg' => 55,
            'blood_type' => 'O',
            'kip_number' => 'KIP-998877',
        ]);

        // Tambah riwayat rombel & mutasi
        $ay = AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $classroom = Classroom::first() ?? Classroom::factory()->create();
        StudentClassroom::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $ay->id,
            'academic_year_name' => $academicYear,
            'classroom_name' => $classroom->name,
            'status' => 'aktif',
        ]);

        // Tambah catatan perizinan piket
        $today = now()->toDateString();
        $journal = PiketJournal::whereDate('date', $today)->first() ?? PiketJournal::create([
            'date' => $today,
            'academic_year' => $academicYear,
            'semester' => 'ganjil',
            'total_present' => 1,
            'notes' => 'Jurnal piket testing',
        ]);

        PiketStudentPermit::create([
            'piket_journal_id' => $journal->id,
            'permit_number' => 'IZIN-001/TEST',
            'permit_date' => $today,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'classroom_id' => $classroom->id,
            'permit_type' => 'terlambat',
            'reason' => 'Ban sepeda motor bocor',
            'action_taken' => 'Diberikan pembinaan singkat',
            'given_by' => $guruBk->id,
            'status' => 'diberikan',
        ]);

        // Tambahkan layanan konseling dengan catatan rahasia
        $service = BkService::create([
            'student_id' => $student->id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => $today,
            'service_type' => 'pribadi',
            'summary' => 'Sesi konseling penguatan motivasi',
            'result' => 'Siswa berkomitmen hadir tepat waktu',
            'counselor_id' => $guruBk->id,
        ]);

        BkConfidentialNote::create([
            'bk_service_id' => $service->id,
            'notes' => 'Catatan sangat rahasia konseling',
            'created_by' => $guruBk->id,
        ]);

        // Request oleh Guru BK berwenang
        $response = $this->actingAs($guruBk)->get(route('bk.students.history', $student));
        $response->assertOk();

        // 1. Verifikasi seluruh 8 tab tersedia di view Tabler UI
        $response->assertSee('Biodata &amp; Keluarga', false);
        $response->assertSee('Riwayat Kelas &amp; Mutasi', false);
        $response->assertSee('Minat &amp; Karier', false);
        $response->assertSee('Prestasi &amp; Kegiatan', false);
        $response->assertSee('Catatan Perkembangan', false);
        $response->assertSee('Layanan BK', false);
        $response->assertSee('Kedisiplinan &amp; Perizinan', false);
        $response->assertSee('Dokumen Pendukung', false);

        // 2. Verifikasi Data Tab Biodata & Keluarga
        $response->assertSee('Bambang Sudarmono');
        $response->assertSee('Siti Aminah');
        $response->assertSee('SMP Negeri 1 Surabaya');
        $response->assertSee('KIP-998877');
        $response->assertSee('165 cm');

        // 3. Verifikasi Data Tab Riwayat Kelas & Mutasi
        $response->assertSee($classroom->name);

        // 4. Verifikasi Data Tab Layanan BK & Catatan Rahasia (bisa dilihat oleh Guru BK berwenang)
        $response->assertSee('Sesi konseling penguatan motivasi');
        $response->assertSee('Catatan sangat rahasia konseling');

        // 5. Verifikasi Data Tab Kedisiplinan & Perizinan (Rujukan Piket & Tiket Izin)
        $response->assertSee('Ban sepeda motor bocor');
        $response->assertSee('IZIN-001/TEST');

        // 6. Verifikasi Tab 4, 5, 8 telah aktif dan tampil pada antarmuka
        $response->assertSee('Rekam Jejak Prestasi &amp; Kegiatan Siswa', false);
        $response->assertSee('Catatan Observasi Perkembangan Siswa Non-Kasus', false);
        $response->assertSee('Arsip Dokumen Pendukung &amp; Berkas Privat', false);

        // 7. Pengamanan akses: Pengguna non-BK / Wali kelas tanpa peran BK dilarang mengakses
        $waliRole = $this->getOrCreateRole('wali_kelas', 'Wali Kelas', ['access_piket']);
        $waliUser = User::factory()->create(['role_id' => $waliRole->id]);
        $this->actingAs($waliUser)->get(route('bk.students.history', $student))->assertForbidden();
    }

    public function test_guru_bk_can_store_and_update_student_career_profile(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear);

        // 1. Simpan Profil Baru
        $response = $this->actingAs($guruBk)->post(route('bk.students.career.store', $student), [
            'academic_year' => $academicYear,
            'hobbies' => 'Membaca buku teknologi dan coding',
            'talents' => 'Logika pemrograman dan matematika',
            'skills_to_develop' => 'Fullstack web development',
            'favorite_subjects' => 'Pemrograman Web dan Basis Data',
            'career_aspirations' => 'Software Engineer',
            'post_graduation_plan' => 'bekerja',
            'target_industry' => 'Industri IT & Software House',
            'target_job_position' => 'Junior Web Developer',
            'notes' => 'Siswa memiliki kemauan belajar mandiri yang sangat tinggi.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bk_student_career_profiles', [
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'post_graduation_plan' => 'bekerja',
            'career_aspirations' => 'Software Engineer',
            'target_industry' => 'Industri IT & Software House',
            'recorded_by' => $guruBk->id,
        ]);

        $this->assertEquals(1, BkStudentCareerProfile::where('student_id', $student->id)->where('academic_year', $academicYear)->count());

        // 2. Perbarui Profil yang Sama (Tanpa Duplikasi)
        $updateResponse = $this->actingAs($guruBk)->post(route('bk.students.career.store', $student), [
            'academic_year' => $academicYear,
            'hobbies' => 'Membaca buku teknologi dan coding',
            'talents' => 'Logika pemrograman dan matematika',
            'career_aspirations' => 'Lead Software Architect',
            'post_graduation_plan' => 'melanjutkan',
            'target_university' => 'Institut Teknologi Bandung',
            'target_study_program' => 'Teknik Informatika',
        ]);

        $updateResponse->assertRedirect();
        $this->assertEquals(1, BkStudentCareerProfile::where('student_id', $student->id)->where('academic_year', $academicYear)->count());

        $profile = BkStudentCareerProfile::where('student_id', $student->id)->where('academic_year', $academicYear)->first();
        $this->assertEquals('melanjutkan', $profile->post_graduation_plan);
        $this->assertEquals('Lead Software Architect', $profile->career_aspirations);
        $this->assertEquals('Institut Teknologi Bandung', $profile->target_university);
        $this->assertEquals($guruBk->id, $profile->updated_by);
    }

    public function test_different_academic_years_maintain_independent_career_profiles(): void
    {
        $year1 = '2025/2026';
        $year2 = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $year1], ['start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => false]);
        AcademicYear::firstOrCreate(['name' => $year2], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $year1);
        $this->assignGuruBk($guruBk, $year2);

        $student = $this->createStudent($year1);

        // Profil Kelas X (Tahun 2025/2026)
        $this->actingAs($guruBk)->post(route('bk.students.career.store', $student), [
            'academic_year' => $year1,
            'career_aspirations' => 'Mekanik Sepeda Motor',
            'post_graduation_plan' => 'bekerja',
            'target_industry' => 'Bengkel Resmi AHASS',
        ])->assertRedirect();

        // Profil Kelas XI (Tahun 2026/2027)
        $this->actingAs($guruBk)->post(route('bk.students.career.store', $student), [
            'academic_year' => $year2,
            'career_aspirations' => 'Pengusaha Bengkel Mandiri',
            'post_graduation_plan' => 'wirausaha',
            'entrepreneurship_type' => 'Jasa Servis & Modifikasi Motor',
        ])->assertRedirect();

        // Verifikasi kedua profil tersimpan mandiri
        $this->assertEquals(2, BkStudentCareerProfile::where('student_id', $student->id)->count());

        $profile1 = BkStudentCareerProfile::where('student_id', $student->id)->where('academic_year', $year1)->first();
        $this->assertEquals('bekerja', $profile1->post_graduation_plan);
        $this->assertEquals('Mekanik Sepeda Motor', $profile1->career_aspirations);

        $profile2 = BkStudentCareerProfile::where('student_id', $student->id)->where('academic_year', $year2)->first();
        $this->assertEquals('wirausaha', $profile2->post_graduation_plan);
        $this->assertEquals('Pengusaha Bengkel Mandiri', $profile2->career_aspirations);
    }

    public function test_career_profile_validation_rejects_invalid_plans(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear);

        $response = $this->actingAs($guruBk)->post(route('bk.students.career.store', $student), [
            'academic_year' => $academicYear,
            'post_graduation_plan' => 'invalid_plan_value',
        ]);

        $response->assertSessionHasErrors(['post_graduation_plan']);
        $this->assertEquals(0, BkStudentCareerProfile::where('student_id', $student->id)->count());
    }

    public function test_guru_bk_can_store_and_delete_assessments_with_cross_student_protection(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $studentA = $this->createStudent($academicYear, 'Siswa A Test');
        $studentB = $this->createStudent($academicYear, 'Siswa B Test');

        // 1. Tambah Asesmen 1 untuk Siswa A
        $this->actingAs($guruBk)->post(route('bk.students.assessments.store', $studentA), [
            'academic_year' => $academicYear,
            'assessment_date' => '2026-08-15',
            'assessment_name' => 'Tes Minat Bakat RIASEC',
            'assessor_name' => 'Tim Psikologi Terapan',
            'result_summary' => 'Dominan tipe Realistic dan Investigative',
            'recommended_career_paths' => 'Teknik Elektro, Pemrograman, Mekatronika',
        ])->assertRedirect();

        // 2. Tambah Asesmen 2 untuk Siswa A
        $this->actingAs($guruBk)->post(route('bk.students.assessments.store', $studentA), [
            'academic_year' => $academicYear,
            'assessment_date' => '2026-10-10',
            'assessment_name' => 'Tes Gaya Belajar VAK',
            'assessor_name' => 'Guru BK',
            'result_summary' => 'Dominan tipe Kinestetik 60%, Visual 30%',
        ])->assertRedirect();

        $this->assertEquals(2, BkStudentAssessment::where('student_id', $studentA->id)->count());

        $assessmentA = BkStudentAssessment::where('student_id', $studentA->id)->first();

        // 3. Upaya Manipulasi ID Siswa: Hapus asesmen milik Siswa A melalui rute Siswa B -> 404 Not Found
        $this->actingAs($guruBk)
            ->delete(route('bk.students.assessments.destroy', ['student' => $studentB, 'assessment' => $assessmentA]))
            ->assertNotFound();

        // 4. Hapus asesmen yang sah oleh Siswa A
        $this->actingAs($guruBk)
            ->delete(route('bk.students.assessments.destroy', ['student' => $studentA, 'assessment' => $assessmentA]))
            ->assertRedirect();

        $this->assertEquals(1, BkStudentAssessment::where('student_id', $studentA->id)->count());
    }

    public function test_unauthorized_user_cannot_modify_career_profiles_or_assessments(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $waliRole = $this->getOrCreateRole('wali_kelas', 'Wali Kelas');
        $unauthorizedUser = User::factory()->create(['role_id' => $waliRole->id]);

        $student = $this->createStudent($academicYear);

        // Coba simpan profil -> 403 Forbidden
        $this->actingAs($unauthorizedUser)->post(route('bk.students.career.store', $student), [
            'academic_year' => $academicYear,
            'career_aspirations' => 'Hacker',
        ])->assertForbidden();

        // Coba tambah asesmen -> 403 Forbidden
        $this->actingAs($unauthorizedUser)->post(route('bk.students.assessments.store', $student), [
            'academic_year' => $academicYear,
            'assessment_date' => '2026-08-15',
            'assessment_name' => 'Hacking Test',
            'result_summary' => 'Failed',
        ])->assertForbidden();
    }

    public function test_tab_3_renders_stored_career_data_and_never_leaks_confidential_notes(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear);

        // Buat profil karier
        BkStudentCareerProfile::create([
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'hobbies' => 'Aeromodelling dan Robotika',
            'talents' => 'Desain CAD 3D',
            'career_aspirations' => 'Teknisi Pesawat Terbang',
            'post_graduation_plan' => 'bekerja',
            'target_industry' => 'PT Dirgantara Indonesia',
            'target_job_position' => 'Assembly Aircraft Technician',
            'notes' => 'Siswa dipersiapkan untuk uji sertifikasi BNSP',
            'recorded_by' => $guruBk->id,
        ]);

        // Buat hasil asesmen
        BkStudentAssessment::create([
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'assessment_date' => '2026-09-01',
            'assessment_name' => 'Asesmen Bakat Mekanik Dasar',
            'assessor_name' => 'Biro Psikologi Dirgantara',
            'result_summary' => 'Skor spasial dan mekanik sangat tinggi (persentil 92)',
            'recommended_career_paths' => 'Teknik Penerbangan, Teknik Mesin',
            'recorded_by' => $guruBk->id,
        ]);

        // Buka halaman Buku Pribadi Siswa
        $response = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $response->assertOk();

        // Verifikasi data Tab 3 tampil
        $response->assertSee('Aeromodelling dan Robotika');
        $response->assertSee('Desain CAD 3D');
        $response->assertSee('Teknisi Pesawat Terbang');
        $response->assertSee('PT Dirgantara Indonesia');
        $response->assertSee('Assembly Aircraft Technician');
        $response->assertSee('Asesmen Bakat Mekanik Dasar');
        $response->assertSee('Skor spasial dan mekanik sangat tinggi');
        $response->assertSee('Teknik Penerbangan, Teknik Mesin');
    }

    public function test_sidebar_contains_correct_structure_and_no_catat_layanan_baru_link(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $response = $this->actingAs($guruBk)->get(route('bk.referrals.index'));
        $response->assertOk();

        // Menu sidebar harus menampilkan Rujukan Piket, Sesi Layanan BK, dan Buku Pribadi Siswa
        $response->assertSee('Bimbingan &amp; Konseling', false);
        $response->assertSee('Rujukan Piket');
        $response->assertSee('Sesi Layanan BK');
        $response->assertSee('Buku Pribadi Siswa');
        $response->assertSee(route('bk.students.index'));

        // Sidebar TIDAK boleh memuat menu Catat Layanan Baru
        $response->assertDontSee('<a class="dropdown-item" href="'.route('bk.services.create').'">Catat Layanan Baru</a>', false);
        $response->assertDontSee('Catat Layanan Baru'."\n".'                                </a>', false);
    }

    public function test_buku_pribadi_siswa_index_page_renders_cleanly(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Putri Ananda');

        $response = $this->actingAs($guruBk)->get(route('bk.students.index', ['academic_year' => $academicYear]));
        $response->assertOk();
        $response->assertSee('Buku Pribadi Siswa Digital');
        $response->assertSee('PUTRI ANANDA');
        $response->assertSee(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
    }

    public function test_sesi_layanan_bk_page_has_functional_catat_layanan_baru_button(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        // Buka halaman Sesi Layanan BK
        $response = $this->actingAs($guruBk)->get(route('bk.services.index', ['academic_year' => $academicYear]));
        $response->assertOk();

        // Pastikan tombol Catat Layanan Baru ada di halaman dan mengarah ke route('bk.services.create')
        $response->assertSee('Catat Layanan Baru');
        $response->assertSee(route('bk.services.create', ['academic_year' => $academicYear]));

        // Buka formulir Catat Layanan Baru
        $createResponse = $this->actingAs($guruBk)->get(route('bk.services.create', ['academic_year' => $academicYear]));
        $createResponse->assertOk();
        $createResponse->assertSee('Formulir Pencatatan Sesi Layanan Bimbingan &amp; Konseling', false);
        $createResponse->assertSee('Siswa yang Dibimbing');
    }

    public function test_rujukan_piket_filters_without_sql_errors(): void
    {
        $academicYear = '2026/2027';
        $incident = $this->createIncident($academicYear, 'rujuk_bk');

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $response = $this->actingAs($guruBk)->get(route('bk.referrals.index', ['academic_year' => $academicYear]));
        $response->assertOk();
        $response->assertSee($incident->student_name);
    }

    public function test_tab_4_guru_bk_can_record_and_delete_achievement(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Budi Santoso');

        $payload = [
            'academic_year' => $academicYear,
            'event_date' => '2026-09-15',
            'type' => 'prestasi',
            'category' => 'akademik',
            'level' => 'provinsi',
            'title' => 'Juara 1 Olimpiade Sains Terapan',
            'achievement_result' => 'Juara 1 / Medali Emas',
            'organizer' => 'Dinas Pendidikan Jawa Barat',
            'certificate_number' => '421.3/99/O2SN/2026',
            'notes' => 'Mewakili sekolah ke tingkat nasional.',
        ];

        $response = $this->actingAs($guruBk)->post(route('bk.students.achievements.store', $student), $payload);
        $response->assertRedirect(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bk_student_achievements', [
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'title' => 'Juara 1 Olimpiade Sains Terapan',
            'level' => 'provinsi',
            'recorded_by' => $guruBk->id,
        ]);

        $achievement = BkStudentAchievement::where('student_id', $student->id)->first();

        // View verification on history page
        $viewResponse = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Juara 1 Olimpiade Sains Terapan');
        $viewResponse->assertSee('Tingkat Provinsi');

        // Delete test
        $delResponse = $this->actingAs($guruBk)->delete(route('bk.students.achievements.destroy', [$student, $achievement]));
        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('bk_student_achievements', ['id' => $achievement->id]);
    }

    public function test_tab_4_unauthorized_user_cannot_record_achievement(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBiasa = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        $student = $this->createStudent($academicYear, 'Citra Lestari');

        $payload = [
            'academic_year' => $academicYear,
            'event_date' => '2026-09-15',
            'type' => 'prestasi',
            'category' => 'akademik',
            'level' => 'sekolah',
            'title' => 'Juara Kelas',
        ];

        $response = $this->actingAs($guruBiasa)->post(route('bk.students.achievements.store', $student), $payload);
        $response->assertForbidden();
    }

    public function test_tab_5_guru_bk_can_record_and_delete_development_note(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Dedi Firmansyah');

        $payload = [
            'academic_year' => $academicYear,
            'note_date' => '2026-10-01',
            'category' => 'sikap_belajar',
            'title' => 'Peningkatan Konsentrasi Belajar Teori',
            'description' => 'Siswa menunjukkan antusiasme tinggi dalam berdiskusi kelompok dan menyelesaikan tugas tepat waktu.',
            'follow_up_recommendation' => 'Berikan apresiasi dan dorong menjadi tutor sebaya.',
        ];

        $response = $this->actingAs($guruBk)->post(route('bk.students.development-notes.store', $student), $payload);
        $response->assertRedirect(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bk_student_development_notes', [
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'title' => 'Peningkatan Konsentrasi Belajar Teori',
            'category' => 'sikap_belajar',
            'recorded_by' => $guruBk->id,
        ]);

        $note = BkStudentDevelopmentNote::where('student_id', $student->id)->first();

        // View verification on history page
        $viewResponse = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Peningkatan Konsentrasi Belajar Teori');
        $viewResponse->assertSee('Berikan apresiasi dan dorong menjadi tutor sebaya.');

        // Delete test
        $delResponse = $this->actingAs($guruBk)->delete(route('bk.students.development-notes.destroy', [$student, $note]));
        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('bk_student_development_notes', ['id' => $note->id]);
    }

    public function test_tab_5_catatan_perkembangan_strictly_does_not_leak_confidential_counseling_notes(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Eka Prasetya');

        // Create a counseling service with secret confidential note
        $service = BkService::create([
            'student_id' => $student->id,
            'counselor_id' => $guruBk->id,
            'academic_year' => $academicYear,
            'service_date' => '2026-10-10',
            'service_type' => 'pribadi',
            'summary' => 'Konseling penyesuaian diri',
            'result' => 'Siswa merasa lebih tenang',
        ]);

        BkConfidentialNote::create([
            'bk_service_id' => $service->id,
            'notes' => 'RAHASIA_KONSELING_KELUARGA_SUPER_PRIVAT_12345',
            'created_by' => $guruBk->id,
        ]);

        // Create development note on Tab 5
        BkStudentDevelopmentNote::create([
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'note_date' => '2026-10-10',
            'category' => 'kemandirian',
            'title' => 'Kemandirian Siswa di Asrama',
            'description' => 'Siswa dapat mengatur waktu belajar dengan baik.',
            'recorded_by' => $guruBk->id,
        ]);

        // User with BK operation privilege but NO confidential access (e.g. koordinator or other role without canAccessCounselingNotes)
        // Let's verify Tab 5 content does not contain confidential notes
        $viewResponse = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $viewResponse->assertOk();

        // Tab 5 section
        $content = $viewResponse->getContent();
        $tab5Position = strpos($content, 'id="tab-perkembangan"');
        $tab6Position = strpos($content, 'id="tab-layanan"');

        $tab5Content = substr($content, $tab5Position, $tab6Position - $tab5Position);
        $this->assertStringNotContainsString('RAHASIA_KONSELING_KELUARGA_SUPER_PRIVAT_12345', $tab5Content);
        $this->assertStringContainsString('Kemandirian Siswa di Asrama', $tab5Content);
    }

    public function test_tab_6_and_tab_7_renders_operational_services_and_piket_incidents(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Faisal Akbar');

        // Create referral incident for Tab 7
        $incident = $this->createIncident($academicYear, 'rujuk_bk', $student);

        // Create BK service for Tab 6
        BkService::create([
            'student_id' => $student->id,
            'counselor_id' => $guruBk->id,
            'incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => '2026-10-11',
            'service_type' => 'sosial',
            'summary' => 'Bimbingan perilaku sosial di kelas',
            'result' => 'Siswa berkomitmen mematuhi tata tertib',
        ]);

        $response = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $response->assertOk();

        // Tab 6 assertion
        $response->assertSee('Bimbingan perilaku sosial di kelas');
        $response->assertSee('Siswa berkomitmen mematuhi tata tertib');

        // Tab 7 assertion
        $response->assertSee('Riwayat Kasus Piket Dirujuk ke BK');
        $response->assertSee($incident->description);
    }

    public function test_tab_8_guru_bk_can_upload_download_and_delete_document_securely(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Gilang Ramadhan');

        $fakeFile = UploadedFile::fake()->create('psikotes_gilang.pdf', 500, 'application/pdf');

        $payload = [
            'academic_year' => $academicYear,
            'document_category' => 'hasil_psikotes',
            'title' => 'Laporan Hasil Tes IQ dan Bakat Siswa',
            'document_date' => '2026-08-20',
            'description' => 'Hasil asesmen psikologi formal tahun ajaran baru.',
            'document_file' => $fakeFile,
        ];

        $response = $this->actingAs($guruBk)->post(route('bk.students.documents.store', $student), $payload);
        $response->assertRedirect(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $response->assertSessionHas('success');

        $document = BkStudentDocument::where('student_id', $student->id)->first();
        $this->assertNotNull($document);
        $this->assertEquals('Laporan Hasil Tes IQ dan Bakat Siswa', $document->title);
        $this->assertEquals('psikotes_gilang.pdf', $document->file_name);

        // Verify storage on local (private) disk
        Storage::disk('local')->assertExists($document->file_path);

        // Download document
        $downloadResponse = $this->actingAs($guruBk)->get(route('bk.students.documents.download', [$student, $document]));
        $downloadResponse->assertOk();

        // Delete document
        $deleteResponse = $this->actingAs($guruBk)->delete(route('bk.students.documents.destroy', [$student, $document]));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('bk_student_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_tab_8_unauthorized_user_or_wrong_student_cannot_download_document(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $guruBiasa = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        $student1 = $this->createStudent($academicYear, 'Hendra Wijaya');
        $student2 = $this->createStudent($academicYear, 'Indra Pratama');

        // Create document for student1
        Storage::disk('local')->put("bk_documents/{$student1->id}/test.pdf", 'dummy content');
        $document = BkStudentDocument::create([
            'student_id' => $student1->id,
            'academic_year' => $academicYear,
            'document_category' => 'surat_komitmen',
            'title' => 'Surat Komitmen Pembinaan',
            'file_path' => "bk_documents/{$student1->id}/test.pdf",
            'file_name' => 'test.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'recorded_by' => $guruBk->id,
        ]);

        // 1. Unauthorized user cannot download
        $unauthResponse = $this->actingAs($guruBiasa)->get(route('bk.students.documents.download', [$student1, $document]));
        $unauthResponse->assertForbidden();

        // 2. Mismatched student ID should return 404
        $mismatchResponse = $this->actingAs($guruBk)->get(route('bk.students.documents.download', [$student2, $document]));
        $mismatchResponse->assertNotFound();
    }

    public function test_end_to_end_piket_referral_to_bk_service_to_follow_up_to_student_history(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Joko Susilo');

        // 1. Kejadian piket dengan rujukan ke BK
        $incident = $this->createIncident($academicYear, 'rujuk_bk', $student);

        // 2. Tampil di daftar rujukan dengan status awal menunggu
        $referralsIndex = $this->actingAs($guruBk)->get(route('bk.referrals.index', ['academic_year' => $academicYear]));
        $referralsIndex->assertOk();
        $referralsIndex->assertSee($student->name);
        $referralsIndex->assertSee('Menunggu Tindak Lanjut');

        // 3. Guru BK membuka halaman rujukan
        $showReferral = $this->actingAs($guruBk)->get(route('bk.referrals.show', $incident));
        $showReferral->assertOk();
        $showReferral->assertSee($incident->description);

        // 4. Guru BK mencatat sesi layanan dari rujukan piket tersebut
        $servicePayload = [
            'student_id' => $student->id,
            'piket_incident_id' => $incident->id,
            'academic_year' => $academicYear,
            'service_date' => '2026-10-15',
            'service_type' => 'pribadi',
            'summary' => 'Konseling mendalam mengenai pelanggaran tata tertib piket',
            'result' => 'Siswa menyadari kekeliruan dan berjanji memperbaiki sikap',
            'follow_up_plan' => 'Pemantauan berkala bersama wali kelas selama 2 pekan',
            'confidential_notes' => 'Catatan latar belakang masalah keluarga siswa',
        ];

        $storeResponse = $this->actingAs($guruBk)->post(route('bk.services.store'), $servicePayload);
        $storeResponse->assertRedirect();

        // Status rujukan otomatis bertransisi menjadi 'diproses'
        $this->assertDatabaseHas('bk_referrals', [
            'piket_incident_id' => $incident->id,
            'status' => 'diproses',
        ]);

        // 5. Guru BK menyelesaikan rujukan dengan catatan penutupan kasus
        $statusResponse = $this->actingAs($guruBk)->post(route('bk.referrals.status', $incident), [
            'status' => 'selesai',
            'notes' => 'Tindak lanjut bimbingan konseling telah tuntas dilaksanakan.',
        ]);
        $statusResponse->assertRedirect(route('bk.referrals.show', $incident));

        $this->assertDatabaseHas('bk_referrals', [
            'piket_incident_id' => $incident->id,
            'status' => 'selesai',
        ]);

        // 6. Verifikasi pada Buku Pribadi Siswa (Tab 6 Layanan BK & Tab 7 Kedisiplinan)
        $historyResponse = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $academicYear]));
        $historyResponse->assertOk();
        $historyResponse->assertSee('Konseling mendalam mengenai pelanggaran tata tertib piket');
        $historyResponse->assertSee('Siswa menyadari kekeliruan dan berjanji memperbaiki sikap');
        $historyResponse->assertSee('Pemantauan berkala bersama wali kelas selama 2 pekan');
        $historyResponse->assertSee('Catatan latar belakang masalah keluarga siswa');
        $historyResponse->assertSee($incident->description);
    }

    public function test_bk_services_can_be_filtered_by_follow_up_status(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Kurnia Dewi');

        $serviceWithRtl = BkService::create([
            'student_id' => $student->id,
            'counselor_id' => $guruBk->id,
            'academic_year' => $academicYear,
            'service_date' => '2026-10-16',
            'service_type' => 'belajar',
            'summary' => 'Bimbingan teknik belajar efektif',
            'result' => 'Siswa menyusun jadwal belajar',
            'follow_up_plan' => 'Evaluasi hasil ulangan pekan depan',
        ]);

        $serviceWithoutRtl = BkService::create([
            'student_id' => $student->id,
            'counselor_id' => $guruBk->id,
            'academic_year' => $academicYear,
            'service_date' => '2026-10-17',
            'service_type' => 'karier',
            'summary' => 'Konsultasi minat jurusan kuliah',
            'result' => 'Siswa memperoleh referensi jurusan',
            'follow_up_plan' => null,
        ]);

        // Filter ada RTL
        $resAda = $this->actingAs($guruBk)->get(route('bk.services.index', ['academic_year' => $academicYear, 'follow_up' => 'ada']));
        $resAda->assertOk();
        $resAda->assertSee('Bimbingan teknik belajar efektif');
        $resAda->assertDontSee('Konsultasi minat jurusan kuliah');

        // Filter belum ada RTL
        $resBelum = $this->actingAs($guruBk)->get(route('bk.services.index', ['academic_year' => $academicYear, 'follow_up' => 'belum']));
        $resBelum->assertOk();
        $resBelum->assertSee('Konsultasi minat jurusan kuliah');
        $resBelum->assertDontSee('Bimbingan teknik belajar efektif');
    }

    public function test_sensitive_documents_require_confidential_notes_authority_for_upload_download_and_deletion(): void
    {
        $academicYear = '2026/2027';
        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Lukman Hakim');

        // Create a teacher without BK assignment
        $guruBiasa = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);

        $fakeFile = UploadedFile::fake()->create('psikotes.pdf', 300, 'application/pdf');

        // 1. Guru biasa tidak bisa upload dokumen sensitif (403)
        $unauthUpload = $this->actingAs($guruBiasa)->post(route('bk.students.documents.store', $student), [
            'academic_year' => $academicYear,
            'document_category' => 'hasil_psikotes',
            'title' => 'Tes IQ Siswa',
            'document_file' => $fakeFile,
        ]);
        $unauthUpload->assertForbidden();

        // 2. Guru BK yang sah dapat mengunggah dokumen sensitif
        $authUpload = $this->actingAs($guruBk)->post(route('bk.students.documents.store', $student), [
            'academic_year' => $academicYear,
            'document_category' => 'hasil_psikotes',
            'title' => 'Tes IQ Siswa',
            'document_file' => $fakeFile,
        ]);
        $authUpload->assertRedirect();

        $doc = BkStudentDocument::where('student_id', $student->id)->where('document_category', 'hasil_psikotes')->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->isSensitive());

        // 3. Guru biasa ditolak saat mencoba mengunduh dokumen sensitif
        $unauthDownload = $this->actingAs($guruBiasa)->get(route('bk.students.documents.download', [$student, $doc]));
        $unauthDownload->assertForbidden();

        // 4. Guru BK berwenang dapat mengunduh dokumen sensitif
        $authDownload = $this->actingAs($guruBk)->get(route('bk.students.documents.download', [$student, $doc]));
        $authDownload->assertOk();

        // 5. Guru biasa ditolak saat mencoba menghapus dokumen sensitif
        $unauthDelete = $this->actingAs($guruBiasa)->delete(route('bk.students.documents.destroy', [$student, $doc]));
        $unauthDelete->assertForbidden();

        // 6. Guru BK berwenang dapat menghapus dokumen sensitif
        $authDelete = $this->actingAs($guruBk)->delete(route('bk.students.documents.destroy', [$student, $doc]));
        $authDelete->assertRedirect();
        $this->assertDatabaseMissing('bk_student_documents', ['id' => $doc->id]);
    }

    public function test_cross_module_student_historical_classroom_resolution_and_directory_filtering(): void
    {
        $year25 = '2025/2026';
        $year26 = '2026/2027';

        $ay25 = AcademicYear::firstOrCreate(['name' => $year25], ['semester' => 'Genap', 'is_active' => false]);
        $ay26 = AcademicYear::firstOrCreate(['name' => $year26], ['semester' => 'Ganjil', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $year25);
        $this->assignGuruBk($guruBk, $year26);

        $gradeX = GradeLevel::firstOrCreate(['name' => 'X'], ['level' => 10]);
        $gradeXI = GradeLevel::firstOrCreate(['name' => 'XI'], ['level' => 11]);
        $dept = Department::firstOrCreate(['code' => 'TKJ'], ['name' => 'Teknik Komputer & Jaringan']);

        $classX = Classroom::firstOrCreate(
            ['name' => 'X TKJ 1'],
            ['academic_year' => $year25, 'academic_year_id' => $ay25->id, 'grade_level_id' => $gradeX->id, 'department_id' => $dept->id, 'is_active' => false]
        );

        $classXI = Classroom::firstOrCreate(
            ['name' => 'XI TKJ 1'],
            ['academic_year' => $year26, 'academic_year_id' => $ay26->id, 'grade_level_id' => $gradeXI->id, 'department_id' => $dept->id, 'is_active' => true]
        );

        // Siswa saat ini berada di kelas XI TKJ 1
        $student = Student::create([
            'nisn' => '9988776655',
            'name' => 'Bambang Pamungkas',
            'classroom_id' => $classXI->id,
            'academic_year' => $year26,
            'status' => 'aktif',
        ]);

        // Catat riwayat kelas X pada 2025/2026 dan kelas XI pada 2026/2027
        StudentClassroom::create([
            'student_id' => $student->id,
            'classroom_id' => $classX->id,
            'academic_year_id' => $ay25->id,
            'academic_year_name' => $year25,
            'status' => 'aktif',
        ]);

        StudentClassroom::create([
            'student_id' => $student->id,
            'classroom_id' => $classXI->id,
            'academic_year_id' => $ay26->id,
            'academic_year_name' => $year26,
            'status' => 'aktif',
        ]);

        // 1. Verifikasi model resolver classroomForAcademicYear
        $resolvedClass25 = $student->classroomForAcademicYear($year25);
        $resolvedClass26 = $student->classroomForAcademicYear($year26);

        $this->assertEquals($classX->id, $resolvedClass25?->id);
        $this->assertEquals($classXI->id, $resolvedClass26?->id);

        // 2. Verifikasi halaman Buku Pribadi Siswa pada tahun ajaran lampau 2025/2026
        $resHistory25 = $this->actingAs($guruBk)->get(route('bk.students.history', ['student' => $student, 'academic_year' => $year25]));
        $resHistory25->assertOk();
        $resHistory25->assertSee('Kelas (TP 2025/2026):');
        $resHistory25->assertSee('X TKJ 1');
        $resHistory25->assertSee('(Terkini: XI TKJ 1)');

        // 3. Verifikasi direktori siswa BK (/bk/buku-pribadi) saat difilter berdasarkan classroom_id lampau
        $resDir = $this->actingAs($guruBk)->get(route('bk.students.index', [
            'academic_year' => $year25,
            'classroom_id' => $classX->id,
        ]));
        $resDir->assertOk();
        $resDir->assertSee('BAMBANG PAMUNGKAS');
    }

    public function test_bk_services_index_early_warning_system_with_repeat_piket_incidents(): void
    {
        $academicYear = '2026/2027';
        AcademicYear::firstOrCreate(['name' => $academicYear], ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

        $guruRole = $this->getOrCreateRole('guru', 'Guru');
        $guruBk = User::factory()->create(['role_id' => $guruRole->id, 'position' => 'guru']);
        $this->assignGuruBk($guruBk, $academicYear);

        $student = $this->createStudent($academicYear, 'Siswa Bermasalah');

        // Create 2 piket incidents for student
        PiketIncident::create([
            'academic_year' => $academicYear,
            'incident_date' => '2026-08-10',
            'incident_time' => '08:00:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat masuk sekolah',
            'action_taken' => 'Teguran lisan',
            'recorded_by' => $guruBk->id,
        ]);

        PiketIncident::create([
            'academic_year' => $academicYear,
            'incident_date' => '2026-08-15',
            'incident_time' => '08:15:00',
            'student_id' => $student->id,
            'student_name' => $student->name,
            'category' => 'kedisiplinan',
            'description' => 'Terlambat kedua kali',
            'action_taken' => 'Pembinaan piket',
            'recorded_by' => $guruBk->id,
        ]);

        $response = $this->actingAs($guruBk)->get(route('bk.services.index', ['academic_year' => $academicYear]));
        $response->assertOk();
        $response->assertViewHas('ewsStats');
        $ewsStats = $response->viewData('ewsStats');
        $this->assertEquals(1, $ewsStats['repeatIncidents']);
    }
}
