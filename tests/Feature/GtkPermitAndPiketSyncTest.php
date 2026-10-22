<?php

namespace Tests\Feature;

use App\Models\GtkPermit;
use App\Models\PiketJournal;
use App\Models\Role;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GtkPermitAndPiketSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'super_admin')->first()?->id,
                'is_active' => true,
            ]);

        $this->teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);
    }

    public function test_can_view_gtk_permits_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('attendance.gtk-permits.index'));
        $response->assertStatus(200);
        $response->assertSee('Perizinan &amp; Cuti Guru', false);
    }

    public function test_can_store_multi_day_gtk_permit_with_attachment(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('surat_dokter.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('attendance.gtk-permits.store'), [
            'user_id' => $this->teacher->id,
            'permission_type' => 'sakit',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'reason' => 'Demam dan istirahat dokter',
            'document' => $file,
            'notes' => 'Surat dokter terlampir',
        ]);

        $response->assertRedirect(route('attendance.gtk-permits.index'));
        $this->assertDatabaseHas('gtk_permits', [
            'user_id' => $this->teacher->id,
            'permission_type' => 'sakit',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'duration_days' => 3,
            'reason' => 'Demam dan istirahat dokter',
        ]);
    }

    public function test_piket_journal_show_does_not_contain_piket_attendances(): void
    {
        $journal = PiketJournal::create([
            'date' => '2026-10-05',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'status' => 'berlangsung',
            'coordinator_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('piket.journals.show', $journal));
        $response->assertStatus(200);
        $response->assertDontSee('1. Presensi & Kehadiran Petugas Piket');
        $response->assertSee('Guru Berhalangan & Guru Inval', false);
    }

    public function test_gtk_permit_reflects_in_attendance_report(): void
    {
        GtkPermit::create([
            'user_id' => $this->teacher->id,
            'permission_type' => 'cuti',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'duration_days' => 2,
            'reason' => 'Cuti Tahunan',
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('attendance.report.teachers', [
            'period_type' => 'custom',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Cuti');
    }

    public function test_gtk_permit_displays_on_dashboard(): void
    {
        $today = now()->toDateString();
        GtkPermit::create([
            'user_id' => $this->teacher->id,
            'permission_type' => 'izin',
            'status' => GtkPermit::STATUS_APPROVED,
            'start_date' => $today,
            'end_date' => $today,
            'duration_days' => 1,
            'reason' => 'Keperluan Keluarga Mendesak',
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Pemberitahuan Izin & Cuti GTK Hari Ini', false);
        $response->assertSee($this->teacher->name);
        $response->assertSee('Keperluan Keluarga Mendesak');
    }

    public function test_telegram_service_notify_gtk_permit(): void
    {
        $permit = GtkPermit::create([
            'user_id' => $this->teacher->id,
            'permission_type' => 'dinas_luar',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'duration_days' => 2,
            'reason' => 'Workshop Kurikulum',
            'approved_by' => $this->admin->id,
        ]);

        $telegramService = app(TelegramService::class);
        $result = $telegramService->notifyGtkPermit($permit, $this->admin);

        // Result can be true or false depending on whether bot token and chat_ids exist, but method must execute cleanly
        $this->assertIsBool($result);
    }

    public function test_individual_recap_table_contains_required_columns_and_keterangan(): void
    {
        GtkPermit::create([
            'user_id' => $this->teacher->id,
            'permission_type' => 'sakit',
            'status' => GtkPermit::STATUS_APPROVED,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'duration_days' => 1,
            'reason' => 'Demam dan Flu',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('attendance.report.teachers', [
            'period_type' => 'custom',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'user_id' => $this->teacher->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Jam Masuk');
        $response->assertSee('Jam Pulang');
        $response->assertSee('Status');
        $response->assertSee('Keterangan');
        $response->assertSee('Demam dan Flu');
    }

    public function test_gtk_self_submission_is_pending_and_can_be_approved_by_principal(): void
    {
        $principal = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first()
            ?? User::factory()->create([
                'username' => 'kepsek_test',
                'role_id' => Role::where('slug', 'kepala_sekolah')->first()?->id ?? 5,
                'is_active' => true,
            ]);

        // 1. Guru mengajukan izin mandiri
        $response = $this->actingAs($this->teacher)->post(route('attendance.gtk-permits.store'), [
            'user_id' => $this->teacher->id,
            'permission_type' => 'izin',
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
            'reason' => 'Urusan Bank',
        ]);

        $response->assertRedirect(route('attendance.gtk-permits.index'));

        $permit = GtkPermit::where('user_id', $this->teacher->id)
            ->where('reason', 'Urusan Bank')
            ->first();

        $this->assertNotNull($permit);
        $this->assertEquals(GtkPermit::STATUS_PENDING, $permit->status);
        $this->assertNull($permit->approved_by);

        // 2. Guru lain tidak berhak menyetujui
        $otherTeacher = User::factory()->create([
            'username' => 'guru2_test',
            'role_id' => Role::where('slug', 'guru')->first()?->id ?? 4,
            'is_active' => true,
        ]);
        $unauthResponse = $this->actingAs($otherTeacher)->post(route('attendance.gtk-permits.approve', $permit));
        $unauthResponse->assertRedirect();
        $this->assertEquals(GtkPermit::STATUS_PENDING, $permit->fresh()->status);

        // 3. Kepala Sekolah menyetujui izin
        $approveResponse = $this->actingAs($principal)->post(route('attendance.gtk-permits.approve', $permit), [
            'approval_note' => 'Disetujui, silakan titipkan tugas.',
        ]);

        $approveResponse->assertRedirect(route('attendance.gtk-permits.index'));
        $this->assertEquals(GtkPermit::STATUS_APPROVED, $permit->fresh()->status);
        $this->assertEquals($principal->id, $permit->fresh()->approved_by);
        $this->assertEquals('Disetujui, silakan titipkan tugas.', $permit->fresh()->approval_note);
    }

    public function test_principal_can_reject_gtk_permit_with_reason(): void
    {
        $principal = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first()
            ?? User::factory()->create([
                'username' => 'kepsek_test2',
                'role_id' => Role::where('slug', 'kepala_sekolah')->first()?->id ?? 5,
                'is_active' => true,
            ]);

        $permit = GtkPermit::create([
            'user_id' => $this->teacher->id,
            'permission_type' => 'cuti',
            'status' => GtkPermit::STATUS_PENDING,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-16',
            'duration_days' => 2,
            'reason' => 'Liburan',
        ]);

        $response = $this->actingAs($principal)->post(route('attendance.gtk-permits.reject', $permit), [
            'approval_note' => 'Jadwal ujian semester, mohon reschedule.',
        ]);

        $response->assertRedirect(route('attendance.gtk-permits.index'));
        $this->assertEquals(GtkPermit::STATUS_REJECTED, $permit->fresh()->status);
        $this->assertEquals('Jadwal ujian semester, mohon reschedule.', $permit->fresh()->approval_note);
    }
}
