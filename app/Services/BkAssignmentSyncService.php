<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Role;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

class BkAssignmentSyncService
{
    /**
     * Dapatkan atau pastikan role Guru BK tersedia.
     */
    public function getBkRole(): Role
    {
        return Role::whereIn('slug', ['guru_bk', 'bk'])->first()
            ?? Role::firstOrCreate(
                ['slug' => 'guru_bk'],
                ['name' => 'Guru BK', 'description' => 'Guru Bimbingan dan Konseling siswa']
            );
    }

    /**
     * Sinkronkan penugasan kelas binaan Guru BK dari penugasan jam mata pelajaran BK.
     *
     * @param  string|null  $academicYear  Tahun pelajaran yang disinkronkan (opsional)
     * @return int Jumlah rombel yang berhasil disinkronkan
     */
    public function syncFromSubjectAssignments(?string $academicYear = null): int
    {
        $bkRole = $this->getBkRole();

        $query = TeacherSubjectAssignment::with(['subject', 'teacher', 'classroom'])
            ->whereNotNull('classroom_id')
            ->whereHas('subject', fn ($q) => $q->bk());

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        $subjectAssignments = $query->get();
        $syncedCount = 0;

        DB::transaction(function () use ($subjectAssignments, $bkRole, &$syncedCount) {
            foreach ($subjectAssignments as $sa) {
                if (! $sa->teacher_id || ! $sa->classroom_id) {
                    continue;
                }

                $ayId = AcademicYear::where('name', $sa->academic_year)->value('id');
                $noteText = 'Otomatis dari Mapel BK ('.$sa->hours_per_week.' JTM)';

                // Cek penugasan BK yang sudah ada untuk kelas & tahun ajaran ini
                $existing = UserRoleAssignment::where('role_id', $bkRole->id)
                    ->where('context_type', Classroom::class)
                    ->where('context_id', $sa->classroom_id)
                    ->where('academic_year', $sa->academic_year)
                    ->where('status', true)
                    ->first();

                if (! $existing) {
                    // Belum ada penugasan sama sekali -> buat baru
                    UserRoleAssignment::create([
                        'user_id' => $sa->teacher_id,
                        'role_id' => $bkRole->id,
                        'academic_year' => $sa->academic_year,
                        'academic_year_id' => $ayId,
                        'context_type' => Classroom::class,
                        'context_id' => $sa->classroom_id,
                        'notes' => $noteText,
                        'status' => true,
                        'assigned_by' => auth()->id() ?? 1,
                    ]);
                    $syncedCount++;
                } elseif ($existing->user_id === $sa->teacher_id) {
                    // Sudah ditugaskan ke guru yang sama -> perbarui catatan jika belum ada
                    if (empty($existing->notes) || str_contains($existing->notes, 'Guru BK Rombel')) {
                        $existing->update(['notes' => $noteText]);
                    }
                    $syncedCount++;
                } else {
                    // Kelas ini dipegang guru lain:
                    // Jika penugasan sebelumnya otomatis dari mapel BK, ganti ke guru baru ini
                    if ($existing->notes && str_contains($existing->notes, 'Otomatis dari Mapel BK')) {
                        $existing->update([
                            'user_id' => $sa->teacher_id,
                            'notes' => $noteText,
                            'academic_year_id' => $ayId,
                            'assigned_by' => auth()->id() ?? 1,
                        ]);
                        $syncedCount++;
                    }
                    // Jika penugasan sebelumnya memiliki nomor SK manual, hormati penetapan manual (tidak ditimpa)
                }
            }
        });

        return $syncedCount;
    }

    /**
     * Sinkronkan otomatis saat ada penugasan mapel BK baru ditambahkan/diperbarui.
     */
    public function syncOnSubjectAssignmentSaved(TeacherSubjectAssignment $sa): void
    {
        if (! $sa->classroom_id || ! $sa->teacher_id) {
            return;
        }

        $subject = $sa->subject ?? Subject::find($sa->subject_id);
        if (! $subject || ! $subject->isBk()) {
            return;
        }

        $bkRole = $this->getBkRole();
        $ayId = AcademicYear::where('name', $sa->academic_year)->value('id');
        $noteText = 'Otomatis dari Mapel BK ('.$sa->hours_per_week.' JTM)';

        UserRoleAssignment::updateOrCreate(
            [
                'user_id' => $sa->teacher_id,
                'role_id' => $bkRole->id,
                'academic_year' => $sa->academic_year,
                'context_type' => Classroom::class,
                'context_id' => $sa->classroom_id,
            ],
            [
                'academic_year_id' => $ayId,
                'notes' => $noteText,
                'status' => true,
                'assigned_by' => auth()->id() ?? 1,
            ]
        );
    }

    /**
     * Bersihkan otomatis penugasan kelas binaan jika penugasan mapel BK dihapus.
     */
    public function syncOnSubjectAssignmentDeleted(TeacherSubjectAssignment $sa): void
    {
        if (! $sa->classroom_id || ! $sa->teacher_id) {
            return;
        }

        $subject = $sa->subject ?? Subject::find($sa->subject_id);
        if (! $subject || ! $subject->isBk()) {
            return;
        }

        // Cek apakah guru masih memiliki penugasan mapel BK lain di kelas dan tahun yang sama
        $stillHasBkAssignment = TeacherSubjectAssignment::where('teacher_id', $sa->teacher_id)
            ->where('classroom_id', $sa->classroom_id)
            ->where('academic_year', $sa->academic_year)
            ->where('id', '!=', $sa->id)
            ->whereHas('subject', fn ($q) => $q->bk())
            ->exists();

        if (! $stillHasBkAssignment) {
            $bkRole = $this->getBkRole();

            // Hanya hapus jika penugasan tersebut dibuat secara otomatis oleh sistem (mengandung 'Otomatis dari Mapel BK')
            UserRoleAssignment::where('user_id', $sa->teacher_id)
                ->where('role_id', $bkRole->id)
                ->where('academic_year', $sa->academic_year)
                ->where('context_type', Classroom::class)
                ->where('context_id', $sa->classroom_id)
                ->where(function ($q) {
                    $q->where('notes', 'like', '%Otomatis dari Mapel BK%')
                        ->orWhereNull('sk_number');
                })
                ->delete();
        }
    }
}
