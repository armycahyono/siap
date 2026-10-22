<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'login' => 'waka',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_authenticated_waka_can_access_dashboard(): void
    {
        $waka = User::where('username', 'waka')->first();

        $response = $this->actingAs($waka)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Supervisi Guru');
    }

    public function test_teacher_can_access_their_own_supervision_portal(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        $response = $this->actingAs($guru)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Portal Supervisi Guru');
    }

    public function test_public_qr_verification_page_renders(): void
    {
        $supervision = Supervision::whereNotNull('qr_token')->first();

        $this->assertNotNull($supervision);

        $response = $this->get('/verifikasi/'.$supervision->qr_token);

        $response->assertStatus(200);
        $response->assertSee('Sistem Supervisi Pembelajaran');
    }

    public function test_print_page_renders_successfully(): void
    {
        $waka = User::where('username', 'waka')->first();
        $supervision = Supervision::first();

        $this->assertNotNull($supervision);

        $response = $this->actingAs($waka)->get("/laporan/{$supervision->id}/cetak");

        $response->assertStatus(200);
        $response->assertSee('INSTRUMEN OBSERVASI PEMBELAJARAN');
    }

    public function test_pdf_download_includes_teacher_name_in_filename(): void
    {
        $waka = User::where('username', 'waka')->first();
        $supervision = Supervision::first();
        $supervision->update(['report_number' => 'SPV/007/SMK PGRI KRAS/2026']);

        $response = $this->actingAs($waka)->get(route('reports.pdf', $supervision));
        $response->assertStatus(200);

        $teacherName = $supervision->teacher->name;
        $cleanTeacherName = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $teacherName));
        $expectedFilename = "Laporan_SPV_007_SMK_PGRI_KRAS_2026 ({$cleanTeacherName}).pdf";

        $response->assertHeader('content-disposition', 'attachment; filename="'.$expectedFilename.'"');
    }

    public function test_prototype_direct_workflow_and_history_menus_render(): void
    {
        $waka = User::where('username', 'waka')->first();

        // 1. Praobservasi menu
        $resPreobs = $this->actingAs($waka)->get('/praobservasi');
        $resPreobs->assertStatus(200);
        $resPreobs->assertSee('Praobservasi');

        // 2. Observasi Pembelajaran menu
        $resObs = $this->actingAs($waka)->get('/observasi');
        $resObs->assertStatus(200);
        $resObs->assertSee('Observasi Pembelajaran');

        // 3. Dokumentasi menu
        $resDocs = $this->actingAs($waka)->get('/dokumentasi');
        $resDocs->assertStatus(200);
        $resDocs->assertSee('Dokumentasi Supervisi');

        // 4. Hasil Supervisi menu
        $resResults = $this->actingAs($waka)->get('/hasil-supervisi');
        $resResults->assertStatus(200);
        $resResults->assertSee('Hasil Supervisi');

        // 5. Cetak Laporan menu
        $resPrint = $this->actingAs($waka)->get('/cetak-laporan');
        $resPrint->assertStatus(200);
        $resPrint->assertSee('Cetak Laporan Supervisi');

        // 6. Riwayat Supervisi menu
        $resHistory = $this->actingAs($waka)->get('/riwayat');
        $resHistory->assertStatus(200);
        $resHistory->assertSee('Riwayat Supervisi');

        // 7. Detail berkas supervisi (supervisions.show)
        $supervision = Supervision::first();
        $resShow = $this->actingAs($waka)->get(route('supervisions.show', $supervision));
        $resShow->assertStatus(200);
        $resShow->assertSee('Berkas Supervisi');
        $resShow->assertSee('alt="QR Code Verifikasi Dokumen"', false);
    }

    public function test_supervision_schedules_and_dashboard_are_scoped_by_academic_year(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // Create an old supervision for 2024/2025
        $oldSpv = Supervision::create([
            'teacher_id' => $guru->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
            'schedule_date' => '2024-09-10',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Mapel Tahun Lalu Spesifik',
            'class_name' => 'X TKR 1',
            'status' => 'terjadwal',
        ]);

        // Filter current active year (2026/2027)
        $resActive = $this->actingAs($waka)->get(route('schedules.index', [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
        ]));
        $resActive->assertStatus(200);
        $resActive->assertDontSee('Mapel Tahun Lalu Spesifik');

        // Filter previous year (2024/2025)
        $resOld = $this->actingAs($waka)->get(route('schedules.index', [
            'academic_year' => '2024/2025',
            'semester' => 'Ganjil',
        ]));
        $resOld->assertStatus(200);
        $resOld->assertSee('Mapel Tahun Lalu Spesifik');

        // Approvals filtering
        $oldSpv->update(['status' => 'menunggu_persetujuan']);
        $resAppr = $this->actingAs($waka)->get(route('approvals.index', [
            'academic_year' => '2026/2027',
        ]));
        $resAppr->assertStatus(200);
        $resAppr->assertDontSee('Mapel Tahun Lalu Spesifik');
    }

    public function test_all_supervision_stages_and_reports_automatically_filter_by_active_session_academic_year(): void
    {
        $waka = User::where('username', 'waka')->first();
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        // Create a unique supervision for an archive academic year 2023/2024
        $archiveSpv = Supervision::create([
            'teacher_id' => $guru->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2023/2024',
            'semester' => 'Ganjil',
            'schedule_date' => '2023-10-15',
            'schedule_time' => '07:30 - 09:00 WIB',
            'subject' => 'Fisika Kuantum Arsip 2023',
            'class_name' => 'XII TKJ 2',
            'status' => 'disetujui',
            'approved_by_principal' => true,
            'report_number' => 'SPV/2023/999',
        ]);

        // 1. In default academic year (2026/2027), none of the stage or report pages should display this record
        $this->actingAs($waka);

        $this->get(route('supervisions.preobs'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('supervisions.obs'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('supervisions.docs'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('supervisions.results'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('reports.history'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('reports.print-index'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');
        $this->get(route('schedules.index'))->assertStatus(200)->assertDontSee('Fisika Kuantum Arsip 2023');

        // 2. When switching academic year context in session to 2023/2024, all pages automatically display only 2023/2024 data
        $sessionContext = ['active_academic_year' => '2023/2024'];

        $this->withSession($sessionContext)->get(route('supervisions.preobs'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('supervisions.obs'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('supervisions.docs'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('supervisions.results'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('reports.history'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('reports.print-index'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
        $this->withSession($sessionContext)->get(route('schedules.index'))->assertStatus(200)->assertSee('Fisika Kuantum Arsip 2023');
    }

    public function test_schedule_create_and_edit_loads_teacher_lesson_schedules_map(): void
    {
        $waka = User::where('username', 'waka')->first();
        $supervision = Supervision::first();

        $createResponse = $this->actingAs($waka)->get(route('schedules.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertViewHas('teachersSchedulesMap');
        $createResponse->assertSee('Pilih Jadwal Mengajar Guru di Kelas');

        if ($supervision) {
            $editResponse = $this->actingAs($waka)->get(route('schedules.edit', $supervision));
            $editResponse->assertStatus(200);
            $editResponse->assertViewHas('teachersSchedulesMap');
            $editResponse->assertSee('Pilih Jadwal Mengajar Guru di Kelas');
        }
    }

    public function test_teacher_cannot_conduct_own_supervision_by_default_without_permission(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $supervision = Supervision::where('teacher_id', $guru->id)->first();

        // Ensure conduct_supervision is not granted to guru role in default seed
        $guruRole = Role::where('slug', 'guru')->first();
        $permission = Permission::where('slug', 'conduct_supervision')->first();
        $guruRole->permissions()->detach($permission->id);

        $this->assertFalse($guru->fresh()->canConductSupervision($supervision));
        $this->assertFalse($guru->fresh()->canEditSupervision($supervision));

        $resObs = $this->actingAs($guru)->get(route('supervisions.observation', $supervision));
        $resObs->assertStatus(200);
        $resObs->assertSee('Mode Pratinjau (Hanya Lihat)');

        // Attempting to save observation should return 403
        $resSave = $this->actingAs($guru)->post(route('supervisions.save-observation', $supervision), [
            'scores' => [1 => 4],
        ]);
        $resSave->assertStatus(403);
    }

    public function test_teacher_can_conduct_and_correct_own_supervision_when_permission_granted(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $supervision = Supervision::where('teacher_id', $guru->id)->first();
        $supervision->update(['status' => 'terjadwal']);

        // Grant conduct_supervision permission to guru role (as done via /pengaturan/hak-akses)
        $guruRole = Role::where('slug', 'guru')->first();
        $permission = Permission::where('slug', 'conduct_supervision')->first();
        $guruRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertTrue($guru->fresh()->canConductSupervision($supervision));
        $this->assertTrue($guru->fresh()->canEditSupervision($supervision));

        // Observation page should not have read-only notice
        $resObs = $this->actingAs($guru)->get(route('supervisions.observation', $supervision));
        $resObs->assertStatus(200);
        $resObs->assertDontSee('Mode Pratinjau (Hanya Lihat)');

        // Teacher can save / correct observation scores
        $resSave = $this->actingAs($guru)->post(route('supervisions.save-observation', $supervision), [
            'scores' => [1 => 4],
            'notes' => [1 => 'Catatan mandiri guru'],
            'obs_date' => now()->toDateString(),
        ]);
        $resSave->assertSessionHas('success');

        // Teacher cannot conduct other teachers' supervision
        $otherSupervision = Supervision::where('teacher_id', '!=', $guru->id)->first();
        if ($otherSupervision) {
            $this->assertFalse($guru->fresh()->canConductSupervision($otherSupervision));
            $resOther = $this->actingAs($guru)->post(route('supervisions.save-observation', $otherSupervision), [
                'scores' => [1 => 4],
            ]);
            $resOther->assertStatus(403);
        }
    }

    public function test_supervision_stepper_displays_accurate_pending_and_completed_states(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $waka = User::where('username', 'waka')->first();

        // Fresh scheduled supervision (nothing done yet)
        $spv = Supervision::create([
            'teacher_id' => $guru->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-10-10',
            'schedule_time' => '08:00 - 09:30 WIB',
            'subject' => 'Mapel Uji Stepper',
            'class_name' => 'X TKR 1',
            'status' => 'terjadwal',
        ]);

        $res = $this->actingAs($guru)->get(route('supervisions.show', $spv));
        $res->assertStatus(200);

        // Step 1 is current/waiting, other steps are pending
        $res->assertSee('step-current');
        $res->assertSee('Menunggu Unggah');
        $res->assertSee('Belum Diisi');
        $res->assertSee('Belum Dinilai');
        $res->assertDontSee('class="workflow-step step-completed"', false);

        // Mark RPP uploaded and Praobservasi done
        $spv->update([
            'rpp_file_path' => 'modul_ajar/test.pdf',
            'pre_completed_at' => now(),
        ]);

        $resUpdated = $this->actingAs($guru)->get(route('supervisions.show', $spv));
        $resUpdated->assertStatus(200);
        $resUpdated->assertSee('class="workflow-step step-completed"', false);
        $resUpdated->assertSee('Sudah Diunggah');
        $resUpdated->assertSee('Selesai');
    }

    public function test_generate_report_number_increments_from_highest_existing_sequence(): void
    {
        $year = date('Y');
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $waka = User::where('username', 'waka')->first();

        // Create a supervision with high sequence number
        Supervision::create([
            'teacher_id' => $guru->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-10-10',
            'subject' => 'Pemeliharaan Mesin Kendaraan',
            'class_name' => 'XI TKRO 2',
            'status' => 'terjadwal',
            'report_number' => "SPV/050/SMK PGRI KRAS/{$year}",
        ]);

        $nextNumber = Supervision::generateReportNumber();
        $this->assertEquals("SPV/051/SMK PGRI KRAS/{$year}", $nextNumber);
    }

    public function test_teacher_with_supervision_cannot_be_deleted(): void
    {
        $waka = User::where('username', 'waka')->first();
        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();

        Supervision::create([
            'teacher_id' => $teacher->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-10-10',
            'subject' => 'Dasar Program Keahlian',
            'class_name' => 'X TKRO 1',
            'status' => 'terjadwal',
        ]);

        $response = $this->actingAs($waka)->delete(route('teachers.destroy', $teacher));

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
    }

    public function test_principal_rejection_notes_are_displayed_on_supervision_show(): void
    {
        $guru = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first();
        $waka = User::where('username', 'waka')->first();

        $spv = Supervision::create([
            'teacher_id' => $guru->id,
            'supervisor_id' => $waka->id,
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'schedule_date' => '2026-10-10',
            'subject' => 'Bahasa Indonesia',
            'class_name' => 'X TKRO 1',
            'status' => 'pascaobservasi',
            'approved_by_principal' => false,
            'principal_notes' => 'Harap lengkapi catatan tindak lanjut dan lampiran foto observasi.',
        ]);

        $response = $this->actingAs($guru)->get(route('supervisions.show', $spv));

        $response->assertStatus(200);
        $response->assertSee('Catatan Revisi / Arahan dari Kepala Sekolah');
        $response->assertSee('Harap lengkapi catatan tindak lanjut dan lampiran foto observasi.');
    }
}
