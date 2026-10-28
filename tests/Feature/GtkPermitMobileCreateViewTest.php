<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtkPermitMobileCreateViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_create_view_renders_correctly_with_new_layout(): void
    {
        $this->seed();

        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);

        // Access route as mobile client (Android User-Agent)
        $response = $this->actingAs($teacher)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36'])
            ->get(route('attendance.gtk-permits.create'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile.attendance.gtk_permits_create');
        // Check for 2-row category pills
        $response->assertSee('pill-sakit', false);
        $response->assertSee('pill-izin', false);
        $response->assertSee('pill-cuti', false);
        $response->assertSee('pill-dinas', false);
        // Check for 1-row date range with s/d
        $response->assertSee('Rentang Hari Izin', false);
        $response->assertSee('s/d', false);
        // Ordinary teacher should NOT see dropdown to select others
        $response->assertDontSee('Pilih Guru / Tenaga Kependidikan', false);
    }

    public function test_principal_sees_approval_and_today_permit_lists_on_tasks_page(): void
    {
        $this->seed();

        $principal = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'kepala_sekolah')->first()?->id,
                'is_active' => true,
            ]);

        $response = $this->actingAs($principal)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36'])
            ->get(route('tasks'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile.tasks');
        $response->assertSee('Daftar Izin &amp; Cuti GTK', false);
        // When there is no pending permit, 'Perlu Persetujuan' should NOT be shown
        $response->assertDontSee('Perlu Persetujuan', false);
        $response->assertSee('Izin Hari Ini', false);
        $response->assertDontSee('Ajukan Izin / Cuti Baru', false);
    }

    public function test_teacher_sees_self_permit_card_on_tasks_page(): void
    {
        $this->seed();

        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::factory()->create([
                'role_id' => Role::where('slug', 'guru')->first()?->id,
                'is_active' => true,
            ]);

        $response = $this->actingAs($teacher)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36'])
            ->get(route('tasks'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile.tasks');
        $response->assertSee('Izin / Cuti GTK Mandiri', false);
        $response->assertSee('Ajukan Izin / Cuti Baru', false);
        $response->assertDontSee('Perlu Persetujuan', false);
    }

    public function test_mobile_home_shows_pulang_card_and_tasks_shows_supervision_when_pending(): void
    {
        $this->seed();

        $ks = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_sekolah'))->first()
            ?? User::where('position', 'like', '%Kepala Sekolah%')->first();

        if (! $ks) {
            $ksRole = Role::firstOrCreate(['slug' => 'kepala_sekolah'], ['name' => 'Kepala Sekolah']);
            $ks = User::create([
                'name' => 'Kepala Sekolah Test',
                'username' => 'kepsek_test',
                'email' => 'kepsek@example.com',
                'password' => bcrypt('password'),
                'role_id' => $ksRole->id,
                'is_active' => true,
            ]);
        }

        $teacher = User::whereHas('role', fn ($q) => $q->where('slug', 'guru'))->first()
            ?? User::create([
                'name' => 'Guru Test',
                'username' => 'guru_test',
                'email' => 'guru@example.com',
                'password' => bcrypt('password'),
                'role_id' => Role::firstOrCreate(['slug' => 'guru'], ['name' => 'Guru'])->id,
                'is_active' => true,
            ]);

        Supervision::create([
            'teacher_id' => $teacher->id,
            'supervisor_id' => $ks->id,
            'principal_id' => $ks->id,
            'academic_year' => '2025/2026',
            'semester' => 'Genap',
            'status' => 'menunggu_persetujuan',
            'subject' => 'Matematika',
            'class_name' => 'X RPL 1',
            'schedule_date' => now()->toDateString(),
            'obs_date' => now()->toDateString(),
            'predicate' => 'Baik',
        ]);

        $responseHome = $this->actingAs($ks)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36'])
            ->get(route('dashboard'));

        $responseHome->assertStatus(200);
        $responseHome->assertViewIs('mobile.dashboard');
        $responseHome->assertSee('Status Pulang', false);
        $responseHome->assertDontSee('Izin / Cuti GTK:', false);

        $responseTasks = $this->actingAs($ks)
            ->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36'])
            ->get(route('tasks'));

        $responseTasks->assertStatus(200);
        $responseTasks->assertSee('Pengesahan Supervisi', false);
        $responseTasks->assertSee('Menunggu Pengesahan KS / Kurikulum', false);
        $responseTasks->assertSee('Sahkan', false);
        $responseTasks->assertSee('Revisi', false);
        $responseTasks->assertSee('Matematika', false);
        $responseTasks->assertDontSee('route(\'supervisions.', false);
    }
}
