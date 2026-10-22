<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\PiketAttendance;
use App\Models\PiketJournal;
use App\Models\PiketSchedule;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualGtkAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $waka;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'super_admin')->first()?->id,
                'is_active' => true,
            ]);

        $this->waka = User::whereHas('role', fn ($q) => $q->where('slug', 'waka_kurikulum'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'waka_kurikulum')->first()?->id,
                'is_active' => true,
            ]);

        $this->teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);
    }

    public function test_guest_cannot_access_manual_gtk_attendance(): void
    {
        $response = $this->get(route('attendance.manual-gtk.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_teacher_cannot_access_manual_gtk_attendance(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('attendance.manual-gtk.index'));
        $response->assertStatus(403);
    }

    public function test_admin_and_waka_can_access_manual_gtk_attendance_on_web(): void
    {
        $adminResponse = $this->actingAs($this->superAdmin)->get(route('attendance.manual-gtk.index'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Presensi Susulan GTK');
        $adminResponse->assertSee('Pemadaman Listrik');

        $wakaResponse = $this->actingAs($this->waka)->get(route('attendance.manual-gtk.index'));
        $wakaResponse->assertStatus(200);
        $wakaResponse->assertSee('Presensi Susulan GTK');
    }

    public function test_mobile_user_cannot_access_manual_gtk_attendance(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148')
            ->get(route('attendance.manual-gtk.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_store_manual_gtk_attendance_due_to_power_outage(): void
    {
        $targetDate = Carbon::yesterday()->toDateString();

        $response = $this->actingAs($this->superAdmin)->post(route('attendance.manual-gtk.store'), [
            'date' => $targetDate,
            'teacher_ids' => [$this->teacher->id],
            'check_in_time' => '06:45',
            'check_out_time' => '15:00',
            'reason' => 'Pemadaman Listrik Sekolah Seharian',
        ]);

        $response->assertRedirect(route('attendance.manual-gtk.index', ['date' => $targetDate]));
        $response->assertSessionHas('success');

        // Verifikasi log tersimpan di database
        $this->assertDatabaseHas('attendance_logs', [
            'device_sn' => 'MANUAL_SUSULAN',
            'user_type' => 'teacher',
            'user_id' => $this->teacher->id,
            'status' => 'check_in',
            'verify_type' => 'manual',
            'scanned_at' => $targetDate.' 06:45:00',
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'device_sn' => 'MANUAL_SUSULAN',
            'user_type' => 'teacher',
            'user_id' => $this->teacher->id,
            'status' => 'check_out',
            'verify_type' => 'manual',
            'scanned_at' => $targetDate.' 15:00:00',
        ]);
    }

    public function test_manual_gtk_attendance_syncs_piket_journal(): void
    {
        $targetDate = '2026-10-12'; // Senin
        $dayName = 'Senin';

        // Jadwalkan guru piket
        PiketSchedule::firstOrCreate([
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'day' => $dayName,
            'teacher_id' => $this->teacher->id,
        ], [
            'role' => 'anggota',
            'duty_location' => 'Pos Gerbang',
            'is_active' => true,
        ]);

        // Buat jurnal piket pada tanggal tersebut
        $journal = PiketJournal::firstOrCreate([
            'date' => $targetDate,
        ], [
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'status' => 'berlangsung',
        ]);

        // Presensi piket awal belum hadir
        PiketAttendance::firstOrCreate([
            'piket_journal_id' => $journal->id,
            'teacher_id' => $this->teacher->id,
        ], [
            'status' => 'belum_hadir',
            'notes' => 'Pos Gerbang',
        ]);

        $piketAtt = PiketAttendance::where('piket_journal_id', $journal->id)->where('teacher_id', $this->teacher->id)->first();
        $this->assertEquals('belum_hadir', $piketAtt->status);

        // Simpan presensi susulan oleh Waka
        $response = $this->actingAs($this->waka)->post(route('attendance.manual-gtk.store'), [
            'date' => $targetDate,
            'teacher_ids' => [$this->teacher->id],
            'check_in_time' => '06:50',
            'check_out_time' => '14:30',
            'reason' => 'Server Offline Mati Lampu',
        ]);

        $response->assertRedirect();

        // Verifikasi status jurnal piket otomatis tersinkron jadi hadir
        $this->assertEquals('hadir', $piketAtt->fresh()->status);
        $this->assertEquals('06:50:00', $piketAtt->fresh()->check_in_time);
        $this->assertEquals('14:30:00', $piketAtt->fresh()->check_out_time);
    }

    public function test_admin_can_delete_manual_susulan_log(): void
    {
        $log = AttendanceLog::create([
            'device_sn' => 'MANUAL_SUSULAN',
            'pin' => (string) $this->teacher->id,
            'user_type' => 'teacher',
            'user_id' => $this->teacher->id,
            'name' => $this->teacher->name,
            'scanned_at' => now(),
            'status' => 'check_in',
            'verify_type' => 'manual',
            'raw_payload' => json_encode(['reason' => 'Salah input']),
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('attendance.manual-gtk.destroy', $log));
        $response->assertRedirect();
        $this->assertDatabaseMissing('attendance_logs', ['id' => $log->id]);
    }
}
