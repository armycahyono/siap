<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

class HomeroomAssignmentService
{
    /**
     * Tetapkan atau perbarui penugasan wali kelas pada rombel serta sinkronkan contextual role.
     *
     * @param  Classroom  $classroom  Model Rombel yang ditugaskan
     * @param  int|null  $newTeacherId  ID guru baru, atau null jika dikosongkan
     * @param  int|null  $assignedBy  ID user yang melakukan penugasan (opsional)
     */
    public function assignHomeroom(Classroom $classroom, ?int $newTeacherId, ?int $assignedBy = null): void
    {
        DB::transaction(function () use ($classroom, $newTeacherId, $assignedBy) {
            // 1. Perbarui kolom homeroom_teacher_id pada tabel classrooms (Single Source of Truth)
            if ((int) $classroom->homeroom_teacher_id !== (int) $newTeacherId) {
                $classroom->update(['homeroom_teacher_id' => $newTeacherId]);
            }

            // 2. Sinkronkan contextual role pada user_role_assignments
            $this->syncHomeroomRole($classroom, $newTeacherId, $assignedBy);
        });
    }

    /**
     * Sinkronkan penugasan peran kontekstual 'wali_kelas' pada user_role_assignments.
     */
    public function syncHomeroomRole(Classroom $classroom, ?int $newTeacherId, ?int $assignedBy = null): void
    {
        $roleWali = Role::firstOrCreate(
            ['slug' => 'wali_kelas'],
            ['name' => 'Wali Kelas', 'description' => 'Wali Kelas binaan rombongan belajar']
        );

        // 1. Nonaktifkan penugasan peran aktif sebelumnya untuk rombel ini (kecuali jika guru yang sama)
        $previousQuery = UserRoleAssignment::where('role_id', $roleWali->id)
            ->where('context_type', Classroom::class)
            ->where('context_id', $classroom->id)
            ->where('status', true);

        if ($newTeacherId) {
            $previousQuery->where('user_id', '!=', $newTeacherId);
        }

        $previousQuery->update([
            'status' => false,
            'valid_until' => now()->toDateString(),
        ]);

        // 2. Jika ada guru baru yang ditugaskan, aktifkan atau buat record penugasan peran
        if ($newTeacherId) {
            UserRoleAssignment::updateOrCreate(
                [
                    'user_id' => $newTeacherId,
                    'role_id' => $roleWali->id,
                    'context_type' => Classroom::class,
                    'context_id' => $classroom->id,
                ],
                [
                    'academic_year' => $classroom->academic_year,
                    'academic_year_id' => $classroom->academic_year_id,
                    'valid_from' => now()->toDateString(),
                    'valid_until' => null,
                    'status' => true,
                    'notes' => "Wali Kelas {$classroom->name}",
                    'assigned_by' => $assignedBy ?? auth()->id(),
                ]
            );
        }
    }

    /**
     * Bersihkan record contextual role saat rombel dihapus dari sistem.
     */
    public function cleanupClassroomRoles(Classroom $classroom): void
    {
        $roleWali = Role::where('slug', 'wali_kelas')->first();
        if ($roleWali) {
            UserRoleAssignment::where('role_id', $roleWali->id)
                ->where('context_type', Classroom::class)
                ->where('context_id', $classroom->id)
                ->delete();
        }
    }
}
