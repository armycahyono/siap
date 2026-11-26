<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LmsIntegrationController extends Controller
{
    public function verifySsoCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:32', 'max:128'],
        ]);

        $userId = Cache::pull("lms_sso_code:{$validated['code']}");

        if (! $userId) {
            return response()->json([
                'message' => 'Authorization code is invalid or expired.',
            ], 401);
        }

        $user = User::with('role')->find($userId);

        if (! $user || ! $user->is_active) {
            return response()->json([
                'message' => 'User account is not active.',
            ], 403);
        }

        $activeYear = AcademicYear::getActive();

        return response()->json([
            'siap_user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'phone' => $user->phone,
            'base_role' => $user->isSuperAdmin() ? 'admin' : 'guru',
            'assignments' => $this->resolveUserAssignments($user, $activeYear),
        ]);
    }

    public function academicYears(Request $request): JsonResponse
    {
        $query = AcademicYear::query()->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (AcademicYear $year) => [
                'siap_id' => $year->id,
                'name' => $year->name,
                'semester' => $year->semester ?? 'Ganjil',
                'is_active' => (bool) $year->is_active,
            ]),
        ]);
    }

    public function teachers(Request $request): JsonResponse
    {
        $activeYear = AcademicYear::getActive();
        $query = User::query()
            ->with('role')
            ->where('is_active', true)
            ->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (User $user) => [
                'siap_user_id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $user->nip,
                'phone' => $user->phone,
                'base_role' => $user->isSuperAdmin() ? 'admin' : 'guru',
                'is_active' => (bool) $user->is_active,
                'assignments' => $this->resolveUserAssignments($user, $activeYear),
            ]),
        ]);
    }

    public function classrooms(Request $request): JsonResponse
    {
        $query = Classroom::query()
            ->with(['gradeLevel:id,name', 'department:id,name'])
            ->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Classroom $classroom) => [
                'siap_id' => $classroom->id,
                'academic_year_id' => $classroom->academic_year_id,
                'name' => $classroom->name,
                'grade_level' => $classroom->gradeLevel?->name,
                'department' => $classroom->department?->name,
            ]),
        ]);
    }

    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::query()->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Subject $subject) => [
                'siap_id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
            ]),
        ]);
    }

    public function teachingAssignments(Request $request): JsonResponse
    {
        $yearsByName = AcademicYear::query()->pluck('id', 'name');
        $query = TeacherSubjectAssignment::query()->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (TeacherSubjectAssignment $assignment) => [
                'siap_assignment_id' => $assignment->id,
                'teacher_siap_user_id' => $assignment->teacher_id,
                'subject_siap_id' => $assignment->subject_id,
                'classroom_siap_id' => $assignment->classroom_id,
                'academic_year_siap_id' => $yearsByName[$assignment->academic_year] ?? null,
                'academic_year' => $assignment->academic_year,
                'semester' => $assignment->semester,
            ]),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $query = Student::query()
            ->whereNotNull('nisn')
            ->where('nisn', '!=', '')
            ->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Student $student) => [
                'siap_student_id' => $student->id,
                'nisn' => $student->nisn,
                'nis' => $student->nis,
                'name' => $student->name,
                'email' => $student->email,
                'phone' => $student->phone_student,
                'classroom_siap_id' => $student->classroom_id,
                'status' => $student->status ?? 'aktif',
                'is_active' => (bool) $student->is_active,
            ]),
        ]);
    }

    /**
     * @return array<int, array{role_type: string, classroom_siap_id: int|null}>
     */
    private function resolveUserAssignments(User $user, ?AcademicYear $activeYear): array
    {
        $assignments = [];

        if ($user->isPrincipal()) {
            $assignments[] = ['role_type' => 'kepsek', 'classroom_siap_id' => null];
        }

        if ($user->isWakaKurikulum($activeYear?->name)) {
            $assignments[] = ['role_type' => 'waka_kurikulum', 'classroom_siap_id' => null];
        }

        $homeroomClassrooms = Classroom::query()
            ->where('homeroom_teacher_id', $user->id)
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->pluck('id');

        foreach ($homeroomClassrooms as $classroomId) {
            $assignments[] = [
                'role_type' => 'wali_kelas',
                'classroom_siap_id' => $classroomId,
            ];
        }

        return $assignments;
    }

    private function parseUpdatedSince(Request $request): ?Carbon
    {
        $raw = $request->query('updated_since');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return Carbon::parse($raw);
    }
}
