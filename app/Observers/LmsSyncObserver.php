<?php

namespace App\Observers;

use App\Jobs\DispatchLmsWebhookJob;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Support\Facades\DB;

class LmsSyncObserver
{
    public function saved(object $model): void
    {
        if (trim((string) config('services.lms.token', '')) === '') {
            return;
        }

        if ($model instanceof Student) {
            if (empty($model->nisn)) {
                return;
            }

            $event = $model->wasChanged('classroom_id') ? 'student.mutated' : 'student.updated';
            $payload = [
                'siap_student_id' => $model->id,
                'nisn' => $model->nisn,
                'nis' => $model->nis,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone_student,
                'father_name' => $model->father_name,
                'father_phone' => $model->father_phone,
                'mother_name' => $model->mother_name,
                'mother_phone' => $model->mother_phone,
                'guardian_name' => $model->guardian_name,
                'guardian_phone' => $model->guardian_phone,
                'phone_parent' => $model->phone_parent,
                'classroom_siap_id' => $model->classroom_id,
                'status' => $model->status ?? 'aktif',
                'is_active' => (bool) $model->is_active,
            ];

            $this->dispatchAfterCommit($event, $payload);

            return;
        }

        if ($model instanceof Classroom) {
            $model->loadMissing(['gradeLevel:id,name', 'department:id,name']);
            $this->dispatchAfterCommit('classroom.updated', [
                'siap_id' => $model->id,
                'academic_year_id' => $model->academic_year_id,
                'name' => $model->name,
                'grade_level' => $model->gradeLevel?->name,
                'department' => $model->department?->name,
            ]);

            return;
        }

        if ($model instanceof TeacherSubjectAssignment) {
            $yearId = AcademicYear::where('name', $model->academic_year)->value('id');
            $this->dispatchAfterCommit('assignment.updated', [
                'siap_assignment_id' => $model->id,
                'teacher_siap_user_id' => $model->teacher_id,
                'subject_siap_id' => $model->subject_id,
                'classroom_siap_id' => $model->classroom_id,
                'academic_year_siap_id' => $yearId,
                'academic_year' => $model->academic_year,
                'semester' => $model->semester,
            ]);

            return;
        }

        if ($model instanceof AcademicYear) {
            if ($model->is_active && ($model->wasRecentlyCreated || $model->wasChanged('is_active'))) {
                $this->dispatchAfterCommit('academic_year.activated', [
                    'siap_id' => $model->id,
                    'name' => $model->name,
                    'semester' => $model->semester ?? 'Ganjil',
                ]);
            }
        }
    }

    public function created(object $model): void
    {
        if (! $model instanceof AttendanceLog || $model->student_id === null) {
            return;
        }

        if (trim((string) config('services.lms.token', '')) === '') {
            return;
        }

        $model->loadMissing('student:id,nisn');
        if (empty($model->student?->nisn)) {
            return;
        }

        $activeYear = AcademicYear::getActive();
        $rawStatus = strtolower((string) ($model->status ?? ''));
        $status = in_array($rawStatus, ['late', 'terlambat'], true) ? 'terlambat' : 'hadir';

        $this->dispatchAfterCommit('attendance.recorded', [
            'siap_attendance_id' => $model->id,
            'siap_student_id' => $model->student_id,
            'nisn' => $model->student->nisn,
            'academic_year_siap_id' => $activeYear?->id,
            'attendance_date' => $model->scanned_at?->toDateString() ?? now()->toDateString(),
            'status' => $status,
            'check_in_time' => $model->scanned_at?->format('H:i:s'),
            'notes' => $model->method_label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchAfterCommit(string $event, array $payload): void
    {
        DB::afterCommit(static function () use ($event, $payload): void {
            DispatchLmsWebhookJob::dispatch($event, $payload);
        });
    }
}
