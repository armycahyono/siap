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
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentVisitationAndDisciplineTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;

    protected Department $department;

    protected Classroom $classroom;

    protected Student $student;

    protected User $superAdmin;

    protected User $guruBk;

    protected User $waliKelas;

    protected User $guruPiket;

    protected User $wakaKesiswaan;

    protected User $guruBiasa;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->department = Department::create([
            'code' => 'PPLG',
            'name' => 'Pengembangan Perangkat Lunak dan Gim',
        ]);

        $gradeLevel = GradeLevel::create([
            'level' => 12,
            'name' => 'XII',
            'is_active' => true,
        ]);

        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator']);
        $guruRole = Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru']);
        $kesiswaanRole = Role::firstOrCreate(['slug' => 'kesiswaan'], ['name' => 'Tim Kesiswaan']);
        $piketRole = Role::firstOrCreate(['slug' => 'piket'], ['name' => 'Petugas Piket']);
        $bkRole = Role::firstOrCreate(['slug' => 'guru_bk'], ['name' => 'Guru BK']);

        $accessPiketPerm = Permission::firstOrCreate(['slug' => 'access_piket'], ['name' => 'Access Piket', 'group' => 'Piket']);
        $managePiketPerm = Permission::firstOrCreate(['slug' => 'manage_piket_cases'], ['name' => 'Manage Piket Cases', 'group' => 'Piket']);
        $operatePiketPerm = Permission::firstOrCreate(['slug' => 'operate_piket'], ['name' => 'Operate Piket', 'group' => 'Piket']);
        $operateAffairsPerm = Permission::firstOrCreate(['slug' => 'operate_student_affairs'], ['name' => 'Operate Student Affairs', 'group' => 'Kesiswaan']);

        $piketRole->permissions()->syncWithoutDetaching([$accessPiketPerm->id, $managePiketPerm->id, $operatePiketPerm->id]);
        $kesiswaanRole->permissions()->syncWithoutDetaching([$accessPiketPerm->id, $operateAffairsPerm->id]);
        $bkRole->permissions()->syncWithoutDetaching([$accessPiketPerm->id]);

        $this->superAdmin = User::factory()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $this->waliKelas = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Guru Mapel',
            'is_active' => true,
        ]);

        $this->classroom = Classroom::create([
            'name' => 'XII PPLG 1',
            'department_id' => $this->department->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->waliKelas->id,
            'academic_year' => $this->academicYear->name,
            'academic_year_id' => $this->academicYear->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Budi Santoso',
            'nisn' => '0012345678',
            'nis' => '26001',
            'gender' => 'L',
            'classroom_id' => $this->classroom->id,
            'status' => 'aktif',
        ]);

        $this->guruBk = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Guru BK',
            'is_active' => true,
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->guruBk->id,
            'role_id' => $bkRole->id,
            'status' => true,
            'academic_year' => $this->academicYear->name,
        ]);

        $this->wakaKesiswaan = User::factory()->create([
            'role_id' => $kesiswaanRole->id,
            'position' => 'Waka Kesiswaan & Kedisiplinan',
            'is_active' => true,
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->wakaKesiswaan->id,
            'role_id' => $kesiswaanRole->id,
            'status' => true,
            'academic_year' => $this->academicYear->name,
        ]);

        $this->guruPiket = User::factory()->create([
            'role_id' => $piketRole->id,
            'position' => 'Petugas Piket',
            'is_active' => true,
        ]);

        $this->guruBiasa = User::factory()->create([
            'role_id' => $guruRole->id,
            'position' => 'Guru Matematika',
            'is_active' => true,
        ]);
    }

    public function test_piket_officer_can_record_incident_with_default_waiting_verification(): void
    {
        $response = $this->actingAs($this->guruPiket)->post(route('piket.incidents.store'), [
            'incident_date' => '2026-10-27',
            'incident_time' => '08:30',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'location' => 'Area Parkir Barat',
            'category' => 'rokok',
            'description' => 'Ditemukan membawa rokok elektrik di area parkir saat jam istirahat.',
            'action_taken' => 'Barang disita di meja piket, siswa diperingatkan.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'confiscated_items' => '1 unit vape',
        ]);

        $response->assertRedirect(route('piket.incidents.index'));

        $this->assertDatabaseHas('piket_incidents', [
            'student_id' => $this->student->id,
            'category' => 'rokok',
            'location' => 'Area Parkir Barat',
            'verification_status' => 'menunggu_verifikasi',
            'status' => 'tercatat',
        ]);
    }

    public function test_regular_piket_and_bk_teachers_cannot_verify_violations(): void
    {
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '09:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'perkelahian',
            'description' => 'Terjadi perselisihan antar siswa di lorong kelas.',
            'action_taken' => 'Dipisahkan oleh guru piket.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $this->guruPiket->id,
            'status' => 'tercatat',
            'verification_status' => 'menunggu_verifikasi',
        ]);

        // 1. Guru Piket mencoba verifikasi -> 403 Forbidden
        $responsePiket = $this->actingAs($this->guruPiket)->post(route('piket.incidents.verify', $incident), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Peringatan keras',
        ]);
        $responsePiket->assertStatus(403);

        // 2. Guru BK (tanpa SK Tim Disiplin) mencoba verifikasi -> 403 Forbidden
        $responseBk = $this->actingAs($this->guruBk)->post(route('piket.incidents.verify', $incident), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Peringatan keras',
        ]);
        $responseBk->assertStatus(403);
    }

    public function test_kesiswaan_can_verify_incident_assign_sanction_and_refer_to_bk(): void
    {
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '09:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'perkelahian',
            'description' => 'Terjadi perselisihan dan perkelahian di lorong kelas.',
            'action_taken' => 'Dipisahkan dan didata di meja piket.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $this->guruPiket->id,
            'status' => 'tercatat',
            'verification_status' => 'menunggu_verifikasi',
        ]);

        $response = $this->actingAs($this->wakaKesiswaan)->post(route('piket.incidents.verify', $incident), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Skorsing 3 hari dan surat peringatan tertulis.',
            'parental_involvement' => 1,
            'parental_involvement_notes' => 'Orang tua dipanggil ke sekolah pada tanggal 28 Oktober 2026.',
            'follow_up_target' => 'rujuk_bk',
            'verification_notes' => 'Siswa mengakui memicu perkelahian karena provokasi.',
            'status' => 'ditindaklanjuti',
        ]);

        $response->assertSessionHas('success');

        $incident->refresh();
        $this->assertEquals('terverifikasi', $incident->verification_status);
        $this->assertEquals($this->wakaKesiswaan->id, $incident->verified_by);
        $this->assertNotNull($incident->verified_at);
        $this->assertTrue($incident->parental_involvement);
        $this->assertEquals('rujuk_bk', $incident->follow_up_target);

        // Memastikan rujukan ke modul BK terintegrasi otomatis
        $this->assertDatabaseHas('bk_referrals', [
            'piket_incident_id' => $incident->id,
            'status' => 'menunggu',
        ]);
    }

    public function test_homeroom_teacher_and_bk_can_schedule_student_visitation(): void
    {
        // 1. Wali Kelas menjadwalkan visitasi untuk siswa di kelas binaannya
        $response = $this->actingAs($this->waliKelas)->post(route('visitations.store'), [
            'student_id' => $this->student->id,
            'visit_type' => 'kunjungan_rumah',
            'source_type' => 'inisiatif_wali_kelas',
            'reason' => 'Siswa tidak masuk sekolah selama 4 hari berturut-turut tanpa kabar.',
            'primary_officer_id' => $this->waliKelas->id,
            'secondary_officer_id' => $this->guruBk->id,
            'planned_date' => '2026-10-29',
            'status' => 'dijadwalkan',
            'confidential_notes' => 'Keluarga diduga mengalami kendala finansial dan musibah keluarga.',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_visitations', [
            'student_id' => $this->student->id,
            'visit_type' => 'kunjungan_rumah',
            'requested_by' => $this->waliKelas->id,
            'primary_officer_id' => $this->waliKelas->id,
            'secondary_officer_id' => $this->guruBk->id,
            'status' => 'dijadwalkan',
            'academic_year' => '2026/2027',
        ]);
    }

    public function test_unrelated_teacher_cannot_create_visitation_for_other_student(): void
    {
        $response = $this->actingAs($this->guruBiasa)->post(route('visitations.store'), [
            'student_id' => $this->student->id,
            'visit_type' => 'kunjungan_rumah',
            'reason' => 'Keinginan pribadi guru mapel.',
            'primary_officer_id' => $this->guruBiasa->id,
            'planned_date' => '2026-10-30',
        ]);

        $response->assertStatus(403);
    }

    public function test_visitation_confidential_notes_are_strictly_guarded(): void
    {
        $visitation = StudentVisitation::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'kunjungan_rumah',
            'source_type' => 'inisiatif_wali_kelas',
            'reason' => 'Masalah absensi dan kondisi psikososial siswa.',
            'requested_by' => $this->waliKelas->id,
            'primary_officer_id' => $this->waliKelas->id,
            'secondary_officer_id' => $this->guruBk->id,
            'planned_date' => '2026-10-29',
            'status' => 'dijadwalkan',
            'confidential_notes' => 'Catatan sensitif: Orang tua siswa dalam proses perceraian.',
        ]);

        // 1. Wali kelas yang ditugaskan dapat melihat catatan sensitif
        $responseWali = $this->actingAs($this->waliKelas)->get(route('visitations.show', $visitation));
        $responseWali->assertOk();
        $responseWali->assertSee('Orang tua siswa dalam proses perceraian');

        // 2. Guru BK dapat melihat catatan sensitif (profesi konseling)
        $responseBk = $this->actingAs($this->guruBk)->get(route('visitations.show', $visitation));
        $responseBk->assertOk();
        $responseBk->assertSee('Orang tua siswa dalam proses perceraian');

        // 3. Guru biasa yang tidak terlibat tidak boleh melihat halaman visitasi
        $responseGuru = $this->actingAs($this->guruBiasa)->get(route('visitations.show', $visitation));
        $responseGuru->assertStatus(403);
    }

    public function test_visitation_report_can_be_completed_with_private_attachment(): void
    {
        $visitation = StudentVisitation::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'kunjungan_rumah',
            'source_type' => 'rujukan_bk',
            'reason' => 'Home visit penanganan absensi.',
            'requested_by' => $this->guruBk->id,
            'primary_officer_id' => $this->guruBk->id,
            'planned_date' => '2026-10-28',
            'status' => 'dijadwalkan',
        ]);

        $fakePdf = UploadedFile::fake()->create('Berita_Acara_Kunjungan.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->guruBk)->put(route('visitations.update', $visitation), [
            'executed_date' => '2026-10-28',
            'status' => 'selesai',
            'result_summary' => 'Orang tua berkomitmen mendampingi siswa dan membuat surat pernyataan kesanggupan hadir tepat waktu.',
            'follow_up_plan' => 'Pemantauan presensi harian bersama wali kelas selama 2 pekan ke depan.',
            'attachment' => $fakePdf,
        ]);

        $response->assertRedirect(route('visitations.show', $visitation));

        $visitation->refresh();
        $this->assertEquals('selesai', $visitation->status);
        $this->assertEquals('2026-10-28', $visitation->executed_date->format('Y-m-d'));
        $this->assertNotNull($visitation->attachment_path);
        Storage::disk('local')->assertExists($visitation->attachment_path);

        // Download dokumen terproteksi otorisasi server-side
        $downloadResponse = $this->actingAs($this->guruBk)->get(route('visitations.attachment', $visitation));
        $downloadResponse->assertOk();

        // Guru luar tidak berhak mengunduh lampiran privat
        $unauthorizedDownload = $this->actingAs($this->guruBiasa)->get(route('visitations.attachment', $visitation));
        $unauthorizedDownload->assertStatus(403);
    }

    public function test_buku_pribadi_siswa_digital_reflects_discipline_and_visitation(): void
    {
        // 1. Buat catatan pelanggaran terverifikasi
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-26',
            'incident_time' => '10:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'location' => 'Gerbang Depan',
            'category' => 'seragam',
            'description' => 'Memakai celana cutbray tidak standar sekolah.',
            'action_taken' => 'Teguran lisan di meja piket.',
            'follow_up_target' => 'selesai_di_piket',
            'recorded_by' => $this->guruPiket->id,
            'verification_status' => 'terverifikasi',
            'verified_by' => $this->wakaKesiswaan->id,
            'verified_at' => now(),
            'disciplinary_sanction' => 'Membuat surat kesanggupan mengganti seragam dalam 3 hari.',
            'parental_involvement' => true,
            'status' => 'ditindaklanjuti',
        ]);

        // 2. Buat visitasi siswa
        $visitation = StudentVisitation::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'pemantauan_khusus',
            'source_type' => 'inisiatif_wali_kelas',
            'reason' => 'Pemantauan kesiapan belajar di rumah menjelang ujian.',
            'requested_by' => $this->waliKelas->id,
            'primary_officer_id' => $this->waliKelas->id,
            'planned_date' => '2026-10-27',
            'status' => 'terlaksana',
            'result_summary' => 'Siswa memiliki ruang belajar cukup dan mendapat bimbingan orang tua.',
        ]);

        // Guru BK membuka Buku Pribadi Siswa Digital
        $response = $this->actingAs($this->guruBk)->get(route('bk.students.history', [
            'student' => $this->student,
            'academic_year' => '2026/2027',
        ]));

        $response->assertOk();
        // Memastikan riwayat visitasi muncul di Tab 5
        $response->assertSee('Riwayat Visitasi &amp; Pemantauan Siswa', false);
        $response->assertSee('Pemantauan kesiapan belajar di rumah menjelang ujian.');
        // Memastikan rekaman pelanggaran terverifikasi muncul di Tab 7
        $response->assertSee('Rekaman Kejadian &amp; Pelanggaran Kedisiplinan Siswa', false);
        $response->assertSee('Pelanggaran Terverifikasi');
        $response->assertSee('Membuat surat kesanggupan mengganti seragam dalam 3 hari.');
    }

    public function test_telegram_notification_dispatched_on_visitation_assignment(): void
    {
        $telegramMock = \Mockery::spy(TelegramService::class);
        $this->app->instance(TelegramService::class, $telegramMock);

        $this->actingAs($this->waliKelas)->post(route('visitations.store'), [
            'student_id' => $this->student->id,
            'visit_type' => 'kunjungan_rumah',
            'reason' => 'Siswa tidak masuk sekolah.',
            'primary_officer_id' => $this->waliKelas->id,
            'secondary_officer_id' => $this->guruBk->id,
            'planned_date' => '2026-10-29',
        ]);

        $telegramMock->shouldHaveReceived('notifyVisitation')->once();
    }

    public function test_telegram_notification_dispatched_on_incident_verification(): void
    {
        $telegramMock = \Mockery::spy(TelegramService::class);
        $this->app->instance(TelegramService::class, $telegramMock);

        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '09:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'rokok',
            'description' => 'Membawa vape.',
            'action_taken' => 'Disita.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $this->guruPiket->id,
            'status' => 'tercatat',
        ]);

        $this->actingAs($this->wakaKesiswaan)->post(route('piket.incidents.verify', $incident), [
            'verification_status' => 'terverifikasi',
            'disciplinary_sanction' => 'Peringatan tertulis',
        ]);

        $telegramMock->shouldHaveReceived('notifyIncidentVerification')->once();
    }

    public function test_discipline_team_authority_is_scoped_to_academic_year(): void
    {
        $disciplineRole = Role::firstOrCreate(['slug' => 'tim_kedisiplinan'], ['name' => 'Tim Kedisiplinan']);

        $userDisiplin = User::factory()->create([
            'role_id' => Role::where('slug', 'guru')->first()->id,
            'is_active' => true,
        ]);

        // Berikan penugasan kontekstual hanya untuk tahun 2026/2027
        UserRoleAssignment::create([
            'user_id' => $userDisiplin->id,
            'role_id' => $disciplineRole->id,
            'academic_year' => '2026/2027',
            'academic_year_id' => $this->academicYear->id,
            'status' => true,
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-06-30',
        ]);

        $this->assertTrue($userDisiplin->isDisciplineTeam('2026/2027'));
        $this->assertFalse($userDisiplin->isDisciplineTeam('2025/2026'));
    }

    public function test_verified_incident_cannot_be_deleted_by_regular_piket_officer(): void
    {
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '10:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'perkelahian',
            'description' => 'Terlibat perkelahian di area kantin.',
            'action_taken' => 'Dilerai dan dipanggil.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $this->guruPiket->id,
            'verification_status' => 'terverifikasi',
            'verified_by' => $this->wakaKesiswaan->id,
            'verified_at' => now(),
            'disciplinary_sanction' => 'Skorsing 3 hari',
        ]);

        // Petugas piket biasa mencoba menghapus insiden yang sudah diverifikasi
        $response = $this->actingAs($this->guruPiket)->delete(route('piket.incidents.destroy', $incident));
        $response->assertStatus(403);
        $this->assertDatabaseHas('piket_incidents', ['id' => $incident->id]);

        // Waka Kesiswaan berwenang dapat menghapus jika memang diperlukan
        $responseAdmin = $this->actingAs($this->wakaKesiswaan)->delete(route('piket.incidents.destroy', $incident));
        $responseAdmin->assertRedirect();
        $this->assertDatabaseMissing('piket_incidents', ['id' => $incident->id]);
    }

    public function test_bk_service_rtl_monitoring_can_be_updated_with_evaluation_and_target_date(): void
    {
        $service = BkService::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'service_date' => '2026-10-27',
            'service_type' => 'pribadi',
            'summary' => 'Konseling penyesuaian diri dan motivasi belajar.',
            'result' => 'Siswa berkomitmen membuat jadwal belajar rutin.',
            'follow_up_plan' => 'Pemeriksaan jurnal belajar mingguan.',
            'counselor_id' => $this->guruBk->id,
            'follow_up_status' => 'belum_dilaksanakan',
        ]);

        $response = $this->actingAs($this->guruBk)->post(route('bk.services.rtl', $service), [
            'follow_up_plan' => 'Pemeriksaan jurnal belajar mingguan dan wawancara orang tua.',
            'follow_up_status' => 'sedang_berjalan',
            'follow_up_target_date' => '2026-11-05',
            'follow_up_assignee_id' => $this->guruBk->id,
            'follow_up_evaluation_notes' => 'Siswa telah menunjukkan peningkatan keaktifan di kelas.',
        ]);

        $response->assertRedirect(route('bk.services.show', $service));
        $service->refresh();

        $this->assertEquals('sedang_berjalan', $service->follow_up_status);
        $this->assertEquals('2026-11-05', $service->follow_up_target_date->format('Y-m-d'));
        $this->assertNotNull($service->follow_up_evaluated_at);
        $this->assertStringContainsString('peningkatan keaktifan', $service->follow_up_evaluation_notes);
    }

    public function test_visitation_pdf_can_be_downloaded_by_authorized_user(): void
    {
        $visitation = StudentVisitation::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'visit_type' => 'kunjungan_rumah',
            'reason' => 'Siswa sering terlambat dan perlu konfirmasi orang tua.',
            'requested_by' => $this->waliKelas->id,
            'primary_officer_id' => $this->guruBk->id,
            'planned_date' => '2026-10-28',
            'actual_date' => '2026-10-28',
            'status' => 'terlaksana',
            'result_summary' => 'Orang tua menyetujui jadwal bangun lebih pagi dan antar jemput.',
        ]);

        $response = $this->actingAs($this->guruBk)->get(route('visitations.pdf', $visitation));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_student_personal_book_pdf_can_be_downloaded_by_bk_teacher(): void
    {
        $response = $this->actingAs($this->guruBk)->get(route('bk.students.history.pdf', [
            'student' => $this->student,
            'academic_year' => '2026/2027',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_surat_panggilan_pdf_can_be_downloaded_by_discipline_team(): void
    {
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '11:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'kedisiplinan',
            'description' => 'Meninggalkan area sekolah tanpa izin saat jam pelajaran.',
            'action_taken' => 'Diamankan petugas.',
            'follow_up_target' => 'rujuk_kesiswaan',
            'recorded_by' => $this->guruPiket->id,
            'verification_status' => 'terverifikasi',
            'verified_by' => $this->wakaKesiswaan->id,
            'verified_at' => now(),
            'disciplinary_sanction' => 'Panggilan orang tua dan surat peringatan 1.',
        ]);

        $response = $this->actingAs($this->wakaKesiswaan)->get(route('piket.incidents.surat_panggilan', $incident));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_early_warning_system_metrics_displayed_in_bk_services_index(): void
    {
        // 1. Buat rujukan piket yang menunggu
        $incident = PiketIncident::create([
            'incident_date' => '2026-10-27',
            'incident_time' => '08:00',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'classroom_id' => $this->classroom->id,
            'category' => 'lainnya',
            'description' => 'Perlu bimbingan lanjutan.',
            'action_taken' => 'Dirujuk.',
            'follow_up_target' => 'rujuk_bk',
            'recorded_by' => $this->guruPiket->id,
            'status' => 'tercatat',
        ]);
        BkReferral::create([
            'piket_incident_id' => $incident->id,
            'status' => 'menunggu',
        ]);

        // 2. Buat layanan dengan RTL jatuh tempo
        BkService::create([
            'student_id' => $this->student->id,
            'academic_year' => '2026/2027',
            'service_date' => '2026-10-25',
            'service_type' => 'belajar',
            'summary' => 'Konseling remedi.',
            'result' => 'Perlu tindak lanjut.',
            'follow_up_plan' => 'Cek tugas remedial.',
            'counselor_id' => $this->guruBk->id,
            'follow_up_status' => 'sedang_berjalan',
            'follow_up_target_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->guruBk)->get(route('bk.services.index', ['academic_year' => '2026/2027']));
        $response->assertOk();
        $response->assertSee('Sistem Peringatan Dini &amp; Pemantauan Tindak Lanjut', false);
        $response->assertSee('Rujukan Piket Menunggu');
        $response->assertSee('RTL Jatuh Tempo');
    }
}
