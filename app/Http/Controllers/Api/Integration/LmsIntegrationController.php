<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceLog;
use App\Models\BkService;
use App\Models\BkStudentAchievement;
use App\Models\BkStudentDevelopmentNote;
use App\Models\Classroom;
use App\Models\CurriculumEvent;
use App\Models\PiketIncident;
use App\Models\PiketStudentPermit;
use App\Models\StructuralPosition;
use App\Models\Student;
use App\Models\StudentVisitation;
use App\Models\Subject;
use App\Models\SubjectAttendanceRecord;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        if ($user->isStaff()) {
            return response()->json([
                'message' => 'Staff accounts are not permitted to access LMS.',
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
            'base_role' => $user->isSystemAdmin() ? 'admin' : 'guru',
            'assignments' => $this->resolveUserAssignments($user, $activeYear),
        ]);
    }

    public function academicYears(Request $request): JsonResponse
    {
        $query = AcademicYear::query()->orderBy('name');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $query->whereIn('id', $window->pluck('id'));
        }

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
            ->where(function ($q) {
                $q->whereNull('position')
                    ->orWhereRaw('LOWER(position) != ?', ['staf']);
            })
            ->whereDoesntHave('role', fn ($q) => $q->where('slug', 'staf'))
            ->orderBy('id');

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        $users = $query->get()
            ->reject(fn (User $user) => $user->isStaff())
            ->values();

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'siap_user_id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $user->nip,
                'phone' => $user->phone,
                'base_role' => $user->isSystemAdmin() ? 'admin' : 'guru',
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

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $query->whereIn('academic_year_id', $window->pluck('id'));
        }

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

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $query->whereIn('academic_year', $window->pluck('name'));
        }

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

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $windowIds = $window->pluck('id');
            $query->where(function ($q) use ($windowIds): void {
                $q->whereHas('classroom', fn ($cq) => $cq->whereIn('academic_year_id', $windowIds))
                    ->orWhere(fn ($nq) => $nq->whereNull('classroom_id')->where('updated_at', '>=', now()->subYear()));
            });
        }

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
                'father_name' => $student->father_name,
                'father_phone' => $student->father_phone,
                'mother_name' => $student->mother_name,
                'mother_phone' => $student->mother_phone,
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'phone_parent' => $student->phone_parent,
                'classroom_siap_id' => $student->classroom_id,
                'status' => $student->status ?? 'aktif',
                'is_active' => (bool) $student->is_active,
            ]),
        ]);
    }

    public function attendances(Request $request): JsonResponse
    {
        $activeYear = AcademicYear::getActive();
        $date = $request->query('date');
        $nisn = trim((string) $request->query('nisn', ''));

        $query = AttendanceLog::query()
            ->with(['student:id,nisn,name,classroom_id', 'student.classroom:id,academic_year_id'])
            ->whereNotNull('student_id')
            ->whereHas('student', function ($q) use ($nisn): void {
                $q->whereNotNull('nisn')->where('nisn', '!=', '');
                if ($nisn !== '') {
                    $q->where('nisn', $nisn);
                }
            })
            ->orderBy('scanned_at');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $windowIds = $window->pluck('id');
            $query->whereHas('student.classroom', fn ($cq) => $cq->whereIn('academic_year_id', $windowIds));
        }

        if (is_string($date) && trim($date) !== '') {
            $query->whereDate('scanned_at', Carbon::parse($date)->toDateString());
        } elseif ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        $grouped = $query->get()
            ->groupBy(fn (AttendanceLog $log): string => $log->student_id.'|'.$log->scanned_at->toDateString());

        $data = $grouped->map(function ($logs) use ($activeYear): array {
            /** @var AttendanceLog $first */
            $first = $logs->first();
            $checkInTime = $first->scanned_at->format('H:i:s');
            $rawStatus = strtolower((string) ($first->status ?? ''));
            $status = in_array($rawStatus, ['late', 'terlambat'], true) ? 'terlambat' : 'hadir';

            return [
                'siap_attendance_id' => $first->id,
                'siap_student_id' => $first->student_id,
                'nisn' => $first->student?->nisn,
                'academic_year_siap_id' => $first->student?->classroom?->academic_year_id ?? $activeYear?->id,
                'attendance_date' => $first->scanned_at->toDateString(),
                'status' => $status,
                'check_in_time' => $checkInTime,
                'notes' => $first->method_label,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function subjectAttendances(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $nisn = trim((string) $request->query('nisn', ''));

        $windowNames = $this->resolveWindowAcademicYears($request)?->pluck('name');

        $query = SubjectAttendanceRecord::query()
            ->with(['student:id,nisn', 'session'])
            ->whereHas('student', function ($q) use ($nisn): void {
                $q->whereNotNull('nisn')->where('nisn', '!=', '');
                if ($nisn !== '') {
                    $q->where('nisn', $nisn);
                }
            })
            ->whereHas('session', function ($q) use ($date, $windowNames): void {
                if ($windowNames) {
                    $q->whereIn('academic_year', $windowNames);
                }
                if (is_string($date) && trim($date) !== '') {
                    $q->whereDate('attendance_date', Carbon::parse($date)->toDateString());
                }
            })
            ->orderBy('id');

        if ((! is_string($date) || trim($date) === '') && ($since = $this->parseUpdatedSince($request))) {
            $query->where('updated_at', '>=', $since);
        }

        $data = $query->get()->map(function (SubjectAttendanceRecord $record): array {
            $session = $record->session;

            return [
                'siap_record_id' => $record->id,
                'siap_student_id' => $record->student_id,
                'nisn' => $record->student?->nisn,
                'classroom_siap_id' => $session?->classroom_id,
                'subject_siap_id' => $session?->subject_id,
                'teacher_siap_user_id' => $session?->teacher_id,
                'academic_year' => $session?->academic_year,
                'semester' => $session?->semester,
                'attendance_date' => $session?->attendance_date?->toDateString(),
                'start_period' => $session?->start_period,
                'end_period' => $session?->end_period,
                'status' => $record->status,
                'topic' => $session?->topic,
                'notes' => $record->notes,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function visitations(Request $request): JsonResponse
    {
        $query = StudentVisitation::query()
            ->with(['student:id,nisn', 'primaryOfficer:id,name'])
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->orderBy('id');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $windowIds = $window->pluck('id');
            $windowNames = $window->pluck('name');
            $query->where(function ($q) use ($windowIds, $windowNames): void {
                $q->whereIn('academic_year_id', $windowIds)
                    ->orWhereIn('academic_year', $windowNames);
            });
        }

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (StudentVisitation $v) => [
                'siap_visitation_id' => $v->id,
                'siap_student_id' => $v->student_id,
                'nisn' => $v->student?->nisn,
                'academic_year_siap_id' => $v->academic_year_id,
                'academic_year' => $v->academic_year,
                'visit_type' => $v->visit_type,
                'visit_type_label' => $v->visit_type_label,
                'reason' => $v->reason,
                'officer_name' => $v->primaryOfficer?->name,
                'planned_date' => $v->planned_date?->toDateString(),
                'executed_date' => $v->executed_date?->toDateString(),
                'status' => $v->status,
                'result_summary' => $v->result_summary,
                'follow_up_plan' => $v->follow_up_plan,
            ])->values(),
        ]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $query = PiketIncident::query()
            ->with(['student:id,nisn', 'recordedBy:id,name'])
            ->whereNotNull('student_id')
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->orderBy('id');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $query->whereIn('academic_year', $window->pluck('name'));
        }

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (PiketIncident $inc) => [
                'siap_incident_id' => $inc->id,
                'siap_student_id' => $inc->student_id,
                'nisn' => $inc->student?->nisn,
                'academic_year' => $inc->academic_year,
                'incident_date' => $inc->incident_date?->toDateString(),
                'incident_time' => $inc->incident_time,
                'location' => $inc->location,
                'category' => $inc->category,
                'description' => $inc->description,
                'action_taken' => $inc->action_taken,
                'follow_up_target' => $inc->follow_up_target,
                'disciplinary_sanction' => $inc->disciplinary_sanction,
                'parental_involvement' => (bool) $inc->parental_involvement,
                'parental_involvement_notes' => $inc->parental_involvement_notes,
                'status' => $inc->status,
                'recorder_name' => $inc->recordedBy?->name,
            ])->values(),
        ]);
    }

    public function permits(Request $request): JsonResponse
    {
        $query = PiketStudentPermit::query()
            ->with(['student:id,nisn', 'givenBy:id,name'])
            ->whereNotNull('student_id')
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->orderBy('id');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $windowIds = $window->pluck('id');
            $query->whereHas('classroom', fn ($cq) => $cq->whereIn('academic_year_id', $windowIds));
        }

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (PiketStudentPermit $permit) => [
                'siap_permit_id' => $permit->id,
                'siap_student_id' => $permit->student_id,
                'nisn' => $permit->student?->nisn,
                'permit_number' => $permit->permit_number,
                'permit_date' => $permit->permit_date?->toDateString(),
                'end_date' => $permit->end_date?->toDateString(),
                'permit_type' => $permit->permit_type,
                'permit_type_label' => $permit->permit_type_label,
                'permit_category' => $permit->permit_category,
                'reason' => $permit->reason,
                'entry_time' => $permit->entry_time,
                'departure_time' => $permit->departure_time,
                'return_time' => $permit->return_time,
                'action_taken' => $permit->action_taken,
                'status' => $permit->status,
                'giver_name' => $permit->givenBy?->name,
            ])->values(),
        ]);
    }

    public function bkRecords(Request $request): JsonResponse
    {
        $since = $this->parseUpdatedSince($request);
        $window = $this->resolveWindowAcademicYears($request);
        $windowIds = $window?->pluck('id');
        $windowNames = $window?->pluck('name');
        $records = collect();

        $achievements = BkStudentAchievement::query()
            ->with(['student:id,nisn', 'recorder:id,name'])
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->when($window, fn ($q) => $q->where(fn ($sub) => $sub->whereIn('academic_year_id', $windowIds)->orWhereIn('academic_year', $windowNames)))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        foreach ($achievements as $ach) {
            $records->push([
                'record_type' => 'achievement',
                'siap_record_id' => $ach->id,
                'siap_student_id' => $ach->student_id,
                'nisn' => $ach->student?->nisn,
                'academic_year_siap_id' => $ach->academic_year_id,
                'academic_year' => $ach->academic_year,
                'record_date' => $ach->event_date?->toDateString(),
                'category' => $ach->category_label,
                'level' => $ach->level_label,
                'title' => $ach->title,
                'summary' => trim(($ach->achievement_result ? $ach->achievement_result.' — ' : '').($ach->notes ?? '')),
                'recommendation' => $ach->organizer,
                'status' => $ach->type,
                'recorded_by_name' => $ach->recorder?->name,
            ]);
        }

        $notes = BkStudentDevelopmentNote::query()
            ->with(['student:id,nisn', 'recorder:id,name'])
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->when($window, fn ($q) => $q->where(fn ($sub) => $sub->whereIn('academic_year_id', $windowIds)->orWhereIn('academic_year', $windowNames)))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        foreach ($notes as $note) {
            $records->push([
                'record_type' => 'development_note',
                'siap_record_id' => $note->id,
                'siap_student_id' => $note->student_id,
                'nisn' => $note->student?->nisn,
                'academic_year_siap_id' => $note->academic_year_id,
                'academic_year' => $note->academic_year,
                'record_date' => $note->note_date?->toDateString(),
                'category' => $note->category_label,
                'level' => null,
                'title' => $note->title,
                'summary' => $note->description,
                'recommendation' => $note->follow_up_recommendation,
                'status' => null,
                'recorded_by_name' => $note->recorder?->name,
            ]);
        }

        $services = BkService::query()
            ->with(['student:id,nisn', 'counselor:id,name'])
            ->whereNotNull('student_id')
            ->whereHas('student', fn ($q) => $q->whereNotNull('nisn')->where('nisn', '!=', ''))
            ->when($window, fn ($q) => $q->whereIn('academic_year', $windowNames))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        foreach ($services as $svc) {
            $records->push([
                'record_type' => 'counseling_service',
                'siap_record_id' => $svc->id,
                'siap_student_id' => $svc->student_id,
                'nisn' => $svc->student?->nisn,
                'academic_year_siap_id' => null,
                'academic_year' => $svc->academic_year,
                'record_date' => $svc->service_date?->toDateString(),
                'category' => $svc->service_type,
                'level' => null,
                'title' => 'Layanan Konseling BK',
                'summary' => trim(($svc->summary ?? '').($svc->result ? ' | Hasil: '.$svc->result : '')),
                'recommendation' => $svc->follow_up_plan,
                'status' => $svc->follow_up_status,
                'recorded_by_name' => $svc->counselor?->name,
            ]);
        }

        return response()->json(['data' => $records->values()]);
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $query = CurriculumEvent::query()
            ->orderBy('start_date')
            ->orderBy('id');

        if ($window = $this->resolveWindowAcademicYears($request)) {
            $query->whereIn('academic_year', $window->pluck('name'));
        }

        if ($since = $this->parseUpdatedSince($request)) {
            $query->where('updated_at', '>=', $since);
        }

        return response()->json([
            'data' => $query->get()->map(fn (CurriculumEvent $event) => [
                'siap_event_id' => $event->id,
                'academic_year' => $event->academic_year,
                'semester' => $event->semester ?? null,
                'title' => $event->title,
                'description' => $event->description ?? null,
                'category' => $event->category,
                'category_label' => $event->category_label,
                'start_date' => $event->start_date?->toDateString(),
                'end_date' => $event->end_date?->toDateString(),
                'start_time' => $event->start_time ?? null,
                'end_time' => $event->end_time ?? null,
                'is_all_day' => (bool) ($event->is_all_day ?? true),
                'is_holiday' => (bool) ($event->is_holiday ?? false),
                'color' => $event->color ?? null,
                'location' => $event->location ?? null,
            ])->values(),
        ]);
    }

    /**
     * @return array<int, array{role_type: string, classroom_siap_id: int|null}>
     */
    private function resolveUserAssignments(User $user, ?AcademicYear $activeYear): array
    {
        $assignments = [];
        $addedKeys = [];

        $addAssignment = function (string $roleType, ?int $classroomId = null) use (&$assignments, &$addedKeys): void {
            $key = $roleType.':'.($classroomId ?? 'null');
            if (isset($addedKeys[$key])) {
                return;
            }

            $addedKeys[$key] = true;
            $assignments[] = [
                'role_type' => $roleType,
                'classroom_siap_id' => $classroomId,
            ];
        };

        if ($user->isPrincipal()) {
            $addAssignment('kepsek');
        }

        if ($user->isWakaKurikulum($activeYear?->name)) {
            $addAssignment('waka_kurikulum');
        }

        if (! $user->isStaff() && ! $user->isSystemAdmin()) {
            $structuralQuery = TeacherStructuralAssignment::query()
                ->with('structuralPosition')
                ->where('teacher_id', $user->id)
                ->whereHas('structuralPosition', fn ($q) => $q
                    ->where('is_active', true)
                    ->where('type', StructuralPosition::TYPE_STRUKTURAL));

            if ($activeYear) {
                $structuralQuery->activeForAcademicYear($activeYear->name);
            } else {
                $structuralQuery->where('is_active', true);
            }

            $codeMap = [
                'KS' => 'kepsek',
                'WAKA_KUR' => 'waka_kurikulum',
            ];

            foreach ($structuralQuery->get() as $structuralAssignment) {
                $code = strtoupper((string) $structuralAssignment->structuralPosition?->code);
                if ($code === '') {
                    continue;
                }

                $roleType = $codeMap[$code] ?? strtolower($code);
                $addAssignment($roleType);
            }
        }

        $homeroomClassrooms = Classroom::query()
            ->where('homeroom_teacher_id', $user->id)
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->pluck('id');

        foreach ($homeroomClassrooms as $classroomId) {
            $addAssignment('wali_kelas', $classroomId);
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

    /**
     * Resolve the 3-year operational window (previous, active, next).
     * Returns null when scope=all is requested.
     *
     * @return Collection<int, AcademicYear>|null
     */
    private function resolveWindowAcademicYears(Request $request): ?Collection
    {
        if (strtolower(trim((string) $request->query('scope', 'window'))) === 'all') {
            return null;
        }

        $years = AcademicYear::query()->orderBy('name')->get(['id', 'name', 'is_active']);
        if ($years->isEmpty()) {
            return $years;
        }

        $activeIndex = $years->search(fn (AcademicYear $year): bool => (bool) $year->is_active);
        if ($activeIndex === false) {
            $activeIndex = $years->count() - 1;
        }

        $start = max(0, $activeIndex - 1);
        $length = min($years->count() - $start, ($activeIndex - $start) + 2);

        return $years->slice($start, $length)->values();
    }
}
