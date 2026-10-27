<?php

use App\Models\StructuralPosition;
use App\Models\TeacherStructuralAssignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Copy nigs_fix value into nigs where nigs_fix is present
        if (Schema::hasColumn('users', 'nigs_fix') && Schema::hasColumn('users', 'nigs')) {
            DB::statement("UPDATE users SET nigs = nigs_fix WHERE nigs_fix IS NOT NULL AND nigs_fix != ''");
        }

        // 2. Drop nigs_fix column
        if (Schema::hasColumn('users', 'nigs_fix')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('nigs_fix');
            });
        }

        // 3. Register Staff & Operational Structural Positions
        $staffPositions = [
            [
                'code' => 'STAF_TU',
                'name' => 'Staf Tenaga Administrasi',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 20,
                'description' => 'Staf Pelaksana Tata Usaha dan Administrasi Sekolah',
                'is_active' => true,
            ],
            [
                'code' => 'SATPAM',
                'name' => 'Satpam',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 21,
                'description' => 'Petugas Keamanan dan Ketertiban Lingkungan Sekolah',
                'is_active' => true,
            ],
            [
                'code' => 'PENJAGA',
                'name' => 'Penjaga',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 22,
                'description' => 'Penjaga dan Pemelihara Keamanan Gedung Sekolah',
                'is_active' => true,
            ],
            [
                'code' => 'TOOLMAN_TKJ',
                'name' => 'Toolman TKJ dan AK',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 23,
                'description' => 'Teknisi dan Pengelola Alat Laboratorium Komputer / TKJ',
                'is_active' => true,
            ],
            [
                'code' => 'TOOLMAN_TKR',
                'name' => 'Toolman TKR',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 24,
                'description' => 'Teknisi dan Pengelola Alat Bengkel Otomotif / TKR',
                'is_active' => true,
            ],
            [
                'code' => 'KEBUN',
                'name' => 'Pesuruh/Wali Kebun',
                'category' => 'Tenaga Kependidikan & Layanan',
                'order' => 25,
                'description' => 'Petugas Kebersihan, Taman, dan Keasrian Lingkungan Sekolah',
                'is_active' => true,
            ],
        ];

        foreach ($staffPositions as $sp) {
            StructuralPosition::firstOrCreate(
                ['code' => $sp['code']],
                $sp
            );
        }

        // 4. Map user duties to teacher_structural_assignments before standardizing position column
        $academicYear = DB::table('school_settings')->value('academic_year') ?? '2026/2027';

        $positionMapping = [
            'Ka. TU' => 'KA_TU',
            'Staf Tenaga Administrasi' => 'STAF_TU',
            'Satpam' => 'SATPAM',
            'Penjaga' => 'PENJAGA',
            'Toolman TKJ dan AK' => 'TOOLMAN_TKJ',
            'Toolman TKR' => 'TOOLMAN_TKR',
            'Pesuruh/Wali Kebun' => 'KEBUN',
            'Bendahara Sekolah' => 'BENDAHARA',
            'Ka. Lab Komputer' => 'KA_LAB_KOMP',
            'Ka. Bengkel' => 'KA_BENGKEL',
            'Kaprogli TKR' => 'KAPRO_TKR',
            'Kaprogli TKJ' => 'KAPRO_TKJ',
            'Kaprogli AKL' => 'KAPRO_AKL',
            'Waka Kurikulum' => 'WAKA_KUR',
            'Waka Kesiswaan' => 'WAKA_KESISWAAN',
            'Waka Sarpras' => 'WAKA_SARPRAS',
            'Waka Humas' => 'WAKA_HUMAS',
            'Kepala Sekolah' => 'KS',
        ];

        $users = DB::table('users')->select('id', 'position')->get();
        foreach ($users as $user) {
            if (empty($user->position)) {
                continue;
            }

            foreach ($positionMapping as $rawTitle => $posCode) {
                if (strcasecmp($user->position, $rawTitle) === 0 || str_contains(strtolower($user->position), strtolower($rawTitle))) {
                    $pos = StructuralPosition::where('code', $posCode)->first();
                    if ($pos) {
                        TeacherStructuralAssignment::firstOrCreate(
                            [
                                'teacher_id' => $user->id,
                                'structural_position_id' => $pos->id,
                                'academic_year' => $academicYear,
                            ],
                            [
                                'is_active' => true,
                                'sk_date' => now()->toDateString(),
                            ]
                        );
                    }
                    break;
                }
            }
        }

        // 5. Standardize users.position column to only: 'guru', 'staf', 'administrator'
        // First: Administrators
        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        if ($superAdminRoleId) {
            DB::table('users')->where('role_id', $superAdminRoleId)->update(['position' => 'administrator']);
        }
        DB::table('users')
            ->where(function ($q) {
                $q->where('position', 'like', '%administrator%')
                    ->orWhere('username', 'like', '%admin%');
            })
            ->where('position', '!=', 'staf')
            ->update(['position' => 'administrator']);

        // Second: Staf (Non-teaching educational personnel)
        $staffKeywords = [
            'ka. tu',
            'tata usaha',
            'administrasi',
            'satpam',
            'penjaga',
            'toolman',
            'pesuruh',
            'kebun',
            'staf',
        ];

        foreach ($staffKeywords as $kw) {
            DB::table('users')
                ->where('position', '!=', 'administrator')
                ->where('position', 'like', "%{$kw}%")
                ->update(['position' => 'staf']);
        }

        // Third: All others become 'guru'
        DB::table('users')
            ->whereNotIn('position', ['administrator', 'staf'])
            ->update(['position' => 'guru']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'nigs_fix')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nigs_fix')->nullable()->after('nigs');
            });

            DB::statement('UPDATE users SET nigs_fix = nigs WHERE nigs IS NOT NULL');
        }
    }
};
