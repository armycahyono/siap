<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AppFeedback;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\SchoolActivity;
use App\Models\SchoolStakeholder;
use App\Models\StructuralPosition;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervision;
use App\Models\TeacherStructuralAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartSearchController extends Controller
{
    /**
     * Handle real-time smart search query across application scopes.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $scope = $request->input('scope');
        $academicYear = $request->input('academic_year') ?: session('active_academic_year');

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'query' => $query,
                'count' => 0,
                'results' => [],
            ]);
        }

        if (! $scope || $scope === 'auto') {
            $scope = $this->detectScopeFromReferer($request);
        }

        $results = match ($scope) {
            'students' => $this->searchStudents($query, $academicYear),
            'teachers' => $this->searchTeachers($query),
            'subjects' => $this->searchSubjects($query),
            'classrooms' => $this->searchClassrooms($query, $academicYear),
            'departments' => $this->searchDepartments($query),
            'stakeholders' => $this->searchStakeholders($query),
            'structural_positions' => $this->searchStructuralPositions($query),
            'assignments' => $this->searchAssignments($query, $academicYear),
            'schedules', 'supervisions' => $this->searchSupervisions($query, $academicYear),
            'activity_logs' => $this->searchActivityLogs($query),
            'active_users' => $this->searchActiveUsers($query),
            'feedback' => $this->searchFeedback($query),
            'activities' => $this->searchActivities($query),
            default => $this->searchGlobal($query, $academicYear),
        };

        return response()->json([
            'success' => true,
            'query' => $query,
            'scope' => $scope,
            'count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Search teachers and staff.
     */
    protected function searchTeachers(string $query): array
    {
        $teachingRoles = ['guru', 'supervisor', 'waka_kurikulum', 'kepala_sekolah', 'operator_kurikulum'];

        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('slug', $teachingRoles))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('nip', 'like', "%{$query}%")
                    ->orWhere('nigs_fix', 'like', "%{$query}%")
                    ->orWhere('nigs', 'like', "%{$query}%")
                    ->orWhere('nuptk', 'like', "%{$query}%")
                    ->orWhere('major', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->with(['role', 'subjectAssignments.subject'])
            ->take(8)
            ->get();

        return $teachers->map(function (User $teacher) {
            $mapelList = $teacher->subjectAssignments->pluck('subject.name')->filter()->unique()->take(2)->implode(', ');
            $idStr = $teacher->nigs_fix ?: ($teacher->nip ?: ($teacher->username ?? '-'));

            return [
                'id' => $teacher->id,
                'title' => $teacher->name,
                'subtitle' => ($mapelList ? "Mapel: {$mapelList} • " : '')."ID: {$idStr}",
                'badge' => $teacher->role?->name ?? 'Guru',
                'badge_class' => 'bg-blue-lt text-blue',
                'icon' => 'ti ti-user',
                'fill_value' => $teacher->name,
                'url' => route('teachers.index', ['search' => $teacher->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search subjects.
     */
    protected function searchSubjects(string $query): array
    {
        $subjects = Subject::with('department')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%");
            })
            ->take(8)
            ->get();

        return $subjects->map(function (Subject $subject) {
            $deptStr = $subject->department?->code ? " • {$subject->department->code}" : '';

            return [
                'id' => $subject->id,
                'title' => "{$subject->code} - {$subject->name}",
                'subtitle' => "Kategori: {$subject->category}{$deptStr}",
                'badge' => $subject->category ?: 'Mapel',
                'badge_class' => 'bg-green-lt text-green',
                'icon' => 'ti ti-book',
                'fill_value' => $subject->name,
                'url' => route('subjects.index', ['search' => $subject->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search classrooms and rombel.
     */
    protected function searchClassrooms(string $query, ?string $academicYear): array
    {
        $queryBuilder = Classroom::with(['gradeLevel', 'department', 'homeroomTeacher'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('academic_year', 'like', "%{$query}%")
                    ->orWhereHas('homeroomTeacher', fn ($t) => $t->where('name', 'like', "%{$query}%"));
            });

        if ($academicYear) {
            $queryBuilder->where('academic_year', $academicYear);
        }

        return $queryBuilder->take(8)->get()->map(function (Classroom $c) {
            $homeroom = $c->homeroomTeacher?->name ? "Wali: {$c->homeroomTeacher->name}" : 'Wali: Belum ditetapkan';

            return [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => "TP. {$c->academic_year} • {$homeroom}",
                'badge' => $c->department?->code ?? 'Rombel',
                'badge_class' => 'bg-purple-lt text-purple',
                'icon' => 'ti ti-school',
                'fill_value' => $c->name,
                'url' => route('classrooms.index', ['search' => $c->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search departments.
     */
    protected function searchDepartments(string $query): array
    {
        $departments = Department::with('headOfDepartment')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->take(8)
            ->get();

        return $departments->map(function (Department $dept) {
            $headStr = $dept->headOfDepartment?->name ? "Kaprog: {$dept->headOfDepartment->name}" : 'Kaprog: -';

            return [
                'id' => $dept->id,
                'title' => "{$dept->code} - {$dept->name}",
                'subtitle' => $headStr,
                'badge' => 'Jurusan',
                'badge_class' => 'bg-azure-lt text-azure',
                'icon' => 'ti ti-building',
                'fill_value' => $dept->name,
                'url' => route('departments.index', ['search' => $dept->code]),
            ];
        })->values()->toArray();
    }

    /**
     * Search school stakeholders.
     */
    protected function searchStakeholders(string $query): array
    {
        $stakeholders = SchoolStakeholder::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('position', 'like', "%{$query}%")
                ->orWhere('institution', 'like', "%{$query}%")
                ->orWhere('nip', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        })->take(8)->get();

        return $stakeholders->map(function (SchoolStakeholder $sh) {
            $inst = $sh->institution ? " • {$sh->institution}" : '';

            return [
                'id' => $sh->id,
                'title' => $sh->name,
                'subtitle' => "{$sh->position}{$inst}",
                'badge' => $sh->category_label,
                'badge_class' => $sh->category_badge,
                'icon' => $sh->category_icon,
                'fill_value' => $sh->name,
                'url' => route('stakeholders.index', ['search' => $sh->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search structural positions.
     */
    protected function searchStructuralPositions(string $query): array
    {
        $positions = StructuralPosition::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%");
        })->take(8)->get();

        return $positions->map(function (StructuralPosition $sp) {
            return [
                'id' => $sp->id,
                'title' => $sp->name,
                'subtitle' => "Kode: {$sp->code} • Kategori: {$sp->category}",
                'badge' => $sp->category ?: 'Struktural',
                'badge_class' => 'bg-teal-lt text-teal',
                'icon' => 'ti ti-award',
                'fill_value' => $sp->name,
                'url' => route('structural-positions.index', ['search' => $sp->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search teacher assignments (mapel & struktural).
     */
    protected function searchAssignments(string $query, ?string $academicYear): array
    {
        $results = [];

        // Search subject assignments
        $mapelAssigns = TeacherSubjectAssignment::with(['teacher', 'subject', 'classroom'])
            ->where(function ($q) use ($query) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', "%{$query}%")->orWhere('code', 'like', "%{$query}%"))
                    ->orWhereHas('classroom', fn ($c) => $c->where('name', 'like', "%{$query}%"));
            });

        if ($academicYear) {
            $mapelAssigns->where('academic_year', $academicYear);
        }

        foreach ($mapelAssigns->take(5)->get() as $ma) {
            $tName = $ma->teacher?->name ?? 'Guru';
            $sName = $ma->subject?->name ?? 'Mapel';
            $cName = $ma->classroom?->name ?? 'Semua Kelas';

            $results[] = [
                'id' => $ma->id,
                'title' => "{$tName} — {$sName}",
                'subtitle' => "Kelas: {$cName} • {$ma->hours_per_week} JP (TP. {$ma->academic_year})",
                'badge' => 'Penugasan Mapel',
                'badge_class' => 'bg-blue-lt text-blue',
                'icon' => 'ti ti-book-2',
                'fill_value' => $tName,
                'url' => route('teacher-assignments.index', ['tab' => 'mapel', 'search' => $tName]),
            ];
        }

        // Search structural assignments
        $structAssigns = TeacherStructuralAssignment::with(['teacher', 'structuralPosition'])
            ->where(function ($q) use ($query) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('structuralPosition', fn ($p) => $p->where('name', 'like', "%{$query}%")->orWhere('code', 'like', "%{$query}%"));
            });

        if ($academicYear) {
            $structAssigns->where('academic_year', $academicYear);
        }

        foreach ($structAssigns->take(4)->get() as $sa) {
            $tName = $sa->teacher?->name ?? 'Guru';
            $pName = $sa->structuralPosition?->name ?? 'Jabatan';

            $results[] = [
                'id' => $sa->id,
                'title' => "{$tName} — {$pName}",
                'subtitle' => "SK: {$sa->sk_number} (TP. {$sa->academic_year})",
                'badge' => 'Struktural',
                'badge_class' => 'bg-indigo-lt text-indigo',
                'icon' => 'ti ti-award',
                'fill_value' => $tName,
                'url' => route('teacher-assignments.index', ['tab' => 'struktural', 'search' => $tName]),
            ];
        }

        return $results;
    }

    /**
     * Search supervisions and schedules.
     */
    protected function searchSupervisions(string $query, ?string $academicYear): array
    {
        $supervisions = Supervision::with(['teacher', 'supervisor'])
            ->where(function ($q) use ($query) {
                $q->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('supervisor', fn ($s) => $s->where('name', 'like', "%{$query}%"))
                    ->orWhere('subject', 'like', "%{$query}%")
                    ->orWhere('class_name', 'like', "%{$query}%")
                    ->orWhere('report_number', 'like', "%{$query}%");
            });

        if ($academicYear) {
            $supervisions->where('academic_year', $academicYear);
        }

        return $supervisions->take(8)->get()->map(function (Supervision $spv) {
            $teacherName = $spv->teacher?->name ?? 'Guru';
            $supervisorName = $spv->supervisor?->name ?? 'Supervisor';
            $dateStr = $spv->schedule_date ? Carbon::parse($spv->schedule_date)->translatedFormat('d M Y') : '-';
            $scoreText = $spv->percentage ? " • Nilai: {$spv->percentage}% ({$spv->predicate})" : '';

            return [
                'id' => $spv->id,
                'title' => "{$teacherName} — {$spv->subject}",
                'subtitle' => "Kelas: {$spv->class_name} • SPV: {$supervisorName} • {$dateStr}{$scoreText}",
                'badge' => ucfirst(str_replace('_', ' ', $spv->status)),
                'badge_class' => 'bg-azure-lt text-azure',
                'icon' => 'ti ti-calendar-event',
                'fill_value' => $teacherName,
                'url' => route('schedules.index', ['search' => $teacherName]),
            ];
        })->values()->toArray();
    }

    /**
     * Search activity logs.
     */
    protected function searchActivityLogs(string $query): array
    {
        $logs = UserActivityLog::where(function ($q) use ($query) {
            $q->where('description', 'like', "%{$query}%")
                ->orWhere('user_name', 'like', "%{$query}%")
                ->orWhere('user_username', 'like', "%{$query}%")
                ->orWhere('action', 'like', "%{$query}%")
                ->orWhere('ip_address', 'like', "%{$query}%");
        })->latest()->take(8)->get();

        return $logs->map(function (UserActivityLog $log) {
            $timeStr = $log->created_at ? $log->created_at->translatedFormat('d M Y, H:i') : '-';

            return [
                'id' => $log->id,
                'title' => $log->user_name ?: 'Sistem / Tamu',
                'subtitle' => "{$log->description} ({$timeStr})",
                'badge' => $log->action_label,
                'badge_class' => $log->action_badge_class,
                'icon' => 'ti ti-activity',
                'fill_value' => $log->user_name ?: $log->description,
                'url' => route('activity-logs.index', ['search' => $log->user_name ?: $log->action]),
            ];
        })->values()->toArray();
    }

    /**
     * Search active users.
     */
    protected function searchActiveUsers(string $query): array
    {
        $users = User::with('role')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->take(8)
            ->get();

        return $users->map(function (User $u) {
            return [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => "Username: {$u->username} • Email: {$u->email}",
                'badge' => $u->role?->name ?? 'User',
                'badge_class' => 'bg-secondary-lt text-secondary',
                'icon' => 'ti ti-user-check',
                'fill_value' => $u->name,
                'url' => route('active-users.index', ['search' => $u->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search feedback.
     */
    protected function searchFeedback(string $query): array
    {
        $feedbacks = AppFeedback::with('user')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('message', 'like', "%{$query}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$query}%"));
            })
            ->latest()
            ->take(8)
            ->get();

        return $feedbacks->map(function (AppFeedback $fb) {
            $sender = $fb->user?->name ?? 'Pengguna';

            return [
                'id' => $fb->id,
                'title' => $fb->title,
                'subtitle' => "Pengirim: {$sender} • {$fb->category}",
                'badge' => ucfirst($fb->status ?? 'Terkirim'),
                'badge_class' => 'bg-yellow-lt text-yellow',
                'icon' => 'ti ti-message-report',
                'fill_value' => $fb->title,
                'url' => route('feedback.index', ['search' => $fb->title]),
            ];
        })->values()->toArray();
    }

    /**
     * Search school activities.
     */
    protected function searchActivities(string $query): array
    {
        $activities = SchoolActivity::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%");
        })->take(8)->get();

        return $activities->map(function (SchoolActivity $act) {
            return [
                'id' => $act->id,
                'title' => $act->name,
                'subtitle' => "Kode: {$act->code} • {$act->category_label}",
                'badge' => $act->category_label,
                'badge_class' => $act->category_badge,
                'icon' => 'ti ti-calendar',
                'fill_value' => $act->name,
                'url' => route('activities.index', ['search' => $act->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Search students scoped by academic year.
     */
    protected function searchStudents(string $query, ?string $academicYear): array
    {
        $activeAy = $academicYear ? AcademicYear::where('name', $academicYear)->first() : null;

        $studentQuery = Student::with(['classroom.homeroomTeacher', 'studentClassrooms'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('nisn', 'like', "%{$query}%")
                    ->orWhere('nis', 'like', "%{$query}%")
                    ->orWhere('nik', 'like', "%{$query}%");
            });

        if ($academicYear) {
            $studentQuery->where(function ($sq) use ($academicYear, $activeAy) {
                if ($activeAy) {
                    $sq->whereHas('studentClassrooms', function ($scq) use ($activeAy) {
                        $scq->where('academic_year_id', $activeAy->id)->where('status', 'aktif');
                    });
                }
                $sq->orWhereHas('classroom', function ($cq) use ($academicYear) {
                    $cq->where('academic_year', $academicYear);
                });
            });
        }

        return $studentQuery->take(8)->get()->map(function (Student $s) {
            $cls = $s->classroom?->name ?? ($s->last_classroom_name ?? 'Belum ada kelas');
            $nisn = $s->nisn ? "NISN: {$s->nisn}" : "NIS: {$s->nis}";

            return [
                'id' => $s->id,
                'title' => $s->name,
                'subtitle' => "Kelas: {$cls} • {$nisn}",
                'badge' => $s->entry_year ? "Akt. {$s->entry_year}" : ($s->status_label ?? 'Siswa'),
                'badge_class' => 'bg-blue-lt text-primary',
                'icon' => 'ti ti-school',
                'fill_value' => $s->name,
                'url' => route('students.index', ['search' => $s->name]),
            ];
        })->values()->toArray();
    }

    /**
     * Global aggregated search across primary modules.
     */
    protected function searchGlobal(string $query, ?string $academicYear): array
    {
        $students = array_slice($this->searchStudents($query, $academicYear), 0, 3);
        $teachers = array_slice($this->searchTeachers($query), 0, 2);
        $subjects = array_slice($this->searchSubjects($query), 0, 2);
        $classrooms = array_slice($this->searchClassrooms($query, $academicYear), 0, 2);
        $supervisions = array_slice($this->searchSupervisions($query, $academicYear), 0, 2);

        return array_merge($students, $teachers, $subjects, $classrooms, $supervisions);
    }

    /**
     * Auto-detect search scope based on HTTP Referer URL.
     */
    protected function detectScopeFromReferer(Request $request): string
    {
        $referer = $request->header('referer') ?: $request->url();
        $path = parse_url($referer, PHP_URL_PATH) ?? '';

        if (str_contains($path, '/students') || str_contains($path, '/data-siswa')) {
            return 'students';
        }
        if (str_contains($path, '/teachers')) {
            return 'teachers';
        }
        if (str_contains($path, '/subjects')) {
            return 'subjects';
        }
        if (str_contains($path, '/classrooms')) {
            return 'classrooms';
        }
        if (str_contains($path, '/departments')) {
            return 'departments';
        }
        if (str_contains($path, '/stakeholders')) {
            return 'stakeholders';
        }
        if (str_contains($path, '/structural-positions')) {
            return 'structural_positions';
        }
        if (str_contains($path, '/penugasan-guru')) {
            return 'assignments';
        }
        if (str_contains($path, '/schedules')) {
            return 'schedules';
        }
        if (str_contains($path, '/supervisions') || str_contains($path, '/laporan')) {
            return 'supervisions';
        }
        if (str_contains($path, '/log-aktivitas')) {
            return 'activity_logs';
        }
        if (str_contains($path, '/pengguna-aktif')) {
            return 'active_users';
        }
        if (str_contains($path, '/kegiatan-sekolah')) {
            return 'activities';
        }
        if (str_contains($path, '/feedback')) {
            return 'feedback';
        }

        return 'global';
    }
}
