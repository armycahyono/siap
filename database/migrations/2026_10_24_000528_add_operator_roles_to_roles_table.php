<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. New Permissions for Operator Segments
        $newPermissions = [
            [
                'slug' => 'operate_curriculum',
                'name' => 'Operasional Kurikulum & Jadwal',
                'group' => 'Kurikulum',
            ],
            [
                'slug' => 'operate_cbt',
                'name' => 'Sinkronisasi & Operasional CBT',
                'group' => 'Kurikulum',
            ],
            [
                'slug' => 'operate_student_affairs',
                'name' => 'Operasional Kesiswaan & Kedisiplinan',
                'group' => 'Kesiswaan',
            ],
            [
                'slug' => 'operate_sarpras',
                'name' => 'Operasional Sarana & Prasarana',
                'group' => 'Sarpras',
            ],
            [
                'slug' => 'operate_humas',
                'name' => 'Operasional Humas & Kemitraan DUDI',
                'group' => 'Humas',
            ],
        ];

        foreach ($newPermissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // Give new permissions to Super Admin and Waka Kurikulum
        $superAdmin = Role::where('slug', 'super_admin')->first();
        if ($superAdmin) {
            $allPermIds = Permission::pluck('id')->toArray();
            $superAdmin->permissions()->sync($allPermIds);
        }

        $wakaKurikulum = Role::where('slug', 'waka_kurikulum')->first();
        if ($wakaKurikulum) {
            $curriculumPerms = Permission::whereIn('slug', [
                'operate_curriculum',
                'operate_cbt',
            ])->pluck('id')->toArray();
            $wakaKurikulum->permissions()->syncWithoutDetaching($curriculumPerms);
        }

        // 2. New Operator Roles
        $operatorRoles = [
            [
                'slug' => 'operator_kurikulum',
                'name' => 'Operator Kurikulum',
                'description' => 'Membantu operasional kurikulum, jadwal pelajaran, kalender kegiatan, dan integrasi CBT',
                'permissions' => [
                    'manage_master_data',
                    'manage_schedules',
                    'upload_rpp',
                    'view_own_supervision',
                    'export_reports',
                    'operate_curriculum',
                    'operate_cbt',
                ],
            ],
            [
                'slug' => 'operator_kesiswaan',
                'name' => 'Operator Kesiswaan',
                'description' => 'Membantu operasional kesiswaan, catatan kasus & kejadian siswa, dan kedisiplinan',
                'permissions' => [
                    'access_piket',
                    'operate_piket',
                    'manage_piket_cases',
                    'view_piket_reports',
                    'operate_student_affairs',
                ],
            ],
            [
                'slug' => 'operator_sarpras',
                'name' => 'Operator Sarpras',
                'description' => 'Membantu operasional sarana prasarana, inventaris alat & ruang kelas/lab',
                'permissions' => [
                    'export_reports',
                    'operate_sarpras',
                ],
            ],
            [
                'slug' => 'operator_humas',
                'name' => 'Operator Humas',
                'description' => 'Membantu operasional kemitraan industri (DUDI), penempatan PKL/Prakerin, dan tracer study',
                'permissions' => [
                    'export_reports',
                    'operate_humas',
                ],
            ],
            [
                'slug' => 'operator',
                'name' => 'Operator Dapodik / TU',
                'description' => 'Membantu operasional administrasi sekolah, pendataan pokok GTK dan siswa',
                'permissions' => [
                    'manage_master_data',
                    'export_reports',
                ],
            ],
        ];

        foreach ($operatorRoles as $r) {
            $role = Role::firstOrCreate(
                ['slug' => $r['slug']],
                ['name' => $r['name'], 'description' => $r['description']]
            );

            $permIds = Permission::whereIn('slug', $r['permissions'])->pluck('id')->toArray();
            $role->permissions()->sync($permIds);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $operatorSlugs = [
            'operator_kurikulum',
            'operator_kesiswaan',
            'operator_sarpras',
            'operator_humas',
            'operator',
        ];

        Role::whereIn('slug', $operatorSlugs)->delete();

        $permSlugs = [
            'operate_curriculum',
            'operate_cbt',
            'operate_student_affairs',
            'operate_sarpras',
            'operate_humas',
        ];

        Permission::whereIn('slug', $permSlugs)->delete();
    }
};
