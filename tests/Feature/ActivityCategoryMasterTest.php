<?php

namespace Tests\Feature;

use App\Models\ActivityCategory;
use App\Models\Role;
use App\Models\SchoolActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityCategoryMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Administrator', 'description' => 'Akses penuh']
        );

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test'],
            [
                'name' => 'Admin Penguji',
                'email' => 'admin_test@example.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
    }

    public function test_admin_can_view_activity_categories_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('activity-categories.index'));

        $response->assertStatus(200);
        $response->assertSee('Data Pokok: Kategori Kegiatan');
        $response->assertSee('Kegiatan Khusus SMK');
        $response->assertSee('Asesmen & Ujian Akademik');
    }

    public function test_can_create_new_activity_category(): void
    {
        $payload = [
            'name' => 'Ekstrakurikuler & Kejuaraan',
            'code' => 'ekskul_lomba',
            'color' => '#10b981',
            'badge_class' => 'bg-success text-white',
            'description' => 'Kegiatan kepramukaan, PMR, paskibra, dan kejuaraan siswa',
            'is_kaldik' => 0,
            'is_active' => 1,
            'order_index' => 10,
        ];

        $response = $this->actingAs($this->admin)->post(route('activity-categories.store'), $payload);

        $response->assertRedirect(route('activity-categories.index'));
        $this->assertDatabaseHas('activity_categories', [
            'name' => 'Ekstrakurikuler & Kejuaraan',
            'code' => 'ekskul_lomba',
            'color' => '#10b981',
        ]);

        // Verify it dynamically shows up in SchoolActivity category definitions
        $defs = SchoolActivity::categoryDefinitions();
        $this->assertArrayHasKey('ekskul_lomba', $defs);
        $this->assertEquals('Ekstrakurikuler & Kejuaraan', $defs['ekskul_lomba']['label']);
    }

    public function test_can_update_activity_category(): void
    {
        $category = ActivityCategory::create([
            'name' => 'Kategori Uji Coba',
            'code' => 'uji_coba',
            'color' => '#3b82f6',
            'description' => 'Deskripsi lama',
            'is_kaldik' => false,
            'is_active' => true,
            'order_index' => 99,
        ]);

        $payload = [
            'name' => 'Kategori Uji Coba Updated',
            'code' => 'uji_coba_baru',
            'color' => '#ef4444',
            'description' => 'Deskripsi baru yang sudah diubah',
            'is_kaldik' => 1,
            'is_active' => 1,
            'order_index' => 5,
        ];

        $response = $this->actingAs($this->admin)->put(route('activity-categories.update', $category), $payload);

        $response->assertRedirect(route('activity-categories.index'));
        $this->assertDatabaseHas('activity_categories', [
            'id' => $category->id,
            'name' => 'Kategori Uji Coba Updated',
            'code' => 'uji_coba_baru',
            'color' => '#ef4444',
            'is_kaldik' => 1,
        ]);
    }

    public function test_cannot_delete_category_when_used_by_activities(): void
    {
        $category = ActivityCategory::create([
            'name' => 'Kategori Terpakai',
            'code' => 'terpakai_code',
            'color' => '#8b5cf6',
            'is_active' => true,
        ]);

        SchoolActivity::create([
            'name' => 'Agenda Yang Memakai Kategori Ini',
            'category' => 'terpakai_code',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('activity-categories.destroy', $category));

        $response->assertRedirect(route('activity-categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('activity_categories', ['id' => $category->id]);
    }

    public function test_can_delete_unused_category(): void
    {
        $category = ActivityCategory::create([
            'name' => 'Kategori Tidak Terpakai',
            'code' => 'tidak_terpakai',
            'color' => '#94a3b8',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('activity-categories.destroy', $category));

        $response->assertRedirect(route('activity-categories.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('activity_categories', ['id' => $category->id]);
    }
}
