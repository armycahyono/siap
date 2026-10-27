<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSupervisorAssignment;
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
        // 1. Create table user_role_assignments
        if (! Schema::hasTable('user_role_assignments')) {
            Schema::create('user_role_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                $table->string('academic_year', 20)->nullable();
                $table->string('context_type', 150)->nullable();
                $table->unsignedBigInteger('context_id')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->boolean('status')->default(true);
                $table->string('sk_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'idx_ura_user_status');
                $table->index(['role_id', 'status'], 'idx_ura_role_status');
                $table->index(['context_type', 'context_id'], 'idx_ura_context');
                $table->index(['academic_year'], 'idx_ura_academic_year');
            });
        }

        // 2. Ensure base & assignment roles exist in `roles`
        $roleStaf = Role::firstOrCreate(
            ['slug' => 'staf'],
            ['name' => 'Staf / Tendik', 'description' => 'Tenaga Kependidikan & Administrasi Sekolah']
        );

        $roleGuru = Role::firstOrCreate(
            ['slug' => 'guru'],
            ['name' => 'Guru', 'description' => 'Dewan Guru Pengajar']
        );

        $roleAdmin = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Akses penuh ke seluruh sistem']
        );

        $roleWaliKelas = Role::firstOrCreate(
            ['slug' => 'wali_kelas'],
            ['name' => 'Wali Kelas', 'description' => 'Wali Kelas binaan rombongan belajar']
        );

        $roleGuruPiket = Role::firstOrCreate(
            ['slug' => 'guru_piket'],
            ['name' => 'Guru Piket', 'description' => 'Petugas Piket harian sekolah']
        );

        $roleWakaKur = Role::firstOrCreate(
            ['slug' => 'waka_kurikulum'],
            ['name' => 'Waka Kurikulum', 'description' => 'Pengelola operasional kurikulum, jadwal, dan guru']
        );

        $roleWakaKesiswaan = Role::firstOrCreate(
            ['slug' => 'waka_kesiswaan'],
            ['name' => 'Waka Kesiswaan', 'description' => 'Wakil Kepala Sekolah Bidang Kesiswaan & Kedisiplinan']
        );

        $roleWakaSarpras = Role::firstOrCreate(
            ['slug' => 'waka_sarpras'],
            ['name' => 'Waka Sarpras', 'description' => 'Wakil Kepala Sekolah Bidang Sarana & Prasarana']
        );

        $roleWakaHumas = Role::firstOrCreate(
            ['slug' => 'waka_humas'],
            ['name' => 'Waka Humas', 'description' => 'Wakil Kepala Sekolah Bidang Hubungan Masyarakat & DUDI']
        );

        $roleKS = Role::firstOrCreate(
            ['slug' => 'kepala_sekolah'],
            ['name' => 'Kepala Sekolah', 'description' => 'Monitoring dan pengesahan seluruh kegiatan sekolah']
        );

        $roleSupervisor = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'description' => 'Tim penilai supervisi pembelajaran KBM']
        );

        // 3. Ensure permissions exist
        $permManageHomeroom = Permission::firstOrCreate(
            ['slug' => 'manage_homeroom'],
            ['name' => 'Kelola Kelas Binaan (Wali Kelas)', 'group' => 'Akademik']
        );

        // Map permissions to roles
        $this->attachPermissionSafely($roleWaliKelas->id, $permManageHomeroom->id);
        $this->attachPermissionSafely($roleWakaKur->id, $permManageHomeroom->id);
        $this->attachPermissionSafely($roleKS->id, $permManageHomeroom->id);
        $this->attachPermissionSafely($roleAdmin->id, $permManageHomeroom->id);

        $accessPiketPerm = Permission::where('slug', 'access_piket')->first();
        $operatePiketPerm = Permission::where('slug', 'operate_piket')->first();
        if ($accessPiketPerm) {
            $this->attachPermissionSafely($roleGuruPiket->id, $accessPiketPerm->id);
        }
        if ($operatePiketPerm) {
            $this->attachPermissionSafely($roleGuruPiket->id, $operatePiketPerm->id);
        }

        // 4. Resolve Active Academic Year
        $activeAy = AcademicYear::where('is_active', true)->first();
        $ayId = $activeAy?->id;
        $ayName = $activeAy?->name ?? '2026/2027';

        // 5. Migrate Special Roles currently in `users.role_id` into `user_role_assignments`
        $specialUsers = User::whereHas('role', function ($q) {
            $q->whereIn('slug', [
                'waka_kurikulum',
                'kepala_sekolah',
                'supervisor',
                'operator_kurikulum',
                'operator_kesiswaan',
                'operator_sarpras',
                'operator_humas',
                'operator',
            ]);
        })->get();

        foreach ($specialUsers as $spUser) {
            DB::table('user_role_assignments')->updateOrInsert(
                [
                    'user_id' => $spUser->id,
                    'role_id' => $spUser->role_id,
                    'context_type' => null,
                    'context_id' => null,
                    'academic_year' => $ayName,
                ],
                [
                    'academic_year_id' => $ayId,
                    'status' => true,
                    'notes' => "Penugasan otomatis dari peran akun: {$spUser->role?->name}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 6. Migrate Wali Kelas from `classrooms`
        $classroomsWithHomeroom = Classroom::whereNotNull('homeroom_teacher_id')->get();
        foreach ($classroomsWithHomeroom as $cls) {
            DB::table('user_role_assignments')->updateOrInsert(
                [
                    'user_id' => $cls->homeroom_teacher_id,
                    'role_id' => $roleWaliKelas->id,
                    'context_type' => 'App\\Models\\Classroom',
                    'context_id' => $cls->id,
                    'academic_year' => $ayName,
                ],
                [
                    'academic_year_id' => $ayId,
                    'status' => true,
                    'notes' => "Wali Kelas {$cls->name}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 7. Migrate Structural Assignments
        $structuralAssignments = TeacherStructuralAssignment::with('structuralPosition')
            ->where('is_active', true)
            ->get();

        foreach ($structuralAssignments as $sa) {
            $code = strtoupper($sa->structuralPosition->code ?? '');
            $targetRole = match ($code) {
                'KS' => $roleKS,
                'WAKA_KUR' => $roleWakaKur,
                'WAKA_KESISWAAN' => $roleWakaKesiswaan,
                'WAKA_SARPRAS' => $roleWakaSarpras,
                'WAKA_HUMAS' => $roleWakaHumas,
                default => null,
            };

            if ($targetRole) {
                DB::table('user_role_assignments')->updateOrInsert(
                    [
                        'user_id' => $sa->teacher_id,
                        'role_id' => $targetRole->id,
                        'context_type' => null,
                        'context_id' => null,
                        'academic_year' => $sa->academic_year ?? $ayName,
                    ],
                    [
                        'academic_year_id' => $ayId,
                        'status' => true,
                        'sk_number' => $sa->sk_number,
                        'notes' => $sa->notes ?: "Penugasan Struktural {$sa->structuralPosition->name}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // 8. Migrate Supervisor Assignments
        $supervisorAssignments = TeacherSupervisorAssignment::all();
        foreach ($supervisorAssignments as $sua) {
            DB::table('user_role_assignments')->updateOrInsert(
                [
                    'user_id' => $sua->teacher_id,
                    'role_id' => $roleSupervisor->id,
                    'context_type' => null,
                    'context_id' => null,
                    'academic_year' => $sua->academic_year ?? $ayName,
                ],
                [
                    'academic_year_id' => $ayId,
                    'status' => true,
                    'notes' => 'Tim Penilai Supervisi Pembelajaran',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 9. Standardize primary `users.role_id` to user type:
        //    - position = 'administrator' -> super_admin (role_id 1)
        //    - position = 'staf'          -> staf
        //    - position = 'guru'          -> guru
        User::where('position', 'administrator')->update(['role_id' => $roleAdmin->id]);
        User::where('position', 'staf')->update(['role_id' => $roleStaf->id]);
        User::where('position', 'guru')->update(['role_id' => $roleGuru->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }

    private function attachPermissionSafely(int $roleId, int $permissionId): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role_id' => $roleId, 'permission_id' => $permissionId],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
};
