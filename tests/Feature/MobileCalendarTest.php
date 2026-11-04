<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CurriculumEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $curriculumUser;

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
        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator']);

        $this->teacher = User::create([
            'name' => 'Guru Kalender Test',
            'username' => 'gurukalender',
            'email' => 'guru.cal@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $this->curriculumUser = User::create([
            'name' => 'Kurikulum Cal Test',
            'username' => 'kurikulumcal',
            'email' => 'kurikulum.cal@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_mobile_calendar(): void
    {
        $response = $this->getJson('/api/v1/mobile/calendar');

        $response->assertStatus(401);
    }

    public function test_authenticated_teacher_can_fetch_calendar_month_data(): void
    {
        // Create sample events
        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Asesmen Sumatif Akhir',
            'category' => 'kegiatan_kurikulum',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'is_all_day' => true,
            'is_holiday' => false,
            'created_by' => $this->curriculumUser->id,
        ]);

        CurriculumEvent::create([
            'academic_year' => '2026/2027',
            'title' => 'Hari Libur Khusus',
            'category' => 'lhb',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-20',
            'is_all_day' => true,
            'is_holiday' => true,
            'created_by' => $this->curriculumUser->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/mobile/calendar?month=2026-11');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'month' => '2026-11',
            ])
            ->assertJsonStructure([
                'success',
                'academic_year',
                'month',
                'month_name',
                'prev_month',
                'next_month',
                'stats' => [
                    'effective_days',
                    'holiday_days',
                    'event_days',
                    'total_events',
                ],
                'days' => [
                    '*' => [
                        'date',
                        'day_num',
                        'day_name',
                        'is_current_month',
                        'is_today',
                        'is_sunday',
                        'is_holiday',
                        'events_count',
                        'events',
                        'dots',
                    ],
                ],
                'categories',
                'can_create',
            ]);

        $days = collect($response->json('days'));
        $day20 = $days->firstWhere('date', '2026-11-20');
        $this->assertNotNull($day20);
        $this->assertTrue($day20['is_holiday']);
        $this->assertCount(1, $day20['events']);
        $this->assertEquals('Hari Libur Khusus', $day20['events'][0]['title']);
    }

    public function test_teacher_without_permission_cannot_store_calendar_event(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/v1/mobile/calendar/store', [
                'title' => 'Rapat Guru Baru',
                'category' => 'tugas_guru',
                'start_date' => '2026-11-15',
            ]);

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_store_calendar_event(): void
    {
        $response = $this->actingAs($this->curriculumUser)
            ->postJson('/api/v1/mobile/calendar/store', [
                'title' => 'Rapat Kerja Guru Semester Genap',
                'category' => 'tugas_guru',
                'start_date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'location' => 'Ruang Guru Utama',
                'description' => 'Membahas persiapan jadwal semester',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Agenda berhasil ditambahkan ke kalender.',
            ]);

        $this->assertDatabaseHas('curriculum_events', [
            'title' => 'Rapat Kerja Guru Semester Genap',
            'category' => 'tugas_guru',
            'start_date' => '2026-11-25',
        ]);
    }

    public function test_waka_and_operator_can_store_calendar_event_with_audience_and_impact(): void
    {
        $wakaRole = Role::firstOrCreate(['slug' => 'waka_sarpras'], ['name' => 'Waka Sarpras']);
        $wakaSarpras = User::create([
            'name' => 'Waka Sarpras Cal Test',
            'username' => 'wakasarprascal',
            'email' => 'waka.sarpras@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $wakaRole->id,
            'position' => 'Waka Bidang Sarpras',
            'is_active' => true,
        ]);

        $opRole = Role::firstOrCreate(['slug' => 'operator_kesiswaan'], ['name' => 'Operator Kesiswaan']);
        $opKesiswaan = User::create([
            'name' => 'Operator Kesiswaan Cal Test',
            'username' => 'opkesiswaancal',
            'email' => 'op.kesiswaan@test.sch.id',
            'password' => bcrypt('password'),
            'role_id' => $opRole->id,
            'is_active' => true,
        ]);

        // 1. Waka Sarpras adds event
        $resWaka = $this->actingAs($wakaSarpras)
            ->postJson('/api/v1/mobile/calendar/store', [
                'title' => 'Pemeliharaan Lab dan Bengkel',
                'category' => 'manajemen',
                'start_date' => '2026-11-28',
                'is_all_day' => true,
                'target_audience' => 'guru_staf',
                'learning_impact' => 'normal',
            ]);

        $resWaka->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // 2. Operator Kesiswaan adds event with Tingkat & Pulang Cepat
        $resOp = $this->actingAs($opKesiswaan)
            ->postJson('/api/v1/mobile/calendar/store', [
                'title' => 'Gladi Bersih Pelantikan Penegak Bantara',
                'category' => 'kesiswaan',
                'start_date' => '2026-11-29',
                'end_date' => '2026-11-29',
                'is_all_day' => false,
                'start_time' => '07:00',
                'end_time' => '11:00',
                'target_audience' => 'tingkat',
                'target_grade_levels' => ['10'],
                'learning_impact' => 'pulang_cepat',
                'max_period' => 5,
            ]);

        $resOp->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('curriculum_events', [
            'title' => 'Gladi Bersih Pelantikan Penegak Bantara',
            'category' => 'kesiswaan',
            'target_audience' => 'tingkat',
            'learning_impact' => 'pulang_cepat',
            'max_period' => 5,
        ]);
    }
}
